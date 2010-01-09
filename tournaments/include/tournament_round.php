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

require_once( 'include/std_classes.php' );

 /*!
  * \file tournament_round.php
  *
  * \brief Functions for handling tournament rounds (for pairing): tables TournamentRound
  */


 /*!
  * \class TournamentRound
  *
  * \brief Class to manage TournamentRound-table with pairing-related tournament-settings
  */

define('TROUND_STATUS_INIT',     'INIT');
define('TROUND_STATUS_POOLINIT', 'POOLINIT');
define('TROUND_STATUS_PAIRINIT', 'PAIRINIT');
define('TROUND_STATUS_GAMEINIT', 'GAMEINIT');
define('TROUND_STATUS_DONE',     'DONE');
define('CHECK_TROUND_STATUS', 'INIT|POOLINIT|PAIRINIT|GAMEINIT|DONE');


// lazy-init in TournamentRound::get..Text()-funcs
global $ARR_GLOBALS_TOURNAMENT_ROUND; //PHP5
$ARR_GLOBALS_TOURNAMENT_ROUND = array();

global $ENTITY_TOURNAMENT_ROUND; //PHP5
$ENTITY_TOURNAMENT_ROUND = new Entity( 'TournamentRound',
      FTYPE_PKEY, 'tid', 'Round',
      FTYPE_INT,  'ID', 'Round', 'RulesID', 'MinPoolSize', 'MaxPoolSize', 'PoolCount',
      FTYPE_DATE, 'Lastchanged',
      FTYPE_ENUM, 'Status'
   );

class TournamentRound
{
   var $tid;
   var $Round;
   var $RulesID;
   var $Lastchanged;
   var $Status;
   var $MinPoolSize;
   var $MaxPoolSize;
   var $PoolCount;

   /*! \brief Constructs TournamentRound-object with specified arguments. */
   function TournamentRound( $tid=0, $round=0, $rules_id=0, $lastchanged=0,
         $status=TROUND_STATUS_INIT, $min_pool_size=0, $max_pool_size=0,
         $pool_count=0 )
   {
      $this->tid = (int)$tid;
      $this->Round = (int)$round;
      $this->RulesID = (int)$rules_id;
      $this->Lastchanged = (int)$lastchanged;
      $this->setStatus( $status );
      $this->MinPoolSize = (int)$min_pool_size;
      $this->MaxPoolSize = (int)$max_pool_size;
      $this->PoolCount = (int)$pool_count;
   }

   function setStatus( $status )
   {
      if( !preg_match( "/^(".CHECK_TROUND_STATUS.")$/", $status ) )
         error('invalid_args', "TournamentRound.setStatus($status)");
      $this->Status = $status;
   }

   function to_string()
   {
      return print_r( $this, true );
   }

   /*! \brief Inserts or updates tournament-properties in database. */
   function persist()
   {
      if( TournamentRound::isTournamentRound($this->tid, $this->Round) )
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   function insert()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->insert( "TournamentRound::insert(%s)" );
   }

