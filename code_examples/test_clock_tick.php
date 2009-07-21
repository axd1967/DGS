<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once( "include/std_functions.php" );
require_once( "include/rating.php" );

{
   connect2mysql();

   $start = time();
   $lim = 1000;
   #$lim = 100000;
   $v = 'B';
   for($i=1; $i <= $lim; $i++) {
      test($v, $i, false);
      if( $i % 100 == 0 ) {
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
   if( $qver == 'A' )
   {
      $query = 'SELECT Games.*, Games.ID as gid, Clock.Ticks as ticks, ' .
               'black.Handle as blackhandle, white.Handle as whitehandle, ' .
               'black.Name as blackname, white.Name as whitename ' .
               'FROM (Games, Clock, Players as white, Players as black) ' .
               "WHERE Status!='INVITED' AND Status!='FINISHED' " .
               'AND Games.ClockUsed >= 0 ' . // not VACATION_CLOCK
               'AND Clock.ID=Games.ClockUsed ' .
               "AND Clock.Lastchanged=FROM_UNIXTIME($NOW) " .
               'AND white.ID=White_ID AND black.ID=Black_ID';
   }
   else
   {
      $query = 'SELECT Games.*, Games.ID as gid, Clock.Ticks as ticks, ' .
               'black.Handle as blackhandle, white.Handle as whitehandle, ' .
               'black.Name as blackname, white.Name as whitename ' .
               'FROM (Games, Clock, Players as white, Players as black) ' .
               "WHERE Status IN ('PLAY','PASS','SCORE','SCORE2') " .
               'AND Games.ClockUsed >= 0 ' . // not VACATION_CLOCK
               'AND Clock.ID=Games.ClockUsed ' .
               "AND Clock.Lastchanged=FROM_UNIXTIME($NOW) " .
               'AND white.ID=White_ID AND black.ID=Black_ID';
   }

   $result = mysql_query($query)
               or error('mysql_query_failed','clock_tick.find_timeout_games');

   while($row = mysql_fetch_assoc($result))
   {
      extract($row);

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
   }
   $end = gettimeofday();
   mysql_free_result($result);

   if( $print )
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
   if( $msdiff < 0 )
   {
      $msdiff += 1000000;
      $sec_diff++;
   }
   $msdiff += $sec_diff * 1000000;

   return sprintf("%1.3f", $msdiff/1000);
}

?>
