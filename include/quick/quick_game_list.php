<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/quick/quick_game_info.php';
require_once 'include/std_functions.php';
require_once 'include/std_classes.php';
require_once 'include/classlib_user.php';
require_once 'include/time_functions.php';
require_once 'include/game_functions.php';
require_once 'include/rating.php';
require_once 'include/gamelist_control.php';


 /*!
  * \file quick_game_list.php
  *
  * \brief QuickHandler for returning list of game-objects.
  * \see specs/quick_suite.txt (3a)
  */

// see specs/quick_suite.txt (3a)
define('GAMELIST_COMMANDS', 'list');

define('GAMELIST_OPT_VIEW', 'view');
define('GAMELIST_OPT_UID', 'uid');
define('QGAMELIST_OPTIONS', 'view|uid');

define('GAMELIST_OPTVAL_VIEW_STATUS', 'status');
define('GAMELIST_OPTVAL_VIEW_OBSERVE', 'observe');
define('GAMELIST_OPTVAL_VIEW_RUNNING', 'running');
define('GAMELIST_OPTVAL_VIEW_FINISHED', 'finished');
define('CHECK_GAMELIST_OPTVAL_VIEW', 'status|observe|running|finished');

define('GAMELIST_FILTER_MPG', 'mpg');
define('GAMELIST_FILTER_TID', 'tid');
define('GAMELIST_FILTERS', 'mpg|tid');


 /*!
  * \class QuickHandlerGameList
  *
  * \brief Quick-handler class for handling game-object.
  */
class QuickHandlerGameList extends QuickHandler
{
   private $opt_view = '';
   private $opt_uid = 0; // original option: int | 'all' | 'mine'

   private $glc = null; // GameListControl
   private $game_result_rows = null;


   // ---------- Interface ----------------------------------------

   public static function canHandle( $obj, $cmd ) // static
   {
      return ( $obj == QOBJ_GAME ) && QuickHandler::matchRegex(GAMELIST_COMMANDS, $cmd);
   }

   public function parseURL()
   {
      parent::checkArgsUnknown(QGAMELIST_OPTIONS);
      $this->parseFilters(GAMELIST_FILTERS);

      $this->opt_view = get_request_arg(GAMELIST_OPT_VIEW);
      $this->opt_uid = get_request_arg(GAMELIST_OPT_UID);

      // filter-defaults
      if( !isset($this->filters[GAMELIST_FILTER_MPG]) )
         $this->filters[GAMELIST_FILTER_MPG] = 0; // default: OFF
      if( !ALLOW_TOURNAMENTS || !isset($this->filters[GAMELIST_FILTER_TID]) )
         $this->filters[GAMELIST_FILTER_TID] = 0; // default: 0
   }//parseURL

