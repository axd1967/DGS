<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/classlib_goban.php';

 /* Author: Jens-Uwe Gaspar */


 /*!
  * \file goban_handler_sl.php
  *
  * \brief Class implementing GobanHandler for format "Sensei's Library" to read and write Goban-object.
  */


define('SL_HOSTBASE', "http://senseis.xmp.net/?");

 /*!
  * \class GobanHandlerSL1
  * \brief Goban-reader and Goban-writer for igoban-type [SL1]: Sensei's Library syntax version 1
  *
  * <pre>
  *   SL1-format (Sensei's Library), ref: http://senseis.xmp.net/?HowDiagramsWork
  *   irregular boards, ref: http://senseis.xmp.net/?CreatingIrregularGobansWithWiki
  *
  *   each line starts with '$$' to indicate a go-diagram
  *   a space has meaning
  *   title-line := first line (mandatory)
  *     format of title-line (regex): $$(B|W)?c?(size)?(m\d+)?(title)?
  *     - B|W = gives start of color for numbered stones: W -> first numbered stone is white
  *     - c = enables coordinates (of type A1)
  *     - size = auto-size for board (influence edges)
  *     - m\d+ = gives moves-start-number (default: 1); m7 -> start with 7
  *     - title-text put under generated board
  *
  *     . = empty intersection, ',' = hoshi
  *     | + - = forming edges
  *     '-' = creates empty intersection if not at edge
  *     '_' = diagram-separator (similar to '-', but no edge)
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
  *        A            TA        black-territory
  *        V            TV        white-territory
  *        ~            T~        neutral-undecided-territory (DGS-only)
  *        =            T=        dame-territory (DGS-only)
  *
  *   stone with numbers
  *      - 0 = stone with number 10 in diagram
  *      - default: odd  numbers = black numbered stones
  *                 even numbers = white numbered stones
  *        color-order (default) can be given/overriden in title-line with (B|W)
  *      - move-start-number in title-line: (m\d+)
  *      - "A at B" in title line includes "move into SGF"
  *
  *   text-block below diagram-lines will be shown to right of diagram, except if started with "^%%%%".
  *   text-block initiated with empty-line or "%%%%" below diagram-data.
  * </pre>
  *
  * \note DGS-exceptions to SL-format:
  *   - Ex01: lines can start with white-spaces
  *   - Ex02: line-prefix '$$' can be omitted
  *   - Ex04: easier coordinate-usage (checking on + | -): "^+" "++" "-+", no spaces allowed in line with border-info
  *   - Ex05: goban-links supports: SL-topics, DGS-thread-anchor "#123", DGS-link "dgs:docs.php", http-links "http://..."
  *   - Ex06: URL-arg 'raw' prints content of original <igoban SL1>-tag; see GobanHandlerGfxBoard.build_rawtext()
  *   - Ex07: automatic setting of hoshi-points
  *   - Ex08: no big support for some irregular boards with edge-magic (allowed by SL1-syntax)
  *   - Ex09: arrows and lines are not supported, inline-images are not supported
  *   - Ex10: extended markers: A = black-territory, V = white-territory, ~ = neutral-territory, '=' = dame
  *   - Ex11: extended marker-text: TA TV T? T=  for territory-markers (Ex10)
  *   - Ex12: SL-chars '*' and '?' are not supported on DGS (as they don't contribute so much for diagrams)
  */
class GobanHandlerSL1
{
   private $args; // key => val, #1..n => val
   private $text = ''; // text to parse
   private $goban; // Goban

   private $showCoordinates; // bool
   private $boardSize; // NULL or int
   private $boardTitle; // NULL or string
   private $startMoveBlack; // bool
   private $startMoveNumber; // int

   private $borders; // GOBB_N/S/W/E (position) - board-line to clear at edges
   private $lines; // lines to parse
   private $borderLines; // border-lines to parse later; [ ypos => line, ... ]
   private $lpos;  // line-pos to parse 0..
   private $ypos;  // board-ypos 1..
   private $erasePoints; // arr( x,y )
   private $text_block;
   private $emptyLines;
   private $hoshiCount;

