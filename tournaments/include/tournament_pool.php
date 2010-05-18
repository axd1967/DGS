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
require_once 'tournaments/include/tournament_pool_classes.php';
require_once 'tournaments/include/tournament_utils.php';

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
define('TPOOL_LOADOPT_ONLY_RATING', 0x08 ); // only load Rating2 from Players-table
define('TPOOL_LOADOPT_UROW_RATING', 0x10 ); // additionally set TPool->User->urow['Rating2']

global $ENTITY_TOURNAMENT_POOL; //PHP5
$ENTITY_TOURNAMENT_POOL = new Entity( 'TournamentPool',
   FTYPE_PKEY,  'ID',
   FTYPE_AUTO,  'ID',
   FTYPE_INT,   'ID', 'tid', 'Round', 'Pool', 'uid', 'Rank'
   );

class TournamentPool
{
   var $ID;
   var $tid;
   var $Round;
   var $Pool;
   var $uid;
   var $Rank;

   // non-DB fields

   var $User; // User-object
   var $PoolGames; // PoolGame-object array
   var $Points;
   var $Wins;
   var $SODOS;

   /*! \brief Constructs TournamentPool-object with specified arguments. */
   function TournamentPool( $id=0, $tid=0, $round=1, $pool=1, $uid=0, $rank=TPOOL_NO_RANK )
   {
      $this->ID = (int)$id;
      $this->tid = (int)$tid;
      $this->Round = (int)$round;
      $this->Pool = (int)$pool;
      $this->uid = (int)$uid;
      $this->Rank = (int)$rank;
      // non-DB fields
      if( is_a($uid, 'User') )
      {
         $this->User = $uid;
         $this->uid = $this->User->ID;
      }
      else
         $this->User = new User( $this->uid );
      $this->PoolGames = array();
      $this->Points = 0;
      $this->Wins = 0;
      $this->SODOS = 0;
   }

   function to_string()
   {
      return print_r( $this, true );
   }

   function formatRank()
   {
      if( $this->Rank == TPOOL_RETREAT )
         return NO_VALUE;
      elseif( $this->Rank > TPOOL_NO_RANK )
         return abs($this->Rank);
      else
         return '';
   }

