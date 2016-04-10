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

$TranslateGroups[] = "Start";

require_once 'include/globals.php';
require_once 'include/db_classes.php';
require_once 'include/std_classes.php';

 /*!
  * \file verification.php
  *
  * \brief Functions for managing mail-verification: table Verification
  * \see specs/db/table-Verification.txt
  */

define('VFY_MIN_CODELEN', 8);
define('VFY_MAX_DAYS_CODE_VALID', 30);

// NOTE: for adding new also adjust UserRegistration::remove_verification()
define('VFY_TYPE_USER_REGISTRATION', 1);
define('VFY_TYPE_EMAIL_CHANGE', 2);

 /*!
  * \class Verification
  *
  * \brief Class to manage Verification-table
  */

global $ENTITY_VERIFICATION; //PHP5
$ENTITY_VERIFICATION = new Entity( 'Verification',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_INT,  'ID', 'uid', 'VType', 'Counter',
      FTYPE_TEXT, 'Email', 'Code', 'IP',
      FTYPE_DATE, 'Verified', 'Created'
   );

class Verification
{
   public $ID;
   public $uid;
   public $Verified;
   public $Created;
   public $VType;
   public $Email;
   public $Code;
   public $Counter;
   public $IP;

   /*! \brief Constructs Verification-object with specified arguments. */
   public function __construct( $id=0, $uid=0, $verified=0, $created=0, $vtype=0, $email='', $code='', $counter=0, $ip=null )
   {
      $this->ID = (int)$id;
      $this->uid = (int)$uid;
      $this->Verified = (int)$verified;
      $this->Created = (int)$created;
      $this->setVType( $vtype );
      $this->Email = $email;
      $this->Code = $code;
      $this->Counter = (int)$counter;
      $this->IP = ( is_null($ip) ) ? (string)@$_SERVER['REMOTE_ADDR'] : $ip;
   }//__construct

   private function setVType( $vtype )
   {
      if ( $vtype != VFY_TYPE_USER_REGISTRATION && $vtype != VFY_TYPE_EMAIL_CHANGE )
         error('invalid_args', "Verification.setVType($vtype)");
      else
         $this->VType = (int)$vtype;
   }

   public function to_string()
   {
      return print_r($this, true);
   }

   /*! \brief Inserts or updates Verification-entry in database. */
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
      $result = $entityData->insert( "Verification.insert(%s)" );
      if ( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   public function update()
   {
      $entityData = $this->fillEntityData();
      return $entityData->update( "Verification.update(%s)" );
   }

   public function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "Verification.delete(%s)" );
   }

   public function fillEntityData( $data=null )
   {
      if ( is_null($data) )
         $data = $GLOBALS['ENTITY_VERIFICATION']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'Verified', $this->Verified );
      $data->set_value( 'Created', $this->Created );
      $data->set_value( 'VType', $this->VType );
      $data->set_value( 'Email', $this->Email );
      $data->set_value( 'Code', $this->Code );
      $data->set_value( 'Counter', $this->Counter );
      $data->set_value( 'IP', $this->IP );
      return $data;
   }

   /*!
    * \brief Increase verify-counter by one in DB & this object, and invalidate if limit is reached.
    * \return true, if limit reached; false otherwise
    */
   public function increase_verification_counter( $max )
   {
      if ( ++$this->Counter >= $max )
      {
         $this->Verified = $GLOBALS['NOW']; // invalidate verification
         $this->update();
         return true;
      }

      // only increase counter
      db_query("Verification.increase_verification_counter.inc_counter({$this->ID},$max)",
         "UPDATE Verification SET Counter=Counter+1 WHERE ID={$this->ID} LIMIT 1" );
      return false;
   }//increase_verification_counter


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of Verification-objects for given arguments. */
   public static function build_query_sql( $id=0, $uid=0, $code=null, $verified=null )
   {
      $qsql = $GLOBALS['ENTITY_VERIFICATION']->newQuerySQL('V');
      if ( $id > 0 )
         $qsql->add_part( SQLP_WHERE, 'V.ID='. (int)$id );
      if ( $uid > 0 )
         $qsql->add_part( SQLP_WHERE, 'V.uid='. (int)$uid );
      if ( !is_null($code) )
         $qsql->add_part( SQLP_WHERE, sprintf("V.Code='%s'", mysql_addslashes($code)) );
      if ( !is_null($verified) )
         $qsql->add_part( SQLP_WHERE, 'V.Verified='. (int)$verified );
      return $qsql;
   }//build_query_sql

   /*! \brief Returns Verification-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $verification = new Verification(
            // from Verification
            @$row['ID'],
            @$row['uid'],
            @$row['X_Verified'],
            @$row['X_Created'],
            @$row['VType'],
            @$row['Email'],
            @$row['Code'],
            @$row['Counter'],
            @$row['IP']
         );
      return $verification;
   }//new_from_row

   /*!
    * \brief Loads and returns Verification-object for given uid and code.
    * \return NULL if nothing found; Verification-object otherwise
    */
   public static function load_verification( $id )
   {
      $qsql = self::build_query_sql( $id );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "Verification:load_verification.find($id)", $qsql->get_select() );
      return ($row) ? self::new_from_row($row) : NULL;
   }//load_verification

   /*!
    * \brief Loads and returns unverified Verification-objects for given uid.
    * \return NULL if nothing found; Verification-object otherwise
    */
   /*! \brief Returns enhanced (passed) ListIterator with Verification-objects. */
   public static function load_verifications( $iterator, $uid, $unverified=true )
   {
      $qsql = self::build_query_sql( 0, $uid, /*code*/null, ($unverified ? 0 : null) );

      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "Verification:load_verifications($uid,$unverified)", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $shape = self::new_from_row( $row );
         $iterator->addItem( $shape, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_verifications

   public static function build_code( $uid, $email, $len=8 )
   {
      if ( $len < VFY_MIN_CODELEN )
         error('invalid_args', "Verification:build_code.check.bad_length($uid,$len)");

      $code = sha1( sprintf('%s %s %s', $uid, $email, time()) );
      return substr( $code, 0, $len );
   }

   public static function get_type_text( $vtype )
   {
      static $ARR_TYPES = null; // vtype => text

      // lazy-init of texts
      if ( is_null($ARR_TYPES) )
      {
         $arr = array();
         $arr[VFY_TYPE_USER_REGISTRATION] = T_('User-Registration#VFY_type');
         $arr[VFY_TYPE_EMAIL_CHANGE] = T_('Email-Change#VFY_type');
         $ARR_TYPES = $arr;
      }

      if ( !isset($ARR_TYPES[$vtype]) )
         error('invalid_args', "Verification:get_type_text($vtype)");
      return $ARR_TYPES[$vtype];
   }//get_type_text

} // end of 'Verification'
?>
