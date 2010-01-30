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

chdir('..');
require_once( 'include/std_functions.php' );
require_once( 'include/gui_functions.php' );
require_once( 'include/form_functions.php' );
require_once( 'include/table_infos.php' );
require_once( 'include/rating.php' );
require_once( 'include/game_functions.php' );
require_once( 'tournaments/include/tournament_utils.php' );
require_once( 'tournaments/include/tournament.php' );
require_once( 'tournaments/include/tournament_participant.php' );
require_once( 'tournaments/include/tournament_properties.php' );
require_once( 'tournaments/include/tournament_rules.php' );
require_once( 'tournaments/include/tournament_ladder_props.php' );

$GLOBALS['ThePage'] = new Page('Tournament');


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
      error('unknown_tournament', "Tournament.view_tournament.find_tournament($tid)");
   $allow_edit_tourney = $tourney->allow_edit_tournaments( $my_id );

   // init

   // TP-count
   $tp_counts = TournamentParticipant::count_tournament_participants( $tid );
   $tourney->setTP_Counts($tp_counts);
   $tp_count_all = (int)@$tp_counts[TPCOUNT_STATUS_ALL];
   unset($tp_counts[TPCOUNT_STATUS_ALL]);

   $tprops = TournamentProperties::load_tournament_properties( $tid );
   $trule  = TournamentRules::load_tournament_rule( $tid );

   $tt_props = null; // T-type-specific props
   if( $tourney->Type == TOURNEY_TYPE_LADDER )
      $tt_props = TournamentLadderProps::load_tournament_ladder_props( $tid );


   $page_tdirs   = "tournaments/list_directors.php?tid=$tid";
   $page_tourney = "tournaments/view_tournament.php?tid=$tid";


   $page_title = sprintf( T_('Tournament #%s'), $tid );
   start_page( $page_title, true, $logged_in, $player_row );

   // --------------- Information -----------------------------------

   $title = sprintf( '%s %s %s',
                     Tournament::getScopeText($tourney->Scope),
                     Tournament::getTypeText($tourney->Type),
                     sprintf( T_('Tournament #%s - General Information'), $tid ));
   section( 'info', $title );
   echo
      T_('This page contains all necessary information and links to participate in the tournament.'),
      MINI_SPACING,
      T_('There are different sections:#tourney'), "\n<ul>",
         "\n<li>", anchor( "$base_path$page_tourney#title", T_('Tournament description') ),
         "\n<li>", anchor( "$base_path$page_tourney#rules", T_('Tournament ruleset') ),
         "\n<li>", anchor( "$base_path$page_tourney#registration", T_('Tournament registration information') ),
         "\n<li>", anchor( "$base_path$page_tourney#games", T_('Tournament games') ),
         "\n<li>", anchor( "$base_path$page_tourney#result", T_('Tournament results') ),
      "</ul>\n",
      make_html_safe(
         T_('When you have a question about the tournament, please send a message '
            . 'to one of the tournament directors or ask in the <home forum/index.php>Tournaments forum</home>.'),
         true ),
      "\n";

   // ------------- Section Menu

   $sectmenu = array();
   $sectmenu[T_('Tournament directors')] = $page_tdirs;
   if( $allow_edit_tourney )
      $sectmenu[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );
   make_menu( $sectmenu, false);

   // --------------- Title ---------------------

   echo
      "<hr>\n", '<a name="title">', "\n",
      "<h2 class=Header>" . make_html_safe($tourney->Title, true) . "</h2>\n",
      make_html_safe($tourney->Description, true),
      "\n";

   // --------------- Status --------------------

   echo "<hr>\n", '<a name="result">', "\n";
   section( 'tournament', T_('Tournament Status#T_view') );

   $reg_user_status = TournamentParticipant::isTournamentParticipant($tid, $my_id);
   $reg_user_info   = TournamentParticipant::getStatusUserInfo($reg_user_status);

   $itable = new Table_info('tstatus');
   $itable->add_sinfo( T_('Current Tournament Status:'), $tourney->getStatusText($tourney->Status) );
   $itable->add_sinfo( T_('Current Tournament Round:'), $tourney->formatRound() );
   if( $reg_user_info )
      $itable->add_sinfo( T_('Registration status:'), span('TUserStatus', $reg_user_info) );

   echo $itable->make_table();


   // --------------- Rules -----------------------------------------

   echo "<hr>\n", '<a name="rules">', "\n";
   section( 'tournament', T_('Rules#T_view') );

   if( !is_null($trule) )
      echo_tournament_rules( $trule );


   // --------------- Registration ----------------------------------

   echo "<hr>\n", '<a name="registration">', "\n";
   section( 'tournament', T_('Registration#T_view') );

   if( !is_null($tprops) )
      echo_tournament_registration( $tprops );

   // number of registered users
   echo sprintf( T_('Registrations for this tournament: %s user(s)'), $tp_count_all ),
      "<br>\n<ul>";
   foreach( $tp_counts as $t_status => $cnt )
   {
      echo "  <li>",
         sprintf( T_('%3d users on status [%s]'), $cnt, TournamentParticipant::getStatusText($t_status) ),
         "\n";
   }
   echo "</ul>\n";

   // ------------- Section Menu

   $sectmenu = array();
   $sectmenu[T_('Tournament participants')] = "tournaments/list_participants.php?tid=$tid";

   $reg_user_str = TournamentParticipant::getLinkTextRegistration($tid, $reg_user_status);
   if( $reg_user_str )
      $sectmenu[$reg_user_str] = "tournaments/register.php?tid=$tid"; # for user

   if( $allow_edit_tourney )
      $sectmenu[T_('Edit participants')] =
         array( 'url' => "tournaments/edit_participant.php?tid=$tid", 'class' => 'TAdmin' ); # for TD

   make_menu( $sectmenu, false);


   // --------------- Games -----------------------------------------

   echo "<hr>\n", '<a name="games">', "\n";
   section( 'tournament', T_('Games#T_view') );

   // show tourney-type-specific properties
   $tt_notes = null;
   if( !is_null($tt_props) )
      $tt_notes = $tt_props->build_notes_props();
   if( !is_null($tt_notes) )
      echo_notes( 'ttprops', $tt_notes[0], $tt_notes[1], false );

   // ------------- Section Menu

   $sectmenu = array();
   if( $tourney->Type == TOURNEY_TYPE_LADDER ) //TODO only show on certain stati
      if( $tourney->Status == TOURNEY_STATUS_PLAY || $tourney->Status == TOURNEY_STATUS_CLOSED )
         $sectmenu[T_('View Ladder')] = "tournaments/ladder/view.php?tid=$tid";

   make_menu( $sectmenu, false);


   // --------------- Results ---------------------------------------

   echo "<hr>\n", '<a name="result">', "\n";
   section( 'tournament', T_('Results#T_view') );

   /* TODO
   echo
      "[TODO] Results (Show Winners, Show intermediate results (link))",
      "\n";
   */


   end_page();
}


