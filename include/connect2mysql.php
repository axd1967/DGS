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

require_once( "include/config-local.php" );
//@set_time_limit(0); //does not work with safe_mode

//because we will use MySQL, this will help to
//complete the *multiple queries* transactions.
//@ignore_user_abort(true); //moved to connect2mysql(), also see jump_to()

// fetch-types for mysql_single_fetch-function
//   PHP has funcs: mysql_fetch_(array|assoc|field|lengths|object|row)
define('FETCHTYPE_ARRAY', 'array');
define('FETCHTYPE_ASSOC', 'assoc');
define('FETCHTYPE_ROW',   'row');


//At least needed when connect2mysql.php is used alone (as in quick_status.php):
function jump_to($uri, $absolute=false)
{
   $uri= str_replace( URI_AMP, URI_AMP_IN, $uri);
   session_write_close();
   //@ignore_user_abort(false);
   if( connection_aborted() )
      exit;
   //header('HTTP/1.1 303 REDIRECT');
   if( $absolute )
      header( "Location: " . $uri );
   else
      header( "Location: " . HOSTBASE . $uri );
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
   header('Expires: ' . gmdate(GMDATE_FMT, $expire));
   header('Last-Modified: ' . gmdate(GMDATE_FMT, $stamp));
   if( !$expire || $expire<=$NOW )
   {
      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); // HTTP/1.1
      header('Pragma: no-cache');                                              // HTTP/1.0
   }
}



//Because of mysql_real_escape_string(), mysql_addslashes()
// can't be used without a valid connection to mysql
if( function_exists('mysql_real_escape_string') ) //PHP >= 4.3.0
{
   function mysql_addslashes($str)
   {
      //If no connection is found, an E_WARNING level warning is generated.
      //Warning: Can't connect to MySQL server on '...' in ... on line ...
      //$e= error_reporting(E_ALL & ~(E_WARNING | E_NOTICE));
      $res= mysql_real_escape_string($str);
      if( $res === false )
      {
         //error('mysql_query_failed','mysql_addslashes');
         $res= mysql_escape_string($str); // deprecated-warning since PHP 4.3.0
      }
      //error_reporting($e);
      return $res;
   }
}
elseif( function_exists('mysql_escape_string') ) //PHP >= 4.0.3
{
   function mysql_addslashes($str)
   {
      //mysql_escape_string() is deprecated since version 4.3.0
      //Its use generate an E_WARNING level message
      //$e= error_reporting(E_ALL & ~(E_WARNING | E_NOTICE));
      $res= mysql_escape_string($str);
      //error_reporting($e);
      return $res;
   }
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
                  .", IP='".mysql_addslashes($ip)."'" ; //+ Date= timestamp
   $result = mysql_query($query)
      or error('mysql_query_failed','connect2mysql.admin_log');

   return $result;
}

function db_close()
{
   global $dbcnx;
   //error_log("db_close with dbcnx=$dbcnx");
   if( $dbcnx ) // $dbcnx is a resource
      @mysql_close( $dbcnx);
   $dbcnx= 0;
}

function connect2mysql($no_errors=false)
{
   global $dbcnx;

   // user can stop the output, but not the script
   $old_ignore = @ignore_user_abort(true);

   $i = 3; //retry count
   do
   {
      $dbcnx = @mysql_connect( MYSQLHOST, MYSQLUSER, MYSQLPASSWORD);
      if( $dbcnx )
         break;
      //max_user_connections: Error: 1203 SQLSTATE: 42000 (ER_TOO_MANY_USER_CONNECTIONS)
      if( mysql_errno() != 1203 )
         break;
      usleep(1000000); //delay in ms
   } while(--$i >= 0);

   if( !$dbcnx )
   {
      $err= 'mysql_connect_failed';
      if( $no_errors ) return $err;
      //TODO: error() with no err_log()
      error($err);
   }

   if( !@mysql_select_db(DB_NAME) )
   {
      @mysql_close( $dbcnx);
      $dbcnx= 0;
      $err= 'mysql_select_db_failed';
      if( $no_errors ) return $err;
      //TODO: error() with no err_log()
      error($err);
   }

   @ignore_user_abort((bool)$old_ignore);
   //error_log("connect2mysql($no_errors): dbcnx=$dbcnx on attempt #".(4-$i)."/3");

   return false;
}

