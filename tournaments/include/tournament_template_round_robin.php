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

require_once 'tournaments/include/tournament_properties.php';
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
      $this->limits->setLimits( TLIMITS_TRD_MAX_ROUNDS, false, 1, TROUND_MAX_COUNT );
      $this->limits->setLimits( TLIMITS_TRD_MIN_POOLSIZE, false, 2, TROUND_MAX_POOLSIZE );
      $this->limits->setLimits( TLIMITS_TRD_MAX_POOLSIZE, false, 2, TROUND_MAX_POOLSIZE );
      $this->limits->setLimits( TLIMITS_TRD_MAX_POOLCOUNT, true, 1, TROUND_MAX_POOLCOUNT );
   }

   /*!
    * \brief Abstract function to persist create tournament-tables.
    * \internal
    */
   function _createTournament( $tourney, $tprops, $trules, $tround )
   {
      global $NOW;

      ta_begin();
      {//HOT-section to create various tables for new tournament
         // check args
         if( !is_a($tourney, 'Tournament') )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tourney.check(%s)");
         if( !is_a($tprops, 'TournamentProperties') )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tprops.check(%s)");
         if( !is_a($trules, 'TournamentRules') )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.trules.check(%s)");
         if( !is_a($tround, 'TournamentRound') )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tround.check(%s)");

         // insert tournament-related tables
         if( !$tourney->persist() )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tourney.insert(%s)");
         $tid = $tourney->ID;

         $tprops->tid = $tid;
         if( !$tprops->insert() )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tprops.insert(%s,$tid)");

         $trules->tid = $tid;
         if( !$trules->insert() )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.trules.insert(%s,$tid)");

         $tround->tid = $tid;
         if( !$tround->insert() )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tround.insert(%s,$tid)");
      }
      ta_end();

      return $tid;
   }

   function calcTournamentMinParticipants( $tprops, $tround=null )
   {
      return max( $tprops->MinParticipants, $tround->MinPoolSize );
   }

   function checkProperties( $tourney, $t_status )
   {
      $tid = $tourney->ID;
      $round = $tourney->CurrentRound;
      $errors = array();

      $tround = TournamentRound::load_tournament_round( $tid, $round );
      if( is_null($tround) )
         error('bad_tournament', "TournamentTemplateRoundRobin.checkProperties.find_tround($tid,$round,{$this->uid})");

      if( $t_status == TOURNEY_STATUS_REGISTER || $t_status == TOURNEY_STATUS_PAIR )
         $errors = array_merge( $errors, $tround->check_properties() );

      return $errors;
   }

   function checkParticipantRegistrations( $tid, $arr_TPs )
   {
      //TODO see also tournament_status.php
      //TODO(later) condition (for round-robin): all T-games must be ready to start (i.e. inserted and set up)
      return array( 'Error' );
   }

} // end of 'TournamentTemplateRoundRobin'

?>
