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
require_once( 'forum/post.php' );


// use-case U04: show revision history of post
function revision_history( $display_forum, $post_id )
{
   $revhist_thread = new ForumThread();
   $revhist_thread->load_revision_history( $post_id );
   $revhist_thread->thread_post->last_edited = 0; // don't show last-edit
   // end of DB-stuff

   $display_forum->headline = array(
      T_('Revision history') => "colspan={$display_forum->cols}",
   );
   $display_forum->back_post_id = $post_id;
   $display_forum->links |= LINK_BACK_TO_THREAD;
   $display_forum->links &= ~LINK_REFRESH;

   $display_forum->forum_start_table('Revision');
   $display_forum->change_depth( 1 );
   $display_forum->draw_post( DRAWPOST_REPLY, $revhist_thread->thread_post, null );

   echo "<tr><td colspan={$display_forum->cols} height=2></td></tr>";
   $display_forum->change_depth( 2 );
   foreach( $revhist_thread->posts as $post )
   {
      $display_forum->draw_post( DRAWPOST_EDIT, $post, true, null );
      echo "<tr><td colspan={$display_forum->cols} height=2></td></tr>";
   }

   $display_forum->change_depth( -1 );
   $display_forum->forum_end_table();
} //revision_history



{
   #$DEBUG = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error("not_logged_in");
   $my_id = $player_row['ID'];
   $cfg_pages = ConfigPages::load_config_pages($my_id);

   $reply = @$_REQUEST['reply']+0;
   $forum_id = @$_REQUEST['forum']+0;
   $thread = @$_REQUEST['thread']+0;
   $edit = @$_REQUEST['edit']+0;
   $markread = get_request_arg('markread', ''); // syntax of ForumRead::mark_read
   $rx_term = get_request_arg('xterm', '');

   // toggle forumflag
   $toggleflag = @$_REQUEST['toggleflag'] + 0;
   $toggle_baseurl = "read.php?forum=$forum_id"
      . URI_AMP."thread=$thread"
      . ( $rx_term != '' ? URI_AMP."xterm=$rx_term" : '');
   if( ConfigPages::toggle_forum_flags($my_id, $toggleflag) )
   {
      jump_to( 'forum/'.$toggle_baseurl );
   }
   $show_overview = ( $cfg_pages->get_forum_flags() & FORUMFLAG_POSTVIEW_OVERVIEW );

   $arg_moderator = get_request_arg('moderator');
   $switch_moderator = switch_admin_status( $player_row, ADMIN_FORUM, $arg_moderator );
   $is_moderator = ($switch_moderator == 1);

   // assure independence from forum_id
   if( $forum_id == 0 && $thread > 0 )
      $forum_id = load_forum_id( $thread );

   $forum = Forum::load_forum( $forum_id );

   $f_opts = new ForumOptions( $player_row );
   if( !$f_opts->is_visible_forum( $forum->options ) )
      error('forbidden_forum');

   // for GoDiagrams
   $preview = isset($_POST['preview']);
   $cfg_board = null;
//   if( ALLOW_GO_DIAGRAMS && ( $preview || isset($_POST['post']) ) )
//      $cfg_board = ConfigBoard::load_config_board($my_id);

   $post_errmsg = '';
   if( isset($_POST['post']) )
   {
      list( $pmsg_id, $msg ) = post_message($player_row, $cfg_board, $forum->options, $thread);
      if( $pmsg_id )
      {// added/edited post successfully
         jump_to("forum/read.php?forum=$forum_id".URI_AMP."thread=$thread"
            . URI_AMP."sysmsg=".urlencode($msg)."#$pmsg_id");
      }
      else
      {// error detected, post not saved -> switch to preview
         $preview = true;
         $post_errmsg = $msg;
      }
   }

   // prepare users forum-reads
   $FR = new ForumRead( $my_id, $forum_id, $thread );
   if( $markread != '' )
      $FR->mark_read( $markread );

   $preview_ID = ($edit > 0 ? $edit : @$_REQUEST['parent']+0 );
   $preview_GoDiagrams = NULL;
   if( $preview )
   {
      $preview_Subject = trim(get_request_arg('Subject'));
      $preview_Text = trim(get_request_arg('Text'));
      if( !($edit > 0) )
         $reply = @$_REQUEST['parent']+0;
//      if( ALLOW_GO_DIAGRAMS && is_javascript_enabled() )
//         $preview_GoDiagrams = create_godiagrams($preview_Text, $cfg_board);
   }

   $disp_forum = new DisplayForum( $my_id, $is_moderator, $forum_id, $thread );
   $disp_forum->setConfigBoard( $cfg_board );
   $disp_forum->set_rx_term( $rx_term );
   $disp_forum->cols = 2;
   $disp_forum->links = LINKPAGE_READ;
   $disp_forum->links |= LINK_FORUMS | LINK_THREADS | LINK_SEARCH | LINK_REFRESH;

   if( $switch_moderator >= 0 )
   {
      $disp_forum->links |= LINK_TOGGLE_MODERATOR;
      $modpid = get_request_arg('modpid', 0); // post-id
      if( $arg_moderator == '' && $modpid > 0 )
      {
         $modact = get_request_arg('modact'); // moderate-action
         if( $modact == 'show' )
            show_post( $forum_id, $thread, $modpid );
         elseif( $modact == 'hide' )
            hide_post( $forum_id, $thread, $modpid );
         elseif( $modact == 'approve' )
            approve_post( $forum_id, $thread, $modpid );
         elseif( $modact == 'reject' )
            reject_post( $forum_id, $thread, $modpid );
      }
   }


   if( $show_overview )
   {
      $headtitle1 = sprintf( '%s <span class="HeaderToggle">(<a href="%s">%s</a>)</span>',
         T_('Reading thread overview'),
         $toggle_baseurl .URI_AMP.'toggleflag='.FORUMFLAG_POSTVIEW_OVERVIEW,
         T_('Hide overview#threadoverview') );
      $headline1 = array(
         $headtitle1 => "colspan={$disp_forum->cols}"
      );

      $headtitle2 = T_('Reading thread posts');
   }
   else
   {
      $headline1 = null;

      $headtitle2 = sprintf( '%s <span class="HeaderToggle">(<a href="%s">%s</a>)</span>',
         T_('Reading thread posts'),
         $toggle_baseurl .URI_AMP.'toggleflag='.FORUMFLAG_POSTVIEW_OVERVIEW,
         T_('Show overview#threadoverview') );
   }
   $headline2 = array(
      $headtitle2 => "colspan={$disp_forum->cols}"
   );

   $title = sprintf( '%s - %s', T_('Forum'), $forum->name );
   $style_str = (is_null($cfg_board))
      ? '' : GobanWriterGfxBoard::style_string( $cfg_board->get_stone_size() );
   start_page($title, true, $logged_in, $player_row, $style_str );
   echo "<h3 class=Header>$title</h3>\n";
   if( $DEBUG ) echo "<pre>", print_r($_REQUEST,true), "</pre><br>\n";

   $disp_forum->print_moderation_note('99%');

   if( @$_GET['revision_history'] > 0 )
   {
      revision_history( $disp_forum, @$_GET['revision_history'] );
      end_page();
      hit_thread( $thread ); // use-case U04
      exit;
   }


   // load users forum-reads
   $FR->load_reads_post();

   // select all posts of current thread
   $qsql = new QuerySQL();
   $qsql->add_part( SQLP_WHERE,
      "P.Forum_ID=$forum_id",
      "P.Thread_ID=$thread",
      "P.PosIndex>''" ); // '' == inactivated (edited)
   $qsql->add_part( SQLP_ORDER, 'P.PosIndex' );
   $fthread = new ForumThread( $FR );
   $fthread->load_posts( $qsql );
   $fthread->create_navigation_tree();
   // end of DB-stuff

   $post0 = $fthread->thread_post(); // initial post of the thread
   $is_empty_thread = is_null($post0);
   if( $is_empty_thread )
   {
      $thread_Subject = '';
      $Lastchangedthread = 0 ;
      $cnt_posts = 0;
   }
   else
   {
      $thread_Subject = $post0->subject;
      $Lastchangedthread = $post0->last_changed;
      $cnt_posts = $post0->count_posts;
   }

   // headline2 = no thread tree-overview
   $disp_forum->headline = ( $show_overview ) ? $headline1 : $headline2;

   if( $fthread->count_new > 0 )
      $disp_forum->links |= LINK_MARK_READ;
   $disp_forum->forum_start_table('Read');

   if( $show_overview && !$is_empty_thread )
   {
      // draw tree-overview
      $disp_forum->draw_overview( $fthread );
      $disp_forum->print_headline( $headline2 );
   }

   // draw posts
   $all_my_posts = true;
   foreach( $fthread->posts as $post )
   {
      $post->count_new = ($FR->is_read_post($post)) ? 0 : 1;
      $pid = $post->id;
      $uid = $post->author->id;
      $is_my_post = ($uid == $my_id);
      if( !$is_my_post ) $all_my_posts = false;

      $hidden = !$post->is_approved();
      $show_hidden_post = ( $is_my_post || $disp_forum->is_moderator );
      if( $hidden && !$post->is_thread_post() && !$show_hidden_post )
         continue;

      $disp_forum->change_depth( $post->depth );

      if( $edit == $pid && $is_my_post )
         $drawmode = DRAWPOST_EDIT;
      elseif( $reply == $pid )
         $drawmode = DRAWPOST_REPLY;
      else
         $drawmode = DRAWPOST_NORMAL;
      $drawmode_type = $drawmode;
      if( $hidden && $drawmode != DRAWPOST_EDIT )
      {
         $drawmode |= MASK_DRAWPOST_HIDDEN;
         if( !$show_hidden_post )
            $drawmode |= MASK_DRAWPOST_NO_BODY;
      }

      // draw current post
      $GoDiagrams = NULL;
//      if( ALLOW_GO_DIAGRAMS && is_javascript_enabled() )
//         $GoDiagrams = find_godiagrams($post->text, $cfg_board);
      $post_reference = $disp_forum->draw_post( $drawmode, $post, $is_my_post, $GoDiagrams );

      // preview of new or existing post (within existing thread)
      $pvw_post = $post; // copy for preview/edit
      if( $preview && $preview_ID == $pid )
      {
         $disp_forum->change_depth( $disp_forum->cur_depth + 1 );

         $pvw_post->subject = $preview_Subject;
         $pvw_post->text = $preview_Text;
         $GoDiagrams = $preview_GoDiagrams;
         $disp_forum->draw_post(DRAWPOST_PREVIEW, $pvw_post, false, $GoDiagrams );
      }

      // input-form for reply/edit-post
      if( $drawmode_type == DRAWPOST_EDIT || $drawmode_type == DRAWPOST_REPLY )
      {
         $pvw_post_text = $pvw_post->text;
         if( $drawmode_type == DRAWPOST_REPLY && !($preview && $preview_ID == $pid) )
         {
            // can only quote "showable"-post (non-hidden, my-post, or moderator)
            if( ALLOW_QUOTING && @$_REQUEST['quote'] && !($drawmode & MASK_DRAWPOST_NO_BODY) )
               $pvw_post_text = "<quote>$post_reference\n\n$pvw_post_text</quote>\n";
            else
               $pvw_post_text = '';
            $GoDiagrams = null;
         }
         echo "<tr><td colspan={$disp_forum->cols} align=center>\n";
         $disp_forum->forum_message_box($drawmode_type, $pid, $GoDiagrams, $post_errmsg,
            $pvw_post->subject, $pvw_post_text);
         echo "</td></tr>\n";
      }
   } //posts loop


   // preview of new thread
   if( $preview && $preview_ID == 0 )
   {
      $disp_forum->change_depth( 1 );
      $post = new ForumPost( 0, $forum_id, 0, null, 0, 0, 0, $preview_Subject, $preview_Text );
      $GoDiagrams = $preview_GoDiagrams;
      $disp_forum->draw_post(DRAWPOST_PREVIEW, $post, false, $GoDiagrams );

      echo "<tr><td colspan={$disp_forum->cols} align=center>\n";
      $disp_forum->forum_message_box(DRAWPOST_PREVIEW, $thread, $GoDiagrams, $post_errmsg,
         $post->subject, $post->text );
      echo "</td></tr>\n";
   }

   // footer: reply-form (only for a NEW THREAD or if ONE post existing)
   if( ($cnt_posts <= 1) && !($reply > 0) && !($edit > 0) && !$preview )
   {
      $disp_forum->change_depth( 1 );
      echo "<tr><td colspan={$disp_forum->cols} align=center>\n";
      if( $thread > 0 )
         echo '<hr>';
      $disp_forum->forum_message_box(DRAWPOST_NORMAL, $thread, null, '', $thread_Subject);
      echo "</td></tr>\n";
   }

   // add link to add reply to initial-thread at end of thread-view
   if( $cnt_posts > 1 && !$preview && !($edit > 0) )
   {
      $disp_forum->change_depth( 0 );
      echo "<tr><td colspan={$disp_forum->cols} align=center>\n<hr>"
         , anchor( $post0->build_url_post(null, 'reply='.$post0->id),
                   '[ '.T_('Add reply to inital thread post').' ]' )
         , "<br>&nbsp;</td></tr>\n";
   }

   $disp_forum->change_depth( -1 );
   $disp_forum->forum_end_table();

   // use-cases U03: increase thread-hits show thread-"activity"
   if( !($reply > 0) && !$preview && !($edit > 0) && !$all_my_posts )
      hit_thread( $thread );

   end_page();
}
?>
