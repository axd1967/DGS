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

$TranslateGroups[] = "Common";

$date_fmt = 'Y-m-d H:i';
$date_fmt2 = 'Y-m-d&\n\b\s\p;H:i';

define('WEEKEND_CLOCK_OFFSET', 100);
define('VACATION_CLOCK', -1-WEEKEND_CLOCK_OFFSET); // stay < 0 over weekend

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
// because mktime() can return undefined result if DST is active
   $d= date("d");
   $m= date("m");
   $y= date("Y");
   $n= -1;
   for($i=0; $i<5; $i++)
   {
      $o= $n;
      $n= mktime($nightstart,0,0,$m,$d+$i,$y);
      if( $n<0 ) continue;
      $n= gmdate('G', $n);
      if( $n === $o ) break;
   }
   return ((($n % 24) + 24) % 24);
}

function get_clock_ticks($clock_used)
{
   if( $clock_used < 0) // VACATION_CLOCK
      return 0; // On vacation

   if( $row=mysql_single_fetch(
            "SELECT Ticks FROM Clock WHERE ID=$clock_used"
     ) )
   {
      return (int)@$row['Ticks'];
   }
   error("mysql_clock_ticks", $clock_used);
}

function ticks_to_hours($ticks)
{
   global $tick_frequency;

   return ( $ticks > $tick_frequency ? floor(($ticks-1) / $tick_frequency) : 0 );
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
            if( $byotime == 0 )
            {
               $byoper = 0; // No byoyomi
               break;
            }
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

function echo_time($hours, $keep_english=false, $short=false)
{
   if( $hours <= 0 )
      return '---';

   $T_= ( $keep_english ? 'fnop' : 'T_' );

   $h = $hours % 15;
   $days = ($hours-$h) / 15;
   if( $days > 0 )
   {
      if( $days <= 1 )
         $str = '1' . ( $short ? 'd' : '&nbsp;' . $T_('day') );
      else
         $str = $days . ( $short ? 'd' : '&nbsp;' . $T_('days') );
   }
   else
         $str = '';

   if( $h > 0 ) //or $str == '' )
   {
      if( $str > '' )
         $str .= ( $short ? ',&nbsp;' : '&nbsp;' . $T_('and') . '&nbsp;');

      if( $h <= 1 )
         $str .= '1' . ( $short ? 'h' : '&nbsp;' . $T_('hour') );
      else
         $str .= $h . ( $short ? 'h' : '&nbsp;' . $T_('hours') );
   }

   return $str;
}

function echo_time_limit($Maintime, $Byotype, $Byotime, $Byoperiods, $keep_english=false, $short=false)
{
   $T_= ( $keep_english ? 'fnop' : 'T_' );
   $str = '';

   if( $Byotype == 'FIS' )
   {
      if( !$short ) $str .= $T_('Fischer time') . ', ';

      $str .= echo_time($Maintime, $keep_english, $short) . ' ' .
         sprintf( $T_('with %s extra per move'), echo_time($Byotime, $keep_english) );
   }
   else
   {
      $str .= echo_time($Maintime, $keep_english, $short);

      if( $Byotime <= 0 )
         if( !$short )
            $str .= ' ' . $T_('without byoyomi');
      else
      {
         $str .= ' + ' . echo_time($Byotime, $keep_english, $short);

         if( $Byotype == 'JAP' )
         {
            $str .= ' * ' . $Byoperiods . ' ' .
               ($Byoperiods == 1 ? $T_('period') : $T_('periods'));
            if( !$short )
               $str .= ' ' . $T_('Japanese byoyomi');
         }
         else
         {
            $str .= ' / ' . $Byoperiods . ' ' .
               ($Byoperiods == 1 ? $T_('stone') : $T_('stones'));
            if( !$short )
               $str .= ' ' . $T_('Canadian byoyomi');
         }
      }
   }
   return $str;
}

function echo_time_remaining($Byotype, $Maintime_left, $Byotime_left,
                             $Byoperiods_left, $short=false)
{
   $str = '';
   if( $Maintime_left > 0 )
   {
      $str .= echo_time($Maintime_left,false,$short);
   }
   else if( $Byotime_left > 0 )
   {
      if( !$short ) $str .= T_('In byoyomi') . ': ';
      if( $Byotype == 'JAP' )
      {
         $str .= echo_time($Byotime_left,false,$short) . ' * ' . $Byoperiods_left . ' ' .
            ($Byoperiods_left <= 1 ? T_('period') : T_('periods'));
      }
      else if( $Byotype == 'CAN' )
      {
         $str .= echo_time($Byotime_left,false,$short) . ' / ' . $Byoperiods_left . ' ' .
            ($Byoperiods_left <= 1 ? T_('stone') : T_('stones'));
      }
   }
   else
   {
      $str .= T_('The time is up');
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
