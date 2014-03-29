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
require_once 'include/std_classes.php';
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_pool_classes.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_round_helper.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentPoolView');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.roundrobin.view_pools');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.roundrobin.view_pools');

   $my_id = $player_row['ID'];

   $page = "view_pools.php";

/* Actual REQUEST calls used
     tid=[&round=]                  : view T-pool
*/

   $tid = (int) @$_REQUEST['tid'];
   if ( $tid < 0 ) $tid = 0;
   $round = (int) @$_REQUEST['round'];
   if ( $round < 0 ) $round = 0;
   $edit = (get_request_arg('edit', 0)) ? 1 : 0;

   $tourney = TournamentCache::load_cache_tournament( 'Tournament.pool_view.find_tournament', $tid );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   if ( !$ttype->need_rounds )
      error('tournament_edit_rounds_not_allowed', "Tournament.pool_view.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );

   // create/edit allowed?
   $allow_edit_tourney = TournamentHelper::allow_edit_tournaments($tourney, $my_id );
   if ( $edit && !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.pool_view.find_tournament($tid)");

   // load existing T-round
   if ( $round < 1 || $round > $tourney->CurrentRound )
      $round = $tourney->CurrentRound;
   $tround = TournamentCache::load_cache_tournament_round( 'Tournament.pool_view', $tid, $round );

   $tpoints = TournamentCache::load_cache_tournament_points( 'Tournament.pool_view', $tid );

   // init
   $errors = $tstatus->check_view_status( TournamentHelper::get_view_data_status($allow_edit_tourney) );
   $allow_view = ( count($errors) == 0 );

   // no caching for directors, and only for T-status on PLAY/CLOSED, and if there are >4 pools
   $use_pool_cache = !$allow_edit_tourney && ($tround->Pools > 4)
      && in_array($tourney->Status, TournamentHelper::get_view_data_status());

   if ( $allow_view )
   {
      $tprops = TournamentCache::load_cache_tournament_properties( 'Tournament.pool_view', $tid );
      $need_trating = $tprops->need_rating_copy();
      $games_per_challenge = TournamentRoundHelper::determine_games_per_challenge( $tid );

      $tpool_iterator = TournamentCache::load_cache_tournament_pools( 'Tournament.pool_view.load_pools',
         $tid, $round, $need_trating, $use_pool_cache );
      $poolTables = new PoolTables( $tround->Pools );
      $poolTables->fill_pools( $tpool_iterator );
      $count_players = $tpool_iterator->getItemCount();

      $tg_iterator = TournamentCache::load_cache_tournament_games( 'Tournament.pool_view',
         $tid, $tround->ID, 0, /*all-stati*/null );
      $poolTables->fill_games( $tg_iterator, $tpoints );
      $counts = $poolTables->count_games();
   }//allow_view


   // --------------- Tournament-Pools EDIT form --------------------

   $title = sprintf( T_('Pools of Tournament #%s for round %s'), $tid, $round );
   start_page( $title, true, $logged_in, $player_row );
   echo "<h2 class=Header>", $tourney->build_info(3, sprintf(T_('Round #%s#tourney'), $round)), "</h2>\n";

   if ( count($errors) )
   {
      echo "<table><tr>",
         buildErrorListString( T_('There are some errors'), $errors, 1, false ),
         "</tr></table>\n";
   }

   if ( $allow_view )
   {
      echo sprintf( T_('Round summary (%s players in %s pools): %s games started, %s running, %s finished#tpool'),
                    $count_players, $tround->Pools, $counts['all'], $counts['run'], $counts['finished'] ),
         "<br>\n";

      $my_tpool = $tpool_iterator->getIndexValue( 'uid', $my_id, 0 );
      if ( $my_tpool )
      {
         $pool_link = anchor('#pool'.$my_tpool->Pool, sprintf( T_('Pool %s'), $my_tpool->Pool ) );
         echo sprintf( T_('You are playing in %s.#tpool'), $pool_link ), "<br>\n";
      }
      echo "<br>\n";

      if ( $tourney->Rounds > 1 )
      {
         $out = array();
         for( $r=1; $r <= $tourney->Rounds; $r++ )
            $out[] = anchor( $base_path."tournaments/roundrobin/view_pools.php?tid=$tid".URI_AMP."round=$r",
               sprintf( T_('View Pools (Round #%s)'), $r ));
         echo implode(', ', $out), "<br><br>\n";
      }

      $poolViewer = new PoolViewer( $tid, $page, $poolTables, $games_per_challenge,
         ($need_trating ? 0 : PVOPT_NO_TRATING) | ($edit ? PVOPT_EDIT_RANK : 0) | ($use_pool_cache ? PVOPT_NO_ONLINE : 0) );
      if ( $edit )
         $poolViewer->setEditCallback( 'pool_user_edit_rank' );
      $poolViewer->init_pool_table();
      $poolViewer->make_pool_table();
      $poolViewer->echo_pool_table();
   }//allow_view

   echo_notes( 'tpoolnotesTable', T_('Tournament Pool notes'), build_pool_notes($tpoints), true, false );


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   if ( $allow_view )
      $menu_array[T_('View Pools')] = "tournaments/roundrobin/view_pools.php?tid=$tid";
   if ( $allow_edit_tourney )
   {
      if ( $tround->Status == TROUND_STATUS_POOL )
         $menu_array[T_('Edit pools#tpool')] =
            array( 'url' => "tournaments/roundrobin/edit_pools.php?tid=$tid", 'class' => 'TAdmin' );
      if ( $tround->Status == TROUND_STATUS_PLAY )
         $menu_array[T_('Edit ranks#tpool')] =
            array( 'url' => "tournaments/roundrobin/edit_ranks.php?tid=$tid", 'class' => 'TAdmin' );
      $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );
   }

   end_page(@$menu_array);
}//main


