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


/*!
 * \file graph.php
 *
 * \brief Functions for creating graph images.
 */


/* True Type Font:
 * if not found a non-ttf font is used (the PHP embedded one via imagestring())
 * TTF_NAME:
 *  default name, set it to '' to use embedded font by default
 *  for Windows, something like 'ARIAL' or 'COUR'
 *  for Linux, something like 'FreeSans-Medium'
 * TTF_PATH:
 *  set it to '' to disable TTF.
 *  the %s will be replaced by the font name.
 *  for Windows, something like 'C:/WINDOWS/FONTS/%s.TTF'
 *  for Linux, something like '/var/lib/defoma/fontconfig.d/F/%s.ttf'
 * TTF_HEIGHT:
 *  adjust it to match the embedded font height
 */
   define('TTF_PATH', '/var/lib/defoma/fontconfig.d/F/%s.ttf'); // Font path
   define('TTF_NAME', 'FreeSans-Medium'); //Font name
   define('TTF_HEIGHT', 10); //Font height

//if( function_exists('imagefilledarc') && defined('IMG_ARC_PIE') )
   define('GD2_ARC',function_exists('imagefilledarc') && defined('IMG_ARC_PIE'));

if( !defined('M_2PI') )
   define('M_2PI', 2*M_PI);

   //For IMG_COLOR_STYLED alignment = count of imagesetstyle() arrays.
   define('DASH_MODULO',6); //see setdash()

   define('PIE_SHADOW_FACTOR', .70);


   /*! \publicsection */

function scale($x, $min, $max, $offset, $size)
{
   if( $min == $max || $size < 1 )
      return round( $offset);
   return round( $offset + (($x-$min)/($max-$min))*$size );
} //scale

function gscale($x)
{
 global $GSCALEMIN, $GSCALEMAX, $GSCALEOFFSET, $GSCALESIZE;
   if( $GSCALEMIN == $GSCALEMAX || $GSCALESIZE < 1 )
      return round( $GSCALEOFFSET);
   return round( $GSCALEOFFSET + (($x-$GSCALEMIN)/($GSCALEMAX-$GSCALEMIN))*$GSCALESIZE );
} //gscale

function gscaleinit($min, $max, $offset, $size)
{
 global $GSCALEMIN, $GSCALEMAX, $GSCALEOFFSET, $GSCALESIZE;
   $GSCALEMIN = $min;
   $GSCALEMAX = $max;
   $GSCALEOFFSET = $offset;
   $GSCALESIZE = $size;
} //gscaleinit

function getellipticpoint($x, $y, $w, $h, $radian)
{
   //the Y axis is inversed, the rotation too! (clockwise)
   return array(
      $x + round( cos($radian) * $w),
      $y + round( sin($radian) * $h),
      );
/* Must follow this charter:
   while( $radian >= M_2PI ) $radian-= M_2PI;
   while( $radian < 0 ) $radian+= M_2PI;
   $sw = $sh = 1;
   if( $radian > M_PI )
   {
      $radian = M_2PI-$radian;
      $sh = -$sh;
   }
   if( $radian > M_PI_2 )
   {
      $radian = M_PI-$radian;
      $sw = -$sw;
   }
   if( $h < 0 )
   {
      $h = -$h;
      $sh = -$sh;
   }
   if( $w < 0 )
   {
      $w = -$w;
      $sw = -$sw;
   }
   $w = round( cos($radian) * $w);
   $h = round( sin($radian) * $h);
   return array(
      $x + ($sw<0 ?-$w :$w),
      $y + ($sh<0 ?-$h :$h),
      );
*/
} //getellipticpoint


function points_reverse( &$points, $xoff=0, $yoff=0)
{
   $n = (count($points)&-2)-1;
   $p = array();
   while( $n > 0 )
   {
      $p[] = $points[$n-1] + $xoff;
      $p[] = $points[$n] + $yoff;
      $n -= 2;
   }
   return $p;
} //points_reverse

function points_join($pointX, $pointY)
{
   $points = array();
   while( (list($dummy, $x)=each( $pointX))
       && (list($dummy, $y)=each( $pointY))
      )
   {
      $points[]= $x;
      $points[]= $y;
   }
   return $points;
} //points_join

function points_split(&$points, &$pointX, &$pointY)
{
   reset( $points);
   $pointX = array();
   $pointY = array();
   while( (list($dummy, $x)=each( $points))
       && (list($dummy, $y)=each( $points))
      )
   {
      $pointX[]= $x;
      $pointY[]= $y;
   }
} //points_split

//callback must be a function($x, $y) and return f($x,$y)
function points_map($callback, &$points)
{
   points_split($points, $pointX, $pointY);
   return array_map($callback, $pointX, $pointY);
} //points_map

