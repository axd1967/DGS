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

// Call GameCommentHelperTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
   define("PHPUnit_MAIN_METHOD", "GameCommentHelperTest::main");
}

require_once "PHPUnit/Framework/TestCase.php";
require_once "PHPUnit/Framework/TestSuite.php";

require_once 'include/game_comments.php';
require_once 'include/game_functions.php';

define('USER1', 11);
define('USER2', 22);
define('USER3', 33);
define('USER4', 44);
define('USER5', 4711); // observer


/**
 * Test class for GameCommentHelper.
 */
class GameCommentHelperTest extends PHPUnit_Framework_TestCase {

   private static $MSG = 'p1 <h>hid</h> p2 <c>pub</c> p3 <m>secret</m> p4';
   private static $MPG_SETUP = array(
         GAMETYPE_ZEN_GO => array(
            'BW:1' => array( 'uid' => USER1 ),
            'BW:2' => array( 'uid' => USER2 ),
            'BW:3' => array( 'uid' => USER3 ),
         ),
         GAMETYPE_TEAM_GO => array(
            'B:1' => array( 'uid' => USER1 ),
            'B:2' => array( 'uid' => USER2 ),
            'W:1' => array( 'uid' => USER3 ),
            'W:2' => array( 'uid' => USER4 ),
         ),
      );
   private static $MPG_SETUP_GP = array(
         GAMETYPE_ZEN_GO => '3',
         GAMETYPE_TEAM_GO => '2:2',
      );

   /**
    * Runs the test methods of this class.
    *
    * @access public
    * @static
    */
   public static function main() {
      require_once "PHPUnit/TextUI/TestRunner.php";

      $suite  = new PHPUnit_Framework_TestSuite("GameCommentHelperTest");
      $result = PHPUnit_TextUI_TestRunner::run($suite);
   }


   public function test_filter_comment_std_game() {
      $gcr = $this->buildStdGameCommentHelper( GAME_STATUS_PLAY );
      $gcf = $this->buildStdGameCommentHelper( GAME_STATUS_FINISHED );

      // formatting & MPG-stuff
      $this->assertEquals( "p1 <span class=GameTagH>&lt;h&gt;hid&lt;/h&gt;</span> p2 <span class=GameTagC>&lt;c&gt;pub&lt;/c&gt;</span> p3 <span class=GameTagM>&lt;m&gt;secret&lt;/m&gt;</span> p4",
         $gcr->filter_comment( self::$MSG, 0, BLACK, BLACK, /*html*/true ) );
      $this->assertEquals( 0, $gcr->get_mpg_user() );
      $this->assertEquals( 0, $gcr->get_mpg_move_color() );

      // move_stone=BLACK
      $this->assertEquals( self::$MSG,
         $gcr->filter_comment( self::$MSG, 0, BLACK, BLACK, /*html*/false ) ); // running + writer
      $this->assertEquals( "p1  p2 <c>pub</c> p3  p4",
         $gcr->filter_comment( self::$MSG, 0, BLACK, WHITE, /*html*/false ) ); // running + opponent
      $this->assertEquals( "pub",
         $gcr->filter_comment( self::$MSG, 0, BLACK, DAME, /*html*/false ) ); // running + observer

      $this->assertEquals( self::$MSG,
         $gcf->filter_comment( self::$MSG, 0, BLACK, BLACK, /*html*/false ) ); // finished + writer
      $this->assertEquals( self::$MSG,
         $gcf->filter_comment( self::$MSG, 0, BLACK, WHITE, /*html*/false ) ); // finished + opponent
      $this->assertEquals( "hid\npub",
         $gcf->filter_comment( self::$MSG, 0, BLACK, DAME, /*html*/false ) ); // finished + observer

      // move_stone=WHITE
      $this->assertEquals( self::$MSG,
         $gcr->filter_comment( self::$MSG, 0, WHITE, WHITE, /*html*/false ) ); // running + writer
      $this->assertEquals( "p1  p2 <c>pub</c> p3  p4",
         $gcr->filter_comment( self::$MSG, 0, WHITE, BLACK, /*html*/false ) ); // running + opponent
      $this->assertEquals( "pub",
         $gcr->filter_comment( self::$MSG, 0, WHITE, DAME, /*html*/false ) ); // running + observer

      $this->assertEquals( self::$MSG,
         $gcf->filter_comment( self::$MSG, 0, WHITE, WHITE, /*html*/false ) ); // finished + writer
      $this->assertEquals( self::$MSG,
         $gcf->filter_comment( self::$MSG, 0, WHITE, BLACK, /*html*/false ) ); // finished + opponent
      $this->assertEquals( "hid\npub",
         $gcf->filter_comment( self::$MSG, 0, WHITE, DAME, /*html*/false ) ); // finished + observer

      // parsing surprising comment-tag combos (not HTML-compliant but still matching)
      $arr_chk = array( // val=0 -> expect the same as input
            DAME.' <c>pub1</c> 2' => 'pub1',
            DAME.' <comment>pub2</comment> 2' => 'pub2',
            DAME.' <c>pub3</comment> 2' => 'pub3',
            DAME.' <comment>pub4</c> 2' => 'pub4',
            DAME.' <c>pub5</co> 2' => '', // wrong end-tag
            DAME.' <c>pub6</c 2' => '', // wrong end-tag
            DAME.' <h>hid1</h> 2' => 'hid1',
            DAME.' <hidden>hid2</hidden> 2' => 'hid2',
            DAME.' <h>hid3</hidden> 2' => 'hid3',
            DAME.' <hidden>hid4</h> 2' => 'hid4',
            DAME.' <h>hid5<h> 2' => '', // double start-tag
            BLACK.' <m>mine1</m> 2' => 0,
            BLACK.' <mysecret>mine2</m> 2' => 0,
            BLACK.' <m>mine3<m 2' => 0, // wrong end-tag
            DAME.' <m>mine4</m> 2' => '',
         );
      foreach ( $arr_chk as $chk => $expected )
         $this->assertEquals( ($expected === 0 ? $chk : $expected),
            $gcf->filter_comment( $chk, 0, BLACK, (int)substr($chk,0,1), /*html*/false ) );
   }

