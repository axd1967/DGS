<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once 'include/classlib_user.php';


define('MSGBOXROWS_NORMAL', 12);
define('MSGBOXROWS_INVITE', 6);

define('MAX_MSG_RECEIVERS', 16); // oriented at max. for multi-player-game

{
   $send_message = ( @$_REQUEST['send_message']
                  || @$_REQUEST['send_accept']
                  || @$_REQUEST['send_decline']
                  || @$_REQUEST['foldermove'] );
   $preview = @$_REQUEST['preview'];
   $handle_msg_action = $send_message && !$preview;

   if( $handle_msg_action )
      disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error("not_logged_in");
   $is_bulk_admin = ( @$player_row['admin_level'] & (ADMIN_DEVELOPER|ADMIN_FORUM|ADMIN_GAME) );

/* Actual GET calls used (to identify the ways to handle them):
   if(message.php?mode=...) //with mode
      NewMessage           : from menu (or site_map)
      NewMessage&uid=      : from user info
      NewMessage&mpmt=&mpgid=&...  : special-message for multi-player-game
         mpmt=1                    : multi-message  for MPG-start_game
         mpmt=2 & mpcol=&mpmove=   : multi-message  for MPG-resign
         mpmt=3 & mpuid=           : single-message for MPG-invite
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
   $mpg_gid = (int)get_request_arg('mpgid');
   $mpg_type = (int)get_request_arg('mpmt');

   $mid = (int)@$_REQUEST['mid'];
   $other_uid = (int)@$_REQUEST['oid']; // for bulk-message
   $mode = @$_REQUEST['mode'];
   if( !$mode )
      $mode = ($mid > 0 ? 'ShowMessage' : 'NewMessage');
   elseif( @$_REQUEST['mode_dispute'] )
      $mode = 'Dispute';
   $can_reply = false;

   $arg_to = get_request_arg('to'); // single or multi-receivers
   $has_arg_to = ( (string)trim($arg_to) != '' );
   if( $mpg_type > 0 && (!$arg_to && $mode == 'NewMessage') )
   {
      // handle multi-player-game msg-request
      list( $arg_to, $arr_mpg_users, $mpg_arr ) = read_mpgame_request();
   }
   else
      $arr_mpg_users = null;
   if( !$arg_to )
      $arg_to = read_user_from_request(); // single

   $maxGamesCheck = new MaxGamesCheck();
   $dgs_message = new DgsMessage();
   if( $handle_msg_action )
   {
      ta_begin();
      {//HOT-section to handle message
         $errors = handle_send_message( $dgs_message, $mpg_type, $arg_to );
      }
      ta_end();
   }
   else
      $errors = array();

   $my_rating = $player_row['Rating2'];
   $iamrated = ( $player_row['RatingStatus'] != RATING_NONE ) && is_valid_rating($my_rating);


   $default_subject = get_request_arg('subject');
   $default_message = get_request_arg('message');
   if( $mpg_type > 0 && (@$mpg_gid || !$has_arg_to) && (empty($default_subject) && empty($default_message)) )
   {
      list( $default_subject, $default_message ) =
         MultiPlayerGame::get_message_defaults( $mpg_type, $mpg_gid, $mpg_arr );
   }
   $rx_term = get_request_arg('xterm'); // rx-terms: abc|def|...

   $submode = $mode;
   if( $mode == 'ShowMessage' || $mode == 'Dispute' )
   {
      $msg_row = DgsMessage::load_message( "message", $mid, $my_id, $other_uid, /*fulldata*/true );
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
            ta_begin();
            {//HOT-section to move message away from NEW-folder
               DgsMessage::update_message_folder( $mid, $my_id, $Sender, $Folder_nr );
               update_count_message_new( "message.update_message_folder.upd_cnt_msg_new($my_id)",
                  $my_id, COUNTNEW_RECALC );
            }
            ta_end();
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

      if( $mode != 'Invite' && (string)$default_subject == '' )
         $errors[] = T_('Missing message subject');
   }//NewMessage

   // prepare to show conv/proper-handitype-suggestions
   $other_row = $dgs_message->get_recipient(); // NULL if self-invited, bulk or other error
   $map_ratings = NULL;
   if( ($submode === 'Dispute' || $submode === 'Invite') && $iamrated && !is_null($other_row) )
   {
      $other_rating = (int)@$other_row['Rating2'];
      if( @$other_row['RatingStatus'] != RATING_NONE && is_valid_rating($other_rating) ) // other is rated
         $map_ratings = array( 'rating1' => $my_rating, 'rating2' => $other_rating );
   }

   // check own/opp max-games
   if( preg_match("/^(InviteDisputed|ShowInvite|ShowMyInvite|Invite|Dispute)$/", $submode) )
   {
      $opp_row = $other_row;
      if( is_null($opp_row) && !$arg_to && @$msg_row['other_handle'] )
      {
         $arg_to = $msg_row['other_handle'];
         if( read_message_receivers( $dgs_message, $type, false, $arg_to ) )
            $errors = array_merge( $errors, $dgs_message->errors );
         else
            $opp_row = $dgs_message->get_recipient();
      }

      $chk_errors = check_max_games($opp_row);
      $allow_game_start = ( count($chk_errors) == 0 );
      if( !$allow_game_start )
         $errors = array_merge( $errors, $chk_errors );
   }
   else
      $allow_game_start = true;

   $has_errors = ( count($errors) > 0 );


   start_page("Message - $submode", true, $logged_in, $player_row );

   echo "<center>\n";

   $message_form = new Form('messageform', 'message.php#preview', FORM_POST, true );
   $message_form->add_hidden('mode', $mode);
   $message_form->add_hidden('mid', $mid);
   $message_form->add_hidden('senderid', $my_id);
   if( $mpg_type > 0 )
   {
      foreach( array( 'mpmt', 'mpgid', 'mpcol', 'mpmove', 'mpuid' ) as $hidden_key )
         $message_form->add_hidden( $hidden_key, get_request_arg($hidden_key) );
   }

   switch( (string)$submode )
   {
      case 'ShowMessage':
      case 'AlreadyDeclined':
      case 'AlreadyAccepted':
      case 'InviteDisputed':
      {
         section('message', T_('Message View') );

         message_info_table($mid, $X_Time, $to_me,
                            $other_id, $other_name, $other_handle,
                            $Subject, $Text,
                            $Flags, $Thread, $ReplyTo, $X_Flow,
                            $folders, $Folder_nr, $message_form, $Replied=='M', $rx_term);

         if( $submode == 'AlreadyAccepted' )
         {
            echo span('InviteMsgInfo',
                      sprintf( T_('This %sgame%s invitation has already been accepted.'),
                               "<a href=\"game.php?gid=$Game_ID\">", '</a>' ) );
         }
         else if( $submode == 'AlreadyDeclined' )
         {
            echo span('InviteMsgInfo', T_('This invitation has been declined or the game deleted') );
         }
         else if( $submode == 'InviteDisputed' )
         {
            echo span('InviteMsgInfo',
                      sprintf( T_('The settings for this game invitation has been %sdisputed%s'),
                               "<a href=\"message.php?mid=$Game_mid\">", '</a>' ) );
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
               'HEADER', ($mpg_type > 0) ? T_('New multi-player-game message') : T_('New message'),
            ));
         $message_form->add_row( array(
               'DESCRIPTION', T_('To (userid)'),
               'TEXTINPUTX', 'to', 50, 275, $arg_to, ( $mpg_type == MPGMSG_INVITE ? 'disabled=1' : '' ),
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
         section('invite', T_('Game Invitation') );
         echo $maxGamesCheck->get_warn_text();

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
                  'SUBMITBUTTONX', 'mode_dispute', T_('Dispute settings'),
                              array( 'disabled' => ($allow_game_start ? 0 : 1) ),
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
                              array( 'accesskey' => ACCKEY_ACT_EXECUTE, 'disabled' => ($allow_game_start ? 0 : 1) ),
                  'SUBMITBUTTON', 'send_decline', T_('Decline'),
                  'SUBMITBUTTONX', 'preview', T_('Preview'),
                              array( 'accesskey' => ACCKEY_ACT_PREVIEW ),
               ));
         }
         break;
      }//case ShowInvite/ShowMyInvite

      case 'Dispute':
      {
         section('invite', T_('Game Invitation Dispute') );
         echo $maxGamesCheck->get_warn_text();

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
                           array( 'accesskey' => ACCKEY_ACT_EXECUTE, 'disabled' => ($allow_game_start ? 0 : 1) ),
               'SUBMITBUTTONX', 'preview', T_('Preview'),
                           array( 'accesskey' => ACCKEY_ACT_PREVIEW ),
            ));
         break;
      }//case Dispute

      case 'Invite':
      {
         section('invite', T_('Game Invitation') );
         echo $maxGamesCheck->get_warn_text();

         if( $preview )
            game_settings_form($message_form, GSET_MSG_INVITE, GSETVIEW_SIMPLE, $iamrated, 'redraw', @$_POST, $map_ratings);
         else
            game_settings_form($message_form, GSET_MSG_INVITE, GSETVIEW_SIMPLE, $iamrated, null, null, $map_ratings);

         $message_form->add_row( array(
               'HEADER', T_('Invitation message'),
            ));
         $message_form->add_row( array(
               'DESCRIPTION', T_('To (userid)'),
               'TEXTINPUT', 'to', 25, 25, $arg_to,
            ));
         $message_form->add_row( array(
               'DESCRIPTION', T_('Message'),
               'TEXTAREA', 'message', 70, MSGBOXROWS_INVITE, $default_message,
            ));

         $message_form->add_row( array(
               'HIDDEN', 'subject', 'Game invitation',
               'HIDDEN', 'type', 'INVITATION',
               'SUBMITBUTTONX', 'send_message', T_('Send Invitation'),
                           array( 'accesskey' => ACCKEY_ACT_EXECUTE, 'disabled' => ($allow_game_start ? 0 : 1) ),
               'SUBMITBUTTONX', 'preview', T_('Preview'),
                           array( 'accesskey' => ACCKEY_ACT_PREVIEW ),
            ));
         break;
      }//case Invite
   }//switch $submode

   $message_form->echo_string(1);


   if( $has_errors )
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
 * \brief Checks and if no error occured performs message-actions.
 * \param $arg_to single or multi-receivers
 * \return does NOT return on success but jump to status-page (or to game-player-page for MPG-invite);
 *         on check-failure returns error-array for previewing
 */
