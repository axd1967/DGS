<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/std_functions.php';

$quick_mode = (boolean)@$_REQUEST['quick_mode'];
if ( $quick_mode )
   $TheErrors->set_mode(ERROR_MODE_PRINT);


{
   disable_cache();

   if ( @$_REQUEST['logout'] )
   {
      set_login_cookie("","", true);
      if ( $quick_mode )
      {
         echo "\nOk";
         exit;
      }
      jump_to("index.php");
   }

   connect2mysql();


   $uhandle = (string)get_request_arg('userid');
   $passwd = (string)get_request_arg('passwd');
   if ( !$passwd && is_numeric( $i= strcspn( $uhandle, ':') ) )
   {
      $passwd = substr($uhandle,$i+1);
      $uhandle = substr($uhandle,0,$i);
   }

   $row = mysql_single_fetch( "login.find_player($uhandle)",
         "SELECT Handle, AdminOptions,Password,Newpassword,Sessioncode, UNIX_TIMESTAMP(Sessionexpire) AS Expire " .
         "FROM Players WHERE Handle='".mysql_addslashes($uhandle)."' LIMIT 1" );
   if ( !$row )
      error('wrong_userid', "login.find_player2($uhandle)");

   $code = @$row['Sessioncode'];
   $userid = @$row['Handle'];

   if ( $userid == 'guest' )
      error_on_blocked_ip( 'ip_blocked_guest_login', $row );

   if ( (@$row['AdminOptions'] & ADMOPT_DENY_LOGIN) )
      error('login_denied', "login($uhandle,$userid)");

   if ( !@$_REQUEST['cookie_check'] ) // login with user-handle + password
   {
      if ( !check_password( $uhandle, $row['Password'], $row['Newpassword'], $passwd ) )
      {
         admin_log( 0, $userid, 'wrong_password');
         error('wrong_password', "login.check_password($userid,$uhandle)");
      }
      if ( $is_down )
         check_maintenance( $uhandle );

      if ( !$code || @$row['Expire'] < $NOW )
      {
         $code = make_session_code();
         db_query( 'login.update_player',
            "UPDATE Players SET " .
                      "Sessioncode='$code', " .
                      'Sessionexpire=FROM_UNIXTIME(' . ($NOW + SESSION_DURATION) . '), ' .
                      'CountBulletinNew=-1 ' .
                      "WHERE Handle='".mysql_addslashes($uhandle)."' LIMIT 1" );
      }

      if ( $uhandle !== $userid && strcasecmp($uhandle,$userid) == 0 )
         $uhandle = $userid; // store original user-id as db-check is case-INsensitive
      set_login_cookie( $uhandle, $code );
   }
   else // cookie_check (login via cookies user-handle + sessioncode)
   {
      if ( safe_getcookie('handle') != $uhandle || safe_getcookie('sessioncode') != $code )
         error('cookies_disabled', "login.check_cookies($uhandle)");
   }

   //admin_log( 0, $userid, 'logged_in'); // activate when needed

   if ( $quick_mode )
   {
      echo "\nOk";
      exit;
   }

   // redirect to original intended page (or else status-page)
   $page = ( isset($_REQUEST['page']) ) ? $_REQUEST['page'] : 'status.php';
   jump_to($page);
}
?>
