<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/quick_common.php';
require_once 'include/quick/quick_game.php';
require_once 'include/quick/quick_game_info.php';
require_once 'include/quick/quick_game_list.php';
require_once 'include/quick/quick_game_notes.php';
require_once 'include/quick/quick_user.php';
require_once 'include/quick/quick_message.php';
require_once 'include/quick/quick_message_list.php';
require_once 'include/quick/quick_folder.php';
require_once 'include/quick/quick_contact.php';
require_once 'include/quick/quick_wroom.php';
require_once 'include/quick/quick_bulletin.php';

 /*!
  * \file quick_suite.php
  *
  * \brief Alternative "quick" interface to DGS functionality.
  */



 /*!
  * \class QuickSuite
  *
  * \brief Class to manage quick-functionality.
  */
class QuickSuite
{
   // ------------ static functions ----------------------------

   /*!
    * \brief Returns QuickHandler for given object and command (or taken from URL if args==null).
    * \note Fires error if no handler found.
    */
   public static function getQuickHandler( $obj=null, $cmd=null )
   {
      // NOTE: Handler must implement static interface-method canHandle(obj,cmd)
      //   List of handlers is checked if obj/cmd is supported;
      //   Order should be kept according to most frequent usage.
      static $quick_handler_list = array(
         'QuickHandlerGame',        // game: delete | set_handicap | move | resign | status_score | score
         'QuickHandlerMessage',     // message: info | move_msg | delete_msg | send_msg | accept_inv | decline_inv
         'QuickHandlerWaitingroom', // wroom: info | delete | join
         'QuickHandlerBulletin',    // bulletin: list | mark_read
         'QuickHandlerUser',        // user: info
         'QuickHandlerGameInfo',    // game: info
         'QuickHandlerGameList',    // game: list
         'QuickHandlerMessageList', // message: list
         'QuickHandlerGameNotes',   // game: get_notes | save_notes | hide_notes | show_notes
         'QuickHandlerContact',     // contact: list
         'QuickHandlerFolder',      // folder: list
      );

      if ( is_null($obj) )
         $obj = get_request_arg('obj');
      if ( is_null($cmd) )
         $cmd = get_request_arg('cmd');
      $quick_obj = new QuickObject( $obj, $cmd );

      $quick_handler = null;
      foreach ( $quick_handler_list as $handler_class )
      {
         if ( call_user_func( array($handler_class, 'canHandle'), $obj, $cmd ) ) // Handler::canHandle(obj,cmd)
            $quick_handler = new $handler_class( $quick_obj );
      }
      if ( is_null($quick_handler) )
         error('invalid_args', "QuickSuite:getQuickHandler.no_handler($obj,$cmd)");

      return $quick_handler;
   }//getQuickHandler

} // end of 'QuickSuite'
?>
