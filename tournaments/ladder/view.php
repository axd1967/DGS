<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/classlib_user.php';
require_once 'include/classlib_userconfig.php';
require_once 'include/time_functions.php';
require_once 'include/game_functions.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_ladder.php';
require_once 'tournaments/include/tournament_ladder_props.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentLadderView');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.ladder.view');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.ladder.view');
   $my_id = $player_row['ID'];

   // JavaScript for ladder-view: set translated texts as global JS-vars
   $js_enabled = is_javascript_enabled();
   if ( $js_enabled )
   {
      $js  = add_js_var( 'T_rankInfoTitle', T_('Player Rank Details') );
      $js .= add_js_var( 'T_rankInfoFormat', TournamentLadder::get_rank_info_format() );
   }
   else
      $js = null;

/* Actual REQUEST calls used
     tid=                           : view T-ladder
     tid=&admin=1                   : admin-mode on T-ladder (edit-ladder)
     tid=&admin=1&ta_updrank&rid=&new_rank= : change rank of user given by rid
*/

   $tid = (int)@$_REQUEST['tid'];
   $rid = (int)@$_REQUEST['rid'];
   $admin_mode = (bool)@$_REQUEST['admin'];
   $new_rank = (int)@$_REQUEST['new_rank'];
   if ( $tid < 0 ) $tid = 0;

   $tourney = TournamentCache::load_cache_tournament( 'Tournament.ladder_view.find_tournament', $tid );
   $tstatus = new TournamentStatus( $tourney );
   $is_seed_status = ( $tourney->Status == TOURNEY_STATUS_PAIR );
   $allow_play = ( $tourney->Status == TOURNEY_STATUS_PLAY );
   $tdwork_locked = $tourney->isFlagSet(TOURNEY_FLAG_LOCK_TDWORK);
   $play_locked = $tdwork_locked || $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN | TOURNEY_FLAG_LOCK_CLOSE);

   $allow_edit_tourney = TournamentHelper::allow_edit_tournaments($tourney, $my_id);
   if ( $admin_mode && !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.ladder_view.admin_mode($tid,$my_id)");

   $tl_props = TournamentCache::load_cache_tournament_ladder_props( 'Tournament.ladder_view', $tid );
   $tprops = TournamentCache::load_cache_tournament_properties( 'Tournament.ladder_view', $tid );
   $need_tp_rating = $tprops->need_rating_copy();

   $errors = $tstatus->check_view_status( TournamentHelper::get_view_data_status($allow_edit_tourney) );
   $allow_view = ( count($errors) == 0 );
   $allow_admin = ($admin_mode)
      ? TournamentLadder::allow_edit_ladder($tourney, $errors) // check-locks
      : false;

   $tl_user = $arr_tg_counts = $ltable = null;
   $cnt_players = 0;
   if ( $allow_view )
   {
      if ( $admin_mode && $allow_admin && @$_REQUEST['ta_updrank'] ) // move rank (by TD)
      {
         $prep_errors = array();
         $tladder = prepare_update_rank( $tid, $rid, $new_rank, $prep_errors );
         if ( count($prep_errors) )
            $errors = array_merge( $errors, $prep_errors );
         else
         {
            $old_rank = $tladder->Rank;
            if ( $tladder->change_user_rank($new_rank, $allow_edit_tourney) )
            {
               $sys_msg = urlencode( sprintf( T_('Moved selected user from rank [%s] to [%s]!#T_ladder'),
                                              $old_rank, $new_rank ));
               jump_to("tournaments/ladder/view.php?tid=$tid".URI_AMP."admin=1".URI_AMP."rid=$rid"
                     . URI_AMP."sysmsg=$sys_msg");
            }
         }
      }

      $cfg_tblcols = ConfigTableColumns::load_config( $my_id, CFGCOLS_TOURNAMENT_LADDER_VIEW );
      if ( !$cfg_tblcols )
         error('user_init_error', "Tournament.ladder.view.init.config_table_cols($tid)");

      // init table
      $page = "view.php?";
      $ltable = new Table( 'tournament_ladder', $page, $cfg_tblcols, '',
         TABLE_NO_SORT|TABLE_NO_PAGE|TABLE_NO_SIZE|TABLE_ROWS_NAVI );
      $ltable->use_show_rows(false);
      $ltable->add_or_del_column();

      // page vars
      $page_vars = new RequestParameters();
      $page_vars->add_entry( 'tid', $tid );
      if ( $admin_mode )
         $page_vars->add_entry( 'admin', ($admin_mode ? 1 : 0) );
      $ltable->add_external_parameters( $page_vars, true ); // add as hiddens

      // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
      $ltable->add_tablehead( 2, T_('Change#TL_header'), 'Center', 0 );
      $ltable->add_tablehead( 1, T_('Rank#T_ladder'), 'Number', TABLE_NO_HIDE, 'Rank+' );
      $ltable->add_tablehead(19, T_('Rating Pos#TL_header'), 'RatPos', 0 );
      $ltable->add_tablehead( 3, T_('Name#header'), 'User', 0 );
      $ltable->add_tablehead( 4, T_('Userid#header'), 'User', TABLE_NO_HIDE );
      $ltable->add_tablehead( 5, T_('Country#header'), 'Image', 0 );
      $ltable->add_tablehead( 6, T_('User Rating#header'), 'Rating', 0 );
      if ( $need_tp_rating )
         $ltable->add_tablehead(14, T_('Tournament Rating#header'), 'Rating', 0 );
      $ltable->add_tablehead( 7, T_('Actions#header'), '', TABLE_NO_HIDE );
      $ltable->add_tablehead(12, new TableHead( T_('Running and finished tournament games'), 'images/table.gif'), 'Image', 0 );
      $ltable->add_tablehead( 8, T_('Challenges-In#header'), '', TABLE_NO_HIDE );
      $ltable->add_tablehead(16, T_('Challenges-Out#header'), '', 0 );
      $ltable->add_tablehead( 9, T_('Rank Changed#T_ladder'), 'Date', 0 );
      $ltable->add_tablehead(10, T_('Rank Kept#header'), '', 0 );
      $ltable->add_tablehead(17, span('title="'.basic_safe(T_('Consecutive Wins').' [: '.T_('Max. Consecutive Wins').']').'"',
         T_('#Consecutive Wins#header')), 'NumberC', 0 );
      $ltable->add_tablehead(15, new TableHead( T_('User online#header'), 'images/online.gif',
         sprintf( T_('Indicator for being online up to %s mins ago'), SPAN_ONLINE_MINS)
            . ', ' . T_('or on vacation#header') ), 'Image', 0 );
      $ltable->add_tablehead(13, T_('Last access#T_header'), '', 0 );
      $ltable->add_tablehead(18, T_('Tournament last move#T_header'), '', 0 );
      $ltable->add_tablehead(11, T_('Started#header'), 'Date', 0 );

      $ltable->set_default_sort( 1 );
      //$ltable->make_sort_images(); //obvious, so left away as it also take a bit of unneccessary table-width

      $iterator = TournamentLadder::load_cache_tournament_ladder( 'Tournament.ladder_view', $tid, $need_tp_rating,
         /*need-TP.Fin*/true, /*TP.Lastmove*/true, 0, /*idx*/true );
      $cnt_players = $iterator->getItemCount();
      $ltable->set_found_rows( $cnt_players );
      $ltable->set_rows_per_page( null ); // no navigating
      $show_rows = $ltable->compute_show_rows( $iterator->getResultRows() );

      if ( $ltable->Is_Column_Displayed[19] )
         TournamentLadder::compute_user_rating_pos_tournament_ladder( $iterator );

      if ( $admin_mode )
      {
         if ( $allow_admin )
            $ltable->set_extend_table_form_function( 'admin_edit_ladder_extend_table_form' ); //defined below
      }
      else
      {
         $tg_iterator = TournamentCache::load_cache_tournament_games( 'Tournament.ladder_view',
            $tid, 0, 0, array(TG_STATUS_PLAY, TG_STATUS_WAIT, TG_STATUS_SCORE) );

         // add ladder-info (challenge-range)
         $tl_user = $tl_props->fill_ladder_challenge_range( $iterator, $my_id );
         $arr_tg_counts = $tl_props->fill_ladder_running_games( $iterator, $tg_iterator, $my_id );
      }
   }//allow-view


   $title = sprintf( T_('Tournament-Ladder #%s'), $tid );
   start_page( $title, true, $logged_in, $player_row, null, null, $js );
   echo "<h2 class=Header>", $tourney->build_info(2), "</h2>\n";

   db_close(); // HTML to send for ladder can be quite large, so free db-connection as early as possible

   $maxGamesCheck = new MaxGamesCheck();
   echo $maxGamesCheck->get_warn_text();

   if ( $allow_view )
   {
      $my_uid = (is_null($tl_user)) ? 0 : $my_id; // i'm participating on ladder
      $tform = $ltable->make_table_form();
      while ( list(,$arr_item) = $iterator->getListIterator() )
      {
         list( $tladder, $orow ) = $arr_item;
         $uid = $tladder->uid;
         $user = User::new_from_row($orow, 'TLP_');
         $is_mine = ( $my_id == $uid );

         $run_games_str = $fin_games_str = '';
         if ( $is_mine || $ltable->Is_Column_Displayed[12] )
         {
            if ( $tladder->ChallengesIn + $tladder->ChallengesOut > 0 )
            {
               $run_games_str = echo_image_table( IMG_GAMETABLE_RUN,
                  $base_path."show_games.php?tid=$tid".URI_AMP."uid=$uid",
                  ( ($is_mine)
                        ? T_('My running tournament games')
                        : sprintf( T_('Running tournament games of user [%s]'), $user->Handle ) ),
                  false );
            }
            if ( @$orow['TP_Finished'] > 0 )
            {
               $fin_games_str = echo_image_table( IMG_GAMETABLE_FIN,
                  $base_path."show_games.php?tid=$tid".URI_AMP."uid=$uid".URI_AMP."finished=1",
                  ( ($is_mine)
                        ? T_('My finished tournament games')
                        : sprintf( T_('Finished tournament games of user [%s]'), $user->Handle ) ),
                  false );
            }
         }

         $row_str = array();

         if ( $ltable->Is_Column_Displayed[ 1] )
            $row_str[ 1] = "<a name=\"rank{$tladder->Rank}\">{$tladder->Rank}.</a>";
         if ( $ltable->Is_Column_Displayed[ 2] )
            $row_str[ 2] = build_rank_change( $tladder );
         if ( $ltable->Is_Column_Displayed[ 3] )
            $row_str[ 3] = user_reference( REF_LINK, 1, '', $uid, $user->Name, '');
         if ( $ltable->Is_Column_Displayed[ 4] )
            $row_str[ 4] = user_reference( REF_LINK, 1, '', $uid, $user->Handle, '');
         if ( $ltable->Is_Column_Displayed[ 5] )
            $row_str[ 5] = getCountryFlagImage( $user->Country );
         if ( $ltable->Is_Column_Displayed[ 6] )
            $row_str[ 6] = echo_rating( $user->Rating, true, $uid);
         if ( $ltable->Is_Column_Displayed[ 7] ) // actions
            $row_str[ 7] = build_action_row_str( $tladder, $tform, $is_mine, $rid,
               ( $ltable->Is_Column_Displayed[12] ? '' : $run_games_str . $fin_games_str) );
         if ( $ltable->Is_Column_Displayed[ 8] )
            $row_str[ 8] = implode(' ', $tladder->build_linked_incoming_games( $my_uid ));
         if ( $ltable->Is_Column_Displayed[ 9] )
            $row_str[ 9] = ($tladder->RankChanged > 0) ? date(DATE_FMT2, $tladder->RankChanged) : '';
         if ( $ltable->Is_Column_Displayed[10] )
            $row_str[10] = $tladder->build_rank_kept();
         if ( $ltable->Is_Column_Displayed[11] )
            $row_str[11] = ($tladder->Created > 0) ? date(DATE_FMT2, $tladder->Created) : '';
         if ( $ltable->Is_Column_Displayed[12] )
            $row_str[12] = $run_games_str . $fin_games_str;
         if ( $ltable->Is_Column_Displayed[13] )
            $row_str[13] = TimeFormat::echo_time_diff( $NOW, $user->Lastaccess, 24, TIMEFMT_SHORT|TIMEFMT_ZERO );
         if ( $need_tp_rating && @$ltable->Is_Column_Displayed[14] )
            $row_str[14] = echo_rating( $orow['TP_Rating'], true, $uid);
         if ( $ltable->Is_Column_Displayed[15] )
            $row_str[15] = echo_user_online_vacation( $user->urow['TLP_OnVacation'], $user->Lastaccess );
         if ( $ltable->Is_Column_Displayed[16] )
            $row_str[16] = implode(' ', $tladder->build_linked_outgoing_games( $my_uid ));
         if ( $ltable->Is_Column_Displayed[17] )
            $row_str[17] = $tladder->SeqWins
               . (( $tladder->SeqWins != $tladder->SeqWinsBest ) ? ' : ' . $tladder->SeqWinsBest : '' );
         if ( $ltable->Is_Column_Displayed[18] )
         {
            $tp_lastmoved = (int)@$orow['TP_X_Lastmoved'];
            $row_str[18] = ( $tp_lastmoved > 0 )
               ? TimeFormat::echo_time_diff( $NOW, $tp_lastmoved, 24, TIMEFMT_SHORT|TIMEFMT_ZERO )
               : NO_VALUE;
         }
         if ( $ltable->Is_Column_Displayed[19] )
            $row_str[19] = span('smaller', (int)$tladder->UserRatingPos);

         if ( $is_mine )
            $row_str['extra_class'] = 'TourneyUser';
         $ltable->add_row( $row_str );
      }
   }//allow-view

   if ( $admin_mode )
   {
      echo '<h3 class="Header">', T_('Edit Ladder'), "</h3>\n";
      if ( TournamentUtils::isAdmin() )
         echo T_('Tournament Flags'), ': ', $tourney->formatFlags(NO_VALUE), "<br><br>\n";
   }

   if ( count($errors) )
   {
      echo "<table><tr>",
         buildErrorListString( T_('There are some errors'), $errors, 1, false ),
         "</tr></table>\n";
   }

   if ( $allow_view )
   {
      if ( $play_locked )
      {
         if ( !$admin_mode || $tdwork_locked )
            echo $tourney->buildMaintenanceLockText(-1, ': '),
               span('LadderWarn', T_('Challenging locked#T_ladder')), ".<br>\n";
         if ( $tourney->isFlagSet(TOURNEY_FLAG_LOCK_CLOSE) )
            echo Tournament::getLockText(TOURNEY_FLAG_LOCK_CLOSE), MINI_SPACING,
               span('LadderWarn', T_('Challenging locked#T_ladder')), ".<br>\n";
         echo "<br>\n";
      }

      if ( $cnt_players > 0 && is_array($arr_tg_counts) )
      {
         echo sprintf( T_('Ladder summary (%s players): %s running games, %s finished games waiting to be processed#T_ladder'),
            $cnt_players, (int)@$arr_tg_counts[TG_STATUS_PLAY], (int)@$arr_tg_counts[TG_STATUS_SCORE] ), "<br>\n";
      }

      if ( !is_null($tl_user) && !$admin_mode )
      {
         if ( $tl_props->MaxChallenges > 0 )
            $ch_out_str = sprintf( T_('You have started %s of max. %s outgoing game challenges#T_ladder'),
                                   $tl_user->ChallengesOut, $tl_props->MaxChallenges ) . ': ';
         else
            $ch_out_str = sprintf( T_('You have started %s outgoing game challenges#T_ladder'),
                                   $tl_user->ChallengesOut ) . ': ';
         echo
            ( ($tl_user->MaxChallengedOut)
               ? span('TLMaxChallenges', $ch_out_str) . span('LadderWarn', T_('Challenging stalled#T_ladder'))
               : $ch_out_str . T_('Challenging allowed#T_ladder')
            ), ".<br>\n";
         if ( !is_javascript_enabled() )
         {
            $cmp_curr_rank = ( $tl_user->PeriodRank > 0 ) ? $tl_user->PeriodRank : $tl_user->StartRank;
            $cmp_prev_rank = ( $tl_user->HistoryRank > 0 ) ? $tl_user->HistoryRank : $tl_user->StartRank;
            echo sprintf( T_('Your start rank (change) in the current period is: %s#T_ladder'),
                        TournamentLadder::build_rank_diff( $tl_user->Rank, $cmp_curr_rank )), "<br>\n";
            echo sprintf( T_('Your rank (change) in the previous period was: %s#T_ladder'),
                        TournamentLadder::build_rank_diff( $tl_user->Rank, $cmp_prev_rank )), "<br>\n";
         }

         $jump_rank = max( 1, $tl_user->Rank - 10 );
         echo anchor( "#rank{$jump_rank}", sprintf( T_('Your current rank is %s.#T_ladder'), $tl_user->Rank ) ),
            MED_SPACING,
            sprintf( T_('Your best rank is %s.#T_ladder'), $tl_user->BestRank ),
            "<br>\n";
      }

      $ltable->echo_table();
   }

   if ( !is_null($tl_props) )
   {
      $tt_notes = $tl_props->build_notes_props();
      echo_notes( 'ttprops', $tt_notes[0], $tt_notes[1] );
   }


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   if ( $allow_view )
   {
      $menu_array[T_('View Ladder')] = "tournaments/ladder/view.php?tid=$tid";
      if ( !$is_seed_status && !is_null($tl_user) && !$admin_mode )
         $menu_array[T_('Withdraw from Ladder')] = "tournaments/ladder/withdraw.php?tid=$tid";
   }
   $menu_array[T_('Tournament participants')] = "tournaments/list_participants.php?tid=$tid";
   if ( in_array($tourney->Status, TournamentHelper::get_view_data_status()) )
   {
      $menu_array[T_('All running games')] = "show_games.php?tid=$tid".URI_AMP."uid=all";
      $menu_array[T_('All finished games')] = "show_games.php?tid=$tid".URI_AMP."uid=all".URI_AMP."finished=1";
   }
   if ( $allow_edit_tourney )
   {
      if ( $is_seed_status )
         $menu_array[T_('Admin Ladder')] =
            array( 'url' => "tournaments/ladder/admin.php?tid=$tid", 'class' => 'TAdmin' );
      $menu_array[T_('Edit Ladder')] =
         array( 'url' => "tournaments/ladder/view.php?tid=$tid".URI_AMP."admin=1", 'class' => 'TAdmin' );
      $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );
   }

   end_page(@$menu_array);
}//main


