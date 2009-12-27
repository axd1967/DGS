<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
   /*! \brief array of arrays with object-entries (first is array on x-axis). */
   var $entries_x;
   /*! \brief array of arrays with value '1' (first is array on y-axis). */
   var $entries_y;
   /*! \brief array with infos about matrix-characteristics. */
   var $infos;

   /*! \brief Constructs Matrix. */
   function Matrix()
   {
      $this->entries_x = array();
      $this->entries_y = array();
      $this->infos = array(
         MATRIX_NUM_ENTRIES => 0,
      );
   }

   /*!
    * \brief Adds non-NULL object with coordinates ($x,$y) to Matrix.
    * NOTE: NULL is reserved as marker for non-existing entry in get_entry(x,y)-function!
    */
   function add( $x, $y, $obj )
   {
      if( is_null($this->get_entry($x,$y)) )
         $this->infos[MATRIX_NUM_ENTRIES]++;

      $this->entries_x[$x][$y] = $obj;
      $this->entries_y[$y][$x] = 1;

      if( !isset($this->infos[MATRIX_MIN_X]) || $x < @$this->infos[MATRIX_MIN_X] )
         $this->infos[MATRIX_MIN_X] = $x;
      if( !isset($this->infos[MATRIX_MAX_X]) || $x > @$this->infos[MATRIX_MAX_X] )
         $this->infos[MATRIX_MAX_X] = $x;
      if( !isset($this->infos[MATRIX_MIN_Y]) || $y < @$this->infos[MATRIX_MIN_Y] )
         $this->infos[MATRIX_MIN_Y] = $y;
      if( !isset($this->infos[MATRIX_MAX_Y]) || $y > @$this->infos[MATRIX_MAX_Y] )
         $this->infos[MATRIX_MAX_Y] = $y;
   }

   /*! \brief Returns added entry at specific position or NULL otherwise. */
   function get_entry( $x, $y )
   {
      return ( isset($this->entries_x[$x][$y]) ) ? $this->entries_x[$x][$y] : NULL;
   }

   /*! \brief Returns array with all x-coordinates. */
   function get_x_axis()
   {
      return array_keys( $this->entries_x );
   }

   /*! \brief Returns array with all y-coordinates. */
   function get_y_axis()
   {
      return array_keys( $this->entries_y );
   }

   /*!
    * \brief Returns entries for specified x-coordinate as non-null array.
    * param sortflag 0 to keep original order;
    *       otherwise sortflag like for PHP sort()-func: SORT_REGULAR, SORT_NUMERIC, SORT_STRING
    */
   function get_y_entries( $x, $sortflag=0 )
   {
      if( !isset($this->entries_x[$x]) )
         return array();
      $result = $this->entries_x[$x];
      if( $sortflag )
         sort($result, $sortflag);
      return $result;
   }

   /*!
    * \brief Returns entries for specified y-coordinate as non-null array.
    * param sortflag 0 to keep original order;
    *       otherwise sortflag like for PHP sort()-func: SORT_REGULAR, SORT_NUMERIC, SORT_STRING
    */
   function get_x_entries( $y, $sortflag=0 )
   {
      if( !isset($this->entries_y[$y]) )
         return array();
      $result = $this->entries_y[$y];
      if( $sortflag )
         sort($result, $sortflag);
      return $result;
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
