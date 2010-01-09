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

require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_director.php';
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
   var $tid; // Tournament.ID
   var $is_admin;
   var $tourney; // Tournament-object
   var $curr_status; // old Tournament.Status
   var $new_status; // new Tournament.Status
   var $errors; // arr


   /*!
    * \brief Constructs TournamentStatus.
    * \param $tid Tournament-object or tournament-id
    */
   function TournamentStatus( $tid )
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
         error('unknown_tournament', "TournamentStatus.find_tournament($tid)");

      $this->is_admin = TournamentUtils::isAdmin();
      $this->curr_status = $this->new_status = $this->tourney->Status;
      $this->errors = array();
   }

   function has_error()
   {
      return (bool)count($this->errors);
   }

   /*! \brief Checks change for new-status (writes also $this->errors). */
   function check_status_change( $new_status )
   {
      $this->errors = array();
      $this->new_status = $new_status;

      if( $this->curr_status != $new_status )
      {
         // expected status is: TOURNEY_STATUS_(ADM|NEW|REG|PAIR|PLAY|CLOSED|DEL)
         $check_funcname = 'check_conditions_status_' . strtoupper($new_status);
         call_user_func( array( $this, $check_funcname ) );
      }
   }

   function check_conditions_status_ADM()
   {
      $this->errors[] = sprintf( T_('Change to Tournament status [%s] only allowed by Tournament Admin.'),
                                 Tournament::getStatusText($this->new_status) );
   }

   function check_conditions_status_NEW()
   {
      $this->errors[] = sprintf( T_('Change to Tournament status [%s] only allowed by Tournament Admin.'),
                                 Tournament::getStatusText($this->new_status) );
   }

   function check_conditions_status_REG()
   {
      if( $this->curr_status != TOURNEY_STATUS_NEW )
         $this->errors[] = $this->error_expected_status( TOURNEY_STATUS_NEW );

      if( strlen($this->tourney->Title) < 8 )
         $this->errors[] = T_('Tournament title missing or too short');
      if( strlen($this->tourney->Description) < 4 )
         $this->errors[] = T_('Tournament description missing or too short');

      if( !TournamentDirector::has_tournament_director($this->tid) )
         $this->errors[] = T_('Missing at least one tournament director');

      //TODO condition: no contradicting settings (rules-rated <-> reg-prop-user-rating )
   }

   function check_conditions_status_PAIR()
   {
      if( $this->curr_status != TOURNEY_STATUS_REGISTER )
         $this->errors[] = $this->error_expected_status( TOURNEY_STATUS_REGISTER );

      //TODO condition: T-props must be fulfilled
      $this->errors[] = 'not_implemented_yet';
   }

   function check_conditions_status_PLAY()
   {
      if( $this->curr_status != TOURNEY_STATUS_PAIR )
         $this->errors[] = $this->error_expected_status( TOURNEY_STATUS_PAIR );

      //TODO condition: all T-games must be ready to start (i.e. inserted and set up)
      $this->errors[] = 'not_implemented_yet';
   }

   function check_conditions_status_CLOSED()
   {
      if( $this->curr_status != TOURNEY_STATUS_PLAY )
         $this->errors[] = $this->error_expected_status( TOURNEY_STATUS_PLAY );

      //TODO condition: no running T-game
      $this->errors[] = 'not_implemented_yet';
   }

   function check_conditions_status_DEL()
   {
      //TODO condition: no running T-game
      $this->errors[] = 'not_implemented_yet';
   }


   function error_expected_status( $status )
   {
      if( is_array($status) )
      {
         $arr = array();
         foreach( $status as $s )
            $arr[] = Tournament::getStatusText($s);
         $status_str = implode('|', $arr);
      }
      else
         $status_str = Tournament::getStatusText($status);

      return sprintf( T_('Expecting current tournament status [%s] for change to status [%s]'),
                      $status_str,
                      Tournament::getStatusText($this->new_status) );
   }

   /*!
    * \brief Checks if current tourname-status allows editing.
    * \param $arr_status status-array
    * \return error-list; empty if no error
    */
   function check_edit_status( $arr_status )
   {
      $errors = array();

      // T-Admin can do anything at any time
      if( !$this->is_admin && !in_array($this->tourney->Status, $arr_status) )
      {
         $arrst = array();
         foreach( $arr_status as $status )
            $arrst[] = Tournament::getStatusText($status);

         //$errors[] = sprintf( T_('Edit forbidden for Tournament Status [%s], only allowed for (%s)!'),
         $errors[] = sprintf( T_('Edit is forbidden for tournament on status [%s], only allowed for (%s) !'),
                              Tournament::getStatusText($this->tourney->Status),
                              implode('|', $arrst) );
      }

      return $errors;
   }

} // end of 'TournamentStatus'

?>
