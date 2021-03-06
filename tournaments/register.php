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
require_once 'include/db/bulletin.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_gui_helper.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_factory.php';

$GLOBALS['ThePage'] = new Page('TournamentRegistration');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.register');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.register');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'Tournament.register');
   if ( @$player_row['AdminOptions'] & ADMOPT_DENY_TOURNEY_REGISTER )
      error('tournament_register_denied', 'Tournament.register');

   $page = "register.php";

/* Actual REQUEST calls used (TD=tournament-director)
     tid=                           : current user registers for tournament
     tid=&rid=                      : current user edits existing registration
     tp_preview&tid=&rid=           : preview for T-registration-save
     tp_save&tid=&rid=              : update registration in database
     tp_delete&tid=&rid=            : remove registration (need confirm)
     tp_delete&confirm=1&tid=&rid=  : remove registration (confirmed)
     tp_cancel&tid=&rid=            : cancel remove-confirmation
     tp_ack_invite&tid=             : approve invitation by TP
     tp_edit_invite&tid=            : reject invitation by TP, transforming to user-application
*/

   $tid = (int) @$_REQUEST['tid'];
   if ( $tid < 0 ) $tid = 0;
   $rid = (int) @$_REQUEST['rid'];
   $is_delete = (bool) @$_REQUEST['tp_delete'];
   if ( $rid < 0 ) $rid = 0;

   if ( @$_REQUEST['tp_cancel'] ) // cancel delete
      jump_to("tournaments/register.php?tid=$tid".URI_AMP."rid=$rid");

   $tourney = TournamentCache::load_cache_tournament( 'Tournament.register.find_tournament', $tid );
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   $tprops = TournamentCache::load_cache_tournament_properties( 'Tournament.register', $tid );

   $status_errors = $tstatus->check_edit_status( $ttype->allow_register_tourney_status, null, null, false );

   // existing application ? (check matching tid & uid if loaded by rid)
   if ( $rid > 0 )
      $tp = TournamentParticipant::load_tournament_participant_by_id( $rid, $tid, $my_id );
   else
      $tp = TournamentCache::load_cache_tournament_participant( 'Tournament.register', $tid, $my_id );
   if ( is_null($tp) )
      $tp = new TournamentParticipant( 0, $tid, $my_id, User::new_from_row($player_row) ); // new TP
   $rid = $tp->ID; // 0=new-registration, >0 = edit-registration

   // authorize actions
   $authorise_delete = $tp->authorise_delete( $tourney->Status );
   $authorise_edit_custom = $tp->authorise_edit_customized( $tourney->Status );

   // check exceptions for edit-allowed on PLAY-status for TP with higher start-round than current round
   $warnings = array();
   $allow_blocked_edit = false;
   if ( $ttype->need_rounds && $tourney->Status == TOURNEY_STATUS_PLAY && count($status_errors) > 0
         && $tp->StartRound > $tourney->CurrentRound )
   {
      if ( $rid && $is_delete ) // allow removal of existing-TP only on higher start-round (=withdrawal)
         $allow_blocked_edit = true;
      elseif ( $rid && @$_REQUEST['tp_ack_invite'] ) // accept invite
         $allow_blocked_edit = true;

      $warnings[] = make_html_safe( T_("Edit is normally forbidden except for accepting invitation or removing registration\n" .
         "on higher start round than current round.#tourney"), true);
   }
   $errors = ( $allow_blocked_edit ) ? array() : $status_errors;

   if ( $rid && $is_delete && $authorise_delete && @$_REQUEST['confirm'] && count($errors) == 0 ) // confirm delete TP-reg
   {
      ta_begin();
      {//HOT-section to delete TP
         TournamentParticipant::delete_tournament_participant( $tid, $rid );
         Bulletin::update_count_bulletin_new( "Tournament.register.del_tp($tid)", $my_id );
         TournamentLogHelper::log_tp_registration_by_user( TLOG_ACT_REMOVE, $tid, $tp->build_log_string() );
      }
      ta_end();

      jump_to("tournaments/register.php?tid=$tid".URI_AMP."sysmsg="
            . urlencode( sprintf( T_('Registration for user [%s] removed!#tourney'), @$player_row['Handle'] )) );
   }


   // init
   $old_status = $tp->Status;
   $old_rating = $tp->Rating;
   $old_start_round = $tp->StartRound;
   $old_tp = clone $tp;

   // check + parse edit-form
   $reg_check_type = ( $rid ) ? TCHKTYPE_USER_EDIT : TCHKTYPE_USER_NEW;
   list( $vars, $edits, $input_errors ) = parse_edit_form( $tp, $tourney, $ttype, $tprops );
   list( $reg_errors, $reg_warnings ) =
      $tprops->checkUserRegistration( $tourney, $tp, $my_id, $reg_check_type, TCHKFLAG_OLD_GAMES );

   // check own max-games
   $maxGamesCheck = new MaxGamesCheck();
   if ( !$rid && !$maxGamesCheck->allow_tournament_registration() )
      $errors[] = ErrorCode::get_error_text('max_games_tourney_reg');

   $need_apply_status = false;
   if ( $tp->StartRound > 1 && $tp->StartRound != $old_start_round )
      $need_apply_status = true;
   elseif ( $tp->hasRating() && (int)$old_rating != (int)$tp->Rating && (int)$tp->User->Rating != (int)$tp->Rating )
      $need_apply_status = true;

   list( $lock_errors, $lock_warnings ) = $tourney->checkRegistrationLocks( $reg_check_type );
   if ( count($lock_errors) )
      $errors = array_merge( $lock_errors, $errors );

   if ( !$rid ) // new-TP
   {
      if ( count($reg_errors) )
         $errors[] = T_('Restrictions do not allow you to register.#tourney');

      $tp->Status = $tp->calc_init_status($tprops->RatingUseMode);
   }
   $errors = array_merge( $errors, $input_errors );
   $warnings = array_merge( $lock_warnings, $warnings );
   $is_invite = ( $tp->Status == TP_STATUS_INVITE );
   $allow_register = ( count($reg_errors) + count($lock_errors) == 0 );


   // ---------- Process inputs into actions ------------------------------------

   if ( $is_invite && count($errors) == 0 )
   {
      // handle invitation: ACK (approval)
      if ( @$_REQUEST['tp_ack_invite'] ) // accepted invite
      {
         ta_begin();
         {//HOT-section to accept tourney-invitation
            $tp->Status = TP_STATUS_REGISTER;
            $tp->Flags |= TP_FLAG_ACK_INVITE;
            $ttype->joinTournament( $tourney, $tp, TLOG_TYPE_USER ); // update
            TournamentParticipant::sync_tournament_registeredTP( $tid, $old_status, $tp->Status );
            TournamentLogHelper::log_change_tp_registration_by_user( $tid, 'ack_invite', $edits, $old_tp, $tp );
         }
         ta_end();

         jump_to("tournaments/register.php?tid=$tid".URI_AMP."rid={$tp->ID}".URI_AMP."sysmsg="
               . urlencode(T_('Invitation accepted!')) );
      }

      // handle invitation: NACK (rejection) -> transform into APPLY
      if ( @$_REQUEST['tp_edit_invite'] ) // declined invite with edit
      {
         ta_begin();
         {//HOT-section to switch tourney-invitation to application
            $tp->Status = TP_STATUS_APPLY;
            $tp->Flags &= ~TP_FLAG_ACK_APPLY;
            $tp->persist(); // update
            TournamentParticipant::sync_tournament_registeredTP( $tid, $old_status, $tp->Status );
            TournamentLogHelper::log_change_tp_registration_by_user( $tid, 'edit_invite', $edits, $old_tp, $tp );
         }
         ta_end();

         jump_to("tournaments/register.php?tid=$tid".URI_AMP."rid={$tp->ID}".URI_AMP."sysmsg="
               . urlencode(T_('Invitation declined and transformed into application!')) );
      }
   }

   if ( $tp->Status == TP_STATUS_REGISTER )
   {
      // NOTE: an APPLY should not be reverted to REGISTER-status by user
      //       (there might be other reasons for the apply than just round and rating)
      if ( $need_apply_status )
         $tp->Status = TP_STATUS_APPLY;
   }

   if ( $old_status != $tp->Status )
      $edits[] = T_('Status');

   $tp->authorise_edit_register_status($tourney->Status, $old_status, $errors); // check status-change

   // persist TP-reg/edit in database
   if ( @$_REQUEST['tp_save'] && !@$_REQUEST['tp_preview'] && $allow_register && count($errors) == 0 )
   {
      if ( $tp->Status == TP_STATUS_REGISTER && $tprops->need_rating_copy() && !$tp->hasRating() )
      {
         if ( !$vars['_has_custom_rating'] ) // copy only if no customized-rating
            $tp->Rating = $tp->User->Rating;
      }
      $tp->NextRound = $tp->StartRound; //copy on REGISTER

      ta_begin();
      {//HOT-section to update tourney-registration
         $ttype->joinTournament( $tourney, $tp, TLOG_TYPE_USER ); // insert or update (and join eventually)
         TournamentParticipant::sync_tournament_registeredTP( $tid, $old_status, $tp->Status );

         if ( $rid == 0 ) // new TP
         {
            Bulletin::update_count_bulletin_new( "Tournament.register.add_tp($tid)", $tp->uid );
            TournamentLogHelper::log_tp_registration_by_user( TLOG_ACT_CREATE, $tid, $tp->build_log_string() );
         }
         else
         {
            $tlog_chtype = ( $tp->Status == TP_STATUS_APPLY ) ? 'apply' : 'register';
            TournamentLogHelper::log_change_tp_registration_by_user( $tid, $tlog_chtype, $edits, $old_tp, $tp );
         }
      }
      ta_end();

      jump_to("tournaments/register.php?tid=$tid".URI_AMP."rid={$tp->ID}".URI_AMP."sysmsg="
            . urlencode(T_('Tournament Registration saved!')) );
   }

   if ( $tp->Status == TP_STATUS_REGISTER && count($edits) == 0 && !$is_delete )
      $reg_warnings = array();


   // ---------- Tournament-Registration EDIT form ------------------------------

   $tpform = new Form( 'tournamentparticipant', $page, FORM_POST );
   $tpform->add_hidden( 'tid', $tid );
   $tpform->add_hidden( 'rid', $rid );

   // edit registration
   $tpform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   $tpform->add_row( array(
         'DESCRIPTION', T_('Tournament Status'),
         'TEXT',        Tournament::getStatusText($tourney->Status) ));
   $tpform->add_row( array(
         'DESCRIPTION', T_('Tournament Round'),
         'TEXT',        $tourney->formatRound(), ));
   $tpform->add_row( array(
         'DESCRIPTION', T_('User'),
         'TEXT',        user_reference( REF_LINK, 1, '', $player_row), ));
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
   $tpform->add_row( array(
         'DESCRIPTION', T_('Current Registration Status#tourney'),
         'TEXT', ( $old_status ? TournamentParticipant::getStatusText($old_status) : NO_VALUE )
                  . SEP_SPACING
                  . span('TUserStatus', TournamentParticipant::getStatusUserInfo($old_status)), ));
   if ( $old_status != $tp->Status )
      $tpform->add_row( array(
            'DESCRIPTION', T_('New Registration Status#tourney'),
            'TEXT',        span('TWarning', TournamentParticipant::getStatusText($tp->Status)), ));

   if ( count($errors) || count($warnings) )
      $tpform->add_row( array( 'HR' ));
   if ( count($errors) )
   {
      $tpform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
   }
   if ( count($warnings) )
   {
      $tpform->add_row( array(
            'DESCRIPTION', T_('Warning'),
            'TEXT', buildWarnListString(T_('There are some warnings'), $warnings) ));
   }

   if ( count($reg_errors) || count($reg_warnings) )
   {
      // NOTE: if tourney-restrictions forbid registration -> TP can ask TD for invitation
      $tpform->add_row( array( 'HR' ));
      $tpform->add_row( array( 'HEADER', T_('Registration restrictions#tourney') ));
      if ( count($reg_errors) )
         $tpform->add_row( array(
               'OWNHTML', buildErrorListString(
                          T_('[Errors]: You are not allowed to register for this tournament'), $reg_errors, 2) ));
      if ( count($reg_warnings) )
         $tpform->add_row( array(
               'OWNHTML', buildErrorListString(
                          T_('[Warnings]: You are normally not allowed to register anew for this tournament'), $reg_warnings, 2) ));
   }

   // EDIT: Ratings --------------------

   $tpform->add_row( array( 'HR' ));
   $tpform->add_row( array(
         'DESCRIPTION', T_('Rating Use Mode#tourney'),
         'TEXT',        span('smaller', TournamentProperties::getRatingUseModeText($tprops->RatingUseMode, false)), ));

   $current_rating_str = ($tp->User->hasRating()) ? echo_rating($tp->User->Rating, true, $my_id) : NO_VALUE;
   $tpform->add_row( array(
         'DESCRIPTION', T_('Current User Rating'),
         'TEXT',        $current_rating_str, ));

   if ( $allow_register && $authorise_edit_custom && !$is_invite && !$is_delete && $tprops->allow_rating_edit() )
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
      $tpform->add_row( array(
            'DESCRIPTION', T_('Tournament Rating'),
            'TEXT',        echo_rating($tp->Rating, true), ));
   $tpform->add_empty_row();

   // EDIT: Rounds ---------------------

   if ( $ttype->need_rounds )
   {
      $tpform->add_row( array(
            'DESCRIPTION', T_('Current Start Round#tourney'),
            'TEXT',        $old_start_round, ));

      if ( $tprops->MaxStartRound > 1 && $allow_register && $authorise_edit_custom && !$is_invite && !$is_delete
           && $tprops->MinRatingStartRound != NO_RATING && $tp->User->Rating >= $tprops->MinRatingStartRound )
      {
         $tpform->add_row( array(
               'DESCRIPTION', T_('Customized Start Round#tourney'),
               'TEXTINPUT',   'start_round', 3, 3, $vars['start_round'],
               'TEXT',        ' ' . sprintf( T_('Range %s#tourney'), build_range_text(1, $tprops->MaxStartRound)), ));
      }
      if ( !$is_delete )
         $tpform->add_empty_row();
   }

   // EDIT: Texts ----------------------

   if ( $allow_register && !$is_delete )
   {
      $tpform->add_row( array(
            'DESCRIPTION', T_('Public User Comment#tourney'),
            'TEXTINPUT',   'comment', 60, 60, $tp->Comment,
            'TEXT', T_('shown in tournament-participants list'), ));
      if ( (string)$tp->DirectorMessage != '' )
         $tpform->add_row( array(
            'DESCRIPTION', T_('Tournament Director Message'),
            'TEXT', span('TUserStatus', make_html_safe($tp->DirectorMessage, true)), ));

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
   elseif ( $allow_register )
   {
      $rowarr = array( 'TAB', 'CELL', 1, '', ); // align submit-buttons
      if ( $is_invite )
      {
         array_push( $rowarr,
            'SUBMITBUTTON', 'tp_ack_invite', T_('Accept invitation#tourney'),
            'SUBMITBUTTON', 'tp_edit_invite', T_('Edit invitation#tourney') );
      }
      else
      {
         array_push( $rowarr,
            'SUBMITBUTTON', 'tp_save', ($rid) ? T_('Update registration#tourney') : T_('Register#tourney'),
            'SUBMITBUTTON', 'tp_preview', T_('Preview') );
      }
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


   $title = sprintf( T_('Tournament Registration for [%s]'), $tourney->Title );
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";
   echo $maxGamesCheck->get_warn_text();

   $tpform->echo_string();

   echo_notes( 'registernotesTable', T_('Registration notes#tourney'), build_participant_notes() );


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Tournament participants')] = "tournaments/list_participants.php?tid=$tid";
   $menu_array[T_('Tournament directors')] = "tournaments/list_directors.php?tid=$tid";

   $reg_user_str = TournamentGuiHelper::getLinkTextRegistration($tid, $old_status);
   $menu_array[$reg_user_str] = "tournaments/register.php?tid=$tid";

   if ( $tourney->Type == TOURNEY_TYPE_LADDER && $tourney->Status != TOURNEY_STATUS_PAIR && $rid > 0 )
      $menu_array[T_('Withdraw from Ladder')] = "tournaments/ladder/withdraw.php?tid=$tid";

   end_page(@$menu_array);
}//main


// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$tp, $tourney, $ttype, $tprops )
{
   $edits = array();
   $errors = array();
   $is_posted = ( @$_REQUEST['tp_save'] || @$_REQUEST['tp_preview'] );

   // read from props or set defaults
   $vars = array(
      'custom_rating'      => '', // no default
      '_has_custom_rating' => false,
      'start_round'        => $tp->StartRound,
      'comment'            => $tp->Comment,
   );
   $old_rating = $tp->Rating;

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach ( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // parse URL-vars
   if ( $is_posted && ($tp->Status != TP_STATUS_INVITE) )
   {
      $old_vals['custom_rating'] = NO_RATING;

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

      if ( $ttype->need_rounds && $tprops->MaxStartRound > 1
            && $tprops->MinRatingStartRound != NO_RATING && $tp->User->Rating >= $tprops->MinRatingStartRound )
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

   if ( $is_posted || @$_REQUEST['tp_ack_invite'] || @$_REQUEST['tp_edit_invite'] )
   {
      $tp->Comment = trim(get_request_arg('comment'));

      // determine edits
      if ( $old_vals['comment'] != $tp->Comment ) $edits[] = T_('Comment');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form

/*! \brief Returns array with notes about registering users. */
function build_participant_notes( $deny_reason=null, $intro=true )
{
   $notes = array();
   if ( !is_null($deny_reason) )
   {
      $notes[] = sprintf( '<color darkred><b>%s:</b></color> %s', T_('Registration restricted#tourney'), $deny_reason );
      $notes[] = null; // empty line
   }

   if ( $intro )
   {
      $notes[] = T_('Questions and support requests regarding this tournament can be directed to the tournament directors.');
      $notes[] = null; // empty line

      $notes[] = T_('You will need a customized rating if you don\'t have a DGS-rating yet or if you don\'t want to start with your current DGS-rating.#tourney');
      $notes[] = T_('Customized rating and round can only be modified freely while in registration phase.#tourney');
      $notes[] = T_('If you enter a customized rating or a non-default starting-round, your application needs to be verified by a tournament director.#tourney');
      $notes[] = null; // empty line
   }

   $narr = array( T_('Registration Status#tourney') . ':' );
   $narr[] = sprintf( '%s = %s', NO_VALUE, T_('user is not registered for tournament#tp_status_unreg') );
   $arrst = array();
   $arrst[TP_STATUS_APPLY] = T_('user-application needs verification by tournament director#tp_status');
   $arrst[TP_STATUS_INVITE] = T_('user has been invited by tournament director and needs verification by user#tp_status');
   $arrst[TP_STATUS_REGISTER] = T_('user has been successfully registered#tp_status');
   foreach ( $arrst as $status => $descr )
      $narr[] = sprintf( "%s = $descr", TournamentParticipant::getStatusText($status) );
   $notes[] = $narr;

   return $notes;
}//build_participant_notes
?>
