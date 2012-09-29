<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once( "include/game_functions.php" );

if( !defined('EDGE_SIZE') )
   define('EDGE_SIZE', 10);

// for Board::load_from_db()
define('BOARDOPT_MARK_DEAD',     0x01);
define('BOARDOPT_LOAD_LAST_MSG', 0x02);
define('BOARDOPT_STOP_ON_FIX',   0x04); // if set: stop and return FALSE if corrupted game found
define('BOARDOPT_USE_CACHE',     0x08);


class Board
{
   var $gid;
   var $size;
   var $max_moves;
   var $coord_borders;
   var $board_flags;
   var $stone_size;
   var $woodcolor;

   var $array; //2D array: [$PosX][$PosY] => $Stone
   var $moves; //array: [$MoveNr] => array($Stone,$PosX,$PosY); first MoveNr=MOVE_SETUP ('S') for shape-game
   var $marks; //array: [$sgf_coord] => $mark
   var $captures; //array: [$MoveNr] => array($Stone,$PosX,$PosY,$mark)
   var $js_moves; //array($MoveNr,$Stone,$PosX,$PosY) to build moves for JS-game-editor
   var $moves_captures; //array: [$MoveNr] => array(sgfcoord-prisoners, ...)
   var $last_captures; //array: [sgfcoord] => 1 (if pos should be marked)

   //Last move shown ($movemrkx<0 if PASS, RESIGN or SCORE)
   var $movemrkx, $movemrky, $movecol, $movemsg;

   var $infos; //extra-infos collected

   var $dirx, $diry; //directions arrays
   var $visited_points; // visited coords arr[x][y] set after run of has_liberty_check()-call


   // Constructor. Create a new board array and initialize it.
   function Board( $gid=0, $size=19, $max_moves=0 )
   {
      $this->gid = $gid;
      $this->size = $size;
      $this->max_moves = $max_moves;

      $this->coord_borders = -1;
      $this->board_flags = 0;
      $this->stone_size = 25;
      $this->woodcolor = 1;

      $this->array = NULL;
      $this->moves = array();
      $this->marks = NULL;
      $this->captures = NULL;
      $this->js_moves = array();
      $this->moves_captures = NULL;
      $this->last_captures = array();

      $this->movemrkx = $this->movemrky = -1;
      $this->movecol = DAME;
      $this->movemsg = '';
      $this->infos = array();

      $this->dirx = array( -1,0,1,0 );
      $this->diry = array( 0,-1,0,1 );
      $this->visited_points = NULL;
   }

   function init_board()
   {
      $this->array = array();
      $this->moves = array();
      $this->marks = array();
      $this->captures = array();
      $this->movemrkx = $this->movemrky = -1; // don't use as last move coord
      $this->movecol = DAME;
      $this->movemsg = '';
      $this->infos = array();
      $this->js_moves = array();
      $this->moves_captures = array();
   }//init_board

