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

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
require_once( "include/rating.php" );
require_once( "include/graph.php" );

//Display the number of games played below the dates.
define('SHOW_NRGAMES', true);
//Display the win/lost/unrated pie.
define('ENA_WIN_PIE', true);
//Default display of the win/lost/unrated pie.
define('SHOW_WIN_PIE', false);


function interpolate($val1, $val3, $time1, $time2, $time3)
{
   if( $time1 == $time3 )
      return $val3;
   return $val3 + ($val1-$val3)*($time2-$time3)/($time1-$time3);
}


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

//   if( !$logged_in )
//      error("not_logged_in");


   //disable translations in graph if not latin
   if( eregi( '^iso-8859-', $encoding_used) )
   {
      $keep_english= false;
      $T_= 'T_';
      $datelabel = create_function('$x',
         'return T_(date("M",$x)).date("\\nY",$x);' );
      $ratinglabel = create_function('$x',
         'return echo_rating($x,0,0,0);' );
   }
   else
   {
      $keep_english= true;
      $T_= 'fnop';
      $datelabel = create_function('$x',
         'return date("M\\nY",$x);' );
      $ratinglabel = create_function('$x',
         'return echo_rating($x,0,0,1);' );
   }

   //actually, $show_by_number can't work without SHOW_NRGAMES. TODO
   $show_by_number = SHOW_NRGAMES && GRAPH_RATING_BY_NUM_ENABLED && ((bool)@$_GET['bynumber']);
   $show_win_pie = ENA_WIN_PIE && (SHOW_WIN_PIE xor ((bool)@$_GET['winpie']));


   $starttime = mktime(0,0,0,BEGINMONTH,1,BEGINYEAR);
   $endtime = $NOW + GRAPH_RATING_MIN_INTERVAL;
   if( $endtime < $starttime )
   {
      $endtime = $starttime + GRAPH_RATING_MIN_INTERVAL;
      if( $endtime < $starttime )
        swap($starttime, $endtime);
   }

   if( isset($_GET['startyear']) )
      $starttime = max($starttime, mktime(0,0,0
         ,isset($_GET['startmonth']) ? $_GET['startmonth'] : 1
         ,1,$_GET['startyear']));

   if( isset($_GET['endyear']) )
      $endtime = min($endtime, mktime(0,0,0
         ,isset($_GET['endmonth']) ? $_GET['endmonth']+1 : 13
         ,0,$_GET['endyear']));

   $endtime = max( $endtime, $starttime + GRAPH_RATING_MIN_INTERVAL);


   //prepare the graph

   $SizeX = max( 200, @$_GET['size'] > 0 ? $_GET['size'] : 640 );
   $SizeY = $SizeX * 3 / 4;

   $gr = new Graph($SizeX, $SizeY, substr($bg_color, 1, -1));

   $black = $gr->getcolor(0, 0, 0);
   $red = $gr->getcolor(205, 159, 156);
   $light_blue = $gr->getcolor(220, 229, 255);
   $number_color = $gr->getcolor(250, 100, 98);


   //fetch and prepare datas

   get_rating_data(@$_GET["uid"]);
   $nr_points = count($ratings);

   //$startnumber is the number of games before the graph start
   if( !SHOW_NRGAMES || !isset($number) || count($number) < 1 )
   {
      $startnumber = $endnumber = -1;
   }
   else
   {
      $startnumber = round($number[0]);
      $endnumber = round($number[count($number)-1]);
   }

   if( GRAPH_RATING_BY_NUM_ENABLED && $show_by_number )
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
   foreach( $m as $y )
   {
      $b= $gr->labelbox($y);
      $x= max($x,$b['x']);
   }
   $marge_left  = $gr->border+10 +$x;
   $marge_right = max(10,DASH_MODULO+2); //better if > DASH_MODULO
   $marge_top   = max(10,DASH_MODULO+2); //better if > DASH_MODULO
   $marge_bottom= $gr->border+ (SHOW_NRGAMES?3:2)*$gr->labelMetrics['LINEH'];

   $gr->setgraphbox(
      $marge_left,
      $marge_top,
      $gr->width-$marge_right,
      $gr->height-$marge_bottom
      );


   //scale datas

   $gr->setgraphview(
      $xlims['MIN'],
      $ymax,
      $xlims['MAX'],
      $ymin
      );

   $ratingmax = $gr->mapscaleY($ratingmax);
   $ratingmin = $gr->mapscaleY($ratingmin);
   $ratings = $gr->mapscaleY($ratings);

   $xvals = $gr->mapscaleX($xvals);


   //draw the blue array

   if( $nr_points > 1 )
      $gr->filledpolygon(
            array_merge(points_join($xvals, $ratingmax)
               ,array_reverse(points_join($ratingmin, $xvals))
               )
            ,2*$nr_points, $light_blue);



   //vertical scaling

   $step = 100; //i.e. 1 kyu step
   $start = ceil($ymin/$step)*$step;
   $gr->gridY( $start, $step, $gr->border
      , $ratinglabel, $black
      , '', $black);


   //horizontal scaling

   if( GRAPH_RATING_BY_NUM_ENABLED && $show_by_number )
   { // the X-axis is the number of games
      $step = 20.; //min grid distance in pixels
      $step/= $gr->sizeX; //graph width
      $step = ceil(($endnumber - $startnumber) * $step);

      if( $startnumber >= 0 )
      {
         function nbr2date($v){
            global $startnumber, $time, $datelabel;
            if( $startnumber >= 0 )
               if( isset($time[$v-=$startnumber]) )
                  return $datelabel($time[$v]);
            return '';
         }
         $y = $gr->boxbottom+3 +1*$gr->labelMetrics['LINEH'];
         $gr->gridX( $startnumber, $step, $y
            , 'nbr2date', $black
            , '', $red);
      }
      if( SHOW_NRGAMES )
      {
         $y = $gr->boxbottom+3;
         $x = $gr->label($gr->border, $y
                  , $T_('Games').':', $number_color);
         if( $startnumber >= 0 )
         {
            $x= $x['x'] +$gr->labelMetrics['WIDTH'];
            $gr->gridX( $startnumber, $step, $y
               , '', $number_color
               , '', $red, 0, $x);
         }
      }
   }
   else //!(GRAPH_RATING_BY_NUM_ENABLED && $show_by_number)
   { // the X-axis is the date of games
      $step = 20.; //min grid distance in pixels
      $step/= $gr->sizeX; //graph width
      $step/= 3600*24*30; //one month
      $step = ceil(($endtime - $starttime) * $step);

      $month = date('n',$starttime)+1;
      $year = date('Y',$starttime);
      $dategrid = create_function('$x',
         "return mktime(0,0,0,\$x,1,$year,0);" );
      $gr->gridX( $month, $step, $gr->boxbottom+3
         , $datelabel, $black
         , $dategrid, $red);

      if( SHOW_NRGAMES )
      {
         $y = $gr->boxbottom+3 +2*$gr->labelMetrics['LINEH'];
         $x = $gr->label($gr->border, $y
                  , $T_('Games').':', $number_color);
         if( $startnumber >= 0 )
         {
            function date2nbr($v){
               global $time, $number;
               $n= array_bsearch($v, $time);
               if( $n > 0 )
                  $n--;
               else
                  $n= 0;
               return round($number[$n]);
            }
            $x= $x['x'] +$gr->labelMetrics['WIDTH'];
            $gr->gridX( $month, $step, $y
               , 'date2nbr', $number_color
               , $dategrid, $red, 0, $x);
         }
      }
   }


   //draw the curves

   $gr->curve($xvals, $ratings, $nr_points, $black);


   //misc drawings

   if( ENA_WIN_PIE && $show_win_pie )
   {
      // set pie dimensions.
      $sx = $gr->width/6.; $sy = $sx/3.; $sz = $sx/16.;
      // set pie position.
      $cx = $gr->boxleft+4 +$sx/2;
      $cy = $gr->boxtop+4 +$sy/2;

      //empty portion = unrated games
      $datas[-1] = $owner_row['Finished']-$owner_row['RatedGames'];
      $datas[0] = $owner_row['Won'];
      $datas[1] = $owner_row['RatedGames']-$owner_row['Won']-$owner_row['Lost'];
      $datas[2] = $owner_row['Lost'];
      $color[0] = 0x289828; //win
      $color[1] = 0x3048F0; //jigo
      $color[2] = 0xff3838; //lost
      $gr->pie( $datas, $cx, $cy, $sx, $sy, $sz, $color);
   }

   if( @$_REQUEST['show_time'] )
      $gr->label($gr->offsetX, 0,
                 sprintf('%0.2f ms', (getmicrotime()-$page_microtime)*1000), $black);

   $gr->imagesend();
}


