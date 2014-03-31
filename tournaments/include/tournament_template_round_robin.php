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

 /* Author: Jens-Uwe Gaspar */

$TranslateGroups[] = "Tournament";

require_once 'include/std_classes.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_template.php';

require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_points.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_pool_classes.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_log_helper.php';

 /*!
  * \file tournament_template_round_robin.php
  *
  * \brief Base Classe and functions to handle (pooled) round-robin-typed tournaments
  */


 /*!
  * \class TournamentTemplateRoundRobin
  *
  * \brief Template-pattern for general (pooled) round-robin-tournaments
  */
abstract class TournamentTemplateRoundRobin extends TournamentTemplate
{
   protected function __construct( $wizard_type, $title_main, $title_extras )
   {
      parent::__construct( $wizard_type, $title_main, $title_extras );

      // overwrite tournament-type-specific properties
      $this->need_rounds = true;
      $this->allow_register_tourney_status = array( TOURNEY_STATUS_REGISTER );
      $this->limits->setLimits( TLIMITS_TRD_MAX_ROUNDS, false, 1, TROUND_MAX_COUNT );
      $this->limits->setLimits( TLIMITS_TRD_MIN_POOLSIZE, false, 2, TROUND_MAX_POOLSIZE );
      $this->limits->setLimits( TLIMITS_TRD_MAX_POOLSIZE, false, 2, TROUND_MAX_POOLSIZE );
      $this->limits->setLimits( TLIMITS_TRD_MAX_POOLCOUNT, true, 1, TROUND_MAX_POOLCOUNT );
   }

   /*!
    * \brief Function to persist create tournament-tables for round-robin tournament.
    * \internal
    */
   protected function _createTournament( $tourney, $tprops, $trules, $tpoints, $tround )
   {
      global $NOW;

      ta_begin();
      {//HOT-section to create various tables for new tournament
         // check args
         if ( !($tourney instanceof Tournament) )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tourney.check(%s)");
         if ( !($tprops instanceof TournamentProperties) )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tprops.check(%s)");
         if ( !($trules instanceof TournamentRules) )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.trules.check(%s)");
         if ( !($tpoints instanceof TournamentPoints) )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tpoints.check(%s)");
         if ( !($tround instanceof TournamentRound) )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tround.check(%s)");

         // insert tournament-related tables
         if ( !$tourney->persist() )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tourney.insert(%s)");
         $tid = $tourney->ID;

         $tprops->tid = $tid;
         if ( !$tprops->insert() )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tprops.insert(%s,$tid)");

         $trules->tid = $tid;
         if ( !$trules->insert() )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.trules.insert(%s,$tid)");

         $tpoints->tid = $tid;
         if ( !$tpoints->insert() )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tpoints.insert(%s,$tid)");

         $tround->tid = $tid;
         if ( !$tround->insert() )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tround.insert(%s,$tid)");

         TournamentLogHelper::log_create_tournament( $tid, $tourney->WizardType, $tourney->Title );
      }
      ta_end();

      return $tid;
   }//_createTournament

   public function calcTournamentMinParticipants( $tprops, $tround )
   {
      if ( is_null($tround) )
         return 2;
      elseif ( $tround->Round > 1 )
         return $tround->MinPoolSize;
      else
         return max( $tprops->MinParticipants, $tround->MinPoolSize );
   }

