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

require_once 'include/std_classes.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_director.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_utils.php';

 /*!
  * \file tournament_status.php
  *
  * \brief class with checks for different phases/status of tournaments
  */


 /*!
  * \class TournamentStatus
  *
  * \brief Class to help with precondition-checks for tournaments
  */
class TournamentStatus
{
   private $tid; // Tournament.ID
   private $tourney; // Tournament-object
   private $ttype; // specific typed TournamentTemplate-object
   private $tprops; // TournamentProperties-object
   private $tround; // TournamentRound-object
   private $curr_status; // old Tournament.Status
   private $new_status; // new Tournament.Status
   private $errors; // arr


   /*!
    * \brief Constructs TournamentStatus.
    * \param $tid Tournament-object or tournament-id
    */
   public function __construct( $tid )
   {
      if ( $tid instanceof Tournament )
      {
         $this->tourney = $tid;
         $this->tid = $this->tourney->ID;
      }
      elseif ( is_numeric($tid) && $tid > 0 )
      {
         $this->tid = (int)$tid;
         $this->tourney = TournamentCache::load_cache_tournament(
            'TournamentStatus.constructfind_tournament.find_tournament', $this->tid );
      }
      if ( is_null($this->tourney) || (int)$this->tid <= 0 )
         error('unknown_tournament', "TournamentStatus.construct.find_tournament2({$this->tid})");

      $this->ttype = TournamentFactory::getTournament($this->tourney->WizardType);
      $this->tprops = null;
      $this->tround = null;

      $this->curr_status = $this->new_status = $this->tourney->Status;
      $this->errors = array();
   }

   public function get_tournament()
   {
      return $this->tourney;
   }

   public function has_error()
   {
      return (bool)count($this->errors);
   }

   public function get_errors()
   {
      return $this->errors;
   }

   public function add_error( $str )
   {
      if ( $str )
         $this->errors[] = $str;
   }

   public function get_current_status()
   {
      return $this->curr_status;
   }

   public function get_new_status()
   {
      return $this->new_status;
   }

   /*! \internal to load TournamentProperties. */
   private function _load_tprops()
   {
      if ( is_null($this->tprops) )
         $this->tprops = TournamentCache::load_cache_tournament_properties( 'TournamentStatus.find_tprops', $this->tid );
   }

   /*! \internal to load TournamentRound (if needed). */
   private function _load_tround()
   {
      if ( is_null($this->tround) && $this->ttype->need_rounds )
      {
         $round = $this->tourney->CurrentRound;
         $this->tround = TournamentCache::load_cache_tournament_round( 'TournamentStatus.find_tround', $this->tid, $round );
      }
   }

   /*! \brief Checks change for new-status (writes also $this->errors). */
   public function check_status_change( $new_status )
   {
      $this->new_status = $new_status;

      if ( $this->curr_status != $new_status )
      {
         // expected status is: TOURNEY_STATUS_(ADM|NEW|REG|PAIR|PLAY|CLOSED|DEL)
         $check_funcname = 'check_conditions_status_' . strtoupper($new_status);
         call_user_func( array( $this, $check_funcname ) );
      }
   }



   /*!
    * \brief Check if change to ADM-tourney-status is allowed.
    * \note Change to ADM-status is mostly done to take tournament completly out of the loop.
    *       It should be documented somewhere what the last status was to be able to reactivate it,
    *       because the status before the change could be any status.
    */
   public function check_conditions_status_ADM()
   {
      $this->errors[] = sprintf( T_('Change on Tournament Status [%s] only allowed by Tournament Admin.'),
                                 Tournament::getStatusText($this->new_status) );
   }


   /*!
    * \brief Check if change to NEW-tourney-status is allowed.
    * \note Change to NEW-status can normally happen to re-activate a tournament from ADM-status or
    *       for a finished or deleted and resetted tournament.
    */
   public function check_conditions_status_NEW()
   {
      $this->errors[] = sprintf( T_('Change on Tournament Status [%s] only allowed by Tournament Admin.'),
                                 Tournament::getStatusText($this->new_status) );
   }


