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

/*!
 * \file forum_functions.php
 *
 * \brief Helper-class to manage forum-reads NEW-flag
 */

$TranslateGroups[] = "Forum"; //local use

require_once( 'include/std_classes.php' );
require_once( 'include/connect2mysql.php' );
require_once( 'forum/class_forum_options.php' );



define('CACHEFILE_FORUM_GLOBAL', CACHE_FOLDER.'cache_forum_global_updated');

 /*!
  * \class ForumRead
  *
  * \brief Class to help with handling forum-reads and cope with 'new'-flag.
  *
  * NOTE: Dates are stored in UNIX-time
  * NOTE: expected row-fields: Forum_ID, Thread_ID, X_Time, HasNew
  * NOTE: need combined index on IDs for correct working of update-funcs
  */
class ForumRead
{
   var $uid;
   var $fid;
   var $tid;
   var $reads; // [ "fid,tid" => unix-time ]
   var $min_date; // posts older (or equal) than min_date are considered as read

   /*! \brief Constructs a ForumUser with specified args. */
   function ForumRead( $user_id, $forum_id=0, $thread_id=0 )
   {
      $this->uid = $user_id;
      $this->fid = $forum_id;
      $this->tid = $thread_id;
      $this->reads = array();
      $this->min_date = ForumRead::get_min_date();
   }

   /*!
    * \brief Returns true, if specified check-date is older than read-date
    *        stored for specified forum and thread.
    *        So is a read (and no "new") post.
    */
   function has_newer_read_date( $chkdate, $forum_id, $thread_id )
   {
      $time = (int)@$this->reads["$forum_id,$thread_id"];
      return ( $time >= $chkdate );
   }

   /*! \brief Loads forum-reads for thread-post view into this ForumRead-object. */
   function load_forum_reads()
   {
      $qsql = ForumRead::build_query_sql( $this->uid );
      $qsql->add_part( SQLP_WHERE,
         'Forum_ID='.$this->fid,
         'Thread_ID='.$this->tid );
      $query = $qsql->get_select();
      $result = db_query( "ForumRead.load_forum_reads({$this->uid},{$this->fid},{$this->tid})", $query );

      while( $row = mysql_fetch_array( $result ) )
      {
         $key = $row['Forum_ID'] . ',' . $row['Thread_ID'];
         $this->reads[$key] = $row['X_Time'];
      }
      mysql_free_result($result);
   }

   /*! \brief Replaces Forumreads-db-entry with time and new-flag for specified fid/tid-key. */
   function replace_row_forumread( $dbgmsg, $fid, $tid, $time, $has_new=false )
   {
      // IMPORTANT NOTE:
      // - 'INSERT .. ON DUPLICATE UPDATE' need at least 5.0.38 for replication-bugfix,
      //   can also be used for multi-row inserts.
      // - REPLACE is slower as it would be a combined DELETE + INSERT
      //   with full index-updating on both steps.
      $new_val = ($has_new) ? 1 : 0;
      db_query( $dbgmsg."({$this->uid},$fid,$tid)",
         "INSERT INTO Forumreads (User_ID,Forum_ID,Thread_ID,Time,HasNew) "
            . "VALUES ({$this->uid},$fid,$tid,FROM_UNIXTIME($time),$new_val) "
            . "ON DUPLICATE KEY UPDATE Time=VALUES(Time), HasNew=VALUES(HasNew)" );
   }

   /*!
    * \brief Marks posts in thread as read that are older than specified (read-)time.
    * \see specs/forums.txt (use-case U03)
    */
   function mark_thread_read( $thread_id, $mark_time )
   {
      if( $this->fid > 0 && $thread_id > 0 && $mark_time >= $this->min_date )
      {
         $this->replace_row_forumread( "ForumRead.mark_thread_read",
            $this->fid, $thread_id, $mark_time );
         ForumRead::trigger_recalc_forum_read_update( $this->uid, $this->fid );
         ForumRead::trigger_recalc_global_read_update( $this->uid );
      }
   }

