<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Jens-Uwe Gaspar

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

// Call GameSetupTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
   define("PHPUnit_MAIN_METHOD", "GameSetupTest::main");
}

require_once "PHPUnit/Framework/TestCase.php";
require_once "PHPUnit/Framework/TestSuite.php";

require_once 'include/quick_common.php';
require_once 'scripts/tests/UnitTestHelper.php';
require_once 'include/game_functions.php';

recover_language( array('Lang' => LANG_DEF_LOAD) ); // want default english


/**
 * Test class for helpers to handle game-setup-data.
 * Generated by PHPUnit_Util_Skeleton on 2009-12-06 at 10:55:49.
 */
class GameSetupTest extends PHPUnit_Framework_TestCase {

   private $gs;
   private $gsi;

   /**
    * Runs the test methods of this class.
    *
    * @access public
    * @static
    */
   public static function main() {
      require_once "PHPUnit/TextUI/TestRunner.php";

      $suite  = new PHPUnit_Framework_TestSuite("GameSetupTest");
      $result = PHPUnit_TextUI_TestRunner::run($suite);
   }

   protected function setUp() {
      UnitTestHelper::clearErrors(ERROR_MODE_PRINT);

      $this->gs = self::create_gs();
      $this->gsi = self::create_gs_inv();
   }

   public function test_swap_htype() {
      // implicit check of create_gs()
      $gs = self::create_gs(); // uid=123
      $this->assertEquals( 123, $gs->uid );

      $gs->Handicaptype = HTYPE_CONV;
      $this->assertEquals( HTYPE_CONV, $gs->get_user_view_handicaptype(123) );
      $this->assertEquals( HTYPE_CONV, $gs->get_user_view_handicaptype(456) );

      $gs->Handicaptype = HTYPE_BLACK;
      $this->assertEquals( HTYPE_BLACK, $gs->get_user_view_handicaptype(123) );
      $this->assertEquals( HTYPE_WHITE, $gs->get_user_view_handicaptype(456) );

      $gs->Handicaptype = HTYPE_WHITE;
      $this->assertEquals( HTYPE_WHITE, $gs->get_user_view_handicaptype(123) );
      $this->assertEquals( HTYPE_BLACK, $gs->get_user_view_handicaptype(456) );

      $gs->Handicaptype = HTYPE_I_KOMI_YOU_COLOR;
      $this->assertEquals( HTYPE_I_KOMI_YOU_COLOR, $gs->get_user_view_handicaptype(123) );
      $this->assertEquals( HTYPE_YOU_KOMI_I_COLOR, $gs->get_user_view_handicaptype(456) );

      $gs->Handicaptype = HTYPE_YOU_KOMI_I_COLOR;
      $this->assertEquals( HTYPE_YOU_KOMI_I_COLOR, $gs->get_user_view_handicaptype(123) );
      $this->assertEquals( HTYPE_I_KOMI_YOU_COLOR, $gs->get_user_view_handicaptype(456) );
   }

   public function test_new_from_game_row() {
      // implicit check of create_gs()
      $this->assertEquals( 123, $this->gs->uid );
      $this->assertEquals( HTYPE_AUCTION_SECRET, $this->gs->Handicaptype );
      $this->assertEquals( 2, $this->gs->Handicap );
      $this->assertEquals( -1, $this->gs->AdjHandicap );
      $this->assertEquals( 3, $this->gs->MinHandicap );
      $this->assertEquals( 9, $this->gs->MaxHandicap );
      $this->assertEquals( -199.5, $this->gs->Komi );
      $this->assertEquals( 70, $this->gs->AdjKomi );
      $this->assertEquals( JIGOMODE_ALLOW_JIGO, $this->gs->JigoMode );
      $this->assertEquals( 'Y', $this->gs->MustBeRated );
      $this->assertEquals( -800, $this->gs->RatingMin );
      $this->assertEquals( 2600, $this->gs->RatingMax );
      $this->assertEquals( 998, $this->gs->MinRatedGames );
      $this->assertEquals( 0, $this->gs->MinHeroRatio );
      $this->assertEquals( -102, $this->gs->SameOpponent );
      $this->assertEquals( 'slow game', $this->gs->Message );
   }

