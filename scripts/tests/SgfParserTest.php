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

// Call SgfParserTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
   define("PHPUnit_MAIN_METHOD", "SgfParserTest::main");
}

require_once "PHPUnit/Framework/TestCase.php";
require_once "PHPUnit/Framework/TestSuite.php";

require_once 'include/sgf_parser.php';



/**
 * Test class for SgfParser.
 * Generated by PHPUnit_Util_Skeleton on 2014-05-22 at 13:12:05.
 */
class SgfParserTest extends PHPUnit_Framework_TestCase {

   /**
    * Runs the test methods of this class.
    *
    * @access public
    * @static
    */
   public static function main()
   {
      require_once "PHPUnit/TextUI/TestRunner.php";

      $suite  = new PHPUnit_Framework_TestSuite("SgfParserTest");
      $result = PHPUnit_TextUI_TestRunner::run($suite);
   }

   /** Tests sgf_parser(). */
   public function test_sgf_parser() {
      $sgf = '(;GM[1]FF[4]CA[UTF-8]RU[Chinese]SZ[19]KM[7.00]GN[game-name]PW[White]PB[Black]WR[1k]BR[1d]GC[comment]PC[DGS]XM[1];B[])';
      $parser = SgfParser::sgf_parser( $sgf );
      $this->assertEquals( '', $parser->error_msg );
      $this->assertEquals( 1, count($parser->games) );
      $this->assertEquals( $sgf, $parser->games[0]->to_string() );

      // fill defaults
      $sgf = '(;GM[1]CA[UTF-8]KM[7]PW[W]PB[B]GC[comment];B[dd])';
      $parser = SgfParser::sgf_parser( $sgf );
      $this->assertEquals( '', $parser->error_msg );
      $this->assertEquals( 1, count($parser->games) );
      $this->assertEquals( '(;GM[1]CA[UTF-8]KM[7]PW[W]PB[B]GC[comment]FF[4]SZ[19]RU[Japanese];B[dd])',
         $parser->games[0]->to_string() );

      // skip root-node; multi-args
      $sgf = '(;AB[aa][bb][cc]AW[ff][gg][hh]B[ee])';
      $parser = SgfParser::sgf_parser( $sgf, SGFP_OPT_SKIP_ROOT_NODE );
      $this->assertEquals( '', $parser->error_msg );
      $this->assertEquals( 1, count($parser->games) );
      $this->assertEquals( $sgf, $parser->games[0]->to_string() );

      // with variations; escape ']' with '\\'
      $sgf = '(;B[pd];W[qf];B[nc];W[rd]LB[qc:A][qh:B] (;B[qc]LB[ph:B][qi:A] (;W[qi]C[calm-\\]nice];B[cp];W[eq];B[do];W[hq]) (;W[ph];B[qj];W[pj]LB[pk:B][qk:A] (;B[qk];W[qi];B[qn];W[pk];B[ql]) (;B[pk];W[oj];B[ql];W[ok];B[ol];W[qi]))) (;B[qh]C[aggressive];W[qc];B[qe];W[re];B[pf];W[pg];B[qg];W[rf];B[og]))';
      $parser = SgfParser::sgf_parser( str_replace(' ', '', $sgf), SGFP_OPT_SKIP_ROOT_NODE );
      $this->assertEquals( '', $parser->error_msg );
      $this->assertEquals( 1, count($parser->games) );
      $this->assertEquals( $sgf, SgfParser::sgf_builder( array( $parser->games[0] ), ' ', '', '' ));

      // empty root-node/variation
      $sgf = '((;B[pd]))';
      $parser = SgfParser::sgf_parser( $sgf, SGFP_OPT_SKIP_ROOT_NODE );
      $this->assertEquals( '', $parser->error_msg );
      $this->assertEquals( 1, count($parser->games) );
      $this->assertEquals( '(;B[pd])', $parser->games[0]->to_string() );
   }//test_sgf_parser

