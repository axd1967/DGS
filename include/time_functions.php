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

$TranslateGroups[] = "Common";

define('NIGHT_LEN', 9); //may be from 0 to 24 hours
define('WEEKEND_CLOCK_OFFSET', 100);
define('VACATION_CLOCK', -1); // keep it < 0

function getmicrotime()
{
   list($usec, $sec) = explode(" ",microtime());
   return ((float)$usec + (float)$sec);
}

function unix_timestamp($date)
{
   $pattern = "/(19|20)(\d{2})-(\d{1,2})-(\d{1,2}) (\d{1,2}):(\d{1,2}):(\d{1,2})/";
   $m = preg_match ($pattern, $date, $matches);

   if(empty($date) || $date == "0000-00-00" || !$m)
   {
      return NULL;
   }

   list($whole, $y1, $y2, $month, $day, $hour, $minute, $second) = $matches;
   return mktime($hour,$minute,$second,$month,$day,$y1.$y2);
}


// >>> Caution: the result depends of the current timezone (see setTZ())
function get_clock_used($nightstart)
{
/***
 * originally:  return gmdate('G', mktime( $nightstart,0,0));
 * but because ($nightstart,0,0) could fall in the one hour gap
 * when DST is active, mktime() can return undefined result.
 * must ALWAYS return an integer 0 <= n < 24   => clock ID
 ***/
   $d= date("d");
   $m= date("m");
   $y= date("Y");
   $n= -1;
   for($i=0; $i<6; $i++) //within the 6 next days,
   { //try to find two days with the same result
      $o= $n;
      $n= mktime($nightstart,0,0,$m,$d+$i,$y);
      if( $n<0 ) continue;
      $n= gmdate('G', $n); //hour without leading zeros. 0..23
      if( $n === $o ) break;
   }
   return (max(0, (int)$n) % 24); //ALWAYS integer 0..23
}

function get_clock_ticks($clock_used)
{
   if( $clock_used < 0) // VACATION_CLOCK
      return 0; // On vacation

   $row= mysql_single_fetch( 'time_functions.get_clock_ticks',
                  "SELECT Ticks FROM Clock WHERE ID=$clock_used" );
   if( $row )
      return (int)@$row['Ticks'];
   error('mysql_clock_ticks', 'time_functions.get_clock_ticks:'.$clock_used);
}

function ticks_to_hours($ticks)
{
   //returns the greatest integer within [0 , $ticks/TICK_FREQUENCY[
   return ( $ticks > TICK_FREQUENCY ? floor(($ticks-1) / TICK_FREQUENCY) : 0 );
}

