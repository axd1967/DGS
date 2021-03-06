<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Users";

require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/rating.php';
require_once 'include/graph.php';
require_once 'include/db/ratinglog.php';

// NOTE: always display the number of games played below the dates.


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row, LOGIN_SKIP_UPDATE );

   // rating-graph can be viewed without being logged in
   // if ( !$logged_in )
   //    error('not_logged_in', 'ratingpng');

   //disable translations in graph if not latin
   if ( preg_match( '/^iso-8859-/i', $encoding_used) )
   {
      //$T_= 'T_';
      $datelabel = create_function('$x', 'return format_translated_date("M\\nY", $x);' );
      $ratinglabel = create_function('$x', 'return echo_rating($x,0,0,0);' );
   }
   else
   {
      $T_= 'fnop'; // keep English
      $datelabel = create_function('$x', 'return date("M\\nY",$x);' );
      $ratinglabel = create_function('$x', 'return echo_rating($x,0,0,1);' );
   }

   $show_by_number = (bool)@$_GET['bynumber']; // x-axis (true=Games, false=Time)
   $hide_rating_data = (bool)@$_GET['hd'];
   $show_wma = (bool)@$_GET['wma']; // weighted moving average
   $wma_taps = (int)@$_GET['wma_taps'];

   $starttime = mktime(0,0,0,BEGINMONTH,1,BEGINYEAR);
   $endtime = $NOW + GRAPH_RATING_MIN_INTERVAL;
   if ( $endtime < $starttime )
   {
      $endtime = $starttime + GRAPH_RATING_MIN_INTERVAL;
      if ( $endtime < $starttime )
        swap($starttime, $endtime);
   }

   if ( isset($_GET['startyear']) )
      $starttime = max($starttime, mktime(0,0,0
         ,isset($_GET['startmonth']) ? $_GET['startmonth'] : 1
         ,1,$_GET['startyear']));

   if ( isset($_GET['endyear']) )
      $endtime = min($endtime, mktime(0,0,0
         ,isset($_GET['endmonth']) ? $_GET['endmonth']+1 : 13
         ,0,$_GET['endyear']));

   $endtime = max( $endtime, $starttime + GRAPH_RATING_MIN_INTERVAL);


   // prepare the graph

   $SizeX = max( 200, @$_GET['size'] > 0 ? $_GET['size'] : 640 );
   $SizeY = $SizeX * 3 / 4;

   $gr = new Graph($SizeX, $SizeY, substr($bg_color, 1, -1));

   $black = $gr->getcolor(0, 0, 0);
   $red = $gr->getcolor(205, 159, 156);
   $light_blue = $gr->getcolor(220, 229, 255);
   $number_color = $gr->getcolor(250, 100, 98);

   $wma_color = $gr->getcolor(0xd0, 0, 0);


   // fetch and prepare datas

   get_rating_data(@$_GET["uid"]);
   $nr_points = count($ratings);
   $x_data = ( $show_by_number ) ? $number : $time;

   // graph filters

   if ( $show_wma )
   {
      if ( $wma_taps < 2 )
         $wma_taps = 2;
      elseif ( $wma_taps > MAX_WMA_TAPS )
         $wma_taps = MAX_WMA_TAPS;
      if ( $wma_taps > $nr_points - 2 )
         $wma_taps = $nr_points - 2;

      // NOTE: keep weighted-code (perhaps needed later)
      $wma_simple = true;
      //$arr_wma_weights = build_wma_weights( false, $wma_taps );
      //error_log("WMA-weights($wma_taps): ".implode(', ', $arr_wma_weights));
      $rating_wma = calculate_wma( $ratings, ($wma_simple ? $wma_taps : $arr_wma_weights) );
   }//wma


   //$startnumber is the number of games before the graph start
   if ( !isset($number) || count($number) < 1 )
   {
      $startnumber = $endnumber = -1;
   }
   else
   {
      $startnumber = round($number[0]);
      $endnumber = round($number[count($number)-1]);
   }

   if ( $show_by_number )
   {
      $xvals = $number;
      $xlims = array('MIN'=>$startnumber, 'MAX'=>$endnumber);
   }
   else
   {
      $xvals = $time;
      $xlims = array('MIN'=>$starttime, 'MAX'=>$endtime);
   }

   $ymax = array_reduce($ratingmax, "max",-10000);
   $ymin = array_reduce($ratingmin, "min", 10000);


   //just a string sample to evaluate $marge_left
   $m = array ($ratinglabel( 100), //20kyu
               $ratinglabel(3000)); //10dan
   $x = 0;
   foreach ( $m as $y )
   {
      $b= $gr->labelbox($y);
      $x= max($x,$b['x']);
   }
   $marge_left  = $gr->border+10 +$x;
   $marge_right = max(10,DASH_MODULO+2); //better if > DASH_MODULO
   $marge_top   = max(10,DASH_MODULO+2); //better if > DASH_MODULO
   $marge_bottom= $gr->border + 3 * $gr->labelMetrics['LINEH']; // show-numbers: 3 else 2

   $gr->setgraphbox( $marge_left, $marge_top, $gr->width-$marge_right, $gr->height-$marge_bottom );

   // scale datas

   $gr->setgraphview( $xlims['MIN'], $ymax, $xlims['MAX'], $ymin );

   $xvals = $gr->mapscaleX($xvals);
   $ratingmax = $gr->mapscaleY($ratingmax);
   $ratingmin = $gr->mapscaleY($ratingmin);

   if ( !$hide_rating_data )
      $ratings = $gr->mapscaleY($ratings);

   if ( $show_wma )
      $rating_wma = $gr->mapscaleY($rating_wma);


   // draw the blue array

   if ( $nr_points > 1 )
   {
      $gr->filledpolygon(
            array_merge(
               points_join($xvals, $ratingmax),
               array_reverse(points_join($ratingmin, $xvals)) ),
            2*$nr_points, $light_blue);
   }


   // vertical scaling

   $step = 100; //i.e. 1 kyu step
   $start = ceil($ymin/$step)*$step;
   $gr->gridY( $start, $step, $gr->border, $ratinglabel, $black, '', $black );


   // horizontal scaling

   if ( $show_by_number )
   { // the X-axis is the number of games
      $step = 20.; //min grid distance in pixels
      $step/= $gr->sizeX; //graph width
      $step = ceil(($endnumber - $startnumber) * $step);

      if ( $startnumber >= 0 )
      {
         function nbr2date($v){
            global $startnumber, $time, $datelabel;
            if ( $startnumber >= 0 )
            {
               if ( isset($time[$v-=$startnumber]) )
                  return $datelabel($time[$v]);
            }
            return '';
         }
         $y = $gr->boxbottom+3 +1*$gr->labelMetrics['LINEH'];
         $gr->gridX( $startnumber, $step, $y, 'nbr2date', $black, '', $red );
      }

      // show numbers
      $y = $gr->boxbottom+3;
      $x = $gr->label($gr->border, $y, $T_('Games').':', $number_color);
      if ( $startnumber >= 0 )
      {
         $x= $x['x'] +$gr->labelMetrics['WIDTH'];
         $gr->gridX( $startnumber, $step, $y, '', $number_color, '', $red, 0, $x );
      }
   }
   else //!$show_by_number
   { // the X-axis is the date of games
      $step = 20.; //min grid distance in pixels
      $step/= $gr->sizeX; //graph width
      $step/= 30 * SECS_PER_DAY; //one month
      $step = ceil(($endtime - $starttime) * $step);

      $month = date('n',$starttime)+1;
      $year = date('Y',$starttime);
      $dategrid = create_function('$x',
         "return mktime(0,0,0,\$x,1,$year,0);" );
      $gr->gridX( $month, $step, $gr->boxbottom+3, $datelabel, $black, $dategrid, $red );

      // show numbers
      $y = $gr->boxbottom+3 +2*$gr->labelMetrics['LINEH'];
      $x = $gr->label($gr->border, $y, $T_('Games').':', $number_color );
      if ( $startnumber >= 0 )
      {
         function date2nbr($v){
            global $time, $number;
            $n= array_bsearch($v, $time);
            if ( $n > 0 )
               $n--;
            else
               $n= 0;
            return round($number[$n]);
         }
         $x= $x['x'] +$gr->labelMetrics['WIDTH'];
         $gr->gridX( $month, $step, $y, 'date2nbr', $number_color, $dategrid, $red, 0, $x );
      }
   }


   // draw the curves

   if ( !$hide_rating_data )
      $gr->curve($xvals, $ratings, $nr_points, $black);

   if ( $show_wma )
      $gr->curve($xvals, $rating_wma, $nr_points, $wma_color);


   // misc drawings

   if ( @$_REQUEST['show_time'] )
      $gr->label( $gr->offsetX, 0, sprintf('%0.2f ms', (getmicrotime()-$page_microtime)*1000), $black );


   $gr->send_image();
}//main


