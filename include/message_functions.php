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


function init_standard_folders()
{
   global $STANDARD_FOLDERS;
   $STANDARD_FOLDERS = array(
      FOLDER_ALL_RECEIVED => array(T_('All Received'),'f7f5e300','000000'),
      FOLDER_MAIN => array(T_('Main'), '00000000', '000000'),
      FOLDER_NEW => array(T_('New'), 'aaffaa90', '000000'),
      FOLDER_REPLY => array(T_('Reply!'), 'ffaaaa80', '000000'),
      FOLDER_DELETED => array(T_('Trashcan'), 'ff88ee00', '000000'),
      FOLDER_SENT => array(T_('Sent'), '00000000', '0000ff'),
      );
}

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
                             "IF(White_ID=$my_ID," . WHITE . "," . BLACK . ") AS Color " .
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


define('FLOW_ANSWER'  ,0x1);
define('FLOW_ANSWERED',0x2);
   $msg_icones = array(
  0                         => array('msg'   ,'&nbsp;-&nbsp;'),
  FLOW_ANSWER               => array('msg_lr','&gt;-&nbsp;'), //is an answer
              FLOW_ANSWERED => array('msg_rr','&nbsp;-&gt;'), //is answered
  FLOW_ANSWER|FLOW_ANSWERED => array('msg_2r','&gt;-&gt;'),
      );

