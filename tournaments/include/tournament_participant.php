<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/utilities.php';
require_once 'include/db_classes.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_utils.php';
require_once 'tournaments/include/tournament_globals.php';

 /*!
  * \file tournament_participant.php
  *
  * \brief Functions for handling tournament participants: tables TournamentParticipant
  */


 /*!
  * \class TournamentParticipant
  *
  * \brief Class to manage TournamentParticipant-table
  */

global $ENTITY_TOURNAMENT_PARTICIPANT; //PHP5
$ENTITY_TOURNAMENT_PARTICIPANT = new Entity( 'TournamentParticipant',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_CHBY,
      FTYPE_INT,  'ID', 'tid', 'uid', 'Flags', 'StartRound', 'NextRound', 'Finished', 'Won', 'Lost',
      FTYPE_FLOAT, 'Rating',
      FTYPE_TEXT, 'Comment', 'Notes', 'UserMessage', 'AdminMessage',
      FTYPE_DATE, 'Created', 'Lastchanged',
      FTYPE_ENUM, 'Status'
   );

class TournamentParticipant
{
   private static $ARR_TP_TEXTS = array(); // lazy-init in TournamentParticipant::get..Text()-funcs: [key][id] => text

   public $ID;
   public $tid;
   public $uid;
   public $Status; // null | TP_STATUS_...
   public $Flags;
   public $Rating; // NO_RATING | valid-rating
   public $StartRound;
   public $NextRound;
   public $Created;
   public $Lastchanged;
   public $ChangedBy;
   public $Comment;
   public $Notes;
   public $UserMessage;
   public $AdminMessage;
   public $Finished;
   public $Won;
   public $Lost;

   // non-DB fields

   public $User; // User-object

   /*! \brief Constructs TournamentParticipant-object with specified arguments. */
   public function __construct( $id=0, $tid=0, $uid=0, $user=NULL, $status=null, $flags=0,
         $rating=NULL, $start_round=1, $next_round=0, $created=0, $lastchanged=0, $changed_by='',
         $comment='', $notes='', $user_message='', $admin_message='', $finished=0, $won=0, $lost=0 )
   {
      $this->ID = (int)$id;
      $this->tid = (int)$tid;
      $this->uid = (int)$uid;
      $this->setStatus( $status );
      $this->Flags = (int)$flags;
      $this->setRating( $rating );
      $this->setStartRound( $start_round );
      $this->NextRound = (int)$next_round;
      $this->Created = (int)$created;
      $this->Lastchanged = (int)$lastchanged;
      $this->ChangedBy = $changed_by;
      $this->Comment = $comment;
      $this->Notes = $notes;
      $this->UserMessage = $user_message;
      $this->AdminMessage = $admin_message;
      $this->Finished = (int)$finished;
      $this->Won = (int)$won;
      $this->Lost = (int)$lost;
      // non-DB fields
      $this->User = ($user instanceof User) ? $user : new User( $this->uid );
   }

   public function setStatus( $status )
   {
      if ( !is_null($status) && !preg_match( "/^(".CHECK_TP_STATUS.")$/", $status ) )
         error('invalid_args', "TournamentParticipant.setStatus($status)");
      $this->Status = $status;
   }

   public function setRating( $rating )
   {
      $this->Rating = TournamentUtils::normalizeRating( $rating );
   }

   public function hasRating()
   {
      return (abs($this->Rating) < OUT_OF_RATING);
   }

   public function setStartRound( $start_round )
   {
      $this->StartRound = limit( (int)$start_round, 1, 255, 1 );
   }

   /*!
    * \brief Checks if tid and uid of this TournamentParticipant-object matches given $tid and $uid.
    * \param $uid 0 to skip assertion on uid, e.g. for deletion
    * \see load_tournament_participant_by_id()
    */
   private function assert_tournament_participant( $dbgmsg, $tid, $uid )
   {
      if ( $this->tid != $tid )
         error('tournament_register_edit_not_allowed', $dbgmsg.".TP.assert_tp.check.tid($tid,$uid,{$this->ID})");
      if ( $uid > 0 && $this->uid != $uid )
         error('tournament_register_edit_not_allowed', $dbgmsg.".TP.assert_tp.check.uid($tid,$uid,{$this->ID})");
   }

