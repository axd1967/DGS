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

define("FOLDER_ALL_RECEIVED", 0);
define("FOLDER_MAIN", 1);
define("FOLDER_NEW", 2);
define("FOLDER_REPLY", 3);
define("FOLDER_DELETED", 4);
define("FOLDER_SENT", 5);

$STANDARD_FOLDERS = array(
   FOLDER_ALL_RECEIVED => array(T_('All Received'),'f7f5e300','000000'),
   FOLDER_MAIN => array(T_('Main'), '00000000', '000000'),
   FOLDER_NEW => array(T_('New'), 'aaffaa90', '000000'),
   FOLDER_REPLY => array(T_('Reply!'), 'ffaaaa80', '000000'),
   FOLDER_DELETED => array(T_('Trashcan'), 'ff88ee00', '000000'),
   FOLDER_SENT => array(T_('Sent'), '00000000', '0000ff'));

// Prints game setting form used by invite.php

function game_settings_form(&$mform, $my_ID=NULL, $gid=NULL, $waiting_room=false)
{

   // Default values:
   $Size = 19;
   $Komi = 6.5;
   $Handicap = 0;
   $MyColor = 'White';
   $Maintime = 3;
   $MaintimeUnit = 'months';
   $Byotype = 'JAP';
   $Byotime_jap = 1;
   $ByotimeUnit_jap = 'days';
   $Byoperiods_jap = 10;
   $Byotime_can = 15;
   $ByotimeUnit_can = 'days';
   $Byoperiods_can = 15;
   $Byotime_fis = 1;
   $ByotimeUnit_fis = 'days';
   $Weekendclock = true;
   $Rated = true;
   $Handitype = 'conv';

   // If dispute, use values from game $gid
   if( $gid > 0 )
   {
      $result = mysql_query( "SELECT Handle,Size,Komi,Handicap,ToMove_ID," .
                             "Maintime,Byotype,Byotime,Byoperiods,Rated,Weekendclock, " .
                             "(White_ID=$my_ID)+1 AS Color " .
                             "FROM Games,Players WHERE Games.ID=$gid " .
                             "AND ((Players.ID=Black_ID AND White_ID=$my_ID) " .
                             "OR (Players.ID=White_ID AND Black_ID=$my_ID)) " .
                             "AND Status='INVITED'" );

      if( mysql_num_rows($result) != 1 )
         error("unknown_game");

      $game_row = mysql_fetch_array($result);

      extract($game_row);

      $MyColor = ( $Color == BLACK ? 'Black' : 'White' );
      $Rated = ( $Rated == 'Y' );
      $Weekendclock = ( $Weekendclock == 'Y' );

      $ByotimeUnit = 'hours';
      time_convert_to_longer_unit($Byotime, $ByotimeUnit);

      $MaintimeUnit = 'hours';
      time_convert_to_longer_unit($Maintime, $MaintimeUnit);

      $Handitype = 'manual';

      if( $ToMove_ID == -1 ) $Handitype = 'conv';
      else if( $ToMove_ID == -2 ) $Handitype = 'proper';
      else if( $ToMove_ID == -3 ) $Handitype = 'nigiri';
      else if( $ToMove_ID == -4 ) $Handitype = 'double';


      switch( $Byotype )
      {
         case 'JAP':
         {
            $Byotime_jap = $Byotime;
            $ByotimeUnit_jap = $ByotimeUnit;
            $Byoperiods_jap = $Byoperiods;
         }
         break;

         case 'CAN':
         {
            $Byotime_can = $Byotime;
            $ByotimeUnit_can = $ByotimeUnit;
            $Byoperiods_can = $Byoperiods;
         }
         break;

         case 'FIS':
         {
            $Byotime_fis = $Byotime;
            $ByotimeUnit_fis = $ByotimeUnit;
         }
         break;
      }

   }


   $value_array=array();
   for( $bs = 5; $bs <= 25; $bs++ )
     $value_array[$bs]=$bs;

   $mform->add_row( array( 'SPACE' ) );
   $mform->add_row( array( 'DESCRIPTION', T_('Board size'),
                           'SELECTBOX', 'size', 1, $value_array, $Size, false ) );

   $color_array = array( 'White' => T_('White'), 'Black' => T_('Black') );

   $handi_array=array( 0 => 0 );
   for( $bs = 2; $bs <= 20; $bs++ )
     $handi_array[$bs]=$bs;


   $mform->add_row( array( 'SPACE' ) );

   $mform->add_row( array( 'DESCRIPTION', T_('Conventional handicap (komi 0.5 if not even)'),
                           'RADIOBUTTONS', 'handicap_type', array('conv'=>''), $Handitype ) );

   $mform->add_row( array( 'DESCRIPTION', T_('Proper handicap'),
                           'RADIOBUTTONS', 'handicap_type', array('proper'=>''), $Handitype ) );

   if( !$waiting_room )
   {
      $mform->add_row( array( 'DESCRIPTION', T_('Manual setting'),
                              'RADIOBUTTONS', 'handicap_type', array('manual'=>''), $Handitype,
                              'TEXT', '&nbsp;&nbsp;&nbsp;' . T_('My color'),
                              'SELECTBOX', 'color', 1, $color_array, $MyColor, false,
                              'TEXT', '&nbsp;&nbsp;&nbsp;' . T_('Handicap'),
                              'SELECTBOX', 'handicap', 1, $handi_array, $Handicap, false,
                              'TEXT', '&nbsp;&nbsp;&nbsp;' . T_('Komi'),
                              'TEXTINPUT', 'komi_m', 5, 5, $Komi ) );
   }

   $mform->add_row( array( 'DESCRIPTION', T_('Even game with nigiri'),
                           'RADIOBUTTONS', 'handicap_type', array('nigiri'=>''), $Handitype,
                           'TEXT', '&nbsp;&nbsp;&nbsp;' . T_('Komi'),
                           'TEXTINPUT', 'komi_n', 5, 5, $Komi ) );

   $mform->add_row( array( 'DESCRIPTION', T_('Double game'),
                           'RADIOBUTTONS', 'handicap_type', array('double'=>''), $Handitype,
                           'TEXT', '&nbsp;&nbsp;&nbsp;' . T_('Komi'),
                           'TEXTINPUT', 'komi_d', 5, 5, $Komi ) );




   $value_array=array( 'hours' => T_('hours'),
                       'days' => T_('days'),
                       'months' => T_('months') );

   $mform->add_row( array( 'SPACE' ) );

   $mform->add_row( array( 'DESCRIPTION', T_('Main time'),
                           'TEXTINPUT', 'timevalue', 5, 5, $Maintime,
                           'SELECTBOX', 'timeunit', 1, $value_array, $MaintimeUnit, false ) );

   $mform->add_row( array( 'DESCRIPTION', T_('Japanese byo-yomi'),
                           'RADIOBUTTONS', 'byoyomitype', array( 'JAP' => '' ), $Byotype,
                           'TEXTINPUT', 'byotimevalue_jap', 5, 5, $Byotime_jap,
                           'SELECTBOX', 'timeunit_jap', 1,$value_array, $ByotimeUnit_jap, false,
                           'TEXT', T_('with') . '&nbsp;',
                           'TEXTINPUT', 'byoperiods_jap', 5, 5, $Byoperiods_jap,
                           'TEXT', T_('extra periods.') ) );

   $mform->add_row( array( 'DESCRIPTION', T_('Canadian byo-yomi'),
                           'RADIOBUTTONS', 'byoyomitype', array( 'CAN' => '' ), $Byotype,
                           'TEXTINPUT', 'byotimevalue_can', 5, 5, $Byotime_can,
                           'SELECTBOX', 'timeunit_can', 1,$value_array, $ByotimeUnit_can, false,
                           'TEXT', T_('for') . '&nbsp;',
                           'TEXTINPUT', 'byoperiods_can', 5, 5, $Byoperiods_can,
                           'TEXT', T_('stones') . '.' ) );

   $mform->add_row( array( 'DESCRIPTION', T_('Fischer time'),
                           'RADIOBUTTONS', 'byoyomitype', array( 'FIS' => '' ), $Byotype,
                           'TEXTINPUT', 'byotimevalue_fis', 5, 5, $Byotime_fis,
                           'SELECTBOX', 'timeunit_fis', 1,$value_array, $ByotimeUnit_fis, false,
                           'TEXT', T_('extra per move.') ) );

   $mform->add_row( array( 'SPACE' ) );

   $mform->add_row( array( 'DESCRIPTION', T_('Clock runs on weekends'),
                           'CHECKBOX', 'weekendclock', 'Y', "", $Weekendclock ) );
   $mform->add_row( array( 'DESCRIPTION', T_('Rated'),
                           'CHECKBOX', 'rated', 'Y', "", $Rated ) );
}

