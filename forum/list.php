<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony

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


require_once( "forum_functions.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error("not_logged_in");

   $forum = max(0,(int)@$_GET['forum']);
   $offset = max(0,(int)@$_GET['offset']);

   $Forumname = forum_name($forum, $moderated);


   start_page(T_('Forum') . " - $Forumname", true, $logged_in, $player_row );

   echo "<center><h4><font color=$h3_color>$Forumname</font></H4></center>\n";

   $result = mysql_query("SELECT Posts.Subject, Posts.Thread_ID, " .
                         "Posts.User_ID, Posts.PostsInThread, Name, " .
                         "UNIX_TIMESTAMP(Forumreads.Time) AS Lastread, " .
                         "UNIX_TIMESTAMP(Posts.LastChanged) AS Lastchanged " .
                         "FROM Posts LEFT JOIN Players ON Players.ID=Posts.User_ID " .
                         "LEFT JOIN Posts as LPost ON Posts.LastPost=LPost.ID " .
                         "LEFT JOIN Forumreads ON (Forumreads.User_ID=" . $player_row["ID"] .
                         " AND Forumreads.Thread_ID=Posts.Thread_ID) " .
                         "WHERE Posts.Forum_ID=$forum AND Posts.Parent_ID=0 " .
                         "ORDER BY Posts.LastChanged desc LIMIT $offset,$MaxRowsPerPage")
      or error("mysql_query_failed",'forum_list1');

   $show_rows = $nr_rows = mysql_num_rows($result);

   if( $show_rows > $RowsPerPage )
      $show_rows = $RowsPerPage;

   $cols = 4;
   $headline = array(T_("Thread")=>"width=50%",T_("Author")=>"width=20%",
                     T_("Posts")=>"width=10%  align=center",T_("Last post")=>"width=20%");
   $links = LINK_FORUMS | LINK_NEW_TOPIC | LINK_SEARCH;

   if( $offset > 0 )
      $links |= LINK_PREV_PAGE;
   if( $show_rows < $nr_rows )
      $links |= LINK_NEXT_PAGE;

   $is_moderator = false;
   if( ($player_row['admin_level'] & ADMIN_FORUM) > 0 )
   {
      $links |= LINK_TOGGLE_MODERATOR_LIST;
      $is_moderator = set_moderator_cookie();
   }

   print_moderation_note($is_moderator, '98%');

   start_table($headline, $links, "width=98%", $cols);

   $odd = true;
   while( $row = mysql_fetch_array( $result ) and $show_rows > 0)
   {
      $Name = '?';
      $Lastread = NULL;
      extract($row);

      $new = get_new_string($Lastchanged, $Lastread);

      $color = ( $odd ? "" : " bgcolor=white" );

      if( $PostsInThread > 0 or $is_moderator )
      {
         $Subject = make_html_safe( $Subject, true);
         echo "<tr$color><td><a href=\"read.php?forum=$forum".URI_AMP."thread=$Thread_ID\">$Subject</a>$new</td><td>" . make_html_safe($Name)
           . "</td><td align=center>" . $PostsInThread . "</td><td nowrap>" .date($date_fmt, $Lastchanged) . "</td></tr>\n";
         $odd = !$odd;
         $show_rows--;
      }
   }

   end_table($links, $cols);

   end_page();
}
?>