function get_rating_data($uid)
{
   global $ratings, $ratingmin, $ratingmax, $number,
      $time, $starttime, $endtime;

   if ( !($uid > 0 ) )
      exit;


   $ratings = array();
   $ratingmax = array();
   $ratingmin = array();
   $time = array();
   $number = array();

   $bound_interval = GRAPH_RATING_MIN_INTERVAL/4;

   // note: Ratinglog-entries exists only for rated games
   $query = "SELECT InitialRating AS Rating, " .
      "InitialRating+200+GREATEST(1600-InitialRating,0)*2/15 AS RatingMax, " .
      "InitialRating-200-GREATEST(1600-InitialRating,0)*2/15 AS RatingMin, " .
      "UNIX_TIMESTAMP(Registerdate) AS reg_seconds " .
      "FROM Players WHERE ID=$uid LIMIT 1";
   $result = db_query( 'ratingpng.find_initial', $query );
   if ( @mysql_num_rows($result) != 1 )
      exit;

   $min_row = mysql_fetch_assoc($result);
   $max_row = mysql_single_fetch( 'ratingpng.find_min_max_time',
      "SELECT UNIX_TIMESTAMP(MIN(Time)) AS min_seconds, UNIX_TIMESTAMP(MAX(Time)) AS max_seconds " .
      "FROM Ratinglog WHERE uid=$uid LIMIT 1" );

   list( $rlog_cached, $cnt_rating_logs, $rlog_result ) = Ratinglog::load_cache_ratinglogs( $uid, 3000 ); // ordered by Time
   if ( $cnt_rating_logs < 1 )
      exit;

   // start time with first rated-game, otherwise registration-date
   $min_row['seconds'] = ( @$max_row['min_seconds'] )
      ? $max_row['min_seconds'] - SECS_PER_DAY
      : $min_row['reg_seconds'];

   if ( $starttime < $min_row['seconds'] - $bound_interval )
      $starttime = $min_row['seconds'] - $bound_interval;
   if ( $endtime < $min_row['seconds'] + $bound_interval)
      $endtime = $min_row['seconds'] + $bound_interval;

   if ( $starttime > $max_row['max_seconds'] - $bound_interval )
      $starttime = $max_row['max_seconds'] - $bound_interval;
   if ( $endtime > $max_row['max_seconds'] + $bound_interval)
      $endtime = $max_row['max_seconds'] + $bound_interval;

   if ( ($endtime - $starttime) < GRAPH_RATING_MIN_INTERVAL )
   {
      $mean = ( $starttime + $endtime )/2 + 12*SECS_PER_HOUR;
      $starttime = $mean - GRAPH_RATING_MIN_INTERVAL/2;
      $endtime = $starttime + GRAPH_RATING_MIN_INTERVAL;
   }


   $numbercount = -1 ; //first point is Registerdate
   $first = true;
   $tmp = NULL;
   $row = $min_row;
   do
   {
      if ( $row['seconds'] < $starttime )
      {
         $tmp = $row;
         $numbercount++ ;
         continue;
      }

      if ( $first )
      {
         if ( is_array($tmp) && $tmp['seconds'] < $starttime )
         {
            //interpolate the first curves points
            $ratings[]= interpolate($tmp['Rating'], $row['Rating'],
                              $tmp['seconds'], $starttime, $row['seconds']);
            $ratingmin[]= interpolate($tmp['RatingMin'], $row['RatingMin'],
                              $tmp['seconds'], $starttime, $row['seconds']);
            $ratingmax[]= interpolate($tmp['RatingMax'], $row['RatingMax'],
                              $tmp['seconds'], $starttime, $row['seconds']);
            $time[]= $starttime;
            $number[]= $numbercount+.4; //mark the interpolation
         }
         $first = false;
      }

      if ( $row['seconds'] > $endtime )
      {
         //interpolate the last curves points
         if ( is_array($tmp) && $tmp['seconds'] <= $endtime )
         {
            $ratings[]= interpolate($tmp['Rating'], $row['Rating'],
                              $tmp['seconds'], $endtime, $row['seconds']);
            $ratingmin[]= interpolate($tmp['RatingMin'], $row['RatingMin'],
                              $tmp['seconds'], $endtime, $row['seconds']);
            $ratingmax[]= interpolate($tmp['RatingMax'], $row['RatingMax'],
                              $tmp['seconds'], $endtime, $row['seconds']);
            $time[]= $endtime;
            $number[]= $numbercount+.6; //mark the interpolation
         }
         break;
      }

      $ratings[]= $row['Rating'];
      $ratingmin[]= $row['RatingMin'];
      $ratingmax[]= $row['RatingMax'];
      $time[]= $row['seconds'];
      $numbercount++;
      $number[]= $numbercount;

      $tmp = $row;
   } while ( $row = ( $rlog_cached ? array_shift($rlog_result) : mysql_fetch_assoc($rlog_result) ) );

   if ( !$rlog_cached )
      mysql_free_result($rlog_result);
}//get_rating_data



