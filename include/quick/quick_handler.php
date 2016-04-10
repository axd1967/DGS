<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar, Rod Ival

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

require_once 'include/error_functions.php';
require_once 'include/rating.php';

 /*!
  * \file quick_handler.php
  *
  * \brief Alternative "quick" interface: base class for handling specific DGS-object.
  * \see specs/quick_suite.txt
  */

// quick-objects
define('QOBJ_GAME', 'game');
define('QOBJ_USER', 'user');
define('QOBJ_MESSAGE', 'message');
define('QOBJ_FOLDER', 'folder');
define('QOBJ_CONTACT', 'contact');
define('QOBJ_WROOM', 'wroom');
define('QOBJ_BULLETIN', 'bulletin');

// general quick-commands
define('QCMD_INFO', 'info');  // get info about single object
define('QCMD_LIST', 'list');  // retrieve list of objects

// general quick-options
define('QOPT_WITH', 'with');  // recursive (=deep-) loading of some objects
define('QOPT_TEST', 'test');  // output JSON in "plain/text" content-type
define('QOPT_LIST_STYLE', 'lstyle');  // output-style for result-list: table (=default) | json
define('QOPT_FIELDS', 'fields');  // filter fields in result: '' (=default=all-fields) | name1,name2,...
define('QOPT_LIMIT', 'limit');  // max. number of rows to deliver for 'list'-command
define('QOPT_OFFSET', 'off');  // starting row to page result for 'list'-command
define('QUICK_STD_OPTIONS', 'obj|cmd|test|lstyle|with|fields|limit|off|filter_\\w+');

// with-options
define('QWITH_USER_ID', 'user_id'); // include user-id user-fields: id, handle, name
define('QWITH_FOLDER',  'folder');  // include main folder-fields: id, name, system, color_bg, color_fg
// object-specific with-options
define('QWITH_PRIO', 'prio'); // game: include prio-field
define('QWITH_NOTES', 'notes'); // game: include notes-field
define('QWITH_RATINGDIFF', 'ratingdiff'); // game: include rating-diff


// listtype-option
define('QLIST_STYLE_TABLE', 'table'); // default
define('QLIST_STYLE_JSON', 'json');

define('QLIST_LIMIT_DEFAULT', 10);



 /*!
  * \class QuickObject
  *
  * \brief Base class holding generic quick-object information and result.
  */
class QuickObject
{
   public $obj;
   public $cmd;
   public $result;

   public function __construct( $obj, $cmd )
   {
      $this->obj = $obj;
      $this->cmd = $cmd;
      $this->initResult( true );
   }

   public function getResult()
   {
      return $this->result;
   }

   public function addResult( $field, $value )
   {
      $this->result[$field] = $value;
   }

   /*! \brief Inits result-array for special handling of nested using of QuickHandlers. */
   public function initResult( $standardResult )
   {
      if ( $standardResult )
      {
         $this->result = array(
            'version' => QUICK_SUITE_VERSION,
            'error' => '',
         );
         quick_suite_add_quota( $this->result );
      }
      else
         $this->result = array();
   }

} // end of 'QuickObject'



 /*!
  * \class QuickHandler
  *
  * \brief Base class of quick-handler for specific DGS-object.
  */
abstract class QuickHandler
{
   protected $my_id;
   protected $quick_object;
   protected $filters = array(); // [ name => value ]

   // from options: with, lstyle, fields, limit, off
   protected $with;
   protected $list_style;
   protected $list_limit;
   protected $list_offset;
   protected $list_order = '';
   protected $rx_keep_fields; // regex with varnames to keep; or empty (=all fields)

   // calculated
   protected $list_totals = -1;

   public function __construct( $quick_object )
   {
      global $player_row;
      $this->my_id = (int)$player_row['ID'];
      $this->quick_object = $quick_object;

      $this->_parse_options();
   }

