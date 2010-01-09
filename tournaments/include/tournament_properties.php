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

require_once( 'include/db_classes.php' );
require_once( 'include/classlib_user.php' );
require_once( 'tournaments/include/tournament_utils.php' );
require_once( 'tournaments/include/tournament.php' );

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

define('TPROP_RUMODE_COPY_CUSTOM',  'COPY_CUSTOM');
define('TPROP_RUMODE_CURR_FIX',     'CURR_FIX');
define('TPROP_RUMODE_COPY_FIX',     'COPY_FIX');
define('CHECK_TPROP_RUMODE', 'COPY_CUSTOM|CURR_FIX|COPY_FIX');

// lazy-init in TournamentProperties::get..Text()-funcs
global $ARR_GLOBALS_TOURNAMENT_PROPERTIES; //PHP5
$ARR_GLOBALS_TOURNAMENT_PROPERTIES = array();

global $ENTITY_TOURNAMENT_PROPERTIES; //PHP5
$ENTITY_TOURNAMENT_PROPERTIES = new Entity( 'TournamentProperties',
      FTYPE_PKEY, 'tid',
      FTYPE_INT,  'tid', 'MinParticipants', 'MaxParticipants', 'UserMinRating',
                  'UserMaxRating', 'UserMinGamesFinished', 'UserMinGamesRated',
      FTYPE_TEXT, 'Notes',
      FTYPE_DATE, 'Lastchanged', 'RegisterEndTime',
      FTYPE_ENUM, 'RatingUseMode', 'UserRated'
   );

class TournamentProperties
{
   var $tid;
   var $Lastchanged;
   var $Notes;
   var $MinParticipants;
   var $MaxParticipants;
   var $RatingUseMode;
   var $RegisterEndTime;
   var $UserMinRating;
   var $UserMaxRating;
   var $UserRated;
   var $UserMinGamesFinished;
   var $UserMinGamesRated;

   /*! \brief Constructs TournamentProperties-object with specified arguments. */
   function TournamentProperties(
         $tid=0, $lastchanged=0, $notes='',
         $min_participants=2, $max_participants=0, $rating_use_mode=TPROP_RUMODE_COPY_CUSTOM,
         $reg_end_time=0, $user_min_rating=MIN_RATING, $user_max_rating=RATING_9DAN, $user_rated=false,
         $user_min_games_finished=0, $user_min_games_rated=0 )
   {
      $this->tid = (int)$tid;
      $this->Lastchanged = (int)$lastchanged;
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

   function setRatingUseMode( $use_mode )
   {
      if( !is_null($use_mode) && !preg_match( "/^(".CHECK_TPROP_RUMODE.")$/", $use_mode ) )
         error('invalid_args', "TournamentProperties.setRatingUseMode($use_mode)");
      $this->RatingUseMode = $use_mode;
   }

   function setUserMinRating( $rating )
   {
      $this->UserMinRating = TournamentUtils::normalizeRating( $rating );
   }

   function setUserMaxRating( $rating )
   {
      $this->UserMaxRating = TournamentUtils::normalizeRating( $rating );
   }

   function to_string()
   {
      return print_r( $this, true );
   }

   /*! \brief Inserts or updates tournament-properties in database. */
   function persist()
   {
      if( TournamentProperties::isTournamentProperties($this->tid) ) // async
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   function insert()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->insert( "TournamentProperties::insert(%s)" );
   }

   function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->update( "TournamentProperties::update(%s)" );
   }

