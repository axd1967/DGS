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

define("LEFT",1);
define("UP",2);
define("RIGHT",4);
define("DOWN",8);

function board2sgf_coords($x, $y, $Size)
{
   if( !($x<$Size and $y<$Size and $x>=0 and $y>=0) )
      return NULL;

   return chr(ord('a')+$x) . chr(ord('a')+$y);
}

function sgf2board_coords($coord, $Size)
{
   if( !is_string($coord) or strlen($coord)!=2 )
      return array(NULL,NULL);
      
   $x = ord($coord[0])-ord('a');
   $y = ord($coord[1])-ord('a');

   if( !($x<$Size and $y<$Size and $x>=0 and $y>=0) )
      return array(NULL,NULL);
   
   return array(ord($coord[0])-ord('a'), ord($coord[1])-ord('a'));
}


function draw_board($Size, &$array, $may_play, $gid, 
$Last_X, $Last_Y, $stone_size, $font_size, $msg, $stonestring, $handi, 
$board_type, $coord_borders, $woodcolor  )
{
   $mark_letter = 'm';
   $board_type = 3;
   $sizestringtype = 1;
   
   if( !( $woodcolor >= 1 and $woodcolor <= 5) ) 
   {
      $woodcolor = 1;
      $coord_borders = 15;
      $board_type = 3;
   }


   $use_second_table = ( $board_type != 2 );

   $use_gif_coords = ( $board_type != 1 );


   if( !$stone_size ) $stone_size = 25;
   if( !$font_size ) $font_size = "+0";

   if($use_second_table)
   {
      $board_begin = '<table border=0 cellpadding=0 cellspacing=0 ' .
          'align=center valign=center background="">';
      $board_end = "</table>\n";
      $row_start = '<tr>';
      $row_end = "</tr>\n";
      $table_start = "<td>";
      $table_start_c = "<td align=center>";
      $table_end = "</td>\n";
   }
   else
   {
      $board_begin = $board_end = $row_start = "";
      $row_end = "<br\n>";
      $table_start = $table_start_c = $table_end = "";
   }

   if( $sizestringtype == 2 )
   {
      $size_str = "width=$stone_size heigth=$stone_size";
      $size_str2 = "width=" . floor($stone_size * 31/25) . " heigth=$stone_size";
   }
   else if( $sizestringtype == 1 )
   {
      $size_str = "class=s$stone_size";
      $size_str2 = "class=c$stone_size";      
   }
   if( $use_gif_coords )
   {
      $coord_start_number = $table_start . "<img $size_str2 src=$stone_size/c";
      $coord_start_letter = $table_start . "<img $size_str src=$stone_size/c";
      $coord_alt = '.gif alt=';
      $coord_end = ">" . $table_end;
      $coord_empty = $table_start . "<img $size_str2 alt=\" \" src=$stone_size/.gif>" . $table_end;
   }
   else
   {
      $coord_start_letter = $coord_start_number = $table_start_c . 
          ( $font_size != "+0"  ? "<font size=\"$font_size\">" :"");
      $coord_start = $table_start_c . ( $font_size != "+0"  ? "<font size=\"$font_size\">" :"");
      $coord_end = ( $font_size != "+0"  ? "</font>" : "") . $table_end;
      $coord_empty = "<td>&nbsp;</td>";
   }


   $str1 = "$table_start<IMG $size_str alt=\"";
   $str4 = ".gif></A>". $table_end;
   $str5 = ".gif>" . $table_end;
   if( $may_play )
   {
      if( $handi or !$stonestring )
         $on_empty = true;

      if( $handi )
      {
         $str2 = "$table_start<A href=\"game.php?g=$gid&a=handicap&c=";
         $str3 = "&s=$stonestring\"><IMG $size_str border=0 alt=\"";
      }
      else if( $stonestring )
      {
         $str2 = "$table_start<A href=\"game.php?g=$gid&a=remove&c=";
         $str3 = "&s=$stonestring\"><IMG $size_str border=0 alt=\"";
      }
      else
      {
         $str2 = "$table_start<A href=\"game.php?g=$gid&a=move&c=";
         $str3 = "\"><IMG $size_str border=0 alt=\"";
      }
   }
    
   if( $msg )
      echo "<table border=2 cellpadding=3 align=center><tr>" . 
         "<td width=\"" . $stone_size*19 . "\" align=left>$msg</td></tr></table><BR>\n";

   echo '<table border=0 cellpadding=0 cellspacing=0 background="images/wood' . $woodcolor . '.gif" align=center><tr><td valign=top>';
   
   echo $board_begin;

   if( $coord_borders & UP )
   {
      if( $coord_borders & LEFT )
         echo $coord_empty;
      $colnr = 1;
      $letter = 'a';
      while( $colnr <= $Size )
      {
         echo $coord_start_letter . $letter . $coord_alt;
         if( $use_gif_coords )
            echo $letter;
         echo $coord_end;
         $colnr++;
         $letter++;
         if( $letter == 'i' ) $letter++;
      }  
      if( $coord_borders & RIGHT )
         echo $coord_empty . $row_end;
   }

   if( $Size > 11 ) $hoshi_dist = 4; else $hoshi_dist = 3;

   // 4 == center, 5 == side, 6 == corner
   if( $Size >=5 ) $hoshi_1 = 4; else $hoshi_1 = 7;
   if( $Size >=8 ) $hoshi_2 = 6; else $hoshi_2 = 7;
   if( $Size >=13) $hoshi_3 = 5; else $hoshi_3 = 7;

   $letter_r = 'a';

   for($rownr = $Size; $rownr > 0; $rownr-- )
   {
      echo $row_start;
      if( $coord_borders & LEFT )
      {
         echo $coord_start_number . $rownr . $coord_alt;
         if( $use_gif_coords )
            echo  $rownr;
         
         echo $coord_end;
      }
            
      $hoshi_r = 0;
      if( $rownr == $hoshi_dist  or $rownr == $Size - $hoshi_dist + 1 ) $hoshi_r = 3;
      if( $rownr == $Size - $rownr + 1 ) $hoshi_r = 2;
            
      $letter_c = 'a';
      for($colnr = 0; $colnr < $Size; $colnr++ )
      {
         $stone = $array[$colnr][$Size-$rownr];
         $empty = false;
         if( $stone == BLACK )
         {
            $type = 'b';
            $alt = '#';
         }
         else if( $stone == WHITE )
         {
            $type = 'w';
            $alt = 'O';
         }
         else if( $stone == BLACK_DEAD )
         {
            $type = 'bw';
            $alt = '/';
         }
         else if( $stone == WHITE_DEAD )
         {
            $type = 'wb';
            $alt = '-';
         }
         else
         {
            $type = 'e';
            $alt = '.';
            if( $rownr == 1 ) $type = 'd';
            if( $rownr == $Size ) $type = 'u';
            if( $colnr == 0 ) $type .= 'l';
            if( $colnr == $Size-1 ) $type .= 'r';
                    
            if( $hoshi_r > 0 and $type=='e' )
            {
               $hoshi_c = 0;
               if( $colnr == $hoshi_dist -  1 or $colnr == $Size - $hoshi_dist ) 
                  $hoshi_c = 3;

               if( $colnr == $Size - $colnr - 1 ) $hoshi_c = 2;
                            
               if( $hoshi_c + $hoshi_r == $hoshi_1 or 
               $hoshi_c + $hoshi_r == $hoshi_2 or
               $hoshi_c + $hoshi_r == $hoshi_3 )
               {
                  $type = 'h';
                  $alt = ',';
               }
            }

            if( $stone == BLACK_TERRITORY )
               $type .= 'b';
            else if( $stone == WHITE_TERRITORY )
               $type .= 'w';
            else if( $stone == DAME )
               $type .= 'd';

            $empty = true;

                            
         }

         if( !$empty and $colnr == $Last_X and $rownr == $Size - $Last_Y )
         {
            $type .= $mark_letter;
            $alt = ( $alt == '#' ? 'X' : '@' );
         }
                    
         if( $may_play and ( $empty xor !$on_empty ) )
            echo "$str2$letter_c$letter_r$str3$alt\" SRC=$stone_size/$type$str4";
         else
            echo "$str1$alt\" SRC=$stone_size/$type$str5";

         $letter_c ++;
      }

      if( $coord_borders & RIGHT )
      {
         echo $coord_start_number . $rownr . $coord_alt;
         if( $use_gif_coords )
            echo  $rownr;
         
         echo $coord_end;
      }
      $letter_r++;
      echo $row_end;
   }

   if( $coord_borders & DOWN )
   {
      echo $row_start;
      if( $coord_borders & LEFT )
         echo $coord_empty;
      $colnr = 1;
      $letter = 'a';
      while( $colnr <= $Size )
      {
         echo $coord_start_letter . $letter . $coord_alt;
         if( $use_gif_coords )
            echo $letter;
         echo $coord_end;

         $colnr++;
         $letter++;
         if( $letter == 'i' ) $letter++;
      }  
      if( $coord_borders & RIGHT )
         echo $coord_empty . $row_end;
   }

   echo $board_end;

   echo '</td></tr></table>
';
}


