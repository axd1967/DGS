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
require_once 'include/classlib_user.php';
require_once 'tournaments/include/tournament_globals.php';

 /*!
  * \file tournament_pool.php
  *
  * \brief Functions for handling tournament pools (for assigning users)
  *        for round-robin tournaments: tables TournamentPool
  */


 /*!
  * \class TournamentPool
  *
  * \brief Class to manage TournamentPool-table with pooling-related tournament-settings
  *        for round-robin tournaments
  */

define('TPOOL_LOADOPT_USER',    0x01 ); // load Players-stuff
define('TPOOL_LOADOPT_TRATING', 0x02 ); // load TournamentParticipant.Rating
define('TPOOL_LOADOPT_REGTIME', 0x04 ); // load TournamentParticipant.Created (=register-time)

global $ENTITY_TOURNAMENT_POOL; //PHP5
$ENTITY_TOURNAMENT_POOL = new Entity( 'TournamentPool',
   FTYPE_PKEY,  'ID',
   FTYPE_AUTO,  'ID',
   FTYPE_INT,   'ID', 'tid', 'Round', 'Pool', 'uid', 'GamesRun'
   );

class TournamentPool
{
   var $ID;
   var $tid;
   var $Round;
   var $Pool;
   var $uid;
   var $GamesRun;

   // non-DB fields

   var $User; // User-object

   /*! \brief Constructs TournamentPool-object with specified arguments. */
   function TournamentPool( $id=0, $tid=0, $round=1, $pool=1, $uid=0, $games_run=0 )
   {
      $this->ID = (int)$id;
      $this->tid = (int)$tid;
      $this->Round = (int)$round;
      $this->Pool = (int)$pool;
      $this->uid = (int)$uid;
      $this->GamesRun = (int)$games_run;
      // non-DB fields
      $this->User = (is_a($uid, 'User')) ? $user : new User( $this->uid );
   }

   function to_string()
   {
      return print_r( $this, true );
   }

