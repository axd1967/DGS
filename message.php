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
require_once( "include/message_functions.php" );
require_once( "include/form_functions.php" );


// Input variables:

$mid = @$_GET['mid'];
$mode = @$_GET['mode'];
$uid = @$_GET['uid'];

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");
   init_standard_folders();

   $my_id = $player_row["ID"];

   if( !$mode )
   {
      $mode = ($mid > 0 ? 'ShowMessage' : 'NewMessage');
   }

   if( !$uid )
   {
//default recipient = last referenced user (ex: if from userinfo by menu link)
      if( eregi("[?&]uid=([0-9]+)", $HTTP_REFERER, $result) )
         $uid = $result[1];
   }
   if( $uid > 0 and $uid != $player_row["ID"] )
   {
      $result = mysql_query( "SELECT Handle AS default_handle FROM Players WHERE ID=$uid" );

      if( mysql_num_rows( $result ) == 1 )
      {
         extract(mysql_fetch_array($result));
      }
   }
   if( !isset($default_handle) )
   {
      $uid = 0;
      $default_handle = '';
   }

   $folders = get_folders($my_id);

   if( $mode == 'ShowMessage' or $mode == 'Dispute' )
   {
      if( !($mid > 0) )
         error("unknown_message");

      $query = "SELECT Messages.*, " .
          "UNIX_TIMESTAMP(Messages.Time) AS date, " .
          "IF(Messages.ReplyTo>0,".FLOW_ANSWER.",0)+IF(me.Replied='Y' or other.Replied='Y',".FLOW_ANSWERED.",0) AS flow, " .
          "me.Replied, me.Sender, me.Folder_nr, " .
          "Players.Name AS other_name, Players.ID AS other_id, Players.Handle AS other_handle, " .
          "Games.Status, Games.mid AS Game_mid, " .
          "Size, Komi, Handicap, Maintime, Byotype, " .
          "Byotime, Byoperiods, Rated, Weekendclock, " .
          "ToMove_ID, IF(White_ID=$my_id," . WHITE . "," . BLACK . ") AS Color " .
          "FROM Messages, MessageCorrespondents AS me " .
          "LEFT JOIN MessageCorrespondents AS other " .
            "ON other.mid=me.mid AND other.Sender!=me.Sender " .
          "LEFT JOIN Players ON Players.ID=other.uid " .
          "LEFT JOIN Games ON Games.ID=Game_ID " .
          "WHERE me.uid=$my_id AND Messages.ID=me.mid AND me.mid=$mid " .
//sort old messages to myself with Sender='N' first if both 'N' and 'Y' remains
          "ORDER BY Sender" ;

      $result = mysql_query( $query ) or error("mysql_query_failed"); //die(mysql_error());

      if( mysql_num_rows($result) != 1  and mysql_num_rows($result) != 2 )
         error("unknown_message");


      $row = mysql_fetch_array($result);

      extract($row);

      if( $Sender === 'M' ) //Message to myself
      {
         $other_name = $player_row["Name"];
         $other_id = $my_id;
         $other_handle = $player_row["Handle"];
      }
      else if( $other_id <= 0 )
      {
         $other_name = T_('Server message');
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
    - me.uid=$my_id and $Sender=me.Sender
    - $To_ID is always a ID associed with a not sender
   so the old ($To_ID == $my_id) is near of ($Sender != 'Y')
   or maybe ($Sender == 'N') or... check for new Sender types.
*/
      $can_reply = ( $Sender != 'Y' && $other_id && $other_handle);
      $to_me = ( $Sender != 'Y' );

      if( $mode == 'ShowMessage' )
      {
         $default_subject = $Subject;
         if( strcasecmp(substr($default_subject,0,3), "re:") != 0 )
            $default_subject = "RE: " . $default_subject;

         if( $Folder_nr == FOLDER_NEW )
         {
            // Remove NEW flag

            $Folder_nr = ( $Type == 'INVITATION' ? FOLDER_REPLY : FOLDER_MAIN );

            mysql_query( "UPDATE MessageCorrespondents SET Folder_nr=$Folder_nr " .
                         "WHERE mid=$mid AND uid=$my_id AND Sender='$Sender' LIMIT 1" )
               or die( mysql_error());

            if( mysql_affected_rows() != 1)
               error("mysql_message_info", 'remove new-flag failed');

         }

         if( $Type == 'INVITATION' )
         {
            if( $Status=='INVITED' and ($Replied != 'Y') )
            {
               if( $to_me )
                  $mode = 'ShowInvite';
               else
                  $mode = 'ShowMyInvite';
            }
            else if( is_null($Status) )
            {
               $mode = 'AlreadyDeclined';
            }
            else
            {
               $mode = 'AlreadyAccepted';
            }
         }
         else if( $Type == 'DISPUTED' )
         {
            $mode = 'InviteDisputed';
         }

      }

   }

   start_page("Message - $mode", true, $logged_in, $player_row );

   echo "<center>\n";

   $message_form = new Form('messageform', 'send_message.php', FORM_POST, true );

   switch( $mode )
   {
      case 'ShowMessage':
      case 'AlreadyDeclined':
      case 'AlreadyAccepted':
      case 'InviteDisputed':
      {
         message_info_table($mid, $date, $to_me,
                            $other_id, $other_name, $other_handle,
                            $Subject, $ReplyTo, $flow, $Text,
                            $folders, $Folder_nr, $message_form, $Replied=='M');

         if( $mode == 'AlreadyAccepted' )
         {
            echo '<font color=green>' .
               sprintf( T_('This %sgame%s invitation has already been accepted.'),
                        "<a href=\"game.php?gid=$Game_ID\">", '</a>' ) . '</font>';
         }
         else if( $mode == 'AlreadyDeclined' )
            echo '<font color=green>' .
               T_('This invitation has been declined or the game deleted') . '</font>';
         else if( $mode == 'InviteDisputed' )
            echo '<font color=green>' .
               sprintf(T_('The settings for this game invitation has been %sdisputed%s'),
                       "<a href=\"message.php?mid=$Game_mid\">", '</a>' ) . '</font>';

         if( $can_reply )
         {
           $message_form->add_row( array( 'HEADER', T_('Reply') ) );
           $message_form->add_row( array( 'HIDDEN', 'to', $other_handle ) );
           $message_form->add_row( array( 'HIDDEN', 'reply', $mid ) );
           $message_form->add_row( array( 'DESCRIPTION', T_('Subject'),
                                          'TEXTINPUT', 'subject', 50, 80, $default_subject );
           $message_form->add_row( array( 'DESCRIPTION', T_('Message'),
                                          'TEXTAREA', 'message', 50, 8, "" ) );
           $message_form->add_row( array( 'SUBMITBUTTON', 'send', T_('Send Reply') ) );
         }
      }
      break;

      case 'NewMessage':
      {
        $message_form->add_row( array( 'HEADER', T_('New message') ) );
        $message_form->add_row( array( 'DESCRIPTION', T_('To (userid)') ,
                                       'TEXTINPUT', 'to', 50, 80, $default_handle ) );
        $message_form->add_row( array( 'DESCRIPTION', T_('Subject'),
                                       'TEXTINPUT', 'subject', 50, 80, "" ) );
        $message_form->add_row( array( 'DESCRIPTION', T_('Message'),
                                       'TEXTAREA', 'message', 50, 8, "" ) );
        $message_form->add_row( array( 'SUBMITBUTTON', 'send', T_('Send Message') ) );
      }
      break;

      case 'ShowInvite':
      case 'ShowMyInvite':
      {
         message_info_table($mid, $date, $to_me,
                            $other_id, $other_name, $other_handle,
                            $Subject, $ReplyTo, $flow, $Text,
                            $folders, $Folder_nr, $message_form, ($mode=='ShowInvite' or $Replied=='M'));

         if( $Color == BLACK )
         {
            $colortxt = "<img src='17/w.gif' alt='" . T_('White') . "'> " .
               user_reference( 0, true, '', 0, $other_name, $other_handle) .
               "&nbsp;&nbsp;<img src='17/b.gif' alt='" . T_('Black') . "'> " .
               user_reference( 0, true, '', $player_row) .
               '&nbsp;&nbsp;';
         }
         else
         {
            $colortxt = "<img src='17/w.gif' alt='" . T_('White') . "'> " .
               user_reference( 0, true, '', $player_row) .
               "&nbsp;&nbsp;<img src='17/b.gif' alt='" . T_('Black') . "'> " .
               user_reference( 0, true, '', 0, $other_name, $other_handle) .
               '&nbsp;&nbsp;';
         }

         game_info_table($Size, $colortxt, $ToMove_ID, $Komi, $Handicap, $Maintime,
                         $Byotype, $Byotime, $Byoperiods, $Rated, $Weekendclock);

         if( $can_reply )
         {
            echo '<a href="message.php?mode=Dispute&mid=' . $mid . '">' .
               T_('Dispute settings') . '</a>';
            echo "<p>&nbsp;<p>\n";

            $message_form->add_row( array(
                  'HEADER', T_('Reply'),
               ) );
            $message_form->add_row( array(
                  'DESCRIPTION', T_('Message'),
                  'TEXTAREA', 'message', 50, 8, "",
               ) );
            $message_form->add_row( array(
                  'TEXT', '', 
                  'HIDDEN', 'to', $other_handle,
                  'HIDDEN', 'reply', $mid,
                  'HIDDEN', 'gid', $Game_ID,
                  'TD',
                  'SUBMITBUTTON', 'accepttype', T_('Accept'),
                  'SUBMITBUTTON', 'declinetype', T_('Decline'),
               ) );

         }
      }
      break;

      case 'Dispute':
      {
         message_info_table($mid, $date, $to_me,
                            $other_id, $other_name, $other_handle,
                            $Subject, $ReplyTo, $flow, $Text); //no folders, so no move

         $message_form->add_row( array( 'HEADER', T_('Dispute settings') ) );
         $message_form->add_row( array( 'HIDDEN', 'subject', 'Game invitation dispute' ) );
         $message_form->add_row( array( 'HIDDEN', 'disputegid', $Game_ID ) );
         $message_form->add_row( array( 'HIDDEN', 'to', $other_handle ) );
         $message_form->add_row( array( 'HIDDEN', 'reply', $mid ) );
         $message_form->add_row( array( 'HIDDEN', 'type', 'INVITATION' ) );
         $message_form->add_row( array( 'DESCRIPTION', T_('Message'),
                                        'TEXTAREA', 'message', 50, 8, "" ) );

         game_settings_form($message_form, $my_id, $Game_ID);

         $message_form->add_row( array( 'SUBMITBUTTON', 'send', T_('Send Reply') ) );
      }
      break;

      case 'Invite':
      {
         $message_form->add_row( array( 'HEADER', T_('Invitation message') ) );
         $message_form->add_row( array( 'HIDDEN', 'type', 'INVITATION' ) );
         $message_form->add_row( array( 'DESCRIPTION', T_('To (userid)'),
                                        'TEXTINPUT', 'to', 50, 80, $default_handle ) );
         $message_form->add_row( array( 'DESCRIPTION', T_('Message'),
                                        'TEXTAREA', 'message', 50, 8, "" ) );

         game_settings_form($message_form);

         $message_form->add_row( array( 'SUBMITBUTTON', 'send', T_('Send Invitation') ) );
      }
      break;
   }

   $message_form->echo_string();

   echo "</center>\n";

   end_page();
}

?>