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

require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_utils.php';
require_once 'tournaments/include/tournament_limits.php';
require_once 'tournaments/include/tournament_template_round_robin.php';

require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_rules.php';
require_once 'tournaments/include/tournament_round.php';

 /*!
  * \file dgs_round_robin.php
  *
  * \brief Classes and functions to handle (pooled) Round-Robin-typed tournament
  */


 /*!
  * \class DgsRoundRobinTournament
  *
  * \brief Template-pattern for official "DGS Round-Robin"-tournament
  */
class DgsRoundRobinTournament extends TournamentTemplateRoundRobin
{
   public function __construct()
   {
      parent::__construct( TOURNEY_WIZTYPE_DGS_ROUNDROBIN,
         T_('DGS Round-Robin with multiple rounds and pools#tourney'),
         TOURNEY_TITLE_NO_RESTRICTION|TOURNEY_TITLE_ADMIN_ONLY );

      $this->limits->setLimits( TLIMITS_MAX_TP, true, 2, TP_MAX_COUNT );
   }

   public function createTournament()
   {
      $tourney = $this->make_tournament( TOURNEY_SCOPE_DRAGON, "DGS pooled Round-Robin (19x19)" );

      $tprops = new TournamentProperties();
      $tprops->MinParticipants = 0;
      $tprops->MaxParticipants = 0;
      $tprops->MaxStartRound = 0; // 0 provoke error, so change by TD enforced
      $tprops->setMinRatingStartRound( NO_RATING );

      $trules = new TournamentRules();
      $trules->Size = 19;
      $trules->Handicaptype = TRULE_HANDITYPE_NIGIRI;

      $tpoints = new TournamentPoints();
      $tpoints->setDefaults( TPOINTSTYPE_SIMPLE );

      $tround = new TournamentRound();
      $tround->MinPoolSize = 0; // 0's provoke error, so change by TD enforced
      $tround->MaxPoolSize = 0;
      $tround->MaxPoolCount = 0;
      $tround->PoolWinnerRanks = 0;

      return $this->_createTournament( $tourney, $tprops, $trules, $tpoints, $tround );
   }//createTournament

} // end of 'DgsRoundRobinTournament'

?>
