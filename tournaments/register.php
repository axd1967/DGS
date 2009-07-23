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

$TranslateGroups[] = "Tournament";

chdir('..');
require_once( 'include/std_functions.php' );
require_once( 'include/gui_functions.php' );
require_once( 'include/form_functions.php' );
require_once( 'include/rating.php' );
require_once( 'tournaments/include/tournament.php' );
require_once( 'tournaments/include/tournament_participant.php' );
require_once( 'tournaments/include/tournament_properties.php' );

$ThePage = new Page('TournamentRegistration');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.register');
   //TODO(later maybe): check for DENY_TOURNEY_REGISTER for NEW-reg, allow edits (?)
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

/* Actual REQUEST calls used (TD=tournament-director)
     tid=                           : current user registers for tournament
     tid=&rid=                      : current user edits existing registration
     tp_preview&tid=&rid=           : preview for T-registration-save
     tp_save&tid=&rid=              : update registration in database
     tp_delete&tid=&rid=            : remove registration (need confirm)
     tp_delete&confirm=1&tid=&rid=  : remove registration (confirmed)
     tp_cancel&tid=&rid=            : cancel remove-confirmation
     tp_ack_invite&tid=             : approve invitation by TD
     tp_edit_invite&tid=            : reject invitation by TD, transforming to user-apply
*/

   //TODO check: delete only allowed during reg-period (T.Status='REG')

   //TODO can this page be viewed for user A by user B (non-TD) ? -> get invalid-args now (load_TPs for uid)

   $tid = (int) @$_REQUEST['tid'];
   $rid = (int) @$_REQUEST['rid'];
   if( $rid < 0 ) $rid = 0;

   if( @$_REQUEST['tp_cancel'] ) // cancel delete
      jump_to("tournaments/register.php?tid=$tid".URI_AMP."rid=$rid");

   $tourney = Tournament::load_tournament( $tid ); // existing tournament ?
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.register.find_tournament($tid)");

   $tprops = TournamentProperties::load_tournament_properties( $tid );
   if( is_null($tprops) )
      $tprops = new TournamentProperties($tid);

   // existing application ? (check matching tid & uid if loaded by rid)
   $tp = TournamentParticipant::load_tournament_participant( $tid, $my_id, $rid, true, true );
   if( is_null($tp) )
      $tp = new TournamentParticipant( 0, $tid, $my_id, User::new_from_row($player_row) ); // new TP
   else
      $rid = $tp->ID;

   // register/edit allowed?
   $reg_errors = $tourney->allow_register($my_id);
   if( count($reg_errors) )
      error('tournament_register_not_allowed', "Tournament.register($tid,$my_id)");
   $reg_errors = $tprops->checkUserRegistration( $tourney, $my_id );

   $is_delete = (bool)( @$_REQUEST['tp_delete'] );
   if( $rid && $is_delete && @$_REQUEST['confirm'] )
   {
      TournamentParticipant::delete_tournament_participant( $tid, $rid );
      jump_to("tournaments/register.php?tid=$tid".URI_AMP."sysmsg="
            . urlencode( sprintf( T_('Registration for user [%s] removed!'), @$player_row['Handle'] )) );
   }

   $is_invite = ( $tp->Status == TP_STATUS_INVITE );

   // check + parse edit-form
   //TODO use same method as in edit_properties.php (error, edits, vars, parsing)
   $errors = array();
   $allow_register = true;
   if( $tp->ID == 0 && count($reg_errors) ) // error only if new-reg by user
   {
      $errors[] = T_('Registration restrictions disallow you to register.');
      $allow_register = false;
   }

   $val_custom_rating = trim(get_request_arg('custom_rating'));
   if( !$is_invite && (string)$val_custom_rating != '' )
   {
      $custom_rating = convert_to_rating( $val_custom_rating,
            get_request_arg('ratingtype', 'dragonrank'), true );
      //TODO if( $custom_rating == '' ) error (bad-rating)
   }
   else
      $custom_rating = $tp->Rating;
   $custom_rating_str = echo_rating( $custom_rating, true );

   if( !$is_invite && ( @$_REQUEST['tp_save'] || @$_REQUEST['tp_preview'] ) ) // read URL-vars
   {
      //TODO check for rating-status
      $ratingStatus = @$player_row['RatingStatus'];
      if( $tp->ID == 0 )
         $tp->Status = ( $tp->needTournamentRating() ) ? TP_STATUS_APPLY : TP_STATUS_REGISTER;

      if( $val_custom_rating != '' && (string)$custom_rating != '' )
      {
         $tp->Rating = $custom_rating;
         $tp->Status = TP_STATUS_APPLY;
      }

      $start_round = trim(get_request_arg('start_round'));
      if( $start_round != '' && is_numeric($start_round) )
      {
         // check round
         if( $tourney->Rounds > 0 && $start_round > $tourney->Rounds )
            $start_round = $tourney->Rounds;
         if( $tp->StartRound != $start_round )
         {
            $tp->StartRound = $start_round;
            $tp->Status = TP_STATUS_APPLY;
         }
      }
   }

   if( @$_REQUEST['tp_save'] || @$_REQUEST['tp_preview']
         || @$_REQUEST['tp_ack_invite'] || @$_REQUEST['tp_edit_invite'] ) // read URL-vars
   {
      $tp->Comment = trim(get_request_arg('comment'));
      $tp->UserMessage = trim(get_request_arg('user_message'));
   }

   // handle invitation: ACK (approval)
   if( $is_invite && @$_REQUEST['tp_ack_invite'] ) // accepted invite
   {
      $tp->Status = TP_STATUS_REGISTER;
      $tp->Flags |= TP_FLAGS_ACK_INVITE;
      $tp->persist(); // update
      jump_to("tournaments/register.php?tid=$tid".URI_AMP."rid={$tp->ID}".URI_AMP."sysmsg="
            . urlencode(T_('Invitation accepted!')) );
   }

   // handle invitation: NACK (rejection)
   if( $is_invite && @$_REQUEST['tp_edit_invite'] ) // declined invite with edit
   {
      $tp->Status = TP_STATUS_APPLY;
      $tp->Flags &= ~TP_FLAGS_ACK_APPLY;
      $tp->persist(); // update
      jump_to("tournaments/register.php?tid=$tid".URI_AMP."rid={$tp->ID}".URI_AMP."sysmsg="
            . urlencode(T_('Invitation declined!')) );
   }


   // persist TP in database
   if( $tid && @$_REQUEST['tp_save'] && !@$_REQUEST['tp_preview'] && count($errors) == 0 )
   {
      $tp->persist(); // insert or update
      jump_to("tournaments/register.php?tid=$tid".URI_AMP."rid={$tp->ID}".URI_AMP."sysmsg="
            . urlencode(T_('Registration saved!')) );
   }

   $reg_user_status = TournamentParticipant::isTournamentParticipant( $tid, $my_id );
   $reg_user_info = ( count($tourney->allow_register($my_id, true)) )
      ? '' : TournamentParticipant::getStatusText( $reg_user_status, false, true );


   $page = "register.php";
   if( @$_REQUEST['tp_delete'] )
      $title = T_('Tournament Registration removal for [%s]');
   else
      $title = T_('Tournament Registration for [%s]');
   $title = sprintf( $title, $tourney->Title );


   // ---------- Tournament-Registration EDIT form ------------------------------

   $current_rating = echo_rating( @$player_row['Rating2'], true, $my_id );


   $tpform = new Form( 'tournamentparticipant', $page, FORM_POST );
   $tpform->add_hidden( 'tid', $tid );
   $tpform->add_hidden( 'rid', $rid );

   // edit registration
   $tpform->add_row( array(
         'DESCRIPTION', T_('User'),
         'TEXT',        user_reference( REF_LINK, 1, '', $player_row), ));
   if( $tp->Created > 0 )
      $tpform->add_row( array(
            'DESCRIPTION', T_('Application Date'),
            'TEXT',        date(DATE_FMT2, $tp->Created), ));
   if( $tp->Lastchanged > 0 )
      $tpform->add_row( array(
            'DESCRIPTION', T_('Last changed'),
            'TEXT',        date(DATE_FMT2, $tp->Lastchanged), ));
   $tpform->add_row( array(
         'DESCRIPTION', T_('Status'),
         'TEXT',        ($rid ? TournamentParticipant::getStatusText($tp->Status, true) : NO_VALUE)
                        . "<br>\n<span class=\"TUserStatus\">$reg_user_info</span>", ));

   if( count($errors) )
   {
      $tpform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT',        '<span class="ErrorMsg">'
                  . T_('There are some errors, so registration can\'t be saved:') . "<br>\n"
                  . '* ' . implode(",<br>\n* ", $errors)
                  . '</span>' ));
   }
   $tpform->add_empty_row();

   $tpform->add_row( array(
         'DESCRIPTION', T_('Current Rating'),
         'TEXT',        ( $current_rating ? $current_rating : NO_VALUE), ));
   if( $allow_register && !$is_invite && !$is_delete )
   {
      $tpform->add_row( array(
            'DESCRIPTION', T_('Custom Rating'),
            'TEXT',        ( $custom_rating_str ? $custom_rating_str : NO_VALUE ) . SMALL_SPACING,
            'TEXTINPUT',   'custom_rating', 10, 10, get_request_arg('custom_rating'), '',
            'SELECTBOX',   'ratingtype', 1, getRatingTypes(),
                           get_request_arg('ratingtype','dragonrank'), false,
            'SUBMITBUTTON', 'tp_preview', T_('Convert Rating'), ));
   }
   if( abs($tp->Rating) < OUT_OF_RATING )
      $tpform->add_row( array(
            'DESCRIPTION', T_('Tournament Rating'),
            'TEXT',        echo_rating( $tp->Rating, true, $my_id ), ));
   $tpform->add_empty_row();

   if( $allow_register )
      $tpform->add_row( array(
            'DESCRIPTION', T_('Start Round'),
            'TEXT',        $tp->StartRound, ));
   if( $allow_register && !$is_invite && !$is_delete )
   {
      $tpform->add_row( array(
            'DESCRIPTION', T_('Custom Start Round'),
            'TEXTINPUT',   'start_round', 3, 3, get_request_arg('start_round'), '',
            'TEXT',        MINI_SPACING . $tourney->getRoundLimitText(), ));
   }
   $tpform->add_empty_row();

   if( $allow_register && !$is_delete )
   {
      $tpform->add_row( array(
            'DESCRIPTION', T_('Public User Comment'),
            'TEXTINPUT',   'comment', 60, 60, $tp->Comment, '', ));

      $tpform->add_row( array(
            'DESCRIPTION', T_('User Message'),
            'TEXTAREA',    'user_message', 70, 5, $tp->UserMessage ));
      $tpform->add_row( array(
            'DESCRIPTION', T_('Admin Message'),
            'TEXT', make_html_safe( $tp->AdminMessage, true ) ));
   }

   if( $is_delete )
   {
      $tpform->add_hidden( 'confirm', 1 );
      $tpform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'tp_delete', T_('Confirm removal of registration'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tp_cancel', T_('Cancel') ));
   }
   else if( $allow_register )
   {
      $rowarr = array( 'TAB', 'CELL', 1, '', ); // align submit-buttons
      if( $is_invite )
      {
         array_push( $rowarr,
            'SUBMITBUTTON', 'tp_ack_invite', T_('Accept invitation'),
            'SUBMITBUTTON', 'tp_edit_invite', T_('Edit invitation') );
      }
      else
      {
         array_push( $rowarr,
            'SUBMITBUTTON', 'tp_save', ($rid) ? T_('Update registration') : T_('Register'),
            'SUBMITBUTTON', 'tp_preview', T_('Preview') );
      }
      if( $rid )
      {
         array_push( $rowarr,
               'TEXT', SMALL_SPACING,
               'SUBMITBUTTON', 'tp_delete', T_('Remove registration') );
      }
      $tpform->add_row( $rowarr );
   }


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   if( count($reg_errors) )
   {
      section( 'restriction', T_('Registration restrictions') );
      echo_notes( 'registerrestrictionsTable',
         T_('You are not eligible to register for this tournament:'), $reg_errors, false );
      echo "<center><hr class=Inline></center>\n";
   }

   $tpform->echo_string();

   $notes = TournamentParticipant::build_notes();
   echo_notes( 'registernotesTable', T_('Registration notes'), $notes );


   $menu_array = array();
   $menu_array[T_('Tournament participants')] = "tournaments/list_participants.php?tid=$tid";
   $menu_array[T_('Tournament directors')] = "tournaments/list_directors.php?tid=$tid";
   $menu_array[T_('View this tournament')] = "tournaments/view_tournament.php?tid=$tid";

   end_page(@$menu_array);
}

?>
