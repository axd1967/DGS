<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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


require_once( "include/std_functions.php" );
require_once( "include/classlib_userquota.php" );

$TheErrors->set_mode(ERROR_MODE_COLLECT);


if( !$is_down )
{
   $daily_diff = 3600*24;
   if( $chained )
      $chained = $daily_diff;
   else
      connect2mysql();
   $daily_diff -= 3600;


   // Check that updates are not too frequent

   $row = mysql_single_fetch( 'daily_cron.check_frequency',
      "SELECT ($NOW-UNIX_TIMESTAMP(Lastchanged)) AS timediff"
      ." FROM Clock WHERE ID=203 LIMIT 1" );
   if( !$row )
      $TheErrors->dump_exit('daily_cron');
   if( $row['timediff'] < $daily_diff )
      $TheErrors->dump_exit('daily_cron');

   db_query( 'daily_cron.set_lastchanged',
         "UPDATE Clock SET Ticks=1, Lastchanged=FROM_UNIXTIME($NOW) WHERE ID=203 LIMIT 1" )
      or $TheErrors->dump_exit('daily_cron');

   // ---------- BEGIN ------------------------------


   $delete_waitingroom_entries = true;
   $waitingroom_timelimit = 30; //days

   if( $delete_waitingroom_entries )
   {
      // Delete old waitingroom list entries
      $timelimit = $NOW - $waitingroom_timelimit*24*3600;
      db_query( 'daily_cron.waitingroom',
         "DELETE FROM Waitingroom WHERE UNIX_TIMESTAMP(Time)<$timelimit" );

      // Delete WaitingroomJoined entries without Waitingroom-entry
      db_query( 'daily_cron.waitingroom_joined',
         "DELETE FROM WaitingroomJoined WHERE wroom_id NOT IN (SELECT ID FROM Waitingroom)" );
   }




// Update statistics

if(1){//new
/*
TODO: add infos from mysql_stat() which returns an array:
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
   foreach( array(
      'finished' =>
         "SELECT SUM(Moves) AS MovesFinished, COUNT(*) AS GamesFinished"
         ." FROM Games WHERE Status='FINISHED'",
      'running' =>
         "SELECT SUM(Moves) AS MovesRunning, COUNT(*) AS GamesRunning"
         ." FROM Games WHERE Status" . IS_RUNNING_GAME,
      'users' =>
         "SELECT SUM(Hits) AS Hits, Count(*) AS Users, SUM(Activity)/$ActivityForHit AS Activity"
         ." FROM Players",
      ) as $key => $query )
   {
      $row = mysql_single_fetch( "daily_cron.statistics.$key", $query);
      if( $row )
         $today_stats= array_merge( $today_stats, $row);
   }

   $row= array(
      "Moves=({$today_stats['MovesFinished']}+{$today_stats['MovesRunning']})",
      "Games=({$today_stats['GamesFinished']}+{$today_stats['GamesRunning']})",
      "Time=FROM_UNIXTIME($NOW)"
      );
   foreach( $today_stats as $key => $query )
   {
      $row[]= "$key=$query";
   }
   $query= implode(',', $row);

   db_query( 'daily_cron.statistics_insert',
      "INSERT INTO Statistics SET $query" );

}else{//old //TODO check! remove !?
   $q_finished = "SELECT SUM(Moves) as MovesFinished, COUNT(*) as GamesFinished FROM Games " .
       "WHERE Status='FINISHED'";
   $q_running = "SELECT SUM(Moves) as MovesRunning, COUNT(*) as GamesRunning FROM Games " .
       "WHERE Status" . IS_RUNNING_GAME;
   $q_users = "SELECT SUM(Hits) as Hits, Count(*) as Users, SUM(Activity)/$ActivityForHit as Activity FROM Players";

   $result = db_query( 'daily_cron.statistics_moves_finished', $q_finished );
   if( @mysql_num_rows($result) > 0 )
      extract( mysql_fetch_assoc($result));
   mysql_free_result($result);

   $result = db_query( 'daily_cron.statistics_moves_running', $q_running );

   if( @mysql_num_rows($result) > 0 )
      extract( mysql_fetch_assoc($result));
   mysql_free_result($result);

   $result = db_query( 'daily_cron.statistics_users', $q_users );

   if( @mysql_num_rows($result) > 0 )
      extract( mysql_fetch_assoc($result));
   mysql_free_result($result);

   db_query( 'daily_cron.statistics_insert',
      "INSERT INTO Statistics SET"
               ." Time=FROM_UNIXTIME($NOW)" //could become a Date= timestamp field
               .",Hits=" . (int)$Hits
               .",Users=" . (int)$Users
               .",Moves=" . (int)($MovesFinished+$MovesRunning)
               .",MovesFinished=" . (int)$MovesFinished
               .",MovesRunning=" . (int)$MovesRunning
               .",Games=" . (int)($GamesRunning+$GamesFinished)
               .",GamesFinished=" . (int)$GamesFinished
               .",GamesRunning=" . (int)$GamesRunning
               .",Activity=" . (int)$Activity );

}//new/old



// Delete old Forumreads: use-case A08 (cleanup) from specs/forums.txt

   $min_date = $NOW - FORUM_SECS_NEW_END;
   db_query( 'daily_cron.cleanup_forum_read.delete_old',
      "DELETE FROM Forumreads " .
      "WHERE Thread_ID>0 AND Time < FROM_UNIXTIME($min_date)" .
         " AND User_ID>0 AND Forum_ID>0" ); // secondary restrictions


// Apply recently changed night hours

if(1){//new
   $result = db_query( 'daily_cron.night_hours',
      "SELECT ID, Timezone, Nightstart, ClockUsed FROM Players WHERE ClockChanged='Y'" );
   // note: DST-adjustments are checked in include/std_functions.php is_logged_in()

   if( @mysql_num_rows( $result) > 0 )
   {
      $otz= setTZ(); //reset to default
      while( $row = mysql_fetch_assoc($result) )
      {
         setTZ( $row['Timezone']); //for get_clock_used()
         $newclock= get_clock_used( $row['Nightstart']);
         $uid = $row['ID'];
         db_query( "daily_cron.night_hours.update($uid)",
               "UPDATE Players SET ClockChanged='N',ClockUsed=$newclock"
               . " WHERE ID=$uid LIMIT 1" )
            or error('mysql_query_failed', "daily_cron.night_hours.update2($uid)");
      }
      setTZ($otz); //reset to previous
   }
   mysql_free_result($result);
}else{//older and bugged //TODO remove !?
   $result = db_query( 'daily_cron.night_hours',
      "SELECT ID, Nightstart, ClockUsed, Timezone " .
      "FROM Players WHERE ClockChanged='Y' OR ID=1 ORDER BY ID" );
   //$result always contains guest(first!) and the other ClockChanged='Y'

   if( @mysql_num_rows( $result) > 0 )
   {
      $row = mysql_fetch_assoc($result); //always "guest"
      setTZ( $row['Timezone']); //always GMT (guest default)

      // Changed to/from summertime?
      if( $row['ClockUsed'] !== get_clock_used($row['Nightstart']) )
      {
         //adjust the whole community (ClockChanged='Y' or not)
         $result = db_query( 'daily_cron.summertime_check',
            "SELECT ID, Nightstart, ClockUsed, Timezone FROM Players" );
      }

      while( $row = mysql_fetch_assoc($result) )
      {
         setTZ( $row['Timezone']);
         db_query( 'daily_cron.summertime_update',
            "UPDATE Players " .
                     "SET ClockChanged='N', " .
                     "ClockUsed='" . get_clock_used($row['Nightstart']) . "' " .
                     "WHERE ID='" . $row['ID'] . "' LIMIT 1" );
      }
   }
   mysql_free_result($result);
}//new/old



// Increase feature-points

   UserQuota::increase_update_feature_points();


   // ---------- END --------------------------------

   db_query( 'daily_cron.reset_tick',
         "UPDATE Clock SET Ticks=0 WHERE ID=203 LIMIT 1" );

   if( !$chained )
      $TheErrors->dump_exit('daily_cron');

}//$is_down
?>
