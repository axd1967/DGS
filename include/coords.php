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


function number2sgf_coords($x, $y, $Size)
{
   if( !(is_numeric($x) && is_numeric($y) && $x>=0 && $y>=0 && $x<$Size && $y<$Size) )
      return NULL;

   return chr(ord('a')+$x) . chr(ord('a')+$y);
}

function sgf2number_coords($coord, $Size)
{
   if( !is_string($coord) or strlen($coord)!=2 )
      return array(NULL,NULL);

   $x = ord($coord{0})-ord('a');
   $y = ord($coord{1})-ord('a');

   if( !($x<$Size and $y<$Size and $x>=0 and $y>=0) )
      return array(NULL,NULL);

   return array($x, $y);
}

function number2board_coords($x, $y, $Size)
{
   if( !(is_numeric($x) && is_numeric($y) && $x>=0 && $y>=0 && $x<$Size && $y<$Size) )
     return NULL;

  $col = chr( $x + ord('a') );
  if( $col >= 'i' ) $col++;

  return  $col . ($Size - $y);
}

function board2number_coords($coord, $Size)
{
   if( is_string($coord) && strlen($coord)>=2 )
   {
      $x = ord($coord{0}) - ord('a');
      if( $x != 8 )
      {
         if( $x > 8 ) $x--;

         $y = $Size - substr($coord, 1);

         if( $x<$Size and $y<$Size and $x>=0 and $y>=0 )
            return  array($x, $y);
      }
   }
   return array(NULL,NULL);
}

?>