function get_rating_data($uid)
{
   global $ratings, $ratingmin, $ratingmax, $number,
      $time, $starttime, $endtime;

   if( !($uid > 0 ) )
      exit;


   $ratings = array();
   $ratingmax = array();
   $ratingmin = array();
   $time = array();
   $number = array();

   $bound_interval = GRAPH_RATING_MIN_INTERVAL/4;

   $query =
      "SELECT InitialRating AS Rating, " ;
   if( ENA_WIN_PIE )
   {
      $query.=
         "Running, Finished, RatedGames, Won, Lost," ;
   }
   $query.=
      "InitialRating+200+GREATEST(1600-InitialRating,0)*2/15 AS RatingMax, " .
      "InitialRating-200-GREATEST(1600-InitialRating,0)*2/15 AS RatingMin, " .
      "UNIX_TIMESTAMP(Registerdate) AS seconds " .
      "FROM Players WHERE ID=$uid" ;
   $result = mysql_query( $query)
      or error('mysql_query_failed', 'ratingpng.initial');

   if( @mysql_num_rows($result) != 1 )
      exit;

   $min_row = mysql_fetch_assoc($result);
   if( $starttime < $min_row['seconds'] - $bound_interval )
      $starttime = $min_row['seconds'] - $bound_interval;
   if( $endtime < $min_row['seconds'] + $bound_interval)
      $endtime = $min_row['seconds'] + $bound_interval;
   if( ENA_WIN_PIE )
   {
      global $owner_row;
      $owner_row = $min_row;
   }

   $result = mysql_query("SELECT MAX(UNIX_TIMESTAMP(Time)) AS seconds " .
                         "FROM Ratinglog WHERE uid=$uid")
      or error('mysql_query_failed', 'ratingpng.max_time');

   $max_row = mysql_fetch_assoc($result);
   if( $starttime > $max_row['seconds'] - $bound_interval )
      $starttime = $max_row['seconds'] - $bound_interval;
   if( $endtime > $max_row['seconds'] + $bound_interval)
      $endtime = $max_row['seconds'] + $bound_interval;

   if( ($endtime - $starttime) < GRAPH_RATING_MIN_INTERVAL )
   {
      $mean = ( $starttime + $endtime )/2 + 12*3600;
      $starttime = $mean - GRAPH_RATING_MIN_INTERVAL/2;
      $endtime = $starttime + GRAPH_RATING_MIN_INTERVAL;
   }


   $result = mysql_query("SELECT Rating, RatingMax, RatingMin, " .
                         "UNIX_TIMESTAMP(Time) AS seconds " .
                         "FROM Ratinglog WHERE uid=$uid ORDER BY Time")
      or error('mysql_query_failed', 'ratingpng.ratingdata');

   if( @mysql_num_rows( $result ) < 1 )
      exit;

   $numbercount = -1 ; //first point is Registerdate
   $first = true;
   $tmp = NULL;
   $row = $min_row;
   do
   {
      if( $row['seconds'] < $starttime )
      {
         $tmp = $row;
         $numbercount++ ;
         continue;
      }

      if( $first )
      {
         if( is_array($tmp) && $tmp['seconds'] < $starttime )
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

      if( $row['seconds'] > $endtime )
      {
         //interpolate the last curves points
         if( is_array($tmp) && $tmp['seconds'] <= $endtime )
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
   } while( $row = mysql_fetch_assoc($result) ) ;
}

?>
