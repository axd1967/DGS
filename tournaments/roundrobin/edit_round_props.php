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
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_log_helper.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_round_status.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentRoundEdit');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.roundrobin.edit_round_props');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.roundrobin.edit_round_props');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'Tournament.roundrobin.edit_round_props');

/* Actual REQUEST calls used:
     tid=&round=              : edit tournament round
     tr_preview&tid=&round=   : preview for tournament-round-save
     tr_save&tid=&round=      : update (replace) tournament-round in database
*/

   $tid = (int) @$_REQUEST['tid'];
   if ( $tid < 0 ) $tid = 0;
   $round = (int) @$_REQUEST['round'];
   if ( $round < 0 ) $round = 0;

   $tourney = TournamentCache::load_cache_tournament( 'Tournament.edit_round_props.find_tournament', $tid );
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   $t_limits = $ttype->getTournamentLimits();
   if ( !$ttype->need_rounds )
      error('tournament_edit_rounds_not_allowed', "Tournament.edit_round_props.need_rounds($tid)");

   // create/edit allowed?
   $allow_edit_tourney = TournamentHelper::allow_edit_tournaments($tourney, $my_id);
   if ( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.edit_round_props.edit_tournament($tid,$my_id)");

   // load existing T-round
   $tround = TournamentCache::load_cache_tournament_round( 'Tournament.edit_round_props', $tid, $round );
   $trstatus = new TournamentRoundStatus( $tourney, $tround );

   // init
   $errors = $tstatus->check_edit_status( array( TOURNEY_STATUS_NEW, TOURNEY_STATUS_REGISTER, TOURNEY_STATUS_PAIR ) );
   $errors = array_merge( $errors, $trstatus->check_edit_round_status( TROUND_STATUS_INIT ) );
   if ( !TournamentUtils::isAdmin() && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();

   // check + parse edit-form (notes)
   $old_tround = clone $tround;
   list( $vars, $edits, $input_errors ) = parse_edit_form( $tround, $t_limits );
   $errors = array_merge( $errors, $input_errors, $tround->check_properties() );

   // save tournament-round-object with values from edit-form
   if ( @$_REQUEST['tr_save'] && !@$_REQUEST['tr_preview'] && count($errors) == 0 )
   {
      $tround->persist(); // insert or update
      TournamentLogHelper::log_change_tournament_round_props( $tid, $allow_edit_tourney, $edits, $old_tround, $tround );

      jump_to("tournaments/roundrobin/edit_round_props.php?tid={$tid}".URI_AMP."round={$round}".URI_AMP
            . "sysmsg=". urlencode(T_('Tournament Round saved!')) );
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
         'DESCRIPTION', T_('Round Status#tourney'),
         'TEXT',        TournamentRound::getStatusText($tround->Status), ));

   if ( count($errors) )
   {
      $trform->add_row( array( 'HR' ));
      $trform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $trform->add_empty_row();
   }

   $trform->add_row( array( 'HR' ));
   $trform->add_row( array(
         'DESCRIPTION', T_('Min. Pool Size'),
         'TEXTINPUT',   'min_pool_size', 3, 3, $vars['min_pool_size'],
         'TEXT',        $t_limits->getLimitRangeTextAdmin(TLIMITS_TRD_MIN_POOLSIZE), ));
   $trform->add_row( array(
         'DESCRIPTION', T_('Max. Pool Size'),
         'TEXTINPUT',   'max_pool_size', 3, 3, $vars['max_pool_size'],
         'TEXT',        $t_limits->getLimitRangeTextAdmin(TLIMITS_TRD_MAX_POOLSIZE), ));
   $trform->add_row( array(
         'DESCRIPTION', T_('Max. Pool Count'),
         'TEXTINPUT',   'max_pool_count', 5, 5, $vars['max_pool_count'],
         'TEXT',        $t_limits->getLimitRangeTextAdmin(TLIMITS_TRD_MAX_POOLCOUNT), ));
   $trform->add_empty_row();
   $trform->add_row( array(
         'DESCRIPTION', T_('Pool Winner Ranks'),
         'TEXTINPUT',   'poolwinner_ranks', 3, 3, $vars['poolwinner_ranks'], ));

   $trform->add_empty_row();
   $trform->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT',        span('TWarning', implode(', ', $edits), '[%s]'), ));

   $trform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'tr_save', T_('Save Tournament Round'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tr_preview', T_('Preview'),
      ));


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $trform->echo_string();


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Edit rounds#tourney')] =
         array( 'url' => "tournaments/roundrobin/edit_rounds.php?tid=$tid".URI_AMP."round=$round", 'class' => 'TAdmin' );
   $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}//main


// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$trd, $t_limits )
{
   $edits = array();
   $errors = array();

   // read from props or set defaults
   $vars = array(
      'min_pool_size'   => $trd->MinPoolSize,
      'max_pool_size'   => $trd->MaxPoolSize,
      'max_pool_count'  => $trd->MaxPoolCount,
      'poolwinner_ranks' => $trd->PoolWinnerRanks,
   );

   // copy to determine edit-changes
   $old_vals = array_merge( array(), $vars );
   // read URL-vals into vars
   foreach ( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // parse URL-vars
   if ( @$_REQUEST['tr_save'] || @$_REQUEST['tr_preview'] )
   {
      $new_value = $vars['min_pool_size'];
      if ( TournamentUtils::isNumberOrEmpty($new_value) && $new_value >= 0 && $new_value <= TROUND_MAX_POOLSIZE )
      {
         $limit_errors = $t_limits->checkRounds_MinPoolSize( $new_value, $trd->MinPoolSize );
         if ( count($limit_errors) )
            $errors = array_merge( $errors, $limit_errors );
         else
            $trd->MinPoolSize = $new_value;
      }
      else
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('Min. Pool Size'),
            $t_limits->getLimitRangeText(TLIMITS_TRD_MIN_POOLSIZE) ); // check for general MAX, but show specific max

      $new_value = $vars['max_pool_size'];
      if ( TournamentUtils::isNumberOrEmpty($new_value) && $new_value >= 0 && $new_value <= TROUND_MAX_POOLSIZE )
      {
         $limit_errors = $t_limits->checkRounds_MaxPoolSize( $new_value, $trd->MaxPoolSize );
         if ( count($limit_errors) )
            $errors = array_merge( $errors, $limit_errors );
         else
            $trd->MaxPoolSize = $new_value;
      }
      else
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('Max. Pool Size'),
            $t_limits->getLimitRangeText(TLIMITS_TRD_MAX_POOLSIZE) ); // check for general MAX, but show specific max

      $new_value = $vars['max_pool_count'];
      if ( TournamentUtils::isNumberOrEmpty($new_value) && $new_value >= 0 && $new_value <= TROUND_MAX_POOLCOUNT )
      {
         $limit_errors = $t_limits->checkRounds_MaxPoolCount( $new_value, $trd->MaxPoolCount );
         if ( count($limit_errors) )
            $errors = array_merge( $errors, $limit_errors );
         else
            $trd->MaxPoolCount = $new_value;
      }
      else
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('Max. Pool Count'),
            $t_limits->getLimitRangeText(TLIMITS_TRD_MAX_POOLCOUNT) ); // check for general MAX, but show specific max

      $new_value = $vars['poolwinner_ranks'];
      if ( isNumber($new_value, /*neg*/false, /*empty*/false) )
         $trd->PoolWinnerRanks = $new_value;
      else
         $errors[] = sprintf( T_('Expecting number for pool winner ranks in range %s and smaller max. pool size [%s].'),
            build_range_text( 1, $t_limits->getMaxLimit(TLIMITS_TRD_MAX_POOLSIZE)), $trd->MaxPoolSize );

      // determine edits
      if ( $old_vals['min_pool_size'] != $trd->MinPoolSize ) $edits[] = T_('Pool Size');
      if ( $old_vals['max_pool_size'] != $trd->MaxPoolSize ) $edits[] = T_('Pool Size');
      if ( $old_vals['max_pool_count'] != $trd->MaxPoolCount ) $edits[] = T_('Pool Count');
      if ( $old_vals['poolwinner_ranks'] != $trd->PoolWinnerRanks ) $edits[] = T_('Pool Winner Ranks');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form

?>
