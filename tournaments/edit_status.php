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

chdir('..');
require_once( 'include/std_functions.php' );
require_once( 'include/gui_functions.php' );
require_once( 'include/form_functions.php' );
require_once( 'tournaments/include/tournament_utils.php' );
require_once( 'tournaments/include/tournament.php' );
require_once( 'tournaments/include/tournament_status.php' );

$GLOBALS['ThePage'] = new Page('TournamentStatusEdit');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_status');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

/* Actual REQUEST calls used:
     tid=                 : edit status of tournament
     t_save&tid=          : change tournament-status in database (need confirm)
     t_confirm&tid=       : change tournament-status in database (confirmed)
     t_cancel             : cancel status-change-confirmation
*/

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;

   $tstatus = new TournamentStatus( $tid ); // existing tournament?
   $tourney = $tstatus->tourney;

   // create/edit allowed?
   $allow_edit_tourney = $tourney->allow_edit_tournaments($my_id);
   if( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.edit_status.edit_tournament($tid,$my_id)");

   if( @$_REQUEST['t_cancel'] ) // cancel status-change
      jump_to("tournaments/edit_status.php?tid=$tid");

   // init
   $arr_status = Tournament::getStatusText();

   // check + parse edit-form + check status
   list( $vars, $edits ) = parse_edit_form($tourney);

   $new_status = $vars['status'];
   $tstatus->check_status_change($new_status);
   $tourney->setStatus($new_status);

   // save tournament-object with values from edit-form (if no errors and something changed)
   $allow_confirm = ( !$tstatus->has_error() || $tstatus->is_admin );
   if( @$_REQUEST['t_confirm'] && count($edits) && $allow_confirm )
   {
      $tourney->persist();
      jump_to("tournaments/edit_status.php?tid=$tid".URI_AMP
            . "sysmsg=". urlencode(T_('Tournament status saved!')) );
   }

   $page = "edit_status.php";
   $title = T_('Tournament Status Editor');


   // ---------- Tournament Status EDIT form -----------------------

   $tform = new Form( 'tournament', $page, FORM_POST );
   $tform->add_hidden( 'tid', $tid );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Created'),
         'TEXT',        date(DATEFMT_TOURNAMENT, $tourney->Created) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Last changed'),
         'TEXT',        TournamentUtils::buildLastchangedBy($tourney->Lastchanged, $tourney->ChangedBy) ));
   $tform->add_row( array( 'HR' ));

   if( $tstatus->has_error() )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString(T_('There are some errors'), $tstatus->errors) ));
      $tform->add_empty_row();
   }

   $tform->add_row( array(
         'DESCRIPTION', T_('Current Status#tourney'),
         'TEXT',        Tournament::getStatusText($tstatus->curr_status) ));
   $tform->add_row( array(
         'TAB',
         'SELECTBOX',    'status', 1, $arr_status, $tourney->Status, false,
         'SUBMITBUTTON', 't_save', T_('Change status#tourney'), ));

   if( count($edits) && @$_REQUEST['t_save'] ) // show confirmation
   {
      $confirm_notes = '';
      if( $tstatus->has_error() )
      {
         if( $tstatus->is_admin )
            $confirm_notes = T_('Confirm only, if you want to change the status regardless of the occured errors!');
      }
      else
      {
         $confirm_notes = T_('Are you still sure you want to change the status?');
         if( !$tstatus->is_admin )
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

   echo_notes( 'edittourneystatusTable', T_('Tournament status notes'), build_status_notes() );


   $menu_array = array();
   if( $tid )
      $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   if( $allow_edit_tourney ) # for TD
      $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}


// return ( vars-hash, edits-arr )
function parse_edit_form( $tourney )
{
   $edits = array();
   $is_posted = ( @$_REQUEST['t_save'] || @$_REQUEST['t_confirm'] );

   // read from props or set defaults
   $vars = array(
      'status' => $tourney->Status,
   );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // parse URL-vars
   if( $is_posted )
   {
      $tourney->setStatus($vars['status'], true/*check-only*/);

      // determine edits
      if( $old_vals['status'] != $vars['status'] ) $edits[] = T_('Status#edits');
   }

   return array( $vars, array_unique($edits) );
}//parse_edit_form

/*! \brief Returns array with notes about tournament-status. */
function build_status_notes()
{
   $notes = array();
   //$notes[] = null; // empty line

   $stchfmt = "<b>%s > %s:</b> %s";
   $notes[] = array( T_('Allowed status changes for Tournament Directors with preconditions'),
         sprintf( $stchfmt,
            Tournament::getStatusText(TOURNEY_STATUS_NEW),
            Tournament::getStatusText(TOURNEY_STATUS_REGISTER),
            T_('Tournament setup must be complete.') ),
         sprintf( $stchfmt,
            Tournament::getStatusText(TOURNEY_STATUS_REGISTER),
            Tournament::getStatusText(TOURNEY_STATUS_PAIR),
            T_('Tournament registration conditions must be fulfilled.') ),
         sprintf( $stchfmt,
            Tournament::getStatusText(TOURNEY_STATUS_PAIR),
            Tournament::getStatusText(TOURNEY_STATUS_PLAY),
            T_('All tournament-games must be ready to start after setup.') ),
         sprintf( $stchfmt,
            Tournament::getStatusText(TOURNEY_STATUS_PLAY),
            Tournament::getStatusText(TOURNEY_STATUS_CLOSED),
            T_('All tournament-games must have been finished or not have started yet.') ),
         sprintf( "<b>* > %s:</b> %s",
            Tournament::getStatusText(TOURNEY_STATUS_DELETE),
            T_('All tournament-games must have been finished or not have started yet.') ),
      );
   $notes[] = null;

   $notes[] = array( T_('Reserved Status changes for Tournament Admins'),
         sprintf( "<b>* > %s > *%s%s > *:</b><br>\n%s",
            Tournament::getStatusText(TOURNEY_STATUS_ADMIN),
            SEP_SPACING,
            Tournament::getStatusText(TOURNEY_STATUS_DELETE),
            T_('Tournament Admin can do any status changes and undelete tournaments.') ),
      );
   $notes[] = null;

   $arrst = array();
   $arrst[TOURNEY_STATUS_NEW]      = T_('Tournament setup phase (adding infos, properties and rules)#tstat_new');
   $arrst[TOURNEY_STATUS_REGISTER] = T_('Tournament registration phase (users can register or be invited)#tstat_reg');
   $arrst[TOURNEY_STATUS_PAIR]     = T_('Tournament pairing phase (preparing and setting up tournament games)#tstat_pair');
   $arrst[TOURNEY_STATUS_PLAY]     = T_('Tournament play phase (tournament participants are playing)#tstat_play');
   $arrst[TOURNEY_STATUS_CLOSED]   = T_('Tournament finalizing phase (results are announced, tournament is finished)#tstat_closed');
   $arrst[TOURNEY_STATUS_ADMIN]    = T_('Tournament admin phase managed only by tournament admin (hidden, archived tournaments)#tstat_admin');
   $arrst[TOURNEY_STATUS_DELETE]   = T_('Tournament delete phase managed only by tournament admin (tournament ready for deletion)#tstat_del');
   $narr = array( T_('Tournament status') );
   foreach( $arrst as $status => $descr )
      $narr[] = sprintf( "%s = %s", Tournament::getStatusText($status), $descr );
   $notes[] = $narr;

   return $notes;
}//build_status_notes

?>