// fills $array with positions where the stones are.
// returns who is next to move
function make_array( $gid, &$array, &$msg, $max_moves, $move, &$result, &$marked_dead, 
$no_marked_dead = false )
{
   $array=NULL;
  
   if( !$move ) $move = $max_moves;

   $result = mysql_query( "SELECT * FROM Moves$gid order by ID" );

   $removed_dead = FALSE;
   $marked_dead = array();

   while( $row = mysql_fetch_array($result) )
   {
      if( $row["MoveNr"] > $move ) 
         break;

      $x = $row["PosX"];
      $y = $row["PosY"];

      if( $row["Stone"] <= WHITE )
      {
         if( $row["MoveNr"] == $move and $row["Stone"] > 0 )
            $msg = $row["Text"];

         if( $row["PosX" ] < 0 ) continue;

         $array[$x][$y] = $row["Stone"];
            
         $removed_dead = FALSE;
      }
      else if( $row["Stone"] >= BLACK_DEAD )
      {
         if( $removed_dead == FALSE )
         {
            $marked_dead = array(); // restart removal
            $removed_dead = TRUE;
         }
         array_push($marked_dead, array($x,$y));
      } 
   }

   if( !$no_marked_dead and $removed_dead == TRUE )
   {
      while( $sub = each($marked_dead) )
      {
         list($dummy, list($X, $Y)) = $sub;
         if( $array[$X][$Y] >= BLACK_DEAD )
            $array[$X][$Y] -= 6;
         else
            $array[$X][$Y] += 6;
      }
   }
    
   return array($x,$y);
}

