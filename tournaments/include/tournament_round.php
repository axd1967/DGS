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

require_once 'include/db_classes.php';
require_once 'include/dgs_cache.php';
require_once 'include/std_classes.php';
require_once 'tournaments/include/tournament_utils.php';

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

global $ENTITY_TOURNAMENT_ROUND; //PHP5
$ENTITY_TOURNAMENT_ROUND = new Entity( 'TournamentRound',
   FTYPE_PKEY,  'ID',
   FTYPE_AUTO,  'ID',
   FTYPE_CHBY,
   FTYPE_INT,   'ID', 'tid', 'Round', 'MinPoolSize', 'MaxPoolSize', 'MaxPoolCount', 'PoolWinnerRanks',
                'Pools', 'PoolSize',
   FTYPE_DATE,  'Lastchanged',
   FTYPE_ENUM,  'Status'
   );

class TournamentRound
{
   public $ID;
   public $tid;
   public $Round;
   public $Status;
   public $MinPoolSize;
   public $MaxPoolSize;
   public $MaxPoolCount;
   public $PoolWinnerRanks;
   public $Pools;
   public $PoolSize;
   public $Lastchanged;
   public $ChangedBy;

   /*! \brief Constructs TournamentRound-object with specified arguments. */
   public function __construct( $id=0, $tid=0, $round=1, $status=TROUND_STATUS_INIT,
         $min_pool_size=2, $max_pool_size=2, $max_pool_count=0, $poolwinner_ranks=1, $pool_count=0, $pool_size=0,
         $lastchanged=0, $changed_by='' )
   {
      $this->ID = (int)$id;
      $this->tid = (int)$tid;
      $this->Round = (int)$round;
      $this->setStatus( $status );
      $this->MinPoolSize = (int)$min_pool_size;
      $this->MaxPoolSize = (int)$max_pool_size;
      $this->MaxPoolCount = (int)$max_pool_count;
      $this->PoolWinnerRanks = (int)$poolwinner_ranks;
      $this->Pools = (int)$pool_count;
      $this->PoolSize = (int)$pool_size;
      $this->Lastchanged = (int)$lastchanged;
      $this->ChangedBy = $changed_by;
   }

   public function setStatus( $status, $check_only=false )
   {
      if ( !preg_match( "/^(".CHECK_TROUND_STATUS.")$/", $status ) )
         error('invalid_args', "TournamentRound.setStatus($status)");
      if ( !$check_only )
         $this->Status = $status;
   }

   public function to_string()
   {
      return print_r( $this, true );
   }

