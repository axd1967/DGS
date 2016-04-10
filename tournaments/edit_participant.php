<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Tournament";

chdir('..');
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'include/game_functions.php';
require_once 'include/rating.php';
require_once 'include/error_codes.php';
require_once 'include/db/bulletin.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_log_helper.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_status.php';

$GLOBALS['ThePage'] = new Page('TournamentEditParticipant');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.edit_participant');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_participant');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'Tournament.edit_participant');

   $page = "edit_participant.php";

/* Actual REQUEST calls used (TD=tournament-director)
     tid=                                 : edit TPs (no user selected)
     tid=&uid=                            : edit TP (given by Players.ID = uid)
     tid=&showuser=123&tp_showuser_uid    : edit TP (given by Players.ID = showuser)
     tid=&showuser=abc&tp_showuser_handle : edit TP (given by Players.Handle = showuser)

     tp_preview&tid=&uid=                 : preview edits for TP
     tp_save&tid=&uid=                    : update TP-registration in database
     tp_delete&tid=&uid=                  : remove TP (need confirm)
     tp_delete&confirm=1&tid=&uid=        : remove TP (confirmed)
     tp_cancel&tid=&uid=                  : cancel remove-confirmation or cancel editing

   Fields for preview & save:
       status                             : new status (only some combinations)
       custom_rating,ratingtype           : customized rating + rating-type for conversion
       del_rating=1                       : remove TP-Rating if set
       start_round                        : start round (if needed)
       admin_message, admin_notes         : AdminMessage, Notes
*/

   $tid = (int) @$_REQUEST['tid'];
   $uid = (int) @$_REQUEST['uid'];
   $is_delete = (bool) @$_REQUEST['tp_delete'];
   $ignore_warnings = get_request_arg('no_warn');
   $userhandle = get_request_arg('showuser');
   $user = NULL;

   if ( @$_REQUEST['tp_cancel'] ) // cancel delete or edit
      jump_to("tournaments/edit_participant.php?tid=$tid".URI_AMP."uid=$uid");

   $tourney = TournamentCache::load_cache_tournament( "Tournament.edit_participant.find_tournament($my_id)", $tid );
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);

   $allow_edit_tourney = TournamentHelper::allow_edit_tournaments($tourney, $my_id);
   if ( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.edit_participant($tid,$my_id)");
   $is_admin = TournamentUtils::isAdmin();

   $errors = array();
   $status_errors = $tstatus->check_edit_status( $ttype->allow_register_tourney_status );

   // load-user, change-user?
   if ( @$_REQUEST['tp_showuser_uid'] )
   {
      if ( is_numeric($userhandle) )
         $user = User::load_user( $userhandle );
   }
   elseif ( @$_REQUEST['tp_showuser_handle'] )
   {
      if ( (string)$userhandle != '' )
         $user = User::load_user_by_handle( $userhandle );
   }
   else
      $user = ( $uid <= GUESTS_ID_MAX ) ? null : User::load_user($uid);

   if ( is_null($user) )
   {
      if ( $uid > GUESTS_ID_MAX )
         $errors[] = T_('Unknown user');
      $uid = 0;
   }
   else
   {
      $uid = $user->ID;
      if ( (string)$userhandle == '' )
         $userhandle = $user->Handle;
   }

   // check for guest-user
   if ( $uid != 0 && $uid <= GUESTS_ID_MAX )
      jump_to("tournaments/edit_participant.php?tid=$tid".URI_AMP."sysmsg="
            . urlencode( T_('Guest users can not be edited, please choose another user!') ));

   // user eligible?
   $tprops = ( is_null($user) )
      ? null
      : TournamentCache::load_cache_tournament_properties( 'Tournament.edit_participant', $tid );

   // existing participation ?
   $tp = ( $uid > 0 )
      ? TournamentCache::load_cache_tournament_participant( 'Tournament.edit_participant', $tid, $uid )
      : null;
   if ( is_null($tp) )
   {
      if ( !is_null($user) )
         $tp = new TournamentParticipant( 0, $tid, $user->ID, $user ); // new TP
      else
         $tp = new TournamentParticipant( 0, $tid ); // dummy "null" TP
   }
   $rid = $tp->ID; // 0 (=register new), >0 (=edit)

   // authorize actions
   $authorise_delete = $tp->authorise_delete( $tourney->Status );
   $authorise_edit_custom = $tp->authorise_edit_customized( $tourney->Status );


   // init
   $old_status = $tp->Status;
   $old_flags = $tp->Flags;
   $old_rating = $tp->Rating;
   $old_start_round = $tp->StartRound;
   $old_tp = clone $tp;

   // check + parse edit-form
   list( $vars, $edits, $input_errors ) = parse_edit_form( $tp, $tourney, $ttype, $tprops );
   list( $reg_errors, $reg_warnings ) = ( !is_null($tprops) )
      ? $tprops->checkUserRegistration( $tourney, $tp, $user, TCHKTYPE_TD, TCHKFLAG_OLD_GAMES )
      : array( array(), array() );

   // check user max-games
   if ( !is_null($user) )
   {
      $userMaxGamesCheck = new MaxGamesCheck( $user->urow );
      if ( !$rid && !$userMaxGamesCheck->allow_tournament_registration() )
      {
         if ( $is_admin )
            $reg_warnings[] = ErrorCode::get_error_text('max_games_user_tourney_reg');
         else
            $errors[] = ErrorCode::get_error_text('max_games_user_tourney_reg');
      }
   }

   list( $lock_errors, $lock_warnings ) = $tourney->checkRegistrationLocks(TCHKTYPE_TD);
   if ( count($lock_errors) )
      $errors = array_merge( $lock_errors, $errors );

   if ( !$rid ) // new-TP
   {
      if ( count($reg_errors) )
         $errors[] = T_('[Errors]: Restrictions do not allow user to register.#tourney');
      if ( !$ignore_warnings && count($reg_warnings) )
         $errors[] = T_('[Warnings]: Restrictions normally do not allow user to register.#tourney');

      $tp->Status = TP_STATUS_INVITE; // TD can only invite
   }

   // check exceptions for edit-allowed on PLAY-status for TP with higher start-round than current round
   $allow_blocked_edit = false;
   $warnings = array();
   if ( $ttype->need_rounds && $tourney->Status == TOURNEY_STATUS_PLAY && count($status_errors) > 0
         && $tp->StartRound > $tourney->CurrentRound )
   {
      if ( !$rid ) // allow new-TP with higher start-round than current round
         $allow_blocked_edit = true;
      elseif ( $rid && @$_REQUEST['tp_delete'] ) // allow removal of existing-TP only on higher start-round (=withdrawal)
         $allow_blocked_edit = true;

      $warnings[] = make_html_safe( T_("Edit is normally forbidden except for adding or removing user \n" .
         "on higher start round than current round.#tourney"), true);
   }
   if ( $allow_blocked_edit )
   {
      $errors = array_merge( $errors, $input_errors );
      $warnings = array_merge( $lock_warnings, $status_errors, $warnings );
   }
   else
   {
      $errors = array_merge( $status_errors, $errors, $input_errors );
      $warnings = array_merge( $lock_warnings, $warnings );
   }


   // ---------- Process inputs into actions ------------------------------------

   if ( !is_null($user) ) // edit
   {
      if ( $rid && $is_delete && $authorise_delete && @$_REQUEST['confirm'] && count($errors) == 0 ) // confirm delete TP-reg
      {
         ta_begin();
         {//HOT-section to delete tournament-participant
            TournamentParticipant::delete_tournament_participant( $tid, $rid );
            $sys_msg = send_register_notification( 'delete', $tp, $my_id );
            Bulletin::update_count_bulletin_new( "Tournament.edit_participant.del_tp($tid)", $uid );
            TournamentLogHelper::log_tp_registration_by_director(
               TLOG_ACT_REMOVE, $tid, $allow_edit_tourney, $uid, $tp->build_log_string() );
         }
         ta_end();

         jump_to("tournaments/edit_participant.php?tid=$tid".URI_AMP."uid=$uid".URI_AMP."sysmsg=$sys_msg");
      }

      // check user-denial
      if ( ($user->AdminOptions & ADMOPT_DENY_TOURNEY_REGISTER) && $tp->Status != TP_STATUS_REGISTER )
         $errors[] = T_('Tournament registration of this user has been denied by admins.');

      // check status integrity
      $is_customized = false;
      if ( $tp->StartRound > 1 && $tp->StartRound != $old_start_round )
         $is_customized = true;
      if ( $tp->hasRating() && (int)$old_rating != (int)$tp->Rating )
         $is_customized = true;
      elseif ( !$tp->hasRating() && is_valid_rating($old_rating) )
         $is_customized = true;
      if ( $is_customized )
         $tp->Status = TP_STATUS_INVITE;

      if ( $tp->Status == TP_STATUS_INVITE )
         $tp->Flags = ($tp->Flags | TP_FLAG_INVITED) & ~TP_FLAG_ACK_INVITE;
      elseif ( $old_status == TP_STATUS_APPLY && $tp->Status == TP_STATUS_REGISTER )
         $tp->Flags |= TP_FLAG_ACK_APPLY;
      if ( count($reg_errors) || count($reg_warnings) ) // violate registration restrictions
         $tp->Flags |= TP_FLAG_VIOLATE;

      if ( $old_status != $tp->Status )
         $edits[] = T_('Status');

      $tp->authorise_edit_register_status($tourney->Status, $old_status, $errors); // check status-change

      // persist TP in database
      if ( $tid && @$_REQUEST['tp_save'] && !@$_REQUEST['tp_preview'] && count($errors) == 0
            && count($reg_errors) == 0 && ( $rid || $ignore_warnings || count($reg_warnings) == 0 ) )
      {
         if ( ($tp->Status == TP_STATUS_REGISTER || $tp->Status == TP_STATUS_INVITE) && $tprops->need_rating_copy()
               && !$tp->hasRating() )
         {
            if ( !$vars['_has_custom_rating'] ) // copy only if no customized-rating
               $tp->Rating = $tp->User->Rating;
         }
         $tp->NextRound = $tp->StartRound; //copy on REGISTER

         ta_begin();
         {//HOT-section to update tournament-registration
            $ttype->joinTournament( $tourney, $tp, $allow_edit_tourney ); // insert or update (and join eventually)
            TournamentParticipant::sync_tournament_registeredTP( $tid, $old_status, $tp->Status );

            // send notification (if needed)
            if ( $old_status == TP_STATUS_APPLY && $tp->Status == TP_STATUS_REGISTER ) // APPLY-ACK
            {
               $sys_msg = send_register_notification( 'ack_apply', $tp, $my_id );
               $tlog_chgtype = 'ack_apply';
            }
            elseif ( $old_status != $tp->Status && $tp->Status == TP_STATUS_INVITE ) // customized -> INVITE
            {
               $ntype = ( $rid ) ? $old_status.'_invite' : 'new_invite';
               $sys_msg = send_register_notification( $ntype, $tp, $my_id );
               $tlog_chgtype = strtolower($ntype);
            }
            else
            {
               $sys_msg = urlencode( T_('Tournament Registration saved!') );
               $tlog_chgtype = 'edit';
            }

            if ( $rid == 0 ) // new TP
            {
               Bulletin::update_count_bulletin_new( "Tournament.edit_participant.add_tp($tid)", $uid );
               TournamentLogHelper::log_tp_registration_by_director(
                  TLOG_ACT_CREATE, $tid, $allow_edit_tourney, $uid, $tp->build_log_string() );
            }
            else
            {
               TournamentLogHelper::log_change_tp_registration_by_director( $tid, $allow_edit_tourney, $uid,
                  $tlog_chgtype, $edits, $old_tp, $tp );
            }
         }
         ta_end();

         jump_to("tournaments/edit_participant.php?tid=$tid".URI_AMP."uid=$uid".URI_AMP."sysmsg=$sys_msg");
      }

      if ( $tp->Status == TP_STATUS_REGISTER && count($edits) == 0 && !$is_delete )
         $reg_warnings = array();
   }//known-user


   // ---------- Tournament-Registration EDIT form ------------------------------

   $tpform = new Form( 'tournamenteditparticipant', $page, FORM_POST );
   $tpform->add_hidden( 'tid', $tid );
   $tpform->add_hidden( 'uid', $uid );

   // edit registration
   $tpform->add_row( array(
         'DESCRIPTION',    T_('Edit User'),
         'TEXTINPUT',      'showuser', 16, 16, $userhandle,
         'SUBMITBUTTON',   'tp_showuser_handle', T_('Show User by Handle'),
         'SUBMITBUTTON',   'tp_showuser_uid', T_('Show User by ID'), ));
   $tpform->add_row( array( 'HR' ));

   $tpform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   $tpform->add_row( array(
         'DESCRIPTION', T_('Tournament Status'),
         'TEXT',        Tournament::getStatusText($tourney->Status), ));
   $tpform->add_row( array(
         'DESCRIPTION', T_('Tournament Round'),
         'TEXT',        $tourney->formatRound(), ));
   if ( !is_null($user) )
      $tpform->add_row( array(
            'DESCRIPTION', T_('User'),
            'TEXT', $user->user_reference(), ));

   if ( count($errors) || count($warnings) )
      $tpform->add_row( array( 'HR' ));
   if ( count($errors) )
   {
      $tpform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString( T_('There are some errors'), $errors ) ));
   }
   if ( count($warnings) )
   {
      $tpform->add_row( array(
            'DESCRIPTION', T_('Warning'),
            'TEXT', buildWarnListString(T_('There are some warnings'), $warnings) ));
   }

   if ( count($reg_errors) || count($reg_warnings) )
   {
      $tpform->add_row( array( 'HR' ));
      $tpform->add_row( array( 'HEADER', T_('Registration restrictions#tourney') ));
      if ( count($reg_errors) )
         $tpform->add_row( array(
               'OWNHTML', buildErrorListString(
                          T_('[Errors]: User is not allowed to register for this tournament'), $reg_errors, 2) ));
      if ( count($reg_warnings) )
      {
         $tpform->add_row( array(
               'OWNHTML', buildWarnListString(
                          T_('[Warnings]: User is normally not allowed to register for this tournament'), $reg_warnings, 2) ));
         if ( !$rid && count($reg_errors) == 0 ) // no ignore on error, else ignore only for NEW-reg
            $tpform->add_row( array(
                  'CHECKBOX', 'no_warn', '1', T_('Ignore warnings'), (get_request_arg('no_warn') ? 1 : '') ));
      }
      $tpform->add_row( array( 'HR' ));
   }


   if ( !is_null($user) ) // edit
   {
      $tpform->add_row( array( 'HR' ));
      if ( $tp->Created > 0 )
         $tpform->add_row( array(
               'DESCRIPTION', T_('Created'),
               'TEXT',        date(DATE_FMT2, $tp->Created), ));
      if ( $tp->Lastchanged > 0 )
         $tpform->add_row( array(
               'DESCRIPTION', T_('Last changed'),
               'TEXT',        TournamentUtils::buildLastchangedBy($tp->Lastchanged, $tp->ChangedBy) ));
      if ( $tp->Lastmoved > 0 )
         $tpform->add_row( array(
               'DESCRIPTION', T_('Tournament last move'),
               'TEXT',        date(DATE_FMT2, $tp->Lastmoved) ));

      // EDIT: Status ---------------------

      $old_status_str = ($old_status) ? TournamentParticipant::getStatusText($old_status) : NO_VALUE;

      $tpform->add_row( array(
            'DESCRIPTION', T_('Current Registration Status#tourney'),
            'TEXT',        $old_status_str, ));
      if ( $authorise_edit_custom )
      {
         $tpform->add_row( array(
               'DESCRIPTION', T_('New Registration Status#tourney'),
               'TEXT',        span('TWarning', TournamentParticipant::getStatusText($tp->Status)) .
                              SMALL_SPACING,
               'SELECTBOX',   'status', 1, build_new_status_choices($tp), $vars['status'], false, ));
      }

      $tpform->add_row( array(
            'DESCRIPTION', T_('Current Flags'),
            'TEXT',        TournamentParticipant::getFlagsText($old_flags), ));
      if ( $old_flags != $tp->Flags )
         $tpform->add_row( array(
               'DESCRIPTION', T_('New Flags'),
               'TEXT',        TournamentParticipant::getFlagsText($tp->Flags), ));
      $tpform->add_empty_row();

      // EDIT: Ratings --------------------

      $tpform->add_row( array(
            'DESCRIPTION', T_('Rating Use Mode#tourney'),
            'TEXT',  TournamentProperties::getRatingUseModeText($tprops->RatingUseMode)
                     . "<br>\n"
                     . span('smaller', TournamentProperties::getRatingUseModeText($tprops->RatingUseMode, false), '(%s)'), ));
      $tpform->add_row( array(
            'DESCRIPTION', T_('Current User Rating'),
            'TEXT',        build_rating_str($tp->User->Rating, $tp->uid), ));
      if ( !$is_delete && $authorise_edit_custom && $tprops->allow_rating_edit() )
      {
         $custom_rating_str = ( $tp->hasRating() && $vars['_has_custom_rating'] )
            ? echo_rating($tp->Rating, true) : NO_VALUE;
         $rating_type = get_request_arg('rating_type', 'dragonrank');

         $tpform->add_row( array(
               'DESCRIPTION', T_('Customized Rating#tourney'),
               'TEXT',        $custom_rating_str . SMALL_SPACING,
               'TEXTINPUT',   'custom_rating', 10, 10, $vars['custom_rating'],
               'SELECTBOX',   'rating_type', 1, getRatingTypes(), $rating_type, false,
               'SUBMITBUTTON', 'tp_preview', T_('Convert Rating'), ));
      }

      if ( !$is_delete && $tp->hasRating() )
      {
         $arr = array(
               'DESCRIPTION', T_('Tournament Rating'),
               'TEXT',        build_rating_str($tp->Rating, $tp->uid), );
         if ( !$is_delete && $authorise_edit_custom && $tp->User->hasRating() ) // T-rating not needed if user has rating
            array_push( $arr,
               'TEXT', SMALL_SPACING,
               'CHECKBOX', 'del_rating', '1', T_('Remove customized rating#tourney'),
                           (get_request_arg('del_rating') ? 1 : '') );
         $tpform->add_row( $arr );
      }
      $tpform->add_empty_row();

      // EDIT: Rounds ---------------------

      if ( $ttype->need_rounds )
      {
         $tpform->add_row( array(
               'DESCRIPTION', T_('Current Start Round#tourney'),
               'TEXT',        $old_start_round, ));

         if ( $tprops->MaxStartRound > 1 && !$is_delete && $authorise_edit_custom )
         {
            $tpform->add_row( array(
                  'DESCRIPTION', T_('Customized Start Round#tourney'),
                  'TEXTINPUT',   'start_round', 3, 3, get_request_arg('start_round'),
                  'TEXT',        ' ' . sprintf( T_('Range %s#tourney'), build_range_text(1, $tprops->MaxStartRound)), ));

            $edit_warning = null;
            if ( $tprops->MinRatingStartRound == NO_RATING )
               $edit_warning = T_('Warning: Users are not allowed to choose a customized start round.#tourney');
            elseif ( $user->Rating < $tprops->MinRatingStartRound )
            {
               $edit_warning = sprintf(
                     T_("Warning: This user [%s] has a rating [%s] lower than the minimum rating [%s]\n" .
                        "specified for this tournament to allow the user to choose a customized start round."),
                     $user->Handle, echo_rating($user->Rating, true), echo_rating($tprops->MinRatingStartRound, true) );
            }
            if ( !is_null($edit_warning) )
               $tpform->add_row( array( 'TAB', 'TEXT', span('TWarning', make_html_safe($edit_warning, true)), ));
         }

         if ( !$is_delete )
            $tpform->add_empty_row();
      }

      // EDIT: Texts ----------------------

      if ( !$is_delete )
      {
         $tpform->add_row( array(
               'DESCRIPTION', T_('Public User Comment#tourney'),
               'TEXT',        $tp->Comment, ));

         $tpform->add_row( array(
               'DESCRIPTION', T_('User Message#tourney'),
               'TEXT', make_html_safe( $tp->UserMessage, true ) ));

         $tpform->add_row( array(
               'DESCRIPTION', T_('Admin Message'),
               'TEXTAREA',    'admin_message', 70, 5, $tp->AdminMessage ));
         if ( @$_REQUEST['tp_preview'] )
            $tpform->add_row( array(
                  'DESCRIPTION', T_('Preview'),
                  'TEXT', make_html_safe($tp->AdminMessage, true), ));
         $tpform->add_row( array(
               'DESCRIPTION', T_('Admin Notes'),
               'TEXTAREA',    'admin_notes', 70, 5, $tp->Notes ));

         $tpform->add_row( array(
               'DESCRIPTION', T_('Unsaved edits'),
               'TEXT',        span('TWarning', implode(', ', $edits ), '[%s]'), ));
      }

      // EDIT: Submit-Buttons -------------

      if ( $is_delete )
      {
         if ( $authorise_delete )
         {
            $tpform->add_hidden( 'confirm', 1 );
            $tpform->add_row( array(
                  'TAB', 'CELL', 1, '', // align submit-buttons
                  'SUBMITBUTTON', 'tp_delete', T_('Confirm removal of registration#tourney'),
                  'TEXT', SMALL_SPACING,
                  'SUBMITBUTTON', 'tp_cancel', T_('Cancel') ));
         }
      }
      else
      {
         $rowarr = array(
               'TAB', 'CELL', 1, '', // align submit-buttons
               'SUBMITBUTTON', 'tp_save', ($rid ? T_('Update registration#tourney') : T_('Save registration#tourney')),
               'SUBMITBUTTON', 'tp_preview', T_('Preview') );
         if ( $rid && $authorise_delete )
            array_push( $rowarr,
                  'TEXT', SMALL_SPACING,
                  'SUBMITBUTTON', 'tp_delete', T_('Remove registration#tourney') );
         if ( @$_REQUEST['tp_save'] || @$_REQUEST['tp_preview'] )
            array_push( $rowarr,
                  'TEXT', SMALL_SPACING,
                  'SUBMITBUTTON', 'tp_cancel', T_('Cancel') );
         $tpform->add_row( $rowarr );
      }
   } //user_known


   $title = sprintf( T_('Tournament Registration Editor for [%s]'), $tourney->Title );
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $sectmenu = array();
   $sectmenu[T_('Show users')] = "users.php?tid=$tid";
   $sectmenu[T_('Show my opponents')] = "opponents.php?tid=$tid";
   $sectmenu[T_('Show my contacts')] = "list_contacts.php?tid=$tid";
   make_menu( $sectmenu, false);
   echo "<p></p>\n";

   $tpform->echo_string();

   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Tournament participants')] = "tournaments/list_participants.php?tid=$tid";
   $menu_array[T_('Tournament directors')] = "tournaments/list_directors.php?tid=$tid";
   if ( $tourney->Type == TOURNEY_TYPE_LADDER && $rid )
   {
      if ( $tourney->Status == TOURNEY_STATUS_PAIR || $tourney->Status == TOURNEY_STATUS_PLAY )
         $menu_array[T_('Admin user')] =
               array( 'url' => "tournaments/ladder/admin.php?tid=$tid".URI_AMP."uid=$uid", 'class' => 'TAdmin' );
   }
   $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}//main


