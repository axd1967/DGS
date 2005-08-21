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

require_once( "include/std_functions.php" );
require_once( "include/rating.php" );
require_once( "include/message_functions.php" );

disable_cache();


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");
   init_standard_folders();


   if( $player_row["Handle"] == "guest" )
      error("not_allowed_for_guest");


   $my_id = $player_row['ID'];
   $message_id = @$_REQUEST['foldermove_mid'];
   $disputegid = @$_REQUEST['disputegid'];
   $to = @$_REQUEST['to'];
   $reply = @$_REQUEST['reply']; //ID of message replied. if set then (often?always?) == $message_id
   $subject = get_request_arg('subject');
   $message = get_request_arg('message');
   $type = @$_REQUEST['type'];
   $gid = @$_REQUEST['gid'];
   $accepttype = @$_REQUEST['accepttype'];
   $declinetype = @$_REQUEST['declinetype'];

   $folders = get_folders($my_id);
   $new_folder = @$_REQUEST['folder'];
   $current_folder = @$_REQUEST['current_folder'];

   if( isset($_REQUEST['foldermove']) )
   {
      if( change_folders($my_id, $folders, array($message_id), $new_folder
            , $current_folder, $type == 'INVITATION') <= 0 )
      {
         $new_folder = ( $current_folder ? $current_folder : FOLDER_ALL_RECEIVED ) ;
      }

      $page = "";
      foreach( $_REQUEST as $key => $val )
      {
         if( $val == 'Y' && preg_match("/^mark\d+$/i", $key) )
           $page.= URI_AMP."$key=Y" ;
      }

      jump_to("list_messages.php?folder=$new_folder$page");
/*
      $msg = urlencode(T_('Message moved!'));

      jump_to("status.php?msg=$msg");
*/
   }


   if( $to == "guest" )
      error("guest_may_not_receive_messages");

   if( $to == $player_row["Handle"] and $type == 'INVITATION' )
      error("invite_self");


// find receiver of the message

   $result = mysql_query( "SELECT ID, SendEmail, Notify, ClockUsed, OnVacation, " .
                          "Rating2, RatingStatus " .
                          "FROM Players WHERE Handle='$to'" );

   if( @mysql_num_rows( $result ) != 1 )
      error("receiver_not_found");


   $opponent_row = mysql_fetch_array($result);
   $opponent_ID = $opponent_row["ID"];


