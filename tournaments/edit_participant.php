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
require_once( 'include/error_codes.php' );
require_once( 'tournaments/include/tournament.php' );
require_once( 'tournaments/include/tournament_participant.php' );
require_once( 'tournaments/include/tournament_properties.php' );
require_once( 'tournaments/include/tournament_status.php' );
require_once( 'tournaments/include/tournament_factory.php' );

$GLOBALS['ThePage'] = new Page('TournamentEditParticipant');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_participant');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

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

   if( @$_REQUEST['tp_cancel'] ) // cancel delete or edit
      jump_to("tournaments/edit_participant.php?tid=$tid".URI_AMP."uid=$uid");

   $tourney = Tournament::load_tournament( $tid ); // existing tournament ?
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.edit_participant.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);

   $allow_edit_tourney = $tourney->allow_edit_tournaments( $my_id );
   if( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.edit_participant($tid,$my_id)");

   $errors = $tstatus->check_edit_status( TournamentParticipant::get_edit_tournament_status() );

   // load-user, change-user?
   if( @$_REQUEST['tp_showuser_uid'] )
   {
      if( is_numeric($userhandle) )
         $user = User::load_user( $userhandle );
   }
   elseif( @$_REQUEST['tp_showuser_handle'] )
   {
      if( (string)$userhandle != '' )
         $user = User::load_user_by_handle( $userhandle );
   }
   else
      $user = User::load_user( $uid );

   if( is_null($user) )
   {
      $uid = 0;
      $errors[] = T_('Unknown user');
   }
   else
   {
      $uid = $user->ID;
      if( (string)$userhandle == '' )
         $userhandle = $user->Handle;
   }

   // check for guest-user
   if( $uid != 0 && $uid <= GUESTS_ID_MAX )
      jump_to("tournaments/edit_participant.php?tid=$tid".URI_AMP."sysmsg="
            . urlencode( T_('Guest users can not be edited, please choose another user!') ));

   // user eligible?
   $tprops = null;
   if( !is_null($user) )
   {
      $tprops = TournamentProperties::load_tournament_properties($tid);
      if( is_null($tprops) )
         error('bad_tournament', "Tournament.edit_participant.miss_properties($tid,$my_id)");
   }

   // existing participation ?
   $tp = TournamentParticipant::load_tournament_participant($tid, $uid);
   if( is_null($tp) )
   {
      if( !is_null($user) )
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

   // check + parse edit-form
   list( $vars, $edits, $input_errors ) = parse_edit_form( $tp, $tourney, $ttype );
   list( $reg_errors, $reg_warnings ) = ( !is_null($tprops) )
      ? $tprops->checkUserRegistration($tourney, $tp->hasRating(), $user, TPROP_CHKTYPE_TD)
      : array( array(), array() );

   if( !$rid ) // new-TP
   {
      if( count($reg_errors) )
         $errors[] = T_('[Errors]: Registration restrictions disallow user to be registered.');
      if( !$ignore_warnings && count($reg_warnings) )
         $errors[] = T_('[Warnings]: Registration restrictions normally disallow user to be registered.');

      $tp->Status = TP_STATUS_INVITE; // TD can only invite
   }
   $errors = array_merge( $errors, $input_errors );

   // ---------- Process inputs into actions ------------------------------------

   if( !is_null($user) ) // edit
   {
      if( $rid && $is_delete && $authorise_delete && @$_REQUEST['confirm'] && count($errors) == 0 ) // confirm delete TP-reg
      {
         TournamentParticipant::delete_tournament_participant( $tid, $rid );
         $sys_msg = send_register_notification( 'delete', $tp, $my_id );
         jump_to("tournaments/edit_participant.php?tid=$tid".URI_AMP."uid=$uid".URI_AMP."sysmsg=$sys_msg");
      }

      // check user-denial
      if( ($user->AdminOptions & ADMOPT_DENY_TOURNEY_REGISTER) && $tp->Status != TP_STATUS_REGISTER )
         $errors[] = T_('Tournament registration of this user has been denied by admins.');

      // check status integrity
      $is_customized = false;
      if( $tp->StartRound > 1 && $tp->StartRound != $old_start_round )
         $is_customized = true;
      if( $tp->hasRating() && (int)$old_rating != (int)$tp->Rating )
         $is_customized = true;
      elseif( !$tp->hasRating() && is_valid_rating($old_rating) )
         $is_customized = true;
      if( $is_customized )
         $tp->Status = TP_STATUS_INVITE;

      if( $tp->Status == TP_STATUS_INVITE )
         $tp->Flags = ($tp->Flags | TP_FLAGS_INVITED) & ~TP_FLAGS_ACK_INVITE;
      elseif( $old_status == TP_STATUS_APPLY && $tp->Status == TP_STATUS_REGISTER )
         $tp->Flags |= TP_FLAGS_ACK_APPLY;
      if( count($reg_errors) || count($reg_warnings) ) // violate registration restrictions
         $tp->Flags |= TP_FLAGS_VIOLATE;

      if( $old_status != $tp->Status )
         $edits[] = T_('Status#edits');

      $tp->authorise_edit_register_status($tourney->Status, $old_status, $errors); // check status-change

      // persist TP in database
      if( $tid && @$_REQUEST['tp_save'] && !@$_REQUEST['tp_preview'] && count($errors) == 0
            && count($reg_errors) == 0 && ( $rid || $ignore_warnings || count($reg_warnings) == 0 ) )
      {
         if( $tp->Status == TP_STATUS_REGISTER && $tprops->need_rating_copy() && !$tp->hasRating() )
         {
            if( !$vars['_has_custom_rating'] ) // copy only if no customized-rating
               $tp->Rating = $tp->User->Rating;
         }

         $ttype->joinTournament( $tourney, $tp ); // insert or update (and join eventually)

         // send notification (if needed)
         if( $old_status == TP_STATUS_APPLY && $tp->Status == TP_STATUS_REGISTER ) // APPLY-ACK
            $sys_msg = send_register_notification( 'ack_apply', $tp, $my_id );
         elseif( $old_status != $tp->Status && $tp->Status == TP_STATUS_INVITE ) // customized -> INVITE
         {
            $ntype = ( $rid ) ? $old_status.'_invite' : 'new_invite';
            $sys_msg = send_register_notification( $ntype, $tp, $my_id );
         }
         else
            $sys_msg = urlencode( T_('Registration saved!') );

         jump_to("tournaments/edit_participant.php?tid=$tid".URI_AMP."uid=$uid".URI_AMP."sysmsg=$sys_msg");
      }

      if( $tp->Status == TP_STATUS_REGISTER && count($edits) == 0 && !$is_delete )
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
   if( !is_null($user) )
      $tpform->add_row( array(
            'DESCRIPTION', T_('User'),
            'TEXT', $user->user_reference(), ));

   if( count($errors) )
   {
      $tpform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString( T_('There are some errors'), $errors ) ));
      $tpform->add_empty_row();
   }
   if( count($reg_errors) || count($reg_warnings) )
   {
      $tpform->add_row( array( 'HR' ));
      $tpform->add_row( array( 'HEADER', T_('Registration restrictions') ));
      if( count($reg_errors) )
         $tpform->add_row( array(
               'OWNHTML', TournamentUtils::buildErrorListString(
                          T_('[Errors]: User is not allowed to register for this tournament'), $reg_errors, 2) ));
      if( count($reg_warnings) )
      {
         $tpform->add_row( array(
               'OWNHTML', TournamentUtils::buildErrorListString(
                          T_('[Warnings]: User is normally not allowed to register for this tournament'), $reg_warnings, 2) ));
         if( !$rid && count($reg_errors) == 0 ) // no ignore on error, else ignore only for NEW-reg
            $tpform->add_row( array(
                  'CHECKBOX', 'no_warn', '1', T_('Ignore warnings'), (get_request_arg('no_warn') ? 1 : '') ));
      }
      $tpform->add_row( array( 'HR' ));
   }

   if( !is_null($user) ) // edit
   {
      if( $tp->Created > 0 )
         $tpform->add_row( array(
               'DESCRIPTION', T_('Created'),
               'TEXT',        date(DATE_FMT2, $tp->Created), ));
      if( $tp->Lastchanged > 0 )
         $tpform->add_row( array(
               'DESCRIPTION', T_('Last changed'),
               'TEXT',        TournamentUtils::buildLastchangedBy($tp->Lastchanged, $tp->ChangedBy) ));

      // EDIT: Status ---------------------

      $old_status_str = ($old_status) ? TournamentParticipant::getStatusText($old_status) : NO_VALUE;

      $tpform->add_row( array(
            'DESCRIPTION', T_('Current Registration Status'),
            'TEXT',        $old_status_str, ));
      if( $authorise_edit_custom )
      {
         $tpform->add_row( array(
               'DESCRIPTION', T_('New Registration Status'),
               'TEXT',        span('TWarning', TournamentParticipant::getStatusText($tp->Status)) .
                              SMALL_SPACING,
               'SELECTBOX',   'status', 1, build_new_status_choices($tp), $vars['status'], false, ));
      }

      $tpform->add_row( array(
            'DESCRIPTION', T_('Current Flags'),
            'TEXT',        TournamentParticipant::getFlagsText($old_flags), ));
      if( $old_flags != $tp->Flags )
         $tpform->add_row( array(
               'DESCRIPTION', T_('New Flags'),
               'TEXT',        TournamentParticipant::getFlagsText($tp->Flags), ));
      $tpform->add_empty_row();

      // EDIT: Ratings --------------------

      $tpform->add_row( array(
            'DESCRIPTION', T_('Rating Use Mode'),
            'TEXT',  TournamentProperties::getRatingUseModeText($tprops->RatingUseMode)
                     . "<br>\n"
                     . span('TInfo', TournamentProperties::getRatingUseModeText($tprops->RatingUseMode, false), '(%s)'), ));
      $tpform->add_row( array(
            'DESCRIPTION', T_('Current User Rating'),
            'TEXT',        build_rating_str($tp->User->Rating, $tp->uid), ));
      if( !$is_delete && $authorise_edit_custom && $tprops->allow_rating_edit() )
      {
         $custom_rating_str = ( $tp->hasRating() && $vars['_has_custom_rating'] )
            ? echo_rating($tp->Rating, true) : NO_VALUE;
         $rating_type = get_request_arg('rating_type', 'dragonrank');

         $tpform->add_row( array(
               'DESCRIPTION', T_('Customized Rating'),
               'TEXT',        $custom_rating_str . SMALL_SPACING,
               'TEXTINPUT',   'custom_rating', 10, 10, $vars['custom_rating'],
               'SELECTBOX',   'rating_type', 1, getRatingTypes(), $rating_type, false,
               'SUBMITBUTTON', 'tp_preview', T_('Convert Rating'), ));
      }

      if( !$is_delete && $tp->hasRating() )
      {
         $arr = array(
               'DESCRIPTION', T_('Tournament Rating'),
               'TEXT',        build_rating_str($tp->Rating, $tp->uid), );
         if( !$is_delete && $authorise_edit_custom && $tp->User->hasRating() ) // T-rating not needed if user has rating
            array_push( $arr,
               'TEXT', SMALL_SPACING,
               'CHECKBOX', 'del_rating', '1', T_('Remove customized rating'),
                           (get_request_arg('del_rating') ? 1 : '') );
         $tpform->add_row( $arr );
      }
      $tpform->add_empty_row();

      // EDIT: Rounds ---------------------

      if( $ttype->need_rounds && !$is_delete )
      {
         $tpform->add_row( array(
               'DESCRIPTION', T_('Current Start Round'),
               'TEXT',        $old_start_round, ));
         if( $tourney->Rounds > 1 && !$is_delete && $authorise_edit_custom )
            $tpform->add_row( array(
                  'DESCRIPTION', T_('Customized Start Round'),
                  'TEXTINPUT',   'start_round', 3, 3, get_request_arg('start_round'),
                  'TEXT',        MINI_SPACING . $tourney->getRoundLimitText(), ));
         $tpform->add_empty_row();
      }

      // EDIT: Texts ----------------------

      if( !$is_delete )
      {
         $tpform->add_row( array(
               'DESCRIPTION', T_('Public User Comment'),
               'TEXT',        $tp->Comment, ));

         $tpform->add_row( array(
               'DESCRIPTION', T_('User Message'),
               'TEXT', make_html_safe( $tp->UserMessage, true ) ));

         $tpform->add_row( array(
               'DESCRIPTION', T_('Admin Message'),
               'TEXTAREA',    'admin_message', 70, 5, $tp->AdminMessage ));
         if( @$_REQUEST['tp_preview'] )
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

      if( $is_delete )
      {
         if( $authorise_delete )
         {
            $tpform->add_hidden( 'confirm', 1 );
            $tpform->add_row( array(
                  'TAB', 'CELL', 1, '', // align submit-buttons
                  'SUBMITBUTTON', 'tp_delete', T_('Confirm removal of registration'),
                  'TEXT', SMALL_SPACING,
                  'SUBMITBUTTON', 'tp_cancel', T_('Cancel') ));
         }
      }
      else
      {
         $rowarr = array(
               'TAB', 'CELL', 1, '', // align submit-buttons
               'SUBMITBUTTON', 'tp_save', ($rid ? T_('Update registration') : T_('Save registration')),
               'SUBMITBUTTON', 'tp_preview', T_('Preview') );
         if( $rid && $authorise_delete )
            array_push( $rowarr,
                  'TEXT', SMALL_SPACING,
                  'SUBMITBUTTON', 'tp_delete', T_('Remove registration') );
         if( @$_REQUEST['tp_save'] || @$_REQUEST['tp_preview'] )
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
   if( $tourney->Type == TOURNEY_TYPE_LADDER && $rid )
   {
      if( $tourney->Status == TOURNEY_STATUS_PAIR || $tourney->Status == TOURNEY_STATUS_PLAY )
         $menu_array[T_('Admin user')] =
               array( 'url' => "tournaments/ladder/admin.php?tid=$tid".URI_AMP."uid=$uid", 'class' => 'TAdmin' );
   }
   $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}


function build_rating_str( $rating, $uid=0 )
{
   return (is_valid_rating($rating)) ? echo_rating($rating, true, $uid) : NO_VALUE;
}

function build_new_status_choices( $tp )
{
   $arr = array() + TournamentParticipant::getStatusText();
   if( $tp->ID == 0 || $tp->Status == TP_STATUS_INVITE )
   {
      unset($arr[TP_STATUS_APPLY]);
      unset($arr[TP_STATUS_REGISTER]);
   }
   elseif( $tp->Status == TP_STATUS_REGISTER )
      unset($arr[TP_STATUS_APPLY]);
   return $arr;
}

// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$tp, $tourney, $ttype )
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
   if( !@$_REQUEST['tp_showuser_handle'] && !@$_REQUEST['tp_showuser_uid'] )
   {
      // read URL-vals into vars
      foreach( $vars as $key => $val )
         $vars[$key] = get_request_arg( $key, $val );
   }

   // parse URL-vars
   if( $is_posted )
   {
      $old_vals['custom_rating'] = -OUT_OF_RATING;

      $tp->setStatus( $vars['status'] );

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
      if( get_request_arg('del_rating') )
         $tp->Rating = -OUT_OF_RATING;

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

   if( $is_posted || @$_REQUEST['tp_ack_apply'] )
   {
      $tp->AdminMessage = trim($vars['admin_message']);
      $tp->Notes = trim($vars['admin_notes']);

      // determine edits
      if( $old_vals['admin_message'] != $tp->AdminMessage ) $edits[] = T_('AdminMessage#edits');
      if( $old_vals['admin_notes'] != $tp->Notes ) $edits[] = T_('AdminNotes#edits');
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
   switch( (string)$type )
   {
      case 'delete':
         $subj = T_('Removed registration for tournament #%s');
         $body = T_('Your registration for %s has been deleted by tournament director %s.');
         $msg  = T_('Participant [%s] removed!');
         break;
      case 'ack_apply':
         $subj = T_('Approved registration for tournament #%s');
         $body = T_('Your application for %s has been approved by tournament director %s.');
         $msg  = T_('Application of user [%s] accepted!');
         break;
      case 'new_invite':
         $subj = T_('Invitation for tournament #%s');
         $body = T_('Tournament director %2$s has invited you for %1$s. This invitation must be verified by you by either approving or rejecting it.');
         $body2 = anchor( SUB_PATH."tournaments/register.php?tid=$tid", T_('Edit tournament registration') );
         $msg  = T_('User [%s] invited!');
         break;
      case TP_STATUS_APPLY.'_invite':
         $subj = T_('Verification of registration for tournament #%s');
         $body = T_('Your application for %s has been verified by tournament director %s and changed to an invitation needing your verification.');
         $msg  = T_('Application of user [%s] changed to invitation!');
         break;
      case TP_STATUS_REGISTER.'_invite':
         $subj = T_('Verification of registration for tournament #%s');
         $body = T_('Your registration for %s has been verified by tournament director %s and changed to an invitation needing your verification.');
         $msg  = T_('Registration of user [%s] changed to invitation!');
         break;
      default:
         error('invalid_args', "tournament.edit_participant($type,$tid,$uid,$my_id)");
   }

   send_message( "tournament.edit_participant.$type($tid,$uid,$my_id)",
      trim( sprintf( $body, "<tourney $tid>", "<user $my_id>" ) . "\n\n" .
            ( $body2 ? "$body2\n\n" : '' ) .
            "$sep<b>" . T_('Last user message:') . "</b>\n" . $tp->UserMessage . "\n" .
            "$sep<b>" . T_('Last admin message:') . "</b>\n" . $tp->AdminMessage ),
      sprintf( $subj, $tid ),
      $uid, '', true,
      0/*sys-msg*/, 'NORMAL', 0 );

   return urlencode( sprintf( "$msg " . T_('Message sent to user!#tourney'), $tp->User->Handle ) );
}//send_register_notification
?>
