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


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( isset($_POST['goback']) )
      jump_to("index.php");

   $pswduser = get_request_arg('pswduser');

   if( $pswduser == "guest" )
      error("not_allowed_for_guest");

   $result = mysql_query( "SELECT ID, Newpassword, Email " .
                          "FROM Players WHERE Handle='".addslashes($pswduser)."'" );

   if( @mysql_num_rows($result) != 1 )
      error("unknown_user");

   $row = mysql_fetch_assoc($result);

   if( $row['ID'] == 1 )
      error("not_allowed_for_guest");

   if( !empty($row['Newpassword']) )
      error("newpassword_already_sent");

   if( !empty($_POST['email']) )
   {
     // Could force email only if admin
     if( !$logged_in )
       error("not_logged_in");
     if( !($player_row['admin_level'] & ADMIN_PASSWORD) )
       error("adminlevel_too_low");

     $row['Email'] = trim($_POST['email']);
   }

   if( empty($row['Email']) )
      error('no_email');

// Now generate new password:

   $newpasswd = generate_random_password();

   $Email= $row['Email'];

   admin_log( @$player_row['ID'], @$player_row['Handle'],
         "send a new password to $pswduser at $Email.");

// Save password in database

   $result = mysql_query( "UPDATE Players " .
                          "SET Newpassword=PASSWORD('$newpasswd') " .
                          "WHERE Handle='".addslashes($pswduser)."' LIMIT 1" );


   $msg= 'You (or possibly someone else) has requested a new password, and it has
been randomly chosen as: ' . $newpasswd . '

Both the old and the new password will also be valid until your next
login. Now please login and then change your password to something more
rememberable.

' . $HOSTBASE;

   $headers = "From: $EMAIL_FROM\n";

   if( !function_exists('mail')
    or !mail( trim($Email), $FRIENDLY_LONG_NAME.': New password', $msg, $headers ) )
      error('mail_failure',"Uid:$pswduser Addr:$Email Text:$msg");


   $msg = urlencode(T_("New password sent!"));
   if( $logged_in )
      jump_to("status.php?sysmsg=$msg");
   jump_to("index.php?sysmsg=$msg");
}
?>
