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


require_once( 'include/table_infos.php' );
require_once( "include/rating.php" );

define('INVITE_HANDI_CONV',-1);
define('INVITE_HANDI_PROPER',-2);
define('INVITE_HANDI_NIGIRI',-3);
define('INVITE_HANDI_DOUBLE',-4);


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
   $WeekendClock = true;
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
      switch( $game_row['ToMove_ID'] )
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
      switch( $Byotype )
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
      $Handitype = 'manual';
      $allowed = false;
   }
   else if( $formstyle=='dispute' && $Handitype=='proper' )
   {
      $mform->add_row( array( 'DESCRIPTION', T_('Proper handicap'), //T_('No initial rating')
                              'TEXT', SMALL_SPACING . '<font color="red">' . T_('Impossible') . '</font>',
                            ));
      $Handitype = 'manual';
      $allowed = false;
   }


   if( $formstyle != 'waitingroom' )
   {
      $mform->add_row( array( 'DESCRIPTION', T_('Manual setting'),
                              'RADIOBUTTONS', 'handicap_type', array('manual'=>''), $Handitype,
                              'TEXT', SMALL_SPACING . T_('My color'),
                              'SELECTBOX', 'color', 1, $color_array, $myColor, false,
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
                           'CHECKBOX', 'weekendclock', 'Y', "", $WeekendClock ) );

   if( $iamrated )
   {
      $mform->add_row( array( 'DESCRIPTION', T_('Rated game'),
                              'CHECKBOX', 'rated', 'Y', "", $Rated ) );
   }
   else if( $formstyle=='dispute' && $Rated )
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
                            $folders=null, $folder_nr=null, $form=null, $delayed_move=false,
                            $rx_terms='')
{
   global $date_fmt, $msg_icones, $bg_color;

   if( $other_id > 0 )
   {
     $name = user_reference( REF_LINK, 0, 'black', $other_id, $other_name, $other_handle) ;
   }
   else
     $name = $other_name; //i.e. T_("Server message"); or T_('Receiver not found');

   echo "<table border=0>\n" .
      "<tr><td><b>" . T_('Date') . ":</b></td>" .
      "<td colspan=2>" . date($date_fmt, $date) . "</td></tr>\n" .
      "<tr><td><b>" . ($to_me ? T_('From') : T_('To') ) . ":</b></td>\n" .
      "<td colspan=2>$name</td>" .
      "</tr>\n";

   $subject = make_html_safe( $subject, SUBJECT_HTML, $rx_terms);
   $text = make_html_safe( $text, true, $rx_terms);

   echo "<tr><td><b>" . T_('Subject') . ":</b></td><td colspan=2>" .
      $subject . "</td></tr>\n" .
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
      "<td width=475 align=left>" . $text .
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


function game_info_table( $tablestyle, $game_row, $player_row, $iamrated)
{
   extract($game_row);

   if( $tablestyle == 'waitingroom' )
   {
      switch( $Handicaptype )
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
      switch( $game_row['ToMove_ID'] )
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
                  'sinfo' => user_reference( REF_LINK, 1, 'black', $other_id, $other_name, $other_handle),
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

   switch( $Handitype )
   {
      case 'conv': // Conventional handicap
         $itable->add_row( array(
                  'sname' => T_('Handicap'),
                  'sinfo' => T_('Conventional handicap (komi 0.5 if not even)'),
                  'iattb' => ( $haverating ? ''
                    : $itable->warning_cell_attb( T_('No initial rating'))
                    ),
                  ) );
         break;

      case 'proper': // Proper handicap
         $itable->add_row( array(
                  'sname' => T_('Handicap'),
                  'sinfo' => T_('Proper handicap'),
                  'iattb' => ( $haverating ? ''
                    : $itable->warning_cell_attb( T_('No initial rating'))
                    ),
                  ) );
         break;

      case 'nigiri': // Nigiri
         //'nigiri' => T_('Even game with nigiri'),
         $itable->add_sinfo(
                   T_('Colors')
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
                   T_('Colors')
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
       or $tablestyle != 'waitingroom'
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
                  or ($Handitype == 'conv'
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
      or error('mysql_query_failed', 'get_folders');

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
         $where_clause = "AND (Sender='Y' OR Sender='M') ";
      else if( $new_folder == FOLDER_REPLY )
         $where_clause = "AND (Sender='N' OR Sender='M') ";
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
      or error('mysql_query_failed','folder_is_empty');

   $nr = (@mysql_num_rows($result) === 0);
   mysql_free_result($result);
   return $nr;
}

// param bgcolor: if null, fall back to default-val (in blend_alpha_hex-func)
// param attbs, prefix, suffix: optional text (needed for filters)
function echo_folder_box( $folders, $folder_nr, $bgcolor, $attbs='', $prefix='', $suffix='' )
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

   return "<td bgcolor=\"#$folderbgcolor\" $attbs><font color=\"#$folderfgcolor\">".
          $prefix .
          make_html_safe($foldername) .
          $suffix .
          "</font></td>";
}

// param extra_querysql: QuerySQL-object to extend query
// return array( result, merged-QuerySQL )
function message_list_query($my_id, $folderstring='all', $order='date', $limit='', $extra_querysql=null)
{
   $qsql = new QuerySQL();
   $qsql->add_part( SQLP_FIELDS,
      'M.Type', 'M.Subject', 'M.Game_ID',
      'UNIX_TIMESTAMP(M.Time) AS Time',
      'me.mid as date',
      "IF(M.ReplyTo>0 and NOT ISNULL(previous.mid),".FLOW_ANSWER.",0)" .
          "+IF(me.Replied='Y' or other.Replied='Y',".FLOW_ANSWERED.",0) AS flow",
      'me.mid', 'me.Replied', 'me.Sender', 'me.Folder_nr AS folder',
      "IF(me.sender='M',' ',otherP.Name) AS other_name", // the ' ' helps to sort
      'otherP.ID AS other_ID',
      'otherP.Handle AS other_handle' );
   $qsql->add_part( SQLP_FROM,
      'Messages M ' .
      'INNER JOIN MessageCorrespondents AS me ON M.ID=me.mid',
      'LEFT JOIN MessageCorrespondents AS other ON other.mid=me.mid AND other.Sender!=me.Sender',
      'LEFT JOIN Players AS otherP ON otherP.ID=other.uid',
      'LEFT JOIN MessageCorrespondents AS previous ON previous.mid=M.ReplyTo AND previous.uid=me.uid' );
   $qsql->add_part( SQLP_WHERE, "me.uid=$my_id" );
   if ( $folderstring != "all" and $folderstring != '' )
      $qsql->add_part( SQLP_WHERE, "me.Folder_nr IN ($folderstring)" );
   $qsql->add_part( SQLP_ORDER, $order );
   $qsql->merge( $extra_querysql );
   $query = $qsql->get_select() . " $limit";

   $result = mysql_query( $query )
      or error('mysql_query_failed','message_list_query');

   return array( $result, $qsql );
}

// param full_details: if true, show additional fields for message-search
// param header_part: tri-states param (null, false, true)
//    if null or true, tableheads are added (needed for message-search);
//    then if null, the workflow goes on (needed for list-messages)
// param rx_terms: rx with terms to be marked within text
function message_list_table( &$mtable, $result, $show_rows
             , $current_folder, $my_folders
             , $no_sort=true, $no_mark=true, $toggle_marks=false
             , $full_details=false, $header_part=null, $rx_terms=''
             )
{
 global $date_fmt, $msg_icones, $player_row;

   $can_move_messages = false;

   if ( is_null($header_part) or $header_part )
   {
      // add_tablehead($nr, $description, $sort_string = NULL, $desc_default = false, $undeletable = false, $width = NULL)
      $mtable->add_tablehead( 1, T_('Folder'),
         ( $no_sort or $current_folder>FOLDER_ALL_RECEIVED ) ? NULL : 'folder', true, false );

      if ( $full_details )
      {
         // additional fields for search-messages
         $mtable->add_tablehead( 6, T_('Type'), 'M.Type', false, true );
         $mtable->add_tablehead( 7, T_('Direction'), $no_sort ? NULL : 'Sender', false, false );
         $mtable->add_tablehead( 2, T_('Correspondent'), $no_sort ? NULL : 'other_name', false, false );
      }
      else
         $mtable->add_tablehead( 2, ($current_folder == FOLDER_SENT ? T_('To') : T_('From') ),
            $no_sort ? NULL : 'other_name', false, false );

      $mtable->add_tablehead( 3, T_('Subject'), $no_sort ? NULL : 'Subject', false, false );
      list($ico,$alt) = $msg_icones[0];
      $mtable->add_tablehead( 0, image( "images/$ico.gif", $alt, T_('Messages')),
         $no_sort ? NULL : 'flow', false, true );
      $mtable->add_tablehead( 4, T_('Date'), $no_sort ? NULL : 'date', true, false );
      if( !$no_mark )
         $mtable->add_tablehead( 5, T_('Mark'), NULL, true, true );

      // then stop if $header_part is true
      if ( !is_null($header_part) )
         return $can_move_messages;
   }

   $page = '';

   $p = T_('Answer');
   $n = T_('Replied');
   $tits[0                        ] = T_('Message');
   $tits[FLOW_ANSWER              ] = $p ;
   $tits[            FLOW_ANSWERED] = $n ;
   $tits[FLOW_ANSWER|FLOW_ANSWERED] = "$p - $n" ;

   $url_terms = ($rx_terms != '') ? URI_AMP."terms=".urlencode($rx_terms) : '';

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

      // link to message
      $showmsg_start = "<A href=\"message.php?mode=ShowMessage".URI_AMP."mid=$mid{$url_terms}\">";
      $showmsg_end   = "</A>";

      // link to user
      if ( $full_details )
      {
         if( $row['Sender'] === 'M' ) //Message to myself
            $user_str = user_reference( REF_LINK, 1, '',
               array( 'ID'     => $player_row['ID'],
                      'Name'   => $row["other_name"] . ' - ' . $player_row['Name'],
                      'Handle' => $player_row['Handle'] ));
         else if( $row["other_ID"] > 0 )
            $user_str = user_reference( REF_LINK, 1, '',
               $row['other_ID'], $row['other_name'], $row['other_handle'] );
         else
            $user_str = '';
      }

      if ( $full_details ) // user-link
         $mrow_strings[2] = "<td>$user_str</td>";
      else
      { // msg-link
         $str = $showmsg_start . make_html_safe($row["other_name"]) . $showmsg_end;
         if( $row['Sender'] === 'Y' )
            $str = T_('To') . ': ' . $str;
         $mrow_strings[2] = "<td>$str</td>";
      }

      $subject = $row['Subject'];
      $subject = make_html_safe( $subject, SUBJECT_HTML, $rx_terms);
      if ( $full_details ) // link to msg
         $str = $showmsg_start . $subject . $showmsg_end;
      else // no-link
         $str = $subject;
      $mrow_strings[3] = "<td>$str&nbsp;</td>";

      list($ico,$alt) = $msg_icones[$row['flow']];
      $str = image( "images/$ico.gif", $alt, $tits[$row['flow']]);
      $str = $showmsg_start . $str . $showmsg_end;
      $mrow_strings[0] = "<td>$str</td>";

      $mrow_strings[4] = "<td>" . date($date_fmt, $row["Time"]) . "</td>";

      // additional fields for search-messages
      if ( $full_details )
      {
         $type = $row['Type'];
         $mrow_strings[6] = "<td>" . strtoupper($type{0}) . strtolower(substr($type,1)) . "</td>";

         $msgdir = ( $row['Sender'] === 'Y' ) ? 'To' : 'From';
         $mrow_strings[7] = "<td class=Right>$msgdir:&nbsp;&nbsp;</td>";
      }

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
            $n = $mtable->Prefix."mark$mid";
            $checked = ((@$_REQUEST[$n]=='Y') xor $toggle_marks);
            if( $checked )
               $page.= "$n=Y".URI_AMP ;
            $mrow_strings[5] = "<td class=Mark>"  .
               "<input type='checkbox' name='$n' value='Y'".
               ($checked ? ' checked' : '') .
               '></td>';
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
}

?>
