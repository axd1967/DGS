<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_log_helper.php';
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
define('TPOOL_LOADOPT_TP_ID',   0x02 ); // load TournamentParticipant.ID (for rid)
define('TPOOL_LOADOPT_TRATING', 0x04 ); // load TournamentParticipant.Rating
define('TPOOL_LOADOPT_REGTIME', 0x08 ); // load TournamentParticipant.Created (=register-time)
define('TPOOL_LOADOPT_TP_LASTMOVED', 0x10 ); // load TournamentParticipant.Lastmoved
define('TPOOL_LOADOPT_ONLY_RATING', 0x20 ); // only load Rating2 from Players-table
define('TPOOL_LOADOPT_UROW_RATING', 0x40 ); // additionally set TPool->User->urow['Rating2']

global $ENTITY_TOURNAMENT_POOL; //PHP5
$ENTITY_TOURNAMENT_POOL = new Entity( 'TournamentPool',
   FTYPE_PKEY,  'ID',
   FTYPE_AUTO,  'ID',
   FTYPE_INT,   'ID', 'tid', 'Round', 'Pool', 'uid', 'Rank'
   );

class TournamentPool
{
   public $ID;
   public $tid;
   public $Round;
   public $Pool;
   public $uid;
   public $Rank;

   // non-DB fields

   public $User; // User-object
   public $PoolGames = array(); // PoolGame-object array
   public $Points = 0;
   public $Wins = 0;
   public $Losses = 0;
   public $SODOS = 0;
   public $CalcRank = 0;

   /*! \brief Constructs TournamentPool-object with specified arguments. */
   private function __construct( $id=0, $tid=0, $round=1, $pool=1, $uid=0, $rank=TPOOLRK_NO_RANK )
   {
      $this->ID = (int)$id;
      $this->tid = (int)$tid;
      $this->Round = (int)$round;
      $this->Pool = (int)$pool;
      $this->uid = (int)$uid;
      $this->Rank = (int)$rank;

      // non-DB fields
      if ( $uid instanceof User )
      {
         $this->User = $uid;
         $this->uid = $this->User->ID;
      }
      else
         $this->User = new User( $this->uid );
   }

   public function to_string()
   {
      return print_r( $this, true );
   }

   public function build_result_info()
   {
      $rank_str = $this->formatRank(false, T_('unset#tpool'));
      return
         echo_image_info( "tournaments/roundrobin/view_pools.php?tid={$this->tid}".URI_AMP."round={$this->Round}#pool{$this->Pool}",
            T_('Tournament Pool') )
         . MINI_SPACING
         . span('bold', T_('Tournament Pool'), '%s: ')
         . sprintf( T_('Pool [%s], Rank [%s]#tourney'), $this->Pool, $rank_str )
         . ',' . MED_SPACING . $this->echoRankImage( T_('No Pool winner#tourney') );
   }

   public function get_cmp_rank()
   {
      // NOTE: 127 = lowest prio
      if ( $this->Rank == TPOOLRK_WITHDRAW || $this->Rank == TPOOLRK_NO_RANK )
         return $this->CalcRank;
      else
         return abs($this->Rank);
   }

   public function formatRank( $incl_calc_rank=false, $unset_rank='', $withdraw_rank=false )
   {
      if ( $this->Rank == TPOOLRK_WITHDRAW )
         $s = NO_VALUE . ( $withdraw_rank ? sprintf(' (%s)', T_('player withdrawn#tpool')) : '' );
      elseif ( $this->Rank > TPOOLRK_RANK_ZONE )
         $s = abs($this->Rank);
      else // rank unset
         $s = $unset_rank;

      if ( $incl_calc_rank && $this->CalcRank > 0 && $this->CalcRank != abs($this->Rank) )
         $s .= ' ' . span('Calc', sprintf( '(%s)', $this->CalcRank ));
      return trim($s);
   }//formatRank

   public function formatRankText()
   {
      return $this->formatRank( false, T_('unset#tpool'), true );
   }

   public function echoRankImage( $default='' )
   {
      return ( $this->Rank > 0 ) ? echo_image_tourney_pool_winner() : $default;
   }

