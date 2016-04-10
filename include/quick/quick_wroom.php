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
define('WROOM_COMMANDS', 'info|list|delete|join');

define('WROOM_FILTER_SUITABLE', 'suitable');
define('WROOM_FILTERS', 'suitable');


 /*!
  * \class QuickHandlerWaitingroom
  *
  * \brief Quick-handler class for handling wroom-object.
  */
class QuickHandlerWaitingroom extends QuickHandler
{
   private $wroom_id = 0;
   private $wroom = null;
   private $wroom_iterator = null;


   // ---------- Interface ----------------------------------------

   public static function canHandle( $obj, $cmd ) // static
   {
      return ( $obj == QOBJ_WROOM ) && QuickHandler::matchRegex(WROOM_COMMANDS, $cmd);
   }

   public function parseURL()
   {
      parent::checkArgsUnknown('wrid');
      $this->parseFilters(WROOM_FILTERS);

      $this->wroom_id = (int)get_request_arg('wrid');

      // filter-defaults
      if ( !isset($this->filters[WROOM_FILTER_SUITABLE]) )
         $this->filters[WROOM_FILTER_SUITABLE] = 1; // default: suitable=ON
   }//parseURL

   public function prepare()
   {
      global $player_row;

      // see specs/quick_suite.txt (3f)
      $dbgmsg = "QuickHandlerWaitingroom.prepare({$this->my_id})";
      $this->checkCommand( $dbgmsg, WROOM_COMMANDS );
      $cmd = $this->quick_object->cmd;

      // prepare command: info, delete, join, list

      if ( $cmd == QCMD_INFO || $cmd == WROOMCMD_DELETE || $cmd == WROOMCMD_JOIN )
      {
         // check id
         if ( (string)$this->wroom_id == '' || !is_numeric($this->wroom_id) || $this->wroom_id <= 0 )
            error('invalid_args', "$dbgmsg.bad_wrid");
      }

      if ( $cmd == QCMD_INFO )
      {
         $this->filters[WROOM_FILTER_SUITABLE] = 0; // allow ALL
         $qsql = WaitingroomControl::build_waiting_room_query( $this->wroom_id, /*suitable*/false );
         $this->wroom = Waitingroom::load_waitingroom_by_query( $qsql );
         if ( is_null($this->wroom) )
            error('unknown_entry', "$dbgmsg.load_wroom({$this->wroom_id})");
      }
      elseif ( $cmd == QCMD_LIST )
      {
         $filter_suitable = (int)$this->filters[WROOM_FILTER_SUITABLE]; // 0=all, 1=suitable, 2=mine
         $qsql = WaitingroomControl::build_waiting_room_query( 0, ( $filter_suitable == 1 ) );
         if ( $filter_suitable == 2 ) // mine
            $qsql->add_part( SQLP_WHERE, "WR.uid=".$this->my_id );
         else // all | suitable
         {
            $qsql->add_part( SQLP_WHERE, "WR.uid<>".$this->my_id );
            if ( $filter_suitable == 1 ) // suitable
               $qsql = WaitingroomControl::extend_query_waitingroom_suitable( $qsql );
         }

         $qsql->add_part( SQLP_ORDER, 'WRP_Rating2 DESC', 'WRP_Handle ASC', 'ID ASC' );
         $this->add_query_limits( $qsql, /*calc-rows*/true );
         $iterator = new ListIterator("$dbgmsg.list");
         $this->wroom_iterator = Waitingroom::load_waitingroom_entries( $qsql, $iterator );
         $this->read_found_rows();
      }
   }//prepare

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   public function process()
   {
      $cmd = $this->quick_object->cmd;
      if ( $cmd == QCMD_INFO )
         $this->fill_wroom_object( $this->quick_object->result, $this->wroom, /*list*/false );
      elseif ( $cmd == QCMD_LIST )
         $this->process_cmd_list();
      elseif ( $cmd == WROOMCMD_DELETE )
         WaitingroomControl::delete_waitingroom_game( $this->wroom_id );
      elseif ( $cmd == WROOMCMD_JOIN )
         WaitingroomControl::join_waitingroom_game( $this->wroom_id );
   }//process

