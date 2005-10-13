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

   $uri= str_replace( URI_AMP, URI_AMP_IN, $uri);
   session_write_close();
   //header('HTTP/1.1 303 REDIRECT');
   if( $absolute )
      header( "Location: " . $uri );
   else
      header( "Location: " . $HOSTBASE . '/' . $uri );
   header('Status: 303');
   //header('Connection: close'); 
   exit;
}

function disable_cache($stamp=NULL)
{
   global $NOW;
  // Force revalidation
   header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
   header('Cache-Control: no-store, no-cache, must-revalidate, max_age=0'); // HTTP/1.1
   header('Pragma: no-cache');                                              // HTTP/1.0
   if( !$stamp )
      header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $NOW) . ' GMT');  // Always modified
   else
      header('Last-Modified: ' . gmdate('D, d M Y H:i:s',$stamp) . ' GMT');
}


if( !function_exists('error') )
{
function error($err, $debugmsg=NULL)
{

   $handle = @$_COOKIE[COOKIE_PREFIX.'handle'];

   list( $err, $uri)= err_log( $handle, $err, $debugmsg);

 global $quick_errors;
   if( @$quick_errors )
      quick_error( $err ); //Short one line message

   disable_cache();

   jump_to( $uri );
}
}


function err_log( $handle, $err, $debugmsg=NULL)
{

   $uri = "error.php?err=" . urlencode($err);
   $errorlog_query = "INSERT INTO Errorlog SET Handle='".addslashes($handle)."', " .
      "Message='$err', IP='{$_SERVER['REMOTE_ADDR']}'" ;

   $mysqlerror = @mysql_error();

   if( !empty($mysqlerror) )
   {
      $uri .= URI_AMP."mysqlerror=" . urlencode($mysqlerror);
      $errorlog_query .= ", MysqlError='".addslashes( $mysqlerror)."'";
      $err.= ' / '. $mysqlerror;
   }

   
   if( empty($debugmsg) )
   {
    global $SUB_PATH;
      $debugmsg = @$_SERVER['REQUEST_URI']; //@$_SERVER['PHP_SELF'];
      //$debugmsg = str_replace( $SUB_PATH, '', $debugmsg);
      $debugmsg = substr( $debugmsg, strlen($SUB_PATH));
   }
   //if( !empty($debugmsg) )
   {
      $errorlog_query .= ", Debug='" . addslashes( $debugmsg) . "'";
      //$err.= ' / '. $debugmsg; //Do not display this info!
   }

 global $dbcnx;
   if( !isset($dbcnx) )
      connect2mysql( true);

   @mysql_query( $errorlog_query );

   return array( $err, $uri);
}


function admin_log( $uid, $handle, $text)
{
   $query = "INSERT INTO Adminlog SET uid='$uid', Handle='".addslashes($handle)."', Message='".addslashes($text)."'" ;

   return mysql_query( $query );
}


function connect2mysql($no_errors=false)
{
   global $dbcnx, $MYSQLUSER, $MYSQLHOST, $MYSQLPASSWORD, $DB_NAME;

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