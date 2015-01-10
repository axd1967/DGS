<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'include/message_functions.php';
require_once 'include/db/shape.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_log_helper.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_rules.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentRulesEdit');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.edit_rules');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_rules');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'Tournament.edit_rules');

/* Actual REQUEST calls used:
     tid=                : add new or edit tournament rules
     tr_preview&tid=     : preview for tournament-rules-save
     tr_save&tid=        : update (replace) tournament-rules in database
*/

   $tid = (int) @$_REQUEST['tid'];
   if ( $tid < 0 ) $tid = 0;

   $tourney = TournamentCache::load_cache_tournament( 'Tournament.edit_rules.find_tournament', $tid );
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   $t_limits = $ttype->getTournamentLimits();

   // create/edit allowed?
   $allow_edit_tourney = TournamentHelper::allow_edit_tournaments($tourney, $my_id);
   if ( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.edit_rules.edit_tournament($tid,$my_id)");

   $trule = TournamentCache::load_cache_tournament_rules( 'Tournament.edit_rules', $tid );
   $trule->TourneyType = $tourney->Type; // for parsing rules

   $errors = $tstatus->check_edit_status( TournamentRules::get_edit_tournament_status() );
   if ( !TournamentUtils::isAdmin() && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();

   // check + parse edit-form (notes)
   $old_trule = clone $trule;
   list( $vars, $edits, $input_errors, $gsc ) = parse_edit_form( $trule, $t_limits );
   $errors = array_merge( $errors, $input_errors );

   // check (if Rated=Yes) that ALL existing TPs have a user-rating (can happen by admin-ops)
   if ( $trule->Rated && !TournamentParticipant::check_rated_tournament_participants($tid) )
      $errors[] = T_('There are users without a rating, which conflicts with a "rated" tournament.');

   // save tournament-rules-object with values from edit-form
   if ( @$_REQUEST['tr_save'] && !@$_REQUEST['tr_preview'] && count($errors) == 0 )
   {
      $trule->update();
      TournamentLogHelper::log_change_tournament_rules( $tid, $allow_edit_tourney, $edits, $old_trule, $trule );

      jump_to("tournaments/edit_rules.php?tid={$tid}".URI_AMP
            . "sysmsg=". urlencode(T_('Tournament Rules saved!')) );
   }

   $page = "edit_rules.php";
   $title = T_('Tournament Rules Editor');


   // --------------- Tournament-Rules EDIT form --------------------

   $trform = new Form( 'tournament', $page, FORM_POST );

   $trform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   TournamentUtils::show_tournament_flags( $trform, $tourney );
   if ( $trule->Lastchanged )
      $trform->add_row( array(
            'DESCRIPTION', T_('Last changed'),
            'TEXT',        TournamentUtils::buildLastchangedBy($trule->Lastchanged, $trule->ChangedBy) ));
   $trform->add_row( array( 'HR' ));

   if ( count($errors) )
   {
      $trform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
   }

   $formstyle = ($tourney->Type == TOURNEY_TYPE_LADDER) ? GSET_TOURNAMENT_LADDER : GSET_TOURNAMENT_ROUNDROBIN;
   game_settings_form( $trform, $formstyle, GSETVIEW_STANDARD, true/*$iamrated*/, 'redraw', $vars, null, $gsc );

   $trform->add_empty_row();
   $trform->add_row( array(
         'DESCRIPTION', T_('Notes'),
         'TEXTAREA',    '_tr_notes', 70, 6, $vars['_tr_notes'] ));

   $trform->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT',        span('TWarning', implode(', ', $edits), '[%s]'), ));

   $trform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'tr_save', T_('Save Tournament Rules'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tr_preview', T_('Preview'),
      ));

   if ( @$_REQUEST['tr_preview'] || $trule->Notes != '' )
   {
      $trform->add_row( array(
            'DESCRIPTION', T_('Preview Notes'),
            'TEXT', make_html_safe( $trule->Notes, true ) ));
   }

   $trform->add_hidden( 'tid', $tid );


   start_page( $title, true, $logged_in, $player_row, null, null, build_game_settings_javascript() );
   echo "<h3 class=Header>$title</h3>\n";

   $trform->echo_string();


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}//main


