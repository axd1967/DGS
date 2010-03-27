<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'tournaments/include/tournament_template_ladder.php';

require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_rules.php';
require_once 'tournaments/include/tournament_ladder_props.php';

 /*!
  * \file dgs_ladder.php
  *
  * \brief Classes and functions to handle Ladder-typed tournament
  */


 /*!
  * \class DgsLadderTournament
  *
  * \brief Template-pattern for official "DGS Ladder"-tournament
  */
class DgsLadderTournament extends TournamentTemplateLadder
{
   function DgsLadderTournament()
   {
      parent::TournamentTemplateLadder(
         TOURNEY_WIZTYPE_DGS_LADDER,
         T_('DGS Ladder (only for Admin)#ttype') );

      $this->limits->setLimits( TLIMITS_MAX_TP, true, 2, TP_MAX_COUNT );
      $this->limits->setLimits( TLIMITS_TL_MAX_DF, false, 0, TLADDER_MAX_DEFENSES );
      $this->limits->setLimits( TLIMITS_TL_MAX_CH, true, 0, TLADDER_MAX_CHALLENGES );
   }

   function createTournament()
   {
      $tourney = $this->make_tournament( TOURNEY_SCOPE_DRAGON, "DGS Ladder (19x19)" );

      $tprops = new TournamentProperties();
      $tprops->MinParticipants = 0;
      $tprops->MaxParticipants = 0;

      $trules = new TournamentRules();
      $trules->Size = 19;
      $trules->Handicaptype = TRULE_HANDITYPE_NIGIRI;

      $tl_props = new TournamentLadderProps();
      $tl_props->ChallengeRangeAbsolute = 10;
      $tl_props->MaxChallenges = 10;
      // 1..5 max. 5 games, 6..10 max. 4 games, else 3 games:
      $tl_props->MaxDefenses1 = 5;
      $tl_props->MaxDefensesStart1 = 5;
      $tl_props->MaxDefenses2 = 4;
      $tl_props->MaxDefensesStart2 = 10;
      $tl_props->MaxDefenses = 3;

      return $this->_createTournament( $tourney, $tprops, $trules, $tl_props );
   }

} // end of 'DgsLadderTournament'

?>
