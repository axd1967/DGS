<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/globals.php';


define('NIGHT_LEN', 9); //may be from 0 to 24 hours
define('DAY_LEN', (24-NIGHT_LEN)); // hours

define('BYOTYPE_JAPANESE', 'JAP');
define('BYOTYPE_CANADIAN', 'CAN');
define('BYOTYPE_FISCHER',  'FIS');
define('REGEX_BYOTYPES', '('.BYOTYPE_JAPANESE.'|'.BYOTYPE_CANADIAN.'|'.BYOTYPE_FISCHER.')');


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
   $d= date("j");
   $m= date("n");
   $y= date("Y");
   $n= -1;
   for($i=0; $i<6; $i++) //within the 6 next days,
   { //try to find two days with the same result (on hour)
      $o= $n;
      $n= mktime($nightstart,0,0,$m,$d+$i,$y);
      if( $n === FALSE || $n < 0 ) continue; // invalid timestamp
      $n= gmdate('G', $n); //hour without leading zeros. 0..23
      if( $n === $o ) break;
   }
   return (max(0, (int)$n) % 24); //ALWAYS integer 0..23
}

/*! Returns true, if given check-hour lies within the running-clock interval for $NOW (or the given timestamp). */
function is_hour_clock_run( $check_hour, $timestamp=null )
{
   // NOTE: also see clock_tick.php for night-time calculations
   global $NOW;
   $hour = gmdate('G', (is_null($timestamp) ? $NOW : $timestamp));
   $s = ($hour + 1) % 24;
   $e = ($hour + 24 - NIGHT_LEN) % 24;
   if( $s <= $e)
      $running_clock = ( $s <= $check_hour ) && ( $check_hour <= $e );
   else
      $running_clock = ( $check_hour <= $e ) || ( $s <= $check_hour );
   return $running_clock;
}

/*! \brief Returns true, if it's weekend (Sat/Sun) for UTC-timezone. */
function is_weekend( $timestamp=null )
{
   global $NOW;
   $day_of_week = gmdate('w', (is_null($timestamp) ? $NOW : $timestamp));
   return ( $day_of_week == 6 || $day_of_week == 0 ); // Sat | Sun
}

/*! Returns true, if given clock-id is a weekend-clock. */
function is_weekend_clock( $clock_id )
{
   return ( $clock_id >= WEEKEND_CLOCK_OFFSET && $clock_id < WEEKEND_CLOCK_OFFSET + 24 );
}

/*! Returns true, if given clock-id is within non-ticking weekend-clock. */
function is_weekend_clock_stopped( $clock_id, $timestamp=null )
{
   if( is_weekend_clock($clock_id) )
      $clock_id -= WEEKEND_CLOCK_OFFSET;
   if( $clock_id < 0 || $clock_id > 23 )
      error('invalid_args', "is_weekend_clock_stopped($clock_id,$timestamp)");

   if( is_weekend($timestamp) )
   {
      // NOTE: this can be seen as bug, but MUCH easier if you don't!
      //       BUG: weekend-clock is wrong, needs fixing; //is_hour_clock_run( $clock_id, $timestamp );
      //      -> really ? just keep it and it's easy (keeping UTC-weekends)
      $running_clock = false;
   }
   else
      $running_clock = true;
   return !$running_clock;
}

/*! Returns true, if given clock-id is a vacation-clock, i.e. player to move in game is on vacation. */
function is_vacation_clock( $clock_id )
{
   return ( $clock_id < 0 );
}

/*! Returns true, if given clock-id indicates, that user is in sleeping-time. */
function is_nighttime_clock( $clock_id, $timestamp=null )
{
   // clock_id is UTC-normalized ClockUsed (=NightStart)
   if( is_weekend_clock($clock_id) )
      $clock_id -= WEEKEND_CLOCK_OFFSET;
   if( $clock_id < 0 || $clock_id > 23 )
      error('invalid_args', "is_nighttime_clock($clock_id,$timestamp)");
   return !is_hour_clock_run( $clock_id, $timestamp );
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

function time_remaining( $hours, &$main, &$byotime, &$byoper,
      $startmaintime, $byotype, $startbyotime, $startbyoper, $has_moved)
{
   $elapsed = $hours;

   if( $main > $elapsed ) // still have main time left
   {
      $main -= $elapsed;

      // add extra-time on move for Fischer-time
      if( $has_moved && $byotype == 'FIS' )
      {
         // not capping main-time after adding time if it exceeds starting-main-time (cap)
         // NOTE: avoiding complicated checks for add-time feature
         if( $main < $startmaintime )
            $main = min($startmaintime, $main + $startbyotime);
      }

      return;
   }

   if( $main > 0 )
      $elapsed -= $main;
   $main = 0;

   switch( (string)$byotype )
   {
      case BYOTYPE_FISCHER:
         $byotime = $byoper = 0;  // time is up
         break;

      case BYOTYPE_JAPANESE:
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
         break;
      }//case BYOTYPE_JAPANESE

      case BYOTYPE_CANADIAN:
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
         break;
      }//case BYOTYPE_CANADIAN
   }
} //time_remaining