/* unused, but kept
// regression-line formulas taken from https://de.wikipedia.org/wiki/Methode_der_kleinsten_Quadrate#Herleitung_und_Verfahren
// \return arr( a, b, x0, xLast, y0, yLast ) for linear func(x) := a*x + b
function calculate_LSQ( $show_by_number, $ratings, $x_data )
{
   $cnt = count($ratings);
   if ( $cnt == 0 )
      return null;

   $y_data = $ratings;
   $x_mean = calculate_mean( $x_data );
   $y_mean = calculate_mean( $y_data );

   $numerator = 0;
   $denominator = 0;
   for ( $i=0; $i < $cnt; $i++ ) {
      $x_diff = ( $x_data[$i] - $x_mean );
      $numerator += $x_diff * ( $y_data[$i] - $y_mean );
      $denominator += $x_diff * $x_diff;
   }

   // f(x) := a*x + b  (linear line)
   $a = $numerator / $denominator;
   $b = $y_mean - $a * $x_mean;

   $x0 = $x_data[0];
   $xL = $x_data[$cnt-1];
   $y0 = $a * $x0 + $b;
   $yL = $a * $xL + $b;
   //error_log("calculate_LSQ(#$cnt): $a * x + $b ; ($x0,$y0) .. ($xL,$yL)");

   return array( $a, $b, $x0, $y0, $xL, $yL );
}//calculate_LSQ
*/


