<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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

chdir('../..');
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_round_status.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentRoundStatusEdit');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_round_status');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

/* Actual REQUEST calls used:
     tid=&round=                 : edit status of tournament-round
     t_save&tid=&round=          : change tournament-round-status in database (need confirm)
     t_confirm&tid=&round=       : change tournament-round-status in database (confirmed)
     t_cancel                    : cancel status-change-confirmation
*/

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;
   $round = (int) @$_REQUEST['round'];
   if( $round < 0 ) $round = 0;

   // load tourney and T-round
   $tr_status = new TournamentRoundStatus( $tid, $round ); // existing tournament-round?
   $tourney = $tr_status->tourney;
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   if( !$ttype->need_rounds )
      error('tournament_edit_rounds_not_allowed', "Tournament.edit_round_status.need_rounds($tid,$round)");

   // create/edit allowed?
   $allow_edit_tourney = $tourney->allow_edit_tournaments($my_id);
   if( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.edit_round_status.edit_tournament($tid,$round,$my_id)");

   $tround = $tr_status->tround;
   $is_admin = TournamentUtils::isAdmin();

   if( @$_REQUEST['t_cancel'] ) // cancel status-change
      jump_to("tournaments/roundrobin/edit_round_status.php?tid=$tid".URI_AMP."round=$round");


   // init
   $arr_status = TournamentRound::getStatusText();
   $status_errors = $tstatus->check_edit_status( TournamentRound::get_edit_tournament_status() );
   foreach( $status_errors as $errmsg )
      $tr_status->add_error( $errmsg );

   // check + parse edit-form + check status
   list( $vars, $edits ) = parse_edit_form( $tround );

   $new_status = $vars['status'];
   $tr_status->check_status_change($new_status);
   $tround->setStatus($new_status);
   if( !$is_admin && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $tr_status->add_error( $tourney->buildAdminLockText() );

   // save tournament-object with values from edit-form (if no errors and something changed)
   $allow_confirm = ( !$tr_status->has_error() || $is_admin );
   if( @$_REQUEST['t_confirm'] && count($edits) && $allow_confirm && count($status_errors) == 0 )
   {
      $tround->persist();
      jump_to("tournaments/roundrobin/edit_round_status.php?tid=$tid".URI_AMP."round=$round".URI_AMP
            . "sysmsg=". urlencode(T_('Tournament round status saved!')) );
   }

   $page = "edit_round_status.php";
   $title = T_('Tournament Round Status Editor');


   // ---------- Tournament Round Status EDIT form -----------------------

   $tform = new Form( 'tournament', $page, FORM_GET );
   $tform->add_hidden( 'tid', $tid );
   $tform->add_hidden( 'round', $round );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   TournamentUtils::show_tournament_flags( $tform, $tourney );
   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament Round#tround'),
         'TEXT',        $tround->Round, ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Last changed'),
         'TEXT',        TournamentUtils::buildLastchangedBy($tround->Lastchanged, $tround->ChangedBy) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Round Status#tround'),
         'TEXT',        TournamentRound::getStatusText($tround->Status), ));
   $tform->add_row( array( 'HR' ));

   if( $tr_status->has_error() )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString(T_('There are some errors'), $tr_status->errors) ));
      $tform->add_empty_row();
   }

   $tform->add_row( array(
         'DESCRIPTION', T_('Current Status#tround'),
         'TEXT',        TournamentRound::getStatusText($tr_status->curr_status) ));
   $tform->add_row( array(
         'TAB',
         'SELECTBOX',    'status', 1, $arr_status, $tround->Status, false,
         'SUBMITBUTTON', 't_save', T_('Change status#tourney'), ));

   if( count($edits) && @$_REQUEST['t_save'] ) // show confirmation
   {
      $confirm_notes = '';
      if( $tr_status->has_error() )
      {
         if( $is_admin && count($status_errors) == 0 )
            $confirm_notes = T_('Confirm only, if you want to change the status regardless of the occured errors!');
      }
      else
      {
         $confirm_notes = T_('Are you still sure you want to change the status?');
         if( !$is_admin )
            $confirm_notes = // role_info comes here (see below)
               T_('Be aware, that only the status changes defined below are possible.')
               . "<br>\n"
               . T_('The status change may not be reversible.')
               . "<br><br>\n"
               . $confirm_notes;
      }

      if( $confirm_notes )
      {
         $role_str = $tourney->build_role_info();
         $tform->add_empty_row();
         $tform->add_row( array(
               'DESCRIPTION', T_('Confirmation notes#tourney'),
               'TEXT', span('ErrorMsg',
                          sprintf( "%s<br>\n%s", $tourney->build_role_info(), $confirm_notes )), ));
         $tform->add_row( array(
               'TAB', 'CELL', 1, '', // align submit-buttons
               'SUBMITBUTTON', 't_confirm', T_('Confirm status change'),
               'TEXT', SMALL_SPACING,
               'SUBMITBUTTON', 't_cancel', T_('Cancel') ));
      }
   }


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();

   echo_notes( 'edittroundstatusTable', T_('Tournament Round status notes'), build_status_notes() );


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   if( $allow_edit_tourney ) # for TD
      $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}


