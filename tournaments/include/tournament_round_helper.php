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

 /* Author: Jens-Uwe Gaspar */

$TranslateGroups[] = "Tournament";

require_once 'include/std_classes.php';
require_once 'include/db/bulletin.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_extension.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_limits.php';
require_once 'tournaments/include/tournament_log_helper.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_pool_classes.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_rules.php';
require_once 'tournaments/include/tournament_utils.php';

 /*!
  * \file tournament_round_helper.php
  *
  * \brief General functions to support tournament management of round-robin tournaments with db-access.
  */


define('TRR_START_TGAMES_LOCK_HOURS', 3 ); // 3 hours lock on tournament-extension-locking

 /*!
  * \class TournamentRoundHelper
  *
  * \brief Helper-class for round-robin-like tournaments with mostly static functions
  *        to support Tournament management with db-access combining forces of different tournament-classes.
  */
class TournamentRoundHelper
{

   /*!
    * \brief Processes end of tournament-game for round-robin-tournament.
    *
    * \note Do NOT use directly. Use TournamentHelper.process_tournament_game_end() instead.
    */
   public static function process_tournament_round_robin_game_end( $tourney, $tgame )
   {
      $tid = $tourney->ID;

      ta_begin();
      {//HOT-section to process tournament-game-end
         $tgame->setStatus(TG_STATUS_DONE);
         $tgame->update();

         // update TP.Finished/Won/Lost for challenger and defender (except for annulled(=detached) games)
         if ( !( $tgame->Flags & TG_FLAG_GAME_DETACHED ) )
         {
            TournamentParticipant::update_game_end_stats( $tid, $tgame->Challenger_rid, $tgame->Challenger_uid, $tgame->Score );
            TournamentParticipant::update_game_end_stats( $tid, $tgame->Defender_rid, $tgame->Defender_uid, -$tgame->Score );
         }

         list( $withdraw_tp, $logmsg_timeout ) =
            TournamentHelper::handle_timeout_loss_for_participant( $tgame, 0, 0 ); // no penalty

         TournamentLogHelper::log_tournament_round_robin_game_end( $tid,
            sprintf('Game End(game %s) Round_ID[%s] Pool[%s]: user_role:rid/uid Challenger:%s/%s vs Defender:%s/%s; T-Game(%s): Status=[%s], Flags=[%s], Score=[%s]',
               $tgame->gid, $tgame->Round_ID, $tgame->Pool,
               $tgame->Challenger_rid, $tgame->Challenger_uid,
               $tgame->Defender_rid, $tgame->Defender_uid,
               $tgame->ID, $tgame->Status, $tgame->formatFlags(), $tgame->Score ) .
            $logmsg_timeout );
      }
      ta_end();

      return true;
   }//process_tournament_round_robin_game_end

