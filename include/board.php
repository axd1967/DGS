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

define("LEFT",1);
define("UP",2);
define("RIGHT",4);
define("DOWN",8);

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


// If move update was interupted between thw mysql queries, there may
// be extra entries in the Moves and MoveMessages tables.
function fix_corrupted_move_table($gid)
{
   $result = mysql_query("SELECT Moves FROM Games WHERE ID=$gid");

   if( mysql_num_rows($result) != 1 )
      error("mysql_query_failed");

   extract(mysql_fetch_array($result));


   $result = mysql_query("SELECT MAX(MoveNr) AS max_movenr FROM Moves WHERE gid=$gid");

   if( mysql_num_rows($result) != 1 )
      error("mysql_query_failed");

   extract(mysql_fetch_array($result));



   if($Moves == $max_movenr)
      return;

   if($max_movenr != $Moves+1)
      error("mysql_data_corruption");    // Can't handle this type of problem

   mysql_query("DELETE FROM Moves WHERE gid=$gid AND MoveNr=$max_movenr");
   mysql_query("DELETE FROM MoveMessages WHERE gid=$gid AND MoveNr=$max_movenr");
}



function draw_board($Size, &$array, $may_play, $gid,
$Last_X, $Last_Y, $stone_size, $font_size, $msg, $stonestring, $handi,
$coord_borders, $woodcolor  )
{
   $coord_width_array = array( 13 => 16, 17 => 21, 21 => 26, 25 => 31,
                               29 => 35, 35 => 43, 42 => 52, 50 => 62 );

   $mark_letter = 'm';
   $sizestringtype = 1;
   $use_border = true;

   if( !( $woodcolor >= 1 and $woodcolor <= 5) )
   {
      $woodcolor = 1;
      $coord_borders = 15;
   }

   if( !$stone_size ) $stone_size = 25;
   if( !$font_size ) $font_size = "+0";

   $coord_width=$coord_width_array[$stone_size];

   $board_begin = '<table border=0 cellpadding=0 cellspacing=0 ' .
      'align=center valign=center background="">';
   $board_end = "</table>\n";
   $row_start = '<tr>';
   $row_end = "</tr>\n";

   $coord_start_number = "<td><img class=c$stone_size src=$stone_size/c";
   $coord_start_letter = "<td><img class=s$stone_size src=$stone_size/c";
   $coord_alt = '.gif alt=';
   $coord_end = '></td>';
   $coord_empty = "<td><img class=c$stone_size alt=\" \" src=$stone_size/.gif></td>";


   $str1 = "<td><IMG class=s$stone_size alt=\"";
   $str4 = '.gif></A></td>';
   $str5 = '.gif></td>';

// Variables for the 3d-looking border
   $border_start = 140 - ( $coord_borders & LEFT ? $coord_width : 0 );
   $border_imgs = ceil( ($Size * $stone_size - $border_start) / 150 ) - 1;
   $border_rem = $Size * $stone_size - $border_start - 150 * $border_imgs;

   if( $may_play )
   {
      if( $handi or !$stonestring )
         $on_empty = true;

      if( $handi )
      {
         $str2 = "<td><A href=\"game.php?g=$gid&a=handicap&c=";
         $str3 = "&s=$stonestring\"><IMG class=s$stone_size border=0 alt=\"";
      }
      else if( $stonestring )
      {
         $str2 = "<td><A href=\"game.php?g=$gid&a=remove&c=";
         $str3 = "&s=$stonestring\"><IMG class=s$stone_size border=0 alt=\"";
      }
      else
      {
         $str2 = "<td><A href=\"game.php?g=$gid&a=move&c=";
         $str3 = "\"><IMG class=s$stone_size border=0 alt=\"";
      }
   }

   if( $msg )
      echo "<table border=2 cellpadding=3 align=center><tr>" .
         "<td width=\"" . $stone_size*19 . "\" align=left>$msg</td></tr></table><BR>\n";

   echo '<table border=0 cellpadding=0 cellspacing=0 background="images/wood' . $woodcolor . '.gif" align=center><tr><td valign=top>';

   echo $board_begin;

   if( $coord_borders & UP )
   {
      $span = ($coord_borders & LEFT ? 1 : 0 ) + ( $use_border ? 1 : 0 );
      $w = ($coord_borders & LEFT ? $coord_width : 0 ) + ( $use_border ? 10 : 0 );
      if( $span > 0 )
         echo "<td colspan=$span background=\"images/blank.gif\"><img src=\"images/blank.gif\" width=$w height=$stone_size></td>";

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

      $span = ($coord_borders & RIGHT ? 1 : 0 ) + ( $use_border ? 1 : 0 );
      $w = ($coord_borders & RIGHT ? $coord_width : 0 ) + ( $use_border ? 10 : 0 );
      if( $span > 0 )
         echo "<td colspan=$span background=\"images/blank.gif\"><img src=\"images/blank.gif\" width=$w height=$stone_size></td></tr>";
   }

   if( $use_border )
   {
      echo '<tr>';

      if( $coord_borders & LEFT )
         echo "<td><img src=\"images/blank.gif\" width=$coord_width height=10></td>";

      echo '<td><img src="images/wood' . $woodcolor .
         '_ul.gif" height=10 width=10></td>' . "\n";

      echo "<td colspan=$Size width=" . $Size*$stone_size . '>';
      echo '<img src="images/wood' . $woodcolor . '_u.gif" height=10 width=' .
         $border_start . '>';
      for($i=0; $i<$border_imgs; $i++ )
         echo '<img src="images/wood' . $woodcolor . '_u.gif" height=10 width=150>';

      echo '<img src="images/wood' . $woodcolor . '_u.gif" height=10 width=' .
         $border_rem . '>';

      echo "</td>\n" . '<td><img src="images/wood' . $woodcolor .
         '_ur.gif" height=10 width=10></td>' . "\n";

      if( $coord_borders & RIGHT )
         echo "<td><img src=\"images/blank.gif\" width=$coord_width height=10></td>";

      echo "</tr>\n";
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
         echo $coord_start_number . $rownr . $coord_alt . $rownr .$coord_end;

      if( $use_border )
         echo '<td><img src="images/wood' . $woodcolor . '_l.gif" height=' .
            $stone_size . ' width=10></td>';


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

         if( !$empty and $colnr == $Last_X and $rownr == $Size - $Last_Y
             and ( $stone == BLACK or $stone == WHITE ) )
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

      if( $use_border )
         echo '<td><img src="images/wood' . $woodcolor . '_r.gif" height=' .
            $stone_size . ' width=10></td>';

      if( $coord_borders & RIGHT )
         echo $coord_start_number . $rownr . $coord_alt . $rownr .$coord_end;

      $letter_r++;
      echo $row_end;
   }

   if( $use_border )
   {
      echo '<tr>';

      if( $coord_borders & LEFT )
         echo "<td><img src=\"images/blank.gif\" width=$coord_width height=10></td>";

      echo '<td><img src="images/wood' . $woodcolor .
         '_dl.gif" height=10 width=10></td>' . "\n";

      echo "<td colspan=$Size width=" . $Size*$stone_size . '>';
      echo '<img src="images/wood' . $woodcolor . '_d.gif" height=10 width=' .
         $border_start . '>';
      for($i=0; $i<$border_imgs; $i++ )
         echo '<img src="images/wood' . $woodcolor . '_d.gif" height=10 width=150>';

      echo '<img src="images/wood' . $woodcolor . '_d.gif" height=10 width=' .
         $border_rem . '>';

      echo "</td>\n" . '<td><img src="images/wood' . $woodcolor .
         '_dr.gif" height=10 width=10></td>' . "\n";

      if( $coord_borders & RIGHT )
         echo "<td><img src=\"images/blank.gif\" width=$coord_width height=10></td>";

      echo "</tr>\n";
   }

   if( $coord_borders & DOWN )
   {
      echo $row_start;

      $span = ($coord_borders & LEFT ? 1 : 0 ) + ( $use_border ? 1 : 0 );
      $w = ($coord_borders & LEFT ? $coord_width : 0 ) + ( $use_border ? 10 : 0 );
      if( $span > 0 )
         echo "<td colspan=$span background=\"images/blank.gif\"><img src=\"images/blank.gif\" width=$w height=$stone_size></td>";

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

      $span = ($coord_borders & RIGHT ? 1 : 0 ) + ( $use_border ? 1 : 0 );
      $w = ($coord_borders & RIGHT ? $coord_width : 0 ) + ( $use_border ? 10 : 0 );
      if( $span > 0 )
         echo "<td colspan=$span background=\"images/blank.gif\"><img src=\"images/blank.gif\" width=$w height=$stone_size align=center></td>";
   }

   echo $board_end;

   echo '</td></tr></table>
';
}


