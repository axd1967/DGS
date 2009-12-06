<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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


require_once( "include/std_functions.php" );
require_once( "include/rating.php" );

$quick_mode = (boolean)@$_REQUEST['quick_mode'];
if( $quick_mode )
   $TheErrors->set_mode(ERROR_MODE_PRINT);

// can't use html_entity_decode() because of the '&nbsp;' below:
$reverse_htmlentities_table= get_html_translation_table(HTML_ENTITIES); //HTML_SPECIALCHARS or HTML_ENTITIES
$reverse_htmlentities_table= array_flip($reverse_htmlentities_table);
$reverse_htmlentities_table['&nbsp;'] = ' '; //else may be '\xa0' as with html_entity_decode()
function reverse_htmlentities( $str )
{
   global $reverse_htmlentities_table;
   return strtr($str, $reverse_htmlentities_table);
}


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
$prop_type = 'root';
function sgf_echo_prop( $prop )
{
   global $prop_type;

   //if( stristr('-B-W-MN-BL-WL-KO-BM-DO-IT-OB-OW-TE-', $prop.'-') )
   if( stristr('-B-W-MN-', '-'.$prop.'-') )
   {
      if( $prop_type == 'setup' )
         echo "\n;" . $prop;
      else
         echo $prop;
      $prop_type= 'move';
   }
   else if( stristr('-AB-AE-AW-PL-', '-'.$prop.'-') )
   {
      if( $prop_type == 'move' )
         echo "\n;" . $prop;
      else
         echo $prop;
      $prop_type= 'setup';
   }
   else
      echo $prop;
}


function sgf_simpletext( $str )
{
   return str_replace("]","\\]", str_replace("\\","\\\\",
         preg_replace("/[\\x1-\\x20]+/", ' ', reverse_htmlentities( $str )
      ) ) );
}

function sgf_echo_comment( $com, $trim=true )
{
   if( !$com )
      return false;
   $comment_trimmed = ($trim) ? ltrim( $com, "\r\n" ) : $com;
   echo "\nC[",
      str_replace( "]","\\]",
         str_replace("\\","\\\\",
            reverse_htmlentities($comment_trimmed) )),
      "]";
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
      $prop= $overwrite_prop;
      echo "\n";
      sgf_echo_prop( $prop);
   }
   else
   {
      asort($points);
      $prop= false;
   }

   foreach($points as $coord => $point_prop)
   {
      if( !$overwrite_prop && $prop !== $point_prop )
      {
         if( !$point_prop )
            continue;
         $prop= $point_prop;
         echo "\n";
         sgf_echo_prop( $prop);
      }
      echo "[$coord]";
   }

   return true;
}

// {--- from old board.php (before board class)
$dirx = array( -1,0,1,0 );
$diry = array( 0,-1,0,1 );

function mark_territory( $x, $y, $size, &$array )
{
   global $dirx,$diry;

   $c = -1;  // color of territory

   $index[$x][$y] = 7;
   $point_count= 1; //for the current point (theoricaly NONE)

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
               $c|= OFFSET_TERRITORY ;

            if( $c==DAME || $point_count>MAX_SEKI_MARK)
               $c|= FLAG_NOCLICK ;

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

         $x -= $dirx[$m];  // Go back
         $y -= $diry[$m];
      }
      else
      {
         $dir = (int)($index[$x][$y] / 8);
         $index[$x][$y] += 8;

         $nx = $x+$dirx[$dir];
         $ny = $y+$diry[$dir];

         if( ( $nx < 0 ) || ($nx >= $size) || ($ny < 0) || ($ny >= $size) ||
             isset($index[$nx][$ny]) )
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
            {
               $c = NONE; // This area will become dame
            }
            else if( $c == -1 )
            {
               $c = $new_color;
            }
            else if( $c == (WHITE+BLACK-$new_color) )
            {
               $c = NONE; // This area has both colors as boundary
            }
         }
      }
   }
}
// }--- from old board.php (before board class)


