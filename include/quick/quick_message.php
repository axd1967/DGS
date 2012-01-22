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
require_once 'include/quick/quick_folder.php';
require_once 'include/std_functions.php';
require_once 'include/message_functions.php';
require_once 'include/classlib_user.php';

 /*!
  * \file quick_message.php
  *
  * \brief QuickHandler for message-object.
  * \see specs/quick_suite.txt (3c)
  */

// see specs/quick_suite.txt (3c)
// mid=<MESSAGE_ID>
define('MESSAGEOPT_MID',  'mid');
define('MESSAGEOPT_OID',  'oid');

define('MESSAGECMD_INFO', 'info');
define('MESSAGE_COMMANDS', 'info');


 /*!
  * \class QuickHandlerMessage
  *
  * \brief Quick-handler class for handling message-object.
  */
class QuickHandlerMessage extends QuickHandler
{
   var $mid;
   var $oid; // for bulk-msg

   var $msg_row;
   var $user_rows;
   var $folder;

   function QuickHandlerMessage( $quick_object )
   {
      parent::QuickHandler( $quick_object );
      $this->mid = 0;
      $this->oid = 0;

      $this->msg_row = null;
      $this->user_rows = null;
      $this->folder = null;
   }


   // ---------- Interface ----------------------------------------

   function canHandle( $obj, $cmd ) // static
   {
      return ( $obj == QOBJ_MESSAGE ) && QuickHandler::matchRegex(MESSAGE_COMMANDS, $cmd);
   }

   function parseURL()
   {
      $this->mid = (int)get_request_arg(MESSAGEOPT_MID);
      $this->oid = (int)get_request_arg(MESSAGEOPT_OID);
   }

   function prepare()
   {
      global $player_row;
      $my_id = (int)@$player_row['ID'];

      // see specs/quick_suite.txt (3c)
      $dbgmsg = "QuickHandlerMessage.prepare($my_id,{$this->mid})";
      $this->checkCommand( $dbgmsg, MESSAGE_COMMANDS );
      $cmd = $this->quick_object->cmd;

      // check mid
      if( (string)$this->mid == '' || !is_numeric($this->mid) || $this->mid <= 0 )
         error('invalid_args', "$dbgmsg.bad_message");
      if( (string)$this->oid != '' && !is_numeric($this->oid) )
         error('invalid_args', "$dbgmsg.bad_uid.oid");
      $mid = $this->mid;

      // prepare command: info

      if( $cmd == MESSAGECMD_INFO )
      {
         /* see also the note about MessageCorrespondents.mid==0 in message_list_query() */
         $this->msg_row = DgsMessage::load_message( $dbgmsg, $mid, $my_id, $this->oid, /*full*/false );

         if( $this->is_with_option(QWITH_USER_ID) )
            $this->user_rows = User::load_quick_userinfo( array(
               $my_id, (int)$this->msg_row['other_ID'] ));

         $this->folder = $this->load_folder( $my_id, $this->msg_row['Folder_nr'] );
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
      $other_uid = (int)$row['other_id'];
      switch( $row['Sender'] ) // also see get_message_directions()
      {
         case 'M': $uid_from = $my_id; $uid_to = $my_id; break; // myself
         case 'S': $uid_from = 0;      $uid_to = $my_id; break; // system
         case 'Y': $uid_from = $my_id; $uid_to = $other_uid; break;
         case 'N': $uid_from = $other_uid; $uid_to = $my_id; break;
         default:  $uid_from = $uid_to = 0; break;
      }

      $this->addResultKey( 'id', (int)$row['ID'] );
      $this->addResultKey( 'user_from', $this->build_obj_user($uid_from, $this->user_rows) );
      $this->addResultKey( 'user_to',   $this->build_obj_user($uid_to, $this->user_rows) );
      $this->addResultKey( 'type', strtoupper($row['Type']) );
      $this->addResultKey( 'flags', QuickHandlerMessage::convertMessageFlags($row['Flags']) );
      $this->addResultKey( 'folder',
         QuickHandlerFolder::build_obj_folder(
            (int)$this->msg_row['Folder_nr'], $this->folder, $this->is_with_option(QWITH_FOLDER)) );
      $this->addResultKey( 'created_at', QuickHandler::formatDate(@$urow['X_Time']) );
      $this->addResultKey( 'thread', (int)$row['Thread'] );
      $this->addResultKey( 'level', (int)$row['Level'] );
      $this->addResultKey( 'message_prev', (int)$row['ReplyTo'] );
      $this->addResultKey( 'message_hasnext', ($row['X_Flow'] & FLOW_ANSWERED) ? 1 : 0 );
      $this->addResultKey( 'needs_reply', ($row['Folder_nr'] == FOLDER_NEW && $row['Type'] == MSGTYPE_INVITATION) ? 1 : 0 );
      $this->addResultKey( 'game_id', (int)$row['Game_ID'] );
      $this->addResultKey( 'subject', $row['Subject'] );
      $this->addResultKey( 'text', $row['Text'] );
   }//process_cmd_info

   function load_folder( $uid, $folder_id )
   {
      if( !$this->is_with_option(QWITH_FOLDER) )
         return null;

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
      return $arr;
   }//load_folder


   // ------------ static functions ----------------------------

   function convertMessageFlags( $flags )
   {
      $out = array();
      if( $flags & MSGFLAG_BULK )
         $out[] = 'BULK';
      return implode(',', $out);
   }

} // end of 'QuickHandlerMessage'

?>
