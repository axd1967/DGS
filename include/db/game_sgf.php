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

require_once 'include/globals.php';
require_once 'include/db_classes.php';
require_once 'include/std_classes.php';

 /*!
  * \file game_sgf.php
  *
  * \brief Functions for managing SGFs saved for game: table GameSgf
  * \see specs/db/table-Games.txt
  */



 /*!
  * \class GameSgf
  *
  * \brief Class to manage GameSgf-table
  */

global $ENTITY_GAME_SGF; //PHP5
$ENTITY_GAME_SGF = new Entity( 'GameSgf',
      FTYPE_PKEY, 'gid', 'uid',
      FTYPE_INT,  'gid', 'uid',
      FTYPE_DATE, 'Lastchanged',
      FTYPE_TEXT, 'SgfData'
   );

class GameSgf
{
   var $gid;
   var $uid;
   var $Lastchanged;
   var $SgfData;

   /*! \brief Constructs GameSgf-object with specified arguments. */
   function GameSgf( $gid, $uid, $lastchanged=0, $sgf_data='' )
   {
      $this->gid = (int)$gid;
      $this->uid = (int)$uid;
      $this->Lastchanged = (int)$lastchanged;
      $this->SgfData = $sgf_data;
   }

   function to_string()
   {
      return print_r($this, true);
   }

   /*! \brief Inserts or updates GameSgf in database. */
   function persist()
   {
      $entityData = $this->fillEntityData();
      $query = $entityData->build_sql_insert_values(true)
         . $entityData->build_sql_insert_values()
         . " ON DUPLICATE KEY UPDATE Lastchanged=VALUES(Lastchanged), SgfData=VALUES(SgfData)";
      return db_query( "GameSgf.persist.on_dupl_key({$this->gid},{$this->uid})", $query );
   }

   function insert()
   {
      $entityData = $this->fillEntityData();
      return $entityData->insert( "GameSgf.insert(%s)" );
   }

   function update()
   {
      $entityData = $this->fillEntityData();
      return $entityData->update( "GameSgf.update(%s)" );
   }

   function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "GameSgf.delete(%s)" );
   }

   function fillEntityData( $data=null )
   {
      if( is_null($data) )
         $data = $GLOBALS['ENTITY_GAME_SGF']->newEntityData();
      $data->set_value( 'gid', $this->gid );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'SgfData', $this->SgfData );
      return $data;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of GameSgf-objects for given game-id and uid. */
   function build_query_sql( $gid, $uid=0 )
   {
      $qsql = $GLOBALS['ENTITY_GAME_SGF']->newQuerySQL('GSGF');
      $qsql->add_part( SQLP_WHERE, "GSGF.gid=$gid" );
      if( $uid > 0 )
         $qsql->add_part( SQLP_WHERE, "GSGF.uid=$uid" );
      return $qsql;
   }

   /*! \brief Returns GameSgf-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $game_sgf = new GameSgf(
            // from GameSgf
            @$row['gid'],
            @$row['uid'],
            @$row['X_Lastchanged'],
            @$row['SgfData']
         );
      return $game_sgf;
   }

   /*!
    * \brief Loads and returns GameSgf-object for given game-id and user-id limited to 1 result-entry.
    * \param $gid Games.ID
    * \param $uid Players.ID (SGF-author)
    * \return NULL if nothing found; GameSgf-object otherwise
    */
   function load_game_sgf( $gid, $uid )
   {
      $qsql = GameSgf::build_query_sql( $gid, $uid );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "GameSgf::load_game_sgf.find($gid,$uid)", $qsql->get_select() );
      return ($row) ? GameSgf::new_from_row($row) : NULL;
   }

   /*! \brief Returns non-null array of GameSgf-objects for given game-id. */
   function load_game_sgfs( $gid )
   {
      $qsql = GameSgf::build_query_sql( $gid );
      $qsql->add_part( SQLP_ORDER, "Lastchanged ASC" );
      $query = $qsql->get_select();
      $db_result = db_query( "GameSgf.load_game_sgfs($gid)", $query );

      $result = array();
      while( $row = mysql_fetch_array($db_result) )
         $result[] = GameSgf::new_from_row( $row );
      mysql_free_result($db_result);

      return $result;
   }

   function count_game_sgfs( $gid )
   {
      $row = mysql_single_fetch( "GameSgf.count_game_sgfs($gid)",
         "SELECT COUNT(*) AS X_Count FROM GameSgf WHERE gid=$gid" );
      return ($row) ? (int)$row['X_Count'] : 0;
   }

} // end of 'GameSgf'
?>
