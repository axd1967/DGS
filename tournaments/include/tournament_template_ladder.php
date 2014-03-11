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

//$TranslateGroups[] = "Tournament";

require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_template.php';
require_once 'tournaments/include/tournament_log_helper.php';

require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_extension.php';
require_once 'tournaments/include/tournament_ladder.php';
require_once 'tournaments/include/tournament_ladder_props.php';

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
abstract class TournamentTemplateLadder extends TournamentTemplate
{
   protected function __construct( $wizard_type, $title_main, $title_extras )
   {
      parent::__construct( $wizard_type, $title_main, $title_extras );

      // overwrite tournament-type-specific properties
      $this->allow_register_tourney_status = array( TOURNEY_STATUS_REGISTER, TOURNEY_STATUS_PLAY );
      $this->showcount_tournament_standings = 5;
      $this->limits->setLimits( TLIMITS_TL_MAX_DF, false, 0, TLADDER_MAX_DEFENSES );
      $this->limits->setLimits( TLIMITS_TL_MAX_CH, false, 1, TLADDER_MAX_CHALLENGES );
   }

   /*!
    * \brief Function to persist create tournament-tables.
    * \internal
    */
   protected function _createTournament( $tourney, $tprops, $t_rules, $tl_props )
   {
      global $NOW;

      ta_begin();
      {//HOT-section to create various tables for new tournament
         // check args
         if ( !($tourney instanceof Tournament) )
            $this->create_error("TournamentTemplateLadder._createTournament.tourney.check(%s)");
         if ( !($tprops instanceof TournamentProperties) )
            $this->create_error("TournamentTemplateLadder._createTournament.tprops.check(%s)");
         if ( !($t_rules instanceof TournamentRules) )
            $this->create_error("TournamentTemplateLadder._createTournament.t_rules.check(%s)");
         if ( !($tl_props instanceof TournamentLadderProps) )
            $this->create_error("TournamentTemplateLadder._createTournament.tl_props.check(%s)");

         // insert tournament-related tables
         if ( !$tourney->persist() )
            $this->create_error("TournamentTemplateLadder._createTournament.tourney.insert(%s)");
         $tid = $tourney->ID;

         $tprops->tid = $tid;
         if ( !$tprops->insert() )
            $this->create_error("TournamentTemplateLadder._createTournament.tprops.insert(%s,$tid)");

         $t_rules->tid = $tid;
         if ( !$t_rules->insert() )
            $this->create_error("TournamentTemplateLadder._createTournament.t_rules.insert(%s,$tid)");

         $tl_props->tid = $tid;
         if ( !$tl_props->insert() )
            $this->create_error("TournamentTemplateLadder._createTournament.tl_props.insert(%s,$tid)");

         $t_ext = new TournamentExtension( $tid, TE_PROP_TLADDER_RANK_PERIOD_UPDATE, 0,
               TournamentUtils::get_month_start_time($NOW, 1) ); // start with next month
         if ( !$t_ext->persist() )
            $this->create_error("TournamentTemplateLadder._createTournament.t_ext.insert(%s,$tid)");

         TournamentLogHelper::log_create_tournament( $tid, $tourney->WizardType, $tourney->Title );
      }
      ta_end();

      return $tid;
   }//_createTournament

   public function checkProperties( $tourney, $t_status )
   {
      $tid = $tourney->ID;
      $errors = array();

      if ( $t_status == TOURNEY_STATUS_REGISTER )
      {
         $tl_props = TournamentCache::load_cache_tournament_ladder_props( "TournamentTemplateLadder.checkProperties({$this->uid})", $tid );
         $errors = $tl_props->check_properties();
      }

      // IMPORTANT NOTE for $t_status == PAIR: TPs on APPLY/INVITE-status are allowed!

      return $errors;
   }//checkProperties

   public function checkPooling( $tourney, $round )
   {
      return array(); // no check for ladder
   }

   public function checkParticipantRegistrations( $tid, $arr_TPs )
   {
      return TournamentLadder::check_participant_registrations( $tid, $arr_TPs );
   }

   public function checkGamesStarted( $tid )
   {
      return array(); // games need not be started
   }

   public function checkPoolWinners( $tourney, $tround )
   {
      return array( array(), array() ); // no pools or rounds (only one) for ladders
   }

   public function joinTournament( $tourney, $tp, $tlog_type )
   {
      ta_begin();
      {//HOT-section to save TournamentParticipant and add user in ladder
         $result = $tp->persist();
         if ( $tourney->Status == TOURNEY_STATUS_PLAY && $tp->Status == TP_STATUS_REGISTER )
            $result = TournamentLadder::add_user_to_ladder( $tp->tid, $tp->uid, $tlog_type );
      }
      ta_end();
      return $result;
   }

} // end of 'TournamentTemplateLadder'

?>
