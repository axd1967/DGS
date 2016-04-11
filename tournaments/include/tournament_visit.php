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

   /*!
    * \brief Inserts (or updates existing) TournamentVisit-entries for given user-id and tournament-id
    *       with TVTYPE_OPEN_INFO visit-type.
    */
   public static function mark_tournament_visited( $uid, $tid )
   {
      global $NOW;
      $uid = (int)$uid;
      $tid = (int)$tid;

      if ( self::get_tournament_visit_type("TVisit.mark_tournament_visited", $uid, $tid) != TVTYPE_OPEN_INFO )
      {
         db_query( "TournamentVisit:mark_tournament_visited.insert($uid,$tid)",
            "INSERT INTO TournamentVisit (uid,tid,VisitTime,VisitType) " .
            "VALUES ($uid,$tid,FROM_UNIXTIME($NOW),".TVTYPE_OPEN_INFO.") " .
            "ON DUPLICATE KEY UPDATE VisitTime=VALUES(VisitTime), VisitType=VALUES(VisitType)" );

         self::reset_players_tournament_new_count( $uid, 1 );
         self::delete_cache_tournament_visits( "TournamentVisit:mark_tournament_visited($uid,$tid)", $uid );
      }
   }//mark_tournament_visited

   /*!
    * \brief Inserts TournamentVisit-entries for given user-id and list of tournament-IDs
    *       with TVTYPE_MARK_READ visit-type.
    * \note Existing entries with TVTYPE_OPEN_INFO will not be overwritten (as they are more "important").
    */
   public static function mark_tournaments_read( $uid, $arr_tids )
   {
      global $NOW;
      $uid = (int)$uid;

      if ( !is_array($arr_tids) || count($arr_tids) == 0 )
         return;

      $v_arr = array();
      foreach( $arr_tids as $tid )
         $v_arr[] = "($uid,$tid,FROM_UNIXTIME($NOW),".TVTYPE_MARK_READ.")";

      // Existing entries can only be of visit-type TVTYPE_MARK_READ or TVTYPE_OPEN_INFO, so in
      // both cases the entry should not be overwritten (TVTYPE_MARK_READ is already marked-as-read,
      // and TVTYPE_OPEN_INFO has higher precedence).  Therfore use INSERT-IGNORE, that ignores
      // duplicate entries for unique-key.
      db_query( "TournamentVisit:mark_tournaments_read.insert($uid,#".count($arr_tids).")",
         "INSERT IGNORE INTO TournamentVisit (uid,tid,VisitTime,VisitType) VALUES " .
         implode(', ', $v_arr) );

      self::reset_players_tournament_new_count( $uid, count($arr_tids) );
      self::delete_cache_tournament_visits( "TournamentVisit:mark_tournaments_read($uid)", $uid );
   }//mark_tournaments_read

   /*!
    * \brief Checks if user(uid) has visited ("viewed") tournament-info-page for given tournament (tid).
    * \return TVTYPE_... for specified user-id & tournament-ID; 0 = not visited or marked-as-read
    */
   private static function get_tournament_visit_type( $dbgmsg, $uid, $tid )
   {
      $uid = (int)$uid;
      $tid = (int)$tid;
      $dbgmsg .= ".TournamentVisit:get_tournament_visit_type($uid,$tid)";
      $key = "TVisit.$uid";

      $map_tvisits = DgsCache::fetch( $dbgmsg, CACHE_GRP_TVISIT, $key );
      if ( is_null($map_tvisits) )
      {
         $map_tvisits = self::load_tournament_visits( $uid );
         DgsCache::store( $dbgmsg, CACHE_GRP_TVISIT, $key, $map_tvisits, 7*SECS_PER_DAY );
      }

      return (isset($map_tvisits[$tid])) ? $map_tvisits[$tid] : 0;
   }//get_tournament_visit_type

   /*! \brief Returns non-null map( tid => tournament-visit-type) for tournament-IDs (tid) that user has visited. */
   public static function load_tournament_visits( $uid )
   {
      $uid = (int)$uid;
      $db_result = db_query( "TournamentVisit:load_tournament_visits($uid)",
         "SELECT tid, VisitType FROM TournamentVisit WHERE uid=$uid" );

      $result = array();
      while ( $row = mysql_fetch_array($db_result) )
         $result[$row['tid']] = $row['VisitType'];
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
    * \param $diff >0 = also update global $player_row['CountTourneyNew'] to reflect correct count
    *       in main-menu (only if $uid > 0)
    */
   public static function reset_players_tournament_new_count( $uid=0, $diff=0 )
   {
      global $player_row;

      $uid = (int)$uid;
      if ( $uid > 0 )
      {
         if ( $diff > 0 )
            $player_row['CountTourneyNew'] -= $diff;

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
   }//reset_players_tournament_new_count

} // end of 'TournamentVisit'
?>
