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
      $this->result = array( 'error' => '' );
   }

   function getResult()
   {
      return $this->result;
   }

   function addResult( $field, $value )
   {
      $this->result[$field] = $value;
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

   function QuickHandler( $quick_object )
   {
      global $player_row;
      $this->my_id = (int)$player_row['ID'];
      $this->quick_object = $quick_object;
   }

   function getResult()
   {
      return $this->quick_object->getResult();
   }

   /*! \brief throw error for unknown command. */
   function checkCommand( $dbgmsg, $regex_cmds )
   {
      $cmd = $this->quick_object->cmd;
      if( !QuickHandler::matchCommand($regex_cmds, $cmd) )
         error('invalid_command', "QuickHandler.checkCommand.bad_cmd($dbgmsg,$cmd))");
   }


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

   /*! \brief Returns true, if command matches one of supported commands. */
   function matchCommand( $regex_supported_cmds, $cmd )
   {
      return preg_match( "/^($regex_supported_cmds)$/", $cmd );
   }

   /*! \brief Ensures, that given args appear in URL-args; throw error if arg missing. */
   function checkArgMandatory( $dbgmsg, $key, $val, $allow_empty=false )
   {
      if( is_null($val) )
         error('invalid_args', "QuickHandler::checkArgMandatory.miss_arg($dbgmsg,$key)");
      elseif( !$allow_empty && (string)$val == '' )
         error('invalid_args', "QuickHandler::checkArgMandatory.empty_arg($dbgmsg,$key)");
   }

} // end of 'QuickHandler'

?>