   function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "TournamentProperties::delete(%s)" );
   }

   function fillEntityData( )
   {
      // checked fields: RatingUseMode/UserMinRating/UserMaxRating
      $data = $GLOBALS['ENTITY_TOURNAMENT_PROPERTIES']->newEntityData();
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
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
    *        list of matching criteria, that disallow registration.
    * \param $tourney Tournament with set TP_Counts (loaded if not set)
    * \param $tp_has_rating = is_valid_rating(TournamentParticipant->Rating)
    * \param $check_user User-object or user-id
    */
   function checkUserRegistration( $tourney, $tp_has_rating, $check_user )
   {
      global $NOW;
      $errors = array();

      // limit register end-time
      if( $this->RegisterEndTime && $NOW > $this->RegisterEndTime )
         $errors[] = sprintf( T_('Registration phase ended on [%s].'),
                              TournamentUtils::formatDate($this->RegisterEndTime) );

      // limit participants
      if( $this->MaxParticipants > 0 )
      {
         if( is_null($tourney->TP_Counts) )
            $tourney->setTP_Counts( TournamentParticipant::count_tournament_participants( $this->tid ) );

         if( (int)@$tourney->TP_Counts[TPCOUNT_STATUS_ALL] >= $this->MaxParticipants )
            $errors[] = sprintf( T_('Tournament max. participant limit (%s users) is reached.'),
                                 $this->MaxParticipants );
      }

      // ----- user-specific checks -----

      $user = $this->_load_user($check_user);

      // use-rating-mode
      if( $this->RatingUseMode == TPROP_RUMODE_CURR_FIX || $this->RatingUseMode == TPROP_RUMODE_COPY_FIX )
      {// need user-rating or tournament-rating
         //TODO BUG: CURR_FIX must NOT check tp_has_rating
         if( !$user->hasRating() && !$tp_has_rating )
            $errors[] = T_('User has no Dragon or tournament rating, which is needed for tournament rating mode:')
               . "\n" . TournamentProperties::getRatingUseModeText($this->RatingUseMode, false);
      }

      // limit user-rating
      if( $this->UserRated )
      {
         // user must have rating, because tournament-games are rated
         if( !$user->hasRating() )
            $errors[] = T_('User has no Dragon rating, which is required for this tournament.');
         elseif ( !$user->matchRating( $this->UserMinRating, $this->UserMaxRating ) )
            $errors[] = sprintf( T_('User rating [%s] does not match the required rating range [%s - %s].'),
               echo_rating( $user->Rating ),
               echo_rating( $this->UserMinRating, false ),
               echo_rating( $this->UserMaxRating, false ) );
      }

      // limit games-number
      if( $this->UserMinGamesFinished > 0 )
      {
         if( $user->GamesFinished < $this->UserMinGamesFinished )
            $errors[] = sprintf( T_('User must have at least %s finished games, but has only %s.'),
               $this->UserMinGamesFinished, $user->GamesFinished );
      }
      if( $this->UserMinGamesRated > 0 )
      {
         if( $user->GamesRated < $this->UserMinGamesRated )
            $errors[] = sprintf( T_('User must have at least %s rated finished games, but has only %s.'),
               $this->UserMinGamesRated, $user->GamesRated );
      }

      return $errors;
   }// checkUser

   /*! \brief (internally) loads User-object if user is only user-id and returns User-object. */
   function _load_user( $check_user )
   {
      if( is_a($check_user, 'User') )
         return $check_user;
      if( !is_numeric($check_user) )
         error('invalid_args', "TournamentProperties._load_user($check_user)");
      return User::load_user( (int)$check_user );
   }


   // ------------ static functions ----------------------------

   /*! \brief Checks, if tournament property existing for given tournament. */
   function isTournamentProperties( $tid )
   {
      return (bool)mysql_single_fetch( "TournamentProperties.isTournamentProperties($tid)",
         "SELECT 1 FROM TournamentProperties WHERE tid='$tid' LIMIT 1" );
   }

   /*! \brief Deletes TournamentProperties-entry for given tournament-id. */
   function delete_tournament_properties( $tid ) //TODO used?
   {
      $t_props = new TournamentProperties( $tid );
      return $t_props->delete( "TournamentProperties::delete_tournament_properties(%s)" );
   }

   /*! \brief Returns db-fields to be used for query of single TournamentProperties-object for given tournament-id. */
   function build_query_sql( $tid )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_PROPERTIES']->newQuerySQL('TPR');
      $qsql->add_part( SQLP_WHERE, "TPR.tid='$tid'" );
      $qsql->add_part( SQLP_LIMIT, '1' );
      return $qsql;
   }

   /*! \brief Returns TournamentProperties-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $tp = new TournamentProperties(
            // from TournamentProperties
            @$row['tid'],
            @$row['X_Lastchanged'],
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

   /*!
    * \brief Loads and returns TournamentProperties-object for given tournament-ID.
    */
   function load_tournament_properties( $tid )
   {
      $result = NULL;
      if( $tid > 0 )
      {
         $qsql = TournamentProperties::build_query_sql( $tid );
         $row = mysql_single_fetch( "TournamentProperties.load_tournament_properties($tid)",
            $qsql->get_select() );
         if( $row )
            $result = TournamentProperties::new_from_row( $row );
      }
      return $result;
   }

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   function getRatingUseModeText( $use_mode=null, $short=true )
   {
      global $ARR_GLOBALS_TOURNAMENT_PROPERTIES;

      // lazy-init of texts
      $key = 'USE_MODE';
      if( !isset($ARR_GLOBALS_TOURNAMENT_PROPERTIES[$key]) )
      {
         $arr = array();
         $arr[TPROP_RUMODE_COPY_CUSTOM] = T_('Copy Custom#TP_usemode');
         $arr[TPROP_RUMODE_COPY_FIX]    = T_('Copy Fix#TP_usemode');
         $arr[TPROP_RUMODE_CURR_FIX]    = T_('Current Fix#TP_usemode');
         $ARR_GLOBALS_TOURNAMENT_PROPERTIES[$key] = $arr;

         $arr = array();
         $arr[TPROP_RUMODE_COPY_CUSTOM] = T_('Dragon user rating is copied on registration, but can be customized by user.');
         $arr[TPROP_RUMODE_COPY_FIX]    = T_('Dragon user rating is copied on registration and can not be changed.');
         $arr[TPROP_RUMODE_CURR_FIX]    = T_('Current Dragon user rating will be used during whole tournament.');
         $ARR_GLOBALS_TOURNAMENT_PROPERTIES[$key.'_LONG'] = $arr;
      }

      if( !$short )
         $key .= '_LONG';
      if( is_null($use_mode) )
         return $ARR_GLOBALS_TOURNAMENT_PROPERTIES[$key];
      if( !isset($ARR_GLOBALS_TOURNAMENT_PROPERTIES[$key][$use_mode]) )
         error('invalid_args', "TournamentProperties.getRatingUseModeText($use_mode,$key)");
      return $ARR_GLOBALS_TOURNAMENT_PROPERTIES[$key][$use_mode];
   }

   function get_edit_tournament_status()
   {
      static $statuslist = array( TOURNEY_STATUS_NEW );
      return $statuslist;
   }

} // end of 'TournamentProperties'
?>
