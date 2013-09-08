<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once 'include/game_functions.php';
require_once 'include/board.php';
require_once 'include/shape_control.php';
require_once 'tournaments/include/tournament_cache.php';


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
EV    event name           game-info   string, e.g. tournament-name and/or link (without round-info)
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
RO    round                game-info   string : "Round N of M"
RU    rules                game-info   string : AGA,GOE,Japanese,NZ,Chinese
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

# private DGS properties:
XM    move_id              game-info   see specs/quick_suite.txt (3a)

*/



 /*!
  * \class SgfBuilder
  *
  * \brief Convenience Class to build SGF.
  */
class SgfBuilder
{
   // config vars for SGF-formatting

   /*!
    * \brief As board size may be > 'tt' coord, we can't use [tt] for pass moves;
    *    so we use [] and, then, we need at least sgf_version = 4 (FF[4]).
    */
   private $sgf_version = 4;
   /*!
    * \brief
    * \note * WARNING: Some fields could cause problems because of charset:
    * - those coming from user (like $Blackname)
    * - those translated (like score2text(), echo_rating() or echo_time_limit())
    * (but, actually, the translation database is not available here)
    * We could use the CA[] (FF[4]) property if we know what it is.
    */
   private $charset = ''; //$charset = 'UTF-8'; //by default

   private $use_HA = true;
   private $use_AB_for_handicap = true;

   private $include_games_notes = true; // && owned_comments set
   private $include_node_name = false;

   //0=no highlight, 1=with Name property, 2=in comments, 3=both
   private $sgf_pass_highlight = 0;
   private $sgf_score_highlight = 0;

   // multi-player-game options
   private $mpg_node_add_user = true;
   private $mpg_users = array();
   public $mpg_active_user = null; // arr with Players-fields, see GamePlayers::load_users_for_mpgame()
   private $is_mpgame = false;

   private $file_format = null; // null = use default, see build_filename_sgf()

   // load data from database

   private $gid;
   private $game_row = array();
   private $moves_iterator;
   private $player_notes = '';
   private $shape_info = ''; // for shape-game
   private $tourney = null; // Tournament-object

   // runtime vars

   private $sgf_trim_nr = -1;
   private $node_com = '';
   private $array = array();
   private $dead_stone_prop = '';
   private $points = array();
   private $next_color = 'B';
   private $last_prop = '';

   private $prop_type = 'root'; // root | setup | force_node | move

   private $use_buffer; // bool
   private $SGF = ''; // output-buffer


   public function __construct( $gid, $use_buffer )
   {
      $this->gid = $gid;
      $this->moves_iterator = new ListIterator( "SgfBuilder.load_moves_text($gid)" );
      $this->use_buffer = $use_buffer;
   }

   public function is_include_games_notes()
   {
      return $this->include_games_notes;
   }

   public function set_mpg_node_add_user( $mpg_node_add_user )
   {
      $this->mpg_node_add_user = $mpg_node_add_user;
   }

   public function is_mpgame()
   {
      return $this->is_mpgame;
   }

   public function get_sgf()
   {
      return $this->SGF;
   }

   public function set_file_format( $file_format )
   {
      $file_format = trim($file_format);
      if ( (string)$file_format != '' )
         $this->file_format = $file_format;
   }

   private function echo_sgf( $sgf_text, $last_prop=0 )
   {
      if ( $this->use_buffer )
         $this->SGF .= $sgf_text;
      else
         echo $sgf_text;
      if ( $last_prop )
         $this->last_prop = $last_prop;
   }//echo_sgf

   private function sgf_echo_prop( $prop )
   {
      //if ( stristr('-B-W-MN-BL-WL-KO-BM-DO-IT-OB-OW-TE-', $prop.'-') )
      if ( stristr('-B-W-MN-', '-'.$prop.'-') )
      {
         if ( $this->prop_type == 'setup' || $this->prop_type == 'force_node' )
            $this->echo_sgf( "\n;" . $prop );
         else
            $this->echo_sgf( $prop );
         $this->prop_type = 'move';
      }
      else if ( stristr('-AB-AE-AW-PL-', '-'.$prop.'-') )
      {
         if ( $this->prop_type == 'move' || $this->prop_type == 'force_node' )
            $this->echo_sgf( "\n;" . $prop );
         else
            $this->echo_sgf( $prop );
         $this->prop_type = 'setup';
      }
      else
         $this->echo_sgf( $prop );
      $this->last_prop = $prop;
   }//sgf_echo_prop

