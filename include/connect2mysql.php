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
//@set_time_limit(0); //does not work with safe_mode

//because we will use MySQL, this will help to
//complete the *multiple queries* transactions.
@ignore_user_abort(true);


//At least needed when connect2mysql.php is used alone (as in quick_status.php):
function jump_to($uri, $absolute=false)
{
   global $HOSTBASE;

   $uri= str_replace( URI_AMP, URI_AMP_IN, $uri);
   session_write_close();
   @ignore_user_abort(false);
   if( connection_aborted() )
      exit;
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



if( function_exists('mysql_real_escape_string') ) //PHP >= 4.3.0
{
   function mysql_addslashes($str) {
      global $dbcnx;
      if( @$dbcnx )
      {
         //If no connection is found, an E_WARNING level warning is generated.
         //Warning: Can't connect to MySQL server on '...' in ... on line ...
         $e= error_reporting(E_ALL & ~(E_WARNING | E_NOTICE));
         $res= mysql_real_escape_string($str, $dbcnx);
         error_reporting($e);
         if( $res !== false )
            return $res;
         //error('mysql_query_failed','mysql_addslashes');
      }
      return mysql_escape_string($str);
   }
}
else
if( function_exists('mysql_escape_string') ) //PHP >= 4.0.3
{
   function mysql_addslashes($str) { return mysql_escape_string($str); }
}
else
{
   function mysql_addslashes($str) { return addslashes($str); }
}


function admin_log( $uid, $handle, $err)
{
   $uid = (int)$uid;
   $ip = (string)@$_SERVER['REMOTE_ADDR'];
   $query = "INSERT INTO Adminlog SET uid='$uid'"
                  .", Handle='".mysql_addslashes($handle)."'"
                  .", Message='".mysql_addslashes($err)."'"
                  .", IP='".mysql_addslashes($ip)."'" ;

   return ( mysql_query( $query )
            or error('mysql_query_failed','connect2mysql.admin_log') );
}


function mysql_single_fetch( $debugmsg, $query, $type='assoc')
{
   $result = mysql_query($query);
   if( $result == false )
   {
      if( $debugmsg !== false )
         error('mysql_query_failed', ((string)$debugmsg).'.single_fetch');
      return false;
   }
   if( mysql_num_rows($result) != 1 )
   {
      mysql_free_result($result);
      return false;
   }
   $type = 'mysql_fetch_'.$type;
   $row = $type($result);
   mysql_free_result($result);
   if( !is_array($row) )
      return false;
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


function check_password( $uhandle, $passwd, $new_passwd, $given_passwd )
{
   $given_passwd_encrypted =
      mysql_single_fetch( 'check_password',
               "SELECT PASSWORD ('".mysql_addslashes($given_passwd)."')"
               ,'row')
         or error('mysql_query_failed','check_password.get_password');

   $given_passwd_encrypted = $given_passwd_encrypted[0];

   if( $passwd != $given_passwd_encrypted )
   {
      // Check if there is a new password
      if( empty($new_passwd) or $new_passwd != $given_passwd_encrypted )
         return false;
   }

   if( !empty($new_passwd) )
   {
      mysql_query( 'UPDATE Players ' .
                   "SET Password='" . $given_passwd_encrypted . "', " .
                   'Newpassword=NULL ' .
                   "WHERE Handle='".mysql_addslashes($uhandle)."' LIMIT 1" )
         or error('mysql_query_failed','check_password.set_password');
   }

   return true;
}

?>
