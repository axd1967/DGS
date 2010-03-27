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


// lazy-init in TournamentRound::get..Text()-funcs
global $ARR_GLOBALS_TOURNAMENT_ROUND; //PHP5
$ARR_GLOBALS_TOURNAMENT_ROUND = array();

global $ENTITY_TOURNAMENT_ROUND; //PHP5
$ENTITY_TOURNAMENT_ROUND = new Entity( 'TournamentRound',
   FTYPE_PKEY,  'ID',
   FTYPE_AUTO,  'ID',
   FTYPE_CHBY,
   FTYPE_INT,   'ID', 'tid', 'Round', 'MinPoolSize', 'MaxPoolSize', 'MaxPoolCount',
   FTYPE_DATE,  'Lastchanged',
   FTYPE_ENUM,  'Status'
   );

class TournamentRound
{
   var $ID;
   var $tid;
   var $Round;
   var $Status;
   var $MinPoolSize;
   var $MaxPoolSize;
   var $MaxPoolCount;
   var $Lastchanged;
   var $ChangedBy;

   /*! \brief Constructs TournamentRound-object with specified arguments. */
   function TournamentRound( $id=0, $tid=0, $round=1, $status=TROUND_STATUS_INIT,
         $min_pool_size=2, $max_pool_size=2, $max_pool_count=0, $lastchanged=0, $changed_by='' )
   {
      $this->ID = (int)$id;
      $this->tid = (int)$tid;
      $this->Round = (int)$round;
      $this->setStatus( $status );
      $this->MinPoolSize = (int)$min_pool_size;
      $this->MaxPoolSize = (int)$max_pool_size;
      $this->MaxPoolCount = (int)$max_pool_count;
      $this->Lastchanged = (int)$lastchanged;
      $this->ChangedBy = $changed_by;
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

   /*! \brief Inserts or updates tournament-round in database. */
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
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData(true);
      $result = $entityData->insert( "Tournament::insert(%s)" );
      if( $result )
         $this->ID = mysql_insert_id();
      return $result;
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

   function fillEntityData()
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
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'ChangedBy', $this->ChangedBy );
      return $data;
   }

   /*! \brief Checks if all round-properties are valid; return error-list, empty if ok. */
   function check_properties()
   {
      $errors = array();

      if( $this->MinPoolSize < 2 || $this->MinPoolSize > TROUND_MAX_POOLSIZE )
         $errors[] = sprintf( T_('Tournament round min. pool size must be in range %s.'),
            TournamentUtils::build_range_text(2, TROUND_MAX_POOLSIZE) );
      if( $this->MaxPoolSize < 2 || $this->MaxPoolSize > TROUND_MAX_POOLSIZE )
         $errors[] = sprintf( T_('Tournament round max. pool size must be in range %s.'),
            TournamentUtils::build_range_text(2, TROUND_MAX_POOLSIZE) );
      if( $this->MinPoolSize > $this->MaxPoolSize )
         $errors[] = T_('Tournament round min. pool size must be smaller than max. pool size.');

      if( $this->MaxPoolCount < 0 || $this->MaxPoolCount > TROUND_MAX_POOLCOUNT )
         $errors[] = sprintf( T_('Tournament Round max. pool count must be in range %s.'),
            TournamentUtils::build_range_text(2, TROUND_MAX_POOLCOUNT) );

      return $errors;
   }

   /*! \brief Returns array( header, notes-array ) with this properties in textual form. */
   function build_notes_props()
   {
      $arr_props = array();

      // status / pool-size / pool-count
      $arr_props[] = sprintf( '%s: %s', T_('Tournament Round Status'),
         TournamentRound::getStatusText($this->Status) );
      $arr_props[] = sprintf( '%s: %s', T_('Pool minimum size'), $this->MinPoolSize );
      $arr_props[] = sprintf( '%s: %s', T_('Pool maximum size'), $this->MaxPoolSize );
      if( $this->MaxPoolCount > 0 )
         $arr_props[] = sprintf( '%s: %s', T_('Maximum Pool count'), $this->MaxPoolCount );

      // general conditions
      $arr_props[] = sprintf( T_("You may only retreat from the tournament round while not in status [%s]."),
         TournamentRound::getStatusText(TROUND_STATUS_PLAY) );

      return array( T_('Configuration of the current tournament round') . ':', $arr_props );
   }//build_notes_props


   // ------------ static functions ----------------------------

   /*! \brief Checks, if there is a tournament-round for given tournament-id and round. */
   function isTournamentRound( $tid, $round )
   {
      return (bool)mysql_single_fetch( "TournamentRound::isTournamentRound($tid,$round)",
         "SELECT 1 FROM TournamentRound WHERE tid='$tid' AND Round='$Round' LIMIT 1" );
   }

   /*!
    * \brief Adds new tournament-round with defaults for given tournament-id,
    *        returning new TournamentRound-object added or null on error.
    */
   function add_tournament_round( $tid )
   {
      global $player_row, $NOW;
      $table = $GLOBALS['ENTITY_TOURNAMENT_ROUND']->table;
      $tround = null;

      // defaults: Status=INIT, MinPoolSize=0, MaxPoolSize=0, MaxPoolCount=0
      $changed_by = EntityData::build_sql_value_changed_by( $player_row['Handle'] );
      $query = "INSERT INTO $table (tid,Round,Lastchanged,ChangedBy) "
             . "SELECT $tid, MAX(Round)+1, FROM_UNIXTIME($NOW), $changed_by FROM $table WHERE tid=$tid";
      if( db_query( "TournamentRound::add_tournament_round.insert($tid)", $query ) )
      {
         $new_id = mysql_insert_id();
         $tround = TournamentRound::load_tournament_round_by_id($new_id);
      }
      return $tround;
   }

   /*! \brief Deletes TournamentRound-entry for given id. */
   function delete_tournament_round( $tid, $round )
   {
      $result = db_query( "TournamentRound::delete_tournament_round($tid,$round)",
         "DELETE FROM TournamentRound WHERE tid='$tid' AND Round='$round' LIMIT 1" );
      return $result;
   }

   /*! \brief Returns db-fields to be used for query of TournamentRound-objects for given tournament-id. */
   function build_query_sql( $tid=0 )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_ROUND']->newQuerySQL('TRD');
      if( $tid > 0 )
         $qsql->add_part( SQLP_WHERE, "TRD.tid='$tid'" );
      return $qsql;
   }

   /*! \brief Returns TournamentRound-object created from specified (db-)row. */
   function new_from_row( $row )
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
            @$row['X_Lastchanged'],
            @$row['ChangedBy']
         );
      return $trd;
   }

   /*! \brief Loads and returns TournamentRound-object for given tournament-ID and round [1..n]. */
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

         $row = mysql_single_fetch( "TournamentRound::load_tournament_round($tid,$round)",
            $qsql->get_select() );
         if( $row )
            $result = TournamentRound::new_from_row( $row );
      }
      return $result;
   }

   /*! \brief Loads TournamentRound-object for given tournament-round-ID. */
   function load_tournament_round_by_id( $id )
   {
      $id = (int)$id;
      $qsql = TournamentRound::build_query_sql();
      $qsql->add_part( SQLP_WHERE, "ID=$id" );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "TournamentRound::load_tournament_round_by_id($id)", $qsql->get_select() );
      return ( $row ) ? TournamentRound::new_from_row( $row ) : null;
   }

   /*! \brief Returns enhanced (passed) ListIterator with TournamentRound-objects for given tournament-id. */
   function load_tournament_rounds( $iterator, $tid )
   {
      $qsql = TournamentRound::build_query_sql( $tid );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentRound::load_tournament_rounds", $query );
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
         $arr[TROUND_STATUS_INIT] = T_('Init#TRD_status');
         $arr[TROUND_STATUS_POOL] = T_('Pool#TRD_status');
         $arr[TROUND_STATUS_PAIR] = T_('Pair#TRD_status');
         $arr[TROUND_STATUS_GAME] = T_('Game#TRD_status');
         $arr[TROUND_STATUS_PLAY] = T_('Play#TRD_status');
         $arr[TROUND_STATUS_DONE] = T_('Done#TRD_status');
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
         TOURNEY_STATUS_NEW, TOURNEY_STATUS_REGISTER, TOURNEY_STATUS_PAIR
      );
      return $statuslist;
   }

   /*! \brief Authorise setting/switching of T-round depending on tourney-status. */
   function authorise_set_tround( $t_status )
   {
      return ( $t_status == TOURNEY_STATUS_PAIR || TournamentUtils::isAdmin() );
   }

} // end of 'TournamentRound'
?>
