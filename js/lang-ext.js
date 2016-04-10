// <!--
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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


/**
 * Code originally from EidoGo, but modified to suit the environment and needs of DGS:
 *   Source: git://github.com/jkk/eidogo.git
 *   File:   player/js/lang.js   19-Mar-2008 23:09:15   817449a6f592403cb39c4d58429f12e0908c6eb6
 *
 * EidoGo -- Web-based SGF Editor
 * Copyright (c) 2007, Justin Kramer <jkkramer@gmail.com>
 * Code licensed under AGPLv3:
 * http://www.fsf.org/licensing/licenses/agpl-3.0.html
 *
 * Supplements to core language objects (Array, Function).
 */


Array.prototype.contains = function( needle ) {
   if ( Array.prototype.indexOf )
      return this.indexOf(needle) != -1;
   for ( var i in this ) {
      if ( this[i] == needle )
         return true;
   }
   return false;
};

Array.prototype.setLength = function( len, val ) {
   val = ( typeof val != "undefined" ) ? val : null;
   for ( var i = 0; i < len; i++ )
      this[i] = val;
   return this;
};

Array.prototype.addDimension = function( len, val ) {
   val = ( typeof val != "undefined" ) ? val : null;
   var thisLen = this.length; // minor optimization
   for ( var i = 0; i < thisLen; i++ )
      this[i] = [].setLength(len, val);
   return this;
};

if ( !Array.prototype.first ) {
   Array.prototype.first = function() {
      return this[0];
   };
}

if ( !Array.prototype.last ) {
   Array.prototype.last = function() {
      return this[this.length-1];
   };
}

Array.prototype.copy = function() {
   var copy = [];
   var len = this.length; // minor optimization
   for ( var i = 0; i < len; i++ ) {
      if ( this[i] instanceof Array )
         copy[i] = this[i].copy();
      else
         copy[i] = this[i];
   }
   return copy;
};


if ( !Array.prototype.map ) {
   Array.prototype.map = function( fun /*, thisp*/ ) {
      var len = this.length;
      if ( typeof fun != "function" )
         throw new TypeError();

      var res = new Array(len);
      var thisp = arguments[1];
      for ( var i = 0; i < len; i++ ) {
         if ( i in this )
            res[i] = fun.call(thisp, this[i], i, this);
      }
      return res;
   };
}

if ( !Array.prototype.filter ) {
   Array.prototype.filter = function( fun /*, thisp*/ ) {
      var len = this.length;
      if ( typeof fun != "function" )
         throw new TypeError();

      var res = new Array();
      var thisp = arguments[1];
      for ( var i = 0; i < len; i++ ) {
         if ( i in this ) {
            var val = this[i]; // in case fun mutates this
            if ( fun.call(thisp, val, i, this) )
               res.push(val);
         }
      }
      return res;
   };
}

if ( !Array.prototype.forEach ) {
   Array.prototype.forEach = function( fun /*, thisp*/ ) {
      var len = this.length;
      if ( typeof fun != "function" )
         throw new TypeError();

      var thisp = arguments[1];
      for ( var i = 0; i < len; i++ ) {
         if ( i in this )
            fun.call(thisp, this[i], i, this);
      }
   };
}

if ( !Array.prototype.every ) {
   Array.prototype.every = function( fun /*, thisp*/ ) {
      var len = this.length;
      if ( typeof fun != "function" )
         throw new TypeError();

      var thisp = arguments[1];
      for ( var i = 0; i < len; i++ ) {
         if ( i in this && !fun.call(thisp, this[i], i, this) )
            return false;
      }
      return true;
   };
}

if ( !Array.prototype.some ) {
   Array.prototype.some = function( fun /*, thisp*/ ) {
      var len = this.length;
      if ( typeof fun != "function" )
         throw new TypeError();

      var thisp = arguments[1];
      for ( var i = 0; i < len; i++ ) {
         if ( i in this && fun.call(thisp, this[i], i, this) )
            return true;
      }
      return false;
   };
}

if ( !Array.prototype.some ) {
   Array.from = function( it ) {
      var arr = [];
      for ( var i = 0; i < it.length; i++ )
         arr[i] = it[i];
      return arr;
   };
}


if ( !Function.prototype.bind ) {
   Function.prototype.bind = function( thisObj ) {
      var method = this;
      var args = Array.from(arguments).slice(1);
      return function() {
         return method.apply(thisObj, args.concat(Array.from(arguments)));
      };
   };
}

// -->
