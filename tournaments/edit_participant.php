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

/* Actual REQUEST calls used (TD=tournament-director)
     tid=                                 : edit TPs (no user selected)
     tid=&uid=                            : edit TP (given by Players.ID = uid)
     tid=&showuser=123&tp_showuser_uid    : edit TP (given by Players.ID = showuser)
     tid=&showuser=abc&tp_showuser_handle : edit TP (given by Players.Handle = showuser)
     tp_preview&tid=&uid=                 : preview edits for TP
     tp_save&tid=&uid=                    : update TP-registration in database
     tp_delete&tid=&uid=                  : remove TP (need confirm)
     tp_delete&confirm=1tid=&uid=         : remove TP (confirmed)
     tp_cancel&tid=&uid=                  : cancal remove-confirmation

   Fields for preview & save:
       status                             : new status
       custom_rating,ratingtype           : custom rating + rating-type for conversion
       del_rating=1                       : remove TP-Rating if set
       start_round                        : start round (if needed)
       admin_message, admin_notes         : AdminMessage, Notes
*/

   $tid = (int) @$_REQUEST['tid'];
   $uid = (int) @$_REQUEST['uid'];
   $userhandle = get_request_arg('showuser');
   $user = NULL;

   if( @$_REQUEST['tp_cancel'] ) // cancel delete
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
      $tprops = TournamentProperties::load_tournament_properties( $tid );
      if( is_null($tprops) )
         error('bad_tournament', "Tournament.edit_participant.miss_properties($tid,$my_id)");
   }

   // existing application ?
   $tp = TournamentParticipant::load_tournament_participant( $tid, $uid );
   if( is_null($tp) )
   {
      if( !is_null($user) )
         $tp = new TournamentParticipant( 0, $tid, $user->ID, $user ); // new TP
      else
         $tp = new TournamentParticipant( 0, $tid ); // dummy "null" TP
   }
   $rid = $tp->ID;


   // init
   $is_delete = @$_REQUEST['tp_delete'];
   $old_status = $tp->Status;
   $old_flags = $tp->Flags;
   $old_rating = $tp->Rating;
   $old_start_round = $tp->StartRound;

   // check + parse edit-form
   list( $vars, $edits, $errorlist ) = parse_edit_form( $tp, $tourney, $ttype );
   $errors += $errorlist;

   // delete TD in database
   if( $rid && $is_delete && @$_REQUEST['confirm'] )
   {
      TournamentParticipant::delete_tournament_participant( $tid, $rid );

      $sep = str_repeat('-', 20). "\n";
      send_message( "tournament.edit_participant.delete($tid,$uid)",
         trim(sprintf(
               T_("Your application for %s has been deleted by tournament director %s.\n\n%s\n%s"),
               "<tourney $tid>", "<user $my_id>",
               $sep . sprintf( T_("<b>Former user message:</b>\n%s"), $tp->UserMessage ),
               $sep . sprintf( T_("<b>Former admin message:</b>\n%s"), $tp->AdminMessage ) )),
         sprintf( T_('Removed registration for tournament #%s'), $tid),
         $uid, '', true,
         $my_id, 'NORMAL', 0 );

      jump_to("tournaments/edit_participant.php?tid=$tid".URI_AMP."uid=$uid".URI_AMP."sysmsg="
            . urlencode( sprintf( T_('Participant [%s] removed! Message sent to user!'), $tp->User->Handle )) );
   }

   // check user-attributes
   if( !is_null($tprops) )
   {
      $tp_has_rating = is_valid_rating($tp->Rating); //TODO used?
      $reg_errors = $tprops->checkUserRegistration( $tourney, $user );
   }
   else
      $reg_errors = array();

   // process parsed inputs from form for: Status, Flags
   if( $vars['_is_posted'] )
   {
      if( $tp->Status == TP_STATUS_INVITE )
         $tp->Flags = ($tp->Flags | TP_FLAGS_INVITED) & ~TP_FLAGS_ACK_INVITE;
      if( $old_status == TP_STATUS_APPLY && $tp->Status == TP_STATUS_REGISTER )
         $tp->Flags |= TP_FLAGS_ACK_APPLY;
      if( count($reg_errors) ) // violate registration restrictions
         $tp->Flags |= TP_FLAGS_VIOLATE;
   }

   // persist TP in database
   if( $tid && @$_REQUEST['tp_save'] && !@$_REQUEST['tp_preview'] && count($errors) == 0 && count($reg_errors) == 0 )
   {
      $tp->persist();
      jump_to("tournaments/edit_participant.php?tid=$tid".URI_AMP."uid={$tp->uid}".URI_AMP."sysmsg="
            . urlencode(T_('Registration saved!')) );
   }


   $page = "edit_participant.php";
   $title = T_('Tournament Registration Editor for [%s]');
   $title = sprintf( $title, $tourney->Title );


   // ---------- Tournament-Registration-Edit EDIT form -------------------------

   $tpform = new Form( 'tournamenteditparticipant', $page, FORM_POST );
   $tpform->add_hidden( 'tid', $tid );
   $tpform->add_hidden( 'uid', $uid );

   // edit registration
   $tpform->add_row( array(
         'DESCRIPTION',    T_('Edit User'),
         'TEXTINPUT',      'showuser', 16, 16, $userhandle, '',
         'SUBMITBUTTON',   'tp_showuser_handle', T_('Show User by Handle'),
         'SUBMITBUTTON',   'tp_showuser_uid', T_('Show User by ID'), ));
   $tpform->add_row( array( 'HR' ));

   if( count($reg_errors) )
   {
      $restrictions = T_('<b>Registration restrictions</b> for this tournament and this user:')
         . "\n<ul>";
      foreach( $reg_errors as $err )
         $restrictions .= "<li>" . make_html_safe($err, 'line') . "\n";
      $restrictions .= "</ul><hr>";
      $restrictions = span('TWarning', $restrictions);
      $tpform->add_row( array(
            'OWNHTML', "<td colspan=2>$restrictions</td>" ));
   }

   $tpform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));

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

   if( !is_null($user) ) // edit
   {
      $old_status_str = ($old_status) ? TournamentParticipant::getStatusText($old_status) : NO_VALUE;

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
            'TEXT',        $old_status_str . SMALL_SPACING,
            'SELECTBOX',   'status', 1, TournamentParticipant::getStatusText(), $tp->Status, false, ));
      $tpform->add_row( array(
            'DESCRIPTION', T_('Flags'),
            'TEXT',        TournamentParticipant::getFlagsText($old_flags), ));
      $tpform->add_empty_row();

      if( !is_null($tprops) )
         $tpform->add_row( array(
               'DESCRIPTION', T_('Rating Use Mode'),
               'TEXT',  TournamentProperties::getRatingUseModeText($tprops->RatingUseMode)
                        . "<br>\n"
                        . span('TInfo', TournamentProperties::getRatingUseModeText($tprops->RatingUseMode, false), '(%s)'), ));
      $tpform->add_row( array(
            'DESCRIPTION', T_('Current Rating'),
            'TEXT',        build_rating_str($tp->User->Rating, $tp->uid), ));
      if( !$is_delete )
      {
         $rating_type = get_request_arg('ratingtype', 'dragonrank');
         $tpform->add_row( array(
               'DESCRIPTION', T_('Custom Rating'),
               'TEXT',        $vars['_custom_rating_str'] . SMALL_SPACING,
               'TEXTINPUT',   'custom_rating', 10, 10, get_request_arg('custom_rating'), '',
               'SELECTBOX',   'ratingtype', 1, getRatingTypes(), $rating_type, false,
               'SUBMITBUTTON', 'tp_preview', T_('Convert Rating'), ));
      }

      $arr = array(
            'DESCRIPTION', T_('Tournament Rating'),
            'TEXT',        build_rating_str( $old_rating, $tp->uid ), );
      if( $tp->User->hasRating() ) // T-rating not needed if user has rating
         array_push( $arr,
            'TEXT', SMALL_SPACING,
            'CHECKBOX', 'del_rating', '1', T_('Remove custom rating'),
                        (get_request_arg('del_rating') ? 1 : '') );
      $tpform->add_row( $arr );
      $tpform->add_empty_row();

      if( $ttype->need_rounds )
      {
         $tpform->add_row( array(
               'DESCRIPTION', T_('Start Round'),
               'TEXT',        $old_start_round, ));
         if( !$is_delete )
            $tpform->add_row( array(
                  'DESCRIPTION', T_('Custom Start Round'),
                  'TEXTINPUT',   'start_round', 3, 3, get_request_arg('start_round'), '',
                  'TEXT',        MINI_SPACING . $tourney->getRoundLimitText(), ));
         $tpform->add_empty_row();
      }

      $tpform->add_row( array(
            'DESCRIPTION', T_('Public User Comment'),
            'TEXT',        $tp->Comment, ));

      $tpform->add_row( array(
            'DESCRIPTION', T_('User Message'),
            'TEXT', make_html_safe( $tp->UserMessage, true ) ));
      $tpform->add_row( array(
            'DESCRIPTION', T_('Admin Message'),
            'TEXTAREA',    'admin_message', 70, 5, $tp->AdminMessage ));
      if( !$is_delete )
         $tpform->add_row( array(
               'DESCRIPTION', T_('Admin Notes'),
               'TEXTAREA',    'admin_notes', 70, 5, $tp->Notes ));

      $tpform->add_row( array(
            'DESCRIPTION', T_('Unsaved edits'),
            'TEXT',        span('TWarning', implode(', ', $edits ), '[%s]'), ));

      if( $is_delete )
      {
         $tpform->add_hidden( 'confirm', 1 );
         $tpform->add_row( array(
            'TAB', 'CELL', 1, '', // align submit-buttons
            'SUBMITBUTTON', 'tp_delete', T_('Confirm removal of registration'),
            'TEXT', SMALL_SPACING,
            'SUBMITBUTTON', 'tp_cancel', T_('Cancel') ));
      }
      else
      {
         $rowarr = array(
               'TAB', 'CELL', 1, '', // align submit-buttons
               'SUBMITBUTTON', 'tp_save', ($rid ? T_('Update registration') : T_('Save registration')),
               'SUBMITBUTTON', 'tp_preview', T_('Preview'),
            );
         if( $rid )
            $rowarr = array_merge($rowarr, array(
                  'TEXT', SMALL_SPACING,
                  'SUBMITBUTTON', 'tp_delete', T_('Remove registration'),
               ));
         $tpform->add_row( $rowarr );
      }
   } //user_known


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
   $menu_array[T_('Tournament participants')] = "tournaments/list_participants.php?tid=$tid";
   $menu_array[T_('Tournament directors')] = "tournaments/list_directors.php?tid=$tid";
   $menu_array[T_('View this tournament')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Manage this tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}


