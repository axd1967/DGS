<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Game";

require_once( 'include/classlib_goban.php' );
require_once( 'include/classlib_userconfig.php' ); // consts SMOOTH_EDGE

 /* Author: Jens-Uwe Gaspar */


 /*!
  * \file goban_handler_gfx.php
  *
  * \brief Class implementing GobanHandler to output Goban-object as graphical board.
  */


 /*!
  * \class GobanHandlerGfxBoard
  * \brief Goban-writer writing standard DGS (similar to that on the game page)
  */

if( !defined('EDGE_SIZE') )
   define('EDGE_SIZE', 10);

// see init_statics()
global $MAP_FORM_MARKERS, $MAP_BOX_MARKERS, $MAP_BOARDLINES; //PHP5
$MAP_FORM_MARKERS = NULL;
$MAP_BOX_MARKERS = NULL;
$MAP_BOARDLINES = NULL;

class GobanHandlerGfxBoard
{
   var $stone_size;
   var $coord_borders;
   var $woodcolor;

   var $imageAttribute; // e.g. "onClick=\"click('%s','%s')\"" ); // x,y

   var $size;
   var $result; // array

   /*! \brief Constructs GobanHandler for outputting DGS go-board. */
   function GobanHandlerGfxBoard()
   {
      GobanHandlerGfxBoard::init_statics();

      //TODO read from ConfigBoard/players_row
      $this->stone_size = 25;
      $this->coord_borders = 0;
      $this->woodcolor = 1;

      $this->imageAttribute = '';
   }

   // static, init in static-method to fix include-priority (avoids declaring of this file in "client" before classlib_goban.php)
   function init_statics()
   {
      global $MAP_BOARDLINES, $MAP_BOX_MARKERS, $MAP_FORM_MARKERS;
      if( is_null($MAP_BOX_MARKERS) )
      {
         $MAP_BOX_MARKERS = array(
            GOBM_BOX_B     => 'b',
            GOBM_BOX_W     => 'w',
            GOBM_BOX_GRN   => 'd',
            GOBM_BOX_RED   => 'g',
         );
      }

      if( is_null($MAP_FORM_MARKERS) )
      {
         $MAP_FORM_MARKERS = array(
            GOBM_CIRCLE    => 'c',
            GOBM_SQUARE    => 's',
            GOBM_TRIANGLE  => 't',
            GOBM_CROSS     => 'x',
         );
      }

      if( is_null($MAP_BOARDLINES) )
      {
         /*
          *  SE  SWE  SW             ul u ur
          *  NSE NWSE NSW     ->     el e er
          *  NE  NWE  NW             dl d dr
          *  0 , NS           ->     '', du
          */
         $MAP_BOARDLINES = array(
            GOBB_NORTH | GOBB_SOUTH | GOBB_WEST | GOBB_EAST => 'e',  // middle
            GOBB_NORTH | GOBB_SOUTH | GOBB_WEST             => 'er', // =W
            GOBB_NORTH | GOBB_SOUTH             | GOBB_EAST => 'el', // =E
            GOBB_NORTH | GOBB_SOUTH                         => '',   // supported, but can't be mixed
            GOBB_NORTH              | GOBB_WEST | GOBB_EAST => 'd',  // =N
            GOBB_NORTH              | GOBB_WEST             => 'dr',
            GOBB_NORTH                          | GOBB_EAST => 'dl',
            GOBB_NORTH                                      => '',   // unsupported
                         GOBB_SOUTH | GOBB_WEST | GOBB_EAST => 'u',  // =S
                         GOBB_SOUTH | GOBB_WEST             => 'ur',
                         GOBB_SOUTH             | GOBB_EAST => 'ul',
                         GOBB_SOUTH                         => '',   // unsupported
                                      GOBB_WEST | GOBB_EAST => '',   // unsupported
                                      GOBB_WEST             => '',   // unsupported
                                                  GOBB_EAST => '',   // unsupported
                                                          0 => '',   // empty
         );
      }
   }

   function setImageAttribute( $image_attr )
   {
      $this->imageAttribute = $image_attr;
   }


   /*! \brief Returns empty goban, because no graphical input supported. */
   function read_goban( $text )
   {
      return new Goban();
   }

