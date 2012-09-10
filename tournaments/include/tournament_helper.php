<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/connect2mysql.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_director.php';
require_once 'tournaments/include/tournament_extension.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_ladder.php';
require_once 'tournaments/include/tournament_ladder_props.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_pool_classes.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_result.php';
require_once 'tournaments/include/tournament_rules.php';
require_once 'tournaments/include/tournament_utils.php';

 /*!
  * \file tournament_helper.php
  *
  * \brief General functions to support tournament management with db-access.
  */


 /*!
  * \class TournamentHelper
  *
  * \brief Helper-class with mostly static functions to support Tournament management
  *        with db-access combining forces of different tournament-classes.
  */
class TournamentHelper
{
   var $tcache;

   function TournamentHelper()
   {
      $this->tcache = new TournamentCache();
   }

   function process_tournament_game_end( $tourney, $tgame, $check_only )
   {
      $tid = $tourney->ID;
      if( $tourney->Type != TOURNEY_TYPE_LADDER && $tourney->Type != TOURNEY_TYPE_ROUND_ROBIN )
         error('invalid_args', "TournamentHelper.process_tournament_game_end($tid,{$tourney->Type},{$tgame->ID})");

      // check if processing needed
      if( $tourney->Status != TOURNEY_STATUS_PLAY ) // process only PLAY-status
         return false;
      if( $check_only )
         return true;

      if( $tourney->Type == TOURNEY_TYPE_LADDER )
         return $this->process_tournament_ladder_game_end( $tourney, $tgame );
      elseif( $tourney->Type == TOURNEY_TYPE_ROUND_ROBIN )
         return $this->process_tournament_round_robin_game_end( $tourney, $tgame );
      else
         return false;
   }

   function process_tournament_ladder_game_end( $tourney, $tgame )
   {
      $tid = $tourney->ID;
      $tl_props = $this->tcache->load_tournament_ladder_props( 'process_tournament_ladder_game_end', $tid);
      if( is_null($tl_props) )
         return false;

      // process game-end
      $game_end_action = $tl_props->calc_game_end_action( $tgame->Score, $tgame->Flags );

      ta_begin();
      {//HOT-section to process tournament-game-end
         $success = TournamentLadder::process_game_end( $tid, $tgame, $game_end_action );
         if( $success )
         {
            // decrease TG.ChallengesIn for defender
            $tladder_df = new TournamentLadder( $tid, $tgame->Defender_rid, $tgame->Defender_uid );
            $tladder_df->update_incoming_challenges( -1 );

            // decrease TG.ChallengesOut for challenger
            $tladder_ch = new TournamentLadder( $tid, $tgame->Challenger_rid, $tgame->Challenger_uid );
            $tladder_ch->update_outgoing_challenges( -1 );

            // tournament-game done
            if( $tl_props->ChallengeRematchWaitHours > 0 )
            {
               $tgame->setStatus(TG_STATUS_WAIT);
               $tgame->TicksDue = $tl_props->calc_ticks_due_rematch_wait( $this->tcache );
            }
            else
               $tgame->setStatus(TG_STATUS_DONE);
            $tgame->update();

            // update TP.Finished/Won/Lost for challenger and defender
            TournamentParticipant::update_game_end_stats( $tid, $tgame->Challenger_rid, $tgame->Score );
            TournamentParticipant::update_game_end_stats( $tid, $tgame->Defender_rid, -$tgame->Score );
         }
      }
      ta_end();

      return $success;
   }//process_tournament_ladder_game_end

   function process_tournament_round_robin_game_end( $tourney, $tgame )
   {
      $tid = $tourney->ID;

      ta_begin();
      {//HOT-section to process tournament-game-end
         $tgame->setStatus(TG_STATUS_DONE);
         $tgame->update();

         // update TP.Finished/Won/Lost for challenger and defender
         TournamentParticipant::update_game_end_stats( $tid, $tgame->Challenger_rid, $tgame->Score );
         TournamentParticipant::update_game_end_stats( $tid, $tgame->Defender_rid, -$tgame->Score );
      }
      ta_end();

      return true;
   }

