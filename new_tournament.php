<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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

{
   require_once( "include/std_functions.php" );
   require_once( "include/tournament.php" );

   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   start_page(T_('Create New Tournament'), true, $logged_in, $player_row);

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   $tour_form = new Form( 'tournamentform', 'create_tournament.php', FORM_POST );

   $tour_form->add_row( array( 'HEADER', T_('Tournament Information') ) );
   $tour_form->add_row( array( 'DESCRIPTION', T_('Tournament name'),
                               'TEXTINPUT', 'name', 50, 80, "" ) );
   $tour_form->add_row( array( 'DESCRIPTION', T_('Tournament description'),
                               'TEXTAREA', 'description', 50, 8, "" ) );
   $tour_form->add_row( array( 'SUBMITBUTTON', 'action', T_('Submit') ) );

   echo "<CENTER>\n";
   $tour_form->echo_string();
   echo "</CENTER>\n";

   end_page(false);

}
?>
