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

require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_utils.php';

 /*!
  * \file tournament_round_status.php
  *
  * \brief class with checks for different phases/status of tournament-round
  */


 /*!
  * \class TournamentRoundStatus
  *
  * \brief Class to help with precondition-checks for tournament-rounds
  */
class TournamentRoundStatus
{
   private $tid; // TournamentRound.tid
   private $tourney; // Tournament-object
   private $ttype; // specific typed TournamentTemplate-object
   private $tround; // TournamentRound-object
   private $Round; // TournamentRound.Round
   private $curr_status;
   private $new_status;
   private $errors; // arr
   private $warnings; // arr


   /*!
    * \brief Constructs TournamentRoundStatus.
    * \param $tid Tournament-object or tournament-id
    * \param $round TournamentRound-object or tournament-round
    */
   public function __construct( $tid, $round )
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
            'TournamentRoundStatus.construct.find_tournament', $this->tid );
      }
      if ( is_null($this->tourney) || (int)$this->tid <= 0 )
         error('unknown_tournament', "TournamentRoundStatus.construct.find_tournament2({$this->tid},{$this->Round})");

      $this->ttype = TournamentFactory::getTournament($this->tourney->WizardType);

      if ( $round instanceof TournamentRound )
      {
         $this->tround = $round;
         $this->Round = $this->tround->Round;
      }
      else
      {
         $this->Round = (int)$round;
         $this->tround = TournamentCache::load_cache_tournament_round(
            'TournamentRoundStatus.construct.find_tround', $this->tid, $this->Round );
      }

      $this->curr_status = $this->new_status = $this->tround->Status;
      $this->errors = array();
      $this->warnings = array();
   }//__construct

   public function get_tournament()
   {
      return $this->tourney;
   }

   public function get_tournament_round()
   {
      return $this->tround;
   }

   public function get_current_status()
   {
      return $this->curr_status;
   }

   public function get_new_status()
   {
      return $this->new_status;
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

   public function has_warning()
   {
      return (bool)count($this->warnings);
   }

   public function get_warnings()
   {
      return $this->warnings;
   }


   /*! \brief Checks change for new-status (writes also $this->errors). */
   public function check_round_status_change( $new_status )
   {
      $this->new_status = $new_status;

      if ( $this->curr_status != $new_status )
      {
         // expected status is: TROUND_STATUS_(INIT|POOL|PAIR|PLAY|DONE)
         $check_funcname = 'check_conditions_round_status_' . strtoupper($new_status);
         call_user_func( array( $this, $check_funcname ) );
      }
   }


   /*!
    * \brief Check if change to INIT-tourney-status is allowed.
    * \note Change to INIT-status is mostly done by admin to fix bad states.
    */
   public function check_conditions_round_status_INIT()
   {
      $this->errors[] = sprintf( T_('Change to Tournament Round Status [%s] only allowed by Tournament Admin.'),
                                 TournamentRound::getStatusText($this->new_status) );
   }


   /*!
    * \brief Check if change to POOL-tourney-status is allowed.
    * \note Normal allowed status-change is only INIT->POOL (checking round-props).
    */
   public function check_conditions_round_status_POOL()
   {
      $this->check_expected_round_status( TROUND_STATUS_INIT, TOURNEY_STATUS_PAIR );

      $this->errors = array_merge( $this->errors,
         $this->ttype->checkProperties( $this->tourney, TOURNEY_STATUS_PAIR ) );
   }


   /*!
    * \brief Check if change to PAIR-tourney-status is allowed.
    * \note Normal allowed status-change is only POOL->PAIR.
    */
   public function check_conditions_round_status_PAIR()
   {
      $this->check_expected_round_status( TROUND_STATUS_POOL, TOURNEY_STATUS_PAIR );

      $this->errors = array_merge( $this->errors,
         $this->ttype->checkPooling( $this->tourney, $this->tround ) );
   }


   /*!
    * \brief Check if change to PLAY-tourney-status is allowed.
    * \note Only allowed status-change is PAIR->PLAY, but is normally done automatically by pairing-editor.
    */
   public function check_conditions_round_status_PLAY()
   {
      $this->check_expected_round_status( TROUND_STATUS_PAIR, TOURNEY_STATUS_PAIR );

      $this->errors[] = T_('Status change normally done automatically by Pairing-Editor.#tourney') . ' '
         . sprintf( T_('Change to Tournament Round Status [%s] only allowed by Tournament Admin.'),
                    TournamentRound::getStatusText($this->new_status) );
   }


   /*!
    * \brief Check if change to DONE-tourney-status is allowed.
    * \note Normal allowed status-change is only PLAY->DONE,
    *       which is a precondition to change T-status or switch to next-round.
    */
   public function check_conditions_round_status_DONE()
   {
      $this->check_expected_round_status( TROUND_STATUS_PLAY, TOURNEY_STATUS_PLAY );

      // check that all started games are finished and processed
      $cnt_tgames = TournamentGames::count_tournament_games( $this->tid, $this->tround->ID );
      if ( $cnt_tgames > 0 )
         $this->errors[] = sprintf( T_('There are still %s unfinished tournament games in round %d.'),
            $cnt_tgames, $this->Round );

      list( $errors, $warnings ) = $this->ttype->checkPoolWinners( $this->tourney, $this->tround );
      if ( count($errors) )
         $this->errors = array_merge( $this->errors, $errors );
      if ( count($warnings) )
         $this->warnings = array_merge( $this->warnings, $warnings );
   }



   // Checks expected round- and tournament-status
   private function check_expected_round_status( $round_status, $t_status )
   {
      if ( $this->curr_status != $round_status || $this->tourney->Status != $t_status )
      {
         $this->errors[] = sprintf( T_('Expecting current round status [%s] and tournament status [%s] for change of round status to [%s]'),
            TournamentRound::getStatusText($round_status),
            Tournament::getStatusText($t_status),
            TournamentRound::getStatusText($this->new_status) );
      }
   }


   /*!
    * \brief Checks if current tournament-round-status allows certain action.
    * \param $errmsgfmt error-message-format expecting two args: 1. tourney-round-status, 2. expected status-list
    * \param $arr_status status-array or single status
    * \param $allow_admin if true, admin can do anything; otherwise admin is treated like non-admin
    * \return error-list; empty if no error
    */
   public function check_action_round_status( $errmsgfmt, $arr_status, $allow_admin=true )
   {
      $errors = array();
      if ( !is_array($arr_status) )
         $arr_status = array( $arr_status );

      // T-Admin can do anything at any time
      if ( $allow_admin && TournamentUtils::isAdmin() )
         $allow = true;
      else
         $allow = in_array($this->tround->Status, $arr_status);

      if ( !$allow )
      {
         $errors[] = sprintf( $errmsgfmt,
                              TournamentRound::getStatusText($this->tround->Status),
                              build_text_list( 'TournamentRound::getStatusText', $arr_status, ' | ' ) );
      }

      return $errors;
   }//check_action_round_status

   public function check_edit_round_status( $arr_status, $allow_admin=true )
   {
      return $this->check_action_round_status(
         T_('Edit is forbidden for tournament round on status [%s], only allowed for [%s] !'),
         $arr_status, $allow_admin );
   }

} // end of 'TournamentRoundStatus'

?>
