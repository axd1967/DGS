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

define('EDGE_SIZE', 10);


class Board
{

   var $gid;
   var $size;
   var $max_moves;
   var $coord_borders;
   var $stone_size;
   var $woodcolor;

   var $array; //2D array: [$PosX][$PosY] => $Stone
   var $moves; //array: [$MoveNr] => array($Stone,$PosX,$PosY)
   var $marks; //array: [$sgf_coord] => $mark
   var $captures; //array: [$MoveNr] => array($Stone,$PosX,$PosY)
   var $size;

   //Last move shown ($movemrkx<0 if PASS, RESIGN or SCORE)
   var $movemrkx, $movemrky, $movecol, $movemsg;

   var $dirx, $diry; //directions arrays


   // Constructor. Create a new board array and initialize it.
   function Board( $gid=0, $size=19, $max_moves=0 )
   {
      $this->gid = $gid;
      $this->size = $size;
      $this->max_moves = $max_moves;

      $this->coord_borders = -1;
      $this->stone_size = 25;
      $this->woodcolor = 1;

      $this->array = NULL;
      $this->moves = array();
      $this->marks = NULL;
      $this->captures = NULL;

      $this->movemrkx = $this->movemrky = -1;
      $this->movecol = DAME;
      $this->movemsg = '';

      $this->dirx = array( -1,0,1,0 );
      $this->diry = array( 0,-1,0,1 );
   }


   // fills $array with positions where the stones are.
   // fills $moves with moves and coordinates.
   // keep the coords, color and message of the move $move.
   function load_from_db( &$game_row, $move=0, $no_marked_dead=true )
   {
      $this->array = NULL;
      $this->moves = array();
      $this->marks = array();
      $this->captures = array();
      $this->movemrkx = $this->movemrky = -1; // don't use as last move coord
      $this->movecol = DAME;
      $this->movemsg = '';

      $gid = $game_row['ID'];
      if( $gid <= 0 )
         return FALSE;

      $this->gid = $gid;
      $this->size = $game_row['Size'];
      $this->max_moves = $game_row['Moves'];

      if( $this->max_moves <= 0 )
         return TRUE;

      $result = mysql_query( "SELECT * FROM Moves WHERE gid=$gid ORDER BY ID" );
      if( $result===FALSE or @mysql_num_rows($result) < 1 )
         return FALSE;

      if( $move<=0 or $move>$this->max_moves )
         $move = $this->max_moves;

      $marked_dead = array();
      $removed_dead = FALSE;

      while( $row = mysql_fetch_assoc($result) )
      {

         extract($row);

         if( $Stone == BLACK or $Stone == WHITE )
         {
            $this->moves[$MoveNr] = array( $Stone, $PosX, $PosY);
         }

         if( $MoveNr > $move )
         {
            if( $MoveNr > $this->max_moves )
            {
               $this->fix_corrupted_move_table( $gid);
               break;
            }
            continue;
         }

         if( $Stone <= WHITE ) //including NONE (prisoners)
         {
            if( $PosX < 0 ) continue; //excluding PASS, RESIGN and SCORE

            $this->array[$PosX][$PosY] = $Stone; //including DAME (prisoners)

            $removed_dead = FALSE; // restart removal
         }
         else if( $Stone == MARKED_BY_WHITE or $Stone == MARKED_BY_BLACK)
         {
            if( !$removed_dead )
            {
               $marked_dead = array(); // restart removal
               $removed_dead = TRUE;
            }
            $marked_dead[] = array( $PosX, $PosY);
         }
      }

      if( !$no_marked_dead and $removed_dead )
      {
         foreach( $marked_dead as $sub )
         {
            list( $x, $y) = $sub;
            @$this->array[$x][$y] ^= OFFSET_MARKED;
         }
      }
      
      if( isset($this->moves[$move]) )
      {
         list($this->movecol, $this->movemrkx, $this->movemrky) = $this->moves[$move];

         //No movemsg if we don't have movecol
         $result = mysql_query( "SELECT Text FROM MoveMessages WHERE gid=$gid AND MoveNr=$move" );

         if( @mysql_num_rows($result) == 1 )
         {
            $row = mysql_fetch_assoc($result);
            $this->movemsg = trim($row['Text']);
         }
         //else $this->movemsg = '';
      }

      return TRUE;
   }


