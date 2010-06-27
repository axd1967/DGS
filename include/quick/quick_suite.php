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

require_once 'include/quick_common.php';
//TODO require_once 'include/quick/quick_game.php';

 /*!
  * \file quick_suite.php
  *
  * \brief Alternative "quick" interface to DGS functionality.
  */

// quick-objects
define('QOBJ_GAME', 'game');



 /*!
  * \class QuickSuite
  *
  * \brief Class to manage quick-functionality.
  */
class QuickSuite
{
   // ------------ static functions ----------------------------

   function getQuickHandler( $obj=null )
   {
      if( is_null($obj) )
         $obj = get_request_arg('obj');

      /* TODO
      if( $obj == QOBJ_GAME )
         $quick_handler = new QuickHandlerGame();
      else
      */
         error('invalid_args', "QuickSuite.getQuickHandler($obj)");

      $quick_obj = new QuickObject( $obj, get_request_arg('cmd') );
      $quick_handler->parse( $quick_obj );
      $quick_handler->check();
      $quick_handler->prepare();

      return $quick_handler;
   }

} // end of 'QuickSuite'
?>