   /*! \brief Inserts or updates tournament-round in database. */
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
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData(true);
      $result = $entityData->insert( "TournamentRound.insert(%s)" );
      if ( $result )
         $this->ID = mysql_insert_id();
      self::delete_cache_tournament_round( 'TournamentRound.insert', $this->tid, $this->Round );
      return $result;
   }

   public function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      $result = $entityData->update( "TournamentRound.update(%s)" );
      self::delete_cache_tournament_round( 'TournamentRound.update', $this->tid, $this->Round );
      return $result;
   }

   public function delete()
   {
      $entityData = $this->fillEntityData();
      $result = $entityData->delete( "TournamentRound.delete(%s)" );
      self::delete_cache_tournament_round( 'TournamentRound.delete', $this->tid, $this->Round );
      return $result;
   }

   public function fillEntityData()
   {
      // checked fields: Status
      $data = $GLOBALS['ENTITY_TOURNAMENT_ROUND']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'Round', $this->Round );
      $data->set_value( 'Status', $this->Status );
      $data->set_value( 'MinPoolSize', $this->MinPoolSize );
      $data->set_value( 'MaxPoolSize', $this->MaxPoolSize );
      $data->set_value( 'MaxPoolCount', $this->MaxPoolCount );
      $data->set_value( 'PoolWinnerRanks', $this->PoolWinnerRanks );
      $data->set_value( 'Pools', $this->Pools );
      $data->set_value( 'PoolSize', $this->PoolSize );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'ChangedBy', $this->ChangedBy );
      return $data;
   }

   /*! \brief Checks if all round-properties are valid; return error-list, empty if ok. */
   public function check_properties()
   {
      $errors = array();

      if ( $this->MinPoolSize < 2 || $this->MinPoolSize > TROUND_MAX_POOLSIZE )
         $errors[] = sprintf( T_('Tournament Round min. pool size must be in range %s.'),
            build_range_text(2, TROUND_MAX_POOLSIZE) );
      if ( $this->MaxPoolSize < 2 || $this->MaxPoolSize > TROUND_MAX_POOLSIZE )
         $errors[] = sprintf( T_('Tournament Round max. pool size must be in range %s.'),
            build_range_text(2, TROUND_MAX_POOLSIZE) );
      if ( $this->MinPoolSize > $this->MaxPoolSize )
         $errors[] = T_('Tournament Round min. pool size must be smaller than max. pool size.');

      // NOTE: to enforce that pools of next-round get smaller, pool winner ranks must be < max-pool-size,
      //       otherwise all pool-players could proceed and the tournament will not end or will be prolonged.
      if ( $this->PoolWinnerRanks < 1 || $this->PoolWinnerRanks >= $this->MaxPoolSize )
         $errors[] = sprintf( T_('Tournament Round pool winner ranks must be in range %s and smaller max. pool size [%s].'),
            build_range_text(1, TROUND_MAX_POOLSIZE), $this->MaxPoolSize );

      if ( $this->MaxPoolCount < 0 || $this->MaxPoolCount > TROUND_MAX_POOLCOUNT )
         $errors[] = sprintf( T_('Tournament Round max. pool count must be in range %s.'),
            build_range_text(2, TROUND_MAX_POOLCOUNT) );

      return $errors;
   }//check_properties

   /*!
    * \brief Returns array( header, notes-array ) with this properties in textual form.
    * \param $games_factor factor for calculation of games per user
    */
   public function build_notes_props( $games_factor=1 )
   {
      $arr_props = array();

      // status / pool-size / pool-count
      $arr_props[] = sprintf( '%s: %s', T_('Tournament Round Status'), self::getStatusText($this->Status) );
      $arr_props[] = sprintf( '%s: %s', T_('Min. Pool Size'), $this->MinPoolSize );
      $arr_props[] = sprintf( '%s: %s', T_('Max. Pool Size'), $this->MaxPoolSize );
      if ( $this->MaxPoolCount > 0 )
         $arr_props[] = sprintf( '%s: %s', T_('Maximum Pool count'), $this->MaxPoolCount );

      $arr_props[] = sprintf( '%s: %s', T_('Pool Count'), $this->Pools );

      $max_games_text = sprintf( '%s: %s', T_('Max. simultaneous games per user in this round'),
         $games_factor * ( $this->MaxPoolSize - 1 ) );
      $arr_props[] = array( 'text' => span('bold', make_html_safe($max_games_text, 'line')) );

      // general conditions
      $arr_props[] = sprintf( T_('For the current round, the players with ranks %s are pool winners.'),
         '1..' . $this->PoolWinnerRanks );

      return array( sprintf( T_('Configuration of the current tournament round #%s'), $this->Round )
            . ':', $arr_props );
   }//build_notes_props


   // ------------ static functions ----------------------------

   /*! \brief Checks, if there is a tournament-round for given tournament-id and round. */
   public static function isTournamentRound( $tid, $round )
   {
      $round = (int)$round;
      return (bool)mysql_single_fetch( "TournamentRound:isTournamentRound($tid,$round)",
         "SELECT 1 FROM TournamentRound WHERE tid='$tid' AND Round=$Round LIMIT 1" );
   }

   /*!
    * \brief Adds new tournament-round with defaults for given tournament-id,
    *        returning new TournamentRound-object added or null on error.
    */
   public static function add_tournament_round( $tid )
   {
      global $player_row, $NOW;
      $tround = null;

      // defaults: Status=INIT, MinPoolSize=0, MaxPoolSize=0, MaxPoolCount=0
      $changed_by = EntityData::build_sql_value_changed_by( $player_row['Handle'] );
      $query = "INSERT INTO TournamentRound (tid,Round,Lastchanged,ChangedBy) "
             . "SELECT $tid, MAX(Round)+1, FROM_UNIXTIME($NOW), $changed_by FROM TournamentRound WHERE tid=$tid";
      if ( db_query( "TournamentRound:add_tournament_round.insert($tid)", $query ) )
      {
         $new_id = mysql_insert_id();
         $tround = self::load_tournament_round_by_id($new_id);
      }
      return $tround;
   }//add_tournament_round

   /*! \brief Deletes TournamentRound-entry for given id. */
   public static function delete_tournament_round( $tid, $round )
   {
      $tid = (int)$tid;
      $round = (int)$round;

      $result = db_query( "TournamentRound:delete_tournament_round($tid,$round)",
         "DELETE FROM TournamentRound WHERE tid=$tid AND Round=$round LIMIT 1" );
      self::delete_cache_tournament_round( 'TournamentRound.delete_tournament_round', $tid, $round );
      return $result;
   }

   /*! \brief Returns db-fields to be used for query of TournamentRound-objects for given tournament-id. */
   public static function build_query_sql( $tid=0 )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_ROUND']->newQuerySQL('TRD');
      if ( $tid > 0 )
         $qsql->add_part( SQLP_WHERE, "TRD.tid='$tid'" );
      return $qsql;
   }

   /*! \brief Returns TournamentRound-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $trd = new TournamentRound(
            // from TournamentRound
            @$row['ID'],
            @$row['tid'],
            @$row['Round'],
            @$row['Status'],
            @$row['MinPoolSize'],
            @$row['MaxPoolSize'],
            @$row['MaxPoolCount'],
            @$row['PoolWinnerRanks'],
            @$row['Pools'],
            @$row['PoolSize'],
            @$row['X_Lastchanged'],
            @$row['ChangedBy']
         );
      return $trd;
   }

   /*! \brief Loads and returns TournamentRound-object for given tournament-ID and round [1..n]. */
   public static function load_tournament_round( $tid, $round )
   {
      $result = NULL;
      if ( $tid > 0 )
      {
         $qsql = self::build_query_sql( $tid );
         $qsql->add_part( SQLP_LIMIT, '1' );

         if ( $round > 0 ) // get specific round
            $qsql->add_part( SQLP_WHERE, "TRD.Round='{$round}'" );
         else // get latest round
            $qsql->add_part( SQLP_ORDER, "TRD.Round DESC'" );

         $row = mysql_single_fetch( "TournamentRound:load_tournament_round($tid,$round)",
            $qsql->get_select() );
         if ( $row )
            $result = self::new_from_row( $row );
      }
      return $result;
   }//load_tournament_round

   /*! \brief Loads TournamentRound-object for given tournament-round-ID. */
   public static function load_tournament_round_by_id( $id )
   {
      $id = (int)$id;
      $qsql = self::build_query_sql();
      $qsql->add_part( SQLP_WHERE, "ID=$id" );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "TournamentRound:load_tournament_round_by_id($id)", $qsql->get_select() );
      return ( $row ) ? self::new_from_row( $row ) : null;
   }//load_tournament_round_by_id

   /*! \brief Returns enhanced (passed) ListIterator with TournamentRound-objects for given tournament-id. */
   public static function load_tournament_rounds( $iterator, $tid )
   {
      $qsql = self::build_query_sql( $tid );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentRound:load_tournament_rounds", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $tourney = self::new_from_row( $row );
         $iterator->addItem( $tourney, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_tournament_rounds

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   public static function getStatusText( $status=null )
   {
      static $ARR_TROUND_STATUS = null; // status => text

      // lazy-init of texts
      if ( is_null($ARR_TROUND_STATUS) )
      {
         $arr = array();
         $arr[TROUND_STATUS_INIT] = T_('Init#trd_status');
         $arr[TROUND_STATUS_POOL] = T_('Pool#trd_status');
         $arr[TROUND_STATUS_PAIR] = T_('Pair#trd_status');
         $arr[TROUND_STATUS_PLAY] = T_('Play#trd_status');
         $arr[TROUND_STATUS_DONE] = T_('Done#trd_status');
         $ARR_TROUND_STATUS = $arr;
      }

      if ( is_null($status) )
         return $ARR_TROUND_STATUS;
      if ( !isset($ARR_TROUND_STATUS[$status]) )
         error('invalid_args', "Tournament:getStatusText($status)");
      return $ARR_TROUND_STATUS[$status];
   }//getStatusText

   /*! \brief Authorise setting/switching of T-round depending on tourney-status; return error-msg or false (=ok). */
   public static function authorise_set_tround( $t_status )
   {
      static $ARR_TSTATUS = array( TOURNEY_STATUS_PAIR, TOURNEY_STATUS_PLAY );
      if ( TournamentUtils::isAdmin() || in_array($t_status, $ARR_TSTATUS) )
         return false;
      else
      {
         return sprintf( T_('Setting current tournament round is only allowed on tournament status [%s].'),
            build_text_list('Tournament::getStatusText', $ARR_TSTATUS) );
      }
   }//authorise_set_tround

   public static function delete_cache_tournament_round( $dbgmsg, $tid, $round )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_TROUND, "TRound.$tid.$round" );
   }

} // end of 'TournamentRound'
?>
