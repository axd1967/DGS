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


function draw_board($Size, &$array, $may_play, $gid, $Last_X, $Last_Y, $stone_size,
                    $msg, $stonestring, $handi, $coord_borders, $woodcolor  )
{
   global $woodbgcolors;

   if( !($woodcolor >= 1 and $woodcolor <= 5 or $woodcolor >= 11 and $woodcolor <= 15) )
      $woodcolor = 1;

   if( !( $coord_borders >= 0 and $coord_borders <= 31) )
      $coord_borders = 31;

   $smooth_edge = ( ($coord_borders & SMOOTH_EDGE) and ($woodcolor < 10) );

   if( !$stone_size ) $stone_size = 25;

   $coord_width=floor($stone_size*31/25);

   $coord_start_number = "<td><img class=c$stone_size src=$stone_size/c";
   $coord_start_letter = "<td><img class=s$stone_size src=$stone_size/c";
   $coord_alt = '.gif alt=';
   $coord_end = '></td>';


   $str1 = "<td><IMG class=s$stone_size alt=\"";
   $str4 = '.gif></A></td>';
   $str5 = '.gif></td>';

// Variables for the 3d-looking border
   $border_start = 140 - ( $coord_borders & LEFT ? $coord_width : 0 );
   $border_imgs = ceil( ($Size * $stone_size - $border_start) / 150 ) - 1;
   $border_rem = $Size * $stone_size - $border_start - 150 * $border_imgs;
   if( $border_imgs < 0 )
      $border_rem = $Size * $stone_size;

   if( $may_play )
   {
      if( $handi or !$stonestring )
      {
         $on_not_empty = false;
         $on_empty = true;
      }
      else
      {
         $on_not_empty = true;
         if( MAX_SEKI_MARK>0 and $stonestring )
            $on_empty = true;
      }

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

   $woodstring = ( $woodcolor > 10
                   ? 'bgcolor=' . $woodbgcolors[$woodcolor - 10]
                   : 'background="images/wood' . $woodcolor . '.gif"');

   echo '<table border=0 cellpadding=0 cellspacing=0 ' . $woodstring .
      ' align=center><tr><td valign=top>';

   echo '<table border=0 cellpadding=0 cellspacing=0 ' .
      'align=center background="">'; //no valign for TABLE, if needed use TBODY

   if( $coord_borders & UP )
   {
      echo '<tr>';

      $span = ($coord_borders & LEFT ? 1 : 0 ) + ( $smooth_edge ? 1 : 0 );
      $w = ($coord_borders & LEFT ? $coord_width : 0 ) + ( $smooth_edge ? 10 : 0 );
      if( $span > 0 )
         echo "<td colspan=$span background=\"images/blank.gif\"><img src=\"images/blank.gif\" alt=\"  \" width=$w height=$stone_size></td>";

      $colnr = 1;
      $letter = 'a';
      while( $colnr <= $Size )
      {
         echo $coord_start_letter . $letter . $coord_alt . $letter . $coord_end;
         $colnr++;
         $letter++;
         if( $letter == 'i' ) $letter++;
      }

      $span = ($coord_borders & RIGHT ? 1 : 0 ) + ( $smooth_edge ? 1 : 0 );
      $w = ($coord_borders & RIGHT ? $coord_width : 0 ) + ( $smooth_edge ? 10 : 0 );
      if( $span > 0 )
         echo "<td colspan=$span background=\"images/blank.gif\"><img src=\"images/blank.gif\" alt=\"  \" width=$w height=$stone_size></td>";

      echo "</tr>\n";
   }

   if( $smooth_edge )
   {
      echo '<tr>';

      if( $coord_borders & LEFT )
         echo "<td><img src=\"images/blank.gif\" alt=\"  \" width=$coord_width height=10></td>";

      echo '<td><img src="images/wood' . $woodcolor .
         '_ul.gif" alt=" " height=10 width=10></td>' . "\n";

      echo "<td colspan=$Size width=" . $Size*$stone_size . '>';
      if( $border_imgs >= 0 )
         echo '<img src="images/wood' . $woodcolor . '_u.gif" alt=" " height=10 width=' .
            $border_start . '>';
      for($i=0; $i<$border_imgs; $i++ )
         echo '<img src="images/wood' . $woodcolor . '_u.gif" alt=" " height=10 width=150>';

      echo '<img src="images/wood' . $woodcolor . '_u.gif" alt=" " height=10 width=' .
         $border_rem . '>';

      echo "</td>\n" . '<td><img src="images/wood' . $woodcolor .
         '_ur.gif" alt=" " height=10 width=10></td>' . "\n";

      if( $coord_borders & RIGHT )
         echo "<td><img src=\"images/blank.gif\" alt=\"  \" width=$coord_width height=10></td>";

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
      echo '<tr>';

      if( $coord_borders & LEFT )
         echo $coord_start_number . $rownr . $coord_alt . $rownr .$coord_end;

      if( $smooth_edge )
         echo '<td><img src="images/wood' . $woodcolor . '_l.gif" alt=" " height=' .
            $stone_size . ' width=10></td>';


      $hoshi_r = 0;
      if( $rownr == $hoshi_dist  or $rownr == $Size - $hoshi_dist + 1 ) $hoshi_r = 3;
      if( $rownr == $Size - $rownr + 1 ) $hoshi_r = 2;

      $letter_c = 'a';
      for($colnr = 0; $colnr < $Size; $colnr++ )
      {
         $stone = (int)@$array[$colnr][$Size-$rownr];
         $empty = false;
         if( $stone & FLAG_NOCLICK ) {
            $stone &= ~FLAG_NOCLICK;
            $no_click = true;
         }
         else
            $no_click=false;

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
         }
         else if( $stone == WHITE_DEAD )
         {
            $type = 'wb';
            $alt = 'o';
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
            {
               $type .= 'b';
               $alt = '+';
            }
            else if( $stone == WHITE_TERRITORY )
            {
               $type .= 'w';
               $alt = '-';
            }
            else if( $stone == DAME )
            {
               $type .= 'd';
               $alt = '.';
            }
            else if( $stone == MARKED_DAME )
            {
               $type .= 'g';
               $alt = 's';
            }

            $empty = true;


         }

         if( !$empty and $colnr == $Last_X and $rownr == $Size - $Last_Y
             and ( $stone == BLACK or $stone == WHITE ) )
         {
            $type .= 'm';
            $alt = ( $stone == BLACK ? '#' : '@' );
         }

         if( $may_play and !$no_click and
             ( ($empty and $on_empty) or (!$empty and $on_not_empty) ) )
            echo "$str2$letter_c$letter_r$str3$alt\" SRC=$stone_size/$type$str4";
         else
            echo "$str1$alt\" SRC=$stone_size/$type$str5";

         $letter_c ++;
      }

      if( $smooth_edge )
         echo '<td><img src="images/wood' . $woodcolor . '_r.gif" alt=" " height=' .
            $stone_size . ' width=10></td>';

      if( $coord_borders & RIGHT )
         echo $coord_start_number . $rownr . $coord_alt . $rownr .$coord_end;

      echo "</tr>\n";
      $letter_r++;
   }

   if( $smooth_edge )
   {
      echo '<tr>';

      if( $coord_borders & LEFT )
         echo "<td><img src=\"images/blank.gif\" alt=\"  \" width=$coord_width height=10></td>";

      echo '<td><img src="images/wood' . $woodcolor .
         '_dl.gif" alt=" " height=10 width=10></td>' . "\n";

      echo "<td colspan=$Size width=" . $Size*$stone_size . '>';
      if( $border_imgs >= 0 )
         echo '<img src="images/wood' . $woodcolor . '_d.gif" alt=" " height=10 width=' .
            $border_start . '>';
      for($i=0; $i<$border_imgs; $i++ )
         echo '<img src="images/wood' . $woodcolor . '_d.gif" alt=" " height=10 width=150>';

      echo '<img src="images/wood' . $woodcolor . '_d.gif" alt=" " height=10 width=' .
         $border_rem . '>';

      echo "</td>\n" . '<td><img src="images/wood' . $woodcolor .
         '_dr.gif" alt=" " height=10 width=10></td>' . "\n";

      if( $coord_borders & RIGHT )
         echo "<td><img src=\"images/blank.gif\" alt=\"  \" width=$coord_width height=10></td>";

      echo "</tr>\n";
   }

   if( $coord_borders & DOWN )
   {
      echo '<tr>';

      $span = ($coord_borders & LEFT ? 1 : 0 ) + ( $smooth_edge ? 1 : 0 );
      $w = ($coord_borders & LEFT ? $coord_width : 0 ) + ( $smooth_edge ? 10 : 0 );
      if( $span > 0 )
         echo "<td colspan=$span background=\"images/blank.gif\"><img src=\"images/blank.gif\" alt=\"  \" width=$w height=$stone_size></td>";

      $colnr = 1;
      $letter = 'a';
      while( $colnr <= $Size )
      {
         echo $coord_start_letter . $letter . $coord_alt . $letter . $coord_end;

         $colnr++;
         $letter++;
         if( $letter == 'i' ) $letter++;
      }

      $span = ($coord_borders & RIGHT ? 1 : 0 ) + ( $smooth_edge ? 1 : 0 );
      $w = ($coord_borders & RIGHT ? $coord_width : 0 ) + ( $smooth_edge ? 10 : 0 );
      if( $span > 0 )
         echo "<td colspan=$span background=\"images/blank.gif\"><img src=\"images/blank.gif\" alt=\"  \" width=$w height=$stone_size></td>";

      echo "</tr>\n";
   }

   echo '</table></td></tr></table>' . "\n";
}


