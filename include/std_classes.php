<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

// NOTE: don't use translation-texts in this file


// used as valid query with an empty result, since mysql4.1 use -> 'SELECT 1 FROM DUAL'
//define('EMPTY_SQL_QUERY', 'SELECT 1 FROM Forums WHERE 1=0');
define('EMPTY_SQL_QUERY', 'SELECT 1 FROM DUAL');



 /*!
  * \class RequestParameters
  *
  * \brief Class to store request-parameters, which can be attached to Table- or Form-class.
  * This class provides two interface-methods:
  *    \see get_hiddens()
  *    \see get_url_parts()
  *    \see use_hidden()
  *
  * see also SearchFilters->get_req_params(..)
  */
class RequestParameters
{
   /*! \brief array holding pairs of data: ( key => val ), val can be an array too representing multi-values. */
   private $values;
   /*! \brief false, if hidden-vars should not be exported (default: true). */
   private $use_hidden;

   /*!
    * \brief Constructs RequestParameters( [array( key => val)] )
    * \param $arr_src Copies values from optional passed source-arry.
    */
   public function __construct( $arr_src = null, $use_hidden=true )
   {
      $this->values = array();
      if ( is_array($arr_src) )
      {
         foreach ( $arr_src as $key => $val )
            $this->values[$key] = $val;
      }
      $this->use_hidden = $use_hidden;
   }

   /*! \brief Sets var to determine if hiddens should be exported or not (default: true). */
   public function use_hidden( $use_hidden=true )
   {
      $this->use_hidden = (bool)$use_hidden;
   }

   /*!
    * \brief Adds entry.
    * signature: add_entry( string key, string|array value );
    */
   public function add_entry( $key, $value )
   {
      $this->values[$key] = $value;
   }

   /*!
    * \brief Returns a string containing hidden-form-elements containing all the data
    *        and storing all key-value-pairs within $hiddens-referenced-array.
    * signature: interface string hidden_str = get_hiddens( arr-ref hiddens );
    * note: used as interface for Form- or Table-class
    * note: returning elements don't handle multi-values correctly (if val is array), though not needed IMHO
    */
   public function get_hiddens( &$hiddens )
   {
      if ( !$this->use_hidden)
         return ''; // don't export hiddens

      if ( is_array($hiddens) )
         $hiddens = array_merge( $hiddens, $this->values);
      else
         $hiddens = $this->values;
      return build_hidden( $this->values);
   }//get_hiddens

   /*!
    * \brief Returns an URL-encoded URL-parts-string for all the key-value-pairs of this object.
    * signature: interface string url_parts = get_url_parts();
    * note: used as interface for Form- or Table-class
    * note: also handle multi-values using 'varname[]'-notation
    */
   public function get_url_parts( $end_sep=false)
   {
      return build_url( $this->values, $end_sep);
   }

   /*! \brief Return entries as key-value array. */
   public function get_entries()
   {
      return $this->values;
   }

} // end of 'RequestParameters'



// signature: string fill_sql_template( string sql_template, string operator, string value )
// Example:
//   fill_sql_template( "(f #OP #VAL or f2 #OP #VAL)", ">=", "'val'")  gives  "(f >= 'val' or f2 >= 'val')
function fill_sql_template( $tmpl, $op, $val )
{
   return preg_replace( array( "/#OP/", "/#VAL/" ), array( $op, $val ), $tmpl );
}


 /*!
  * \class QuerySQL
  *
  * \brief Class to build SQL-statements (mainly to ease handling SQL with Filters).
  * for Resulting SQL-Statement \see get_select()
  *
  * supported: sub-clauses (though those are since mysql4.1).
  *
  * SQL-parts of type:
  *    SQLP_OPTS: [DISTINCT] [STRAIGHT_JOIN] [SQL_SMALL_RESULT | SQL_BIG_RESULT] [SQL_BUFFER_RESULT]
  *               [SQL_CACHE | SQL_NO_CACHE] [SQL_CALC_FOUND_ROWS]
  *    SQLP_GROUP, SQLP_LIMIT: can be only set once with same value
  *    SQLP_FIELDS, SQLP_FIELDS: mandatory
  *    SQLP_FNAMES, SQLP_WHERETMPL are not directly included for query, but fieldnames to be used for Filters
  *
  * Examples:
  *   \see code_examples/query_sql.php
  */