   public function test_filter_comment_MP_game() {
      // Zen-Go
      $this->assertFilterComment( self::$MSG,
         self::$MSG, GAMETYPE_ZEN_GO, GAME_STATUS_PLAY, USER1, 1, /*html*/false ); // running + writer (move1)
      $this->assertFilterComment( self::$MSG,
         self::$MSG, GAMETYPE_ZEN_GO, GAME_STATUS_PLAY, USER2, 2, /*html*/false ); // running + writer (move2)
      $this->assertFilterComment( self::$MSG,
         self::$MSG, GAMETYPE_ZEN_GO, GAME_STATUS_PLAY, USER3, 6, /*html*/false ); // running + writer (move6)
      $this->assertFilterComment( "p1  p2 <c>pub</c> p3  p4",
         self::$MSG, GAMETYPE_ZEN_GO, GAME_STATUS_PLAY, USER3, 2, /*html*/false ); // running + oppponent (move2)
      $this->assertFilterComment( "pub",
         self::$MSG, GAMETYPE_ZEN_GO, GAME_STATUS_PLAY, USER5, 2, /*html*/false ); // running + observer

      $this->assertFilterComment( self::$MSG,
         self::$MSG, GAMETYPE_ZEN_GO, GAME_STATUS_FINISHED, USER1, 1, /*html*/false ); // finished + writer (move1)
      $this->assertFilterComment( self::$MSG,
         self::$MSG, GAMETYPE_ZEN_GO, GAME_STATUS_FINISHED, USER2, 2, /*html*/false ); // finished + writer (move2)
      $this->assertFilterComment( self::$MSG,
         self::$MSG, GAMETYPE_ZEN_GO, GAME_STATUS_FINISHED, USER3, 6, /*html*/false ); // finished + writer (move6)
      $this->assertFilterComment( "p1 <h>hid</h> p2 <c>pub</c> p3 <m>secret</m> p4",
         self::$MSG, GAMETYPE_ZEN_GO, GAME_STATUS_FINISHED, USER3, 2, /*html*/false ); // finished + oppponent (move2)
      $this->assertFilterComment( "hid\npub",
         self::$MSG, GAMETYPE_ZEN_GO, GAME_STATUS_FINISHED, USER5, 2, /*html*/false ); // finished + observer

      // Team-Go
      $this->assertFilterComment( self::$MSG,
         self::$MSG, GAMETYPE_TEAM_GO, GAME_STATUS_PLAY, USER1, 1, /*html*/false ); // running + writer (move1)
      $this->assertFilterComment( self::$MSG,
         self::$MSG, GAMETYPE_TEAM_GO, GAME_STATUS_PLAY, USER2, 7, /*html*/false ); // running + writer (move7)
      $this->assertFilterComment( self::$MSG,
         self::$MSG, GAMETYPE_TEAM_GO, GAME_STATUS_PLAY, USER3, 2, /*html*/false ); // running + writer (move2)
      $this->assertFilterComment( self::$MSG,
         self::$MSG, GAMETYPE_TEAM_GO, GAME_STATUS_PLAY, USER4, 12, /*html*/false ); // running + writer (move12)
      $this->assertFilterComment( "p1  p2 <c>pub</c> p3  p4",
         self::$MSG, GAMETYPE_TEAM_GO, GAME_STATUS_PLAY, USER4, 1, /*html*/false ); // running + oppponent (move1)
      $this->assertFilterComment( "pub",
         self::$MSG, GAMETYPE_TEAM_GO, GAME_STATUS_PLAY, USER5, 4, /*html*/false ); // running + observer

      $this->assertFilterComment( self::$MSG,
         self::$MSG, GAMETYPE_TEAM_GO, GAME_STATUS_FINISHED, USER1, 1, /*html*/false ); // finished + writer (move1)
      $this->assertFilterComment( self::$MSG,
         self::$MSG, GAMETYPE_TEAM_GO, GAME_STATUS_FINISHED, USER2, 2, /*html*/false ); // finished + writer (move2)
      $this->assertFilterComment( self::$MSG,
         self::$MSG, GAMETYPE_TEAM_GO, GAME_STATUS_FINISHED, USER3, 7, /*html*/false ); // finished + writer (move7)
      $this->assertFilterComment( self::$MSG,
         self::$MSG, GAMETYPE_TEAM_GO, GAME_STATUS_FINISHED, USER4, 12, /*html*/false ); // finished + writer (move12)
      $this->assertFilterComment( "p1 <h>hid</h> p2 <c>pub</c> p3 <m>secret</m> p4",
         self::$MSG, GAMETYPE_TEAM_GO, GAME_STATUS_FINISHED, USER4, 1, /*html*/false ); // finished + oppponent (move1)
      $this->assertFilterComment( "hid\npub",
         self::$MSG, GAMETYPE_TEAM_GO, GAME_STATUS_FINISHED, USER5, 4, /*html*/false ); // finished + observer
   }

