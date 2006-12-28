<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

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
require_once( "include/make_game.php" );

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
   $tmp = @$_REQUEST['senderid'];
   if( $tmp>0 && $my_id != $tmp )
      error('user_mismatch');

   $message_id = @$_REQUEST['foldermove_mid'];
   $tohdl = get_request_arg('to');
   $reply = @$_REQUEST['reply']; //ID of message replied. if set then (often?always?) == $message_id
   $subject = get_request_arg('subject');
   $message = get_request_arg('message');
   $type = @$_REQUEST['type'];
   if( !$type )
      $type = "NORMAL";
   $gid = @$_REQUEST['gid'];
   $accepttype = isset($_REQUEST['send_accept']);
   $declinetype = isset($_REQUEST['send_decline']);

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

      jump_to("status.php?sysmsg=$msg");
*/
   }


   if( $tohdl == "guest" )
      error("guest_may_not_receive_messages");


// find receiver of the message

   $opponent_row = mysql_single_fetch( 'send_message.find_receiver',
                          "SELECT ID, SendEmail, Notify, ClockUsed, OnVacation, " .
                          "Rating2, RatingStatus " .
                          "FROM Players WHERE Handle='".addslashes($tohdl)."'" );

   if( !$opponent_row )
      error('receiver_not_found');


   $opponent_ID = $opponent_row["ID"];
   $to_me = ( $my_id == $opponent_ID ); //Message to myself
   if( $to_me and $type == 'INVITATION' )
      error("invite_self");


// Update database

   $disputegid = -1;
   if( $type == "INVITATION" )
   {
      $disputegid = @$_REQUEST['disputegid'];
      if( !is_numeric( $disputegid) )
         $disputegid = 0;

      $gid = make_invite_game($player_row, $opponent_row, $disputegid);

      if( $disputegid > 0 )
         $subject = "Game invitation dispute";
      else
         $subject = "Game invitation";
   }
   else if( $accepttype )
   {
      $game_row = mysql_single_fetch( 'send_message.accept',
                             "SELECT Black_ID, White_ID, ToMove_ID, " .
                             "Size, Handicap, Komi, " .
                             "Maintime, Byotype, Byotime, Byoperiods, " .
                             "Rated, StdHandicap, WeekendClock " .
                             "FROM Games WHERE ID=$gid" );

      if( !$game_row )
         error('mysql_start_game','send_message.accept');

      //ToMove_ID hold handitype since INVITATION
      $handitype = $game_row["ToMove_ID"];
      $size = $game_row["Size"];

      $my_rating = $player_row["Rating2"];
      $iamrated = ( $player_row['RatingStatus'] && is_numeric($my_rating) && $my_rating >= MIN_RATING );
      $opprating = $opponent_row["Rating2"];
      $opprated = ( $opponent_row['RatingStatus'] && is_numeric($opprating) && $opprating >= MIN_RATING );


      switch( $handitype )
      {
         case INVITE_HANDI_CONV:
         {
            if( !$iamrated or !$opprated )
               error('no_initial_rating');
            list($game_row['Handicap'],$game_row['Komi'],$i_am_black) =
               suggest_conventional( $my_rating, $opprating, $size);
         }
         break;

         case INVITE_HANDI_PROPER:
         {
            if( !$iamrated or !$opprated )
               error('no_initial_rating');
            list($game_row['Handicap'],$game_row['Komi'],$i_am_black) =
               suggest_proper( $my_rating, $opprating, $size);
         }
         break;

         case INVITE_HANDI_NIGIRI:
         {
            mt_srand ((double) microtime() * 1000000);
            $i_am_black = mt_rand(0,1);
         }
         break;

         case INVITE_HANDI_DOUBLE:
         {
            create_game($player_row, $opponent_row, $game_row);
            $i_am_black = false;
         }
         break;

         default: // 'manual'
         {
            $i_am_black = ( $game_row["Black_ID"] == $my_id );
         }
         break;
      }

      if( $i_am_black )
         create_game($player_row, $opponent_row, $game_row, $gid);
      else
         create_game($opponent_row, $player_row, $game_row, $gid);

      mysql_query( "UPDATE Players SET Running=Running+" . ( $handitype == INVITE_HANDI_DOUBLE ? 2 : 1 ) .
                   ( $game_row['Rated'] == 'Y' ? ", RatingStatus='RATED'" : '' ) .
                   " WHERE ID=$my_id OR ID=$opponent_ID LIMIT 2" )
         or error('mysql_query_failed', 'send_message.update_player');

      $subject = "Game invitation accepted";
   }
   else if( $declinetype )
   {
      $result = mysql_query( "DELETE FROM Games WHERE ID=$gid AND Status='INVITED'" .
                             " AND ( Black_ID=$my_id OR White_ID=$my_id ) " .
                             " AND ( Black_ID=$opponent_ID OR White_ID=$opponent_ID ) " .
                             "LIMIT 1")
         or error('mysql_query_failed', 'send_message.decline');

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
   //not if invitation/dispute/decline:
   //if( !$message ) error('empty_message');
   $subject = addslashes(trim($subject));
   $query .= "Subject=\"$subject\", Text=\"$message\"";

   $result = mysql_query( $query )
      or error('mysql_query_failed', 'send_message.insert_message');

   if( mysql_affected_rows() != 1)
      error("mysql_insert_message",'send1');

   $mid = mysql_insert_id();
   if( $to_me ) //Message to myself
   {
      $query = "INSERT INTO MessageCorrespondents (uid,mid,Sender,Folder_nr) VALUES " .
         "($my_id, $mid, 'M', ".FOLDER_NEW.")";
   }
   else
   {
      $query = "INSERT INTO MessageCorrespondents (uid,mid,Sender,Folder_nr,Replied) VALUES " .
         "($my_id, $mid, 'Y', ".FOLDER_SENT.",'N'), " .
         "($opponent_ID, $mid, 'N', ".FOLDER_NEW.",".($type == 'INVITATION' ? "'M'" : "'N'").")";
   }
   $result = mysql_query( $query )
      or error('mysql_query_failed', 'send_message.insert_mess_corr');
   if( mysql_affected_rows() != ( $to_me ? 1 : 2) )
      error("mysql_insert_message",'send2');

   if( $type == "INVITATION" )
      mysql_query( "UPDATE Games SET mid='$mid' WHERE ID='$gid' LIMIT 1" )
         or error('mysql_query_failed', 'send_message.invitation');

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

      $query .= " WHERE mid=$reply AND Sender!='Y' AND uid=$my_id LIMIT 1";

      mysql_query( $query )
         or error('mysql_query_failed', 'send_message.reply');

      if( $disputegid > 0 )
         mysql_query( "UPDATE Messages SET Type='DISPUTED' " .
                      "WHERE ID=$reply LIMIT 1")
            or error('mysql_query_failed', 'send_message.dispute');
   }


// Notify receiver about message

   if( !$to_me and !(strpos($opponent_row["SendEmail"], 'ON') === false)
       and $opponent_row["Notify"] == 'NONE')
   {
      $result = mysql_query( "UPDATE Players SET Notify='NEXT' " .
                             "WHERE Handle='".addslashes($tohdl)."' LIMIT 1" )
         or error('mysql_query_failed', 'send_message.notify_receiver');
   }

   $msg = urlencode(T_('Message sent!'));

   jump_to("status.php?sysmsg=$msg");
}
?>
