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

$is_down = false;
$is_down_message = "Sorry, dragon is down for maintenance at the moment, " .
                   "please return in an hour or so.";

if( !isset($quick_errors) )
   $quick_errors = false;

function quick_error($string) //Short one line message
{
   echo "\n#Error: " . trim(ereg_replace( "[\x01-\x20]+", " ", $string));
   exit;
}

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
//define('COOKIE_PREFIX', '');

//used in quick_status.php
define("FOLDER_NEW", 2);

//used in daily_cron.php
$new_end =  4*7*24*3600;  // four weeks


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
   if( !$cookie ) //compatibility with old cookies: to be removed in a while
   {
      if( $name=='handle' or $name=='sessioncode' or substr($name,0,5)=='prefs' )
         $cookie = arg_stripslashes((string)@$_COOKIE[$name]);
   }
   return $cookie;
}

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