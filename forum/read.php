<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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


{
   #$DEBUG = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('login_if_not_logged_in', 'forum.read');
   $my_id = $player_row['ID'];
   $cfg_pages = ConfigPages::load_config_pages($my_id);

   $reply = @$_REQUEST['reply']+0;
   $forum_id = @$_REQUEST['forum']+0;
   $thread = @$_REQUEST['thread']+0;
   $edit = @$_REQUEST['edit']+0;
   $rx_term = get_request_arg('xterm', '');

   // toggle / change forumflags
   $toggleflag = (int)@$_REQUEST['toggleflag'];
   $chg_view = @$_REQUEST['view']; // t=tree-view, fo=flat-old-first, fn=flat-new-first
   $toggle_baseurl = "read.php?forum=$forum_id"
      . URI_AMP."thread=$thread"
      . ( $rx_term != '' ? URI_AMP."xterm=$rx_term" : '');

   if( $toggleflag > 0 && ConfigPages::toggle_forum_flags($my_id, $toggleflag) )
      jump_to( 'forum/'.$toggle_baseurl );
   elseif( $chg_view )
   {
      if( $chg_view == 't' )
         ConfigPages::set_clear_forum_flags($my_id, FORUMFLAGS_POSTVIEW_FLAT, 0 );
      elseif( $chg_view == 'fo' )
         ConfigPages::set_clear_forum_flags($my_id, FORUMFLAGS_POSTVIEW_FLAT, FORUMFLAG_POSTVIEW_FLAT_OLD_FIRST );
      elseif( $chg_view == 'fn' )
         ConfigPages::set_clear_forum_flags($my_id, FORUMFLAGS_POSTVIEW_FLAT, FORUMFLAG_POSTVIEW_FLAT_NEW_FIRST );
      jump_to( 'forum/'.$toggle_baseurl );
   }

   $f_flags = $cfg_pages->get_forum_flags();
   $show_overview = ( $f_flags & FORUMFLAG_POSTVIEW_OVERVIEW );

   $arg_moderator = get_request_arg('moderator');
   $switch_moderator = switch_admin_status( $player_row, ADMIN_FORUM, $arg_moderator );
   $is_moderator = ($switch_moderator == 1);

   // no mark-read on moderator-actions or after moderator-toggle
   $allow_mark_read = !$is_moderator;
   if( $switch_moderator >= 0 && (string)$arg_moderator != '' )
      $allow_mark_read = false;

   // assure independence from forum_id
   if( $forum_id == 0 && $thread > 0 )
      $forum_id = load_forum_id( $thread );

   $forum = Forum::load_cache_forum( $forum_id );
   $f_opts = new ForumOptions( $player_row );
   if( !$f_opts->is_visible_forum( $forum->options ) )
      error('forbidden_forum', "forum_read.check.forum_visible($forum_id,$my_id)");

   // for GoDiagrams
   $preview = isset($_POST['preview']);
   $cfg_board = null; // only load ConfigBoard if post contains go-diagram

   $post_errmsg = '';
   if( isset($_POST['post']) )
   {
      ta_begin(); //caller-TA
      {//HOT-section to post (=add/edit) message
         list( $pmsg_id, $msg ) = post_message($player_row, $cfg_board, $forum->options, $thread);
      }
      ta_end();

      if( $pmsg_id )
      {// added/edited post successfully
         // maybe there are new posts during reply, so omit jump to reply, but jump to top instead
         jump_to("forum/read.php?forum=$forum_id".URI_AMP."thread=$thread"
            . URI_AMP."sysmsg=".urlencode($msg));
      }
      else
      {// error detected, post not saved -> switch to preview
         $preview = true;
         $post_errmsg = $msg;
      }
   }

   $preview_ID = ($edit > 0 ? $edit : @$_REQUEST['parent']+0 );
   $preview_GoDiagrams = NULL;
   if( $preview )
   {
      $preview_Subject = trim(get_request_arg('Subject'));
      $preview_Text = trim(get_request_arg('Text'));

      if( is_null($cfg_board) && MarkupHandlerGoban::contains_goban($preview_Text) ) // lazy-load
         $cfg_board = ConfigBoard::load_config_board($my_id);

      if( !($edit > 0) )
         $reply = @$_REQUEST['parent']+0;
//      if( ALLOW_GO_DIAGRAMS && is_javascript_enabled() )
//         $preview_GoDiagrams = create_godiagrams($preview_Text, $cfg_board);
   }

   $disp_forum = new DisplayForum( $my_id, $is_moderator, $forum_id, $thread );
   $disp_forum->setConfigBoard( $cfg_board );
   $disp_forum->set_threadpost_view( $f_flags );
   $disp_forum->set_forum_options( $forum->options );
   $disp_forum->set_rx_term( $rx_term );
   $disp_forum->cols = 2;
   $disp_forum->links = LINKPAGE_READ;
   $disp_forum->links |= LINK_FORUMS | LINK_THREADS | LINK_SEARCH | LINK_REFRESH;

   $modact = '';
   if( $switch_moderator >= 0 )
   {
      $disp_forum->links |= LINK_TOGGLE_MODERATOR;
      $modpid = get_request_arg('modpid', 0); // post-id
      if( $arg_moderator == '' && $modpid > 0 )
      {
         ta_begin(); //caller-TA
         {//HOT-section to moderate post
            $modact = get_request_arg('modact'); // moderate-action
            if( $modact == 'show' )
               show_post( $forum_id, $thread, $modpid );
            elseif( $modact == 'hide' )
               hide_post( $forum_id, $thread, $modpid );
            elseif( $modact == 'approve' )
               approve_post( $forum_id, $thread, $modpid );
            elseif( $modact == 'reject' )
               reject_post( $forum_id, $thread, $modpid );
            $allow_mark_read = false;
         }
         ta_end();
      }
   }

   // load thread/post-data
   $revh_post_id = (int)@$_GET['revision_history'];
   if( $revh_post_id > 0 )
      $fthread = load_revision_history( $revh_post_id );
   else if( $thread > 0 )
   {
      // load user forum-reads
      $FR = new ForumRead( $my_id, $forum_id, $thread );
      $FR->load_forum_reads();

      // select all posts of current thread
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_WHERE,
         "P.Forum_ID=$forum_id",
         "P.Thread_ID=$thread",
         "P.PosIndex>''" ); // '' == inactivated (edited)

      if( $disp_forum->flat_view < 0 ) // flat old-first
         $qsql->add_part( SQLP_ORDER, 'P.Time ASC' );
      elseif( $disp_forum->flat_view > 0 ) // flat new-first
         $qsql->add_part( SQLP_ORDER, 'P.Time DESC' );
      else // tree-view
         $qsql->add_part( SQLP_ORDER, 'P.PosIndex' );

      $fthread = new ForumThread( $FR );
      $disp_forum->count_new_posts = $fthread->load_posts( $qsql );
      $fthread->create_navigation_tree();

      if( !$reply && !$edit && !$preview && $allow_mark_read )
         $FR->mark_thread_read( $thread, $fthread->last_created ); // use-case U01
   }
   else
      $fthread = new ForumThread();

   if( is_null($cfg_board) && $fthread->contains_goban() )
      $cfg_board = ConfigBoard::load_config_board($my_id);
   // end of DB-stuff


   $title = sprintf( '%s - %s', T_('Forum'), $forum->name );
   $style_str = (is_null($cfg_board))
      ? '' : GobanHandlerGfxBoard::style_string( $cfg_board->get_stone_size() );
   start_page($title, true, $logged_in, $player_row, $style_str );

   $fopts_str = $forum->build_options_text( $f_opts );
   echo "<h3 class=Header>$title$fopts_str</h3>\n";
   if( $DEBUG ) echo "<pre>", print_r($_REQUEST,true), "</pre><br>\n";

   $disp_forum->print_moderation_note('99%');

   if( $revh_post_id > 0 )
   {
      show_revision_history( $fthread, $disp_forum, $revh_post_id );
      end_page();
      if( !$fthread->thread_post->is_author($my_id) )
         hit_thread( $thread ); // use-case U04
      exit;
   }


   $post0 = $fthread->thread_post; // initial post of the thread
   $is_empty_thread = is_null($post0);
   if( $is_empty_thread )
   {
      $allow_thread_post = true;
      $thread_Subject = '';
      $Lastchangedthread = 0 ;
      $cnt_posts = 0;
   }
   else
   {
      $allow_thread_post = $post0->allow_post_reply();
      $thread_Subject = $post0->subject;
      $Lastchangedthread = $post0->last_changed;
      $cnt_posts = $post0->count_posts;
   }

   // headline2 = no thread tree-overview
   list( $headline1, $headtitle2 ) = $disp_forum->build_threadpostview_headlines( $toggle_baseurl, $show_overview );
   $headtitle2 .= DASH_SPACING . '(' . sprintf(T_('%s hits#fthread'), $fthread->get_thread_hits()) . ')';
   $headline2 = array(
      $headtitle2 => "colspan={$disp_forum->cols}"
   );
   $disp_forum->headline = ( $show_overview ) ? $headline1 : $headline2;
   $disp_forum->forum_start_table('Read');

   if( $show_overview && !$is_empty_thread )
   {
      // draw tree-overview
      $disp_forum->draw_overview( $fthread );
      $disp_forum->print_headline( $headline2 );
   }


   // draw posts
   if( $disp_forum->flat_view )
      $disp_forum->change_depth( 1 );

   $all_my_posts = true;
   foreach( $fthread->posts as $post )
   {
      $pid = $post->id;
      $is_my_post = $post->is_author($my_id);
      if( !$is_my_post ) $all_my_posts = false;

      $hidden = !$post->is_approved();
      $show_hidden_post = ( $is_my_post || $disp_forum->is_moderator );
      if( $hidden && !$post->is_thread_post() && !$show_hidden_post )
         continue;

      if( !$disp_forum->flat_view )
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
      $pvw_post = $post->copy_post(); // copy for preview/edit
      if( $allow_thread_post && $preview && $preview_ID == $pid )
      {
         $disp_forum->change_depth( $disp_forum->cur_depth + 1 );

         $pvw_post->subject = $preview_Subject;
         $pvw_post->text = $preview_Text;
         $GoDiagrams = $preview_GoDiagrams;
         $disp_forum->draw_post(DRAWPOST_PREVIEW, $pvw_post, false, $GoDiagrams );
      }

      // input-form for reply/edit-post
      if( $allow_thread_post && ( $drawmode_type == DRAWPOST_EDIT || $drawmode_type == DRAWPOST_REPLY ) )
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


   if( $allow_thread_post && Forum::allow_posting( $player_row, $forum->options ) )
   {
      // preview of new thread
      if( $preview && $preview_ID == 0 )
      {
         $disp_forum->change_depth( 1 );
         $post = new ForumPost( 0, $forum_id, 0, null, 0, 0, 0, $preview_Subject, $preview_Text );
         if( get_request_arg('ReadOnly') )
            $post->flags |= FPOST_FLAG_READ_ONLY;
         $GoDiagrams = $preview_GoDiagrams;
         $disp_forum->draw_post(DRAWPOST_PREVIEW, $post, false, $GoDiagrams );

         echo "<tr><td colspan={$disp_forum->cols} align=center>\n";
         $disp_forum->forum_message_box(DRAWPOST_PREVIEW, $thread, $GoDiagrams, $post_errmsg,
            $post->subject, $post->text, $post->flags );
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
   }

   $disp_forum->change_depth( -1 );
   $disp_forum->forum_end_table();

   // use-cases U03: increase thread-hits show thread-"activity"
   if( $thread > 0 && !($reply > 0) && !$preview && !($edit > 0) && !$all_my_posts )
      hit_thread( $thread );

   end_page();
}//main


// use-case U04: load revision history of post
// return: loaded ForumThread
function load_revision_history( $post_id )
{
   $revhist_thread = new ForumThread();
   $revhist_thread->load_revision_history( $post_id );
   $revhist_thread->thread_post->last_edited = 0; // don't show last-edit
   return $revhist_thread;
}//load_revision_history

// use-case U04: show revision history of post
function show_revision_history( $revhist_thread, $display_forum, $post_id )
{
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
}//show_revision_history

?>
