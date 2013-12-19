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


/**
 * Code originally from EidoGo, but modified to suit the environment and needs of DGS:
 *    Source: git://github.com/jkk/eidogo.git
 *    File:   player/js/gametree.js   08-Sep-2008 16:33:12   29cd15cf3ddb8f540d466dead2191e2fdc0295c6
 *
 * EidoGo -- Web-based SGF Editor
 * Copyright (c) 2007, Justin Kramer <jkkramer@gmail.com>
 * Code licensed under AGPLv3:
 * http://www.fsf.org/licensing/licenses/agpl-3.0.html
 *
 * This file contains GameNode and GameCursor.
 */


// For uniquely identifying nodes. Should work even if we have multiple Player instantiations.
// Setting this to 100000 is kind of a hack to avoid overlap with ids of as-yet-unloaded trees.
DGS.gameNodeIdCounter = 100000;

/**
 * @class GameNode holds SGF-like data containing things like moves, labels
 *    game information, and so on. Each GameNode has children and (usually) a parent.
 *    The first child is the main line.
 */
DGS.GameNode = function() {
   this.init.apply(this, arguments);
};

DGS.GameNode.prototype = {

   /**
    * @constructor
    * @param {GameNode} _parent Parent of the node
    * @param {Object} properties SGF-like JSON object to load into the node
    */
   init : function( _parent, properties, id ) {
      this._id = ( typeof id != "undefined" ) ? id : DGS.gameNodeIdCounter++;
      this._parent = _parent || null;
      this._children = [];
      this._preferredChild = 0;
      if ( properties )
         this.loadJson(properties);
   },

   /**
    * Adds a property to this node without replacing existing values.
    * If the given property already exists, it will make the value an array containing the given value and
    * any existing values.
    */
   pushProperty : function( prop, value ) {
      if ( this[prop] ) {
         if ( !(this[prop] instanceof Array) )
            this[prop] = [ this[prop] ];
         if ( !this[prop].contains(value) )
            this[prop].push(value);
      } else {
         this[prop] = value;
      }
   },

   /** Checks whether this node contains the given property with the given value. */
   hasPropertyValue : function( prop, value ) {
      if ( !this[prop] )
         return false;
      var values = ( this[prop] instanceof Array ) ? this[prop] : [ this[prop] ];
      return values.contains(value);
   },

   /**
    * Removes a value from property or properties.
    * If the value is the only one for the property, removes the property also. Value can be a RegExp or a string.
    */
   deletePropertyValue : function( prop, value ) {
      var test = ( value instanceof RegExp )
         ? function( v ) { return value.test(v); }
         : function( v ) { return value == v; };
      var props = ( prop instanceof Array ) ? prop : [ prop ];
      for (var i = 0; prop = props[i]; i++) {
         if ( this[prop] instanceof Array ) {
            this[prop] = this[prop].filter( function( v ) { return !test(v); } );
            if ( !this[prop].length )
               delete this[prop];
         } else if ( test(this.prop) ) {
            delete this[prop];
         }
      }
   },

   /**
    * Loads SGF-like data given in JSON format:
    *     {PROP1: VALUE, PROP2: VALUE, _children: [...]}
    * Node properties will be overwritten if they exist or created if they don't.
    *
    * We use a stack instead of recursion to avoid recursion limits.
    */
   loadJson : function( data ) {
      var jsonStack = [ data ], gameStack = [ this ];
      var jsonNode, gameNode;
      var i, len;
      while ( jsonStack.length ) {
         jsonNode = jsonStack.pop();
         gameNode = gameStack.pop();
         gameNode.loadJsonNode(jsonNode);
         len = ( jsonNode._children ) ? jsonNode._children.length : 0;
         for ( i = 0; i < len; i++ ) {
            jsonStack.push(jsonNode._children[i]);
            if ( !gameNode._children[i] )
               gameNode._children[i] = new DGS.GameNode(gameNode);
            gameStack.push(gameNode._children[i]);
         }
      }
   },

   /** Adds properties to the current node from a JSON object. */
   loadJsonNode : function( data ) {
      for ( var prop in data ) {
         if ( prop == "_id" ) {
            this[prop] = data[prop].toString();
            DGS.gameNodeIdCounter = Math.max( DGS.gameNodeIdCounter, parseInt(data[prop], 10) );
            continue;
         }
         if ( prop.charAt(0) != "_" )
            this[prop] = data[prop];
      }
   },

   /**
    * Loads SGF-like data given in simplified JSON format
    *     [ var-no, node={ PROP: VAL, _vars: [ var-no, var-no, ... ] }, node, ..., var-no, node, ... ]
    * into tree-like structure:
    *     {PROP1: VALUE, PROP2: VALUE, _children: [...]}
    */
   loadJsonFlatTree : function( data ) {
      var parent_node = this, curr_var = -1,  node, item;
      var var_refs = []; // [ var_ref, target_node ], ...
      var var_list = []; // [ var_num, start_node ], ...
      var i = 0, datalen = data.length;
      while ( i < datalen ) {
         item = data[i++];
         if ( typeof(item) == 'number' ) { // parse variation
            if ( item != curr_var ) { // new variation
               if ( curr_var >= 0 ) // not first variation
                  parent_node = null;
               curr_var = item;
            }
         } else { // parse node with properties
            node = new DGS.GameNode( parent_node, item );
            if ( var_list[curr_var] === undefined )
               var_list[curr_var] = parent_node || node;
            parent_node._children.push( node );
            parent_node = node;

            if ( "_vars" in node ) { // remember variations to fill in later
               var node_vars = node['_vars'];
               for ( var j=0, vlen=node_vars.length; j < vlen; j++ )
                  var_refs.push([ node_vars[j], node ]);
               delete node['_vars'];
            }
         }
      }

      // add variations in children of remembered nodes
      for ( i=0, vlen=var_refs.length; i < vlen; i++ ) {
         curr_var = var_ref[i][0];
         node = var_ref[i][1];
         node._children.push( var_list[curr_var] );
      }
   },

   /** Adds a new child (variation). */
   appendChild : function( node ) {
      node._parent = this;
      this._children.push(node);
   },

   /** Returns all the properties for this node. */
   getProperties : function() {
      var properties = {}, propName, isReserved, isString, isArray;
      for ( propName in this ) {
         isPrivate = ( propName.charAt(0) == "_" );
         isString = ( typeof this[propName] == "string" );
         isArray = ( this[propName] instanceof Array );
         if ( !isPrivate && (isString || isArray) )
            properties[propName] = this[propName];
      }
      return properties;
   },

   /**
    * Applies a function to this node and all its children, recursively (although we use a stack
    * instead of actual recursion).
    */
   walk : function( fn, thisObj ) {
      var stack = [ this ];
      var node, i, len;
      while ( stack.length ) {
         node = stack.pop();
         fn.call( thisObj || this, node );
         len = ( node._children ) ? node._children.length : 0;
         for ( i = 0; i < len; i++ )
            stack.push(node._children[i]);
      }
   },

   /** Gets the current black or white move as a raw SGF coordinate. */
   getMove : function() {
      if ( typeof this.W != "undefined" )
         return this.W;
      else if ( typeof this.B != "undefined" )
         return this.B;
      return null;
   },

   /** Empties the current node of any black or white stones (played or added). */
   emptyPoint : function( coord ) {
      var props = this.getProperties();
      var deleted = null;
      for ( var propName in props ) {
         if ( propName == "AW" || propName == "AB" || propName == "AE" ) {
            if ( !(this[propName] instanceof Array) )
               this[propName] = [this[propName]];

            this[propName] = this[propName].filter(
               function( val ) {
                  if ( val == coord ) {
                     deleted = val;
                     return false;
                  }
                  return true;
               });

            if ( !this[propName].length )
               delete this[propName];
         } else if ( (propName == "B" || propName == "W") && this[propName] == coord ) {
            deleted = this[propName];
            delete this[propName];
         }
      }
      return deleted;
   }, //emptyPoint

   /** Returns the node's position in its parent's _children array. */
   getPosition : function() {
      if ( !this._parent )
         return null;
      var siblings = this._parent._children;
      for ( var i = 0; i < siblings.length; i++ ) {
         if ( siblings[i]._id == this._id )
            return i;
      }
      return null;
   },

   /** Converts this node and all children to SGF. */
   toSgf : function() {
      var sgf = ( this._parent ) ? "(" : "";
      var node = this;

      function propsToSgf( props ) {
         if ( !props )
            return "";
         var sgf = ";", key, val;
         for ( key in props ) {
            if ( props[key] instanceof Array ) {
               val = props[key].map( function (val) {
                  return val.toString().replace(/\]/g, "\\]");
               }).join("][");
            } else {
               val = props[key].toString().replace(/\]/g, "\\]");
            }
            sgf += key + "[" + val  + "]";
         }
         return sgf;
      }//propsToSgf

      sgf += propsToSgf(node.getProperties());

      // Follow main line until we get to a node with multiple variations
      while ( node._children.length == 1 ) {
         node = node._children[0];
         sgf += propsToSgf(node.getProperties());
      }

      // Variations
      for ( var i = 0; i < node._children.length; i++ )
         sgf += node._children[i].toSgf();

      sgf += ( this._parent ) ? ")" : "";

      return sgf;
   }, //toSgf

   // for debugging: JSON.stringify(..) fails on GameNode because of "too much recursion".
   // so return object without recursive structures by replacing referenced nested GameNode-instances with using ref._id as representation
   toJSON : function() {
      var copy = {};
      for ( var prop in this ) {
         if ( prop == '_children' ) {
            var arr = [];
            for ( var i=0; i < this._children.length; i++ ) {
               arr.push( this._children[i]._id );
            }
            copy[prop] = arr;
         } else if ( prop == '_parent' ) {
            copy[prop] = (this._parent) ? this._parent._id : null;
         } else {
            copy[prop] = this[prop];
         }
      }
      return copy;
   }

}; // end of 'DGS.GameNode'




