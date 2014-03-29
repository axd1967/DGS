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

chdir('..');
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'include/table_infos.php';
require_once 'include/rulesets.php';
require_once 'include/rating.php';
require_once 'include/game_functions.php';
require_once 'include/shape_control.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_gui_helper.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_ladder.php';
require_once 'tournaments/include/tournament_ladder_props.php';
require_once 'tournaments/include/tournament_news.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_result.php';
require_once 'tournaments/include/tournament_result_control.php';
require_once 'tournaments/include/tournament_rules.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('Tournament');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.view_tournament');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.view_tournament');

   $my_id = $player_row['ID'];

   $tid = (int) @$_REQUEST['tid'];
   $tourney = TournamentCache::load_cache_tournament( 'Tournament.view_tournament.find_tournament', $tid );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   $allow_edit_tourney = TournamentHelper::allow_edit_tournaments($tourney, $my_id);

   // init
   $page = "view_tournament.php?tid=$tid";
   $cnt_tstandings = $ttype->getCountTournamentStandings($tourney->Status);
   $show_tresult = TournamentResult::show_tournament_result( $tourney->Status );

   // TP-count
   $tp_all_counts = TournamentCache::count_cache_all_tournament_participants($tid);

   $my_tp = TournamentCache::load_cache_tournament_participant( 'Tournament.view_tournament', $tid, $my_id );
   $reg_user_status = ( $my_tp ) ? $my_tp->Status : false;
   $reg_user_info   = TournamentParticipant::getStatusUserInfo($reg_user_status);
   $reg_user_str = TournamentGuiHelper::getLinkTextRegistration($tid, $reg_user_status);

   $arr_tnews = TournamentCache::load_cache_tournament_news( 'Tournament.view_tournament.news',
      $tid, $allow_edit_tourney, $reg_user_status );
   $tprops = TournamentCache::load_cache_tournament_properties( 'Tournament.view_tournament', $tid );
   $trule = TournamentCache::load_cache_tournament_rules( 'Tournament.view_tournament', $tid );

   // user result state
   $tt_props = $tpoints = null; // T-type-specific props
   $tt_user_state = '';
   if ( $tourney->Type == TOURNEY_TYPE_LADDER )
   {
      $tt_props = TournamentCache::load_cache_tournament_ladder_props( 'Tournament.view_tournament', $tid, /*check*/false );
      $tl_iterator = TournamentLadder::load_cache_tournament_ladder( 'Tournament.view_tournament',
         $tid, $tprops->need_rating_copy(), /*need-TP.Fin*/false, $cnt_tstandings );
      $tl_rank = TournamentLadder::determine_ladder_rank( $tl_iterator, $my_id );
      $tt_user_state = ( $tl_rank > 0 )
         ? sprintf( T_('Your current ladder rank is #%s out of %s.'), $tl_rank, (int)@$tp_all_counts[1][TP_STATUS_REGISTER] )
         : NO_VALUE;
   }
   elseif ( $tourney->Type == TOURNEY_TYPE_ROUND_ROBIN )
   {
      $tt_props = TournamentCache::load_cache_tournament_round( 'Tournament.view_tournament',
         $tid, $tourney->CurrentRound, /*chk*/false );
      if ( $my_tp )
      {
         $tt_user_state = NO_VALUE;
         $tpool = TournamentPool::load_tournament_pool_user( $tid, $tourney->CurrentRound, $my_id ); // NOTE: not cached for now
         if ( $tpool && $tpool->Pool > 0 )
         {
            $tt_user_state = sprintf( T_('Rank %s in Pool %s'), $tpool->formatRankText(), $tpool->Pool );
            if ( $tpool->Rank > 0 )
               $tt_user_state .= SMALL_SPACING . '+ ' . $tpool->echoRankImage();
         }
      }

      $tpoints = TournamentCache::load_cache_tournament_points( 'Tournament.view_tournament', $tid, /*chk*/false );
   }


   $page_tdirs   = "tournaments/list_directors.php?tid=$tid";
   $page_tourney = "tournaments/view_tournament.php?tid=$tid";


   $page_title = sprintf( T_('Tournament #%s'), $tid );
   start_page( $page_title, true, $logged_in, $player_row );

   // --------------- Information -----------------------------------

   $title = sprintf( '%s %s %s',
                     Tournament::getScopeText($tourney->Scope),
                     Tournament::getTypeText($tourney->Type),
                     sprintf( T_('Tournament #%s - General Information'), $tid ));
   $base_page_tourney = $base_path . $page_tourney;
   section( 'TournamentInfo', $title );
   echo
      T_('This page contains all necessary information and links to participate in the tournament.'),
      MINI_SPACING,
      T_('There are different sections:#tourney'), "\n<ul>",
         "\n<li>", anchor( "$base_page_tourney#title", T_('Tournament Description') ),
         "\n<li>", anchor( "$base_page_tourney#news", T_('Tournament News#T_view') ),
         "\n<li>", anchor( "$base_page_tourney#status", T_('Tournament Status') ),
         ( $cnt_tstandings > 0
            ? "\n<li>" . anchor( "$base_page_tourney#standings", T_('Tournament Standings') )
            : ''),
         "\n<li>", anchor( "$base_page_tourney#rules", T_('Tournament Rules') ),
         "\n<li>", anchor( "$base_page_tourney#registration", T_('Tournament Registration') ),
         "\n<li>", anchor( "$base_page_tourney#games", T_('Tournament Games') ),
         ( $show_tresult
            ? "\n<li>" . anchor( "$base_page_tourney#result", T_('Tournament Results') )
            : ''),
      "</ul>\n",
      make_html_safe(
         T_('When you have a question about the tournament, please send a message '
            . 'to one of the tournament directors or ask in the <home forum/index.php>Tournaments forum</home>.'),
         true ),
      "\n";

   // ------------- Section Menu

   $sectmenu = array();
   $tourney->build_data_link( $sectmenu );
   if ( $reg_user_str )
      $sectmenu[$reg_user_str] = "tournaments/register.php?tid=$tid"; # for user
   $sectmenu[T_('Tournament directors')] = $page_tdirs;
   if ( $allow_edit_tourney )
      $sectmenu[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );
   make_menu( $sectmenu, false);

   // --------------- Title ---------------------

   echo
      "<hr>\n", name_anchor('title'),
      "<h2 class=Header>" . make_html_safe($tourney->Title, true) . "</h2>\n",
      make_html_safe($tourney->Description, true),
      "\n";

   // --------------- News ----------------------

   section( 'TournamentNews', T_('Tournament News#T_view'), 'news' );

   if ( count($arr_tnews) > 0 )
   {
      foreach ( $arr_tnews as $tnews )
         echo TournamentNews::build_tournament_news( $tnews );
   }
   else
      echo "<center>", T_('No tournament news.'), "</center><br>\n";

   // ------------- Section Menu

   $sectmenu = array();
   $sectmenu[T_('Tournament news archive')] = "tournaments/list_news.php?tid=$tid";

   make_menu( $sectmenu, false);

   // --------------- Status --------------------

   section( 'TournamentStatus', T_('Tournament Status'), 'status', true );

   $arr_locks = check_locks( $tourney );

   $itable = new Table_info('tstatus', TABLEOPT_LABEL_COLON);
   if ( count($arr_locks) )
      $itable->add_sinfo( T_('Tournament Locks'), implode("<br>\n", $arr_locks) );
   $itable->add_sinfo( T_('Tournament Status'), $tourney->getStatusText($tourney->Status) );
   $itable->add_sinfo( T_('Tournament started'), format_translated_date(DATE_FMT6, $tourney->StartTime) );
   if ( $tourney->EndTime > 0 )
      $itable->add_sinfo( T_('Tournament ended'), format_translated_date(DATE_FMT6, $tourney->EndTime) );
   if ( $ttype->need_rounds )
   {
      $itable->add_sinfo( T_('Tournament Round'), $tourney->formatRound() );
      if ( $my_tp && $my_tp->StartRound > 1 )
         $itable->add_sinfo( T_('Start Round#tourney'), $my_tp->StartRound );
   }
   if ( $reg_user_info )
      $itable->add_sinfo( T_('Registration Status#tourney'), span('TUserStatus', $reg_user_info) );
   if ( $my_tp )
      $itable->add_sinfo( T_('Tournament Games'),
         sprintf( T_('%s finished, %s won, %s lost tournament games'), $my_tp->Finished, $my_tp->Won, $my_tp->Lost ));
   if ( $tt_user_state )
   {
      if ( $tourney->Type == TOURNEY_TYPE_LADDER || $tourney->Type == TOURNEY_TYPE_ROUND_ROBIN )
         $itable->add_sinfo( T_('User Result State#tourney'), $tt_user_state );
   }

   echo $itable->make_table();

   // --------------- Standings -------------------------------------

   if ( $cnt_tstandings > 0 )
   {
      section( 'TournamentStandings', sprintf( T_('Tournament Standings (TOP %s)'), $cnt_tstandings ),
         'standings' );

      if ( $tourney->Type == TOURNEY_TYPE_LADDER )
         echo TournamentGuiHelper::build_tournament_ladder_standings( $tl_iterator, $page, $tprops->need_rating_copy() );
   }

   // --------------- Rules -----------------------------------------

   section( 'TournamentRules', T_('Tournament Rules'), 'rules', true );

   echo_tournament_rules( $tourney, $trule );

   if ( !is_null($tpoints) )
   {
      $notes = TournamentGuiHelper::build_tournament_pool_notes($tpoints, /*pool-view*/false );
      echo_notes( 'tpointsNotesTable', T_('Tournament Points Configuration'), $notes, /*sep*/false, false );
   }


   // --------------- Registration ----------------------------------

   section( 'TournamentRegistration', T_('Tournament Registration'), 'registration', true );

   echo_tournament_registration( $tprops );

   // number of registered users for all rounds and TP-stati
   $table = make_table_tournament_participant_counts( $page, $tourney, $tp_all_counts, $ttype->need_rounds );
   echo T_('Registrations for this tournament'), ":<br>\n", $table->make_table(), "<br>\n";

   // ------------- Section Menu

   $sectmenu = array();
   $sectmenu[T_('Tournament participants')] = "tournaments/list_participants.php?tid=$tid";

   $reg_user_str = TournamentGuiHelper::getLinkTextRegistration($tid, $reg_user_status);
   if ( $reg_user_str )
      $sectmenu[$reg_user_str] = "tournaments/register.php?tid=$tid"; # for user

   if ( $allow_edit_tourney )
      $sectmenu[T_('Edit participants')] =
         array( 'url' => "tournaments/edit_participant.php?tid=$tid", 'class' => 'TAdmin' ); # for TD

   make_menu( $sectmenu, false);


   // --------------- Games -----------------------------------------

   section( 'TournamentGames', T_('Tournament Games'), 'games', true );

   // show tourney-type-specific properties
   $tt_notes = null;
   if ( !is_null($tt_props) )
      $tt_notes = $tt_props->build_notes_props();
   if ( !is_null($tt_notes) )
      echo_notes( 'ttprops', $tt_notes[0], $tt_notes[1], false );

   // ------------- Section Menu

   $sectmenu = array();
   $tourney->build_data_link( $sectmenu );

   $sectmenu[T_('My running games')] = "show_games.php?tid=$tid".URI_AMP."uid=$my_id";
   $sectmenu[T_('All running games')] = "show_games.php?tid=$tid".URI_AMP."uid=all";
   $sectmenu[T_('My finished games')] = "show_games.php?tid=$tid".URI_AMP."uid=$my_id".URI_AMP."finished=1";
   $sectmenu[T_('All finished games')] = "show_games.php?tid=$tid".URI_AMP."uid=all".URI_AMP."finished=1";

   make_menu( $sectmenu, false);


   // --------------- Results ---------------------------------------

   if ( $show_tresult )
   {
      static $TRESULT_LIMIT = 10;
      $tresult_control = new TournamentResultControl( /*full*/false, $page, $tourney, /*edit*/false, $TRESULT_LIMIT );
      $tresult_control->build_tournament_result_table( 'Tournament.view_tournament' );
      if ( $tresult_control->get_show_rows() == 0 )
         $tresult_str = T_('No tournament results.');
      else
         $tresult_str = $tresult_control->make_table_tournament_results();

      section( 'TournamentResult', sprintf( T_('Tournament Results (TOP %s)'), $TRESULT_LIMIT ), 'result', true );

      echo "<center>", $tresult_str, "</center><br>\n";

      $sectmenu = array();
      $sectmenu[T_('All tournament results')] = "tournaments/list_results.php?tid=$tid";
      $sectmenu[T_('My tournament results')] =
         "tournaments/list_results.php?tid=$tid".URI_AMP."user=".urlencode($player_row['Handle']);
      make_menu( $sectmenu, false);
   }

   $menu = array();
   $menu[T_('Refresh tournament info')] = "tournaments/view_tournament.php?tid=$tid";

   end_page(@$menu);
}//main


