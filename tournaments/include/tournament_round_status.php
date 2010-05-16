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

require_once 'tournaments/include/tournament_factory.php';
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
   var $tid; // TournamentRound.tid
   var $is_admin;
   var $tourney; // Tournament-object
   var $ttype; // specific typed TournamentTemplate-object
   var $tround; // TournamentRound-object
   var $Round; // TournamentRound.Round
   var $old_status;
   var $new_status;
   var $errors; // arr


   /*!
    * \brief Constructs TournamentRoundStatus.
    * \param $tid Tournament-object or tournament-id
    * \param $round TournamentRound-object or tournament-round
    */
   function TournamentRoundStatus( $tid, $round )
   {
      if( is_a($tid, 'Tournament') )
      {
         $this->tourney = $tid;
         $this->tid = $this->tourney->ID;
      }
      elseif( is_numeric($tid) && $tid > 0 )
      {
         $this->tid = (int)$tid;
         $this->tourney = Tournament::load_tournament( $this->tid );
      }
      if( is_null($this->tourney) || (int)$this->tid <= 0 )
         error('unknown_tournament', "TournamentRoundStatus.find_tournament({$this->tid},{$this->Round})");

      $this->ttype = TournamentFactory::getTournament($this->tourney->WizardType);

      if( is_a($round, 'TournamentRound') )
      {
         $this->tround = $round;
         $this->Round = $this->tround->Round;
      }
      else
      {
         $this->Round = (int)$round;
         $this->tround = TournamentRound::load_tournament_round( $this->tid, $this->Round );
         if( is_null($this->tround) )
            error('bad_tournament', "TournamentRoundStatus.find_tround({$this->tid},{$this->Round})");
      }

      $this->is_admin = TournamentUtils::isAdmin();
      $this->curr_status = $this->new_status = $this->tround->Status;
      $this->errors = array();
   }

   function has_error()
   {
      return (bool)count($this->errors);
   }

   function add_error( $str )
   {
      if( $str )
         $this->errors[] = $str;
   }


   /*! \brief Checks change for new-status (writes also $this->errors). */
   function check_status_change( $new_status )
   {
      $this->errors = array();
      $this->new_status = $new_status;

      if( $this->curr_status != $new_status )
      {
         // expected status is: TROUND_STATUS_(INIT|POOL|PAIR|PLAY|DONE)
         $check_funcname = 'check_conditions_status_' . strtoupper($new_status);
         call_user_func( array( $this, $check_funcname ) );
      }
   }

   /*! \brief Check if change to INIT-tourney-status is allowed. */
   function check_conditions_status_INIT()
   {
      $this->errors[] = sprintf( T_('Change to Tournament Round Status [%s] only allowed by Tournament Admin.'),
                                 TournamentRound::getStatusText($this->new_status) );
   }

   /*! \brief Check if change to POOL-tourney-status is allowed. */
   function check_conditions_status_POOL()
   {
      if( $this->curr_status != TROUND_STATUS_INIT )
         $this->errors[] = $this->error_expected_status( TROUND_STATUS_INIT );

      $check_errors = $this->ttype->checkProperties( $this->tourney, TOURNEY_STATUS_PAIR );
      if( count($check_errors) )
         $this->errors = array_merge( $this->errors, $check_errors );
   }

   /*! \brief Check if change to PAIR-tourney-status is allowed. */
   function check_conditions_status_PAIR()
   {
      if( $this->curr_status != TROUND_STATUS_POOL )
         $this->errors[] = $this->error_expected_status( TROUND_STATUS_POOL );

      $check_errors = $this->ttype->checkPooling( $this->tourney, $this->tround );
      if( count($check_errors) )
         $this->errors = array_merge( $this->errors, $check_errors );
   }

   /*! \brief Check if change to PLAY-tourney-status is allowed. */
   function check_conditions_status_PLAY()
   {
      if( $this->curr_status != TROUND_STATUS_PAIR )
         $this->errors[] = $this->error_expected_status( TROUND_STATUS_PAIR );

      //TODO
      $this->errors[] = 'status-transition not implemented yet';
   }

   /*! \brief Check if change to DONE-tourney-status is allowed. */
   function check_conditions_status_DONE()
   {
      if( $this->curr_status != TROUND_STATUS_PLAY )
         $this->errors[] = $this->error_expected_status( TROUND_STATUS_PLAY );

      //TODO
      $this->errors[] = 'status-transition not implemented yet';
   }


   function error_expected_status( $status )
   {
      if( is_array($status) )
      {
         $arr = array();
         foreach( $status as $s )
            $arr[] = TournamentRound::getStatusText($s);
         $status_str = implode('|', $arr);
      }
      else
         $status_str = TournamentRound::getStatusText($status);

      return sprintf( T_('Expecting current tournament round status [%s] for change to status [%s]'),
                      $status_str,
                      TournamentRound::getStatusText($this->new_status) );
   }


   /*!
    * \brief Checks if current tournament-round-status allows certain action.
    * \param $errmsgfmt error-message-format expecting two args: 1. tourney-round-status, 2. expected status-list
    * \param $arr_status status-array or single status
    * \param $allow_admin if true, admin can do anything; otherwise admin is treated like non-admin
    * \return error-list; empty if no error
    */
   function check_action_status( $errmsgfmt, $arr_status, $allow_admin=true )
   {
      $errors = array();
      if( !is_array($arr_status) )
         $arr_status = array( $arr_status );

      // T-Admin can do anything at any time
      if( $allow_admin && $this->is_admin )
         $allow = true;
      else
         $allow = in_array($this->tround->Status, $arr_status);

      if( !$allow )
      {
         $arrst = array();
         foreach( $arr_status as $status )
            $arrst[] = TournamentRound::getStatusText($status);

         $errors[] = sprintf( $errmsgfmt,
                              TournamentRound::getStatusText($this->tround->Status),
                              implode('|', $arrst) );
      }

      return $errors;
   }//check_action_status

   function check_edit_status( $arr_status, $allow_admin=true )
   {
      return $this->check_action_status(
         T_('Edit is forbidden for tournament round on status [%s], only allowed for [%s] !'),
         $arr_status, $allow_admin );
   }

} // end of 'TournamentRoundStatus'

?>
