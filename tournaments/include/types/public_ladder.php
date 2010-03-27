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
  * \file public_ladder.php
  *
  * \brief Classes and functions to handle Ladder-typed tournament
  */


 /*!
  * \class PublicLadderTournament
  *
  * \brief Template-pattern for public Ladder-tournament
  */
class PublicLadderTournament extends TournamentTemplateLadder
{
   function PublicLadderTournament()
   {
      parent::TournamentTemplateLadder(
         TOURNEY_WIZTYPE_PUBLIC_LADDER,
         T_('Public Ladder (with game restrictions)#ttype') );

      // overwrite tournament-type-specific properties
      $this->need_admin_create_tourney = false;
      $this->limits->setLimits( TLIMITS_MAX_TP, false, 2, 300 );
      $this->limits->setLimits( TLIMITS_TL_MAX_DF, false, 0, 10 );
      $this->limits->setLimits( TLIMITS_TL_MAX_CH, false, 1, 10 );
   }

   function createTournament()
   {
      global $player_row;
      $tourney = $this->make_tournament( TOURNEY_SCOPE_PUBLIC,
         sprintf( T_('%s\'s Ladder'), $player_row['Handle'] ) );

      $tprops = new TournamentProperties();
      $tprops->MinParticipants = 0;
      $tprops->MaxParticipants = 100;

      $trules = new TournamentRules();

      $tl_props = new TournamentLadderProps();
      $tl_props->ChallengeRangeAbsolute = 10;
      $tl_props->MaxChallenges = 5;
      $tl_props->MaxDefenses = 3;

      return $this->_createTournament( $tourney, $tprops, $trules, $tl_props );
   }

} // end of 'PublicLadderTournament'

?>