   public function test_game_tag_filter() {
      $this->assertEquals( "<h>hid</h>\n<c>pub</c>\n<m>secret</m>",
         GameCommentHelper::game_tag_filter( self::$MSG, false, /*inclTags*/true ) );
      $this->assertEquals( "<h>hid</h>\n<c>pub</c>",
         GameCommentHelper::game_tag_filter( self::$MSG, true, /*inclTags*/true ) );

      $this->assertEquals( "hid\npub\nsecret",
         GameCommentHelper::game_tag_filter( self::$MSG, false, /*inclTags*/false ) );
      $this->assertEquals( "hid\npub",
         GameCommentHelper::game_tag_filter( self::$MSG, true, /*inclTags*/false ) );
   }

   public function test_remove_game_tags() {
      $this->assertEquals( 'p1  p2 <c>pub</c> p3 <m>secret</m> p4',
         GameCommentHelper::remove_hidden_game_tags( self::$MSG ) );
      $this->assertEquals( 'p1 <h>hid</h> p2 <c>pub</c> p3  p4',
         GameCommentHelper::remove_secret_game_tags( self::$MSG ) );
   }


   private function buildStdGameCommentHelper( $game_status ) {
      return new GameCommentHelper( 123, $game_status, GAMETYPE_GO, '1:1', 0, array(), null );
   }

   private function buildMPGameCommentHelper( $game_type, $game_status, $uid ) {
      $mpg_users = self::$MPG_SETUP[$game_type];
      $mpg_active_user = GamePlayer::find_mpg_user( $mpg_users, $uid );
      $game_players = self::$MPG_SETUP_GP[$game_type];
      return new GameCommentHelper( 123, $game_status, $game_type, $game_players, 0, $mpg_users, $mpg_active_user );
   }

   private function assertFilterComment( $expected, $msg, $game_type, $game_status, $uid, $move_nr, $html ) {
      $gc = $this->buildMPGameCommentHelper( $game_type, $game_status, $uid );
      $this->assertEquals( $expected, $gc->filter_comment( $msg, $move_nr, 0, 0, $html ) );
   }

}

// Call GameCommentHelperTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "GameCommentHelperTest::main") {
   GameCommentHelperTest::main();
}
?>
