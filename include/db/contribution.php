<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Docs";

require_once 'include/globals.php';
require_once 'include/db_classes.php';
require_once 'include/std_classes.php';

 /*!
  * \file Contribution.php
  *
  * \brief Functions for managing server contributions: table Contribution
  * \see specs/db/table-Players.txt
  */

define('CONTRIB_CAT_FOUNDER',       'FOUNDER');
define('CONTRIB_CAT_DEV_MAIN',      'DEV_MAIN');
define('CONTRIB_CAT_DEV_RECRUIT',   'DEV_RECRUIT');
define('CONTRIB_CAT_DEV_CLIENT',    'DEV_CLIENT');
define('CONTRIB_CAT_OTHER',         'OTHER');
define('CHECK_CONTRIB_CATEGORY', 'FOUNDER|DEV_MAIN|DEV_RECRUIT|DEV_CLIENT|OTHER');

 /*!
  * \class Contribution
  *
  * \brief Class to manage Contribution-table
  */

global $ENTITY_CONTRIBUTION; //PHP5
$ENTITY_CONTRIBUTION = new Entity( 'Contribution',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_INT,  'ID', 'uid',
      FTYPE_ENUM, 'Category',
      FTYPE_TEXT, 'Comment',
      FTYPE_DATE, 'Created', 'Updated'
   );

class Contribution
{
   public $ID;
   public $uid;
   public $Category;
   public $Comment;
   public $Created;
   public $Updated;

   // other DB-fields

   public $crow = null;

   /*! \brief Constructs Contribution-object with specified arguments. */
   public function __construct( $id=0, $uid=0, $category=CONTRIB_CAT_OTHER, $comment='', $created=0, $updated=0 )
   {
      $this->ID = (int)$id;
      $this->uid = (int)$uid;
      $this->setCategory( $category );
      $this->Comment = trim($comment);
      $this->Created = (int)$created;
      $this->Updated = (int)$updated;
   }//__construct

   public function setCategory( $category )
   {
      if ( !preg_match( "/^(".CHECK_CONTRIB_CATEGORY.")$/", $category ) )
         error('invalid_args', "Contribution.setCategory($category)");
      $this->Category = $category;
   }

   public function to_string()
   {
      return print_r($this, true);
   }

   /*! \brief Inserts or updates Contribution-entry in database. */
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
      $this->Created = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      $result = $entityData->insert( "Contribution.insert(%s)" );
      if ( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   public function update()
   {
      $this->Updated = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->update( "Contribution.update(%s)" );
   }

   public function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "Contribution.delete(%s)" );
   }

   public function fillEntityData( $data=null )
   {
      if ( is_null($data) )
         $data = $GLOBALS['ENTITY_CONTRIBUTION']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'Category', $this->Category );
      $data->set_value( 'Comment', $this->Comment );
      $data->set_value( 'Created', $this->Created );
      $data->set_value( 'Updated', $this->Updated );
      return $data;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of Contribution-objects for given arguments. */
   public static function build_query_sql( $with_players_info=false )
   {
      $qsql = $GLOBALS['ENTITY_CONTRIBUTION']->newQuerySQL('CTB');
      if ( $with_players_info )
      {
         $qsql->add_part( SQLP_FIELDS, 'CTB_P.Handle AS CTB_Handle', 'CTB_P.Name AS CTB_Name' );
         $qsql->add_part( SQLP_FROM, 'INNER JOIN Players AS CTB_P ON CTB_P.ID=CTB.uid' );
      }
      return $qsql;
   }//build_query_sql

   /*! \brief Returns Contribution-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $contrib= new Contribution(
            // from Contribution
            @$row['ID'],
            @$row['uid'],
            @$row['Category'],
            @$row['Comment'],
            @$row['X_Created'],
            @$row['X_Updated']
         );
      $contrib->crow = $row;
      return $contrib;
   }//new_from_row

   /*!
    * \brief Loads and returns Contribution-object for given contribution-id.
    * \return NULL if nothing found; Contribution-object otherwise
    */
   public static function load_contribution( $id, $with_player_info=false )
   {
      if ( !is_numeric($id) )
         error('invalid_args', "Contribution:load_contribution($id,$with_player_info)");
      $qsql = self::build_query_sql( $with_player_info );
      $qsql->add_part( SQLP_WHERE, "CTB.ID=$id" );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "Contribution:load_contribution.find($id,$with_player_info)", $qsql->get_select() );
      return ($row) ? self::new_from_row($row) : NULL;
   }//load_contribution

   /*! \brief Returns enhanced (passed) ListIterator with Contribution-objects for all(uid=0) or for specified user-id. */
   public static function load_contributions( $iterator, $uid=0, $with_player_info=false )
   {
      $qsql = self::build_query_sql( $with_player_info );
      if ( is_numeric($uid) && $uid > GUESTS_ID_MAX )
         $qsql->add_part( SQLP_WHERE, "CTB.uid=$uid" );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "Contribution:load_contributions($uid,$with_player_info)", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $contrib = self::new_from_row( $row );
         $iterator->addItem( $contrib, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_contributions

   /*! \brief Returns category-text or all category-texts (if arg=null). */
   public static function getCategoryText( $category=null )
   {
      static $ARR_CATEGORIES = null; // status => text

      // lazy-init of texts
      if ( is_null($ARR_CATEGORIES) )
      {
         $arr = array();
         $arr[CONTRIB_CAT_FOUNDER] = T_('Founder#CTB_category');
         $arr[CONTRIB_CAT_DEV_MAIN] = T_('Developer (Main)#CTB_category');
         $arr[CONTRIB_CAT_DEV_RECRUIT] = T_('Developer (Recruit)#CTB_category');
         $arr[CONTRIB_CAT_DEV_CLIENT] = T_('Developer (Client)#CTB_category');
         $arr[CONTRIB_CAT_OTHER] = T_('Other#CTB_category');
         $ARR_CATEGORIES = $arr;
      }

      if ( is_null($category) )
         return $ARR_CATEGORIES;
      if ( !isset($ARR_CATEGORIES[$category]) )
         error('invalid_args', "Contribution:getCategoryText($category)");
      return $ARR_CATEGORIES[$category];
   }//getCategoryText

} // end of 'Contribution'
?>
