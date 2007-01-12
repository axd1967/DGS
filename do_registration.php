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

require_once( "include/std_functions.php" );

define('USE_REGEXP_REGISTRATION',1); //loose account name reject


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

   if( !USE_REGEXP_REGISTRATION )
   {
   //if foO exist, reject foo but accept fo0 (with a zero instead of uppercase o)
      $result = mysql_query(
            "SELECT Handle FROM Players WHERE Handle='"
                  .mysql_addslashes($uhandle)."'"
         )
         or error('mysql_query_failed','do_registration.find_player');
   }
   else
   {
   //reject the oO0, lL1, sS5 confusing matchings (used by account usurpers)
      $regx = preg_quote($uhandle); //quotemeta()
      $regx = eregi_replace( '[0o]', '[0o]', $regx);
      $regx = eregi_replace( '[1l]', '[1l]', $regx);
      $regx = eregi_replace( '[5s]', '[5s]', $regx);
      $regx = mysql_addslashes($regx);
      $regx = '^'.$regx.'$';

      $result = mysql_query(
            "SELECT Handle FROM Players WHERE Handle REGEXP '$regx'"
         )
         or error('mysql_query_failed','do_registration.find_player_regexp');
   }

   if( @mysql_num_rows($result) > 0 )
      error('userid_in_use');



// Userid and password are fine, now do the registration to the database

   $code = make_session_code();

   $result = mysql_query( "INSERT INTO Players SET " .
                          "Handle='".mysql_addslashes($uhandle)."', " .
                          "Name='".mysql_addslashes($name)."', " .
                          "Password=PASSWORD('".mysql_addslashes($passwd)."'), " .
                          "Registerdate=FROM_UNIXTIME($NOW), " .
                          "Sessioncode='$code', " .
                          "Sessionexpire=FROM_UNIXTIME(".($NOW+$session_duration).")" )
      or error('mysql_query_failed', 'do_registration.insert_player');

   $new_id = mysql_insert_id();

   if( mysql_affected_rows() != 1 )
      error("mysql_insert_player");


   set_login_cookie( $uhandle, $code );

   jump_to("status.php");
}
?>