function message_info_table($date, $to_me, $sender_id, $sender_name, $sender_handle,
                            $subject, $reply_mid, $text,
                            $folders=null, $folder_nr=null, $form=null)
{
   global $date_fmt;

   echo "<table border=0 witdh=\"50%\">\n" .
      "<tr><td><b>" . T_('Date') . ":</b></td>" .
      "<td colspan=2>" . date($date_fmt, $date) . "</td></tr>\n" .
      "<tr><td><b>" . ($to_me ? T_('From') : T_('To') ) . ":</b></td>\n" .
      "<td colspan=2><A href=\"userinfo.php?uid=$sender_id\">$sender_name ($sender_handle)</A>" .
      "</td></tr>\n";

   echo "<tr><td><b>" . T_('Subject') . ":</b></td><td colspan=2>$subject</td></tr>\n" .
      "<tr><td valign=\"top\">" .
      ( $reply_mid > 0 ?
        "<a href=\"message.php?mode=ShowMessage&mid=$reply_mid\">" . T_('Replied') . ":</a>" :
        "<b>" . T_('Message') . ":</b>" ) .
      "</td>\n" .
      "<td align=\"center\" colspan=2>\n" .
      "<table border=2 align=center><tr>" .
      "<td width=475 align=left>" . make_html_safe($text, true) .
      "</td></tr></table><BR></td></tr>\n";

   if( isset($folders) )
   {
      echo "<tr><td><b>" . T_('Folder') . ":</b></td>\n<td><table cellpadding=3><tr>" .
         echo_folder_box($folders, $folder_nr, 'f7f5e3') . "</table></td>\n" .
         '<td>';

      $fld = array('' => '');
      foreach( $folders as $key => $val )
         if( $key != $folder_nr and ($to_me xor $key == FOLDER_SENT) and $key != FOLDER_NEW )
            $fld[$key] = $val[0];

      echo $form->print_insert_select_box('folder', '1', $fld, '', '') .
         $form->print_insert_submit_button('foldermove', T_('Move to folder')) .
         "</td></tr>\n";
   }


   echo "</table>\n";
}