   /*!
    * \brief (interface) Transforms given Goban-object into DGs go-board using single images.
    * \note keep in sync with Board::draw_board()
    */
   function write_goban( $goban )
   {
      global $base_path;
      $this->result = array(); // later concatenated as output-string

      $stone_size = $this->stone_size;
      $coord_width = floor($stone_size*31/25);
      $this->size = max( $goban->max_x, $goban->max_y );

      // init board-layout options
      $opts_coords = $goban->getOptionsCoords();

      $smooth_edge = ( ($this->coord_borders & SMOOTH_EDGE) && ($this->woodcolor < 10) );
      if( $smooth_edge )
      {
         $border_start = 140 - ( $opts_coords & GOBB_WEST ? $coord_width : 0 );
         $border_imgs = ceil( ($this->size * $stone_size - $border_start) / 150 ) - 1;
         $border_rem = $this->size * $stone_size - $border_start - 150 * $border_imgs;
         if( $border_imgs < 0 )
            $border_rem = $this->size * $stone_size;

         $edge_coord = '<td><img alt=" " height='.EDGE_SIZE.' src="'.$base_path.'images/blank.gif" width=' . $coord_width . "></td>\n";
         $edge_start = '<img alt=" " height='.EDGE_SIZE.' src="'.$base_path.'images/wood' . $this->woodcolor . '_' ;
         $edge_vert = '<img alt=" " height=' . $stone_size . ' width='.EDGE_SIZE.' src="'.$base_path.'images/wood' . $this->woodcolor . '_' ;
      }

      $coord_alt = '.gif" alt="';
      $coord_end = "\"></td>\n";
      if( $opts_coords & (GOBB_WEST | GOBB_EAST) )
      {
         $coord_start_number = "<td class=brdn><img class=brdn src=\"$base_path$stone_size/c";
      }
      if( $opts_coords & (GOBB_NORTH | GOBB_SOUTH) )
      {
         $coord_start_letter = "<td class=brdl><img class=brdl src=\"$base_path$stone_size/c";

         $s = ($opts_coords & GOBB_WEST ? 1 : 0 ) + ( $smooth_edge ? 1 : 0 );
         if( $s )
            $coord_left = "<td colspan=$s><img src=\"{$base_path}images/blank.gif\" width="
               . ( ( $opts_coords & GOBB_WEST ? $coord_width : 0 )
                  + ( $smooth_edge ? EDGE_SIZE : 0 ) )
               . " height=$stone_size alt=\" \"></td>\n";
         else
            $coord_left = '';

         $s = ($opts_coords & GOBB_EAST ? 1 : 0 ) + ( $smooth_edge ? 1 : 0 );
         if( $s )
            $coord_right = "<td colspan=$s><img src=\"{$base_path}images/blank.gif\" width="
               . ( ( $opts_coords & GOBB_EAST ? $coord_width : 0 )
                  + ( $smooth_edge ? EDGE_SIZE : 0 ) )
               . " height=$stone_size alt=\" \"></td>\n";
         else
            $coord_right = '';
      }


      // goban

      /**
       * style="background-image..." is not understood by old browsers like Netscape Navigator 4.0
       * meanwhile background="..." is not W3C compliant
       * so, for those old browsers, use the bgcolor="..." option
       **/
      global $woodbgcolors;
      if( $this->woodcolor > 10 )
         $woodstring = ' bgcolor="' . $woodbgcolors[$this->woodcolor - 10] . '"';
      else
         $woodstring = ' style="background-image:url('.$base_path.'images/wood' . $this->woodcolor . '.gif);"';

      /**
       * Some simple browsers (like Pocket PC IE or PALM ones) poorly
       * manage the CSS commands related to cellspacing and cellpadding.
       * Most of the time, this results in a 1 or 2 pixels added to the
       * cells size and is not so disturbing into normal tables.
       * But this is really annoying for the board cells.
       * So we keep the HTML commands here, even if deprecated.
       **/
      $cell_size_fix = ' border=0 cellspacing=0 cellpadding=0';

      // sprintf-args: (1) = optional name, (2) = optional additional attribute (e.g. for JavaScript)
      $blank_image = "<img%s alt=\".\" src=\"{$base_path}images/dot.gif\" width=\"$stone_size\" height=\"$stone_size\"%s>";

      $this->result[] = '<table class=Goban' . $woodstring . $cell_size_fix . '><tbody>';

      if( $opts_coords & GOBB_NORTH )
         $this->draw_coord_row( $coord_start_letter, $coord_alt, $coord_end,
                                $coord_left, $coord_right );

      if( $smooth_edge )
         $this->draw_edge_row( $goban, $edge_start.'u', $edge_coord,
                               $border_start, $border_imgs, $border_rem );

      for($rownr = $this->size, $y = 1; $y <= $goban->max_y; $rownr--, $y++ )
      {
         $out = '<tr>';

         if( $opts_coords & GOBB_WEST )
            $out .= $coord_start_number . $rownr . $coord_alt . $rownr .$coord_end;

         if( $smooth_edge )
            $out .= '<td>' . $edge_vert . "l.gif\"></td>\n";

         $letter = 'a'; // TODO for board-points
         for($colnr = 0, $x = 1; $colnr < $this->size; $colnr++, $x++ )
         {
            $arr = $goban->getValue( $x, $y );
            $image = $this->write_image( $x, $y, $arr[GOBMATRIX_VALUE], $arr[GOBMATRIX_LABEL] );
            if( $image )
               $out .= "<td class=brdx>$image</td>\n";
            else
            {
               $imgAttr = ($this->imageAttribute) ? ' '.sprintf( $this->imageAttribute, $x, $y ) : '';
               $out .= "<td class=brdx>" . sprintf( $blank_image, " name=\"x{$x}y{$y}\"", $imgAttr ) . "</td>\n";
            }

            if( ++$letter == 'i' ) $letter++;
         }

         if( $smooth_edge )
            $out .= '<td>' . $edge_vert . "r.gif\"></td>\n";

         if( $opts_coords & GOBB_EAST )
            $out .= $coord_start_number . $rownr . $coord_alt . $rownr .$coord_end;

         $out .= "</tr>\n";
         $this->result[] = $out;
      }//for

      if( $smooth_edge )
         $this->draw_edge_row( $goban, $edge_start.'d', $edge_coord,
                               $border_start, $border_imgs, $border_rem );

      if( $opts_coords & GOBB_SOUTH )
         $this->draw_coord_row( $coord_start_letter, $coord_alt, $coord_end,
                           $coord_left, $coord_right );

      $this->result[] = "</tbody></table>\n";

      return implode('', $this->result);
   } //write_goban

