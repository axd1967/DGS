<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


require_once 'include/quick_common.php';
require_once 'include/std_classes.php';
require_once 'include/std_functions.php';
require_once 'include/rating.php';


/* SGF specs (version 4):
   Setup properties must not be mixed with move properties within a node.
   Docs: http://www.red-bean.com/sgf/

   http://www.red-bean.com/sgf/sgf4.html
   http://www.red-bean.com/sgf/user_guide/index.html#gameinfo
   http://www.red-bean.com/sgf/properties.html

   Coordinates for stone/point: http://www.red-bean.com/sgf/go.html
   Property types : root, move, setup, gameinfo, -

ID    Description          type        property value
----  -------------------  ----------- --------------------------------------
AP    application          root        "name:version"
CA    charset              root        charset (RFC 1345); default "Latin1" = "ISO-8859-1"
FF    file format          root        integer : 1-4; default "1"
GM    game type            root        integer : 1=Go, default "1"
ST    style view tree      root        0-3, default "0"
SZ    board size           root        integer : 1-52, "cols:rows" for rectangular boards

AN    annotator name       game-info   string
BR    Black Rank           game-info   string : 9k, 3d, ?, 4k*
WR    White Rank           game-info   string : 9k, 3d, ?, 4k*
BT    Black team name      game-info   string
WT    White team name      game-info   string
CP    copyright            game-info   string
DT    date of game         game-info   list of string : "YYYY-MM-DD", abbreviations allowed
EV    event name           game-info   string, e.g. tournament-name without round-info
GN    game name            game-info   string : can indicate filename
GC    general comment      game-info   string
HA    handicap             game-info   integer : >=2
KM    komi                 game-info   real : -7, 3.5, 0
ON    opening played       game-info   string
OT    overtime info        game-info   string : freetext, re-defined in FF[5]
PB    Black player name    game-info   string
PW    White player name    game-info   string
PC    place                game-info   string
RE    result               game-info   B|W+real, W+0.5, 0=jigo, B+R|Resign, W+T|Time, B+F|Forfeit, ?, Void=no-result/suspended
RO    round                game-info   string : "round (type)"
RU    rules                game-info   string : AGA,GOE,Japanese,NZ
SO    source               game-info   string
TM    main-time            game-info   real : >0, seconds
US    user/program of SGF  game-info   string

AB    add black stone      setup       list of stone
AW    add white stone      setup       list of stone
AE    add empty stone      setup       list of point
PL    player to move       setup       B|W

B     black move           move        stone, "" for pass-move or "tt" for size<=19
W     white move           move        dito
KO    do illegal move      move        "" : ko
MN    set move number      move        integer
BL    black time left      move        real : seconds
WL    white time left      move        real : seconds
OB    stones left black    move        integer : number of stones left in overtime
OW    stones left white    move        integer : number of stones left in overtime
TB    black territory      -           point
TW    white territory      -           point
C     game-comment         -           string
N     node name            -           string

# markup properties:
AR (arrow), CR (circle-mark), DD (dim stones), LB (labels), LN (line), MA (X-mark),
SL (select-mark), SQ (square-mark), TR (triangle-mark)

# more '-'-type properties:
DM (even position), GB (good for B), GW (good for W), HO (hotspot), UC (unclear), V (value)

# more move-type properties:
BM (bad move), DO (doubtful move), IT (interesting move), TE (tesuji)

# printing properties:
FG    figure for printing  -           integer : mask, see http://www.red-bean.com/sgf/properties.html#FG
PM    print number         -           0-2, default "1"
VW    view board part      -           list of points

*/



 /*!
  * \class SgfBuilder
  *
  * \brief Convenience Class to build SGF.
  */
class SgfBuilder
{
   // config vars for SGF-formatting

   var $sgf_version;
   var $charset;

   var $use_HA;
   var $use_AB_for_handicap;

   var $include_games_notes; // && owned_comments set
   var $include_node_name;

   //0=no highlight, 1=with Name property, 2=in comments, 3=both
   var $sgf_pass_highlight;
   var $sgf_score_highlight;

   // load data from database

   var $gid;
   var $game_row;
   var $moves_iterator;
   var $player_notes;