   /*!
    * \brief Marks threads in forum-list as read that are older than specified (read-)time.
    * \see specs/forums.txt (use-case U09)
    */
   function mark_forum_read( $mark_time, $is_moderator )
   {
      if( $this->fid <= 0 || $mark_time < $this->min_date )
         return;

      $min_date = ForumRead::get_min_date();
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'P.Thread_ID',
         'UNIX_TIMESTAMP(P.Lastchanged) AS X_Lastchanged',
         'IF(ISNULL(FR.User_ID),0,UNIX_TIMESTAMP(FR.Time)) AS FR_X_Lastread' );
      $qsql->add_part( SQLP_FROM,
         'Posts AS P',
         "LEFT JOIN Forumreads AS FR ON FR.User_ID='{$this->uid}' AND FR.Forum_ID='{$this->fid}' "
            . 'AND FR.Thread_ID=P.Thread_ID' );
      $qsql->add_part( SQLP_WHERE,
         "P.Forum_ID=".$this->fid,
         'P.Parent_ID=0', 'P.Thread_ID=P.ID', //=thread (better query with thread=id for Forumreads)
         "P.Lastchanged >= FROM_UNIXTIME($min_date)" );
      if( !$is_moderator )
         $qsql->add_part( SQLP_WHERE, 'P.PostsInThread>0' );
      $query = $qsql->get_select();
      $result = db_query( "ForumRead.mark_forum_read.load({$this->uid},{$this->fid})", $query );

      $upd_arr = array();
      $ins_arr = array();
      while( $row = mysql_fetch_array( $result ) )
      {
         if( $row['FR_X_Lastread'] > 0 )
         {
            if( $row['X_Lastchanged'] > $row['FR_X_Lastread'] )
               $upd_arr[] = $row['Thread_ID'];
         }
         else
            $ins_arr[] = $row['Thread_ID'];
      }
      mysql_free_result($result);

      if( count($upd_arr) ) // update existing Forumreads-entries
      {
         $upd_threads = implode(',', $upd_arr);
         db_query( "ForumRead.mark_forum_read.update(({$this->uid},{$this->fid})",
            "UPDATE Forumreads SET Time=GREATEST(Time,FROM_UNIXTIME($mark_time)) " .
            "WHERE User_ID={$this->uid} AND Forum_ID={$this->fid} AND Thread_ID>0 AND " .
               "Thread_ID IN ($upd_threads)" );
      }

      if( count($ins_arr) ) // insert new Forumreads-entries for NEW-threads
      {
         $query = "INSERT INTO Forumreads (User_ID,Forum_ID,Thread_ID,Time) VALUES ";

         $first = true;
         foreach( $ins_arr as $thread_id )
         {
            if( !$first ) $query .= ', ';
            $first = false;
            $query .= "({$this->uid},{$this->fid},$thread_id,FROM_UNIXTIME($mark_time))";
         }

         // use ON-DUPLICATE to avoid race-condiditions
         db_query( "ForumRead.mark_forum_read.insert(({$this->uid},{$this->fid})",
            $query . " ON DUPLICATE KEY UPDATE Time=GREATEST(Time,VALUES(Time))" );
      }

