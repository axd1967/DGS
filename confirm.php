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

$TranslateGroups[] = "Game"; // same as for game.php

require_once( "include/std_functions.php" );
require_once( "include/std_classes.php" );
require_once( "include/game_functions.php" );
require_once( "include/board.php" );
require_once( "include/move.php" );
require_once( "include/rating.php" );
require_once( 'include/classlib_game.php' );
require_once 'tournaments/include/tournament_games.php';



function jump_to_next_game($uid, $Lastchanged, $Moves, $TimeOutDate, $gid)
{
   global $player_row;

   $order = NextGameOrder::get_next_game_order(
      'Games', $player_row['NextGameOrder'], true ); // enum -> order
   $qsql = new QuerySQL(
         SQLP_FIELDS, 'ID',
         SQLP_FROM,   'Games',
         SQLP_WHERE,  "ToMove_ID=$uid", 'Status '.IS_RUNNING_GAME,
         SQLP_ORDER,  $order,
         SQLP_LIMIT,  1
      );

   // restrictions must be oriented on next-game-order-string to keep order
   // like for status-games on status-page
   $def_where_nextgame =
      "( Lastchanged > '$Lastchanged' OR ( Lastchanged = '$Lastchanged' AND ID>$gid ))";
   switch( (string)$player_row['NextGameOrder'] )
   {
      case NGO_MOVES:
         $qsql->add_part( SQLP_WHERE,
            "( Moves < $Moves OR (Moves=$Moves AND $def_where_nextgame ))" );
         break;
      case NGO_PRIO:
         $prio = NextGameOrder::load_game_priority( $gid, $uid );
         $qsql->add_part( SQLP_FIELDS, 'COALESCE(GP.Priority,0) AS X_Priority' );
         $qsql->add_part( SQLP_FROM,
            "LEFT JOIN GamesPriority AS GP ON GP.gid=Games.ID AND GP.uid=$uid" );
         $qsql->add_part( SQLP_WHERE,
            "( COALESCE(GP.Priority,0) < $prio OR "
               . "(COALESCE(GP.Priority,0)=$prio AND $def_where_nextgame ))" );
         break;
      case NGO_TIMELEFT:
         $qsql->add_part( SQLP_WHERE,
            "( TimeOutDate > $TimeOutDate OR (TimeOutDate=$TimeOutDate AND $def_where_nextgame ))" );
         break;
      default: //case NGO_LASTMOVED
         $qsql->add_part( SQLP_WHERE, $def_where_nextgame );
         break;
   }

   $row = mysql_single_fetch( "confirm.jump_to_next_game($gid,$uid)", $qsql->get_select() );
   if( !$row )
      jump_to("status.php");

   jump_to("game.php?gid=" . $row['ID']);
}//jump_to_next_game