function echo_tournament_rules( $trule )
{
   $adj_komi = array();
   if( $trule->AdjKomi )
      $adj_komi[] = sprintf( T_('adjusted by %s points#trules_komi'),
            spacing( ($trule->AdjKomi > 0 ? '+' : '') . sprintf('%.1f', $trule->AdjKomi), 1, 'b') );
   if( $trule->JigoMode == JIGOMODE_ALLOW_JIGO )
      $adj_komi[] = T_('Jigo allowed#trules_komi');
   elseif( $trule->JigoMode == JIGOMODE_NO_JIGO )
      $adj_komi[] = T_('No Jigo allowed#trules_komi');

   $adj_handi = array();
   if( $trule->AdjHandicap )
      $adj_handi[] = sprintf( T_('adjusted by %s stones#trules_handi'),
            spacing( ($trule->AdjHandicap > 0 ? '+' : '') . $trule->AdjHandicap, 1, 'b') );
   if( $trule->MinHandicap > 0 && $trule->MaxHandicap < MAX_HANDICAP )
      $adj_handi[] = sprintf( T_('limited by min. %s and max. %s stones#trules_handi'),
            $trule->MinHandicap, min( MAX_HANDICAP, $trule->MaxHandicap) );
   elseif( $trule->MinHandicap > 0 )
      $adj_handi[] = sprintf( T_('limited by min. %s stones#trules_handi'), $trule->MinHandicap );
   elseif( $trule->MaxHandicap < MAX_HANDICAP )
      $adj_handi[] = sprintf( T_('limited by max. %s stones#trules_handi'), $trule->MaxHandicap );

   $itable = new Table_info('gamerules');
   $itable->add_sinfo( T_('Board Size:#trules'), $trule->Size .' x '. $trule->Size );
   $itable->add_sinfo( T_('Handicap Type:#trules'),
         TournamentRules::getHandicaptypeText($trule->Handicaptype) );
   $itable->add_sinfo( T_('Handicap:#trules'),
      ( $trule->needsCalculatedHandicap()
            ? T_('calculated stones#trules_handi')
            : sprintf( T_('%s stones#trules_handi'), $trule->Handicap) )
      . ', ' .
      ( $trule->needsCalculatedKomi()
            ? T_('calculated komi#trules_handi')
            : sprintf( T_('%s komi#trules_handi'), $trule->Komi) ));
   if( count($adj_handi) )
      $itable->add_sinfo( T_('Handicap adjustment:#trules_handi'), implode(', ', $adj_handi) );
   if( count($adj_komi) )
      $itable->add_sinfo( T_('Komi adjustment:#trules_komi'), implode(', ', $adj_komi) );
   if( ENA_STDHANDICAP )
      $itable->add_sinfo( T_('Standard placement:#trules_handi'), yesno($trule->StdHandicap) );
   $itable->add_sinfo( T_('Main time:#trules'), TimeFormat::echo_time($trule->Maintime)
         . ( ($trule->Byotime == 0) ? SMALL_SPACING.T_('(absolute time)#trules') : '' ));
   if( $trule->Byotime > 0 )
      $itable->add_sinfo(
         TimeFormat::echo_byotype($trule->Byotype) . ':',
         TimeFormat::echo_time_limit( -1, $trule->Byotype, $trule->Byotime, $trule->Byoperiods, 0) );
   $itable->add_sinfo( T_('Clock runs on weekends:#trules'), yesno($trule->WeekendClock) );
   $itable->add_sinfo( T_('Rated:#trules'), yesno($trule->Rated) );

   if( $trule->Notes != '')
      echo make_html_safe( $trule->Notes, true ), "<br><br>\n";

   echo T_('The following ruleset is used for this tournament'), ':',
      $itable->make_table(),
      "<br>\n";
}//echo_tournament_rules


