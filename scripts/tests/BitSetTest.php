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

// Call BitSetTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
   define("PHPUnit_MAIN_METHOD", "BitSetTest::main");
}

require_once "PHPUnit/Framework/TestCase.php";
require_once "PHPUnit/Framework/TestSuite.php";

require_once 'include/classlib_bitset.php';


/**
 * Test class for BitSet.
 * Generated by PHPUnit_Util_Skeleton on 2009-01-10 at 13:09:17.
 */
class BitSetTest extends PHPUnit_Framework_TestCase {

   /** standard BitSet, inited before each test in setUp-func. */
   private $bitset;

   // consts
   private $store_allset = array( 16777215,16777215,4095 );
   private $store_clear  = array( 0,0,0 );

   /**
    * Runs the test methods of this class.
    *
    * @access public
    * @static
    */
   public static function main() {
      require_once "PHPUnit/TextUI/TestRunner.php";

      $suite  = new PHPUnit_Framework_TestSuite("BitSetTest");
      $result = PHPUnit_TextUI_TestRunner::run($suite);
   }

   /** Sets up the fixture. */
   protected function setUp() {
      $this->bitset = BitSet::read_from_hex('AFFE');
   }

   /** Test constructor. */
   public function test_BitSet() {
      $bitset = new BitSet();
      $this->assertEquals( $this->store_clear, $bitset->_get_store() );
   }

   /** Test reset(). */
   public function test_reset() {
      $this->assertNotEquals( '0', $this->bitset->get_bin_format() );

      $this->bitset->reset();
      $this->assertEquals( '0', $this->bitset->get_bin_format() );
      $this->assertEquals( $this->store_clear, $this->bitset->_get_store() );

      $this->bitset->reset(1);
      $this->assertEquals( str_repeat('1',BITSET_MAXSIZE), $this->bitset->get_bin_format() );
      $this->assertEquals( $this->store_allset, $this->bitset->_get_store() );
   }

   /** Test set_bit(). */
   public function test_set_bit() {
      // test constructor
      $this->bitset = new BitSet();
      $this->assertEquals( '0', $this->bitset->get_bin_format() );

      // invalid bitpos
      $r = $this->bitset->set_bit(-1);
      $this->assertFalse( $r );
      $this->assertEquals( '0', $this->bitset->get_bin_format() );
      $r = $this->bitset->set_bit(0);
      $this->assertFalse( $r );
      $this->assertEquals( '0', $this->bitset->get_bin_format() );
      $r = $this->bitset->set_bit(BITSET_MAXSIZE + 1);
      $this->assertFalse( $r );
      $this->assertEquals( '0', $this->bitset->get_bin_format() );

      // valid bitpos
      $this->bitset->set_bit(1);
      $this->assertEquals( '1', $this->bitset->get_bin_format() );
      $this->bitset->set_bit(8);
      $this->assertEquals( '10000001', $this->bitset->get_bin_format() );
      $this->bitset->set_bit(1, 0);
      $this->assertEquals( '10000000', $this->bitset->get_bin_format() );
      $this->bitset->set_bit(1, 'set_it');
      $this->assertEquals( '10000001', $this->bitset->get_bin_format() );
      $this->bitset->set_bit(8, null);
      $this->assertEquals( '1', $this->bitset->get_bin_format() );
   }

   /** Test clear_bit(). */
   public function test_clear_bit() {
      $this->bitset->clear_bit(1); // no-effect, because 0
      $this->bitset->clear_bit(4);
      $this->bitset->clear_bit(8);
      $this->bitset->clear_bit(12);
      $this->bitset->clear_bit(16);
      $this->assertEquals( '2776', $this->bitset->get_hex_format() );
   }

   /** Test toggle_bit(). */
   public function test_toggle_bit() {
      $this->bitset->toggle_bit(1);
      $this->assertEquals( 'afff', $this->bitset->get_hex_format() );

      $bitset = new BitSet();
      $this->assertEquals( '0', $bitset->get_bin_format() );
      $bitset->toggle_bit(BITSET_MAXSIZE);
      $this->assertEquals( '1'.str_repeat('0',BITSET_MAXSIZE-1), $bitset->get_bin_format() );
      $bitset->toggle_bit(BITSET_MAXSIZE);
      $bitset->toggle_bit(2);
      $this->assertEquals( '10', $bitset->get_bin_format() );
   }

   /** Test get_bit(). */
   public function test_get_bit() {
      // invalid bitpos
      $this->assertEquals( 0, $this->bitset->get_bit(-1) );
      $this->assertEquals( 0, $this->bitset->get_bit(BITSET_MAXSIZE+1) );

      // valid bitpos
      $this->assertEquals( 0, $this->bitset->get_bit(1) );
      $this->assertEquals( 1, $this->bitset->get_bit(2) );
      $this->assertEquals( 1, $this->bitset->get_bit(16) );
   }

