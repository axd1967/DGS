<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/std_functions.php';
require_once 'include/game_functions.php';
require_once 'include/message_functions.php';
require_once 'include/classlib_userquota.php';
require_once 'include/dgs_cache.php';
require_once 'include/db/bulletin.php';

$TheErrors->set_mode(ERROR_MODE_COLLECT);


if ( !$is_down )
{
   $daily_diff = SECS_PER_DAY;
   if ( $chained )
      $chained = $daily_diff;
   else
      connect2mysql();
   $daily_diff -= SECS_PER_HOUR;


   // Check that updates are not too frequent

   $row = mysql_single_fetch( 'daily_cron.check_frequency',
      "SELECT ($NOW-UNIX_TIMESTAMP(Lastchanged)) AS timediff"
      ." FROM Clock WHERE ID=".CLOCK_CRON_DAY." LIMIT 1" );
   if ( !$row )
      $TheErrors->dump_exit('daily_cron');
   if ( $row['timediff'] < $daily_diff )
      $TheErrors->dump_exit('daily_cron');

   db_query( 'daily_cron.set_lastchanged',
         "UPDATE Clock SET Ticks=1, Lastchanged=FROM_UNIXTIME($NOW) WHERE ID=".CLOCK_CRON_DAY." LIMIT 1" )
      or $TheErrors->dump_exit('daily_cron');

   // ---------- BEGIN ------------------------------


   $delete_waitingroom_entries = true;
   $waitingroom_timelimit = 30; //days

   if ( $delete_waitingroom_entries )
   {
      ta_begin();
      {//HOT-section to delete old waitingroom entries
         $wroom_query = "WHERE WR.Time < FROM_UNIXTIME($NOW) - INTERVAL $waitingroom_timelimit DAY";

         // delete old waitingroom entries for std-go game-types
         db_query( 'daily_cron.waitingroom.del_type_go',
            "DELETE WR FROM Waitingroom AS WR $wroom_query AND WR.GameType='".GAMETYPE_GO."'" );

         // Find old waitingroom entries for MP-games
         $result = db_query( 'daily_cron.waitingroom.find_mpg',
            "SELECT WR.ID, WR.gid, WR.nrGames, WR.GameType, WR.uid, WR.Comment, G.Status " .
            "FROM Waitingroom AS WR " .
               "INNER JOIN Games AS G ON G.ID=WR.gid " .
            "$wroom_query AND WR.gid>0 AND WR.nrGames>0 AND " .
               "WR.GameType<>'".GAMETYPE_GO."' AND G.Status='".GAME_STATUS_SETUP."'" );
         while ( $row = mysql_fetch_assoc($result) )
         {
            $wr_id = (int)$row['ID'];
            $gid = (int)$row['gid'];
            $nrGames = (int)$row['nrGames'];
            $master_uid = (int)$row['uid'];

            if ( MultiPlayerGame::revoke_offer_game_players($gid, $nrGames, GPFLAG_WAITINGROOM) )
            {
               db_query( "daily_cron.waitingroom.del_mpg($wr_id,$gid)",
                  "DELETE FROM Waitingroom WHERE ID=$wr_id AND GameType<>'".GAMETYPE_GO."' LIMIT 1" );

               // notify game-master about deletion
               send_message( "daily_cron.waitingroom.del_mpg.notify_master($wr_id,$gid,$master_uid)",
                  sprintf( T_('The daily CRON deleted your waiting-room entry older than %s days '
                              . 'with %s reserved slots for the multi-player-game %s.#mpg'),
                           $waitingroom_timelimit, $nrGames, "<game $gid>" ) .
                  "\n\n" .
                  T_('Comment') . ': ' . @$row['Comment'],
                  'Waiting-room entries for multi-player-game removed',
                  $master_uid, '', /*notify*/true );
            }
         }
         mysql_free_result($result);

         // delete WaitingroomJoined entries without Waitingroom-entry
         db_query( 'daily_cron.waitingroom_joined',
            "DELETE WRJ FROM WaitingroomJoined AS WRJ " .
               "LEFT JOIN Waitingroom AS WR ON WR.ID=WRJ.wroom_id WHERE WR.ID IS NULL" );
      }
      ta_end();
   }




// Update statistics

/*
TODO(better stats): add infos from mysql_stat() which returns an array:
[0] => Uptime: 5380
[1] => Threads: 2
[2] => Questions: 1321299
[3] => Slow queries: 0
[4] => Opens: 26
[5] => Flush tables: 1
[6] => Open tables: 17
[7] => Queries per second avg: 245.595
 (or its equivalent with SHOW STATUS)
and maybe send an email to admin(s)? in case of daily_delta too high
SELECT * FROM Statistics ORDER BY ID DESC LIMIT 2
if num_rows==2 {compute differences and checks}
*/
   $today_stats= array();
   foreach ( array(
      'finished' =>
         "SELECT SUM(Moves) AS MovesFinished, COUNT(*) AS GamesFinished FROM Games WHERE Status='".GAME_STATUS_FINISHED."'",
      'running' =>
         "SELECT SUM(Moves) AS MovesRunning, COUNT(*) AS GamesRunning FROM Games WHERE Status".IS_STARTED_GAME,
      'users' =>
         "SELECT SUM(Hits) AS Hits, COUNT(*) AS Users, SUM(Activity)/$ActivityForHit AS Activity FROM Players",
      ) as $key => $query )
   {
      $row = mysql_single_fetch( "daily_cron.statistics.$key", $query);
      if ( $row )
         $today_stats = array_merge( $today_stats, $row);
   }

   $row = array(
      "Moves=({$today_stats['MovesFinished']}+{$today_stats['MovesRunning']})",
      "Games=({$today_stats['GamesFinished']}+{$today_stats['GamesRunning']})",
      "Time=FROM_UNIXTIME($NOW)",
      );
   foreach ( $today_stats as $key => $val )
      $row[] = "$key=$val";
   $query = implode(',', $row);

   db_query( 'daily_cron.statistics_insert',
      "INSERT INTO Statistics SET $query" );

   DgsCache::delete_group( 'daily_cron.statistics', CACHE_GRP_STATS_GAMES, 'Statistics.games' );



// Delete old Forumreads: use-case A08 (cleanup) from specs/forums.txt

   $min_date = $NOW - FORUM_SECS_NEW_END;
   db_query( 'daily_cron.cleanup_forum_read.delete_old',
      "DELETE FROM Forumreads " .
      "WHERE Thread_ID>0 AND Time < FROM_UNIXTIME($min_date)" .
         " AND User_ID>0 AND Forum_ID>0" ); // secondary restrictions



// Apply recently changed night hours

   $result = db_query( 'daily_cron.night_hours',
      "SELECT ID, Timezone, Nightstart, ClockUsed FROM Players WHERE ClockChanged='Y'" );
   // NOTE: DST-adjustments are checked in include/std_functions.php is_logged_in()

   // NOTE: clock-used for games to move in MUST NOT be changed, because the clocks are not aligned.
   //       See also doc about 'Games.TimeOutDate' in 'specs/db/table-Games.txt'

   if ( @mysql_num_rows( $result) > 0 )
   {
      $otz= setTZ(); //reset to default
      while ( $row = mysql_fetch_assoc($result) )
      {
         setTZ( $row['Timezone']); //for get_clock_used()
         $newclock = get_clock_used($row['Nightstart']);
         $uid = $row['ID'];

         // NightStart may be unchanged, but we have to reset ClockChanged
         db_query( "daily_cron.night_hours.update($uid,$newclock)",
               "UPDATE Players SET ClockChanged='N',ClockUsed=$newclock WHERE ID=$uid LIMIT 1" )
            or error('mysql_query_failed', "daily_cron.night_hours.update2($uid,$newclock)");
      }
      setTZ($otz); //reset to previous
   }
   mysql_free_result($result);



// Increase feature-points

   UserQuota::increase_update_feature_points();



// Expire Bulletins

   Bulletin::process_expired_bulletins();



// Cleanup MoveStats

   $loctime = get_utc_timeinfo(); // need UTC to equalize all users on time-slot
   $old_week = floor( $loctime['tm_yday'] / 7 ) - 4; // keep 4 weeks
   if ( $old_week >= 0 )
      $where_clause = "SlotWeek <= $old_week";
   else
      $where_clause = "SlotWeek > " . (52 + $old_week);

   db_query( "daily_cron.movestats_cleanup.delete",
      "DELETE FROM MoveStats WHERE $where_clause" );



// Cleanup old invitations

   if ( (int)GAME_INVITATIONS_EXPIRE_MONTHS > 0 )
   {
      db_query( "daily_cron.cleanup_old_invitations.delete",
         "DELETE FROM Games " .
         "WHERE Status='".GAME_STATUS_INVITED."' AND " .
            "Lastchanged <= NOW() - INTERVAL ".GAME_INVITATIONS_EXPIRE_MONTHS." MONTH " .
         "LIMIT 100" );

      // delete GameInvitation-entries without Games-entry
      db_query( 'daily_cron.cleanup_old_invitations.game_inv',
         "DELETE GI FROM GameInvitation AS GI " .
            "LEFT JOIN Games AS G ON G.ID=GI.gid WHERE G.ID IS NULL" );

      fix_invitations_replied( 'daily_cron.cleanup_old_invitations', 100 );
   }



   // ---------- END --------------------------------

   db_query( 'daily_cron.reset_tick',
         "UPDATE Clock SET Ticks=0, Finished=FROM_UNIXTIME(".time().") WHERE ID=".CLOCK_CRON_DAY." LIMIT 1" );

   if ( !$chained )
      $TheErrors->dump_exit('daily_cron');

}//$is_down
?>
