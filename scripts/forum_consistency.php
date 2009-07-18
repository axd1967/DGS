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


// Checks and fixes errors in Forum fields in the database.
// see section 'Calculated database fields' in 'specs/forums.txt' for necessary forum-updates !!

chdir( '../' );
require_once( 'include/std_functions.php' );
require_once( 'include/table_columns.php' );
require_once( 'forum/forum_functions.php' );


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

   if( $do_it = @$_REQUEST['do_it'] )
   {
      echo "<p>*** Fixes errors ***"
         ."<br>".anchor(make_url($page, $page_args), 'Just show it')
         ."</p>";
   }
   else
   {
      $tmp = array_merge($page_args,array('do_it' => 1));
      echo "<p>(just show needed queries)"
         ."<br>".anchor(make_url($page, $page_args), 'Show it again')
         ."<br>".anchor(make_url($page, $tmp), '[Validate it]')
         ."</p>";
   }
   $debug = !$do_it;
   $cnt_err = 0;

   echo "<br>On ", date(DATE_FMT, $NOW), ' GMT<br>';

//----------------- Fix all forums

   $begin = getmicrotime();
   echo "<p>", str_repeat('-', 60), "<br>\n";

   $forumlist = Forum::load_fix_forum_list();
   foreach( $forumlist as $forum )
   {
      echo sprintf( "Check forum ID [%s] Name [%s]: LastPost=%s, PostsInThread=%s, ThreadsInForum=%s :<br>\n",
            $forum->id, $forum->name, $forum->last_post_id, $forum->count_posts, $forum->count_threads );
      echo '<pre>';
      $cnt_err += $forum->fix_forum( $debug );
      echo '</pre>';
   }

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin));
   echo "\n<br>Forums Done.";

//----------------- Fix all threads

   $begin = getmicrotime();
   echo "<p>", str_repeat('-', 60), "<br>\n";

   echo "Read all threads ...<br>\n";

   $query =
      'SELECT ID, Forum_ID, PostsInThread, LastPost, UNIX_TIMESTAMP(Lastchanged) AS X_Lastchanged ' .
      'FROM Posts AS P ' .
      "WHERE Thread_ID>0 AND Parent_ID=0 AND Approved='Y'";
   $result = db_query( 'forum_consistency.load_threads', $query );
   $threads = array(); # ID => ID,Forum_ID,PostsInThread,LastPost,X_Lastchanged
   while( $row = mysql_fetch_array( $result ) )
   {
      $threads[$row['ID']] = $row;
   }
   mysql_free_result($result);

   //------------------------ Fix Posts.PostsInThread

   echo sprintf( "Check Posts.PostsInThread for all %s threads ...<br>\n", count($threads) );

   $query = "SELECT Thread_ID, COUNT(*) AS X_CountPosts FROM Posts " .
      "WHERE Thread_ID>0 AND Approved='Y' AND PosIndex>'' GROUP BY Thread_ID";
   $result = db_query( 'forum_consistency.read_thread.PostsInThread', $query );
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
         "SELECT ID AS X_LastPost, UNIX_TIMESTAMP(Time) AS X_Lastchanged FROM Posts " .
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

   $query = "SELECT Thread_ID, COUNT(DISTINCT Forum_ID) AS X_Count, COUNT(*) AS X_AllCount FROM Posts " .
      "WHERE Approved='Y' AND PosIndex>'' AND Thread_ID>0 GROUP BY Thread_ID HAVING X_Count > 1";
   $result = db_query( 'forum_consistency.read_thread.distinct_forum_id', $query );
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


   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin));
   echo "\n<br>Threads Done.";

//----------------- Fix all Updates for NEW-count for threads and forums

   $begin = getmicrotime();
   echo "<p>", str_repeat('-', 60), "<br>\n";

   $upd_arr = array();
   echo "Check Posts and Forums Updated-fields for NEW-counts ...<br>\n";
   $row = mysql_single_fetch( 'forum_consistency.read_updated',
      "SELECT COUNT(*) AS X_Count FROM Posts " .
      "WHERE ((Lastchanged > Updated) OR (Updated=0)) AND " .
         "Thread_ID>0 AND Parent_ID=0 AND Approved='Y' LIMIT 1" );
   if( $row && ($row['X_Count'] > 0) )
   {
      $cnt = $row['X_Count'];
      $upd_arr[] = "UPDATE Posts SET Updated=GREATEST(Updated,Lastchanged) " .
         "WHERE ((Lastchanged > Updated) OR (Updated=0)) AND " .
            "Thread_ID>0 AND Parent_ID=0 AND Approved='Y' LIMIT $cnt";
      $cnt_err += $cnt;
   }

   do_updates( 'forum_consistency.update_updated', $upd_arr, $do_it );


//-----------------

   echo sprintf( "<br><p><b><font color=red>Found %s errors (inconsistencies).</font></b><br>\n", $cnt_err );
   echo "<b>IMPORTANT NOTE:</b> Run this script at least twice until no errors found, because fixes influence each other!<br>\n";

   echo "\n<br>Needed (all): " . sprintf("%1.3fs", (getmicrotime() - $beginall));
   echo "\n<br>Needed (all): " . sprintf("%1.3fs", (getmicrotime() - $beginall));
   echo "<hr>Done!!!\n";
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
