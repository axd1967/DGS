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


{

   $defaultsize = 640;

//Check font and find pagging constantes

// Font name, if it's not found a non-ttf font is used instead (with imagestring())
   $x = 'FreeSans-Medium' ;

   if ( isset($_GET['font']) )
      $x = $_GET['font'] ;

   define('TTF_FONT',"/var/lib/defoma/fontconfig.d/F/$x.ttf"); // Font path


   if ( function_exists('imagettftext') //TTF need GD and Freetype.
        && is_file(TTF_FONT) //...and access rights check if needed
        //&& 0
   )
   {

      define('LABEL_FONT'  ,-1);
      define('LABEL_HEIGHT',9);
      define('LABEL_SEPARATION',3);

      if( function_exists('imagettfbbox') )
      {
         function imagelabelbox(&$im, $str)
         {
            /*
            0=>X, 1=>Y ;bottom left
            2=>X, 3=>Y ;bottom right
            4=>X, 5=>Y ;top right
            6=>X, 7=>Y ;top left
            (i.e. the polygon of the bounding rectangle)
            */
            $b= imagettfbbox( LABEL_HEIGHT, 0, TTF_FONT, $str);
            return array( 'x'=>$b[2]-$b[6], 'y'=>$b[3]-$b[7]);
         }

         $b= imagettfbbox(LABEL_HEIGHT, 0, TTF_FONT, 'MImi');
         define('LABEL_ALIGN', $b[3]-$b[7] +1);
         //LABEL_WIDTH is just an average to be used as space
         define('LABEL_WIDTH', ($b[2]-$b[6])/4 +1);

      }
      else
      {
         function imagelabelbox(&$im, $str)
         {
            //IMG_COLOR_TRANSPARENT does not work, so draw it out of bound
            $b= imagettftext($im, LABEL_HEIGHT, 0, -100, -100, 0, TTF_FONT, $str);
            return array( 'x'=>$b[2]-$b[6], 'y'=>$b[3]-$b[7]);
         }

         define('LABEL_ALIGN',LABEL_HEIGHT +1);
         define('LABEL_WIDTH',LABEL_HEIGHT*4/5);

      }

      //$x,$y is the upper left corner
      function imagelabel(&$im, $x, $y, $str, $color)
      {
/* to compare with embedded fonts
         $f = 2;
         $w = ImageFontWidth($f);
         $h = ImageFontHeight($f)-1;
         $b = $x + strlen($str)*$w ;
         global $black; imagerectangle($im, $x, $y, $b, $y+$h, $black);
         imagestring($im, $f, $x, $y, $str, $color);
*/

         $b= imagettftext($im, LABEL_HEIGHT, 0, $x, $y+LABEL_ALIGN, $color, TTF_FONT, $str);
         //global $red; imagerectangle($im, $b[6], $b[7], $b[2], $b[3], $red);
         return $b[2] ;
      }

   }
   else //True type font problem, so use embedded fonts:
   {

      define('LABEL_FONT'  ,2);
      define('LABEL_HEIGHT',ImageFontHeight(LABEL_FONT)-1);
      define('LABEL_SEPARATION',1);
      define('LABEL_ALIGN',LABEL_HEIGHT*4/5);
      define('LABEL_WIDTH',ImageFontWidth(LABEL_FONT));

      function imagelabelbox(&$im, $str)
      {
         return array( 'x'=>strlen($str)*LABEL_WIDTH, 'y'=>LABEL_HEIGHT);
      }

      //$x,$y is the upper left corner
      function imagelabel(&$im, $x, $y, $str, $color)
      {
         $b = $x + strlen($str)*LABEL_WIDTH ;
         //global $red; imagerectangle($im, $x, $y, $b, $y+LABEL_HEIGHT, $red);
         imagestring($im, LABEL_FONT, $x, $y, $str, $color);
         return $b ;
      }
   }

   //For IMG_COLOR_STYLED alignment = count of imagesetstyle() arrays.
   define('DASH_MODULO' ,6);
   function imagesetdash(&$im, $col)
   {
      imagesetstyle ($im, array($col,$col,
                                IMG_COLOR_TRANSPARENT,IMG_COLOR_TRANSPARENT,
                                IMG_COLOR_TRANSPARENT,IMG_COLOR_TRANSPARENT));
   }

   function imagecurve(&$im, &$pointX, &$pointY, $nr_points, $color)
   {
      for( $i=1; $i<$nr_points; $i++)
         imageline($im, $pointX[$i-1],$pointY[$i-1],$pointX[$i],$pointY[$i],$color);
   }

   //like imagepolygon() but open
   function imagemultiline(&$im, &$points, $nr_points, $color)
   {
      for( $i=0; $i<$nr_points-1; $i++)
         imageline($im, $points[2*$i],$points[2*$i+1],$points[2*$i+2],$points[2*$i+3],$color);
   }

   function imagesend(&$im)
   {
      if (function_exists("imagepng"))
      {
         header("Content-type: image/png");
         imagepng($im);
      } else
      if (function_exists("imagegif"))
      {
         header("Content-type: image/gif");
         imagegif($im);
      } else
      if (function_exists("imagejpeg"))
      {
         header("Content-type: image/jpeg");
         imagejpeg($im, "", 0.5);
      } else
      if (function_exists("imagewbmp"))
      {
         header("Content-type: image/vnd.wap.wbmp");
         imagewbmp($im);
      } else
      {
         imagedestroy($im);
         die("No PHP graphic support on this server");
      }
      imagedestroy($im);
   }

}
?>
