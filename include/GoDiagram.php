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


$hoshi_dist = array(0,0,0,0,0,3,0,4,3,3,3,3,4,4,4,4,4,4,4,4,4,4,4,4,4,4);
$hoshi_pos  = array(0,0,0,0,0,1,0,1,4,5,4,5,4,7,7,7,7,7,7,7,7,7,7,7,7,7);

class GoDiagram
{
   var $Size;

   var $Left;
   var $Right;
   var $Up;
   var $Down;

   var $Data;



   function GoDiagram( $_Size=19, $_Left=1, $_Right=19, $_Up=1, $_Down=19, $_Data = null)
      {
         $this->Size = $_Size;
         $this->Left = $_Left;
         $this->Right = $_Right;
         $this->Up = $_Up;
         $this->Down = $_Down;
         $this->Data = $_Data;
      }

   function limit($val, $minimum, $maximum, $default)
      {
         if( !is_numeric($val) )
            return $default;
         else if( $val < $minimum )
            return $minimum;
         else if( $val > $maximum )
            return $maximum;

         return $val;
      }

   function extract_value($string, $name, $minimum, $maximum, $default)
      {
         preg_match("/$name=(\w+)/i", $string, $matches);
         return $this->limit( $matches[1], $minimum, $maximum, $default );
      }

   function set_geometry( $_Size=19, $_Left=1, $_Right=19, $_Up=1, $_Down=19 )
      {
         $this->Size = $this->limit($_Size, 2, 25, 19);
         $this->Left = $this->limit($_Left, 1, $this->Size - 1, 1);
         $this->Right = $this->limit($_Right, $this->Left + 1, $this->Size, $this->Size);
         $this->Up = $this->limit($_Up, 1, $this->Size - 1, 1);
         $this->Down = $this->limit($_Down, $this->Up + 1, $this->Size, $this->Size);
      }

   function set_data( $_Data )
      {
         $this->Data = $_Data;

         // TODO: Check data ?
      }

   function clear_data()
      {
         $s = 'e' . str_repeat(',e', $this->Right - $this->Left);
         $this->Data = $s . str_repeat(";$s", $this->Down - $this->Up);
      }

   function set_values_from_post( $nr )
      {
         list($_Size, $_Left, $_Right, $_Up, $_Down) =
            explode( ',', $_POST['dimensions' . $nr], 5 );

         $this->set_geometry($_Size, $_Left, $_Right, $_Up, $_Down);

         $this->set_data( $_POST['data' . $nr] );
      }

   function set_values_from_database_row( $row )
      {
         $this->set_geometry($row['Size'],
                             $row['View_Left'], $row['View_Right'],
                             $row['View_Up'], $row['View_Down']);
         $this->set_data( $row['Data'] );
      }

   function set_values_from_goban_tag( $s )
      {
         $this->Size = $this->extract_value($s, 'size', 2, 25, 19);
         $this->Left = $this->extract_value($s, 'left', 1, $this->Size - 1, 1);
         $this->Right = $this->extract_value($s, 'right', $this->Left + 1, $this->Size, $this->Size);
         $this->Up = $this->extract_value($s, 'up', 1, $this->Size - 1, 1);
         $this->Down = $this->extract_value($s, 'down', $this->Up + 1, $this->Size, $this->Size);
         $this->clear_data();
      }

   function get_empty_image($x, $y, $sz)
      {
         global $hoshi_pos, $hoshi_dist;

         $fig = 'e';

         if( $hoshi_pos[$sz] &
             ( ( ( $x == $hoshi_dist[$sz]-1 || $x == $sz-$hoshi_dist[$sz] ? 2 : 0 ) +
                 ( $x*2+1 == $sz ? 1 : 0) ) *
               ( ( $y == $hoshi_dist[$sz]-1 || $y == $sz-$hoshi_dist[$sz] ? 2 : 0 ) +
                 ( $y*2+1 == $sz ? 1 : 0) ) ) )
            $fig = 'h';

         if( $y == 0 ) $fig = 'u';
         if( $y == $sz-1 ) $fig = 'd';
         if( $x == 0 ) $fig .= 'l';
         if( $x == $sz-1 ) $fig .= 'r';

         return $fig;
      }

