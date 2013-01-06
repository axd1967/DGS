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
  * \file ratingchangeadmin.php
  *
  * \brief Functions for managing rating results: tables RatingChangeAdmin
  */


 /*!
  * \class RatinChangeAdmin
  *
  * \brief Class to manage RatingChangeAdmin-table
  */

global $ENTITY_RATING_CHANGE_ADMIN; //PHP5
$ENTITY_RATING_CHANGE_ADMIN = new Entity( 'RatingChangeAdmin',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_INT,  'ID', 'uid', 'Changes',
      FTYPE_FLOAT, 'Rating',
      FTYPE_DATE, 'Created'
   );

class RatingChangeAdmin
{
   var $ID;
   var $uid;
   var $Created;
   var $Changes;
   var $Rating;

   /*! \brief Constructs RatingChangeAdmin-object with specified arguments. */
   function RatingChangeAdmin( $id=0, $uid=0, $created=0, $changes=0, $rating=NO_RATING )
   {
      $this->ID = (int)$id;
      $this->uid = (int)$uid;
      $this->Created = (int)$created;
      $this->Changes = (int)$changes;
      $this->Rating = is_null($rating) ? null : (float)$rating;
   }

   function to_string()
   {
      return print_r($this, true);
   }

   /*! \brief Inserts or updates RatingChangeAdmin-entry in database. */
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
      $this->Created = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      $result = $entityData->insert( "RatingChangeAdmin.insert(%s)" );
      if( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   function update()
   {
      $entityData = $this->fillEntityData();
      return $entityData->update( "RatingChangeAdmin.update(%s)" );
   }

   function fillEntityData( $data=null )
   {
      if( is_null($data) )
         $data = $GLOBALS['ENTITY_RATING_CHANGE_ADMIN']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'Created', $this->Created );
      $data->set_value( 'Changes', $this->Changes );
      $data->set_value( 'Rating', $this->Rating );
      return $data;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of RatingChangeAdmin-objects for given game-id. */
   function build_query_sql( $uid=0 )
   {
      $qsql = $GLOBALS['ENTITY_RATING_CHANGE_ADMIN']->newQuerySQL('RCA');
      if( $uid > 0 )
         $qsql->add_part( SQLP_WHERE, "RCA.uid='$uid'" );
      return $qsql;
   }

   /*! \brief Returns RatingChangeAdmin-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $rca = new RatingChangeAdmin(
            // from RatingChangeAdmin
            @$row['ID'],
            @$row['uid'],
            @$row['X_Created'],
            @$row['Changes'],
            @$row['Rating']
         );
      return $rca;
   }

   /*!
    * \brief Loads and returns RatingChangeAdmin-object for given games-id limited to 1 result-entry.
    * \param $query_qsql QuerySQL restricting entries, expecting one result
    * \return NULL if nothing found; RatingChangeAdmin otherwise
    */
   function load_ratingchangeadmin_with_query( $query_qsql )
   {
      if( !is_a($query_qsql, 'QuerySQL') )
         error('invalid_args', "RatingChangeAdmin.load_ratingchangeadmin_with_query");

      $qsql = RatingChangeAdmin::build_query_sql();
      $qsql->merge( $query_qsql );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "RatingChangeAdmin::load_ratingchangeadmin_with_query.find_RatingChangeAdmin()",
         $qsql->get_select() );
      return ($row) ? RatingChangeAdmin::new_from_row($row) : NULL;
   }

   /*! \brief Returns enhanced (passed) ListIterator with RatingChangeAdmin-objects. */
   function load_ratingchangeadmin( $iterator, $uid=0 )
   {
      $qsql = RatingChangeAdmin::build_query_sql( $uid );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "RatingChangeAdmin.load_ratingchangeadmin", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $rca = RatingChangeAdmin::new_from_row( $row );
         $iterator->addItem( $rca, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

} // end of 'RatingChangeAdmin'
?>