   private function sgf_echo_comment( $comment, $check_empty=true )
   {
      $comment = trim($comment);
      if ( (string)$comment == '' )
      {
         if ( $check_empty )
            return false;
         elseif ( $this->last_prop == 'C' )
            return false;
      }
      $this->echo_sgf( "\nC[" .
         str_replace( "]","\\]",
            str_replace("\\","\\\\", reverse_htmlentities($comment) ))
         . "]" );
      $this->last_prop = 'C';
      return true;
   }//sgf_echo_comment

   /* Possible properties are: (uppercase only)
    * - AB/AW/AE: add black stone/white stone/empty point (setup properties)
    * - MA/CR/TR/SQ: mark with a cross/circle/triangle/square (SQ is FF[4])
    * - TB/TW: mark territory black/white
    */
   private function sgf_echo_point( $points, $overwrite_prop=false )
   {
      if ( count($points) <= 0 )
         return false;

      if ( $overwrite_prop )
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

      foreach ($points as $coord => $point_prop)
      {
         if ( !$overwrite_prop && $prop !== $point_prop )
         {
            if ( !$point_prop )
               continue;
            $prop = $point_prop;
            $this->echo_sgf( "\n" );
            $this->sgf_echo_prop( $prop );
         }
         $this->echo_sgf( "[$coord]" );
      }

      return true;
   }//sgf_echo_point


   public function load_game_info()
   {
      $result = db_query( "SgfBuilder.load_game_info({$this->gid})",
         'SELECT Games.*, ' .
         'Games.Flags+0 AS X_GameFlags, ' .
         'UNIX_TIMESTAMP(Games.Starttime) AS startstamp, ' .
         'UNIX_TIMESTAMP(Games.Lastchanged) AS timestamp, ' .
         'black.Sessioncode AS Blackscode, ' .
         'white.Sessioncode AS Whitescode, ' .
         'UNIX_TIMESTAMP(black.Sessionexpire) AS Blackexpire, ' .
         'UNIX_TIMESTAMP(white.Sessionexpire) AS Whiteexpire, ' .
         'black.ID AS Black_uid, ' .
         'black.Name AS Blackname, ' .
         'black.Handle AS Blackhandle, ' .
         "IF(Games.Status='".GAME_STATUS_FINISHED."', Games.Black_End_Rating, black.Rating2 ) AS Blackrating, " .
         'white.ID AS White_uid, ' .
         'white.Name AS Whitename, ' .
         'white.Handle AS Whitehandle, ' .
         "IF(Games.Status='".GAME_STATUS_FINISHED."', Games.White_End_Rating, white.Rating2 ) AS Whiterating " .
         'FROM Games ' .
            'LEFT JOIN Players AS black ON black.ID=Games.Black_ID ' .
            'LEFT JOIN Players AS white ON white.ID=Games.White_ID ' .
         "WHERE Games.ID={$this->gid} LIMIT 1"
         );
      if ( @mysql_num_rows($result) != 1 )
         error('unknown_game', "SgfBuilder.load_game_info2({$this->gid})");
      $grow = mysql_fetch_array($result);

      $status = $grow['Status'];
      if ( !isRunningGame($status) && $status != GAME_STATUS_FINISHED ) // not for fair-komi
         error('invalid_game_status', "SgfBuilder.load_game_info.check.status({$this->gid},$status)");

      $this->game_row = $grow;
      $this->is_mpgame = ( $this->game_row['GameType'] != GAMETYPE_GO );
      if ( $this->is_mpgame )
         GamePlayer::load_users_for_mpgame( $this->gid, '', false, $this->mpg_users );

      $shape_snapshot = $this->game_row['ShapeSnapshot'];
      if ( $shape_snapshot )
      {
         $shape_id = $this->game_row['ShapeID'];
         $arr_shape = GameSnapshot::parse_check_extended_snapshot($shape_snapshot);
         if ( !is_array($arr_shape) )
            error('invalid_snapshot', "SgfBuilder.load_game_info.check.shape({$this->gid},$shape_id,$shape_snapshot)");

         $b_first = (bool)@$arr_shape['PlayColorB'];
         $this->shape_info = ShapeControl::build_snapshot_info(
            $this->game_row['ShapeID'], $this->game_row['Size'], $shape_snapshot, $b_first, /*incl-img*/false );
      }

      $tid = (int)$grow['tid'];
      if ( $tid > 0 )
         $this->tourney = TournamentCache::load_cache_tournament( "SgfBuilder.load_game_info({$this->gid})", $tid, /*chk*/false );

      return $this->game_row;
   }//load_game_info