   /*! \brief Constructs GobanHandler for igoban-type SL1. */
   public function __construct( $arr_args=null )
   {
      $this->args = (is_array($arr_args)) ? array() : $arr_args;
      $this->goban = new Goban();
   }


   /*! \brief (interface) Parses given text with SL1-syntax and returns Goban-object. */
   public function read_goban( $text )
   {
      // init
      $this->text = preg_replace( "/<br>/i", "\n", $text );
      $this->goban = new Goban();
      $this->borders = 0;
      $this->erasePoints = array();

      // defaults
      $this->showCoordinates = false;
      $this->boardSize = null;
      $this->boardTitle = NULL;
      $this->startMoveBlack  = true;
      $this->startMoveNumber = 1;

      $this->lines = preg_split( "/\r?\n/", trim($this->text) ); // Ex01
      $this->borderLines = array();
      $this->lpos = 0;
      $this->ypos = 1;

      $this->text_block = null;
      $this->emptyLines = 0;
      $this->hoshiCount = 0;

      foreach( $this->lines as $line )
      {
         $this->lpos++;
         $line = preg_replace( "/\s/", " ", $line ); // replace tabs -> space

         // Ex02: line-prefix '$$' can be omitted
         if( substr($line,0,2) === '$$' )
            $line = substr($line,2);

         // Ex01: trim leading/trailing white-spaces
         $line = trim($line);
         if( $this->lpos == 1 )
            $this->parse_title_line( $line );
         else
         {
            if( !$this->parse_text_block($line) )
            {
               $line = trim( preg_replace( "/&nbsp;/", " ", $line ) );
               if( preg_match( "/^{/", $line ) )
                  ; // Ex09: Arrows/Lines not supported
               elseif( preg_match( "/^\[/", $line ) )
                  $this->parse_links($line);
               elseif( !$this->parse_borders($line) )
                  $this->parse_line( $line );
            }
         }
      }

      $this->parse_border_lines();
      $this->goban->setOptionsCoords( $this->borders, $this->showCoordinates );
      $this->setBoardBorders();

      $xSize = $ySize = ( !is_null($this->boardSize) ) ? $this->boardSize : 19;
      if( is_null($this->boardSize) && $this->borders == GOBB_MID )
      {
         $xSize = $this->goban->max_x;
         $ySize = $this->goban->max_y;
         $this->boardSize = max( $xSize, $ySize );
      }
      $this->goban->setSize( $xSize, $ySize );

      if( !is_null($this->boardTitle) && (string)$this->boardTitle != '' )
         $this->goban->BoardTitle = $this->parse_SL_text( $this->boardTitle ); // wrapping by fixed table-width

      if( !is_null($this->text_block) )
         $this->goban->BoardText = $this->parse_SL_text( $this->text_block );

      if( !$this->hoshiCount )
         $this->setHoshiPoints();

      foreach( $this->erasePoints as $arr )
         $this->goban->eraseBoardPoint( $arr[0], $arr[1] ); //x,y

      return $this->goban;
   }//read_goban

   /*!
    * \brief parse SL1-syntax for title.
    * \internal
    */
   private function parse_title_line( $line )
   {
      // format: $$(B|W)?c?(size)?(m\d+)? (title)?
      if( preg_match( "/^([BW])?(c)?(\d+)?(m\d+)?\s*(\b.*)?$/i", $line, $matches ) )
      {
         if( @$matches[1] ) // B|W
            $this->startMoveBlack = (bool)( strtoupper($matches[1]) === 'B' );
         if( @$matches[2] ) // c
            $this->showCoordinates = true;
         if( @$matches[3] ) // 99
            $this->boardSize = (int)$matches[3];
         if( @$matches[4] ) // m99
            $this->startMoveNumber = (int)substr($matches[4], 1);

         $title = trim(@$matches[5]);
         if( $title != '' )
            $this->boardTitle = $title;

         return true;
      }

      return false; // parse-error
   }//parse_title_line

