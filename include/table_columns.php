<?php
/*
Dragon Go Server
Copyright (C) 2001  Erik Ouchterlony

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

function tablehead($Head, $sort_string=NULL, $desc_default=false)
{
   global $sort1, $desc1, $sort2, $desc2,$column_set,$page;

   if( !in_array($Head,$column_set) )
      return;

   if( !$sort_string )
      return "<th>" . _($Head) .
         "</font></A><a href=\"" . $page . "del=" . urlencode($Head) .
         "\"><sup><font size=\"-1\" color=red>x</font></sup></a></th>\n";

   if( $sort_string == $sort1 )
   {
      $s1 = $sort1;
      $s2 = $sort2;
      $d1 = !$desc1;
      $d2 = !$desc2;
   }
   else
   {
      $s1 = $sort_string;
      $d1 = $desc_default;
      $s2 = $sort1;
      $d2 = $desc1 xor $desc_default;
   }

   return "<th><A href=\"$page" . order_string($s1,$d1,$s2,$d2) . 
      "\"><font color=black>" .  _($Head) . 
      "</font></A><a href=\"" . $page . "del=" . urlencode($Head) . 
      "\"><sup><font size=\"-1\" color=red>x</font></sup></a></th>\n";
}

function tableelement($Head, $string)
{
   global $column_set,$page;

   if( !in_array($Head,$column_set) )
      return;

   return "<td>$string</td>\n";
}

function order_string($sortA, $descA, $sortB, $descB)
{
   if( $sortA )
   {
      $order = "sort1=$sortA" . ($descA ? '&desc1=1' : '');
      if( $sortB )
         $order .= "&sort2=$sortB" . ($descB ? '&desc2=1' : '');
   }

   return $order;
}

function next_prev($page, $new_from_row, $next)
{
   global $sort1, $desc1, $sort2, $desc2;

   echo "<a href=\"" . $page . "from_row=$new_from_row&" . 
      order_string($sort1,$desc1,$sort2,$desc2) . "\">" .
      ($next ? "next page -->" : "<-- previous page") . "</a>";  
}

?>