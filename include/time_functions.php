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

$NOW = time() + (int)$timeadjust;

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
   if( $clock_used == -1 ) return 0; // On vacation

   $result = mysql_query( "SELECT Ticks FROM Clock WHERE ID=$clock_used" );
   if( mysql_num_rows( $result ) != 1 )
      error("mysql_clock_ticks", true);

   $row = mysql_fetch_row($result);
   return $row[0];
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

   if( $main > 0 or $byoper < 0 ) // entering byoyomi
   {
      $byotime = $startbyotime;
      $byoper = $startbyoper;
   }

   if( $byotype == 'JAP' )
   {
      $byoper -= (int)(($startbyotime + $elapsed - $byotime)/$startbyotime);
      if( !$has_moved )
         $byotime = mod($byotime-$elapsed-1, $startbyotime)+1;

      if( $byoper < 0 )
         $byotime = $byoper = 0;  // time is up;
   }
   else if( $byotype == 'CAN' ) // canadian byoyomi
   {
      if( $has_moved )
         $byoper--; // byo stones;

      $byotime -= $elapsed;

      if( $byotime <= 0 )
         $byotime = 0;
      else if( $byoper <= 0 ) // get new stones;
      {
         $byotime = $startbyotime;
         $byoper = $startbyoper;
      }

   }
   else if( $byotype == 'FIS' )
   {
      $byotime = $byoper = 0;  // time is up;
   }

   $main = 0;
}

function echo_time($hours)
{
   if( $hours <= 0 )
      return '-';

   $days = (int)($hours/15);
   if( $days > 0 )
   {
      if( $days == 1 )
         $str = '1&nbsp;' . T_('day');
      else
         $str = $days .'&nbsp;' . T_('days');
   }

   $h = $hours % 15;
   if( $h > 0 )
   {
      if( $days > 0 )
         $str .='&nbsp;' . T_('and') . '&nbsp;';

      if( $h == 1 )
         $str .= '1&nbsp;' . T_('hour');
      else
         $str .= $h . '&nbsp;' . T_('hours');
   }

   return $str;
}

function echo_time_limit($Maintime, $Byotype, $Byotime, $Byoperiods)
{
   $str = '';
   if ( $Maintime > 0 )
      $str = echo_time( $Maintime );

   if( $Byotime <= 0 )
         $str .= ' ' . T_('without byoyomi');
      else if( $Byotype == 'FIS' )
      {
         $str .= ' ' . sprintf( T_('with %s extra per move'), echo_time($Byotime) );
      }
      else
      {
         if ( $Maintime > 0 )
            $str .= ' + ';
         $str .= echo_time($Byotime);
         $str .= '/' . $Byoperiods . ' ';

         if( $Byotype == 'JAP' )
            $str .= T_('periods') . ' ' . T_('Japanese byoyomi');
         else
            $str .= T_('stones') . ' ' . T_('Canadian byoyomi');
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