   public function test_read_waitingroom_fields() {
      // implicit check of create_gs_inv()
      $this->assertEquals( 4711, $this->gsi->tid );
      $this->assertEquals( 7, $this->gsi->ShapeID );
      $this->assertEquals( 'A* 19 W', $this->gsi->ShapeSnapshot );
      $this->assertEquals( GAMETYPE_GO, $this->gsi->GameType );
      $this->assertEquals( '2:3', $this->gsi->GamePlayers );
      $this->assertEquals( RULESET_CHINESE, $this->gsi->Ruleset );
      $this->assertEquals( 17, $this->gsi->Size );
      $this->assertEquals( true, $this->gsi->Rated );
      $this->assertEquals( false, $this->gsi->StdHandicap );
      $this->assertEquals( 61, $this->gsi->Maintime );
      $this->assertEquals( BYOTYPE_JAPANESE, $this->gsi->Byotype );
      $this->assertEquals( 17, $this->gsi->Byotime );
      $this->assertEquals( 4, $this->gsi->Byoperiods );
      $this->assertEquals( true, $this->gsi->WeekendClock );
   }

   public function test_encode() {
      $gs = $this->gs;
      $this->assertEquals( 'T7:U123:H2:-1:3:9:K-199.5:70.0:J1:FK:R1:-800:2600:998:H%:-102:Cslow game', $gs->encode_game_setup() );

      $gs->Handicaptype = HTYPE_PROPER;
      $gs->AdjHandicap = +7;
      $gs->Komi = 6;
      $gs->AdjKomi = -199.5;
      $gs->JigoMode = JIGOMODE_KEEP_KOMI;
      $gs->SameOpponent = 0;
      $gs->MinHeroRatio = 40;
      $gs->Message = 'bla:blub';
      $this->assertEquals( 'T2:U123:H2:7:3:9:K6.0:-199.5:J0:FK:R1:-800:2600:998:H%40-:0:Cbla:blub', $gs->encode_game_setup() );
   }

   public function test_encode_invite() {
      $gsi = $this->gsi;
      $this->assertEquals( 'T7:U123:H2:-1:3:9:K-199.5:70.0:J1:FK:R1:-800:2600:998:H%:-102:C:I17:1:r2:0:tJ:61:17:4:1', $gsi->encode_game_setup(GSENC_FULL_GAME) );
      $this->assertEquals( 'T7:U123:H2:-1:3:9:K-199.5:70.0:J1:FK:R1:-800:2600:998:H%:-102:C', $gsi->encode_game_setup() );

      $gsi->Handicaptype = HTYPE_PROPER;
      $gsi->Komi = 6;
      $gsi->AdjKomi = 10;
      $gsi->JigoMode = JIGOMODE_NO_JIGO;
      $gsi->SameOpponent = 3;
      $gsi->Message = 'wwi';
      $gsi->Size = 18;
      $gsi->Rated = false;
      $gsi->Ruleset = RULESET_JAPANESE;
      $gsi->StdHandicap = true;
      $gsi->Maintime = 6;
      $gsi->Byotype = BYOTYPE_CANADIAN;
      $gsi->Byotime = 10;
      $gsi->Byoperiods = 3;
      $gsi->WeekendClock = false;
      $this->assertEquals( 'T2:U123:H2:-1:3:9:K6.0:10.0:J2:FK:R1:-800:2600:998:H%:3:C:I18:0:r1:1:tC:6:10:3:0', $gsi->encode_game_setup(GSENC_FULL_GAME) );
      $this->assertEquals( 'T2:U123:H2:-1:3:9:K6.0:10.0:J2:FK:R1:-800:2600:998:H%:3:Cwwi', $gsi->encode_game_setup() );
   }

