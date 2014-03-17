<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Game";

require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/std_classes.php';
require_once 'include/table_columns.php';
require_once 'include/table_infos.php';
require_once 'include/time_functions.php';
require_once 'include/countries.php';
require_once 'include/rulesets.php';
require_once 'include/rating.php';
require_once 'include/game_functions.php';
if ( ALLOW_TOURNAMENTS ) {
   require_once 'tournaments/include/tournament.php';
   require_once 'tournaments/include/tournament_cache.php';
   require_once 'tournaments/include/tournament_games.php';
   require_once 'tournaments/include/tournament_helper.php';
   require_once 'tournaments/include/tournament_ladder.php';
}

$GLOBALS['ThePage'] = new Page('GameInfo');


// map Games.Status -> KOMI, SETUP, INVITED, "RUNNING", FINISHED
function build_game_status( $status )
{
   return isRunningGame($status) ? 'RUNNING' : $status;
}

function build_rating_diff( $rating_diff )
{
   if ( isset($rating_diff) )
      return ( $rating_diff > 0 ? '+' : '' ) . sprintf( "%0.2f", $rating_diff / 100 );
   else
      return '';
}


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'gameinfo');

   $my_id = $player_row['ID'];
   $is_admin = (@$player_row['admin_level'] & ADMIN_DEVELOPER);

   $gid = (int) get_request_arg('gid', 0);
   if ( $gid < 1 )
      error('unknown_game', "gameinfo.check.game($gid)");

   if ( get_request_arg('set_prio') )
   {
      $new_prio = trim(get_request_arg('prio'));
      NextGameOrder::persist_game_priority( $gid, $my_id, $new_prio );
   }


   // load game-values
   $qsql = new QuerySQL();
   $qsql->add_part( SQLP_FIELDS,
      'Games.*',
      'BP.Name AS Black_Name', 'BP.Handle AS Black_Handle', 'BP.Country AS Black_Country',
      'BP.Rating2 AS Black_Rating', 'BP.OnVacation AS Black_OnVacation',
      'UNIX_TIMESTAMP(BP.Lastaccess) AS Black_Lastaccess', 'UNIX_TIMESTAMP(BP.LastMove) AS Black_LastMove',
      'BP.ClockUsed AS Black_ClockUsed',
      'WP.Name AS White_Name', 'WP.Handle AS White_Handle', 'WP.Country AS White_Country',
      'WP.Rating2 AS White_Rating', 'WP.OnVacation AS White_OnVacation',
      'UNIX_TIMESTAMP(WP.Lastaccess) AS White_Lastaccess', 'UNIX_TIMESTAMP(WP.LastMove) AS White_LastMove',
      'WP.ClockUsed AS White_ClockUsed',
      'BRL.RatingDiff AS Black_RatingDiff',
      'WRL.RatingDiff AS White_RatingDiff',
      'UNIX_TIMESTAMP(Starttime) AS X_Starttime',
      'UNIX_TIMESTAMP(Lastchanged) AS X_Lastchanged',
      "IF(Games.Rated='N','N','Y') AS X_Rated",
      'BP.Handle AS Blackhandle', 'WP.Handle AS Whitehandle' //for FairKomiNegotiation.get_htype_user_handles()
      );
   $qsql->add_part( SQLP_FROM,
      'Games',
      'INNER JOIN Players AS BP ON BP.ID=Games.Black_ID',
      'INNER JOIN Players AS WP ON WP.ID=Games.White_ID',
      'LEFT JOIN Ratinglog AS BRL ON BRL.gid=Games.ID AND BRL.uid=Games.Black_ID',
      'LEFT JOIN Ratinglog AS WRL ON WRL.gid=Games.ID AND WRL.uid=Games.White_ID' );
   $qsql->add_part( SQLP_WHERE,
      'Games.ID='.$gid );
   $query = $qsql->get_select() . ' LIMIT 1';

   $grow = mysql_single_fetch( "gameinfo.find($gid)", $query );
   if ( !$grow )
      error('unknown_game', "gameinfo.find2($gid)");
   $game_status = $grow['Status'];
   if ( $game_status == GAME_STATUS_SETUP || $game_status == GAME_STATUS_INVITED )
      error('invalid_game_status', "gameinfo.find3($gid,$game_status)");

   $black_id = $grow['Black_ID'];
   $white_id = $grow['White_ID'];
   $shape_id = (int)@$grow['ShapeID'];
   $tid = (int) @$grow['tid'];
   $tourney = $tgame = $tladder_rank = null;
   if ( !ALLOW_TOURNAMENTS || $tid <= 0 )
      $tid = 0;
   else
   {
      $tourney = TournamentCache::load_cache_tournament( "gameinfo.find_tournament($gid,$tid)", $tid );
      $tgame = TournamentGames::load_tournament_game_by_gid($gid);

      if ( $tourney->Type == TOURNEY_TYPE_LADDER && !is_null($tgame) && isRunningGame($game_status) )
      {
         $tladder_rank = array( $black_id => NO_VALUE, $white_id => NO_VALUE );
         $arr_tladder = TournamentLadder::load_tournament_ladder_by_uids( $tid, array( $black_id, $white_id ) );
         if ( isset($arr_tladder[$black_id]) )
            $tladder_rank[$black_id] = $arr_tladder[$black_id]->Rank;
         if ( isset($arr_tladder[$white_id]) )
            $tladder_rank[$white_id] = $arr_tladder[$white_id]->Rank;
      }
   }


   // init some vars
   $is_my_game = ( $my_id == $black_id || $my_id == $white_id );
   $arr_status = array( // see build_game_status()
      GAME_STATUS_SETUP    => T_('Setup'),
      GAME_STATUS_INVITED  => T_('Inviting'),
      'RUNNING'            => T_('Running'),
      GAME_STATUS_FINISHED => T_('Finished'),
      GAME_STATUS_KOMI     => T_('Komi Negotiation'),
      );
   $status = build_game_status($game_status);
   $game_finished = ( $game_status === GAME_STATUS_FINISHED );
   $to_move = get_to_move( $grow, 'gameinfo.bad_ToMove_ID' );

   $game_setup = GameSetup::new_from_game_setup($grow['GameSetup']);
   $Handitype = $game_setup->Handicaptype;
   $cat_htype = get_category_handicaptype($Handitype);
   $is_fairkomi = ( $cat_htype == CAT_HTYPE_FAIR_KOMI );
   $is_fairkomi_negotiation = ( $is_fairkomi && $game_status == GAME_STATUS_KOMI );
   $fk_htype_text = GameTexts::get_fair_komi_types($Handitype);
   $jigo_mode = $game_setup->JigoMode;


   // ------------------------
   // build table-info: game settings

   $itable = new Table_info('game');
   $itable->add_caption( T_('Game settings') );
   $itable->add_sinfo(
         T_('Game ID'),
         anchor( "{$base_path}game.php?gid=$gid", "#$gid" ) .
         echo_image_gameinfo($gid, true) .
         echo_image_shapeinfo( $shape_id, $grow['Size'], $grow['ShapeSnapshot'], false, true ) );
   if ( $grow['DoubleGame_ID'] )
   {
      $dbl_gid = $grow['DoubleGame_ID'];
      $itable->add_sinfo(
            T_('Double Game ID'),
            ($dbl_gid > 0)
               ? anchor( "{$base_path}game.php?gid=$dbl_gid", "#$dbl_gid" ) . echo_image_gameinfo($dbl_gid, true)
               : '#'.abs($dbl_gid). sprintf(' (%s)', T_('deleted#dblgame') )
         );
   }
   if ( $is_my_game && $grow['mid'] > 0 )
   {
      $itable->add_sinfo(
            T_('Message'),
            anchor( "{$base_path}message.php?mode=ShowMessage".URI_AMP.'mid='.$grow['mid'],
                    T_('Show invitation') )
         );
   }
   $itable->add_sinfo(
         T_('Game Type'),
         GameTexts::format_game_type($grow['GameType'], $grow['GamePlayers'])
            . ( ($grow['GameType'] != GAMETYPE_GO ) ? MED_SPACING . echo_image_game_players($gid) : '' )
         );
   $itable->add_sinfo(
         T_('Status'),
         $arr_status[$status]
            . ( $is_admin ? " (<span class=\"DebugInfo\">$game_status</span>)" : '')
      );
   if ( $is_fairkomi )
      $itable->add_sinfo( T_('Fair Komi Type#fairkomi'), $fk_htype_text );
   if ( $game_finished )
   {
      $admResult = ( $grow['Flags'] & GAMEFLAGS_ADMIN_RESULT )
         ? span('ScoreWarning', sprintf(' (%s)', T_('set by admin#game')))
         : '';
      $itable->add_sinfo( T_('Score'), score2text(@$grow['Score'], @$grow['Flags'], /*verbose*/false) . $admResult);
   }
   $itable->add_sinfo( T_('Start Time'),  date(DATE_FMT3, @$grow['X_Starttime']) );
   $itable->add_sinfo( T_('Lastchanged'), date(DATE_FMT3, @$grow['X_Lastchanged']) );
   $itable->add_sinfo( T_('Ruleset'),     Ruleset::getRulesetText($grow['Ruleset']) );
   $itable->add_sinfo( T_('Size'),        $grow['Size'] );
   $itable->add_sinfo( T_('Handicap'),    $grow['Handicap'] );
   $itable->add_sinfo( T_('Komi'),
      ( $is_fairkomi_negotiation ? T_('negotiated by Fair Komi#fairkomi') : $grow['Komi'] ) );
   if ( $is_fairkomi )
      $itable->add_sinfo( T_('Jigo mode'), GameTexts::get_jigo_modes($jigo_mode) );
   $itable->add_sinfo( T_('Rated'),       yesno($grow['X_Rated']) );
   $itable->add_sinfo( T_('Weekend Clock'),     yesno($grow['WeekendClock']) ); // Yes=clock runs on weekend
   $itable->add_sinfo( T_('Standard Handicap'), yesno($grow['StdHandicap']) );
   $itable_str_game = $itable->make_table();
   unset($itable);


   // ------------------------
   // build table-info: players

   $color_class = 'class="InTextImage"';
   if ( $is_fairkomi )
   {
      $fk = new FairKomiNegotiation($game_setup, $grow);
      $komibid_black = $fk->get_view_komibid( $my_id, $black_id );
      $komibid_white = $fk->get_view_komibid( $my_id, $white_id );
   }
   else
      $komibid_black = $komibid_white = '';
   if ( $is_fairkomi_negotiation )
   {
      if ( is_htype_divide_choose($Handitype) )
      {
         $uhandles = $fk->get_htype_user_handles();
         $color_note = GameTexts::get_fair_komi_types( $Handitype, null, $uhandles[0], $uhandles[1] );
      }
      else
         $color_note = $fk_htype_text;
      $icon_col_b = $icon_col_w = image( $base_path.'17/y.gif', $color_note, NULL, $color_class );
   }
   else
   {
      $icon_col_b = image( $base_path.'17/b.gif', T_('Black'), null, $color_class );
      $icon_col_w = image( $base_path.'17/w.gif', T_('White'), null, $color_class );
   }

   $itable = new Table_info('players');
   $itable->add_caption( T_('Players Information') );
   $itable->add_sinfo(
         T_('Color'),
         array(
            $icon_col_b,
            $icon_col_w,
         ),
         'class=Colors' );
   $itable->add_sinfo(
         T_('Player'),
         array(
            user_reference( REF_LINK, 1, '', $black_id, @$grow['Black_Name'], @$grow['Black_Handle'] )
               . MED_SPACING . getCountryFlagImage(@$grow['Black_Country'], 'InTextImage'),
            user_reference( REF_LINK, 1, '', $white_id, @$grow['White_Name'], @$grow['White_Handle'] )
               . MED_SPACING . getCountryFlagImage(@$grow['White_Country'], 'InTextImage'),
         ));
   $itable->add_sinfo(
         T_('Last access'),
         array(
            date(DATE_FMT2, @$grow['Black_Lastaccess']),
            date(DATE_FMT2, @$grow['White_Lastaccess']),
         ));
   $itable->add_sinfo(
         T_('Last move'),
         array(
            date(DATE_FMT2, @$grow['Black_LastMove']),
            date(DATE_FMT2, @$grow['White_LastMove']),
         ));
   if ( @$grow['Black_OnVacation'] > 0 || @$grow['White_OnVacation'] > 0 )
   {
      $itable->add_sinfo(
            T_('On vacation') . MINI_SPACING . echo_image_vacation(),
            array(
                  TimeFormat::echo_onvacation(@$grow['Black_OnVacation']),
                  TimeFormat::echo_onvacation(@$grow['White_OnVacation']),
            ),
            '', 'class=OnVacation' );
   }

   $itable->add_sinfo(
         T_('Off-time'),
         array(
            echo_off_time( ($to_move == BLACK), ($grow['Black_OnVacation'] > 0), $grow['Black_ClockUsed'],
               $grow['WeekendClock'] ),
            echo_off_time( ($to_move == WHITE), ($grow['White_OnVacation'] > 0), $grow['White_ClockUsed'],
               $grow['WeekendClock'] ),
         ),
         'class="Images"' );
   $itable->add_sinfo(
         T_('Current rating'),
         array(
            echo_rating( @$grow['Black_Rating'], true, $black_id ),
            echo_rating( @$grow['White_Rating'], true, $white_id ),
         ));
   $itable->add_sinfo(
         ($grow['GameType'] == GAMETYPE_GO) ? T_('Start rating') : T_('Group start rating'),
         array(
            echo_rating( @$grow['Black_Start_Rating']),
            echo_rating( @$grow['White_Start_Rating']),
         ));
   if ( !is_null($tladder_rank) )
   {
      $itable->add_sinfo(
            T_('Ladder rank#tourney'),
            array(
               $tladder_rank[$black_id],
               $tladder_rank[$white_id],
            ));
   }
   if ( $game_finished )
   {
      $itable->add_sinfo(
            T_('End rating'),
            array(
               echo_rating( @$grow['Black_End_Rating']),
               echo_rating( @$grow['White_End_Rating']),
            ));

      if ( $grow['X_Rated'] === 'Y' &&
            ( isset($grow['Black_RatingDiff']) || isset($grow['White_RatingDiff']) ))
      {
         $itable->add_sinfo(
               T_('Rating diff'),
               array(
                  build_rating_diff( @$grow['Black_RatingDiff'] ),
                  build_rating_diff( @$grow['White_RatingDiff'] ),
               ));
      }
   }
   if ( $komibid_black || $komibid_white )
   {
      $itable->add_sinfo(
            T_('Komi Bid#fairkomi'),
            array(
               $komibid_black,
               $komibid_white,
            ),
            'class="KomiBid"' );
   }
   $itable_str_players = $itable->make_table();
   unset($itable);


   // ------------------------
   // build table-info: time settings

   // reduce player-time by current number of ticks
   if ( $grow['Maintime'] > 0 || $grow['Byotime'] > 0 )
   {
      // LastTicks may handle -(time spend) at the moment of the start of vacations
      $clock_ticks = get_clock_ticks( "gameinfo($gid)", $grow['ClockUsed'] );
      $hours = ticks_to_hours( $clock_ticks - $grow['LastTicks']);

      if ( $to_move == BLACK )
      {
         time_remaining($hours, $grow['Black_Maintime'], $grow['Black_Byotime'],
                        $grow['Black_Byoperiods'], $grow['Maintime'],
                        $grow['Byotype'], $grow['Byotime'], $grow['Byoperiods'], false);
      }
      else
      {
         time_remaining($hours, $grow['White_Maintime'], $grow['White_Byotime'],
                        $grow['White_Byoperiods'], $grow['Maintime'],
                        $grow['Byotype'], $grow['Byotime'], $grow['Byoperiods'], false);
      }
   }

   $movefmt = T_('%s\'s turn#fairkomi_user');
   $player_to_move = ( $to_move == BLACK )
      ? $icon_col_b . ' ' . ( $is_fairkomi ? sprintf($movefmt, $grow['Black_Handle']) : T_('Black to move') )
      : $icon_col_w . ' ' . ( $is_fairkomi ? sprintf($movefmt, $grow['White_Handle']) : T_('White to move') );

   $timefmt = TIMEFMT_SHORT | TIMEFMT_ZERO;
   $itable = new Table_info('time');
   $itable->add_caption( T_('Time settings and Remaining time') );
   $itable->add_sinfo(
         T_('Color'),
         array(
            $player_to_move,
            image( "{$base_path}17/b.gif", T_('Black'), null ),
            image( "{$base_path}17/w.gif", T_('White'), null ),
         ),
         'class="Colors"' );
   $itable->add_sinfo(
         T_('Time system'),
         array(
            TimeFormat::echo_byotype($grow['Byotype']),
            '',
            '',
         ));
   $itable->add_sinfo(
         T_('Main time'),
         array(
            TimeFormat::echo_time($grow['Maintime']),
            TimeFormat::echo_time($grow['Black_Maintime'], $timefmt ),
            TimeFormat::echo_time($grow['White_Maintime'], $timefmt ),
         ));

   $game_extratime = TimeFormat::echo_time_limit( -1, $grow['Byotype'], $grow['Byotime'],
         $grow['Byoperiods'], $timefmt );
   $itable->add_sinfo(
         T_('Extra time'),
         array(
            TimeFormat::echo_time_limit( -1, $grow['Byotype'], $grow['Byotime'], $grow['Byoperiods'],
               $timefmt & 0),
            (( $grow['Black_Maintime'] > 0 )
               ? $game_extratime
               : TimeFormat::echo_time_remaining( 0, $grow['Byotype'], $grow['Black_Byotime'],
                     $grow['Black_Byoperiods'], $grow['Byotime'], $grow['Byoperiods'],
                     $timefmt )
            ),
            (( $grow['White_Maintime'] > 0 )
               ? $game_extratime
               : TimeFormat::echo_time_remaining( 0, $grow['Byotype'], $grow['White_Byotime'],
                     $grow['White_Byoperiods'], $grow['Byotime'], $grow['Byoperiods'],
                     $timefmt )
            ),
         ));

   $clock_status = array( BLACK => array(), WHITE => array() );
   if ( is_vacation_clock($grow['ClockUsed']) )
   {
      $clock_status[$to_move][] = echo_image_vacation( 'in_text',
         TimeFormat::echo_onvacation(
            ($to_move == BLACK) ? $grow['Black_OnVacation'] : $grow['White_OnVacation'] ),
         true );
   }
   elseif ( is_weekend_clock_stopped($grow['ClockUsed']) )
      $clock_status[$to_move][] = echo_image_weekendclock(true, true);
   if ( is_nighttime_clock( ($to_move == BLACK) ? $grow['Black_ClockUsed'] : $grow['White_ClockUsed'] ) )
      $clock_status[$to_move][] = echo_image_nighttime('in_text', true);

   $clock_stopped = count($clock_status[BLACK]) + count($clock_status[WHITE]);
   $itable->add_sinfo(
         T_('Clock status'),
         array(
            ( $clock_stopped ? T_('Clock stopped') : T_('Clock running') ),
            implode(' ', $clock_status[BLACK]),
            implode(' ', $clock_status[WHITE]),
         ));
   if ( $is_admin )
   {
      $itable->add_row( array(
            'rattb' => 'class=DebugInfo',
            'sname' => T_('Clock used'),
            'sinfo' => array(
               T_('Game') . ': ' . $grow['ClockUsed'],
               $grow['Black_ClockUsed'],
               $grow['White_ClockUsed'],
            )));
   }
   $itable_str_time = $itable->make_table();
   unset($itable);


   // ------------------------
   // build table-info: tournament info

   if ( ALLOW_TOURNAMENTS && $tid && !is_null($tourney) )
   {
      $itable = new Table_info('tourney');
      $itable->add_caption( T_('Tournament info') );

      $itable->add_sinfo(
            T_('ID'),
            anchor( $base_path."tournaments/view_tournament.php?tid=$tid", "#$tid" )
               . echo_image_tournament_info($tid, true) );
      $itable->add_sinfo(
            T_('Scope#tourney'),
            Tournament::getScopeText($tourney->Scope) );
      $itable->add_sinfo(
            T_('Type#tourney'),
            Tournament::getTypeText($tourney->Type) );
      $itable->add_sinfo(
            T_('Title'),
            make_html_safe(wordwrap($tourney->Title, 30), true) );
      if ( $tourney->Type != TOURNEY_TYPE_LADDER )
         $itable->add_sinfo(
               T_('Current Round#tourney'),
               $tourney->formatRound() );
      $itable->add_sinfo(
            T_('Tournament Status'),
            Tournament::getStatusText($tourney->Status) );
      if ( !is_null($tgame) )
      {
         $itable->add_sinfo(
               T_('Tournament Game Status'),
               TournamentGames::getStatusText($tgame->Status) );

         if ( $tourney->Type == TOURNEY_TYPE_LADDER )
         {
            $tg_col = '';
            if ( $tgame->Challenger_uid == $my_id )
               $tg_role = T_('Challenger#T_ladder');
            elseif ( $tgame->Defender_uid == $my_id )
               $tg_role = T_('Defender#T_ladder');
            else
            {
               $tg_col = T_('Black');
               $tg_role = ( $tgame->Challenger_uid == $black_id ) ? T_('Challenger#T_ladder') : T_('Defender#T_ladder');
            }
            if ( !$tg_col )
               $tg_col = ( $my_id == $black_id ) ? T_('Black') : T_('White');

            $itable->add_sinfo(
                  ( $is_my_game ) ? T_('My Tournament Game Role') : T_('Tournament Game Role'),
                  "$tg_col: $tg_role" );
         }

         if ( $tgame->isScoreStatus(/*chk-detach*/true) && $black_id )
         {
            $arr_flags = array();
            if ( $tgame->Flags & TG_FLAG_GAME_END_TD )
               $arr_flags[] = T_('by TD#TG_flag');
            $flags_str = (count($arr_flags)) ? sprintf( ' (%s)', implode(', ', $arr_flags)) : '';

            list( $tg_score, $tg_score_text ) = $tgame->getGameScore( $black_id, /*verbose*/false );
            if ( !$game_finished || !is_null($tg_score) )
               $tg_score_text = span('ScoreWarning', $tg_score_text );

            $itable->add_sinfo(
                  T_('Tournament Game Score'),
                  $tg_score_text . $flags_str );
         }

         $arr_flags = array();
         if ( $tgame->Flags & TG_FLAG_GAME_DETACHED )
            $arr_flags[] = span('TWarning', T_('detached#tourney'));
         if ( count($arr_flags) )
         {
            $itable->add_sinfo(
                  T_('Tournament Game Flags'),
                  implode(', ', $arr_flags) );
         }
      }

      $itable_str_tourney = $itable->make_table();
      unset($itable);
   }
   else
      $itable_str_tourney = '';


   // ------------------------
   // build form for editing games-priority

   // for players and running games only
   if ( $is_my_game && !$game_finished )
   {
      $prio = NextGameOrder::load_game_priority( $gid, $my_id, '' );

      $prioform = new Form( 'gameprio', "gameinfo.php?gid=$gid", FORM_GET );
      $prioform->add_row( array(
            'DESCRIPTION',  T_('Status games list#nextgame'),
            'TEXTINPUT',    'prio', 5, 5, $prio,
            'SUBMITBUTTON', 'set_prio', T_('Set priority'),
            'HIDDEN', 'gid', $gid,
         ));

      $form_str_gameprio = $prioform->get_form_string();
   }
   else
      $form_str_gameprio = '';

   // ------------------------ END of building


   $title = T_('Game information');
   start_page( $title, true, $logged_in, $player_row );

   echo "<h3 class=Header>$title</h3>\n";

   echo
      "<table><tr valign=\"top\">",
      "<td>$itable_str_game<br>$form_str_gameprio<br>$itable_str_tourney</td>",
      "<td>$itable_str_time<br>$itable_str_players</td>",
      "</tr></table>\n";


   $menu_array = array();
   $menu_array[T_('Show game')] = "game.php?gid=$gid";
   if ( $grow['GameType'] != GAMETYPE_GO )
      $menu_array[T_('Show game-players')] = "game_players.php?gid=$gid";
   GameRematch::add_rematch_links( $menu_array, $gid, $game_status, $grow['GameType'], $grow['tid'] );
   if ( $grow['GameType'] == GAMETYPE_GO )
   {
      $menu_array[T_('Show rating changes')] = "rating_changes.php?b=".urlencode($grow['Black_Handle']) .
         URI_AMP. "w=".urlencode($grow['White_Handle']).URI_AMP."size={$grow['Size']}" .
         URI_AMP."handicap={$grow['Handicap']}".URI_AMP."komi={$grow['Komi']}";
   }
   if ( ALLOW_TOURNAMENTS && $tid && !is_null($tourney) )
   {
      $tourney->build_data_link( $menu_array );
      if ( TournamentHelper::allow_edit_tournaments($tourney, $my_id, TD_FLAG_GAME_END) )
         $menu_array[T_('Admin tournament game')] =
            array( 'url' => "tournaments/game_admin.php?tid=$tid".URI_AMP."gid=$gid", 'class' => 'TAdmin' );
      if ( TournamentHelper::allow_edit_tournaments($tourney, $my_id) )
         $menu_array[T_('Manage tournament')] =
            array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );
   }
   if ( $is_admin )
      $menu_array[T_('Show game-calc')] =
         array( 'url' => 'game_calc.php?show=1'.URI_AMP."gid=$gid", 'class' => 'AdminLink' );
   if ( @$player_row['admin_level'] & ADMIN_GAME )
      $menu_array[T_('Admin game')] =
         array( 'url' => "admin_game.php?gid=$gid", 'class' => 'AdminLink' );

   end_page(@$menu_array);
}//main

?>
