<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once( "include/error_functions.php" );
//@set_time_limit(0); //does not work with safe_mode

// following switches can be defined in config-local for test-systems
if( !defined('DBG_QUERY') )
   define('DBG_QUERY', 0); //0=no-debug, 1=print-query, 2=print-retry-count
if( !defined('DBG_TEST') )
   define('DBG_TEST', 0); //0=no-debug, 1=allow-some-stuff-for-testing
if( !defined('DBG_RATING') )
   define('DBG_RATING', 0); //0=no-debug, 1=print-rating-calculations


// fetch-types for mysql_single_fetch-function
//   PHP has funcs: mysql_fetch_(array|assoc|field|lengths|object|row)
define('FETCHTYPE_ARRAY', 'array');
define('FETCHTYPE_ASSOC', 'assoc');
define('FETCHTYPE_ROW',   'row');

global $dbcnx; //PHP5
$dbcnx = 0;


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
   header('Expires: ' . gmdate(GMDATE_FMT, $expire)); // HTTP/1.0 (replaced with max-age)
   header('Last-Modified: ' . gmdate(GMDATE_FMT, $stamp));
   if( !$expire || $expire<=$NOW )
      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); // HTTP/1.1
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
   if( @$GLOBALS['is_down'] )
      return true;

   $uid = (int)$uid;
   $ip = (string)@$_SERVER['REMOTE_ADDR'];
   $query = "INSERT INTO Adminlog SET uid='$uid'"
                  .", Handle='".mysql_addslashes($handle)."'"
                  .", Message='".mysql_addslashes($err)."'"
                  .", IP='".mysql_addslashes($ip)."'" ; //+ Date= timestamp
   $result = db_query( 'connect2mysql.admin_log', $query );
   return $result;
}

function writeIpStats( $page )
{
   global $player_row, $NOW;

   $ip = @$_SERVER['REMOTE_ADDR'];
   if( !@$GLOBALS['is_down'] && !empty($ip) )
   {
      $uid = (int)@$player_row['ID'];
      $sql_ip = mysql_addslashes($ip);
      $sql_page = mysql_addslashes($page);

      db_query( "writeIpStats.insert($uid,$page,$ip)",
         "INSERT INTO IpStats (uid,Page,IP,Counter,Lastchanged) VALUES ($uid,'$sql_page','$sql_ip',1,FROM_UNIXTIME($NOW)) " .
         "ON DUPLICATE KEY UPDATE Page=VALUES(Page), Counter=Counter+1, Lastchanged=VALUES(Lastchanged)" );
   }
}//writeIpStats

// IMPORTANT NOTE:
//   in general, position of db_close() must take into account,
//   that texts may contain DGS-markup still needing db-access
function db_close()
{
   global $dbcnx;
   if( DBG_QUERY>1 ) error_log("db_close(): dbcnx=[$dbcnx]");
   if( $dbcnx ) // $dbcnx is a resource
      @mysql_close( $dbcnx);
   $dbcnx= 0;
}

function connect2mysql($no_errors=false)
{
   global $dbcnx;

   // user can stop the output, but not the script
   // Because we will use MySQL, this will help to complete the *multiple queries* transactions.
   //
   // IMPORTANT NOTE:
   //       use HOT-sections with ta_begin/ta_end when writing into multiple tables
   //       to avoid "transaction"-breaks, but allow users to stop requests (slow queries)
   //
   //$old_ignore = @ignore_user_abort(true);
   //@ignore_user_abort($old_ignore);

   $retry = DB_CONNECT_RETRY_COUNT; //retry count = $retry+1 attempts to connect
   $rcnt = 0;
   for(;;)
   {
      $rcnt++;
      $dbcnx = @mysql_connect( MYSQLHOST, MYSQLUSER, MYSQLPASSWORD);
      if( $dbcnx ) break; // got connection
      if( --$retry < 0 ) break; // retry count reached (don't sleep on last loop)

      //max_user_connections: Error: 1203 SQLSTATE: 42000 (ER_TOO_MANY_USER_CONNECTIONS)
      if( mysql_errno() != 1203 ) break;

      //delay in micro-secs, needs PHP5 for Win-server
      usleep(1000 * DB_CONNECT_RETRY_SLEEP_MS);
   }

   if( !$dbcnx )
   {
      $err= 'mysql_connect_failed';
      if( $no_errors ) return $err;
      error($err); //error() with no err_log(), because no DB, see need_db_errorlog()-func
   }

   if( !@mysql_select_db(DB_NAME) )
   {
      @mysql_close( $dbcnx);
      $dbcnx= 0;
      $err= 'mysql_select_db_failed';
      if( $no_errors )
         return $err;
      error($err); //TODO: error() with no err_log(), because no DB
   }

   if( DBG_QUERY>1 ) error_log("connect2mysql($no_errors): dbcnx=[$dbcnx] on attempt #$rcnt/".DB_CONNECT_RETRY_COUNT);

   return false;
} //connect2mysql