   function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->update( "TournamentRound::update(%s)" );
   }

   function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "TournamentRound::delete(%s)" );
   }

   function fillEntityData( $withCreated )
   {
      // checked fields: Status
      $data = $GLOBALS['ENTITY_TOURNAMENT_ROUND']->newEntityData();
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'Round', $this->Round );
      $data->set_value( 'RulesID', $this->RulesID );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'Status', $this->Status );
      $data->set_value( 'MinPoolSize', $this->MinPoolSize );
      $data->set_value( 'MaxPoolSize', $this->MaxPoolSize );
      $data->set_value( 'PoolCount', $this->PoolCount );
      return $data;
   }


   // ------------ static functions ----------------------------

   /*! \brief Checks, if there is a tournament-round for given tournament-id and round. */
   function isTournamentRound( $tid, $round )
   {
      return (bool)mysql_single_fetch( "TournamentRound.isTournamentRound($tid,$round)",
         "SELECT 1 FROM TournamentRound WHERE tid='$tid' AND Round='$Round' LIMIT 1" );
   }

   /*! \brief Deletes TournamentRound-entry for given id. */
   function delete_tournament_round( $tid, $round )
   {
      $result = db_query( "TournamentRound::delete_tournament_round($tid,$round)",
         "DELETE FROM TournamentRound WHERE tid='$tid' AND Round='{$this->Round}' LIMIT 1" );
      return $result;
   }

   /*! \brief Returns db-fields to be used for query of TournamentRound-objects for given tournament-id. */
   function build_query_sql( $tid )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_ROUND']->newQuerySQL('TRD');
      $qsql->add_part( SQLP_WHERE, "TRD.tid='$tid'" );
      return $qsql;
   }

   /*! \brief Returns TournamentRound-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $trd = new TournamentRound(
            // from TournamentRound
            @$row['tid'],
            @$row['Round'],
            @$row['RulesID'],
            @$row['X_Lastchanged'],
            @$row['Status'],
            @$row['MinPoolSize'],
            @$row['MaxPoolSize'],
            @$row['PoolCount']
         );
      return $trd;
   }

   /*!
    * \brief Loads and returns TournamentRound-object for given tournament-ID.
    */
   function load_tournament_round( $tid, $round )
   {
      $result = NULL;
      if( $tid > 0 )
      {
         $qsql = TournamentRound::build_query_sql( $tid );
         $qsql->add_part( SQLP_LIMIT, '1' );

         if( $round > 0 ) // get specific round
            $qsql->add_part( SQLP_WHERE, "TRD.Round='{$round}'" );
         else // get latest round
            $qsql->add_part( SQLP_ORDER, "TRD.Round DESC'" );

         $row = mysql_single_fetch( "TournamentRound.load_tournament_round($tid,$round)",
            $qsql->get_select() );
         if( $row )
            $result = TournamentRound::new_from_row( $row );
      }
      return $result;
   }

   /*! \brief Returns enhanced (passed) ListIterator with TournamentRound-objects for given tournament-id. */
   function load_tournament_rounds( $iterator, $tid )
   {
      $qsql = TournamentRound::build_query_sql( $tid );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentRound.load_tournament_rounds", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $tourney = TournamentRound::new_from_row( $row );
         $iterator->addItem( $tourney, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   function getStatusText( $status=null )
   {
      global $ARR_GLOBALS_TOURNAMENT_ROUND;

      // lazy-init of texts
      $key = 'STATUS';
      if( !isset($ARR_GLOBALS_TOURNAMENT_ROUND[$key]) )
      {
         $arr = array();
         $arr[TROUND_STATUS_INIT]     = T_('Init#TRD_status');
         $arr[TROUND_STATUS_POOLINIT] = T_('Pool-Init#TRD_status');
         $arr[TROUND_STATUS_PAIRINIT] = T_('Pair-Init#TRD_status');
         $arr[TROUND_STATUS_GAMEINIT] = T_('Game-Init#TRD_status');
         $arr[TROUND_STATUS_DONE]     = T_('Done#TRD_status');
         $ARR_GLOBALS_TOURNAMENT_ROUND[$key] = $arr;
      }

      if( is_null($status) )
         return $ARR_GLOBALS_TOURNAMENT_ROUND[$key];
      if( !isset($ARR_GLOBALS_TOURNAMENT_ROUND[$key][$status]) )
         error('invalid_args', "Tournament.getStatusText($status)");
      return $ARR_GLOBALS_TOURNAMENT_ROUND[$key][$status];
   }

   function get_edit_tournament_status()
   {
      static $statuslist = array(
         TOURNEY_STATUS_NEW, TOURNEY_STATUS_PAIR
      );
      return $statuslist;
   }

} // end of 'TournamentRound'
?>