   /*!
    * \brief Starts all tournament games needed for current round and specified pools, prints progress by printing
    *       and flushing on STDOUT.
    * \param $create_pools 0 = start games for all pools; otherwise array with tier-pool-keys for pools to create
    *
    * \note TournamentRound-status will be set to PLAY if ALL expected games have been started.
    *
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    * \note to make flushing work, PAGEFLAG_IMPLICIT_FLUSH is required on using-page!
    *
    * \return NULL on lock-error,
    *       or error-text on severe consistency-error (which also prohibits game-pairing till it's fixed by keeping
    *             existing tournament-extension-entry),
    *       or on success: arr( number of started games, expected number of games, tround-status-switched )
    */
   public static function start_tournament_round_games( $tlog_type, $tourney, $tround, $create_pools )
   {
      global $NOW;
      $tid = $tourney->ID;
      $round = $tround->Round;

      // check pools to create games for -> create_pools = 0 | arr( pools )
      if ( is_numeric($create_pools) && $create_pools == 0 )
         $dbg_pool = $create_pools;
      elseif ( is_array($create_pools) )
      {
         if ( count($create_pools) == 0 )
            return ErrorCode::get_error_text('invalid_args')
               . "; TRH:start_tround_games.check.pools_arr.empty($tid,$round)";

         $p_parser = new PoolParser( $tourney->Type, $tround );
         foreach ( $create_pools as $tier_pool_key )
         {
            list( $tier, $pool ) = TournamentUtils::decode_tier_pool_key( $tier_pool_key );
            if ( !$p_parser->is_valid_tier_pool( $tier, $pool ) )
            {
               return ErrorCode::get_error_text('invalid_args')
                  . "; TRH:start_tround_games.check.pools_arr.bad_value($tid,$round,$tier,$pool)";
            }
         }
         $dbg_pool = implode(',', $create_pools);
      }
      else
      {
         return ErrorCode::get_error_text('invalid_args')
            . "; TRH:start_tround_games.check.pools.bad_type($tid,$round,$create_pools)";
      }
      $dbgmsg = "TRH:start_tournament_round_games($tid,$round,pools=[$dbg_pool])";


      // lock T-ext (expires after 3 hours)
      $t_ext = new TournamentExtension( $tid, TE_PROP_TROUND_START_TGAMES, 0, $NOW );
      if ( !$t_ext->getExtensionLock( TRR_START_TGAMES_LOCK_HOURS * SECS_PER_HOUR ) )
         return null;
      echo sprintf( T_('Established lock for starting tournament games. Lock expires in %s hours.'), TRR_START_TGAMES_LOCK_HOURS),
         "<br>\n";


      // read T-rule
      $trules = TournamentCache::load_cache_tournament_rules( $dbgmsg, $tid );
      $trules->TourneyType = $tourney->Type;
      $games_factor = TournamentHelper::determine_games_factor( $tid, $trules );

      // read T-props
      $tprops = TournamentCache::load_cache_tournament_properties( $dbgmsg, $tid );

      // read T-games: read all existing TGames for specified pools to check if creation has been partly done
      $check_tgames = array(); // uid.uid => game-count
      $created_games_col = array(); // uid.opp-id => BLACK | WHITE; only used for ALTERNATE-htype
      $tg_iterator = new ListIterator( "$dbgmsg.find_tgames" );
      if ( $trules->Handicaptype == TRULE_HANDITYPE_ALTERNATE )
      {
         // for htype ALTERNATE: read all created Games for existing Black/White-distribution
         $tg_iterator->addQuerySQLMerge( new QuerySQL(
               SQLP_FIELDS, 'G.Black_ID AS G_Black_ID', 'G.White_ID AS G_White_ID',
               SQLP_FROM,   'INNER JOIN Games AS G ON G.ID=TG.gid' ));
      }
      $tg_iterator = TournamentGames::load_tournament_games( $tg_iterator, $tid, $tround->ID, $create_pools );
      while ( list(,$arr_item) = $tg_iterator->getListIterator() )
      {
         list( $tgame, $orow ) = $arr_item;
         list( $uid1, $uid2 ) = $tgame->get_ordered_uids();
         $fkey = "$uid1.$uid2";
         if ( !isset($check_tgames[$fkey]) )
            $check_tgames[$fkey] = 1;
         else
            ++$check_tgames[$fkey];
         if ( $trules->Handicaptype == TRULE_HANDITYPE_ALTERNATE )
         {
            $b_uid = $orow['G_Black_ID'];
            $w_uid = $orow['G_White_ID'];
            $created_games_col["$b_uid.$w_uid"] = BLACK;
            $created_games_col["$w_uid.$b_uid"] = WHITE;
         }
      }
      unset($tg_iterator);

      // ensure, that games-per-challenge for existing games is ok (e.g. half of DOUBLE-game must be fixed by admin)
      $inconsistencies = array();
      foreach ( $check_tgames as $fkey => $cnt )
      {
         if ( $cnt != $games_factor )
            $inconsistencies[] = $fkey;
      }
      if ( count($inconsistencies) > 0 )
      {
         return sprintf( T_('Inconsistencies found: for tournament #%s in round %s there is a mismatch of games per challenge (%s) for user-pairs:'),
                         $tid, $round, "$games_factor <-> $cnt" )
            . "<br>\n[" . implode('] [', $inconsistencies) . ']';
      }

      // read specified pools with all users and TPs (if needed for T-rating), need TP_ID for TG.*_rid
      $load_opts_tpool = TPOOL_LOADOPT_TP_ID | TPOOL_LOADOPT_USER | TPOOL_LOADOPT_ONLY_RATING | TPOOL_LOADOPT_UROW_RATING;
      if ( $tprops->need_rating_copy() )
         $load_opts_tpool |= TPOOL_LOADOPT_TRATING;
      $tpool_iterator = new ListIterator( "$dbgmsg.load_pools" );
      $tpool_iterator->addIndex( 'uid' );
      $tpool_iterator = TournamentPool::load_tournament_pools( $tpool_iterator,
         $tid, $round, $create_pools, $load_opts_tpool );

      $poolTables = new PoolTables( $tpool_iterator );
      if ( $trules->Handicaptype == TRULE_HANDITYPE_ALTERNATE )
         $poolTables->reorder_pool_users_by_tp_id();
      $expected_games = $poolTables->calc_pool_games_count( $games_factor );

      $errmsg = null;
      $switched_tround_status = false;
      if ( $poolTables->count_unassigned_pool_users() > 0 )
      {
         $errmsg = sprintf( T_('Inconsistency found: there are %s unassigned pool-users for tournament #%s in round %s.'),
            $poolTables->count_unassigned_pool_users(), $tid, $round );
      }
      else
      {
         list( $count_games, $count_old_games ) =
            self::start_games_for_specific_pools( $tourney->Type, $tround, $trules, $poolTables, $check_tgames,
               $created_games_col );

         if ( $count_games > 0 )
         {
            TournamentLogHelper::log_start_tournament_games( $tid, $tlog_type, $tround, $dbg_pool,
               $expected_games, $count_old_games, $count_games );

            // clear T-games cache
            TournamentGames::delete_cache_tournament_games( "TRH:start_tournament_round_games($tid,$round)", $tid );
         }

         // switch T-round-status PAIR -> PLAY (only if ALL pools processed and all expected games have been started)
         $count_games += $count_old_games;
         if ( $create_pools == 0 && $count_games == $expected_games )
         {
            $tround->setStatus( TROUND_STATUS_PLAY );
            if ( $tround->update() )
               $switched_tround_status = true;
         }
      }

      // unlock T-ext
      $t_ext->delete();
      echo T_('Released lock for starting tournament games.'), "<br>\n";

      return ( !is_null($errmsg) ) ? $errmsg : array( $count_games, $expected_games, $switched_tround_status );
   }//start_tournament_round_games

