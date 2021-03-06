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

$TranslateGroups[] = "Forum"; //local use

require_once 'include/classlib_userconfig.php';
require_once 'forum/forum_functions.php';


// to increase thread-hits to show thread-"activity"
function hit_thread( $thread )
{
   if ( is_numeric($thread) && $thread > 0 )
   {
      db_query( "forum_post.hit_thread($thread)",
         "UPDATE Posts SET Hits=Hits+1 WHERE ID='$thread' LIMIT 1" );
   }
}

/*!
 * \brief Saves post-message for use-cases U06/U07/U08
 * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
 *
 * \return array( id, msg ); if id=inserted/updated-Post.ID: msg contains success-message,
 *         if id=0: msg contains error-message (post not saved!)
 * \see specs/forums.txt
 */
function post_message($player_row, &$cfg_board, $forum_opts, &$thread )
{
   global $NOW;

   if ( ($player_row['AdminOptions'] & ADMOPT_FORUM_NO_POST) )
      return array( 0, T_('Sorry, you are not allowed to post on the forum') );

   $uid = @$player_row['ID'];
   $forum = (int)@$_POST['forum'];
   $parent = (int)@$_POST['parent'];
   $edit = (int)@$_POST['edit'];

   $f_opts = new ForumOptions( $player_row );
   if ( !$f_opts->is_visible_forum( $forum_opts ) )
      error('forbidden_forum', "post_message.check.forum($uid,$forum)");

   if ( !Forum::allow_posting( $player_row, $forum_opts ) )
      error('read_only_forum', "post_message.check.read_only($uid,$forum)");

   $Text = trim(get_request_arg('Text'));
   $Subject = trim(get_request_arg('Subject'));
   if ( (string)$Subject == '' || (string)$Text == '' )
      return array( 0, T_('Message not saved, because of missing subject and/or text-body.') );

   $errmsg_post_violations = check_forum_post_violations( $Subject, $Text );
   if ( $errmsg_post_violations )
      return array( 0, $errmsg_post_violations );

   $ReadOnly = (bool)get_request_arg('ReadOnly');
   if ( !$parent || !ForumPost::allow_post_read_only() ) // new post setting read-only flag only allowed for new-thread and executives
      $ReadOnly = false;

   if ( is_null($cfg_board) && MarkupHandlerGoban::contains_goban($Text) ) // lazy-load
      $cfg_board = ConfigBoard::load_config_board_or_default($uid);

   $Subject = mysql_addslashes( $Subject);
   $Text = mysql_addslashes( $Text);

   $moderated = (($forum_opts & FORUMOPT_MODERATED) || ($player_row['AdminOptions'] & ADMOPT_FORUM_MOD_POST));

   // -------   Edit old post  ---------- (use-case U07)
   if ( $edit > 0 )
   {
      $row = mysql_single_fetch( "post_message.edit.find_post($uid,$edit)",
               "SELECT Forum_ID,Thread_ID,Subject,Text,GREATEST(Time,Lastedited) AS Time,Approved ".
               "FROM Posts WHERE ID=$edit AND User_ID=$uid LIMIT 1" )
         or error('unknown_parent_post', "post_message.edit.find_post2($uid,$edit)");

      $oldSubject = mysql_addslashes( trim($row['Subject']));
      $oldText = mysql_addslashes( trim($row['Text']));
      $oldModerated = ( $row['Approved'] != 'Y' );
      $thread = @$row['Thread_ID'];

      if ( $oldSubject != $Subject || $oldText != $Text )
      {
         //Update old record with new text
         db_query( "post_message.edit.update($edit)",
               "UPDATE Posts SET " .
                     "Lastedited=FROM_UNIXTIME($NOW), " .
                     "Subject='$Subject', " .
                     "Text='$Text' " .
                     ( $moderated ? ", Approved='P' " : '' ) .
                     "WHERE ID=$edit LIMIT 1" );

         //Insert new record with old text
         db_query( 'post_message.edit.insert',
               "INSERT INTO Posts SET " .
                     "Time='" . $row['Time'] ."', " .
                     "Parent_ID=$edit, " .
                     "Forum_ID=" . $row['Forum_ID'] . ", " .
                     "User_ID=$uid, " .
                     "PosIndex='', " . // '' == inactivated (edited)
                     "Subject='$oldSubject', " .
                     "Text='$oldText'" );

         add_forum_log( @$row['Thread_ID'], $edit,
            ($moderated) ? FORUMLOGACT_EDIT_PEND_POST : FORUMLOGACT_EDIT_POST );
      }

      if ( !$oldModerated && $moderated ) // shown -> hidden
         hide_post_update_trigger( $row['Forum_ID'], $thread, $edit );

      hit_thread( $thread );

      return array( $edit, T_('Message updated!') );
   }//edit-post
   else
   {
   // -------   Add post  ----------

      // -------   Reply / Quote  ---------- (use-case U08)
      $post_flags = 0;
      if ( $parent > 0 ) // existing thread
      {
         $is_newthread = false;
         $row = mysql_single_fetch( "post_message.reply.find($forum,$parent)",
                        "SELECT PosIndex,Depth,Thread_ID,Flags FROM Posts " .
                        "WHERE ID=$parent AND Forum_ID=$forum LIMIT 1" )
            or error('unknown_parent_post', "post_message.reply.find2($forum,$parent)" );

         extract( $row); // $PosIndex, $Depth, $Thread_ID, $Flags

         if ( ($Flags & FPOST_FLAG_READ_ONLY) && !ForumPost::allow_post_read_only() )
            error('read_only_thread', "post_message.reply.check.thread_readonly($forum,$parent)" );

         $row = mysql_single_fetch( "post_message.reply.max($parent)",
                        "SELECT MAX(AnswerNr) AS answer_nr " .
                        "FROM Posts WHERE Parent_ID=$parent LIMIT 1" )
            or error('unknown_parent_post', "post_message.reply.max2($parent)");
         $answer_nr = $row['answer_nr'];
         if ( $answer_nr <= 0 ) $answer_nr = 0;

         $lastchanged_string = ''; // Lastchanged only set for thread
      }
      // -------   New thread  ---------- (use-case U06)
      else
      {
         // New thread
         $is_newthread = true;
         $answer_nr = 0;
         $PosIndex = ''; //just right now... (adjusted below)
         $Depth = 0;
         $Thread_ID = -1;
         $lastchanged_string = "LastChanged=FROM_UNIXTIME($NOW), ";
         if ( $ReadOnly )
            $post_flags |= FPOST_FLAG_READ_ONLY;
      }


      // -------   Update database   ------- (use-case U06, U08)

      if ( $answer_nr >= 64*64 )
         error('internal_error', "post_message.answer_nr.too_large($Thread_ID,$answer_nr)");

      if ( ++$Depth >= FORUM_MAX_DEPTH ) //see also the length of Posts.PosIndex
         error('internal_error', "post_message.depth.too_large($Thread_ID,$Depth)");

      $order_str = ORDER_STR;
      $PosIndex .= $order_str[$answer_nr/64] . $order_str[$answer_nr%64];

      $query = "INSERT INTO Posts SET " .
         "Forum_ID=$forum, " .
         "Thread_ID=$Thread_ID, " .
         "Flags=$post_flags, " .
         "Time=FROM_UNIXTIME($NOW), " .
         $lastchanged_string .
         "Subject=\"$Subject\", " .
         "Text=\"$Text\", " .
         "User_ID=" . $player_row["ID"] . ", " .
         "Parent_ID=$parent, " .
         "AnswerNr=" . ($answer_nr+1) . ", " .
         "Depth=$Depth, " .
         "Approved=" . ($moderated ? "'P'" : "'Y'")  . ", " .
         "crc32=" . crc32($Text) . ", " . // note: Text is mysql-escaped now; PHP-crc32 is signed-int
         "PosIndex='$PosIndex'";

      db_query( "post_message.insert_new_post($Thread_ID)", $query );
      if ( mysql_affected_rows() != 1)
         error('mysql_insert_post', "post_message.insert_new_post($Thread_ID)");
      $New_ID = mysql_insert_id();

      if ( $is_newthread ) // U06 (New thread), also $Thread_ID = -1
      {
         db_query( "post_message.new_thread($New_ID)",
            'UPDATE Posts SET Thread_ID=ID, LastPost=ID, '
            . 'PostsInThread=' . ($moderated ? '0' : '1')
            . " WHERE ID=$New_ID LIMIT 1" );
         if ( mysql_affected_rows() != 1)
            error('mysql_insert_post', "post_message.new_thread.update_thread($New_ID)");

         $thread = $Thread_ID = $New_ID;
      }
      else // U08 (reply/quote)
      {
         hit_thread( $Thread_ID );
      }

      $flog_actsuffix = ( $Thread_ID == $New_ID ) ? FORUMLOGACT_SUFFIX_NEW_THREAD : FORUMLOGACT_SUFFIX_REPLY;
      if ( $moderated ) // hidden post
      {
         add_forum_log( $Thread_ID, $New_ID, FORUMLOGACT_NEW_PEND_POST . $flog_actsuffix );
         return array( $New_ID, T_('This post is subject to moderation. '
            . 'It will be shown once the moderators have approved it.') );
      }
      else // shown post
      {
         // thread-trigger
         if ( !$is_newthread ) // reply/quote
         {
            db_query( "post_message.thread_trigger($Thread_ID)",
               'UPDATE Posts SET '
               . 'PostsInThread=PostsInThread+1, '
               . "LastPost=GREATEST(LastPost,$New_ID), "
               . "Lastchanged=IF(LastPost>$New_ID,Lastchanged,FROM_UNIXTIME($NOW)) "
               . "WHERE ID='$Thread_ID' LIMIT 1" );
         }

         // forum-trigger (reply/quote, new-thread)
         db_query( "post_message.forum_trigger($forum)",
            'UPDATE Forums SET '
            . 'PostsInForum=PostsInForum+1, '
            . ( $is_newthread ? 'ThreadsInForum=ThreadsInForum+1, ' : '' )
            . "LastPost=GREATEST(LastPost,$New_ID), "
            . "Updated=GREATEST(Updated,FROM_UNIXTIME($NOW)) "
            . "WHERE ID='$forum' LIMIT 1" );

         Forum::delete_cache_forum( "post_message.forum_trigger($forum)", $forum );

         // global forum-trigger
         ForumRead::trigger_recalc_global_post_update( $NOW );

         add_forum_log( $Thread_ID, $New_ID, FORUMLOGACT_NEW_POST . $flog_actsuffix );

         return array( $New_ID, T_('Message sent!') );
      }
   }//add-post
} //post_message


