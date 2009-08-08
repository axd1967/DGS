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

require_once( "include/connect2mysql.php" );
require_once( "include/error_functions.php" );

// $is_down can be overriden for maintenance-allowed users (in config-local.php)
// $is_maintenance is not changed (can be used to indicate maintenance-mode)
$is_down = false;
$is_down_message = "Sorry, dragon is down for maintenance at the moment,"
                 . " please return in an hour or so.<br>\n"
                 . " Don't worry: the clocks are frozen until the server restarts";
$is_maintenance = $is_down;

define('DGS_VERSION', '1.0.15');


// options settable by admins (user-capabilities)
// NOTE: also adjust admin_users.php and admin_show_users.php on adding new options
//define('ADMOPT_BYPASS_IP_BLOCK', 0x001); // by-passes blocked IP for user
define('ADMOPT_DENY_LOGIN',      0x002); // deny: server usage, login
define('ADMOPT_DENY_EDIT_BIO',   0x004);
//define('ADMOPT_DENY_START_GAME', 0x008); // deny: new/join game in waitroom, invite; being invited allowed
//define('ADMOPT_DENY_SEND_MSG',   0x010);
//define('ADMOPT_DENY_UPD_FORUM',  0x020); // deny: new/edits in forums
define('ADMOPT_SHOW_TIME',       0x040); // shows time in page bottom bar
define('ADMOPT_HIDE_BIO',        0x080); // hide user bio
define('ADMOPT_FGROUP_ADMIN',    0x100); // user can see ADMIN-forums
define('ADMOPT_FGROUP_DEV',      0x200); // user can see DEV-forums
define('ADMOPT_DENY_VOTE',       0x400); // deny: voting on features


function setTZ( $tz='GMT')
{
   static $curtz;
   if( !@$curtz ) $curtz='GMT'; //default
   $res= $curtz;
   if( is_string( $tz) && !empty( $tz) )
   {
      if( !function_exists('date_default_timezone_set')
            || !date_default_timezone_set( $tz) )
      {
         putenv( 'TZ='.$tz);
         //putenv('PHP_TZ='.$tz); //Does not seem to realize something
      }
      $curtz= $tz;
   }
   return $res;
}

setTZ('GMT'); //default

$timeadjust = 0;
if( @is_readable( "timeadjust.php" ) )
   include_once( "timeadjust.php" );
if( !is_numeric($timeadjust) )
   $timeadjust = 0;

// time() always returns time in UTC
$NOW = time() + (int)$timeadjust;

define('DATE_FMT', 'Y-m-d H:i');
define('DATE_FMT2', 'Y-m-d&\n\b\s\p;H:i');
define('DATE_FMT3', 'Y-m-d&\n\b\s\p;H:i:s');
define('DATE_FMT4', 'YmdHis');
define('DATE_FMT5', 'D, Y-m-d H:i T');
define('DATE_FMT6', 'd-M-Y H:i');
define('GMDATE_FMT', 'D, d M Y H:i:s \G\M\T');
define('DATE_FMT_YMD', 'Y-m-d');

define('SESSION_DURATION', 3600*12*61); // 1 month
define('TICK_FREQUENCY', 12); // ticks/hour (every 5 minutes)

define('SECS_PER_DAY', 86400);

//a $_REQUEST['handle'] will not overlap $_COOKIE['cookie_handle']
define('COOKIE_PREFIX', 'cookie_');

// don't set UHANDLE_NAME to 'userid' which is the handle of the
// user currently browsing the site (associated to 'passwd').
// This one, like 'uid', is an other user that the logged one.
define('UHANDLE_NAME', 'user'); //see quick_status.php and get_request_user()

/*! SQL-clause-part applied for Games.Status to select all running games. */
define('IS_RUNNING_GAME', " IN ('PLAY','PASS','SCORE','SCORE2')");


//used in quick_status.php and associated (wap, rss ...)
define('FOLDER_NEW', 2);

//used in daily_cron.php & others (to "mark" forum oldest unread entry)
define('FORUM_SECS_NEW_END', 7 * FORUM_WEEKS_NEW_END * SECS_PER_DAY); // [secs]


//FIXME: get_magic_quotes_gpc-func is deprecated and will be removed (soon)
// NOTE: also calling arg_stripslashes recursively can be exploited:
//       see http://talks.php.net/show/php-best-practices/26
if( get_magic_quotes_gpc() )
{
   function arg_stripslashes( $arg)
   {
      if( is_string( $arg) )
         return stripslashes($arg);
      if( !is_array( $arg) )
         return $arg;
      return array_map('arg_stripslashes',$arg);
   }
} else {
   function arg_stripslashes( $arg)
   {
      return $arg;
   }
}