// return TournamentLadder-object; maybe null
function prepare_update_rank( $tid, $rid, $new_rank, &$errors )
{
   $tladder = null;
   if ( @$_REQUEST['ta_updrank'] && $rid <= 0 )
      $errors[] = T_('Missing user selection for rank change#T_ladder');
   else
   {
      $tladder = TournamentLadder::load_tournament_ladder_by_user($tid, 0, $rid);
      if ( is_null($tladder) )
         error('invalid_args', "Tournament.ladder_view.find_ladder_user($tid,$rid)");
   }

   $max_rank = TournamentLadder::load_max_rank($tid);
   if ( $new_rank < 1 || $new_rank > $max_rank )
      $errors[] = sprintf( T_('New rank [%s] is out of valid range %s.#T_ladder'), $new_rank,
         build_range_text(1, $max_rank) );

   if ( !is_null($tladder) && $new_rank == $tladder->Rank )
      $errors[] = sprintf( T_('No change in rank [%s] for selected user#T_ladder'), $tladder->Rank );

   return $tladder;
}

// callback-func for admin-edit-ladder Table-form adding form-elements below table
function admin_edit_ladder_extend_table_form( &$table, &$form )
{
   $result = $form->print_insert_text_input( 'new_rank', 6, 6, get_request_arg('new_rank') );
   $result .= $form->print_insert_submit_button( 'ta_updrank', T_('Update rank#T_ladder') );
   return $result;
}