// parts of SQL-select-statement:
// NOTE: if adding new: handle ARR_SQL_STATEMENTS, add_part, get_part
define('SQLP_FIELDS',    'fields'); // mandatory
define('SQLP_OPTS',      'options');
define('SQLP_FROM',      'from'); // mandatory
define('SQLP_WHERE',     'where');
define('SQLP_GROUP',     'group'); // only once
define('SQLP_HAVING',    'having');
define('SQLP_ORDER',     'order');
define('SQLP_LIMIT',     'limit'); // only once
// special types for Filters (must be handled manually): \see include/filters.php
// note: be careful when merging 2 QuerySQLs
define('SQLP_FNAMES',    'fieldnames'); // field-names used to build "real" SQLP_WHERE
define('SQLP_WHERETMPL', 'where_tmpl'); // SQL-template used to build "real" SQLP_WHERE; only used to store templates along with other parts
define('SQLP_UNION_WHERE', 'union_where');

define('SQLOPT_CALC_ROWS', 'SQL_CALC_FOUND_ROWS'); // for SQLP_OPTS

class QuerySQL
{
   /*! \brief array( type => array( part, ...), ...) */
   private $parts;
   private $use_union_all;

   // sql-statements for part-types
   private static $ARR_SQL_STATEMENTS = array(
         SQLP_FIELDS    => 'SELECT',
         SQLP_OPTS      => '',
         SQLP_FROM      => 'FROM',
         SQLP_WHERE     => 'WHERE',
         SQLP_GROUP     => 'GROUP BY',
         SQLP_HAVING    => 'HAVING',
         SQLP_ORDER     => 'ORDER BY',
         SQLP_LIMIT     => 'LIMIT',
         SQLP_FNAMES    => NULL,
         SQLP_WHERETMPL => NULL,
         SQLP_UNION_WHERE => 'UNION',
      );

   /*!
    * \brief Constructs QuerySQL( part_type, part1, part2, ... part_type, ...) with var-args.
    * \param $part_type: one of SQLP_...-consts
    */
   public function __construct()
   {
      $this->parts = array();
      foreach ( array_keys(self::$ARR_SQL_STATEMENTS) as $type )
         $this->parts[$type] = array();
      $this->use_union_all = false;

      // skip arg #0=type-arg to add var-args: parts
      $type = '';
      $cnt_args = func_num_args();
      for ( $i=0; $i < $cnt_args; $i++)
      {
         $arg = trim(func_get_arg($i));
         if ( isset($this->parts[$arg]) )
            $type = $arg;
         else
         {
            if ( $type == '')
               error('assert', "QuerySQL.construct.miss_type($arg)"); // missing part-type for part
            if ( $arg != '' )
               $this->parts[$type][] = $arg;
         }
      }
   }//__construct

   public function useUnionAll( $use=true )
   {
      $this->use_union_all = $use;
   }

   /*!
    * \brief Adds SQL-part
    * signature: add_part( part_type, part1, part2, ...); var-args for one part-type
    * \param $type sql-part-type to add to QuerySQL: one of SQLP_...-consts
    * \param $parts allow variable number of parts (1..n); if part is an array its items are merged as if being parts
    *
    * \note empty parts are skipped
    * \note Best practice is to use only ONE SQL-varname per SQLP_FIELDS-part
    *
    * Examples:
    *    add_part( SQLP_FIELDS,  'P.Name', 'P.Handle', 'P.ID' );
    *    add_part( SQLP_OPTS,    "DISTINCT" );
    *    add_part( SQLP_FROM,    "Games" );
    *    add_part( SQLP_FROM,    "INNER JOIN ..." );
    *    add_part( SQLP_WHERE,   "G.ID=G2.ID AND G.Moves>10" ); // joined with 'AND'
    *    add_part( SQLP_GROUP,   "Games.To_MoveID" );
    *    add_part( SQLP_HAVING,  'haverating AND goodrating' );
    *    add_part( SQLP_ORDER,   'M.ID DESC' );
    *    add_part( SQLP_LIMIT,   '0,10' );
    *    add_part( SQLP_WHERETMPL, "(G.ID #OP #VAL OR G.ID2 #OP #VAL)" );
    *    add_part( SQLP_UNION_WHERE, 'Black_ID=4711', 'White_ID=4711' );
    */
   public function add_part( $type ) // var-args for parts
   {
      // check
      if ( !isset($this->parts[$type]) )
         error('assert', "QuerySQL.add_part.unknown_type($type)");
      if ( ($type === SQLP_GROUP || $type === SQLP_LIMIT) && $this->has_part($type) )
      {
         // allow only once, except same part-value
         $arg = ( func_num_args() > 1 ) ? func_get_arg(1) : null;
         if ( !is_null($arg) && !empty($arg) && $this->get_part($type) != $arg )
         {
            // type can only be set once (except the same value), part1=$arg
            error('assert', "QuerySQL.add_part.set_type_once($type,$arg)");
         }
         return; // ignore same-value
      }


      // skip arg #0=type-arg to add var-args: parts
      $cnt_args = func_num_args();
      for ( $i=1; $i < $cnt_args; $i++)
      {
         $part = trim(func_get_arg($i));
         if ( (string)$part != '' )
            $this->parts[$type][] = $part;
      }
   }//add_part