function handle_send_message( &$dgs_message, $mpg_type, $arg_to )
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
   if( read_message_receivers( $dgs_message, $type, $invitation_step, $arg_to ) )
      return $dgs_message->errors;

   // check own/opp max-games
   $opponent_row = $dgs_message->get_recipient();
   if( ($type == 'INVITATION') || $accepttype )
   {
      $chk_errors = check_max_games($opponent_row);
      if( count($chk_errors) )
      {
         foreach( $chk_errors as $err )
            $dgs_message->add_error( $err );
         return $dgs_message->errors;
      }
   }

   $subject = trim(get_request_arg('subject'));
   $message = get_request_arg('message');

   if( (string)$subject == '' )
   {
      $dgs_message->add_error( T_('Missing message subject') );
      return $dgs_message->errors;
   }

   // Update database

   $msg_gid = 0;
   if( $dgs_message->has_recipient() ) // single-receiver
   {
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
      else if( $mpg_type == MPGMSG_INVITE )
      {
         $msg_gid = (int)@$_REQUEST['mpgid'];
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
      , $to_uids, '', /*notify: $opponent_row['Notify'] == 'NONE'*/true
      , $my_id, $type, $msg_gid
      , $prev_mid, ($disputegid > 0 ? 'DISPUTED' : '')
      , isset($folders[$new_folder]) ? $new_folder : ( $invitation_step ? FOLDER_MAIN : FOLDER_NONE )
      );

   if( $mpg_type == MPGMSG_INVITE && $msg_gid > 0 )
      jump_to("game_players.php?gid=$msg_gid".URI_AMP."sysmsg=".urlencode(T_('Message sent!')));
   else
      jump_to("status.php?sysmsg=".urlencode(T_('Message sent!')));
}//handle_send_message

