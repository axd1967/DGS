<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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

require( "include/std_functions.php" );
include( "include/form_functions.php" );

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   if( !$uid )
   {
      if( eregi("uid=([0-9]+)", $HTTP_REFERER, $result) )
         $uid = $result[1];
   }


   if( $uid and $uid != $player_row["ID"] )
   {
      $result = mysql_query( "SELECT Handle FROM Players WHERE ID=$uid" );

      if( mysql_num_rows( $result ) == 1 )
      {
         extract(mysql_fetch_array($result));
      }

   }

   start_page("Send Message", true, $logged_in, $player_row );

   echo "<center>\n";
   echo "<B><font size=+1>New message:</font></B>\n<HR>\n";
   echo form_start( 'messageform', 'send_message.php', 'POST' );

   echo form_insert_row( 'DESCRIPTION', 'To (userid)',
                         'TEXTINPUT', 'to', 50, 80, $Handle );
   echo form_insert_row( 'DESCRIPTION', 'Subject',
                         'TEXTINPUT', 'subject', 50, 80, "" );
   echo form_insert_row( 'DESCRIPTION', 'Message',
                         'TEXTAREA', 'message', 50, 8, "" );
   echo form_insert_row( 'SUBMITBUTTON', 'send', 'Send message' );

   echo form_end();
   echo "</center>\n";

   end_page();

}

?>