// use-case A04 (approve post on pending-approval)
// IMPORTANT NOTE: caller needs to open TA with HOT-section!!
function approve_post( $fid, $tid, $pid )
{
   global $NOW;

   // approve post
   db_query( "approve_post.update_post($pid)",
      "UPDATE Posts SET Approved='Y' WHERE ID=$pid AND Approved<>'Y' LIMIT 1" );
   if ( mysql_affected_rows() < 1 )
      return; // already approved

   add_forum_log( $tid, $pid, FORUMLOGACT_APPROVE_POST );

   // update trigger
   $row =
      mysql_single_fetch( "approve_post.find_post($pid)",
         "SELECT UNIX_TIMESTAMP(Time) AS X_Time FROM Posts "
         . "WHERE ID=$pid AND Thread_ID=$tid LIMIT 1" )
      or error('unknown_post', "approve_post.find_post2($pid)");
   $post_created = $row['X_Time'];

   $row = // read thread-info
      mysql_single_fetch( "approve_post.find_thread($tid)",
         "SELECT UNIX_TIMESTAMP(Lastchanged) AS X_Lastchanged, PostsInThread FROM Posts "
         . "WHERE ID=$tid AND Thread_ID=$tid LIMIT 1" )
      or error('unknown_post', "approve_post.find_thread2($tid)");
   $thread_lastchanged = $row['X_Lastchanged'];
   $thread_cntposts = $row['PostsInThread'];

   // thread-trigger
   db_query( "approve_post.trigger_thread($tid,$pid)",
      'UPDATE Posts SET '
      . ( ($post_created > $thread_lastchanged)
            ? "LastPost=$pid, Lastchanged=FROM_UNIXTIME($post_created), "
            : '' )
      . 'PostsInThread=PostsInThread+1 '
      . "WHERE ID=$tid LIMIT 1" );

   // forum-trigger
   db_query( "approve_post.trigger_forum($fid,$pid)",
      'UPDATE Forums SET '
      . 'PostsInForum=PostsInForum+1, '
      . ( ($thread_cntposts == 0) ? 'ThreadsInForum=ThreadsInForum+1, ' : '' )
      . "Updated=GREATEST(Updated,FROM_UNIXTIME($NOW)), "
      . "LastPost=GREATEST(LastPost,$pid) "
      . "WHERE ID=$fid LIMIT 1" );

   Forum::delete_cache_forum( "approve_post.trigger_forum($fid,$pid)", $fid );

   // global-trigger
   ForumRead::trigger_recalc_global_post_update( $NOW );
}//approve_post

