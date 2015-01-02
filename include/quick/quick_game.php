<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/quick/quick_handler.php';
require_once 'include/std_functions.php';
require_once 'include/coords.php';
require_once 'include/board.php';
require_once 'include/move.php';
require_once 'include/time_functions.php';
require_once 'include/rating.php';
require_once 'include/classlib_user.php';
require_once 'include/game_functions.php';
require_once 'include/game_actions.php';

 /*!
  * \file quick_game.php
  *
  * \brief QuickHandler for game-object.
  * \see specs/quick_suite.txt (3a)
  */

// see specs/quick_suite.txt (3a)
// gid=<GAME_ID>&move_id=<MOVE_ID>&move=<MOVES>&msg=<MESSAGE>
define('GAMEOPT_GID',     'gid');
define('GAMEOPT_MOVEID',  'move_id');
define('GAMEOPT_MOVES',   'move');
define('GAMEOPT_MESSAGE', 'msg');
define('GAMEOPT_FORMAT',  'fmt');
define('GAMEOPT_TOGGLE',  'toggle');
define('GAMEOPT_AGREE',   'agree');
define('QGAME_OPTIONS', 'gid|move_id|move|msg|fmt|toggle|agree');

define('GAMEOPTVAL_MOVE_PASS', 'pass');
define('GAMEOPTVAL_TOGGLE_ALL',    'all');
define('GAMEOPTVAL_TOGGLE_UNIQUE', 'uniq');

define('GAMECMD_DELETE', 'delete');
define('GAMECMD_SET_HANDICAP', 'set_handicap');
define('GAMECMD_MOVE',   'move');
define('GAMECMD_RESIGN', 'resign');
define('GAMECMD_STATUS_SCORE', 'status_score');
define('GAMECMD_SCORE',  'score');
define('GAME_COMMANDS', 'delete|set_handicap|move|resign|status_score|score');


 /*!
  * \class QuickHandlerGame
  *
  * \brief Quick-handler class for handling game-object.
  */
class QuickHandlerGame extends QuickHandler
{
   private $gid = 0;
   private $move_id = 0;
   private $message = '';
   private $url_moves = ''; // if null, expect 'moves' and 'is_pass_move' correctly initialized
   private $toggle_mode = GAMEOPTVAL_TOGGLE_ALL; // all|uniq
   private $agree = 0; // 0|1

   private $moves = null; // coords-array [ (x,y), ... ], if null -> parse from url_moves
   private $is_pass_move = false;

   private $gah = null;
   private $game_row = null;
   private $TheBoard = null;
   private $to_move = null;
   private $action = null;


   // ---------- Interface ----------------------------------------

   public static function canHandle( $obj, $cmd ) // static
   {
      return ( $obj == QOBJ_GAME ) && QuickHandler::matchRegex(GAME_COMMANDS, $cmd);
   }

   public function parseURL()
   {
      parent::checkArgsUnknown(QGAME_OPTIONS);
      $this->gid = (int)get_request_arg(GAMEOPT_GID);
      $this->move_id = get_request_arg(GAMEOPT_MOVEID); // no cast, must be able to check for non-existence != 0
      $this->message = trim( get_request_arg(GAMEOPT_MESSAGE) );
      $this->url_moves = strtolower( trim( get_request_arg(GAMEOPT_MOVES) ) );
      $this->toggle_mode = get_request_arg(GAMEOPT_TOGGLE, GAMEOPTVAL_TOGGLE_ALL);
      $this->agree = get_request_arg(GAMEOPT_AGREE);
   }

