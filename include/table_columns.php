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

function tablehead($nr, $Head, $sort_string=NULL, $desc_default=false, $undeletable=false)
{
   global $sort1, $desc1, $sort2, $desc2,$column_set,$page,$removed_columns;

   $col_pos = 1 << ($nr-1);

   if( !($col_pos & $column_set) )
   {
      if( !is_array($removed_columns) )
         $removed_columns = array('');
      $removed_columns[$nr] = $Head;
      return;
   }

   if( !$undeletable )
      $delete_string = "<a href=\"" . $page .
         ($sort1 ? order_string($sort1,$desc1,$sort2,$desc2) . '&' : '') .
         "del=$nr" .
         "\"><sup><font size=\"-1\" color=red>x</font></sup></a>";

   if( !$sort_string )
      return "<th nowrap valign=bottom><font color=black>" . $Head .
         "</font>$delete_string</th>\n";

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

   return "<th nowrap valign=bottom><A href=\"$page" . order_string($s1,$d1,$s2,$d2) .
      "\"><font color=black>" .  $Head .
      "</font></A>$delete_string</th>\n";
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

function next_prev($new_from_row, $next)
{
   global $sort1, $desc1, $sort2, $desc2, $page;

   return "<a href=\"" . $page . "from_row=$new_from_row&" .
      order_string($sort1,$desc1,$sort2,$desc2) . "\">" .
      ($next ? T_("next page") . " -->" : "<-- " . T_("prev page")) . "</a>";
}

function strip_last_et($string)
{
   $c = substr($string, -1);

   if( $c == '&' or $c == '?' )
      return substr($string, 0, -1);

   return $string;
}

function add_column_form()
{
   global $removed_columns, $page;

   if( count($removed_columns) <= 1 )
      return '';

   $string = form_start( 'add_column_form', strip_last_et($page), 'POST' ) .
      form_insert_row('SELECTBOX', 'add', 1, $removed_columns, '', false,
                      'SUBMITBUTTON', 'action', 'Add Column') .
      form_end();

   return $string;
}

// Needed for php < 4.0.5
if( !function_exists("array_search") )
{
   function array_search($needle, $haystack)
      {
         while( list($key,$val) = each($haystack) )
         {
            if( $val == $needle )
               return $key;
         }
         return false;
      }
}

function add_or_del($add, $del, $mysql_column)
{
   global $column_set, $player_row;

   if( $del or $add )
   {
      if( $add )
         $column_set |= 1 << ($add-1);
      if( $del )
         $column_set &= ~(1 << ($del-1));

      $query = "UPDATE Players " .
          "SET $mysql_column=$column_set " .
          "WHERE ID=" . $player_row["ID"];

      mysql_query($query);
   }
}
function start_end_column_table($start)
{
   global $from_row, $nr_rows, $show_rows, $RowsPerPage, $table_head_color;

   if( $start )
      $string =
         "<table border=0 cellspacing=0 cellpadding=3 align=center>\n";
   else
      $string = "";


   $string .= "<tr><td align=left colspan=2>";

   if( $from_row > 0 )
      $string .= next_prev($from_row-$RowsPerPage, false);

   $string .= "</td>\n<td align=right colspan=20>";

   if( $show_rows < $nr_rows )
      $string .= next_prev($from_row+$RowsPerPage, true);

   $string .= "</td>\n</tr>\n";

   if( $start )
      $string .= "<tr bgcolor=$table_head_color>";
   else
      $string .= '<tr><td colspan=20 align=right>' .
         add_column_form() . "</td></tr></table>\n";

   return $string;
}

?>