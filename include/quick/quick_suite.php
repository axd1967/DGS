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

require_once 'include/quick_common.php';
require_once 'include/quick/quick_game.php';
require_once 'include/quick/quick_game_info.php';
require_once 'include/quick/quick_game_list.php';
require_once 'include/quick/quick_user.php';
require_once 'include/quick/quick_message.php';
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
   function getQuickHandler( $obj=null, $cmd=null )
   {
      // NOTE: Handler must implement static interface-method canHandle(obj,cmd)
      //   List of handlers is checked if obj/cmd is supported;
      //   Order should be kept according to most frequent usage.
      static $quick_handler_list = array(
         'QuickHandlerGame',        // game: delete | set_handicap | move | resign | status_score | score
         'QuickHandlerGameList',    // game: list
         'QuickHandlerMessage',     // message: info
         'QuickHandlerUser',        // user: info
         'QuickHandlerGameInfo',    // game: info | get_notes
         'QuickHandlerWaitingroom', // wroom: list | info | new_game
         'QuickHandlerFolder',      // folder: list
         'QuickHandlerContact',     // contact: list
         'QuickHandlerBulletin',    // bulletin: list | mark_read
      );

      if( is_null($obj) )
         $obj = get_request_arg('obj');
      if( is_null($cmd) )
         $cmd = get_request_arg('cmd');
      $quick_obj = new QuickObject( $obj, $cmd );

      $quick_handler = null;
      foreach( $quick_handler_list as $handler_class )
      {
         if( call_user_func( array($handler_class, 'canHandle'), $obj, $cmd ) ) // Handler::canHandle(obj,cmd)
            $quick_handler = new $handler_class( $quick_obj );
      }
      if( is_null($quick_handler) )
         error('invalid_args', "QuickSuite.getQuickHandler.no_handler($obj,$cmd)");

      return $quick_handler;
   }

} // end of 'QuickSuite'
?>
