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

require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_limits.php';

 /*!
  * \file tournament_template.php
  *
  * \brief "Interface" / template-pattern for different Tournament-type-classes
  */


 /*!
  * \class TournamentTemplate
  *
  * \brief Template for different tournament-types
  */
abstract class TournamentTemplate
{
   public $wizard_type;
   public $title;
   public $uid;

   // tournament-type-specific properties

   public $need_rounds = false;
   public $allow_register_tourney_status = array( TOURNEY_STATUS_REGISTER );
   public $need_admin_create_tourney = true;
   public $showcount_tournament_standings = 0;
   public $limits;

   /*! \brief Constructs template for different tournament-types. */
   protected function __construct( $wizard_type, $title )
   {
      global $player_row;
      $this->wizard_type = $wizard_type;
      $this->title = $title;
      $this->uid = (int)@$player_row['ID'];
      $this->limits = new TournamentLimits();
      $this->limits->setLimits( TLIMITS_MAX_TP, false, 2, TP_MAX_COUNT );
   }

   public function getTournamentLimits()
   {
      return $this->limits;
   }

   public function to_string()
   {
      return print_r( $this, true );
   }

   public function create_error( $msgfmt )
   {
      error('tournament_create_error', sprintf( $msgfmt, $this->uid ) );
   }

   /*! \brief Creates Tournament object with given arguments. */
   public function make_tournament( $scope, $title )
   {
      $tourney = new Tournament();
      $tourney->setScope( $scope );
      $tourney->setWizardType( $this->wizard_type );
      $tourney->Title = $title;
      $tourney->Owner_ID = $this->uid;
      return $tourney;
   }

   /*! \brief Returns number of standings to show for tournament. */
   public function getCountTournamentStandings( $t_status )
   {
      return ( $t_status == TOURNEY_STATUS_PLAY || $t_status == TOURNEY_STATUS_CLOSED )
         ? $this->showcount_tournament_standings : 0;
   }


   // ---------- Interface ----------------------------------------

   /*! \brief Returns inserted Tournament.ID if successful; 0 otherwise. */
   abstract public function createTournament();

   /*! \brief Returns calculated min-participants required for specific tournament-type. */
   public function calcTournamentMinParticipants( $tprops, $tround=null )
   {
      return $tprops->MinParticipants;
   }

   /*! \brief Returns list with errors from checking tournament-type-speficic properties for specific target-tourney-status; empty if ok. */
   abstract public function checkProperties( $tourney, $t_status );

   /*! \brief Returns list with errors from checking pooling for tournament; empty if ok. */
   abstract public function checkPooling( $tourney, $round );

   abstract public function checkParticipantRegistrations( $tid, $arr_TPs );

   abstract public function checkGamesStarted( $tid );

   /*! \brief Saves given TournamentParticipant in HOT-section and joins (running) tournament if not already joined. */
   public function joinTournament( $tourney, $tp )
   {
      return $tp->persist();
   }


   // ------------ static functions ----------------------------

} // end of 'TournamentTemplate'

?>