   // \internal
   private function _parse_options()
   {
      // parse option: with
      $this->with = array();
      $with_arr = explode(',', @$_REQUEST[QOPT_WITH]);
      foreach ( $with_arr as $opt )
         $this->with[$opt] = 1;

      // parse option: lstyle
      $this->list_style = @$_REQUEST[QOPT_LIST_STYLE];
      if ( $this->list_style != QLIST_STYLE_JSON && $this->list_style != QLIST_STYLE_TABLE )
         $this->list_style = QLIST_STYLE_TABLE;

      // parse option: limit
      $limit_opt = @$_REQUEST[QOPT_LIMIT];
      $this->list_limit = ( (string)$limit_opt != '' && is_numeric($limit_opt) )
         ? limit( (int)@$_REQUEST[QOPT_LIMIT], 1, 100, QLIST_LIMIT_DEFAULT )
         : QLIST_LIMIT_DEFAULT;

      // parse option: off
      $offset_opt = @$_REQUEST[QOPT_OFFSET];
      $this->list_offset = ( (string)$offset_opt != '' && is_numeric($offset_opt) && $offset_opt >= 0 ) ? (int)$offset_opt : 0;

      // parse option: fields
      $field_opt = @$_REQUEST[QOPT_FIELDS];
      if ( (string)$field_opt == '' )
         $this->rx_keep_fields = '';
      else
      {
         static $RXK = '[a-z_][a-z0-9_]*\\*?'; // varname (with optional *-suffix)
         if ( !preg_match("/^$RXK(|(,$RXK)+)$/", $field_opt) ) // lower-case only
            error('invalid_args', "QuickHandler.opt.fields.bad_syntax($field_opt)");
         $rx_fields = str_replace('*', '.*', str_replace(',', '|', $field_opt)); // , * -> | .*
         $this->rx_keep_fields = "/^(version|error.*|quota_.+|list_.+|id|$rx_fields)$/"; // +std-fields
      }
   }//parse_options

   /*! \brief Returns result (processed to keep only expected fields). */
   public function getProcessedResult()
   {
      if ( $this->quick_object->cmd != QCMD_LIST )
         $this->clear_unexpected_fields( $this->quick_object->result );
      return $this->quick_object->result;
   }

   protected function addResultKey( $key, $value )
   {
      $this->quick_object->result[$key] = $value;
   }

   protected function setError( $error_code )
   {
      $this->quick_object->result['error'] = $error_code;
   }

   /*! \brief Returns true if specified with-option value has been specified. */
   public function is_with_option( $with )
   {
      return isset($this->with[$with]);
   }

   /*! \brief Resets parts of WITH-option given as elements in array. */
   protected function clear_with_options( $arr )
   {
      if ( is_array($arr) )
      {
         foreach ( $arr as $opt )
            unset($this->with[$opt]);
      }
   }

   protected function check_list_style( $ltype=QLIST_STYLE_TABLE )
   {
      return ($this->list_style == $ltype);
   }

   /*! \brief throw error for unknown command. */
   protected function checkCommand( $dbgmsg, $regex_cmds )
   {
      $cmd = $this->quick_object->cmd;
      if ( !self::matchRegex($regex_cmds, $cmd) )
         error('invalid_command', "QuickHandler.checkCommand.bad_cmd($dbgmsg,$cmd))");
   }

   protected function parseFilters( $rx_filters )
   {
      $regex = sprintf( "/^filter_(%s)\$/", $rx_filters );
      $matches = array();
      foreach ( $_REQUEST as $key => $val )
      {
         if ( strncmp($key, 'filter_', 7) )
            continue;

         if ( !preg_match($regex, $key, $matches) )
            error('invalid_args', "QuickHandler:parseFilters.unknown($key,allowed=[$rx_filters])");
         $fkey = $matches[1];
         $this->filters[$fkey] = $val;
      }
   }//parseFilters

