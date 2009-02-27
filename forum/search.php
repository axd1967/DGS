<?php
/*
Dragon Go Server
Copyright (C) 2001-2008  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

chdir('..');
require_once( "forum/forum_functions.php" );
require_once( "include/std_classes.php" );
require_once( "include/filter.php" );
require_once( "include/filterlib_mysqlmatch.php" );

//does not work. Need at least a special SearchHidden class
//TODO
define('MODERATOR_SEARCH', 0);


{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error("not_logged_in");
   $my_id = $player_row['ID'];

   $switch_moderator = switch_admin_status( $player_row, ADMIN_FORUM, @$_REQUEST['moderator'] );
   $is_moderator = ($switch_moderator == 1);

   // build forum-array for filter: ( Name => Forum_ID )
   $f_opts = new ForumOptions( $player_row );
   $arr_fnames = Forum::load_forum_names( $f_opts ); // name => id
   $farr_vis = array_values($arr_fnames); // IDs of visible forums
   $arr_forum = array( T_('All#forum') => '' );
   foreach( $arr_fnames as $name => $id )
      $arr_forum[$name] = 'Posts.Forum_ID=' . $id;

   $disp_forum = new DisplayForum( $my_id, $is_moderator );
   $disp_forum->links = LINKPAGE_SEARCH;

   $page = "search.php";
   $f_opts = new ForumOptions( $player_row );

   if( !MODERATOR_SEARCH || $switch_moderator < 0 )
      $disp_forum->is_moderator = 0;
   else
      $disp_forum->links |= LINK_TOGGLE_MODERATOR;

   $title = T_('Forum search');
   start_page($title, true, $logged_in, $player_row);
   echo "<h3 class=Header>$title</h3>\n";

   if( @$_REQUEST[FFORM_RESET_ACTION] )
   {
      $offset = 0;
      $order = 0;
   }
   else
   {
      $offset = max(0,(int)@$_REQUEST['offset']);
      $order = get_request_arg( 'order', 0 );
   }
   $disp_forum->offset = $offset;

   // for order-form-element
   $arr_order = array(
      0 => T_('Term relevance#forumsort'), // default: time-sort for no-term-search
      1 => T_('Creation date (new first)#forumsort'),
      2 => T_('Creation date (old first)#forumsort'),
      3 => T_('Modification date (new first)#forumsort'),
      4 => T_('Modification date (old first)#forumsort'),
   );
   $arr_sql_order = array( // index=arr_order[order]
      0 => 'Score DESC, Time DESC',
      1 => 'Time DESC',
      2 => 'Time ASC',
      3 => 'P.Lastchanged DESC',
      4 => 'P.Lastchanged ASC',
   );
   if( !is_numeric($order) || $order < 0 || $order >= count($arr_order) )
      $order = 0;
   $sql_order = $arr_sql_order[$order];

   // show max-rows
   $maxrows = (int)@$_REQUEST['maxrows'];
   $maxrows = get_maxrows( $maxrows, MAXROWS_PER_PAGE_FORUM, MAXROWS_PER_PAGE_DEFAULT );
   $arr_maxrows = build_maxrows_array( $maxrows, MAXROWS_PER_PAGE_FORUM );
   $disp_forum->max_rows = $maxrows;

   // static filters
   $ffilter = new SearchFilter();
   $ffilter->add_filter( 1, 'Selection', $arr_forum, true);
   $ffilter->add_filter( 2, 'MysqlMatch', 'Subject,Text', true);
   $ffilter->add_filter( 3, 'Text', 'PAuthor.Handle', true,
         array( FC_SIZE => 16 ));
   $ffilter->add_filter( 4, 'Selection',
         array( T_('All messages#forum') => '',
                T_('First messages#forum') => 'P.Parent_ID=0' ),
         true);
   $ffilter->add_filter( 5, 'RelativeDate', 'P.Time', true,
         array( FC_SIZE => 12, FC_TIME_UNITS => FRDTU_ABS | FRDTU_ALL ) );
   $ffilter->init(); // parse current value from $_REQUEST
   $filter2 =& $ffilter->get_filter(2);

   // form for static filters
   $fform = new Form( 'tableFSF', $page, FORM_POST );
   $fform->attach_table($ffilter); // add hiddens

   $fform->add_row( array(
         'DESCRIPTION', T_('Forum'),
         'FILTER',      $ffilter, 1 ));
   $fform->add_row( array(
         'DESCRIPTION', T_('Search terms#forum'),
         'CELL',        1, 'align=left width=500',
         'FILTER',      $ffilter, 2,
         'BR',
         'FILTERWARN',  $ffilter, 2, $FWRN1, $FWRN2.'<BR>', false,
         'FILTERERROR', $ffilter, 2, $FERR1, $FERR2.'<BR>', true,
         ));
   $fform->add_row( array(
         'DESCRIPTION', T_('Author (Userid)'),
         'FILTER',      $ffilter, 3,
         'FILTERERROR', $ffilter, 3, '<BR>'.$FERR1, $FERR2, true,
         ));
   $fform->add_row( array(
         'DESCRIPTION', T_('Message scope#forum'),
         'FILTER',      $ffilter, 4,
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
         'DESCRIPTION', T_('Number of hits#forum'),
         'SELECTBOX',   'maxrows', 1, $arr_maxrows, $maxrows, false, ));
   $fform->add_row( array(
         'TAB',
         'CELL',        1, 'align=left',
         'OWNHTML',     implode( '', $ffilter->get_submit_elements( $fform ) ) ));

   echo $fform->get_form_string();

   $query_filter = $ffilter->get_query(); // clause-parts for filter
   if( $DEBUG_SQL ) echo "WHERE: " . make_html_safe($query_filter->get_select()) . "<br>\n";
   if( $DEBUG_SQL ) echo "MARK-TERMS: " . make_html_safe( implode('|', $filter2->get_rx_terms()) ) . "<br>\n";

   if( $ffilter->has_query() )  // Display results
   {
      // get clause-part for mysql-match as select-col
      if( is_null($filter2->get_query()) )
         $query_match = '1';
      else
         $query_match = $filter2->get_match_query_part();

      $qsql = ForumPost::build_query_sql();
      $qsql->add_part( SQLP_FIELDS, "$query_match as Score" );
      if( !MODERATOR_SEARCH )
         $qsql->add_part( SQLP_WHERE, "P.Approved='Y'" );
      $qsql->add_part( SQLP_WHERE, "P.PosIndex>''" ); // '' == inactivated (edited)

      // restrict query on "visible" forums (matching admin-options)
      $qsql->add_part( SQLP_FROM,
         'INNER JOIN Forums ON Forums.ID=P.Forum_ID' );
      $qsql->add_part( SQLP_WHERE,
         'Forums.ID IN ('.implode(',', $farr_vis).')' );

      $qsql->merge($query_filter);

      if( $sql_order)
         $qsql->add_part( SQLP_ORDER, $sql_order );
      $qsql->add_part( SQLP_LIMIT, "$offset," . ($maxrows+1) ); // +1 for next-page detection
      $query = $qsql->get_select();

      if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query) . "<br>\n";

      $result = db_query( 'forum_search.find', $query );
      $nr_rows = mysql_num_rows($result);

      $cnt_rows = $maxrows; // read only what is needed (nr_rows maybe +1)
      $findposts = array();
      while( ($row = mysql_fetch_array( $result )) && $cnt_rows-- > 0 )
      {
         $post = ForumPost::new_from_row($row);
         $post->forum_name = @$arr_fnames[$post->forum_id];
         $post->score = $row['Score'];
         $findposts[] = $post;
      }
      // end of DB-stuff


      $disp_forum->cols = $cols = 2;
      $disp_forum->headline = array( T_('Search result') => "colspan=$cols" );

      $disp_forum->links |= LINK_FORUMS;
      if( $offset > 0 )
         $disp_forum->links |= LINK_PREV_PAGE;
      if( $nr_rows > $maxrows )
         $disp_forum->links |= LINK_NEXT_PAGE;

      // build navi-URL for paging (offset/maxrows set in forum_start_table/make_link_array-func)
      $rp = $ffilter->get_req_params();
      $rp->add_entry( 'order',  $order );

      // show resultset of search
      $rx_term = implode('|', $filter2->get_rx_terms() );
      $disp_forum->show_score = true; // for draw_post
      $disp_forum->set_rx_term( $rx_term );

      $disp_forum->print_moderation_note('99%');
      $disp_forum->forum_start_table('Search', $rp);
      echo "<tr><td colspan=$cols><table width=\"100%\" cellpadding=2 cellspacing=0 border=0>\n";

      foreach( $findposts as $post )
      {
         $is_my_post = ( $post->author->id == $my_id );
         $hidden = !$post->is_approved();
         if( $hidden && !$disp_forum->is_moderator && !$is_my_post )
            continue;

         $postClass = 'SearchResult';
         if( $hidden )
            $postClass = 'Hidden'; //need a special SearchHidden class

         $disp_forum->draw_post($postClass, $post, $is_my_post, null );

         echo "<tr><td colspan=$cols></td></tr>\n"; //separator
      }

      echo "</table></td></tr>\n";
      $disp_forum->forum_end_table();
   }

   end_page();
}
?>
