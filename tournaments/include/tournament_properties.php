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

require_once 'include/classlib_user.php';
require_once 'include/db_classes.php';
require_once 'include/dgs_cache.php';
require_once 'include/gui_functions.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_utils.php';
require_once 'tournaments/include/tournament_participant.php';

 /*!
  * \file tournament_properties.php
  *
  * \brief Functions for handling tournament properties: tables TournamentProperties
  */


 /*!
  * \class TournamentProperties
  *
  * \brief Class to manage TournamentProperties-table to restrict registration-phase
  */

global $ENTITY_TOURNAMENT_PROPERTIES; //PHP5
$ENTITY_TOURNAMENT_PROPERTIES = new Entity( 'TournamentProperties',
      FTYPE_PKEY, 'tid',
      FTYPE_CHBY,
      FTYPE_INT,  'tid', 'MinParticipants', 'MaxParticipants', 'UserMinGamesFinished', 'UserMinGamesRated',
                  'UserMinRating', 'UserMaxRating',
      FTYPE_TEXT, 'Notes',
      FTYPE_DATE, 'Lastchanged', 'RegisterEndTime',
      FTYPE_ENUM, 'RatingUseMode', 'UserRated'
   );

class TournamentProperties
{
   public $tid;
   public $Lastchanged;
   public $ChangedBy;
   public $Notes;
   public $MinParticipants;
   public $MaxParticipants;
   public $RatingUseMode;
   public $RegisterEndTime;
   public $UserMinRating;
   public $UserMaxRating;
   public $UserRated;
   public $UserMinGamesFinished;
   public $UserMinGamesRated;

   /*! \brief Constructs TournamentProperties-object with specified arguments. */
   public function __construct( $tid=0, $lastchanged=0, $changed_by='', $notes='',
         $min_participants=2, $max_participants=0, $rating_use_mode=TPROP_RUMODE_COPY_CUSTOM,
         $reg_end_time=0, $user_min_rating=MIN_RATING, $user_max_rating=RATING_9DAN, $user_rated=false,
         $user_min_games_finished=0, $user_min_games_rated=0 )
   {
      $this->tid = (int)$tid;
      $this->Lastchanged = (int)$lastchanged;
      $this->ChangedBy = $changed_by;
      $this->Notes = $notes;
      $this->MinParticipants = (int)$min_participants;
      $this->MaxParticipants = (int)$max_participants;
      $this->setRatingUseMode( $rating_use_mode );
      $this->RegisterEndTime = (int)$reg_end_time;
      $this->setUserMinRating( $user_min_rating );
      $this->setUserMaxRating( $user_max_rating );
      $this->UserRated = (bool)$user_rated;
      $this->UserMinGamesFinished = (int)$user_min_games_finished;
      $this->UserMinGamesRated = (int)$user_min_games_rated;
   }

   public function setRatingUseMode( $use_mode )
   {
      if ( !is_null($use_mode) && !preg_match( "/^(".CHECK_TPROP_RUMODE.")$/", $use_mode ) )
         error('invalid_args', "TournamentProperties.setRatingUseMode($use_mode)");
      $this->RatingUseMode = $use_mode;
   }

   public function setUserMinRating( $rating )
   {
      $this->UserMinRating = (int) TournamentUtils::normalizeRating( $rating );
   }

   public function setUserMaxRating( $rating )
   {
      $this->UserMaxRating = (int) TournamentUtils::normalizeRating( $rating );
   }

   public function getMaxParticipants( $real_val=false )
   {
      return ( $real_val || $this->MaxParticipants > 0 ) ? $this->MaxParticipants : TP_MAX_COUNT;
   }

   public function to_string()
   {
      return print_r( $this, true );
   }

   /*! \brief Returns true, if user-rating need to be copied. */
   public function need_rating_copy()
   {
      return ( $this->RatingUseMode == TPROP_RUMODE_COPY_CUSTOM || $this->RatingUseMode == TPROP_RUMODE_COPY_FIX );
   }

