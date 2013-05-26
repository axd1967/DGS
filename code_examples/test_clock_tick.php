<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

/*
 * Code example to test for better query-time of clock-tick-script
 *
 * Usage: change var $v in code to 'A' or 'B' and run it
 */

chdir('..');
require_once 'include/std_functions.php';
require_once 'include/rating.php';

{
   exit; // NOTE: for security: comment for local-testing only
   $v = 'A'; // NOTE: for manual test -> choose 'A' or 'B' !!

   connect2mysql();

   $start = time();
   $lim = 10000;
   for ($i=1; $i <= $lim; $i++) {
      test($v, $i, false);
      if ( $i % 100 == 0 ) {
         echo "$i/$lim ...\n";
         echo "Needed[$v], Sum: " . (time() - $start) . "s\n";
      }
   }
   echo "Needed[$v], Sum: " . (time() - $start) . "s\n";
}

function test( $qver, $nr, $print=true )
{
   global $NOW;
   $begin = gettimeofday();
   if ( $qver == 'A' )
   {
      $query = 'SELECT Games.*, Games.ID as gid, Clock.Ticks as ticks, ' .
               'black.Handle as blackhandle, white.Handle as whitehandle, ' .
               'black.Name as blackname, white.Name as whitename ' .
               'FROM Games ' .
                  'INNER JOIN Clock ON Clock.ID=Games.ClockUsed ' .
                  'INNER JOIN Players AS white ON white.ID=Games.White_ID ' .
                  'INNER JOIN Players AS black ON black.ID=Games.Black_ID ' .
               "WHERE Status NOT IN ('KOMI','SETUP','INVITED','FINISHED' ) " . // all running games (using != )
                  'AND Games.ClockUsed >= 0 ' . // not VACATION_CLOCK
                  "AND Clock.Lastchanged=FROM_UNIXTIME($NOW)";
   }
   else
   {
      $query = 'SELECT Games.*, Games.ID as gid, Clock.Ticks as ticks, ' .
               'black.Handle as blackhandle, white.Handle as whitehandle, ' .
               'black.Name as blackname, white.Name as whitename ' .
               'FROM Games ' .
                  'INNER JOIN Clock ON Clock.ID=Games.ClockUsed ' .
                  'INNER JOIN Players AS white ON white.ID=Games.White_ID ' .
                  'INNER JOIN Players AS black ON black.ID=Games.Black_ID ' .
               'WHERE Status'.IS_RUNNING_GAME . // all running games (using = )
                  'AND Games.ClockUsed >= 0 ' . // not VACATION_CLOCK
                  "AND Clock.Lastchanged=FROM_UNIXTIME($NOW)";
   }

   $result = db_query( "clock_tick.find_timeout_games", $query );

   while ($row = mysql_fetch_assoc($result))
   {
      extract($row);

      $hours = ticks_to_hours($ticks - $LastTicks);

      if ( $ToMove_ID == $Black_ID )
      {
         time_remaining( $hours, $Black_Maintime, $Black_Byotime, $Black_Byoperiods,
            $Maintime, $Byotype, $Byotime, $Byoperiods, false);

         $time_is_up = ( $Black_Maintime == 0 && $Black_Byotime == 0 );
      }
      else if ( $ToMove_ID == $White_ID )
      {
         time_remaining( $hours, $White_Maintime, $White_Byotime, $White_Byoperiods,
            $Maintime, $Byotype, $Byotime, $Byoperiods, false);

         $time_is_up = ( $White_Maintime == 0 && $White_Byotime == 0 );
      }
   }
   $end = gettimeofday();
   mysql_free_result($result);

   if ( $print )
      echo "Needed[$qver], #$nr: " . timediff($begin,$end) . "\n";

}

function timediff( $start, $end )
{
   $sec1 = $start['sec'];
   $ms1  = $start['usec']; // micro-secs

   $sec2 = $end['sec'];
   $ms2  = $end['usec'];

   $sec_diff   = $sec2 - $sec1;
   $msdiff = $ms2 - $ms1;
   if ( $msdiff < 0 )
   {
      $msdiff += 1000000;
      $sec_diff++;
   }
   $msdiff += $sec_diff * 1000000;

   return sprintf("%1.3f", $msdiff/1000);
}

?>