   // runtime vars

   var $sgf_trim_nr;
   var $node_com;
   var $array;
   var $dead_stone_prop;
   var $points;
   var $next_color;

   var $prop_type;
   var $dirx;
   var $diry;

   var $use_buffer; // bool
   var $SGF; // output-buffer


   function SgfBuilder( $gid, $use_buffer )
   {
      //As board size may be > 'tt' coord, we can't use [tt] for pass moves
      // so we use [] and, then, we need at least sgf_version = 4 (FF[4])
      $this->sgf_version = 4;

      /*
         WARNING: Some fields could cause problems because of charset:
         - those coming from user (like $Blackname)
         - those translated (like score2text(), echo_rating() or echo_time_limit())
         (but, actually, the translation database is not available here)
         We could use the CA[] (FF[4]) property if we know what it is.
      */
      //$this->charset = 'UTF-8'; //by default
      $this->charset = '';

      $this->use_HA = true;
      $this->use_AB_for_handicap = true;

      $this->include_games_notes = true; // && owned_comments set
      $this->include_node_name = false;

      //0=no highlight, 1=with Name property, 2=in comments, 3=both
      $this->sgf_pass_highlight = 0;
      $this->sgf_score_highlight = 0;


      $this->gid = $gid;
      $this->game_row = array();
      $this->moves_iterator = new ListIterator( "SgfBuilder.load_moves_text($gid)" );
      $this->player_notes = '';


      $this->sgf_trim_nr = -1;
      $this->node_com = '';
      $this->array = array();
      $this->dead_stone_prop = '';
      $this->points = array();
      $this->next_color = 'B';

      $this->prop_type = 'root';

      // {--- from old board.php (before board class)
      $this->dirx = array( -1,0,1,0 );
      $this->diry = array( 0,-1,0,1 );

      $this->use_buffer = $use_buffer;
      $this->SGF = '';
   }

   function echo_sgf( $sgf_text )
   {
      if( $this->use_buffer )
         $this->SGF .= $sgf_text;
      else
         echo $sgf_text;
   }

   function sgf_echo_prop( $prop )
   {
      //if( stristr('-B-W-MN-BL-WL-KO-BM-DO-IT-OB-OW-TE-', $prop.'-') )
      if( stristr('-B-W-MN-', '-'.$prop.'-') )
      {
         if( $this->prop_type == 'setup' )
            $this->echo_sgf( "\n;" . $prop );
         else
            $this->echo_sgf( $prop );
         $this->prop_type = 'move';
      }
      else if( stristr('-AB-AE-AW-PL-', '-'.$prop.'-') )
      {
         if( $this->prop_type == 'move' )
            $this->echo_sgf( "\n;" . $prop );
         else
            $this->echo_sgf( $prop );
         $this->prop_type = 'setup';
      }
      else
         $this->echo_sgf( $prop );
   }

   function sgf_echo_comment( $com, $trim=true )
   {
      if( !$com )
         return false;
      $comment_trimmed = ($trim) ? ltrim( $com, "\r\n" ) : $com;
      $this->echo_sgf( "\nC[" .
         str_replace( "]","\\]",
            str_replace("\\","\\\\", reverse_htmlentities($comment_trimmed) ))
         . "]" );
      return true;
   }

   /* Possible properties are: (uppercase only)
    * - AB/AW/AE: add black stone/white stone/empty point (setup properties)
    * - MA/CR/TR/SQ: mark with a cross/circle/triangle/square (SQ is FF[4])
    * - TB/TW: mark territory black/white
    */
   function sgf_echo_point( $points, $overwrite_prop=false )
   {
      if( count($points) <= 0 )
         return false;

      if( $overwrite_prop )
      {
         $prop = $overwrite_prop;
         $this->echo_sgf( "\n" );
         $this->sgf_echo_prop( $prop );
      }
      else
      {
         asort($points);
         $prop = false;
      }

      foreach($points as $coord => $point_prop)
      {
         if( !$overwrite_prop && $prop !== $point_prop )
         {
            if( !$point_prop )
               continue;
            $prop = $point_prop;
            $this->echo_sgf( "\n" );
            $this->sgf_echo_prop( $prop );
         }
         $this->echo_sgf( "[$coord]" );
      }

      return true;
   }

