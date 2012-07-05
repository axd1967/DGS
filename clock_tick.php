<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once( "include/rating.php" );
require_once( 'include/classlib_game.php' );
require_once( 'include/game_functions.php' );

$TheErrors->set_mode(ERROR_MODE_COLLECT);

if( !$is_down )
{
   $tick_diff = floor(3600/TICK_FREQUENCY);
   if( $chained )
      $chained = $tick_diff;
   else
      connect2mysql();
   $tick_diff -= 10;


   // Check that ticks are not too frequent

   $row = mysql_single_fetch( 'clock_tick.check_frequency',
      "SELECT ($NOW-UNIX_TIMESTAMP(Lastchanged)) AS timediff"
      ." FROM Clock WHERE ID=".CLOCK_CRON_TICK." LIMIT 1" );
   if( !$row )
      $TheErrors->dump_exit('clock_tick');
   if( $row['timediff'] < $tick_diff )
      $TheErrors->dump_exit('clock_tick');

   db_query( 'clock_tick.set_lastchanged',
         "UPDATE Clock SET Ticks=1, Lastchanged=FROM_UNIXTIME($NOW) WHERE ID=".CLOCK_CRON_TICK." LIMIT 1" )
      or $TheErrors->dump_exit('clock_tick');


   // ---------- BEGIN ------------------------------

// Handle game timeouts

   if( !$clocks_stopped )
      handle_game_timeouts();



   // ---------- END --------------------------------

   db_query( 'clock_tick.reset_tick',
         "UPDATE Clock SET Ticks=0 WHERE ID=".CLOCK_CRON_TICK." LIMIT 1" );

   if( !$chained )
      $TheErrors->dump_exit('clock_tick');

}//$is_down
//end main


// build a range SQL-query-part for the field $n
// from $s to $e on a 24 clocks basis with the offset $o
function clkrng( $n, $s, $e, $o=0)
{
   if( $s>23 ) $s-= 24;
   if( $e>23 ) $e-= 24;
   if( $s>$e )
      return clkrng($n,0,$e,$o)." OR ".clkrng($n,$s,23,$o);
   $s+= $o; $e+= $o;
   if( $s==$e )
      return "$n=$s";
   return "($n BETWEEN $s AND $e)"; // ($n>=$s AND $n<=$e)
}//clkrng

