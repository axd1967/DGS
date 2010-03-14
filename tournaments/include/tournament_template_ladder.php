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

require_once 'tournaments/include/tournament_ladder_props.php';
require_once 'tournaments/include/tournament_ladder.php';
require_once 'tournaments/include/tournament_extension.php';

 /*!
  * \file tournament_template_ladder.php
  *
  * \brief Base Classe and functions to handle Ladder-typed tournaments
  */


 /*!
  * \class TournamentTemplateLadder
  *
  * \brief Template-pattern for general Ladder-tournaments
  */
class TournamentTemplateLadder extends TournamentTemplate
{
   function TournamentTemplateLadder( $wizard_type, $title )
   {
      parent::TournamentTemplate( $wizard_type, $title );

      // overwrite tournament-type-specific properties
      $this->allow_register_tourney_status = array( TOURNEY_STATUS_REGISTER, TOURNEY_STATUS_PLAY );
   }

   /*!
    * \brief Abstract function to persist create tournament-tables.
    * \internal
    */
   function _createTournament( $tourney, $tprops, $t_rules, $tl_props )
   {
      global $NOW;

      ta_begin();
      {//HOT-section to create various tables for new tournament
         // check args
         if( !is_a($tourney, 'Tournament') )
            $this->create_error("TournamentTemplateLadder._createTournament.tourney.check(%s)");
         if( !is_a($tprops, 'TournamentProperties') )
            $this->create_error("TournamentTemplateLadder._createTournament.tprops.check(%s)");
         if( !is_a($t_rules, 'TournamentRules') )
            $this->create_error("TournamentTemplateLadder._createTournament.t_rules.check(%s)");
         if( !is_a($tl_props, 'TournamentLadderProps') )
            $this->create_error("TournamentTemplateLadder._createTournament.tl_props.check(%s)");

         // insert tournament-related tables
         if( !$tourney->persist() )
            $this->create_error("TournamentTemplateLadder._createTournament.tourney.insert(%s)");
         $tid = $tourney->ID;

         $tprops->tid = $tid;
         if( !$tprops->insert() )
            $this->create_error("TournamentTemplateLadder._createTournament.tprops.insert(%s,$tid)");

         $t_rules->tid = $tid;
         if( !$t_rules->insert() )
            $this->create_error("TournamentTemplateLadder._createTournament.t_rules.insert(%s,$tid)");

         $tl_props->tid = $tid;
         if( !$tl_props->insert() )
            $this->create_error("TournamentTemplateLadder._createTournament.tl_props.insert(%s,$tid)");

         $t_ext = new TournamentExtension( $tid, TE_PROP_TLADDER_RANK_PERIOD_UPDATE, 0,
               TournamentUtils::get_month_start_time($NOW) );
         if( !$t_ext->persist() )
            $this->create_error("TournamentTemplateLadder._createTournament.t_ext.insert(%s,$tid)");
      }
      ta_end();

      return $tid;
   }

   function checkProperties( $tid )
   {
      $tl_props = TournamentLadderProps::load_tournament_ladder_props($tid);
      if( is_null($tl_props) )
         error('bad_tournament', "TournamentTemplateLadder.checkProperties($tid,{$this->uid})");

      $errors = $tl_props->check_properties();
      return $errors;
   }

   function checkParticipantRegistrations( $tid, $arr_TPs )
   {
      return TournamentLadder::check_participant_registrations( $tid, $arr_TPs );
   }

   function joinTournament( $tourney, $tp )
   {
      ta_begin();
      {//HOT-section to save TournamentParticipant and add user in ladder
         $result = $tp->persist();
         if( $tourney->Status == TOURNEY_STATUS_PLAY && $tp->Status == TP_STATUS_REGISTER )
            $result = TournamentLadder::add_user_to_ladder( $tp->tid, $tp->uid );
      }
      ta_end();
      return $result;
   }

} // end of 'TournamentTemplateLadder'

?>
