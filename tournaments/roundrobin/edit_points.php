<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/table_columns.php';
require_once 'include/rating.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_log_helper.php';
require_once 'tournaments/include/tournament_points.php';
require_once 'tournaments/include/tournament_pool_classes.php';
require_once 'tournaments/include/tournament_rules.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentPointsEdit');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.edit_points');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_points');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'Tournament.edit_points');

/* Actual REQUEST calls used:
     tid=                     : edit tournament points
     tpts_preview&tid=&cpX=   : preview for points-save (with custom-points cpX, X=1..3)
     tpts_save&tid=           : update (replace) points in database
     tpts_reset&tid=          : reset to defaults for selected 'points_type'
*/

   $tid = (int) @$_REQUEST['tid'];
   if ( $tid < 0 ) $tid = 0;

   $tourney = TournamentCache::load_cache_tournament( 'Tournament.edit_points.find_tournament', $tid );
   $tstatus = new TournamentStatus( $tourney );

   // create/edit allowed?
   $allow_edit_tourney = TournamentHelper::allow_edit_tournaments($tourney, $my_id);
   if ( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.edit_points.edit_tournament($tid,$my_id)");

   $tpoints = TournamentCache::load_cache_tournament_points( 'Tournament.edit_points', $tid );

   // init
   $errors = $tstatus->check_edit_status( TournamentPoints::get_edit_tournament_status() );
   if ( !TournamentUtils::isAdmin() && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();
   $arr_points_types = TournamentPoints::getPointsTypeText();

   // check + parse edit-form
   $old_tpoints = clone $tpoints;
   if ( @$_REQUEST['tpts_reset'] )
      $tpoints->setDefaults( @$_REQUEST['points_type'] );

   list( $vars, $edits, $input_errors ) = parse_edit_form( $tpoints );
   $errors = array_merge( $errors, $input_errors );

   // save tournament-points-object with values from edit-form
   if ( @$_REQUEST['tpts_save'] && !@$_REQUEST['tpts_preview'] && count($errors) == 0 )
   {
      $tpoints->update();
      TournamentLogHelper::log_change_tournament_points( $tid, $allow_edit_tourney, $edits, $old_tpoints, $tpoints );

      jump_to("tournaments/roundrobin/edit_points.php?tid={$tid}".URI_AMP
            . "sysmsg=". urlencode(T_('Tournament Points saved!')) );
   }

   $page = "edit_points.php";
   $title = T_('Tournament Points Editor');


   // ---------- Tournament-Points EDIT form ------------------------

   $tform = new Form( 'tournament', $page, FORM_GET );
   $tform->set_layout( FLAYOUT_GLOBAL, '1,(2|3),4' );
   $tform->add_hidden( 'tid', $tid );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   TournamentUtils::show_tournament_flags( $tform, $tourney );
   if ( $tpoints->Lastchanged )
      $tform->add_row( array(
            'DESCRIPTION', T_('Last changed'),
            'TEXT',        TournamentUtils::buildLastchangedBy($tpoints->Lastchanged, $tpoints->ChangedBy) ));
   $tform->add_row( array( 'HR' ));

   if ( count($errors) )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $tform->add_empty_row();
   }


   $text_winner = T_('points for winner#tpoints');
   $text_loser  = T_('points for loser#tpoints');
   $text_both   = T_('points for both players#tpoints');
   $tform->set_area( 2 );
   $tform->add_row( array(
         'DESCRIPTION', T_('Points Type#tourney'),
         'SELECTBOX',   'points_type', 1, $arr_points_types, $vars['points_type'], false, ));

   $tform->add_empty_row();
   $tform->add_row( array( 'CHAPTER', sprintf( T_('only for points-type [%s]#tourney'),
         TournamentPoints::getPointsTypeText(TPOINTSTYPE_SIMPLE)), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Points Won#tourney'),
         'TEXTINPUT',   'points_won', 5, 5, $vars['points_won'],
         'TEXT',        $text_winner, ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Points Lost#tourney'),
         'TEXTINPUT',   'points_lost', 5, 5, $vars['points_lost'],
         'TEXT',        $text_loser, ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Points Draw#tourney'),
         'TEXTINPUT',   'points_draw', 5, 5, $vars['points_draw'],
         'TEXT',        $text_both, ));

   $tform->add_empty_row();
   $tform->add_row( array( 'CHAPTER', sprintf( T_('only for points-type [%s]#tourney'),
         TournamentPoints::getPointsTypeText(TPOINTSTYPE_HAHN)), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Score Block#tourney'),
         'TEXTINPUT',   'score_block', 5, 5, $vars['score_block'], ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Max. Points#tourney'),
         'TEXTINPUT',   'max_points', 5, 5, $vars['max_points'], ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Share Max. Points#tourney'),
         'CHECKBOX',    'flag_share_maxp', '1', '', $vars['flag_share_maxp'], ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Allow Negative Points#tourney'),
         'CHECKBOX',    'flag_neg_points', '1', '', $vars['flag_neg_points'], ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Points Resignation#tourney'),
         'TEXTINPUT',   'points_resign', 5, 5, $vars['points_resign'],
         'TEXT',        $text_winner, ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Points Timeout#tourney'),
         'TEXTINPUT',   'points_timeout', 5, 5, $vars['points_timeout'],
         'TEXT',        $text_winner, ));

   $tform->add_empty_row();
   $tform->add_row( array( 'CHAPTER', T_('for all points-types#tourney'), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Points Forfeit#tourney'),
         'TEXTINPUT',   'points_forfeit', 5, 5, $vars['points_forfeit'],
         'TEXT',        $text_winner, ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Points No-Result#tourney'),
         'TEXTINPUT',   'points_no_res', 5, 5, $vars['points_no_res'],
         'TEXT',        $text_both, ));


   $tform->set_area( 4 );
   $tform->add_row( array( 'HR' ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT',        span('TWarning', implode(', ', $edits), '[%s]'), ));

   $tform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'tpts_save', T_('Save Tournament Points'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tpts_preview', T_('Preview'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tpts_reset', T_('Reset to Defaults'),
      ));


   $examples_table = build_points_examples($tform, $tpoints);
   $tform->set_area( 3 );
   $tform->add_row( array(
         'HEADER', T_('Examples Preview#tourney'), ));
   $tform->add_row( array(
         'CELL', 1, '',
         'OWNHTML', $examples_table->make_table(), ));


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Edit points#tourney')] =
         array( 'url' => "tournaments/roundrobin/edit_points.php?tid=$tid", 'class' => 'TAdmin' );
   $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}//main


// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$tpoi )
{
   $edits = array();
   $errors = array();
   $is_posted = ( @$_REQUEST['tpts_save'] || @$_REQUEST['tpts_preview'] );

   // read from props or set defaults
   $vars = array(
      'points_type'     => $tpoi->PointsType,
      'points_won'      => $tpoi->PointsWon,
      'points_lost'     => $tpoi->PointsLost,
      'points_draw'     => $tpoi->PointsDraw,
      'score_block'     => $tpoi->ScoreBlock,
      'max_points'      => $tpoi->MaxPoints,
      'flag_share_maxp' => (bool)($tpoi->Flags & TPOINTS_FLAGS_SHARE_MAX_POINTS),
      'flag_neg_points' => (bool)($tpoi->Flags & TPOINTS_FLAGS_NEGATIVE_POINTS),
      'points_resign'   => $tpoi->PointsResignation,
      'points_timeout'  => $tpoi->PointsTimeout,
      'points_forfeit'  => $tpoi->PointsForfeit,
      'points_no_res'   => $tpoi->PointsNoResult,
   );

   $old_vals = array() + $vars; // copy to determine edit-changes
   if ( !@$_REQUEST['tpts_reset'] )
   {
      // read URL-vals into vars
      foreach ( $vars as $key => $val )
         $vars[$key] = get_request_arg( $key, $val );

      // handle checkboxes having no key/val in _POST-hash
      if ( $is_posted )
      {
         foreach ( array( 'flag_share_maxp', 'flag_neg_points' ) as $key )
            $vars[$key] = get_request_arg( $key, false );
      }
   }

   // parse URL-vars
   if ( $is_posted )
   {
      $tp_lim = $tpoi->getPointsLimit(); // for selected type
      $sp_lim = $tpoi->getPointsLimit(TPOINTSTYPE_SIMPLE); // simple
      $hp_lim = $tpoi->getPointsLimit(TPOINTSTYPE_HAHN); // Hahn
      $tp_rng = build_range_text(-$tp_lim, $tp_lim);
      $sp_rng = build_range_text(-$sp_lim, $sp_lim);
      $hp_rng = build_range_text(-$hp_lim, $hp_lim);

      $tpoi->setPointsType( $vars['points_type'] );

      $new_value = $vars['points_won'];
      if ( isNumber($new_value) && $new_value >= -$sp_lim && $new_value <= $sp_lim )
         $tpoi->PointsWon = (int)$new_value;
      else
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('Points Won#tourney'), $sp_rng );

      $new_value = $vars['points_lost'];
      if ( isNumber($new_value) && $new_value >= -$sp_lim && $new_value <= $sp_lim )
         $tpoi->PointsLost = (int)$new_value;
      else
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('Points Lost#tourney'), $sp_rng );

      $new_value = $vars['points_draw'];
      if ( isNumber($new_value) && $new_value >= -$sp_lim && $new_value <= $sp_lim )
         $tpoi->PointsDraw = (int)$new_value;
      else
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('Points Draw#tourney'), $sp_rng );

      $new_value = $vars['score_block'];
      if ( isNumber($new_value) && $new_value >= 1 && $new_value <= $hp_lim )
         $tpoi->ScoreBlock = (int)$new_value;
      else
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'),
            T_('Score Block#tourney'), build_range_text(1, $hp_lim) );

      $flag_shared_maxp = ( $vars['flag_share_maxp'] ) ? TPOINTS_FLAGS_SHARE_MAX_POINTS : 0;
      $flag_neg_points  = ( $vars['flag_neg_points'] ) ? TPOINTS_FLAGS_NEGATIVE_POINTS : 0;
      if ( $flag_shared_maxp && $flag_neg_points )
         $errors[] = sprintf( T_('Options [%s] and [%s] can not both be enabled.#tourney'),
            T_('Share Max. Points#tourney'), T_('Allow Negative Points#tourney') );
      else
         $tpoi->Flags = $flag_shared_maxp | $flag_neg_points;

      $need_even = $flag_shared_maxp;
      $need_even_hahn = ( $flag_shared_maxp && $tpoi->PointsType == TPOINTSTYPE_HAHN );

      $new_value = $vars['max_points'];
      if ( !( isNumber($new_value) && $new_value >= 1 && $new_value <= $hp_lim ) )
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'),
            T_('Max. Points#tourney'), build_range_text(1, $hp_lim) );
      elseif ( $need_even && ($new_value & 1) )
         $errors[] = sprintf( T_('Expecting even number for %s if max. points are shared.#tourney'),
            T_('Max. Points#tourney') );
      else
         $tpoi->MaxPoints = (int)$new_value;

      $new_value = $vars['points_resign'];
      if ( !( isNumber($new_value) && $new_value >= -$hp_lim && $new_value <= $hp_lim ) )
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('Points Resignation#tourney'), $hp_rng );
      elseif ( $need_even && ($new_value & 1) )
         $errors[] = sprintf( T_('Expecting even number for %s if max. points are shared.#tourney'),
            T_('Points Resignation#tourney') );
      else
         $tpoi->PointsResignation = (int)$new_value;

      $new_value = $vars['points_timeout'];
      if ( !( isNumber($new_value) && $new_value >= -$hp_lim && $new_value <= $hp_lim ) )
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('Points Timeout#tourney'), $hp_rng );
      elseif ( $need_even && ($new_value & 1) )
         $errors[] = sprintf( T_('Expecting even number for %s if max. points are shared.#tourney'),
            T_('Points Timeout#tourney') );
      else
         $tpoi->PointsTimeout = (int)$new_value;

      $new_value = $vars['points_forfeit'];
      if ( !( isNumber($new_value) && $new_value >= -$tp_lim && $new_value <= $tp_lim ) )
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('Points Forfeit#tourney'), $tp_rng );
      elseif ( $need_even_hahn && ($new_value & 1) )
         $errors[] = sprintf( T_('Expecting even number for %s if max. points are shared.#tourney'),
            T_('Points Forfeit#tourney') );
      else
         $tpoi->PointsForfeit = (int)$new_value;

      $new_value = $vars['points_no_res'];
      if ( !( isNumber($new_value) && $new_value >= -$tp_lim && $new_value <= $tp_lim ) )
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('Points No-Result#tourney'), $tp_rng );
      elseif ( $need_even_hahn && ($new_value & 1) )
         $errors[] = sprintf( T_('Expecting even number for %s if max. points are shared.#tourney'),
            T_('Points No-Result#tourney') );
      else
         $tpoi->PointsNoResult = (int)$new_value;


      // determine edits
      if ( $old_vals['points_type'] != $tpoi->PointsType ) $edits[] = T_('Points Type#tourney');
      if ( $old_vals['points_won'] != $tpoi->PointsWon ) $edits[] = T_('Points Won#tourney');
      if ( $old_vals['points_lost'] != $tpoi->PointsLost ) $edits[] = T_('Points Lost#tourney');
      if ( $old_vals['points_draw'] != $tpoi->PointsDraw ) $edits[] = T_('Points Draw#tourney');
      if ( $old_vals['score_block'] != $tpoi->ScoreBlock ) $edits[] = T_('Score Block#tourney');
      if ( $old_vals['max_points'] != $tpoi->MaxPoints ) $edits[] = T_('Max. Points#tourney');
      if ( (bool)$old_vals['flag_share_maxp'] != (bool)($tpoi->Flags & TPOINTS_FLAGS_SHARE_MAX_POINTS) )
         $edits[] = T_('Share Max. Points#tourney');
      if ( (bool)$old_vals['flag_neg_points'] != (bool)($tpoi->Flags & TPOINTS_FLAGS_NEGATIVE_POINTS) )
         $edits[] = T_('Allow Negative Points#tourney');
      if ( $old_vals['points_resign'] != $tpoi->PointsResignation ) $edits[] = T_('Points Resignation#tourney');
      if ( $old_vals['points_timeout'] != $tpoi->PointsTimeout ) $edits[] = T_('Points Timeout#tourney');
      if ( $old_vals['points_forfeit'] != $tpoi->PointsForfeit ) $edits[] = T_('Points Forfeit#tourney');
      if ( $old_vals['points_no_res'] != $tpoi->PointsNoResult ) $edits[] = T_('Points No-Result#tourney');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form


