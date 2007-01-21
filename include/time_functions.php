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
   return ((($n % 24) + 24) % 24); //ALWAYS integer 0..23
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
   global $tick_frequency;
   //always return an integer
   return ( $ticks > $tick_frequency ? floor(($ticks-1) / $tick_frequency) : 0 );
}

function time_remaining( $hours, &$main, &$byotime, &$byoper
   , $startmaintime, $byotype, $startbyotime, $startbyoper, $has_moved)
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
               $byoper = 0; // No byoyomi. time is up;
               break;
            }
            $byoper = $startbyoper-1;
         }

         /***
          * previous formula:
          *  $byoper -= (int)(($startbyotime + $elapsed - $byotime)/$startbyotime);
          * only BECAUSE $elapsed>=0 and ($startbyotime - $byotime)>=0,
          * the (int)x cast is a floor(x) and this is equal to:
          *  $byoper -= floor(($elapsed - $byotime)/$startbyotime) +1;
          *  $byoper += ceil(($byotime - $elapsed)/$startbyotime) -1;
          * so don't make the (int) cast works on a negative number
          ***/
         $deltaper = (int)(($startbyotime + $elapsed - $byotime)/$startbyotime);
         $byoper -= $deltaper;

         if( $byoper < 0 )
            $byotime = $byoper = 0;  // time is up;
         else if( $has_moved )
            $byotime = $startbyotime;
         else 
            $byotime-= $elapsed - $deltaper*$startbyotime;
         /***
          * previous formula:
          *  $byotime = mod($byotime-$elapsed-1, $startbyotime)+1;
          * initially, the amount of time was:
          *  A = B + P * S    //B=$byotime, P=$byoper, S=$startbyotime)
          * now, we have:     //E=$elapsed
          *  a = b + p * S
          *  a = (B-E-1)-S*floor((B-E-1)/S)+1 + S*(P-floor((E-B)/S)-1)
          *  a = (B-E-1)+1 +S*(P -floor((E-B)/S)-1 -floor((B-E-1)/S))
          *  a = B -E +S*(P -floor((E-B)/S) -floor((B-E-1)/S) -1)
          *  E = A - a
          *  E = B + S*P -B +E -S*(P -floor((E-B)/S) -floor((B-E-1)/S) -1)
          *  E = E -S*(-floor((E-B)/S) -floor((B-E-1)/S) -1)
          *  => (-floor((E-B)/S) -floor((B-E-1)/S) -1) = 0
          *  => floor(-K/S) +floor((K-1)/S) = -1        //eq#1
          *  a) if K/S integer then
          *     floor(-K/S) = -K/S
          *     floor((K-1)/S) = K/S + floor(-1/S) = K/S -1
          *     eq#1 verified
          *  b) because we have only integers, if S don't divide K then
          *     floor(-K/S) = ceil(-K/S) -1 = -floor(K/S) -1
          *     floor((K-1)/S) = floor(K/S)
          *     eq#1 verified
          * the b calculus could have been:
          *  b = a -S*p = A -E -S*p = B +S*P -E -S*p = B -E +S*(P-p)
          *  p = P -(P-p)
          * i.e:         *
          *  $p-= (P-p)
          *  $b-= E-S*(P-p)
          ***/
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

function echo_day( $days, $keep_english=false, $short=false)
{
   if( $short && $keep_english )
      return $days . 'd';
   $T_= ( $keep_english ? 'fnop' : 'T_' );
   if( $short )
      return $days . ( abs($days) <= 1 ? $T_('day#short') : $T_('days#short') );
   return $days .'&nbsp;' . ( abs($days) <= 1 ? $T_('day') : $T_('days') );
}

function echo_hour( $hours, $keep_english=false, $short=false)
{
   if( $short && $keep_english )
      return $hours . 'h';
   $T_= ( $keep_english ? 'fnop' : 'T_' );
   if( $short )
      return $hours . ( abs($hours) <= 1 ? $T_('hour#short') : $T_('hours#short') );
   return $hours .'&nbsp;' . ( abs($hours) <= 1 ? $T_('hour') : $T_('hours') );
}

function echo_time( $hours, $keep_english=false, $short=false)
{
   if( $hours <= 0 )
      return '---';

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
}

function echo_byotype( $byotype, $keep_english=false, $short=false)
{
   if( $short )
      return substr($byotype, 0, 1);
      
   $T_= ( $keep_english ? 'fnop' : 'T_' );
   
   switch( $byotype )
   {
      case 'JAP':
         return $T_('Japanese byoyomi');
      case 'CAN':
         return $T_('Canadian byoyomi');
      case 'FIS':
         return $T_('Fischer time');
   }

   return '';
}

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
}


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
   else if( $byotype == 'FIS' or $byotime <= 0 )
   {
      if( $short )
         $str.= '---';
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