function check_locks( $tourney )
{
   $arr_locks = array();
   if ( $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN|TOURNEY_FLAG_LOCK_TDWORK) )
      $arr_locks[] = $tourney->buildMaintenanceLockText();
   if ( $tourney->isFlagSet(TOURNEY_FLAG_LOCK_REGISTER) )
      $arr_locks[] = Tournament::getLockText(TOURNEY_FLAG_LOCK_REGISTER);
   if ( $tourney->isFlagSet(TOURNEY_FLAG_LOCK_CLOSE) )
      $arr_locks[] = Tournament::getLockText(TOURNEY_FLAG_LOCK_CLOSE);
   return $arr_locks;
}

function echo_tournament_rules( $tourney, $trule )
{
   $adj_komi = array();
   if ( $trule->AdjKomi )
      $adj_komi[] = sprintf( T_('adjusted by %s points#komi'),
            spacing( ($trule->AdjKomi > 0 ? '+' : '') . sprintf('%.1f', $trule->AdjKomi), 1, 'b') );
   if ( $trule->JigoMode == JIGOMODE_ALLOW_JIGO )
      $adj_komi[] = T_('Jigo allowed');
   elseif ( $trule->JigoMode == JIGOMODE_NO_JIGO )
      $adj_komi[] = T_('No Jigo allowed');

   $adj_handi = array();
   if ( $trule->AdjHandicap )
      $adj_handi[] = sprintf( T_('adjusted by %s stones#handi'),
            spacing( ($trule->AdjHandicap > 0 ? '+' : '') . $trule->AdjHandicap, 1, 'b') );

   $lim_handi = DefaultMaxHandicap::build_text_handicap_limits( $trule->Size, $trule->MinHandicap, $trule->MaxHandicap );
   if ( $lim_handi )
      $adj_handi[] = $lim_handi;

   $itable = new Table_info('gamerules', TABLEOPT_LABEL_COLON);
   if ( $trule->ShapeID && $trule->ShapeSnapshot )
   {
      $arr_shape = GameSnapshot::parse_check_extended_snapshot($trule->ShapeSnapshot);
      if ( is_array($arr_shape) )
      {
         $itable->add_sinfo( T_('Shape-Game'),
               ShapeControl::build_snapshot_info(
                  $trule->ShapeID, $arr_shape['Size'], $arr_shape['Snapshot'], $arr_shape['PlayColorB'] ) );
      }
   }
   $itable->add_sinfo( T_('Ruleset'), Ruleset::getRulesetText($trule->Ruleset) );
   $itable->add_sinfo( T_('Board Size'), $trule->Size .' x '. $trule->Size );
   $itable->add_sinfo( T_('Handicap Type'),
         TournamentRules::getHandicaptypeText($trule->Handicaptype, $tourney->Type) );
   $itable->add_sinfo( T_('Handicap'),
      ( $trule->needsCalculatedHandicap() ? T_('calculated stones') : sprintf( T_('%s stones'), $trule->Handicap) )
      . ', ' .
      ( $trule->needsCalculatedKomi() ? T_('calculated komi') : sprintf( T_('%s points komi'), $trule->Komi) ));
   if ( count($adj_handi) )
      $itable->add_sinfo( T_('Handicap adjustment'), implode(', ', $adj_handi) );
   if ( count($adj_komi) )
      $itable->add_sinfo( T_('Komi adjustment'), implode(', ', $adj_komi) );
   if ( ENABLE_STDHANDICAP )
      $itable->add_sinfo( T_('Standard placement'), yesno($trule->StdHandicap) );
   $itable->add_sinfo( T_('Main time'), TimeFormat::echo_time($trule->Maintime)
         . ( ($trule->Byotime == 0) ? SMALL_SPACING.T_('(absolute time)') : '' ));
   if ( $trule->Byotime > 0 )
      $itable->add_sinfo(
         TimeFormat::echo_byotype($trule->Byotype),
         TimeFormat::echo_time_limit( -1, $trule->Byotype, $trule->Byotime, $trule->Byoperiods, 0) );
   $itable->add_sinfo( T_('Clock runs on weekends'), yesno($trule->WeekendClock) );
   $itable->add_sinfo( T_('Rated'), yesno($trule->Rated) );

   if ( $trule->Notes != '')
      echo make_html_safe( $trule->Notes, true ), "<br><br>\n";

   echo T_('The following game rules are used for this tournament'), ':',
      $itable->make_table(),
      "<br>\n";
}//echo_tournament_rules