   public function prepare()
   {
      $uid = (int) @$this->my_id;

      // see specs/quick_suite.txt (3a)
      $dbgmsg = "QuickHandlerGame.prepare($uid,{$this->gid})";
      $this->checkCommand( $dbgmsg, GAME_COMMANDS );
      $cmd = $this->quick_object->cmd;

      // check gid
      QuickHandler::checkArgMandatory( $dbgmsg, GAMEOPT_GID, $this->gid );
      if ( !is_numeric($this->gid) || $this->gid <= 0 )
         error('unknown_game', "QuickHandlerGame.prepare.check({$this->gid})");
      $gid = $this->gid;

      // prepare command: del, resign; set_handi, move, score

      $this->gah = new GameActionHelper( $this->my_id, $gid, /*quick*/true );
      $this->game_row = $this->gah->load_game( $dbgmsg );
      extract($this->game_row);

      // check move(s) + context (move-id)
      if ( $cmd == GAMECMD_DELETE || $cmd == GAMECMD_SET_HANDICAP || $cmd == GAMECMD_MOVE || $cmd == GAMECMD_RESIGN || $cmd == GAMECMD_SCORE )
      {
         QuickHandler::checkArgMandatory( $dbgmsg, GAMEOPT_MOVEID, $this->move_id );
         if ( !is_numeric($this->move_id) )
            error('invalid_args', "QuickHandlerGame.prepare.check.move_id($gid,{$this->move_id})");
      }
      if ( $cmd == GAMECMD_SET_HANDICAP || $cmd == GAMECMD_MOVE )
         QuickHandler::checkArgMandatory( $dbgmsg, GAMEOPT_MOVES, $this->url_moves );
      if ( $cmd == GAMECMD_SET_HANDICAP || $cmd == GAMECMD_MOVE || $cmd == GAMECMD_STATUS_SCORE || $cmd == GAMECMD_SCORE )
         $this->prepareMoves();
      if ( $this->is_pass_move && $cmd != GAMECMD_MOVE )
         error('move_problem', "QuickHandlerGame.prepare.check.pass_move($gid,$cmd)");


      // affirm, that game is running
      if ( $Status == GAME_STATUS_INVITED || $Status == GAME_STATUS_SETUP )
         error('game_not_started', "$dbgmsg.check.status($Status)");
      elseif ( $Status == GAME_STATUS_FINISHED )
         error('game_finished', "$dbgmsg.check.status.finished");
      elseif ( !isRunningGame($Status) )
         error('invalid_game_status', "$dbgmsg.check.game_status($Status)");

      if ( $uid != $Black_ID && $uid != $White_ID )
         error('not_game_player', "dbgmsg.check.not_your_game($uid)");

      // allow delete|resign|score-status even if not-to-move
      if ( $uid != $ToMove_ID && !($cmd == GAMECMD_DELETE || $cmd == GAMECMD_RESIGN || $cmd == GAMECMD_STATUS_SCORE) )
         error('not_your_turn', "$dbgmsg.check.move_user($ToMove_ID,$uid)");
      if ( (int)$this->move_id != $Moves && $cmd != GAMECMD_STATUS_SCORE )
         error('already_played', "$dbgmsg.check.move_id.move_id({$this->move_id},$Moves)");


      // check for invalid-action

      $is_mpgame = ($GameType != GAMETYPE_GO);
      $this->action = self::convert_game_cmd_to_action( $cmd );
      if ( $cmd == GAMECMD_DELETE )
      {
         $too_few_moves = ( $Moves < DELETE_LIMIT + $Handicap );
         $may_del_game  = $too_few_moves && ( $tid == 0 ) && !$is_mpgame;
         if ( !$may_del_game )
            error('invalid_action', "$dbgmsg.check.status($cmd,$Handicap,$Moves,$tid)");
      }
      elseif ( $cmd == GAMECMD_SET_HANDICAP )
      {
         if ( $Status != GAME_STATUS_PLAY || $Handicap == 0 || $Moves > 0 || $ToMove_ID != $Black_ID )
            error('invalid_action', "$dbgmsg.check.status($cmd,$Status,$Handicap,$Moves)");
         if ( count($this->moves) != $Handicap )
            error('wrong_number_of_handicap_stone', "$dbgmsg.check.move_count($cmd,$Handicap,{$this->url_moves})");
      }
      elseif ( $cmd == GAMECMD_MOVE )
      {
         if ( $this->is_pass_move )
         {
            $this->action = GAMEACT_PASS;

            if ( $Handicap > 0 && $Moves < $Handicap )
               error('early_pass', "$dbgmsg.check.pass($Handicap,$Moves)");
            if ( $Status != GAME_STATUS_PLAY && $Status != GAME_STATUS_PASS )
               error('invalid_action', "$dbgmsg.check.pass.status($cmd,$Status)");
         }
         else
         {
            if ( $Moves < $Handicap )
               error('invalid_action', "$dbgmsg.check.miss_handicap($uid,$cmd,$Moves,$Handicap)");
            if ( count($this->moves) != 1 )
               error('invalid_args', "$dbgmsg.check.move_count($cmd)");
         }
      }
      elseif ( $cmd == GAMECMD_STATUS_SCORE || $cmd == GAMECMD_SCORE )
      {
         if ( $Status != GAME_STATUS_SCORE && $Status != GAME_STATUS_SCORE2 )
            error('invalid_action', "$dbgmsg.check.status($cmd,$Status)");
         if ( $this->toggle_mode != GAMEOPTVAL_TOGGLE_ALL && $this->toggle_mode != GAMEOPTVAL_TOGGLE_UNIQUE )
            error('invalid_args', "$dbgmsg.check.toggle($cmd,{$this->toggle_mode})");

         if ( $cmd == GAMECMD_SCORE )
         {
            if ( !preg_match("/^(|[01])$/", $this->agree) ) // empty|0|1 allowed; match on string (not ints for clear spec)
               error('invalid_args', "$dbgmsg.check.bad_agree({$this->agree})");
            $this->agree = (int)$this->agree;

            if ( !is_null($this->moves) && count($this->moves) > 0 && $this->agree )
               error('invalid_args', "$dbgmsg.check.agreed_but_moves({$this->url_moves})");
         }
      }

      $this->gah->set_game_action( $this->action );
      $this->gah->init_globals( $dbgmsg );
      $this->gah->load_game_conditional_moves( $dbgmsg );
      $this->TheBoard = $this->gah->load_game_board( $dbgmsg ); // load board with moves
   }//prepare

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   public function process()
   {
      $cmd = $this->quick_object->cmd;
      if ( $cmd == GAMECMD_STATUS_SCORE )
         $this->process_cmd_status_score();
      else
         $this->process_cmd_play();
   }

