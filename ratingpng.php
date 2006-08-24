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

require_once( "include/std_functions.php" );
require_once( "include/rating.php" );
require_once( "include/graph.php" );

define('IMAGE_BORDER',6);

//Display the number of games played below the date.
define('SHOW_NRGAMES',true);


function get_rating_data($uid)
{
   global $ratings, $ratingmin, $ratingmax, $nr_games,
      $time, $starttime, $endtime, $ratingpng_min_interval;

   $nr_games = -1 ; //first point is Registerdate

   if( !($uid > 0 ) )
      exit;


   $ratings = array();
   $ratingmax = array();
   $ratingmin = array();
   $time = array();

   $bound_interval = $ratingpng_min_interval/4;

   $result = mysql_query(
      "SELECT InitialRating AS Rating, " .
      "InitialRating+200+GREATEST(1600-InitialRating,0)*2/15 AS RatingMax, " .
      "InitialRating-200-GREATEST(1600-InitialRating,0)*2/15 AS RatingMin, " .
      "UNIX_TIMESTAMP(Registerdate) AS seconds " .
      "FROM Players WHERE ID=$uid")
      or error('mysql_query_failed', 'ratingpng.initial');

   if( @mysql_num_rows($result) != 1 )
      exit;

   $min_row = mysql_fetch_assoc($result);
   if( $starttime < $min_row['seconds'] - $bound_interval )
      $starttime = $min_row['seconds'] - $bound_interval;
   if( $endtime < $min_row['seconds'] + $bound_interval)
      $endtime = $min_row['seconds'] + $bound_interval;


   $result = mysql_query("SELECT MAX(UNIX_TIMESTAMP(Time)) AS seconds " .
                         "FROM Ratinglog WHERE uid=$uid")
      or error('mysql_query_failed', 'ratingpng.max_time');

   $max_row = mysql_fetch_assoc($result);
   if( $starttime > $max_row['seconds'] - $bound_interval )
      $starttime = $max_row['seconds'] - $bound_interval;
   if( $endtime > $max_row['seconds'] + $bound_interval)
      $endtime = $max_row['seconds'] + $bound_interval;

   if( ($endtime - $starttime) < $ratingpng_min_interval )
   {
      $mean = ( $starttime + $endtime )/2 + 12*3600;
      $starttime = $mean - $ratingpng_min_interval/2;
      $endtime = $starttime + $ratingpng_min_interval;
   }


   $result = mysql_query("SELECT Rating, RatingMax, RatingMin, " .
                         "UNIX_TIMESTAMP(Time) AS seconds " .
                         "FROM Ratinglog WHERE uid=$uid ORDER BY Time")
      or error('mysql_query_failed', 'ratingpng.ratingdata');

   if( @mysql_num_rows( $result ) < 1 )
      exit;

   $first = true;
   $tmp = NULL;
   $row = $min_row;
   do
   {
      if( $row['seconds'] < $starttime )
      {
         $tmp = $row;
         $nr_games++ ;
         continue;
      }

      if( $first )
      {
         if( is_array($tmp) && $tmp['seconds'] < $starttime )
         {
            array_push($ratings, scale2($tmp['Rating'], $row['Rating'],
                                        $tmp['seconds'], $starttime, $row['seconds']));
            array_push($ratingmin, scale2($tmp['RatingMin'], $row['RatingMin'],
                                          $tmp['seconds'], $starttime, $row['seconds']));
            array_push($ratingmax, scale2($tmp['RatingMax'], $row['RatingMax'],
                                          $tmp['seconds'], $starttime, $row['seconds']));
            array_push($time, $starttime);
            $nr_games--; //first point is not a game
         }
         $first = false;
      }

      if( $row['seconds'] > $endtime )
      {
         if( is_array($tmp) && $tmp['seconds'] <= $endtime )
         {
            array_push($ratings, scale2($tmp['Rating'], $row['Rating'],
                                        $tmp['seconds'], $endtime, $row['seconds']));
            array_push($ratingmin, scale2($tmp['RatingMin'], $row['RatingMin'],
                                          $tmp['seconds'], $endtime, $row['seconds']));
            array_push($ratingmax, scale2($tmp['RatingMax'], $row['RatingMax'],
                                          $tmp['seconds'], $endtime, $row['seconds']));
            array_push($time, $endtime);
         }
         break;
      }

      array_push($ratings, $row['Rating']);
      array_push($ratingmin, $row['RatingMin']);
      array_push($ratingmax, $row['RatingMax']);
      array_push($time, $row['seconds']);

      $tmp = $row;
   } while( $row = mysql_fetch_assoc($result) ) ;
}

