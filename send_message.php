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

$TranslateGroups[] = "Messages";

require( "include/std_functions.php" );
require( "include/rating.php" );
require( "include/message_functions.php" );

disable_cache();

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");


   if( $player_row["Handle"] == "guest" )
      error("not_allowed_for_guest");


   $my_id = $player_row['ID'];
   $message_id = $_GET['messageid'];

   $folders = get_folders($my_id);
   $new_folder = $_GET['folder'];

   if( isset($_GET['foldermove'] and isset($new_folder) and isset($folders[$new_folder]) )
   {
      mysql_query( "UPDATE MessageCorrespondents SET Folder_nr='$newfolder' " .
                   "WHERE uid='$my_id' AND mid='$message_id' " .
                   "AND !( Type='INVITATION' and Replied='N' ) LIMIT 1" );

      jump_to("message.php?mid=$message_id");
   }


   if( $to == "guest" )
      error("guest_may_not_recieve_messages");

   if( $to == $player_row["Handle"] and $type == 'INVITATION' )
      error("invite_self");


// find reciever of the message

   $result = mysql_query( "SELECT ID, SendEmail, Notify, ClockUsed, Rating2, RatingStatus " .
                          "FROM Players WHERE Handle='$to'" );

   if( mysql_num_rows( $result ) != 1 )
      error("reciever_not_found");


   $opponent_row = mysql_fetch_array($result);
   $opponent_ID = $opponent_row["ID"];

// Check if dispute game exists
   if( $disputegid > 0 )
   {
      $result = mysql_query("SELECT ID FROM Games WHERE ID=$disputegid " .
                            "AND Status='INVITED' AND " .
                            "((Black_ID=$my_id AND White_ID=$opponent_ID) OR " .
                            "(Black_ID=$opponent_ID AND White_ID=$my_id))");

      if( mysql_num_rows($result) != 1 )
         error('unknown_game');
   }


// Update database

   if( !$type )
      $type = "NORMAL";

   if( $type == "INVITATION" )
   {
      if( $color == "White" )
      {
         $Black_ID = $opponent_ID;
         $White_ID = $my_id;
      }
      else
      {
         $White_ID = $opponent_ID;
         $Black_ID = $my_id;
      }

      if( ( $handicap_type == 'conv' or $handicap_type == 'proper' ) and
          ( !$player_row["RatingStatus"] or !$opponent_row["RatingStatus"] ) )
      {
         error( "no_initial_rating" );
      }

      $komi = $komi_m;
      $tomove = $Black_ID;
      if($handicap_type == 'conv' ) { $tomove = -1; $komi = 0; }
      else if($handicap_type == 'proper' ) { $tomove = -2; $komi = 0; }
      else if($handicap_type == 'nigiri' ) { $tomove = -3; $komi = $komi_n; }
      else if($handicap_type == 'double' ) { $tomove = -4; $komi = $komi_d; }

      if( !($komi <= 200 and $komi >= -200) )
         error("komi_range");

      interpret_time_limit_forms();

      if( $rated != 'Y' or $my_id == $opponent_ID )
         $rated = 'N';

      if( $weekendclock != 'Y' )
         $weekendclock = 'N';

      $query = "Black_ID=$Black_ID, " .
         "White_ID=$White_ID, " .
         "ToMove_ID=$tomove, " .
         "Lastchanged=FROM_UNIXTIME($NOW), " .
         "Size=$size, " .
         "Handicap=$handicap, " .
         "Komi=ROUND(2*($komi))/2, " .
         "Maintime=$hours, " .
         "Byotype='$byoyomitype', " .
         "Byotime=$byohours, " .
         "Byoperiods=$byoperiods, " .
         "Black_Maintime=$hours, " .
         "White_Maintime=$hours," .
         "WeekendClock='$weekendclock', " .
         "Rated='$rated'";

      if( $disputegid > 0 )
         $query = "UPDATE Games SET $query  WHERE ID=$disputegid LIMIT 1";
      else
         $query = "INSERT INTO Games SET $query";

      $result = mysql_query( $query )
         or error("mysql_insert_game");

      if( mysql_affected_rows() != 1)
         error("mysql_insert_game");

      if( $disputegid > 0 )
      {
         $gid = $disputegid;
         $subject = "Game invitation dispute";
      }
      else
      {
         $gid = mysql_insert_id();
         $subject = "Game invitation";
      }
   }
   else if( $accepttype )
   {
      $result = mysql_query( "SELECT Black_ID, White_ID, ToMove_ID, " .
                             "Size, Handicap, Komi, " .
                             "Maintime, Byotype, Byotime, Byoperiods, " .
                             "Rated, WeekendClock " .
                             "FROM Games WHERE ID=$gid" );

      if( mysql_num_rows($result) != 1)
         error("mysql_start_game");


      $game_row = mysql_fetch_array($result);
      if( $opponent_ID == $game_row["Black_ID"] )
      {
         $clock_used_black = $opponent_row["ClockUsed"];
         $clock_used_white = $player_row["ClockUsed"];
         $rating_black = $opponent_row["Rating2"];
         $rating_white = $player_row["Rating2"];
      }
      else if( $my_id == $game_row["Black_ID"] )
      {
         $clock_used_white = $opponent_row["ClockUsed"];
         $clock_used_black = $player_row["ClockUsed"];
         $rating_white = $opponent_row["Rating2"];
         $rating_black = $player_row["Rating2"];
      }
      else
      {
         error("mysql_start_game");
      }

      if( $weekendclock != 'Y' )
      {
         $clock_used_white += 100;
         $clock_used_black += 100;
      }

      $ticks_black = get_clock_ticks($clock_used_black);
      $ticks_white = get_clock_ticks($clock_used_white);

      $handitype = $game_row["ToMove_ID"];

      mt_srand ((double) microtime() * 1000000);
      if( $handitype == -3 ) // nigiri
         $swap = mt_rand(0,1);


      $query =  "UPDATE Games SET " .
         "Status='PLAY', ";


      if( $handitype == -2 ) // Proper handi
      {
            list($handicap,$komi,$swap) =
               suggest_proper($rating_white, $rating_black, $game_row["Size"]);

         $query .= "Handicap=$handicap, Komi=$komi, ";
      }

      if( $handitype == -1 ) // Conventional handi
      {
         list($handicap,$komi,$swap) =
            suggest_conventional($rating_white, $rating_black, $game_row["Size"]);

         $query .= "Handicap=$handicap, Komi=$komi, ";
      }

      $Rated = ( $game_row['Rated'] === 'Y' and
                 !empty($opponent_row['RatingStatus']) and
                 !empty($player_row['RatingStatus']) );

      if( !$Rated and $game_row['Rated'] === 'Y' )
         $query .= "Rated='N', ";

      if( $swap )
         $query .= "Black_ID=" . $game_row["White_ID"] . ", " .
            "White_ID=" . $game_row["Black_ID"] . ", " .
            "ToMove_ID=" . $game_row["White_ID"] . ", " .
            (is_numeric($rating_black) ? "White_Start_Rating=$rating_black, " : '' ) .
            (is_numeric($rating_white) ? "Black_Start_Rating=$rating_white, " : '' ) .
            "ClockUsed=$clock_used_white, " .
            "LastTicks=$ticks_white, ";
      else
         $query .= "ToMove_ID=Black_ID, " .
            (is_numeric($rating_black) ? "Black_Start_Rating=$rating_black, " : '' ) .
            (is_numeric($rating_white) ? "White_Start_Rating=$rating_white, " : '' ) .
            "ClockUsed=$clock_used_black, " .
            "LastTicks=$ticks_black, ";

      $query .= "Starttime=FROM_UNIXTIME($NOW), " .
         "Lastchanged=FROM_UNIXTIME($NOW) " .
         "WHERE ID=$gid AND Status='INVITED' " .
         "AND ( Black_ID=$my_id OR White_ID=$my_id ) " .
         "AND ( Black_ID=$opponent_ID OR White_ID=$opponent_ID ) " .
         "LIMIT 1";

      $result = mysql_query( $query );

      if( mysql_affected_rows() != 1)
         error("mysql_start_game");

      if( $handitype == -4 ) // double
      {
         $query = "INSERT INTO Games SET " .
            "Black_ID=" . $game_row["White_ID"] . ", " .
            "White_ID=" . $game_row["Black_ID"] . ", " .
            "ToMove_ID=" . $game_row["White_ID"] . ", " .
            "Status='PLAY', " .
            "ClockUsed=$clock_used_white, " .
            "LastTicks=$ticks_white, " .
            "Lastchanged=FROM_UNIXTIME($NOW), " .
            "Starttime=FROM_UNIXTIME($NOW), " .
            "Size=" . $game_row["Size"] . ", " .
            "Handicap=" . $game_row["Handicap"] . ", " .
            "Komi=" . $game_row["Komi"] . ", " .
            "Maintime=" . $game_row["Maintime"] . ", " .
            "Byotype='" . $game_row["Byotype"] . "', " .
            "Byotime=" . $game_row["Byotime"] . ", " .
            "Byoperiods=" . $game_row["Byoperiods"] . ", " .
            "Black_Maintime=" . $game_row["Maintime"] . ", " .
            "White_Maintime=" . $game_row["Maintime"] . "," .
            (is_numeric($rating_black) ? "Black_Start_Rating=$rating_black, " : '' ) .
            (is_numeric($rating_white) ? "White_Start_Rating=$rating_white, " : '' ) .
            "WeekendClock='" . $game_row["WeekendClock"] . "', " .
            "Rated='" . ($Rated ? 'Y' : 'N' ) . "'";

         mysql_query( $query )
            or error("mysql_query_failed");

      }

      mysql_query( "UPDATE Players SET Running=Running+" . ( $handitype == -4 ? 2 : 1 ) .
                   ( $Rated ? ", RatingStatus='RATED'" : '' ) .
                   " WHERE ID=$my_id OR ID=$opponent_ID LIMIT 2" );

      $subject = "Game invitation accepted";
   }
   else if( $declinetype )
   {
      $result = mysql_query( "DELETE FROM Games WHERE ID=$gid AND Status='INVITED'" .
                             " AND ( Black_ID=$my_id OR White_ID=$my_id ) " .
                             " AND ( Black_ID=$opponent_ID OR White_ID=$opponent_ID ) " .
                             "LIMIT 1");

      if( mysql_affected_rows() != 1)
      {
         error("mysql_delete_game_invitation");
         exit;
      }

      $subject = "Game invitation decline";
   }



// Update database

   $query = "INSERT INTO Messages SET " .
       "From_ID=$my_id, " .
       "To_ID=$opponent_ID, " .
       "Time=FROM_UNIXTIME($NOW), " .
       "Type='$type', ";

   if( $type == 'INVITATION' )
      $query .= "Game_ID=$gid, ";

   if( $reply > 0 )
      $query .= "ReplyTo=$reply, ";

   $message = trim($message);
   $query .= "Subject=\"$subject\", Text=\"$message\"";

   $result = mysql_query( $query );
   if( mysql_affected_rows() != 1)
      error("mysql_insert_message",true);

   $mid = mysql_insert_id();
   $query = "INSERT INTO MessageCorrespondents (uid,mid,Sender,Folder_nr) VALUES " .
      "($my_id, $mid, 'Y', 5), " .
      "($opponent_ID, $mid, 'N', 2)";

   $result = mysql_query( $query );
   if( mysql_affected_rows() != 2)
      error("mysql_insert_message",true);

   if( $type == "INVITATION" )
      mysql_query( "UPDATE Games SET mid='$mid' WHERE ID='$gid' LIMIT 1" );

   if( $reply > 0 )
   {
      $query = "UPDATE MessageCorrespondents SET Replied='Y'";

      if( $accepttype or $declinetype or
          (isset($new_folder) and isset($folders[$new_folder])) )
      {
         if( !isset($new_folder) or !isset($folders[$new_folder]) )
            $new_folder = FOLDER_MAIN;
         $query .= ", Folder_nr='$new_folder'";
      }

      $query .= " WHERE mid=$reply AND Sender='N' AND Replied='N' AND uid=$my_id LIMIT 1";

      mysql_query( $query ) or die(mysql_error());

      if( $disputegid > 0 )
         mysql_query( "UPDATE Messages SET Type='DISPUTED' " .
                      "WHERE ID=$reply AND To_ID=$my_id LIMIT 1");
   }


// Notify reciever about message

   if( !(strpos($opponent_row["SendEmail"], 'ON') === false)
       and $opponent_row["Notify"] == 'NONE' )
   {
      $result = mysql_query( "UPDATE Players SET Notify='NEXT' WHERE Handle='$to' LIMIT 1" );
   }

   $msg = urlencode(T_('Message sent!'));

   jump_to("status.php?msg=$msg");
}
?>
