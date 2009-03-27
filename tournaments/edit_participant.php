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

$ThePage = new Page('TournamentEditParticipant');

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
       start_round                        : start round
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

   $allow_edit_tourney = $tourney->allow_edit_tournaments( $my_id );
   if( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.edit_participant($tid,$my_id)");

   // load-user, change-user?
   $errors = array();
   $edits = array();
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


   // check + parse edit-form
   $val_custom_rating = trim(get_request_arg('custom_rating'));
   $custom_rating_str = '';
   if( (string)$val_custom_rating != '' )
   {
      $custom_rating = convert_to_rating( $val_custom_rating,
            get_request_arg('ratingtype', 'dragonrank'), true );
      //TODO if( $custom_rating == '' ) error (bad-rating)
      $custom_rating_str = echo_rating( $custom_rating, true );
   }

   $old_status = $tp->Status;
   $old_flags = $tp->Flags;
   $old_rating = $tp->Rating;
   $old_start_round = $tp->StartRound;

   // parse inputs from form
   $is_delete = @$_REQUEST['tp_delete'];
   if( @$_REQUEST['tp_save'] || @$_REQUEST['tp_preview'] || $is_delete ) // read URL-vars
   {
      $tp->setStatus( get_request_arg('status', TP_STATUS_APPLY) );
      if( strcmp($old_status, $tp->Status) ) $edits[] = T_('Status#edits');

      if( $tp->Status == TP_STATUS_INVITE )
      {
         $tp->Flags |= TP_FLAGS_INVITED;
         $tp->Flags &= ~TP_FLAGS_ACK_INVITE;
      }
      if( $old_status == TP_STATUS_APPLY && $tp->Status == TP_STATUS_REGISTER )
         $tp->Flags |= TP_FLAGS_ACK_APPLY;

      if( $val_custom_rating != '' && (string)$custom_rating != '' )
         $tp->Rating = $custom_rating;
      if( get_request_arg('del_rating') )
         $tp->Rating = -OUT_OF_RATING;
      if( $old_rating != $tp->Rating ) $edits[] = T_('Rating#edits');

      $start_round = trim(get_request_arg('start_round'));
      if( $start_round != '' && is_numeric($start_round) ) //TODO check round
         $tp->StartRound = $start_round;
      if( $old_start_round != $tp->StartRound ) $edits[] = T_('Round#edits');

      $tmp = trim(get_request_arg('admin_notes'));
      if( strcmp($tp->Notes, $tmp) ) $edits[] = T_('Notes#edits');
      $tp->Notes = $tmp;

      $tmp = trim(get_request_arg('admin_message'));
      if( strcmp($tp->AdminMessage, $tmp) ) $edits[] = T_('AdminMessage#edits');
      $tp->AdminMessage = $tmp;
   }

   if( $rid && $is_delete  && @$_REQUEST['confirm'] )
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


   // persist TP in database
   if( $tid && @$_REQUEST['tp_save'] && !@$_REQUEST['tp_preview'] && count($errors) == 0 )
   {
      $tp->persist(); // insert or update
      jump_to("tournaments/edit_participant.php?tid=$tid".URI_AMP."uid={$tp->uid}".URI_AMP."sysmsg="
            . urlencode(T_('Registration saved!')) );
   }


   $page = "edit_participant.php";
   $title = T_('Tournament Registration Editor for [%s]');
   $title = sprintf( $title, $tourney->Title );


   // ---------- Tournament-Registration-Edit EDIT form -------------------------

   $user_rating_str = echo_rating( $tp->User->Rating, true, $tp->uid);
   $old_rating_str = (is_valid_rating($old_rating)) ? echo_rating( $old_rating, true, $tp->uid) : NO_VALUE;


   $tpform = new Form( 'tournamenteditparticipant', $page, FORM_POST );
   $tpform->add_hidden( 'tid', $tid );
   $tpform->add_hidden( 'uid', $uid );

   // edit registration
   $tpform->add_row( array(
         'DESCRIPTION',    T_('Edit User'),
         'TEXTINPUT',      'showuser', 16, 16, $userhandle, '',
         'SUBMITBUTTON',   'tp_showuser_handle', T_('Show User by Handle'),
         'SUBMITBUTTON',   'tp_showuser_uid', T_('Show User by ID'), ));
   $tpform->add_empty_row();
   if( !is_null($user) )
      $tpform->add_row( array(
            'DESCRIPTION', T_('User'),
            'TEXT', $user->user_reference(), ));

   if( count($errors) )
   {
      $tpform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT',        '<span class="ErrorMsg">'
                  . T_('There are some errors:') . "<br>\n"
                  . '* ' . implode(",<br>\n* ", $errors)
                  . '</span>' ));
   }

   if( !is_null($user) )
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

      $tpform->add_row( array(
            'DESCRIPTION', T_('Current Rating'),
            'TEXT',        ( $user_rating_str ? $user_rating_str : NO_VALUE), ));
      if( !$is_delete )
      {
         $tpform->add_row( array(
               'DESCRIPTION', T_('Custom Rating'),
               'TEXT',        ( $custom_rating_str ? $custom_rating_str : NO_VALUE ) . SMALL_SPACING,
               'TEXTINPUT',   'custom_rating', 10, 10, get_request_arg('custom_rating'), '',
               'SELECTBOX',   'ratingtype', 1, getRatingTypes(),
                              get_request_arg('ratingtype','dragonrank'), false,
               'SUBMITBUTTON', 'tp_preview', T_('Convert Rating'), ));
      }

      $arr = array(
            'DESCRIPTION', T_('Tournament Rating'),
            'TEXT',        $old_rating_str, );
      if( !$tp->needTournamentRating() )
         array_push( $arr,
            'TEXT', SMALL_SPACING,
            'CHECKBOX', 'del_rating', '1', T_('Remove custom rating'),
                        (get_request_arg('del_rating') ? 1 : '') );
      $tpform->add_row( $arr );
      $tpform->add_empty_row();

      $tpform->add_row( array(
            'DESCRIPTION', T_('Start Round'),
            'TEXT',        $old_start_round, ));
      if( !$is_delete )
         $tpform->add_row( array(
               'DESCRIPTION', T_('Custom Start Round'),
               'TEXTINPUT',   'start_round', 3, 3, get_request_arg('start_round'), '', ));
      $tpform->add_empty_row();

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
            'TEXT', sprintf( '<span class="TWarning">[%s]</span>', implode(', ', $edits )), ));

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

   $tpform->echo_string();


   $menu_array = array();
   $menu_array[T_('Tournament participants')] = "tournaments/list_participants.php?tid=$tid";
   $menu_array[T_('Tournament directors')] = "tournaments/list_directors.php?tid=$tid";
   $menu_array[T_('View this tournament')] = "tournaments/view_tournament.php?tid=$tid";

   end_page(@$menu_array);
}
?>
