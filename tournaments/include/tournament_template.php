<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'tournaments/include/tournament_director.php';
require_once 'tournaments/include/tournament_utils.php';

 /*!
  * \file tournament_template.php
  *
  * \brief "Interface" / template-pattern for different Tournament-type-classes
  */

// bitmask for tournament-template title extra-parts
define('TOURNEY_TITLE_INVITE_ONLY',       0x0001);
define('TOURNEY_TITLE_ADMIN_ONLY',        0x0002);
define('TOURNEY_TITLE_NO_RESTRICTION',    0x0004);
define('TOURNEY_TITLE_GAME_RESTRICTION',  0x0008);


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

   public $need_rounds = false; // true = tournament has round-management; false = tournament has no rounds
   public $allow_register_tourney_status = array( TOURNEY_STATUS_REGISTER );
   public $allow_edit_tourney_status_td_adm_edit = array( TOURNEY_STATUS_NEW, TOURNEY_STATUS_REGISTER );
   public $allow_game_annul = false; // true = tournament-game can be annulled (=detached) by T-Admin or T-Director
   public $need_admin_create_tourney = true;
   public $showcount_tournament_standings = 0;
   public $limits;

   /*!
    * \brief Constructs template for different tournament-types.
    * \param $wizard_type TOURNEY_WIZTYPE_...
    * \param $title_main main title-part, used to build title
    * \param $title_extras bitmask of TOURNEY_TITLE_.. = additional extra-information including in title
    */
   protected function __construct( $wizard_type, $title_main, $title_extras )
   {
      global $player_row;
      $this->wizard_type = $wizard_type;
      $this->title = self::build_tournament_template_title( $title_main, $title_extras );
      $this->uid = (int)@$player_row['ID'];
      $this->limits = new TournamentLimits();
      $this->limits->setLimits( TLIMITS_MAX_TP, false, 2, TP_MAX_COUNT );
      $this->limits->setLimits( TLIMITS_TPR_RATING_USE_MODE, false, 0 );
      $this->limits->setLimits( TLIMITS_TRULE_GAME_UNRATED, false, 0 );
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

   /*! \brief Creates default Tournament object with given arguments. */
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

   /*! \brief Returns absolute max. limit of rounds for tournament. */
   public function getMaxRounds()
   {
      if ( $this->need_rounds )
         return $this->limits->getMaxLimit(TLIMITS_TRD_MAX_ROUNDS);
      else
         return 1;
   }

   /*! \brief Returns max-limit for TournamentProperties.MaxStartRound. */
   public function determineLimitMaxStartRound( $max_tp )
   {
      if ( $this->need_rounds )
      {
         if ( $max_tp <= 0 )
            $max_tp = TP_MAX_COUNT;
         $max_rounds = (int)( log($max_tp) / log(2) ); // max-estimate for round-count dep. on max-participants-count
         $max_limit = min( $this->getMaxRounds(), $max_rounds );
      }
      else // tournament without round-management
         $max_limit = 1;
      return $max_limit;
   }//determineLimitMaxStartRound

   /*! \brief Creates default tournament-director with only game-end-privilege (if not admin). */
   protected function create_default_tournament_director( $tid )
   {
      if ( !TournamentUtils::isAdmin() && $this->uid > GUESTS_ID_MAX )
      {
         $t_director = new TournamentDirector( $tid, $this->uid, TD_FLAG_GAME_END );
         if ( !$t_director->insert() )
            $this->create_error("TournamentTemplate.create_default_tournament_director.insert(%s,$tid)");
      }
   }//create_default_tournament_director


   // ---------- Interface ----------------------------------------

   /*! \brief Returns inserted Tournament.ID if successful; 0 otherwise. */
   abstract public function createTournament();

   abstract public function getDefaultPoolNamesFormat();

   /*!
    * \brief Returns calculated min-participants required for specific tournament-type.
    * \param $tround TournamentRound, can be null
    */
   public function calcTournamentMinParticipants( $tprops, $tround )
   {
      return $tprops->MinParticipants;
   }

   /*!
    * \brief Returns list with errors from checking tournament-type-specific properties for specific target-tourney-status; empty if ok.
    * \note Used on tournament-status and tournament-round-status changes.
    *
    * \note IMPORTANT NOTE: implementation should decide, if it is allowed to have TPs with APPLY/INVITE-status!
    */
   abstract public function checkProperties( $tourney, $t_status );

   /*!
    * \brief Returns list with errors from checking pooling for tournament; empty if ok.
    * \param $round integer tournament-round or TournamentRound-object
    */
   abstract public function checkPooling( $tourney, $round );

   abstract public function checkParticipantRegistrations( $tid, $arr_TPs );

   abstract public function checkGamesStarted( $tid );

   /*!
    * \brief Returns list with warnings and errors from checking if pool-winners are set correctly after all pool-games
    *        for a round have been finished.
    * \param $tround TournamentRound-object
    * \return array( errors, warnings )
    */
   abstract public function checkPoolWinners( $tourney, $tround );

   /*!
    * \brief Saves given TournamentParticipant in HOT-section and joins (running) tournament if not already joined.
    * \param $tlog_type for tournament-log TLOG_TYPE_... who executed the tournament-join
    */
   public function joinTournament( $tourney, $tp, $tlog_type )
   {
      return $tp->persist();
   }

   /*!
    * \brief Returns list with warnings and errors from checking if tournament can be closed (=finished).
    * \return array( errors, warnings )
    */
   abstract public function checkClosingTournament( $tourney );


   // ------------ static functions ----------------------------

   // see _construct() for arguments
   private static function build_tournament_template_title( $main_part, $extras )
   {
      $out = array();
      if ( $extras & TOURNEY_TITLE_INVITE_ONLY )
         $out[] = T_('invite-only#ttype');
      if ( $extras & TOURNEY_TITLE_ADMIN_ONLY )
         $out[] = T_('only for admin#ttype');
      if ( $extras & TOURNEY_TITLE_NO_RESTRICTION )
         $out[] = T_('no restrictions#ttype');
      if ( $extras & TOURNEY_TITLE_GAME_RESTRICTION )
         $out[] = T_('with game restrictions#ttype');
      return ( count($out) == 0 ) ? $main_part : sprintf('%s (%s)', $main_part, implode(', ', $out) );
   }//build_tournament_template_title

} // end of 'TournamentTemplate'

?>