   public function to_string()
   {
      return " ID=[{$this->ID}]"
            . ", tid=[{$this->tid}]"
            . ", uid=[{$this->uid}]"
            . sprintf( ", User=[%s]", $this->User->to_string() )
            . ", Status=[{$this->Status}]"
            . sprintf( ",Flags=[0x%x]", $this->Flags)
            . ", Rating=[{$this->Rating}]"
            . ", StartRound=[{$this->StartRound}]"
            . ", NextRound=[{$this->NextRound}]"
            . ", Created=[{$this->Created}]"
            . ", Lastchanged=[{$this->Lastchanged}]"
            . ", ChangedBy=[{$this->ChangedBy}]"
            . ", Comment=[{$this->Comment}]"
            . ", Notes=[{$this->Notes}]"
            . ", UserMessage=[{$this->UserMessage}]"
            . ", AdminMessage=[{$this->AdminMessage}]"
            . ", Finished=[{$this->Finished}]"
            . ", Won=[{$this->Won}]"
            . ", Lost=[{$this->Lost}]"
         ;
   }

   public function build_log_string()
   {
      $out = array();
      if ( $this->ID > 0 )
         $out[] = "ID=[{$this->ID}]";
      $out[] = "Status=[{$this->Status}]";
      $out[] = sprintf('Flags=[%s]', self::getFlagsText($this->Flags));
      if ( !is_null($this->Rating) )
         $out[] = "Rating=[{$this->Rating}]";
      $out[] = "StartRound=[{$this->StartRound}]";
      if ( $this->StartRound != $this->NextRound )
         $out[] = "NextRound=[{$this->NextRound}]";
      if ( (string)$this->Comment != '' )
         $out[] = "Comment=[{$this->Comment}]";
      if ( (string)$this->Notes != '' )
         $out[] = "Notes=[{$this->Notes}]";
      if ( (string)$this->UserMessage != '' )
         $out[] = "UserMsg=[{$this->UserMessage}]";
      if ( (string)$this->AdminMessage != '' )
         $out[] = "AdmMsg=[{$this->AdminMessage}]";
      if ( $this->Finished + $this->Won + $this->Lost > 0 )
         $out[] = "Fin/Won/Lost=[{$this->Finished}/{$this->Won}/{$this->Lost}]";
      return implode(', ', $out);
   }//build_log_string

   public function calc_init_status( $rating_use_mode )
   {
      return ( $rating_use_mode == TPROP_RUMODE_COPY_CUSTOM && !$this->User->hasRating() )
         ? TP_STATUS_APPLY
         : TP_STATUS_REGISTER;
   }


   /*!
    * \brief Returns true if removal of tournament-participant is authorised.
    * \param $t_status TOURNEY_STATUS_...
    */
   public function authorise_delete( $t_status )
   {
      if ( $t_status == TOURNEY_STATUS_REGISTER )
         return true;
      if ( $t_status == TOURNEY_STATUS_PLAY && $this->ID && $this->Status != TP_STATUS_REGISTER )
         return true;
      return false;
   }

   /*!
    * \brief Returns true if editing customized fields is authorised.
    * \param $t_status TOURNEY_STATUS_...
    */
   public function authorise_edit_customized( $t_status )
   {
      if ( $t_status == TOURNEY_STATUS_REGISTER )
         return true;
      if ( $t_status == TOURNEY_STATUS_PLAY )
      {
         if ( $this->ID <= 0 || $this->Status != TP_STATUS_REGISTER )
            return true;
      }
      return false;
   }

