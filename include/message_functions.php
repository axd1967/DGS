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

define('INVITE_HANDI_CONV',-1);
define('INVITE_HANDI_PROPER',-2);
define('INVITE_HANDI_NIGIRI',-3);
define('INVITE_HANDI_DOUBLE',-4);


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


if( !defined('SMALL_SPACING') )
   define('SMALL_SPACING', '&nbsp;&nbsp;&nbsp;');

// Prints game setting form used by message.php and waiting_room.php
function game_settings_form(&$mform, $formstyle, $iamrated=true, $my_ID=NULL, $gid=NULL)
{

   if( $formstyle != 'dispute' &&  $formstyle != 'waitingroom' )
       $formstyle = 'invite';

   $allowed = true;


   // Default values: ('invite' or 'waitingroom')
   $Size = 19;
   if( $iamrated )
      $Handitype = 'conv';
   else
      $Handitype = 'nigiri';
   $MyColor = 'White';
   $Handicap_m = 0;
   $Handicap_d = 0;
   $Komi_m = 6.5;
   $Komi_n = 6.5;
   $Komi_d = 6.5;
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
   $StdHandicap = false;
   $Rated = true;

   if( $my_ID==='redraw' && is_array($gid) )
   {
      // If redraw, use values from array $gid
      // ($gid[] is the $_POST[] of the form asking the preview (i.e. this form))
      if( isset($gid['size']) )
         $Size = (int)$gid['size'];

      if( isset($gid['handicap_type']) )
         $Handitype = (string)$gid['handicap_type'];
      if( isset($gid['color']) )
         $MyColor = (string)$gid['color'];
      if( isset($gid['handicap_m']) )
         $Handicap_m = (int)$gid['handicap_m'];
      if( isset($gid['handicap_d']) )
         $Handicap_d = (int)$gid['handicap_d'];
      if( isset($gid['komi_m']) )
         $Komi_m = (float)$gid['komi_m'];
      if( isset($gid['komi_n']) )
         $Komi_n = (float)$gid['komi_n'];
      if( isset($gid['komi_d']) )
         $Komi_d = (float)$gid['komi_d'];

      if( isset($gid['byoyomitype']) )
         $Byotype = (string)$gid['byoyomitype'];

      if( isset($gid['timevalue']) )
         $Maintime = (int)$gid['timevalue'];
      if( isset($gid['timeunit']) )
         $MaintimeUnit = (string)$gid['timeunit'];

      if( isset($gid['byotimevalue_jap']) )
         $Byotime_jap = (int)$gid['byotimevalue_jap'];
      if( isset($gid['timeunit_jap']) )
         $ByotimeUnit_jap = (string)$gid['timeunit_jap'];
      if( isset($gid['byoperiods_jap']) )
         $Byoperiods_jap = (int)$gid['byoperiods_jap'];

      if( isset($gid['byotimevalue_can']) )
         $Byotime_can = (int)$gid['byotimevalue_can'];
      if( isset($gid['timeunit_can']) )
         $ByotimeUnit_can = (string)$gid['timeunit_can'];
      if( isset($gid['byoperiods_can']) )
         $Byoperiods_can = (int)$gid['byoperiods_can'];

      if( isset($gid['byotimevalue_fis']) )
         $Byotime_fis = (int)$gid['byotimevalue_fis'];
      if( isset($gid['timeunit_fis']) )
         $ByotimeUnit_fis = (string)$gid['timeunit_fis'];

      $Weekendclock = ( @$gid['weekendclock'] == 'Y' );
      $StdHandicap = ( @$gid['stdhandicap'] == 'Y' );
      $Rated = ( @$gid['rated'] == 'Y' );
   }
   else if( $gid > 0 && $my_ID > 0 ) //'Dispute'
   {
      // If dispute, use values from game $gid
      $query = "SELECT Handle,Size,Komi,Handicap,ToMove_ID," .
                 "Maintime,Byotype,Byotime,Byoperiods,Rated,StdHandicap,Weekendclock, " .
                 "IF(White_ID=$my_ID," . WHITE . "," . BLACK . ") AS Color " .
                 "FROM Games,Players WHERE Games.ID=$gid " .
                 "AND ((White_ID=$my_ID AND Players.ID=Black_ID) " .
                   "OR (Black_ID=$my_ID AND Players.ID=White_ID)) " .
                 "AND Status='INVITED'" ;

      if( !($game_row=mysql_single_fetch( $query,
                                          'assoc', 'message_functions.game_settings_form')) )
         error("unknown_game");

      extract($game_row);

      $MyColor = ( $Color == BLACK ? 'Black' : 'White' );
      $Rated = ( $Rated == 'Y' );
      $StdHandicap = ( $StdHandicap == 'Y' );
      $Weekendclock = ( $Weekendclock == 'Y' );

      $ByotimeUnit = 'hours';
      time_convert_to_longer_unit($Byotime, $ByotimeUnit);

      $MaintimeUnit = 'hours';
      time_convert_to_longer_unit($Maintime, $MaintimeUnit);

      //ToMove_ID hold handitype since INVITATION
      switch( $ToMove_ID )
      {
         case INVITE_HANDI_CONV:
         {
            $Handitype = 'conv';
         }
         break;

         case INVITE_HANDI_PROPER:
         {
            $Handitype = 'proper';
         }
         break;

         case INVITE_HANDI_NIGIRI:
         {
            $Handitype = 'nigiri';
            $Komi_n = $Komi;
         }
         break;

         case INVITE_HANDI_DOUBLE:
         {
            $Handitype = 'double';
            $Handicap_d = $Handicap;
            $Komi_d = $Komi;
         }
         break;

         default: //Black_ID
         {
            $Handitype = 'manual';
            $Handicap_m = $Handicap;
            $Komi_m = $Komi;
         }
         break;
      }

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

         default: //case 'FIS':
         {
            $Byotype = 'FIS';
            $Byotime_fis = $Byotime;
            $ByotimeUnit_fis = $ByotimeUnit;
         }
         break;
      }

   }

   switch( $Handitype )
   {
      case 'conv':
      case 'proper':
      case 'double':
      case 'nigiri':
         break;
      case 'manual': //not allowed in waiting room
         if( $formstyle != 'waitingroom' )
            break;
      default: //always available even if waiting room or unrated
         $Handitype = 'nigiri';
         break;
   }

   $value_array=array();
   for( $bs = MIN_BOARD_SIZE; $bs <= MAX_BOARD_SIZE; $bs++ )
     $value_array[$bs]=$bs;

   $mform->add_row( array( 'SPACE' ) );
   $mform->add_row( array( 'DESCRIPTION', T_('Board size'),
                           'SELECTBOX', 'size', 1, $value_array, $Size, false ) );

   $color_array = array( 'White' => T_('White'), 'Black' => T_('Black') );

   $handi_stones=array( 0 => 0 );
   for( $bs = 2; $bs <= MAX_HANDICAP; $bs++ )
     $handi_stones[$bs]=$bs;


   $mform->add_row( array( 'SPACE' ) );

   if( $iamrated )
   {
      $mform->add_row( array( 'DESCRIPTION', T_('Conventional handicap (komi 0.5 if not even)'),
                              'RADIOBUTTONS', 'handicap_type', array('conv'=>''), $Handitype ) );

      $mform->add_row( array( 'DESCRIPTION', T_('Proper handicap'),
                              'RADIOBUTTONS', 'handicap_type', array('proper'=>''), $Handitype ) );
   }
   else if( $formstyle=='dispute' && $Handitype=='conv' )
   {
      $mform->add_row( array( 'DESCRIPTION', T_('Conventional handicap (komi 0.5 if not even)'),
                              'TEXT', SMALL_SPACING . '<font color="red">' . T_('Impossible') . '</font>',
                            ));
      $Handitype = 'nigiri';
      $allowed = false;
   }
   else if( $formstyle=='dispute' && $Handitype=='proper' )
   {
      $mform->add_row( array( 'DESCRIPTION', T_('Proper handicap'),
                              'TEXT', SMALL_SPACING . '<font color="red">' . T_('Impossible') . '</font>',
                            ));
      $Handitype = 'nigiri';
      $allowed = false;
   }


   if( $formstyle != 'waitingroom' )
   {
      $mform->add_row( array( 'DESCRIPTION', T_('Manual setting'),
                              'RADIOBUTTONS', 'handicap_type', array('manual'=>''), $Handitype,
                              'TEXT', SMALL_SPACING . T_('My color'),
                              'SELECTBOX', 'color', 1, $color_array, $MyColor, false,
                              'TEXT', SMALL_SPACING . T_('Handicap'),
                              'SELECTBOX', 'handicap_m', 1, $handi_stones, $Handicap_m, false,
                              'TEXT', SMALL_SPACING . T_('Komi'),
                              'TEXTINPUT', 'komi_m', 5, 5, $Komi_m ) );
   }
   else if( $Handitype=='manual' )
   {
      $Handitype = 'nigiri';
      $allowed = false;
   }

   $mform->add_row( array( 'DESCRIPTION', T_('Even game with nigiri'),
                           'RADIOBUTTONS', 'handicap_type', array('nigiri'=>''), $Handitype,
                           'TEXT', SMALL_SPACING . T_('Komi'),
                           'TEXTINPUT', 'komi_n', 5, 5, $Komi_n ) );

   $mform->add_row( array( 'DESCRIPTION', T_('Double game'),
                           'RADIOBUTTONS', 'handicap_type', array('double'=>''), $Handitype,
                           'TEXT', SMALL_SPACING . T_('Handicap'),
                           'SELECTBOX', 'handicap_d', 1, $handi_stones, $Handicap_d, false,
                           'TEXT', SMALL_SPACING . T_('Komi'),
                           'TEXTINPUT', 'komi_d', 5, 5, $Komi_d ) );

   if( ENA_STDHANDICAP )
   $mform->add_row( array( 'DESCRIPTION', T_('Standard placement'),
                           'CHECKBOX', 'stdhandicap', 'Y', "", $StdHandicap ) );



   $value_array=array( 'hours' => T_('hours'),
                       'days' => T_('days'),
                       'months' => T_('months') );

   $mform->add_row( array( 'SPACE' ) );

   $mform->add_row( array( 'DESCRIPTION', T_('Main time'),
                           'TEXTINPUT', 'timevalue', 5, 5, $Maintime,
                           'SELECTBOX', 'timeunit', 1, $value_array, $MaintimeUnit, false ) );

   $mform->add_row( array( 'DESCRIPTION', T_('Japanese byoyomi'),
                           //'CELL', 1, 'nowrap',
                           'RADIOBUTTONS', 'byoyomitype', array( 'JAP' => '' ), $Byotype,
                           'TEXTINPUT', 'byotimevalue_jap', 5, 5, $Byotime_jap,
                           'SELECTBOX', 'timeunit_jap', 1,$value_array, $ByotimeUnit_jap, false,
                           'TEXT', T_('with') . '&nbsp;',
                           'TEXTINPUT', 'byoperiods_jap', 5, 5, $Byoperiods_jap,
                           'TEXT', T_('extra periods') ) );

   $mform->add_row( array( 'DESCRIPTION', T_('Canadian byoyomi'),
                           'RADIOBUTTONS', 'byoyomitype', array( 'CAN' => '' ), $Byotype,
                           'TEXTINPUT', 'byotimevalue_can', 5, 5, $Byotime_can,
                           'SELECTBOX', 'timeunit_can', 1,$value_array, $ByotimeUnit_can, false,
                           'TEXT', T_('for') . '&nbsp;',
                           'TEXTINPUT', 'byoperiods_can', 5, 5, $Byoperiods_can,
                           'TEXT', T_('stones') ) );

   $mform->add_row( array( 'DESCRIPTION', T_('Fischer time'),
                           'RADIOBUTTONS', 'byoyomitype', array( 'FIS' => '' ), $Byotype,
                           'TEXTINPUT', 'byotimevalue_fis', 5, 5, $Byotime_fis,
                           'SELECTBOX', 'timeunit_fis', 1,$value_array, $ByotimeUnit_fis, false,
                           'TEXT', T_('extra per move') ) );

   $mform->add_row( array( 'SPACE' ) );

   $mform->add_row( array( 'DESCRIPTION', T_('Clock runs on weekends'),
                           'CHECKBOX', 'weekendclock', 'Y', "", $Weekendclock ) );

   if( $iamrated )
   {
      $mform->add_row( array( 'DESCRIPTION', T_('Rated game'),
                              'CHECKBOX', 'rated', 'Y', "", $Rated ) );
   }
   else if( $formstyle=='dispute' && $Rated=='Y' )
   {
      $mform->add_row( array( 'DESCRIPTION', T_('Rated game'),
                              'TEXT', SMALL_SPACING . '<font color="red">' . T_('Impossible') . '</font>',
                              //'HIDDEN', 'rated', '',
                            ));
      $allowed = false;
   }

   return $allowed;
}