function time_remaining( $hours, &$main, &$byotime, &$byoper
   , $startmaintime, $byotype, $startbyotime, $startbyoper, $has_moved)
{
   $elapsed = $hours;

   if( $main > $elapsed ) // still have main time left
   {
      $main -= $elapsed;

      if( $has_moved && $byotype == 'FIS' )
         $main = min($startmaintime, $main + $startbyotime);

      return;
   }

   if( $main > 0 )
      $elapsed -= $main;
   $main = 0;

   switch((string)$byotype)
   {
      case("FIS"):
      {
         $byotime = $byoper = 0;  // time is up
      }
      break;
     
      case("JAP"):
      {
         if( $startbyotime <= 0 )
         {
            $byotime = $byoper = 0; // no byoyomi, time is up
            break;
         }

         if( $byoper < 0 ) // entering byoyomi
         {
            $byotime = $startbyotime;
            $byoper = $startbyoper-1;
         }

         /***
          * with B=$byotime, P=$byoper, S=$startbyotime, E=$elapsed
          *  and b=new byotime, p=new byoper
          * we have:
          *  A = B + P*S   //old amount of time (0 < B <= S)
          *  a = b + p*S   //new amount of time (0 < b <= S)
          *  a = A - E
          * then:
          *  a = A - E = B + P*S - E
          *  p = (a - b)/S = (B + P*S - E - b)/S
          * p must be big enough to keep (0 < b <= S)
          *  p = ceil((B + P*S - E)/S) -1 = ceil((B - E)/S) + P -1
          *  d = P-p = -ceil((B - E)/S) +1 = floor((E - B)/S) +1
          *  b = a - p*S = B + P*S - E - p*S = B - E + d*S
          * finally:
          *  d = floor((S + E - B)/S)
          *  $byoper-= d
          *  $byotime-= E - d*S
          * because E and (S-B) are positive integers, the (int) cast
          *  (which rounds towards zero) may be used instead of floor()
          ***/
         $deltaper = (int)(($startbyotime + $elapsed - $byotime)/$startbyotime);
         $byoper -= $deltaper;

         if( $byoper < 0 )
            $byotime = $byoper = 0;  // time is up
         else if( $has_moved )
            $byotime = $startbyotime;
         else
            $byotime-= $elapsed - $deltaper*$startbyotime;
      }
      break;

      case("CAN"):
      {
         if( $startbyoper <= 0 )
         {
            $byotime = $byoper = 0; // no byoyomi, time is up
            break;
         }

         if( $byoper < 0 ) // entering byoyomi
         {
            $byotime = $startbyotime;
            $byoper = $startbyoper;
         }

         $byotime -= $elapsed;

         if( $byotime <= 0 )
            $byotime = $byoper = 0;  // time is up
         else if( $has_moved )
         {
            $byoper--; // byo stones
            if( $byoper <= 0 ) // get new stones
            {
               $byotime = $startbyotime;
               $byoper = $startbyoper;
            }
         }
      }
      break;
   }
}

function echo_day( $days, $keep_english=false, $short=false)
{
   if( $short && $keep_english )
      return $days . 'd';
   $T_= ( $keep_english ? 'fnop' : 'T_' );
   if( $short )
      return $days . ( abs($days) <= 1 ? $T_('day#short') : $T_('days#short') );
   return $days .'&nbsp;' . ( abs($days) <= 1 ? $T_('day') : $T_('days') );
} //echo_day

function echo_hour( $hours, $keep_english=false, $short=false)
{
   if( $short && $keep_english )
      return $hours . 'h';
   $T_= ( $keep_english ? 'fnop' : 'T_' );
   if( $short )
      return $hours . ( abs($hours) <= 1 ? $T_('hour#short') : $T_('hours#short') );
   return $hours .'&nbsp;' . ( abs($hours) <= 1 ? $T_('hour') : $T_('hours') );
} //echo_hour

function echo_time( $hours, $keep_english=false, $short=false)
{
   if( $hours <= 0 )
      return NO_VALUE;

   $T_= ( $keep_english ? 'fnop' : 'T_' );

   $h = $hours % 15;
   $days = ($hours-$h) / 15;
   if( $days > 0 )
      $str = echo_day( $days, $keep_english, $short);
   else
      $str = '';

   if( $h > 0 ) //or $str == '' )
   {
      if( $str > '' )
         $str.= ( $short ? '&nbsp;' : '&nbsp;' . $T_('and') . '&nbsp;');

      $str.= echo_hour( $h, $keep_english, $short);
   }

   return $str;
} //echo_time

function echo_onvacation( $days, $keep_english=false, $short=false)
{
   //return echo_day(floor($days)).' '.T_('left#2');
   $hours= round($days*24);
   if( $hours <= 0 )
      return NO_VALUE;

   $T_= ( $keep_english ? 'fnop' : 'T_' );

   $h = $hours % 24;
   $days = ($hours-$h) / 24;
   if( $days > 0 )
      $str = echo_day( $days, $keep_english, $short);
   else
      $str = '';

   if( $h > 0 )
   {
      if( $str > '' )
         $str.= ( $short ? '&nbsp;' : '&nbsp;' . $T_('and') . '&nbsp;');

      $str.= echo_hour( $h, $keep_english, $short);
   }

   return $str.' '.$T_('left#2');
} //echo_onvacation

