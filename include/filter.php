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

$TranslateGroups[] = "Common";

require_once( "include/std_functions.php" );
require_once( "include/std_classes.php" );
require_once( "include/form_functions.php" );
require_once( "include/filter_functions.php" );
require_once( "include/filter_parser.php" );
require_once( 'include/classlib_bitset.php' );

 /* Author: Jens-Uwe Gaspar */

 /*!
  * \file filter.php
  *
  * \brief Classes to provide a framework to support filters that can be used
  *        together with the Form- and/or Table-class.
  *
  * <p>Basically a filter is combining a GUI-representation (as form-elements)
  * with a value-parser that transforms the user-input into a SQL-query on some
  * database-fields using the parsed value(s).
  *
  * <p>There are several standard filters that can be used with several
  * different options, which are described at the respective class-description.
  *
  * \see SearchFilter
  * \see Filter
  * \see   FilterNumeric
  * \see   FilterText
  * \see   FilterRating
  * \see   FilterDate
  * \see   FilterRelativeDate
  * \see   FilterSelection
  * \see   FilterBoolSelect
  * \see   FilterRatedSelect
  * \see   FilterBoolean
  * \see   FilterScore
  * \see   FilterRatingDiff
  * \see   FilterCheckboxArray
  *
  * \see   FilterCountry     (see include/filterlib_country.php)
  * \see   FilterMysqlMatch  (see include/filterlib_mysqlmatch.php)
  */

 /*!
  * \example filter_example.php
  *
  * Example of how to use the filter-framework.
  * You may also have a look into 'opponents.php',
  * 'forum/search.php' or 'users.php'.
  */

// choices for certain filter-characteristics, set get_filter_keys-func
define('GETFILTER_NONE',      0); // only filter-general attributes (init, activeset, hashcode)
define('GETFILTER_ALL',       1); // all filters
define('GETFILTER_ACTIVE',    2); // active filters
define('GETFILTER_INACTIVE',  3); // inactive filters
define('GETFILTER_USED',      4); // active filters with non-empty value
define('GETFILTER_ERROR',     5); // active filters with non-empty value and error
define('GETFILTER_VISIBLE',   6); // visible filters
define('GETFILTER_INVISIBLE', 7); // invisible filters
define('GETFILTER_WARNING',   8); // active filters with non-empty value and warning

// if true, forces all filter-elements to be static (overruled by filter-config FC_HIDE)
define('FILTER_CONF_FORCE_STATIC', true);

// form-element names
define('PFX_FILTER', 'sf'); // prefix for search-filter
define('FNAME_INIT',       'sf_init'); // initial state (unset if first launched)
define('FNAME_ACTIVE_SET', 'sf_act');  // fieldname active-set  (w/o sf-prefix)
define('FNAME_HASHCODE',   'sf_hc');   // hashcode for active filter-values
define('FFORM_TOGGLE_FID',    'togglefid'); // suffix to store add-col-form [ filter-id, 0, -1 ]
define('FFORM_TOGGLE_ACTION', 'action_af'); // suffix for submit-action for add/del-filter
define('FFORM_SEARCH_ACTION', 'search_f');  // suffix for apply-filter-submit-action for table-filter-controls
define('FFORM_RESET_ACTION',  'reset_f');   // suffix for reset-submit-action for table-filter-controls


// Filter Config, see description of Filter-classes to see which configs are supported
//   doc-template: \brief main-description; values: allowed config-values; default is ...
//   note: if no default given here, the default is filter-specific

/*! \brief Size for input-text-elem or height of select-box or other size-based attributes; values: int. */
define('FC_SIZE', 'size');

/*! \brief Max-length for input-text-elements; values: int (0 to omit maxlen in form-element); default is 0. */
define('FC_MAXLEN', 'maxlen');

/*! \brief Filter is always shown if set (can't be changed to be inactive); values: bool; default is false. */
define('FC_STATIC', 'static');

/*! \brief Filter-element has hide-toggle (even if global FILTER_CONF_FORCE_STATIC is true); default is false. */
define('FC_HIDE', 'hide');

/*! \brief Used for text/numeric-based filters: allow no range-syntax like '3-9'; values: bool; default is false (range-allowed). */
define('FC_NO_RANGE', 'no_range');

/*! \brief for text-based filters: forbids wildcard like 'foo*'; values: bool; default is false (wildcard-allowed). */
define('FC_NO_WILD', 'no_wild');

/*!
 * \brief for text-based filters: allows value to start with wildcard like '*foo'.
 * values: int (number of consecutive chars),
 *         value STARTWILD_OPTMINCHARS (=4) ideally used so that mysql can optimize
 * default is false (input-value must not start with wildcard).
 */
define('FC_START_WILD', 'start_wild');

/*!
 * \brief for text-based filters: substring-search using implicit wildcards at start and
 *        end of string (needs FC_START_WILD to be set); values: bool; default is false.
 */
define('FC_SUBSTRING', 'substring');

/*!
 * \brief for RelativeDate-Filter: selection of time-units to be shown in selectbox,
 *        values can be OR'ed together (e.g. FRDTU_ABS | FRDTU_DHM ).
 * values:  FRDTU_ABS  - also allow absolute dates like in Date-Filter, \see FilterDate
 *          FRDTU_YMWD - combining time-units (YEAR, MONTH, WEEK, DAY)
 *          FRDTU_DHM  - combining time-units (DAY, HOUR, MIN)
 *          FRDTU_YEAR, FRDTU_MONTH, FRDTU_WEEK, FRDTU_DAY, FRDTU_HOUR, FRDTU_MIN - single time-unit
 *          FRDTU_ALL_ABS - combining absolute and all relative time-units (YEAR, MONTH, WEEK, DAY, HOUR, MIN)
 * default: FRDTU_ALL_REL
 */
define('FC_TIME_UNITS', 'time_units');

/*!
 * \brief Additionally QuerySQL-object to be merged if filter is set.
 * value: QuerySQL
 */
define('FC_QUERYSQL', 'querysql');

/*!
 * \brief Groups filters together by OR'ing the queries of filters with the same group-name.
 * values: string group_name
 */
define('FC_GROUP_SQL_OR', 'group_sql_or');

/*!
 * \brief Treat dbfield-argument of Filter as SQL-template.
 * value: bool
 * format for dbfield: string SQL_where_part, e.g. 'field #OP #VAL'
 *     (#OP is replaced with operation, #VAL replaced with input-value from filter)
 */
define('FC_SQL_TEMPLATE', 'sql_templ');

/*!
 * \brief Don't include filters-query when building whole SearchFilters query;
 * values: bool
 * default is false (include filter)
 */
define('FC_SQL_SKIP', 'sql_skip');

/*!
 * \brief Use alternative form-element-name (no prefix used);
 *        for multi-element-filters it's the base-name, which may be suffixed.
 * values: bool
 * default: use automatically built name (prefix PFX_FILTER filter-id, e.g. 'sf7')
 */
define('FC_FNAME', 'fname');

/*! \brief for Boolean-Filter: specify text-label for checkbox. */
define('FC_LABEL', 'label');

/*!
 * \brief Sets default-value(s) for filter to be used.
 * note: for format see specific Filter-doc (can be scalar or array)
 * note: filter with FC_DEFAULT but not shown is still used to build query(!)
 * default: none
 */
define('FC_DEFAULT', 'default');

/*!
 * \brief Allows conditional actions performed for SearchFilter->get_query().
 * values: array( condition, actions ...)
 *         see eval_condition-func for condition-syntax,
 *         see perform_conditional_action-func for action-syntax
 */
define('FC_IF', 'if');

/*!
 * \brief for filters using SQL-templates: if true, where-clause-part is added as HAVING-part instead.
 *        Its main-use is when the field to be filtered is something calculated from the SELECT-fields.
 * values: bool
 * default is false.
 */
define('FC_ADD_HAVING', 'add_having');

/*! \brief for NumericFilter: gives numerical factor for values before used in SQL; values: float; default is 1. */
define('FC_NUM_FACTOR', 'num_factor');

/*!
 * \brief for Selection-Filter and CheckboxArray-Filter: makes a multi-value selectbox.
 * note: URL-args are given as 'fname[]=val, ...'
 * values: array( choice-description => HTML-unquoted value to be combined into dbfield-based query ), see filter_example.php
 * default is false
 */
define('FC_MULTIPLE', 'multiple');

/*!
 * \brief overrule standard quotetype for parsing of text- and numeric-based filters.
 * values: QUOTETYPE_ESCAPE, QUOTETYPE_QUOTE, QUOTETYPE_DOUBLE, see tokenizer.php
 * default is to use user-set default (which is normally QUOTETYPE_ESCAPE)
 */
define('FC_QUOTETYPE', 'quotetype');

/*!
 * \brief allows to adjust hover-text with syntax-description for filter.
 * values: array( FCV_SYNHINT_ADDINFO => optional, additional text inserted right after 'Syntax (addinfo):' );
 * default is not to use any additional hint in syntax-description.
 */
define('FC_SYNTAX_HINT', 'syntax_hint');
define('FCV_SYNHINT_ADDINFO', 'fcv_synhint_addinfo');

/*!
 * \brief allows to overwrite syntax-help-id in hover-text with syntax-description for filter.
 * values: text inserted right after 'Syntax[HELP] (addinfo):'
 * default is to use filters syntax-help-id.
 */
define('FC_SYNTAX_HELP', 'syntax_help');

/*! \brief for CheckboxArray-Filter: indicates to build a bitmask; values: bool; default is false. */
define('FC_BITMASK', 'bitmask');


// globals with lazy-init for filter-specific translated texts
$ARR_GLOBALS_FILTERS = array();


 /*!
  * \class SearchFilter
  * \brief Container managing list of filters.
  */
class SearchFilter
{
   /*! \brief array storing filters: arr( id => Filter ) */
   var $Filters;
   /*! \brief Prefix used to build vars for URL/form-names (without PFX_FILTER). */
   var $Prefix;
   /*! \brief Profile-handler */
   var $ProfileHandler;

   /*! \brief previous hash-code to check if filter-values have changed. */
   var $HashCode;
   /*! \brief true, if filter is initialized; false = no-search-started yet */
   var $is_init;
   /*! \brief true, if filter in reset-state */
   var $is_reset;

   /*! \brief array with accesskeys used for filters: array( search, reset ); none set per default. */
   var $accesskeys;

   /*!
    * \brief Constructs SearchFilter with optional prefix to be able to use more than one.
    *        Sets default accesskeys.
    */
   function SearchFilter( $prefix = '', $prof_handler=null )
   {
      $this->Filters  = array();
      $this->Prefix   = $prefix;
      $this->is_init  = false;
      $this->is_reset = false;
      $this->set_accesskeys( ACCKEY_ACT_FILT_SEARCH, ACCKEY_ACT_FILT_RESET );

      $this->set_profile_handler( $prof_handler );
   }

   /*! \brief Sets ProfileHandler managing search-profiles; use NULL to clear it. */
   function set_profile_handler( $prof_handler )
   {
      $this->ProfileHandler = $prof_handler;

      // register standard arg-names for filters
      if( $this->ProfileHandler )
      {
         $pfx = $this->Prefix;
         $this->ProfileHandler->register_argnames( array(
               // see init-method
               $pfx.FNAME_INIT, $pfx.FNAME_ACTIVE_SET, $pfx.FNAME_HASHCODE,
               FNAME_INIT,
            ));

         // NOTE: \d+\w* capture most of the additional-element-names, e.g. for Score/RelativeDate/CheckboxArray/MysqlMatch-Filter
         $this->ProfileHandler->register_regex_save_args(
            sprintf( "%s(%s|%s|%s|%s)|%s",
               $pfx, PFX_FILTER."\d+\w*", FNAME_INIT, FNAME_ACTIVE_SET, FNAME_HASHCODE,
               FNAME_INIT ));
      }
   }

   /*!
    * \brief Returns string- or array-value read from potentially saved profile or
    *        parsed from _REQUEST-array stripped of slashes.
    * signature: string|array get_saved_arg( string name, [bool use_prefix=1])
    * \internal
    * \see get_arg()
    */
   function get_saved_arg( $name, $use_prefix = true )
   {
      $fname = ( $use_prefix ) ? $this->Prefix . $name : $name;
      // clear & reset handled in init-func
      $value = ( $this->ProfileHandler )
         ? $this->ProfileHandler->get_arg( $fname )
         : NULL;
      return (is_null($value)) ? get_request_arg($fname) : $value;
   }

   /*!
    * \brief Returns string- or array-value parsed from _REQUEST-array stripped of slashes.
    * signature: string|array get_arg( string name, [bool use_prefix=1])
    * \internal
    * \see get_saved_arg()
    */
   function get_arg( $name, $use_prefix = true )
   {
      $fname = ( $use_prefix ) ? $this->Prefix . $name : $name;
      return get_request_arg($fname);
   }

   /*!
    * \brief Adds a filter.
    * signature: Filter & add_filter(int id, string type, string dbfield, [bool active=false], [array config])
    * \param id numeric, if Table involved, id must match with the nr of the table-head from 'add_tablehead(nr,..)'
    * \param type what Filter to add, see on the specific Filters description as "SearchFilter-Type".
    * \param dbfield string or array with db-field-specification (see specs/filters.txt)
    * \param active true, if filter should be active; if not active filter is not included to build the resulting query;
    *               within a table, an inactive filter is hidden with a '+'-sign.
    * \param config array ( config_name => config_value ); filter-general and filter-specific configuration (see specs/filters.txt)
    */
   function &add_filter($id, $type, $dbfield, $active = false, $config = null)
   {
      // checks: force unique filterid
      // note: don't force unique dbfields (so we are open to allow more filters on same field)
      if( strlen($type) == 0 )
         error('invalid_filter', "filter.add_filter.bad_type($id,$type)"); // type non-empty
      if( !is_numeric($id) )
         error('invalid_filter', "filter.add_filter.bad_filter_id($id)");
      if( $id < 1 || $id > BITSET_MAXSIZE )
         error('invalid_filter', "filter.add_filter.filter_id_out_of_range($id)");
      if( isset($this->Filters[$id]) )
         error('invalid_filter', "filter.add_filter.unique_filter_id($id)");
      if( count($this->Filters) > BITSET_MAXSIZE )
         error('invalid_filter', "filter.add_filter.full");

      $filter_class = 'Filter' . $type;
      $filter = new $filter_class($id, $dbfield, $config); // error if unknown class
      $filter->SearchFilter = $this;
      $this->Filters[$id] =& $filter; // need ref

      // init filter
      $filter->set_active($active);

      // register arg-names to profile-handler (regex-parts set in set_profile_handler)
      if( $this->ProfileHandler )
      {
         $pfx = ($filter->get_config(FC_FNAME)) ? '' : $this->Prefix;
         $elems = $filter->get_element_names();
         foreach( $elems as $fname )
            $this->ProfileHandler->register_argnames( $pfx . $fname );
      }

      return $filter;
   }