   function echoRankImage()
   {
      return ( $this->Rank > 0 ) ? echo_image_tourney_next_round() : '';
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
      $data->set_value( 'Rank', $this->Rank );
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
            @$row['Rank']
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

   /*!
    * \brief Returns array( pool-entries, pool-count, distinct-user-count ) for given tournament-id
    *        and round (and pool-id if given).
    */
   function count_tournament_pool( $tid, $round, $pool=0, $count_uid=false )
   {
      $query = 'SELECT COUNT(*) AS X_CountAll, COUNT(DISTINCT Pool) AS X_CountPools, '
         . ( $count_uid ? 'COUNT(DISTINCT uid)' : '0' ) . ' AS X_CountUsers '
         . sprintf( 'FROM TournamentPool WHERE tid=%s AND Round=%s', (int)$tid, (int)$round );
      if( is_numeric($pool) && $pool > 0 )
         $query .= " AND Pool=$pool";
      $row = mysql_single_fetch( "TournamentPool::count_tournament_pool($tid,$round,$pool)", $query );
      return ($row) ? array( $row['X_CountAll'], $row['X_CountPools'], $row['X_CountUsers'] ) : array( 0, 0, 0 );
   }

   /*! \brief Returns expected sum of games for all pools for given tournament and round. */
   function count_tournament_pool_games( $tid, $round )
   {
      $query = sprintf( "SELECT Pool, COUNT(*) AS X_Count FROM TournamentPool "
         . "WHERE tid=%s and Round=%s GROUP BY Pool", (int)$tid, (int)$round );
      $result = db_query( "TournamentPool::count_tournament_pool_games($tid,$round)", $query );

      $count = 0;
      while( $row = mysql_fetch_assoc($result) )
         $count += TournamentUtils::calc_pool_games( $row['X_Count'] );
      mysql_free_result($result);

      return $count;
   }

   /*!
    * \brief Returns array( pool-entries, pool-count, distinct-user-count ) for given tournament-id
    *        and round (and pool-id if given).
    */
   function load_tournament_pool_bad_user( $tid, $round )
   {
      $query = "SELECT uid FROM TournamentPool WHERE tid=$tid AND Round=$round GROUP BY uid HAVING COUNT(*) > 1";
      $result = db_query( "TournamentPool::load_tournament_pool_bad_user($tid,$round)", $query );

      $arr_uids = array();
      while( $row = mysql_fetch_assoc($result) )
         $arr_uids[] = $row['uid'];
      mysql_free_result($result);

      return $arr_uids;
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
      $needs_tp_rating = ( $load_opts & TPOOL_LOADOPT_TRATING );
      $has_userdata = $needs_user || $needs_tp;

      $qsql = TournamentPool::build_query_sql( $tid, $round, $pool );
      if( $load_opts & TPOOL_LOADOPT_USER )
      {
         if( !($load_opts & TPOOL_LOADOPT_ONLY_RATING) )
         {
            $qsql->add_part( SQLP_FIELDS,
               'TPU.Name AS TPU_Name',
               'TPU.Handle AS TPU_Handle',
               'TPU.Country AS TPU_Country',
               'UNIX_TIMESTAMP(TPU.Lastaccess) AS TPU_X_Lastaccess' );
         }
         $qsql->add_part( SQLP_FIELDS,
            'TPU.ID AS TPU_ID', // MUST have to overwrite TP.ID with Players.ID in User-obj and User->urow(!)
            'TPU.Rating2 AS TPU_Rating2',
            'TPU.RatingStatus AS TPU_RatingStatus',
            'TPU.OnVacation AS TPU_OnVacation',
            'TPU.ClockUsed AS TPU_ClockUsed' );
         $qsql->add_part( SQLP_FROM,
            'INNER JOIN Players AS TPU ON TPU.ID=TPOOL.uid' );

         if( !$needs_tp_rating )
            $qsql->add_part( SQLP_FIELDS, 'TPU.Rating2 AS TP_Rating' );
         if( !$needs_tp )
            $qsql->add_part( SQLP_FIELDS, '0 AS TP_X_RegisterTime' );
      }

      if( $needs_tp )
      {
         if( $needs_tp_rating )
            $qsql->add_part( SQLP_FIELDS, 'TP.Rating AS TP_Rating' );
         if( $load_opts & TPOOL_LOADOPT_REGTIME )
            $qsql->add_part( SQLP_FIELDS, 'UNIX_TIMESTAMP(TP.Created) AS TP_X_RegisterTime' );

         $qsql->add_part( SQLP_FIELDS, 'TP.ID AS TP_ID' );
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
            $user = User::new_from_row( $row, 'TPU_', /*urow-strip-prefix*/true );
            $user->urow['TP_ID'] = (int)@$row['TP_ID'];
            $user->urow['TP_X_RegisterTime'] = (int)@$row['TP_X_RegisterTime'];
            $user->urow['TP_Rating'] = (float)@$row['TP_Rating'];
            if( $load_opts & TPOOL_LOADOPT_UROW_RATING ) // User- or TP-rating dependent on load-opts TPOOL_LOADOPT_TRATING
               $user->urow['Rating2'] = $user->urow['TP_Rating'];
            $tpool->User = $user;
         }
         $iterator->addItem( $tpool, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_tournament_pools

   /*!
    * \brief Returns ListIterator with array-items: uncomplete TPool(tid,round,pool) + orow.
    * \return orow: array( 'uid' => X, 'Pool' => Y, 'X_HasPool' => 0|1 (0=TP without pool-entry) ).
    * \note TPool returned is incomplete, so don't update() from that object
    */
   function load_tournament_participants_with_pools( $iterator, $tid, $round, $only_pools=false )
   {
      if( $only_pools )
      {
         $qsql = new QuerySQL(
            SQLP_FIELDS, 'uid', 'ID', 'Pool', '1 AS X_HasPool',
            SQLP_FROM,   'TournamentPool',
            SQLP_WHERE,  "tid=$tid", "Round=$round" );
      }
      else
      {
         $qsql = new QuerySQL(
            SQLP_FIELDS,
               'TP.uid',
               'TPOOL.ID', 'TPOOL.Pool', 'IFNULL(TPOOL.ID,0) AS X_HasPool',
            SQLP_FROM,
               'TournamentParticipant AS TP',
               "LEFT JOIN TournamentPool AS TPOOL ON TPOOL.uid=TP.uid "
                  . "AND TPOOL.tid=$tid AND TPOOL.Round=$round", // must not be in WHERE for outer-join
            SQLP_WHERE,
               "TP.tid=$tid",
               "TP.Status='".TP_STATUS_REGISTER."'"
            );
      }

      $iterator->setQuerySQL( $qsql );
      $iterator->addIndex( 'uid' );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentPool::load_tournament_participants_with_pools", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $tpool = ( $row['X_HasPool'] )
            ? new TournamentPool( $row['ID'], $tid, $round, $row['Pool'], $row['uid'] )
            : null;
         $iterator->addItem( $tpool, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_tournament_participants_with_pools

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
   function seed_pools( $tid, $tprops, $tround, $seed_order, $slice_mode )
   {
      if( !is_numeric($tid) || $tid <= 0 )
         error('unknown_tournament', "TournamentPool::seed_pools.check_tid($tid)");

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
      $tpool_iterator = TournamentPool::load_tournament_pools( $tpool_iterator, $tid, $round );

      // find all registered TPs (optimized)
      $arr_TPs = TournamentParticipant::load_registered_users_in_seedorder( $tid, $seed_order );

      // add all TPs to pools
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
            $tpool->Pool = $pool;
         $arr_pools[$pool]++;

         $tpool->fillEntityData( $data );
         $arr_inserts[] = $data->build_sql_insert_values(false, /*with-PK*/true);
      }
      unset($arr_TPs);
      unset($tpool_iterator);

      // insert all registered TPs to pools
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

   /*! \brief Add missing registered users to pool #0 (very similar to seed_pools()-func). */
   function add_missing_registered_users( $tid, $tround )
   {
      if( !is_numeric($tid) || $tid <= 0 )
         error('unknown_tournament', "TournamentPool::add_missing_registered_users.check_tid($tid)");

      if( !is_a($tround, 'TournamentRound') )
         error('invalid_args', "TournamentPool::add_missing_registered_users.check_tround($tid)");
      $round = $tround->Round;

      // load already joined pool-users
      $tpool_iterator = new ListIterator( "TournamentPool::add_missing_registered_users.load_pools($tid,$round)" );
      $tpool_iterator->addIndex( 'uid' );
      $tpool_iterator->addQuerySQLMerge( new QuerySQL( SQLP_WHERE,  "TPOOL.Round=$round" ));
      $tpool_iterator = TournamentPool::load_tournament_pools( $tpool_iterator, $tid, $round );

      // find all registered TPs (optimized)
      $arr_TPs = TournamentParticipant::load_registered_users_in_seedorder( $tid, TOURNEY_SEEDORDER_NONE );

      // add all missing TPs to pools
      $NOW = $GLOBALS['NOW'];
      $data = $GLOBALS['ENTITY_TOURNAMENT_POOL']->newEntityData();
      $arr_inserts = array();

      $pool = 0; // like for TROUND_SLICE_MANUAL
      foreach( $arr_TPs as $row )
      {
         $uid = $row['uid'];

         $tpool = $tpool_iterator->getIndexValue( 'uid', $uid, 0 );
         if( !is_null($tpool) ) // user already joined
            continue;

         $tpool = new TournamentPool( 0, $tid, $round, $pool, $uid );
         $tpool->fillEntityData( $data );
         $arr_inserts[] = $data->build_sql_insert_values(false, /*with-PK*/true);
      }
      unset($arr_TPs);
      unset($tpool_iterator);

      // insert all missing registered TPs to pool #0
      $cnt = count($arr_inserts);
      $seed_query = $data->build_sql_insert_values(true, /*with-PK*/true) . implode(',', $arr_inserts)
         . " ON DUPLICATE KEY UPDATE Pool=VALUES(Pool) ";

      $table = $data->entity->table;
      db_lock( "TournamentPool::add_missing_registered_users($tid,$round)", "$table WRITE" );
      {//LOCK TournamentPool
         $result = db_query( "TournamentPool::add_missing_registered_users.insert($tid,$round,#$cnt)", $seed_query );
      }
      db_unlock();

      return $result;
   }//add_missing_registered_users

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

   /*!
    * \brief checks pool integrity and return list of errors; empty list if all ok.
    * \return array( errors, pool_summary )
    *         $pool_summary fill in pool-summary:
    *         array( pool => array( pool-user-count, array( errormsg, ... ), pool-games-count ), ... )
    */
   function check_pools( $tround, $only_summary=false )
   {
      $tid = $tround->tid;
      $round = $tround->Round;
      $errors = array();

      // load tourney-participants and pool data
      $iterator = new ListIterator( 'TournamentTemplateRoundRobin.checkPooling.load_tp_pools' );
      $iterator = TournamentPool::load_tournament_participants_with_pools( $iterator, $tid, $round, $only_summary );

      $poolTables = new PoolTables( $tround->Pools );
      $poolTables->fill_pools( $iterator );

      $pool_summary = $poolTables->calc_pool_summary();
      if( $only_summary ) // load only summary
         return array( $errors, $pool_summary );


      $arr_counts = array(); // [ pool => #users, ... ]
      foreach( $pool_summary as $pool => $arr )
         $arr_counts[$pool] = $arr[0];

      $cnt_pool0 = (int)@$arr_counts[0];
      $cnt_real_pools = count($arr_counts) - ( $cnt_pool0 > 0 ? 1 : 0 ); // pool-count without 0-pool
      list( $cnt_entries, $cnt_pools, $cnt_users ) = // cnt_pools can include 0-pool
         TournamentPool::count_tournament_pool( $tid, $round, 0, /*count_uid*/true );

      // ---------- check pool integrity ----------

      // check that uids are distinct in all pools
      if( $cnt_entries != $cnt_users )
      {
         $arr_bad_users = TournamentPool::load_tournament_pool_bad_user( $tid, $round );
         $errors[] = sprintf( T_('Fatal error: Please contact an admin to fix multiple user-entries [%s] for tournament #%d, round %d.'),
            implode(',',$arr_bad_users), $tid, $round );
      }

      // check that there are some pools
      if( $cnt_pools == 0 )
         $errors[] = T_('Expecting at least one pool.');

      // check that count of pools matches the expected TRound.Pools-count
      if( $cnt_real_pools != $tround->Pools )
      {
         $errors[] = sprintf( T_('Expected %s pools, but currently there are %s pools.'),
            $tround->Pools, $cnt_real_pools );
         for( $i = $tround->Pools; $i <= $cnt_real_pools; $i++ )
            $pool_summary[$i][1][] = T_('Bad Pool-Number#poolsum');
      }

      // check that all registered users joined somewhere in the pools
      $arr_missing_users = array();
      $iterator->resetListIterator();
      $cnt_missing_users = 0;
      while( list(,$arr_item) = $iterator->getListIterator() )
      {
         list(,$orow ) = $arr_item;
         if( !$orow['X_HasPool'] )
            $cnt_missing_users++;
      }
      if( $cnt_missing_users > 0 )
         $errors[] = sprintf( T_('There are %s registered users, that are not appearing in the pools.'),
            $cnt_missing_users );

      // check that there are no unassigned users (with Pool=0)
      if( $cnt_pool0 > 0 )
      {
         $errors[] = sprintf( T_('There are %s unassigned users. Please assign them to a pool!'), $cnt_pool0 );
         $pool_summary[0][1][] = T_('Unassigned users#poolsum');
      }

      // check that the user-count of each pool is in valid range of min/max-pool-size
      $cnt_violate_poolsize = 0;
      foreach( $arr_counts as $pool => $pool_usercount )
      {
         if( $pool == 0 ) continue;
         if( $pool_usercount < $tround->MinPoolSize || $pool_usercount > $tround->MaxPoolSize )
            $cnt_violate_poolsize++;
         if( $pool_usercount < $tround->MinPoolSize )
            $pool_summary[$pool][1][] = T_('Pool-Size too small#poolsum');
         if( $pool_usercount > $tround->MaxPoolSize )
            $pool_summary[$pool][1][] = T_('Pool-Size too big#poolsum');
      }
      if( $cnt_violate_poolsize > 0 )
         $errors[] = sprintf( T_('There are %s pools violating the valid pool-size range %s.'),
            $cnt_violate_poolsize,
            TournamentUtils::build_range_text($tround->MinPoolSize, $tround->MaxPoolSize) );

      // check that there are no empty pools
      $cnt_empty = 0;
      foreach( $arr_counts as $pool => $pool_usercount )
      {
         if( $pool_usercount == 0 )
         {
            $cnt_empty++;
            $pool_summary[$pool][1][] = T_('Pool empty#poolsum');
         }
      }
      if( $cnt_empty > 0 )
         $errors[] = sprintf( T_('There are %s empty pools. Please fill or remove them!'), $cnt_empty );

      return array( $errors, $pool_summary );
   }//check_pools

   function get_edit_tournament_status()
   {
      static $statuslist = array(
         TOURNEY_STATUS_PAIR
      );
      return $statuslist;
   }

} // end of 'TournamentPool'
?>
