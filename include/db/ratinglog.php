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

$TranslateGroups[] = "Game";

require_once 'include/globals.php';
require_once 'include/db_classes.php';
require_once 'include/std_classes.php';

 /*!
  * \file ratinglog.php
  *
  * \brief Functions for managing rating results: tables Ratinglog
  */


 /*!
  * \class Ratinglog
  *
  * \brief Class to manage rating-log
  */

global $ENTITY_RATINGLOG; //PHP5
$ENTITY_RATINGLOG = new Entity( 'Ratinglog',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_INT,  'ID', 'uid', 'gid',
      FTYPE_FLOAT, 'Rating', 'RatingMin', 'RatingMax', 'RatingDiff',
      FTYPE_DATE, 'Time'
   );

class Ratinglog
{
   var $ID;
   var $uid;
   var $gid;
   var $Time;
   var $Rating;
   var $RatingMin;
   var $RatingMax;
   var $RatingDiff;

   /*! \brief Constructs Ratinglog-object with specified arguments. */
   function Ratinglog( $id=0, $uid=0, $gid=0, $time=0,
                   $rating=null, $rating_min=null, $rating_max=null, $rating_diff=null )
   {
      $this->ID = (int)$id;
      $this->uid = (int)$uid;
      $this->gid = (int)$gid;
      $this->Time = (int)$time;
      $this->Rating = is_null($rating) ? null : (float)$rating;
      $this->RatingMin = is_null($rating_min) ? null : (float)$rating_min;
      $this->RatingMax = is_null($rating_max) ? null : (float)$rating_max;
      $this->RatingDiff = is_null($rating_diff) ? null : (float)$rating_diff;
   }

   function to_string()
   {
      return print_r($this, true);
   }

   /*! \brief Inserts or updates Ratinglog-entry in database. */
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
      $this->Time = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      $result = $entityData->insert( "Ratinglog.insert(%s)" );
      if( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   function update()
   {
      $entityData = $this->fillEntityData();
      return $entityData->update( "Ratinglog.update(%s)" );
   }

   function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "Ratinglog.delete(%s)" );
   }

   function fillEntityData( &$data=null )
   {
      if( is_null($data) )
         $data = $GLOBALS['ENTITY_RATINGLOG']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'gid', $this->gid );
      $data->set_value( 'Time', $this->Time );
      $data->set_value( 'Rating', $this->Rating );
      $data->set_value( 'RatingMin', $this->RatingMin );
      $data->set_value( 'RatingMax', $this->RatingMax );
      $data->set_value( 'RatingDiff', $this->RatingDiff );
      return $data;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of Ratinglog-objects for given game-id. */
   function build_query_sql( $gid=0, $uid=0 )
   {
      $qsql = $GLOBALS['ENTITY_RATINGLOG']->newQuerySQL('RL');
      if( $gid > 0 )
         $qsql->add_part( SQLP_WHERE, "RL.gid='$gid'" );
      if( $uid > 0 )
         $qsql->add_part( SQLP_WHERE, "RL.uid='$uid'" );
      return $qsql;
   }

   /*! \brief Returns Ratinglog-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $rl = new Ratinglog(
            // from Ratinglog
            @$row['ID'],
            @$row['uid'],
            @$row['gid'],
            @$row['X_Time'],
            @$row['Rating'],
            @$row['RatingMin'],
            @$row['RatingMax'],
            @$row['RatingDiff']
         );
      return $rl;
   }

   /*!
    * \brief Loads and returns Ratinglog-object for given games-id limited to 1 result-entry.
    * \param $query_qsql QuerySQL restricting entries, expecting one result
    * \return NULL if nothing found; Games otherwise
    */
   function load_ratinglog_with_query( $query_qsql )
   {
      if( !is_a($query_qsql, 'QuerySQL') )
         error('invalid_args', "Ratinglog.load_ratinglog_with_query");

      $qsql = Ratinglog::build_query_sql();
      $qsql->merge( $query_qsql );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "Ratinglog::load_ratinglog_with_query.find_ratinglog()",
         $qsql->get_select() );
      return ($row) ? Ratinglog::new_from_row($row) : NULL;
   }

   /*! \brief Returns enhanced (passed) ListIterator with Ratinglog-objects. */
   function load_ratinglogs( $iterator, $gid=0, $uid=0 )
   {
      $qsql = Ratinglog::build_query_sql( $gid, $uid );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "Ratinglog.load_ratinglogs", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $rlog = RatingLog::new_from_row( $row );
         $iterator->addItem( $rlog, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

} // end of 'Ratinglog'
?>
