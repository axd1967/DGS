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
                         "Posts.User_ID, Posts.PostsInThread, " .
                         "Players.Handle, Players.Name, " .
                         "UNIX_TIMESTAMP(Forumreads.Time) AS Lastread, " .
                         "UNIX_TIMESTAMP(Posts.LastChanged) AS Lastchanged " .
                         "FROM (Posts) " .
                         "LEFT JOIN Players ON Players.ID=Posts.User_ID " .
                         "LEFT JOIN Forumreads ON (Forumreads.User_ID=" . $player_row["ID"] .
                         " AND Forumreads.Thread_ID=Posts.Thread_ID) " .
                         "WHERE Posts.Forum_ID=$forum AND Posts.Parent_ID=0 " .
                         "ORDER BY Posts.LastChanged desc LIMIT $offset,$show_rows")
      or error('mysql_query_failed','forum_list.find');

   $show_rows = mysql_num_rows($result);

   $cols = 4;
   $links = LINKPAGE_LIST;

   $headline = array(
            T_('Thread') => 'class=Subject', //"width='50%'",
            T_('Author') => 'class=Name', //"width='20%'",
            T_('Posts') => 'class=PostCnt', //"width='10%'  align=center",
            T_('Last post') => 'class=PostDate'); //"width='20%'nowrap");
   $links |= LINK_FORUMS | LINK_NEW_TOPIC | LINK_SEARCH;

   if( $offset > 0 )
      $links |= LINK_PREV_PAGE;
   if( $show_rows > $RowsPerPage )
   {
      $show_rows = $RowsPerPage;
      $links |= LINK_NEXT_PAGE;
   }

   $is_moderator = switch_admin_status( $player_row, ADMIN_FORUM, @$_REQUEST['moderator']);
   if( $is_moderator < 0 )
      $is_moderator = 0;
   else
   {
      $links |= LINK_TOGGLE_MODERATOR;
   }


   $title = T_('Forum').' - '.$Forumname;
   start_page($title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   print_moderation_note($is_moderator, '98%');

   forum_start_table('List', $headline, $links, $cols);

   $c=0;
   while( ($row = mysql_fetch_array( $result)) && $show_rows-- > 0 )
   {
      $Handle = '';
      $Name = '';
      $Lastread = NULL;
      extract($row);

      if( $PostsInThread > 0 || $is_moderator )
      {
         $c=($c % LIST_ROWS_MODULO)+1;
         $new = get_new_string($Lastchanged, $Lastread);
         //Posts.User_ID, Players.Handle, Players.Name
         if( empty($Handle) ) $Handle= UNKNOWN_VALUE;
         if( empty($Name) ) $Name= UNKNOWN_VALUE;
         $author= user_reference( REF_LINK, 0, '', $User_ID,
               make_html_safe($Name), $Handle);
         $Subject = make_html_safe( $Subject, SUBJECT_HTML);
         echo "<tr class=Row$c>"
            . "<td class=Subject><a href=\"read.php?forum=$forum" . URI_AMP
               . "thread=$Thread_ID#new1\">$Subject</a>$new</td>"
            . "<td class=Name>$author</td>"
            . "<td class=PostCnt>$PostsInThread</td>"
            . "<td class=PostDate>" . date($date_fmt, $Lastchanged)
               . "</td></tr>\n";
      }
   }
   mysql_free_result($result);

   forum_end_table($links, $cols);

   end_page();
}
?>