   /*! \brief Parses vars from _REQUEST to initialize filters with internal and filter-values. */
   function init()
   {
      $arr_keys = $this->get_filter_keys(GETFILTER_ALL);
      $is_init  = (bool)( $this->get_saved_arg(FNAME_INIT) );
      $is_reset = (bool)( $this->get_arg(FFORM_RESET_ACTION) != '' );
      $act_set  = $this->get_saved_arg(FNAME_ACTIVE_SET); // hex-str for BitSet
      $bitset_active = BitSet::read_from_hex($act_set);

      // handle reset or clear induced by profile-handler
      $need_clear = false;
      if( $this->ProfileHandler )
      {
         if( $this->ProfileHandler->need_reset )
            $is_reset = true;
         elseif( $this->ProfileHandler->need_clear )
            $is_init = $need_clear = true;
      }

      // prefix-independent init & reset
      $this->is_init  = (bool)( $is_init  || $this->get_saved_arg(FNAME_INIT, false) );
      $this->is_reset = (bool)( $is_reset || ($this->get_arg(FFORM_RESET_ACTION, false) != '') );

      $this->HashCode = ($this->is_init) ? $this->get_saved_arg(FNAME_HASHCODE) : $this->hashcode();

      // 1. parse active-set if initialized
      // 2. parse values from args-array into filters
      foreach( $arr_keys as $id )
      {
         $filter =& $this->get_filter($id);
         if( isset($filter) )
         {
            // parse active-set
            if( $this->is_init )
               $filter->set_active( $bitset_active->get_bit($id) );

            // parse values if active and not a reset
            if( $filter->is_active() )
            {
               if( $this->is_reset || !$this->is_init )
                  $filter->reset();

               $use_prefix = !((bool) $filter->get_config(FC_FNAME));
               $elems = $filter->get_element_names();
               foreach( $elems as $fname )
               {
                  if( $this->is_reset )
                     $qvalue = null; // reset value, independent from is_init(!)
                  else
                     $qvalue = $this->get_saved_arg( $fname, $use_prefix ); // string | array

                  // reset to default, if no value and in init-state
                  if( $qvalue == '' && !$this->is_init && !$need_clear )
                     $qvalue = null;

                  if( is_array($qvalue) && !$filter->get_config(FC_MULTIPLE) )
                     error('invalid_filter', "filter.init.no_multi_value_support($id,$fname)");
                  $filter->parse_value( $fname, $qvalue ); // ignore all parsing-errors and fill filter-vars
               }
               if( count($elems) > 1 && !$filter->has_error() ) // for multi-element-filters
                  $filter->build_query();
            }
         }
      }
   }

   /*!
    * \brief Set access keys for search and reset of filters:
    * Normal would be ACCKEY_ACT_FILT_SEARCH for search and ACCKEY_ACT_FILT_RESET for reset.
    */
   function set_accesskeys( $acckey_search='', $acckey_reset='' )
   {
      $this->accesskeys[0] = $acckey_search;
      $this->accesskeys[1] = $acckey_reset;
   }

   /*! \brief Parses vars from _REQUEST to update activity-state of filters (used for Table). */
   function add_or_del_filter()
   {
      $act = (string) $this->get_arg(FFORM_TOGGLE_ACTION, true); // set if show-/hide-action needed
      $fid = (string) $this->get_arg(FFORM_TOGGLE_FID, true); // if act set: >0=Nr for thead[Nr], 0=hide-all, -1=show-all
      if( $act && (string)$fid != '' )
      {
         if( $fid > 0 ) // show or hide single filter
         {
            if( !$this->is_active($fid) )
               $this->reset_filter($fid);
            $this->toggle_active($fid);
         }
         elseif( $fid < 0 ) // -1 (show all)
         {
            $this->reset_filters(GETFILTER_INACTIVE);
            $this->setall_active(true);
         }
         else // fname=0 (hide all)
            $this->setall_active(false);
      }
   }

   /*! \brief Returns reference of specified filter or null if no filter defined for id. */
   function &get_filter($id)
   {
      // note: following check is needed, otherwise the call creates entries :(
      // note: MUST NOT use '?'-operator, or else copy is returned instead of ref :(
      if( isset($this->Filters[$id]) ) {
         return $this->Filters[$id];
      } else {
         $nullref = NULL;
         return $nullref;
      }
   }

   /*!
    * \brief Returns names of all registered filters (not neccessarily unordered)
    *        with optionally specified filter-characteristics.
    * \param choice GETFILTER_NONE | ALL| ACTIVE| INACTIVE| USED| ERROR| VISIBLE |INVISIBLE
    * signature: string[] get_filter_keys([int getfilter_choice=GETFILTER_ALL])
    */
   function get_filter_keys( $choice = GETFILTER_ALL )
   {
      $arr = array();
      if( $choice == GETFILTER_NONE )
         return $arr;

      foreach( $this->Filters as $id => $filter )
      {
         // in order of mostly used in code
         if( $choice == GETFILTER_ALL )
            ; // all
         elseif( $choice == GETFILTER_ACTIVE )
         {
            if( !$filter->is_active() ) continue; // only active
         }
         elseif( $choice == GETFILTER_INACTIVE )
         {
            if( $filter->is_active() ) continue; // only inactive
         }
         elseif( $choice == GETFILTER_VISIBLE )
         {
            if( !$filter->is_visible() ) continue; // only visible
         }
         elseif( $choice == GETFILTER_INVISIBLE )
         {
            if( $filter->is_visible() ) continue; // only invisbile
         }
         elseif( $choice == GETFILTER_USED )
         {
            if( !$filter->is_active() || $filter->is_empty() )
               continue; // only active and with non-empty value
         }
         elseif( $choice == GETFILTER_ERROR )
         {
            if( !$filter->is_active() || $filter->is_empty() || !$filter->has_error() )
               continue; // only active and with non-empty value and with error-message
         }
         elseif( $choice == GETFILTER_WARNING )
         {
            if( !$filter->is_active() || $filter->is_empty() || !$filter->has_warn() )
               continue; // only active and with non-empty value and with warng-message
         } // else: all

         $arr[]= $id;
      }
      return $arr;
   }

   /*! \brief Returns true, if SearchFilter initialized, or false if no search started yet. */
   function is_init()
   {
      return $this->is_init;
   }

   /*! \brief Returns true, if SearchFilter in reset-state. */
   function is_reset()
   {
      return $this->is_reset;
   }

   /*! \brief Returns count of filters with optionally specified characteristic. */
   function size( $choice = GETFILTER_ALL )
   {
      return count( $this->get_filter_keys( $choice ) );
   }

   /*!
    * \brief Returns hashcode-string (CRC32-encoded) of stored active filter-values.
    * Used to check, if filter-values have been changed to be able to reset from_row in Table.
    */
   function hashcode()
   {
      $arr = $this->get_filter_keys(GETFILTER_ACTIVE);
      $arrhash = array();
      foreach( $arr as $id )
      {
         $filter = $this->get_filter($id);
         foreach( $filter->get_element_names() as $name )
            $arrhash[]= "$name=". $filter->get_value($name);
      }
      $hashstr = implode( ',', $arrhash );
      $hashcode = crc32($hashstr);
      # $hashcode = crc32($hashstr) . '.' . md5($hashstr);
      return $hashcode;
   }

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   function to_string()
   {
      $out = "SearchFilter={ Prefix=[{$this->Prefix}], HashCode=[{$this->HashCode}], is_init=[{$this->is_init}], "
         . "is_reset=[{$this->is_reset}],";
      $cnt = 0;
      foreach( $this->Filters as $filter )
      {
         $cnt++;
         $out .= "$cnt. " . $filter->to_string() . "\n";
      }
      return $out;
   }

   /*!
    * \brief Returns non-null SQL-query for filters (but it could be empty).
    * signature: QuerySQL get_query([int getfilter_choice=GETFILTER_ACTIVE])
    * note: joining all queries, OR-grouping filters with (FC_GROUP_SQL_OR-config)
    * note: skipping filters with FC_SQL_SKIP-config set
    * note: handle FC_IF-config on filters
    */
   function get_query( $choice = GETFILTER_ACTIVE )
   {
      $arr_query = array(); // QuerySQLs for filters w/o grouping
      $arr_groupquery = array(); // map: groupname => OR-merged QuerySQLs

      $this->handle_conditional_actions( $choice );

      $arr_id = $this->get_filter_keys( $choice );
      foreach( $arr_id as $id )
      {
         $filter = $this->get_filter($id);
         if( $filter->get_config(FC_SQL_SKIP) ) // skip
            continue;

         $fquery = $filter->get_query(); // use copy
         if( is_null($fquery) ) // no query
            continue;

         $merge_qsql = $filter->get_config(FC_QUERYSQL); // merge query
         if( is_a($merge_qsql, 'QuerySQL') )
            $fquery->merge( $merge_qsql );

         $groupname = $filter->get_config(FC_GROUP_SQL_OR);
         if( $groupname == '' )
         { // collect queries without OR-grouping
            $arr_query[]= $fquery;
         }
         else
         { // merge queries to-be-grouped by OR'ing them together
            if( !isset($arr_groupquery[$groupname]) )
               $arr_groupquery[$groupname] = new QuerySQL();
            $arr_groupquery[$groupname] = $arr_groupquery[$groupname]->merge_or($fquery);
         }
      }

      // handling OR'ed queries
      foreach( $arr_groupquery as $group => $query )
         $arr_query[]= $query;

      // AND'ing all queries
      $result_query = new QuerySQL();
      if( count($arr_query) > 0 )
      {
         foreach( $arr_query as $query )
            $result_query->merge($query);
      }
      return $result_query;
   }

   /*!
    * \brief Handle FC_IF-config with conditions and actions on filters.
    * \internal
    * signature: bool success = handle_conditional_actions()
    * note: return false, if one of the filters used in the conditions has an error
    *
    * Example: FC_IF => array( "Q2 or V4='1'", "SET_VAL F3,N1,1" )
    */
   function handle_conditional_actions()
   {
      $arr_id = $this->get_filter_keys(GETFILTER_VISIBLE);
      foreach( $arr_id as $id )
      {
         $filter = $this->get_filter($id);
         $if_arr = $filter->get_config(FC_IF);
         if( !is_array($if_arr) )
            continue;

         $cond = array_shift($if_arr);
         $eval_cond = $this->eval_condition( $cond );
         if( is_null($eval_cond) )
            return false; // error
         if( !$eval_cond )
            continue;

         $arr_post_act = array(); // fid -> array of post-action
         foreach( $if_arr as $action )
         {
            $r = $this->perform_conditional_action( $id, $action );
            if( is_array($r) )
               $arr_post_act[$r[1]][] = $r[0];
         }

         // do post-actions
         foreach( $arr_post_act as $fid => $arr_fid_act )
         {
            $arr = array_unique( $arr_fid_act );
            $f =& $this->get_filter($fid);
            foreach( $arr as $act )
            {
               if( $act === 'BQ' )
                  $f->build_query();
               else
                  error('invalid_filter', "filter.handle_conditional_actions.unknown_post_action($fid,$act)");
            }
         }
      }

      return true;
   }

   /*!
    * \brief Evaluates condition set by FC_IF-config on filter.
    * \internal
    * signature: bool success = eval_condition( string condition )
    * \param condition syntax:
    *        - Qnum => !filter[num]->has_error() and filter[num]->has_query()
    *        - Vnum => !filter[num]->has_error() and filter[num]->get_value()
    *        note: for comparison, quotes are allowed in condition
    * note: return NULL, if one of the specified filters has an error
    * Example for condition:
    *    "Q2 or V4=='a z'" => filter #2 has a query OR value of filter #4 is 'a z'
    */
   function eval_condition( $condition )
   {
      $aq = array(); // query-array: aq[fid] = !has_err and has_query
      $av = array(); // value-array: av[fid] = !has_err and get_val
      if( preg_match_all( "/\b[QV](\d+)/", $condition, $out, PREG_PATTERN_ORDER) )
      {
         foreach( $out[1] as $fid )
         {
            $f = $this->get_filter($fid);
            if( $f->has_error() )
               return NULL;

            $aq[$fid] = !is_null($f->get_query());
            $av[$fid] = $f->get_value();
         }
      }

      $expression = preg_replace(
         "/([QV])(\d+)/e",
         '\'$a\'' . " . strtolower('\\1') . '[\\2]'",
         $condition );

      $result = eval( "return $expression;" );

      #error_log("eval_condition($condition): expression=[$expression], result=[$result]");
      return (bool)$result;
   }

   /*!
    * \brief Only used by eval_condition-func: Performs action for filter-condition.
    * signature: bool success = perform_conditional_action( filter_id, string action )
    * \param id filter-id
    * \param action syntax and action performed:
    *        - SET_VAL F[num1],N[num2],val => filter[num1]->parse_value(name[num2],val);
    *          parses and rebuilds query for filter => filter[num1]->parse_value(name[num2],val);
    *          returns array( 'BQ', fid) if need to build_query after all actions performed (for multi-element)
    *        - SET_ACT F[num1],val=0|1   => filter[num1]->set_active(val)
    *        - SET_VIS F[num1],val=0|1   => filter[num1]->set_visible(val)
    *        note: no quotes allowed in action-syntax
    * \return true, if no further action taken,
    *         error is thrown in case of an error,
    *         but may return other values (see syntax-defs)
    * \internal
    */
   function perform_conditional_action( $id, $action )
   {
      if( preg_match( "/^SET_VAL\s+F(\d+)?,N(\d+)?,(.*?)\s*$/i", $action, $out ) ) // out: f-num, name-num, val
      {
         // handle SET_VAL
         $fid = (is_numeric($out[1])) ? $out[1] : $id;
         $f =& $this->get_filter($fid);
         if( is_null($f) )
            error('invalid_filter', "filter.perform_conditional_action.unknown_filter($fid,$action)");
         $nid = (is_numeric($out[2])) ? $out[2] : 1;
         $elems = $f->get_element_names();
         if( $nid < 1 || $nid > count($elems) )
            error('invalid_filter', "filter.perform_conditional_action.unknown_name_num($nid,$action)");
         $name = $elems[$nid - 1];

         $f->parse_value( $name, $out[3] ); // ignore all parsing-errors
         if( count($elems) > 1 && !$f->has_error() ) // for multi-element-filters
            return array( 'BQ', $fid );
         else
            return true;
      }

      if( preg_match( "/^SET_(ACT|VIS)\s+F(\d+)?,(0|1)\s*$/i", $action, $out ) ) // out: ACT|VIS, f-num, val
      {
         // handle SET_ACT + SET_VIS
         $fid = (is_numeric($out[2])) ? $out[2] : $id;
         $f =& $this->get_filter($fid);
         if( is_null($f) )
            error('invalid_filter', "filter.perform_conditional_action.unknown_filter2($fid,$action)");
         if( $out[1] === 'ACT' )
            $f->set_active((bool)$out[3]);
         else
            $f->set_visible((bool)$out[3]);
         return true;
      }

      error('invalid_filter', "filter.perform_conditional_action.unknown_filter3($fid,$action)");
   }

   /*!
    * \brief Returns true, if there is a query for the specified filters, i.e. has at least one non-empty where-clause.
    * signature: bool has_query( choice = null)
    * \param choice - scalar (filter-choice, e.g. GETFILTER_ACTIVE), or
    *               - array with speficic filter-IDs to check: array( filter_id, ... )
    *               - if null or empty-array use choice GETFILTER_ACTIVE
    * \param $arr_exclude optional array with filter-IDs to exclude from check
    */
   function has_query( $choice=null, $arr_exclude=null )
   {
      if( is_null($choice) || ( is_array($choice) && count($choice) == 0 ) )
         $choice = GETFILTER_ACTIVE;
      if( is_array($choice) )
         $arr_id = $choice;
      else
         $arr_id = $this->get_filter_keys( $choice );

      foreach( $arr_id as $id )
      {
         $filter = $this->get_filter($id);
         if( is_array($arr_exclude) && in_array($id, $arr_exclude) )
            continue;
         if( isset($filter) && !is_null($filter->get_query()) )
            return true;
      }
      return false;
   }


   /*! \brief Returns true, if filter with specified id is in active-state; false for unknown filter. */
   function is_active( $id )
   {
      $filter =& $this->get_filter($id);
      return ( isset($filter) ) ? $filter->is_active() : false;
   }

   /*! \brief Returns BitSet for filters in active-state. */
   function get_active_set()
   {
      $bitset = new BitSet();
      $arr = $this->get_filter_keys(GETFILTER_ACTIVE);
      foreach( $arr as $id )
         $bitset->set_bit($id);
      return $bitset;
   }

