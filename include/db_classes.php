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

require_once 'include/connect2mysql.php';
require_once 'include/std_classes.php';
require_once 'include/error_functions.php';

 /*!
  * \file db_classes.php
  *
  * \brief Functions for handling db-access

// template for static function returning new entity
global $ENTITY_TABLE; //PHP5
$ENTITY_TABLE = new Entity( 'Table',
   FTYPE_PKEY,
   FTYPE_AUTO,
   FTYPE_INT,
   FTYPE_FLOAT,
   FTYPE_TEXT,
   FTYPE_DATE,
   FTYPE_ENUM,
   FTYPE_CHBY,
   FTYPE_OPTLOCK,
   );

  */


define('FTYPE_INT',   1); // for numeric-values (ints), which need no escaping
define('FTYPE_FLOAT', 2); // for floating-point-values (float, double, decimal), which need no escaping
define('FTYPE_TEXT',  3);
define('FTYPE_DATE',  4);
define('FTYPE_ENUM',  5);
define('FTYPE_PKEY',  6); // primary-key of entity
define('FTYPE_AUTO',  7); // auto-increment field
define('FTYPE_CHBY',  8); // ChangedBy-field
define('FTYPE_OPTLOCK', 9); // LockVersion-field for optimistic-locking
define('_FTYPE_MAX',  9);

define('FIELD_CHANGEDBY', 'ChangedBy');
define('FIELD_LOCKVERSION', 'LockVersion'); // for optimistic-locking, must be type: tinyint unsigned
define('FORMFIELD_LOCKVERSION', '_lock_version'); // for optimistic-locking

 /*!
  * \class Entity
  *
  * \brief Class to represent db-table-entity with reading and writing to db
  */
class Entity
{
   /*! \brief table of entity. */
   public $table;
   /*! \brief array with field-info: [ fieldname => type ] */
   public $fields = array();
   /*! \brief array with primary-keys: [ fieldname => 1 ] */
   public $pkeys = array();
   /*! \brief fieldname with auto-increment. */
   public $field_autoinc = null;
   /*! \brief true, if entity has a ChangedBy-field. */
   public $has_changedby = false;
   /*! \brief true, if entity has a LockVersion-field. */
   public $has_optimistic_locking = false;

   /*! \brief array with date-fields: [ fieldname, ... ] */
   public $date_fields = array();

   /*!
    * \brief Constructs Entity( table_name, type, field, field, ..., type, ... ).
    * \param $type one of FTYPE_...-consts (var-args)
    */
   public function __construct( $table )
   {
      $this->table = $table;

      // skip arg #0=type-arg to add var-args: fields
      $type = 0;
      $cnt_args = func_num_args();
      for( $i=1; $i < $cnt_args; $i++)
      {
         $arg = trim(func_get_arg($i));
         if( is_numeric($arg) )
         {
            if( $arg >= 1 && $arg <= _FTYPE_MAX )
               $type = $arg;
            else
               init_error('entity_init_error', "Entity.bad_type($table,$i,$arg)");

            if( $type == FTYPE_CHBY )
            {
               $this->fields[FIELD_CHANGEDBY] = $type;
               $this->has_changedby = true;
            }
            elseif( $type == FTYPE_OPTLOCK )
            {
               $this->fields[FIELD_LOCKVERSION] = $type;
               $this->has_optimistic_locking = true;
            }
         }
         else
         {
            if( $type == 0)
               init_error('entity_init_error', "Entity.miss_type($table,$i,$type,$arg)");
            if( $type == FTYPE_CHBY )
               init_error('entity_init_error', "Entity.bad_arg.changedby_no_arg($table,$i,$type,$arg)");
            if( $type == FTYPE_OPTLOCK )
               init_error('entity_init_error', "Entity.bad_arg.optlock_no_arg($table,$i,$type,$arg)");
            if( $arg == '' )
               init_error('entity_init_error', "Entity.bad_arg($table,$i,$type,$arg)");

            if( $type == FTYPE_PKEY )
               $this->pkeys[$arg] = 1;
            elseif( $type == FTYPE_AUTO )
               $this->field_autoinc = $arg;
            else
            {
               $this->fields[$arg] = $type;
               if( $type == FTYPE_DATE )
                  $this->date_fields[] = $arg;
            }
         }
      }

      // reserved-fieldname for optimistic-locking
      if( isset($this->fields[FIELD_LOCKVERSION]) && !$this->has_optimistic_locking )
         init_error('entity_init_error', "Entity.miss_optlock($table,".FIELD_LOCKVERSION.")");
   }//__construct