// remaining-time calculus aggregating remaining-time into one value of hours
// Ref: http://www.dragongoserver.net/forum/read.php?forum=4&thread=5728#5743
// - Fischer:   M main-time + T extra-time            -> M
// - Japanese:  M main-time + N * T extra-time        -> M + remainingN * T
// - Canadian:  M main-time + T extra-time / N stones -> M + currT / N
function time_remaining_value( $byotype, $startByotime, $startByoperiods,
      $currMaintime, $currByotime, $currByoperiods )
{
   $result = 0;

   switch( (string)$byotype )
   {
      case BYOTYPE_FISCHER:
         $result = ($currMaintime > 0) ? $currMaintime : 0;
         break;

      case BYOTYPE_JAPANESE:
         // IMPORTANT NOTE: need to handle add-time properly, see also specs/time.txt !!
         //   byo-yomi partly reset on add-time needs special handling
         if( $currMaintime > 0 ) // not in byo-yomi yet
         {
            $result = $currMaintime;
            if( $currByoperiods > 0 ) // can happen after add-time (only for JAP-time)
               $result += $currByoperiods * $startByotime; // part byo-yomi reset
            elseif( $startByotime > 0 ) // non-absolute
               $result += $startByoperiods * $startByotime;
         }
         else
         {// in byo-yomi
            $result = $currByotime;
            if( $startByoperiods > 0 && $startByotime > 0 )
               $result += $currByoperiods * $startByotime;
         }
         break;

      case BYOTYPE_CANADIAN:
         // IMPORTANT NOTE: need to handle add-time properly, see also specs/time.txt !!
         //    byo-yomi fully resetted on add-time, so no special handling needed
         if( $currMaintime > 0 ) // not in byo-yomi yet
         {
            $result = $currMaintime;
            if( $startByoperiods > 0 ) // non-absolute
               $result += $startByotime / $startByoperiods;
         }
         else
         {// in byo-yomi
            if( $startByoperiods > 0 && $currByoperiods > 0 ) // non-absolute
               $result = $currByotime / $currByoperiods;
            else
               $result = 0;
         }
         break;
   }
   return $result;
}

// returns CSS-class if hours within warning-level for remaining-time
function get_time_remaining_warning_class( $hours )
{
   if( $hours <= 3 * DAY_LEN )
      return 'RemTimeWarn1';
   elseif( $hours <= 7 * DAY_LEN )
      return 'RemTimeWarn2';
   else
      return '';
}

/*!
 * \brief Builds time-remaining info.
 * \param $grow = Games-row with (White|Black)_(Maintime|Byotime|Byoperiods),
 *              X_Ticks, LastTicks, Maintime, Byotype, Byotime, Byoperiods
 * \param $color BLACK | WHITE
 * \param $is_to_move true if color-user is to move (taking current ticks into account)
 * \return array( attbs => CSS-arr, text => remaining-time-str )
 */
function build_time_remaining( $grow, $color, $is_to_move, $timefmt=null )
{
   $prefix_col = ($color == BLACK) ? 'Black' : 'White';
   $userMaintime   = $grow[$prefix_col.'_Maintime'];
   $userByotime    = $grow[$prefix_col.'_Byotime'];
   $userByoperiods = $grow[$prefix_col.'_Byoperiods'];
   if( is_null($timefmt) )
      $timefmt = TIMEFMT_ADDTYPE | TIMEFMT_ABBEXTRA | TIMEFMT_ZERO;

   // no Ticks (vacation) == 0 => lead to 0 elapsed hours
   $elapsed_hours = ( $is_to_move ) ? ticks_to_hours(@$grow['X_Ticks'] - @$grow['LastTicks']) : 0;

   time_remaining($elapsed_hours, $userMaintime, $userByotime, $userByoperiods,
      $grow['Maintime'], $grow['Byotype'], $grow['Byotime'], $grow['Byoperiods'], false);
   $hours_remtime = time_remaining_value( $grow['Byotype'], $grow['Byotime'], $grow['Byoperiods'],
      $userMaintime, $userByotime, $userByoperiods );

   $class_remtime = get_time_remaining_warning_class( $hours_remtime );
   $rem_time = TimeFormat::echo_time_remaining( $userMaintime, $grow['Byotype'], $userByotime,
      $userByoperiods, $grow['Byotime'], $grow['Byoperiods'], $timefmt );

   return array(
         'attbs' => array( 'class' => $class_remtime ),
         'text'  => $rem_time,
      );
}//build_time_remaining

