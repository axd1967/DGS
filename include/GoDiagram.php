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

require_once( "include/coords.php" );

$hoshi_dist = array(0,0,0,0,0,3,0,4,3,3,3,3,4,4,4,4,4,4,4,4,4,4,4,4,4,4);
$hoshi_pos  = array(0,0,0,0,0,1,0,1,4,5,4,5,4,7,7,7,7,7,7,7,7,7,7,7,7,7);

class GoDiagram
{
   var $Size;

   var $Left;
   var $Right;
   var $Down;
   var $Up;

   var $Data;



   function GoDiagram( $_Size=19, $_Left=1, $_Right=19, $_Down=1, $_Up=19, $_Data = null)
      {
         $this->set_geometry($_Size, $_Left, $_Right, $_Down, $_Up);
         if( !empty($_Data ) )
            $this->set_data($_Data);
         else
            $this->clear_data();
      }

   function set_geometry( $_Size=19, $_Left=1, $_Right=19, $_Down=1, $_Up=19 )
      {
         $this->Size = limit($_Size, 2, 25, 19);
         $this->Left = limit($_Left, 1, $this->Size - 1, 1);
         $this->Right = limit($_Right, $this->Left + 1, $this->Size, $this->Size);
         $this->Down = limit($_Down, 1, $this->Size - 1, 1);
         $this->Up = limit($_Up, $this->Down + 1, $this->Size, $this->Size);
      }

   function set_data( $_Data )
      {
         $this->Data = $_Data;

         // TODO: Check data ?
      }

   function clear_data()
      {
         $s = 'e' . str_repeat(',e', $this->Right - $this->Left);
         $this->Data = $s . str_repeat(";$s", $this->Up - $this->Down);
      }

   function set_values_from_post( $ID )
      {
         $this->set_data( $_REQUEST["data$ID"] );
      }

   function set_values_from_database_row( $row )
      {
         $this->set_geometry($row['Size'],
                             $row['View_Left'], $row['View_Right'],
                             $row['View_Down'], $row['View_Up']);
         $this->set_data( $row['Data'] );
      }

