<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival

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

$TranslateGroups[] = "Docs";

require_once( "include/std_functions.php" );

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   start_page(T_('Affero General Public License'), true, $logged_in, $player_row );

   //section( 'Docs');
   centered_container();

   echo "<pre>\n";

   //readfile("COPYING");
   //$contents= implode('', file('COPYING'));
   $contents= @read_from_file('COPYING');
   $contents= str_replace("\x0c", "\n\n", $contents);
   $contents= @htmlentities($contents, ENT_QUOTES);
   echo $contents;

   echo "</pre>\n";

   end_page();
?>
