<?php
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

/* Author: Jens-Uwe Gaspar */

// Call GeneralFunctionsTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
   define("PHPUnit_MAIN_METHOD", "GeneralFunctionsTest::main");
}

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once 'include/std_functions.php';
require_once 'include/coords.php';



/**
 * Test class for general-functions.
 * Generated by PHPUnit_Util_Skeleton on 2009-11-14 at 13:32:21.
 */
class GeneralFunctionsTest extends PHPUnit_Framework_TestCase {

   /**
    * Runs the test methods of this class.
    *
    * @access public
    * @static
    */
   public static function main() {
      require_once 'PHPUnit/TextUI/TestRunner.php';

      $suite  = new PHPUnit_Framework_TestSuite("GeneralFunctionsTest");
      $result = PHPUnit_TextUI_TestRunner::run($suite);
   }


   /** Tests parseDate(). */
   public function test_parseDate() {
      $this->assertEquals( 0, parseDate('test', '', /*secs*/false) );
      $this->assertTrue( (bool)preg_match("/dateformat.*wrong.*test/i", parseDate('test', 'no-date', /*secs*/false)) );

      static $ts = 1365856577; // 2013-04-13 12:36:17
      $this->assertEquals( $ts, parseDate('test', '2013-04-13 12:36:17', /*secs*/true) );
      $this->assertEquals( $ts - 17, parseDate('test', '2013-04-13 12:36:17', /*secs*/false) );
      $this->assertEquals( $ts - 17, parseDate('test', '2013-04-13 12:36', /*secs*/false) );
      $this->assertEquals( $ts - 17, parseDate('test', '2013-04-13 12:36', /*secs*/true) );
   }

   /** Tests is_valid_sgf_coords(). */
   public function test_is_valid_sgf_coords() {
      $this->assertTrue( is_valid_sgf_coords( 'aa', 5 ));
      $this->assertTrue( is_valid_sgf_coords( 'yy', 25 ));
      $this->assertTrue( is_valid_sgf_coords( 'ss', 19 ));
      $this->assertFalse( is_valid_sgf_coords( 'ss', 18 ));
      $this->assertFalse( is_valid_sgf_coords( 'AA', 5 ));
      $this->assertFalse( is_valid_sgf_coords( '', 5 ));
   }

   /** Tests is_valid_board_coords(). */
   public function test_is_valid_board_coords() {
      $this->assertTrue( is_valid_board_coords( 'a1', 5 ));
      $this->assertTrue( is_valid_board_coords( 'A1', 5 ));
      $this->assertTrue( is_valid_board_coords( 'e5', 5 ));
      $this->assertTrue( is_valid_board_coords( 'E5', 5 ));
      $this->assertFalse( is_valid_board_coords( 'f3', 5 ));
      $this->assertFalse( is_valid_board_coords( 'e6', 5 ));
      $this->assertFalse( is_valid_board_coords( '', 5 ));
      $this->assertTrue( is_valid_board_coords( 'z25', 25 ));
      $this->assertTrue( is_valid_board_coords( 'Z25', 25 ));
      $this->assertFalse( is_valid_board_coords( 'i1', 9 ));
      $this->assertFalse( is_valid_board_coords( 'i1', 5 ));
      $this->assertTrue( is_valid_board_coords( 'h8', 8 ));
      $this->assertFalse( is_valid_board_coords( 'h8', 7 ));
      $this->assertTrue( is_valid_board_coords( 't19', 19 ));
      $this->assertFalse( is_valid_board_coords( 'u19', 19 ));
      $this->assertFalse( is_valid_board_coords( 't20', 19 ));
      $this->assertFalse( is_valid_board_coords( 'T20', 19 ));
   }

}

// Call GeneralFunctionsTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "GeneralFunctionsTest::main") {
    GeneralFunctionsTest::main();
}
?>
