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
require_once 'include/std_classes.php';
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_gui_helper.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_pool_classes.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_rules.php';
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

   $games_factor = TournamentHelper::determine_games_factor( $tid );

   if ( $allow_view )
   {
      $tprops = TournamentCache::load_cache_tournament_properties( 'Tournament.pool_view', $tid );
      $need_trating = $tprops->need_rating_copy();

      $tpool_iterator = TournamentCache::load_cache_tournament_pools( 'Tournament.pool_view.load_pools',
         $tid, $round, $need_trating, /*TP-lastmove*/true, $use_pool_cache );
      $poolTables = new PoolTables( $tpool_iterator );
      $count_players = $tpool_iterator->getItemCount();

      $tg_iterator = TournamentCache::load_cache_tournament_games( 'Tournament.pool_view', $tid, $tround->ID );
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
         $pn_formatter = new PoolNameFormatter( $tround->PoolNamesFormat );
         $pool_label = PoolViewer::format_pool_label( $tourney->Type, $my_tpool->Tier, $my_tpool->Pool );
         $pool_link = anchor("#$pool_label", $pn_formatter->format($my_tpool->Tier, $my_tpool->Pool) );
         echo sprintf( T_('You are playing in %s.#tpool'), $pool_link ), "<br>\n";
      }
      echo "<br>\n";

      if ( $tourney->Rounds > 1 )
      {
         $out = array();
         for ( $r=1; $r <= $tourney->Rounds; $r++ )
            $out[] = anchor( $base_path."tournaments/roundrobin/view_pools.php?tid=$tid".URI_AMP."round=$r",
               sprintf( T_('View Pools (Round #%s)'), $r ));
         echo implode(', ', $out), "<br><br>\n";
      }

      $poolViewer = new PoolViewer( $tid, $page, $tourney->Type, $poolTables, $tround->PoolNamesFormat, $games_factor,
         ($need_trating ? 0 : PVOPT_NO_TRATING) | ($edit ? PVOPT_EDIT_RANK : 0) | ($use_pool_cache ? PVOPT_NO_ONLINE : 0) );
      if ( $edit )
         $poolViewer->setEditCallback( 'pool_user_edit_rank' );
      $poolViewer->init_pool_table();
      $poolViewer->make_pool_table();
      $poolViewer->echo_pool_table();
   }//allow_view

   $trnd_notes = $tround->build_notes_props( $games_factor, $tourney->Type, false );
   $notes = TournamentGuiHelper::build_tournament_pool_notes($tpoints, /*pool-view*/true );
   $notes[] = null;
   $notes[] = array_merge( array( $trnd_notes[0] ), $trnd_notes[1] );
   echo_notes( 'tpoolnotesTable', T_('Tournament Pool notes'), $notes, true, false );


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   if ( $allow_view )
      $menu_array[T_('View Pools')] = "tournaments/roundrobin/view_pools.php?tid=$tid";
   if ( in_array($tourney->Status, TournamentHelper::get_view_data_status()) )
      $menu_array[T_('All running games')] = "show_games.php?tid=$tid".URI_AMP."uid=all";
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

?>