// return [ vars-hash, edits-arr, errorlist, GameSetupChecker ]
function parse_edit_form( &$trule, $t_limits )
{
   $edits = array();
   $errors = array();
   $is_posted = ( @$_REQUEST['tr_save'] || @$_REQUEST['tr_preview'] );

   if ( $is_posted )
   {
      $gsc = GameSetupChecker::check_fields( GSC_VIEW_TRULES );
      if ( $gsc->has_errors() )
         $errors = array_merge( $errors, $gsc->get_errors() );
   }
   else
      $gsc = null;

   // read from DB or set defaults
   $vars = array();
   $trule->convertTournamentRules_to_EditForm( $vars );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach ( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );
   // handle checkboxes having no key/val in _POST-hash
   if ( $is_posted )
   {
      foreach ( array( 'stdhandicap', 'weekendclock', 'rated' ) as $key )
         $vars[$key] = get_request_arg( $key, false );
   }

   // parse URL-vars
   if ( $is_posted )
   {
      $allow_rated = !$t_limits->getMinLimit(TLIMITS_TRULE_GAME_UNRATED);
      $trule->convertEditForm_to_TournamentRules( $vars, $errors, $allow_rated );
      if ( $trule->ShapeSnapshot ) // refresh loaded-shape-snapshot
         $vars['snapshot'] = $trule->ShapeSnapshot;

      // determine edits
      $tr_cat_htype = get_category_handicaptype(strtolower($trule->Handicaptype));
      if ( $old_vals['size'] != $trule->Size ) $edits[] = T_('Board Size');
      if ( $old_vals['cat_htype'] != $tr_cat_htype )
         $edits[] = T_('Handicap Type');
      elseif ( $tr_cat_htype == CAT_HTYPE_MANUAL )
      {
         if ( $old_vals['color_m'] != $vars['color_m'] ) $edits[] = T_('Color');
         if ( $old_vals['handicap_m'] != $trule->Handicap ) $edits[] = T_('Handicap');
         if ( $old_vals['komi_m'] != $trule->Komi ) $edits[] = T_('Komi');
      }

      if ( ($old_vals['adj_komi'] != $trule->AdjKomi) || ( $old_vals['jigo_mode'] != $trule->JigoMode ) )
         $edits[] = T_('Adjust Komi');
      if ( ($old_vals['adj_handicap'] != $trule->AdjHandicap)
            || ( $old_vals['min_handicap'] != $trule->MinHandicap )
            || ( $old_vals['max_handicap'] != $trule->MaxHandicap ) )
         $edits[] = T_('Adjust Handicap');
      if ( getBool($old_vals['stdhandicap']) != getBool($trule->StdHandicap) )
         $edits[] = T_('Standard placement');

      list( $old_hours, $old_byohours, $old_byoperiods, $time_errors, $time_errfields ) =
         TournamentRules::convertFormTimeSettings( $old_vals );
      if ( count($time_errors) )
         $errors = array_merge( $errors, $time_errors );
      if ( ($old_vals['byoyomitype'] != $trule->Byotype)
            || ($old_hours != $trule->Maintime)
            || ($old_byohours != $trule->Byotime)
            || ($trule->Byotype == BYOTYPE_FISCHER && $old_byoperiods != $trule->Byoperiods) )
         $edits[] = T_('Time settings');

      if ( getBool($old_vals['weekendclock']) != getBool($trule->WeekendClock) )
         $edits[] = T_('Weekend Clock');
      if ( getBool($old_vals['rated']) != getBool($trule->Rated) ) $edits[] = T_('Rated');
      if ( $old_vals['_tr_notes'] != $trule->Notes ) $edits[] = T_('Notes');
      if ( ($old_vals['shape'] != $trule->ShapeID) || ($old_vals['snapshot'] != $trule->ShapeSnapshot) )
         $edits[] = T_('Shape');
   }

   return array( $vars, array_unique($edits), $errors, $gsc );
}//parse_edit_form

// return true|false from val (Y|N|bool|int|str|null)
function getBool( $val )
{
   if ( is_string($val) ) return ( $val == 'Y' );
   if ( is_numeric($val) ) return ( $val != 0 );
   if ( is_null($val) ) return false;
   return (bool)$val;
}

?>
