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

require_once 'include/db_classes.php';
require_once 'include/error_codes.php';
require_once 'tournaments/include/tournament_globals.php';

 /*!
  * \file tournament_log.php
  *
  * \brief Functions for handling tournament logging: table Tournamentlog
  */


 /*!
  * \class Tournamentlog
  *
  * \brief Class to manage Tournamentlog-table with logging actions on tournaments
  */

global $ENTITY_TOURNAMENT_LOG; //PHP5
$ENTITY_TOURNAMENT_LOG = new Entity( 'Tournamentlog',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_INT,  'ID', 'tid', 'uid', 'actuid',
      FTYPE_TEXT, 'Type', 'Object', 'Action', 'Message',
      FTYPE_DATE, 'Date'
   );

// type_subtype:
// - type: T, TD, TPR, TRULE, TN, TP, TG, TRES; TL, TLP, TRR, TRND, TPOOL
// - subtype: Lock, Status, Game, Reg, Rank, News, Props, Pool, Round, NextRound
//define('TLOG_OBJ_', 'X');

// actions: Add, Set, Clear, Seed, Start
define('TLOG_ACT_CREATE', 'Create');
define('TLOG_ACT_CHANGE', 'Change');
define('TLOG_ACT_ADDTIME', 'AddTime');
define('TLOG_ACT_REMOVE', 'Remove');

class Tournamentlog
{
   var $ID;
   var $tid;
   var $uid;
   var $Date;
   var $Type;
   var $Object;
   var $Action;
   var $actuid;
   var $Message;

   /*!
    * \brief Constructs Tournamentlog-object with specified arguments.
    * \param $uid user-id, if <=0 use use my-id
    * \param $date creation-date, if <=0 use current date
    * \param $type TLOG_TYPE_..., if empty determine from players admin-level
    */
   function Tournamentlog( $id=0, $tid=0, $uid=0, $date=0, $type='', $object='T', $action='', $actuid=0, $message='' )
   {
      global $player_row, $NOW;

      $this->ID = (int)$id;
      $this->tid = (int)$tid;
      $this->uid = ( is_numeric($uid) && $uid > 0 ) ? (int)$uid : $player_row['ID'];
      $this->Date = ( is_numeric($date) && $date > 0 ) ? (int)$date : $NOW;
      $this->setType( $type );
      $this->Object = $object;
      $this->Action = $action;
      $this->actuid = (int)$actuid;
      $this->Message = $message;
   }

   /* \brief Sets type to TLOG_TYPE_..., if empty determine from players admin-level. */
   function setType( $type )
   {
      if( $type )
      {
         if( !preg_match( "/^(".CHECK_TLOG_TYPES.")$/", $type ) )
            error('invalid_args', "Tournamentlog.setType($type)");
      }
      else
      {
         global $player_row;
         $type = ( @$player_row['admin_level'] & ADMIN_TOURNAMENT ) ? TLOG_TYPE_ADMIN : TLOG_TYPE_USER;
      }

      $this->Type = $type;
   }

   function to_string()
   {
      return print_r($this, true);
   }

   /*! \brief Inserts or updates tournament-log in database. */
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
      $this->Date = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      $result = $entityData->insert( "Tournamentlog::insert(%s,{$this->tid})" );
      if( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   function update()
   {
      $entityData = $this->fillEntityData();
      return $entityData->update( "Tournamentlog::update(%s,{$this->tid})" );
   }

   function fillEntityData()
   {
      $data = $GLOBALS['ENTITY_TOURNAMENT_LOG']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'Date', $this->Date );
      $data->set_value( 'Type', $this->Type );
      $data->set_value( 'Object', $this->Object );
      $data->set_value( 'Action', $this->Action );
      $data->set_value( 'actuid', $this->actuid );
      $data->set_value( 'Message', $this->Message );
      return $data;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of Tournamentlog-objects for given tournament-id. */
   function build_query_sql( $tid=0 )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_LOG']->newQuerySQL('TLOG');
      if( is_numeric($tid) && $tid > 0 )
         $qsql->add_part( SQLP_WHERE, "TLOG.tid=$tid" );
      return $qsql;
   }

   /*! \brief Returns Tournamentlog-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $tlog = new Tournamentlog(
            // from Tournamentlog
            @$row['ID'],
            @$row['tid'],
            @$row['uid'],
            @$row['X_Date'],
            @$row['Type'],
            @$row['Object'],
            @$row['Action'],
            @$row['actuid'],
            @$row['Message']
         );
      return $tlog;
   }

   /*! \brief Returns enhanced (passed) ListIterator with Tournamentlog-objects. */
   function load_tournament_logs( $iterator )
   {
      $qsql = Tournamentlog::build_query_sql();
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "Tournamentlog::load_tournament_log", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $tourney = Tournamentlog::new_from_row( $row );
         $iterator->addItem( $tourney, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

} // end of 'Tournamentlog'
?>
