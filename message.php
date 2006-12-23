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
require_once( "include/message_functions.php" );
require_once( "include/form_functions.php" );


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

/* Actual GET calls used:
   if(message.php?mode=...)
      NewMessage           : from menu
                              (or site_map)
      NewMessage&uid=      : from user info
      ShowMessage&mid=     : from message_list_table()
                              or message_info_table()
                              or list_messages
                              or here 
      Invite               : from menu
                              (or site_map or introduction)
      Invite&uid=          : from user_info
                              or show_games
      Dispute&mid=         : from here
   else if(message.php?...)
      mid=                 : from notifications
                              or here 
                           => ShowMessage&mid=
   else if(message.php)(alone)
                           : from site_map
                           => NewMessage

   Other $mode are just local.
   Where uid=ID is used, user=handle could be substitued, default from HTTP_REFERER.
*/

   $preview = @$_REQUEST['preview'];

   $default_uhandle = get_request_arg('to');
   if( !$default_uhandle )
   {
      get_request_user( $uid, $uhandle, true);
      if( !$uhandle and $uid > 0 )
      {
         if( $uid == $player_row["ID"] )
            $uhandle = $player_row["Handle"];
         else
         {
            $row = mysql_single_fetch( "SELECT Handle AS uhandle FROM Players WHERE ID=$uid",
                                       'assoc', 'message.handle');
            if( $row )
               extract($row);
         }
      }
      $default_uhandle = $uhandle;
      unset($uid); unset($uhandle); //no more used
   }

   init_standard_folders();
   $my_id = $player_row["ID"];
   $my_rating = $player_row["Rating2"];
   $iamrated = ( $player_row['RatingStatus'] && is_numeric($my_rating) && $my_rating >= MIN_RATING );
   $folders = get_folders($my_id);


   $default_subject = get_request_arg('subject');
   $default_message = get_request_arg('message');


   $mid = (int)@$_REQUEST['mid'];


   $mode = @$_REQUEST['mode'];
   if( !$mode )
   {
      $mode = ($mid > 0 ? 'ShowMessage' : 'NewMessage');
   }
   else if( @$_REQUEST['mode_dispute'] )
   {
      $mode = 'Dispute';
   }


   $submode = $mode;
   if( $mode == 'ShowMessage' or $mode == 'Dispute' )
   {
      if( !($mid > 0) )
         error("unknown_message");

      $query = "SELECT Messages.*, " .
          "UNIX_TIMESTAMP(Messages.Time) AS date, " .
          "IF(Messages.ReplyTo>0 and NOT ISNULL(previous.mid),".FLOW_ANSWER.",0)" .
          "+IF(me.Replied='Y' or other.Replied='Y',".FLOW_ANSWERED.",0) AS flow, " .
          "me.Replied, me.Sender, me.Folder_nr, " .
          "Players.Name AS other_name, Players.ID AS other_id, Players.Handle AS other_handle, " .
          "Games.Status, Games.mid AS Game_mid, " .
          "Size, Komi, Handicap, Maintime, Byotype, " .
          "Byotime, Byoperiods, Rated, Weekendclock, StdHandicap, " .
          "ToMove_ID, IF(White_ID=$my_id," . WHITE . "," . BLACK . ") AS Color " .
          "FROM (Messages, MessageCorrespondents AS me) " .
          "LEFT JOIN MessageCorrespondents AS other " .
            "ON other.mid=me.mid AND other.Sender!=me.Sender " .
          "LEFT JOIN Players ON Players.ID=other.uid " .
          "LEFT JOIN Games ON Games.ID=Game_ID " .
          "LEFT JOIN MessageCorrespondents AS previous " .
            "ON previous.mid=Messages.ReplyTo AND previous.uid=me.uid " .
          "WHERE me.uid=$my_id AND Messages.ID=me.mid AND me.mid=$mid " .
//sort old messages to myself with Sender='N' first if both 'N' and 'Y' remains
          "ORDER BY Sender" ;

      $row = mysql_single_fetch($query, 'assoc', 'message.find');
      if( !$row )
         error("unknown_message");

      extract($row);


      if( $Sender === 'M' ) //Message to myself
      {
         $other_name = $player_row["Name"];
         $other_id = $my_id;
         $other_handle = $player_row["Handle"];
      }
      else if( $other_id <= 0 )
      {
         $other_name = '['.T_('Server message').']';
         $other_handle = '';
      }
      if( empty($other_name) )
      {
         $other_name = '-';
         $other_handle = '';
      }

      $other_name = make_html_safe($other_name);

/* Here, the line was:
      $can_reply = ( $To_ID == $my_id && $other_id && $other_handle);
   but: 
    - $my_id=me.uid and $Sender=me.Sender
    - $To_ID is always an ID associated with a *not sender*
   so the old ($To_ID == $my_id) is near of ($Sender != 'Y')
   or maybe ($Sender == 'N') or... check for new Sender types.
*/
      $can_reply = ( $Sender != 'Y' && $other_id && $other_handle);
      $to_me = ( $Sender != 'Y' );

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

            mysql_query( "UPDATE MessageCorrespondents SET Folder_nr=$Folder_nr " .
                         "WHERE mid=$mid AND uid=$my_id AND Sender='$Sender' LIMIT 1" )
               or error('mysql_query_failed', 'message.update_mess_corr');

            if( mysql_affected_rows() != 1)
               error("mysql_message_info", "remove new-flag failed mid=$mid uid=$my_id Sender='$Sender'");

         }

         if( $Type == 'INVITATION' )
         {
            if( $Status=='INVITED' and ($Replied != 'Y') )
            {
               if( $to_me )
                  $submode = 'ShowInvite';
               else
                  $submode = 'ShowMyInvite';
            }
            else if( is_null($Status) )
            {
               $submode = 'AlreadyDeclined';
            }
            else
            {
               $submode = 'AlreadyAccepted';
            }
         }
         else if( $Type == 'DISPUTED' )
         {
            $submode = 'InviteDisputed';
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

   switch( $submode )
   {
      case 'ShowMessage':
      case 'AlreadyDeclined':
      case 'AlreadyAccepted':
      case 'InviteDisputed':
      {
         message_info_table($mid, $date, $to_me,
                            $other_id, $other_name, $other_handle,
                            $Subject, $Text,
                            $ReplyTo, $flow,
                            $folders, $Folder_nr, $message_form, $Replied=='M');

         if( $submode == 'AlreadyAccepted' )
         {
            echo '<font color=green>' .
               sprintf( T_('This %sgame%s invitation has already been accepted.'),
                        "<a href=\"game.php?gid=$Game_ID\">", '</a>' ) . '</font>';
         }
         else if( $submode == 'AlreadyDeclined' )
            echo '<font color=green>' .
               T_('This invitation has been declined or the game deleted') . '</font>';
         else if( $submode == 'InviteDisputed' )
            echo '<font color=green>' .
               sprintf(T_('The settings for this game invitation has been %sdisputed%s'),
                       "<a href=\"message.php?mid=$Game_mid\">", '</a>' ) . '</font>';

         if( $can_reply )
         {
            $message_form->add_row( array(
                  'HEADER', T_('Reply'),
               ) );
            $message_form->add_row( array(
                  'DESCRIPTION', T_('Subject'),
                  'TEXTINPUT', 'subject', 50, 80, $default_subject,
               ) );
            $message_form->add_row( array(
                  'DESCRIPTION', T_('Message'),
                  'TEXTAREA', 'message', 50, 8, $default_message,
               ) );
            $message_form->add_row( array(
                  'HIDDEN', 'to', $other_handle,
                  'HIDDEN', 'reply', $mid,
                  'TAB',
                  'SUBMITBUTTONX', 'send_message', T_('Send Reply'),
                              array('accesskey' => 'x'),
                  'SUBMITBUTTONX', 'preview', T_('Preview'),
                              array('accesskey' => 'w'),
               ) );
         }
      }
      break;

      case 'NewMessage':
      {
            $message_form->add_row( array(
                  'HEADER', T_('New message'),
               ) );
            $message_form->add_row( array(
                  'DESCRIPTION', T_('To (userid)'),
                  'TEXTINPUT', 'to', 50, 80, $default_uhandle,
               ) );
            $message_form->add_row( array(
                  'DESCRIPTION', T_('Subject'),
                  'TEXTINPUT', 'subject', 50, 80, $default_subject,
               ) );
            $message_form->add_row( array(
                  'DESCRIPTION', T_('Message'),
                  'TEXTAREA', 'message', 50, 8, $default_message,
               ) );
            $message_form->add_row( array(
                  'TAB',
                  'SUBMITBUTTONX', 'send_message', T_('Send Message'),
                              array('accesskey' => 'x'),
                  'SUBMITBUTTONX', 'preview', T_('Preview'),
                              array('accesskey' => 'w'),
               ) );
      }
      break;

      case 'ShowInvite':
      case 'ShowMyInvite':
      {
         message_info_table($mid, $date, $to_me,
                            $other_id, $other_name, $other_handle,
                            $Subject, $Text,
                            $ReplyTo, $flow,
                            $folders, $Folder_nr, $message_form, ($submode=='ShowInvite' or $Replied=='M'));

         $colortxt = " align='top'";
         if( $Color == BLACK )
         {
            $colortxt = "<img src='17/w.gif' alt=\"" . T_('White') . "\"$colortxt> " .
               user_reference( 0, 1, '', 0, $other_name, $other_handle) .
               "&nbsp;&nbsp;<img src='17/b.gif' alt=\"" . T_('Black') . "\"$colortxt> " .
               user_reference( 0, 1, '', $player_row) .
               '&nbsp;&nbsp;';
         }
         else
         {
            $colortxt = "<img src='17/w.gif' alt=\"" . T_('White') . "\"$colortxt> " .
               user_reference( 0, 1, '', $player_row) .
               "&nbsp;&nbsp;<img src='17/b.gif' alt=\"" . T_('Black') . "\"$colortxt> " .
               user_reference( 0, 1, '', 0, $other_name, $other_handle) .
               '&nbsp;&nbsp;';
         }

         game_info_table($Size, $colortxt, $ToMove_ID, $Komi, $Handicap, $Maintime,
                         $Byotype, $Byotime, $Byoperiods, $Rated, $Weekendclock, $StdHandicap);

         if( $can_reply )
         {
            $message_form->add_row( array(
                  'TAB',
                  'SUBMITBUTTON', 'mode_dispute', T_('Dispute settings'),
               ) );

            $message_form->add_row( array(
                  'HEADER', T_('Reply'),
               ) );
            $message_form->add_row( array(
                  'DESCRIPTION', T_('Message'),
                  'TEXTAREA', 'message', 50, 8, $default_message,
               ) );
            $message_form->add_row( array(
                  'HIDDEN', 'to', $other_handle,
                  'HIDDEN', 'reply', $mid,
                  'HIDDEN', 'subject', "Game invitation accepted (or declined)",
                  'HIDDEN', 'gid', $Game_ID,
                  'TAB',
                  'SUBMITBUTTONX', 'send_accept', T_('Accept'),
                              array('accesskey' => 'x'),
                  'SUBMITBUTTON', 'send_decline', T_('Decline'),
                  'SUBMITBUTTONX', 'preview', T_('Preview'),
                              array('accesskey' => 'w'),
               ) );
         }
      }
      break;

      case 'Dispute':
      {
         message_info_table($mid, $date, $to_me,
                            $other_id, $other_name, $other_handle,
                            $Subject, $Text,
                            $ReplyTo, $flow); //no folders, so no move

         if( $preview )
            game_settings_form($message_form, 'dispute', $iamrated, 'redraw', @$_POST);
         else
            game_settings_form($message_form, 'dispute', $iamrated, $my_id, $Game_ID);

            $message_form->add_row( array(
                  'HEADER', T_('Dispute settings'),
               ) );
            $message_form->add_row( array(
                  'DESCRIPTION', T_('Message'),
                  'TEXTAREA', 'message', 50, 8, $default_message,
               ) );

            $message_form->add_row( array(
                  'HIDDEN', 'to', $other_handle,
                  'HIDDEN', 'reply', $mid,
                  'HIDDEN', 'subject', 'Game invitation dispute',
                  'HIDDEN', 'type', 'INVITATION',
                  'HIDDEN', 'disputegid', $Game_ID,
                  'TAB',
                  'SUBMITBUTTONX', 'send_message', T_('Send Reply'),
                              array('accesskey' => 'x'),
                  'SUBMITBUTTONX', 'preview', T_('Preview'),
                              array('accesskey' => 'w'),
               ) );
      }
      break;

      case 'Invite':
      {
         if( $preview )
            game_settings_form($message_form, 'invite', $iamrated, 'redraw', @$_POST);
         else
            game_settings_form($message_form, 'invite', $iamrated);

            $message_form->add_row( array(
                  'HEADER', T_('Invitation message'),
               ) );
            $message_form->add_row( array(
                  'DESCRIPTION', T_('To (userid)'),
                  'TEXTINPUT', 'to', 50, 80, $default_uhandle,
               ) );
            $message_form->add_row( array(
                  'DESCRIPTION', T_('Message'),
                  'TEXTAREA', 'message', 50, 8, $default_message,
               ) );

            $message_form->add_row( array(
                  'HIDDEN', 'subject', 'Game invitation',
                  'HIDDEN', 'type', 'INVITATION',
                  'TAB',
                  'SUBMITBUTTONX', 'send_message', T_('Send Invitation'),
                              array('accesskey' => 'x'),
                  'SUBMITBUTTONX', 'preview', T_('Preview'),
                              array('accesskey' => 'w'),
               ) );
      }
      break;
   }

   $message_form->echo_string(1);

   if( $preview )
   {
      echo "\n<a name=\"preview\"></a><h3><font color=$h3_color>" . 
               T_('Preview') . ":</font></h3>\n";
      //$mid==0 means preview - display a *to_me* like message

      $row = mysql_single_fetch('SELECT ID, Name FROM Players ' .
                                'WHERE Handle ="' . mysql_escape_string($default_uhandle) .
                                "\"", 'assoc', 'message.preview');
      if( !$row )
         $Name = '<font color="red">' . T_('Receiver not found') . '</font>';
      else
         $Name = make_html_safe($row["Name"]);

      message_info_table(0, $NOW, false,
                         (int)$row['ID'], $Name,
                         make_html_safe($default_uhandle),
                         $default_subject, $default_message);
   }

   echo "\n</center>\n";

   end_page();
}

?>