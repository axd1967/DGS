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

$quick_errors = 1;
require_once( "include/std_functions.php" );
require_once( "include/board.php" );
require_once( "include/move.php" );
//require_once( "include/rating.php" );



function quick_warning($string) //Short one line message
{
   echo "\nWarning: " . ereg_replace( "[\x01-\x20]+", " ", $string);
}



if( $is_down )
{
   quick_warning($is_down_message);
}
else
{
   disable_cache();

   $gid = @$_REQUEST['gid'] ;
   if( $gid <= 0 )
      error("no_game_nr");

   connect2mysql();

   // logged in?

   $uhandle= @$_COOKIE[COOKIE_PREFIX.'handle'];
   $result = @mysql_query( "SELECT ID, Timezone, " .
                           "UNIX_TIMESTAMP(Sessionexpire) AS Expire, Sessioncode " .
                           "FROM Players WHERE Handle='".addslashes($uhandle)."'" );

   if( @mysql_num_rows($result) != 1 )
   {
      error("not_logged_in",'qp1');
   }

   $player_row = mysql_fetch_assoc($result);

   if( $player_row['Sessioncode'] !== @$_COOKIE[COOKIE_PREFIX.'sessioncode']
       or $player_row["Expire"] < $NOW )
   {
      error("not_logged_in",'qp2');
   }

/*
   setTZ( $player_row['Timezone']);
*/

   $my_id = $player_row['ID'];

   $game_row = mysql_single_fetch(
                          "SELECT Games.*, " .
                          "Games.Flags+0 AS GameFlags, " . //used by check_move
                          "black.ClockUsed AS Blackclock, " .
                          "white.ClockUsed AS Whiteclock, " .
                          "black.OnVacation AS Blackonvacation, " .
                          "white.OnVacation AS Whiteonvacation " .
                          "FROM Games, Players AS black, Players AS white " .
                          "WHERE Games.ID=$gid AND Black_ID=black.ID AND White_ID=white.ID"
                        );

   if( !$game_row )
      error("unknown_game");

   $Last_X = $Last_Y = -1;
   $game_row=mysql_fetch_assoc($result);
   extract($game_row);

   if( $Status == 'INVITED' )
   {
      error("game_not_started");
   }
   else if( $Status == 'FINISHED' )
   {
      error("game_finished");
   }
   else if( $Status!='PLAY' //exclude SCORE,PASS steps and INVITED or FINISHED
      or !number2sgf_coords( $Last_X, $Last_Y, $Size) //exclude first move and previous moves like pass,resume...
      or ($Handicap>1 && $Moves<=$Handicap) //exclude first white move after handicap stones
     )
   {
      error("invalid_action");
   }


   if( $my_id != $ToMove_ID )
      error("not_your_turn",'qp9');


   //See *** HOT_SECTION *** below
   if( isset($_REQUEST['sgf_move']) )
      list( $query_X, $query_Y) = sgf2number_coords($_REQUEST['sgf_move'], $Size);
   elseif( isset($_REQUEST['board_move']) )
      list( $query_X, $query_Y) = board2number_coords($_REQUEST['board_move'], $Size);
   else
      list( $query_X, $query_Y) = array( NULL, NULL);

   if( is_null($query_X) or is_null($query_Y) )
      error("illegal_position",'qp3');

   if( isset($_REQUEST['sgf_prev']) )
      list( $prev_X, $prev_Y) = sgf2number_coords($_REQUEST['sgf_prev'], $Size);
   elseif( isset($_REQUEST['board_prev']) )
      list( $prev_X, $prev_Y) = board2number_coords($_REQUEST['board_prev'], $Size);
   else
      list( $prev_X, $prev_Y) = array( NULL, NULL);

   if( is_null($prev_X) or is_null($prev_Y) )
      error("illegal_position",'qp4');

   if( $prev_X != $Last_X or $prev_Y != $Last_Y )
      error("already_played",'qp5');

   $move_color = @$_REQUEST['color'];
   if( $move_color != ($to_move==WHITE ? 'W' : 'B') )
      error("not_your_turn",'qp8');


   if( $Black_ID == $ToMove_ID )
      $to_move = BLACK;
   else if( $White_ID == $ToMove_ID )
      $to_move = WHITE;
   else
      error("database_corrupted");


   //$action = always 'move'

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

   $no_marked_dead = true; //( $Status == 'PLAY' or $Status == 'PASS' or $action == 'move' );

   $TheBoard = new Board( );
   if( !$TheBoard->load_from_db( $game_row, 0, $no_marked_dead) )
      error('internal_error', "quick_play load_from_db $gid");

   //$too_few_moves = ($Moves < DELETE_LIMIT+$Handicap) ;



/* **********************
*** HOT_SECTION ***
>>> See also confirm.php, quick_play.php and clock_tick.php
Various dirty things (like duplicated moves) could append
in case of multiple calls with the same move number. This could
append in case of multi-players account with simultaneous logins
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
   $Moves++;



      //case 'move':
      {
         $coord = number2sgf_coords( $query_X, $query_Y, $Size);

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

         if( strlen($prisoner_string) != $nr_prisoners*2 )
            error("move_problem");

         $move_query .= "($gid, $Moves, $to_move, $colnr, $rownr, $hours) ";


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


   //See *** HOT_SECTION *** above
   $result = mysql_query( $game_query . $game_clause );

   if( mysql_affected_rows() != 1 )
      error("mysql_update_game","qp20($gid)");

   $result = mysql_query( $move_query );

   if( mysql_affected_rows() < 1 and $action != 'delete' )
      error("mysql_insert_move","qp21($gid)");





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



// No Jump somewhere

   echo "\nOk";
}
?>