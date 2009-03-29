<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once( 'include/std_classes.php' );
require_once( 'tournaments/include/tournament_utils.php' );

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
define('TPROP_RUMODE_ENTER_FIX',    'ENTER_FIX');
define('CHECK_TPROP_RUMODE', 'COPY_CUSTOM|CURR_FIX|COPY_FIX|ENTER_FIX');

// lazy-init in TournamentProperties::get..Text()-funcs
$ARR_GLOBALS_TOURNAMENT_PROPERTIES = array();

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
   var $UserMinMoves;

   /*! \brief Constructs TournamentProperties-object with specified arguments. */
   function TournamentProperties(
         $tid=0, $lastchanged=0, $notes='',
         $min_participants=2, $max_participants=0, $rating_use_mode=TPROP_RUMODE_COPY_CUSTOM,
         $reg_end_time=0, $user_min_rating=MIN_RATING, $user_max_rating=RATING_9DAN, $user_rated=false,
         $user_min_games_finished=0, $user_min_games_rated=0, $user_min_moves=0 )
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
      $this->UserMinMoves = (int)$user_min_moves;
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
      return " tid=[{$this->tid}]"
            . ", Lastchanged=[{$this->Lastchanged}]"
            . ", Notes=[{$this->Notes}]"
            . ", MinParticipants=[{$this->MinParticipants}]"
            . ", MaxParticipants=[{$this->MaxParticipants}]"
            . ", RatingUseMode=[{$this->RatingUseMode}]"
            . ", RegisterEndTime=[{$this->RegisterEndTime}]"
            . ", UserMinRating=[{$this->UserMinRating}]"
            . ", UserMaxRating=[{$this->UserMaxRating}]"
            . ", UserRated=[{$this->UserRated}]"
            . ", UserMinGamesFinished=[{$this->UserMinGamesFinished}]"
            . ", UserMinGamesRated=[{$this->UserMinGamesRated}]"
            . ", UserMinMoves=[{$this->UserMinMoves}]"
         ;
   }

   /*! \brief Inserts or updates tournament-properties in database. */
   function persist()
   {
      $success = $this->insert();
      return $success;
   }

   /*! \brief Builds query-part for persistance (insert or update). */
   function build_persist_query_part()
   {
      // RatingUseMode/UserMinRating/UserMaxRating are checked
      return  " tid='{$this->tid}'"
            . ",Lastchanged=FROM_UNIXTIME({$this->Lastchanged})"
            . ",Notes='" . mysql_addslashes($this->Notes) . "'"
            . ",MinParticipants='{$this->MinParticipants}'"
            . ",MaxParticipants='{$this->MaxParticipants}'"
            . ",RatingUseMode='" . mysql_addslashes($this->RatingUseMode) . "'"
            . ",RegisterEndTime=FROM_UNIXTIME({$this->RegisterEndTime})"
            . ",UserMinRating='{$this->UserMinRating}'"
            . ",UserMaxRating='{$this->UserMaxRating}'"
            . sprintf( ",UserRated='%s'", ($this->UserRated ? 'Y' : 'N') )
            . ",UserMinGamesFinished='{$this->UserMinGamesFinished}'"
            . ",UserMinGamesRated='{$this->UserMinGamesRated}'"
            . ",UserMinMoves='{$this->UserMinMoves}'"
         ;
   }

   /*!
    * \brief Inserts or replaces existing TournamentProperties-entry.
    * \note sets Lastchanged=NOW
    */
   function insert()
   {
      global $NOW;
      $this->Lastchanged = $NOW;

      $result = db_query( "TournamentProperties::insert({$this->tid})",
            "REPLACE INTO TournamentProperties SET "
            . $this->build_persist_query_part()
         );
      return $result;
   }

   /*!
    * \brief Updates TournamentProperties-entry.
    * \note sets Lastchanged=NOW
    */
   function update()
   {
      global $NOW;
      $this->Lastchanged = $NOW;

      $result = db_query( "TournamentProperties::update({$this->tid})",
            "UPDATE TournamentProperties SET "
            . $this->build_persist_query_part()
            . " WHERE tid='{$this->tid}' LIMIT 1"
         );
      return $result;
   }

   // ------------ static functions ----------------------------

   /*! \brief Deletes TournamentProperties-entry for given tournament-id. */
   function delete_tournament_properties( $tid )
   {
      $result = db_query( "TournamentProperties::delete_tournament_properties($tid)",
         "DELETE FROM TournamentProperties WHERE tid='$tid' LIMIT 1" );
      return $result;
   }

   /*! \brief Returns db-fields to be used for query of single TournamentProperties-object for given tournament-id. */
   function build_query_sql( $tid )
   {
      // TournamentProperties: tid,Lastchanged,Notes,MinParticipants,MaxParticipants,
      //     RatingUseMode,RegisterEndTime,UserMinRating,UserMaxRating,UserRated,
      //     UserMinGamesFinished,UserMinGamesRated,UserMinMoves
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'TPR.*',
         'UNIX_TIMESTAMP(TPR.Lastchanged) AS X_Lastchanged',
         'UNIX_TIMESTAMP(TPR.RegisterEndTime) AS X_RegisterEndTime' );
      $qsql->add_part( SQLP_FROM,
         'TournamentProperties AS TPR' );
      $qsql->add_part( SQLP_WHERE,
         "TPR.tid='$tid'" );
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
            @$row['UserMinGamesRated'],
            @$row['UserMinMoves']
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
      // lazy-init of texts
      $key = 'USE_MODE';
      if( !isset($ARR_GLOBALS_TOURNAMENT_PROPERTIES[$key]) )
      {
         $arr = array();
         $arr[TPROP_RUMODE_COPY_CUSTOM] = T_('Copy Custom#TP_usemode');
         $arr[TPROP_RUMODE_CURR_FIX]    = T_('Current Fix#TP_usemode');
         $arr[TPROP_RUMODE_COPY_FIX]    = T_('Copy Fix#TP_usemode');
         $arr[TPROP_RUMODE_ENTER_FIX]   = T_('Enter Fix#TP_usemode');
         $ARR_GLOBALS_TOURNAMENT_PROPERTIES[$key] = $arr;

         $arr = array();
         $arr[TPROP_RUMODE_COPY_CUSTOM] = T_('Dragon user rating is used for registration, but can be changed afterwards.');
         $arr[TPROP_RUMODE_CURR_FIX]    = T_('Current Dragon user rating will be used during whole tournament.');
         $arr[TPROP_RUMODE_COPY_FIX]    = T_('Dragon user rating is used for registration and can not be changed afterwards by user.');
         $arr[TPROP_RUMODE_ENTER_FIX]   = T_('Tournament rating must be manually entered on registration.');
         $ARR_GLOBALS_TOURNAMENT_PROPERTIES[$key.'_SHORT'] = $arr;
      }

      if( is_null($use_mode) )
         return $ARR_GLOBALS_TOURNAMENT_PROPERTIES[$key];

      if( !$short ) $key .= '_SHORT';
      if( !isset($ARR_GLOBALS_TOURNAMENT_PROPERTIES[$key][$use_mode]) )
         error('invalid_args', "TournamentProperties.getRatingUseModeText($use_mode,$key)");
      return $ARR_GLOBALS_TOURNAMENT_PROPERTIES[$key][$use_mode];
   }

   /*! \brief Returns array with notes about tournament properties. */
   function build_notes()
   {
      $notes = array();
      $notes[] = T_('All properties on this page are optional.');
      $notes[] = T_('Value of [0] is treated as no restriction.');
      $notes[] = null; // empty line

      $notes[] = sprintf(
            T_('Rating Use Mode:<ul>'
               . '<li>%1$s = Rating is copied on registration. Rating can be customized by user.'."\n" // copy-custom
               . '<li>%2$s = Current rating is always used. Rating can\'t be changed by user or tournament director.'."\n" // curr-fix
               . '<li>%3$s = Rating is copied on registration, but can\'t be changed by user afterwards.'."\n" // copy-fix
               . '<li>%4$s = Rating must be manually entered and can be customized by user.'."\n" // enter-fix
               . '</ul>'),
            TournamentProperties::getRatingUseModeText(TPROP_RUMODE_COPY_CUSTOM),
            TournamentProperties::getRatingUseModeText(TPROP_RUMODE_CURR_FIX),
            TournamentProperties::getRatingUseModeText(TPROP_RUMODE_COPY_FIX),
            TournamentProperties::getRatingUseModeText(TPROP_RUMODE_ENTER_FIX)
         );
      return $notes;
   }

} // end of 'TournamentProperties'
?>
