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

// Call GameAddTimeTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
   define("PHPUnit_MAIN_METHOD", "GameAddTimeTest::main");
}

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once 'include/game_functions.php';

/**
 * Test class for GameAddTime.
 * Generated by PHPUnit_Util_Skeleton on 2009-12-06 at 10:55:34.
 */
class GameAddTimeTest extends PHPUnit_Framework_TestCase {

   private $grow;

   /**
    * Runs the test methods of this class.
    *
    * @access public
    * @static
    */
   public static function main() {
      require_once 'PHPUnit/TextUI/TestRunner.php';

      $suite  = new PHPUnit_Framework_TestSuite("GameAddTimeTest");
      $result = PHPUnit_TextUI_TestRunner::run($suite);
   }

   /** Sets up the fixture. */
   protected function setUp() {
      $this->grow = array( // Games-row
         'ID'                 => 4711,
         'tid'                => 0,
         'Status'             => GAME_STATUS_PLAY,
         // T=JAP, M=210, B=15, P=10; B(111): m=210,b=0,p=-1, W(222): m=210,b=0,p=-1
         'Byotype'            => BYOTYPE_JAPANESE,
         'Maintime'           => 14 * 15, // 14d
         'Byotime'            => 1 * 15, // 1d
         'Byoperiods'         => 10,
         'Black_ID'           => 111,
         'Black_Maintime'     => 14 * 15,
         'Black_Byotime'      => 0,
         'Black_Byoperiods'   => -1,
         'White_ID'           => 222,
         'White_Maintime'     => 14 * 15,
         'White_Byotime'      => 0,
         'White_Byoperiods'   => -1,
      );
   }


