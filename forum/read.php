<?php
/*
Dragon Go Server
Copyright (C) 2001  Erik Ouchterlony

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


include("forum_functions.php");

{
   connect2mysql();


   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   $result = mysql_query("SELECT Name as Forumname from Forums where ID=$forum");
   
   if( mysql_num_rows($result) != 1 )
      error("Unknown forum");
   
   extract(mysql_fetch_array($result));

   start_page("Reading forum $Forumname", true, $logged_in, $player_row );



   $result = mysql_query("SELECT Posts.*, Lastchanged from Posts, Threads, count(*) AS Count " .
                         "WHERE Posts.Thread_ID=Threads.ID " .
                         "ORDER BY Lastchanged, PosIndex");

   start_table("", array());

   while( $row = mysql_fetch_array( $result ) )
   {
      
   }

   end_page();
}
?>
