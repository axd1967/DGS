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

connect2mysql();

$logged_in = who_is_logged( $player_row);

start_page(T_("Register"), true, $logged_in, $player_row );

echo "<center>\n";

$reg_form = new Form( 'loginform', 'do_registration.php', FORM_POST );
$reg_form->add_row( array( 'HEADER', T_('Please enter data') ) );
$reg_form->add_row( array( 'DESCRIPTION', T_('Userid'),
                           'TEXTINPUT', 'userid', 16, 16, '' ) );
$reg_form->add_row( array( 'DESCRIPTION', T_('Full name'),
                           'TEXTINPUT', 'name', 16,80, '' ) );
$reg_form->add_row( array( 'DESCRIPTION', T_('Password'),
                           'PASSWORD', 'passwd', 16, 16, '' ) );
$reg_form->add_row( array( 'DESCRIPTION', T_('Confirm password'),
                           'PASSWORD', 'passwd2', 16, 16, '' ) );
$reg_form->add_row( array( 'SUBMITBUTTON', 'register', T_('Register') ) );
$reg_form->echo_string(1);

  echo T_("Note for beginners: read the FAQ especially for your initial rank setting in your profile page.");

echo "</center>\n";

end_page();
?>