   /*! \brief Toggles active-state for speficied filter. */
   function toggle_active( $id )
   {
      $filter =& $this->get_filter($id);
      if( isset($filter) )
         $filter->toggle_active();
   }

   /*! \brief Changes active-state of specified filter. */
   function set_active( $id, $is_active )
   {
      $filter =& $this->get_filter($id);
      if( isset($filter) )
         $filter->set_active($is_active);
   }

   /*! \brief Changes active-state for all managed filters. */
   function setall_active( $is_active )
   {
      $arr = $this->get_filter_keys(GETFILTER_ALL);
      foreach( $arr as $id )
         $this->set_active($id, $is_active);
   }


   /*! \brief Resets specified filter to default-state (clear if no default-set). */
   function reset_filter( $id )
   {
      $filter =& $this->get_filter($id);
      if( isset($filter) )
         $filter->reset();
   }

   /*! \brief Resets specified choice (GETFILTER_...) of managed filters to their default-state. */
   function reset_filters( $choice )
   {
      $arr = $this->get_filter_keys($choice);
      foreach( $arr as $id )
         $this->reset_filter($id);
   }

   /*! \brief Changes visible-state of specified filter; see var Filter->visible ! */
   function set_visible( $id, $is_visible )
   {
      $filter =& $this->get_filter($id);
      if( isset($filter) )
         $filter->set_visible($is_visible);
   }


   /*!
    * \brief Returns encoded URL-string for specified filter-choice and exclusions.
    * \param arr_out return URL-vars with values additionally in this array
    * \param choice filter-choice to get URL-parts for
    * \param arr_exclude array of filter-ids to exclude (skip filter-IDs in this array)
    * note: Func for SearchFilter-class.
    */
   function get_url_parts( &$arr_out, $choice = GETFILTER_ACTIVE, $arr_exclude = null )
   {
      $arr_url = array(); // for URL: arr( 'key=val' )

      $arr_keys = $this->get_filter_keys($choice);
      $arr_parts = array(); // 'key' => val, key with(!) Prefix (except filters with FC_FNAME)
      foreach( $arr_keys as $id )
      {
         if( is_array($arr_exclude) && isset($arr_exclude[$id]) )
            continue; // exclude id

         $filter = $this->get_filter($id);
         $qstr = $filter->get_url_parts( $this->Prefix, $arr_parts );
         if( $qstr != '' )
            $arr_url[]= $qstr; // qstr may contain >1 URL-parts
      }

      // add other vars
      $arr = array();
      $bitset_active = $this->get_active_set();
      $arr[FNAME_INIT] = '1'; // already initialized
      $arr[FNAME_ACTIVE_SET] = $bitset_active->get_hex_format();
      $arr[FNAME_HASHCODE] = $this->hashcode(); // to check, if filter-values have changed
      foreach( $arr as $key => $value )
      {
         // filters need ''<>0, so don't use empty(val)
         if( (string)$value == '' || !is_string($key) || empty($key) )
            continue;
         $pkey = $this->Prefix . $key; // no prefix PFX_FILTER to avoid clashes with non-numeric keys
         $pval = urlencode($value);

         $arr_parts[$pkey] = $pval;
         $arr_url[]= "$pkey=$pval";
      }

      // return arr-out per call-by-ref
      if( is_array($arr_out) )
      {
         foreach( $arr_parts as $key => $value )
            $arr_out[$key] = $value;
      }

      return implode( URI_AMP, $arr_url );
   }

   /*! \brief Interface-func for Form-class if attached to get hiddens for filter-values. */
   function get_hiddens( &$arr_hiddens )
   {
      $this->get_filter_hiddens( $arr_hiddens, GETFILTER_NONE );
   }

   /*!
    * \brief Returns string with hidden-input strings for specified filter-choice and exclusions.
    * same args as for get_url_parts-func
    */
   function get_filter_hiddens( &$hiddens, $choice = GETFILTER_ACTIVE, $arr_exclude = null)
   {
      $arr_parts = array();
      $this->get_url_parts( $arr_parts, $choice, $arr_exclude );
      if( is_array($hiddens) )
         $hiddens = array_merge( $hiddens, $arr_parts);
      else
         $hiddens = $arr_parts;
      return build_hidden( $arr_parts);
   }

   /*! \brief Returns RequestParameters for specified filter-choice and exclusions. */
   function get_req_params( $choice = GETFILTER_ALL, $arr_exclude = null )
   {
      $arr_out = array();
      $this->get_url_parts( $arr_out, $choice, $arr_exclude );
      return new RequestParameters( $arr_out );
   }

   /*!
    * \brief Returns two form-elements with start-filter submit and
    *        profile-filter form-element (reset-submit at minimum)
    *        to be used with Table or External-Form.
    * \param $form instance of Form-class
    * \see set_accesskeys()
    */
   function get_submit_elements( $form )
   {
      $search_elem = $form->print_insert_submit_buttonx(
         $this->Prefix . FFORM_SEARCH_ACTION,
         T_('Search#filter'), array( 'accesskey' => $this->accesskeys[0] ));

      if( is_null($this->ProfileHandler) )
      {
         // normal reset-element
         $reset_elem = $form->print_insert_submit_buttonx(
            $this->Prefix . FFORM_RESET_ACTION,
            T_('Reset search#filter'), array( 'accesskey' => $this->accesskeys[1] ));

         $form_elems = array( $search_elem, $reset_elem );
      }
      else
      {
         // use profile-handler to get according form-elements
         $profile_elems = $this->ProfileHandler->get_form_elements( $form );
         $form_elems = array( $profile_elems, $search_elem );
      }
      return $form_elems;
   }

   /*! \brief Wrapper for get_filter(id)->get_input_element(prefix,attr); returns '' if filter not existing. */
   function get_filter_input_element( $id, $prefix, $attr = array() )
   {
      $filter = $this->get_filter($id);
      return ( isset($filter) ) ? $filter->get_input_element( $prefix, $attr ) : '';
   }

   /*! \brief Wrapper for QuerySQL|null get_filter(id)->get_query(); returns null if filter not existing. */
   function get_filter_query( $id )
   {
      $filter = $this->get_filter($id);
      return ( isset($filter) ) ? $filter->get_query() : NULL;
   }

   /*!
    * \brief Returns html-safe error-message for filter with passed id prepended
    *        with prefix and appended with syntax (if wanted) and a mandatory suffix;
    *        returns '' if no error occured for filter or unknown filter.
    */
   function get_filter_errormsg( $id, $prefix = '', $suffix = '', $with_syntax = true )
   {
      $filter = $this->get_filter($id);
      if( !isset($filter) || !$filter->has_error() )
         return '';

      $syntax = ( $with_syntax ) ? "; " . $filter->get_syntax_description() : '';
      return $prefix . make_html_safe( $filter->errormsg() . $syntax ) . $suffix;
   }

   /*! \brief Returns array( filter_id => error-msg for filter_id ), filter is only added if it has error. */
   function get_filter_errors()
   {
      $arr_keys = $this->get_filter_keys(GETFILTER_ERROR);
      $arr_errors = array();
      foreach( $arr_keys as $id )
      {
         $filter = $this->get_filter($id);
         if( isset($filter) && $filter->has_error() )
            $arr_errors[$id] = $filter->errormsg();
      }
      return $arr_errors;
   }

} // end of 'SearchFilter'




 /*!
  * \class Filter
  *
  * \brief Abstract base class with interface- and utility-methods needed to
  *        represent a filter.
  */
class Filter
{
   // filter-specific vars

   /*! \brief Reference to managing SearchFilter-object; maybe null, but normally set. */
   var $SearchFilter;

   /*! \brief Filter-ID used for referencing, normally == SearchFilter(id) */
   var $id;
   /*! \brief base-name used for form-element-names; default is 'PFX_FILTER$id'; can be overwritten with FC_FNAME-config. */
   var $name;
   /*! \brief dbfield-specification used to build filter-query: dbfield, array, sql-template, QuerySQL (see specs/filters.txt). */
   var $dbfield;
   /*! \brief array with additional URL-keys for multiple form-elements associated with filter; default is '$id' (e.g. see FilterRelativeDate). */
   var $elem_names;
   /*! \brief default-values as array( name => value ), read from config in Filter-constructor. */
   var $defvalues;
   /*! \brief (abstract) value specifying type of Filter (directly corresponding to specific Filter-class like FilterText as example); mandatory. */
   var $type;
   /*! \brief (abstract) value containing syntax-description for specific filter. */
   var $syntax_descr;
   /*! \brief (abstract) value containing syntax-help (showed in hover-text as help-id for FAQ). */
   var $syntax_help;
   /*!
    * \brief array containing config of filter: array( config_key => value );
    * keys are those with FC_-prefix.
    * note: be aware, that there must be a default for configs being used, or else you get an error.
    *       -> use @$config[key] to access config.
    */
   var $config;
   /*! \brief store TokenizerConfig for parsing using a tokenizer. */
   var $tok_config;
   /*! \brief bitmask for holding additional parser-flags. */
   var $parser_flags;

   /*! \brief boolean active-state: true, if filter should be used to build query in SearchFilter->get_query(). */
   var $active;
   /*!
    * \brief boolean visible-state; true, if visible (default); false set by deactivate_filters() if column not displayed.
    * note: var used to fix side-effect if FC_STATIC-config set which makes is_active() always true.
    */
   var $visible;

   // parsing vars (see parse_value-func)

   /*! \brief original input-value. */
   var $value;
   /*! \brief original input-value for multiple form-elements: array( name => value ), see get_value-func(!). */
   var $values;
   /*! \brief last parse error-msg; filter must be error-free to build query. */
   var $errormsg;
   /*! \brief last parse warning-msg; filter can show warnings, but still build query. */
   var $warnmsg;

   // p_start, p_end, p_value containing safe SQL-values (against SQL-injection used mysql-escaping)

   /*! \brief range-start (from beginning if not set). */
   var $p_start;
   /*! \brief range-end (until end if not set); for text used exclusive (=out-of-range). */
   var $p_end;
   /*! \brief special search-value, e.g. regex or exact. */
   var $p_value;
   /*! \brief flags(bitmask) for actually parsed values, e.g. PFLAG_WILDCARD. */
   var $p_flags;
   /*! \brief QuerySQL or NULL (unset). */
   var $query;
   /*! \brief non-null array with search-terms. */
   var $match_terms;


   /*!
    * \brief Constructs Filter with ID, dbfield-spec, default-config-array and normal config-array.
    * \internal
    */
   function Filter($id, $dbfield, $def_config = null, $config = null )
   {
      $this->SearchFilter = null;
      $this->config = array();

      $this->id = $id;
      $this->dbfield = $dbfield;
      $this->add_config( $def_config );
      $this->add_config( $config );

      // init config before reading it
      $name = $this->get_config(FC_FNAME);
      $this->name = ( $name != '') ? $name : PFX_FILTER . $id;
      $this->elem_names = array( $this->name );
      $this->read_defaults( $this->get_config(FC_DEFAULT) );

      // abstract values:
      $this->type = null; // abstract: to be defined in sub-class
      $this->syntax_descr = null; // abstract: to be defined in sub-class
      $this->syntax_help  = null; // abstract: to be defined in sub-class
      $this->tok_config = null; // abstract (may be used)

      $this->parser_flags = 0; // may be used

      $this->active  = true;
      $this->visible = true; // used in get_query()

      $this->values = array();
      $this->init_parse('');
   }

   /*! \brief Add config-array to local config (should be used only internal). */
   function add_config( $arrconf = null )
   {
      if( is_array($arrconf) )
      {
         foreach( $arrconf as $key => $value )
            $this->config[$key] = $value;
      }
   }

   /*! \brief Adds config key-value-pair to local config. */
   function set_config( $key, $value )
   {
      $this->config[$key] = $value;
   }

   /*!
    * \brief Returns string or array value for requested config-key; or passed non-null(!) default-value otherwise.
    * signature: string|array get_config( string key, [mixed defval=null])
    */
   function get_config( $key, $defvalue = null )
   {
      if( isset($this->config[$key]) )
         return $this->config[$key];
      elseif( !is_null($defvalue) )
         return $defvalue;
      else
         return '';
   }

   /*!
    * \brief Returns non-null array with regex-search-terms (from parsing-process),
    *        if filter supports it.
    * <p>Mainly used to highlight text, though not exactly matching the same
    * terms using a regular-expression to mark the text.
    * \see parse_html_safe() in std_functions.php
    */
   function get_rx_terms()
   {
      if( $this->is_active() )
         return $this->match_terms;
      else
         return array();
   }

   /*! \brief creates default TokenizerConfig with optional overruling by FC_QUOTETYPE-config. */
   function create_TokenizerConfig()
   {
      $qtype = $this->get_config(FC_QUOTETYPE);
      if( $qtype == '' )
         $qtype = null;
      $tokconf = createTokenizerConfig( $qtype ); // defined in filter_functions.php
      return $tokconf;
   }

   /*!
    * \brief Returns (new) QuerySQL built from passed object (if string or QuerySQL) and local config.
    * note: used to basic QuerySQL from various forms of dbfield.
    * \internal
    * signature: QuerySQL|null build_base_query( mixed obj, bool is_clause, bool use_tmpl )
    * \param obj QuerySQL or string db-fieldname or where-clause (if FC_SQL_TEMPLATE set),
    * \param is_clause if true, obj is considered a fix SQL-clause that needs
    *                     no transformation into template => put into QuerySQL(SQLP_WHERE |SQLP_HAVING)
    *                     according to FC_ADD_HAVING-config
    *                  if false, obj can be QuerySQL with one field in SQLP_FNAMES or else SQLP_WHERETMPL, or
    *                     obj can be fieldnames or sql-template (if FC_SQL_TEMPLATE set) => set QuerySQL(SQLP_FNAMES, SQLP_WHERETMPL)
    * \param use_tmpl if false, QuerySQL is returned without setup of SQLP_WHERETMPL though set SQLP_FNAMES is expected,
    *                 used when template forbidden to be used (e.g. for FilterScore),
    *                 expects that check_forbid_sql_template() has been called
    * \return null, if obj is an array or empty; otherwise new non-empty QuerySQL
    *         prepared to be used to build query internally.
    */
   function build_base_query( $obj, $is_clause, $use_tmpl = true )
   {
      if( is_array($obj) || empty($obj) )
         return NULL;

      if( is_a($obj, 'QuerySQL') )
      {
         $query = $obj;
         if( $use_tmpl && $query->has_part(SQLP_FNAMES) )
         {
            $query->clear_parts(SQLP_WHERETMPL); // overwrite template
            $query->add_part( SQLP_WHERETMPL,
               $this->default_sql_template( $obj->get_part(SQLP_FNAMES) ) );
         }
         return $query;
      }

      // obj (dbfield) is string (fieldnames or sql-clause or sql-template)
      $query = new QuerySQL();
      if( !$use_tmpl )
         $query->add_part( SQLP_FNAMES, $obj );
      elseif( $this->get_config(FC_SQL_TEMPLATE) )
         $query->add_part( SQLP_WHERETMPL, $obj ); // obj is sql-template
      else
      {
         if( $is_clause )
         {
            $parttype = ($this->get_config(FC_ADD_HAVING)) ? SQLP_HAVING : SQLP_WHERE;
            $query->add_part( $parttype, $obj ); // obj is SQL-clause
         }
         else
         {
            // obj is fieldnames
            $query->add_part( SQLP_FNAMES, $obj );
            $query->add_part( SQLP_WHERETMPL, $this->default_sql_template($obj) );
         }
      }

      return $query;
   }