   public function to_string()
   {
      return print_r( $this, true );
   }

   public function is_field( $field )
   {
      return isset($this->fields[$field]);
   }

   public function is_primary_key( $field )
   {
      return isset($this->pkeys[$field]);
   }

   public function is_auto_increment( $field )
   {
      if( is_null($this->field_autoinc) )
         return false;
      return (strcmp($this->field_autoinc, $field) == 0);
   }

   /*! \brief Returns db-fields to be used for query of entity. */
   public function newQuerySQL( $table_alias='' )
   {
      $tbl = ($table_alias) ? $table_alias : $this->table;

      $qsql = new QuerySQL();
      $qsql->add_part(
         SQLP_FIELDS, "$tbl.*" );
      $qsql->add_part(
         SQLP_FROM, $this->table . ($table_alias ? " AS $table_alias" : '') );

      $arr_dates = array();
      foreach( $this->date_fields as $field )
         $arr_dates[] = "UNIX_TIMESTAMP($tbl.$field) AS X_$field";
      $qsql->add_part_fields( $arr_dates );

      return $qsql;
   }//newQuerySQL

   public function newEntityData()
   {
      return new EntityData( $this );
   }

} // end of 'Entity'



 /*!
  * \class EntityData
  *
  * \brief Class to represent table-row for specific Entity
  */
class EntityData
{
   /*! \brief Entity-object for this data-set.*/
   public $entity;
   /*! \brief Array with values: [ fieldname => value ] */
   public $values = array();
   /*! \brief Array with query-part-clauses: [ fieldname => field-based-query-part ] */
   public $query_values = array();

   public function __construct( $entity )
   {
      $this->entity = $entity;
   }

   public function to_string()
   {
      return print_r( $this, true );
   }

   public function get_pkey_string()
   {
      $arr = array();
      foreach( $this->entity->pkeys as $field => $tmp )
         $arr[] = sprintf( '%s[%s]', $field,
            ( isset($this->values[$field]) ? $this->values[$field] : '' ));
      return implode(',', $arr);
   }//get_pkey_string

   public function set_value( $field, $value )
   {
      if( !$this->entity->is_field($field) )
         error('assert', "EntityData.set_value.unknown_field({$this->entity->table},$field)");
      $this->values[$field] = $value;
   }

   public function set_query_value( $field, $query_value )
   {
      if( !$this->entity->is_field($field) )
         error('assert', "EntityData.set_query_value.unknown_field({$this->entity->table},$field)");
      $this->query_values[$field] = $query_value;
   }

   /*! \brief Returns array with valid field-names for current entity. */
   public function get_fields( $dbgmsg )
   {
      $arr = array();
      foreach( $this->values as $field => $tmp )
      {
         if( !$this->entity->is_field($field) )
            error('assert', "$dbgmsg.unknown_field({$this->entity->table},$field)");
         $arr[$field] = 1;
      }

      foreach( $this->query_values as $field => $tmp )
      {
         if( !$this->entity->is_field($field) )
            error('assert', "$dbgmsg.unknown_field2({$this->entity->table},$field)");
         $arr[$field] = 2;
      }

      if( isset($arr[FIELD_LOCKVERSION]) )
         unset($arr[FIELD_LOCKVERSION]);

      return array_keys( $arr );
   }//get_fields

   /*! \brief Returns cloned array with values (without query-values). */
   public function make_row( $clone=false )
   {
      return ($clone) ? array() + $this->values : $this->values;
   }

   public function get_value( $field, $default=null )
   {
      return (isset($this->values[$field])) ? $this->values[$field] : $default;
   }

