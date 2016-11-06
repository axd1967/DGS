<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/classlib_user.php';
require_once 'include/rating.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_pool_classes.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_round_helper.php';
require_once 'tournaments/include/tournament_round_status.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentRankEditor');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.roundrobin.edit_ranks');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.roundrobin.edit_ranks');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'Tournament.roundrobin.edit_ranks');

   $page = "edit_ranks.php";

/* Actual REQUEST calls used:
     NOTE: used for round-robin-tournaments
     NOTE: used for league-tournaments (without pool-winners)

     tid=                                          : edit tournament pool ranks
     tid=&uid=                                     : edit rank of single user
     t_stats&tid=                                  : show rank stats + pool-winners-check
     t_fillranks&tid=                              : fill ranks for all finished pools
     t_setpoolwinners&tid=                         : set pool winners for all finished pools (only for round-robin)
     t_exec&tid=&action=&rank_from=&rank_to=&tpk=  : execute action on ranks
     t_userexec&tid=&uid=&action=                  : execute action on rank of single pool-user
     t_setrank&tid=&uid=&rank=&poolwin=            : set rank / pool-win for single pool-user
*/

   $tid = (int) @$_REQUEST['tid'];
   if ( $tid < 0 ) $tid = 0;
   $uid = (int) @$_REQUEST['uid'];

   $tourney = TournamentCache::load_cache_tournament( 'Tournament.edit_ranks.find_tournament', $tid );
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   if ( !$ttype->need_rounds )
      error('tournament_edit_rounds_not_allowed', "Tournament.edit_ranks.need_rounds($tid)");
   $is_league = ( $tourney->Type == TOURNEY_TYPE_LEAGUE );

   // create/edit allowed?
   $allow_edit_tourney = TournamentHelper::allow_edit_tournaments($tourney, $my_id);
   if ( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.edit_ranks.edit_tournament($tid,$my_id)");

   // load existing T-round
   $round = $tourney->CurrentRound;
   $tround = TournamentCache::load_cache_tournament_round( 'Tournament.edit_ranks', $tid, $round );
   $trstatus = new TournamentRoundStatus( $tourney, $tround );


   // init
   $errors = $tstatus->check_edit_status( array( TOURNEY_STATUS_PLAY ) );
   $errors = array_merge( $errors, $trstatus->check_edit_round_status( TROUND_STATUS_PLAY ) );
   if ( !TournamentUtils::isAdmin() && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();

   $user = $tpool_user = null;
   if ( $uid ) // check user
   {
      $user = User::load_user( $uid );
      if ( is_null($user) )
         $errors[] = sprintf( T_('Unknown user-id [%s]'), $uid );
      else
      {
         $tpool_user = TournamentPool::load_tournament_pool_user( $tid, $round, $uid, TPOOL_LOADOPT_USER );
         if ( is_null($tpool_user) )
            $errors[] = sprintf( T_('User [%s] is not participating in tournament [%s] round [%s]'),
               $user->Handle, $tid, $round );
      }
   }

   // ---------- Process actions ------------------------------------------------

   $result_notes = $rstable = null;
   $show_rank_sum = false;
   $show_stats = @$_REQUEST['t_stats'];
   $has_errors = count($errors);
   $upd_count = -1;
   if ( !$has_errors )
   {
      if ( @$_REQUEST['t_exec'] ) // execute rank-actions
      {
         $action    = (int)get_request_arg('action');
         $rank_from = get_request_arg('rank_from');
         $rank_to   = get_request_arg('rank_to');
         $act_tier_pool_key = trim(get_request_arg('tpk'));

         $upd_count = TournamentPool::execute_rank_action( $allow_edit_tourney, $tourney, $tround, $action, /*uid*/0,
            $rank_from, $rank_to, $act_tier_pool_key );
      }
      elseif ( $uid && @$_REQUEST['t_userexec'] ) // execute rank-actions on single user
      {
         $action = (int)get_request_arg('action');
         $upd_count = TournamentPool::execute_rank_action( $allow_edit_tourney, $tourney, $tround, $action, $uid );
         $tpool_user = TournamentPool::load_tournament_pool_user( $tid, $round, $uid, TPOOL_LOADOPT_USER );
      }
      elseif ( $uid && @$_REQUEST['t_setrank'] ) // set rank for single user
      {
         $rank_value = (int)get_request_arg('rank');
         $pool_win = (bool)get_request_arg('poolwin');
         if ( !$is_league && !$pool_win && $rank_value )
            $rank_value = -abs($rank_value);
         $upd_count = TournamentPool::update_tournament_pool_ranks( $tid, $allow_edit_tourney, 'edit_ranks',
            $tpool_user->ID, $rank_value, /*fix-rank*/true );
         if ( $upd_count > 0 )
         {
            $tpool_user->Rank = $rank_value;
            TournamentPool::delete_cache_tournament_pools( "Tournament.edit_ranks.set_rank_user1($tid,$round)",
               $tid, $round );
         }
      }

      if ( $show_stats || @$_REQUEST['t_exec'] || @$_REQUEST['t_userexec'] || @$_REQUEST['t_setrank'] )
         $show_rank_sum = true;
      elseif ( @$_REQUEST['t_fillranks'] )
         $result_notes = TournamentRoundHelper::fill_ranks_tournament_pool(
            $allow_edit_tourney, $tround, $tourney->Type );
      elseif ( @$_REQUEST['t_setpoolwinners'] && !$is_league )
         $result_notes = TournamentRoundHelper::fill_pool_winners_tournament_pool(
            $allow_edit_tourney, $tround, $tourney->Type );
   }

   $pw_errors = $pw_warnings = null;
   if ( $show_rank_sum || $show_stats )
   {
      $tp_regcount = ( $is_league )
         ? 0
         : TournamentParticipant::count_tournament_participants( $tid, TP_STATUS_REGISTER, $round + 1, /*NextR*/false );

      // count ranks for current round over all pools
      $rank_counts = TournamentPool::count_tournament_pool_ranks( $tid, $round );
      $relegation_counts = ( $is_league ) ? TournamentPool::count_tournament_pool_relegations( $tid ) : null;
      $rank_summary = new RankSummary( $page, $tourney->Type, $rank_counts, $relegation_counts, $tp_regcount );
      $rstable = $rank_summary->make_table_rank_summary();
      $result_notes = $rank_summary->build_notes_rank_summary();

      if ( $show_stats )
         list( $pw_errors, $pw_warnings ) = $ttype->checkPoolWinners( $tourney, $tround );
   }

   // --------------- Tournament-Pool-Ranks EDIT form --------------------

   $tform = new Form( 'tournament', $page, FORM_GET );
   $tform->add_hidden( 'tid', $tid );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament Status'),
         'TEXT',        Tournament::getStatusText($tourney->Status), ));
   if ( !$is_league )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Tournament Round'),
            'TEXT',        $tourney->formatRound(), ));
   }
   TournamentUtils::show_tournament_flags( $tform, $tourney );
   $tform->add_row( array(
         'DESCRIPTION', T_('Round Status#tourney'),
         'TEXT',        TournamentRound::getStatusText($tround->Status), ));
   if ( !$is_league )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Pool Winner Ranks'),
            'TEXT',        $tround->PoolWinnerRanks, ));
   }

   if ( count($errors) )
   {
      $tform->add_row( array( 'HR' ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $tform->add_empty_row();
   }

   // Actions choices ------------------------

   $disable_submit = ($has_errors) ? 'disabled=1' : '';
   list( $arr_actions, $default_action ) = build_rank_actions_selection( $tourney->Type );

   $tform->add_row( array( 'HR' ));

   $tform->add_row( array(
         'CELL', 1, '',
         'SUBMITBUTTON', 't_stats', T_('Show Rank Stats#tourney'),
         'CELL', 1, '',
         'TEXT', T_('Show counts of all ranks & rank-actions.#tourney'), ));

   $tform->add_row( array(
         'CELL', 1, '',
         'SUBMITBUTTONX', 't_fillranks', T_('Fill Ranks#tpool'), $disable_submit,
         'CELL', 1, '',
         'TEXT', T_('Fill ranks for finished pools.'), ));

   if ( !$is_league )
   {
      $tform->add_row( array(
            'CELL', 1, '',
            'SUBMITBUTTONX', 't_setpoolwinners', T_('Set Pool Winners'), $disable_submit,
            'CELL', 1, '',
            'TEXT', sprintf( T_('Set pool winners with ranks %s for finished pools.'), '1..' . $tround->PoolWinnerRanks) ));
   }

   if ( !$has_errors && (@$_REQUEST['t_stats'] || @$_REQUEST['t_exec']) && !$uid )
   {
      $p_parser = new PoolParser( $tourney->Type, $tround );
      $arr_ranks = array_value_to_key_and_value( $rank_summary->get_ranks() );
      $arr_ranks_to = array( '' => '=' ) + $arr_ranks;
      $arr_ranks = array( '' => T_('All') ) + $arr_ranks;
      $arr_pools = array( '' => T_('All') ) + $p_parser->build_valid_tier_pools_selection();
      $tform->add_empty_row();
      $tform->add_row( array(
            'CELL', 2, '',
            'SELECTBOX', 'action', 1, $arr_actions, get_request_arg('action', $default_action), false,
            'TEXT', T_('for all users with ranks#tpool') . MED_SPACING,
            'SELECTBOX', 'rank_from', 1, $arr_ranks, get_request_arg('rank_from',''), false,
            'TEXT', '...' . MED_SPACING,
            'SELECTBOX', 'rank_to', 1, $arr_ranks_to, get_request_arg('rank_to',''), false,
            'TEXT', T_('on pool') . MED_SPACING,
            'SELECTBOX', 'tpk', 1, $arr_pools, get_request_arg('tpk'), false,
            'TEXT', ' ',
            'SUBMITBUTTONX', 't_exec', T_('Execute'), $disable_submit, ));
   }

   // edit rank of single user
   if ( $uid && !is_null($tpool_user) && !@$_REQUEST['t_fillranks'] && !@$_REQUEST['t_setpoolwinners'] )
   {
      $curr_rank = $tpool_user->Rank;
      $tform->add_hidden( 'uid', $uid );
      $tform->add_empty_row();
      $tform->add_row( array(
            'CELL', 2, '',
            'TEXT', sprintf( T_('Edit Rank of pool-user [ %s ] with rating [%s] in pool %s#tpool') . ':',
                             $user->user_reference(), echo_rating($user->Rating, true),
                             PoolViewer::format_tier_pool( $tourney->Type, $tpool_user->Tier, $tpool_user->Pool, true)), ));
      $tform->add_row( array(
            'CELL', 2, '',
            'TEXT', sprintf( '%s (%s): %s', T_('User Result State#tourney'),
                  T_('Rank#tpool') . ' & ' . ( $is_league ? T_('Relegation#tpool') : T_('Pool-Winner Status#tourney') ),
                  span('TRank', $tpool_user->formatRankText() . MED_SPACING . '|' . MED_SPACING
                        . $tpool_user->echoRankImage($tourney->Type, NO_VALUE)) ), ));

      $arr_set_ranks = array_value_to_key_and_value( range(1, $tround->PoolSize) );
      $rank_defval = ( $curr_rank != 0 && $curr_rank > TPOOLRK_RANK_ZONE ) ? abs($curr_rank) : 1;

      $arr_row = array(
            'CELL', 2, '',
            'SELECTBOX', 'action', 1, $arr_actions, get_request_arg('action', $default_action), false,
            'TEXT', ' ',
            'SUBMITBUTTONX', 't_userexec', T_('Execute'), $disable_submit,
            'TEXT', SMALL_SPACING.SMALL_SPACING . T_('or') . SMALL_SPACING.SMALL_SPACING,
            'TEXT', T_('Set Rank#tpool') . MED_SPACING,
            'SELECTBOX', 'rank', 1, $arr_set_ranks, $rank_defval, false,
            'TEXT', ' ' );
      if ( !$is_league )
      {
         array_push( $arr_row,
            'CHECKBOX', 'poolwin', 1, T_('Pool Winner#tourney'), ($curr_rank > 0),
            'TEXT', SMALL_SPACING );
      }
      array_push( $arr_row,
            'SUBMITBUTTONX', 't_setrank', T_('Execute'), $disable_submit );
      $tform->add_row( $arr_row );
   }//single-user-edit


   $title = T_('Tournament Pool Ranks Editor');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();

   section( 'actionResult', T_('Action Results') );
   if ( !is_null($rstable) )
   {
      if ( $upd_count >= 0 )
      {
         echo span( ($upd_count > 0 ? 'TInfo' : 'TWarning'),
            sprintf( T_('%s entries have been updated for this action.#tourney'), $upd_count )),
            "<br><br>\n";
      }

      $rstable->echo_table();

      if ( is_array($pw_errors) || is_array($pw_warnings) )
      {
         $out = array();
         if ( count(@$pw_errors) )
            $out[] = "<tr>" . buildErrorListString(T_('Errors'), $pw_errors, 1) . "</tr>\n";
         echo "</tr></table>\n";
         if ( count(@$pw_warnings) )
            $out[] = "<tr>" . buildErrorListString(T_('Warnings'), $pw_warnings, 1, true, 'TInfo', 'WarnMsg') . "</tr>\n";
         if ( count($out) == 0 )
            $out[] = "<tr><td>" . T_('No errors or warnings found.#tourney') . "<br><br></td></tr>\n";
         echo "<table>\n<tr><th>", T_('Check Result').':', "</th></tr>\n", implode('', $out), "</table>\n";
      }
   }
   if ( !is_null($result_notes) )
      echo_notes( 'edittournamentpoolrankTable', T_('Rank Summary Notes#tourney').':', $result_notes, false );


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('View Pools')] = "tournaments/roundrobin/view_pools.php?tid=$tid".URI_AMP.'edit=1';
   $menu_array[T_('Edit ranks#tpool')] =
      array( 'url' => "tournaments/roundrobin/edit_ranks.php?tid=$tid", 'class' => 'TAdmin' );
   $menu_array[T_('Manage tournament')] =
      array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}//main


function build_rank_actions_selection( $tourney_type )
{
   $arr = array();
   if ( $tourney_type == TOURNEY_TYPE_LEAGUE )
   {
      $default_action = RKACT_PROMOTE;
      $arr[RKACT_PROMOTE] = T_('Promote Player#pool');
      $arr[RKACT_DEMOTE] = T_('Demote Player#pool');
      $arr[RKACT_CLEAR_RELEGATION] = T_('Clear Relegation#pool');
   }
   else //if ( $tourney_type == TOURNEY_TYPE_ROUND_ROBIN )
   {
      $default_action = RKACT_SET_POOL_WIN;
      $arr[RKACT_SET_POOL_WIN] = T_('Set Pool Winner');
      $arr[RKACT_CLEAR_POOL_WIN] = T_('Clear Pool Winner');
   }
   $arr[RKACT_WITHDRAW] = T_('Withdraw Player#tpool');
   $arr[RKACT_REMOVE_RANKS] = T_('Remove Rank#tpool');
   return array( $arr, $default_action );
}//build_rank_actions_selection

?>
