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

require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_utils.php';
require_once 'tournaments/include/tournament_limits.php';
require_once 'tournaments/include/tournament_template_league.php';

require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_rules.php';
require_once 'tournaments/include/tournament_round.php';

 /*!
  * \file dgs_league.php
  *
  * \brief Classes and functions to handle (pooled) League-typed tournament
  */


 /*!
  * \class DgsLeagueTournament
  *
  * \brief Template-pattern for official "DGS League"-tournament
  */
class DgsLeagueTournament extends TournamentTemplateLeague
{
   public function __construct()
   {
      parent::__construct( TOURNEY_WIZTYPE_DGS_LEAGUE,
         T_('DGS League with tiered pools#tourney'),
         TOURNEY_TITLE_NO_RESTRICTION|TOURNEY_TITLE_ADMIN_ONLY );

      // overwrite tournament-type-specific properties
      $this->limits->setLimits( TLIMITS_MAX_TP, true, 2, TP_MAX_COUNT );

      // consts to be editable later
      // TierFactor := 2
      // PromoteRanks := 2
      // DemoteStartRank := 6
   }

   public function createTournament()
   {
      $tourney = $this->make_tournament( TOURNEY_SCOPE_DRAGON, "DGS League (19x19)" );

      $tprops = new TournamentProperties();
      $tprops->MinParticipants = 0;
      $tprops->MaxParticipants = 0;

      $trules = new TournamentRules();
      $trules->Size = 19;
      $trules->Handicaptype = TRULE_HANDITYPE_ALTERNATE;

      $tpoints = new TournamentPoints();
      $tpoints->setDefaults( TPOINTSTYPE_SIMPLE );

      $tround = new TournamentRound();
      $tround->MinPoolSize = 5;
      $tround->MaxPoolSize = 10;
      $tround->MaxPoolCount = 0;
      $tround->PoolWinnerRanks = 0;
      $tround->PoolSize = 10;

      return $this->_createTournament( $tourney, $tprops, $trules, $tpoints, $tround );
   }//createTournament

} // end of 'DgsLeagueTournament'

?>
