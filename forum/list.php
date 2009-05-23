<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once( 'include/classlib_userconfig.php' );
require_once( 'forum/forum_functions.php' );
require_once( 'include/form_functions.php' );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error("not_logged_in");
   $my_id = $player_row['ID'];
   $cfg_pages = ConfigPages::load_config_pages($my_id);

   $forum_id = max(0, (int)get_request_arg('forum'));
   $offset = max(0, (int)get_request_arg('offset'));
   unset( $_GET['forum_showrows'] );

   // show max-rows
   $maxrows = (int)@$_REQUEST['maxrows'];
   $maxrows = get_maxrows( $maxrows, MAXROWS_PER_PAGE, MAXROWS_PER_PAGE_DEFAULT );
   $arr_maxrows = build_maxrows_array( $maxrows, MAXROWS_PER_PAGE);

   // toggle forumflag
   $toggleflag = @$_REQUEST['toggleflag'] + 0;
   $toggle_baseurl = "list.php?forum=$forum_id"
      . ( $maxrows>0 ? URI_AMP."maxrows=$maxrows" : '' )
      . ( $offset>0 ? URI_AMP."offset=$offset" : '');
   if( ConfigPages::toggle_forum_flags($my_id, $toggleflag) )
   {
      jump_to( 'forum/'.$toggle_baseurl );
   }
   $show_lp_author = ( $cfg_pages->get_forum_flags() & FORUMFLAG_THREAD_SHOWAUTHOR );

   $forum = Forum::load_forum( $forum_id );
   $f_opts = new ForumOptions( $player_row );
   if( !$f_opts->is_visible_forum( $forum->options ) )
      error('forbidden_forum');

   $switch_moderator = switch_admin_status( $player_row, ADMIN_FORUM, @$_REQUEST['moderator'] );
   $is_moderator = ($switch_moderator == 1);

   $show_rows = $forum->load_threads( $my_id, $is_moderator, $maxrows, $offset );

   // recalc NEWs
   $FR = new ForumRead( $my_id );
   $FR->recalc_thread_reads( $forum->threads );
   // end of DB-stuff

   $disp_forum = new DisplayForum( $my_id, $is_moderator, $forum_id );
   $disp_forum->offset = $offset;
   $disp_forum->max_rows = $maxrows;

   $disp_forum->cols = 5;
   $disp_forum->links = LINKPAGE_LIST;
   $disp_forum->links |= LINK_FORUMS | LINK_NEW_TOPIC | LINK_SEARCH;
   if( $offset > 0 )
      $disp_forum->links |= LINK_PREV_PAGE;
   if( $forum->has_more_threads() )
      $disp_forum->links |= LINK_NEXT_PAGE;
   if( $switch_moderator >= 0 )
      $disp_forum->links |= LINK_TOGGLE_MODERATOR;

   $head_lastpost = sprintf( '%s <span class="HeaderToggle">(<a href="%s">%s</a>)</span>',
      T_('Last post'),
      $toggle_baseurl .URI_AMP.'toggleflag='.FORUMFLAG_THREAD_SHOWAUTHOR,
      ( ($show_lp_author) ? T_('Hide author') : T_('Show author') ));
   $disp_forum->headline = array(
      T_('Thread') => 'class=Subject',
      T_('Author') => 'class=Name',
      T_('Posts') => 'class=PostCnt',
      T_('Hits') => 'class=HitCnt',
      $head_lastpost => 'class=LastPost',
   );

   $title = sprintf( '%s - %s', T_('Forum'), $forum->name );
   start_page($title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $disp_forum->print_moderation_note('98%');
   $disp_forum->forum_start_table('List');

   $c=0;
   foreach( $forum->threads as $thread )
   {
      if( $thread->count_posts > 0 || $is_moderator )
      {
         $c=($c % LIST_ROWS_MODULO)+1;
         $lpost = $thread->last_post;

         $newstr = $disp_forum->get_new_string( NEWMODE_NEWCOUNT | NEWMODE_NO_LINK,
            $thread->count_new );
         $subject = make_html_safe( $thread->subject, SUBJECT_HTML);
         $author = $thread->author->user_reference();

         $lpost_date   = $lpost->build_link_postdate( $thread->last_changed, 'class=LastPost' );
         $lpost_author = ( $show_lp_author && $lpost->author->is_set() )
            ? sprintf(' <span class=PostUser>%s %s</span>', T_('by'), $lpost->author->user_reference())
            : '';

         echo "<tr class=Row$c>"
            . '<td class=Subject>' . anchor( $thread->build_url_post(''), $subject ) . $newstr . '</td>'
            . "<td class=Name>$author</td>"
            . "<td class=PostCnt>{$thread->count_posts}</td>"
            . "<td class=HitCnt>{$thread->count_hits}</td>"
            . "<td class=LastPost><span class=PostDate>$lpost_date</span>$lpost_author</td>"
            . "</tr>\n";
      }
   }

   $disp_forum->forum_end_table();

   // form for thread-list
   $form = new Form( 'tableFTL', 'list.php', FORM_GET );
   $xkey = ACCKEY_ACT_FILT_SEARCH;

   $form->add_row( array(
        'DESCRIPTION', T_('Number of threads#forum'),
        'SELECTBOX',   'maxrows', 1, $arr_maxrows, $maxrows, false,
        'CELL',        1, 'align=left',
        'OWNHTML',     '<input type="submit" name="forum_showrows" value="' . T_('Show Rows#forum')
                        . '" accesskey=' . attb_quote($xkey) . ' title=' . attb_quote("[&amp;$xkey]") . '>' ));
   $form->add_hidden('forum', $forum_id);
   $form->add_hidden('offset', $offset);
   echo $form->get_form_string();

   end_page();
}
?>
