<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

// Call Game_SettingsHelperTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
    define("PHPUnit_MAIN_METHOD", "Game_SettingsHelperTest::main");
}

require_once "PHPUnit/Framework/TestCase.php";
require_once "PHPUnit/Framework/TestSuite.php";

require_once 'include/game_functions.php';

/**
 * Test class for helpers to handle game-settings.
 * Generated by PHPUnit_Util_Skeleton on 2009-12-06 at 10:55:49.
 */
class Game_SettingsHelperTest extends PHPUnit_Framework_TestCase {

   /**
    * Runs the test methods of this class.
    *
    * @access public
    * @static
    */
   public static function main() {
      require_once "PHPUnit/TextUI/TestRunner.php";

      $suite  = new PHPUnit_Framework_TestSuite("Game_SettingsHelperTest");
      $result = PHPUnit_TextUI_TestRunner::run($suite);
   }

   /** Tests adjust_komi(). */
   public function test_adjust_komi() {
      // new_komi = adjust_komi( $komi, $adj_komi, $jigo_mode )

      $this->assertEquals( 6.5, adjust_komi( 6.5, 0, JIGOMODE_KEEP_KOMI ));
      $this->assertEquals( 10, adjust_komi( 6.5, +3.5, JIGOMODE_KEEP_KOMI ));
      $this->assertEquals( 3, adjust_komi( 6.5, -3.5, JIGOMODE_KEEP_KOMI ));
      $this->assertEquals( -7, adjust_komi( 6.5, -13.5, JIGOMODE_KEEP_KOMI ));

      // max-komi-range
      $this->assertEquals( MAX_KOMI_RANGE, adjust_komi( 6, MAX_KOMI_RANGE, JIGOMODE_KEEP_KOMI ));
      $this->assertEquals( -MAX_KOMI_RANGE, adjust_komi( -6, -MAX_KOMI_RANGE, JIGOMODE_KEEP_KOMI ));

      // jigo-modes (allow-jigo)
      $this->assertEquals( 16, adjust_komi( 6.5, 10, JIGOMODE_ALLOW_JIGO ));
      $this->assertEquals( -3, adjust_komi( -6.5, 3, JIGOMODE_ALLOW_JIGO ));
      $this->assertEquals( -9, adjust_komi( -6.5, -3, JIGOMODE_ALLOW_JIGO ));
      $this->assertEquals( 16, adjust_komi( 6, 10, JIGOMODE_ALLOW_JIGO ));

      // jigo-modes (deny-jigo)
      $this->assertEquals( 16.5, adjust_komi( 6, 10, JIGOMODE_NO_JIGO ));
      $this->assertEquals( -3.5, adjust_komi( -6, 3, JIGOMODE_NO_JIGO ));
      $this->assertEquals( -9.5, adjust_komi( -6, -3, JIGOMODE_NO_JIGO ));
      $this->assertEquals( 16.5, adjust_komi( 6.5, 10, JIGOMODE_NO_JIGO ));
   }

   /** Tests adjust_handicap(). */
   public function test_adjust_handicap() {
      // new_handicap = adjust_handicap( $handicap, $adj_handicap, $min_handicap=0, $max_handicap=MAX_HANDICAP )

      $this->assertEquals( 0, adjust_handicap( 0, 0 ));
      $this->assertEquals( MAX_HANDICAP, adjust_handicap( MAX_HANDICAP+10, 0 ));
      $this->assertEquals( 0, adjust_handicap( -1, 0 ));

      // valid limits
      $this->assertEquals( MAX_HANDICAP, adjust_handicap( MAX_HANDICAP+10, 0 ));
      $this->assertEquals( MAX_HANDICAP, adjust_handicap( MAX_HANDICAP, 10 ));
      $this->assertEquals( 0, adjust_handicap( -30, 5, -10, 70 ));
      $this->assertEquals( MAX_HANDICAP, adjust_handicap( 30, 5, -10, 70 ));
      $this->assertEquals( 10, adjust_handicap( -30, 40, -10, 70 ));
      $this->assertEquals( MAX_HANDICAP, adjust_handicap( MAX_HANDICAP+2, 0, MAX_HANDICAP+1, MAX_HANDICAP ));
      $this->assertEquals( MAX_HANDICAP, adjust_handicap( MAX_HANDICAP+2, 0, MAX_HANDICAP+1, -7 ));

      // swapped limits
      $this->assertEquals( 5, adjust_handicap( 2, 3, 9, 4 ));
      $this->assertEquals( 7, adjust_handicap( 2, 3, 9, 7 ));
      $this->assertEquals( 4, adjust_handicap( 10, 2, 4, 2 ));

      // add + min/max
      $this->assertEquals( 9, adjust_handicap( 2, 3, 9, 11 ));
      $this->assertEquals( 4, adjust_handicap( 2, 3, 1, 4 ));
      $this->assertEquals( 0, adjust_handicap( 0, -2 ));
      $this->assertEquals( 3, adjust_handicap( 0, -2, 3 ));
      $this->assertEquals( 2, adjust_handicap( 0, -2, 2, 9 ));
      $this->assertEquals( 9, adjust_handicap( 5, 7, 2, 9 ));
   }

}

// Call Game_SettingsHelperTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "Game_SettingsHelperTest::main") {
   Game_SettingsHelperTest::main();
}
?>
