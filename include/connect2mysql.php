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

require_once( "include/config.php" );

//At least needed when connect2mysql.php is used alone (as in quick_status.php):
function jump_to($uri, $absolute=false)
{
   global $HOSTBASE;

   if( $absolute )
      header( "Location: " . $uri );
   else
      header( "Location: " . $HOSTBASE . '/' . $uri );

   exit;
}

function disable_cache($stamp=NULL)
{
   global $NOW;
  // Force revalidation
   header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
   header ('Cache-Control: no-store, no-cache, must-revalidate, max_age=0'); // HTTP/1.1
   header ('Pragma: no-cache');                                              // HTTP/1.0
   if( !$stamp )
      header ('Last-Modified: ' . gmdate('D, d M Y H:i:s', $NOW) . ' GMT');  // Always modified
   else
      header ('Last-Modified: ' . gmdate('D, d M Y H:i:s',$stamp) . ' GMT');
}

function error($err, $debugmsg=NULL)
{
   disable_cache();

   $handle = @$_COOKIE[COOKIE_PREFIX.'handle'];

   $uri = "error.php?err=" . urlencode($err);
   $errorlog_query = "INSERT INTO Errorlog SET Handle='$handle', " .
      "Message='$err', IP='{$_SERVER['REMOTE_ADDR']}'" ;

   $mysqlerror = @mysql_error();

   if( !empty($mysqlerror) )
   {
      $uri .= "&mysqlerror=" . urlencode($mysqlerror);
      $errorlog_query .= ", MysqlError='" . $mysqlerror . "'";
      $err.= ' / '. $mysqlerror;
   }

   
   if( empty($debugmsg) )
   {
      $debugmsg = @$_SERVER['PHP_SELF'];
   }
   //if( !empty($debugmsg) )
   {
      $errorlog_query .= ", Debug='$debugmsg'";
      //$err.= ' / '. $debugmsg;
   }

   @mysql_query( $errorlog_query );

 global $quick_errors;
   if( @$quick_errors )
   {
      //Short one line message:
      echo "\nError: ".ereg_replace( "[\x01-\x20]+", " ", $err);
      exit;
   }

   jump_to( $uri );
}


function connect2mysql($no_errors=false)
{
   global $MYSQLUSER, $MYSQLHOST, $MYSQLPASSWORD, $DB_NAME;

   $dbcnx = @mysql_connect( $MYSQLHOST, $MYSQLUSER, $MYSQLPASSWORD);

   if (!$dbcnx)
   {
      if( $no_errors ) return;
      error("mysql_connect_failed");
   }

   if (! @mysql_select_db($DB_NAME) )
   {
      if( $no_errors ) return;
      error("mysql_select_db_failed");
   }
}

?>