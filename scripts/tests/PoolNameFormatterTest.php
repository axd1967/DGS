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

// Call PoolNameFormatterTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
   define("PHPUnit_MAIN_METHOD", "PoolNameFormatterTest::main");
}

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once 'tournaments/include/tournament_pool_classes.php';


/**
 * Test class for formatting pool-name of round-robin-tournaments.
 */
class PoolNameFormatterTest extends PHPUnit_Framework_TestCase {

   /**
    * Runs the test methods of this class.
    *
    * @access public
    * @static
    */
   public static function main() {
      require_once 'PHPUnit/TextUI/TestRunner.php';

      $suite  = new PHPUnit_Framework_TestSuite("PoolNameFormatterTest");
      $result = PHPUnit_TextUI_TestRunner::run($suite);
   }

   public function test_is_valid_format() {
      $this->assertTrue( $this->is_valid('%P %L %p(num) %p(uc) %t(num) %t(uc) %%') );
      $this->assertTrue( $this->is_valid('') );

      $this->assertFalse( $this->is_valid('Text only: missing pool') );
      $this->assertFalse( $this->is_valid('  %p(num)  ') );
      $this->assertFalse( $this->is_valid(' ') );
      $this->assertFalse( $this->is_valid("%P %p(num)\r") );
      $this->assertFalse( $this->is_valid("%P %p(num)\n") );
      $this->assertFalse( $this->is_valid('%p(num) %p(uc) %t(num) %t(uc) %%') );
      $this->assertFalse( $this->is_valid('Text %A Text') );
      $this->assertFalse( $this->is_valid('Text <b>HTML forbidden</b>') );
   }

   public function test_format_with_default() {
      $this->assertEquals( 'Pool C7', PoolNameFormatter::format_with_default( 3, 7 ) );
      $this->assertEquals( 'Pool A1', PoolNameFormatter::format_with_default( 1, 1 ) );
      $this->assertEquals( 'Pool 0', PoolNameFormatter::format_with_default( 1, 0 ) );
   }

   public function test_format() {
      static $FMT = '%t(uc)-%L, %P T%t(num)/P%p(num) : %p(uc)%%';
      $this->assertEquals( 'C-League, Pool T3/P7 : G%', $this->format( $FMT, 3, 7 ) );
      $this->assertEquals( '-League, Pool T/P7 : G%', $this->format( $FMT, -1, 7 ) );
      $this->assertEquals( '29-League, Pool T29/P28 : 28%', $this->format( $FMT, 29, 28 ) );
      $this->assertEquals( '-League, Pool T/P0 : 0%', $this->format( $FMT, 1, 0 ) );
   }

   private function is_valid( $format )
   {
      $pn = new PoolNameFormatter($format);
      return $pn->is_valid_format();
   }

   private function format( $format, $tier, $pool )
   {
      $pn = new PoolNameFormatter($format);
      return $pn->format($tier, $pool);
   }

}

// Call PoolNameFormatterTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "PoolNameFormatterTest::main") {
   PoolNameFormatterTest::main();
}
?>
