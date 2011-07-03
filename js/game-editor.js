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

var DBG;
//$("#gameNotes").val(DBG); //TODO DBG @ game-viewer

(function($) { // ensure local scope and $ == jQuery


// --------------- Global Functions -------------------------------------------

DGS.game_editor = {
   loadPage : function() {
      $(document).ready( DGS.game_editor.initPage );
   },

   initPage : function() {
      $( function() {
         $("#tabs").tabs({ disabled: [], selected: 1 });
      });

      $("#tab_Size input#size_upd").click( function(event) {
         event.preventDefault();
         DGS.run.gameEditor.action_size_updateSize();
      });

      $("#tab_Edit a.Tool").click( function(event) {
         event.preventDefault();
         DGS.run.gameEditor.action_edit_handle_tool( this );
      });
      $("#tab_Edit a.UndoTool").click( function(event) {
         event.preventDefault();
         DGS.run.gameEditor.action_edit_handle_undo_tool( this );
      });


      DGS.run.gameEditor.testBoard(); //TODO test
   }
};

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
   },

   highlight : function( selector ) {
      $(selector).effect("highlight", { color: '#FF0000' }).focus();
   },

   makeSgfCoords : function( x, y ) { // x/y=1..n
      return String.fromCharCode(0x60 + x) + String.fromCharCode(0x60 + y); //0x61=a
   },

   // SGF-single-coord: a..y -> 1..25
   makeNumberCoord : function( sgf_part ) {
      if( sgf_part < 'a' || sgf_part > 'y' )
         throw "DGS.utils.makeNumberCoord("+sgf_part+"): invalid args";
      return sgf_part.charCodeAt(0) - 0x60; //0x61=a
   },

   debug : function( msg ) {
      //TODO return;
      msg = (msg + "\n" + $("#D").text()).substr(0, 200);
      $("#D").text( msg );
   }

};



// --------------- GobanLabels ------------------------------------------------

DGS.GobanLabels = function() {
   this.numbers = []; // 1..500 allowed number-labels
   this.letters = []; // 1..26  allowed letter-labels
   this.next_number = 1;
   this.next_letter = 1;
};

$.extend( DGS.GobanLabels.prototype, {

   set_label : function( label ) {
      if( label >= 1 && label <= 500 ) {
         this.numbers[label] = label;
         while( this.numbers[this.next_number] )
            this.next_number++;
      } else if( label >= 'a' && label <= 'z' ) {
         label = label.charCodeAt(0) - 0x60; //0x61=a
         this.letters[label] = label;
         while( this.letters[this.next_letter] )
            this.next_letter++;
      } else {
         throw "DGS.GobalLabels.set_label(" + label + "): invalid label ["+typeof(label)+"] len ["+label.length+"]";
      }
   },

   clear_label : function( label ) {
      var pos;
      if( label >= 1 && label <= 500 ) {
         this.numbers[label] = 0;
         if( label < this.next_number )
            this.next_number = label;
      } else if( label >= 'a' && label <= 'z' ) {
         label = label.charCodeAt(0) - 0x60; //0x61=a
         this.letters[label] = 0;
         if( label < this.next_letter )
            this.next_letter = label;
      } else {
         throw "DGS.GobalLabels.clear_label(" + label + "): invalid label ["+typeof(label)+"] len ["+label.length+"]";
      }
   },

   // returns next-label for type=GOBM_NUMBER|LETTER or 0 if there are no next-labels
   get_next_label : function( type ) {
      if( type == C.GOBM_NUMBER ) {
         return ( this.next_number <= 500 ) ? this.next_number : 0;
      } else if( type == C.GOBM_LETTER ) {
         return ( this.next_letter <= 26 ) ? String.fromCharCode(0x60 + this.next_letter) : 0; //0x61=a
      } else {
         throw "DGS.GobalLabels.get_next_label(" + type + "): invalid type";
      }
   },

   // returns hash-value for current state, used to check if edit-label-tools need update in GUI
   get_hash : function () {
      return this.next_number + 500 * this.next_letter;
   }

});



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

   GOBALL_BITMASK : 0x000F | 0x0080 | 0x0070 | 0x0F00,

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
   // bitmask using GOBB_NORTH|SOUTH|WEST|EAST enabling coordinates on that side of the go-board.
   this.opts_coords = 0;
   this.show_coords = true; // true to show coordinates

   // matrix[y][x] = GOB-bitmask | [ GOB-value-bitmask, number|letter (=label) ]; x/y=1..n
   // NOTE: makeBoard() MUST be called to properly init matrix
   this.matrix = [];

   // 1..max_x/y really HAS something on board, can be partial board of size_x/y
   this.max_x = this.max_y = 1;

   // for starting y-coordinates-label
   this.size_x = this.size_y = 1;

   this.goban_labels = new DGS.GobanLabels();
};

