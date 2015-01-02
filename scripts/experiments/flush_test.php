<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Jens-Uwe Gaspar

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


// Demonstrates how flushing works using implicit-flush page-flag!

chdir('../..');
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';

$GLOBALS['ThePage'] = new Page('Flush', PAGEFLAG_IMPLICIT_FLUSH );

{
   $beginall = getmicrotime();
   disable_cache();
   connect2mysql();
   //set_time_limit(0); // don't want script-break during "transaction" with multi-db-queries

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scripts.flush_test');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.flush_test');
   if ( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'scripts.flush_test');

   start_html( 'flush_test', 0, '',
      "  table.Table { border:0; background: #c0c0c0; }\n" .
      "  tr.Row1 { background: #ffffff; }\n" .
      "  tr.Row2 { background: #dddddd; }\n" .
      "  tr.hil { background: #ffb010; }" );

//-----------------

   echo "Start ", date(DATE_FMT5, $NOW), "<br><br>\n";

   for ( $i=1; $i <= 8; $i++ )
   {
      echo "Entry $i<br>\n";
      for ( $j=1; $j <= 3000000; $j++ ) ; // wait a bit (without using sleep()-func)
   }

   end_html();
}//main

?>
