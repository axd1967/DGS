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

require_once( "include/std_functions.php" );

$quick_mode = (boolean)@$_REQUEST['quick_mode'];
if( $quick_mode )
   $TheErrors->set_mode(ERROR_MODE_PRINT);

{
   if( @$_REQUEST['logout'] )
   {
      set_login_cookie("","", true);
      if( $quick_mode )
      {
         echo "\nOk";
         exit;
      }
      jump_to("index.php");
   }

   connect2mysql();


   $uhandle = (string)get_request_arg('userid');
   $passwd = (string)get_request_arg('passwd');
   if( !$passwd && is_numeric( $i= strcspn( $uhandle, ':') ) )
   {
      $passwd = substr($uhandle,$i+1);
      $uhandle = substr($uhandle,0,$i);
   }

   $row = mysql_single_fetch( "login.find_player($uhandle)",
                  "SELECT Handle, AdminOptions,Password,Newpassword,Sessioncode, " .
                        "UNIX_TIMESTAMP(Sessionexpire) AS Expire ".
                  "FROM Players WHERE Handle='".mysql_addslashes($uhandle)."'" );
   if( !$row )
      error('wrong_userid', "login.find_player2($uhandle)");

   $code = @$row['Sessioncode'];
   $userid = @$row['Handle'];

   if( $userid == 'guest' )
      error_on_blocked_ip( 'ip_blocked_guest_login', $row );

   if( !@$_REQUEST['cookie_check'] )
   {
      if( !check_password( $uhandle, $row['Password'],
                           $row['Newpassword'], $passwd ) )
      {
         admin_log( 0, $userid, 'wrong_password');
         error("wrong_password", "login.check_password($userid,$uhandle)");
      }

      if( !$code || @$row['Expire'] < $NOW )
      {
         $code = make_session_code();
         db_query( 'login.update_player',
            "UPDATE Players SET " .
                      "Sessioncode='$code', " .
                      'Sessionexpire=FROM_UNIXTIME(' . ($NOW + SESSION_DURATION) . ') ' .
                      "WHERE Handle='".mysql_addslashes($uhandle)."' LIMIT 1" );
      }

      set_login_cookie( $uhandle, $code );
      jump_to("login.php?cookie_check=1"
             . URI_AMP."userid=".urlencode($uhandle)
             . ( $quick_mode ? URI_AMP."quick_mode=1" : '' )
             );
   }
   //else cookie_check

   if( safe_getcookie('handle') != $uhandle || safe_getcookie('sessioncode') != $code )
      error('cookies_disabled', "login.check_cookies($uhandle)");

   if( (@$row['AdminOptions'] & ADMOPT_DENY_LOGIN) )
      error('login_denied');

   //admin_log( 0, $userid, 'logged_in'); // activate when needed

   if( $quick_mode )
   {
      echo "\nOk";
      exit;
   }
   jump_to("status.php");
}
?>