$dirx = array( -1,0,1,0 );
$diry = array( 0,-1,0,1 );


function has_liberty_check( $x, $y, $Size, &$array, &$prisoners, $remove )
{
   global $dirx,$diry;
    
   $c = $array[$x][$y]; // Color of this stone

   $index=NULL;
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
               while( list($x, $sub) = each($index) )
               {
                  while( list($y, $val) = each($sub) )
                  {
                     array_push($prisoners, array($x,$y));
                     unset($array[$x][$y]);
                  }
               }
            }
            return false;
         }

         $x -= $dirx[$m];  // Go back
         $y -= $diry[$m];
      }
      else
      {
         $dir = (int)($index[$x][$y] / 8);
         $index[$x][$y] += 8;

         $nx = $x+$dirx[$dir];
         $ny = $y+$diry[$dir];

         $new_color = $array[$nx][$ny];

         if( (!$new_color or $new_color == NONE ) and 
             ( $nx >= 0 ) and ($nx < $Size) and ($ny >= 0) and ($ny < $Size) )
            return true; // found liberty
                    
         if( $new_color == $c and !$index[$nx][$ny])
         {
            $x = $nx;  // Go to the neigbour
            $y = $ny; 
            $index[$x][$y] = $dir;
         }
      }
   }
}



function check_prisoners($colnr,$rownr, $col, $Size, &$array, &$prisoners )
{
   global $dirx,$diry;

   //    echo $col . "<p>";

   for($i=0; $i<4; $i++)
   {
      $x = $colnr+$dirx[$i];
      $y = $rownr+$diry[$i];
      //            echo "x: $x<p>";
      //            echo "y: $y<p>";
      //            echo "color: " . $array[$x][$y] . "<p>"; 
      if( $array[$x][$y] == $col )
         has_liberty_check($x,$y, $Size, $array, $prisoners, true);
   }

}



function mark_territory( $x, $y, $size, &$array )
{
   global $dirx,$diry;

   $c = -1;  // color of territory
    
   $index[$x][$y] = 7;


   while( true )
   {
      if( $index[$x][$y] >= 32 )  // Have looked in all directions
      {
         $m = $index[$x][$y] % 8;

         if( $m == 7 )   // At starting point, all checked
         {
            while( list($x, $sub) = each($index) )
            {
               while( list($y, $val) = each($sub) )
               {
                  if( $array[$x][$y] < BLACK_DEAD )
                     $array[$x][$y] = $c + 3;
               }
            }

            return true;
         }

         $x -= $dirx[$m];  // Go back
         $y -= $diry[$m];
      }
      else
      {
         $dir = (int)($index[$x][$y] / 8);
         $index[$x][$y] += 8;

         $nx = $x+$dirx[$dir];
         $ny = $y+$diry[$dir];

         if( ( $nx < 0 ) or ($nx >= $size) or ($ny < 0) or ($ny >= $size) or 
             isset($index[$nx][$ny]) )
            continue;


         $new_color = $array[$nx][$ny];

         if( !$new_color or $new_color == NONE or $new_color >= BLACK_DEAD )
         {
            $x = $nx;  // Go to the neigbour
            $y = $ny; 
            $index[$x][$y] = $dir;
         }
         else
         {
            if( $c == -1 )
            {
               $c = $new_color;
            }
            else if( $c == (3-$new_color) )
            {
               $c = NONE; // This area has both colors as boundary
            }
         }
      }
   }
}

