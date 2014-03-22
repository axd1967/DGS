<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

// Call TournamentPointsTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
   define("PHPUnit_MAIN_METHOD", "TournamentPointsTest::main");
}

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once 'tournaments/include/tournament_points.php';

/**
 * Test class for helpers to handle tournament-points.
 * Generated by PHPUnit_Util_Skeleton on 2009-12-06 at 10:55:49.
 */
class TournamentPointsTest extends PHPUnit_Framework_TestCase {

   /**
    * Runs the test methods of this class.
    *
    * @access public
    * @static
    */
   public static function main() {
      require_once 'PHPUnit/TextUI/TestRunner.php';

      $suite  = new PHPUnit_Framework_TestSuite("TournamentPointsTest");
      $result = PHPUnit_TextUI_TestRunner::run($suite);
   }

   /** Tests getPointsLimit(point_type). */
   public function test_getPointsLimit()
   {
      $tp = new TournamentPoints();
      $this->assertEquals(  100, $tp->getPointsLimit(TPOINTSTYPE_SIMPLE) );
      $this->assertEquals( 1000, $tp->getPointsLimit(TPOINTSTYPE_HAHN) );
   }

   /** Tests calculate_points(game_score). */
   public function test_calculate_points_SIMPLE() {
      $tp = new TournamentPoints();
      $tp->setDefaults( TPOINTSTYPE_SIMPLE );
      $this->assertEquals( TPOINTSTYPE_SIMPLE, $tp->PointsType );

      $this->assertEquals( 2, $tp->calculate_points( -SCORE_RESIGN ) );
      $this->assertEquals( 2, $tp->calculate_points( -SCORE_TIME ) );
      $this->assertEquals( 2, $tp->calculate_points( -SCORE_FORFEIT ) );
      $this->assertEquals( 2, $tp->calculate_points( -150 ) );
      $this->assertEquals( 2, $tp->calculate_points( -27 ) );
      $this->assertEquals( 2, $tp->calculate_points( -30.5 ) );
      $this->assertEquals( 2, $tp->calculate_points( -2.5 ) );
      $this->assertEquals( 2, $tp->calculate_points( -0.5 ) );

      $this->assertEquals( 1, $tp->calculate_points( 0 ) );

      $this->assertEquals( 0, $tp->calculate_points( +SCORE_RESIGN ) );
      $this->assertEquals( 0, $tp->calculate_points( +SCORE_TIME ) );
      $this->assertEquals( 0, $tp->calculate_points( +SCORE_FORFEIT ) );
      $this->assertEquals( 0, $tp->calculate_points( +150 ) );
      $this->assertEquals( 0, $tp->calculate_points( +27 ) );
      $this->assertEquals( 0, $tp->calculate_points( +30.5 ) );
      $this->assertEquals( 0, $tp->calculate_points( +2.5 ) );
      $this->assertEquals( 0, $tp->calculate_points( +0.5 ) );

      $this->assertEquals( 0, $tp->calculate_points( 0, TG_FLAG_GAME_DETACHED ) );
      $this->assertEquals( 1, $tp->calculate_points( 0, TG_FLAG_GAME_NO_RESULT ) );
   }


   public function test_calculate_points_HAHN_shared() {
      $tp = new TournamentPoints();
      $tp->setDefaults( TPOINTSTYPE_HAHN );
      $this->assertEquals( TPOINTSTYPE_HAHN, $tp->PointsType );
      $this->assertEquals( TPOINTS_FLAGS_SHARE_MAX_POINTS, ($tp->Flags & TPOINTS_FLAGS_SHARE_MAX_POINTS) );

      $this->assertEquals( 10, $tp->calculate_points( -SCORE_RESIGN ) );
      $this->assertEquals( 10, $tp->calculate_points( -SCORE_TIME ) );
      $this->assertEquals( 10, $tp->calculate_points( -SCORE_FORFEIT ) );
      $this->assertEquals( 10, $tp->calculate_points( -150 ) );
      $this->assertEquals(  9, $tp->calculate_points( -30.5 ) );
      $this->assertEquals(  8, $tp->calculate_points( -27 ) );
      $this->assertEquals(  6, $tp->calculate_points( -2.5 ) );
      $this->assertEquals(  6, $tp->calculate_points( -0.5 ) );

      $this->assertEquals(  5, $tp->calculate_points( 0 ) );

      $this->assertEquals(  0, $tp->calculate_points( +SCORE_RESIGN ) );
      $this->assertEquals(  0, $tp->calculate_points( +SCORE_TIME ) );
      $this->assertEquals(  0, $tp->calculate_points( +SCORE_FORFEIT ) );
      $this->assertEquals(  0, $tp->calculate_points( +150 ) );
      $this->assertEquals(  1, $tp->calculate_points( +30.5 ) );
      $this->assertEquals(  2, $tp->calculate_points( +27 ) );
      $this->assertEquals(  4, $tp->calculate_points( +2.5 ) );
      $this->assertEquals(  4, $tp->calculate_points( +0.5 ) );

      $this->assertEquals(  0, $tp->calculate_points( 0, TG_FLAG_GAME_DETACHED ) );
      $this->assertEquals(  0, $tp->calculate_points( 0, TG_FLAG_GAME_NO_RESULT ) );
      $tp->PointsNoResult = 10;
      $this->assertEquals( 10, $tp->calculate_points( 0, TG_FLAG_GAME_NO_RESULT ) );
   }

