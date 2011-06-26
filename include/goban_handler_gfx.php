<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Goban";

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
global $MAP_FORM_MARKERS, $MAP_TERRITORY_MARKERS, $MAP_BOARDLINES; //PHP5
$MAP_FORM_MARKERS = NULL;
$MAP_TERRITORY_MARKERS = NULL;
$MAP_BOARDLINES = NULL;

class GobanHandlerGfxBoard
{
   var $rawtext;

   var $stone_size;
   var $coord_borders; // use smooth-edge
   var $woodcolor;
   var $imageAttribute; // e.g. "onClick=\"click('%s','%s')\"" ); // x,y
   var $enable_id; // add CSS-id on board <td>-cells

   var $goban;
   var $result; // array

   /*! \brief Constructs GobanHandler for outputting DGS go-board. */
   function GobanHandlerGfxBoard( $rawtext='' )
   {
      GobanHandlerGfxBoard::init_statics();

      $this->rawtext = $rawtext;
      $this->enable_id = false;

      global $player_row;
      if( @$player_row['Stonesize'] )
      {
         $this->stone_size = $player_row['Stonesize'];
         $this->coord_borders = $player_row['Boardcoords'];
         $this->woodcolor = $player_row['Woodcolor'];
      }
      else // defaults
      {
         $this->stone_size = 25;
         $this->coord_borders = 0; // use smooth-edge
         $this->woodcolor = 1;
      }

      $this->imageAttribute = '';
   }

