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

   $Forumname = forum_name($forum);
   
   start_page("Forum $Forumname", true, $logged_in, $player_row );

   $result = mysql_query("SELECT Subject,Thread_ID,Lastchanged,User_ID,Name " .
                         "FROM Posts,Players " .
                         "WHERE Forum_ID=$forum AND Depth=1 AND Players.ID=User_ID " .
                         "ORDER BY Lastchanged");

   $cols = 3;
   $headline = array("Thread"=>"width=50%","Author"=>"width=25%","Date"=>"width=25%");
   $links = LINK_FORUMS | LINK_THREADS | LINK_NEW_TOPIC;
   start_table($headline, $links, "width=98%", $cols); 
               
   $odd = true;
   while( $row = mysql_fetch_array( $result ) )
   {
      extract($row);

      $color = ( $odd ? "" : " bgcolor=white" );
      
      if(  $Lastchanged > $player_row["Forumreaddate$forum"] )
         $new = "<font color=red size=\"-1\">&nbsp;&nbsp;new</i></font>";
      else
         $new = "";

      echo "<tr$color><td><a href=\"read.php?forum=$forum&thread=$Thread_ID\">$Subject</a>$new</td><td>$Name</td><td>$Lastchanged</td></tr>\n";
      $odd = !$odd;
   }

   end_table($links, $cols);

   end_page();
}
?>