   /*!
    * \brief Check if change to REG-tourney-status is allowed.
    * \note Normal allowed status-change is only NEW-to-REG.
    */
   public function check_conditions_status_REG()
   {
      $this->check_expected_status( TOURNEY_STATUS_NEW );
      $this->check_basic_conditions_status_change();

      if ( $this->tourney->CurrentRound > 1 )
         $this->errors[] = sprintf( T_('Tournament-Status [%s] is only allowed for first round.'),
            Tournament::getStatusText(TOURNEY_STATUS_REGISTER) );

      // check tournament-type specific checks
      $this->errors = array_merge( $this->errors,
         $this->ttype->checkProperties( $this->tourney, TOURNEY_STATUS_REGISTER ) );
   }


   /*!
    * \brief Check if change to PAIR-tourney-status is allowed.
    * \note Normal allowed status-change is only REG-to-PAIR.
    */
   public function check_conditions_status_PAIR()
   {
      global $base_path;

      //TODO TODO T-stat-chg *->PAIR: Set-Round may automatically change T-status PLAY->PAIR (for next-round), but as fallback manual change may be needed (e.g. by admin (perhaps for TD as well));; be careful with this though, because some config checked here may be round-specific (setup after current-round is set !?)
      $this->check_expected_status( TOURNEY_STATUS_REGISTER );
      $this->check_basic_conditions_status_change();

      //TODO TODO T-stat-chg *->PAIR: check for higher rounds must be done separately, but where?

      // check min/max participants-count for current round on TP.NextRound
      $curr_round = $this->tourney->CurrentRound;
      $this->_load_tprops();
      $this->_load_tround();
      $min_participants = $this->ttype->calcTournamentMinParticipants( $this->tprops, $this->tround );
      $max_participants = $this->tprops->getMaxParticipants();
      $tp_reg_count = TournamentParticipant::count_tournament_participants(
         $this->tid, TP_STATUS_REGISTER, $curr_round, /*NextR*/true );
      if ( $min_participants > 0 && $tp_reg_count < $min_participants )
      {
         $this->errors[] = sprintf(
            T_('Tournament min. participant limit (%s users) for round %s has not been reached yet: %s registrations are missing.'),
            $min_participants, $curr_round, $min_participants - $tp_reg_count );
      }
      if ( $curr_round == 1 && $tp_reg_count > $max_participants ) // no MAX-TP-check for higher rounds
         $this->errors[] = sprintf( T_('Tournament max. participant limit (%s users) has been exceeded by %s registrations.'),
            $max_participants, $tp_reg_count - $max_participants );

      // check basic restrictions for all REGISTERED users for ALL rounds 1..n on TP.StartRound.
      // (checks on TP.NextRound for higher rounds must be done separately)
      $iterator = new ListIterator( 'TournamentStatus.check_conditions_status_PAIR',
            new QuerySQL( SQLP_WHERE, "TP.Status='".TP_STATUS_REGISTER."'" )); // only registered TPs
      $iterator = TournamentParticipant::load_tournament_participants($iterator, $this->tid);
      $err_count = $limit_err_count = 50; // check all, but limit error-output to some users
      while ( list(,$arr_item) = $iterator->getListIterator() )
      {
         list( $tp, $orow ) = $arr_item;
         list( $reg_errors, $reg_warnings ) =
            $this->tprops->checkUserRegistration( $this->tourney, $tp, $tp->User, TCHKTYPE_TD );
         foreach ( $reg_errors as $err )
         {
            $err_link = anchor( $base_path."tournaments/edit_participant.php?tid={$this->tid}".URI_AMP."uid={$tp->uid}", $tp->User->Handle );
            $this->errors[] = make_html_safe( sprintf( T_('Error for user %s'), "[$err_link]"), 'line') . ": $err";
            if ( $err_count-- <= 0 )
               break;
         }
         if ( $err_count < 0 )
            break;
      }
      if ( $err_count < 0 )
         $this->errors[] = sprintf( T_('More than %s errors occured, so output was limited.#tourney'), $limit_err_count );

      // check tournament-type specific checks
      $this->errors = array_merge( $this->errors,
         $this->ttype->checkProperties( $this->tourney, TOURNEY_STATUS_PAIR ) );
   }//check_conditions_status_PAIR


