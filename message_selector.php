<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

   $send_message = ( @$_REQUEST['send_message']
                  || @$_REQUEST['send_accept']
                  || @$_REQUEST['send_decline']
                  || @$_REQUEST['foldermove']
                  ) ;

   $preview = @$_REQUEST['preview'];

   if( !$send_message or $preview )
      include_once( 'message.php');
   else
      include_once( 'send_message.php');

?>
