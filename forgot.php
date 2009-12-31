<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival

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

$TranslateGroups[] = "Start";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );
require_once( "include/error_codes.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   $title = T_('Forgot password?');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=\"Header\">$title</h3>\n";

   echo '<p></p>';
   centered_container();
   echo '<p></p>',
      T_('Resetting a password by yourself is only possible if you have a set email in your account.'),
      '<p></p>',
      T_('If you have forgotten your password we can email a new one.
<br>The new password will be randomly generated, but you can of course change it later from the edit profile page.
<br>Until you change it, both new and old passwords will be operational.'),
      '<p></p>',
      T_('In case you have no email set in your account:'),
      "<br>\n",
      ErrorCode::get_error_text('no_email:support');
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


   $menu_array[T_("Show login page")] = 'index.php?logout=t';

   end_page(@$menu_array);
}
?>