   public function get_query_value( $field, $default=null )
   {
      return (isset($this->query_values[$field])) ? $this->query_values[$field] : $default;
   }

   public function remove_value( $field, $with_queryval=true )
   {
      unset($this->values[$field]);
      if( $with_queryval )
         unset($this->query_values[$field]);
   }

   public function get_sql_value( $field, $default=null )
   {
      if( $field == FIELD_CHANGEDBY )
         return "RTRIM('" . mysql_addslashes( $this->build_changed_by() ) . "')";

      if( isset($this->values[$field]) )
      {
         $ftype = $this->entity->fields[$field];
         $value = $this->get_value( $field, $default );
         if( $ftype == FTYPE_INT )
            return (int)$value;
         elseif( $ftype == FTYPE_TEXT || $ftype == FTYPE_ENUM )
            return "'" . mysql_addslashes($value) . "'";
         elseif( $ftype == FTYPE_DATE )
            return ( $value != 0 ) ? "FROM_UNIXTIME($value)" : "'0000-00-00 00:00:00'";
         elseif( $ftype == FTYPE_FLOAT )
            return (float)$value;
         else
            error('assert', "EntityData.get_sql_value.bad_field_type({$this->entity->table},$field)");
      }
      elseif( isset($this->query_values[$field]) )
         return $this->get_query_value( $field, $default );
      else
         return $default;
   }//get_sql_value

   public function build_changed_by( $value=null )
   {
      global $player_row;
      $handle = ( (string)@$player_row['Handle'] != '' ) ? @$player_row['Handle'] : UNKNOWN_VALUE;
      $changed_by = "[$handle]";

      if( is_null($value) )
         $value = $this->get_value(FIELD_CHANGEDBY, '');

      if( strncmp($value, $changed_by, strlen($changed_by)) != 0 )
         $value = "$changed_by $value";
      return $value;
   }//build_changed_by

   public function build_sql_insert()
   {
      // primary-key field values must exist
      foreach( $this->entity->pkeys as $field => $tmp )
      {
         if( !isset($this->values[$field]) && !$this->entity->is_auto_increment($field) )
            error('assert', "EntityData.build_sql_insert.miss_pkey_value({$this->entity->table},$field)");
      }

      $arr = array();
      foreach( $this->get_fields("EntityData.build_sql_insert") as $field )
      {
         if( !$this->entity->is_auto_increment($field) )
            $arr[] = $field . '=' . $this->get_sql_value( $field );
      }

      if( $this->entity->has_changedby && !isset($this->values[FIELD_CHANGEDBY]) )
         $arr[] = FIELD_CHANGEDBY . '=' . $this->get_sql_value(FIELD_CHANGEDBY);

      $query = 'INSERT INTO ' . $this->entity->table . ' SET ' . implode(', ', $arr);
      return $query;
   }//build_sql_insert

   public function build_sql_insert_values( $header=false, $with_PK=false, $skip_fields=null )
   {
      $arr = array();
      if( $header ) // header
      {
         $query_fmt = 'INSERT INTO ' . $this->entity->table . ' (%s) VALUES ';
         foreach( $this->entity->fields as $field => $ftype ) // in order from entity
         {
            if( !isset($skip_fields[$field]) && ( $with_PK || !$this->entity->is_auto_increment($field) ) )
               $arr[] = $field;
         }
      }
      else // values
      {
         $query_fmt = '(%s)';
         foreach( $this->entity->fields as $field => $ftype ) // in order from entity
         {
            if( !isset($skip_fields[$field]) && ( $with_PK || !$this->entity->is_auto_increment($field) ) )
            {
               $sql_val = $this->get_sql_value( $field );
               $arr[] = (is_null($sql_val)) ? "DEFAULT($field)" : $sql_val;
            }
         }
      }

      $query = sprintf( $query_fmt, implode(',', $arr) );
      return $query;
   }//build_sql_insert_values