   function set_values_from_goban_tag( $s )
      {
         $this->Size = extract_value($s, 'size', 2, 25, $this->Size);
         $this->set_geometry($this->Size);

         $view = strtolower(extract_value($s, 'view'));
         if(isset($view))
         {
            list($dl,$ur) = split('-', $view);
            list($l,$d) = board_coords2number($dl, $this->Size);
            list($r,$u) = board_coords2number($ur, $this->Size);
         }
         else
         {
            $l = extract_value($s, 'left', 1, $this->Size);
            $r = extract_value($s, 'right', 1, $this->Size);
            $u = extract_value($s, 'down', 1, $this->Size);
            $d = extract_value($s, 'up', 1, $this->Size);
         }

         if( $l > $r ) swap($l, $r);
         if( $u < $d ) swap($u, $d);

         $this->Left = 1 + limit($l, 0, $this->Size-2, $this->Left-1);
         $this->Right = 1 + limit($r, $l, $this->Size-1, $this->Right-1);
         $this->Down = 1 + limit($d, 0, $this->Size-2, $this->Down-1);
         $this->Up = 1 + limit($u, $d, $this->Size-1, $this->Up-1);

         if( empty($this->Data) )
            $this->clear_data();
         else
            $this->clear_data(); // TODO: Modify data
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

         for( $y = $this->Down-1; $y < $this->Up; $y++)
         {
            $row = explode(',', $data_rows[$y-$this->Down+1]);

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
            "goeditor($nr, {$this->Size}, {$this->Left}, {$this->Right}, {$this->Down}, $this->Up, $stonesize, $woodcolor, 1);\n" .
            "enter_data($nr, '{$this->Data}');\n" .
            "</script>\n" .
            '<input type="hidden" name="altered'.$nr.'" value="">' .
            '<input type="hidden" name="data'.$nr.'" value="">' . "\n";
      }

}


function extract_value($string, $name, $minimum=null, $maximum=null, $default=null)
{
   preg_match("/$name=([-\w]+)/i", $string, $matches);
   return limit( $matches[1], $minimum, $maximum, $default );
}

function callback($matches)
{
   global $callback_diagrams, $callback_diag_nr;

   return $callback_diagrams[$matches[1]]->echo_board();
}

function replace_goban_tags_with_boards($text, $diagrams)
{
   global $callback_diagrams, $callback_diag_nr;

   $callback_diag_nr = 0;
   $callback_diagrams = $diagrams;
   return preg_replace_callback('/<goban id=(\d+)>/i', 'callback', $text);
}

function create_godiagrams(&$text)
{
   global $NOW;

   $diagrams = array();

   if( !preg_match_all('/<goban([^>]*)>/i', $text, $matches) )
      return $diagrams;

   $text = preg_replace('/<goban([^>]*)>/i','<goban id=#>', $text);


   $old_diagrams = array();

   foreach( $matches[1] as $m )
      {
         $ID = extract_value($m, 'id' );
         $altered = $_REQUEST["altered$ID"];
         $save_data = false;

         if( $ID > 0 )
         {
            $result = mysql_query("SELECT * FROM GoDiagrams WHERE ID=$ID");

            if( mysql_num_rows($result) == 1 )
            {
               $row = mysql_fetch_array( $result );
               $diagrams[$ID] = new GoDiagram();
               $diagrams[$ID]->set_values_from_database_row($row);
            }
         }


         if( !($ID > 0) or empty($row['Saved']) or
             ($row['Saved']=='Y' and (!preg_match('/^\s*id=\d+\s*$/i', $m) or $altered=='Y')))
         {
            if( $ID > 0 )
            {
               $diag = $diagrams[$ID];
               $diag->set_values_from_post($ID);
            }
            else
            {
               $diag = new GoDiagram();
               $diag->set_values_from_goban_tag($m);
            }

            mysql_query("INSERT INTO GoDiagrams SET " .
                        "Size={$diag->Size}, " .
                        "View_Left={$diag->Left}, " .
                        "View_Right={$diag->Right}, " .
                        "View_Down={$diag->Down}, " .
                        "View_Up={$diag->Up}, " .
                        "Date=FROM_UNIXTIME($NOW)") or die(mysql_error());

            $New_ID = mysql_insert_id();
            $diagrams[$New_ID] = $diag;
            if( $ID > 0 )
               unset($diagrams[$ID]);
            $ID = $New_ID;

            $save_data = true;
         }
         else
         {
            if( !preg_match('/^\s*id=\d+\s*$/i', $m) )
            {
               $diagrams[$ID]->set_values_from_goban_tag($m);
               $save_data = true;
            }

            if( $altered == 'Y' )
            {
               $diagrams[$ID]->set_values_from_post($ID);
               $save_data = true;
            }
         }

         $text = preg_replace('/<goban id=#>/i',"<goban id=$ID>", $text, 1);

         if( $save_data )
         {
            mysql_query('UPDATE GoDiagrams SET Data="' . $diagrams[$ID]->Data . '" ' .
                         "WHERE ID=$ID AND Saved='N' LIMIT 1");
         }
      }

   return $diagrams;
}

function find_godiagrams($text)
{
   $diagram_IDs = array();
   if( !preg_match_all('/<goban id=(\d+)>/i', $text, $matches) )
      return $diagrams;

   foreach( $matches[1] as $ID )
      {
         if( $ID > 0 )
            array_push($diagram_IDs, $ID);
      }

   $result = mysql_query("SELECT * FROM GoDiagrams " .
                         "WHERE ID IN(" . implode(',',$diagram_IDs) .")")
      or die(mysql_error());

   $diagrams = array();
   while( $row = mysql_fetch_array( $result ) )
   {
      $diagrams[$row['ID']] = new GoDiagram();
      $diagrams[$row['ID']]->set_values_from_database_row($row);
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

function save_diagrams($GoDiagrams)
{
   $IDs = array();
   foreach( $GoDiagrams as $ID => $diagram )
      if( $ID > 0 )
         array_push($IDs, $ID);

   if( count($IDs) > 0 )
      mysql_query("UPDATE GoDiagrams SET Saved='Y' WHERE ID IN (" . implode(',', $IDs) . ")")
         or die(mysql_error());
}

?>