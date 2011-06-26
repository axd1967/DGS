// <!--
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

// NOTE: global DGS-object defined in common.js

var DBG; //TODO //$("#gameNotes").val(DBG); //TODO DBG

(function($) { // ensure local scope and $ == jQuery


// --------------- Global Functions -------------------------------------------

DGS.game = {
   loadPage : function() {
      $(document).ready( DGS.game.initPage );
   },

   initPage : function() {
      $("a.GameViewer").click( function(event) {
         event.preventDefault();
         $("span#GameViewer").toggle();

         if( $("span#GameViewer").is(":visible") ) {
            DGS.run.gameEditor.saveBoard();
            DGS.run.gameEditor.drawBoard();
         } else {
            DGS.run.gameEditor.restoreBoard();
         }
      });

      $("span#GameViewer a").click( function(event) {
         event.preventDefault();
         //TODO this.id = (First|Last|Next|Prev)Move
      });
   }
};

DGS.utils = {
   build_map : function( arr ) {
      if( !$.isArray(arr) )
         throw "DGS.utils.build_map(): invalid args, array expected ["+arr+"]";
      if( (arr.length & 2) == 1 )
         throw "DGS.utils.build_map(): invalid args, odd arr-length ["+arr.join(',')+"]";

      var map = [];
      for( var i=0; i < arr.length; i += 2 ) {
         map[ arr[i] ] = arr[i+1];
      }
      return map;
   }
};




// --------------- Goban ------------------------------------------------------

// Usage (Goban): keep board-state
//    see Goban-class in 'include/classlib_goban.php'

DGS.constants.Goban = {
   // Goban (4 bits, single bits):
   // board-lines at board-position (x/y), empty=0, MID=N|S|W|E
   GOBB_BITMASK : 0x000F,
   GOBB_EMPTY   : 0x0000, // empty layer = empty intersection
   GOBB_NORTH   : 0x0001, // north-line
   GOBB_SOUTH   : 0x0002, // south-line
   GOBB_WEST    : 0x0004, // west-line
   GOBB_EAST    : 0x0008, // east-line
   GOBB_MID     : 0x000F, // middle board-position (N|S|W|E)

   // Goban (special other layer)
   GOBO_HOSHI   : 0x0080, // hoshi-bit

   // Goban (3 bits, combined bitset): stone-color
   GOBS_BITMASK : 0x0070,
   GOBS_EMPTY   : 0x0000, // empty layer = no stone
   GOBS_BLACK   : 0x0010, // black
   GOBS_WHITE   : 0x0020, // white
   //define('GOBS_..', 0x0030..0x0070); // reserved

   // Goban (4 bits, single + combined bitset): markers
   GOBM_BITMASK   : 0x0F00,
   GOBM_EMPTY     : 0x0000, // empty layer = no marker
   // - mutual exclusive markers (no bitmask)
   GOBM_NUMBER    : 0x0100, // 1..n
   GOBM_LETTER    : 0x0200, // a..z
   GOBM_MARK      : 0x0300, // double circle
   GOBM_CIRCLE    : 0x0400,
   GOBM_SQUARE    : 0x0500, // unfilled
   GOBM_TRIANGLE  : 0x0600,
   GOBM_CROSS     : 0x0700,
   GOBM_TERR_W    : 0x0800, // white (filled) box = white territory
   GOBM_TERR_B    : 0x0900, // black (filled) box = black territory
   GOBM_TERR_NEUTRAL : 0x0A00, // green (filled) box = neutral territory
   GOBM_TERR_DAME : 0x0B00, // red (filled) box = dame territory
   //define('GOBM_..', 0x0B00..0x0F00); // reserved

   // internal
   GOBMATRIX_VALUE : 0, // matrix-idx: layer-value
   GOBMATRIX_LABEL : 1, // matrix-idx: label for GOBM_NUMBER|LETTER

   HOSHI : { // see 'include/coord.php', is_hoshi()-func
      //index=size: dist (value=side-distance), pos (value=mask)
      //$hoshi_pos: 0x01 allow center, 0x02 allow side, 0x04 allow corner
      DIST : [ 0,0,0,0,0,0,0,0,3,3,3,3,4,4,4,4,4,4,4,4,4,4,4,4,4,4 ],
      POS  : [ 0,0,0,0,0,1,0,1,4,5,4,5,4,7,4,7,4,7,4,7,4,7,4,7,4,7 ]
   }
};
var C = DGS.constants.Goban;


// constructs Goban
DGS.Goban = function() {
   // matrix[y][x] = GOB-bitmask | [ GOB-value-bitmask, number|letter (=label) ]; x/y=1..n
   // NOTE: makeBoard() MUST be called to properly init matrix
   this.matrix = [];

   // 1..max_x/y really HAS something on board, can be partial board of size_x/y
   this.max_x = this.max_y = 1;

   // for starting y-coordinates-label
   this.size_x = this.size_y = 1;
};

$.extend( DGS.Goban.prototype, {

   setSizeX : function( size_x ) {
      this.size_x = size_x;
   },

   setSizeY : function( size_y ) {
      this.size_y = size_y;
   },

   toString : function() {
      var buf = String.sprintf("Goban(%s,%s):\n", this.max_x, this.max_y);
      for( var y=1; y <= this.max_y; y++ ) {
         buf += y + ": ";
         for( var x=1; x <= this.max_x; x++ ) {
            var arr = this.getValue(x,y);
            buf += String.sprintf("[%x,%s] ", arr[C.GOBMATRIX_VALUE], arr[C.GOBMATRIX_LABEL] );
         }
         buf += "\n";
      }
      return buf;
   },

   clearBoard : function() {
      this.makeBoard( this.max_x, this.max_y, true );
   },

   makeBoard : function( width, height, withHoshi ) {
      if( width < 2 || height < 2 )
         throw "Goban.makeBoard("+width+","+height+","+withHoshi+"): invalid_args width, height";

      this.matrix = [];
      for( var y=1; y <= width; y++) {
         var board_lines = C.GOBB_MID;
         if( y == 1 )
            board_lines &= ~C.GOBB_NORTH;
         else if( y == height )
            board_lines &= ~C.GOBB_SOUTH;

         this.matrix[y] = [];
         for( var x=1; x <= height; x++) {
            var val = board_lines;
            if( x == 1 )
               val &= ~C.GOBB_WEST;
            else if( x == width )
               val &= ~C.GOBB_EAST;

            if( withHoshi && this.isHoshi(x-1, y-1, width, height) )
               val |= C.GOBO_HOSHI;

            this.matrix[y][x] = val;
         }
      }

      this.max_x = width;
      this.max_y = height;
   }, //makeBoard

   isHoshi : function( x, y, size_x, size_y ) {
      if( size_y == undefined )
         size_y = size_x;

      //board letter:     - a b c d e f g h j k l m n o p q r s t u v w x y z
      if( size_x == size_y ) {
         var hd = C.HOSHI.DIST[size_x];
         var h = ( (x*2+1 == size_x) ? 1 : ( (x == hd-1 || x == size_x-hd) ? 2 : 0 ) );
         if( h )
            h *= (y*2+1 == size_x) ? 1 : ( (y == hd-1 || y == size_x-hd) ? 2 : 0 );
         return C.HOSHI.POS[size_x] & h;
      } else {
         var hdx = C.HOSHI.DIST[size_x];
         var hdy = C.HOSHI.DIST[size_y];
         var hx = (x*2+1 == size_x) ? 1 : ( (x == hdx-1 || x == size_x-hdx) ? 2 : 0 );
         var hy = (y*2+1 == size_y) ? 1 : ( (y == hdy-1 || y == size_y-hdy) ? 2 : 0 );
         return (C.HOSHI.POS[size_x] & hx) && (C.HOSHI.POS[size_y] & hy);
      }
   }, //isHoshi

   // internal, overwriting layer-value
   setValue : function( x, y, value, label ) { // optional: label=undef
      this.max_x = Math.max( x, this.max_x );
      this.max_y = Math.max( y, this.max_y );

      if( $.isArray(value) )
         this.matrix[y][x] = value;
      else if( typeof(value) != 'number' )
         throw "Goban.setValue("+x+","+y+","+value+","+label+"): invalid_args value";
      else if( label == undefined )
         this.matrix[y][x] = value; // optimization to avoid too many object-instances
      else
         this.matrix[y][x] = [ value, label ];
   }, //setValue

   hasValue : function( x, y ) {
      return (this.matrix[y] && this.matrix[y][x]);
   },

   // returns non-null [ value, label ]
   getValue : function( x, y ) {
      if( !this.hasValue(x,y) )
         return [ 0, '' ];
      var arrval = this.matrix[y][x];
      if( typeof(arrval) == 'number' )
         return [ arrval, '' ];
      else if( typeof(arrval) == 'object' )
         return arrval;
      else
         return [ 0, '' ];
   },

   setStone : function( x, y, stone_value ) {
      var upd_arr = this.getValue(x,y);
      upd_arr[C.GOBMATRIX_VALUE] =
         ( upd_arr[C.GOBMATRIX_VALUE] & ~C.GOBS_BITMASK ) | (stone_value & C.GOBS_BITMASK);
      this.setValue( x, y, upd_arr );
   },

   getStone : function( x, y ) {
      var current_arr = this.getValue(x,y);
      return (current_arr[C.GOBMATRIX_VALUE] & C.GOBS_BITMASK);
   },

   setMarker : function( x, y, marker_value, label ) { // optional: label=undef
      marker_value &= C.GOBM_BITMASK;
      var upd_arr = this.getValue(x,y);
      upd_arr[C.GOBMATRIX_VALUE] = ( upd_arr[C.GOBMATRIX_VALUE] & ~C.GOBM_BITMASK ) | marker_value;
      upd_arr[C.GOBMATRIX_LABEL] = ( label == undefined ) ? '' : label;
      this.setValue( x, y, upd_arr );
   },

   // returns non-null [ marker-value, label ]
   getMarker : function( x, y, with_label ) { // optional: with_label=false
      if( with_label == undefined )
         with_label = false;
      var current_arr = this.getValue(x,y);
      var value = ( current_arr[C.GOBMATRIX_VALUE] & C.GOBM_BITMASK );
      return ( with_label ) ? [ value, current_arr[C.GOBMATRIX_LABEL] ] : value;
   },

   // return marker-label only
   getMarkerLabel : function( x, y ) {
      return this.getValue(x,y)[C.GOBMATRIX_LABEL];
   }

}); //Goban




// --------------- GobanChanges -----------------------------------------------

// constructs GobanChanges
DGS.GobanChanges = function() {
   //arr: [ x, y, value_xormask, label_diff ]; x/y=1..n, mask=int, diff=+L -L ""
   this.changes = [];
};

$.extend( DGS.GobanChanges.prototype, {

   // param label_diff: if label-changed "+L|-L" then also xor_mask must contain according bit GOBM_LETTER|NUMBER
   add_change : function( x, y, xor_mask, label_diff ) {
      this.changes.push( [ x, y, xor_mask, label_diff ] );
   },

   // apply GobanChanges in this object on given Goban
   apply_changes : function( goban ) {
      var chg, x, y, xor_mask, label_diff, arrval, old_value, old_label;
      for( var i=0; i < this.changes.length; i++ ) {
         chg = this.changes[i];
         x = chg[0], y = chg[1], xor_mask = chg[2], label_diff = chg[3];

         if( xor_mask || label_diff ) {
            arrval = goban.getValue(x,y);
            old_value = arrval[C.GOBMATRIX_VALUE];
            old_label = arrval[C.GOBMATRIX_LABEL];

            if( xor_mask )
               arrval[C.GOBMATRIX_VALUE] ^= xor_mask;
            if( label_diff ) {
               if( label_diff.charAt(0) == '+' )
                  arrval[C.GOBMATRIX_LABEL] = label_diff.substr(1);
               else if( label_diff.charAt(0) == '-' )
                  arrval[C.GOBMATRIX_LABEL] = "";
            }
            if( old_value != arrval[C.GOBMATRIX_VALUE] || old_label != arrval[C.GOBMATRIX_LABEL] )
               goban.setValue( x, y, arrval );
         }
      }
   } //apply_changes

}); //GobanChanges




// --------------- Board ------------------------------------------------------

// Usage (Board): drawing goban by updating images on board
// see 'include/goban_handler_gfx.php'

DGS.constants.Board = {
   MAP_TERRITORY_MARKERS : DGS.utils.build_map([
      C.GOBM_TERR_B       , 'b',
      C.GOBM_TERR_W       , 'w',
      C.GOBM_TERR_NEUTRAL , 'd',
      C.GOBM_TERR_DAME    , 'g'
   ]),

   MAP_FORM_MARKERS : DGS.utils.build_map([
      C.GOBM_CIRCLE    , 'c',
      C.GOBM_SQUARE    , 's',
      C.GOBM_TRIANGLE  , 't',
      C.GOBM_CROSS     , 'x'
   ]),

   MAP_BOARDLINES : DGS.utils.build_map([
      /*
       *  SE  SWE  SW             ul u ur
       *  NSE NWSE NSW     ->     el e er
       *  NE  NWE  NW             dl d dr
       *  0 , NS           ->     '', du
       */
      C.GOBB_NORTH | C.GOBB_SOUTH | C.GOBB_WEST | C.GOBB_EAST , 'e',  // middle
      C.GOBB_NORTH | C.GOBB_SOUTH | C.GOBB_WEST               , 'er', // =W
      C.GOBB_NORTH | C.GOBB_SOUTH               | C.GOBB_EAST , 'el', // =E
      C.GOBB_NORTH | C.GOBB_SOUTH                             , '',   // supported, but can't be mixed
      C.GOBB_NORTH                | C.GOBB_WEST | C.GOBB_EAST , 'd',  // =N
      C.GOBB_NORTH                | C.GOBB_WEST               , 'dr',
      C.GOBB_NORTH                              | C.GOBB_EAST , 'dl',
      C.GOBB_NORTH                                            , '',   // unsupported
                     C.GOBB_SOUTH | C.GOBB_WEST | C.GOBB_EAST , 'u',  // =S
                     C.GOBB_SOUTH | C.GOBB_WEST               , 'ur',
                     C.GOBB_SOUTH               | C.GOBB_EAST , 'ul',
                     C.GOBB_SOUTH                             , '',   // unsupported
                                    C.GOBB_WEST | C.GOBB_EAST , '',   // unsupported
                                    C.GOBB_WEST               , '',   // unsupported
                                                  C.GOBB_EAST , '',   // unsupported
                                                            0 , ''    // empty
   ])
};
var BC = DGS.constants.Board;


// constructs Board
DGS.Board = function( stone_size ) {
   this.stone_size = (stone_size == undefined) ? 25 : stone_size;
};

$.extend( DGS.Board.prototype, {

   // updates board; rebuild=true to rebuild all td-cells (content replaced)
   draw_board : function( goban, rebuild ) {
      if( rebuild ) {
         $("table#Goban td.brdx a img").unwrap(); // remove all image-links
         $("table#Goban td.brdx img").removeAttr('alt');
      }

      for( var y=1; y <= goban.max_y; y++ ) {
         for( var x=1; x <= goban.max_x; x++ ) {
            var arr = goban.getValue(x,y);
            this.write_image( x, y, arr[C.GOBMATRIX_VALUE], arr[C.GOBMATRIX_LABEL] );
         }
      }
   },

   draw_goban_changes : function( goban, goban_changes ) {
      var arrval, x, y;
      var visited = []; //"x:y"=1
      for( var i=0; i < goban_changes.changes.length; i++ ) {
         var arr = goban_changes.changes[i];
         x = arr[0], y = arr[1], key = x+':'+y;
         if( !visited[key] ) {
            arrval = goban.getValue(x,y);
            this.write_image( x, y, arrval[C.GOBMATRIX_VALUE], arrval[C.GOBMATRIX_LABEL] );
         }
         visited[key] = 1;
      }
   },

   // updates td-cell with board-image (and link); rebuild=true to rebuild td-cell (content replaced)
   write_image : function( x, y, value, label ) {
      //global base_path
      var lBoard  = value & C.GOBB_BITMASK;
      var lStone  = value & C.GOBS_BITMASK;
      var lHoshi  = value & C.GOBO_HOSHI;
      var lMarker = value & C.GOBM_BITMASK;
      var isStoneBW = (lStone == C.GOBS_BLACK || lStone == C.GOBS_WHITE );
      var bLineType = this.getBoardLineType(lBoard, true); // only mixable

      var type = ''; // unknown mapping
      var territoryMarker, formMarker;

      // mapping and prioritize goban-layer-values to actual images available on DGS
      // starting with most special ... ending with most generalized images
      if( lMarker == C.GOBM_NUMBER && isStoneBW ) {
         type = (lStone == C.GOBS_BLACK) ? 'b' : 'w';
         if( label >= 1 && label <= 500 )
            type += parseInt(label, 10); // strip away leading 0s
      }
      else if( lMarker == C.GOBM_MARK && isStoneBW )
         type = (lStone == C.GOBS_BLACK) ? 'bm' : 'wm';
      else if( lMarker == C.GOBM_TERR_B && lStone == C.GOBS_WHITE )
         type = 'wb';
      else if( lMarker == C.GOBM_TERR_W && lStone == C.GOBS_BLACK )
         type = 'bw';
      else if( (territoryMarker = BC.MAP_TERRITORY_MARKERS[lMarker]) && lStone == 0 && bLineType )
         type = bLineType + territoryMarker;
      else if( (formMarker = BC.MAP_FORM_MARKERS[lMarker]) && isStoneBW )
         type = ( (lStone == C.GOBS_BLACK) ? 'b' : 'w' ) + formMarker;
      else if( (formMarker = BC.MAP_FORM_MARKERS[lMarker]) && lStone == 0 && lHoshi )
         type = 'h' + formMarker;
      else if( lMarker == 0 && isStoneBW )
         type = (lStone == C.GOBS_BLACK) ? 'b' : 'w';
      else if( lMarker == 0 && lHoshi )
         type = 'h';
      else if( lMarker == C.GOBM_LETTER ) {
         if( label >= 'a' && label <= 'z' )
            type = 'l' + label;
      }
      else if( (formMarker = BC.MAP_FORM_MARKERS[lMarker]) && lStone == 0 && bLineType )
         type = bLineType + formMarker;
      else if( lMarker == 0 && lStone == 0 ) {
         type = this.getBoardLineType( lBoard, false );
         if( !type ) type = 'dot'; // empty-cell default
      }

      var sgf_coord = this.makeSgfCoords(x,y);
      if( type )
         $("td#" + sgf_coord + " img").attr("src", base_path + this.stone_size + '/' + type + '.gif' );
   }, //write_image

   // mixed=true : allow board-lines mixed with markers
   getBoardLineType : function( board_lines, mixed ) {
      board_lines &= C.GOBB_BITMASK;
      if( !mixed && board_lines == (C.GOBB_NORTH|C.GOBB_SOUTH) )
         return 'du';
      return BC.MAP_BOARDLINES[board_lines];
   },

   makeSgfCoords : function( x, y ) {
      return String.fromCharCode(0x60 + x) + String.fromCharCode(0x60 + y); //0x61=a, x/y=1..n
   }

}); //Board




// ---------- GameEditor -------------------------------------------------------

// constructs GameEditor
DGS.GameEditor = function( stone_size ) {
   this.goban = new DGS.Goban(); // DGS.Goban to keep board-state
   this.board = new DGS.Board( stone_size ); // DGS.Board for drawing board
   this.board_storage = null; // for restoring board
};

$.extend( DGS.GameEditor.prototype, {

   /*
    * $moves_data = space-separate String splitted into $game_moves
    * Syntax $game_moves := [ game_move* ]
    *    game_move := move_nr ( color move | prisoners | setup )
    *    move_nr   := digit+
    *    color     := "b" | "w"
    *    move      := ( "a".."z" "a".."z" | "_P" )       ; _P = pass
    *    TODO prisoners := "P" black_prisoners "/" white_prisoners
    *    TODO setup     := ( COLOR "S" move+ )+
    *    TODO COLOR     := "B" | "W"
    *    TODO territory := ( COLOR "T" move+ )+               ; for LATER
    */
   parseMoves : function( size_x, size_y, moves_data ) {
      this.goban.setSizeX( size_x );
      this.goban.setSizeY( size_y );
      this.goban.makeBoard( size_x, size_y, true );

      var game_tree = [], result;

      for( var mvd in moves_data.split(' ') ) {
         if( (result = /^(\d+\.)?([bw])([a-z][a-z])$/.exec(mvd)) ) { // pattern: 123.czz
            var mnr = result[1]; // move-nr (optional)
            var col = result[2];
            var pos = result[3];

            //var changes = this.calculateMoveChanges( col, pos ); // TODO impl. has-lib-check, calc prisoners/captured-stones
            //var treenode = new DGS.TreeNode( mnr, col, pos, 'MOVE', changes ); //TODO
            //game_tree.push( treenode );
         }
      }
   }, //parseMoves

   drawBoard : function() {
      this.goban.clearBoard();
      this.board.draw_board( this.goban, true );

      //TODO test
      var changes = new DGS.GobanChanges();
      changes.add_change( 4,4, C.GOBS_BLACK | C.GOBM_TRIANGLE, '' );
      changes.add_change( 6,6, C.GOBS_WHITE | C.GOBM_NUMBER, '+4' );
      changes.add_change( 8,8, C.GOBM_LETTER, '+u' );
      changes.add_change( 9,9, C.GOBM_SQUARE | C.GOBO_HOSHI, '' );
      changes.add_change( 2,2, C.GOBB_MID, '' );
      changes.apply_changes( this.goban );

      this.board.draw_goban_changes( this.goban, changes );
   },

   saveBoard : function() {
      this.board_storage = $("table#Goban").html();
   },

   restoreBoard : function() {
      if( this.board_storage ) {
         $("table#Goban").html(this.board_storage);
         this.board_storage = null;
      }
   }

}); //GameEditor

})(jQuery);

// -->
