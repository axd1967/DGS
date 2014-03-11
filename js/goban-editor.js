// <!--
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Jens-Uwe Gaspar

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

// import global vars
var C = DGS.constants.Goban;

// --------------- Global Functions -------------------------------------------

DGS.goban_editor = {
   loadPage : function() {
      $(document).ready( DGS.goban_editor.initPage );
   },

   initPage : function() {
      $("#tab_Size input#size_upd").click( function(evt) {
         evt.preventDefault();
         DGS.run.GobanEditor.action_size_updateSize();
      });
      $("#tabs a.UndoTool").click( function(evt) {
         evt.preventDefault();
         DGS.run.GobanEditor.action_handle_undo_tool( this );
      });
      $("#tab_Edit a.Tool").click( function(evt) {
         evt.preventDefault();
         DGS.run.GobanEditor.action_edit_handle_tool( this );
      });
      $("#tab_Play a.Tool").click( function(evt) {
         evt.preventDefault();
         DGS.run.GobanEditor.action_play_handle_tool( this );
      });
      $("#tabs").on("tabscreate tabsactivate", function(evt, ui) {
         DGS.run.GobanEditor.action_handle_show_tab( ui );
      }).tabs({ active: 2 });

      DGS.run.GobanEditor.testBoard(); //TODO test
   }

}; //end of DGS.goban_editor



// --------------- GobanLabels ------------------------------------------------

DGS.GobanLabels = function() {
   this.init.apply( this, arguments );
};

$.extend( DGS.GobanLabels.prototype, {

   init : function() {
      this.numbers = []; // 1..500 allowed number-labels
      this.letters = []; // 1..26  allowed letter-labels
      this.next_number = 1;
      this.next_letter = 1;
   },

   set_label : function( label ) {
      if ( label >= 1 && label <= 500 ) {
         this.numbers[label] = label;
         while ( this.numbers[this.next_number] )
            this.next_number++;
      } else if ( label >= 'a' && label <= 'z' ) {
         label = label.charCodeAt(0) - 0x60; //0x61=a
         this.letters[label] = label;
         while ( this.letters[this.next_letter] )
            this.next_letter++;
      } else {
         throw "DGS.GobalLabels.set_label(" + label + "): invalid label ["+typeof(label)+"] len ["+label.length+"]";
      }
   },

   clear_label : function( label ) {
      var pos;
      if ( label >= 1 && label <= 500 ) {
         this.numbers[label] = 0;
         if ( label < this.next_number )
            this.next_number = label;
      } else if ( label >= 'a' && label <= 'z' ) {
         label = label.charCodeAt(0) - 0x60; //0x61=a
         this.letters[label] = 0;
         if ( label < this.next_letter )
            this.next_letter = label;
      } else {
         throw "DGS.GobalLabels.clear_label(" + label + "): invalid label ["+typeof(label)+"] len ["+label.length+"]";
      }
   },

   // returns next-label for type=GOBM_NUMBER|LETTER or 0 if there are no next-labels
   get_next_label : function( type ) {
      if ( type == C.GOBM_NUMBER ) {
         return ( this.next_number <= 500 ) ? this.next_number : 0;
      } else if ( type == C.GOBM_LETTER ) {
         return ( this.next_letter <= 26 ) ? String.fromCharCode(0x60 + this.next_letter) : 0; //0x61=a
      } else {
         throw "DGS.GobalLabels.get_next_label(" + type + "): invalid type";
      }
   },

   // returns hash-value for current state, used to check if edit-label-tools need update in GUI
   get_hash : function () {
      return this.next_number + 500 * this.next_letter;
   },

   // NOTE: originally in DGS.Goban.setValue(..); refactored here for later use; needs adjustment for this/args
   update_label : function( old_label, new_label ) {
      if ( old_label != new_label ) {
         if ( !old_label && new_label ) {
            this.set_label( new_label );
         } else if ( old_label && !new_label ) {
            this.clear_label( old_label );
         } else { // update-label (both-labels != '')
            this.clear_label( old_label );
            this.set_label( new_label );
         }
      }
   }

}); //GobanLabels




// --------------- Goban (extension) ------------------------------------------

