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

 /* Author: Jens-Uwe Gaspar */


 /*!
  * \file goban_handler_sl.php
  *
  * \brief Class implementing GobanHandler for format "Sensei's Library" to read and write Goban-object.
  */


 /*!
  * \class GobanHandlerSL1
  * \brief Goban-reader and Goban-writer for igoban-type [SL1]: Sensei's Library syntax version 1
  *
  * <pre>
  *   SL1-format (Sensei's Library), ref: http://senseis.xmp.net/?HowDiagramsWork
  *   irregaular boards, ref: http://senseis.xmp.net/?CreatingIrregularGobansWithWiki
  *
  *   each line starts with '$$' to indicate a go-diagram
  *   a space has meaning
  *   title-line := first line
  *     format of title-line (regex): $$(B|W)?c?(size)?(m\d+)?(title)?
  *     - B|W = gives start of color for numbered stones: W -> first numbered stone is white
  *     - c = enables coordinates (of type A1)
  *     - size = auto-size for board (influence edges)
  *     - m\d+ = gives moves-start-number (default: 1); m7 -> start with 7
  *     - title-text put under generated board
  *
  *   . = empty intersection, ',' = hoshi
  *   | + - = forming edges, '-' = creates empty intersection if not at edge
  *
  *   markup in diagram, in text with description (transformed to image):
  *        Diagram      Text      Description
  *        X O          BO WO     black stone, white stone
  *        B|W0..9      B|W1.100  numbered black|white stones, W0/B0=W10/B10
  *        B W          BC WC     black|white stone with circle
  *        # @          BS WS     black|white stone with square
  *        Y Q          BT WT     black|white stone with triangle
  *        Z P          BX WX     black|white stone with cross
  *        C            EC        circle
  *        S            ES        square
  *        T            ET        triangle
  *        M            EX        cross
  *        a..z         a..z      letters on empty intersection
  *
  *   stone with numbers
  *      - 0 = stone with number 10 in diagram
  *      - default: odd  numbers = black numbered stones
  *                 even numbers = white numbered stones
  *        color-order (default) can be given/overriden in title-line with (B|W)
  *      - move-start-number in title-line: (m\d+)
  *      - "A at B" in title line includes "move into SGF"
  *
  *   text below the go-board: //TODO doc + impl
  * </pre>
  *
  * \note DGS-exceptions to SL-format:
  *   - Ex01: lines can start with white-spaces
  *   - Ex02: line-prefix '$$' can be omitted
  *   - Ex03: chars "'" and '\' are not allowed in text
  *   - Ex04: easier coordinate-usage (checking on + | -)
  *
  * TODO
  * - fix parsing and handling of coordinates-stuff
  * - fix setting of hoshi-points on board
  * - fix parsing of "border"-stuff
  * - handle "unknown parse-items"
  * - add parsing of title
  * - add parsing of text/links
  * - add links in graphic-board (good for DGS ?)
  * - implement write_goban()
  */
class GobanHandlerSL1
{
   var $args; // key => val, #1..n => val
   var $text; // text to parse
   var $goban; // Goban

   var $showCoordinates; // bool
   var $boardSize; // NULL or int
   var $boardTitle; // NULL or string
   var $startMoveBlack; // bool
   var $startMoveNumber; // int

   var $borders; // GOBB_N/S/W/E (position)
   var $lines; // lines to parse
   var $lpos;  // line-pos to parse 0..
   var $ypos;  // board-ypos 1..
   var $max_x;
   var $max_y;
   var $erasePoints; // arr( x,y )

   /*! \brief Constructs GobanHandler for igoban-type SL1. */
   function GobanHandlerSL1( $arr_args=null )
   {
      $this->args = (is_array($arr_args)) ? array() : $arr_args;
      $this->text = '';
      $this->goban = new Goban();
   }


