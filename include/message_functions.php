<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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


require_once( 'include/gui_functions.php' );
require_once( 'include/table_infos.php' );
require_once( "include/rating.php" );
require_once( 'include/game_functions.php' );
require_once( 'include/time_functions.php' );
require_once( 'include/utilities.php' );

define('INVITE_HANDI_CONV',   -1);
define('INVITE_HANDI_PROPER', -2);
define('INVITE_HANDI_NIGIRI', -3);
define('INVITE_HANDI_DOUBLE', -4);

// game-settings form-/table-style defs
define('GSET_WAITINGROOM', 'waitingroom');
define('GSET_TOURNAMENT',  'tournament');
define('GSET_MSG_INVITE',  'invite');
define('GSET_MSG_DISPUTE', 'dispute');
define('CHECK_GSET', 'waitingroom|tournament|invite|dispute');

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
      //FOLDER_DESTROYED => array(T_('Destroyed'), 'ff88ee00', '000000'), // non-visible folder!!
      FOLDER_ALL_RECEIVED => array(T_('All Received'),'00000000','000000'), // pseudo-folder (grouping other folders)
      FOLDER_MAIN => array(T_('Main'), '00000000', '000000'),
      FOLDER_NEW => array(T_('New'), 'aaffaa90', '000000'),
      FOLDER_REPLY => array(T_('Reply!'), 'ffaaaa80', '000000'),
      FOLDER_DELETED => array(T_('Trashcan'), 'ff88ee00', '000000'),
      FOLDER_SENT => array(T_('Sent'), '00000000', '0000ff'),
      );
}


/*!
 * \brief Prints game setting form for some pages.
 * \param $formstyle:
 *     GSET_MSG_INVITE | GSET_MSG_DISPUTE = for message.php
 *     GSET_WAITINGROOM = for waiting_room.php / new_game.php
 *     GSET_TOURNAMENT = for tournaments/edit_rules.php
 * \param $map_ratings:
 *     if set, contain map with keys (rating1, rating2) ->
 *     then add probable game-settings for conventional/proper-handicap-type
 * \param $my_ID user-id for invite/dispute, then $gid is game-id;
 *        my_ID='redraw' for invite/dispute/tourney and $gid then is the $_POST[] of the form asking preview
 */