   public function prepare()
   {
      global $player_row;
      $my_id = $player_row['ID'];

      // see specs/quick_suite.txt (3a)
      $dbgmsg = "QuickHandlerGameList.prepare()";
      $this->checkCommand( $dbgmsg, GAMELIST_COMMANDS );
      $cmd = $this->quick_object->cmd;

      // check args
      QuickHandler::checkArgMandatory( $dbgmsg, GAMELIST_OPT_VIEW, $this->opt_view );
      if( !QuickHandler::matchRegex(CHECK_GAMELIST_OPTVAL_VIEW, $this->opt_view) )
         error('invalid_args', "$dbgmsg.check.opt.view({$this->opt_view})");

      $uid = 0;
      if( is_numeric($this->opt_uid) && $this->opt_uid > 0 )
         $uid = (int)$this->opt_uid;
      elseif( $this->opt_uid == 'all' )
         $uid = 'all';
      elseif( (string)$this->opt_uid == '' || $this->opt_uid == 0 || $this->opt_uid == 'mine' )
         $uid = $my_id;
      else
         error('invalid_args', "$dbgmsg.check.opt.uid({$this->opt_uid})");

      // check filters
      $f_ext_tid = $this->filters[GAMELIST_FILTER_TID];
      if( !ALLOW_TOURNAMENTS || !is_numeric($f_ext_tid) || $f_ext_tid < 0 )
         $f_ext_tid = 0;
      $f_mpg = (bool)$this->filters[GAMELIST_FILTER_MPG];

      $dbgmsg = "QuickHandlerGameList.prepare({$this->opt_view},$uid)";


      // prepare command: list

      $glc = new GameListControl(/*quick*/true);
      if( $this->opt_view == GAMELIST_OPTVAL_VIEW_STATUS )
      {
         if( $uid != $my_id )
            error('invalid_args', "$dbgmsg.check.view.only_mine");

         $glc->setView( GAMEVIEW_STATUS, $uid );
         $this->clear_with_options( array( QWITH_RATINGDIFF ) ); // ST: not allowed

         // allow returning ALL entries for status-view
         if( @$_REQUEST[QOPT_LIMIT] === 'all' )
            $this->list_limit = $this->list_offset = 0;

         $qsql = GameListControl::build_game_list_query_status_view(
            $uid, $this->is_with_option(QWITH_NOTES), $this->is_with_option(QWITH_PRIO), $f_mpg, $f_ext_tid );

         $this->list_order = NextGameOrder::get_next_game_order( $player_row['NextGameOrder'], 'QUICK', false );

         if( $this->is_with_option(QWITH_USER_ID) )
            GameListControl::extend_game_list_query_with_user_info( $qsql );
      }
      else //view running|finished|observe
      {
         if( $this->opt_view == GAMELIST_OPTVAL_VIEW_OBSERVE && !($uid === 'all' || $uid == $my_id) )
            error('invalid_args', "$dbgmsg.check.view.uid.only_all_or_mine");
         elseif( $this->opt_view != GAMELIST_OPTVAL_VIEW_OBSERVE && $uid === 'all' ) // would need restriction as web-site
            error('invalid_args', "$dbgmsg.check.view.uid.all.not_supported({$this->opt_uid})");

         if( $this->opt_view == GAMELIST_OPTVAL_VIEW_OBSERVE )
            $glc->setView( ($uid === 'all' ? GAMEVIEW_OBSERVE_ALL : GAMEVIEW_OBSERVE_MINE), $uid );
         else // running/finished
            $glc->setView( ($this->opt_view == GAMELIST_OPTVAL_VIEW_RUNNING ? GAMEVIEW_RUNNING : GAMEVIEW_FINISHED), $uid );

         // reset forbidden WITH-options
         if( $glc->is_observe_all() )
            $this->clear_with_options( array( QWITH_PRIO, QWITH_NOTES, QWITH_RATINGDIFF ) ); // OA
         elseif( $glc->is_running() )
         {
            if( $uid == $my_id )
               $this->clear_with_options( array( QWITH_RATINGDIFF ) ); // MY-RU
            else
               $this->clear_with_options( array( QWITH_PRIO, QWITH_RATINGDIFF ) ); // OTHER-RU
         }
         elseif( $glc->is_finished() )
            $this->clear_with_options( array( QWITH_PRIO, QWITH_NOTES ) ); // FU

         $glc->mp_game = $f_mpg;
         $glc->ext_tid = $f_ext_tid;

         // only load notes/ratingdiff/rem-time if field requested by client to reduce server-load
         // avoiding additional joins on respective tables: GamesNotes, Ratinglog, Clock
         $is_mine = ($uid == $my_id);
         $show_notes = (LIST_GAMENOTE_LEN>0 && !$glc->is_observe() && !$glc->is_all() && $is_mine); // FU+RU subset
         $glc->load_notes = ($show_notes && $this->is_with_option(QWITH_NOTES) );
         $load_remaining_time = ( $glc->is_running() && !$glc->is_all() && $uid == $my_id );

         $qsql = $glc->build_games_query( $this->is_with_option(QWITH_RATINGDIFF), $load_remaining_time, $this->is_with_option(QWITH_PRIO) );

         // default order
         if( $glc->is_observe_all() )
         {
            $qsql->add_part( SQLP_ORDER, 'X_ObsCount DESC', 'Lastchanged DESC', 'ID DESC' );
            $this->list_order = 'obs_count-,time_lastmove-,id-';
         }
         else
         {
            $qsql->add_part( SQLP_ORDER, 'Lastchanged DESC', 'ID DESC' );
            $this->list_order = 'time_lastmove-,id-';
         }
      }
      $this->glc = $glc;

      $this->add_query_limits( $qsql, /*calc-rows*/true );

      // load games
      $arr = array();
      $result = db_query( "$dbgmsg.find_games", $qsql->get_select() );
      while( $row = mysql_fetch_assoc($result) )
         $arr[] = $row;
      mysql_free_result($result);
      $this->game_result_rows = $arr;

      $this->read_found_rows();
   }//prepare

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   public function process()
   {
      $out = array();

      if( is_array($this->game_result_rows) )
      {
         foreach( $this->game_result_rows as $game_row )
         {
            $arr = array();
            $out[] = QuickHandlerGameInfo::fill_game_info($this, $this->glc, $arr, $game_row);
         }
      }

      $this->add_list( QOBJ_GAME, $out );
   }//process


   // ------------ static functions ----------------------------

} // end of 'QuickHandlerGameList'

?>