   /*!
    * \brief Starts tournament games for selected pools, prints progress by printing and flushing on STDOUT.
    * \internal used by start_tournament_round_games()
    * \return arr( count-games-created, count-old-games-existing, count-games-expected )
    */
   private static function start_games_for_specific_pools( $tourney_type, $tround, $trules, $poolTables,
         $check_tgames, $created_games_col )
   {
      mt_srand((double)microtime()*1000000);

      // loop over specified pools
      echo "<table id=\"Progress\"><tr><td><ul>\n";
      $count_games = $count_old_games = $progress = 0;
      $arr_pools = $poolTables->get_pools();
      foreach ( $arr_pools as $tier_pool_key => $arr_users )
      {
         if ( count($arr_users) == 0 ) // skip empty pools
            continue;
         list( $tier, $pool ) = TournamentUtils::decode_tier_pool_key( $tier_pool_key );

         echo "<br>\n<li>", PoolViewer::format_tier_pool( $tourney_type, $tier, $pool ), ":<br>\n";

         // determine start-color for alternating color
         $chall_col_start_black = ( $trules->Handicaptype == TRULE_HANDITYPE_ALTERNATE )
            ? self::determine_htype_alternate_start_color( $arr_users, $created_games_col )
            : null;

         $count_game_curr = $count_games;
         while ( count($arr_users) )
         {
            $ch_uid = array_shift( $arr_users ); // challenger
            $ch_tpool = $poolTables->get_user_tournament_pool( $ch_uid );
            $user_ch = $ch_tpool->User;

            // start new (user-row) with same color to alternate (only used for TRULE_HANDITYPE_ALTERNATE)
            $chall_col_black = $chall_col_start_black;

            // start game with all remaining opponent-users (as defender)
            foreach ( $arr_users as $df_uid )
            {
               $df_tpool = $poolTables->get_user_tournament_pool( $df_uid );

               // check if TGames already exists for pair "challenger vs defender"
               $fkey = ( $ch_uid < $df_uid ) ? "$ch_uid.$df_uid" : "$df_uid.$ch_uid";
               $cnt_exist_tgames = (int)@$check_tgames[$fkey];
               if ( $cnt_exist_tgames > 0 ) // games already created
                  $count_old_games += $cnt_exist_tgames;
               else // NEW
               {
                  $arr_tg = self::create_pairing_games( $trules, $tround->ID, $tier, $pool, $user_ch, $df_tpool->User,
                     $chall_col_black );
                  if ( is_array($arr_tg) )
                     $count_games += count($arr_tg);
               }

               $chall_col_black = !$chall_col_black; // alternate color

               if ( !(++$progress % 10) )
                  echo sprintf( T_('Step %s. Created %s games so far ...#tourney') . "<br>\n", $progress, $count_games );
            }
         }

         echo sprintf( T_('Created %s games for pool #%s'), ($count_games - $count_game_curr), $pool ), "</li>";
      }
      if ( $count_old_games > 0 )
         echo "<br>\n<li>", sprintf( T_('%s games already existed'), $count_old_games ), '</li>';
      echo "</ul></td></tr></table>\n";

      return array( $count_games, $count_old_games );
   }//start_games_for_specific_pools

