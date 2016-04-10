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

chdir('..');
require_once 'include/std_functions.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_result.php';
require_once 'tournaments/include/tournament_result_control.php';

$GLOBALS['ThePage'] = new Page('TournamentResultList');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.list_results');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.list_results');
   $my_id = $player_row['ID'];

   $tid = (int) @$_REQUEST['tid'];
   $tourney = TournamentCache::load_cache_tournament( 'Tournament.list_results.find_tournament', $tid );
   $allow_edit_tourney = TournamentHelper::allow_edit_tournaments($tourney, $my_id);

   $page = "list_results.php";

   $tresult_control = new TournamentResultControl( /*full*/true, $page, $tourney, $allow_edit_tourney, /*limit*/-1 );
   $tresult_control->build_tournament_result_table( 'Tournament.list_results' );


   $title = T_('Tournament Results (Hall of Fame)');
   start_page( $title, true, $logged_in, $player_row );

   echo "<h3 class=Header>$title</h3>\n",
      "<h3 class=Header>", $tourney->build_info(5), "</h3>\n";

   echo $tresult_control->make_table_tournament_results();


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('All tournament results')] = "tournaments/list_results.php?tid=$tid";
   $menu_array[T_('My tournament results')] =
      "tournaments/list_results.php?tid=$tid".URI_AMP."user=".urlencode($player_row['Handle']);
   if ( $allow_edit_tourney )
   {
      $menu_array[T_('Edit results#tourney')] =
         array( 'url' => "tournaments/edit_results.php?tid=$tid", 'class' => 'TAdmin' );
      $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );
   }

   end_page(@$menu_array);
}

?>
