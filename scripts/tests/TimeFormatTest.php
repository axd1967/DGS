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

// Call TimeFormatTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
    define("PHPUnit_MAIN_METHOD", "TimeFormatTest::main");
}

require_once "PHPUnit/Framework/TestCase.php";
require_once "PHPUnit/Framework/TestSuite.php";

require_once 'include/time_functions.php';



/**
 * Test class for TimeFormat.
 * Generated by PHPUnit_Util_Skeleton on 2009-11-14 at 13:32:21.
 */
class TimeFormatTest extends PHPUnit_Framework_TestCase {

   /**
    * Runs the test methods of this class.
    *
    * @access public
    * @static
    */
   public static function main() {
      require_once "PHPUnit/TextUI/TestRunner.php";

      $suite  = new PHPUnit_Framework_TestSuite("TimeFormatTest");
      $result = PHPUnit_TextUI_TestRunner::run($suite);
   }

   /** Sets up tests. */
   protected function setUp() { // setup-once would be preffered though
      global $Tr;
      $Tr['day#short'] = 'T';
      $Tr['days#short'] = 'Te';
      $Tr['day'] = 'Tag';
      $Tr['days'] = 'Tage';
      $Tr['hour#short'] = 'Std.';
      $Tr['hours#short'] = 'Stdn.';
      $Tr['hour'] = 'Stunde';
      $Tr['hours'] = 'Stunden';
      $Tr['and'] = 'und';
      $Tr['left#2'] = 'uebrig';
   }


   /** Tests echo_day(). */
   public function test_echo_day() {
      // "7d" (short), "7 days" (long)
      // fmtflags: TIMEFMT_ENGL, TIMEFMT_SHORT, TIMEFMT_HTMLSPC
      $this->assertEquals( "7d", TimeFormat::echo_day( 7, TIMEFMT_ENGL | TIMEFMT_SHORT ));
      $this->assertEquals( "7Te", TimeFormat::echo_day( 7, TIMEFMT_SHORT ));
      $this->assertEquals( "1T", TimeFormat::echo_day( 1, TIMEFMT_SHORT ));
      $this->assertEquals( "7 days", TimeFormat::echo_day( 7, TIMEFMT_ENGL ));
      $this->assertEquals( "1 day", TimeFormat::echo_day( 1, TIMEFMT_ENGL ));
      $this->assertEquals( "7&nbsp;days", TimeFormat::echo_day( 7, TIMEFMT_ENGL | TIMEFMT_HTMLSPC ));
      $this->assertEquals( "1&nbsp;day", TimeFormat::echo_day( 1, TIMEFMT_ENGL | TIMEFMT_HTMLSPC ));
      $this->assertEquals( "7 Tage", TimeFormat::echo_day( 7, 0 ));
      $this->assertEquals( "1 Tag", TimeFormat::echo_day( 1, 0 ));
      $this->assertEquals( "0d", TimeFormat::echo_day( 0, TIMEFMT_ENGL | TIMEFMT_SHORT ));
      $this->assertEquals( "0Te", TimeFormat::echo_day( 0, TIMEFMT_SHORT ));
      $this->assertEquals( "0 days", TimeFormat::echo_day( 0, TIMEFMT_ENGL ));
      $this->assertEquals( "-2Te", TimeFormat::echo_day( -2, TIMEFMT_SHORT ));
      $this->assertEquals( "-1T", TimeFormat::echo_day( -1, TIMEFMT_SHORT ));
   }