   function set_move_mark( $x=-1, $y=-1)
   {
      $this->movemrkx= $x;
      $this->movemrky= $y;
   }


   function move_numbers( $start, $end)
   {
      $start = max( $start, 1);
      for( $n=$end; $n>=$start; $n-- )
      {
         if( isset($this->moves[$n]) )
         {
            list( $s, $x, $y) = $this->moves[$n];
            //if( $s != BLACK and $s != WHITE ) continue;
            $m = number2sgf_coords( $x, $y, $this->size);
            if( $m )
            {
               $b = @$this->array[$x][$y];
               if( !isset($this->marks[$m]) )
               {
                  if( ($b & 0x3)==$s ) // or $s==($b^OFFSET_MARKED) )
                  {
                     $this->marks[$m] = (($n-1)%100)+1;
                     continue;
                  }
                  if( $b==NONE )
                  {
                     $this->marks[$m] = 'x';
                  }
               }
               $this->captures[$n] = array( $s, $x, $y);
            }
         }
      }      
   }


   function draw_captures_box( $caption)
   {
      if( !is_array($this->captures) )
         return false;
      $n= count($this->captures);
      if( $n < 1 )
         return false;

      $stone_size = $this->stone_size;
      $size = $this->size;

      $wcap= array();
      $bcap= array();
      foreach( $this->captures as $n => $sub )
      {
         list( $s, $x, $y) = $sub;
         $m = number2board_coords( $x, $y, $size);
         $r = (($n-1)%100)+1;
         if( $s == BLACK )
         {
            array_unshift( $bcap,
                  image( "$stone_size/b$r.gif", "X$n", '', 'align=middle')
                   . "&nbsp;:&nbsp;$m<br>\n" );
         }
         else if( $s == WHITE )
         {
            array_unshift( $wcap,
                  image( "$stone_size/w$r.gif", "O$n", '', 'align=middle')
                   . "&nbsp;:&nbsp;$m<br>\n" );
         }
      }

      echo "<table class=captures>\n";
      echo "<tr>\n<th colspan=2>$caption</th>\n </tr>\n";
      echo "<tr>\n<td class=b>\n";
      foreach( $bcap as $s )
         echo $s;
      echo "</td>\n<td class=w>\n";
      foreach( $wcap as $s )
         echo $s;
      echo "</td>\n</tr>\n";
      echo "</table>\n";

      return true;
   }


   function set_style( &$player_row)
   {
      if( isset($player_row['Boardcoords']) &&
          $player_row['Boardcoords'] >= 0 && $player_row['Boardcoords'] <= 0x3F )
         $this->coord_borders = $player_row['Boardcoords'];
      else
         $this->coord_borders = -1;

      if( isset($player_row['Stonesize']) &&
          $player_row['Stonesize'] >= 5 && $player_row['Stonesize'] <= 50 )
         $this->stone_size = $player_row['Stonesize'];
      else
         $this->stone_size = 25;

      if( isset($player_row['Woodcolor']) &&
           ( $player_row['Woodcolor'] >= 1 and $player_row['Woodcolor'] <= 5
          or $player_row['Woodcolor'] >= 11 and $player_row['Woodcolor'] <= 15 ) )
         $this->woodcolor = $player_row['Woodcolor'];
      else
         $this->woodcolor = 1;
   }


   function style_string()
   {
      $stone_size = $this->stone_size;
      $coord_width = floor($stone_size*31/25);

      $tmp = "img.brd%s{ width:%dpx; height:%dpx;}\n";
      return sprintf( $tmp, 'x', $stone_size, $stone_size)
           . sprintf( $tmp, 'l', $stone_size, $stone_size)
           . sprintf( $tmp, 'n', $coord_width, $stone_size);
   }


   function draw_coord_row( $coord_start_letter, $coord_alt, $coord_end,
                            $coord_left, $coord_right )
   {
      echo "<tr>\n";

      if( $coord_left )
         echo $coord_left;

      $colnr = 1;
      $letter = 'a';
      while( $colnr <= $this->size )
      {
         echo $coord_start_letter . $letter . $coord_alt . $letter . $coord_end;
         $colnr++;
         $letter++; if( $letter == 'i' ) $letter++;
      }

      if( $coord_right )
         echo $coord_right;

      echo "</tr>\n";
   }