   private static function determine_htype_alternate_start_color( $arr_users, $created_games )
   {
      $chall_col_start_black = (bool) mt_rand(0,1); // start with random start-color (per pool)

      // determine start-color from already created pool-games in previous (broken) pairing-run
      if ( count($arr_users) >= 2 )
      {
         $ch_uid = $arr_users[0];
         $df_uid = $arr_users[1];
         $chk_col = (int)@$created_games_col["$ch_uid.$df_uid"];
         if ( $chk_col )
            $chall_col_start_black = ($chk_col == BLACK);
      }

      return $chall_col_start_black;
   }//determine_htype_alternate_start_color

   /*!
    * \brief Creates tournament game (or games for DOUBLE) for specific pairing of two users for round-robin-tourneys.
    * \internal
    * \param $trules TournamentRules-object containing tourney-id tid
    * \param $tround_id TournamentRound-ID
    * \param $tier TournamentPool.Tier number
    * \param $pool TournamentPool.Pool number
    * \param $user_ch 1st user (challenger) as User-object with ID and urow->['TP_ID'] (=rid) set
    * \param $user_df 2nd user (defender) as User-object (dito as $user_ch)
    * \return array with TournamentGames-object or null on error (shouldn't happen because "exceptions" on errors).
    *
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
   private static function create_pairing_games( $trules, $tround_id, $tier, $pool, $user_ch, $user_df, $chall_col_black )
   {
      $gids = $trules->create_tournament_games( $user_ch, $user_df, $chall_col_black );
      if ( !$gids )
         return null;

      $out = array();
      foreach ( $gids as $gid ) // can be multiple games per pair (e.g. DOUBLE-tourney)
      {
         $tg = new TournamentGames( 0, $trules->tid );
         $tg->Challenger_uid = $user_ch->ID;
         $tg->Challenger_rid = $user_ch->urow['TP_ID'];
         $tg->Defender_uid   = $user_df->ID;
         $tg->Defender_rid   = $user_df->urow['TP_ID'];

         $tg->gid = $gid;
         $tg->Round_ID = $tround_id;
         $tg->Tier = $tier;
         $tg->Pool = $pool;
         $tg->setStatus( TG_STATUS_PLAY );
         $tg->StartTime = $GLOBALS['NOW'];
         $tg->insert();
         $out[] = $tg;
      }

      return $out;
   }//create_pairing_games


   /*!
    * \brief Adds new tournament-round and updates Tournament.Rounds, returning new TournamentRound-object.
    * \param $src_tround TournamentRound to take over some values from for new tournament-round;
    *       or null if nothing to seed in new entry
    * \param $set_curr_round true = sets current round to newly added round; false = no change of current round
    * \note Assumes, that there's at least one tournament-round already existing & persisted.
    * \return new TournamentRound on success; null on failure
    */
   public static function add_new_tournament_round( $tlog_type, $tourney, $src_tround, &$errors,
         $check_only, $set_curr_round=false )
   {
      $tid = $tourney->ID;
      $ttype = TournamentFactory::getTournament($tourney->WizardType);
      $t_limits = $ttype->getTournamentLimits();

      $errors = array();
      if ( $tourney->Rounds >= TROUND_MAX_COUNT ) // check for static-max T-rounds
         $errors[] = sprintf( T_('Maximum of allowed tournament rounds of %s has been reached.'), TROUND_MAX_COUNT );
      $errors = array_merge( $errors, $t_limits->check_MaxRounds( $tourney->Rounds + 1, $tourney->Rounds ) );
      if ( count($errors) || $check_only )
         return null;

      ta_begin();
      {//HOT-section to add T-round and updating T-data
         $tround = TournamentRound::add_tournament_round( $tid );
         if ( !is_null($tround) )
         {
            $tround->PoolNamesFormat = ( is_null($src_tround) )
               ? $ttype->getDefaultPoolNamesFormat()
               : $src_tround->PoolNamesFormat;
            $tround->update();

            $success = $tourney->update_rounds( 1, ($set_curr_round ? $tround->Round : 0) );
         }
         else
            $success = false;

         TournamentLogHelper::log_add_tournament_round( $tid, $tlog_type, $set_curr_round, $tround, $tourney, $success );
      }
      ta_end();

      return $tround;
   }//add_new_tournament_round