function build_rating_str( $rating, $uid=0 )
{
   return (is_valid_rating($rating)) ? echo_rating($rating, true, $uid) : NO_VALUE;
}

// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$tp, $tourney, $ttype )
{
   $edits = array();
   $errors = array();
   $is_posted = ( @$_REQUEST['tp_save'] || @$_REQUEST['tp_preview'] || @$_REQUEST['tp_delete'] );

   // read from props or set defaults
   $vars = array(
      'status'          => $tp->Status,
      'custom_rating'   => echo_rating( $tp->Rating, false, 0, true, false ),
      'start_round'     => $tp->StartRound,
      'admin_message'   => $tp->AdminMessage,
      'admin_notes'     => $tp->Notes,
   );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // parse URL-vars
   if( $is_posted )
   {
      $old_vals['custom_rating'] = $tp->Rating;

      $tp->setStatus( $vars['status'], TP_STATUS_APPLY );

      $new_value = trim($vars['custom_rating']); // optional
      if( (string)$new_value != '' )
      {
         $rating_type = get_request_arg('ratingtype', 'dragonrank');
         $custom_rating = convert_to_rating( $new_value, $rating_type, true );
         if( !is_valid_rating($custom_rating) )
            $errors[] = ErrorCode::get_error_text('invalid_rating');
         else
            $tp->Rating = $custom_rating;
      }
      if( get_request_arg('del_rating') )
         $tp->Rating = -OUT_OF_RATING;

      if( $ttype->need_rounds )
      {
         $new_value = trim($vars['start_round']); // optional
         if( (string)$new_value != '' )
         {
            if( !is_numeric($new_value) )
               $errors[] = T_('Expecting positive number for start round');
            elseif( $new_value < 1 )
               $tp->StartRound = 1;
            elseif( $tourney->Rounds > 0 && $new_value > $tourney->Rounds )
               $tp->StartRound = $tourney->Rounds;
            else
               $tp->StartRound = $new_value;
         }
      }

      $tp->AdminMessage = trim($vars['admin_message']);
      $tp->Notes = trim($vars['admin_notes']);

      // reformat
      $vars['custom_rating'] = echo_rating( $tp->Rating, false, 0, true, false );

      // determine edits
      if( $old_vals['status'] != $tp->Status ) $edits[] = T_('Status#edits');
      if( $old_vals['custom_rating'] != $tp->Rating ) $edits[] = T_('Rating#edits');
      if( $old_vals['start_round'] != $tp->StartRound ) $edits[] = T_('StartRound#edits');
      if( $old_vals['admin_message'] != $tp->AdminMessage ) $edits[] = T_('AdminMessage#edits');
      if( $old_vals['admin_notes'] != $tp->Notes ) $edits[] = T_('AdminNotes#edits');
   }

   // additionals
   $vars['_custom_rating_str'] =
      (is_valid_rating($tp->Rating)) ? echo_rating($tp->Rating, true) : NO_VALUE;
   $vars['_is_posted'] = $is_posted;

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form
?>
