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

$defaultsize = 640;

//Display the number of games played below the date.
define('SHOW_NRGAMES',true);

//For IMG_COLOR_STYLED alignment = count of both imagesetstyle() arrays.
define('DASH_MODULO' ,6);


function get_rating_data($uid)
{
   global $ratings, $ratingmin, $ratingmax, $nr_games,
      $time, $starttime, $endtime, $ratingpng_min_interval;
   global $NOW;

   $nr_games = -1 ; //first point is Registerdate

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


   $row = mysql_fetch_assoc($result);

   $min_interval = min( $ratingpng_min_interval, $NOW - $row['seconds'] );

   if( $starttime < $row['seconds'] - $ratingpng_min_interval/2 )
      $starttime = $row['seconds'] - $ratingpng_min_interval/2;

   if( $endtime < $row['seconds'] + $min_interval/2 )
      $endtime = $row['seconds'] + $min_interval/2;

   $result = mysql_query("SELECT MAX(UNIX_TIMESTAMP(Time)) AS seconds " .
                         "FROM Ratinglog WHERE uid=$uid") or die(mysql_error());

   $max_row = mysql_fetch_assoc($result);
   if( $endtime > $max_row['seconds'] + $ratingpng_min_interval/2)
      $endtime = $max_row['seconds'] + $ratingpng_min_interval/2;

   if( $starttime > $max_row['seconds'] - $min_interval/2 )
      $starttime = $max_row['seconds'] - $min_interval/2;

   if( ($endtime - $starttime) < $min_interval )
   {
      $mean = ( $starttime + $endtime )/2 + 12*3600;
      $starttime = $mean - $min_interval/2;
      $endtime = $starttime + $min_interval;
   }

   $result = mysql_query("SELECT Rating, RatingMax, RatingMin, " .
                         "UNIX_TIMESTAMP(Time) AS seconds " .
                         "FROM Ratinglog WHERE uid=$uid ORDER BY Time") or die(mysql_error());

   if( mysql_num_rows( $result ) < 1 )
      exit;

   $first = true;
   $tmp = NULL;
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

function imagemultiline($im, $points, $nr_points, $color)
{
   for( $i=0; $i<$nr_points-1; $i++)
      imageline($im, $points[2*$i],$points[2*$i+1],$points[2*$i+2],$points[2*$i+3],$color);
}






{
   $microtime = getmicrotime();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

//   if( !$logged_in )
//      error("not_logged_in");

// globals used by echo_rating()
   $dan = T_('dan');
   $kyu = T_('kyu');


//First check font and find pagging constantes


// Font name, if it's not found a non-ttf font is used instead (with imagestring())
   $x = 'FreeSans-Medium' ;

   if ( isset($_GET['font']) )
      $x = $_GET['font'] ;

   define('TTF_FONT',"/var/lib/defoma/fontconfig.d/F/$x.ttf"); // Font path


//Just two string samples to evaluate MARGE_LEFT
   $x = array (echo_rating(100, false), //20kyu
               echo_rating(3000, false)); //10dan

   if ( function_exists('imagettftext') //TTF need GD and Freetype.
        && is_file(TTF_FONT) //...and access rights check if needed
   )
   {
      define('LABEL_FONT'  ,-1);
      define('LABEL_HEIGHT',9);
      define('LABEL_SEPARATION',3);
      $m = $v = 0;
      foreach( $x as $y )
         {
            $b= imagettfbbox(LABEL_HEIGHT, 0, TTF_FONT, $y);
            $a = $b[2]-$b[6] +1 ;
            if( $a > $m )
            {
               $m = $a ;
               $v = $a/strlen($y) ;
            }
         }
      define('LABEL_MIDDLE', $b[3]-$b[7] +1);
      define('LABEL_WIDTH' , $v +1);
      define('MARGE_LEFT'  , $m +15);

      function imagelabel($im, $x, $y, $str, $color)
         {
            $b= imagettftext($im, LABEL_HEIGHT, 0, $x, $y+LABEL_MIDDLE, $color, TTF_FONT, $str);
            //global $red; imagerectangle($im, $b[6], $b[7], $b[2], $b[3], $red);
            return $b[2]+LABEL_WIDTH ;
         }
   }
   else //True type font file problem, so use embedded fonts:
   {
      define('LABEL_FONT'  ,2);
      define('LABEL_HEIGHT',ImageFontHeight(LABEL_FONT)-1);
      define('LABEL_SEPARATION',1);
      define('LABEL_WIDTH' ,ImageFontWidth(LABEL_FONT));
      define('LABEL_MIDDLE',LABEL_HEIGHT*2/3);
      $m = 0;
      foreach( $x as $y )
         $m = max( $m , strlen($y)*LABEL_WIDTH ) ;
      define('MARGE_LEFT'  , $m +15);

      function imagelabel($im, $x, $y, $str, $color)
         {
            $b = $x + strlen($str)*LABEL_WIDTH ;
            //global $red; imagerectangle($im, $x, $y, $b, $y+LABEL_HEIGHT, $red);
            imagestring($im, LABEL_FONT, $x, $y, $str, $color);
            return $b+LABEL_WIDTH ;
         }
   }

   define('MARGE_TOP'   ,max(10,DASH_MODULO+2)); //Better if > DASH_MODULO
   define('MARGE_RIGHT' ,max(10,DASH_MODULO+2)); //Better if > DASH_MODULO
   define('MARGE_BOTTOM',6+(SHOW_NRGAMES?3:2)*(LABEL_HEIGHT+LABEL_SEPARATION));


//Then draw the graph


   $SizeX = ( @$_GET['size'] > 0 ? $_GET['size'] : $defaultsize );
   $SizeY = $SizeX * 3 / 4;


   $starttime = mktime(0,0,0,$BEGINMONTH,1,$BEGINYEAR);

   if( isset($_GET['startyear']) and isset($_GET['startmonth']) )
      $starttime = max($starttime, mktime(0,0,0,$_GET['startmonth'],1,$_GET['startyear']));

   $endtime = $NOW + $ratingpng_min_interval;
   if( isset($_GET['endyear']) and isset($_GET['endmonth']) )
      $endtime = min($endtime, mktime(0,0,0,$_GET['endmonth']+1,0,$_GET['endyear']));

   get_rating_data(@$_GET["uid"]);

   $max = array_reduce($ratingmax, "max", -10000);
   $min = array_reduce($ratingmin, "min", +10000);

   scale_data();

   $nr_points = count($ratings);

   $im = imagecreate( $SizeX, $SizeY );

   $bg = imagecolorallocate ($im, 247, 245, 227);
   $black = imagecolorallocate ($im, 0, 0, 0);
   $nr_games_color = imagecolorallocate ($im, 250, 100, 98);
   $light_blue = imagecolorallocate ($im, 220, 229, 255);
   $red = imagecolorallocate ($im, 205, 159, 156);


   if( $nr_points > 1 )
      imagefilledpolygon($im,
                         array_merge(array_reverse(interleave_data($ratingmin, $time)),
                                     interleave_data($time, $ratingmax)),
                         2*$nr_points, $light_blue);


   $SIZE = $SizeY-MARGE_BOTTOM-MARGE_TOP;
   $OFFSET = MARGE_TOP;
   $MIN = $max;
   $MAX = $min;

   imagesetstyle ($im, array($black,$black,IMG_COLOR_TRANSPARENT,IMG_COLOR_TRANSPARENT,
                             IMG_COLOR_TRANSPARENT,IMG_COLOR_TRANSPARENT));

   $v = ceil($min/100)*100;
   $a = MARGE_LEFT-4 ;
   $b = $SizeX- (($SizeX-$a) % DASH_MODULO)-1 ; //so all lines start in the same way
   $y = $SizeY ;
   while( $v < $max )
   {
      $sc = scale($v);
      imageline($im, $a, $sc, $b, $sc, IMG_COLOR_STYLED);
      if ( $y > $sc )
      {
         imagelabel ($im, 4, $sc-LABEL_MIDDLE, echo_rating($v, false), $black);
         $y = $sc - LABEL_HEIGHT ;
      }
      $v += 100;
   }


   $SIZE = $SizeX-MARGE_LEFT-MARGE_RIGHT;
   $OFFSET = MARGE_LEFT;
   $MIN = $starttime;
   $MAX = $endtime;

   imagesetstyle ($im, array($red,$red,IMG_COLOR_TRANSPARENT,IMG_COLOR_TRANSPARENT,
                             IMG_COLOR_TRANSPARENT,IMG_COLOR_TRANSPARENT));

   $x = 0;
   if (SHOW_NRGAMES)
   {
      $x = max($x,imagelabel($im, 4,
                             $SizeY-MARGE_BOTTOM+3+ 2*(LABEL_SEPARATION+LABEL_HEIGHT),
                             T_('Games').':', $nr_games_color));
   }

   $year = date('Y',$starttime);
   $month = date('n',$starttime)+1;

   $step = ceil(($endtime - $starttime)/(3600*24*30) * 20 / $SIZE);
   $no_text = true;
   $b = $SizeY-MARGE_BOTTOM+3 ;
   $a = MARGE_TOP -DASH_MODULO+(($b-MARGE_TOP) % DASH_MODULO)+1 ;
   $ix_games = 0 ;
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

      $x= max($x,imagelabel($im, $sc, $SizeY-MARGE_BOTTOM+3,  T_(date('M', $dt)), $black));
      $x= max($x,imagelabel($im, $sc, $SizeY-MARGE_BOTTOM+3+LABEL_HEIGHT+LABEL_SEPARATION,
                            date('Y', $dt), $black));
      if (SHOW_NRGAMES)
      {
         while ($ix_games < $nr_points && $time[$ix_games] <= $sc)
            $ix_games++;
         $v = max( 0, $nr_games+$ix_games);
         $x= max($x,imagelabel($im, $sc,
                               $SizeY-MARGE_BOTTOM+3+2*LABEL_HEIGHT+2*LABEL_SEPARATION,
                               $v, $nr_games_color));
      }
   }

   if( @$_GET['show_time'] == 'y')
      imagelabel($im, MARGE_LEFT, 0,
                 sprintf('%0.2f ms', (getmicrotime()-$microtime)*1000), $black);

   imagemultiline($im, interleave_data($time, $ratings), $nr_points, $black);

   header("Content-type: image/png");
   imagePNG($im);
   imagedestroy($im);
}
?>