function echo_tournament_registration( $tprops )
{
   $arr_tprops = array();

   // limit register end-time
   if ( $tprops->RegisterEndTime )
      $arr_tprops[] = sprintf( T_('Registration phase ends on [%s]#tourney'), formatDate($tprops->RegisterEndTime) );

   // limit participants
   if ( $tprops->MinParticipants > 0 && $tprops->MaxParticipants > 0 )
      $arr_tprops[] = sprintf( T_('Tournament needs: min. %s and max. %s participants'),
            $tprops->MinParticipants, $tprops->MaxParticipants );
   elseif ( $tprops->MinParticipants > 0 )
      $arr_tprops[] = sprintf( T_('Tournament needs: min. %s participants'), $tprops->MinParticipants );
   elseif ( $tprops->MaxParticipants > 0 )
      $arr_tprops[] = sprintf( T_('Tournament needs: max. %s participants'), $tprops->MaxParticipants );

   // use-rating-mode, limit user-rating
   $arr_tprops[] = TournamentProperties::getRatingUseModeText( $tprops->RatingUseMode, false );
   if ( $tprops->UserRated )
      $arr_tprops[] = sprintf( T_('User rating must be between [%s - %s].'),
            echo_rating( $tprops->UserMinRating, false ),
            echo_rating( $tprops->UserMaxRating, false ));

   // limit games-number
   if ( $tprops->UserMinGamesFinished > 0 )
      $arr_tprops[] = sprintf( T_('User must have at least %s finished games.'),
            $tprops->UserMinGamesFinished );
   if ( $tprops->UserMinGamesRated > 0 )
      $arr_tprops[] = sprintf( T_('User must have at least %s rated finished games.'),
            $tprops->UserMinGamesRated );

   if ( count($arr_tprops) )
      echo T_('To register for this tournament the following criteria must match'), ':',
           '<ul><li>', implode("\n<li>", $arr_tprops), "</ul>\n";
   if ( $tprops->Notes != '' )
      echo make_html_safe($tprops->Notes, true), "<br><br>\n";
}//echo_tournament_registration

