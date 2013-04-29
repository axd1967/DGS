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
      if( $tid instanceof Tournament )
      {
         $this->tourney = $tid;
         $this->tid = $this->tourney->ID;
      }
      elseif( is_numeric($tid) && $tid > 0 )
      {
         $this->tid = (int)$tid;
         $this->tourney = TournamentCache::load_cache_tournament(
            'TournamentStatus.constructfind_tournament.find_tournament', $this->tid );
      }
      if( is_null($this->tourney) || (int)$this->tid <= 0 )
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
      if( $str )
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
      if( is_null($this->tprops) )
         $this->tprops = TournamentCache::load_cache_tournament_properties( 'TournamentStatus.find_tprops', $this->tid );
   }

   /*! \internal to load TournamentRound (if needed). */
   private function _load_tround()
   {
      if( is_null($this->tround) && $this->ttype->need_rounds )
      {
         $round = $this->tourney->CurrentRound;
         $this->tround = TournamentCache::load_cache_tournament_round( 'TournamentStatus.find_tround', $this->tid, $round );
      }
   }

   /*! \brief Checks change for new-status (writes also $this->errors). */
   public function check_status_change( $new_status )
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



   /*! \brief Check if change to ADM-tourney-status is allowed. */
   public function check_conditions_status_ADM()
   {
      $this->errors[] = sprintf( T_('Change on Tournament Status [%s] only allowed by Tournament Admin.'),
                                 Tournament::getStatusText($this->new_status) );
   }

   /*! \brief Check if change to NEW-tourney-status is allowed. */
   public function check_conditions_status_NEW()
   {
      $this->errors[] = sprintf( T_('Change on Tournament Status [%s] only allowed by Tournament Admin.'),
                                 Tournament::getStatusText($this->new_status) );
   }

   /*! \brief Check if change to REG-tourney-status is allowed. */
   public function check_conditions_status_REG()
   {
      if( $this->curr_status != TOURNEY_STATUS_NEW )
         $this->errors[] = $this->error_expected_status( TOURNEY_STATUS_NEW );

      $this->check_basic_conditions_status_change();

      // check tournament-type specific checks
      $check_errors = $this->ttype->checkProperties( $this->tourney, TOURNEY_STATUS_REGISTER );
      if( count($check_errors) )
         $this->errors = array_merge( $this->errors, $check_errors );
   }

   /*! \brief Check if change to PAIR-tourney-status is allowed. */
   public function check_conditions_status_PAIR()
   {
      if( $this->curr_status != TOURNEY_STATUS_REGISTER )
         $this->errors[] = $this->error_expected_status( TOURNEY_STATUS_REGISTER );

      $this->check_basic_conditions_status_change();

      // check participants-count
      $this->_load_tprops();
      $this->_load_tround();
      $min_participants = $this->ttype->calcTournamentMinParticipants( $this->tprops, $this->tround );
      $tp_counts = TournamentCache::count_cache_tournament_participants($this->tid, TP_STATUS_REGISTER); //TODO count TP for StartRound=CurrentRound
      $reg_count = (int)@$tp_counts[TPCOUNT_STATUS_ALL];
      if( $min_participants > 0 && $reg_count < $min_participants )
         $this->errors[] = sprintf( //TODO ... "for users registered to start in round X"
               T_('Tournament min. participant limit (%s users) has not been reached yet: %s registrations are missing.'),
               $min_participants, $min_participants - $reg_count );
      if( $this->tprops->MaxParticipants > 0 && $reg_count > $this->tprops->MaxParticipants )
         $this->errors[] = sprintf(
               T_('Tournament max. participant limit (%s users) has been exceeded by %s registrations.'),
               $this->tprops->MaxParticipants, $reg_count - $this->tprops->MaxParticipants );

      // check restrictions for all registered users (incl. for future rounds)
      global $base_path;
      $iterator = new ListIterator( 'TournamentStatus.check_conditions_status_PAIR',
            new QuerySQL( SQLP_WHERE, sprintf( "TP.Status='%s'", mysql_addslashes(TP_STATUS_REGISTER)) ) );
      $iterator = TournamentParticipant::load_tournament_participants($iterator, $this->tid);
      while( list(,$arr_item) = $iterator->getListIterator() )
      {
         list( $tp, $orow ) = $arr_item;
         list( $reg_errors, $reg_warnings ) =
            $this->tprops->checkUserRegistration($this->tourney, $tp->hasRating(), $tp->User, TCHKTYPE_TD); //TODO check for which round?
         $count = 50; // check all, but limit error-output to some users
         foreach( $reg_errors as $err )
         {
            $err_link = anchor( $base_path."tournaments/edit_participant.php?tid={$this->tid}".URI_AMP."uid={$tp->uid}", $tp->User->Handle );
            $this->errors[] = make_html_safe( sprintf( T_('Error for user %s'), "[$err_link]"), 'line') . ": $err";
            if( $count-- <= 0 ) break;
         }
      }

      // check tournament-type specific checks
      $check_errors = $this->ttype->checkProperties( $this->tourney, TOURNEY_STATUS_PAIR );
      if( count($check_errors) )
         $this->errors = array_merge( $this->errors, $check_errors );
   }//check_conditions_status_PAIR

   /*! \brief Check if change to PLAY-tourney-status is allowed. */
   public function check_conditions_status_PLAY()
   {
      if( $this->curr_status != TOURNEY_STATUS_PAIR )
         $this->errors[] = $this->error_expected_status( TOURNEY_STATUS_PAIR );

      $this->check_basic_conditions_status_change();

      // check that all registered TPs are added
      $arr_TPs = TournamentParticipant::load_tournament_participants_registered( $this->tid ); //TODO load only for current-round
      $check_errors = $this->ttype->checkParticipantRegistrations( $this->tid, $arr_TPs );
      if( count($check_errors) )
         $this->errors = array_merge( $this->errors, $check_errors );

      // check that all games have been started
      $check_errors = $this->ttype->checkGamesStarted( $this->tid );
      if( count($check_errors) )
         $this->errors = array_merge( $this->errors, $check_errors );
   }

   /*! \brief Check if change to CLOSED-tourney-status is allowed. */
   public function check_conditions_status_CLOSED()
   {
      if( $this->curr_status != TOURNEY_STATUS_PLAY )
         $this->errors[] = $this->error_expected_status( TOURNEY_STATUS_PLAY );

      $this->check_basic_conditions_status_change();
      $this->check_conditions_unfinished_tourney_games();
   }

   /*! \brief Check if change to DEL-tourney-status is allowed. */
   public function check_conditions_status_DEL()
   {
      $this->check_conditions_unfinished_tourney_games();
   }



   /*! \brief Checks basic conditions for change of tourney-status: title/description/TD. */
   public function check_basic_conditions_status_change()
   {
      if( strlen($this->tourney->Title) < 8 )
         $this->errors[] = T_('Tournament title missing or too short');
      if( strlen($this->tourney->Description) < 4 )
         $this->errors[] = T_('Tournament description missing or too short');
      if( !TournamentDirector::has_tournament_director($this->tid) )
         $this->errors[] = T_('Missing at least one tournament director');
   }

   /*! \brief Checks if there are unfinished tourney-games that can prohibit tourney-status-change. */
   public function check_conditions_unfinished_tourney_games()
   {
      // check for not-DONE T-games
      $tg_count_running = TournamentGames::count_tournament_games( $this->tid );
      if( $tg_count_running > 0 )
         $this->errors[] = sprintf(
               T_('Tournament has %s unfinished tournament games, that must be ended first.'),
               $tg_count_running );
   }


   public function error_expected_status( $status )
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

      return sprintf( T_('Expecting current Tournament Status [%s] for change to status [%s]'),
                      $status_str,
                      Tournament::getStatusText($this->new_status) );
   }//error_expected_status

   /*!
    * \brief Checks if current tournament-status allows certain action.
    * \param $errmsgfmt error-message-format expecting two args: 1. tourney-status, 2. expected status-list
    * \param $arr_status status-array
    * \param $allow_admin if true, admin can do anything; otherwise admin is treated like non-admin
    * \return error-list; empty if no error
    */
   public function check_action_status( $errmsgfmt, $arr_status, $allow_admin=true )
   {
      $errors = array();

      // T-Admin can do anything at any time
      if( $allow_admin && TournamentUtils::isAdmin() )
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
