<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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


// ------ internal consts (change with care) !! -------

// number of bits stored in one integer-value:
// - must be power of 16 (4-bit nibbles) - needed for easy hex-conversion
// - must be <32, ints in PHP are signed and bit-operations may have strange results
define('BITSET_STORE_BITS', 24);
define('BITSET_STORE_NIBBLES', (BITSET_STORE_BITS >> 2));
define('BITSET_STORE_MASK', ((1 << BITSET_STORE_BITS) - 1) );
define('BITSET_EXPORT_INTBITS', 30); // assure: (val<=30)
// increase factor, if more bits needed
define('BITSET_MAXSIZE', (2 * BITSET_EXPORT_INTBITS));


 /*!
  * \class BitSet
  *
  * \brief Class to manage bit-sets with arbitrary number of bits
  *
  * Examples:
  *    $b = new BitSet();
  *    $b = BitSet::read_from_int_array(array( 97, 83));
  *    $b = BitSet::read_from_bin('100111001');
  *    $b = BitSet::read_from_hex('9B1');
  *    $b = BitSet::read_from_db_set(prefix,'b1,b7');
  *    $b->set_bit(1 [,false]);
  *    $b->clear_bit(1);
  *    $b->clear(1);
  *    $b->toggle_bit(1);
  *    $b->get_bit(1);
  *    $b->get_size();
  *    $b->get_bitpos_array();
  *    $b->get_int_array();
  *    $b->get_bin_format();
  *    $b->get_hex_format();
  *    $b->get_db_set('b');
  *
  * NOTE: GMP is not used for implementation, since it needs PHP5.1 for Windows-platforms.
  *       Can later be replaced using GMP-library.
  *
  * NOTE: BitSet-operations return 0 if bit-number is out-of-range.
  * NOTE: Maximum bit-pos is 62 (see const BITSET_MAXSIZE).
  */
class BitSet
{

   /*!
    * \brief int-array storing BitSet with 24-bit integers (see const BITSET_INTBITS);
    *        first int are lowest 24-bit, next int are next 24-bits, etc.
    */
   var $store;

   /*! \brief Constructs empty BitSet (all bits cleared with maximum num of bits). */
   function BitSet()
   {
      $this->store = array_fill( 0, BitSet::_arrpos(BITSET_MAXSIZE) + 1, 0 );
   }

   /*! \brief Clears all bits in this BitSet. */
   function clear()
   {
      for( $i=0; $i < count($this->store); $i++)
      {
         $this->store[$i] = 0;
      }
   }

   /*!
    * \brief Sets bit as pos (1..n) in this BitSet according to 2nd argument, return if valid bitpos used.
    * \param $bitpos position of bit (1..n); no operation if bitpos out of range (but return false)
    * \param $setval set bit if true; otherwise clear bit
    * \return true if bitpos is valid; false otherwise
    */
   function set_bit( $bitpos, $setval=true )
   {
      if( $bitpos >=1 && $bitpos <= BITSET_MAXSIZE )
      {
         $arrpos = BitSet::_arrpos($bitpos);
         if( $setval )
            $this->store[$arrpos] |= BitSet::_intmask($bitpos);
         else
            $this->store[$arrpos] &= (~BitSet::_intmask($bitpos));
         return true;
      }
      else
         return false;
   }

   /*! \brief Clears bit at pos (1..n) in this BitSet; return if valid bitpos used. */
   function clear_bit( $bitpos )
   {
      return $this->set_bit( $bitpos, 0 );
   }

   /*! \brief Toggles bit at pos (1..n) in this BitSet; return if valid bitpos used. */
   function toggle_bit( $bitpos )
   {
      if( $bitpos >=1 && $bitpos <= BITSET_MAXSIZE )
      {
         $arrpos = BitSet::_arrpos($bitpos);
         $this->store[$arrpos] ^= BitSet::_intmask($bitpos);
         return true;
      }
      else
         return false;
   }

