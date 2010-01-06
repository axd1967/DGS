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

require_once( 'include/coords.php' );
require_once( 'include/goban_handler_sl.php' );
require_once( 'include/goban_handler_gfx.php' );

 /* Author: Jens-Uwe Gaspar */


 /*!
  * \file classlib_goban.php
  *
  * \brief Classes to support formatting of DGS go-boards.
  */


// register of all known GobanHandlers, each must implement the interface-methods
// - constructor( $args_map )
// - Goban  read_goban( $text )
// - String write_goban( Goban )
global $ARR_GOBAN_HANDLERS_READER; //PHP5
$ARR_GOBAN_HANDLERS_READER = array(
   // uppercase(igoban_type) => GobanHandler-class
   'SL1' => 'GobanHandlerSL1',
);

global $ARR_GOBAN_HANDLERS_WRITER; //PHP5
$ARR_GOBAN_HANDLERS_WRITER = array(
   // uppercase(Goban-Writer-type) => GobanHandler-class
   'GFX' => 'GobanHandlerGfxBoard',
);

 /*!
  * \class MarkupHandlerGoban
  * \brief Static helper class managing parsing and formatting of <igoban...>-tag.
  * \note TODO later integrate <goban...>-tag as well (integrating GoDiagram.php)
  */
class MarkupHandlerGoban
{

   function replace_igoban_tags( $text )
   {
      //<igoban [t|type=]type [...]> => show inline-goban
      return preg_replace( "/<igoban +([\w\s=]+) *>(.*?)<\/igoban *>/ise",
                           "MarkupHandlerGoban::parse_igoban('\\1','\\2','GFX')", $text );
   }

   /*!
    * \brief Parses <igoban $attbs>..</igoban> tags into display of a go-board using DGS-images.
    * \param $attbs attribute-string of <igoban>-tag with syntax, separated by spaces: (k=v|v)*
    * \param $text payload between start- and end-igoban-tags, can include LFs
    */
   function parse_igoban( $attbs, $text, $writer_type='GFX' )
   {
      // attributes: key=value
      $map_args = MarkupHandlerGoban::attribute_split( $attbs );

      // parse igoban-type (1st attribute); no type-default
      $igoban_type = MarkupHandlerGoban::extract_igoban_type( $map_args );
      if( is_null($igoban_type) )
         return "<igoban $attbs>$text</igoban>"; // orig TODO

      global $ARR_GOBAN_HANDLERS_READER, $ARR_GOBAN_HANDLERS_WRITER;
      $readerClass = @$ARR_GOBAN_HANDLERS_READER[strtoupper($igoban_type)];
      $writerClass = @$ARR_GOBAN_HANDLERS_WRITER[strtoupper($writer_type)];
      if( !$readerClass )
         error('invalid_args', "Goban.parse_igoban.check.igoban_type($igoban_type)");
      if( !$writerClass )
         error('invalid_args', "Goban.parse_igoban.check.writer_type($writer_type)");

      $goban_reader = new $readerClass( $map_args );
      $goban = $goban_reader->read_goban( $text );

      $goban_writer = new $writerClass();
      $result = $goban_writer->write_goban( $goban );

      return $result;
   }

   // Extracts type from: <igoban [t|type=]...>
   function extract_igoban_type( $args )
   {
      if( isset($args['#1']) ) // first arg
         return $args['#1'];
      elseif( isset($args['type']) )
         return $args['type'];
      elseif( isset($args['t']) )
         return $args['t'];
      else
         return null;
   }

   /*!
    * \brief Returns attribute-string parsed into map( key => value ).
    * \note: map additionally contains entries: '#n' => value, with '#n' arg-pos in attbs starting with 1
    */
   function attribute_split( $attbs )
   {
      $arr = preg_split( "/\s+/", trim($attbs) );
      $result = array();
      $arg_idx = 0;
      foreach( $arr as $part )
      {
         if( $part === '' ) continue;
         $arg_idx++;
         if( preg_match( "/^([^=]+)=(\S*)$/", $part, $matches) )
            $result["#$arg_idx"] = $result[$matches[1]] = $matches[2];
         else
            $result["#$arg_idx"] = $part;
      }
      return $result;
   }

} //end 'MarkupHandlerGoban'




// Goban (4 bits, single bits):
// board-lines at board-position (x/y), empty=0, MID=N|S|W|E
define('GOBB_BITMASK', 0x000F);
define('GOBB_EMPTY', 0x0000); // empty layer = empty intersection
define('GOBB_NORTH', 0x0001); // north-line
define('GOBB_SOUTH', 0x0002); // south-line
define('GOBB_WEST', 0x0004); // west-line
define('GOBB_EAST', 0x0008); // east-line
define('GOBB_MID', (GOBB_NORTH|GOBB_SOUTH|GOBB_WEST|GOBB_EAST)); // middle board-position

// Goban (special other layer)
define('GOBO_HOSHI', 0x0080); // hoshi-bit

