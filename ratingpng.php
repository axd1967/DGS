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

require_once( "include/std_functions.php" );
require_once( "include/rating.php" );

$defaltsize = 640;

function get_rating_data($uid)
{
   global $ratings, $ratingmin, $ratingmax,
      $time, $starttime, $endtime, $ratingpng_min_interval;

   if( !($uid > 0 ) )
      exit;


   $ratings = array();
   $ratingmax = array();
   $ratingmin = array();
   $time = array();

   $result = mysql_query(
      "SELECT InitialRating AS Rating, " .
      "InitialRating+200+GREATEST(1600-InitialRating,0)*2/15 AS RatingMax, " .
      "InitialRating-200-GREATEST(1600-InitialRating,0)*2/15 AS RatingMin, " .
      "UNIX_TIMESTAMP(Registerdate) AS seconds " .
      "FROM Players WHERE ID=$uid");

   if( mysql_num_rows($result) != 1 )
      exit;


   $tmp = mysql_fetch_array($result);

   $min_interval = min( $ratingpng_min_interval, $NOW - $tmp['seconds'] );

   if( $starttime < $tmp['seconds'] - 2*24*3600 )
      $starttime = $tmp['seconds'] - 2*24*3600;

   if( $endtime < $tmp['seconds'] + $min_interval/2 )
      $endtime = $tmp['seconds'] + $min_interval/2;

   $result = mysql_query("SELECT MAX(UNIX_TIMESTAMP(Time)) AS seconds " .
                         "FROM Ratinglog WHERE uid=$uid") or die(mysql_error());

   $max_row = mysql_fetch_array($result);
   if( $endtime > $max_row['seconds'] + 2*24*3600)
      $endtime = $max_row['seconds'] + 2*24*3600;

   if( $starttime > $max_row['seconds'] - $min_interval )
      $starttime = $max_row['seconds'] - $min_interval;

   if( $endtime - $starttime < $min_interval )
   {
      $mean = ( $starttime + $endtime )/2;
      $starttime = $mean - $min_interval/2;
      $endtime = $mean + $min_interval/2;
   }

   $result = mysql_query("SELECT Rating, RatingMax, RatingMin, " .
                         "UNIX_TIMESTAMP(Time) AS seconds " .
                         "FROM Ratinglog WHERE uid=$uid ORDER BY Time") or die(mysql_error());

   if( mysql_num_rows( $result ) < 2 )
      exit;

   $first = true;
   while( $row = mysql_fetch_array($result) )
   {
      if( $row['seconds'] < $starttime )
         {
            $tmp = $row;
            continue;
         }

      if( $first )
      {
         array_push($ratings, scale2($tmp['Rating'], $row['Rating'],
                                     $tmp['seconds'], $starttime, $row['seconds']));
         array_push($ratingmin, scale2($tmp['RatingMin'], $row['RatingMin'],
                                     $tmp['seconds'], $starttime, $row['seconds']));
         array_push($ratingmax, scale2($tmp['RatingMax'], $row['RatingMax'],
                                     $tmp['seconds'], $starttime, $row['seconds']));
         array_push($time, $starttime);
         $first = false;
      }

      if( $row['seconds'] > $endtime )
      {
         array_push($ratings, scale2($tmp['Rating'], $row['Rating'],
                                     $tmp['seconds'], $endtime, $row['seconds']));
         array_push($ratingmin, scale2($tmp['RatingMin'], $row['RatingMin'],
                                       $tmp['seconds'], $endtime, $row['seconds']));
         array_push($ratingmax, scale2($tmp['RatingMax'], $row['RatingMax'],
                                       $tmp['seconds'], $endtime, $row['seconds']));
         array_push($time, $endtime);
         break;
      }

      array_push($ratings, $row['Rating']);
      array_push($ratingmin, $row['RatingMin']);
      array_push($ratingmax, $row['RatingMax']);
      array_push($time, $row['seconds']);

      $tmp = $row;
   }
}

function scale($x)
{
   global $MAX, $MIN, $SIZE, $OFFSET;

   return round( $OFFSET + (($x-$MIN)/($MAX-$MIN))*$SIZE);
}

function scale2($val1, $val3, $time1, $time2, $time3)
{
   return $val3 + ($val1-$val3)*($time2-$time3)/($time1-$time3);
}