   /*!
    * \brief Returns true if TP-status-change from TP-REGISTER is authorised.
    * \param $t_status TOURNEY_STATUS_...
    */
   public function authorise_edit_register_status( $t_status, $tp_status_old, &$errors )
   {
      $allowed = false;
      if ( $t_status == TOURNEY_STATUS_REGISTER )
         $allowed = true;
      elseif ( $t_status == TOURNEY_STATUS_PLAY )
      {
         if ( $this->ID <= 0 ) // new
            $allowed = true;
         elseif ( $tp_status_old != TP_STATUS_REGISTER || $tp_status_old == $this->Status ) // existing TP
            $allowed = true;
      }

      if ( !$allowed && is_array($errors) )
         $errors[] = sprintf( T_('Registration status change [%s] to [%s] is not allowed for tournament status [%s].'),
                              (is_null($tp_status_old) ? NO_VALUE : self::getStatusText($tp_status_old)),
                              self::getStatusText($this->Status),
                              Tournament::getStatusText($t_status) );
      return $allowed;
   }//authorise_edit_register_status


   /*! \brief Inserts or updates tournament-participant in database. */
   public function persist()
   {
      if ( $this->ID > 0 )
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   public function insert()
   {
      $this->Created = $this->Lastchanged = $GLOBALS['NOW'];

      $this->checkData();
      $entityData = $this->fillEntityData(true);
      $result = $entityData->insert( "TournamentParticipant.insert(%s)" );
      if ( $result )
         $this->ID = mysql_insert_id();
      self::delete_cache_tournament_participant_counts( 'TournamentParticipant.insert', $this->tid );
      self::delete_cache_tournament_participant( 'TournamentParticipant.insert', $this->tid, $this->uid );
      return $result;
   }

   public function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $this->checkData();
      $entityData = $this->fillEntityData();
      $result = $entityData->update( "TournamentParticipant.update(%s)" );
      self::delete_cache_tournament_participant_counts( 'TournamentParticipant.update', $this->tid );
      self::delete_cache_tournament_participant( 'TournamentParticipant.update', $this->tid, $this->uid );
      return $result;
   }

   public function delete()
   {
      $entityData = $this->fillEntityData();
      $result = $entityData->delete( "TournamentParticipant.delete(%s)" );
      self::delete_cache_tournament_participant_counts( 'TournamentParticipant.delete', $this->tid );
      self::delete_cache_tournament_participant( 'TournamentParticipant.delete', $this->tid, $this->uid );
      return $result;
   }

   private function checkData()
   {
      if ( is_null($this->Status) )
         error('invalid_args', "TournamentParticipant.checkData.miss_status({$this->ID},{$this->tid})");
   }