   /*!
    * \brief Returns map for user-object; with handle/name-fields only if WITH-option QWITH_USER_ID used.
    * \param $inclkeys fieldnames to explicitly include additional fields: 'country,rating,lastacc'
    */
   public function build_obj_user( $uid, $user_row=null, $prefix='', $inclkeys='', $always=false )
   {
      $userinfo = array( 'id' => $uid );
      if ( ($always || $this->is_with_option(QWITH_USER_ID)) && is_array($user_row) )
      {
         $userinfo['handle'] = @$user_row[$prefix.'Handle'];
         $userinfo['name'] = @$user_row[$prefix.'Name'];

         if ( strpos($inclkeys, 'country') !== false )
            $userinfo['country'] = @$user_row[$prefix.'Country'];

         if ( strpos($inclkeys, 'rating') !== false )
         {
            if ( isset($user_row[$prefix.'Rating2']) )
            {
               $rating = $user_row[$prefix.'Rating2'];
               $userinfo['rating'] = echo_rating($rating, /*perc*/1, /*uid*/0, /*engl*/true, /*short*/1 );
               $userinfo['rating_elo'] = $rating;
            }
            else
               $userinfo['rating'] = $userinfo['rating_elo'] = '';
         }

         if ( strpos($inclkeys, 'lastacc') !== false )
            $userinfo['last_access'] = self::formatDate( @$user_row[$prefix.'X_Lastaccess'] );
      }
      return $userinfo;
   }//build_obj_user

   protected function add_query_limits( &$qsql, $calc_rows )
   {
      if ( $calc_rows )
         $qsql->add_part( SQLP_OPTS, SQLOPT_CALC_ROWS );
      if ( $this->list_limit > 0 )
         $qsql->add_part( SQLP_LIMIT,
            sprintf('%s,%s', $this->list_offset, $this->list_limit + 1) ); //+1 to check for has-next
   }

   protected function read_found_rows()
   {
      $this->list_totals = mysql_found_rows(
         "quick_handler.found_rows({$this->quick_object->obj},{$this->quick_object->cmd})");
   }

   /*! \brief Adds list-result into result-map (totals + offset + limit taken from handler-vars). */
   protected function add_list( $object_name, $list, $ordered_by='' )
   {
      $has_next = 0;
      if ( $this->list_limit > 0 && count($list) == $this->list_limit + 1 )
      {
         $has_next = 1;
         array_pop( $list ); // remove last element to determine has-next
      }
      elseif ( $this->list_limit == 0 && $this->list_totals < 0 )
      {
         $this->list_totals = count($list);
      }

      $this->addResultKey( 'list_object', $object_name );
      $this->addResultKey( 'list_totals', $this->list_totals );
      $this->addResultKey( 'list_size', count($list) );
      $this->addResultKey( 'list_offset', $this->list_offset );
      $this->addResultKey( 'list_limit', $this->list_limit );
      $this->addResultKey( 'list_has_next', $has_next );
      $this->addResultKey( 'list_order', ( $ordered_by ? $ordered_by : $this->list_order ) );

      // build "processed" list
      if ( count($list) && $this->check_list_style() ) // list-style = table
      {
         $this->addResultKey( 'list_header',
            self::buildObjectArray( $list[0], /*keys*/true, $this->rx_keep_fields ) );

         $out = array();
         foreach ( $list as $item )
            $out[] = self::buildObjectArray( $item, /*keys*/false, $this->rx_keep_fields );
      }
      else // list-style = json
      {
         if ( $this->rx_keep_fields )
         {
            $out = array();
            foreach ( $list as $elem )
            {
               $this->clear_unexpected_fields( $elem );
               $out[] = $elem;
            }
         }
         else
            $out = $list;
      }
      $this->addResultKey( 'list_result', $out );
   }//add_list

   private function clear_unexpected_fields( &$arr )
   {
      if ( $this->rx_keep_fields )
      {
         foreach ( $arr as $key => $val )
         {
            if ( !preg_match($this->rx_keep_fields, $key) )
               unset($arr[$key]);
         }
      }
   }//clear_unexpected_fields


