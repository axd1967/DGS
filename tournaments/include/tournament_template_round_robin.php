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
require_once 'tournaments/include/tournament_template.php';

require_once 'tournaments/include/tournament_round.php';

 /*!
  * \file tournament_template_round_robin.php
  *
  * \brief Base Classe and functions to handle (pooled) round-robin-typed tournaments
  */


 /*!
  * \class TournamentTemplateRoundRobin
  *
  * \brief Template-pattern for general (pooled) round-robin-tournaments
  */
class TournamentTemplateRoundRobin extends TournamentTemplate
{
   function TournamentTemplateRoundRobin( $wizard_type, $title )
   {
      parent::TournamentTemplate( $wizard_type, $title );

      // overwrite tournament-type-specific properties
      $this->need_rounds = true;
      $this->allow_register_tourney_status = array( TOURNEY_STATUS_REGISTER );
      $this->limits->setLimits( TLIMITS_MAX_ROUNDS, true, 1, TROUND_MAX_COUNT );
   }

   /*!
    * \brief Abstract function to persist create tournament-tables.
    * \internal
    */
   function _createTournament( $tourney, $tprops, $t_rules, $t_rnd )
   {
      global $NOW;

      ta_begin();
      {//HOT-section to create various tables for new tournament
         // check args
         if( !is_a($tourney, 'Tournament') )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tourney.check(%s)");
         if( !is_a($tprops, 'TournamentProperties') )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tprops.check(%s)");
         if( !is_a($t_rules, 'TournamentRules') )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.t_rules.check(%s)");
         if( !is_a($t_rnd, 'TournamentRound') )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.t_rnd.check(%s)");

         // insert tournament-related tables
         if( !$tourney->persist() )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tourney.insert(%s)");
         $tid = $tourney->ID;

         $tprops->tid = $tid;
         if( !$tprops->insert() )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tprops.insert(%s,$tid)");

         $t_rules->tid = $tid;
         if( !$t_rules->insert() )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.t_rules.insert(%s,$tid)");

         $t_rnd->tid = $tid;
         if( !$t_rnd->insert() )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.t_rnd.insert(%s,$tid)");
      }
      ta_end();

      return $tid;
   }

   function checkProperties( $tid )
   {
      //TODO check-properties for round-robin
      return array( 'Error' );
   }

   function checkParticipantRegistrations( $tid, $arr_TPs )
   {
      //TODO see also tournament_status.php
      //TODO(later) condition (for round-robin): all T-games must be ready to start (i.e. inserted and set up)
      return array( 'Error' );
   }

} // end of 'TournamentTemplateRoundRobin'

?>
