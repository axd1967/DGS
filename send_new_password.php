<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   $my_id = @$player_row['ID'];

   if( isset($_POST['goback']) )
      jump_to("index.php");

   $pswduser = get_request_arg('pswduser');
   if( strtolower($pswduser) == 'guest' )
      error('not_allowed_for_guest');

   $result = db_query( "send_new_password.find_player($pswduser)",
      "SELECT ID, Newpassword, Email " .
         "FROM Players WHERE Handle='".mysql_addslashes($pswduser)."' LIMIT 1" );
   if( @mysql_num_rows($result) != 1 )
      error('unknown_user', "send_new_password.find_player2($pswduser)");

   $row = mysql_fetch_assoc($result);
   if( $row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   if( !empty($row['Newpassword']) )
   {
      if( @$_POST['overnew'] )
      {
         // Could force email only if admin
         if( !$logged_in )
            error('not_logged_in', "send_new_password.check_admin.password($my_id,$pswduser)");
         if( !(@$player_row['admin_level'] & ADMIN_PASSWORD) )
            error('adminlevel_too_low', "send_new_password.reset_password($my_id,$pswduser)");

         $row['Newpassword'] = '';
      }
   }

   if( !empty($row['Newpassword']) )
      error('newpassword_already_sent', "send_new_password.already_sent($pswduser)");

   if( !empty($_POST['email']) )
   {
      // Could force email only if admin
      if( !$logged_in )
         error('not_logged_in', "send_new_password.check_admin.email($my_id,$pswduser)");
      if( !(@$player_row['admin_level'] & ADMIN_PASSWORD) )
         error('adminlevel_too_low', "send_new_password.set_email($my_id,$pswduser)");

      $row['Email'] = trim($_POST['email']);
   }

   if( empty($row['Email']) )
      error('no_email', "send_new_password.miss_email($my_id,$pswduser)");


// Now generate new password:

   $newpasswd = generate_random_password();

   $Email= trim($row['Email']);

   admin_log( @$player_row['ID'], @$player_row['Handle'],
         "send a new password to $pswduser at $Email.");

// Save password in database

   $result = db_query( "send_new_password.update($pswduser)",
         "UPDATE Players " .
         "SET Newpassword=".PASSWORD_ENCRYPT."('$newpasswd') " .
         "WHERE Handle='".mysql_addslashes($pswduser)."' LIMIT 1" );

   $msg=
"You (or possibly someone else) has requested a new password\n" .
" for the account: $pswduser\n" . //the handle of the requesting account
" and it has been randomly chosen as: $newpasswd\n" .
'
Both the old and the new password will also be valid until
 your next login. Now please login and then change your
 password to something more rememberable.

' . HOSTBASE;

   verify_email( 'send_new_password', $Email);

   send_email("send_new_password Uid:$pswduser Text:$msg", $Email, 0, $msg);

   $msg = urlencode(T_("New password sent!"));
   if( $logged_in )
      jump_to("status.php?sysmsg=$msg");
   jump_to("index.php?sysmsg=$msg");
}
?>
