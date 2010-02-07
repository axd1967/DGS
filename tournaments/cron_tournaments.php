<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TheErrors->set_mode(ERROR_MODE_COLLECT);


if( !$is_down )
{
   if( $chained )
      $chk_time_diff = $chained = 3600/4;
   else
      connect2mysql();
   $chk_time_diff -= 100;


   // Check that updates are not too frequent

   $row = mysql_single_fetch( 'cron_tournament.check_frequency',
      "SELECT ($NOW-UNIX_TIMESTAMP(Lastchanged)) AS timediff"
      ." FROM Clock WHERE ID=205 LIMIT 1" );
   if( !$row )
      $TheErrors->dump_exit('cron_tournament');
   if( $row['timediff'] < $chk_time_diff )
      $TheErrors->dump_exit('cron_tournament');

   db_query( 'cron_tournament.set_lastchanged',
         "UPDATE Clock SET Ticks=1, Lastchanged=FROM_UNIXTIME($NOW) WHERE ID=205 LIMIT 1" )
      or $TheErrors->dump_exit('cron_tournament');


   // ---------- BEGIN ------------------------------


   // ---------- END --------------------------------

   db_query( 'cron_tournament.reset_tick',
         "UPDATE Clock SET Ticks=0 WHERE ID=205 LIMIT 1" );

   if( !$chained )
      $TheErrors->dump_exit('cron_tournament');

}//$is_down
?>