   /*!
    * \brief parse SL1, parse text-block below diagram; started by empty-line or ^%%%% .
    * \internal
    */
   private function parse_text_block( $line )
   {
      if( !is_null($this->text_block) )
         $this->text_block .= "$line\n";
      else
      {
         if( preg_match("/^(&nbsp;|\s)*$/", $line) )
            $this->emptyLines++;
         elseif( preg_match("/^%%%%/", $line) )
         {
            $this->text_block = '';
            $this->goban->BoardTextInline = false;
         }
         elseif( $this->emptyLines > 0 )
            $this->text_block = "$line\n";
         else
            return false;
      }

      return true;
   }//parse_text_block

   /*!
    * \brief parse SL1, returns true if was only border-line (nothing more to parse).
    * \internal
    */
   private function parse_borders( $line )
   {
      if( !preg_match("/^[-+|]+$/", $line) )
         return false;

      $this->borderLines[$this->ypos] = $line;
      return true;
   }//parse_borders

   /*!
    * \brief parse saved border-lines after board-dimension is clear.
    * \internal
    */
   private function parse_border_lines()
   {
      foreach( $this->borderLines as $ypos => $line )
      {
         $clearLineBorder = false;
         if( $ypos == 1 )
            $gobb_vmask = GOBB_NORTH;
         elseif( $ypos >= $this->goban->max_y )
            $gobb_vmask = GOBB_SOUTH;
         else
            continue; // in-between separation-lines for irregular boards not supported

         // consume line with border-info
         if( preg_match("/^\+/", $line) ) // Ex04: ^+
         {
            $this->borders |= $gobb_vmask | GOBB_WEST;
            if( !$gobb_vmask )
               $clearLineBorder = true;
         }
         if( preg_match("/[-+]\+$/", $line) ) // Ex04: -+$   ++$
         {
            $this->borders |= $gobb_vmask | GOBB_EAST;
            if( !$gobb_vmask )
               $clearLineBorder = true;
         }
         if( preg_match("/-+/", $line) ) // ...----...
         {
            if( $gobb_vmask )
               $this->borders |= $gobb_vmask;
            else
               $clearLineBorder = true;
         }

         if( $clearLineBorder )
         {
            $this->clearLineBorderBit( $ypos - 1, GOBB_SOUTH );
            $this->clearLineBorderBit( $ypos, GOBB_NORTH );
         }
      }
   }//parse_border_lines

   /*! \brief (\internal) Clears border-bit for whole line. */
   private function clearLineBorderBit( $y, $bitvalue )
   {
      if( $y >=1 && $y <= $this->goban->max_y )
      {
         for( $x=1; $x < $this->goban->max_x; $x++ )
            $this->goban->clearBoardLinesBit( $x, $y, $bitvalue );
      }
   }//clearLineBorderBit

   /*!
    * \brief parse SL1, returns true if was only a link (nothing more to parse).
    * \internal
    */
   private function parse_links( $line )
   {
      // Ex05: consume line with diagram-links: [ref|link], link=dgs:dgsPage|http...|SL-topic; ignore remaining in line
      if( preg_match("/^\[\s*([^|])\s*\|\s*([^\]]+)\s*\]/", $line, $matches) )
      {
         $label = $matches[1];
         $link = $matches[2];
         if( preg_match("/^dgs:(.*)$/i", $link, $matches) )
            $link = HOSTBASE . $matches[1];
         elseif( !preg_match("/^(#|http)/", $link) )
            $link = SL_HOSTBASE . $link; // SL-topic

         $this->goban->addLink( $label, $link );
         return true;
      }

      return false;
   }//parse_links

