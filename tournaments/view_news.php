<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once( 'include/std_functions.php' );
require_once( 'include/std_classes.php' );
require_once( 'tournaments/include/tournament_cache.php' );
require_once( 'tournaments/include/tournament_helper.php' );
require_once( 'tournaments/include/tournament_news.php' );
require_once( 'tournaments/include/tournament_participant.php' );
require_once( 'tournaments/include/tournament_utils.php' );

$GLOBALS['ThePage'] = new Page('TournamentNewsView');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.view_news');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.view_news');
   $my_id = $player_row['ID'];

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;
   $tnews_id = (int) @$_REQUEST['tn'];
   if( $tid == 0 || $tnews_id < 0 )
      error('invalid_args', "Tournament.view_news.check_args($tid,$tnews_id)");

   $tourney = TournamentCache::load_cache_tournament( 'Tournament.view_news.find_tournament', $tid );

   // init
   $is_admin = TournamentUtils::isAdmin();
   $allow_edit_tourney = TournamentHelper::allow_edit_tournaments($tourney, $my_id);
   $is_participant = ($allow_edit_tourney)
      ? true
      : TournamentParticipant::isTournamentParticipant( $tid, $my_id );

   $qsql = TournamentNews::build_view_query_sql( $tnews_id, $tid, /*tn-stat*/null,
      $allow_edit_tourney, $is_participant );
   $qsql->merge( TournamentNews::build_query_sql() );
   $tnews = TournamentNews::load_tournament_news_entry_by_query( $qsql );
   if( is_null($tnews) )
      error('unknown_tournament_news', "Tournament.view_news.find_tournament_news($tid,$tnews_id)");

   $page = "view_news.php?";


   $pagetitle = sprintf( T_('Tournament News View #%d'), $tnews_id );
   $title = sprintf( T_('Tournament News of [%s]'), $tourney->Title );
   start_page($pagetitle, true, $logged_in, $player_row );
   echo "<h3 class=Header>". $title . "</h3>\n";

   echo "<br>\n", TournamentNews::build_tournament_news($tnews);


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Tournament news archive')] = "tournaments/list_news.php?tid=$tid";
   if( $allow_edit_tourney ) # for TD
   {
      $menu_array[T_('Add news#tnews')] =
         array( 'url' => "tournaments/edit_news.php?tid=$tid", 'class' => 'TAdmin' );
      $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );
   }

   end_page(@$menu_array);
}

?>