/*! \brief Returns table with examples for calculating points. */
function build_points_examples( &$form, $tpoints )
{
   $table = new Table( 'PreviewTPoints', '', null, '',
      TABLE_NO_SORT|TABLE_NO_HIDE|TABLE_NO_PAGE|TABLE_NO_SIZE );

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $table->add_tablehead( 1, T_('Category#header'), '' );
   $table->add_tablehead( 2, T_('Score#header'), 'Right' );
   $table->add_tablehead( 3, T_('Win#header'), 'NumberC' );
   $table->add_tablehead( 4, T_('Loss#header'), 'NumberC' );

   $diff_title = T_('Point-Diff#tpoints');
   $arr_scores = array(
         // category (0=point-diff), score
         T_('Draw'), 0,
         0, 0.5,
         0, 2.5,
         0, 30.5,
         0, 27,
         0, 150,
         T_('Resignation'), SCORE_RESIGN,
         T_('Timeout'), SCORE_TIME,
         T_('Forfeit'), SCORE_FORFEIT,
      );
   for ( $i=0; $i < count($arr_scores); )
   {
      $title = $arr_scores[$i++];
      $score = $arr_scores[$i++];
      if ( !$title )
         $title = $diff_title;

      $table->add_row( array(
            1 => $title,
            2 => $score,
            3 => $tpoints->calculate_points( -$score ),
            4 => $tpoints->calculate_points(  $score ),
         ));
   }

   for ( $i=1; $i <= 3; $i++)
   {
      $var_value = trim( get_request_arg("cp$i") ); // custom-points
      if ( (string)$var_value == '' )
         $var_value = 0;
      $custom_points = (int)abs( $var_value );
      $table->add_row( array(
            1 => $diff_title,
            2 => $form->print_insert_text_input( "cp$i", 4, 4, $var_value,
               array( 'title' => T_('Custom value for point calculations#tpoints')) ),
            3 => $tpoints->calculate_points( -$custom_points ),
            4 => $tpoints->calculate_points(  $custom_points ),
         ));
   }

   return $table;
}//build_points_examples

?>
