<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

require_once( "include/std_functions.php" );
require_once( "include/board.php" );
require_once( "include/move.php" );
require_once( "include/rating.php" );

define('HOT_SECTION', true);

disable_cache();

function jump_to_next_game($uid, $Lastchanged, $gid)
{
   $result = mysql_query("SELECT ID FROM Games " .
                         "WHERE ToMove_ID=$uid "  .
                         "AND Status!='INVITED' AND Status!='FINISHED' " .
                         "AND ( UNIX_TIMESTAMP(Lastchanged) > UNIX_TIMESTAMP('$Lastchanged') " .
                         "OR ( UNIX_TIMESTAMP(Lastchanged) = UNIX_TIMESTAMP('$Lastchanged') " .
                         "AND ID>$gid )) " .
                         "ORDER BY Lastchanged,ID " .
                         "LIMIT 1");

   if( @mysql_num_rows($result) != 1 )
      jump_to("status.php");

   $row = mysql_fetch_assoc($result);

   jump_to("game.php?gid=" . $row["ID"]);
}



{
   if( !@$_REQUEST['gid'] )
      error("no_game_nr");
   $gid = $_REQUEST['gid'] ;

   if( @$_REQUEST['nextback'] )
      jump_to("game.php?gid=$gid");

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");


   $result = mysql_query( "SELECT Games.*, " .
                          "Games.Flags+0 AS GameFlags, " . //used by check_move
                          "black.ClockUsed AS Blackclock, " .
                          "white.ClockUsed AS Whiteclock, " .
                          "black.OnVacation AS Blackonvacation, " .
                          "white.OnVacation AS Whiteonvacation " .
                          "FROM Games, Players AS black, Players AS white " .
                          "WHERE Games.ID=$gid AND Black_ID=black.ID AND White_ID=white.ID"
                        )
            or error('mysql_query_failed');

   if( @mysql_num_rows($result) != 1 )
      error("unknown_game");

   $game_row = mysql_fetch_assoc($result);
   extract($game_row);

   if( @$_REQUEST['skip'] )
   {
      jump_to_next_game($player_row["ID"], $Lastchanged, $gid);
   }

   if( $Status == 'INVITED' )
   {
      error("game_not_started");
   }
   else if( $Status == 'FINISHED' )
   {
      error("game_finished");
   }

   if( $player_row["ID"] != $ToMove_ID )
      error("not_your_turn");

   $qry_move = @$_REQUEST['move'];
   //could append in case of multi-players account with simultaneous logins
   //or if one player hit twice the validation button during a net lag
   //and if opponent has already played between the two confirm.php calls.
   if( $qry_move > 0 && $qry_move != $Moves )
      error("already_played",'conf7');


   if( $Black_ID == $ToMove_ID )
      $to_move = BLACK;
   else if( $White_ID == $ToMove_ID )
      $to_move = WHITE;
   else
      error("database_corrupted");


   $action = @$_REQUEST['action'];

   $next_to_move = WHITE+BLACK-$to_move;

   if( $Moves+1 < $Handicap ) $next_to_move = BLACK;

   $next_to_move_ID = ( $next_to_move == BLACK ? $Black_ID : $White_ID );


// Update clock

   if( $Maintime > 0 or $Byotime > 0)
   {

      $ticks = get_clock_ticks($ClockUsed) - $LastTicks;
      $hours = ( $ticks > $tick_frequency ? floor(($ticks-1) / $tick_frequency) : 0 );

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

      if( $next_to_move == BLACK and $Blackonvacation > 0 or
          $next_to_move == WHITE and $Whiteonvacation > 0 )
      {
         $next_clockused = -1;
         $next_ticks = 0;
      }
      else
      {
         $next_clockused = ( $next_to_move == BLACK ? $Blackclock : $Whiteclock );
         if( $WeekendClock != 'Y' )
            $next_clockused += 100;
         $next_ticks = get_clock_ticks($next_clockused);
      }

      $time_query .= "LastTicks=$next_ticks, " .
          "ClockUsed=$next_clockused, ";
   }
   else
   {
      $hours = 0;
      $time_query = '';
   }

   $no_marked_dead = ( $Status == 'PLAY' or $Status == 'PASS' or $action == 'move' );

   $TheBoard = new Board( );
   if( !$TheBoard->load_from_db( $game_row, 0, $no_marked_dead) )
      error('internal_error', "confirm load_from_db $gid");

   $message = addslashes(trim(get_request_arg('message')));
   $message_query = '';

   $game_finished = false;

   $where_clause = " ID=$gid AND Moves=$Moves";
   $too_few_moves = ($Moves < DELETE_LIMIT+$Handicap) ;
   $old_moves = $Moves;
   $Moves++;

   switch( $action )
   {
      case 'move':
      {
         $coord = @$_REQUEST['coord'];

{//to fixe old way Ko detect. Could be removed when no more old way games.
  if( !@$Last_Move ) $Last_Move= number2sgf_coords($Last_X, $Last_Y, $Size);
}
         check_move( $TheBoard, $coord, $to_move);
//ajusted globals by check_move(): $Black_Prisoners, $White_Prisoners, $prisoners, $nr_prisoners, $colnr, $rownr;
//here, $prisoners list the captured stones of play (or suicided stones if, a day, $suicide_allowed==true)

         $move_query = "INSERT INTO Moves (gid, MoveNr, Stone, PosX, PosY, Hours) VALUES ";

         $prisoner_string = '';
         reset($prisoners);
         while( list($dummy, list($x,$y)) = each($prisoners) )
         {
            $move_query .= "($gid, $Moves, ".NONE.", $x, $y, 0), ";
            $prisoner_string .= number2sgf_coords($x, $y, $Size);
         }

         $old_prisoner_string = @$_REQUEST['prisoner_string'];
         if( strlen($prisoner_string) != $nr_prisoners*2 or
             ( $old_prisoner_string and $prisoner_string != $old_prisoner_string) )
            error("move_problem");

         $move_query .= "($gid, $Moves, $to_move, $colnr, $rownr, $hours) ";

         if( $message )
            $message_query = "INSERT INTO MoveMessages SET gid=$gid, MoveNr=$Moves, Text=\"$message\"";

         $game_query = "UPDATE Games SET " .
             "Moves=$Moves, " .
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
             $time_query . "Lastchanged=FROM_UNIXTIME($NOW)" .
             " WHERE $where_clause LIMIT 1";
      }
      break;

      case 'pass':
      {
         if( $Moves < $Handicap )
            error("early_pass");


         if( $Status == 'PLAY' )
            $next_status = 'PASS';
         else if( $Status == 'PASS' )
            $next_status = 'SCORE';
         else
            error("invalid_action",'conf1');


         $move_query = "INSERT INTO Moves SET " .
             "gid=$gid, " .
             "MoveNr=$Moves, " .
             "Stone=$to_move, " .
             "PosX=".POSX_PASS.", PosY=0, " .
             "Hours=$hours";

         if( $message )
            $message_query = "INSERT INTO MoveMessages SET gid=$gid, MoveNr=$Moves, Text=\"$message\"";

         $game_query = "UPDATE Games SET " .
             "Moves=$Moves, " .
             "Last_X=".POSX_PASS.", " .
             "Status='$next_status', " .
             "ToMove_ID=$next_to_move_ID, " .
             "Last_Move='$Last_Move', " . //Not a move, re-use last one
             "Flags=$GameFlags, " . //Don't reset Flags else PASS,PASS,RESUME could break a Ko
             $time_query . "Lastchanged=FROM_UNIXTIME($NOW)" .
             " WHERE $where_clause LIMIT 1";
      }
      break;

      case 'handicap':
      {
         if( $Status != 'PLAY' or $Moves != 1 )
            error("invalid_action",'conf2');

         $stonestring = (string)@$_REQUEST['stonestring'];
         check_handicap( $TheBoard); //adjust $stonestring

         if( strlen( $stonestring ) != 2 * $Handicap )
            error("wrong_number_of_handicap_stone");


         $move_query = "INSERT INTO Moves ( gid, MoveNr, Stone, PosX, PosY, Hours ) VALUES ";


         for( $i=1; $i <= $Handicap; $i++ )
         {
            list($colnr,$rownr) = sgf2number_coords(substr($stonestring, $i*2-2, 2), $Size);

            if( !isset($rownr) or !isset($colnr) )
               error("illegal_position");

            $move_query .= "($gid, $i, " . BLACK . ", $colnr, $rownr, " .
               ($i == $Handicap ? "$hours)" : "0), " );
         }

         if( $message )
            $message_query = "INSERT INTO MoveMessages SET gid=$gid, MoveNr=$Handicap, Text=\"$message\"";


         $game_query = "UPDATE Games SET " .
             "Moves=$Handicap, " .
             "Last_X=$colnr, " .
             "Last_Y=$rownr, " .
             "Last_Move='" . number2sgf_coords($colnr, $rownr, $Size) . "', " .
             "ToMove_ID=$White_ID, " .
             $time_query . "Lastchanged=FROM_UNIXTIME($NOW)" .
             " WHERE $where_clause LIMIT 1";
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

         $game_query = "UPDATE Games SET " .
             "Moves=$Moves, " .
             "Last_X=".POSX_RESIGN.", " .
             "Status='FINISHED', " .
             "ToMove_ID=0, " .
             "Score=$score, " .
             //"Flags=0, " . //Not useful
             $time_query . "Lastchanged=FROM_UNIXTIME($NOW)" .
             " WHERE $where_clause LIMIT 1";

         $game_finished = true;
      }
      break;

      case 'delete':
      {
         if( $Status != 'PLAY' or !$too_few_moves )
            error("invalid_action",'conf3');

/*
  Here, the previous line was:
         $move_query = "DELETE FROM Moves WHERE gid=$gid LIMIT $Moves";
  But, the number of records of Moves could be greater than the number of moves if:
  - there are prisoners
  - a sequence like PASS/PASS/SCORE.../RESUME had already occured.
  So some garbage records could remains alone because of the LIMIT.
*/
         $move_query = "DELETE FROM Moves WHERE gid=$gid";
         $message_query = "DELETE FROM MoveMessages WHERE gid=$gid LIMIT $Moves";
         $game_query = "DELETE FROM Games WHERE ID=$gid LIMIT 1";

         $game_finished = true;
      }
      break;

      case 'done':
      {
         if( $Status != 'SCORE' and $Status != 'SCORE2' )
            error("invalid_action",'conf4');

         $stonestring = (string)@$_REQUEST['stonestring'];
         check_remove( $TheBoard);
//ajusted globals by check_remove(): $score, $stonestring;

         $l = strlen( $stonestring );

         $next_status = 'SCORE2';
         if( $Status == 'SCORE2' and  $l < 2 )
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


         $game_query = "UPDATE Games SET " .
             "Moves=$Moves, " .
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
             $time_query . "Lastchanged=FROM_UNIXTIME($NOW)" .
             " WHERE $where_clause LIMIT 1";

      }
      break;

      default:
      {
         error("invalid_action",'conf5');
      }
   }


if( HOT_SECTION )
{
   //*********************** HOT SECTION START ***************************
   //could append in case of multi-players account with simultaneous logins
   //or if one player hit twice the validation button during a net lag
   //and if opponent has already played between the two quick_play.php/confirm.php calls.

   $result = mysql_query( "LOCK TABLES Games WRITE, Moves WRITE"
      //. ", MoveMessages WRITE"
      );

   if ( !$result )
      error("internal_error","confirm LOCK");

   // Maybe not useful:
   function unlock_games_tables()
   {
      $result = mysql_query( "UNLOCK TABLES");
   }
   register_shutdown_function('unlock_games_tables');

   // Locked ... an ultimate verification:
   $result = mysql_query( "SELECT Moves FROM Games WHERE Games.ID=$gid" );

   if( @mysql_num_rows($result) != 1 )
      error("internal_error", "confirm verif $gid");

   $tmp = mysql_fetch_assoc($result);

   if( $tmp["Moves"] != $old_moves )
      error("already_played",'conf6');
}//HOT_SECTION

   $result = mysql_query( $move_query );

   if( mysql_affected_rows() < 1 and $action != 'delete' )
      error("mysql_insert_move",'conf7');

   $result = mysql_query( $game_query );

   if( mysql_affected_rows() != 1 )
      error("mysql_update_game");

if( HOT_SECTION )
{
   $result = mysql_query( "UNLOCK TABLES");
   if ( !$result )
      error("internal_error","confirm UNLOCK");
   //*********************** HOT SECTION END *****************************
}//HOT_SECTION


   if( $message_query )
   {
      $result = mysql_query( $message_query );

      if( mysql_affected_rows() < 1 and $action != 'delete' )
         error("mysql_insert_move",'conf8');
   }


   if( $game_finished )
   {
      // send message to my opponent about the result

      $result = mysql_query( "SELECT * FROM Players WHERE ID=" .
                             ( $player_row["ID"] == $Black_ID ? $White_ID : $Black_ID ) );

      if( @mysql_num_rows($result) != 1 )
         error("opponent_not_found");

      $opponent_row = mysql_fetch_array($result);

      if( $player_row["ID"] == $Black_ID )
      {
         $blackname = $player_row["Name"];
         $whitename = $opponent_row["Name"];
         $blackhandle = $player_row["Handle"];
         $whitehandle = $opponent_row["Handle"];
      }
      else
      {
         $whitename = $player_row["Name"];
         $blackname = $opponent_row["Name"];
         $whitehandle = $player_row["Handle"];
         $blackhandle = $opponent_row["Handle"];
      }


      if( $action == 'delete' )
      {
         mysql_query("UPDATE Players SET Running=Running-1 " .
                     "WHERE ID=$Black_ID OR ID=$White_ID LIMIT 2");

         $Subject = 'Game deleted';
         //reference: game is deleted => no link
         $Text = "The game:<center>"
               . game_reference( 0, 1, '', $gid, 0, $whitename, $blackname)
               . "</center>has been deleted by your opponent.<br>";

         delete_all_observers($gid, false);
      }
      else
      {
//         update_rating($gid);
         $rated_status = update_rating2($gid); //0=rated game

         mysql_query( "UPDATE Players SET Running=Running-1, Finished=Finished+1" .
                      ($rated_status ? '' : ", RatedGames=RatedGames+1" .
                       ($score > 0 ? ", Won=Won+1" : ($score < 0 ? ", Lost=Lost+1 " : ""))
                      ) . " WHERE ID=$White_ID LIMIT 1" );

         mysql_query( "UPDATE Players SET Running=Running-1, Finished=Finished+1" .
                      ($rated_status ? '' : ", RatedGames=RatedGames+1" .
                       ($score < 0 ? ", Won=Won+1" : ($score > 0 ? ", Lost=Lost+1 " : ""))
                      ) . " WHERE ID=$Black_ID LIMIT 1" );

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
         delete_all_observers($gid, $rated_status!=1, addslashes( $tmp));
      }

      $message_from_server_way = true; //else simulate a message from this player
      //nervertheless, the clock_tick.php messages are always sent by the server
      if( $message_from_server_way )
      {
         //The server messages does not allow a reply,
         // so add a *in message* reference to this player.
         $Text.= "Send a message to:<center>"
               . send_reference( REF_LINK, 1, '', $player_row["ID"], $player_row["Name"], $player_row["Handle"])
               . "</center>" ;
      }

         $Text = addslashes( $Text);
      if ( $message )
      {
         if( $message_from_server_way )
         {
            //A server message will only be read by this player
            $Text .= "Your opponent wrote:<p>" . $message;
         }
         else
         {
            //Because both players will read this message
            $Text .= "The final message was:<p>" . $message;
         }
      }

      mysql_query( "INSERT INTO Messages SET Time=FROM_UNIXTIME($NOW), " .
                   "Game_ID=$gid, Subject='$Subject', Text='$Text'");

      if( mysql_affected_rows() != 1)
         error("mysql_insert_message");

      $mid = mysql_insert_id();

      $query = "INSERT INTO MessageCorrespondents (uid,mid,Sender,Folder_nr) VALUES " .
                  "(" . $opponent_row['ID'] . ", $mid, 'N', ".FOLDER_NEW.")" ;

      if( !$message_from_server_way )
      {
         //This simulate a message sent by this player
         //This will allow a direct message reply but will fill his *sent* folder
         $query.= ",(" . $player_row["ID"] . ", $mid, 'Y', ".FOLDER_SENT.")" ;
         //else we could force a NULL Folder_nr (trashed message)
      }

      mysql_query( $query);

   }


// Notify opponent about move

   mysql_query( "UPDATE Players SET Notify='NEXT' " .
                "WHERE ID='$next_to_move_ID' AND SendEmail LIKE '%ON%' " .
                "AND Notify='NONE' AND ID!='" .$player_row["ID"] . "' LIMIT 1") ;



// Increase moves and activity

   mysql_query( "UPDATE Players " .
                "SET Activity=Activity + $ActivityForMove, " .
                "Moves=Moves+1, " .
                "LastMove=FROM_UNIXTIME($NOW) " .
                "WHERE ID=" . $player_row["ID"] . " LIMIT 1" );



// Jump somewhere

   if( @$_REQUEST['nextstatus'] )
   {
      jump_to("status.php");
   }
   else if( @$_REQUEST['nextgame'] )
   {
      jump_to_next_game($player_row["ID"], $Lastchanged, $gid);
   }

   jump_to("game.php?gid=$gid");
}
?>
