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
require_once( 'tournaments/include/tournament.php' );
require_once( 'tournaments/include/tournament_rules.php' );
require_once( 'include/message_functions.php' );
require_once( 'tournaments/include/tournament_status.php' );

$GLOBALS['ThePage'] = new Page('TournamentRulesEdit');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_rules');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

/* Actual REQUEST calls used:
     tid=                : add new or edit tournament rules
     tr_preview&tid=     : preview for tournament-rules-save
     tr_save&tid=        : update (replace) tournament-rules in database
*/

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;

   $tourney = Tournament::load_tournament( $tid ); // existing tournament ?
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.edit_rules.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );
   //TODO(later) if Rated=Yes, check that ALL users have a rating

   // create/edit allowed?
   if( !$tourney->allow_edit_tournaments($my_id) )
      error('tournament_edit_not_allowed', "Tournament.edit_rules.edit_tournament($tid,$my_id)");

   $trule = TournamentRules::load_tournament_rule( $tid );
   if( is_null($trule) )
      error('bad_tournament', "Tournament.edit_rules.miss_rules($tid,$my_id)");

   // check + parse edit-form (notes)
   list( $vars, $edits, $errorlist ) = parse_edit_form( $trule );
   $errorlist += $tstatus->check_edit_status( TournamentRules::get_edit_tournament_status() );

   // save tournament-rules-object with values from edit-form
   if( @$_REQUEST['tr_save'] && !@$_REQUEST['tr_preview'] && count($errorlist) == 0 )
   {
      $trule->persist();
      jump_to("tournaments/edit_rules.php?tid={$tid}".URI_AMP
            . "sysmsg=". urlencode(T_('Tournament rules saved!')) );
   }

   $page = "edit_rules.php";
   $title = T_('Tournament Rules Editor');


   // --------------- Tournament-Rules EDIT form --------------------

   $trform = new Form( 'tournament', $page, FORM_POST );

   $trform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   if( $trule->Lastchanged )
      $trform->add_row( array(
            'DESCRIPTION', T_('Last changed'),
            'TEXT',        TournamentUtils::buildLastchangedBy($trule->Lastchanged, $trule->ChangedBy) ));
   $trform->add_row( array( 'HR' ));

   if( count($errorlist) )
   {
      $trform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString(T_('There are some errors'), $errorlist) ));
   }

   game_settings_form( $trform, GSET_TOURNAMENT, true/*$iamrated*/, 'redraw', $vars );

   $trform->add_empty_row();
   $trform->add_row( array(
         'DESCRIPTION', T_('Notes'),
         'TEXTAREA',    '_tr_notes', 70, 6, $vars['_tr_notes'] ));

   $trform->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT',        span('TWarning', implode(', ', $edits), '[%s]'), ));

   $trform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'tr_save', T_('Save tournament rules'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tr_preview', T_('Preview'),
      ));

   if( @$_REQUEST['tr_preview'] || $trule->Notes != '' )
   {
      $trform->add_row( array(
            'DESCRIPTION', T_('Preview Notes'),
            'TEXT', make_html_safe( $trule->Notes, true ) ));
   }

   $trform->add_hidden( 'tid', $tid );


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $trform->echo_string();


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}

// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$trule )
{
   $edits = array();
   $errors = array();
   $is_posted = ( @$_REQUEST['tr_save'] || @$_REQUEST['tr_preview'] );

   // read from DB or set defaults
   $vars = array();
   $trule->convertTournamentRules_to_EditForm( $vars );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );
   // handle checkboxes having no key/val in _POST-hash
   if( $is_posted )
   {
      foreach( array( 'stdhandicap', 'weekendclock', 'rated' ) as $key )
         $vars[$key] = get_request_arg( $key, false );
   }

   // parse URL-vars
   if( $is_posted )
   {
      $trule->convertEditForm_to_TournamentRules( $vars, $errors );

      // determine edits
      $tr_cat_htype = get_category_handicaptype(strtolower($trule->Handicaptype));
      if( $old_vals['size'] != $trule->Size ) $edits[] = T_('Board size#edits');
      if( $old_vals['cat_htype'] != $tr_cat_htype )
         $edits[] = T_('Handicap type#edits');
      elseif( $tr_cat_htype == CAT_HTYPE_MANUAL )
      {
         if( $old_vals['handicap_m'] != $trule->Handicap ) $edits[] = T_('Handicap#edits');
         if( $old_vals['komi_m'] != $trule->Komi ) $edits[] = T_('Komi#edits');
      }

      if( ($old_vals['adj_komi'] != $trule->AdjKomi) || ( $old_vals['jigo_mode'] != $trule->JigoMode ) )
         $edits[] = T_('Adjust Komi#edits');
      if( ($old_vals['adj_handicap'] != $trule->AdjHandicap)
            || ( $old_vals['min_handicap'] != $trule->MinHandicap )
            || ( $old_vals['max_handicap'] != $trule->MaxHandicap ) )
         $edits[] = T_('Adjust Handicap#edits');
      if( getBool($old_vals['stdhandicap']) != getBool($trule->StdHandicap) )
         $edits[] = T_('Standard placement#edits');

      list($old_hours, $old_byohours, $old_byoperiods) =
         TournamentRules::convertFormTimeSettings( $old_vals );
      if( ($old_vals['byoyomitype'] != $trule->Byotype)
            || ($old_hours != $trule->Maintime)
            || ($old_byohours != $trule->Byotime)
            || ($trule->Byotype == BYOTYPE_FISCHER && $old_byoperiods != $trule->Byoperiods) )
         $edits[] = T_('Time settings#edits');

      if( getBool($old_vals['weekendclock']) != getBool($trule->WeekendClock) )
         $edits[] = T_('Weekend clock#edits');
      if( getBool($old_vals['rated']) != getBool($trule->Rated) ) $edits[] = T_('Rated#edits');
      if( $old_vals['_tr_notes'] != $trule->Notes ) $edits[] = T_('Notes#edits');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form

// return true|false from val (Y|N|bool|int|str|null)
function getBool( $val )
{
   if( is_string($val) ) return ( $val == 'Y' );
   if( is_numeric($val) ) return ( $val != 0 );
   if( is_null($val) ) return false;
   return (bool)$val;
}

?>