   public function test_new_from_game_setup() {
      $gs = GameSetup::new_from_game_setup( 'T7:U123:H2:-1:3:9:K-199.5:70.0:J1:FK:R1:-800:2600:998:H%:-102:Cslow game' );
      $this->assertEquals( $this->gs->to_string(), $gs->to_string() );
      $this->assertEquals( $this->gs->to_string(true), $gs->to_string(true) );

      $gs = GameSetup::new_from_game_setup( 'T2:U123:H2:7:3:9:K6.0:-190.5:J0:FK-7.0:R0:-333:1000:5:H%33-:0:Cbla:blub' );
      $this->assertEquals( 'GameSetup: U=123 T=proper H=2/7/3..9 K=6/-190.5 J=KEEP_KOMI FK=-7 MBR=No Rating=-333..1000 MRG=5 HERO=33- SO=0 M=[bla:blub]; #G=1 view=', $gs->to_string() );
      $this->assertEquals( 'GameSetup: U=123 T=proper H=2/7/3..9 K=6/-190.5 J=KEEP_KOMI FK=-7 MBR=No Rating=-333..1000 MRG=5 HERO=33- SO=0 M=[bla:blub]; S=19 Rules=JAPANESE Rated=Yes StdH=Yes Time=FIS:210/15/0:Yes tid=0 shape=0/[] gtype=GO/; #G=1 view=', $gs->to_string(true) );
   }

   public function test_new_from_game_setup_invitation() {
      $gsi = GameSetup::new_from_game_setup( 'T7:U123:H2:-1:3:9:K-199.5:70.0:J1:FK:R1:-800:2600:998:H%:-102:C:I17:1:r2:0:tJ:61:17:4:1', true );
      $this->gsi->tid = $this->gsi->ShapeID = 0;
      $this->gsi->ShapeSnapshot = $this->gsi->GamePlayers = '';
      $this->assertEquals( $this->gsi->to_string(true), $gsi->to_string(true) );

      $gsi = GameSetup::new_from_game_setup( 'T2:U123:H2:7:3:9:K6.0:-190.5:J0:FK-7.0:R0:-333:1000:5:H%:0:C:I9:0:r2:1:tC:50:10:7:0', true );
      $this->assertEquals( 'GameSetup: U=123 T=proper H=2/7/3..9 K=6/-190.5 J=KEEP_KOMI FK=-7 MBR=No Rating=-333..1000 MRG=5 HERO=0- SO=0 M=[]; S=9 Rules=CHINESE Rated=No StdH=Yes Time=CAN:50/10/7:No tid=0 shape=0/[] gtype=GO/; #G=1 view=', $gsi->to_string(true) );

      // catch error: no message for 'Ccomment'
      UnitTestHelper::clearErrors(ERROR_MODE_TEST);
      $gsi = GameSetup::new_from_game_setup( 'T2:U123:H2:7:3:9:K6.0:-190.5:J0:FK-7.0:R0:-333:1000:5:H%:0:Cbad-text:I9:0:r2:1:tC:50:10:7:0', true );
      $this->assertEquals( 1, UnitTestHelper::countErrors() );

      // catch error: unknown byo-type
      UnitTestHelper::clearErrors(ERROR_MODE_TEST);
      $gsi = GameSetup::new_from_game_setup( 'T2:U123:H2:7:3:9:K6.0:-190.5:J0:FK-7.0:R0:-333:1000:5:H%:0:C:I9:0:r2:1:tA:50:10:7:0', true );
      $this->assertEquals( 1, UnitTestHelper::countErrors() );
   }