   public function test_calculate_points_HAHN_not_shared() {
      $tp = new TournamentPoints();
      $tp->setDefaults( TPOINTSTYPE_HAHN );
      $this->assertEquals( TPOINTSTYPE_HAHN, $tp->PointsType );
      $tp->Flags = 0;

      $this->assertEquals( 10, $tp->calculate_points( -SCORE_RESIGN ) );
      $this->assertEquals( 10, $tp->calculate_points( -SCORE_TIME ) );
      $this->assertEquals( 10, $tp->calculate_points( -SCORE_FORFEIT ) );
      $this->assertEquals( 10, $tp->calculate_points( -150 ) );
      $this->assertEquals(  4, $tp->calculate_points( -30.5 ) );
      $this->assertEquals(  3, $tp->calculate_points( -27 ) );
      $this->assertEquals(  1, $tp->calculate_points( -2.5 ) );
      $this->assertEquals(  1, $tp->calculate_points( -0.5 ) );

      $this->assertEquals(  0, $tp->calculate_points( 0 ) );

      $this->assertEquals(  0, $tp->calculate_points( +SCORE_RESIGN ) );
      $this->assertEquals(  0, $tp->calculate_points( +SCORE_TIME ) );
      $this->assertEquals(  0, $tp->calculate_points( +SCORE_FORFEIT ) );
      $this->assertEquals(  0, $tp->calculate_points( +27 ) );
      $this->assertEquals(  0, $tp->calculate_points( +150 ) );
      $this->assertEquals(  0, $tp->calculate_points( +30.5 ) );
      $this->assertEquals(  0, $tp->calculate_points( +2.5 ) );
      $this->assertEquals(  0, $tp->calculate_points( +0.5 ) );

      $this->assertEquals(  0, $tp->calculate_points( 0, TG_FLAG_GAME_DETACHED ) );
      $this->assertEquals(  0, $tp->calculate_points( 0, TG_FLAG_GAME_NO_RESULT ) );
   }

   public function test_calculate_points_HAHN_negative_points() {
      $tp = new TournamentPoints();
      $tp->setDefaults( TPOINTSTYPE_HAHN );
      $this->assertEquals( TPOINTSTYPE_HAHN, $tp->PointsType );
      $tp->Flags = TPOINTS_FLAGS_NEGATIVE_POINTS;

      $this->assertEquals( 10, $tp->calculate_points( -SCORE_RESIGN ) );
      $this->assertEquals( 10, $tp->calculate_points( -SCORE_TIME ) );
      $this->assertEquals( 10, $tp->calculate_points( -SCORE_FORFEIT ) );
      $this->assertEquals( 10, $tp->calculate_points( -150 ) );
      $this->assertEquals(  4, $tp->calculate_points( -30.5 ) );
      $this->assertEquals(  3, $tp->calculate_points( -27 ) );
      $this->assertEquals(  1, $tp->calculate_points( -2.5 ) );
      $this->assertEquals(  1, $tp->calculate_points( -0.5 ) );

      $this->assertEquals(  0, $tp->calculate_points( 0 ) );

      $this->assertEquals( -10, $tp->calculate_points( +SCORE_RESIGN ) );
      $this->assertEquals( -10, $tp->calculate_points( +SCORE_TIME ) );
      $this->assertEquals( -10, $tp->calculate_points( +SCORE_FORFEIT ) );
      $this->assertEquals( -10, $tp->calculate_points( +150 ) );
      $this->assertEquals(  -4, $tp->calculate_points( +30.5 ) );
      $this->assertEquals(  -3, $tp->calculate_points( +27 ) );
      $this->assertEquals(  -1, $tp->calculate_points( +2.5 ) );
      $this->assertEquals(  -1, $tp->calculate_points( +0.5 ) );

      $this->assertEquals(  0, $tp->calculate_points( 0, TG_FLAG_GAME_DETACHED ) );
      $this->assertEquals(  0, $tp->calculate_points( 0, TG_FLAG_GAME_NO_RESULT ) );
   }

}

// Call TournamentPointsTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "TournamentPointsTest::main") {
   TournamentPointsTest::main();
}
?>