   function write_image( $x, $y, $value, $label='' )
   {
      if( ($value & (GOBB_BITMASK|GOBS_BITMASK|GOBO_HOSHI|GOBM_BITMASK)) == 0 )
         return '';

      // layers
      $lBoard = $value & GOBB_BITMASK;
      $lStone = $value & GOBS_BITMASK;
      $lHoshi = $value & GOBO_HOSHI;
      $lMarker = $value & GOBM_BITMASK;
      $isStoneBW = ($lStone == GOBS_BLACK || $lStone == GOBS_WHITE );
      $bLineType = $this->getBoardLineType($lBoard); // only mixeable

      $alt = '';
      $title = '';

      global $MAP_BOX_MARKERS, $MAP_FORM_MARKERS;
      // mapping and prioritize goban-layer-values to actual images available on DGS
      // starting with most special ... ending with most generalized images
      if( $lMarker == GOBM_NUMBER && $isStoneBW )
      {
         $type = ($lStone == GOBS_BLACK) ? 'b' : 'w';
         if( $label >= 1 && $label <= 500 )
            $type .= (int)$label;
      }
      elseif( $lMarker == GOBM_MARK && $isStoneBW )
      {
         $type = ($lStone == GOBS_BLACK) ? 'b' : 'w';
         $type .= 'm';
      }
      elseif( $lMarker == GOBM_BOX_B && $lStone == GOBS_WHITE )
      {
         $type = 'wb';
      }
      elseif( $lMarker == GOBM_BOX_W && $lStone == GOBS_BLACK )
      {
         $type = 'bw';
      }
      elseif( ($boxMarker = @$MAP_BOX_MARKERS[$lMarker]) != '' && $lStone == 0 && $bLineType != '' )
      {
         $type = $bLineType . $boxMarker;
      }
      elseif( ($formMarker = @$MAP_FORM_MARKERS[$lMarker]) != '' && $isStoneBW )
      {
         $type = ($lStone == GOBS_BLACK) ? 'b' : 'w';
         $type .= $formMarker;
      }
      elseif( ($formMarker = @$MAP_FORM_MARKERS[$lMarker]) != '' && $lStone == 0 && $lHoshi )
      {
         $type = 'h' . $formMarker;
      }
      elseif( $lMarker == 0 && $isStoneBW )
      {
         $type = ($lStone == GOBS_BLACK) ? 'b' : 'w';
      }
      elseif( $lMarker == 0 && $lHoshi )
      {
         $type = 'h';
      }
      elseif( $lMarker == GOBM_LETTER )
      {
         if( $label >= 'a' && $label <= 'z' )
            $type = 'l' . $label;
      }
      elseif( ($formMarker = @$MAP_FORM_MARKERS[$lMarker]) != '' && $lStone == 0 && $bLineType != '' )
      {
         $type = $bLineType . $formMarker;
      }
      elseif( $lMarker == 0 && $lStone == 0 )
      {
         $type = $this->getBoardLineType($lBoard, false);
      }
      else
      {// unknown mapping
         $type = '';
      }

      if( $type != '' )
      {
         global $base_path;
         $title_str = ($title != '') ? " title=\"$title\"" : '';
         $imgAttr = ($this->imageAttribute) ? ' '.sprintf( $this->imageAttribute, $x, $y ) : '';
         return "<img name=\"x{$x}y{$y}\" class=brdx src=\"{$base_path}{$this->stone_size}/$type.gif\" alt=\"$alt\"$title$imgAttr>";
      }
      else
         return '';
   } //write_image

