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
require( "include/timezones.php" );
require( "include/rating.php" );
require( "include/form_functions.php" );

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

if( !$logged_in )
   error("not_logged_in");


start_page("Edit password", true, $logged_in, $player_row );

echo "<CENTER>\n";
echo form_start( 'passwordform', 'change_password.php', 'POST' );

echo form_insert_row( 'DESCRIPTION', 'New password',
                      'PASSWORD', 'passwd',16,16 );
echo form_insert_row( 'DESCRIPTION', 'Confirm password',
                      'PASSWORD', 'passwd2',16,16 );
echo form_insert_row( 'SUBMITBUTTON', 'action', 'Change password' );
echo form_end();
echo "</CENTER>\n";

end_page(false);

?>

