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

require_once 'include/quick/quick_handler.php';
require_once 'include/std_functions.php';
require_once 'include/message_functions.php';

 /*!
  * \file quick_message.php
  *
  * \brief QuickHandler for user-object.
  * \see specs/quick_suite.txt (3c)
  */

// see specs/quick_suite.txt (3c)
// mid=<MESSAGE_ID>
define('MESSAGEOPT_MID',  'mid');

define('MESSAGECMD_INFO', 'info');
define('MESSAGE_COMMANDS', 'info');


 /*!
  * \class QuickHandlerMessage
  *
  * \brief Quick-handler class for handling game-object.
  */
class QuickHandlerMessage extends QuickHandler
{
   var $mid;

   var $msg_row;
   var $folder;

   function QuickHandlerMessage( $quick_object )
   {
      parent::QuickHandler( $quick_object );
      $this->mid = 0;

      $this->msg_row = null;
      $this->folder = null;
   }


   // ---------- Interface ----------------------------------------

   function canHandle( $obj, $cmd ) // static
   {
      return ( $obj == QOBJ_MESSAGE ) && QuickHandler::matchCommand(MESSAGE_COMMANDS, $cmd);
   }

   function parseURL()
   {
      $this->mid = (int)get_request_arg(MESSAGEOPT_MID);
   }

   function prepare()
   {
      // see specs/quick_suite.txt (3c)
      $dbgmsg = "QuickHandlerMessage.prepare({$this->mid})";
      $this->checkCommand( $dbgmsg, MESSAGE_COMMANDS );
      $cmd = $this->quick_object->cmd;

      // check mid
      if( (string)$this->mid == '' || !is_numeric($this->mid) || $this->mid <= 0 )
         error('invalid_args', "$dbgmsg.bad_message");
      $mid = $this->mid;

      // prepare command: info

      if( $cmd == MESSAGECMD_INFO )
      {
         /* see also the note about MessageCorrespondents.mid==0 in message_list_query() */
         global $player_row;
         $my_id = $player_row['ID'];

         $this->msg_row = mysql_single_fetch( "$dbgmsg.find_message",
                  "SELECT M.* " .
                  ",UNIX_TIMESTAMP(M.Time) AS X_Time " .
                  ",IF(NOT ISNULL(previous.mid),".FLOW_ANSWER.",0)" .
                     "+IF(me.Replied='Y' OR other.Replied='Y',".FLOW_ANSWERED.",0) AS X_Flow " .
                  ",other.uid AS other_ID " .
                  ",me.Replied, me.Sender, me.Folder_nr " .
                  "FROM Messages AS M " .
                  "INNER JOIN MessageCorrespondents AS me ON me.mid=$mid AND me.uid=$my_id " .
                  "LEFT JOIN MessageCorrespondents AS other ON other.mid=$mid AND other.Sender!=me.Sender " .
                  "LEFT JOIN MessageCorrespondents AS previous " .
                     "ON M.ReplyTo>0 AND previous.mid=M.ReplyTo AND previous.uid=$my_id " .
                  "WHERE M.ID=$mid " .
                  "LIMIT 1" )
               or error('unknown_message', "$dbgmsg.find_message2");

         $this->folder = QuickHandlerMessage::load_folder( $my_id, $this->msg_row['Folder_nr'] );
      }

      // check for invalid-action

   }//prepare

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   function process()
   {
      $cmd = $this->quick_object->cmd;
      if( $cmd == MESSAGECMD_INFO )
         $this->process_cmd_info();
   }

   function process_cmd_info()
   {
      global $player_row;
      $row = $this->msg_row;
      $my_id = $player_row['ID'];
      $other_uid = (int)$row['other_ID'];
      switch( $row['Sender'] ) // also see get_message_directions()
      {
         case 'M': $uid_from = $my_id; $uid_to = $my_id; break; // myself
         case 'S': $uid_from = 0;      $uid_to = $my_id; break; // system
         case 'Y': $uid_from = $my_id; $uid_to = $other_uid; break;
         case 'N': $uid_from = $other_uid; $uid_to = $my_id; break;
         default:  $uid_from = $uid_to = 0; break;
      }

      $this->addResultKey( 'id', (int)$row['ID'] );
      $this->addResultKey( 'uid_from', (int)$uid_from );
      $this->addResultKey( 'uid_to', (int)$uid_to );
      $this->addResultKey( 'type', strtoupper($row['Type']) );
      $this->addResultKey( 'folder', $this->folder );
      $this->addResultKey( 'time_created', QuickHandler::formatDate(@$urow['X_Time']) );
      $this->addResultKey( 'thread', (int)$row['Thread'] );
      $this->addResultKey( 'level', (int)$row['Level'] );
      $this->addResultKey( 'message_prev', (int)$row['ReplyTo'] );
      $this->addResultKey( 'message_hasnext', ($row['X_Flow'] & FLOW_ANSWERED) ? 1 : 0 );
      $this->addResultKey( 'game_id', (int)$row['Game_ID'] );
      $this->addResultKey( 'subject', $row['Subject'] );
      $this->addResultKey( 'text', $row['Text'] );
   }//process_cmd_info


   // ------------ static functions ----------------------------

   function load_folder( $uid, $folder_id )
   {
      if( $folder_id == FOLDER_DESTROYED ) // invisible-folder
         $arr = array( T_('Destroyed#folder'), 'FF88EE00', '000000' );
      elseif( $folder_id == FOLDER_ALL_RECEIVED ) // pseudo folder
         $arr = array( T_('All Received'), '00000000', '000000' );
      else
      {
         init_standard_folders();
         $arr = get_folders( $uid, true, $folder_id );
         if( is_array($arr) && isset($arr[$folder_id]) )
            $arr = $arr[$folder_id];
         if( !is_array($arr) )
            $arr = array( T_('Unknown#folder'), '00000000', '000000' );
      }
      return array( 'id'       => $folder_id,
                    'name'     => $arr[0],
                    'color_bg' => $arr[1],
                    'color_fg' => $arr[2], );
   }

} // end of 'QuickHandlerMessage'

?>
