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

require_once 'include/std_functions.php';
require_once 'include/quick/quick_suite.php';


$TheErrors->set_mode(ERROR_MODE_QUICK_SUITE);

if( $is_down )
{
   error('server_down', $is_down_message);
}
else
{
   disable_cache();
   connect2mysql();

   $logged_in = who_is_logged( $player_row, LOGIN_QUICK_SUITE );
   if( !$logged_in )
      error('not_logged_in', 'quick_do.logged_in');

   // call quick-handler
   $quick_handler = QuickSuite::getQuickHandler();
   $quick_handler->process();
   $result = $quick_handler->getResult();
   //$result = array( 'error' => '' ); // success

   // output HTTP-header
   //TODO? header( 'Content-Type: application/json' );
   echo dgs_json_encode( $result );
}
?>