   /*! \brief Returns 1 if bit is set at given pos (1..n); 0 if pos out-of-range or if bit is not set. */
   function get_bit( $bitpos )
   {
      $result = 0;
      if( $bitpos >=1 && $bitpos <= BITSET_MAXSIZE )
      {
         $arrpos = BitSet::_arrpos($bitpos);
         if( $this->store[$arrpos] & BitSet::_intmask($bitpos) )
            $result = 1;
      }
      return $result;
   }

   /*! \brief Returns maximum bit-position with set bit; 0 if no bit set. */
   function get_size()
   {
      $arrpos = count($this->store);
      while( $arrpos-- >= 0 )
      {
         if( ($value = $this->store[$arrpos]) > 0 )
            return $arrpos * BITSET_STORE_BITS + intval( floor(log($value) / log(2)) ) + 1;
      }
      return 0;
   }

   /*! \brief Returns array with bit-positions with set bits (high-end first). */
   function get_bitpos_array()
   {
      $out = array();
      for( $bitpos = $this->get_size(); $bitpos >= 1; $bitpos-- )
      {
         if( $this->get_bit($bitpos) )
            $out[] = $bitpos;
      }
      return $out;
   }

   /*!
    * \brief Returns array with integers building BitSet (low-end first(!)), leading 0's are included per default.
    * \param $leading_zero if false, don't include leading 0's (may result in empty array for cleared bitset)
    * \param $bits_per_int number of bits (<31) stored in one int-value (BITSET_EXPORT_INTBITS default)
    * \return null if $bits_per_int-argument invalid (too large)
    */
   function get_int_array( $leading_zero=true, $bits_per_int=BITSET_EXPORT_INTBITS )
   {
      if( $bits_per_int > 30 )
         return NULL;
      if( $bits_per_int < 1 )
         $bits_per_int = 1;

      $out = array();
      $size = ($leading_zero) ? BITSET_MAXSIZE : $this->get_size();
      for( $s_bitpos = 1; $s_bitpos <= $size; $s_bitpos += $bits_per_int )
      {
         $e_bitpos = $s_bitpos + $bits_per_int - 1;
         $out[] = $this->_get_intval_range( $s_bitpos, $e_bitpos );
      }
      return $out;
   }

   /*! \brief Returns the BitSet in binary format (high-endian); 0 if all bits are cleared. */
   function get_bin_format()
   {
      // NOTE: can't use base_convert(), because not '0'-prefixed

      $binfmt = '%0'.BITSET_STORE_BITS.'b';
      $skip0 = true;
      $out_str = '';
      foreach( array_reverse($this->store) as $int_val )
      {
         if( $skip0 && $int_val == 0 ) continue;
         $skip0 = false;
         $out_str .= sprintf( $binfmt, $int_val );
      }
      $out_str = ltrim($out_str, '0');
      return ($out_str != '') ? $out_str : '0';
   }

   /*! \brief Returns the BitSet in hexa-decimal format (lower-case is default, high-endian); 0 if all bits are cleared. */
   function get_hex_format( $uppercase=false )
   {
      // NOTE: can't use base_convert(), because not '0'-prefixed

      $hexfmt = '%0'.(BITSET_STORE_BITS >> 2) . ($uppercase ? 'X' : 'x'); // hex-digit (=nibble)
      $skip0 = true;
      $out_str = '';
      foreach( array_reverse($this->store) as $int_val )
      {
         if( $skip0 && $int_val == 0 ) continue;
         $skip0 = false;
         $out_str .= sprintf( $hexfmt, $int_val );
      }
      $out_str = ltrim($out_str, '0');
      return ($out_str != '') ? $out_str : '0';
   }

   /*!
    * \brief Returns the BitSet in mysql-SET-format with given prefix, assuming SET('b1','b2',..,'bN').
    * \param $prefix prefix for database-SET value-names, default='b'
    * \return Example: for bits #1,#3 set return 'b3,b1' (can be used as MySQL-SET value)
    */
   function get_db_set( $prefix='b' )
   {
      $func = create_function( '$v', 'return "'.$prefix.'".$v;' );
      return implode(',', array_map( $func, $this->get_bitpos_array()) );
   }


