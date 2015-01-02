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
require_once 'include/form_functions.php';
require_once 'include/classlib_user.php';
require_once 'include/rating.php';
require_once 'include/message_functions.php';
require_once 'include/game_functions.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_ladder.php';
require_once 'tournaments/include/tournament_ladder_props.php';
require_once 'tournaments/include/tournament_log_helper.php';
require_once 'tournaments/include/tournament_rules.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentLadderChallenge');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.ladder.challenge');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.ladder.challenge');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'Tournament.ladder.challenge');

   $page = "challenge.php";

/* Actual REQUEST calls used
     tid=&rid=             : info about challenge of current user and Defender-Reg_ID
     tid=&rid=confirm=1    : challenge confirmed -> schedule T-game
     tid=&rid=tl_cancel    : cancel challenge
*/

   $tid = (int)@$_REQUEST['tid'];
   $rid = (int)@$_REQUEST['rid'];
   if ( $tid < 0 ) $tid = 0;
   if ( $rid < 0 ) $rid = 0;

   if ( @$_REQUEST['tl_cancel'] ) // cancel delete
      jump_to("tournaments/ladder/view.php?tid=$tid");

   $tourney = TournamentCache::load_cache_tournament( 'Tournament.ladder.challenge.find_tournament', $tid );
   if ( $tourney->Status != TOURNEY_STATUS_PLAY )
      error('tournament_wrong_status', "Tournament.ladder.challenge.check_status($tid,{$tourney->Status})");

   // checks
   $errors = array();
   if ( $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN|TOURNEY_FLAG_LOCK_TDWORK) )
      $errors[] = $tourney->buildMaintenanceLockText();
   if ( $tourney->isFlagSet(TOURNEY_FLAG_LOCK_CLOSE) )
      $errors[] = Tournament::getLockText(TOURNEY_FLAG_LOCK_CLOSE);

   $tladder_ch = TournamentLadder::load_tournament_ladder_by_user($tid, $my_id); // challenger
   if ( is_null($tladder_ch) )
      $errors[] = T_('Challenger is not participating on ladder');
   $user_ch = User::new_from_row( $player_row );

   $tladder_df = TournamentLadder::load_tournament_ladder_by_user($tid, 0, $rid); // defender
   $user_df = null;
   if ( is_null($tladder_df) )
      $errors[] = T_('Defender is not participating on ladder');
   else
   {
      $user_df = User::load_user( $tladder_df->uid );
      if ( !$user_df )
         error('unknown_user', "Tournament.ladder.challenge.load_user($tid,{$tladder_df->uid}");
   }

   // check if challenge is valid
   $trules = $tprops = null;
   if ( !is_null($tladder_ch) && !is_null($tladder_df) )
   {
      $tl_props = TournamentCache::load_cache_tournament_ladder_props( 'Tournament.ladder.challenge', $tid );

      $trules = TournamentCache::load_cache_tournament_rules( 'Tournament.ladder.challenge', $tid );
      $trules->TourneyType = $tourney->Type;

      $tprops = TournamentCache::load_cache_tournament_properties( 'Tournament.ladder.challenge', $tid );

      // prep TP_Rating for add_form_user_info()
      $tp_rating_ch = TournamentHelper::get_tournament_rating( $tid, $user_ch, $tprops->RatingUseMode, /*strict*/true );
      $tp_rating_df = TournamentHelper::get_tournament_rating( $tid, $user_df, $tprops->RatingUseMode, /*strict*/true );
      if ( !is_null($tp_rating_ch) )
         $user_ch->urow['TP_Rating'] = $tp_rating_ch;
      if ( !is_null($tp_rating_df) )
         $user_df->urow['TP_Rating'] = $tp_rating_df;

      $rating_pos = TournamentLadder::find_ladder_rating_pos( 'Tournament.ladder.challenge',
         $tid, $tl_props, @$user_ch->Rating );
      $challenge_errors = $tl_props->verify_challenge( $tladder_ch, $tladder_df, $rating_pos );
      $errors = array_merge( $errors, $challenge_errors );
   }


   // ---------- Process actions ------------------------------------------------

   if ( @$_REQUEST['confirm'] && !is_null($tladder_ch) && !is_null($tladder_df) && count($errors) == 0 ) // confirm challange
   {
      ta_begin();
      {//HOT-section to start T-game
         $ch_uid = $user_ch->ID;
         $df_uid = $user_df->ID;
         $dbgmsg = "Tournament.ladder.challenge($tid,$ch_uid,$df_uid)";

         $gids = TournamentHelper::create_games_from_tournament_rules( $tid, $tourney->Type, $user_ch, $user_df );
         $cnt_gids = count($gids);
         if ( $cnt_gids != 1 )
            error('internal_error', "$dbgmsg.create_game($cnt_gids)");
         $gid = $gids[0];

         $tg = TournamentLadder::new_tournament_game( $tid, $tladder_ch, $tladder_df );
         $tg->gid = $gid;
         $tg->setStatus( TG_STATUS_PLAY );
         $tg->StartTime = $NOW;
         $tg->insert();

         $tladder_df->update_incoming_challenges( +1 );
         $tladder_ch->update_outgoing_challenges( +1 );

         $msg = sprintf( T_('%s on ladder-rank #%s has challenged you.#T_ladder'), "<user $ch_uid>", $tladder_ch->Rank )
            . "\n"
            . sprintf( T_('Your current ladder-rank is #%s.#T_ladder'), $tladder_df->Rank )
            . "\n\n<tourney $tid>\n"
            . T_('Game') . ": <game $gid>";

         // notify defender about started game
         send_message( "$dbgmsg.notify",
            $msg,
            sprintf( T_('Challenge started for tournament #%s#T_ladder'), $tid ),
            $df_uid, '', /*notify*/true,
            0/*sys-msg*/, MSGTYPE_NORMAL );

         // clear caches
         TournamentGames::delete_cache_tournament_games( $dbgmsg, $tid );
         TournamentLadder::delete_cache_tournament_ladder( $dbgmsg, $tid );

         TournamentLogHelper::log_tournament_ladder_challenge_user( $tid,
            sprintf('Challenger: %s; Defender: %s -> game %s',
               $tladder_ch->build_log_string(1), $tladder_df->build_log_string(1), $gid ));
      }
      ta_end();

      $sys_msg = urlencode( T_('Tournament Game started!') );
      jump_to("game.php?gid=$gid".URI_AMP."sysmsg=$sys_msg");
   }


   // ---------- Tournament Form -----------------------------------

   $tform = new Form( 'tournament', $page, FORM_GET );
   $tform->add_hidden( 'tid', $tid );
   $tform->add_hidden( 'rid', $rid );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   $tform->add_empty_row();

   add_form_user_info( $tform, T_('Defender#T_ladder'),   $user_df, $tladder_df, $trules );
   add_form_user_info( $tform, T_('Challenger#T_ladder'), $user_ch, $tladder_ch, $trules );

   $has_errors = ( count($errors) > 0 );
   if ( $has_errors )
   {
      $tform->add_row( array( 'HR' ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString( T_('There are some errors'), $errors ) ));
      $tform->add_empty_row();
   }

   // EDIT: Submit-Buttons -------------

   $tform->add_row( array(
         'CELL', 2, '',
         'TEXT', T_('Please confirm if you want to challenge this user!#T_ladder') . "<br>\n" . T_('(also see notes below)'), ));

   if ( !$has_errors )
      $tform->add_hidden( 'confirm', 1 );
   $tform->add_row( array(
         'CELL', 2, '',
         'SUBMITBUTTONX', 'tl_challenge', T_('Confirm Challenge#T_ladder'), ( $has_errors ? 'disabled=1' : '' ),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tl_cancel', T_('Cancel') ));


   $title = T_('Challenge Ladder User');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $maxGamesCheck = new MaxGamesCheck();
   echo $maxGamesCheck->get_warn_text();

   $tform->echo_string();

   // show probable game-info/settings for challenge
   show_game_info( $tid, $user_ch, $user_df, $trules, $tprops );

   echo_notes( 'challengenotesTable', T_('Challenge notes#T_ladder'), build_challenge_notes() );


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('View Ladder')] = "tournaments/ladder/view.php?tid=$tid";

   end_page(@$menu_array);
}