//callback must be a function(&$x, &$y) and modify $x and $y
function points_mapXY($callback, &$points)
{
   reset( $points);
   $p = array();
   while( (list($dummy, $x)=each( $points))
       && (list($dummy, $y)=each( $points))
      )
   {
      //call_user_func($callback, &$x, &$y); //does not pass references
      call_user_func_array($callback, array(&$x, &$y));
      $p[]= $x;
      $p[]= $y;
   }
   return $p;
} //points_mapXY


/*! \brief Extract the RGB values from various types of colors.
 * return an array($r, $g, $b)
 * will all return the same result:
 *  colortoRGB( 255, 128, 42);
 *  colortoRGB( ((255*256 + 128)<<8) | 42);
 *  colortoRGB( 0xff802a);
 *  colortoRGB( 0xff80, 42);
 *  colortoRGB( 'ff802a');
 *  colortoRGB( '#ff802a');
 *  colortoRGB( array(255, 128, 42));
 *  colortoRGB( array('#ff802a'));
 */
function colortoRGB($r, $g=NULL, $b=NULL)
{
   if( is_array($r) )
   {
      $tmp = $r;
      @list($r,$g,$b) = $tmp;
   }
   if( is_string($r) ) // i.e. 'ff802a'
   {
      if( substr($r,0,1) == '#' )
         $r = substr($r,1,6);
      else
         $r = substr($r,0,6);
      $r = base_convert($r, 16, 10);
   }
   if( !isset($g) )
   {
      $g = $r & 255;
      $r >>= 8;
   }
   if( !isset($b) )
   {
      $b = $g;
      $g = $r & 255;
      $r >>= 8;
   }
   return array( $r&255, $g&255, $b&255);
} //colortoRGB

/*! \brief return a fixed pattern color.
 */
function patterncolor($n)
{
   $x = $n%6;
   $y = 255-($n-$x)*8;
   $x++;
   $b = $x&1 ?0 :$y;
   $r = $x&2 ?0 :$y;
   $g = $x&4 ?0 :$y;
   return array( $r&255, $g&255, $b&255);
} //patterncolor

function get_image_type( $filename)
{
   $type= @getimagesize($filename);
   if( !is_array($type) )
      return false;
   $type=(int)$type[2];
   switch( $type )
   {
      case IMAGETYPE_GIF: $mime= 'image/gif'; break;
      case IMAGETYPE_JPEG: $mime= 'image/jpeg'; break;
      case IMAGETYPE_PNG: $mime= 'image/png'; break;
      case IMAGETYPE_BMP: $mime= 'image/bmp'; break;
      case IMAGETYPE_WBMP: $mime= 'image/vnd.wap.wbmp'; break;
      default: return false;
   }
   return array($type, $mime);
}

//writes an image file to the output buffer adding adjusted headers
function image_passthru( $filename, $modified=null, $expire=null)
{
   $type= get_image_type($filename);
   if( is_array($type) )
   {
      $img= @read_from_file($filename);
      if( $img )
      {
         if( isset($modified) )
            header('Last-Modified: ' . gmdate(GMDATE_FMT, $modified));
         if( isset($expire) )
            header('Expires: ' . gmdate(GMDATE_FMT, $expire));
         header('Content-type: '.$type[1]);
         header('Content-Length: '.strlen($img));
         echo $img;
         return $type; //done
      }
   }
   return false; //error
}

/*!
 * \class Graph
 *
 * \brief Class to ease the creation of graphics.
 */

class Graph
{
   /*! \privatesection */

   /*! \brief this image. */
   var $im;

   /*! \brief image sizes. */
   var $width;
   var $height;

   /*! \brief background colorid. */
   var $bkground;

   /*! \brief border width. */
   var $border;

   /*! \brief True Type Font file path, '' if disabled. */
   var $TTFfile;

   /*! \brief Label metrics.
    * HEIGHT => height of a character (pixels)
    * WIDTH => average width of a character (pixels)
    * ALIGN => vertical alignement to the upper left corner (pixels)
    * VSPACE => vertical spacing (pixels)
    * LINEH => total line height (pixels)
    */
   var $labelMetrics;

   /*! \brief Bounds of X axis. */
   var $offsetX;
   var $sizeX;
   var $boxleft;
   var $boxright;
   var $minX;
   var $maxX;

   /*! \brief Bounds of Y axis. */
   var $offsetY;
   var $sizeY;
   var $boxtop;
   var $boxbottom;
   var $minY;
   var $maxY;


   /*! \publicsection */