   /*! \brief Deletes tournament-round and updates Tournament.Rounds. */
   public static function remove_tournament_round( $tlog_type, $tourney, $tround, &$errors, $check_only )
   {
      if ( !$tround )
         error('invalid_args', "TRH:remove_tournament_round.check.miss.t_round({$tourney->ID})");

      $errors = array();
      if ( $tourney->CurrentRound == $tround->Round )
         $errors[] = T_('The current tournament round can not be removed.');
      if ( $tround->Status != TROUND_STATUS_INIT )
         $errors[] = sprintf( T_('Only tournament rounds on status [%s] can be removed.'),
            TournamentRound::getStatusText(TROUND_STATUS_INIT) );
      if ( $tourney->Rounds <= 1 )
         $errors[] = T_('There must be at least one tournament round.');
      if ( $tround->Round != $tourney->Rounds )
         $errors[] = sprintf( T_('You can only remove the last tournament round #%s.'), $tourney->Rounds );

      $cnt_games = TournamentGames::count_tournament_games( $tourney->ID, $tround->ID, array() );
      if ( $cnt_games > 0 )
         $errors[] = sprintf( T_('There are %s tournament games for round %s.'), $cnt_games, $tround->Round );

      if ( TournamentPool::exists_tournament_tier_pool( $tourney->ID, $tround->Round ) )
         $errors[] = sprintf( T_('There are existing tournament pools for round %s.'), $tround->Round );

      if ( count($errors) || $check_only )
         return false;

      ta_begin();
      {//HOT-section to remove existing T-round and updating T-data
         $success = TournamentRound::delete_tournament_round( $tourney->ID, $tround->Round );
         if ( $success )
            $success = $tourney->update_rounds( -1 );

         TournamentLogHelper::log_delete_tournament_round( $tid, $tlog_type, $tround, $success );
      }
      ta_end();

      return $success;
   }//remove_tournament_round