   /*! \brief (interface) Parses given text with SL1-syntax and returns Goban-object. */
   function read_goban( $text )
   {
      // init
      $this->text = preg_replace( "/<br>/i", "\n", $text );
      $this->goban = new Goban();
      $this->borders = 0;
      $this->erasePoints = array();

      // defaults
      $this->showCoordinates = false;
      $this->boardSize = 19;
      $this->boardTitle = NULL; // no title
      $this->startMoveBlack  = true;
      $this->startMoveNumber = 1;

      $this->lines = preg_split( "/\r?\n/", trim($this->text) ); // Ex01
      $this->lpos = 0;
      $this->ypos = 1;
      $this->max_x = 1;
      $this->max_y = 1;
      foreach( $this->lines as $line )
      {
         $line = preg_replace( "/\s/", " ", $line ); // replace tabs -> space

         // Ex02: line-prefix '$$' can be omitted
         if( substr($line,0,2) === '$$' )
            $line = substr($line,2);

         // Ex01: trim leading white-spaces
         $line = trim($line);

         if( $this->lpos == 0 )
            $this->parse_title_line( $line );
         else
         {
            if( !$this->parse_borders( $line ) )
               $this->parse_line( $line );
         }
         $this->lpos++;
      }

      $this->max_y = $this->ypos;
      $this->goban->setSize( max($this->goban->max_x, $this->goban->max_y) );

      $this->goban->setOptionsCoords( ($this->showCoordinates) ? $this->borders : 0 );
      $this->setBoardBorders();

      foreach( $this->erasePoints as $arr )
         $this->goban->eraseBoardPoint( $arr[0], $arr[1] ); //x,y

      //TODO: auto set hoshi-points

      return $this->goban;
   }

   // internal, parse SL1, returns true, if was only border-line (nothing more to parse)
   function parse_borders( $line )
   {
      // consume line with border-info: +--- | ----- | ...--+
      if( preg_match("/^(\+\-?|\-\-).*?(\+)?$/", $line, $matches) )
      {
         if( $this->ypos == 1 )
            $this->borders |= GOBB_NORTH;
         else
            $this->borders |= GOBB_SOUTH;

         if( @$matches[1] == '+' )
            $this->borders |= GOBB_WEST;
         if( @$matches[2] == '+' )
            $this->borders |= GOBB_EAST;

         return true;
      }
      else
         return false;
   }

   // internal, parse SL1
   function parse_line( $line )
   {
      $x = 0; // 1..n
      $y = $this->ypos; // 1..n
      $linelen = strlen($line);
      $expect_sep = true;

      for( $idx=0; $idx < $linelen; $idx++)
      {
         $item = $line[$idx];
         $expect_sep = !$expect_sep;
         if( $expect_sep && $item == ' ' )
            continue;

         if( $item == '|' ) // v-edge
         {
            // in first or last col?
            if( $x == 0 )
               $this->borders |= GOBB_WEST;
            elseif( substr($line,-1,1) == '|' )
               $this->borders |= GOBB_EAST;
            continue;
         }

         $x++;
         $this->goban->setBoardLines( $x, $y, GOBB_MID );

         if( $item == '.' ) // empty intersection
         {
            ; // no stone, but board-lines
         }
         elseif( $item == '-' ) // empty cell
         {
            $this->goban->setValue( $x, $y, 0 ); // to have x,y in matrix
            $this->erasePoints[] = array( $x,$y );
         }
         elseif( $item == ',' ) // hoshi
            $this->goban->setHoshi( $x, $y, true );
         elseif( $item == 'X' ) // black stone
            $this->goban->setStone( $x, $y, GOBS_BLACK );
         elseif( $item == 'O' ) // white stone
            $this->goban->setStone( $x, $y, GOBS_WHITE );
         elseif( $item == 'B' ) // black stone (circle)
         {
            $this->goban->setStone( $x, $y, GOBS_BLACK );
            $this->goban->setMarker( $x, $y, GOBM_CIRCLE );
         }
         elseif( $item == 'W' ) // white stone (circle)
         {
            $this->goban->setStone( $x, $y, GOBS_WHITE );
            $this->goban->setMarker( $x, $y, GOBM_CIRCLE );
         }
         elseif( $item == '#' ) // black stone (square)
         {
            $this->goban->setStone( $x, $y, GOBS_BLACK );
            $this->goban->setMarker( $x, $y, GOBM_SQUARE );
         }
         elseif( $item == '@' ) // white stone (square)
         {
            $this->goban->setStone( $x, $y, GOBS_WHITE );
            $this->goban->setMarker( $x, $y, GOBM_SQUARE );
         }
         elseif( $item == 'Y' ) // black stone (triangle)
         {
            $this->goban->setStone( $x, $y, GOBS_BLACK );
            $this->goban->setMarker( $x, $y, GOBM_TRIANGLE );
         }
         elseif( $item == 'Q' ) // white stone (triangle)
         {
            $this->goban->setStone( $x, $y, GOBS_WHITE );
            $this->goban->setMarker( $x, $y, GOBM_TRIANGLE );
         }
         elseif( $item == 'Z' ) // black stone (cross)
         {
            $this->goban->setStone( $x, $y, GOBS_BLACK );
            $this->goban->setMarker( $x, $y, GOBM_CROSS );
         }
         elseif( $item == 'P' ) // white stone (cross)
         {
            $this->goban->setStone( $x, $y, GOBS_WHITE );
            $this->goban->setMarker( $x, $y, GOBM_CROSS );
         }
         elseif( $item == 'C' ) // circle
            $this->goban->setMarker( $x, $y, GOBM_CIRCLE );
         elseif( $item == 'S' ) // square
            $this->goban->setMarker( $x, $y, GOBM_SQUARE );
         elseif( $item == 'T' ) // triangle
            $this->goban->setMarker( $x, $y, GOBM_TRIANGLE );
         elseif( $item == 'M' ) // cross = mark
            $this->goban->setMarker( $x, $y, GOBM_CROSS );
         elseif( is_numeric($item) ) // numbered stone
         {
            $num = (int)$item;
            $move_num = $this->startMoveNumber + $num - 1;
            if( $num & 1 ) //odd
               $move_col = ($this->startMoveBlack) ? GOBS_BLACK : GOBS_WHITE;
            else
               $move_col = ($this->startMoveBlack) ? GOBS_WHITE : GOBS_BLACK;
            $this->goban->setStone( $x, $y, $move_col );
            $this->goban->setMarker( $x, $y, GOBM_NUMBER, $move_num );
         }
         elseif( preg_match( "/^[a-z]$/i", $item) ) // letter a..z
            $this->goban->setMarker( $x, $y, GOBM_LETTER, strtolower($item) );
         else // unknown item
         {
            //error_log("WARNING: Unknown item[$item]");//TODO
         }
      }
      if( $x > $this->max_x )
         $this->max_x = $x;

      $this->ypos++;
   } //parse_line