   public function find_mpg_user( $handle )
   {
      $result = null;
      foreach ( $this->mpg_users as $key => $arr )
      {
         if ( $arr['Handle'] == $handle )
         {
            $result = $arr;
            break;
         }
      }
      $this->mpg_active_user = $result;
      return $result;
   }//find_mpg_user

   public function load_player_game_notes( $uid )
   {
      // load GamesNotes for player
      $this->player_notes = '';
      if ( $this->include_games_notes )
      {
         $gn_row = GameHelper::load_cache_game_notes( 'SgfBuilder.load_player_game_notes', $this->gid, $uid );
         if ( is_array($gn_row) )
            $this->player_notes = @$gn_row['Notes'];
      }
   }//load_player_game_notes

   public function load_trimmed_moves( $with_comments )
   {
      // load moves
      $arr_moves = Board::load_cache_game_moves( 'SgfBuilder.load_trimmed_moves', $this->gid, /*fetch*/true, /*store*/false );
      if ( !is_array($arr_moves) )
         $arr_moves = array();

      // load move-messages
      if ( $with_comments )
      {
         $arr_movemsg = Board::load_cache_game_move_message( 'SgfBuilder.load_trimmed_moves',
            $this->gid, /*move*/null, /*fetch*/true, /*store*/false );
         if ( !is_array($arr_movemsg) )
            $arr_movemsg = array();
      }
      else
         $arr_movemsg = array();

      $cnt_moves = count($arr_moves);
      $this->moves_iterator->setResultRows( $cnt_moves );

      // possibly skip some moves
      $this->sgf_trim_nr = $this->moves_iterator->getResultRows() - 1 ;
      if ( $this->game_row['Status'] == GAME_STATUS_FINISHED && isset($this->game_row['Score']) )
      {
         $score = $this->game_row['Score'];

         //skip the ending moves where PosX <= $sgf_trim_level
         //-1=POSX_PASS= skip ending pass, -2=POSX_SCORE= keep them ... -999= keep everything
         if ( abs($score) < SCORE_RESIGN ) // real-point-score
            $sgf_trim_level = POSX_SCORE; // keep PASSes for better SGF=DGS-move-numbering
         else if ( abs($score) == SCORE_TIME )
            $sgf_trim_level = POSX_RESIGN; // keep PASSes and SCORing
         else // resignation
            $sgf_trim_level = POSX_SCORE;

         while ( $this->sgf_trim_nr >= 0 )
         {
            $row = $arr_moves[$this->sgf_trim_nr];
            if ( $row['PosX'] > $sgf_trim_level && ($row['Stone'] == WHITE || $row['Stone'] == BLACK) )
               break;
            $this->sgf_trim_nr-- ;
         }
      }

      $this->moves_iterator->clearItems();
      foreach ( $arr_moves as $row )
      {
         $row['Text'] = @$arr_movemsg[$row['MoveNr']];
         $this->moves_iterator->addItem( null, $row );
      }
   }//load_trimmed_moves

