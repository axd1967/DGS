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
require_once( "include/board.php" );

{
   connect2mysql();


   // Check that updates are not too frequent

   $result = mysql_query( "SELECT ($NOW-UNIX_TIMESTAMP(Lastchanged)) AS timediff " .
                          "FROM Clock WHERE ID=202 LIMIT 1");

   $row = mysql_fetch_array( $result );

   if( $row['timediff'] < 1500 )
      exit;

   mysql_query("UPDATE Clock SET Lastchanged=FROM_UNIXTIME($NOW) WHERE ID=202");


// Send notifications


   $result = mysql_query( "SELECT ID as uid, Email, SendEmail, Lastaccess FROM Players " .
                          "WHERE SendEmail LIKE '%ON%' AND Notify='NOW'" );


   while( $row = mysql_fetch_array( $result ) )
   {
      extract($row);

      $msg = "A message or game move is waiting for you at $HOSTBASE/status.php\n";

      // Find games

      if( !(strpos($SendEmail, 'MOVE') === false) )
      {
         $query = "SELECT Games.*, " .
             "black.Name AS Blackname, " .
             "black.Handle AS Blackhandle, " .
             "white.Name AS Whitename, " .
             "white.Handle AS Whitehandle " .
             "FROM Games, Players AS black, Players AS white " .
             "WHERE ToMove_ID=$uid AND Black_ID=black.ID AND White_ID=white.ID" .
             " AND UNIX_TIMESTAMP(Lastchanged) > UNIX_TIMESTAMP('$Lastaccess')";

         $res2 = mysql_query( $query ) or die(mysql_error() . $query);

         if( mysql_num_rows($res2) > 0 )
         {
            $msg .= str_pad('', 47, '-') . "\n  Games:\n";

            while( $game_row = mysql_fetch_array( $res2 ) )
            {
               extract($game_row);

               $mess = NULL;
               make_array( $ID, $array, $mess, $Moves, NULL, $moves_result, $marked_dead );

               $msg .= str_pad('', 47, '-') . "\n";
               $msg .= "Game ID: $ID  ($HOSTBASE/game.php?gid=$ID)\n";
               $msg .= "Black: ".make_html_safe("$Blackname ($Blackhandle)")."\n";
               $msg .= "White: ".make_html_safe("$Whitename ($Whitehandle)")."\n";
               $msg .= "Move $Moves: " . number2board_coords($Last_X, $Last_Y, $Size) . "\n";

               if( !(strpos($SendEmail, 'BOARD') === false) )
                  $msg .= draw_ascii_board($Size, $array, $ID, $Last_X, $Last_Y, 15,
                                           make_html_safe($mess, 'game'));
            }
         }
      }


      // Find new messages

      if( !(strpos($SendEmail, 'MESSAGE') === false) )
      {
  $folderstring=FOLDER_NEW;
         $query = "SELECT Messages.*, " .
            "UNIX_TIMESTAMP(Messages.Time) AS date, " .
            "Players.Name AS FromName, Players.Handle AS FromHandle " .
            "FROM Messages, MessageCorrespondents AS me " .
            "LEFT JOIN MessageCorrespondents AS other " .
              "ON other.mid=me.mid AND other.Sender!=me.Sender " .
            "LEFT JOIN Players ON Players.ID=other.uid " .
            "WHERE me.uid=$uid AND Messages.ID=me.mid " .
              "AND me.Folder_nr IN ($folderstring) " .
              "AND me.Sender='N' " . //exclude message to myself
              "AND UNIX_TIMESTAMP(Messages.Time) > UNIX_TIMESTAMP('$Lastaccess') " .
            "ORDER BY Time DESC";


         $res3 = mysql_query( $query ) or die(mysql_error() . $query);
         if( mysql_num_rows($res3) > 0 )
         {
            $msg .= str_pad('', 47, '-') . "\n  New messages:\n";
            while( $msg_row = mysql_fetch_array( $res3 ) )
            {
               extract($msg_row);
               if($FromName && $FromHandle)
                  $From= make_html_safe("$FromName ($FromHandle)");
               else
                  $From= 'Server message';

               $msg .= str_pad('', 47, '-') . "\n" .
                   "Date: " . date($date_fmt, $date) . "\n" .
                   "From: $From\n" .
                   "Subject: " . make_html_safe($Subject) .
                   "  ($HOSTBASE/show_message.php?mid=$ID)\n\n" .
                   wordwrap(make_html_safe($Text),47) . "\n";
            }
         }
      }

      $msg .= str_pad('', 47, '-');

      mail( $Email, 'Dragon Go Server notification', $msg, "From: $EMAIL_FROM" );
   }


   //Setting Notify to 'DONE' stop notifications until the player's visite
   mysql_query( "UPDATE Players SET Notify='DONE' " .
                "WHERE SendEmail LIKE '%ON%' AND Notify='NOW' " );

   mysql_query( "UPDATE Players SET Notify='NOW' " .
                "WHERE SendEmail LIKE '%ON%' AND Notify='NEXT' " );



// Update activities

   $factor = exp( -M_LN2 * 30 / $ActivityHalvingTime );

   mysql_query("UPDATE Players SET Activity=Activity * $factor");


// Check end of vacations

   $result = mysql_query("SELECT ID, ClockUsed from Players " .
                         "WHERE OnVacation>0 AND OnVacation <= 1/(2*24)");

   while( $row = mysql_fetch_array( $result ) )
   {
      $uid = $row['ID'];
      $ClockUsed = $row['ClockUsed'];

      $res2 = mysql_query("SELECT Games.ID as gid, LastTicks+Clock.Ticks AS ticks " .
                          "FROM Games, Clock " .
                          "WHERE Clock.ID=$ClockUsed AND ToMove_ID='$uid' " .
                          "AND ClockUsed=-1 " .
                          "AND Status!='INVITED' AND Status!='FINISHED'")
      or die(mysql_error());

      while( $row2 = mysql_fetch_array( $res2 ) )
      {
         mysql_query("UPDATE Games SET ClockUsed=$ClockUsed, " .
                     "LastTicks='" . $row2['ticks'] . "' " .
                     "WHERE ID='" . $row2['gid'] . "' LIMIT 1");
      }
   }



// Change vacation days

   mysql_query("UPDATE Players SET " .
               "VacationDays=LEAST(365.24/12, VacationDays + 1/(12*2*24)), " .
               "OnVacation=GREATEST(0, OnVacation - 1/(2*24))");

}
?>