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

require_once( 'include/connect2mysql.php' );
require_once( 'include/std_classes.php' );
require_once( 'include/error_functions.php' );

 /*!
  * \file db_classes.php
  *
  * \brief Functions for handling db-access

// template for new entity
$ENTITY_TABLENAME = new Entity( 'Table',
      FTYPE_PKEY,
      FTYPE_AUTO,
      FTYPE_INT,
      FTYPE_TEXT,
      FTYPE_DATE,
      FTYPE_ENUM,
   );

  */


define('FTYPE_INT',  1);
define('FTYPE_TEXT', 2);
define('FTYPE_DATE', 3);
define('FTYPE_ENUM', 4);
define('FTYPE_PKEY', 5); // primary-key of entity
define('FTYPE_AUTO', 6); // auto-increment field
define('_FTYPE_MAX', 6);

 /*!
  * \class Entity
  *
  * \brief Class to represent db-table-entity with reading and writing to db
  */
class Entity
{
   /*! \brief table of entity. */
   var $table;
   /*! \brief array with field-info: [ fieldname => type ] */
   var $fields;
   /*! \brief array with primary-keys: [ fieldname => 1 ] */
   var $pkeys;
   /*! \brief fieldname with auto-increment. */
   var $field_autoinc;

   /*! \brief array with date-fields: [ fieldname, ... ] */
   var $date_fields;

   /*!
    * \brief Constructs Entity( table_name, type, field, field, ..., type, ... ).
    * \param type: one of FTYPE_...-consts
    */
   function Entity( $table )
   {
      $this->table = $table;
      $this->fields = array();
      $this->pkeys = array();
      $this->date_fields = array();
      $this->field_autoinc = null;

      // skip arg #0=type-arg to add var-args: fields
      $type = 0;
      for( $i=1; $i < func_num_args(); $i++)
      {
         $arg = trim(func_get_arg($i));
         if( is_numeric($arg) )
         {
            if( $arg >= 1 && $arg <= _FTYPE_MAX )
               $type = $arg;
            else
               error('invalid_args', "Entity.bad_type($i,$arg)");
         }
         else
         {
            if( $type == 0)
               error('invalid_args', "Entity.miss_type($i,$type,$arg)");
            if( $arg == '' )
               error('invalid_args', "Entity.bad_arg($i,$type,$arg)");

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
   } //constructor Entity

   function to_string()
   {
      return print_r( $this, true );
   }

   function is_field( $field )
   {
      return isset($this->fields[$field]);
   }

   function is_primary_key( $field )
   {
      return isset($this->pkeys[$field]);
   }

   function is_auto_increment( $field )
   {
      if( is_null($this->field_autoinc) )
         return false;
      return (strcmp($this->field_autoinc, $field) == 0);
   }

   /*! \brief Returns db-fields to be used for query of entity. */
   function newQuerySQL( $table_alias='' )
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
   }

   function newEntityData()
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
   var $entity;
   /*! \brief Array with values: [ fieldname => value ] */
   var $values;

   function EntityData( $entity )
   {
      $this->entity = $entity;
      $this->values = array();
   }

   function to_string()
   {
      return print_r( $this, true );
   }

   function set_value( $field, $value )
   {
      if( !$this->entity->is_field($field) )
         error('assert', "EntityData.set_value.unknown_field({$this->entity->table},$field)");
      $this->values[$field] = $value;
   }

   function get_value( $field, $default=null )
   {
      return (isset($this->values[$field])) ? $this->values[$field] : $default;
   }

   function remove_value( $field )
   {
      unset($this->values[$field]);
   }

   function get_sql_value( $field, $default=null )
   {
      if( !isset($this->values[$field]) )
         return $default;

      $ftype = $this->entity->fields[$field];
      $value = $this->get_value( $field, $default );
      if( $ftype == FTYPE_INT )
         return (int)$value;
      elseif( $ftype == FTYPE_TEXT || $ftype == FTYPE_ENUM )
         return "'" . mysql_addslashes($value) . "'";
      elseif( $ftype == FTYPE_DATE )
         return "FROM_UNIXTIME($value)";
      else
         error('assert', "EntityData.get_sql_value.bad_field_type({$this->entity->table},$field)");
   }

   function build_sql_insert()
   {
      // primary-key field values must exist
      foreach( $this->entity->pkeys as $field => $tmp )
      {
         if( !isset($this->values[$field]) && !$this->entity->is_auto_increment($field) )
            error('assert', "EntityData.build_sql_insert.miss_pkey_value({$this->entity->table},$field)");
      }

      $arr = array();
      foreach( $this->values as $field => $value )
      {
         if( !$this->entity->is_field($field) )
            error('assert', "EntityData.build_sql_insert.unknown_field({$this->entity->table},$field)");
         $arr[] = $field . '=' . $this->get_sql_value( $field );
      }

      $query = 'INSERT INTO ' . $this->entity->table . ' SET ' . implode(', ', $arr);
      return $query;
   }

   function build_sql_insert_values( $header=false )
   {
      $arr = array();
      if( $header ) // header
      {
         $query_fmt = 'INSERT INTO ' . $this->entity->table . ' (%s) VALUES ';
         foreach( $this->entity->fields as $field => $ftype ) // in order from entity
         {
            if( !$this->entity->is_auto_increment($field) )
               $arr[] = $field;
         }
      }
      else // values
      {
         $query_fmt = '(%s)';
         foreach( $this->entity->fields as $field => $ftype ) // in order from entity
         {
            if( !$this->entity->is_auto_increment($field) )
            {
               $sql_val = $this->get_sql_value( $field );
               $arr[] = (is_null($sql_val)) ? "DEFAULT($field)" : $sql_val;
            }
         }
      }

      $query = sprintf( $query_fmt, implode(',', $arr) );
      return $query;
   }

   function build_sql_update()
   {
      // primary-key field values must exist
      $arr_pkeys = array();
      foreach( $this->entity->pkeys as $field => $tmp )
      {
         if( !isset($this->values[$field]) )
            error('assert', "EntityData.build_sql_update.miss_pkey_value({$this->entity->table},$field)");
         $sql_val = $this->get_sql_value( $field );
         if( !is_null($sql_val) )
            $arr_pkeys[] = $field . '=' . $sql_val;
      }

      $arr = array();
      foreach( $this->values as $field => $value )
      {
         if( !$this->entity->is_field($field) )
            error('assert', "EntityData.build_sql_update.unknown_field({$this->entity->table},$field)");
         if( !$this->entity->is_primary_key($field) )
            $arr[] = $field . '=' . $this->get_sql_value( $field );
      }

      $query = 'UPDATE ' . $this->entity->table . ' SET ' . implode(', ', $arr)
         . ' WHERE ' . implode(' AND ', $arr_pkeys);
      return $query;
   }

   function build_sql_delete()
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
      return $query;
   }

} // end of 'EntityData'

?>
