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


 /*!
  * \file quick_wroom.php
  *
  * \brief QuickHandler for wroom-object.
  * \see specs/quick_suite.txt (3f)
  */

// see specs/quick_suite.txt (3f)
define('WROOMCMD_JOIN', 'join'); //TODO impl
define('WROOMCMD_NEW_GAME', 'new_game'); //TODO impl
define('WROOM_COMMANDS', 'info');


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

      // prepare command: list

      if( $cmd == QCMD_INFO )
      {
         // check id
         if( (string)$this->wroom_id == '' || !is_numeric($this->wroom_id) || $this->wroom_id <= 0 )
            error('invalid_args', "$dbgmsg.bad_wrid");

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
   }

   function process_cmd_info()
   {
      $wr = $this->wroom;
      $user_rows = array( $wr->uid => $wr->User->urow );
      $time_limit = TimeFormat::echo_time_limit(
            $wr->Maintime, $wr->Byotype, $wr->Byotime, $wr->Byoperiods,
            TIMEFMT_QUICK|TIMEFMT_ENGL|TIMEFMT_SHORT|TIMEFMT_ADDTYPE);

      $this->addResultKey('id', $wr->ID );
      $this->addResultKey('user', $this->build_obj_user($wr->uid, $user_rows) );
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

      //TODO TODO
      $this->addResultKey('opp_started_games', 0 ); //TODO also add for wroom
      $this->addResultKey('calc_type', 0 );
      $this->addResultKey('calc_color', 0 );
      $this->addResultKey('calc_handicap', 0 );
      $this->addResultKey('calc_komi', 0 );
      $this->addResultKey('restrictions', '' );
   }//process_cmd_info


   // ------------ static functions ----------------------------

} // end of 'QuickHandlerWaitingroom'

?>
