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

   start_page(T_('Forum') . " - Search", true, $logged_in, $player_row );

   $is_moderator = false;
   $links = 0;

   if( ($player_row['admin_level'] & ADMIN_FORUM) > 0 )
   {
      $links |= LINK_TOGGLE_MODERATOR;

      if( @$_GET['show'] > 0 )
         approve_message( @$_GET['show'], $thread, $forum, true );
      else if( @$_GET['hide'] > 0 )
         approve_message( @$_GET['hide'], $thread, $forum, false );

      $is_moderator = set_moderator_cookie();
   }

   $offset = max(0,(int)@$_REQUEST['offset']);
   $search_terms = mysql_escape_string(@$_REQUEST['search_terms']);

   echo "<center><h4><font color=$h3_color>Forum search</font></H4></center>\n";


   // Search form
   echo '
<CENTER>
<FORM name="search" action="search.php" method="GET">
        Search terms:
        <INPUT type="text" name="search_terms" value="" tabindex="1" size="40" maxlength="80">
        <INPUT type="submit" name="action" value="Do search" tabindex="2">
</FORM>
</CENTER>
';

   if( $search_terms )  // Display results
   {
      $query = "SELECT Posts.*, MATCH (Subject,Text) AGAINST ('$search_terms') as Score, " .
         "Players.ID AS uid, Players.Name, Players.Handle " .
         "FROM Posts  LEFT JOIN Players ON Posts.User_ID=Players.ID " .
         "WHERE MATCH (Subject,Text) AGAINST ('$search_terms') " .
         "LIMIT $offset,$MaxSearchPostsPerPage";

      $result = mysql_query($query) or die(mysql_error());

      $cols=5;
      $headline = array(T_("Reading thread") => "colspan=$cols");
      $links = LINK_NEXT_PAGE;

      start_table($headline, $links, 'width="99%"', $cols);

      while( $row = mysql_fetch_array( $result ) )
      {
         draw_post('normal', false, $row['Subject'], $row['Text']);
      }
   }

   end_page();
}
?>