   /** Tests echo_hour(). */
   public function test_echo_hour() {
      // "3h" (short), "3 hours" (long)
      // fmtflags: TIMEFMT_ENGL, TIMEFMT_SHORT, TIMEFMT_HTMLSPC
      $this->assertEquals( "7h", TimeFormat::echo_hour( 7, TIMEFMT_ENGL | TIMEFMT_SHORT ));
      $this->assertEquals( "7Stdn.", TimeFormat::echo_hour( 7, TIMEFMT_SHORT ));
      $this->assertEquals( "1Std.", TimeFormat::echo_hour( 1, TIMEFMT_SHORT ));
      $this->assertEquals( "7 hours", TimeFormat::echo_hour( 7, TIMEFMT_ENGL ));
      $this->assertEquals( "1 hour", TimeFormat::echo_hour( 1, TIMEFMT_ENGL ));
      $this->assertEquals( "7&nbsp;hours", TimeFormat::echo_hour( 7, TIMEFMT_ENGL | TIMEFMT_HTMLSPC ));
      $this->assertEquals( "1&nbsp;hour", TimeFormat::echo_hour( 1, TIMEFMT_ENGL | TIMEFMT_HTMLSPC ));
      $this->assertEquals( "7 Stunden", TimeFormat::echo_hour( 7, 0 ));
      $this->assertEquals( "1 Stunde", TimeFormat::echo_hour( 1, 0 ));
      $this->assertEquals( "0h", TimeFormat::echo_hour( 0, TIMEFMT_ENGL | TIMEFMT_SHORT ));
      $this->assertEquals( "0Stdn.", TimeFormat::echo_hour( 0, TIMEFMT_SHORT ));
      $this->assertEquals( "0 hours", TimeFormat::echo_hour( 0, TIMEFMT_ENGL ));
      $this->assertEquals( "-2Stdn.", TimeFormat::echo_hour( -2, TIMEFMT_SHORT ));
      $this->assertEquals( "-1Std.", TimeFormat::echo_hour( -1, TIMEFMT_SHORT ));
   }

   /** Test echo_time(). */
   public function test_echo_time() {
      // fmtflags: TIMEFMT_ENGL, TIMEFMT_SHORT, TIMEFMT_HTMLSPC
      $this->assertEquals( "7d", TimeFormat::echo_time( 7*15, TIMEFMT_ENGL | TIMEFMT_SHORT ));
      $this->assertEquals( "7 days", TimeFormat::echo_time( 7*15, TIMEFMT_ENGL ));
      $this->assertEquals( "7 Tage", TimeFormat::echo_time( 7*15, 0 ));
      $this->assertEquals( "3d 7h", TimeFormat::echo_time( 52, TIMEFMT_ENGL | TIMEFMT_SHORT ));
      $this->assertEquals( "7h", TimeFormat::echo_time( 7, TIMEFMT_ENGL | TIMEFMT_SHORT ));
      $this->assertEquals( "3 Tage und 7 Stunden", TimeFormat::echo_time( 52, 0 ));
      $this->assertEquals( "1 Tag", TimeFormat::echo_time( 1*15, 0 ));
      $this->assertEquals( NO_VALUE, TimeFormat::echo_time( 0, TIMEFMT_ENGL | TIMEFMT_SHORT ));
      $this->assertEquals( "abc", TimeFormat::echo_time( 0, TIMEFMT_ENGL | TIMEFMT_SHORT, "abc" ));
      $this->assertEquals( "", TimeFormat::echo_time( -1, TIMEFMT_ENGL | TIMEFMT_SHORT, "" ));
      $this->assertEquals( "0 hours", TimeFormat::echo_time( 0, TIMEFMT_ENGL | TIMEFMT_ZERO ));
      $this->assertEquals( "0h", TimeFormat::echo_time( 0, TIMEFMT_ENGL | TIMEFMT_SHORT | TIMEFMT_ZERO ));
   }

