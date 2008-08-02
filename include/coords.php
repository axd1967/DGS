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


function number2sgf_coords($x, $y, $Size)
{
   if( !(is_numeric($x) && is_numeric($y) && $x>=0 && $y>=0 && $x<$Size && $y<$Size) )
      return NULL;

   return chr(ord('a')+$x) . chr(ord('a')+$y);
}

function sgf2number_coords($coord, $Size)
{
   if( !is_string($coord) || strlen($coord)!=2 )
      return array(NULL,NULL);

   $x = ord($coord[0])-ord('a');
   $y = ord($coord[1])-ord('a');

   if( !($x<$Size && $y<$Size && $x>=0 && $y>=0) )
      return array(NULL,NULL);

   return array($x, $y);
}

function number2board_coords($x, $y, $Size)
{
   if( !(is_numeric($x) && is_numeric($y) && $x>=0 && $y>=0 && $x<$Size && $y<$Size) )
     return NULL;

  $col = chr( $x + ($x>=8?1:0) + ord('a') );

  return  $col . ($Size - $y);
}

function board2number_coords($coord, $Size)
{
   if( is_string($coord) && strlen($coord)>=2 )
   {
      $x = ord($coord[0]) - ord('a');
      if( $x != 8 )
      {
         if( $x > 8 ) $x--;

         $y = $Size - substr($coord, 1);

         if( $x<$Size && $y<$Size && $x>=0 && $y>=0 )
            return  array($x, $y);
      }
   }
   return array(NULL,NULL);
}

//board letter:     - a b c d e f g h j k l m n o p q r s t u v w x y z
$hoshi_dist = array(0,0,0,0,0,0,0,0,3,3,3,3,4,4,4,4,4,4,4,4,4,4,4,4,4,4);
$hoshi_pos  = array(0,0,0,0,0,1,0,1,4,5,4,5,4,7,4,7,4,7,4,7,4,7,4,7,4,7);
//$hoshi_pos: 0x01 allow center, 0x02 allow side, 0x04 allow corner

function is_hoshi($x, $y, $sz)
{
  global $hoshi_pos, $hoshi_dist;

   $hd= $hoshi_dist[$sz];
   if( $h = $x*2+1 == $sz ? 1 : ( $x == $hd-1 || $x == $sz-$hd ? 2 : 0 ) )
       $h*= $y*2+1 == $sz ? 1 : ( $y == $hd-1 || $y == $sz-$hd ? 2 : 0 ) ;
   return $hoshi_pos[$sz] & $h;
}

?>
