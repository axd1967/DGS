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
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_template_round_robin.php';

require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_points.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_pool_classes.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_log_helper.php';

 /*!
  * \file tournament_template_league.php
  *
  * \brief Base Classe and functions to handle (tiered & pooled) league-typed tournaments
  */


 /*!
  * \class TournamentTemplateLeague
  *
  * \brief Template-pattern for general (tiered & pooled) league-tournaments
  */
abstract class TournamentTemplateLeague extends TournamentTemplateRoundRobin
{
   protected function __construct( $wizard_type, $title_main, $title_extras )
   {
      parent::__construct( $wizard_type, $title_main, $title_extras );

      // overwrite tournament-type-specific properties
      $this->need_rounds = true;
      $this->allow_register_tourney_status = array( TOURNEY_STATUS_REGISTER );
      $this->limits->setLimits( TLIMITS_TRD_MAX_ROUNDS, false, 1, 1 ); // 1 round = 1 cycle
      $this->limits->setLimits( TLIMITS_TRD_MIN_POOLSIZE, false, 5, 10 );
      $this->limits->setLimits( TLIMITS_TRD_MAX_POOLSIZE, false, 10, 10 );

      // consts to be editable later (see also fields of TournamentRound-class)
      // TierFactor := 2
      // PromoteRanks := 2
      // DemoteStartRank := 6
   }

   public function getDefaultPoolNamesFormat()
   {
      return '%t(uc)-%L, %P %t(uc)%p(num)';
   }

   protected function check_pools_finish_tournament_type_specific( $tourney, $tround, &$errors, &$warnings )
   {
      if ( $tourney->Type == TOURNEY_TYPE_LEAGUE )
      {
         $tid = $tourney->ID;
         $tpool_iterator = new ListIterator( "TLG:check_pools_finish_tournament_type_specific.load_tpool($tid)" );
         $tpool_iterator = TournamentPool::load_tournament_pools( $tpool_iterator, $tid, 1, 0, TPOOL_LOADOPT_USER_HANDLE );

         $this->check_pools_invalid_ranks_flags( $tid, $tround, $tpool_iterator, $errors );
         $this->check_pools_relegations( $tourney->Type, $tround, $tpool_iterator, $warnings );
      }
   }//check_pools_finish_tournament_type_specific

   /*! \brief Checks that there is no invalid rank/flags-combination (which shouldn't happen, but would mess up pool-finishing if present). */
   protected function check_pools_invalid_ranks_flags( $tid, $tround, $tpool_iterator, &$errors )
   {
      global $base_path;
      $uri_edit_user = $base_path . "tournaments/roundrobin/edit_ranks.php?tid=$tid&uid=%s";

      $arr_users_invalid_ranks = array();
      $arr_users_invalid_withdrawals = array();
      $arr_users_invalid_relegations = array();

      $tpool_iterator->resetListIterator();
      while ( list(,$arr_item) = $tpool_iterator->getListIterator() )
      {
         list( $tpool, $orow ) = $arr_item;
         $uid = $tpool->uid;
         $handle = $tpool->User->Handle;

         // check if there are invalid -90<=Rank<0, or Rank > allowed pool-size
         if ( ( $tpool->Rank >= TPOOLRK_RANK_ZONE && $tpool->Rank < 0 ) || $tpool->Rank > $tround->PoolSize )
            $arr_users_invalid_ranks[] = anchor( sprintf($uri_edit_user, $uid), $handle );

         // check if there are withdrawals with relegation-flags set
         if ( $tpool->Rank == TPOOLRK_WITHDRAW && ($tpool->Flags & TPOOL_FLAG_RELEGATIONS) )
            $arr_users_invalid_withdrawals[] = anchor( sprintf($uri_edit_user, $uid), $handle );

         // check if invalid relegations set (promote + demote at the same time)
         if ( $tpool->Rank > 0 && ($tpool->Flags & TPOOL_FLAG_RELEGATIONS) == TPOOL_FLAG_RELEGATIONS )
            $arr_users_invalid_relegations[] = anchor( sprintf($uri_edit_user, $uid), $handle );
      }

      if ( count($arr_users_invalid_ranks) )
      {
         $errors[] = sprintf( T_('Pool-users [%s] have invalid ranks (<0 or >%s). Try removing their ranks and re-process them.'),
            implode(', ', $arr_users_invalid_ranks), $tround->PoolSize);
      }
      if ( count($arr_users_invalid_withdrawals) )
      {
         $errors[] = sprintf( T_('Pool-users [%s] have invalid state with withdrawing and relegation flags. ' .
               'Try removing their ranks and re-process them.'),
            implode(', ', $arr_users_invalid_withdrawals));
      }
      if ( count($arr_users_invalid_relegations) )
      {
         $errors[] = sprintf( T_('Pool-users [%s] have invalid relegation flags (both set). ' .
               'Try removing their ranks and re-process them.'),
            implode(', ', $arr_users_invalid_relegations));
      }
   }//check_pools_invalid_ranks_flags