   // ---------- internal non-static funcs -------------

   /*! \brief Access to store for outsiders independent from internal namings. */
   function _get_store()
   {
      return $this->store;
   }

   /*!
    * \brief Returns integer-value extracted from start- to end-bitpos range.
    * \return -1 on error (range too big)
    * \internal
    */
   function _get_intval_range( $spos, $epos )
   {
      // assure valid values for spos, epos; and spos <= epos
      if( $spos < 1 ) $spos = 1;
      if( $epos > BITSET_MAXSIZE ) $epos = BITSET_MAXSIZE;
      if( $spos > $epos )
      {//swap
         $tmp = $spos; $spos = $epos; $epos = $tmp;
      }
      if( ($epos - $spos + 1) > 30 )
         return -1;

      // can be optimized later using bitmasks and shifting
      $out_val = 0;
      $out_bitmask = 0x1;
      for( $bitpos = $spos; $bitpos <= $epos; $bitpos++, $out_bitmask <<= 1 )
      {
         $arrpos = BitSet::_arrpos($bitpos);
         if( $this->store[$arrpos] & BitSet::_intmask($bitpos) )
            $out_val |= $out_bitmask;
      }
      return $out_val;
   }

   /*!
    * \brief Updates store from start- to end-bitpos range extracted from integer-value
    *        (ignores bits out-of-range)
    * \return false on error; otherwise true=success
    * \internal
    */
   function _set_intval_range( $spos, $epos, $int_val )
   {
      // assure valid values for spos, epos; and spos <= epos
      if( $spos > $epos )
      {//swap
         $tmp = $spos; $spos = $epos; $epos = $tmp;
      }
      if( $spos < 1 )
         return false;
      if( $epos > BITSET_MAXSIZE ) // ignore irrelvant bits
         $epos = BITSET_MAXSIZE;
      if( ($epos - $spos + 1) > 30 )
         return false;

      // can be optimized later using bitmasks and shifting
      $chk_bitmask = 0x1;
      for( $bitpos = $spos; $bitpos <= $epos; $bitpos++, $chk_bitmask <<= 1 )
      {
         if( $int_val & $chk_bitmask )
         {
            $arrpos = BitSet::_arrpos($bitpos);
            $this->store[$arrpos] |= BitSet::_intmask($bitpos);
         }
      }
      return true;
   }


   // ------------ static functions ----------------------------

   /*!
    * \brief Returns non-null BitSet read from (low-end first(!)) int-array
    *        with given bits-per-int (<31); ignore bits non-fitting bits;
    *        expect positive int-values.
    */
   function read_from_int_array( $arr_ints, $bits_per_int=BITSET_EXPORT_INTBITS )
   {
      if( $bits_per_int < 1 )
         $bits_per_int = 1;
      elseif( $bits_per_int > 30 )
         $bits_per_int = 30;

      $bitset = new BitSet();
      if( is_null($arr_ints) )
         return $bitset;

      $maskbits_intval = (1 << $bits_per_int) - 1;
      $s_bitpos = 1;
      foreach( $arr_ints as $int_val )
      {
         if( $int_val < 0 ) continue;

         $int_val &= $maskbits_intval; // ignore not relevant bits
         if( $int_val )
         {
            $e_bitpos = $s_bitpos + $bits_per_int - 1;
            $bitset->_set_intval_range( $s_bitpos, $e_bitpos, $int_val );
         }
         $s_bitpos += $bits_per_int;
      }
      return $bitset;
   }

