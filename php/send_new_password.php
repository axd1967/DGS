<?php
/*
Dragon Go Server
Copyright (C) 2001  Erik Ouchterlony

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


include( "std_functions.php" );

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);



if( $action == 'Go back' )
{
    header("Location: index.php");
    exit;
}

$result = mysql_query( "SELECT Newpassword, Email " .
                       "FROM Players WHERE Handle='$handle'" );
  
if( mysql_num_rows($result) != 1 )
{
    header("Location: error.php?err=unknown_user");
    exit;
}

$row = mysql_fetch_array($result);

if( !empty($row['Newpassword']) )
{
    header("Location: error.php?err=newpassword_already_sent");
    exit;
}

// Now generate new password:

$newpasswd = generate_random_password();

// Save password in database

$result = mysql_query( "UPDATE Players " .
                       "SET Newpassword=PASSWORD('$newpasswd') Where Handle='$handle'" );
         

mail( $row["Email"], 
'Dragon Go Server: New password', 
'You (or possibly someone else) has requested a new password, and it has 
been randomly chosen as: ' . $newpasswd . '

Both the old and the new password will also be valid until your next 
login. Now please login and then change your password to something more 
rememberable.
 
' . $HOSTNAME,

'From: ' . $EMAIL_FROM);


start_page("New password sent", true, $logged_in, $player_row );

echo "New password sent!";

end_page();
?>
