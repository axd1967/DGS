<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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


require_once( 'include/table_infos.php' );
require_once( "include/rating.php" );

define('INVITE_HANDI_CONV',-1);
define('INVITE_HANDI_PROPER',-2);
define('INVITE_HANDI_NIGIRI',-3);
define('INVITE_HANDI_DOUBLE',-4);

$MSG_TYPES = array( // keep them untranslated{!)
   'NORMAL'     => 'Normal',
   'INVITATION' => 'Invitation',
   'ACCEPTED'   => 'Accept',
   'DECLINED'   => 'Decline',
   'DELETED'    => 'Delete',
   'DISPUTED'   => 'Dispute',
   'RESULT'     => 'Result',
);

define('FOLDER_COLS_MODULO', 8); //number of columns of "tab" layouts

function init_standard_folders()
{
   global $STANDARD_FOLDERS;
   $STANDARD_FOLDERS = array(  // arr=( Name, BGColor, FGColor ); $bg_color value (#f7f5e3)
      FOLDER_ALL_RECEIVED => array(T_('All Received'),'00000000','000000'),
      FOLDER_MAIN => array(T_('Main'), '00000000', '000000'),
      FOLDER_NEW => array(T_('New'), 'aaffaa90', '000000'),
      FOLDER_REPLY => array(T_('Reply!'), 'ffaaaa80', '000000'),
      FOLDER_DELETED => array(T_('Trashcan'), 'ff88ee00', '000000'),
      FOLDER_SENT => array(T_('Sent'), '00000000', '0000ff'),
      );
}


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
   else if( $formstyle != 'waitingroom' )
      $Handitype = 'manual';
   else
      $Handitype = 'nigiri';
   $myColor = 'White';
   $Handicap_m = 0;
   $Handicap_d = 0;
   $Komi_m = 6.5;
   $Komi_n = 6.5;
   $Komi_d = 6.5;
   $Maintime = 1;
   $MaintimeUnit = 'months';
   $Byotype = 'FIS';
   $Byotime_jap = 1;
   $ByotimeUnit_jap = 'days';
   $Byoperiods_jap = 10;
   $Byotime_can = 15;
   $ByotimeUnit_can = 'days';
   $Byoperiods_can = 15;
   $Byotime_fis = 1;
   $ByotimeUnit_fis = 'days';
   $WeekendClock = true;
   $StdHandicap = true;
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
         $myColor = (string)$gid['color'];
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

      $WeekendClock = ( @$gid['weekendclock'] == 'Y' );
      $StdHandicap = ( @$gid['stdhandicap'] == 'Y' );
      $Rated = ( @$gid['rated'] == 'Y' );
   }
   else if( $gid > 0 && $my_ID > 0 ) //'Dispute'
   {
      // If dispute, use values from game $gid tables
      $query = "SELECT Handle,Size,Komi,Handicap,ToMove_ID," .
                 "Maintime,Byotype,Byotime,Byoperiods," .
                 "Rated,StdHandicap,WeekendClock, " .
                 "IF(White_ID=$my_ID," . WHITE . "," . BLACK . ") AS myColor " .
                 "FROM (Games,Players) WHERE Games.ID=$gid" .
                 " AND (White_ID=$my_ID OR Black_ID=$my_ID)" .
                 " AND Players.ID=White_ID+Black_ID-$my_ID" .
                 " AND Status='INVITED'" ;
      $game_row= mysql_single_fetch( 'game_settings_form',
                                     $query );
      if( !$game_row )
         error('unknown_game','game_settings_form');

      $Size = $game_row['Size'];
      $myColor = ( $game_row['myColor'] == BLACK ? 'Black' : 'White' );
      $Rated = ( $game_row['Rated'] == 'Y' );
      $StdHandicap = ( $game_row['StdHandicap'] == 'Y' );
      $WeekendClock = ( $game_row['WeekendClock'] == 'Y' );

      //ToMove_ID hold handitype since INVITATION
      switch( (int)$game_row['ToMove_ID'] )
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
            $Komi_n = $game_row['Komi'];
         }
         break;

         case INVITE_HANDI_DOUBLE:
         {
            $Handitype = 'double';
            $Handicap_d = $game_row['Handicap'];
            $Komi_d = $game_row['Komi'];
         }
         break;

         default: //Manual: any positive value
         {
            $Handitype = 'manual';
            $Handicap_m = $game_row['Handicap'];
            $Komi_m = $game_row['Komi'];
         }
         break;
      }

      $MaintimeUnit = 'hours';
      $Maintime = $game_row['Maintime'];
      time_convert_to_longer_unit($Maintime, $MaintimeUnit);

      $game_row['ByotimeUnit'] = 'hours';
      time_convert_to_longer_unit($game_row['Byotime'], $game_row['ByotimeUnit']);

      $Byotype = $game_row['Byotype'];
      switch( (string)$Byotype )
      {
         case 'JAP':
         {
            $Byotime_jap = $game_row['Byotime'];
            $ByotimeUnit_jap = $game_row['ByotimeUnit'];
            $Byoperiods_jap = $game_row['Byoperiods'];
         }
         break;

         case 'CAN':
         {
            $Byotime_can = $game_row['Byotime'];
            $ByotimeUnit_can = $game_row['ByotimeUnit'];
            $Byoperiods_can = $game_row['Byoperiods'];
         }
         break;

         default: //case 'FIS':
         {
            $Byotype = 'FIS';
            $Byotime_fis = $game_row['Byotime'];
            $ByotimeUnit_fis = $game_row['ByotimeUnit'];
         }
         break;
      }

   } //collecting datas


   // Now, compute datas

   switch( (string)$Handitype )
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

   $trc = T_('Conventional handicap (komi 0.5 if not even)');
   $trp = T_('Proper handicap (komi adjusted by system)');
   if( $iamrated )
   {
      $mform->add_row( array( 'DESCRIPTION', $trc,
                              'RADIOBUTTONS', 'handicap_type', array('conv'=>''), $Handitype ) );

      $mform->add_row( array( 'DESCRIPTION', $trp,
                              'RADIOBUTTONS', 'handicap_type', array('proper'=>''), $Handitype ) );
   }
   else if( $formstyle=='dispute' && $Handitype=='conv' )
   {
      $mform->add_row( array( 'DESCRIPTION', $trc,
                              'TEXT', sptext('<font color="red">' . T_('Impossible') . '</font>',1),
                            ));
      $Handitype = 'manual';
      $allowed = false;
   }
   else if( $formstyle=='dispute' && $Handitype=='proper' )
   {
      $mform->add_row( array( 'DESCRIPTION', $trp, //T_//('No initial rating')
                              'TEXT', sptext('<font color="red">' . T_('Impossible') . '</font>',1),
                            ));
      $Handitype = 'manual';
      $allowed = false;
   }


   if( $formstyle != 'waitingroom' )
   {
      $mform->add_row( array( 'DESCRIPTION', T_('Manual setting'),
                              'RADIOBUTTONS', 'handicap_type', array('manual'=>''), $Handitype,
                              'TEXT', sptext(T_('My color'),1),
                              'SELECTBOX', 'color', 1, $color_array, $myColor, false,
                              'TEXT', sptext(T_('Handicap'),1),
                              'SELECTBOX', 'handicap_m', 1, $handi_stones, $Handicap_m, false,
                              'TEXT', sptext(T_('Komi'),1),
                              'TEXTINPUT', 'komi_m', 5, 5, $Komi_m ) );
   }
   else if( $Handitype=='manual' )
   {
      $Handitype = 'nigiri';
      $allowed = false;
   }

   $mform->add_row( array( 'DESCRIPTION', T_('Even game with nigiri'),
                           'RADIOBUTTONS', 'handicap_type', array('nigiri'=>''), $Handitype,
                           'TEXT', sptext(T_('Komi'),1),
                           'TEXTINPUT', 'komi_n', 5, 5, $Komi_n ) );

   $mform->add_row( array( 'DESCRIPTION', T_('Double game'),
                           'RADIOBUTTONS', 'handicap_type', array('double'=>''), $Handitype,
                           'TEXT', sptext(T_('Handicap'),1),
                           'SELECTBOX', 'handicap_d', 1, $handi_stones, $Handicap_d, false,
                           'TEXT', sptext(T_('Komi'),1),
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
                           'TEXT', sptext(T_('with')),
                           'TEXTINPUT', 'byoperiods_jap', 5, 5, $Byoperiods_jap,
                           'TEXT', sptext(T_('extra periods')),
                           ) );

   $mform->add_row( array( 'DESCRIPTION', T_('Canadian byoyomi'),
                           'RADIOBUTTONS', 'byoyomitype', array( 'CAN' => '' ), $Byotype,
                           'TEXTINPUT', 'byotimevalue_can', 5, 5, $Byotime_can,
                           'SELECTBOX', 'timeunit_can', 1,$value_array, $ByotimeUnit_can, false,
                           'TEXT', sptext(T_('for')),
                           'TEXTINPUT', 'byoperiods_can', 5, 5, $Byoperiods_can,
                           'TEXT', sptext(T_('stones')),
                           ) );

   $mform->add_row( array( 'DESCRIPTION', T_('Fischer time'),
                           'RADIOBUTTONS', 'byoyomitype', array( 'FIS' => '' ), $Byotype,
                           'TEXTINPUT', 'byotimevalue_fis', 5, 5, $Byotime_fis,
                           'SELECTBOX', 'timeunit_fis', 1,$value_array, $ByotimeUnit_fis, false,
                           'TEXT', sptext(T_('extra per move')),
                           ) );

   $mform->add_row( array( 'SPACE' ) );

   $mform->add_row( array( 'DESCRIPTION', T_('Clock runs on weekends'),
                           'CHECKBOX', 'weekendclock', 'Y', "", $WeekendClock ) );

   if( $iamrated )
   {
      $mform->add_row( array( 'DESCRIPTION', T_('Rated game'),
                              'CHECKBOX', 'rated', 'Y', "", $Rated ) );
   }
   else if( $formstyle=='dispute' && $Rated )
   {
      $mform->add_row( array( 'DESCRIPTION', T_('Rated game'),
                              'TEXT', sptext('<font color="red">' . T_('Impossible') . '</font>',1),
                              //'HIDDEN', 'rated', '',
                            ));
      $allowed = false;
   }

   return $allowed;
}