function game_info_table($Size, $col, $handicap_type, $Komi, $Handicap,
                         $Maintime, $Byotype, $Byotime, $Byoperiods,
                         $Rated, $WeekendClock, $gid=NULL)
{
   echo '<table align=center border=2 cellpadding=3 cellspacing=3>' . "\n";

   if( $gid > 0 )
      echo "<tr><td><b>" . T_('Game ID') . "</b></td><td><a href=\"game.php?gid=$gid\">$gid</a></td></tr>\n";

   echo '<tr><td><b>' . T_('Size') . '<b></td><td>' . $Size . "</td></tr>\n";

   switch( $handicap_type )
   {
      case -1: // conventional handicap
         echo '<tr><td><b>' . T_('Handicap') . '</b></td><td>' .
            T_('Conventional handicap (komi 0.5 if not even)') . "</td></tr>\n";
         break;

      case -2: // Proper handicap
         echo '<tr><td><b>' . T_('Handicap') . '</b></td><td>' .
            T_('Proper handicap') . "</td></tr>\n";
         break;

      case -3: // Nigiri
         echo '<tr><td><b>' . T_('Komi') . '</b></td><td>' . $Komi . "</td></tr>\n";
         echo '<tr><td><b>' . T_('Colors') . '</b></td><td>' . T_('Nigiri') . "</td></tr>\n";
         break;

      case -4: // Double game
         echo '<tr><td><b>' . T_('Komi') . '</b></td><td>' . $Komi . "</td></tr>\n";
         echo '<tr><td><b>' . T_('Colors') . '</b></td><td>' .
            T_('Double game') . "</td></tr>\n";
         break;

      default:
         echo '<tr><td><b>' . T_('Colors') . "<b></td><td>$col</td></tr>\n";
         echo '<tr><td><b>' . T_('Komi') . '</b></td><td>' . $Komi . "</td></tr>\n" .
            '<tr><td><b>' . T_('Handicap') . '</b></td><td>' . $Handicap . "</td></tr>\n";
         break;
   }


   echo '<tr><td><b>' . T_('Main time') . '</b></td><td>' .
      echo_time($Maintime) . "</td></tr>\n";

   if( $Byotype == 'JAP' )
   {
      echo '<tr><td><b>' . T_('Byoyomi') . '</b></td><td> ' . T_('Japanese') . ': ' .
         sprintf(T_('%s per move and %s extra periods'), echo_time($Byotime), $Byoperiods) .
         ' </td></tr>' . "\n";
   }
   else if ( $Byotype == 'CAN' )
   {
      echo '<tr><td><b>' . T_('Byoyomi') . '</b></td><td> ' . T_('Canadian') . ': ' .
         sprintf(T_('%s per %s stones'), echo_time($Byotime), $Byoperiods) .
         ' </td></tr>' . "\n";
   }
   else if ( $Byotype == 'FIS' )
   {
      echo '<tr><td><b>' . T_('Fischer time') . '</b></td><td> ' .
         echo_time($Byotime) . ' ' . T_('extra per move') . ' </td></tr>' . "\n";
   }

    echo '<tr><td><b>' . T_('Rated') . '</b></td><td>' .
       ( $Rated == 'Y' ? T_('Yes') : T_('No') ) .
       '</td></tr><tr><td><b>' . T_('Clock runs on weekends') . '</b></td><td>' .
       ( $WeekendClock == 'Y' ? T_('Yes') : T_('No') ) . '</td></tr>
</table>
';

}