define('FLOW_ANSWER'  ,0x1);
define('FLOW_ANSWERED',0x2);
   $msg_icones = array(
  0                         => array('msg'   ,'&nbsp;-&nbsp;'),
  FLOW_ANSWER               => array('msg_lr','&gt;-&nbsp;'), //is an answer
              FLOW_ANSWERED => array('msg_rr','&nbsp;-&gt;'), //is answered
  FLOW_ANSWER|FLOW_ANSWERED => array('msg_2r','&gt;-&gt;'),
      );

function message_info_table($mid, $date, $to_me, //$mid==0 means preview
                            $other_id, $other_name, $other_handle, //must be html_safe
                            $subject, $text, //must NOT be html_safe
                            $reply_mid=0, $flow=0,
                            $folders=null, $folder_nr=null, $form=null, $delayed_move=false)
{
   global $date_fmt, $msg_icones, $bg_color;

   if( $other_id > 0 )
   {
     $name = user_reference( REF_LINK, 0, '', $other_id, $other_name, $other_handle) ;
   }
   else
     $name = $other_name; //i.e. T_("Server message");

   echo "<table border=0>\n" .
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
      $str.= "<a href=\"message.php?mode=ShowMessage".URI_AMP."mid=$reply_mid\">" .
             "<img border=0 alt='$alt' src='images/$ico.gif'"
             . ' title="' . T_("Previous message") . '"'
             . "></a>&nbsp;" ;
   }
   if( $flow & FLOW_ANSWERED && $mid > 0)
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

   if( isset($folders) && $mid > 0 )
   {
      echo "<tr>\n<td><b>" . T_('Folder') . ":</b></td>\n<td><table cellpadding=3><tr>" .
         echo_folder_box($folders, $folder_nr, substr($bg_color, 2, 6))
          . "</tr></table></td>\n<td>";

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
            if( $folder_nr > FOLDER_ALL_RECEIVED )
               echo $form->print_insert_hidden_input("current_folder", $folder_nr) ;
         }
         echo $form->print_insert_hidden_input('foldermove_mid', $mid) ;
      }

      echo "\n</td></tr>\n";
   }

   echo "</table>\n";
}