// callback-func for edit-column in pools
function pool_user_edit_rank( &$poolviewer, $uid )
{
   global $base_path, $tid;
   return anchor( $base_path."tournaments/roundrobin/edit_ranks.php?tid=$tid".URI_AMP."uid=$uid",
         image( $base_path.'images/edit.gif', T_('Edit Rank#tpool'), null, 'class="InTextImage"' ) );
}

/*! \brief Returns array with notes about tournament pools. */
function build_pool_notes( $tpoints )
{
   $notes = array();
   $notes[] = sprintf( T_('Pools are ranked by Tie-breakers: %s'),
      implode(', ', array( T_('Points#tourney'), T_('SODOS#tourney') ) ));

   $mfmt = MINI_SPACING . '%s' . MINI_SPACING;
   $sep = ', ' . MED_SPACING;
   $img_pool_winner = echo_image_tourney_pool_winner();
   $points_type_text = TournamentPoints::getPointsTypeText($tpoints->PointsType);

   $notes[] = array( T_('Pool matrix entries & colors (with link to game)#tpool_table') . ':',
      sprintf( T_("'%s' = running game, '%s' = no game#tpool"), '#', '-' )
      . $sep .
      span('MatrixSelf', T_('self#tpool_table'), $mfmt),
      span('MatrixWon', T_('game won#tpool_table'), $mfmt)
      . $sep .
      span('MatrixWon MatrixForfeit', T_('game won by forfeit#tpool_table'), $mfmt),
      span('MatrixLost', T_('game lost#tpool_table'), $mfmt)
      . $sep .
      span('MatrixLost MatrixForfeit', T_('game lost by forfeit#tpool_table'), $mfmt),
      span('MatrixDraw', T_('game draw#tpool_table'), $mfmt)
      . $sep .
      span('MatrixAnnulled', T_('game annulled#tpool_table'), $mfmt)
      . $sep .
      span('MatrixNoResult', T_('game no-result#tpool_table'), $mfmt)
      );

   $notes[] = sprintf( T_('[%s] in format "wins : losses" = number of wins and losses for user'), T_('#Wins#tourney') );
   $notes[] = sprintf( T_('[%s] = sum of points calculated from game-results of player'), T_('Points#header') );

   $arr = array( T_('Points configuration type#tpoints') . ': ' . span('bold', $points_type_text ) );
   if ( $tpoints->PointsType == TPOINTSTYPE_SIMPLE )
   {
      $arr[] = sprintf( T_('game ended by score (>0), resignation or timeout: %s points for winner, %s points for loser#tpool_table'),
         $tpoints->PointsWon, $tpoints->PointsLost );
      $arr[] = sprintf( T_('game ended by forfeit: %s points for winner, %s points for loser#tpool_table'),
         $tpoints->PointsForfeit, $tpoints->PointsLost );
      $arr[] = sprintf( T_('game ended by draw: %s points for both players#tpool_table'), $tpoints->PointsDraw );
      $arr[] = sprintf( T_('game ended by no-result: %s points for both players#tpool_table'), $tpoints->PointsNoResult );
      $arr[] = sprintf( T_('game annulled: %s points#tpool_table'), 0 );
   }
   else //TPOINTSTYPE_HAHN
   {
      $arr[] = sprintf( T_('game ended by score or draw: %s points for every block of %s score-points#tpool_table'),
         1, $tpoints->ScoreBlock );
      $arr[] = sprintf( T_('game ended by resignation (%s points), timeout (%s points), forfeit (%s points), no-result (%s points)#tpool_table'),
         $tpoints->PointsResignation, $tpoints->PointsTimeout, $tpoints->PointsForfeit, $tpoints->PointsNoResult );
      $arr[] = sprintf( T_('max. points per game: %s points#tpool_table'), $tpoints->MaxPoints )
         . ( ($tpoints->Flags & TPOINTS_FLAGS_SHARE_MAX_POINTS) ? $sep . T_('share points for game ended by score or draw#tpool_table') : '' )
         . ( ($tpoints->Flags & TPOINTS_FLAGS_NEGATIVE_POINTS) ? $sep . T_('negative points allowed#tpool_table') : '' );
      $arr[] = sprintf( T_('game annulled: %s points#tpool_table'), 0 );
   }
   $notes[] = $arr;

   $notes[] = sprintf( T_('[%s] = Tie-Breaker SODOS = Sum of Defeated Opponents Score'), T_('SODOS#tourney') );
   $notes[] = array(
      sprintf( T_('[%s] = Rank of user within one pool (1=Highest rank); Format "R (CR) %s"#tpool'),
               T_('Rank#tpool'), $img_pool_winner ),
      T_('R = (optional) rank set by tournament director, really final only at end of tournament round#tpool'),
      sprintf( T_('R = \'%s\' = user withdrawing from next round#tpool'), span('bold', NO_VALUE) ),
      T_('CR = preliminary calculated rank, omitted when it can\'t be calculated or identical to rank R#tpool'),
      sprintf( T_('%s = marks user as pool winner (to advance to next round, or mark for final result)#tpool'),
         $img_pool_winner ),
   );

   return $notes;
}//build_pool_notes
?>
