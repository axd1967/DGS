// <!--
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

// NOTE: global DGS-object defined in common.js

(function($) { // ensure local scope and $ == jQuery


// --------------- Global Functions -------------------------------------------

DGS.utils = {
   /** Returns associative array[key_i]=val_i built from arr( key_1, val_1, key_2, val_2, ... ). */
   build_map : function( arr ) {
      if ( !$.isArray(arr) )
         throw "DGS.utils.build_map(): invalid args, array expected ["+arr+"]";
      if ( (arr.length & 2) == 1 )
         throw "DGS.utils.build_map(): invalid args, odd arr-length ["+arr.join(',')+"]";

      var map = [];
      for ( var i=0; i < arr.length; i += 2 ) {
         map[ arr[i] ] = arr[i+1];
      }
      return map;
   },

   highlight : function( selector ) {
      $(selector).effect("highlight", { color: '#FF0000' }).focus();
   },

   // builds SGF-coord (e.g. 'aa') from x/y-coordinate starting with 0
   makeSgfCoords : function( x, y ) {
      return String.fromCharCode(0x61 + x) + String.fromCharCode(0x61 + y); //0x61=a
   },

   // returns converted SGF-coord-part (e.g. 'a') to number coordinate (start with 0), e.g. 'a' -> 0
   makeNumberCoord : function( sgf_part ) {
      if ( sgf_part < 'a' || sgf_part > 'y' )
         throw "DGS.utils.makeNumberCoord("+sgf_part+"): invalid args";
      return sgf_part.charCodeAt(0) - 0x61; //0x61=a
   },

   // returns  {x/y: x/y|null=pass}  for SGF-coordinate, e.g. 'aa' -> {x:0, y:0}; '' (= pass-move) -> {x:null, y:null}
   makePointNumberCoord : function( sgf_coord ) {
      if ( !sgf_coord )
         return { x: null, y: null };

      return {
          x: DGS.utils.makeNumberCoord( sgf_coord.charAt(0) ),
          y: DGS.utils.makeNumberCoord( sgf_coord.charAt(1) )
      };
   },

   // returns array with value (if value is not already an array), otherwise just return value-array
   makeArray : function( value ) {
      return ( value instanceof Array ) ? value : [ value ];
   },

   debug : function( msg ) {
      //return; // uncomment to disable debugging
      msg = (msg + "\n" + $("#D").text()).substr(0, 200);
      $("#D").text( msg );
   }

}; //end of DGS.utils



// ---------- GamePageEditor ---------------------------------------------------

/*!
 * @class Constructs game-editor for game-page.
 */
DGS.GamePageEditor = function() {
   this.init.apply( this, arguments );
};

$.extend( DGS.GamePageEditor.prototype, {

   init : function( stone_size, wood_color, board_size, max_moves, view_move ) {
      var me = this;

      this.max_moves = max_moves;
      this.curr_move = view_move; // <0 = no-move selected
      this.board = new DGS.Board( stone_size, wood_color ); // DGS.Board for drawing board
      this.goban = new DGS.Goban( board_size, board_size, this.board ); // DGS.Goban to keep board-state
      this.root_game_collection = new DGS.GameNode(); // 1st GameNode is game-colllection-root, from which move #0 of game-roots are children
      this.cursor = null; // cursor on current game-root

      // handlers for the various types of GameNode properties;; inspired by Eidogo
      this.propertyHandlers = {
         B:  this.playMove,
         W:  this.playMove,
         AW: this.addStone,
         AB: this.addStone,
         d_capt: this.addCaptures,
         d_tp:   this.toggleTerritory
      };

      $(document).ready( function() { me.makeDocReady.apply(me); });
   },

   // prepare DOM setting to initial setup and binding actions for game-editor on game-page
   makeDocReady : function() {
      var me = this;

      me.board.reset_board();
      me.goban.render_board( false );

      $("#tabs").tabs({ active: 0 });

      $("#GameMsgTool_ToggleComment").click( { action: "hide" }, function( evt ) {
         me.handle_action_toggle_comment( this, evt );
      });
      $("#GameMsgTool_ScrollToCurrMove").click( function( evt ) {
         me.scrollToMoveMessage( me.curr_move, 200 );
      });
      $("#GameMessageBody a.MRef").click( function( evt ) {
         evt.preventDefault();
         me.handle_action_view_move( this, evt );
      });
      $("#Goban img.brdx").click( function( evt ) {
         me.handle_action_board_point( this, evt );
      });

      $("span#GameViewer img").click( function( evt ) {
         me.handle_action_move_navigation( this, evt );
      });
      $(document).keypress( function( evt ) {
         me.handle_key_press( evt );
      });

      $("#GameMessage").draggable({ handle: "#GameMessageHeader", opacity: 0.50 });
      $("#GameMessage").resizable({
         alsoResize: "#GameMessageBody",
         minWidth: 300, minHeight: 150,  maxWidth: 600, maxHeight: 600
      });
      $("#GameMessageBody").resizable();
      $("#GameMessageBody div.ui-resizable-handle").remove(); // removes resizable-handle for inner element

      $("#tabs").show(); // hidden till all elements built

      me.goto_move();
   }, //makeDocReady

   /**
    * Parses JSON-formatted game-tree into client-side used game-tree structure.
    * $tree_data = [ var-no, { PROP: VAL|ARR|OBJ, _vars: [ var-no, ... ] }, ... ]
    * Returns tree-structure like node = { PROP: VAL|ARR|OBJ, _children: [ node, ...] }
    **/
   parseGameTree : function( tree_data ) {
      var target = new DGS.GameNode();
      target.loadJsonFlatTree( tree_data );
      this.root_game_collection = target;

      var cursor = new DGS.GameCursor( target );
      this.cursor = new DGS.GameCursor( cursor.getRootGameNode() ); // cursor on move #0 of 1st game

      this.goban.clearBoard();
   }, //parseGameTree


   // handle toggle-comment action in header of game-move-messages box: hide <-> show move-comments
   handle_action_toggle_comment : function( elem, evt ) {
      var action = evt.data.action;
      if ( action == "hide" ) {
         evt.data.action = "show";
         $(elem).attr("src", "images/comment_show.png");
         $(elem).attr("title", T_gametools["show_comment"] );
         $(elem).attr("alt", T_gametools["show_comment"] );
         $("#GameMessageBody div.CBody").hide();
      } else { // unhide(=show)
         evt.data.action = "hide";
         $(elem).attr("src", "images/comment_hide.png");
         $(elem).attr("title", T_gametools["hide_comment"] );
         $(elem).attr("alt", T_gametools["hide_comment"] );
         $("#GameMessageBody div.CBody").show();
      }
   },

   // handle key-press events on document:
   // - cursor-left/right navigates back/forward in game-tree
   handle_key_press : function( evt ) {
      var code = evt.keyCode;
      var stop = true;
      var showAnalyseTab = false;

      switch ( code ) {
         case 37: // left
            if ( evt.ctrlKey )
               this.goto_move( 0 ); // first-move
            else
               this.goto_previous_node(); // prev-move
            showAnalyseTab = true;
            break;

         case 39: // CURSOR-right
            if ( evt.ctrlKey )
               this.goto_move( this.max_moves, /*from-curr-node*/true ); // last-move
            else
               this.goto_next_variation_node(); // next-move
            showAnalyseTab = true;
            break;

         default:
            stop = false;
            break;
      }

      if ( showAnalyseTab ) { // show Analyse-tab if not already active
         if ( $("#tabs").tabs('option', 'active') != 2 )
            $("#tabs").tabs({ active: 2 });
      }

      if ( stop )
         evt.preventDefault();
   },

   // handle action on clicking first/prev/next/last icons to navigate in game-tree
   handle_action_move_navigation : function( elem, evt ) {
      var id = $(elem).attr("id");
      if ( id == 'FirstMove' ) {
         this.goto_move( 0 );
      } else if ( id == 'PrevMove' ) {
         this.goto_previous_node();
      } else if ( id == 'NextMove' ) {
         this.goto_next_variation_node();
      } else if ( id == 'LastMove' ) {
         this.goto_move( this.max_moves, /*from-curr-node*/true );
      }
   },

   // handle click on move in move-message-box -> view selected move
   handle_action_view_move : function( elem, evt ) {
      var id = $(elem).closest("div[id^=movetxt]").attr("id");
      var move_nr = parseInt( id.substr(7), 10 );
      if ( move_nr != this.curr_move )
         this.goto_move( move_nr ); // go-to seems fast enough, so no optimized backward/forward-moving is required
   },

   // handle click on one of the board-images
   // - click on board-stone navigates to selected move by searching back in game-tree
   handle_action_board_point : function( elem, evt ) {
      var coord = $(elem).closest("td.brdx").attr("id"); // sgf-coord
      if ( this.cursor.node.getMove() == coord ) // current-move?
         return;

      // find stone-color: all B/W-stone-images start with 'b' or 'w'; otherwise it's an empty-point or label or no-stone
      var img = $(elem).attr("src").replace( /^.*?\/([bw])?.+$/, "$1" );
      if ( img == 'b' || img == 'w' ) {
         var stone_color = img.toUpperCase(); // prop in node: B/W

         // backward-search in game-tree for stone on selected coord
         var move_cursor = new DGS.GameCursor( this.cursor.node );
         var move_nr = -1; // -1 = not-found
         while ( move_cursor.previous() ) {
            if ( move_cursor.node[stone_color] == coord ) {
               move_nr = move_cursor.getDgsMoveNumber();
               break;
            }
         }

         if ( move_nr < 0 && move_cursor.getDgsMoveNumber() == 0 ) {
            // search in shape-setup
            var shapeSetup = move_cursor.node["A" + stone_color]; // prop: AB/AW
            if ( shapeSetup ) {
               if ( shapeSetup instanceof Array ) {
                  if ( shapeSetup.contains(coord) )
                     move_nr = 0;
               } else if ( shapeSetup == coord ) {
                  move_nr = 0;
               }
            }
         }

         if ( move_nr >= 0 )
            this.goto_move( move_nr );
      }
   }, // handle_action_board_point

   // handles going to the previous node in the game-tree
   // NOTE: taken from Eidogo (Player.back()) + adjusted
   goto_previous_node : function() {
      if ( this.cursor.previous() ) {
         this.goban.revert( 1 );
         this.refresh();
      } else if ( this.cursor.getDgsMoveNumber() == 1 ) {
         this.goto_move( 0 );
      }
   },

   // handles going to the next sibling or variation following preffered variation-path
   // return true = moved to next node, false = no next node (already on last node for wanted variation)
   // NOTE: taken from Eidogo (Player.variation()) + adjusted
   goto_next_variation_node : function() {
      if ( this.cursor.next() ) {
         this.executeNode();
         this.refresh();
         return true;
      } else {
         return false;
      }
   },

   // refresh board by re-committing current node & updating other UI-controls
   refresh : function() {
      this.setCurrentMove( this.cursor.getDgsMoveNumber() );

      var prisoners = this.goban.getPrisoners();
      $("span.BlackPrisoners").text( prisoners[0] );
      $("span.WhitePrisoners").text( prisoners[1] );

      this.goban.render( /*full*/false );
   },

   // scrolls move-message-box to show div with move-info for given move with given scroll-speed
   scrollToMoveMessage : function( move_nr, duration ) {
      var target = "#movetxt" + move_nr;
      if ( !$(target).length )
         target = ( move_nr < 1 ) ? 0 : 'max'; // use top or bottom if target-id not found
      $("#GameMessageBody").scrollTo( target,
         { axis: "xy", duration: duration, easing: "swing", queue: true });
   },

   // sets current-move
   // - mark it in move-message-box, unmark previous move-selection
   // - this.curr_move must be set for initial setup (with move_nr passed in as undefined)
   setCurrentMove : function( move_nr ) {
      if ( move_nr != undefined ) {
         if ( this.curr_move != move_nr && this.curr_move >= 0 )
            $("#movetxt" + this.curr_move + " div.Tools img.CurrMove").remove();
         this.curr_move = move_nr;
      }

      if ( this.curr_move >= 0 ) {
         this.scrollToMoveMessage( this.curr_move, 20 ); // scroll to selected move

         var elemId = "#movetxt" + this.curr_move + " div.Tools";
         if ( !$(elemId + " img.CurrMove").length )
            $(elemId).prepend('<img src="images/backward.gif" class="CurrMove" title="'+T_gametools['curr_move']+'">');
      }
   },


   // goto given move by forward-replaying nodes from start or current-node in game-tree
   // NOTE: taken from Eidogo + simplified
   goto_move : function( move_nr, from_curr_node ) {
      var varNum = 0; // navigate in first child-variations
      if ( move_nr == undefined )
         move_nr = this.curr_move;

      if ( !from_curr_node ) {
         this.goban.emptyBoard();
         this.cursor.resetToRootGameNode(); // reset to move #0
      }

      var skip_exec_node = from_curr_node;
      var dgsMoveNum;
      while ( (dgsMoveNum = this.cursor.getDgsMoveNumber()) <= move_nr ) {
         if ( skip_exec_node )
            skip_exec_node = false; // skip only first (current) node to execute, b/c already executed
         else
            this.executeNode( this.cursor.node );

         if ( dgsMoveNum >= move_nr )
            break;
         if ( !this.cursor.next(varNum) )
            break; // last node reached
      }

      this.refresh();
   },

   // apply properties from GameNode on Goban;; taken from Eidogo + simplified
   executeNode : function( node ) {
      var curr_node = (node) ? node : this.cursor.node;

      // execute handlers for the appropriate properties
      var props = curr_node.getProperties();
      for ( var propName in props ) {
         if ( this.propertyHandlers[propName] )
            this.propertyHandlers[propName].call( this, curr_node, propName );
      }

      this.goban.commit();
   },

   // SGF-prop-handler: plays a move on the board and apply rules to it; coord='' for PASS
   // @param node current node; node[prop] == coord with single SGF-coord or '' (for PASS)
   // @param prop property-name 'B' or 'W' which is the 'color' to play the move
   // NOTE: inspired by Eidogo
   playMove : function( node, prop ) {
      if ( prop != 'B' && prop != 'W' )
         return;

      var coord = node[prop];
      if ( coord ) { // move on board
         var pt = DGS.utils.makePointNumberCoord( coord );
         this.goban.setStone( pt.x, pt.y, ( prop == 'B' ? C.GOBS_BLACK : C.GOBS_WHITE ) );
         this.goban.replaceLastMove( pt.x, pt.y );

         //TODO handle captures, currently handled with d_capt-property added in game-tree by server
         //this.rules.apply(pt, color);
      } else { // PASS-move
         this.goban.replaceLastMove( null, null );
      }
   },

   // SGF-prop-handler: adds stones on the board
   // @param node current node; node[prop] == coords with single SGF-coord or SGF-coords-array
   // @param prop property-name 'AB' or 'AW' which is the 'color' to set a stone
   // NOTE: inspired by Eidogo
   addStone : function( node, prop ) {
      if ( prop != 'AB' && prop != 'AW' )
         return;

      var coords = DGS.utils.makeArray( node[prop] );
      for ( var i=0; i < coords.length; i++ ) {
         if ( coords[i] ) { // no pass-move allowed
            var pt = DGS.utils.makePointNumberCoord( coords[i] );
            this.goban.setStone( pt.x, pt.y, ( prop == 'AB' ? C.GOBS_BLACK : C.GOBS_WHITE ) );
         }
      }
   },

   // SGF-prop-handler: handle pre-stored captures from 'd_capt'-SGF-dgs-pseudo-property
   // @param node current node; node[prop] == coords with single SGF-coord or SGF-coords-array
   // @param prop DGS-pseudo-property 'd_capt'
   addCaptures : function( node, prop ) {
      var coords = DGS.utils.makeArray( node[prop] );
      var cnt = 0;
      for ( var i=0; i < coords.length; i++ ) {
         if ( coords[i] ) { // no pass-move allowed
            var pt = DGS.utils.makePointNumberCoord( coords[i] );
            this.goban.setStone( pt.x, pt.y, C.GOBS_EMPTY );
            cnt++;
         }
      }

      // update prisoner-count for stone-color
      var color = 0;
      if ( node.B )
         color = C.GOBS_BLACK;
      else if ( node.W )
         color = C.GOBS_WHITE;
      if ( color )
         this.goban.addPrisoners( color, cnt );
   }, //addCaptures

   // SGF-prop-handler: toggles territory-markers of stones (dead/alive-state) or grid-points (neutral/non-neutral-state)
   // @param node current node; node[prop] == coords with single SGF-coord or SGF-coords-array
   // @param prop property-name 'd_tp' indicating toggle-points on board
   toggleTerritory : function( node, prop ) {
      var coords = DGS.utils.makeArray( node[prop] );
      for ( var i=0; i < coords.length; i++ ) {
         if ( coords[i] ) { // no pass-move allowed
            var pt = DGS.utils.makePointNumberCoord( coords[i] );
            this.goban.toggleTerritoryMarker( pt.x, pt.y );
         }
      }
   } //toggleTerritory

}); //GamePageEditor



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
   GOBG_BITMASK : 0x008F, // bitmask for grid-stuff including hoshi

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

   // Goban (16 bits, 2 combined bitset): labels
   GOBL_BITMASK   : 0xFFFF000,
   GOBL_EMPTY     : 0x0000000, // empty layer = no label
   // - mutual exclusive labels (no bitmask)
   GOBL_LETTER    : 0x001F000, // a..z =1..26 (5 bits; bit 12-16)
   GOBL_NUMBER    : 0xFF80000, // 1..500 (9 bits; bit 19-27)
   //define('GOBL_..', 0x0060000); // reserved

   GOBALL_BITMASK : 0x000F | 0x0080 | 0x0070 | 0x0F00 | 0xFFFF000,

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
   this.init.apply( this, arguments );
};

$.extend( DGS.Goban.prototype, {

   // @param width board-size
   // @param height board-size
   // @param renderer object to render Goban with interface-methods: draw_board(goban,rebuild), render_point(x,y,val)
   init : function( width, height, renderer ) {
      if ( width < 2 || height < 2 )
         throw "Goban.init("+width+","+height+"): invalid_args width, height";
      if ( !renderer && !renderer.draw_board && !renderer.render_point )
         throw "Goban.init(): missing renderer with interface-methods: draw_board & render_point";

      this.size_x = width;
      this.size_y = height;
      this.renderer = renderer;

      this.reset();
   },

   reset : function() {
      // matrix[ y * size_x + x ] = GOB-bitmask | [ GOB-value-bitmask, number|letter (=label) ]; x/y=0..n-1
      // NOTE: makeBoard() MUST be called to properly init matrix (with grid)
      // NOTE: inspired by Eidogo
      this.matrix = [];
      this.grid_matrix = null; // board-matrix-copy with only grid+hoshi for faster board-clearing; null=lazy-init of initial board
      this.cache = []; // store stack with matrix-/lastmove-snapshots for each move
      this.lastRender = []; // last-copy of matrix to compare to and calculating changes to fast render

      this.prisoners = [ 0, 0 ]; // B,W-prisoner-counts

      // feature of Goban to track last move, normally a board-state does not know of it
      this.last_move = []; // [] = no last-move, [null,null] = PASS, else [x,y]
   },

   toString : function() {
      var buf = String.sprintf("Goban(%s,%s):\n", this.size_x, this.size_y );
      for ( var y=0; y < this.size_y; y++ ) {
         buf += y + ": ";
         for ( var x=0; x < this.size_x; x++ )
            buf += String.sprintf("%x ", this.getValue(x,y));
         buf += "\n";
      }
      buf += "  Prisoners=" + JSON.stringify(this.prisoners) + "\n";
      buf += "  LastMove=" + JSON.stringify(this.LastMove) + "\n";
      return buf;
   },

   clearBoard : function() {
      this.makeBoard( true );
   },

   // NOTE: does not change this.last_move
   clearMarkers : function() {
      var clear_bitmask = ~C.GOBM_BITMASK; // keep grid/hoshi + stones, clear markers/labels
      for ( var i=0, mlen=this.matrix.length; i < mlen; i++ )
         this.matrix[i] &= clear_bitmask;
   },

   // clear all stones/markers/labels from the board, but keeping grid; resets last-move
   emptyBoard : function() {
      if ( this.grid_matrix )
         this.matrix = this.grid_matrix.concat();
      else {
         var clear_bitmask = C.GOBB_BITMASK | C.GOBO_HOSHI;
         for ( var i=0, mlen=this.matrix.length; i < mlen; i++ )
            this.matrix[i] &= clear_bitmask;
      }
      this.prisoners = [ 0, 0 ];
      this.last_move = [];
   },

   // fill board-state matrix with grid+hoshi
   makeBoard : function( withHoshi ) {
      this.reset();

      for ( var y=0; y < this.size_y; y++) {
         var board_lines = C.GOBB_MID;
         if ( y == 0 )
            board_lines &= ~C.GOBB_NORTH;
         else if ( y == this.size_y - 1 )
            board_lines &= ~C.GOBB_SOUTH;

         for ( var x=0; x < this.size_x; x++) {
            var val = board_lines;
            if ( x == 0 )
               val &= ~C.GOBB_WEST;
            else if ( x == this.size_x - 1 )
               val &= ~C.GOBB_EAST;

            if ( withHoshi && this.isHoshi(x, y, this.size_x, this.size_y) )
               val |= C.GOBO_HOSHI;

            this.matrix[ y * this.size_x + x ] = val;
         }
      }

      this.grid_matrix = this.matrix.concat(); // clone for fast emptyBoard()
   }, //makeBoard

   isHoshi : function( x, y, size_x, size_y ) {
      if ( size_y == undefined )
         size_y = size_x;

      //board letter:     - a b c d e f g h j k l m n o p q r s t u v w x y z
      if ( size_x == size_y ) {
         var hd = C.HOSHI.DIST[size_x];
         var h = ( (x*2+1 == size_x) ? 1 : ( (x == hd-1 || x == size_x-hd) ? 2 : 0 ) );
         if ( h )
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


   // returns "safe" raw-value from coord x/y on Goban: 0=out-of-bound or no value set; else goban-value
   getValue : function( x, y ) {
      var xy = y * this.size_x + x;
      return ( xy < 0 || xy >= this.matrix.length ) ? 0 : this.matrix[xy];
   },

   // returns goban-value for given SGF-coord
   getValueSgf : function( coord_sgf ) {
      var x = DGS.utils.makeNumberCoord( coord_sgf.charAt(0) );
      var y = DGS.utils.makeNumberCoord( coord_sgf.charAt(1) );
      return this.getValue(x,y);
   },

   // sets C.GOBS_BLACK/WHITE/EMPTY stone on Goban, keeping grid, overwriting stone/marker/label
   setStone : function( x, y, stone_value ) {
      // keep grid, overwrite stone/marker/label
      var xy = y * this.size_x + x;
      this.matrix[xy] = ( this.matrix[xy] & C.GOBG_BITMASK ) | (stone_value & C.GOBS_BITMASK);
   },

   // returns C.GOBS_... from goban @x/y
   getStone : function( x, y ) {
      var xy = y * this.size_x + x;
      return ( this.matrix[xy] & C.GOBS_BITMASK );
   },

   // removes former last-move, adding last-move marker at x/y, unless x/y=null/null for PASS-move
   replaceLastMove : function( x, y ) {
      // remove last-move marker
      if ( this.last_move.length == 2 && this.last_move[0] != null && this.last_move[1] != null ) {
         var lm_xy = this.last_move[1] * this.size_x + this.last_move[0];
         if ( (this.matrix[lm_xy] & C.GOBM_BITMASK) == C.GOBM_MARK )
            this.matrix[lm_xy] &= ~C.GOBM_BITMASK; // clear marker
         this.last_move = [];
      }

      // set new last-move-marker
      if ( x != null && y != null ) {
         var xy = y * this.size_x + x;
         if ( this.matrix[xy] & C.GOBS_BITMASK ) {
            // keep grid/stone, replace marker, clear label
            this.matrix[xy] = (this.matrix[xy] & (C.GOBG_BITMASK|C.GOBS_BITMASK)) | C.GOBM_MARK;
            this.last_move = [ x, y ];
         }
      } else {
         this.last_move = [ null, null ]; // PASS-move
      }
   },

   getLastMove : function() {
      return this.last_move;
   },

   // sets C.GOBM_... marker on Goban, keeping grid/stone/label, replace marker only
   setMarker : function( x, y, marker_value ) {
      var xy = y * this.size_x + x;
      this.matrix[xy] = ( this.matrix[xy] & ~C.GOBM_BITMASK ) | (marker_value & C.GOBM_BITMASK);
   },

   // returns C.GOBM_... from goban @x/y
   getMarker : function( x, y ) {
      var xy = y * this.size_x + x;
      return ( this.matrix[xy] & C.GOBM_BITMASK );
   },

   // toggles territory-marker on given empty point (territory <-> neutral) or on stone (alive <-> dead)
   //    regardless of former potential territory-mark of different colorm
   toggleTerritoryMarker : function( x, y ) {
      var xy = y * this.size_x + x;
      var curr_stone = ( this.matrix[xy] & C.GOBS_BITMASK );
      var curr_marker = ( this.matrix[xy] & C.GOBM_BITMASK );

      if ( curr_stone ) { // toggle stone: alive <-> dead
         if ( curr_marker == C.GOBM_TERR_B || curr_marker == C.GOBM_TERR_W ) {
            this.matrix[xy] &= ~C.GOBM_BITMASK; // toggle stones into alive-state (w/o territory-marker)
         } else {
            // set new territory-marker (with territory-marker of opposite stone-color)
            if ( curr_stone == C.GOBS_BLACK )
               this.matrix[xy] = ( this.matrix[xy] & ~C.GOBM_BITMASK ) | C.GOBM_TERR_W;
            else // white-stone
               this.matrix[xy] = ( this.matrix[xy] & ~C.GOBM_BITMASK ) | C.GOBM_TERR_B;
         }
      } else {
         if ( curr_marker == C.GOBM_TERR_NEUTRAL )
            this.matrix[xy] &= ~C.GOBM_BITMASK; // toggle point into territory-point (no markings, b/c it needs color of surrounding stones)
         else
            this.matrix[xy] = ( this.matrix[xy] & ~C.GOBM_BITMASK ) | C.GOBM_TERR_NEUTRAL; // toggle into neutral point
      }
   },

   // sets C.GOBM_NUMBER/LETTER + label-number/letter on Goban, keeping grid/stone, replacing marker/label
   setLabel : function( x, y, label ) {
      var xy = y * this.size_x + x;
      var value = ( this.matrix[xy] & ~(C.GOBM_BITMASK|C.GOBL_BITMASK) );
      if ( parseInt(label,10) == label && label >= 1 && label <= 500 ) {
         value |= GOBM_NUMBER | ( label << 19 );
      } else if ( label >= 'a' && label <= 'z' ) {
         value |= GOBM_LETTER | ( (label.charCodeAt(0) + 0x60) << 12 );
      }
      this.matrix[xy] = value;
   },

   // return label from Goban @xy as number-label 1..500 or letter-label 'a'..'z'; else 0 = no label
   getLabel : function( x, y ) {
      var value = this.matrix[ y * this.size_x + x ];
      var label;
      if ( value & C.GOBM_NUMBER )
         label = (value & C.GOBL_NUMBER) >> 19;
      else if ( value & C.GOBM_LETTER )
         label = (value & C.GOBL_LETTER) >> 12;
      else
         label = 0;
      return label;
   },

   addPrisoners : function( stone_color, diff_count ) {
      if ( stone_color == C.GOBS_BLACK )
         this.prisoners[0] += diff_count;
      else if ( stone_color == C.GOBS_WHITE )
         this.prisoners[1] += diff_count;
   },

   getPrisoners : function() {
      return this.prisoners;
   },


   // returns cloned and filtered matrix[x,y] with only stone-data GOBS_EMPTY|BLACK|WHITE
   // NOTE: taken from Eidogo + simplified
   cloneStoneMatrix : function() {
      var cmatrix = []; // cloned matrix
      for ( var i=0, mlen=this.matrix.length; i < mlen; i++ )
         cmatrix[i] = ( this.matrix[i] & C.GOBS_BITMASK );
      return cmatrix;
   }, //cloneStoneMatrix

   // saves the current board-state (allows us to revert back to previous states for navigating backwards in a game).
   // NOTE: taken from Eidogo + modified
   commit : function() {
      this.cache.push({
         matrix: this.matrix.concat(),
         prisoners: this.prisoners.concat(),
         last_move: this.last_move.concat()
      });
   },

   // undo any uncomitted changes (needed when game-tree has been modified by editing)
   // NOTE: taken from Eidogo + simplified
   rollback : function() {
      if ( this.cache.last() ) {
         this.matrix = this.cache.last().matrix.concat();
         this.prisoners = this.cache.last().prisoners.concat();
         this.last_move = this.cache.last().last_move.concat();
      } else {
         this.emptyBoard();
      }
   },

   // revert to a previous board-state
   // NOTE: taken from Eidogo + simplified
   revert : function( count ) {
      if ( !count || count < 0 )
         count = 1;
      for ( var i=0; i < count; i++ )
         this.cache.pop();
      this.rollback();
   },


   // draw full board
   render_board : function( rebuild ) {
      this.renderer.draw_board( this, rebuild );
      this.lastRender = this.matrix.concat();
   },

   // draw only changed points on board since last commit
   // NOTE: inspired by Eidogo
   render : function( complete ) {
      if ( complete || !this.cache.last() ) { // render everything
         this.render_board( false );
      } else { // only render (committed) changes since last rendering
         var committed_matrix = this.cache.last().matrix;
         for ( var i=0, x=0, y=0, mlen=committed_matrix.length; i < mlen; i++ ) {
            if ( committed_matrix[i] != this.lastRender[i] ) {
               this.renderer.render_point( x, y, committed_matrix[i] );
               this.lastRender[i] = committed_matrix[i];
            }
            if ( ++x >= this.size_x ) {
               x = 0;
               y++;
            }
         }
      }
   } //render

}); //Goban




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
DGS.Board = function() { // see init() for constructor-args
   this.init.apply( this, arguments );
};

// NOTE: in terms of Eidogo DGS.Board resembles a board-html-renderer
$.extend( DGS.Board.prototype, {

   init : function( stone_size, wood_color ) {
      this.stone_size = (stone_size == undefined) ? 25 : stone_size;
      this.wood_color = wood_color;

      // bitmask using GOBB_NORTH|SOUTH|WEST|EAST enabling coordinates on that side of the go-board.
      this.opts_coords = 0;
      this.show_coords = true; // true to show coordinates
   },

   setOptionsCoords : function( coords, showCoords ) {
      this.opts_coords = (coords & C.GOBB_BITMASK);
      this.show_coords = showCoords;
   },

   getOptionsCoords : function() {
      return (this.show_coords) ? this.opts_coords : 0;
   },

   // reset drawn board
   // - remove all actions from board
   reset_board : function() {
      $("#Goban td.brdx a img").unwrap(); // remove all image-links
      $("#Goban td.brdx img").removeAttr('alt');
   },

   make_coord_row : function( max_x, start_val, coord_start_letter, coord_alt, coord_end, coord_left, coord_right ) {
      var out = '', letterIdx = 0, letter;
      for ( var colnr = 0; colnr <= max_x; colnr++ ) {
         if ( letterIdx == 8 ) letterIdx++; // skip 8='i'
         letter = String.fromCharCode(0x61 + start_val + letterIdx); //0x61=a
         out += coord_start_letter + letter + coord_alt + letter + coord_end;
         letterIdx++;
      }
      return '<tr>' + (coord_left ? coord_left : '') + out + (coord_right ? coord_right : '') + "</tr>\n";
   }, //make_coord_row

   // draw full board
   // @param goban DGS.Goban with board-state to draw
   // @param rebuild true = prepare board for client-use after rendering from server (removing all actions from board)
   draw_board : function( goban, rebuild ) {
      if ( rebuild ) {
         $("table#Goban td.brdx a img").unwrap(); // remove all image-links
         $("table#Goban td.brdx img").removeAttr('alt');
      }

      for ( var y=0; y <= goban.size_y; y++ ) {
         for ( var x=0; x <= goban.size_x; x++ )
            this.render_point( x, y, goban.getValue(x,y) );
      }
   },

   // updates td-cell with board-image (and link) at x/y=0..; replace src-attribute of pre-initialized image in td-cell
   render_point : function( x, y, value ) {
      //global base_path
      var lBoard  = value & C.GOBB_BITMASK;
      var lStone  = value & C.GOBS_BITMASK;
      var lHoshi  = value & C.GOBO_HOSHI;
      var lMarker = value & C.GOBM_BITMASK;

      var type = ''; // unknown mapping

      // mapping and prioritize goban-layer-values to actual images available on DGS
      // starting with mostly-used types ...
      if ( lMarker == 0 ) { // no marker (=no label)
         if ( lStone == 0 ) { // empty cell (grid or no grid)
            if ( lHoshi ) // empty board-point with hoshi
               type = 'h';
            else { // no-hoshi grid-point
               type = this.getBoardLineType( lBoard, false );
               if ( !type )
                  type = 'dot'; // empty-cell default
            }
         }
         else // if ( lStone ) // simple B/W-stone (no marker, no label)
            type = (lStone == C.GOBS_BLACK) ? 'b' : 'w';
      } else { // point with some marker
         // ... continuing with most special ... ending with most generalized images
         var bLineType = this.getBoardLineType(lBoard, true); // only mixable
         var territoryMarker, formMarker;

         if ( lStone && lMarker == C.GOBM_MARK ) // B/W-stone with last-move-marker
            type = (lStone == C.GOBS_BLACK) ? 'bm' : 'wm';
         else if ( lStone && lMarker == C.GOBM_NUMBER ) { // B/W-stone with number-label
            type = (lStone == C.GOBS_BLACK) ? 'b' : 'w';
            var labelNumber = (value & C.GOBL_NUMBER) >> 19;
            if ( labelNumber >= 1 && labelNumber <= 500 )
               type += parseInt(labelNumber, 10); // strip away leading 0s
         }
         else if ( lStone == C.GOBS_WHITE && lMarker == C.GOBM_TERR_B ) // dead W-stone (marked as B-territory)
            type = 'wb';
         else if ( lStone == C.GOBS_BLACK && lMarker == C.GOBM_TERR_W ) // dead B-stone (marked as W-territory)
            type = 'bw';
         else if ( lStone == 0 && (territoryMarker = BC.MAP_TERRITORY_MARKERS[lMarker]) && bLineType ) // territory-marker without stone
            type = bLineType + territoryMarker;
         else if ( lStone && (formMarker = BC.MAP_FORM_MARKERS[lMarker]) ) // marker on B/W-stone
            type = ( (lStone == C.GOBS_BLACK) ? 'b' : 'w' ) + formMarker;
         else if ( lStone == 0 && (formMarker = BC.MAP_FORM_MARKERS[lMarker]) && lHoshi ) // marker without stone on hoshi
            type = 'h' + formMarker;
         else if ( lMarker == C.GOBM_LETTER ) { // label (letter)
            var labelLetter = String.fromCharCode( 0x60 + ((value & C.GOBL_LETTER) >> 12) ); //0x61=a
            if ( labelLetter >= 'a' && labelLetter <= 'z' )
               type = 'l' + labelLetter;
         }
         else if ( lStone == 0 && (formMarker = BC.MAP_FORM_MARKERS[lMarker]) && bLineType ) // marker on grid
            type = bLineType + formMarker;
      }

      if ( type ) {
         var sgf_coord = DGS.utils.makeSgfCoords(x,y);
         $("td#" + sgf_coord + " img").attr("src", base_path + this.stone_size + '/' + type + '.gif' );
      }
   }, //render_point

   // mixed=true : allow board-lines mixed with markers
   getBoardLineType : function( board_lines, mixed ) {
      board_lines &= C.GOBB_BITMASK;
      if ( !mixed && board_lines == (C.GOBB_NORTH|C.GOBB_SOUTH) )
         return 'du';
      return BC.MAP_BOARDLINES[board_lines];
   }

}); //Board




// --------------- GameChangeCalculator ---------------------------------------

// corresponds to Eidogo Rules-class
DGS.GameChangeCalculator = function( goban ) {

   this.goban = goban;
   this.stone_matrix = null;

   var DIR_X = [ -1,  0, 1, 0 ];
   var DIR_Y = [  0, -1, 0, 1 ];

   /*
    * Calculates GobanChanges to play move on Goban at given sgf-coordinates.
    * \param $color = C.GOBS_BLACK|WHITE
    *
    * \note extracted from check_remove()-func in 'include/move.php'
    */
   this.calc_change_play_move = function( coord_sgf, color ) {
      var goban_changes = new DGS.GobanChanges( true );
      if ( color != C.GOBS_BLACK && color != C.GOBS_WHITE ) // only B/W-move
         return goban_changes;

      var old_stone = ( this.goban.getValueSgf( coord_sgf ) & C.GOBS_BITMASK );
      if ( old_stone != C.GOBS_EMPTY ) // point must be empty
         return goban_changes;

      var x0 = DGS.utils.makeNumberCoord( coord_sgf.charAt(0) ); //0..
      var y0 = DGS.utils.makeNumberCoord( coord_sgf.charAt(1) );

      // determine captures
      this.stone_matrix = this.goban.cloneStoneMatrix(); // only contains simple stones
      this.stone_matrix[ y0 * this.goban.size_x + x0 ] = color;
      var opp_color = C.GOBS_BLACK + C.GOBS_WHITE - color;
      var prisoners = this.determine_prisoners( x0, y0, opp_color );
      var nr_prisoners = prisoners.length;

      // check for suicide
      if ( nr_prisoners == 0 ) {
         if ( !this.has_liberties( x0, y0, [], /*remove*/false) )
            return goban_changes; // suicide not allowed
      }

      // check for ko
      /*
      //TODO global $Last_Move, $GameFlags; //input only
      // note: $GameFlags has set Ko-flag if last move has taken a single stone
      if ( nr_prisoners == 1 && (GameFlags & GAMEFLAGS_KO) ) {
         var xy = prisoners[0];
         if ( Last_Move_xy == xy )
            return goban_changes; // ko not allowed
      }
      */

      // draw stone + mark
      goban_changes.add_change_sgf( coord_sgf, C.GOBS_BITMASK | C.GOBM_BITMASK, color | C.GOBM_MARK, '-' );

      /* TODO needs adjustment as mark_point has been removed (b/c mark is a too specialized concept to react on setting a MARK-marker)
      // remove "previous" last-move-mark
      var mark = this.goban.mark_point;
      if ( mark )
         goban_changes.add_change( mark.x, mark.y, C.GOBM_BITMASK, C.GOBM_EMPTY, '' );
      */

      // remove captured stones (with all markers)
      for ( var i=0; i < prisoners.length; i++ ) {
         var xy = prisoners[i];
         goban_changes.add_change( xy[0], xy[1], C.GOBS_BITMASK|C.GOBM_BITMASK, C.GOBS_EMPTY, '-' );
      }

      //TODO handle: prisoners

      return goban_changes;
   }; //calc_change_play_move

   // extracted from check_prisoners()-func in 'include/board.php'
   this.determine_prisoners = function( x0, y0, color ) {
      var prisoners = [], x, y;
      for ( var dir=0; dir < 4; dir++ ) { // determine captured stones for ALL directions
         x = x0 + DIR_X[dir];
         y = y0 + DIR_Y[dir];
         if ( this.stone_matrix[ y * this.goban.size_x + x ] == color )
            this.has_liberties( x, y, prisoners, /*remove*/true );
      }
      return prisoners;
   }; //determine_prisoners

   /*!
    * \brief Returns true if group at position (x,y) has at least one liberty.
    * \param $x0/y0 board-position to check, 0..n
    * \param $prisoners if $remove is set, pass-back extended prisoners-array if group at x,y has no liberty
    * \param $remove if true, remove captured stones
    *
    * \note extracted from has_liberty_check()-func in 'include/board.php'
    */
   this.has_liberties = function( x0, y0, prisoners, remove ) {
      var color = this.stone_matrix[ y0 * this.goban.size_x + x0 ]; // Color of this stone

      var arr_xy = [ x0, y0 ];
      var stack = [ arr_xy ];

      var visited = []; // potential prisoners and marker if point already checked
      visited[ y0 * this.goban.size_x + x0 ] = 1;

      // scanning all directions starting at start-x/y building up a stack of adjacent points to check
      var dir, x, new_x, new_y, new_color;
      while ( ( arr_xy = stack.shift() ) ) {
         x = arr_xy[0], y = arr_xy[1];

         for ( dir=0; dir < 4; dir++ ) { // scan all directions: W N E S
            new_x = x + DIR_X[dir];
            new_y = y + DIR_Y[dir];

            if ( (new_x >= 0 && new_x <= this.goban.size_x) && (new_y >= 0 && new_y <= this.goban.size_y) ) {
               new_m_xy = new_y * this.goban.size_x + new_x;
               new_color = this.stone_matrix[new_m_xy];
               if ( !new_color || new_color == C.GOBS_EMPTY ) {
                  return true; // found liberty
               } else if ( new_color == color && !visited[new_m_xy] ) {
                  stack.push( [ new_x, new_y ] );
                  visited[new_m_xy] = 1;
               }
            }
         }
      }

      if ( remove ) {
         for ( var m_xy=0; m_xy <= visited.length; m_xy++ ) {
            if ( visited[m_xy] ) {
               y = Math.floor( m_xy / this.goban.size_y );
               x = m_xy % this.goban.size_y;
               prisoners.push( [x,y] );
               this.stone_matrix[ m_xy ] = C.GOBS_EMPTY;
            }
         }
      }

      return false;
   }; //has_liberties

}; //GameChangeCalculator


})(jQuery);

// -->