   /** Test echo_onvacation(). */
   public function test_echo_onvacation() {
      // fmtflags: TIMEFMT_ENGL, TIMEFMT_SHORT, TIMEFMT_HTMLSPC
      $this->assertEquals( "7d left", TimeFormat::echo_onvacation( 7, TIMEFMT_ENGL | TIMEFMT_SHORT ));
      $this->assertEquals( "7d&nbsp;left", TimeFormat::echo_onvacation( 7, TIMEFMT_ENGL | TIMEFMT_SHORT | TIMEFMT_HTMLSPC ));
      $this->assertEquals( "7 days left", TimeFormat::echo_onvacation( 7, TIMEFMT_ENGL ));
      $this->assertEquals( "7 Tage left", TimeFormat::echo_onvacation( 7, 0 ));
      $this->assertEquals( "3d 12h left", TimeFormat::echo_onvacation( 3.5, TIMEFMT_ENGL | TIMEFMT_SHORT ));
      $this->assertEquals( "12h left", TimeFormat::echo_onvacation( 0.5, TIMEFMT_ENGL | TIMEFMT_SHORT ));
      $this->assertEquals( "3 Tage und 12 Stunden left", TimeFormat::echo_onvacation( 3.5, 0 ));
      $this->assertEquals( "1 Tag left", TimeFormat::echo_onvacation( 1, 0 ));
      $this->assertEquals( NO_VALUE, TimeFormat::echo_onvacation( 0, TIMEFMT_ENGL | TIMEFMT_SHORT ));
      $this->assertEquals( "abc", TimeFormat::echo_onvacation( 0, TIMEFMT_ENGL | TIMEFMT_SHORT, "abc" ));
      $this->assertEquals( "0 hours left", TimeFormat::echo_onvacation( 0, TIMEFMT_ENGL | TIMEFMT_ZERO ));
   }

   /** Test echo_byotype(). */
   public function test_echo_byotype() {
      // fmtflags: TIMEFMT_ENGL, TIMEFMT_SHORT
      $this->assertEquals( "J", TimeFormat::echo_byotype( BYOTYPE_JAPANESE, TIMEFMT_SHORT ));
      $this->assertEquals( "C", TimeFormat::echo_byotype( BYOTYPE_CANADIAN, TIMEFMT_SHORT ));
      $this->assertEquals( "F", TimeFormat::echo_byotype( BYOTYPE_FISCHER, TIMEFMT_SHORT ));
      $this->assertEquals( "B", TimeFormat::echo_byotype( 'Bronstein', TIMEFMT_SHORT ));
      $this->assertEquals( "", TimeFormat::echo_byotype( 'Bronstein', TIMEFMT_ENGL ));
   }