   /*! \brief Sets current tournament-round updating Tournament.CurrentRound. */
   public static function set_tournament_round( $tlog_type, $tourney, $new_round, &$errors, $check_only )
   {
      $tid = $tourney->ID;
      $tround = TournamentCache::load_cache_tournament_round( 'TRH.set_tournament_round', $tid, $tourney->CurrentRound );

      $errors = array();
      if ( $errmsg = TournamentRound::authorise_set_tround($tourney->Status) )
         $errors[] = $errmsg;
      if ( $new_round < 1 || $new_round > $tourney->Rounds )
         $errors[] = sprintf( T_('Selected tournament round must be an existing round in range %s.'),
            build_range_text(1, $tourney->Rounds) );
      if ( $tround->Round == $new_round )
         $errors[] = T_('Current tournament round is already set to selected round.');
      if ( $tround->Status != TROUND_STATUS_DONE && $new_round > $tround->Round )
         $errors[] = sprintf( T_('Current tournament round %s must be finished before switching to next.'),
            $tourney->CurrentRound );

      $cnt_missing_next_rounders = TournamentPool::count_tournament_pool_missing_next_rounders( $tid, $new_round - 1 );
      if ( $cnt_missing_next_rounders )
         $errors[] = sprintf( T_('Missing next-round-mark for %s users (with "start next round" on previous tournament round %s).'),
            $cnt_missing_next_rounders, $new_round - 1 );

      if ( count($errors) || $check_only )
         return false;

      ta_begin();
      {//HOT-section to switch T-round
         $success = $tourney->update_rounds( 0, $new_round );

         TournamentLogHelper::log_set_tournament_round( $tid, $tlog_type, $tround, $new_round, $success );
      }
      ta_end();

      return $success;
   }//set_tournament_round


   /*!
    * \brief Starts next round after current round is finished.
    *    Executed steps (can be partly done already):
    *       1. prepare next round by setting TPs next-round participation
    *       2. switch tournament-status to PAIR
    *       3. add new round + set it as current round
    * \return success: 0=failure, otherwise bitmask with success of steps: step1=1, step2=2, step3=4; should be 7 for full success
    */
   public static function start_next_tournament_round( $tlog_type, $tourney, &$errors, $check_only )
   {
      static $ARR_TSTATUS = array( TOURNEY_STATUS_PAIR, TOURNEY_STATUS_PLAY );

      $cnt_rounds = $tourney->Rounds;
      $curr_round = $tourney->CurrentRound;
      $next_round = $curr_round + 1;
      $tid = $tourney->ID;
      $tround = TournamentCache::load_cache_tournament_round( 'TRH.start_next_tournament_round', $tid, $curr_round );

      $ttype = TournamentFactory::getTournament($tourney->WizardType);
      $t_limits = $ttype->getTournamentLimits();

      $errors = array();
      if ( $cnt_rounds >= TROUND_MAX_COUNT ) // check for static-max T-rounds
         $errors[] = sprintf( T_('Maximum of allowed tournament rounds of %s has been reached.'), TROUND_MAX_COUNT );
      $errors = array_merge( $errors, $t_limits->check_MaxRounds( $next_round, $curr_round ) );
      if ( !in_array($tourney->Status, $ARR_TSTATUS) )
         $errors[] = sprintf( T_('Starting next round is only allowed on tournament status [%s].'),
            build_text_list('Tournament::getStatusText', $ARR_TSTATUS) );
      if ( $tround->Status != TROUND_STATUS_DONE )
         $errors[] = sprintf( T_('Current tournament round %s must be finished before starting new round.'),
            $curr_round );
      if ( $curr_round != $cnt_rounds )
         $errors[] = sprintf( T_('Current tournament round must be last finished round %s. Contact tournament-admin for support!'),
            $cnt_rounds );

      // need min. 2 players for next-round (participants with according StartRound and/or next-rounders from current round)
      $cnt_tp_nextround = TournamentParticipant::count_tournament_participants( $tid, null, $next_round, /*NextRnd*/true );
      $cnt_tpool_next_rounders = TournamentPool::count_tournament_pool_next_rounders( $tid, $curr_round );
      if ( $cnt_tp_nextround + $cnt_tpool_next_rounders < 2 )
         $errors[] = sprintf( T_('Need at least %s players to start next round.'), 2 );

      if ( $check_only && $curr_round == $cnt_rounds )
      {
         self::add_new_tournament_round( $tlog_type, $tourney, $tround, $errors_add_round, /*chk-only*/true );
         if ( count($errors_add_round) )
            $errors = array_unique( array_merge( $errors, $errors_add_round ) );
      }

      if ( count($errors) || $check_only )
         return false;

      ta_begin();
      {//HOT-section to start new round
         $success = 0;

         // 1. prepare next round by setting TPs next-round participation (TPOOL->TP.NextRound from curr-round)
         if ( TournamentPool::count_tournament_pool_missing_next_rounders( $tid, $curr_round ) > 0 )
         {
            TournamentPool::mark_next_round_participation( $tid, $curr_round );
            if ( TournamentPool::count_tournament_pool_missing_next_rounders( $tid, $curr_round ) == 0 ) // re-check
               $success |= 1;
         }
         else
            $success |= 1;

         // 2. switch tournament-status
         if ( $success & 1 )
         {
            if ( $tourney->Status != TOURNEY_STATUS_PAIR )
            {
               if ( $tourney->update_tournament_status( TOURNEY_STATUS_PAIR ) )
                  $success |= 2;
            }
            else
               $success |= 2;
         }

         // 3. add new round + set it as current round
         // NOTE: must be atomar operation and last step (b/c current-round changed, which is a precondition for steps1+3)
         if ( (($success & 3) == 3) && self::add_new_tournament_round( $tlog_type, $tourney, $tround, $errors, /*chk*/false, /*set-curr-rnd*/true ) )
            $success |= 4;

         TournamentLogHelper::log_start_next_tournament_round( $tid, $tlog_type, $tourney, $curr_round, $success );
      }
      ta_end();

      return $success;
   }//start_next_tournament_round


