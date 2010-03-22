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
   function DgsRoundRobinTournament()
   {
      parent::TournamentTemplateRoundRobin(
         TOURNEY_WIZTYPE_DGS_ROUNDROBIN,
         T_('DGS Round-Robin with multiple pools (only for Admin)#ttype') );

      $this->limits->setLimits( TLIMITS_MAX_TP, true, 2, TP_MAX_COUNT );
   }

   function createTournament()
   {
      $tourney = $this->make_tournament( TOURNEY_SCOPE_DRAGON, "DGS pooled Round-Robin (19x19)" );

      $tprops = new TournamentProperties();
      $tprops->MinParticipants = 0;
      $tprops->MaxParticipants = 0;

      $t_rules = new TournamentRules();
      $t_rules->Size = 19;
      $t_rules->Handicaptype = TRULE_HANDITYPE_NIGIRI;

      $t_rnd = new TournamentRound();
      $t_rnd->MinPoolSize = 0;
      $t_rnd->MaxPoolSize = 8;

      return $this->_createTournament( $tourney, $tprops, $t_rules, $t_rnd );
   }

} // end of 'DgsRoundRobinTournament'

?>