function create_territories_and_score( $size, &$array )
{
   // mark territories

   for( $x=0; $x<$size; $x++)
   {
      for( $y=0; $y<$size; $y++)
      {
         if( !$array[$x][$y] or $array[$x][$y] == NONE )
         {
            mark_territory( $x, $y, $size, $array );
         }
      }
   }

   // count

   $score = 0;

   for( $x=0; $x<$size; $x++)
   {
      for( $y=0; $y<$size; $y++)
      {
         switch( $array[$x][$y] )
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
}



function remove_dead( $x, $y, &$array, &$prisoners )
{
   global $dirx,$diry;
    
   $c = $array[$x][$y]; // Color of this stone
    
   $index[$x][$y] = 7;


   while( true )
   {
      if( $index[$x][$y] >= 32 )  // Have looked in all directions
      {
         $m = $index[$x][$y] % 8;

         if( $m == 7 )   // At starting point, all checked
         {
            while( list($x, $sub) = each($index) )
            {
               while( list($y, $val) = each($sub) )
               {
                  array_push($prisoners, array($x,$y));
                  if( $array[$x][$y] < 7 )
                     $array[$x][$y] += 6;
                  else
                     $array[$x][$y] -= 6;
               }
            }

            return;
         }

         $x -= $dirx[$m];  // Go back
         $y -= $diry[$m];
      }
      else
      {
         $dir = (int)($index[$x][$y] / 8);
         $index[$x][$y] += 8;

         $nx = $x+$dirx[$dir];
         $ny = $y+$diry[$dir];

         $new_color = $array[$nx][$ny];

         if( $new_color == $c and !$index[$nx][$ny])
         {
            $x = $nx;  // Go to the neigbour
            $y = $ny; 
            $index[$x][$y] = $dir;
         }
      }
   }
}

function check_consistency($gid)
{
   global $coord, $Size, $array, $to_move, $flags, $Last_X, $Last_Y, 
      $Black_Prisoners, $White_Prisoners, $nr_prisoners;

   echo "Game $gid: ";
   $result = mysql_query("SELECT * from Games where ID=$gid");
   if( mysql_num_rows($result) != 1 )
   {
      echo "Doesn't exist?<br>\n";
      return false;
   }

   extract( mysql_fetch_array( $result ) );

   $result = mysql_query( "SELECT * FROM Moves$gid order by ID" );
   
   $move_nr=0;
   $array = NULL;
   $games_Black_Prisoners = $Black_Prisoners;
   $games_White_Prisoners = $White_Prisoners;
   $Black_Prisoners=$White_Prisoners=0;
   $moves_Black_Prisoners=$moves_White_Prisoners=0;
   while( $row = mysql_fetch_array($result) )
   {
      extract($row);
      if( !($Stone == WHITE or $Stone == BLACK ) or $PosX<0 )
      {
         if( $Stone == NONE )
            $nr_prisoners++;
         elseif( $PosX < 0 )
            $move_nr++;

         continue;
      }
      $move_nr++;
      $to_move=$Stone;
      if( $to_move == BLACK )
         $moves_Black_Prisoners += $nr_prisoners;
      else
         $moves_White_Prisoners += $nr_prisoners;

      $coord = board2sgf_coords($PosX,$PosY);

      if( !check_move(false) )
      {
         echo ", problem at move $move_nr<br>\n";
         return false;
      }
      
      $Last_X=$PosX;
      $Last_Y=$PosY;
      $nr_prisoners=0;
   }

   if( $Moves != $move_nr )
   {
      echo "Wrong number of moves!<br>\n";
      return false;
   }

   if( $Black_Prisoners != $games_Black_Prisoners or 
   $White_Prisoners != $games_White_Prisoners )
   {
      echo "Wrong number of prisoners in Games table!<br>\n";
      echo "Black: $games_Black_Prisoners should be:$Black_Prisoners<br>\n";
      echo "White: $games_White_Prisoners should be:$White_Prisoners<br>\n";
      return false;
      
   }

   if( $Black_Prisoners != $moves_Black_Prisoners or 
   $White_Prisoners != $moves_White_Prisoners )
   {
      echo "Wrong number of prisoners removed!<br>\n";
      return false;
      
   }


   echo "Ok<br>\n";
}


?>