// fills $array with positions where the stones are.
// returns the coords of the last move
function make_array( $gid, &$array, &$msg, $max_moves, $move, &$result, &$marked_dead,
                     $no_marked_dead = false )
{
   $array = NULL;
   $lastx = $lasty = -1; // don't use as lastx/lasty

   if( !$move ) $move = $max_moves;

   $result = mysql_query( "SELECT * FROM Moves WHERE gid=$gid order by ID" );

   $removed_dead = FALSE;
   $marked_dead = array();

   while( $row = mysql_fetch_assoc($result) )
   {

      if( $row["MoveNr"] > $move )
      {
         if( $row["MoveNr"] > $max_moves )
            fix_corrupted_move_table($gid);
         break;
      }

      extract($row);

      $lastx = $lasty = -1; // don't use as lastx/lasty
      if( $Stone <= WHITE )
      {
         if( $PosX < 0 ) continue;

         $array[$PosX][$PosY] = $Stone;
         $lastx = $PosX; $lasty = $PosY;

         $removed_dead = FALSE;
      }
      else if( $Stone == MARKED_BY_WHITE or $Stone == MARKED_BY_BLACK)
      {
         if( $removed_dead == FALSE )
         {
            $marked_dead = array(); // restart removal
            $removed_dead = TRUE;
         }
         array_push($marked_dead, array($PosX,$PosY));
      }
   }

   if( !$no_marked_dead and $removed_dead == TRUE )
   {
      while( $sub = each($marked_dead) )
      {
         list($dummy, list($X, $Y)) = $sub;
         if( $array[$X][$Y] >= MARKED_DAME )
            $array[$X][$Y] -= OFFSET_MARKED;
         else
            $array[$X][$Y] += OFFSET_MARKED;
      }
   }

   $result2 = mysql_query( "SELECT Text FROM MoveMessages WHERE gid=$gid AND MoveNr=$move" );

   if( mysql_num_rows($result2) == 1 )
   {
      $row = mysql_fetch_array($result2);
      $msg = $row["Text"];
   }
   else
      $msg = '';

   return array($lastx,$lasty);
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

         $new_color = @$array[$nx][$ny];

         if( (!$new_color or $new_color == NONE ) and
             ( $nx >= 0 ) and ($nx < $Size) and ($ny >= 0) and ($ny < $Size) )
            return true; // found liberty

         if( $new_color == $c and !@$index[$nx][$ny])
         {
            $x = $nx;  // Go to the neighbour
            $y = $ny;
            $index[$x][$y] = $dir;
         }
      }
   }
}