   /** Tests echo_time_limit(). */
   public function test_echo_time_limit() {
      // fmtflags: TIMEFMT_ENGL, TIMEFMT_SHORT, TIMEFMT_HTMLSPC, TIMEFMT_ZERO, TIMEFMT_ADDTYPE
      $fmt = TIMEFMT_ENGL | TIMEFMT_SHORT | TIMEFMT_ADDTYPE;

      // JAP
      $this->assertEquals( "J: 4d 10h + 3d 5h * 5", TimeFormat::echo_time_limit( 70, BYOTYPE_JAPANESE, 50, 5, $fmt ));
      $this->assertEquals( "J: 4d + 3d * 2", TimeFormat::echo_time_limit( 60, BYOTYPE_JAPANESE, 45, 2, $fmt ));
      $this->assertEquals( "4 days and 2 hours + 2 days and 11 hours per move and 2 extra periods",
         TimeFormat::echo_time_limit( 62, BYOTYPE_JAPANESE, 41, 2, $fmt & ~(TIMEFMT_SHORT|TIMEFMT_ADDTYPE) ));
      $this->assertEquals( "J: 4d 2h + 2d 11h * 2",
         TimeFormat::echo_time_limit( 62, BYOTYPE_JAPANESE, 41, 2, $fmt ));
      $this->assertEquals( "J: 2d 11h * 2",
         TimeFormat::echo_time_limit( 0, BYOTYPE_JAPANESE, 41, 2, $fmt ));
      $this->assertEquals( "J: 4d 2h",
         TimeFormat::echo_time_limit( 62, BYOTYPE_JAPANESE, -1, -1, $fmt ));
      $this->assertEquals( "4 days without byoyomi",
         TimeFormat::echo_time_limit( 60, BYOTYPE_JAPANESE, -1, -1, $fmt & ~(TIMEFMT_SHORT|TIMEFMT_ADDTYPE) ));
      $this->assertEquals( "J: 0h",
         TimeFormat::echo_time_limit( 0, BYOTYPE_JAPANESE, -1, -1, $fmt | TIMEFMT_ZERO ));

      // CAN
      $this->assertEquals( "C: 4d 10h + 3d 5h / 5", TimeFormat::echo_time_limit( 70, BYOTYPE_CANADIAN, 50, 5, $fmt ));
      $this->assertEquals( "C: 4d + 3d / 2", TimeFormat::echo_time_limit( 60, BYOTYPE_CANADIAN, 45, 2, $fmt ));
      $this->assertEquals( "4 days and 2 hours + 2 days and 11 hours per 2 stones",
         TimeFormat::echo_time_limit( 62, BYOTYPE_CANADIAN, 41, 2, $fmt & ~(TIMEFMT_SHORT|TIMEFMT_ADDTYPE) ));
      $this->assertEquals( "C: 4d 2h + 2d 11h / 2",
         TimeFormat::echo_time_limit( 62, BYOTYPE_CANADIAN, 41, 2, $fmt ));
      $this->assertEquals( "C: 2d 11h / 2",
         TimeFormat::echo_time_limit( 0, BYOTYPE_CANADIAN, 41, 2, $fmt ));
      $this->assertEquals( "C: 4d 2h",
         TimeFormat::echo_time_limit( 62, BYOTYPE_CANADIAN, -1, -1, $fmt ));
      $this->assertEquals( "4 days without byoyomi",
         TimeFormat::echo_time_limit( 60, BYOTYPE_CANADIAN, -1, -1, $fmt & ~(TIMEFMT_SHORT|TIMEFMT_ADDTYPE) ));
      $this->assertEquals( "C: 0h",
         TimeFormat::echo_time_limit( 0, BYOTYPE_CANADIAN, -1, -1, $fmt | TIMEFMT_ZERO ));

      // FIS
      $this->assertEquals( "F: 4d 10h + 3d 5h", TimeFormat::echo_time_limit( 70, BYOTYPE_FISCHER, 50, 5, $fmt ));
      $this->assertEquals( "F: 4d + 3d", TimeFormat::echo_time_limit( 60, BYOTYPE_FISCHER, 45, 2, $fmt ));
      $this->assertEquals( "4 days and 2 hours with 2 days and 11 hours extra per move",
         TimeFormat::echo_time_limit( 62, BYOTYPE_FISCHER, 41, 2, $fmt & ~(TIMEFMT_SHORT|TIMEFMT_ADDTYPE) ));
      $this->assertEquals( "F: 4d 2h + 2d 11h",
         TimeFormat::echo_time_limit( 62, BYOTYPE_FISCHER, 41, 2, $fmt ));
      $this->assertEquals( "F: + 2d 11h", // no maintime makes no sense for Fischer-time
         TimeFormat::echo_time_limit( 0, BYOTYPE_FISCHER, 41, 2, $fmt ));
      $this->assertEquals( "F: 4d 2h",
         TimeFormat::echo_time_limit( 62, BYOTYPE_FISCHER, -1, -1, $fmt ));
      $this->assertEquals( "4 days",
         TimeFormat::echo_time_limit( 60, BYOTYPE_FISCHER, -1, -1, $fmt & ~(TIMEFMT_SHORT|TIMEFMT_ADDTYPE) ));
      $this->assertEquals( "F: 0h",
         TimeFormat::echo_time_limit( 0, BYOTYPE_FISCHER, -1, -1, $fmt | TIMEFMT_ZERO ));
   }

