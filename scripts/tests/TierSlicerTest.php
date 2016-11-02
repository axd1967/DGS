<?php
/*
Dragon Go Server
Copyright (C) 2001-  Jens-Uwe Gaspar

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

// Call TierSlicerTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
   define("PHPUnit_MAIN_METHOD", "TierSlicerTest::main");
}

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once 'tournaments/include/tournament_pool_classes.php';


define('TST_POOL_COUNT', 4);
define('TST_POOL_SIZE', 5);
define('TST_CNT_TP',  17);

/**
 * Test class for distribute user into pools with different slice-modes.
 */
class TierSlicerTest extends PHPUnit_Framework_TestCase {

   // expected league upper tiers with 3 tiers & pool-size 4
   private static $EXPECT_T3_P4 = 'A1,A1,A1,A1,B1,B2,B1,B2,B1,B2,B1,B2,C1,C2,C3,C4,C1,C2,C3,C4,C1,C2,C3,C4,C1,C2,C3,C4';

   /**
    * Runs the test methods of this class.
    *
    * @access public
    * @static
    */
   public static function main() {
      require_once 'PHPUnit/TextUI/TestRunner.php';

      $suite  = new PHPUnit_Framework_TestSuite("TierSlicerTest");
      $result = PHPUnit_TextUI_TestRunner::run($suite);
   }

   public function test_tier_slicer_roundrobin_snake() {
      $tround = $this->_create_tround( TST_POOL_COUNT, TST_POOL_SIZE );
      $ts = new TierSlicer( TOURNEY_TYPE_ROUND_ROBIN, $tround, TROUND_SLICE_SNAKE, TST_CNT_TP );

      $chk = $this->_seed_pools( $ts, TST_CNT_TP );
      $this->assertEquals( 'A1,A2,A3,A4,A4,A3,A2,A1,A1,A2,A3,A4,A4,A3,A2,A1,A1', join(',', $chk));
      $this->assertEquals( array( 1, TST_POOL_COUNT, 0 ), $ts->get_slicer_counts());
   }

   public function test_tier_slicer_league_snake() {
      $tround = $this->_create_tround( 0, TST_POOL_SIZE, 2 );
      $ts = new TierSlicer( TOURNEY_TYPE_LEAGUE, $tround, TROUND_SLICE_SNAKE, TST_CNT_TP );

      $chk = $this->_seed_pools( $ts, TST_CNT_TP );
      $this->assertEquals( 'A1,A1,A1,A1,A1,B1,B2,B2,B1,B1,B2,B2,B1,B1,B2,C1,C1', join(',', $chk));
      $this->assertEquals( array( 3, 4, 0 ), $ts->get_slicer_counts());
   }

   public function test_tier_slicer_league_fillup_full_pyramid() {
      $cnt_tps = 30;
      $tround = $this->_create_tround( 0, 2 );
      $ts = new TierSlicer( TOURNEY_TYPE_LEAGUE, $tround, TROUND_SLICE_FILLUP_POOLS, $cnt_tps);

      $chk = $this->_seed_pools( $ts, $cnt_tps);
      $this->assertEquals( 'A1,A1,B1,B1,B2,B2,C1,C1,C2,C2,C3,C3,C4,C4,D1,D1,D2,D2,D3,D3,D4,D4,D5,D5,D6,D6,D7,D7,D8,D8', join(',', $chk));
      $this->assertEquals( array( 4, 15, 0 ), $ts->get_slicer_counts());
   }

   public function test_tier_slicer_league_bottom_tier_corner_case_less_than_minpoolsize() {
      $cnt_tps = 31;
      $tround = $this->_create_tround( 0, 4, 4 );
      $ts = new TierSlicer( TOURNEY_TYPE_LEAGUE, $tround, TROUND_SLICE_ROUND_ROBIN, $cnt_tps );

      $chk = $this->_seed_pools( $ts, $cnt_tps );
      $this->assertEquals( self::$EXPECT_T3_P4.',0,0,0', join(',', $chk));
      $this->assertEquals( array( 3, 8, 1 ), $ts->get_slicer_counts());
   }

   public function test_tier_slicer_league_bottom_tier_more_than_half() {
      $cnt_tps = 28 + 20;
      $tround = $this->_create_tround( 0, 4, 2 );
      $ts = new TierSlicer( TOURNEY_TYPE_LEAGUE, $tround, TROUND_SLICE_ROUND_ROBIN, $cnt_tps );

      $chk = $this->_seed_pools( $ts, $cnt_tps );
      $this->assertEquals( self::$EXPECT_T3_P4.',D1,D2,D3,D4,D5,D6,D7,D8,D1,D2,D3,D4,D5,D6,D7,D8,D1,D2,D3,D4', join(',', $chk));
      $this->assertEquals( array( 4, 15, 0 ), $ts->get_slicer_counts());
   }

   public function test_tier_slicer_league_bottom_tier_less_than_half() {
      $cnt_tps = 28 + 9;
      $tround = $this->_create_tround( 0, 4, 2 );
      $ts = new TierSlicer( TOURNEY_TYPE_LEAGUE, $tround, TROUND_SLICE_ROUND_ROBIN, $cnt_tps );

      $chk = $this->_seed_pools( $ts, $cnt_tps );
      $this->assertEquals( self::$EXPECT_T3_P4.',D1,D2,D3,D4,D1,D2,D3,D4,D1', join(',', $chk));
      $this->assertEquals( array( 4, 11, 0 ), $ts->get_slicer_counts());
   }

   public function test_tier_slicer_league_bottom_tier_less_than_half_big_pool() {
      $cnt_tps = 10 + 7;
      $tround = $this->_create_tround( 0, 10, 5 );
      $ts = new TierSlicer( TOURNEY_TYPE_LEAGUE, $tround, TROUND_SLICE_ROUND_ROBIN, $cnt_tps );

      $chk = $this->_seed_pools( $ts, $cnt_tps );
      $this->assertEquals( 'A1,A1,A1,A1,A1,A1,A1,A1,A1,A1,B1,B1,B1,B1,B1,B1,B1', join(',', $chk));
      $this->assertEquals( array( 2, 2, 0 ), $ts->get_slicer_counts());
   }


   private function _create_tround( $pool_count, $pool_size, $min_pool_size=2 )
   {
      $tround = new TournamentRound();
      $tround->Pools = $pool_count;
      $tround->PoolSize = $pool_size;
      $tround->MinPoolSize = $min_pool_size;
      return $tround;
   }

   private function _seed_pools( &$ts, $cnt )
   {
      $pn = new PoolNameFormatter('%t(uc)%p(num)');
      $result = array();
      for ($i=0; $i < $cnt; $i++)
      {
         list( $tier, $pool ) = $ts->next_tier_pool();
         $result[] = $pn->format( $tier, $pool );
      }
      return $result;
   }

}

// Call TierSlicerTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "TierSlicerTest::main") {
   TierSlicerTest::main();
}
?>
