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

require_once 'include/std_classes.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_director.php';
require_once 'tournaments/include/tournament_utils.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_factory.php';

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
   var $ttype; // specific typed TournamentTemplate-object
   var $tprops; // TournamentProperties-object
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
         error('unknown_tournament', "TournamentStatus.find_tournament({$this->tid})");

      $this->ttype = TournamentFactory::getTournament($this->tourney->WizardType);
      $this->tprops = null;

      $this->is_admin = TournamentUtils::isAdmin();
      $this->curr_status = $this->new_status = $this->tourney->Status;
      $this->errors = array();
   }

   function has_error()
   {
      return (bool)count($this->errors);
   }

   function _load_tprops()
   {
      if( is_null($this->tprops) )
      {
         $this->tprops = TournamentProperties::load_tournament_properties($this->tid);
         if( is_null($this->tprops) )
            error('bad_tournament', "TournamentStatus.find_tournamt_properties({$this->tid})");
      }
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

      $this->check_basic_conditions_status_change();

      // check tournament-type specific checks
      $check_errors = $this->ttype->checkProperties( $this->tid );
      if( count($check_errors) )
         $this->erros = array_merge( $this->errors, $check_errors );
   }

   function check_conditions_status_PAIR()
   {
      if( $this->curr_status != TOURNEY_STATUS_REGISTER )
         $this->errors[] = $this->error_expected_status( TOURNEY_STATUS_REGISTER );

      $this->check_basic_conditions_status_change();

      // check participants-count
      $this->_load_tprops();
      $tp_counts = TournamentParticipant::count_tournament_participants($this->tid, TP_STATUS_REGISTER);
      $reg_count = (int)@$tp_counts[TPCOUNT_STATUS_ALL];
      if( $this->tprops->MinParticipants > 0 && $reg_count < $this->tprops->MinParticipants )
         $this->errors[] = sprintf(
               T_('Tournament min. participant limit (%s users) has not been reached yet: %s registrations are missing.'),
               $this->tprops->MinParticipants, $this->tprops->MinParticipants - $reg_count );
      if( $this->tprops->MaxParticipants > 0 && $reg_count > $this->tprops->MaxParticipants )
         $this->errors[] = sprintf(
               T_('Tournament max. participant limit (%s users) has been exceeded by %s registrations.'),
               $this->tprops->MaxParticipants, $reg_count - $this->tprops->MaxParticipants );

      // check restrictions for all registered users
      global $base_path;
      $iterator = new ListIterator( 'TournamentStatus.check_conditions_status_PAIR',
            new QuerySQL( SQLP_WHERE, sprintf( "TP.Status='%s'", mysql_addslashes(TP_STATUS_REGISTER)) ) );
      $iterator = TournamentParticipant::load_tournament_participants($iterator, $this->tid);
      while( list(,$arr_item) = $iterator->getListIterator() )
      {
         list( $tp, $orow ) = $arr_item;
         list( $reg_errors, $reg_warnings ) =
            $this->tprops->checkUserRegistration($this->tourney, $tp->hasRating(), $tp->User, TPROP_CHKTYPE_TD);
         foreach( $reg_errors as $err )
         {
            $err_link = anchor( $base_path."tournaments/edit_participant.php?tid={$this->tid}".URI_AMP."uid={$tp->uid}", $tp->User->Handle );
            $this->errors[] = make_html_safe( sprintf( T_('Error for user %s'), "[$err_link]"), 'line') . ": $err";
         }
      }
   }//check_conditions_status_PAIR

   function check_conditions_status_PLAY()
   {
      if( $this->curr_status != TOURNEY_STATUS_PAIR )
         $this->errors[] = $this->error_expected_status( TOURNEY_STATUS_PAIR );

      $this->check_basic_conditions_status_change();

      // check that all registered TPs are added
      //TODO(later) condition (for round-robin): all T-games must be ready to start (i.e. inserted and set up)
      $arr_TPs = TournamentParticipant::load_tournament_participants_registered( $this->tid );
      $chk_errors = $this->ttype->checkParticipantRegistrations( $this->tid, $arr_TPs );
      if( count($chk_errors) )
         $this->errors = array_merge( $this->errors, $chk_errors );
   }

   function check_conditions_status_CLOSED()
   {
      if( $this->curr_status != TOURNEY_STATUS_PLAY )
         $this->errors[] = $this->error_expected_status( TOURNEY_STATUS_PLAY );

      $this->check_basic_conditions_status_change();

      //TODO condition: no running T-game
      $this->errors[] = 'not_implemented_yet';
   }

   function check_conditions_status_DEL()
   {
      //TODO condition: no running T-game
      $this->errors[] = 'not_implemented_yet';
   }


   function check_basic_conditions_status_change()
   {
      if( strlen($this->tourney->Title) < 8 )
         $this->errors[] = T_('Tournament title missing or too short');
      if( strlen($this->tourney->Description) < 4 )
         $this->errors[] = T_('Tournament description missing or too short');
      if( !TournamentDirector::has_tournament_director($this->tid) )
         $this->errors[] = T_('Missing at least one tournament director');
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
    * \brief Checks if current tournament-status allows certain action.
    * \param $errmsgfmt error-message-format expecting two args: 1. tourney-status, 2. expected status-list
    * \param $arr_status status-array
    * \param $allow_admin if true, admin can do anything; otherwise admin is treated like non-admin
    * \return error-list; empty if no error
    */
   function check_action_status( $errmsgfmt, $arr_status, $allow_admin=true )
   {
      $errors = array();

      // T-Admin can do anything at any time
      if( $allow_admin && $this->is_admin )
         $allow = true;
      else
         $allow = in_array($this->tourney->Status, $arr_status);

      if( !$allow )
      {
         $arrst = array();
         foreach( $arr_status as $status )
            $arrst[] = Tournament::getStatusText($status);

         $errors[] = sprintf( $errmsgfmt,
                              Tournament::getStatusText($this->tourney->Status),
                              implode('|', $arrst) );
      }

      return $errors;
   }//check_action_status

   function check_edit_status( $arr_status, $allow_admin=true )
   {
      return $this->check_action_status(
         T_('Edit is forbidden for tournament on status [%s], only allowed for (%s) !'),
         $arr_status, $allow_admin );
   }

   function check_view_status( $arr_status, $allow_admin=true )
   {
      return $this->check_action_status(
         T_('View of tournament is forbidden on status [%s], only allowed for (%s) !'),
         $arr_status, $allow_admin );
   }

} // end of 'TournamentStatus'

?>