// Goban (3 bits, combined bitset): stone-color
define('GOBS_BITMASK', 0x0070);
define('GOBS_EMPTY', 0x0000); // empty layer = no stone
define('GOBS_BLACK', 0x0010); // black
define('GOBS_WHITE', 0x0020); // white
//define('GOBS_..', 0x0030..0x0070); // reserved


// Goban (4 bits, single + combined bitset): markers
define('GOBM_BITMASK',  0x0F00);
define('GOBM_EMPTY',    0x0000); // empty layer = no marker
// - mutual exclusive markers (no bitmask)
define('GOBM_NUMBER',   0x0100); // 1..n
define('GOBM_LETTER',   0x0200); // a..z
define('GOBM_MARK',     0x0300); // double circle
define('GOBM_CIRCLE',   0x0400);
define('GOBM_SQUARE',   0x0500); // unfilled
define('GOBM_TRIANGLE', 0x0600); //
define('GOBM_CROSS',    0x0700); //
define('GOBM_BOX_W',    0x0800); // white (filled) box
define('GOBM_BOX_B',    0x0900); // black (filled) box
define('GOBM_BOX_GRN',  0x0A00); // green (filled) box
define('GOBM_BOX_RED',  0x0B00); // red (filled) box
//define('GOBM_..', 0x0B00..0x0F00); // reserved

// internal
define('GOBMATRIX_VALUE', 'V' ); // layer-value
define('GOBMATRIX_LABEL', 'L' ); // label for GOBM_NUMBER|LETTER

 /*!
  * \class Goban
  * \brief Container for storing a board used for go-diagrams.
  *
  * \note A position on a goban consists of four "layers":
  *       1. the background (image or nothing), global config
  *       2. the board lines
  *       3. stone or no-stone (colored)
  *       4. markers
  * The layers can be independently used.
  * Rendering a Goban-object need to choose a "representation" for all the combined layers.
  *    This can result in a mapping where not all combinations make sense or
  *    can be mapped to something, and so can lead to an loss of information.
  */
class Goban
{
   /*! \brief bitmask using GOBB_NORTH|SOUTH|WEST|EAST enabling coordinates on that side of the go-board. */
   var $opts_coords;
   /*!
    * \brief Board matrix
    * \note Format (x/y=1..):
    *    [y][x] = int-bitmask |
    *           = array( layer => int-bitmask, label => number|letter )
    */
   var $matrix;
   var $max_x;
   var $max_y;
   var $size;

   /*! \brief Constructs Goban. */
   function Goban()
   {
      $this->matrix = array();
      $this->opts_coords = 0;
      $this->max_x = $this->max_y = $this->size = 1;
   }

   function to_string()
   {
      $out = array();
      $out[] = sprintf("opts_coords=0x%x", $this->opts_coords);
      for($y=1; $y < $this->max_y; $y++)
      {
         for($x=1; $x < $this->max_x; $x++)
         {
            $arr = $this->getValue( $x, $y );
            $out[] = sprintf("[%2d,%2d]: V=%03x L=%s", $x,$y, $arr[GOBMATRIX_VALUE], $arr[GOBMATRIX_LABEL] );
         }
      }
      return "Goban({$this->max_x}x{$this->max_y},size={$this->size})={\n  ".implode("\n  ", $out)." }\n\n";
   }


   function setOptionsCoords( $coords )
   {
      $this->opts_coords = ($coords & GOBB_BITMASK);
   }

   function getOptionsCoords()
   {
      return $this->opts_coords;
   }

   function setSize( $size )
   {
      $this->size = $size;
   }


   // internal, overwriting layer-value
   function setValue( $x, $y, $value, $label=NULL )
   {
      $this->max_x = max( $x, $this->max_x );
      $this->max_y = max( $y, $this->max_y );

      if( is_array($value) )
         $this->matrix[$y][$x] = $value;
      elseif( !is_numeric($value) )
         error('invalid_args', "Goban.setValue($x,$y,$value)");
      elseif( is_null($label) )
         $this->matrix[$y][$x] = $value; // optimization to avoid too many object-instances
      else
         $this->matrix[$y][$x] = array( GOBMATRIX_VALUE => $value, GOBMATRIX_LABEL => $label );
   }

   function hasValue( $x, $y )
   {
      return isset($this->matrix[$y][$x]);
   }

   // internal, always return non-null layer-value-array
   function getValue( $x, $y )
   {
      $arrval = @$this->matrix[$y][$x];
      if( is_array($arrval) )
         $result = $arrval;
      elseif ( is_numeric($arrval) )
         $result = array( GOBMATRIX_VALUE => $arrval, GOBMATRIX_LABEL => '' );
      else
         $result = array( GOBMATRIX_VALUE => 0, GOBMATRIX_LABEL => '' );
      //error_log("Goban::getValue($x,$y): V=[".sprintf("%x",$result[GOBMATRIX_VALUE])."], L=[".sprintf("%x",$result[GOBMATRIX_LABEL])."]");
      return $result;
   }


