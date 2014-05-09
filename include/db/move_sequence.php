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
require_once 'include/dgs_cache.php';
require_once 'include/std_classes.php';

 /*!
  * \file move_sequence.php
  *
  * \brief Functions for managing shapes: tables MoveSequence
  * \see specs/db/table-Games.txt
  */

define('MSEQ_FLAG_PRIVATE', 0x01); // conditional moves only visible to "author"

define('MSEQ_ERR_MISS_CONTEXT_MOVE', 1);
define('MSEQ_ERR_NO_LAST_MOVE', 2);
define('MSEQ_ERR_NEXT_MOVE_VARNODE', 3);
define('MSEQ_ERR_NEXT_MOVE_BAD_MOVE_NR', 4);
define('MSEQ_ERR_NEXT_MOVE_COLOR_MISMATCH', 5);
define('MSEQ_ERR_ILLEGAL_STATE_PASS_MOVE', 6);
define('MSEQ_ERR_ILLEGAL_MOVE_COORDS', 7);
define('MSEQ_ERR_ILLEGAL_MOVE_KO', 8);
define('MSEQ_ERR_ILLEGAL_MOVE_SUICIDE', 9);


 /*!
  * \class MoveSequence
  *
  * \brief Class to manage MoveSequence-table
  */

global $ENTITY_MOVE_SEQUENCE; //PHP5
$ENTITY_MOVE_SEQUENCE = new Entity( 'MoveSequence',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_INT,  'ID', 'gid', 'uid', 'Flags', 'ErrorCode', 'StartMoveNr', 'LastMoveNr', 'LastMovePos',
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
   public $ErrorCode;
   public $StartMoveNr;
   public $StartMove;
   public $LastMoveNr;
   public $LastMovePos;
   public $LastMove;
   public $Sequence;

   // non-DB fields:

   public $parsed_nodes = null; // SGF-game-tree

   /*! \brief Constructs MoveSequence-object with specified arguments. */
   public function __construct( $id=0, $gid=0, $uid=0, $status=MSEQ_STATUS_INACTIVE, $flags=0, $error_code=0,
         $start_move_nr=0, $start_move='', $last_move_nr=0, $last_move_pos=0, $last_move='', $sequence='' )
   {
      $this->ID = (int)$id;
      $this->gid = (int)$gid;
      $this->uid = (int)$uid;
      $this->setStatus( $status );
      $this->Flags = (int)$flags;
      $this->ErrorCode = (int)$error_code;
      $this->StartMoveNr = (int)$start_move_nr;
      $this->StartMove = $start_move;
      $this->set_last_move_info( $last_move_nr, $last_move_pos, $last_move );
      $this->Sequence = $sequence;
   }//__construct

   public function setStatus( $status )
   {
      if ( !preg_match( "/^(".CHECK_MSEQ_STATUS.")$/", $status ) )
         error('invalid_args', "MoveSequence.setStatus($status)");
      $this->Status = $status;
   }

   public function set_last_move_info( $last_move_nr, $last_move_pos, $last_move )
   {
      $this->LastMoveNr = (int)$last_move_nr;
      $this->LastMovePos = (int)$last_move_pos;
      $this->LastMove = $last_move;
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
      self::delete_cache_move_sequence( "move_sequence.insert({$this->gid},{$this->uid})", $this->gid, $this->uid );
      return $result;
   }

   public function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      $result = $entityData->update( "move_sequence.update(%s)" );
      self::delete_cache_move_sequence( "move_sequence.update({$this->gid},{$this->uid})", $this->gid, $this->uid );
      return $result;
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
      $data->set_value( 'ErrorCode', $this->ErrorCode );
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
   public static function build_query_sql( $gid, $uid=0 )
   {
      $qsql = $GLOBALS['ENTITY_MOVE_SEQUENCE']->newQuerySQL('MS');
      $qsql->add_part( SQLP_WHERE, "MS.gid=$gid" );
      if ( $uid > 0 )
         $qsql->add_part( SQLP_WHERE, "MS.uid=$uid" );
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
            @$row['ErrorCode'],
            @$row['StartMoveNr'],
            @$row['StartMove'],
            @$row['LastMoveNr'],
            @$row['LastMovePos'],
            @$row['LastMove'],
            @$row['Sequence']
         );
      return $move_seq;
   }//new_from_row

   /*!
    * \brief Loads and returns latest MoveSequence-object (biggest ID) for given game-id and user-id.
    * \return NULL if nothing found; MoveSequence-object otherwise
    */
   public static function load_last_move_sequence( $gid, $uid, $status=null )
   {
      $qsql = self::build_query_sql( $gid, $uid );
      if ( !is_null($status) )
         $qsql->add_part( SQLP_WHERE, "MS.Status='".mysql_addslashes($status)."'" );
      $qsql->add_part( SQLP_ORDER, 'MS.ID DESC' );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "MoveSequence:load_last_move_sequence.find_move_seq($gid,$uid)", $qsql->get_select() );
      return ($row) ? self::new_from_row($row) : NULL;
   }//load_last_move_sequence

   /*! \brief Returns enhanced (passed) ListIterator with MoveSequence-objects. */
   public static function load_move_sequences( $iterator, $gid, $uid=0 )
   {
      $qsql = self::build_query_sql( $gid, $uid );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "MoveSequence:load_move_sequences", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $move_seq = self::new_from_row( $row );
         $iterator->addItem( $move_seq, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_move_sequences

   /*!
    * \brief Loads and returns latest MoveSequence-object (biggest ID) for given game-id and user-id from cache.
    * \return NULL if nothing found; MoveSequence-object otherwise
    */
   public static function load_cache_last_move_sequence( $dbgmsg, $gid, $uid )
   {
      $dbgmsg = "MoveSequence:load_cache_last_move_sequence($gid,$uid).$dbgmsg";
      $key = "CondMoves.$gid.$uid";

      $move_seq = DgsCache::fetch( $dbgmsg, CACHE_GRP_COND_MOVES, $key );
      if ( is_null($move_seq) )
      {
         $move_seq = self::load_last_move_sequence( $gid, $uid );
         $cache_val = ( is_null($move_seq) ) ? 0 : $move_seq; // store NULL (=not-found) as 0 to differ from "not-cached"
         DgsCache::store( $dbgmsg, CACHE_GRP_COND_MOVES, $key, $cache_val, SECS_PER_HOUR, "CondMoves.$gid" );
      }
      elseif ( is_numeric($move_seq) )
         $move_seq = null;

      return $move_seq;
   }//load_cache_last_move_sequence

   /*! \brief Deactives still active conditional-moves setting them to INACTIVE-status. */
   public static function deactivate_move_sequences( $dbgmsg, $gid )
   {
      $gid = (int)$gid;
      $result = db_query( $dbgmsg.".MoveSequence:deactivate_move_sequences($gid)",
         "UPDATE MoveSequence SET Status='".MSEQ_STATUS_INACTIVE."' " .
         "WHERE gid=$gid AND Status='".MSEQ_STATUS_ACTIVE."'" );
      self::delete_cache_move_sequence( $dbgmsg, $gid );
      return $result;
   }

   public static function delete_cache_move_sequence( $dbgmsg, $gid, $uid=0 )
   {
      if ( $uid <= 0 )
         DgsCache::delete_group( $dbgmsg, CACHE_GRP_COND_MOVES, "CondMoves.$gid" );
      else
         DgsCache::delete( $dbgmsg, CACHE_GRP_COND_MOVES, "CondMoves.$gid.$uid" );
   }

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   public static function getStatusText( $status )
   {
      static $ARR_MSEQ_STATUS = null; // status => text

      // lazy-init of texts
      if ( is_null($ARR_MSEQ_STATUS) )
      {
         $arr = array();
         $arr[MSEQ_STATUS_INACTIVE] = T_('Inactive#CM_status');
         $arr[MSEQ_STATUS_ACTIVE] = T_('Active#CM_status');
         $arr[MSEQ_STATUS_ILLEGAL] = T_('Illegal#CM_status');
         $arr[MSEQ_STATUS_OPP_MSG] = T_('Opponent Message#CM_status');
         $arr[MSEQ_STATUS_DEVIATED] = T_('Deviated#CM_status');
         $arr[MSEQ_STATUS_DONE] = T_('Done#CM_status');
         $ARR_MSEQ_STATUS = $arr;
      }

      if ( !isset($ARR_MSEQ_STATUS[$status]) )
         error('invalid_args', "MoveSequence:getStatusText($status)");
      return $ARR_MSEQ_STATUS[$status];
   }//getStatusText

   /*! \brief Returns text for error-codes. */
   public static function getErrorCodeText( $error_code )
   {
      static $ARR_MSEQ_ERRORCODE = null; // error-code => text

      // lazy-init of texts
      if ( is_null($ARR_MSEQ_STATUS) )
      {
         $arr = array();
         $arr[MSEQ_ERR_MISS_CONTEXT_MOVE] = T_('Missing context start-move#CM_err');
         $arr[MSEQ_ERR_NO_LAST_MOVE] = T_('No last move found#CM_err');
         $arr[MSEQ_ERR_NEXT_MOVE_VARNODE] = T_('Expected move-node, but found variation#CM_err');
         $arr[MSEQ_ERR_NEXT_MOVE_BAD_MOVE_NR] = T_('Unexpected move-nr for next-move found#CM_err');
         $arr[MSEQ_ERR_NEXT_MOVE_COLOR_MISMATCH] = T_('Color mismatch for next-move#CM_err');
         $arr[MSEQ_ERR_ILLEGAL_STATE_PASS_MOVE] = T_('Bad game-status for PASS-move#CM_err');
         $arr[MSEQ_ERR_ILLEGAL_MOVE_COORDS] = T_('Illegal position for next-move#CM_err');
         $arr[MSEQ_ERR_ILLEGAL_MOVE_KO] = T_('Illegal ko for next-move#CM_err');
         $arr[MSEQ_ERR_ILLEGAL_MOVE_SUICIDE] = T_('Illegal suicide for next-move#CM_err');
         $ARR_MSEQ_ERRORCODE = $arr;
      }

      if ( !isset($ARR_MSEQ_ERRORCODE[$error_code]) )
         error('invalid_args', "MoveSequence:getErrorCodeText($error_code)");
      return $ARR_MSEQ_ERRORCODE[$error_code];
   }//getErrorCodeText

} // end of 'MoveSequence'
?>
