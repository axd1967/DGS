<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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

require( "include/std_functions.php" );
require( "include/message_functions.php" );
require( "include/form_functions.php" );


// Input variables:
//
// $mid        -- Message ID
// $mode       -- NewMessage(Default),ShowMessage,Dispute or Invite
// $disputegid -- game ID for dispute
//

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


   if( $mode == 'ShowMessage' or $mode == 'Dispute' )
   {
      if( !($mid > 0) )
         error("unknown_message");

      $result = mysql_query("SELECT Messages.*, " .
                            "UNIX_TIMESTAMP(Messages.Time) AS date, " .
                            "Players.Name AS sender_name, " .
                            "Players.Handle AS sender_handle, Players.ID AS sender_id, " .
                            "Games.Status, Size, Komi, Handicap, Maintime, Byotype, " .
                            "Byotime, Byoperiods, Rated, Weekendclock, " .
                            "ToMove_ID, (White_ID=$my_id)+1 AS Color " .
                            "FROM Messages,Players " .
                            "LEFT JOIN Games ON Games.ID=Game_ID " .
                            "WHERE Messages.ID=$mid " .
                            "AND ((Messages.To_ID=$my_id AND From_ID=Players.ID) " .
                            "OR (Messages.From_ID=$my_id AND To_ID=Players.ID))");

      if( @mysql_num_rows($result) != 1 )
         error("unknown_message");

      $row = mysql_fetch_array($result);

      extract($row);

      $to_me = $can_reply = ( $To_ID == $my_id );
      $has_replied = !(strpos($Flags,'REPLIED') === false);


      if( $mode == 'ShowMessage' or !$can_reply )
      {
         $default_subject = $Subject;
         if( strcasecmp(substr($Subject,0,3), "re:") != 0 )
            $default_subject = "RE: " . $Subject;

         // Remove NEW flag
         $pos = strpos($Flags,'NEW');

         if( $to_me and !($pos === false) )
         {
            $Flags = substr_replace($Flags, '', $pos, 3);

            mysql_query( "UPDATE Messages SET Flags='$Flags' " .
                         "WHERE ID=$mid AND To_ID=$my_id LIMIT 1" ) or die( mysql_error());

            if( mysql_affected_rows() != 1)
               error("mysql_message_info", true);
         }

         if( $Type=='INVITATION' )
         {
            if( $can_reply and $Status=='INVITED' and !$has_replied)
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

   switch( $mode )
   {
      case 'ShowMessage':
      case 'AlreadyDeclined':
      case 'AlreadyAccepted':
      {
         message_info_table($date, $can_reply, $sender_id, $sender_name, $sender_handle,
                            $Subject, $ReplyTo, $Text);
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
            echo "<B><h3><font color=$h3_color>" . T_('Reply') . ":</font></B><p>\n";
            echo form_start( 'messageform', 'send_message.php', 'POST' );
            echo form_insert_row( 'HIDDEN', 'to', $sender_handle );
            echo form_insert_row( 'HIDDEN', 'reply', $mid );
            echo form_insert_row( 'DESCRIPTION', T_('Subject'),
                                  'TEXTINPUT', 'subject', 50, 80, $default_subject );
            echo form_insert_row( 'DESCRIPTION', T_('Message'),
                                  'TEXTAREA', 'message', 50, 8, "" );
            echo form_insert_row( 'SUBMITBUTTON', 'send', T_('Send Reply') );
            }
         }
      break;

      case 'NewMessage':
      {
         echo "<B><h3><font color=$h3_color>" . T_('New message') . ":</font></B><p>\n";
         echo form_start( 'messageform', 'send_message.php', 'POST' );
         echo form_insert_row( 'DESCRIPTION', T_('To (userid)'),
                               'TEXTINPUT', 'to', 50, 80, $default_handle );
         echo form_insert_row( 'DESCRIPTION', T_('Subject'),
                               'TEXTINPUT', 'subject', 50, 80, "" );
         echo form_insert_row( 'DESCRIPTION', T_('Message'),
                               'TEXTAREA', 'message', 50, 8, "" );
         echo form_insert_row( 'SUBMITBUTTON', 'send', T_('Send Message') );
      }
      break;

      case 'ShowInvite':
      {
         message_info_table($date, $can_reply, $sender_id, $sender_name, $sender_handle,
                            $Subject, $ReplyTo, $Text);

         if( $Color == BLACK )
         {
            $color = "<img src='17/w.gif' alt='" . T_('white') . "'> " .
               "$sender_name ($sender_handle)" .
               " &nbsp;&nbsp;<img src='17/b.gif' alt='" . T_('black') . "'> " .
               $player_row["Name"] .' (' . $player_row["Handle"] . ')';
         }
         else
         {
            $color = "<img src='17/w.gif' alt='" . T_('white') . "'> " .
               $player_row["Name"] .' (' . $player_row["Handle"] . ')' .
               " &nbsp;&nbsp;<img src='17/b.gif' alt='" . T_('black') . "'> " .
               "$sender_name ($sender_handle) &nbsp;&nbsp;";
         }

         game_info_table($Size, $color, $ToMove_ID, $Komi, $Handicap, $Maintime,
                         $Byotype, $Byotime, $Byoperiods, $Rated, $Weekendclock);
         echo '<a href="message.php?mode=Dispute&mid=' . $mid . '">Dispute settings</a>';
         echo "<p>&nbsp;<p><B><h3><font color=$h3_color>Reply:</font></B>\n";
         echo form_start( 'messageform', 'send_message.php', 'POST' );
         echo form_insert_row( 'HIDDEN', 'to', $sender_handle );
         echo form_insert_row( 'HIDDEN', 'reply', $mid );
         echo form_insert_row( 'HIDDEN', 'gid', $Game_ID );
         echo form_insert_row( 'DESCRIPTION', T_('Message'),
                               'TEXTAREA', 'message', 50, 8, "" );
         echo '<td><td><INPUT type="submit" name="accepttype" value="' .T_('Accept') . '">' .
            '<INPUT type="submit" name="declinetype" value="' . T_('Decline') . '"></td>';
      }
      break;

      case 'Dispute':
      {
         message_info_table($date, $can_reply, $sender_id, $sender_name, $sender_handle,
                            $Subject, $ReplyTo, $Text);

         echo "<B><h3><font color=$h3_color>" . T_('Disputing settings') . ":</font></B><p>\n";
         echo form_start( 'messageform', 'send_message.php', 'POST' );
         echo form_insert_row( 'HIDDEN', 'mode', $mode );
         echo form_insert_row( 'HIDDEN', 'subject', 'Game invitation dispute' );
         echo form_insert_row( 'HIDDEN', 'disputegid', $disputegid );
         echo form_insert_row( 'HIDDEN', 'to', $sender_handle );
         echo form_insert_row( 'HIDDEN', 'reply', $mid );
         echo form_insert_row( 'HIDDEN', 'type', 'INVITATION' );
         echo form_insert_row( 'DESCRIPTION', T_('Message'),
                               'TEXTAREA', 'message', 50, 8, "" );

         game_settings_form($my_id, $Game_ID);

         echo form_insert_row( 'SUBMITBUTTON', 'send', T_('Send Reply') );
      }
      break;

      case 'Invite':
      {
         echo "<B><h3><font color=$h3_color>" . T_('Invitation message') . ":</font></B><p>\n";
         echo form_start( 'messageform', 'send_message.php', 'POST' );
         echo form_insert_row( 'HIDDEN', 'type', 'INVITATION' );
         echo form_insert_row( 'DESCRIPTION', T_('To (userid)'),
                               'TEXTINPUT', 'to', 50, 80, $default_handle );
         echo form_insert_row( 'DESCRIPTION', T_('Message'),
                               'TEXTAREA', 'message', 50, 8, "" );

         game_settings_form();

         echo form_insert_row( 'SUBMITBUTTON', 'send', T_('Send Invitation') );
      }
      break;
   }

   if( $can_reply )
      echo form_end();

   echo "</center>\n";

   end_page();
}
?>