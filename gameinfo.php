<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once( 'include/std_functions.php' );
require_once( 'include/gui_functions.php' );
require_once( 'include/std_classes.php' );
require_once( 'include/table_columns.php' );
require_once( 'include/table_infos.php' );
require_once( 'include/time_functions.php' );
require_once( 'include/rating.php' );
require_once( 'include/game_functions.php' );
require_once( 'include/classlib_game.php' );
if( ALLOW_TOURNAMENTS ) {
   require_once 'tournaments/include/tournament.php';
   require_once 'tournaments/include/tournament_games.php';
}

$GLOBALS['ThePage'] = new Page('GameInfo');


// Status: enum('INVITED','PLAY','PASS','SCORE','SCORE2','FINISHED')
// -> INVITED, RUNNING, FINISHED
function build_game_status( $status )
{
   return ( $status === 'INVITED' || $status === 'FINISHED' ) ? $status : 'RUNNING';
}

function build_rating_diff( $rating_diff )
{
   if( isset($rating_diff) )
      return ( $rating_diff > 0 ? '+' : '' ) . sprintf( "%0.2f", $rating_diff / 100 );
   else
      return '';
}


{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   $my_id = $player_row['ID'];
   $is_admin = (@$player_row['admin_level'] & ADMIN_DEVELOPER);

   $gid = (int) get_request_arg('gid', 0);
   if( $gid < 1 )
      error('unknown_game', "gameinfo.check.game($gid)");

   if( get_request_arg('set_prio') )
   {
      $new_prio = trim(get_request_arg('prio'));
      NextGameOrder::persist_game_priority( $gid, $my_id, $new_prio );
   }


   // load game-values
   $qsql = new QuerySQL();
   $qsql->add_part( SQLP_FIELDS,
      'Games.*',
      'BP.Name AS Black_Name', 'BP.Handle AS Black_Handle',
      'BP.Rating2 AS Black_Rating', 'BP.OnVacation AS Black_OnVacation',
      'BP.ClockUsed AS Black_ClockUsed',
      'WP.Name AS White_Name', 'WP.Handle AS White_Handle',
      'WP.Rating2 AS White_Rating', 'WP.OnVacation AS White_OnVacation',
      'WP.ClockUsed AS White_ClockUsed',
      'BRL.RatingDiff AS Black_RatingDiff',
      'WRL.RatingDiff AS White_RatingDiff',
      'UNIX_TIMESTAMP(Starttime) AS X_Starttime',
      'UNIX_TIMESTAMP(Lastchanged) AS X_Lastchanged',
      "IF(Games.Rated='N','N','Y') AS X_Rated"
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
   if( !$grow )
      error('unknown_game', "gameinfo.find2($gid)");

   $tid = (int) @$grow['tid'];
   $tourney = $tgmae = null;
   if( $tid <= 0 )
      $tid = 0;
   else
   {
      $tourney = Tournament::load_tournament($tid);
      if( is_null($tourney) )
         error('unknown_tournament', "gameinfo.find_tournament($gid,$tid)");

      $tgame = TournamentGames::load_tournament_game_by_gid($gid);
   }


   // init some vars
   $is_my_game = ( $my_id == $grow['Black_ID'] || $my_id == $grow['White_ID'] );
   $arr_status = array(
      'INVITED'  => T_('Inviting'),
      'RUNNING'  => T_('Running'),
      'FINISHED' => T_('Finished'),
      );
   $status = build_game_status($grow['Status']);
   $game_finished = ( $grow['Status'] === 'FINISHED' );

   $to_move = get_to_move( $grow, 'gameinfo.bad_ToMove_ID' );


   // ------------------------
   // build table-info: game settings

   $itable = new Table_info('game');
   $itable->add_caption( T_('Game settings') );
   $itable->add_sinfo(
         T_('Game ID'),
         anchor( "{$base_path}game.php?gid=$gid", "#$gid" ) . echo_image_gameinfo($gid, true) );
   if( $grow['DoubleGame_ID'] )
   {
      $dbl_gid = $grow['DoubleGame_ID'];
      $itable->add_sinfo(
            T_('Double Game ID'),
            ($dbl_gid > 0)
               ? anchor( "{$base_path}game.php?gid=$dbl_gid", "#$dbl_gid" )
                     .SMALL_SPACING. echo_image_gameinfo($dbl_gid)
               : '#'.abs($dbl_gid). sprintf(' (%s)', T_('deleted#dblgame') )
         );
   }
   if( $is_my_game && $grow['mid'] > 0 )
   {
      $itable->add_sinfo(
            T_('Message'),
            anchor( "{$base_path}message.php?mode=ShowMessage".URI_AMP.'mid='.$grow['mid'],
                    T_('Show invitation') )
         );
   }
   $itable->add_sinfo(
         T_('Status'),
         $arr_status[$status]
            . ( $is_admin ? " (<span class=\"DebugInfo\">{$grow['Status']}</span>)" : '')
      );
   if( $game_finished )
      $itable->add_sinfo( T_('Score'), score2text(@$grow['Score'], false));
   $itable->add_sinfo( T_('Start time'),  date(DATE_FMT3, @$grow['X_Starttime']) );
   $itable->add_sinfo( T_('Lastchanged'), date(DATE_FMT3, @$grow['X_Lastchanged']) );
   $itable->add_sinfo( T_('Size'),        $grow['Size'] );
   $itable->add_sinfo( T_('Handicap'),    $grow['Handicap'] );
   $itable->add_sinfo( T_('Komi'),        $grow['Komi'] );
   $itable->add_sinfo( T_('Rated'),       yesno($grow['X_Rated']) );
   $itable->add_sinfo( T_('Weekend Clock'),     yesno($grow['WeekendClock']) ); // Yes=clock runs on weekend
   $itable->add_sinfo( T_('Standard Handicap'), yesno($grow['StdHandicap']) );
   $itable_str_game = $itable->make_table();
   unset($itable);


   // ------------------------
   // build table-info: players

   $itable = new Table_info('players');
   $itable->add_caption( T_('Players') );
   $itable->add_sinfo(
         T_('Color'),
         array(
            image( "{$base_path}17/b.gif", T_('Black'), null ),
            image( "{$base_path}17/w.gif", T_('White'), null ),
         ),
         'class=Colors' );
   $itable->add_sinfo(
         T_('Player'),
         array(
            user_reference( REF_LINK, 1, '', @$grow['Black_ID'], @$grow['Black_Name'], @$grow['Black_Handle'] ),
            user_reference( REF_LINK, 1, '', @$grow['White_ID'], @$grow['White_Name'], @$grow['White_Handle'] ),
         ));
   if( @$grow['Black_OnVacation'] > 0 || @$grow['White_OnVacation'] > 0 )
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
            echo_off_time( ($to_move == BLACK), ($grow['Black_OnVacation'] > 0), $grow['Black_ClockUsed'] ),
            echo_off_time( ($to_move == WHITE), ($grow['White_OnVacation'] > 0), $grow['White_ClockUsed'] ),
         ),
         'class="Images"' );
   $itable->add_sinfo(
         T_('Current rating'),
         array(
            echo_rating( @$grow['Black_Rating'], true, $grow['Black_ID'] ),
            echo_rating( @$grow['White_Rating'], true, $grow['White_ID'] ),
         ));
   $itable->add_sinfo(
         T_('Start rating'),
         array(
            echo_rating( @$grow['Black_Start_Rating']),
            echo_rating( @$grow['White_Start_Rating']),
         ));
   if( $game_finished )
   {
      $itable->add_sinfo(
            T_('End rating'),
            array(
               echo_rating( @$grow['Black_End_Rating']),
               echo_rating( @$grow['White_End_Rating']),
            ));

      if( $grow['X_Rated'] === 'Y' &&
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
   $itable_str_players = $itable->make_table();
   unset($itable);


   // ------------------------
   // build table-info: time settings

   // reduce player-time by current number of ticks
   if( $grow['Maintime'] > 0 || $grow['Byotime'] > 0 )
   {
      // LastTicks may handle -(time spend) at the moment of the start of vacations
      $hours = ticks_to_hours(get_clock_ticks($grow['ClockUsed']) - $grow['LastTicks']);

      if( $to_move == BLACK )
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

   $timefmt = TIMEFMT_SHORT | TIMEFMT_ZERO;
   $itable = new Table_info('time');
   $itable->add_caption( T_('Time settings and Remaining time') );
   $itable->add_sinfo(
         T_('Color'),
         array(
            ( ($to_move == BLACK)
               ? image( "{$base_path}17/b.gif", T_('Black'), null, 'class="InTextImage"' ) . ' ' . T_('Black to move')
               : image( "{$base_path}17/w.gif", T_('White'), null, 'class="InTextImage"' ) . ' ' . T_('White to move') ),
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
                     $timefmt | TIMEFMT_ADDEXTRA )
            ),
            (( $grow['White_Maintime'] > 0 )
               ? $game_extratime
               : TimeFormat::echo_time_remaining( 0, $grow['Byotype'], $grow['White_Byotime'],
                     $grow['White_Byoperiods'], $grow['Byotime'], $grow['Byoperiods'],
                     $timefmt | TIMEFMT_ADDEXTRA )
            ),
         ));

   $clock_status = array( BLACK => array(), WHITE => array() );
   if( is_vacation_clock($grow['ClockUsed']) )
   {
      $clock_status[$to_move][] = echo_image_vacation( 'in_text',
         TimeFormat::echo_onvacation(
            ($to_move == BLACK) ? $grow['Black_OnVacation'] : $grow['White_OnVacation'] ),
         true );
   }
   elseif( is_weekend_clock_stopped($grow['ClockUsed']) )
      $clock_status[$to_move][] = echo_image_weekendclock(true, true);
   if( is_nighttime_clock( ($to_move == BLACK) ? $grow['Black_ClockUsed'] : $grow['White_ClockUsed'] ) )
      $clock_status[$to_move][] = echo_image_nighttime('in_text', true);

   $clock_stopped = count($clock_status[BLACK]) + count($clock_status[WHITE]);
   $itable->add_sinfo(
         T_('Clock status'),
         array(
            ( $clock_stopped ? T_('Clock stopped') : T_('Clock running') ),
            implode(' ', $clock_status[BLACK]),
            implode(' ', $clock_status[WHITE]),
         ));
   if( $is_admin )
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

   if( $tid && !is_null($tourney) )
   {
      $itable = new Table_info('tourney');
      $itable->add_caption( T_('Tournament info') );

      $itable->add_sinfo(
            T_('ID#tourney'),
            anchor( $base_path."tournaments/view_tournament.php?tid=$tid", "#$tid" )
               . echo_image_tournament_info($tid, true) );
      $itable->add_sinfo(
            T_('Scope#tourney'),
            Tournament::getScopeText($tourney->Scope) );
      $itable->add_sinfo(
            T_('Type#tourney'),
            Tournament::getTypeText($tourney->Type) );
      $itable->add_sinfo(
            T_('Title#tourney'),
            make_html_safe($tourney->Title, true) );
      if( $tourney->Type != TOURNEY_TYPE_LADDER )
         $itable->add_sinfo(
               T_('Current Round#tourney'),
               $tourney->formatRound() );
      $itable->add_sinfo(
            T_('Tourney Status#tourney'),
            Tournament::getStatusText($tourney->Status) );
      if( !is_null($tgame) )
      {
         $itable->add_sinfo(
               T_('Tourney Game Status#tourney'),
               TournamentGames::getStatusText($tgame->Status) );

         if( $tgame->isScoreStatus() && @$grow['Black_ID'] )
         {
            $arr_flags = array();
            if( $tgame->Flags & TG_FLAG_GAME_END_TD )
               $arr_flags[] = T_('by TD#TG_flag');
            $flags_str = (count($arr_flags)) ? sprintf( ' (%s)', implode(', ', $arr_flags)) : '';

            $tg_score = $tgame->getScoreForUser( $grow['Black_ID'] );
            $tg_score_str = score2text( $tg_score, false );
            if( !$game_finished || ( @$grow['Score'] != $tg_score ) )
               $tg_score_str = span('ScoreWarning', $tg_score_str );

            $itable->add_sinfo(
                  T_('Tourney Game Score#tourney'),
                  $tg_score_str . $flags_str );
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
   if( $is_my_game && !$game_finished )
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

   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query) ."<br>\n";
   echo "<h3 class=Header>$title</h3>\n";

   echo
      "<table><tr valign=\"top\">",
      "<td>$itable_str_game<br>$form_str_gameprio<br>$itable_str_tourney</td>",
      "<td>$itable_str_time<br>$itable_str_players</td>",
      "</tr></table>\n";


   $menu_array = array();
   $menu_array[T_('Show game')] = 'game.php?gid='.$gid;
   if( $tid && !is_null($tourney) )
   {
      if( $tourney->Type == TOURNEY_TYPE_LADDER )
         $menu_array[T_('View Ladder')] = "tournaments/ladder/view.php?tid=$tid";
      if( $tourney->allow_edit_tournaments($my_id, TD_FLAG_GAME_END) )
         $menu_array[T_('Admin tournament game')] =
            array( 'url' => "tournaments/game_admin.php?tid=$tid".URI_AMP."gid=$gid", 'class' => 'TAdmin' );
      if( $tourney->allow_edit_tournaments($my_id) )
         $menu_array[T_('Manage tournament')] =
            array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );
   }

   end_page(@$menu_array);
}

?>
