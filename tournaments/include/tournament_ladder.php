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

require_once 'include/db_classes.php';
require_once 'include/std_classes.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_properties.php';

 /*!
  * \file tournament_ladder.php
  *
  * \brief Functions to keep track of ladder-type tournaments: tables TournamentLadder
  */


 /*!
  * \class TournamentLadder
  *
  * \brief Class to manage TournamentLadder-table to keep track of ladder
  */

global $ENTITY_TOURNAMENT_LADDER; //PHP5
$ENTITY_TOURNAMENT_LADDER = new Entity( 'TournamentLadder',
      FTYPE_PKEY, 'tid', 'rid',
      FTYPE_INT,  'tid', 'rid', 'uid', 'Rank', 'BestRank',
      FTYPE_DATE, 'Created', 'RankChanged'
   );

class TournamentLadder
{
   var $tid;
   var $rid;
   var $uid;
   var $Created;
   var $RankChanged;
   var $Rank;
   var $BestRank;

   /*! \brief Constructs TournamentLadder-object with specified arguments. */
   function TournamentLadder( $tid=0, $rid=0, $uid=0, $created=0, $rank_changed=0, $rank=0, $bestrank=0 )
   {
      $this->tid = (int)$tid;
      $this->rid = (int)$rid;
      $this->uid = (int)$uid;
      $this->Created = (int)$created;
      $this->RankChanged = (int)$rank_changed;
      $this->Rank = $rank;
      $this->BestRank = $bestrank;
   }

   function to_string()
   {
      return print_r($this, true);
   }

