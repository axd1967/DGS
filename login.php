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

if( @$_REQUEST['quick_mode'] )
   $quick_errors = 1;

{
   disable_cache();

   connect2mysql();

   $userid = @$_REQUEST['userid'];
   $result = mysql_query( "SELECT *, UNIX_TIMESTAMP(Sessionexpire) AS Expire ".
                          "FROM Players WHERE Handle='" . $userid . "'" );

   if( @mysql_num_rows($result) != 1 )
      error("wrong_userid");


   $row = mysql_fetch_array($result);

   $passwd = @$_REQUEST['passwd'];
   check_password( $userid, $row["Password"], $row["Newpassword"], $passwd );

   $code = $row["Sessioncode"];

   if( !$code or $row["Expire"] < $NOW )
   {
      $code = make_session_code();
      $result = mysql_query( "UPDATE Players SET " .
                             "Sessioncode='$code', " .
                             "Sessionexpire=FROM_UNIXTIME($NOW + $session_duration) " .
                             "WHERE Handle='$userid' LIMIT 1" );

   }

   if( @$_COOKIE[COOKIE_PREFIX.'handle'] != $userid
      or @$_COOKIE[COOKIE_PREFIX.'sessioncode'] != $code )
   {
      set_cookies( $userid, $code );
   }

   if( $quick_errors )
   {
      echo "\nOk";
      exit;
   }
   jump_to("status.php");
}
?>
