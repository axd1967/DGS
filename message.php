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
require_once( 'include/game_functions.php' );
require_once( "include/message_functions.php" );
require_once( "include/form_functions.php" );


define('MSGBOXROWS_NORMAL', 12);
define('MSGBOXROWS_INVITE', 6);

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $show_suggestions = true;

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

   $preview = @$_REQUEST['preview'];
   $rx_term = get_request_arg('xterm'); // rx-terms: abc|def|...

   $default_uhandle = get_request_arg('to');
   if( !$default_uhandle )
   {
      get_request_user( $uid, $uhandle, true);
      if( !$uhandle && $uid > 0 )
      {
         if( $uid == $player_row["ID"] )
            $uhandle = $player_row['Handle'];
         else
         {
            $row = mysql_single_fetch( 'message.handle',
               "SELECT Handle FROM Players WHERE ID=$uid" );
            if( $row )
               $uhandle = $row['Handle'];
         }
      }
      $default_uhandle = $uhandle;
      unset($uid);
      unset($uhandle);
   }

   $my_id = $player_row["ID"];
   $my_rating = $player_row["Rating2"];
   $iamrated = ( $player_row['RatingStatus'] != RATING_NONE
         && is_numeric($my_rating) && $my_rating >= MIN_RATING );

   init_standard_folders();
   $folders = get_folders($my_id);

   $default_subject = get_request_arg('subject');
   $default_message = get_request_arg('message');

   $mid = (int)@$_REQUEST['mid'];
   $mode = @$_REQUEST['mode'];
   if( !$mode )
      $mode = ($mid > 0 ? 'ShowMessage' : 'NewMessage');
   elseif( @$_REQUEST['mode_dispute'] )
      $mode = 'Dispute';

   $submode = $mode;
   if( $mode == 'ShowMessage' || $mode == 'Dispute' )
   {
      if( !($mid > 0) )
         error("unknown_message", "message.miss_message($mid)");

      /* see also the note about MessageCorrespondents.mid==0 in message_list_query() */
      $query = "SELECT Messages.*"
         .",UNIX_TIMESTAMP(Messages.Time) AS date"
         .",IF(NOT ISNULL(previous.mid),".FLOW_ANSWER.",0)"
         . "+IF(me.Replied='Y' OR other.Replied='Y',".FLOW_ANSWERED.",0) AS flow"
         .",me.Replied, me.Sender, me.Folder_nr"
         .",Players.ID AS other_id,Players.Handle AS other_handle"
         .",Players.Name AS other_name,Players.Rating2 AS other_rating"
         .",Players.RatingStatus AS other_ratingstatus"
         .",Games.Status,Games.mid AS Game_mid"
         .",Size, Komi, Handicap, Rated, WeekendClock, StdHandicap"
         .",Maintime, Byotype, Byotime, Byoperiods"
         .",ToMove_ID, IF(White_ID=$my_id," . WHITE . "," . BLACK . ") AS myColor"
         ." FROM (Messages, MessageCorrespondents AS me) " .
          "LEFT JOIN MessageCorrespondents AS other " .
            "ON other.mid=$mid AND other.Sender!=me.Sender " .
          "LEFT JOIN Players ON Players.ID=other.uid " .
          "LEFT JOIN Games ON Games.ID=Game_ID " .
          "LEFT JOIN MessageCorrespondents AS previous " .
            "ON Messages.ReplyTo>0 AND previous.mid=Messages.ReplyTo AND previous.uid=$my_id " .
          "WHERE Messages.ID=$mid AND me.mid=$mid AND me.uid=$my_id " .
          //sort old messages to myself with Sender='N' first if both 'N' and 'Y' remains
          "ORDER BY Sender" ;

      /**
       * TODO: msg multi-receivers
       * Actually, this query and the following code does not support
       * multiple receivers (i.e. more than one "other" LEFT JOINed row).
       * Multiple receivers are just allowed when it is a message from
       * the server (ID=0) because the message is not read BY the server.
       * See also: send_message
       **/
      $msg_row = mysql_single_fetch( "message.find($mid)", $query);
      if( !$msg_row )
         error('unknown_message', "message.find.not_found($mid)");

      extract($msg_row);


      if( $Sender === 'M' ) //message to myself
      {
         $other_name = $player_row["Name"];
         $other_id = $my_id;
         $other_handle = $player_row["Handle"];
      }
      else if( $other_id <= 0 )
      {
         $other_name = '['.T_('Server message').']';
         $other_id = 0;
         $other_handle = '';
      }
      if( empty($other_name) )
      {
         $other_name = NO_VALUE;
         $other_id = 0;
         $other_handle = '';
      }

      $other_name = make_html_safe($other_name);

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

            $Folder_nr = ( $Type == 'INVITATION' ? FOLDER_REPLY : FOLDER_MAIN );

            db_query( "message.update_mess_corr($my_id,$mid,$Sender)",
               "UPDATE MessageCorrespondents SET Folder_nr=$Folder_nr " .
               "WHERE mid=$mid AND uid=$my_id AND Sender='$Sender' LIMIT 1" );
            if( mysql_affected_rows() != 1)
               error("mysql_message_info", "message.update_mess_corr2($mid,$my_id,$Sender)");

            update_count_message_new( "message.update_mess_corr.upd_cnt_msg_new($my_id)",
               $my_id, COUNTNEW_RECALC );
         }

         if( $Type == 'INVITATION' )
         {
            if( $Status=='INVITED' && ($Replied != 'Y') )
               $submode = ( $to_me ) ? 'ShowInvite' : 'ShowMyInvite';
            else if( is_null($Status) )
               $submode = 'AlreadyDeclined';
            else
               $submode = 'AlreadyAccepted';
         }
         else if( $Type == 'DISPUTED' )
         {
            $submode = 'InviteDisputed';
         }
      }
   }// $mode == 'ShowMessage' || $mode == 'Dispute'


   // prepare to show conv/proper-handitype-suggestions
   $map_ratings = NULL;
   if( $submode === 'Dispute' || $submode === 'Invite' )
   {
      if( $show_suggestions && $iamrated && $default_uhandle != $player_row['Handle'] )
      {
         $other_row = mysql_single_fetch( 'message.invite_suggest.'.$submode,
            'SELECT Rating2, RatingStatus FROM Players ' .
            'WHERE Handle="' . mysql_addslashes($default_uhandle) . '"' );
         if( $other_row )
         {
            $other_rating = (int)@$other_row['Rating2'];
            if( @$other_row['RatingStatus'] != RATING_NONE
                  && is_numeric($other_rating) && $other_rating >= MIN_RATING )
            {// other is rated
               $map_ratings = array( 'rating1' => $my_rating, 'rating2' => $other_rating );
            }
         }
      }
   }


   start_page("Message - $submode", true, $logged_in, $player_row );

   echo "<center>\n";

   $message_form = new Form('messageform', 'message_selector.php#preview', FORM_POST, true );
   //by default:
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
         message_info_table($mid, $date, $to_me,
                            $other_id, $other_name, $other_handle,
                            $Subject, $Text,
                            $Thread, $ReplyTo, $flow,
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
               'TEXTINPUT', 'to', 25, 40, $default_uhandle,
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
         message_info_table($mid, $date, $to_me,
                            $other_id, $other_name, $other_handle,
                            $Subject, $Text,
                            $Thread, $ReplyTo, $flow,
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
         message_info_table($mid, $date, $to_me,
                            $other_id, $other_name, $other_handle,
                            $Subject, $Text,
                            $Thread, $ReplyTo, $flow, //no folders, so no move
                            null, null, null, false, $rx_term);

         if( $preview )
            game_settings_form($message_form, GSET_MSG_DISPUTE, $iamrated, 'redraw', @$_POST, $map_ratings);
         else
            game_settings_form($message_form, GSET_MSG_DISPUTE, $iamrated, $my_id, $Game_ID, $map_ratings);

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
            game_settings_form($message_form, GSET_MSG_INVITE, $iamrated, 'redraw', @$_POST, $map_ratings);
         else
            game_settings_form($message_form, GSET_MSG_INVITE, $iamrated, null, null, $map_ratings);

         $message_form->add_row( array(
               'HEADER', T_('Invitation message'),
            ));
         $message_form->add_row( array(
               'DESCRIPTION', T_('To (userid)'),
               'TEXTINPUT', 'to', 25, 40, $default_uhandle,
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

   if( $preview )
   {
      echo "\n<h3 id='preview' class=Header>" .
         T_('Message preview') . "</h3>\n";
      //$mid==0 means preview - display a *to_me* like message

      $row = mysql_single_fetch( 'message.preview',
         'SELECT ID, Handle, Name FROM Players ' .
         'WHERE Handle="' . mysql_addslashes($default_uhandle) . '"' );
      if( !$row )
      {
         $row['Name'] = '<span class=InlineWarning>' . T_('Receiver not found') . '</span>';
         $row['ID'] = 0;
         $row['Handle'] = '';
      }
      else
         $row['Name'] = make_html_safe($row['Name']);

      message_info_table( 0 /* preview */, $NOW, false,
                         $row['ID'], $row['Name'], $row['Handle'],
                         $default_subject, $default_message);
   }

   echo "\n</center>\n";

   end_page();
}
?>
