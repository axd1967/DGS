<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

$TranslateGroups[] = "Common";

 /* Author: Jens-Uwe Gaspar */

// used as valid query with an empty result, since mysql4.1 use -> 'SELECT 1 FROM DUAL'
define('EMPTY_SQL_QUERY', 'SELECT 1 FROM Forums WHERE 1=0');



 /*!
  * \class WhereClause
  *
  * \brief Class to build where-clauses by AND'ing, OR'ing and merging WhereClauses
  * \see QuerySQL
  *
  * Example:
  *    $wc = new WhereClause();
  *    $wc->add('a=1');
  *    $wc->add('b=2');
  *    $wc->embrace();    // $wc->get_where_clause(false) = '(a=1 AND b=2)'
  *    $wc->add('c=3', 'or');
  *    $wc2 = new WhereClause('OR');
  *    $wc2->add('d>7');
  *    $wc2->add('e<5');  // $wc2->get_where_clause(false) = '(a=1 AND b=2)'
  *    $wc->add( $wc2 );
  *    $wc->set_operator( 'or' );
  *    $wc->add('f=0');
  *    $wc->get_where_clause() = 'WHERE ((a=1 AND b=2) OR c=3) AND (d>7 OR e<5) OR f=0'
  */
class WhereClause
{
   /*! \brief array of parts ( clauses and operators ). */
   var $parts;
   /*! \brief currently used operator, e.g. OR or AND */
   var $operator;

   /*! \brief Constructs WhereClause( [string operator] ). */
   function WhereClause( $_operator = 'AND' )
   {
      $this->parts = array();
      $this->set_operator( $_operator );
   }

   /*! \brief Returns true, if WhereClause contains at least one part. */
   function has_clause()
   {
      return ( count($this->parts) > 0 );
   }

   /*! \brief Set current operator-string (not checked if valid); it's used to concat clauses. */
   function set_operator( $_operator )
   {
      $this->operator = $this->make_operator($_operator);
   }

   /*! \internal */
   function make_operator( $operator )
   {
      return ' ' . strtoupper($operator) . ' ';
   }

   /*!
    * \brief adding non-empty clauses with specified operator or with current if none given.
    * signature: add( WhereClause|string, [string operator] );
    * param $operator optional, not checked
    * note: embrace current clauses with '(..)' if merging with WhereClause
    */
   function add( $clause, $operator = null)
   {
      if ( is_a( $clause, "WhereClause" ))
      { // append other WhereClause
         $merge_clause = $clause->get_where_clause( false );
         if ( $merge_clause )
         {
            $this->embrace();
            $this->add( "({$merge_clause})", $operator );
         }
      }
      elseif ( $clause )
      { // append clause-part
         $op = ( is_null($operator) ) ? $this->operator : $this->make_operator( $operator );
         if ( $this->has_clause() )
            array_push( $this->parts, $op );
         array_push( $this->parts, $clause );
      }
   }

   /*!
    * \brief Returns where-clause.
    * \param $inc_where optional arg, if false, keyword 'WHERE' is not returned as prefix.
    */
   function get_where_clause( $inc_where = true )
   {
      if ( $this->has_clause() )
         return ($inc_where ? 'WHERE ' : '') . implode(' ', $this->parts);
      else
         return '';
   }

   /*! \brief embrace current clause-parts in this object with '(..)'. */
   function embrace()
   {
      $clause = $this->get_where_clause( false );
      $this->parts = array( "($clause)" );
   }
} // end of 'WhereClause'



 /*!
  * \class RequestParameters
  *
  * \brief Class to store request-parameters, which can be attached to Table- or Form-class.
  * This class provides two interface-methods:
  *    \see get_hiddens()
  *    \see get_url_parts()
  *
  * see also SearchFilters->get_req_params(..)
  */
class RequestParameters
{
   /*! \brief array holding pairs of data: ( key => val ), val can be an array too representing multi-values. */
   var $values;

   /*!
    * \brief Constructs RequestParameters( [array( key => val)] )
    * \param #arr_src Copies values from optional passed source-arry.
    */
   function RequestParameters( $arr_src = null )
   {
      $this->values = array();
      if ( is_array($arr_src) )
      {
         foreach( $arr_src as $key => $val )
            $this->values[$key] = $val;
      }
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
      $arr_str = array();
      foreach( $this->values as $key => $val )
      {
         if ( is_array($hiddens) )
            $hiddens[$key] = $val;
         array_push( $arr_str, "  <input type=\"hidden\" name=\"{$key}\" value=\"{$val}\">\n" );
      }

      return implode( '', $arr_str );
   }