   /*!
    * \brief Adds SQL-part, only for SQLP_FIELDS
    * signature: add_part_array( part_type, array( part1, part2, ...) );
    * \param $part_arr parts (1..n)
    *
    * \see also func add_part(type, ...)
    */
   public function add_part_fields( $part_arr )
   {
      foreach ( $part_arr as $part )
      {
         $part = trim($part);
         if ( $part != '' )
            $this->parts[SQLP_FIELDS][] = $part;
      }
   }//add_part_fields

   /*!
    * \brief Clears specified part-types (var-args)
    * \param $part_type SQLP_...
    */
   public function clear_parts() // var-args
   {
      $args = func_get_args();
      foreach ( $args as $type )
      {
         if ( !isset($this->parts[$type]) )
            error('assert', "QuerySQL.clear_parts.unknown_type($type)");
         if ( isset($this->parts[$type]) )
            $this->parts[$type] = array();
      }
   }//clear_parts

   /*! \brief Returns true, if sql-part existing */
   public function has_part( $type )
   {
      if ( !isset($this->parts[$type]) )
         error('assert', "QuerySQL.has_part.unknown_type($type)");
      return ( count($this->parts[$type]) > 0 );
   }

   /*! \brief Returns true, if query has a UNION-part. */
   public function has_union()
   {
      return $this->has_part(SQLP_UNION_WHERE);
   }

   /*! \brief Returns parts-array for specified part_type */
   public function get_parts( $type )
   {
      if ( !isset($this->parts[$type]) )
         error('assert', "QuerySQL.get_parts.unknown_type($type)");
      return $this->parts[$type];
   }

   /*!
    * \brief Returns typed part of select-statement; '' for none set.
    * \param $incl_prefix: if true, preprend sql-part with according SQL-keyword, e.g. 'SELECT' for SQLP_FIELDS
    * \param $union_part -1 (no union = default), 0..n = part of union;
    *        for some union-parts certain SQL-options are forbidden
    */
   public function get_part( $type, $incl_prefix = false, $union_part=-1 )
   {
      if ( !$this->has_part($type) )
         return '';

      // make unique: SQLP_FIELDS|OPTS|FROM|WHERE|HAVING|ORDER
      $arr = array_unique( $this->parts[$type] );
      if ( $type === SQLP_FIELDS || $type === SQLP_ORDER || $type === SQLP_GROUP )
         $part = implode(', ', $arr);
      elseif ( $type === SQLP_WHERE || $type === SQLP_HAVING || $type === SQLP_WHERETMPL )
         $part = implode(' AND ', $arr);
      elseif ( $type === SQLP_FROM )
         $part = $this->merge_from_parts( $arr );
      elseif ( $type === SQLP_LIMIT )
         $part = (count($arr) > 0) ? $arr[0] : '';
      elseif ( $type === SQLP_FNAMES )
         $part = implode(',', $arr);
      elseif ( $type === SQLP_OPTS )
      {
         $skiparr = array(
            'HIGH_PRIORITY'  => ($union_part >= 0), // forbidden with UNION
            SQLOPT_CALC_ROWS => ($union_part > 0), // only allowed in 1st SELECT of UNION-clause
         );
         $part = '';
         foreach ( $arr as $val )
         {
            if ( !@$skiparr[$val] )
               $part .= ' ' . $val;
         }
      }
      elseif ( $type === SQLP_UNION_WHERE )
         $part = implode(' OR ', $arr);

      $prefix = ($incl_prefix) ? self::$ARR_SQL_STATEMENTS[$type] . ' ' : '';
      return $prefix . $part;
   }//get_part