function echo_byotype( $byotype, $keep_english=false, $short=false)
{
   if( $short )
      return substr($byotype, 0, 1);
      
   $T_= ( $keep_english ? 'fnop' : 'T_' );
   
   switch( (string)$byotype )
   {
      case 'JAP':
         return $T_('Japanese byoyomi');
      case 'CAN':
         return $T_('Canadian byoyomi');
      case 'FIS':
         return $T_('Fischer time');
   }

   return '';
} //echo_byotype

function echo_time_limit( $maintime, $byotype, $byotime, $byoper
                  , $keep_english=false, $short=false, $btype=true)
{
   $T_= ( $keep_english ? 'fnop' : 'T_' );
   
   $str = ''; 

   if( $btype )
      $str.= echo_byotype( $byotype, $keep_english, $short) . ': ';

   if( $maintime > 0 )
      $str.= echo_time( $maintime, $keep_english, $short) . ' ';

   if( $byotype == 'FIS' )
   {
      if( $short )
         $str.= '+ ' . echo_time( $byotime, $keep_english, $short);
      else
         $str.= sprintf( $T_('with %s extra per move')
                     , echo_time( $byotime, $keep_english, $short) );
   }
   else
   {
      if( $byotime <= 0 )
      {
         if( !$short )
            $str.= $T_('without byoyomi');
      }
      else if( $short )
      {
         if( $maintime > 0 )
            $str.= '+ ';

         $str.= echo_time( $byotime, $keep_english, $short);
         if( $byotype == 'JAP' )
         {
            $str.= ' * ' . $byoper;
         }
         else //if( $byotype == 'CAN' )
         {
            $str.= ' / ' . $byoper;
         }
      }
      else
      {
         if( $maintime > 0 )
            $str.= '+ ';

         if( $byotype == 'JAP' )
         {
            $str.= sprintf( $T_('%s per move and %s extra periods')
               , echo_time( $byotime, $keep_english, $short), $byoper);
         }
         else //if( $byotype == 'CAN' )
         {
            $str.= sprintf( $T_('%s per %s stones')
               , echo_time( $byotime, $keep_english, $short), $byoper);
         }
      }
   }

   return $str;
} //echo_time_limit


function echo_time_remaining( $maintime, $byotype, $byotime, $byoper
      , $startbyotime, $keep_english=false, $short=false, $btype=false)
{
   $T_= ( $keep_english ? 'fnop' : 'T_' );

   $str = '';

   if( $btype )
      $str.= echo_byotype( $byotype, $keep_english, $short) . ': ';

   if( $maintime > 0 )
   {
      $str.= echo_time( $maintime, $keep_english, $short);
   }
   else if( $byotype == 'FIS' || $byotime <= 0 )
   {
      if( $short )
         $str.= NO_VALUE;
      else
         $str.= $T_('The time is up');
   }
   else
   {
      if( !$short )
         $str.= $T_('In byoyomi') . ' ';

      if( $byotype == 'JAP' ) //now, it's like if $byotime was the $maintime
         $str.= echo_time_limit( $byotime, $byotype, $startbyotime, $byoper
                  , $keep_english, $short, false);
      else //if( $byotype == 'CAN' )
         $str.= echo_time_limit( -1, $byotype, $byotime, $byoper
                  , $keep_english, $short, false);
   }

   return $str;
} //echo_time_remaining

function time_convert_to_hours($time, $unit)
{
   $time = (int)$time;
   if( $unit != 'hours' )
      $time *= 15;
   if( $unit == 'months' )
      $time *= 30;
   return $time;
} //time_convert_to_hours

function time_convert_to_longer_unit(&$time, &$unit)
{
   if( $unit == 'hours' && $time % 15 == 0 )
   {
      $unit = 'days';
      $time /= 15;
   }

   if( $unit == 'days' && $time % 30 == 0 )
   {
      $unit = 'months';
      $time /= 30;
   }
}
?>
