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

function extract_value($string, $name, $default)
{
   if( preg_match("/$name=(\w+)/i", $string, $matches) > 0 )
      return $matches[1];
   else
      return $default;
}

function draw_diagrams($mid, $text)
{
   global $player_row;

   $stonesize = $player_row['Stonesize'];
   if( empty($stonesize) ) $stonesize = 25;

   $woodcolor = $player_row['Woodcolor'];
   if( empty($woodcolor) ) $woodcolor = 1;

   $string = '';

   if( empty($mid) )
   {
      if( !preg_match_all('/<goban([^>]*)>/i', $text, $matches) )
         return;

      $nr = 0;
      foreach( $matches[1] as $m )
      {
         $nr ++;
         $string .= '<script language="JavaScript">' . "\n" .
            "goeditor($nr, " . extract_value($m, 'size', 19) . ',' .
            extract_value($m, 'left', 1) . ',' .
            extract_value($m, 'right', 19) . ',' .
            extract_value($m, 'up', 1) . ',' .
            extract_value($m, 'down', 19) . ',' .
            "$stonesize,$woodcolor,true);\n" .
            "</script>\n";

         $string .= '<input type="hidden" name="dimensions'.$nr.'" value="">' .
            '<input type="hidden" name="data'.$nr.'" value="">' . "\n";
      }
   }
   else
   {
      if( !preg_match_all('/<goban id=(\d+)>/i', $text, $matches) )
         return;

      $result = mysql_query("SELECT * FROM GoDiagrams " .
                            "WHERE mid=\"$mid\" " .
                            "AND ID IN (" . implode(',', $matches[1]) . ")");

      while( $row = mysql_fetch_array( $result ) )
      {
         $string .= '<script language="JavaScript">' . "\n" .
            "goeditor(" . $row['ID'] . ',' . $row['Size'] . ',' .
            $row['View_Left'] . ',' . $row['View_Right'] . ',' .
            $row['View_UP'] . ',' . $row['View_Down'] . ',' .
            "$stonesize,$woodcolor,true);\n" .
            "</script>\n";
      }
   }

   return $string;
}


?>