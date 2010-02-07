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

require_once( "include/quick_common.php" );


if( !$is_down )
{
   $chained = 1;

   $TheErrors->set_mode(ERROR_MODE_COLLECT);
   $TheErrors->error_clear();

   connect2mysql();

   // the whole cron stuff in one run (see also 'INSTALL')
   {
      // normally every 5 mins
      include_once( "clock_tick.php" );

      // normally every 15 mins
      include_once( "tournaments/cron_tournaments.php" );

      // normally every 30 mins
      include_once( "halfhourly_cron.php" );

      // normally every 24 h
      include_once( "daily_cron.php" );
   }

   if( $chained )
      $TheErrors->dump_exit('cron_chained');

}//$is_down
?>