   /*!
    * \brief Merges FROM-parts (could be Tables or JOIN-parts).
    * \note "{ OJ ..}" not supported (mysql-specialty)
    */
   public function merge_from_parts( $arr )
   {
      if ( count($arr) == 0 )
         return '';

      $result = array_shift($arr);
      foreach ( $arr as $part )
      {
         // valid JOIN-Syntax (mysql 4.0):
         //    STRAIGHT_JOIN, [INNER | CROSS] JOIN, [NATURAL] LEFT|RIGHT [OUTER] JOIN
         // NOTE about mysql5.0-compatibility for JOINs:
         // - parentheses can be omitted if all other tables stands before a JOIN
         //   (whatever join you use): "FROM (...) JOIN",
         //   see also http://dev.mysql.com/doc/refman/5.0/en/join.html
         // - fix: -> replace ','-join with 'INNER JOIN' (could also use CROSS JOIN),
         //   because join has higher precedence than ','-join
         //   see referenced URL above(!)
         if ( !preg_match( "/^(STRAIGHT_JOIN|((INNER|CROSS)\s+)?JOIN|(NATURAL\s+)?(LEFT|RIGHT)\s+(OUTER\s+)?JOIN)\s/i", $part ) )
            $result .= ' INNER JOIN';
         $result .= " $part";
      }

      return $result;
   }//merge_from_parts

   /*!
    * \brief Returns SQL-statement with current SQL-parts as one string.
    * \return query-string:
    *    output: "SELECT [options] fields FROM from [WHERE where] [GROUP BY group] [HAVING having] [ORDER BY order] [LIMIT limit]"
    *    output: "(SELECT ...) UNION (SELECT ...) [ORDER BY order] [LIMIT limit]" if UNION_WHERE-part set
    */
   public function get_select()
   {
      if ( !$this->has_union() )
         return $this->get_select_normal();

      // handle UNION-syntax
      $arr_union = array();
      $union_parts = $this->get_parts(SQLP_UNION_WHERE);
      $cnt_uparts = count($union_parts);
      for ( $idx=0; $idx < $cnt_uparts; $idx++)
      {
         $arr_union[]= $this->get_select_normal($idx);
      }

      $arrsql = array();
      $union_cmd = ($this->use_union_all) ? 'UNION ALL' : 'UNION DISTINCT';
      $arrsql[]= '(' . implode(") $union_cmd (", $arr_union) . ')';

      if ( $this->has_part(SQLP_ORDER) )
         $arrsql[]= $this->get_part(SQLP_ORDER, true);
      if ( $this->has_part(SQLP_LIMIT) )
         $arrsql[]= $this->get_part(SQLP_LIMIT, true);

      $sql = implode(' ', $arrsql);
      return $sql;
   }//get_select

   /*!
    * \brief Returns SQL-statement with current SQL-parts as one string.
    * \internal
    * \param $union_part -1 (no union = default), 0..n = union-part to add;
    *        then order + limit not added
    * \return output: "SELECT [options] fields FROM from [WHERE where] [GROUP BY group] [HAVING having] [ORDER BY order] [LIMIT limit]"
    */
   private function get_select_normal( $union_part = -1 )
   {
      $arrsql = array();
      $has_opts = $this->has_part(SQLP_OPTS);
      if ( $this->has_part(SQLP_FIELDS) || $has_opts )
         $arrsql[]= 'SELECT';
      if ( $has_opts )
         $arrsql[]= $this->get_part(SQLP_OPTS, false, $union_part );
      $arrsql[]= $this->get_part(SQLP_FIELDS);
      $arrsql[]= $this->get_part(SQLP_FROM, true);

      // handle UNION-WHERE and WHERE
      if ( $union_part < 0 )
      {
         if ( $this->has_part(SQLP_WHERE) )
            $arrsql[]= $this->get_part(SQLP_WHERE, true);
      }
      else
      {
         $union_parts = $this->get_parts(SQLP_UNION_WHERE); // non-empty
         $arrsql[]= 'WHERE ' . $union_parts[$union_part];

         if ( $this->has_part(SQLP_WHERE) )
            $arrsql[]= 'AND ' . $this->get_part(SQLP_WHERE);
      }

      if ( $this->has_part(SQLP_GROUP) )
         $arrsql[]= $this->get_part(SQLP_GROUP, true);
      if ( $this->has_part(SQLP_HAVING) )
         $arrsql[]= $this->get_part(SQLP_HAVING, true);

      // ORDER and LIMIT only for non-union-select
      if ( $union_part < 0 )
      {
         if ( $this->has_part(SQLP_ORDER) )
            $arrsql[]= $this->get_part(SQLP_ORDER, true);
         if ( $this->has_part(SQLP_LIMIT) )
            $arrsql[]= $this->get_part(SQLP_LIMIT, true);
      }

      $sql = implode(' ', $arrsql);
      return $sql;
   }//get_select_normal

