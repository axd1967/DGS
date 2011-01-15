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

require_once 'include/std_functions.php';
require_once 'include/error_codes.php';
require_once 'include/game_functions.php';
require_once 'include/message_functions.php';
require_once 'include/form_functions.php';
require_once 'include/rating.php';
require_once 'include/make_game.php';
require_once 'include/contacts.php';


define('MSGBOXROWS_NORMAL', 12);
define('MSGBOXROWS_INVITE', 6);

define('MAX_MSG_RECEIVERS', 16);

{
   $send_message = ( @$_REQUEST['send_message']
                  || @$_REQUEST['send_accept']
                  || @$_REQUEST['send_decline']
                  || @$_REQUEST['foldermove']
                  );
   $preview = @$_REQUEST['preview'];
   $handle_msg_action = $send_message && !$preview;

   if( $handle_msg_action )
      disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error("not_logged_in");

/* Actual GET calls used (to identify the ways to handle them):
   if(message.php?mode=...) //with mode
      NewMessage           : from menu (or site_map)
      NewMessage&uid=      : from user info
      ShowMessage&mid=     : from message_list_body() or message_info_table() or list_messages or here
      Invite               : from menu (or site_map or introduction)
      Invite&uid=          : from user_info or show_games
      Dispute&mid=         : from here
   else if(message.php?...) //without mode
      mid=                 : from notifications or here
                           => ShowMessage&mid=
   else if(message.php) //alone
                           : from site_map
                           => NewMessage

   Other $mode are just local.
   Where uid=ID is used, user=handle could be substitued, default from HTTP_REFERER.
*/

   init_standard_folders();
   $my_id = $player_row['ID'];
   $folders = get_folders($my_id);
   $type = get_request_arg('type', 'NORMAL');

   $dgs_message = new DgsMessage();
   if( $handle_msg_action )
      $errors = handle_send_message( $dgs_message );
   else
      $errors = array();

   $arg_to = get_request_arg('to'); // single or multi-receivers
   if( !$arg_to )
      $arg_to = read_user_from_request(); // single

   $my_rating = $player_row['Rating2'];
   $iamrated = ( $player_row['RatingStatus'] != RATING_NONE ) && is_valid_rating($my_rating);


   $default_subject = get_request_arg('subject');
   $default_message = get_request_arg('message');
   $rx_term = get_request_arg('xterm'); // rx-terms: abc|def|...

   $mid = (int)@$_REQUEST['mid'];
   $other_uid = (int)@$_REQUEST['oid']; // for bulk-message
   $mode = @$_REQUEST['mode'];
   if( !$mode )
      $mode = ($mid > 0 ? 'ShowMessage' : 'NewMessage');
   elseif( @$_REQUEST['mode_dispute'] )
      $mode = 'Dispute';
   $can_reply = false;

   $submode = $mode;
   if( $mode == 'ShowMessage' || $mode == 'Dispute' )
   {
      $msg_row = DgsMessage::load_message( "message", $mid, $my_id, $other_uid, true );
      extract($msg_row);

      if( $Sender === 'M' ) //message to myself
      {
         $other_id = $my_id;
         $other_handle = $player_row["Handle"];
         $other_name = $player_row["Name"];
      }
      else if( $other_id <= 0 )
      {
         $other_id = 0;
         $other_handle = '';
         $other_name = '['.T_('Server message').']';
      }
      $other_name = ( empty($other_name) ) ? NO_VALUE : make_html_safe($other_name);

      $can_reply = ( $Sender != 'Y' && $other_id>0 && $other_handle); //exclude system messages
      $to_me = ( $Sender != 'Y' ); //include system and myself messages

      if( $mode == 'ShowMessage' )
      {
         if( !$preview )
         {
            $default_subject = $Subject;
            $default_message = '';
         }
         if( strcasecmp(substr($default_subject,0,3), "re:") != 0 )
            $default_subject = "RE: " . $default_subject;

         if( $Folder_nr == FOLDER_NEW )
         {
            // Remove NEW flag
            $Folder_nr = ( $Type == 'INVITATION' ) ? FOLDER_REPLY : FOLDER_MAIN;
            DgsMessage::update_message_folder( $mid, $my_id, $Sender, $Folder_nr );
            update_count_message_new( "message.update_message_folder.upd_cnt_msg_new($my_id)",
               $my_id, COUNTNEW_RECALC );
         }

         if( $Type == 'INVITATION' )
         {
            if( $Status == GAME_STATUS_INVITED && ($Replied != 'Y') )
               $submode = ( $to_me ) ? 'ShowInvite' : 'ShowMyInvite';
            else if( is_null($Status) )
               $submode = 'AlreadyDeclined';
            else
               $submode = 'AlreadyAccepted';
         }
         else if( $Type == 'DISPUTED' )
            $submode = 'InviteDisputed';
      }
   }// $mode == 'ShowMessage' || $mode == 'Dispute'

   // more checks
   if( $mode == 'NewMessage' || $mode == 'Invite' || $can_reply )
   {
      if( $arg_to )
      {
         if( read_message_receivers( $dgs_message, $type, false, $arg_to ) )
            $errors = array_merge( $errors, $dgs_message->errors );
      }
      else
      {
         if( $mode == 'NewMessage' )
            $errors[] = T_('Missing message receiver');
      }

      if( (string)$default_subject == '' )
         $errors[] = T_('Missing message subject');
   }//NewMessage

   // prepare to show conv/proper-handitype-suggestions
   $map_ratings = NULL;
   if( ($submode === 'Dispute' || $submode === 'Invite') && $iamrated )
   {
      $other_row = $dgs_message->get_recipient(); // not set if self-invited or other error
      if( !is_null($other_row) )
      {
         $other_rating = (int)@$other_row['Rating2'];
         if( @$other_row['RatingStatus'] != RATING_NONE && is_valid_rating($other_rating) ) // other is rated
            $map_ratings = array( 'rating1' => $my_rating, 'rating2' => $other_rating );
      }
   }

   $has_errors = ( count($errors) > 0 );


   start_page("Message - $submode", true, $logged_in, $player_row );

   echo "<center>\n";

   $message_form = new Form('messageform', 'message.php#preview', FORM_POST, true );
   $message_form->add_hidden( 'mode', $mode);
   $message_form->add_hidden( 'mid', $mid);
   $message_form->add_hidden( 'senderid', $my_id);

   switch( (string)$submode )
   {
      case 'ShowMessage':
      case 'AlreadyDeclined':
      case 'AlreadyAccepted':
      case 'InviteDisputed':
      {
         message_info_table($mid, $X_Time, $to_me,
                            $other_id, $other_name, $other_handle,
                            $Subject, $Text,
                            $Flags, $Thread, $ReplyTo, $X_Flow,
                            $folders, $Folder_nr, $message_form, $Replied=='M', $rx_term);

         if( $submode == 'AlreadyAccepted' )
         {
            echo '<font color=green>' .
               sprintf( T_('This %sgame%s invitation has already been accepted.'),
                        "<a href=\"game.php?gid=$Game_ID\">", '</a>' ) . '</font>';
         }
         else if( $submode == 'AlreadyDeclined' )
         {
            echo '<font color=green>' .
               T_('This invitation has been declined or the game deleted') . '</font>';
         }
         else if( $submode == 'InviteDisputed' )
         {
            echo '<font color=green>' .
               sprintf(T_('The settings for this game invitation has been %sdisputed%s'),
                       "<a href=\"message.php?mid=$Game_mid\">", '</a>' ) . '</font>';
         }

         if( $can_reply )
         {
            $message_form->add_row( array(
                  'HEADER', T_('Reply'),
               ));
            $message_form->add_row( array(
                  'DESCRIPTION', T_('Subject'),
                  'TEXTINPUT', 'subject', 70, 80, $default_subject,
               ));
            $message_form->add_row( array(
                  'DESCRIPTION', T_('Message'),
                  'TEXTAREA', 'message', 70, MSGBOXROWS_NORMAL, $default_message,
               ));
            $message_form->add_row( array(
                  'HIDDEN', 'to', $other_handle,
                  'HIDDEN', 'reply', $mid,
                  'SUBMITBUTTONX', 'send_message', T_('Send Reply'),
                              array( 'accesskey' => ACCKEY_ACT_EXECUTE ),
                  'SUBMITBUTTONX', 'preview', T_('Preview'),
                              array( 'accesskey' => ACCKEY_ACT_PREVIEW ),
               ));
         }
         break;
      }//case ShowMessage/AlreadyDeclined/AlreadyAccepted/InviteDisputed

      case 'NewMessage':
      {
         $message_form->add_row( array(
               'HEADER', T_('New message'),
            ));
         $message_form->add_row( array(
               'DESCRIPTION', T_('To (userid)'),
               'TEXTINPUT', 'to', 50, 275, $arg_to,
            ));
         $message_form->add_row( array(
               'DESCRIPTION', T_('Subject'),
               'TEXTINPUT', 'subject', 70, 80, $default_subject,
            ));
         $message_form->add_row( array(
               'DESCRIPTION', T_('Message'),
               'TEXTAREA', 'message', 70, MSGBOXROWS_NORMAL, $default_message,
            ));
         $message_form->add_row( array(
               'SUBMITBUTTONX', 'send_message', T_('Send Message'),
                           array( 'accesskey' => ACCKEY_ACT_EXECUTE ),
               'SUBMITBUTTONX', 'preview', T_('Preview'),
                           array( 'accesskey' => ACCKEY_ACT_PREVIEW ),
            ));
         break;
      }//case NewMessage

      case 'ShowInvite':
      case 'ShowMyInvite':
      {
         message_info_table($mid, $X_Time, $to_me,
                            $other_id, $other_name, $other_handle,
                            $Subject, $Text,
                            $Flags, $Thread, $ReplyTo, $X_Flow,
                            $folders, $Folder_nr, $message_form, ($submode=='ShowInvite' || $Replied=='M'),
                            $rx_term);

         game_info_table( GSET_MSG_INVITE, $msg_row, $player_row, $iamrated);

         if( $can_reply )
         {
            $message_form->add_row( array(
                  'SUBMITBUTTON', 'mode_dispute', T_('Dispute settings'),
               ));

            $message_form->add_row( array(
                  'HEADER', T_('Reply'),
               ));
            $message_form->add_row( array(
                  'DESCRIPTION', T_('Message'),
                  'TEXTAREA', 'message', 70, MSGBOXROWS_INVITE, $default_message,
               ));
            $message_form->add_row( array(
                  'HIDDEN', 'to', $other_handle,
                  'HIDDEN', 'reply', $mid,
                  'HIDDEN', 'subject', "Game invitation accepted (or declined)",
                  'HIDDEN', 'gid', $Game_ID,
                  'SUBMITBUTTONX', 'send_accept', T_('Accept'),
                              array( 'accesskey' => ACCKEY_ACT_EXECUTE ),
                  'SUBMITBUTTON', 'send_decline', T_('Decline'),
                  'SUBMITBUTTONX', 'preview', T_('Preview'),
                              array( 'accesskey' => ACCKEY_ACT_PREVIEW ),
               ));
         }
         break;
      }//case ShowInvite/ShowMyInvite

      case 'Dispute':
      {
         message_info_table($mid, $X_Time, $to_me,
                            $other_id, $other_name, $other_handle,
                            $Subject, $Text,
                            $Flags, $Thread, $ReplyTo, $X_Flow, //no folders, so no move
                            null, null, null, false, $rx_term);

         if( $preview )
            game_settings_form($message_form, GSET_MSG_DISPUTE, GSETVIEW_SIMPLE, $iamrated, 'redraw', @$_POST, $map_ratings);
         else
            game_settings_form($message_form, GSET_MSG_DISPUTE, GSETVIEW_SIMPLE, $iamrated, $my_id, $Game_ID, $map_ratings);

         $message_form->add_row( array(
               'HEADER', T_('Dispute settings'),
            ));
         $message_form->add_row( array(
               'DESCRIPTION', T_('Message'),
               'TEXTAREA', 'message', 70, MSGBOXROWS_INVITE, $default_message,
            ));

         $message_form->add_row( array(
               'HIDDEN', 'to', $other_handle,
               'HIDDEN', 'reply', $mid,
               'HIDDEN', 'subject', 'Game invitation dispute',
               'HIDDEN', 'type', 'INVITATION',
               'HIDDEN', 'disputegid', $Game_ID,
               'SUBMITBUTTONX', 'send_message', T_('Send Reply'),
                           array( 'accesskey' => ACCKEY_ACT_EXECUTE ),
               'SUBMITBUTTONX', 'preview', T_('Preview'),
                           array( 'accesskey' => ACCKEY_ACT_PREVIEW ),
            ));
         break;
      }//case Dispute

      case 'Invite':
      {
         if( $preview )
            game_settings_form($message_form, GSET_MSG_INVITE, GSETVIEW_SIMPLE, $iamrated, 'redraw', @$_POST, $map_ratings);
         else
            game_settings_form($message_form, GSET_MSG_INVITE, GSETVIEW_SIMPLE, $iamrated, null, null, $map_ratings);

         $message_form->add_row( array(
               'HEADER', T_('Invitation message'),
            ));
         $message_form->add_row( array(
               'DESCRIPTION', T_('To (userid)'),
               'TEXTINPUT', 'to', 25, 275, $arg_to, // max-len only to hold bad-vals
            ));
         $message_form->add_row( array(
               'DESCRIPTION', T_('Message'),
               'TEXTAREA', 'message', 70, MSGBOXROWS_INVITE, $default_message,
            ));

         $message_form->add_row( array(
               'HIDDEN', 'subject', 'Game invitation',
               'HIDDEN', 'type', 'INVITATION',
               'SUBMITBUTTONX', 'send_message', T_('Send Invitation'),
                           array( 'accesskey' => ACCKEY_ACT_EXECUTE ),
               'SUBMITBUTTONX', 'preview', T_('Preview'),
                           array( 'accesskey' => ACCKEY_ACT_PREVIEW ),
            ));
         break;
      }//case Invite
   }//switch $submode

   $message_form->echo_string(1);


   if( $has_errors && ($send_message || $preview) )
   {
      echo "<br>\n<table><tr>",
         buildErrorListString( T_('There have been some errors'), array_unique($errors), 1 ),
         "</tr></table>";
   }

   if( $preview || ($send_message && $has_errors) )
   {
      echo "\n<h3 id='preview' class=Header>", T_('Message preview'), "</h3>\n";

      //$mid==0 means preview - display a *to_me* like message
      if( $dgs_message->has_recipient() ) // single-receiver
      {
         $user_row = $dgs_message->build_recipient_user_row();
         message_info_table( 0 /* preview */, $NOW, false,
                             $user_row['ID'], $user_row['Name'], $user_row['Handle'],
                             $default_subject, $default_message, 0 );
      }
      else // multi-receiver (bulk)
      {
         message_info_table( 0 /* preview */, $NOW, false,
                             $dgs_message->recipients, '', '',
                             $default_subject, $default_message, MSGFLAG_BULK );
      }
   }

   echo "\n</center>\n";

   end_page();
}//main



