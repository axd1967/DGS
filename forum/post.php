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

$TranslateGroups[] = "Forum"; //local use

require_once( 'forum/forum_functions.php' );


// to increase thread-hits to show thread-"activity"
function hit_thread( $thread )
{
   if ( is_numeric($thread) && $thread > 0 )
   {
      db_query( 'forum.hit_thread',
         "UPDATE Posts SET Hits=Hits+1 WHERE ID='$thread' LIMIT 1" );
   }
}

/*!
 * \brief Saves post-message for use-cases U06/U07/U08/U09
 * \see specs/forums.txt
 */
function post_message($player_row, $moderated_forum, &$thread)
{
   global $NOW, $order_str;

   if( $player_row['MayPostOnForum'] == 'N' )
      return T_('Sorry, you are not allowed to post on the forum');

   $forum = @$_POST['forum']+0;
   $parent = @$_POST['parent']+0;
   $edit = @$_POST['edit']+0;

   $Text = trim(get_request_arg('Text'));
   if( $Text == '' )
      return '';
   // TODO return on empty subject (subject & text are mandatory)
   $Subject = trim(get_request_arg('Subject'));
   if( $Subject == '' )
      $Subject = UNKNOWN_VALUE;
//   $GoDiagrams = create_godiagrams($Text);
   $Subject = mysql_addslashes( $Subject);
   $Text = mysql_addslashes( $Text);

   $moderated = ($moderated_forum || $player_row['MayPostOnForum'] == 'M');

   // -------   Edit old post  ---------- (use-case U07)

   if( $edit > 0 )
   {
      $row = mysql_single_fetch( 'forum_post.post_message.edit.find',
               "SELECT Forum_ID,Thread_ID,Subject,Text,GREATEST(Time,Lastedited) AS Time ".
               "FROM Posts WHERE ID=$edit AND User_ID=" . $player_row['ID'] )
         or error('unknown_parent_post', 'forum_post.post_message.edit.find');

      $oldSubject = mysql_addslashes( trim($row['Subject']));
      $oldText = mysql_addslashes( trim($row['Text']));
      $thread = @$row['Thread_ID'];

      if( $oldSubject != $Subject || $oldText != $Text )
      {
         //Update old record with new text
         mysql_query("UPDATE Posts SET " .
                     "Lastedited=FROM_UNIXTIME($NOW), " .
                     "Subject='$Subject', " .
                     "Text='$Text' " .
                     "WHERE ID=$edit LIMIT 1")
            or error('mysql_query_failed','forum_post.post_message.edit.update');

         //Insert new record with old text
         mysql_query("INSERT INTO Posts SET " .
                     "Time='" . $row['Time'] ."', " .
                     "Parent_ID=$edit, " .
                     "Forum_ID=" . $row['Forum_ID'] . ", " .
                     "User_ID=" . $player_row['ID'] . ", " .
                     "PosIndex='', " . // '' == inactivated (edited)
                     "Subject='$oldSubject', " .
                     "Text='$oldText'" )
            or error('mysql_query_failed','forum_post.post_message.edit.insert');
      }

      hit_thread( $thread );
      return $edit;
   }
   else
   {
   // -------   Else add post  ----------


   // -------   Reply / Quote  ---------- (use-case U08/U09)

      if( $parent > 0 )
      {
         $is_newthread = false;
         $row = mysql_single_fetch( 'forum_post.reply.find',
                        "SELECT PosIndex,Depth,Thread_ID FROM Posts " .
                        "WHERE ID=$parent AND Forum_ID=$forum" )
            or error('unknown_parent_post', 'forum_post.reply.find');

         extract( $row); // $PosIndex, $Depth, $Thread_ID

         $row = mysql_single_fetch( 'forum_post.reply.max',
                        "SELECT MAX(AnswerNr) AS answer_nr " .
                        "FROM Posts WHERE Parent_ID=$parent" )
            or error('unknown_parent_post', 'forum_post.reply.max');
         $answer_nr = $row['answer_nr'];

         $lastchanged_string = ''; //TODO: why not set as for new-thread in DB ?

         if( $answer_nr <= 0 ) $answer_nr=0;
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
      }



      // -------   Update database   ------- (use-case U06, U08/U09)

      if( $answer_nr >= 64*64 )
         error('internal_error', "AnswerNr too large: $answer_nr" );

      if( ++$Depth >= FORUM_MAX_DEPTH ) //see also the length of Posts.PosIndex
         error('internal_error', "Depth too large: $Depth" );

      $PosIndex .= $order_str[$answer_nr/64] . $order_str[$answer_nr%64];

      $query = "INSERT INTO Posts SET " .
         "Forum_ID=$forum, " .
         "Thread_ID=$Thread_ID, " .
         "Time=FROM_UNIXTIME($NOW), " .
         $lastchanged_string .
         "Subject=\"$Subject\", " .
         "Text=\"$Text\", " .
         "User_ID=" . $player_row["ID"] . ", " .
         "Parent_ID=$parent, " .
         "AnswerNr=" . ($answer_nr+1) . ", " .
         "Depth=$Depth, " .
         "Approved=" . ($moderated ? "'P'" : "'Y'")  . ", " .
         "crc32=" . crc32($Text) . ", " .
         "PosIndex='$PosIndex'";

      mysql_query( $query )
         or error('mysql_query_failed','forum_post.insert_new_post');

      if( mysql_affected_rows() != 1)
         error("mysql_insert_post", 'forum_post.insert_new_post');

      $New_ID = mysql_insert_id();

      if( $is_newthread ) // U06 (New thread), also $Thread_ID = -1
      {
         db_query( 'forum_post.new_thread',
            'UPDATE Posts SET Thread_ID=ID, LastPost=ID, '
            . 'PostsInThread=' . ($moderated ? '0' : '1')
            . "WHERE ID=$New_ID LIMIT 1" );

         if( mysql_affected_rows() != 1)
            error("mysql_insert_post", 'forum_post.new_thread');

         $thread = $Thread_ID = $New_ID;
      }
      else // U08/U09 (reply/quote)
      {
         hit_thread( $Thread_ID );
      }

//   save_diagrams($GoDiagrams);

      if( $moderated )
      {
         return T_('This post is subject to moderation. It will be shown once the moderators have approved it.');
      }
      else
      {
         // thread-trigger
         if( !$is_newthread ) // reply/quote
         {
            db_query( "forum_post.thread_trigger($Thread_ID)",
               'UPDATE Posts SET '
               . 'PostsInThread=PostsInThread+1, '
               . "LastPost=GREATEST(LastPost,$New_ID), "
               . "Lastchanged=IF(LastPost>$New_ID,Lastchanged,FROM_UNIXTIME($NOW)), "
               . "Updated=GREATEST(Updated,FROM_UNIXTIME($NOW)) "
               . "WHERE ID='$Thread_ID' LIMIT 1" );
         }

         // forum-trigger (reply/quote, new-thread)
         db_query( "forum_post.forum_trigger($forum)",
            'UPDATE Forums SET '
            . 'PostsInForum=PostsInForum+1, '
            . ( ($is_newthread)
               ? 'ThreadsInForum=ThreadsInForum+1, '
               : "LastPost=GREATEST(LastPost,$New_ID), " )
            . "Updated=GREATEST(Updated,FROM_UNIXTIME($NOW)) "
            . "WHERE ID='$forum' LIMIT 1" );

         return T_('Message sent!');
      }
   }
} //post_message