{
   disable_cache();

   $gid = (int)@$_REQUEST['gid'] ;
   if( $gid <= 0 )
      error('unknown_game');

   if( @$_REQUEST['cancel'] )
      jump_to("game.php?gid=$gid");

   connect2mysql();
   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];

   $game_row = mysql_single_fetch( "confirm.find_game($gid)",
                 "SELECT Games.*, " .
                 "Games.Flags+0 AS GameFlags, " . //used by check_move
                 "black.ClockUsed AS X_BlackClock, " .
                 "white.ClockUsed AS X_WhiteClock, " .
                 "black.OnVacation AS Blackonvacation, " .
                 "white.OnVacation AS Whiteonvacation " .
                 "FROM (Games, Players AS black, Players AS white) " .
                 "WHERE Games.ID=$gid AND black.ID=Black_ID AND white.ID=White_ID" )
      or error('unknown_game', "confirm.find_game2($gid)");

   extract($game_row);

   if( @$_REQUEST['nextskip'] )
      jump_to_next_game( $my_id, $Lastchanged, $Moves, $TimeOutDate, $gid);

   if( @$_REQUEST['nextaddtime'] )
      do_add_time( $game_row, $my_id); // jump back

   if( $Status == GAME_STATUS_INVITED || $Status == GAME_STATUS_SETUP )
      error('game_not_started', "confirm.check.bad_status($gid,$Status)");
   elseif( $Status == GAME_STATUS_FINISHED )
      error('game_finished', "confirm.check.finished($gid)");

   if( $ToMove_ID == 0 )
      error('game_finished', "confirm.bad_ToMove_ID.gamend($gid)");
   if( $Black_ID == $ToMove_ID )
      $to_move = BLACK;
   elseif( $White_ID == $ToMove_ID )
      $to_move = WHITE;
   else
      error('database_corrupted', "confirm.bad_ToMove_ID($gid)");

   $action = @$_REQUEST['action'];
   if( $Moves < $Handicap && $action == 'domove' )
      error('invalid_action', "confirm.check.miss_handicap($gid,$my_id,$action,$Moves,$Handicap)");

   $stay_on_board = @$_REQUEST['stay'];
   $my_game = ( $my_id == $Black_ID || $my_id == $White_ID );
   $is_mpgame = ( $GameType != GAMETYPE_GO );

   $is_running_game = isRunningGame($Status);

   $too_few_moves = ( $Moves < DELETE_LIMIT+$Handicap );
   $may_del_game  = $my_game && $too_few_moves && $is_running_game && ( $tid == 0 )
      && ($GameType == GAMETYPE_GO);

   $may_resign_game = $my_game && $is_running_game;
   if( $action == 'resign' )
   {
      if( !$may_resign_game )
         error('invalid_action', "confirm.resign($gid,$Status)");

      if( $my_id != $ToMove_ID )
         $to_move = WHITE+BLACK-$to_move;
   }

   if( $is_mpgame && ($action == 'resign' || $action == 'done') )
      error('invalid_action', "confirm.check.action.mpgame($gid,$my_id,$GameType,$action)");

   if( $my_id != $ToMove_ID && !$may_del_game && !$may_resign_game )
      error('not_your_turn', "confirm.check_tomove($gid,$ToMove_ID,$may_del_game,$may_resign_game)");


   //See *** HOT_SECTION *** below
   if( !isset($_REQUEST['move']) )
      error('move_problem', "confirm.check.miss_move($gid)");
   $qry_move = @$_REQUEST['move'];
   if( $qry_move != $Moves )
      error('already_played', "confirm.check.move($gid,$qry_move,$Moves)");

   $next_to_move = WHITE+BLACK-$to_move;
   $next_to_move_ID = ( $next_to_move == BLACK ? $Black_ID : $White_ID );


// Update clock

   if( $Maintime > 0 || $Byotime > 0)
   {
      // LastTicks may handle -(time spend) at the moment of the start of vacations
      // time since start of move in the reference of the ClockUsed by the game
      $hours = ticks_to_hours(get_clock_ticks($ClockUsed) - $LastTicks);

      if( $to_move == BLACK )
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

      if( ($next_to_move == BLACK ? $Blackonvacation : $Whiteonvacation) > 0 )
         $next_clockused = VACATION_CLOCK; //and LastTicks=0, see below
      else
      {
         $next_clockused = ( $next_to_move == BLACK ? $X_BlackClock : $X_WhiteClock );
         if( $WeekendClock != 'Y' )
            $next_clockused += WEEKEND_CLOCK_OFFSET;
      }

      $next_lastticks = get_clock_ticks($next_clockused);

      $timeout_date = NextGameOrder::make_timeout_date( $game_row, $next_to_move, $next_lastticks );

      $time_query .= "LastTicks=$next_lastticks, "
         . "ClockUsed=$next_clockused, "
         . "TimeOutDate=$timeout_date, ";
   }
   else
   {
      $hours = 0;
      $time_query = '';
   }

   $no_marked_dead = ( $Status == GAME_STATUS_PLAY || $Status == GAME_STATUS_PASS || $action == 'domove' );

   $TheBoard = new Board( );
   if( !$TheBoard->load_from_db( $game_row, 0, $no_marked_dead) )
      error('internal_error', "confirm.board.load_from_db($gid)");

   $mp_query = '';
   if( $is_mpgame && ($action == 'domove' || $action == 'pass' || $action == 'handicap') )
   {
      list( $group_color, $group_order )
         = MultiPlayerGame::calc_game_player_for_move( $GamePlayers, $Moves, $Handicap, 2 );
      $mp_gp = GamePlayer::load_game_player( $gid, $group_color, $group_order );
      $mp_uid = $mp_gp->uid;
      $mp_query = (( $ToMove_ID == $Black_ID ) ? 'Black_ID' : 'White_ID' ) . "=$mp_uid, ";
   }

   $message_raw = trim(get_request_arg('message'));
   if( preg_match( "/^<c>\s*<\\/c>$/si", $message_raw ) ) // remove empty comment-only tags
      $message_raw = '';
   $message = mysql_addslashes($message_raw);

   if( $message && preg_match( "#</?h(idden)?>#is", $message) )
      $GameFlags |= GAMEFLAGS_HIDDEN_MSG;

   $game_finished = false;