   /*!
    * \brief Updates TournamentLadder.Period/History-Rank when rank-update is due, set next update-date.
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
   function process_rank_period( $t_ext )
   {
      $tid = $t_ext->tid;
      $tl_props = $this->tcache->load_tournament_ladder_props( 'process_rank_period', $tid);
      if( is_null($tl_props) )
         return false;

      // set next check date at month-start (min-period = 1 month)
      $t_ext->DateValue = TournamentUtils::get_month_start_time( $GLOBALS['NOW'], $tl_props->RankPeriodLength );
      $success = $t_ext->update();

      if( $success )
         $success = TournamentLadder::process_rank_period( $tid );

      return $success;
   }


   // ------------ static functions ----------------------------

   /*!
    * \brief Wrapper to TournamentRules.create_tournament_games() creating game(s) between two users.
    * \return array of Games.ID (e.g. for DOUBLE-tourney)
    */
   function create_games_from_tournament_rules( $tid, $tourney_type, $user_ch, $user_df )
   {
      $trules = TournamentRules::load_tournament_rule( $tid );
      if( is_null($trules) )
         error('bad_tournament', "TournamentHelper::create_game_from_tournament_rules.find_trules($tid)");
      $trules->TourneyType = $tourney_type;

      $tprops = TournamentProperties::load_tournament_properties( $tid );
      if( is_null($tprops) )
         error('bad_tournament', "TournamentHelper::create_game_from_tournament_rules.find_tprops($tid)");

      // set challenger & defender rating according to rating-use-mode
      $ch_uid = $user_ch->ID;
      $ch_rating = TournamentHelper::get_tournament_rating( $tid, $user_ch, $tprops->RatingUseMode );
      $user_ch->urow['Rating2'] = $ch_rating;

      $df_uid = $user_df->ID;
      $df_rating = TournamentHelper::get_tournament_rating( $tid, $user_df, $tprops->RatingUseMode );
      $user_df->urow['Rating2'] = $df_rating;

      $gids = $trules->create_tournament_games( $user_ch, $user_df );
      return $gids;
   }

