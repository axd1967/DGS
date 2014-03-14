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

   if ( $allow_view )
   {
      $tprops = TournamentCache::load_cache_tournament_properties( 'Tournament.pool_view', $tid );
      $need_trating = $tprops->need_rating_copy();
      $games_per_challenge = TournamentRoundHelper::determine_games_per_challenge( $tid );

      $tpool_iterator = new ListIterator( 'Tournament.pool_view.load_pools' );
      $tpool_iterator = TournamentPool::load_tournament_pools( $tpool_iterator, $tid, $round, 0,
         TPOOL_LOADOPT_USER | ( $need_trating ? TPOOL_LOADOPT_TRATING : 0 ) );
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
      echo sprintf( T_('Round summary (%s players): %s games started, %s finished, %s running#tpool'),
                    $count_players, $counts['all'], $counts['finished'], $counts['run'] ),
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
         ($need_trating ? 0 : PVOPT_NO_TRATING) | ($edit ? PVOPT_EDIT_RANK : 0) );
      if ( $edit )
         $poolViewer->setEditCallback( 'pool_user_edit_rank' );
      $poolViewer->init_pool_table();
      $poolViewer->make_pool_table();
      $poolViewer->echo_pool_table();
   }//allow_view

   echo_notes( 'edittournamentpoolnotesTable', T_('Tournament Pool notes'), build_pool_notes($tpoints), true, false );


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
   $img_pool_winner = echo_image_tourney_pool_winner();
   //TODO TODO adjust for TournamentPoints; forfeit
   $notes[] =
      sprintf( T_('[%s] 1..n: \'#\' = running game, \'-\' = no game, %s#tpool'),
         T_('Position#tourneyheader'), span('MatrixSelf', T_('self#tpool_table'), $mfmt) )
      . ",<br>\n"
      . sprintf( T_('\'%s\' on colored background (%s)#tpool_table'), T_('Points#tourney'),
            sprintf( ' %s %s %s ',
               span('MatrixWon', T_('game won#tpool_table')   . ' = ' . $tpoints->PointsWon, $mfmt),
               span('MatrixLost', T_('game lost#tpool_table') . ' = ' . $tpoints->PointsLost, $mfmt),
               span('MatrixJigo', T_('game draw#tpool_table') . ' = ' . $tpoints->PointsDraw, $mfmt) ));
   $notes[] = sprintf( T_('[%s] in format "wins : losses" = number of wins and losses for user'), T_('#Wins#tourney') );
   $notes[] = sprintf( T_('[%s] = points calculated from wins, losses and jigo for user'), T_('Points#header') );
   $notes[] = sprintf( T_('[%s] = Tie-Breaker SODOS = Sum of Defeated Opponents Score'), T_('SODOS#tourney') );
   $notes[] = array(
      sprintf( T_('[%s] = Rank of user within one pool (1=Highest rank); Format "R (CR) %s"#tpool'),
               T_('Rank#tpool'), $img_pool_winner ),
      T_('R = (optional) rank set by tournament director, really final only at end of tournament round#tpool'),
      T_('R = \'---\' = user retreating from next round (temporary mark)#tpool'),
      T_('CR = preliminary calculated rank, omitted when it can\'t be calculated or identical to rank R#tpool'),
      sprintf( T_('%s = marks user as pool winner (to advance to next round, or mark for final result)#tpool'), $img_pool_winner ),
   );

   return $notes;
}//build_pool_notes
?>
