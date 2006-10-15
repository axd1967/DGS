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

   $is_moderator = false;
   $links = LINKPAGE_SEARCH;

   if( ($player_row['admin_level'] & ADMIN_FORUM) > 0 )
   {
      $links |= LINK_TOGGLE_MODERATOR;

      if( @$_GET['show'] > 0 )
         approve_message( @$_GET['show'], $thread, $forum, true );
      else if( @$_GET['hide'] > 0 )
         approve_message( @$_GET['hide'], $thread, $forum, false );

      $is_moderator = set_moderator_cookie($player_row['ID']);
   }


   $title = T_('Forum') . " - " . T_('Search');
   start_page($title, true, $logged_in, $player_row );
   echo "<center><h3><font color=$h3_color>$title</font></h3></center>\n";

   $offset = max(0,(int)@$_REQUEST['offset']);
   $search_terms = get_request_arg('search_terms');
   $bool = (int)(@$_REQUEST['bool']) > 0;


   // Search form
   echo '
<CENTER>
<FORM name="search" action="search.php" method="GET">
';

   echo T_('Search terms') . ':&nbsp;&nbsp;';

   echo '<INPUT type="text" name="search_terms" value="' 
         . textarea_safe($search_terms) . '" tabindex="1" size="40" maxlength="80">';

   echo '
        <INPUT type="submit" name="action" value="'.T_('Search').'" tabindex="2">
<p>
        <INPUT type="checkbox" name="bool" value="1"' . ($bool ? ' checked' : '' ) 
            . ' tabindex="3">' . T_('Boolean mode') . '
</p></FORM>
</CENTER>
';

   if( $search_terms )  // Display results
   {
      $query = "SELECT Posts.*, " .
         "UNIX_TIMESTAMP(Posts.Lastedited) AS Lasteditedstamp, " .
         "UNIX_TIMESTAMP(Posts.Lastchanged) AS Lastchangedstamp, " .
         "UNIX_TIMESTAMP(Posts.Time) AS Timestamp, " .
         "MATCH (Subject,Text) AGAINST ('".mysql_escape_string($search_terms)."'" .
         ($bool ? ' IN BOOLEAN MODE' : '') . ") as Score, " .
         "Players.ID AS uid, Players.Name, Players.Handle, " .
         "Forums.Name as ForumName " .
         "FROM (Posts) LEFT JOIN Players ON Posts.User_ID=Players.ID " .
         "LEFT JOIN Forums ON Forums.ID = Posts.Forum_ID " .
         "WHERE MATCH (Subject,Text) AGAINST ('".mysql_escape_string($search_terms)."'" .
         ($bool ? ' IN BOOLEAN MODE' : '') . ") AND Approved='Y' " .
         "AND PosIndex IS NOT NULL " .
         ($bool ? 'ORDER BY TIME DESC ' : '') .
         "LIMIT $offset,$MaxSearchPostsPerPage";

      $result = mysql_query($query)
         or error("mysql_query_failed",'forum_search.find');

      $show_rows = $nr_rows = mysql_num_rows($result);
      if( $show_rows > $SearchPostsPerPage )
         $show_rows = $SearchPostsPerPage;

      $cols=2;
      $headline = array(T_("Search result") => "colspan=$cols");

      $links |= LINK_FORUMS;
      if( $offset > 0 ) $links |= LINK_PREV_PAGE;
      if( $show_rows < $nr_rows ) $links |= LINK_NEXT_PAGE;


      start_table($headline, $links, 'width="99%"', $cols);
      echo "<tr><td colspan=$cols><table width=\"100%\" cellpadding=2 cellspacing=0 border=0>\n";

      while( $row = mysql_fetch_array( $result ) )
      {
         extract($row);
         draw_post('search_result', false, $row['Subject'], $row['Text']);
         echo "<tr><td colspan=$cols></td></tr>\n";
      }
      echo "</table></td></tr>\n";
      end_table($links, $cols);
   }

   end_page();
}
?>
