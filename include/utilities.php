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

/*!
 * \file utilities.php
 *
 * \brief General PHP utility functions.
 *
 * IMPORTANT NOTE:
 *
 * This include-file contains ...
 *   - functions, that are unspecific to DGS and could easily be utils
 *     for other php-projects as well
 *   - functions without reference to other includes
 *   - no global constants definitions or global vars
 *   - functions without reference to global vars/constants
 *   - functions without DGS-translation-texts
 *   - no big GUI/HTML-contributing functions, except minor functions
 *     that can be useful for other projects too
 */



// swap values
function swap( &$a, &$b )
{
   $tmp = $a;
   $a = $b;
   $b = $tmp;
}

/****
 * get rid of the negative results of ($k % $m)
 * ( remember: ((-$k) % $m) == -($k % $m) )
 * return a int within [0..(int)abs($m)[
 *
 * if used with float, the truncation is toward zero,
 *  (( i.e. in the same way that the PHP (int)$x does:
 *     (int)$x := ( $x < 0 ? ceil($x) : floor($x) )
 *     ceil($x) := -floor(-$x)
 *  ))
 * with $m integer, an other way is to use (int)modf($k,$m):
 *  $k   abs($m)  mod($k,$m) (int)modf($k,$m)  modf($k,$m)
 *  3.0     3         0            0              0.0
 *  2.5     3         2            2              2.5
 *  2.0     3         2            2              2.0
 *  1.5     3         1            1              1.5
 *  1.0     3         1            1              1.0
 *  0.5     3         0            0              0.5
 *  0.0     3         0            0              0.0
 * -0.5     3         0     <>     2              2.5
 * -1.0     3         2            2              2.0
 * -1.5     3         2     <>     1              1.5
 * -2.0     3         1            1              1.0
 * -2.5     3         1     <>     0              0.5
 * -3.0     3         0            0              0.0
 ****/
//equ: function mod( $k, $m) { return ($k % $m + $m) % $m; }
function mod( $k, $m)
{
   $m = (int)( $m < 0 ? -$m : $m );
   $k = $k % $m;
   return ( $k < 0 ? $k + $m : $k );
}

//get rid of the negative results of fmod($k % $m)
//return a float within [0..abs($m)[
function modf( $k, $m)
{
   if( $m < 0 ) $m = -$m;
   return $k - $m * floor($k/$m);
}

if( !function_exists('array_combine') ) //exists in PHP5
{
   function array_combine($keys, $values)
   {
      $res = array();
      while( (list(, $k)=each( $keys)) && (list(, $v)=each( $values)) )
      {
         $res[$k]= $v;
      }
      return $res;
   }
}

// [ v1, v2, ... ] converted to [ v1 => v1, v2 => v2, ... ]
function array_value_to_key_and_value( $array )
{
   $new_array = array();
   foreach( $array as $value )
      $new_array[$value] = $value;
   return $new_array;
}

// returns string-representation of flat map: "key=[val], ..."
// NOTE: similar to PHP-funcs var_export() and print_r()
function map_to_string( $map, $sep=', ' )
{
   if( !is_array($map) )
      return '';

   $arr = array();
   foreach( $map as $key => $val )
      $arr[]= "$key=[$val]";

   return implode( $sep, $arr );
}

/**
 * Quick search in a sorted array. $haystack must be sorted.
 * will return:
 *  the index where $needle was found (the lower doublon if duplicates).
 *  the index of the next higher if not found (where it must be inserted).
 *  count($haystack) if no higher ($needle must be appended).
 **/
function array_bsearch( $needle, &$haystack )
{
   $h = count($haystack);
   $l = 0;
   while( $h > $l )
   {
      $p = ($h+$l) >> 1;
      if( $needle > $haystack[$p] )
         $l = $p+1;
      else
         $h = $p;
   }
   return $h;
} //array_bsearch

// restricts given value into min/max-limitations,
// or return default if value non-numerical after converting from:
// - percentaged value, e.g. "50%" -> set value to 50% of min-max-distance and adds minimum
// - hexadecimal value starting with one char of 'hHxX#$'
// Examples:
//   limit(10,2,5,0) = 5
//   limit('20%',10,110,0) = 30
//   limit('#0f',0,16,0) = 15
function limit( $val, $minimum, $maximum, $default )
{
   if( is_string($val) )
   {
      $val = trim($val);
      if( strlen($val) > 1 )
      {
         if( substr($val,-1) == '%' && is_numeric($minimum) && is_numeric($maximum) )
            $val = ( $maximum - $minimum ) * (substr($val,0,-1) / 100.) + $minimum;
         elseif( is_numeric(strpos('hHxX#$', $val[0])) )
            $val = base_convert( substr($val,1), 16, 10);
      }
   }

   if( !is_numeric($val) )
      return (isset($default) ? $default : $val );
   elseif( is_numeric($minimum) && $val < $minimum )
      return $minimum;
   elseif( is_numeric($maximum) && $val > $maximum )
      return $maximum;

   return $val;
}

/*!
 * \brief Extracts and returns value from attributes-string (no spaces allowed in rxname): rxname=rxvalue ...
 * \return NULL if no match found
 */
function extract_regex_value( $string, $rxname, $rxvalue="[-\w]+" )
{
   if( preg_match( "/\s+($rxname)=($rxvalue)/i", $string, $matches) )
      return $matches[2];
   else
      return null;
}

/*! \brief (GUI) Returns string with some basic replaced HTML ( < > " ' ) with HTML-entities. */
function basic_safe( $str )
{
   return str_replace(
         array( '<', '>', '"', "'" ),
         array( '&lt;', '&gt;', '&quot;', '&#039;' ),
         $str );
}

/*! \brief Formats elements of array with given sprintf-format */
function format_array( $arr, $fmt_elem )
{
   $str = '';
   if( is_array($arr) && count($arr) > 0 )
   {
      foreach( $arr as $elem )
         $str .= sprintf( $fmt_elem, $elem );
   }
   return $str;
}

?>