   /** Tests errors for sgf_parser(). */
   public function test_errors_sgf_parser() {
      $sgf = '(    ';
      $parser = SgfParser::sgf_parser( $sgf );
      $this->assertEquals( 'Bad end of file', $parser->error_msg );

      $sgf = '(  MA[x])';
      $parser = SgfParser::sgf_parser( $sgf );
      $this->assertEquals( 'Bad root node', $parser->error_msg );

      $sgf = '(;GM[2])';
      $parser = SgfParser::sgf_parser( $sgf );
      $this->assertEquals( 'Not a Go game (GM[1])', $parser->error_msg );
      $sgf = '(;SZ[7])';
      $parser = SgfParser::sgf_parser( $sgf );
      $this->assertEquals( 'Not a Go game (GM[1])', $parser->error_msg );

      $sgf = '(;B[aa];W[bb](;B[cc];W[dd](;B[ee];W[ff]);MA[x]))';
      $parser = SgfParser::sgf_parser( $sgf, SGFP_OPT_SKIP_ROOT_NODE );
      $this->assertEquals( 'Bad node position outside variation', $parser->error_msg );

      $sgf = '(;B[aa];W[bb](;B[cc];W[dd])MA[x])';
      $parser = SgfParser::sgf_parser( $sgf, SGFP_OPT_SKIP_ROOT_NODE );
      $this->assertEquals( 'Game-Tree syntax error', $parser->error_msg );

      $sgf = '(;GM[1];B[aa];W[bb](;B[cc];W[dd])';
      $parser = SgfParser::sgf_parser( $sgf );
      $this->assertEquals( 'Missing right parenthesis', $parser->error_msg );

      $sgf = '(;B[aa];W[dd]MA[x )';
      $parser = SgfParser::sgf_parser( $sgf, SGFP_OPT_SKIP_ROOT_NODE );
      $this->assertEquals( 'Missing right bracket', $parser->error_msg );

      $sgf = '(;B[aa];W[dd]MA[x]W[hh])';
      $parser = SgfParser::sgf_parser( $sgf, SGFP_OPT_SKIP_ROOT_NODE );
      $this->assertEquals( 'Property not unique', $parser->error_msg );

      $sgf = '(;B[aa];W[dd] 123)';
      $parser = SgfParser::sgf_parser( $sgf, SGFP_OPT_SKIP_ROOT_NODE );
      $this->assertEquals( 'Node syntax error', $parser->error_msg );
      $sgf = '(;B[ii];W[dd]  ';
      $parser = SgfParser::sgf_parser( $sgf, SGFP_OPT_SKIP_ROOT_NODE );
      $this->assertEquals( 'Node syntax error', $parser->error_msg );
   }//test_errors_sgf_parser


   /** Tests push_var_stack(). */
   public function test_push_var_stack() {
      $vars = array();
      $tree = new SgfGameTree();
      $node = new SgfNode( 13 );
      $obj  = 'sample';

      $false = false;
      SgfParser::push_var_stack( $vars, $false );
      $this->assertEquals( 1, count($vars) );
      list( $data, $entry ) = array_pop($vars);
      $this->assertEquals( 0, $data );
      $this->assertEquals( false, $entry );

      SgfParser::push_var_stack( $vars, $tree, 47 );
      $this->assertEquals( 1, count($vars) );
      list( $data, $stack_tree ) = array_pop($vars);
      $this->assertEquals( 47, $data );
      $this->assertEquals( '()', $stack_tree->debug() );

      // preserve reference to payload-object (not really a test, more a check on PHP-behaviour)
      SgfParser::push_var_stack( $vars, $tree, array( $node, 11 ) );
      $this->assertEquals( 1, count($vars) );
      list( $data, $entry ) = array_pop($vars);
      list( $stack_node, $stack_val ) = $data;
      $this->assertEquals( $stack_node->to_string(), $node->to_string());
      $this->assertEquals( $stack_val, 11 );
      $stack_node->test = 42;
      $this->assertEquals( $stack_node->to_string(), $node->to_string());

      // preserve iterator on payload-object-array tree->nodes (not really a test, more a check on PHP-behaviour)
      for ( $i=0; $i < 5; $i++ )
         $tree->nodes[] = new SgfNode( $i + 1 );
      list( $k, $v ) = each($tree->nodes);
      list( $k, $v ) = each($tree->nodes);
      $this->assertEquals( 1, $k );
      $this->assertEquals( 2, $v->pos );
      SgfParser::push_var_stack( $vars, $obj, array( $tree ) );
      list( $data, $entry ) = array_pop($vars);
      list( $stack_tree ) = $data;
      list( $k, $v ) = each($stack_tree->nodes);
      $this->assertEquals( 2, $k );
      $this->assertEquals( 3, $v->pos );
      reset($tree->nodes);
      list( $k, $v ) = each($stack_tree->nodes);
      $this->assertEquals( 0, $k );
      $this->assertEquals( 1, $v->pos );
   }//test_push_var_stack

