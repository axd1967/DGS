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

require( "include/std_functions.php" );
require( "include/form_functions.php" );

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

start_page("Register", true, $logged_in, $player_row );

echo "<center>\n";

echo "<B><font size=+1>Please enter data:</font></B>\n";

echo form_start('loginform', 'do_registration.php', 'POST');

echo form_insert_row( 'DESCRIPTION', 'Userid',
                      'TEXTINPUT', 'userid', 16, 16, '' );
echo form_insert_row( 'DESCRIPTION', 'Full name',
                      'TEXTINPUT', 'name', 16,80, '' );
echo form_insert_row( 'DESCRIPTION', 'password',
                      'PASSWORD', 'passwd', 16, 16, '' );
echo form_insert_row( 'DESCRIPTION', 'Confirm password',
                      'PASSWORD', 'passwd2', 16, 16, '' );
echo form_insert_row( 'SUBMITBUTTON', 'register', 'register' );

echo form_end();
echo "</center>\n";

end_page();
?>