function message_info_table($mid, $date, $to_me,
                            $other_id, $other_name, $other_handle, //must be html_safe
                            $subject, $reply_mid, $flow, $text, //must NOT be html_safe
                            $folders=null, $folder_nr=null, $form=null, $delayed_move=false)
{
   global $date_fmt, $msg_icones, $bg_color;

   if( $other_id > 0 )
   {
     $name = user_reference( true, false, '', $other_id, $other_name, $other_handle) ;
   }
   else
     $name = $other_name; //i.e. T_("Server message");

   echo "<table border=0 witdh=\"50%\">\n" .
      "<tr><td><b>" . T_('Date') . ":</b></td>" .
      "<td colspan=2>" . date($date_fmt, $date) . "</td></tr>\n" .
      "<tr><td><b>" . ($to_me ? T_('From') : T_('To') ) . ":</b></td>\n" .
      "<td colspan=2>$name</td>" .
      "</tr>\n";

   echo "<tr><td><b>" . T_('Subject') . ":</b></td><td colspan=2>" .
      make_html_safe($subject, true) . "</td></tr>\n" .
      "<tr><td valign=\"top\">" ;

   echo "<b>" . T_('Message') . ":</b>" ;
   $str = '';
   if( $flow & FLOW_ANSWER && $reply_mid > 0 )
   {
      list($ico,$alt) = $msg_icones[FLOW_ANSWER];
      $str.= "<a href=\"message.php?mode=ShowMessage&mid=$reply_mid\">" .
             "<img border=0 alt='$alt' src='images/$ico.gif'"
             . ' title="' . T_("Previous message") . '"'
             . "></a>&nbsp;" ;
   }
   if( $flow & FLOW_ANSWERED )
   {
      list($ico,$alt) = $msg_icones[FLOW_ANSWERED];
      $str.= "<a href=\"list_messages.php?find_answers=$mid\">" .
             "<img border=0 alt='$alt' src='images/$ico.gif'"
             . ' title="' . T_("Next messages") . '"'
             . "></a>&nbsp;" ;
   }
   if( $str )
     echo "<center>$str</center>";

   echo "</td>\n" .

      "<td align=\"center\" colspan=2>\n" .
      "<table border=2 align=center><tr>" .
      "<td width=475 align=left>" . make_html_safe($text, true) .
      "</td></tr></table><BR></td></tr>\n";

   if( isset($folders) )
   {
      echo "<tr><td><b>" . T_('Folder') . ":</b></td>\n<td><table cellpadding=3><tr>" .
         echo_folder_box($folders, $folder_nr, substr($bg_color, 2, 6))
          . "</table></td>\n<td>";

      $deleted = ( is_null($folder_nr) );
      if( !$deleted )
      {

         $fld = array('' => '');
         foreach( $folders as $key => $val )
            if( $key != $folder_nr and (!$to_me or $key != FOLDER_SENT) and $key != FOLDER_NEW )
               $fld[$key] = $val[0];

         echo $form->print_insert_select_box('folder', '1', $fld, '', '');
         if( $delayed_move )
            echo T_('Move to folder when replying');
         else
         {
            echo $form->print_insert_submit_button('foldermove', T_('Move to folder'));
            echo $form->print_insert_hidden_input("mark$mid", 'Y') ;
            if( $folder_nr != FOLDER_ALL_RECEIVED )
               echo $form->print_insert_hidden_input("current_folder", $folder_nr) ;
         }
         echo $form->print_insert_hidden_input('messageid', $mid) ;
      }

      echo "\n</td></tr>\n";
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


//Set global $hours,$byohours,$byoperiods
function interpret_time_limit_forms()
{
   global $hours, $byohours, $byoperiods; //outputs
   global $byoyomitype, $timevalue, $timeunit, //inputs
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

   while( $row = mysql_fetch_array($result) )
   {
      if( empty($row['Name']))
         $row['Name'] = ( $row['Folder_nr'] < USER_FOLDERS ?
                          $STANDARD_FOLDERS[$row['Folder_nr']][0] : T_('Folder name') );
      $flds[$row['Folder_nr']] = array($row['Name'], $row['BGColor'], $row['FGColor']);
   }

   if( $remove_all_received )
      unset($flds[FOLDER_ALL_RECEIVED]);

   return $flds;
}

function change_folders_for_marked_messages($uid, $folders)
{

   if( isset($_GET['move_marked']) )
   {
      $new_folder = $_GET['folder'];
   }
   else if( isset($_GET['destroy_marked'] ) )
   {
      $new_folder = "NULL";
   }
   else
      return -1; //i.e. no move query

   $message_ids = array();
   foreach( $_GET as $key => $val )
   {
      if( preg_match("/^mark(\d+)$/", $key, $matches) )
         array_push($message_ids, $matches[1]);
   }

   return change_folders($uid, $folders, $message_ids, $new_folder, @$_GET['current_folder']);
}

function change_folders($uid, $folders, $message_ids, $new_folder, $current_folder=false, $need_replied=false)
{

   if( count($message_ids) <= 0 )
      return 0;

   if( $new_folder == "NULL" )
   {
      $where_clause = "AND Folder_nr='" .FOLDER_DELETED. "' ";      
   }
   else
   {
      if( !isset($new_folder) or !isset($folders[$new_folder])
        or $new_folder == FOLDER_NEW or $new_folder == FOLDER_ALL_RECEIVED )
         error('folder_not_found');

      if( $new_folder == FOLDER_SENT )
         $where_clause = "AND (Sender='Y' or Sender='M') ";
      else if( $new_folder == FOLDER_REPLY )
         $where_clause = "AND (Sender='N' or Sender='M') ";
      else
         $where_clause = '';

      if( $current_folder && $current_folder!=FOLDER_ALL_RECEIVED  && $current_folder!='NULL' )
         $where_clause.= "AND Folder_nr='" .$current_folder. "' ";      
   }

   if( $need_replied )
      $where_clause.= "AND Replied='Y' ";
   else
      $where_clause.= "AND Replied!='M' ";

   mysql_query("UPDATE MessageCorrespondents SET Folder_nr=$new_folder " .
               "WHERE uid='$uid' $where_clause" .
               "AND NOT ISNULL(Folder_nr) " .
               "AND mid IN (" . implode(',', $message_ids) . ") " .
               "LIMIT " . count($message_ids) )
      or error("mysql_query_failed"); //die(mysql_error());
   return mysql_affected_rows() ;
}

function echo_folders($folders, $current_folder)
{
   global $STANDARD_FOLDERS;

   $string = '<table align=center border=0 cellpadding=0 cellspacing=7><tr>' . "\n" .
      '<td><b>' . T_('Folder') . ":&nbsp;&nbsp;&nbsp;</b></td>\n";

   $folders[FOLDER_ALL_RECEIVED] = $STANDARD_FOLDERS[FOLDER_ALL_RECEIVED];
   ksort($folders);

   foreach( $folders as $nr => $val )
   {
      list($name, $color, $fcol) = $val;
      $name = make_html_safe($name);
      $string .= '<td bgcolor="#' .blend_alpha_hex($color). '"' ;
      if( $nr == $current_folder)
         $string .= " style=\"border:'3px solid #6666ff'; padding:4px; color:'#" .
                    $fcol. "'\">$name</td>\n";
      else
         $string .= " style=\"padding:7px;\"><a style=\"color:'#" .
                    $fcol. "'\" href=\"list_messages.php?folder=$nr\">$name</a></td>\n";
   }

   $string .= '</tr></table>' . "\n";

   return $string;
}

function folder_is_empty($nr, $uid)
{
   $result = mysql_query("SELECT ID FROM MessageCorrespondents " .
                         "WHERE uid='$uid' AND Folder_nr='$nr' LIMIT 1");

   return (mysql_num_rows($result) === 0);
}

function echo_folder_box($folders, $folder_nr, $bgcolor)
{
 global $STANDARD_FOLDERS;

   if ( is_null($folder_nr) ) //case of $deleted messages
     list($foldername, $folderbgcolor, $folderfgcolor) = array('---',0,0);
   else
     list($foldername, $folderbgcolor, $folderfgcolor) = @$folders[$folder_nr];

   if( empty($foldername) )
     if ( $folder_nr < USER_FOLDERS )
       list($foldername, $folderbgcolor, $folderfgcolor) = $STANDARD_FOLDERS[$folder_nr];
     else
       $foldername = T_('Folder name');

   $folderbgcolor = blend_alpha_hex($folderbgcolor, $bgcolor);
   if( empty($folderfgcolor) )
      $folderfgcolor = "000000" ;

   return "<td bgcolor=\"#$folderbgcolor\"><font color=\"#$folderfgcolor\">".
          make_html_safe($foldername) . "</font></td>";
}

function message_list_query($my_id, $folderstring='all', $order='date', $limit='', $extra_where='')
{
//    $rec_query = "SELECT UNIX_TIMESTAMP(Messages.Time) AS date, " .
//       "Messages.ID AS mid, Messages.Subject, Messages.Replied, " .
//       "Players.Name AS other_name, To_Folder_nr AS folder " .
//       "FROM Messages, Players " .
//       "WHERE obsolet(To_ID)=$my_id AND To_Folder_nr IN ($folderstring) AND To_ID=Players.ID " .
//       "ORDER BY $order $limit";

//    $sent_query = "SELECT UNIX_TIMESTAMP(Messages.Time) AS date, " .
//       "Messages.ID AS mid, Messages.Subject, Messages.Replied, " .
//       "Players.Name AS other_name, From_Folder_nr AS folder " .
//       "FROM Messages, Players " .
//       "WHERE obsolet(From_ID)=$my_id AND From_Folder_nr IN ($folderstring) AND obsolet(To_ID)=Players.ID " .
//       "ORDER BY $order $limit";


// for mysql 4.0

//    $l = $_GET['from_row']+$MaxRowsPerPage;
//    $query = "(SELECT UNIX_TIMESTAMP(Messages.Time) AS date, " .
//       "Messages.ID AS mid, Messages.Subject, Messages.Replied, " ,
//       "Players.Name AS other_name, From_Folder_nr AS folder " .
//       "FROM Messages, Players WHERE obsolet(From_ID)=$my_id AND From_Folder_nr IN ($folderstring) " .
//       "AND obsolet(To_ID)=Players.ID order by $order limit $l)" .
//       "UNION " .
//       "(SELECT UNIX_TIMESTAMP(Messages.Time) AS date, " .
//       "Messages.ID AS mid, Messages.Subject, Messages.Replied, " .
//       "Players.Name AS other_name, To_Folder_nr AS folder " .
//       "FROM Messages, Players WHERE obsolet(To_ID)=$my_id AND To_Folder_nr IN ($folderstring) " .
//       "AND obsolet(From_ID)=Players.ID order by $order limit $l)" .
//       "ORDER BY $order $limit";

   $query = "SELECT Messages.Type, Messages.Subject, " .
      "UNIX_TIMESTAMP(Messages.Time) AS Time, me.mid as date, " .
      "IF(Messages.ReplyTo>0,".FLOW_ANSWER.",0)+IF(me.Replied='Y' or other.Replied='Y',".FLOW_ANSWERED.",0) AS flow, " .
      "me.mid, me.Replied, me.Sender, me.Folder_nr AS folder, " .
      "IF(me.sender='M',' ',Players.Name) AS other_name, " . //the ' ' help to sort
      "Players.ID AS other_ID " .
      "FROM Messages, MessageCorrespondents AS me " .
      "LEFT JOIN MessageCorrespondents AS other " .
        "ON other.mid=me.mid AND other.Sender!=me.Sender " .
      "LEFT JOIN Players ON Players.ID=other.uid " .
      "WHERE me.uid=$my_id AND Messages.ID=me.mid $extra_where " .
        ( $folderstring=="all" ? "" : "AND me.Folder_nr IN ($folderstring) " ) .
      "ORDER BY $order $limit";

   $result = mysql_query( $query ) or error("mysql_query_failed"); //die(mysql_error());
   return $result;
}

function message_list_table( &$mtable, $result, $show_rows
             , $current_folder, $my_folders
             , $no_sort=true, $no_mark=true, $toggle_marks=false
             )
{
 global $date_fmt, $msg_icones;

   $can_move_messages = false;

   $mtable->add_tablehead( 1, T_('Folder'), ( $no_sort or $current_folder>FOLDER_ALL_RECEIVED ) ? NULL : 
                           'folder', true, true );
   $mtable->add_tablehead( 2, ($current_folder == FOLDER_SENT ? T_('To') : T_('From') ),
                           $no_sort ? NULL : 'other_name', false, true );
   $mtable->add_tablehead( 3, T_('Subject'), $no_sort ? NULL : 'subject', false, true );
   list($ico,$alt) = $msg_icones[0];
   $tit = T_('Messages');
   $mtable->add_tablehead( 0, 
      "<img border=0 alt='$alt' title=\"$tit\" src='images/$ico.gif'>"
      , $no_sort ? NULL : 'flow', false, true );
   $mtable->add_tablehead( 4, T_('Date'), $no_sort ? NULL : 'date', true, true );
   if( !$no_mark )
      $mtable->add_tablehead( 5, T_('Mark'), NULL, true, true );

   $page = '';

   $p = T_('Answer'); $n = T_('Replied');
   $tits[0                        ] = T_('Message') ;
   $tits[FLOW_ANSWER              ] = $p ;
   $tits[            FLOW_ANSWERED] = $n ;
   $tits[FLOW_ANSWER|FLOW_ANSWERED] = "$p&#10;$n" ;

   while( ($row = mysql_fetch_array( $result )) && $show_rows-- > 0 )
   {
      $mid = $row["mid"];
      $mrow_strings = array();

      $folder_nr = $row['folder'];
      $deleted = ( is_null($folder_nr) );
      $bgcolor = $mtable->blend_next_row_color_hex();

      $mrow_strings[1] = echo_folder_box($my_folders, $folder_nr, $bgcolor);

      if( $row['Sender'] === 'M' ) //Message to myself
      {
         $row["other_name"] = T_('Myself');
      }
      else if( $row["other_ID"] <= 0 )
         $row["other_name"] = T_('Server message');
      if( empty($row["other_name"]) )
         $row["other_name"] = '-';

      $str = make_html_safe($row["other_name"]) ;
      //if( !$deleted )
         $str = "<A href=\"message.php?mode=ShowMessage&mid=$mid\">$str</A>";
      if( $row['Sender'] === 'Y' )
         $str = T_('To') . ': ' . $str;
      $mrow_strings[2] = "<td>$str</td>";

      $mrow_strings[3] = "<td>" . make_html_safe($row["Subject"], true) . "&nbsp;</td>";

      list($ico,$alt) = $msg_icones[$row["flow"]];
      $tit = $tits[$row["flow"]];
      $str = "<img border=0 alt='$alt' title=\"$tit\" src='images/$ico.gif'>";
      //if( !$deleted )
         $str = "<A href=\"message.php?mode=ShowMessage&mid=$mid\">$str</A>";
      $mrow_strings[0] = "<td>$str</td>";

      $mrow_strings[4] = "<td>" . date($date_fmt, $row["Time"]) . "</td>";

      if( !$no_mark )
      {
         if( $folder_nr == FOLDER_NEW or $row['Replied'] == 'M'
           or ( $folder_nr == FOLDER_REPLY and $row['Type'] == 'INVITATION'
              and $row['Replied'] != 'Y' )
           or $deleted )
            $mrow_strings[5] = '<td>&nbsp;</td>';
         else
         {
            $can_move_messages = true;
            $checked = ((@$_REQUEST["mark$mid"]=='Y') xor $toggle_marks) ;
            if( $checked )
               $page.= "mark$mid=Y&" ;
            $mrow_strings[5] = "<td align=center>"  .
               "<input type='checkbox' name='mark$mid' value='Y'".
               ($checked ? ' checked' : '') .
               '></td>';
         }
      }
      $mtable->add_row( $mrow_strings );

   }


   $mtable->Page.= $page ;

   return $can_move_messages ;
}
?>