   /*!
    * \brief Throws error, if FC_SQL_TEMPLATE or QuerySQL with SQLP_WHERETMPL is used, or there is no FNAMES-entry.
    * \internal
    * \see build_base_query()
    */
   function check_forbid_sql_template( $max_fnames = -1 )
   {
      if( $this->get_config(FC_SQL_TEMPLATE) )
         error('invalid_filter', "filter.check_forbid_sql_template.forbid_FC_SQL_TEMPLATE({$this->id})");
      if( is_a($this->dbfield, 'QuerySQL') )
      {
         if( $this->dbfield->has_part(SQLP_WHERETMPL) )
            error('invalid_filter', "filter.check_forbid_sql_template.QuerySQL_no_SQLP_WHERETMPL({$this->id})");

         $cnt_fnames = count($this->dbfield->get_parts(SQLP_FNAMES));
         if( $max_fnames >= 0 && $cnt_fnames > $max_fnames )
            error('invalid_filter', "filter.check_forbid_sql_template.QuerySQL.max_num.SQLP_FNAMES({$this->id},$max_fnames)");
         if( $cnt_fnames < 1 )
            error('invalid_filter', "filter.check_forbid_sql_template.QuerySQL.min1.SQLP_FNAMES({$this->id})");
      }
   }

   /*! \brief Adds additional non-empty element-name (without prefix); used for multi-element-filters. */
   function add_element_name( $name )
   {
      if( $name != '' )
         $this->elem_names[]= $name;
   }

   /*! \brief Returns string-array with element_names of multi-element-filter; names (without Prefix) for form-elements and URL-keys. */
   function get_element_names()
   {
      return $this->elem_names;
   }

   /*!
    * \brief Reads / Sets default-values.
    * \param conf string => sets default-value for main-value of filter,
    *             array( name => default-value ) => set default-values for multi-element-filters
    * \param arr_is_map set to false, if default-array (conf) should be
    *             treated as a map instead of scalar; default is true.
    */
   function read_defaults( $conf, $arr_is_map = true )
   {
      $this->defvalues = array();
      if( !is_array($conf) && (string)$conf == '' )
         return;

      if( $arr_is_map && is_array($conf) )
      {
         foreach( $conf as $k => $val )
            $this->defvalues["{$this->name}$k"] = $val;
      }
      else
         $this->defvalues[$this->name] = $conf;
   }

   /*! \brief Returns string|array default-value for specified filter-element-name; empty if none defined. */
   function get_default( $name )
   {
      if( isset($this->defvalues[$name]) )
         return $this->defvalues[$name];
      else
         return '';
   }

   /*!
    * \brief Returns default-value for filter-element-name if given value is null
    * \internal
    */
   function handle_default( $name, $val )
   {
      if( is_null($val) )
         $result = $this->get_default( $name );
      else
         $result = $val;
      return $result;
   }

   /*! \brief Returns string-representation of this filter (for debugging purposes). */
   function to_string()
   {
      return "Filter{$this->type}={ id=[{$this->id}], name=[{$this->name}], dbf=[{$this->dbfield}], "
         . "active=[{$this->active}], visible=[{$this->visible}], "
         . "defvalues={" . map_to_string($this->defvalues) . "}}, "
         . "value=[{$this->value}], errmsg=[{$this->errormsg}], warnmsg=[{$this->warnmsg}]"
         . "parsed p_start=[{$this->p_start}] p_end=[{$this->p_end}] p_value=[{$this->p_value}], "
         . "values={" . map_to_string($this->values) . "}}, "
         . "parser-flags=[{$this->parser_flags}], "
         . "query=[" . (is_null($this->query) ? '' : $this->query->to_string()) . "], "
         . "config={" . map_to_string($this->config) . "}}";
   }

   /*! \brief Resets filter-value back to the default-value if one set or else clear it. */
   function reset()
   {
      // reset all elements to default-value
      $this->values = array();
      foreach( $this->get_element_names() as $name )
         $this->init_parse( null, $name );
   }

   /*! \brief Returns main element-name for this filter. */
   function get_name()
   {
      return $this->name;
   }

   /*! \brief Returns error-message for last parsing of value; default is '' (no-error). */
   function errormsg()
   {
      return $this->errormsg;
   }

   /*! \brief Returns true, if error occured during parsing of filter-value. */
   function has_error()
   {
      return ( (string)$this->errormsg != '' );
   }

   /*!
    * \brief Returns warning-message for last parsing of value; default is '' (no-warning).
    *        At the moment solely set by MySQLMatch-filter.
    */
   function warnmsg()
   {
      return $this->warnmsg;
   }

   /*! \brief Returns true, if warning occured during parsing of filter-value. */
   function has_warn()
   {
      return ( (string)$this->warnmsg != '' );
   }

   /*!
    * \brief Returns value for specified optional element-name.
    * \param name if omitted, return value for main filter-value
    * note: name may be prefixed with PFX_FILTER if no FC_FNAME set
    */
   function get_value( $name = '' ) {
      if( $name == '' || $name === $this->name || $name === $this->id )
         return $this->value;
      else
         return @$this->values[$name];
   }

   /*! \brief Returns true, if value empty or null. */
   function is_empty() {
      return (is_null($this->value) || $this->value == '');
   }


   /*! \brief Returns true, if filter is declared to be static (always shown and used). */
   function is_static()
   {
      // filter-config FC_HIDE overrules global static-force
      if( FILTER_CONF_FORCE_STATIC )
         return !( (bool) @$this->config[FC_HIDE] );
      else
         return (bool) @$this->config[FC_STATIC];
   }

   /*! \brief Returns true, if filter is used to build query; always true for static filter. */
   function is_active()
   {
      return ( $this->is_static() ) ? true : $this->active;
   }

   /*! \brief Changes active-state of filter. */
   function set_active( $active )
   {
      $this->active = (bool)$active;
   }

   /*! \brief Toggles active-state of filter; active always set to true for static-filter. */
   function toggle_active()
   {
      if( $this->is_static() )
         $this->active = true;
      else
         $this->active = !$this->active;
   }

   /*! \brief Returns true, if filter visible or not (only used together with Table). */
   function is_visible()
   {
      return $this->visible;
   }

   /*! \brief Sets visible-state for filter (only used with Table). */
   function set_visible( $visible )
   {
      $this->visible = (bool)$visible;
   }

   /*! \brief Returns syntax-description for filter (maybe empty for some filters). */
   function get_syntax_description()
   {
      if( isset($this->syntax_descr) && !is_null($this->syntax_descr) )
      {
         $addinfo = $this->get_syntax_hint(FCV_SYNHINT_ADDINFO, " (%s)" );
         if( $this->syntax_descr == '' && $addinfo == '' )
            return '';

         $syntax = T_('Syntax#filter') . $this->get_syntax_help() . $addinfo;
         if( $this->syntax_descr != '' )
            $syntax .= ': ' . $this->syntax_descr;
         return $syntax;
      }
      else
         error('invalid_filter', "filter.get_syntax_description.miss_syntax_descr({$this->type})");
   }

   /*! \brief Returns optional syntax-hint-text for $confkey and format with specified sprintf-format. */
   function get_syntax_hint( $confkey, $format )
   {
      $arr = $this->get_config(FC_SYNTAX_HINT);
      if( is_array($arr) )
         return sprintf( $format, $arr[$confkey] );
      else
         return '';
   }

   /*! \brief Returns syntax-help-type: [help] */
   function get_syntax_help()
   {
      $help = $this->get_config(FC_SYNTAX_HELP);
      if( $help == '' )
         $help = $this->syntax_help;
      if( $help != '' )
         return "[$help]";
      else
         return '';
   }

   /*!
    * \brief Returns copy of query as QuerySQL-object for filter without
    *        parsing-error and with visible-state, if filter has a query built; null otherwise.
    * \param force if true, returns query also if filter in invisible-state.
    */
   function get_query( $force = false )
   {
      if( $this->errormsg )
         return NULL; // invalid input
      elseif( $force || $this->is_visible() )
         return $this->query;
      else
         return NULL;
   }

   /*!
    * \brief Returns encoded URL-string for filter prefixing vars with given prefix.
    * \param prefix URL-varnames are prefixed with that except FC_FNAME-config used on filter
    * \param arr_out return URL-vars with values additionally in this array
    * note: filter can also contain more than one element
    * note: multi-values are saved as array-value in arr_out and as 'field[]=..' in URL
    * note: Func for Filter-class
    */
   function get_url_parts( $prefix, &$arr_out )
   {
      $arr_url = array();

      foreach( $this->get_element_names() as $name )
      {
         $val = $this->get_value( $name );
         if( (string)$val != '' ) // val can be 0(!), filters need ''<>0, so don't use empty(val)
         {
            $fname = ($this->get_config(FC_FNAME)) ? $name : $prefix . $name;
            if( is_array($arr_out) )
               $arr_out[$fname] = $val;

            if( is_array($val) )
            {
               $akey = $fname . '%5b%5d='; //encoded []
               foreach( $val as $v )
                  $arr_url[]= $akey . urlencode($v);
            }
            else
               $arr_url[]= $fname . '=' . urlencode($val);
         }
      }

      return implode( URI_AMP, $arr_url );
   }


   // abstract interface (main-functions)

   /*!
    * \brief Parses and stores value for given element-name in a filter-specific
    *        way and builds query if not a multi-element-filter (\see build_query()).
    * Updates local vars: value, errormsg, p_start, p_end, p_value, p_wild, query (\see init_parse()).
    * signature: abstract bool success = parse_value( string name, string|array val )
    *
    * <p>Important Notes:
    * - if val is NULL, it's the initial-state of the filter which
    *   is used to reset all internal parser-values.
    *   \see SearchFilter->init()
    * - val can be of type string or array (for multi-values)
    */
   function parse_value( $name, $val )
   {
      // concrete filter needs to implement this abstract method
      error('invalid_filter', "filter.parse_value.miss_implementation(".get_class($this).",{$this->type})");
   }

   /*!
    * \brief Builds SQL-query from saved filter-object-vars (mostly used internal to handle multi-element-filters)
    * note: expects that filter has no error.
    */
   function build_query()
   {
      // empty function, only used for multi-filter-elements, called in SearchFilters->init()
   }

   /*!
    * \brief Returns HTML form-element(s) for filter to be used in a form.
    * \param prefix additional prefix used for form-element-names except if FC_FNAME-config used,
    *        suffixed with PFX_FILTER
    * \param attr optional array containing attributes used to build-up element
    */
   function get_input_element($prefix, $attr = array() )
   {
      // concrete filter needs to implement this abstract method
      error('invalid_filter', "filter.get_input_element.miss_implementation(".get_class($this).",{$this->type})");
   }


   // help functions

   /*!
    * \brief Sets and/or resets values to initialize filter for parsing.
    * \param val value to set
    * \param name name of filter-element to set value for: if empty or main-name,
    *             the parsing values (value, errormsg, p_start, p_end, p_value, query)
    *             are resetted; otherwise set value for name in local values-array
    *             without resetting normal vars (for supporting multi-element-filters)
    */
   function init_parse( $val, $name = '' )
   {
      if( $name == '' || $name === $this->name || $name == $this->id )
      {
         $this->value = $val;
         $this->errormsg = '';
         $this->warnmsg = '';

         $this->p_start = '';
         $this->p_end = '';
         $this->p_value = '';
         $this->p_flags = 0;
         $this->query = NULL;
         $this->match_terms = array();
      }
      elseif( $name != '' ) // multi-element-filter
      {
         $this->values[$name] = $val;
      }
   }

   /*! \brief Copies p_start, p_end, p_value, p_flags from specified object into current filter. */
   function copy_parsed( $obj )
   {
      $this->p_start = $obj->p_start;
      $this->p_end   = $obj->p_end;
      $this->p_value = $obj->p_value;
      $this->p_flags = $obj->p_flags;
   }

   /*! \brief Copies p_start, p_end, p_value, p_flags from current filter into given object. */
   function copy_parsed_to( &$obj )
   {
      $obj->p_start = $this->p_start;
      $obj->p_end   = $this->p_end;
      $obj->p_value = $this->p_value;
      $obj->p_flags = $this->p_flags;
   }

   /*! \brief Returns true, if specified flag in local p_flags. */
   function is_flag_set( $pflag )
   {
      if( $this->errormsg )
         return '';
      else
         return (bool)( $this->p_flags & $pflag );
   }

   /*!
    * \brief Internal help-func to parse numeric values.
    * \return false, if error occured during parsing; true for success.
    * \see NumericParser for supported modi and flags.
    */
   function parse_numeric( $name, $val, $tok_config, $flags = 0 ) {
      $this->init_parse($val, $name);

      $np = new NumericParser( $val, $tok_config, $flags );
      if( $np->errormsg() )
      {
         $this->errormsg = $np->errormsg();
         return false;
      }
      $this->copy_parsed($np);
      return true;
   }

   /*!
    * \brief Internal help-func to parse text values.
    * \return false, if error occured during parsing; true for success.
    * \see TextParser for supported modi and flags.
    */
   function parse_text( $name, $val, $tok_config, $flags = 0) {
      $this->init_parse($val, $name);

      $tp = new TextParser( $val, $tok_config, $flags );
      if( $tp->errormsg() )
      {
         $this->errormsg = $tp->errormsg();
         return false;
      }
      $this->copy_parsed($tp);

      if( count($tp->p_terms) > 0 )
         $this->match_terms = array_merge( $this->match_terms, $tp->p_terms );
      return true;
   }


   /*!
    * \brief Returns default sql-template 'tmplfield #OP #VAL' used to build base-query or
    *        return tmplfield if FC_SQL_TEMPLATE-config set for filter
    * \param tmplfield if null or omitted, use this->dbfield instead
    */
   function default_sql_template( $tmplfield = null)
   {
      if( is_null($tmplfield) )
         $tmplfield = $this->dbfield;
      return ( $this->get_config(FC_SQL_TEMPLATE) ) ? $tmplfield : "$tmplfield #OP #VAL";
   }

   /*!
    * \brief Builds QuerySQL for numeric-syntax (exact or range) from local vars
    *        p_start, p_end, p_value and used dbfield-specification.
    * \return null if parse-error occured.
    * note: no mysql-escaping done on values (therefore local vars should be checked to contain numerics).
    * note: exclusive comparators are used if PFLAG_EXCL_START|END set in p_flags.
    */
   function build_query_numeric( $query = null, $num_factor = 1 ) {
      if( $this->errormsg )
         return NULL;

      if( is_null($query) )
         $query = $this->build_base_query($this->dbfield, false);
      $sql_templ = $query->get_part(SQLP_WHERETMPL);
      $parttype = ($this->get_config(FC_ADD_HAVING)) ? SQLP_HAVING : SQLP_WHERE;

      // note: use quotes (like 'num') as SQL-values to avoid empty-value-error
      if( (string)$this->p_value != '' ) // exact search
         $query->add_part( $parttype,
            fill_sql_template( $sql_templ, '=', "'" . ( $num_factor * $this->p_value ) ."'" ) );
      else
      { // range search
         $arr = array(); // start/end
         if( (string)$this->p_start != '' )
         {
            $op = ($this->is_flag_set(PFLAG_EXCL_START) ? '>' : '>=');
            $arr[]= fill_sql_template( $sql_templ, $op, "'" . ( $num_factor * $this->p_start ) . "'" );
         }
         if( (string)$this->p_end != '' )
         {
            $op = ($this->is_flag_set(PFLAG_EXCL_END) ? '<' : '<=');
            $arr[]= fill_sql_template( $sql_templ, $op, "'" . ( $num_factor * $this->p_end ) . "'" );
         }

         if( count($arr) > 0 )
            $query->add_part( $parttype, implode(" AND ", $arr) );
         else
            $query = NULL;
      }

      return $query;
   }

