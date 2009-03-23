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

$TranslateGroups[] = "Tournament";

chdir('..');
require_once( 'include/std_functions.php' );
require_once( 'include/gui_functions.php' );
require_once( 'include/form_functions.php' );
require_once( 'tournaments/include/tournament.php' );
require_once( 'tournaments/include/tournament_participant.php' );

$ThePage = new Page('Tournament');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.view_tournament');
   $my_id = $player_row['ID'];

   $tid = (int) @$_REQUEST['tid'];
   $tourney = Tournament::load_tournament( $tid );
   if( is_null($tourney) )
      error('unknown_tournament', "view_tournament.find_tournament($tid)");

   $allow_edit_tourney = $tourney->allow_edit_tournaments( $my_id );

   $page_tdirs   = "tournaments/list_directors.php?tid=$tid";
   $page_tourney = "tournaments/view_tournament.php?tid=$tid";


   $page_title = sprintf( T_('Tournament #%s'), $tid );
   start_page( $page_title, true, $logged_in, $player_row );

   // --------------- Information -----------------------------------

   $title = sprintf( T_('%1$s %2$s Tournament - General Information'),
         Tournament::getScopeText($tourney->Scope),
         Tournament::getTypeText($tourney->Type) );
   section( 'info', $title );
   echo
      make_html_safe(
         sprintf( T_('This page contains all necessary information and links to participate in the tournament. '
                  . 'There are different sections with a <home %1$s>description of the tournament</home>, '
                  . 'the used <home %2$s>rulesets</home>, <home %3$s>registration information</home> '
                  . 'and the <home %4$s>tournament results</home>.'),
            $page_tourney.'#title',
            $page_tourney.'#rules',
            $page_tourney.'#registration',
            $page_tourney.'#result' ), true ),
      "<br><br>\n",
      make_html_safe(
         sprintf( T_('When you have a question about the tournament, please send '
                  . 'a message to one of the <home %s>tournament directors</home> '
                  . 'or ask in the <home forum/index.php>Tournaments forum</home>.'),
            $page_tdirs ), true ),
      "\n";

   // ------------- Section Menu

   $sectmenu = array();
   $sectmenu[T_('Tournament directors')] = $page_tdirs;
   if( $tourney->allow_edit_tournaments($my_id) )
      $sectmenu[T_('Manage this tournament')] =
         array( 'url' => "tournaments/edit_tournament.php?tid=$tid", 'class' => 'TAdmin' );
   make_menu( $sectmenu, false);

   // --------------- Title ---------------------

   echo
      "<hr>\n", '<a name="title">', "\n",
      "<h2 class=Header>" . make_html_safe($tourney->Title, true) . "</h2>\n",
      make_html_safe($tourney->Description, true),
      "\n";

   // --------------- Rules -----------------------------------------

   echo "<hr>\n", '<a name="rules">', "\n";
   section( 'tournament', T_('Rules#T_view') );
   echo
      "[TODO] Show Ruleset", //TODO
      "\n";

   // --------------- Registration ----------------------------------

   echo "<hr>\n", '<a name="registration">', "\n";
   section( 'tournament', T_('Registration#T_view') );
   $cnt_status = ($allow_edit_tourney) ? NULL : TP_STATUS_REGISTER;
   $tp_counts = TournamentParticipant::count_tournament_participants( $tid, $cnt_status );
   $td_view = "<br>\n";
   if( $allow_edit_tourney && @$tp_counts[TP_STATUS_REGISTER] != $tp_counts['*'])
   {
      $td_view .= "<ul>";
      foreach( $tp_counts as $t_status => $cnt )
         if( $t_status != '*' )
            $td_view .= "  <li>" . sprintf( T_('Status [%s] : %d users'), $t_status, $cnt ) . "\n";
      $td_view .= "</ul>\n";
   }
   $cnt_participants = (int)@$tp_counts[TP_STATUS_REGISTER];

   $reg_user_status = TournamentParticipant::isTournamentParticipant( $tid, $my_id );
   $reg_user_info = ( count($tourney->allow_register($my_id, true)) )
      ? '' : TournamentParticipant::getStatusText( $reg_user_status, false, true );

   echo "\n",
      sprintf( T_('Complete registrations for this tournament: %s user(s)'), $cnt_participants ),
      $td_view,
      "<br>\n",
      ( ($reg_user_info)
            ? sprintf( '%s%s<span class="TUserStatus">%s</span>',
                       T_('Registration status:'), SMALL_SPACING, $reg_user_info )
            : '' ),
      "\n";

   // ------------- Section Menu

   $sectmenu = array();
   $sectmenu[T_('Tournament participants')] = "tournaments/list_participants.php?tid=$tid";

   $reg_user_str = TournamentParticipant::getLinkTextRegistration( $tourney, $my_id, $reg_user_status );
   if( $reg_user_str )
      $sectmenu[$reg_user_str] = "tournaments/register.php?tid=$tid"; # for user

   if( $allow_edit_tourney )
      $sectmenu[T_('Edit participants')] =
         array( 'url' => "tournaments/edit_participant.php?tid=$tid", 'class' => 'TAdmin' ); # for TD

   make_menu( $sectmenu, false);

   // --------------- Results ---------------------------------------

   echo "<hr>\n", '<a name="result">', "\n";
   section( 'tournament', T_('Results#T_view') );
   echo
      "[TODO] Results (Show Winners, Show intermediate results (link))", //TODO
      "\n";


   end_page();
}
?>