   // internal, parse SL1
   function parse_title_line( $line )
   {
      // format: $$(B|W)?c?(size)?(m\d+)? (title)?
      if( preg_match( "/^([BW])?(c)?(\d+)?(m\d+)?(\s+.*)?$/i", $line, $matches ) )
      {
         if( @$matches[1] )
            $this->startMoveBlack = (bool)( strtoupper($matches[1]) === 'B' );
         if( @$matches[2] )
            $this->showCoordinates = true;
         if( @$matches[3] )
            $this->boardSize = (int)$matches[3];
         if( @$matches[4] )
            $this->startMoveNumber = (int)substr($matches[4], 1);
         $title = trim(@$matches[5]);
         if( $title != '' )
            $this->boardTitle = $title;
         return true;
      }
      else
         return false; // parse-error
   }

   function setBoardBorders()
   {
      $borders = $this->goban->getOptionsCoords();

      if( $borders & GOBB_NORTH ) // first row
         for( $x=1; $x <= $this->max_x; $x++)
            $this->goban->clearBoardLinesBit( $x, 1, GOBB_NORTH );
      if( ($borders & GOBB_SOUTH) || ($this->max_y == $this->goban->size) ) // last row
         for( $x=1; $x <= $this->max_x; $x++)
            $this->goban->clearBoardLinesBit( $x, $this->max_y, GOBB_SOUTH );

      if( $borders & GOBB_WEST ) // first col
         for( $y=1; $y <= $this->max_y; $y++)
            $this->goban->clearBoardLinesBit( 1, $y, GOBB_WEST );
      if( ($borders & GOBB_EAST) || ($this->max_x == $this->goban->size) ) // last col
         for( $y=1; $y <= $this->max_y; $y++)
            $this->goban->clearBoardLinesBit( $this->max_x, $y, GOBB_EAST );
   }


   /*! \brief (interface) Transforms given Goban-object into SL1-format. */
   function write_goban( $goban )
   {
      $result = array();
      //TODO
      return "<igoban SL1>\n"
            . implode("\n", $result)
            . "\n</igoban>";
   }

} //end 'GobanHandlerSL1'

?>