      ForumRead::trigger_recalc_forum_read_update( $this->uid, $this->fid );
      ForumRead::trigger_recalc_global_read_update( $this->uid );
   } //mark_forum_read

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   function to_string()
   {
      $reads = '';
      foreach( $this->reads as $k => $time )
         $reads .= sprintf( "\n{ [$k]=[%s] },", date('Y-m-d H:i:s', $time));
      return "ForumRead(uid={$this->uid},fid={$this->fid},tid={$this->tid}): "
         . "min_date=[{$this->min_date}], "
         . $reads;
   }


   // ---------- Static Class functions ----------------------------

   /*! \brief Builds basic QuerySQL to load forum-reads. */
   function build_query_sql( $uid )
   {
      // Forumreads: User_ID, Forum_ID, Thread_ID, Time
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS, 'FR.*', 'UNIX_TIMESTAMP(FR.Time) AS X_Time' );
      $qsql->add_part( SQLP_FROM, 'Forumreads AS FR' );
      $qsql->add_part( SQLP_WHERE, "FR.User_ID='$uid'" );
      return $qsql;
   }

   /*! \brief Updates global forum-read for user. */
   function update_global_forum_read( $dbgmsg, $uid, $time, $has_new=false )
   {
      $new_val = ($has_new) ? 1 : 0;
      return db_query( "ForumRead::update_global_forum_read.$dbgmsg($uid,$time,$new_val)",
         "UPDATE Players SET ForumReadTime=FROM_UNIXTIME($time), ForumReadNew=$new_val " .
         "WHERE ID=$uid LIMIT 1" );
   }

   /*!
    * \brief Returns true, if there is at least one new post in all forums or in specific forum.
    * \param $forum_id if 0 check for all forums, otherwise check for given forum
    */
   function has_new_posts_in_forums( $uid, $forum_id )
   {
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'P.Lastchanged',
         "IF(ISNULL(FR.User_ID),'0000-00-00 00:00:00',FR.Time) AS FR_Lastread" );
      $qsql->add_part( SQLP_FROM,
         "Posts AS P",
         "LEFT JOIN Forumreads AS FR " .
            "ON FR.Forum_ID=P.Forum_ID AND FR.Thread_ID=P.Thread_ID AND FR.User_ID='$uid'" );
      $qsql->add_part( SQLP_WHERE,
         'P.ID=P.Thread_ID',
         'P.Parent_ID=0', // redundant, but sometimes query is faster (intersect-index-merge)
         'P.PostsInThread>0',
         'P.Lastchanged >= FROM_UNIXTIME('.ForumRead::get_min_date().')' );
      if( $forum_id >= 0 )
         $qsql->add_part( SQLP_WHERE, 'P.Forum_ID='.$forum_id );
      $qsql->add_part( SQLP_HAVING, 'P.Lastchanged > FR_Lastread' );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $query = $qsql->get_select();
      $has_new = mysql_exists_row( "ForumRead::has_new_posts_in_forums($uid,$forum_id)", $query );
      return $has_new;
   } //has_new_posts_in_forums

   /*!
    * \brief Returns true, if there is at least one new post in all forums user can view.
    * \param $forum_opts ForumOptions-object with user-id
    */
   function has_new_posts_in_all_forums( $forum_opts )
   {
      $uid = $forum_opts->uid;
      $exclude_fopts = (int)$forum_opts->build_db_options_exclude();

      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'P.Lastchanged',
         "IF(ISNULL(FR.User_ID),'0000-00-00 00:00:00',FR.Time) AS FR_Lastread" );
      $qsql->add_part( SQLP_FROM,
         "Posts AS P",
         "INNER JOIN Forums ON Forums.ID=P.Forum_ID",
         "LEFT JOIN Forumreads AS FR " .
            "ON FR.Forum_ID=P.Forum_ID AND FR.Thread_ID=P.Thread_ID AND FR.User_ID='$uid'" );
      if( $exclude_fopts > 0 )
         $qsql->add_part( SQLP_WHERE, "Forums.Options & $exclude_fopts = 0" );
      $qsql->add_part( SQLP_WHERE,
         'P.ID=P.Thread_ID',
         'P.Parent_ID=0', // redundant, but sometimes query is faster (intersect-index-merge)
         'P.PostsInThread>0',
         'P.Lastchanged >= FROM_UNIXTIME('.ForumRead::get_min_date().')' );
      $qsql->add_part( SQLP_HAVING, 'P.Lastchanged > FR_Lastread' );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $query = $qsql->get_select();
      $has_new = mysql_exists_row( "ForumRead::has_new_posts_in_all_forums($uid)", $query );
      return $has_new;
   } //has_new_posts_in_all_forums

   /*!
    * \brief Returns boolean if forums have new posts for specified user-id (in ForumOptions).
    * \param $forum_opts ForumOptions-object for given user-id
    * \note stores result for user in Forumreads-table
    */
   function load_global_new( $forum_opts )
   {
      global $player_row;

      if( !is_a($forum_opts, 'ForumOptions') )
         error('invalid_args', "ForumRead::load_global_new.check.forum_opts($forum_opts)");
      $user_id = $forum_opts->uid;

      $global_updated = ForumRead::load_global_updated();
      $global_has_new = false;

      // load (from player_row) and check against global forum-read for user
      $fr_time = (int)@$player_row['X_ForumReadTime'];
      if( $fr_time <= 0 || $global_updated > $fr_time )
      {
         $global_has_new = ForumRead::has_new_posts_in_all_forums( $forum_opts );
         $success_update = ForumRead::update_global_forum_read( "load_global_new",
            $user_id, $global_updated, $global_has_new );
         if( $success_update )
         {
            $player_row['X_ForumReadTime'] = $global_updated;
            $player_row['ForumReadNew'] = ($global_has_new) ? 1 : 0;
         }
      }
      else
         $global_has_new = (bool)$player_row['ForumReadNew'];

      return $global_has_new;
   } //load_global_new

   /*! \brief Returns non-0 global forum Updated date (creates one if not existing). */
   function load_global_updated( $need_update=true )
   {
      // assume updated=true if no file-cache (inefficient but functionality available)
      if( (string)CACHE_FOLDER == '' )
         return true;

      // read from file-cache
      clearstatcache(); //FIXME since PHP5.3.0 with filename
      $updated = ( file_exists(CACHEFILE_FORUM_GLOBAL) )
         ? (int)@filemtime(CACHEFILE_FORUM_GLOBAL)
         : -1;

      if( $need_update && $updated <= 0 ) // need-update ?
      {
         global $NOW;
         $updated = $NOW;
         ForumRead::trigger_recalc_global_post_update( $updated );
      }
      return $updated;
   }

   /*! \brief Updates global-forum Updated-date because of change to post (stored in file-system). */
   function trigger_recalc_global_post_update( $time )
   {
      if( (string)CACHE_FOLDER != '' )
      {
         $updated = ForumRead::load_global_updated( false );
         if( $updated < 0 || $time > $updated ) // update only if newer
            touch(CACHEFILE_FORUM_GLOBAL, $time );
      }
   }

   /*! \brief Updates Forumreads to initiate recalculation for global-forum NEW-flag-state. */
   function trigger_recalc_global_read_update( $uid )
   {
      // force trigger updating with older updated-date on next load-global
      $global_updated = ForumRead::load_global_updated( false );
      return ForumRead::update_global_forum_read( "trigger_recalc_global_read_update",
         $uid, $global_updated - SECS_PER_DAY, false );
   }

   /*! \brief Updates Forumreads to initiate recalculation for forum NEW-flag-state. */
   function trigger_recalc_forum_read_update( $uid, $forum_id )
   {
      // force trigger updating with older updated-date on next load-global
      global $NOW;
      $row = mysql_single_col( "ForumRead::trigger_recalc_forum_read_update.read_forum($uid,$forum_id)",
         "SELECT UNIX_TIMESTAMP(Updated) AS X_Updated FROM Forums WHERE ID='$forum_id' LIMIT 1" );
      $rowval = ( $row ) ? $row[0] : 0;
      $forum_updated = ($rowval > 0) ? $rowval : $NOW;

      $fread = new ForumRead( $uid, $forum_id );
      $fread->replace_row_forumread( "ForumRead::trigger_recalc_forum_read_update.upd",
         $forum_id, 0, $forum_updated - SECS_PER_DAY );
   }

   function get_min_date()
   {
      global $NOW;
      return $NOW - FORUM_SECS_NEW_END;
   }

} // end of 'ForumRead'

?>