define('FLOW_ANSWER'  ,0x1);
define('FLOW_ANSWERED',0x2);
   $msg_icones = array(
      0                         => array('images/msg.gif'   ,'&nbsp;-&nbsp;'),
      FLOW_ANSWER               => array('images/msg_lr.gif','&gt;-&nbsp;'), //is an answer
                  FLOW_ANSWERED => array('images/msg_rr.gif','&nbsp;-&gt;'), //is answered
      FLOW_ANSWER|FLOW_ANSWERED => array('images/msg_2r.gif','&gt;-&gt;'),
      );

function message_info_table($mid, $date, $to_me, //$mid==0 means preview
                            $other_id, $other_name, $other_handle, //must be html_safe
                            $subject, $text, //must NOT be html_safe
                            $reply_mid=0, $flow=0,
                            $folders=null, $folder_nr=null, $form=null, $delayed_move=false,
                            $rx_term='')
{
   global $msg_icones, $bg_color;

   if( $other_id > 0 )
   {
      $name = user_reference( REF_LINK, 0, '', $other_id, $other_name, $other_handle) ;
   }
   else
      $name = $other_name; //i.e. T_("Server message"); or T_('Receiver not found');

   $cols = 2;
   echo "<table class=MessageInfos>\n" .
      "<tr class=Date>" .
      "<td class=Rubric>" . T_('Date') . ":</td>" .
      "<td colspan=$cols>" . date(DATE_FMT, $date) . "</td></tr>\n" .
      "<tr class=Correspondent>" .
      "<td class=Rubric>" . ($to_me ? T_('From') : T_('To') ) . ":</td>\n" .
      "<td colspan=$cols>$name</td>" .
      "</tr>\n";

   $subject = make_html_safe( $subject, SUBJECT_HTML, $rx_term);
   $text = make_html_safe( $text, true, $rx_term);

   // warn on empty subject
   $subj_fmt = $subject;
   if( (string)$subject == '' )
      $subj_fmt = '<span class=InlineWarning>' . T_('(no subject)') . '</span>';

   echo "<tr class=Subject>" .
      "<td class=Rubric>" . T_('Subject') . ":</td>" .
      "<td colspan=$cols>" . $subj_fmt . "</td></tr>\n" .
      "<tr class=Message>" .
      "<td class=Rubric>" . T_('Message') . ":" ;
   $str = '';
   if( $flow & FLOW_ANSWER && $reply_mid > 0 )
   {
      list($ico,$alt) = $msg_icones[FLOW_ANSWER];
      $str.= "<a href=\"message.php?mode=ShowMessage".URI_AMP."mid=$reply_mid\">" .
             "<img border=0 alt='$alt' src='$ico'"
             . ' title="' . T_("Previous message") . '"'
             . "></a>&nbsp;" ;
   }
   if( $flow & FLOW_ANSWERED && $mid > 0)
   {
      list($ico,$alt) = $msg_icones[FLOW_ANSWERED];
      $str.= "<a href=\"list_messages.php?find_answers=$mid\">" .
             "<img border=0 alt='$alt' src='$ico'"
             . ' title="' . T_("Next messages") . '"'
             . "></a>&nbsp;" ;
   }
   if( $str )
     echo "<div class=MessageFlow>$str</div>";

   echo "</td>\n"
      . "<td colspan=$cols>\n";

   echo "<table class=MessageBox><tr><td>"
      . $text
      . "</td></tr></table>";

   echo "</td></tr>\n";

   if( isset($folders) && $mid > 0 )
   {
      echo "<tr class=Folder>\n";

      echo "<td class=Rubric>" . T_('Folder') . ":</td>\n"
         . "<td><table class=FoldersTabs><tr>"
         . echo_folder_box($folders, $folder_nr, substr($bg_color, 2, 6))
         . "</tr></table></td>\n";

      echo "<td>";
      $deleted = ( is_null($folder_nr) );
      if( !$deleted )
      {
         $fldrs = array('' => '');
         foreach( $folders as $key => $val )
            if( $key != $folder_nr && $key != FOLDER_NEW
               && (!$to_me || $key != FOLDER_SENT) )
               $fldrs[$key] = $val[0];

         echo $form->print_insert_select_box('folder', '1', $fldrs, '', '');
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


function game_info_table( $tablestyle, $game_row, $player_row, $iamrated)
{
   extract($game_row);

   if( $tablestyle == 'waitingroom' )
   {
      switch( (string)$Handicaptype )
      {
         case 'conv':
         case 'proper':
         case 'double':
         case 'nigiri':
            $Handitype = $Handicaptype;
            break;
         case 'manual': //not allowed in waiting room
         default: //always available even if waiting room or unrated
            $Handitype = 'nigiri';
            break;
      }
   }
   else
   {
      $tablestyle = 'invite';
      //ToMove_ID hold handitype since INVITATION
      switch( (int)$game_row['ToMove_ID'] )
      {
         case INVITE_HANDI_CONV:
            $Handitype = 'conv';
            $calculated = true;
            break;
         case INVITE_HANDI_PROPER:
            $Handitype = 'proper';
            $calculated = true;
            break;
         case INVITE_HANDI_NIGIRI:
            $Handitype = 'nigiri';
            $calculated = false;
            break;
         case INVITE_HANDI_DOUBLE:
            $Handitype = 'double';
            $calculated = false;
            break;
         default: //Manual: any positive value
            $Handitype = 'manual';
            $calculated = false;
            break;
      }
      $goodrating = 1;
      if( $iamrated )
         $haverating = 1;
      else
         $haverating = !$calculated;
   }


   $itable= new Table_info('game'); //==> ID='gameInfos'

   if( $tablestyle == 'waitingroom' )
   {
         $itable->add_scaption(T_('Info'));

         $itable->add_sinfo(
                   T_('Number of games')
                  ,$nrGames
                  );

         $itable->add_row( array(
                  'sname' => T_('Player'),
                  'sinfo' => user_reference( REF_LINK, 1, '', $other_id, $other_name, $other_handle),
                  ) );
   }

         $itable->add_row( array(
                  'sname' => T_('Rating'),
                  'sinfo' => echo_rating($other_rating,true,$other_id),
                  ) );


         $itable->add_sinfo(
                   T_('Size')
                  , $Size
                  );

   switch( (string)$Handitype )
   {
      case 'conv': // Conventional handicap
         $itable->add_row( array(
                  'sname' => T_('Type'),
                  'sinfo' => T_('Conventional handicap (komi 0.5 if not even)'),
                  'iattb' => ( $haverating ? ''
                    : $itable->warning_cell_attb( T_('No initial rating'))
                    ),
                  ) );
         break;

      case 'proper': // Proper handicap
         $itable->add_row( array(
                  'sname' => T_('Type'),
                  'sinfo' => T_('Proper handicap'),
                  'iattb' => ( $haverating ? ''
                    : $itable->warning_cell_attb( T_('No initial rating'))
                    ),
                  ) );
         break;

      case 'nigiri': // Nigiri
         //'nigiri' => T_('Even game with nigiri'),
         $itable->add_sinfo(
                   T_('Type')
                  , T_('Even game with nigiri') //T_('Nigiri')
                  );
         $itable->add_sinfo(
                   T_('Komi')
                  , $Komi
                  );
         break;

      case 'double': // Double game
         //'double' => T_('Double game') );
         $itable->add_sinfo(
                   T_('Type')
                  , T_('Double game')
                  );
         $itable->add_sinfo(
                   T_('Handicap')
                  , $Handicap
                  );
         $itable->add_sinfo(
                   T_('Komi')
                  , $Komi
                  );
         break;

      default: // 'manual'
         $colortxt = 'class=InTextStone';
         if( $game_row['myColor'] == BLACK )
         {
            $colortxt = image( '17/w.gif', T_('White'), '', $colortxt) . '&nbsp;'
                      . user_reference( 0, 1, '', 0, $other_name, $other_handle)
                      . '&nbsp;&nbsp;'
                      . image( '17/b.gif', T_('Black'), '', $colortxt) . '&nbsp;'
                      . user_reference( 0, 1, '', $player_row)
                      ;
         }
         else
         {
            $colortxt = image( '17/w.gif', T_('White'), '', $colortxt) . '&nbsp;'
                      . user_reference( 0, 1, '', $player_row)
                      . '&nbsp;&nbsp;'
                      . image( '17/b.gif', T_('Black'), '', $colortxt) . '&nbsp;'
                      . user_reference( 0, 1, '', 0, $other_name, $other_handle)
                      ;
         }

         $itable->add_sinfo(
                   T_('Colors')
                  , $colortxt
                  );
         $itable->add_sinfo(
                   T_('Handicap')
                  , $Handicap
                  );
         $itable->add_sinfo(
                   T_('Komi')
                  , $Komi
                  );
         break;
   }

   if( ENA_STDHANDICAP )
   {
         $itable->add_sinfo(
                   T_('Standard placement')
                  , yesno( $StdHandicap)
                  );
   }

   if( $tablestyle == 'waitingroom' )
   {
         $Ratinglimit= echo_rating_limit($MustBeRated, $Ratingmin, $Ratingmax);
         $itable->add_row( array(
                  'sname' => T_('Rating range'),
                  'sinfo' => $Ratinglimit,
                  'iattb' => ( $goodrating ? ''
                    : $itable->warning_cell_attb( T_('Out of range'))
                    ),
                  ) );
   }

         $itable->add_sinfo(
                   T_('Main time')
                  , echo_time($Maintime)
                  );
         $itable->add_sinfo(
                   echo_byotype($Byotype)
                  , echo_time_limit( -1, $Byotype, $Byotime, $Byoperiods
                                       , false, false, false)
                  );

         $itable->add_row( array(
                  'sname' => T_('Rated game'),
                  'sinfo' => yesno( $Rated),
                  'iattb' => ( $iamrated || $Rated != 'Y' ? ''
                    : $itable->warning_cell_attb( T_('No initial rating'))
                    ),
                  ) );
         $itable->add_sinfo(
                   T_('Clock runs on weekends')
                  , yesno( $WeekendClock)
                  );

   if( $tablestyle == 'waitingroom' )
   {
         //if( empty($Comment) ) $Comment = '&nbsp;';
         $itable->add_row( array(
                  'sname' => T_('Comment'),
                  'info' => $Comment, //INFO_HTML
                  ) );
   }

   if( $calculated && $haverating && $goodrating &&
       ( $game_row['other_id'] != $player_row['ID'] //not my game
       || $tablestyle != 'waitingroom'
       ) )
   {
         // compute the 'Probable settings'

         if( $Handitype == 'proper' )
            list($infoHandicap,$infoKomi,$info_i_am_black) =
               suggest_proper($player_row['Rating2'], $other_rating, $Size);
         else if( $Handitype == 'conv' )
            list($infoHandicap,$infoKomi,$info_i_am_black) =
               suggest_conventional($player_row['Rating2'], $other_rating, $Size);
         else
         {
            $infoHandicap = $Handicap; $infoKomi = $Komi; $info_i_am_black = 0;
         }

         $colortxt = 'class=InTextStone';
         if( $Handitype == 'double' )
         {
            $colortxt = image( '17/w.gif', T_('White'), '', $colortxt)
                      . '&nbsp;+&nbsp;'
                      . image( '17/b.gif', T_('Black'), '', $colortxt);
         }
         else if( $Handitype == 'nigiri'
                  || ($Handitype == 'conv'
                        && $infoHandicap == 0 && $infoKomi == 6.5) )
         {
            $colortxt = image( '17/y.gif', T_('Nigiri'), T_('Nigiri'), $colortxt);
         }
         else if( $info_i_am_black )
         {
            $colortxt = image( '17/b.gif', T_('Black'), '', $colortxt);
         }
         else
         {
            $colortxt = image( '17/w.gif', T_('White'), '', $colortxt);
         }

         /** TODO; remove the "probable" vocable in case of
          *  double games or nigiri games (i.e. keep it only for
          *  computed games).
          **/
         $itable->add_scaption(T_('Probable settings'));

         $itable->add_row( array(
                  'sname' => T_('Color'),
                  'sinfo' => $colortxt,
                  ) );
         $itable->add_sinfo(
                   T_('Handicap')
                  ,$infoHandicap
                  );
         $itable->add_sinfo(
                   T_('Komi')
                  ,sprintf("%.1f",$infoKomi)
                  );
   } //Probable settings

   $itable->echo_table();
}


function interpret_time_limit_forms($byoyomitype, $timevalue, $timeunit,
                                    $byotimevalue_jap, $timeunit_jap, $byoperiods_jap,
                                    $byotimevalue_can, $timeunit_can, $byoperiods_can,
                                    $byotimevalue_fis, $timeunit_fis)
{
   $max = time_convert_to_hours( 365, 'days');

   $mainhours = time_convert_to_hours($timevalue, $timeunit);
   if( $mainhours > $max ) $mainhours = $max;
   else if( $mainhours < 0 ) $mainhours = 0;

   if( $byoyomitype == 'JAP' )
   {
      $byohours = time_convert_to_hours($byotimevalue_jap, $timeunit_jap);
      if( $byohours > $max ) $byohours = $max;
      else if( $byohours < 0 ) $byohours = 0;

      $byoperiods = (int)$byoperiods_jap;
      if( $byohours * $byoperiods > $max )
         $byoperiods = floor($max/$byohours);
   }
   else if( $byoyomitype == 'CAN' )
   {
      $byohours = time_convert_to_hours($byotimevalue_can, $timeunit_can);
      if( $byohours > $max ) $byohours = $max;
      else if( $byohours < 0 ) $byohours = 0;

      $byoperiods = (int)$byoperiods_can;
      if( $byoperiods < 1 ) $byoperiods = 1;
   }
   else // if( $byoyomitype == 'FIS' )
   {
      $byoyomitype = 'FIS';
      $byohours = time_convert_to_hours($byotimevalue_fis, $timeunit_fis);
      if( $byohours > $mainhours ) $byohours = $mainhours;
      else if( $byohours < 0 ) $byohours = 0;

      $byoperiods = 0;
   }

   return array($mainhours, $byohours, $byoperiods);
}

function get_folders($uid, $remove_all_received=true)
{
   global $STANDARD_FOLDERS;

   $result = mysql_query("SELECT * FROM Folders WHERE uid=$uid ORDER BY Folder_nr")
      or error('mysql_query_failed', 'get_folders');

   $fldrs = $STANDARD_FOLDERS;

   while( $row = mysql_fetch_assoc($result) )
   {
      if( empty($row['Name']))
         $row['Name'] = ( $row['Folder_nr'] < USER_FOLDERS ?
                          $STANDARD_FOLDERS[$row['Folder_nr']][0] : T_('Folder name') );
      $fldrs[$row['Folder_nr']] = array($row['Name'], $row['BGColor'], $row['FGColor']);
   }
   mysql_free_result($result);

   if( $remove_all_received )
      unset($fldrs[FOLDER_ALL_RECEIVED]);

   return $fldrs;
}

function change_folders_for_marked_messages($uid, $folders)
{
   if( isset($_GET['move_marked']) )
   {
      if( !isset($_GET['folder']) )
         return -1; //i.e. no move query
      $new_folder = (int)$_GET['folder'];
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
         $message_ids[]= $matches[1];
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
      if( !isset($new_folder) || !isset($folders[$new_folder])
        || $new_folder == FOLDER_NEW || $new_folder == FOLDER_ALL_RECEIVED )
         error('folder_not_found');

      if( $new_folder == FOLDER_SENT )
         $where_clause = "AND Sender IN('Y','M') ";
      else if( $new_folder == FOLDER_REPLY )
         $where_clause = "AND Sender IN('N','M','S') ";
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
      or error('mysql_query_failed','change_folders');

   return mysql_affected_rows() ;
}

function echo_folders($folders, $current_folder)
{
   global $STANDARD_FOLDERS;

   $string = '<table class=FoldersTabs><tr>' . "\n" .
      '<td class=Rubric>' . T_('Folder') . ":</td>\n";

   $folders[FOLDER_ALL_RECEIVED] = $STANDARD_FOLDERS[FOLDER_ALL_RECEIVED];
   ksort($folders);

   $i = 0;
   foreach( $folders as $nr => $val )
   {
      if( $i > 0 && ($i % FOLDER_COLS_MODULO) == 0 )
          $string .= "</tr>\n<tr><td></td>"; //empty cell under title
      $i++;

      if( $nr == $current_folder)
         $string.= echo_folder_box( $folders, $val, null, 'class=Selected');
      else
         $string.= echo_folder_box( $folders, $val, null, 'class=Tab'
                        , "<a href=\"list_messages.php?folder=$nr\">%s</a>");
   }
   $i = ($i % FOLDER_COLS_MODULO);
   if( $i > 0 ) //empty cells of last line
   {
      $i = FOLDER_COLS_MODULO - $i;
      if( $i > 1 )
         $string .= "<td colspan=$i></td>";
      else
         $string .= "<td></td>";
   }

   $string .= "</tr></table>\n";

   return $string;
}

// param bgcolor: if null, fall back to default-val (in blend_alpha_hex-func)
// $folder_nr: id of the folders, may also be an array of the folder properties
function echo_folder_box( $folders, $folder_nr, $bgcolor=null, $attbs='', $layout_fmt='')
{
 global $STANDARD_FOLDERS;

   if( is_null($folder_nr) ) //case of $deleted messages
     list($foldername, $folderbgcolor, $folderfgcolor) = array(NO_VALUE,0,0);
   else if( is_array($folder_nr) )
     list($foldername, $folderbgcolor, $folderfgcolor) = $folder_nr;
   else
     list($foldername, $folderbgcolor, $folderfgcolor) = @$folders[$folder_nr];

   if( empty($foldername) )
     if( $folder_nr < USER_FOLDERS )
       list($foldername, $folderbgcolor, $folderfgcolor) = $STANDARD_FOLDERS[$folder_nr];
     else
       $foldername = T_('Folder name');

   $folderbgcolor = blend_alpha_hex($folderbgcolor, $bgcolor);
   if( empty($folderfgcolor) )
      $folderfgcolor = "000000" ;

   $foldername= "<font color=\"#$folderfgcolor\">" . make_html_safe($foldername) . "</font>";
   if( $layout_fmt )
      $foldername= sprintf( $layout_fmt, $foldername);

   if( !$attbs )
      $attbs = 'class=FolderBox';

   return "<td bgcolor=\"#$folderbgcolor\" $attbs>$foldername</td>";
}

function folder_is_empty($nr, $uid)
{
   $result = mysql_query("SELECT ID FROM MessageCorrespondents " .
                         "WHERE uid='$uid' AND Folder_nr='$nr' LIMIT 1")
      or error('mysql_query_failed','folder_is_empty');

   $nr = (@mysql_num_rows($result) === 0);
   mysql_free_result($result);
   return $nr;
}

// param extra_querysql: QuerySQL-object to extend query
// return array( result, merged-QuerySQL )
function message_list_query($my_id, $folderstring='all', $order=' ORDER BY date', $limit='', $extra_querysql=null)
{
/**
 * N.B.: On 2007-10-15, we have found, in the DGS database,
 *  30 records of MessageCorrespondents with .mid == 0
 *  all between "2004-06-03 09:25:17" and "2006-08-10 20:40:31".
 * While this should not have occured, those "lost" records can disturb
 *  some queries like this one where .mid is compared to .ReplyTo which
 *  may be 0 (meaning "no reply").
 * We have strengthened this query but also manually changed the faulty
 *  .mid from 0 to -9999 (directly in the database) to move them apart.
 **/
   $qsql = new QuerySQL();
   $qsql->add_part( SQLP_FIELDS,
      'M.Type', 'M.Subject', 'M.Game_ID',
      'UNIX_TIMESTAMP(M.Time) AS Time',
      'me.mid as date',
      "IF(NOT ISNULL(previous.mid),".FLOW_ANSWER.",0)" .
          "+IF(me.Replied='Y' or other.Replied='Y',".FLOW_ANSWERED.",0) AS flow",
      'me.mid', 'me.Replied', 'me.Sender', 'me.Folder_nr AS folder',
      "IF(me.Sender='M',' ',otherP.Name) AS other_name", // the ' ' helps to sort
      'otherP.ID AS other_ID',
      'otherP.Handle AS other_handle' );
   $qsql->add_part( SQLP_FROM,
      'Messages AS M',
      'INNER JOIN MessageCorrespondents AS me ON M.ID=me.mid',
      'LEFT JOIN MessageCorrespondents AS other ON other.mid=me.mid AND other.Sender!=me.Sender',
      'LEFT JOIN Players AS otherP ON otherP.ID=other.uid',
      'LEFT JOIN MessageCorrespondents AS previous ON M.ReplyTo>0 AND previous.mid=M.ReplyTo AND previous.uid='.$my_id );
   $qsql->add_part( SQLP_WHERE, "me.uid=$my_id" );
   if( $folderstring != "all" && $folderstring != '' )
      $qsql->add_part( SQLP_WHERE, "me.Folder_nr IN ($folderstring)" );
   $qsql->merge( $extra_querysql );
   $query = $qsql->get_select() . "$order$limit";

   $result = mysql_query( $query )
      or error('mysql_query_failed','message_list_query');

   return array( $result, $qsql );
}

// param full_details: if true, show additional fields for message-search
function message_list_head( &$mtable, $current_folder
             , $no_mark=true, $full_details=false
             )
{
 global $msg_icones;

   $mtable->ExtMode['no_mark']= $no_mark;
   $mtable->ExtMode['full_details']= $full_details;
   $mtable->ExtMode['current_folder']= $current_folder;

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $mtable->add_tablehead( 1, T_('Folder#header'), 'Folder',
      ($current_folder>FOLDER_ALL_RECEIVED ? TABLE_NO_SORT : 0), 'folder-');

   if( $full_details )
   {
      // additional fields for search-messages
      $mtable->add_tablehead( 6, T_('Type#header'), '', TABLE_NO_HIDE, 'M.Type+');
      $mtable->add_tablehead( 7, T_('Direction#header'), 'MsgDir', 0, 'Sender+');
      $mtable->add_tablehead( 2, T_('Correspondent#header'), 'User', 0, 'other_name+');
   }
   else
      $mtable->add_tablehead( 2, ($current_folder == FOLDER_SENT ? T_('To#header')
                  : T_('From#header')), 'User', 0, 'other_name+');

   $mtable->add_tablehead( 3, T_('Subject#header'), '', 0, 'Subject+');
   list($ico,$alt) = $msg_icones[0];
   $mtable->add_tablehead(33, image( $ico, '*-*'), 'Image', TABLE_NO_HIDE, 'flow+');
   $mtable->add_tablehead( 4, T_('Date#header'), 'Date', 0, 'date-');
   if( !$no_mark )
      $mtable->add_tablehead( 5, T_('Mark#header'), 'Mark', TABLE_NO_HIDE|TABLE_NO_SORT);

} //message_list_head

// param result: typically coming from message_list_query()
// param rx_terms: rx with terms to be marked within text
// NOTE: frees given mysql $result
function message_list_body( &$mtable, $result, $show_rows
             , $my_folders, $toggle_marks=false, $rx_term=''
             )
{
 global $msg_icones, $player_row;

   $no_mark= @$mtable->ExtMode['no_mark'];
   $full_details= @$mtable->ExtMode['full_details'];
   //$current_folder= @$mtable->ExtMode['current_folder'];

   $can_move_messages = false;
   //$page = ''; //not used, see below

   $p = T_('Answer');
   $n = T_('Replied');
   $tits = array(
      0                         => T_('Message'),
      FLOW_ANSWER               => $p ,
                  FLOW_ANSWERED => $n ,
      FLOW_ANSWER|FLOW_ANSWERED => "$p - $n" ,
      );

   // synchronize those translations with search_messages.php
   $dirs = array(
      'M' => T_('Myself#msgdir'),
      'S' => T_('Server#msgdir'),
      'Y' => T_('To#msgdir'),
      'N' => T_('From#msgdir'),
      );

   $url_terms = ($rx_term != '') ? URI_AMP."xterm=".urlencode($rx_term) : '';

   while( ($row = mysql_fetch_assoc( $result )) && $show_rows-- > 0 )
   {
      $mid = $row["mid"];
      $mrow_strings = array();

      $folder_nr = $row['folder'];
      $deleted = ( is_null($folder_nr) );
      $bgcolor = $mtable->blend_next_row_color_hex();

      $mrow_strings[ 1] = array(
         'owntd' => echo_folder_box($my_folders, $folder_nr, $bgcolor) );

      if( $row['Sender'] === 'M' ) //Message to myself
         $row["other_name"] = '(' . T_('Myself') . ')';
      else if( $row["other_ID"] <= 0 )
         $row["other_name"] = '[' . T_('Server message') . ']';
      if( empty($row["other_name"]) )
         $row["other_name"] = NO_VALUE;

      // link to message
      $showmsg_start = "<A href=\"message.php?mode=ShowMessage".URI_AMP."mid=$mid{$url_terms}\">";
      $showmsg_end   = "</A>";

      // link to user
      if( $row['Sender'] === 'M' ) //Message to myself
      {
         if( $full_details )
            $user_str = user_reference( REF_LINK, 1, '', $player_row );
         else
            $user_str = $row['other_name'];
      }
      else if( $row["other_ID"] > 0 )
         $user_str = user_reference( REF_LINK, 1, '',
            $row['other_ID'], $row['other_name'], $row['other_handle'] );
      else
         $user_str = $row['other_name']; // server-msg or unknown

      $str = $user_str;
      if( !$full_details )
         if( $row['Sender'] === 'Y' )
            $str = T_('To') . ': ' . $str;
      $mrow_strings[ 2] = $str;

      $subject = $row['Subject'];
      $subject = make_html_safe( $subject, SUBJECT_HTML, $rx_term);
      $mrow_strings[ 3] = "$showmsg_start$subject$showmsg_end";

      list($ico,$alt) = $msg_icones[$row['flow']];
      $str = image( $ico, $alt, $tits[$row['flow']]);
      $mrow_strings[33] = "$showmsg_start$str$showmsg_end";

      $mrow_strings[ 4] = date(DATE_FMT, $row["Time"]);

      // additional fields for search-messages
      if( $full_details )
      {
         global $MSG_TYPES;
         $mrow_strings[ 6] = $MSG_TYPES[$row['Type']];

         $mrow_strings[ 7] = $dirs[$row['Sender']];
      }

      if( !$no_mark )
      {
         if( $folder_nr == FOLDER_NEW || $row['Replied'] == 'M'
           || ( $folder_nr == FOLDER_REPLY && $row['Type'] == 'INVITATION'
              && $row['Replied'] != 'Y' )
           || $deleted )
            $mrow_strings[ 5] = '';
         else
         {
            $can_move_messages = true;
            $n = $mtable->Prefix."mark$mid";
            $checked = (('Y'==(string)@$_REQUEST[$n]) xor (bool)$toggle_marks);
            //if( $checked ) $page.= "$n=Y".URI_AMP;
            $mrow_strings[ 5] = "<input type='checkbox' name='$n' value='Y'"
               . ($checked ? ' checked' : '') . '>';
         }
      }
      $mtable->add_row( $mrow_strings );
   }
   mysql_free_result($result);

   //insertion of the marks in the URL of sort, page move and add/del column.
   //it's useless to add marks to the URLs while they are only used with actions
   // that change the order or the page because the marks will not stay on display.
   //$mtable->Page.= $page ;

   return $can_move_messages ;
} //message_list_body

?>
