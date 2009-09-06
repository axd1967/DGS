<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once( "include/std_functions.php" );


{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $guest_id= (int)@$_REQUEST['guestpass'];
   if( $guest_id > 0 && $guest_id <= GUESTS_ID_MAX )
   {
      // admin can change guest-password(?)
      if( !(@$player_row['admin_level'] & ADMIN_PASSWORD) )
         error('adminlevel_too_low', 'change_password');

      $passwd = $GUESTPASS;
      if( illegal_chars( $passwd, true ) )
         error('password_illegal_chars');

      $query = "UPDATE Players SET " .
          "Password=".PASSWORD_ENCRYPT."('".mysql_addslashes($passwd)."') " .
          "WHERE ID=$guest_id LIMIT 1";
   }
   else
   {
      $oldpasswd = @$_POST['oldpasswd'];
      if( !check_password( $player_row["Handle"], $player_row["Password"],
                           $player_row["Newpassword"], $oldpasswd ) )
      {
         error("wrong_password");
      }

      $passwd = @$_POST['passwd'];
      if( strlen($passwd) < 6 )
         error("password_too_short");
      if( illegal_chars( $passwd, true ) )
         error("password_illegal_chars");

      if( $passwd != @$_POST['passwd2'] )
         error("password_mismatch");

      $query = "UPDATE Players SET " .
          "Password=".PASSWORD_ENCRYPT."('".mysql_addslashes($passwd)."') " .
          "WHERE ID=" . $player_row['ID'] . " LIMIT 1";
   }

   db_query( 'change_password', $query );

   $msg = urlencode(T_('Password changed!'));

   jump_to("userinfo.php?uid=" . $player_row["ID"] . URI_AMP."sysmsg=$msg");
}
?>
