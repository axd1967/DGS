<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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
chdir("../");
require_once( "include/std_classes.php" );
require_once( "include/filter.php" );
chdir("forum/");

//does not work. Need at least a special SearchHidden class
define('MODERATOR_SEARCH', 0);


{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error("not_logged_in");

   $uid = $player_row["ID"];
   $page = "search.php";
   $is_moderator = false;
   $links = LINKPAGE_SEARCH;

   if( MODERATOR_SEARCH && (@$player_row['admin_level'] & ADMIN_FORUM) )
   {
      $links |= LINK_TOGGLE_MODERATOR;

/*
      if( @$_REQUEST['show'] > 0 )
         approve_message( @$_REQUEST['show'], $thread, $forum, true );
      else if( @$_REQUEST['hide'] > 0 )
         approve_message( @$_REQUEST['hide'], $thread, $forum, false );
*/

      $is_moderator = set_moderator_cookie($player_row['ID']);
   }


   $title = T_('Forum search');
   $prefix = '';
   start_page($title, true, $logged_in, $player_row);
   echo "<h3 class=Header>$title</h3>\n";

   if ( @$_REQUEST[FFORM_RESET_ACTION] )
   {
      $offset = 0;
      $order = 0;
   }
   else
   {
      $offset = max(0,(int)@$_REQUEST['offset']);
      $order = get_request_arg( 'order', 0 );
   }

   // build forum-array for filter: ( Name => Forum_ID )
   $arr_forum = array( T_('All#forum') => '' );
   $query = "SELECT ID, Name FROM Forums ORDER BY SortOrder";
   $result = mysql_query($query)
      or error('mysql_query_failed','forum_name_search.find');
   while( $row = mysql_fetch_array( $result ) )
      $arr_forum[$row['Name']] = "Posts.Forum_ID=" . $row['ID'];
   mysql_free_result($result);

   // for order-form-element
   $arr_order = array(
      0 => T_('Term relevance#forumsort'), // default: time-sort for no-term-search
      1 => T_('Creation date (new first)#forumsort'),
      2 => T_('Creation date (old first)#forumsort'),
      3 => T_('Modification date (new first)#forumsort'),
      4 => T_('Modification date (old first)#forumsort'),
   );
   $arr_sql_order = array( // index=arr_order[order]
      'Score DESC, Time DESC',
      'Time DESC',
      'Time ASC',
      'Posts.Lastchanged DESC',
      'Posts.Lastchanged ASC',
   );
   if ( !is_numeric($order) || $order < 0 || $order >= count($arr_order) )
      $order = 0;
   $sql_order = $arr_sql_order[$order];

   // static filters
   $ffilter = new SearchFilter();
   $ffilter->add_filter( 1, 'Selection', $arr_forum, true);
   $ffilter->add_filter( 2, 'MysqlMatch', 'Subject,Text', true);
   $ffilter->add_filter( 3, 'Text', 'P.Handle', true,
         array( FC_SIZE => 16 ));
   $ffilter->add_filter( 4, 'Selection',     #! \todo Handle New Forum-Posts
         array( T_('All messages#forum') => '',
                T_('First messages#forum') => 'Posts.Parent_ID=0' ),
         true);
   $ffilter->add_filter( 5, 'RelativeDate', 'Posts.Time', true,
         array( FC_SIZE => 12, FC_TIME_UNITS => FRDTU_ABS | FRDTU_ALL ) );
   #global $NOW;
   #$ffilter->add_filter( 6, 'Boolean', new QuerySQL( SQLP_FIELDS, "((Posts.Time + INTERVAL ".DAYS_NEW_END." DAY > FROM_UNIXTIME($NOW) AND ISNULL(FR.Time)) OR Posts.Time > FR.Time) AS NewPost", SQLP_FROM, "LEFT JOIN Forumreads AS FR ON FR.User_ID=$uid AND FR.Thread_ID=Posts.Thread_ID", SQLP_HAVING, "NewPost>0" ), true, array( FC_LABEL => T_('Restrict to new messages') ) ); //! \todo Handle New Forum-Posts
   $ffilter->init(); // parse current value from $_REQUEST
   $filter2 =& $ffilter->get_filter(2);

   // form for static filters
   $fform = new Form( 'tableFSF', $page, FORM_POST );
   $fform->set_config( FEC_TR_ATTR, 'valign=top' );
   $fform->attach_table($ffilter); // add hiddens

   $fform->add_row( array(
         'DESCRIPTION', T_('Forum'),
         'FILTER',      $ffilter, 1 ));
   $fform->add_row( array(
         'DESCRIPTION', T_('Search terms#forum'),
         'CELL',        1, 'align=left width=500',
         'FILTER',      $ffilter, 2,
         'BR',
         'FILTERERROR', $ffilter, 2, $FERR1, $FERR2."<BR>", true,
         ));
   $fform->add_row( array(
         'DESCRIPTION', T_('Author (Userid)'),
         'FILTER',      $ffilter, 3,
         'FILTERERROR', $ffilter, 3, "<BR>$FERR1", $FERR2, true,
         ));
   $fform->add_row( array(
         'DESCRIPTION', T_('Message scope#forum'),
         'FILTER',      $ffilter, 4,
         #'BR',
         #'FILTER',      $ffilter, 6, //TODO
         ));
   $fform->add_row( array(
         'DESCRIPTION', T_('Date#forum'),
         'CELL',        1, 'align=left',
         'FILTER',      $ffilter, 5,
         'FILTERERROR', $ffilter, 5, "<BR>$FERR1", $FERR2, true,
         ));
   $fform->add_row( array(
         'DESCRIPTION', T_('Order#forum'),
         'SELECTBOX',   'order', 1, $arr_order, $order, false, ));
   $fform->add_row( array(
         'TAB',
         'CELL',        1, 'align=left',
         'OWNHTML',     implode( '', $ffilter->get_submit_elements( 'x', 'e' ) ) ));

   echo $fform->get_form_string();

   $query_filter = $ffilter->get_query(); // clause-parts for filter
   if ( $DEBUG_SQL ) echo "WHERE: " . make_html_safe($query_filter->get_select()) . "<br>\n";
   if ( $DEBUG_SQL ) echo "MARK-TERMS: " . make_html_safe( implode('|', $filter2->get_terms()) ) . "<br>\n";

   if( $ffilter->has_query() )  // Display results
   {
      // get clause-part for mysql-match as select-col
      if ( is_null($filter2->get_query()) )
         $query_match = "1";
      else
         $query_match = $filter2->get_match_query_part();

      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'Posts.*',
         'UNIX_TIMESTAMP(Posts.Lastedited) AS Lasteditedstamp',
         'UNIX_TIMESTAMP(Posts.Lastchanged) AS Lastchangedstamp',
         'UNIX_TIMESTAMP(Posts.Time) AS Timestamp',
         "$query_match as Score",
         'P.ID AS uid', 'P.Name', 'P.Handle',
         'Forums.Name as ForumName' );
      $qsql->add_part( SQLP_FROM,
         'Forums',
         'INNER JOIN Posts ON Forums.ID=Posts.Forum_ID ',
         'INNER JOIN Players AS P ON Posts.User_ID=P.ID ' );
      if( !MODERATOR_SEARCH )
         $qsql->add_part( SQLP_WHERE,
            "Approved='Y'" );
      $qsql->add_part( SQLP_WHERE,
         "PosIndex>''" ); // '' == inactivated (edited)
      $qsql->merge($query_filter);

      if ( $sql_order)
         $qsql->add_part( SQLP_ORDER, $sql_order );
      $qsql->add_part( SQLP_LIMIT, "$offset,$MaxSearchPostsPerPage" );
      $query = $qsql->get_select();

      if ( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query) . "<br>\n";

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

      // build navi-URL for paging
      $rp = $ffilter->get_req_params();
      #$rp->add_entry( 'offset', $offset ); set in forum_start_table-func
      $rp->add_entry( 'order',  $order );

      // show resultset of search
      $search_terms = implode('|', $filter2->get_terms() );
      $show_score = true; // used in draw_post per global-var

      print_moderation_note($is_moderator, '99%');

      forum_start_table('Search', $headline, $links, $cols, $rp);
      echo "<tr><td colspan=$cols><table width=\"100%\" cellpadding=2 cellspacing=0 border=0>\n";

      while( $row = mysql_fetch_array( $result ) )
      {
         extract($row); //needed for global vars of draw_post()

         $hidden = ($Approved == 'N');

         if( $hidden and !$is_moderator and $uid !== $player_row['ID'] )
            continue;

         $postClass = 'SearchResult';
         if( $hidden )
            $postClass = 'Hidden'; //need a special SearchHidden class

         draw_post($postClass, $uid == $player_row['ID'], $row['Subject'], $row['Text'], null, $search_terms);

         echo "<tr><td colspan=$cols></td></tr>\n"; //separator
      }
      mysql_free_result($result);

      echo "</table></td></tr>\n";
      forum_end_table($links, $cols);
   }

   end_page();
}
?>