// use-case A05 (reject post on pending-approval)
// IMPORTANT NOTE: caller needs to open TA with HOT-section!!
function reject_post( $fid, $tid, $pid )
{
   db_query( "reject_post.update_post($pid)",
      "UPDATE Posts SET Approved='N' WHERE ID=$pid AND Approved<>'N' LIMIT 1" );
   if ( mysql_affected_rows() < 1 )
      return; // already rejected

   add_forum_log( $tid, $pid, FORUMLOGACT_REJECT_POST );
}

// use-case A06 (hide shown post)
// IMPORTANT NOTE: caller needs to open TA with HOT-section!!
function hide_post( $fid, $tid, $pid )
{
   // hide post
   db_query( "hide_post.update_post($pid)",
      "UPDATE Posts SET Approved='N' WHERE ID=$pid AND Approved<>'N' LIMIT 1" );
   if ( mysql_affected_rows() < 1 )
      return; // already hidden

   add_forum_log( $tid, $pid, FORUMLOGACT_HIDE_POST );

   hide_post_update_trigger( $fid, $tid, $pid );
}

// used for use-cases (U07,A06)
// - call only if post is shown and should be hidden
// IMPORTANT NOTE: caller needs to open TA with HOT-section!!
function hide_post_update_trigger( $fid, $tid, $pid )
{
   global $NOW;

   $row = // read thread-info
      mysql_single_fetch( "hide_post_update_trigger.find_thread($tid)",
         "SELECT LastPost, PostsInThread FROM Posts "
         . "WHERE ID=$tid AND Thread_ID=$tid LIMIT 1" )
      or error('unknown_post', "hide_post_update_trigger.find_thread2($tid)");
   $thread_lastpost = $row['LastPost'];
   $thread_cntposts = $row['PostsInThread'];

   $row =
      mysql_single_fetch( "hide_post_update_trigger.find_forum($tid)",
         "SELECT LastPost, ThreadsInForum FROM Forums WHERE ID=$fid LIMIT 1" )
      or error('unknown_forum', "hide_post_update_trigger.find_forum2($fid)");
   $forum_lastpost = $row['LastPost'];
   $forum_cntthreads = $row['ThreadsInForum'];

   // thread-trigger
   db_query( "hide_post_update_trigger.trigger_thread($tid,$pid)",
      'UPDATE Posts SET '
      . ( ($pid == $thread_lastpost && $thread_cntposts == 0) ? 'LastPost=0, ' : '' )
      . 'PostsInThread=PostsInThread-1 '
      . "WHERE ID=$tid LIMIT 1" );

   // forum-trigger
   db_query( "hide_post_update_trigger.trigger_forum($fid,$pid)",
      'UPDATE Forums SET '
      . ( ($thread_cntposts == 1) ? 'ThreadsInForum=ThreadsInForum-1, ' : '' )
      . 'PostsInForum=PostsInForum-1, '
      . "Updated=GREATEST(Updated,FROM_UNIXTIME($NOW)) "
      . "WHERE ID=$fid LIMIT 1" );

   Forum::delete_cache_forum( "hide_post_update_trigger.trigger_forum($fid,$pid)", $fid );

   // global-trigger
   ForumRead::trigger_recalc_global_post_update( $NOW );

   if ( $pid == $thread_lastpost && $thread_cntposts > 0 )
      recalc_thread_lastpost($tid);

   if ( $pid == $forum_lastpost && $forum_cntthreads > 0 )
      recalc_forum_lastpost($fid);
} //hide_post_update_trigger