   /*!
    * \brief parse SL1-syntax line.
    * \internal
    */
   private function parse_line( $line )
   {
      //error_log("SL.parse_line($line)");
      if( (string)$line == '' )
         return false;

      $x = 0; // 1..n
      $y = $this->ypos; // 1..n
      $linelen = strlen($line);
      $expect_sep = true;

      $prev_item = '';
      for( $idx=0; $idx < $linelen; $idx++, $prev_item = $item )
      {
         $item = $line[$idx];
         $next_item = ($idx + 1 < $linelen ) ? $line[$idx + 1] : '';

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
         if( $prev_item == '|' )
            $this->goban->clearBoardLinesBit( $x, $y, GOBB_WEST );
         if( $next_item == '|' )
            $this->goban->clearBoardLinesBit( $x, $y, GOBB_EAST );

         if( $item == '.' ) // empty intersection
         {
            ; // no stone, but board-lines
         }
         elseif( $item == '-' || $item == '_' || $item == ' ' ) // empty cell
         {
            $this->goban->setValue( $x, $y, 0 ); // to have x,y in matrix
            $this->erasePoints[] = array( $x,$y );
         }
         elseif( $item == ',' ) // hoshi
         {
            $this->goban->setHoshi( $x, $y, true );
            $this->hoshiCount++;
         }
         elseif( $item == 'X' ) // black stone
            $this->goban->setStone( $x, $y, GOBS_BLACK );
         elseif( $item == 'O' ) // white stone
            $this->goban->setStone( $x, $y, GOBS_WHITE );
         elseif( $item == 'B' ) // black stone (circle)
         {
            $this->goban->setStone( $x, $y, GOBS_BLACK );
            $this->goban->setMarker( $x, $y, GOBM_CIRCLE, $item );
         }
         elseif( $item == 'W' ) // white stone (circle)
         {
            $this->goban->setStone( $x, $y, GOBS_WHITE );
            $this->goban->setMarker( $x, $y, GOBM_CIRCLE, $item );
         }
         elseif( $item == '#' ) // black stone (square)
         {
            $this->goban->setStone( $x, $y, GOBS_BLACK );
            $this->goban->setMarker( $x, $y, GOBM_SQUARE, $item );
         }
         elseif( $item == '@' ) // white stone (square)
         {
            $this->goban->setStone( $x, $y, GOBS_WHITE );
            $this->goban->setMarker( $x, $y, GOBM_SQUARE, $item );
         }
         elseif( $item == 'Y' ) // black stone (triangle)
         {
            $this->goban->setStone( $x, $y, GOBS_BLACK );
            $this->goban->setMarker( $x, $y, GOBM_TRIANGLE, $item );
         }
         elseif( $item == 'Q' ) // white stone (triangle)
         {
            $this->goban->setStone( $x, $y, GOBS_WHITE );
            $this->goban->setMarker( $x, $y, GOBM_TRIANGLE, $item );
         }
         elseif( $item == 'Z' ) // black stone (cross)
         {
            $this->goban->setStone( $x, $y, GOBS_BLACK );
            $this->goban->setMarker( $x, $y, GOBM_CROSS, $item );
         }
         elseif( $item == 'P' ) // white stone (cross)
         {
            $this->goban->setStone( $x, $y, GOBS_WHITE );
            $this->goban->setMarker( $x, $y, GOBM_CROSS, $item );
         }
         elseif( $item == 'C' ) // circle
            $this->goban->setMarker( $x, $y, GOBM_CIRCLE, $item );
         elseif( $item == 'S' ) // square
            $this->goban->setMarker( $x, $y, GOBM_SQUARE, $item );
         elseif( $item == 'T' ) // triangle
            $this->goban->setMarker( $x, $y, GOBM_TRIANGLE, $item );
         elseif( $item == 'M' ) // cross = mark
            $this->goban->setMarker( $x, $y, GOBM_CROSS, $item );
         // Ex10: DGS-only board-markers ------
         elseif( $item == 'A' ) // black territory (black filled box)
            $this->goban->setMarker( $x, $y, GOBM_TERR_B, $item );
         elseif( $item == 'V' ) // white territory (white filled box)
            $this->goban->setMarker( $x, $y, GOBM_TERR_W, $item );
         elseif( $item == '~' ) // neutral territory (green filled box)
            $this->goban->setMarker( $x, $y, GOBM_TERR_NEUTRAL, $item );
         elseif( $item == '=' ) // dame territory (red filled box)
            $this->goban->setMarker( $x, $y, GOBM_TERR_DAME, $item );
         // END Ex10 ------
         elseif( is_numeric($item) ) // numbered stone
         {
            $num = (int)$item;
            $move_num = $this->startMoveNumber + $num - 1 + ( $num == 0 ? 10 : 0 );
            if( $num & 1 ) //odd
               $move_col = ($this->startMoveBlack) ? GOBS_BLACK : GOBS_WHITE;
            else
               $move_col = ($this->startMoveBlack) ? GOBS_WHITE : GOBS_BLACK;
            $this->goban->setStone( $x, $y, $move_col );
            $this->goban->setMarker( $x, $y, GOBM_NUMBER, $move_num );
         }
         elseif( preg_match( "/^[a-z]$/", $item) ) // letter a..z
            $this->goban->setMarker( $x, $y, GOBM_LETTER, strtolower($item) );
         else // unknown item
         {
            //TODO handle "unknown parse-items"
            //error_log("WARNING: Unknown item[$item] at $idx in line #{$this->lpos} [$line]");
         }
      }//end-for

      $this->ypos++;

      return true;
   }//parse_line

