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

if( @$_REQUEST['quick_mode'] )
   $quick_errors = 1;
require_once( "include/std_functions.php" );

{
   if( @$_GET['logout'] )
   {
      set_login_cookie("","", true);
      if( $quick_errors )
         exit;
      jump_to("index.php");
   }

   connect2mysql();

   $uhandle = get_request_arg('userid');
   $result = mysql_query( "SELECT *, UNIX_TIMESTAMP(Sessionexpire) AS Expire ".
                          "FROM Players WHERE Handle='".addslashes($uhandle)."'" );

   if( @mysql_num_rows($result) != 1 )
      error("wrong_userid");


   $row = mysql_fetch_array($result);

   $passwd = get_request_arg('passwd');
   if( !check_password( $uhandle, $row["Password"],
                        $row["Newpassword"], $passwd ) )
      error("wrong_password");

   $code = $row["Sessioncode"];

   if( !$code or $row["Expire"] < $NOW )
   {
      $code = make_session_code();
      $result = mysql_query( "UPDATE Players SET " .
                             "Sessioncode='$code', " .
                             "Sessionexpire=FROM_UNIXTIME($NOW + $session_duration) " .
                             "WHERE Handle='".addslashes($uhandle)."' LIMIT 1" )
                or error("mysql_query_failed");
   }

   if( @$_COOKIE[COOKIE_PREFIX.'handle'] != $uhandle
      or @$_COOKIE[COOKIE_PREFIX.'sessioncode'] != $code )
   {
      if( @$_REQUEST['cookie_check'] )
         error('cookies_disabled');

      set_login_cookie( $uhandle, $code );
      jump_to("login.php?cookie_check=1"
             . URI_AMP."userid=".urlencode($uhandle)
             . URI_AMP."passwd=".urlencode($passwd)
             . ( $quick_errors ? URI_AMP."quick_mode=1" : '' )
             );
   }

   if( $quick_errors )
   {
      echo "\nOk";
      exit;
   }
   jump_to("status.php");
}
?>
