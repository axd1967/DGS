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
   if( !($x<$Size and $y<$Size and $x>=0 and $y>=0) )
      return NULL;

   return chr(ord('a')+$x) . chr(ord('a')+$y);
}

function sgf2number_coords($coord, $Size)
{
   if( !is_string($coord) or strlen($coord)!=2 )
      return array(NULL,NULL);

   $x = ord($coord[0])-ord('a');
   $y = ord($coord[1])-ord('a');

   if( !($x<$Size and $y<$Size and $x>=0 and $y>=0) )
      return array(NULL,NULL);

   return array(ord($coord[0])-ord('a'), ord($coord[1])-ord('a'));
}

function number2board_coords($x, $y, $Size)
{
  if( !($x<$Size and $y<$Size and $x>=0 and $y>=0) )
     return NULL;

  $col = chr( $x + ord('a') );
  if( $col >= 'i' ) $col++;

  return  $col . ($Size - $y);

}

function board_coords2number($string, $Size)
{
   $x = ord($string{0}) - ord('a');
   if( $x > 8 ) $x--;

   $y = $Size - substr($string, 1);

   if( !($x<$Size and $y<$Size and $x>=0 and $y>=0) )
      return NULL;

   return  array($x, $y);
}

?>