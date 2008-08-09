<?php
/*
Dragon Go Server
Copyright (C) 2001-2008  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

 /* Author: Jens-Uwe Gaspar */

// min/max x/y used as coordinates
define('MATRIX_MIN_X', 'minX');
define('MATRIX_MIN_Y', 'minY');
define('MATRIX_MAX_X', 'maxX');
define('MATRIX_MAX_Y', 'maxY');
define('MATRIX_NUM_ENTRIES', 'numEntries');


 /*!
  * \class Matrix
  *
  * \brief Class to hold non-NULL objects placed at some x/y coordinates
  *
  * Example:
  *    $m = new Matrix();
  *    $m->add( 3,7, $object);
  *    $min_x = $m->get_info(MATRIX_MIN_X);
  *    $max_y = $m->get_info(MATRIX_MAX_Y);
  *    $arr   = $m->get_infos();  // MATRIX-key => info
  *    $obj = $m->get_entry($x,$y);
  */
class Matrix
{
   /*! \brief array of arrays with object-entries. */
   var $entries;
   /*! \brief array with infos about matrix-characteristics. */
   var $infos;

   /*! \brief Constructs Matrix. */
   function Matrix()
   {
      $this->entries = array();
      $this->infos   = array(
         MATRIX_NUM_ENTRIES => 0,
      );
   }

   /*!
    * \brief Adds non-NULL object with coordinates ($x,$y) to Matrix.
    * NOTE: NULL is reserved as marker for non-existing entry in get_entry(x,y)-function!
    */
   function add( $x, $y, $obj )
   {
      if ( is_null($this->get_entry($x,$y)) )
         $this->infos[MATRIX_NUM_ENTRIES]++;

      $this->entries[$x][$y] = $obj;

      if ( !isset($this->infos[MATRIX_MIN_X]) || $x < @$this->infos[MATRIX_MIN_X] )
         $this->infos[MATRIX_MIN_X] = $x;
      if ( !isset($this->infos[MATRIX_MAX_X]) || $x > @$this->infos[MATRIX_MAX_X] )
         $this->infos[MATRIX_MAX_X] = $x;
      if ( !isset($this->infos[MATRIX_MIN_Y]) || $y < @$this->infos[MATRIX_MIN_Y] )
         $this->infos[MATRIX_MIN_Y] = $y;
      if ( !isset($this->infos[MATRIX_MAX_Y]) || $y > @$this->infos[MATRIX_MAX_Y] )
         $this->infos[MATRIX_MAX_Y] = $y;
   }

   /*! \brief Returns added entry at specific position or NULL otherwise. */
   function get_entry( $x, $y )
   {
      return ( isset($this->entries[$x][$y]) ) ? $this->entries[$x][$y] : NULL;
   }

   /*! \brief Returns entries for specified x-coordinate as non-null array. */
   function get_entries_y( $x )
   {
      return ( isset($this->entries[$x]) ) ? $this->entries[$x] : array();
   }

   /*! \brief Returns infos-array (some keys may be unset, so use @$arr[key] to check values). */
   function get_infos()
   {
      return $this->infos;
   }

   /*! \brief Returns info for specified key; return NULL if key is unset. */
   function get_info( $key )
   {
      return ( isset($this->infos[$key]) ) ? $this->infos[$key] : NULL;
   }

} // end of 'Matrix'

?>