   /*!
    * \brief Merges passed QuerySQL in current one.
    * \param $qsql may be empty or null (-> then skipped and true returned)
    * \return true if merging is possible; false otherwise and merge hasn't been started.
    *
    * \note SQLP_GROUP|LIMIT are set, if current unset or the same part is used; otherwise error
    *
    * Example: bool success = merge( QuerySQL );
    */
   public function merge( $qsql )
   {
      // checks
      if ( is_null($qsql) || empty($qsql) )
         return true;

      if ( !($qsql instanceof QuerySQL) )
      {
         error('assert', "QuerySQL.merge.expect_obj.QuerySQL" );
         return false; // error may be func that go-on
      }

      // eventually add_part throws error
      $this->add_part( SQLP_GROUP, $qsql->get_part(SQLP_GROUP) );
      $this->add_part( SQLP_LIMIT, $qsql->get_part(SQLP_LIMIT) );

      foreach ( array_keys( $this->parts ) as $type )
      {
         if ( $qsql->has_part($type) )
            if ( $type != SQLP_GROUP && $type != SQLP_LIMIT )
               $this->parts[$type] = array_merge( $this->parts[$type], $qsql->get_parts($type) );
      }

      return true;
   }//merge

   /*!
    * \brief Merges passed QuerySQL with current one returning _NEW_ QuerySQL, if merging possible.
    * signature: QuerySQL merge_or( QuerySQL );
    * \return NULL otherwise.
    * \note merge WHERE and HAVING parts using OR-operation
    */
   public function merge_or( $qsql )
   {
      // normal merge into new one
      $query = $this->duplicate();
      if ( !$query->merge($qsql) )
         return NULL;

      // manual merge of WHERE and HAVING
      $query->clear_parts( SQLP_WHERE, SQLP_HAVING );
      foreach ( array( SQLP_WHERE, SQLP_HAVING ) as $type )
      {
         $arr = array();
         if ( $this->has_part($type) )
            $arr[]= $this->get_part($type);
         if ( $qsql->has_part($type) )
            $arr[]= $qsql->get_part($type);
         if ( count($arr) > 0 )
            $query->add_part( $type, '(' . implode(') OR (', $arr) . ')' );
      }

      return $query;
   }//merge_or

   /*! \brief Returns copy of this object */
   public function duplicate()
   {
      $q = new QuerySQL();
      $q->merge($this);
      return $q;
   }

   /*! \brief Returns String-representation of this object. */
   public function to_string()
   {
      $arr = array();
      foreach ( array_keys($this->parts) as $type )
      {
         if ( $this->has_part($type) )
            $arr[]= "$type={[" . implode( '], [', $this->parts[$type] ) . "]}";
      }
      return "QuerySQL: " . implode(', ', $arr);
   }//to_string

} // end of 'QuerySQL'



 /*!
  * \class ListIterator
  *
  * \brief Class to help with iterating over lists, especially loaded from database.
  *
  * \note Also help to keep track and build query, supportive for debugging query
  */
class ListIterator
{
   /*! \brief Name of list-iterator (type of items). */
   private $Name;

   /*! \brief main QuerySQL | null */
   private $QuerySQL;
   /*! \brief List of QuerySQL to be merged into main QuerySQL. */
   private $QuerySQLMerge = array();
   /*! \brief optional order string to be appended to query. */
   private $QueryOrder;
   /*! \brief optional limit string to be appended to query. */
   private $QueryLimit;
   /*! \brief field-name for comparison-function _compare_items_...() for sortListIterator(). */
   private $SortField = null;

