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

// Call ProfileTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
   define("PHPUnit_MAIN_METHOD", "ProfileTest::main");
}

require_once "PHPUnit/Framework/TestCase.php";
require_once "PHPUnit/Framework/TestSuite.php";

require_once 'include/classlib_profile.php';

define('PROFTEST_USER', 3);
define('S', SEP_PROFVAL); // for test-only


/**
 * Test class for Profile.
 */
class ProfileTest extends PHPUnit_Framework_TestCase {

   /** standard Profile, inited before each test in setUp-func. */
   private $profile;

   /**
    * Runs the test methods of this class.
    *
    * @access public
    * @static
    */
   public static function main() {
      require_once "PHPUnit/TextUI/TestRunner.php";

      $suite  = new PHPUnit_Framework_TestSuite("ProfileTest");
      $result = PHPUnit_TextUI_TestRunner::run($suite);
   }

   /** Sets up the fixture. */
   protected function setUp() {
      $this->profile = Profile::new_profile( PROFTEST_USER, PROFTYPE_FILTER_USERS );
   }

   /** Test set_text() and get_text(): empty-like values. */
   public function test_setget_text_null_empty() {
      $this->assertEquals( '', $this->profile->get_text(true) );

      // null
      $this->profile->set_text(null);
      $this->assertEquals( '0', $this->profile->get_text(true) );
      $this->assertNull( $this->profile->get_text() );
      $this->profile->set_text('0');
      $this->assertEquals( '0', $this->profile->get_text(true) );
      $this->assertNull( $this->profile->get_text() );

      // ''
      $this->profile->set_text('');
      $this->assertEquals( '', $this->profile->get_text(true) );
      $this->assertEquals( array(), $this->profile->get_text() );
      $this->profile->set_text(array());
      $this->assertEquals( '', $this->profile->get_text(true) );
      $this->assertEquals( array(), $this->profile->get_text() );

      // empty array
      $this->profile->set_text(array());
      $this->assertEquals( '', $this->profile->get_text(true) );
      $this->assertEquals( array(), $this->profile->get_text() );
   }

   /** Test set_text() and get_text(): non-empty values. */
   public function test_setget_text_values() {
      $args = array( 'a' => 1 );
      $this->profile->set_text( $args );
      $this->assertEquals( 'a=1', $this->profile->get_text(true) );
      $this->assertEquals( $args, $this->profile->get_text() );

      $args['b'] = 2;
      $this->profile->set_text( $args );
      $this->assertEquals( 'a=1'.S.'b=2', $this->profile->get_text(true) );
      $this->assertEquals( $args, $this->profile->get_text() );

      $args['c'] = array( 'v1', 'v2', 'v3' );
      $this->profile->set_text( $args );
      $this->assertEquals( $args, $this->profile->get_text() );
      $this->assertEquals(
         'a=1'.S.'b=2'.S.'c%5b%5d=v1'.S.'c%5b%5d=v2'.S.'c%5b%5d=v3',
         $this->profile->get_text(true) );
   }

   /** Test set_text() and get_text(): special (encoding) values. */
   public function test_setget_text_special_values() {
      $args = array( 'a' => ' ' );
      $this->profile->set_text( $args );
      $this->assertEquals( 'a=+', $this->profile->get_text(true) );
      $this->assertEquals( $args, $this->profile->get_text() );

      $args = array( 'a' => '+' );
      $this->profile->set_text( $args );
      $this->assertEquals( 'a=%2B', $this->profile->get_text(true) );
      $this->assertEquals( $args, $this->profile->get_text() );
      $args['a'] = 1;

      $args['b'] = SEP_PROFVAL;
      $this->profile->set_text( $args );
      $this->assertEquals( $args, $this->profile->get_text() );
      $this->assertEquals( 'a=1'.S.'b=%26', $this->profile->get_text(true) );

      $args['b'] = URI_AMP;
      $this->profile->set_text( $args );
      $this->assertEquals( $args, $this->profile->get_text() );
      $this->assertEquals( 'a=1'.S.'b=%26amp%3B', $this->profile->get_text(true) );

      $args['b'] = 'c[]';
      $this->profile->set_text( $args );
      $this->assertEquals( $args, $this->profile->get_text() );
      $this->assertEquals( 'a=1'.S.'b=c%5B%5D', $this->profile->get_text(true) );
      $this->profile->set_text( 'a=1'.S.'b=c%5b%5d' ); // lower-case-encoded
      $this->assertEquals( $args, $this->profile->get_text() );

      $args['b'] = '%20';
      $this->profile->set_text( $args );
      $this->assertEquals( $args, $this->profile->get_text() );
      $this->assertEquals( 'a=1'.S.'b=%2520', $this->profile->get_text(true) );
   }

}

// Call ProfileTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "ProfileTest::main") {
   ProfileTest::main();
}
?>