// fills $array with positions where the stones are.
// returns the coords of the last move
function make_array( $gid, &$array, &$msg, $max_moves, $move, &$result, &$marked_dead,
$no_marked_dead = false )
{
   $array=NULL;

   if( !$move ) $move = $max_moves;

   $result = mysql_query( "SELECT * FROM Moves WHERE gid=$gid order by ID" );

   $removed_dead = FALSE;
   $marked_dead = array();

   while( $row = mysql_fetch_array($result) )
   {

      if( $row["MoveNr"] > $move )
      {
         if( $row["MoveNr"] > $max_moves )
            fix_corrupted_move_table($gid);
         break;
      }

      extract($row);

      if( $Stone <= WHITE )
      {
         if( $PosX < 0 ) continue;

         $array[$PosX][$PosY] = $Stone;

         $removed_dead = FALSE;
      }
      else if( $Stone >= BLACK_DEAD )
      {
         if( $removed_dead == FALSE )
         {
            $marked_dead = array(); // restart removal
            $removed_dead = TRUE;
         }
         array_push($marked_dead, array($PosX,$PosY));
         $PosX = $PosY = NULL; // don't use as lastx/lasty
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

   $result2 = mysql_query( "SELECT Text FROM MoveMessages WHERE gid=$gid AND MoveNr=$move" );

   if( mysql_num_rows($result2) == 1 )
   {
      $row = mysql_fetch_array($result2);
      $msg = $row["Text"];
   }

   return array($PosX,$PosY);
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

   $result = mysql_query( "SELECT * FROM Moves WHERE gid=$gid order by ID" );

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

      $coord = number2sgf_coords($PosX,$PosY,$Size);

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

   $handi = ($Handicap < 2 ? 1 : $Handicap );
   $black_to_move = (($Moves < $handi) or ($Moves-$handi)%2 == 1 );
   if( $Status!='FINISHED' and
       (($black_to_move and $ToMove_ID!=$Black_ID) or
        (!$black_to_move and $ToMove_ID!=$White_ID )) )
   {
      echo "Wrong Player to move!<br>\n";
      return false;
   }

   echo "Ok<br>\n";
}

function draw_ascii_board($Size, &$array, $gid, $Last_X, $Last_Y,  $coord_borders, $msg )
{
   $out = "\n";

   if( $msg )
      $out .= wordwrap("Message: $msg", 47) . "\n\n";

   if( $coord_borders & UP )
   {
      $out .= '  ';
      if( $coord_borders & LEFT )
         $out .= '  ';

      $colnr = 1;
      $letter = 'a';
      while( $colnr <= $Size )
      {
         $out .= " $letter";
         $colnr++;
         $letter++;
         if( $letter == 'i' ) $letter++;
      }
      $out .= "\n";
   }

   if( $Size > 11 ) $hoshi_dist = 4; else $hoshi_dist = 3;

   // 4 == center, 5 == side, 6 == corner
   if( $Size >=5 ) $hoshi_1 = 4; else $hoshi_1 = 7;
   if( $Size >=8 ) $hoshi_2 = 6; else $hoshi_2 = 7;
   if( $Size >=13) $hoshi_3 = 5; else $hoshi_3 = 7;

   $letter_r = 'a';

   for($rownr = $Size; $rownr > 0; $rownr-- )
   {
      $out .= '  ';
      if( $coord_borders & LEFT )
         $out .= str_pad($rownr, 2, ' ', STR_PAD_LEFT);

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
            $type = '.';

            if( $hoshi_r > 0 )
            {
               $hoshi_c = 0;
               if( $colnr == $hoshi_dist -  1 or $colnr == $Size - $hoshi_dist )
                  $hoshi_c = 3;

               if( $colnr == $Size - $colnr - 1 ) $hoshi_c = 2;

               if( $hoshi_c + $hoshi_r == $hoshi_1 or
               $hoshi_c + $hoshi_r == $hoshi_2 or
               $hoshi_c + $hoshi_r == $hoshi_3 )
               {
                  $type = ',';
               }
            }

            if( $stone == BLACK_TERRITORY )
               $type .= '+';
            else if( $stone == WHITE_TERRITORY )
               $type .= '-';
            else if( $stone == DAME )
               $type .= '.';

            $empty = true;
         }

         if( $pre_mark )
         {
            $out .= ")$type";
            $pre_mark = false;
         }
         else if( !$empty and $colnr == $Last_X and $rownr == $Size - $Last_Y )
         {
            $out .= "($type";
            $pre_mark = true;
         }
         else
         {
            $out .= " $type";
         }

         $letter_c ++;
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
      while( $colnr <= $Size )
      {
         $out .= " $letter";
         $colnr++;
         $letter++;
         if( $letter == 'i' ) $letter++;
      }
      $out .= "\n";
   }

   return $out;
}
?>