/*!
 * \brief make table with counts of tournament-participants for all rounds and TP-stati.
 * \param $arr_tp_cnt =[ round => [ TP_STATUS_... => cnt, ... ] ]
 * \param $show_single_round false = do not include round-1-row if only one round but only show summary-line
 */
function make_table_tournament_participant_counts( $page, $tourney, $arr_tp_cnt, $show_single_round )
{
   static $ARR_TP_STATUS = array( TP_STATUS_REGISTER, TP_STATUS_INVITE, TP_STATUS_APPLY );

   $table = new Table( 'TPCountSummary', $page, null, 'tpcs',
      TABLE_NO_SORT|TABLE_NO_HIDE|TABLE_NO_PAGE|TABLE_NO_SIZE );

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $table->add_tablehead( 1, T_('Status#header').':', 'BoldC' );
   $col = 2;
   $arr_rndcnt = array();
   foreach ( $ARR_TP_STATUS as $tp_status )
   {
      $table->add_tablehead( $col++, TournamentParticipant::getStatusText($tp_status), 'NumberC' );
      $arr_rndcnt[$tp_status] = 0;
   }
   $table->add_tablehead( $col, T_('Sum#header'), 'NumberC' );

   // fill rounds
   $sum_all = 0;
   $has_only_one_round = ( count($arr_tp_cnt) == 1 );
   foreach ( $arr_tp_cnt as $round => $arr )
   {
      $row_arr = array( 1 => sprintf( T_('Round %s#header'), $round ) );
      $col = 2;
      $round_sum = 0;
      foreach ( $ARR_TP_STATUS as $tp_status )
      {
         $row_arr[$col++] = @$arr[$tp_status]; // empty if not set
         $cnt = (int)@$arr[$tp_status];
         $round_sum += $cnt;
         $sum_all += $cnt;
         $arr_rndcnt[$tp_status] += $cnt;
      }
      $row_arr[$col++] = $round_sum;
      if ( !$has_only_one_round || $show_single_round )
         $table->add_row( $row_arr );
   }

   // summary row
   $row_arr = array( 1 => T_('Sum#header'), 'extra_class' => 'Sum' );
   $col = 2;
   foreach ( $ARR_TP_STATUS as $tp_status )
      $row_arr[$col++] = $arr_rndcnt[$tp_status];
   $row_arr[$col++] = $sum_all;
   $table->add_row( $row_arr );

   return $table;
}//make_table_tournament_participant_counts

?>