   /*!
    * \brief Returns an URL-encoded URL-parts-string for all the key-value-pairs of this object.
    * signature: interface string url_parts = get_url_parts();
    * note: used as interface for Form- or Table-class
    * note: also handle multi-values using 'varname[]'-notation
    */
   function get_url_parts()
   {
      $arr_url = array(); // for URL: arr( 'key=val' )
      foreach( $this->values as $key => $val )
      {
         if ( is_array($val) )
         {
            $akey = $key . '%5b%5d='; //encoded []
            foreach( $val as $v )
               array_push( $arr_url, $akey . urlencode($v) );
         }
         else
            array_push( $arr_url, $key . '=' . urlencode($val) );
      }
      return implode( URI_AMP, $arr_url );
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
  * supported: sub-clauses (though those are since mysql4.1 and DGS is below that for now).
  * NOT supported: UNION-selects-syntax
  *
  * SQL-parts of type:
  *    SQLP_OPTS: [DISTINCT] [STRAIGHT_JOIN] [SQL_BIG_RESULT] [SQL_BUFFER_RESULT] [SQL_CACHE | SQL_NO_CACHE]
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
         if ( isset($this->parts[$arg]) )
            $type = $arg;
         else
         {
            if ( $type == '')
               error("QuerySQL: missing part-type for part [$arg]");
            if ( $arg != '' )
               $this->parts[$type][] = $arg;
         }
      }
   }

   /*!
    * \brief Adds SQL-part
    * signature: add_part( part_type, part1, part2, ...); var-args for one part-type
    * param $type sql-part-type to add to QuerySQL: one of SQLP_...-consts
    * param $parts allow variable number of parts (1..n)
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
      if ( !isset($this->parts[$type]) )
         error("QuerySQL.add_part($type): used unknown type [$type]");
      if ( ($type === SQLP_GROUP or $type === SQLP_LIMIT) and $this->has_part($type) )
      {
         // allow only once, except same part-value
         $arg = ( func_num_args() > 1 ) ? func_get_arg(1) : null;
         if ( !is_null($arg) and !empty($arg) and $this->get_part($type) != $arg )
            error("QuerySQL.add_part($type): type [$type] can only be set once (except the same value), part1=$arg");
         return; // ignore same-value
      }

      // skip arg #0=type-arg to add var-args: parts
      for( $i=1; $i < func_num_args(); $i++)
      {
         $part = trim(func_get_arg($i));
         if ( $part != '' )
            $this->parts[$type][] = $part;
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
         if ( !isset($this->parts[$type]) )
            error("QuerySQL.clear_parts(): used unknown type [$type]");
         if ( isset($this->parts[$type]) )
            $this->parts[$type] = array();
      }
   }

   /*! \brief Returns true, if sql-part existing */
   function has_part( $type )
   {
      if ( !isset($this->parts[$type]) )
         error("QuerySQL.has_part($type): used unknown type");
      return ( count($this->parts[$type]) > 0 );
   }

   /*! \brief Returns parts-array for specified part_type */
   function get_parts( $type )
   {
      if ( !isset($this->parts[$type]) )
         error("QuerySQL.get_parts($type): used unknown type");
      return $this->parts[$type];
   }

   /*!
    * \brief Returns typed part of select-statement; '' for none set.
    * param incl_prefix: if true, preprend sql-part with according SQL-keyword, e.g. 'SELECT' for SQLP_FIELDS
    */
   function get_part( $type, $incl_prefix = false )
   {
      if ( !$this->has_part($type) )
         return '';

      // make unique: SQLP_FIELDS|OPTS|FROM|WHERE|HAVING|ORDER
      $arr = array_unique( $this->parts[$type] );
      if ( $type === SQLP_FIELDS or $type === SQLP_ORDER or $type === SQLP_GROUP )
         $part = implode(', ', $arr);
      elseif ( $type === SQLP_WHERE or $type === SQLP_HAVING or $type === SQLP_WHERETMPL )
         $part = implode(' AND ', $arr);
      elseif ( $type === SQLP_FROM )
         $part = $this->merge_from_parts( $arr );
      elseif ( $type === SQLP_LIMIT )
         $part = (count($arr) > 0) ? $arr[0] : '';
      elseif ( $type === SQLP_FNAMES )
         $part = implode(',', $arr);
      elseif ( $type === SQLP_OPTS )
         $part = implode(' ', $arr);
      elseif ( $type === SQLP_UNION_WHERE )
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
      if ( count($arr) == 0 )
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
         if ( !preg_match( "/^(STRAIGHT_JOIN|((INNER|CROSS)\s+)?JOIN|(NATURAL\s+)?(LEFT|RIGHT)\s+(OUTER\s+)?JOIN)\s/i", $part ) )
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
      if ( !$this->has_part(SQLP_UNION_WHERE) )
         return $this->get_select_normal();

      // handle UNION-syntax
      $arr_union = array();
      $union_parts = $this->get_parts(SQLP_UNION_WHERE);
      for( $idx=0; $idx < count($union_parts); $idx++)
      {
         array_push( $arr_union, $this->get_select_normal($idx) );
      }

      $arrsql = array();
      array_push( $arrsql, '(' . implode(') UNION (', $arr_union) . ')' );

      if ( $this->has_part(SQLP_ORDER) )
         array_push( $arrsql, $this->get_part(SQLP_ORDER, true) );
      if ( $this->has_part(SQLP_LIMIT) )
         array_push( $arrsql, $this->get_part(SQLP_LIMIT, true) );

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
      if ( $this->has_part(SQLP_FIELDS) or $has_opts )
         array_push( $arrsql, 'SELECT' );
      if ( $has_opts )
         array_push( $arrsql, $this->get_part(SQLP_OPTS) );
      array_push( $arrsql, $this->get_part(SQLP_FIELDS) );
      array_push( $arrsql, $this->get_part(SQLP_FROM, true) );

      // handle UNION-WHERE and WHERE
      if ( $union_part < 0 )
      {
         if ( $this->has_part(SQLP_WHERE) )
            array_push( $arrsql, $this->get_part(SQLP_WHERE, true) );
      }
      else
      {
         $union_parts = $this->get_parts(SQLP_UNION_WHERE); // non-empty
         array_push( $arrsql, 'WHERE ' . $union_parts[$union_part] );

         if ( $this->has_part(SQLP_WHERE) )
            array_push( $arrsql, 'AND ' . $this->get_part(SQLP_WHERE) );
      }

      if ( $this->has_part(SQLP_GROUP) )
         array_push( $arrsql, $this->get_part(SQLP_GROUP, true) );
      if ( $this->has_part(SQLP_HAVING) )
         array_push( $arrsql, $this->get_part(SQLP_HAVING, true) );

      // ORDER and LIMIT only for non-union-select
      if ( $union_part < 0 )
      {
         if ( $this->has_part(SQLP_ORDER) )
            array_push( $arrsql, $this->get_part(SQLP_ORDER, true) );
         if ( $this->has_part(SQLP_LIMIT) )
            array_push( $arrsql, $this->get_part(SQLP_LIMIT, true) );
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
      if ( is_null($qsql) or empty($qsql) )
         return true;

      $errmsg = '';
      if ( !is_a($qsql, 'QuerySQL') )
         $errmsg = "QuerySQL.merge(): specified argument is not a QuerySQL-object";
      if ( $errmsg != '' )
      {
         error( $errmsg ); // error may be func that go-on
         return false;
      }

      // eventually add_part throws error
      $this->add_part( SQLP_GROUP, $qsql->get_part(SQLP_GROUP) );
      $this->add_part( SQLP_LIMIT, $qsql->get_part(SQLP_LIMIT) );

      foreach( array_keys( $this->parts ) as $type )
      {
         if ( $qsql->has_part($type) )
            if ( $type != SQLP_GROUP and $type != SQLP_LIMIT )
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
      if ( !$query->merge($qsql) )
         return NULL;

      // manual merge of WHERE and HAVING
      $query->clear_parts( SQLP_WHERE, SQLP_HAVING );
      foreach( array( SQLP_WHERE, SQLP_HAVING ) as $type )
      {
         $arr = array();
         if ( $this->has_part($type) )
            array_push( $arr, $this->get_part($type) );
         if ( $qsql->has_part($type) )
            array_push( $arr, $qsql->get_part($type) );
         if ( count($arr) > 0 )
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
         if ( $this->has_part($type) )
            array_push( $arr, "$type={[" . implode( '], [', $this->parts[$type] ) . "]}" );
      }
      return "QuerySQL: " . implode(', ', $arr);
   }
} // end of 'QuerySQL'

?>
