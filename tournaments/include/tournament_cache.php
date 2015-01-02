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

 /* Author: Jens-Uwe Gaspar */

require_once 'include/connect2mysql.php';
require_once 'include/dgs_cache.php';
require_once 'include/std_classes.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_director.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_ladder_props.php';
require_once 'tournaments/include/tournament_news.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_points.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_result.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_rules.php';

 /*!
  * \file tournament_cache.php
  *
  * \brief Container and function to cache tournament-objects.
  */


 /*!
  * \class TournamentCache
  *
  * \brief Helper-class to store different tournament-objects in local cache.
  */
class TournamentCache
{
   /*! \brief array( tid => Tournament-object ); fallback if shared-mem-cache disabled. */
   private $cache_tournament = array();
   /*! \brief locked Tournament-object (mostly used for cron-locking). */
   private $lock_tourney = null;

   private function __construct()
   {
   }

   public function is_tournament_locked()
   {
      return !is_null($this->lock_tourney);
   }

   /*! \brief Releases (previously) cron-locked tournament. */
   public function release_tournament_cron_lock( $tid=0 )
   {
      // release (previous) lock when handling NEW tourney-ID
      if ( $this->is_tournament_locked() && ($tid != $this->lock_tourney->ID) )
      {
         $lock_tid = $this->lock_tourney->ID;
         $this->lock_tourney->update_flags( TOURNEY_FLAG_LOCK_CRON, 0 );
         $this->lock_tourney = null;

         self::delete_cache_tournament( $lock_tid );
      }
   }

   /*!
    * \brief Sets cron-lock for given tournament if not admin/tdwork-locked.
    * \param $tid tournament-ID
    * \param true if lock was successful; false otherwise
    */
   public function set_tournament_cron_lock( $tid )
   {
      $tourney = self::load_cache_tournament( 'TCache.set_tournament_cron_lock.find', $tid );

      if ( $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN | TOURNEY_FLAG_LOCK_TDWORK) )
         return false;

      // lock for tourney-changes
      if ( !$this->is_tournament_locked() )
      {
         $tourney->update_flags( TOURNEY_FLAG_LOCK_CRON, 1 );
         $this->lock_tourney = $tourney;
      }