   function draw_edge_row( $edge_start, $edge_coord,
                           $border_start, $border_imgs, $border_rem )
   {
      echo "<tr>\n";

      if( $this->coord_borders & LEFT )
         echo $edge_coord;

      echo '<td>' . $edge_start . 'l.gif" width='.EDGE_SIZE.'>' . "</td>\n";

      echo '<td colspan=' . $this->size . ' width=' . $this->size*$this->stone_size . '>';

      if( $border_imgs >= 0 )
         echo $edge_start . '.gif" width=' . $border_start . '>';
      for($i=0; $i<$border_imgs; $i++ )
         echo $edge_start . '.gif" width=150>';
      echo $edge_start . '.gif" width=' . $border_rem . '>';

      echo "</td>\n" . '<td>' . $edge_start . 'r.gif" width='.EDGE_SIZE.'>' . "</td>\n";

      if( $this->coord_borders & RIGHT )
         echo $edge_coord;

      echo "</tr>\n";
   }


   function draw_board( $may_play=false, $action='', $stonestring='')
   {
      global $woodbgcolors;

      if( ($gid=$this->gid) <= 0 )
         $may_play= false;

      $stone_size = $this->stone_size;
      $coord_width = floor($stone_size*31/25);

      $smooth_edge = ( ($this->coord_borders & SMOOTH_EDGE) and ($this->woodcolor < 10) );

      if( $smooth_edge )
      {
         $border_start = 140 - ( $this->coord_borders & LEFT ? $coord_width : 0 );
         $border_imgs = ceil( ($this->size * $stone_size - $border_start) / 150 ) - 1;
         $border_rem = $this->size * $stone_size - $border_start - 150 * $border_imgs;
         if( $border_imgs < 0 )
            $border_rem = $this->size * $stone_size;

         $edge_coord = '<td><img alt=" " height='.EDGE_SIZE.' src="images/blank.gif" width=' . $coord_width . "></td>\n";
         $edge_start = '<img alt=" " height='.EDGE_SIZE.' src="images/wood' . $this->woodcolor . '_' ;
         $edge_vert = '<img alt=" " height=' . $stone_size . ' width='.EDGE_SIZE.' src="images/wood' . $this->woodcolor . '_' ;
      }

      $coord_alt = '.gif" alt="';
      $coord_end = "\"></td>\n";
      if( $this->coord_borders & (LEFT | RIGHT) )
      {
         $coord_start_number = "<td><img class=brdn src=\"$stone_size/c";
      }
      if( $this->coord_borders & (UP | DOWN) )
      {
         $coord_start_letter = "<td><img class=brdl src=\"$stone_size/c";

         $s = ($this->coord_borders & LEFT ? 1 : 0 ) + ( $smooth_edge ? 1 : 0 );
         if ( $s )
            $coord_left = "<td colspan=$s><img src=\"images/blank.gif\" width=" .
             ( ( $this->coord_borders & LEFT ? $coord_width : 0 )
             + ( $smooth_edge ? EDGE_SIZE : 0 ) ) .
             " height=$stone_size alt=\" \"></td>\n";
         else
            $coord_left = '';

         $s = ($this->coord_borders & RIGHT ? 1 : 0 ) + ( $smooth_edge ? 1 : 0 );
         if ( $s )
            $coord_right = "<td colspan=$s><img src=\"images/blank.gif\" width=" .
             ( ( $this->coord_borders & RIGHT ? $coord_width : 0 )
             + ( $smooth_edge ? EDGE_SIZE : 0 ) ) .
             " height=$stone_size alt=\" \"></td>\n";
         else
            $coord_right = '';
      }

      $nomove_start = "<td><img class=brdx alt=\"";
      $nomove_end = ".gif\"></td>\n";
      if( $may_play )
      {
         switch( $action )
         {
            case 'handicap':
               $on_not_empty = false;
               $on_empty = true;
               $move_start = "<td><a href=\"game.php?g=$gid".URI_AMP."a=handicap".URI_AMP."c=";
               $move_alt = "\"><img class=brdx border=0 alt=\"";
               if( $stonestring )
                  $move_alt = URI_AMP."s=$stonestring".$move_alt;
               break;
            case 'remove':
               $on_not_empty = true;
               if( MAX_SEKI_MARK>0 )
                  $on_empty = true;
               else
                  $on_empty = false;
               $move_start = "<td><a href=\"game.php?g=$gid".URI_AMP."a=remove".URI_AMP."c=";
               $move_alt = "\"><img class=brdx border=0 alt=\"";
               if( $stonestring )
                  $move_alt = URI_AMP."s=$stonestring".$move_alt;
               break;
            default:
               $on_not_empty = false;
               $on_empty = true;
               $move_start = "<td><a href=\"game.php?g=$gid".URI_AMP."a=move".URI_AMP."c=";
               $move_alt = "\"><img class=brdx border=0 alt=\"";
               break;
         }
         $move_end = ".gif\"></a></td>\n";
      }

      if( $this->movemsg )
         echo "<table id=\"game_board\" border=2 cellpadding=3 align=center><tr>" .
            "<td width=\"" . $stone_size*19 . "\" align=left>$this->movemsg</td></tr></table><BR>\n";


      if( $this->woodcolor > 10 )
         $woodstring = 'bgcolor="' . $woodbgcolors[$this->woodcolor - 10] . '"';
      else
         $woodstring = 'style="background-image:url(images/wood' . $this->woodcolor . '.gif);"';

      echo '<table border=0 cellpadding=0 cellspacing=0 ' . 
          $woodstring . ' align=center>';

      if( $this->coord_borders & UP )
         $this->draw_coord_row( $coord_start_letter, $coord_alt, $coord_end,
                           $coord_left, $coord_right );

      if( $smooth_edge )
         $this->draw_edge_row( $edge_start.'u', $edge_coord,
                               $border_start, $border_imgs, $border_rem );

      $letter_r = 'a';
      for($rownr = $this->size; $rownr > 0; $rownr-- )
      {
         echo '<tr>';

         if( $this->coord_borders & LEFT )
            echo $coord_start_number . $rownr . $coord_alt . $rownr .$coord_end;

         if( $smooth_edge )
            echo '<td>' . $edge_vert . "l.gif\"></td>\n";


         $letter = 'a';
         $letter_c = 'a';
         for($colnr = 0; $colnr < $this->size; $colnr++ )
         {
            $stone = (int)@$this->array[$colnr][$this->size-$rownr];
            $empty = false;
            $marked = false;
            if( $stone & FLAG_NOCLICK ) {
               $stone &= ~FLAG_NOCLICK;
               $no_click = true;
            }
            else
               $no_click = false;

            if( $stone == BLACK )
            {
               $type = 'b';
               $alt = 'X';
            }
            else if( $stone == WHITE )
            {
               $type = 'w';
               $alt = 'O';
            }
            else if( $stone == BLACK_DEAD )
            {
               $type = 'bw';
               $alt = 'x';
               $marked = true;
            }
            else if( $stone == WHITE_DEAD )
            {
               $type = 'wb';
               $alt = 'o';
               $marked = true;
            }
            else
            {
               $empty = true;

               $type = 'e';
               $alt = '.';
               if( $rownr == 1 ) $type = 'd';
               else if( $rownr == $this->size ) $type = 'u';
               if( $colnr == 0 ) $type .= 'l';
               else if( $colnr == $this->size-1 ) $type .= 'r';

               if( $stone == BLACK_TERRITORY )
               {
                  $type .= 'b';
                  $alt = '+';
                  $marked = true;
               }
               else if( $stone == WHITE_TERRITORY )
               {
                  $type .= 'w';
                  $alt = '-';
                  $marked = true;
               }
               else if( $stone == DAME )
               {
                  $type .= 'd';
                  $alt = '.';
                  $marked = true;
               }
               else if( $stone == MARKED_DAME )
               {
                  $type .= 'g';
                  $alt = 's'; //for seki
                  $marked = true;
               }

               if( $type=='e' )
               {
                  if( is_hoshi($colnr, $this->size-$rownr, $this->size) )
                  {
                     $type = 'h';
                     $alt = ',';
                  }
               }
            }

            if( !$marked )
            {
               if( !$empty && ( $stone == BLACK or $stone == WHITE )
                   && $this->movemrkx == $colnr
                   && $this->movemrky == $this->size-$rownr )
               {
                  $type .= 'm';
                  $alt = ( $stone == BLACK ? '#' : '@' );
                  $marked = true;
               }
               elseif( is_array($this->marks) )
               {
                  $m = number2sgf_coords($colnr, $this->size-$rownr, $this->size);
                  if( $m && @$this->marks[$m] )
                  {
                     //$alt .= $this->marks[$m];
                     $type .= $this->marks[$m];
                     $marked = true;
                  }
               }
            }
            if( $this->coord_borders & OVER )
               $alt.= "\" title=\"$letter$rownr";

            if( $may_play and !$no_click and
                ( ($empty and $on_empty) or (!$empty and $on_not_empty) ) )
               echo "$move_start$letter_c$letter_r$move_alt$alt\" src=\"$stone_size/$type$move_end";
            else
               echo "$nomove_start$alt\" src=\"$stone_size/$type$nomove_end";

            $letter_c++;
            $letter++; if( $letter == 'i' ) $letter++;
         }

         if( $smooth_edge )
            echo '<td>' . $edge_vert . "r.gif\"></td>\n";

         if( $this->coord_borders & RIGHT )
            echo $coord_start_number . $rownr . $coord_alt . $rownr .$coord_end;

         echo "</tr>\n";
         $letter_r++;
      }

      if( $smooth_edge )
         $this->draw_edge_row( $edge_start.'d', $edge_coord,
                               $border_start, $border_imgs, $border_rem );

      if( $this->coord_borders & DOWN )
         $this->draw_coord_row( $coord_start_letter, $coord_alt, $coord_end,
                           $coord_left, $coord_right );

      echo "</table>\n";
   } //draw_board


