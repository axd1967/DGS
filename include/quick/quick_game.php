<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'tournaments/include/tournament_games.php';
require_once 'include/time_functions.php';
require_once 'include/rating.php';
require_once 'include/classlib_user.php';

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

define('GAMEOPTVAL_MOVE_PASS', 'pass');

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
   var $gid;
   var $move_id;
   var $url_moves; // if null, expect 'moves' and 'is_pass_move' correctly initialized
   var $moves; // coords-array, if null -> parse from url_move
   var $is_pass_move;
   var $message;
   var $stonestring;

   var $game_row;
   var $TheBoard;
   var $to_move;
   var $action;

   function QuickHandlerGame( $quick_object )
   {
      parent::QuickHandler( $quick_object );
      $this->gid = 0;
      $this->url_moves = '';
      $this->moves = null;
      $this->is_pass_move = false;
      $this->stonestring = null;

      $this->game_row = null;
      $this->TheBoard = null;
      $this->to_move = null;
      $this->action = null;
   }


   // ---------- Interface ----------------------------------------

   function canHandle( $obj, $cmd ) // static
   {
      return ( $obj == QOBJ_GAME ) && QuickHandler::matchRegex(GAME_COMMANDS, $cmd);
   }

   function parseURL()
   {
      $this->gid = (int)get_request_arg(GAMEOPT_GID);
      $this->move_id = (int)get_request_arg(GAMEOPT_MOVEID);
      $this->message = get_request_arg(GAMEOPT_MESSAGE);
      $this->url_moves = get_request_arg(GAMEOPT_MOVES);
   }

   //TODO TODO TODO add status_score-command !!!
   function prepare()
   {
      global $player_row;
      $uid = (int)@$player_row['ID'];

      // see specs/quick_suite.txt (3a)
      $dbgmsg = "QuickHandlerGame.prepare($uid,{$this->gid})";
      $this->checkCommand( $dbgmsg, GAME_COMMANDS );
      $cmd = $this->quick_object->cmd;

      // check gid
      QuickHandler::checkArgMandatory( $dbgmsg, GAMEOPT_GID, $this->gid );
      if( !is_numeric($this->gid) || $this->gid <= 0 )
         error('unknown_game', "QuickHandlerGame.check({$this->gid})");
      $gid = $this->gid;

      // check move(s) + context (move-id)
      QuickHandler::checkArgMandatory( $dbgmsg, GAMEOPT_MOVEID, $this->move_id );
      if( $cmd == GAMECMD_SET_HANDICAP || $cmd == GAMECMD_MOVE || $cmd == GAMECMD_STATUS_SCORE | $cmd == GAMECMD_SCORE )
      {
         //TODO why url_moves name (URL ??)
         if( is_null($this->url_moves) )
         {
            //TODO how can this->moves be an array ?? ... 1st need prepareMoves()-func
            if( !is_array($this->moves) )
               error('invalid_args', "QuickHandlerGame::checkArgMandatory.miss_arg($dbgmsg,".GAMEOPT_MOVES.")");
         }
         else
         {
            if( $cmd == GAMECMD_SET_HANDICAP || $cmd == GAMECMD_MOVE )
               QuickHandler::checkArgMandatory( $dbgmsg, GAMEOPT_MOVES, $this->url_moves );
         }
      }


      // prepare command: del, resign; set_handi, move, score

      $this->game_row = mysql_single_fetch( "QuickHandlerGame.prepare.find_game($gid)",
                 "SELECT Games.*, " .
                 "Games.Flags+0 AS GameFlags, " . //used by check_move
                 "black.ClockUsed AS X_BlackClock, " .
                 "white.ClockUsed AS X_WhiteClock, " .
                 "black.OnVacation AS Blackonvacation, " .
                 "white.OnVacation AS Whiteonvacation " .
                 "FROM (Games, Players AS black, Players AS white) " .
                 "WHERE Games.ID=$gid AND black.ID=Black_ID AND white.ID=White_ID" )
            or error('unknown_game', "QuickHandlerGame.prepare.find_game2($gid)");
      extract($this->game_row);

      if( $cmd == GAMECMD_SET_HANDICAP || $cmd == GAMECMD_MOVE || $cmd == GAMECMD_SCORE )
         $this->prepareMoves();
      if( $this->is_pass_move && $cmd != GAMECMD_MOVE )
         error('move_problem', "QuickHandlerGame.prepare.check.pass_move($gid,$cmd)");


      // affirm, that game is running
      if( $Status == GAME_STATUS_INVITED || $Status == GAME_STATUS_SETUP )
         error('game_not_started', "QuickHandlerGame.prepare.check.status($gid,$Status)");
      elseif( $Status == GAME_STATUS_FINISHED )
         error('game_finished', "QuickHandlerGame.prepare.check.status.finished($gid)");
      elseif( !isRunningGame($Status) )
         error('invalid_game_status', "QuickHandlerGame.prepare.check.game_status($gid,$Status)");

      if( $Black_ID == $ToMove_ID )
         $this->to_move = BLACK;
      elseif( $White_ID == $ToMove_ID )
         $this->to_move = WHITE;
      else
         error('database_corrupted', "QuickHandlerGame.prepare.check.to_move($gid)");

      if( $this->my_id != $Black_ID && $this->my_id != $White_ID )
         error('not_game_player', "QuickHandlerGame.prepare.check.not_your_game($gid)");
      if( !($cmd == GAMECMD_DELETE || $cmd == GAMECMD_RESIGN) ) // allow delete|resign even if not-to-move
      {
         if( $my_id != $ToMove_ID )
            error('not_your_turn', "QuickHandlerGame.prepare.check.move_user($gid,$ToMove_ID)");
      }

      if( (int)$this->move_id != $Moves )
         error('already_played', "QuickHandlerGame.prepare.check.move_id.move_id($gid,{$this->move_id},$Moves)");


      // check for invalid-action

      $this->action = $cmd;
      if( $cmd == GAMECMD_DELETE )
      {
         $too_few_moves = ( $Moves < DELETE_LIMIT + $Handicap );
         $may_del_game  = $too_few_moves && ( $tid == 0 ) && ($GameType == GAMETYPE_GO);
         if( !$may_del_game )
            error('invalid_action', "QuickHandlerGame.prepare.check.status($gid,$cmd,$Handicap,$Moves,$tid)");
      }
      elseif( $cmd == GAMECMD_SET_HANDICAP )
      {
         if( $Status != GAME_STATUS_PLAY || $Handicap == 0 || $Moves > 0 || $this->to_move != BLACK )
            error('invalid_action', "QuickHandlerGame.prepare.check.status($gid,$cmd,$Status,$Handicap,$Moves)");
         if( count($this->moves) != $Handicap )
            error('wrong_number_of_handicap_stone', "QuickHandlerGame.prepare.check.move_count($gid,$cmd,$Handicap,{$this->url_moves})");
      }
      elseif( $cmd == GAMECMD_MOVE )
      {
         if( $this->is_pass_move )
         {
            $this->action = GAMEACT_PASS;

            if( $Handicap > 0 && $Moves < $Handicap )
               error('early_pass', "QuickHandlerGame.prepare.check.pass($gid,$Handicap,$Moves)");
            if( $Status != GAME_STATUS_PLAY && $Status != GAME_STATUS_PASS )
               error('invalid_action', "QuickHandlerGame.prepare.check.pass.status($gid,$cmd,$Status)");
         }
         else
         {
            if( $Moves < $Handicap )
               error('invalid_action', "QuickHandlerGame.prepare.check.miss_handicap($gid,$uid,$cmd,$Moves,$Handicap)");
            if( count($this->moves) != 1 )
               error('invalid_args', "QuickHandlerGame.prepare.check.move_count($gid,$cmd)");
         }
      }
      elseif( $cmd == GAMECMD_RESIGN )
      {// no checks
      }
      elseif( $cmd == GAMECMD_SCORE )
      {
         if( $Status != GAME_STATUS_SCORE && $Status != GAME_STATUS_SCORE2 )
            error('invalid_action', "QuickHandlerGame.prepare.check.status($gid,$cmd,$Status)");
      }

      $this->TheBoard = new Board();
      $no_marked_dead = ( $this->action == GAMECMD_MOVE || $Status == GAME_STATUS_PLAY || $Status == GAME_STATUS_PASS );
      if( !$this->TheBoard->load_from_db( $this->game_row, 0, $no_marked_dead) )
         error('internal_error', "QuickHandlerGame.prepare.load_board($gid,$no_marked_dead)");
   }//prepare

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   function process()
   {
      $cmd = $this->quick_object->cmd;
      $this->process_cmd_play(); //TODO later split into separate commands for clarity
   }

   function process_cmd()
   {
      static $MOVE_INSERT_QUERY = "INSERT INTO Moves ( gid, MoveNr, Stone, PosX, PosY, Hours ) VALUES ";
      global $player_row, $NOW;
      $cmd = $this->quick_object->cmd;
      $gid = $this->gid;
      extract($this->game_row);

      $next_to_move = WHITE + BLACK - $this->to_move;
      $next_to_move_ID = ( $next_to_move == BLACK ) ? $Black_ID : $White_ID;

      list( $time_query, $hours ) = $this->update_clock();

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
      $message_query = '';
      $doublegame_query = '';
      $Moves++;
      $game_finished = false;

      $action = $this->action;
      switch( (string)$action )
      {
         case GAMECMD_DELETE:
         {
            /*
              No LIMIT for 'DELETE FROM Moves', because the number of Moves-records
              could be greater than the number of moves if:
              - there are prisoners
              - a sequence like PASS/PASS/SCORE.../RESUME had already occured
              - time has been added to opponent
              => so some garbage records could remain!
            */
            $move_query = "DELETE FROM Moves WHERE gid=$gid";
            $message_query = "DELETE FROM MoveMessages WHERE gid=$gid LIMIT $Moves";
            $game_query = "DELETE FROM Games" ; //See *** HOT_SECTION ***

            // mark reference in other double-game to indicate referring game has vanished
            $dbl_gid = (int)@$this->game_row['DoubleGame_ID'];
            if( $dbl_gid > 0 )
               $doublegame_query = "UPDATE Games SET DoubleGame_ID=-ABS(DoubleGame_ID) WHERE ID=$dbl_gid LIMIT 1";

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
                "ToMove_ID=$next_to_move_ID, ";
            break;
         }//set_handicap

         case GAMECMD_MOVE:
         {
            // NOTE: moves = single move to submit, move="pass" for passing move
            // NOTE: (optional) this->stonestring is the list of prisoners (to verify)
            $next_status = GAME_STATUS_PLAY;

            {//to fix the old way Ko detect. Could be removed when no more old way games.
               if( !@$Last_Move ) $Last_Move = number2sgf_coords($Last_X, $Last_Y, $Size);
            }
            check_move( $this->TheBoard, $this->moves[0], $this->to_move);
            //adjusted globals by check_move(): $Black_Prisoners, $White_Prisoners, $prisoners, $nr_prisoners, $colnr, $rownr;
            //here, $prisoners list the captured stones of play (or suicided stones if, a day, $suicide_allowed==true)

            $move_query = $MOVE_INSERT_QUERY; // gid,MoveNr,Stone,PosX,PosY,Hours

            $prisoner_string = '';
            foreach($prisoners as $coord)
            {
               list( $x, $y ) = $coord;
               $move_query .= "($gid, $Moves, ".NONE.", $x, $y, 0), ";
               $prisoner_string .= number2sgf_coords($x, $y, $Size);
            }

            if( strlen($prisoner_string) != $nr_prisoners*2
                  || ( $this->stonestring && $prisoner_string != $this->stonestring) )
               error('move_problem', "QuickHandlerGame.process.move.prisoner($gid,$nr_prisoners,$prisoner_string,{$this->stonestring})");

            $move_query .= "($gid, $Moves, {$this->to_move}, $x, $y, $hours) ";

            $game_query = "UPDATE Games SET Moves=$Moves, " . //See *** HOT_SECTION ***
                "Last_X=$colnr, " . //used with mail notifications
                "Last_Y=$rownr, " .
                "Last_Move='" . number2sgf_coords($colnr, $rownr, $Size) . "', " . //used to detect Ko
                "Status='$next_status', ";

            if( $nr_prisoners > 0 )
            {
               if( $this->to_move == BLACK )
                  $game_query .= "Black_Prisoners=$Black_Prisoners, ";
               else
                  $game_query .= "White_Prisoners=$White_Prisoners, ";
            }

            if( $nr_prisoners == 1 )
               $GameFlags |= GAMEFLAGS_KO;
            else
               $GameFlags &= ~GAMEFLAGS_KO;

            $game_query .= "ToMove_ID=$next_to_move_ID, " .
                "Flags=$GameFlags, ";
            break;
         }//move

         case GAMEACT_PASS:
         {
            if( $Status == GAME_STATUS_PLAY )
               $next_status = GAME_STATUS_PASS;
            else if( $Status == GAME_STATUS_PASS )
               $next_status = GAME_STATUS_SCORE;

            $move_query = $MOVE_INSERT_QUERY; // gid,MoveNr,Stone,PosX,PosY,Hours
            $move_query .= "($gid, $Moves, {$this->to_move}, ".POSX_PASS.", 0, $hours)";

            $game_query = "UPDATE Games SET Moves=$Moves, " . //See *** HOT_SECTION ***
                "Last_X=".POSX_PASS.", " .
                "Status='$next_status', " .
                "ToMove_ID=$next_to_move_ID, ";
                //"Last_Move='$Last_Move', " . //Not a move, re-use last one
                //"Flags=$GameFlags, " . //Don't reset Flags else PASS,PASS,RESUME could break a Ko
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
                "Score=$score, ";

            $game_finished = true;
            break;
         }//resign

         case GAMECMD_SCORE:
         {
            //TODO // required opts: MOVE = moves : list of coordinates of stones marked as dead
            // NOTE: stonestring is the list of toggled points
            $stonestring = (string)@$_REQUEST['stonestring'];
            $game_score = check_remove( $this->TheBoard, getRulesetScoring($Ruleset) ); //adjusted globals: $stonestring
            $score = $game_score->calculate_score();

            $l = strlen( $stonestring );
            if( $Status == GAME_STATUS_SCORE2 && $l < 2 )
            {
               $next_status = GAME_STATUS_FINISHED;
               $game_finished = true;
            }
            else
               $next_status = GAME_STATUS_SCORE2;

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
                "Score=$score, ";
                //"Last_Move='$Last_Move', " . //Not a move, re-use last one
                //"Flags=$GameFlags, " . //Don't reset Flags else SCORE,RESUME could break a Ko

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
         $game_query .= $time_query . "Lastchanged=FROM_UNIXTIME($NOW)" ;

         if( $message )
         {
            $move_nr = ( $cmd == GAMECMD_SET_HANDICAP ) ? $Handicap : $Moves;
            $message_query = "INSERT INTO MoveMessages SET gid=$gid, MoveNr=$move_nr, Text=\"$message\"";
         }
      }


      ta_begin();
      {//HOT-section to update game-action

         //See *** HOT_SECTION *** above
         $result = db_query( "QuickHandlerGame.process.update_game($gid,$action})", $game_query . $game_clause );
         if( mysql_affected_rows() != 1 )
            error('mysql_update_game', "QuickHandlerGame.process.update_game2($gid,$action})");

         $result = db_query( "QuickHandlerGame.process.update_moves($gid,$action})", $move_query );
         if( mysql_affected_rows() < 1 && $this->action != GAMECMD_DELETE )
            error('mysql_insert_move', "QuickHandlerGame.process.update_moves2($gid,$action})");

         if( $message_query )
         {
            $result = db_query( "QuickHandlerGame.process.insert_movemessage($gid,$action})", $message_query );
            if( mysql_affected_rows() < 1 && $this->action != GAMECMD_DELETE )
               error('mysql_insert_move', "QuickHandlerGame.process.insert_movemessage2($gid,$action})");
         }


         if( $game_finished )
         {
            // signal game-end for tournament
            if( $tid > 0 )
               TournamentGames::update_tournament_game_end( "QuickHandlerGame.process.tourney_game_end.$action}",
                  $tid, $gid, $Black_ID, $score );

            // send message to opponent about the result

            $opponent_row = mysql_single_fetch( "QuickHandlerGame.process.find_opponent($gid,$White_ID,$Black_ID)",
                  "SELECT ID,Handle,Name FROM Players " .
                  "WHERE ID=" . ($White_ID + $Black_ID - $this->my_id) . " LIMIT 1" )
               or error('opponent_not_found', "QuickHandlerGame.process.find_opponent2($gid,$White_ID,$Black_ID)");

            if( $this->my_id == $Black_ID )
            {
               $blackname = $player_row['Name'];
               $whitename = $opponent_row['Name'];
               $blackhandle = $player_row['Handle'];
               $whitehandle = $opponent_row['Handle'];
            }
            else
            {
               $whitename = $player_row['Name'];
               $blackname = $opponent_row['Name'];
               $whitehandle = $player_row['Handle'];
               $blackhandle = $opponent_row['Handle'];
            }


            if( $this->action == GAMECMD_DELETE )
            {
               db_query( "QuickHandlerGame.process.delete.update_players($gid,$Black_ID,$White_ID)",
                  "UPDATE Players SET Running=Running-1 WHERE ID IN ($Black_ID,$White_ID) LIMIT 2" );

               db_query( "QuickHandlerGame.process.delete_gamenotes($gid,$action})",
                  "DELETE FROM GamesNotes WHERE gid=$gid LIMIT 2" );

               // mark reference in other double-game to indicate referring game has vanished
               if( $doublegame_query )
                  db_query( "QuickHandlerGame.process.delete.update_doublegame($gid,$action})", $doublegame_query );

               $Subject = 'Game deleted';
               //reference: game is deleted => no link
               $Text = "The game:<center>"
                     . game_reference( 0, 1, '', $gid, 0, $whitename, $blackname)
                     . "</center>has been deleted by your opponent.<br>";

               delete_all_observers($gid, false);
            }
            else
            {
               $rated_status = update_rating2($gid); //0=rated game

               $query = "UPDATE Players SET Running=Running-1, Finished=Finished+1" .
                         ($rated_status ? '' : ", RatedGames=RatedGames+1" .
                              ($score > 0 ? ", Won=Won+1" : ($score < 0 ? ", Lost=Lost+1 " : ""))
                         ) . " WHERE ID=$White_ID LIMIT 1" ;
               db_query( "QuickHandlerGame.process.update_players_finished.W($gid,$action,$White_ID)", $query );

               $query = "UPDATE Players SET Running=Running-1, Finished=Finished+1" .
                         ($rated_status ? '' : ", RatedGames=RatedGames+1" .
                              ($score < 0 ? ", Won=Won+1" : ($score > 0 ? ", Lost=Lost+1 " : ""))
                         ) . " WHERE ID=$Black_ID LIMIT 1" ;
               db_query( "QuickHandlerGame.process.update_players_finished.B($gid,$action,$Black_ID)", $query );

               $Subject = 'Game result'; // note: don't translate
               $Text = "The result in the game:<center>"
                     . game_reference( REF_LINK, 1, '', $gid, 0, $whitename, $blackname)
                     . "</center>was:<center>"
                     . score2text($score,true,true)
                     . "</center>";

               $tmpText = $Text . "<p>Send a message to:<center>"
                     . send_reference( REF_LINK, 1, '', $White_ID, $whitename, $whitehandle)
                     . "<br>"
                     . send_reference( REF_LINK, 1, '', $Black_ID, $blackname, $blackhandle)
                     . "</center>" ;

               delete_all_observers($gid, $rated_status!=1, $tmpText);
            }

            // GamesPriority-entries are kept for running games only, delete for finished games too
            NextGameOrder::delete_game_priorities( $gid );

            //Send a message to the opponent

            if( $this->action != GAMECMD_DELETE )
            {
               if( $GameFlags & GAMEFLAGS_HIDDEN_MSG )
                  $Text .= "<p><b>Info:</b> The game has hidden comments!";
            }

            // NOTE: server messages does not allow a reply, so add an *in message* reference to this player.
            $Text .= "<p>Send a message to:<center>"
                  . send_reference( REF_LINK, 1, '', $this->my_id, $player_row['Name'], $player_row['Handle'])
                  . "</center>" ;

            if( $message_raw )
            {
               // NOTE: server message will only be read by this player
               $Text .= "<p>Your opponent wrote:<p></p>" . $message_raw;
            }

            send_message( 'confirm', $Text, $Subject
               , $opponent_row['ID'], ''
               , /*notify*/false //the move itself is always notified, see below
               , /*system-msg*/0
               , 'RESULT', $gid);
         }//game_finished


         // Notify opponent about move

if(1){ //new
         notify( 'confirm', $next_to_move_ID);
}else{ //old
         db_query( "QuickHandlerGame.process.notify_opponent($gid,$action,$next_to_move_ID})",
            "UPDATE Players SET Notify='NEXT' " .
            "WHERE ID='$next_to_move_ID' AND Notify='NONE' " .
            "AND FIND_IN_SET('ON',SendEmail) LIMIT 1" );
} //old/new


         // Increase moves and activity

         db_query( "QuickHandlerGame.process.update_activity($gid,$action})",
            "UPDATE Players SET Moves=Moves+1" . // NOTE: TODO count delete/set_handicap as (one) move?
               ",Activity=LEAST($ActivityMax,$ActivityForMove+Activity)" .
               ",LastMove=FROM_UNIXTIME($NOW)" .
            " WHERE ID={$this->my_id} LIMIT 1" );
      }
      ta_end();
   }//process_cmd_play

   /*! \brief Checks syntax and splits moves into array this->moves removing double coords, or detect pass-move. */
   function prepareMoves()
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
      //TODO handle sgf-coords w/o comma-sep
      $arr_coords = explode(',', $this->url_moves);
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

   // returns ( $time_query, $hours )
   function update_clock()
   {
      extract($this->game_row);

      if( $Maintime > 0 || $Byotime > 0)
      {
         // LastTicks may handle -(time spend) at the moment of the start of vacations
         // time since start of move in the reference of the ClockUsed by the game
         $hours = ticks_to_hours(get_clock_ticks($ClockUsed) - $LastTicks);

         if( $this->to_move == BLACK )
         {
            time_remaining( $hours, $Black_Maintime, $Black_Byotime, $Black_Byoperiods,
               $Maintime, $Byotype, $Byotime, $Byoperiods, true);
            $time_query = "Black_Maintime=$Black_Maintime, " .
                "Black_Byotime=$Black_Byotime, " .
                "Black_Byoperiods=$Black_Byoperiods, ";
         }
         else
         {
            time_remaining( $hours, $White_Maintime, $White_Byotime, $White_Byoperiods,
               $Maintime, $Byotype, $Byotime, $Byoperiods, true);
            $time_query = "White_Maintime=$White_Maintime, " .
                "White_Byotime=$White_Byotime, " .
                "White_Byoperiods=$White_Byoperiods, ";
         }

         if( ($this->to_move == WHITE ? $Blackonvacation : $Whiteonvacation) > 0 )
         {
            $next_clockused = VACATION_CLOCK; //and LastTicks=0, see below
         }
         else
         {
            $next_clockused = ( $next_to_move == BLACK ) ? $X_BlackClock : $X_WhiteClock;
            if( $WeekendClock != 'Y' )
               $next_clockused += WEEKEND_CLOCK_OFFSET;
         }

         $next_lastticks = get_clock_ticks($next_clockused);

         $timeout_date = NextGameOrder::make_timeout_date( $this->game_row, $next_to_move, $next_lastticks );

         $time_query .= "LastTicks=$next_lastticks, "
            . "ClockUsed=$next_clockused, "
            . "TimeOutDate=$timeout_date, ";
      }
      else
      {
         $hours = 0;
         $time_query = '';
      }

      return array( $time_query, $hours );
   }//update_clock

} // end of 'QuickHandlerGame'

?>