   /*!
    * \brief Loads move and messages into Board for given game-info and move-number.
    * \param $game_row need fields: ID, Size, Moves, ShapeSnapshot
    * \param $move move-number, 0=last-move, MOVE_SETUP=S=initial-setup-for-shape-game
    * \param $board_opts BOARDOPT_... specifying extras to do or not do:
    *        BOARDOPT_MARK_DEAD, BOARDOPT_LOAD_LAST_MSG, BOARDOPT_STOP_ON_FIX, BOARDOPT_USE_CACHE
    * \param $cache_ttl 0 = use default (see load_cache_game_moves-func); otherwise TTL in secs for caching game-moves
    *
    * \note fills $this->array with positions where the stones are (incl. handling of shape-game)
    * \note fills $this->moves with moves and coordinates.
    * \note keep the coords, color and message of the move $move.
    */
   function load_from_db( $game_row, $move=0, $board_opts=0, $cache_ttl=0 )
   {
      $this->init_board();

      $gid = $game_row['ID'];
      if( $gid <= 0 )
         return FALSE;

      $this->gid = $gid;
      $this->size = $game_row['Size'];
      $this->max_moves = $game_row['Moves'];

      $shape_snapshot = @$game_row['ShapeSnapshot'];
      $is_shape = (bool)$shape_snapshot;
      if( !$is_shape && $this->max_moves <= 0 )
         return TRUE;

      // handle goto-move (incl. shape-game)
      if( !$is_shape && $move === MOVE_SETUP )
         error('invalid_args', "board.load_from_db.check.move.no_shape($gid,$move)");
      if( $is_shape && $move === MOVE_SETUP )
      {
         $move = 1;
         $show_move_setup = true;
      }
      else
         $show_move_setup = false;
      if( $move <= 0 || $move > $this->max_moves )
         $move = $this->max_moves;

      // load moves
      $use_cache = (bool)( $board_opts & BOARDOPT_USE_CACHE );
      $arr_moves = Board::load_cache_game_moves( 'load_from_db', $gid, $use_cache, $use_cache, $cache_ttl );
      if( $arr_moves === false )
         return FALSE;
      $cnt_moves = count($arr_moves);
      if( $cnt_moves == 0 )
         return FALSE;


      $marked_dead = array();
      $removed_dead = FALSE;

      // parse init-board from shape-snapshot
      if( $shape_snapshot )
      {
         $arr_xy = GameSnapshot::parse_stones_snapshot( $this->size, $shape_snapshot, BLACK, WHITE );
         if( count($arr_xy) )
         {
            $this->moves[MOVE_SETUP] = array( BLACK, POSX_SETUP, 0 );
            foreach( $arr_xy as $arr_setup )
            {
               list( $Stone, $PosX, $PosY ) = $arr_setup;
               $this->array[$PosX][$PosY] = $Stone;
               $this->js_moves[] = array( 0, $Stone, $PosX, $PosY );
            }
         }
      }

      // load moves
      $fix_stop = ( $board_opts & BOARDOPT_STOP_ON_FIX );
      foreach( $arr_moves as $row )
      {
         extract($row); //$MoveNr, $Stone, $PosX, $PosY, $Hours

         if( $PosX <= POSX_ADDTIME ) //configuration actions
         {
            if( $PosX == POSX_ADDTIME )
            {
               $added_by_td = ($PosY & 2);
               $time_from = ($added_by_td) ? STONE_TD_ADDTIME : $Stone;
               $this->infos[] = array(POSX_ADDTIME, $MoveNr, $time_from, $Stone, $Hours, ($PosY & 1));
            }
            continue;
         }

         if( $Stone == BLACK || $Stone == WHITE )
         {
            $this->moves[$MoveNr] = array( $Stone, $PosX, $PosY);
            $this->js_moves[] = array( $MoveNr, $Stone, $PosX, $PosY );
         }

         if( $MoveNr > $move )
         {
            if( $MoveNr > $this->max_moves )
            {
               if( $fix_stop )
                  return FALSE;
               else
                  $this->fix_corrupted_move_table( $gid);
               break;
            }
            continue;
         }

         if( $Stone == NONE && $PosX >= 0 ) // prisoners
            $this->moves_captures[$MoveNr][] = number2sgf_coords( $PosX, $PosY, $this->size );

         if( $Stone <= WHITE ) //including NONE (prisoners)
         {
            if( $PosX < 0 )
            {
               $this->js_moves[] = array( $MoveNr, $Stone, $PosX, 0 );
               continue; //excluding PASS, RESIGN and SCORE, ADDTIME
            }

            $this->array[$PosX][$PosY] = $Stone; //including DAME (prisoners)

            $removed_dead = FALSE; // restart removal
         }
         else if( $Stone == MARKED_BY_WHITE || $Stone == MARKED_BY_BLACK)
         {
            if( !$removed_dead )
            {
               $marked_dead = array(); // restart removal
               $removed_dead = TRUE;
            }
            $marked_dead[] = array( $PosX, $PosY);
            $this->js_moves[] = array( $MoveNr, $Stone, $PosX, $PosY );
         }
      }//scan moves
      unset($arr_moves);

      if( ($board_opts & BOARDOPT_MARK_DEAD) && $removed_dead )
      {
         foreach( $marked_dead as $sub )
         {
            list( $x, $y) = $sub;
            @$this->array[$x][$y] ^= OFFSET_MARKED;
         }
      }

      if( ($board_opts & BOARDOPT_LOAD_LAST_MSG) && !$show_move_setup && isset($this->moves[$move]) )
      {
         list($this->movecol, $this->movemrkx, $this->movemrky) = $this->moves[$move];

         $move_text = Board::load_cache_game_move_message( 'load_from_db', $gid, $move, $use_cache, $use_cache, $cache_ttl );
         if( $move_text !== false && !is_array($move_text) )
            $this->movemsg = $move_text;
      }

      return TRUE;
   } //load_from_db

   // static
   function load_cache_game_moves( $dbgmsg, $gid, $fetch_cache, $store_cache, $cache_ttl=0 )
   {
      $dbgmsg = "board.load_cache_game_moves($gid,$fetch_cache,$store_cache).$dbgmsg";
      $key = "Game.moves.$gid";

      $arr_moves = ( $fetch_cache && DgsCache::is_persistent(CACHE_GRP_GAME_MOVES) )
         ? DgsCache::fetch( $dbgmsg, CACHE_GRP_GAME_MOVES, $key )
         : null;
      if( is_null($arr_moves) )
      {
         $db_result = db_query( $dbgmsg,
            "SELECT MoveNr, Stone, PosX, PosY, Hours FROM Moves WHERE gid=$gid ORDER BY MoveNr" );

         if( $db_result )
         {
            $arr_moves = array();
            while( $row = mysql_fetch_assoc($db_result) )
               $arr_moves[] = $row;
         }
         else
            $arr_moves = false;
         mysql_free_result($db_result);

         if( $store_cache )
         {
            $ttl = ( is_numeric($cache_ttl) && $cache_ttl > 0 ) ? $cache_ttl : 10*SECS_PER_MIN;
            DgsCache::store( $dbgmsg, CACHE_GRP_GAME_MOVES, $key, $arr_moves, $ttl );
         }
      }

      return $arr_moves;
   }//load_cache_game_moves

