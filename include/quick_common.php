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

$is_down = false;
$is_down_message = "Sorry, dragon is down for maintenance at the moment, " .
                   "please return in an hour or so.";

if( !isset($quick_errors) )
   $quick_errors = false;

$timeadjust = 0;
if( @is_readable( "timeadjust.php" ) )
   include_once( "timeadjust.php" );
if( !is_numeric($timeadjust) )
   $timeadjust = 0;

$NOW = time() + (int)$timeadjust;


$session_duration = 3600*12*61; // 1 month
$tick_frequency = 12; // ticks/hour


//a $_REQUEST['handle'] will not overlap $_COOKIE['cookie_handle']
//define('COOKIE_PREFIX', 'cookie_');
define('COOKIE_PREFIX', '');

//used in quick_status.php
define("FOLDER_NEW", 2);

//used in daily_cron.php
$new_end =  4*7*24*3600;  // four weeks

?>