// use-case A07 (show hidden post)
// IMPORTANT NOTE: caller needs to open TA with HOT-section!!
function show_post( $fid, $tid, $pid )
{
   global $NOW;

   // show post
   db_query( "show_post.update_post($pid)",
      "UPDATE Posts SET Approved='Y' WHERE ID=$pid AND Approved<>'Y' LIMIT 1" );
   if ( mysql_affected_rows() < 1 )
      return; // already shown

   add_forum_log( $tid, $pid, FORUMLOGACT_SHOW_POST );


   // update trigger
   $row =
      mysql_single_fetch( "show_post.find_post($pid)",
         "SELECT UNIX_TIMESTAMP(Time) AS X_Time FROM Posts "
         . "WHERE ID=$pid AND Thread_ID=$tid LIMIT 1" )
      or error('unknown_post', "show_post.find_post2($pid)");
   $post_created = $row['X_Time'];

   $row = // read thread-info
      mysql_single_fetch( "show_post.find_thread($tid)",
         'SELECT PostsInThread, LastPost, UNIX_TIMESTAMP(Lastchanged) AS X_Lastchanged '
         . "FROM Posts WHERE ID=$tid AND Thread_ID=$tid LIMIT 1" )
      or error('unknown_post', "show_post.find_thread2($tid)");
   $thread_lastchanged = $row['X_Lastchanged'];
   $thread_lastpost = $row['LastPost'];
   $thread_cntposts = $row['PostsInThread'];

   // thread-trigger
   db_query( "show_post.trigger_thread($tid,$pid)",
      'UPDATE Posts SET '
      . ( ($post_created > $thread_lastchanged || $thread_lastpost == 0)
         ? "LastPost=$pid, Lastchanged=FROM_UNIXTIME($post_created), "
         : '' )
      . 'PostsInThread=PostsInThread+1 '
      . "WHERE ID=$tid LIMIT 1" );

   // forum-trigger
   db_query( "show_post.trigger_forum($fid,$pid)",
      'UPDATE Forums SET '
      . ( ($thread_cntposts == 0) ? 'ThreadsInForum=ThreadsInForum+1, ' : '' )
      . "Updated=GREATEST(Updated,FROM_UNIXTIME($NOW)), "
      . 'PostsInForum=PostsInForum+1 '
      . "WHERE ID=$fid LIMIT 1" );

   Forum::delete_cache_forum( "show_post.trigger_forum($fid,$pid)", $fid );

   // global-trigger
   ForumRead::trigger_recalc_global_post_update( $NOW );

   recalc_forum_lastpost($fid);
}//show_post


