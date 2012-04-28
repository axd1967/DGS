<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/quick/quick_handler.php';
require_once 'include/db/waitingroom.php';
require_once 'include/time_functions.php';
require_once 'include/game_functions.php';
require_once 'include/wroom_control.php';
require_once 'include/message_functions.php';


 /*!
  * \file quick_wroom.php
  *
  * \brief QuickHandler for wroom-object.
  * \see specs/quick_suite.txt (3f)
  */

// see specs/quick_suite.txt (3f)
define('WROOMCMD_DELETE', 'delete');
define('WROOMCMD_JOIN', 'join');
define('WROOMCMD_NEW_GAME', 'new_game'); //TODO impl
define('WROOM_COMMANDS', 'info|delete|join');


 /*!
  * \class QuickHandlerWaitingroom
  *
  * \brief Quick-handler class for handling wroom-object.
  */
class QuickHandlerWaitingroom extends QuickHandler
{
   var $wroom_id;
   var $wroom;

   function QuickHandlerWaitingroom( $quick_object )
   {
      parent::QuickHandler( $quick_object );

      $this->wroom_id = 0;
      $this->wroom = null;
   }


   // ---------- Interface ----------------------------------------

   function canHandle( $obj, $cmd ) // static
   {
      return ( $obj == QOBJ_WROOM ) && QuickHandler::matchRegex(WROOM_COMMANDS, $cmd);
   }

   function parseURL()
   {
      parent::checkArgsUnknown('wrid');
      $this->wroom_id = (int)get_request_arg('wrid');
   }

   function prepare()
   {
      global $player_row;
      $uid = (int)@$player_row['ID'];

      // see specs/quick_suite.txt (3f)
      $dbgmsg = "QuickHandlerWaitingroom.prepare($uid)";
      $this->checkCommand( $dbgmsg, WROOM_COMMANDS );
      $cmd = $this->quick_object->cmd;

      // prepare command: list, info, delete, join

      if( $cmd == QCMD_INFO || $cmd == WROOMCMD_DELETE || $cmd == WROOMCMD_JOIN )
      {
         // check id
         if( (string)$this->wroom_id == '' || !is_numeric($this->wroom_id) || $this->wroom_id <= 0 )
            error('invalid_args', "$dbgmsg.bad_wrid");
      }

      if( $cmd == QCMD_INFO )
      {
         $this->wroom = Waitingroom::load_waitingroom( $this->wroom_id, $this->is_with_option(QWITH_USER_ID) );
         if( is_null($this->wroom) )
            error('unknown_entry', "$dbgmsg.load_wroom({$this->wroom_id})");
      }

      // check for invalid-action

   }//prepare

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   function process()
   {
      $cmd = $this->quick_object->cmd;
      if( $cmd == QCMD_INFO )
         $this->process_cmd_info();
      elseif( $cmd == WROOMCMD_DELETE )
         WaitingroomControl::delete_waitingroom_game( $this->wroom_id );
      elseif( $cmd == WROOMCMD_JOIN )
         WaitingroomControl::join_waitingroom_game( $this->wroom_id );
   }

   function process_cmd_info()
   {
      $wr = $this->wroom;
      $user_rows = array( $wr->uid => $wr->User->urow );
      $time_limit = TimeFormat::echo_time_limit(
            $wr->Maintime, $wr->Byotype, $wr->Byotime, $wr->Byoperiods,
            TIMEFMT_QUICK|TIMEFMT_ENGL|TIMEFMT_SHORT|TIMEFMT_ADDTYPE);
      $opp_started_games = GameHelper::count_started_games( $this->my_id, $wr->uid );

      $restrictions = ''; //TODO TODO
         //echo_game_restrictions($MustBeRated, $RatingMin, $RatingMax,
            //$MinRatedGames, $goodmaxgames, $SameOpponent, (!$suitable && @$CH_hidden), true) );

      $calc_type = 1; // TODO quality of setings: 1=probable-setting (conv/proper depends on rating), 2=fix-calculated
      $calc_color = 'black'; // TODO probable/fix color of logged-in user=> double | fairkomi | nigiri | black | white
      $calc_handicap = 3; // TODO probable/fix handicap
      $calc_komi = 6.5; // TODO probably/fix komi

      $this->addResultKey('id', $wr->ID );
      $this->addResultKey('user', $this->build_obj_user($wr->uid, $user_rows, 'country,rating') );
      $this->addResultKey('created_at', QuickHandler::formatDate($wr->Created) );
      $this->addResultKey('count_offers', $wr->CountOffers );
      $this->addResultKey('comment', $wr->Comment );

      // game-settings
      $this->addResultKey('game_type', $wr->GameType );
      $this->addResultKey('game_players', $wr->GamePlayers );
      $this->addResultKey('handicap_type', strtolower($wr->Handicaptype) );
      $this->addResultKey('shape_id', $wr->ShapeID );

      $this->addResultKey('rated', ( $wr->Rated ) ? 1 : 0 );
      $this->addResultKey('ruleset', strtoupper($wr->Ruleset) );
      $this->addResultKey('size', $wr->Size );
      $this->addResultKey('komi', $wr->Komi );
      $this->addResultKey('jigo_mode', strtoupper($wr->JigoMode) );
      $this->addResultKey('handicap', $wr->Handicap );
      $this->addResultKey('handicap_mode', ( $wr->StdHandicap ) ? 'STD' : 'FREE' );

      $this->addResultKey('adjust_komi', $wr->AdjKomi );
      $this->addResultKey('adjust_handicap', $wr->AdjHandicap );
      $this->addResultKey('min_handicap', $wr->MinHandicap );
      $this->addResultKey('max_handicap', $wr->MaxHandicap );

      $this->addResultKey('time_weekend_clock', ( $wr->WeekendClock ) ? 1 : 0 );
      $this->addResultKey('time_mode', strtoupper($wr->Byotype) );
      $this->addResultKey('time_limit', $time_limit );
      $this->addResultKey('time_main', $wr->Maintime );
      $this->addResultKey('time_byo', $wr->Byotime );
      $this->addResultKey('time_periods', $wr->Byoperiods );

      $this->addResultKey('restrictions', $restrictions );
      $this->addResultKey('opp_started_games', $opp_started_games );
      $this->addResultKey('calc_type', $calc_type );
      $this->addResultKey('calc_color', $calc_color );
      $this->addResultKey('calc_handicap', $calc_handicap );
      $this->addResultKey('calc_komi', $calc_komi );
   }//process_cmd_info


   // ------------ static functions ----------------------------

} // end of 'QuickHandlerWaitingroom'

?>
