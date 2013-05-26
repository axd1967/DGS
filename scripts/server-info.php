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

chdir('..');
require_once 'include/std_functions.php';

{
   connect2mysql();

   $logged_in = who_is_logged($player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scripts.server_info');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.server_info');
   if ( !(@$player_row['admin_level'] & ADMIN_DEVELOPER) )
      error('adminlevel_too_low', 'scripts.server_info');

   $cpuinfo = @file_get_contents( "/proc/cpuinfo", false );
   $meminfo = @file_get_contents( "/proc/meminfo", false );

   start_page('DGS Server Info', true, $logged_in, $player_row );

   section('cpuinfo', 'CPU Info');
   echo "<pre>\n$cpuinfo</pre><br>\n";

   section('meminfo', 'Memory Info');
   echo "<pre>\n$meminfo</pre><br>\n";

   end_page();
}
?>
