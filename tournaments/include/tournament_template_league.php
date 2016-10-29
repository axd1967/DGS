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

} // end of 'TournamentTemplateLeague'

?>
