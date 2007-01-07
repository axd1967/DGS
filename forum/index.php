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

/*

Description of mysql table Posts:

Parent_ID: The post this post replies to.
Thread_ID: The post which started the thread.
AnswerNr: The number of siblings (i.e. posts to the parent post) when it was posted.
Depth: The number of generations, the tread starter has depth 0.

PosIndex: A string used to sort the posts in the thread. The string is composed of the
          PosIndex of the parent plus two letters which encodes the AnswerNr in base 64,
          where the letters are given by $order_str (see forum_functions.php).

          If PosIndex is NULL, the post has been edited and is not active.

The following three pieces of data are only used for the first post of the thread.
PostsInThread: Total number of approved posts in the thread.
LastPost: Id of the last post in the thread.
*/

$TranslateGroups[] = "Forum";

require_once( "forum_functions.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error("not_logged_in");

   $links = LINKPAGE_INDEX;



   $result = mysql_query("SELECT Forums.ID,Description,Name,Moderated, PostsInForum, " .
                         "UNIX_TIMESTAMP(Posts.Time) AS Timestamp " .
                         "FROM (Forums) LEFT JOIN Posts ON Forums.LastPost=Posts.ID " .
                         "ORDER BY SortOrder")
      or error("mysql_query_failed",'forum_index1');

   $cols = 4;
   $headline   = array(T_("Forums") => "colspan=$cols");
   $links |= LINK_SEARCH;

   $is_moderator = false;
   if( ($player_row['admin_level'] & ADMIN_FORUM) > 0 )
   {
      $links |= LINK_TOGGLE_MODERATOR;
      $is_moderator = set_moderator_cookie($player_row['ID']);
   }

   $title = T_('Forum list');
   start_page($title, true, $logged_in, $player_row );
   echo "<center><h3><font color=$h3_color>$title</font></h3></center>\n";

   print_moderation_note($is_moderator, '98%');


   start_table($headline, $links, "width='98%'", $cols);


   while( $row = mysql_fetch_array( $result ) )
   {
      extract($row);
      $date = date($date_fmt, $Timestamp);

      if( empty($row['Timestamp']) )
      {
         $date='&nbsp;&nbsp;-';
         $Count = 0;
      }

      echo '<tr><td width="60%"><b>&nbsp;<a href="list.php?forum=' . $ID . '">' . $Name .
         '</a></b>'. ( $Moderated == 'Y' ? ' &nbsp; <font color="#ff4466">[' . T_('Moderated') . ']</font>' : '') .'</td>' .
         '<td nowrap>'.T_('Posts').': <b>' . $PostsInForum .  '&nbsp;&nbsp;&nbsp;</b></td>' .
         '<td nowrap>'.T_('Last post').': <b>' . $date . "</b></td></tr>\n" .
         '<tr bgcolor=white><td colspan=3><dl><dt><dd>&nbsp;' . $Description .
         "</dl></td></tr>\n";
   }

   end_table($links, $cols);

   end_page();
}
?>