<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once( 'include/std_functions.php' );
require_once( 'include/gui_functions.php' );
require_once( 'include/form_functions.php' );
require_once( 'include/rating.php' );
require_once( 'tournaments/include/tournament.php' );
require_once( 'tournaments/include/tournament_participant.php' );
require_once( 'tournaments/include/tournament_properties.php' );
require_once( 'tournaments/include/tournament_status.php' );
require_once( 'tournaments/include/tournament_factory.php' );

$GLOBALS['ThePage'] = new Page('TournamentRegistration');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.register');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $page = "register.php";

/* Actual REQUEST calls used (TD=tournament-director)
     tid=                           : current user registers for tournament
     tid=&rid=                      : current user edits existing registration
     tp_preview&tid=&rid=           : preview for T-registration-save
     tp_save&tid=&rid=              : update registration in database
     tp_delete&tid=&rid=            : remove registration (need confirm)
     tp_delete&confirm=1&tid=&rid=  : remove registration (confirmed)
     tp_cancel&tid=&rid=            : cancel remove-confirmation
     tp_ack_invite&tid=             : approve invitation by TD
     tp_edit_invite&tid=            : reject invitation by TD, transforming to user-application
*/

   $tid = (int) @$_REQUEST['tid'];
   $rid = (int) @$_REQUEST['rid'];
   $is_delete = (bool) @$_REQUEST['tp_delete'];
   if( $rid < 0 ) $rid = 0;

   if( @$_REQUEST['tp_cancel'] ) // cancel delete
      jump_to("tournaments/register.php?tid=$tid".URI_AMP."rid=$rid");

   $tourney = Tournament::load_tournament($tid); // existing tournament ?
   if( is_null($tourney) || $tid <= 0 )
      error('unknown_tournament', "Tournament.register.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);

   $tprops = TournamentProperties::load_tournament_properties($tid);
   if( is_null($tprops) )
      error('bad_tournament', "Tournament.register.miss_properties($tid,$my_id)");

   // existing application ? (check matching tid & uid if loaded by rid)
   $tp = TournamentParticipant::load_tournament_participant( $tid, $my_id, $rid, true, true );
   if( is_null($tp) )
      $tp = new TournamentParticipant( 0, $tid, $my_id, User::new_from_row($player_row) ); // new TP
   $rid = $tp->ID; // 0=new-registration, >0 = edit-registration

   if( $rid && $is_delete && @$_REQUEST['confirm'] ) // confirm delete TP-reg
   {
      TournamentParticipant::delete_tournament_participant( $tid, $rid );
      jump_to("tournaments/register.php?tid=$tid".URI_AMP."sysmsg="
            . urlencode( sprintf( T_('Registration for user [%s] removed!'), @$player_row['Handle'] )) );
   }


   // init
   $old_status = $tp->Status;
   $old_rating = $tp->Rating;
   $old_start_round = $tp->StartRound;

   // check + parse edit-form
   list( $vars, $edits, $input_errors ) = parse_edit_form( $tp, $tourney, $ttype, $tprops );
   $reg_errors = $tprops->checkUserRegistration( $tourney, $tp->hasRating(), $my_id );

   $errors = array();
   if( !$rid ) // new-TP
   {
      if( count($reg_errors) )
         $errors[] = T_('Registration restrictions disallow you to register.');

      $tp->Status = $tp->calc_init_status($tprops->RatingUseMode);
   }
   $errors = array_merge( $errors, $input_errors );
   $is_invite = ( $tp->Status == TP_STATUS_INVITE );
   $allow_register = ( count($reg_errors) == 0 );

   // ---------- Process inputs into actions ------------------------------------

   if( $is_invite )
   {
      // handle invitation: ACK (approval)
      if( @$_REQUEST['tp_ack_invite'] ) // accepted invite
      {
         $tp->Status = TP_STATUS_REGISTER;
         $tp->Flags |= TP_FLAGS_ACK_INVITE;
         $tp->persist(); // update
         jump_to("tournaments/register.php?tid=$tid".URI_AMP."rid={$tp->ID}".URI_AMP."sysmsg="
               . urlencode(T_('Invitation accepted!')) );
      }

      // handle invitation: NACK (rejection) -> transform into APPLY
      if( @$_REQUEST['tp_edit_invite'] ) // declined invite with edit
      {
         $tp->Status = TP_STATUS_APPLY;
         $tp->Flags &= ~TP_FLAGS_ACK_APPLY;
         $tp->persist(); // update
         jump_to("tournaments/register.php?tid=$tid".URI_AMP."rid={$tp->ID}".URI_AMP."sysmsg="
               . urlencode(T_('Invitation declined and transformed into application!')) );
      }
   }

   if( $tp->Status == TP_STATUS_REGISTER )
   {
      // NOTE: an APPLY should not be reverted to REGISTER-status by user
      //       (there might be other reasons for the apply than just round and rating)
      if( $tp->StartRound > 1 && $tp->StartRound != $old_start_round )
         $tp->Status = TP_STATUS_APPLY;
      if( $tp->hasRating() && (int)$old_rating != (int)$tp->Rating && (int)$tp->User->Rating != (int)$tp->Rating )
         $tp->Status = TP_STATUS_APPLY;
   }

   if( $old_status != $tp->Status )
      $edits[] = T_('Status#edits');

   // persist TP-reg/edit in database
   if( @$_REQUEST['tp_save'] && !@$_REQUEST['tp_preview'] && $allow_register && count($errors) == 0 )
   {
      if( $tp->Status == TP_STATUS_REGISTER && $tprops->need_rating_copy() && !$tp->hasRating() )
      {
         if( !$vars['_has_custom_rating'] ) // copy only if no customized-rating
            $tp->Rating = $tp->User->Rating;
      }

      $tp->persist(); // insert or update
      jump_to("tournaments/register.php?tid=$tid".URI_AMP."rid={$tp->ID}".URI_AMP."sysmsg="
            . urlencode(T_('Registration saved!')) );
   }


   // ---------- Tournament-Registration EDIT form ------------------------------

   $tpform = new Form( 'tournamentparticipant', $page, FORM_POST );
   $tpform->add_hidden( 'tid', $tid );
   $tpform->add_hidden( 'rid', $rid );

   // edit registration
   $tpform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   $tpform->add_row( array(
         'DESCRIPTION', T_('User'),
         'TEXT',        user_reference( REF_LINK, 1, '', $player_row), ));
   if( $tp->Created > 0 )
      $tpform->add_row( array(
            'DESCRIPTION', T_('Created'),
            'TEXT',        date(DATE_FMT2, $tp->Created), ));
   if( $tp->Lastchanged > 0 )
      $tpform->add_row( array(
            'DESCRIPTION', T_('Last changed'),
            'TEXT',        TournamentUtils::buildLastchangedBy($tp->Lastchanged, $tp->ChangedBy) ));
   $tpform->add_row( array(
         'DESCRIPTION', T_('Current Registration Status'),
         'TEXT', ( $old_status ? TournamentParticipant::getStatusText($old_status) : NO_VALUE )
                  . SEP_SPACING
                  . span('TUserStatus', TournamentParticipant::getStatusUserInfo($old_status)), ));
   if( $old_status != $tp->Status )
      $tpform->add_row( array(
            'DESCRIPTION', T_('New Registration Status'),
            'TEXT',        span('TWarning', TournamentParticipant::getStatusText($tp->Status)), ));

   if( count($errors) )
   {
      $tpform->add_row( array( 'HR' ));
      $tpform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString(T_('There are some errors'), $errors) ));
   }
   if( count($reg_errors) )
   {
      // NOTE: if tourney-restrictions forbid registration -> TP can ask TD for invitation
      $tpform->add_row( array( 'HR' ));
      $tpform->add_row( array( 'HEADER', T_('Registration restrictions') ));
      $tpform->add_row( array(
            'OWNHTML', TournamentUtils::buildErrorListString(
                           T_('You are not allowed to register for this tournament'), $reg_errors, 2) ));
   }

   // EDIT: Ratings --------------------

   $tpform->add_row( array( 'HR' ));
   $tpform->add_row( array(
         'DESCRIPTION', T_('Rating Use Mode'),
         'TEXT',        span('TInfo', TournamentProperties::getRatingUseModeText($tprops->RatingUseMode, false)), ));

   $current_rating_str = ($tp->User->hasRating()) ? echo_rating($tp->User->Rating, true, $my_id) : NO_VALUE;
   $tpform->add_row( array(
         'DESCRIPTION', T_('Current User Rating'),
         'TEXT',        $current_rating_str, ));

   if( $allow_register && !$is_invite && !$is_delete && $tprops->allow_rating_edit() )
   {
      $custom_rating_str = ( $tp->hasRating() && $vars['_has_custom_rating'] )
         ? echo_rating($tp->Rating, true) : NO_VALUE;
      $rating_type = get_request_arg('rating_type', 'dragonrank');

      $tpform->add_row( array(
            'DESCRIPTION', T_('Customized Rating'),
            'TEXT',        $custom_rating_str . SMALL_SPACING,
            'TEXTINPUT',   'custom_rating', 10, 10, $vars['custom_rating'], '',
            'SELECTBOX',   'rating_type', 1, getRatingTypes(), $rating_type, false,
            'SUBMITBUTTON', 'tp_preview', T_('Convert Rating'), ));
   }
   if( !$is_delete && $tp->hasRating() )
      $tpform->add_row( array(
            'DESCRIPTION', T_('Tournament Rating'),
            'TEXT',        echo_rating($tp->Rating, true), ));
   $tpform->add_empty_row();

   // EDIT: Rounds ---------------------

   if( $ttype->need_rounds && !$is_delete )
   {
      $tpform->add_row( array(
            'DESCRIPTION', T_('Current Start Round'),
            'TEXT',        $old_start_round, ));
      if( $tourney->Rounds > 1 && $allow_register && !$is_invite && !$is_delete )
      {
         $tpform->add_row( array(
               'DESCRIPTION', T_('Customized Start Round'),
               'TEXTINPUT',   'start_round', 3, 3, $vars['start_round'], '',
               'TEXT',        MINI_SPACING . $tourney->getRoundLimitText(), ));
      }
      $tpform->add_empty_row();
   }

   // EDIT: Texts ----------------------

   if( $allow_register && !$is_delete )
   {
      $tpform->add_row( array(
            'DESCRIPTION', T_('Public User Comment'),
            'TEXTINPUT',   'comment', 60, 60, $tp->Comment, '', ));
      $tpform->add_row( array(
            'DESCRIPTION', T_('User Message'),
            'TEXTAREA',    'user_message', 70, 5, $tp->UserMessage, ));
      if( @$_REQUEST['tp_preview'] )
         $tpform->add_row( array(
               'DESCRIPTION', T_('Preview'),
               'TEXT', make_html_safe( $tp->UserMessage, true ) ));
      $tpform->add_row( array(
            'DESCRIPTION', T_('Admin Message'),
            'TEXT', make_html_safe($tp->AdminMessage, true), ));

      $tpform->add_row( array(
            'DESCRIPTION', T_('Unsaved edits'),
            'TEXT',        span('TWarning', implode(', ', $edits ), '[%s]'), ));
   }

   // EDIT: Submit-Buttons -------------

   if( $is_delete )
   {
      $tpform->add_hidden( 'confirm', 1 );
      $tpform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'tp_delete', T_('Confirm removal of registration'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tp_cancel', T_('Cancel') ));
   }
   elseif( $allow_register )
   {
      $rowarr = array( 'TAB', 'CELL', 1, '', ); // align submit-buttons
      if( $is_invite )
      {
         array_push( $rowarr,
            'SUBMITBUTTON', 'tp_ack_invite', T_('Accept invitation#tourney'),
            'SUBMITBUTTON', 'tp_edit_invite', T_('Edit invitation#tourney') );
      }
      else
      {
         array_push( $rowarr,
            'SUBMITBUTTON', 'tp_save', ($rid) ? T_('Update registration') : T_('Register'),
            'SUBMITBUTTON', 'tp_preview', T_('Preview') );
      }
      if( $rid )
         array_push( $rowarr,
               'TEXT', SMALL_SPACING,
               'SUBMITBUTTON', 'tp_delete', T_('Remove registration') );
      if( @$_REQUEST['tp_save'] || @$_REQUEST['tp_preview'] )
         array_push( $rowarr,
               'TEXT', SMALL_SPACING,
               'SUBMITBUTTON', 'tp_cancel', T_('Cancel') );
      $tpform->add_row( $rowarr );
   }


   $title = sprintf( T_('Tournament Registration for [%s]'), $tourney->Title );
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tpform->echo_string();

   echo_notes( 'registernotesTable', T_('Registration notes'), build_participant_notes() );


   $menu_array = array();
   $menu_array[T_('Tournament participants')] = "tournaments/list_participants.php?tid=$tid";
   $menu_array[T_('Tournament directors')] = "tournaments/list_directors.php?tid=$tid";
   $menu_array[T_('View this tournament')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('View this tournament')] = "tournaments/view_tournament.php?tid=$tid";

   $reg_user_str = TournamentParticipant::getLinkTextRegistration( $tourney, $my_id, $old_status );
   $menu_array[$reg_user_str] = "tournaments/register.php?tid=$tid";

   end_page(@$menu_array);
}


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
      'user_message'       => $tp->UserMessage,
   );
   $old_rating = $tp->Rating;

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // parse URL-vars
   if( $is_posted && ($tp->Status != TP_STATUS_INVITE) )
   {
      $old_vals['custom_rating'] = -OUT_OF_RATING;

      $new_value = trim($vars['custom_rating']); // optional
      if( (string)$new_value != '' )
      {
         $rating_type = get_request_arg('rating_type', 'dragonrank');
         $custom_rating = convert_to_rating( $new_value, $rating_type, true );
         if( !is_valid_rating($custom_rating) )
            $errors[] = ErrorCode::get_error_text('invalid_rating');
         else
         {
            $tp->Rating = $custom_rating;
            $vars['_has_custom_rating'] = true;
         }
      }

      if( $ttype->need_rounds && $tourney->Rounds > 1 )
      {
         $new_value = trim($vars['start_round']); // optional
         if( (string)$new_value != '' )
         {
            if( !is_numeric($new_value) || $new_value < 1 )
               $errors[] = T_('Expecting positive number for start round');
            elseif( $tourney->Rounds > 0 && $new_value > $tourney->Rounds )
               $errors[] = sprintf( T_('Start round is out of range of actual rounds [1..%s] for this tournament.'), $tourney->Rounds );
            else
               $tp->StartRound = $new_value;
         }
      }

      // determine edits
      if( $old_rating != $tp->Rating ) $edits[] = T_('Rating#edits');
      if( $old_vals['start_round'] != $tp->StartRound ) $edits[] = T_('StartRound#edits');
   }

   if( $is_posted || @$_REQUEST['tp_ack_invite'] || @$_REQUEST['tp_edit_invite'] )
   {
      $tp->Comment = trim(get_request_arg('comment'));
      $tp->UserMessage = trim(get_request_arg('user_message'));

      // determine edits
      if( $old_vals['comment'] != $tp->Comment ) $edits[] = T_('Comment#edits');
      if( $old_vals['user_message'] != $tp->UserMessage ) $edits[] = T_('UserMessage#edits');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form

/*! \brief Returns array with notes about registering users. */
function build_participant_notes( $deny_reason=null, $intro=true )
{
   $notes = array();
   if( !is_null($deny_reason) )
   {
      $notes[] = sprintf( '<color darkred><b>%s:</b></color> %s',
                          T_('Registration restricted'), $deny_reason );
      $notes[] = null; // empty line
   }

   if( $intro )
   {
      $notes[] = T_('Questions and support requests regarding this tournament can be directed to the tournament directors.');
      $notes[] = null; // empty line

      $notes[] = T_('You will need a customized rating if you don\'t have a DGS-rating yet or if you don\'t want to start with your current DGS-rating.');
      $notes[] = T_('If you enter a customized rating or a non-default starting-round, your application needs to be verified by a tournament director.');
      $notes[] = T_('To accelerate the registration process please add your reasoning for the changes in the user-message box.');
      $notes[] = null; // empty line
   }

   $narr = array( T_('Registration Status') );
   $narr[] = sprintf( '%s = %s', NO_VALUE, T_('user is not registered for tournament#tpstat_unreg') );
   $arrst = array();
   $arrst[TP_STATUS_APPLY] = T_('user-application needs verification by tournament director#tpstat_apply');
   $arrst[TP_STATUS_INVITE] = T_('user has been invited by tournament director and needs verification by user#tpstat_invite');
   $arrst[TP_STATUS_REGISTER] = T_('user has been successfully registered#tpstat_reg');
   foreach( $arrst as $status => $descr )
      $narr[] = sprintf( "%s = $descr", TournamentParticipant::getStatusText($status) );
   $notes[] = $narr;

   return $notes;
}//build_participant_notes
?>