   // NOTE: called on changes of T-status and T-round-status
   // \param $t_status target T-status TOURNEY_STATUS_...
   public function checkProperties( $tourney, $t_status )
   {
      $tid = $tourney->ID;
      $curr_round = $tourney->CurrentRound;
      $errors = array();

      $tround = TournamentCache::load_cache_tournament_round( 'TournamentTemplateRoundRobin.checkProperties',
         $tid, $curr_round );
      if ( $t_status == TOURNEY_STATUS_REGISTER || $t_status == TOURNEY_STATUS_PAIR )
         $errors = array_merge( $errors, $tround->check_properties() );

      if ( $t_status == TOURNEY_STATUS_REGISTER ) // check semantics of registration-properties
      {
         $tprops = TournamentCache::load_cache_tournament_properties( 'TournamentTemplateRoundRobin.checkProperties',
            $tid );
         $max_start_round = $this->determineLimitMaxStartRound( $tprops->MaxParticipants );
         $errors = array_merge( $errors, $tprops->check_properties( $max_start_round ) );
      }

      if ( $t_status == TOURNEY_STATUS_PAIR ) // extra-checks for PAIR-status
      {
         // TPs on APPLY/INVITE-status are not allowed!
         static $ARR_TPSTATUS = array( TP_STATUS_APPLY, TP_STATUS_INVITE );
         $tp_nonreg_count =
            TournamentParticipant::count_tournament_participants( $tid, $ARR_TPSTATUS, /*all-rounds*/0, /*NextR*/false );
         if ( $tp_nonreg_count > 0 )
         {
            $errors[] = sprintf(
               T_('Found %s non-registered tournament participants on status [%s]: they must be either registered or being removed.'),
               $tp_nonreg_count,
               build_text_list( 'TournamentParticipant::getStatusText', $ARR_TPSTATUS) );
         }
      }

      return $errors;
   }//checkProperties

   public function checkPooling( $tourney, $round )
   {
      $tid = $tourney->ID;

      if ( $round instanceof TournamentRound )
      {
         $tround = $round;
         $round = $tround->Round;
      }
      else
         $tround = TournamentCache::load_cache_tournament_round( 'TournamentTemplateRoundRobin.checkPooling', $tid, $round );

      list( $check_errors, $arr_pool_summary ) = TournamentPool::check_pools( $tround );
      return $check_errors;
   }//checkPooling

   public function checkParticipantRegistrations( $tid, $arr_TPs )
   {
      // see also tournament_status.php:
      // - no check for round-robin-tourneys, because participants already seeded in pools
      return array();
   }

   public function checkGamesStarted( $tid )
   {
      // see also tournament_status.php
      $errors = array();

      $tourney = TournamentCache::load_cache_tournament( 'TournamentTemplateRoundRobin.checkGamesStarted.find_tourney', $tid );
      $round = $tourney->CurrentRound;

      $tround = TournamentCache::load_cache_tournament_round( 'TournamentTemplateRoundRobin.checkGamesStarted', $tid, $round );
      if ( $tround->Status != TROUND_STATUS_PLAY )
         $errors[] = sprintf( T_('Expecting current tournament round status [%s] for status-change'),
                              TournamentRound::getStatusText(TROUND_STATUS_PLAY) );

      // check, that all T-games are started for current round
      $expected_games = TournamentPool::count_tournament_pool_games( $tid, $round );
      $count_tgames = TournamentGames::count_tournament_games( $tid, $tround->ID, array() );
      $count_games_started = TournamentGames::count_games_started( $tid, $tround->ID );

      if ( $count_tgames != $expected_games )
         $errors[] = sprintf( T_('Expected %s tournament-games (TG), but found %s games: Start remaining games first!'),
            $expected_games, $count_tgames );
      if ( $count_games_started != $expected_games )
         $errors[] = sprintf( T_('Expected %s games (G), but found %s games: Contact tournament-admin for fix!'),
            $expected_games, $count_games_started );

      return $errors;
   }//checkGamesStarted


   public function checkPoolWinners( $tourney, $tround )
   {
      $errors = array();
      $warnings = array();

      $tid = (int)$tourney->ID;
      $round = (int)$tround->Round;

      $this->check_unset_pool_ranks( $tid, $round, $errors );
      $this->check_auto_poolwinners( $tid, $tround, $errors, $warnings );
      $this->check_minimum_poolwinners( $tid, $tround, $errors, $warnings );

      return array( $errors, $warnings );
   }//checkPoolWinners