   /*!
    * \brief Builds QuerySQL for textual-syntax (exact, range or wildcard) from local vars
    *        p_start, p_end, p_value and used dbfield-specification.
    * \brief Builds QuerySQL for textual-syntax from local vars p_start, p_end, p_value, p_flags
    *        and used dbfield-specification.
    * \return null if parse-error occured.
    * note: p_end is treated as exclusive-range-value.
    * note: SQL-LIKE only used if allow_wildcard is true and PFLAG_WILDCARD set in p_flags.
    */
   function build_query_text( $allow_wildcard = true )
   {
      if( $this->errormsg )
         return NULL;

      $query = $this->build_base_query($this->dbfield, false);
      $sql_templ = $query->get_part(SQLP_WHERETMPL);
      $parttype = ($this->get_config(FC_ADD_HAVING)) ? SQLP_HAVING : SQLP_WHERE;

      if( (string)$this->p_value != '' )
      { // exact or wildcard
         $v = mysql_addslashes($this->p_value);
         if( $allow_wildcard && $this->is_flag_set(PFLAG_WILDCARD) ) // SQL-wildcard search
            $query->add_part( $parttype, fill_sql_template( $sql_templ, 'LIKE', "'$v'" ) );
         else // exact search
            $query->add_part( $parttype, fill_sql_template( $sql_templ, '=', "'$v'" ) );
      }
      else
      { // range-search
         $arr = array();
         if( (string)$this->p_start != '' )
            $arr[]= fill_sql_template( $sql_templ, '>=', "'" . mysql_addslashes($this->p_start) . "'" );
         if( (string)$this->p_end != '' )
            $arr[]= fill_sql_template( $sql_templ, '<', "'" . mysql_addslashes($this->p_end) . "'" );
         if( count($arr) > 0 )
            $query->add_part( $parttype, implode(" AND ", $arr) );
         else
            $query = NULL;
      }

      return $query;
   }


   /*!
    * \brief Builds generic text-input form-element using passed arguments with
    *        optional hover-help (title).
    * param maxlen may be 0 (no maxlen used)
    * note: element-name is built from 'prefix name' or FC_FNAME-config if set
    */
   function build_generic_input_text_elem( $prefix, $name, $value, $title = '', $maxlen = 0, $size = '' )
   {
      $fname = ($this->get_config(FC_FNAME)) ? $name : $prefix . $name;
      $elem = "<input";
      $elem .= " name=\"{$fname}\"";
      $elem .= " type=\"text\"";
      if( $maxlen > 0)
         $elem .= " maxlength=\"{$maxlen}\"";
      if( $size )
         $elem .= " size=\"{$size}\"";
      $elem .= " value=" . attb_quote($value);
      if( $title != '' )
         $elem .= " title=" . attb_quote($title);
      $elem .= ">";
      return $elem;
   }

   /*!
    * \brief Help-function to build filter-typical text-input form-element
    *        including syntax-description.
    * \see build_generic_input_text_elem()
    */
   function build_input_text_elem( $prefix, $attr = array(), $maxlen = 0, $size = '' )
   {
      return $this->build_generic_input_text_elem( $prefix, $this->name, $this->value,
         $this->get_syntax_description(), $maxlen, $size);
   }

   /*!
    * \brief Builds generic selectbox form-element using passed arguments.
    * param index_start_keys:
    *     if numeric -> used as start-index using for option-value and description given in values-array;
    *     otherwise array( option-value => description ), values is null (ignored)
    * param size number of lines shown (default is 1)
    * note: builds multi-var selectbox if FC_MULTIPLE-config set on filter.
    * note: element-name is built from 'prefix name' or FC_FNAME-config if set
    */
   function build_generic_selectbox_elem( $prefix, $name, $value, $index_start_keys, $values = null, $size = 1 )
   {
      $fname = ($this->get_config(FC_FNAME)) ? $name : ($prefix . $name);
      if( !is_numeric($size) )
         $size = 1;
      $is_multi = $this->get_config(FC_MULTIPLE);

      $elem = "\n <select name=\"$fname";
      $elem .= ( $is_multi ) ? '[]" multiple' : '"';
      $elem .= " size=\"$size\">";

      if( is_array($index_start_keys) ) // key-value-array (not multi)
      {
         foreach( $index_start_keys as $optval => $descr )
         {
            $selected = ($value == $optval) ? ' selected' : '';
            $descr = basic_safe($descr); //basic_safe() because inside <option></option>
            $elem .= "\n  <option value=\"$optval\"$selected>$descr</option>";
         }
      }
      elseif( is_numeric($index_start_keys) )
      {
         if( $is_multi && !is_array($value) )
            $value = array();
         for( $i=0; $i < count($values); $i++)
         {
            $optval = $index_start_keys + $i;
            if( $is_multi )
               $selected = (in_array($optval, $value)) ? ' selected' : ''; # optval(idx) found in val-arr
            else
               $selected = ($value == $optval) ? ' selected' : '';
            $descr = basic_safe($values[$i]); //basic_safe() because inside <option></option>
            $elem .= "\n  <option value=\"$optval\"$selected>$descr</option>";
         }
      }
      else
         error('invalid_filter', "filter.build_generic_selectbox_elem.invalid_arg.index_start_keys($index_start_keys)");

      $elem .= "\n </select>";
      return $elem;
   } //build_generic_selectbox_elem

   /*!
    * \brief Help-function to build filter-typical selectbox form-element.
    * \see build_generic_selectbox_elem()
    */
   function build_selectbox_elem( $prefix, $index_start_keys, $values = null )
   {
      $size = @$this->config[FC_SIZE];
      if( !is_numeric($size) )
         $size = 1;

      return $this->build_generic_selectbox_elem(
         $prefix, $this->name, $this->value, $index_start_keys, $values, $size);
   }

   /*!
    * \brief Builds generic checkbox form-element using passed arguments with
    *        optional hover-help (title).
    * note: element-name is built from 'prefix name' or FC_FNAME-config if set
    */
   function build_generic_checkbox_elem( $prefix, $name, $value, $text, $title='' )
   {
      $fname = ($this->get_config(FC_FNAME)) ? $name : ($prefix . $name);

      $elem = "<input type=\"checkbox\" name=\"$fname\" value=\"1\"";
      if( !empty($title) )
         $elem .= " title=" . attb_quote($title);
      if( $value )
         $elem .= " checked";
      $elem .= ">";
      $elem .= $text;
      return $elem;
   }

   /*!
    * \brief Help-function to build filter-typical checkbox form-element.
    * \see build_generic_checkbox_elem()
    * note: text is also used for alt-attribute
    */
   function build_checkbox_elem( $prefix, $text )
   {
      return $this->build_generic_checkbox_elem( $prefix, $this->name, $this->value, $text );
   }

} // end of 'Filter'


// --------------------------------------------------------------------------
//                            F I L T E R S
// --------------------------------------------------------------------------


 /*!
  * \class FilterNumeric
  *
  * \brief Filter for (positive) numerics (float or integer) allowing exact
  *        value or range-values; SearchFilter-Type: Numeric.
  * <p>GUI: text input-box
  *
  * <p>Allowed Syntax:
  *    "314" = exact value
  *    "-31" = range-syntax, search value ending with 31
  *    "47-" = range-syntax, search value beginning with 47
  *    "3-7" = range-syntax, search value between 3 and 7
  *
  * <p>supported common config (restrictions or defaults):
  *    FC_FNAME, FC_STATIC, FC_GROUP_SQL_OR, FC_SQL_TEMPLATE, FC_ADD_HAVING,
  *    FC_NO_RANGE, FC_DEFAULT (numeric value), FC_SIZE (5), FC_MAXLEN,
  *    FC_QUOTETYPE, FC_SYNTAX_HINT, FC_SYNTAX_HELP, FC_HIDE
  *
  * <p>supported filter-specific config:
  *    FC_NUM_FACTOR
  */
class FilterNumeric extends Filter
{
   /*! \brief factor to multiplay value before using in query. */
   var $num_factor;

   /*! \brief Constructs Numeric-Filter. */
   function FilterNumeric($name, $dbfield, $config)
   {
      static $_default_config = array( FC_SIZE => 5 );
      parent::Filter($name, $dbfield, $_default_config, $config);
      $this->type = 'Numeric';
      $this->tok_config = $this->create_TokenizerConfig();
      $this->syntax_help = T_('NUM#filterhelp');

      $factor = $this->num_factor = $this->get_config(FC_NUM_FACTOR, 1);

      if( $this->get_config(FC_NO_RANGE) )
      {
         $this->parser_flags |= TEXTPARSER_FORBID_RANGE;
         $this->syntax_descr = '314';
      }
      else
      {
         $s = $this->tok_config->sep;
         $this->syntax_descr = "3, {$s}14, 1{$s}5, 9{$s}"; // pi
      }
   }

   /*! \brief Parses numeric-value (float or integer) using NumericParser. */
   function parse_value( $name, $val )
   {
      $val = $this->handle_default( $name, $val );
      if( !$this->parse_numeric( $name, $val, $this->tok_config, $this->parser_flags ) )
         return false;

      $this->query = $this->build_query_numeric( null, $this->num_factor );
      return true;
   }

   /*! \brief Returns input-text form-element. */
   function get_input_element($prefix, $attr = array() )
   {
      return $this->build_input_text_elem(
         $prefix, $attr, @$this->config[FC_MAXLEN], @$this->config[FC_SIZE] );
   }
} // end of 'FilterNumeric'



 /*!
  * \class FilterText
  * \brief Filter for texts allowing exact value, range value and using
  *        wildcard; SearchFilter-Type: Text.
  * <p>GUI: text input-box
  * <p>Additional interface functions:
  * - \see get_rx_terms() to return array with search-terms as regex.
  *
  * note: special quoting used according to MATCH-syntax of mysql,
  *
  * <p>Allowed Syntax:
  *    "foo"  = exact value
  *    "-bar" = range-syntax, search value ending at bar
  *    "baz-" = range-syntax, search value beginning with baz until end of data-entries
  *    "a-e"  = range-syntax, search value between 'a' and 'e'
  *    "boo*" = wildcard, search for values starting with 'boo' as first 3 chars
  *    "*boo" = wildcard, search for values ending with 'boo' as last 3 chars
  *             (need filter-config of FC_START_WILD=3 to be allowed)
  *
  * <p>supported common config (restrictions or defaults):
  *    FC_FNAME, FC_STATIC, FC_GROUP_SQL_OR, FC_SIZE (8), FC_MAXLEN,
  *    FC_NO_RANGE, FC_DEFAULT (text-value), FC_SQL_TEMPLATE, FC_ADD_HAVING,
  *    FC_QUOTETYPE, FC_SYNTAX_HINT, FC_SYNTAX_HELP, FC_HIDE
  *
  * <p>supported filter-specific config:
  *    FC_NO_WILD = if true, wildcard is forbidden (and treated as normal char)
  *    FC_START_WILD = minimum number of consecutive non-wildcard-chars used in text-value
  *         (use STARTWILD_OPTMINCHARS as value (=4) -> that allows mysql to do some optimizations;
  *         that's because a search with a starting wildcard can't use an database-index)
  *    FC_SUBSTRING = if true, substring-search with text using implicit wildcard at start
  *         and end of string, needs FC_START_WILD to be set (default STARTWILD_OPTMINCHARS).
  *         Outrules range-syntax (uses implicit FC_NO_RANGE).
  */
class FilterText extends Filter
{
   /*! \brief Constructs Text-Filter. */
   function FilterText($name, $dbfield, $config)
   {
      static $_default_config = array( FC_SIZE => 8 );
      parent::Filter($name, $dbfield, $_default_config, $config);
      $this->type = 'Text';
      $this->tok_config = $this->create_TokenizerConfig();
      $this->syntax_help = T_('TEXT#filterhelp');

      $arr_syntax = array();
      if( $this->get_config(FC_NO_RANGE) || $this->get_config(FC_SUBSTRING) )
      { // substring forcing no-range
         $arr_syntax[]= 'foo';
         $this->parser_flags |= TEXTPARSER_FORBID_RANGE;
      }
      else
      {
         $s = $this->tok_config->sep;
         $arr_syntax[]= "foo, {$s}bar, baz{$s}, boo{$s}far";
      }

      $allow_wild = !$this->get_config(FC_NO_WILD);
      if( $allow_wild )
         $arr_syntax[]= 'fa*z*';
      else
         $this->parser_flags |= TEXTPARSER_FORBID_WILD;

      $minchars = (int) $this->get_config(FC_START_WILD);
      if( $allow_wild && $minchars )
      {
         $arr_syntax[]= "*goo" . ($minchars > 1 ? " ($minchars)" : '');
         $this->parser_flags |= TEXTPARSER_ALLOW_START_WILD;
         $this->tok_config->add_config( TEXTPARSER_CONF_STARTWILD_MINCHARS, $minchars );
      }

      $this->syntax_descr = implode(', ', $arr_syntax);
      if( $this->get_config(FC_SUBSTRING) )
      {
         if( !$minchars )
            error('invalid_filter', "filter.FilterText.bad_config.FC_SUBSTRING_miss_FC_START_WILD");
         $this->parser_flags |= TEXTPARSER_IMPLICIT_WILD;
         $this->syntax_descr = '['. T_('substring#filter') . '] ' . $this->syntax_descr;
      }
   }

   /*! \brief Parses text-value using TextParser with default TokenizerConfig. */
   function parse_value( $name, $val )
   {
      $val = $this->handle_default( $name, $val );
      if( !$this->parse_text( $name, $val, $this->tok_config, $this->parser_flags ) )
         return false;

      $this->query = $this->build_query_text();
      return true;
   }

   /*! \brief Returns input-text form-element. */
   function get_input_element($prefix, $attr = array() )
   {
      return $this->build_input_text_elem(
         $prefix, $attr, @$this->config[FC_MAXLEN], @$this->config[FC_SIZE] );
   }
} // end of 'FilterText'



 /*!
  * \class FilterRating
  * \brief Filter for a players rating allowing allowing exact value and
  *        range value; SearchFilter-Type: Rating.
  * <p>GUI: text input-box
  *
  * <p>Allowed Syntax:
  *    note: rank-specification according to read_rating()-func
  *    "7k" = representing rating for 7 kyu within range: 7k = 7k(-50%) .. 7k(+49%)
  *    "3d, 3dan, 3 dan"      = alternatives for rank of 3 dan
  *    "7k, 7kyu, 7 k, 7 gup" = alternatives for rank of 7k
  *    range-syntax: like for \see FilterText
  *    "5k (+45%), 5k 45%, 5k (-15%), 5k-15%" = represents specific rating
  *
  * <p>Note:
  * - minus-char in '(-30%)' need not to be escaped in "5k (-30%) - 1d (+10%)"i
  *   to allow for range-syntax (that's catched by the filter, however quoting
  *   is possible too writing "5k (\-30%)" or equivalent quoting).
  *
  * <p>supported common config (restrictions or defaults):
  *    FC_FNAME, FC_STATIC, FC_GROUP_SQL_OR, FC_SIZE (6), FC_MAXLEN,
  *    FC_NO_RANGE, FC_DEFAULT (valid rank-text), FC_SQL_TEMPLATE,
  *    FC_ADD_HAVING, FC_QUOTETYPE, FC_SYNTAX_HINT, FC_SYNTAX_HELP, FC_HIDE
  */
class FilterRating extends Filter
{
   /*! \brief Constructs Rating-Filter. */
   function FilterRating($name, $dbfield, $config)
   {
      static $_default_config = array( FC_SIZE => 8 );
      parent::Filter($name, $dbfield, $_default_config, $config);
      $this->type = 'Rating';
      $this->tok_config = $this->create_TokenizerConfig();
      $this->syntax_help = T_('RATING#filterhelp');

      $arr_syntax = array();
      if( $this->get_config(FC_NO_RANGE) )
      {
         $arr_syntax[]= "2d, 8k (+28%), 4k-59%"; // e
         $this->parser_flags |= TEXTPARSER_FORBID_RANGE;
      }
      else
      {
         $s = $this->tok_config->sep;
         $arr_syntax[]= "2d, {$s}7k, 18k{$s}, 28k{$s}1d; 8k (+28%), 4k-59%"; // e

         // regex considering -59% not as range-separator
         $this->tok_config->add_config( TEXTPARSER_CONF_RX_NO_SEP, '-\d+%' );
      }
      $this->syntax_descr = implode(', ', $arr_syntax);
   }

