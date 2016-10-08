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

// Call PoolSlicerTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
   define("PHPUnit_MAIN_METHOD", "PoolSlicerTest::main");
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
class PoolSlicerTest extends PHPUnit_Framework_TestCase {

   /**
    * Runs the test methods of this class.
    *
    * @access public
    * @static
    */
   public static function main() {
      require_once 'PHPUnit/TextUI/TestRunner.php';

      $suite  = new PHPUnit_Framework_TestSuite("PoolSlicerTest");
      $result = PHPUnit_TextUI_TestRunner::run($suite);
   }

   public function test_pool_slicer_snake() {
      $pc = new PoolSlicer( TROUND_SLICE_SNAKE, TST_POOL_COUNT, TST_POOL_SIZE );

      $chk = $this->seed_pools( $pc );
      $this->assertEquals( '1,2,3,4,4,3,2,1,1,2,3,4,4,3,2,1,1', join(',', $chk));
      $this->assertEquals( TST_POOL_COUNT, $pc->count_visited_pools());
   }

   public function test_pool_slicer_round_robin() {
      $pc = new PoolSlicer( TROUND_SLICE_ROUND_ROBIN, TST_POOL_COUNT, TST_POOL_SIZE );

      $chk = $this->seed_pools( $pc );
      $this->assertEquals( '1,2,3,4,1,2,3,4,1,2,3,4,1,2,3,4,1', join(',', $chk));
      $this->assertEquals( TST_POOL_COUNT, $pc->count_visited_pools());
   }

   public function test_pool_fillup_pools() {
      $pc = new PoolSlicer( TROUND_SLICE_FILLUP_POOLS, TST_POOL_COUNT, TST_POOL_SIZE );

      $chk = $this->seed_pools( $pc );
      $this->assertEquals( '1,1,1,1,1,2,2,2,2,2,3,3,3,3,3,4,4', join(',', $chk));
      $this->assertEquals( TST_POOL_COUNT, $pc->count_visited_pools());
   }

   public function test_pool_slicer_manual() {
      $pc = new PoolSlicer( TROUND_SLICE_MANUAL, TST_POOL_COUNT, TST_POOL_SIZE );

      $chk = $this->seed_pools( $pc );
      $this->assertEquals( '0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0', join(',', $chk));
      $this->assertEquals( 1, $pc->count_visited_pools());
   }

   public function test_pool_count_visited_pools() {
      $pc = new PoolSlicer( TROUND_SLICE_FILLUP_POOLS, TST_POOL_COUNT, TST_POOL_SIZE );

      $chk = $this->seed_pools( $pc, TST_POOL_SIZE + 2 );
      $this->assertEquals( '1,1,1,1,1,2,2', join(',', $chk));
      $this->assertEquals( 2, $pc->count_visited_pools());
   }

   private function seed_pools( &$pc, $cnt=TST_CNT_TP )
   {
      $result = array();
      for ($i=0; $i < $cnt; $i++)
      {
         $result[] = $pc->next_pool();
         $pc->visit_pool();
      }
      return $result;
   }

}

// Call PoolSlicerTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "PoolSlicerTest::main") {
   PoolSlicerTest::main();
}
?>
