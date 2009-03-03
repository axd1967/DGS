<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = 'Tournament';

chdir('..');
require_once( 'include/std_functions.php' );
require_once( 'include/gui_functions.php' );
require_once( 'include/form_functions.php' );
require_once( 'tournaments/include/tournament.php' );

$ThePage = new Page('Tournament');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];

   $tid = (int) @$_REQUEST['tid'];
   $tourney = Tournament::load_tournament( $tid );
   if( is_null($tourney) )
      error('unknown_tournament', "view_tournament.find_tournament($tid)");


   $page_title = sprintf( T_('Tournament #%s'), $tid );
   start_page( $page_title, true, $logged_in, $player_row );

   $title =
      sprintf( "[%s Tournament] - %s",
         Tournament::getScopeText($tourney->Scope),
         make_html_safe($tourney->Title, true) );
   //echo "<h3 class=Header>" . $title . "</h3>\n";
   section( 'info', $title ); //T_('Information#T_view') );//TODO

   echo
      make_html_safe($tourney->Description, true),
      T_('Last changed date'), date(DATEFMT_TOURNAMENT, $tourney->Lastchanged),
      T_('Start time'), date(DATEFMT_TOURNAMENT, $tourney->StartTime),
      T_('End time'), date(DATEFMT_TOURNAMENT, $tourney->EndTime),
      T_('Type'), Tournament::getTypeText($tourney->Type),
      T_('Status'), Tournament::getStatusText($tourney->Status),
      "\n";

   section( 'tournament', T_('Rules#T_view') );
   echo
      "[TODO] Show Ruleset", //TODO
      "\n";

   section( 'tournament', T_('Registration#T_view') );
   echo
      "[TODO] Registration (TPs, register, invite, ask-TD)", //TODO
      "\n";

   section( 'tournament', T_('Results#T_view') );
   echo
      "[TODO] Results (Show Winners, Show intermediate results (link))", //TODO
      "\n";


   $menu_array = array();
   $menu_array[T_('Tournaments')] = "tournaments/list_tournaments.php";
   $menu_array[T_('Tournament directors')] = "tournaments/list_directors.php?tid=$tid";
   if( $tourney->allow_edit_tournaments($my_id) )
      $menu_array[T_('Edit this tournament')] = "tournaments/edit_tournament.php?tid=$tid";

   end_page(@$menu_array);
}

?>