   /*!
    * \brief Start all tournament games needed for current round, prints progress by printing and flushing on STDOUT.
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    *
    * \return arr( number of started games, expected number of games) or NULL on lock-error.
    */
   function start_tournament_round_games( $tourney, $tround )
   {
      global $NOW;
      $tid = $tourney->ID;
      $round = $tround->Round;

      // lock T-ext
      $t_ext = new TournamentExtension( $tid, TE_PROP_TROUND_START_TGAMES, 0, $NOW );
      if( !$t_ext->insert() ) // need to fail if existing
         return null;

      // read T-rule
      $trules = TournamentRules::load_tournament_rule( $tid );
      if( is_null($trules) )
         error('bad_tournament', "TournamentHelper::start_tournament_round_games.find_trules($tid)");
      $trules->TourneyType = $tourney->Type;
      $games_per_challenge = TournamentHelper::determine_games_per_challenge( $tid, $trules );

      // read T-props
      $tprops = TournamentProperties::load_tournament_properties( $tid );
      if( is_null($tprops) )
         error('bad_tournament', "TournamentHelper::start_tournament_round_games.find_tprops($tid)");

      // read T-games: read all existing TGames to check if creation has been partly done
      $check_tgames = array(); // uid.uid => game-count
      $tg_iterator = new ListIterator( "TournamentHelper::start_tournament_round_games.find_tgames($tid,$round)" );
      $tg_iterator = TournamentGames::load_tournament_games( $tg_iterator, $tid, $tround->ID );
      while( list(,$arr_item) = $tg_iterator->getListIterator() )
      {
         list( $tgame, $orow ) = $arr_item;
         list( $uid1, $uid2 ) = $tgame->get_ordered_uids();
         $fkey = "$uid1.$uid2";
         if( !isset($check_tgames[$fkey]) )
            $check_tgames[$fkey] = 1;
         else
            ++$check_tgames[$fkey];
      }
      unset($tg_iterator);

      // ensure, that games-per-challenge for existing games is ok (e.g. half of DOUBLE-game must be fixed by admin)
      foreach( $check_tgames as $fkey => $cnt )
      {
         if( $cnt != $games_per_challenge )
            error('bad_tournament', "TournamentHelper::start_tournament_round_games.check_gper_chall($tid,[$fkey])");
      }

      // read all pools with all users and TPs (if needed for T-rating), need TP_ID for TG.*_rid
      $load_opts_tpool = TPOOL_LOADOPT_TP_ID | TPOOL_LOADOPT_USER | TPOOL_LOADOPT_ONLY_RATING | TPOOL_LOADOPT_UROW_RATING;
      if( $tprops->RatingUseMode != TPROP_RUMODE_CURR_FIX )
         $load_opts_tpool |= TPOOL_LOADOPT_TRATING;
      $tpool_iterator = new ListIterator( "TournamentHelper::start_tournament_round_games.load_pools($tid,$round)" );
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
      foreach( $arr_poolusers as $pool => $arr_users )
      {
         echo_message( "<br>\n<li>" . sprintf( T_('Pool %s of %s'), $pool, $cnt_pools ) . ":<br>\n" );

         $count_game_curr = $count_games;
         while( count($arr_users) )
         {
            $ch_uid = array_shift( $arr_users ); // challenger
            $ch_tpool = $poolTables->get_user_tournament_pool( $ch_uid );
            $user_ch = $ch_tpool->User;

            // start game with all remaining opponent-users (as defender)
            foreach( $arr_users as $df_uid )
            {
               $df_tpool = $poolTables->get_user_tournament_pool( $df_uid );

               // check if TGames already exists for pair challenger vs defender
               $fkey = ( $ch_uid < $df_uid ) ? "$ch_uid.$df_uid" : "$df_uid.$ch_uid";
               $cnt_exist_tgames = (int)@$check_tgames[$fkey];
               if( $cnt_exist_tgames > 0 ) // games already created
                  $count_old_games += $cnt_exist_tgames;
               else // NEW
               {
                  $arr_tg = TournamentHelper::create_pairing_games( $trules, $tround->ID, $pool, $user_ch, $df_tpool->User );
                  if( is_array($arr_tg) )
                     $count_games += count($arr_tg);
               }

               if( !(++$progress % 25) )
                  echo_message( sprintf( T_('Created %s games so far ...') . "<br>\n", $count_games ));
            }
         }

         echo_message( sprintf( T_('Created %s games for pool #%s') . "</li>",
            ($count_games - $count_game_curr), $pool ));
      }
      if( $count_old_games > 0 )
         echo_message( "<br>\n<li>" . sprintf( T_('%s games already existed') . '</li>', $count_old_games ));
      echo_message("</ul></td></tr></table>\n");

      // check expected games-count
      $count_games += $count_old_games;
      if( $count_games == $expected_games )
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
    * \param $trules TournamentRules-object containing tourney-id tid
    * \param $tround_id TournamentRound-ID
    * \param $pool TournamentPool.Pool number
    * \param $user_ch 1st user (challenger) as User-object with ID and urow->['TP_ID'] (=rid) set
    * \param $user_df 2nd user (defender) as User-object (dito as $user_ch)
    * \return array with TournamentGames-object or null on error (shouldn't happen because "exceptions" on errors).
    *
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
   function create_pairing_games( $trules, $tround_id, $pool, $user_ch, $user_df )
   {
      $gids = $trules->create_tournament_games( $user_ch, $user_df );
      if( !$gids )
         return null;

      $out = array();
      foreach( $gids as $gid ) // can be multiple games per pair (e.g. DOUBLE-tourney)
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
   }

   /*!
    * \brief Returns rating for user from Players/TournamentParticipant-table according to rating-use-mode.
    * \param $user User-object
    * \param $RatingUseMode TournamentProperties.RatingUseMode
    */
   function get_tournament_rating( $tid, $user, $RatingUseMode )
   {
      if( $RatingUseMode == TPROP_RUMODE_CURR_FIX )
         $rating = $user->Rating;
      else //if( $RatingUseMode == TPROP_RUMODE_COPY_CUSTOM || $RatingUseMode == TPROP_RUMODE_COPY_FIX )
      {
         $tp = TournamentParticipant::load_tournament_participant( $tid, $user->ID, 0, false, false );
         if( is_null($tp) )
            error('tournament_participant_unknown',
                  "TournamentHelper::get_tournament_rating($tid,{$user->ID},$RatingUseMode)");
         $rating = $tp->Rating;
      }

      return $rating;
   }

   /*! \brief Finds out games-per-challenge from various sources for given tournament. */
   function determine_games_per_challenge( $tid, $trule=null )
   {
      if( !($trule instanceof TournamentRules) )
      {
         // load T-rules (need HandicapType for games-count)
         $trule = TournamentRules::load_tournament_rule( $tid );
         if( is_null($trule) )
            error('bad_tournament', "TournamentHelper::determine_games_per_challenge.find_trules($tid)");
      }

      $games_per_challenge = ( $trule->Handicaptype == TRULE_HANDITYPE_DOUBLE ) ? 2 : 1;

      return $games_per_challenge;
   }

   function load_ladder_absent_users( $iterator=null )
   {
      $qsql = new QuerySQL(
         SQLP_FIELDS,
            'TL.tid', 'TL.rid', 'TL.uid',
            'TLP.UserAbsenceDays AS TLP_UserAbsenceDays',
         SQLP_FROM,
            'Tournament AS T',
            'INNER JOIN TournamentLadderProps AS TLP ON TLP.tid=T.ID',
            'INNER JOIN TournamentLadder AS TL ON TL.tid=T.ID',
            'INNER JOIN Players AS P ON P.ID=TL.uid',
         SQLP_WHERE,
            'TLP.UserAbsenceDays > 0',
            "T.Status='".TOURNEY_STATUS_PLAY."'",
            'P.Lastaccess < NOW() - INTERVAL (TLP.UserAbsenceDays + CEIL(P.OnVacation)) DAY',
            'P.LastQuickAccess < NOW() - INTERVAL (TLP.UserAbsenceDays + CEIL(P.OnVacation)) DAY',
         SQLP_ORDER,
            'TL.tid ASC'
         );

      if( is_null($iterator) )
         $iterator = new ListIterator( 'TournamentHelper::load_ladder_absent_users' );
      $iterator->addQuerySQLMerge( $qsql );
      return TournamentLadder::load_tournament_ladder( $iterator );
   }

   function load_ladder_rank_period_update( $iterator = null )
   {
      global $NOW;

      $qsql = new QuerySQL(
         SQLP_FROM,
            'INNER JOIN Tournament AS T ON T.ID=TE.tid',
         SQLP_WHERE,
            "T.Status='".TOURNEY_STATUS_PLAY."'",
            "TE.Property=".TE_PROP_TLADDER_RANK_PERIOD_UPDATE,
            "TE.DateValue <= FROM_UNIXTIME($NOW)",
         SQLP_ORDER,
            'TE.tid ASC'
         );

      if( is_null($iterator) )
         $iterator = new ListIterator( 'TournamentHelper::load_ladder_rank_period_update' );
      $iterator->addQuerySQLMerge( $qsql );
      return TournamentExtension::load_tournament_extensions( $iterator );
   }

   /*! \brief Adds new tournament-round and updates Tournament.Rounds, returning new TournamentRound-object. */
   function add_new_tournament_round( $tourney, &$errors, $check_only )
   {
      $errors = array();
      $ttype = TournamentFactory::getTournament($tourney->WizardType);
      $t_limits = $ttype->getTournamentLimits();

      if( $tourney->Rounds >= TROUND_MAX_COUNT ) // check for static-max T-rounds
         $errors[] = sprintf( T_('Maximum of allowed tournament rounds of %s has been reached.'), TROUND_MAX_COUNT );
      $errors = array_merge( $errors, $t_limits->check_MaxRounds( $tourney->Rounds + 1, $tourney->Rounds ) );
      if( count($errors) || $check_only )
         return null;

      ta_begin();
      {//HOT-section to add T-round and updating T-data
         $tround = TournamentRound::add_tournament_round( $tourney->ID );
         if( !is_null($tround) )
            $success = $tourney->update_rounds( 1 );
      }
      ta_end();

      return $tround;
   }

   /*! \brief Deletes tournament-round and updates Tournament.Rounds. */
   function delete_tournament_round( $tourney, $tround, &$errors, $check_only )
   {
      $errors = array();
      if( $tourney->CurrentRound == $tround->Round )
         $errors[] = T_('The current tournament round can not be removed.');
      if( $tround->Status != TROUND_STATUS_INIT )
         $errors[] = sprintf( T_('Only tournament rounds on status [%s] can be removed.'),
            TournamentRound::getStatusText(TROUND_STATUS_INIT) );
      if( $tourney->Rounds <= 1 )
         $errors[] = T_('There must be at least one tournament-round.');
      if( $tround->Round != $tourney->Rounds )
         $errors[] = sprintf( T_('You can only remove the last tournament round #%s.'), $tourney->Rounds );
      if( count($errors) || $check_only )
         return false;

      ta_begin();
      {//HOT-section to remove existing T-round and updating T-data
         $success = TournamentRound::delete_tournament_round( $tourney->ID, $tround->Round );
         if( $success )
            $success = $tourney->update_rounds( -1 );
      }
      ta_end();

      return $success;
   }

   /*! \brief Sets current tournament-round updating Tournament.CurrentRound. */
   function set_tournament_round( $tourney, $new_round, &$errors, $check_only )
   {
      $tround = TournamentRound::load_tournament_round( $tourney->ID, $tourney->CurrentRound );
      if( is_null($tround) )
         error('bad_tournament', "TournamentHelper.set_tournament_round.find_tround({$tourney->ID},{$tourney->CurrentRound})");

      $errors = array();
      if( !TournamentRound::authorise_set_tround($tourney->Status) )
         $errors[] = sprintf( T_('Setting current tournament round is only allowed on tournament status %s.'),
            Tournament::getStatusText(TOURNEY_STATUS_PAIR) );
      if( $new_round < 1 || $new_round > $tourney->Rounds )
         $errors[] = sprintf( T_('Selected tournament round must be an existing round in range %s.'),
            build_range_text(1, $tourney->Rounds) );
      if( $tround->Round == $new_round )
         $errors[] = T_('Current tournament round is already set to selected round.');
      if( $tround->Status != TROUND_STATUS_DONE )
         $errors[] = sprintf( T_('Current tournament round %s must be finished before switching to next.'),
            $tourney->CurrentRound );
      if( $tround->Status == TROUND_STATUS_DONE && $new_round != $tround->Round + 1 )
         $errors[] = sprintf( T_('You are only allowed to switch to the next tournament round %s.'),
            $tround->Round + 1 );
      if( count($errors) || $check_only )
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
   function fill_ranks_tournament_pool( $tround )
   {
      $tid = $tround->tid;
      $round = $tround->Round;
      $arr_pools_to_finish = array(); // [ pool, ... ]
      $result = array();

      // 1. identify pools to finish
      $arr_tgames = TournamentGames::count_tournament_games( $tid, $tround->ID, array(), /*pool-group*/true );
      $arr_finished_pools = array(); // [ pool, ... ] = all pool that have all games finished
      foreach( $arr_tgames as $pool => $arr_status )
      {
         if( !array_key_exists(TG_STATUS_PLAY, $arr_status) )
            $arr_finished_pools[] = $pool;
      }

      $arr_pools_no_rank = TournamentPool::count_tournament_pool_users( $tid, $round, TPOOLRK_NO_RANK );
      foreach( $arr_finished_pools as $pool )
      {
         if( @$arr_pools_no_rank[$pool] ) // pool is to finish when there are entries with NO_RANK
            $arr_pools_to_finish[] = $pool;
      }
      $count_finish = count($arr_pools_to_finish);
      $result[] = sprintf( T_('Identified %s pools to finish by setting ranks for pools: %s'),
         $count_finish, ($count_finish ? implode(', ', $arr_pools_to_finish) : NO_VALUE) );

      // 2. calculate ranks for users of identified (finished) pools
      if( $count_finish )
      {
         $tpool_iterator = new ListIterator( 'Tournament.edit_ranks.load_pools' );
         $tpool_iterator = TournamentPool::load_tournament_pools( $tpool_iterator, $tid, $round,
            $arr_pools_to_finish, /*load-opts*/0 );
         $poolTables = new PoolTables( $tround->Pools );
         $poolTables->fill_pools( $tpool_iterator );

         $tg_iterator = new ListIterator( 'Tournament.edit_ranks.load_tgames' );
         $tg_iterator = TournamentGames::load_tournament_games( $tg_iterator, $tid, $tround->ID,
            $arr_pools_to_finish, /*all-stati*/null );
         $poolTables->fill_games( $tg_iterator );

         // 3. finish identified pools (update rank)
         $arr_updates = array(); // [ rank => [ TPool.ID, ... ], ... ]
         foreach( $poolTables->users as $uid => $tpool )
         {
            if( $tpool->Rank == TPOOLRK_NO_RANK )
               $arr_updates[$tpool->CalcRank][] = $tpool->ID;
         }

         ta_begin();
         {//HOT-section to update user-ranks for finished pools
            $count_done = 0;
            foreach( $arr_updates as $rank => $arr_tpools )
            {
               if( TournamentPool::update_tournament_pool_ranks($arr_tpools, -$rank) )
                  $count_done++;
            }
         }
         ta_end();

         if( $count_done )
            $result[] = T_('Pools finished.');
      }

      return $result;
   }//fill_ranks_tournament_pool


   /*!
    * \brief Checks if a king is to be crowned for a ladder-tournament.
    * \return ListIterator with data to crown ladder-king.
    * \see #process_tournament_ladder_crown_king()
    */
   function load_ladder_crown_kings( $iterator=null )
   {
      global $NOW;
      $qsql = new QuerySQL(
         SQLP_FIELDS,
            'T.ID AS tid',
            'TL.uid',
            'TL.rid',
            'TL.Rank',
            'UNIX_TIMESTAMP(TL.RankChanged) AS X_RankChanged',
            'TLP.CrownKingHours',
            'P.Rating2',
            'T.Owner_ID AS owner_uid',
         SQLP_FROM,
            'Tournament AS T',
            'INNER JOIN TournamentLadderProps AS TLP ON TLP.tid=T.ID',
            'INNER JOIN TournamentLadder AS TL ON TL.tid=T.ID',
            'INNER JOIN Players AS P ON P.ID=TL.uid',
         SQLP_WHERE,
            "T.Status='".TOURNEY_STATUS_PLAY."'",
            "T.Type='".TOURNEY_TYPE_LADDER."'",
            'TLP.CrownKingHours > 0',
            "TLP.CrownKingStart <= FROM_UNIXTIME($NOW)",
            'TL.Rank=1',
            'TL.RankChanged > 0',
            "TL.RankChanged < FROM_UNIXTIME($NOW) - INTERVAL TLP.CrownKingHours HOUR"
         );

      if( is_null($iterator) )
         $iterator = new ListIterator( 'TournamentHelper::load_ladder_crown_kings' );
      $iterator->addQuerySQLMerge( $qsql );
      $result = db_query( "TournamentHelper::load_ladder_crown_kings", $iterator->buildQuery() );
      $iterator->setResultRows( mysql_num_rows($result) );

      while( $row = mysql_fetch_array($result) )
         $iterator->addItem( null, $row );
      mysql_free_result($result);

      return $iterator;
   }//load_ladder_crown_kings

   /*!
    * \brief Crowns King with information given in $row.
    * \param $row map with fields: tid, uid, rid, Rank, X_RankChanged, CrownKingHours, Rating2, owner_uid
    * \see #load_ladder_crown_kings()
    */
   function process_tournament_ladder_crown_king( $row, $by_tdir_uid=0 )
   {
      global $NOW;

      $tid = (int)$row['tid'];
      if( !is_numeric($tid) || $tid <= 0 )
         error('invalid_args', "TournamentHelper::process_tournament_ladder_crown_king.check.tid($tid)");
      if( !is_numeric($by_tdir_uid) || $by_tdir_uid < 0 )
         error('invalid_args', "TournamentHelper::process_tournament_ladder_crown_king.check.tdir_uid($tid,$by_tdir_uid)");

      $rank_kept_hours = (int)( ($NOW - $row['X_RankChanged']) / SECS_PER_HOUR);
      $tresult = new TournamentResult( 0, $tid, $row['uid'], $row['rid'], $row['Rating2'],
         TRESULTTYPE_KING_OF_THE_HILL, /*start*/$row['X_RankChanged'], /*end*/$NOW,
         /*round*/1, $row['Rank'], $rank_kept_hours );

      $nfy_uids = TournamentDirector::load_tournament_directors_uid( $tid );
      $nfy_uids[] = $row['owner_uid'];

      // build message-text for TD/owner notify
      $msg_text = '';
      if( $by_tdir_uid > 0 )
         $ufmt = ( ($by_tdir_uid == $row['owner_uid']) ? T_('Owner#tourney') : T_('Tournament director#tourney') )
            . " <user $by_tdir_uid>";
      else
         $ufmt = 'CRON';
      $msg_text .=
         sprintf( T_('Tournament result changed by %s.'), $ufmt ) .
         "\n\n" .
         sprintf( T_("For %s user [ %s ] has kept the rank #%s for [%s], so the user is crowned as \"King of the Hill\"."),
                  "<tourney $tid>",
                  "<user {$row['uid']}>",
                  $row['Rank'],
                  TimeFormat::echo_time_diff( $NOW, $row['X_RankChanged'], 24, TIMEFMT_ZERO, '' ) ) .
         "\n\n" .
         T_('This notification has been sent to the tournament-owner and to all tournament-directors.');

      ta_begin();
      {//HOT-section to insert crowned-king for ladder-tournament as tournament-result
         $tresult->persist(); // add T-result

         // reset TL.RankChanged
         TournamentLadder::process_crown_king_reset_rank( $tid, $row['rid'] );

         // notify TDs + owner
         send_message( "TournamentHelper::process_tournament_ladder_crown_king.check.tid($tid)",
            $msg_text, sprintf( T_('King of the Hill crowned for tournament #%s'), $tid ),
            $nfy_uids, '', /*notify*/true,
            /*sys-msg*/0, MSGTYPE_NORMAL );
      }
      ta_end();
   }//process_tournament_ladder_crown_king

} // end of 'TournamentHelper'

?>
