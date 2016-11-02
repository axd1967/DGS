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

// Call PoolParserTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
   define("PHPUnit_MAIN_METHOD", "PoolParserTest::main");
}

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once 'tournaments/include/tournament_pool_classes.php';


/**
 * Test class for parsing & checking tier/pool-combination for league- & round-robin-tournaments.
 */
class PoolParserTest extends PHPUnit_Framework_TestCase {

   /**
    * Runs the test methods of this class.
    *
    * @access public
    * @static
    */
   public static function main() {
      require_once 'PHPUnit/TextUI/TestRunner.php';

      $suite  = new PHPUnit_Framework_TestSuite("PoolParserTest");
      $result = PHPUnit_TextUI_TestRunner::run($suite);
   }

   public function test_valid_tier_pools() {
      $this->_assert_equals_valid_tier_pools( 1, 2, 'A1' );
      $this->_assert_equals_valid_tier_pools( 4, 2, 'A1,B1,B2,C1' );
      $this->_assert_equals_valid_tier_pools( 8, 2, 'A1,B1,B2,C1,C2,C3,C4,D1' );
      $this->_assert_equals_valid_tier_pools( 6, 3, 'A1,B1,B2,B3,C1,C2' );
      $this->_assert_equals_valid_tier_pools( 6, 4, 'A1,B1,B2,B3,B4,C1' );
   }

   public function test_parse_tier_pool_round_robin() {
      $t_type = TOURNEY_TYPE_ROUND_ROBIN;
      $p_parser = $this->_create_pool_parser( $t_type, 3, 2 ); // 1,2,3
      $this->_assert_equals_parse_tier_pool( $t_type, $p_parser, 'TRR:3', '1', '1' );
      $this->_assert_equals_parse_tier_pool( $t_type, $p_parser, 'TRR:3', '3', '3' );

      $this->_assert_equals_parse_tier_pool( $t_type, $p_parser, 'TRR:3', '4',
         '[TRR:3]: Pool [4] is invalid, must be in range [1..3]' );
   }

   public function test_parse_tier_pool_league_tierfactor2() {
      $t_type = TOURNEY_TYPE_LEAGUE;
      $p_parser = $this->_create_pool_parser( $t_type, 4, 2 ); // A1,B1,B2,C1
      $this->_assert_equals_parse_tier_pool( $t_type, $p_parser, 'TLG:4:2', 'A1', 'A1' );
      $this->_assert_equals_parse_tier_pool( $t_type, $p_parser, 'TLG:4:2', 'c1', 'C1' );
      $this->_assert_equals_parse_tier_pool( $t_type, $p_parser, 'TLG:4:2', '2.2', 'B2' );
      $this->_assert_equals_parse_tier_pool( $t_type, $p_parser, 'TLG:4:2', '2   1', 'B1' );
      $this->_assert_equals_parse_tier_pool( $t_type, $p_parser, 'TLG:4:2', '1/1', 'A1' );

      foreach ( array( '1.0', '0', '3.2', '4.1', 'b3', 'c2', 'a2' ) as $value )
         $this->_assert_equals_parse_tier_pool( $t_type, $p_parser, 'TLG:4:2', $value,
            "[TLG:4:2]: Pool [$value] is invalid" );
   }

   public function test_parse_tier_pool_league_tierfactor3() {
      $t_type = TOURNEY_TYPE_LEAGUE;
      $p_parser = $this->_create_pool_parser( $t_type, 5, 3 ); // A1,B1,B2,B3,C1
      $this->_assert_equals_parse_tier_pool( $t_type, $p_parser, 'TLG:5:3', 'A1', 'A1' );
      $this->_assert_equals_parse_tier_pool( $t_type, $p_parser, 'TLG:5:3', 'c1', 'C1' );
      $this->_assert_equals_parse_tier_pool( $t_type, $p_parser, 'TLG:5:3', '2.3', 'B3' );
      $this->_assert_equals_parse_tier_pool( $t_type, $p_parser, 'TLG:5:3', '1/1', 'A1' );

      foreach ( array( '1.0', '0', '3.2', '4.1', 'b4', 'c2', 'a2' ) as $value )
         $this->_assert_equals_parse_tier_pool( $t_type, $p_parser, 'TLG:5:3', $value,
            "[TLG:5:3]: Pool [$value] is invalid" );
   }