function interpret_time_limit_forms()
{
   global $hours, $timevalue, $timeunit, $byoyomitype, $byohours, $byoperiods,
      $byotimevalue_jap, $timeunit_jap, $byoperiods_jap,
      $byotimevalue_can, $timeunit_can, $byoperiods_can,
      $byotimevalue_fis, $timeunit_fis;

      $hours = $timevalue;
      if( $timeunit != 'hours' )
         $hours *= 15;
      if( $timeunit == 'months' )
         $hours *= 30;

      if( $hours > 5475 ) $hours = 5475;
      if( $hours < 0 ) $hour = 0;

      if( $byoyomitype == 'JAP' )
      {
         $byohours = $byotimevalue_jap;
         if( $timeunit_jap != 'hours' ) $byohours *= 15;
         if( $timeunit_jap == 'months' ) $byohours *= 30;

         if( $byohours > 5475 ) $byohours = 5475;
         if( $byohours < 0 ) $byohour = 0;

         $byoperiods = $byoperiods_jap;
         if( $byohours * ($byoperiods+1) > 5475 )
            $byoperiods = floor(5475/$byohours) - 1;
      }
      else if( $byoyomitype == 'CAN' )
      {
         $byohours = $byotimevalue_can;
         if( $timeunit_can != 'hours' ) $byohours *= 15;
         if( $timeunit_can == 'months' ) $byohours *= 30;
         if( $byohours < 0 ) $byohour = 0;
         if( $byohours > 5475 ) $byohours = 5475;

         $byoperiods = $byoperiods_can;
         if( $byoperiods < 1 ) $byoperiods = 1;
      }
      else if( $byoyomitype == 'FIS' )
      {
         $byohours = $byotimevalue_fis;

         if( $timeunit_fis != 'hours' )
            $byohours *= 15;
         if( $timeunit_fis == 'months' )
            $byohours *= 30;

         if( $byohours < 0 ) $byohours = 0;
         if( $byohours > $hours ) $byohours = $hours;

         $byoperiods = 0;
      }

}