// return non-null error-arr checking on OWN/opponents max-games
function check_max_games( $opp_row )
{
   global $maxGamesCheck;
   $errors = array();

   if( !$maxGamesCheck->allow_game_start() )
      $errors[] = $maxGamesCheck->get_error_text(false);

   if( !is_null($opp_row) )
   {
      $oppMaxGamesCheck = new MaxGamesCheck( (int)@$opp_row['X_OppGamesCount'] );
      if( !$oppMaxGamesCheck->allow_game_start() )
         $errors[] = ErrorCode::get_error_text('max_games_opp');
   }

   return $errors;
}//check_max_games

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
function read_message_receivers( &$dgs_msg, $msg_type, $invitation_step, &$to_handles )
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
      global $player_row, $is_bulk_admin, $arr_mpg_users, $mpg_type, $mpg_gid;

      $cache[$to_handles] = 1; // handle(s) checked

      if( $cnt_to < 1 )
         return $dgs_msg->add_error( T_('Missing message receiver') );
      elseif( $cnt_to > MAX_MSG_RECEIVERS )
         return $dgs_msg->add_error( sprintf( T_('Too much receivers (max. %s)'), MAX_MSG_RECEIVERS ) );
      else // single | multi
      {
         if( $cnt_to > 1 && $msg_type == 'INVITATION' )
            return $dgs_msg->add_error( T_('Only one receiver for invitation allowed!') );

         $sender_uid = (int)@$player_row['ID']; // sender
         list( $arr_receivers, $errors ) =
            DgsMessage::load_message_receivers( $msg_type, $invitation_step, $arr_to );
         $dgs_msg->add_errors( $errors );

         if( $mpg_type > 0 && $mpg_gid > 0 && is_null($arr_mpg_users) )
         {
            $mpg_col = get_request_arg('mpcol');
            $arr_mpg_users = GamePlayer::load_users_for_mpgame( $mpg_gid, $mpg_col, /*skip-myself*/true, $tmp_arr );
         }

         $arr_handles = array();
         foreach( $arr_receivers as $handle => $user_row )
         {
            $uid = $user_row['ID'];
            if( $uid == $sender_uid && $cnt_to > 1 )
               $dgs_msg->add_error( ErrorCode::get_error_text('bulkmessage_self') );

            $dgs_msg->add_recipient( $user_row );
            $arr_handles[] = $user_row['Handle']; // original case

            // MPG-message only allows MPG-players as recipients
            if( $mpg_type > 0 && $mpg_gid > 0 )
            {
               $is_mpg_player = ( is_array($arr_mpg_users) )
                  ? isset($arr_mpg_users[$uid])
                  : GamePlayer::exists_game_player($mpg_gid, $uid); // fallback-check
               if( !$is_mpg_player )
                  $dgs_msg->add_error(
                     sprintf( T_('User [%s] is no participant of multi-player-game.'), $user_row['Handle'] ));
            }
         }
         $to_handles = implode(' ', $arr_handles); // reset original case

         // bulk-message only allowed for MPG|admin as sender
         if( $dgs_msg->count_recipients() > 1 )
         {
            $check_flags = ( $mpg_type == MPGMSG_INVITE ) ? GPFLAG_MASTER : 0;
            if( !$is_bulk_admin && !( $mpg_type > 0 && GamePlayer::exists_game_player($mpg_gid, $sender_uid, $check_flags) ) )
               $dgs_msg->add_error( ErrorCode::get_error_text('bulkmessage_forbidden') );
         }
      }
   }

   return ( $dgs_msg->count_errors() > 0 );
}//read_message_receivers

