<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

// Call DefaultMaxHandicapTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
    define("PHPUnit_MAIN_METHOD", "DefaultMaxHandicapTest::main");
}

require_once "PHPUnit/Framework/TestCase.php";
require_once "PHPUnit/Framework/TestSuite.php";

require_once 'include/game_functions.php';

/**
 * Test class for checking default-max-handicap handling.
 * Generated by PHPUnit_Util_Skeleton on 2009-12-06 at 10:55:49.
 */
class DefaultMaxHandicapTest extends PHPUnit_Framework_TestCase {

   /**
    * Runs the test methods of this class.
    *
    * @access public
    * @static
    */
   public static function main() {
      require_once "PHPUnit/TextUI/TestRunner.php";

      $suite  = new PHPUnit_Framework_TestSuite("DefaultMaxHandicapTest");
      $result = PHPUnit_TextUI_TestRunner::run($suite);
   }

   /** Tests DefaultMaxHandicap::calc_def_max_handicap(). */
   public function test_calc_def_max_handicap() {
      $s = 5;
      $this->assertEquals(  2, DefaultMaxHandicap::calc_def_max_handicap( $s++ )); // 5
      $this->assertEquals(  2, DefaultMaxHandicap::calc_def_max_handicap( $s++ ));
      $this->assertEquals(  2, DefaultMaxHandicap::calc_def_max_handicap( $s++ ));
      $this->assertEquals(  2, DefaultMaxHandicap::calc_def_max_handicap( $s++ ));
      $this->assertEquals(  3, DefaultMaxHandicap::calc_def_max_handicap( $s++ )); // 9
      $this->assertEquals(  3, DefaultMaxHandicap::calc_def_max_handicap( $s++ ));
      $this->assertEquals(  3, DefaultMaxHandicap::calc_def_max_handicap( $s++ ));
      $this->assertEquals(  4, DefaultMaxHandicap::calc_def_max_handicap( $s++ ));
      $this->assertEquals(  4, DefaultMaxHandicap::calc_def_max_handicap( $s++ )); // 13
      $this->assertEquals(  5, DefaultMaxHandicap::calc_def_max_handicap( $s++ ));
      $this->assertEquals(  6, DefaultMaxHandicap::calc_def_max_handicap( $s++ ));
      $this->assertEquals(  6, DefaultMaxHandicap::calc_def_max_handicap( $s++ ));
      $this->assertEquals(  7, DefaultMaxHandicap::calc_def_max_handicap( $s++ ));
      $this->assertEquals(  8, DefaultMaxHandicap::calc_def_max_handicap( $s++ ));
      $this->assertEquals(  9, DefaultMaxHandicap::calc_def_max_handicap( $s++ )); // 19
      $this->assertEquals( 10, DefaultMaxHandicap::calc_def_max_handicap( $s++ ));
      $this->assertEquals( 11, DefaultMaxHandicap::calc_def_max_handicap( $s++ ));
      $this->assertEquals( 12, DefaultMaxHandicap::calc_def_max_handicap( $s++ ));
      $this->assertEquals( 13, DefaultMaxHandicap::calc_def_max_handicap( $s++ ));
      $this->assertEquals( 14, DefaultMaxHandicap::calc_def_max_handicap( $s++ ));
      $this->assertEquals( 16, DefaultMaxHandicap::calc_def_max_handicap( $s++ )); // 25
   }

}

// Call DefaultMaxHandicapTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "DefaultMaxHandicapTest::main") {
   DefaultMaxHandicapTest::main();
}
?>