function scale_data()
{
   global $MAX, $MIN, $SIZE, $OFFSET, $SizeX, $SizeY,
      $ratings, $ratingmin, $ratingmax, $time, $endtime, $starttime;

   $MIN = array_reduce($ratingmax, "max");
   $MAX = array_reduce($ratingmin, "min");
   $SIZE = $SizeY-40;
   $OFFSET = 10;

   $ratingmax = array_map("scale", $ratingmax);
   $ratingmin = array_map("scale", $ratingmin);
   $ratings = array_map("scale", $ratings);


   $MAX = $endtime;
   $MIN = $starttime;
   $SIZE = $SizeX-60;
   $OFFSET = 50;

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

function imagemultiline($im, $points, $nr_points,$color)
{
   for( $i=0; $i<$nr_points-1; $i++)
      imageline($im, $points[2*$i],$points[2*$i+1],$points[2*$i+2],$points[2*$i+3],$color);
}






{
   $microtime = getmicrotime();

   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

//   if( !$logged_in )
//      error("not_logged_in");

   $SizeX = ( $_GET['size'] > 0 ? $_GET['size'] : $defaltsize );
   $SizeY = $SizeX * 3 / 4;


   $starttime = 0;
   if( isset($_GET['startyear']) and isset($_GET['startmonth']) )
      $starttime = min($NOW, mktime(0,0,0,$_GET['startmonth'],0,($_GET['startyear'])));

   $endtime = $NOW;
   if( isset($_GET['endyear']) and isset($_GET['endmonth']) )
      $endtime = min($NOW, mktime(0,0,0,$_GET['endmonth']+1,0,($_GET['endyear'])));

   get_rating_data($_GET["uid"]);

   $max = array_reduce($ratingmax, "max");
   $min = array_reduce($ratingmin, "min");

   scale_data();

   $nr_points = count($ratings);

   $im = imagecreate( $SizeX, $SizeY );

   $bg = imagecolorallocate ($im, 247, 245, 227);
   $black = imagecolorallocate ($im, 0, 0, 0);
   $light_blue = imagecolorallocate ($im, 220, 229, 255);
   $red = imagecolorallocate ($im, 205, 159, 156);

   if( count($time) > 1 )
      imagefilledpolygon($im,
                         array_merge(array_reverse(interleave_data($ratingmin, $time)),
                                     interleave_data($time, $ratingmax)),
                         2*$nr_points, $light_blue);

   $MAX = $min;
   $MIN = $max;
   $SIZE = $SizeY-40;
   $OFFSET = 10;

   imagesetstyle ($im, array($black,$black,IMG_COLOR_TRANSPARENT,IMG_COLOR_TRANSPARENT,
                             IMG_COLOR_TRANSPARENT,IMG_COLOR_TRANSPARENT));

   $v = ceil($min/100)*100;

   while( $v < $max )
   {
      imageline($im, 42, scale($v), $SizeX, scale($v), IMG_COLOR_STYLED);
      imagestring ($im, 2, 4, scale($v)-7,  echo_rating($v, false), $black);
      $v += 100;
   }

   $MIN = $starttime;
   $MAX = $endtime;
   $SIZE = $SizeX-60;
   $OFFSET = 50;

   imagesetstyle ($im, array($red,$red,IMG_COLOR_TRANSPARENT,IMG_COLOR_TRANSPARENT,
                             IMG_COLOR_TRANSPARENT,IMG_COLOR_TRANSPARENT));

   $year = date('Y',$starttime);
   $month = date('n',$starttime)+1;

   $step = ceil(($endtime - $starttime)/(3600*24*30) * 30 / ($SizeX-60));
   $no_text = true;

   for(;;$month+=$step)
   {
      $dt = mktime(0,0,0,$month,1,$year);
      if( $dt > $endtime )
      {
         if( !$no_text ) break;
         $dt = $starttime;
      }
      else
         imageline($im, scale($dt), 10, scale($dt), $SizeY-27, IMG_COLOR_STYLED);

      imagestring($im, 2, scale($dt)+2, $SizeY-27,  T_(date('M', $dt)), $black);
      imagestring($im, 2, scale($dt)+2, $SizeY-15,  date('Y', $dt), $black);
      $no_text = false;
   }

   if( $_GET['show_time'] == 'y' )
      imagestring($im, 2, 50, 0, sprintf('%0.2f', (getmicrotime()-$microtime)*1000), $black);

   imagemultiline($im, interleave_data($time, $ratings), $nr_points, $black);

   header("Content-type: image/png");
   imagePNG($im);
   imagedestroy($im);
}
?>