   /** Tests add_time(). */
   public function test_add_time() {
      // add_hours = add_time( $add_hours, $reset_byo=false )

      // T=JAP, M=210, B=15, P=10; B(111): m=210,b=0,p=-1, W(222): m=210,b=0,p=-1
      $gat = $this->createGAT( BYOTYPE_JAPANESE, 111 );
      $arr_invalid_values = array( 'abc', -7, time_convert_to_hours( MAX_ADD_DAYS, 'days') + 1 );
      foreach ( $arr_invalid_values as $chk )
         $this->assertEquals( "Invalid value for add_hours [$chk]", $gat->add_time( $chk ));

      // mess around with data
      $gat->uid = 333; // other user
      $this->assertTrue( stripos( $gat->add_time( $chk ), 'conditions' ) === FALSE );


      // JAP: no add, no reset
      $gat = $this->createGAT( BYOTYPE_JAPANESE, 111 );
      $this->assertEquals( 0, $gat->add_time( 0, false ));
      $this->checkTimes( "J.R1a", $gat, WHITE, 210, 0, -1 );
      $this->checkTimes( "J.R1b", $gat, BLACK, 210, 0, -1 );
      $this->assertFalse( (bool)$gat->game_query );

      // JAP: no add, with reset
      $gat = $this->createGAT( BYOTYPE_JAPANESE, 111 );
      $this->seedTimes( $gat, WHITE, 210, 7, 3 );
      $this->seedTimes( $gat, BLACK, 210, 4, 2 );
      $this->checkTimes( "J.R2a", $gat, WHITE, 210, 7, 3 );
      $this->assertEquals( -1, $gat->add_time( 0, true ));
      $this->checkTimes( "J.R2b", $gat, WHITE, 210, 0, -1 );
      $this->checkTimes( "J.R2c", $gat, BLACK, 210, 4, 2 ); // untouched
      $this->assertTrue( (bool)$gat->game_query );

      // JAP: no add, no reset (byo-yomi started)
      $gat = $this->createGAT( BYOTYPE_JAPANESE, 111 );
      $this->seedTimes( $gat, WHITE, 0, 7, 3 );
      $this->seedTimes( $gat, BLACK, 0, 4, 2 );
      $this->checkTimes( "J.R3a", $gat, WHITE, 0, 7, 3 );
      $this->assertEquals( 0, $gat->add_time( 0, false ));
      $this->checkTimes( "J.R3b", $gat, WHITE, 0, 7, 3 ); // untouched
      $this->checkTimes( "J.R3c", $gat, BLACK, 0, 4, 2 ); // untouched
      $this->assertFalse( (bool)$gat->game_query );

      // JAP: add, no reset
      $gat = $this->createGAT( BYOTYPE_JAPANESE, 222 );
      $this->seedTimes( $gat, BLACK, 210, 7, 3 );
      $this->seedTimes( $gat, WHITE, 210, 4, 2 );
      $this->checkTimes( "J.R4a", $gat, BLACK, 210, 7, 3 );
      $this->assertEquals( 45, $gat->add_time( 45, false ));
      $this->checkTimes( "J.R4b", $gat, BLACK, 210 + 45, 15, 3 );
      $this->checkTimes( "J.R4c", $gat, WHITE, 210, 4, 2 ); // untouched
      $this->assertTrue( (bool)$gat->game_query );

      // JAP: add, with reset
      $gat = $this->createGAT( BYOTYPE_JAPANESE, 111 );
      $this->seedTimes( $gat, WHITE, 210, 7, 3 );
      $this->seedTimes( $gat, BLACK, 210, 4, 2 );
      $this->checkTimes( "J.R5a", $gat, WHITE, 210, 7, 3 );
      $this->assertEquals( 45, $gat->add_time( 45, true ));
      $this->checkTimes( "J.R5b", $gat, WHITE, 210 + 45, 0, -1 );
      $this->checkTimes( "J.R5c", $gat, BLACK, 210, 4, 2 ); // untouched
      $this->assertTrue( (bool)$gat->game_query );

      // JAP: add, with reset (absolute time #1)
      $gat = $this->createGAT( BYOTYPE_JAPANESE, 111 );
      $gat->game_row['Byotime'] = 0;
      $this->seedTimes( $gat, WHITE, 210, 7, 3 );
      $this->seedTimes( $gat, BLACK, 210, 4, 2 );
      $this->assertEquals( 45, $gat->add_time( 45, true ));
      $this->checkTimes( "J.R6a", $gat, WHITE, 210 + 45, 7, 3 );
      $this->checkTimes( "J.R6b", $gat, BLACK, 210, 4, 2 ); // untouched
      $this->assertTrue( (bool)$gat->game_query );

      // JAP: no add, with reset (absolute time #2)
      $gat = $this->createGAT( BYOTYPE_JAPANESE, 111 );
      $gat->game_row['Byoperiods'] = -1;
      $this->seedTimes( $gat, WHITE, 210, 7, 3 );
      $this->seedTimes( $gat, BLACK, 210, 4, 2 );
      $this->assertEquals( 0, $gat->add_time( 0, true ));
      $this->checkTimes( "J.R7a", $gat, WHITE, 210, 7, 3 ); // untouched
      $this->checkTimes( "J.R7b", $gat, BLACK, 210, 4, 2 ); // untouched
      $this->assertFalse( (bool)$gat->game_query );

      // JAP: no add, with reset (byo-yomi started)
      $gat = $this->createGAT( BYOTYPE_JAPANESE, 111 );
      $this->seedTimes( $gat, WHITE, 0, 7, 3 );
      $this->seedTimes( $gat, BLACK, 0, 4, 2 );
      $this->assertEquals( -1, $gat->add_time( 0, true ));
      $this->checkTimes( "J.R8a", $gat, WHITE, 0, 15, 10 );
      $this->checkTimes( "J.R8b", $gat, BLACK, 0, 4, 2 ); // untouched
      $this->assertTrue( (bool)$gat->game_query );


      // CAN: no add, no reset
      $gat = $this->createGAT( BYOTYPE_CANADIAN, 111 );
      $this->assertEquals( 0, $gat->add_time( 0, false ));
      $this->checkTimes( "C.R1a", $gat, WHITE, 210, 0, -1 );
      $this->checkTimes( "C.R1b", $gat, BLACK, 210, 0, -1 );
      $this->assertFalse( (bool)$gat->game_query );

      // CAN: no add, with reset
      $gat = $this->createGAT( BYOTYPE_CANADIAN, 111 );
      $this->seedTimes( $gat, WHITE, 210, 7, 3 );
      $this->seedTimes( $gat, BLACK, 210, 4, 2 );
      $this->checkTimes( "C.R2a", $gat, WHITE, 210, 7, 3 );
      $this->assertEquals( -1, $gat->add_time( 0, true ));
      $this->checkTimes( "C.R2b", $gat, WHITE, 210, 0, -1 );
      $this->checkTimes( "C.R2c", $gat, BLACK, 210, 4, 2 ); // untouched
      $this->assertTrue( (bool)$gat->game_query );

      // CAN: no add, no reset (byo-yomi started)
      $gat = $this->createGAT( BYOTYPE_CANADIAN, 111 );
      $this->seedTimes( $gat, WHITE, 0, 7, 3 );
      $this->seedTimes( $gat, BLACK, 0, 4, 2 );
      $this->checkTimes( "C.R3a", $gat, WHITE, 0, 7, 3 );
      $this->assertEquals( 0, $gat->add_time( 0, false ));
      $this->checkTimes( "C.R3b", $gat, WHITE, 0, 7, 3 ); // untouched
      $this->checkTimes( "C.R3c", $gat, BLACK, 0, 4, 2 ); // untouched
      $this->assertFalse( (bool)$gat->game_query );

      // CAN: add, no reset
      $gat = $this->createGAT( BYOTYPE_CANADIAN, 222 );
      $this->seedTimes( $gat, BLACK, 210, 7, 3 );
      $this->seedTimes( $gat, WHITE, 210, 4, 2 );
      $this->checkTimes( "C.R4a", $gat, BLACK, 210, 7, 3 );
      $this->assertEquals( 45, $gat->add_time( 45, false ));
      $this->checkTimes( "C.R4b", $gat, BLACK, 210 + 45, 0, -1 );
      $this->checkTimes( "C.R4c", $gat, WHITE, 210, 4, 2 ); // untouched
      $this->assertTrue( (bool)$gat->game_query );

      // CAN: add, with reset
      $gat = $this->createGAT( BYOTYPE_CANADIAN, 111 );
      $this->seedTimes( $gat, WHITE, 210, 7, 3 );
      $this->seedTimes( $gat, BLACK, 210, 4, 2 );
      $this->checkTimes( "C.R5a", $gat, WHITE, 210, 7, 3 );
      $this->assertEquals( 45, $gat->add_time( 45, true ));
      $this->checkTimes( "C.R5b", $gat, WHITE, 210 + 45, 0, -1 );
      $this->checkTimes( "C.R5c", $gat, BLACK, 210, 4, 2 ); // untouched
      $this->assertTrue( (bool)$gat->game_query );


      // FIS: no add, no reset
      $gat = $this->createGAT( BYOTYPE_FISCHER, 111 );
      $this->seedTimes( $gat, WHITE, 210, 0, -1 );
      $this->seedTimes( $gat, BLACK, 210, 0, -1 );
      $this->assertEquals( 0, $gat->add_time( 0, false ));
      $this->checkTimes( "F.R1a", $gat, WHITE, 210, 0, -1 ); // untouched
      $this->checkTimes( "F.R1b", $gat, BLACK, 210, 0, -1 ); // untouched
      $this->assertFalse( (bool)$gat->game_query );

      // FIS: no add, with reset
      $gat = $this->createGAT( BYOTYPE_FISCHER, 111 );
      $this->seedTimes( $gat, WHITE, 210, 0, -1 );
      $this->seedTimes( $gat, BLACK, 210, 0, -1 );
      $this->assertEquals( 0, $gat->add_time( 0, true ));
      $this->checkTimes( "F.R2a", $gat, WHITE, 210, 0, -1 ); // untouched
      $this->checkTimes( "F.R2b", $gat, BLACK, 210, 0, -1 ); // untouched
      $this->assertFalse( (bool)$gat->game_query );

      // FIS: add, no reset
      $gat = $this->createGAT( BYOTYPE_FISCHER, 222 );
      $this->seedTimes( $gat, WHITE, 210, 0, -1 );
      $this->seedTimes( $gat, BLACK, 210, 0, -1 );
      $this->assertEquals( 45, $gat->add_time( 45, false ));
      $this->checkTimes( "F.R3a", $gat, BLACK, 255, 0, -1 );
      $this->checkTimes( "F.R3b", $gat, WHITE, 210, 0, -1 ); // untouched
      $this->assertTrue( (bool)$gat->game_query );

      // FIS: add, with reset
      $gat = $this->createGAT( BYOTYPE_FISCHER, 111 );
      $this->seedTimes( $gat, WHITE, 210, 0, -1 );
      $this->seedTimes( $gat, BLACK, 210, 0, -1 );
      $this->assertEquals( 45, $gat->add_time( 45, true ));
      $this->checkTimes( "F.R4a", $gat, WHITE, 255, 0, -1 );
      $this->checkTimes( "F.R4b", $gat, BLACK, 210, 0, -1 ); // untouched
      $this->assertTrue( (bool)$gat->game_query );
   }

