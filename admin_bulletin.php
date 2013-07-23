<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Bulletin";

require_once 'include/error_codes.php';
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'include/db/bulletin.php';
require_once 'include/gui_bulletin.php';
require_once 'include/gui_user_functions.php';
require_once 'include/classlib_user.php';
require_once 'tournaments/include/tournament_cache.php';

$GLOBALS['ThePage'] = new Page('BulletinAdmin');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'admin_bulletin');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'admin_bulletin');

   $is_admin = Bulletin::is_bulletin_admin();
   if ( !$is_admin )
      error('adminlevel_too_low', 'admin_bulletin');

/* Actual REQUEST calls used:
     ''                       : add new admin bulletin
     n_gid=                   : add new MPG-bulletin
     n_tid=                   : add new TP/TD-bulletin
     n_uid=$uid|-1|''         : prepare new user-changeable bulletin for user: $uid, -1=some-user, ''=myself
     bid=                     : edit existing bulletin
     preview&bid=             : preview for bulletin-save
     save&bid=                : save new/updated bulletin
*/

   $bid = (int) get_request_arg('bid');
   if ( $bid < 0 ) $bid = 0;
   $n_gid = (int) get_request_arg('n_gid');
   if ( $n_gid < 0 ) $n_gid = 0;
   $n_tid = (int) get_request_arg('n_tid');
   if ( $n_tid < 0 ) $n_tid = 0;
   $n_uid = (int) get_request_arg('n_uid');
   if ( $n_uid < 0 ) $n_uid = $my_id;

   // init
   $bulletin = ( $bid > 0 ) ? Bulletin::load_bulletin($bid) : null;
   if ( is_null($bulletin) )
      $bulletin = Bulletin::new_bulletin( $is_admin, $n_gid, $n_tid, $n_uid );
   else
   {
      $bulletin->loadUserList();
      if ( $bulletin->tid > 0 )
         $bulletin->Tournament = TournamentCache::load_cache_tournament( 'admin_bulletin', $bulletin->tid, /*check*/false );
   }
   $bulletin->readLockVersion();

   $b_old_status = $bulletin->Status;
   $b_old_category = $bulletin->Category;
   $b_old_target_type = $bulletin->TargetType;
   $b_old_admnote = $bulletin->AdminNote;

   $arr_status = GuiBulletin::getStatusText();
   $arr_categories = GuiBulletin::getCategoryText();
   $arr_target_types = GuiBulletin::getTargetTypeText();

   // check + parse edit-form
   list( $vars, $edits, $arr_user_msg_rejected, $input_errors ) = parse_edit_form( $bulletin );
   $errors = $input_errors;

   // save bulletin-object with values from edit-form
   if ( @$_REQUEST['save'] && !@$_REQUEST['preview'] && count($errors) == 0 )
   {
      if ( count($edits) == 0 )
         $errors[] = T_('Sorry, there\'s nothing to save.');
      else
      {
         ta_begin();
         {//HOT-section to save bulletin-data
            $bulletin->persist();
            if ( $bid && $bulletin->is_optimistic_lock_clash() )
               $errors[] = ErrorCode::get_error_text('optlock_clash');
            else
            {
               $bid = $bulletin->ID;

               if ( $bulletin->TargetType == BULLETIN_TRG_USERLIST )
                  Bulletin::persist_bulletin_userlist( $bid, $bulletin->UserList );

               if ( $bulletin->CountReads > 0 && $b_old_status != BULLETIN_STATUS_SHOW && $bulletin->Status == BULLETIN_STATUS_SHOW )
                  Bulletin::reset_bulletin_read( $bid );

               $bulletin->update_count_players('admin_bullet');
            }
         }
         ta_end();

         if ( count($errors) == 0 )
            jump_to("admin_bulletin.php?bid=$bid".URI_AMP."sysmsg=". urlencode(T_('Bulletin saved!')) );
      }
   }

   $page = "admin_bulletin.php";
   $title = T_('Admin Bulletin');


   // ---------- Bulletin EDIT form --------------------------------

   $bform = new Form( 'bulletinEdit', $page, FORM_POST );
   $bform->add_hidden( 'bid', $bid );
   $bform->add_hidden( FORMFIELD_LOCKVERSION, $bulletin->LockVersion );
   if ( $n_uid )
      $bform->add_hidden( 'n_uid', $n_uid );

   $bform->add_row( array(
         'DESCRIPTION', T_('Bulletin ID'),
         'TEXT',        ($bid ? anchor( $base_path."admin_bulletin.php?bid=$bid", $bid )
                              : T_('NEW bulletin#bulletin')), ));
   if ( $bid || $vars['author'] == $bulletin->User->Handle )
      $bform->add_row( array(
            'DESCRIPTION', T_('Bulletin Author'),
            'TEXT',        $bulletin->User->user_reference(), ));
   if ( $bulletin->Flags > 0 )
      $bform->add_row( array(
            'DESCRIPTION', T_('Bulletin Flags'),
            'TEXT',        GuiBulletin::formatFlags($bulletin->Flags), ));
   if ( $bulletin->Lastchanged > 0 )
      $bform->add_row( array(
            'DESCRIPTION', T_('Last changed'),
            'TEXT',        formatDate($bulletin->Lastchanged), ));
   if ( !is_null($bulletin->Tournament) )
      $bform->add_row( array(
            'DESCRIPTION', T_('Tournament'),
            'TEXT',        $bulletin->Tournament->build_info(1), ));
   if ( $bid )
   {
      $bform->add_row( array(
            'DESCRIPTION', T_('Hits (read marks)'),
            'TEXT',        $bulletin->CountReads, ));
      if ( $b_old_admnote )
         $bform->add_row( array(
               'DESCRIPTION', T_('Admin Note'),
               'TEXT',        span('BulletinAdminNote', $b_old_admnote), ));
   }

   $bform->add_row( array( 'HR' ));

   if ( count($errors) )
   {
      $bform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $bform->add_empty_row();
   }

   $bform->add_row( array(
         'DESCRIPTION', T_('Current Category#bulletin'),
         'TEXT',        GuiBulletin::getCategoryText($b_old_category) ));
   $bform->add_row( array(
         'TAB',
         'SELECTBOX',    'category', 1, $arr_categories, $vars['category'], false, ));

   $bform->add_row( array(
         'DESCRIPTION', T_('Current Status#bulletin'),
         'TEXT',        GuiBulletin::getStatusText($b_old_status) ));
   $bform->add_row( array(
         'TAB',
         'SELECTBOX',    'status', 1, $arr_status, $vars['status'], false, ));

   $bform->add_row( array(
         'DESCRIPTION', T_('Current Target Type#bulletin'),
         'TEXT',        GuiBulletin::getTargetTypeText($b_old_target_type) ));
   $bform->add_row( array(
         'TAB',
         'SELECTBOX',    'target_type', 1, $arr_target_types, $vars['target_type'], false, ));

   if ( ( $vars['target_type'] == BULLETIN_TRG_TP || $vars['target_type'] == BULLETIN_TRG_TD )
         || $vars['category'] == BULLETIN_CAT_TOURNAMENT_NEWS
         || $vars['tnews_tid'] || $bulletin->tid > 0 )
   {
      $bform->add_row( array(
            'DESCRIPTION', T_('Tournament ID'),
            'TEXTINPUT',   'tnews_tid', 8, 12, $vars['tnews_tid'], ));
   }
   if ( $vars['target_type'] == BULLETIN_TRG_USERLIST || (string)trim(get_request_arg('user_list')) != '' )
   {
      $bform->add_row( array(
            'DESCRIPTION', T_('User List'),
            'TEXT',        sprintf( T_('only for target-type [%s], user-id (text or numeric)#bulletin_userlist'),
                                    GuiBulletin::getTargetTypeText($vars['target_type']) ), ));
      $bform->add_row( array(
            'TAB',
            'TEXTAREA',    'user_list', 80, 3, $vars['user_list'], ));

      if ( count($arr_user_msg_rejected) )
      {
         $bform->add_row( array(
               'DESCRIPTION', span('FormWarning', T_('User List Warning')),
               'CHECKBOX',    'warn_reject', 1, T_('Ignore User List Warning'), $vars['warn_reject'], ));
         $bform->add_row( array(
               'TAB', 'TEXT', span('FormWarning',
                  sprintf( T_('Users [%s] have reject message contact-setting for bulletin-author [%s]'),
                           implode(' ', $arr_user_msg_rejected),
                           $bulletin->User->Handle )), ));
      }
   }
   if ( $vars['target_type'] == BULLETIN_TRG_MPG || $vars['gid'] || $bulletin->gid > 0 )
   {
      $bform->add_row( array(
            'DESCRIPTION', T_('Game ID'),
            'TEXTINPUT',   'gid', 8, 12, $vars['gid'], ));
   }

   $bform->add_row( array(
         'DESCRIPTION', T_('Admin Note'),
         'TEXTINPUT',   'admin_note', 80, 255, $vars['admin_note'] ));


   $bform->add_empty_row();
   $bform->add_row( array(
         'DESCRIPTION', T_('Author'),
         'TEXTINPUT',   'author', 16, 20, $vars['author'], ));
   $bform->add_row( array(
         'DESCRIPTION', T_('Publish time'),
         'TEXTINPUT',   'publish_time', 20, 30, $vars['publish_time'],
         'TEXT',  '&nbsp;' . span('EditNote', sprintf( T_('(Date format [%s])'), FMT_PARSE_DATE)), ));
   $bform->add_row( array(
         'DESCRIPTION', T_('Expire Time'),
         'TEXTINPUT',   'expire_time', 20, 30, $vars['expire_time'],
         'TEXT',  '&nbsp;' .
            span('EditNote', sprintf( T_('(Date format [%s])'), FMT_PARSE_DATE ) .
                  ( $bulletin->is_user_bulletin() ? '' : ', ' . T_('can be empty') )
               ), ));

   $bform->add_row( array(
         'DESCRIPTION', T_('Subject'),
         'TEXTINPUT',   'subject', 80, 255, $vars['subject'] ));
   $bform->add_row( array(
         'DESCRIPTION', T_('Text'),
         'TEXTAREA',    'text', 80, 10, $vars['text'] ));

   $bform->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT',        span('TWarning', implode(', ', $edits), '[%s]'), ));

   $bform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'save', T_('Save bulletin'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'preview', T_('Preview'),
      ));

   if ( @$_REQUEST['preview'] || $bulletin->Subject . $bulletin->Text != '' )
   {
      $bform->add_empty_row();
      $bform->add_row( array(
            'DESCRIPTION', T_('Preview'),
            'OWNHTML', '<td class="Preview">' . GuiBulletin::build_view_bulletin($bulletin) . '</td>', ));
      if ( $vars['target_type'] == BULLETIN_TRG_USERLIST && (string)$vars['user_list'] != '' )
      {
         if ( is_array($bulletin->UserListUserRefs) )
         {
            $arr = array();
            foreach ( $bulletin->UserListUserRefs as $uid => $urow )
            {
               $arr[] = user_reference( REF_LINK, 1, '', $urow ) .
                  ( @$urow['C_RejectMsg']
                     ? span('FormWarning', T_('User has reject-message contact-setting with bulletin-author'), ' - %s' )
                     : '' ) .
                  "<br>\n";
            }

            $bform->add_row( array(
                  'DESCRIPTION', T_('Target Users'),
                  'TEXT', implode('', $arr) ));
         }
      }
   }


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $bform->echo_string();


   $menu_array = array();
   $menu_array[T_('All Bulletins')] =
      array( 'url' => "list_bulletins.php?read=2", 'class' => 'AdminLink' );
   $menu_array[T_('New user bulletin')] =
      array( 'url' => "admin_bulletin.php?n_uid=-1", 'class' => 'AdminLink' );
   $menu_array[T_('New admin bulletin')] =
      array( 'url' => "admin_bulletin.php", 'class' => 'AdminLink' );

   end_page(@$menu_array);
}//main


