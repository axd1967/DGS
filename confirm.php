<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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

require( "include/std_functions.php" );
require( "include/board.php" );
require( "include/move.php" );

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
   if( !$gid )
      error("no_game_nr");

   if( $next == 'Go back' )
      jump_to("game.php?gid=$gid");

   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");


   $result = mysql_query( "SELECT Games.*, " .
                          "Games.Flags+0 AS flags, " .
                          "black.ClockUsed AS Blackclock, " . 
                          "white.ClockUsed AS Whiteclock " . 
                          "FROM Games, Players AS black, Players AS white " .
                          "WHERE Games.ID=$gid AND Black_ID=black.ID AND White_ID=white.ID" );

   if(  mysql_num_rows($result) != 1 )
      error("unknown_game");

   extract(mysql_fetch_array($result));

   if( $next == 'Skip to next game' )
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


   $next_to_move = 3-$to_move;

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
   $consistent_query = "Consistent='N', ";
   switch( $action )
   {
      case 'move':
      {
         check_move();

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
             "Status='PLAY', " . $time_query . $consistent_query;

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
             " WHERE" . $where_clause;
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
             "ToMove_ID=$next_to_move_ID, " . $time_query . $consistent_query .
             "Flags=0 " .
             " WHERE" . $where_clause;
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
                ($i == $Handicap ? $hours : 0 ) . ")";

            if( $message )
               $query2 = "INSERT INTO MoveMessages SET gid=$gid, MoveNr=$Moves, Text=\"$message\"";
         }


         $game_query = "UPDATE Games SET " .
             "Moves=$Handicap, " .
             "Lastchanged=FROM_UNIXTIME($NOW), " .
             "Last_X=$colnr, " .
             "Last_Y=$rownr, " . $time_query . $consistent_query .
             "ToMove_ID=$White_ID " .
             " WHERE" . $where_clause;
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
            $score = 1000;
         else
            $score = -1000;

         $game_query = "UPDATE Games SET " .
             "Moves=$Moves, " .
             "Lastchanged=FROM_UNIXTIME($NOW), " .
             "Last_X=-3, " .
             "Status='FINISHED', " .
             "ToMove_ID=0, " .
             "Score=$score, " . $time_query . $consistent_query .
             "Flags=0" .
             " WHERE" . $where_clause;

         $game_finished = true;
      }
      break;

      case 'delete':
      {
         if( $Status != 'PLAY' or ( $Moves >= 4+$Handicap ) )
            error("invalid_action");
       
         $query = "DELETE FROM Moves WHERE gid=$gid";
         $query2 = "DELETE FROM MoveMessages WHERE gid=$gid";
         $game_query = "DELETE FROM Games WHERE ID=$gid";

         $game_finished = true;
      }
      break;

      case 'done':
      {
         if( $Status != 'SCORE' and $Status != 'SCORE2' )
            error("invalid_action");

         check_done();

         $nr_prisoners = count($prisoners);

         $next_status = 'SCORE2';
         if( $Status == 'SCORE2' and  $nr_prisoners == 0 )
         {
            $next_status = 'FINISHED';
            $game_finished = true;
         }

         $query = "INSERT INTO Moves ( gid, MoveNr, Stone, PosX, PosY, Hours ) VALUES ";


         while( list($dummy, list($x,$y)) = each($prisoners) )
         {
            $query .= "($gid, $Moves, " . (9 - $to_move ) . ", $x, $y, 0), ";
         }

         $query .= "($gid, $Moves, $to_move, -2, NULL, $hours) ";

         if( $message )
            $query2 = "INSERT INTO MoveMessages SET gid=$gid, MoveNr=$Moves, Text=\"$message\"";


         $game_query = "UPDATE Games SET " .
             "Lastchanged=FROM_UNIXTIME($NOW), " .
             "Moves=$Moves, " .
             "Last_X=-2, " .
             "Status='$next_status', " . $time_query . $consistent_query;

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
             " WHERE" . $where_clause;
         
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
      $result = mysql_query( $query );
        
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


      $Text = "The result in the game <a href=\"game.php?gid=$gid\">" . 
          "$whitename (W)  vs. $blackname (B) </a>" . 
          "was: <p><center>" . score2text($score,true) . "</center></br>";

      $Subject = 'Game result';

      if( $action == 'delete' )
      {
         $Text = "The game $whitename (W)  vs. $blackname (B) " .
             "has been deleted by your opponent";
         $Subject = 'Game deleted';
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

      update_rating($gid);
   }


// Notify opponent about move

   mysql_query( "UPDATE Players SET Notify='NEXT' " .
                "WHERE ID='$next_to_move_ID' AND SendEmail LIKE '%ON%' " .
                "AND Notify='NONE' AND ID!='" .$player_row["ID"] . "'") ;



// Increase moves and activity

   mysql_query( "UPDATE Players SET Activity=Activity + $ActivityForMove, Moves=Moves+1 " . 
                "WHERE ID=" . $player_row["ID"] );



// Jump somewhere

   if( $next == "Submit and go to status" )
   {
      jump_to("status.php");
   }
   else if( $next == "Submit and go to next game" )
   {
      jump_to_next_game($player_row["ID"], $Lastchanged, $gid);
   }

   jump_to("game.php?gid=$gid");
}
?>