   function setBoardLines( $x, $y, $board_value )
   {
      $upd_arr = $this->getValue($x,$y);
      $upd_arr[GOBMATRIX_VALUE] =
         ($upd_arr[GOBMATRIX_VALUE] & ~GOBB_BITMASK) | ($board_value & GOBB_BITMASK);
      $this->setValue( $x, $y, $upd_arr );
   }

   function getBoardLines( $x, $y )
   {
      $current_arr = $this->getValue($x,$y);
      return ($current_arr[GOBMATRIX_VALUE] & GOBB_BITMASK);
   }

   function eraseBoardPoint( $x, $y )
   {
      $this->setBoardLines( $x, $y, 0 );

      // adjust neighbour points
      if( $x > 1 )
         $this->clearBoardLinesBit( $x-1, $y, GOBB_EAST );
      if( $this->hasValue( $x+1, $y ) )
         $this->clearBoardLinesBit( $x+1, $y, GOBB_WEST );
      if( $y > 1 )
         $this->clearBoardLinesBit( $x, $y-1, GOBB_SOUTH );
      if( $this->hasValue( $x, $y+1 ) )
         $this->clearBoardLinesBit( $x, $y+1, GOBB_NORTH );
   }

   function clearBoardLinesBit( $x, $y, $value )
   {
      $value &= GOBB_BITMASK;
      $upd_arr = $this->getValue($x,$y);
      $upd_arr[GOBMATRIX_VALUE] &= ~$value;
      $this->setValue( $x, $y, $upd_arr );
   }


   function setHoshi( $x, $y, $hoshi_set )
   {
      $upd_arr = $this->getValue($x,$y);
      $upd_arr[GOBMATRIX_VALUE] =
         ($upd_arr[GOBMATRIX_VALUE] & ~GOBO_HOSHI) | ( (bool)$hoshi_set ? GOBO_HOSHI : 0 );
      $this->setValue( $x, $y, $upd_arr );
   }

   // bool getHoshi(x,y)
   function getHoshi( $x, $y )
   {
      $current_arr = $this->getValue($x,$y);
      return (bool)($current_arr[GOBMATRIX_VALUE] & GOBO_HOSHI);
   }


   function setStone( $x, $y, $stone_value )
   {
      $upd_arr = $this->getValue($x,$y);
      $upd_arr[GOBMATRIX_VALUE] = ($upd_arr[GOBMATRIX_VALUE] & ~GOBS_BITMASK)
            | ($stone_value & GOBS_BITMASK);
      $this->setValue( $x, $y, $upd_arr );
   }

   function getStone( $x, $y )
   {
      $current_arr = $this->getValue($x,$y);
      return ($current_arr[GOBMATRIX_VALUE] & GOBS_BITMASK);
   }


   function setMarker( $x, $y, $marker_value, $label='' )
   {
      $marker_value &= GOBM_BITMASK;
      $upd_arr = $this->getValue($x,$y);
      $upd_arr[GOBMATRIX_VALUE] =
         ($upd_arr[GOBMATRIX_VALUE] & ~GOBM_BITMASK) | $marker_value;
      $upd_arr[GOBMATRIX_LABEL] =
         ($marker_value == GOBM_LETTER || $marker_value == GOBM_NUMBER) ? $label : '';
      $this->setValue( $x, $y, $upd_arr );
   }

   function getMarker( $x, $y, $with_label=false )
   {
      $current_arr = $this->getValue($x,$y);
      $value = ($current_arr[GOBMATRIX_VALUE] & GOBM_BITMASK);
      return ( $with_label ) ? array( $value, $current_arr[GOBMATRIX_LABEL] ) : $value;
   }

   function getMarkerLabel( $x, $y )
   {
      $current_arr = $this->getValue($x,$y);
      return $current_arr[GOBMATRIX_LABEL];
   }

   //TODO fix for width <> height
   function makeBoard( $width, $height, $withHoshi=true )
   {
      if( $width < 2 || $height < 2 )
         error('invalid_args', "Goban.makeBoard.check($width,$height)");

      for( $y=1; $y <= $width; $y++)
      {
         $board_lines = GOBB_MID;
         if( $y == 1 )
            $board_lines &= ~GOBB_NORTH;
         elseif( $y == $height )
            $board_lines &= ~GOBB_SOUTH;

         for( $x=1; $x <= $height; $x++)
         {
            $val = $board_lines;
            if( $x == 1 )
               $val &= ~GOBB_WEST;
            elseif( $x == $width )
               $val &= ~GOBB_EAST;

            if( $withHoshi && is_hoshi($x-1, $y-1, $width, $height) )
               $val |= GOBO_HOSHI;

            $this->matrix[$y][$x] = $val;
         }
      }

      $this->max_x = $width;
      $this->max_y = $height;
   }

} //end 'Goban'

?>
