<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

   start_page(T_('Forgot password?'), true, $logged_in, $player_row );

   echo '<p></p>';
   centered_container();
   echo '<p></p>';
   echo T_('If you have forgotten your password we can email a new one.
<br>The new password will be randomly generated, but you can of course change it later from the edit profile page.
<br>Until you change it, both new and old passwords will be operational.');
   centered_container(0);

   $passwd_form = new Form( 'newpasswd', "send_new_password.php", FORM_POST );

   $passwd_form->add_row( array( 'HEADER', T_('New password') ) );

   $passwd_form->add_row( array( 'DESCRIPTION', T_('Userid'),
                                 'TEXTINPUT', 'pswduser', 16, 16, '',
                                 'SUBMITBUTTON', 'action', T_("Send password"),
                               ) );
   $passwd_form->add_row( array( 'CELL', 2, "class=right",
                                 'SUBMITBUTTON', 'goback', T_("Go back"),
                               ) );
   $passwd_form->echo_string(1);

   end_page();
}
?>