   public function build_filename_sgf( $bulk_filename )
   {
      // <white_user>-<black_user>-<gid>-YYYYMMDD.sgf
      static $FMT_STD  = '$w-$b-$g-$d1';
      // DGS-<gid>_YYYY-MM-DD_<rated=R|F><size>(H<handi>)K<komi>(=<result>)_<white>-<black>.sgf
      static $FMT_BULK = 'DGS-$g_$d2_$R$S$H$K$r_$w-$b';

      if ( is_null($this->file_format) )
         $file_format = ( $bulk_filename ) ? $FMT_BULK : $FMT_STD;
      else
         $file_format = $this->file_format;

      $f_rated = ( $this->game_row['Rated'] == 'N' ) ? 'F' : 'R';
      $f_handi = ( $this->game_row['Handicap'] > 0 ) ? 'H' . $this->game_row['Handicap'] : '';
      $f_komi = 'K' . str_replace( '.', ',', $this->game_row['Komi'] );
      $f_result = '';
      if ( $this->game_row['Status'] == GAME_STATUS_FINISHED )
      {
         $f_result = '=' . ( $this->game_row['Score'] < 0 ? 'B' : 'W' );
         if ( abs($this->game_row['Score']) == SCORE_TIME )
            $f_result .= 'T';
         elseif ( abs($this->game_row['Score']) == SCORE_RESIGN )
            $f_result .= 'R';
         else
            $f_result .= str_replace( '.', ',', abs($this->game_row['Score']) );
      }

      // see <FILEFORMAT>-option in section "4.SGF" in 'specs/quick_suite.txt'
      $filename = str_replace(
         array( '$b', '$w', '$g', '$d1', '$d2', '$S', '$R', '$H', '$K', '$r', '$$' ),
         array(
            $this->game_row['Blackhandle'], // $b
            $this->game_row['Whitehandle'], // $w
            $this->gid, // $g
            date('Ymd', $this->game_row['timestamp']),   // $d1
            date('Y-m-d', $this->game_row['timestamp']), // $d2
            $this->game_row['Size'], // $S
            $f_rated,  // $R
            $f_handi,  // $H
            $f_komi,   // $K
            $f_result, // $r
            '$', // $$
         ),
         $file_format );

      return $filename;
   }//build_filename_sgf

   // owned_comments: BLACK|WHITE=viewed by B/W-player (or game-player for MP-game), DAME=viewed by other user
   public function build_sgf( $filename, $owned_comments )
   {
      $this->build_sgf_start( $filename );
      $this->build_sgf_shape_setup(); // handle shape-game
      $this->build_sgf_moves( $owned_comments ); // loop over Moves
      $this->build_sgf_result();
      $this->build_sgf_end( $owned_comments );
   }