   /*! \brief Returns true, if customized T-rating can be specified. */
   public function allow_rating_edit()
   {
      return ( $this->RatingUseMode == TPROP_RUMODE_COPY_CUSTOM );
   }


   /*! \brief Inserts or updates tournament-properties in database. */
   public function persist()
   {
      if ( self::isTournamentProperties($this->tid) ) // async
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   public function insert()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->insert( "TournamentProperties.insert(%s)" );
   }

   public function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      $result = $entityData->update( "TournamentProperties.update(%s)" );
      self::delete_cache_tournament_properties( 'TournamentProperties.update', $this->tid );
      return $result;
   }

   public function delete()
   {
      $entityData = $this->fillEntityData();
      $result = $entityData->delete( "TournamentProperties.delete(%s)" );
      Tournament::delete_cache_tournament_properties( 'TournamentProperties.delete', $this->tid );
      return $result;
   }

   public function fillEntityData( )
   {
      // checked fields: RatingUseMode/UserMinRating/UserMaxRating
      $data = $GLOBALS['ENTITY_TOURNAMENT_PROPERTIES']->newEntityData();
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'ChangedBy', $this->ChangedBy );
      $data->set_value( 'MinParticipants', $this->MinParticipants );
      $data->set_value( 'MaxParticipants', $this->MaxParticipants );
      $data->set_value( 'RatingUseMode', $this->RatingUseMode );
      $data->set_value( 'RegisterEndTime', $this->RegisterEndTime );
      $data->set_value( 'UserMinRating', $this->UserMinRating );
      $data->set_value( 'UserMaxRating', $this->UserMaxRating );
      $data->set_value( 'UserRated', ($this->UserRated ? 'Y' : 'N') );
      $data->set_value( 'UserMinGamesFinished', $this->UserMinGamesFinished );
      $data->set_value( 'UserMinGamesRated', $this->UserMinGamesRated );
      $data->set_value( 'Notes', $this->Notes );
      return $data;
   }

   /*!
    * \brief Checks potential registration by given user and returns non-null
    *        list of errors and warnings with matching criteria, that disallow registration.
    * \param $tourney Tournament-object
    * \param $tp TournamentParticipant-object to check: $tp->ID =0 for new-user; $tp->StartRound set;
    *        $tp->Rating set if customized-rating wanted for tourney
    * \param $check_user User-object or user-id
    * \param $check_type TCHKTYPE_USER_NEW | TCHKTYPE_USER_EDIT | TCHKTYPE_TD describing use-case/scope for checks
    * \return arr( reg-error-array, reg-warning-error )
    * \note some checks are reported as warnings, because T-directors can ignore some checks while a
    *       new-user-registration (TCHKTYPE_USER_NEW) returns also the warnings as errors.
    */
   public function checkUserRegistration( $tourney, $tp, $check_user, $check_type )
   {
      $is_new_tp = ( $tp->ID == 0 ); // >0 = edit-existing-TP

      $errors = array();
      if ( $is_new_tp )
         $warnings =& $errors;
      else
         $warnings = array();

      if ( $is_new_tp && $check_type == TCHKTYPE_USER_NEW && $tourney->Scope == TOURNEY_SCOPE_PRIVATE )
         $errors[] = T_('This is a private tournament, so you must be invited to participate.');

      // limit register end-time
      global $NOW;
      if ( $this->RegisterEndTime && $NOW > $this->RegisterEndTime )
         $warnings[] = sprintf( T_('Registration phase ended on [%s].#tourney'), formatDate($this->RegisterEndTime) );

      // limit participants
      $maxTP = $this->getMaxParticipants();
      $round_max_tps = ( $tp->StartRound > 1 ) ? round( $maxTP / pow(2, $tp->StartRound - 1) ) : $maxTP;
      $tp_count = TournamentCache::count_cache_tournament_participants(
         $this->tid, /*TP-stat-ALL*/null, $tp->StartRound, /*NextR*/false );
      if ( $is_new_tp )
         ++$tp_count;
      if ( $tp_count > $round_max_tps )
         $errors[] = sprintf( T_('Tournament max. participant limit (%s users) for Start-Round %s is reached.'),
            $round_max_tps, $tp->StartRound );

      // ----- user-specific checks -----

      $user = $this->_load_user($check_user);

      // check use-rating-modes
      if ( $this->RatingUseMode == TPROP_RUMODE_CURR_FIX || $this->RatingUseMode == TPROP_RUMODE_COPY_FIX )
      {// need user-rating or tournament-rating
         if ( !$user->hasRating() )
            $errors[] = T_('User has no valid Dragon rating, which is needed for tournament rating mode.');
               //. "\n" . self::getRatingUseModeText($this->RatingUseMode, false);
      }
      elseif ( $this->RatingUseMode == TPROP_RUMODE_COPY_CUSTOM )
      {
         if ( !$tp->hasRating() && !$user->hasRating() )
            $errors[] = T_('User needs valid Dragon rating or customized rating, which is required by tournament rating mode.#tourney');
      }

      // limit user-rating
      if ( $this->UserRated )
      {
         // user must have rating, because tournament-games are rated
         if ( !$user->hasRating() )
            $errors[] = T_('User has no Dragon rating, which is required for this rated tournament.');
         elseif ( !$user->matchRating( $this->UserMinRating, $this->UserMaxRating ) )
            $warnings[] = sprintf( T_('User rating [%s] does not match the required rating range %s.'),
                  echo_rating( $user->Rating ),
                  build_range_text(
                     echo_rating( $this->UserMinRating, false ),
                     echo_rating( $this->UserMaxRating, false ),
                     '[%s - %s]' ) );
      }

      // limit games-number
      if ( $this->UserMinGamesFinished > 0 )
      {
         if ( $user->GamesFinished < $this->UserMinGamesFinished )
            $warnings[] = sprintf( T_('User must have at least %s finished games, but has only %s.'),
               $this->UserMinGamesFinished, $user->GamesFinished );
      }
      if ( $this->UserMinGamesRated > 0 )
      {
         if ( $user->GamesRated < $this->UserMinGamesRated )
            $warnings[] = sprintf( T_('User must have at least %s rated finished games, but has only %s.'),
               $this->UserMinGamesRated, $user->GamesRated );
      }

      return ( $is_new_tp )
         ? array( $errors, array() )
         : array( $errors, $warnings );
   }//checkUserRegistration

   /*! \brief (internally) loads User-object if user is only user-id and returns User-object. */
   private function _load_user( $check_user )
   {
      if ( $check_user instanceof User )
         return $check_user;
      if ( !is_numeric($check_user) )
         error('invalid_args', "TournamentProperties._load_user($check_user)");
      return User::load_user( (int)$check_user );
   }

   /*! \brief Returns array with default and seed-order array for tournaments (ladder + round-robin). */
   public function build_seed_order()
   {
      $arr = array();
      $default = 0;
      if ( $this->RatingUseMode == TPROP_RUMODE_CURR_FIX )
      {
         $arr[TOURNEY_SEEDORDER_CURRENT_RATING] = T_('Current User Rating#T_ladder');
         $default = TOURNEY_SEEDORDER_CURRENT_RATING;
      }
      $arr[TOURNEY_SEEDORDER_REGISTER_TIME] = T_('Tournament Registration Time');
      if ( $default == 0 )
         $default = TOURNEY_SEEDORDER_REGISTER_TIME;
      if ( $this->RatingUseMode == TPROP_RUMODE_COPY_CUSTOM || $this->RatingUseMode == TPROP_RUMODE_COPY_FIX )
         $arr[TOURNEY_SEEDORDER_TOURNEY_RATING] = T_('Tournament Rating');
      $arr[TOURNEY_SEEDORDER_RANDOM] = T_('Random#T_ladder');
      return array( $default, $arr );
   }//build_seed_order


   // ------------ static functions ----------------------------

   /*! \brief Checks, if tournament property existing for given tournament. */
   public static function isTournamentProperties( $tid )
   {
      return (bool)mysql_single_fetch( "TournamentProperties:isTournamentProperties($tid)",
         "SELECT 1 FROM TournamentProperties WHERE tid='$tid' LIMIT 1" );
   }

   /*! \brief Deletes TournamentProperties-entry for given tournament-id. */
   public static function delete_tournament_properties( $tid )
   {
      $t_props = new TournamentProperties( $tid );
      return $t_props->delete();
   }

   /*! \brief Returns db-fields to be used for query of single TournamentProperties-object for given tournament-id. */
   public static function build_query_sql( $tid )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_PROPERTIES']->newQuerySQL('TPR');
      $qsql->add_part( SQLP_WHERE, "TPR.tid='$tid'" );
      $qsql->add_part( SQLP_LIMIT, '1' );
      return $qsql;
   }

   /*! \brief Returns TournamentProperties-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $tp = new TournamentProperties(
            // from TournamentProperties
            @$row['tid'],
            @$row['X_Lastchanged'],
            @$row['ChangedBy'],
            @$row['Notes'],
            @$row['MinParticipants'],
            @$row['MaxParticipants'],
            @$row['RatingUseMode'],
            @$row['X_RegisterEndTime'],
            @$row['UserMinRating'],
            @$row['UserMaxRating'],
            ( @$row['UserRated'] == 'Y' ),
            @$row['UserMinGamesFinished'],
            @$row['UserMinGamesRated']
         );
      return $tp;
   }

   /*! \brief Loads and returns TournamentProperties-object for given tournament-ID. */
   public static function load_tournament_properties( $tid )
   {
      $result = NULL;
      if ( $tid > 0 )
      {
         $qsql = self::build_query_sql( $tid );
         $row = mysql_single_fetch( "TournamentProperties:load_tournament_properties($tid)",
            $qsql->get_select() );
         if ( $row )
            $result = self::new_from_row( $row );
      }
      return $result;
   }

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   public static function getRatingUseModeText( $use_mode=null, $short=true )
   {
      static $ARR_RAT_USEMODES = array(); // [key][use-mode] => text

      // lazy-init of texts
      $key = 'USE_MODE';
      if ( !isset($ARR_RAT_USEMODES[$key]) )
      {
         $arr = array();
         $arr[TPROP_RUMODE_COPY_CUSTOM] = T_('Copy Custom#TP_usemode');
         $arr[TPROP_RUMODE_COPY_FIX]    = T_('Copy Fix#TP_usemode');
         $arr[TPROP_RUMODE_CURR_FIX]    = T_('Current Fix#TP_usemode');
         $ARR_RAT_USEMODES[$key] = $arr;

         $arr = array();
         $arr[TPROP_RUMODE_COPY_CUSTOM] = T_('Dragon user rating is used for tournament registration, but can be customized by user.');
         $arr[TPROP_RUMODE_COPY_FIX]    = T_('Dragon user rating is copied on tournament registration and can not be changed.');
         $arr[TPROP_RUMODE_CURR_FIX]    = T_('Current Dragon user rating will be used during whole tournament.');
         $ARR_RAT_USEMODES[$key.'_LONG'] = $arr;
      }

      if ( !$short )
         $key .= '_LONG';
      if ( is_null($use_mode) )
         return $ARR_RAT_USEMODES[$key];
      if ( !isset($ARR_RAT_USEMODES[$key][$use_mode]) )
         error('invalid_args', "TournamentProperties:getRatingUseModeText($use_mode,$key)");
      return $ARR_RAT_USEMODES[$key][$use_mode];
   }//getRatingUseModeText

   public static function get_edit_tournament_status()
   {
      static $statuslist = array( TOURNEY_STATUS_NEW );
      return $statuslist;
   }

   public static function delete_cache_tournament_properties( $dbgmsg, $tid )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_TPROPS, "TProps.$tid" );
   }

} // end of 'TournamentProperties'
?>
