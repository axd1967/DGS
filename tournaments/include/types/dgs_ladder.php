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

require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_utils.php';
require_once 'tournaments/include/tournament_template.php';

require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_rules.php';

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
class DgsLadderTournament extends TournamentTemplate
{
   function DgsLadderTournament()
   {
      parent::TournamentTemplate( TOURNEY_WIZTYPE_DGS_LADDER );
      $this->need_rounds = false;
      $this->allow_register_tourney_status = array( TOURNEY_STATUS_REGISTER, TOURNEY_STATUS_PLAY );
   }

   function createTournament()
   {
      $tourney = new Tournament();
      $tourney->setScope(TOURNEY_SCOPE_DRAGON);
      $tourney->setWizardType($this->wizard_type);
      $tourney->Title = "DGS Ladder (19x19)";
      $tourney->Owner_ID = $this->uid;
      if( !$tourney->persist() )
         create_error("LadderTournament.createTournament1(%s)");
      $tid = $tourney->ID;

      $tprops = new TournamentProperties( $tid );
      $tprops->MinParticipants = 1;
      if( !$tprops->insert() )
         create_error("LadderTournament.createTournament2(%s,$tid)");

      $t_rules = new TournamentRules( 0, $tid );
      $t_rules->Size = 19;
      $t_rules->Handicaptype = TRULE_HANDITYPE_NIGIRI;
      if( !$t_rules->persist() )
         create_error("LadderTournament.createTournament3(%s,$tid)");

      return $tid;
   }

} // end of 'DgsLadderTournament'

?>