   private function build_sgf_start( $filename )
   {
      extract($this->game_row);

      if ( $this->tourney )
      {
         $tourney_info = self::sgf_simpletext(
            sprintf('(%s %s) Tournament #%s: %s',
               Tournament::getScopeText($this->tourney->Scope), Tournament::getTypeText($this->tourney->Type),
               $this->tourney->ID, $this->tourney->Title ));
         $tourney_str = "\nEV[$tourney_info]"
            . sprintf("\nRO[Round %s of %s]", $this->tourney->CurrentRound, $this->tourney->Rounds );
         $tourney_info .= sprintf('; with %s participants', $this->tourney->RegisteredTP );
      }
      else
         $tourney_str = $tourney_info = '';

      $this->echo_sgf(
         "(\n;FF[{$this->sgf_version}]GM[1]"
         . ( $this->charset ? "CA[{$this->charset}]" : '' )
         . "\nAP[DGS:".DGS_VERSION."]"
         //. "\nCP[".FRIENDLY_LONG_NAME.", ".HOSTBASE."licence.php]" // copyright on games
         . "\nPC[".FRIENDLY_LONG_NAME.": ".HOSTBASE."]"
         . "\nDT[" . date( 'Y-m-d', $startstamp ) . ',' . date( 'Y-m-d', $timestamp ) . "]"
         . "\nGN[" . self::sgf_simpletext($filename) . "]"
         . "\nSO[".HOSTBASE."game.php?gid={$this->gid}]"
         . $tourney_str
         . "\nPB[" . self::sgf_simpletext($this->buildPlayerName(BLACK, false)) . "]"
         . "\nPW[" . self::sgf_simpletext($this->buildPlayerName(WHITE, false)) . "]"
         );

      // ratings
      $this->echo_sgf(
         "\nBR[" . self::sgf_echo_rating($Blackrating) . ']' .
         "\nWR[" . self::sgf_echo_rating($Whiterating) . ']'
         );
      if ( $this->is_mpgame )
      {
         $teams = array(); // group-color => [ user, ... ]
         $mpg_general_comment = "\n\nGame Players (Order. Color: Name (Handle), Current Rating):";
         $last_order = 0;
         foreach ( $this->mpg_users as $key => $arr )
         {
            $gr_col = $arr['GroupColor'];
            $gr_order = $arr['GroupOrder'];
            $teams[$gr_col][] = $arr['Handle'];

            if ( $gr_order <= $last_order )
               $mpg_general_comment .= "\n";
            $last_order = $gr_order;
            $mpg_general_comment .=
               sprintf( "\n   %d. %s: %s (%s), %s - ELO %d", $gr_order, $gr_col, $arr['Name'], $arr['Handle'],
                        self::sgf_echo_rating($arr['Rating2'],true), $arr['Rating2'] );
         }
         if ( $GameType == GAMETYPE_ZEN_GO )
            $this->echo_sgf("\nWT[" . self::sgf_simpletext(implode(' ', $teams[GPCOL_BW])) . ']');
         else //TEAM_GO
         {
            $this->echo_sgf("\nBT[" . self::sgf_simpletext(implode(' ', $teams[GPCOL_B])) . ']');
            $this->echo_sgf("\nWT[" . self::sgf_simpletext(implode(' ', $teams[GPCOL_W])) . ']');
         }
         $game_players = $GamePlayers;
      }
      else
         $game_players = '1:1';

      // move-id
      $this->echo_sgf("\nXM[$Moves]");

      // general comment: game-id, rated-game, start/end-ratings
      $w_rating_start = ( is_valid_rating($White_Start_Rating) ) ? self::sgf_echo_rating($White_Start_Rating,true) : '';
      $general_comment = "Game ID: {$this->gid}"
         . "\nGame Type: $GameType ($game_players)"
         . ( $tourney_info ? "\n$tourney_info" : '' )
         . "\nRated: ". ( $Rated=='N' ? 'N' : 'Y' )
         . ( ($X_GameFlags & GAMEFLAGS_ADMIN_RESULT) ? "\nNote: game-result set by admin" : '' )
         . "\n"
         . ( is_valid_rating($White_Start_Rating)
               ? sprintf( "\nWhite Start Rating: %s - ELO %d",
                            self::sgf_echo_rating($White_Start_Rating,true), $White_Start_Rating )
               : "\nWhite Start Rating: ?" )
         . ( is_valid_rating($Black_Start_Rating)
               ? sprintf( "\nBlack Start Rating: %s - ELO %d",
                            self::sgf_echo_rating($Black_Start_Rating,true), $Black_Start_Rating )
               : "\nBlack Start Rating: ?" );
      if ( $Status == GAME_STATUS_FINISHED && isset($Score) )
      {
         $general_comment .=
            ( is_valid_rating($White_End_Rating)
               ? sprintf( "\nWhite End Rating: %s - ELO %d",
                            self::sgf_echo_rating($White_End_Rating,true), $White_End_Rating )
               : "\nWhite End Rating: ?" )
            . ( is_valid_rating($Black_End_Rating)
               ? sprintf( "\nBlack End Rating: %s - ELO %d",
                            self::sgf_echo_rating($Black_End_Rating,true), $Black_End_Rating )
               : "\nBlack End Rating: ?" );
      }
      if ( $this->shape_info )
         $general_comment .= "\n\n" . $this->shape_info;
      if ( $this->is_mpgame )
         $general_comment .= $mpg_general_comment;
      $this->echo_sgf( "\nGC[$general_comment]" );

      // NOTE: time-properties are noted in seconds, which on turn-based servers
      //       can get very big numbers, disturbing SGF-viewers.
      //       Therefore time-info not included at the moment.
      if ( $this->sgf_version >= 4 )
      {// overtime
         $timeprop = TimeFormat::echo_time_limit($Maintime, $Byotype, $Byotime, $Byoperiods, TIMEFMT_ENGL);
         $this->echo_sgf( "\nOT[" . self::sgf_simpletext($timeprop) . "]" );
      }

      $rules = self::sgf_get_ruleset($Ruleset); //Mandatory for Go (GM[1])
      if ( $rules )
         $this->echo_sgf( "\nRU[$rules]" );

      $this->echo_sgf(
         "\nSZ[$Size]" .
         "\nKM[$Komi]",
         /*lastprop*/'KM' );

      if ( $Handicap > 0 && $this->use_HA )
         $this->echo_sgf( "\nHA[$Handicap]", /*lastprop*/'HA' );

      if ( $Status == GAME_STATUS_FINISHED && isset($Score) )
      {
         $this->echo_sgf( "\nRE[" . self::sgf_simpletext( $Score==0 ? '0' : score2text($Score, false, true)) . "]",
            /*lastprop*/'RE' );
      }
   }//build_sgf_start