   //$coord_borders and $movemsg stay local.
   function draw_ascii_board( $movemsg='', $coord_borders=15)
   {
      $out = "\n";

      if( $movemsg )
         $out .= wordwrap("Message: $movemsg", 47) . "\n\n";

      if( $coord_borders & UP )
      {
         $out .= '  ';
         if( $coord_borders & LEFT )
            $out .= '  ';

         $colnr = 1;
         $letter = 'a';
         while( $colnr <= $this->size )
         {
            $out .= " $letter";
            $colnr++;
            $letter++; if( $letter == 'i' ) $letter++;
         }
         $out .= "\n";
      }

      $letter_r = 'a';
      for($rownr = $this->size; $rownr > 0; $rownr-- )
      {
         $out .= '  ';
         if( $coord_borders & LEFT )
            $out .= str_pad($rownr, 2, ' ', STR_PAD_LEFT);

         $pre_mark = false;
         $letter_c = 'a';
         for($colnr = 0; $colnr < $this->size; $colnr++ )
         {
            $stone = (int)@$this->array[$colnr][$this->size-$rownr];
            $empty = false;
            if( $stone == BLACK )
            {
               $type = 'X';
            }
            else if( $stone == WHITE )
            {
               $type = 'O';
            }
            else if( $stone == BLACK_DEAD )
            {
               $type = 'x';
            }
            else if( $stone == WHITE_DEAD )
            {
               $type = 'o';
            }
            else
            {
               $empty = true;

               $type = '.';

               if( $stone == BLACK_TERRITORY )
                  $type .= '+';
               else if( $stone == WHITE_TERRITORY )
                  $type .= '-';
               else if( $stone == DAME )
                  $type .= '.';
               else if( $stone == MARKED_DAME )
                  $type .= 's'; //for seki

               if( $type=='.' )
               {
                  if( is_hoshi($colnr, $this->size-$rownr, $this->size) )
                     $type = ',';
               }
            }

            if( $pre_mark )
            {
               $out .= ")$type";
               $pre_mark = false;
            }
            else if( !$empty && ( $stone == BLACK or $stone == WHITE )
                   && $this->movemrkx == $colnr
                   && $this->movemrky == $this->size-$rownr )
            {
               $out .= "($type";
               $pre_mark = true;
            }
            else
            {
               $out .= " $type";
            }

            $letter_c++;
         }

         $out .= ( $pre_mark ? ')' : ' ' );

         if( $coord_borders & RIGHT )
            $out .= str_pad($rownr, 2, ' ', STR_PAD_RIGHT);

         $letter_r++;
         $out .= "\n";
      }

      if( $coord_borders & DOWN )
      {
         $out .= '  ';
         if( $coord_borders & LEFT )
            $out .= '  ';

         $colnr = 1;
         $letter = 'a';
         while( $colnr <= $this->size )
         {
            $out .= " $letter";
            $colnr++;
            $letter++; if( $letter == 'i' ) $letter++;
         }
         $out .= "\n";
      }

      return $out;
   } //draw_ascii_board