   /*!
    * \brief Replacing SL-text with DGS-tags if possible.
    * \note Supported SL-text-parts:
    *    [topic]  => <http://senseis.xmp.net/?topic |topic>
    *    BO WO    => <image board/b|w.gif>
    *    B|W1.100 => <image board/b|w100.gif>
    *    BC WC    => <image board/b|wc.gif>
    *    BS WS    => <image board/b|ws.gif>
    *    BT WT    => <image board/b|wt.gif>
    *    BX WX    => <image board/b|wx.gif>
    *    EC ES ET EX => <image board/c|s|t|x.gif>
    *    TA TV T~ T= => <image board/hb|hw|hg|hd.gif>  (DGS-only)
    *    !XYZ     => "XYZ" (=escaping XYZ)
    *
    * \internal
    */
   private function parse_SL_text( $text )
   {
      static $map_territory = array( 'A' => 'hb', 'V' => 'hw', '~' => 'hg', '=' => 'hd' ); // Ex11: map "T..."
      return preg_replace(
         array(
            "/(?<!\!)\[([^\]]+)\]/", // [topic]
            "/\b(?<!!)(B|W)O\b/e", // BO WO
            "/\b(?<!!)(B|W)(\d\d?|100)\b/e", // B|W1.100
            "/\b(?<!!)(B|W)([CSTX])\b/e", // (B|W)(C|S|T|X)
            "/\b(?<!!)E([CSTX])\b/e", // E(C|S|T|X)
            "/\b(?<!!)T([AV~=])/e", // Ex11: T(A|V|~|=)
            "/!(\S)/", // !XYZ
         ), array(
            "<".SL_HOSTBASE."\\1 |\\1>", // [topic]
            "\"<image board/\" . strtolower('\\1') . \".gif>\"", // BO WO
            "\"<image board/\" . strtolower('\\1') . \"\\2.gif>\"", // B|W1.100
            "\"<image board/\" . strtolower('\\1') . strtolower('\\2') . \".gif>\"", // (B|W)(C|S|T|X)
            "\"<image board/\" . strtolower('\\1') . \".gif>\"", // E(C|S|T|X)
            "\"<image board/{\$map_territory['\\1']}.gif>\"", // Ex11: T(A|V|~|=)
            "\\1", // !XYZ
         ), $text );
   }//parse_SL_text

   /*!
    * \brief sets borders of board according to this->borders.
    * \internal
    */
   private function setBoardBorders()
   {
      if( $this->borders == 0 )
         return;

      if( $this->borders & GOBB_NORTH ) // first row
         for( $x=1; $x <= $this->goban->max_x; $x++)
            $this->goban->clearBoardLinesBit( $x, 1, GOBB_NORTH );
      if( ($this->borders & GOBB_SOUTH) || (!is_null($this->boardSize) && $this->goban->max_y >= $this->boardSize) ) // last row
         for( $x=1; $x <= $this->goban->max_x; $x++)
            $this->goban->clearBoardLinesBit( $x, $this->goban->max_y, GOBB_SOUTH );

      if( $this->borders & GOBB_WEST ) // first col
         for( $y=1; $y <= $this->goban->max_y; $y++)
            $this->goban->clearBoardLinesBit( 1, $y, GOBB_WEST );
      if( ($this->borders & GOBB_EAST) || (!is_null($this->boardSize) && $this->goban->max_x >= $this->boardSize) ) // last col
         for( $y=1; $y <= $this->goban->max_y; $y++)
            $this->goban->clearBoardLinesBit( $this->goban->max_x, $y, GOBB_EAST );
   }//setBoardBorders

