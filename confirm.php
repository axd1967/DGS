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
require_once( "include/game_functions.php" );
require_once( "include/board.php" );
require_once( "include/move.php" );
require_once( "include/rating.php" );



function jump_to_next_game($uid, $Lastchanged, $gid)
{
   $row = mysql_single_fetch( 'confirm.jump_to_next_game',
            "SELECT ID FROM Games " .
            "WHERE ToMove_ID=$uid "  .
            "AND Status" . IS_RUNNING_GAME .
            " AND ( Lastchanged > '$Lastchanged' OR ( Lastchanged = '$Lastchanged' AND ID>$gid )) " .
            //keep this order like the one in the status page
            "ORDER BY Lastchanged,ID " .
            "LIMIT 1" );

   if( !$row )
      jump_to("status.php");

   jump_to("game.php?gid=" . $row['ID']);
}



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
                 "black.ClockUsed AS Blackclock, " .
                 "white.ClockUsed AS Whiteclock, " .
                 "black.OnVacation AS Blackonvacation, " .
                 "white.OnVacation AS Whiteonvacation " .
                 "FROM (Games, Players AS black, Players AS white) " .
                 "WHERE Games.ID=$gid AND black.ID=Black_ID AND white.ID=White_ID" )
      or error('unknown_game', "confirm.find_game($gid)");

   extract($game_row);

   if( @$_REQUEST['nextskip'] )
   {
      jump_to_next_game( $my_id, $Lastchanged, $gid);
   }

   if( @$_REQUEST['nextaddtime'] )
   {
      do_add_time( $game_row, $my_id); // jump back
   }

   if( $Status == 'INVITED' )
      error('game_not_started');
   else if( $Status == 'FINISHED' )
      error('game_finished');

   if( $Black_ID == $ToMove_ID )
      $to_move = BLACK;
   else if( $White_ID == $ToMove_ID )
      $to_move = WHITE;