function get_folders($uid, $remove_all_received=true)
{
   global $STANDARD_FOLDERS;

   $result = mysql_query("SELECT * FROM Folders WHERE uid=$uid ORDER BY Folder_nr")
      or die(mysql_error());

   $flds = $STANDARD_FOLDERS;
   if( $remove_all_received )
      unset($flds[FOLDER_ALL_RECEIVED]);

   while( $row = mysql_fetch_array($result) )
   {
      if( empty($row['Name']))
         $row['Name'] = ( $row['Folder_nr'] < 6 ?
                          $STANDARD_FOLDERS['Folder_nr'][0] : T_('Folder name') );
      $flds[$row['Folder_nr']] = array($row['Name'], $row['BGColor'], $row['FGColor']);
   }

   return $flds;
}

function change_folders_for_marked_messages($uid, $folders)
{

   if( isset($_GET['move_marked']) )
   {
      $new_folder = $_GET['folder'];
      if( !isset($folders[$new_folder]) or
          $new_folder == FOLDER_NEW or $new_folder == FOLDER_ALL_RECEIVED )
         error('folder_not_found');
   }
   else if( isset($_GET['destory_marked'] ) )
      $new_folder = "NULL";
   else
      return;

   if( $new_folder == FOLDER_SENT )
      $sender_where_clause = 'AND Sender="Y"';
   if( $new_folder == FOLDER_REPLY )
      $sender_where_clause = 'AND Sender="N"';


   $message_ids = array();
   foreach( $_GET as $key => $val )
      {
         if( preg_match("/^mark(\d+)$/", $key, $matches) )
            array_push($message_ids, $matches[1]);
      }

   if( count($message_ids) )
   {
      mysql_query("UPDATE MessageCorrespondents SET Folder_nr=$new_folder " .
                  "WHERE uid='$uid' $sender_where_clause " .
                  "AND mid IN (" . implode(',', $message_ids) . ") " .
                  "LIMIT " . count($message_ids) )
         or die(mysql_error());
   }
}

function echo_folders($folders, $current_folder)
{
   global $STANDARD_FOLDERS;

   $string = '<table align=center border=0 cellpadding=0 cellspacing=17><tr>' . "\n" .
      '<td><b>' . T_('Folder') . ":&nbsp;&nbsp;&nbsp;</b></td>\n";

   $folders[FOLDER_ALL_RECEIVED] = $STANDARD_FOLDERS[FOLDER_ALL_RECEIVED];
   ksort($folders);

   foreach( $folders as $nr => $val )
      {
         list($name, $color) = $val;
         if( $nr == $current_folder )
            $string .= "<td><div style=\"align:right; color: black; border: solid #6666ff; padding: 7px;\">$name</div></td>\n";
            else
         $string .= "<td><a href=\"list_messages.php?folder=$nr\">$name</a></td>\n";
      }

   $string .= '</tr></table>' . "\n";

   return $string;
}

function folder_is_removable($nr, $uid)
{
   $result = mysql_query("SELECT ID FROM MessageCorrespondents " .
                         "WHERE uid='$uid' AND Folder_nr='$nr' LIMIT 1");

   return (mysql_num_rows($result) === 0);
}

function echo_folder_box($folders, $folder_nr, $bgcolor)
{
      list($foldername, $folderbgcolor, $folderfgcolor) = $folders[$folder_nr];
      $folderbgcolor = blend_alpha_hex($folderbgcolor, $bgcolor);

      if( empty($foldername) )
         $foldername = ( $folder_nr <= 5 ? $STANDARD_FOLDERS[$folder_nr][0]
                         : T_('Folder name') );

      return "<td bgcolor=\"#$folderbgcolor\" nowrap><font color=\"#$folderfgcolor\">$foldername</font></td>";

}
?>
