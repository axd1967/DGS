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

$TranslateGroups[] = "Start";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   if( !($player_row['admin_level'] & ADMIN_PASSWORD) )
      error("adminlevel_too_low");

   start_page(T_("Admin").' - '.T_('Send password'), true, $logged_in, $player_row );

   echo "<center>\n";

   $passwd_form = new Form( 'adminnewpasswdform', "send_new_password.php", FORM_POST );

   $passwd_form->add_row( array( 'HEADER', T_('New password') ) );

   $passwd_form->add_row( array( 'DESCRIPTION', T_('Userid'),
                                 'TEXTINPUT', 'userid', 16, 16, '',
                                 'SUBMITBUTTON', 'action', T_("Send password"),
                               ) );
   $passwd_form->add_row( array( 'DESCRIPTION', T_('Email'),
                                 'TEXTINPUT', 'email', 16, 80, '',
                                 'TEXT', T_("to overwrite user's one"),
                               ) );
   $passwd_form->echo_string();

   echo "</center>\n";
   end_page();
}
?>
