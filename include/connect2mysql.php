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
      header( "Location: " . $HOSTBASE . $uri );
   header('Status: 303');
   //header('Connection: close'); 
   exit;
}

function disable_cache($stamp=NULL, $expire=NULL)
{
   global $NOW;
   if( !$stamp )
      $stamp = $NOW;  // Always modified
   if( !$expire )
      $expire = $stamp-3600;  // Force revalidation

   //header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
   header('Expires: ' . gmdate('D, d M Y H:i:s',$expire) . ' GMT');
   header('Last-Modified: ' . gmdate('D, d M Y H:i:s',$stamp) . ' GMT');
   if( !$expire or $expire<=$NOW )
   {
      header('Cache-Control: no-store, no-cache, must-revalidate, max_age=0'); // HTTP/1.1
      header('Pragma: no-cache');                                              // HTTP/1.0
   }
}


if( !function_exists('error') )
{
function error($err, $debugmsg=NULL)
{

   $handle = safe_getcookie('handle');

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
   $ip = (string)@$_SERVER['REMOTE_ADDR'];
   $errorlog_query = "INSERT INTO Errorlog SET Handle='".mysql_escape_string($handle)."'"
      .", Message='".mysql_escape_string($err)."', IP='".mysql_escape_string($ip)."'" ;

   $mysqlerror = @mysql_error();

   if( !empty($mysqlerror) )
   {
      $uri .= URI_AMP."mysqlerror=" . urlencode($mysqlerror);
      $errorlog_query .= ", MysqlError='".mysql_escape_string( $mysqlerror)."'";
      $err.= ' / '. $mysqlerror;
   }

   
   if( empty($debugmsg) )
   {
    global $SUB_PATH;
//CAUTION: sometime, REQUEST_URI != PHP_SELF+args
//if there is a redirection, _URI==requested, while _SELF==reached (running one)
      $debugmsg = @$_SERVER['REQUEST_URI']; //@$_SERVER['PHP_SELF'];
      //$debugmsg = str_replace( $SUB_PATH, '', $debugmsg);
      $debugmsg = substr( $debugmsg, strlen($SUB_PATH));
   }
   if( !empty($debugmsg) )
   {
      $errorlog_query .= ", Debug='" . mysql_escape_string( $debugmsg) . "'";
      //$err.= ' / '. $debugmsg; //Do not display this info!
   }

   global $dbcnx;
   if( !@$dbcnx )
      connect2mysql( true);

   @mysql_query( $errorlog_query );

   return array( $err, $uri);
}


function admin_log( $uid, $handle, $err)
{
   $uid = (int)$uid;
   $ip = (string)@$_SERVER['REMOTE_ADDR'];
   $query = "INSERT INTO Adminlog SET uid='$uid', Handle='".mysql_escape_string($handle)."'"
      .", Message='".mysql_escape_string($err)."', IP='".mysql_escape_string($ip)."'" ;

   return @mysql_query( $query );
}


function mysql_single_fetch( $query, $type='assoc', $error_debug='single_fetch')
{
   $row = false;
   $result = mysql_query( $query ) or error("mysql_query_failed", $error_debug);
   if( $result != false )
   {
      $type = 'mysql_fetch_'.$type;
      if( (mysql_num_rows($result) != 1 )
            or !is_array( $row=$type( $result) ) )
         $row = false;
      mysql_free_result($result);
   }
   return $row;
}


function connect2mysql($no_errors=false)
{
   global $dbcnx, $MYSQLUSER, $MYSQLHOST, $MYSQLPASSWORD, $DB_NAME;

   $dbcnx = @mysql_connect( $MYSQLHOST, $MYSQLUSER, $MYSQLPASSWORD);

   if (!$dbcnx)
   {
      if( $no_errors ) return false;
      error("mysql_connect_failed");
   }

   if (! @mysql_select_db($DB_NAME) )
   {
      mysql_close( $dbcnx);
      $dbcnx= 0;
      if( $no_errors ) return false;
      error("mysql_select_db_failed");
   }

   return true;
}

?>
