<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Games";

require_once 'include/globals.php';
require_once 'include/db_classes.php';
require_once 'include/std_classes.php';
require_once 'include/classlib_user.php';

 /*!
  * \file shape.php
  *
  * \brief Functions for managing shapes: tables Shape
  * \see specs/db/table-Games.txt
  */

// also adjust GuiShape::getFlagsText()
define('SHAPE_FLAG_PLAYCOLOR_W', 0x01);


 /*!
  * \class Shape
  *
  * \brief Class to manage Shape-table
  */

global $ENTITY_SHAPE; //PHP5
$ENTITY_SHAPE = new Entity( 'Shape',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_INT,  'ID', 'uid', 'Size', 'Flags',
      FTYPE_TEXT, 'Name', 'Snapshot', 'Notes',
      FTYPE_DATE, 'Created', 'Lastchanged'
   );

class Shape
{
   var $ID;
   var $uid;
   var $Name;
   var $Size;
   var $Flags;
   var $Snapshot;
   var $Notes;
   var $Created;
   var $Lastchanged;

   // non-DB fields

   var $User; // User-object

   /*! \brief Constructs Bulletin-object with specified arguments. */
   function Shape( $id=0, $uid=0, $user=null, $name='', $size=19, $flags=0, $snapshot='', $notes='',
         $created=0, $lastchanged=0 )
   {
      $this->ID = (int)$id;
      $this->uid = (int)$uid;
      $this->Name = $name;
      $this->Size = (int)$size;
      $this->Flags = (int)$flags;
      $this->Snapshot = $snapshot;
      $this->Notes = $notes;
      $this->Created = (int)$created;
      $this->Lastchanged = (int)$lastchanged;
      // non-DB fields
      $this->User = (is_a($user, 'User')) ? $user : new User( $this->uid );
   }

   function to_string()
   {
      return print_r($this, true);
   }

   /*! \brief Inserts or updates Shape-entry in database. */
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
      $this->Created = $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      $result = $entityData->insert( "Shape.insert(%s)" );
      if( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->update( "Shape.update(%s)" );
   }

   function fillEntityData( $data=null )
   {
      if( is_null($data) )
         $data = $GLOBALS['ENTITY_SHAPE']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'Name', $this->Name );
      $data->set_value( 'Size', $this->Size );
      $data->set_value( 'Flags', $this->Flags );
      $data->set_value( 'Snapshot', $this->Snapshot );
      $data->set_value( 'Notes', $this->Notes );
      $data->set_value( 'Created', $this->Created );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      return $data;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of Shape-objects for given shape-id. */
   function build_query_sql( $shape_id=0, $with_player=true )
   {
      $qsql = $GLOBALS['ENTITY_SHAPE']->newQuerySQL('SHP');
      if( $with_player )
      {
         $qsql->add_part( SQLP_FIELDS,
            'SHP.uid AS SHPP_ID',
            'SHPP.Name AS SHPP_Name',
            'SHPP.Handle AS SHPP_Handle' );
         $qsql->add_part( SQLP_FROM,
            'INNER JOIN Players AS SHPP ON SHPP.ID=SHP.uid' );
      }
      if( $shape_id > 0 )
         $qsql->add_part( SQLP_WHERE, "SHP.ID=$shape_id" );
      return $qsql;
   }

   /*! \brief Returns Shape-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $shape = new Shape(
            // from Shape
            @$row['ID'],
            @$row['uid'],
            User::new_from_row( $row, 'SHPP_' ), // from Players SHPP
            @$row['Name'],
            @$row['Size'],
            @$row['Flags'],
            @$row['Snapshot'],
            @$row['Notes'],
            @$row['X_Created'],
            @$row['X_Lastchanged']
         );
      return $shape;
   }

   /*!
    * \brief Loads and returns Shape-object for given shape-id limited to 1 result-entry.
    * \param $shape_id Shape.ID
    * \return NULL if nothing found; Shape-object otherwise
    */
   function load_shape( $shape_id, $with_player=true )
   {
      $qsql = Shape::build_query_sql( $shape_id, $with_player );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "Shape::load_shape.find_shape($shape_id)", $qsql->get_select() );
      return ($row) ? Shape::new_from_row($row) : NULL;
   }

   /*! \brief Loads Shape by name; or return NULL if not found. */
   function load_shape_by_name( $name, $with_player=false )
   {
      $qsql = Shape::build_query_sql( 0, $with_player );
      $qsql->add_part( SQLP_WHERE, "SHP.Name='".mysql_addslashes($name)."'" );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "Shape::load_shape_by_name.find_shape($name)", $qsql->get_select() );
      return ($row) ? Shape::new_from_row($row) : NULL;
   }

   /*! \brief Returns enhanced (passed) ListIterator with Shape-objects. */
   function load_shapes( $iterator )
   {
      $qsql = Shape::build_query_sql();
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "Shape.load_shapes", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $shape = Shape::new_from_row( $row );
         $iterator->addItem( $shape, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

} // end of 'Shape'
?>
