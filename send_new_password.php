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


{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);



   if( $action == 'Go back' )
      jump_to("index.php");


   $result = mysql_query( "SELECT Newpassword, Email " .
                          "FROM Players WHERE Handle='$userid'" );

   if( mysql_num_rows($result) != 1 )
      error("unknown_user");


   $row = mysql_fetch_array($result);

   if( !empty($row['Newpassword']) )
      error("newpassword_already_sent");


// Now generate new password:

   $newpasswd = generate_random_password();

// Save password in database

   $result = mysql_query( "UPDATE Players " .
                          "SET Newpassword=PASSWORD('$newpasswd') Where Handle='$userid'" );


   mail( $row["Email"],
   'Dragon Go Server: New password',
   'You (or possibly someone else) has requested a new password, and it has
been randomly chosen as: ' . $newpasswd . '

Both the old and the new password will also be valid until your next
login. Now please login and then change your password to something more
rememberable.

' . $HOSTBASE,

   'From: ' . $EMAIL_FROM);


   $msg = urlencode("New password sent!");
   jump_to("status.php?msg=$msg");
}
?>
