<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
   var $values;
   /*! \brief false, if hidden-vars should not be exported (default: true). */
   var $use_hidden;

   /*!
    * \brief Constructs RequestParameters( [array( key => val)] )
    * \param $arr_src Copies values from optional passed source-arry.
    */
   function RequestParameters( $arr_src = null, $use_hidden=true )
   {
      $this->values = array();
      if( is_array($arr_src) )
      {
         foreach( $arr_src as $key => $val )
            $this->values[$key] = $val;
      }
      $this->use_hidden = $use_hidden;
   }

   /*! \brief Sets var to determine if hiddens should be exported or not (default: true). */
   function use_hidden( $use_hidden=true )
   {
      $this->use_hidden = (bool)$use_hidden;
   }

   /*!
    * \brief Adds entry.
    * signature: add_entry( string key, string|array value );
    */
   function add_entry( $key, $value )
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
   function get_hiddens( &$hiddens )
   {
      if( !$this->use_hidden)
         return ''; // don't export hiddens

      if( is_array($hiddens) )
         $hiddens = array_merge( $hiddens, $this->values);
      else
         $hiddens = $this->values;
      return build_hidden( $this->values);
   }

   /*!
    * \brief Returns an URL-encoded URL-parts-string for all the key-value-pairs of this object.
    * signature: interface string url_parts = get_url_parts();
    * note: used as interface for Form- or Table-class
    * note: also handle multi-values using 'varname[]'-notation
    */
   function get_url_parts( $end_sep=false)
   {
      return build_url( $this->values, $end_sep);
   }

   /*! \brief Return entries as key-value array. */
   function get_entries()
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
  * NOT supported: UNION-selects-syntax
  *
  * SQL-parts of type:
  *    SQLP_OPTS: [DISTINCT] [STRAIGHT_JOIN] [SQL_BIG_RESULT] [SQL_BUFFER_RESULT] [SQL_CACHE | SQL_NO_CACHE]
  *               [SQL_CALC_FOUND_ROWS]
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