   /*! \brief Constructor. Create a new graphics and initialize it. */
   function Graph( $width=640, $height=0, $bgcolor=0xffffff)
   {
      $width = (int)$width;
      if( $width <= 1 )
         $width = 640;
      $height = (int)$height;
      if( $height <= 0 )
         $height = (int)($width * 3 / 4);

      $this->width = $width;
      $this->height = $height;
      $this->im = imagecreate( $width, $height);
      $this->bkground = $this->colorallocate($bgcolor); //first=background color
      $this->border = 6;

      if( !($x=(string)@$_GET['font']) )
         $x = TTF_NAME;
      $this->setfont($x);

   } //Graph constructor


   /*! \brief Set the TTF font used.
    * 'FreeSans-Medium', 'ARIAL' or 'COUR'
    */
   function setfont( $font='')
   {
      if( $font > '' && $font != '*'
        && function_exists('imagettftext') //TTF need GD and Freetype.
        )
      {
         $file = sprintf(TTF_PATH, $font);
         if( is_file($file) //file_exists() may return false because of safe_mode
           && is_readable($file) //...and access rights check if needed
           )
         {
            $this->TTFfile = $file;
            TTFdefaultlabelMetrics($this);
            return true;
         }
      }
      $this->TTFfile = '';
      EMBdefaultlabelMetrics($this);
      return false;
   } //setfont

   function labelbox($str)
   {
      if( $this->TTFfile > '' )
         return TTFlabelbox($this, $str);
      else
         return EMBlabelbox($this, $str);
   } //labelbox

   //$x,$y is the upper left corner
   function label($x, $y, $str, $colorid)
   {
      if( $this->TTFfile > '' )
         return TTFlabel($this, $x, $y, $str, $colorid);
      else
         return EMBlabel($this, $x, $y, $str, $colorid);
   } //label



   function colorallocate($r, $g=NULL, $b=NULL)
   {
      list($r,$g,$b) = colortoRGB($r, $g, $b);
      return imagecolorallocate($this->im, $r, $g, $b);
   } //colorallocate

   /*! \brief A way to get each time a colorid.
    * If the *exact* color is found in the image, it will be returned.
    * If we don't have the exact color, we try to allocate it.
    * If we can't allocate it, we return the closest color in the image.
    */
   function getcolor($r, $g=NULL, $b=NULL) {
      list($r,$g,$b) = colortoRGB($r, $g, $b);
      $c = imagecolorexact($this->im, $r, $g, $b);
      if( $c >= 0 ) return $c;
      $c = imagecolorallocate($this->im, $r, $g, $b);
      if( $c >= 0 ) return $c;
      return imagecolorclosest($this->im, $r, $g, $b);
   } //getcolor

   function setdash($colorid)
   {
      return imagesetstyle($this->im, array($colorid,$colorid,
                    IMG_COLOR_TRANSPARENT,IMG_COLOR_TRANSPARENT,
                    IMG_COLOR_TRANSPARENT,IMG_COLOR_TRANSPARENT));
   } //setdash

   function line($x1, $y1, $x2, $y2, $colorid)
   {
      return imageline($this->im, $x1, $y1, $x2, $y2, $colorid);
   } //line

   function curve(&$pointX, &$pointY, $nr_points, $colorid)
   {
      for( $i=1; $i<$nr_points; $i++)
         imageline($this->im,
            $pointX[$i-1],$pointY[$i-1],$pointX[$i],$pointY[$i],$colorid);
   } //curve

   function polygon(&$points, $nr_points, $colorid)
   {
      imagepolygon($this->im, $points, $nr_points, $colorid);
   } //polygon

   //like imagepolygon() but open
   function polyline(&$points, $nr_points, $colorid)
   {
      for( $i=0; $i<$nr_points-1; $i++)
         imageline($this->im, $points[2*$i], $points[2*$i+1],
                            $points[2*$i+2], $points[2*$i+3], $colorid);
   } //polyline

   function filledpolygon(&$points, $nr_points, $colorid)
   {
      if( $nr_points > 2 )
      {
         imagefilledpolygon($this->im, $points, $nr_points, $colorid);
      }
      if( $nr_points > 1 )
      {
         //also fix a strange bug of imagefilledpolygon() that
         //does not cover every frontier lines.
         $this->polyline($points, $nr_points, $colorid);
      }
      else if( $nr_points > 0 )
      {
         imagesetpixel($this->im, $points[0], $points[1], $colorid);
      }
   } //filledpolygon

   function polarline($x, $y, $length, $radian, $colorid)
   {
      //the Y axis is inversed, the rotation too (clockwise)
      list($px, $py) = getellipticpoint($x, $y, $length, $length, $radian);
      imageline($this->im, $x, $y, $px, $py, $colorid);
      return array($px, $py);
   } //polarline


