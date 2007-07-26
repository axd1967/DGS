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
require_once( "include/game_functions.php" );

disable_cache();


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   if( $player_row["Handle"] == "guest" )
      error("not_allowed_for_guest");


   $my_id = $player_row['ID'];
   $tmp = @$_REQUEST['senderid'];
   if( $tmp>0 && $my_id != $tmp )
      error('user_mismatch');

   $tohdl = get_request_arg('to');
   $subject = get_request_arg('subject');
   $message = get_request_arg('message');
   $type = @$_REQUEST['type'];
   if( !$type )
      $type = 'NORMAL';
   $prev_mid = max( 0, (int)@$_REQUEST['reply']); //ID of message replied.
   $accepttype = isset($_REQUEST['send_accept']);
   $declinetype = isset($_REQUEST['send_decline']);

   init_standard_folders();
   $folders = get_folders($my_id);
   $new_folder = @$_REQUEST['folder'];

   if( isset($_REQUEST['foldermove']) )
   {
      $foldermove_mid = @$_REQUEST['foldermove_mid'];
      $current_folder = @$_REQUEST['current_folder'];
      if( change_folders($my_id, $folders, array($foldermove_mid), $new_folder
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
                          "SELECT ID, ClockUsed, OnVacation, Rating2, RatingStatus" .
                          (ENA_SEND_MESSAGE ?'' :", SendEmail, Notify") .
                          " FROM Players WHERE Handle='".mysql_addslashes($tohdl)."'" );

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
      $gid = (int)@$_REQUEST['gid'];
      $game_row = mysql_single_fetch( 'send_message.accept',
                             "SELECT Status"
                           . ",Black_ID,White_ID,ToMove_ID"
                           . ",Size,Handicap,Komi"
                           . ",Maintime,Byotype,Byotime,Byoperiods"
                           . ",Rated,StdHandicap,WeekendClock"
                           . " FROM Games WHERE ID=$gid" );

      if( !$game_row )
         error('invited_to_unknown_game',"send_message.accept($gid)");
      if( $game_row['Status'] != 'INVITED' )
         error('game_already_accepted',"send_message.accept($gid)");

      //ToMove_ID hold handitype since INVITATION
      $handitype = $game_row['ToMove_ID'];
      $size = $game_row['Size'];

      $my_rating = $player_row['Rating2'];
      $iamrated = ( $player_row['RatingStatus'] && is_numeric($my_rating) && $my_rating >= MIN_RATING );
      $opprating = $opponent_row['Rating2'];
      $opprated = ( $opponent_row['RatingStatus'] && is_numeric($opprating) && $opprating >= MIN_RATING );


      $double = false;
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
            $double = true;
            $i_am_black = true;
         }
         break;

         default: // 'manual': any positive value
         {
            $i_am_black = ( $game_row["Black_ID"] == $my_id );
         }
         break;
      }

      //HOT_SECTION:
      // create_game() must check the Status='INVITED' state of the game to avoid
      // that multiple clicks lead to a bad Running count increase below.
      $gids = array();
      if( $i_am_black or $double )
         $gids[] = create_game($player_row, $opponent_row, $game_row, $gid);
      else
         $gids[] = create_game($opponent_row, $player_row, $game_row, $gid);
      //always after the "already in database" one (after $gid had been checked)
      if( $double )
         $gids[] = create_game($opponent_row, $player_row, $game_row);

      //TODO: provide a link between the two paired "double" games
      $cnt = count($gids);
      mysql_query( "UPDATE Players SET Running=Running+$cnt" .
                   ( $game_row['Rated'] == 'Y' ? ", RatingStatus='RATED'" : '' ) .
                   " WHERE (ID=$my_id OR ID=$opponent_ID) LIMIT 2" )
         or error('mysql_query_failed', 'send_message.update_player');

      $subject = "Game invitation accepted";
   }
   else if( $declinetype )
   {
      $gid = (int)@$_REQUEST['gid'];
      $result = mysql_query( "DELETE FROM Games WHERE ID=$gid AND Status='INVITED'" .
                             //" AND ( Black_ID=$my_id OR White_ID=$my_id ) " .
                             //" AND ( Black_ID=$opponent_ID OR White_ID=$opponent_ID ) " .
                             " AND (White_ID=$my_id OR Black_ID=$my_id)" .
                             " AND $opponent_ID=White_ID+Black_ID-$my_id" .
                             " LIMIT 1")
         or error('mysql_query_failed', "send_message.decline($gid)");

      if( mysql_affected_rows() != 1)
      {
         error('game_delete_invitation', "send_message.decline($gid)");
         exit;
      }

      $gid = -1; //deleted
      $subject = "Game invitation decline";
   }
   else if( $type == 'ADDTIME' )
   {
      $gid = (int)@$_REQUEST['gid'];
      $add_days = (int)@$_REQUEST['add_days'];

      $error = add_time_opponent( $gid, $my_id, time_convert_to_hours( $add_days, 'days') );
      if ( $error )
         error('mysql_game_add_time',
            "send_message.addtime(game=$gid,uid=$my_id,$add_days days,opp=$opponent_ID): $error");

      $main_msg = (string)@$_REQUEST['main_message'];
      if ( trim($message) != '' )
         $main_msg .= "\n\n";
      $message = $main_msg . $message;
   }
   else
      $gid = 0;