function game_info_table($Size, $col, $handicap_type, $Komi, $Handicap,
                         $Maintime, $Byotype, $Byotime, $Byoperiods,
                         $Rated, $WeekendClock, $StdHandicap, $gid=NULL)
{
   echo '<table align=center border=2 cellpadding=3 cellspacing=3>' . "\n";

   if( $gid > 0 )
      echo "<tr><td><b>" . T_('Game ID') . "</b></td><td><a href=\"game.php?gid=$gid\">$gid</a></td></tr>\n";

   echo '<tr><td><b>' . T_('Size') . '<b></td><td>' . $Size . "</td></tr>\n";

   switch( $handicap_type )
   {
      case INVITE_HANDI_CONV: // Conventional handicap
         echo '<tr><td><b>' . T_('Handicap') . '</b></td><td>' .
            T_('Conventional handicap (komi 0.5 if not even)') . "</td></tr>\n";
         break;

      case INVITE_HANDI_PROPER: // Proper handicap
         echo '<tr><td><b>' . T_('Handicap') . '</b></td><td>' .
            T_('Proper handicap') . "</td></tr>\n";
         break;

      case INVITE_HANDI_NIGIRI: // Nigiri
         echo '<tr><td><b>' . T_('Colors') . '</b></td><td>' . T_('Nigiri') . "</td></tr>\n";
         echo '<tr><td><b>' . T_('Komi') . '</b></td><td>' . $Komi . "</td></tr>\n";
         break;

      case INVITE_HANDI_DOUBLE: // Double game
         echo '<tr><td><b>' . T_('Colors') . '</b></td><td>' .
            T_('Double game') . "</td></tr>\n";
         echo '<tr><td><b>' . T_('Komi') . '</b></td><td>' . $Komi . "</td></tr>\n";
         break;

      default: // Manual: $handicap_type == $Black_ID
         echo '<tr><td><b>' . T_('Colors') . "<b></td><td>$col</td></tr>\n";
         echo '<tr><td><b>' . T_('Handicap') . '</b></td><td>' . $Handicap . "</td></tr>\n";
         echo '<tr><td><b>' . T_('Komi') . '</b></td><td>' . $Komi . "</td></tr>\n";
         break;
   }

   if( ENA_STDHANDICAP )
   {
      echo '<tr><td><b>' . T_('Standard placement') . '</b></td><td>' .
          ( $StdHandicap == 'Y' ? T_('Yes') : T_('No') ) . "</td></tr>\n";
   }


   echo '<tr><td><b>' . T_('Main time') . '</b></td><td>'
            . echo_time($Maintime) 
         . "</td></tr>\n";

   if( $Byotype == 'JAP' )
   {
      echo '<tr><td><b>' . T_('Japanese byoyomi') . '</b></td><td> ' .
         sprintf(T_('%s per move and %s extra periods')
            , echo_time($Byotime), $Byoperiods)
         . "</td></tr>\n";
   }
   else if ( $Byotype == 'CAN' )
   {
      echo '<tr><td><b>' . T_('Canadian byoyomi') . '</b></td><td> ' .
         sprintf(T_('%s per %s stones'), echo_time($Byotime), $Byoperiods)
         . "</td></tr>\n";
   }
   else if ( $Byotype == 'FIS' )
   {
      echo '<tr><td><b>' . T_('Fischer time') . '</b></td><td> ' .
         sprintf(T_('%s extra per move'), echo_time($Byotime))
         . "</td></tr>\n";
   }

   echo '<tr><td><b>' . T_('Rated game') . '</b></td><td>' .
       ( $Rated == 'Y' ? T_('Yes') : T_('No') ) . "</td></tr>\n";
   echo '<tr><td><b>' . T_('Clock runs on weekends') . '</b></td><td>' .
       ( $WeekendClock == 'Y' ? T_('Yes') : T_('No') ) . "</td></tr>\n";

   echo "</table>\n";

}