   /*!
    * \brief Returns BitSet read from binary-string (if empty or null assume '0'),
    *        bits not fitting into BitSet are ignored.
    * \return NULL on bad input-args (bad binary-digit used)
    */
   function read_from_bin( $bin_str )
   {
      if( is_null($bin_str) || $bin_str == '' )
         return new BitSet();
      if( !preg_match( "/^[01]+$/", $bin_str) )
         return NULL;

      // convert binary to hex-string
      // NOTE: can't use base_convert(x,2,16), it relies on double-type with limited precision
      $bitstep = 28; // 7 nibbles
      $hexfmt = '%0'.($bitstep >> 2).'x%s'; // correct number of leading 0's for hex-digits
      $bin_str = str_repeat('0',$bitstep + 1) . $bin_str; // avoid special cases
      $binlen = strlen($bin_str);
      $hex_str = '';
      for( $spos = $bitstep; $spos <= $binlen; $spos += $bitstep )
      {
         $binpart = substr($bin_str, -$spos, $bitstep);
         $hex_str = sprintf($hexfmt, base_convert($binpart,2,10), $hex_str);
      }
      return BitSet::read_from_hex( $hex_str );
   }

   /*!
    * \brief Returns BitSet read from hex-string; if empty or null assume '0',
    *        prefix '0x' is ignored, non-fitting bits are ignored.
    * \return NULL on bad input-args (used non-hex-digits)
    */
   function read_from_hex( $hex_str )
   {
      if( is_null($hex_str) || $hex_str == '' )
         return new BitSet();

      $hex_str = preg_replace( "/^(0x)?/", '', $hex_str ); // strip 0x-prefix
      if( !preg_match( "/^[0-9A-F]*$/i", $hex_str) )
         return NULL;

      $bitset = new BitSet();
      if( $hex_str == '' || $hex_str == '0' )
         return $bitset;
      $hex_str = str_repeat( '0', BITSET_STORE_NIBBLES ) . $hex_str;
      $len_hex = strlen($hex_str);

      // here: hex_str is valid and long enough
      $rpos = BITSET_STORE_NIBBLES; // str-pos from right
      $s_bitpos = 1;
      while( $rpos < $len_hex )
      {
         $hexpart_str = substr( $hex_str, -$rpos, BITSET_STORE_NIBBLES );
         $int_val = intval( $hexpart_str, 16 ); // hex -> dec
         if( $int_val )
         {
            $e_bitpos = $s_bitpos + BITSET_STORE_BITS - 1;
            $bitset->_set_intval_range( $s_bitpos, $e_bitpos, $int_val );
         }
         $rpos += BITSET_STORE_NIBBLES;
         $s_bitpos += BITSET_STORE_BITS;
      }
      return $bitset;
   }

   /*!
    * \brief Returns BitSet read from database-SET value with given prefix,
    *        non-fitting bits are ignored.
    * \return NULL on bad input-args (empty, null or bad prefix used; or bad db-item used)
    */
   function read_from_db_set( $db_set_str, $prefix='b' )
   {
      if( is_null($prefix) || strlen($prefix) == 0 )
         return NULL;

      $bitset = new BitSet();
      if( is_null($db_set_str) || $db_set_str == '' )
         return $bitset;

      $prefixlen = strlen($prefix);
      $arr_db = explode(',', $db_set_str);
      foreach( $arr_db as $db_value )
      {
         if( strncmp($db_value, $prefix, $prefixlen) != 0 ) // bad prefix
            return NULL;
         $value = substr($db_value, $prefixlen);
         if( !preg_match( "/^\d+$/", $value ) )
            return NULL;

         $bitset->set_bit( (int)$value );
      }
      return $bitset;
   }


   // ---------- internal static funcs -------------

   /*!
    * \brief Returns array-pos for given bit-position.
    * \internal
    */
   function _arrpos( $bitpos )
   {
      return intval( ($bitpos-1) / BITSET_STORE_BITS );
   }

   /*!
    * \brief Returns store-integer-pos for given bit-position.
    * \internal
    */
   function _intpos( $bitpos )
   {
      return ($bitpos-1) % BITSET_STORE_BITS;
   }

   /*!
    * \brief Returns store-integer-pos for given bit-position.
    * \internal
    */
   function _intmask( $bitpos )
   {
      return 1 << (($bitpos-1) % BITSET_STORE_BITS);
   }

} // end of 'BitSet'

?>