   private function process_cmd_play()
   {
      global $player_row, $NOW, $ActivityMax, $ActivityForMove;
      $cmd = $this->quick_object->cmd;
      $gid = $this->gid;
      extract($this->game_row);

      $dbgmsg = 'QuickHandlerGame.process';


      // ***** HOT_SECTION *****
      // >>> See also: confirm.php, quick_play.php, include/quick/quick_game.php, clock_tick.php (for timeout)
      $this->gah->init_query( $dbgmsg );
      $this->gah->set_game_move_message( $this->message );
      $this->gah->increase_moves();

      switch ( (string)$this->action )
      {
         case GAMEACT_DELETE:
            $this->gah->set_game_finished( true );
            break;

         case GAMEACT_SET_HANDICAP:
            // NOTE: moves = list of coordinates of the handicap-stone placement
            $this->gah->prepare_game_action_set_handicap( $dbgmsg, /*orig-stonestr*/null, $this->moves );
            break;

         case GAMEACT_DO_MOVE:
            // NOTE: moves = single move to submit (non "pass"-move)
            $this->gah->prepare_game_action_do_move( $dbgmsg, /*(x,y)*/$this->moves[0] );
            break;

         case GAMEACT_PASS:
            // NOTE: moves = "pass" for passing move
            $this->gah->prepare_game_action_pass( $dbgmsg );
            break;

         case GAMEACT_RESIGN:
            $this->gah->prepare_game_action_resign( $dbgmsg );
            break;

         case GAMEACT_SCORE:
            // NOTE: moves = coords to toggle for disagreement, toggle = toggle-mode, agree = agreement to finish game
            $toggle_uniq = ( $this->toggle_mode == GAMEOPTVAL_TOGGLE_UNIQUE );
            $arr_coords = $this->build_arr_coords($Size);
            $this->gah->prepare_game_action_score( $dbgmsg, /*stonestr*/'', $toggle_uniq, $this->agree, $arr_coords );
            break;

         default:
            error('invalid_action', "QuickHandlerGame.process.noaction($gid,{$this->action},$Status)");
            break;
      }//switch $this->action

      $this->gah->prepare_game_action_generic();
      $this->gah->process_game_action( $dbgmsg );
   }//process_cmd_play

   private function process_cmd_status_score()
   {
      // NOTE: moves = coords to toggle, toggle = toggle-mode, fmt = coordinate-format for output
      $gid = $this->gid;
      extract($this->game_row);

      $arr_coords = $this->build_arr_coords($Size);
      $gchkscore = new GameCheckScore( $this->TheBoard, /*stonestr*/'', $Handicap, $Komi, $Black_Prisoners, $White_Prisoners );
      if ( $this->toggle_mode == GAMEOPTVAL_TOGGLE_UNIQUE )
         $gchkscore->set_toggle_unique();
      $game_score = $gchkscore->check_remove( $Ruleset, $arr_coords, /*board-status*/true );
      $score = $game_score->calculate_score();

      $this->addResultKey( 'ruleset', strtoupper($Ruleset) );
      $this->addResultKey( 'score',
         score2text( $score, $this->game_row['Flags'], /*verbose*/false, /*engl*/false, /*quick*/true) );

      // board-status
      $fmt = get_request_arg(GAMEOPT_FORMAT, 'sgf');
      $bs = $game_score->get_board_status();
      $this->addResultKey( 'dame', self::convert_coords( $bs->get_coords(DAME), $fmt, $Size ) );
      $this->addResultKey( 'neutral', self::convert_coords( $bs->get_coords(MARKED_DAME), $fmt, $Size ) );
      $this->addResultKey( 'white_stones', self::convert_coords( $bs->get_coords(WHITE), $fmt, $Size ) );
      $this->addResultKey( 'black_stones', self::convert_coords( $bs->get_coords(BLACK), $fmt, $Size ) );
      $this->addResultKey( 'white_dead', self::convert_coords( $bs->get_coords(WHITE_DEAD), $fmt, $Size ) );
      $this->addResultKey( 'black_dead', self::convert_coords( $bs->get_coords(BLACK_DEAD), $fmt, $Size ) );
      $this->addResultKey( 'white_territory', self::convert_coords( $bs->get_coords(WHITE_TERRITORY), $fmt, $Size ) );
      $this->addResultKey( 'black_territory', self::convert_coords( $bs->get_coords(BLACK_TERRITORY), $fmt, $Size ) );
   }//process_cmd_status_score