   // note: from old board.php (before board class), incl. dirx/diry-arr
   function mark_territory( $x, $y, $size, &$array )
   {
      $c = -1;  // color of territory

      $index[$x][$y] = 7;
      $point_count = 1; //for the current point (theoricaly NONE)

      while( true )
      {
         if( $index[$x][$y] >= 32 )  // Have looked in all directions
         {
            $m = $index[$x][$y] % 8;

            if( $m == 7 )   // At starting point, all checked
            {
               if( $c == -1 )
                  $c = DAME ;
               else
                  $c |= OFFSET_TERRITORY ;

               if( $c == DAME || $point_count > MAX_SEKI_MARK )
                  $c |= FLAG_NOCLICK ;

               while( list($x, $sub) = each($index) )
               {
                  while( list($y, $val) = each($sub) )
                  {
                     //keep all marks unchanged and reversible
                     if( @$array[$x][$y] < MARKED_DAME )
                        $array[$x][$y] = $c;
                  }
               }

               return $point_count;
            }

            $x -= $this->dirx[$m];  // Go back
            $y -= $this->diry[$m];
         }
         else
         {
            $dir = (int)($index[$x][$y] / 8);
            $index[$x][$y] += 8;

            $nx = $x + $this->dirx[$dir];
            $ny = $y + $this->diry[$dir];

            if( ( $nx < 0 ) || ($nx >= $size) || ($ny < 0) || ($ny >= $size) || isset($index[$nx][$ny]) )
               continue;

            $new_color = @$array[$nx][$ny];

            if( !$new_color || $new_color == NONE || $new_color >= BLACK_DEAD )
            {
               $x = $nx;  // Go to the neighbour
               $y = $ny;
               $index[$x][$y] = $dir;
               $point_count++;
            }
            else //remains BLACK/WHITE/DAME/BLACK_TERRITORY/WHITE_TERRITORY and MARKED_DAME
            {
               if( $new_color == MARKED_DAME )
                  $c = NONE; // This area will become dame
               else if( $c == -1 )
                  $c = $new_color;
               else if( $c == (WHITE+BLACK-$new_color) )
                  $c = NONE; // This area has both colors as boundary
            }
         }
      }
   }//mark_territory


   function sgf_create_territories( $size, &$array,
         &$black_territory, &$white_territory, &$black_prisoner, &$white_prisoner )
   {
      // mark territories

      for( $x=0; $x<$size; $x++)
      {
         for( $y=0; $y<$size; $y++)
         {
            if( !@$array[$x][$y] || $array[$x][$y] == NONE )
            {
               $this->mark_territory( $x, $y, $size, $array );
            }
         }
      }

      // split

      for( $x=0; $x<$size; $x++)
      {
         for( $y=0; $y<$size; $y++)
         {
            $coord = chr($x + ord('a')) . chr($y + ord('a'));
            switch( (int)( @$array[$x][$y] & ~FLAG_NOCLICK) )
            {
               case WHITE_DEAD:
                  $black_prisoner[$coord]='AE';
               case BLACK_TERRITORY:
                  $black_territory[$coord]='TB';
                  break;

               case BLACK_DEAD:
                  $white_prisoner[$coord]='AE';
               case WHITE_TERRITORY:
                  $white_territory[$coord]='TW';
                  break;
            }
         }
      }
   }//sgf_create_territories