//Set global $hours,$byohours,$byoperiods
function interpret_time_limit_forms($byoyomitype, $timevalue, $timeunit,
                                    $byotimevalue_jap, $timeunit_jap, $byoperiods_jap,
                                    $byotimevalue_can, $timeunit_can, $byoperiods_can,
                                    $byotimevalue_fis, $timeunit_fis)
{

      $hours = (int)$timevalue;
      if( $timeunit != 'hours' )
         $hours *= 15;
      if( $timeunit == 'months' )
         $hours *= 30;

      if( $hours > 5475 ) $hours = 5475; //365*15
      else if( $hours < 0 ) $hours = 0;

      if( $byoyomitype == 'JAP' )
      {
         $byohours = (int)$byotimevalue_jap;
         if( $timeunit_jap != 'hours' ) $byohours *= 15;
         if( $timeunit_jap == 'months' ) $byohours *= 30;

         if( $byohours > 5475 ) $byohours = 5475;
         else if( $byohours < 0 ) $byohours = 0;

         $byoperiods = (int)$byoperiods_jap;
         if( $byohours * ($byoperiods+1) > 5475 )
            $byoperiods = floor(5475/$byohours) - 1;
      }
      else if( $byoyomitype == 'CAN' )
      {
         $byohours = (int)$byotimevalue_can;
         if( $timeunit_can != 'hours' ) $byohours *= 15;
         if( $timeunit_can == 'months' ) $byohours *= 30;

         if( $byohours > 5475 ) $byohours = 5475;
         else if( $byohours < 0 ) $byohours = 0;

         $byoperiods = (int)$byoperiods_can;
         if( $byoperiods < 1 ) $byoperiods = 1;
      }
      else // if( $byoyomitype == 'FIS' )
      {
         $byoyomitype = 'FIS';
         $byohours = (int)$byotimevalue_fis;
         if( $timeunit_fis != 'hours' ) $byohours *= 15;
         if( $timeunit_fis == 'months' ) $byohours *= 30;

         if( $byohours > $hours ) $byohours = $hours;
         else if( $byohours < 0 ) $byohours = 0;

         $byoperiods = 0;
      }

      return array($hours, $byohours, $byoperiods);
}

