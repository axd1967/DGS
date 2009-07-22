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

require_once( 'include/quick_common.php' );
require_once( "include/std_functions.php" );
require_once( 'include/classlib_userconfig.php' );
require_once( 'include/classlib_userquota.php' );

define('USE_REGEXP_REGISTRATION',1); //loose account name reject


{
   connect2mysql();

   error_on_blocked_ip( 'ip_blocked_register' );

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

   $policy = get_request_arg('policy');
   if( !$policy )
     error("registration_policy_not_checked");

   if( !USE_REGEXP_REGISTRATION )
   {
      //if foO exist, reject foo but accept fo0 (with a zero instead of uppercase o)
      $result = db_query( 'do_registration.find_player',
         "SELECT Handle FROM Players WHERE Handle='".mysql_addslashes($uhandle)."'" );
   }
   else
   {
   //reject the O0, l1I and S5 confusing matchings (used by account usurpers)
   //for instance, with the Arial font, a I and a l can't be distinguished
      $regx = preg_quote($uhandle); //quotemeta()
      $regx = eregi_replace( '[0o]', '[0o]', $regx);
      $regx = eregi_replace( '[1li]', '[1li]', $regx);
      $regx = eregi_replace( '[5s]', '[5s]', $regx);
      $regx = mysql_addslashes($regx);
      $regx = '^'.$regx.'$';

      $result = db_query( 'do_registration.find_player_regexp',
         "SELECT Handle FROM Players WHERE Handle REGEXP '$regx'" );
   }

   if( @mysql_num_rows($result) > 0 )
      error('userid_in_use');



// Userid and password are fine, now do the registration to the database

   $code = make_session_code();

   $result = db_query( 'do_registration.insert_player',
      "INSERT INTO Players SET " .
                          "Handle='".mysql_addslashes($uhandle)."', " .
                          "Name='".mysql_addslashes($name)."', " .
                          "Password=".PASSWORD_ENCRYPT."('".mysql_addslashes($passwd)."'), " .
                          "Registerdate=FROM_UNIXTIME($NOW), " .
                          "Sessioncode='$code', " .
                          "Sessionexpire=FROM_UNIXTIME(".($NOW+SESSION_DURATION).")" );

   $new_id = mysql_insert_id();

   if( mysql_affected_rows() != 1 )
      error("mysql_insert_player");

   ConfigPages::insert_default( $new_id );
   ConfigBoard::insert_default( $new_id );
   UserQuota::insert_default( $new_id );

   set_login_cookie( $uhandle, $code );

   jump_to("status.php");
}
?>