   /*! \brief Checks that relegations are done correctly, but allow T-director to overwrite (so issue as warnings). */
   protected function check_pools_relegations( $tourney_type, $tround, $tpool_iterator, &$warnings )
   {
      global $base_path;
      $tid = $tround->tid;
      $uri_edit_user = $base_path . "tournaments/roundrobin/edit_ranks.php?tid=$tid&uid=%s";

      $cnt_double_ranks = array(); // [ tier-pool-key => [ Rank => COUNT, ... ] ]
      $arr_double_ranks = array(); // [ pool-name => 1, ... ]
      $arr_should_promote = array(); // [ user-edit-link, ... ]
      $arr_should_demote = array(); // [ user-edit-link, ... ]
      $arr_should_stay = array(); // [ user-edit-link, ... ]
      $cnt_relegations = array(); // [ pool-name => [ TPOOL_FLAG_PROMOTE|DEMOTE|0 => COUNT, ... ] ]

      $tpool_iterator->resetListIterator();
      while ( list(,$arr_item) = $tpool_iterator->getListIterator() )
      {
         list( $tpool, $orow ) = $arr_item;

         if ( $tpool->Rank > 0 )
         {
            $tier_pool_key = TournamentUtils::encode_tier_pool_key( $tpool->Tier, $tpool->Pool );
            $pool_name = PoolViewer::format_tier_pool( $tourney_type, $tpool->Tier, $tpool->Pool, true );
            $is_promote = ( $tpool->Flags & TPOOL_FLAG_PROMOTE );
            $is_demote = ( $tpool->Flags & TPOOL_FLAG_DEMOTE );
            $uid = $tpool->uid;
            $handle = $tpool->User->Handle;

            // check for double-ranks -> may need tie-breaking
            if ( !isset($cnt_double_ranks[$tier_pool_key][$tpool->Rank]) )
               $cnt_double_ranks[$tier_pool_key][$tpool->Rank] = 1;
            else
               $cnt_double_ranks[$tier_pool_key][$tpool->Rank]++;
            if ( $cnt_double_ranks[$tier_pool_key][$tpool->Rank] == 2 ) // report only once
               $arr_double_ranks[$pool_name] = 1;

            // check for users with rank-based relegations that doesn't match league-configuration
            $user_edit_link = anchor( sprintf($uri_edit_user, $uid), "$pool_name: $handle" );
            if ( $tpool->Rank <= $tround->PromoteRanks && !$is_promote )
               $arr_should_promote[] = $user_edit_link; // user should be promoted
            if ( $tpool->Rank >= $tround->DemoteStartRank && !$is_demote )
               $arr_should_demote[] = $user_edit_link; // user should be demoted
            elseif ( $tpool->Rank > $tround->PromoteRanks && $tpool->Rank < $tround->DemoteStartRank && ($is_promote || $is_demote) )
               $arr_should_stay[] = $user_edit_link; // user should stay in same league

            // check for too many relegations (promote / demote / stay)
            if ( !($is_promote && $is_demote) ) // skip bad relegation-state
            {
               if ( !isset($cnt_relegations[$pool_name]) )
                  $cnt_relegations[$pool_name] = array( TPOOL_FLAG_PROMOTE => 0, TPOOL_FLAG_DEMOTE => 0, 0 => 0 );
               $cnt_relegations[$pool_name][$tpool->Flags & TPOOL_FLAG_RELEGATIONS]++;
            }
         }
      }

      if ( count($arr_double_ranks) )
         $warnings[] = sprintf( T_('Found pools [%s] with non-unique ranks, which may need tie-breaking.'),
            implode(', ', array_keys($arr_double_ranks)) );

      if ( count($arr_should_promote) )
         $warnings[] = sprintf( T_('Found pool with users [%s], that should be promoted.'),
            implode(', ', $arr_should_promote) );
      if ( count($arr_should_demote) )
         $warnings[] = sprintf( T_('Found pool with users [%s], that should be demoted.'),
            implode(', ', $arr_should_demote) );
      if ( count($arr_should_stay) )
         $warnings[] = sprintf( T_('Found pool with users [%s], that should stay in the same league.'),
            implode(', ', $arr_should_stay) );

      $exp_promotes = $tround->PromoteRanks;
      $exp_demotes = $tround->PoolSize - $tround->DemoteStartRank + 1;
      $exp_stays = $tround->DemoteStartRank - $tround->PromoteRanks - 1;
      $arr_too_many_promotes = array();
      $arr_too_many_demotes = array();
      $arr_too_many_stays = array();
      foreach ( $cnt_relegations as $pool_name => $arr_cnt_flags )
      {
         $cnt_promote = (int)@$arr_cnt_flags[TPOOL_FLAG_PROMOTE];
         $cnt_demote = (int)@$arr_cnt_flags[TPOOL_FLAG_DEMOTE];
         $cnt_stay = (int)@$arr_cnt_flags[0];
         if ( $cnt_promote > $exp_promotes )
            $arr_too_many_promotes[] = $pool_name;
         if ( $cnt_demote > $exp_demotes )
            $arr_too_many_demotes[] = $pool_name;
         if ( $cnt_stay > $exp_stays )
            $arr_too_many_stays[] = $pool_name;
      }

      if ( count($arr_too_many_promotes) )
         $warnings[] = sprintf( T_('Pools [%s] have too many promoted users (max. %s expected).'),
            implode(', ', $arr_too_many_promotes), $exp_promotes );
      if ( count($arr_too_many_demotes) )
         $warnings[] = sprintf( T_('Pools [%s] have too many demoted users (max. %s expected).'),
            implode(', ', $arr_too_many_demotes), $exp_demotes );
      if ( count($arr_too_many_stays) )
         $warnings[] = sprintf( T_('Pools [%s] have too many users that stay in the same league (max. %s expected).'),
            implode(', ', $arr_too_many_stays), $exp_stays );
   }//check_pools_relegations

} // end of 'TournamentTemplateLeague'

?>