   function load_game_info()
   {
      $result = db_query( "SgfBuilder.load_game_info({$this->gid})",
         'SELECT Games.*, ' .
         'UNIX_TIMESTAMP(Games.Starttime) AS startstamp, ' .
         'UNIX_TIMESTAMP(Games.Lastchanged) AS timestamp, ' .
         'black.Sessioncode AS Blackscode, ' .
         'white.Sessioncode AS Whitescode, ' .
         'UNIX_TIMESTAMP(black.Sessionexpire) AS Blackexpire, ' .
         'UNIX_TIMESTAMP(white.Sessionexpire) AS Whiteexpire, ' .
         'black.ID AS Black_uid, ' .
         'black.Name AS Blackname, ' .
         'black.Handle AS Blackhandle, ' .
         "IF(Games.Status='FINISHED', Games.Black_End_Rating, black.Rating2 ) AS Blackrating, " .
         'white.ID AS White_uid, ' .
         'white.Name AS Whitename, ' .
         'white.Handle AS Whitehandle, ' .
         "IF(Games.Status='FINISHED', Games.White_End_Rating, white.Rating2 ) AS Whiterating " .
         'FROM (Games, Players AS black, Players AS white) ' .
         "WHERE Games.ID={$this->gid} AND Black_ID=black.ID AND White_ID=white.ID LIMIT 1"
         );
      if( @mysql_num_rows($result) != 1 )
         error('unknown_game', "SgfBuilder.load_game_info2({$this->gid})");

      $this->game_row = mysql_fetch_array($result);
      return $this->game_row;
   }

   function load_player_game_notes( $uid )
   {
      // load GamesNotes for player
      $this->player_notes = '';
      if( $this->include_games_notes )
      {
         $gn_row = mysql_single_fetch( "SgfBuilder.load_player_game_notes.find_notes({$this->gid},$uid)",
            "SELECT Notes FROM GamesNotes WHERE gid={$this->gid} AND uid='$uid' LIMIT 1" );
         if( is_array($gn_row) )
            $this->player_notes = @$gn_row['Notes'];
      }
   }

   function load_trimmed_moves( $with_comments )
   {
      if( $with_comments )
      {
         $moves_query =
            "SELECT Moves.*,MoveMessages.Text " .
            "FROM Moves LEFT JOIN MoveMessages " .
               "ON MoveMessages.gid={$this->gid} AND MoveMessages.MoveNr=Moves.MoveNr " .
            "WHERE Moves.gid={$this->gid} ORDER BY Moves.ID";
      }
      else
      {
         $moves_query =
            "SELECT Moves.*, '' AS Text " .
            "FROM Moves WHERE Moves.gid={$this->gid} ORDER BY Moves.ID";
      }
      $result = db_query( "SgfBuilder.load_trimmed_moves({$this->gid})", $moves_query );

      $this->moves_iterator->setResultRows( @mysql_num_rows($result) );

      // possibly skip some moves
      $this->sgf_trim_nr = $this->moves_iterator->ResultRows - 1 ;
      if( $this->game_row['Status'] == 'FINISHED' && isset($this->game_row['Score']) )
      {
         $score = $this->game_row['Score'];

         //skip the ending moves where PosX <= $sgf_trim_level
         //-1=POSX_PASS= skip ending pass, -2=POSX_SCORE= keep them ... -999= keep everything
         if( abs($score) < SCORE_RESIGN ) // real-score
            $sgf_trim_level = POSX_SCORE; // keep PASSes for better SGF=DGS-move-numbering
         else if( abs($score) == SCORE_TIME )
            $sgf_trim_level = POSX_RESIGN;
         else
            $sgf_trim_level = POSX_SCORE;

         while( $this->sgf_trim_nr >= 0 )
         {
            if( !mysql_data_seek($result, $this->sgf_trim_nr) )
               break;
            if( !$row = mysql_fetch_array($result) )
               break;
            if( $row['PosX'] > $sgf_trim_level && ($row['Stone'] == WHITE || $row['Stone'] == BLACK) )
               break;
            $this->sgf_trim_nr-- ;
         }

         if( $this->moves_iterator->ResultRows > 0 )
            mysql_data_seek($result, 0);
      }

      $this->moves_iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
         $this->moves_iterator->addItem( null, $row );
      mysql_free_result($result);
   }//load_trimmed_moves