function read_user_from_request()
{
   global $player_row;

   get_request_user( $uid, $uhandle, true); // set globals: $uid, $uhandle
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

// read and check mpgame-request-info; returns: [ arg_to, $arr_mgp_handles, mpg_arr ]
// mpg_arr as required for MultiPlayerGame::get_message_defaults()
// needs globals: mpg_gid, mpg_type
function read_mpgame_request()
{
   global $player_row, $mpg_gid, $mpg_type;

   $col = get_request_arg('mpcol');
   $dbgmsg = "message.read_mpgame_request($mpg_gid,$mpg_type,$col)";
   if( $mpg_type != MPGMSG_STARTGAME && $mpg_type != MPGMSG_RESIGN && $mpg_type != MPGMSG_INVITE )
      error('invalid_args', "$dbgmsg.check.mpg_type");

   if( $mpg_gid <= 0 )
      error('multi_player_msg_miss_game', "$dbgmsg.check.game");
   $game_row = mysql_single_fetch( "$dbgmsg.load_game",
         "SELECT GameType, GamePlayers, ToMove_ID, Status FROM Games WHERE ID=$mpg_gid LIMIT 1" );
   if( !$game_row )
      error('unknown_game', "$dbgmsg.load_game2");
   if( $game_row['GameType'] != GAMETYPE_TEAM_GO && $game_row['GameType'] != GAMETYPE_ZEN_GO )
      error('multi_player_msg_no_mpg', "$dbgmsg.load_game2");
   $game_status = $game_row['Status'];

   if( $mpg_type == MPGMSG_INVITE )
   {
      $mpg_uid = (int)get_request_arg('mpuid');
      $mpg_gp = GamePlayer::load_game_player_by_uid( $mpg_gid, $mpg_uid );
      if( is_null($mpg_gp) )
         error('multi_player_unknown_user', "$dbgmsg.check.mpuid");

      $arr_users = User::load_quick_userinfo( array( $mpg_uid ) );
      if( !isset($arr_users[$mpg_uid]) )
         error('multi_player_invite_unknown_user', "$dbgmsg.check.mpuid($mpg_uid)");

      $handles = array( $mpg_uid => $arr_users[$mpg_uid]['Handle'] );
   }
   else // MPGMSG_STARTGAME | MPGMSG_RESIGN
   {
      $handles = GamePlayer::load_users_for_mpgame( $mpg_gid, $col, /*skip-myself*/true, $tmp_arr );
   }
   if( count($handles) == 0 )
      error('multi_player_no_users', "$dbgmsg.check.handles");

   $mpg_arr = array(); // for Resign
   if( $mpg_type == MPGMSG_INVITE )
   {
      if( $game_status != GAME_STATUS_SETUP )
         error('invalid_game_status', "$dbgmsg.check.invite.status($game_status)");
      if( $player_row['ID'] != $game_row['ToMove_ID'] )
         error('multi_player_master_mismatch', "$dbgmsg.check.invite.master($game_status)");

      $mpg_arr['from_handle'] = $player_row['Handle'];
      $mpg_arr['game_type']   = GameTexts::format_game_type( $game_row['GameType'], $game_row['GamePlayers'] );
   }
   elseif( $mpg_type == MPGMSG_RESIGN )
   {
      if( !isRunningGame($game_status) )
         error('invalid_game_status', "$dbgmsg.check.resign.status($game_status)");

      $mpg_arr['move'] = (int)get_request_arg('mpmove'); // for Resign
   }
   elseif( $mpg_type == MPGMSG_STARTGAME )
   {
      if( $game_status != GAME_STATUS_SETUP )
         error('invalid_game_status', "$dbgmsg.check.startgame.status($game_status)");
   }

   return array( implode(' ', array_values($handles)), $handles, $mpg_arr );
}//read_mpgame_request

?>
