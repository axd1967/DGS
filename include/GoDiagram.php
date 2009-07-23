<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once( 'include/classlib_userconfig.php' );
require_once( "include/coords.php" );

class GoDiagram
{
   var $ConfigBoard;

   var $Size;

   var $Left;
   var $Right;
   var $Down;
   var $Up;

   var $Data;

   function GoDiagram( $cfg_board, $_Size=19, $_Left=1, $_Right=19, $_Down=1, $_Up=19, $_Data = null)
   {
      global $player_row;
      $this->ConfigBoard = (is_null($cfg_board)) ? new ConfigBoard($player_row['ID']) : $cfg_board;

      $this->set_geometry($_Size, $_Left, $_Right, $_Down, $_Up);
      if( !empty($_Data ) )
         $this->set_data($_Data);
      else
         $this->clear_data();
   }

   function set_geometry( $_Size=19, $_Left=1, $_Right=19, $_Down=1, $_Up=19 )
   {
      $this->Size = limit($_Size, 2, MAX_BOARD_SIZE, 19);
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
      $size = extract_value($s, 'size', 2, MAX_BOARD_SIZE, $this->Size);
      $this->Size = $size;
      $this->set_geometry($this->Size);

      $view = extract_value($s, 'view');
      if(isset($view))
      {
         list($dl,$ur) = explode('-', strtolower($view));
         list($l,$d) = board2number_coords($dl, $size);
         list($r,$u) = board2number_coords($ur, $size);
      }
      else
      {
         $l = extract_value($s, 'left',  1, $size - 1, 1);
         $r = extract_value($s, 'right', 2, $size, $size);
         $u = extract_value($s, 'down',  1, $size - 1, 1);
         $d = extract_value($s, 'up',    2, $size, $size);
      }

      if( $l > $r ) swap($l, $r);
      if( $u < $d ) swap($u, $d);

      //FIXME: why restricting within existing view-box ? -> used set_geometry() now
      $this->set_geometry( $this->Size, $l, $r, $d, $u );
      //$this->Left = 1 + limit($l, 0, $size-2, $this->Left-1);
      //$this->Right = 1 + limit($r, $l, $size-1, $this->Right-1);
      //$this->Down = 1 + limit($d, 0, $size-2, $this->Down-1);
      //$this->Up = 1 + limit($u, $d, $size-1, $this->Up-1);

      if( empty($this->Data) )
         $this->clear_data();
      else
         $this->clear_data(); // TODO: Modify data
   }

   function get_empty_image($x, $y, $sz)
   {
      if( is_hoshi($x, $y, $sz) )
         $fig = 'h';
      else
      {
         if( $y == 0 )
            $fig = 'u';
         elseif( $y == $sz-1 )
            $fig = 'd';
         else
            $fig = 'e';

         if( $x == 0 )
            $fig .= 'l';
         elseif( $x == $sz-1 )
            $fig .= 'r';
      }
      return $fig;
   }

   function echo_board()
   {
      global $player_row, $base_path, $woodbgcolors;

      $string = '';
      $woodcolor = $this->ConfigBoard->get_wood_color();
      $stonesize = $this->ConfigBoard->get_stone_size();

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
            if( $str[0] === 'e' )
            {
               if( strlen($str)>2 && $str[1] === 'l' ) //empty+letter
                  $str = substr($str, 1);
               else
                  $str = $this->get_empty_image($x, $y, $this->Size) . substr($str, 1);
            }

            $string .= "<td><img border=0 src=\"$base_path$stonesize/" . $str .
               ".gif\" width=$stonesize height=$stonesize></td>";
         }
         $string .= "</tr>\n";
      }

      $string .= '</table></td></tr></table>' . "\n";

      return $string;
   }

   function echo_editor($nr)
   {
      $stonesize = $this->ConfigBoard->get_stone_size();
      if( empty($stonesize) ) $stonesize = 25;

      $woodcolor = $this->ConfigBoard->get_wood_color();
      if( empty($woodcolor) ) $woodcolor = 1;

      global $base_path;
      return '<script language="JavaScript" type="text/javascript">' . "\n" .
         "goeditor($nr, {$this->Size}, {$this->Left}, {$this->Right}, {$this->Down}, $this->Up, $stonesize, $woodcolor, '$base_path');\n" .
         "enter_data($nr, '{$this->Data}');\n" .
         "</script>\n" .
         '<input type="hidden" name="altered'.$nr.'" value="">' .
         '<input type="hidden" name="data'.$nr.'" value="">' . "\n";
   }

} // end of 'GoDiagram'