// \param $taps must be > 1
// NOTE: keep it even though $binomial is not used for rating-graph
function build_wma_weights( $binomial, $taps )
{
   static $PYRAMIDS = array(
         0 => array( 1 ),
         1 => array( 1, 1 ),
      );

   if ( $taps <= 1 )
      return $PYRAMIDS[$taps-1];
   if ( $taps == 2 )
      return $PYRAMIDS[$taps-1];

   if ( !$binomial ) // weights for simple filter
      return array_fill(0, $taps, 1);

   // build weights for binomial n-tap filter (LaPlace pyramid)
   $pyramid = $PYRAMIDS[1];
   for ( $level=2; $level < $taps; $level++ )
   {
      $prev_pyramid = array_merge( array(), $pyramid ); // clone previous pyramid-level
      $pyramid = array( 1 );

      // create half of new level ...
      $half_cnt = floor( $level / 2 ) + 1;
      for ( $i=1; $i < $half_cnt; $i++ )
         $pyramid[] = $prev_pyramid[$i-1] + $prev_pyramid[$i];

      // ... and mirror it for 2nd half
      $mirror_start = $i-1;
      if ( $level & 1 ) // odd level
         $pyramid[] = $pyramid[$mirror_start];
      while ( $mirror_start-- > 0 )
         $pyramid[] = $pyramid[$mirror_start];
   }

   return $pyramid;
}//build_wma_weights

// calculated weighted moving average (simple MA, if $weights only contain same values or $weights is number)
// \param $weights integer = simple moving average; otherwise array with weights;  there must be at least 2 entries
function calculate_wma( $ratings, $weights )
{
   if ( is_array($weights) )
   {
      $size = count($weights);
      $simple_wma = ( count(array_count_values($weights)) == 1 ); // simple if all weights are equal
   }
   else
   {
      $size = (int)$weights;
      $simple_wma = true;
   }
   if ( $size < 2 )
      error('invalid_args', "calculate_wma.check_size($size)");

   $start = $size - 1;
   $result = ( $size > 1 ) ? array_fill(0, round($start/2), null) : array();
   $cnt = count($ratings);

   if ( $simple_wma ) // simple moving average
   {
      for ( $i=$start; $i < $cnt; $i++ )
         $result[] = calculate_mean( array_slice($ratings, $i - $start, $size), $size );
   }
   else // weighted moving average
   {
      $w_sum = array_sum($weights);
      for ( $i=$start; $i < $cnt; $i++ )
         $result[] = array_weighted_moving_average( array_slice($ratings, $i - $start, $size), $weights, $size, $w_sum );
   }

   $rcnt = $cnt - count($result);
   for ( $i=0; $i < $rcnt; $i++ )
      $result[] = null;

   return $result;
}//calculate_wma

function array_weighted_moving_average( $arr, $weights, $w_cnt, $w_sum )
{
   $wma = 0;
   for ( $i=0; $i < $w_cnt; $i++ )
      $wma += $weights[$i] * $arr[$i];
   return $wma / $w_sum;
}//array_weighted_moving_average

?>