function scale($x)
{
   global $MAX, $MIN, $SIZE, $OFFSET;
   if( $MAX == $MIN )
      return $OFFSET;
   return round( $OFFSET + (($x-$MIN)/($MAX-$MIN))*$SIZE);
}

function scale2($val1, $val3, $time1, $time2, $time3)
{
   if( $time1 == $time3 )
      return $val3;
   return $val3 + ($val1-$val3)*($time2-$time3)/($time1-$time3);
}

function scale_data()
{
   global $MAX, $MIN, $SIZE, $OFFSET, $SizeX, $SizeY,
      $max, $min, $ratings, $ratingmin, $ratingmax, $time, $endtime, $starttime;

   $SIZE = $SizeY-MARGE_BOTTOM-MARGE_TOP;
   $OFFSET = MARGE_TOP;
   $MIN = $max;
   $MAX = $min;

   $ratingmax = array_map("scale", $ratingmax);
   $ratingmin = array_map("scale", $ratingmin);
   $ratings = array_map("scale", $ratings);


   $SIZE = $SizeX-MARGE_LEFT-MARGE_RIGHT;
   $OFFSET = MARGE_LEFT;
   $MIN = $starttime;
   $MAX = $endtime;

   $time = array_map("scale", $time);
}

function interleave_data($arrayX, $arrayY)
{
   $array = array();

   while( list($dummy, $x) = each( $arrayX ) )
   {
      array_push($array, $x);
      list($dummy, $y) = each( $arrayY );
      array_push($array, $y);
   }

   return $array;
}


