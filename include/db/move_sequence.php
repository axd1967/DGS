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

$TranslateGroups[] = "Game";

require_once 'include/globals.php';
require_once 'include/db_classes.php';
require_once 'include/std_classes.php';

 /*!
  * \file move_sequence.php
  *
  * \brief Functions for managing shapes: tables MoveSequence
  * \see specs/db/table-Games.txt
  */

define('MSEQ_FLAG_PRIVATE', 0x01); // conditional moves only visible to "author"


 /*!
  * \class MoveSequence
  *
  * \brief Class to manage MoveSequence-table
  */

global $ENTITY_MOVE_SEQUENCE; //PHP5
$ENTITY_MOVE_SEQUENCE = new Entity( 'MoveSequence',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_INT,  'ID', 'gid', 'uid', 'Flags', 'StartMoveNr', 'LastMoveNr', 'LastMovePos',
      FTYPE_TEXT, 'StartMove', 'LastMove', 'Sequence',
      FTYPE_ENUM, 'Status'
   );

class MoveSequence
{
   public $ID;
   public $gid;
   public $uid;
   public $Status;
   public $Flags;
   public $StartMoveNr;
   public $StartMove;
   public $LastMoveNr;
   public $LastMovePos;
   public $LastMove;
   public $Sequence;

   /*! \brief Constructs MoveSequence-object with specified arguments. */
   public function __construct( $id=0, $gid=0, $uid=0, $status=MSEQ_STATUS_INACTIVE, $flags=0,
         $start_move_nr=0, $start_move='', $last_move_nr=0, $last_move_pos=0, $last_move='', $sequence='' )
   {
      $this->ID = (int)$id;
      $this->gid = (int)$gid;
      $this->uid = (int)$uid;
      $this->setStatus( $status );
      $this->Flags = (int)$flags;
      $this->StartMoveNr = (int)$start_move_nr;
      $this->StartMove = $start_move;
      $this->LastMoveNr = (int)$last_move_nr;
      $this->LastMovePos = (int)$last_move_pos;
      $this->LastMove = $last_move;
      $this->Sequence = $sequence;
   }//__construct

   public function setStatus( $status )
   {
      if ( !preg_match( "/^(".CHECK_MSEQ_STATUS.")$/", $status ) )
         error('invalid_args', "MoveSequence.setStatus($status)");
      $this->Status = $status;
   }

   /*! \brief Inserts or updates MoveSequence-entry in database. */
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
      $result = $entityData->insert( "move_sequence.insert(%s)" );
      if ( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   public function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->update( "move_sequence.update(%s)" );
   }

   public function fillEntityData( $data=null )
   {
      if ( is_null($data) )
         $data = $GLOBALS['ENTITY_MOVE_SEQUENCE']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'gid', $this->gid );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'Status', $this->Status );
      $data->set_value( 'Flags', $this->Flags );
      $data->set_value( 'StartMoveNr', $this->StartMoveNr );
      $data->set_value( 'StartMove', $this->StartMove );
      $data->set_value( 'LastMoveNr', $this->LastMoveNr );
      $data->set_value( 'LastMovePos', $this->LastMovePos );
      $data->set_value( 'LastMove', $this->LastMove );
      $data->set_value( 'Sequence', $this->Sequence );
      return $data;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of MoveSequence-objects for given game-id. */
   public static function build_query_sql( $gid )
   {
      $qsql = $GLOBALS['ENTITY_MOVE_SEQUENCE']->newQuerySQL('MS');
      $qsql->add_part( SQLP_WHERE, "MS.gid=$gid" );
      return $qsql;
   }//build_query_sql

   /*! \brief Returns MoveSequence-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $move_seq = new MoveSequence(
            // from MoveSequence
            @$row['ID'],
            @$row['gid'],
            @$row['uid'],
            @$row['Status'],
            @$row['Flags'],
            @$row['StartMoveNr'],
            @$row['StartMove'],
            @$row['LastMoveNr'],
            @$row['LastMovePos'],
            @$row['LastMove'],
            @$row['Sequence']
         );
      return $move_seq;
   }//new_from_row

} // end of 'MoveSequence'
?>