   function build_filename_sgf( $bulk_filename )
   {
      if( $bulk_filename )
      {
         // DGS-<gid>_YYYY-MM-DD_<rated=R|F><size>(H<handi>)K<komi>(=<result>)_<white>-<black>.sgf
         $f_rated = ( $this->game_row['Rated'] == 'N' ) ? 'F' : 'R';
         $f_size = $this->game_row['Size'];
         $f_handi = ( $this->game_row['Handicap'] > 0 ) ? 'H' . $this->game_row['Handicap'] : '';
         $f_komi = 'K' . str_replace( '.', ',', $this->game_row['Komi'] );
         $f_result = '';
         if( $this->game_row['Status'] == 'FINISHED' )
         {
            $f_result = '=' . ( $this->game_row['Score'] < 0 ? 'B' : 'W' );
            if( abs($this->game_row['Score']) == SCORE_TIME )
               $f_result .= 'T';
            elseif( abs($this->game_row['Score']) == SCORE_RESIGN )
               $f_result .= 'R';
            else
               $f_result .= str_replace( '.', ',', abs($this->game_row['Score']) );
         }
         $filename = "DGS-{$this->gid}_" . date('Y-m-d', $this->game_row['timestamp'])
            . "_{$f_rated}{$f_size}{$f_handi}{$f_komi}{$f_result}_{$this->game_row['Whitehandle']}-{$this->game_row['Blackhandle']}";
      }
      else
         $filename = "{$this->game_row['Whitehandle']}-{$this->game_row['Blackhandle']}-{$this->gid}-" . date('Ymd', $this->game_row['timestamp']);

      return $filename;
   }

   // owned_comments: BLACK|WHITE=viewed by B/W-player, DAME=viewed by other user
   function build_sgf( $filename, $owned_comments )
   {
      $this->build_sgf_start( $filename );
      $this->build_sgf_moves( $owned_comments ); // loop over Moves
      $this->build_sgf_result();
      $this->build_sgf_end( $owned_comments );
   }

   function build_sgf_start( $filename )
   {
      extract($this->game_row);

      $this->echo_sgf(
         "(\n;FF[{$this->sgf_version}]GM[1]"
         . ( $this->charset ? "CA[{$this->charset}]" : '' )
         . "\nAP[DGS:".DGS_VERSION."]"
         //. "\nCP[".FRIENDLY_LONG_NAME.", ".HOSTBASE."licence.php]" // copyright on games
         . "\nPC[".FRIENDLY_LONG_NAME.": ".HOSTBASE."]"
         . "\nDT[" . date( 'Y-m-d', $startstamp ) . ',' . date( 'Y-m-d', $timestamp ) . "]"
         . "\nGN[" . SgfBuilder::sgf_simpletext($filename) . "]"
         . "\nSO[".HOSTBASE."game.php?gid={$this->gid}]"
         . "\nPB[" . SgfBuilder::sgf_simpletext("$Blackname ($Blackhandle)") . "]"
         . "\nPW[" . SgfBuilder::sgf_simpletext("$Whitename ($Whitehandle)") . "]"
         );

      // ratings
      $this->echo_sgf(
         "\nBR[" . SgfBuilder::sgf_echo_rating($Blackrating) . ']' .
         "\nWR[" . SgfBuilder::sgf_echo_rating($Whiterating) . ']'
         );

      // general comment: game-id, rated-game, start/end-ratings
      $w_rating_start = ( is_valid_rating($White_Start_Rating) ) ? SgfBuilder::sgf_echo_rating($White_Start_Rating,true) : '';
      $general_comment = "Game ID: {$this->gid}"
         . "\nRated: ". ( $Rated=='N' ? 'N' : 'Y' )
         . "\n"
         . ( is_valid_rating($White_Start_Rating)
               ? sprintf( "\nWhite Start Rating: %s - ELO %d",
                            SgfBuilder::sgf_echo_rating($White_Start_Rating,true), $White_Start_Rating )
               : "\nWhite Start Rating: ?" )
         . ( is_valid_rating($Black_Start_Rating)
               ? sprintf( "\nBlack Start Rating: %s - ELO %d",
                            SgfBuilder::sgf_echo_rating($Black_Start_Rating,true), $Black_Start_Rating )
               : "\nBlack Start Rating: ?" );
      if( $Status == 'FINISHED' && isset($Score) )
      {
         $general_comment .=
            ( is_valid_rating($White_End_Rating)
               ? sprintf( "\nWhite End Rating: %s - ELO %d",
                            SgfBuilder::sgf_echo_rating($White_End_Rating,true), $White_End_Rating )
               : "\nWhite End Rating: ?" )
            . ( is_valid_rating($Black_End_Rating)
               ? sprintf( "\nBlack End Rating: %s - ELO %d",
                            SgfBuilder::sgf_echo_rating($Black_End_Rating,true), $Black_End_Rating )
               : "\nBlack End Rating: ?" );
      }
      $this->echo_sgf( "\nGC[$general_comment]" );

      // NOTE: time-properties are noted in seconds, which on turn-based servers
      //       can get very big numbers, disturbing SGF-viewers.
      //       Therefore time-info not included at the moment.
      if( $this->sgf_version >= 4 )
      {// overtime
         $timeprop = TimeFormat::echo_time_limit($Maintime, $Byotype, $Byotime, $Byoperiods, TIMEFMT_ENGL);
         $this->echo_sgf( "\nOT[" . SgfBuilder::sgf_simpletext($timeprop) . "]" );
      }

      $rules = SgfBuilder::sgf_get_ruleset($Ruleset); //Mandatory for Go (GM[1])
      if( $rules )
         $this->echo_sgf( "\nRU[$rules]" );

      $this->echo_sgf(
         "\nSZ[$Size]" .
         "\nKM[$Komi]" );

      if( $Handicap > 0 && $this->use_HA )
         $this->echo_sgf( "\nHA[$Handicap]" );

      if( $Status == 'FINISHED' && isset($Score) )
      {
         $this->echo_sgf( "\nRE[" . SgfBuilder::sgf_simpletext( $Score==0 ? '0' : score2text($Score, false, true)) . "]" );
      }
   }//build_sgf_start

