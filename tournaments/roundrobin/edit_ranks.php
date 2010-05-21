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
require_once 'include/form_functions.php';
require_once 'include/gui_functions.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_pool_classes.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_round_status.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentRankEditor');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_ranks');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $page = "edit_ranks.php";

/* Actual REQUEST calls used:
     tid=                     : edit tournament pool ranks
     t_stats&tid=             : show rank stats
     t_fill&tid=              : fill ranks for all finished pools
     t_exec&tid=&action=&rank_from=&rank_to=&pool=    : execute action on ranks
*/

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;

   $tourney = Tournament::load_tournament( $tid ); // existing tournament ?
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.edit_ranks.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   if( !$ttype->need_rounds )
      error('tournament_edit_rounds_not_allowed', "Tournament.edit_ranks.need_rounds($tid)");

   // create/edit allowed?
   if( !$tourney->allow_edit_tournaments($my_id) )
      error('tournament_edit_not_allowed', "Tournament.edit_ranks.edit_tournament($tid,$my_id)");

   // load existing T-round
   $round = $tourney->CurrentRound;
   $tround = TournamentRound::load_tournament_round( $tid, $round );
   if( is_null($tround) )
      error('bad_tournament', "Tournament.edit_ranks.find_tournament_round($tid,$round,$my_id)");
   $trstatus = new TournamentRoundStatus( $tourney, $tround );


   // init
   $errors = $tstatus->check_edit_status( TournamentPool::get_edit_ranks_tournament_status() );
   $errors = array_merge( $errors, $trstatus->check_edit_status( TROUND_STATUS_PLAY ) );
   if( !TournamentUtils::isAdmin() && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();

   // ---------- Process actions ------------------------------------------------

   $result_notes = null;
   $rstable = null;
   if( count($errors) == 0 )
   {
      if( @$_REQUEST['t_exec'] ) // execute rank-actions
      {
         // args: action=&rank_from=&rank_to=&pool=
         $action    = (int)get_request_arg('action');
         $rank_from = get_request_arg('rank_from');
         $rank_to   = get_request_arg('rank_to');
         $act_pool  = get_request_arg('pool');
         TournamentPool::execute_rank_action( $tid, $round, $action, $rank_from, $rank_to, $act_pool );
      }

      if( @$_REQUEST['t_stats'] || @$_REQUEST['t_exec'] )
      {
         $tp_count = 0; //TODO load #next-round-RPs

         // count ranks for current round over all pools
         $rank_counts = TournamentPool::count_tournament_pool_ranks( $tid, $round );
         $rank_summary = new RankSummary( $page, $rank_counts, $tp_count );
         $rstable = $rank_summary->make_table_rank_summary();
         $result_notes = $rank_summary->build_notes();
      }
      elseif( @$_REQUEST['t_fill'] )
      {
         $result_notes = TournamentHelper::fill_ranks_tournament_pool( $tround );
      }
   }

   // --------------- Tournament-Pool-Ranks EDIT form --------------------

   $tform = new Form( 'tournament', $page, FORM_GET );
   $tform->add_hidden( 'tid', $tid );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament Round#tround'),
         'TEXT',        $tourney->formatRound(), ));
   TournamentUtils::show_tournament_flags( $tform, $tourney );
   $tform->add_row( array(
         'DESCRIPTION', T_('Round Status#tround'),
         'TEXT',        TournamentRound::getStatusText($tround->Status), ));

   $has_errors = count($errors);
   if( $has_errors )
   {
      $tform->add_row( array( 'HR' ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString(T_('There are some errors'), $errors) ));
      $tform->add_empty_row();
   }

   // Actions choices ------------------------

   $disable_submit = ($has_errors) ? 'disabled=1' : '';
   $tform->add_row( array( 'HR' ));

   $tform->add_row( array(
         'CELL', 1, '',
         'SUBMITBUTTONX', 't_stats', T_('Show Rank Stats'), $disable_submit,
         'CELL', 1, '',
         'TEXT', T_('Show counts of all ranks + Show rank-actions.'), ));

   $tform->add_row( array(
         'CELL', 1, '',
         'SUBMITBUTTONX', 't_fill', T_('Fill Ranks'), $disable_submit,
         'CELL', 1, '',
         'TEXT', T_('Fill ranks for finished pools.'), ));

   if( @$_REQUEST['t_stats'] || @$_REQUEST['t_exec'] )
   {
      $arr_actions = array(
         RKACT_SET_NEXT_RND   => T_('Set Next-Round#rank_edit'),
         RKACT_CLEAR_NEXT_RND => T_('Clear Next-Round#rank_edit'),
         RKACT_CLEAR_RANKS    => T_('Clear Ranks#rank_edit'),
         RKACT_RESET_RANKS    => T_('Reset Ranks#rank_edit'),
      );
      $arr_ranks = array_value_to_key_and_value( $rank_summary->get_ranks() );
      $arr_ranks_to = array( '' => '=' ) + $arr_ranks;
      $arr_ranks = array( '' => T_('All#ranks') ) + $arr_ranks;
      $arr_pools = array( '' => T_('All#ranks') ) + array_value_to_key_and_value( range(1, $tround->Pools) );
      $tform->add_empty_row();
      $tform->add_row( array(
            'CELL', 2, '',
            'SELECTBOX', 'action', 1, $arr_actions, get_request_arg('action', RKACT_SET_NEXT_RND), false,
            'TEXT', T_('for users with ranks#rank_edit') . MED_SPACING,
            'SELECTBOX', 'rank_from', 1, $arr_ranks, get_request_arg('rank_from',''), false,
            'TEXT', '...' . MED_SPACING,
            'SELECTBOX', 'rank_to', 1, $arr_ranks_to, get_request_arg('rank_to',''), false,
            'TEXT', T_('on pool#rank_edit') . MED_SPACING,
            'SELECTBOX', 'pool', 1, $arr_pools, get_request_arg('pool'), false,
            'TEXT', ' ',
            'SUBMITBUTTONX', 't_exec', T_('Execute'), $disable_submit, ));
   }

   $title = T_('Tournament Pool Ranks Editor');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();

   section( 'actionResult', T_('Action Results') );
   if( !is_null($rstable) )
      $rstable->echo_table();
   if( !is_null($result_notes) )
      echo_notes( 'edittournamentpoolrankTable', '', $result_notes );


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('View Pools')] = "tournaments/roundrobin/view_pools.php?tid=$tid";
   $menu_array[T_('Edit ranks')] =
      array( 'url' => "tournaments/roundrobin/edit_ranks.php?tid=$tid", 'class' => 'TAdmin' );
   $menu_array[T_('Manage tournament')] =
      array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}
?>
