<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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

// cmd => action
define('GAMEACT_PASS', 'pass');


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
      $this->move_id = (int)get_request_arg(GAMEOPT_MOVEID);
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
      if( !is_numeric($this->gid) || $this->gid <= 0 )
         error('unknown_game', "QuickHandlerGame.check({$this->gid})");
      $gid = $this->gid;

      // prepare command: del, resign; set_handi, move, score

      $this->game_row = GameHelper::load_game_row( "$dbgmsg.find_game", $gid );
      extract($this->game_row);

      // check move(s) + context (move-id)
      if( $cmd == GAMECMD_DELETE || $cmd == GAMECMD_SET_HANDICAP || $cmd == GAMECMD_MOVE || $cmd == GAMECMD_RESIGN || $cmd == GAMECMD_SCORE )
         QuickHandler::checkArgMandatory( $dbgmsg, GAMEOPT_MOVEID, $this->move_id );
      if( $cmd == GAMECMD_SET_HANDICAP || $cmd == GAMECMD_MOVE )
         QuickHandler::checkArgMandatory( $dbgmsg, GAMEOPT_MOVES, $this->url_moves );
      if( $cmd == GAMECMD_SET_HANDICAP || $cmd == GAMECMD_MOVE || $cmd == GAMECMD_STATUS_SCORE || $cmd == GAMECMD_SCORE )
         $this->prepareMoves();
      if( $this->is_pass_move && $cmd != GAMECMD_MOVE )
         error('move_problem', "QuickHandlerGame.prepare.check.pass_move($gid,$cmd)");


      // affirm, that game is running
      if( $Status == GAME_STATUS_INVITED || $Status == GAME_STATUS_SETUP )
         error('game_not_started', "$dbgmsg.check.status($Status)");
      elseif( $Status == GAME_STATUS_FINISHED )
         error('game_finished', "$dbgmsg.check.status.finished");
      elseif( !isRunningGame($Status) )
         error('invalid_game_status', "$dbgmsg.check.game_status($Status)");

      if( $ToMove_ID == 0 )
         error('game_finished', "$dbgmsg.bad_ToMove_ID.gamend");
      if( $Black_ID == $ToMove_ID )
         $this->to_move = BLACK;
      elseif( $White_ID == $ToMove_ID )
         $this->to_move = WHITE;
      else
         error('database_corrupted', "$dbgmsg.check.to_move");

      if( $uid != $Black_ID && $uid != $White_ID )
         error('not_game_player', "dbgmsg.check.not_your_game($uid)");

      // allow delete|resign|score-status even if not-to-move
      if( $uid != $ToMove_ID && !($cmd == GAMECMD_DELETE || $cmd == GAMECMD_RESIGN || $cmd == GAMECMD_STATUS_SCORE) )
         error('not_your_turn', "$dbgmsg.check.move_user($ToMove_ID,$uid)");
      if( (int)$this->move_id != $Moves && $cmd != GAMECMD_STATUS_SCORE )
         error('already_played', "$dbgmsg.check.move_id.move_id({$this->move_id},$Moves)");


      // check for invalid-action

      $is_mpgame = ($GameType != GAMETYPE_GO);
      $this->action = $cmd;
      if( $cmd == GAMECMD_DELETE )
      {
         $too_few_moves = ( $Moves < DELETE_LIMIT + $Handicap );
         $may_del_game  = $too_few_moves && ( $tid == 0 ) && !$is_mpgame;
         if( !$may_del_game )
            error('invalid_action', "$dbgmsg.check.status($cmd,$Handicap,$Moves,$tid)");
      }
      elseif( $cmd == GAMECMD_SET_HANDICAP )
      {
         if( $Status != GAME_STATUS_PLAY || $Handicap == 0 || $Moves > 0 || $this->to_move != BLACK )
            error('invalid_action', "$dbgmsg.check.status($cmd,$Status,$Handicap,$Moves)");
         if( count($this->moves) != $Handicap )
            error('wrong_number_of_handicap_stone', "$dbgmsg.check.move_count($cmd,$Handicap,{$this->url_moves})");
      }
      elseif( $cmd == GAMECMD_MOVE )
      {
         if( $this->is_pass_move )
         {
            $this->action = GAMEACT_PASS;

            if( $Handicap > 0 && $Moves < $Handicap )
               error('early_pass', "$dbgmsg.check.pass($Handicap,$Moves)");
            if( $Status != GAME_STATUS_PLAY && $Status != GAME_STATUS_PASS )
               error('invalid_action', "$dbgmsg.check.pass.status($cmd,$Status)");
         }
         else
         {
            if( $Moves < $Handicap )
               error('invalid_action', "$dbgmsg.check.miss_handicap($uid,$cmd,$Moves,$Handicap)");
            if( count($this->moves) != 1 )
               error('invalid_args', "$dbgmsg.check.move_count($cmd)");
         }
      }
      elseif( $cmd == GAMECMD_STATUS_SCORE || $cmd == GAMECMD_SCORE )
      {
         if( $Status != GAME_STATUS_SCORE && $Status != GAME_STATUS_SCORE2 )
            error('invalid_action', "$dbgmsg.check.status($cmd,$Status)");
         if( $this->toggle_mode != GAMEOPTVAL_TOGGLE_ALL && $this->toggle_mode != GAMEOPTVAL_TOGGLE_UNIQUE )
            error('invalid_args', "$dbgmsg.check.toggle($cmd,{$this->toggle_mode})");

         if( $cmd == GAMECMD_SCORE )
         {
            if( !preg_match("/^(|[01])$/", $this->agree) ) // empty|0|1 allowed; match on string (not ints for clear spec)
               error('invalid_args', "$dbgmsg.check.bad_agree({$this->agree})");
            $this->agree = (int)$this->agree;

            if( !is_null($this->moves) && count($this->moves) > 0 && $this->agree )
               error('invalid_args', "$dbgmsg.check.agreed_but_moves({$this->url_moves})");
         }
      }

      // load board with moves
      $this->TheBoard = new Board();
      $no_marked_dead = ( $this->action == GAMECMD_MOVE || $Status == GAME_STATUS_PLAY || $Status == GAME_STATUS_PASS );
      $board_opts = ( $no_marked_dead ? 0 : BOARDOPT_MARK_DEAD );
      if( !$this->TheBoard->load_from_db( $this->game_row, 0, $board_opts) )
         error('internal_error', "$dbgmsg.load_board($no_marked_dead)");
   }//prepare

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   public function process()
   {
      $cmd = $this->quick_object->cmd;
      if( $cmd == GAMECMD_STATUS_SCORE )
         $this->process_cmd_status_score();
      else
         $this->process_cmd_play();
   }

   private function process_cmd_play()
   {
      static $MOVE_INSERT_QUERY = "INSERT INTO Moves ( gid, MoveNr, Stone, PosX, PosY, Hours ) VALUES ";
      global $player_row, $NOW, $ActivityMax, $ActivityForMove;
      $cmd = $this->quick_object->cmd;
      $gid = $this->gid;
      extract($this->game_row);

      $next_to_move = WHITE + BLACK - $this->to_move;
      $next_to_move_ID = ( $next_to_move == BLACK ) ? $Black_ID : $White_ID;

      // update clock
      list( $hours, $upd_clock ) = GameHelper::update_clock( "QHGame.play($gid)", $this->game_row, $this->to_move, $next_to_move );
      $time_query = $upd_clock->get_query(false, true);

      $mp_query = '';
      $is_mpgame = ($GameType != GAMETYPE_GO);
      if( $is_mpgame && ($cmd == GAMECMD_MOVE || $cmd == GAMECMD_SET_HANDICAP || $cmd == GAMECMD_SCORE) )
      {
         list( $group_color, $group_order, $gpmove_color )
            = MultiPlayerGame::calc_game_player_for_move( $GamePlayers, $Moves, $Handicap, 2 );
         $mp_gp = GamePlayer::load_game_player( $gid, $group_color, $group_order );
         $mp_uid = $mp_gp->uid;
         $mp_query = (( $ToMove_ID == $Black_ID ) ? 'Black_ID' : 'White_ID' ) . "=$mp_uid, ";
      }

      $message_raw = trim($this->message);
      if( preg_match( "/^<c>\s*<\\/c>$/si", $message_raw ) ) // remove empty comment-only tags
         $message_raw = '';
      $message = mysql_addslashes($message_raw);

      if( $message && preg_match( "#</?h(idden)?>#is", $message) )
         $GameFlags |= GAMEFLAGS_HIDDEN_MSG;


      /* **********************
      *** HOT_SECTION ***
      >>> See also confirm.php, quick_play.php and clock_tick.php
      Various dirty things (like duplicated moves) could appear
      in case of multiple calls with the same move number. This could
      happen in case of multi-players account with simultaneous logins
      or if one player hit twice the validation button during a net lag
      and/or if the opponent had already played between the two calls.

      Because the LOCK query is not implemented with MySQL < 4.0,
      and we don't want to use a database-LOCK query (blocks whole table too long with ISAM-engine),
      we use the Moves field of the Games table to check those
      possible multiple queries.
      This is why:
      - the arguments are checked against the current state of the Games table
      - the current Games table give the current Moves value
      - the Games table is always modified while checking its Moves field (see $game_clause)
      - the Games table modification must always modify the Moves field (see $game_query)
      - this modification is always done in first place and checked before continuation
      *********************** */
      $game_clause = " WHERE ID=$gid AND Status".IS_RUNNING_GAME." AND Moves=$Moves LIMIT 1";
      $game_query = $doublegame_query = $move_query = $message_query = '';
      $score = null;
      $Moves++;
      $game_finished = false;

      $action = $this->action;
      switch( (string)$action )
      {
         case GAMECMD_DELETE:
         {
            $game_finished = true;
            break;
         }//delete

         case GAMECMD_SET_HANDICAP:
         {
            // NOTE: moves = list of coordinates of the handicap-stone placement
            $this->TheBoard->add_handicap_stones( $this->moves ); // check coords

            $move_query = $MOVE_INSERT_QUERY; // gid,MoveNr,Stone,PosX,PosY,Hours
            for( $i=1; $i <= $Handicap; $i++ )
            {
               list( $x, $y ) = $this->moves[$i-1];
               $move_query .= "($gid, $i, ".BLACK.", $x, $y, " . ($i == $Handicap ? "$hours)" : "0), " );
            }

            $game_query = "UPDATE Games SET Moves=$Handicap, " . //See *** HOT_SECTION ***
                "Last_X=$x, " .
                "Last_Y=$y, " .
                "Last_Move='" . number2sgf_coords($x, $y, $Size) . "', " .
                "Flags=$GameFlags, " .
                "Snapshot='" . GameSnapshot::make_game_snapshot($Size, $this->TheBoard) . "', " .
                "ToMove_ID=$White_ID, ";
            break;
         }//set_handicap

         case GAMECMD_MOVE:
         {
            // NOTE: moves = single move to submit (non "pass"-move)
            $next_status = GAME_STATUS_PLAY;

            {//to fix the old way Ko detect. Could be removed when no more old way games.
               if( !@$Last_Move ) $Last_Move = number2sgf_coords($Last_X, $Last_Y, $Size);
            }
            $gchkmove = new GameCheckMove( $this->TheBoard );
            $gchkmove->check_move( /*(x,y)*/$this->moves[0], $this->to_move, $Last_Move, $GameFlags );
            $gchkmove->update_prisoners( $Black_Prisoners, $White_Prisoners );

            $move_query = $MOVE_INSERT_QUERY; // gid,MoveNr,Stone,PosX,PosY,Hours

            $prisoner_string = '';
            foreach($gchkmove->prisoners as $coord)
            {
               list( $x, $y ) = $coord;
               $move_query .= "($gid, $Moves, ".NONE.", $x, $y, 0), ";
               $prisoner_string .= number2sgf_coords($x, $y, $Size);
            }

            if( strlen($prisoner_string) != $gchkmove->nr_prisoners*2 )
               error('move_problem', "QuickHandlerGame.process.move.prisoner($gid,{$gchkmove->nr_prisoners},$prisoner_string)");

            $move_query .= "($gid, $Moves, {$this->to_move}, {$gchkmove->colnr}, {$gchkmove->rownr}, $hours) ";

            $game_query = "UPDATE Games SET Moves=$Moves, " . //See *** HOT_SECTION ***
                "Last_X={$gchkmove->colnr}, " . //used with mail notifications
                "Last_Y={$gchkmove->rownr}, " .
                "Last_Move='" . number2sgf_coords($gchkmove->colnr, $gchkmove->rownr, $Size) . "', " . //used to detect Ko
                "Status='$next_status', ";

            if( $gchkmove->nr_prisoners > 0 )
            {
               if( $this->to_move == BLACK )
                  $game_query .= "Black_Prisoners=$Black_Prisoners, ";
               else
                  $game_query .= "White_Prisoners=$White_Prisoners, ";
            }

            if( $gchkmove->nr_prisoners == 1 )
               $GameFlags |= GAMEFLAGS_KO;
            else
               $GameFlags &= ~GAMEFLAGS_KO;

            $game_query .= "ToMove_ID=$next_to_move_ID, " .
               "Flags=$GameFlags, " .
               "Snapshot='" . GameSnapshot::make_game_snapshot($Size, $this->TheBoard) . "', ";
            break;
         }//move

         case GAMEACT_PASS:
         {
            // NOTE: moves = "pass" for passing move
            if( $Status == GAME_STATUS_PLAY )
               $next_status = GAME_STATUS_PASS;
            else if( $Status == GAME_STATUS_PASS )
               $next_status = GAME_STATUS_SCORE;

            $move_query = $MOVE_INSERT_QUERY; // gid,MoveNr,Stone,PosX,PosY,Hours
            $move_query .= "($gid, $Moves, {$this->to_move}, ".POSX_PASS.", 0, $hours)";

            $game_query = "UPDATE Games SET Moves=$Moves, " . //See *** HOT_SECTION ***
                "Last_X=".POSX_PASS.", " .
                "Status='$next_status', " .
                "ToMove_ID=$next_to_move_ID, " .
                //"Last_Move='$Last_Move', " . //Not a move, re-use last one
                "Flags=$GameFlags, "; //Don't reset KO-Flag else PASS,PASS,RESUME could break a Ko
            break;
         }//pass

         case GAMECMD_RESIGN:
         {
            $next_status = GAME_STATUS_FINISHED;
            $score = ( $this->to_move == BLACK ) ? SCORE_RESIGN : -SCORE_RESIGN;

            $move_query = $MOVE_INSERT_QUERY; // gid,MoveNr,Stone,PosX,PosY,Hours
            $move_query .= "($gid, $Moves, {$this->to_move}, ".POSX_RESIGN.", 0, $hours)";

            $game_query = "UPDATE Games SET Moves=$Moves, " . //See *** HOT_SECTION ***
                "Last_X=".POSX_RESIGN.", " .
                "Status='$next_status', " .
                "ToMove_ID=0, " .
                "Score=$score, " .
                "Flags=$GameFlags, ";

            $game_finished = true;
            break;
         }//resign

         case GAMECMD_SCORE:
         {
            // NOTE: moves = coords to toggle for disagreement, toggle = toggle-mode, agree = agreement to finish game
            $stonestring = '';
            $gchkscore = new GameCheckScore( $this->TheBoard, $stonestring, $Handicap, $Komi, $Black_Prisoners, $White_Prisoners );
            if( $this->toggle_mode == GAMEOPTVAL_TOGGLE_UNIQUE )
               $gchkscore->set_toggle_unique();
            $arr_coords = $this->build_arr_coords($Size);
            $game_score = $gchkscore->check_remove( getRulesetScoring($Ruleset), $arr_coords );
            $gchkscore->update_stonestring( $stonestring );
            $score = $game_score->calculate_score();

            $l = strlen( $stonestring );
            if( $Status == GAME_STATUS_SCORE2 && $l < 2 )
            {
               if( !$this->agree )
                  error('invalid_action', "QuickHandlerGame.process.expect_agree({$this->agree})");
               $next_status = GAME_STATUS_FINISHED;
               $game_finished = true;
            }
            else
            {
               if( $this->agree )
                  error('invalid_action', "QuickHandlerGame.process.expect_disagree({$this->agree})");
               $next_status = GAME_STATUS_SCORE2;
            }

            $move_query = $MOVE_INSERT_QUERY; // gid,MoveNr,Stone,PosX,PosY,Hours
            $mark_stone = ( $this->to_move == BLACK ) ? MARKED_BY_BLACK : MARKED_BY_WHITE;
            for( $i=0; $i < $l; $i += 2 )
            {
               list($x,$y) = sgf2number_coords(substr($stonestring, $i, 2), $Size);
               $move_query .= "($gid, $Moves, $mark_stone, $x, $y, 0), ";
            }
            $move_query .= "($gid, $Moves, {$this->to_move}, ".POSX_SCORE.", 0, $hours) ";

            $game_query = "UPDATE Games SET Moves=$Moves, " . //See *** HOT_SECTION ***
                "Last_X=".POSX_SCORE.", " .
                "Status='$next_status', " .
                "Score=$score, " .
                //"Last_Move='$Last_Move', " . //Not a move, re-use last one
                "Flags=$GameFlags, " . //Don't reset KO-Flag else SCORE,RESUME could break a Ko
                "Snapshot='" . GameSnapshot::make_game_snapshot($Size, $this->TheBoard) . "', ";

            if( $next_status != GAME_STATUS_FINISHED )
               $game_query .= "ToMove_ID=$next_to_move_ID, ";
            else
               $game_query .= "ToMove_ID=0, ";
            break;
         }//score

         default:
            error('invalid_action', "QuickHandlerGame.process.noaction($gid,$action,$Status)");
            break;
      }//switch $action

      if( $cmd != GAMECMD_DELETE )
      {
         $game_query .= $mp_query . $time_query . "Lastchanged=FROM_UNIXTIME($NOW)" ;

         if( $message )
         {
            $move_nr = ( $cmd == GAMECMD_SET_HANDICAP ) ? $Handicap : $Moves;
            $message_query = "INSERT INTO MoveMessages SET gid=$gid, MoveNr=$move_nr, Text=\"$message\"";
         }
      }


      ta_begin();
      {//HOT-section to update game-action

         //See *** HOT_SECTION *** above
         if( $game_query )
         {
            $result = db_query( "QuickHandlerGame.process.update_game($gid,$action})", $game_query . $game_clause );
            if( mysql_affected_rows() != 1 )
               error('mysql_update_game', "QuickHandlerGame.process.update_game2($gid,$action})");

            GameHelper::delete_cache_game_row( "QuickHandlerGame.process.update_game3($gid,$action)", $gid );
         }

         if( $move_query )
         {
            $result = db_query( "QuickHandlerGame.process.update_moves($gid,$action})", $move_query );
            if( mysql_affected_rows() < 1 && $this->action != GAMECMD_DELETE )
               error('mysql_insert_move', "QuickHandlerGame.process.update_moves2($gid,$action})");

            clear_cache_quick_status( array( $ToMove_ID, $next_to_move_ID ), QST_CACHE_GAMES );
            GameHelper::delete_cache_status_games( "QuickHandlerGame.process.update_moves($gid,$action})", $ToMove_ID, $next_to_move_ID );
            Board::delete_cache_game_moves( "QuickHandlerGame.process.update_moves($gid,$action})", $gid );
         }

         if( $message_query )
         {
            $result = db_query( "QuickHandlerGame.process.insert_movemessage($gid,$action})", $message_query );
            if( mysql_affected_rows() < 1 && $this->action != GAMECMD_DELETE )
               error('mysql_insert_move', "QuickHandlerGame.process.insert_movemessage2($gid,$action})");

            Board::delete_cache_game_move_messages( "QuickHandlerGame.process.insert_movemessage($gid,$action})", $gid );
         }

         if( $game_finished )
         {
            $game_finalizer = new GameFinalizer( ACTBY_PLAYER, $this->my_id, $gid, $tid,
               $Status, $GameType, $GamePlayers, $GameFlags, $Black_ID, $White_ID, $Moves, ($Rated != 'N') );

            $do_delete = ( $this->action == GAMECMD_DELETE );

            $game_finalizer->skip_game_query();
            $game_finalizer->finish_game( "QuickHandlerGame.process", $do_delete, null, $score, $message_raw );
         }
         else
            $do_delete = false;

         // Notify opponent about move
         if( !$do_delete )
            notify( "QuickHandlerGame.process.notify_opponent($gid,$action,$next_to_move_ID})", $next_to_move_ID );

         // Increase moves and activity
         db_query( "QuickHandlerGame.process.update_activity($gid,$action})",
            "UPDATE Players SET Moves=Moves+1" . // NOTE: count also delete + set-handicap as one move
               ",Activity=LEAST($ActivityMax,$ActivityForMove+Activity)" .
               ",LastMove=FROM_UNIXTIME($NOW)" .
            " WHERE ID={$this->my_id} LIMIT 1" );

         increaseMoveStats( $this->my_id );
      }
      ta_end();
   }//process_cmd_play

   private function process_cmd_status_score()
   {
      // NOTE: moves = coords to toggle, toggle = toggle-mode, fmt = coordinate-format for output
      $gid = $this->gid;
      extract($this->game_row);

      $arr_coords = $this->build_arr_coords($Size);
      $gchkscore = new GameCheckScore( $this->TheBoard, /*stonestr*/'', $Handicap, $Komi, $Black_Prisoners, $White_Prisoners );
      if( $this->toggle_mode == GAMEOPTVAL_TOGGLE_UNIQUE )
         $gchkscore->set_toggle_unique();
      $game_score = $gchkscore->check_remove( getRulesetScoring($Ruleset), $arr_coords, /*board-status*/true );
      $score = $game_score->calculate_score();

      $this->addResultKey( 'ruleset', strtoupper($Ruleset) );
      $this->addResultKey( 'score', score2text($score, false, false, true) );

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
      foreach( $this->moves as $arr_xy )
      {
         list($x,$y) = $arr_xy;
         $arr_coords[] = number2sgf_coords($x, $y, $size);
      }
      return $arr_coords;
   }//build_arr_coords

   /*! \brief Checks syntax and splits moves into array this->moves removing double coords, or detect pass-move. */
   private function prepareMoves()
   {
      if( is_null($this->url_moves) )
         return;

      $this->moves = array();
      if( empty($this->url_moves) )
         return;

      if( (string)$this->url_moves == GAMEOPTVAL_MOVE_PASS )
      {
         $this->is_pass_move = true;
         return;
      }

      $size = $this->game_row['Size'];
      $is_label_format = preg_match("/\\d/", $this->url_moves); // label | sgf format

      $arr_double = array(); // check for doublettes
      $arr_coords = self::parse_coords( $this->url_moves );
      foreach( $arr_coords as $coord )
      {
         $xy_arr = ($is_label_format) ? board2number_coords($coord, $size) : sgf2number_coords($coord, $size);
         if( is_null($xy_arr[0]) || is_null($xy_arr[1]) )
            error('invalid_coord', "QuickHandlerGame.prepareMoves({$this->gid},$size,[$coord]})");

         if( !isset($arr_double[$coord]) ) // remove double coords
            $this->moves[] = $xy_arr;
         $arr_double[$coord] = 1;
      }
   }//prepareMoves


   // ---------- static funcs ----------------------

   /*! \brief Converts string of SGF-coords (without comma) into other coordinate-format. */
   private static function convert_coords( $coord_str, $fmt, $size )
   {
      if( $fmt == 'sgf' )
         return $coord_str;
      elseif( $fmt == 'board' )
      {
         $out = array();
         $coord_len = strlen($coord_str);
         for( $i=0; $i < $coord_len; $i+=2 )
            $out[] = sgf2board_coords( substr($coord_str, $i, 2 ), $size );
         return implode(',', $out);
      }
      else // fmt= separator
      {
         $out = array();
         $coord_len = strlen($coord_str);
         for( $i=0; $i < $coord_len; $i+=2 )
            $out[] = substr($coord_str, $i, 2);
         return implode($fmt, $out);
      }
   }//convert_coords

   /*! \brief Returns array of coordinate-strings (supported formats: sgf, sgf-comma, board). */
   private static function parse_coords( $move_str )
   {
      if( strpos($move_str, ',') !== false || preg_match("/\\d/", $move_str) )
         return explode(',', $move_str);
      else
      {
         $out = array();
         $move_len = strlen($move_str);
         for( $i=0; $i < $move_len; $i += 2 )
            $out[] = substr($move_str, $i, 2);
         return $out;
      }
   }//parse_coords

} // end of 'QuickHandlerGame'

?>
