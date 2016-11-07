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
         $this->check_pools_invalid_ranks_flags( $tourney, $tround, $errors );
      }
   }//check_pools_finish_tournament_type_specific

   /*! \brief Checks that there is no invalid rank/flags-combination (which shouldn't happen, but would mess up pool-finishing if present). */
   protected function check_pools_invalid_ranks_flags( $tourney, $tround, &$errors )
   {
      global $base_path;
      $tid = (int)$tourney->ID;
      $uri_edit_user = $base_path . "tournaments/roundrobin/edit_ranks.php?tid=$tid&uid=%s";

      // check if there are invalid -90<=Rank<0, or Rank > allowed pool-size
      $result = db_query( "TournamentTemplateLeague.check_pools_invalid_ranks_flags.find_negative_ranks($tid)",
         "SELECT SQL_SMALL_RESULT TPOOL.uid, P.Handle, COUNT(*) AS X_Count " .
         "FROM TournamentPool AS TPOOL " .
            "INNER JOIN Players AS P ON P.ID=TPOOL.uid " .
         "WHERE TPOOL.tid=$tid AND TPOOL.Round=1 AND ((TPOOL.Rank BETWEEN ".TPOOLRK_RANK_ZONE." AND -1) OR TPOOL.Rank > {$tround->PoolSize}) " .
         "GROUP BY TPOOL.uid" );
      $arr_users = array();
      while ( $row = mysql_fetch_assoc($result) )
         $arr_users[] = anchor( sprintf($uri_edit_user, $row['uid']), $row['Handle'] );
      mysql_free_result($result);
      if ( count($arr_users) )
      {
         $errors[] = sprintf( T_('Pool-users [%s] have invalid ranks (<0 or >%s). Try removing their ranks and re-process them.'),
            implode(', ', $arr_users), $tround->PoolSize);
      }

      // check if there are withdrawals with relegation-flags set
      $result = db_query( "TournamentTemplateLeague.check_pools_invalid_ranks_flags.find_withdrawal_with_relegations($tid)",
         "SELECT SQL_SMALL_RESULT TPOOL.uid, P.Handle, COUNT(*) AS X_Count " .
         "FROM TournamentPool AS TPOOL " .
            "INNER JOIN Players AS P ON P.ID=TPOOL.uid " .
         "WHERE TPOOL.tid=$tid AND TPOOL.Round=1 " .
            "AND TPOOL.Rank=".TPOOLRK_WITHDRAW." AND (TPOOL.Flags & ".TPOOL_FLAG_RELEGATIONS.") " .
         "GROUP BY TPOOL.uid" );
      $arr_users = array();
      while ( $row = mysql_fetch_assoc($result) )
         $arr_users[] = anchor( sprintf($uri_edit_user, $row['uid']), $row['Handle'] );
      mysql_free_result($result);
      if ( count($arr_users) )
      {
         $errors[] = sprintf( T_('Pool-users [%s] have invalid state with withdrawing and relegation flags. ' .
               'Try removing their ranks and re-process them.'),
            implode(', ', $arr_users));
      }

      // check if invalid relegations set (promote + demote at the same time)
      $result = db_query( "TournamentTemplateLeague.check_pools_invalid_ranks_flags.find_both_relegations($tid)",
         "SELECT SQL_SMALL_RESULT TPOOL.uid, P.Handle, COUNT(*) AS X_Count " .
         "FROM TournamentPool AS TPOOL " .
            "INNER JOIN Players AS P ON P.ID=TPOOL.uid " .
         "WHERE TPOOL.tid=$tid AND TPOOL.Round=1 " .
            "AND TPOOL.Rank>0 AND (TPOOL.Flags & ".TPOOL_FLAG_RELEGATIONS.")=".TPOOL_FLAG_RELEGATIONS." " .
         "GROUP BY TPOOL.uid" );
      $arr_users = array();
      while ( $row = mysql_fetch_assoc($result) )
         $arr_users[] = anchor( sprintf($uri_edit_user, $row['uid']), $row['Handle'] );
      mysql_free_result($result);
      if ( count($arr_users) )
      {
         $errors[] = sprintf( T_('Pool-users [%s] have invalid relegation flags (both set). ' .
               'Try removing their ranks and re-process them.'),
            implode(', ', $arr_users));
      }
   }//check_pools_invalid_ranks_flags

} // end of 'TournamentTemplateLeague'

?>
