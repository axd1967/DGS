<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Rod Ival

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

$TranslateGroups[] = "Users";

require_once 'include/std_functions.php';
require_once 'include/form_functions.php';

{
   // NOTE: using page: change_password.php

   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'edit_password');


   start_page(T_("Edit password"), true, $logged_in, $player_row );
   echo "<h3 class=Header>", T_('Change password'), "</h3>\n";

   echo "<CENTER>\n";

   $pass_form = new Form( 'passwordform', 'change_password.php', FORM_POST );

   $pass_form->add_row( array( 'DESCRIPTION', T_('Old password'),
                               'PASSWORD', 'oldpasswd',16,16, '' ) );
   $pass_form->add_row( array( 'DESCRIPTION', T_('New password'),
                               'PASSWORD', 'passwd',16,16, '' ) );
   $pass_form->add_row( array( 'DESCRIPTION', T_('Confirm password'),
                               'PASSWORD', 'passwd2',16,16, '' ) );
   $pass_form->add_row( array( 'SUBMITBUTTON', 'action', T_('Change password') ) );

   $pass_form->echo_string(1);

   echo "</CENTER>\n";

   $menu_array = array();
   $menu_array[T_('Change email & notifications')] = 'edit_email.php';

   end_page(@$menu_array);
}
?>
