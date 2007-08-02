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

$TranslateGroups[] = "Forum";

require_once( "forum_functions.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error("not_logged_in");

   $forum = max(0,(int)@$_GET['forum']);
   $offset = max(0,(int)@$_GET['offset']);

   $Forumname = forum_name($forum, $moderated);

   $show_rows = $RowsPerPage+1;
   $result = mysql_query("SELECT Posts.Subject, Posts.Thread_ID, " .
                         "Posts.User_ID, Posts.PostsInThread, Players.Name, " .
                         "UNIX_TIMESTAMP(Forumreads.Time) AS Lastread, " .
                         "UNIX_TIMESTAMP(Posts.LastChanged) AS Lastchanged " .
                         "FROM (Posts) " .
                         "LEFT JOIN Players ON Players.ID=Posts.User_ID " .
//useless???                "LEFT JOIN Posts as LPost ON Posts.LastPost=LPost.ID " .
                         "LEFT JOIN Forumreads ON (Forumreads.User_ID=" . $player_row["ID"] .
                         " AND Forumreads.Thread_ID=Posts.Thread_ID) " .
                         "WHERE Posts.Forum_ID=$forum AND Posts.Parent_ID=0 " .
                         "ORDER BY Posts.LastChanged desc LIMIT $offset,$show_rows")
      or error('mysql_query_failed','forum_list.find');

   $show_rows = mysql_num_rows($result);

   $cols = 4;
   $links = LINKPAGE_LIST;

   $headline = array(T_("Thread")=>"width='50%'",T_("Author")=>"width='20%'",
                     T_("Posts")=>"width='10%'  align=center",T_("Last post")=>"width='20%'");
   $links |= LINK_FORUMS | LINK_NEW_TOPIC | LINK_SEARCH;

   if( $offset > 0 )
      $links |= LINK_PREV_PAGE;
   if( $show_rows > $RowsPerPage )
   {
      $show_rows = $RowsPerPage;
      $links |= LINK_NEXT_PAGE;
   }

   $is_moderator = false;
   if( (@$player_row['admin_level'] & ADMIN_FORUM) )
   {
      $links |= LINK_TOGGLE_MODERATOR;
      $is_moderator = set_moderator_cookie($player_row['ID']);
   }


   $title = T_('Forum').' - '.$Forumname;
   start_page($title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   print_moderation_note($is_moderator, '98%');

   forum_start_table('List', $headline, $links, $cols);

   $odd = true;
   while( ($row = mysql_fetch_array( $result)) && $show_rows-- > 0 )
   {
      $Name = '?';
      $Lastread = NULL;
      extract($row);

      $color = ( $odd ? "" : " bgcolor=white" );

      if( $PostsInThread > 0 or $is_moderator )
      {
         $new = get_new_string($Lastchanged, $Lastread);
         $Subject = make_html_safe( $Subject, SUBJECT_HTML);
         echo "<tr$color><td><a href=\"read.php?forum=$forum" . URI_AMP
           . "thread=$Thread_ID#new1\">$Subject</a>$new</td><td>" . make_html_safe($Name)
           . "</td><td align=center>" . $PostsInThread . "</td><td nowrap>"
           . date($date_fmt, $Lastchanged) . "</td></tr>\n";
         $odd = !$odd;
      }
   }

   forum_end_table($links, $cols);

   end_page();
}
?>