   // static
   function delete_cache_game_moves( $dbgmsg, $gid )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_GAME_MOVES, "Game.moves.$gid" );
   }

   // static
   // \param $move null = load all moves, otherwise MoveNr for text
   // \return false = nothing found; Text (if $move>0 and entry found); arr( MoveNr => Text, ...) otherwise (arr can be empty)
   function load_cache_game_move_message( $dbgmsg, $gid, $move, $fetch_cache, $store_cache, $cache_ttl=0 )
   {
      $dbgmsg = "board.load_cache_game_move_message($gid,$move,$fetch_cache,$store_cache).$dbgmsg";
      $key = "Game.movemsg.$gid";

      $query = false;
      if( $fetch_cache && DgsCache::is_persistent(CACHE_GRP_GAME_MOVEMSG) )
      {
         $result = DgsCache::fetch( $dbgmsg, CACHE_GRP_GAME_MOVEMSG, $key );
         if( is_null($result) )
            $query = "SELECT MoveNr, Text FROM MoveMessages WHERE gid=$gid";
      }
      elseif( is_numeric($move) )
         $query = "SELECT MoveNr, Text FROM MoveMessages WHERE gid=$gid AND MoveNr=$move LIMIT 1";
      else
         $query = "SELECT MoveNr, Text FROM MoveMessages WHERE gid=$gid";

      if( $query )
      {
         $db_result = db_query( $dbgmsg, $query ); // single or all
         if( $db_result )
         {
            $result = array();
            while( $row = mysql_fetch_assoc($db_result) )
               $result[$row['MoveNr']] = $row['Text'];
         }
         else
            $result = false;
         mysql_free_result($db_result);

         if( $store_cache )
         {
            $ttl = ( is_numeric($cache_ttl) && $cache_ttl > 0 ) ? $cache_ttl : 10*SECS_PER_MIN;
            DgsCache::store( $dbgmsg, CACHE_GRP_GAME_MOVEMSG, $key, $result, $ttl );
         }
      }

      if( is_numeric($move) && isset($result[$move]) )
         $result = $result[$move]; // Text-value for single-move

      return $result;
   }//load_cache_game_move_message

   // static
   function delete_cache_game_move_messages( $dbgmsg, $gid )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_GAME_MOVEMSG, "Game.movemsg.$gid" );
   }

   // fills $array with positions where the stones are (incl. handling of shape-game)
   // NOTE: only used for shape-checking, not to control board
   function build_from_shape_snapshot( $size, $shape_snapshot )
   {
      $this->init_board();
      $this->size = $size;

      // parse init-board from shape-snapshot
      if( $shape_snapshot )
      {
         $arr_xy = GameSnapshot::parse_stones_snapshot( $this->size, $shape_snapshot, BLACK, WHITE );
         foreach( $arr_xy as $arr_setup )
         {
            list( $Stone, $PosX, $PosY ) = $arr_setup;
            $this->array[$PosX][$PosY] = $Stone;
         }
      }
   }//build_from_shape_snapshot


   /*!
    * \brief Adds handicap stones to board-array.
    * \param $coords list with coordinates-array: [ (x,y), ... ]
    */
   function add_handicap_stones( $coords )
   {
      foreach( $coords as $coord )
      {
         list( $x, $y ) = $coord;
         if( !isset($x) || !isset($y) || @$array[$x][$y] != NONE )
            error('illegal_position', "board.add_handicap_stones({$this->gid},$x,$y)");

         $this->array[$x][$y] = BLACK;
      }
   }


   function set_move_mark( $x=-1, $y=-1)
   {
      $this->movemrkx= $x;
      $this->movemrky= $y;
   }


   function move_marks( $start, $end, $mark=0)
   {
      if( is_string( $mark) )
         $mod = 0;
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
      $movenums = count($this->moves);
      if( isset($this->moves[MOVE_SETUP]) ) // shape-game-setup
         $movenums--;

      $is_relative = ($this->coord_borders & COORD_RELATIVE_MOVENUM);
      $is_reverse  = ($this->coord_borders & COORD_REVERSE_MOVENUM);
      for( $n=$end; $n>=$start; $n-- )
      {
         if( isset($this->moves[$n]) )
         {
            list( $s, $x, $y) = $this->moves[$n];
            //if( $s != BLACK && $s != WHITE ) continue;
            $sgfc = number2sgf_coords( $x, $y, $this->size);
            if( $sgfc )
            {
               if( $mod > 1 )
               {
                  $movelabel = $n;
                  if( $is_reverse )
                     $movelabel = ($is_relative ? $end : $movenums) - $movelabel;
                  elseif( $is_relative )
                     $movelabel = $movelabel - $start + 1;

                  $mrk = (($movelabel-1) % $mod)+1;
               }
               else
                  $mrk = $mark;

               if( !isset($this->marks[$sgfc]) )
               {
                  $b = @$this->array[$x][$y];
                  if( ($b % OFFSET_MARKED) == $s ) // || $s==($b^OFFSET_MARKED)
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
   }// move_marks

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
      {
         echo '<dl><dt>'.implode("</dt\n><dt>", $bcap)."</dt\n></dl>";
         //echo implode("<br>\n", $bcap);
      }
      echo "</td>\n<td class=w>\n";
      if( count($wcap)>0 )
      {
         echo '<dl><dt>'.implode("</dt\n><dt>", $wcap)."</dt\n></dl>";
         //echo implode("<br>\n", $wcap);
      }
      echo "</td>\n</tr>\n";
      echo "</table>\n";

      return true;
   } //draw_captures_box


   function mark_last_captures( $move )
   {
      $this->last_captures = array();
      if( is_array($this->moves_captures) && isset($this->moves_captures[$move]) )
      {
         foreach( $this->moves_captures[$move] as $sgfc )
            $this->last_captures[$sgfc] = 1;
      }
      return count($this->last_captures);
   }//mark_last_captures

   function draw_last_captures_box( $caption )
   {
      if( !is_array($this->last_captures) )
         return false;
      $cnt_captures = count($this->last_captures);

      $arr = array();
      foreach( $this->last_captures as $sgfc => $val )
      {
         list( $x, $y ) = sgf2number_coords( $sgfc, $this->size );
         $arr[] = number2board_coords( $x, $y, $this->size );
      }
      if( $cnt_captures == 0 )
         $arr[] = NO_VALUE;

      $capture_str = wordwrap( implode(', ', $arr), 20, "<br>\n", false );
      echo "<table class=Captures>\n",
         "<tr>\n<th>$caption</th>\n </tr>\n",
         "<tr><td>",
            sprintf('#%d: %s', $cnt_captures, $capture_str),
         "</td></tr>\n",
         "</table>\n";

      return true;
   }//draw_last_captures_box


   function set_style( $cfg_board )
   {
      $board_coords = $cfg_board->get_board_coords();
      if( is_numeric($board_coords) )
         $this->coord_borders = $board_coords;
      else
         $this->coord_borders = -1;

      $this->board_flags = $cfg_board->get_board_flags();
      $this->stone_size = $cfg_board->get_stone_size();
      $this->woodcolor = $cfg_board->get_wood_color();
   }


   // keep in sync with GobanHandlerGfxBoard
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


   // keep in sync with GobanHandlerGfxBoard
   function draw_coord_row( $coord_start_letter, $coord_alt, $coord_end, $coord_left, $coord_right )
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


   // keep in sync with GobanHandlerGfxBoard
   function draw_edge_row( $edge_start, $edge_coord, $border_start, $border_imgs, $border_rem )
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


   // keep in sync with GobanHandlerGfxBoard
   // board: img.alt-attr mapping: B>X W>O, last-move B># W>@, dead B>x W>o, terr B>+ W>-, dame>. seki-dame>s hoshi>, else>.
   function draw_board( $may_play=false, $action='', $stonestring='')
   {
      global $woodbgcolors;

      if( ($gid=$this->gid) <= 0 )
         $may_play= false;

      $stone_size = $this->stone_size;
      $coord_width = floor($stone_size*31/25);
      $mark_last_capture = ($this->board_flags & BOARDFLAG_MARK_LAST_CAPTURE );

      $smooth_edge = ( ($this->coord_borders & SMOOTH_EDGE) && ($this->woodcolor < 10) );

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
         if( $s )
            $coord_left = "<td colspan=$s><img src=\"images/blank.gif\" width=" .
               ( ( $this->coord_borders & COORD_LEFT ? $coord_width : 0 )
               + ( $smooth_edge ? EDGE_SIZE : 0 ) ) .
               " height=$stone_size alt=\" \"></td>\n";
         else
            $coord_left = '';

         $s = ($this->coord_borders & COORD_RIGHT ? 1 : 0 ) + ( $smooth_edge ? 1 : 0 );
         if( $s )
            $coord_right = "<td colspan=$s><img src=\"images/blank.gif\" width=" .
               ( ( $this->coord_borders & COORD_RIGHT ? $coord_width : 0 )
               + ( $smooth_edge ? EDGE_SIZE : 0 ) ) .
               " height=$stone_size alt=\" \"></td>\n";
         else
            $coord_right = '';
      }

      $nomove_start = "<td id=%s class=brdx><img class=brdx alt=\"";
      $nomove_src = "\" src=\"$stone_size/";
      $nomove_end = ".gif\"></td>\n";
      if( $may_play )
      {
         switch( (string)$action )
         {
            case 'handicap':
               $on_not_empty = false;
               $on_empty = true;
               $move_start = "<td id=%s class=brdx><a href=\"game.php?g=$gid".URI_AMP."a=handicap".URI_AMP."c=";
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
               $move_start = "<td id=%s class=brdx><a href=\"game.php?g=$gid".URI_AMP."a=remove".URI_AMP."c=";
               $move_alt = "\"><img class=brdx alt=\"";
               if( $stonestring )
                  $move_alt = URI_AMP."s=$stonestring".$move_alt;
               break;
            default:
               $on_not_empty = false;
               $on_empty = true;
               $move_start = "<td id=%s class=brdx><a href=\"game.php?g=$gid".URI_AMP."a=domove".URI_AMP."c=";
               $move_alt = "\"><img class=brdx alt=\"";
               break;
         }
         $move_src = "\" src=\"$stone_size/";
         $move_end = ".gif\"></a></td>\n";
      }

      if( $this->movemsg )
         $this->draw_move_message( $this->movemsg );


      { // draw goban
         /**
          * style="background-image..." is not understood by old browsers like Netscape Navigator 4.0
          * meanwhile background="..." is not W3C compliant
          * so, for those old browsers, use the bgcolor="..." option
          **/
         if( $this->woodcolor > 10 )
            $woodstring = ' bgcolor="' . $woodbgcolors[$this->woodcolor - 10] . '"';
         else
            $woodstring = ' style="background-image:url(images/wood' . $this->woodcolor . '.gif);"';

         /**
          * Some simple browsers (like Pocket PC IE or PALM ones) poorly
          * manage the CSS commands related to cellspacing and cellpadding.
          * Most of the time, this results in a 1 or 2 pixels added to the
          * cells size and is not so disturbing into normal tables.
          * But this is really annoying for the board cells.
          * So we keep the HTML commands here, even if deprecated.
          **/
         $cell_size_fix = ' border=0 cellspacing=0 cellpadding=0';

         echo '<table id=Goban class=Goban' . $woodstring . $cell_size_fix . '>';

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
               if( is_array($this->marks) && $sgfc && @$this->marks[$sgfc] )
               {
                  $mrk = $this->marks[$sgfc];
                  if( is_numeric($mrk) && $this->coord_borders & NUMBER_OVER )
                     $numover = true;
               }
               if( !$mrk && $sgfc && $mark_last_capture && @$this->last_captures[$sgfc] )
                  $mrk = 'x';

               $img_id = '';
               if( !$marked )
               {
                  if( !$empty && ( $stone == BLACK || $stone == WHITE )
                      && $this->movemrkx == $colnr
                      && $this->movemrky == $this->size-$rownr )
                  { //last move mark
                     $type .= 'm';
                     $alt = ( $stone == BLACK ? '#' : '@' );
                     $img_id = 'lastMove';
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

               if( $img_id )
                  $img_id = "\" id=\"$img_id";

               if( $may_play && !$no_click &&
                   ( ($empty && $on_empty) || (!$empty && $on_not_empty) ) )
                  echo sprintf($move_start, $sgfc) . "$letter_c$letter_r$move_alt$alt$img_id$move_src$type$move_end";
               else
                  echo sprintf($nomove_start, $sgfc) . "$alt$img_id$nomove_src$type$nomove_end";

               $letter_c++;
               $letter++; if( $letter == 'i' ) $letter++;
            }//colnr

            if( $smooth_edge )
               echo '<td>' . $edge_vert . "r.gif\"></td>\n";

            if( $this->coord_borders & COORD_RIGHT )
               echo $coord_start_number . $rownr . $coord_alt . $rownr .$coord_end;

            echo "</tr>\n";
            $letter_r++;
         }//rownr

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
               $type = 'X';
            else if( $stone == WHITE )
               $type = 'O';
            else if( $stone == BLACK_DEAD )
               $type = 'x';
            else if( $stone == WHITE_DEAD )
               $type = 'o';
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
            else if( !$empty && ( $stone == BLACK || $stone == WHITE )
                   && $this->movemrkx == $colnr
                   && $this->movemrky == $this->size-$rownr )
            {
               $out .= "($type";
               $pre_mark = true;
            }
            else
               $out .= " $type";

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

   function draw_move_message( $msg )
   {
      echo "<table id=\"gameMessage\" class=MessageBox><tr>" . //align=center
           "<td width=\"" . $this->stone_size*19 . "\" align=left>$msg</td></tr></table><BR>\n";
   }

   /*!
    * \brief Returns true if group at position (x,y) has at least one liberty.
    * \param $start_x/y board-position to check, 0..n
    * \param $prisoners if $remove is set, pass-back extended prisoners-array if group at x,y has no liberty
    * \param $remove if true, remove captured stones
    * \note fill this.visited_points if set as an array
    */
   function has_liberty_check( $start_x, $start_y, &$prisoners, $remove )
   {
      $color = @$this->array[$start_x][$start_y]; // Color of this stone

      $arr_xy = array( $start_x, $start_y );
      $stack = array( $arr_xy );
      $visited = array(); // potential prisoners and marker if point already checked
      $visited[$start_x][$start_y] = $arr_xy;
      $fill_visit = !is_null($this->visited_points);
      if( $fill_visit )
         $this->visited_points[$start_x][$start_y] = 1;

      // scanning all directions starting at start-x/y building up a stack of adjacent points to check
      while( $arr_xy = array_shift($stack) )
      {
         list( $x, $y ) = $arr_xy;

         for( $dir=0; $dir < 4; $dir++) { // scan all directions: W N E S
            $new_x = $x + $this->dirx[$dir];
            $new_y = $y + $this->diry[$dir];

            if( ($new_x >= 0 && $new_x < $this->size) && ($new_y >= 0 && $new_y < $this->size) )
            {
               $new_color = @$this->array[$new_x][$new_y];
               if( !$new_color || $new_color == NONE )
                  return true; // found liberty
               elseif( $new_color == $color && !@$visited[$new_x][$new_y] )
               {
                  $arr_xy = array( $new_x, $new_y );
                  $stack[] = $arr_xy;
                  $visited[$new_x][$new_y] = $arr_xy;
                  if( $fill_visit )
                     $this->visited_points[$new_x][$new_y] = 1;
               }
            }
         }
      }

      if( $remove )
      {
         foreach( $visited as $x => $arr_y )
         {
            foreach( $arr_y as $y => $arr_xy )
            {
               $prisoners[] = $arr_xy;
               unset($this->array[$x][$y]);
            }
         }
      }

      return false;
   }//has_liberty_check


   // checks if stone/move at [colnr/rownr] captures some stone(s)
   // returns true, if some stones captured (in which case $prisoners-arg contains the x/y-coords of the prisoners)
   function check_prisoners( $colnr, $rownr, $col, &$prisoners )
   {
      $some = false;
      for($i=0; $i<4; $i++) // determine captured stones for ALL directions
      {
         $x = $colnr + $this->dirx[$i];
         $y = $rownr + $this->diry[$i];

         if( @$this->array[$x][$y] == $col )
         {
            if( !$this->has_liberty_check( $x, $y, $prisoners, true) ) // change $prisoners
               $some = true; // can't exit before all prisoners collected
         }
      }
      return $some;
   } //check_prisoners


   /*!
    * \brief Returns number of marked territory points at position (x,y).
    * \param $start_x/y board-position to check, 0..n
    */
   function mark_territory( $start_x, $start_y )
   {
      $color = -1;  // color of territory

      $stack = array( array( $start_x, $start_y ) );
      $visited = array(); // marker if point already checked
      $visited[$start_x][$start_y] = 1;

      // scanning all directions starting at start-x/y building up a stack of adjacent points to check
      while( $arr_xy = array_shift($stack) )
      {
         list( $x, $y ) = $arr_xy;

         for( $dir=0; $dir < 4; $dir++) { // scan all directions: W N E S
            $new_x = $x + $this->dirx[$dir];
            $new_y = $y + $this->diry[$dir];

            if( ($new_x >= 0 && $new_x < $this->size) && ($new_y >= 0 && $new_y < $this->size) && !@$visited[$new_x][$new_y] )
            {
               $new_color = @$this->array[$new_x][$new_y];

               if( !$new_color || $new_color == NONE || $new_color >= BLACK_DEAD )
               {
                  $stack[] = array( $new_x, $new_y );
                  $visited[$new_x][$new_y] = 1;
               }
               else //remains BLACK/WHITE/DAME/BLACK_TERRITORY/WHITE_TERRITORY and MARKED_DAME
               {
                  if( $new_color == MARKED_DAME )
                     $color = NONE; // This area will become dame
                  else if( $color == -1 )
                     $color = $new_color;
                  else if( $color == (WHITE + BLACK - $new_color) )
                     $color = NONE; // This area has both colors as boundary
               }
            }
         }
      }

      $point_count = count($visited);
      if( $color == -1 )
         $color = DAME ;
      else
         $color |= OFFSET_TERRITORY ;
      if( $color == DAME || $point_count > MAX_SEKI_MARK)
         $color |= FLAG_NOCLICK;

      foreach( $visited as $x => $arr_y )
      {
         foreach( $arr_y as $y => $val )
         {
            //keep all marks unchanged and reversible
            if( @$this->array[$x][$y] < MARKED_DAME )
               $this->array[$x][$y] = $color;
         }
      }

      return $point_count;
   }//mark_territory


   /*!
    * \brief Calculates game-score-data.
    * \param $with_board_status enriches GameScore-object with board-status (used for quick-scoring)
    * \return if $with_coords is false, return null; otherwise returns
    *         [ territory-array, prisoners-array ] with [ coords => SGF-prop, ...] for SGF-download
    */
   function fill_game_score( &$game_score, $with_coords=false, $with_board_status=false )
   {
      // mark territory
      for( $x=0; $x<$this->size; $x++)
      {
         for( $y=0; $y<$this->size; $y++)
         {
            if( !@$this->array[$x][$y] || $this->array[$x][$y] == NONE )
               $this->mark_territory( $x, $y);
         }
      }

      // count
      $counts = array(
         DAME  => 0,
         BLACK => 0, BLACK_DEAD => 0, BLACK_TERRITORY => 0,
         WHITE => 0, WHITE_DEAD => 0, WHITE_TERRITORY => 0,
      );

      if( $with_board_status )
      {
         $counts[MARKED_DAME] = 0; // =neutral, only for board-status
         $bs = new BoardStatus();
         $game_score->set_board_status( $bs );
      }
      else
         $bs = null;

      $territory = array();
      $prisoners = array();

      for( $x=0; $x<$this->size; $x++)
      {
         for( $y=0; $y<$this->size; $y++)
         {
            if( $bs || $with_coords )
               $coord = chr($x + ord('a')) . chr($y + ord('a'));

            # 0=NONE, 1=BLACK, 2=WHITE, 4=DAME, 5=BLACK_TERRITORY, 6=WHITE_TERRITORY, 9=BLACK_DEAD, 10=WHITE_DEAD
            $mark = ( @$this->array[$x][$y] & ~FLAG_NOCLICK );
            if( isset($counts[$mark]) )
            {
               $counts[$mark]++;
               if( $bs )
                  $bs->add_coord( $mark, $coord );
            }

            if( $with_coords )
            {
               switch( (int)$mark )
               {
                  case WHITE_DEAD:
                     $prisoners[$coord] = 'AE';
                  case BLACK_TERRITORY:
                     $territory[$coord] = 'TB';
                     break;

                  case BLACK_DEAD:
                     $prisoners[$coord] = 'AE';
                  case WHITE_TERRITORY:
                     $territory[$coord] = 'TW';
                     break;
               }
            }
         }
      }

      // fill
      $game_score->set_stones_all( $counts[BLACK], $counts[WHITE] );
      $game_score->set_dead_stones_all( $counts[BLACK_DEAD], $counts[WHITE_DEAD] );
      $game_score->set_territory_all( $counts[BLACK_TERRITORY], $counts[WHITE_TERRITORY] );
      $game_score->set_dame( $counts[DAME] );
      //error_log($game_score->to_string());

      return ( $with_coords ) ? array( $territory, $prisoners ) : null;
   } //fill_game_score


   /*!
    * \brief Toggles marked area for scoring starting at position (x,y).
    * \param $start_x/y board-position to check, 0..n
    * \param $marked pass-back marked x/y-points
    * \param $toggled array[x][y] with toggled points to avoid re-toggling (used for unique-toggle-mode of quick-suite)
    */
   function toggle_marked_area( $start_x, $start_y, &$marked, &$toggled, $companion_groups=true )
   {
      $color = @$this->array[$start_x][$start_y]; // Color of this stone

      /***
       * Actually, $opposite_dead force an already marked dead neighbour group from the
       * opposite color to reverse to not dead, but this does not work properly if
       * $companion_groups is not true, as both groups may be not touching themself.
       ***/
      if( $companion_groups && ($color == BLACK || $color == WHITE) )
         $opposite_dead = WHITE + BLACK_DEAD - $color;
      else
         $opposite_dead = -1 ;

      $arr_xy = array( $start_x, $start_y );
      $stack = array( $arr_xy );
      $visited = array(); // marker if point already checked
      $visited[$start_x][$start_y] = $arr_xy;

      $has_toggle = is_array($toggled);
      if( $has_toggle )
         Board::toggle_xy($toggled, $start_x, $start_y);

      // scanning all directions starting at start-x/y building up a stack of adjacent points to check
      while( $arr_xy = array_shift($stack) )
      {
         list( $x, $y ) = $arr_xy;

         for( $dir=0; $dir < 4; $dir++) { // scan all directions: W N E S
            $new_x = $x + $this->dirx[$dir];
            $new_y = $y + $this->diry[$dir];

            if( ($new_x >= 0 && $new_x < $this->size) && ($new_y >= 0 && $new_y < $this->size) && !@$visited[$new_x][$new_y] )
            {
               $new_color = @$this->array[$new_x][$new_y];

               if( $new_color == $color || ( $companion_groups && $new_color == NONE ) )
               {
                  $stack[] = array( $new_x, $new_y, $color );
                  $visited[$new_x][$new_y] = array( $new_x, $new_y );
                  if( $has_toggle )
                     Board::toggle_xy($toggled, $new_x, $new_y);
               }
               elseif( $new_color == $opposite_dead )
               {
                  $this->toggle_marked_area( $new_x, $new_y, $marked, $toggled, $companion_groups);
               }
            }
         }
      }

      foreach( $visited as $x => $arr_y )
      {
         foreach( $arr_y as $y => $arr_xy )
         {
            if( $color == @$this->array[$x][$y] ) {
               if( !$has_toggle || @$toggled[$x][$y] == 1 )
               {
                  $marked[] = $arr_xy;
                  @$this->array[$x][$y] ^= OFFSET_MARKED;
               }
            }
         }
      }
   }//toggle_marked_area

   // static
   function toggle_xy( &$arr, $x, $y )
   {
      if( isset($arr[$x][$y]) )
         $arr[$x][$y]++;
      else
         $arr[$x][$y] = 1;
   }


   // If move update was interupted between two mysql queries, there may
   // be extra entries in the Moves and MoveMessages tables.
   // \internal
   function fix_corrupted_move_table( $gid)
   {
      $row= mysql_single_fetch( "board.fix_corrupted_move_table.moves: $gid",
         "SELECT Moves FROM Games WHERE ID=$gid" );
      if( !$row )
         error('internal_error', "board.fix_corrupted_move_table.moves($gid)");
      $Moves = $row['Moves'];

      $row = mysql_single_fetch( "board.fix_corrupted_move_table.max: $gid",
         "SELECT MAX(MoveNr) AS max FROM Moves WHERE gid=$gid" );
      if( !$row )
         error('internal_error', "board.fix_corrupted_move_table.max($gid)");
      $max_movenr= $row['max'];

      if($max_movenr == $Moves)
         return;

      if( $max_movenr != $Moves+1 )
         error('database_corrupted', "board.fix_corrupted_move_table.unfixable($gid)"); // Can't handle this type of problem

      ta_begin();
      {//HOT-section to fix Moves-table
         db_query( "board.fix_corrupted_move_table.delete_moves($gid)",
            "DELETE FROM Moves WHERE gid=$gid AND MoveNr=$max_movenr" );
         db_query( "board.fix_corrupted_move_table.delete_move_mess($gid)",
            "DELETE FROM MoveMessages WHERE gid=$gid AND MoveNr=$max_movenr" );

         Board::delete_cache_game_moves( "board.fix_corrupted_move_table.delete_moves($gid)", $gid );
         Board::delete_cache_game_move_messages( "board.fix_corrupted_move_table.delete_move_mess($gid)", $gid );
      }
      ta_end();
   }//fix_corrupted_move_table

   /*!
    * \brief interface-method on Board-class for GameSnapshot-class.
    * \return 0/1/2/3 for given board-pos x/y=0..size-1, 0=empty, 1=black, 2=white, 3=dead
    * \see GameSnapshot#make_game_snapshot()
    */
   function read_stone_value( $x, $y, $with_dead=true )
   {
      static $VAL_MAP = array(
            BLACK       => 1, // 01 (black)
            WHITE       => 2, // 10 (white)
            BLACK_DEAD  => 3, // 11 (dead-stone)
            WHITE_DEAD  => 3, // 11 (dead-stone)
         );

      $stone = (int)@$this->array[$x][$y];
      $result = (int)@$VAL_MAP[$stone]; // undef = 00 (empty-field)
      return ( !$with_dead && $result == 3 ) ? 0 : $result;
   }//read_stone_value

   /*!
    * \brief Returns String with moves to be passed to JavaScript-game-editor for building up game-tree.
    * \return space-separate string with moves, for syntax see GameEditor.parseMoves() in 'js/game-editor.js'
    * \note prisoners will be calculated, handicap is either done as SETUP or as MOVE
    */
   function make_js_game_moves()
   {
      $out = array();
      foreach( $this->js_moves as $arr )
      {
         list( $move_nr, $stone, $x, $y ) = $arr;
         if( $stone == BLACK )
            $color = 'b';
         elseif( $stone == WHITE )
            $color = 'w';
         else
            continue; // stone=NONE|MARKED_BY_BLACK/WHITE, x=POSX_RESIGN/TIMEOUT/SCORE

         if( $x == POSX_PASS )
            $val = '_P';
         else
            $val = number2sgf_coords($x,$y, $this->size);
         $out[] = "{$color}{$val}";
         //$out[] = "{$move_nr}.{$color}{$val}";
      }

      $result = implode(' ', $out);
      //error_log("make_js_game_moves({$this->size}) = [$result]");
      return $result;
   }//make_js_game_moves

} //class Board

?>
