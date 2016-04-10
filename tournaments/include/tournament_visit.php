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

$TranslateGroups[] = "Tournament";

require_once 'include/cache_globals.php';
require_once 'include/connect2mysql.php';
require_once 'include/dgs_cache.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament.php';


 /*!
  * \file tournament_visit.php
  *
  * \brief Functions for handling tournament visits: table TournamentVisit
  */


 /*!
  * \class TournamentVisit
  *
  * \brief Class to manage TournamentVisit-table with info about user viewing tournament-info-page
  */
class TournamentVisit
{

   // ------------ static functions ----------------------------

   /*! \brief Inserts (or updates existing) TournamentVisit-entries for given user-id and tournament-id. */
   public static function mark_tournament_visited( $uid, $tid )
   {
      global $NOW;
      $uid = (int)$uid;
      $tid = (int)$tid;

      if ( !self::has_visited_tournament( "TVisit.mark_tournament_visited", $uid, $tid ) )
      {
         db_query( "TournamentVisit:mark_tournament_visited.insert($uid,$tid)",
            "INSERT INTO TournamentVisit (uid,tid,VisitTime) " .
            "VALUES ($uid,$tid,FROM_UNIXTIME($NOW)) " .
            "ON DUPLICATE KEY UPDATE VisitTime=VALUES(VisitTime)" );

         self::reset_players_tournament_new_count( $uid );
         self::delete_cache_tournament_visits( "TournamentVisit:mark_tournament_visited($uid,$tid)", $uid );
      }
   }//mark_tournament_visited

   /*!
    * \brief Checks if user(uid) has visited ("viewed") tournament-info-page for given tournament (tid).
    * \return TournamentDirector-object (with Flags set, but without Comment) for matching director;
    *         or null if user is not tournament-director
    */
   public static function has_visited_tournament( $dbgmsg, $uid, $tid )
   {
      $uid = (int)$uid;
      $tid = (int)$tid;
      $dbgmsg .= ".TournamentVisit:has_visited_tournament($uid,$tid)";
      $key = "TVisit.$uid";

      $arr_tvisits = DgsCache::fetch( $dbgmsg, CACHE_GRP_TVISIT, $key );
      if ( is_null($arr_tvisits) )
      {
         $arr_tvisits = self::load_tournament_visits( $uid );
         DgsCache::store( $dbgmsg, CACHE_GRP_TVISIT, $key, $arr_tvisits, 7*SECS_PER_DAY );
      }

      return in_array( $tid, $arr_tvisits );
   }//has_visited_tournament

   /*! \brief Returns non-null array with tournament-IDs (tid) that user has visited. */
   public static function load_tournament_visits( $uid )
   {
      $uid = (int)$uid;
      $db_result = db_query( "TournamentVisit:load_tournament_visits($uid)",
         "SELECT tid FROM TournamentVisit WHERE uid=$uid" );

      $result = array();
      while ( $row = mysql_fetch_array($db_result) )
         $result[$row['tid']] = $row['tid'];
      mysql_free_result($db_result);

      return $result;
   }//load_tournament_visits

   public static function delete_cache_tournament_visits( $dbgmsg, $uid )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_TVISIT, "TVisit.$uid" );
   }

   /*! \brief Deletes TournamentVisit-entries for closed and deleted tournaments. */
   public static function cleanup_tournament_visits()
   {
      db_query( "TournamentVisit:cleanup_tournament_visits()",
         "DELETE TV FROM TournamentVisit AS TV INNER JOIN Tournament AS T ON T.ID=TV.tid " .
         "WHERE T.Status IN ('".TOURNEY_STATUS_CLOSED."','".TOURNEY_STATUS_DELETE."')" );

      // remove all cache-entries for cache-group
      DgsCache::cleanup_cache( CACHE_GRP_TVISIT, $GLOBALS['NOW'] + SECS_PER_HOUR );
   }//cleanup_tournament_visits

   /*!
    * \brief Sets Players.CountTourneyNew=-1 to force reload of tournament-new-count in main-menu.
    * \param $uid 0 = reset all players
    */
   public static function reset_players_tournament_new_count( $uid=0 )
   {
      $uid = (int)$uid;
      if ( $uid > 0 )
      {
         db_query( "TournamentVisit:reset_players_tournament_new_count.upd_single($uid)",
            "UPDATE Players SET CountTourneyNew=-1 WHERE ID=$uid LIMIT 1" );
      }
      else
      {
         // reset all players with access within last X+7 days (auto-reset on login after X days anyway)
         $days_reset = DAYS_RESET_COUNT_TOURNEY_NEW + 7;
         db_query( "TournamentVisit:reset_players_tournament_new_count.upd_all()",
            "UPDATE Players SET CountTourneyNew=-1 " .
            "WHERE Lastaccess > NOW() - INTERVAL $days_reset DAY AND CountTourneyNew >= 0" );
      }
   }

} // end of 'TournamentVisit'
?>