   /*!
    * \brief Check if change to PLAY-tourney-status is allowed.
    * \note Normal allowed status-change is only PAIR-to-PLAY.
    */
   public function check_conditions_status_PLAY()
   {
      $this->check_expected_status( TOURNEY_STATUS_PAIR );
      $this->check_basic_conditions_status_change();

      // check that all registered TPs are added
      $arr_TPs = TournamentParticipant::load_tournament_participants_registered( $this->tid, $this->tourney->CurrentRound );
      $check_errors = $this->ttype->checkParticipantRegistrations( $this->tid, $arr_TPs );
      if ( count($check_errors) )
         $this->errors = array_merge( $this->errors, $check_errors );

      // check that all games have been started
      $this->errors = array_merge( $this->errors,
         $this->ttype->checkGamesStarted( $this->tid ) );
   }


   /*!
    * \brief Check if change to CLOSED-tourney-status is allowed.
    * \note Normal allowed status-change is only PLAY-to-CLOSED.
    */
   public function check_conditions_status_CLOSED()
   {
      $this->check_expected_status( TOURNEY_STATUS_PLAY );
      $this->check_basic_conditions_status_change();

      $this->check_conditions_unfinished_tourney_games();

      //TODO TODO T-stat-chg *->CLOSED: check for tournament-results
   }


   /*!
    * \brief Check if change to DEL-tourney-status is allowed.
    * \note This could happen if a tournament is discontinued (normally done by admin, but allowed for TD as well).
    */
   public function check_conditions_status_DEL()
   {
      //TODO TODO T-stat-chg *->DEL: are these the only conditions to finish a T !?
      $this->check_conditions_unfinished_tourney_games();
   }



   /*! \brief Checks basic conditions for change of tourney-status: title/description/TD. */
   public function check_basic_conditions_status_change()
   {
      if ( strlen($this->tourney->Title) < 8 )
         $this->errors[] = T_('Tournament title missing or too short');
      if ( strlen($this->tourney->Description) < 4 )
         $this->errors[] = T_('Tournament description missing or too short');
      if ( !TournamentDirector::has_tournament_director($this->tid) )
         $this->errors[] = T_('Missing at least one tournament director');
   }

   /*! \brief Checks if there are unfinished tourney-games that can prohibit tourney-status-change. */
   public function check_conditions_unfinished_tourney_games()
   {
      // check for not-DONE T-games
      $tg_count_running = TournamentGames::count_tournament_games( $this->tid );
      if ( $tg_count_running > 0 )
      {
         $this->errors[] = sprintf( T_('Tournament has %s unfinished tournament games, that must be ended first.'),
            $tg_count_running );
      }
   }


   private function check_expected_status( $arr_status )
   {
      if ( !is_array($arr_status) )
         $arr_status = array( $arr_status );

      if ( !in_array( $this->curr_status, $arr_status ) )
      {
         $this->errors[] = sprintf( T_('Expecting current Tournament Status [%s] for change to status [%s]'),
            build_text_list( 'Tournament::getStatusText', $arr_status, ' | ' ),
            Tournament::getStatusText($this->new_status) );
      }
   }//check_expected_status

   /*!
    * \brief Checks if current tournament-status allows certain action.
    * \param $errmsgfmt error-message-format expecting two args: 1. tourney-status, 2. expected status-list
    * \param $arr_status status-array
    * \param $allow_admin if true, admin can do anything; otherwise admin is treated like non-admin
    * \return error-list; empty if no error
    */
   private function check_action_status( $errmsgfmt, $arr_status, $allow_admin=true )
   {
      $errors = array();

      // T-Admin can do anything at any time
      if ( $allow_admin && TournamentUtils::isAdmin() )
         $allow = true;
      else
         $allow = in_array($this->tourney->Status, $arr_status);

      if ( !$allow )
      {
         $errors[] = sprintf( $errmsgfmt,
                              Tournament::getStatusText($this->tourney->Status),
                              build_text_list( 'Tournament::getStatusText', $arr_status, ' | ' ) );
      }

      return $errors;
   }//check_action_status

   public function check_edit_status( $arr_status, $allow_admin=true )
   {
      return $this->check_action_status(
         T_('Edit is forbidden for tournament on status [%s], only allowed for [%s] !'),
         $arr_status, $allow_admin );
   }

   public function check_view_status( $arr_status, $allow_admin=true )
   {
      return $this->check_action_status(
         T_('View of tournament is forbidden on status [%s], only allowed for [%s] !'),
         $arr_status, $allow_admin );
   }

} // end of 'TournamentStatus'

?>