   /** Test get_size(). */
   public function test_get_size() {
      $bitset = new BitSet();
      $this->assertEquals( 0, $bitset->get_size() );
      $bitset = BitSet::read_from_hex('5');
      $this->assertEquals( 3, $bitset->get_size() );
      $bitset = BitSet::read_from_hex('7fff');
      $this->assertEquals( 15, $bitset->get_size() );
      $bitset = BitSet::read_from_hex('0FFFFFFFFFFFFFFF'); // 1 nibble = 4 bit
      $this->assertEquals( 60, $bitset->get_size() );
      $bitset = BitSet::read_from_bin('1'.str_repeat('0',BITSET_MAXSIZE-1));
      $this->assertEquals( BITSET_MAXSIZE, $bitset->get_size() );
   }

   /** Test get_bitpos_array(). */
   public function test_get_bitpos_array() {
      $this->assertEquals(
         array( 16,14, 12,11,10,9, 8,7,6,5, 4,3,2 ), // AFFE
         $this->bitset->get_bitpos_array() );

      $bitset = new BitSet();
      $this->assertEquals( array(), $bitset->get_bitpos_array() ); // empty
      $bitset = BitSet::read_from_bin('10110');
      $this->assertEquals( array( 5, 3, 2 ), $bitset->get_bitpos_array() );
   }

   /** Test get_int_array(). */
   public function test_get_int_array() {
      // AFFE
      $this->assertEquals( array( 45054, 0 ), $this->bitset->get_int_array() );
      $this->assertEquals( array( 45054 ), $this->bitset->get_int_array( false ) );

      $bitset = BitSet::read_from_hex('AFFEAFFE');
      $this->assertEquals( array( 805220350, 2 ), $bitset->get_int_array() );

      $bitset = new BitSet();
      $this->assertEquals( array( 0,0 ), $bitset->get_int_array() );
      $this->assertEquals( array(), $bitset->get_int_array( false ) );

      $maxbitset = BitSet::read_from_hex('FFFFFFFFFFFFFFF'); // max-val
      $this->assertEquals( array( 1073741823, 1073741823 ), $maxbitset->get_int_array() );
      $this->assertEquals( array( 255,255,255,255, 255,255,255,15 ), $maxbitset->get_int_array(true,8) );
   }

   /** Test get_bin_format(). */
   public function test_get_bin_format() {
      $this->assertEquals( '1010111111111110', $this->bitset->get_bin_format() );
      $bitset = new BitSet();
      $this->assertEquals( '0', $bitset->get_bin_format() );
   }

   /** Test get_hex_format(). */
   public function test_get_hex_format() {
      $this->assertEquals( 'affe', $this->bitset->get_hex_format() );
      $this->assertEquals( 'AFFE', $this->bitset->get_hex_format(true) );
      $bitset = new BitSet();
      $this->assertEquals( '0', $bitset->get_hex_format() );
   }

   /** Test get_db_set(). */
   public function test_get_db_set() {
      $bitset = BitSet::read_from_bin('101');
      $this->assertEquals( 'b3,b1', $bitset->get_db_set() );
      $this->assertEquals( 'sf3,sf1', $bitset->get_db_set('sf') );
      $bitset = new BitSet();
      $this->assertEquals( '', $bitset->get_db_set() );
   }

   /** Test static read_from_int_array(). */
   public function test_read_from_int_array() {
      $bitset = BitSet::read_from_int_array( array( 45054 ) );
      $this->assertEquals( array( 45054,0,0 ), $bitset->_get_store() );
      $bitset = BitSet::read_from_int_array( array( 45054,0 ) );
      $this->assertEquals( array( 45054,0,0 ), $bitset->_get_store() );

      $bitset = BitSet::read_from_int_array( array( 1073741823, 1073741823 ) );
      $this->assertEquals( $this->store_allset, $bitset->_get_store() );
      $bitset = BitSet::read_from_int_array( array( 255,255,255,255, 255,255,255,15 ),8 );
      $this->assertEquals( $this->store_allset, $bitset->_get_store() );
      $bitset = BitSet::read_from_int_array( array( 0,1,1 ), 1 ); // 1-bit-import
      $this->assertEquals( array( 6,0,0 ), $bitset->_get_store() );
      $this->assertEquals( '110', $bitset->get_bin_format(true) );
      $bitset = BitSet::read_from_int_array( array( 6,1,7,5,2 ), 3 ); // 3-bit-import
      $this->assertEquals( '10101111001110', $bitset->get_bin_format(true) );
      $this->assertEquals( array( base_convert('10101111001110',2,10),0,0 ), $bitset->_get_store() );

      // "bad" normal args
      $bitset = BitSet::read_from_int_array( array( 1073741824, 1073741824 ) );
      $this->assertEquals( $this->store_clear, $bitset->_get_store() );
      $bitset = BitSet::read_from_int_array( array( 2147483647, 2147483647 ) );
      $this->assertEquals( $this->store_allset, $bitset->_get_store() );
      $bitset = BitSet::read_from_int_array( array( 0,1 ), -1); // ok => 1
      $this->assertEquals( array( 2,0,0 ), $bitset->_get_store() );
      $bitset = BitSet::read_from_int_array( array( 0,1 ), 0); // ok => 1
      $this->assertEquals( array( 2,0,0 ), $bitset->_get_store() );
      $bitset = BitSet::read_from_int_array( array( 45054 ), 31 ); // 2nd arg too big
      $this->assertEquals( array( 45054,0,0 ), $bitset->_get_store() );
      $bitset = BitSet::read_from_int_array( array( 1,1, 255 ) ); // too many items
      $this->assertEquals( array( 1,64,0 ), $bitset->_get_store() );
      $bitset = BitSet::read_from_int_array( array( -1, 0 ) ); // val < 0
      $this->assertEquals( array( 16777215,63,0 ), $bitset->_get_store() );
      $bitset = BitSet::read_from_int_array( array( -1, 3 ) ); // val < 0 (former -1)
      $this->assertEquals( array( 16777215,255,0 ), $bitset->_get_store() );
   }