   /*! \brief Checks that there is no unset TPool.Rank (<= TPOOLRK_RANK_ZONE). */
   private function check_unset_pool_ranks( $tid, $round, &$errors )
   {
      $result = db_query( "TournamentTemplateRoundRobin.check_unset_pool_ranks($tid,$round)",
         "SELECT SQL_SMALL_RESULT Pool, COUNT(*) AS X_Count FROM TournamentPool " .
         "WHERE tid=$tid AND Round=$round AND Rank <= ".TPOOLRK_RANK_ZONE." GROUP BY Pool" );
      $arr = array();
      $cnt = 0;
      while ( $row = mysql_fetch_assoc($result) )
      {
         $arr[] = $row['Pool'];
         $cnt += $row['X_Count'];
      }
      mysql_free_result($result);
      if ( count($arr) )
         $errors[] = sprintf( T_('Pool(s) [%s] have still %s unset ranks.'), implode(',', $arr), $cnt );
   }//check_unset_ranks

   /*! \brief Checks that all "automatic" PoolWinnerRanks are set. */
   private function check_auto_poolwinners( $tid, $tround, &$errors, &$warnings )
   {
      if ( $tround->PoolWinnerRanks > 0 )
      {
         $round = (int)$tround->Round;

         $result = db_query( "TournamentTemplateRoundRobin.check_auto_poolwinners.miss_auto($tid,$round)",
            "SELECT SQL_SMALL_RESULT DISTINCT Pool FROM TournamentPool " .
            "WHERE tid=$tid AND Round=$round AND Rank >= -{$tround->PoolWinnerRanks} AND Rank < 0" );
         $arr = array();
         while ( $row = mysql_fetch_assoc($result) )
            $arr[] = $row['Pool'];
         mysql_free_result($result);
         if ( count($arr) )
         {
            $errors[] = sprintf( T_('Pool(s) [%s] have unmarked pool-winners (rank <= %d).'),
               implode(',', $arr), $tround->PoolWinnerRanks );
         }

         // warning if there is TP with TPool.Rank > PoolWinnerRank (may be set by TD, or a mistake)
         $result = db_query( "TournamentTemplateRoundRobin.check_auto_poolwinners.bigger_rank($tid,$round)",
            "SELECT TPOOL.Pool, TPOOL.Rank, TPOOL.uid as ID, TPP.Handle, TPP.Name " .
            "FROM TournamentPool TPOOL INNER JOIN Players AS TPP ON TPP.ID=TPOOL.uid " .
            "WHERE TPOOL.tid=$tid AND TPOOL.Round=$round AND TPOOL.Rank > {$tround->PoolWinnerRanks}" );
         $arr = array();
         while ( $row = mysql_fetch_assoc($result) )
         {
            $arr[] = '** ' . sprintf( T_('Pool %d: user [%s] with rank %d'),
               $row['Pool'], user_reference(0, 1, 'WarnMsg', $row), $row['Rank'] );
         }
         mysql_free_result($result);
         if ( count($arr) )
         {
            $warnings[] =
               sprintf( T_("There are the following users marked as pool winners with a bigger rank \n"
                        .  "than the pool-winner rank [%s] defined for this round (which is either a mistake \n"
                        .  "or done intentionally by the tournament directors):"),
                  $tround->PoolWinnerRanks ) . "<br>\n" . implode("<br>\n", $arr);
         }
      }
   }//check_auto_poolwinners

