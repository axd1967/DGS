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
// input: $forum

   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");


   $result = mysql_query("SELECT Name AS Forumname FROM Forums WHERE ID=$forum");
   
   if( mysql_num_rows($result) != 1 )
      error("Unknown forum");


   extract(mysql_fetch_array($result));

   start_page("Forum $Forumname - New Topic", true, $logged_in, $player_row );

   $cols=2;
   $headline   = array("New topic" => "colspan=$cols");
   $links = LINK_FORUMS | LINK_THREADS;

   start_table($headline, $links, "", $cols);

   message_box($forum);
   
   end_table($links, $cols);
   end_page();
}
?>