function check_prisoners($colnr,$rownr, $col, $Size, &$array, &$prisoners )
{
   global $dirx,$diry;

   for($i=0; $i<4; $i++)
   {
      $x = $colnr+$dirx[$i];
      $y = $rownr+$diry[$i];

      if( @$array[$x][$y] == $col )
         has_liberty_check($x,$y, $Size, $array, $prisoners, true);
   }

}



function mark_territory( $x, $y, $size, &$array )
{
   global $dirx,$diry;

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

            while( list($x, $sub) = each($index) )
            {
               while( list($y, $val) = each($sub) )
               {
                  //keep all marks unchanged and reversible
                  if( @$array[$x][$y] < MARKED_DAME )
                     $array[$x][$y] = $c;
               }
            }

            return $point_count;
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

         $new_color = @$array[$nx][$ny];

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
}

function create_territories_and_score( $size, &$array )
{
   // mark territories

   for( $x=0; $x<$size; $x++)
   {
      for( $y=0; $y<$size; $y++)
      {
         if( !@$array[$x][$y] or $array[$x][$y] == NONE )
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
         switch( $array[$x][$y] & ~FLAG_NOCLICK)
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



function toggle_marked_area( $x, $y, $size, &$array, &$marked, $companion_groups=true )
{
   global $dirx,$diry;

   $c = @$array[$x][$y]; // Color of this stone

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
            while( list($x, $sub) = each($index) )
            {
               while( list($y, $val) = each($sub) )
               {
                  if ($c == @$array[$x][$y]) {
                     array_push($marked, array($x,$y));
                     if ( isset($array[$x][$y]) )
                        $array[$x][$y] ^= OFFSET_MARKED ;
                     else
                        $array[$x][$y]  = MARKED_DAME ;
                  }
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

         if( ( $nx < 0 ) or ($nx >= $size) or ($ny < 0) or ($ny >= $size) or
            @$index[$nx][$ny] )
            continue;

         $new_color = @$array[$nx][$ny];

         if( $new_color == $c or ( $companion_groups and $new_color == NONE ) )
         {
            $x = $nx;  // Go to the neighbour
            $y = $ny;
            $index[$x][$y] = $dir;
         }
         else if( $new_color == $opposite_dead )
         {
            toggle_marked_area( $nx, $ny, $size, $array, $marked, $companion_groups);
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

  //ajusted globals by check_move(): $array, $Black_Prisoners, $White_Prisoners, $prisoners, $nr_prisoners;
  //here, $prisoners list the captured stones of play (or suicided stones if, a day, $suicide_allowed==true)
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
            else if( $stone == MARKED_DAME )
               $type .= 's';

            $empty = true;
         }

         if( $pre_mark )
         {
            $out .= ")$type";
            $pre_mark = false;
         }
         else if( !$empty and $colnr == $Last_X and $rownr == $Size - $Last_Y
             and ( $stone == BLACK or $stone == WHITE ) )
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


?>