function safe_getcookie($name)
{
   $cookie = arg_stripslashes((string)@$_COOKIE[COOKIE_PREFIX.$name]);
/*
//compatibility with old cookies: to be removed in a while (as partner code lines)
define('COOKIE_OLD_COMPATIBILITY', 1 && COOKIE_PREFIX>'');
   if( COOKIE_OLD_COMPATIBILITY && !$cookie )
   {
      if( $name=='handle' || $name=='sessioncode' || substr($name,0,5)=='prefs' )
         $cookie = arg_stripslashes((string)@$_COOKIE[$name]);
   }
*/
   return $cookie;
}

// Returns value for passed varname $name or else $default if varname not set or if value is invalid
//    (invalid = not an element of the optional list containing the valid values)
function get_request_arg( $name, $def='', $list=NULL)
{
   $val = (isset($_REQUEST[$name]))
      ? arg_stripslashes($_REQUEST[$name])
      : $def; //$HTTP_REQUEST_VARS does not exist
   if( is_array($list) && !is_array($val) )
   {
      if( !array_key_exists( (string) $val, $list) )
         $val = $def;
   }
   return $val;
}

// languages and encodings...

define('LANG_TRANSL_CHAR', ','); //do not use '-'
define('LANG_CHARSET_CHAR', '.'); //do not use '-'
//define('LANG_DEF_CHARSET', 'iso-8859-1'); //lowercase
define('LANG_DEF_CHARSET', 'utf-8'); //lowercase
//used as default load of translations:
define('LANG_DEF_LOAD', 'en'.LANG_CHARSET_CHAR.'iso-8859-1'); //lowercase

function fnop( $a)
{
   return $a;
}

function get_preferred_browser_language()
{
   global $known_languages;

   //for instance: "fr-FR,fr,en;q=0.8,es;q=0.5,en-us;q=0.3"
   $accept_langcodes = explode( ',', @$_SERVER['HTTP_ACCEPT_LANGUAGE'] );
   //for instance: "UTF-8,*"
   $accept_charset = strtolower(trim(@$_SERVER['HTTP_ACCEPT_CHARSET']));

   $current_q_val = -100;
   $language = NULL;

   foreach( $accept_langcodes as $lang )
   {
      @list($browsercode, $q_val) = explode( ';', $lang);

      $q_val = trim(preg_replace( '/q=/i', '', $q_val));
      if( empty($q_val) || !is_numeric($q_val) )
         $q_val = 1.0;
      if( $current_q_val >= $q_val )
         continue;

      // Normalization for the array_key_exists() matchings
      $browsercode = strtolower(trim($browsercode));
      if( $browsercode == 'n' )
      {
         $language = 'N';
         $current_q_val = $q_val;
         continue;
      }
      while( $browsercode && !array_key_exists($browsercode, $known_languages))
      {
         $tmp = strrpos( $browsercode, '-');
         if( !is_numeric($tmp) || $tmp < 2 )
         {
            $browsercode = '';
            break;
         }
         $browsercode = substr( $browsercode, 0, $tmp);
         $q_val-= 1.0;
      }
      if( !$browsercode )
         continue;

      if( $current_q_val >= $q_val )
         continue;

      $found = false;
      if( $accept_charset )
      {
         foreach( $known_languages[$browsercode] as $charenc => $langname )
         {
            //$charenc = strtolower($charenc); // Normalization
            if( strpos( $accept_charset, $charenc) !== false )
            {
               $found = true;
               break;
            }
         }
      }
      if( !$found )
      {  // No supporting encoding found. Take the first one anyway.
         reset($known_languages[$browsercode]);
         $charenc = key($known_languages[$browsercode]);
      }

      $language = $browsercode . LANG_CHARSET_CHAR . $charenc;
      $current_q_val = $q_val;
   }

   return $language;
}