/*!
 * \brief Checks and performs message-actions.
 * \return does NOT return on success but jump to status-page;
 *         on check-failure returns error-array for previewing
 */
function handle_send_message( &$dgs_message )
{
   global $player_row, $folders, $type;

   $my_id = (int)@$player_row['ID'];
   if( $my_id <= GUESTS_ID_MAX )
      return array( ErrorCode::get_error_text('not_allowed_for_guest') );

   $new_folder = (int)get_request_arg('folder');

   if( isset($_REQUEST['foldermove']) )
   {
      handle_change_folder( $my_id, $folders, $new_folder, $type );
      exit; // for safety
   }

   $sender_id = (int)@$_REQUEST['senderid'];
   if( $sender_id > 0 && $my_id != $sender_id )
      return array( ErrorCode::get_error_text('user_mismatch') );

   $prev_mid = max( 0, (int)@$_REQUEST['reply']); //ID of message replied.
   $accepttype = isset($_REQUEST['send_accept']);
   $declinetype = isset($_REQUEST['send_decline']);
   $disputegid = -1;
   if( $type == "INVITATION" )
   {
      $disputegid = (int)@$_REQUEST['disputegid'];
      if( !is_numeric( $disputegid) )
         $disputegid = 0;
   }
   $invitation_step = ( $accepttype || $declinetype || ($disputegid > 0) ); //not needed: || ($type == "INVITATION")

   // find receiver of the message

   $arg_to = get_request_arg('to'); // single or multi-receivers
   if( read_message_receivers( $dgs_message, $type, $invitation_step, $arg_to ) )
      return $dgs_message->errors;

   // Update database

   $subject = get_request_arg('subject');
   $message = get_request_arg('message');

   $msg_gid = 0;
   if( $dgs_message->has_recipient() ) // single-receiver
   {
      $opponent_row = $dgs_message->get_recipient();
      $opponent_ID = $opponent_row['ID'];

      if( $type == 'INVITATION' )
      {
         $msg_gid = make_invite_game($player_row, $opponent_row, $disputegid);
         $subject = ( $disputegid > 0 ) ? 'Game invitation dispute' : 'Game invitation';
      }
      else if( $accepttype )
      {
         $msg_gid = (int)@$_REQUEST['gid'];
         accept_invite_game( $msg_gid, $player_row, $opponent_row );
         $subject = 'Game invitation accepted';
      }
      else if( $declinetype )
      {
         // game will be deleted
         $msg_gid = (int)@$_REQUEST['gid'];
         if( !GameHelper::delete_invitation_game( 'send_message.decline', $msg_gid, $my_id, $opponent_ID ) )
            exit;
         $subject = 'Game invitation decline';
      }

      $to_uids = $opponent_ID;
   }
   else // multi-receiver (bulk)
   {
      $to_uids = array();
      foreach( $dgs_message->recipients as $user_row )
         $to_uids[] = (int)@$user_row['ID'];
   }

   // Send message

   send_message( 'send_message', $message, $subject
      , $to_uids, '', true //$opponent_row['Notify'] == 'NONE'
      , $my_id, $type, $msg_gid
      , $prev_mid, ($disputegid > 0 ? 'DISPUTED' : '')
      , isset($folders[$new_folder]) ? $new_folder
         : ( $invitation_step ? FOLDER_MAIN : FOLDER_NONE )
      );

   jump_to("status.php?sysmsg=".urlencode(T_('Message sent!')));
}//handle_send_message

