<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/


$quick_errors = 1; //just store errors in log database
require_once( "include/std_functions.php" );
//require_once( "include/rating.php" );

if( !$is_down )
{
   if( @$chained ) //when chained after halfhourly_cron.php
   {
      $i = 3600*24;
      $daily_diff = $i - $chained/2;
      $chained = $i;
   }
   else
   {
      $daily_diff = 3600*23;
      connect2mysql();
   }


   // Check that updates are not too frequent

   $result = mysql_query( "SELECT ($NOW-UNIX_TIMESTAMP(Lastchanged)) AS timediff " .
                          "FROM Clock WHERE ID=203 LIMIT 1")
               or error('mysql_query_failed','daily_cron1');

   $row = mysql_fetch_assoc( $result );
   mysql_free_result($result);

   if( $row['timediff'] < $daily_diff )
      //if( !@$_REQUEST['forced'] )
         exit;

   mysql_query("UPDATE Clock SET Lastchanged=FROM_UNIXTIME($NOW) WHERE ID=203")
               or error('mysql_query_failed','daily_cron2');

   //$delete_messages = false;
   //$delete_invitations = false;
   $delete_waitingroom_entries = true;
   //$message_timelimit = 90; //days
   //$invite_timelimit = 60; //days
   $waitingroom_timelimit = 30; //days


/* to be reviewed: the field *Flags* doesn't exist.
   if( $delete_messages )
   {
      // Delete old messages
      mysql_query("UPDATE Messages " .
                  "SET Flags=CONCAT_WS(',',Flags,'DELETED') " .
                  "WHERE $NOW-UNIX_TIMESTAMP(Time) > " . ($message_timelimit*24*3600) .
                  " AND NOT ( Flags LIKE '%NEW%' OR Flags LIKE '%REPLY REQUIRED%' )")
               or error('mysql_query_failed','daily_cron?');
   }
*/


/* to be reviewed: the field *Type* 'DELETED' no more used (replaced by Folder_nr = NULL).
   if( $delete_invitations )
   {
      // Delete old invitations
      $timelimit = $invite_timelimit*24*3600;
      $query = "SELECT Messages.ID as mid, Game_ID " .
         "FROM Messages, Games " .
         "WHERE Game.ID=Messages.Game_ID AND Games.Status='INVITED' " .
         "AND Messages.Type='INVITATION' AND $NOW-UNIX_TIMESTAMP(Time) > $timelimit " .
         "AND $NOW-UNIX_TIMESTAMP(Lastchanged) > $timelimit";

      $result = mysql_query( $query )
               or error('mysql_query_failed','daily_cron?');

      if( @mysql_num_rows($result) > 0 )
      {
         while( $row = mysql_fetch_assoc( $result ) )
         {
            mysql_query( "DELETE FROM Games WHERE ID=" . $row["Game_ID"] .
                         " AND Status='INVITED' LIMIT 1" )
               or error('mysql_query_failed','daily_cron?');

            mysql_query( "UPDATE Messages SET Type='DELETED' " .
                         "WHERE ID=" . $row['mid'] . " LIMIT 1")
               or error('mysql_query_failed','daily_cron?');
         }
      }
      mysql_free_result($result);
   }
*/


   if( $delete_waitingroom_entries )
   {
      // Delete old waitingroom list entries
      $timelimit = $NOW - $waitingroom_timelimit*24*3600;
      $query = "DELETE FROM Waitingroom " .
         "WHERE UNIX_TIMESTAMP(Time) < $timelimit";

      mysql_query( $query )
               or error('mysql_query_failed','daily_cron3');
   }




// Update rating


//    $query = "SELECT Games.ID as gid ".
//        "FROM Games, Players as white, Players as black " .
//        "WHERE Status='FINISHED' AND Rated='Y' " .
//        "AND white.ID=White_ID AND black.ID=Black_ID ".
//        "AND ( white.RatingStatus='READY' OR white.RatingStatus='RATED' ) " .
//        "AND ( black.RatingStatus='READY' OR black.RatingStatus='RATED' ) " .
//        "ORDER BY Lastchanged, gid";


//    $result = mysql_query( $query );

//    while( $row = mysql_fetch_assoc( $result ) )
//    {
//       update_rating($row["gid"]);
//       $rated_status = update_rating2($row["gid"]); //0=rated game
//    }
//    mysql_free_result($result);

//    $result = mysql_query( "UPDATE Players SET RatingStatus='READY' " .
//                           "WHERE RatingStatus='INIT' " );




// Update statistics

   $q_finished = "SELECT SUM(Moves) as MovesFinished, COUNT(*) as GamesFinished FROM Games " .
       "WHERE Status='FINISHED'";
   $q_running = "SELECT SUM(Moves) as MovesRunning, COUNT(*) as GamesRunning FROM Games " .
       "WHERE Status!='FINISHED' AND Status!='INVITED'";
   $q_users = "SELECT SUM(Hits) as Hits, Count(*) as Users, SUM(Activity) as Activity FROM Players";

   $result = mysql_query( $q_finished )
               or error('mysql_query_failed','daily_cron4');
   if( @mysql_num_rows($result) > 0 )
      extract( mysql_fetch_assoc($result));
   mysql_free_result($result);

   $result = mysql_query( $q_running )
               or error('mysql_query_failed','daily_cron5');
   if( @mysql_num_rows($result) > 0 )
      extract( mysql_fetch_assoc($result));
   mysql_free_result($result);

   $result = mysql_query( $q_users )
               or error('mysql_query_failed','daily_cron6');
   if( @mysql_num_rows($result) > 0 )
      extract( mysql_fetch_assoc($result));
   mysql_free_result($result);


   mysql_query( "INSERT INTO Statistics SET"
               ." Time=FROM_UNIXTIME($NOW)"
               .",Hits=" . (int)$Hits
               .",Users=" . (int)$Users
               .",Moves=" . (int)($MovesFinished+$MovesRunning)
               .",MovesFinished=" . (int)$MovesFinished
               .",MovesRunning=" . (int)$MovesRunning
               .",Games=" . (int)($GamesRunning+$GamesFinished)
               .",GamesFinished=" . (int)$GamesFinished
               .",GamesRunning=" . (int)$GamesRunning
               .",Activity=" . (int)$Activity )
               or error('mysql_query_failed','daily_cron7');



// Delete old forumreads


   mysql_query("DELETE FROM Forumreads WHERE UNIX_TIMESTAMP(Time) + $new_end < $NOW")
      or error('mysql_query_failed','daily_cron8');


// Apply recently changed night hours

   $result = mysql_query("SELECT ID, Nightstart, ClockUsed, Timezone " .
                         "FROM Players WHERE ClockChanged='Y' OR ID=1 ORDER BY ID")
               or error('mysql_query_failed','daily_cron10');

   if( @mysql_num_rows( $result) > 0 )
   {
      $row = mysql_fetch_assoc($result); //is "guest" and skipped else summertime changes
      setTZ( $row['Timezone']); //always GMT (guest default)

      // Changed to/from summertime?
      if( $row['ClockUsed'] !== get_clock_used($row['Nightstart']) )
         $result =  mysql_query("SELECT ID, Nightstart, ClockUsed, Timezone FROM Players")
                  or error('mysql_query_failed','daily_cron11');

      while( $row = mysql_fetch_assoc($result) )
      {
         setTZ( $row['Timezone']);
         mysql_query("UPDATE Players " .
                     "SET ClockChanged='N', " .
                     "ClockUsed='" . get_clock_used($row['Nightstart']) . "' " .
                     "WHERE ID='" . $row['ID'] . "' LIMIT 1")
                  or error('mysql_query_failed','daily_cron12');
      }
   }
   mysql_free_result($result);
}
?>