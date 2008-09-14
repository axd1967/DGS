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

$ThePage = new Page('ForumsList');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];

   $switch_moderator = switch_admin_status( $player_row, ADMIN_FORUM, @$_REQUEST['moderator']);
   $is_moderator = ($switch_moderator == 1);

   //TODO: recalc NEWs

   $forum_list = Forum::load_forum_list( $my_id );
   // end of DB-stuff

   $disp_forum = new DisplayForum( $my_id, $is_moderator );
   $disp_forum->cols = 4;

   $disp_forum->links = LINKPAGE_INDEX;
   $disp_forum->links |= LINK_SEARCH;
   if( $switch_moderator >=0 )
      $disp_forum->links |= LINK_TOGGLE_MODERATOR;

   $disp_forum->headline = array(
      T_('Forums')    => '',
      T_('Threads')   => 'class="HeaderThreadCnt"',
      T_('Posts')     => 'class="HeaderPostCnt"',
      T_('Last post') => 'class="LastPost"',
   );

   $title = T_('Forum list');
   start_page($title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $disp_forum->print_moderation_note('98%');
   $disp_forum->forum_start_table('Index');

   foreach( $forum_list as $forum )
   {
      $lpost = $forum->last_post;
      $lpost_date   = $lpost->build_link_postdate( $lpost->created, 'class=LastPost' );
      $lpost_author = ( $lpost->author->is_set() )
         ? sprintf( ' <span class=PostUser>%s %s</span>', T_('by'), $lpost->author->user_reference())
         : '';
      $new_str = ( $forum->count_new > 0 )
         ? $new_str = sprintf( '<span class="NewFlag">%s (%s)</span>', T_('new'), $forum->count_new )
         : '';

      //incompatible with: $c=($c % LIST_ROWS_MODULO)+1;
      echo '<tr class=Row1><td class=Name>'
         . '<a href="list.php?forum=' . $forum->id . '">' . make_html_safe( $forum->name, 'cell') . '</a>'
         . ( $forum->is_moderated()
            ? ' &nbsp;&nbsp;<span class=Moderated>[' . T_('Moderated') . ']</span>'
            : '')
         . $new_str
         . '</td>'
         . '<td class=ThreadCnt>' . $forum->count_threads . '</td>'
         . '<td class=PostCnt>' . $forum->count_posts . '</td>'
         . "<td class=LastPost><span class=PostDate>$lpost_date</span>$lpost_author</td>"
         . "</tr>\n";

      echo '<tr class=Row2>'
         . '<td colspan=4><dl><dd>' . make_html_safe( $forum->description, 'faq') . '</dd></dl></td>'
         . "</tr>\n";
   }

   $disp_forum->forum_end_table();

   end_page();
}
?>
