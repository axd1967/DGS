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
   $type = get_request_arg('type', 'NORMAL');

   init_standard_folders();
   $folders = get_folders($my_id);
   $new_folder = get_request_arg('folder');

   if( isset($_REQUEST['foldermove']) )
   {
      $foldermove_mid = get_request_arg('foldermove_mid');
      $current_folder = get_request_arg('current_folder');
      if( change_folders($my_id, $folders, array($foldermove_mid), $new_folder, $current_folder, $type == 'INVITATION') <= 0 )
         $new_folder = ( $current_folder ) ? $current_folder : FOLDER_ALL_RECEIVED;

      $page = "";
      foreach( $_REQUEST as $key => $val )
      {
         if( $val == 'Y' && preg_match("/^mark\d+$/i", $key) )
            $page.= URI_AMP."$key=Y" ;
      }

      jump_to("list_messages.php?folder=$new_folder$page");
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
   $invitation_step = ( $accepttype || $declinetype || ($disputegid > 0) ); //not needed: || ($type == "INVITATION")


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
         . " WHERE P.Handle='".mysql_addslashes($tohdl)."'";
   $opponent_row = mysql_single_fetch( "send_message.find_receiver($tohdl)", $query);
   if( !$opponent_row )
      error('receiver_not_found', "send_message.find_receiver2($tohdl)");
   if( $opponent_row['C_denied'] && !($player_row['admin_level'] & ADMIN_DEVELOPER) )
   {
      $msg = ( $type == 'INVITATION' ) ? T_('Invitation rejected!') : T_('Message rejected!');
      jump_to("status.php?sysmsg=".urlencode($msg));
   }

   $opponent_ID = $opponent_row['ID'];
   $to_me = ( $my_id == $opponent_ID ); //Message to myself
   if( $to_me && $type == 'INVITATION' )
      error('invite_self');


   // Update database

   if( $type == "INVITATION" )
   {
      $gid = make_invite_game($player_row, $opponent_row, $disputegid);
      $subject = ( $disputegid > 0 ) ? "Game invitation dispute" : "Game invitation";
   }
   else if( $accepttype )
   {
      $gid = (int)@$_REQUEST['gid'];
      accept_invite_game( $gid, $player_row, $opponent_row );
      $subject = "Game invitation accepted";
   }
   else if( $declinetype )
   {
      $gid = (int)@$_REQUEST['gid'];
      if( !GameHelper::delete_invitation_game( 'send_message.decline', $gid, $my_id, $opponent_ID ) )
         exit;
      $gid = -1; //deleted
      $subject = "Game invitation decline";
   }
   else
      $gid = 0;


   // Update database

   $msg_gid = ( $type == 'INVITATION' ) ? $gid : 0;

   send_message( 'send_message', $message, $subject
      , $opponent_ID, '', true //$opponent_row['Notify'] == 'NONE'
      , $my_id, $type, $msg_gid
      , $prev_mid, $disputegid > 0 ?'DISPUTED' :''
      , isset($folders[$new_folder]) ? $new_folder
         : ( $invitation_step ? FOLDER_MAIN : FOLDER_NONE )
      );

   jump_to("status.php?sysmsg=".urlencode(T_('Message sent!')));
}
?>