// Update database

   if( !$type )
      $type = "NORMAL";

   if( $type == "INVITATION" )
   {
      $size = min(MAX_BOARD_SIZE, max(MIN_BOARD_SIZE, (int)@$_REQUEST['size']));
      $handicap_type = @$_REQUEST['handicap_type'];
      $color = @$_REQUEST['color'];
      $handicap = (int)@$_REQUEST['handicap'];
      $komi_m = (int)@$_REQUEST['komi_m'];
      $komi_n = (int)@$_REQUEST['komi_n'];
      $komi_d = (int)@$_REQUEST['komi_d'];
      $rated = @$_REQUEST['rated'];
      $stdhandicap = @$_REQUEST['stdhandicap'];
      $weekendclock = @$_REQUEST['weekendclock'];

      //for interpret_time_limit_forms{
      $byoyomitype = @$_REQUEST['byoyomitype'];
      $timevalue = @$_REQUEST['timevalue'];
      $timeunit = @$_REQUEST['timeunit'];

      $byotimevalue_jap = @$_REQUEST['byotimevalue_jap'];
      $timeunit_jap = @$_REQUEST['timeunit_jap'];
      $byoperiods_jap = @$_REQUEST['byoperiods_jap'];

      $byotimevalue_can = @$_REQUEST['byotimevalue_can'];
      $timeunit_can = @$_REQUEST['timeunit_can'];
      $byoperiods_can = @$_REQUEST['byoperiods_can'];

      $byotimevalue_fis = @$_REQUEST['byotimevalue_fis'];
      $timeunit_fis = @$_REQUEST['timeunit_fis'];
      //for interpret_time_limit_forms}



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

      //ToMove_ID=$tomove will hold handitype until ACCEPTED
      switch( $handicap_type )
      {
         case 'conv':
         {
            $tomove = INVITE_HANDI_CONV;
            $komi = 0;
         }
         break;

         case 'proper':
         {
            $tomove = INVITE_HANDI_PROPER;
            $komi = 0;
         }
         break;

         case 'nigiri':
         {
            $tomove = INVITE_HANDI_NIGIRI;
            $komi = $komi_n;
         }
         break;

         case 'double':
         {
            $tomove = INVITE_HANDI_DOUBLE;
            $komi = $komi_d;
         }
         break;

         default: //'manual'
         {
            $tomove = $Black_ID;
            $komi = $komi_m;
         }
         break;
      }

      if( !($komi <= MAX_KOMI_RANGE and $komi >= -MAX_KOMI_RANGE) )
         error("komi_range");

      if( !($handicap <= MAX_HANDICAP and $handicap >= 0) )
         error("handicap_range");

      interpret_time_limit_forms(); //Set global $hours,$byohours,$byoperiods

      if( $rated != 'Y' or $my_id == $opponent_ID )
         $rated = 'N';

      if( $stdhandicap != 'Y' )
         $stdhandicap = 'N';

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
         "StdHandicap='$stdhandicap', " .
         "Rated='$rated'";

      if( $disputegid > 0 )
      {
      // Check if dispute game exists
         $result = mysql_query("SELECT ID FROM Games WHERE ID=$disputegid " .
                               "AND Status='INVITED' AND " .
                               "((Black_ID=$my_id AND White_ID=$opponent_ID) OR " .
                               "(Black_ID=$opponent_ID AND White_ID=$my_id))");

         if( @mysql_num_rows($result) != 1 )
            error('unknown_game');

         $query = "UPDATE Games SET $query  WHERE ID=$disputegid LIMIT 1";
      }
      else
         $query = "INSERT INTO Games SET $query";

      $result = mysql_query( $query )
         or error('mysql_insert_game','invite1');

      if( mysql_affected_rows() != 1)
         error('mysql_insert_game','invite2');

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
                             "Rated, StdHandicap, WeekendClock " .
                             "FROM Games WHERE ID=$gid" );

      if( @mysql_num_rows($result) != 1)
         error("mysql_start_game",'send3');


      $game_row = mysql_fetch_assoc($result);
      if( $opponent_ID == $game_row["Black_ID"] && $my_id == $game_row["White_ID"])
      {
         $clock_used_black = ( $opponent_row['OnVacation'] > 0 ? -1 : $opponent_row["ClockUsed"]);
         $clock_used_white = ( $player_row['OnVacation'] > 0 ? -1 : $player_row["ClockUsed"]);
         $rating_black = $opponent_row["Rating2"];
         $rating_white = $player_row["Rating2"];
      }
      else if( $my_id == $game_row["Black_ID"] && $opponent_ID == $game_row["White_ID"])
      {
         $clock_used_white = ( $opponent_row['OnVacation'] > 0 ? -1 : $opponent_row["ClockUsed"]);
         $clock_used_black = ( $player_row['OnVacation'] > 0 ? -1 : $player_row["ClockUsed"]);
         $rating_white = $opponent_row["Rating2"];
         $rating_black = $player_row["Rating2"];
      }
      else
      {
         error("mysql_start_game",'send4');
      }

      $size = min(MAX_BOARD_SIZE, max(MIN_BOARD_SIZE, (int)$game_row["Size"]));

      if( $game_row['WeekendClock'] != 'Y' )
      {
         $clock_used_white += 100;
         $clock_used_black += 100;
      }

      $ticks_black = get_clock_ticks($clock_used_black);
      $ticks_white = get_clock_ticks($clock_used_white);


      $query =  "UPDATE Games SET " .
         "Status='PLAY', ";


      mt_srand ((double) microtime() * 1000000);

      //ToMove_ID hold handitype since INVITATION
      $handitype = $game_row["ToMove_ID"];

      switch( $handitype )
      {
         case INVITE_HANDI_CONV:
      {
            list($handicap,$komi,$swap) =
               suggest_conventional($rating_white, $rating_black, $size);

         $query .= "Handicap=$handicap, Komi=$komi, ";
      }
         break;

         case INVITE_HANDI_PROPER:
      {
         list($handicap,$komi,$swap) =
               suggest_proper($rating_white, $rating_black, $size);

         $query .= "Handicap=$handicap, Komi=$komi, ";
      }
         break;

         case INVITE_HANDI_NIGIRI:
         {
            $swap = mt_rand(0,1);
/* already in game record: no query needed
            $handicap= $game_row["Handicap"];
            $komi= $game_row["Komi"];
*/
         }
         break;

         default:
         {
            $swap = 0;
/* already in game record: no query except for second game of double game
            $handicap= $game_row["Handicap"];
            $komi= $game_row["Komi"];
*/
         }
         break;
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
         error("mysql_start_game",'send5');

      if( $handitype == INVITE_HANDI_DOUBLE ) // double
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
            "Size=$size, " .
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
            "StdHandicap='" . $game_row["StdHandicap"] . "', " .
            "Rated='" . ($Rated ? 'Y' : 'N' ) . "'";

         mysql_query( $query )
            or error("mysql_query_failed");

      }

      mysql_query( "UPDATE Players SET Running=Running+" . ( $handitype == INVITE_HANDI_DOUBLE ? 2 : 1 ) .
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

   $query = "INSERT INTO Messages SET Time=FROM_UNIXTIME($NOW), " .
       "Type='$type', ";

   if( $type == 'INVITATION' )
      $query .= "Game_ID=$gid, ";

   if( $reply > 0 )
      $query .= "ReplyTo=$reply, ";

   $message = addslashes(trim($message));
   $subject = addslashes(trim($subject));
   $query .= "Subject=\"$subject\", Text=\"$message\"";

   $result = mysql_query( $query );
   if( mysql_affected_rows() != 1)
      error("mysql_insert_message",'send1');

   $mid = mysql_insert_id();
   if( $my_id == $opponent_ID ) //Message to myself
   {
      $to_me = 1;
      $query = "INSERT INTO MessageCorrespondents (uid,mid,Sender,Folder_nr) VALUES " .
         "($my_id, $mid, 'M', ".FOLDER_NEW.")";
   }
   else
   {
      $to_me = 0;
      $query = "INSERT INTO MessageCorrespondents (uid,mid,Sender,Folder_nr,Replied) VALUES " .
         "($my_id, $mid, 'Y', ".FOLDER_SENT.",'N'), " .
         "($opponent_ID, $mid, 'N', ".FOLDER_NEW.",".($type == 'INVITATION' ? "'M'" : "'N'").")";
   }
   $result = mysql_query( $query );
   if( mysql_affected_rows() != 2-$to_me)
      error("mysql_insert_message",'send2');

   if( $type == "INVITATION" )
      mysql_query( "UPDATE Games SET mid='$mid' WHERE ID='$gid' LIMIT 1" );

   if( $reply > 0 )
   {
      $query = "UPDATE MessageCorrespondents SET Replied='Y'";

      if( $accepttype or $declinetype or ($disputegid > 0) or
          (isset($new_folder) and isset($folders[$new_folder])) )
      {
         if( !isset($new_folder) or !isset($folders[$new_folder]) )
            $new_folder = FOLDER_MAIN;
         $query .= ", Folder_nr=$new_folder";
      }

      $query .= " WHERE mid=$reply AND Sender!='Y' AND Replied!='Y' AND uid=$my_id LIMIT 1";

      mysql_query( $query ) or die(mysql_error());

      if( $disputegid > 0 )
         mysql_query( "UPDATE Messages SET Type='DISPUTED' " .
                      "WHERE ID=$reply LIMIT 1");
   }


// Notify receiver about message

   if( !$to_me and !(strpos($opponent_row["SendEmail"], 'ON') === false)
       and $opponent_row["Notify"] == 'NONE')
   {
      $result = mysql_query( "UPDATE Players SET Notify='NEXT' WHERE Handle='$to' LIMIT 1" );
   }

   $msg = urlencode(T_('Message sent!'));

   jump_to("status.php?msg=$msg");
}
?>
