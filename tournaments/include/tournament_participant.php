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

// lazy-init in TournamentParticipant::get..Text()-funcs
global $ARR_GLOBALS_TOURNAMENT_PARTICIPANT; //PHP5
$ARR_GLOBALS_TOURNAMENT_PARTICIPANT = array();

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
   var $ID;
   var $tid;
   var $uid;
   var $Status; // null | TP_STATUS_...
   var $Flags;
   var $Rating; // NO_RATING | valid-rating
   var $StartRound;
   var $NextRound;
   var $Created;
   var $Lastchanged;
   var $ChangedBy;
   var $Comment;
   var $Notes;
   var $UserMessage;
   var $AdminMessage;
   var $Finished;
   var $Won;
   var $Lost;

   // non-DB fields

   var $User; // User-object

   /*! \brief Constructs TournamentParticipant-object with specified arguments. */
   function TournamentParticipant( $id=0, $tid=0, $uid=0, $user=NULL, $status=null, $flags=0,
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
      $this->User = (is_a($user, 'User')) ? $user : new User( $this->uid );
   }

   function setStatus( $status )
   {
      if( !is_null($status) && !preg_match( "/^(".CHECK_TP_STATUS.")$/", $status ) )
         error('invalid_args', "TournamentParticipant.setStatus($status)");
      $this->Status = $status;
   }

   function setRating( $rating )
   {
      $this->Rating = TournamentUtils::normalizeRating( $rating );
   }

   function hasRating()
   {
      return (abs($this->Rating) < OUT_OF_RATING);
   }

   function setStartRound( $start_round )
   {
      $this->StartRound = limit( (int)$start_round, 1, 255, 1 );
   }

   /*!
    * \brief Checks if tid and uid of this TournamentParticipant-object matches given $tid and $uid.
    * \param $uid 0 to skip assertion on uid, e.g. for deletion
    * \see load_tournament_participant_by_id()
    */
   function assert_tournament_participant( $dbgmsg, $tid, $uid )
   {
      if( $this->tid != $tid )
         error('tournament_register_edit_not_allowed', $dbgmsg.".TP.assert_tp.check.tid($tid,$uid,{$this->ID})");
      if( $uid > 0 && $this->uid != $uid )
         error('tournament_register_edit_not_allowed', $dbgmsg.".TP.assert_tp.check.uid($tid,$uid,{$this->ID})");
   }

   function to_string()
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

   function build_log_string()
   {
      $out = array();
      if( $this->ID > 0 )
         $out[] = "ID=[{$this->ID}]";
      $out[] = "Status=[{$this->Status}]";
      $out[] = sprintf('Flags=[0x%x]', $this->Flags);
      if( !is_null($this->Rating) )
         $out[] = "Rating=[{$this->Rating}]";
      $out[] = "StartRound=[{$this->StartRound}]";
      if( $this->StartRound != $this->NextRound )
         $out[] = "NextRound=[{$this->NextRound}]";
      if( (string)$this->Comment != '' )
         $out[] = "Comment=[{$this->Comment}]";
      if( (string)$this->Notes != '' )
         $out[] = "Notes=[{$this->Notes}]";
      if( (string)$this->UserMessage != '' )
         $out[] = "UserMsg=[{$this->UserMessage}]";
      if( (string)$this->AdminMessage != '' )
         $out[] = "AdmMsg=[{$this->AdminMessage}]";
      if( $this->Finished + $this->Won + $this->Lost > 0 )
         $out[] = "Fin/Won/Lost=[{$this->Finished}/{$this->Won}/{$this->Lost}]";
      return implode(', ', $out);
   }//build_log_string

   function calc_init_status( $rating_use_mode )
   {
      return ( $rating_use_mode == TPROP_RUMODE_COPY_CUSTOM && !$this->User->hasRating() )
         ? TP_STATUS_APPLY
         : TP_STATUS_REGISTER;
   }


   /*! \brief Returns true if removal of tournament-participant is authorised; t_status is tournament-status. */
   function authorise_delete( $t_status )
   {
      if( $t_status == TOURNEY_STATUS_REGISTER )
         return true;
      if( $t_status == TOURNEY_STATUS_PLAY && $this->ID && $this->Status != TP_STATUS_REGISTER )
         return true;
      return false;
   }

   /*! \brief Returns true if editing customized fields is authorised; t_status is tournament-status. */
   function authorise_edit_customized( $t_status )
   {
      if( $t_status == TOURNEY_STATUS_REGISTER )
         return true;
      if( $t_status == TOURNEY_STATUS_PLAY )
      {
         if( $this->ID <= 0 || $this->Status != TP_STATUS_REGISTER )
            return true;
      }
      return false;
   }

   /*! \brief Returns true if TP-status-change from TP-REGISTER is authorised; t_status is tournament-status. */
   function authorise_edit_register_status( $t_status, $tp_status_old, &$errors )
   {
      $allowed = false;
      if( $t_status == TOURNEY_STATUS_REGISTER )
         $allowed = true;
      elseif( $t_status == TOURNEY_STATUS_PLAY )
      {
         if( $this->ID <= 0 ) // new
            $allowed = true;
         elseif( $tp_status_old != TP_STATUS_REGISTER || $tp_status_old == $this->Status ) // existing TP
            $allowed = true;
      }

      if( !$allowed && is_array($errors) )
         $errors[] = sprintf( T_('Registration status change [%s] to [%s] is not allowed for tournament status [%s].'),
                              (is_null($tp_status_old) ? NO_VALUE : TournamentParticipant::getStatusText($tp_status_old)),
                              TournamentParticipant::getStatusText($this->Status),
                              Tournament::getStatusText($t_status) );
      return $allowed;
   }


   /*! \brief Inserts or updates tournament-participant in database. */
   function persist()
   {
      if( $this->ID > 0 )
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   function insert()
   {
      $this->Created = $this->Lastchanged = $GLOBALS['NOW'];

      $this->checkData();
      $entityData = $this->fillEntityData(true);
      $result = $entityData->insert( "TournamentParticipant::insert(%s)" );
      if( $result )
         $this->ID = mysql_insert_id();
      TournamentParticipant::delete_cache_tournament_participant_count( 'TournamentParticipant.insert', $this->tid );
      TournamentParticipant::delete_cache_tournament_participant( 'TournamentParticipant.insert', $this->tid, $this->uid );
      return $result;
   }

   function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $this->checkData();
      $entityData = $this->fillEntityData();
      $result = $entityData->update( "TournamentParticipant::update(%s)" );
      TournamentParticipant::delete_cache_tournament_participant_count( 'TournamentParticipant.update', $this->tid );
      TournamentParticipant::delete_cache_tournament_participant( 'TournamentParticipant.update', $this->tid, $this->uid );
      return $result;
   }

   function delete()
   {
      $entityData = $this->fillEntityData();
      $result = $entityData->delete( "TournamentParticipant::delete(%s)" );
      TournamentParticipant::delete_cache_tournament_participant_count( 'TournamentParticipant.delete', $this->tid );
      TournamentParticipant::delete_cache_tournament_participant( 'TournamentParticipant.delete', $this->tid, $this->uid );
      return $result;
   }

   function checkData()
   {
      if( is_null($this->Status) )
         error('invalid_args', "TournamentParticipant.checkData.miss_status({$this->ID},{$this->tid})");
   }

   function fillEntityData( $withCreated=false )
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
      if( $withCreated )
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
    * \brief Returns non-null array with count of TournamentParticipants for given tournament and TP-status.
    * \return array( TP_STATUS_... => count, TPCOUNT_STATUS_ALL => summary-count )
    */
   function count_tournament_participants( $tid, $status=NULL )
   {
      $query_status = (is_null($status)) ? '' : " AND Status='".mysql_addslashes($status)."'";
      $result = db_query( "TournamentParticipant.count_tournament_participants($tid,$status)",
            "SELECT SQL_SMALL_RESULT Status, COUNT(*) AS X_Count FROM TournamentParticipant "
            . "WHERE tid='$tid' $query_status GROUP BY Status" );

      $out = array();
      if( !is_null($status) )
         $out[$status] = 0;
      $sum = 0;
      while( $row = mysql_fetch_array( $result ) )
      {
         $cnt = (int)@$row['X_Count'];
         $out[$row['Status']] = $cnt;
         $sum += $cnt;
      }
      mysql_free_result($result);
      $out[TPCOUNT_STATUS_ALL] = $sum;
      return $out;
   }

   /*! \brief Returns db-fields to be used for query of TournamentParticipant-object. */
   function build_query_sql()
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
   function new_from_row( $row )
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
   function isTournamentParticipant( $tid, $uid )
   {
      $tid = (int)$tid;
      $uid = (int)$uid;
      $row = mysql_single_fetch( "TournamentParticipant.isTournamentParticipant($tid,$uid)",
         "SELECT Status FROM TournamentParticipant WHERE tid=$tid AND uid=$uid LIMIT 1" );
      return ($row) ? @$row['Status'] : false;
   }

   /*!
    * \brief Loads and returns TournamentParticipant-object for given registration-id ($rid, which is PK).
    * \param $uid 0 to skip assertion on uid, e.g. for deletion
    * \return TournamentParticipant-object; or null if TP not found
    */
   function load_tournament_participant_by_id( $rid, $tid, $uid )
   {
      $rid = (int)$rid;

      $result = NULL;
      if( $rid > 0 )
      {
         $qsql = TournamentParticipant::build_query_sql();
         $qsql->add_part( SQLP_WHERE, "TP.ID=$rid" ); // primary-key
         $qsql->add_part( SQLP_LIMIT, '1' );

         $row = mysql_single_fetch( "TournamentParticipant.load_tournament_participant_by_id($rid)",
            $qsql->get_select() );
         if( $row )
         {
            $result = TournamentParticipant::new_from_row( $row );
            $result->assert_tournament_participant("TournamentParticipant.load_tournament_participant_by_id($rid)", $tid, $uid);
         }
      }
      return $result;
   }

   /*!
    * \brief Loads and returns TournamentParticipant-object for given tournament-ID and user-id.
    * \return TournamentParticipant-object; NULL if no entry found.
    */
   function load_tournament_participant( $tid, $uid )
   {
      $tid = (int)$tid;
      $uid = (int)$uid;

      $result = NULL;
      if( $tid > 0 && $uid > GUESTS_ID_MAX )
      {
         $qsql = TournamentParticipant::build_query_sql();
         $qsql->add_part( SQLP_WHERE, "TP.tid=$tid", "TP.uid=$uid" );
         $qsql->add_part( SQLP_LIMIT, '1' );

         $row = mysql_single_fetch( "TournamentParticipant.load_tournament_participant($tid,$uid)",
            $qsql->get_select() );
         if( $row )
            $result = TournamentParticipant::new_from_row( $row );
      }
      return $result;
   }

   /*! \brief Returns enhanced (passed) ListIterator with TournamentParticipant-objects of given tournament. */
   function load_tournament_participants( $iterator, $tid )
   {
      $qsql = TournamentParticipant::build_query_sql();
      $qsql->add_part( SQLP_WHERE, "TP.tid='$tid'" );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentParticipant.load_tournament_participants($tid)", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $tourney = TournamentParticipant::new_from_row( $row );
         $iterator->addItem( $tourney, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

   /*! \brief Returns array( ID=>uid ) with TournamentParticipant.ID for given tournament. */
   function load_tournament_participants_registered( $tid )
   {
      $query = "SELECT ID, uid FROM TournamentParticipant WHERE tid=$tid AND Status='".TP_STATUS_REGISTER."'";
      $result = db_query( "TournamentParticipant.load_tournament_participants_registered($tid)", $query );

      $arr = array();
      while( $row = mysql_fetch_array( $result ) )
         $arr[$row['ID']] = $row['uid'];
      mysql_free_result($result);
      return $arr;
   }

   /*!
    * \brief Returns array of row-arrays with [ rid=> TP.ID, uid => TP.uid ]
    *        for given tournament ordered according to given tourney-seed-order.
    */
   function load_registered_users_in_seedorder( $tid, $seed_order )
   {
      // find all registered TPs (optimized)
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS, 'TP.ID AS rid', 'TP.uid' );
      $qsql->add_part( SQLP_FROM,   "TournamentParticipant AS TP" );
      $qsql->add_part( SQLP_WHERE,  "TP.tid=$tid", "TP.Status='".TP_STATUS_REGISTER."'" );
      if( $seed_order == TOURNEY_SEEDORDER_CURRENT_RATING )
      {
         $qsql->add_part( SQLP_FROM,  'INNER JOIN Players AS TPP ON TPP.ID=TP.uid' );
         $qsql->add_part( SQLP_ORDER, 'TPP.Rating2 DESC' );
      }
      elseif( $seed_order == TOURNEY_SEEDORDER_REGISTER_TIME )
         $qsql->add_part( SQLP_ORDER, 'TP.Created ASC' );
      elseif( $seed_order == TOURNEY_SEEDORDER_TOURNEY_RATING )
         $qsql->add_part( SQLP_ORDER, 'TP.Rating DESC' );

      // load all registered TPs (optimized = no TournamentParticipant-objects)
      $result = db_query( "TournamentParticipant::load_registered_users_ordered.find_TPs($tid,$seed_order)",
         $qsql->get_select() );
      $arr_TPs = array();
      while( $row = mysql_fetch_array($result) )
         $arr_TPs[] = $row;
      mysql_free_result($result);

      if( $seed_order == TOURNEY_SEEDORDER_RANDOM )
         shuffle( $arr_TPs );

      return $arr_TPs;
   }//load_registered_users_ordered

   /*! \brief Returns false, if there is at least one TP, that does not have a user-rating. */
   function check_rated_tournament_participants( $tid )
   {
      $row = mysql_single_fetch( "TournamentParticipant::check_rated_tournament_participants.find_unrated($tid)",
         "SELECT TP.uid FROM TournamentParticipant AS TP INNER JOIN Players AS P ON P.ID=TP.uid " .
         "WHERE TP.tid=$tid AND P.RatingStatus='NONE' LIMIT 1" );
      return ( $row ) ? false : true;
   }

   /*!
    * \brief Updates TournamentParticipant.Finished/Won/Lost-fields for given rid (=TP.ID).
    * \param $uid PK is $rid, but $uid is required for deleting TP-cache
    * \param $score relative score for user: <0 = game won, >0 = game lost for given user
    */
   function update_game_end_stats( $tid, $rid, $uid, $score )
   {
      $data = $GLOBALS['ENTITY_TOURNAMENT_PARTICIPANT']->newEntityData();
      $data->set_value( 'ID', $rid );
      $data->set_value( 'tid', $tid );
      $data->set_value( 'Lastchanged', $GLOBALS['NOW'] );
      $data->set_query_value( 'Finished', "Finished+1" );
      if( $score < 0 )
         $data->set_query_value( 'Won', "Won+1" );
      if( $score > 0 )
         $data->set_query_value( 'Lost', "Lost+1" );

      $result = $data->update( "TournamentParticipant::update_game_end_stats($tid,$rid,$score)" );
      TournamentParticipant::delete_cache_tournament_participant( 'TournamentParticipant::update_game_end_stats', $tid, $uid );
      return $result;
   }

   /*! \brief Updates Tournament.RegisteredTP if needed by comparing old/new TP-status. */
   function sync_tournament_registeredTP( $tid, $old_tp_status, $new_tp_status )
   {
      if( $old_tp_status != TP_STATUS_REGISTER && $new_tp_status == TP_STATUS_REGISTER )
         Tournament::update_tournament_registeredTP( $tid, 1 );
      elseif( $old_tp_status == TP_STATUS_REGISTER && $new_tp_status != $old_tp_status )
         Tournament::update_tournament_registeredTP( $tid, -1 );
   }

   /*!
    * \brief Deletes TournamentParticipant-entry for given tournament- and reg-id.
    * \note Updates Tournament.RegisteredTP if TP was REGISTERed. See also sync_tournament_registeredTP()
    *
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
   function delete_tournament_participant( $tid, $rid )
   {
      $tp = TournamentParticipant::load_tournament_participant_by_id( $rid, $tid, 0 );
      if( is_null($tp) )
         $result = true; // already deleted
      else
      {
         $result = $tp->delete();
         if( $tp->Status == TP_STATUS_REGISTER )
            Tournament::update_tournament_registeredTP( $tid, -1 );
      }
      return $result;
   }

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   function getStatusText( $status=null, $with_value=false, $info_mode=false )
   {
      global $ARR_GLOBALS_TOURNAMENT_PARTICIPANT;

      // lazy-init of texts
      if( !isset($ARR_GLOBALS_TOURNAMENT_PARTICIPANT['STATUS']) )
      {
         $arr = array();
         $arr[TP_STATUS_APPLY]    = T_('Apply#TP_status');
         $arr[TP_STATUS_REGISTER] = T_('Register#TP_status');
         $arr[TP_STATUS_INVITE]   = T_('Invite#TP_status');
         $ARR_GLOBALS_TOURNAMENT_PARTICIPANT['STATUS'] = $arr;

         $arr = array();
         $arr['']                 = T_('You are not registered for this tournament.');
         $arr[TP_STATUS_APPLY]    = T_('Your registration needs to be verified by a tournament director.');
         $arr[TP_STATUS_REGISTER] = T_('You are registered for this tournament.');
         $arr[TP_STATUS_INVITE]   = T_('The invitation for this tournament needs your verification.');
         $ARR_GLOBALS_TOURNAMENT_PARTICIPANT['STATUS_INFO'] = $arr;
      }

      if( $info_mode )
      {
         $key = 'STATUS_INFO';
         if( !$status ) $status = '';
         if( !isset($ARR_GLOBALS_TOURNAMENT_PARTICIPANT[$key][$status]) )
            error('invalid_args', "TournamentParticipant.getStatusText($status,$key)");

         $status_str = $ARR_GLOBALS_TOURNAMENT_PARTICIPANT[$key][$status];
         return $status_str;
      }
      else
      {
         $key = 'STATUS';
         if( is_null($status) )
            return $ARR_GLOBALS_TOURNAMENT_PARTICIPANT[$key];

         if( !isset($ARR_GLOBALS_TOURNAMENT_PARTICIPANT[$key][$status]) )
            error('invalid_args', "TournamentParticipant.getStatusText($status,$key)");
         $status_str = $ARR_GLOBALS_TOURNAMENT_PARTICIPANT[$key][$status];
         return ( $with_value ) ? sprintf( '%s (%s)', $status, $status_str ) : $status_str;
      }
   }

   function getStatusUserInfo( $status )
   {
      return TournamentParticipant::getStatusText($status, false, true);
   }

   /*! \brief Returns flags-text for given int-bitmask or all flags-texts (if arg=null). */
   function getFlagsText( $flags=null )
   {
      global $ARR_GLOBALS_TOURNAMENT_PARTICIPANT;

      // lazy-init of texts
      if( !isset($ARR_GLOBALS_TOURNAMENT_PARTICIPANT['FLAGS']) )
      {
         $arr = array();
         $arr[TP_FLAGS_INVITED]     = T_('Invited#TP_flag');
         $arr[TP_FLAGS_ACK_INVITE]  = T_('ACK-Invite#TP_flag');
         $arr[TP_FLAGS_ACK_APPLY]   = T_('ACK-Apply#TP_flag');
         $arr[TP_FLAGS_VIOLATE]     = T_('REG-Violate#TP_flag');
         $ARR_GLOBALS_TOURNAMENT_PARTICIPANT['FLAGS'] = $arr;
      }
      else
         $arr = $ARR_GLOBALS_TOURNAMENT_PARTICIPANT['FLAGS'];
      if( is_null($flags) )
         return $arr;

      $out = array();
      foreach( $arr as $flagmask => $flagtext )
         if( $flags & $flagmask ) $out[] = $flagtext;
      return implode(', ', $out);
   }

   function get_edit_tournament_status()
   {
      static $statuslist = array(
         TOURNEY_STATUS_REGISTER, TOURNEY_STATUS_PLAY
      );
      return $statuslist;
   }

   function delete_cache_tournament_participant_count( $dbgmsg, $tid )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_TP_COUNT, "TPCount.$tid" );
   }

   function delete_cache_tournament_participant( $dbgmsg, $tid, $uid )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_TPARTICIPANT, "TParticipant.$tid.$uid" );
   }

} // end of 'TournamentParticipant'

?>
