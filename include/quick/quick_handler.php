<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar, Rod Ival

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

// general quick-commands
define('QCMD_INFO', 'info');  // get info about single object
define('QCMD_LIST', 'list');  // retrieve list of objects

// general quick-options
define('QOPT_WITH', 'with');  // recursive (=deep-) loading of some objects
define('QOPT_TEST', 'test');  // output JSON in "plain/text" content-type
define('QOPT_LIST_STYLE', 'lstyle');  // output-style for result-list: table (=default) | json

// with-options
define('QWITH_USER_ID', 'user_id'); // include user-id user-fields: id, handle, name
define('QWITH_FOLDER',  'folder');  // include main folder-fields: id, name, system, color_bg, color_fg

// listtype-option
define('QLIST_STYLE_TABLE', 'table'); // default
define('QLIST_STYLE_JSON', 'json');



 /*!
  * \class QuickObject
  *
  * \brief Base class holding generic quick-object information and result.
  */
class QuickObject
{
   var $obj;
   var $cmd;
   var $result;

   function QuickObject( $obj, $cmd )
   {
      $this->obj = $obj;
      $this->cmd = $cmd;
      $this->initResult( false );
   }

   function getResult()
   {
      return $this->result;
   }

   function addResult( $field, $value )
   {
      $this->result[$field] = $value;
   }