   /*! \brief (internal) QuerySQL built from merging QuerySQL with list of QuerySQLMerge. */
   private $MergedQuerySQL = null;
   /*! \brief query-string for db-query (for debugging). */
   private $Query = '';

   /*! \brief Number of rows resulting from db-query. */
   private $ResultRows = -1;
   /*! \brief List of array with objects and original row read from db-query: array( array( Obj, row), ...). */
   private $Items;
   /*! \brief optional index mapping index-fields from query-result to items: array( field => [ val => (Obj,row) ], ...). */
   private $Index = array();


   /*!
    * \brief Constructs ListIterator with name
    * \param $qsql will be added as merge-QuerySQL
    */
   public function __construct( $name, $qsql=null, $order='', $limit='' )
   {
      $this->Name = $name;
      $this->QuerySQL = new QuerySQL();
      $this->addQuerySQLMerge( $qsql );
      $this->QueryOrder = $order;
      $this->QueryLimit = $limit;

      $this->clearItems();
   }

   /*! \brief Clears list of items in this list-iterator. */
   public function clearItems()
   {
      $this->Items = array();
   }

   /*! \brief Returns number of stored items. */
   public function getItemCount()
   {
      return count( $this->Items );
   }

   /*! \brief Returns each() from items-list of this ListIterator. */
   public function getListIterator()
   {
      return each( $this->Items );
   }

   /*! \brief Resets iterating with getListIterator-func. */
   public function resetListIterator()
   {
      reset( $this->Items );
   }

   public function getItem( $row_idx )
   {
      if ( !is_numeric($row_idx) || !isset($this->Items[$row_idx]) )
         error('invalid_args', "ListIterator.getItem.check_idx($row_idx)");
      return $this->Items[$row_idx];
   }

   public function setItemRawValue( $row_idx, $key, $value )
   {
      if ( !is_numeric($row_idx) || $row_idx < 0 || $row_idx >= $this->getItemCount() )
         error('invalid_args', "ListIterator.setItemRawValue.check_idx($row_idx)");
      $this->Items[$row_idx][1][$key] = $value;
   }

   /*! \brief Sorts internal items-list by given compare-function $cmp_func. */
   public function sortListIteratorCustom( $cmp_func )
   {
      return usort( $this->Items, $cmp_func );
   }

   /*!
    * \brief Sorts internal items-list by given $field (from row-data).
    * \param $sort_flags SORT_NUMERIC, SORT_STRING (case-sensitive), SORT_STRING|SORT_FLAG_CASE (case-insensitive)
    */
   public function sortListIterator( $field, $sort_flags=SORT_REGULAR )
   {
      if ( count($this->Items) == 0 )
         return true;

      if ( !isset($this->Items[0][1][$field]) ) // 1st item, row[field]
         error('invalid_args', "ListIterator.sortListIterator.unknown_field($field,$sort_flags)");
      $this->SortField = $field;

      $cmp_func = array( $this );
      if ( $sort_flags == SORT_NUMERIC )
         $cmp_func[] = '_compare_items_numeric';
      elseif ( $sort_flags & SORT_STRING )
         $cmp_func[] = '_compare_items_string';
      elseif ( ($sort_flags & (SORT_STRING|SORT_FLAG_CASE)) == (SORT_STRING|SORT_FLAG_CASE) )
         $cmp_func[] = '_compare_items_string_nocase';
      else
         error('invalid_args', "ListIterator.sortListIterator.bad_sort_flags($field,$sort_flags)");

      return usort( $this->Items, $cmp_func );
   }//sortListIterator

   // \internal, see sortListIterator()
   private function _compare_items_numeric( $item1, $item2 )
   {
      $a = $item1[1][$this->SortField]; // row[field]
      $b = $item2[1][$this->SortField];

      // could use cmp_int(), but inline is faster
      if ($a == $b)
         return 0;
      else
         return ($a < $b) ? -1 : 1;
   }

   // \internal, see sortListIterator()
   private function _compare_items_string( $item1, $item2 )
   {
      $a = $item1[1][$this->SortField]; // row[field]
      $b = $item2[1][$this->SortField];
      return strcmp($a, $b);
   }

