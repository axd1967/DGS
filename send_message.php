<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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
require( "include/rating.php" );

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

   if( $to == $player_row["Handle"] and $type == 'INVITATION' )
      error("invite_self");


// find reciever of the message

   $result = mysql_query( "SELECT ID, SendEmail, Notify, ClockUsed, RatingStatus " .
                          "FROM Players WHERE Handle='$to'" );

   if( mysql_num_rows( $result ) != 1 )
      error("reciever_not_found");


   $opponent_row = mysql_fetch_array($result);
   $opponent_ID = $opponent_row["ID"];
   $my_ID = $player_row["ID"];

// Check if dispute game exists
   if( $disputegid > 0 )
   {
      $result = mysql_query("SELECT ID FROM Games WHERE ID=$disputegid " .
                            "AND Status='INVITED' AND " .
                            "((Black_ID=$my_ID AND White_ID=$opponent_ID) OR " .
                            "(Black_ID=$opponent_ID AND White_ID=$my_ID))");

      if( mysql_num_rows($result) != 1 )
         error('unknown_game');
   }


// Update database

   if( !$type )
      $type = "NORMAL";

   if( $type == "INVITATION" )
   {
      if( $komi > 200 or $komi < -200 )
         error("komi_range");

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

      if( ( $handicap_type == 'conv' or $handicap_type == 'proper' ) and
          ( !$player_row["RatingStatus"] or !$opponent_row["RatingStatus"] ) )
      {
         error( "no_initial_rating" );
      }

      $tomove = $Black_ID;
      if($handicap_type == 'conv' ) $tomove = -1;
      else if($handicap_type == 'proper' ) $tomove = -2;
      else if($handicap_type == 'nigiri' ) $tomove = -3;
      else if($handicap_type == 'double' ) $tomove = -4;

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

         $byoperiods = $byoperiods_can;
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

      if( $rated != 'Y' or $my_ID == $opponent_ID )
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

      $result = mysql_query( $query );

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
         $rating_black = $opponent_row["Rating"];
         $rating_white = $player_row["Rating"];
      }
      else if( $my_ID == $game_row["Black_ID"] )
      {
         $clock_used_white = $opponent_row["ClockUsed"];
         $clock_used_black = $player_row["ClockUsed"];
         $rating_black = $opponent_row["Rating"];
         $rating_white = $player_row["Rating"];
      }
      else
      {
         error("mysql_start_game");
      }

      if( $weekendclock != 'Y' )
         $clock_used += 100;

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
         if( $rating_black > $rating_white )
         {
            $swap = true;
            list($handicap,$komi) = suggest($rating_black, $rating_white, $game_row["Size"]);
         }
         else
            list($handicap,$komi) = suggest($rating_white, $rating_black, $game_row["Size"]);

         $query .= "Handicap=$handicap, Komi=$komi, ";
      }

      if( $handitype == -1 ) // Conventional handi
      {
         $handicap = floor(abs($rating_black-$rating_white)/100);
         $komi = 0.5;

         if( $handicap == 0 )
         {
            $komi = 6.5;
            $swap = mt_rand(0,1);
         }

         if( $handicap < 0 )
         {
            $swap = true;
            $handicap = -$handicap;
         }

         if( $handicap == 1 )
            $handicap = 0;

         $query .= "Handicap=$handicap, Komi=$komi, ";
      }


      if( $swap )
         $query .= "Black_ID=White_ID, " .
            "White_ID=Black_ID, " .
            "ToMove_ID=White_ID, " .
            "ClockUsed=$clock_used_white, " .
            "LastTicks=$ticks_white, ";
      else
         $query .= "ToMove_ID=Black_ID, " .
            "ClockUsed=$clock_used_black, " .
            "LastTicks=$ticks_black, ";

      $query .= "Starttime=FROM_UNIXTIME($NOW), " .
         "Lastchanged=FROM_UNIXTIME($NOW) " .
         "WHERE ID=$gid AND Status='INVITED' " .
         "AND ( Black_ID=$my_ID OR White_ID=$my_ID ) " .
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
            "Size=" . $game_row["Size"] . ", " .
            "Handicap=" . $game_row["Handicap"] . ", " .
            "Komi=" . $game_row["Komi"] . ", " .
            "Maintime=" . $game_row["Maintime"] . ", " .
            "Byotype='" . $game_row["Byotype"] . "', " .
            "Byotime=" . $game_row["Byotime"] . ", " .
            "Byoperiods=" . $game_row["Byoperiods"] . ", " .
            "Black_Maintime=" . $game_row["Maintime"] . ", " .
            "White_Maintime=" . $game_row["Maintime"] . "," .
            "WeekendClock='" . $game_row["WeekendClock"] . "', " .
            "Rated='" . $game_row["Rated"] . "'";

         mysql_query( $query )
            or error("mysql_query_failed");

      }

      mysql_query( "UPDATE Players SET Running=Running+" . ( $handitype == -4 ? 2 : 1 ) .
                   " WHERE ID=$my_ID OR ID=$opponent_ID LIMIT 2" );

      $subject = "Game invitation accepted";
   }
   else if( $declinetype )
   {
      $result = mysql_query( "DELETE FROM Games WHERE ID=$gid AND Status='INVITED'" .
                             " AND ( Black_ID=$my_ID OR White_ID=$my_ID ) " .
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
       "From_ID=$my_ID, " .
       "To_ID=$opponent_ID, " .
       "Time=FROM_UNIXTIME($NOW), " .
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
      mysql_query( "UPDATE Messages SET Flags='REPLIED' " .
                   "WHERE ID=$reply AND To_ID=$my_ID LIMIT 1" );
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