   function build_sgf_moves( $owned_comments )
   {
      extract($this->game_row);

      $this->array = array();
      $this->points = array();
      $this->next_color = 'B';

      //Last dead stones property: (uppercase only)
      // ''= keep them, 'AE'= remove, 'MA'/'CR'/'TR'/'SQ'= mark them
      $this->dead_stone_prop = '';

      //Last marked dame property: (uppercase only)
      // ''= no mark, 'MA'/'CR'/'TR'/'SQ'= mark them
      $marked_dame_prop = '';

      $movenum = 0;
      $movesync = 0;

      $this->moves_iterator->resetListIterator();
      while( list(, $arr_item) = $this->moves_iterator->getListIterator() )
      {
         $row = $arr_item[1];
         $Text = '';
         extract($row); // fields: ID,gid,MoveNr,Stone,PosX,PosY,Hours
         $coord = chr($PosX + ord('a')) . chr($PosY + ord('a'));

         switch( (int)$Stone )
         {
            case MARKED_BY_WHITE:
            case MARKED_BY_BLACK:
            { // toggle marks
               //record last skipped SCORE/SCORE2 marked points
               if( $this->sgf_trim_nr < 0 )
                  @$this->array[$PosX][$PosY] ^= OFFSET_MARKED;

               if( isset($this->points[$coord]) )
               {
                  unset($this->points[$coord]);
               }
               elseif( $this->sgf_trim_nr < 0 )
               {
                  if( @$this->array[$PosX][$PosY] == MARKED_DAME )
                     $this->points[$coord] = $marked_dame_prop;
                  else
                     $this->points[$coord] = $this->dead_stone_prop;
               }
               else
               {
                  if( @$this->array[$PosX][$PosY] == NONE )
                     $this->points[$coord] = 'MA';
                  else
                     $this->points[$coord] = 'MA';
               }

               break;
            }

            case NONE:
            { //+prisoners
               $this->array[$PosX][$PosY] = $Stone;
               break;
            }

            case WHITE:
            case BLACK:
            {
               if( $PosX <= POSX_ADDTIME ) //configuration actions
               {
                  //TODO: include time-info, fields see const-def
                  break;
               }

               $this->array[$PosX][$PosY] = $Stone;

               //keep comments even if in ending pass, SCORE, SCORE2 or resign steps.
               if( $owned_comments == BLACK || $owned_comments == WHITE )
               {
                  if( $Status != 'FINISHED' && $owned_comments != $Stone )
                     $Text = trim(preg_replace("'<h(idden)? *>(.*?)</h(idden)? *>'is", "", $Text));

                  if( $Text )
                     $this->node_com .= "\n" . ( $Stone == WHITE ? $Whitename : $Blackname ) . ': ' . $Text;
               }
               else //SGF query from an observer
               {
                  if( $Status != 'FINISHED' )
                     $Text = preg_replace("'<h(idden)? *>(.*?)</h(idden)? *>'is", "", $Text);

                  $nr_matches = preg_match_all(
                        "'(<c(omment)? *>(.*?)</c(omment)? *>)".
                        "|(<h(idden)? *>(.*?)</h(idden)? *>)'is"
                        , $Text, $matches );
                  for( $i=0; $i < $nr_matches; $i++ )
                  {
                     $Text = trim($matches[3][$i]);
                     if( !$Text )
                        $Text = trim($matches[7][$i]);
                     if( $Text )
                        $this->node_com .= "\n" . ( $Stone == WHITE ? $Whitename : $Blackname ) . ': ' . $Text;
                  }
               }
               $Text = '';

               if( $MoveNr <= $Handicap && $this->use_AB_for_handicap )
               {// handicap
                  $this->points[$coord] = 'AB'; //setup property
                  if( $MoveNr < $Handicap)
                     break;

                  $this->sgf_echo_point( $this->points);
                  $this->points = array();

                  $this->sgf_echo_comment( $this->node_com );
                  $this->node_com = '';
               }
               elseif( $this->sgf_trim_nr >= 0 )
               {// move
                  if( $Stone == WHITE )
                     $color = 'W';
                  else
                     $color = 'B';

                  if( $this->next_color != $color )
                  {
                     $this->sgf_echo_prop('PL'); //setup property
                     $this->echo_sgf( "[$color]" );
                  }


                  if( $Stone == WHITE )
                     $this->next_color = 'B';
                  else
                     $this->next_color = 'W';

                  $this->echo_sgf( "\n;" ); //Node start
                  $this->prop_type = '';

                  // sync move-number
                  if( $MoveNr > $Handicap && $PosX >= POSX_PASS )
                  {
                     $movenum++;
                     if( $MoveNr != $movenum + $movesync )
                     {
                        //useful when "non AB handicap" or "resume after SCORE"
                        $this->sgf_echo_prop('MN'); //move property
                        $this->echo_sgf( "[$movenum]" );
                        $movesync = $MoveNr - $movenum;
                     }
                  }

                  if( $PosX < POSX_PASS )
                  { //score steps, others filtered by sgf_trim_level
                     $this->sgf_echo_prop($color);
                     $this->echo_sgf( "[]" ); // add 3rd pass
                     $this->next_color = SgfBuilder::switch_move_color( $color );

                     if( $this->sgf_score_highlight & 1 )
                        $this->echo_sgf( "N[$color SCORE]" );

                     if( $this->sgf_score_highlight & 2 )
                        $this->node_com .= "\n$color SCORE";

                     $this->sgf_echo_point( $this->points );
                  }
                  else
                  { //pass, normal move or non AB handicap

                     if( $PosX == POSX_PASS )
                     {
                        $this->sgf_echo_prop($color); //move property
                        $this->echo_sgf( "[]" ); //do not use [tt]

                        if( $this->sgf_pass_highlight & 1 )
                           $this->echo_sgf( "N[$color PASS]" );

                        if( $this->sgf_pass_highlight & 2 )
                           $this->node_com .= "\n$color PASS";
                     }
                     else //move or non AB handicap
                     {
                        $this->sgf_echo_prop($color); //move property
                        $this->echo_sgf( "[$coord]" );
                     }

                     $this->points = array();
                  }

                  $this->sgf_echo_comment( $this->node_com );
                  $this->node_com = '';
               }
               break;
            }//case WHITE/BLACK
         }//switch $Stone

         $this->sgf_trim_nr--;
      }
   }//build_sgf_moves