function build_rank_change( $tladder )
{
   global $js_enabled;

   $cmp_rank = ( $tladder->PeriodRank > 0 ) ? $tladder->PeriodRank : $tladder->StartRank;
   $result = TournamentLadder::build_rank_diff( $tladder->Rank, $cmp_rank, '%2$s' );
   if ( $js_enabled )
   {
      $result = anchor( '#', $result, '',
         array(
            'class' => 'TLadderRank',
            'onmouseover' => sprintf( "showTLRankInfo(event,%s,%s,%s,%s);",
                                      $tladder->Rank, $tladder->BestRank, $cmp_rank, $tladder->HistoryRank ),
            'onmouseout' => 'hideInfo();' ));
   }
   return $result;
}//build_rank_change

function build_action_row_str( &$tladder, &$form, $is_mine, $rid, $run_games_str )
{
   global $base_path, $admin_mode, $allow_play, $allow_admin;
   $tid = $tladder->tid;

   $row_str = '';
   if ( $admin_mode )
   {
      if ( !$allow_admin )
         $row_str = span('LadderWarn', T_('Edit prohibited#T_ladder'));
      else
      {
         $row_str =
            anchor( $base_path."tournaments/ladder/admin.php?tid=$tid".URI_AMP."uid={$tladder->uid}",
                  image( $base_path.'images/edit.gif', 'E', '', 'class="Action InTextImage"' ), T_('Admin user') )
            . ' '
            . $form->print_insert_radio_buttonsx( 'rid', array( $tladder->rid => '' ), ($rid == $tladder->rid) );
      }
   }
   elseif ( $is_mine )
   {
      $out = array();
      if ( $tladder->RatingPos > 0 )
         $out[] = sprintf( T_('Your ladder-rank ordered by rating would be %s.'), $tladder->RatingPos );
      $row_str = span('TourneyUser', T_('This is you#T_ladder'), '%s', implode('; ', $out));
      if ( $run_games_str )
         $row_str .= SMALL_SPACING . $run_games_str;
   }
   elseif ( $allow_play )
   {
      if ( $tladder->AllowChallenge )
      {
         global $tl_user, $play_locked;
         if ( $play_locked )
            $row_str = span('LadderWarn', T_('Challenging locked#T_ladder') );
         elseif ( !is_null($tl_user) && $tl_user->MaxChallengedOut )
            $row_str = span('LadderWarn', T_('Challenging stalled#T_ladder') );
         else
            $row_str = sprintf( '[%s]',
               anchor( $base_path."tournaments/ladder/challenge.php?tid=$tid".URI_AMP."rid={$tladder->rid}",
                       T_('Challenge this user#T_ladder') ));
      }
      elseif ( $tladder->MaxChallengedIn )
      {
         $row_str = span('LadderInfo', sprintf( T_('Already in %s challenges#T_ladder'), $tladder->ChallengesIn ));
      }
      elseif ( $tladder->RematchWait >= 0 )
      {
         if ( $tladder->RematchWait > 0 )
         {
            $time_str = TournamentLadderProps::echo_rematch_wait($tladder->RematchWait, true);
            $time_str = sprintf( T_('Rematch Wait [%s]#T_ladder'), $time_str );
         }
         else
            $time_str = T_('Rematch Wait due#T_ladder');
         $row_str = span('LadderInfo', $time_str);
      }
   }

   return $row_str;
}//build_action_row_str
?>