/**
 * @class GameCursor is used to navigate among the nodes of a game tree.
 */
DGS.GameCursor = function() {
   this.init.apply(this, arguments);
};

DGS.GameCursor.prototype = {

   /**
    * @constructor
    * @param {DGS.GameNode} A node to start with
    */
   init : function( node ) {
      this.node = node;
   },

   next : function( varNum ) {
      varNum = ( typeof varNum == "undefined" || varNum == null ) ? this.node._preferredChild : varNum;
      if ( !this.hasNext(varNum) )
         return false;
      this.node._preferredChild = varNum;
      this.node = this.node._children[varNum];
      return true;
   },

   previous : function() {
      if ( !this.hasPrevious() )
         return false;
      this.node = this.node._parent;
      return true;
   },

   hasNext : function( varNum ) {
      varNum = ( typeof varNum == "undefined" || varNum == null || varNum < 0 ) ? this.node._preferredChild : varNum;
      return this.node && (varNum < this.node._children.length);
   },

   hasPrevious : function() {
      // Checking _parent of _parent is to prevent returning to root-game-collection
      return this.node && this.node._parent && this.node._parent._parent;
   },

   /* return map={ sgf-coord: child-index } or null (no next move) with first move for every variation from current node. */
   getNextMoves : function() {
      if ( !this.hasNext() )
         return null;
      var moves = {}, i, node;
      for ( i = 0; node = this.node._children[i]; i++ )
         moves[node.getMove()] = i;
      return moves;
   },

   getNextColor : function() {
      if ( !this.hasNext() )
         return null;
      var node, i;
      for ( i = 0; node = this.node._children[i]; i++ ) {
         if ( node.W || node.B )
            return node.W ? "W" : "B";
      }
      return null;
   },

   getNextNodeWithVariations : function() {
      var node = this.node;
      while ( node._children.length == 1 )
         node = node._children[0];
      return node;
   },

   getPath : function() {
      var n = this.node, rpath = [], mn = 0;
      while ( n && n._parent && n._parent._children.length == 1 && n._parent._parent ) {
         mn++;
         n = n._parent;
      }
      rpath.push(mn);
      while ( n ) {
         if ( n._parent && (n._parent._children.length > 1 || !n._parent._parent) )
            rpath.push( n.getPosition() || 0 );
         n = n._parent;
      }
      return rpath.reverse();
   },

   getPathMoves : function() {
      var path = [];
      var cur = new DGS.GameCursor(this.node);
      path.push(cur.node.getMove());
      while ( cur.previous() ) {
         var move = cur.node.getMove();
         if ( move )
            path.push(move);
      }
      return path.reverse();
   },

   getMoveNumber : function() {
      var num = 0, node = this.node;
      while ( node ) {
         if ( node.W || node.B )
            num++;
         node = node._parent;
      }
      return num;
   },

   // return GameNode.d_mn property set with move-number of current-node
   getDgsMoveNumber : function() {
      return parseInt( this.node.d_mn, 10 );
   },

   // move cursor to root-node of 1st game
   resetToRootGameNode : function() {
      // If we're on the tree root, set cursor to the first game
      if ( !this.node._parent && this.node._children.length )
         this.node = this.node._children[0];
      else {
         while ( this.previous() ) { }
      }
   },

   getRootGameNode : function() {
      if ( !this.node )
         return null;
      var cur = new DGS.GameCursor(this.node);
      // If we're on the tree root, return the first game
      if ( !this.node._parent && this.node._children.length )
         return this.node._children[0];
      while ( cur.previous() ) { }
      return cur.node;
   },

   toJSON : function() {
      return this.node.toJSON();
   }

}; // end of 'DGS.GameCursor'

// -->
