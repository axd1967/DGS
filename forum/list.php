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


require("forum_functions.php");

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   $Forumname = forum_name($forum);

   start_page("Forum $Forumname", true, $logged_in, $player_row );

   $result = mysql_query("SELECT Subject, Posts.Thread_ID, Lastchanged, " .
                         "Posts.User_ID, Replies, Name, " .
                         "UNIX_TIMESTAMP(Forumreads.Time) AS Lastread, " .
                         "UNIX_TIMESTAMP(Lastchanged) AS Lastchangedstamp " .
                         "FROM Posts LEFT JOIN Players ON Players.ID=Posts.User_ID " .
                         "LEFT JOIN Forumreads ON (Forumreads.User_ID=" . $player_row["ID"] .
                         " AND Forumreads.Thread_ID=Posts.Thread_ID) " .
                         "WHERE Forum_ID=$forum AND Depth=1 AND Approved='Y'" .
                         "ORDER BY Lastchanged desc")
   or die(mysql_error());

   $cols = 4;
   $headline = array("Thread"=>"width=50%","Author"=>"width=20%",
                     "Replies"=>"width=10%  align=center","Date"=>"width=20%");
   $links = LINK_FORUMS | LINK_NEW_TOPIC;
   start_table($headline, $links, "width=98%", $cols);

   $odd = true;
   while( $row = mysql_fetch_array( $result ) )
   {
      $Name = '?';
      $Lastread = NULL;
      extract($row);

      $new = get_new_string($Lastchangedstamp, $Lastread);

      $color = ( $odd ? "" : " bgcolor=white" );
      echo "<tr$color><td><a href=\"read.php?forum=$forum&thread=$Thread_ID\">$Subject</a>$new</td><td>" . make_html_safe($Name) . "</td><td align=center>" . ($Replies-1) . "</td><td nowrap>$Lastchanged</td></tr>\n";
      $odd = !$odd;
   }

   end_table($links, $cols);

   end_page();
}
?>