/* **********************
*** HOT_SECTION ***
>>> See also confirm.php, quick_play.php and clock_tick.php
Various dirty things (like duplicated moves) could appear
in case of multiple calls with the same move number. This could
happen in case of multi-players account with simultaneous logins
or if one player hit twice the validation button during a net lag
and/or if the opponent had already played between the two calls.

Because the LOCK query is not implemented with MySQL < 4.0,
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


   switch( (string)$action )
   {
      case 'domove': //stonestring is the list of prisoners
      {
         if( !$is_running_game ) //after resume
            error('invalid_action', "confirm.domove.check_status($gid,$Status)");

         $coord = @$_REQUEST['coord'];
         $stonestring = @$_REQUEST['stonestring'];

         {//to fix the old way Ko detect. Could be removed when no more old way games.
            if( !@$Last_Move ) $Last_Move= number2sgf_coords($Last_X, $Last_Y, $Size);
         }
         check_move( $TheBoard, $coord, $to_move);
//ajusted globals by check_move(): $Black_Prisoners, $White_Prisoners, $prisoners, $nr_prisoners, $colnr, $rownr;
//here, $prisoners list the captured stones of play (or suicided stones if, a day, $suicide_allowed==true)

         $move_query = "INSERT INTO Moves (gid, MoveNr, Stone, PosX, PosY, Hours) VALUES ";

         $prisoner_string = '';
         foreach($prisoners as $tmp)
         {
            list($x,$y) = $tmp;
            $move_query .= "($gid, $Moves, ".NONE.", $x, $y, 0), ";
            $prisoner_string .= number2sgf_coords($x, $y, $Size);
         }


         if( strlen($prisoner_string) != $nr_prisoners*2 || ( $stonestring && $prisoner_string != $stonestring) )
            error('move_problem', "confirm.domove.prisoner($gid)");

         $move_query .= "($gid, $Moves, $to_move, $colnr, $rownr, $hours) ";

         if( $message )
            $message_query = "INSERT INTO MoveMessages SET gid=$gid, MoveNr=$Moves, Text=\"$message\"";

         $game_query = "UPDATE Games SET Moves=$Moves, " . //See *** HOT_SECTION ***
             "Last_X=$colnr, " . //used with mail notifications
             "Last_Y=$rownr, " .
             "Last_Move='" . number2sgf_coords($colnr, $rownr, $Size) . "', " . //used to detect Ko
             "Status='PLAY', ";

         if( $nr_prisoners > 0 )
         {
            if( $to_move == BLACK )
               $game_query .= "Black_Prisoners=$Black_Prisoners, ";
            else
               $game_query .= "White_Prisoners=$White_Prisoners, ";
         }

         if( $nr_prisoners == 1 )
            $GameFlags |= GAMEFLAGS_KO;
         else
            $GameFlags &= ~GAMEFLAGS_KO;

         $game_query .= "ToMove_ID=$next_to_move_ID, " .
             "Flags=$GameFlags, " .
             $mp_query . $time_query . "Lastchanged=FROM_UNIXTIME($NOW)" ;
         break;
      }//switch for 'domove'

      case 'pass':
      {
         if( $Moves < $Handicap )
            error('early_pass', "confirm.pass($gid,$Moves,$Handicap)");

         if( $Status == GAME_STATUS_PLAY )
            $next_status = GAME_STATUS_PASS;
         else if( $Status == GAME_STATUS_PASS )
            $next_status = GAME_STATUS_SCORE;
         else
            error('invalid_action', "confirm.pass.check_status($gid,$Status)");


         $move_query = "INSERT INTO Moves SET " .
             "gid=$gid, " .
             "MoveNr=$Moves, " .
             "Stone=$to_move, " .
             "PosX=".POSX_PASS.", PosY=0, " .
             "Hours=$hours";

         if( $message )
            $message_query = "INSERT INTO MoveMessages SET gid=$gid, MoveNr=$Moves, Text=\"$message\"";

         $game_query = "UPDATE Games SET Moves=$Moves, " . //See *** HOT_SECTION ***
             "Last_X=".POSX_PASS.", " .
             "Status='$next_status', " .
             "ToMove_ID=$next_to_move_ID, " .
             "Last_Move='$Last_Move', " . //Not a move, re-use last one
             "Flags=$GameFlags, " . //Don't reset KO-Flag else PASS,PASS,RESUME could break a Ko
             $mp_query . $time_query . "Lastchanged=FROM_UNIXTIME($NOW)" ;
         break;
      }//switch for 'pass'

      case 'handicap': //stonestring is the list of handicap stones
      {
         if( $Status != GAME_STATUS_PLAY || !( $Handicap>1 && $Moves==1 ) )
            error('invalid_action', "confirm.handicap.check_status($gid,$Status,$Handicap,$Moves)");

         $stonestring = (string)@$_REQUEST['stonestring'];
         check_handicap( $TheBoard); //adjust $stonestring

         if( strlen( $stonestring ) != 2 * $Handicap )
            error('wrong_number_of_handicap_stone', "confirm.check.handicap($gid,$Handicap,$stonestring)");

         $move_query = "INSERT INTO Moves ( gid, MoveNr, Stone, PosX, PosY, Hours ) VALUES ";

         for( $i=1; $i <= $Handicap; $i++ )
         {
            list($colnr,$rownr) = sgf2number_coords(substr($stonestring, $i*2-2, 2), $Size);

            if( !isset($rownr) || !isset($colnr) )
               error('illegal_position', "confirm.check_pos($gid,#$i,$Handicap)");

            $move_query .= "($gid, $i, " . BLACK . ", $colnr, $rownr, " .
               ($i == $Handicap ? "$hours)" : "0), " );
         }

         if( $message )
            $message_query = "INSERT INTO MoveMessages SET gid=$gid, MoveNr=$Handicap, Text=\"$message\"";


         $game_query = "UPDATE Games SET Moves=$Handicap, " . //See *** HOT_SECTION ***
             "Last_X=$colnr, " .
             "Last_Y=$rownr, " .
             "Last_Move='" . number2sgf_coords($colnr, $rownr, $Size) . "', " .
             "ToMove_ID=$White_ID, " .
             "Flags=$GameFlags, " .
             $mp_query . $time_query . "Lastchanged=FROM_UNIXTIME($NOW)" ;
         break;
      }//switch for 'handicap'

      case 'resign':
      {
         $move_query = "INSERT INTO Moves SET " .
             "gid=$gid, " .
             "MoveNr=$Moves, " .
             "Stone=$to_move, " .
             "PosX=".POSX_RESIGN.", PosY=0, " .
             "Hours=$hours";

         if( $message )
            $message_query = "INSERT INTO MoveMessages SET gid=$gid, MoveNr=$Moves, Text=\"$message\"";

         if( $to_move == BLACK )
            $score = SCORE_RESIGN;
         else
            $score = -SCORE_RESIGN;

         $game_query = "UPDATE Games SET Moves=$Moves, " . //See *** HOT_SECTION ***
             "Last_X=".POSX_RESIGN.", " .
             "Status='FINISHED', " .
             "ToMove_ID=0, " .
             "Score=$score, " .
             "Flags=$GameFlags, " .
             $time_query . "Lastchanged=FROM_UNIXTIME($NOW)" ;

         $game_finished = true;
         break;
      }//switch for 'resign'

      case 'delete':
      {
         if( !$may_del_game )
            error('invalid_action', "confirm.delete($gid,$my_id,$Status)");

         $game_finished = true;
         break;
      }//switch for 'delete'

      case 'done': //stonestring is the list of toggled points
      {
         if( $Status != GAME_STATUS_SCORE && $Status != GAME_STATUS_SCORE2 )
            error('invalid_action', "confirm.done.check_status($gid,$Status)");

         $stonestring = (string)@$_REQUEST['stonestring'];
         $game_score = check_remove( $TheBoard, getRulesetScoring($Ruleset) ); //adjusted globals: $stonestring
         $score = $game_score->calculate_score();

         $l = strlen( $stonestring );

         $next_status = GAME_STATUS_SCORE2;
         if( $Status == GAME_STATUS_SCORE2 &&  $l < 2 )
         {
            $next_status = GAME_STATUS_FINISHED;
            $game_finished = true;
         }

         $move_query = "INSERT INTO Moves ( gid, MoveNr, Stone, PosX, PosY, Hours ) VALUES ";

         for( $i=0; $i < $l; $i += 2 )
         {
            list($x,$y) = sgf2number_coords(substr($stonestring, $i, 2), $Size);
            $move_query .= "($gid, $Moves, " . ($to_move == BLACK ? MARKED_BY_BLACK : MARKED_BY_WHITE ) . ", $x, $y, 0), ";
         }

         $move_query .= "($gid, $Moves, $to_move, ".POSX_SCORE.", 0, $hours) ";

         if( $message )
            $message_query = "INSERT INTO MoveMessages SET gid=$gid, MoveNr=$Moves, Text=\"$message\"";

         $game_query = "UPDATE Games SET Moves=$Moves, " . //See *** HOT_SECTION ***
             "Last_X=".POSX_SCORE.", " .
             "Status='$next_status', ";

         if( $next_status != GAME_STATUS_FINISHED )
            $game_query .= "ToMove_ID=$next_to_move_ID, ";
         else
            $game_query .= "ToMove_ID=0, ";

         $game_query .=
             "Score=$score, " .
             "Last_Move='$Last_Move', " . //Not a move, re-use last one
             "Flags=$GameFlags, " . //Don't reset KO-Flag else SCORE,RESUME could break a Ko
             $time_query . "Lastchanged=FROM_UNIXTIME($NOW)" ;
         break;
      }//switch for 'done'

      default:
         error('invalid_action', "confirm.noaction($gid,$Status)");
         break;
   }//switch $action


   ta_begin();
   {//HOT-section to update game-action

      //See *** HOT_SECTION *** above
      if( $game_query )
      {
         $result = db_query( "confirm.update_game($gid,$action)", $game_query . $game_clause );
         if( mysql_affected_rows() != 1 )
            error('mysql_update_game', "confirm.update_game2($gid,$action)");
      }

      if( $move_query )
      {
         $result = db_query( "confirm.update_moves($gid,$action)", $move_query );
         if( mysql_affected_rows() < 1 && $action != 'delete' )
            error('mysql_insert_move', "confirm.update_moves2($gid,$action)");
      }

      if( $message_query )
      {
         $result = db_query( "confirm.message_query($gid,$action)", $message_query );
         if( mysql_affected_rows() < 1 && $action != 'delete' )
            error('mysql_insert_move', "confirm.message_query2($gid,$action)");
      }


      if( $game_finished )
      {
         // signal game-end for tournament
         if( $tid > 0 )
            TournamentGames::update_tournament_game_end( "confirm.tourney_game_end.$action",
               $tid, $gid, $Black_ID, $score );

         // send message to my opponent / all-players / observers about the result
         $game_notify = new GameNotify( $gid, $my_id, $GameType, $GameFlags, $Black_ID, $White_ID,
            $score, $message_raw );

         if( $action == 'delete' )
         {
            GameHelper::delete_running_game( $gid );
            list( $Subject, $Text ) = $game_notify->get_text_game_deleted();
            $stay_on_board = false; // no game to stay on
         }
         else
         {
            $rated_status = update_rating2($gid);
            GameHelper::update_players_end_game( "confirm.game_finished",
               $gid, $GameType, $rated_status, $score, $Black_ID, $White_ID );

            list( $Subject, $Text, $observerText ) = $game_notify->get_text_game_result();

            // GamesPriority-entries are kept for running games only, delete for finished games too
            NextGameOrder::delete_game_priorities( $gid );

            delete_all_observers($gid, ($rated_status != RATEDSTATUS_DELETABLE), $observerText);
         }

         // Send a message to the opponent
         send_message( 'confirm', $Text, $Subject
            , /*to*/$game_notify->get_recipients(), ''
            , /*notify*/false //the move itself is always notified, see below
            , /*system-msg*/0
            , 'RESULT', $gid );
      }//game-finished


      // Notify opponent about move
      notify( "confirm.notify_opponent($gid,$next_to_move_ID)", $next_to_move_ID );

      // Increase moves and activity
      db_query( 'confirm.activity',
            "UPDATE Players SET Moves=Moves+1" // NOTE: TODO count delete/set_handicap as (one) move?
            .",Activity=LEAST($ActivityMax,$ActivityForMove+Activity)"
            .",LastMove=FROM_UNIXTIME($NOW)"
            ." WHERE ID=$my_id LIMIT 1" );
   }
   ta_end();



   // Jump somewhere

   if( @$_REQUEST['nextstatus'] )
      jump_to("status.php");
   elseif( @$_REQUEST['nextgame'] && !$stay_on_board )
      jump_to_next_game( $my_id, $Lastchanged, $Moves, $TimeOutDate, $gid);

   jump_to("game.php?gid=$gid");
}

function do_add_time( $game_row, $my_id)
{
   $gid = $game_row['ID'];
   $add_days  = (int) @$_REQUEST['add_days'];
   $reset_byo = (bool) @$_REQUEST['reset_byoyomi'];

   ta_begin();
   {//HOT-section to add time
      $add_hours = GameAddTime::add_time_opponent( $game_row, $my_id,
            time_convert_to_hours( $add_days, 'days'), $reset_byo );
      if( !is_numeric($add_hours) )
         error('confirm_add_time', "confirm.do_add_time($gid,$my_id,$add_days,$reset_byo,$add_hours)");
   }
   ta_end();

   jump_to("game.php?gid=$gid"
      . ($add_hours != 0 ? URI_AMP."sysmsg=" . urlencode(T_('Time added!')) : '')
      . '#boardInfos');
}

?>
