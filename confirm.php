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

disable_cache();

function jump_to_next_game($id, $Lastchanged, $gid)
{
   $result = mysql_query("SELECT ID FROM Games " .
                         "WHERE ToMove_ID=$id "  .
                         "AND Status!='INVITED' AND Status!='FINISHED' " .
                         "AND ( UNIX_TIMESTAMP(Lastchanged) > UNIX_TIMESTAMP('$Lastchanged') " .
                         "OR ( UNIX_TIMESTAMP(Lastchanged) = UNIX_TIMESTAMP('$Lastchanged') " .
                         "AND ID>$gid )) " .
                         "ORDER BY Lastchanged,ID " .
                         "LIMIT 1");

   if( mysql_num_rows($result) != 1 )
      jump_to("status.php");

   $row = mysql_fetch_array($result);

   jump_to("game.php?gid=" . $row["ID"]);
}



{
   if( $nextback )
      jump_to("game.php?gid=$gid");

   connect2mysql();

   if( !$gid )
      error("no_game_nr");

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");


   $result = mysql_query( "SELECT Games.*, " .
                          "Games.Flags+0 AS flags, " .
                          "black.ClockUsed AS Blackclock, " .
                          "white.ClockUsed AS Whiteclock, " .
                          "black.OnVacation AS Blackonvacation, " .
                          "white.OnVacation AS Whiteonvacation " .
                          "FROM Games, Players AS black, Players AS white " .
                          "WHERE Games.ID=$gid AND Black_ID=black.ID AND White_ID=white.ID" );

   if(  mysql_num_rows($result) != 1 )
      error("unknown_game");

   extract(mysql_fetch_array($result));

   if( $skip )
   {
      jump_to_next_game($player_row["ID"], $Lastchanged, $gid);
   }

   $old_moves = $Moves;

   if( $player_row["ID"] != $ToMove_ID )
      error("not_your_turn");

   if( $Status == 'INVITED' )
   {
      error("game_not_started");
   }
   else if( $Status == 'FINISHED' )
   {
      error("game_finished");
   }


   if( $Black_ID == $ToMove_ID )
      $to_move = BLACK;
   else if( $White_ID == $ToMove_ID )
      $to_move = WHITE;
   else
      error("database_corrupted");


   $next_to_move = WHITE+BLACK-$to_move;

   if( $Moves+1 < $Handicap ) $next_to_move = BLACK;

   $next_to_move_ID = ( $next_to_move == BLACK ? $Black_ID : $White_ID );


// Update clock

   $hours = 0;

   if( $Maintime > 0 or $Byotime > 0)
   {

      $ticks = get_clock_ticks($ClockUsed) - $LastTicks;
      $hours = ( $ticks > 0 ? (int)(($ticks-1) / $tick_frequency) : 0 );

      if( $to_move == BLACK )
      {
         time_remaining($hours, $Black_Maintime, $Black_Byotime, $Black_Byoperiods, $Maintime,
         $Byotype, $Byotime, $Byoperiods, true);
         $time_query = "Black_Maintime=$Black_Maintime, " .
             "Black_Byotime=$Black_Byotime, " .
             "Black_Byoperiods=$Black_Byoperiods, ";
      }
      else
      {
         time_remaining($hours, $White_Maintime, $White_Byotime, $White_Byoperiods, $Maintime,
         $Byotype, $Byotime, $Byoperiods, true);
         $time_query = "White_Maintime=$White_Maintime, " .
             "White_Byotime=$White_Byotime, " .
             "White_Byoperiods=$White_Byoperiods, ";
      }

      $next_clockused = ( $next_to_move == BLACK ? $Blackclock : $Whiteclock );
      if( $WeekendClock != 'Y' )
         $next_clockused += 100;

      if( $next_to_move == BLACK and $Blackonvacation > 0 or
          $next_to_move == WHITE and $Whiteonvacation > 0 )
      {
         $next_clockused = -1;
         $next_ticks = 0;
      }
      else
         $next_ticks = get_clock_ticks($next_clockused);

      $time_query .= "LastTicks=$next_ticks, " .
          "ClockUsed=$next_clockused, ";
   }

   $no_marked_dead = ( $Status == 'PLAY' or $Status == 'PASS' or $action == 'move' );

   list($lastx,$lasty) =
      make_array( $gid, $array, $msg, $Moves, NULL, $moves_result, $marked_dead, $no_marked_dead );

   $Moves++;

   if( $message ) $message = trim($message);

   $where_clause = " ID=$gid AND Moves=$old_moves";

   switch( $action )
   {
      case 'move':
      {
         check_move();
  //ajusted globals by check_move(): $array, $Black_Prisoners, $White_Prisoners, $prisoners, $nr_prisoners;
  //here, $prisoners list the captured stones of play (or suicided stones if, a day, $suicide_allowed==true)

         $query = "INSERT INTO Moves (gid, MoveNr, Stone, PosX, PosY, Hours) VALUES ";

         reset($prisoners);
         $new_prisoner_string = "";

         while( list($dummy, list($x,$y)) = each($prisoners) )
         {
            $query .= "($gid, $Moves, \"NONE\", $x, $y, 0), ";
            $new_prisoner_string .= number2sgf_coords($x, $y, $Size);
         }

         if( strlen($new_prisoner_string) != $nr_prisoners*2 or
             (isset($prisoner_string) and $new_prisoner_string != $prisoner_string) )
            error("move_problem");

         $query .= "($gid, $Moves, $to_move, $colnr, $rownr, $hours) ";

         if( $message )
            $query2 = "INSERT INTO MoveMessages SET gid=$gid, MoveNr=$Moves, Text=\"$message\"";

         $game_query = "UPDATE Games SET " .
             "Moves=$Moves, " .
             "Last_X=$colnr, " .
             "Last_Y=$rownr, " .
             "Lastchanged=FROM_UNIXTIME($NOW), " .
             "Status='PLAY', " . $time_query;

         if( $nr_prisoners > 0 )
            if( $to_move == BLACK )
               $game_query .= "Black_Prisoners=$Black_Prisoners, ";
            else
               $game_query .= "White_Prisoners=$White_Prisoners, ";

         if( $nr_prisoners == 1 )
            $flags |= KO;
         else
            $flags &= ~KO;

         $game_query .= "ToMove_ID=$next_to_move_ID, " .
             "Flags=$flags " .
             " WHERE ID=$gid AND Moves=$old_moves LIMIT 1";
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
            error("invalid_action");


         $query = "INSERT INTO Moves SET " .
             "gid=$gid, " .
             "MoveNr=$Moves, " .
             "Stone=$to_move, " .
             "PosX=-1, " .
             "Hours=$hours";

         if( $message )
            $query2 = "INSERT INTO MoveMessages SET gid=$gid, MoveNr=$Moves, Text=\"$message\"";

         $game_query = "UPDATE Games SET " .
             "Moves=$Moves, " .
             "Last_X=-1, " .
             "Status='$next_status', " .
             "Lastchanged=FROM_UNIXTIME($NOW), " .
             "ToMove_ID=$next_to_move_ID, " . $time_query .
             "Flags=0 " .
             " WHERE ID=$gid AND Moves=$old_moves LIMIT 1";
      }
      break;

      case 'handicap':
      {
         if( $Status != 'PLAY' or $Moves != 1 )
            error("invalid_action");

         check_handicap();

         if( strlen( $stonestring ) != 2 * $Handicap + 1 )
            error("wrong_number_of_handicap_stone");


         $query = "INSERT INTO Moves ( gid, MoveNr, Stone, PosX, PosY, Hours ) VALUES ";


         for( $i=1; $i <= $Handicap; $i++ )
         {
            list($colnr,$rownr) = sgf2number_coords(substr($stonestring, $i*2-1, 2), $Size);

            if( !isset($rownr) or !isset($colnr) )
               error("illegal_position");

            $query .= "($gid, $i, " . BLACK . ", $colnr, $rownr, " .
               ($i == $Handicap ? "$hours)" : "0), " );
         }

         if( $message )
            $query2 = "INSERT INTO MoveMessages " .
               "SET gid=$gid, MoveNr=$Handicap, Text=\"$message\"";


         $game_query = "UPDATE Games SET " .
             "Moves=$Handicap, " .
             "Lastchanged=FROM_UNIXTIME($NOW), " .
             "Last_X=$colnr, " .
             "Last_Y=$rownr, " . $time_query .
             "ToMove_ID=$White_ID " .
             " WHERE ID=$gid AND Moves=$old_moves LIMIT 1";
      }
      break;

      case 'resign':
      {
         $query = "INSERT INTO Moves SET " .
             "gid=$gid, " .
             "MoveNr=$Moves, " .
             "Stone=$to_move, " .
             "PosX=-3, " .
             "Hours=$hours";

         if( $message )
            $query2 = "INSERT INTO MoveMessages SET gid=$gid, MoveNr=$Moves, Text=\"$message\"";

         if( $to_move == BLACK )
            $score = SCORE_RESIGN;
         else
            $score = -SCORE_RESIGN;

         $game_query = "UPDATE Games SET " .
             "Moves=$Moves, " .
             "Lastchanged=FROM_UNIXTIME($NOW), " .
             "Last_X=-3, " .
             "Status='FINISHED', " .
             "ToMove_ID=0, " .
             "Score=$score, " . $time_query .
             "Flags=0" .
             " WHERE ID=$gid AND Moves=$old_moves LIMIT 1";

         $game_finished = true;
      }
      break;

      case 'delete':
      {
         if( $Status != 'PLAY' or ( $Moves >= 1+DELETE_LIMIT+$Handicap ) )
            error("invalid_action");

/* Rod:
  Here, the previous line was:
         $query = "DELETE FROM Moves WHERE gid=$gid LIMIT $Moves";
  But, the number of records of Moves could be greater than the number of moves if:
  - there are prisoners
  - a sequence like PASS/PASS/SCORE.../RESUME had already occured.
  So some garbage records could remains alone because of the LIMIT.
*/
         $query = "DELETE FROM Moves WHERE gid=$gid";
         $query2 = "DELETE FROM MoveMessages WHERE gid=$gid LIMIT $Moves";
         $game_query = "DELETE FROM Games WHERE ID=$gid LIMIT 1";

         $game_finished = true;
      }
      break;

      case 'done':
      {
         if( $Status != 'SCORE' and $Status != 'SCORE2' )
            error("invalid_action");

         check_remove();
  //ajusted globals by check_remove(): $array, $score, $stonestring;

         $l = strlen( $stonestring );

         $next_status = 'SCORE2';
         if( $Status == 'SCORE2' and  $l < 2 )
         {
            $next_status = 'FINISHED';
            $game_finished = true;
         }

         $query = "INSERT INTO Moves ( gid, MoveNr, Stone, PosX, PosY, Hours ) VALUES ";

         for( $i=1; $i < $l; $i += 2 )
         {
            list($x,$y) = sgf2number_coords(substr($stonestring, $i, 2), $Size);
            $query .= "($gid, $Moves, " . ($to_move == BLACK ? MARKED_BY_BLACK : MARKED_BY_WHITE ) . ", $x, $y, 0), ";
         }

         $query .= "($gid, $Moves, $to_move, -2, NULL, $hours) ";

         if( $message )
            $query2 = "INSERT INTO MoveMessages SET gid=$gid, MoveNr=$Moves, Text=\"$message\"";


         $game_query = "UPDATE Games SET " .
             "Lastchanged=FROM_UNIXTIME($NOW), " .
             "Moves=$Moves, " .
             "Last_X=-2, " .
             "Status='$next_status', " . $time_query;

         if( $next_status != 'FINISHED' )
         {
            if( $next_to_move == BLACK )
               $game_query .= "ToMove_ID=$Black_ID, ";
            else
               $game_query .= "ToMove_ID=$White_ID, ";
         }
         else
            $game_query .= "ToMove_ID=0, ";


         $game_query .=
             "Flags=0, " .
             "Score=$score" .
             " WHERE ID=$gid AND Moves=$old_moves LIMIT 1";

      }
      break;

      default:
      {
         error("invalid_action");
      }
   }


   $result = mysql_query( $query );

   if( mysql_affected_rows() < 1 and $action != 'delete' )
      error("mysql_insert_move", true);

   if( strlen($query2) > 0 )
   {
      $result = mysql_query( $query2 );

      if( mysql_affected_rows() < 1 and $action != 'delete' )
         error("mysql_insert_move", true);
   }



   $result = mysql_query( $game_query );

   if( mysql_affected_rows() != 1 )
      error("mysql_update_game", true);


   if( $game_finished )
   {
      // send message to my opponent about the result

      $result = mysql_query( "SELECT * FROM Players WHERE ID=" .
                             ( $player_row["ID"] == $Black_ID ? $White_ID : $Black_ID ) );

      if( mysql_num_rows($result) != 1 )
         error("opponent_not_found", true);

      $opponent_row = mysql_fetch_array($result);

      if( $player_row["ID"] == $Black_ID )
      {
         $blackname = $player_row["Name"];
         $whitename = $opponent_row["Name"];
      }
      else
      {
         $whitename = $player_row["Name"];
         $blackname = $opponent_row["Name"];
      }


      if( $action == 'delete' )
      {
         $Text = addslashes("The game $whitename (W)  vs. $blackname (B) " .
             "has been deleted by your opponent");
         $Subject = 'Game deleted';

         mysql_query("UPDATE Players SET Running=Running-1 " .
                     "WHERE ID=$Black_ID OR ID=$White_ID LIMIT 2");

         delete_all_observers($gid, false);
      }
      else
      {
//         update_rating($gid);
         update_rating2($gid);

         $Text = addslashes("The result in the game <a href=\"game.php?gid=$gid\">" .
             "$whitename (W)  vs. $blackname (B) </a>" .
             "was: <p><center>" . score2text($score,true,true) . "</center><br>");
         $Subject = 'Game result';

         mysql_query( "UPDATE Players " .
                      "SET Running=Running-1, Finished=Finished+1" .
                      ($score > 0 ? ", Won=Won+1" : ($score < 0 ? ", Lost=Lost+1 " : "")) .
                      " WHERE ID=$White_ID LIMIT 1" );

         mysql_query( "UPDATE Players " .
                      "SET Running=Running-1, Finished=Finished+1" .
                      ($score < 0 ? ", Won=Won+1" : ($score > 0 ? ", Lost=Lost+1 " : "")) .
                      " WHERE ID=$Black_ID LIMIT 1" );

         delete_all_observers($gid, ($old_moves >= DELETE_LIMIT+$Handicap), $Text);
      }

      if ( $message )
      {
         $Text .= "<p>Your opponent wrote:<p>" . $message;
      }

      mysql_query( "INSERT INTO Messages SET " .
                   "From_ID=" . $player_row["ID"] . ", " .
                   "To_ID=" . $opponent_row["ID"] . ", " .
                   "Time=FROM_UNIXTIME($NOW), " .
                   "Game_ID=$gid, Subject='$Subject', Text='$Text'");

      $mid = mysql_insert_id();

      mysql_query("INSERT INTO MessageCorrespondents (uid,mid,Sender,Folder_nr) VALUES " .
                  "(" . $opponent_row['ID'] . ", $mid, 'N', '".FOLDER_NEW."')");


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

   if( $nextstatus )
   {
      jump_to("status.php");
   }
   else if( $nextgame )
   {
      jump_to_next_game($player_row["ID"], $Lastchanged, $gid);
   }

   jump_to("game.php?gid=$gid");
}
?>