   /*!
    * \brief Returns integer ELO-rating converted from rank-spec (7k, 3d).
    * note: sets errormsg and resets filter-values on parse-error
    */
   function convert_rank($rank)
   {
      $rating = read_rating($rank);
      if( is_null($rating) || $rating < MIN_RATING || $rating > OUT_OF_RATING )
      { // min=30k (-900), max=9999 (ominous limit)
         $this->init_parse($this->value); // reset all parsed-vars
         $this->errormsg = "[$rank] " . T_('invalid rank');
      }
      return $rating;
   }

   /*! \brief Parses rank-value using modified TextParser. */
   function parse_value( $name, $val )
   {
      $val = $this->handle_default( $name, $val );
      $this->init_parse($val);

      // need textparser for conversion below
      $tp = new TextParser( $val, $this->tok_config,
            $this->parser_flags | TEXTPARSER_FORBID_WILD | TEXTPARSER_END_INCL );
      if( $tp->errormsg() )
      {
         $this->errormsg = $tp->errormsg();
         return false;
      }

      // check kyu/dan-ranks (string) & convert into rating (int)
      $orig_pval   = $tp->p_value;
      $orig_pstart = $tp->p_start;
      $orig_pend   = $tp->p_end;
      if( (string)$tp->p_start != '' )
      {
         $rat_start = $this->convert_rank($tp->p_start);
         if( $this->errormsg )
            return false;
         $tp->p_start = $rat_start;
      }
      if( (string)$tp->p_end != '' )
      {
         $rat_end = $this->convert_rank($tp->p_end);
         if( $this->errormsg )
            return false;
         $tp->p_end = $rat_end;
      }
      if( (string)$tp->p_value != '' )
      {
         $rat_value = $this->convert_rank($tp->p_value);
         if( $this->errormsg )
            return false;
         $tp->p_value = $rat_value;
      }

      if( $tp->handle_reverse_range(true) ) // forced swap
         swap( $orig_pstart, $orig_pend ); // swap orig start & end too
      $this->copy_parsed($tp);

      // adjust rank-range and used operations
      if( (string)$this->p_value != '' )
      {
         // replace single value by start/end-search if not an accurate rank
         $this->p_start = $this->p_end = $this->p_value;
         $this->p_value = '';
         $this->adjust_op_rating( true,  $orig_pval );
         $this->adjust_op_rating( false, $orig_pval );
      }
      else
      {
         $this->adjust_op_rating( true,  $orig_pstart );
         $this->adjust_op_rating( false, $orig_pend );
      }

      $this->query = $this->build_query_numeric();
      return true;
   }

   /*! Sets p_start or p_end and according p_flags to adjust rating and operation for range-filtering. */
   function adjust_op_rating( $is_start=true, $orig_val )
   {
      // something there to adjust?
      if( $is_start && (string)$this->p_start == '' )
         return;
      if( !$is_start && (string)$this->p_end == '' )
         return;

      // full or percentaged rank?
      $has_percent = !( strpos($orig_val, '%') === false );
      // negative percentage?
      $has_negperc = $has_percent && !( strpos($orig_val, '-') === false );

      if( $has_percent )
         $adjust_range = 0.5; // percentaged rank: 9k+44% ->  >= 9k(+44%) and < 9k(+45%)
      else
         $adjust_range = 50;  // full rank: 9k ->  >= 9k(-50%) and < 9k(+50%)

      // adjust (flags depends on sign because of rounding effects)
      if( $is_start )
      {
         $perc_is0 = false; // percentage is [+/-]0%
         if( $this->p_start % 100 == 0 ) // handle '-0%' as non-negative
         {
            $has_negperc = false;
            $perc_is0 = true;
         }

         if( $has_negperc && abs($this->p_start) % 100 == 50 ) // -50%
            $adjust_range = 0;
         $this->p_start -= $adjust_range; // -50 | -0.5
         if( $this->p_start < MIN_RATING )
            $this->p_start = MIN_RATING;

         $opexcl = true; // '>'-comparison, false -> '>='
         if( !$perc_is0 && !$has_negperc ) // perc > 0% (not 0 and no '-')
            $opexcl = false;
         if( $this->p_start <= 0 )
         {
            if( !$has_percent )
               $opexcl = true;
         }
         else
         {
            if( $adjust_range == 0 )
               $opexcl = false;
            if( !$has_percent )
               $opexcl = false;
         }
         if( $this->p_start <= MIN_RATING )
            $opexcl = false;
         if( $opexcl )
            $this->p_flags |= PFLAG_EXCL_START;

      }
      else // is-end
      {
         $perc_is0 = false; // percentage is [+/-]0%
         if( $this->p_end % 100 == 0 ) // handle '-0%' as non-negative
         {
            $has_negperc = false;
            $perc_is0 = true;
         }

         if( !$has_negperc && abs($this->p_end) % 100 == 50 ) // +50%
            $adjust_range = 0;
         $this->p_end += $adjust_range; // +50 | +0.5
         if( $this->p_end < MIN_RATING )
            $this->p_end = MIN_RATING;

         $opexcl = false; // '<='-comparison, true -> '<'
         if( !$has_negperc ) // perc >= 0% (no '-')
            $opexcl = true;
         if( $this->p_end < 0 )
         {
            if( $adjust_range == 0 )
               $opexcl = false;
            if( !$has_percent )
               $opexcl = false;
         }
         else
         {
            if( !$has_percent )
               $opexcl = true;
         }
         if( $this->p_end <= MIN_RATING )
            $opexcl = false;
         if( $opexcl )
            $this->p_flags |= PFLAG_EXCL_END;
      }
   }

   /*! \brief Returns input-text form-element. */
   function get_input_element($prefix, $attr = array() )
   {
      return $this->build_input_text_elem(
         $prefix, $attr, @$this->config[FC_MAXLEN], @$this->config[FC_SIZE] );
   }
} // end of 'FilterRating'



 /*!
  * \class FilterDate
  * \brief Filter for absolute dates (and timestamp) allowing exact value and
  *        range value; SearchFilter-Type: Date.
  * <p>Note: db-field must be a SQL-date-field, * not a UNIX_TIMESTAMP(SQL-date-field).
  *
  * <p>GUI: text input-box
  *
  * <p>Allowed Syntax:
  *    note: for syntax also see class \see DateParser:
  *    "YYYY", "YYYYMM", "YYYYMMDD" - year with optional month and day-specs
  *    "YYYYMMDD hh", "YYYYMMDD hhmm" - year, month, day with optional hour and minute-specs
  *    ':' can be used at any place to make date more readable
  *    range-syntax: like for \see FilterText
  *
  * <p>supported common config (restrictions or defaults):
  *    FC_FNAME, FC_STATIC, FC_GROUP_SQL_OR, FC_SIZE (16), FC_MAXLEN,
  *    FC_NO_RANGE, FC_DEFAULT (date-text), FC_SQL_TEMPLATE, FC_ADD_HAVING,
  *    FC_QUOTETYPE, FC_SYNTAX_HINT, FC_SYNTAX_HELP, FC_HIDE
  */
class FilterDate extends Filter
{
   /*! \brief Constructs Date-Filter. */
   function FilterDate($name, $dbfield, $config)
   {
      static $_default_config = array( FC_SIZE => 16 );
      parent::Filter($name, $dbfield, $_default_config, $config);
      $this->type = 'Date';
      $this->tok_config = $this->create_TokenizerConfig();
      $this->parser_flags |= TEXTPARSER_FORBID_WILD | TEXTPARSER_END_INCL;
      $this->syntax_help = T_('DATE#filterhelp');

      if( $this->get_config(FC_NO_RANGE) )
      {
         $this->parser_flags |= TEXTPARSER_FORBID_RANGE;
         $this->syntax_descr = "d=Y(M(D(h(m(s";
      }
      else
      {
         $s = $this->tok_config->sep;
         $this->syntax_descr = "d, {$s}d, d{$s}, d1{$s}d2; d=Y(M(D(h(m(s";
      }
   }

   /*! \brief Parses date-text-value using TextParser. */
   function parse_value( $name, $val )
   {
      $val = $this->handle_default( $name, $val );

      // use text-parser to parse range-syntax
      if( !$this->parse_text( $name, $val, $this->tok_config, $this->parser_flags ) )
         return false;

      // check & completes dates
      if( (string)$this->p_value != '' )
      { // handle like range (start-end) to allow '1999' searching for 1999 <= x < 2000
         $this->p_start = $this->p_value;
         $this->p_end   = $this->p_value;
         $this->p_value = '';
      }

      $dp_start = null;
      $arr_err = array();
      if( (string)$this->p_start != '' )
      {
         $dp_start = new DateParser($this->p_start, RANGE_START);
         if( $dp_start->errormsg() )
            $arr_err[]= $dp_start->errormsg;
         else
            $this->p_start = $dp_start->get_completed_date();
      }

      if( (string)$this->p_end != '' )
      {
         $dp_end = new DateParser($this->p_end, RANGE_END);
         if( $dp_end->errormsg() )
            $arr_err[]= $dp_end->errormsg;
         else
            $this->p_end = $dp_end->get_completed_date();
      }

      // handling error (start + end date)
      if( count($arr_err) > 0 )
      {
         $this->init_parse($this->value); // reset all parsed-vars
         $this->errormsg = implode( "; ", array_unique($arr_err) );
         return false;
      }

      // handle reverse-range (only if both start+end used)
      if( (string)$this->p_end != '' && is_object($dp_start) )
      {
         if( $dp_start->rawdate > $dp_end->rawdate )
         {
            // swap needed and re-parsing (because date adjusted for RANGE_END)
            //   but skip already passed checks
            $orig_start = $dp_start->origdate;
            $orig_end   = $dp_end->origdate;
            $dp_start = new DateParser($orig_end,   RANGE_START);
            $dp_end   = new DateParser($orig_start, RANGE_END);
            $this->p_start = $dp_start->get_completed_date();
            $this->p_end   = $dp_end->get_completed_date();
         }
      }

      $this->query = $this->build_query_text();
      return true;
   }

   /*! \brief Returns input-text form-element. */
   function get_input_element($prefix, $attr = array() )
   {
      return $this->build_input_text_elem(
         $prefix, $attr, @$this->config[FC_MAXLEN], @$this->config[FC_SIZE] );
   }
} // end of 'FilterDate'



 /*!
  * \class FilterRelativeDate
  * \brief Filter for relative and absolute dates allowing exact value and
  *        range value for absolute date-spec; SearchFilter-Type: RelativeDate.
  * <p>Note: db-field must be a SQL-date-field, * not a UNIX_TIMESTAMP(SQL-date-field).
  *
  * <p>GUI: text input-box +
  *         selectbox to choose for absolute-mode or time-unit for relative-date
  *
  * note: If only absolute-date filter wanted, use FilterDate instead.
  *
  * <p>Allowed Syntax:
  *    for absolute date-mode: \see FilterDate (must select 'absolute'),
  *          need FRDTU_ABS-bit set in filter-config FC_TIME_UNITS
  *    for relative date-mode:
  *      "30" or "<30" = search value from 30 days (or selected time-unit) ago until today
  *      ">30"         = search value before 30 days (or selected time-unit)
  *      choices of time-units must be specified with FC_TIME_UNITS-config
  *
  * <p>supported common config (restrictions or defaults):
  *    FC_FNAME, FC_STATIC, FC_GROUP_SQL_OR, FC_SIZE (4),
  *    FC_SQL_TEMPLATE, FC_ADD_HAVING, FC_SYNTAX_HINT, FC_SYNTAX_HELP, FC_HIDE
  *
  * <p>supported filter-specific config:
  *    FC_TIME_UNITS - bitmask specifying choice in selectbox for time-units; values: FRDTU_...
  *    FC_DEFAULT - date-text-value if scalar, or
  *                 array( '' => date-text-value, 'tu' => FRDTU_time-unit-flag )
  */

// time-unit-flags (used for bitmask) configurable through FC_TIME_UNITS
define('FRDTU_ABS',   0x0001); // include absolute-date using FilterDate-syntax
define('FRDTU_YEAR',  0x0002);
define('FRDTU_MONTH', 0x0004);
define('FRDTU_WEEK',  0x0008);
define('FRDTU_DAY',   0x0010);
define('FRDTU_HOUR',  0x0020);
define('FRDTU_MIN',   0x0040);
define('FRDTU_YMWD',  FRDTU_YEAR | FRDTU_MONTH | FRDTU_WEEK | FRDTU_DAY ); // time-unit: year/month/week/day
define('FRDTU_DHM',   FRDTU_DAY | FRDTU_HOUR | FRDTU_MIN); // time-unit: day/hour/min
define('FRDTU_ALL_REL', FRDTU_YEAR | FRDTU_MONTH | FRDTU_WEEK | FRDTU_DAY | FRDTU_HOUR | FRDTU_MIN); // all above relative time-units
define('FRDTU_ALL_ABS', FRDTU_ALL_REL | FRDTU_ABS ); // all time-units (relative + absolute)

// array for SQL-interval-specification
$FRDTU_interval_sql = array(
   FRDTU_YEAR  => 'YEAR',
   FRDTU_MONTH => 'MONTH',
   # no week-date-interval until mysql5 -> convert to days
   #FRDTU_WEEK  => 'WEEK', # supported since mysql5.0.0
   FRDTU_WEEK  => '*7 DAY', //Note: need appropriate parenthesis in formulas
   FRDTU_DAY   => 'DAY',
   FRDTU_HOUR  => 'HOUR',
   FRDTU_MIN   => 'MINUTE',
);

// some internals
define('FRD_RANGE_ABS',   1); // absolute-values
define('FRD_RANGE_START', 2); // start-range
define('FRD_RANGE_END',   3); // end-range

class FilterRelativeDate extends Filter
{
   /*! \brief array of used time-units. */
   var $time_units;
   /*! \brief element-name for time-units selectbox. */
   var $elem_tu;
   /*! \brief array with choices for select-box (excerpt from FRDTU_choices). */
   var $choices_tu;
   /*! \brief FilterDate for absolute-choice (null if FRDTU_ABS not used). */
   var $filterdate;

   /*! \brief Indicating used range-mode; values: FRD_RANGE_ABS|START(default)|END */
   var $range_mode;

   /*! \brief Constructs RelativeDate-Filter. */
   function FilterRelativeDate($name, $dbfield, $config)
   {
      static $_default_config = array( FC_SIZE => 4, FC_TIME_UNITS => FRDTU_ALL_REL );

      parent::Filter($name, $dbfield, $_default_config, $config);
      $this->type = 'RelativeDate';
      $this->syntax_descr = "30 (= <30), >30";
      $this->syntax_help = T_('RELDATE#filterhelp');

      // check & setup time-units
      $this->time_units = array();
      $this->choices_tu = array();
      $fc_time_units = $this->get_config(FC_TIME_UNITS);
      $FRDTU_choices = FilterRelativeDate::getTimeUnitText();
      foreach( $FRDTU_choices as $tu => $descr )
      {
         if( $fc_time_units & $tu )
         {
            if( $tu != FRDTU_ABS ) // skip for absolute
               $this->time_units[]= $tu;
            $this->choices_tu[$tu] = $descr;
         }
      }
      if( count($this->time_units) == 0 )
         error('invalid_filter', "filter.FilterRelativeDate.miss_time_unit");

      // absolute filter
      $this->filterdate = NULL;
      if( $fc_time_units & FRDTU_ABS )
      {
         $this->filterdate = new FilterDate( $name, $dbfield, $config );
         $this->syntax_descr .= '; ' . T_('absolute#filter') . ': ' . $this->filterdate->syntax_descr;
      }

      $this->elem_tu = "{$this->name}tu";
      $this->add_element_name( $this->elem_tu );

      // set defaults
      $this->range_mode = 0;
      $this->set_value_defaults( $this->elem_tu );
   }