   function echo_board()
      {
         global $player_row, $base_path, $woodbgcolors;

         $string = '';
         $woodcolor = $player_row['Woodcolor'];
         $stonesize = $player_row['Stonesize'];

         $data_rows = explode(';', $this->Data);

         $woodstring = ( $woodcolor > 10
                         ? 'bgcolor=' . $woodbgcolors[$woodcolor - 10]
                         : 'background="'.$base_path.'images/wood' . $woodcolor . '.gif"');

         $string .= "<table border=0 cellpadding=0 cellspacing=0 $woodstring><tr><td valign=top><table border=0 cellpadding=0 cellspacing=0 align=center valign=center background=\"\">";

         for( $y = $this->Up-1; $y < $this->Down; $y++)
         {
            $row = explode(',', $data_rows[$y-$this->Up+1]);

            $string .= "<tr>\n";
            for( $x = $this->Left-1; $x < $this->Right; $x++)
            {
               $str = $row[$x-$this->Left+1];
               if( $str{0} === 'e' )
                  if( $str{1} === 'l' )
                     $str = substr($str, 1);
                  else
                     $str = $this->get_empty_image($x, $y, $this->Size) . substr($str, 1);

               $string .= "<td><img border=0 src=\"$base_path$stonesize/" . $str .
                  ".gif\" width=$stonesize height=$stonesize></td>";
            }
            $string .= "</tr>\n";
         }

         $string .= '</table></td></tr></table>' . "\n";

         return $string;
      }

   function echo_editor($nr, $woodcolor, $stonesize)
      {
         return '<script language="JavaScript">' . "\n" .
            "goeditor($nr, {$this->Size}, {$this->Left}, {$this->Right}, {$this->Up}, $this->Down, $stonesize, $woodcolor, 1);\n" .
            "enter_data($nr, '{$this->Data}');\n" .
            "</script>\n" .
            '<input type="hidden" name="dimensions'.$nr.'" value="">' .
            '<input type="hidden" name="data'.$nr.'" value="">' . "\n";
      }

}


function callback($matches)
{
   global $callback_diagrams, $callback_diag_nr;

   return $callback_diagrams[++$callback_diag_nr]->echo_board();
}

function replace_goban_tags_with_boards($text, $diagrams)
{
   global $callback_diagrams, $callback_diag_nr;

   $callback_diag_nr = 0;
   $callback_diagrams = $diagrams;
   return preg_replace_callback('/<goban([^>]*)>/i', 'callback', $text);
}

function create_godiagrams($mid, $text)
{
   $diagrams = array();

   if( empty($mid) )
   {
      // New message, get info from $_POST or <goban> tag

      if( !preg_match_all('/<goban([^>]*)>/i', $text, $matches) )
         return;

//      $text = preg_replace('/<goban([^>]*)>/i','<goban>', $text);

      $nr = 0;
      $post_nr = 0;
      foreach( $matches[1] as $m )
      {
         $nr++;
         if( empty($m) )
         {
            // Use POST data
            $post_nr++;

            if( empty($_POST["dimensions$post_nr"]) )
            {
               error("forum_no_diagram_found");
            }

            $diagrams[$nr] = new GoDiagram();
            $diagrams[$nr]->set_values_from_post($post_nr);
         }
         else
         {
            // New diagram with dimensions from the regexp match

            $diagrams[$nr] = new GoDiagram();
            $diagrams[$nr]->set_values_from_goban_tag($m);
         }
      }
   }
   else
   {
      $N = preg_match_all('/<goban( id=(\d+))?>/i', $text, $matches);

      if( !($N) )
         return;

      $result = mysql_query("SELECT * FROM GoDiagrams WHERE mid='$mid'");

      if( mysql_num_rows($result) !== $N )
         warn('GoDiagram: Missmatch in number of diagrams');

      while( $row = mysql_fetch_array( $result ) )
      {
         $diagrams[$row['diagid']] = new GoDiagram();
         $diagrams[$row['diagid']]->set_values_from_database_row($row);
      }
   }

   return $diagrams;
}

function draw_editors($GoDiagrams)
{
   global $player_row;

   $stonesize = $player_row['Stonesize'];
   if( empty($stonesize) ) $stonesize = 25;

   $woodcolor = $player_row['Woodcolor'];
   if( empty($woodcolor) ) $woodcolor = 1;

   $string = '';

   foreach( $GoDiagrams as $nr => $diagram )
      {
         $string .= $diagram->echo_editor($nr, $woodcolor, $stonesize);
      }

   return $string;
}

?>