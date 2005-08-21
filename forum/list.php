<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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

   $result = mysql_query("SELECT Subject, Posts.Thread_ID, Lastchanged, " .
                         "Posts.User_ID, Replies, Name, " .
                         "UNIX_TIMESTAMP(Forumreads.Time) AS Lastread, " .
                         "UNIX_TIMESTAMP(Lastchanged) AS Lastchangedstamp " .
                         "FROM Posts LEFT JOIN Players ON Players.ID=Posts.User_ID " .
                         "LEFT JOIN Forumreads ON (Forumreads.User_ID=" . $player_row["ID"] .
                         " AND Forumreads.Thread_ID=Posts.Thread_ID) " .
                         "WHERE Forum_ID=$forum AND Depth=1 " .
                         "ORDER BY Lastchanged desc LIMIT $offset,$MaxRowsPerPage")
   or die(mysql_error());

   $show_rows = $nr_rows = mysql_num_rows($result);
   if( $show_rows > $RowsPerPage )
      $show_rows = $RowsPerPage;

   $cols = 4;
   $headline = array(T_("Thread")=>"width=50%",T_("Author")=>"width=20%",
                     T_("Replies")=>"width=10%  align=center",T_("Date")=>"width=20%");
   $links = LINK_FORUMS | LINK_NEW_TOPIC;

   if( $offset > 0 )
      $links |= LINK_PREV_PAGE;
   if( $show_rows < $nr_rows )
      $links |= LINK_NEXT_PAGE;

   if( ($player_row['admin_level'] & ADMIN_FORUM) > 0 )
   {
      $links |= LINK_TOGGLE_EDITOR_LIST;
      $is_editor = set_editor_cookie();
   }

   start_table($headline, $links, "width=98%", $cols);

   $odd = true;
   while( $row = mysql_fetch_array( $result ) and $show_rows > 0)
   {
      $Name = '?';
      $Lastread = NULL;
      extract($row);

      $new = get_new_string($Lastchangedstamp, $Lastread);

      $color = ( $odd ? "" : " bgcolor=white" );

      if( $Replies >= 0 or $is_editor )
      {
         $Subject = make_html_safe( $Subject, true);
         echo "<tr$color><td><a href=\"read.php?forum=$forum".URI_AMP."thread=$Thread_ID\">$Subject</a>$new</td><td>" . make_html_safe($Name)
           . "</td><td align=center>" . $Replies . "</td><td nowrap>$Lastchanged</td></tr>\n";
         $odd = !$odd;
         $show_rows--;
      }
   }

   end_table($links, $cols);

   end_page();
}
?>