      return true;
   }//set_tournament_cron_lock


   // ------------ static functions ----------------------------

   /*! \brief Returns TournamentCache-singleton. */
   public static function get_instance()
   {
      static $TOURNAMENT_CACHE = null;
      if ( is_null($TOURNAMENT_CACHE) )
         $TOURNAMENT_CACHE = new TournamentCache();
      return $TOURNAMENT_CACHE;
   }

   /*!
    * \brief Loads and caches tournament for given tournament-id (fallback to run-cache if shared-mem-cache unavailable).
    * \param $check_exist true = die if tournament cannot be found
    */
   public static function load_cache_tournament( $dbgmsg, $tid, $check_exist=true )
   {
      $tid = (int)$tid;
      $dbgmsg .= ".TCache:load_cache_tournament($tid,$check_exist)";
      $key = "Tournament.$tid";

      $use_dgs_cache = DgsCache::is_persistent( CACHE_GRP_TOURNAMENT );

      $tourney = $tcache = null;
      if ( $use_dgs_cache )
         $tourney = DgsCache::fetch( $dbgmsg, CACHE_GRP_TOURNAMENT, $key );
      else
      {
         $tcache = self::get_instance();
         if ( isset($tcache->cache_tournament[$tid]) )
            $tourney = $tcache->cache_tournament[$tid];
      }

      if ( is_null($tourney) )
      {
         $tourney = Tournament::load_tournament($tid);
         if ( $check_exist && is_null($tourney) )
            error('unknown_tournament', $dbgmsg);

         if ( !is_null($tourney) ) // only cache if existing
         {
            if ( $use_dgs_cache )
               DgsCache::store( $dbgmsg, CACHE_GRP_TOURNAMENT, $key, $tourney, SECS_PER_HOUR );
            else
               $tcache->cache_tournament[$tid] = $tourney;
         }
      }

      return $tourney;
   }//load_cache_tournament

   public static function delete_cache_tournament( $tid )
   {
      DgsCache::delete( "TCache:delete_cache_tournament($tid)", CACHE_GRP_TOURNAMENT, "Tournament.$tid" );

      // delete run-cache
      $tcache = self::get_instance();
      unset($tcache->cache_tournament[$tid]);
   }

   /*!
    * \brief Checks if user(uid) is tournament-director for given tournament with given flags.
    * \param $flags TD_FLAG_GAME_END, ...
    * \return TournamentDirector-object (with Flags set, but without Comment) for matching director;
    *         or null if user is not tournament-director
    */
   public static function is_cache_tournament_director( $dbgmsg, $tid, $uid, $flags=0 )
   {
      $tid = (int)$tid;
      $uid = (int)$uid;
      $dbgmsg .= ".TCache:is_cache_tournament_director($tid,$uid,$flags)";
      $key = "TDirector.$tid";

      $arr_tdir = DgsCache::fetch( $dbgmsg, CACHE_GRP_TDIRECTOR, $key );
      if ( is_null($arr_tdir) )
      {
         $arr_tdir = TournamentDirector::load_tournament_directors_flags( $tid );
         DgsCache::store( $dbgmsg, CACHE_GRP_TDIRECTOR, $key, $arr_tdir, SECS_PER_DAY );
      }

      $td_result = null;
      if ( isset($arr_tdir[$uid]) ) // user is TD
      {
         $td_flags = (int)$arr_tdir[$uid];
         if ( $flags <= 0 || ($td_flags & $flags) ) // pure TD, or TD-matching-flags
            $td_result = new TournamentDirector($tid, $uid, $td_flags);
      }

      return $td_result;
   }//is_cache_tournament_director

   /*!
    * \brief Loads and caches TournamentLadderProps for given tournament-id.
    * \param $check_exist true = die if db-entry cannot be found
    */
   public static function load_cache_tournament_ladder_props( $dbgmsg, $tid, $check_exist=true )
   {
      $tid = (int)$tid;
      $dbgmsg .= ".TCache:load_cache_tlp($tid,$check_exist)";
      $key = "TLadderProps.$tid";

      $tl_props = DgsCache::fetch( $dbgmsg, CACHE_GRP_TLPROPS, $key );
      if ( is_null($tl_props) )
      {
         $tl_props = TournamentLadderProps::load_tournament_ladder_props($tid);
         if ( $check_exist && is_null($tl_props) )
            error('bad_tournament', $dbgmsg);

         if ( !is_null($tl_props) ) // only cache if existing
            DgsCache::store( $dbgmsg, CACHE_GRP_TLPROPS, $key, $tl_props, SECS_PER_DAY );
      }

      return $tl_props;
   }//load_cache_tournament_ladder_props

   /*!
    * \brief Loads and caches TournamentProperties for given tournament-id.
    * \param $check_exist true = die if db-entry cannot be found
    */
   public static function load_cache_tournament_properties( $dbgmsg, $tid, $check_exist=true )
   {
      $tid = (int)$tid;
      $dbgmsg .= ".TCache:load_cache_tprops($tid,$check_exist)";
      $key = "TProps.$tid";

      $tprops = DgsCache::fetch( $dbgmsg, CACHE_GRP_TPROPS, $key );
      if ( is_null($tprops) )
      {
         $tprops = TournamentProperties::load_tournament_properties($tid);
         if ( $check_exist && is_null($tprops) )
            error('bad_tournament', $dbgmsg);

         if ( !is_null($tprops) ) // only cache if existing
            DgsCache::store( $dbgmsg, CACHE_GRP_TPROPS, $key, $tprops, SECS_PER_DAY );
      }

      return $tprops;
   }//load_cache_tournament_properties

   /*!
    * \brief Loads and caches TournamentRules for given tournament-id.
    * \param $check_exist true = die if db-entry cannot be found
    */
   public static function load_cache_tournament_rules( $dbgmsg, $tid, $check_exist=true )
   {
      $tid = (int)$tid;
      $dbgmsg .= ".TCache:load_cache_trules($tid,$check_exist)";
      $key = "TRules.$tid";

      $trule = DgsCache::fetch( $dbgmsg, CACHE_GRP_TRULES, $key );
      if ( is_null($trule) )
      {
         $trule = TournamentRules::load_tournament_rule($tid);
         if ( $check_exist && is_null($trule) )
            error('bad_tournament', $dbgmsg);

         if ( !is_null($trule) ) // only cache if existing
            DgsCache::store( $dbgmsg, CACHE_GRP_TRULES, $key, $trule, SECS_PER_DAY );
      }

      return $trule;
   }//load_cache_tournament_rules

   /*!
    * \brief Loads and caches TournamentRound for given tournament-id and round.
    * \param $check_exist true = die if db-entry cannot be found
    */
   public static function load_cache_tournament_round( $dbgmsg, $tid, $round, $check_exist=true )
   {
      $tid = (int)$tid;
      $round = (int)$round;
      $dbgmsg .= ".TCache:load_cache_tround($tid,$round,$check_exist)";
      $key = "TRound.$tid.$round";

      $tround = DgsCache::fetch( $dbgmsg, CACHE_GRP_TROUND, $key );
      if ( is_null($tround) )
      {
         $tround = TournamentRound::load_tournament_round($tid, $round);
         if ( $check_exist && is_null($tround) )
            error('bad_tournament', $dbgmsg);

         if ( !is_null($tround) ) // only cache if existing
            DgsCache::store( $dbgmsg, CACHE_GRP_TROUND, $key, $tround, SECS_PER_DAY );
      }

      return $tround;
   }//load_cache_tournament_round

   /*! \brief Loads and caches TournamentNews for view_tournament.php-page for given combination of tournament-id/is-admin/is-TP. */
   public static function load_cache_tournament_news( $dbgmsg, $tid, $is_admin, $is_tp )
   {
      $tid = (int)$tid;
      $dbgmsg .= ".TCache:load_cache_tnews($tid,$is_admin,$is_tp)";
      $key = sprintf( "TNews.%s.%s.%s", $tid, ($is_admin ? 1 : 0), ($is_tp ? 1 : 0) );

      $arr_tnews = DgsCache::fetch( $dbgmsg, CACHE_GRP_TNEWS, $key );
      if ( is_null($arr_tnews) )
      {
         $news_qsql = TournamentNews::build_view_query_sql( /*tid*/0, /*tn*/0, TNEWS_STATUS_SHOW, $is_admin, $is_tp );
         $news_qsql->add_part( SQLP_ORDER, 'TN.Published DESC' );
         $news_iterator = new ListIterator( 'Tournament.view_tournament.news.SHOW.tnews', $news_qsql );
         $news_iterator = TournamentNews::load_tournament_news( $news_iterator, $tid );

         $arr_tnews = array();
         while ( list(,$arr_item) = $news_iterator->getListIterator() )
            $arr_tnews[] = $arr_item[0];

         DgsCache::store( $dbgmsg, CACHE_GRP_TNEWS, $key, $arr_tnews, SECS_PER_DAY, "TNews.$tid" );
      }

      return $arr_tnews;
   }//load_cache_tournament_news

   /*!
    * \brief Returns cached count of TournamentParticipants for given tournament, TP-status, round and NextRound.
    * \param $tp_status one of TP_STATUS_... or array of TP_STATUS_... or null (=count all TP-stati)
    * \param $round 0 (=count all rounds), >0 (=count only given round)
    * \param $use_next_round true = match $next_round on TP.NextRound; false = match on TP.StartRound
    * \see TournamentParticipant.count_tournament_participants()
    */
   public static function count_cache_tournament_participants( $tid, $tp_status, $round, $use_next_round )
   {
      $tid = (int)$tid;
      if ( is_array($tp_status) )
         $stat_str = implode('-', $tp_status);
      elseif ( is_null($tp_status) )
         $stat_str = '';
      else
         $stat_str = $tp_status;

      $dbgmsg = "TCache:count_cache_tps($tid,$stat_str,$round,$use_next_round)";
      $group_id = "TPCount.$tid";
      $key = "TPCount.$tid.$stat_str.$round." . ($use_next_round ? 1 : 0);

      $arr_counts = DgsCache::fetch( $dbgmsg, CACHE_GRP_TP_COUNT, $key );
      if ( is_null($arr_counts) )
      {
         $arr_counts = TournamentParticipant::count_tournament_participants($tid, $tp_status, $round, $use_next_round);
         DgsCache::store( $dbgmsg, CACHE_GRP_TP_COUNT, $key, $arr_counts, SECS_PER_DAY, $group_id );
      }

      return $arr_counts;
   }//count_cache_tournament_participants

   /*!
    * \brief Returns non-null array with cached count of TournamentParticipants for all (start-)rounds and TP-stati.
    * \see TournamentParticipant.count_all_tournament_participants()
    */
   public static function count_cache_all_tournament_participants( $tid )
   {
      $tid = (int)$tid;
      $dbgmsg = "TCache:count_cache_all_tps($tid)";
      $key = "TPCountAll.$tid";

      $arr_counts = DgsCache::fetch( $dbgmsg, CACHE_GRP_TP_COUNT_ALL, $key );
      if ( is_null($arr_counts) )
      {
         $arr_counts = TournamentParticipant::count_all_tournament_participants($tid);
         DgsCache::store( $dbgmsg, CACHE_GRP_TP_COUNT_ALL, $key, $arr_counts, SECS_PER_DAY );
      }

      return $arr_counts;
   }//count_cache_all_tournament_participants

   /*! \brief Loads and caches TournamentParticipant for given tournament-id and user-id. */
   public static function load_cache_tournament_participant( $dbgmsg, $tid, $uid )
   {
      $tid = (int)$tid;
      $uid = (int)$uid;
      $dbgmsg .= ".TCache:load_cache_tp($tid,$uid)";
      $key = "TParticipant.$tid.$uid";

      $tp = DgsCache::fetch( $dbgmsg, CACHE_GRP_TPARTICIPANT, $key );
      if ( is_null($tp) )
      {
         $tp = TournamentParticipant::load_tournament_participant( $tid, $uid );
         if ( !is_null(@$tp->User->urow) )
            $tp->User->urow = null; // all fields read
         if ( $uid > 0 )
            DgsCache::store( $dbgmsg, CACHE_GRP_TPARTICIPANT, $key, (is_null($tp) ? false : $tp), SECS_PER_HOUR );
      }
      elseif ( $tp === false )
         $tp = null;

      return $tp;
   }//load_cache_tournament_participant

   public static function is_cache_tournament_participant( $dbgmsg, $tid, $uid )
   {
      if ( DgsCache::is_persistent(CACHE_GRP_TPARTICIPANT) )
      {
         $dbgmsg .= ".TCache:is_cache_tp";
         $tp = self::load_cache_tournament_participant( $dbgmsg, $tid, $uid );
         $result = (is_null($tp)) ? false : $tp->Status;
      }
      else
         $result = TournamentParticipant::isTournamentParticipant( $tid, $uid );

      return $result;
   }//is_cache_tournament_participant

   /*!
    * \brief Loads and caches TournamentResults for given tournament-id and type.
    * \return arr( ListIterator, found_rows )
    *       IMPORTANT NOTE: ListIterator re-constructed from cached tournament-results lacks query-stuff
    *       and only contains items and result-rows-count.
    */
   public static function load_cache_tournament_results( $dbgmsg, $tid, $iterator, $with_player )
   {
      $tid = (int)$tid;
      $dbgmsg .= ".TCache:load_cache_tresults($tid,$with_player)";
      $key = "TResult.$tid";
      $same_query_check = sprintf( '%s;%s;%s', $tid, ( $with_player ? 1 : 0 ), $iterator->buildQuery() );

      $arr_tresult = DgsCache::fetch( $dbgmsg, CACHE_GRP_TRESULT, $key );
      if ( !is_null($arr_tresult) ) // check if cache-entry needs to be invalidated
      {
         // expected cache-entry: $same_query_check, $result_found_rows, $arr_tresult
         if ( count($arr_tresult) != 3 )
            $arr_tresult = null; // something's fishy here, better reload
         else
         {
            $same_query_cached = $arr_tresult[0]; // check 1st entry with same-query-check
            if ( $same_query_cached != $same_query_check )
               $arr_tresult = null; // need to load different query
         }
      }

      if ( is_null($arr_tresult) )
      {
         // load tournament-results
         $result_iterator = TournamentResult::load_tournament_results( $iterator, $tid, $with_player );
         $result_found_rows = mysql_found_rows("$dbgmsg.found_rows");

         // start with 1st + 2nd entry: query-check + mysql-found-rows
         $arr_tresult = array( $same_query_check, $result_found_rows );

         while ( list(,$arr_item) = $result_iterator->getListIterator() )
            $arr_tresult[] = $arr_item[1]; // only store orig-row to save cache-storage-space
         $result_iterator->resetListIterator();

         DgsCache::store( $dbgmsg, CACHE_GRP_TRESULT, $key, $arr_tresult, SECS_PER_DAY );
      }
      else // convert cached tresult-array into ListIterator
      {
         $same_query_cached = array_shift( $arr_tresult ); // remove 1st entry with query-check
         $result_found_rows = (int)array_shift( $arr_tresult ); // remove 2nd entry with mysql-found-rows
         $result_iterator = new ListIterator( $dbgmsg );
         $result_iterator->setResultRows( count($arr_tresult) );
         foreach ( $arr_tresult as $orow )
            $result_iterator->addItem( TournamentResult::new_from_row($orow), $orow ); // rebuild obj
      }

      return array( $result_iterator, $result_found_rows );
   }//load_cache_tournament_results

   /*! \brief Loads and caches TournamentGames for given tournament-id. */
   public static function load_cache_tournament_games( $dbgmsg, $tid, $round_id=0, $pool=0, $status=null )
   {
      $tid = (int)$tid;
      $pool_info = (is_array($pool)) ? implode(',', $pool) : $pool;

      $dbgmsg .= ".TCache:load_cache_tgames($tid)";
      $group_id = "TGames.$tid";
      $key = "TGames.$tid.$round_id.$pool_info";
      $tg_iterator = new ListIterator( $dbgmsg );

      $arr_tgames = DgsCache::fetch( $dbgmsg, CACHE_GRP_TGAMES, $key );
      if ( is_null($arr_tgames) )
      {
         $tg_iterator = TournamentGames::load_tournament_games( $tg_iterator, $tid, $round_id, $pool, $status );

         DgsCache::store( $dbgmsg, CACHE_GRP_TGAMES, $key, $tg_iterator->getItemRows(), SECS_PER_DAY, $group_id );
      }
      else // transform cache-stored row-arr into ListIterator of TournamentGames
      {
         foreach ( $arr_tgames as $row )
         {
            $tgame = TournamentGames::new_from_row( $row );
            $tg_iterator->addItem( $tgame, $row );
         }
      }

      return $tg_iterator;
   }//load_cache_tournament_games

   /*!
    * \brief Loads and caches TournamentPoints for given tournament-id.
    * \param $check_exist true = die if db-entry cannot be found
    */
   public static function load_cache_tournament_points( $dbgmsg, $tid, $check_exist=true )
   {
      $tid = (int)$tid;
      $dbgmsg .= ".TCache:load_cache_tpoints($tid,$check_exist)";
      $key = "TPoints.$tid";

      $tpoints = DgsCache::fetch( $dbgmsg, CACHE_GRP_TPOINTS, $key );
      if ( is_null($tpoints) )
      {
         $tpoints = TournamentPoints::load_tournament_points($tid);
         if ( $check_exist && is_null($tpoints) )
            error('bad_tournament', $dbgmsg);

         if ( !is_null($tpoints) ) // only cache if existing
            DgsCache::store( $dbgmsg, CACHE_GRP_TPOINTS, $key, $tpoints, SECS_PER_DAY );
      }

      return $tpoints;
   }//load_cache_tournament_points

   /*!
    * \brief Loads and caches TournamentPools for given tournament-id and round.
    * \param $use_cache false = bypass cache and do not store data in cache; true = use cache
    * \note IMPORTANT NOTE: keep in sync with TournamentPool::load_tournament_pools()
    */
   public static function load_cache_tournament_pools( $dbgmsg, $tid, $round, $need_trating, $use_cache )
   {
      $tid = (int)$tid;
      $dbgmsg .= ".TCache:load_cache_tpools($tid,$round,$need_trating,$use_cache)";
      $key = "TPools.$tid.$round." . ($need_trating ? 1 : 0);
      $group_id = "TPools.$tid.$round";

      $load_opts = TPOOL_LOADOPT_USER | ( $need_trating ? TPOOL_LOADOPT_TRATING : 0 );
      $tpool_iterator = new ListIterator( $dbgmsg );

      if ( $use_cache )
         $arr_tpools = DgsCache::fetch( $dbgmsg, CACHE_GRP_TPOOLS, $key );
      else
         $arr_tpools = null;
      if ( is_null($arr_tpools) )
      {
         $tpool_iterator = TournamentPool::load_tournament_pools( $tpool_iterator, $tid, $round, 0, $load_opts );

         if ( $use_cache )
            DgsCache::store( $dbgmsg, CACHE_GRP_TPOOLS, $key, $tpool_iterator->getItemRows(), SECS_PER_HOUR, $group_id );
      }
      else // transform cache-stored row-arr into ListIterator of TournamentPool
      {
         $tpool_iterator->addIndex( 'uid' );
         foreach ( $arr_tpools as $row )
         {
            $tpool = TournamentPool::new_tournament_pool_from_cache_row( $row, $load_opts );
            $tpool_iterator->addItem( $tpool, $row );
         }
         $tpool_iterator->setResultRows( $tpool_iterator->getItemCount() );
      }

      return $tpool_iterator;
   }//load_cache_tournament_pools

} // end of 'TournamentCache'
?>
