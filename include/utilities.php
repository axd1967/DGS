<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
   if ( $m < 0 ) $m = -$m;
   return $k - $m * floor($k/$m);
}

// [ v1, v2, ... ] converted to [ v1 => v1, v2 => v2, ... ]
function array_value_to_key_and_value( $array )
{
   $new_array = array();
   foreach ( $array as $value )
      $new_array[$value] = $value;
   return $new_array;
}

// param $is_count false => $count is "end" of range
function build_num_range_map( $start, $count, $is_count=true )
{
   $arr = array();
   $end_range = ( $is_count ) ? $start + $count - 1 : $count;
   for ( $i=$start; $i <= $end_range; $i++ )
      $arr[$i] = $i;
   return $arr;
}

// returns string-representation of flat map: "key=[val], ..."
// NOTE: similar to PHP-funcs var_export() and print_r()
function map_to_string( $map, $sep=', ' )
{
   if ( !is_array($map) )
      return '';

   $arr = array();
   foreach ( $map as $key => $val )
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
   while ( $h > $l )
   {
      $p = ($h+$l) >> 1;
      if ( $needle > $haystack[$p] )
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
   if ( is_string($val) )
   {
      $val = trim($val);
      if ( strlen($val) > 1 )
      {
         if ( substr($val,-1) == '%' && is_numeric($minimum) && is_numeric($maximum) )
            $val = ( $maximum - $minimum ) * (substr($val,0,-1) / 100.) + $minimum;
         elseif ( is_numeric(strpos('hHxX#$', $val[0])) )
            $val = base_convert( substr($val,1), 16, 10);
      }
   }

   if ( !is_numeric($val) )
      return (isset($default) ? $default : $val );
   elseif ( is_numeric($minimum) && $val < $minimum )
      return $minimum;
   elseif ( is_numeric($maximum) && $val > $maximum )
      return $maximum;

   return $val;
}

/*!
 * \brief Extracts and returns value from attributes-string (no spaces allowed in rxname): rxname=rxvalue ...
 * \return NULL if no match found
 */
function extract_regex_value( $string, $rxname, $rxvalue="[-\w]+" )
{
   if ( preg_match( "/\s+($rxname)=($rxvalue)/i", $string, $matches) )
      return $matches[2];
   else
      return null;
}

/*! \brief (GUI) Returns string with some basic replaced HTML ( < > " ' ) with HTML-entities. */
function basic_safe( $str )
{
   return str_replace(
         array( '<', '>', '"', "'" ),
         array( '&lt;', '&gt;', '&quot;', '&#39;' ), // &#39; == &apos; is no HTML-standard (not supported by all browsers)
         $str );
}

/*! \brief (GUI) Returns string with some basic replacement for a JavaScript-string. */
function js_safe( $str, $quote="'" )
{
   $str = str_replace(
         array( "\r", "\n" ),
         array( '\r', '\n' ),
         addslashes($str) );
   return $quote . $str. $quote;
}

/*! \brief (GUI) Returns JavaScript initializing global-var with quoted PHP-string. */
function add_js_var( $varname, $text, $raw_text=false )
{
   return sprintf( "var %s = %s;\n", $varname, ($raw_text ? $text : js_safe($text)) );
}

/*! \brief Formats elements of array with given sprintf-format */
function format_array( $arr, $fmt_elem )
{
   $str = '';
   if ( is_array($arr) && count($arr) > 0 )
   {
      foreach ( $arr as $elem )
         $str .= sprintf( $fmt_elem, $elem );
   }
   return $str;
}

/*!
 * \brief Returns array intersecting first array $array1 with var-args given flat arrays
 * \note: signature: array array_intersect_key_values( hash $array1, key-array, ... )
 */
function array_intersect_key_values( $array1 ) // var-args
{
   $arr_keys = array();

   $cnt_args = func_num_args();
   for ( $i=1; $i < $cnt_args; $i++)
   {
      $arr_intersect = func_get_arg($i);
      if ( !is_array($arr_intersect) )
         error('invalid_args', "array_intersect_key.check_arr(arg$i)");
      foreach ( $arr_intersect as $key )
      {
         if ( isset($array1[$key]) )
            $arr_keys[$key] = 1;
      }
   }

   // build intersection in key-order of given main-array
   $arr = array();
   foreach ( $array1 as $key => $val )
   {
      if ( isset($arr_keys[$key]) )
         $arr[$key] = $val;
   }
   return $arr;
}

/*! \brief Format number with sign. */
function format_number( $num )
{
   return ( $num <= 0 ) ? $num : "+$num";
}

/*! \brief Concats two strings separated by given separator if strings are non-empty. */
function concat_str( $str1, $sep, $str2 )
{
   if ( (string)$str1.$str2 == '' )
      return '';
   else
      return $str1 . ( ((string)$str1 != '' && (string)$str2 != '') ? $sep : '' ) . $str2;
   return $result;
}

/*! \brief Returns -1 (a<b), 1 (a>b), 0 (a=b). */
function cmp_int( $a, $b )
{
   if ( $a == $b )
      return 0;
   else
      return ( $a < $b ) ? -1 : 1;
}

/*!
 * \brief Cuts string after $len chars, append $hardcut if set and str-len > $len.
 * \param $handle_entities keeps HTML-entities intact if true; strings like '&quot;' may end up cut if false
 */
function cut_str( $str, $len, $handle_entities=true, $hardcut='...' )
{
   $s = substr($str, 0, $len);

   if ( $handle_entities )
   {
      $pos = strrpos($s, '&');
      if ( $pos !== false && preg_match("/(\\&(#\\d+|[a-z]+);)/i", substr($str,$pos), $matches) )
      {
         if ( $pos + strlen($matches[1]) > $len )
            $s = substr($s, 0, $pos) . $matches[1];
      }
   }

   if ( $hardcut && strlen($str) > $len )
      $s .= $hardcut;

   return $s;
}//cut_str

/**
 * [Rod] I've noticed that we can't know what is the encoding used at input time
 * by a user. For instance, with my FireFox browser 1.0.7, I may force the
 * encoding of the displayed page. Doing this, the same data in the same
 * input field may be received in various way by PHP:
 * -with ISO-8859-1 forced: "ü"(u-umlaut) is received as 1 byte (HEX:"\xFC")
 * -with UTF-8 forced: the same "ü" is received as 2 bytes (HEX:"\xC3\xBC")
 * Maybe this makes sense, but, as I've not found any information which could
 * say us what was the "encoding used at input time", this leaves us with
 * the unpleasant conclusion: we can't be sure to decode a string from
 * $_RESQUEST[] in the right way.
 * See also make_translationfiles.php
 * At least, the following function will keep the string AscII7, just
 * disturbing the display until the admin will enter a better HTML entity.
 **/
function latin1_safe( $str )
{
   //return $str;
   $res= preg_replace(
      "%([\\x80-\\xff])%ise",
      //"'[\\1]'",
      "'&#'.ord('\\1').';'",
      $str);
   return $res;
}

function isNumber( $value, $allow_negative=true, $allow_empty=false )
{
   if ( $allow_empty && (string)$value == '' )
      return true;
   $rx_sign = ($allow_negative) ? '\\-?' : '';
   return preg_match( "/^{$rx_sign}\d+$/", $value );
}

/*! \brief Builds relative or absolute path (path_def if absolute starting with '/' or src_path/path_def if relative). */
function build_path_dir( $src_path, $path_def )
{
   return ( substr($path_def,0,1) == '/' ) ? $path_def : $src_path . '/' . $path_def;
}

/*! \brief Appends $item to $str if not there already separated by $sep. */
function append_unique( $str, $item, $sep=',' )
{
   if ( (string)$str == '' )
      $str = $item;
   elseif ( !preg_match("/\\b".preg_quote($item)."\\b/", $str) )
      $str .= $sep . $item;
   return $str;
}

/*!
 * \brief Returns build_text_list( 'f', array( 'a', 'b' ), '|' ) == f(a) .'|'. f(b)
 * \param $arr array( items, ...) or string-item
 */
function build_text_list( $funcname, $arr, $sep=',' )
{
   $out = array();
   if ( !is_array($arr) )
      $arr = array( $arr );
   foreach ( $arr as $item )
      $out[] = call_user_func( $funcname, $item );
   return implode($sep, $out);
}

/*! \brief Formats text-lines by breaking line after $item_count items. */
function build_text_block( $arr, $item_count, $item_sep=', ', $line_sep="<br>\n" )
{
   if ( $item_count < 1 )
      $item_count = 1;

   $lines = array();
   $cnt_all = count($arr);
   for ( $cnt = 0; $cnt < $cnt_all; $cnt += $item_count )
   {
      $lines[] = implode($item_sep, array_slice( $arr, $cnt, $item_count ) )
         . ( $cnt + $item_count < $cnt_all ? $item_sep : '' );
   }

   return implode($line_sep, $lines);
}//build_text_block

function signum( $x )
{
   return ($x < 0) ? -1 : ($x == 0 ? 0 : 1);
}

function interpolate( $y1, $y3, $x1, $x2, $x3 )
{
   if ( $x1 == $x3 )
      return $y3;
   return $y3 + ( $y1 - $y3 ) * ( $x2 - $x3 ) / ( $x1 - $x3 );
}

function calculate_mean( $arr )
{
   return array_sum($arr) / count($arr);
}

/*!
 * \brief Returns median of number array.
 * \param $arr non-null, non-empty array with numbers to find median for
 */
function array_median( $arr ) {
   sort($arr, SORT_NUMERIC);

   $cnt = count($arr);
   $mid_idx = floor( $cnt / 2 );
   $median = $arr[$mid_idx];
   if ( ($cnt & 1) == 0 ) // even number of items
      $median = ( $median + $arr[$mid_idx - 1] ) / 2;
   return $median;
}//array_median

?>
