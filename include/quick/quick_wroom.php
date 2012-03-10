<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/quick/quick_handler.php';


 /*!
  * \file quick_wroom.php
  *
  * \brief QuickHandler for wroom-object.
  * \see specs/quick_suite.txt (3f)
  */

// see specs/quick_suite.txt (3f)
define('WROOM_COMMANDS', 'list');


 /*!
  * \class QuickHandlerWaitingroom
  *
  * \brief Quick-handler class for handling wroom-object.
  */
class QuickHandlerWaitingroom extends QuickHandler
{
   function QuickHandlerWaitingroom( $quick_object )
   {
      parent::QuickHandler( $quick_object );
   }


   // ---------- Interface ----------------------------------------

   function canHandle( $obj, $cmd ) // static
   {
      return ( $obj == QOBJ_WROOM ) && QuickHandler::matchRegex(WROOM_COMMANDS, $cmd);
   }

   function parseURL()
   {
   }

   function prepare()
   {
      global $player_row;
      $uid = (int)@$player_row['ID'];

      // see specs/quick_suite.txt (3f)
      $dbgmsg = "QuickHandlerWaitingroom.prepare($uid)";
      $this->checkCommand( $dbgmsg, WROOM_COMMANDS );
      $cmd = $this->quick_object->cmd;

      // prepare command: list

      if( $cmd == QCMD_LIST )
      {
         //TODO
      }

      // check for invalid-action

   }//prepare

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   function process()
   {
      $cmd = $this->quick_object->cmd;
      if( $cmd == QCMD_LIST )
         $this->process_cmd_list();
   }

   function process_cmd_list()
   {
      $out = array();
      //TODO

      $this->add_list( QOBJ_WROOM, $out );
   }//process_cmd_list


   // ------------ static functions ----------------------------

} // end of 'QuickHandlerWaitingroom'

?>