   function imagesend()
   {
      if( function_exists("imagepng") )
      {
         header("Content-type: image/png");
         //header('Content-Length: ' . strlen($img));
         imagepng($this->im);
      } else
      if( function_exists("imagegif") )
      {
         header("Content-type: image/gif");
         imagegif($this->im);
      } else
      if( function_exists("imagejpeg") )
      {
         header("Content-type: image/jpeg");
         imagejpeg($this->im, '', 0.5);
      } else
      if( function_exists("imagewbmp") )
      { //for wap devices
         header("Content-type: image/vnd.wap.wbmp");
         imagewbmp($this->im);
      } else
      {
         //imagedestroy($this->im);
         die("No PHP graphic support on this server");
      }
      //imagedestroy($this->im);
      //unset($this->im);
   } //imagesend


   function scaleX($x)
   {
      return scale($x, $this->minX, $this->maxX, $this->offsetX, $this->sizeX);
   } //scaleX

   function scaleY($y)
   {
      return scale($y, $this->minY, $this->maxY, $this->offsetY, $this->sizeY);
   } //scaleY

   function mapscaleX(&$pointX)
   {
      gscaleinit($this->minX, $this->maxX, $this->offsetX, $this->sizeX);
      return array_map('gscale', $pointX);
      //return array_map(array(&$this, 'scaleX'), $pointX);
   } //mapscaleX

   function mapscaleY(&$pointY)
   {
      gscaleinit($this->minY, $this->maxY, $this->offsetY, $this->sizeY);
      return array_map('gscale', $pointY);
      //return array_map(array(&$this, 'scaleY'), $pointY);
   } //mapscaleY

   /*! \brief Set the graph box.
    * values of two opposite corners of the rectangle
    */
   function setgraphbox($x1, $y1, $x2, $y2)
   {
      if( $x1 > $x2 )
      {
         $this->offsetX = $x2;
         $this->sizeX = $x1-$x2;
      }
      else
      {
         $this->offsetX = $x1;
         $this->sizeX = $x2-$x1;
      }
      $this->boxleft = $this->offsetX;
      $this->boxright = $this->offsetX+$this->sizeX;
      if( $y1 > $y2 )
      {
         $this->offsetY = $y2;
         $this->sizeY = $y1-$y2;
      }
      else
      {
         $this->offsetY = $y1;
         $this->sizeY = $y2-$y1;
      }
      $this->boxtop = $this->offsetY;
      $this->boxbottom = $this->offsetY+$this->sizeY;
   } //setgraphbox

   /*! \brief Set the graph mapping (oriented).
    * values of (left,top,right,bottom) bounds
    */
   function setgraphview($xleft, $ytop, $xright, $ybottom)
   {
      $this->setgraphviewX($xleft, $xright);
      $this->setgraphviewY($ytop, $ybottom);
   } //setgraphview

   /*! \brief Set the X mapping (oriented).
    * values of (left,right) bounds
    */
   function setgraphviewX($xleft, $xright)
   {
      $this->minX = $xleft;
      $this->maxX = $xright;
   } //setgraphviewX

   /*! \brief Set the Y mapping (oriented).
    * values of (top,bottom) bounds
    */
   function setgraphviewY($ytop, $ybottom)
   {
      $this->minY = $ytop; //correct the Y axis inversion
      $this->maxY = $ybottom;
   } //setgraphviewY