   /*!
    * \brief Ex07: set hoshi-points for board with at least one pair of perpendicular edges.
    * \internal
    */
   private function setHoshiPoints()
   {
      // need at least one pair of perpendicular edges
      if( $this->borders == 0 )
         return false;
      elseif( $this->borders == (GOBB_WEST|GOBB_EAST) || $this->borders == (GOBB_NORTH|GOBB_SOUTH) )
         return false;
      elseif( $this->borders == (1 << floor(log($this->borders, 2))) )
         return false;

      $size_x = (is_null($this->boardSize)) ? $this->goban->size_x : $this->boardSize;
      $size_y = (is_null($this->boardSize)) ? $this->goban->size_y : $this->boardSize;
      if( ($this->borders & (GOBB_WEST|GOBB_EAST)) == (GOBB_WEST|GOBB_EAST) )
         $size_x = $this->goban->max_x;
      if( ($this->borders & (GOBB_NORTH|GOBB_SOUTH)) == (GOBB_NORTH|GOBB_SOUTH) )
         $size_y = $this->goban->max_y;

      $arr_hoshi = get_hoshi_coords( $size_x, $size_y, 1 );
      if( ($this->borders & (GOBB_NORTH|GOBB_WEST)) == (GOBB_NORTH|GOBB_WEST) ) // |^^
         $this->drawHoshiPoints( $arr_hoshi, 0, 0 );
      elseif( ($this->borders & (GOBB_NORTH|GOBB_EAST)) == (GOBB_NORTH|GOBB_EAST) ) // ^^|
         $this->drawHoshiPoints( $arr_hoshi, $this->goban->max_x + 1, 0 );
      elseif( ($this->borders & (GOBB_SOUTH|GOBB_WEST)) == (GOBB_SOUTH|GOBB_WEST) ) // |__
         $this->drawHoshiPoints( $arr_hoshi, 0, $this->goban->max_y + 1 );
      else //if( ($this->borders & (GOBB_SOUTH|GOBB_EAST)) == (GOBB_SOUTH|GOBB_EAST) ) // __|
         $this->drawHoshiPoints( $arr_hoshi, $this->goban->max_x + 1, $this->goban->max_y + 1 );

      return true;
   }//setHoshiPoints

   // \internal
   private function drawHoshiPoints( $arr_hoshi, $chg_x, $chg_y )
   {
      foreach( $arr_hoshi as $arr_xy )
      {
         list( $x, $y ) = $arr_xy;
         $this->goban->setHoshi( ($chg_x ? $chg_x - $x : $x), ($chg_y ? $chg_y - $y : $y), true, /*chk*/true );
      }
   }//drawHoshiPoints