function echo_tournament_registration( $tprops )
{
   $arr_tprops = array();

   // limit register end-time
   if( $tprops->RegisterEndTime )
      $arr_tprops[] = sprintf( T_('Registration phase ends on [%s]'),
            TournamentUtils::formatDate($tprops->RegisterEndTime) );

   // limit participants
   if( $tprops->MinParticipants > 0 && $tprops->MaxParticipants > 0 )
      $arr_tprops[] = sprintf( T_('Tournament needs: min. %s and max. %s participants'),
            $tprops->MinParticipants, $tprops->MaxParticipants );
   elseif( $tprops->MinParticipants > 0 )
      $arr_tprops[] = sprintf( T_('Tournament needs: min. %s participants'), $tprops->MinParticipants );
   elseif( $tprops->MaxParticipants > 0 )
      $arr_tprops[] = sprintf( T_('Tournament needs: max. %s participants'), $tprops->MaxParticipants );

   // use-rating-mode, limit user-rating
   $arr_tprops[] = TournamentProperties::getRatingUseModeText( $tprops->RatingUseMode, false );
   if( $tprops->UserRated )
      $arr_tprops[] = sprintf( T_('User rating must be between [%s - %s].'),
            echo_rating( $tprops->UserMinRating, false ),
            echo_rating( $tprops->UserMaxRating, false ));

   // limit games-number
   if( $tprops->UserMinGamesFinished > 0 )
      $arr_tprops[] = sprintf( T_('User must have at least %s finished games.'),
            $tprops->UserMinGamesFinished );
   if( $tprops->UserMinGamesRated > 0 )
      $arr_tprops[] = sprintf( T_('User must have at least %s rated finished games.'),
            $tprops->UserMinGamesRated );

   if( count($arr_tprops) )
      echo T_('To register for this tournament the following criteria must match'), ':',
           '<ul><li>', implode("\n<li>", $arr_tprops), "</ul>\n";
   if( $tprops->Notes != '' )
      echo make_html_safe($tprops->Notes, true), "<br><br>\n";
}//echo_tournament_registration
?>