// sql-statements for part-types
$ARR_SQL_STATEMENTS = array(
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

class QuerySQL
{
   /*! \brief array( type => array( part, ...), ...) */
   var $parts;

   /*!
    * \brief Constructs QuerySQL( part_type, part1, part2, ... part_type, ...) with var-args.
    * param $part_type: one of SQLP_...-consts
    */
   function QuerySQL()
   {
      global $ARR_SQL_STATEMENTS;
      $this->parts = array();
      foreach( array_keys($ARR_SQL_STATEMENTS) as $type )
         $this->parts[$type] = array();

      // skip arg #0=type-arg to add var-args: parts
      $type = '';
      for( $i=0; $i < func_num_args(); $i++)
      {
         $arg = trim(func_get_arg($i));
         if( isset($this->parts[$arg]) )
            $type = $arg;
         else
         {
            if( $type == '')
               error('assert', "QuerySQL.miss_type($arg)"); // missing part-type for part
            if( $arg != '' )
               $this->parts[$type][] = $arg;
         }
      }
   }

   /*!
    * \brief Adds SQL-part
    * signature: add_part( part_type, part1, part2, ...); var-args for one part-type
    * param $type sql-part-type to add to QuerySQL: one of SQLP_...-consts
    * param $parts allow variable number of parts (1..n); if part is an array its items are merged as if being parts
    *
    * note: empty parts are skipped
    * NOTE: Best practice is to use only ONE SQL-varname per SQLP_FIELDS-part
    *
    * Examples:
    *    add_part( SQLP_FIELDS,  'P.Name', 'P.Handle', 'P.ID' );
    *    add_part( SQLP_OPTS,    "DISTINCT" );
    *    add_part( SQLP_FROM,    "Games" );
    *    add_part( SQLP_FROM,    "INNER JOIN ..." );
    *    add_part( SQLP_WHERE,   "G.ID=G2.ID AND G.Moves>10" ); // joined with 'AND'
    *    add_part( SQLP_GROUP,   "Games.To_MoveID" );
    *    add_part( SQLP_HAVING,  'haverating and goodrating' );
    *    add_part( SQLP_ORDER,   'M.ID DESC' );
    *    add_part( SQLP_LIMIT,   '0,10' );
    *    add_part( SQLP_WHERETMPL, "(G.ID #OP #VAL OR G.ID2 #OP #VAL)" );
    *    add_part( SQLP_UNION_WHERE, 'Black_ID=4711', 'White_ID=4711' );
    */
   function add_part( $type ) // var-args for parts
   {
      // check
      if( !isset($this->parts[$type]) )
         error('assert', "QuerySQL.add_part.unknown_type($type)");
      if( ($type === SQLP_GROUP || $type === SQLP_LIMIT) && $this->has_part($type) )
      {
         // allow only once, except same part-value
         $arg = ( func_num_args() > 1 ) ? func_get_arg(1) : null;
         if( !is_null($arg) && !empty($arg) && $this->get_part($type) != $arg )
         {
            // type can only be set once (except the same value), part1=$arg
            error('assert', "QuerySQL.add_part.set_type_once($type,$arg)");
         }
         return; // ignore same-value
      }


      // skip arg #0=type-arg to add var-args: parts
      for( $i=1; $i < func_num_args(); $i++)
      {
         $part = trim(func_get_arg($i));
         if( (string)$part != '' )
            $this->parts[$type][] = $part;
      }
   }

   /*!
    * \brief Adds SQL-part, only for SQLP_FIELDS
    * signature: add_part_array( part_type, array( part1, part2, ...) );
    * param $part_arr parts (1..n)
    *
    * see also func add_part(type, ...)
    */
   function add_part_fields( $part_arr )
   {
      foreach( $part_arr as $part )
      {
         $part = trim($part);
         if( $part != '' )
            $this->parts[SQLP_FIELDS][] = $part;
      }
   }

   /*!
    * \brief Clears specified part-types (var-args)
    * param $part_type SQLP_...
    */
   function clear_parts() // var-args
   {
      $args = func_get_args();
      foreach( $args as $type )
      {
         if( !isset($this->parts[$type]) )
            error('assert', "QuerySQL.clear_parts.unknown_type($type)");
         if( isset($this->parts[$type]) )
            $this->parts[$type] = array();
      }
   }

   /*! \brief Returns true, if sql-part existing */
   function has_part( $type )
   {
      if( !isset($this->parts[$type]) )
         error('assert', "QuerySQL.has_part.unknown_type($type)");
      return ( count($this->parts[$type]) > 0 );
   }

   /*! \brief Returns true, if query has a UNION-part. */
   function has_union()
   {
      return $this->has_part(SQLP_UNION_WHERE);
   }

   /*! \brief Returns parts-array for specified part_type */
   function get_parts( $type )
   {
      if( !isset($this->parts[$type]) )
         error('assert', "QuerySQL.get_parts.unknown_type($type)");
      return $this->parts[$type];
   }

   /*!
    * \brief Returns typed part of select-statement; '' for none set.
    * param incl_prefix: if true, preprend sql-part with according SQL-keyword, e.g. 'SELECT' for SQLP_FIELDS
    * \param $union_part -1 (no union = default), 0..n = part of union;
    *        for some union-parts certain SQL-options are forbidden
    */
   function get_part( $type, $incl_prefix = false, $union_part=-1 )
   {
      if( !$this->has_part($type) )
         return '';

      // make unique: SQLP_FIELDS|OPTS|FROM|WHERE|HAVING|ORDER
      $arr = array_unique( $this->parts[$type] );
      if( $type === SQLP_FIELDS || $type === SQLP_ORDER || $type === SQLP_GROUP )
         $part = implode(', ', $arr);
      elseif( $type === SQLP_WHERE || $type === SQLP_HAVING || $type === SQLP_WHERETMPL )
         $part = implode(' AND ', $arr);
      elseif( $type === SQLP_FROM )
         $part = $this->merge_from_parts( $arr );
      elseif( $type === SQLP_LIMIT )
         $part = (count($arr) > 0) ? $arr[0] : '';
      elseif( $type === SQLP_FNAMES )
         $part = implode(',', $arr);
      elseif( $type === SQLP_OPTS )
      {
         $skiparr = array(
            'HIGH_PRIORITY'  => ($union_part >= 0), // forbidden with UNION
            SQLOPT_CALC_ROWS => ($union_part > 0), // only allowed in 1st SELECT of UNION-clause
         );
         $part = '';
         foreach( $arr as $val )
         {
            if( !@$skiparr[$val] )
               $part .= ' ' . $val;
         }
      }
      elseif( $type === SQLP_UNION_WHERE )
         $part = implode(' OR ', $arr);

      global $ARR_SQL_STATEMENTS;
      $prefix = ($incl_prefix) ? $ARR_SQL_STATEMENTS[$type] . ' ' : '';
      return $prefix . $part;
   }

   /*!
    * \brief Merges FROM-parts (could be Tables or JOIN-parts).
    * note: "{ OJ ..}" not supported (mysql-specialty)
    */
   function merge_from_parts( $arr )
   {
      if( count($arr) == 0 )
         return '';

      $result = array_shift($arr);
      foreach( $arr as $part )
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
         if( !preg_match( "/^(STRAIGHT_JOIN|((INNER|CROSS)\s+)?JOIN|(NATURAL\s+)?(LEFT|RIGHT)\s+(OUTER\s+)?JOIN)\s/i", $part ) )
            $result .= ' INNER JOIN';
         $result .= " $part";
      }

      return $result;
   }

   /*!
    * \brief Returns SQL-statement with current SQL-parts as one string.
    * output: "SELECT [options] fields FROM from [WHERE where] [GROUP BY group] [HAVING having] [ORDER BY order] [LIMIT limit]"
    * output: "(SELECT ...) UNION (SELECT ...) [ORDER BY order] [LIMIT limit]" if UNION_WHERE-part set
    */
   function get_select()
   {
      if( !$this->has_union() )
         return $this->get_select_normal();

      // handle UNION-syntax
      $arr_union = array();
      $union_parts = $this->get_parts(SQLP_UNION_WHERE);
      for( $idx=0; $idx < count($union_parts); $idx++)
      {
         $arr_union[]= $this->get_select_normal($idx);
      }

      $arrsql = array();
      $arrsql[]= '(' . implode(') UNION (', $arr_union) . ')';

      if( $this->has_part(SQLP_ORDER) )
         $arrsql[]= $this->get_part(SQLP_ORDER, true);
      if( $this->has_part(SQLP_LIMIT) )
         $arrsql[]= $this->get_part(SQLP_LIMIT, true);

      $sql = implode(' ', $arrsql);
      return $sql;
   }

   /*!
    * \brief Returns SQL-statement with current SQL-parts as one string.
    * \internal
    * \param $union_part -1 (no union = default), 0..n = union-part to add;
    *        then order + limit not added
    * output: "SELECT [options] fields FROM from [WHERE where] [GROUP BY group] [HAVING having] [ORDER BY order] [LIMIT limit]"
    */
   function get_select_normal( $union_part = -1 )
   {
      $arrsql = array();
      $has_opts = $this->has_part(SQLP_OPTS);
      if( $this->has_part(SQLP_FIELDS) || $has_opts )
         $arrsql[]= 'SELECT';
      if( $has_opts )
         $arrsql[]= $this->get_part(SQLP_OPTS, false, $union_part );
      $arrsql[]= $this->get_part(SQLP_FIELDS);
      $arrsql[]= $this->get_part(SQLP_FROM, true);

      // handle UNION-WHERE and WHERE
      if( $union_part < 0 )
      {
         if( $this->has_part(SQLP_WHERE) )
            $arrsql[]= $this->get_part(SQLP_WHERE, true);
      }
      else
      {
         $union_parts = $this->get_parts(SQLP_UNION_WHERE); // non-empty
         $arrsql[]= 'WHERE ' . $union_parts[$union_part];

         if( $this->has_part(SQLP_WHERE) )
            $arrsql[]= 'AND ' . $this->get_part(SQLP_WHERE);
      }

      if( $this->has_part(SQLP_GROUP) )
         $arrsql[]= $this->get_part(SQLP_GROUP, true);
      if( $this->has_part(SQLP_HAVING) )
         $arrsql[]= $this->get_part(SQLP_HAVING, true);

      // ORDER and LIMIT only for non-union-select
      if( $union_part < 0 )
      {
         if( $this->has_part(SQLP_ORDER) )
            $arrsql[]= $this->get_part(SQLP_ORDER, true);
         if( $this->has_part(SQLP_LIMIT) )
            $arrsql[]= $this->get_part(SQLP_LIMIT, true);
      }

      $sql = implode(' ', $arrsql);
      return $sql;
   }

   /*!
    * \brief Merges passed QuerySQL in current one, if merging possible.
    * Return false otherwise and merge hasn't been started.
    * signature: bool success = merge( QuerySQL );
    * param $qsql: may be empty or null (-> then skipped and true returned)
    * note: SQLP_GROUP|LIMIT are set, if current unset or the same part is used; otherwise error
    */
   function merge( $qsql )
   {
      // checks
      if( is_null($qsql) || empty($qsql) )
         return true;

      if( !is_a($qsql, 'QuerySQL') )
      {
         error('assert', "QuerySQL.merge.expect_obj.QuerySQL" );
         return false; // error may be func that go-on
      }

      // eventually add_part throws error
      $this->add_part( SQLP_GROUP, $qsql->get_part(SQLP_GROUP) );
      $this->add_part( SQLP_LIMIT, $qsql->get_part(SQLP_LIMIT) );

      foreach( array_keys( $this->parts ) as $type )
      {
         if( $qsql->has_part($type) )
            if( $type != SQLP_GROUP && $type != SQLP_LIMIT )
               $this->parts[$type] = array_merge( $this->parts[$type], $qsql->get_parts($type) );
      }

      return true;
   }

   /*!
    * \brief Merges passed QuerySQL with current one returning _NEW_ QuerySQL, if merging possible.
    * Return NULL otherwise.
    * signature: QuerySQL merge_or( QuerySQL );
    * note: merge WHERE and HAVING parts using OR-operation
    */
   function merge_or( $qsql )
   {
      // normal merge into new one
      $query = $this->duplicate();
      if( !$query->merge($qsql) )
         return NULL;

      // manual merge of WHERE and HAVING
      $query->clear_parts( SQLP_WHERE, SQLP_HAVING );
      foreach( array( SQLP_WHERE, SQLP_HAVING ) as $type )
      {
         $arr = array();
         if( $this->has_part($type) )
            $arr[]= $this->get_part($type);
         if( $qsql->has_part($type) )
            $arr[]= $qsql->get_part($type);
         if( count($arr) > 0 )
            $query->add_part( $type, '(' . implode(') OR (', $arr) . ')' );
      }

      return $query;
   }

   /*! \brief Returns copy of this object */
   function duplicate()
   {
      $q = new QuerySQL();
      $q->merge($this);
      return $q;
   }

   /*! \brief Returns String-representation of this object. */
   function to_string()
   {
      $arr = array();
      foreach( array_keys($this->parts) as $type )
      {
         if( $this->has_part($type) )
            $arr[]= "$type={[" . implode( '], [', $this->parts[$type] ) . "]}";
      }
      return "QuerySQL: " . implode(', ', $arr);
   }
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
   var $Name;

   /*! \brief main QuerySQL | null */
   var $QuerySQL;
   /*! \brief List of QuerySQL to be merged into main QuerySQL. */
   var $QuerySQLMerge;
   /*! \brief optional order string to be appended to query. */
   var $QueryOrder;
   /*! \brief optional limit string to be appended to query. */
   var $QueryLimit;

   /*! \brief (internal) QuerySQL built from merging QuerySQL with list of QuerySQLMerge. */
   var $MergedQuerySQL;
   /*! \brief query-string for db-query (for debugging). */
   var $Query;

   /*! \brief Number of rows resulting from db-query. */
   var $ResultRows;
   /*! \brief List of array with objects and original row read from db-query: array( array( Obj, row), ...). */
   var $Items;

   /*!
    * \brief Constructs ListIterator with name
    * \param $qsql will be added as merge-QuerySQL
    */
   function ListIterator( $name, $qsql=null, $order='', $limit='' )
   {
      $this->Name = $name;
      $this->QuerySQL = new QuerySQL();
      $this->QuerySQLMerge = array();
      $this->addQuerySQLMerge( $qsql );
      $this->QueryOrder = $order;
      $this->QueryLimit = $limit;

      $this->MergedQuerySQL = null;
      $this->Query = '';
      $this->ResultRows = -1; // not queried yet
      $this->clearItems();
   }

   /*! \brief Sets main QuerySQL. */
   function setQuerySQL( $qsql=null )
   {
      if( !is_null($qsql) && !is_a($qsql, 'QuerySQL') )
         error('invalid_args', 'ListIterator.setQuerySQL');
      $this->QuerySQL = $qsql;
   }

   /*! \brief Adds QuerySQL for merging. */
   function addQuerySQLMerge( $qsql=null )
   {
      if( !is_null($qsql) )
      {
         if( !is_a($qsql, 'QuerySQL') )
            error('invalid_args', 'ListIterator.addQuerySQLMerge');
         $this->QuerySQLMerge[] = $qsql;
      }
   }

   /*! \brief Sets ORDER-string appended to resulting query built from merging QuerySQLs. */
   function setQueryOrder( $order='' )
   {
      $this->QueryOrder = $order;
   }

   /*! \brief Sets LIMIT-string appended to resulting query built from merging QuerySQLs. */
   function setQueryLimit( $limit='' )
   {
      $this->QueryLimit = $limit;
   }

   /*!
    * \brief Sets SQL-query-string (built from main and merge-list QuerySQLs,
    *        order and limit query-parts). Should contain the final query used
    *        to query database.
    */
   function setQuery( $query_str )
   {
      $this->Query = $query_str;
   }

   /*! \brief Sets number of rows from resulting db-query. */
   function setResultRows( $result_rows )
   {
      $this->ResultRows = $result_rows;
   }

   /*!
    * \brief Builds SQL-query from main QuerySQL and merge-list of QuerySQLs,
    *        appending optional order and limit query parts.
    * \note Sets QuerySQL if unset.
    * \note Sets MergedQuerySQL and Query with finalized SQL-query-string.
    */
   function buildQuery()
   {
      if( is_null($this->QuerySQL) )
         $this->QuerySQL = new QuerySQL();

      // merge all QuerySQLs
      $merged_qsql = $this->QuerySQL;
      foreach( $this->QuerySQLMerge as $m_qsql )
         $merged_qsql->merge( $m_qsql );
      $this->MergedQuerySQL = $merged_qsql;

      $query = $merged_qsql->get_select() . $this->QueryOrder . $this->QueryLimit;
      $this->setQuery( $query );
      return $query;
   }

   /*! \brief Clears list of items in this list-iterator. */
   function clearItems()
   {
      $this->Items = array();
   }

   /*! \brief Adds item to item-list. */
   function addItem( $item, $row )
   {
      $this->Items[] = array( $item, $row );
   }

   /*! \brief Returns each() from items-list of this ListIterator. */
   function getListIterator()
   {
      return each( $this->Items );
   }

   /*! \brief Returns String-representation of this object. */
   function to_string()
   {
      $arr = array();
      if( !is_null($this->QuerySQL) )
         $arr[] = "QuerySQL={" . $this->QuerySQL->to_string() . '}';
      $idx = 1;
      foreach( $this->QuerySQLMerge as $qsql )
         $arr[] = sprintf( "QuerySQLMerge.%d=[%s]", $idx++, $qsql->to_string() );
      $arr[] = "QueryOrder=[{$this->QueryOrder}]";
      $arr[] = "QueryLimit=[{$this->QueryLimit}]";
      $arr[] = "Query=[{$this->Query}]";
      $arr[] = "ResultRows=[{$this->ResultRows}]";
      $arr[] = '#Items=[' . count($this->Items) . ']';
      $idx = 1;
      foreach( $this->Items as $arr_item )
      {
         list( $item, $row ) = $arr_item;
         $arr[] = sprintf( "Item.%d=[%s]", $idx++,
            ( method_exists($item, 'to_string') ? $item->to_string() : print_r($item,true) ));
      }
      return "ListIterator({$this->Name}): " . implode(', ', $arr);
   }

} // end of 'ListIterator'

?>