$.extend( DGS.Goban.prototype, {

   setOptionsCoords : function( coords, showCoords ) {
      this.opts_coords = (coords & C.GOBB_BITMASK);
      this.show_coords = showCoords;
   },

   getOptionsCoords : function() {
      return (this.show_coords) ? this.opts_coords : 0;
   },

   setSize : function( size_x, size_y ) {
      this.size_x = size_x;
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
      for( var y=1; y <= height; y++) {
         var board_lines = C.GOBB_MID;
         if( y == 1 )
            board_lines &= ~C.GOBB_NORTH;
         else if( y == height )
            board_lines &= ~C.GOBB_SOUTH;

         this.matrix[y] = [];
         for( var x=1; x <= width; x++) {
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

   // internal, overwriting layer-value; does NOT set/clear goban_labels
   setValue : function( x, y, value, label ) { // label optional (=undefined) if value is array
      this.max_x = Math.max( x, this.max_x );
      this.max_y = Math.max( y, this.max_y );

      var old_label = this.getMarkerLabel(x,y);
      var is_arr_value = $.isArray(value);
      if( is_arr_value )
         label = value[C.GOBMATRIX_LABEL];

      if( is_arr_value )
         this.matrix[y][x] = value;
      else if( typeof(value) != 'number' )
         throw "Goban.setValue("+x+","+y+","+value+","+label+"): invalid_args value";
      else if( label )
         this.matrix[y][x] = [ value, label ];
      else
         this.matrix[y][x] = value; // optimization to avoid too many object-instances

      if( old_label != label ) {
         if( !old_label && label ) {
            this.goban_labels.set_label( label );
         } else if( old_label && !label ) {
            this.goban_labels.clear_label( old_label );
         } else { // update-label (both-labels != '')
            this.goban_labels.clear_label( old_label );
            this.goban_labels.set_label( label );
         }
      }
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

   getValueSgf : function( coord_sgf ) {
      var x = DGS.utils.makeNumberCoord( coord_sgf.charAt(0) );
      var y = DGS.utils.makeNumberCoord( coord_sgf.charAt(1) );
      return this.getValue(x,y);
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

   setMarker : function( x, y, marker_value, label ) {
      marker_value &= C.GOBM_BITMASK;
      var upd_arr = this.getValue(x,y);
      upd_arr[C.GOBMATRIX_VALUE] = ( upd_arr[C.GOBMATRIX_VALUE] & ~C.GOBM_BITMASK ) | marker_value;
      upd_arr[C.GOBMATRIX_LABEL] = label;
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
   //arr: [ x, y, change-bitmask, value, label_diff ]; x/y=1..n, mask=int, diff=+L - ""
   this.changes = [];
   this.undo_changes = []; // needs current Goban for calculation
};

$.extend( DGS.GobanChanges.prototype, {

   // param label_diff: if label-changed "+L|-" then also value-bitmask and value must set/clear according GOBM_LETTER|NUMBER
   add_change : function( x, y, change_mask, value, label_diff ) {
      this.changes.push( [ x, y, change_mask, value, label_diff ] );
   },

   add_change_sgf : function( sgf_xy, change_mask, value, label_diff ) {
      var x = DGS.utils.makeNumberCoord( sgf_xy.charAt(0) );
      var y = DGS.utils.makeNumberCoord( sgf_xy.charAt(1) );
      this.changes.push( [ x, y, change_mask, value, label_diff ] );
   },

   has_changes : function() {
      return (this.changes.length > 0);
   },

   // apply GobanChanges in this object on given Goban, return number of real updates
   apply_changes : function( goban ) {
      return this.apply_goban_changes( goban, this.changes, true );
   }, //apply_changes

   apply_undo_changes : function( goban ) {
      return this.apply_goban_changes( goban, this.undo_changes, false );
   },

   // internal only
   apply_goban_changes : function( goban, changes, create_undo ) {
      var count_updates = 0, chg, x, y, change_mask, value, label_diff, arrval, old_value, old_label, new_value, new_label;

      if( create_undo )
         this.undo_changes = [];

      for( var i=0; i < changes.length; i++ ) {
         chg = changes[i];
         x = chg[0], y = chg[1], change_mask = chg[2], value = chg[3], label_diff = chg[4];

         if( change_mask || label_diff ) {
            arrval = goban.getValue(x,y);
            old_value = new_value = arrval[C.GOBMATRIX_VALUE];
            old_label = new_label = arrval[C.GOBMATRIX_LABEL];

            if( change_mask )
               new_value = (new_value & ~change_mask) | value;
            new_label = ( label_diff == '-' ) ? '' : label_diff;

            if( old_value != new_value || old_label != new_label ) {
               goban.setValue( x, y, [ new_value, new_label ] );
               count_updates++;

               // calculate compensation for undo
               if( create_undo )
                  this.undo_changes.push( [ x, y, C.GOBALL_BITMASK, old_value, old_label ] );
            }
         }
      }
      return count_updates;
   } //apply_goban_changes

}); //GobanChanges




// --------------- Board ------------------------------------------------------

// Usage (Board): drawing goban by updating images on board
// see 'include/goban_handler_gfx.php'

DGS.constants.Board = {
   MAP_TERRITORY_MARKERS : DGS.utils.build_map([
      C.GOBM_TERR_B       , 'b',
      C.GOBM_TERR_W       , 'w',
      C.GOBM_TERR_NEUTRAL , 'g',
      C.GOBM_TERR_DAME    , 'd'
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
   ]),

   // see $woodbgcolors in 'include/std_functions.php'
   ARR_WOODBGCOLORS : [ 'white', /*1*/'#e8c878', '#e8b878', '#e8a858', '#d8b878', '#b88848' ]
};
var BC = DGS.constants.Board;


// constructs Board
DGS.Board = function( stone_size, wood_color ) {
   this.stone_size = (stone_size == undefined) ? 25 : stone_size;
   this.wood_color = wood_color;
};

$.extend( DGS.Board.prototype, {

   // redraw board-structure without board-content, used after size-change
   draw_board_structure : function( goban, withActions ) {
      if( withActions == undefined )
         withActions = false;
      $("#Goban tbody > *").hide().remove();
      var tbody = $("table#Goban tbody");

      var coord_width = Math.floor( this.stone_size * 31 / 25 );
      var table_width = goban.max_x * this.stone_size;

      // init board-layout options
      var opts_coords = goban.getOptionsCoords();
      var add_width_west = ( opts_coords & C.GOBB_WEST ) ? coord_width : 0;
      var add_width_east = ( opts_coords & C.GOBB_EAST ) ? coord_width : 0;
      table_width += add_width_west + add_width_east;

      var coord_alt = '.gif" alt="';
      var coord_end = "\"></td>\n";
      var coord_start_number, coord_start_letter, coord_left = '', coord_right = '';
      if( opts_coords & (C.GOBB_WEST | C.GOBB_EAST) )
         coord_start_number = "<td class=brdn><img class=brdn src=\"" + base_path + this.stone_size + "/c";
      if( opts_coords & (C.GOBB_NORTH | C.GOBB_SOUTH) ) {
         coord_start_letter = "<td class=brdl><img class=brdl src=\"" + base_path + this.stone_size + "/c";

         var coord_tmp = "<td><img src=\"" + base_path + "images/blank.gif\" width=" + add_width_west + " height=" + this.stone_size + " alt=\" \"></td>\n";
         if( opts_coords & C.GOBB_WEST )
            coord_left = coord_tmp;
         if( opts_coords & C.GOBB_EAST )
            coord_right = coord_tmp;
      }

      var borders = opts_coords;
      var start_col = 0;
      if( (goban.size_x > goban.max_x && !(borders & C.GOBB_WEST)) )
         start_col = goban.size_x - goban.max_x;

      var start_row = goban.size_y;
      if( (goban.size_y > goban.max_y && !(borders & C.GOBB_NORTH)) || (goban.size_y < goban.max_y ) )
         start_row = goban.max_y;

      // ---------- Goban ------------------------------------------------

      var table_styles = {};
      table_styles['width'] = table_width + "px";

      var table_attr = {};
      table_attr['border'] = 0;
      table_attr['cellspacing'] = 0;
      table_attr['cellpadding'] = 0;
      if( this.wood_color > 10 ) {
         $("#Goban").removeAttr('background-image');
         var bcol = BC.ARR_WOODBGCOLORS[this.wood_color - 10];
         table_attr['bgcolor'] = bcol;
         table_styles['background-color'] = bcol;
      } else {
         $("#Goban").removeAttr('bgcolor').removeAttr('background-color');
         table_styles['background-image'] = "url(" + base_path + "images/wood" + this.wood_color + ".gif)";
      }
      $("#Goban").css(table_styles).attr(table_attr);

      var row, img;
      var blank_image = "<img src=\"" + base_path + "images/dot.gif\">";
      if( withActions )
         blank_image = '<a href="#">' + blank_image + '</a>';

      if( opts_coords & C.GOBB_NORTH ) {
         row = this.make_coord_row( goban.max_x, start_col, coord_start_letter, coord_alt, coord_end, coord_left, coord_right );
         tbody.append( $(row) );
      }

      for( var rownr = start_row, y = 1; y <= goban.max_y; rownr--, y++ ) {
         row = ( opts_coords & C.GOBB_WEST ) ? coord_start_number + rownr + coord_alt + rownr + coord_end : '';
         for( var x = 1; x <= goban.max_x; x++ ) {
            row += '<td id=' + DGS.utils.makeSgfCoords(x,y) + " class=brdx>" + blank_image + "</td>\n";
         }
         if( opts_coords & C.GOBB_EAST )
            row += coord_start_number + rownr + coord_alt + rownr + coord_end;
         $('<tr>' + row + '</tr>').appendTo(tbody);
      }//for y

      if( opts_coords & C.GOBB_SOUTH ) {
         row = this.make_coord_row( goban.max_x, start_col, coord_start_letter, coord_alt, coord_end, coord_left, coord_right );
         tbody.append( $(row) );
      }

      $("#GameEditor div.GobanGfx").css('width', table_width + 'px');
      if( withActions )
      $("#GameEditor td.brdx a").click( function(event) {
         DGS.run.gameEditor.action_handle_board( this, event );
         event.preventDefault();
      });

      $("#Goban tbody").show();
   }, //draw_board_structure

   make_coord_row : function( max_x, start_val, coord_start_letter, coord_alt, coord_end, coord_left, coord_right ) {
      var out = '', letterIdx = 0, letter;
      for( var colnr = 1; colnr <= max_x; colnr++ ) {
         if( letterIdx == 8 ) letterIdx++; // skip 8='i'
         letter = String.fromCharCode(0x61 + start_val + letterIdx); //0x61=a
         out += coord_start_letter + letter + coord_alt + letter + coord_end;
         letterIdx++;
      }
      return '<tr>' + (coord_left ? coord_left : '') + out + (coord_right ? coord_right : '') + "</tr>\n";
   }, //make_coord_row

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
         if( !visited[key] ) { //TODO why not execute ALL changes on same x/y-coord ?
            arrval = goban.getValue(x,y);
            this.write_image( x, y, arrval[C.GOBMATRIX_VALUE], arrval[C.GOBMATRIX_LABEL] );
            visited[key] = 1;
         }
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

      var sgf_coord = DGS.utils.makeSgfCoords(x,y);
      if( type )
         $("td#" + sgf_coord + " img").attr("src", base_path + this.stone_size + '/' + type + '.gif' );
   }, //write_image

   // mixed=true : allow board-lines mixed with markers
   getBoardLineType : function( board_lines, mixed ) {
      board_lines &= C.GOBB_BITMASK;
      if( !mixed && board_lines == (C.GOBB_NORTH|C.GOBB_SOUTH) )
         return 'du';
      return BC.MAP_BOARDLINES[board_lines];
   }

}); //Board




// ---------- ChangeCalculator -------------------------------------------------

// constructs ChangeCalculator
DGS.ChangeCalculator = function() {
};

$.extend( DGS.ChangeCalculator.prototype, {

   /*
    * Calculates GobanChanges to place stone with given color on Goban at given sgf-coordinates.
    * \param $new_stone = C.GOBS_BLACK|WHITE|EMPTY
    * \note only for allowed combinations:
    *    - GOBS_EMPTY NOT-OK with GOBM_MARK|NUMBER
    *    - GOBS_BLACK|WHITE NOT-OK with GOBM_LETTER|TERR_NEUTRAL|TERR_DAME
    *    - GOBS_BLACK NOT-OK with GOBM_TERR_B
    *    - GOBS_WHITE NOT-OK with GOBM_TERR_W
    */
   calc_goban_change_set_stone : function( goban, coord, new_stone ) {
      new_stone &= C.GOBS_BITMASK;

      // clear marker for invalid combinations
      var old_marker = ( goban.getValueSgf( coord )[C.GOBMATRIX_VALUE] & C.GOBM_BITMASK );
      var gob_mask = C.GOBS_BITMASK;
      var chg_label = '';

      if( new_stone == C.GOBS_EMPTY ) {
         if( old_marker == C.GOBM_MARK ) {
            gob_mask |= C.GOBM_BITMASK;
         } else if( old_marker == C.GOBM_NUMBER ) {
            gob_mask |= C.GOBM_BITMASK;
            chg_label = '-';
         }
      } else { // new_stone == B|W
         if( old_marker == C.GOBM_LETTER ) {
            gob_mask |= C.GOBM_BITMASK;
            chg_label = '-';
         } else if( old_marker == C.GOBM_TERR_NEUTRAL || old_marker == C.GOBM_TERR_DAME ) {
            gob_mask |= C.GOBM_BITMASK;
         } else if( new_stone == C.GOBS_BLACK && old_marker == C.GOBM_TERR_B ) {
            gob_mask |= C.GOBM_BITMASK;
         } else if( new_stone == C.GOBS_WHITE && old_marker == C.GOBM_TERR_W ) {
            gob_mask |= C.GOBM_BITMASK;
         }
      }

      var goban_changes = new DGS.GobanChanges();
      goban_changes.add_change_sgf( coord, gob_mask, new_stone, chg_label );
      return goban_changes;
   }, //calc_goban_change_set_stone

   /*
    * Calculates GobanChanges to toggle stone (new_stone) on Goban at given sgf-coordinates.
    * \param $new_stone = C.GOBS_BLACK|WHITE
    * \note only for allowed combinations:
    *    - toggle empty into new-stone color
    *    - if old-stone is new-stone => toggle to empty-stone; otherwise toggle to new-stone color
    *    - toggle only into allowed combinations (see calc_goban_change_set_stone-method)
    */
   calc_goban_change_toggle_stone : function( goban, coord, new_stone ) {
      var arrval = goban.getValueSgf( coord );
      var old_value = (arrval[C.GOBMATRIX_VALUE] & (C.GOBS_BITMASK|C.GOBM_BITMASK));
      var old_stone  = (old_value & C.GOBS_BITMASK);
      var old_marker = (old_value & C.GOBM_BITMASK);

      var is_trg_stone = ( old_stone == new_stone );
      var trg_stone = ( old_stone == C.GOBS_EMPTY || !is_trg_stone ) ? new_stone : C.GOBS_EMPTY;

      // clear marker for invalid combinations
      var new_value = trg_stone | old_marker;
      var chg_label = '';

      if( old_marker == C.GOBM_LETTER || ((old_marker == C.GOBM_NUMBER || old_marker == C.GOBM_MARK) && is_trg_stone) ) {
         new_value &= ~C.GOBM_BITMASK;
         chg_label = '-';
      } else if( old_marker == C.GOBM_TERR_B && trg_stone == C.GOBS_BLACK ) {
         new_value &= ~C.GOBM_BITMASK;
      } else if( old_marker == C.GOBM_TERR_W && trg_stone == C.GOBS_WHITE ) {
         new_value &= ~C.GOBM_BITMASK;
      } else if( old_marker == C.GOBM_TERR_NEUTRAL || old_marker == C.GOBM_TERR_DAME ) {
         new_value &= ~C.GOBM_BITMASK;
      }

      var goban_changes = new DGS.GobanChanges();
      goban_changes.add_change_sgf( coord, C.GOBS_BITMASK | C.GOBM_BITMASK, new_value, chg_label );
      return goban_changes;
   }, //calc_goban_change_toggle_stone

   /*
    * Calculates GobanChanges to toggle marker on Goban at given sgf-coordinates.
    * \param $new_marker = C.GOBM_MARK|CIRCLE|SQUARE|TRIANGLE|CROSS|TERR_B/W/DAME/NEUTRAL
    * \note only for allowed combinations:
    *    - if old-marker is new-marker => toggle to empty-marker; otherwise toggle to new-marker
    *    - toggle only into allowed combinations (see calc_goban_change_set_stone-method)
    *    - toggle mark only on B/W-stones
    */
   calc_goban_change_toggle_marker : function( goban, coord, new_marker ) {
      var goban_changes = new DGS.GobanChanges();

      var old_value = goban.getValueSgf( coord )[C.GOBMATRIX_VALUE];
      var old_stone  = (old_value & C.GOBS_BITMASK);
      var old_marker = (old_value & C.GOBM_BITMASK);

      // clear marker for invalid combinations
      var trg_marker = new_marker;
      var gob_mask = C.GOBM_BITMASK;
      var chg_label = '';

      if( old_marker == new_marker ) {
         trg_marker = C.GOBM_EMPTY;
      } else if( new_marker == C.GOBM_MARK ) {
         if( old_stone != C.GOBS_BLACK && old_stone != C.GOBS_WHITE )
            return goban_changes; // no change
      } else {
         if( old_marker == C.GOBM_NUMBER || old_marker == C.GOBM_LETTER )
            chg_label = '-';

         //NOTE: nothing special for: if( new_marker == C.GOBM_CIRCLE || new_marker == C.GOBM_SQUARE || new_marker == C.GOBM_TRIANGLE || new_marker == C.GOBM_CROSS )
         if( new_marker == C.GOBM_TERR_NEUTRAL || new_marker == C.GOBM_TERR_DAME ) {
            gob_mask |= C.GOBS_BITMASK;
         } else if( old_stone != C.GOBS_EMPTY && (new_marker == C.GOBM_TERR_B || new_marker == C.GOBM_TERR_W) ) {
            if( old_stone == C.GOBS_BLACK && new_marker == C.GOBM_TERR_B ) {
               gob_mask |= C.GOBS_BITMASK; // clear stone
            } else if( old_stone == C.GOBS_WHITE && new_marker == C.GOBM_TERR_W ) {
               gob_mask |= C.GOBS_BITMASK; // clear stone
            }
         }
      }

      goban_changes.add_change_sgf( coord, gob_mask, trg_marker, chg_label );
      return goban_changes;
   }, //calc_goban_change_toggle_marker

   /*
    * Calculates GobanChanges to toggle number- or letter-label on Goban at given sgf-coordinates.
    * \param $label_type = C.GOBM_NUMBER|LETTER
    * \note only for allowed combinations:
    *    - toggle number-label only on B|W-stone between next-number-label and empty
    *    - toggle letter-label only on empty-stone between next-letter-label and empty (clear stone if necessary)
    */
   calc_goban_change_toggle_label : function( goban, coord, label_type ) {
      var old_value = goban.getValueSgf( coord )[C.GOBMATRIX_VALUE];
      var old_stone  = (old_value & C.GOBS_BITMASK);
      var old_marker = (old_value & C.GOBM_BITMASK);

      // clear marker for invalid combinations
      var gob_mask = C.GOBM_BITMASK;
      var chg_label = undefined;
      var next_label = goban.goban_labels.get_next_label( label_type );

      if( label_type == C.GOBM_NUMBER ) { // number only WITH B/W-stones
         if( old_stone != C.GOBS_EMPTY )
            chg_label = ( old_marker == label_type ) ? '-' : next_label;
      } else if( label_type == C.GOBM_LETTER ) { // letter only WITHOUT B/W-stone
         gob_mask |= C.GOBS_BITMASK; // clear stone
         chg_label = ( old_marker == label_type ) ? '-' : next_label;
      }

      var goban_changes = new DGS.GobanChanges();
      if( chg_label != undefined ) {
         var trg_marker = ( chg_label == '-' ) ? C.GOBM_EMPTY : label_type;
         goban_changes.add_change_sgf( coord, gob_mask, trg_marker, chg_label );
      }
      return goban_changes;
   } //calc_goban_change_toggle_label

}); // ChangeCalculator




// ---------- GameEditor -------------------------------------------------------

// constructs GameEditor
DGS.GameEditor = function( stone_size, wood_color ) {
   this.goban = new DGS.Goban(); // DGS.Goban to keep board-state
   this.board = new DGS.Board( stone_size, wood_color ); // DGS.Board for drawing board
   this.calc = new DGS.ChangeCalculator(); // DGS.ChangeCalculator for calculating changes for goban & more
   this.board_storage = null; // for restoring board

   this.edit_tool_selected = null;
   this.history_undo = []; // GobanChanges-arr
};

DGS.GameEditor.CONFIG = {
   edit : {
      stone_tool : DGS.utils.build_map([
         'b', C.GOBS_BLACK,
         'w', C.GOBS_WHITE,
         'clear', C.GOBS_EMPTY
      ]),

      marker_tool : DGS.utils.build_map([
         'mark',     C.GOBM_MARK,
         'circle',   C.GOBM_CIRCLE,
         'square',   C.GOBM_SQUARE,
         'triangle', C.GOBM_TRIANGLE,
         'cross',    C.GOBM_CROSS,
         'terr_b',   C.GOBM_TERR_B,
         'terr_w',   C.GOBM_TERR_W,
         'terr_neutral', C.GOBM_TERR_NEUTRAL,
         'terr_dame',    C.GOBM_TERR_DAME
      ]),

      label_tool : DGS.utils.build_map([
         'number', C.GOBM_NUMBER,
         'letter', C.GOBM_LETTER
      ])
   } //edit
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
      this.goban.setSize( size_x, size_y );
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
   },

   testBoard : function() {
      this.goban.setSize(9,9);
      this.goban.makeBoard( this.goban.size_x, this.goban.size_y, true );
      this.goban.setOptionsCoords( C.GOBB_MID, true );
      this.board.draw_board_structure( this.goban, true );
      this.board.draw_board( this.goban, false );

      // init game-editor
      this.update_label_tool();

      /*
      var changes = new DGS.GobanChanges();
      changes.add_change( 4,4, C.GOBS_BITMASK | C.GOBM_BITMASK, C.GOBS_BLACK | C.GOBM_TRIANGLE, '' );
      changes.add_change( 6,6, C.GOBS_BITMASK | C.GOBM_BITMASK, C.GOBS_WHITE | C.GOBM_NUMBER, '+4' );
      changes.add_change( 8,8, C.GOBM_BITMASK, C.GOBM_LETTER, '+u' );
      changes.add_change( 9,9, C.GOBM_BITMASK | C.GOBO_HOSHI, C.GOBM_SQUARE | C.GOBO_HOSHI, '' );
      changes.add_change( 2,2, C.GOBB_BITMASK, C.GOBB_EMPTY, '' );
      changes.apply_changes( this.goban );

      this.board.draw_goban_changes( this.goban, changes );
      */
   },

   saveBoard : function() {
      this.board_storage = $("table#Goban").html();
   },

   restoreBoard : function() {
      if( this.board_storage ) {
         $("table#Goban").html(this.board_storage);
         this.board_storage = null;
      }
   },


   // ---------- Actions on SIZE-tab -------------------------------------------

   action_size_updateSize : function() {
      // check inputs
      var width  = $('#size_w').val();
      var height = $('#size_h').val();
      var error = false;
      if( !width || !parseInt(width,10) || width < 2 || width > 25 ) {
         DGS.utils.highlight('#size_w');
         return false;
      }
      if( !height || !parseInt(height,10) || height < 2 || height > 25 ) {
         DGS.utils.highlight('#size_h');
         return false;
      }

      // re-init board
      this.goban.setSize( width, height );
      this.goban.makeBoard( width, height, true );
      this.goban.setOptionsCoords( C.GOBB_MID, true );
      this.board.draw_board_structure( this.goban, true );
      this.board.draw_board( this.goban, false );
      return true;
   },

   // ---------- Actions on EDIT-tab -------------------------------------------

   action_edit_handle_tool : function( $tool ) { // $tool = selected edit-tool
      if( this.edit_tool_selected == $tool )
         return;

      if( this.edit_tool_selected != null )
         $(this.edit_tool_selected).toggleClass('ToolSelected', false);
      this.edit_tool_selected = $tool;
      $($tool).toggleClass('ToolSelected', true);

      DGS.utils.debug( $tool.id );
   }, //action_edit_handle_tool

   action_edit_handle_undo_tool : function( $tool ) {
      var dbg = $tool.id;

      if( $tool.id == 'edit_tool_undo' ) {
         if( this.history_undo.length > 0 ) {
            var label_hash = this.goban.goban_labels.get_hash();
            var goban_changes = this.history_undo.pop();

            if( goban_changes.apply_undo_changes( this.goban ) ) {
               this.board.draw_goban_changes( this.goban, goban_changes );
               this.update_label_tool( label_hash );
               this.update_history_tool();
            }
         }
      }

      DGS.utils.debug( dbg );
   }, //action_edit_handle_undo_tool

   action_handle_board : function( $point, $event ) { // $point = clicked board-point, $event = event for click
      var point_id = $($point).parent().attr('id'); // SGF-coord
      var dbg = point_id;

      if( this.edit_tool_selected != null ) {
         var tool_id = this.edit_tool_selected.id;
         var goban_changes, value;

         // calculate goban-change
         // --- STONE-tools ---
         if( (result = tool_id.match(/^edit_tool_(b|w|clear)_stone$/)) ) {
            value = DGS.GameEditor.CONFIG.edit.stone_tool[ result[1] ];
            goban_changes = this.calc.calc_goban_change_set_stone( this.goban, point_id, value );

         } else if( tool_id == 'edit_tool_toggle_stone' ) {
            value = ( $event.shiftKey ) ? C.GOBS_WHITE : C.GOBS_BLACK;
            goban_changes = this.calc.calc_goban_change_toggle_stone( this.goban, point_id, value );
         }
         // --- MARKER-tools ---
         else if( (result = tool_id.match(/^edit_tool_(circle|square|triangle|cross|terr_(b|w|neutral|dame))_marker$/)) ) {
            value = DGS.GameEditor.CONFIG.edit.marker_tool[ result[1] ];
            goban_changes = this.calc.calc_goban_change_toggle_marker( this.goban, point_id, value );
         }
         // --- LABEL-tools ---
         else if( (result = tool_id.match(/^edit_tool_(number|letter)_label$/)) ) {
            value = DGS.GameEditor.CONFIG.edit.label_tool[ result[1] ];
            goban_changes = this.calc.calc_goban_change_toggle_label( this.goban, point_id, value );
         }

         // draw-goban-change
         var label_hash = this.goban.goban_labels.get_hash();
         if( goban_changes.apply_changes( this.goban ) ) {
            this.board.draw_goban_changes( this.goban, goban_changes );
            this.update_label_tool( label_hash );
            this.save_change_history( goban_changes );
         }
      }

      DGS.utils.debug( dbg );
   }, //action_handle_board

   // old_hash=undefined to redraw both Number/Letter-label-tools
   update_label_tool : function( old_hash ) {
      if( old_hash == undefined || (old_hash != this.goban.goban_labels.get_hash() ) ) {
         // 0=no-next-label -> keep former label
         var next_number_label = this.goban.goban_labels.get_next_label( C.GOBM_NUMBER );
         var next_letter_label = this.goban.goban_labels.get_next_label( C.GOBM_LETTER );
         if( next_number_label )
            $("#edit_tool_number_label span.LabelTool").text( next_number_label );
         if( next_letter_label )
            $("#edit_tool_letter_label span.LabelTool").text( next_letter_label );
      }
   },

   save_change_history : function( goban_changes ) {
      this.history_undo.push( goban_changes );
      this.update_history_tool();
   },

   update_history_tool : function() {
      $("#edit_tool_undo_hist").text( String.sprintf("(%s)", this.history_undo.length) );
   }

   // ---------- Actions (END) -------------------------------------------------

}); //GameEditor

})(jQuery);

// -->
