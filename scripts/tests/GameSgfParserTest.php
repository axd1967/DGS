<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Jens-Uwe Gaspar

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

// Call GameSgfParserTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
   define("PHPUnit_MAIN_METHOD", "GameSgfParserTest::main");
}

require_once "PHPUnit/Framework/TestCase.php";
require_once "PHPUnit/Framework/TestSuite.php";

require_once 'include/sgf_parser.php';



/**
 * Test class for GameSgfParser.
 * Generated by PHPUnit_Util_Skeleton on 2014-05-22 at 16:19:46.
 */
class GameSgfParserTest extends PHPUnit_Framework_TestCase {

   /**
    * Runs the test methods of this class.
    *
    * @access public
    * @static
    */
   public static function main() {
      require_once "PHPUnit/TextUI/TestRunner.php";

      $suite  = new PHPUnit_Framework_TestSuite("GameSgfParserTest");
      $result = PHPUnit_TextUI_TestRunner::run($suite);
   }

   /** Tests GameSgfParser.verify_game_attributes(). */
   public function test_verify_game_attributes() {
      $sgf = '(;GM[1]SZ[9]HA[2]KM[7.5];B[aa];W[bb])';
      $gp = GameSgfParser::parse_sgf_game( $sgf );
      $this->assertEquals( '', $gp->error_msg );
      $this->assertEquals( 0, count( $gp->verify_game_attributes( 9, 2, 7.5) ) );
      $this->assertEquals( 3, count( $gp->verify_game_attributes( 13, 7, 10 ) ) );

      $sgf = '(;GM[1];W[bb])';
      $gp = GameSgfParser::parse_sgf_game( $sgf );
      $this->assertEquals( '', $gp->error_msg );
      $this->assertEquals( 0, count( $gp->verify_game_attributes( 19, 0, 0) ) );
      $this->assertTrue( strpos( implode('', $gp->verify_game_attributes( 19, 1, 0) ), 'Handicap' ) !== false );
   }

   /** Tests GameSgfParser.verify_game_shape_setup(). */
   public function test_verify_game_shape_setup() {
      $sgf = '(;GM[1]AB[dd][hh]AW[cc][ii];B[aa];W[kk])';
      $arr_setup = array( 'dd' => BLACK, 'hh' => BLACK, 'cc' => WHITE, 'ii' => WHITE, );
      $arr_setup2 = array( 'dd' => BLACK, 'hh' => BLACK, 'cc' => WHITE, );
      $arr_setup3 = array( 'dd' => BLACK, 'hh' => BLACK, 'cc' => WHITE, 'ii' => WHITE, 'nn' => BLACK );

      $gp = GameSgfParser::parse_sgf_game( $sgf );
      $this->assertEquals( '', $gp->error_msg );
      $this->assertEquals( 0, count( $gp->verify_game_shape_setup( $arr_setup, 19 ) ) );
      $this->assertEquals( 'Shape-Setup mismatch: found discrepancy at coord [j11]',
         implode('', $gp->verify_game_shape_setup( $arr_setup2, 19 ) ) );
      $this->assertEquals( 'Shape-Setup mismatch: missing setup stones in SGF [o6]',
         implode('', $gp->verify_game_shape_setup( $arr_setup3, 19 ) ) );
   }

   /** Tests GameSgfParser.verify_game_moves(). */
   public function test_verify_game_moves() {
      $sgf = '(;GM[1];B[];W[aa];B[bb];W[cc];B[dd];W[];B[];W[ee];B[ff])';
      $db_moves = array( 'B', 'Waa', 'Bbb', 'Wcc', 'Bdd', 'W', 'B', 'Wee', 'Bff' );
      $db_moves_no_passes = array( 'Waa', 'Bbb', 'Wcc', 'Bdd', 'Wee', 'Bff' );

      $gp = GameSgfParser::parse_sgf_game( $sgf );
      $this->assertEquals( '', $gp->error_msg );
      $this->assertEquals( 0, count( $gp->verify_game_moves( 9, $db_moves, false ) ) );
      $this->assertEquals( 0, count( $gp->verify_game_moves( 9, $db_moves_no_passes, true ) ) );

      $sgf = '(;GM[1];B[];W[aa];B[bb];W[cc];B[nn];W[mm];B[oo];W[pp];B[qq])';
      $gp = GameSgfParser::parse_sgf_game( $sgf );
      $this->assertEquals( 0, count( $gp->verify_game_moves( 4, $db_moves, false ) ) );
      $this->assertEquals( 'Moves mismatch: found discrepancy at move #5',
         implode('', $gp->verify_game_moves( 5, $db_moves, false ) ) );
      $this->assertEquals( 'Moves mismatch: found discrepancy at move #4',
         implode('', $gp->verify_game_moves( 5, $db_moves_no_passes, true ) ) );
   }

   /** Tests GameSgfParser.parse_sgf_game(). */
   public function test_parse_sgf_game() {
      $sgf = '(;GM[1];B[aa];W[bb])';
      $gp = GameSgfParser::parse_sgf_game( $sgf, 1 );
      $this->assertEquals( '', $gp->get_error() );
      $this->assertEquals( '(;B[aa];W[bb])', $gp->sgf_game_tree->to_string() );

      $gp = GameSgfParser::parse_sgf_game( $sgf, 2 );
      $this->assertEquals( '(;W[bb])', $gp->sgf_game_tree->to_string() );

      $sgf = '(;GM[1];B[aa];';
      $gp = GameSgfParser::parse_sgf_game( $sgf );
      $this->assertTrue( (bool)$gp->get_error() );
   }

}

// Call GameSgfParserTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "GameSgfParserTest::main") {
   GameSgfParserTest::main();
}
?>