function add_form_user_info( &$tform, $utype, $user, $tladder, $trules )
{
   if ( is_null($user) && is_null($tladder) )
      return '';

   $tform->add_row( array( 'HR' ));
   if ( !is_null($user) )
   {
      $tform->add_row( array(
            'DESCRIPTION', span('bold', $utype),
            'TEXT', $user->user_reference(),
            'TEXT', echo_off_time( false, $user->urow['OnVacation'], null, @$trules->WeekendClock ),
            'TEXT', SEP_SPACING . echo_rating( $user->Rating, true, $user->ID), ));
      if ( isset($user->urow['TP_Rating']) )
         $tform->add_row( array(
               'DESCRIPTION', T_('Tournament Rating'),
               'TEXT', echo_rating( $user->urow['TP_Rating'], true, $user->ID ), ));
   }

   if ( !is_null($tladder) )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Ladder Rank'),
            'TEXT', '#' . $tladder->Rank,
            'TEXT', ( ($tladder->RankChanged > 0)
                        ? SEP_SPACING . sprintf( T_('Kept for %s#T_ladder'), $tladder->build_rank_kept(TIMEFMT_ZERO, NO_VALUE) )
                        : '' ), ));
   }

   $tform->add_empty_row();
}//add_form_user_info

// show game-info-table for challenge
function show_game_info( $tid, $user_ch, $user_df, $trules, $tprops )
{
   global $player_row;
   if ( is_null($user_ch) || is_null($user_df) )
      return;

   $ch_rating = TournamentHelper::get_tournament_rating( $tid, $user_ch, $tprops->RatingUseMode );
   $df_rating = TournamentHelper::get_tournament_rating( $tid, $user_df, $tprops->RatingUseMode );

   $game_row = array();
   $gs_tourney = $trules->convertTournamentRules_to_GameSetup();
   $ch_is_black = $trules->prepare_create_game_row( $game_row, $gs_tourney, $user_ch->ID, $ch_rating, $user_df->ID, $df_rating );

   $game_row = $trules->convertTournamentRules_to_GameRow();
   $game_row['calculated'] = $trules->needsCalculatedHandicap();

   // user-data
   $game_row['other_id']     = $user_df->ID;
   $game_row['other_handle'] = $user_df->Handle;
   $game_row['other_name']   = $user_df->Name;
   $game_row['other_rating'] = $df_rating;
   $user_row = array(
      'ID'      => $user_ch->ID,
      'Handle'  => $user_ch->Handle,
      'Name'    => $user_ch->Name,
      'Rating2' => $ch_rating,
   );

   echo "<br>\n";

   // NOTE: challenger must be current user, properly set in GameSetup
   game_info_table( GSET_TOURNAMENT_LADDER, $game_row, $user_row, $user_ch->hasRating(), $gs_tourney );
}//show_game_info

/*! \brief Returns array with notes about challenging users on ladder. */
function build_challenge_notes()
{
   $notes = array();

   $notes[] = T_('After confirmation a tournament game will be started immediately.');
   $notes[] = null; // empty line

   return $notes;
}//build_challenge_notes
?>