function build_rating_str( $rating, $uid=0 )
{
   return (is_valid_rating($rating)) ? echo_rating($rating, true, $uid) : NO_VALUE;
}

function build_new_status_choices( $tp )
{
   $arr = array() + TournamentParticipant::getStatusText();
   if ( $tp->ID == 0 || $tp->Status == TP_STATUS_INVITE )
   {
      unset($arr[TP_STATUS_APPLY]);
      unset($arr[TP_STATUS_REGISTER]);
   }
   elseif ( $tp->Status == TP_STATUS_REGISTER )
      unset($arr[TP_STATUS_APPLY]);
   return $arr;
}

// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$tp, $tourney, $ttype, $tprops )
{
   $edits = array();
   $errors = array();

   $is_posted = ( @$_REQUEST['tp_save'] || @$_REQUEST['tp_preview'] || @$_REQUEST['tp_delete'] );

   // read from props or set defaults
   $vars = array(
      'status'             => $tp->Status,
      'custom_rating'      => '', // no default
      '_has_custom_rating' => false,
      'start_round'        => $tp->StartRound,
      'admin_message'      => $tp->AdminMessage,
      'admin_notes'        => $tp->Notes,
   );
   $old_rating = $tp->Rating;

   $old_vals = array() + $vars; // copy to determine edit-changes
   if ( !@$_REQUEST['tp_showuser_handle'] && !@$_REQUEST['tp_showuser_uid'] )
   {
      // read URL-vals into vars
      foreach ( $vars as $key => $val )
         $vars[$key] = get_request_arg( $key, $val );
   }

   // parse URL-vars
   if ( $is_posted )
   {
      $old_vals['custom_rating'] = NO_RATING;

      $tp->setStatus( $vars['status'] );

      $new_value = trim($vars['custom_rating']); // optional
      if ( (string)$new_value != '' )
      {
         $rating_type = get_request_arg('rating_type', 'dragonrank');
         $custom_rating = convert_to_rating( $new_value, $rating_type, MAX_ABS_RATING, true );
         if ( !is_valid_rating($custom_rating) )
            $errors[] = ErrorCode::get_error_text('invalid_rating');
         else
         {
            $tp->Rating = $custom_rating;
            $vars['_has_custom_rating'] = true;
         }
      }
      if ( get_request_arg('del_rating') )
         $tp->Rating = NO_RATING;

      if ( $ttype->need_rounds && $tprops->MaxStartRound > 1 )
      {
         $new_value = trim($vars['start_round']); // optional
         if ( (string)$new_value != '' )
         {
            if ( !is_numeric($new_value) || $new_value < 1 || $new_value > $tprops->MaxStartRound )
               $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('Customized Start Round#tourney'),
                  build_range_text(1, $tprops->MaxStartRound) );
            else
               $tp->StartRound = $new_value;
         }
      }

      // determine edits
      if ( $old_rating != $tp->Rating ) $edits[] = T_('Rating');
      if ( $old_vals['start_round'] != $tp->StartRound ) $edits[] = T_('Start Round#tourney');
   }

   if ( $is_posted || @$_REQUEST['tp_ack_apply'] )
   {
      $tp->AdminMessage = trim($vars['admin_message']);
      $tp->Notes = trim($vars['admin_notes']);

      // determine edits
      if ( $old_vals['admin_message'] != $tp->AdminMessage ) $edits[] = T_('Admin Message');
      if ( $old_vals['admin_notes'] != $tp->Notes ) $edits[] = T_('Admin Notes');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form

// sends out message to user and returns URL-encoded system-message
function send_register_notification( $type, $tp, $my_id )
{
   $sep = str_repeat('-', 20). "\n";
   $tid = $tp->tid;
   $uid = $tp->uid;

   $body2 = '';
   switch ( (string)$type )
   {
      case 'delete':
         $subj = T_('Removed registration for tournament #%s');
         $body = T_('Your registration for %s has been deleted by tournament director %s.');
         $msg  = T_('Participant [%s] removed!#tourney');
         break;
      case 'ack_apply':
         $subj = T_('Approved registration for tournament #%s');
         $body = T_('Your application for %s has been approved by tournament director %s.');
         $msg  = T_('Application of user [%s] accepted!#tourney');
         break;
      case 'new_invite':
         $subj = T_('Invitation for tournament #%s');
         $body = T_('Tournament director %2$s has invited you for %1$s. This invitation must be verified by you by either approving or rejecting it.');
         $body2 = anchor( SUB_PATH."tournaments/register.php?tid=$tid", T_('Edit tournament registration') );
         $msg  = T_('User [%s] invited!#tourney');
         break;
      case TP_STATUS_APPLY.'_invite':
         $subj = T_('Verification of registration for tournament #%s');
         $body = T_('Your application for %s has been verified by tournament director %s and changed to an invitation, which needs your verification.');
         $body2 = anchor( SUB_PATH."tournaments/register.php?tid=$tid", T_('Edit tournament registration') );
         $msg  = T_('Application of user [%s] changed to invitation!#tourney');
         break;
      case TP_STATUS_REGISTER.'_invite':
         $subj = T_('Verification of registration for tournament #%s');
         $body = T_('Your registration for %s has been verified by tournament director %s and changed to an invitation, which needs your verification.');
         $body2 = anchor( SUB_PATH."tournaments/register.php?tid=$tid", T_('Edit tournament registration') );
         $msg  = T_('Registration of user [%s] changed to invitation!#tourney');
         break;
      default:
         error('invalid_args', "tournament.edit_participant($type,$tid,$uid,$my_id)");
   }

   send_message( "tournament.edit_participant.$type($tid,$uid,$my_id)",
      trim( sprintf( $body, "<tourney $tid>", "<user $my_id>" ) . "\n\n" .
            ( $body2 ? "$body2\n\n" : '' ) .
            "$sep<b>" . T_('Last user message#tourney') . ":</b>\n" . $tp->UserMessage . "\n" .
            "$sep<b>" . T_('Last admin message#tourney') . ":</b>\n" . $tp->AdminMessage ),
      sprintf( $subj, $tid ),
      $uid, '', /*notify*/true,
      0/*sys-msg*/, MSGTYPE_NORMAL );

   return urlencode( sprintf( "$msg " . T_('Message sent to user!'), $tp->User->Handle ) );
}//send_register_notification
?>