   /*!
    * \brief Sets default for time-unit-selectbox.
    * \internal
    */
   function set_value_defaults( $name )
   {
      if( $name === $this->elem_tu )
      {
         $defidx = ( is_null($this->filterdate) ) ? 0 : 1;
         $this->values[$this->elem_tu] =
            ( $this->get_config(FC_TIME_UNITS) & FRDTU_DAY ) ? FRDTU_DAY : $this->time_units[$defidx];
      }
   }

   /*!
    * \brief Overloading method to return element-names in reverse order,
    *        which is needed so that parse_value-func has selectbox-element
    *        set before main-element is parsed.
    * \internal
    */
   function get_element_names()
   {
      return array_reverse( $this->elem_names );
   }

   /*! \brief Parses date-text-value (absolute or relative) using FilterDate for absolute date. */
   function parse_value( $name, $val )
   {
      $val = $this->handle_default( $name, $val );
      $this->init_parse($val, $name); // if elem, value for elem_tu saved

      if( $name === $this->name )
      {
         $v = preg_replace( "/\\s+/", "", $val); // remove all spaces
         if( $v == '' )
            return true;

         // note: expecting elem_tu parsed first (reached by overloading get_element_names-method)
         if( $this->values[$this->elem_tu] == FRDTU_ABS )
         { // absolute
            if( is_null($this->filterdate) )
               error('invalid_filter', "filter.FilterRelativeDate.bad_config_absolute_date({$this->id})");

            // parse val using FilterDate-syntax
            $this->range_mode = FRD_RANGE_ABS;

            if( !$this->filterdate->parse_value( $name, $val ) )
            {
               $this->errormsg = $this->filterdate->errormsg;
               return false;
            }
         }
         else
         { // relative
            // parse val: 30 <30 >30
            $this->range_mode = FRD_RANGE_START;

            if( substr($v, 0, 1) == ">" ) // >30
            {
               $v = substr($v, 1);
               $this->range_mode = FRD_RANGE_END;
            }
            elseif( substr($v, 0, 1) == "<" ) // <30 (same as '30')
            {
               $v = substr($v, 1);
            }

            if( !is_numeric($v) )
            {
               $this->errormsg = "[$v] " . T_('not numeric');
               if( !is_null($this->filterdate) )
                  $this->errormsg .= ' (' . T_('maybe you want to choose absolute#reldate') . ')';
               return false;
            }
         }

         $this->p_value = $v; // indicator of correctly parsed value
      }
      elseif( $name === $this->elem_tu )
      {
         // set default (days) or else first list-item
         if( $val == '' )
            $this->values[$this->elem_tu] =
               ( $this->get_config(FC_TIME_UNITS) & FRDTU_DAY ) ? FRDTU_DAY : $this->time_units[0];
      }
      else
         error('invalid_filter', "filter.RelativeDate.parse_value.invalid_arg.name($name,$val)");

      return true;
   }

   /*!
    * \brief Builds query for multi-element filter.
    * expecting: p_value set or unset, range_mode set, values[elem_tu] with time-unit-choice
    */
   function build_query()
   {
      // check for parsed-value
      if( $this->p_value == '' )
         return;

      if( $this->values[$this->elem_tu] == FRDTU_ABS )
      { // parse absolute date
         $query = $this->filterdate->query;
      }
      else
      { // parse relative date
         global $FRDTU_interval_sql;

         // handle weeks (no week-date-interval until mysql5) -> use days
         $tu = $this->values[$this->elem_tu];

         // build SQL
         global $NOW;
         $query = $this->build_base_query($this->dbfield, false);
         $sql_templ = $query->get_part(SQLP_WHERETMPL);
         $sql_op = ( $this->range_mode == FRD_RANGE_END ) ? "<=" : ">=";
         $sql_date = "FROM_UNIXTIME($NOW) - INTERVAL (" . $this->p_value . "){$FRDTU_interval_sql[$tu]}";
         $parttype = ($this->get_config(FC_ADD_HAVING)) ? SQLP_HAVING : SQLP_WHERE;
         $query->add_part( $parttype, fill_sql_template( $sql_templ, $sql_op, $sql_date ) );
      }

      $this->query = $query;
   }

   /*!
    * \brief Returns input-text and select-box form-element.
    * note: if only one time-unit, replace select-box with static text.
    */
   function get_input_element($prefix, $attr = array() )
   {
      // input-text for number of (time-units)
      $r = $this->build_input_text_elem( $prefix, $attr, @$this->config[FC_MAXLEN], @$this->config[FC_SIZE] );

      // select-box for unit of time (or fix if no choice)
      if( count($this->time_units) > 1 )
         $r .= $this->build_generic_selectbox_elem( $prefix, $this->elem_tu, $this->values[$this->elem_tu], $this->choices_tu );
      else
         $r .= FilterRelativeDate::getTimeUnitText( $this->time_units[0] );

      return $r;
   }

   // ----------- static funcs -----------

   /*! \brief Returns choice-text for time-units or all choice-texts (if arg=null). */
   function getTimeUnitText( $timeunit=null )
   {
      // lazy-init of texts
      $key = 'reldate_choices';
      if( !isset($ARR_GLOBALS_FILTERS[$key]) )
      {
         $arr = array();
         // choices-array for time-units (for select-box)
         $arr[FRDTU_ABS]   = T_('absolute#reldate');
         $arr[FRDTU_YEAR]  = T_('years#reldate');
         $arr[FRDTU_MONTH] = T_('months#reldate');
         $arr[FRDTU_WEEK]  = T_('weeks#reldate');
         $arr[FRDTU_DAY]   = T_('days#reldate');
         $arr[FRDTU_HOUR]  = T_('hours#reldate');
         $arr[FRDTU_MIN]   = T_('minutes#reldate');
         $ARR_GLOBALS_FILTERS[$key] = $arr;
      }

      if( is_null($timeunit) )
         return $ARR_GLOBALS_FILTERS[$key];
      if( !isset($ARR_GLOBALS_FILTERS[$key][$timeunit]) )
         error('invalid_args', "FilterRelativeDate.getTimeUnitText($timeunit)");
      return $ARR_GLOBALS_FILTERS[$key][$timeunit];
   }

} // end of 'FilterRelativeDate'



 /*!
  * \class FilterSelection
  * \brief Filter for selecting from different choices; SearchFilter-Type: Selection.
  * <p>GUI: selectbox
  *
  * type of dbfield for this Filter must be an array:
  *    array( choice-descr => sql-where_clause-part|QuerySQL )
  *
  * <p>supported common config (restrictions or defaults):
  *    FC_FNAME, FC_STATIC, FC_GROUP_SQL_OR, FC_SYNTAX_HELP, FC_HIDE
  *    FC_ADD_HAVING (makes no sense with dbfield = QuerySQL (could use SQLP_HAVING),
  *         dbfield should be where-clause instead)
  *
  * <p>supported filter-specific config:
  *    FC_SIZE (multi-line) - rows of selectbox, must be >1 (otherwise default is 2)
  *    FC_DEFAULT - default-index that should be selected for single selection-mode (index 0..), or
  *                 array with indexes (starting with 1...) for multi-selection-mode
  *    FC_MULTIPLE (multi-value, index starts with 1, expecting array( descr => values_for_building_query) ),
  *         expecting dbfield to contain one fieldname only or as SQLP_FNAME in QuerySQL for complex query
  */
class FilterSelection extends Filter
{
   /*! \brief array with choices for selectbox. */
   var $choices;
   /*! \brief choice-correspondent sql-clauses (single: string|QuerySQL; multi: values for query to build from dbfield) */
   var $clauses;
   /*! \brief start-index (for multi: 1, for single: 0) */
   var $idx_start;

   /*! \brief Constructs Selection-Filter. */
   function FilterSelection($name, $dbfield, $config)
   {
      parent::Filter($name, $dbfield, null, $config);
      $this->type = 'Selection';
      $this->syntax_descr = ''; // action: select from choices
      $this->syntax_help = '';

      if( $this->get_config(FC_MULTIPLE) )
      {
         $this->check_forbid_sql_template( 1 ); # max. one fieldname
         $dbfield = $this->get_config(FC_MULTIPLE); # array-index gives value for according choice
         $this->idx_start = 1;

         // check FC_SIZE
         $fc_size = $this->get_config(FC_SIZE);
         if( empty($fc_size) || $fc_size <= 1 )
            $this->set_config( FC_SIZE, 2 ); // default FC_SIZE for multi-val

         // handle FC_DEFAULT as index-array
         $this->read_defaults( $this->get_config(FC_DEFAULT), false );
      }
      else
      {
         if( !is_array($dbfield) )
            error('invalid_filter', "filter.FilterSelection.expect_dbfield_array($name)");
         $this->check_forbid_sql_template( 0 ); # no fieldnames
         $dbfield = $this->dbfield;
         $this->idx_start = 0;
      }

      $this->choices = array_keys($dbfield);
      $this->clauses = array_values($dbfield); # single (=where-clauses), multi (=values for dbfield-combining)
   }

   /*!
    * \brief Parses single- and multi-value selection.
    * param val: string | array (only for multiple-support)
    */
   function parse_value( $name, $val )
   {
      $val = $this->handle_default( $name, $val );
      $this->init_parse($val);

      if( is_array($val) ) // multi-val
      {
         if( !$this->get_config(FC_MULTIPLE) )
            error('invalid_filter', "filter.FilterSelection.parse_value.no_multi_value_support({$this->id})");

         // build SQL
         $query = $this->build_base_query($this->dbfield, false, true); // query with set SQLP_FNAMES
         $arrfn = $query->get_parts(SQLP_FNAMES);
         $field = $arrfn[0];

         $arr_in = array();
         foreach( $val as $v ) # v is index into clauses-arr
         {
            $idx = $v - $this->idx_start;
            if( !isset($this->clauses[$idx]) )
               error('invalid_filter', "ERROR: FilterSelection.parse_value($name): bad index-value [$v] used for filter [{$this->id}]");
            $arr_in[]= "'" . mysql_addslashes( $this->clauses[$idx] ) . "'";
         }

         if( count($arr_in) > 0 )
         {
            $clause = "$field IN (" . implode(',', $arr_in) . ")";

            $parttype = ($this->get_config(FC_ADD_HAVING)) ? SQLP_HAVING : SQLP_WHERE;
            $query->add_part( $parttype, $clause ); // use having to allow alias-FNAMES
         }
         $this->query = $query;
      }
      elseif( is_numeric($val) ) // single-val
         $this->query = $this->build_base_query( $this->clauses[$val], true );
      else
         return false;

      return true;
   }

   /*! \brief Returns selectbox form-element. */
   function get_input_element($prefix, $attr = array() )
   {
      return $this->build_selectbox_elem( $prefix, $this->idx_start, $this->choices );
   }
} // end of 'FilterSelection'



 /*!
  * \class FilterBoolSelect
  * \brief Filter for boolean-selection (3 choices: all, yes, no);
  *        SearchFilter-Type: BoolSelect.
  * <p>GUI: selectbox
  *
  * type of dbfield for this Filter can be:
  *    a) simple sql-field, or b) QuerySQL with one dbfield in SQLP_FNAMES
  *    note: dbfield-value for comparison is 'Y' for yes-choice, 'N' for no-choice
  *
  * <p>supported common config (restrictions or defaults):
  *    FC_FNAME, FC_STATIC, FC_GROUP_SQL_OR, FC_SQL_TEMPLATE, FC_ADD_HAVING,
  *    FC_SYNTAX_HELP, FC_HIDE
  *
  * <p>supported filter-specific config:
  *    FC_DEFAULT - index 0=All, 1=Yes, 2=No
  */
class FilterBoolSelect extends FilterSelection
{
   /*! \brief Constructs BoolSelect-Filter extending FilterSelection. */
   function FilterBoolSelect($name, $dbfield, $config)
   {
      $this->check_forbid_sql_template( 1 );

      // build query with set SQLP_FNAMES
      $query = $this->build_base_query($dbfield, false, true);
      $arrfn = $query->get_parts(SQLP_FNAMES);
      $field = $arrfn[0];

      // use having to allow alias-FNAMES
      $parttype = (is_array($config) && @$config[FC_ADD_HAVING]) ? SQLP_HAVING : SQLP_WHERE;
      $query_yes = $query->duplicate();
      $query_yes->add_part( $parttype, "$field='Y'" );
      $query->add_part( $parttype, "$field='N'" );

      $choices = array(
         T_('All#boolsel') => '',
         T_('Yes#boolsel') => $query_yes,
         T_('No#boolsel')  => $query );
      parent::FilterSelection($name, $choices, $config);
      $this->type = 'BoolSelect';
      $this->syntax_descr = ''; // action: select from choices
   }
} // end of 'FilterBoolSelect'



 /*!
  * \class FilterRatedSelect
  * \brief Filter for boolean-selection for the Rated-column (3 choices: all, yes, no);
  *        This is a special filter, because the 'Yes' has to be translated
  *        into a db-query of "rated_field IN ('Y','Done')";
  *        SearchFilter-Type: BoolSelect.
  * <p>GUI: selectbox
  *
  * type of dbfield for this Filter can be:
  *    a) simple sql-field, or b) QuerySQL with one dbfield in SQLP_FNAMES
  *
  * <p>supported common config (restrictions or defaults):
  *    FC_FNAME, FC_STATIC, FC_GROUP_SQL_OR, FC_ADD_HAVING, FC_SYNTAX_HELP,
  *    FC_HIDE
  *
  * <p>supported filter-specific config:
  *    FC_DEFAULT - index 0=All, 1=Yes, 2=No
  */
class FilterRatedSelect extends FilterSelection
{
   /*! \brief Constructs RatedSelect-Filter extending FilterSelection. */
   function FilterRatedSelect($name, $dbfield, $config)
   {
      $this->check_forbid_sql_template( 1 );

      // build query with set SQLP_FNAMES
      $query = $this->build_base_query($dbfield, false, true);
      $arrfn = $query->get_parts(SQLP_FNAMES);
      $field = $arrfn[0];

      // use having to allow alias-FNAMES
      $parttype = (is_array($config) && @$config[FC_ADD_HAVING]) ? SQLP_HAVING : SQLP_WHERE;
      $query_yes = $query->duplicate();
      $query_yes->add_part( $parttype, "$field IN ('Y','Done')" );
      $query->add_part( $parttype, "$field='N'" );

      $choices = array(
         T_('All#ratedsel') => '',
         T_('Yes#ratedsel') => $query_yes,
         T_('No#ratedsel')  => $query );
      parent::FilterSelection($name, $choices, $config);
      $this->type = 'RatedSelect';
      $this->syntax_descr = ''; // action: select from choices
   }
} // end of 'FilterRatedSelect'



 /*!
  * \class FilterBoolean
  * \brief Filter for a boolean decision; SearchFilter-Type: Boolean.
  * <p>GUI: checkbox
  *
  * type of dbfield for this Filter:
  *    string where_clause | QuerySQL to be used if checked, or
  *    array ( true|false => clause_string | QuerySQL)
  *
  * <p>supported common config (restrictions or defaults):
  *    FC_FNAME, FC_STATIC, FC_GROUP_SQL_OR, FC_SYNTAX_HELP, FC_HIDE
  *
  * <p>supported filter-specific config:
  *    FC_LABEL   - unescaped text printed right to checkbox as description
  *    FC_DEFAULT - 1|true to select, 0|false for deselect
  */