   public function test_build_invitation_diffs() {
      $gs1 = GameSetup::new_from_game_setup( 'T2:U123:H2:0:0:0:K6:0:J0:FK:R0:0:0:0:H%:0:C:I9:0:r2:1:tC:50:10:7:0', true );
      $gs2 = clone $gs1;
      $this->assertEquals( 0, count(GameSetup::build_invitation_diffs($gs1,$gs2)) );

      $gs2 = GameSetup::new_from_game_setup( 'T4:U123:H0:0:0:0:K-5:0:J1:FK:R0:0:0:0:H%:0:C:I11:1:r1:0:tC:90:5:4:1', true );
      $r = GameSetup::build_invitation_diffs($gs1,$gs2);
      $this->assertEquals( 8, count($r) );
      $i = 0;
      $this->assertEquals(
         array( 'Ruleset', 'Chinese', 'Japanese' ), $r[$i++] );
      $this->assertEquals(
         array( 'Board Size', '9', '11' ), $r[$i++] );
      $this->assertEquals(
         array( 'Handicap Type', 'Proper handicap', 'Manual setting with My Color [Double], Handicap 0, Komi -5', 1 ), $r[$i++] );
      $this->assertEquals(
         array( 'Handicap stones placement', 'Standard-Handicap', 'Free-Handicap' ), $r[$i++] );
      $this->assertEquals(
         array( 'Adjust Komi', '', '[Allow Jigo]' ), $r[$i++] );
      $this->assertEquals(
         array( 'Time', 'C: 3days 5hours + 10hours / 7', 'C: 6days + 5hours / 4' ), $r[$i++] );
      $this->assertEquals(
         array( 'Clock runs on weekends', 'No', 'Yes' ), $r[$i++] );
      $this->assertEquals(
         array( 'Rated game', 'No', 'Yes' ), $r[$i++] );

      $gs3 = GameSetup::new_from_game_setup( 'T7:U123:H0:0:0:0:K5:0:J2:FK:R0:0:0:0:H%:0:C:I11:1:r1:0:tJ:90:5:4:1', true );
      $r = GameSetup::build_invitation_diffs($gs2,$gs3);
      $this->assertEquals( 3, count($r) );
      $i = 0;
      $this->assertEquals(
         array( 'Handicap Type', 'Manual setting with My Color [Double], Handicap 0, Komi -5', 'Fair Komi of Type [Secret Auction Komi], Jigo mode [Forbid Jigo]', 1 ), $r[$i++] );
      $this->assertEquals(
         array( 'Adjust Komi', '[Allow Jigo]', '[No Jigo]' ), $r[$i++] );
      $this->assertEquals(
         array( 'Time', 'C: 6days + 5hours / 4', 'J: 6days + 5hours * 4' ), $r[$i++] );
   }


   private static function create_gs() {
      $r = array(
         'uid' => 123,
         'Handicaptype' => HTYPE_AUCTION_SECRET,
         'Handicap' => 2,
         'AdjHandicap' => -1,
         'MinHandicap' => 3,
         'MaxHandicap' => 9,
         'Komi' => -199.5,
         'AdjKomi' => 70.0,
         'JigoMode' => JIGOMODE_ALLOW_JIGO,
         'MustBeRated' => 'Y',
         'RatingMin' => -800,
         'RatingMax' => 2600,
         'MinRatedGames' => 998,
         'MinHeroRatio' => 0,
         'SameOpponent' => -102,
         'Message' => 'slow game',
      );
      $gs = GameSetup::new_from_waitingroom_game_row( $r );
      return $gs;
   }

   public static function create_gs_inv() {
      $gs = self::create_gs();
      $gs->read_waitingroom_fields( array(
         'tid' => 4711,
         'ShapeID' => 7,
         'ShapeSnapshot' => 'A* 19 W',
         'GameType' => GAMETYPE_GO,
         'GamePlayers' => '2:3',
         'Ruleset' => RULESET_CHINESE,
         'Size' => '17',
         'Rated' => true,
         'StdHandicap' => false,
         'Maintime' => 61,
         'Byotype' => BYOTYPE_JAPANESE,
         'Byotime' => 17,
         'Byoperiods' => 4,
         'WeekendClock' => true,
      ));
      $gs->Message = '';
      return $gs;
   }

}

// Call GameSetupTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "GameSetupTest::main") {
   GameSetupTest::main();
}
?>
