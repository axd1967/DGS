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

 /* Author: Jens-Uwe Gaspar */

require_once 'include/connect2mysql.php';
require_once 'include/cache_clock.php';
require_once 'include/dgs_cache.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_director.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_ladder_props.php';

 /*!
  * \file tournament_cache.php
  *
  * \brief Container and function to cache tournament-objects.
  */

global $TOURNAMENT_CACHE;
$TOURNAMENT_CACHE = new TournamentCache();


 /*!
  * \class TournamentCache
  *
  * \brief Helper-class to store different tournament-objects in local cache.
  */
class TournamentCache
{
   /*! \brief array( tid => Tournament-object ); fallback if shared-mem-cache disabled. */
   var $cache_tournament;

   var $cache_clock;

   /*! \brief locked Tournament-object (mostly used for cron-locking). */
   var $lock_tourney;


   function TournamentCache()
   {
      $this->cache_tournament = array();
      $this->cache_clock = ClockCache::get_clock_cache();
      $this->lock_tourney = null;
   }

   function load_clock_ticks( $dbgmsg, $clock_id )
   {
      return $this->cache_clock->load_clock_ticks( $dbgmsg, $clock_id, /*use-cache*/true );
   }

   function is_tournament_locked()
   {
      return !is_null($this->lock_tourney);
   }

   /*! \brief Releases (previously) cron-locked tournament. */
   function release_tournament_cron_lock( $tid=0 )
   {
      // release (previous) lock when handling NEW tourney-ID
      if( $this->is_tournament_locked() && ($tid != $this->lock_tourney->ID) )
      {
         $lock_tid = $this->lock_tourney->ID;
         $this->lock_tourney->update_flags( TOURNEY_FLAG_LOCK_CRON, 0 );
         $this->lock_tourney = null;

         TournamentCache::delete_cache_tournament( $lock_tid );
      }
   }

   /*!
    * \brief Sets cron-lock for given tournament if not admin/tdwork-locked.
    * \param $tid tournament-ID
    * \param true if lock was successful; false otherwise
    */
   function set_tournament_cron_lock( $tid )
   {
      $tourney = TournamentCache::load_cache_tournament( 'TCache.set_tournament_cron_lock.find', $tid );

      if( $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN | TOURNEY_FLAG_LOCK_TDWORK) )
         return false;

      // lock for tourney-changes
      if( !$this->is_tournament_locked() )
      {
         $tourney->update_flags( TOURNEY_FLAG_LOCK_CRON, 1 );
         $this->lock_tourney = $tourney;
      }

      return true;
   }


   // ------------ static functions ----------------------------

   function get_instance()
   {
      global $TOURNAMENT_CACHE;
      return $TOURNAMENT_CACHE;
   }

   /*!
    * \brief Loads and caches tournament for given tournament-id (fallback to run-cache if shared-mem-cache unavailable).
    * \param $check_exist true = die if tournament cannot be found
    */
   function load_cache_tournament( $dbgmsg, $tid, $check_exist=true )
   {
      $tid = (int)$tid;
      $dbgmsg .= ".TCache::load_cache_tournament($tid,$check_exist)";
      $key = "Tournament.$tid";

      $use_dgs_cache = DgsCache::is_shared_enabled();

      $tourney = $tcache = null;
      if( $use_dgs_cache )
         $tourney = DgsCache::fetch($dbgmsg, $key);
      else
      {
         $tcache = TournamentCache::get_instance();
         if( isset($tcache->cache_tournament[$tid]) )
            $tourney = $tcache->cache_tournament[$tid];
      }

      if( is_null($tourney) )
      {
         $tourney = Tournament::load_tournament($tid);
         if( $check_exist && is_null($tourney) )
            error('unknown_tournament', $dbgmsg);

         if( !is_null($tourney) ) // only cache if existing
         {
            if( $use_dgs_cache )
               DgsCache::store( $dbgmsg, $key, $tourney, SECS_PER_HOUR );
            else
               $tcache->cache_tournament[$tid] = $tourney;
         }
      }

      return $tourney;
   }//load_cache_tournament

   function delete_cache_tournament( $tid )
   {
      DgsCache::delete( "TCache.delete_cache_tournament($tid)", "Tournament.$tid" );

      // delete run-cache
      $tcache = TournamentCache::get_instance();
      unset($tcache->cache_tournament[$tid]);
   }

   /*!
    * \brief Checks if user(uid) is tournament-director for given tournament with given flags.
    * \param $flags TD_FLAG_GAME_END, ...
    * \return TournamentDirector-object (with Flags set, but without Comment) for matching director;
    *         or null if user is not tournament-director
    */
   function is_cache_tournament_director( $dbgmsg, $tid, $uid, $flags=0 )
   {
      $tid = (int)$tid;
      $uid = (int)$uid;
      $dbgmsg .= ".TCache::is_cache_tournament_director($tid,$uid,$flags)";
      $key = "TDirector.$tid";

      $arr_tdir = DgsCache::fetch($dbgmsg, $key);
      if( is_null($arr_tdir) )
      {
         $arr_tdir = TournamentDirector::load_tournament_directors_flags( $tid );
         DgsCache::store( $dbgmsg, $key, $arr_tdir, SECS_PER_HOUR );
      }

      $td_result = null;
      if( isset($arr_tdir[$uid]) ) // user is TD
      {
         $td_flags = (int)$arr_tdir[$uid];
         if( $flags <= 0 || ($td_flags & $flags) ) // pure TD, or TD-matching-flags
            $td_result = new TournamentDirector($tid, $uid, $td_flags);
      }

      return $td_result;
   }//is_cache_tournament_director

   /*!
    * \brief Loads and caches TournamentLadderProps for given tournament-id.
    * \param $check_exist true = die if db-entry cannot be found
    */
   function load_cache_tournament_ladder_props( $dbgmsg, $tid, $check_exist=true )
   {
      $tid = (int)$tid;
      $dbgmsg .= ".TCache::load_cache_tlp($tid,$check_exist)";
      $key = "TLadderProps.$tid";

      $tl_props = DgsCache::fetch($dbgmsg, $key);
      if( is_null($tl_props) )
      {
         $tl_props = TournamentLadderProps::load_tournament_ladder_props($tid);
         if( $check_exist && is_null($tl_props) )
            error('bad_tournament', $dbgmsg);

         if( !is_null($tl_props) ) // only cache if existing
            DgsCache::store( $dbgmsg, $key, $tl_props, SECS_PER_HOUR );
      }

      return $tl_props;
   }//load_cache_tournament_ladder_props

   /*!
    * \brief Loads and caches TournamentProperties for given tournament-id.
    * \param $check_exist true = die if db-entry cannot be found
    */
   function load_cache_tournament_properties( $dbgmsg, $tid, $check_exist=true )
   {
      $tid = (int)$tid;
      $dbgmsg .= ".TCache::load_cache_tprops($tid,$check_exist)";
      $key = "TProps.$tid";

      $tprops = DgsCache::fetch($dbgmsg, $key);
      if( is_null($tprops) )
      {
         $tprops = TournamentProperties::load_tournament_properties($tid);
         if( $check_exist && is_null($tprops) )
            error('bad_tournament', $dbgmsg);

         if( !is_null($tprops) ) // only cache if existing
            DgsCache::store( $dbgmsg, $key, $tprops, SECS_PER_HOUR );
      }

      return $tprops;
   }//load_cache_tournament_properties

} // end of 'TournamentCache'
?>