   // static, init in static-method to fix include-priority (avoids declaring of this file in "client" before classlib_goban.php)
   function init_statics()
   {
      global $MAP_BOARDLINES, $MAP_TERRITORY_MARKERS, $MAP_FORM_MARKERS;
      if( is_null($MAP_TERRITORY_MARKERS) )
      {
         $MAP_TERRITORY_MARKERS = array(
            GOBM_TERR_B       => 'b',
            GOBM_TERR_W       => 'w',
            GOBM_TERR_NEUTRAL => 'd',
            GOBM_TERR_DAME    => 'g',
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
   }//init_statics

   function setImageAttribute( $image_attr )
   {
      $this->imageAttribute = $image_attr;
   }


   /*! \brief Returns empty goban, because no graphical input supported. */
   function read_goban( $text )
   {
      return new Goban(); // unsupported
   }

   /*!
    * \brief (interface) Transforms given Goban-object into DGS go-board using single images.
    * \param $skeleton if true create only skeleton (used for JavaScript-based game-editor)
    * \note keep in sync with Board::draw_board(), though this implementation has some specialities!
    */
   function write_goban( $goban, $skeleton=false )
   {
      global $base_path;
      $this->goban = $goban;
      $this->result = array(); // later concatenated as output-string

      $size_x = $this->goban->max_x;
      $stone_size = $this->stone_size;
      $coord_width = floor($stone_size*31/25);
      $table_width = $size_x * $stone_size;

      // init board-layout options
      $opts_coords = ($skeleton) ? 0 : $goban->getOptionsCoords();
      $smooth_edge = ($skeleton) ? 0 : ( ($this->coord_borders & SMOOTH_EDGE) && ($this->woodcolor < 10) );
      if( $smooth_edge )
      {
         $border_start = 140 - ( $opts_coords & GOBB_WEST ? $coord_width : 0 );
         $border_imgs = ceil( ($size_x * $stone_size - $border_start) / 150 ) - 1;
         $border_rem = $size_x * $stone_size - $border_start - 150 * $border_imgs;
         if( $border_imgs < 0 )
            $border_rem = $size_x * $stone_size;

         $edge_coord = '<td><img alt=" " height='.EDGE_SIZE.' src="'.$base_path.'images/blank.gif" width=' . $coord_width . "></td>\n";
         $edge_start = '<img alt=" " height='.EDGE_SIZE.' src="'.$base_path.'images/wood' . $this->woodcolor . '_' ;
         $edge_vert = '<img alt=" " height=' . $stone_size . ' width='.EDGE_SIZE.' src="'.$base_path.'images/wood' . $this->woodcolor . '_' ;
      }

      $add_width_west = ( $opts_coords & GOBB_WEST ? $coord_width : 0 ) + ( $smooth_edge ? EDGE_SIZE : 0 );
      $add_width_east = ( $opts_coords & GOBB_EAST ? $coord_width : 0 ) + ( $smooth_edge ? EDGE_SIZE : 0 );
      $table_width += $add_width_west + $add_width_east;

      $coord_alt = '.gif" alt="';
      $coord_end = "\"></td>\n";
      if( $opts_coords & (GOBB_WEST | GOBB_EAST) )
         $coord_start_number = "<td class=brdn><img class=brdn src=\"$base_path$stone_size/c";
      if( $opts_coords & (GOBB_NORTH | GOBB_SOUTH) )
      {
         $coord_start_letter = "<td class=brdl><img class=brdl src=\"$base_path$stone_size/c";

         $s = ( $opts_coords & GOBB_WEST ? 1 : 0 ) + ( $smooth_edge ? 1 : 0 );
         $coord_left = ( $s )
            ? "<td colspan=$s><img src=\"{$base_path}images/blank.gif\" width=$add_width_west height=$stone_size alt=\" \"></td>\n"
            : '';

         $s = ( $opts_coords & GOBB_EAST ? 1 : 0 ) + ( $smooth_edge ? 1 : 0 );
         $coord_right = ( $s )
            ? "<td colspan=$s><img src=\"{$base_path}images/blank.gif\" width=$add_width_east height=$stone_size alt=\" \"></td>\n"
            : '';
      }

      $borders = $opts_coords;
      $start_col = 0;
      if( ($goban->size_x > $goban->max_x && !($borders & GOBB_WEST)) )
         $start_col = $goban->size_x - $goban->max_x;

      $start_row = $goban->size_y;
      if( ($goban->size_y > $goban->max_y && !($borders & GOBB_NORTH)) || ($goban->size_y < $goban->max_y ) )
         $start_row = $goban->max_y;


      // ---------- Goban ------------------------------------------------

      /**
       * style="background-image|color..." is not understood by old browsers like Netscape Navigator 4.0
       * meanwhile background="..." is not W3C compliant
       * so, for those old browsers, use the bgcolor="..." option
       **/
      global $woodbgcolors;
      $styles = array( "width:{$table_width}px;" );
      if( $this->woodcolor > 10 )
      {
         $bcol = $woodbgcolors[$this->woodcolor - 10];
         $woodstring = ' bgcolor="' . $bcol . '"';
         $styles[] = "background-color:$bcol;";
      }
      else
      {
         $woodstring = '';
         $styles[] = "background-image:url({$base_path}images/wood{$this->woodcolor}.gif);";
      }

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

      $table_id = ($this->enable_id) ? ' id=Goban' : '';
      $this->result[] = "<table{$table_id} class=Goban" . $woodstring . $cell_size_fix
         . (count($styles) ? ' style="' . implode(' ', $styles) . '"': '') . '><tbody>';

      if( $opts_coords & GOBB_NORTH )
         $this->draw_coord_row( $start_col, $coord_start_letter, $coord_alt, $coord_end, $coord_left, $coord_right );

      if( $smooth_edge )
         $this->draw_edge_row( $goban, $edge_start.'u', $edge_coord, $border_start, $border_imgs, $border_rem );

      for( $rownr = $start_row, $y = 1; !$skeleton && $y <= $goban->max_y; $rownr--, $y++ )
      {
         $out = '<tr>';

         if( $opts_coords & GOBB_WEST )
            $out .= $coord_start_number . $rownr . $coord_alt . $rownr .$coord_end;
         if( $smooth_edge )
            $out .= '<td>' . $edge_vert . "l.gif\"></td>\n";

         for( $x = 1; $x <= $goban->max_x; $x++ )
         {
            $arr = $goban->getValue( $x, $y );
            $cell_id = ($this->enable_id)
               ? sprintf( ' id=%s', number2sgf_coords( $x-1, $y-1, $goban->size_x, $goban->size_y ) )
               : '';

            $link = ( (string)$arr[GOBMATRIX_LABEL] != '' ) ? $goban->getLink($arr[GOBMATRIX_LABEL]) : null;
            $background = ($link) ? ' BoardLink' : '';

            $image = $this->write_image( $x, $y, $arr[GOBMATRIX_VALUE], $arr[GOBMATRIX_LABEL], $link );
            if( $image )
               $out .= "<td{$cell_id} class=\"brdx{$background}\">$image</td>\n";
            else
            {
               $imgAttr = ($this->imageAttribute) ? ' '.sprintf( $this->imageAttribute, $x, $y ) : '';
               $img = sprintf( $blank_image, " name=\"x{$x}y{$y}\"", $imgAttr );
               $out .= "<td{$cell_id} class=\"brdx{$background}\">$img</td>\n";
            }
         }//for x

         if( $smooth_edge )
            $out .= '<td>' . $edge_vert . "r.gif\"></td>\n";
         if( $opts_coords & GOBB_EAST )
            $out .= $coord_start_number . $rownr . $coord_alt . $rownr .$coord_end;

         $out .= "</tr>\n";
         $this->result[] = $out;
      }//for y

      if( $smooth_edge )
         $this->draw_edge_row( $goban, $edge_start.'d', $edge_coord, $border_start, $border_imgs, $border_rem );

      if( $opts_coords & GOBB_SOUTH )
         $this->draw_coord_row( $start_col, $coord_start_letter, $coord_alt, $coord_end, $coord_left, $coord_right );

      $this->result[] = "</tbody></table>\n";

      $title_div = ( !$skeleton && (string)$goban->BoardTitle != '' )
         ? "<div class=\"Title\">" . make_html_safe($goban->BoardTitle, true) . "</div>\n"
         : '';
      $text_block = ( !$skeleton && (string)$goban->BoardText != '' )
         ? "<br>\n" . make_html_safe($goban->BoardText, true)
         : '';

      if( $skeleton )
         $goban_str = '';
      else
      {
         $goban_str = $this->build_rawtext()
            . ( $goban->BoardTextInline ? $text_block : '' )
            . "<div class=\"GobanEnd\"></div>\n"
            . ( $goban->BoardTextInline ? '' : $text_block );
      }

      return '<div class="GobanGfx" style="width:'.$table_width.'px;"><div class="Board">' . implode('', $this->result) . "</div>"
         . $title_div . "</div>\n"
         . $goban_str
         //. "<br><hr><pre>" . $goban->to_string() . "</pre>\n"; // for debugging
         ;
   }//write_goban

   function write_image( $x, $y, $value, $label='', $link=null )
   {
      if( !($value & (GOBB_BITMASK|GOBS_BITMASK|GOBO_HOSHI|GOBM_BITMASK)) )
         return ''; // TODO bug? set everything to EMPTY board-cell

      // layers
      $lBoard = $value & GOBB_BITMASK;
      $lStone = $value & GOBS_BITMASK;
      $lHoshi = $value & GOBO_HOSHI;
      $lMarker = $value & GOBM_BITMASK;
      $isStoneBW = ($lStone == GOBS_BLACK || $lStone == GOBS_WHITE );
      $bLineType = $this->getBoardLineType($lBoard); // only mixable

      $alt = '';

      global $MAP_TERRITORY_MARKERS, $MAP_FORM_MARKERS;
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
      elseif( $lMarker == GOBM_TERR_B && $lStone == GOBS_WHITE )
      {
         $type = 'wb';
      }
      elseif( $lMarker == GOBM_TERR_W && $lStone == GOBS_BLACK )
      {
         $type = 'bw';
      }
      elseif( ($territoryMarker = @$MAP_TERRITORY_MARKERS[$lMarker]) != '' && $lStone == 0 && $bLineType != '' )
      {
         $type = $bLineType . $territoryMarker;
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
         $imgAttr = ($this->imageAttribute) ? ' '.sprintf( $this->imageAttribute, $x, $y ) : '';
         $out = "<img name=\"x{$x}y{$y}\" class=brdx src=\"{$base_path}{$this->stone_size}/$type.gif\" alt=\"$alt\"$imgAttr>";
         if( !is_null($link) )
            $out = "<a href=\"$link\" target=\"_blank\">$out</a>";
         return $out;
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
      return @$MAP_BOARDLINES[$board_lines];
   }//getBoardLineType

   // keep in sync with Board
   function draw_coord_row( $start_val, $coord_start_letter, $coord_alt, $coord_end, $coord_left, $coord_right )
   {
      $out = "<tr>\n";

      if( $coord_left )
         $out .= $coord_left;

      $letter = chr(ord('a') + $start_val);
      for( $colnr=1; $colnr <= $this->goban->max_x; $colnr++ )
      {
         if( $letter == 'i' ) $letter++;
         $out .= $coord_start_letter . $letter . $coord_alt . $letter . $coord_end;
         $letter++;
      }

      if( $coord_right )
         $out .= $coord_right;

      $out .= "</tr>\n";
      $this->result[] = $out;
   }//draw_coord_row

   // keep in sync with Board
   function draw_edge_row( $goban, $edge_start, $edge_coord, $border_start, $border_imgs, $border_rem )
   {
      $out = "<tr>\n";

      $size_x = $this->goban->max_x;
      $opts_coords = $goban->getOptionsCoords();

      if( $opts_coords & GOBB_WEST )
         $out .= $edge_coord;

      $out .= '<td>' . $edge_start . 'l.gif" width='.EDGE_SIZE.'>' . "</td>\n";

      $out .= '<td colspan=' . $size_x . ' width=' . $size_x * $this->stone_size . '>';

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
   }//draw_edge_row

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
   }//style_string

   /*! \brief (Ex06) Returns raw-text if URL-arg raw=1 or DBG_TEST bit #1 is set, though raw=0 overrules DBG_TEST. */
   function build_rawtext()
   {
      $arg_raw = ( isset($_REQUEST['raw']) ) ? (int)@$_REQUEST['raw'] : null;
      $orig = preg_replace("/<br>/i", "\n", $this->rawtext);
      return ( (is_null($arg_raw) && (DBG_TEST&2) ) || $arg_raw )
         ? "<div class=\"GobanRaw\"><pre>" . basic_safe($orig) . "</pre></div>\n"
         : '';
   }//build_rawtext

} //end 'GobanHandlerGfxBoard'

?>