   function has_liberty_check( $x, $y, &$prisoners, $remove )
   {
      $c = @$this->array[$x][$y]; // Color of this stone

      $index = NULL;
      $index[$x][$y] = 7;

      while( true )
      {
         if( $index[$x][$y] >= 32 )  // Have looked in all directions
         {
            $m = $index[$x][$y] % 8;

            if( $m == 7 )   // At starting point, no liberties found
            {
               if( $remove )
               {
                  foreach( $index as $x => $sub )
                  {
                     foreach( $sub as $y => $val )
                     {
                        array_push($prisoners, array($x,$y));
                        unset($this->array[$x][$y]);
                     }
                  }
               }
               return false;
            }

            $x -= $this->dirx[$m];  // Go back
            $y -= $this->diry[$m];
         }
         else
         {
            $dir = (int)($index[$x][$y] / 8);
            $index[$x][$y] += 8;

            $nx = $x+$this->dirx[$dir];
            $ny = $y+$this->diry[$dir];

            $new_color = @$this->array[$nx][$ny];

            if( (!$new_color or $new_color == NONE ) and
                ($nx >= 0) and ($nx < $this->size) and
                ($ny >= 0) and ($ny < $this->size) )
               return true; // found liberty

            if( $new_color == $c and !@$index[$nx][$ny])
            {
               $x = $nx;  // Go to the neighbour
               $y = $ny;
               $index[$x][$y] = $dir;
            }
         }
      }
   } //has_liberty_check