   /** Tests echo_time_remaining() for Japanese time. */
   public function test_echo_time_remaining_jap() {
      // fmtflags: TIMEFMT_ENGL, TIMEFMT_HTMLSPC, TIMEFMT_ZERO, TIMEFMT_ADDTYPE, TIMEFMT_NO_EXTRA
      $fmt = TIMEFMT_ENGL | TIMEFMT_ADDTYPE;

      // JAP: M + B * P
      $this->assertEquals( "J: 0h",
         TimeFormat::echo_time_remaining( // time is up: m=0, B=0, P>0
            0, BYOTYPE_JAPANESE, 0, -1,  0, 7, $fmt ));
      $this->assertEquals( "J: 0h",
         TimeFormat::echo_time_remaining( // time is up: m=0, B=0, P=0
            0, BYOTYPE_JAPANESE, 0, -1,  0, 0, $fmt ));
      $this->assertEquals( "J: 0h",
         TimeFormat::echo_time_remaining( // time is up: m=0, B>0, P>0, b=0
            0, BYOTYPE_JAPANESE, 0, -1,  30, 7, $fmt ));
      $this->assertEquals( "J: 0h",
         TimeFormat::echo_time_remaining( // time is up: m=0, B>0, P>0, b=p=0
            0, BYOTYPE_JAPANESE, 0, 0,  30, 7, $fmt ));
      $this->assertEquals( "J: 10d 7h (-)",
         TimeFormat::echo_time_remaining( // abs-time: m>0, B=0
            157, BYOTYPE_JAPANESE, 0, -1,  0, 7, $fmt ));
      $this->assertEquals( "J: 10d 7h (+ 2d * 7)",
         TimeFormat::echo_time_remaining( // with byoyomi: byo-yomi not started yet
            157, BYOTYPE_JAPANESE, 0, -1,  30, 7, $fmt ));
      $this->assertEquals( "J: 1d (+ 2d * 4)",
         TimeFormat::echo_time_remaining( // with byoyomi: byo-yomi started, has main-time
            15, BYOTYPE_JAPANESE, 0, 4,  30, 7, $fmt ));
      $this->assertEquals( "J: 1d 3h (2d * 4)",
         TimeFormat::echo_time_remaining( // in byoyomi
            0, BYOTYPE_JAPANESE, 18, 4,  30, 7, $fmt ));
      $this->assertEquals( "J: 1d 3h (2d * 0)",
         TimeFormat::echo_time_remaining( // in byoyomi, last period
            0, BYOTYPE_JAPANESE, 18, 0,  30, 7, $fmt ));

      // format-opts
      $this->assertEquals( "8h&nbsp;(+&nbsp;2d&nbsp;*&nbsp;7)",
         TimeFormat::echo_time_remaining( // HTML-space, no type
            8, BYOTYPE_JAPANESE, 0, -1,  30, 7,
            TIMEFMT_ENGL | TIMEFMT_HTMLSPC ));
      $this->assertEquals( "J: 1d 3h",
         TimeFormat::echo_time_remaining( // no-extra
            0, BYOTYPE_JAPANESE, 18, 0,  30, 7, $fmt | TIMEFMT_NO_EXTRA ));
   }

