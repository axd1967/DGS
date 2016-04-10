<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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

//$TranslateGroups[] = "Tournament";

require_once 'include/db_classes.php';
require_once 'tournaments/include/tournament_globals.php';

 /*!
  * \file tournament_extension.php
  *
  * \brief Functions for handling tournament extension: tables TournamentExtension
  */


 /*!
  * \class TournamentExtension
  *
  * \brief Class to manage TournamentExtension-table
  */

global $ENTITY_TOURNAMENT_EXTENSION; //PHP5
$ENTITY_TOURNAMENT_EXTENSION = new Entity( 'TournamentExtension',
      FTYPE_PKEY, 'tid', 'Property',
      FTYPE_CHBY,
      FTYPE_INT,  'tid', 'Property', 'IntValue',
      FTYPE_DATE, 'DateValue', 'Lastchanged'
   );

class TournamentExtension
{
   public $tid;
   public $Property;
   public $IntValue;
   public $DateValue;
   public $Lastchanged;
   public $ChangedBy;

   /*! \brief Constructs TournamentExtension-object with specified arguments. */
   public function __construct( $tid=0, $property=0, $int_val=0, $date_val=0, $lastchanged=0, $changed_by='' )
   {
      $this->tid = (int)$tid;
      $this->setProperty( $property );
      $this->IntValue = (int)$int_val;
      $this->DateValue = (int)$date_val;
      $this->Lastchanged = (int)$lastchanged;
      $this->ChangedBy = $changed_by;
   }

   public function setProperty( $property )
   {
      if ( !is_numeric($property) || $property <= 0 || $property > TE_MAX_PROP )
         error('invalid_args', "TournamentExtension.setProperty($property)");
      $this->Property = (int)$property;
   }

   /*! \brief Inserts or updates TournamentExtension in database. */
   public function persist()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      $query = $entityData->build_sql_insert_values(true)
         . $entityData->build_sql_insert_values()
         . " ON DUPLICATE KEY UPDATE IntValue=VALUES(IntValue), DateValue=VALUES(DateValue), "
            . "Lastchanged=VALUES(Lastchanged), ChangedBy=VALUES(ChangedBy)";
      return db_query( "TournamentExtension.persist.on_dupl_key({$this->tid},{$this->Property})", $query );
   }

   public function insert()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->insert( "TournamentExtension.insert(%s,{$this->tid},{$this->Property})" );
   }

   public function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->update( "TournamentExtension.update(%s,{$this->tid},{$this->Property})" );
   }

   public function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "TournamentExtension.delete(%s,{$this->tid},{$this->Property})" );
   }

   public function fillEntityData()
   {
      $data = $GLOBALS['ENTITY_TOURNAMENT_EXTENSION']->newEntityData();
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'Property', $this->Property );
      $data->set_value( 'IntValue', $this->IntValue );
      $data->set_value( 'DateValue', $this->DateValue );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'ChangedBy', $this->ChangedBy );
      return $data;
   }

   /*!
    * \brief Tries get lock by storing this TournamentExtension.
    * \param $expire_secs if >0 and TournamentExtension with that property is already stored,
    *       it will be deleted (=unlocked) if the store-date (=Lastchanged) has expired.
    * \return true = successfullly got lock (and tournament-extension has been stored);
    *       false = could not get lock (and store tournament-extension)
    */
   public function getExtensionLock( $expire_secs=0 )
   {
      global $NOW;
      if ( $expire_secs > 0 )
      {
         $t_ext = TournamentExtension::load_tournament_extension( $this->tid, $this->Property );
         if ( !is_null($t_ext) && $t_ext->Lastchanged + $expire_secs <= $NOW )
            $t_ext->delete();
      }

      return $this->insert(); // need to fail if existing
   }//getExtensionLock


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of TournamentExtension-object. */
   public static function build_query_sql( $tid=0, $property=0 )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_EXTENSION']->newQuerySQL('TE');
      if ( $tid > 0 )
         $qsql->add_part( SQLP_WHERE, "TE.tid='$tid'" );
      if ( $property > 0 )
         $qsql->add_part( SQLP_WHERE, "TE.Property='$property'" );
      return $qsql;
   }

   /*! \brief Returns TournamentExtension-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $t_ext = new TournamentExtension(
            // from TournamentExtension
            @$row['tid'],
            @$row['Property'],
            @$row['IntValue'],
            @$row['X_DateValue'],
            @$row['X_Lastchanged'],
            @$row['ChangedBy']
         );
      return $t_ext;
   }

   /*! \brief Loads and returns TournamentExtension-object for given tournament-ID and property; NULL if nothing found. */
   public static function load_tournament_extension( $tid, $property )
   {
      $result = NULL;
      if ( $tid > 0 && $property > 0 )
      {
         $qsql = self::build_query_sql( $tid, $property );
         $qsql->add_part( SQLP_LIMIT, '1' );

         $row = mysql_single_fetch( "TournamentExtension.load_tournament_extension($tid,$property)",
            $qsql->get_select() );
         if ( $row )
            $result = self::new_from_row( $row );
      }
      return $result;
   }//load_tournament_extension

   /*! \brief Returns enhanced (passed) ListIterator with TournamentExtension-objects. */
   public static function load_tournament_extensions( $iterator )
   {
      $qsql = self::build_query_sql();
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentExtension.load_tournament_extensions", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $tourney = self::new_from_row( $row );
         $iterator->addItem( $tourney, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_tournament_extensions

} // end of 'TournamentExtension'

?>
