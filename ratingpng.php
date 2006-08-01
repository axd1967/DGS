<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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

require( "include/std_functions.php" );
require( "include/rating.php" );

$defaltsize = 800;

function get_rating_data($uid)
{
   global $ratings, $ratingmin, $ratingmax, $time;

   if( !($uid > 0 ) )
      exit;


   $ratings = array();
   $ratingmax = array();
   $ratingmin = array();
   $time = array();

   $result = mysql_query("SELECT InitialRating, " .
                         "UNIX_TIMESTAMP(Registerdate) AS regdate " .
                         "FROM Players WHERE ID=$uid");

   if( mysql_num_rows($result) != 1 )
      exit;

   $row = mysql_fetch_array($result);

   $init = $row['InitialRating'];
   array_push($ratings, $init);
   array_push($ratingmax, $init + 200 + max(1600-$init,0)*2/15);
   array_push($ratingmin, $init - 200 - max(1600-$init,0)*2/15);
   array_push($time, $row['regdate']);

   $result = mysql_query("SELECT Rating, RatingMax, RatingMin, " .
                         "UNIX_TIMESTAMP(Time) as seconds " .
                         "FROM Ratinglog WHERE uid=$uid") or die(mysql_error());

   if( mysql_num_rows( $result ) < 2 )
      exit;

   while( $row = mysql_fetch_array($result) )
   {
      array_push($ratings, $row['Rating']);
      array_push($ratingmin, $row['RatingMin']);
      array_push($ratingmax, $row['RatingMax']);

      array_push($time, $row['seconds']);
   }
}

function scale($x)
{
   global $MAX, $MIN, $SIZE, $OFFSET;

   return round( $OFFSET + (($x-$MIN)/($MAX-$MIN))*$SIZE);
}


function scale_data()
{
   global $MAX, $MIN, $SIZE, $OFFSET, $SizeX, $SizeY,
      $ratings, $ratingmin, $ratingmax, $time;

   $MIN = array_reduce($ratingmax, "max");
   $MAX = array_reduce($ratingmin, "min");
   $SIZE = $SizeY-40;
   $OFFSET = 10;

   $ratingmax = array_map("scale", $ratingmax);
   $ratingmin = array_map("scale", $ratingmin);
   $ratings = array_map("scale", $ratings);


   $MAX = array_reduce($time, "max");
   $MIN = array_reduce($time, "min");
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
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $SizeX = ( $_GET['size'] > 0 ? $_GET['size'] : $defaltsize );
   $SizeY = $SizeX * 3 / 4;

   get_rating_data($_GET["uid"]);

   $max = array_reduce($ratingmax, "max");
   $min = array_reduce($ratingmin, "min");

   $maxtime = array_reduce($time, "max");
   $mintime = array_reduce($time, "min");

   scale_data();

   $nr_points = count($ratings);

   $im = imagecreate( $SizeX, $SizeY );

//     echo $nr_points;
//     print_r($ratings);
//     echo '<p>';
//     exit;

   $bg = imagecolorallocate ($im, 247, 245, 227);
   $black = imagecolorallocate ($im, 0, 0, 0);
   $light_blue = imagecolorallocate ($im, 220, 229, 255);
   $red = imagecolorallocate ($im, 205, 159, 156);
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

   $MIN = $mintime;
   $MAX = $maxtime;
   $SIZE = $SizeX-60;
   $OFFSET = 50;

   imagesetstyle ($im, array($red,$red,IMG_COLOR_TRANSPARENT,IMG_COLOR_TRANSPARENT,
                             IMG_COLOR_TRANSPARENT,IMG_COLOR_TRANSPARENT));

   $year = date('Y',$mintime);
   $month = date('n',$mintime)+1;

   $step = ceil(($maxtime - $mintime)/(3600*24*30) * 30 / ($SizeX-60));
   $no_text = true;

   for(;;$month+=$step)
   {
      $dt = mktime(0,0,0,$month,1,$year);
      if( $dt > $maxtime )
      {
         if( !$no_text ) break;
         $dt = $mintime;
      }
      else
         imageline($im, scale($dt), 10, scale($dt), $SizeY-27, IMG_COLOR_STYLED);

      imagestring($im, 2, scale($dt)+2, $SizeY-27,  date('M', $dt), $black);
      imagestring($im, 2, scale($dt)+2, $SizeY-15,  date('Y', $dt), $black);
      $no_text = false;
   }


   imagemultiline($im, interleave_data($time, $ratings), $nr_points, $black);

   header("Content-type: image/png");
   imagePNG($im);
   imagedestroy($im);
}
?>