   /*! \brief Inserts or updates tournament-ladder in database. */
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
      $this->Created = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->insert( "TournamentLadder::insert(%s)" );
   }

   function update()
   {
      $entityData = $this->fillEntityData();
      return $entityData->update( "TournamentLadder::update(%s)" );
   }

   function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "TournamentLadder::delete(%s)" );
   }

   function fillEntityData( &$data=null )
   {
      if( is_null($data) )
         $data = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->newEntityData();
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'rid', $this->rid );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'Created', $this->Created );
      $data->set_value( 'RankChanged', $this->RankChanged );
      $data->set_value( 'Rank', $this->Rank );
      $data->set_value( 'BestRank', $this->BestRank );
      return $data;
   }

   /*!
    * \brief Removes user given by User-object from ladder with given tournament tid and
    *        remove TP if $remove_all=true.
    * \return error-list (empty on success)
    */
   function remove_user_from_ladder( $remove_all )
   {
      //TODO do consistency-checks (no running games for tournament)
      //if( ... ) return array( T_('error...') );

      ta_begin();
      {
         $this->delete();
         //TODO update rank after user-removal from ladder

         $tladder_check = TournamentLadder::load_tournament_ladder_by_uid( $this->tid, $this->uid );
         if( is_null($tladder_check) && $remove_all ) // really deleted ?
            TournamentParticipant::delete_tournament_participant( $this->tid, $this->rid );
      }
      ta_end();

      return array();
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of TournamentLadder-objects for given tournament-id. */
   function build_query_sql( $tid )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->newQuerySQL('TL');
      $qsql->add_part( SQLP_WHERE, "TL.tid='$tid'" );
      return $qsql;
   }

   /*! \brief Returns TournamentLadder-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $tl = new TournamentLadder(
            // from TournamentLadder
            @$row['tid'],
            @$row['rid'],
            @$row['uid'],
            @$row['X_Created'],
            @$row['X_RankChanged'],
            @$row['Rank'],
            @$row['BestRank']
         );
      return $tl;
   }

   /*! \brief Returns max Rank of TournamentLadder for given tournament-id; 0 if no entries found. */
   function load_max_rank( $tid )
   {
      $qsql = TournamentLadder::build_query_sql( $tid );
      $qsql->clear_parts( SQLP_FIELDS );
      $qsql->add_part( SQLP_FIELDS, 'IFNULL(MAX(Rank),0) AS X_Rank' );
      $row = mysql_single_fetch( "TournamentLadder::get_max_rank($tid)", $qsql->get_select() );
      return ( $row ) ? (int)@$row['X_Rank'] : 0;
   }

   /*!
    * \brief Loads and returns TournamentLadder-object for given tournament-ID and user-id;
    * \return NULL if nothing found; TournamentLadder otherwise
    */
   function load_tournament_ladder_by_uid( $tid, $uid )
   {
      if( $tid <=0 || $uid <= GUESTS_ID_MAX )
         return NULL;

      $qsql = TournamentLadder::build_query_sql( $tid );
      $qsql->add_part( SQLP_WHERE, "TL.uid='$uid'" );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "TournamentLadder.load_tournament_ladder_by_uid($tid,$uid)",
         $qsql->get_select() );
      return ($row) ? TournamentLadder::new_from_row($row) : NULL;
   }

   /*! \brief Returns enhanced (passed) ListIterator with TournamentLadder-objects for given tournament-id. */
   function load_tournament_ladder( $iterator, $tid )
   {
      $qsql = TournamentLadder::build_query_sql( $tid );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentLadder.load_tournament_ladder", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $tourney = TournamentLadder::new_from_row( $row );
         $iterator->addItem( $tourney, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

   /*! \brief Adds user given by User-object for tournament tid at bottom of ladder. */
   function add_user_to_ladder( $tid, $user )
   {
      if( !is_a($user, 'User') )
         error('invalid_user', "TournamentLadder::add_user_to_ladder.check_user($tid)");
      $uid = $user->ID;

      $tp = TournamentParticipant::load_tournament_participant( $tid, $uid, 0 );
      if( is_null($tp) )
         error('invalid_args', "TournamentLadder::add_user_to_ladder.load_tp($tid,$uid)");

      // check pre-conditions
      if( $tp->Status != TP_STATUS_REGISTER )
         error('tournament_participant_invalid_status',
               "TournamentLadder::add_user_to_ladder.check_tp_status($tid,$uid,{$tp->Status})");

      return TournamentLadder::add_participant_to_ladder( $tid, $tp->ID, $uid );
   }

   /*! \brief Adds TP configured by rid,uid for tournament tid at bottom of ladder. */
   function add_participant_to_ladder( $tid, $rid, $uid )
   {
      global $NOW;
      $table = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->table;
      $query = "INSERT INTO $table (tid,rid,uid,Created,RankChanged,Rank,BestRank) "
             . "SELECT $tid, $rid, $uid, FROM_UNIXTIME($NOW), FROM_UNIXTIME($NOW), "
               . "IFNULL(MAX(Rank),0)+1 AS Rank, 0 AS BestRank "
               . "FROM $table WHERE tid=$tid";
      return db_query( "TournamentLadder::add_participant_to_ladder.insert(tid[$tid],rid[$rid])", $query );
   }

   /*! \brief Delete complete ladder for given tournament-id. */
   function delete_ladder( $tid )
   {
      $table = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->table;
      $query = "DELETE FROM $table WHERE tid=$tid";
      return db_query( "TournamentLadder::delete_ladder(tid[$tid])", $query );
   }

   /*! \brief Seeds ladder with all registered TPs. */
   function seed_ladder( $tourney, $tprops, $seed_order )
   {
      if( !is_a($tourney, 'Tournament') && $tourney->ID <= 0 )
         error('unknown_tournament', "TournamentLadder::seed_ladder.check_tid($seed_order)");
      $tid = $tourney->ID;

      list( $def, $arr_seed_order ) = $tprops->build_ladder_seed_order();
      if( !isset($arr_seed_order[$seed_order]) )
         error('invalid_args', "TournamentLadder::seed_ladder.check_seed_order($tid,$seed_order)");

      // find all registered TPs (optimized)
      $table = $GLOBALS['ENTITY_TOURNAMENT_PARTICIPANT']->table;
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS, 'TP.ID AS rid', 'TP.uid' );
      $qsql->add_part( SQLP_FROM,   "$table AS TP" );
      $qsql->add_part( SQLP_WHERE,  "TP.tid=$tid", "TP.Status='".TP_STATUS_REGISTER."'" );
      if( $seed_order == LADDER_SEEDORDER_CURRENT_RATING )
      {
         $qsql->add_part( SQLP_FROM,  'INNER JOIN Players AS TPP ON TPP.ID=TP.uid' );
         $qsql->add_part( SQLP_ORDER, 'TPP.Rating2 DESC' );
      }
      elseif( $seed_order == LADDER_SEEDORDER_REGISTER_TIME )
         $qsql->add_part( SQLP_ORDER, 'TP.Created ASC' );
      elseif( $seed_order == LADDER_SEEDORDER_TOURNEY_RATING )
         $qsql->add_part( SQLP_ORDER, 'TP.Rating DESC' );

      // load all registered TPs (optimized = no TournamentParticipant-objects)
      $result = db_query( "TournamentLadder::seed_ladder.load_tournament_participants($tid,$seed_order)",
         $qsql->get_select() );
      $arr_TPs = array();
      while( $row = mysql_fetch_array($result) )
         $arr_TPs[] = $row;
      mysql_free_result($result);

      if( $seed_order == LADDER_SEEDORDER_RANDOM )
         shuffle( $arr_TPs );

      // add all TPs to ladder
      global $NOW;
      $data = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->newEntityData();
      $arr_inserts = array();
      $rank = TournamentLadder::load_max_rank($tid);
      foreach( $arr_TPs as $row )
      {
         ++$rank;
         $tladder = new TournamentLadder( $tid, $row['rid'], $row['uid'], $NOW, $NOW, $rank, 0 );
         $tladder->fillEntityData( $data );
         $arr_inserts[] = $data->build_sql_insert_values();
      }
      unset($arr_TPs);

      // insert all registered TPs to ladder
      $cnt = count($arr_inserts);
      $seed_query = $data->build_sql_insert_values(true) . implode(', ', $arr_inserts);
      return db_query( "TournamentLadder::seed_ladder.insert_all(tid[$tid],$seed_order,#{$cnt})", $seed_query );
   }//seed_ladder

   /*!
    * \brief Checks if all participants (arr_TPS[rid=>uid]) are registered in ladder;
    *        auto-removing bad registrations.
    */
   function check_participant_registrations( $tid, $arr_TPs )
   {
      $table = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->table;
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS, 'rid', 'uid' );
      $qsql->add_part( SQLP_FROM,   $table );
      $qsql->add_part( SQLP_WHERE,  "tid=$tid" );
      $result = db_query( "TournamentLadder::check_participant_registrations.load_ladder($tid)",
         $qsql->get_select() );

      $arr_miss = array() + $arr_TPs; // registered TP, but not in ladder
      $arr_bad  = array(); // user in ladder, but not a registered TP
      while( $row = mysql_fetch_array($result) )
      {
         $rid = $row['rid'];
         $uid = $row['uid'];
         if( isset($arr_TPs[$rid]) )
            unset($arr_miss[$rid]);
         else
            $arr_bad[$rid] = $uid;
      }
      mysql_free_result($result);

      global $base_path;
      $errors = array();
      if( count($arr_miss) )
      {
         $arr = array();
         foreach( $arr_miss as $rid => $uid )
            $arr[] = anchor( $base_path."tournaments/ladder/admin.php?tid=$tid".URI_AMP."uid=$uid", $uid );
         $errors[] = T_('Found registered tournament participants not added to ladder')
            . "<br>\n" . sprintf( T_('users [%s]#T_ladder'), implode(', ', $arr) );
      }
      if( count($arr_bad) )
      {
         $arr = array();
         foreach( $arr_bad as $rid => $uid )
            $arr[] = anchor( $base_path."tournaments/edit_participant.php?tid=$tid".URI_AMP."uid=$uid", $uid );
         $errors[] = T_('Auto-removing of found users added to ladder without registration')
            . "<br>\n" . sprintf( T_('users [%s]#T_ladder'), implode(', ', $arr) );

         // removing bad ladder-registrations
         foreach( $arr_bad as $rid => $uid )
         {
            $tladder = new TournamentLadder( $tid, $rid );
            $tladder->delete();
         }
      }
      return $errors;
   }//check_participant_registrations

   function get_edit_tournament_status()
   {
      static $statuslist = array( TOURNEY_STATUS_PAIR, TOURNEY_STATUS_PLAY );
      return $statuslist;
   }

} // end of 'TournamentLadder'
?>