   /*! \brief (interface) Transforms given Goban-object into SL1-format. */
   public function write_goban( $goban )
   {
      $CONV_MAP = array( // GOBS_BITMASK | GOBM_BITMASK
            GOBS_BLACK                 => 'X',
            GOBS_WHITE                 => 'O',
            GOBM_CIRCLE | GOBS_BLACK   => 'B',
            GOBM_CIRCLE | GOBS_WHITE   => 'W',
            GOBM_CIRCLE                => 'C',
            GOBM_SQUARE | GOBS_BLACK   => '#',
            GOBM_SQUARE | GOBS_WHITE   => '@',
            GOBM_SQUARE                => 'S',
            GOBM_TRIANGLE | GOBS_BLACK => 'Y',
            GOBM_TRIANGLE | GOBS_WHITE => 'Q',
            GOBM_TRIANGLE              => 'T',
            GOBM_CROSS | GOBS_BLACK    => 'Z',
            GOBM_CROSS | GOBS_WHITE    => 'P',
            GOBM_CROSS                 => 'M',
            //GOBM_TERR_B | GOBS_WHITE   => '?', // white-terr on B-stone
            //GOBM_TERR_W | GOBS_BLACK   => '?', // black-terr on W-stone
            GOBM_TERR_B                => 'A',
            GOBM_TERR_W                => 'V',
            GOBM_TERR_NEUTRAL          => '~',
            GOBM_TERR_DAME             => '=',
         );
      $result = array();
      $result[] = '$$'; // placeholder for title

      // NOTE: only export standard board-lines, no irregular boards
      // NOTE: export hoshi if set, no detection for auto-hoshi

      // find start-move-number for number-labels
      $arr_nums = array(); // label-num => is-black
      for( $y=1; $y <= $goban->max_y; $y++ )
      {
         for( $x=1; $x <= $goban->max_x; $x++ )
         {
            $arrval = $goban->getValue($x,$y);
            if( $arrval[GOBMATRIX_VALUE] & GOBM_NUMBER )
            {
               $label = $arrval[GOBMATRIX_LABEL];
               if( $label )
                  $arr_nums[$label] = ($arrval[GOBMATRIX_VALUE] & GOBS_BLACK);
            }
         }
      }
      ksort($arr_nums, SORT_NUMERIC);
      $min_num = (count($arr_nums)) ? array_shift( array_keys($arr_nums) ) : 0;
      $firstmove_color = ( $min_num > 0 && !$arr_nums[$min_num] ) ? 'W' : '';

      $result[] = '$$ ++';
      for( $y=1; $y <= $goban->max_y; $y++ )
      {
         $line = '$$';
         for( $x=1; $x <= $goban->max_x; $x++ )
         {
            $arrval = $goban->getValue($x,$y);
            $val = $arrval[GOBMATRIX_VALUE];
            $label = $arrval[GOBMATRIX_LABEL];

            $stone = ($val & GOBS_BITMASK);
            $marker = ($val & GOBM_BITMASK);
            $hoshi = ($val & GOBO_HOSHI);
            $num_label = ($marker == GOBM_NUMBER) ? $label - $min_num + 1 : 0;

            if( $stone != GOBS_EMPTY && $marker == GOBM_NUMBER && $num_label >= 1 && $num_label <= 10 )
               $line .= ' ' . ($num_label == 10 ? 0 : $num_label);
            elseif( $stone == GOBS_EMPTY && $marker == GOBM_LETTER && $label >= 'a' && $label <= 'z' )
               $line .= ' ' . $label;
            elseif( $conv_val = @$CONV_MAP[$stone | $marker] )
               $line .= ' ' . $conv_val;
            elseif( $conv_val = @$CONV_MAP[$stone] )
               $line .= ' ' . $conv_val;
            elseif( $stone == GOBS_EMPTY )
               $line .= ' ' . ($hoshi ? ',' : '.');
         }
         $result[] = $line;
      }
      $result[] = '$$ ++';

      // LINKS
      foreach( $goban->BoardLinks as $label => $link )
      {
         if( strpos($link, HOSTBASE) === 0 )
            $link = 'dgs:' . substr($link, strlen(HOSTBASE));
         elseif( strpos($link, SL_HOSTBASE) === 0 )
            $link = substr($link, strlen(SL_HOSTBASE));
         $result[] = "\$\$ [$label|$link]";
      }

      // TITLE-line
      if( $firstmove_color )
         $result[0] .= $firstmove_color;
      if( $goban->show_coords && $goban->opts_coords != GOBB_EMPTY )
         $result[0] .= 'c';
      if( $goban->size_x == $goban->size_y && $goban->max_x == $goban->max_y && $goban->size_x == $goban->max_x )
         $result[0] .= $goban->size_x;
      if( $min_num > 1 )
         $result[0] .= 'm' . $min_num;
      if( !is_null($goban->BoardTitle) )
         $result[0] .= ' ' . $goban->BoardTitle;

      // TEXT-block
      if( !is_null($goban->BoardText) )
      {
         if( !$goban->BoardTextInline )
            $result[] = '%%%%';
         $result[] = $goban->BoardText;
      }

      return "<igoban SL1>\n" . implode("\n", $result) . "\n</igoban>";
   }//write_goban

} //end 'GobanHandlerSL1'

?>