   /*! \brief Checks that at least ONE PoolWinner is set per pool of current round. */
   private function check_minimum_poolwinners( $tid, $tround, &$errors, &$warnings )
   {
      $round = (int)$tround->Round;

      // warning, if there are pools without pool-winners
      $result = db_query( "TournamentTemplateRoundRobin.check_minimum_poolwinners.1($tid,$round)",
         "SELECT SQL_SMALL_RESULT Pool, COUNT(*) AS X_Count FROM TournamentPool " .
         "WHERE tid=$tid AND Round=$round AND Rank > 0 GROUP BY Pool" );
      $arr = array_value_to_key_and_value( range(1, $tround->Pools) ); // [ 1=>1, 2=>2, ... ]
      while ( $row = mysql_fetch_assoc($result) )
         unset($arr[$row['Pool']]);
      mysql_free_result($result);
      if ( count($arr) )
         $warnings[] = sprintf( T_('Pool(s) [%s] should have at least one pool-winner.'), implode(',', array_keys($arr)) );

      // warning, if there are pools with all players marked as pool-winners
      $result = db_query( "TournamentTemplateRoundRobin.check_minimum_poolwinners.2($tid,$round)",
         "SELECT SQL_SMALL_RESULT Pool, COUNT(*) AS X_PoolCount, SUM(IF(Rank>0,1,0)) AS X_WinnerCount FROM TournamentPool " .
         "WHERE tid=$tid AND Round=$round GROUP BY Pool HAVING X_PoolCount=X_WinnerCount" );
      $arr = array(); // [ Pool, ... ]
      while ( $row = mysql_fetch_assoc($result) )
         $arr[] = $row['Pool'];
      mysql_free_result($result);
      if ( count($arr) )
         $warnings[] = sprintf( T_('In pool(s) [%s] ALL players are marked as pool-winners.'), implode(',', $arr) );

      // warning, if there's not a single pool-winner for current round
      $cnt_tpool_next_rounders = TournamentPool::count_tournament_pool_next_rounders( $tid, $round );
      if ( $cnt_tpool_next_rounders == 0 )
         $warnings[] = T_('There is no pool-winner for this round in any pool.');

      // warning, if there are not enough players (min. 2) for start of a potential next round
      $cnt_tp_nextround = TournamentParticipant::count_tournament_participants( $tid, null, $round + 1, /*NextRnd*/true );
      if ( $cnt_tp_nextround + $cnt_tpool_next_rounders < 2 )
         $warnings[] = sprintf( T_('Need at least %s players to start next round.'), 2 );
   }//check_min_poolwinners


   public function checkClosingTournament( $tourney )
   {
      global $base_path;
      $errors = array();
      $warnings = array();
      $tid = $tourney->ID;
      $last_round = $tourney->Rounds;

      $this->check_unfinished_rounds( $tid, $errors );

      // warn, if there are TPs with higher (start-/)next-round w/o having played yet
      $iterator = new ListIterator( 'TournamentTemplateRoundRobin.checkClosingTournament.TP',
         new QuerySQL( SQLP_WHERE, "TP.NextRound > $last_round" ),
         'ORDER BY TP.ID ASC' );
      $iterator = TournamentParticipant::load_tournament_participants( $iterator, $tid );
      $cnt_unplayed_tps = $iterator->getItemCount();
      if ( $cnt_unplayed_tps > 0 )
      {
         $warnings[] = sprintf( T_('There are %s tournament participants registered to start in rounds higher than last round #%s:'),
                                $cnt_unplayed_tps, $last_round )
            . "<br>\n** " . T_('These participants should be "handled" first before finishing the tournament.')
            . "<br>\n** "
            . anchor( $base_path."tournaments/list_participants.php?tid=$tid".URI_AMP."round=".urlencode(($last_round+1).'-'),
                      T_('Show tournament participants on higher start rounds.'));
      }

      // warn, if there is more than one pool in final round.
      list( $cnt_all, $cnt_pools, $cnt_users ) = TournamentPool::count_tournament_pool( $tid, $last_round );
      if ( $cnt_pools > 1 )
         $warnings[] = sprintf( T_('There is more than one pool in last round #%s: found %s pools.'),
            $last_round, $cnt_pools );

      return array( $errors, $warnings );
   }//checkClosingTournament

   /*! \brief Adds error, if not all tournament-rounds are on DONE-Status. */
   private function check_unfinished_rounds( $tid, &$errors )
   {
      $iterator = new ListIterator( 'TournamentTemplateRoundRobin.check_unfinished_rounds',
         new QuerySQL( SQLP_WHERE, "Status<>'".TROUND_STATUS_DONE."'" ),
         'ORDER BY Round ASC' );
      $iterator = TournamentRound::load_tournament_rounds( $iterator, $tid );

      $arr_rounds = array();
      while ( list(,$arr_item) = $iterator->getListIterator() )
      {
         list( $tround, $orow ) = $arr_item;
         $arr_rounds[] = $tround->Round;
      }

      if ( count($arr_rounds) > 0 )
         $errors[] = sprintf( T_('All rounds must be done before tournament can be finished: Rounds [%s] are not done yet.'),
            implode(',', $arr_rounds) );
   }//check_unfinished_rounds

} // end of 'TournamentTemplateRoundRobin'

?>
