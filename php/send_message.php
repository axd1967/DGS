<?php
/*
Dragon Go Server
Copyright (C) 2001  Erik Ouchterlony

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

require( "include/std_functions.php" );
disable_cache();

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");


   if( $player_row["Handle"] == "guest" )
      error("not_allowed_for_guest");

   if( $to == "guest" )
      error("guest_may_not_recieve_messages");



// find reciever of the message

   $result = mysql_query( "SELECT ID, Flags+0 AS flags, Notify, ClockUsed " . 
                          "FROM Players WHERE Handle='$to'" );

   if( mysql_num_rows( $result ) != 1 )
      error("reciever_not_found");


   $opponent_row = mysql_fetch_array($result);
   $opponent_ID = $opponent_row["ID"];
   $my_ID = $player_row["ID"];

   if( $my_ID == $opponent_ID )
      error("reciver_self");



// Update database

   if( !$type )
      $type = "NORMAL";

   if( $type == "INVITATION" )
   {
      if( $komi > 200 or $komi < -200 )
         error("komi_range");

      $type = "INVITATION";
      if( $color == "White" )
      {
         $Black_ID = $opponent_ID;
         $White_ID = $my_ID;
      }
      else
      {
         $White_ID = $opponent_ID;
         $Black_ID = $my_ID;
      }

      $hours = $timevalue;
      if( $timeunit != 'hours' )
         $hours *= 15;
      if( $timeunit == 'months' )
         $hours *= 30;

      if( $byoyomitype == 'JAP' )
      {
         $byohours = $byotimevalue_jap;
         if( $timeunit_jap != 'hours' )
            $byohours *= 15;
         if( $timeunit_jap == 'months' )
            $byohours *= 30;

         $byoperiods = $byoperiods_jap;
      }
      else if( $byoyomitype == 'CAN' )
      {
         $byohours = $byotimevalue_can;
         if( $timeunit_can != 'hours' )
            $byohours *= 15;
         if( $timeunit_can == 'months' )
            $byohours *= 30;
            
         $byoperiods = $byostones_can;
      }
      else if( $byoyomitype == 'FIS' )
      {
         $byohours = $byotimevalue_fis;
         if( $timeunit_fis != 'hours' )
            $byohours *= 15;
         if( $timeunit_fis == 'months' )
            $byohours *= 30;
            
         $byoperiods = 0;
      }

      if( $rated != 'Y' )
         $rated = 'N';

      $result = mysql_query( "INSERT INTO Games SET " .
                             "Black_ID=$Black_ID, " .
                             "White_ID=$White_ID, " .
                             "ToMove_ID=$Black_ID, " .
                             "Size=$size, " .
                             "Handicap=$handicap, " .
                             "Komi=$komi, " .
                             "Maintime=$hours, " .
                             "Byotype='$byoyomitype', " .
                             "Byotime=$byohours, " .
                             "Byoperiods=$byoperiods, " .
                             "Black_Maintime=$hours, " .
                             "White_Maintime=$hours," .
                             "Rated='$rated'" 
         );

      if( mysql_affected_rows() != 1)
         error("mysql_insert_game");


      $gid = mysql_insert_id();
      $subject = "Game invitation";
   }
   else if( $type == "Accept" )
   {
      $result = mysql_query( "SELECT Black_ID, White_ID FROM Games WHERE ID=$gid" );

      if( mysql_num_rows($result) != 1)
         error("mysql_start_game");
    
    
      $game_row = mysql_fetch_array($result);
      if( $opponent_ID == $game_row["Black_ID"] )
      {
         $clock_used = $opponent_row["ClockUsed"];
      }
      else if( $my_ID == $game_row["Black_ID"] )
      {
         $clock_used = $player_row["ClockUsed"];
      }
      else
      {
         error("mysql_start_game");
      }
        
    
      $ticks = get_clock_ticks($clock_used);

      $result = mysql_query( "UPDATE Games SET " .
                             "Status='PLAY', " .
                             "Starttime=NOW(), " .
                             "ClockUsed=$clock_used, " .
                             "LastTicks=$ticks " .
                             "WHERE ID=$gid AND Status='INVITED'" .
                             " AND ( Black_ID=$my_ID OR White_ID=$my_ID ) " .
                             " AND ( Black_ID=$opponent_ID OR White_ID=$opponent_ID ) " );

      if( mysql_affected_rows() != 1)
         error("mysql_start_game");


      $result = mysql_query( "CREATE TABLE Moves$gid (" .
                             "ID SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, " .
                             "MoveNr SMALLINT UNSIGNED, " .
                             "Stone SMALLINT UNSIGNED NOT NULL, " .
                             "PosX SMALLINT, " .
                             "PosY SMALLINT, " .
                             "Hours SMALLINT UNSIGNED NOT NULL, " .
                             "Text TEXT )" );

      $subject = "Game invitation accepted";
   }
   else if( $type == "Decline" )
   {
      $result = mysql_query( "DELETE FROM Games WHERE ID=$gid AND Status='INVITED'" .
                             " AND ( Black_ID=$my_ID OR White_ID=$my_ID ) " .
                             " AND ( Black_ID=$opponent_ID OR White_ID=$opponent_ID ) " );

      if( mysql_affected_rows() != 1)
      {
         error("mysql_delete_game_invitation");
         exit;
      }

      $subject = "Game invitation decline";
   }



// Update database

   $query = "INSERT INTO Messages SET " .
       "From_ID=$my_ID, " .
       "To_ID=$opponent_ID, " .
       "Type='$type', ";

   if( $type == 'INVITATION' )
   {
      $query .= "Game_ID=$gid, " .
          "Flags='NEW,REPLY REQUIRED', ";
   }

   if( $reply )
      $query .= "ReplyTo=$reply, ";

   $message = trim($message);
   $query .= "Subject=\"$subject\", Text=\"$message\"";

   $result = mysql_query( $query );

   if( mysql_affected_rows() != 1)
      error("mysql_insert_message",true);


   if( $reply )
   {
      mysql_query( "UPDATE Messages SET Flags='REPLIED', Time=Time " .
                   "WHERE  ID=$reply AND To_ID=$my_ID" );
   }


// Notify reciever about message

   if( $opponent_row["flags"] & WANT_EMAIL and $opponent_row["Notify"] == 'NONE' )
   {
      $result = mysql_query( "UPDATE Players SET Notify='NEXT', Lastaccess=Lastaccess " .
                             "WHERE Handle='$to'" );
   }

   $msg = urlencode("Message sent!");

   header("Location: status.php?msg=$msg");
   exit;
}
?>
