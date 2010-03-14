<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Tournament";

require_once 'include/connect2mysql.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_director.php';
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
   /*! \brief array( tid => Tournament-object ) */
   var $cache_tournament;

   /*! \brief array( tid:uid => TournamentDirector-object(without-user) ) */
   var $cache_tdirector;

   /*! \brief array( tid => TournamentLadderProps-object ) */
   var $cache_tl_props;

   /*! \brief array( clock_id => ticks ) */
   var $cache_clock_ticks;

   /*! \brief locked Tournament-object (mostly used for cron-locking). */
   var $lock_tourney;


   function TournamentCache()
   {
      $this->cache_tournament = array();
      $this->cache_tdirector = array();
      $this->cache_tl_props = array();
      $this->cache_clock_ticks = array();
      $this->lock_tourney = null;
   }

   function load_tournament( $dbgmsg, $tid )
   {
      $tid = (int)$tid;
      if( isset($this->cache_tournament[$tid]) )
         $tourney = $this->cache_tournament[$tid];
      else
      {
         $tourney = Tournament::load_tournament($tid);
         if( is_null($tourney) )
            error('unknown_tournament', "$dbgmsg.find_tournament($tid)");
         else
            $this->cache_tournament[$tid] = $tourney;
      }
      return $tourney;
   }

   /*!
    * \brief Checks if user(uid) is tournament-director for given tournament with given flags.
    * \param $flags TD_FLAG_GAME_END, ...
    * \return flags-matching TournamentDirectory-object; null otherwise
    */
   function is_tournament_director( $dbgmsg, $tid, $uid, $flags=0 )
   {
      $tid = (int)$tid;
      $uid = (int)$uid;
      $key = "$tid:$uid";
      if( isset($this->cache_tdirector[$key]) )
         $td = $this->cache_tdirector[$key];
      else
      {
         $td = TournamentDirector::load_tournament_director( $tid, $uid, /*with_user*/false );
         if( !is_null($td) )
            $this->cache_tdirector[$key] = $td;
      }

      $is_tdir = !is_null($td);
      if( $is_tdir && ($flags > 0) )
         $is_tdir = ( $td->Flags & $flags );
      return ($is_tdir) ? $td : null;
   }

   function load_tournament_ladder_props( $dbgmsg, $tid )
   {
      $tid = (int)$tid;
      if( isset($this->cache_tl_props[$tid]) )
         $tl_props = $this->cache_tl_props[$tid];
      else
      {
         $tl_props = TournamentLadderProps::load_tournament_ladder_props( $tid );
         if( is_null($tl_props) )
            error('unknown_tournament', "$dbgmsg.find_tladder_props($tid)");
         else
            $this->cache_tl_props[$tid] = $tl_props;
      }
      return $tl_props;
   }

   function load_clock_ticks( $dbgmsg, $clock_id )
   {
      if( !is_numeric($clock_id) || $clock_id < 0 || $clock_id > MAX_CLOCK )
         error('invalid_args', "$dbgmsg.load_clock_ticks.check($clock_id)");

      if( !isset($this->cache_clock_ticks[$clock_id]) )
      {
         $row = mysql_single_fetch( "$dbgmsg.load_clock_ticks.find($clock_id)",
            "SELECT Ticks FROM Clock WHERE ID=$clock_id LIMIT 1" );
         if( !$row )
            error('invalid_args', "$dbgmsg.load_clock_ticks.bad_clock($clock_id)");
         else
            $this->cache_clock_ticks[$clock_id] = (int)@$row['Ticks'];
      }
      $ticks = (int)@$this->cache_clock_ticks[$clock_id];
      return $ticks;
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
         $this->lock_tourney->update_flags( TOURNEY_FLAG_LOCK_CRON, 0 );
         $this->lock_tourney = null;
      }
   }

   /*!
    * \brief Sets cron-lock for given tournament if not admin/tdwork-locked.
    * \param $tid tournament-ID
    * \param true if lock was successful; false otherwise
    */
   function set_tournament_cron_lock( $tid )
   {
      // load (cached) Tournament
      $tourney = $this->load_tournament( 'TournamentCache.set_tournament_cron_lock.find', $tid );

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

} // end of 'TournamentCache'
?>
