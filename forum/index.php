<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once 'include/classlib_userconfig.php';
require_once 'forum/forum_functions.php';

$GLOBALS['ThePage'] = new Page('ForumsList');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'forum.index');

   $my_id = $player_row['ID'];

   $cfg_pages = ConfigPages::load_config_pages($my_id);
   if ( !$cfg_pages )
      error('user_init_error', 'forum.index.init.config_pages');

   // toggle forumflag
   $toggleflag = (int)@$_REQUEST['toggleflag'] + 0;
   $toggle_baseurl = 'index.php';
   if ( ConfigPages::toggle_forum_flags($my_id, $toggleflag) )
      jump_to( 'forum/'.$toggle_baseurl );
   $show_lp_author = ( $cfg_pages->get_forum_flags() & FORUMFLAG_FORUM_SHOWAUTHOR );

   $switch_moderator = switch_admin_status( $player_row, ADMIN_FORUM, @$_REQUEST['moderator']);
   $is_moderator = ($switch_moderator == 1);

   $f_opts = new ForumOptions( $player_row );
   $forums = Forum::load_forum_list( $f_opts );
   // end of DB-stuff


   $disp_forum = new DisplayForum( $my_id, $is_moderator );
   $disp_forum->cols = 4;

   $disp_forum->links = LINKPAGE_INDEX;
   $disp_forum->links |= LINK_FORUMS | LINK_SEARCH;
   if ( $switch_moderator >=0 )
      $disp_forum->links |= LINK_TOGGLE_MODERATOR;

   $head_lastpost = sprintf( '%s <span class="HeaderToggle">(<a href="%s">%s</a>)</span>',
      T_('Last post'),
      $toggle_baseurl . '?toggleflag='.FORUMFLAG_FORUM_SHOWAUTHOR,
      ( ($show_lp_author) ? T_('Hide author') : T_('Show author') ));
   $disp_forum->headline = array(
      T_('Forums')    => '',
      T_('Threads')   => 'class="HeaderThreadCnt"',
      T_('Posts')     => 'class="HeaderPostCnt"',
      $head_lastpost  => 'class="HeaderLastPost"',
   );

   $title = T_('Forum list');
   start_page($title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $disp_forum->print_moderation_note('98%');
   $disp_forum->forum_start_table('Index');

   foreach ( $forums as $forum )
   {
      if ( !$f_opts->is_visible_forum( $forum->options ) )
         continue;

      $lpost = $forum->last_post;
      $lpost_date   = $lpost->build_link_postdate( $lpost->created, 'class=LastPost' );
      $lpost_author = ( $show_lp_author && $lpost->author->is_set() )
         ? sprintf( ' <span class=PostUser>%s %s</span>', T_('by'), $lpost->author->user_reference())
         : '';
      $new_str = ( $forum->has_new_posts ) ? span('NewFlag', T_('new#forum') ) : '';
      $fopts_str = $forum->build_options_text( $f_opts );

      //incompatible with: $c=($c % LIST_ROWS_MODULO)+1;
      echo '<tr class=Row1><td class=Name>'
         , '<a href="list.php?forum=', $forum->id, '">', make_html_safe( $forum->name, 'cell'), '</a>'
         , $new_str, $fopts_str
         , '</td>'
         , '<td class=ThreadCnt>', $forum->count_threads, '</td>'
         , '<td class=PostCnt>', $forum->count_posts, '</td>'
         , "<td class=LastPost><span class=PostDate>$lpost_date</span>$lpost_author</td>"
         , "</tr>\n";

      echo '<tr class=Row2>'
         , '<td colspan=4><dl><dd>', make_html_safe( $forum->description, 'faq'), '</dd></dl></td>'
         , "</tr>\n";
   }

   $disp_forum->forum_end_table();

   $menu_array = array();
   $menu_array[T_('Goban Editor')] = "goban_editor.php";
   if ( ALLOW_SURVEY_VOTE )
      $menu_array[T_('Surveys')] = "list_surveys.php";
   if ( Forum::is_admin() )
      $menu_array[T_('Show Forum Log')] =
         array( 'url' => 'forum/admin_show_forumlog.php', 'class' => 'AdminLink' );

   end_page(@$menu_array);
}
?>