   /** Test static read_from_bin(). */
   public function test_read_from_bin() {
      $bitset = BitSet::read_from_bin('');
      $this->assertEquals( $this->store_clear, $bitset->_get_store() );
      $bitset = BitSet::read_from_bin('0');
      $this->assertEquals( $this->store_clear, $bitset->_get_store() );

      $bitset = BitSet::read_from_bin('10110');
      $this->assertEquals( array( 22,0,0 ), $bitset->_get_store() );
      $bitset = BitSet::read_from_bin('1'.str_repeat('0',BITSET_MAXSIZE-1));
      $this->assertEquals( array( 0,0,2048 ), $bitset->_get_store() );
      $bitset = BitSet::read_from_bin(str_repeat('1',BITSET_MAXSIZE)); //max-val
      $this->assertEquals( $this->store_allset, $bitset->_get_store() );

      // out-of-range
      $bitset = BitSet::read_from_bin(null);
      $this->assertEquals( '0', $bitset->get_bin_format() );
      $bitset = BitSet::read_from_bin( str_repeat('1',BITSET_MAXSIZE + 1) ); // too large
      $this->assertEquals( $this->store_allset, $bitset->_get_store() );

      // invalid
      $this->assertNull( BitSet::read_from_bin('abc') );
      $this->assertNull( BitSet::read_from_bin('0210') );
   }

   /** Test static read_from_hex(). */
   public function test_read_from_hex() {
      $bitset = BitSet::read_from_hex('');
      $this->assertEquals( $this->store_clear, $bitset->_get_store() );
      $bitset = BitSet::read_from_hex('0');
      $this->assertEquals( $this->store_clear, $bitset->_get_store() );

      $bitset = BitSet::read_from_hex('DEAFBEE');
      $this->assertEquals( array( 15399918,13,0 ), $bitset->_get_store() );
      $bitset = BitSet::read_from_hex('FFFFFFFFFFFFFFF'); // max-val
      $this->assertEquals( $this->store_allset, $bitset->_get_store() );

      // "bad" normal args
      $bitset = BitSet::read_from_hex(null);
      $this->assertEquals( $this->store_clear, $bitset->_get_store() );
      $bitset = BitSet::read_from_hex('1FFFFFFFFFFFFFFF'); // too large
      $this->assertEquals( $this->store_allset, $bitset->_get_store() );

      // invalid
      $this->assertNull( BitSet::read_from_hex('zoff') );
      $this->assertNull( BitSet::read_from_hex('7c 93') );
   }

   /** Test static read_from_db_set(). */
   public function test_read_from_db_set() {
      $bitset = BitSet::read_from_db_set('');
      $this->assertEquals( $this->store_clear, $bitset->_get_store() );

      $bitset = BitSet::read_from_db_set('b3,b5,b2');
      $this->assertEquals( array( 22,0,0 ), $bitset->_get_store() );
      $bitset = BitSet::read_from_db_set('_x_3,_x_5,_x_2', '_x_');
      $this->assertEquals( array( 22,0,0 ), $bitset->_get_store() );
      $bitset = BitSet::read_from_db_set('b'.BITSET_MAXSIZE);
      $this->assertEquals( array( 0,0,2048 ), $bitset->_get_store() );

      // "bad" normal args
      $bitset = BitSet::read_from_db_set(null);
      $this->assertEquals( $this->store_clear, $bitset->_get_store() );
      $bitset = BitSet::read_from_db_set('b0,b3'); // out-of-range
      $this->assertEquals( array( 4,0,0 ), $bitset->_get_store() );
      $bitset = BitSet::read_from_db_set('b8,b'.(BITSET_MAXSIZE+1) ); // out-of-range
      $this->assertEquals( array( 128,0,0 ), $bitset->_get_store() );

      // invalid
      $this->assertNull( BitSet::read_from_db_set('abc') );
      $this->assertNull( BitSet::read_from_db_set('1', '') );
      $this->assertNull( BitSet::read_from_db_set('1', null) );
      $this->assertNull( BitSet::read_from_db_set('ba,b8') );
      $this->assertNull( BitSet::read_from_db_set('b1,b3,b5,wumm') );
      $this->assertNull( BitSet::read_from_db_set('b1,b') );
   }

}

// Call BitSetTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "BitSetTest::main") {
   BitSetTest::main();
}
?>
