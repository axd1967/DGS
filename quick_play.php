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

define('HOT_SECTION', true);


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

   connect2mysql();

   // logged in?

   $result = @mysql_query( "SELECT ID, Timezone, " .
                           "UNIX_TIMESTAMP(Sessionexpire) AS Expire, Sessioncode " .
                           "FROM Players WHERE Handle='{$_COOKIE[COOKIE_PREFIX.'handle']}'" );

   if( @mysql_num_rows($result) != 1 )
   {
      error("not_logged_in");
   }

   $player_row = mysql_fetch_assoc($result);

   if( $player_row['Sessioncode'] !== @$_COOKIE[COOKIE_PREFIX.'sessioncode']
       or $player_row["Expire"] < $NOW )
   {
      error("not_logged_in");
   }

   $gid = @$_REQUEST['gid'] ;
   if( $gid <= 0 )
      error("no_game_nr");

/*
   if( !empty( $player_row["Timezone"] ) )
      putenv('TZ='.$player_row["Timezone"] );
*/

   $my_id = $player_row['ID'];

   $result = mysql_query( "SELECT Games.*, " .
                          "Games.Flags+0 AS flags, " .
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

   $Last_X = NULL; $Last_Y = NULL;
   extract(mysql_fetch_assoc($result));

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

   $old_moves = $Moves;

   if( $Black_ID == $ToMove_ID )
      $to_move = BLACK;
   else if( $White_ID == $ToMove_ID )
      $to_move = WHITE;
   else
      error("database_corrupted");

   if( $my_id != $ToMove_ID )
      error("not_your_turn");


   if( isset($_REQUEST['sgf_move']) )
      list( $query_X, $query_Y) = sgf2number_coords($_REQUEST['sgf_move'], $Size);
   elseif( isset($_REQUEST['board_move']) )
      list( $query_X, $query_Y) = board2number_coords($_REQUEST['board_move'], $Size);
   else
      list( $query_X, $query_Y) = array( NULL, NULL);

   if( is_null($query_X) or is_null($query_Y) )
      error("illegal_position");

   if( isset($_REQUEST['sgf_prev']) )
      list( $prev_X, $prev_Y) = sgf2number_coords($_REQUEST['sgf_prev'], $Size);
   elseif( isset($_REQUEST['board_prev']) )
      list( $prev_X, $prev_Y) = board2number_coords($_REQUEST['board_prev'], $Size);
   else
      list( $prev_X, $prev_Y) = array( NULL, NULL);

   if( is_null($prev_X) or is_null($prev_Y) )
      error("illegal_position");

   if( $prev_X != $Last_X or $prev_Y != $Last_Y )
      error("already_played");

   $move_color = @$_REQUEST['color'];
   if( $move_color != ($to_move==WHITE ? 'W' : 'B') )
      error("not_your_turn");


   $next_to_move = WHITE+BLACK-$to_move;

   if( $old_moves+1 < $Handicap ) $next_to_move = BLACK;

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


   list($lastx,$lasty) =
      make_array( $gid, $array, $msg, $old_moves, NULL, $moves_result, $marked_dead, true );

   $where_clause = " ID=$gid AND Moves=$old_moves";
   //$too_few_moves = ($old_moves < DELETE_LIMIT+$Handicap) ;
   $Moves++;

      //case 'move':
      {
         $coord = number2sgf_coords( $query_X, $query_Y, $Size);

         check_move();
  //ajusted globals by check_move(): $array, $Black_Prisoners, $White_Prisoners, $prisoners, $nr_prisoners, $colnr, $rownr;
  //here, $prisoners list the captured stones of play (or suicided stones if, a day, $suicide_allowed==true)

         $move_query = "INSERT INTO Moves (gid, MoveNr, Stone, PosX, PosY, Hours) VALUES ";

         reset($prisoners);
         $new_prisoner_string = "";

         while( list($dummy, list($x,$y)) = each($prisoners) )
         {
            $move_query .= "($gid, $Moves, \"NONE\", $x, $y, 0), ";
            $new_prisoner_string .= number2sgf_coords($x, $y, $Size);
         }

         if( strlen($new_prisoner_string) != $nr_prisoners*2 )
            error("move_problem");

         $move_query .= "($gid, $Moves, $to_move, $colnr, $rownr, $hours) ";

         $game_query = "UPDATE Games SET " .
             "Moves=$Moves, " .
             "Last_X=$colnr, " .
             "Last_Y=$rownr, " .
             "Status='PLAY', ";

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
             "Flags=$flags, " .
             $time_query . "Lastchanged=FROM_UNIXTIME($NOW)" .
             " WHERE $where_clause LIMIT 1";
      }


if( HOT_SECTION )
{
   //*********************** HOT SECTION START ***************************
   //could append in case of multi-players account with simultaneous logins
   //or if one player hit twice the validation button during a net lag
   //and if opponent has already played between the two quick_play.php/confirm.php calls.

   $result = mysql_query( "LOCK TABLES Games WRITE, Moves WRITE" );

   if ( !$result )
      error("internal_error","quick_play LOCK");

   // Maybe not useful:
   function unlock_games_tables()
   {
      $result = mysql_query( "UNLOCK TABLES");
   }
   register_shutdown_function('unlock_games_tables');

   // Locked ... an ultimate verification:
   $result = mysql_query( "SELECT Moves FROM Games WHERE Games.ID=$gid" );

   if( @mysql_num_rows($result) != 1 )
      error("internal_error", "quick_play verif $gid");

   $tmp = mysql_fetch_assoc($result);

   if( $tmp["Moves"] != $old_moves )
      error("already_played");
}//HOT_SECTION

   $result = mysql_query( $move_query );

   if( mysql_affected_rows() < 1 )
      error("mysql_insert_move");

   $result = mysql_query( $game_query );

   if( mysql_affected_rows() != 1 )
      error("mysql_update_game");

if( HOT_SECTION )
{
   $result = mysql_query( "UNLOCK TABLES");
   if ( !$result )
      error("internal_error","quick_play UNLOCK");
   //*********************** HOT SECTION END *****************************
}//HOT_SECTION



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


   if( $quick_errors )
   {
      echo "\nOk";
      exit;
   }

// Jump somewhere

   jump_to("game.php?gid=$gid");
}
?>