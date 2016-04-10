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


function number2sgf_coords($x, $y, $SizeX, $SizeY=null)
{
   if ( is_null($SizeY) ) $SizeY = $SizeX;
   if ( !(is_numeric($x) && is_numeric($y) && $x>=0 && $y>=0 && $x<$SizeX && $y<$SizeY) )
      return NULL;

   return chr(ord('a')+$x) . chr(ord('a')+$y);
}

function sgf2number_coords($coord, $Size)
{
   if ( !is_string($coord) || strlen($coord)!=2 )
      return array(NULL,NULL);

   $x = ord($coord[0])-ord('a');
   $y = ord($coord[1])-ord('a');

   if ( !($x<$Size && $y<$Size && $x>=0 && $y>=0) )
      return array(NULL,NULL);

   return array($x, $y);
}

function sgf2board_coords($coord, $Size)
{
   if ( !is_string($coord) || strlen($coord)!=2 )
      return '';

   $x = ord($coord[0]) - ord('a');
   $y = ord($coord[1]) - ord('a');
   if ( !($x<$Size && $y<$Size && $x>=0 && $y>=0) )
      return '';

   $col = chr( $x + ($x>=8?1:0) + ord('a') );
   return  $col . ($Size - $y);
}

function number2board_coords($x, $y, $Size)
{
   if ( !(is_numeric($x) && is_numeric($y) && $x>=0 && $y>=0 && $x<$Size && $y<$Size) )
     return NULL;

   $col = chr( $x + ($x>=8?1:0) + ord('a') );
   return  $col . ($Size - $y);
}

function board2number_coords($coord, $Size)
{
   if ( is_string($coord) && strlen($coord)>=2 )
   {
      $x = ord($coord[0]) - ord('a');
      if ( $x != 8 )
      {
         if ( $x > 8 ) $x--;

         $y = $Size - substr($coord, 1);

         if ( $x<$Size && $y<$Size && $x>=0 && $y>=0 )
            return  array($x, $y);
      }
   }
   return array(NULL,NULL);
}

// \param $coord expecting real move != PASS-move ('tt' or '')
function is_valid_sgf_coords( $coord, $size )
{
   if ( strlen($coord) != 2 )
      return false;
   if ( $coord[0] < 'a' || $coord[1] < 'a' )
      return false;
   $max_ch = chr( ord('a') + (int)$size - 1 );
   return ( $coord[0] <= $max_ch && $coord[1] <= $max_ch );
}//is_valid_sgf_coords

function is_valid_board_coords( $coord, $size )
{
   if ( strlen($coord) < 2 )
      return false;
   $c2 = substr($coord, 1);
   if ( !is_numeric($c2) || $c2 < 1 || $c2 > $size )
      return false;

   $c1 = strtolower($coord[0]);
   if ( $c1 < 'a' || $c1 == 'i' )
      return false;
   $max_ch = chr( ord('a') + (int)$size - ($size <= 8 ? 1 : 0) );
   return ( $c1 <= $max_ch );
}//is_valid_board_coords


//index=size: dist (value=side-distance), pos (value=mask)
//$hoshi_pos: 0x01 allow center, 0x02 allow side, 0x04 allow corner
static $hoshi_dist = array(0,0,0,0,0,0,0,0,3,3,3,3,4,4,4,4,4,4,4,4,4,4,4,4,4,4);
static $hoshi_pos  = array(0,0,0,0,0,1,0,1,4,5,4,5,4,7,4,7,4,7,4,7,4,7,4,7,4,7);

function is_hoshi($x, $y, $sz, $szy=null)
{
   global $hoshi_dist, $hoshi_pos;

   if ( is_null($szy) ) $szy = $sz;

   //board letter:     - a b c d e f g h j k l m n o p q r s t u v w x y z
   if ( $sz == $szy )
   {
      $hd = $hoshi_dist[$sz];
      if ( $h  = ( ($x*2+1 == $sz) ? 1 : ( ($x == $hd-1 || $x == $sz-$hd) ? 2 : 0 ) ) )
          $h *=   ($y*2+1 == $sz) ? 1 : ( ($y == $hd-1 || $y == $sz-$hd) ? 2 : 0 );
      return $hoshi_pos[$sz] & $h;
   }
   else
   {
      $szx = $sz;
      $hdx = $hoshi_dist[$szx];
      $hdy = $hoshi_dist[$szy];
      $hx = ($x*2+1 == $szx) ? 1 : ( ($x == $hdx-1 || $x == $szx-$hdx) ? 2 : 0 );
      $hy = ($y*2+1 == $szy) ? 1 : ( ($y == $hdy-1 || $y == $szy-$hdy) ? 2 : 0 );
      return ($hoshi_pos[$szx] & $hx) && ($hoshi_pos[$szy] & $hy);
   }
}//is_hoshi

// returns [ [x,y], ... ]
function get_hoshi_coords( $size_x, $size_y, $start=0 )
{
   global $hoshi_dist, $hoshi_pos;

   if ( $size_x < 1 || $size_x > 25 || $size_y < 1 || $size_y > 25 ) // unknown sizes
      return array();

   $hdx = $hoshi_dist[$size_x];
   $hdy = $hoshi_dist[$size_y];
   $hpx = $hoshi_pos[$size_x];
   $hpy = $hoshi_pos[$size_y];

   $xmid = (( $size_x - 1 ) >> 1 ) + $start;
   $ymid = (( $size_y - 1 ) >> 1 ) + $start;
   $xW = $hdx - 1 + $start;
   $xE = $size_x - $hdx + $start;
   $yN = $size_y - $hdy + $start;
   $yS = $hdy - 1 + $start;

   $arr = array();

   if ( ($hpx & 1) && ($hpy & 1) ) // center
      $arr[] = array( $xmid, $ymid );
   if ( $hpx & 2 ) // side
   {
      $arr[] = array( $xmid, $yN );
      $arr[] = array( $xmid, $yS );
   }
   if ( $hpy & 2 ) // side
   {
      $arr[] = array( $xW, $ymid );
      $arr[] = array( $xE, $ymid );
   }
   if ( ($hpx & 4) && ($hpy & 4) ) // corners
   {
      $arr[] = array( $xW, $yN );
      $arr[] = array( $xW, $yS );
      $arr[] = array( $xE, $yN );
      $arr[] = array( $xE, $yS );
   }

   return $arr;
}//get_hoshi_coords

?>
