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

require( "include/std_functions.php" );
require( "include/message_functions.php" );
require( "include/form_functions.php" );


// Input variables:

$mid = $_GET['mid'];
$mode = $_GET['mode'];

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $my_id = $player_row["ID"];
   $can_reply = true;
   if( !$mode )
      $mode = "NewMessage";

   if( !$uid )
   {
      if( eregi("uid=([0-9]+)", $HTTP_REFERER, $result) )
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

   $folders = get_folders($my_id);

   if( isset($_POST['foldermove']) and isset($folders[$_POST['folder']]) )
   {
      mysql_query( "UPDATE MessageCorrespondents SET Folder_nr='{$_POST['folder']}' " .
                   "WHERE uid='$my_id' AND mid='$mid' LIMIT 1" );
   }

   if( $mode == 'ShowMessage' or $mode == 'Dispute' )
   {
      if( !($mid > 0) )
         error("unknown_message");

      $result = mysql_query("SELECT Messages.*, me.Sender, me.Folder_nr, me.Replied, " .
                            "UNIX_TIMESTAMP(Messages.Time) AS date, " .
                            "Players.Name AS sender_name, " .
                            "Players.Handle AS sender_handle, Players.ID AS sender_id, " .
                            "Games.Status, Size, Komi, Handicap, Maintime, Byotype, " .
                            "Byotime, Byoperiods, Rated, Weekendclock, " .
                            "ToMove_ID, (White_ID=$my_id)+1 AS Color " .
                            "FROM Messages, MessageCorrespondents AS me, " .
                            "MessageCorrespondents AS other, Players " .
                            "LEFT JOIN Games ON Games.ID=Game_ID " .
                            "WHERE Messages.ID=$mid AND me.mid=$mid AND me.uid=$my_id " .
                            "AND other.mid=$mid AND other.uid!=$my_id " .
                            "AND Players.ID=other.uid");

      if( @mysql_num_rows($result) != 1 )
         error("unknown_message");

      $row = mysql_fetch_array($result);

      extract($row);

      $sender_name = make_html_safe($sender_name);
      $sender_handle_safe = make_html_safe($sender_handle);

      $to_me = $can_reply = ( $To_ID == $my_id );


      if( $mode == 'ShowMessage' or !$can_reply )
      {
         $default_subject = $Subject;
         if( strcasecmp(substr($default_subject,0,3), "re:") != 0 )
            $default_subject = "RE: " . $default_subject;

         if( $Folder_nr == FOLDER_NEW )
         {
            // Remove NEW flag
            mysql_query( "UPDATE MessageCorrespondents SET Folder_nr=" . FOLDER_MAIN . " " .
                         "WHERE mid=$mid AND uid=$my_id LIMIT 1" )
               or die( mysql_error());

            if( mysql_affected_rows() != 1)
               error("mysql_message_info", 'remove new-flag failed');
         }

         if( $Type == 'INVITATION' )
         {
            if( $Status=='INVITED' and !($Replied == 'N'))
            {
               $mode = 'ShowInvite';
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
      }

   }

   start_page("Message - $mode", true, $logged_in, $player_row );

   echo "<center>\n";

   $message_form = null;
   switch( $mode )
   {
      case 'ShowMessage':
      case 'AlreadyDeclined':
      case 'AlreadyAccepted':
      {
         $message_form = new Form( 'messageform', 'send_message.php', FORM_GET );
         message_info_table($date, $can_reply, $sender_id, $sender_name, $sender_handle_safe,
                            $Subject, $ReplyTo, $Text, $folders, $Folder_nr, $message_form);
         if( $mode == 'AlreadyAccepted' )
         {
            echo '<font color=green>';
            printf( T_('This %sgame%s invitation has already been accepted.'),
                    "<a href=\"game.php?gid=$Game_ID\">", '</a>' );
            echo '</font>';
         }
         if( $mode == 'AlreadyDeclined' )
            echo '<font color=green>' .
               T_('This invitation has been declined or the game deleted') . '</font>';

         if( $can_reply )
            {
              $message_form->add_row( array( 'HEADER', T_('Reply') ) );
              $message_form->add_row( array( 'HIDDEN', 'to', $sender_handle ) );
              $message_form->add_row( array( 'HIDDEN', 'reply', $mid ) );
              $message_form->add_row( array( 'DESCRIPTION', T_('Subject'),
                                             'TEXTINPUT', 'subject', 50, 80,
                                             $default_subject ) );
              $message_form->add_row( array( 'DESCRIPTION', T_('Message'),
                                             'TEXTAREA', 'message', 50, 8, "" ) );
              $message_form->add_row( array( 'SUBMITBUTTON', 'send', T_('Send Reply') ) );
            }
         }
      break;

      case 'NewMessage':
      {
        $message_form = new Form( 'messageform', 'send_message.php', FORM_GET );
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
         $message_form = new Form( 'messageform', 'send_message.php', FORM_GET );
         message_info_table($date, $can_reply, $sender_id, $sender_name, $sender_handle_safe,
                            $Subject, $ReplyTo, $Text, $folders, $Folder_nr, $message_form);

         if( $Color == BLACK )
         {
            $color = "<img src='17/w.gif' alt='" . T_('White') . "'> " .
               "$sender_name ($sender_handle_safe)" .
               " &nbsp;&nbsp;<img src='17/b.gif' alt='" . T_('Black') . "'> " .
               make_html_safe($player_row["Name"]) .
               ' (' . make_html_safe($player_row["Handle"]) . ')';
         }
         else
         {
            $color = "<img src='17/w.gif' alt='" . T_('White') . "'> " .
               make_html_safe($player_row["Name"]) .
               ' (' . make_html_safe($player_row["Handle"]) . ')' .
               " &nbsp;&nbsp;<img src='17/b.gif' alt='" . T_('Black') . "'> " .
               "$sender_name ($sender_handle_safe) &nbsp;&nbsp;";
         }

         game_info_table($Size, $color, $ToMove_ID, $Komi, $Handicap, $Maintime,
                         $Byotype, $Byotime, $Byoperiods, $Rated, $Weekendclock);

         if( $can_reply )
         {
            echo '<a href="message.php?mode=Dispute&mid=' . $mid . '">' .
               T_('Dispute settings') . '</a>';
            echo "<p>&nbsp;<p>\n";

            $message_form->add_row( array( 'HEADER', T_('Reply') ) );
            $message_form->add_row( array( 'HIDDEN', 'to', $sender_handle ) );
            $message_form->add_row( array( 'HIDDEN', 'reply', $mid ) );
            $message_form->add_row( array( 'HIDDEN', 'gid', $Game_ID ) );
            $message_form->add_row( array( 'DESCRIPTION', T_('Message'),
                                           'TEXTAREA', 'message', 50, 8, "" ) );
            $message_form->add_row( array( 'TEXT', '',
                                           'TD',
                                           'SUBMITBUTTON', 'accepttype', T_('Accept'),
                                           'SUBMITBUTTON', 'declinetype', T_('Decline') ) );
         }
      }
      break;

      case 'Dispute':
      {
         message_info_table($date, $can_reply, $sender_id, $sender_name, $sender_handle_safe,
                            $Subject, $ReplyTo, $Text);

         $message_form = new Form( 'messageform', 'send_message.php', FORM_GET );
         $message_form->add_row( array( 'HEADER', T_('Dispute settings') ) );
         $message_form->add_row( array( 'HIDDEN', 'mode', $mode ) );
         $message_form->add_row( array( 'HIDDEN', 'subject', 'Game invitation dispute' ) );
         $message_form->add_row( array( 'HIDDEN', 'disputegid', $Game_ID ) );
         $message_form->add_row( array( 'HIDDEN', 'to', $sender_handle ) );
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
         $message_form = new Form( 'messageform', 'send_message.php', FORM_GET );
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

   if( !is_null($message_form) )
     $message_form->echo_string();

   echo "</center>\n";

   end_page();
}
?>