   /** Tests normalize_move_coords(). */
   public function test_normalize_move_coords() {
      // PASS-move
      $this->assertEquals( '', SgfParser::normalize_move_coords( '', 20) );
      $this->assertEquals( '', SgfParser::normalize_move_coords( 'tt', 19) );
      $this->assertEquals( 'tt', SgfParser::normalize_move_coords( 'tt', 20) );

      $this->assertEquals( 'as', SgfParser::normalize_move_coords( 'a1', 19) );
      $this->assertEquals( 'z25', SgfParser::normalize_move_coords( 'z25', 19) ); // keep invalid
      $this->assertEquals( 'pq', SgfParser::normalize_move_coords( 'pq', 19) );
   }

   /** Tests sgf_convert_move_to_board_coords(). */
   public function test_sgf_convert_move_to_board_coords() {
      $node = new SgfNode( 1 );
      $node->props['B'][] = 'tt';
      $node->props['W'][] = 'pq';
      $conv_node = SgfParser::sgf_convert_move_to_board_coords( $node, 19);
      $this->assertEquals( 'B[] W[q3]', $conv_node->get_props_text() );
      $node->props['C'][] = 'test';
      $this->assertEquals( $node->get_props_text(), $conv_node->get_props_text() ); // obj passed per ref
   }

   /** Tests get_handicap_pattern(); global function. */
   public function test_get_handicap_pattern() {
      // IMPORTANT NOTE: this test need the 'pattern/'-directory, so in the test-dir this may require a link to work!!

      $this->assertEquals( '', get_handicap_pattern( 19, 0, $err) );
      $this->assertEquals( '', get_handicap_pattern( 19, 1, $err) );
      $this->assertEquals( '', $err );

      $this->assertEquals( '', get_handicap_pattern( 26, 2, $err) ); // unknown size
      $this->assertTrue( (bool)$err );

      $this->assertEquals( 'dbbd', get_handicap_pattern( 5, 2, $err) );
      $this->assertEquals( 'pddpppddpjdjjpjdjj', get_handicap_pattern( 19, 9, $err) );
      $this->assertEquals( 'pddpppddpjdjjpjdjjmggmmmggqccqqqccqjcjjqjcpgdmmpgdmdgppmdgmjgjjmjgqgcmmqgcmcgqqmcg',
         get_handicap_pattern( 19, 41, $err) );

      // read variations
      $this->assertEquals( 'jddj', get_handicap_pattern( 13, 2, $err) );
      $this->assertEquals( 'jddjjj', get_handicap_pattern( 13, 3, $err) );
      $this->assertEquals( 'jddjjjdd', get_handicap_pattern( 13, 4, $err) );
      $this->assertEquals( 'jddjjjddgg', get_handicap_pattern( 13, 5, $err) );
      $this->assertEquals( 'jddjjjddjgdggg', get_handicap_pattern( 13, 7, $err) );
      $this->assertEquals( 'jddjjjddjgdggjgd', get_handicap_pattern( 13, 8, $err) );
   }//test_get_handicap_pattern

}

// Call SgfParserTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "SgfParserTest::main") {
   SgfParserTest::main();
}
?>