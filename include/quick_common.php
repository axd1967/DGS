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

require_once( "include/connect2mysql.php" );
require_once( "include/error_functions.php" );

$is_down = false;
$is_down_message = "Sorry, dragon is down for maintenance at the moment,"
                 . " please return in an hour or so."
                 . " Don't worry: the clocks are frozen until the server restarts";


function setTZ( $tz='GMT')
{
   if( is_string( $tz) && !empty( $tz) )
   {
      if( !function_exists('date_default_timezone_set')
            or !date_default_timezone_set( $tz) )
      {
         putenv( 'TZ='.$tz);
         //putenv('PHP_TZ='.$tz); //Does not seems to realize something
      }
   }
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

?>
