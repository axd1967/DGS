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


require_once( "include/std_functions.php" );
//require_once( "include/rating.php" );

if( !$is_down )
{
   connect2mysql();


   // Check that updates are not too frequent

   $result = mysql_query( "SELECT ($NOW-UNIX_TIMESTAMP(Lastchanged)) AS timediff " .
                          "FROM Clock WHERE ID=203 LIMIT 1");

   $row = mysql_fetch_array( $result );

   if( $row['timediff'] < 3600*23 )
      exit;

   mysql_query("UPDATE Clock SET Lastchanged=FROM_UNIXTIME($NOW) WHERE ID=203");

   $delete_msgs = false;
   $delete_invitations = false;
   $delete_waitingroom_entries = true;
   $message_timelimit = 90; //days
   $invite_timelimit = 60; //days
   $waitingroom_timelimit = 30; //days

// Delete old messages

/* to be reviewed: the field *Flags* doesn't exist.
   if( $delete_msgs )
   {
      mysql_query("UPDATE Messages " .
                  "SET Flags=CONCAT_WS(',',Flags,'DELETED') " .
                  "WHERE $NOW-UNIX_TIMESTAMP(Time) > " . ($message_timelimit*24*3600) .
                  " AND NOT ( Flags LIKE '%NEW%' OR Flags LIKE '%REPLY REQUIRED%' )");
   }
*/


// Delete old invitations

   if( $delete_invitations )
   {
      $timelimit = $invite_timelimit*24*3600;
      $query = "SELECT Messages.ID as mid, Game_ID " .
         "FROM Messages, Games " .
         "WHERE Game.ID=Messages.Game_ID AND Games.Status='INVITED' " .
         "AND Messages.Type='INVITATION' AND $NOW-UNIX_TIMESTAMP(Time) > $timelimit " .
         "AND $NOW-UNIX_TIMESTAMP(Lastchanged) > $timelimit";

      mysql_query( $query );

      if( mysql_num_rows($result) > 0 )
      {
         while( $row = mysql_fetch_array( $result ) )
         {
            mysql_query( "DELETE FROM Games WHERE ID=" . $row["Game_ID"] .
                         " AND Status='INVITED' LIMIT 1" );

            mysql_query( "UPDATE Messages SET Type='DELETED' " .
                         "WHERE ID=" . $row['mid'] . " LIMIT 1");
         }
      }
   }


// Delete old waiting list entries

   if( $delete_waitingroom_entries )
   {
      $timelimit = $waitingroom_timelimit*24*3600;
      $query = "DELETE FROM Waitingroom " .
         "WHERE $NOW-UNIX_TIMESTAMP(Time) > $timelimit";

      mysql_query( $query );
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

//    while( $row = mysql_fetch_array( $result ) )
//    {
//       update_rating($row["gid"]);
//       update_rating2($row["gid"]);
//    }

//    $result = mysql_query( "UPDATE Players SET RatingStatus='READY' " .
//                           "WHERE RatingStatus='INIT' " );




// Update statistics

   $q_finished = "SELECT SUM(Moves) as MovesFinished, COUNT(*) as GamesFinished FROM Games " .
       "WHERE Status='FINISHED'";
   $q_running = "SELECT SUM(Moves) as MovesRunning, COUNT(*) as GamesRunning FROM Games " .
       "WHERE Status!='FINISHED' AND Status!='INVITED'";
   $q_users = "SELECT SUM(Hits) as Hits, Count(*) as Users, SUM(Activity) as Activity FROM Players";

   extract( mysql_fetch_array(mysql_query( $q_finished )));
   extract( mysql_fetch_array(mysql_query( $q_running )));
   extract( mysql_fetch_array(mysql_query( $q_users )));

   mysql_query( "INSERT INTO Statistics SET " .
                "Time=FROM_UNIXTIME($NOW), " .
                "Hits=$Hits, " .
                "Users=$Users, " .
                "Moves=" . ($MovesFinished+$MovesRunning) . ", " .
                "MovesFinished=$MovesFinished, " .
                "MovesRunning=$MovesRunning, " .
                "Games=" . ($GamesRunning+$GamesFinished) . ", " .
                "GamesFinished=$GamesFinished, " .
                "GamesRunning=$GamesRunning, " .
                "Activity=$Activity" );



// Delete old forumreads

   $result = mysql_query("SELECT ID FROM Posts " .
                         "WHERE Depth=1 " .
                         "AND UNIX_TIMESTAMP(Lastchanged) + $new_end < $NOW " .
                         "AND UNIX_TIMESTAMP(Lastchanged) + $new_end + 7*24*3600 > $NOW");

   $query = "DELETE FROM Forumreads WHERE UNIX_TIMESTAMP(Time) + $new_end < $NOW";

   while( $row = mysql_fetch_array($result) )
   {
      $query .= " OR Thread_ID=" . $row["ID"];
   }

   mysql_query( $query );




// Apply recently changed night hours

   $result = mysql_query("SELECT ID, Nightstart, ClockUsed, Timezone " .
                         "FROM Players WHERE ClockChanged='Y' OR ID=1 ORDER BY ID");

   $row = mysql_fetch_array($result);
   putenv('TZ='.$row["Timezone"] );

   // Changed to/from summertime?
   if( $row['ClockUsed'] !== get_clock_used($row['Nightstart']) )
      $result =  mysql_query("SELECT ID, Nightstart, ClockUsed, Timezone FROM Players");

   while( $row = mysql_fetch_array($result) )
   {
      putenv('TZ='.$row["Timezone"] );
      mysql_query("UPDATE Players " .
                  "SET ClockChanged=NULL, " .
                  "ClockUsed='" . get_clock_used($row['Nightstart']) . "' " .
                  "WHERE ID='" . $row['ID'] . "' LIMIT 1");
   }
}
?>