$.extend( DGS.Goban.prototype, {

   //TODO check wether this method is required in this form at all !?
   // internal, overwriting layer-value; does NOT set/clear goban_labels
   setValue : function( x, y, value, label ) { // label optional (=undefined) if value is array
      var m_xy = y * this.size_x + x;
      this.matrix[m_xy] = value;

      if ( this.mark_point
            && ((value & C.GOBS_BITMASK) == C.GOBS_EMPTY) && this.mark_point.x == x && this.mark_point.y == y )
         this.mark_point = null;
      if ( (value & C.GOBM_BITMASK) == C.GOBM_MARK ) // only used in PLAY-mode, otherwise undo/redo not working
         this.mark_point = { x: x, y: y };

      //TODO check wether label is required as arg, or if it's better to have dedicated method
      this.goban_labels.update_label( this.getValue(x,y), label );
   } //setValue

}); //Goban




// --------------- Board (extension) ------------------------------------------

$.extend( DGS.Board.prototype, {

   // redraw board-structure without board-content, used after size-change
   // NOTE: keep in "sync" with Board::draw_board()
   //TODO withActions -> better pass-in action-func to set, making board indep from GED-action;; or even better put binding action into sep func
   draw_board_structure : function( goban, withActions ) {
      if ( withActions == undefined )
         withActions = false;
      $("#Goban tbody > *").hide().remove();
      var tbody = $("table#Goban tbody");

      // max_x/y for art-sized boards have been removed from Goban (for now), but keep it for later using size_x/y
      var max_x = goban.size_x;
      var max_y = goban.size_y;

      var coord_width = Math.floor( this.stone_size * 31 / 25 );
      var table_width = (max_x+1) * this.stone_size;

      // init board-layout options
      var opts_coords = this.getOptionsCoords();
      var add_width_west = ( opts_coords & C.GOBB_WEST ) ? coord_width : 0;
      var add_width_east = ( opts_coords & C.GOBB_EAST ) ? coord_width : 0;
      table_width += add_width_west + add_width_east;

      var coord_alt = '.gif" alt="';
      var coord_end = "\"></td>\n";
      var coord_start_number, coord_start_letter, coord_left = '', coord_right = '';
      if ( opts_coords & (C.GOBB_WEST | C.GOBB_EAST) )
         coord_start_number = "<td class=brdn><img class=brdn src=\"" + base_path + this.stone_size + "/c";
      if ( opts_coords & (C.GOBB_NORTH | C.GOBB_SOUTH) ) {
         coord_start_letter = "<td class=brdl><img class=brdl src=\"" + base_path + this.stone_size + "/c";

         var coord_tmp = "<td><img src=\"" + base_path + "images/blank.gif\" width=" + add_width_west + " height=" + this.stone_size + " alt=\" \"></td>\n";
         if ( opts_coords & C.GOBB_WEST )
            coord_left = coord_tmp;
         if ( opts_coords & C.GOBB_EAST )
            coord_right = coord_tmp;
      }

      var borders = opts_coords;
      var start_col = 0;
      if ( (goban.size_x > max_x + 1 && !(borders & C.GOBB_WEST)) )
         start_col = goban.size_x - max_x - 1;

      var start_row = goban.size_y;
      if ( (goban.size_y > max_y + 1 && !(borders & C.GOBB_NORTH)) || (goban.size_y < max_y + 1 ) )
         start_row = max_y + 1;

      // ---------- Goban ------------------------------------------------

      var table_styles = {};
      table_styles['width'] = table_width + "px";

      var table_attr = {};
      table_attr['border'] = 0;
      table_attr['cellspacing'] = 0;
      table_attr['cellpadding'] = 0;
      if ( this.wood_color > 10 ) {
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
      //TODO do we need an <a> ? action can be set on <img> directly
      if ( withActions )
         blank_image = '<a href="#">' + blank_image + '</a>';

      if ( opts_coords & C.GOBB_NORTH ) {
         row = this.make_coord_row( max_x, start_col, coord_start_letter, coord_alt, coord_end, coord_left, coord_right );
         tbody.append( $(row) );
      }

      for ( var rownr = start_row, y = 0; y <= max_y; rownr--, y++ ) {
         row = ( opts_coords & C.GOBB_WEST ) ? coord_start_number + rownr + coord_alt + rownr + coord_end : '';
         for ( var x = 0; x <= max_x; x++ ) {
            row += '<td id=' + DGS.utils.makeSgfCoords(x,y) + " class=brdx>" + blank_image + "</td>\n";
         }
         if ( opts_coords & C.GOBB_EAST )
            row += coord_start_number + rownr + coord_alt + rownr + coord_end;
         $('<tr>' + row + '</tr>').appendTo(tbody);
      }//for y

      if ( opts_coords & C.GOBB_SOUTH ) {
         row = this.make_coord_row( max_x, start_col, coord_start_letter, coord_alt, coord_end, coord_left, coord_right );
         tbody.append( $(row) );
      }

      $("#GameEditor div.GobanGfx").css('width', table_width + 'px');
      if ( withActions ) {
         $("#GameEditor td.brdx a").click( function(evt) {
            DGS.run.gameEditor.action_handle_board( this, evt );
            evt.preventDefault();
         });
      }

      $("#Goban tbody").show();
   } //draw_board_structure

}); //Board


// --------------- GobanChanges -----------------------------------------------

// constructs GobanChanges
DGS.GobanChanges = function( play_mode ) {
   this.is_play_mode = (play_mode != undefined) ? play_mode : false;

   //arr: [ x, y, change-bitmask, value, label_diff ]; x/y=1..n, mask=int, diff=+L - ""
   this.changes = [];
   this.undo_changes = []; // needs current Goban for calculation
   this.snapshot = {}; // [ "x:y" => "val:lab", ...]
};

$.extend( DGS.GobanChanges.prototype, {

   draw_goban_changes : function( goban, board ) {
      var arrval, x, y;
      var visited = []; //"x:y"=1
      for ( var i=0; i < this.changes.length; i++ ) {
         var arr = goban_changes.changes[i];
         x = arr[0], y = arr[1], key = x+':'+y;
         if ( !visited[key] ) { //TODO why not execute ALL changes on same x/y-coord ? perhaps should not happen
            board.render_point( x, y, goban.getValue(x,y) );
            visited[key] = 1;
         }
      }
   },

   // param label_diff: if label-changed "+L|-" then also value-bitmask and value must set/clear according GOBM_LETTER|NUMBER
   add_change : function( x, y, change_mask, value, label_diff ) {
      this.changes.push( [ x, y, change_mask, value, label_diff ] );
   },

   add_change_sgf : function( sgf_xy, change_mask, value, label_diff ) {
      var x = DGS.utils.makeNumberCoord( sgf_xy.charAt(0) );
      var y = DGS.utils.makeNumberCoord( sgf_xy.charAt(1) );
      this.changes.push( [ x, y, change_mask, value, label_diff ] );
   },

   //TODO needed ?
   merge_changes : function( goban_changes ) {
      for ( var i=0; i < goban_changes.changes.length; i++ ) {
         this.changes.push( goban_changes.changes[i] );
      }
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

      if ( create_undo ) {
         this.undo_changes = [];
         this.snapshot = {};
      }

      for ( var i=0; i < changes.length; i++ ) {
         chg = changes[i];
         x = chg[0], y = chg[1], change_mask = chg[2], value = chg[3], label_diff = chg[4];

         if ( change_mask || label_diff ) {
            old_value = new_value = goban.getValue(x,y);
            old_label = new_label = goban.getLabel(x,y);

            if ( change_mask )
               new_value = (new_value & ~change_mask) | value;
            //TODO handle new-label
            new_label = ( label_diff == '-' ) ? '' : label_diff;

            if ( old_value != new_value || old_label != new_label ) {
               //TODO handle new-value/new-label
               //goban.setValue( x, y, [ new_value, new_label ] );
               count_updates++;

               // calculate compensation for undo
               if ( create_undo ) {
                  this.undo_changes.push( [ x, y, C.GOBALL_BITMASK, old_value, old_label ] );
                  this.snapshot[x+':'+y] = new_value + ':' + new_label;
               }
            }
         } else {
            changes.splice( i--, 1 ); // remove change without effect
         }
      }
      return count_updates;
   }, //apply_goban_changes

   // returns true, if goban-snapshot of this GobanChange equals snaphost of given one.
   is_equal_goban_snapshot : function( goban_change ) {
      if ( this.snapshot.length != goban_change.snapshot.length )
         return false;
      var curr_snapshot = this.build_goban_snapshot( this.snapshot );
      var cmp_snapshot  = this.build_goban_snapshot( goban_change.snapshot );
      return (curr_snapshot == cmp_snapshot);
   },

   // \internal, build string from snapshot-array: x:y=val:lab ...
   build_goban_snapshot : function( arrmap ) {
      var keys = [];
      for ( var key in arrmap )
         keys.push( key );
      keys.sort();

      var out = [];
      for ( var i=0; i < keys.length; i++ )
         out.push( keys[i] + '=' + arrmap[keys[i]] );
      return out.join(' ');
   }

}); //GobanChanges




// ---------- ChangeCalculator -------------------------------------------------

// constructs ChangeCalculator
DGS.ChangeCalculator = function() {

   /*
    * Calculates GobanChanges to place stone with given color on Goban at given sgf-coordinates.
    * \param $new_stone = C.GOBS_BLACK|WHITE|EMPTY
    * \note only for allowed combinations:
    *    - GOBS_EMPTY NOT-OK with GOBM_MARK|NUMBER
    *    - GOBS_BLACK|WHITE NOT-OK with GOBM_LETTER|TERR_NEUTRAL|TERR_DAME
    *    - GOBS_BLACK NOT-OK with GOBM_TERR_B
    *    - GOBS_WHITE NOT-OK with GOBM_TERR_W
    */
   this.calc_goban_change_set_stone = function( goban, coord, new_stone ) {
      new_stone &= C.GOBS_BITMASK;

      // clear marker for invalid combinations
      var old_marker = ( goban.getValueSgf( coord ) & C.GOBM_BITMASK );
      var gob_mask = C.GOBS_BITMASK;
      var chg_label = '';

      if ( new_stone == C.GOBS_EMPTY ) {
         if ( old_marker == C.GOBM_MARK ) {
            gob_mask |= C.GOBM_BITMASK;
         } else if ( old_marker == C.GOBM_NUMBER ) {
            gob_mask |= C.GOBM_BITMASK;
            chg_label = '-';
         }
      } else { // new_stone == B|W
         if ( old_marker == C.GOBM_LETTER ) {
            gob_mask |= C.GOBM_BITMASK;
            chg_label = '-';
         } else if ( old_marker == C.GOBM_TERR_NEUTRAL || old_marker == C.GOBM_TERR_DAME ) {
            gob_mask |= C.GOBM_BITMASK;
         } else if ( new_stone == C.GOBS_BLACK && old_marker == C.GOBM_TERR_B ) {
            gob_mask |= C.GOBM_BITMASK;
         } else if ( new_stone == C.GOBS_WHITE && old_marker == C.GOBM_TERR_W ) {
            gob_mask |= C.GOBM_BITMASK;
         }
      }

      var goban_changes = new DGS.GobanChanges();
      goban_changes.add_change_sgf( coord, gob_mask, new_stone, chg_label );
      return goban_changes;
   }; //calc_goban_change_set_stone

   /*
    * Calculates GobanChanges to toggle stone (new_stone) on Goban at given sgf-coordinates.
    * \param $new_stone = C.GOBS_BLACK|WHITE
    * \note only for allowed combinations:
    *    - toggle empty into new-stone color
    *    - if old-stone is new-stone => toggle to empty-stone; otherwise toggle to new-stone color
    *    - toggle only into allowed combinations (see calc_goban_change_set_stone-method)
    */
   this.calc_goban_change_toggle_stone = function( goban, coord, new_stone ) {
      var value = goban.getValueSgf( coord );
      var old_value = (value & (C.GOBS_BITMASK|C.GOBM_BITMASK));
      var old_stone  = (old_value & C.GOBS_BITMASK);
      var old_marker = (old_value & C.GOBM_BITMASK);

      var is_trg_stone = ( old_stone == new_stone );
      var trg_stone = ( old_stone == C.GOBS_EMPTY || !is_trg_stone ) ? new_stone : C.GOBS_EMPTY;

      // clear marker for invalid combinations
      var new_value = trg_stone | old_marker;
      var chg_label = '';

      if ( old_marker == C.GOBM_LETTER || ((old_marker == C.GOBM_NUMBER || old_marker == C.GOBM_MARK) && is_trg_stone) ) {
         new_value &= ~C.GOBM_BITMASK;
         chg_label = '-';
      } else if ( old_marker == C.GOBM_TERR_B && trg_stone == C.GOBS_BLACK ) {
         new_value &= ~C.GOBM_BITMASK;
      } else if ( old_marker == C.GOBM_TERR_W && trg_stone == C.GOBS_WHITE ) {
         new_value &= ~C.GOBM_BITMASK;
      } else if ( old_marker == C.GOBM_TERR_NEUTRAL || old_marker == C.GOBM_TERR_DAME ) {
         new_value &= ~C.GOBM_BITMASK;
      }

      var goban_changes = new DGS.GobanChanges();
      goban_changes.add_change_sgf( coord, C.GOBS_BITMASK | C.GOBM_BITMASK, new_value, chg_label );
      return goban_changes;
   }; //calc_goban_change_toggle_stone

   /*
    * Calculates GobanChanges to toggle marker on Goban at given sgf-coordinates.
    * \param $new_marker = C.GOBM_MARK|CIRCLE|SQUARE|TRIANGLE|CROSS|TERR_B/W/DAME/NEUTRAL
    * \note only for allowed combinations:
    *    - if old-marker is new-marker => toggle to empty-marker; otherwise toggle to new-marker
    *    - toggle only into allowed combinations (see calc_goban_change_set_stone-method)
    *    - toggle mark only on B/W-stones
    */
   this.calc_goban_change_toggle_marker = function( goban, coord, new_marker ) {
      var goban_changes = new DGS.GobanChanges();

      var old_value = goban.getValueSgf( coord );
      var old_stone  = (old_value & C.GOBS_BITMASK);
      var old_marker = (old_value & C.GOBM_BITMASK);

      // clear marker for invalid combinations
      var trg_marker = new_marker;
      var gob_mask = C.GOBM_BITMASK;
      var chg_label = '';

      if ( old_marker == new_marker ) {
         trg_marker = C.GOBM_EMPTY;
      } else if ( new_marker == C.GOBM_MARK ) {
         if ( old_stone != C.GOBS_BLACK && old_stone != C.GOBS_WHITE )
            return goban_changes; // no change
      } else {
         if ( old_marker == C.GOBM_NUMBER || old_marker == C.GOBM_LETTER )
            chg_label = '-';

         //NOTE: nothing special for: if ( new_marker == C.GOBM_CIRCLE || new_marker == C.GOBM_SQUARE || new_marker == C.GOBM_TRIANGLE || new_marker == C.GOBM_CROSS )
         if ( new_marker == C.GOBM_TERR_NEUTRAL || new_marker == C.GOBM_TERR_DAME ) {
            gob_mask |= C.GOBS_BITMASK;
         } else if ( old_stone != C.GOBS_EMPTY && (new_marker == C.GOBM_TERR_B || new_marker == C.GOBM_TERR_W) ) {
            if ( old_stone == C.GOBS_BLACK && new_marker == C.GOBM_TERR_B ) {
               gob_mask |= C.GOBS_BITMASK; // clear stone
            } else if ( old_stone == C.GOBS_WHITE && new_marker == C.GOBM_TERR_W ) {
               gob_mask |= C.GOBS_BITMASK; // clear stone
            }
         }
      }

      goban_changes.add_change_sgf( coord, gob_mask, trg_marker, chg_label );
      return goban_changes;
   }; //calc_goban_change_toggle_marker

   /*
    * Calculates GobanChanges to toggle number- or letter-label on Goban at given sgf-coordinates.
    * \param $label_type = C.GOBM_NUMBER|LETTER
    * \note only for allowed combinations:
    *    - toggle number-label only on B|W-stone between next-number-label and empty
    *    - toggle letter-label only on empty-stone between next-letter-label and empty (clear stone if necessary)
    */
   this.calc_goban_change_toggle_label = function( goban, coord, label_type ) {
      var old_value = goban.getValueSgf( coord );
      var old_stone  = (old_value & C.GOBS_BITMASK);
      var old_marker = (old_value & C.GOBM_BITMASK);

      // clear marker for invalid combinations
      var gob_mask = C.GOBM_BITMASK;
      var chg_label = undefined;
      var next_label = goban.goban_labels.get_next_label( label_type );

      if ( label_type == C.GOBM_NUMBER ) { // number only WITH B/W-stones
         if ( old_stone != C.GOBS_EMPTY )
            chg_label = ( old_marker == label_type ) ? '-' : next_label;
      } else if ( label_type == C.GOBM_LETTER ) { // letter only WITHOUT B/W-stone
         gob_mask |= C.GOBS_BITMASK; // clear stone
         chg_label = ( old_marker == label_type ) ? '-' : next_label;
      }

      var goban_changes = new DGS.GobanChanges();
      if ( chg_label != undefined ) {
         var trg_marker = ( chg_label == '-' ) ? C.GOBM_EMPTY : label_type;
         goban_changes.add_change_sgf( coord, gob_mask, trg_marker, chg_label );
      }
      return goban_changes;
   }; //calc_goban_change_toggle_label

}; // ChangeCalculator



// ---------- GobanEditor -------------------------------------------------------

// constructs GobanEditor
DGS.GobanEditor = function() { // see init-args for constructor-args
   this.init.apply( this, arguments );
};

DGS.GobanEditor.CONFIG = {
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

$.extend( DGS.GobanEditor.prototype, {

   init : function( stone_size, wood_color, size_x, size_y ) {
      this.board = new DGS.Board( stone_size, wood_color ); // DGS.Board for drawing board
      this.goban = new DGS.Goban( size_x, size_y, this.board ); // DGS.Goban to keep board-state
      this.calc = new DGS.ChangeCalculator(); // DGS.ChangeCalculator for calculating changes for goban & more
      this.gamecalc = new DGS.GameChangeCalculator( this.goban ); // DGS.GameChangeCalculator for calculating play-changes for goban & more
      this.board_storage = null; // for restoring board

      this.reset();
   },

   reset : function() {
      this.history_undo = []; // GobanChanges-arr for undo
      this.history_redo = []; // GobanChanges-arr for redo

      this.edit_tool_selected = null;
      this.play_tool_selected = null;
      this.play_next_color = C.GOBS_BLACK;
   },

   drawBoard : function() {
      this.goban.clearBoard();
      this.goban.render_board( true );
   },

   testBoard : function() {
      this.goban = new DGS.Goban( 9, 9, this.board );
      this.goban.makeBoard( true );
      this.board.setOptionsCoords( C.GOBB_MID, true );
      this.board.draw_board_structure( this.goban, true );
      this.goban.render_board( false );

      // init game-editor
      this.update_label_tool();
   },

   saveBoard : function() {
      this.board_storage = $("table#Goban").html();
   },

   restoreBoard : function() {
      if ( this.board_storage ) {
         $("table#Goban").html(this.board_storage);
         this.board_storage = null;
      }
   },

   current_tab : function() {
      return $("#tabs").tabs('option', 'active');
   },

   // also set defaults
   action_handle_show_tab : function( ui ) {
      var panel = ui.newPanel || ui.panel;
      if ( panel.is("#tab_Edit") ) {
         if ( this.edit_tool_selected == null ) // default-tool
            $('#edit_tool_toggle_stone').click();
         this.update_label_tool();
         this.update_history_tool();

      } else if ( panel.is("#tab_Play") ) {
         if ( this.play_tool_selected == null ) // default-tool
            $('#play_tool_move').click();
         this.update_play_tool_next_color();
         this.update_history_tool();
      }
   },

   // common undo/redo history for Edit-/Play-tab
   action_handle_undo_tool : function( $tool ) {
      var curr_tab = this.current_tab();
      if ( curr_tab != 1 && curr_tab != 2 )
         return;

      var dbg = $tool.id;
      var goban_changes, label_hash;

      if ( $tool.id == 'edit_tool_undo' || $tool.id == 'play_tool_undo' ) {
         if ( this.history_undo.length > 0 ) {
            goban_changes = this.history_undo.pop();
            label_hash = this.goban.goban_labels.get_hash();
            if ( goban_changes.apply_undo_changes( this.goban ) ) {
               goban_changes.draw_goban_changes( this.goban, this.board );
               this.update_label_tool( label_hash );
               if ( goban_changes.is_play_mode )
                  this.update_play_tool_next_color( /*toggle*/true );
               this.save_change_history( goban_changes, /*undo*/false, /*redo*/false );
            }
         }
      } else if ( $tool.id == 'edit_tool_redo' || $tool.id == 'play_tool_redo' ) {
         if ( this.history_redo.length > 0 ) {
            goban_changes = this.history_redo.pop();
            label_hash = this.goban.goban_labels.get_hash();
            if ( goban_changes.apply_changes( this.goban ) ) {
               goban_changes.draw_goban_changes( this.goban, this.board );
               this.update_label_tool( label_hash );
               if ( goban_changes.is_play_mode )
                  this.update_play_tool_next_color( /*toggle*/true );
               this.save_change_history( goban_changes, /*undo*/true, /*redo*/true );
            }
         }
      }

      DGS.utils.debug( dbg );
   }, //action_handle_undo_tool

   update_history_tool : function() {
      var prefix = ( this.current_tab() == 2 ) ? '#play' : '#edit';
      $(prefix + "_tool_undo_hist").text( String.sprintf("(%s)", this.history_undo.length) );
      $(prefix + "_tool_redo_hist").text( String.sprintf("(%s)", this.history_redo.length) );
   },

   action_handle_board : function( $point, evt ) { // $point = clicked board-point, evt = event for click
      var point_id = $($point).parent().attr('id'); // SGF-coord
      var curr_tab = this.current_tab();

      if ( curr_tab == 1 )
         this.action_edit_handle_board( point_id, evt );
      else if ( curr_tab == 2 )
         this.action_play_handle_board( point_id, evt );
   },

   // ---------- Actions on SIZE-tab -------------------------------------------

   action_size_updateSize : function() {
      // check inputs
      var width  = $('#size_w').val();
      var height = $('#size_h').val();
      var error = false;
      if ( !width || !parseInt(width,10) || width < 2 || width > 25 ) {
         DGS.utils.highlight('#size_w');
         return false;
      }
      if ( !height || !parseInt(height,10) || height < 2 || height > 25 ) {
         DGS.utils.highlight('#size_h');
         return false;
      }

      // re-init board
      this.reset();
      this.goban = new DGS.Goban( width, height, this.board );
      this.goban.makeBoard( true );
      this.board.setOptionsCoords( C.GOBB_MID, true );
      this.board.draw_board_structure( this.goban, true );
      this.goban.render_board( false );
      return true;
   },


   // ---------- Actions on EDIT-tab -------------------------------------------

   action_edit_handle_tool : function( $tool ) { // $tool = selected edit-tool
      if ( this.current_tab() != 1 )
         return;
      if ( this.edit_tool_selected == $tool )
         return;

      if ( this.edit_tool_selected != null )
         $(this.edit_tool_selected).toggleClass('ToolSelected', false);
      this.edit_tool_selected = $tool;
      $($tool).toggleClass('ToolSelected', true);

      DGS.utils.debug( $tool.id );
   }, //action_edit_handle_tool

   action_edit_handle_board : function( point_id, evt ) { // point_id = SGF-coord, evt = event for click
      if ( this.current_tab() != 1 )
         return;
      var dbg = point_id;

      if ( this.edit_tool_selected != null ) {
         var tool_id = this.edit_tool_selected.id;
         var goban_changes, value;

         // calculate goban-change
         // --- STONE-tools ---
         if ( (result = tool_id.match(/^edit_tool_(b|w|clear)_stone$/)) ) {
            value = DGS.GobanEditor.CONFIG.edit.stone_tool[ result[1] ];
            goban_changes = this.calc.calc_goban_change_set_stone( this.goban, point_id, value );

         } else if ( tool_id == 'edit_tool_toggle_stone' ) {
            value = ( evt.shiftKey ) ? C.GOBS_WHITE : C.GOBS_BLACK;
            goban_changes = this.calc.calc_goban_change_toggle_stone( this.goban, point_id, value );
         }
         // --- MARKER-tools ---
         else if ( (result = tool_id.match(/^edit_tool_(circle|square|triangle|cross|terr_(b|w|neutral|dame))_marker$/)) ) {
            value = DGS.GobanEditor.CONFIG.edit.marker_tool[ result[1] ];
            goban_changes = this.calc.calc_goban_change_toggle_marker( this.goban, point_id, value );
         }
         // --- LABEL-tools ---
         else if ( (result = tool_id.match(/^edit_tool_(number|letter)_label$/)) ) {
            value = DGS.GobanEditor.CONFIG.edit.label_tool[ result[1] ];
            goban_changes = this.calc.calc_goban_change_toggle_label( this.goban, point_id, value );
         }

         // draw-goban-change
         var label_hash = this.goban.goban_labels.get_hash();
         if ( goban_changes.apply_changes( this.goban ) ) {
            goban_changes.draw_goban_changes( this.goban, this.board );
            this.update_label_tool( label_hash );
            this.save_change_history( goban_changes, /*undo*/true, /*redo*/false );
         }
      }

      DGS.utils.debug( dbg );
   }, //action_edit_handle_board

   // old_hash=undefined to redraw both Number/Letter-label-tools
   update_label_tool : function( old_hash ) {
      if ( old_hash == undefined || (old_hash != this.goban.goban_labels.get_hash() ) ) {
         // 0=no-next-label -> keep former label
         var next_number_label = this.goban.goban_labels.get_next_label( C.GOBM_NUMBER );
         var next_letter_label = this.goban.goban_labels.get_next_label( C.GOBM_LETTER );
         if ( next_number_label )
            $("#edit_tool_number_label span.LabelTool").text( next_number_label );
         if ( next_letter_label )
            $("#edit_tool_letter_label span.LabelTool").text( next_letter_label );
      }
   },

   // $undo: true = undo-history should be saved, false = redo-history saved
   // $redo: true = performing redo (no diff-redo-check performerd), false = normal move
   save_change_history : function( goban_changes, undo, redo ) {
      if ( undo ) {
         this.history_undo.push( goban_changes );

         // clear redo-history if current change differs from next-redo
         if ( !redo && this.history_redo.length > 0 ) {
            if ( !goban_changes.is_equal_goban_snapshot( this.history_redo[this.history_redo.length - 1] ) )
               this.history_redo = [];
         }
      } else {
         this.history_redo.push( goban_changes );
      }
      this.update_history_tool();
   },


   // ---------- Actions on PLAY-tab -------------------------------------------

   action_play_handle_tool : function( $tool ) { // $tool = selected edit-tool
      if ( this.current_tab() != 2 )
         return;
      if ( this.play_tool_selected == $tool )
         return;

      if ( this.play_tool_selected != null )
         $(this.play_tool_selected).toggleClass('ToolSelected', false);
      this.play_tool_selected = $tool;
      $($tool).toggleClass('ToolSelected', true);

      DGS.utils.debug( $tool.id );
   }, //action_play_handle_tool

   action_play_handle_board : function( point_id, evt ) { // point_id = SGF-coord, evt = event for click
      if ( this.current_tab() != 2 )
         return;
      var dbg = point_id;

      if ( this.play_tool_selected != null ) {
         var tool_id = this.play_tool_selected.id;
         var goban_changes, value;

         // calculate game-changes
         // --- MOVE-tools ---
         if ( tool_id == 'play_tool_move' ) {
            goban_changes = this.gamecalc.calc_change_play_move( point_id, this.play_next_color );
         }

         // draw-goban-change
         var label_hash = this.goban.goban_labels.get_hash();
         if ( goban_changes.apply_changes( this.goban ) ) {
            goban_changes.draw_goban_changes( this.goban, this.board );
            this.update_label_tool( label_hash );
            this.update_play_tool_next_color( /*toggle*/true );
            this.save_change_history( goban_changes, /*undo*/true, /*redo*/false );
         } else {
            dbg += ' INVALID MOVE';
         }
      }

      DGS.utils.debug( dbg );
   }, //action_play_handle_board

   update_play_tool_next_color : function( toggle ) {
      if ( toggle )
         this.play_next_color = C.GOBS_BLACK + C.GOBS_WHITE - this.play_next_color;
      var col = (this.play_next_color == C.GOBS_BLACK) ? 'b' : 'w';
      $("#play_tool_move img").attr('src', base_path + '21/' + col + '.gif');
   }

   // ---------- Actions (END) -------------------------------------------------

}); //GobanEditor

})(jQuery);

// -->