   /*! \brief Inits result-array for special handling of nested using of QuickHandlers. */
   function initResult( $standardResult )
   {
      if( $standardResult )
      {
         $this->result = array(
            'version' => QUICK_SUITE_VERSION,
            'error' => '',
         );
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
class QuickHandler
{
   var $my_id;
   var $quick_object;
   var $with_option;
   var $list_style_option;

   function QuickHandler( $quick_object )
   {
      global $player_row;
      $this->my_id = (int)$player_row['ID'];
      $this->quick_object = $quick_object;

      $this->with_option = array();
      $with_arr = explode(',', @$_REQUEST[QOPT_WITH]);
      foreach( $with_arr as $opt )
         $this->with_option[$opt] = 1;

      $this->list_style_option = @$_REQUEST[QOPT_LIST_STYLE];
      if( $this->list_style_option != QLIST_STYLE_JSON && $this->list_style_option != QLIST_STYLE_TABLE )
         $this->list_style_option = QLIST_STYLE_TABLE;
   }

   function getResult()
   {
      return $this->quick_object->getResult();
   }

   function addResultKey( $key, $value )
   {
      $this->quick_object->result[$key] = $value;
   }

   /*! \brief Returns true if specified with-option value has been specified. */
   function is_with_option( $with )
   {
      return isset($this->with_option[$with]);
   }

   function check_list_style( $ltype=QLIST_STYLE_TABLE )
   {
      return ($this->list_style_option == $ltype);
   }

   /*! \brief throw error for unknown command. */
   function checkCommand( $dbgmsg, $regex_cmds )
   {
      $cmd = $this->quick_object->cmd;
      if( !QuickHandler::matchRegex($regex_cmds, $cmd) )
         error('invalid_command', "QuickHandler.checkCommand.bad_cmd($dbgmsg,$cmd))");
   }

   /*! \brief Returns map for user-object; with handle/name-fields only if WITH-option QWITH_USER_ID used. */
   function build_obj_user( $uid, $user_rows=null, $always=false )
   {
      $userinfo = array( 'id' => $uid );
      if( ($always || $this->is_with_option(QWITH_USER_ID)) && is_array($user_rows) && is_array($user_rows[$uid]) )
      {
         $userinfo['handle'] = $user_rows[$uid]['Handle'];
         $userinfo['name'] = $user_rows[$uid]['Name'];
      }
      return $userinfo;
   }

   /*! \brief Returns map for user-object; with handle/name-fields only if WITH-option QWITH_USER_ID used. */
   function build_obj_user2( $uid, $user_row=null, $prefix='' )
   {
      $userinfo = array( 'id' => $uid );
      if( $this->is_with_option(QWITH_USER_ID) && is_array($user_row) )
      {
         $userinfo['handle'] = $user_row[$prefix.'Handle'];
         $userinfo['name'] = $user_row[$prefix.'Name'];
      }
      return $userinfo;
   }

   /*! \brief Adds list-result into result-map. */
   function add_list( $object_name, $list, $ordered_by='', $offset=0, $limit=0 )
   {
      $this->addResultKey( 'list_object', $object_name );
      $this->addResultKey( 'list_size', count($list) );
      $this->addResultKey( 'list_offset', (int)$offset );
      $this->addResultKey( 'list_limit', (int)$limit );
      $this->addResultKey( 'list_has_next', 0);
      $this->addResultKey( 'list_order', $ordered_by );

      if( count($list) && $this->check_list_style() ) // list-style = table
      {
         $out = array();
         foreach( $list as $item )
            $out[] = QuickHandler::buildObjectArray( $item, /*keys*/false );

         $this->addResultKey( 'list_header',
            QuickHandler::buildObjectArray( $list[0], /*keys*/true ) );
         $this->addResultKey( 'list_result', $out );
      }
      else // list-style = json
         $this->addResultKey( 'list_result', $list );
   }//add_list


   // ---------- Interface ----------------------------------------

   /*! \brief Returns true, if handler-implementation can handle given object and command. */
   function canHandle( $obj, $cmd ) // static
   {
      return false; // base-class
   }

   /*!
    * \brief Parses handler-specific arguments from URL into handler-object.
    * \note this separate method allows to initialize Handler from other source
    * \note Method should not fire error.
    */
   function parseURL()
   {
      // abstract: requires implementation
   }

   /*!
    * \brief Parses and checks handler-specific URL-arguments into QuickObject and/or into handler-object,
    *        and prepares processing of command for object; may fire error(..) and perform db-operations.
    */
   function prepare()
   {
      // abstract: requires implementation
   }

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   function process()
   {
      $obj = @$_REQUEST['obj'];
      error('invalid_method', "QuickHandler.process($obj)");
      return 0;
   }


   // ---------- Static functions ---------------------------------

   /*! \brief Returns true, if given value matches regex. */
   function matchRegex( $regex, $value )
   {
      return preg_match( "/^($regex)$/", $value );
   }

   /*! \brief Ensures, that given args appear in URL-args; throw error if arg missing. */
   function checkArgMandatory( $dbgmsg, $key, $val, $allow_empty=false )
   {
      if( is_null($val) )
         error('invalid_args', "QuickHandler::checkArgMandatory.miss_arg($dbgmsg,$key)");
      elseif( !$allow_empty && (string)$val == '' )
         error('invalid_args', "QuickHandler::checkArgMandatory.empty_arg($dbgmsg,$key)");
   }

   /*! \brief Formats datetime with standard quick-date-formats: long-fmt=YYYY-MM-DD hh:mm:ss, short-fmt=YYYY-MM-DD. */
   function formatDate( $datetime, $long_fmt=true )
   {
      return ( $datetime > 0 ) ? date(($long_fmt) ? DATE_FMT_QUICK : DATE_FMT_QUICK_YMD, $datetime) : '';
   }

   /*!
    * \brief Converts object into flat array for keys (get_keys=true) or values (get_keys=false).
    * \note Only works correctly, if all map-entries (key+value) are set in object,
    *       especially if this method is used to convert a list of objects.
    */
   function buildObjectArray( $obj, $get_keys, $prefix='' )
   {
      $out = array();
      foreach( array_keys($obj) as $key )
      {
         $val = $obj[$key];
         if( is_array($val) )
         {
            $arr = QuickHandler::buildObjectArray($val, $get_keys, "$prefix$key.");
            foreach( $arr as $elem )
               $out[] = $elem;
         }
         else
            $out[] = ($get_keys) ? $prefix.$key : $val;
      }
      return $out;
   }

} // end of 'QuickHandler'

?>