   /*! \brief Draw the X axis grid.
    * $linefct is a function($s) that return the value of the $s step.
    *   $s is one of the successive $start + N*$step
    * $textfct is a function($v) that return the label text for the $v value.
    *   $v is typically a value returned by $linefct($s)
    * labels will be top aligned to $align
    * $linetype: the grid will be 0=invisible, 1=solid, 2=dashed
    */
   function gridX($start, $step, $align
            , $textfct='', $textcolorid=NULL
            , $linefct='', $linecolorid=NULL, $linetype=2
            , $lbound=false, $ubound=false)
   {
      if( !isset($textfct) || !$textfct )
         $textfct = 'fnop';
      if( !isset($linefct) || !$linefct )
         $linefct = 'fnop';
      if( !isset($textcolorid) )
         $textcolorid = $this->getcolor(0);
      if( !isset($linecolorid) )
         $linecolorid = $textcolorid;
      if( $linetype == 2 )
      {
         $this->setdash($linecolorid);
         $linecolorid = IMG_COLOR_STYLED;
      }
      gscaleinit($this->minX, $this->maxX, $this->offsetX, $this->sizeX);


      $align = min( $this->width - $this->border, max( $this->border, $align));
      if( @$lbound <= 0 )
         $lbound = floor($this->offsetX - $this->labelMetrics['WIDTH']);
      $lbound = min( $this->offsetX + $this->sizeX, max( $this->border, $lbound));
      if( @$ubound <= 0 )
         $ubound = $this->offsetX + $this->sizeX;
      $ubound = min( $this->width - $this->border, max( $lbound, $ubound));

      //check to avoid infinite loops
      $grid = gscale($linefct($start));
      if( gscale($linefct($start+$step)) <= $grid )
         $step = -$step;
      if( gscale($linefct($start+$step)) <= $grid )
         return;
      do { $start -= $step; }
      while( gscale($linefct($start)) >= $lbound );
      do { $start += $step; }
      while( gscale($linefct($start)) < $lbound );

      //grid line bounds
      $slin = max( $this->border, $this->boxtop -6);
      $elin = min( $this->height-$this->border, $this->boxbottom +4);
      //so all dashed lines start in the same way
      $slin += ($elin-$slin) % DASH_MODULO +1;

      $no_text = true;
      for( ;; $start += $step )
      {
         $value = $linefct($start);
         $grid = gscale($value);
         if( $grid > $ubound )
         {
            if( !$no_text ) break;
            $value = $this->minX;
            $grid = gscale($value);
         }
         else if( $linetype )
         {
            $this->line($grid, $slin, $grid, $elin, $linecolorid);
         }
         $no_text = false;
         if( $grid < $lbound )
            continue;
         //$str = call_user_func($textfct, $start);
         $str = $textfct($value);
         $b = $this->labelbox($str);
         if( $grid+$b['x'] > $ubound )
            continue;
         $b = $this->label($grid, $align
                           , $str, $textcolorid);
         $lbound = ceil($b['x'] + $this->labelMetrics['WIDTH']);
      }
   } //gridX

   /*! \brief Draw the Y axis grid.
    * $linefct is a function($s) that return the value of the $s step.
    *   $s is one of the successive $start + N*$step
    * $textfct is a function($v) that return the label text for the $v value.
    *   $v is typically a value returned by $linefct($s)
    * labels will be left aligned to $align
    * $linetype: the grid will be 0=invisible, 1=solid, 2=dashed
    */
   function gridY($start, $step, $align
            , $textfct='', $textcolorid=NULL
            , $linefct='', $linecolorid=NULL, $linetype=2
            , $lbound=false, $ubound=false)
   {
      if( !isset($textfct) || !$textfct )
         $textfct = 'fnop';
      if( !isset($linefct) || !$linefct )
         $linefct = 'fnop';
      if( !isset($textcolorid) )
         $textcolorid = $this->getcolor(0);
      if( !isset($linecolorid) )
         $linecolorid = $textcolorid;
      if( $linetype == 2 )
      {
         $this->setdash($linecolorid);
         $linecolorid = IMG_COLOR_STYLED;
      }
      gscaleinit($this->minY, $this->maxY, $this->offsetY, $this->sizeY);


      $align = min( $this->height - $this->border, max( $this->border, $align));
      if( @$lbound <= 0 )
         $lbound = floor($this->offsetY - $this->labelMetrics['HEIGHT']);
      $lbound = min( $this->offsetY + $this->sizeY, max( $this->border, $lbound));
      if( @$ubound <= 0 )
         $ubound = $this->offsetY + $this->sizeY;
      $ubound = min( $this->height - $this->border, max( $lbound, $ubound));

      //check to avoid infinite loops
      $grid = gscale($linefct($start));
      if( gscale($linefct($start+$step)) <= $grid )
         $step = -$step;
      if( gscale($linefct($start+$step)) <= $grid )
         return;
      do { $start -= $step; }
      while( gscale($linefct($start)) >= $lbound );
      do { $start += $step; }
      while( gscale($linefct($start)) < $lbound );

      //grid line bounds
      $slin = max( $this->border, $this->boxleft -4);
      $elin = min( $this->width-$this->border, $this->boxright +6);
      //so all dashed lines start in the same way
      $elin -= ($elin-$slin) % DASH_MODULO +1;

      $no_text = true;
      for( ;; $start += $step )
      {
         $value = $linefct($start);
         $grid = gscale($value);
         if( $grid > $ubound )
         {
            if( !$no_text ) break;
            $value = $this->minY;
            $grid = gscale($value);
         }
         else if( $linetype )
         {
            $this->line($slin, $grid, $elin, $grid, $linecolorid);
         }
         $no_text = false;
         if( $grid < $lbound )
            continue;
         //$str = call_user_func($textfct, $start);
         $str = $textfct($value);
         $b = $this->label($align, $grid - $this->labelMetrics['ALIGN']
                           , $str, $textcolorid);
         $lbound = ceil($b['y'] + $this->labelMetrics['ALIGN']);
      }
   } //gridY