   function check_prisoners( $colnr, $rownr, $col, &$prisoners )
   {
      $some = false;
      for($i=0; $i<4; $i++)
      {
         $x = $colnr+$this->dirx[$i];
         $y = $rownr+$this->diry[$i];

         if( @$this->array[$x][$y] == $col )
            if( !$this->has_liberty_check( $x, $y, $prisoners, true) )
               $some = true;
      }
      return $some;
   } //check_prisoners



   function mark_territory( $x, $y )
   {

      $c = -1;  // color of territory

      $index[$x][$y] = 7;
      $point_count= 1; //for the current point (theoricaly NONE)

      while( true )
      {
         if( $index[$x][$y] >= 32 )  // Have looked in all directions
         {
            $m = $index[$x][$y] % 8;

            if( $m == 7 )   // At starting point, all checked
            {
               if( $c == -1 )
                  $c = DAME ;
               else
                  $c|= OFFSET_TERRITORY ;

               if( $c==DAME || $point_count>MAX_SEKI_MARK)
                  $c|= FLAG_NOCLICK ;

               foreach( $index as $x => $sub )
               {
                  foreach( $sub as $y => $val )
                  {
                     //keep all marks unchanged and reversible
                     if( @$this->array[$x][$y] < MARKED_DAME )
                        $this->array[$x][$y] = $c;
                  }
               }
               return $point_count;
            }

            $x -= $this->dirx[$m];  // Go back
            $y -= $this->diry[$m];
         }
         else
         {
            $dir = (int)($index[$x][$y] / 8);
            $index[$x][$y] += 8;

            $nx = $x+$this->dirx[$dir];
            $ny = $y+$this->diry[$dir];

            if( ( $nx < 0 ) or ($nx >= $this->size) or ($ny < 0) or ($ny >= $this->size) or
                isset($index[$nx][$ny]) )
               continue;

            $new_color = @$this->array[$nx][$ny];

            if( !$new_color or $new_color == NONE or $new_color >= BLACK_DEAD )
            {
               $x = $nx;  // Go to the neighbour
               $y = $ny;
               $index[$x][$y] = $dir;
               $point_count++;
            }
            else //remains BLACK/WHITE/DAME/BLACK_TERRITORY/WHITE_TERRITORY and MARKED_DAME
            {
               if( $new_color == MARKED_DAME )
               {
                  $c = NONE; // This area will become dame
               }
               else if( $c == -1 )
               {
                  $c = $new_color;
               }
               else if( $c == (WHITE+BLACK-$new_color) )
               {
                  $c = NONE; // This area has both colors as boundary
               }
            }
         }
      }
   } //mark_territory