   // build shape-setup position for shape-game
   private function build_sgf_shape_setup()
   {
      $shape_id = (int)$this->game_row['ShapeID'];
      $shape_snapshot = $this->game_row['ShapeSnapshot'];
      if ( $shape_id <= 0 || !$shape_snapshot )
         return;

      $arr_xy = GameSnapshot::parse_stones_snapshot( $this->game_row['Size'], $shape_snapshot, 'AB', 'AW' );
      if ( count($arr_xy) )
      {
         foreach ( $arr_xy as $arr_setup )
         {
            list( $prop, $PosX, $PosY ) = $arr_setup;
            $sgf_coord = chr($PosX + ord('a')) . chr($PosY + ord('a'));
            $this->points[$sgf_coord] = $prop;
         }

         $this->sgf_echo_point( $this->points);
         $this->points = array();

         $comments = array();
         if ( $this->shape_info )
            $comments[] = sprintf( '%s: %s', T_('Shape-Game Setup#sgf'), $this->shape_info );
         if ( $shape_id )
            $comments[] = HOSTBASE."view_shape.php?shape={$shape_id}";
         if ( count($comments) )
            $this->sgf_echo_comment( implode("\n", $comments) );

         $this->prop_type = 'force_node';
      }
   }//build_sgf_setup

