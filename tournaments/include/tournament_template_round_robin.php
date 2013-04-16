<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_pool_classes.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_log_helper.php';

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
abstract class TournamentTemplateRoundRobin extends TournamentTemplate
{
   protected function __construct( $wizard_type, $title )
   {
      parent::__construct( $wizard_type, $title );

      // overwrite tournament-type-specific properties
      $this->need_rounds = true;
      $this->allow_register_tourney_status = array( TOURNEY_STATUS_REGISTER );
      $this->limits->setLimits( TLIMITS_TRD_MAX_ROUNDS, false, 1, TROUND_MAX_COUNT );
      $this->limits->setLimits( TLIMITS_TRD_MIN_POOLSIZE, false, 2, TROUND_MAX_POOLSIZE );
      $this->limits->setLimits( TLIMITS_TRD_MAX_POOLSIZE, false, 2, TROUND_MAX_POOLSIZE );
      $this->limits->setLimits( TLIMITS_TRD_MAX_POOLCOUNT, true, 1, TROUND_MAX_POOLCOUNT );
   }

   /*!
    * \brief Function to persist create tournament-tables.
    * \internal
    */
   protected function _createTournament( $tourney, $tprops, $trules, $tround )
   {
      global $NOW;

      ta_begin();
      {//HOT-section to create various tables for new tournament
         // check args
         if( !($tourney instanceof Tournament) )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tourney.check(%s)");
         if( !($tprops instanceof TournamentProperties) )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.tprops.check(%s)");
         if( !($trules instanceof TournamentRules) )
            $this->create_error("TournamentTemplateRoundRobin._createTournament.trules.check(%s)");
         if( !($tround instanceof TournamentRound) )
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

         TournamentLogHelper::log_create_tournament( $tid, $tourney->WizardType, $tourney->Title );
      }
      ta_end();

      return $tid;
   }//_createTournament

   public function calcTournamentMinParticipants( $tprops, $tround=null )
   {
      return max( $tprops->MinParticipants, $tround->MinPoolSize );
   }

   public function checkProperties( $tourney, $t_status )
   {
      $tid = $tourney->ID;
      $round = $tourney->CurrentRound;
      $errors = array();

      $tround = TournamentCache::load_cache_tournament_round( 'TournamentTemplateRoundRobin.checkProperties', $tid, $round );
      if( $t_status == TOURNEY_STATUS_REGISTER || $t_status == TOURNEY_STATUS_PAIR )
         $errors = array_merge( $errors, $tround->check_properties() );

      //TODO TODO check, that there are enough TPs, taking care users of with TPs.StartRound > 1

      return $errors;
   }//checkProperties

   public function checkPooling( $tourney, $round )
   {
      $tid = $tourney->ID;

      if( $round instanceof TournamentRound )
      {
         $tround = $round;
         $round = $tround->Round;
      }
      else
         $tround = TournamentCache::load_cache_tournament_round( 'TournamentTemplateRoundRobin.checkPooling', $tid, $round );

      list( $check_errors, $arr_pool_summary ) = TournamentPool::check_pools( $tround );
      return $check_errors;
   }//checkPooling

   public function checkParticipantRegistrations( $tid, $arr_TPs )
   {
      // see also tournament_status.php:
      // - no check for round-robin-tourneys, because participants already seeded in pools
      return array();
   }

   public function checkGamesStarted( $tid )
   {
      // see also tournament_status.php
      $errors = array();

      $tourney = TournamentCache::load_cache_tournament( 'TournamentTemplateRoundRobin.checkGamesStarted.find_tourney', $tid );
      $round = $tourney->CurrentRound;

      $tround = TournamentCache::load_cache_tournament_round( 'TournamentTemplateRoundRobin.checkGamesStarted', $tid, $round );
      if( $tround->Status != TROUND_STATUS_PLAY )
         $errors[] = sprintf( T_('Expecting current tournament round status [%s] for status-change'),
                              TournamentRound::getStatusText(TROUND_STATUS_PLAY) );

      // check, that all T-games are started for current round
      $expected_games = TournamentPool::count_tournament_pool_games( $tid, $round );
      $count_tgames = TournamentGames::count_tournament_games( $tid, $tround->ID, array() );
      $count_games_started = TournamentGames::count_games_started( $tid, $tround->ID );

      if( $count_tgames != $expected_games )
         $errors[] = sprintf( T_('Expected %s tournament-games (TG), but found %s games: Start remaining games first!'),
            $expected_games, $count_tgames );
      if( $count_games_started != $expected_games )
         $errors[] = sprintf( T_('Expected %s games (G), but found %s games: Contact tournament-admin for fix!'),
            $expected_games, $count_games_started );

      return $errors;
   }//checkGamesStarted

} // end of 'TournamentTemplateRoundRobin'

?>