   function create_territories_and_score( )
   {
      // mark territories

      for( $x=0; $x<$this->size; $x++)
      {
         for( $y=0; $y<$this->size; $y++)
         {
            if( !@$this->array[$x][$y] or $this->array[$x][$y] == NONE )
            {
               $this->mark_territory( $x, $y);
            }
         }
      }

      // count

      $score = 0;

      for( $x=0; $x<$this->size; $x++)
      {
         for( $y=0; $y<$this->size; $y++)
         {
            switch( @$this->array[$x][$y] & ~FLAG_NOCLICK)
            {
               case BLACK_TERRITORY:
                  $score --;
               break;

               case WHITE_TERRITORY:
                  $score ++;
               break;

               case BLACK_DEAD:
                  $score += 2;
               break;

               case WHITE_DEAD:
                  $score -= 2;
               break;
            }
         }
      }

      return $score;
   } //create_territories_and_score


   function toggle_marked_area( $x, $y, &$marked, $companion_groups=true )
   {

      $c = @$this->array[$x][$y]; // Color of this stone

   /* Actually, $opposite_dead force an already marked dead neighbour group from the
      opposite color to reverse to not dead, but this does not work properly if
      $companion_groups is not true, as both groups may be not touching themself.
   */
      if( $companion_groups and ($c == BLACK or $c == WHITE) )
         $opposite_dead = WHITE+BLACK_DEAD-$c ;
      else
         $opposite_dead = -1 ;

      $index[$x][$y] = 7;

      while( true )
      {
         if( $index[$x][$y] >= 32 )  // Have looked in all directions
         {
            $m = $index[$x][$y] % 8;

            if( $m == 7 )   // At starting point, all checked
            {
               foreach( $index as $x => $sub )
               {
                  foreach( $sub as $y => $val )
                  {
                     if ($c == @$this->array[$x][$y]) {
                        array_push($marked, array($x,$y));
                        @$this->array[$x][$y] ^= OFFSET_MARKED;
                     }
                  }
               }
               return;
            }

            $x -= $this->dirx[$m];  // Go back
            $y -= $this->diry[$m];
         }
         else
         {
            $dir = (int)($index[$x][$y] / 8);
            $index[$x][$y] += 8;

            $nx = $x+$this->dirx[$dir];
            $ny = $y+$this->diry[$dir];

            if( ( $nx < 0 ) or ($nx >= $this->size) or ($ny < 0) or ($ny >= $this->size) or
               @$index[$nx][$ny] )
               continue;

            $new_color = @$this->array[$nx][$ny];

            if( $new_color == $c or ( $companion_groups and $new_color == NONE ) )
            {
               $x = $nx;  // Go to the neighbour
               $y = $ny;
               $index[$x][$y] = $dir;
            }
            else if( $new_color == $opposite_dead )
            {
               $this->toggle_marked_area( $nx, $ny, $marked, $companion_groups);
            }
         }
      }
   } //toggle_marked_area


   // If move update was interupted between thw mysql queries, there may
   // be extra entries in the Moves and MoveMessages tables.
   function fix_corrupted_move_table( $gid)
   {
      $result = mysql_query("SELECT Moves FROM Games WHERE ID=$gid");

      if( @mysql_num_rows($result) != 1 )
         error("mysql_query_failed",'board1');

      extract(mysql_fetch_assoc($result));


      $result = mysql_query("SELECT MAX(MoveNr) AS max_movenr FROM Moves WHERE gid=$gid");

      if( @mysql_num_rows($result) != 1 )
         error("mysql_query_failed",'board2');

      extract(mysql_fetch_assoc($result));


      if($max_movenr == $Moves)
         return;

      if($max_movenr != $Moves+1)
         error("mysql_data_corruption",'board2');    // Can't handle this type of problem

      mysql_query("DELETE FROM Moves WHERE gid=$gid AND MoveNr=$max_movenr");
      mysql_query("DELETE FROM MoveMessages WHERE gid=$gid AND MoveNr=$max_movenr");
   }


} //class Board
?>