global $level_ignore_user_abort; //PHP5
$level_ignore_user_abort = 0;

// used for multi-table transaction-start (no real DB-TA, but at least not broken by user-abort)
// nested call keeps first state
function ta_begin()
{
   global $old_ignore_user_abort, $level_ignore_user_abort;
   if( $level_ignore_user_abort < 0 )
      $level_ignore_user_abort = 0;
   if( $level_ignore_user_abort++ == 0 )
      $old_ignore_user_abort = @ignore_user_abort(true);
   if( DBG_QUERY ) error_log("ta_begin(): level=[$level_ignore_user_abort], old=$old_ignore_user_abort");
   return $old_ignore_user_abort;
}

// used for multi-table transaction-end (no real DB-TA, but at least not broken by user-abort)
// nested call keeps first state
function ta_end( $new_ignore_user_abort=null )
{
   global $old_ignore_user_abort, $level_ignore_user_abort;
   if( is_null($new_ignore_user_abort) )
      $new_ignore_user_abort = $old_ignore_user_abort;
   if( DBG_QUERY ) error_log("ta_end(): level=[$level_ignore_user_abort], new=$new_ignore_user_abort");
   if( --$level_ignore_user_abort <= 0 )
      @ignore_user_abort((bool)$new_ignore_user_abort);
   $old_ignore_user_abort = $new_ignore_user_abort;
}

function db_lock( $debugmsg, $locktables )
{
   ta_begin(); // starting HOT-section
   return db_query( "db_lock($locktables).$debugmsg", 'LOCK TABLES '.$locktables );
}

// note: ending session implicitly unlocks all tables
function db_unlock()
{
   $result = db_query( "db_unlock()", 'UNLOCK TABLES' );
   ta_end(); // ending HOT-section
   return $result;
}

function db_query( $debugmsg, $query, $errorcode='mysql_query_failed' )
{
   //echo $debugmsg.'.db_query='.$query.'<br>';
   if( DBG_QUERY ) error_log("db_query($debugmsg,$errorcode): query=[$query]");
   //for debug: $result = ( preg_match( "/^(insert|update|delete)/i", $query )) ? 1 : mysql_query($query);
   if( @$GLOBALS['is_down'] )
   {
      $chk_pos = stripos($query, 'SELECT'); // SELECTs allowed if server down, but no write-queries
      if( $chk_pos === false || $chk_pos > 3 ) // query with SELECT may start with "(.." or some spaces
      {
         error('server_down', "db_query.check.no_select(): $debugmsg");
         return false;
      }
   }

   $result = mysql_query($query);
   if( $result )
      return $result;
   if( is_string($debugmsg) )
      error( $errorcode, $debugmsg.'='.$query);
   return false;
}