   /*! \brief Inserts or updates tournament-pool in database. */
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
      $entityData = $this->fillEntityData();
      $result = $entityData->insert( "TournamentPool.insert(%s)" );
      if ( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   public function update()
   {
      $entityData = $this->fillEntityData();
      return $entityData->update( "TournamentPool.update(%s)" );
   }

   public function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "TournamentPool.delete(%s)" );
   }

   public function fillEntityData( $data=null )
   {
      if ( is_null($data) )
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

   /*!
    * \brief Returns db-fields to be used for query of TournamentPool-objects for given tournament-id.
    * \param $pool 0 = all pools, otherwise specific pool or pool-list if array of pools
    */
   public static function build_query_sql( $tid=0, $round=0, $pool=0 )
   {
      $tid = (int)$tid;
      $round = (int)$round;
      if ( !is_array($pool) )
         $pool = (int)$pool;

      $qsql = $GLOBALS['ENTITY_TOURNAMENT_POOL']->newQuerySQL('TPOOL');
      if ( $tid > 0 )
         $qsql->add_part( SQLP_WHERE, "TPOOL.tid='$tid'" );
      if ( $round > 0 )
         $qsql->add_part( SQLP_WHERE, "TPOOL.Round='$round'" );
      if ( is_array($pool) )
         $qsql->add_part( SQLP_WHERE, build_query_in_clause( 'TPOOL.Pool', $pool, /*is-str*/false ) );
      elseif ( $pool > 0 )
         $qsql->add_part( SQLP_WHERE, "TPOOL.Pool='$pool'" );
      return $qsql;
   }

   /*! \brief Returns TournamentPool-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $tpool = new TournamentPool(
            // from TournamentPool
            @$row['ID'],
            @$row['tid'],
            @$row['Round'],
            @$row['Pool'],
            @$row['uid'],
            @$row['Rank']
         );
      return $tpool;
   }

   /*!
    * \brief Checks, if TournamentPool-entry exists in db; false if no entry found.
    * \param $pool -2 = check for all pools; -1 = all "real" pools (Pool>0); 0 = special pool #0 for unassigned users
    */
   public static function exists_tournament_pool( $tid, $round, $pool=-2, $uid=null )
   {
      $tid = (int)$tid;
      $round = (int)$round;
      $pool = (int)$pool;

      $query = "SELECT 1 FROM TournamentPool WHERE tid=$tid AND Round=$round";
      if ( $pool >= 0 )
         $query .= " AND Pool=$pool";
      elseif ( $pool == -1 )
         $query .= " AND Pool>0";
      if ( !is_null($uid) && is_numeric($uid) )
         $query .= " AND uid=$uid";

      $row = mysql_single_fetch( "TournamentPool:exists_tournament_pool.find($tid,$round,$pool,$uid)",
         "$query LIMIT 1" );
      return (bool)$row;
   }//exists_tournament_pool

   /*!
    * \brief Returns array( pool-entries, pool-count, distinct-user-count ) for given tournament-id
    *        and round (and pool-id if given).
    */
   public static function count_tournament_pool( $tid, $round, $pool=0, $count_uid=false )
   {
      $tid = (int)$tid;
      $round = (int)$round;

      $query = 'SELECT COUNT(*) AS X_CountAll, COUNT(DISTINCT Pool) AS X_CountPools, '
         . ( $count_uid ? 'COUNT(DISTINCT uid)' : '0' ) . ' AS X_CountUsers '
         . "FROM TournamentPool WHERE tid=$tid AND Round=$round";
      if ( is_numeric($pool) && $pool > 0 )
         $query .= " AND Pool=$pool";

      $row = mysql_single_fetch( "TournamentPool:count_tournament_pool($tid,$round,$pool)", $query );
      return ($row) ? array( $row['X_CountAll'], $row['X_CountPools'], $row['X_CountUsers'] ) : array( 0, 0, 0 );
   }//count_tournament_pool

   /*!
    * \brief Returns array( pool => user-count ) for given tournament-id and round.
    * \param $rank null=count-all, TPOOLRK_NO_RANK = count-all-unset-rank, other value = count-all with Rank>value
    */
   public static function count_tournament_pool_users( $tid, $round, $rank=null )
   {
      $tid = (int)$tid;
      $round = (int)$round;

      if ( !is_null($rank) && is_numeric($rank) )
         $where_rank = ( $rank == TPOOLRK_NO_RANK ) ? "AND Rank <= $rank" : "AND Rank > $rank";
      else
         $where_rank = '';
      $result = db_query( "TournamentPool:count_tournament_pool_users($tid,$round,$rank)",
         "SELECT SQL_SMALL_RESULT Pool, COUNT(*) AS X_Count FROM TournamentPool " .
         "WHERE tid=$tid AND Round=$round $where_rank GROUP BY Pool" );

      $arr = array();
      while ( $row = mysql_fetch_assoc($result) )
         $arr[$row['Pool']] = $row['X_Count'];
      mysql_free_result($result);

      return $arr;
   }//count_tournament_pool_users

   /*! \brief Returns array( rank => count ) for given tournament-id and round. */
   public static function count_tournament_pool_ranks( $tid, $round )
   {
      $tid = (int)$tid;
      $round = (int)$round;

      $result = db_query( "TournamentPool:count_tournament_pool_ranks($tid,$round)",
         "SELECT SQL_SMALL_RESULT Rank, COUNT(*) AS X_Count FROM TournamentPool " .
         "WHERE tid=$tid AND Round=$round GROUP BY Rank" );

      $arr = array();
      while ( $row = mysql_fetch_assoc($result) )
         $arr[$row['Rank']] = $row['X_Count'];
      mysql_free_result($result);

      return $arr;
   }//count_tournament_pool_ranks

   /*! \brief Returns expected sum of games for all pools for given tournament and round. */
   public static function count_tournament_pool_games( $tid, $round )
   {
      $tid = (int)$tid;
      $round = (int)$round;

      $result = db_query( "TournamentPool:count_tournament_pool_games($tid,$round)",
         "SELECT SQL_SMALL_RESULT Pool, COUNT(*) AS X_Count FROM TournamentPool " .
         "WHERE tid=$tid AND Round=$round GROUP BY Pool" );

      $games_factor = TournamentHelper::determine_games_factor( $tid );

      $count = 0;
      while ( $row = mysql_fetch_assoc($result) )
         $count += TournamentUtils::calc_pool_games( $row['X_Count'], $games_factor );
      mysql_free_result($result);

      return $count;
   }//count_tournament_pool_games

   /*!
    * \brief Returns array( pool-entries, pool-count, distinct-user-count ) for given tournament-id
    *        and round (and pool-id if given).
    */
   public static function load_tournament_pool_bad_user( $tid, $round )
   {
      $tid = (int)$tid;
      $round = (int)$round;

      $result = db_query( "TournamentPool:load_tournament_pool_bad_user($tid,$round)",
         "SELECT uid FROM TournamentPool WHERE tid=$tid AND Round=$round GROUP BY uid HAVING COUNT(*) > 1" );

      $arr_uids = array();
      while ( $row = mysql_fetch_assoc($result) )
         $arr_uids[] = $row['uid'];
      mysql_free_result($result);

      return $arr_uids;
   }//load_tournament_pool_bad_user

   /*!
    * \brief Loads single TournamentPool of pool-user with same args and results
    *        like load_tournament_pools()-func.
    * \return null if no matching pool-user found, otherwise TournamentPool-object
    */
   public static function load_tournament_pool_user( $tid, $round, $uid, $load_opts=0 )
   {
      $uid = (int)$uid;
      $tpool_user_iterator = new ListIterator( 'TournamentPool:load_tournament_pool_user',
         new QuerySQL( SQLP_WHERE, "TPOOL.uid=$uid") );
      $tpool_user_iterator = self::load_tournament_pools( $tpool_user_iterator, $tid, $round, 0, $load_opts );

      if ( $tpool_user_iterator->getItemCount() == 1 )
      {
         list(,$arr_item) = $tpool_user_iterator->getListIterator();
         list( $tpool_user, $orow ) = $arr_item;
         return $tpool_user;
      }
      else
         return null;
   }//load_tournament_pool_user

   /*!
    * \brief Returns enhanced (passed) ListIterator with TournamentPool-objects for given tournament-id.
    * \param $pool 0 = load all pools, otherwise load specific pool or pools if array of pools
    * \param $load_opts see options TPOOL_LOADOPT_...
    * \return uid-indexed iterator with items: TournamentPool + .User + .User.urow[TP_X_RegisterTime|TP_Rating]
    *
    * \note IMPORTANT NOTE: keep in sync with TournamentCache::load_cache_tournament_pools()
    */
   public static function load_tournament_pools( $iterator, $tid, $round, $pool=0, $load_opts=0 )
   {
      $needs_tp = ( $load_opts & (TPOOL_LOADOPT_TP_ID|TPOOL_LOADOPT_TRATING|TPOOL_LOADOPT_TP_LASTMOVED|TPOOL_LOADOPT_REGTIME) );
      $needs_tp_rating = ( $load_opts & TPOOL_LOADOPT_TRATING );

      $qsql = self::build_query_sql( $tid, $round, $pool );
      if ( $load_opts & TPOOL_LOADOPT_USER )
      {
         if ( !($load_opts & TPOOL_LOADOPT_ONLY_RATING) )
         {
            $qsql->add_part( SQLP_FIELDS,
               'TPU.Name AS TPU_Name',
               'TPU.Handle AS TPU_Handle',
               'TPU.Country AS TPU_Country' );
         }
         $qsql->add_part( SQLP_FIELDS,
            'TPU.ID AS TPU_ID', // MUST have to overwrite TP.ID with Players.ID in User-obj and User->urow(!)
            'TPU.Rating2 AS TPU_Rating2',
            'TPU.RatingStatus AS TPU_RatingStatus',
            'UNIX_TIMESTAMP(TPU.Lastaccess) AS TPU_X_Lastaccess',
            'TPU.OnVacation AS TPU_OnVacation',
            'TPU.ClockUsed AS TPU_ClockUsed' );
         $qsql->add_part( SQLP_FROM,
            'INNER JOIN Players AS TPU ON TPU.ID=TPOOL.uid' );

         if ( !$needs_tp_rating )
            $qsql->add_part( SQLP_FIELDS, 'TPU.Rating2 AS TP_Rating' );
         if ( !($load_opts & TPOOL_LOADOPT_REGTIME) )
            $qsql->add_part( SQLP_FIELDS, '0 AS TP_X_RegisterTime' );
      }

      if ( $needs_tp )
      {
         if ( $needs_tp_rating )
            $qsql->add_part( SQLP_FIELDS, 'TP.Rating AS TP_Rating' );
         if ( $load_opts & TPOOL_LOADOPT_TP_LASTMOVED )
            $qsql->add_part( SQLP_FIELDS, 'UNIX_TIMESTAMP(TP.Lastmoved) AS TP_X_Lastmoved' );
         if ( $load_opts & TPOOL_LOADOPT_REGTIME )
            $qsql->add_part( SQLP_FIELDS, 'UNIX_TIMESTAMP(TP.Created) AS TP_X_RegisterTime' );

         $qsql->add_part( SQLP_FIELDS, 'TP.ID AS TP_ID' );
         $qsql->add_part( SQLP_FROM,
            "INNER JOIN TournamentParticipant AS TP ON TP.tid=$tid AND TP.uid=TPOOL.uid" );
      }

      $iterator->setQuerySQL( $qsql );
      $iterator->addIndex( 'uid' );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentPool:load_tournament_pools", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $tpool = self::new_tournament_pool_from_cache_row( $row, $load_opts );
         $iterator->addItem( $tpool, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_tournament_pools

   /*!
    * \brief Creates TournamentPool-object from row loaded by load_tournament_pools() to be placed in ListIterator.
    * \note only used internally for load_tournament_pools() and TournamentCache::load_cache_tournament_pools().
    */
   public static function new_tournament_pool_from_cache_row( $row, $load_opts )
   {
      $has_user_data = ( $load_opts & (TPOOL_LOADOPT_USER
         | TPOOL_LOADOPT_TP_ID|TPOOL_LOADOPT_TRATING|TPOOL_LOADOPT_TP_LASTMOVED|TPOOL_LOADOPT_REGTIME) );

      $tpool = self::new_from_row( $row );
      if ( $has_user_data )
      {
         $user = User::new_from_row( $row, 'TPU_', /*urow-strip-prefix*/true );
         $user->urow['TP_ID'] = (int)@$row['TP_ID'];
         $user->urow['TP_X_RegisterTime'] = (int)@$row['TP_X_RegisterTime'];
         $user->urow['TP_X_Lastmoved'] = (int)@$row['TP_X_Lastmoved'];
         $user->urow['TP_Rating'] = (float)@$row['TP_Rating'];
         if ( $load_opts & TPOOL_LOADOPT_UROW_RATING ) // User- or TP-rating dependent on load-opts TPOOL_LOADOPT_TRATING
            $user->urow['Rating2'] = $user->urow['TP_Rating'];
         $tpool->User = $user;
      }
      return $tpool;
   }//new_tournament_pool_from_cache_row

   /*!
    * \brief Returns ListIterator of tournament-participants with NextRound=$round with array-items:
    *       incomplete TPool(tid,round,pool) + orow.
    * \return orow: array( 'uid' => X, 'Pool' => Y, 'X_HasPool' => 0|1 (0=TP without pool-entry) ).
    * \note TPool returned is incomplete, so don't update() from that object
    */
   private static function load_tournament_participants_with_pools( $iterator, $tid, $round, $only_pools=false )
   {
      if ( $only_pools )
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
                  . "AND TPOOL.tid=$tid AND TPOOL.Round=$round", // must not be in main-WHERE
            SQLP_WHERE,
               "TP.tid=$tid",
               "TP.Status='".TP_STATUS_REGISTER."'",
               "TP.NextRound=$round" // load only TPs for given round
            );
      }

      $iterator->setQuerySQL( $qsql );
      $iterator->addIndex( 'uid' );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentPool:load_tournament_participants_with_pools", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $tpool = ( $row['X_HasPool'] )
            ? new TournamentPool( $row['ID'], $tid, $round, $row['Pool'], $row['uid'] )
            : null;
         $iterator->addItem( $tpool, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_tournament_participants_with_pools

   /*! \brief Delete all pools for given tournament-id and round. */
   public static function delete_pools( $tlog_type, $tid, $round )
   {
      if ( !is_numeric($tid) || $tid <= 0 )
         error('invalid_args', "TournamentPool:delete_pools.check.tid($tid,$round)");
      if ( !is_numeric($round) || $round < 1 )
         error('invalid_args', "TournamentPool:delete_pools.check.round($tid,$round)");

      $query = "DELETE FROM TournamentPool WHERE tid=$tid AND Round=$round";
      $result = db_query( "TournamentPool:delete_pools($tid,$round)", $query );
      TournamentLogHelper::log_delete_pools( $tid, $tlog_type, $round, mysql_affected_rows(), $result );
      self::delete_cache_tournament_pools( "TournamentPool:delete_pools($tid,$round)", $tid, $round );
      return $result;
   }//delete_pools

   /*! \brief Seeds pools with all registered TPs for round. */
   public static function seed_pools( $tlog_type, $tid, $tprops, $tround, $seed_order, $slice_mode )
   {
      if ( !is_numeric($tid) || $tid <= 0 )
         error('unknown_tournament', "TournamentPool:seed_pools.check_tid($tid)");

      if ( !($tround instanceof TournamentRound) )
         error('invalid_args', "TournamentPool:seed_pools.check_tround($tid,$seed_order)");
      $round = $tround->Round;

      list( $def, $arr_seed_order ) = $tprops->build_seed_order();
      if ( !isset($arr_seed_order[$seed_order]) )
         error('invalid_args', "TournamentPool:seed_pools.check_seed_order($tid,$seed_order)");

      list( $def, $arr_slice_mode ) = self::get_slice_modes();
      if ( !isset($arr_slice_mode[$slice_mode]) )
         error('invalid_args', "TournamentPool:seed_pools.check_slice_mode($tid,$slice_mode)");

      // load already joined pool-users
      $tpool_iterator = new ListIterator( "TournamentPool:seed_pools.load_pools($tid,$round)" );
      $tpool_iterator->addIndex( 'uid' );
      $tpool_iterator = self::load_tournament_pools( $tpool_iterator, $tid, $round );

      // find all registered TPs (optimized)
      $arr_TPs = TournamentParticipant::load_registered_users_in_seedorder( $tid, $round, $seed_order );

      // add all TPs to pools
      $NOW = $GLOBALS['NOW'];
      $entity_tpool = $GLOBALS['ENTITY_TOURNAMENT_POOL']->newEntityData();
      $arr_inserts = array();
      $slicer = new PoolSlicer( $slice_mode, $tround->Pools, $tround->PoolSize );
      foreach ( $arr_TPs as $row )
      {
         $uid = $row['uid'];

         // handle slice-mode for user-distribution on pools
         $pool = $slicer->next_pool();

         $tpool = $tpool_iterator->getIndexValue( 'uid', $uid, 0 );
         if ( is_null($tpool) ) // user not joined yet
            $tpool = new TournamentPool( 0, $tid, $round, $pool, $uid );
         else // user already joined
            $tpool->Pool = $pool;

         $slicer->visit_pool();

         $data_tpool = $tpool->fillEntityData( $entity_tpool );
         $arr_inserts[] = $data_tpool->build_sql_insert_values(false, /*with-PK*/true);
      }
      unset($arr_TPs);
      unset($tpool_iterator);

      // insert all registered TPs to pools
      $cnt = count($arr_inserts);
      $seed_query = $entity_tpool->build_sql_insert_values(true, /*with-PK*/true) . implode(',', $arr_inserts)
         . " ON DUPLICATE KEY UPDATE Pool=VALUES(Pool) ";

      db_lock( "TournamentPool:seed_pools($tid,$round,$seed_order,$slice_mode)",
         "TournamentPool WRITE" );
      {//LOCK TournamentPool
         $result = db_query( "TournamentPool:seed_pools.insert($tid,$round,$seed_order,$slice_mode,#$cnt)",
            $seed_query );
      }
      db_unlock();

      TournamentLogHelper::log_seed_pools( $tid, $tlog_type, $round,
         "seed_order=$seed_order, slice_mode=$slice_mode", $cnt, $slicer->count_visited_pools(), $result );

      self::delete_cache_tournament_pools( "TournamentPool:seed_pools($tid,$round)", $tid, $round );

      return $result;
   }//seed_pools

   /*! \brief Add missing registered users to pool #0 (very similar to seed_pools()-func). */
   public static function add_missing_registered_users( $tlog_type, $tid, $tround )
   {
      if ( !is_numeric($tid) || $tid <= 0 )
         error('unknown_tournament', "TournamentPool:add_missing_registered_users.check_tid($tid)");

      if ( !($tround instanceof TournamentRound) )
         error('invalid_args', "TournamentPool:add_missing_registered_users.check_tround($tid)");
      $round = $tround->Round;

      // load already joined pool-users
      $tpool_iterator = new ListIterator( "TournamentPool:add_missing_registered_users.load_pools($tid,$round)" );
      $tpool_iterator->addIndex( 'uid' );
      $tpool_iterator = self::load_tournament_pools( $tpool_iterator, $tid, $round );

      // find all registered TPs (optimized = no seed-order)
      $arr_TPs = TournamentParticipant::load_registered_users_in_seedorder( $tid, $round, TOURNEY_SEEDORDER_NONE );

      // add all missing TPs to pools
      $NOW = $GLOBALS['NOW'];
      $entity_tpool = $GLOBALS['ENTITY_TOURNAMENT_POOL']->newEntityData();
      $arr_inserts = array();

      $pool = 0; // like for TROUND_SLICE_MANUAL
      foreach ( $arr_TPs as $row )
      {
         $uid = $row['uid'];

         $tpool = $tpool_iterator->getIndexValue( 'uid', $uid, 0 );
         if ( !is_null($tpool) ) // user already joined
            continue;

         $tpool = new TournamentPool( 0, $tid, $round, $pool, $uid );
         $data_tpool = $tpool->fillEntityData( $entity_tpool );
         $arr_inserts[] = $data_tpool->build_sql_insert_values(false, /*with-PK*/true);
      }
      unset($arr_TPs);
      unset($tpool_iterator);

      // insert all missing registered TPs to pool #0
      $cnt = count($arr_inserts);
      $seed_query = $entity_tpool->build_sql_insert_values(true, /*with-PK*/true) . implode(',', $arr_inserts)
         . " ON DUPLICATE KEY UPDATE Pool=VALUES(Pool) ";

      db_lock( "TournamentPool:add_missing_registered_users($tid,$round)",
         "TournamentPool WRITE" );
      {//LOCK TournamentPool
         $result = db_query( "TournamentPool:add_missing_registered_users.insert($tid,$round,#$cnt)", $seed_query );
      }
      db_unlock();

      TournamentLogHelper::log_seed_pools_add_missing_users( $tid, $tlog_type, $round, $cnt, $result );

      return $result;
   }//add_missing_registered_users

   /*! \brief Assigns list of users to given tournament-round and pool. */
   public static function assign_pool( $tlog_type, $tround, $pool, $arr_uid )
   {
      $tid = (int)$tround->tid;
      $round = (int)$tround->Round;
      if ( !is_numeric($pool) || $pool < 0 || $pool > $tround->Pools )
         error('invalid_args', "TournamentPool:assign_pool.check.pool($tid,$round,$pool)");

      $cnt = count($arr_uid);
      if ( $cnt == 0 )
         return true;

      // find old-pool-state for T-logging
      $uid_where = implode(',', $arr_uid);
      $result = db_query( "TournamentPool:assign_pool.find_old($tid,$round,$pool)",
         "SELECT Pool, uid FROM TournamentPool WHERE tid=$tid AND Round=$round AND uid IN ($uid_where) LIMIT $cnt" );
      $arr_old_pools = array(); // pool -> [uid, ...]
      while ( $row = mysql_fetch_assoc($result) )
      {
         $old_pool = $row['Pool'];
         if ( !isset($arr_old_pools[$old_pool]) )
            $arr_old_pools[$old_pool] = array( $row['uid'] );
         else
            $arr_old_pools[$old_pool][] = $row['uid'];
      }
      mysql_free_result($result);

      // assign new pool for given users
      $result = db_query( "TournamentPool:assign_pool.update($tid,$round,$pool)",
         "UPDATE TournamentPool SET Pool=$pool WHERE tid=$tid AND Round=$round AND uid IN ($uid_where) LIMIT $cnt" );
      $upd_count = mysql_affected_rows();

      TournamentLogHelper::log_assign_tournament_pool( $tid, $tlog_type, $tround, $arr_old_pools, $arr_uid, $pool );

      if ( $upd_count > 0 )
         self::delete_cache_tournament_pools( "TournamentPool:assign_pool($tid,$round,$pool)", $tid, $round );

      return $upd_count;
   }//assign_pool

   /*!
    * \brief checks pool integrity and return list of errors, and fills in number of started-game-counts
    *       in PoolSummary-arrays; empty list if all ok.
    * \return array( errors, pool_summary )
    *         $pool_summary fill in pool-summary:
    *         array( pool => array( pool-user-count,
    *                array( errormsg, ... ),
    *                pool-games-count,
    *                pool-started-games-count ), ... )
    */
   public static function check_pools( $tround, $only_summary=false )
   {
      $tid = $tround->tid;
      $round = $tround->Round;
      $errors = array();

      $games_factor = TournamentHelper::determine_games_factor( $tid );

      // load tourney-participants and pool data
      $iterator = new ListIterator( 'TournamentPool.check_pools.load_tp_pools' );
      $iterator = self::load_tournament_participants_with_pools( $iterator, $tid, $round, $only_summary );

      $poolTables = new PoolTables( $tround->Pools );
      $poolTables->fill_pools( $iterator );

      $pool_summary = $poolTables->calc_pool_summary( $games_factor );
      if ( $only_summary ) // load only summary
      {
         // check game-counts integrity for all pools
         $arr_game_counts = TournamentPool::check_pools_game_integrity( $tround );
         $cnt_miss_games = $cnt_bad_games = 0;
         foreach ( $pool_summary as $pool => $arr )
         {
            $cnt_expected_games = $arr[2];
            if ( isset($arr_game_counts[$pool]) )
            {
               list( $cnt_tgames, $cnt_games ) = $arr_game_counts[$pool];
               if ( $cnt_tgames != $cnt_games || $cnt_expected_games != $cnt_tgames )
               {
                  if ( $cnt_expected_games != $cnt_tgames )
                     ++$cnt_miss_games;
                  if ( $cnt_tgames != $cnt_games )
                     ++$cnt_bad_games;

                  $pool_summary[$pool][1][] =
                     sprintf( T_('Inconsistency: expected %s games, but have: %s TGames & %s Games#tourney'),
                        $cnt_expected_games, $cnt_tgames, $cnt_games );
               }
               $pool_summary[$pool][3] += $cnt_tgames;
            }
         }
         if ( $cnt_miss_games )
            $errors[] = T_('Found inconsistencies requiring partial restart to create missing tournament games.#tourney');
         if ( $cnt_bad_games )
            $errors[] = T_('Found game inconsistencies (TGames!=Games). Contact tournament-admin for assistance!#tourney');

         return array( $errors, $pool_summary );
      }//only-summary


      $arr_counts = array(); // [ pool => #users, ... ]
      foreach ( $pool_summary as $pool => $arr )
         $arr_counts[$pool] = $arr[0];

      $cnt_pool0 = (int)@$arr_counts[0];
      $cnt_real_pools = count($arr_counts) - ( $cnt_pool0 > 0 ? 1 : 0 ); // pool-count without 0-pool
      list( $cnt_entries, $cnt_pools, $cnt_users ) = // cnt_pools can include 0-pool
         self::count_tournament_pool( $tid, $round, 0, /*count_uid*/true );

      // ---------- check pool integrity ----------

      // check that uids are distinct in all pools
      if ( $cnt_entries != $cnt_users )
      {
         $arr_bad_users = self::load_tournament_pool_bad_user( $tid, $round );
         $errors[] = sprintf( T_('Fatal error: Please contact an admin to fix multiple user-entries [%s] for tournament #%d, round %d.'),
            implode(',',$arr_bad_users), $tid, $round );
      }

      // check that there are some pools
      if ( $cnt_pools == 0 )
         $errors[] = T_('Expecting at least one pool.');

      // check that count of pools matches the expected TRound.Pools-count
      if ( $cnt_real_pools != $tround->Pools )
      {
         $errors[] = sprintf( T_('Expected %s pools, but currently there are %s pools.'),
            $tround->Pools, $cnt_real_pools );
         for ( $i = $tround->Pools; $i <= $cnt_real_pools; $i++ )
            $pool_summary[$i][1][] = T_('Bad Pool-Number');
      }

      // check that all registered users joined somewhere in the pools
      $arr_missing_users = array();
      $iterator->resetListIterator();
      $cnt_missing_users = 0;
      while ( list(,$arr_item) = $iterator->getListIterator() )
      {
         list(,$orow ) = $arr_item;
         if ( !$orow['X_HasPool'] )
            $cnt_missing_users++;
      }
      if ( $cnt_missing_users > 0 )
         $errors[] = sprintf( T_('There are %s registered users, that are not appearing in the pools.'),
            $cnt_missing_users );

      // check that there are no unassigned users (with Pool=0)
      if ( $cnt_pool0 > 0 )
      {
         $errors[] = sprintf( T_('There are %s unassigned users. Please assign them to a pool!'), $cnt_pool0 );
         $pool_summary[0][1][] = T_('Unassigned users#tpool');
      }

      // check that the user-count of each pool is in valid range of min/max-pool-size
      $cnt_violate_poolsize = 0;
      foreach ( $arr_counts as $pool => $pool_usercount )
      {
         if ( $pool == 0 ) continue;
         if ( $pool_usercount < $tround->MinPoolSize || $pool_usercount > $tround->MaxPoolSize )
            $cnt_violate_poolsize++;
         if ( $pool_usercount < $tround->MinPoolSize )
            $pool_summary[$pool][1][] = T_('Pool-Size too small');
         if ( $pool_usercount > $tround->MaxPoolSize )
            $pool_summary[$pool][1][] = T_('Pool-Size too big');
      }
      if ( $cnt_violate_poolsize > 0 )
         $errors[] = sprintf( T_('There are %s pools violating the valid pool-size range %s.'),
            $cnt_violate_poolsize,
            build_range_text($tround->MinPoolSize, $tround->MaxPoolSize) );

      // check that there are no empty pools
      $cnt_empty = 0;
      foreach ( $arr_counts as $pool => $pool_usercount )
      {
         if ( $pool_usercount == 0 )
         {
            $cnt_empty++;
            $pool_summary[$pool][1][] = T_('Pool empty');
         }
      }
      if ( $cnt_empty > 0 )
         $errors[] = sprintf( T_('There are %s empty pools. Please fill or remove them!'), $cnt_empty );

      return array( $errors, $pool_summary );
   }//check_pools

   /*!
    * \brief checks game integrity for pools of given tournament-round.
    * \return count of TournamentGames- and respective Games-entries (which should be the same) for each pool in array:
    *       array( pool => array( TournamentGames-count, Games-count ), ... )
    */
   public static function check_pools_game_integrity( $tround )
   {
      $tid = $tround->tid;
      $round_id = $tround->ID;

      $qsql = new QuerySQL(
         SQLP_OPTS, 'SQL_SMALL_RESULT',
         SQLP_FIELDS, 'Pool', 'COUNT(*) AS X_Count_TGames', 'SUM(IF(ISNULL(G.ID),0,1)) AS X_Count_Games',
         SQLP_FROM, 'TournamentGames AS TG', 'LEFT JOIN Games as G ON G.ID=TG.gid',
         SQLP_WHERE, "TG.tid=$tid", "TG.Round_ID=$round_id",
         SQLP_GROUP, 'Pool' );

      $result = db_query( "TournamentPool:check_pools_game_integrity($tid,$round_id)", $qsql->get_select() );
      $arr = array();
      while ( $row = mysql_fetch_array( $result ) )
         $arr[$row['Pool']] = array( $row['X_Count_TGames'], $row['X_Count_Games'] );
      mysql_free_result($result);

      return $arr;
   }//check_pools_game_integrity

   /*!
    * \brief Updates TournamentPool.Rank with given rank for specified TournamentPool.ID(s).
    * \param $tpool_id single ID or array with IDs to update rank
    * \param $fix_rank if true, no update-restriction on Rank;
    *        otherwise expect Rank<TPOOLRK_RANK_ZONE to auto-fill rank
    * \return number of updated entries
    *
    * \note tournament-pool cache for cache-group CACHE_GRP_TPOOLS is NOT invalidated! must be done at calling side.
    */
   public static function update_tournament_pool_ranks( $tid, $tlog_type, $tlog_ref, $tpool_id, $rank, $fix_rank=false )
   {
      if ( is_array($tpool_id) )
      {
         $cnt = count($tpool_id);
         if ( $cnt <= 0 )
            return 0;
         $qpart_tpools = build_query_in_clause( 'ID', $tpool_id, /*is-str*/false );
      }
      elseif ( is_numeric($tpool_id) )
      {
         $cnt = 1;
         $qpart_tpools = 'ID='.$tpool_id;
      }
      else
      {
         error('invalid_args', "TournamentPool:update_tournament_pool_ranks.check.tpool($tid,$tpool_id,$rank)");
         return 0;
      }
      $rank = (int)$rank;

      $qpart_rank = ($fix_rank) ? '' : ' AND Rank<'.TPOOLRK_RANK_ZONE;
      $result = db_query( "TournamentPool:update_tournament_pool_ranks.update($tid,$tpool_id,$rank)",
         "UPDATE TournamentPool SET Rank=$rank " .
         "WHERE $qpart_tpools $qpart_rank LIMIT $cnt" );
      $upd_count = mysql_affected_rows();

      TournamentLogHelper::log_set_tournament_pool_ranks( $tid, $tlog_type, $tlog_ref, $tpool_id, $rank, $fix_rank, $upd_count );

      return $upd_count;
   }//update_tournament_pool_ranks

   /*!
    * \brief Updates TournamentPool.Rank setting pool-winners determined by tround->PoolWinnerRanks.
    * \param $tround TournamentRound-object
    * \return number of affected rows
    *
    * \note expecting TournamentPool.Rank already set, either manually by TD or TournamentRoundHelper::fill_ranks_tournament_pool()
    * \note pool-entries on WITHDRAWN-rank are not touched
    * \note already set pool-winners are not touched
    */
   public static function update_tournament_pool_set_pool_winners( $tround )
   {
      $tid = (int)$tround->tid;
      $round = (int)$tround->Round;
      $poolwinner_ranks = (int)$tround->PoolWinnerRanks;

      $result = db_query( "TournamentPool:update_tournament_pool_set_pool_winners.update($tid,{$tround->ID},$round,$poolwinner_ranks)",
         "UPDATE TournamentPool SET Rank=ABS(Rank) " .
         "WHERE tid=$tid AND Round=$round AND Rank < 0 AND Rank >= ".TPOOLRK_RANK_ZONE . // rank safety-checks
            " AND Rank >= -$poolwinner_ranks" );
      $upd_count = mysql_affected_rows();

      if ( $upd_count > 0 )
         self::delete_cache_tournament_pools( "TournamentPool:update_tournament_pool_set_pool_winners($tid,$round)",
            $tid, $round );

      return $upd_count;
   }//update_tournament_pool_set_pool_winners

   /*!
    * \brief Executes rank-actions on TournamentPool.Rank for specified tournament-round.
    * \param $action one of RKACT_... to set/clear pool-winner (=next-round-"flag"), clear/reset rank;
    *        RKACT_SET_POOL_WIN   = sets pool-winner = ABS(Rank) for Ranks < 0
    *        RKACT_CLEAR_POOL_WIN = clears pool-winner = -ABS(Rank) for Ranks > 0
    *        RKACT_WITHDRAW       = sets Rank=0, withdrawing pool-user from next-round and as pool-winner
    *        RKACT_REMOVE_RANKS   = sets Rank=TPOOLRK_NO_RANK to allow filling ranks anew
    * \param $rank_from ''=all ranks, otherwise numeric rank
    * \param $rank_to ''=same as rank_from (single rank), otherwise numeric rank
    * \param $pool ''=all pools, otherwise specific pool
    * \return number of updated entries
    */
   public static function execute_rank_action( $tlog_type, $tid, $round, $action, $uid, $rank_from=null, $rank_to=null, $pool=null )
   {
      if ( !is_numeric($action) && ($action < 1 || $action > 4) )
         error('invalid_args', "TournamentPool:execute_rank_action.check.action($tid,$round,$action)");
      if ( !is_numeric($uid) && $uid <= GUESTS_ID_MAX )
         error('invalid_args', "TournamentPool:execute_rank_action.check.uid($tid,$round,$uid)");
      if ( $uid == 0 )
      {
         if ( (string)$rank_from != '' && ( !is_numeric($rank_from) || $rank_from < 0 ))
            error('invalid_args', "TournamentPool:execute_rank_action.check.rank_from($tid,$round,$rank_from)");
         if ( (string)$rank_to != '' && ( !is_numeric($rank_to) || $rank_to < 0 ))
            error('invalid_args', "TournamentPool:execute_rank_action.check.rank_to($tid,$round,$rank_to)");
         if ( (string)$pool != '' && !is_numeric($pool) )
            error('invalid_args', "TournamentPool:execute_rank_action.check.pool($tid,$round,$pool)");
      }

      $qpart_rank = ''; // where-clause
      if ( $uid ) // has uid
      {
         if ( $action == RKACT_SET_POOL_WIN )
            $qpart_rank = " AND Rank < 0";
         elseif ( $action == RKACT_CLEAR_POOL_WIN )
            $qpart_rank = " AND Rank > 0";
      }
      else // no uid
      {
         if ( is_numeric($rank_from) && !is_numeric($rank_to) )
            $rank_to = $rank_from;
         if ( $action == RKACT_SET_POOL_WIN )
         {
            if ( !is_numeric($rank_from) )
               $qpart_rank = " AND Rank < 0";
            else
            {
               $rank_from = -$rank_from;
               $rank_to = -$rank_to;
            }
         }
         elseif ( $action == RKACT_CLEAR_POOL_WIN && !is_numeric($rank_from) )
            $qpart_rank = " AND Rank > 0";
         elseif ( ($action == RKACT_WITHDRAW || $action == RKACT_REMOVE_RANKS) && is_numeric($rank_from) )
         {
            if ( $rank_from == $rank_to )
               $qpart_rank = " AND Rank IN (-$rank_from,$rank_from)";
            else
            {
               $rank_min = min( $rank_from, $rank_to );
               $rank_max = max( $rank_from, $rank_to );
               $qpart_rank = ' AND ' . build_query_in_clause('Rank',
                  array_merge( range($rank_min,$rank_max), range(-$rank_max,-$rank_min) ),
                  /*is-str*/false );
            }
         }

         if ( !$qpart_rank && is_numeric($rank_from) )
         {
            if ( $rank_from == $rank_to )
               $qpart_rank = " AND Rank=$rank_from";
            else
            {
               $rank_min = min( $rank_from, $rank_to );
               $rank_max = max( $rank_from, $rank_to );
               $qpart_rank = " AND Rank BETWEEN $rank_min AND $rank_max";
            }
         }
      }//no uid

      $change_unset = false; // unset-ranks are not allowed to be changed normally
      if ( $action == RKACT_SET_POOL_WIN )
         $rankval = 'ABS(Rank)'; // where Rank < 0
      elseif ( $action == RKACT_CLEAR_POOL_WIN )
         $rankval = '-ABS(Rank)'; // where Rank > 0
      elseif ( $action == RKACT_WITHDRAW )
      {
         $rankval = '0';
         $change_unset = true; // allow overwriting unset-ranks for withdrawing (as it's a manual TD-operation)
      }
      else //if ( $action == RKACT_REMOVE_RANKS )
         $rankval = TPOOLRK_NO_RANK;

      $query = "UPDATE TournamentPool SET Rank=$rankval "
         . "WHERE tid=$tid AND Round=$round "
         . ( is_numeric($pool) ? " AND Pool=$pool" : '' )
         . ( $change_unset ? '' : " AND Rank >".TPOOLRK_RANK_ZONE ) // don't touch UNSET-ranks
         . $qpart_rank
         . ( $uid ? " AND uid=$uid LIMIT 1" : '' );
      $result = db_query( "TournamentPool:execute_rank_action.update("
         . "$tid,$round,a$action,u$uid,$rank_from-$rank_to,p$pool)", $query );
      $upd_count = mysql_affected_rows();

      TournamentLogHelper::log_execute_tournament_pool_rank_action( $tid, $tlog_type, $round,
         $action, $uid, $rank_from, $rank_to, $pool, $upd_count );

      if ( $upd_count > 0 )
         self::delete_cache_tournament_pools( "TournamentPool:execute_rank_action($tid,$round)", $tid, $round );

      return $upd_count;
   }//execute_rank_action


   /*! \brief Returns number of user marked as next-rounders/finalists for given tournament-id and round. */
   public static function count_tournament_pool_next_rounders( $tid, $round )
   {
      $tid = (int)$tid;
      $round = (int)$round;

      $row = mysql_single_fetch( "TournamentPool:count_tournament_pool_next_rounders($tid,$round)",
         "SELECT COUNT(*) AS X_Count FROM TournamentPool WHERE tid=$tid AND Round=$round AND Rank>0" );
      return ($row) ? (int)@$row['X_Count'] : 0;
   }//count_tournament_pool_next_rounders

   /*! \brief Returns number of users marked as next-rounders without set TP.NextRound for next-round. */
   public static function count_tournament_pool_missing_next_rounders( $tid, $round )
   {
      $tid = (int)$tid;
      $round = (int)$round; // current-round
      $next_round = $round + 1;

      $row = mysql_single_fetch( "TournamentPool:count_tournament_pool_missing_next_rounders($tid,$round)",
         "SELECT COUNT(*) AS X_Count " .
         "FROM TournamentParticipant AS TP " .
            "INNER JOIN TournamentPool AS TPOOL ON TPOOL.uid=TP.uid " .
         "WHERE TPOOL.tid=$tid AND TPOOL.Round=$round AND TPOOL.Rank>0 " .
            "AND TP.tid=$tid AND TP.NextRound < $next_round" );
      return ($row) ? (int)@$row['X_Count'] : 0;
   }//count_tournament_pool_missing_next_rounders

   /*! \brief Sets TournamentParticipant.NextRound to next-round (round+1) for missing users marked as finalists/next-rounders in current round. */
   public static function mark_next_round_participation( $tid, $round )
   {
      global $NOW, $player_row;
      $tid = (int)$tid;
      $round = (int)$round; // current-round
      $next_round = $round + 1;
      $changed_by = ( (string)@$player_row['Handle'] != '' ) ? @$player_row['Handle'] : UNKNOWN_VALUE;

      return db_query( "TournamentPool:mark_next_round_participation.TP.update($tid,$round)",
         "UPDATE TournamentParticipant AS TP " .
            "INNER JOIN TournamentPool AS TPOOL ON TPOOL.uid=TP.uid " .
         "SET TP.NextRound=$next_round, TP.Lastchanged=$NOW, " .
            "TP.ChangedBy=RTRIM(CONCAT('[".mysql_addslashes($changed_by)."] ',TP.ChangedBy)) " .
         "WHERE TPOOL.tid=$tid AND TPOOL.Round=$round AND TPOOL.Rank>0 " .
            "AND TP.tid=$tid AND TP.NextRound <= $round" );
   }//mark_next_round_participation

   /*! \brief Returns array with default and slice-mode array for round-robin-tournament. */
   public static function get_slice_modes()
   {
      static $ARR_SLICE_MODES = null;

      if ( is_null($ARR_SLICE_MODES) ) // lazy-init
      {
         $ARR_SLICE_MODES = array(
            /*default*/TROUND_SLICE_SNAKE,
            array(
               TROUND_SLICE_SNAKE         => T_('Snake Seeding#trd_slicemode'),
               TROUND_SLICE_ROUND_ROBIN   => T_('Round-Robin#trd_slicemode'),
               TROUND_SLICE_FILLUP_POOLS  => T_('Filling up pools#trd_slicemode'),
               TROUND_SLICE_MANUAL        => T_('Manual#trd_slicemode'),
            ));
      }

      return $ARR_SLICE_MODES;
   }//get_slice_modes

   public static function get_edit_tournament_status()
   {
      static $statuslist = array( TOURNEY_STATUS_PAIR );
      return $statuslist;
   }

   public static function delete_cache_tournament_pools( $dbgmsg, $tid, $round )
   {
      DgsCache::delete_group( $dbgmsg, CACHE_GRP_TPOOLS, "TPools.$tid.$round" );
   }

} // end of 'TournamentPool'
?>
