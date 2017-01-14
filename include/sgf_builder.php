<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once 'include/game_comments.php';
require_once 'include/board.php';
require_once 'include/db/move_sequence.php';
require_once 'include/sgf_parser.php';
require_once 'include/conditional_moves.php';
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
  * \brief Convenience Class to build SGF (including conditional-moves).
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

   private $include_cond_moves = -1; // -1 = default-behaviour, see (4.SGF) in 'specs/quick_suite.xt'
   private $player_uid = 0; // (optionally) logged-in user
   private $cm_game_tree = null; // SgfGameTree to collect conditional-moves variation from game-moves
   private $cm_last_node = null; // last SgfNode to collect conditional-moves
   private $cond_moves_mseq = null; // arr( MoveSequence->parsed_node, ... ) with conditional-moves to merge

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

   /*!
    * \brief Sets mode for loading conditional-moves.
    * \param $cm_opt '' (=use default), 0-3 = restricting conditional-moves, see specs (4.SGF) in 'specs/quick_suite.txt'
    */
   public function set_include_conditional_moves( $cm_opt )
   {
      if ( (string)$cm_opt != '' )
      {
         if ( $cm_opt >= 0 && $cm_opt <= 3 )
            $this->include_cond_moves = (int)$cm_opt;
         else
            error('invalid_args', "SgfBuilder.set_include_conditional_moves.check.bad_value($cm_opt)");
      }
   }

   /*! \brief Sets logged-in player. */
   public function set_player_uid( $uid )
   {
      $this->player_uid = max( 0, (int)$uid );
   }

   // need this->load_game_info() first
   private function is_game_player()
   {
      return ( $this->player_uid == $this->game_row['Black_ID'] || $this->player_uid == $this->game_row['White_ID'] );
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

   // outputs SGF-text (only if not collecting cond-moves)
   private function echo_sgf( $sgf_text, $last_prop=0 )
   {
      if ( $last_prop )
         $this->last_prop = $last_prop;

      if ( !$this->cm_game_tree )
      {
         if ( $this->use_buffer )
            $this->SGF .= $sgf_text;
         else
            echo $sgf_text;
      }
   }//echo_sgf

   /*!
    * \brief Outputs SGF-property with value(s) as text or else collect SgfNode-entries for merging conditional-moves
    *       if $this->cm_game_tree is non-null.
    * \param $value scalar-value or array of values
    * \param $move_nr -1 = don't set move-number in SgfNode for collecting moves for cond-moves-merge; else >0
    *
    * \note SgfNodes collected if $this->cm_game_tree is (non-null) SgfGameTree-object:
    *       sets SgfNode->move_nr, sgf_move=Bxx (xx='' for PASS, else SGF-coord)
    */
   private function sgf_echo_prop( $prop, $value, $move_nr=0 )
   {
      $start_node = false;
      //if ( stristr('-B-W-MN-BL-WL-KO-BM-DO-IT-OB-OW-TE-', $prop.'-') )
      if ( stristr('-B-W-MN-', "-$prop-") )
      {
         if ( $this->prop_type == 'setup' || $this->prop_type == 'force_node' )
            $start_node = true;
         $this->prop_type = 'move';
      }
      elseif ( stristr('-AB-AE-AW-PL-', "-$prop-") )
      {
         if ( $this->prop_type == 'move' || $this->prop_type == 'force_node' )
            $start_node = true;
         $this->prop_type = 'setup';
      }
      $this->last_prop = $prop;

      if ( $this->cm_game_tree ) // collect nodes for merging of cond-moves into SgfGameTree
      {
         if ( $start_node )
         {
            $this->cm_last_node = self::new_sgf_node();
            $this->cm_game_tree->nodes[] = $this->cm_last_node;
         }
         $this->cm_last_node->props[$prop][] = $value; // single or list of values
         if ( $prop == 'B' || $prop == 'W' )
            $this->cm_last_node->sgf_move = $prop . $value;
         if ( $move_nr > 0 && (int)@$this->cm_last_node->move_nr <= 0 )
            $this->cm_last_node->move_nr = $move_nr; // set dynamic attribute
      }
      else // output moves directly
      {
         if ( $start_node )
            $this->echo_sgf( "\n;" . $prop );
         else
            $this->echo_sgf( $prop );

         // output single value or list of values: prop[val]  or  prop[val1][val2]...
         if ( is_array($value) )
            $this->echo_sgf( '[' . implode('][', $value) . ']' );
         else
            $this->echo_sgf( "[$value]" );
      }
   }//sgf_echo_prop

   // outputs SGF-comment property 'C'
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

      $this->echo_sgf( "\n" );
      $this->sgf_echo_prop( 'C', self::sgf_simpletext($comment, /*repl-LF*/false) );
      return true;
   }//sgf_echo_comment

   /*!
    * \brief Outputs list of points.
    * \param $points arr( sgf-coord => prop, ... )
    * Possible properties are: (uppercase only)
    * - AB/AW/AE: add black stone/white stone/empty point (setup properties)
    * - MA/CR/TR/SQ: mark with a cross/circle/triangle/square (SQ is FF[4])
    * - TB/TW: mark territory black/white
    * \param $overwrite_prop false = no overwrite; else prop to use instead of element-values in $points
    */
   private function sgf_echo_points( $points, $move_nr=-1, $overwrite_prop=false )
   {
      if ( count($points) <= 0 )
         return false;

      asort($points);

      $values = array(); // prop => value-array
      foreach ( $points as $coord => $point_prop )
      {
         if ( $point_prop )
            $values[$point_prop][] = $coord;
      }

      foreach ( $values as $point_prop => $prop_values )
      {
         $this->echo_sgf( "\n" );
         $this->sgf_echo_prop( ($overwrite_prop ? $overwrite_prop : $point_prop), $prop_values, $move_nr );
      }

      return true;
   }//sgf_echo_points


   public function load_game_info()
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

   public function find_mpg_active_user( $handle )
   {
      $this->mpg_active_user = GamePlayer::find_mpg_user( $this->mpg_users, 0, $handle );
   }

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

   /*! \brief Load moves, including conditional-moves (if needed). */
   public function load_trimmed_moves( $with_comments )
   {
      // load moves
      $arr_moves = Board::load_cache_game_moves( 'SgfBuilder.load_trimmed_moves', $this->gid, /*fetch*/true, /*store*/false );
      if ( !is_array($arr_moves) )
         $arr_moves = array();

      $this->load_conditional_moves();

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
         $game_flags = (int)$this->game_row['Flags'];

         //skip the ending moves where PosX <= $sgf_trim_level
         //-1=POSX_PASS= skip ending pass, -2=POSX_SCORE= keep them ... -999= keep everything
         if ( abs($score) <= SCORE_MAX ) // real-point-score
            $sgf_trim_level = POSX_SCORE; // keep PASSes for better SGF=DGS-move-numbering
         else if ( abs($score) == SCORE_TIME || abs($score) == SCORE_FORFEIT || ($game_flags & GAMEFLAGS_NO_RESULT) )
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


   /*!
    * \brief Loads and checks if and what merges with conditional-moves are needed.
    * \note Modifies $this->include_cond_moves: 0 = no cond-moves to merge, >0 according value to specs
    * \note Fills $this->cond_moves_mseq with array of MoveSequence-objects with set attribute ->parsed_game_tree
    *       containing game-tree to merge with
    */
   private function load_conditional_moves()
   {
      $this->cond_moves_mseq = array();

      $status = $this->game_row['Status'];
      $is_game_finished = ( $status == GAME_STATUS_FINISHED );
      $is_player = $this->is_game_player();

      if ( !ALLOW_CONDITIONAL_MOVES
            || $this->game_row['GameType'] != GAMETYPE_GO || $status == GAME_STATUS_KOMI ) // not for MPG or FK-negotiation
         $this->include_cond_moves = 0;
      elseif ( $this->include_cond_moves < 0 || $this->include_cond_moves > 3 ) // determine default, limit
         $this->include_cond_moves = 0;
      elseif ( $this->include_cond_moves > 0 && !$is_game_finished && !$is_player )
         $this->include_cond_moves = 0;
      elseif ( $is_player && ( $this->include_cond_moves == 1 || $this->include_cond_moves == 2 ) )
         $this->include_cond_moves = 3;

      if ( $this->include_cond_moves == 0 ) // don't load cond-moves
         return;


      $iterator = new ListIterator( 'SgfBuilder.load_conditional_moves.MoveSequence' );
      $iterator = MoveSequence::load_move_sequences( $iterator, $this->gid ); // CMs of both players
      while ( list(,$arr_item) = $iterator->getListIterator() )
      {
         $move_seq = $arr_item[0];
         $is_private = ( $move_seq->Flags & MSEQ_FLAG_PRIVATE );
         $is_owner = ( $this->player_uid == $move_seq->uid ); // is "author" of this CM

         // only "owner" can see private CMs and CMs for running games; it's forbidden for observers and opponent
         if ( !$is_owner && ( $is_private || !$is_game_finished ) )
            continue;
         // hide CMs for players according to specified include-cond-moves option
         if ( $is_player && (
                  (  $is_owner && !($this->include_cond_moves & 2) )       // only show own CMs
               || ( !$is_owner && !($this->include_cond_moves & 1) ) ) )   // only show opponent CMs
            continue;

         $sgf_parser = new SgfParser( SGFP_OPT_SKIP_ROOT_NODE );
         if ( $sgf_parser->parse_sgf($move_seq->Sequence) )
         {
            // normalize: use SGF-coords for B/W-moves & use '' for PASS-move
            $move_seq->parsed_game_tree =
               ConditionalMoves::fill_conditional_moves_attributes( $sgf_parser->games[0], $move_seq->StartMoveNr );
            $this->cond_moves_mseq[] = $move_seq;
         }
      }

      if ( count($this->cond_moves_mseq) == 0 ) // nothing to merge
         $this->include_cond_moves = 0;
   }//load_conditional_moves


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

      if ( $this->game_row['GameType'] == GAMETYPE_TEAM_GO )
         $f_gametype = 'T' . $this->game_row['GamePlayers']; // T3:2
      elseif ( $this->game_row['GameType'] == GAMETYPE_ZEN_GO )
         $f_gametype = 'T' . (int)$this->game_row['GamePlayers']; // T5
      else // GAMETYPE_GO
         $f_gametype = 'T0';
      $f_moves = 'M' . (int)$this->game_row['Moves'];
      $f_rated = ( $this->game_row['Rated'] == 'N' ) ? 'F' : 'R';
      $f_handi0 = 'H' . (int)$this->game_row['Handicap'];
      $f_handi = ( $this->game_row['Handicap'] > 0 ) ? 'H' . $this->game_row['Handicap'] : '';
      $f_komi = 'K' . str_replace( '.', ',', $this->game_row['Komi'] );
      $f_result = '';
      if ( $this->game_row['Status'] == GAME_STATUS_FINISHED )
      {
         $f_result = '=' . ( $this->game_row['Score'] < 0 ? 'B' : 'W' );
         if ( abs($this->game_row['Score']) == SCORE_RESIGN )
            $f_result .= 'R';
         elseif ( abs($this->game_row['Score']) == SCORE_TIME )
            $f_result .= 'T';
         elseif ( abs($this->game_row['Score']) == SCORE_FORFEIT )
            $f_result .= 'F';
         else
         {
            if ( $this->game_row['Flags'] & GAMEFLAGS_NO_RESULT )
               $f_result = '=VOID';
            else
               $f_result .= str_replace( '.', ',', abs($this->game_row['Score']) );
         }
      }

      // see <FILEFORMAT>-option in section "4.SGF" in 'specs/quick_suite.txt'
      $filename = str_replace(
         array( '$b', '$w', '$g', '$d1', '$d2', '$S', '$M', '$R', '$H0', '$H', '$K', '$r', '$T', '$$' ),
         array(
            $this->game_row['Blackhandle'], // $b
            $this->game_row['Whitehandle'], // $w
            $this->gid, // $g
            date('Ymd', $this->game_row['timestamp']),   // $d1
            date('Y-m-d', $this->game_row['timestamp']), // $d2
            $this->game_row['Size'], // $S
            $f_moves,  // $M
            $f_rated,  // $R
            $f_handi0,  // $H0
            $f_handi,  // $H
            $f_komi,   // $K
            $f_result, // $r
            $f_gametype, // $T
            '$', // $$
         ),
         $file_format );

      return $filename;
   }//build_filename_sgf


   /*!
    * \brief Main-function to build full SGF.
    * \param $owned_comments BLACK|WHITE=viewed by B/W-player (or game-player for MP-game), DAME=viewed by other user
    */
   public function build_sgf( $filename, $owned_comments )
   {
      $this->build_sgf_start( $filename );
      $this->build_sgf_shape_setup(); // handle shape-game

      // loop over Moves
      if ( $this->include_cond_moves > 0 )
         $this->build_sgf_moves_with_conditional_moves( $owned_comments );
      else
         $this->build_sgf_moves( $owned_comments );

      $this->build_sgf_result();

      // add game-notes for finished-games after RESULT
      if ( $this->game_row['Status'] == GAME_STATUS_FINISHED )
         $this->add_game_notes( $owned_comments );

      $this->build_sgf_end();
   }//build_sgf

   /*! \brief Builds root-node for SGF. */
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
               sprintf( "\n   %d. %s: %s (%s), %s - Elo rating %d", $gr_order, $gr_col, $arr['Name'], $arr['Handle'],
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
      // NOTE: all text in comment need to be built with escaped SGF-text
      $w_rating_start = ( is_valid_rating($White_Start_Rating) ) ? self::sgf_echo_rating($White_Start_Rating,true) : '';
      $general_comment = "Game ID: {$this->gid}"
         . "\nGame Type: $GameType ($game_players)"
         . ( $tourney_info ? "\n$tourney_info" : '' )
         . "\nRated: ". ( $Rated=='N' ? 'N' : 'Y' )
         . ( ($Flags & GAMEFLAGS_ADMIN_RESULT) ? "\nNote: game-result set by admin" : '' )
         . "\n"
         . ( is_valid_rating($White_Start_Rating)
               ? sprintf( "\nWhite Start Rating: %s - Elo rating %d",
                            self::sgf_echo_rating($White_Start_Rating,true), $White_Start_Rating )
               : "\nWhite Start Rating: ?" )
         . ( is_valid_rating($Black_Start_Rating)
               ? sprintf( "\nBlack Start Rating: %s - Elo rating %d",
                            self::sgf_echo_rating($Black_Start_Rating,true), $Black_Start_Rating )
               : "\nBlack Start Rating: ?" );
      if ( $Status == GAME_STATUS_FINISHED && isset($Score) )
      {
         $general_comment .=
            ( is_valid_rating($White_End_Rating)
               ? sprintf( "\nWhite End Rating: %s - Elo rating %d",
                            self::sgf_echo_rating($White_End_Rating,true), $White_End_Rating )
               : "\nWhite End Rating: ?" )
            . ( is_valid_rating($Black_End_Rating)
               ? sprintf( "\nBlack End Rating: %s - Elo rating %d",
                            self::sgf_echo_rating($Black_End_Rating,true), $Black_End_Rating )
               : "\nBlack End Rating: ?" );
      }
      if ( $this->shape_info )
         $general_comment .= "\n\n" . self::sgf_simpletext($this->shape_info);
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
         $score_text = score2text( $Score, $Flags, /*verbose*/false, /*engl*/true, /*quick(!)*/0 );
         $this->echo_sgf( "\nRE[" . self::sgf_simpletext($score_text) . "]", /*lastprop*/'RE' );
      }
   }//build_sgf_start

   /*! \brief Builds shape-setup position for shape-game. */
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

         $this->sgf_echo_points( $this->points);
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

   /*!
    * \brief Builds moves (and add game-notes for unfinished games).
    * \param $owned_comments see build_sgf()-func
    * \note see also load_from_db()-func in 'include/board.php'
    */
   private function build_sgf_moves( $owned_comments )
   {
      extract($this->game_row);

      $this->array = array();
      $this->points = array();
      $this->next_color = 'B';

      $gc_helper = new GameCommentHelper( $this->gid, $Status, $GameType, $GamePlayers, $Handicap,
         $this->mpg_users, $this->mpg_active_user );

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
                  $Text = $gc_helper->filter_comment( $Text, $MoveNr, $Stone, $owned_comments, /*html*/false );
                  if ( $this->is_mpgame )
                  {
                     $player_txt = self::formatPlayerName( $gc_helper->get_mpg_user() );

                     if ( (string)$Text != '' )
                        $this->node_com .= "\n$player_txt: $Text";
                     else if ( $this->mpg_node_add_user )
                        $this->node_com .= "\n$player_txt";
                  }
                  else // std-game
                  {
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

                  $this->sgf_echo_points( $this->points );
                  $this->points = array();
               }
               elseif ( $this->sgf_trim_nr >= 0 )
               {// move
                  $color = ( $Stone == WHITE ) ? 'W' : 'B';
                  if ( $this->next_color != $color )
                     $this->sgf_echo_prop('PL', $color); //setup property
                  $this->next_color = ( $Stone == WHITE ) ? 'B' : 'W';

                  $this->prop_type = 'force_node';

                  // sync move-number
                  if ( $MoveNr > $Handicap && $PosX >= POSX_PASS )
                  {
                     $movenum++;
                     if ( $MoveNr != $movenum + $movesync )
                     {
                        //useful when "non AB handicap" or "resume after SCORE"
                        $this->sgf_echo_prop('MN', $movenum); //move property
                        $movesync = $MoveNr - $movenum;
                     }
                  }

                  if ( $PosX < POSX_PASS )
                  { //score steps, others filtered by sgf_trim_level
                     $this->sgf_echo_prop($color, '', $MoveNr); // add 3rd pass
                     $this->next_color = self::switch_move_color( $color );

                     if ( $this->sgf_score_highlight & 1 )
                        $this->sgf_echo_prop( 'N', "$color SCORE" );

                     if ( $this->sgf_score_highlight & 2 )
                        $this->node_com .= "\n$color SCORE";

                     $this->sgf_echo_points( $this->points );
                  }
                  else
                  { //pass, normal move or non AB handicap
                     if ( $PosX == POSX_PASS )
                     {
                        $this->sgf_echo_prop($color, '', $MoveNr); //move property, do not use [tt] for PASS

                        if ( $this->sgf_pass_highlight & 1 )
                           $this->sgf_echo_prop( 'N', "$color PASS" );

                        if ( $this->sgf_pass_highlight & 2 )
                           $this->node_com .= "\n$color PASS";
                     }
                     else //move or non AB handicap
                        $this->sgf_echo_prop($color, $coord, $MoveNr); //move property

                     $this->points = array();
                  }
               }

               // add game-notes for unfinished-games in last move
               if ( $MoveNr == $Moves && $Status != GAME_STATUS_FINISHED )
                  $this->add_game_notes( $owned_comments );

               //error_log("C@$MoveNr $PosX/$PosY [{$this->sgf_trim_nr}]: {$this->node_com}");
               $this->sgf_echo_comment( $this->node_com );
               $this->node_com = '';
               break;
            }//case WHITE/BLACK
         }//switch $Stone

         $this->sgf_trim_nr--;
      }//moves-loop
   }//build_sgf_moves


   /*! \brief Builds SGF moves-part if conditional-moves must be merged in. */
   private function build_sgf_moves_with_conditional_moves( $owned_comments )
   {
      // collect sgf-game-tree with sgf-nodes
      $this->cm_last_node = self::new_sgf_node();
      $this->cm_game_tree = new SgfGameTree();
      $this->cm_game_tree->nodes[] = $this->cm_last_node;
      $this->build_sgf_moves( $owned_comments );
      if ( count($this->cm_game_tree->nodes[0]->props) == 0 ) // strip away empty (very) first "catch" node
         array_shift( $this->cm_game_tree->nodes );

      // merge in conditional-moves into game-moves stored in this->cm_game_tree
      foreach ( $this->cond_moves_mseq as $move_seq )
         $this->merge_conditional_moves( $move_seq );

      // output merged variations
      $sgf = SgfParser::sgf_builder( array( $this->cm_game_tree ), "\n", "\n", 'C,AB,AW' );
      if ( $sgf[0] == '(' && $sgf[1] != '(' && substr($sgf, -1, 1) == ')' ) // strip surrounding superfluous braces
         $sgf = trim( substr( $sgf, 1, -1 ) );

      $this->cm_game_tree = null; // let echo_sgf() output built stuff (instead of collecting nodes)
      $this->echo_sgf( $sgf );
      //error_log("#SGF: $sgf");
   }//build_sgf_moves_with_conditional_moves


   /*!
    * \brief Merges in given MoveSequence into main game-moves this->cm_game_tree.
    * \note expecting attributes (move_nr, sgf_move) set in SgfNodes in MoveSequence->parsed_game_tree
    */
   private function merge_conditional_moves( $mseq )
   {
      // NOTES about some implementation-"traps":
      // - array-iterator is reset on array_splice(), therefore looping-forward is needed (find_target_start_node-func)
      // - prev() with iterator returning last item does NOT move iterator back, but stays at the end,
      //   therefore special handling for last-item required
      // - must keep main-game-line always in 1st var in sub-trees

      // traverse game-tree of cond-moves (=source) and merge into target-tree
      $vars_cm = array(); // stack for variations for traversal of game-tree with conditional-moves
      SgfParser::push_var_stack( $vars_cm, $mseq->parsed_game_tree, $this->cm_game_tree );

      while ( list($trg_tree, $src_tree) = array_pop($vars_cm) ) // process variations-stack of CM-var
      {
         if ( $this->find_target_start_node( $trg_tree, $src_tree ) )
            break; // stop on fatal-error (FIXME perhaps throw exception?)

         $process_src_vars = true;
         foreach ( $src_tree->nodes as $src_node_idx => $src_node ) // $src_node is a SgfNode
         {
            // get next target-node in main-nodes (for merging-in CM-node)
            list( $trg_node_idx, $trg_node, $trg_has_vars ) = $this->yield_next_target_node( $trg_tree, $src_node );

            if ( is_null($trg_node) ) // no next-node found in target-tree
            {
               $extra_node = ( prev($src_tree->nodes) !== false ) ? null : $src_node; // include previous CM-node

               if ( $trg_has_vars ) // there are trg-tree-vars but w/o a matching move, so add remaing src-tree as new var
               {
                  $remaining_src_tree = new SgfGameTree();
                  self::append_remaining_conditional_moves( $remaining_src_tree, $extra_node, $src_tree );
                  $trg_tree->vars[] = $remaining_src_tree;
               }
               else // no vars yet in target-tree, so append remaining src-tree in current target-var
               {
                  self::append_remaining_conditional_moves( $trg_tree, $extra_node, $src_tree );
               }
               $process_src_vars = false; // src-vars already processed here
               break; // continue merging next CM-var from stack
            }
            else // found next-node in target-tree
            {
               if ( $trg_node->move_nr == $src_node->move_nr && $trg_node->sgf_move == $src_node->sgf_move )
               {
                  // move is the same, so merge SGF-props by merging src-node with target-node from game-main-tree
                  self::merge_sgf_node_props( $trg_node, $src_node );
               }
               else // start new variation replacing current target-node
               {
                  // collect remaining nodes/vars of current target-game-tree (including current trg-node)
                  $remaining_trg_tree = new SgfGameTree();
                  $remaining_trg_tree->nodes = array_splice( $trg_tree->nodes, $trg_node_idx );
                  $remaining_trg_tree->vars = $trg_tree->vars;

                  // collect remaining nodes/vars of current cond-moves src-game-tree
                  $extra_node = ( prev($src_tree->nodes) !== false ) ? null : $src_node; // include previous CM-node
                  $remaining_src_tree = new SgfGameTree();
                  self::append_remaining_conditional_moves( $remaining_src_tree, $extra_node, $src_tree );

                  // replace original target-merge-node with the 2 collected variations
                  $trg_tree->vars = array( $remaining_trg_tree, $remaining_src_tree );
                  $process_src_vars = false;
                  break; // continue merging next src-game-tree from stack
               }
            }
         }//nodes-end

         if ( $process_src_vars )
         {
            foreach ( array_reverse($src_tree->vars) as $sub_tree ) // traverse vars of source-game-tree
               SgfParser::push_var_stack( $vars_cm, $sub_tree, $trg_tree );
         }
      }//game-tree end
   }//merge_conditional_moves

   /*!
    * \brief Forwards target-node-iterator to starting point (move-number) to merge in src-game-tree.
    * \return 0 = success; otherwise error-string with fatal-error.
    */
   private function find_target_start_node( &$trg_tree, $src_game_tree )
   {
      reset($trg_tree->nodes);
      $src_node = $src_game_tree->nodes[0];
      $src_move_nr = $src_node->move_nr;

      // find target-start-node of first src-cond-moves (can only be in target-main-var), then merge from there
      $result = 0;
      while ( true )
      {
         if ( ( list( $id, $trg_node ) = each($trg_tree->nodes) ) !== false )
         {
            if ( $trg_node->move_nr < $src_move_nr - 1 )
               continue;
            else
               break;
         }
         else if ( $trg_tree->has_vars() )
         {
            $trg_tree = $trg_tree->vars[0]; // continue search in first variation with main-path
            reset($trg_tree->nodes);
         }
         else // shouldn't happen (i.e. cond-move start_move_nr + 1 should be <= max(move-nr in target-tree) )
         {
            $result = 'assert_bad_start';
            break;
         }
      }//end-while

      return $result;
   }//find_target_start_node

   /*!
    * \brief Gets next target-node in main-nodes matching move in source-node (for merging-in CM-node).
    * \return arr( trg_node_idx, trg_node, trg_has_vars );
    *       trg_node_idx = trg_node == null -> use trg_has_vars = true|false; else ignore trg_has_vars
    */
   private function yield_next_target_node( &$trg_tree, $src_node )
   {
      $trg_node_idx = $trg_node = $trg_has_vars = null;
      while ( is_null($trg_has_vars) )
      {
         $arr_each = each($trg_tree->nodes);
         if ( $arr_each !== false ) // found next main-node (from game to merge)
         {
            list( $trg_node_idx, $trg_node ) = $arr_each;
            break;
         }//else: no further nodes, so now check optional sub-trees

         // find variation from main-path (target-game-tree) with matching 1st src-node-move
         if ( $trg_has_vars = $trg_tree->has_vars() ) // there are vars but w/o a matching move
         {
            foreach ( $trg_tree->vars as $sub_tree )
            {
               $sub_node = $sub_tree->get_first_node(); // shouldn't be null as empty var is forbidden
               if ( !is_null($sub_node) && $src_node->sgf_move == $sub_node->sgf_move )
               {
                  $trg_tree = $sub_tree; // found variation with matching move from src-game-tree
                  reset($trg_tree->nodes);
                  list( $trg_node_idx, $trg_node ) = each($trg_tree->nodes);
                  break 2;
               }
            }
         }
      }//end-while

      return array( $trg_node_idx, $trg_node, $trg_has_vars );
   }//yield_next_target_node


   /*! \brief Builds final result-node in SGF. */
   private function build_sgf_result()
   {
      if ( $this->game_row['Status'] != GAME_STATUS_FINISHED )
         return;

      $this->prop_type = 'force_node';
      $this->sgf_echo_prop($this->next_color, '');

      if ( $this->include_node_name )
         $this->sgf_echo_prop( 'N', 'RESULT' ); //Node start
      $this->prop_type = '';

      // highlighting result in last comments:
      if ( !isset($this->game_row['Score']) )
         return;

      $score = $this->game_row['Score'];
      $this->node_com .= "\n";

      if ( abs($score) <= SCORE_MAX ) // scor-able
      {
         $game_score = new GameScore( $this->game_row['Ruleset'], $this->game_row['Handicap'], $this->game_row['Komi'] );
         $game_score->set_prisoners_all( $this->game_row['Black_Prisoners'], $this->game_row['White_Prisoners'] );

         $board = new Board( $this->gid, $this->game_row['Size'], $this->game_row['Moves'] );
         $board->array = $this->array;
         list( $arr_territory, $arr_prisoners ) = $board->fill_game_score( $game_score, /*with-coords*/true );

         //Last dead stones mark
         if ( $this->dead_stone_prop )
            $this->sgf_echo_points( $arr_prisoners, -1, $this->dead_stone_prop );

         //$points from last skipped SCORE/SCORE2 marked points
         $this->sgf_echo_points( array_merge( $this->points, $arr_territory ) );

         $game_score->calculate_score( null, 'sgf' );
         $scoring_info = $game_score->get_scoring_info();
         foreach ( array_reverse($scoring_info['sgf_texts']) as $key => $info )
            $this->node_com .= "\n$key: $info";
      }

      $this->node_com .= "\nResult: " . score2text($score, $this->game_row['Flags'], /*verbose*/false, /*engl*/true);
      if ( $this->game_row['Flags'] & GAMEFLAGS_ADMIN_RESULT )
         $this->node_com .= " (set by admin)";
   }//build_sgf_result

   /*! \brief Add game-notes in $this->node_com. */
   private function add_game_notes( $owned_comments )
   {
      $notes = ( $owned_comments == BLACK || $owned_comments == WHITE ) ? trim($this->player_notes) : '';
      if ( (string)$notes != '' )
      {
         $player_txt = $this->buildPlayerName( $owned_comments, $this->is_mpgame );
         $this->node_com .= "\n\nNotes - $player_txt:\n" . $notes ;
      }
   }//add_game_notes

   /*! \brief Finishes SGF-tree. */
   private function build_sgf_end()
   {
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

   /*! \brief Escapes text for SGF-output. */
   private static function sgf_simpletext( $str, $repl_white_space=true )
   {
      $str = reverse_htmlentities($str);
      if ( $repl_white_space )
         $str = preg_replace( "/[\\x1-\\x20]+/", ' ', $str );
      return
         str_replace(
            array( "\\", "[", "]" ),
            array( "\\\\", "\\[", "\\]" ), $str );
   }

   /* \brief Creates SgfNode needed for merging of conditional-moves. */
   private static function new_sgf_node()
   {
      $sgf_node = new SgfNode(0);
      $sgf_node->move_nr = 0;
      return $sgf_node;
   }

   /*! \brief Merges certain SGF-properties from src-node into target-node, no overwriting of props. */
   private static function merge_sgf_node_props( $trg_node, $src_node )
   {
      foreach ( $src_node->props as $key => $values )
      {
         if ( isset($trg_node->props[$key]) ) // merge values for existing prop
         {
            if ( $key == 'C' ) // merge text into one value for specific props (uniqued over values)
               $trg_node->props[$key][0] = trim( implode("\n\n",
                  array_unique( array_merge( $values, $src_node->props[$key] ))));
            elseif ( preg_match("/^(AB|AE|AR|AW|CR|DD|LB|LN|MA|SL|SQ|TB|TR|TW|VW)$/", $key) ) // merge unique list values
               $trg_node->props[$key] = array_unique( array_merge( $values, $src_node->props[$key] ) );
            //else: no merge for other props, do not overwrite source-property-value(s)
         }
         else
            $trg_node->props[$key] = $values;
      }
   }//merge_sgf_node_props

   /*!
    * \brief Appends (all or remaining) nodes and variations from source-gametree into target-gametree.
    * \param $extra_node null = no extra-node; SgfNode = append extra-node before appending src-game-tree
    * \note need assertion, that $trg_game_tree has no variations!
    */
   private static function append_remaining_conditional_moves( $trg_game_tree, $extra_node, $src_game_tree )
   {
      if ( $trg_game_tree->has_vars() )
         return; //FIXME throw fatal error !?

      if ( is_null($extra_node) )
         $first_node = true;
      else
      {
         $trg_game_tree->nodes[] = self::mark_node_conditional_moves_start($extra_node);
         $first_node = false;
      }

      // append all nodes from cond-moves after last-move (current nodes-iterator position)
      while ( list( $id, $node ) = each($src_game_tree->nodes) ) // NOTE: 'foreach' resets iterator, so need 'while'
      {
         if ( $first_node )
         {
            self::mark_node_conditional_moves_start($node);
            $first_node = false;
         }
         $trg_game_tree->nodes[] = $node;
      }

      foreach ( $src_game_tree->vars as $sub_tree )
         $trg_game_tree->vars[] = $sub_tree;
   }//append_remaining_conditional_moves

   private static function mark_node_conditional_moves_start( $sgf_node )
   {
      $sgf_node->props['C'][0] = self::sgf_simpletext(ConditionalMoves::$TXT_CM_START)
         . ( isset($sgf_node->props['C']) ? "\n" . $sgf_node->props['C'][0] : '' );
      return $sgf_node;
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