/*!
 * \brief Returns "absolute" time in ticks aligned with Clock[ID=CLOCK_TIMELEFT].
 * \param $hours_left hours_left is not precise but following the calculus of time_remaining_value()
 * \note
 * - used for time-remaining ordering
 * - Weekend-handling is NOT implemented, because:
 *   Weekend-handling would have a flaw, which after outages for which the clock are not "ticking"
 *   (like maintenance) would need that admins have to recalculate the Games.TimeOutDate.
 *   The weekend-handling would need to use the current time to compensate all weekends during
 *   the time left. This weekend-compensation would make it unsynchronized with
 *   the Clock-aligned TimeOutDate.
 *
 *   => Admins can run fix_games_timeleft-script for games without weekend-clock
 *   to fix Games.TimeOutDate after longer outages.
 *   However, the TimeOutDate is "corrected" on the players next-move, so it can be
 *   accepted to skip the fix-step after maintenance if not taking too long.
 */
function time_left_ticksdate( $hours_left, $curr_ticks=-1 )
{
   if( $curr_ticks < 0 )
      $curr_ticks = get_clock_ticks(CLOCK_TIMELEFT);

   $ticks_date = $curr_ticks + round( $hours_left * TICK_FREQUENCY );
   return $ticks_date;
}


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



define('TIMEFMT_ENGL',     0x0001 ); // ignore users-language (keep english)
define('TIMEFMT_SHORT',    0x0002 ); // long-text <-> short-text form
define('TIMEFMT_HTMLSPC',  0x0004 ); // whitespace separator: plain space <-> HTML &nbsp;
define('TIMEFMT_ADDTYPE',  0x0008 ); // omit byo-type <-> include byo-type
define('TIMEFMT_ADDEXTRA', 0x0010 ); // omit extra-time (byo-yomi) <-> include extra-time
define('TIMEFMT_ZERO',     0x0020 ); // use zero time <-> return special-zero-value on zero
define('TIMEFMT_ABBEXTRA', 0x0040 ); // abbreviate extra-time <-> full extra-specs

/*!
 * \brief Static helper class to format Dragon time.
 */
class TimeFormat
{

   // "7d" (short), "7 days" (long)
   // fmtflags: TIMEFMT_ENGL, TIMEFMT_SHORT, TIMEFMT_HTMLSPC
   function echo_day( $days, $fmtflags=0 )
   {
      if( ($fmtflags & TIMEFMT_SHORT) && ($fmtflags & TIMEFMT_ENGL) )
         return $days . 'd';

      $T_= ($fmtflags & TIMEFMT_ENGL) ? 'fnop' : 'T_';
      $absdays = abs($days);

      $str = $days;
      if( $fmtflags & TIMEFMT_SHORT )
         $str .= ( $absdays > 0 && $absdays <= 1 ) ? $T_('day#short') : $T_('days#short');
      else
         $str .= ' ' . ( ($absdays > 0 && $absdays <= 1) ? $T_('day') : $T_('days') );

      return TimeFormat::_replace_space($str, $fmtflags);
   }

   // "3h" (short), "3 hours" (long)
   // fmtflags: TIMEFMT_ENGL, TIMEFMT_SHORT, TIMEFMT_HTMLSPC
   function echo_hour( $hours, $fmtflags=0 )
   {
      if( ($fmtflags & TIMEFMT_SHORT) && ($fmtflags & TIMEFMT_ENGL) )
         return $hours . 'h';

      $T_= ($fmtflags & TIMEFMT_ENGL) ? 'fnop' : 'T_';
      $abshours = abs($hours);

      $str = $hours;
      if( $fmtflags & TIMEFMT_SHORT )
         $str .= ( $abshours > 0 && $abshours <= 1 ) ? $T_('hour#short') : $T_('hours#short');
      else
         $str .= ' ' . ( ($abshours > 0 && $abshours <= 1) ? $T_('hour') : $T_('hours') );

      return TimeFormat::_replace_space($str, $fmtflags);
   }