   /*! \brief Returns update-query as query-string or array( update-part, where-part, after-where-part ). */
   public function build_sql_update( $limit=1, $as_arr=false, $incl_chby=true, $incl_optlock=true, $skip_fields=null )
   {
      // primary-key field values must exist
      $arr_pkeys = array();
      foreach( $this->entity->pkeys as $field => $tmp )
      {
         if( !isset($this->values[$field]) )
            error('assert', "EntityData.build_sql_update.miss_pkey_value({$this->entity->table},$field)");
         if( isset($skip_fields[$field]) )
            unset($skip_fields[$field]);
         $sql_val = $this->get_sql_value( $field );
         if( !is_null($sql_val) )
            $arr_pkeys[] = $field . '=' . $sql_val;
      }

      $arr = array();
      foreach( $this->get_fields("EntityData.build_sql_update") as $field )
      {
         if( !isset($skip_fields[$field]) && !$this->entity->is_primary_key($field) )
            $arr[] = $field . '=' . $this->get_sql_value( $field );
      }

      if( $incl_chby && $this->entity->has_changedby && !isset($this->values[FIELD_CHANGEDBY]) )
         $arr[] = FIELD_CHANGEDBY . '=' . $this->get_sql_value(FIELD_CHANGEDBY);

      if( $incl_optlock && $this->entity->has_optimistic_locking )
         $arr[] = sprintf( '%s=(%s+1) & 0xFF', FIELD_LOCKVERSION, FIELD_LOCKVERSION ); // 0xFF=tinyint-unsigned

      $arr_query = array();
      $arr_query[] = 'UPDATE ' . $this->entity->table . ' SET ' . implode(', ', $arr);
      $arr_query[] = 'WHERE ' . implode(' AND ', $arr_pkeys);
      if( $incl_optlock && $this->entity->has_optimistic_locking && isset($this->values[FIELD_LOCKVERSION]) )
         $arr_query[] = sprintf( 'AND %s=%s', FIELD_LOCKVERSION, (int)$this->values[FIELD_LOCKVERSION] );
      $arr_query[] = ( is_numeric($limit) && $limit > 0 ) ? "LIMIT $limit" : '';
      return ($as_arr) ? $arr_query : rtrim(implode(' ', $arr_query));
   }//build_sql_update

   public function build_sql_delete( $limit=1, $incl_optlock=true )
   {
      $arr = array();
      foreach( $this->entity->pkeys as $field => $tmp )
      {
         if( !isset($this->values[$field]) )
            error('assert', "EntityData.build_sql_delete.miss_pkey_value({$this->entity->table},$field)");
         $sql_val = $this->get_sql_value( $field );
         if( !is_null($sql_val) )
            $arr[] = $field . '=' . $sql_val;
      }

      $query = 'DELETE FROM ' . $this->entity->table . ' WHERE ' . implode(' AND ', $arr);
      if( $incl_optlock && $this->entity->has_optimistic_locking && isset($this->values[FIELD_LOCKVERSION]) )
         $query .= sprintf( ' AND %s=%s ', FIELD_LOCKVERSION, (int)$this->values[FIELD_LOCKVERSION] );
      if( is_numeric($limit) && $limit > 0 )
         $query .= " LIMIT $limit";
      return $query;
   }//build_sql_delete

   public function insert( $msgfmt )
   {
      return db_query( sprintf($msgfmt, $this->get_pkey_string()), $this->build_sql_insert() );
   }

   public function update( $msgfmt, $limit=1 )
   {
      return db_query( sprintf($msgfmt, $this->get_pkey_string()), $this->build_sql_update($limit) );
   }

   public function delete( $msgfmt, $limit=1 )
   {
      return db_query( sprintf($msgfmt, $this->get_pkey_string()), $this->build_sql_delete($limit) );
   }


   // ------------ static functions ----------------------------

   public static function build_update_part_changed_by( $handle )
   {
      return "ChangedBy=RTRIM(CONCAT('[" . mysql_addslashes($handle) . "] ',ChangedBy))";
   }

   public static function build_sql_value_changed_by( $handle )
   {
      return "'[" . mysql_addslashes($handle) . "]'";
   }

} // end of 'EntityData'

?>
