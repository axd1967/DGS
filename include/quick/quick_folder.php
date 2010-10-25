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
require_once 'include/message_functions.php';
require_once 'include/classlib_userconfig.php';


 /*!
  * \file quick_folder.php
  *
  * \brief QuickHandler for folder-object.
  * \see specs/quick_suite.txt (3d)
  */

// see specs/quick_suite.txt (3d)
define('FOLDERCMD_LIST', 'list');
define('FOLDER_COMMANDS', 'list');


 /*!
  * \class QuickHandlerFolder
  *
  * \brief Quick-handler class for handling folder-object.
  */
class QuickHandlerFolder extends QuickHandler
{
   var $folders;
   var $cfg_pages;

   function QuickHandlerFolder( $quick_object )
   {
      parent::QuickHandler( $quick_object );
      $this->folders = null;
      $this->cfg_pages = null;
   }


   // ---------- Interface ----------------------------------------

   function canHandle( $obj, $cmd ) // static
   {
      return ( $obj == QOBJ_FOLDER ) && QuickHandler::matchCommand(FOLDER_COMMANDS, $cmd);
   }

   function parseURL()
   {
   }

   function prepare()
   {
      global $player_row;
      $uid = (int)@$player_row['ID'];

      // see specs/quick_suite.txt (3d)
      $dbgmsg = "QuickHandlerFolder.prepare($uid)";
      $this->checkCommand( $dbgmsg, FOLDER_COMMANDS );
      $cmd = $this->quick_object->cmd;

      init_standard_folders();

      // prepare command: list

      if( $cmd == FOLDERCMD_LIST )
      {
         $this->cfg_pages = ConfigPages::load_config_pages( $uid, CFGCOLS_STATUS_GAMES );
         $this->folders = get_folders( $uid, /*remove-all-received-folder*/true );
      }

      // check for invalid-action

   }//prepare

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   function process()
   {
      $cmd = $this->quick_object->cmd;
      if( $cmd == FOLDERCMD_LIST )
         $this->process_cmd_list();
   }

   function process_cmd_list()
   {
      $out = array();
      if( is_array($this->folders) )
      {
         foreach( $this->folders as $folder_id => $arr )
            $out[] = QuickHandlerFolder::build_obj_folder( $folder_id, $arr, /*with*/true, $this->cfg_pages );
      }

      $this->add_list( QOBJ_FOLDER, $out );
   }//process_cmd_list


   // ------------ static functions ----------------------------

   function build_obj_folder( $folder_id, $arr, $with=true, $cfg_pages=null )
   {
      if( !$with )
         return array( 'id' => $folder_id );

      if( $folder_id == FOLDER_DESTROYED ) // invisible-folder
         $arr = array( T_('Destroyed#folder'), 'FF88EE00', '000000' );
      elseif( $folder_id == FOLDER_ALL_RECEIVED ) // pseudo folder
         $arr = array( T_('All Received'), '00000000', '000000' );

      $out = array(
         'id'        => $folder_id,
         'name'      => $arr[0],
         'system'    => ($folder_id < USER_FOLDERS) ? 1 : 0,
         'color_bg'  => $arr[1],
         'color_fg'  => $arr[2],
      );
      if( !is_null($cfg_pages) )
         $out['on_status'] = ($this->cfg_pages->get_status_folder_visibility($folder_id) > 0) ? 1 : 0;
      return $out;
   }//build_obj_folder

} // end of 'QuickHandlerFolder'

?>