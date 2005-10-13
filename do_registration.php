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

require_once( "include/std_functions.php" );


{
   connect2mysql();

   $uhandle = get_request_arg('userid');
   if( strlen( $uhandle ) < 3 )
      error("userid_too_short");
   if( illegal_chars( $uhandle ) )
      error("userid_illegal_chars");

   $passwd = get_request_arg('passwd');
   if( strlen($passwd) < 6 )
      error("password_too_short");
   if( illegal_chars( $passwd, true ) )
      error("password_illegal_chars");

   if( $passwd != get_request_arg('passwd2') )
      error("password_mismatch");

   $name = get_request_arg('name');
   if( strlen( $name ) < 1 )
      error("name_not_given");

   $result = mysql_query( "SELECT * FROM Players WHERE Handle='".addslashes($uhandle)."'" );

   if( @mysql_num_rows($result) > 0 )
      error("userid_in_use");



// Userid and password are fine, now do the registration to the database

   $code = make_session_code();

   $result = mysql_query( "INSERT INTO Players SET " .
                          "Handle='".addslashes($uhandle)."', " .
                          "Name='".addslashes($name)."', " .
                          "Password=PASSWORD('".addslashes($passwd)."'), " .
                          "Registerdate=FROM_UNIXTIME($NOW), " .
                          "Sessioncode='$code', " .
                          "Sessionexpire=FROM_UNIXTIME($NOW + $session_duration)" );

   $new_id = mysql_insert_id();

   if( mysql_affected_rows() != 1 )
      error("mysql_insert_player");


   set_login_cookie( $uhandle, $code );

   jump_to("status.php");
}
?>