// return ( vars-hash, edits-arr )
function parse_edit_form( &$tround )
{
   $edits = array();
   $is_posted = ( @$_REQUEST['t_save'] || @$_REQUEST['t_confirm'] );

   // read from props or set defaults
   $vars = array(
      'status' => $tround->Status,
   );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // parse URL-vars
   if( $is_posted )
   {
      $tround->setStatus($vars['status'], true/*check-only*/);

      // determine edits
      if( $old_vals['status'] != $vars['status'] ) $edits[] = T_('Status#edits');
   }

   return array( $vars, array_unique($edits) );
}//parse_edit_form

/*! \brief Returns array with notes about tournament-round-status. */
function build_status_notes()
{
   $notes = array();
   //$notes[] = null; // empty line

   $stchfmt = "<b>%s > %s:</b> %s";
   $notes[] = array( T_('Allowed status changes for Tournament Directors with preconditions'),
         sprintf( $stchfmt,
            TournamentRound::getStatusText(TROUND_STATUS_INIT),
            TournamentRound::getStatusText(TROUND_STATUS_POOL),
            T_('Tournament round properties setup must be complete.') ),
         sprintf( $stchfmt,
            TournamentRound::getStatusText(TROUND_STATUS_POOL),
            TournamentRound::getStatusText(TROUND_STATUS_PAIR),
            T_('Pooling for current tournament round must be complete.') ),
         sprintf( $stchfmt,
            TournamentRound::getStatusText(TROUND_STATUS_PAIR),
            TournamentRound::getStatusText(TROUND_STATUS_GAME),
            T_('Pairing for current tournament round must be complete.') ),
         sprintf( $stchfmt,
            TournamentRound::getStatusText(TROUND_STATUS_GAME),
            TournamentRound::getStatusText(TROUND_STATUS_PLAY),
            T_('All tournament-games for current tournament round must be ready to start.') ),
         sprintf( $stchfmt,
            TournamentRound::getStatusText(TROUND_STATUS_PLAY),
            TournamentRound::getStatusText(TROUND_STATUS_DONE),
            T_('All tournament-games for current tournament round must have been finished.') ),
      );
   $notes[] = null;

   $notes[] = array( T_('Reserved Status changes for Tournament Admins'),
                     T_('Tournament Admin can do any status changes.') );
   $notes[] = null;

   $arrst = array();
   $arrst[TROUND_STATUS_INIT] = T_('Tournament round setup phase (set properties needed for pooling, pairing and playing)#trdstat');
   $arrst[TROUND_STATUS_POOL] = T_('Tournament round pooling phase (create and setup pools)#trdstat');
   $arrst[TROUND_STATUS_PAIR] = T_('Tournament round pairing phase (each registered user is assigned to a pool)#trdstat');
   $arrst[TROUND_STATUS_GAME] = T_('Tournament game setup phase (tournament games are prepared)#trdstat');
   $arrst[TROUND_STATUS_PLAY] = T_('Tournament playing phase (tournament game are started and played)#trdstat');
   $arrst[TROUND_STATUS_DONE] = T_('Tournament finalizing phase (prepare next round, announce results, tournament round is finished)#trdstat');
   $narr = array( T_('Tournament Round Status') );
   foreach( $arrst as $status => $descr )
      $narr[] = sprintf( "%s = %s", TournamentRound::getStatusText($status), $descr );
   $notes[] = $narr;

   return $notes;
}//build_status_notes

?>
