<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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

 /* The code in this file is written by Ragnar Ouchterlony */

require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );
require_once( "include/tournament.php" );

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   start_page(T_("Tournaments"), true, $logged_in, $player_row );

   $table = new Table( 'list_tournaments.php', '', 't_', true );

   $table->add_tablehead( 1, T_('ID'), 'ID', false, true );
   $table->add_tablehead( 2, T_('State'), 'State', false, true );
   $table->add_tablehead( 3, T_('Name'), 'Name', false, true );

   $table->add_row( array( 1 => "111", 2 => "Inactive", 3 => "A name" ) );
   $table->add_row( array( 1 => "122", 2 => "Active", 3 => "Another name" ) );

   $table->echo_table();

   end_page(false);
}