// use-case A04 (approve post on pending-approval)
function approve_post( $fid, $tid, $pid )
{
   $row =
      mysql_single_fetch( "approve_post.find_post($pid)",
         "SELECT UNIX_TIMESTAMP(Time) AS X_Time FROM Posts "
         . "WHERE ID=$pid AND Thread_ID=$tid LIMIT 1" )
      or error('unknown_post', "approve_post.find_post($pid)");
   $post_created = $row['X_Time'];

   $row =
      mysql_single_fetch( "approve_post.find_thread($tid)",
         "SELECT UNIX_TIMESTAMP(Lastchanged) AS X_Lastchanged FROM Posts "
         . "WHERE ID=$tid AND Thread_ID=$tid LIMIT 1" )
      or error('unknown_post', "approve_post.find_thread($tid)");
   $thread_lastchanged = $row['X_Lastchanged'];

   // approve post
   db_query( "approve_post.update_post($pid)",
      "UPDATE Posts SET Approved='Y' WHERE ID=$pid LIMIT 1" );

   // thread-trigger
   db_query( "approve_post.trigger_thread($tid,$pid)",
      'UPDATE Posts SET '
      . ( ($post_created > $thread_lastchanged)
         ? "LastPost=$pid, "
           . "Lastchanged=FROM_UNIXTIME($post_created), "
           . "Updated=GREATEST(Updated,FROM_UNIXTIME($post_created)), "
         : '' )
      . 'PostsInThread=PostsInThread+1 '
      . "WHERE ID=$tid LIMIT 1" );

   // forum-trigger
   db_query( "approve_post.trigger_forum($fid,$pid)",
      'UPDATE Forums SET '
      . 'PostsInForum=PostsInForum+1, '
      . ( ($pid == $tid) ? 'ThreadsInForum=ThreadsInForum+1, ' : '' )
      . ( ($post_created > $thread_lastchanged)
         ? "Updated=GREATEST(Updated,FROM_UNIXTIME($post_created)), "
         : '' )
      . "LastPost=GREATEST(LastPost,$pid) "
      . "WHERE ID=$fid LIMIT 1" );
}

