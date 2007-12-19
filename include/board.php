<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

require_once( "include/coords.php" );

define('EDGE_SIZE', 10);
define('COORD_MASK', COORD_UP+COORD_RIGHT+COORD_DOWN+COORD_LEFT);


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
   var $captures; //array: [$MoveNr] => array($Stone,$PosX,$PosY,$mark)

   //Last move shown ($movemrkx<0 if PASS, RESIGN or SCORE)
   var $movemrkx, $movemrky, $movecol, $movemsg;

   var $infos; //extra-infos collected

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
      $this->infos = array();

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
      $this->infos = array();

      $gid = $game_row['ID'];
      if( $gid <= 0 )
         return FALSE;

      $this->gid = $gid;
      $this->size = $game_row['Size'];
      $this->max_moves = $game_row['Moves'];

      if( $this->max_moves <= 0 )
         return TRUE;

      $result = mysql_query( "SELECT * FROM Moves WHERE gid=$gid ORDER BY ID" )
         or error('mysql_query_failed',"board.load_from_db.find_moves($gid)");
      if( !$result )
         return FALSE;
      if( @mysql_num_rows($result) <= 0 )
      {
         mysql_free_result($result);
         return FALSE;
      }

      if( $move<=0 or $move>$this->max_moves )
         $move = $this->max_moves;

      $marked_dead = array();
      $removed_dead = FALSE;

      while( $row = mysql_fetch_assoc($result) )
      {

         extract($row);

         if ( $PosX <= POSX_ADDTIME ) //configuration actions
         {
            if ( $PosX == POSX_ADDTIME )
            {
         //POSX_ADDTIME Stone=time-adder, PosY=0|1 (1=byoyomi-reset), Hours=hours added
               $this->infos[] = array(POSX_ADDTIME, $MoveNr, $Stone, $Hours, $PosY);
            }
            continue;
         }

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
            if( $PosX < 0 ) continue; //excluding PASS, RESIGN and SCORE, ADDTIME

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
      mysql_free_result($result);

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

         //No need of movemsg if we don't have movecol??
         $row= mysql_single_fetch( 'board.load_from_db.movemessage',
                  "SELECT Text FROM MoveMessages WHERE gid=$gid AND MoveNr=$move");
         if( $row )
         {
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


   function move_marks( $start, $end, $mark=0)
   {
      if( is_string( $mark) )
      {
         $mod = 0;
      }
      else if( is_numeric( $mark) )
      {
         if( $mark > 0 )
            $mod = $mark;
         else
            $mod = MAX_MOVENUMBERS;
         $mod = min( $mod, MAX_MOVENUMBERS);
         if( $mod <= 1 )
            return;
         $mark = '';
      }
      else
         return;

      $start = max( $start, 1);

      for( $n=$end; $n>=$start; $n-- )
      {
         if( isset($this->moves[$n]) )
         {
            list( $s, $x, $y) = $this->moves[$n];
            //if( $s != BLACK and $s != WHITE ) continue;
            $sgfc = number2sgf_coords( $x, $y, $this->size);
            if( $sgfc )
            {
               if( $mod > 1 )
                  $mrk = (($n-1) % $mod)+1;
               else
                  $mrk = $mark;

               if( !isset($this->marks[$sgfc]) )
               {
                  $b = @$this->array[$x][$y];
                  if( ($b % OFFSET_MARKED) == $s ) // or $s==($b^OFFSET_MARKED)
                  {
                     if( $mrk > '' )
                        $this->marks[$sgfc] = $mrk;
                     continue;
                  }
                  if( $b==NONE )
                     $this->marks[$sgfc] = 'x';
               }
               $this->captures[$n] = array( $s, $x, $y, $mrk);
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
      $numover = $this->coord_borders & NUMBER_OVER ;

      $stone_attb = 'class=capt';
      $wcap= array();
      $bcap= array();
      foreach( $this->captures as $n => $sub )
      {
         list( $s, $x, $y, $mrk) = $sub;
         if( $numover )
         {
            $tit= (string)$n;
            if( is_numeric($mrk) )
               $mrk= ''; //no mark if $numover
         }
         else
            $tit= '';
         $brdc = number2board_coords( $x, $y, $size);
         if( $s == BLACK )
         {
            array_unshift( $bcap,
                  image( "$stone_size/b$mrk.gif", "X$n", $tit, $stone_attb)
                   . "&nbsp;:&nbsp;$brdc" );
         }
         else if( $s == WHITE )
         {
            array_unshift( $wcap,
                  image( "$stone_size/w$mrk.gif", "O$n", $tit, $stone_attb)
                   . "&nbsp;:&nbsp;$brdc" );
         }
      }

      echo "<table class=Captures>\n";
      echo "<tr>\n<th colspan=2>$caption</th>\n </tr>\n";
      echo "<tr>\n<td class=b>\n";
      if( count($bcap)>0 )
         echo '<dl><dt>'.implode("\n<dt>", $bcap)."</dl>";
         //echo implode("<br>\n", $bcap);
      echo "</td>\n<td class=w>\n";
      if( count($wcap)>0 )
         echo '<dl><dt>'.implode("\n<dt>", $wcap)."</dl>";
         //echo implode("<br>\n", $wcap);
      echo "</td>\n</tr>\n";
      echo "</table>\n";

      return true;
   }


   function set_style( &$player_row)
   {
      if( isset($player_row['Boardcoords']) && is_numeric($player_row['Boardcoords'])
        //&& $player_row['Boardcoords'] >= 0 && $player_row['Boardcoords'] <= 0xFF
        )
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

      $tmp = "img.%s{ width:%dpx; height:%dpx;}\n";
      $str = sprintf( $tmp, 'brdx', $stone_size, $stone_size) //board stones
           . sprintf( $tmp, 'brdl', $stone_size, $stone_size) //letter coords
           . sprintf( $tmp, 'brdn', $coord_width, $stone_size) //num coords
           . sprintf( $tmp, 'capt', $stone_size, $stone_size) //capture box
           ;
      $tmp = "td.%s{ width:%dpx; height:%dpx;}\n";
      $str.= sprintf( $tmp, 'brdx', $stone_size, $stone_size) //board stones
           . sprintf( $tmp, 'brdl', $stone_size, $stone_size) //letter coords
           . sprintf( $tmp, 'brdn', $coord_width, $stone_size) //num coords
           ;
      return $str;
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

      if( $this->coord_borders & COORD_LEFT )
         echo $edge_coord;

      echo '<td>' . $edge_start . 'l.gif" width='.EDGE_SIZE.'>' . "</td>\n";

      echo '<td colspan=' . $this->size . ' width=' . $this->size*$this->stone_size . '>';

      if( $border_imgs >= 0 )
         echo $edge_start . '.gif" width=' . $border_start . '>';
      for($i=0; $i<$border_imgs; $i++ )
         echo $edge_start . '.gif" width=150>';
      echo $edge_start . '.gif" width=' . $border_rem . '>';

      echo "</td>\n" . '<td>' . $edge_start . 'r.gif" width='.EDGE_SIZE.'>' . "</td>\n";

      if( $this->coord_borders & COORD_RIGHT )
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
         $border_start = 140 - ( $this->coord_borders & COORD_LEFT ? $coord_width : 0 );
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
      if( $this->coord_borders & (COORD_LEFT | COORD_RIGHT) )
      {
         $coord_start_number = "<td class=brdn><img class=brdn src=\"$stone_size/c";
      }
      if( $this->coord_borders & (COORD_UP | COORD_DOWN) )
      {
         $coord_start_letter = "<td class=brdl><img class=brdl src=\"$stone_size/c";

         $s = ($this->coord_borders & COORD_LEFT ? 1 : 0 ) + ( $smooth_edge ? 1 : 0 );
         if ( $s )
            $coord_left = "<td colspan=$s><img src=\"images/blank.gif\" width=" .
             ( ( $this->coord_borders & COORD_LEFT ? $coord_width : 0 )
             + ( $smooth_edge ? EDGE_SIZE : 0 ) ) .
             " height=$stone_size alt=\" \"></td>\n";
         else
            $coord_left = '';

         $s = ($this->coord_borders & COORD_RIGHT ? 1 : 0 ) + ( $smooth_edge ? 1 : 0 );
         if ( $s )
            $coord_right = "<td colspan=$s><img src=\"images/blank.gif\" width=" .
             ( ( $this->coord_borders & COORD_RIGHT ? $coord_width : 0 )
             + ( $smooth_edge ? EDGE_SIZE : 0 ) ) .
             " height=$stone_size alt=\" \"></td>\n";
         else
            $coord_right = '';
      }

      $nomove_start = "<td class=brdx><img class=brdx alt=\"";
      $nomove_end = ".gif\"></td>\n";
      if( $may_play )
      {
         switch( $action )
         {
            case 'handicap':
               $on_not_empty = false;
               $on_empty = true;
               $move_start = "<td class=brdx><a href=\"game.php?g=$gid".URI_AMP."a=handicap".URI_AMP."c=";
               $move_alt = "\"><img class=brdx alt=\"";
               if( $stonestring )
                  $move_alt = URI_AMP."s=$stonestring".$move_alt;
               break;
            case 'remove':
               $on_not_empty = true;
               if( MAX_SEKI_MARK>0 )
                  $on_empty = true;
               else
                  $on_empty = false;
               $move_start = "<td class=brdx><a href=\"game.php?g=$gid".URI_AMP."a=remove".URI_AMP."c=";
               $move_alt = "\"><img class=brdx alt=\"";
               if( $stonestring )
                  $move_alt = URI_AMP."s=$stonestring".$move_alt;
               break;
            default:
               $on_not_empty = false;
               $on_empty = true;
               $move_start = "<td class=brdx><a href=\"game.php?g=$gid".URI_AMP."a=domove".URI_AMP."c=";
               $move_alt = "\"><img class=brdx alt=\"";
               break;
         }
         $move_end = ".gif\"></a></td>\n";
      }

      if( $this->movemsg )
         echo "<table id=\"gameMessage\" class=MessageBox><tr>" . //align=center
            "<td width=\"" . $stone_size*19 . "\" align=left>$this->movemsg</td></tr></table><BR>\n";


      { // goban

      /**
       * style="background-image..." is not understood by old browsers like Netscape Navigator 4.0
       * meanwhile background="..." is not W3C compliant
       * so, for those old browsers, use the bgcolor="..." option
       **/
      if( $this->woodcolor > 10 )
         $woodstring = 'bgcolor="' . $woodbgcolors[$this->woodcolor - 10] . '"';
      else
         $woodstring = 'style="background-image:url(images/wood' . $this->woodcolor . '.gif);"';

      /**
       * Some simple browsers (like Pocket PC IE or PALM ones) poorly
       * manage the CSS commands related to cellspacing and cellpadding.
       * Most of the time, this results in a 1 or 2 pixels added to the
       * cells size and is not so disturbing. But this is really annoying
       * for the board cells.
       * So we keep the HTML commands here, even if deprecated.
       **/
      $cell_size_fix = ' border=0 cellspacing=0 cellpadding=0';

      echo '<table class=Goban ' . $woodstring . $cell_size_fix . '>';

      echo '<tbody>';

      if( $this->coord_borders & COORD_UP )
         $this->draw_coord_row( $coord_start_letter, $coord_alt, $coord_end,
                           $coord_left, $coord_right );

      if( $smooth_edge )
         $this->draw_edge_row( $edge_start.'u', $edge_coord,
                               $border_start, $border_imgs, $border_rem );

      $letter_r = 'a';
      for($rownr = $this->size; $rownr > 0; $rownr-- )
      {
         echo '<tr>';

         if( $this->coord_borders & COORD_LEFT )
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

            $sgfc = number2sgf_coords($colnr, $this->size-$rownr, $this->size);
            $mrk = '';
            $numover = false;
            if( is_array($this->marks) )
            {
               if( $sgfc && @$this->marks[$sgfc] )
               {
                  $mrk = $this->marks[$sgfc];
                  if( is_numeric($mrk) && $this->coord_borders & NUMBER_OVER )
                     $numover = true;
               }
            }

            if( !$marked )
            {
               if( !$empty && ( $stone == BLACK or $stone == WHITE )
                   && $this->movemrkx == $colnr
                   && $this->movemrky == $this->size-$rownr )
               { //last move mark
                  $type .= 'm';
                  $alt = ( $stone == BLACK ? '#' : '@' );
                  $marked = true;
               }
               elseif( $mrk )
               {
                  //$alt .= $mrk;
                  if( !$numover )
                  {
                     $type .= $mrk;
                     $marked = true;
                  } //else no mark if $numover
               }
            }

            $tit = '';
            if( $numover )
               $tit = $mrk;
            if( $this->coord_borders & COORD_OVER )
               //strtoupper? -> change capturebox too
               $tit = ( $tit ? $tit.' - ' : '' ) . $letter.$rownr;
            if( $this->coord_borders & COORD_SGFOVER )
               $tit = ( $tit ? $tit.' - ' : '' ) . $sgfc;
            if( $tit )
               $alt.= "\" title=\"$tit";

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

         if( $this->coord_borders & COORD_RIGHT )
            echo $coord_start_number . $rownr . $coord_alt . $rownr .$coord_end;

         echo "</tr>\n";
         $letter_r++;
      }

      if( $smooth_edge )
         $this->draw_edge_row( $edge_start.'d', $edge_coord,
                               $border_start, $border_imgs, $border_rem );

      if( $this->coord_borders & COORD_DOWN )
         $this->draw_coord_row( $coord_start_letter, $coord_alt, $coord_end,
                           $coord_left, $coord_right );

      echo '</tbody>';
      echo "</table>\n";
      } //goban
   } //draw_board


   //$coord_borders and $movemsg stay local.
   function draw_ascii_board( $movemsg='', $coord_borders=COORD_MASK)
   {
      $out = "\n";

      if( $movemsg )
         $out .= wordwrap("Message: $movemsg", 47) . "\n\n";

      if( $coord_borders & COORD_UP )
      {
         $out .= '  ';
         if( $coord_borders & COORD_LEFT )
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
         if( $coord_borders & COORD_LEFT )
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

         if( $coord_borders & COORD_RIGHT )
            $out .= str_pad($rownr, 2, ' ', STR_PAD_RIGHT);

         $letter_r++;
         $out .= "\n";
      }

      if( $coord_borders & COORD_DOWN )
      {
         $out .= '  ';
         if( $coord_borders & COORD_LEFT )
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


   // If move update was interupted between two mysql queries, there may
   // be extra entries in the Moves and MoveMessages tables.
   function fix_corrupted_move_table( $gid)
   {
     $row= mysql_single_fetch( "board.fix_corrupted_move_table.moves: $gid",
              "SELECT Moves FROM Games WHERE ID=$gid" );
     if( !$row )
        error('internal_error', "board.fix_corrupted_move_table.moves($gid)");
     $Moves= $row['Moves'];

     $row= mysql_single_fetch( "board.fix_corrupted_move_table.max: $gid",
              "SELECT MAX(MoveNr) AS max FROM Moves WHERE gid=$gid" );
     if( !$row )
        error('internal_error', "board.fix_corrupted_move_table.max($gid)");
     $max_movenr= $row['max'];

     if($max_movenr == $Moves)
        return;

     if($max_movenr != $Moves+1)
        error("mysql_data_corruption",
              "board.fix_corrupted_move_table.unfixable($gid)"); // Can't handle this type of problem

     mysql_query("DELETE FROM Moves WHERE gid=$gid AND MoveNr=$max_movenr")
        or error('mysql_query_failed',"board.fix_corrupted_move_table.delete_moves($gid)");
     mysql_query("DELETE FROM MoveMessages WHERE gid=$gid AND MoveNr=$max_movenr")
        or error('mysql_query_failed',"board.fix_corrupted_move_table.delete_move_mess($gid)");
   }


} //class Board
?>