   /*! \brief draw a filled pie portion.
    * supplied because imagefilledarc() need GD2. See GD2_ARC constant
    */
   function filledarc($cx, $cy, $w, $h, $s, $e, $colorid)
   {
      $p = array();
      $n = arcpoints($p, $cx, $cy, $w, $h, $s, $e);
      if( $n <= 0 )
      {
         $w /= 2.;
         $h /= 2.;
         $s = deg2rad($s); //$s *= M_PI/180.;
         list($x, $y) = getellipticpoint($cx, $cy, $w, $h, $s);
         imageline($this->im, $cx, $cy, $x, $y, $colorid);
         return;
      }
      $p[] = $cx;
      $p[] = $cy;
      $this->filledpolygon($p, $n+1, $colorid);
   } //filledarc

   /*! \brief draw an ellipse arc.
    * to keep a stroke similarity with our filledarc().
    */
   function arc($cx, $cy, $w, $h, $s, $e, $colorid)
   {
      $p = array();
      $n = arcpoints($p, $cx, $cy, $w, $h, $s, $e);
      $this->polyline($p, $n, $colorid);
   } //arc

   /*! \brief draw a pie.
    * $datas[-1] is the empty portion.
    * $colors: an array of r,g,b colors to be used (allocated if needed)
    */
   function pie( &$datas, $cx, $cy, $sx, $sy, $sz=0, $colors=false)
   {
      if( $sx == 0 || $sy == 0 )
         return;

      //convert to angles.

      $sum = array_sum($datas);
      if( $sum <= 0 )
         return;

      $angles[-1] = 0.; //needed for indice purpose
      $ang = 0.;
      $nbval = 0;
      foreach( $datas as $x => $y )
      {
         if( $x < 0)
            continue;
         $ang += $y * 360. / $sum;
         $angles[$nbval++] = $ang;
      }

      $im = $this->im;

      //colors
      if( !is_array($colors) )
         $colors = array();
      $colord = array();

      $ang = -1.;
      $n = 0;
      for( $i=0; $i<$nbval; $i++ )
      {
         if( isset($colors[$i]) )
            list($r,$g,$b) = colortoRGB($colors[$i]);
         else
            list($r,$g,$b) = patterncolor($n++);

         $colors[$i] = $this->getcolor($r, $g, $b);

         // front edge colors
         if( $ang <= 180. && $sz > 0 )
         {
            $colord[$i] = $this->getcolor($r*PIE_SHADOW_FACTOR
                                        , $g*PIE_SHADOW_FACTOR
                                        , $b*PIE_SHADOW_FACTOR);
            $ang = $angles[$i];
         }
      }

      if( $sz > 0 )
      {
         //draw edge of incomplete pie
         $ang = $angles[$nbval-1];
         if( ($ang > 270 && $ang < 360)
             || ($ang < 90  && $ang >= 0 )
           )
         {
            $p = array();
            $p[] = $cx;
            $p[] = $cy;
            list($x, $y) = getellipticpoint($cx, $cy, $sx/2., $sy/2., deg2rad($ang));
            $p[] = $x;
            $p[] = $y;
            $p[] = $x;
            $p[] = $y+$sz;
            $p[] = $cx;
            $p[] = $cy+$sz;
            //use last computed colors[] to compute the shadow
            $this->filledpolygon($p, 4, $this->getcolor($r*PIE_SHADOW_FACTOR
                                                      , $g*PIE_SHADOW_FACTOR
                                                      , $b*PIE_SHADOW_FACTOR) );
         }

         $nedge = count($colord);
      }
      else
         $nedge = -1;

      // draw portions
      for( $i=0; $i<$nbval; $i++ )
      {
         $p = array();
         $s = $angles[$i-1];
         $e = $angles[$i];
         $n = arcpoints($p, $cx, $cy, $sx, $sy, $s, $e);
         if( $n > 0 && $i < $nedge )
         {
            //draw portion front edge
            if( $e > 180 && $n > 0 )
            {

               // the arc must be properly oriented
               $pt = array();
               $pt[] = $p[0];
               $pt[] = $p[1];
               for( $nt=1; $nt<$n; $nt++ )
               {
                  $x = $p[2*$nt];
                  $y = $p[2*$nt+1];
                  if( $y <= $cy ) {
                     $pt[] = $x;
                     $pt[] = $cy;
                     break;
                  }
                  $pt[] = $x;
                  $pt[] = $y;
               }
               $nt = count($pt)/2;
            }
            else
            {
               $pt = $p;
               $nt = $n;
            }
            if( $nt > 0 )
            {
               $pt = array_merge( $pt, points_reverse( $pt, 0, $sz));
               $this->filledpolygon($pt, $nt+$nt, $colord[$i]);
            }
         }

         //draw portion top face

         $p[] = $cx; //add central point
         $p[] = $cy;
         if( $n > 1 )
         {
            $this->filledpolygon($p, $n+1, $colors[$i]);
         }
         else
         {
            if( $n <= 0 )
            {
               list($x, $y) = getellipticpoint($cx, $cy, $sx/2., $sy/2., deg2rad($e));
               $p[] = $x;
               $p[] = $y;
               $n = 1;
            }
            $this->polyline($p, $n+1, $colors[$i]);
         }
      } // draw portions
   } //pie


