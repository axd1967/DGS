<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

$is_down = false;
$is_down_message = "Sorry, dragon is down for maintenance at the moment,"
                 . " please return in an hour or so."
                 . " Don't worry: the clocks are frozen until the server restarts";


function setTZ( $tz='GMT')
{
   static $curtz;
   if( !@$curtz ) $curtz='GMT'; //default
   $res= $curtz;
   if( is_string( $tz) && !empty( $tz) )
   {
      if( !function_exists('date_default_timezone_set')
            or !date_default_timezone_set( $tz) )
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

$NOW = time() + (int)$timeadjust;


$session_duration = 3600*12*61; // 1 month
$tick_frequency = 12; // ticks/hour


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

//used in daily_cron.php
define('DAYS_NEW_END', 4*7); // four weeks [days]
$new_end = DAYS_NEW_END *24*3600;  // four weeks [secs]


if ( get_magic_quotes_gpc() )
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
      if( $name=='handle' or $name=='sessioncode' or substr($name,0,5)=='prefs' )
         $cookie = arg_stripslashes((string)@$_COOKIE[$name]);
   }
*/
   return $cookie;
}

// Returns value for passed varname $name or else $default if varname not set or if value is invalid
//    (invalid = not an element of the optional list containing the valid values)
function get_request_arg( $name, $def='', $list=NULL)
{
   $val = (isset($_REQUEST[$name]) ? arg_stripslashes($_REQUEST[$name]) :
         //$HTTP_REQUEST_VARS does not exist
         $def) ;
   if (is_array($list) && !is_array($val))
   {
      if (!array_key_exists( (string) $val, $list) )
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

   $accept_langcodes = explode( ',', @$_SERVER['HTTP_ACCEPT_LANGUAGE'] );
   $accept_charset = strtolower(trim(@$_SERVER['HTTP_ACCEPT_CHARSET']));

   $current_q_val = -100;
   $language = NULL;

   foreach( $accept_langcodes as $lang )
   {
      @list($browsercode, $q_val) = explode( ';', trim($lang));

      $q_val = preg_replace( '/q=/i', '', trim($q_val));
      if( empty($q_val) or !is_numeric($q_val) )
         $q_val = 1.0;
      if( $current_q_val >= $q_val )
         continue;

      // Normalization for the array_key_exists() matchings
      $browsercode = strtolower(trim($browsercode));
      while( $browsercode && !array_key_exists($browsercode, $known_languages))
      {
         $tmp = strrpos( $browsercode, '-');
         if( !is_numeric($tmp)  or $tmp < 2 )
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
function recover_language( $player_row=null)
{
//see also:   include_once( "translations/known_languages.php" );
   global $language_used, $encoding_used, $known_languages;
   if( !empty( $language_used ) ) //from a previous call
      $language = $language_used;
   else //first call
   {
      if( !isset($known_languages) )
         if( file_exists( "translations/known_languages.php") )
            include_once( "translations/known_languages.php" );
      if( !isset($known_languages) )
         $known_languages = array();

      if( isset($_GET['language']) )
         $language = (string)$_GET['language'];
      else if( isset($player_row['Lang']) )
         $language = (string)$player_row['Lang'];
      else
         $language = 'C';

      if( empty($language) or $language == 'C' )
         $language = get_preferred_browser_language();

      if( empty($language) or $language == 'en' )
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
            reset($known_languages[$browsercode]);
            $encoding_used = @key($known_languages[$browsercode]);
         }
         if( !@$encoding_used )
            $encoding_used = LANG_DEF_CHARSET;
         if( $language != 'N' )
            $language = $browsercode.LANG_CHARSET_CHAR.$encoding_used;
      }
      $language_used = $language;
   }

   return $language; //here $language_used == $language
}

?>