function handle_game_timeouts()
{
   global $NOW;

   // $NOW is time in UTC
   $hour = gmdate('G', $NOW);
   $day_of_week = gmdate('w', $NOW);

   // Now increase clocks that are not sleeping

   /* NIGHT_LEN hours night
    * Nightstart= N means night in: [ N,(N+NIGHT_LEN)%24 [    (see edit_profile.php)
    *                                 N <= time < (N + NIGHT_LEN)%24
    * if Timezone=='GMT', ClockUsed is always equal to Nightstart
    * if Timezone=='GMT+x'(ou UTC+x), ClockUsed is ( Nightstart+24-x )%24
    * NOTES:
    * - "+24" added to avoid becoming negative
    * - ClockUsed is the GMT hour on which the users night start
    * - ClockUsed also used for weekends (+weekend offset), so NightStart also influences start/end of weekend
    *
    * When the GMT hour is 22, clock_modified= [23]U[00,13], thus:
    *  UserTZ UserTime UserNight UserClockUsed UserClockModified
    *  GMT    22       [22,07[   22            -
    *  GMT+9  07       [22,07[   13            Y
    *  GMT+2  00       [22,07[   20            -
    *  GMT-2  20       [22,07[   00            Y
    *  GMT-9  13       [22,07[   07            Y
    *  GMT+2  00       [20,05[   18            -
    *  GMT+2  00       [22,07[   20            -
    *  GMT+2  00       [02,11[   00            Y
    *  GMT-2  20       [20,05[   22            -
    *  GMT-2  20       [22,07[   00            Y
    *  GMT-2  20       [02,11[   04            Y
    */

   $clock_modified = clkrng( 'Clock.ID', $hour+1, $hour+24-NIGHT_LEN);
   if( $day_of_week > 0 && $day_of_week < 6 )
      $clock_modified.= " OR ".
         clkrng( 'Clock.ID', $hour+1, $hour+24-NIGHT_LEN, WEEKEND_CLOCK_OFFSET);

   db_query( 'clock_tick.increase_clocks',
      "UPDATE Clock SET Ticks=Ticks+1, Lastchanged=FROM_UNIXTIME($NOW) "
         . "WHERE $clock_modified " // game-clocks
         . "OR Clock.ID=".CLOCK_TIMELEFT // clock for remaining-time ordering
      );


   // Check if any game has timed out

   /* TODO(keep as note) BUG in old & new version:
     if maintenance spans more than a whole hour and graces the sleeping-clocks,
     the time-window has passed to detect time-outs within that hour.
     So time-outs are delayed by a full day till the games with the respective clock is checked again!

     => keep Clock.ID>=0 (as safety for clones; else join with Games would be empty anyway
        as there is no Clock.ID<0 any more)
     => remove Clock.Lastchanged=$NOW to match all clock-timeouts
    */

   /*
    In a first approximation, as $has_moved==false (see time_remaining()),
     $main>$hours will just produce $main-=$hours which will never
     activate $time_is_up and so, will produce nothing here...
    So the "find_timeout_games" query may be restricted with:
    - given IF(ToMove_ID=Black_ID, Black_Maintime, White_Maintime) AS ToMove_Maintime
    - WHERE ToMove_Maintime <= $hours
    because $hours is always < $ticks/TICK_FREQUENCY (see ticks_to_hours())
    - Clock.ticks is always > Games.LastTicks
    - given ((ticks-LastTicks) / TICK_FREQUENCY) AS Upper_Ellapsed
    - WHERE ToMove_Maintime < Upper_Ellapsed
    which is:
    - WHERE IF(ToMove_ID=Black_ID, Black_Maintime, White_Maintime)
         < ((Clock.Ticks-Games.LastTicks) / TICK_FREQUENCY)

    During a test, this lowered the number of returned rows from 10,960 to 675.
    This is a table scan, but indeed reduces the number of affected rows.
   */

   $result = db_query( 'clock_tick.find_timeout_games',
      "SELECT Games.*, Games.ID as gid, Clock.Ticks as ticks, Games.Flags+0 AS X_GameFlags"
      ." FROM Games"
         ." INNER JOIN Clock ON Clock.ID=Games.ClockUsed AND ($clock_modified)"
      ." WHERE Clock.ID >= 0 AND" // older DGS-clones may still have clocks<0
      ." IF(ToMove_ID=Black_ID, Black_Maintime, White_Maintime) * ".TICK_FREQUENCY." < Clock.Ticks - Games.LastTicks "
      ." AND Status" . IS_STARTED_GAME
      );

   /* TODO: The following UPDATE should be optimized, splited in smaller chunk?
            use TEMPORARY TABLEs for generated UPDATEs ??? */

   while($row = mysql_fetch_assoc($result))
   {
      extract($row);

      //$game_clause (lock) needed. See *** HOT_SECTION *** in confirm.php
      $game_clause = " WHERE ID=$gid AND Status".IS_STARTED_GAME." AND Moves=$Moves LIMIT 1";

      $hours = ticks_to_hours($ticks - $LastTicks);

      if( $ToMove_ID == $Black_ID )
      {
         time_remaining( $hours, $Black_Maintime, $Black_Byotime, $Black_Byoperiods,
            $Maintime, $Byotype, $Byotime, $Byoperiods, false);

         $time_is_up = ( $Black_Maintime == 0 && $Black_Byotime == 0 );
      }
      else if( $ToMove_ID == $White_ID )
      {
         time_remaining( $hours, $White_Maintime, $White_Byotime, $White_Byoperiods,
            $Maintime, $Byotype, $Byotime, $Byoperiods, false);

         $time_is_up = ( $White_Maintime == 0 && $White_Byotime == 0 );
      }
      else
         continue;


      if( $time_is_up )
      {
         //TODO(feature): Delete games with too few moves ??? (if so -> send delete-game-msg)

         $score = ( $ToMove_ID == $Black_ID ) ? SCORE_TIME : -SCORE_TIME;
         $game_finalizer = new GameFinalizer( ACTBY_CRON, /*cron*/0, $gid, $tid,
            $Status, $GameType, $GamePlayers, $X_GameFlags, $Black_ID, $White_ID, $Moves, ($Rated != 'N') );

         ta_begin();
         {//HOT-section to save game-timeout
            // send message to my opponent / all-players / observers about the result
            $game_finalizer->finish_game( "clock_tick.time_is_up", /*del*/false, null, $score, '' ); // +clears QST-cache
         }
         ta_end();
      }//time-out
   }
   mysql_free_result($result);

}//handle_game_timeouts

?>