   /*! \privatesection */

} //class Graph


   /*! \privatesection */

if( function_exists('imagettftext') ) //TTF need GD and Freetype.
{
   if( function_exists('imagettfbbox') ) //TTF need GD and Freetype.
   {
      /*! \brief return the metrics of the True Type font
       * to keep a compatibility with the embedded font.
       */
      function TTFdefaultlabelMetrics(&$gr)
      {
         $h = TTF_HEIGHT;
         $s = 3;
         $b= imagettfbbox($h, 0, $gr->TTFfile, 'MIXmix');
         $gr->labelMetrics = array(
          'HEIGHT' => $h,
          'WIDTH' => ($b[2]-$b[6])/6 +1,
          'ALIGN' => $b[3]-$b[7] +1,
          'VSPACE' => $s,
          'LINEH' => $h+$s,
         );
      } //TTFdefaultlabelMetrics

      /*! \brief return the height and width of the label
       * as if drawn with the True Type font.
       */
      function TTFlabelbox(&$gr, $str)
      {
         $txt = explode("\n", $str);
         $x = $y = 0;
         foreach( $txt as $str )
         {
            /* imagettfbbox return:
            0=>X, 1=>Y ;bottom left
            2=>X, 3=>Y ;bottom right
            4=>X, 5=>Y ;top right
            6=>X, 7=>Y ;top left
            (i.e. the polygon of the bounding rectangle)
            */
            $b= imagettfbbox( $gr->labelMetrics['HEIGHT'], 0, $gr->TTFfile, $str);
            $x = max($x, $b[2]-$b[6]);
            //$y+= $b[3]-$b[7] + $gr->labelMetrics['VSPACE'];
            $y+= $gr->labelMetrics['LINEH'];
         }
         return array('x' => $x, 'y' => $y - $gr->labelMetrics['VSPACE']);
      } //TTFlabelbox
   }
   else
   {
      /*! \brief return the metrics of the True Type font
       * to keep a compatibility with the embedded font.
       */
      function TTFdefaultlabelMetrics(&$gr)
      {
         $h = TTF_HEIGHT; //too match the embedded font
         $s = 3;
         $gr->labelMetrics = array(
          'HEIGHT' => $h,
          'WIDTH' => $h*4/5,
          'ALIGN' => $h +1,
          'VSPACE' => $s,
          'LINEH' => $h+$s,
         );
      } //TTFdefaultlabelMetrics

      /*! \brief return the height and width of the label
       * as if drawn with the True Type font.
       */
      function TTFlabelbox(&$gr, $str)
      {
         $txt = explode("\n", $str);
         $x = $y = 0;
         foreach( $txt as $str )
         {
            //IMG_COLOR_TRANSPARENT does not work, so draw it out of bound
            $b= imagettftext($gr->im,
               $gr->labelMetrics['HEIGHT'], 0, -100, -100, 0, $gr->TTFfile, $str);
            $x = max($x, $b[2]-$b[6]);
            //$y+= $b[3]-$b[7] + $gr->labelMetrics['VSPACE'];
            $y+= $gr->labelMetrics['LINEH'];
         }
         return array('x' => $x, 'y' => $y - $gr->labelMetrics['VSPACE']);
      } //TTFlabelbox
   }

   /*! \brief draw a label with the True Type font.
    * $x,$y is the upper left corner.
    * return the lower right corner.
    */
   function TTFlabel(&$gr, $x, $y, $str, $colorid)
   {
      $txt = explode("\n", $str);
      $l = $x;
      foreach( $txt as $str )
      {
         /* to compare with embedded fonts
            $f = 2;
            $w = ImageFontWidth($f);
            $h = ImageFontHeight($f)-1;
            $b = $x + strlen($str)*$w ;
            global $black; imagerectangle($gr->im, $x, $y, $b, $y+$h, $black);
            imagestring($gr->im, $f, $x, $y, $str, $colorid);
         */
         $b= imagettftext($gr->im,
            $gr->labelMetrics['HEIGHT'], 0,
            $l, $y+$gr->labelMetrics['ALIGN'],
            $colorid, $gr->TTFfile, $str);
         //global $red; imagerectangle($gr->im, $b[6], $b[7], $b[2], $b[3], $red);
         $x = max($x, $b[2]);
         //$y = $b[3] + $gr->labelMetrics['VSPACE'];
         $y+= $gr->labelMetrics['LINEH'];
      }
      return array('x' => $x, 'y' => $y);
   } //TTFlabel

}
else //True type font problem, so use embedded fonts:
{
   function TTFdefaultlabelMetrics(&$gr) {
      return EMBdefaultlabelMetrics($gr);}
   function TTFlabelbox(&$gr, $str) {
      return EMBlabelbox($gr, $str);}
   function TTFlabel(&$gr, $x, $y, $str, $colorid) {
      return EMBlabel($gr, $x, $y, $str, $colorid);}
}


