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

/*

Description of mysql table Posts:

Parent_ID: The post this post replies to.
Thread_ID: The post which started the thread.
AnswerNr: The number of siblings (i.e. posts to the parent post) when it was posted.
Depth: The number of generations, the tread starter has depth 0.

PosIndex: A string used to sort the posts in the thread. The string is composed of the
          PosIndex of the parent plus two letters which encodes the AnswerNr in base 64,
          where the letters are given by $order_str (see forum_functions.php).
          Any PosIndex will begin by '**' (2 times the first char of $order_str)
          If PosIndex is empty, the post has been edited and is not active.

The following three pieces of data are only used for the first post of the thread.
PostsInThread: Total number of approved posts in the thread.
LastPost: Id of the last post in the thread.
LastChanged: Time of the last post in the thread.
*/

$TranslateGroups[] = "Forum";

require_once( "forum_functions.php" );
$ThePage = new Page('ForumsList');

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
   if( (@$player_row['admin_level'] & ADMIN_FORUM) )
   {
      $links |= LINK_TOGGLE_MODERATOR;
      $is_moderator = set_moderator_cookie($player_row['ID']);
   }

   $title = T_('Forum list');
   start_page($title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   print_moderation_note($is_moderator, '98%');


   forum_start_table('Index',$headline, $links, $cols);


   while( $row = mysql_fetch_array( $result ) )
   {
      extract($row);
      if( empty($row['Timestamp']) )
      {
         $date='&nbsp;&nbsp;-';
         $Count = 0;
      }
      else
         $date = date($date_fmt, $Timestamp);

      //incompatible with: $c=($c % LIST_ROWS_MODULO)+1;
      echo "<tr class=Row1><td class=Name>" .
         '<a href="list.php?forum=' . $ID . '">' . $Name . '</a>'
         . ( $Moderated == 'Y'
            ? ' &nbsp;&nbsp;<span class=Moderated>[' . T_('Moderated') . ']</span>'
            : '') .'</td>' .
         '<td class=PostCnt>'.T_('Posts').': <strong>'
               . $PostsInForum . '</strong></td>' .
         '<td class=PostDate>'.T_('Last post').': <strong>'
               . $date . "</strong></td></tr>\n";

      echo '<tr class=Row2><td colspan=3><dl><dd>' . $Description .
         "</dl></td></tr>\n";
   }
   mysql_free_result($result);

   forum_end_table($links, $cols);

   end_page();
}
?>