   /*! \brief Inserts or updates tournament-pool in database. */
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
      $entityData = $this->fillEntityData();
      $result = $entityData->insert( "TournamentPool::insert(%s)" );
      if( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   function update()
   {
      $entityData = $this->fillEntityData();
      return $entityData->update( "TournamentPool::update(%s)" );
   }

   function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "TournamentPool::delete(%s)" );
   }

   function fillEntityData( &$data=null )
   {
      if( is_null($data) )
         $data = $GLOBALS['ENTITY_TOURNAMENT_POOL']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'Round', $this->Round );
      $data->set_value( 'Pool', $this->Pool );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'GamesRun', $this->GamesRun );
      return $data;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of TournamentPool-objects for given tournament-id. */
   function build_query_sql( $tid=0, $round=0, $pool=0 )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_POOL']->newQuerySQL('TPOOL');
      if( $tid > 0 )
         $qsql->add_part( SQLP_WHERE, "TPOOL.tid='$tid'" );
      if( $round > 0 )
         $qsql->add_part( SQLP_WHERE, "TPOOL.Round='$round'" );
      if( $pool > 0 )
         $qsql->add_part( SQLP_WHERE, "TPOOL.Pool='$pool'" );
      return $qsql;
   }

   /*! \brief Returns TournamentPool-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $trd = new TournamentPool(
            // from TournamentPool
            @$row['ID'],
            @$row['tid'],
            @$row['Round'],
            @$row['Pool'],
            @$row['uid'],
            @$row['GamesRun']
         );
      return $trd;
   }

   /*! \brief Checks, if TournamentPool-entry exists in db; false if no entry found. */
   function exists_tournament_pool( $tid, $round, $pool=0, $uid=null )
   {
      $query = sprintf( "SELECT 1 FROM TournamentPool WHERE tid=%s AND Round=%s", (int)$tid, (int)$round );
      if( is_numeric($pool) && $pool > 0 )
         $query .= " AND Pool=$pool";
      if( !is_null($uid) && is_numeric($uid) )
         $query .= " AND uid=$uid";

      $row = mysql_single_fetch( "TournamentPool::exists_tournament_pool.find($tid,$round,$pool,$uid)",
         "$query LIMIT 1" );
      return (bool)$row;
   }

   /*! \brief Returns array( pool-entries, pool-count ) for given tournament-id and round (and pool-id if given). */
   function count_tournament_pool( $tid, $round, $pool=0 )
   {
      $query = 'SELECT COUNT(*) AS X_CountAll, COUNT(DISTINCT Pool) AS X_CountPools '
         . sprintf( 'FROM TournamentPool WHERE tid=%s AND Round=%s', (int)$tid, (int)$round );
      if( is_numeric($pool) && $pool > 0 )
         $query .= " AND Pool=$pool";
      $row = mysql_single_fetch( "TournamentPool::count_tournament_pool($tid,$round,$pool)", $query );
      return ($row) ? array( $row['X_CountAll'], $row['X_CountPools'] ) : array( 0, 0 );
   }

   /*!
    * \brief Returns enhanced (passed) ListIterator with TournamentPool-objects for given tournament-id.
    * \param $pool 0 = load all pools, otherwise load specific pool
    * \param $with_user false = don't load user, true = load user-data,
    *        otherwise: rating-use-mode = load T-rating + TP-register-time
    * \return uid-indexed iterator with items: TournamentPool + .User + .User.urow[TP_X_RegisterTime|TP_Rating]
    */
   function load_tournament_pools( $iterator, $tid, $round, $pool=0, $load_opts=0 )
   {
      $needs_user = ( $load_opts & TPOOL_LOADOPT_USER );
      $needs_tp = ( $load_opts & (TPOOL_LOADOPT_TRATING|TPOOL_LOADOPT_REGTIME) );
      $has_userdata = $needs_user || $needs_tp;

      $qsql = TournamentPool::build_query_sql( $tid, $round, $pool );
      if( $load_opts & TPOOL_LOADOPT_USER )
      {
         $qsql->add_part( SQLP_FIELDS,
            'TPU.Name AS TPU_Name',
            'TPU.Handle AS TPU_Handle',
            'TPU.Rating2 AS TPU_Rating2',
            'TPU.Country AS TPU_Country',
            'TPU.Country AS TPU_Country',
            'UNIX_TIMESTAMP(TPU.Lastaccess) AS TPU_X_Lastaccess' );
         $qsql->add_part( SQLP_FROM,
            'INNER JOIN Players AS TPU ON TPU.ID=TPOOL.uid' );

         if( !$needs_tp )
            $qsql->add_part( SQLP_FIELDS, 'TPU.Rating2 AS TP_Rating', '0 AS TP_X_RegisterTime' );
      }

      if( $needs_tp )
      {
         if( $load_opts & TPOOL_LOADOPT_TRATING )
            $qsql->add_part( SQLP_FIELDS, 'TP.Rating AS TP_Rating' );
         if( $load_opts & TPOOL_LOADOPT_REGTIME )
            $qsql->add_part( SQLP_FIELDS, 'UNIX_TIMESTAMP(TP.Created) AS TP_X_RegisterTime' );
         $qsql->add_part( SQLP_FROM,
            "INNER JOIN TournamentParticipant AS TP ON TP.tid=$tid AND TP.uid=TPOOL.uid" );
      }

      $iterator->setQuerySQL( $qsql );
      $iterator->addIndex( 'uid' );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentPool::load_tournament_pools", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $tpool = TournamentPool::new_from_row( $row );
         if( $has_userdata )
         {
            $tpool->User = User::new_from_row( $row, 'TPU_' );
            $tpool->User->ID = $tpool->uid;
            $tpool->User->urow = array(
               'TP_X_RegisterTime'  => (int)@$row['TP_X_RegisterTime'],
               'TP_Rating'          => (float)@$row['TP_Rating'],
            );
         }
         $iterator->addItem( $tpool, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_tournament_pools

   /*! \brief Returns array with default and slice-mode array for round-robin-tournament. */
   function build_slice_mode()
   {
      $arr = array();
      $arr[TROUND_SLICE_ROUND_ROBIN]   = T_('Round-Robin#trd_slicemode');
      $arr[TROUND_SLICE_FILLUP_POOLS]  = T_('Filling up pools#trd_slicemode');
      $arr[TROUND_SLICE_MANUAL]        = T_('Manual#trd_slicemode');
      return array( /*default*/TROUND_SLICE_ROUND_ROBIN, $arr );
   }

   /*! \brief Delete all pools for given tournament-id and round. */
   function delete_pools( $tid, $round )
   {
      if( !is_numeric($tid) || $tid <= 0 )
         error('invalid_args', "TournamentPool::delete_pools.check.tid($tid,$round)");
      if( !is_numeric($round) || $round < 1 )
         error('invalid_args', "TournamentPool::delete_pools.check.round($tid,$round)");

      $table = $GLOBALS['ENTITY_TOURNAMENT_POOL']->table;
      $query = "DELETE FROM $table WHERE tid=$tid AND Round=$round";
      return db_query( "TournamentPool::delete_pools($tid,$round)", $query );
   }

   /*! \brief Seeds pools with all registered TPs for round. */
   function seed_pools( $tourney, $tprops, $tround, $seed_order, $slice_mode )
   {
      if( !is_a($tourney, 'Tournament') && $tourney->ID <= 0 )
         error('unknown_tournament', "TournamentPool::seed_pools.check_tid($seed_order)");
      $tid = $tourney->ID;

      if( !is_a($tround, 'TournamentRound') )
         error('invalid_args', "TournamentPool::seed_pools.check_tround($tid,$seed_order)");
      $round = $tround->Round;

      list( $def, $arr_seed_order ) = $tprops->build_seed_order();
      if( !isset($arr_seed_order[$seed_order]) )
         error('invalid_args', "TournamentPool::seed_pools.check_seed_order($tid,$seed_order)");

      list( $def, $arr_slice_mode ) = TournamentPool::build_slice_mode();
      if( !isset($arr_slice_mode[$slice_mode]) )
         error('invalid_args', "TournamentPool::seed_pools.check_slice_mode($tid,$slice_mode)");

      // load already joined pool-users
      $tpool_iterator = new ListIterator( "TournamentPool::seed_pools.load_pools($tid,$round)" );
      $tpool_iterator->addIndex( 'uid' );
      $tpool_iterator->addQuerySQLMerge( new QuerySQL( SQLP_WHERE,  "TPOOL.Round=$round" ));
      $tpool_iterator = TournamentPool::load_tournament_pools( $tpool_iterator, $tid );

      // find all registered TPs (optimized)
      $arr_TPs = TournamentParticipant::load_registered_users_in_seedorder( $tid, $seed_order );

      //TODO(needed?) optimize SQL for manual with one query: INSERT ... SELECT const, Pool=0 FROM TP ...

      // add all TPs to ladder
      $NOW = $GLOBALS['NOW'];
      $data = $GLOBALS['ENTITY_TOURNAMENT_POOL']->newEntityData();
      $arr_inserts = array();
      $arr_pools = array(); // [ pool-id => entries ], also for pool-id=0
      foreach( range(0, $tround->Pools) as $pool_id )
         $arr_pools[$pool_id] = 0;
      $pool = ( $slice_mode == TROUND_SLICE_FILLUP_POOLS ) ? 1 : 0;

      foreach( $arr_TPs as $row )
      {
         $uid = $row['uid'];

         // handle slice-mode for user-distribution on pools
         if( $slice_mode == TROUND_SLICE_ROUND_ROBIN )
         {
            if( ++$pool > $tround->Pools )
               $pool = 1;
         }
         elseif( $slice_mode == TROUND_SLICE_FILLUP_POOLS )
         {
            if( $pool < $tround->Pools && $arr_pools[$pool] >= $tround->PoolSize )
               $pool++;
         } //else: always 0 for TROUND_SLICE_MANUAL

         $tpool = $tpool_iterator->getIndexValue( 'uid', $uid, 0 );
         if( is_null($tpool) ) // user not joined yet
            $tpool = new TournamentPool( 0, $tid, $round, $pool, $uid );
         else // user already joined
            $tpool->Pool = $pool; //TODO assure, that TPool.GamesRun==0 !! (or else merge it)
         $arr_pools[$pool]++;

         $tpool->fillEntityData( $data );
         $arr_inserts[] = $data->build_sql_insert_values(false, /*with-PK*/true);
      }
      unset($arr_TPs);
      unset($tpool_iterator);

      // insert all registered TPs to ladder
      $cnt = count($arr_inserts);
      $seed_query = $data->build_sql_insert_values(true, /*with-PK*/true) . implode(',', $arr_inserts)
         . " ON DUPLICATE KEY UPDATE Pool=VALUES(Pool) ";

      $table = $data->entity->table;
      db_lock( "TournamentPool::seed_pools($tid,$round,$seed_order,$slice_mode)",
         "$table WRITE" );
      {//LOCK TournamentPool
         $result = db_query( "TournamentPool::seed_pools.insert($tid,$round,$seed_order,$slice_mode,#$cnt)",
            $seed_query );
      }
      db_unlock();

      return $result;
   }//seed_pools

   function assign_pool( $tround, $pool, $arr_uid )
   {
      $tid = (int)$tround->tid;
      $round = (int)$tround->Round;
      if( !is_numeric($pool) || $pool < 0 || $pool > $tround->Pools )
         error('invalid_args', "TournamentPool::assign_pool.check.pool($tid,$round,$pool)");

      $cnt = count($arr_uid);
      if( $cnt == 0 )
         return true;

      $table = $GLOBALS['ENTITY_TOURNAMENT_POOL']->table;
      $uid_where = implode(',', $arr_uid);
      return db_query( "TournamentPool::assign_pool.update($tid,$round,$pool)",
         "UPDATE $table SET Pool=$pool WHERE tid=$tid AND Round=$round AND uid IN ($uid_where) LIMIT $cnt" );
   }

   function get_edit_tournament_status()
   {
      static $statuslist = array(
         TOURNEY_STATUS_PAIR
      );
      return $statuslist;
   }

} // end of 'TournamentPool'
?>