   // \internal, see sortListIterator()
   private function _compare_items_string_nocase( $item1, $item2 )
   {
      $a = $item1[1][$this->SortField]; // row[field]
      $b = $item2[1][$this->SortField];
      return strcasecmp($a, $b);
   }

   /*! \brief Sets main QuerySQL. */
   public function setQuerySQL( $qsql=null )
   {
      if ( !is_null($qsql) && !($qsql instanceof QuerySQL) )
         error('invalid_args', 'ListIterator.setQuerySQL');
      $this->QuerySQL = $qsql;
   }

   /*! \brief Adds QuerySQL for merging. */
   public function addQuerySQLMerge( $qsql=null )
   {
      if ( !is_null($qsql) )
      {
         if ( !($qsql instanceof QuerySQL) )
            error('invalid_args', 'ListIterator.addQuerySQLMerge');
         $this->QuerySQLMerge[] = $qsql;
      }
   }//addQuerySQLMerge

   /*! \brief Sets ORDER-string appended to resulting query built from merging QuerySQLs. */
   public function setQueryOrder( $order='' )
   {
      $this->QueryOrder = $order;
   }

   /*! \brief Sets LIMIT-string appended to resulting query built from merging QuerySQLs. */
   public function setQueryLimit( $limit='' )
   {
      $this->QueryLimit = $limit;
   }

   /*!
    * \brief Sets SQL-query-string (built from main and merge-list QuerySQLs,
    *        order and limit query-parts). Should contain the final query used
    *        to query database.
    */
   public function setQuery( $query_str )
   {
      $this->Query = $query_str;
   }

   public function getResultRows()
   {
      return $this->ResultRows;
   }

   /*! \brief Sets number of rows from resulting db-query. */
   public function setResultRows( $result_rows )
   {
      $this->ResultRows = $result_rows;
   }

   /*! \brief Clears list of indexes in this list-iterator. */
   public function clearIndex()
   {
      $this->Index = array();
   }

   /*! \brief Adds index-field(s) to be generated on iterator. */
   public function addIndex( /*var-args*/ )
   {
      $cnt_args = func_num_args();
      for ( $i=0; $i < $cnt_args; $i++)
      {
         $field = func_get_arg($i);
         $this->Index[$field] = array();
      }
   }//addIndex

   /*! \brief Returns true if field has filled index. */
   public function hasIndex( $field )
   {
      return ( count(@$this->Index[$field]) > 0 );
   }

   /*! \brief Returns index-map for indexed field; die with error on unknown field. */
   public function getIndexMap( $field )
   {
      if ( !isset($this->Index[$field]) )
         error('invalid_args', "ListIterator.getIndexMap({$this->Name},$field)");

      return $this->Index[$field];
   }

   /*!
    * \brief Returns index-map-value for indexed field and key; null if undefined.
    * \param $ret_val -1 = return [item,row]; 0=return item, 1=return row
    */
   public function getIndexValue( $field, $key, $ret_val=-1 )
   {
      if ( !isset($this->Index[$field][$key]) )
         return null;
      $arr_item = $this->Index[$field][$key];
      if ( $ret_val < 0 )
         return $arr_item;
      elseif ( $ret_val == 0 )
         return $arr_item[0];
      else
         return $arr_item[1];
   }//getIndexValue

   /*! \brief Returns String-representation of this object. */
   public function to_string()
   {
      $arr = array();
      if ( !is_null($this->QuerySQL) )
         $arr[] = "QuerySQL={" . $this->QuerySQL->to_string() . '}';
      $idx = 1;
      foreach ( $this->QuerySQLMerge as $qsql )
         $arr[] = sprintf( "QuerySQLMerge.%d=[%s]", $idx++, $qsql->to_string() );
      $arr[] = "QueryOrder=[{$this->QueryOrder}]";
      $arr[] = "QueryLimit=[{$this->QueryLimit}]";
      $arr[] = "Query=[{$this->Query}]";
      $arr[] = "ResultRows=[{$this->ResultRows}]";
      $arr[] = '#Items=[' . count($this->Items) . ']';
      $idx = 1;
      foreach ( $this->Items as $arr_item )
      {
         list( $item, $row ) = $arr_item;
         $arr[] = sprintf( "Item.%d=[%s]", $idx++,
            ( method_exists($item, 'to_string') ? $item->to_string() : print_r($item,true) ));
      }
      $this->resetListIterator();
      $arr[] = 'Index=[' . print_r( $this->Index, true ) . ']';
      return "ListIterator({$this->Name}): " . implode(', ', $arr);
   }//to_string


