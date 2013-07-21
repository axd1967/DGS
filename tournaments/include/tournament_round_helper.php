<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_extension.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_log_helper.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_pool_classes.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_rules.php';

 /*!
  * \file tournament_round_helper.php
  *
  * \brief General functions to support tournament management of round-robin tournaments with db-access.
  */


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

         // update TP.Finished/Won/Lost for challenger and defender
         TournamentParticipant::update_game_end_stats( $tid, $tgame->Challenger_rid, $tgame->Challenger_uid, $tgame->Score );
         TournamentParticipant::update_game_end_stats( $tid, $tgame->Defender_rid, $tgame->Defender_uid, -$tgame->Score );
      }
      ta_end();

      return true;
   }//process_tournament_round_robin_game_end

   /*! \brief Finds out games-per-challenge from various sources for given tournament. */
   public static function determine_games_per_challenge( $tid, $trule=null )
   {
      // load T-rules (need Handicaptype for games-count)
      if ( !($trule instanceof TournamentRules) )
         $trule = TournamentCache::load_cache_tournament_rules( 'TRH:determine_games_per_challenge', $tid );

      $games_per_challenge = ( $trule->Handicaptype == TRULE_HANDITYPE_DOUBLE ) ? 2 : 1;
      return $games_per_challenge;
   }//determine_games_per_challenge

   /*!
    * \brief Start all tournament games needed for current round, prints progress by printing and flushing on STDOUT.
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    * \note to make flushing work, PAGEFLAG_IMPLICIT_FLUSH is required on using page!
    *
    * \return NULL on lock-error,
    *       or error-text on severe consistency-error (which also prohibits game-pairing till it's fixed by keeping
    *             existing tournament-extension-entry),
    *       or on success: arr( number of started games, expected number of games)
    */
   public static function start_tournament_round_games( $tourney, $tround )
   {
      global $NOW;
      $tid = $tourney->ID;
      $round = $tround->Round;
      $dbgmsg = "TRH:start_tournament_round_games($tid,$round)";

      // lock T-ext
      $t_ext = new TournamentExtension( $tid, TE_PROP_TROUND_START_TGAMES, 0, $NOW );
      if ( !$t_ext->insert() ) // need to fail if existing
         return null;

      // read T-rule
      $trules = TournamentCache::load_cache_tournament_rules( $dbgmsg, $tid );
      $trules->TourneyType = $tourney->Type;
      $games_per_challenge = self::determine_games_per_challenge( $tid, $trules );

      // read T-props
      $tprops = TournamentCache::load_cache_tournament_properties( $dbgmsg, $tid );

      // read T-games: read all existing TGames to check if creation has been partly done
      $check_tgames = array(); // uid.uid => game-count
      $tg_iterator = new ListIterator( "$dbgmsg.find_tgames" );
      $tg_iterator = TournamentGames::load_tournament_games( $tg_iterator, $tid, $tround->ID );
      while ( list(,$arr_item) = $tg_iterator->getListIterator() )
      {
         list( $tgame, $orow ) = $arr_item;
         list( $uid1, $uid2 ) = $tgame->get_ordered_uids();
         $fkey = "$uid1.$uid2";
         if ( !isset($check_tgames[$fkey]) )
            $check_tgames[$fkey] = 1;
         else
            ++$check_tgames[$fkey];
      }
      unset($tg_iterator);

      // ensure, that games-per-challenge for existing games is ok (e.g. half of DOUBLE-game must be fixed by admin)
      foreach ( $check_tgames as $fkey => $cnt )
      {
         if ( $cnt != $games_per_challenge )
         {
            return sprintf( T_('Inconsistency found: for tournament #%s in round %s there is a mismatch of games per challenge (%s) for user-pair [%s].'),
               $tid, $round, "$games_per_challenge <-> $cnt", $fkey );
         }
      }

      // read all pools with all users and TPs (if needed for T-rating), need TP_ID for TG.*_rid
      $load_opts_tpool = TPOOL_LOADOPT_TP_ID | TPOOL_LOADOPT_USER | TPOOL_LOADOPT_ONLY_RATING | TPOOL_LOADOPT_UROW_RATING;
      if ( $tprops->RatingUseMode != TPROP_RUMODE_CURR_FIX )
         $load_opts_tpool |= TPOOL_LOADOPT_TRATING;
      $tpool_iterator = new ListIterator( "$dbgmsg.load_pools" );
      $tpool_iterator->addIndex( 'uid' );
      $tpool_iterator = TournamentPool::load_tournament_pools( $tpool_iterator, $tid, $round, 0, $load_opts_tpool );

      $poolTables = new PoolTables( $tround->Pools );
      $poolTables->fill_pools( $tpool_iterator );
      $arr_poolusers = $poolTables->get_pool_users();
      $expected_games = $poolTables->calc_pool_games_count( $games_per_challenge );

      // loop over all pools
      echo "<table id=\"Progress\"><tr><td><ul>\n";
      $count_games = $count_old_games = $progress = 0;
      $cnt_pools = count($arr_poolusers);
      foreach ( $arr_poolusers as $pool => $arr_users )
      {
         if ( $pool == 0 )
         {
            return sprintf( T_('Inconsistency found: there are %s unassigned pool-users for tournament #%s in round %s.'),
               count($arr_users), $tid, $round );
         }

         echo "<br>\n<li>", sprintf( T_('Pool %s of %s'), $pool, $cnt_pools ), ":<br>\n";

         $count_game_curr = $count_games;
         while ( count($arr_users) )
         {
            $ch_uid = array_shift( $arr_users ); // challenger
            $ch_tpool = $poolTables->get_user_tournament_pool( $ch_uid );
            $user_ch = $ch_tpool->User;

            // start game with all remaining opponent-users (as defender)
            foreach ( $arr_users as $df_uid )
            {
               $df_tpool = $poolTables->get_user_tournament_pool( $df_uid );

               // check if TGames already exists for pair challenger vs defender
               $fkey = ( $ch_uid < $df_uid ) ? "$ch_uid.$df_uid" : "$df_uid.$ch_uid";
               $cnt_exist_tgames = (int)@$check_tgames[$fkey];
               if ( $cnt_exist_tgames > 0 ) // games already created
                  $count_old_games += $cnt_exist_tgames;
               else // NEW
               {
                  $arr_tg = self::create_pairing_games( $trules, $tround->ID, $pool, $user_ch, $df_tpool->User );
                  if ( is_array($arr_tg) )
                     $count_games += count($arr_tg);
               }

               if ( !(++$progress % 25) )
                  echo sprintf( T_('Created %s games so far ...') . "<br>\n", $count_games );
            }
         }

         echo sprintf( T_('Created %s games for pool #%s'), ($count_games - $count_game_curr), $pool ), "</li>";
      }
      if ( $count_old_games > 0 )
         echo "<br>\n<li>", sprintf( T_('%s games already existed'), $count_old_games ), '</li>';
      echo "</ul></td></tr></table>\n";

      // clear cache
      TournamentGames::delete_cache_tournament_games( "TRH:start_tournament_round_games($tid,$round)", $tid );

      // check expected games-count
      $count_games += $count_old_games;
      if ( $count_games == $expected_games )
      {
         // switch T-round-status PAIR -> PLAY
         $tround->setStatus( TROUND_STATUS_PLAY );
         $tround->update();
      }

      // unlock T-ext
      $t_ext->delete();

      return array( $count_games, $expected_games );
   }//start_tournament_round_games

   /*!
    * \brief Creates tournament game (or games for DOUBLE) for specific pairing of two users for round-robin-tourneys.
    * \internal
    * \param $trules TournamentRules-object containing tourney-id tid
    * \param $tround_id TournamentRound-ID
    * \param $pool TournamentPool.Pool number
    * \param $user_ch 1st user (challenger) as User-object with ID and urow->['TP_ID'] (=rid) set
    * \param $user_df 2nd user (defender) as User-object (dito as $user_ch)
    * \return array with TournamentGames-object or null on error (shouldn't happen because "exceptions" on errors).
    *
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
   private static function create_pairing_games( $trules, $tround_id, $pool, $user_ch, $user_df )
   {
      $gids = $trules->create_tournament_games( $user_ch, $user_df );
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
         $tg->Pool = $pool;
         $tg->setStatus( TG_STATUS_PLAY );
         $tg->StartTime = $GLOBALS['NOW'];
         $tg->insert();
         $out[] = $tg;
      }

      return $out;
   }//create_pairing_games


   /*! \brief Adds new tournament-round and updates Tournament.Rounds, returning new TournamentRound-object. */
   public static function add_new_tournament_round( $tourney, &$errors, $check_only )
   {
      $errors = array();
      $ttype = TournamentFactory::getTournament($tourney->WizardType);
      $t_limits = $ttype->getTournamentLimits();

      if ( $tourney->Rounds >= TROUND_MAX_COUNT ) // check for static-max T-rounds
         $errors[] = sprintf( T_('Maximum of allowed tournament rounds of %s has been reached.'), TROUND_MAX_COUNT );
      $errors = array_merge( $errors, $t_limits->check_MaxRounds( $tourney->Rounds + 1, $tourney->Rounds ) );
      if ( count($errors) || $check_only )
         return null;

      ta_begin();
      {//HOT-section to add T-round and updating T-data
         $tround = TournamentRound::add_tournament_round( $tourney->ID );
         if ( !is_null($tround) )
            $success = $tourney->update_rounds( 1 );
      }
      ta_end();

      return $tround;
   }//add_new_tournament_round


   /*! \brief Deletes tournament-round and updates Tournament.Rounds. */
   public static function remove_tournament_round( $tourney, $tround, &$errors, $check_only )
   {
      if ( !$tround )
         error('invalid_args', "TournamentRoundHelper:remove_tournament_round.check.miss.t_round({$tourney->ID})");

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

      $tp_count = TournamentParticipant::count_tournament_participants(
         $tourney->ID, /*all-stat*/null, $tround->Round, /*NextR*/true );
      if ( $tp_count > 0 )
         $errors[] = sprintf( T_('There are %s tournament participants registered to play in round %s.'),
            $tp_count, $tround->Round );

      if ( count($errors) || $check_only )
         return false;

      ta_begin();
      {//HOT-section to remove existing T-round and updating T-data
         $success = TournamentRound::delete_tournament_round( $tourney->ID, $tround->Round );
         if ( $success )
            $success = $tourney->update_rounds( -1 );
      }
      ta_end();

      return $success;
   }//remove_tournament_round


   /*! \brief Sets current tournament-round updating Tournament.CurrentRound. */
   public static function set_tournament_round( $tourney, $new_round, &$errors, $check_only )
   {
      $tround = TournamentCache::load_cache_tournament_round( 'TRH.set_tournament_round',
         $tourney->ID, $tourney->CurrentRound );

      $errors = array();
      if ( $errmsg = TournamentRound::authorise_set_tround($tourney->Status) )
         $errors[] = $errmsg;
      if ( $new_round < 1 || $new_round > $tourney->Rounds )
         $errors[] = sprintf( T_('Selected tournament round must be an existing round in range %s.'),
            build_range_text(1, $tourney->Rounds) );
      if ( $tround->Round == $new_round )
         $errors[] = T_('Current tournament round is already set to selected round.');
      if ( $tround->Status != TROUND_STATUS_DONE )
         $errors[] = sprintf( T_('Current tournament round %s must be finished before switching to next.'),
            $tourney->CurrentRound );
      if ( $tround->Status == TROUND_STATUS_DONE && $new_round != $tround->Round + 1 )
         $errors[] = sprintf( T_('You are only allowed to switch to the next tournament round %s.'),
            $tround->Round + 1 );

      //TODO TODO check if T-round finalized (copied TPOOL->TP.NextRnd)

      //TODO TODO set-T-rnd: automatically check + do switch T-status back to PLAY->PAIR !? YES ... if so, have to do the same checks as in REG->PAIR T-status-change !? ;; perhaps better to put this into separate use-case "switch to next-round" => check if TStatus->check_status_change() can be used here (may need CurrRound already changed)

      if ( count($errors) || $check_only )
         return false;

      ta_begin();
      {//HOT-section to switch T-round
         $success = $tourney->update_rounds( 0, $new_round );
      }
      ta_end();

      return $success;
   }//set_tournament_round


   /*!
    * \brief Fills ranks of finished pools updating TournamentPool.Rank for given tourney-round.
    * \param $tround TournamentRound-object
    * \return array of actions taken
    */
   public static function fill_ranks_tournament_pool( $tround )
   {
      $tid = $tround->tid;
      $round = $tround->Round;
      $arr_pools_to_finish = array(); // [ pool, ... ]
      $result = array();

      // 1. identify pools to finish
      $arr_tgames = TournamentGames::count_tournament_games( $tid, $tround->ID, array(), /*pool-group*/true );
      $arr_finished_pools = array(); // [ pool, ... ] = all pool that have all games finished
      foreach ( $arr_tgames as $pool => $arr_status )
      {
         if ( !array_key_exists(TG_STATUS_PLAY, $arr_status) )
            $arr_finished_pools[] = $pool;
      }

      $arr_pools_no_rank = TournamentPool::count_tournament_pool_users( $tid, $round, TPOOLRK_NO_RANK );
      foreach ( $arr_finished_pools as $pool )
      {
         if ( @$arr_pools_no_rank[$pool] ) // pool is to finish when there are entries with NO_RANK
            $arr_pools_to_finish[] = $pool;
      }
      $count_finish = count($arr_pools_to_finish);
      $result[] = sprintf( T_('Identified %s pools to finish by setting ranks for pools: %s'),
         $count_finish, ($count_finish ? implode(', ', $arr_pools_to_finish) : NO_VALUE) );

      // 2. calculate ranks for users of identified (finished) pools
      if ( $count_finish )
      {
         $tpool_iterator = new ListIterator( 'TRH:fill_ranks_tournament_pool.load_pools' );
         $tpool_iterator = TournamentPool::load_tournament_pools( $tpool_iterator, $tid, $round,
            $arr_pools_to_finish, /*load-opts*/0 );
         $poolTables = new PoolTables( $tround->Pools );
         $poolTables->fill_pools( $tpool_iterator );

         $tg_iterator = new ListIterator( 'TRH:fill_ranks_tournament_pool.load_tgames' );
         $tg_iterator = TournamentGames::load_tournament_games( $tg_iterator, $tid, $tround->ID,
            $arr_pools_to_finish, /*all-stati*/null );
         $poolTables->fill_games( $tg_iterator );

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
            foreach ( $arr_updates as $rank => $arr_tpools )
            {
               if ( TournamentPool::update_tournament_pool_ranks($arr_tpools, -$rank) )
                  $count_done++;
            }
         }
         ta_end();

         if ( $count_done )
            $result[] = T_('Pools finished.');
      }

      return $result;
   }//fill_ranks_tournament_pool

   /*!
    * \brief Sets pool-winners for finished pools updating TournamentPool.Rank for given tourney-round.
    * \note also executes TournamentRoundHelper::fill_ranks_tournament_pool() to finish pools.
    * \param $tround TournamentRound-object
    * \return array of actions taken
    */
   public static function fill_pool_winners_tournament_pool( $tround )
   {
      $tid = $tround->tid;
      $round = $tround->Round;
      $arr_pools_to_finish = array(); // [ pool, ... ]
      $result = self::fill_ranks_tournament_pool( $tround );

      $cnt_upd = TournamentPool::update_tournament_pool_set_pool_winners( $tround );
      $result[] = sprintf( T_('%s players set as pool winners for finished pools.'), $cnt_upd );

      return $result;
   }//fill_pool_winners_tournament_pool

} // end of 'TournamentRoundHelper'

?>