// use-case A05 (reject post on pending-approval)
function reject_post( $fid, $tid, $pid )
{
   db_query( "reject_post.update_post($pid)",
      "UPDATE Posts SET Approved='N' WHERE ID=$pid LIMIT 1" );
}

// use-case A06 (hide shown post)
function hide_post( $fid, $tid, $pid )
{
   global $NOW;
   $row =
      mysql_single_fetch( "hide_post.find_thread($tid)",
         "SELECT LastPost, PostsInThread FROM Posts "
         . "WHERE ID=$tid AND Thread_ID=$tid LIMIT 1" )
      or error('unknown_post', "hide_post.find_thread($tid)");
   $thread_lastpost = $row['LastPost'];
   $thread_cntposts = $row['PostsInThread'];

   $row =
      mysql_single_fetch( "hide_post.find_forum($tid)",
         "SELECT LastPost FROM Forums WHERE ID=$fid LIMIT 1" )
      or error('unknown_forum', "hide_post.find_forum($fid)");
   $forum_lastpost = $row['LastPost'];

   // hide post
   db_query( "hide_post.update_post($pid)",
      "UPDATE Posts SET Approved='N' WHERE ID=$pid LIMIT 1" );

   // thread-trigger
   db_query( "approve_post.trigger_thread($tid,$pid)",
      'UPDATE Posts SET '
      . ( ($pid == $thread_lastpost && $thread_cntposts == 0) ? 'LastPost=0, ' : '' )
      . 'PostsInThread=PostsInThread-1, '
      . "Updated=GREATEST(Updated,FROM_UNIXTIME($NOW)) " // post could be a NEW one
      . "WHERE ID=$tid LIMIT 1" );

   // forum-trigger
   db_query( "approve_post.trigger_forum($fid,$pid)",
      'UPDATE Forums SET '
      . ( ($pid == $tid) ? 'ThreadsInForum=ThreadsInForum-1, ' : '' )
      . 'PostsInForum=PostsInForum-1, '
      . "Updated=GREATEST(Updated,FROM_UNIXTIME($NOW)) " // post could be a NEW one
      . "WHERE ID=$fid LIMIT 1" );

   if( $pid == $thread_lastpost && $thread_cntposts > 0 )
      recalc_thread_lastpost($tid);

   if( $pid == $forum_lastpost )
      recalc_forum_lastpost($fid);
}

