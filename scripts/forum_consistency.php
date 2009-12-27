<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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


// Checks and fixes errors in Forum fields in the database.
// see section 'Calculated database fields' in 'specs/forums.txt' for necessary forum-updates !!

chdir( '../' );
require_once( 'include/std_functions.php' );
require_once( 'include/gui_functions.php' );
require_once( 'include/table_columns.php' );
require_once( 'forum/forum_functions.php' );

define('SEPLINE', "\n<p><hr>\n");


{
   $beginall = getmicrotime();
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'forum_consistency');

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();
   //$page_args['limit'] = $lim;

   start_html( 'forum_consistency', 0, '',
      "  table.Table { border:0; background: #c0c0c0; }\n" .
      "  tr.Row1 { background: #ffffff; }\n" .
      "  tr.Row2 { background: #dddddd; }\n" .
      "  tr.hil { background: #ffb010; }" );

   $withnew = get_request_arg('withnew');
   if( $do_it = @$_REQUEST['do_it'] )
   {
      echo "<p>*** Fixes errors ***"
         ."<br>".anchor(make_url($page, $page_args), 'Just show it')
         ."</p>";
   }
   else
   {
      $tmp = array_merge($page_args,array('do_it' => 1));
      $tmp2 = array_merge($page_args,array('do_it' => 1, 'withnew' => 1 ));
      echo "<p>(just show needed queries)"
         ."<br>".anchor(make_url($page, $page_args), 'Show it again')
         ."<br>".anchor(make_url($page, $tmp), '[Validate it]')
         .SMALL_SPACING. anchor(make_url($page, $tmp2), '[Validate it with NEW-fixes]')
         ."</p>";
   }
   $debug = !$do_it;
   $cnt_err = 0;

   echo "On ", date(DATE_FMT5, $NOW);


//----------------- Fix all forums

   $begin = getmicrotime();
   echo SEPLINE;

   $forumlist = Forum::load_fix_forum_list();
   foreach( $forumlist as $forum )
   {
      echo sprintf( "Check forum ID [%s] Name [%s]: LastPost=%s, PostsInThread=%s, ThreadsInForum=%s :<br>\n",
            $forum->id, $forum->name, $forum->last_post_id, $forum->count_posts, $forum->count_threads );
      $cnt_err += $forum->fix_forum( $debug, "<pre>%s</pre>\n" );
   }

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin))
      , " - Forums Done.";

//----------------- Fix all threads

   $begin = getmicrotime();
   echo SEPLINE;

   echo "Read all threads ...<br>\n";

   $result = db_query( 'forum_consistency.load_threads',
      'SELECT ID, Forum_ID, PostsInThread, LastPost, UNIX_TIMESTAMP(Lastchanged) AS X_Lastchanged ' .
      'FROM Posts ' .
      "WHERE Thread_ID>0 AND Parent_ID=0" );
   $threads = array(); # ID => ID,Forum_ID,PostsInThread,LastPost,X_Lastchanged
   while( $row = mysql_fetch_array( $result ) )
   {
      $threads[$row['ID']] = $row;
   }
   mysql_free_result($result);

   //------------------------ Fix Posts.PostsInThread

   echo sprintf( "Check Posts.PostsInThread for all %s threads ...<br>\n", count($threads) );

   $result = db_query( 'forum_consistency.read_thread.PostsInThread',
      "SELECT Thread_ID, COUNT(*) AS X_CountPosts FROM Posts " .
      "WHERE Thread_ID>0 AND Approved='Y' AND PosIndex>'' " .
      "GROUP BY Thread_ID" );
   $upd_arr = array();
   while( $row = mysql_fetch_array( $result ) )
   {
      $tid = $row['Thread_ID'];
      if( isset($threads[$tid]) && ($threads[$tid]['PostsInThread'] != $row['X_CountPosts']) )
         $upd_arr[] = "UPDATE Posts SET PostsInThread={$row['X_CountPosts']} WHERE ID='$tid' LIMIT 1";
   }
   mysql_free_result($result);
   $cnt_err += count($upd_arr);

   do_updates( 'forum_consistency.update_thread.PostsInThread', $upd_arr, $do_it );

   //------------------------ Fix Posts.LastPost / Lastchanged

   echo sprintf( "Check Posts.LastPost,Lastchanged for all %s threads ...<br>\n", count($threads) );

   $upd_arr = array();
   foreach( $threads as $tid => $thread )
   {
      $row = mysql_single_fetch( "forum_consistency.read_thread.LastPost_Lastchanged($tid)",
         "SELECT ID AS X_LastPost, UNIX_TIMESTAMP(Time) AS X_Lastchanged " .
         "FROM Posts " .
         "WHERE Thread_ID='$tid' AND Approved='Y' AND PosIndex>'' " .
         "ORDER BY Time DESC LIMIT 1" );
      if( $row && ($row['X_LastPost'] != $thread['LastPost']
                || $row['X_Lastchanged'] != $thread['X_Lastchanged'] ))
      {
         $upd_arr[] = "UPDATE Posts SET LastPost={$row['X_LastPost']}, " .
            "Lastchanged=FROM_UNIXTIME({$row['X_Lastchanged']}) WHERE ID='$tid' LIMIT 1";
      }
   }
   $cnt_err += count($upd_arr);

   do_updates( 'forum_consistency.update_thread.LastPost_Lastchanged', $upd_arr, $do_it );

   //------------------------ Fix distinct Posts.Forum_ID per threads

   echo sprintf( "Check for distinct Posts.Forum_ID for all %s threads ...<br>\n", count($threads) );

   $result = db_query( 'forum_consistency.read_thread.distinct_forum_id',
      "SELECT Thread_ID, COUNT(DISTINCT Forum_ID) AS X_Count, COUNT(*) AS X_AllCount " .
      "FROM Posts " .
      "WHERE Approved='Y' AND PosIndex>'' AND Thread_ID>0 " .
      "GROUP BY Thread_ID " .
      "HAVING X_Count > 1" );
   $upd_arr = array();
   while( $row = mysql_fetch_array( $result ) )
   {
      $tid = $row['Thread_ID'];
      if( isset($threads[$tid]) )
      {
         $fix_fid = $threads[$tid]['Forum_ID'];
         $upd_arr[] = "UPDATE Posts SET Forum_ID='$fix_fid' " .
            "WHERE Thread_ID='$tid' AND Forum_ID<>'$fix_fid' LIMIT {$row['X_AllCount']}";
      }
   }
   mysql_free_result($result);
   $cnt_err += count($upd_arr);

   do_updates( 'forum_consistency.update_thread.distinct_forum_idLastPost_Lastchanged', $upd_arr, $do_it );

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin))
      , " - Threads Done.";