// Update database

if(ENA_SEND_MESSAGE){ //new
   $msg_gid = 0;
   if ( $type == 'INVITATION' or $type == 'ADDTIME' )
      $msg_gid = $gid;

   send_message( 'send_message', $message, $subject
      , $opponent_ID, '', true //$opponent_row['Notify'] == 'NONE'
      , $my_id, $type, $msg_gid
      , $prev_mid, $disputegid > 0 ?'DISPUTED' :''
      , isset($folders[$new_folder]) ? $new_folder
         : ( $accepttype or $declinetype or $disputegid > 0 ? FOLDER_MAIN
            : FOLDER_NONE )
      );
}else{ //old
   $query = "INSERT INTO Messages SET Time=FROM_UNIXTIME($NOW), " .
       "Type='$type', ";

   if( $type == 'INVITATION' or $type == 'ADDTIME' )
      $query .= "Game_ID=$gid, ";

   if( $prev_mid > 0 )
      $query .= "ReplyTo=$prev_mid, ";

   $message = mysql_addslashes(trim($message));
   //not if invitation/dispute/decline:
   //if( !$message ) error('empty_message');
   $subject = mysql_addslashes(trim($subject));
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
         or error('mysql_query_failed', 'send_message.game_message');
   unset($mid);

   if( $prev_mid > 0 )
   {
      $query = "UPDATE MessageCorrespondents SET Replied='Y'";

      if( $accepttype or $declinetype or ($disputegid > 0) or
          (isset($new_folder) and isset($folders[$new_folder])) )
      {
         if( !isset($new_folder) or !isset($folders[$new_folder]) )
            $new_folder = FOLDER_MAIN;
         $query .= ", Folder_nr=$new_folder";
      }

      $query .= " WHERE mid=$prev_mid AND uid=$my_id AND Sender!='Y' LIMIT 1";

      mysql_query( $query )
         or error('mysql_query_failed', 'send_message.reply');

      if( $disputegid > 0 )
         mysql_query( "UPDATE Messages SET Type='DISPUTED' " .
                      "WHERE ID=$prev_mid LIMIT 1")
            or error('mysql_query_failed', 'send_message.dispute');
   }

// Notify receiver about message

   if( !$to_me and !(strpos($opponent_row["SendEmail"], 'ON') === false)
       and $opponent_row["Notify"] == 'NONE')
   {
      $result = mysql_query( "UPDATE Players SET Notify='NEXT' " .
                             "WHERE Handle='".mysql_addslashes($tohdl)."' LIMIT 1" )
         or error('mysql_query_failed', 'send_message.notify_receiver');
   }
} //old/new


   $msg = urlencode(T_('Message sent!'));

   if ( $type == 'ADDTIME' )
      jump_to("game.php?gid=$gid".URI_AMP."sysmsg="
         . urlencode(T_('Time added and message sent!')) );
   else
      jump_to("status.php?sysmsg=$msg");
}
?>