/*
   else if( !$ToMove_ID ) //=0 if INVITED or FINISHED
      error('not_your_turn', "confirm.bad_ToMove_ID($gid)");
*/
   else
      error('database_corrupted', "confirm.bad_ToMove_ID($gid)");

   $action = @$_REQUEST['action'];
   $stay_on_board = @$_REQUEST['stay'];
   $my_game = ( $logged_in && ( $my_id == $Black_ID || $my_id == $White_ID ) );

   $is_running_game = ($Status == 'PLAY' || $Status == 'PASS' || $Status == 'SCORE' || $Status == 'SCORE2' );

   $too_few_moves = ( $Moves < DELETE_LIMIT+$Handicap );
   $may_del_game  = $my_game && $too_few_moves && $is_running_game;

   $may_resign_game = $my_game && $is_running_game;
   if( $action == 'resign' )
   {
      if( !$may_resign_game )
         error('invalid_action', "confirm.resign($gid,$Status,$my_id)");

      if( $my_id != $ToMove_ID )
         $to_move = WHITE+BLACK-$to_move;
   }

   if( $my_id != $ToMove_ID && !$may_del_game && !$may_resign_game )
      error('not_your_turn');


   //See *** HOT_SECTION *** below
   if( !isset($_REQUEST['move']) )
      //error('internal_error','confirm10');
      error('move_problem','confirm10');
   $qry_move = @$_REQUEST['move'];
   if( $qry_move != $Moves )
      error('already_played','confirm11');

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
      {
         $next_clockused = VACATION_CLOCK; //and LastTicks=0, see below
      }
      else
      {
         $next_clockused = ( $next_to_move == BLACK ? $Blackclock : $Whiteclock );
         if( $WeekendClock != 'Y' )
            $next_clockused += WEEKEND_CLOCK_OFFSET;
      }

      $time_query .= "LastTicks=" . get_clock_ticks($next_clockused)
         . ", ClockUsed=$next_clockused, ";
   }
   else
   {
      $hours = 0;
      $time_query = '';
   }

   $no_marked_dead = ( $Status == 'PLAY' || $Status == 'PASS' || $action == 'domove' );

   $TheBoard = new Board( );
   if( !$TheBoard->load_from_db( $game_row, 0, $no_marked_dead) )
      error('internal_error', "confirm load_from_db $gid");

   $message_raw = trim(get_request_arg('message'));
   $message = mysql_addslashes($message_raw);
   $message_query = '';

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
   $game_clause = " WHERE ID=$gid AND Status!='FINISHED' AND Moves=$Moves LIMIT 1";
   $doublegame_query = '';
   $Moves++;



   switch( (string)$action )
   {
      case 'domove': //stonestring is the list of prisoners
      {
         if( !$is_running_game ) //after resume
            error('invalid_action',"confirm.domove.$Status");

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


         if( strlen($prisoner_string) != $nr_prisoners*2 ||
             ( $stonestring && $prisoner_string != $stonestring) )
            error('move_problem','confirm.domove.prisoner');

         $move_query .= "($gid, $Moves, $to_move, $colnr, $rownr, $hours) ";

         if( $message )
            $message_query = "INSERT INTO MoveMessages SET gid=$gid, MoveNr=$Moves, Text=\"$message\"";

         $game_query = "UPDATE Games SET Moves=$Moves, " . //See *** HOT_SECTION ***
             "Last_X=$colnr, " . //used with mail notifications
             "Last_Y=$rownr, " .
             "Last_Move='" . number2sgf_coords($colnr, $rownr, $Size) . "', " . //used to detect Ko
             "Status='PLAY', ";

         if( $nr_prisoners > 0 )
            if( $to_move == BLACK )
               $game_query .= "Black_Prisoners=$Black_Prisoners, ";
            else
               $game_query .= "White_Prisoners=$White_Prisoners, ";

         if( $nr_prisoners == 1 )
            $GameFlags |= KO;
         else
            $GameFlags &= ~KO;

         $game_query .= "ToMove_ID=$next_to_move_ID, " .
             "Flags=$GameFlags, " .
             $time_query . "Lastchanged=FROM_UNIXTIME($NOW)" ;
      }
      break;

      case 'pass':
      {
         if( $Moves < $Handicap )
            error('early_pass');


         if( $Status == 'PLAY' )
            $next_status = 'PASS';
         else if( $Status == 'PASS' )
            $next_status = 'SCORE';
         else
            error('invalid_action',"confirm.pass.$Status");


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
             "Flags=$GameFlags, " . //Don't reset Flags else PASS,PASS,RESUME could break a Ko
             $time_query . "Lastchanged=FROM_UNIXTIME($NOW)" ;
      }
      break;

      case 'handicap': //stonestring is the list of handicap stones
      {
         if( $Status != 'PLAY' || !( $Handicap>1 && $Moves==1 ) )
            error('invalid_action',"confirm.handicap.$Status");

         $stonestring = (string)@$_REQUEST['stonestring'];
         check_handicap( $TheBoard); //adjust $stonestring

         if( strlen( $stonestring ) != 2 * $Handicap )
            error('wrong_number_of_handicap_stone');


         $move_query = "INSERT INTO Moves ( gid, MoveNr, Stone, PosX, PosY, Hours ) VALUES ";

         for( $i=1; $i <= $Handicap; $i++ )
         {
            list($colnr,$rownr) = sgf2number_coords(substr($stonestring, $i*2-2, 2), $Size);

            if( !isset($rownr) || !isset($colnr) )
               error('illegal_position');

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
             $time_query . "Lastchanged=FROM_UNIXTIME($NOW)" ;
      }
      break;

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
             //"Flags=0, " . //Not useful
             $time_query . "Lastchanged=FROM_UNIXTIME($NOW)" ;

         $game_finished = true;
      }
      break;

      case 'delete':
      {
         if( !$may_del_game )
            error('invalid_action',"confirm.delete($Status,$my_id)");

/*
  Here, the previous line was:
         $move_query = "DELETE FROM Moves WHERE gid=$gid LIMIT $Moves";
  But, the number of records of Moves could be greater than the number of moves if:
  - there are prisoners
  - a sequence like PASS/PASS/SCORE.../RESUME had already occured.
  - time has been added to opponent
  So some garbage records could remains alone because of the LIMIT.
*/
         $move_query = "DELETE FROM Moves WHERE gid=$gid";
         $message_query = "DELETE FROM MoveMessages WHERE gid=$gid LIMIT $Moves";
         $game_query = "DELETE FROM Games" ; //See *** HOT_SECTION ***

         // mark reference in other double-game to indicate referring game has vanished
         $dbl_gid = @$game_row['DoubleGame_ID'];
         if( $dbl_gid > 0 )
            $doublegame_query = "UPDATE Games SET DoubleGame_ID=-ABS(DoubleGame_ID) WHERE ID=$dbl_gid LIMIT 1";

         $game_finished = true;
      }
      break;

      case 'done': //stonestring is the list of toggled points
      {
         if( $Status != 'SCORE' && $Status != 'SCORE2' )
            error('invalid_action',"confirm.done.$Status");

         $stonestring = (string)@$_REQUEST['stonestring'];
         $game_score = check_remove( $TheBoard, GSMODE_TERRITORY_SCORING); //ajusted globals: $stonestring
         $score = $game_score->calculate_score();

         $l = strlen( $stonestring );

         $next_status = 'SCORE2';
         if( $Status == 'SCORE2' &&  $l < 2 )
         {
            $next_status = 'FINISHED';
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

         if( $next_status != 'FINISHED' )
            $game_query .= "ToMove_ID=$next_to_move_ID, ";
         else
            $game_query .= "ToMove_ID=0, ";


         $game_query .=
             "Score=$score, " .
             "Last_Move='$Last_Move', " . //Not a move, re-use last one
             "Flags=$GameFlags, " . //Don't reset Flags else SCORE,RESUME could break a Ko
             $time_query . "Lastchanged=FROM_UNIXTIME($NOW)" ;

      }
      break;

      default:
      {
         error('invalid_action',"confirm.noaction.$Status");
      }
   }


   //See *** HOT_SECTION *** above
   $result = db_query( "confirm.update_game($gid)", $game_query . $game_clause );
   if( mysql_affected_rows() != 1 )
      error('mysql_update_game',"confirm20($action,$gid)");

   $result = db_query( "confirm.update_moves($gid)", $move_query );
   if( mysql_affected_rows() < 1 && $action != 'delete' )
      error('mysql_insert_move',"confirm21($action,$gid)");



   if( $message_query )
   {
      $result = db_query( "confirm.message_query", $message_query );
      if( mysql_affected_rows() < 1 && $action != 'delete' )
         error('mysql_insert_move',"confirm22($action,$gid)");
   }


   if( $game_finished )
   {
      // send message to my opponent about the result

      $opponent_row = mysql_single_fetch( 'confirm.find_opponent',
                        "SELECT * FROM Players WHERE ID=" .
                           ($White_ID + $Black_ID - $my_id) )
         or error('opponent_not_found');

      if( $my_id == $Black_ID )
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


      if( $action == 'delete' )
      {
         //TODO: HOT_SECTION ???
         db_query( "confirm.update_players_delete($gid)",
            "UPDATE Players SET Running=Running-1 WHERE ID IN ($Black_ID,$White_ID) LIMIT 2" );

         db_query( "confirm.delete.gamenote($gid)",
            "DELETE FROM GamesNotes WHERE gid=$gid LIMIT 2" );

         // mark reference in other double-game to indicate referring game has vanished
         db_query("confirm.delete.doublegame.update($gid)", $doublegame_query );

         $Subject = 'Game deleted';
         //reference: game is deleted => no link
         $Text = "The game:<center>"
               . game_reference( 0, 1, '', $gid, 0, $whitename, $blackname)
               . "</center>has been deleted by your opponent.<br>";

         delete_all_observers($gid, false);
         $stay_on_board = false; // no game to stay on
      }
      else
      {
         //TODO: HOT_SECTION ???
//         update_rating($gid);
         $rated_status = update_rating2($gid); //0=rated game

         $query = "UPDATE Players SET Running=Running-1, Finished=Finished+1" .
                   ($rated_status ? '' : ", RatedGames=RatedGames+1" .
                    ($score > 0 ? ", Won=Won+1" : ($score < 0 ? ", Lost=Lost+1 " : ""))
                   ) . " WHERE ID=$White_ID LIMIT 1" ;
         db_query( "confirm.update_players_finished.W($gid,$White_ID)", $query );

         $query = "UPDATE Players SET Running=Running-1, Finished=Finished+1" .
                   ($rated_status ? '' : ", RatedGames=RatedGames+1" .
                    ($score < 0 ? ", Won=Won+1" : ($score > 0 ? ", Lost=Lost+1 " : ""))
                   ) . " WHERE ID=$Black_ID LIMIT 1" ;
         db_query( "confirm.update_players_finished.B($gid,$Black_ID)", $query );

         $Subject = 'Game result';
         $Text = "The result in the game:<center>"
               . game_reference( REF_LINK, 1, '', $gid, 0, $whitename, $blackname)
               . "</center>was:<center>"
               . score2text($score,true,true)
               . "</center>";

         $tmp = $Text . "Send a message to:<center>"
               . send_reference( REF_LINK, 1, '', $White_ID, $whitename, $whitehandle)
               . "<br>"
               . send_reference( REF_LINK, 1, '', $Black_ID, $blackname, $blackhandle)
               . "</center>" ;
         delete_all_observers($gid, $rated_status!=1, $tmp);
      }

      //Send a message to the opponent

      $message_from_server_way = true; //else simulate a message from this player
      //nervertheless, the clock_tick.php messages are always sent by the server
      //so it's better to keep $message_from_server_way = true
      if( $message_from_server_way )
      {
         //The server messages does not allow a reply,
         // so add a *in message* reference to this player.
         $Text.= "Send a message to:<center>"
               . send_reference( REF_LINK, 1, '', $my_id, $player_row['Name'], $player_row['Handle'])
               . "</center>" ;
      }

      if( $message_raw )
      {
         if( $message_from_server_way )
         {
            //A server message will only be read by this player
            $Text .= "Your opponent wrote:<p></p>" . $message_raw;
         }
         else
         {
            //Because both players will read this message
            $Text .= "The final message was:<p></p>" . $message_raw;
         }
      }

      send_message( 'confirm', $Text, $Subject
         ,$opponent_row['ID'], ''
         , /*notify*/false //the move itself is always notified, see below
         ,( $message_from_server_way ? 0 : $my_id )
         , 'RESULT', $gid);
   }


   // Notify opponent about move

   //if( $next_to_move_ID != $my_id ) //always true
if(1){ //new
      notify( 'confirm', $next_to_move_ID);
}else{ //old
      db_query( 'confirm.notify_opponent',
         "UPDATE Players SET Notify='NEXT' " .
                   "WHERE ID='$next_to_move_ID' AND Notify='NONE' " .
                   "AND FIND_IN_SET('ON',SendEmail) LIMIT 1" );
                   //"AND SendEmail LIKE '%ON%' LIMIT 1")
} //old/new



   // Increase moves and activity

   db_query( 'confirm.activity',
         "UPDATE Players SET Moves=Moves+1"
         .",Activity=LEAST($ActivityMax,$ActivityForMove+Activity)"
         .",LastMove=FROM_UNIXTIME($NOW)"
         ." WHERE ID=$my_id LIMIT 1" );



   // Jump somewhere

   if( @$_REQUEST['nextstatus'] )
   {
      jump_to("status.php");
   }
   else if( @$_REQUEST['nextgame'] && !$stay_on_board )
   {
      jump_to_next_game( $my_id, $Lastchanged, $gid);
   }

   jump_to("game.php?gid=$gid");
}

function do_add_time( $game_row, $my_id)
{
   $gid = $game_row['ID'];
   $add_days  = (int) @$_REQUEST['add_days'];
   $reset_byo = (bool) @$_REQUEST['reset_byoyomi'];

   $add_hours = add_time_opponent( $game_row, $my_id,
                  time_convert_to_hours( $add_days, 'days'), $reset_byo );
   if( !is_numeric($add_hours) )
      error('confirm_add_time',
         "do_add_time($gid,$my_id,$add_days,$reset_byo): $add_hours");

   jump_to("game.php?gid=$gid" . ($add_hours > 0
                  ? URI_AMP."sysmsg=" . urlencode(T_('Time added!')) : '')
         . '#boardInfos');
}

?>