class FilterBoolean extends Filter
{
   /*! \brief Constructs Boolean-Filter. */
   function FilterBoolean($name, $dbfield, $config)
   {
      static $_default_config = array( FC_DEFAULT => false ); // filter is ON or OFF (default)
      parent::Filter($name, $dbfield, $_default_config, $config);
      $this->type = 'Boolean';
      $this->syntax_descr = ''; // action: mark checkbox
      $this->syntax_help = '';
   }

   /*! \brief Handles check for checkbox. */
   function parse_value( $name, $val )
   {
      $val = $this->handle_default( $name, $val );
      $this->init_parse($val);

      if( is_array($this->dbfield) )
      {
         $key = ($this->value) ? true : false;
         $clause = (isset($this->dbfield[$key])) ? $this->dbfield[$key] : '';
      }
      else
         $clause = ($this->value) ? $this->dbfield : '';
      $this->query = $this->build_base_query( $clause, true );
      return true;
   }

   /*! \brief Returns checkbox form-element. */
   function get_input_element( $prefix, $attr = array() )
   {
      return $this->build_checkbox_elem( $prefix, $this->get_config(FC_LABEL) );
   }
} // end of 'FilterBoolean'



 /*!
  * \class FilterScore
  * \brief Filter for Score allowing exact value choosen by selection
  *        (win by resignation, by time, by score, jigo) and an
  *        optional score-value; SearchFilter-Type: Score.
  * <p>GUI: selectbox + text input-box
  *
  * <p>Allowed Syntax for score-value (for selection '?+?, B|W+?'):
  *    numeric-range (float or int), \see NumericFilter
  *    If score-value is empty, SQL excludes scoring by time + resign.
  *    In score-ranges, jigo is excluded.
  *
  * <p>supported common config (restrictions or defaults):
  *    FC_FNAME, FC_STATIC, FC_GROUP_SQL_OR, FC_SIZE (for text-input),
  *    FC_MAXLEN (for text-input), FC_NO_RANGE, FC_ADD_HAVING, FC_QUOTETYPE,
  *    FC_SYNTAX_HINT, FC_SYNTAX_HELP, FC_HIDE,
  *    FC_DEFAULT - score-mode (index) if scalar, or
  *                 array( '' => score-value, 'r' => score-mode-index );
  *       score-mode: FSCORE_ALL=show-all (default), FSCORE_RESIGN|TIME|SCORE,
  *                   FSCORE_B|W_RESIGN|TIME|SCORE, FSCORE_JIGO
  */

// index for filter-score-choices, and for FC_DEFAULT-config
define('FSCORE_ALL',      0);
define('FSCORE_RESIGN',   1);
define('FSCORE_B_RESIGN', 2);
define('FSCORE_W_RESIGN', 3);
define('FSCORE_TIME',     4);
define('FSCORE_B_TIME',   5);
define('FSCORE_W_TIME',   6);
define('FSCORE_SCORE',    7);
define('FSCORE_B_SCORE',  8);
define('FSCORE_W_SCORE',  9);
define('FSCORE_JIGO',    10);

// choices-array for score-results (for selectbox)
$FSCORE_RESULT_choices = array(
   'All',
   '?+R', 'B+R', 'W+R',
   '?+T', 'B+T', 'W+T',
   '?+?', 'B+?', 'W+?',
   'Jigo',
);

// sql-templates for the corresponding score-mode, %s is replaced with db-field
$FSCORE_BUILD_SQL = array(
   '',
   '%s IN (-'.SCORE_RESIGN.','.SCORE_RESIGN.')',
   '%s = -'.SCORE_RESIGN,
   '%s = '.SCORE_RESIGN,
   '%s IN (-'.SCORE_TIME.','.SCORE_TIME.')',
   '%s = -'.SCORE_TIME,
   '%s = '.SCORE_TIME,
   'ABS(%s)',
   '-%s',
   '%s',
   '%s = 0',
);

class FilterScore extends Filter
{
   /*! \brief element-name for result-selectbox */
   var $elem_result;

   /*! \brief Constructs Score-Filter. */
   function FilterScore($name, $dbfield, $config)
   {
      static $_default_config = array( FC_SIZE => 4 );
      parent::Filter($name, $dbfield, $_default_config, $config);
      $this->type = 'Score';
      $this->tok_config = $this->create_TokenizerConfig();
      $this->syntax_help = T_('SCORE#filterhelp');

      // checks
      $this->check_forbid_sql_template( 1 );

      if( $this->get_config(FC_NO_RANGE) )
      {
         $this->parser_flags |= TEXTPARSER_FORBID_RANGE;
         $this->syntax_descr = '314';
      }
      else
      {
         $s = $this->tok_config->sep;
         $this->syntax_descr = "3, {$s}14, 1{$s}5, 9{$s}"; // pi
      }

      // setup results (select-box)
      $this->elem_result = "{$this->name}r";
      $this->add_element_name( $this->elem_result );
      $this->values[$this->elem_result] = 0; // default (All)
   }

   /*! \brief Parses score-value using NumericParser and handle selection. */
   function parse_value( $name, $val )
   {
      $val = $this->handle_default( $name, $val );
      $this->init_parse($val, $name); // if elem, value for 2nd elem saved

      if( $name === $this->name )
      { // parse val: numeric-range
         if( !$this->parse_numeric( $name, $val, $this->tok_config, $this->parser_flags ) )
            return false;
      }
      elseif( $name === $this->elem_result )
         ; // only var set in values[elem_result]
      else
         error('invalid_filter', "ERROR: Score-Filter parse_value($name,$val) called with unknown key");

      return true;
   }

   /*!
    * \brief Builds query for multi-element filter.
    * expecting: values[elem_result] set, p_value, p_start, p_end set or unset
    */
   function build_query()
   {
      // check for expected values
      //   idx 0: show all
      //   idx 1-3: '?+R', 'B+R', 'W+R',
      //   idx 4-6: '?+T', 'B+T', 'W+T',
      //   idx 7-9: '?+?', 'B+?', 'W+?',  expecting p_value, p_start, p_end set according to range
      //   idx 10:  'Jigo'
      $idx = $this->values[$this->elem_result];
      if( $idx == FSCORE_ALL )
         return;
      if( $idx >= FSCORE_SCORE && $idx <= FSCORE_W_SCORE )
      {
         if( $this->p_value == '' && $this->p_start == '' && $this->p_end == '' )
         {
            // use default to search for scoring without time + resignation
            $this->p_flags |= PFLAG_EXCL_START | PFLAG_EXCL_END;
            $this->p_start = 0;
            $this->p_end   = min(SCORE_RESIGN, SCORE_TIME);
         }
      }

      // build SQL
      $query = $this->build_base_query($this->dbfield, false, true); // query with set SQLP_FNAMES
      $arrfn = $query->get_parts(SQLP_FNAMES);
      $field = $arrfn[0];

      global $FSCORE_BUILD_SQL;
      $clause = sprintf( $FSCORE_BUILD_SQL[$idx], $field );
      if( $idx >= FSCORE_SCORE && $idx <= FSCORE_W_SCORE )
      {
         // here $clause is field-part
         $q = $this->build_query_numeric(
            new QuerySQL( SQLP_WHERETMPL, $this->default_sql_template($clause) ) );
         $clause = $q->get_part(SQLP_WHERE);
      }

      $parttype = ($this->get_config(FC_ADD_HAVING)) ? SQLP_HAVING : SQLP_WHERE;
      $query->add_part( $parttype, $clause ); // use having to allow alias-FNAMES
      $this->query = $query;
   }

   /*! \brief Returns selectbox and input-text form-element for score-mode-selection and score-value. */
   function get_input_element($prefix, $attr = array() )
   {
      global $FSCORE_RESULT_choices;

      // select-box for result
      $r = $this->build_generic_selectbox_elem($prefix,
         $this->elem_result, $this->values[$this->elem_result], 0, $FSCORE_RESULT_choices);

      // input-text for number of points
      $r .= $this->build_input_text_elem(
         $prefix, $attr, @$this->config[FC_MAXLEN], @$this->config[FC_SIZE] );

      return $r;
   }
} // end of 'FilterScore'



 /*!
  * \class FilterRatingDiff
  * \brief Filter for rating-diffs (float-values) allowing exact value or
  *        range value; SearchFilter-Type: RatingDiff.
  * <p>GUI: text input-box
  *
  * <p>Allowed Syntax:
  *    numeric-range (float), \see NumericFilter
  *
  * <p>supported common config (restrictions or defaults):
  *    same as \see FilterNumeric (except FC_NUM_FACTOR)
  */
class FilterRatingDiff extends FilterNumeric
{
   /*! \brief Constructs RatingDiff-Filter by extending FilterNumeric (using static num-factor and other syntax-description). */
   function FilterRatingDiff($name, $dbfield, $config)
   {
      parent::FilterNumeric($name, $dbfield, $config);
      $this->type = 'RatingDiff';

      $this->num_factor = 100;

      if( $this->get_config(FC_NO_RANGE) )
         $this->syntax_descr = '0.314';
      else
      {
         $s = $this->tok_config->sep;
         $this->syntax_descr = "0.3, {$s}0.14, .1{$s}.5, 0.9{$s}"; // pi
      }
   }
} // end of 'FilterRatingDiff'



 /*!
  * \class FilterCheckboxArray
  * \brief Filter for different choices selectable by checkbox-array for the
  *        same db-field; SearchFilter-Type: CheckboxArray.
  * <p>GUI: checkboxes layouted in a matrix-table
  *
  * type of dbfield for this Filter:
  *    string dbfield or QuerySQL with set SQLP_FNAMES
  *
  * <p>supported common config (restrictions or defaults):
  *    FC_FNAME, FC_STATIC (makes not much sense), FC_GROUP_SQL_OR,
  *    FC_ADD_HAVING, FC_HIDE, FC_BITMASK, FC_SYNTAX_HELP
  *
  * <p>supported filter-specific config:
  *    FC_MULTIPLE - mandatory config,
  *         expecting array( td-values => value ),
  *         td-values containing %s which is replaced with checkbox form-element
  *    FC_SIZE - number of checkboxes per rows in matrix; default is 1
  *    FC_DEFAULT - array ( index, ... ); index=1.. - selected index-numbers
  */
class FilterCheckboxArray extends Filter
{
   /*!
    * \brief array with choices and alt-attribute-info.
    * array( sprintf-ready-td => array( value, [alt-text] ) ),
    * note: %s is replaced with checkbox form-element.
    * Example:
    *    array( '<td>%s Checkbox1</td>' => array( '10', 'text for visually impaired for checkbox1' ), ...)
    */
   var $choices;
   /*! \brief array( fname => value) needed to build query. */
   var $clauses;
   /*! \brief start-index */
   var $idx_start;

   /*! \brief Constructs CheckboxArray-Filter. */
   function FilterCheckboxArray($name, $dbfield, $config)
   {
      static $_default_config = array( FC_SIZE => 1 ); // FC_SIZE is nr of cols per row
      parent::Filter($name, $dbfield, $_default_config, $config);
      $this->type = 'CheckboxArray';
      $this->syntax_descr = T_('Select none, one or more elements');
      $this->syntax_help = '';

      $this->check_forbid_sql_template( 1 );
      $this->idx_start = 1;
      $this->clauses = array();

      $this->choices = $this->get_config(FC_MULTIPLE);
      if( $this->choices == '' )
         error('invalid_filter', "filter.FilterCheckboxArray.miss_FC_MULTIPLE({$this->id})");
      if( !is_array($this->choices) )
         error('invalid_filter', "filter.FilterCheckboxArray.expect_array_FC_MULTIPLE({$this->id})");

      // init field-names
      $idx = 0;
      $is_bitmask = $this->get_config(FC_BITMASK);
      foreach( $this->choices as $elemtd => $value )
      {
         $idx++;
         $fname = $this->build_fname($idx);
         $this->add_element_name( $fname );
         $this->values[$fname] = 0; // init
         $this->clauses[$fname] = $value;
         if( $is_bitmask && !is_numeric($value) )
         {
            // FC_BITMASK-config forces integer-values for FC_MULTIPLE-values
            error('invalid_filter', "filter.FilterCheckboxArray.bad_array_FC_BITMASK({$this->id})");
         }
      }

      // handle FC_DEFAULT as index-array (keep in sync with build_fname-func)
      $newdef = array();
      $defarr = $this->get_config(FC_DEFAULT, array());
      $suffix = substr( $this->build_fname( '' ), strlen($this->name) );
      foreach( $defarr as $idx )
         $newdef[ $suffix . $idx ] = 1;
      $this->read_defaults( $newdef );
   }

   /*! \brief Handles multiple checkboxes. */
   function parse_value( $name, $val )
   {
      if( !array_key_exists($name, $this->values) )
         return false;

      $val = $this->handle_default( $name, $val );
      $this->init_parse($val, $name);
      return true;
   }

   /*!
    * \brief Builds query for multi-element filter combining values from checkboxes for one dbfield-spec.
    * expecting: values[elem_chkboxes] set 0|1
    */
   function build_query()
   {
      // build SQL
      $query = $this->build_base_query($this->dbfield, false, true); // query with set SQLP_FNAMES
      $arrfn = $query->get_parts(SQLP_FNAMES);
      $field = $arrfn[0];

      if( $this->get_config(FC_BITMASK) )
      {
         $bitmask = 0;
         foreach( $this->values as $fname => $val )
         {
            if( $val && isset($this->clauses[$fname]) )
               $bitmask |= (int) $this->clauses[$fname];
         }
         if( $bitmask == 0 )
            return;

         $clause = "($field & $bitmask)<>0";
      }
      else {
         // regular values to be joined with OR (respective IN-syntax)
         $arr_in = array();
         foreach( $this->values as $fname => $val )
         {
            if( $val && isset($this->clauses[$fname]) )
               $arr_in[]= "'" . mysql_addslashes( $this->clauses[$fname] ) . "'";
         }
         if( count($arr_in) == 0 )
            return;

         $clause = "$field IN (" . implode(',', $arr_in) . ")";
      }

      $parttype = ($this->get_config(FC_ADD_HAVING)) ? SQLP_HAVING : SQLP_WHERE;
      $query->add_part( $parttype, $clause ); // use having to allow alias-FNAMES
      $this->query = $query;
   }

   /*! \brief Returns matrix of checkbox form-elements with maximum FC_SIZE-columns. */
   function get_input_element($prefix, $attr = array() )
   {
      $rows = array();
      $cols = array();
      $idx = 0;
      $cnt_cols = $this->get_config(FC_SIZE);
      foreach( $this->choices as $elem_td => $value )
      {
         $idx++;
         $fname = $this->build_fname( $idx );
         $elem_chkbox = $this->build_generic_checkbox_elem(
            $prefix, $fname, $this->get_value($fname), '', $this->syntax_descr );
         $td = sprintf( $elem_td, $elem_chkbox );
         $cols[]= $td;

         $cnt_cols--;
         if( $cnt_cols == 0 )
         {
            $rows[]= implode( '', $cols );
            $cnt_cols = $this->get_config(FC_SIZE);
            $cols = array();
         }
      }

      if( count($cols) > 0 )
      {
         $cols = array_pad( $cols, $this->get_config(FC_SIZE), '<td></td>');
         $rows[]= implode( '', $cols );
      }

      // build checkbox-array
      $r = "<table class=CheckboxArray><tr>\n";
      $r .= implode( "</tr><tr>\n", $rows );
      $r .= "</tr></table>\n";
      return $r;
   }

   /*! \brief Internally used to build checkbox form-element-name. */
   function build_fname( $idx )
   {
      return "{$this->name}_{$idx}";
   }
} // end of 'FilterCheckboxArray'


?>
