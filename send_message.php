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

$TranslateGroups[] = "Messages";

require_once( "include/std_functions.php" );
require_once( "include/rating.php" );
require_once( 'include/game_functions.php' );
require_once( "include/message_functions.php" );
require_once( "include/make_game.php" );
require_once( "include/contacts.php" );

disable_cache();


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   $my_id = (int)@$player_row['ID'];
   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');


   $sender_id = (int)@$_REQUEST['senderid'];
   if( $sender_id > 0 && $my_id != $sender_id )
      error('user_mismatch', "send_message.check.user($my_id,$sender_id)");

   $tohdl = get_request_arg('to');
   $subject = get_request_arg('subject');
   $message = get_request_arg('message');
   $type = @$_REQUEST['type'];
   if( !$type )
      $type = 'NORMAL';

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
      $msg = urlencode(T_//('Message moved!'));
      jump_to("status.php?sysmsg=$msg");
      */
   }


   if( strtolower($tohdl) == 'guest' )
      error('guest_may_not_receive_messages');


   $prev_mid = max( 0, (int)@$_REQUEST['reply']); //ID of message replied.
   $accepttype = isset($_REQUEST['send_accept']);
   $declinetype = isset($_REQUEST['send_decline']);
   $disputegid = -1;
   if( $type == "INVITATION" )
   {
      $disputegid = @$_REQUEST['disputegid'];
      if( !is_numeric( $disputegid) )
         $disputegid = 0;
   }
   //some problems with "or" instead of "||" here:
   $invitation_step = ( $accepttype || $declinetype || ($disputegid > 0)
               //not needed: || ($type == "INVITATION")
               ? true : false );


   // find receiver of the message

   /**
    * CSYSFLAG_REJECT_INVITE only blocks the invitations at starting point
    * CSYSFLAG_REJECT_MESSAGE blocks the messages except those from the invitation sequence
    **/
   $tmp= ( $type == 'INVITATION' ? CSYSFLAG_REJECT_INVITE
            : ( $invitation_step ? 0 : CSYSFLAG_REJECT_MESSAGE ));
   $query= "SELECT P.ID,P.ClockUsed,P.OnVacation,P.Rating2,P.RatingStatus"
         . ",IF(ISNULL(C.uid),0,C.SystemFlags & $tmp) AS C_denied"
         . " FROM Players AS P"
         . " LEFT JOIN Contacts AS C ON C.uid=P.ID AND C.cid=$my_id"
         . " WHERE P.Handle='".mysql_addslashes($tohdl)."'"
         ;
   $opponent_row = mysql_single_fetch( "send_message.find_receiver($tohdl)", $query);
   if( !$opponent_row )
      error('receiver_not_found', "send_message.find_receiver2($tohdl)");
   if( $opponent_row['C_denied'] && !($player_row['admin_level'] & ADMIN_DEVELOPER) )
   {
      if( $type == 'INVITATION' )
         $msg = T_('Invitation rejected!');
      else
         $msg = T_('Message rejected!');
      $msg = urlencode($msg);
      jump_to("status.php?sysmsg=$msg");
   }

   $opponent_ID = $opponent_row['ID'];
   $to_me = ( $my_id == $opponent_ID ); //Message to myself
   if( $to_me && $type == 'INVITATION' )
      error('invite_self');


   // Update database

   if( $type == "INVITATION" )
   {
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
                           . ",Ruleset,Size,Handicap,Komi"
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
      $iamrated = ( $player_row['RatingStatus'] != RATING_NONE
            && is_numeric($my_rating) && $my_rating >= MIN_RATING );
      $opprating = $opponent_row['Rating2'];
      $opprated = ( $opponent_row['RatingStatus'] != RATING_NONE
            && is_numeric($opprating) && $opprating >= MIN_RATING );


      $double = false;
      switch( (int)$handitype )
      {
         case INVITE_HANDI_CONV:
            if( !$iamrated || !$opprated )
               error('no_initial_rating');
            list($game_row['Handicap'],$game_row['Komi'],$i_am_black ) =
               suggest_conventional( $my_rating, $opprating, $size);
            break;

         case INVITE_HANDI_PROPER:
            if( !$iamrated || !$opprated )
               error('no_initial_rating');
            list($game_row['Handicap'],$game_row['Komi'],$i_am_black ) =
               suggest_proper( $my_rating, $opprating, $size);
            break;

         case INVITE_HANDI_NIGIRI:
            mt_srand ((double) microtime() * 1000000);
            $i_am_black = mt_rand(0,1);
            break;

         case INVITE_HANDI_DOUBLE:
            $double = true;
            $i_am_black = true;
            break;

         default: // 'manual': any positive value
            $i_am_black = ( $game_row["Black_ID"] == $my_id );
            break;
      }

      //HOT_SECTION:
      // create_game() must check the Status='INVITED' state of the game to avoid
      // that multiple clicks lead to a bad Running count increase below.
      $gids = array();
      if( $i_am_black || $double )
         $gids[] = create_game($player_row, $opponent_row, $game_row, $gid);
      else
         $gids[] = create_game($opponent_row, $player_row, $game_row, $gid);
      //always after the "already in database" one (after $gid had been checked)
      if( $double )
      {
         // provide a link between the two paired "double" games
         $game_row['double_gid'] = $gid;
         $gids[] = $double_gid2 = create_game($opponent_row, $player_row, $game_row);

         db_query( "join_waitingroom_game.update_double2($gid)",
            "UPDATE Games SET DoubleGame_ID=$double_gid2 WHERE ID=$gid LIMIT 1" );
      }

      $cnt = count($gids);
      db_query( "send_message.update_player($my_id,$opponent_ID)",
            "UPDATE Players SET Running=Running+$cnt" .
                   ( $game_row['Rated'] == 'Y' ? ", RatingStatus='".RATING_RATED."'" : '' ) .
                   " WHERE (ID=$my_id OR ID=$opponent_ID) LIMIT 2" );

      $subject = "Game invitation accepted";
   }
   else if( $declinetype )
   {
      $gid = (int)@$_REQUEST['gid'];
      $result = db_query( "send_message.decline($gid)",
            "DELETE FROM Games WHERE ID=$gid AND Status='INVITED'" .
            //" AND ( Black_ID=$my_id OR White_ID=$my_id ) " .
            //" AND ( Black_ID=$opponent_ID OR White_ID=$opponent_ID ) " .
            " AND (White_ID=$my_id OR Black_ID=$my_id)" .
            " AND $opponent_ID=White_ID+Black_ID-$my_id" .
            " LIMIT 1" );

      if( mysql_affected_rows() != 1)
      {
         error('game_delete_invitation', "send_message.decline($gid)");
         exit;
      }

      $gid = -1; //deleted
      $subject = "Game invitation decline";
   }
   else
      $gid = 0;



   // Update database

   $msg_gid = 0;
   if( $type == 'INVITATION' )
      $msg_gid = $gid;

   send_message( 'send_message', $message, $subject
      , $opponent_ID, '', true //$opponent_row['Notify'] == 'NONE'
      , $my_id, $type, $msg_gid
      , $prev_mid, $disputegid > 0 ?'DISPUTED' :''
      , isset($folders[$new_folder]) ? $new_folder
         : ( $invitation_step ? FOLDER_MAIN : FOLDER_NONE )
      );


   $msg = urlencode(T_('Message sent!'));
   jump_to("status.php?sysmsg=$msg");
}
?>