   function build_sgf_result()
   {
      if( $this->game_row['Status'] == 'FINISHED' )
      {
         $this->echo_sgf( "\n;" ); // Node start
         $this->sgf_echo_prop($this->next_color);
         $this->echo_sgf( "[]" ); // add 3rd pass

         if( $this->include_node_name )
            $this->echo_sgf( "N[RESULT]" ); //Node start
         $this->prop_type = '';

         // highlighting result in last comments:
         if( isset($this->game_row['Score']) )
         {
            $score = $this->game_row['Score'];

            $this->node_com .= "\n";

            if( abs($score) < SCORE_RESIGN ) // scor-able
            {
               $black_territory = array();
               $white_territory = array();
               $black_prisoner = array();
               $white_prisoner = array();
               $this->sgf_create_territories( $this->game_row['Size'], $this->array,
                  $black_territory, $white_territory,
                  $black_prisoner, $white_prisoner);

               //Last dead stones mark
               if( $this->dead_stone_prop )
                  $this->sgf_echo_point( array_merge( $black_prisoner, $white_prisoner ), $this->dead_stone_prop );

               //$points from last skipped SCORE/SCORE2 marked points
               $this->sgf_echo_point( array_merge( $this->points, $black_territory, $white_territory ) );

               $this->node_com .= "\n" .
                  SgfBuilder::sgf_count_string( "White"
                     ,count($white_territory)
                     ,$this->game_row['White_Prisoners'] + count($white_prisoner)
                     ,$this->game_row['Komi']
                  );

               $this->node_com .= "\n" .
                  SgfBuilder::sgf_count_string( "Black"
                     ,count($black_territory)
                     ,$this->game_row['Black_Prisoners'] + count($black_prisoner)
                  );

               //$this->node_com .= "\n";
            }
            $this->node_com .= "\nResult: " . score2text($score, false, true) ;
         }
      }
   }//build_sgf_result