//need ./include/board.php
function sgf_create_territories( $size, &$array,
   &$black_territory, &$white_territory,
   &$black_prisoner, &$white_prisoner )
{
   // mark territories

   for( $x=0; $x<$size; $x++)
   {
      for( $y=0; $y<$size; $y++)
      {
         if( !@$array[$x][$y] || $array[$x][$y] == NONE )
         {
            mark_territory( $x, $y, $size, $array );
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
}


/* Example:
> White: 66 territory + 6 prisoners + 0.5 komi = 72.5
> Black: 64 territory + 1 prisoner = 65
*/
function sgf_count_string( $color, $territory, $prisoner, $komi=false )
{
   $score = 0;
   $str = "$color:";

   $str.= " $territory territor" . ($territory > 1 ? "ies" : "y") ;
   $score+= $territory;

   $str.= " + $prisoner prisoner" . ($prisoner > 1 ? "s" : "") ;
   $score+= $prisoner;

   if( is_numeric($komi) )
   {
      $str.= " + $komi komi" ;
      $score+= $komi;
   }

   $str.= " = $score";
   return $str;
}

function switch_move_color( $color )
{
   return ($color == 'B') ? 'W' : 'B';
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


$array=array();


{
   // see the Expires header below and 'no_cache'-URL-arg
   //disable_cache( $NOW, $NOW+5*60);

   connect2mysql();


   //As board size may be > 'tt' coord, we can't use [tt] for pass moves
   // so we use [] and, then, we need at least sgf_version = 4 (FF[4])
   $sgf_version = 4;

   /*
      WARNING: Some fields could cause problems because of charset:
      - those coming from user (like $Blackname)
      - those translated (like score2text(), echo_rating() or echo_time_limit())
      (but, actually, the translation database is not available here)
      We could use the CA[] (FF[4]) property if we know what it is.
   */
   //$charset = 'UTF-8'; //by default
   $charset = '';

   /*
      "AGA" (rules of the American Go Association)
      "GOE" (the Ing rules of Goe)
      "Japanese" (the Nihon-Kiin rule set)
      "NZ" (New Zealand rules)
   */
   $rules = "Japanese"; //Mandatory for Go (GM[1])

   $use_HA = true;
   $use_AB_for_handicap = true;

   $include_games_notes = true; // && owned_comments set
   $include_node_name = false;

   //0=no highlight, 1=with Name property, 2=in comments, 3=both
   $sgf_pass_highlight = 0;
   $sgf_score_highlight = 0;

   //Last dead stones property: (uppercase only)
   // ''= keep them, 'AE'= remove, 'MA'/'CR'/'TR'/'SQ'= mark them
   $dead_stone_prop = '';

   //Last marked dame property: (uppercase only)
   // ''= no mark, 'MA'/'CR'/'TR'/'SQ'= mark them
   $marked_dame_prop = '';


   // parse args
   $gid = (int)@$_GET['gid'];
   if( $gid <= 0 )
   {
      if( eregi("game([0-9]+)", @$_SERVER['REQUEST_URI'], $tmp) )
         $gid = $tmp[1];
   }
   $gid = (int)$gid;
   if( $gid <= 0 )
      error('unknown_game');

   $use_cache = @$_GET['no_cache'];
   #$use_cache = false;

   $owned_comments = @$_GET['owned_comments'];
   if( $owned_comments )
      $field_owned =
         'black.Sessioncode AS Blackscode, ' .
         'white.Sessioncode AS Whitescode, ' .
         'UNIX_TIMESTAMP(black.Sessionexpire) AS Blackexpire, ' .
         'UNIX_TIMESTAMP(white.Sessionexpire) AS Whiteexpire, ' ;
   else
      $field_owned = '';

   $result = db_query( 'sgf.find',
      'SELECT Games.*, ' .
      'UNIX_TIMESTAMP(Games.Starttime) AS startstamp, ' .
      'UNIX_TIMESTAMP(Games.Lastchanged) AS timestamp, ' .
       $field_owned .
      'black.ID AS Black_uid, ' .
      'black.Name AS Blackname, ' .
      'black.Handle AS Blackhandle, ' .
      "IF(Games.Status='FINISHED', Games.Black_End_Rating, black.Rating2 ) AS Blackrating, " .
      'white.ID AS White_uid, ' .
      'white.Name AS Whitename, ' .
      'white.Handle AS Whitehandle, ' .
      "IF(Games.Status='FINISHED', Games.White_End_Rating, white.Rating2 ) AS Whiterating " .
      'FROM (Games, Players AS black, Players AS white) ' .
      "WHERE Games.ID=$gid AND Black_ID=black.ID AND White_ID=white.ID LIMIT 1"
      );

   if( @mysql_num_rows($result) != 1 )
      error('unknown_game');

   $row = mysql_fetch_array($result);
   extract($row);

   // owned_comments: BLACK|WHITE=viewed by B/W-player, DAME=viewed by other user
   $owned_uid = 0;
   if( $owned_comments )
   {
      $owned_comments = DAME;
      if( $Blackhandle == safe_getcookie('handle') )
      {
         if( $Blackscode == safe_getcookie('sessioncode') && $Blackexpire >= $NOW )
         {
            $owned_comments = BLACK ;
            $owned_uid = $Black_uid;
         }
      }
      elseif( $Whitehandle == safe_getcookie('handle') )
      {
         if( $Whitescode == safe_getcookie('sessioncode') && $Whiteexpire >= $NOW )
         {
            $owned_comments = WHITE ;
            $owned_uid = $White_uid;
         }
      }
   }
   else
      $owned_comments = DAME;

   // load GamesNotes for player
   $player_notes = '';
   if( $include_games_notes && ($owned_comments != DAME) && ($owned_uid > 0) )
   {
      $gn_row = mysql_single_fetch( "sgf.notes($gid,$owned_comments,$owned_uid)",
         "SELECT Notes FROM GamesNotes WHERE gid=$gid AND uid='$owned_uid' LIMIT 1" );
      if( is_array($gn_row) )
         $player_notes = @$gn_row['Notes'];
   }

   $node_com = "";

   $result = db_query( "sgf.moves($gid)",
      "SELECT Moves.*,MoveMessages.Text " .
      "FROM Moves LEFT JOIN MoveMessages " .
         "ON MoveMessages.gid=$gid AND MoveMessages.MoveNr=Moves.MoveNr " .
      "WHERE Moves.gid=$gid ORDER BY Moves.ID"
      );

   header( 'Content-Type: application/x-go-sgf' );
   $filename= "$Whitehandle-$Blackhandle-$gid-" . date('Ymd', $timestamp) ;
   //before 2007-10-10: header( "Content-Disposition: inline; filename=\"$filename.sgf\"" );
   header( "Content-Disposition: attachment; filename=\"$filename.sgf\"" );
   header( "Content-Description: PHP Generated Data" );

   //to allow some mime applications to find it in the cache
   if( $use_cache )
   {
      header('Expires: ' . gmdate(GMDATE_FMT, $NOW+5*60));
      header('Last-Modified: ' . gmdate(GMDATE_FMT, $NOW));
   }


   echo "(\n;FF[$sgf_version]GM[1]" . ( $charset ? "CA[$charset]" : '' )
      . "\nAP[DGS:".DGS_VERSION."]"
      //. "\nCP[".FRIENDLY_LONG_NAME.", ".HOSTBASE."licence.php]" // copyright on games
      . "\nPC[".FRIENDLY_LONG_NAME.": ".HOSTBASE."]"
      . "\nDT[" . date( 'Y-m-d', $startstamp ) . ',' . date( 'Y-m-d', $timestamp ) . "]"
      . "\nGN[" . sgf_simpletext($filename) . "]"
      . "\nSO[".HOSTBASE."game.php?gid=$gid]"
      . "\nPB[" . sgf_simpletext("$Blackname ($Blackhandle)") . "]"
      . "\nPW[" . sgf_simpletext("$Whitename ($Whitehandle)") . "]";

   // ratings
   echo "\nBR[", sgf_echo_rating($Blackrating), ']',
        "\nWR[", sgf_echo_rating($Whiterating), ']';

   // general comment: game-id, rated-game, start/end-ratings
   $w_rating_start = ( is_valid_rating($White_Start_Rating) ) ? sgf_echo_rating($White_Start_Rating,true) : '';
   $general_comment = "Game ID: $gid"
      . "\nRated: ". ( $Rated=='N' ? 'N' : 'Y' )
      . "\n"
      . ( is_valid_rating($White_Start_Rating)
            ? sprintf( "\nWhite Start Rating: %s - ELO %d",
                         sgf_echo_rating($White_Start_Rating,true), $White_Start_Rating )
            : "\nWhite Start Rating: ?" )
      . ( is_valid_rating($Black_Start_Rating)
            ? sprintf( "\nBlack Start Rating: %s - ELO %d",
                         sgf_echo_rating($Black_Start_Rating,true), $Black_Start_Rating )
            : "\nBlack Start Rating: ?" );
   if( $Status == 'FINISHED' && isset($Score) )
   {
      $general_comment .=
         ( is_valid_rating($White_End_Rating)
            ? sprintf( "\nWhite End Rating: %s - ELO %d",
                         sgf_echo_rating($White_End_Rating,true), $White_End_Rating )
            : "\nWhite End Rating: ?" )
         . ( is_valid_rating($Black_End_Rating)
            ? sprintf( "\nBlack End Rating: %s - ELO %d",
                         sgf_echo_rating($Black_End_Rating,true), $Black_End_Rating )
            : "\nBlack End Rating: ?" );
   }
   echo "\nGC[$general_comment]";

   // NOTE: time-properties are noted in seconds, which on turn-based servers
   //       can get very big numbers, disturbing SGF-viewers.
   //       Therefore time-info not included at the moment.
   if( $sgf_version >= 4 )
   {// overtime
      $timeprop = TimeFormat::echo_time_limit($Maintime, $Byotype, $Byotime, $Byoperiods, TIMEFMT_ENGL);
      echo "\nOT[", sgf_simpletext($timeprop), "]";
   }

   if( $rules )
      echo "\nRU[$rules]";

   echo "\nSZ[$Size]";
   echo "\nKM[$Komi]";

   if( $Handicap > 0 && $use_HA )
      echo "\nHA[$Handicap]";


   // possibly skip some moves
   $sgf_trim_nr = @mysql_num_rows($result) - 1 ;
   if( $Status == 'FINISHED' && isset($Score) )
   {
      echo "\nRE[" . sgf_simpletext(
         $Score==0 ? '0' : score2text($Score, false, true)
         ) . "]";

      //skip the ending moves where PosX <= $sgf_trim_level
      //-1=POSX_PASS= skip ending pass, -2=POSX_SCORE= keep them ... -999= keep everything
      if( abs($Score) < SCORE_RESIGN ) // real-score
         $sgf_trim_level = POSX_SCORE; // keep PASSes for better SGF=DGS-move-numbering
      else if( abs($Score) == SCORE_TIME )
         $sgf_trim_level = POSX_RESIGN;
      else
         $sgf_trim_level = POSX_SCORE;

      while( $sgf_trim_nr >=0 )
      {
         if( !mysql_data_seek($result, $sgf_trim_nr) )
            break;
         if( !$row = mysql_fetch_array($result) )
            break;
         if( $row["PosX"] > $sgf_trim_level
            && ($row["Stone"] == WHITE || $row["Stone"] == BLACK) )
            break;
         $sgf_trim_nr-- ;
      }
      mysql_data_seek($result, 0) ;
   }


   // loop over Moves

   $movenum= 0; $movesync= 0;
   $points= array();
   $next_color= "B";
   while( $row = mysql_fetch_assoc($result) )
   {
      $Text="";
      extract($row); // fields: ID,gid,MoveNr,Stone,PosX,PosY,Hours
      $coord = chr($PosX + ord('a')) . chr($PosY + ord('a'));

      switch( (int)$Stone )
      {
         case MARKED_BY_WHITE:
         case MARKED_BY_BLACK:
         { // toggle marks
            //record last skipped SCORE/SCORE2 marked points
            if( $sgf_trim_nr < 0 )
               @$array[$PosX][$PosY] ^= OFFSET_MARKED;

            if( isset($points[$coord]) )
            {
               unset($points[$coord]);
            }
            elseif( $sgf_trim_nr < 0 )
            {
               if( @$array[$PosX][$PosY] == MARKED_DAME )
                  $points[$coord]=$marked_dame_prop;
               else
                  $points[$coord]=$dead_stone_prop;
            }
            else
            {
               if( @$array[$PosX][$PosY] == NONE )
                  $points[$coord]='MA';
               else
                  $points[$coord]='MA';
            }

            break;
         }

         case NONE:
         { //+prisoners
            $array[$PosX][$PosY] = $Stone;
            break;
         }

         case WHITE:
         case BLACK:
         {
            if( $PosX <= POSX_ADDTIME ) //configuration actions
            {
               //TODO: POSX_ADDTIME Stone=time-adder, PosY=0|1 (1=byoyomi-reset), Hours=hours added
               break;
            }

            $array[$PosX][$PosY] = $Stone;

            //keep comments even if in ending pass, SCORE, SCORE2 or resign steps.
            if( $owned_comments == BLACK || $owned_comments == WHITE )
            {
               if( $Status != 'FINISHED' && $owned_comments != $Stone )
                  $Text = trim(preg_replace("'<h(idden)? *>(.*?)</h(idden)? *>'is", "", $Text));

               if( $Text )
                  $node_com .= "\n" . ( $Stone == WHITE ? $Whitename : $Blackname ) . ': ' . $Text;
            }
            else //SGF query from an observer
            {
               if( $Status != 'FINISHED' )
                  $Text = preg_replace("'<h(idden)? *>(.*?)</h(idden)? *>'is", "", $Text);

               $nr_matches = preg_match_all(
                     "'(<c(omment)? *>(.*?)</c(omment)? *>)".
                     "|(<h(idden)? *>(.*?)</h(idden)? *>)'is"
                     , $Text, $matches );
               for($i=0; $i<$nr_matches; $i++)
               {
                  $Text = trim($matches[3][$i]);
                  if( !$Text )
                     $Text = trim($matches[7][$i]);
                  if(  $Text )
                     $node_com .= "\n" . ( $Stone == WHITE ? $Whitename : $Blackname ) . ': ' . $Text;
               }
            }
            $Text="";

            if( $MoveNr <= $Handicap && $use_AB_for_handicap )
            {// handicap
               $points[$coord]='AB'; //setup property
               if( $MoveNr < $Handicap)
                  break;

               sgf_echo_point( $points);
               $points= array();

               sgf_echo_comment( $node_com );
               $node_com= "";
            }
            elseif( $sgf_trim_nr >= 0 )
            {// move
               if( $Stone == WHITE )
                  $color='W' ;
               else
                  $color='B' ;

               if( $next_color != $color )
               {
                  sgf_echo_prop('PL'); //setup property
                  echo "[$color]";
               }


               if( $Stone == WHITE )
                  $next_color='B' ;
               else
                  $next_color='W' ;

               echo( "\n;" ); //Node start
               $prop_type ='';

               // sync move-number
               if( $MoveNr > $Handicap && $PosX >= POSX_PASS )
               {
                  $movenum++;
                  if( $MoveNr != $movenum+$movesync)
                  {
                     //useful when "non AB handicap" or "resume after SCORE"
                     sgf_echo_prop('MN'); //move property
                     echo "[$movenum]";
                     $movesync= $MoveNr-$movenum;
                  }
               }

               if( $PosX < POSX_PASS )
               { //score steps, others filtered by $sgf_trim_level
                  sgf_echo_prop($color);
                  echo "[]"; // add 3rd pass
                  $next_color = switch_move_color( $color );

                  if( $sgf_score_highlight & 1 )
                     echo "N[$color SCORE]";

                  if( $sgf_score_highlight & 2 )
                     $node_com .= "\n$color SCORE";

                  sgf_echo_point( $points);
               }
               else
               { //pass, normal move or non AB handicap

                  if( $PosX == POSX_PASS )
                  {
                     sgf_echo_prop($color); //move property
                     echo "[]"; //do not use [tt]

                     if( $sgf_pass_highlight & 1 )
                        echo "N[$color PASS]";

                     if( $sgf_pass_highlight & 2 )
                        $node_com .= "\n$color PASS";
                  }
                  else //move or non AB handicap
                  {
                     sgf_echo_prop($color); //move property
                     echo "[$coord]";
                  }

                  $points= array();
               }

               sgf_echo_comment( $node_com );
               $node_com= "";
            }
            break;
         }//case WHITE/BLACK
      }//switch $Stone

      $sgf_trim_nr--;
   }
   mysql_free_result($result);

   if( $Status == 'FINISHED')
   {
      echo "\n;"; // Node start
      sgf_echo_prop($next_color);
      echo "[]"; // add 3rd pass

      if( $include_node_name )
         echo "N[RESULT]"; //Node start
      $prop_type ='';

      // highlighting result in last comments:
      if( isset($Score) )
      {
         $node_com.= "\n";

         if( abs($Score) < SCORE_RESIGN ) // scor-able
         {
            $black_territory = array();
            $white_territory = array();
            $black_prisoner = array();
            $white_prisoner = array();
            sgf_create_territories( $Size, $array,
               $black_territory, $white_territory,
               $black_prisoner, $white_prisoner);

            //Last dead stones mark
            if( $dead_stone_prop )
               sgf_echo_point(
                  array_merge( $black_prisoner, $white_prisoner)
                  , $dead_stone_prop);

            //$points from last skipped SCORE/SCORE2 marked points
            sgf_echo_point(
               array_merge( $points, $black_territory, $white_territory)
               );

            $node_com.= "\n" .
               sgf_count_string( "White"
                  ,count($white_territory)
                  ,$White_Prisoners + count($white_prisoner)
                  ,$Komi
               );

            $node_com.= "\n" .
               sgf_count_string( "Black"
                  ,count($black_territory)
                  ,$Black_Prisoners + count($black_prisoner)
               );

            //$node_com.= "\n";
         }
         $node_com.= "\nResult: " . score2text($Score, false, true) ;
      }
   }

   if( $owned_comments == BLACK || $owned_comments == WHITE )
     $notes = rtrim($player_notes);
   else
     $notes = '';

   if( !empty($notes) )
     $node_com.= "\n\nNotes - " . ( $owned_comments == WHITE ? $Whitename : $Blackname )
                           . ":\n" . $notes ;

   sgf_echo_comment( $node_com, false ); // no trim
   $node_com= "";

   echo "\n)\n";
}

?>