function handle_change_folder( $my_id, $folders, $new_folder, $type )
{
   $foldermove_mid = (int)get_request_arg('foldermove_mid');
   $current_folder = (int)get_request_arg('current_folder');
   $need_reply = ( $type == 'INVITATION' );

   if( change_folders($my_id, $folders, array($foldermove_mid), $new_folder, $current_folder, $need_reply) <= 0 )
      $new_folder = ( $current_folder ) ? $current_folder : FOLDER_ALL_RECEIVED;

   $page = "";
   foreach( $_REQUEST as $key => $val )
   {
      if( $val == 'Y' && preg_match("/^mark\d+$/i", $key) )
         $page.= URI_AMP."$key=Y" ;
   }
   jump_to("list_messages.php?folder=$new_folder$page");
}//handle_change_folder

// return: false=success, otherwise failure
function read_message_receivers( &$dgs_msg, $type, $invitation_step, &$to_handles )
{
   static $cache = array();

   $to_handles = strtolower( str_replace(',', ' ', trim($to_handles)) );
   $arr_to = array_unique( preg_split( "/\s+/", $to_handles, null, PREG_SPLIT_NO_EMPTY ) );
   $cnt_to = count($arr_to);
   sort($arr_to);
   $to_handles = implode(' ', $arr_to); // lower-case
   $dgs_msg->clear_errors();

   if( !isset($cache[$to_handles]) ) // need lower-case for check
   {
      $cache[$to_handles] = 1; // handle(s) checked

      if( $cnt_to < 1 )
         return $dgs_msg->add_error( T_('Missing message receiver') );
      elseif( $cnt_to > MAX_MSG_RECEIVERS )
         return $dgs_msg->add_error( sprintf( T_('Too much receivers (max. %s)'), MAX_MSG_RECEIVERS ) );
      else // single | multi
      {
         if( $cnt_to > 1 && $type == 'INVITATION' )
            return $dgs_msg->add_error( T_('Only one receiver for invitation allowed!') );

         global $player_row;
         $my_id = (int)@$player_row['ID']; // sender

         list( $arr_receivers, $errors ) =
            DgsMessage::load_message_receivers( $type, $invitation_step, $arr_to );
         foreach( $errors as $error )
            $dgs_msg->add_error( $error );

         $arr_handles = array();
         foreach( $arr_receivers as $handle => $user_row )
         {
            if( $user_row['ID'] == $my_id )
               $dgs_msg->add_error( ErrorCode::get_error_text('bulkmessage_self') );
            $dgs_msg->add_recipient( $user_row );
            $arr_handles[] = $user_row['Handle']; // original case
         }
         $to_handles = implode(' ', $arr_handles); // reset original case
      }
   }

   return (count($dgs_msg->errors) > 0 );
}//read_message_receivers

function read_user_from_request()
{
   global $player_row;

   get_request_user( $uid, $uhandle, true);
   if( !$uhandle && $uid > 0 )
   {
      if( $uid == $player_row['ID'] )
         $uhandle = $player_row['Handle'];
      else
      {
         $row = mysql_single_fetch( 'message.handle', "SELECT Handle FROM Players WHERE ID=$uid LIMIT 1" );
         if( $row )
            $uhandle = $row['Handle'];
      }
   }
   return $uhandle;
}//read_user_from_request

?>
