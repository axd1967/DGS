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

$TranslateGroups[] = "Common";

$date_fmt = 'Y-m-d H:i';
$date_fmt2 = 'Y-m-d&\n\b\s\p;H:i';

define('VACATION_CLOCK', -1);
define('WEEKEND_CLOCK_OFFSET', 100);

function getmicrotime()
{
   list($usec, $sec) = explode(" ",microtime());
   return ((float)$usec + (float)$sec);
}

function unix_timestamp($date)
{
   $pattern = "/(19|20)(\d{2})-(\d{1,2})-(\d{1,2}) (\d{1,2}):(\d{1,2}):(\d{1,2})/";
   $m = preg_match ($pattern, $date, $matches);

   if(empty($date) or $date == "0000-00-00" or !$m)
   {
      return NULL;
   }

   list($whole, $y1, $y2, $month, $day, $hour, $minute, $second) = $matches;
   return mktime($hour,$minute,$second,$month,$day,$y1.$y2);
}


function get_clock_used($nightstart)
{
   return gmdate('G', mktime ($nightstart,0,0,date("m"),date("d"),date("Y")));
}

function get_clock_ticks($clock_used)
{
   if( $clock_used == VACATION_CLOCK or $clock_used == VACATION_CLOCK+WEEKEND_CLOCK_OFFSET )
      return 0; // On vacation

   if( $row=mysql_single_fetch(
            "SELECT Ticks FROM Clock WHERE ID=$clock_used"
     ) )
      return (int)$row[0];
   error("mysql_clock_ticks", $clock_used);
}


function time_remaining($hours, &$main, &$byotime, &$byoper, $startmaintime,
   $byotype, $startbyotime, $startbyoper, $has_moved)
{
   $elapsed = $hours;

   if( $main > $elapsed ) // still have main time left
   {
      $main -= $elapsed;

      if( $has_moved and $byotype == 'FIS' )
         $main = min($startmaintime, $main + $startbyotime);

      return;
   }

   $elapsed -= $main;

   switch($byotype)
   {
      case("FIS"):
      {
         $main = $byotime = $byoper = 0;  // time is up;
      }
      break;
     
      case("JAP"):
      {
   if( $main > 0 or $byoper < 0 ) // entering byoyomi
   {
            $main = 0;
      $byotime = $startbyotime;
         $byoper = $startbyoper-1;
   }

         //because $elapsed>=0 and ($startbyotime - $byotime)>=0, this is equal to:
         //$byoper -= floor(($elapsed - $byotime)/$startbyotime) +1;
         //$byoper += ceil(($byotime - $elapsed)/$startbyotime) -1;
      $byoper -= (int)(($startbyotime + $elapsed - $byotime)/$startbyotime);

      if( $byoper < 0 )
         $byotime = $byoper = 0;  // time is up;
         else if( $has_moved )
            $byotime = $startbyotime;
         else 
            $byotime = mod($byotime-$elapsed-1, $startbyotime)+1;
   }
      break;

      case("CAN"):
   {
         if( $main > 0 or $byoper < 0 ) // entering byoyomi
         {
            $main = 0;
            $byotime = $startbyotime;
            $byoper = $startbyoper;
         }

      $byotime -= $elapsed;

      if( $byotime <= 0 )
         $byotime = $byoper = 0;  // time is up;
         else if( $has_moved )
         {
            $byoper--; // byo stones;
            if( $byoper <= 0 ) // get new stones;
      {
         $byotime = $startbyotime;
         $byoper = $startbyoper;
      }
   }
   }
      break;
   }
}

function echo_day($days)
{
   return $days .'&nbsp;' . ( abs($days) <= 1 ? T_('day') : T_('days') );
}

function echo_hour($hours)
{
   return $hours .'&nbsp;' . ( abs($hours) <= 1 ? T_('hour') : T_('hours') );
}

function echo_time($hours, $keep_english=false)
{
   if( $hours <= 0 )
      return '-';

   $T_= ( $keep_english ? 'fnop' : 'T_' );

   $days = (int)($hours/15);
   if( $days > 0 )
   {
      if( $days == 1 )
         $str = '1&nbsp;' . $T_('day');
      else
         $str = $days .'&nbsp;' . $T_('days');
   }
   else
         $str = '';

   $h = $hours % 15;
   if( $h > 0 )
   {
      if( $days > 0 )
         $str .='&nbsp;' . $T_('and') . '&nbsp;';

      if( $h == 1 )
         $str .= '1&nbsp;' . $T_('hour');
      else
         $str .= $h . '&nbsp;' . $T_('hours');
   }

   return $str;
}

function echo_time_limit($Maintime, $Byotype, $Byotime, $Byoperiods, $keep_english=false, $short=false)
{
   $T_= ( $keep_english ? 'fnop' : 'T_' );
   $str = '';
   if ( $Maintime > 0 )
      $str = echo_time( $Maintime, $keep_english);

   if( $Byotime <= 0 )
         $str .= ' ' . $T_('without byoyomi');
   else if( $Byotype == 'FIS' )
      {
         $str .= ' ' . sprintf( $T_('with %s extra per move'), echo_time($Byotime, $keep_english) );
         if( !$short )
            $str .= ' - ' . $T_('Fischer time');
      }
   else
      {
         if ( $Maintime > 0 )
            $str .= ' + ';
         $str .= echo_time($Byotime, $keep_english);

         if( $Byotype == 'JAP' )
         {
            $str .= ' * ' . $Byoperiods . ' ' . $T_('periods');
            if( !$short )
               $str .= ' - ' . $T_('Japanese byoyomi');
         }
         else
         {
            $str .= ' / ' . $Byoperiods . ' ' . $T_('stones');
            if( !$short )
               $str .= ' - ' . $T_('Canadian byoyomi');
         }
      }

   return $str;
}

function time_convert_to_longer_unit(&$time, &$unit)
{
   if( $unit == 'hours' and $time % 15 == 0 )
   {
      $unit = 'days';
      $time /= 15;
   }

   if( $unit == 'days' and $time % 30 == 0 )
   {
      $unit = 'months';
      $time /= 30;
   }
}
?>
