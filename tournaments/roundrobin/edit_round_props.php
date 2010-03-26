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
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_rules.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentRoundEdit');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_round_props');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

/* Actual REQUEST calls used:
     tid=&round=              : edit tournament round
     tr_preview&tid=&round=   : preview for tournament-round-save
     tr_save&tid=&round=      : update (replace) tournament-round in database
*/

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;
   $round = (int) @$_REQUEST['round'];
   if( $round < 0 ) $round = 0;

   $tourney = Tournament::load_tournament( $tid ); // existing tournament ?
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.edit_round_props.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   if( !$ttype->need_rounds )
      error('tournament_edit_rounds_not_allowed', "Tournament.edit_round_props.need_rounds($tid)");

   // create/edit allowed?
   if( !$tourney->allow_edit_tournaments($my_id) )
      error('tournament_edit_not_allowed', "Tournament.edit_round_props.edit_tournament($tid,$my_id)");

   // load existing T-round
   $tround = TournamentRound::load_tournament_round( $tid, $round );
   if( is_null($tround) )
      error('bad_tournament', "Tournament.edit_round_props.find_tournament_round($tid,$round,$my_id)");

   // init
   $errors = $tstatus->check_edit_status( TournamentRound::get_edit_tournament_status() );
   if( !TournamentUtils::isAdmin() && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();

   // check + parse edit-form (notes)
   list( $vars, $edits, $input_errors ) = parse_edit_form( $tround );
   $errors = array_merge( $errors, $input_errors );

   // save tournament-round-object with values from edit-form
   if( @$_REQUEST['tr_save'] && !@$_REQUEST['tr_preview'] && count($errors) == 0 )
   {
      $tround->persist(); // insert or update
      jump_to("tournaments/roundrobin/edit_round_props.php?tid={$tid}".URI_AMP."round={$round}".URI_AMP
            . "sysmsg=". urlencode(T_('Tournament round saved!')) );
   }

   $page = "edit_round_props.php";
   $title = T_('Tournament Round Properties Editor');


   // --------------- Tournament-Round EDIT form --------------------

   $trform = new Form( 'tournament', $page, FORM_GET );
   $trform->add_hidden( 'tid', $tid );
   $trform->add_hidden( 'round', $round );

   $trform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   $trform->add_row( array(
         'DESCRIPTION', T_('Tournament Round'),
         'TEXT',        $tround->Round, ));
   $trform->add_row( array(
         'DESCRIPTION', T_('Last changed'),
         'TEXT',        TournamentUtils::buildLastchangedBy($tround->Lastchanged, $tround->ChangedBy) ));
   TournamentUtils::show_tournament_flags( $trform, $tourney );
   $trform->add_row( array(
         'DESCRIPTION', T_('Round Status'),
         'TEXT',        TournamentRound::getStatusText($tround->Status), ));
   $trform->add_row( array(
         'DESCRIPTION', T_('Pool Count'),
         'TEXT',        $tround->PoolCount, ));
   $trform->add_row( array( 'HR' ));

   if( count($errors) )
   {
      $trform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString(T_('There are some errors'), $errors) ));
      $trform->add_empty_row();
   }

   $trform->add_row( array(
         'DESCRIPTION', T_('Pool Size'),
         'TEXT',        T_('min.#TRD_poolsize') . MINI_SPACING,
         'TEXTINPUT',   'min_pool_size', 5, 5, $tround->MinPoolSize,
         'TEXT',        SMALL_SPACING . T_('max.#TRD_poolsize') . MINI_SPACING,
         'TEXTINPUT',   'min_pool_size', 5, 5, $tround->MinPoolSize, ));

   $trform->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT',        span('TWarning', implode(', ', $edits), '[%s]'), ));

   $trform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'tr_save', T_('Save tournament round'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tr_preview', T_('Preview'),
      ));


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $trform->echo_string();

   echo_notes( 'edittournamentroundnotesTable', T_('Tournament round notes'), build_round_notes() );


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Edit rounds')] =
         array( 'url' => "tournaments/roundrobin/edit_rounds.php?tid=$tid".URI_AMP."round=$round", 'class' => 'TAdmin' );
   $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}

// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$trd )
{
   $edits = array();
   $errors = array();

   // read from props or set defaults
   $vars = array(
      'min_pool_size'   => $trd->MinPoolSize,
      'max_pool_size'   => $trd->MaxPoolSize,
   );

   // copy to determine edit-changes
   $old_vals = array_merge( array(), $vars );
   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // parse URL-vars
   if( @$_REQUEST['tr_save'] || @$_REQUEST['tr_preview'] )
   {
      $new_value = $vars['min_pool_size'];
      if( TournamentUtils::isNumberOrEmpty($new_value) )
         $trd->MinPoolSize = limit( $new_value, 0, 999, 0 );
      else
         $errors[] = T_('Expecting positive number for minimum pool size');

      $new_value = $vars['max_pool_size'];
      if( TournamentUtils::isNumberOrEmpty($new_value) )
         $trd->MaxPoolSize = limit( $new_value, 0, 999, 0 );
      else
         $errors[] = T_('Expecting positive number for maximum pool size');

      // determine edits
      if( $old_vals['min_pool_size'] != $trd->MinPoolSize ) $edits[] = T_('Pool-Size#edits');
      if( $old_vals['max_pool_size'] != $trd->MaxPoolSize ) $edits[] = T_('Pool-Size#edits');
   }

   if( $trd->MinPoolSize > $trd->MaxPoolSize )
      swap( $trd->MinPoolSize, $trd->MaxPoolSize );

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form


/*! \brief Returns array with notes about tournament-round. */
function build_round_notes()
{
   $notes = array();
   //$notes[] = null; // empty line

   $arrst = array();
   $arrst[TROUND_STATUS_INIT] = T_('start and setup new tournament round#trdstat');
   $arrst[TROUND_STATUS_POOL] = T_('create and setup pools for tournament round#trdstat');
   $arrst[TROUND_STATUS_PAIR] = T_('assign participants to pools#trdstat');
   $arrst[TROUND_STATUS_GAME] = T_('prepare tournament games, ready to be started#trdstat');
   $arrst[TROUND_STATUS_DONE] = T_('pairing for tournament round finished, playing can start#trdstat');
   $narr = array ( T_('Tournament Round Status') );
   foreach( $arrst as $status => $descr )
      $narr[] = sprintf( "%s = $descr", TournamentRound::getStatusText($status) );
   $notes[] = $narr;

   return $notes;
}//build_round_notes

?>
