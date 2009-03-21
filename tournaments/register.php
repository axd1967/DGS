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

$ThePage = new Page('TournamentRegistration');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.register');
   //TODO maybe later: check for DENY_TOURNEY_REGISTER for NEW-reg, allow edits (?)
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

/* Actual REQUEST calls used (TD=tournament-director)
     tid=                           : current user registers for tournament
     tid=&rid=                      : edit existing registration by user
     tp_preview&tid=&rid=           : preview for T-registration-save
     tp_save&tid=&rid=              : update registration in database
     tp_delete&tid=&rid=            : remove registration (need confirm)
     tp_delete&confirm=1&tid=&rid=  : remove registration (confirmed)
     tp_cancel&tid=&rid=            : cancel remove-confirmation
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

   // existing application ? (check matching tid & uid if loaded by rid)
   $tp = TournamentParticipant::load_tournament_participant( $tid, $my_id, $rid, true, true );
   if( is_null($tp) )
      $tp = new TournamentParticipant( 0, $tid, $my_id, User::new_from_row($player_row) ); // new TP
   else
      $rid = $tp->ID;

   // register/edit allowed?
   $reg_errors = $tourney->allow_register($my_id);
   if( count($reg_errors) )
   {
      //TODO output errors, don't jump to error-page
      error('tournament_register_not_allowed', "Tournament.register($tid,$my_id)");
   }

   if( $rid && @$_REQUEST['tp_delete'] && @$_REQUEST['confirm'] )
   {
      TournamentParticipant::delete_tournament_participant( $tid, $rid );
      jump_to("tournaments/register.php?tid=$tid".URI_AMP."sysmsg="
            . urlencode( sprintf( T_('Registration for user [%s] removed!'), @$player_row['Handle'] )) );
   }

   // check + parse edit-form
   $errors = NULL;
   if( @$_REQUEST['tp_save'] || @$_REQUEST['tp_preview'] ) // read URL-vars
   {
      $ratingStatus = @$player_row['RatingStatus'];
      if( $ratingStatus == 'INIT' || $ratingStatus == 'RATED' )
         $tp->Status = TP_STATUS_REGISTER;
      else
         $tp->Status = TP_STATUS_APPLY;

      $tp->Comment = trim(get_request_arg('comment'));
   }

   // persist TP in database
   if( $tid && @$_REQUEST['tp_save'] && !@$_REQUEST['tp_preview'] && is_null($errors) )
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
      $title = T_('Tournament registration removal for [%s]');
   else
      $title = T_('Tournament registration for [%s]');
   $title = sprintf( $title, $tourney->Title );


   // ---------- Tournament-Registration EDIT form ------------------------------

   $tpform = new Form( 'tournamentparticipant', $page, FORM_POST );
   $tpform->add_hidden( 'tid', $tid );
   $tpform->add_hidden( 'rid', $rid );

   // edit registration
   $tpform->add_row( array(
         'DESCRIPTION', T_('User'),
         'TEXT',        user_reference( REF_LINK, 1, '', $player_row), ));
   $tpform->add_row( array(
         'DESCRIPTION', T_('Current Rating'),
         'TEXT',        echo_rating( @$player_row['Rating2'], true, $my_id ) ));
   $tpform->add_row( array(
         'DESCRIPTION', T_('Status'),
         'TEXT',        ($rid ? TournamentParticipant::getStatusText($tp->Status, true) : NO_VALUE)
                        . "<br>\n<span class=\"TUserStatus\">$reg_user_info</span>", ));
   /* TODO
   if( $tp->Status == TP_STATUS_INVITE )
   {
      $tpform->add_row( array(
            'DESCRIPTION', T_('AuthToken'),
            'TEXTINPUT',   'authcode', 40, 32, $authcode, '', ));
   }
   */
   $tpform->add_row( array(
         'DESCRIPTION', T_('User Comment'),
         'TEXTINPUT',   'comment', 60, 60, $tp->Comment, '', ));
   /*
   $tpform->add_row( array(
         'DESCRIPTION', T_('Start Round'),
         'TEXT',        $tp->StartRound, ));
   */
   /* TODO add rating-input; put validation/has-rating check somewhere else
   if( abs($tp->Rating) < OUT_OF_RATING )
      $tpform->add_row( array(
            'DESCRIPTION', T_('Tournament Rating'),
            'TEXT',        echo_rating( $tp->Rating, true, $my_id ), ));
   */
   if( $tp->Created > 0 )
      $tpform->add_row( array(
            'DESCRIPTION', T_('Application Date'),
            'TEXT',        date(DATE_FMT2, $tp->Created), ));
   if( $tp->Lastchanged > 0 )
      $tpform->add_row( array(
            'DESCRIPTION', T_('Last changed'),
            'TEXT',        date(DATE_FMT2, $tp->Lastchanged), ));

   if( @$_REQUEST['tp_delete'] )
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
            'SUBMITBUTTON', 'tp_save', ($rid ? T_('Update registration') : T_('Register')),
         );
      if( $rid )
         $rowarr = array_merge($rowarr, array(
               'TEXT', SMALL_SPACING,
               'SUBMITBUTTON', 'tp_delete', T_('Remove registration'),
            ));
      $tpform->add_row( $rowarr );
   }


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tpform->echo_string();

   $notes = TournamentParticipant::build_notes();
   echo_notes( 'registernotesTable', T_('Registration notes'), $notes );


   $menu_array = array();
   $menu_array[T_('View this tournament')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Tournament directors')] = "tournaments/list_directors.php?tid=$tid";
   $menu_array[T_('Tournament participants')] = "tournaments/list_participants.php?tid=$tid";

   end_page(@$menu_array);
}

?>