//----------------- Fix all Updates for NEW-count for threads

   $begin = getmicrotime();
   echo SEPLINE;

   $upd_arr = array();
   echo "Check Threads Updated-fields for NEW-counts ...<br>\n";

   $query_where =
      "WHERE ((Lastchanged>0 AND Lastchanged > Updated) OR (Updated=0)) AND " .
         "Thread_ID>0 AND Parent_ID=0 AND Approved='Y'";
   $row = mysql_single_fetch( 'forum_consistency.threads.read_updated',
      "SELECT COUNT(*) AS X_Count " .
      "FROM Posts $query_where LIMIT 1" );
   if( $row && ($row['X_Count'] > 0) )
   {
      $cnt = $row['X_Count'];
      $upd_arr[] = "UPDATE Posts SET Updated=GREATEST(Updated,Lastchanged) $query_where LIMIT $cnt";
      $cnt_err += $cnt;
   }

   do_updates( 'forum_consistency.threads.update_updated', $upd_arr, ($do_it && $withnew) );

//----------------- Check posts integrity (author)

   $begin = getmicrotime();
   echo SEPLINE;
   echo "Check for existing authors ...<br>\n";

   // note: join slightly faster than using subquery: Posts where User_ID not in (select ID from Players)
   $result = db_query( 'forum_consistency.check_authors',
      "SELECT Forum_ID, Thread_ID, Posts.ID AS Post_ID, User_ID, PL.ID AS Author " .
      "FROM Posts LEFT JOIN Players AS PL ON PL.ID=Posts.User_ID " .
      "HAVING ISNULL(Author)" );
   $result_count = @mysql_num_rows($result);
   if( $result_count > 0)
      echo "<p><font color=darkred><b>NOTE: The following errors can't be fixed automatically!!</b></font><br>\n";
   while( $row = mysql_fetch_array( $result ) )
   {
      $cnt_err++;
      echo sprintf( "Found post with User_ID=%s for: Posts WHERE ID=%s AND Forum_ID=%s AND Thread_ID=%s<br>\n",
         $row['User_ID'], $row['Post_ID'], $row['Forum_ID'], $row['Thread_ID'] );
   }
   mysql_free_result($result);

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin))
      , " - Author check Done.";

//-----------------

   echo SEPLINE;

   echo sprintf( "<font color=red><b>Found %s errors (inconsistencies).</b></font><br>\n", $cnt_err );
   echo "<b>IMPORTANT NOTE:</b> Run this script at least twice until no errors found, because fixes influence each other!<br>\n";

   echo "\n<br>Needed (all): " . sprintf("%1.3fs", (getmicrotime() - $beginall));
   echo "\n<br>Done!!!\n";
   end_html();
}


function do_updates( $dbgmsg, $upd_arr, $do_it )
{
   if( count($upd_arr) == 0 )
      return;

   echo '<pre>';
   foreach( $upd_arr as $query )
   {
      echo $query, "\n";
      if( $do_it )
         db_query( $dbgmsg, $query );
   }
   echo '</pre>';
}
?>
