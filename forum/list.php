<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once( 'forum/forum_functions.php' );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error("not_logged_in");
   $my_id = $player_row['ID'];

   $forum_id = max(0,(int)@$_GET['forum']);
   $offset = max(0,(int)@$_GET['offset']);

   $switch_moderator = switch_admin_status( $player_row, ADMIN_FORUM, @$_REQUEST['moderator'] );
   $is_moderator = ($switch_moderator == 1);

   $forum = Forum::load_forum( $forum_id );
   $show_rows = $forum->load_threads( $my_id, $is_moderator, $RowsPerPage, $offset );
   // end of DB-stuff

   $disp_forum = new DisplayForum( $my_id, $is_moderator, $forum_id );
   $disp_forum->offset = $offset;
   $disp_forum->page_rows = $RowsPerPage;

   $disp_forum->cols = 5;
   $disp_forum->links = LINKPAGE_LIST;
   $disp_forum->links |= LINK_FORUMS | LINK_NEW_TOPIC | LINK_SEARCH;
   if( $offset > 0 )
      $disp_forum->links |= LINK_PREV_PAGE;
   if( $forum->has_more_threads() )
      $disp_forum->links |= LINK_NEXT_PAGE;
   if( $switch_moderator >= 0 )
      $disp_forum->links |= LINK_TOGGLE_MODERATOR;
   $disp_forum->headline = array(
      T_('Thread') => 'class=Subject',
      T_('Author') => 'class=Name',
      T_('Hits') => 'class=HitCnt',
      T_('Posts') => 'class=PostCnt',
      T_('Last post') => 'class=LastPost',
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

         $new = $disp_forum->get_new_string( $thread->last_changed, $thread->last_read );
         $subject = make_html_safe( $thread->subject, SUBJECT_HTML);
         $author = $thread->author->user_reference();

         $lpost_date   = $lpost->build_link_postdate( $thread->last_changed, 'class=LastPost' );
         $lpost_author = ( $lpost->author->is_set() )
            ? sprintf( ' <span class=PostUser>%s %s</span>', T_('by'), $lpost->author->user_reference())
            : '';

         echo "<tr class=Row$c>"
            . '<td class=Subject>' . anchor( $thread->build_url_post('new1'), $subject ) . $new . '</td>'
            . "<td class=Name>$author</td>"
            . "<td class=HitCnt>{$thread->count_hits}</td>"
            . "<td class=PostCnt>{$thread->count_posts}</td>"
            . "<td class=LastPost><span class=PostDate>$lpost_date</span>$lpost_author</td>"
            . "</tr>\n";
      }
   }

   $disp_forum->forum_end_table();

   end_page();
}
?>