   public function fillEntityData( $withCreated=false )
   {
      // checked fields: Rating/StartRound
      $data = $GLOBALS['ENTITY_TOURNAMENT_PARTICIPANT']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'Status', $this->Status );
      $data->set_value( 'Flags', $this->Flags );
      $data->set_value( 'Rating', $this->Rating );
      $data->set_value( 'StartRound', $this->StartRound );
      $data->set_value( 'NextRound', $this->NextRound );
      if ( $withCreated )
         $data->set_value( 'Created', $this->Created );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'ChangedBy', $this->ChangedBy );
      $data->set_value( 'Comment', $this->Comment );
      $data->set_value( 'Notes', $this->Notes );
      $data->set_value( 'UserMessage', $this->UserMessage );
      $data->set_value( 'AdminMessage', $this->AdminMessage );
      $data->set_value( 'Finished', $this->Finished );
      $data->set_value( 'Won', $this->Won );
      $data->set_value( 'Lost', $this->Lost );
      return $data;
   }


   // ------------ static functions ----------------------------

   /*!
    * \brief Returns count of TournamentParticipants for given tournament, TP-status, round and NextRound.
    * \param $tp_status one of TP_STATUS_... or array of TP_STATUS_... or null (=count all TP-stati)
    * \param $round 0 (=count all rounds), >0 (=count only given round)
    * \param $use_next_round true = match $next_round on TP.NextRound; false = match on TP.StartRound
    */
   public static function count_tournament_participants( $tid, $tp_status, $round, $use_next_round )
   {
      $qsql = new QuerySQL(
         SQLP_OPTS,   'SQL_SMALL_RESULT',
         SQLP_FIELDS, 'COUNT(*) AS X_Count',
         SQLP_FROM,   'TournamentParticipant',
         SQLP_WHERE,  "tid=$tid" );

      $stat_out = array();
      if ( is_array($tp_status) )
      {
         foreach ( $tp_status as $tp_stat )
            $stat_out[] = mysql_addslashes($tp_stat);
         if ( count($stat_out) )
            $qsql->add_part( SQLP_WHERE, "Status IN ('" . implode("','", $stat_out) . "')" );
      }
      elseif ( !is_null($tp_status) )
         $qsql->add_part( SQLP_WHERE, "Status='" . mysql_addslashes($tp_status) . "'" );

      $round_field = ($use_next_round) ? 'NextRound' : 'StartRound';
      if ( is_numeric($round) && $round > 0 )
         $qsql->add_part( SQLP_WHERE, "$round_field=$round" );

      $row = mysql_single_fetch( "TournamentParticipant:count_TPs($tid,".implode('/',$stat_out).",$round_field=$round)",
            $qsql->get_select() );
      return ($row) ? (int)$row['X_Count'] : 0;
   }//count_tournament_participants

   /*!
    * \brief Returns non-null array with count of TournamentParticipants for all (start-)rounds and TP-stati.
    * \return array( TPCOUNT_STATUS_ALL => sum-count for all rounds,
    *                TP.StartRound      => array( TP_STATUS_... => count, ... ))
    */
   public static function count_all_tournament_participants( $tid )
   {
      $result = db_query( "TournamentParticipant:count_all_TPs($tid)",
            "SELECT SQL_SMALL_RESULT Status, StartRound, COUNT(*) AS X_Count FROM TournamentParticipant " .
            "WHERE tid=$tid GROUP BY Status, StartRound" );

      $out = array();
      while ( $row = mysql_fetch_array($result) )
      {
         $status = @$row['Status'];
         $round = (int)@$row['StartRound'];
         $cnt = (int)@$row['X_Count'];
         if ( !isset($out[$round]) )
            $out[$round] = array();
         $out[$round][$status] = $cnt;
      }
      mysql_free_result($result);

      return $out;
   }//count_all_tournament_participants


   /*! \brief Returns db-fields to be used for query of TournamentParticipant-object. */
   public static function build_query_sql()
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_PARTICIPANT']->newQuerySQL('TP');
      $qsql->add_part( SQLP_FIELDS,
         'TPP.ID AS TPP_ID',
         'TPP.Name AS TPP_Name',
         'TPP.Handle AS TPP_Handle',
         'TPP.Country AS TPP_Country',
         'TPP.Rating2 AS TPP_Rating2',
         'TPP.RatingStatus AS TPP_RatingStatus',
         'TPP.RatedGames AS TPP_RatedGames',
         'TPP.Finished AS TPP_Finished',
         'UNIX_TIMESTAMP(TPP.Lastaccess) AS TPP_X_Lastaccess' );
      $qsql->add_part( SQLP_FROM,
         'INNER JOIN Players AS TPP ON TPP.ID=TP.uid' );
      return $qsql;
   }

   /*! \brief Returns TournamentParticipant-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $tp = new TournamentParticipant(
            // from TournamentParticipant
            @$row['ID'],
            @$row['tid'],
            @$row['uid'],
            User::new_from_row( $row, 'TPP_' ), // from Players TPP
            @$row['Status'],
            @$row['Flags'],
            @$row['Rating'],
            @$row['StartRound'],
            @$row['NextRound'],
            @$row['X_Created'],
            @$row['X_Lastchanged'],
            @$row['ChangedBy'],
            @$row['Comment'],
            @$row['Notes'],
            @$row['UserMessage'],
            @$row['AdminMessage'],
            @$row['Finished'],
            @$row['Won'],
            @$row['Lost']
         );
      return $tp;
   }

   /*!
    * \brief Checks, if user is participating for given tournament.
    * \return TournamentParticipant.Status if entry found; false otherwise
    */
   public static function isTournamentParticipant( $tid, $uid )
   {
      $tid = (int)$tid;
      $uid = (int)$uid;
      $row = mysql_single_fetch( "TournamentParticipant:isTournamentParticipant($tid,$uid)",
         "SELECT Status FROM TournamentParticipant WHERE tid=$tid AND uid=$uid LIMIT 1" );
      return ($row) ? @$row['Status'] : false;
   }

   /*!
    * \brief Loads and returns TournamentParticipant-object for given registration-id ($rid, which is PK).
    * \param $uid 0 to skip assertion on uid, e.g. for deletion
    * \return TournamentParticipant-object; or null if TP not found
    */
   public static function load_tournament_participant_by_id( $rid, $tid, $uid )
   {
      $rid = (int)$rid;

      $result = NULL;
      if ( $rid > 0 )
      {
         $qsql = self::build_query_sql();
         $qsql->add_part( SQLP_WHERE, "TP.ID=$rid" ); // primary-key
         $qsql->add_part( SQLP_LIMIT, '1' );

         $row = mysql_single_fetch( "TournamentParticipant:load_tournament_participant_by_id($rid)",
            $qsql->get_select() );
         if ( $row )
         {
            $result = self::new_from_row( $row );
            $result->assert_tournament_participant("TournamentParticipant:load_tournament_participant_by_id($rid)", $tid, $uid);
         }
      }
      return $result;
   }//load_tournament_participant_by_id

   /*!
    * \brief Loads and returns TournamentParticipant-object for given tournament-ID and user-id.
    * \return TournamentParticipant-object; NULL if no entry found.
    */
   public static function load_tournament_participant( $tid, $uid )
   {
      $tid = (int)$tid;
      $uid = (int)$uid;

      $result = NULL;
      if ( $tid > 0 && $uid > GUESTS_ID_MAX )
      {
         $qsql = self::build_query_sql();
         $qsql->add_part( SQLP_WHERE, "TP.tid=$tid", "TP.uid=$uid" );
         $qsql->add_part( SQLP_LIMIT, '1' );

         $row = mysql_single_fetch( "TournamentParticipant:load_tournament_participant($tid,$uid)",
            $qsql->get_select() );
         if ( $row )
            $result = self::new_from_row( $row );
      }
      return $result;
   }//load_tournament_participant

   /*! \brief Returns enhanced (passed) ListIterator with TournamentParticipant-objects of given tournament. */
   public static function load_tournament_participants( $iterator, $tid )
   {
      $qsql = self::build_query_sql();
      $qsql->add_part( SQLP_WHERE, "TP.tid='$tid'" );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentParticipant:load_tournament_participants($tid)", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $tourney = self::new_from_row( $row );
         $iterator->addItem( $tourney, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_tournament_participants

   /*! \brief Returns array( ID=>uid ) with TournamentParticipant.ID for given tournament and round. */
   public static function load_tournament_participants_registered( $tid, $round )
   {
      $query = "SELECT ID, uid FROM TournamentParticipant " .
         "WHERE tid=$tid AND Status='".TP_STATUS_REGISTER."' AND NextRound=$round";
      $result = db_query( "TournamentParticipant:load_tournament_participants_registered($tid,$round)", $query );

      $arr = array();
      while ( $row = mysql_fetch_array( $result ) )
         $arr[$row['ID']] = $row['uid'];
      mysql_free_result($result);
      return $arr;
   }//load_tournament_participants_registered

   /*!
    * \brief Returns array of row-arrays with [ rid=> TP.ID, uid => TP.uid ]
    *        for given tournament and round ordered according to given tourney-seed-order.
    */
   public static function load_registered_users_in_seedorder( $tid, $round, $seed_order )
   {
      // find all registered TPs (optimized)
      $qsql = new QuerySQL(
         SQLP_FIELDS,
            'TP.ID AS rid', 'TP.uid',
         SQLP_FROM,
            'TournamentParticipant AS TP',
         SQLP_WHERE,
            "TP.tid=$tid",
            "TP.Status='".TP_STATUS_REGISTER."'",
            "TP.NextRound=$round"
         );

      if ( $seed_order == TOURNEY_SEEDORDER_CURRENT_RATING )
      {
         $qsql->add_part( SQLP_FROM,  'INNER JOIN Players AS TPP ON TPP.ID=TP.uid' );
         $qsql->add_part( SQLP_ORDER, 'TPP.Rating2 DESC' );
      }
      elseif ( $seed_order == TOURNEY_SEEDORDER_REGISTER_TIME )
         $qsql->add_part( SQLP_ORDER, 'TP.Created ASC' );
      elseif ( $seed_order == TOURNEY_SEEDORDER_TOURNEY_RATING )
         $qsql->add_part( SQLP_ORDER, 'TP.Rating DESC' );

      // load all registered TPs (optimized = no TournamentParticipant-objects)
      $result = db_query( "TournamentParticipant:load_registered_users_in_seedorder.find_TPs($tid,$seed_order)",
         $qsql->get_select() );
      $arr_TPs = array();
      while ( $row = mysql_fetch_array($result) )
         $arr_TPs[] = $row;
      mysql_free_result($result);

      if ( $seed_order == TOURNEY_SEEDORDER_RANDOM )
         shuffle( $arr_TPs );

      return $arr_TPs;
   }//load_registered_users_in_seedorder

   /*! \brief Returns false, if there is at least one TP, that does not have a user-rating. */
   public static function check_rated_tournament_participants( $tid )
   {
      $row = mysql_single_fetch( "TournamentParticipant:check_rated_tournament_participants.exist_tp($tid)",
         "SELECT COUNT(*) AS X_Count FROM TournamentParticipant WHERE tid=$tid" );
      if ( (int)@$row['X_Count'] == 0 )
         return true;

      $row = mysql_single_fetch( "TournamentParticipant:check_rated_tournament_participants.find_unrated($tid)",
         "SELECT TP.uid FROM TournamentParticipant AS TP INNER JOIN Players AS P ON P.ID=TP.uid " .
         "WHERE TP.tid=$tid AND P.RatingStatus='".RATING_NONE."' LIMIT 1" );
      return !$row;
   }

   /*!
    * \brief Updates TournamentParticipant.Finished/Won/Lost-fields for given rid (=TP.ID).
    * \param $uid PK is $rid, but $uid is required for deleting TP-cache
    * \param $score relative score for user: <0 = game won, >0 = game lost for given user
    */
   public static function update_game_end_stats( $tid, $rid, $uid, $score )
   {
      $data = $GLOBALS['ENTITY_TOURNAMENT_PARTICIPANT']->newEntityData();
      $data->set_value( 'ID', $rid );
      $data->set_value( 'tid', $tid );
      $data->set_value( 'Lastchanged', $GLOBALS['NOW'] );
      $data->set_query_value( 'Finished', "Finished+1" );

      // NOTE: jigo is not counted as Win, because count can be calculated: Jigo = Finished - Won - Lost
      if ( $score < 0 )
         $data->set_query_value( 'Won', "Won+1" );
      if ( $score > 0 )
         $data->set_query_value( 'Lost', "Lost+1" );

      $result = $data->update( "TournamentParticipant:update_game_end_stats($tid,$rid,$score)" );
      self::delete_cache_tournament_participant( 'TournamentParticipant:update_game_end_stats', $tid, $uid );
      return $result;
   }//update_game_end_stats

   /*! \brief Updates Tournament.RegisteredTP if needed by comparing old/new TP-status. */
   public static function sync_tournament_registeredTP( $tid, $old_tp_status, $new_tp_status )
   {
      if ( $old_tp_status != TP_STATUS_REGISTER && $new_tp_status == TP_STATUS_REGISTER )
         Tournament::update_tournament_registeredTP( $tid, 1 );
      elseif ( $old_tp_status == TP_STATUS_REGISTER && $new_tp_status != TP_STATUS_REGISTER )
         Tournament::update_tournament_registeredTP( $tid, -1 );
   }

   /*!
    * \brief Deletes TournamentParticipant-entry for given tournament- and reg-id.
    * \note Updates Tournament.RegisteredTP if TP was REGISTERed. See also sync_tournament_registeredTP()
    *
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
   public static function delete_tournament_participant( $tid, $rid )
   {
      $tp = self::load_tournament_participant_by_id( $rid, $tid, 0 );
      if ( is_null($tp) )
         $result = true; // already deleted
      else
      {
         $result = $tp->delete();
         if ( $tp->Status == TP_STATUS_REGISTER )
            Tournament::update_tournament_registeredTP( $tid, -1 );
      }
      return $result;
   }//delete_tournament_participant

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   public static function getStatusText( $status=null, $with_value=false, $info_mode=false )
   {
      // lazy-init of texts
      if ( !isset(self::$ARR_TP_TEXTS['STATUS']) )
      {
         $arr = array();
         $arr[TP_STATUS_APPLY]    = T_('Applied#TP_status');
         $arr[TP_STATUS_REGISTER] = T_('Registered#TP_status');
         $arr[TP_STATUS_INVITE]   = T_('Invited#TP_status');
         self::$ARR_TP_TEXTS['STATUS'] = $arr;

         $arr = array();
         $arr['']                 = T_('You are not registered for this tournament.');
         $arr[TP_STATUS_APPLY]    = T_('Your registration needs to be verified by a tournament director.');
         $arr[TP_STATUS_REGISTER] = T_('You are registered for this tournament.');
         $arr[TP_STATUS_INVITE]   = T_('The invitation for this tournament needs your verification.');
         self::$ARR_TP_TEXTS['STATUS_INFO'] = $arr;
      }

      if ( $info_mode )
      {
         $key = 'STATUS_INFO';
         if ( !$status ) $status = '';
         if ( !isset(self::$ARR_TP_TEXTS[$key][$status]) )
            error('invalid_args', "TournamentParticipant:getStatusText($status,$key)");

         $status_str = self::$ARR_TP_TEXTS[$key][$status];
         return $status_str;
      }
      else
      {
         $key = 'STATUS';
         if ( is_null($status) )
            return self::$ARR_TP_TEXTS[$key];

         if ( !isset(self::$ARR_TP_TEXTS[$key][$status]) )
            error('invalid_args', "TournamentParticipant:getStatusText($status,$key)");
         $status_str = self::$ARR_TP_TEXTS[$key][$status];
         return ( $with_value ) ? sprintf( '%s (%s)', $status, $status_str ) : $status_str;
      }
   }//getStatusText

   public static function getStatusUserInfo( $status )
   {
      return self::getStatusText($status, false, true);
   }

   /*! \brief Returns flags-text for given int-bitmask or all flags-texts (if arg=null). */
   public static function getFlagsText( $flags=null )
   {
      // lazy-init of texts
      if ( !isset(self::$ARR_TP_TEXTS['FLAGS']) )
      {
         $arr = array();
         $arr[TP_FLAGS_INVITED]     = T_('Invited#TP_flag');
         $arr[TP_FLAGS_ACK_INVITE]  = T_('ACK-Invite#TP_flag');
         $arr[TP_FLAGS_ACK_APPLY]   = T_('ACK-Apply#TP_flag');
         $arr[TP_FLAGS_VIOLATE]     = T_('REG-Violate#TP_flag');
         self::$ARR_TP_TEXTS['FLAGS'] = $arr;
      }
      else
         $arr = self::$ARR_TP_TEXTS['FLAGS'];
      if ( is_null($flags) )
         return $arr;

      $out = array();
      foreach ( $arr as $flagmask => $flagtext )
         if ( $flags & $flagmask ) $out[] = $flagtext;
      return implode(', ', $out);
   }//getFlagsText

   public static function get_edit_tournament_status()
   {
      static $statuslist = array(
         TOURNEY_STATUS_REGISTER, TOURNEY_STATUS_PLAY
      );
      return $statuslist;
   }

   public static function delete_cache_tournament_participant_counts( $dbgmsg, $tid )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_TP_COUNT_ALL, "TPCountAll.$tid" );
      DgsCache::delete_group( $dbgmsg, CACHE_GRP_TP_COUNT, "TPCount.$tid" );
   }

   public static function delete_cache_tournament_participant( $dbgmsg, $tid, $uid )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_TPARTICIPANT, "TParticipant.$tid.$uid" );
   }

} // end of 'TournamentParticipant'

?>