function get_folders($uid, $remove_all_received=true)
{
   global $STANDARD_FOLDERS;

   $result = mysql_query("SELECT * FROM Folders WHERE uid=$uid ORDER BY Folder_nr")
      or error('mysql_query_failed', 'message_functions.get_folders');

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
      if( !isset($_GET['folder']) )
         return -1; //i.e. no move query
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

      if( $current_folder > FOLDER_ALL_RECEIVED && isset($folders[$current_folder])
            && $current_folder != 'NULL' )
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
      or error('mysql_query_failed','message_functions.change_folders');

   return mysql_affected_rows() ;
}

function echo_folders($folders, $current_folder)
{
   global $STANDARD_FOLDERS;

   $string = '<table align=center border=0 cellpadding=0 cellspacing=7><tr>' . "\n" .
      '<td><b>' . T_('Folder') . ":&nbsp;&nbsp;&nbsp;</b></td>\n";

   $folders[FOLDER_ALL_RECEIVED] = $STANDARD_FOLDERS[FOLDER_ALL_RECEIVED];
   ksort($folders);

   $i = 0;
   foreach( $folders as $nr => $val )
   {
      if( $i > 0 && ($i % 8) == 0 )
          $string .= "</tr>\n<tr><td>&nbsp;</td>";
      $i++;

      list($name, $color, $fcol) = $val;
      $name = "<font color=\"$fcol\">" . make_html_safe($name) . "</font>" ;
      $string .= '<td bgcolor="#' .blend_alpha_hex($color). '"' ;
      if( $nr == $current_folder)
         $string .= " style=\"padding:4px;border-width:2px;border:solid;border-color:#6666ff;\">$name</td>\n";
      else
         $string .= " style=\"padding:6px;\"><a href=\"list_messages.php?folder=$nr\">$name</a></td>\n";
   }

   $string .= '</tr></table>' . "\n";

   return $string;
}