function game_settings_form(&$mform, $formstyle, $iamrated=true, $my_ID=NULL, $gid=NULL, $map_ratings=NULL)
{
   if( !preg_match( "/^(".CHECK_GSET.")$/", $formstyle ) )
      $formstyle = GSET_MSG_INVITE;

   $allowed = true;

   // Default values: for invite/waitingroom/tournament (dispute comes from DB)
   $Size = 19;
   $Handitype = ($iamrated) ? HTYPE_CONV : HTYPE_NIGIRI;
   $Color_m = HTYPE_NIGIRI; // always my-color of current-user (also for dispute)
   $CategoryHandiType = get_category_handicaptype( $Handitype );
   $Handicap_m = 0;
   $Komi_m = DEFAULT_KOMI;
   $AdjustKomi = 0.0;
   $JigoMode = JIGOMODE_KEEP_KOMI;
   $AdjustHandicap = 0;
   $MinHandicap = 0;
   $MaxHandicap = MAX_HANDICAP;
   $Maintime = 1;
   $MaintimeUnit = 'months';
   $Byotype = BYOTYPE_FISCHER;
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

      if( isset($gid['cat_htype']) )
         $CategoryHandiType = (string)$gid['cat_htype'];
      if( isset($gid['color_m']) )
         $Color_m = (string)$gid['color_m'];
      $Handitype = ( $CategoryHandiType == CAT_HTYPE_MANUAL ) ? $Color_m : $CategoryHandiType;

      if( isset($gid['handicap_m']) )
         $Handicap_m = (int)$gid['handicap_m'];
      if( isset($gid['komi_m']) )
         $Komi_m = (float)$gid['komi_m'];

      if( isset($gid['adj_komi']) )
         $AdjustKomi = (float)$gid['adj_komi'];
      if( isset($gid['jigo_mode']) )
         $JigoMode = (string)$gid['jigo_mode'];

      if( isset($gid['adj_handicap']) )
         $AdjustHandicap = (int)$gid['adj_handicap'];
      if( isset($gid['min_handicap']) )
         $MinHandicap = (int)$gid['min_handicap'];
      if( isset($gid['max_handicap']) )
         $MaxHandicap = min( MAX_HANDICAP, max( 0, (int)$gid['max_handicap'] ));

      if( isset($gid['byoyomitype']) )
         $Byotype = (string)$gid['byoyomitype'];

      // NOTE on time-hours: 36 hours eval to 2d + 6h (because of sleeping time)

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
      $game_row = mysql_single_fetch( "game_settings_form($gid)", $query );
      if( !$game_row )
         error('unknown_game', "game_settings_form($gid)");

      $Size = $game_row['Size'];
      $Rated = ( $game_row['Rated'] == 'Y' );
      $StdHandicap = ( $game_row['StdHandicap'] == 'Y' );
      $WeekendClock = ( $game_row['WeekendClock'] == 'Y' );

      $Color_m = ( $game_row['myColor'] == BLACK ? HTYPE_BLACK : HTYPE_WHITE );

      //ToMove_ID hold handitype since INVITATION
      switch( (int)$game_row['ToMove_ID'] )
      {
         case INVITE_HANDI_CONV:
            $Handitype = HTYPE_CONV;
            break;

         case INVITE_HANDI_PROPER:
            $Handitype = HTYPE_PROPER;
            break;

         case INVITE_HANDI_NIGIRI:
            $Handitype = HTYPE_NIGIRI;
            $Color_m = HTYPE_NIGIRI;
            $Komi_m = $game_row['Komi'];
            break;

         case INVITE_HANDI_DOUBLE:
            $Handitype = HTYPE_DOUBLE;
            $Color_m = HTYPE_DOUBLE;
            $Handicap_m = $game_row['Handicap'];
            $Komi_m = $game_row['Komi'];
            break;

         default: //Manual: any positive value
            $Handitype = $Color_m;
            $Handicap_m = $game_row['Handicap'];
            $Komi_m = $game_row['Komi'];
            break;
      }
      $CategoryHandiType = get_category_handicaptype( $Handitype );

      $MaintimeUnit = 'hours';
      $Maintime = $game_row['Maintime'];
      time_convert_to_longer_unit($Maintime, $MaintimeUnit);

      $game_row['ByotimeUnit'] = 'hours';
      time_convert_to_longer_unit($game_row['Byotime'], $game_row['ByotimeUnit']);

      $Byotype = $game_row['Byotype'];
      switch( (string)$Byotype )
      {
         case BYOTYPE_JAPANESE:
            $Byotime_jap = $game_row['Byotime'];
            $ByotimeUnit_jap = $game_row['ByotimeUnit'];
            $Byoperiods_jap = $game_row['Byoperiods'];
            break;

         case BYOTYPE_CANADIAN:
            $Byotime_can = $game_row['Byotime'];
            $ByotimeUnit_can = $game_row['ByotimeUnit'];
            $Byoperiods_can = $game_row['Byoperiods'];
            break;

         default: //case BYOTYPE_FISCHER:
            $Byotype = BYOTYPE_FISCHER;
            $Byotime_fis = $game_row['Byotime'];
            $ByotimeUnit_fis = $game_row['ByotimeUnit'];
            break;
      }
   } //collecting datas


   // Draw game-settings form

   $value_array = array_value_to_key_and_value( range( MIN_BOARD_SIZE, MAX_BOARD_SIZE ));
   $mform->add_row( array( 'SPACE' ) );
   $mform->add_row( array( 'DESCRIPTION', T_('Board size'),
                           'SELECTBOX', 'size', 1, $value_array, $Size, false ) );

   $handi_stones=array( 0 => 0 );
   for( $bs = 2; $bs <= MAX_HANDICAP; $bs++ )
     $handi_stones[$bs]=$bs;


   $mform->add_row( array( 'SPACE' ) );

   // Conventional & Proper handicap
   $trc = T_('Conventional handicap (komi 0.5 if not even)');
   $trp = T_('Proper handicap (komi adjusted by system)');
   if( $iamrated )
   {// user has a rating
      $sugg_conv = '';
      $sugg_prop = '';
      if( is_array($map_ratings) )
      {
         $r1 = $map_ratings['rating1'];
         $r2 = $map_ratings['rating2'];
         $arr_conv_sugg = suggest_conventional( $r1, $r2, $Size );
         $arr_prop_sugg = suggest_proper( $r1, $r2, $Size );
         $sugg_conv = '<span class="Suggestion">' .
            sptext( build_suggestion_shortinfo($arr_conv_sugg) ) . '</span>';
         $sugg_prop = '<span class="Suggestion">' .
            sptext( build_suggestion_shortinfo($arr_prop_sugg) ) . '</span>';
      }

      $mform->add_row( array(
            'DESCRIPTION', $trc,
            'RADIOBUTTONS', 'cat_htype', array( CAT_HTYPE_CONV => '' ), $CategoryHandiType,
            'TEXT', $sugg_conv ));
      $mform->add_row( array(
            'DESCRIPTION', $trp,
            'RADIOBUTTONS', 'cat_htype', array( CAT_HTYPE_PROPER => '' ), $CategoryHandiType,
            'TEXT', $sugg_prop ));
   }
   else
   {// user-unrated
      if( $formstyle == GSET_MSG_DISPUTE && ( $Handitype == HTYPE_CONV || $Handitype == HTYPE_PROPER ) )
      {
         $descr_str = ( $Handitype == HTYPE_CONV ) ? $trc : $rtp; // No initial rating
         $mform->add_row( array(
               'DESCRIPTION', $descr_str,
               'TEXT', sptext('<font color="red">' . T_('Impossible') . '</font>',1), ));
         $Handitype = HTYPE_NIGIRI; // default
         $CategoryHandiType = get_category_handicaptype( $Handitype );
         $allowed = false;
      }
   }

   // Manual game: nigiri, double, black, white
   $color_arr = array(
         HTYPE_NIGIRI => T_('Nigiri#htman'),
         HTYPE_DOUBLE => T_('Double#htman'),
         HTYPE_BLACK  => T_('Black#htman'),
         HTYPE_WHITE  => T_('White#htman'),
      );
   $arr_manual = array(
      'DESCRIPTION', T_('Manual setting (even or handicap game)'),
      'RADIOBUTTONS', 'cat_htype', array( CAT_HTYPE_MANUAL => '' ), $CategoryHandiType,
      'TEXT', sptext(T_('My color'),1), );
   if( $formstyle == GSET_TOURNAMENT )
      array_push( $arr_manual,
         'HIDDEN', 'color_m', HTYPE_NIGIRI,
         'TEXT', sprintf( '(%s)', T_('Nigiri')) );
   else
      array_push( $arr_manual,
         'SELECTBOX', 'color_m', 1, $color_arr, $Color_m, false );
   array_push( $arr_manual,
      'TEXT', sptext(T_('Handicap'),1),
      'SELECTBOX', 'handicap_m', 1, $handi_stones, $Handicap_m, false,
      'TEXT', sptext(T_('Komi'),1),
      'TEXTINPUT', 'komi_m', 5, 5, $Komi_m );
   $mform->add_row( $arr_manual );


   if( $formstyle == GSET_WAITINGROOM || $formstyle == GSET_TOURNAMENT )
   {
      // adjust handicap stones
      $adj_handi_stones = array();
      $HSTART = max(5, (int)(MAX_HANDICAP/3));
      for( $bs = -$HSTART; $bs <= $HSTART; $bs++ )
         $adj_handi_stones[$bs] = ($bs <= 0) ? $bs : "+$bs";
      $adj_handi_stones[0] = '&nbsp;0';
      $mform->add_row( array( 'SPACE' ) );
      $mform->add_row( array( 'DESCRIPTION', T_('Handicap stones'),
                              'TEXT', sptext(T_('Adjust by#handi')),
                              'SELECTBOX', 'adj_handicap', 1, $adj_handi_stones, $AdjustHandicap, false,
                              'TEXT', sptext(T_('Min.'), 1),
                              'SELECTBOX', 'min_handicap', 1, $handi_stones, $MinHandicap, false,
                              'TEXT', sptext(T_('Max.'), 1),
                              'SELECTBOX', 'max_handicap', 1, $handi_stones, $MaxHandicap, false,
                              ));
   }

   if( ENA_STDHANDICAP )
   {
      $arr = array();
      if( $formstyle == GSET_WAITINGROOM || $formstyle == GSET_TOURNAMENT )
         $arr[] = 'TAB';
      else
         array_push( $arr, 'DESCRIPTION', T_('Handicap stones') );
      array_push( $arr,
            'CHECKBOX', 'stdhandicap', 'Y', "", $StdHandicap,
            'TEXT', T_('Standard placement') );
      $mform->add_row($arr);
   }

   if( $formstyle == GSET_WAITINGROOM || $formstyle == GSET_TOURNAMENT )
   {
      // adjust komi
      $jigo_modes = array(
         'KEEP_KOMI'  => T_('Keep komi'),
         'ALLOW_JIGO' => T_('Allow Jigo'),
         'NO_JIGO'    => T_('No Jigo'),
      );
      $mform->add_row( array(
            'DESCRIPTION', T_('Komi'),
            'TEXT', sptext(T_('Adjust by#komi')),
            'TEXTINPUT', 'adj_komi', 5, 5, $AdjustKomi,
            'TEXT', sptext(T_('Jigo mode'), 1),
            'SELECTBOX', 'jigo_mode', 1, $jigo_modes, $JigoMode, false,
         ));
   }


   $value_array = array(
         'hours'  => T_('hours'),
         'days'   => T_('days'),
         'months' => T_('months') );

   $mform->add_row( array( 'HEADER', T_('Time settings') ) );

   $mform->add_row( array(
         'DESCRIPTION', T_('Main time'),
         'TEXTINPUT', 'timevalue', 5, 5, $Maintime,
         'SELECTBOX', 'timeunit', 1, $value_array, $MaintimeUnit, false ) );

   $mform->add_row( array(
         'DESCRIPTION', T_('Japanese byoyomi'),
         //'CELL', 1, 'nowrap',
         'RADIOBUTTONS', 'byoyomitype', array( BYOTYPE_JAPANESE => '' ), $Byotype,
         'TEXTINPUT', 'byotimevalue_jap', 5, 5, $Byotime_jap,
         'SELECTBOX', 'timeunit_jap', 1,$value_array, $ByotimeUnit_jap, false,
         'TEXT', sptext(T_('with')),
         'TEXTINPUT', 'byoperiods_jap', 5, 5, $Byoperiods_jap,
         'TEXT', sptext(T_('extra periods')),
      ));

   $mform->add_row( array(
         'DESCRIPTION', T_('Canadian byoyomi'),
         'RADIOBUTTONS', 'byoyomitype', array( BYOTYPE_CANADIAN => '' ), $Byotype,
         'TEXTINPUT', 'byotimevalue_can', 5, 5, $Byotime_can,
         'SELECTBOX', 'timeunit_can', 1,$value_array, $ByotimeUnit_can, false,
         'TEXT', sptext(T_('for')),
         'TEXTINPUT', 'byoperiods_can', 5, 5, $Byoperiods_can,
         'TEXT', sptext(T_('stones')),
      ));

   $mform->add_row( array(
         'DESCRIPTION', T_('Fischer time'),
         'RADIOBUTTONS', 'byoyomitype', array( BYOTYPE_FISCHER => '' ), $Byotype,
         'TEXTINPUT', 'byotimevalue_fis', 5, 5, $Byotime_fis,
         'SELECTBOX', 'timeunit_fis', 1,$value_array, $ByotimeUnit_fis, false,
         'TEXT', sptext(T_('extra per move')),
      ));

   $mform->add_row( array( 'SPACE' ) );

   $mform->add_row( array(
         'DESCRIPTION', T_('Clock runs on weekends'),
         'CHECKBOX', 'weekendclock', 'Y', "", $WeekendClock,
         'TEXT', sprintf( '(%s)', T_('UTC timezone') ), ));

   if( $formstyle == GSET_WAITINGROOM )
      $mform->add_row( array( 'HEADER', T_('Restrictions') ) );

   if( $iamrated )
   {
      $mform->add_row( array( 'DESCRIPTION', T_('Rated game'),
                              'CHECKBOX', 'rated', 'Y', "", $Rated ) );
   }
   else if( $formstyle == GSET_MSG_DISPUTE && $Rated )
   {// user unrated
      $mform->add_row( array(
            'DESCRIPTION', T_('Rated game'),
            'TEXT', sptext('<font color="red">' . T_('Impossible') . '</font>',1),
            //'HIDDEN', 'rated', '',
         ));
      $allowed = false;
   }

   return $allowed;
} // end of 'game_settings_form'


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
                            $thread=0, $reply_mid=0, $flow=0,
                            $folders=null, $folder_nr=null, $form=null, $delayed_move=false,
                            $rx_term='')
{
   global $msg_icones, $bg_color, $base_path;

   if( $other_id > 0 )
      $name = user_reference( REF_LINK, 0, '', $other_id, $other_name, $other_handle) ;
   else
      $name = $other_name; //i.e. T_("Server message"); or T_('Receiver not found');

   $cols = 2;
   echo "<table class=MessageInfos>\n",
      "<tr class=Date>",
      "<td class=Rubric>", T_('Date'), ":</td>",
      "<td colspan=$cols>", date(DATE_FMT, $date), "</td></tr>\n",
      "<tr class=Correspondent>",
      "<td class=Rubric>", ($to_me ? T_('From') : T_('To') ), ":</td>\n",
      "<td colspan=$cols>$name</td>",
      "</tr>\n";

   $subject = make_html_safe( $subject, SUBJECT_HTML, $rx_term);
   $text = make_html_safe( $text, true, $rx_term);

   // warn on empty subject
   $subj_fmt = $subject;
   if( (string)$subject == '' )
      $subj_fmt = '<span class=InlineWarning>' . T_('(no subject)') . '</span>';

   echo "<tr class=Subject>",
      "<td class=Rubric>", T_('Subject'), ":</td>",
      "<td colspan=$cols>", $subj_fmt, "</td></tr>\n",
      "<tr class=Message>",
      "<td class=Rubric>", T_('Message'), ":" ;

   $str0 = $str = '';
   if( $flow & FLOW_ANSWER && $reply_mid > 0 )
   {
      list($ico,$alt) = $msg_icones[FLOW_ANSWER];
      $str.= "<a href=\"message.php?mode=ShowMessage".URI_AMP."mid=$reply_mid\">" .
             "<img border=0 alt='$alt' src='$ico' title=\"" . T_("Previous message") . "\"></a>&nbsp;";
   }
   if( $flow & FLOW_ANSWERED && $mid > 0)
   {
      list($ico,$alt) = $msg_icones[FLOW_ANSWERED];
      $str.= "<a href=\"list_messages.php?find_answers=$mid\">" .
             "<img border=0 alt='$alt' src='$ico' title=\"" . T_("Next messages") . "\"></a>&nbsp;";
   }
   if( $str ) // $str set if msg is answer or has answer
      $str0 .= anchor( "message_thread.php?thread=$thread".URI_AMP."mid=$mid#mid$mid",
         image( $base_path.'images/thread.gif', T_('Message thread') ),
         T_('Show message thread') ) . MINI_SPACING;
   if( $thread != $mid )
      $str0 .= anchor( 'message.php?mode=ShowMessage'.URI_AMP.'mid='.$thread,
         image( $base_path.'images/msg_first.gif', T_('First message in thread') ),
         T_('Show initial message in thread') ) . MINI_SPACING;
   if( $str0 || $str )
     echo "<div class=MessageFlow>$str0$str</div>";

   echo "</td>\n"
      , "<td colspan=$cols>\n";

   echo "<table class=MessageBox><tr><td>"
      , $text
      , "</td></tr></table>";

   echo "</td></tr>\n";

   if( isset($folders) && $mid > 0 )
   {
      echo "<tr class=Folder>\n";

      echo "<td class=Rubric>" . T_('Folder') . ":</td>\n"
         , "<td><table class=FoldersTabs><tr>"
         , echo_folder_box($folders, $folder_nr, substr($bg_color, 2, 6))
         , "</tr></table></td>\n";

      echo "<td>";
      $deleted = ( $folder_nr == FOLDER_DESTROYED );
      if( !$deleted )
      {
         $fldrs = array('' => '');
         foreach( $folders as $key => $val )
         {
            if( $key != $folder_nr && $key != FOLDER_NEW && (!$to_me || $key != FOLDER_SENT) )
               $fldrs[$key] = $val[0];
         }

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
} // end of 'message_info_table'


/*!
 * \brief Prints game-info-table for some pages.
 * \param $tablestyle:
 *     GSET_MSG_INVITE | GSET_MSG_DISPUTE = for message.php
 *     GSET_WAITINGROOM = for waiting_room.php
 */
function game_info_table( $tablestyle, $game_row, $player_row, $iamrated)
{
   $Color = HTYPE_NIGIRI; // default, always represents My-Color (of current player)
   $AdjKomi = 0.0;
   $JigoMode = JIGOMODE_KEEP_KOMI;
   $AdjHandicap = 0;
   $MinHandicap = 0;
   $MaxHandicap = MAX_HANDICAP;

   // - for GSET_WAITINGROOM: Waitingroom.*
   // - for GSET_MSG_INVITE:
   //   Players: other_id, other_handle, other_name, other_rating, other_ratingstatus
   //   Games: Status, Game_mid(=mid), Size, Komi, Handicap, Rated, WeekendClock,
   //          StdHandicap, Maintime, Byotype, Byotime, Byoperiods, ToMove_ID, myColor
   extract($game_row);

   $is_my_game = ( $game_row['other_id'] == $player_row['ID'] );

   if( $tablestyle == GSET_WAITINGROOM )
   {
      $Handitype = (string)$Handicaptype;
      $CategoryHandiType = get_category_handicaptype( $Handitype );
      if( $CategoryHandiType == CAT_HTYPE_MANUAL )
         $Color = $Handitype;

      // switch colors for challenger, so $Color represents My-Color of current user
      if( !$is_my_game )
      {
         if( $Color == HTYPE_BLACK )
            $Color = HTYPE_WHITE;
         elseif( $Color == HTYPE_WHITE )
            $Color = HTYPE_BLACK;
      }

      $goodmingames = ( $MinRatedGames > 0 )
         ? ((int)@$player_row['RatedGames'] >= $MinRatedGames)
         : true;
   }
   else // invite|dispute
   {
      $tablestyle = GSET_MSG_INVITE;
      $Color = ($myColor == BLACK) ? HTYPE_BLACK : HTYPE_WHITE;

      //ToMove_ID hold handitype since INVITATION
      switch( (int)$game_row['ToMove_ID'] )
      {
         case INVITE_HANDI_CONV:
            $Handitype = HTYPE_CONV;
            $calculated = true;
            break;
         case INVITE_HANDI_PROPER:
            $Handitype = HTYPE_PROPER;
            $calculated = true;
            break;
         case INVITE_HANDI_NIGIRI:
            $Handitype = HTYPE_NIGIRI;
            $Color = $Handitype;
            $calculated = false;
            break;
         case INVITE_HANDI_DOUBLE:
            $Handitype = HTYPE_DOUBLE;
            $Color = $Handitype;
            $calculated = false;
            break;
         default: //Manual: any positive value
            $Handitype = ($myColor == BLACK) ? HTYPE_BLACK : HTYPE_WHITE;
            $calculated = false;
            break;
      }
      $CategoryHandiType = get_category_handicaptype( $Handitype );

      $goodrating = 1;
      $goodmingames = true;
      $haverating = ( $iamrated ) ? 1 : !$calculated;
   }

   $itable = new Table_info('game'); //==> ID='gameTableInfos'

   if( $tablestyle == GSET_WAITINGROOM )
   {
      $itable->add_scaption(T_('Info'));
      $itable->add_sinfo( T_('Number of games'), $nrGames );
      $itable->add_sinfo(
            T_('Player'),
            user_reference( REF_LINK, 1, '', $other_id, $other_name, $other_handle) );
   }

   $itable->add_sinfo( T_('Rating'), echo_rating($other_rating,true,$other_id) );
   $itable->add_sinfo( T_('Size'), $Size );

   $color_class = 'class=InTextImage';
   switch( (string)$CategoryHandiType )
   {
      case CAT_HTYPE_CONV: // Conventional handicap
         $itable->add_sinfo(
                  T_('Type'), T_('Conventional handicap (komi 0.5 if not even)'),
                  ( $haverating ? '' : warning_cell_attb( T_('No initial rating')) ) );
         break;

      case CAT_HTYPE_PROPER: // Proper handicap
         $itable->add_sinfo(
                  T_('Type'), T_('Proper handicap'),
                  ( $haverating ? '' : warning_cell_attb( T_('No initial rating')) ) );
         break;

      case CAT_HTYPE_MANUAL: // Manual game: Nigiri/Double/Black/White
      {
         if( $Handitype == HTYPE_NIGIRI )
         {
            $subtype = ($Handicap == 0) ? T_('Even game with nigiri') : T_('Handicap game with nigiri');
            $colortxt = image( '17/y.gif', T_('Nigiri'), null, $color_class );
         }
         elseif( $Handitype == HTYPE_DOUBLE )
         {
            $subtype = T_('Double game');
            $colortxt = build_image_double_game( true, $color_class );
         }
         else //if( $Handitype == HTYPE_BLACK || $Handitype == HTYPE_WHITE ) // my-color
         {
            // determine user-white/black
            // NOTE: my-color (for waiting-room color is switched above in this case)
            //       so use same choices for waitingroom/invite/dispute
            if( $Color == HTYPE_BLACK )
            {
               $user_w = array( 'ID' => $other_id, 'Handle' => $other_handle, 'Name' => $other_name );
               $user_b = $player_row;
            }
            else
            {
               $user_w = $player_row;
               $user_b = array( 'ID' => $other_id, 'Handle' => $other_handle, 'Name' => $other_name );
            }

            $subtype = T_('Fix color');
            $colortxt = image( '17/w.gif', T_('White'), null, $color_class) . MINI_SPACING
                      . user_reference( 0, 1, '', $user_w )
                      . SMALL_SPACING
                      . image( '17/b.gif', T_('Black'), null, $color_class) . MINI_SPACING
                      . user_reference( 0, 1, '', $user_b )
                      ;
         }

         $itable->add_sinfo( T_('Type'), sprintf( T_('%s (Manual setting)'), $subtype ) );
         $itable->add_sinfo( T_('Colors'), $colortxt );
         $itable->add_sinfo( T_('Handicap'), $Handicap );
         $itable->add_sinfo( T_('Komi'), (float)$Komi );
         break;
      }//case CAT_HTYPE_MANUAL
   }//switch $CategoryHandiType

   if( $tablestyle == GSET_WAITINGROOM ) // Handicap adjustment
   {
      $adj_handi_str = build_adjust_handicap( $AdjHandicap, $MinHandicap, $MaxHandicap );
      if( $adj_handi_str != '' )
         $itable->add_sinfo( T_('Handicap adjustment'), $adj_handi_str );
   }

   if( ENA_STDHANDICAP )
      $itable->add_sinfo( T_('Standard placement'), yesno( $StdHandicap) );

   if( $tablestyle == GSET_WAITINGROOM ) // Komi adjustment
   {
      $adj_komi_str = build_adjust_komi( $AdjKomi, $JigoMode );
      if( (string)$adj_komi_str != '' )
         $itable->add_sinfo( T_('Komi adjustment'), $adj_komi_str );
   }

   if( $tablestyle == GSET_WAITINGROOM ) // Restrictions
   {
      $ratinglimit_str = echo_game_restrictions($MustBeRated, $Ratingmin, $Ratingmax,
         $MinRatedGames, null, true);
      if( $ratinglimit_str != NO_VALUE )
         $itable->add_sinfo(
            T_('Rating restrictions'), $ratinglimit_str,
            ( ($goodrating && $goodmingames) ? '' : warning_cell_attb( T_('Out of range')) ) );

      $same_opp_str = echo_accept_same_opponent($SameOpponent, $game_row);
      if( $SameOpponent != 0 )
         $itable->add_sinfo(
            T_('Accept same opponent'), $same_opp_str,
            ( $goodsameopp ? '' : warning_cell_attb( T_('Out of range')) ) );
   }

   $itable->add_sinfo( T_('Main time'), echo_time($Maintime) );
   $itable->add_sinfo(
         echo_byotype($Byotype),
         echo_time_limit( -1, $Byotype, $Byotime, $Byoperiods , false, false, false) );

   $itable->add_sinfo(
         T_('Rated game'), yesno( $Rated),
         ( $iamrated || $Rated != 'Y' ? '' : warning_cell_attb( T_('No initial rating')) ) );
   $itable->add_sinfo( T_('Clock runs on weekends'), yesno( $WeekendClock) );

   if( $tablestyle == GSET_WAITINGROOM ) // Comment
   {
      $itable->add_row( array(
            'sname' => T_('Comment'),
            'info' => $Comment, //INFO_HTML
         ));
   }

   // compute the probable game settings
   if( $haverating && $goodrating && $goodmingames
         && ( !$is_my_game || $tablestyle != GSET_WAITINGROOM ) )
   {
      $is_nigiri = false; // true, if nigiri needed (because of same rating)
      if( $CategoryHandiType == CAT_HTYPE_PROPER )
      {
         list($infoHandicap,$infoKomi,$info_i_am_black, $is_nigiri) =
            suggest_proper($player_row['Rating2'], $other_rating, $Size);
      }
      elseif( $CategoryHandiType == CAT_HTYPE_CONV )
      {
         list($infoHandicap,$infoKomi,$info_i_am_black, $is_nigiri) =
            suggest_conventional($player_row['Rating2'], $other_rating, $Size);
      }
      else //if( $CategoryHandiType == CAT_HTYPE_MANUAL )
      {
         $infoHandicap = $Handicap;
         $infoKomi = $Komi;
         $info_i_am_black = 0;
      }

      // adjust handicap
      $infoHandicap_old = $infoHandicap;
      $infoHandicap = adjust_handicap( $infoHandicap, $AdjHandicap, $MinHandicap, $MaxHandicap );
      $adj_handi_str = ( $infoHandicap != $infoHandicap_old )
         ? sprintf( T_('adjusted from %d'), $infoHandicap_old )
         : '';

      // adjust komi
      $infoKomi_old = $infoKomi;
      $infoKomi = adjust_komi( $infoKomi, $AdjKomi, $JigoMode );
      $adj_komi_str = ( $infoKomi != $infoKomi_old )
         ? sprintf( T_('adjusted from %.1f'), $infoKomi_old )
         : '';

      if( $calculated || $adj_handi_str || $adj_komi_str )
      {
         // determine color
         if( $Handitype == HTYPE_DOUBLE )
            $colortxt = build_image_double_game( true, $color_class );
         elseif( $Handitype == HTYPE_NIGIRI || $is_nigiri )
            $colortxt = image( '17/y.gif', T_('Nigiri'), null, $color_class);
         else
            $colortxt = get_colortext_probable( $info_i_am_black );

         $is_calc_handitype = ( $Handitype == HTYPE_CONV || $Handitype == HTYPE_PROPER );
         $itable->add_scaption( ($is_calc_handitype)
            ? T_('Probable game settings')
            : T_('Game settings') );

         $itable->add_sinfo( T_('Color'), $colortxt );
         $itable->add_sinfo(
               T_('Handicap'),
               $infoHandicap . ($adj_handi_str ? "&nbsp;&nbsp;($adj_handi_str)" : '' ) );
         $itable->add_sinfo(
               T_('Komi'),
               sprintf("%.1f",$infoKomi) . ($adj_komi_str ? "&nbsp;&nbsp;($adj_komi_str)" : '' ) );
      }
   } //Probable settings

   $itable->echo_table();
} // end of 'game_info_table'

// output (with optional parts): prefix +/-adj [jigomode] suffix
// returns '' if no komi-adjustment; caller must format "empty" value
function build_adjust_komi( $adj_komi, $jigo_mode, $short=false, $prefix='', $suffix='' )
{
   $out = array();
   if( (float)$adj_komi != 0.0 )
      $out[] = ($adj_komi > 0 ? '+' : '') . (float)$adj_komi;
   if( $jigo_mode != JIGOMODE_KEEP_KOMI )
   {
      $jigo_str = '';
      if( $jigo_mode == JIGOMODE_ALLOW_JIGO )
         $jigo_str = ($short) ? T_('.0#wroomshort') : T_('Allow Jigo#wroom');
      elseif( $jigo_mode == JIGOMODE_NO_JIGO )
         $jigo_str = ($short) ? T_('.5#wroomshort') : T_('No Jigo#wroom');
      if( $jigo_str )
         $out[] = sprintf( '[%s]', $jigo_str );
   }

   if( count($out) )
      return $prefix . implode(' ',$out) . $suffix;
   else
      return '';
}

// output (with optional parts): prefix +/-adj [min,max] suffix
// returns '' if no handicap; caller must format empty to NO_VALUE for example
function build_adjust_handicap( $adj_handicap, $min_handicap, $max_handicap, $prefix='', $suffix='' )
{
   $out = array();
   if( $adj_handicap )
      $out[] = ($adj_handicap > 0 ? '+' : '') . $adj_handicap;
   if( $min_handicap > 0 || $max_handicap < MAX_HANDICAP )
      $out[] = sprintf( "[%d,%d]", $min_handicap, min( MAX_HANDICAP, $max_handicap) );

   if( count($out) )
      return $prefix . implode(' ',$out) . $suffix;
   else
      return '';
}

/*!
 * \brief Returns restrictions on rating-range, rated-finished-games, acceptance-mode-same-opponent.
 * \param $SameOpponent ignore if null
 */
function echo_game_restrictions($MustBeRated, $Ratingmin, $Ratingmax, $MinRatedGames, $SameOpponent=null, $short=false )
{
   $out = array();

   if( $MustBeRated == 'Y')
   {
      // +/-50 reverse the inflation from add_to_waitingroom.php
      $r1 = echo_rating( $Ratingmin + 50, false, 0, false, $short );
      $r2 = echo_rating( $Ratingmax - 50, false, 0, false, $short );
      if( $r1 == $r2 )
         $Ratinglimit = sprintf( T_('%s only'), $r1);
      else
         $Ratinglimit = $r1 . ' - ' . $r2;
      $out[] = $Ratinglimit;
   }

   if( $MinRatedGames > 0 )
   {
      $rg_str = ($short) ? T_('Rated Games[%s]#short') : T_('Rated finished Games[&gt;=%s]');
      $out[] = sprintf( $rg_str, $MinRatedGames );
   }

   if( !is_null($SameOpponent) )
   {
      if( $SameOpponent < 0 )
         $out[] = sprintf( 'SO[%sx]', -$SameOpponent ); // N times
      elseif( $SameOpponent > 0 )
         $out[] = sprintf( 'SO[&gt;%sd]', $SameOpponent ); // after N days
   }

   return ( count($out) ? implode(', ', $out) : NO_VALUE );
}

// WaitingRoom.SameOpponent: 0=always, <0=n times, >0=after n days
function echo_accept_same_opponent( $same_opp, $game_row=null )
{
   if( $same_opp == 0 )
      return T_('always#same_opp');

   if( $same_opp < 0 )
   {
      if ($same_opp == -1)
         $out = T_('1 time#same_opp');
      else //if ($same_opp < 0)
         $out = sprintf( T_('%s times#same_opp'), -$same_opp );
      if( is_array($game_row) && (int)@$game_row['JoinedCount'] > 0 )
      {
         $join_fmt = ($game_row['JoinedCount'] > 1)
            ? T_('joined %s games#same_opp') : T_('joined %s game#same_opp');
         $out .= ' (' . sprintf( $join_fmt, $game_row['JoinedCount'] ) . ')';
      }
   }
   else
   {
      global $NOW;
      if ($same_opp == 1)
         $out = T_('after 1 day#same_opp');
      else //if ($same_opp > 0)
         $out = sprintf( T_('after %s days#same_opp'), $same_opp );
      if( is_array($game_row) && isset($game_row['X_ExpireDate'])
            && ($game_row['X_ExpireDate'] > $NOW) )
      {
         $out .= ' (' . sprintf( T_('wait till %s#same_opp'),
            date(DATE_FMT6, $game_row['X_ExpireDate']) ) . ')';
      }
   }
   return $out;
}

function build_accept_same_opponent_array( $arr )
{
   $out = array();
   foreach( $arr as $same_opp )
      $out[$same_opp] = echo_accept_same_opponent($same_opp);
   return $out;
}

function build_suggestion_shortinfo( $suggest_result )
{
   list( $handi, $komi, $iamblack ) = $suggest_result;
   $info = sprintf( T_('... your Color is probably %1$s with Handicap %2$s, Komi %3$.1f'),
      get_colortext_probable( $iamblack ), $handi, $komi );
   return $info;
}

function get_colortext_probable( $iamblack )
{
   $color_class = 'class="InTextStone"';
   return ( $iamblack )
      ? image( '17/b.gif', T_('Black'), null, $color_class)
      : image( '17/w.gif', T_('White'), null, $color_class);
}


function interpret_time_limit_forms($byoyomitype, $timevalue, $timeunit,
                                    $byotimevalue_jap, $timeunit_jap, $byoperiods_jap,
                                    $byotimevalue_can, $timeunit_can, $byoperiods_can,
                                    $byotimevalue_fis, $timeunit_fis)
{
   $max = time_convert_to_hours( 365, 'days');

   $mainhours = time_convert_to_hours($timevalue, $timeunit);
   if( $mainhours > $max )
      $mainhours = $max;
   elseif( $mainhours < 0 )
      $mainhours = 0;

   if( $byoyomitype == BYOTYPE_JAPANESE )
   {
      $byohours = time_convert_to_hours($byotimevalue_jap, $timeunit_jap);
      if( $byohours > $max )
         $byohours = $max;
      elseif( $byohours < 0 )
         $byohours = 0;

      $byoperiods = (int)$byoperiods_jap;
      if( $byohours * $byoperiods > $max )
         $byoperiods = floor($max/$byohours);
   }
   else if( $byoyomitype == BYOTYPE_CANADIAN )
   {
      $byohours = time_convert_to_hours($byotimevalue_can, $timeunit_can);
      if( $byohours > $max )
         $byohours = $max;
      elseif( $byohours < 0 )
         $byohours = 0;

      $byoperiods = (int)$byoperiods_can;
      if( $byoperiods < 1 ) $byoperiods = 1;
   }
   else // if( $byoyomitype == BYOTYPE_FISCHER )
   {
      $byoyomitype = BYOTYPE_FISCHER;
      $byohours = time_convert_to_hours($byotimevalue_fis, $timeunit_fis);
      if( $byohours > $mainhours )
         $byohours = $mainhours;
      elseif( $byohours < 0 )
         $byohours = 0;

      $byoperiods = 0;
   }

   return array($mainhours, $byohours, $byoperiods);
}

// FOLDER_DESTROYED is NOT in standard-folders
function get_folders($uid, $remove_all_received=true)
{
   global $STANDARD_FOLDERS;

   $result = db_query( 'get_folders',
      "SELECT * FROM Folders WHERE uid=$uid ORDER BY Folder_nr" );

   $fldrs = $STANDARD_FOLDERS;

   while( $row = mysql_fetch_assoc($result) )
   {
      if( empty($row['Name']))
         $row['Name'] = ( $row['Folder_nr'] < USER_FOLDERS )
               ? $STANDARD_FOLDERS[$row['Folder_nr']][0]
               : T_('Folder name');
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
      $new_folder = FOLDER_DESTROYED;
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

   if( $new_folder == FOLDER_DESTROYED )
   {
      // destroy'ing only allowed from Trashcan-folder
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
            && $current_folder != FOLDER_DESTROYED )
         $where_clause.= "AND Folder_nr='" .$current_folder. "' ";
   }

   if( $need_replied )
      $where_clause.= "AND Replied='Y' ";
   else
      $where_clause.= "AND Replied!='M' ";

   db_query( 'change_folders',
      "UPDATE MessageCorrespondents SET Folder_nr=$new_folder " .
               "WHERE uid='$uid' $where_clause" .
               'AND Folder_nr > '.FOLDER_ALL_RECEIVED.' ' .
               "AND mid IN (" . implode(',', $message_ids) . ") " .
               "LIMIT " . count($message_ids) );

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
// $folder_nr: id of the folders, may also be an array with the folder properties like in $STANDARD_FOLDERS
function echo_folder_box( $folders, $folder_nr, $bgcolor=null, $attbs='', $layout_fmt='')
{
   global $STANDARD_FOLDERS;

   if( $folder_nr == FOLDER_DESTROYED ) //case of $deleted messages
     list($foldername, $folderbgcolor, $folderfgcolor) = array(NO_VALUE,0,0);
   else if( is_array($folder_nr) )
     list($foldername, $folderbgcolor, $folderfgcolor) = $folder_nr;
   else
     list($foldername, $folderbgcolor, $folderfgcolor) = @$folders[$folder_nr];

   if( empty($foldername) )
   {
     if( $folder_nr < USER_FOLDERS )
       list($foldername, $folderbgcolor, $folderfgcolor) = $STANDARD_FOLDERS[$folder_nr];
     else
       $foldername = T_('Folder name');
   }

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
   $result = db_query( 'folder_is_empty',
      "SELECT ID FROM MessageCorrespondents " .
      "WHERE uid='$uid' AND Folder_nr='$nr' LIMIT 1" );

   $nr = (@mysql_num_rows($result) === 0);
   mysql_free_result($result);
   return $nr;
}

function get_message_directions()
{
   return array(
      'M' => T_('Myself#msgdir'),
      'S' => T_('Server#msgdir'),
      'Y' => T_('To#msgdir'),
      'N' => T_('From#msgdir'),
   );
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
      'M.Type', 'M.Thread', 'M.Level', 'M.Subject', 'M.Game_ID',
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

   $result = db_query( 'message_list_query', $query );

   return array( $result, $qsql );
}

// param full_details: if true, show additional fields for message-search
function message_list_head( &$mtable, $current_folder, $no_mark=true, $full_details=false )
{
   global $base_path, $msg_icones;

   //TODO refactor, don't use ExtMode as "global var" to exchange args to with other methods!
   $mtable->ExtMode['no_mark']= $no_mark;
   $mtable->ExtMode['full_details']= $full_details;
   $mtable->ExtMode['current_folder']= $current_folder;

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $mtable->add_tablehead( 1, T_('Folder#header'), 'Folder',
         ($current_folder>FOLDER_ALL_RECEIVED ? TABLE_NO_SORT : 0), 'folder-');
   $mtable->add_tablehead( 9, new TableHead( T_('Message thread#header'),
         'images/thread.gif', T_('Show message thread') ), 'Image', 0, 'Thread+' );

   if( $full_details )
   {
      // additional fields for search-messages
      $mtable->add_tablehead( 6, T_('Type#header'), '', TABLE_NO_HIDE, 'M.Type+');
      $mtable->add_tablehead( 7, T_('Direction#header'), 'MsgDir', 0, 'Sender+');
      $mtable->add_tablehead( 2, T_('Correspondent#header'), 'User', 0, 'other_name+');
   }
   else
      $mtable->add_tablehead( 2,
            ($current_folder == FOLDER_SENT) ? T_('To#header') : T_('From#header'),
            'User', 0, 'other_name+');

   $mtable->add_tablehead( 3, T_('Subject#header'), '', 0, 'Subject+');
   list($ico,$alt) = $msg_icones[0];
   $mtable->add_tablehead( 8, image( $ico, '*-*'), 'Image', TABLE_NO_HIDE, 'flow+');
   $mtable->add_tablehead(10, new TableHead( T_('First message in thread#header'),
         'images/msg_first.gif', T_('Show initial message in thread') ), 'Image', TABLE_NO_SORT );
   $mtable->add_tablehead( 4, T_('Date#header'), 'Date', 0, 'date-');
   if( !$no_mark )
      $mtable->add_tablehead( 5, T_('Mark#header'), 'Mark', TABLE_NO_HIDE|TABLE_NO_SORT);

} //message_list_head

// param result: typically coming from message_list_query()
// param rx_terms: rx with terms to be marked within text
// NOTE: frees given mysql $result
function message_list_body( &$mtable, $result, $show_rows, $my_folders, $toggle_marks=false, $rx_term='' )
{
   global $base_path, $msg_icones, $player_row;

   //TODO refactor, don't use ExtMode as "global var" to exchange args to with other methods!
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
   $dirs = get_message_directions();

   $url_terms = ($rx_term != '') ? URI_AMP."xterm=".urlencode($rx_term) : '';

   while( ($row = mysql_fetch_assoc( $result )) && $show_rows-- > 0 )
   {
      $mid = $row["mid"];

      $folder_nr = $row['folder'];
      $deleted = ( is_null($folder_nr) );
      $bgcolor = $mtable->blend_next_row_color_hex();
      $thread = $row['Thread'];

      // link to message
      $msg_url = 'message.php?mode=ShowMessage'.URI_AMP."mid=$mid{$url_terms}";

      $mrow_strings = array();
      $mrow_strings[ 1] = array(
         'owntd' => echo_folder_box($my_folders, $folder_nr, $bgcolor) );

      // link to user
      $str = message_build_user_string( $row, $player_row, $full_details );
      if( !$full_details && ($row['Sender'] === 'Y') )
         $str = T_('To') . ': ' . $str;
      $mrow_strings[ 2] = $str;

      $subject = make_html_safe( $row['Subject'], SUBJECT_HTML, $rx_term);
      $mrow_strings[ 3] = anchor( $msg_url, $subject );

      list($ico,$alt) = $msg_icones[$row['flow']];
      $mrow_strings[ 8] = anchor( $msg_url, image( $ico, $alt, $tits[$row['flow']] ));

      $mrow_strings[ 4] = date(DATE_FMT, $row["Time"]);

      // additional fields for search-messages
      if( $full_details )
      {
         global $MSG_TYPES;
         $mrow_strings[ 6] = $MSG_TYPES[$row['Type']];

         $mrow_strings[ 7] = $dirs[$row['Sender']];
      }

      $mrow_strings[ 9] = '';
      $mrow_strings[10] = '';
      if( $thread )
      {
         $mrow_strings[ 9] = anchor( "message_thread.php?thread=$thread".URI_AMP."mid=$mid",
               image( $base_path.'images/thread.gif', T_('Message thread') ),
               T_('Show message thread') );

         if( $thread != $mid )
            $mrow_strings[10] = anchor( 'message.php?mode=ShowMessage'.URI_AMP."mid=$thread",
                  image( $base_path.'images/msg_first.gif', T_('First message in thread') ),
                  T_('Show initial message in thread') );
      }

      if( !$no_mark )
      {
         if( $folder_nr == FOLDER_NEW || $row['Replied'] == 'M'
               || ( $folder_nr == FOLDER_REPLY && $row['Type'] == 'INVITATION' && $row['Replied'] != 'Y' )
               || $deleted )
         {
            $mrow_strings[ 5] = '';
         }
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

/*!
 * \brief Builds user-string for message-list.
 * \param $row expected fields: Sender, other_ID, other_name, other_handle
 * \param $my_row most often $player_row
 */
function message_build_user_string( &$row, $my_row, $full_details )
{
   if( $row['Sender'] === 'M' ) // Message to myself
      $row['other_name'] = '(' . T_('Myself') . ')';
   else if( $row['other_ID'] <= 0 )
      $row['other_name'] = '[' . T_('Server message') . ']';
   if( empty($row['other_name']) )
      $row['other_name'] = NO_VALUE;

   // link to user
   if( $row['Sender'] === 'M' ) // Message to myself
   {
      if( $full_details )
         $user_str = user_reference( REF_LINK, 1, '', $my_row );
      else
         $user_str = $row['other_name'];
   }
   else if( $row['other_ID'] > 0 )
      $user_str = user_reference( REF_LINK, 1, '',
         $row['other_ID'], $row['other_name'], $row['other_handle'] );
   else
      $user_str = $row['other_name']; // server-msg or unknown

   return $user_str;
}

?>