// extract-value from goban-tag: <goban name=str ...>
function extract_value($string, $name, $minimum=null, $maximum=null, $default=null)
{
   if( preg_match( "/ $name=([-\w]+)/i", $string, $matches) )
      return limit( $matches[1], $minimum, $maximum, $default );
   else
      return $default;
}

function callback_echo_board($matches)
{
   global $callback_diagrams, $callback_diag_nr;

   if( isset($callback_diagrams[$matches[1]])
         && is_object($callback_diagrams[$matches[1]])
         && method_exists($callback_diagrams[$matches[1]], 'echo_board') )
      return $callback_diagrams[$matches[1]]->echo_board();
   else
      return "[goban #".$matches[1]."]";
}

function replace_goban_tags_with_boards($text, $diagrams)
{
   global $callback_diagrams, $callback_diag_nr;

   $callback_diag_nr = 0;
   $callback_diagrams = $diagrams;
   return preg_replace_callback('/<goban id=(\d+)>/i', 'callback_echo_board', $text);
}

function create_godiagrams( &$text, $cfg_board )
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
      $altered = @$_REQUEST["altered$ID"];
      $save_data = false;

      if( isset($ID) && $ID > 0 )
      {
         $result = db_query( 'godiagram.create_godiagrams.find',
            "SELECT * FROM GoDiagrams WHERE ID=$ID" );

         if( @mysql_num_rows($result) == 1 )
         {
            $row = mysql_fetch_array( $result );
            $diagrams[$ID] = new GoDiagram( $cfg_board );
            $diagrams[$ID]->set_values_from_database_row($row);
         }
      }


      if( !($ID > 0) || empty($row['Saved']) ||
          ($row['Saved']=='Y' && (!preg_match('/^\s*id=\d+\s*$/i', $m) || $altered=='Y')))
      {
         if( $ID > 0 )
         {
            $diag = $diagrams[$ID];
            $diag->set_values_from_post($ID);
         }
         else
         {
            $diag = new GoDiagram( $cfg_board );
            $diag->set_values_from_goban_tag($m);
         }

         db_query( 'godiagram.create_godiagrams.insert',
            "INSERT INTO GoDiagrams SET " .
                     "Size={$diag->Size}, " .
                     "View_Left={$diag->Left}, " .
                     "View_Right={$diag->Right}, " .
                     "View_Down={$diag->Down}, " .
                     "View_Up={$diag->Up}, " .
                     "Date=FROM_UNIXTIME($NOW)" );

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
         db_query( 'godiagram.create_godiagrams.save',
            'UPDATE GoDiagrams SET Data="' . $diagrams[$ID]->Data . '" ' .
            "WHERE ID=$ID AND Saved='N' LIMIT 1" );
      }
   } //endfor

   return $diagrams;
} // create_godiagrams

function find_godiagrams($text, $cfg_board)
{
   $diagrams = array();
   if( !preg_match_all('/<goban id=(\d+)>/i', $text, $matches) )
      return $diagrams;

   $diagram_IDs = array();
   foreach( $matches[1] as $ID )
   {
      if( $ID > 0 ) $diagram_IDs[]= $ID;
   }

   $result = db_query( 'godiagram.find_godiagrams',
      "SELECT * FROM GoDiagrams WHERE ID IN(" . implode(',',$diagram_IDs) .")" );

   while( $row = mysql_fetch_array( $result ) )
   {
      $diagrams[$row['ID']] = new GoDiagram( $cfg_board );
      $diagrams[$row['ID']]->set_values_from_database_row($row);
   }

   return $diagrams;
}


function draw_editors($GoDiagrams)
{
   $string = '';
   foreach( $GoDiagrams as $nr => $diagram )
      $string .= $diagram->echo_editor($nr);
   return $string;
}

function save_diagrams($GoDiagrams)
{
   $IDs = array();
   foreach( $GoDiagrams as $ID => $diagram )
   {
      if( $ID > 0 ) $IDs[]= $ID;
   }

   if( count($IDs) > 0 )
      db_query( 'godiagram.save_diagrams',
         "UPDATE GoDiagrams SET Saved='Y' WHERE ID IN (" . implode(',', $IDs) . ")" );
}

?>