   // ---------- Interface ----------------------------------------

   /*!
    * \brief Returns true, if handler-implementation can handle given object and command.
    * \note must be static, because check must be done before class-instantiation!
    */
   public static function canHandle( $obj, $cmd )
   {
      // must be implemented by implementing-class
      error('invalid_method', "QuickHandler:canHandle($obj,$cmd)");
      return false;
   }

   /*!
    * \brief Parses handler-specific arguments from URL into handler-object.
    * \note this separate method allows to initialize Handler from other source
    * \note Method should not fire error.
    */
   abstract public function parseURL();

   /*!
    * \brief Parses and checks handler-specific URL-arguments into QuickObject and/or into handler-object,
    *        and prepares processing of command for object; may fire error(..) and perform db-operations.
    */
   abstract public function prepare();

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   abstract public function process();


   // ---------- Static functions ---------------------------------

   /*! \brief Ensures, that no unknown arguments are used; throw error if unknown arg used. */
   protected static function checkArgsUnknown( $rx_opts='' )
   {
      $rx_chk_opts = QUICK_STD_OPTIONS;
      if ( (string)$rx_opts != '' )
         $rx_chk_opts .= '|' . $rx_opts;
      $regex = sprintf( "/^(%s)\$/", $rx_chk_opts );
      foreach ( $_REQUEST as $key => $val )
      {
         if ( !isset($_COOKIE[$key]) && !preg_match($regex, $key) )
            error('invalid_args', "QuickHandler:checkArgsUnknown.unknown_arg($key,allowed=[$rx_chk_opts])");
      }
   }//checkArgsUnknown

   /*! \brief Returns true, if given value matches regex. */
   protected static function matchRegex( $regex, $value )
   {
      return preg_match( "/^($regex)$/", $value );
   }

   /*! \brief Ensures, that given args appear in URL-args; throw error if arg missing. */
   protected static function checkArgMandatory( $dbgmsg, $key, $val, $allow_empty=false )
   {
      if ( is_null($val) )
         error('invalid_args', "QuickHandler:checkArgMandatory.miss_arg($dbgmsg,$key)");
      elseif ( !$allow_empty && (string)$val == '' )
         error('invalid_args', "QuickHandler:checkArgMandatory.empty_arg($dbgmsg,$key)");
   }

   /*! \brief Formats datetime with standard quick-date-formats: long-fmt=YYYY-MM-DD hh:mm:ss, short-fmt=YYYY-MM-DD. */
   protected static function formatDate( $datetime, $long_fmt=true )
   {
      return ( $datetime > 0 ) ? date(($long_fmt) ? DATE_FMT_QUICK : DATE_FMT_QUICK_YMD, $datetime) : '';
   }

   /*!
    * \brief Converts object into flat array for keys (get_keys=true) or values (get_keys=false).
    * \param $get_keys true (=return only keys of structure), false (=return keys + vars converted)
    * \param $rx_keep_fields '' (=return all fields), otherwise regex with key-names to keep
    * \note Only works correctly, if all map-entries (key+value) are set in object,
    *       especially if this method is used to convert a list of objects.
    */
   protected static function buildObjectArray( $obj, $get_keys, $rx_keep_fields='', $prefix='' )
   {
      $out = array();
      foreach ( array_keys($obj) as $key )
      {
         $pkey = $prefix.$key;
         if ( $rx_keep_fields && !preg_match($rx_keep_fields, $pkey) )
            continue;

         $val = $obj[$key];
         if ( is_array($val) )
         {
            $arr = self::buildObjectArray($val, $get_keys, $rx_keep_fields, $pkey.'.' );
            foreach ( $arr as $elem )
               $out[] = $elem;
         }
         else
            $out[] = ($get_keys) ? $pkey : $val;
      }
      return $out;
   }//buildObjectArray

} // end of 'QuickHandler'

?>