   /** Tests echo_time_remaining() for Canadian time. */
   public function test_echo_time_remaining_can() {
      // fmtflags: TIMEFMT_ENGL, TIMEFMT_HTMLSPC, TIMEFMT_ZERO, TIMEFMT_ADDTYPE, TIMEFMT_NO_EXTRA
      $fmt = TIMEFMT_ENGL | TIMEFMT_ADDTYPE;

      // CAN: M + B / P
      $this->assertEquals( "C: 0h",
         TimeFormat::echo_time_remaining( // time is up: m=0, B=0, P>0
            0, BYOTYPE_CANADIAN, 0, -1,  0, 4, $fmt ));
      $this->assertEquals( "C: 0h",
         TimeFormat::echo_time_remaining( // time is up: m=0, B=0, P=0
            0, BYOTYPE_CANADIAN, 0, -1,  0, 0, $fmt ));
      $this->assertEquals( "C: 0h",
         TimeFormat::echo_time_remaining( // time is up: m=0, B>0, P>0, b=0
            0, BYOTYPE_CANADIAN, 0, -1,  90, 4, $fmt ));
      $this->assertEquals( "C: 0h",
         TimeFormat::echo_time_remaining( // time is up: m=0, B>0, P>0, b=p=0
            0, BYOTYPE_CANADIAN, 0, 0,  90, 4, $fmt ));
      $this->assertEquals( "C: 10d 7h (-)",
         TimeFormat::echo_time_remaining( // abs-time: m>0, B=0
            157, BYOTYPE_CANADIAN, 0, -1,  0, 4, $fmt ));
      $this->assertEquals( "C: 10d 7h (+ 6d / 4)",
         TimeFormat::echo_time_remaining( // with byoyomi: byo-yomi not started yet
            157, BYOTYPE_CANADIAN, 0, -1,  90, 4, $fmt ));
      $this->assertEquals( "C: 1d 3h / 2 (6d / 4)",
         TimeFormat::echo_time_remaining( // in byoyomi
            0, BYOTYPE_CANADIAN, 18, 2,  90, 4, $fmt ));

      // invalid time-values (b>0, p>0 while m>0) -> byo-time should be ignored
      $this->assertEquals( "C: 1d (+ 6d / 4)",
         TimeFormat::echo_time_remaining( // with byoyomi: byo-yomi started, has main-time
            15, BYOTYPE_CANADIAN, 30, 3,  90, 4, $fmt ));
      $this->assertEquals( "C: 1d 3h / 2",
         TimeFormat::echo_time_remaining( // in byoyomi, no-extra
            0, BYOTYPE_CANADIAN, 18, 2,  90, 4, $fmt | TIMEFMT_NO_EXTRA ));
   }

   /** Tests echo_time_remaining() for Fischer time. */
   public function test_echo_time_remaining_fischer() {
      // fmtflags: TIMEFMT_ENGL, TIMEFMT_HTMLSPC, TIMEFMT_ZERO, TIMEFMT_ADDTYPE, TIMEFMT_NO_EXTRA
      $fmt = TIMEFMT_ENGL | TIMEFMT_ADDTYPE;

      // FIS: M + B
      $this->assertEquals( "F: 0h",
         TimeFormat::echo_time_remaining( // time is up: m=0, B=0, P>0
            0, BYOTYPE_FISCHER, 0, -1,  0, 7, $fmt ));
      $this->assertEquals( "F: 0h",
         TimeFormat::echo_time_remaining( // time is up: m=0, B=0, P=0
            0, BYOTYPE_FISCHER, 0, -1,  0, 0, $fmt ));
      $this->assertEquals( "F: 0h",
         TimeFormat::echo_time_remaining( // time is up: m=0, B>0, P>0, b=0
            0, BYOTYPE_FISCHER, 0, -1,  30, 7, $fmt ));
      $this->assertEquals( "F: 0h",
         TimeFormat::echo_time_remaining( // time is up: m=0, B>0, P>0, b=p=0
            0, BYOTYPE_FISCHER, 0, 0,  30, 7, $fmt ));
      $this->assertEquals( "F: 10d 7h (-)",
         TimeFormat::echo_time_remaining( // abs-time: m>0, B=0
            157, BYOTYPE_FISCHER, 0, -1,  0, 0, $fmt ));
      $this->assertEquals( "F: 10d 7h (+ 2d)",
         TimeFormat::echo_time_remaining( // with byoyomi: byo-yomi not started yet
            157, BYOTYPE_FISCHER, 0, -1,  30, 0, $fmt ));

      // invalid time-values (b>0, p>=0 while m>0) -> byo-time/period should be ignored
      $this->assertEquals( "F: 8h (+ 3d 7h)",
         TimeFormat::echo_time_remaining( // with byoyomi: byo-yomi not started yet
            8, BYOTYPE_FISCHER, 18, 2,  52, 0, $fmt )); // byo-time is ignored anyway
      $this->assertEquals( "F: 8h",
         TimeFormat::echo_time_remaining( // no-extra
            8, BYOTYPE_FISCHER, 18, 2,  52, 0, $fmt | TIMEFMT_NO_EXTRA ));
   }

}

// Call TimeFormatTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "TimeFormatTest::main") {
    TimeFormatTest::main();
}
?>