   function getBoardLineType( $board_lines, $mixed=true )
   {
      $board_lines &= GOBB_BITMASK;
      if( !$mixed )
      {
         if( $board_lines == (GOBB_NORTH|GOBB_SOUTH) )
            return 'du';
      }

      global $MAP_BOARDLINES;
      $bl_type = @$MAP_BOARDLINES[$board_lines];
      return $bl_type;
   }

   // keep in sync with Board
   function draw_coord_row( $coord_start_letter, $coord_alt, $coord_end, $coord_left, $coord_right )
   {
      $out = "<tr>\n";

      if( $coord_left )
         $out .= $coord_left;

      $colnr = 1;
      $letter = 'a';
      while( $colnr <= $this->size )
      {
         $out .= $coord_start_letter . $letter . $coord_alt . $letter . $coord_end;
         $colnr++;
         $letter++; if( $letter == 'i' ) $letter++;
      }

      if( $coord_right )
         $out .= $coord_right;

      $out .= "</tr>\n";
      $this->result[] = $out;
   }

   // keep in sync with Board
   function draw_edge_row( $goban, $edge_start, $edge_coord, $border_start, $border_imgs, $border_rem )
   {
      $out = "<tr>\n";

      $opts_coords = $goban->getOptionsCoords();

      if( $opts_coords & GOBB_WEST )
         $out .= $edge_coord;

      $out .= '<td>' . $edge_start . 'l.gif" width='.EDGE_SIZE.'>' . "</td>\n";

      $out .= '<td colspan=' . $this->size . ' width=' . $this->size*$this->stone_size . '>';

      if( $border_imgs >= 0 )
         $out .= $edge_start . '.gif" width=' . $border_start . '>';
      for($i=0; $i<$border_imgs; $i++ )
         $out .= $edge_start . '.gif" width=150>';
      $out .= $edge_start . '.gif" width=' . $border_rem . '>';

      $out .= "</td>\n" . '<td>' . $edge_start . 'r.gif" width='.EDGE_SIZE.'>' . "</td>\n";

      if( $opts_coords & GOBB_EAST )
         $out .= $edge_coord;

      $out .= "</tr>\n";
      $this->result[] = $out;
   }

   // keep in sync with Board
   // (static)
   function style_string( $stone_size )
   {
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

} //end 'GobanHandlerGfxBoard'

?>