// use-case A07 (show hidden post)
function show_post( $fid, $tid, $pid )
{
   $row =
      mysql_single_fetch( "show_post.find_post($pid)",
         "SELECT UNIX_TIMESTAMP(Time) AS X_Time FROM Posts "
         . "WHERE ID=$pid AND Thread_ID=$tid LIMIT 1" )
      or error('unknown_post', "show_post.find_post($pid)");
   $post_created = $row['X_Time'];

   $row =
      mysql_single_fetch( "show_post.find_thread($tid)",
         'SELECT PostsInThread, LastPost, UNIX_TIMESTAMP(Lastchanged) AS X_Lastchanged '
         . "FROM Posts WHERE ID=$tid AND Thread_ID=$tid LIMIT 1" )
      or error('unknown_post', "show_post.find_thread($tid)");
   $thread_lastchanged = $row['X_Lastchanged'];
   $thread_lastpost = $row['LastPost'];
   $thread_cntposts = $row['PostsInThread'];

   // show post
   db_query( "show_post.update_post($pid)",
      "UPDATE Posts SET Approved='Y' WHERE ID=$pid LIMIT 1" );

   // thread-trigger
   db_query( "show_post.trigger_thread($tid,$pid)",
      'UPDATE Posts SET '
      . ( ($post_created > $thread_lastchanged)
         ? "LastPost=$pid, "
           . "Lastchanged=FROM_UNIXTIME($post_created), "
           . "Updated=GREATEST(Updated,FROM_UNIXTIME($post_created)), "
         : '' )
      . 'PostsInThread=PostsInThread+1 '
      . "WHERE ID=$tid LIMIT 1" );

   // forum-trigger
   db_query( "show_post.trigger_forum($fid,$pid)",
      'UPDATE Forums SET '
      . ( ($thread_cntposts == 1) ? 'ThreadsInForum=ThreadsInForum+1, ' : '' )
      . ( ($post_created > $thread_lastchanged)
         ? "Updated=GREATEST(Updated,FROM_UNIXTIME($post_created)), "
           . ( ($thread_lastpost == $forum_lastpost)
               ? "LastPost=GREATEST(LastPost,$pid), "
               : '' )
         : '' )
      . 'PostsInForum=PostsInForum+1 '
      . "WHERE ID=$fid LIMIT 1" );
}


function recalc_thread_lastpost( $tid )
{
   $row =
      mysql_single_fetch( "recalc_thread_lastpost.find_lastpost($tid)",
         "SELECT ID, UNIX_TIMESTAMP(Time) AS X_Time FROM Posts "
         . "WHERE Thread_ID='$tid' AND Approved='Y' "
         . "AND PosIndex>'' " // ''=inactivated (edited)
         . "ORDER BY Time DESC LIMIT 1" )
      or error('unknown_post', "recalc_thread_lastpost.find_lastpost($tid)");
   $lastpost = @$row['ID'] + 0;
   $lastchanged = @$row['X_Time'] + 0;

   db_query( "recalc_thread_lastpost.update_lastpost($tid)",
      'UPDATE Posts SET '
      . "LastPost=$lastpost, Lastchanged=FROM_UNIXTIME($lastchanged) "
      . "WHERE ID='$tid' LIMIT 1" );
}

function recalc_forum_lastpost( $fid )
{
   $row =
      mysql_single_fetch( "recalc_forum_lastpost.find_lastpost($tid)",
         "SELECT ID FROM Posts "
         . "WHERE Forum_ID='$fid' AND Thread_ID>0 AND Approved='Y' "
         . "AND PosIndex>'' " // ''=inactivated (edited)
         . "ORDER BY Time DESC LIMIT 1" )
      or error('unknown_post', "recalc_forum_lastpost.find_lastpost($tid)");
   $lastpost = @$row['ID'] + 0;

   db_query( "recalc_forum_lastpost.update_lastpost($fid)",
      "UPDATE Forums SET LastPost=$lastpost WHERE ID='$tid' LIMIT 1" );
}

// recalculate Thread.PostsInThread and Forums.ThreadsInForum/PostsInForum
// for all threads in specific forum
function recalc_forum_counts( $fid, $update_thread_counts=true )
{
   $result =
      db_query( "recalc_forum_counts.find_counts($fid)",
         "SELECT Thread_ID, COUNT(*) AS X_CountPosts FROM Posts "
         . "WHERE Thread_ID>0 AND Approved='Y' "
         . "AND PosIndex>'' " // ''=inactivated (edited)
         . "AND Forum_ID='$fid' "
         . "GROUP BY Thread_ID" );

   $cnt_threads = 0;
   $sum_posts = 0;
   while( $row = mysql_fetch_array( $result ) )
   {
      $tid = $row['Thread_Id'];
      $cnt = $row['X_CountPosts'];
      $cnt_threads++;
      $sum_posts += $cnt;

      // update thread-count
      if ( $update_thread_counts )
         db_query( "recalc_forum_counts.update_thread_counts($tid)",
            "UPDATE Posts SET PostsInThread=$cnt WHERE ID='$tid' LIMIT 1" );
   }

   // update forum-counts
   db_query( "recalc_forum_counts.update_forum_counts($fid)",
      "UPDATE Forums SET ThreadsInForum=$cnt_threads, PostsInForum=$sum_posts "
      . "WHERE ID='$fid' LIMIT 1" );
}

?>
