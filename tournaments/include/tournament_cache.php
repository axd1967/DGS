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
         $news_iterator = new ListIterator( 'Tournament.view_tournament.news.SHOW', $news_qsql );
         $news_iterator = TournamentNews::load_tournament_news( $news_iterator, $tid );

         $arr_tnews = array();
         while ( list(,$arr_item) = $news_iterator->getListIterator() )
            $arr_tnews[] = $arr_item[0];

         DgsCache::store( $dbgmsg, CACHE_GRP_TNEWS, $key, $arr_tnews, SECS_PER_DAY, "TNews.$tid" );
      }

      return $arr_tnews;
   }//load_cache_tournament_news

   /*!
    * \brief Returns non-null array with count of TournamentParticipants for given tournament and TP-status.
    * \note if caching is activated, all stati are returned even if $status != null
    */
   //TODO TODO obsolete !?
   public static function count_cache_tournament_participants( $tid, $status=null )
   {
      $tid = (int)$tid;
      $dbgmsg = "TCache:count_cache_tp($tid,$status)";
      $key = "TPCount.$tid";

      $arr_counts = DgsCache::fetch( $dbgmsg, CACHE_GRP_TP_COUNT, $key );
      if ( is_null($arr_counts) )
      {
         $arr_counts = TournamentParticipant::count_tournament_participants($tid);
         DgsCache::store( $dbgmsg, CACHE_GRP_TP_COUNT, $key, $arr_counts, SECS_PER_HOUR );
      }

      return $arr_counts;
   }//count_cache_tournament_participants

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

   /*! \brief Loads and caches TournamentResults for given tournament-id and type. */
   public static function load_cache_tournament_results( $dbgmsg, $tid, $tourney_type )
   {
      $tid = (int)$tid;
      $dbgmsg .= ".TCache:load_cache_tresults($tid,$tourney_type)";
      $key = "TResult.$tid";

      $arr_tresult = DgsCache::fetch( $dbgmsg, CACHE_GRP_TRESULT, $key );
      if ( is_null($arr_tresult) )
      {
         // load tournament-results
         if ( $tourney_type == TOURNEY_TYPE_LADDER )
            $order = 'ORDER BY Rank ASC, RankKept DESC, EndTime DESC';
         elseif ( $tourney_type == TOURNEY_TYPE_ROUND_ROBIN )
            $order = 'ORDER BY Round DESC, Rank ASC, EndTime DESC';
         else
            $order = 'ORDER BY ID';
         $iterator = new ListIterator( $dbgmsg, null, $order );
         $iterator->addQuerySQLMerge( new QuerySQL(
               SQLP_FIELDS, 'TRP.Name AS TRP_Name', 'TRP.Handle AS TRP_Handle',
                            'TRP.Country AS TRP_Country', 'TRP.Rating2 AS TRP_Rating2',
               SQLP_FROM,   'INNER JOIN Players AS TRP ON TRP.ID=TRS.uid'
            ));
         $iterator = TournamentResult::load_tournament_results( $iterator, $tid );

         $arr_tresult = array();
         while ( list(,$arr_item) = $iterator->getListIterator() )
            $arr_tresult[] = $arr_item;

         DgsCache::store( $dbgmsg, CACHE_GRP_TRESULT, $key, $arr_tresult, SECS_PER_DAY );
      }

      return $arr_tresult;
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

} // end of 'TournamentCache'
?>
