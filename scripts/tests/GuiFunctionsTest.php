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

/* Author: Jens-Uwe Gaspar */

// Call GuiFunctionsTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
   define("PHPUnit_MAIN_METHOD", "GuiFunctionsTest::main");
}

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once 'include/gui_functions.php';



/**
 * Test class for 'include/gui_functions.php'.
 */
class GuiFunctionsTest extends PHPUnit_Framework_TestCase {

   /**
    * Runs the test methods of this class.
    *
    * @access public
    * @static
    */
   public static function main() {
      require_once 'PHPUnit/TextUI/TestRunner.php';

      $suite  = new PHPUnit_Framework_TestSuite("GuiFunctionsTest");
      $result = PHPUnit_TextUI_TestRunner::run($suite);
   }

   /** Sets up tests. */
   protected function setUp() { // setup-once would be preffered though
      global $Tr; // set some translations

      // german months
      $Tr['May'] = 'Mai';
      $Tr['Oct'] = 'Okt';
      $Tr['Dec'] = 'Dez';

      // german weekdays
      $Tr['Sun'] = 'So';
      $Tr['Mon'] = 'Mo';
      $Tr['Tue'] = 'Di';
      $Tr['Wed'] = 'Mi';
      $Tr['Thu'] = 'Do';
      $Tr['Fri'] = 'Fr';
      $Tr['Sat'] = 'Sa';
   }


   /** Tests quote_dateformat(). */
   public function test_quote_dateformat() {
      $this->assertEquals( "\\A\\B\\C", quote_dateformat('ABC'));
   }

   /** Tests format_translated_date(). */
   public function test_format_translated_date() {
      static $FMT = 'D, d-M-Y \\D\\M';

      $this->assertEquals( "Mo, 20-Mai-2013 DM", format_translated_date($FMT, mktime(13,0,0,   5,20,2013) ));
      $this->assertEquals( "Sa, 19-Okt-2013 DM", format_translated_date($FMT, mktime(13,0,0,  10,19,2013) ));
      $this->assertEquals( "Mi, 18-Dez-2013 DM", format_translated_date($FMT, mktime(13,0,0,  12,18,2013) ));
      $this->assertEquals( "Mi, 18-Dez-2013 DM", format_translated_date($FMT, mktime(13,0,0,  12,18,2013) ));
   }

}

// Call GuiFunctionsTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "GuiFunctionsTest::main") {
    GuiFunctionsTest::main();
}
?>
