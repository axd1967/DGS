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

 /*!
  * \file quick_handler.php
  *
  * \brief Alternative "quick" interface: base class for handling specific DGS-object.
  * \see specs/quick_suite.txt
  */



 /*!
  * \class QuickObject
  *
  * \brief Base class holding generic quick-object information and result.
  */
class QuickObject
{
   var $obj;
   var $cmd;
   var $args;
   var $result;

   function QuickObject( $obj, $cmd )
   {
      $this->obj = $obj;
      $this->cmd = $cmd;
      $this->args = array();
      $this->result = array( 'error' => '' );
   }

   function setArg( $key, $val )
   {
      $this->args[$key] = $val;
   }

   function getArg( $key )
   {
      return (isset($this->args[$key])) ? $this->args[$key] : null;
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

   function QuickHandler()
   {
      global $player_row;
      $this->my_id = (int)$player_row['ID'];
      $this->quick_object = null;
   }

   function getArg( $key )
   {
      return $this->quick_object->getArg($key);
   }

   function getResult()
   {
      return $this->quick_object->result;
   }

   /*! \brief throw error for unknown command. */
   function checkCommand( $dbgmsg, $regex_cmds )
   {
      if( !preg_match("/^($regex_cmds)$/", $this->quick_object->cmd) )
         error('invalid_command', "QuickHandler.checkCommand.bad_cmd($dbgmsg,{$this->quick_object->cmd})");
   }

   /*! \brief Ensures, that given args appear in URL-args; throw error if arg missing. */
   function checkArgMandatory( $dbgmsg, $key, $allow_empty=false )
   {
      $val = $this->quick_object->getArg($key);
      if( is_null($val) )
         error('invalid_args', "QuickHandler.checkArgMandatory.miss_arg($dbgmsg,$key)");
      elseif( !$allow_empty && (string)$val == '' )
         error('invalid_args', "QuickHandler.checkArgMandatory.empty_arg($dbgmsg,$key,$allow_empty)");
   }


   // ---------- Interface ----------------------------------------

   /*!
    * \brief Parses handler-specific URL-arguments into QuickObject and/or into handler-object.
    * \note method MUST NOT fail with error, use check() to verify arguments
    */
   function parse( $quick_object )
   {
      $this->quick_object = $quick_object;
   }

   /*! \brief Checks syntax and verifies object and command and fires error(..) on found error. */
   function check()
   {
      // may not be needed
   }

   /*! \brief Prepares processing of command for object; may fire error(..) and perform db-operations. */
   function prepare()
   {
      // may not be needed
   }

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   function process()
   {
      $obj = @$_REQUEST['obj'];
      error('invalid_method', "QuickHandler.process($obj)");
      return 0;
   }

} // end of 'QuickHandler'

?>