{

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

//   if( !$logged_in )
//      error("not_logged_in");


   //Disable translations in graph if not latin
   if( eregi( '^iso-8859-', $encoding_used) )
   {
      $keep_english= false;
      $T_= 'T_';
   }
   else
   {
      $keep_english= true;
      $T_= 'fnop';
   }


//Then draw the graph

   $SizeX = ( @$_GET['size'] > 0 ? $_GET['size'] : $defaultsize );
   $SizeY = $SizeX * 3 / 4;

   $im = imagecreate( $SizeX, $SizeY );
   list($r,$g,$b,$a)= split_RGBA(substr($bg_color, 2, 6), 0);
   $bg = imagecolorallocate ($im, $r,$g,$b); //first=background color

   $black = imagecolorallocate ($im, 0, 0, 0);
   $red = imagecolorallocate ($im, 205, 159, 156);
   $light_blue = imagecolorallocate ($im, 220, 229, 255);
   $nr_games_color = imagecolorallocate ($im, 250, 100, 98);


   //Just two string samples to evaluate MARGE_LEFT
   $x = array (echo_rating(100, 0,0,$keep_english), //20kyu
               echo_rating(3000, 0,0,$keep_english)); //10dan

   $m = 0;
   foreach( $x as $y )
   {
      $b= imagelabelbox($im, $y);
      $m= max($m,$b['x']);
   }
   define('MARGE_LEFT'  ,$m +IMAGE_BORDER+10);
   define('MARGE_TOP'   ,max(10,DASH_MODULO+2)); //Better if > DASH_MODULO
   define('MARGE_RIGHT' ,max(10,DASH_MODULO+2)); //Better if > DASH_MODULO
   define('MARGE_BOTTOM',IMAGE_BORDER+(SHOW_NRGAMES?3:2)*(LABEL_HEIGHT+LABEL_SEPARATION));



   $starttime = mktime(0,0,0,$BEGINMONTH,1,$BEGINYEAR);
   $endtime = $NOW + $ratingpng_min_interval;
   if( $endtime < $starttime )
   {
      $endtime = $starttime + $ratingpng_min_interval;
      if( $endtime < $starttime )
        swap($starttime, $endtime);
   }

   if( isset($_GET['startyear']) and isset($_GET['startmonth']) )
      $starttime = max($starttime, mktime(0,0,0,$_GET['startmonth'],1,$_GET['startyear']));

   if( isset($_GET['endyear']) and isset($_GET['endmonth']) )
      $endtime = min($endtime, mktime(0,0,0,$_GET['endmonth']+1,0,$_GET['endyear']));

   $endtime = max( $endtime, $starttime + $ratingpng_min_interval);

   get_rating_data(@$_GET["uid"]);
   //$nr_games is the number of games before the graph start

   $max = array_reduce($ratingmax, "max",-10000);
   $min = array_reduce($ratingmin, "min", 10000);


   scale_data();

   $nr_points = count($ratings);


   if( $nr_points > 1 )
      imagefilledpolygon($im,
                         array_merge(array_reverse(interleave_data($ratingmin, $time)),
                                     interleave_data($time, $ratingmax)),
                         2*$nr_points, $light_blue);


   //vertical scaling

   $SIZE = $SizeY-MARGE_BOTTOM-MARGE_TOP;
   $OFFSET = MARGE_TOP;
   $MIN = $max;
   $MAX = $min;

   imagesetdash($im, $black);

   $v = ceil($MAX/100)*100;
   $a = MARGE_LEFT-4 ;
   $b = $SizeX ;
   $b = $b - ((($b-$a) % DASH_MODULO)+1) ; //so all lines start in the same way
   $y = $SizeY ;
   while( $v < $MIN )
   {
      $sc = scale($v);
      imageline($im, $a, $sc, $b, $sc, IMG_COLOR_STYLED);
      if ( $y > $sc )
      {
         imagelabel($im, IMAGE_BORDER, $sc-LABEL_ALIGN
                  , echo_rating($v, 0,0,$keep_english), $black);
         $y = $sc - LABEL_HEIGHT ;
      }
      $v += 100;
   }


   //horizontal scaling

   $SIZE = $SizeX-MARGE_LEFT-MARGE_RIGHT;
   $OFFSET = MARGE_LEFT;
   $MIN = $starttime;
   $MAX = $endtime;

   imagesetdash($im, $red);

   $x = MARGE_LEFT-1 ;
   if (SHOW_NRGAMES)
   {
      $x= max($x,LABEL_WIDTH+
            imagelabel($im, IMAGE_BORDER
                     , $SizeY-MARGE_BOTTOM+3+ 2*(LABEL_SEPARATION+LABEL_HEIGHT)
                     , $T_('Games').':', $nr_games_color));
   }

   $year = date('Y',$starttime);
   $month = date('n',$starttime)+1;

   $step = ceil(($endtime - $starttime)/(3600*24*30) * 20 / $SIZE);
   $no_text = true;
   $b = $SizeY-MARGE_BOTTOM+3 ;
   $a = MARGE_TOP ;
   $a = $a - (DASH_MODULO-((($b-$a) % DASH_MODULO)+1)) ;
   $ix_games = 0 ;
   $y = $SizeY-MARGE_BOTTOM+3;
   for(;;$month+=$step)
   {
      $dt = mktime(0,0,0,$month,1,$year);
      if( $dt > $endtime )
      {
         if( !$no_text ) break;
         $dt = $starttime;
         $sc = scale($dt);
      }
      else
      {
         $sc = scale($dt);
         imageline($im, $sc, $a, $sc, $b, IMG_COLOR_STYLED);
      }

      $no_text = false;
      if ($x >= $sc)
         continue;

      $x= max($x,LABEL_WIDTH+
            imagelabel($im, $sc, $y,
                        $T_(date('M', $dt)), $black));
      $x= max($x,LABEL_WIDTH+
            imagelabel($im, $sc, $y+LABEL_HEIGHT+LABEL_SEPARATION,
                        date('Y', $dt), $black));

      if (SHOW_NRGAMES)
      {
         while ($ix_games < $nr_points && $time[$ix_games] <= $sc)
            $ix_games++;
         $v = max( 0, $nr_games+$ix_games);
         $x= max($x,LABEL_WIDTH+
               imagelabel($im, $sc, $y+2*LABEL_HEIGHT+2*LABEL_SEPARATION,
                           $v, $nr_games_color));
      }
   }

   imagecurve($im, $time, $ratings, $nr_points, $black);


   if( @$_GET['show_time'] == 'y')
      imagelabel($im, MARGE_LEFT, 0,
                 sprintf('%0.2f ms', (getmicrotime()-$page_microtime)*1000), $black);


   imagesend($im);
}
?>