   /*!
    * \brief Fills ranks of finished pools updating TournamentPool.Rank for given tourney-round.
    * \param $tround TournamentRound-object
    * \return array of actions taken
    */
   public static function fill_ranks_tournament_pool( $tlog_type, $tround, $tourney_type )
   {
      $tid = $tround->tid;
      $round = $tround->Round;
      $result = array();

      // 1. identify pools to finish
      $arr_tgames = TournamentGames::count_tournament_games( $tid, $tround->ID, array(), /*pool-group*/true );
      $arr_finished_pools = array(); // [ tier-pool-key, ... ] = all pools that have all games finished
      foreach ( $arr_tgames as $tier_pool_key => $arr_status )
      {
         if ( !array_key_exists(TG_STATUS_PLAY, $arr_status) )
            $arr_finished_pools[] = $tier_pool_key;
      }

      $arr_pools_no_rank = TournamentPool::count_tournament_tiered_pool_users( $tid, $round, TPOOLRK_NO_RANK );
      $arr_pools_to_finish = array(); // [ tier-pool-key, ... ]
      $arr_pools_to_finish_formatted = array();
      foreach ( $arr_finished_pools as $tier_pool_key )
      {
         if ( @$arr_pools_no_rank[$tier_pool_key] ) // pool is to finish when there are entries with NO_RANK
         {
            list( $tier, $pool ) = TournamentUtils::decode_tier_pool_key( $tier_pool_key );
            $arr_pools_to_finish[] = $tier_pool_key;
            $arr_pools_to_finish_formatted[] = PoolViewer::format_tier_pool( $tourney_type, $tier, $pool, true );
         }
      }
      $count_finish = count($arr_pools_to_finish);
      $result[] = sprintf( T_('Identified %s pools to finish by setting ranks for pools: %s'),
         $count_finish, ($count_finish ? implode(', ', $arr_pools_to_finish_formatted) : NO_VALUE) );

      // 2. calculate ranks for users of identified (finished) pools
      if ( $count_finish )
      {
         $tpoints = TournamentCache::load_cache_tournament_points( 'Tournament.pool_view', $tid );

         $tpool_iterator = new ListIterator( 'TRH:fill_ranks_tournament_pool.load_pools' );
         $tpool_iterator = TournamentPool::load_tournament_pools( $tpool_iterator,
            $tid, $round, $arr_pools_to_finish, /*load-opts*/0 );
         $poolTables = new PoolTables( $tpool_iterator );

         $tg_iterator = new ListIterator( 'TRH:fill_ranks_tournament_pool.load_tgames' );
         $tg_iterator = TournamentGames::load_tournament_games( $tg_iterator, $tid, $tround->ID, $arr_pools_to_finish );
         $poolTables->fill_games( $tg_iterator, $tpoints );

         // 3. finish identified pools (update rank)
         $arr_updates = array(); // [ rank => [ TPool.ID, ... ], ... ]
         foreach ( $poolTables->users as $uid => $tpool )
         {
            if ( $tpool->Rank == TPOOLRK_NO_RANK )
               $arr_updates[$tpool->CalcRank][] = $tpool->ID;
         }

         ta_begin();
         {//HOT-section to update user-ranks for finished pools
            $count_done = 0;
            foreach ( $arr_updates as $rank => $arr_tpool_ids )
            {
               $count_done += TournamentPool::update_tournament_pool_ranks($tid, $tlog_type, 'fill_ranks',
                  $arr_tpool_ids, -$rank);
            }
         }
         ta_end();

         if ( $count_done )
         {
            $result[] = sprintf( T_('Pools finished (%s entries updated).#tourney'), $count_done );
            TournamentPool::delete_cache_tournament_pools( "TRH:fill_ranks_tournament_pool($tid,$round)", $tid, $round );
         }
      }

      return $result;
   }//fill_ranks_tournament_pool