   private function process_cmd_list()
   {
      $out = array();
      if ( !is_null($this->wroom_iterator) )
      {
         while ( list(,$arr_item) = $this->wroom_iterator->getListIterator() )
         {
            list( $wr, $wrow ) = $arr_item;
            $arr = array();
            $this->fill_wroom_object( $arr, $wr, /*list*/true );
            $out[] = $arr;
         }
      }
      $this->add_list( QOBJ_WROOM, $out, 'user.rating-,user.handle+' );
   }//process_cmd_list

   private function fill_wroom_object( &$result, $wr, $is_list )
   {
      $wro = new WaitingroomOffer( $wr->wrow );
      $wro->calculate_offer_settings(); // probable game settings

      $user_rows = array( $wr->uid => $wr->User->urow );
      $time_limit = TimeFormat::echo_time_limit(
            $wr->Maintime, $wr->Byotype, $wr->Byotime, $wr->Byoperiods,
            TIMEFMT_QUICK|TIMEFMT_ENGL|TIMEFMT_SHORT|TIMEFMT_ADDTYPE);

      list( $restrictions, $joinable ) =
         WaitingroomControl::get_waitingroom_restrictions(
            $wr->wrow, $this->filters[WROOM_FILTER_SUITABLE], /*html*/false );
      if ( $restrictions == NO_VALUE )
         $restrictions = '';

      $opp_started_games = $join_warning = $join_error = '';
      if ( !$is_list && !$wro->is_my_game() )
      {
         $opp_started_games = GameHelper::count_started_games( $this->my_id, $wr->uid );

         list( $can_join, $html_out, $join_warning, $join_error ) = $wro->check_joining_waitingroom(/*html*/false);
         if ( !$can_join )
            $joinable = false;
      }

      $result['id'] = $wr->ID;
      $result['user'] = $this->build_obj_user($wr->uid, @$user_rows[$wr->uid], '', 'country,rating');
      if ( $this->is_with_option(QWITH_USER_ID) )
         $result['user']['hero_badge'] = User::determine_hero_badge( @$user_rows[$wr->uid]['WRP_HeroRatio'] );
      $result['created_at'] = QuickHandler::formatDate($wr->Created);
      $result['count_offers'] = $wr->CountOffers;
      $result['comment'] = $wr->Comment;

      // game-settings
      $result['game_type'] = $wr->GameType;
      $result['game_players'] = $wr->GamePlayers;
      $result['handicap_type'] = strtolower($wr->Handicaptype);
      $result['shape_id'] = $wr->ShapeID;

      $result['rated'] = ( $wr->Rated ) ? 1 : 0;
      $result['ruleset'] = strtoupper($wr->Ruleset);
      $result['size'] = $wr->Size;
      $result['komi'] = $wr->Komi;
      $result['handicap'] = $wr->Handicap;
      $result['handicap_mode'] = ( $wr->StdHandicap ) ? 'STD' : 'FREE';

      $result['adjust_handicap'] = $wr->AdjHandicap;
      $result['min_handicap'] = $wr->MinHandicap;
      $result['max_handicap'] = $wr->MaxHandicap;
      $result['adjust_komi'] = $wr->AdjKomi;
      $result['jigo_mode'] = strtoupper($wr->JigoMode);

      $result['time_weekend_clock'] = ( $wr->WeekendClock ) ? 1 : 0;
      $result['time_mode'] = strtoupper($wr->Byotype);
      $result['time_limit'] = $time_limit;
      $result['time_main'] = $wr->Maintime;
      $result['time_byo'] = $wr->Byotime;
      $result['time_periods'] = $wr->Byoperiods;

      $result['restrictions'] = $restrictions;
      $result['join'] = ( !$wro->is_my_game() && $joinable ) ? 1 : 0;
      if ( !$is_list )
      {
         $result['join_warn'] = $join_warning;
         $result['join_err'] = $join_error;
         $result['opp_started_games'] = $opp_started_games;
      }

      $result['calc_type'] = $wro->resultType;
      $result['calc_color'] = $wro->resultColor;
      $result['calc_handicap'] = $result['calc_komi'] = ''; // needed for LIST-cmd
      if ( ($wro->resultType == 1 || $wro->resultType == 2) && $wro->resultColor )
      {
         $result['calc_handicap'] = $wro->resultHandicap;
         if ( $wro->resultColor != 'fairkomi' )
            $result['calc_komi'] = $wro->resultKomi;
      }
   }//fill_wroom_object


   // ------------ static functions ----------------------------

} // end of 'QuickHandlerWaitingroom'

?>