   /*!
    * \brief Builds SQL-query from main QuerySQL and merge-list of QuerySQLs,
    *        appending optional order and limit query parts.
    * \note Sets QuerySQL if unset.
    * \note Sets MergedQuerySQL and Query with finalized SQL-query-string.
    */
   public function buildQuery()
   {
      if ( is_null($this->QuerySQL) )
         $this->QuerySQL = new QuerySQL();

      // merge all QuerySQLs
      $merged_qsql = $this->QuerySQL;
      foreach ( $this->QuerySQLMerge as $m_qsql )
         $merged_qsql->merge( $m_qsql );
      $this->MergedQuerySQL = $merged_qsql;

      $query = $merged_qsql->get_select() . ' ' . $this->QueryOrder . ' ' . $this->QueryLimit;
      $this->setQuery( $query );
      return $query;
   }//buildQuery

   /*! \brief Adds item to item-list. */
   public function addItem( $item, $row )
   {
      $arr_item = array( $item, $row );
      $this->Items[] = $arr_item;

      foreach ( $this->Index as $field => $map )
         $this->Index[$field][$row[$field]] = $arr_item;
   }//addItem

   /*! \brief Return array with list of objects (without extra-row). */
   public function getItems( $with_extra=false )
   {
      if ( $with_extra )
         return $this->Items;

      $out = array();
      foreach ( $this->Items as $item )
         $out[] = $item[0];
      return $out;
   }//getItems

   /*! \brief Return array with list of raw rows only. */
   public function getItemRows()
   {
      $out = array();
      foreach ( $this->Items as $arr_item )
         $out[] = $arr_item[1];
      $this->resetListIterator();
      return $out;
   }

   /*! \brief Rescans items and re-filling index. */
   public function rescanIndex()
   {
      foreach ( array_keys($this->Index) as $field )
         $this->Index[$field] = array();

      foreach ( $this->Items as $arr_item )
      {
         $row = $arr_item[1];
         foreach ( $this->Index as $field => $map )
            $this->Index[$field][$row[$field]] = $arr_item;
      }
      $this->resetListIterator();
   }//rescanIndex

} // end of 'ListIterator'



 /*!
  * \class ThreadList
  *
  * \brief Class to help with representing threads
  */
class ThreadList
{
   /*! \brief Thread item of certain type equal for whole thread-list. */
   private $item;
   /*! \brief Level within thread, starting at 0. */
   private $level;
   /*! \brief Parent ThreadList, null if root-item. */
   private $parent;
   /*! \brief List of ThreadList-objects being the children of current thread. */
   private $children = array();

   public function __construct( $item, $level=0, $parent=null )
   {
      $this->item = $item;
      $this->level = $level;
      $this->parent = $parent;
   }

   /*! \brief Returns current item. */
   public function getItem()
   {
      return $this->item;
   }

   /*! \brief Returns level of current item. */
   public function getLevel()
   {
      return $this->level;
   }

   /*! \brief Sets level for current item. */
   public function setLevel( $level )
   {
      $this->level = $level;
   }

   /*! \brief Returns true, if current item has a parent item. */
   public function hasParent()
   {
      return !is_null($this->parent);
   }

   /*! \brief Returns true, if current item has children items. */
   public function hasChildren()
   {
      return (count($this->children) > 0);
   }

   /*! \brief Returns children items. */
   public function getChildren()
   {
      return $this->children;
   }

   /*! \brief Adds child to current item. */
   public function addChild( $item )
   {
      $thread = new ThreadList( $item, $this->level + 1, $this );
      $this->children[] = $thread;
      return $thread;
   }

   /*!
    * \brief Deep-traverse current ThreadList applying given function on each
    *        ThreadList-part and given $result reference.
    * \param $function Function with signature: function_name( ThreadList, &$result )
    * \param $result passed in as reference to function when traversing ThreadList;
    *                can be used as storage
    * \note Function is applied on each item first before applying it on its children.
    */
   public function traverse( $function, &$result )
   {
      $function( $this, $result );
      foreach ( $this->children as $child_item )
      {
         $child_item->traverse( $function, $result );
      }
   }//traverse

} // end of 'ThreadList'

?>
