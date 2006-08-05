<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

   $send_message = ( @$_REQUEST['send_message']
                  or @$_REQUEST['send_accept']
                  or @$_REQUEST['send_decline']
                  or @$_REQUEST['foldermove']
                  ) ;

   $preview = @$_REQUEST['preview'];

   if( !$send_message or $preview )
      include_once( 'message.php');
   else
      include_once( 'send_message.php');

?>