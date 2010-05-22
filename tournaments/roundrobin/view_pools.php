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
require_once 'include/std_classes.php';
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_pool_classes.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentPoolView');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.roundrobin.view_pools');
   $my_id = $player_row['ID'];

   $page = "view_pools.php";

/* Actual REQUEST calls used
     tid=[&round=]                  : view T-pool
*/

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;
   $round = (int) @$_REQUEST['round'];
   if( $round < 0 ) $round = 0;
   $edit = (get_request_arg('edit', 0)) ? 1 : 0;

   $tourney = Tournament::load_tournament($tid);
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.pool_view.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   if( !$ttype->need_rounds )
      error('tournament_edit_rounds_not_allowed', "Tournament.pool_view.find_tournament($tid)");

   // create/edit allowed?
   $allow_edit_tourney = $tourney->allow_edit_tournaments( $my_id );
   if( $edit && !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.pool_view.find_tournament($tid)");

   // load existing T-round
   if( $round < 1 || $round > $tourney->CurrentRound )
      $round = $tourney->CurrentRound;
   $tround = TournamentRound::load_tournament_round( $tid, $round );
   if( is_null($tround) )
      error('bad_tournament', "Tournament.pool_view.find_tournament_round($tid,$round,$my_id)");

   $tprops = TournamentProperties::load_tournament_properties( $tid );
   if( is_null($tprops) )
      error('bad_tournament', "Tournament.edit_pools.find_tournament_props($tid,$my_id)");

   // init
   $errors = array();
   $need_trating = ( $tprops->RatingUseMode != TPROP_RUMODE_CURR_FIX );

   $tpool_iterator = new ListIterator( 'Tournament.pool_view.load_pools' );
   $tpool_iterator = TournamentPool::load_tournament_pools( $tpool_iterator, $tid, $round, 0,
      TPOOL_LOADOPT_USER | ( $need_trating ? TPOOL_LOADOPT_TRATING : 0 ) );
   $poolTables = new PoolTables( $tround->Pools );
   $poolTables->fill_pools( $tpool_iterator );
   $count_players = $tpool_iterator->getItemCount();

   $tg_iterator = new ListIterator( 'Tournament.pool_view.load_tgames' );
   $tg_iterator = TournamentGames::load_tournament_games( $tg_iterator, $tid, $tround->ID, 0, /*all-stati*/null );
   $poolTables->fill_games( $tg_iterator );
   $counts = $poolTables->count_games();


   // --------------- Tournament-Pools EDIT form --------------------

   $title = sprintf( T_('Pools of Tournament #%s for round %s'), $tid, $round );
   start_page( $title, true, $logged_in, $player_row );
   echo "<h2 class=Header>", $tourney->build_info(3, sprintf(T_('Round #%s'), $round)), "</h2>\n";

   if( count($errors) )
   {
      echo "<table><tr>",
         TournamentUtils::buildErrorListString( T_('There are some errors'), $errors, 1, false ),
         "</tr></table>\n";
   }

   echo sprintf( T_('Round summary (%s players): %s games started, %s finished, %s running'),
                 $count_players, $counts['all'], $counts['finished'], $counts['run'] ),
      "<br>\n";

   $my_tpool = $tpool_iterator->getIndexValue( 'uid', $my_id, 0 );
   if( $my_tpool )
      echo sprintf( T_('You are playing in Pool %s.'), $my_tpool->Pool ), "<br>\n";
   echo "<br>\n";

   $poolViewer = new PoolViewer( $tid, $page, $poolTables,
      ($need_trating ? 0 : PVOPT_NO_TRATING) | ($edit ? PVOPT_EDIT_RANK : 0) );
   if( $edit )
      $poolViewer->setEditCallback( 'pool_user_edit_rank' );
   $poolViewer->init_table();
   $poolViewer->make_table();
   $poolViewer->echo_table();

   echo_notes( 'edittournamentpoolnotesTable', T_('Tournament pool notes'), build_pool_notes(), true, false );


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('View Pools')] = "tournaments/roundrobin/view_pools.php?tid=$tid";
   if( $allow_edit_tourney )
   {
      if( $tround->Status == TROUND_STATUS_POOL )
         $menu_array[T_('Edit pools')] =
            array( 'url' => "tournaments/roundrobin/edit_pools.php?tid=$tid", 'class' => 'TAdmin' );
      if( $tround->Status == TROUND_STATUS_PLAY )
         $menu_array[T_('Edit ranks')] =
            array( 'url' => "tournaments/roundrobin/edit_ranks.php?tid=$tid", 'class' => 'TAdmin' );
      $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );
   }

   end_page(@$menu_array);
}


// callback-func for edit-column in pools
function pool_user_edit_rank( &$poolviewer, $uid )
{
   global $base_path, $tid;
   return anchor( $base_path."tournaments/roundrobin/edit_ranks.php?tid=$tid".URI_AMP."uid=$uid",
         image( $base_path.'images/edit.gif', T_('Edit Rank'), null, 'class="InTextImage"' ) );
}

/*! \brief Returns array with notes about tournament pools. */
function build_pool_notes()
{
   $notes = array();
   $notes[] = sprintf( T_('Pools are ranked by Tie-breakers: %s'),
      implode(', ', array( T_('Points#tiebreaker'), T_('SODOS#tiebreaker') ) ));

   $mfmt = MINI_SPACING . '%s' . MINI_SPACING;
   $img_next_round = echo_image_tourney_next_round();
   $notes[] =
      sprintf( T_('[%s] 1..n: \'#\' = running game, \'-\' = no game, %s'),
         T_('Place#pool_header'), span('MatrixSelf', T_('self#pool_table'), $mfmt) )
      . ",<br>\n"
      . sprintf( T_('\'%s\' on colored background (%s)'), T_('Points#pool_header'),
            sprintf( ' %s %s %s ',
               span('MatrixWon', T_('game won#pool_table')   . ' = 2', $mfmt),
               span('MatrixLost', T_('game lost#pool_table') . ' = 0', $mfmt),
               span('MatrixJigo', T_('game draw#pool_table') . ' = 1', $mfmt) ));
   $notes[] = sprintf( T_('[%s] in format "wins : losses" = number of wins and losses for user'), T_('#Wins#pool_header') );
   $notes[] = sprintf( T_('[%s] = points calculated from wins, losses and jigo for user'), T_('Points#pool_header') );
   $notes[] = sprintf( T_('[%s] = Tie-Breaker SODOS = Sum of Defeated Opponents Score'), T_('SODOS#pool_header') );
   $notes[] = array(
      sprintf( T_('[%s] = Rank of user within one pool (1=Highest rank); Format "R (CR) %s"'),
               T_('Rank#pool_header'), $img_next_round ),
      T_('R = (optional) rank set by tournament director, really final only at end of tournament round'),
      T_('R = \'---\' = user retreating from next round (temporary mark)'),
      T_('CR = preliminary calculated rank, omitted when it can\'t be calculated or identical to rank R'),
      sprintf( T_('%s = marks user to advance to next round, or mark for final result'), $img_next_round ),
   );

   return $notes;
}//build_pool_notes
?>