define('LABEL_FONT', 2);
/*! \brief return the metrics of the embedded font
 * to keep a compatibility with the True Type fonts.
 */
function EMBdefaultlabelMetrics(&$gr)
{
   $h = ImageFontHeight(LABEL_FONT)-1;
   $s = 2;
   $gr->labelMetrics = array(
    'HEIGHT' => $h,
    'WIDTH' => ImageFontWidth(LABEL_FONT),
    'ALIGN' => $h*4/5,
    'VSPACE' => $s,
    'LINEH' => $h+$s,
   );
} //EMBdefaultlabelMetrics

/*! \brief return the height and width of the label
 * as if drawn with the embedded font.
 */
function EMBlabelbox(&$gr, $str)
{
   $txt = explode("\n", $str);
   $x = $y = 0;
   foreach( $txt as $str )
   {
      $x = max($x, strlen($str)*$gr->labelMetrics['WIDTH']);
      $y += $gr->labelMetrics['LINEH'];
   }
   return array('x' => $x, 'y' => $y - $gr->labelMetrics['VSPACE']);
} //EMBlabelbox

/*! \brief draw a label with the embedded font.
 * $x,$y is the upper left corner.
 * return the lower right corner.
 */
function EMBlabel(&$gr, $x, $y, $str, $colorid)
{
   //$str = str_replace("\r","",$str);
   $txt = explode("\n", $str);
   $l = $x;
   foreach( $txt as $str )
   {
      imagestring($gr->im, LABEL_FONT, $l, $y, $str, $colorid);
      $x = max($x, $l + strlen($str)*$gr->labelMetrics['WIDTH']);
      //global $red; imagerectangle($gr->im, $l, $y, $x, $y+$gr->labelMetrics['HEIGHT'], $red);
      $y += $gr->labelMetrics['LINEH'];
   }
   return array('x' => $x, 'y' => $y);
} //EMBlabel


function arcpoints(&$p, $cx, $cy, $w, $h, $s, $e)
{
   if( $s == $e )
      return 0;

   //the Y axis is inversed, the rotation too! (clockwise)
   while( $s >= 360 ) $s -= 360;
   while( $s < 0 ) $s += 360;
   while( $e > 360 ) $e -= 360;
   while( $e <= $s ) $e += 360;

   $w /= 2.;
   $h /= 2.;

   $s *= M_PI/180.;
   $e *= M_PI/180.; //no deg2rad($e) because $e could be >360

      // steps walk.
      // heuristic, to have smooth edges.
      $a = $e-$s;
      $n = ceil( $a*($w+$h) * 0.5 ) +1;

      $a /= $n;

         list($px, $py) = getellipticpoint($cx, $cy, $w, $h, $s);
            $ox = $px;
            $oy = $py;
            $p[] = $px;
            $p[] = $py;
            $nb = 1;

      for( $i=1; $i<$n; $i++ )
      {
         $s += $a;
         list($px, $py) = getellipticpoint($cx, $cy, $w, $h, $s);
         if( $px != $ox || $py != $oy )
         {
            $ox = $px;
            $oy = $py;
            $p[] = $px;
            $p[] = $py;
            $nb++;
         }
      }

         list($px, $py) = getellipticpoint($cx, $cy, $w, $h, $e);
         if( $px != $ox || $py != $oy )
         {
            $ox = $px;
            $oy = $py;
            $p[] = $px;
            $p[] = $py;
            $nb++;
         }

   return $nb;
} //arcpoints

?>
