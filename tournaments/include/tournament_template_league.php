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

} // end of 'TournamentTemplateLeague'

?>
