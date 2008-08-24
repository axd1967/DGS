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
require_once( 'forum/post.php' );


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

   $display_forum->forum_start_table('Revision');
   $display_forum->change_depth( 1 );
   $display_forum->draw_post( 'Reply', $revhist_thread->thread_post, null );

   echo "<tr><td colspan={$display_forum->cols} height=2></td></tr>";
   $display_forum->change_depth( 2 );
   foreach( $revhist_thread->posts as $post )
   {
      $display_forum->draw_post( 'Edit' , $post, true, null );
      echo "<tr><td colspan={$display_forum->cols} height=2></td></tr>";
   }

   $display_forum->change_depth( -1 );
   $display_forum->forum_end_table();
} //revision_history



{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error("not_logged_in");
   $my_id = $player_row['ID'];

   $reply = @$_REQUEST['reply']+0;
   $forum_id = @$_REQUEST['forum']+0;
   $thread = @$_REQUEST['thread']+0;
   $edit = @$_REQUEST['edit']+0;
   $rx_term = get_request_arg('xterm', '');

   $switch_moderator = switch_admin_status( $player_row, ADMIN_FORUM, @$_REQUEST['moderator'] );
   $is_moderator = ($switch_moderator == 1);

   $forum = Forum::load_forum( $forum_id );

   if( isset($_POST['post']) )
   {
      $msg = post_message($player_row, $forum->is_moderated(), $thread);
      if( is_numeric( $msg) && $msg>0 )
         jump_to("forum/read.php?forum=$forum_id".URI_AMP."thread=$thread"
            . "#$msg");
      else
         jump_to("forum/read.php?forum=$forum_id".URI_AMP."thread=$thread"
            . URI_AMP."sysmsg=".urlencode($msg)."#new1");
   }

   $preview = isset($_POST['preview']);
   $preview_ID = ($edit > 0 ? $edit : @$_REQUEST['parent']+0 );
   if( $preview )
   {
      $preview_Subject = trim(get_request_arg('Subject'));
      $preview_Text = trim(get_request_arg('Text'));
      if( !$edit > 0 )
         $reply = @$_REQUEST['parent']+0;
//      $preview_GoDiagrams = create_godiagrams($preview_Text);
   }

   $disp_forum = new DisplayForum( $my_id, $is_moderator, $forum_id, $thread );
   $disp_forum->set_rx_term( $rx_term );
   $disp_forum->cols = 2;
   $disp_forum->links = LINKPAGE_READ;
   $disp_forum->links |= LINK_FORUMS | LINK_THREADS | LINK_SEARCH;
   $headline1 = array(
      T_('Reading thread (overview)') => "colspan={$disp_forum->cols}"
   );
   $headline2 = array(
      T_('Reading thread (posts)') => "colspan={$disp_forum->cols}"
   );

   //toggle moderator and preview does not work together.
   //(else add $_POST in the moderator link build)
   if( $preview || $switch_moderator < 0 )
      $is_moderator = 0;
   else
   {
      $disp_forum->links |= LINK_TOGGLE_MODERATOR;
      if( (int)@$_GET['show'] > 0 )
         approve_message( (int)@$_GET['show'], $thread, $forum_id, true );
      else if( (int)@$_GET['hide'] > 0 )
         approve_message( (int)@$_GET['hide'], $thread, $forum_id, false );
      else if( (int)@$_GET['approve'] > 0 )
         approve_message( (int)@$_GET['approve'], $thread, $forum_id, true, true );
      else if( (int)@$_GET['reject'] > 0 )
         approve_message( (int)@$_GET['reject'], $thread, $forum_id, false, true );
   }

   $title = sprintf( '%s - %s', T_('Forum'), $forum->name );
   start_page($title, true, $logged_in, $player_row);
   echo "<h3 class=Header>$title</h3>\n";

   $disp_forum->print_moderation_note('99%');

   if( @$_GET['revision_history'] > 0 )
   {
      revision_history( $disp_forum, @$_GET['revision_history'] ); //set $Lastread
      end_page();
      hit_thread( $thread );
      exit;
   }

   // set var: Lastread
   $Lastread = load_thread_last_read( $my_id, $thread );

   // select all posts of current thread
   $qsql = new QuerySQL();
   $qsql->add_part( SQLP_WHERE,
      "P.Forum_ID=$forum_id",
      "P.Thread_ID=$thread",
      "P.PosIndex>''" ); // '' == inactivated (edited)
   $qsql->add_part( SQLP_ORDER, 'P.PosIndex' );
   $fthread = new ForumThread();
   $fthread->load_posts( $qsql );
   $fthread->create_navigation_tree();
   // end of DB-stuff

   $post0 = $fthread->thread_post(); // initial post of the thread
   $is_empty_thread = is_null($post0);
   if( $is_empty_thread )
   {
      $thread_Subject = '';
      $Lastchangedthread = 0 ;
      $disp_forum->headline = $headline2; // no thread tree-overview
   }
   else
   {
      $thread_Subject = $post0->subject;
      $Lastchangedthread = $post0->last_changed;
      $disp_forum->headline = $headline1;
   }

   $disp_forum->forum_start_table('Read');

   if( !$is_empty_thread )
   {
      // draw tree-overview
      $disp_forum->draw_overview( $fthread, $Lastread );
      $disp_forum->print_headline( $headline2 );
   }

   // draw posts
   $all_my_posts = true;
   foreach( $fthread->posts as $post )
   {
      $post->last_read = $Lastread;
      $pid = $post->id;
      $uid = $post->author->id;
      $is_my_post = ($uid == $my_id);
      if ( !$is_my_post ) $all_my_posts = false;

      $hidden = !$post->approved;
      if( $hidden && !$disp_forum->is_moderator && !$is_my_post )
         continue;

      $disp_forum->change_depth( $post->depth );

      // TODO: refactor (don't control logic with style-var), also see draw_post-func & forum-search (moderator-stuff)
      if( $edit == $pid )
         $postClass = 'Edit';
      else if( $reply == $pid )
         $postClass = 'Reply';
      else if( $hidden )
         $postClass = 'Hidden';
      else
         $postClass = 'Normal';

//      $GoDiagrams = find_godiagrams($Text);

      // draw current post
      $post_reference =
         $disp_forum->draw_post($postClass, $post, $is_my_post, NULL /*$GoDiagrams*/ );

      // preview of new or existing post (within existing thread)
      $pvw_post = $post; // copy for preview/edit
      if( $preview && $preview_ID == $pid )
      {
         $disp_forum->change_depth( $disp_forum->cur_depth + 1 );

         $pvw_post->subject = $preview_Subject;
         $pvw_post->text = $preview_Text;
//         $GoDiagrams = $preview_GoDiagrams;
         $disp_forum->draw_post('Preview', $pvw_post, false, NULL /*$GoDiagrams*/ );
      }

      // input-form for reply/edit-post
      if( $postClass != 'Normal' && $postClass != 'Hidden' && !$disp_forum->is_moderator )
      {
         $pvw_post_text = $pvw_post->text;
         if( $postClass == 'Reply' && !($preview && $preview_ID == $pid) )
         {
            if( ALLOW_QUOTING && @$_REQUEST['quote'] )
               $pvw_post_text = "<quote>$post_reference\n\n$pvw_post_text</quote>\n";
            else
               $pvw_post_text = '';
//            $GoDiagrams = null;
         }
         echo "<tr><td colspan={$disp_forum->cols} align=center>\n";
         $disp_forum->forum_message_box($postClass, $pid, NULL /*$GoDiagrams*/,
            $pvw_post->subject, $pvw_post_text);
         echo "</td></tr>\n";
      }
   } //posts loop


   // preview of new thread
   if( $preview && $preview_ID == 0 && !$disp_forum->is_moderator )
   {
      $disp_forum->change_depth( $disp_forum->cur_depth + 1 );
      $post = new ForumPost( 0, $forum_id, 0, null, 0, 0, 0, $preview_Subject, $preview_Text );
//      $GoDiagrams = $preview_GoDiagrams;
      $disp_forum->draw_post('Preview', $post, false, NULL /*$GoDiagrams*/ );

      echo "<tr><td colspan={$disp_forum->cols} align=center>\n";
      $disp_forum->forum_message_box('Preview', $thread, NULL /*$GoDiagrams*/,
         $post->subject, $post->text );
      echo "</td></tr>\n";
   }

   // footer: reply-form (only for a new thread)
   if( $is_empty_thread && !$preview && !$disp_forum->is_moderator )
   {
      $disp_forum->change_depth( 1 );
      echo "<tr><td colspan={$disp_forum->cols} align=center>\n";
      if( $thread > 0 )
         echo '<hr>';
      $disp_forum->forum_message_box('Normal', $thread, null, $thread_Subject);
      echo "</td></tr>\n";
   }

   $disp_forum->change_depth( -1 );
   $disp_forum->forum_end_table();


   // Update Forumreads to remove the 'new' flag
   if( !$Lastread || $Lastread < $Lastchangedthread )
   {
      mysql_query( "REPLACE INTO Forumreads SET " .
                   "User_ID=$my_id, " .
                   "Thread_ID=$thread, " .
                   "Time=FROM_UNIXTIME($NOW)" )
         or error('mysql_query_failed','forum_read.replace_forumreads');
   }

   // increase thread-hits on "view" (& post-save) to show thread-"activity"
   if ( !($reply > 0) && !$preview && !($edit > 0) && !$all_my_posts )
      hit_thread( $thread );

   end_page();
}
?>