   /*!
    * \brief Sets pool-winners for finished pools updating TournamentPool.Rank for given tourney-round.
    * \note also executes TournamentRoundHelper::fill_ranks_tournament_pool() to finish pools.
    * \param $tround TournamentRound-object
    * \return array of actions taken
    */
   public static function fill_pool_winners_tournament_pool( $tlog_type, $tround, $tourney_type )
   {
      $tid = $tround->tid;
      $round = $tround->Round;
      $result = self::fill_ranks_tournament_pool( $tlog_type, $tround, $tourney_type );

      $cnt_upd = TournamentPool::update_tournament_pool_set_pool_winners( $tround );
      $result[] = sprintf( T_('%s players set as pool winners for finished pools.'), $cnt_upd );

      if ( $cnt_upd > 0 )
         TournamentLogHelper::log_fill_tournament_pool_winners( $tid, $tlog_type, $tround, $cnt_upd );

      return $result;
   }//fill_pool_winners_tournament_pool

   /*!
    * \brief Returns error-text if games per tournament-participant exceed wizard-type limit.
    * \note No restriction for Tournament-admin.
    * \return null on success; otherwise error-msg
    */
   public static function check_tournament_participant_max_games( $tid, $t_limits, $pool_size )
   {
      $errmsg = null;

      $max_tp_games = $t_limits->getMaxLimit(TLIMITS_TRD_TP_MAX_GAMES);
      if ( $max_tp_games > 0 && !TournamentUtils::isAdmin() ) // T-Admin can set any out-of-limit value
      {
         $games_factor = TournamentHelper::determine_games_factor( $tid ); // load from T-rules
         $tp_games = $games_factor * ( $pool_size - 1 );
         if ( $tp_games > $max_tp_games )
         {
            $errmsg = sprintf( T_('The number of games [%s] per participant exceeds the wizard-type limit of [%s].#tourney'),
               $tp_games, $max_tp_games ) . "<br>\n";
            if ( $games_factor > 1 )
               $errmsg .= T_('You can either reduce the max. pool size or do not use handicap-type "double" in the tournament rules.');
            else
               $errmsg .= T_('You have to reduce the max. pool size.#tourney');
         }
      }

      return $errmsg;
   }//check_tournament_participant_max_games

   /*! \brief Creates bulletin addressed to tournament-directors only about last game finished for tournament-round. */
   public static function notify_directors_last_game_finished( $tid, $round )
   {
      $bulletin = new Bulletin( 0, /*CRON*/0, null, BULLETIN_CAT_TOURNAMENT_NEWS, BULLETIN_STATUS_SHOW,
         BULLETIN_TRG_TD, null, null, BULLETIN_FLAG_CRON_CREATED, $GLOBALS['NOW'], 0, $tid );

      $bulletin->Subject = sprintf( T_('[Notification by CRON]: Last game in round %s finished#tourney'), $round );
      $bulletin->Text = sprintf( T_('To all tournament directors of this tournament,

this is an automatic notification informing you, that all games in round %s of this tournament has been processed.
Next step for you is to finish the tournament round, and then either start a new round or end the tournament.'), $round );

      ta_begin();
      {//HOT-section to insert bulletin
         $success = $bulletin->persist();
         if ( $success )
            $success = $bulletin->update_count_players('TRH:notify_directors_last_game_finished');
      }
      ta_end();

      return $success;
   }//notify_directors_last_game_finished

} // end of 'TournamentRoundHelper'

?>