   public function test_is_valid_tier_pool_league_tierfactor2() {
      $p_parser = $this->_create_pool_parser( TOURNEY_TYPE_LEAGUE, 4, 2 ); // A1,B1,B2,C1
      $this->assertTrue( $p_parser->is_valid_tier_pool( 1, 1 ) );
      $this->assertTrue( $p_parser->is_valid_tier_pool( 2, 2 ) );
      $this->assertTrue( $p_parser->is_valid_tier_pool( 3, 1 ) );
      $this->assertFalse( $p_parser->is_valid_tier_pool( 1, 0 ) );
      $this->assertFalse( $p_parser->is_valid_tier_pool( 1, 2 ) );
      $this->assertFalse( $p_parser->is_valid_tier_pool( 2, 3 ) );
      $this->assertFalse( $p_parser->is_valid_tier_pool( 3, 2 ) );
      $this->assertFalse( $p_parser->is_valid_tier_pool( 4, 1 ) );
   }

   public function test_is_valid_tier_pool_league_tierfactor3() {
      $p_parser = $this->_create_pool_parser( TOURNEY_TYPE_LEAGUE, 5, 3 ); // A1,B1,B2,B3,C1
      $this->assertTrue( $p_parser->is_valid_tier_pool( 1, 1 ) );
      $this->assertTrue( $p_parser->is_valid_tier_pool( 2, 3 ) );
      $this->assertTrue( $p_parser->is_valid_tier_pool( 3, 1 ) );
      $this->assertFalse( $p_parser->is_valid_tier_pool( 1, 0 ) );
      $this->assertFalse( $p_parser->is_valid_tier_pool( 1, 2 ) );
      $this->assertFalse( $p_parser->is_valid_tier_pool( 2, 4 ) );
      $this->assertFalse( $p_parser->is_valid_tier_pool( 3, 2 ) );
   }

   public function test_is_valid_tier_pool_round_robin() {
      $p_parser = $this->_create_pool_parser( TOURNEY_TYPE_ROUND_ROBIN, 3, 2 ); // 1,2,3
      $this->assertTrue( $p_parser->is_valid_tier_pool( 1, 1 ) );
      $this->assertTrue( $p_parser->is_valid_tier_pool( 1, 2 ) );
      $this->assertTrue( $p_parser->is_valid_tier_pool( 1, 3 ) );
      $this->assertFalse( $p_parser->is_valid_tier_pool( 1, 0 ) );
      $this->assertFalse( $p_parser->is_valid_tier_pool( 1, 4 ) );
      $this->assertFalse( $p_parser->is_valid_tier_pool( 2, 1 ) );
   }

   private function _create_pool_parser( $tourney_type, $pool_count, $tier_factor )
   {
      $tround = new TournamentRound();
      $tround->Pools = $pool_count;
      $tround->TierFactor = $tier_factor;
      return new PoolParser( $tourney_type, $tround );
   }

   private function _assert_equals_parse_tier_pool( $tourney_type, $p_parser, $ctx, $value, $expected_pool )
   {
      $tpk = $p_parser->parse_tier_pool( $ctx, $value );
      if ( $tpk > 0 )
         $this->assertEquals( $expected_pool, $this->_format_pool( $tourney_type, $tpk ) );
      else
         $this->assertEquals( $expected_pool, join('; ', $p_parser->errors) );
   }

   private function _assert_equals_valid_tier_pools( $pool_count, $tier_factor, $expected_pools )
   {
      $p_parser = $this->_create_pool_parser( TOURNEY_TYPE_LEAGUE, $pool_count, $tier_factor );

      $pools = $p_parser->get_valid_tier_pools();
      $arr = array();
      foreach ( $pools as $tier_pool_key => $tmp )
      {
         list( $tier, $pool ) = TournamentUtils::decode_tier_pool_key( $tier_pool_key );
         $arr[] = PoolViewer::format_tier_pool( TOURNEY_TYPE_LEAGUE, $tier, $pool, true );
      }
      $this->assertEquals( $expected_pools, join(',', $arr) );
   }

   private function _format_pool( $tourney_type, $tier_pool_key )
   {
      list( $tier, $pool ) = TournamentUtils::decode_tier_pool_key( $tier_pool_key );
      return PoolViewer::format_tier_pool( $tourney_type, $tier, $pool, true );
   }

}

// Call PoolParserTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "PoolParserTest::main") {
   PoolParserTest::main();
}
?>