// IMPORTANT NOTE: caller needs to open TA with HOT-section!!
function recalc_thread_lastpost( $tid )
{
   global $NOW;
   $row =
      mysql_single_fetch( "recalc_thread_lastpost.find_lastpost($tid)",
         "SELECT ID, UNIX_TIMESTAMP(Time) AS X_Time FROM Posts "
         . "WHERE Thread_ID='$tid' AND Approved='Y' "
         . "AND PosIndex>'' " // ''=inactivated (edited)
         . "ORDER BY Time DESC LIMIT 1" );
   $lastpost = ($row) ? $row['ID'] : 0;
   $lastchanged = ($row) ? $row['X_Time'] : 0; // 0=no-upd for (un-approved) one-post-threads e.g. for moderated forum

   db_query( "recalc_thread_lastpost.update_lastpost($tid,$lastpost,$lastchanged)",
      'UPDATE Posts SET '
      . "LastPost=$lastpost "
      . ( $lastchanged > 0 ? ", Lastchanged=FROM_UNIXTIME($lastchanged) " : '' )
      . "WHERE ID='$tid' LIMIT 1" );
}

// IMPORTANT NOTE: caller needs to open TA with HOT-section!!
function recalc_forum_lastpost( $fid )
{
   $row =
      mysql_single_fetch( "recalc_forum_lastpost.find_lastpost($fid)",
         "SELECT ID FROM Posts "
         . "WHERE Forum_ID='$fid' AND Thread_ID>0 AND Approved='Y' "
         . "AND PosIndex>'' " // ''=inactivated (edited)
         . "ORDER BY Time DESC LIMIT 1" );
   $lastpost = ($row) ? $row['ID'] : 0;

   db_query( "recalc_forum_lastpost.update_lastpost($fid)",
      "UPDATE Forums SET LastPost=$lastpost WHERE ID='$fid' LIMIT 1" );

   Forum::delete_cache_forum( "recalc_forum_lastpost.update_lastpost($fid)", $fid );
}

/*! \brief Returns error-message if subject and/or text contains offensive terms; 0 = no violations. */
function check_forum_post_violations( $subject, $text )
{
   if ( (string)FORUM_POST_FORBIDDEN_TERMS == '' )
      return 0;

   $regex = "/\\b(" . FORUM_POST_FORBIDDEN_TERMS . ")\\b/";
   $text_violations = array();
   if ( preg_match($regex, $subject) )
      $text_violations[] = T_('subject#forum');
   if ( preg_match($regex, $text) )
      $text_violations[] = T_('message text#forum');

   if ( count($text_violations) )
      return sprintf( T_('Message not saved due to use of offensive words in [%s].'),
         join(' & ', $text_violations) );

   return 0;
}//check_forum_post_violations

?>
