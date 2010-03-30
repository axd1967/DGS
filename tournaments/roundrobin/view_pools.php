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
require_once 'include/gui_functions.php';
require_once 'include/table_columns.php';
require_once 'include/form_functions.php';
require_once 'include/countries.php';
require_once 'include/rating.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_globals.php';
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

   $page = "view_pools.php?";

/* Actual REQUEST calls used
     tid=[&round=]                  : view T-pool
*/

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;
   $round = (int) @$_REQUEST['round'];
   if( $round < 0 ) $round = 0;

   $tourney = Tournament::load_tournament($tid);
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.pool_view.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );
   $allow_edit_tourney = $tourney->allow_edit_tournaments( $my_id );

   // init
   $errors = array();


   $title = sprintf( T_('Pools of Tournament #%s for round %s'), $tid, $round );
   start_page( $title, true, $logged_in, $player_row );
   echo "<h2 class=Header>", $tourney->build_info(2), "</h2>\n";

   if( count($errors) )
   {
      echo "<table><tr>",
         TournamentUtils::buildErrorListString( T_('There are some errors'), $errors, 1, false ),
         "</tr></table>\n";
   }


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('View Pools')] = "tournaments/roundrobin/view_pools.php?tid=$tid";
   if( $allow_edit_tourney )
   {
      $menu_array[T_('Edit pools')] =
         array( 'url' => "tournaments/roundrobin/edit_pools.php?tid=$tid", 'class' => 'TAdmin' );
      $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );
   }

   end_page(@$menu_array);
}
?>