// Returns calculated rows from previous SELECT, or -1 on error
// needs preceeding SELECT with SQL_CALC_FOUND_ROWS, see also QuerySQL-class
// NOTE: only if allowed in config by ALLOW_SQL_CALC_ROWS
function mysql_found_rows( $debugmsg )
{
   if( !ALLOW_SQL_CALC_ROWS )
      return -1; // not allowed

   $found_rows = -1; // error
   $result = db_query( $debugmsg, 'SELECT FOUND_ROWS()' );
   if( $result )
   {
      $val = mysql_result($result, 0);
      if( $val >= 0 )
         $found_rows = $val;
      mysql_free_result($result);
   }
   return $found_rows;
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

// returns true, if at least $expected_rows have been found
function mysql_exists_row( $debugmsg, $query, $expected_rows=1 )
{
   $dbg_str = ( !is_string($debugmsg) ) ? false : $debugmsg.'.exists_row';
   $result = db_query( $dbg_str, $query);
   $exists_row = ( mysql_num_rows($result) >= $expected_rows );
   mysql_free_result($result);
   return $exists_row;
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
   switch( (int)strlen( $passwd_encrypted ) )
   {
      case 41: $method='PASSWORD'; break;
      case 40: $method='SHA1'; break;
      case 32: $method='MD5'; break;
      default:
         $method= (version_compare(MYSQL_VERSION, '4.1', '<') ? 'PASSWORD' : 'OLD_PASSWORD');
         break;
   }
   $given_passwd_encrypted =
      mysql_single_fetch( "check_password.get_password($method)",
               "SELECT $method('".mysql_addslashes($given_passwd)."')"
               ,FETCHTYPE_ROW )
         or error('mysql_query_failed', "check_password.get_password2($method)");

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
      db_query( "check_password.set_password($uhandle)",
           'UPDATE Players'
         . " SET Password=".PASSWORD_ENCRYPT."('".mysql_addslashes($given_passwd)."')"
            . ",Newpassword=''"
         . " WHERE Handle='".mysql_addslashes($uhandle)."' LIMIT 1" );
   }
   return true;
}

/*! \brief Returns query-part for field with IN-clause. */
function build_query_in_clause( $field, $arr, $is_string=true )
{
   $clause = '';
   if( count($arr) > 0 )
   {
      $arr_vals = array();
      foreach( $arr as $item )
      {
         $arr_vals[] = ( $is_string || !is_numeric($item) )
            ? "'" . mysql_addslashes($item) . "'"
            : $item;
      }
      $clause = "$field IN (" . implode(',', $arr_vals) . ")";
   }
   return $clause;
}

function bool_YN( $value )
{
   return ($value) ? 'Y' : 'N';
}


 /*!
  * \class UpdateQuery
  *
  * \brief Class to build SQL-statement to update db-table.
  */
class UpdateQuery
{
   var $table; // only info, checked on merging
   var $updates; // [ upd-part, ... ]

   function UpdateQuery( $table )
   {
      $this->table = $table;
      $this->updates = array();
   }

   function has_updates()
   {
      return count($this->updates);
   }

   function upd_txt( $field, $value )
   {
      $this->updates[] = sprintf( "%s='%s'", $field, mysql_addslashes($value) );
   }

   function upd_num( $field, $value )
   {
      $this->updates[] = "$field=$value";
   }

   /*! \brief For now same as upd_num, but RAW means, just put value as-is into SQL-query. */
   function upd_raw( $field, $value )
   {
      $this->updates[] = "$field=$value";
   }

   function upd_time( $field, $value=null )
   {
      if( is_null($value) )
         $value = $GLOBALS['NOW'];
      $this->updates[] = "$field=FROM_UNIXTIME($value)";
   }

   function upd_bool( $field, $value )
   {
      $this->updates[] = sprintf( "%s='%s'", $field, ($value ? 'Y' : 'N') );
   }

   function merge( $upd_query )
   {
      if( $this->table !== $upd_query->table )
         error('internal_error', "UpdateQuery.merge.conflict({$this->table},{$upd_query->table})");
      foreach( $upd_query->updates as $update )
         $this->updates[] = $update;
   }

   /*! \brief Returns SQL-part to INSERT or UPDATE fields of this UpdateQuery-instance. */
   function get_query( $sep_start=false, $sep_end=false )
   {
      return ($sep_start ? ',' : '') . implode(', ', $this->updates) . ($sep_end ? ',' : '');
   }

} // end of 'UpdateQuery'

?>
