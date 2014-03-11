<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/std_functions.php';
require_once 'include/dgs_cache.php';

$TheErrors->set_mode(ERROR_MODE_COLLECT);


if ( !$is_down )
{
   $hourly_diff = SECS_PER_HOUR;
   if ( $chained )
      $chained = $hourly_diff;
   else
      connect2mysql();
   $hourly_diff -= 5*SECS_PER_MIN;


   // Check that updates are not too frequent

   $row = mysql_single_fetch( 'hourly_cron.check_frequency',
      "SELECT ($NOW-UNIX_TIMESTAMP(Lastchanged)) AS timediff"
      ." FROM Clock WHERE ID=".CLOCK_CRON_HOUR." LIMIT 1" );
   if ( !$row )
      $TheErrors->dump_exit('hourly_cron');
   if ( $row['timediff'] < $hourly_diff )
      $TheErrors->dump_exit('hourly_cron');

   db_query( 'hourly_cron.set_lastchanged',
         "UPDATE Clock SET Ticks=1, Lastchanged=FROM_UNIXTIME($NOW) WHERE ID=".CLOCK_CRON_HOUR." LIMIT 1" )
      or $TheErrors->dump_exit('hourly_cron');

   // ---------- BEGIN ------------------------------

   // Cleanup cache removing cache-entries that have expired (if cache-impl supports it)
   DgsCache::cleanup_cache();


   // ---------- END --------------------------------

   db_query( 'hourly_cron.reset_tick',
         "UPDATE Clock SET Ticks=0, Finished=FROM_UNIXTIME(".time().") WHERE ID=".CLOCK_CRON_HOUR." LIMIT 1" );

   if ( !$chained )
      $TheErrors->dump_exit('hourly_cron');

}//$is_down
?>