   /** Tests allow_add_time_opponent(). */
   public function test_allow_add_time_opponent() {
      // allowed = allow_add_time_opponent( $game_row, $uid )

      $this->assertTrue( GameAddTime::allow_add_time_opponent( $this->grow, 111 ));
      $this->assertTrue( GameAddTime::allow_add_time_opponent( $this->grow, 222 ));

      $gamerow = array() + $this->grow; // clone array to mess data
      $gamerow['Status'] = GAME_STATUS_FINISHED;
      $this->assertFalse( GameAddTime::allow_add_time_opponent( $gamerow, 111 ));
      $gamerow['Status'] = GAME_STATUS_KOMI;
      $this->assertFalse( GameAddTime::allow_add_time_opponent( $gamerow, 111 ));
      $gamerow['Status'] = GAME_STATUS_PLAY;

      $this->assertFalse( GameAddTime::allow_add_time_opponent( $gamerow, 3333 ));

      $gamerow['Black_Maintime'] = time_convert_to_hours( 360 + MAX_ADD_DAYS, 'days') + 1;
      $gamerow['White_Maintime'] = time_convert_to_hours( 360 + MAX_ADD_DAYS, 'days') + 1;
      $this->assertFalse( GameAddTime::allow_add_time_opponent( $gamerow, 111 ));
      $this->assertFalse( GameAddTime::allow_add_time_opponent( $gamerow, 222 ));

      $gamerow = array() + $this->grow; // clone array to mess data
      $gamerow['tid'] = 1; // forbidden for tournament
      $this->assertFalse( GameAddTime::allow_add_time_opponent( $gamerow, 111 ));
   }