   // \internal
   // "5d 7h" (short), "5 days and 7 hours" (long)
   // 0 -> zero_value
   // fmtflags: TIMEFMT_ENGL, TIMEFMT_SHORT, TIMEFMT_HTMLSPC, TIMEFMT_ZERO
   function _echo_time( $hours, $hours_per_day, $fmtflags=0, $zero_value=NO_VALUE )
   {
      if( $hours <= 0 )
         return ($fmtflags & TIMEFMT_ZERO) ? TimeFormat::echo_hour(0, $fmtflags) : $zero_value;

      $T_= ($fmtflags & TIMEFMT_ENGL) ? 'fnop' : 'T_';

      $h = $hours % $hours_per_day;
      $days = ($hours-$h) / $hours_per_day;
      $str = ( $days > 0 ) ? TimeFormat::echo_day( $days, $fmtflags ) : '';

      if( $h > 0 ) //or $str == '' )
      {
         if( $str > '' )
         {
            $str .= ' ';
            if( !($fmtflags & TIMEFMT_SHORT) )
               $str .= $T_('and') . ' ';
         }

         $str .= TimeFormat::echo_hour( $h, $fmtflags );
      }

      return TimeFormat::_replace_space($str,$fmtflags);
   } //_echo_time

   // fmtflags: TIMEFMT_ENGL, TIMEFMT_SHORT, TIMEFMT_HTMLSPC, TIMEFMT_ZERO
   function echo_time( $hours, $fmtflags=0, $zero_value=NO_VALUE )
   {
      return TimeFormat::_echo_time( $hours, 15, $fmtflags, $zero_value );
   }

   // fmtflags: TIMEFMT_ENGL, TIMEFMT_SHORT, TIMEFMT_HTMLSPC, TIMEFMT_ZERO
   function echo_onvacation( $days, $fmtflags=0, $zero_value=NO_VALUE )
   {
      $hours = round($days*24);
      $str = TimeFormat::_echo_time( $hours, 24, $fmtflags, $zero_value );
      if( $hours > 0 || ($fmtflags & TIMEFMT_ZERO) )
         $str .= ' ' . ( ($fmtflags & TIMEFMT_ENGL) ? 'left' : T_('left#2') ); //FIXME: -> engl -> '%s left' (wait for transl-label)

      return TimeFormat::_replace_space($str,$fmtflags);
   }

   // fmtflags: TIMEFMT_ENGL, TIMEFMT_SHORT
   function echo_byotype( $byotype, $fmtflags=0 )
   {
      if( $fmtflags & TIMEFMT_SHORT )
         return substr($byotype, 0, 1); // J, C, F

      $T_= ($fmtflags & TIMEFMT_ENGL) ? 'fnop' : 'T_';
      switch( (string)$byotype )
      {
         case BYOTYPE_JAPANESE:
            return $T_('Japanese byoyomi');
         case BYOTYPE_CANADIAN:
            return $T_('Canadian byoyomi');
         case BYOTYPE_FISCHER:
            return $T_('Fischer time');
      }

      return '';
   }

   // "J: 7d 3h + 2d * 5" (short), "J: $maintime + $byotime per move and $byoper extra periods"
   // "C: 7d 3h + 9d / 3" (short), "C: $maintime + $byotime per NUMBER stones"
   // "F: 7d 3h + 2d"     (short), "F: $maintime + $byotime per move"
   // type, maintime or extra-time are optional
   // fmtflags: TIMEFMT_ENGL, TIMEFMT_SHORT, TIMEFMT_HTMLSPC, TIMEFMT_ZERO, TIMEFMT_ADDTYPE
   function echo_time_limit( $maintime, $byotype, $byotime, $byoper, $fmtflags=TIMEFMT_ADDTYPE )
   {
      $T_= ($fmtflags & TIMEFMT_ENGL) ? 'fnop' : 'T_';
      $str = '';

      if( $fmtflags & TIMEFMT_ADDTYPE )
         $str .= TimeFormat::echo_byotype($byotype, $fmtflags) . ': ';

      if( $maintime > 0 )
         $str .= TimeFormat::echo_time($maintime, $fmtflags) . ' ';

      if( $maintime <= 0 && $byotime <= 0 )
      {
         $str .= TimeFormat::echo_hour(0, $fmtflags);
      }
      else if( $byotype == BYOTYPE_FISCHER )
      {
         if( $byotime > 0 )
         {
            if( $fmtflags & TIMEFMT_SHORT )
               $str .= '+ ' . TimeFormat::echo_time($byotime, $fmtflags);
            else
               $str .= sprintf( $T_('with %s extra per move'),
                                TimeFormat::echo_time($byotime, $fmtflags) );
         }//else: absolute-time
      }
      else // JAP|CAN
      {
         if( $byotime <= 0 ) // absolute-time
         {
            if( !($fmtflags & TIMEFMT_SHORT) )
               $str .= $T_('without byoyomi');
         }
         else // has byo-time
         {
            if( $maintime > 0 )
               $str .= '+ ';

            if( $fmtflags & TIMEFMT_SHORT )
            {
               $str .= TimeFormat::echo_time($byotime, $fmtflags);
               if( $byotype == BYOTYPE_JAPANESE )
                  $str .= " * $byoper";
               else //if( $byotype == BYOTYPE_CANADIAN )
                  $str .= " / $byoper";
            }
            else
            {
               if( $byotype == BYOTYPE_JAPANESE )
               {
                  $str .= sprintf( $T_('%s per move and %s extra periods'),
                                   TimeFormat::echo_time($byotime, $fmtflags), $byoper );
               }
               else //if( $byotype == BYOTYPE_CANADIAN )
               {
                  $str .= sprintf( $T_('%s per %s stones'),
                                   TimeFormat::echo_time($byotime, $fmtflags), $byoper );
               }
            }
         }
      }

      return TimeFormat::_replace_space($str, $fmtflags);
   } //echo_time_limit