//set the globals $language_used and $encoding_used
//if $player_row is absent, use the browser default settings
//called by include_translate_group()
function recover_language( $player_row=null) //must be called from main dir
{
//see also:   include_once( "translations/known_languages.php" );
   global $language_used, $encoding_used, $known_languages;
   if( !empty( $language_used ) ) //set by a previous call
      return $language_used;

   //else first call: find $language_used and $encoding_used
   if( !isset($known_languages) )
      if( file_exists( "translations/known_languages.php") )
         include_once( "translations/known_languages.php" ); //must be called from main dir
   if( !isset($known_languages) )
      $known_languages = array();

   if( isset($_REQUEST['language']) )
      $language = (string)$_REQUEST['language'];
   else if( isset($player_row['Lang']) )
      $language = (string)$player_row['Lang'];
   else
      $language = 'C';

   if( empty($language) || $language == 'C' )
      $language = get_preferred_browser_language();

   if( empty($language) || $language == 'en' )
      $language = LANG_DEF_LOAD;

   @list($browsercode,$encoding_used) = explode( LANG_CHARSET_CHAR, $language, 2);
   if( @$browsercode && !@$encoding_used )
   {
      if( isset($known_languages[$browsercode][LANG_DEF_CHARSET]) )
      {
         $encoding_used = LANG_DEF_CHARSET;
      }
      else if( isset($known_languages[$browsercode]) )
      {
         //get the first suitable encoding
         reset($known_languages[$browsercode]);
         $encoding_used = @key($known_languages[$browsercode]);
      }
      if( !@$encoding_used )
         $encoding_used = LANG_DEF_CHARSET;
      if( $language != 'N' )
         $language = $browsercode.LANG_CHARSET_CHAR.$encoding_used;
   }
   $language_used = $language;

   return $language_used;
}

// Throws error() with errorcode if current-IP is blocked and no user-ip-block-bypass is active
// param row: expect field AdminOptions, if null only check for IP
function error_on_blocked_ip( $errorcode, $row=null )
{
   //if( is_array($row) && (@$row['AdminOptions'] & ADMOPT_BYPASS_IP_BLOCK) )
      //return;

   if( is_blocked_ip() )
   {
      $userid = (is_array($row)) ? @$row['Handle'] : '???';
      admin_log( 0, $userid, $errorcode );
      error($errorcode);
   }
}

// Returns current IP, if used IP is blocked (listed in IP-blocklist set in config.php)
//   return '' if IP is ok
function is_blocked_ip( $ip=null, $arr_check=null )
{
   global $ARR_BLOCK_IPLIST;
   if( is_null($ip) )
      $ip = (string)@$_SERVER['REMOTE_ADDR'];
   if( is_null($arr_check) || !is_array($arr_check) )
      $arr_check = $ARR_BLOCK_IPLIST;

   // check simple IP-syntax in config-array: 1.2.3.4 (=ip)
   if( in_array( $ip, $arr_check ) )
      return $ip;

   // check IP-Syntax in config-array: 1.2.3.4/8 (=subnet), /.../ (=regex)
   foreach( $arr_check as $checkip )
   {
      if( $checkip[0] == '/' ) // regex
      {
         if( preg_match( $checkip, $ip ) )
            return $ip;
      }
      elseif( strpos( $checkip, '/' ) !== false ) // subnet
      {
         if ( check_subnet_ip( $checkip, $ip ) == 1 )
            return $ip;
      }
   }

   return '';
}

// Checks, if subnet matches IP, syntax: 1.2.3.4/8 or 1.2.3.4/32
// Return values: 1=ip-matches, 0=ip-not-matching, -1=subnet-syntax-error, -2=ip-syntax-error
function check_subnet_ip( $subnet, $ip )
{
   // NOTE: PHP-ints are always signed, so split IP and check on 24+8-bit-parts

   // split subnet
   $arrnet = array();
   if( !preg_match( "/^((\d+\.\d+\.\d+)\.(\d+))\/(\d+)$/", $subnet, $arrnet ) )
      return -1;
   $netbits = $arrnet[4];
   if( $netbits == 32 && strcmp($arrnet[1], $ip) == 0 )
      return 1; // ip matching subnet [ip/32]
   if( $netbits <= 0 || $netbits > 32 )
      return -1;

   // split ip
   $arrip  = array();
   if( !preg_match( "/^(\d+\.\d+\.\d+)\.(\d+)$/", $ip, $arrip ) )
      return -2;

   // match high 24-bit
   $ip_high  = ip2long("0.{$arrip[1]}");
   $net_high = ip2long("0.{$arrnet[2]}");
   if( $netbits < 24 )
   {
      $matchmask = 0xffffff ^ ((1 << (24 - $netbits)) - 1);
      if( ($ip_high & $matchmask) == ($net_high & $matchmask) )
         return 1;
   }
   elseif( ($ip_high & 0xffffff) != ($net_high & 0xffffff) )
   {// high 24-bits don't match
      return 0;
   }
   elseif( $netbits == 24 )
   {// high 24-bits match exactly
      return 1;
   }
   else
   {// match low 8-bit (netbits != 32, already checked above)
      $lowbits = $netbits - 24;
      $ip_low  = ip2long("0.0.0.{$arrip[2]}");
      $net_low = ip2long("0.0.0.{$arrnet[3]}");
      $matchmask = 0xff ^ ((1 << (8 - $lowbits)) - 1);
      if( ($ip_low & $matchmask) == ($net_low & $matchmask) )
         return 1;
   }

   return 0; // no match
}

?>
