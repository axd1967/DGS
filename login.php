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

$quick_errors = isset($_REQUEST['quick_mode']);

require_once( "include/std_functions.php" );

{
   if( @$_REQUEST['logout'] )
   {
      set_login_cookie("","", true);
      if( $quick_errors )
         exit;
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

   $row = mysql_single_fetch( "SELECT *, UNIX_TIMESTAMP(Sessionexpire) AS Expire ".
                              "FROM Players WHERE Handle='".addslashes($uhandle)."'",
                              'assoc', 'login.find_player');

   if( !$row )
      error("wrong_userid");


   $code = $row["Sessioncode"];

   if( !@$_REQUEST['cookie_check'] )
   {
      if( !check_password( $uhandle, $row["Password"],
                           $row["Newpassword"], $passwd ) )
         error("wrong_password");

      if( !$code or $row["Expire"] < $NOW )
      {
         $code = make_session_code();
         mysql_query( "UPDATE Players SET " .
                      "Sessioncode='$code', " .
                      "Sessionexpire=FROM_UNIXTIME($NOW + $session_duration) " .
                      "WHERE Handle='".addslashes($uhandle)."' LIMIT 1" )
            or error('mysql_query_failed', 'login.update_player');
      }

      set_login_cookie( $uhandle, $code );
      jump_to("login.php?cookie_check=1"
             . URI_AMP."userid=".urlencode($uhandle)
             . ( $quick_errors ? URI_AMP."quick_mode=1" : '' )
             );
   }
   //else cookie_check

   if( safe_getcookie('handle') != $uhandle
      or safe_getcookie('sessioncode') != $code )
   {
      error('cookies_disabled');
   }

   if( $quick_errors )
   {
      echo "\nOk";
      exit;
   }
   jump_to("status.php");
}
?>