   // fmtflags: TIMEFMT_ENGL, TIMEFMT_HTMLSPC, TIMEFMT_ZERO, TIMEFMT_ADDTYPE,
   //           TIMEFMT_ADDEXTRA, TIMEFMT_ABBEXTRA
   function echo_time_remaining( $maintime, $byotype, $byotime, $byoper,
                                 $startbyotime, $startbyoper, $fmtflags=null )
   {
      if( is_null($fmtflags) )
         $fmtflags = TIMEFMT_ADDTYPE | TIMEFMT_ADDEXTRA;
      $fmtflags |= TIMEFMT_SHORT; // default
      $T_= ($fmtflags & TIMEFMT_ENGL) ? 'fnop' : 'T_';
      $str = '';

      if( $fmtflags & TIMEFMT_ADDTYPE )
         $str .= TimeFormat::echo_byotype($byotype, $fmtflags) . ': ';

      // remaining main/byoyomi-time
      $rem_time = $maintime;
      if( $maintime <= 0 )
      {
         if( $byotype == BYOTYPE_FISCHER || $byotime <= 0 ) // time is up
         {
            $str .= TimeFormat::echo_time($maintime, $fmtflags | TIMEFMT_ZERO);
            return TimeFormat::_replace_space($str, $fmtflags);
         }

         $rem_time = $byotime;
      }

      $str .= TimeFormat::echo_time($rem_time, $fmtflags) . ' ';

      if( $startbyotime <= 0 ) // absolute time
      {
         if( $fmtflags & (TIMEFMT_ADDEXTRA|TIMEFMT_ABBEXTRA) )
            $str .= '(-)';

         return TimeFormat::_replace_space($str, $fmtflags);
      }

      // ignore invalid time-values (M=0, B>0); should not occur esp. after add-time
      if( $byotype == BYOTYPE_CANADIAN && $maintime <= 0 && $byotime > 0 && $byoper > 0 )
         $str .= "/ $byoper ";

      // extra-time
      if( $fmtflags & (TIMEFMT_ADDEXTRA|TIMEFMT_ABBEXTRA) )
      {
         if( $byotype == BYOTYPE_FISCHER )
         {
            $str .= ( $fmtflags & TIMEFMT_ABBEXTRA )
               ? '(+)'
               : '(+ ' . TimeFormat::echo_time($startbyotime, $fmtflags) . ')';
         }
         else
         {
            if( $byoper != 0 )
            {
               if( $byotype == BYOTYPE_CANADIAN )
                  $rem_byoper = $startbyoper;
               else
                  $rem_byoper = ( $byoper > 0 ) ? $byoper : $startbyoper;

               $str2 = TimeFormat::echo_time_limit( 0, $byotype, $startbyotime, $rem_byoper,
                  $fmtflags & ~TIMEFMT_ADDTYPE );

               if( $fmtflags & TIMEFMT_ABBEXTRA)
                  $str .= ( $maintime > 0 ) ? '(+)' : '(..)';
               else
                  $str .= ( $maintime > 0 ) ? "(+ $str2)" : "($str2)";
            }
            else
               $str .= '(-)';
         }
      }

      return TimeFormat::_replace_space($str, $fmtflags);
   } //echo_time_remaining

   /** \internal */
   function _replace_space( $str, $opts )
   {
      if( $opts & TIMEFMT_HTMLSPC )
         return str_replace( ' ', '&nbsp;', trim($str) );
      else
         return trim($str);
   }

} //end 'TimeFormat'

?>