   function build_sgf_end( $owned_comments )
   {
      $notes = ( $owned_comments == BLACK || $owned_comments == WHITE ) ? rtrim($this->player_notes) : '';
      if( !empty($notes) )
         $this->node_com .= "\n\nNotes - " . ( $owned_comments == WHITE ? $this->game_row['Whitename'] : $this->game_row['Blackname'] )
                          . ":\n" . $notes ;

      $this->sgf_echo_comment( $this->node_com, false ); // no trim
      $this->node_com = '';

      $this->echo_sgf( "\n)\n" );
   }



   // ------------ static functions ----------------------------

   function sgf_simpletext( $str )
   {
      return
         str_replace( "]", "\\]",
            str_replace( "\\", "\\\\",
               preg_replace( "/[\\x1-\\x20]+/", ' ', reverse_htmlentities( $str ) ) ));
   }

   function switch_move_color( $color )
   {
      return ($color == 'B') ? 'W' : 'B';
   }

   /* Example:
    * > White: 66 territory + 6 prisoners + 0.5 komi = 72.5
    * > Black: 64 territory + 1 prisoner = 65
    */
   function sgf_count_string( $color, $territory, $prisoner, $komi=false )
   {
      $score = 0;
      $str = "$color:";

      $str .= " $territory territor" . ($territory > 1 ? "ies" : "y") ;
      $score += $territory;

      $str .= " + $prisoner prisoner" . ($prisoner > 1 ? "s" : "") ;
      $score += $prisoner;

      if( is_numeric($komi) )
      {
         $str .= " + $komi komi" ;
         $score += $komi;
      }

      $str .= " = $score";
      return $str;
   }

   function sgf_echo_rating( $rating, $show_percent=false )
   {
      $rating_str = echo_rating( $rating, $show_percent, false, true, true );
      if( (string)$rating_str == '' )
         return '?';
      $rating_str = str_ireplace( 'dan#short', 'd', $rating_str );
      $rating_str = str_ireplace( 'kyu#short', 'k', $rating_str );
      return reverse_htmlentities($rating_str);
   }

   /*!
    * \brief Returns ruleset-content for RU[]-tag.
    * \note available
    *  "AGA" (rules of the American Go Association)
    *  "GOE" (the Ing rules of Goe)
    *  "Japanese" (the Nihon-Kiin rule set)
    *  "NZ" (New Zealand rules)
   */
   function sgf_get_ruleset( $ruleset=null )
   {
      static $arr = array(
         RULESET_JAPANESE => 'Japanese',
         RULESET_CHINESE  => 'Chinese',
      );
      return ( !is_null($ruleset) && isset($arr[$ruleset]) ) ? $arr[$ruleset] : 'Japanese';
   }

} // end of 'SgfBuilder'

?>