   // see also load_from_db()-func in 'include/board.php'
   private function build_sgf_moves( $owned_comments )
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
      while ( list(, $arr_item) = $this->moves_iterator->getListIterator() )
      {
         $row = $arr_item[1];
         $Text = '';
         extract($row); // fields: MoveNr,Stone,PosX,PosY,Hours; Text
         $coord = chr($PosX + ord('a')) . chr($PosY + ord('a'));

         switch ( (int)$Stone )
         {
            case MARKED_BY_WHITE:
            case MARKED_BY_BLACK:
            { // toggle marks
               //record last skipped SCORE/SCORE2 marked points
               if ( $this->sgf_trim_nr < 0 )
                  @$this->array[$PosX][$PosY] ^= OFFSET_MARKED;

               if ( isset($this->points[$coord]) )
                  unset($this->points[$coord]);
               elseif ( $this->sgf_trim_nr < 0 )
               {
                  $this->points[$coord] = ( @$this->array[$PosX][$PosY] == MARKED_DAME )
                     ? $marked_dame_prop
                     : $this->dead_stone_prop;
               }
               else
               {
                  //if ( @$this->array[$PosX][$PosY] == NONE )
                  $this->points[$coord] = 'MA';
               }

               break;
            }

            case NONE:
            { //+prisoners
               $this->array[$PosX][$PosY] = NONE;
               break;
            }

            case WHITE:
            case BLACK:
            {
               if ( $PosX <= POSX_ADDTIME ) //configuration actions
               {
                  //TODO: include time-info, fields see const-def
                  break;
               }

               $this->array[$PosX][$PosY] = $Stone;

               //keep comments even if in ending pass, SCORE, SCORE2 or resign steps.
               if ( $Handicap == 0 || $MoveNr >= $Handicap )
               {
                  if ( $this->is_mpgame )
                  {
                     // get player of current move
                     list( $group_color, $group_order, $move_color ) =
                        MultiPlayerGame::calc_game_player_for_move( $GamePlayers, $MoveNr, $Handicap, -1 );
                     $mpg_user = GamePlayer::get_user_info( $this->mpg_users, $group_color, $group_order );
                     $player_txt = self::formatPlayerName($mpg_user);

                     if ( is_array($this->mpg_active_user) ) // is game-player of MP-game
                     {
                        $is_move_player = ( is_array($mpg_user) && $mpg_user['uid'] == $this->mpg_active_user['uid'] );
                        if ( $Status != GAME_STATUS_FINISHED && !$is_move_player )
                           $Text = remove_hidden_game_tags($Text);

                        if ( (string)$Text != '' )
                           $this->node_com .= "\n$player_txt: $Text";
                        else if ( $this->mpg_node_add_user )
                           $this->node_com .= "\n$player_txt";
                     }
                     else // observer
                     {
                        if ( $Status != GAME_STATUS_FINISHED )
                           $Text = remove_hidden_game_tags($Text);

                        $Text = game_tag_filter( $Text, /*incl-tags*/false );
                        if ( (string)$Text != '' )
                           $this->node_com .= "\n$player_txt: $Text";
                        else if ( $this->mpg_node_add_user )
                           $this->node_com .= "\n$player_txt";
                     }
                  }
                  else if ( $owned_comments == BLACK || $owned_comments == WHITE )
                  {
                     if ( $Status != GAME_STATUS_FINISHED && $owned_comments != $Stone )
                        $Text = remove_hidden_game_tags($Text);

                     if ( (string)$Text != '' )
                        $this->node_com .= "\n" . $this->buildPlayerName($Stone, false) . ': ' . $Text;
                  }
                  else //SGF query from an observer
                  {
                     if ( $Status != GAME_STATUS_FINISHED )
                        $Text = remove_hidden_game_tags($Text);

                     $Text = game_tag_filter( $Text, /*incl-tags*/false );
                     if ( (string)$Text != '' )
                        $this->node_com .= "\n" . $this->buildPlayerName($Stone, false) . ': ' . $Text;
                  }
                  $Text = '';
               }//move-msg

               if ( $MoveNr <= $Handicap && $this->use_AB_for_handicap )
               {// handicap
                  $this->points[$coord] = 'AB'; //setup property
                  if ( $MoveNr < $Handicap)
                     break; //switch-break

                  $this->sgf_echo_point( $this->points);
                  $this->points = array();

                  $this->sgf_echo_comment( $this->node_com );
                  $this->node_com = '';
               }
               elseif ( $this->sgf_trim_nr >= 0 )
               {// move
                  $color = ( $Stone == WHITE ) ? 'W' : 'B';
                  if ( $this->next_color != $color )
                  {
                     $this->sgf_echo_prop('PL'); //setup property
                     $this->echo_sgf( "[$color]" );
                  }
                  $this->next_color = ( $Stone == WHITE ) ? 'B' : 'W';

                  $this->echo_sgf( "\n;" ); //Node start
                  $this->prop_type = '';

                  // sync move-number
                  if ( $MoveNr > $Handicap && $PosX >= POSX_PASS )
                  {
                     $movenum++;
                     if ( $MoveNr != $movenum + $movesync )
                     {
                        //useful when "non AB handicap" or "resume after SCORE"
                        $this->sgf_echo_prop('MN'); //move property
                        $this->echo_sgf( "[$movenum]" );
                        $movesync = $MoveNr - $movenum;
                     }
                  }

                  if ( $PosX < POSX_PASS )
                  { //score steps, others filtered by sgf_trim_level
                     $this->sgf_echo_prop($color);
                     $this->echo_sgf( "[]" ); // add 3rd pass
                     $this->next_color = self::switch_move_color( $color );

                     if ( $this->sgf_score_highlight & 1 )
                        $this->echo_sgf( "N[$color SCORE]", /*lastprop*/'N' );

                     if ( $this->sgf_score_highlight & 2 )
                        $this->node_com .= "\n$color SCORE";

                     $this->sgf_echo_point( $this->points );
                  }
                  else
                  { //pass, normal move or non AB handicap

                     if ( $PosX == POSX_PASS )
                     {
                        $this->sgf_echo_prop($color); //move property
                        $this->echo_sgf( "[]" ); //do not use [tt]

                        if ( $this->sgf_pass_highlight & 1 )
                           $this->echo_sgf( "N[$color PASS]", /*lastprop*/'N' );

                        if ( $this->sgf_pass_highlight & 2 )
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

   private function build_sgf_result()
   {
      if ( $this->game_row['Status'] != GAME_STATUS_FINISHED )
         return;

      $this->echo_sgf( "\n;" ); // Node start
      $this->sgf_echo_prop($this->next_color);
      $this->echo_sgf( "[]" ); // add 3rd pass

      if ( $this->include_node_name )
         $this->echo_sgf( "N[RESULT]", /*lastprop*/'N' ); //Node start
      $this->prop_type = '';

      // highlighting result in last comments:
      if ( !isset($this->game_row['Score']) )
         return;

      $score = $this->game_row['Score'];
      $this->node_com .= "\n";

      if ( abs($score) < SCORE_RESIGN ) // scor-able
      {
         $game_score = new GameScore( $this->game_row['Ruleset'], $this->game_row['Handicap'], $this->game_row['Komi'] );
         $game_score->set_prisoners_all( $this->game_row['Black_Prisoners'], $this->game_row['White_Prisoners'] );

         $board = new Board( $this->gid, $this->game_row['Size'], $this->game_row['Moves'] );
         $board->array = $this->array;
         list( $arr_territory, $arr_prisoners ) = $board->fill_game_score( $game_score, /*with-coords*/true );

         //Last dead stones mark
         if ( $this->dead_stone_prop )
            $this->sgf_echo_point( $arr_prisoners, $this->dead_stone_prop );

         //$points from last skipped SCORE/SCORE2 marked points
         $this->sgf_echo_point( array_merge( $this->points, $arr_territory ) );

         $game_score->calculate_score( null, 'sgf' );
         $scoring_info = $game_score->get_scoring_info();
         foreach ( array_reverse($scoring_info['sgf_texts']) as $key => $info )
            $this->node_com .= "\n$key: $info";
      }

      $this->node_com .= "\nResult: " . score2text($score, false, true);
      if ( $this->game_row['X_GameFlags'] & GAMEFLAGS_ADMIN_RESULT )
         $this->node_com .= " (set by admin)";
   }//build_sgf_result

   private function build_sgf_end( $owned_comments )
   {
      $notes = ( $owned_comments == BLACK || $owned_comments == WHITE ) ? trim($this->player_notes) : '';
      if ( (string)$notes != '' )
      {
         $player_txt = $this->buildPlayerName( $owned_comments, $this->is_mpgame );
         $this->node_com .= "\n\nNotes - $player_txt:\n" . $notes ;
      }

      $this->sgf_echo_comment( $this->node_com );
      $this->node_com = '';

      $this->echo_sgf( "\n)\n" );
   }//build_sgf_end

   private function buildPlayerName( $stone=DAME, $is_mpgame )
   {
      if ( $is_mpgame )
      {
         return (is_array($this->mpg_active_user))
            ? sprintf( '%s (%s)', $this->mpg_active_user['Name'], $this->mpg_active_user['Handle'] )
            : '[?]';
      }
      else
      {
         return ( $stone == WHITE )
            ? sprintf( '%s (%s)', $this->game_row['Whitename'], $this->game_row['Whitehandle'] )
            : sprintf( '%s (%s)', $this->game_row['Blackname'], $this->game_row['Blackhandle'] );
      }
   }//buildPlayerName



   // ------------ static functions ----------------------------

   private static function sgf_simpletext( $str )
   {
      return
         str_replace( "]", "\\]",
            str_replace( "\\", "\\\\",
               preg_replace( "/[\\x1-\\x20]+/", ' ', reverse_htmlentities( $str ) ) ));
   }

   private static function switch_move_color( $color )
   {
      return ($color == 'B') ? 'W' : 'B';
   }

   private static function sgf_echo_rating( $rating, $show_percent=false )
   {
      $rating_str = echo_rating( $rating, $show_percent, /*uid*/0, /*engl*/true, /*short*/1 );
      if ( (string)$rating_str == '' )
         return '?';
      $rating_str = str_ireplace( 'dan#short', 'd', $rating_str );
      $rating_str = str_ireplace( 'kyu#short', 'k', $rating_str );
      return reverse_htmlentities($rating_str);
   }//sgf_echo_rating

   /*!
    * \brief Returns ruleset-content for RU[]-tag.
    * \note available
    *  "AGA" (rules of the American Go Association)
    *  "GOE" (the Ing rules of Goe)
    *  "Japanese" (the Nihon-Kiin rule set)
    *  "NZ" (New Zealand rules)
   */
   private static function sgf_get_ruleset( $ruleset=null )
   {
      static $arr = array(
         RULESET_JAPANESE => 'Japanese',
         RULESET_CHINESE  => 'Chinese',
      );
      return ( !is_null($ruleset) && isset($arr[$ruleset]) ) ? $arr[$ruleset] : 'Japanese';
   }//sgf_get_ruleset

   private static function formatPlayerName( $arr )
   {
      return (is_array($arr))
         ? sprintf( '%s (%s), %s', $arr['Name'], $arr['Handle'], self::sgf_echo_rating($arr['Rating2']) )
         : '';
   }

} // end of 'SgfBuilder'

?>
