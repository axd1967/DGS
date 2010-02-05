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
require_once 'tournaments/include/tournament_utils.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_ladder_props.php';

$GLOBALS['ThePage'] = new Page('TournamentLadderPropsEdit');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'TournamentLadderProps.edit_props');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $page = "edit_props.php";

/* Actual REQUEST calls used:
     tid=                 : edit ladder properties
     tlp_preview&tid=     : preview for properties-save
     tlp_save&tid=        : update (replace) properties in database
*/

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;

   $tourney = Tournament::load_tournament( $tid ); // existing tournament ?
   if( is_null($tourney) )
      error('unknown_tournament', "TournamentLadderProps.edit_props.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );

   // create/edit allowed?
   if( !$tourney->allow_edit_tournaments($my_id) )
      error('tournament_edit_not_allowed', "TournamentLadderProps.edit_props.edit_tournament($tid,$my_id)");

   $tl_props = TournamentLadderProps::load_tournament_ladder_props( $tid );
   if( is_null($tl_props) )
      error('bad_tournament', "TournamentLadderProps.edit_props.miss_properties($tid,$my_id)");

   // init
   $errors = $tstatus->check_edit_status( TournamentLadderProps::get_edit_tournament_status() );

   // check + parse edit-form
   list( $vars, $edits, $input_errors ) = parse_edit_form( $tl_props );
   $errors = array_merge( $errors, $input_errors );
   $errors = array_merge( $errors, $tl_props->check_properties() );

   // save properties-object with values from edit-form
   if( @$_REQUEST['tlp_save'] && !@$_REQUEST['tlp_preview'] && count($edits) && count($errors) == 0 )
   {
      $tl_props->update();
      jump_to("tournaments/ladder/edit_props.php?tid={$tid}".URI_AMP
            . "sysmsg=". urlencode(T_('Tournament Ladder Properties saved!')) );
   }


   // ---------- Tournament-Ladder-Properties EDIT form --------------------

   $tform = new Form( 'tournament', $page, FORM_POST );
   $tform->add_hidden( 'tid', $tid );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   if( $tl_props->Lastchanged )
      $tform->add_row( array(
            'DESCRIPTION', T_('Last changed'),
            'TEXT',        TournamentUtils::buildLastchangedBy($tl_props->Lastchanged, $tl_props->ChangedBy) ));
   $tform->add_row( array( 'HR' ));

   if( count($errors) )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString(T_('There are some errors'), $errors) ));
      $tform->add_empty_row();
   }

   $tform->add_row( array(
         'DESCRIPTION', T_('Challenge Range Absolute'),
         'TEXTINPUT',   'chall_range_abs', 5, 5, $vars['chall_range_abs'], '', ));
   $tform->add_empty_row();

   $tform->add_row( array(
         'DESCRIPTION', T_('Max. Defenses'),
         'TEXT',        T_('Start Rank') . ': ',
         'TEXTINPUT',   'max_def_start1', 3, 3, $vars['max_def_start1'], '',
         'TEXT',        MED_SPACING . T_('Max. Defenses') . ': ',
         'TEXTINPUT',   'max_def1', 3, 3, $vars['max_def1'], '',
         'TEXT',        T_('(Group #1)'), ));
   $tform->add_row( array(
         'TAB',
         'TEXT',        T_('Start Rank') . ': ',
         'TEXTINPUT',   'max_def_start2', 3, 3, $vars['max_def_start2'], '',
         'TEXT',        MED_SPACING . T_('Max. Defenses') . ': ',
         'TEXTINPUT',   'max_def2', 3, 3, $vars['max_def2'], '',
         'TEXT',        T_('(Group #2)'), ));
   $tform->add_row( array(
         'TAB',
         'TEXT',        T_('For remaining ranks restrict max. defenses to') . ': ',
         'TEXTINPUT',   'max_def', 3, 3, $vars['max_def'], '', ));
   $tform->add_empty_row();

   $tform->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT',        span('TWarning', implode(', ', $edits), '[%s]'), ));

   $tform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'tlp_save', T_('Save Ladder properties'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tlp_preview', T_('Preview'),
      ));


   $title = T_('Tournament Ladder Properties Editor');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();

   $tt_notes = $tl_props->build_notes_props();
   echo "<p></p>\n";
   section( 'preview', T_('Preview Properties') );
   echo_notes( 'ttprops', $tt_notes[0], $tt_notes[1], false );


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}


// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$tlp )
{
   $edits = array();
   $errors = array();
   $is_posted = ( @$_REQUEST['tlp_save'] || @$_REQUEST['tlp_preview'] );

   // read from props or set defaults
   $vars = array(
      'chall_range_abs' => $tlp->ChallengeRangeAbsolute,
      'max_def'         => $tlp->MaxDefenses,
      'max_def1'        => $tlp->MaxDefenses1,
      'max_def2'        => $tlp->MaxDefenses2,
      'max_def_start1'  => $tlp->MaxDefensesStart1,
      'max_def_start2'  => $tlp->MaxDefensesStart2,
   );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // parse URL-vars
   if( $is_posted )
   {
      $new_value = $vars['chall_range_abs'];
      $max_value = 1000;
      if( TournamentUtils::isNumberOrEmpty($new_value, true) && $new_value >= -1 )
         $tlp->ChallengeRangeAbsolute = limit( $new_value, -1, $max_value, 10 );
      else
         $errors[] = sprintf( T_('Expecting number for absolute challenge range in range [-1..%s]'), $max_value );


      $new_value = $vars['max_def'];
      if( TournamentUtils::isNumberOrEmpty($new_value) )
         $tlp->MaxDefenses = $new_value;
      else
         $errors[] = sprintf( T_('Expecting number for max. defenses in range [1..%s]'), TLADDER_MAX_DEFENSES );

      $new_value = $vars['max_def1'];
      if( TournamentUtils::isNumberOrEmpty($new_value) )
         $tlp->MaxDefenses1 = $new_value;
      else
         $errors[] = sprintf( T_('Expecting number for max. defenses of group #%s in range [0..%s]'), 1, TLADDER_MAX_DEFENSES );

      $new_value = $vars['max_def2'];
      if( TournamentUtils::isNumberOrEmpty($new_value) )
         $tlp->MaxDefenses2 = $new_value;
      else
         $errors[] = sprintf( T_('Expecting number for max. defenses of group #%s in range [0..%s]'), 2, TLADDER_MAX_DEFENSES );

      $new_value = $vars['max_def_start1'];
      if( TournamentUtils::isNumberOrEmpty($new_value) )
         $tlp->MaxDefensesStart1 = $new_value;
      else
         $errors[] = sprintf( T_('Expecting number for max. defenses start-rank of group #%s'), 1 );

      $new_value = $vars['max_def_start2'];
      if( TournamentUtils::isNumberOrEmpty($new_value) )
         $tlp->MaxDefensesStart2 = $new_value;
      else
         $errors[] = sprintf( T_('Expecting number for max. defenses start-rank of group #%s'), 2 );


      // determine edits
      if( $old_vals['chall_range_abs'] != $tlp->ChallengeRangeAbsolute ) $edits[] = T_('ChallengeRange#edits');
      if( $old_vals['max_def'] != $tlp->MaxDefenses ) $edits[] = T_('MaxDefenses#edits');
      if( $old_vals['max_def1'] != $tlp->MaxDefenses1 ) $edits[] = T_('MaxDefenses#edits');
      if( $old_vals['max_def2'] != $tlp->MaxDefenses2 ) $edits[] = T_('MaxDefenses#edits');
      if( $old_vals['max_def_start1'] != $tlp->MaxDefensesStart1 ) $edits[] = T_('MaxDefenses#edits');
      if( $old_vals['max_def_start2'] != $tlp->MaxDefensesStart2 ) $edits[] = T_('MaxDefenses#edits');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form
?>