function db_query( $debugmsg, $query)
{
   //echo $debugmsg.'.db_query='.$query.'<br>';
   $result = mysql_query($query);
   if( $result )
      return $result;
   if( is_string($debugmsg) )
      error('mysql_query_failed', $debugmsg.'='.$query);
   return false;
}

// arg: fetch_type (FETCHTYPE_...) controls which method to call: mysql_fetch_{$fetch_type}
function mysql_single_fetch( $debugmsg, $query, $fetch_type=FETCHTYPE_ASSOC )
{
   $dbg_str = ( !is_string($debugmsg) ) ? false : $debugmsg.'.single_fetch';
   $result = db_query( $dbg_str, $query);
   if( mysql_num_rows($result) != 1 )
   {
      mysql_free_result($result);
      return false;
   }
   $fetch_func = 'mysql_fetch_'.$fetch_type;
   $row = $fetch_func($result);
   mysql_free_result($result);
   if( !is_array($row) )
      return false;
   return $row;
}

// if( !$keyed ) $result = array( $col[0],...);
// else $result = array( $col[0] => $col[1],...);
function mysql_single_col( $debugmsg, $query, $keyed=false)
{
   $dbg_str = ( !is_string($debugmsg) ) ? false : $debugmsg.'.single_col';
   $result = db_query( $dbg_str, $query);
   if( mysql_num_rows($result) < 1 )
   {
      mysql_free_result($result);
      return false;
   }
   $column = array();
   $row = mysql_fetch_row($result);
   if( $keyed )
   {
      while( is_array($row) && count($row) >= 2 )
      {
         $column[$row[0]] = $row[1];
         $row = mysql_fetch_row($result);
      }
   }
   else
   {
      while( is_array($row) && count($row) >= 1 )
      {
         $column[] = $row[0];
         $row = mysql_fetch_row($result);
      }
   }
   mysql_free_result($result);
   if( count($column) > 0 ) //at least one value
      return $column;
   return false;
}

// Returns true, if encrypted passwords matches the given_passwd
// Returns SQL-encryption-func in method-var used to encrypt password:
//    PASSWORD, OLD_PASSWORD, SHA1, MD5
function check_passwd_method( $passwd_encrypted, $given_passwd, &$method)
{
   /*
      because, with MySQL> =4.1.0, the length of:
      - OLD_PASSWORD() is 16
      - (new_)PASSWORD() is 41
      - MD5() is 32
      - SHA1() is 40
      - others?
   */
   switch( strlen( $passwd_encrypted ) )
   {
      case 41: $method='PASSWORD'; break;
      case 40: $method='SHA1'; break;
      case 32: $method='MD5'; break;
      default: $method= (version_compare(MYSQL_VERSION, '4.1', '<') ? 'PASSWORD' : 'OLD_PASSWORD'); break;
   }
   $given_passwd_encrypted =
      mysql_single_fetch( 'check_password',
               "SELECT $method('".mysql_addslashes($given_passwd)."')"
               ,FETCHTYPE_ROW)
         or error('mysql_query_failed','check_password.get_password');

   return ($passwd_encrypted == $given_passwd_encrypted[0]);
}

// Checks and eventually fixes (old-format) password
function check_password( $uhandle, $passwd, $new_passwd, $given_passwd )
{
   if( !check_passwd_method( $passwd, $given_passwd, $method) )
   {
      // Check if there is a new password
      if( empty($new_passwd) ||
            !check_passwd_method( $new_passwd, $given_passwd, $method) )
         return false;
   }
   if( !empty($new_passwd) || $method != PASSWORD_ENCRYPT )
   {
      db_query( 'check_password.set_password',
           'UPDATE Players'
         . " SET Password=".PASSWORD_ENCRYPT."('".mysql_addslashes($given_passwd)."')"
            . ",Newpassword=''"
         . " WHERE Handle='".mysql_addslashes($uhandle)."' LIMIT 1" );
   }
   return true;
}

?>