   /*! \brief Builds coordinates-array for scoring. */
   private function build_arr_coords( $size )
   {
      $arr_coords = array();
      foreach ( $this->moves as $arr_xy )
      {
         list($x,$y) = $arr_xy;
         $arr_coords[] = number2sgf_coords($x, $y, $size);
      }
      return $arr_coords;
   }//build_arr_coords

   /*! \brief Checks syntax and splits moves into array this->moves removing double coords, or detect pass-move. */
   private function prepareMoves()
   {
      if ( is_null($this->url_moves) )
         return;

      $this->moves = array();
      if ( empty($this->url_moves) )
         return;

      if ( (string)$this->url_moves == GAMEOPTVAL_MOVE_PASS )
      {
         $this->is_pass_move = true;
         return;
      }

      $size = $this->game_row['Size'];
      $is_label_format = preg_match("/\\d/", $this->url_moves); // label | sgf format

      $arr_double = array(); // check for doublettes
      $arr_coords = self::parse_coords( $this->url_moves );
      foreach ( $arr_coords as $coord )
      {
         $xy_arr = ($is_label_format) ? board2number_coords($coord, $size) : sgf2number_coords($coord, $size);
         if ( is_null($xy_arr[0]) || is_null($xy_arr[1]) )
            error('invalid_coord', "QuickHandlerGame.prepareMoves({$this->gid},$size,[$coord]})");

         if ( !isset($arr_double[$coord]) ) // remove double coords
            $this->moves[] = $xy_arr;
         $arr_double[$coord] = 1;
      }
   }//prepareMoves


   // ---------- static funcs ----------------------

   /*! \brief Converts game-command to action needed by GameActionHelper. */
   private static function convert_game_cmd_to_action( $cmd )
   {
      static $ARR_GAME_ACTIONS = array(
            GAMECMD_DELETE  => GAMEACT_DELETE,
            GAMECMD_MOVE    => GAMEACT_DO_MOVE,
            //GAMEACT_PASS    => GAMEACT_PASS,
            GAMECMD_RESIGN  => GAMEACT_RESIGN,
            GAMECMD_SCORE   => GAMEACT_SCORE,
            GAMECMD_SET_HANDICAP => GAMEACT_SET_HANDICAP,
            //GAMECMD_STATUS_SCORE => GAMECMD_STATUS_SCORE,
         );

      $game_action = ( isset($ARR_GAME_ACTIONS[$cmd]) ) ? $ARR_GAME_ACTIONS[$cmd] : $cmd;
      return $game_action;
   }//convert_game_cmd_to_action

   /*! \brief Converts string of SGF-coords (without comma) into other coordinate-format. */
   private static function convert_coords( $coord_str, $fmt, $size )
   {
      if ( $fmt == 'sgf' )
         return $coord_str;
      elseif ( $fmt == 'board' )
      {
         $out = array();
         $coord_len = strlen($coord_str);
         for ( $i=0; $i < $coord_len; $i+=2 )
            $out[] = sgf2board_coords( substr($coord_str, $i, 2 ), $size );
         return implode(',', $out);
      }
      else // fmt= separator
      {
         $out = array();
         $coord_len = strlen($coord_str);
         for ( $i=0; $i < $coord_len; $i+=2 )
            $out[] = substr($coord_str, $i, 2);
         return implode($fmt, $out);
      }
   }//convert_coords

   /*! \brief Returns array of coordinate-strings (supported formats: sgf, sgf-comma, board). */
   private static function parse_coords( $move_str )
   {
      if ( strpos($move_str, ',') !== false || preg_match("/\\d/", $move_str) )
         return explode(',', $move_str);
      else
      {
         $out = array();
         $move_len = strlen($move_str);
         for ( $i=0; $i < $move_len; $i += 2 )
            $out[] = substr($move_str, $i, 2);
         return $out;
      }
   }//parse_coords

} // end of 'QuickHandlerGame'

?>
