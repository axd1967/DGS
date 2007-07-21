<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

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

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );


{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( $player_row['Handle'] == 'guest' )
      error('not_allowed_for_guest');

   if( @$_REQUEST['guestpass'] )
   {
      if( !(@$player_row['admin_level'] & ADMIN_PASSWORD) )
         error('adminlevel_too_low');

      $passwd = $GUESTPASS;
      if( illegal_chars( $passwd, true ) )
         error('password_illegal_chars');

      $query = "UPDATE Players SET " .
          "Password=PASSWORD('".mysql_addslashes($passwd)."') " .
          "WHERE Handle='guest' LIMIT 1";
   }
   else
   {
      $oldpasswd = @$_POST['oldpasswd'];
      if( !check_password( $player_row["Handle"], $player_row["Password"],
                           $player_row["Newpassword"], $oldpasswd ) )
         error("wrong_password");
   
      $passwd = @$_POST['passwd'];
      if( strlen($passwd) < 6 )
         error("password_too_short");
      if( illegal_chars( $passwd, true ) )
         error("password_illegal_chars");
   
      if( $passwd != @$_POST['passwd2'] )
         error("password_mismatch");
   
      $query = "UPDATE Players SET " .
          "Password=PASSWORD('".mysql_addslashes($passwd)."') " .
          "WHERE ID=" . $player_row['ID'] . " LIMIT 1";
   }

   mysql_query( $query )
      or error('mysql_query_failed','change_password');

   $msg = urlencode(T_('Password changed!'));

   jump_to("userinfo.php?uid=" . $player_row["ID"] . URI_AMP."sysmsg=$msg");
}
?>