   private function createGAT( $byotype, $uid )
   {
      $grow = array() + $this->grow; // clone game-row
      $grow['Byotype'] = $byotype;
      $gat = new GameAddTime( $grow, $uid );
      $this->assertEquals( '', $gat->game_query );
      return $gat;
   }

   private function checkTimes( $label, $gat, $color, $maintime, $byotime, $byoper )
   {
      $msg = "$label: expecting M=$maintime, B=$byotime, P=$byoper";
      $pfx = ($color == BLACK) ? 'Black' : 'White';
      $gamerow = $gat->game_row;

      $this->assertEquals( $maintime, $gamerow["{$pfx}_Maintime"], "[maintime] $msg" );
      $this->assertEquals( $byotime, $gamerow["{$pfx}_Byotime"], "[byotime] $msg" );
      $this->assertEquals( $byoper, $gamerow["{$pfx}_Byoperiods"], "[byoperiods] $msg" );
   }

   private function seedTimes( &$gat, $color, $maintime, $byotime, $byoper )
   {
      $pfx = (is_null($color)) ? '' : ( ($color == BLACK) ? 'Black_' : 'White_' );
      //echo "seedTimes($color,$maintime,$byotime,$byoper): pfx=[$pfx]";
      $gat->game_row["{$pfx}Maintime"] = $maintime;
      $gat->game_row["{$pfx}Byotime"] = $byotime;
      $gat->game_row["{$pfx}Byoperiods"] = $byoper;
   }

}

// Call GameAddTimeTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "GameAddTimeTest::main") {
   GameAddTimeTest::main();
}
?>