function folder_is_empty($nr, $uid)
{
   $result = mysql_query("SELECT ID FROM MessageCorrespondents " .
                         "WHERE uid='$uid' AND Folder_nr='$nr' LIMIT 1")
      or error('mysql_query_failed','message_functions.folder_is_empty');

   $nr = (@mysql_num_rows($result) === 0);
   mysql_free_result($result);
   return $nr;
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
   $query = "SELECT Messages.Type, Messages.Subject, " .
      "UNIX_TIMESTAMP(Messages.Time) AS Time, me.mid as date, " .
          "IF(Messages.ReplyTo>0 and NOT ISNULL(previous.mid),".FLOW_ANSWER.",0)" .
          "+IF(me.Replied='Y' or other.Replied='Y',".FLOW_ANSWERED.",0) AS flow, " .
      "me.mid, me.Replied, me.Sender, me.Folder_nr AS folder, " .
      "IF(me.sender='M',' ',Players.Name) AS other_name, " . //the ' ' help to sort
      "Players.ID AS other_ID " .
      "FROM Messages, MessageCorrespondents AS me " .
      "LEFT JOIN MessageCorrespondents AS other " .
        "ON other.mid=me.mid AND other.Sender!=me.Sender " .
      "LEFT JOIN Players ON Players.ID=other.uid " .
      "LEFT JOIN MessageCorrespondents AS previous " .
        "ON previous.mid=Messages.ReplyTo AND previous.uid=me.uid " .
      "WHERE me.uid=$my_id AND Messages.ID=me.mid $extra_where " .
        ( $folderstring=="all" ? "" : "AND me.Folder_nr IN ($folderstring) " ) .
      "ORDER BY $order $limit";

   $result = mysql_query( $query )
      or error('mysql_query_failed','message_functions.message_list_query');

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
                           'folder', true, false );
   $mtable->add_tablehead( 2, ($current_folder == FOLDER_SENT ? T_('To') : T_('From') ),
                           $no_sort ? NULL : 'other_name', false, false );
   $mtable->add_tablehead( 3, T_('Subject'), $no_sort ? NULL : 'subject', false, false );
   list($ico,$alt) = $msg_icones[0];
   $tit = str_replace('"', '&quot;', T_('Messages'));
   $mtable->add_tablehead( 0, 
      "<img border=0 alt='$alt' title=\"$tit\" src='images/$ico.gif'>"
      , $no_sort ? NULL : 'flow', false, true );
   $mtable->add_tablehead( 4, T_('Date'), $no_sort ? NULL : 'date', true, false );
   if( !$no_mark )
      $mtable->add_tablehead( 5, T_('Mark'), NULL, true, true );

   $page = '';

   $p = str_replace('"', '&quot;', T_('Answer'));
   $n = str_replace('"', '&quot;', T_('Replied'));
   $tits[0                        ] = str_replace('"', '&quot;', T_('Message')) ;
   $tits[FLOW_ANSWER              ] = $p ;
   $tits[            FLOW_ANSWERED] = $n ;
   $tits[FLOW_ANSWER|FLOW_ANSWERED] = "$p - $n" ;

   while( ($row = mysql_fetch_assoc( $result )) && $show_rows-- > 0 )
   {
      $mid = $row["mid"];
      $mrow_strings = array();

      $folder_nr = $row['folder'];
      $deleted = ( is_null($folder_nr) );
      $bgcolor = $mtable->blend_next_row_color_hex();

      $mrow_strings[1] = echo_folder_box($my_folders, $folder_nr, $bgcolor);

      if( $row['Sender'] === 'M' ) //Message to myself
      {
         $row["other_name"] = '(' . T_('Myself') . ')';
      }
      else if( $row["other_ID"] <= 0 )
         $row["other_name"] = '[' . T_('Server message') . ']';
      if( empty($row["other_name"]) )
         $row["other_name"] = '-';

      $str = make_html_safe($row["other_name"]) ;
      //if( !$deleted )
         $str = "<A href=\"message.php?mode=ShowMessage".URI_AMP."mid=$mid\">$str</A>";
      if( $row['Sender'] === 'Y' )
         $str = T_('To') . ': ' . $str;
      $mrow_strings[2] = "<td>$str</td>";

      $mrow_strings[3] = "<td>" . make_html_safe($row["Subject"], true) . "&nbsp;</td>";

      list($ico,$alt) = $msg_icones[$row["flow"]];
      $tit = $tits[$row["flow"]];
      $str = "<img border=0 alt='$alt' title=\"$tit\" src='images/$ico.gif'>";
      //if( !$deleted )
         $str = "<A href=\"message.php?mode=ShowMessage".URI_AMP."mid=$mid\">$str</A>";
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
               $page.= "mark$mid=Y".URI_AMP ;
            $mrow_strings[5] = "<td align=center>"  .
               "<input type='checkbox' name='mark$mid' value='Y'".
               ($checked ? ' checked' : '') .
               '></td>';
         }
      }
      $mtable->add_row( $mrow_strings );

   }
   mysql_free_result($result);

   $mtable->Page.= $page ;

   return $can_move_messages ;
}
?>