// return [ vars-hash, edits-arr, handle-msg-rejected-map, errorlist ]
function parse_edit_form( &$bulletin )
{
   $edits = array();
   $errors = array();
   $arr_rejected = array();
   $is_posted = ( @$_REQUEST['save'] || @$_REQUEST['preview'] );

   // read from props or set defaults
   $vars = array(
      'category'        => $bulletin->Category,
      'status'          => $bulletin->Status,
      'target_type'     => $bulletin->TargetType,
      'admin_note'      => $bulletin->AdminNote,
      'author'          => $bulletin->User->Handle,
      'publish_time'    => formatDate($bulletin->PublishTime),
      'expire_time'     => formatDate($bulletin->ExpireTime),
      'subject'         => $bulletin->Subject,
      'text'            => $bulletin->Text,
      'user_list'       => implode(' ', $bulletin->UserListHandles ),
      'tnews_tid'       => $bulletin->tid,
      'gid'             => $bulletin->gid,
      'warn_reject'     => 0,
   );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach ( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );
   // handle checkboxes having no key/val in _POST-hash
   if ( $is_posted )
   {
      foreach ( array( 'warn_reject' ) as $key )
         $vars[$key] = get_request_arg( $key, false );
   }

   // parse URL-vars
   if ( $is_posted )
   {
      $old_vals['publish_time'] = $bulletin->PublishTime;
      $old_vals['expire_time'] = $bulletin->ExpireTime;

      $bulletin->setCategory($vars['category']);
      $bulletin->setStatus($vars['status']);

      $bulletin->setTargetType($vars['target_type']);
      if ( $bulletin->TargetType == BULLETIN_TRG_UNSET ) // not defined in BD, must be set
         $errors[] = sprintf( T_('Bulletin target-type [%s] is only an initial value and must be changed!'),
            GuiBulletin::getTargetTypeText(BULLETIN_TRG_UNSET) );

      $new_value = trim($vars['tnews_tid']);
      $has_ttype_tourney = ( $bulletin->TargetType == BULLETIN_TRG_TP || $bulletin->TargetType == BULLETIN_TRG_TD );
      $need_tid = ( $bulletin->Category == BULLETIN_CAT_TOURNAMENT_NEWS ) || $has_ttype_tourney;
      if ( $has_ttype_tourney && ( (string)$new_value == '' || (int)$new_value <= 0 ) )
      {
         if ( $bulletin->Category == BULLETIN_CAT_TOURNAMENT_NEWS )
            $errors[] = sprintf( T_('Bulletin Category [%s] requires a tournament-ID!'),
               GuiBulletin::getCategoryText($bulletin->Category) );
         if ( $has_ttype_tourney )
            $errors[] = sprintf( T_('Bulletin Target-Type [%s] requires a tournament-ID!'),
               GuiBulletin::getTargetTypeText($bulletin->TargetType) );
      }
      elseif ( $new_value && !is_numeric($new_value) )
         $errors[] = T_('Tournament-ID must be numeric!');
      elseif ( $need_tid )
      {
         $tourney = TournamentCache::load_cache_tournament( 'admin_bulletin.parse_edit_form', $new_value, /*check*/false );
         if ( is_null($tourney) )
            $errors[] = sprintf( T_('No tournament found for tournament-ID [%s]!'), $new_value );
         else
         {
            $bulletin->tid = $new_value;
            $bulletin->Tournament = $tourney;
         }
      }
      else
      {
         $bulletin->tid = 0;
         $bulletin->Tournament = null;
      }
      if ( (string)$new_value != '' && (int)$new_value > 0 && !$has_ttype_tourney )
      {
         if ( $bulletin->Category == BULLETIN_CAT_TOURNAMENT_NEWS )
            $errors[] = sprintf( T_('Target-Type must be set to [%s or %s] if Category is [%s]!'),
               GuiBulletin::getTargetTypeText(BULLETIN_TRG_TP), GuiBulletin::getTargetTypeText(BULLETIN_TRG_TD),
               GuiBulletin::getCategoryText(BULLETIN_CAT_TOURNAMENT_NEWS) );
         else
            $errors[] = sprintf( T_('Tournament-ID [%s] must be cleared when Target-Type is not [%s or %s] and Category is not [%s]!'),
               $new_value,
               GuiBulletin::getTargetTypeText(BULLETIN_TRG_TP), GuiBulletin::getTargetTypeText(BULLETIN_TRG_TD),
               GuiBulletin::getCategoryText(BULLETIN_CAT_TOURNAMENT_NEWS) );
      }

      $new_value = trim($vars['gid']);
      $need_gid = ( $bulletin->TargetType == BULLETIN_TRG_MPG );
      if ( $need_gid && ( (string)$new_value == '' || (int)$new_value <= 0 ) )
      {
         $errors[] = sprintf( T_('Bulletin Target-Type [%s] requires a game-ID!'),
            GuiBulletin::getTargetTypeText($bulletin->TargetType) );
      }
      elseif ( $new_value && !is_numeric($new_value) )
         $errors[] = T_('Game-ID must be numeric!');
      elseif ( $need_gid )
      {
         $game_row = Bulletin::load_multi_player_game($new_value);
         if ( is_null($game_row) )
            $errors[] = sprintf( T_('No game found for game-ID [%s]!'), $new_value );
         elseif ( $game_row['GameType'] != GAMETYPE_TEAM_GO && $game_row['GameType'] != GAMETYPE_ZEN_GO )
            $errors[] = sprintf( T_('Game-ID [%s] must reference a multi-player-game!'), $new_value );
         else
            $bulletin->gid = $new_value;
      }
      else
         $bulletin->gid = 0;
      if ( (string)$new_value != '' && (int)$new_value > 0 && !$need_gid )
      {
         $errors[] = sprintf( T_('Game-ID [%s] must be cleared when Target-Type is not [%s]!'),
            $new_value, GuiBulletin::getTargetTypeText(BULLETIN_TRG_MPG) );
      }


      // NOTE: must be parsed before user-list
      $new_value = trim($vars['author']);
      if ( (string)$new_value == '' )
         $errors[] = T_('Missing bulletin author');
      else
      {
         $user = User::load_user_by_handle( $new_value );
         if ( is_null($user) && is_numeric($new_value) && $new_value > GUESTS_ID_MAX )
            $user = User::load_user( $new_value );
         if ( is_null($user) )
            $errors[] = sprintf( T_('No user found for author [%s]'), $new_value );
         else
         {
            $bulletin->uid = $user->ID;
            $bulletin->User = $user;
            $vars['author'] = $user->Handle;
         }
      }

      $new_value = trim($vars['user_list']);
      if ( (string)$new_value != '' )
      {
         if ( $bulletin->TargetType != BULLETIN_TRG_USERLIST )
            $errors[] = T_('User-list must be cleared when the target-type is changed#bulletin');

         list( $arr_handles, $arr_uids, $arr_urefs, $arr_rejected, $check_errors ) =
            check_user_list( $new_value, $bulletin->uid );
         if ( count($check_errors) > 0 )
            $errors = array_merge( $errors, $check_errors );
         else
         {
            $bulletin->UserList = $arr_uids;
            $bulletin->UserListHandles = $arr_handles;
            $bulletin->UserListUserRefs = $arr_urefs;
            $vars['user_list'] = implode(' ', $arr_handles); // re-format
         }
      }
      if ( count($arr_rejected) && !$vars['warn_reject'] ) // don't show on preview
         $errors[] = T_('There are User List Warnings which must be checked for saving bulletin!');

      $parsed_value = parseDate( T_('Publish time for bulletin'), $vars['publish_time'] );
      if ( is_numeric($parsed_value) )
      {
         $bulletin->PublishTime = $parsed_value;
         $vars['publish_time'] = formatDate($bulletin->PublishTime);
      }
      else
         $errors[] = $parsed_value;
      if ( $bulletin->PublishTime == 0 )
         $errors[] = T_('Missing Publish time');

      $parsed_value = parseDate( T_('Expire time for bulletin'), $vars['expire_time'] );
      if ( is_numeric($parsed_value) )
      {
         if ( GuiBulletin::check_expiretime( $bulletin, $parsed_value, $errors ) )
         {
            $bulletin->ExpireTime = $parsed_value;
            $vars['expire_time'] = formatDate($bulletin->ExpireTime);
         }
      }
      else
         $errors[] = $parsed_value;

      $new_value = trim($vars['subject']);
      if ( strlen($new_value) < 4 )
         $errors[] = T_('Bulletin subject missing or too short');
      else
         $bulletin->Subject = $new_value;

      $bulletin->Text = trim($vars['text']);

      $new_value = trim($vars['admin_note']);
      $miss_admnote = false;
      if ( $bulletin->is_user_bulletin() && !$new_value )
      {
         if ( $bulletin->Status == BULLETIN_STATUS_NEW || $bulletin->Status == BULLETIN_STATUS_PENDING
               || $bulletin->Status == BULLETIN_STATUS_REJECTED )
            $miss_admnote = true;
      }
      if ( $miss_admnote )
         $errors[] = sprintf( T_('Missing admin-note for user-changeable bulletin on status [%s].'),
            GuiBulletin::getStatusText($bulletin->Status) );
      else
         $bulletin->AdminNote = $new_value;


      // determine edits
      if ( $old_vals['category'] != $bulletin->Category ) $edits[] = T_('Category');
      if ( $old_vals['status'] != $bulletin->Status ) $edits[] = T_('Status');
      if ( $old_vals['target_type'] != $bulletin->TargetType ) $edits[] = T_('Target Type#bulletin');
      if ( $old_vals['admin_note'] != $bulletin->AdminNote ) $edits[] = T_('Admin Note');
      if ( $old_vals['tnews_tid'] != $bulletin->tid ) $edits[] = T_('Tournament ID');
      if ( $old_vals['gid'] != $bulletin->gid ) $edits[] = T_('Game ID');
      if ( $old_vals['user_list'] != $vars['user_list'] ) $edits[] = T_('User List');
      if ( $old_vals['author'] != $vars['author'] ) $edits[] = T_('Author');
      if ( $old_vals['publish_time'] != $bulletin->PublishTime ) $edits[] = T_('Publish Time');
      if ( $old_vals['expire_time'] != $bulletin->ExpireTime ) $edits[] = T_('Expire Time');
      if ( $old_vals['subject'] != $bulletin->Subject ) $edits[] = T_('Subject');
      if ( $old_vals['text'] != $bulletin->Text ) $edits[] = T_('Text');
   }

   return array( $vars, array_unique($edits), $arr_rejected, $errors );
}//parse_edit_form

?>
