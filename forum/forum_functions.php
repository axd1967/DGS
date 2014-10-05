<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
 * \brief Functions for forum management.
 */

$TranslateGroups[] = "Forum"; //local use

require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/std_classes.php';
require_once 'include/form_functions.php';
require_once 'include/classlib_user.php';
require_once 'include/classlib_goban.php';
require_once 'include/rating.php';
require_once 'include/dgs_cache.php';
require_once 'forum/class_forum_options.php';
require_once 'forum/class_forum_read.php';
//if ( ALLOW_GO_DIAGRAMS ) require_once 'include/GoDiagram.php';


//must follow the "ORDER BY PosIndex" order and have at least 64 chars:
define('ORDER_STR', "*+-/0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");
define('FORUM_MAX_DEPTH', 40); //half the length of the Posts.PosIndex field
define('FORUM_MAX_INDENT', 25); //at the display time


define("LINK_FORUMS", 1 << 0);
define("LINK_THREADS", 1 << 1);
define("LINK_BACK_TO_THREAD", 1 << 2);
define("LINK_NEW_TOPIC", 1 << 3);
define("LINK_SEARCH", 1 << 4);
define("LINK_MARK_READ", 1 << 5);
define("LINK_PREV_PAGE", 1 << 6);
define("LINK_NEXT_PAGE", 1 << 7);
define("LINKPAGE_READ", 1 << 8);
define("LINKPAGE_LIST", 1 << 9);
define("LINKPAGE_INDEX", 1 << 10);
define("LINKPAGE_SEARCH", 1 << 11);
define("LINK_TOGGLE_MODERATOR", 1 << 12);
define("LINKPAGE_STATUS", 1 << 13); // used for status-page
define("LINK_REFRESH", 1 << 14);

define("LINK_MASKS", ~(LINKPAGE_READ | LINKPAGE_LIST | LINKPAGE_INDEX
          | LINKPAGE_SEARCH | LINKPAGE_STATUS) );


// Forumlog.Action
// NOTE: when adding new also adjust forum/admin_show_forumlog.php
// * for all users (incl. guest); new_..:(new_thread|reply)
define('FORUMLOGACT_NEW_POST',      'new_post');
define('FORUMLOGACT_NEW_PEND_POST', 'new_pend_post');
define('FORUMLOGACT_EDIT_POST',     'edit_post');
define('FORUMLOGACT_EDIT_PEND_POST', 'edit_pend_post');
// * for moderators
define('FORUMLOGACT_APPROVE_POST',  'approve_post');
define('FORUMLOGACT_REJECT_POST',   'reject_post');
define('FORUMLOGACT_SHOW_POST',     'show_post');
define('FORUMLOGACT_HIDE_POST',     'hide_post');
// * new_(pend_)post:(new_thread|reply)
define('FORUMLOGACT_SUFFIX_NEW_THREAD', ':new_thread');
define('FORUMLOGACT_SUFFIX_REPLY',      ':reply');

// Posts.Flags
define('FPOST_FLAG_READ_ONLY', 0x01);


/* \brief Adds entry into Forumlog */
function add_forum_log( $tid, $pid, $action )
{
   global $player_row, $NOW;
   $uid = @$player_row['ID'];
   if ( $uid <= 0 )
      $uid = 1; // use guest-user as default-user
   $ip = (string) @$_SERVER['REMOTE_ADDR'];

   db_query( "forum.add_forum_log.insert($uid,$tid,$pid,$action)",
         "INSERT INTO Forumlog SET " .
               "User_ID='$uid', " .
               "Thread_ID='$tid', " .
               "Post_ID='$pid', " .
               "Time=FROM_UNIXTIME($NOW), " .
               "Action='$action', " .
               "IP='$ip' " );
}

/* \brief Returns Forum_ID for specified thread. */
function load_forum_id( $thread )
{
   $result = db_query( 'load_forum_id',
      "SELECT Forum_ID FROM Posts WHERE ID='$thread' LIMIT 1" );

   if ( @mysql_num_rows($result) != 1 )
      return 0;

   $row = mysql_fetch_array($result);
   return @$row['Forum_ID'];
}

// show list with posts on pending-approval (used on status-page)
// returns number of pending-approval posts
function display_posts_pending_approval()
{
   $result = // fields matching ForumPost::new_from_row
      db_query( 'display_posts_pending_approval.find',
         'SELECT Posts.ID, Forum_ID, Thread_ID, Flags, Subject, UNIX_TIMESTAMP(Time) as X_Time, '
            . 'User_ID, PAuthor.Name AS Author_Name, PAuthor.Handle AS Author_Handle, '
            . 'Forums.Name AS X_Forumname '
         . 'FROM Posts '
            . 'INNER JOIN Players AS PAuthor ON PAuthor.ID=Posts.User_ID '
            . 'INNER JOIN Forums ON Forums.ID=Posts.Forum_ID '
         . "WHERE Approved='P' ORDER BY Time" );

   $cnt = 0;
   if ( mysql_num_rows($result) > 0 )
   {
      $disp_forum = new DisplayForum( 0, false );
      $disp_forum->cols = $cols = 4;
      $disp_forum->headline = array(
         T_('Posts pending approval') => "colspan=".($cols-1),
         anchor( 'forum/admin_show_forumlog.php', T_('Show Forum Log') ) => '',
      );
      $disp_forum->links = LINKPAGE_STATUS;
      $disp_forum->forum_start_table('Pending');

      while ( $row = mysql_fetch_array( $result ) )
      {
         $post = ForumPost::new_from_row($row);
         $forum_name = $row['X_Forumname'];

         $Subject = make_html_safe( $post->subject, SUBJECT_HTML);
         $post_href = $post->build_url_post( null, 'moderator=y' );

         $color = ( ($cnt++ % 2) ? "" : " bgcolor=white" );
         echo "<tr$color>"
            . '<td>' . ( $cols > 3 ? $forum_name . '</td><td>' : '' )
            . "<a href=\"forum/$post_href\">$Subject</a></td><td>"
            . $post->author->user_reference()
            . '</td><td nowrap align=right>' . date(DATE_FMT, $post->created) . "</td></tr>\n";
      }
      $disp_forum->forum_end_table();
   }

   mysql_free_result($result);
   return $cnt;
} //display_posts_pending_approval


// mode-bitmask for get_new_string() and echo_links()
define('NEWMODE_TOP',      0x01);
define('NEWMODE_BOTTOM',   0x02);
define('NEWMODE_OVERVIEW', 0x04);
define('NEWMODE_NO_LINK',  0x08);

// draw-modes for draw_post()
define('MASK_DRAWPOST_MODES', 0x0f); // lower 4 bits reserved for draw-modes
define('DRAWPOST_NORMAL',  1); // post normal view
define('DRAWPOST_PREVIEW', 2); // post preview
define('DRAWPOST_EDIT',    3); // post edit
define('DRAWPOST_REPLY',   4); // post reply
define('DRAWPOST_SEARCH',  5); // post search-result view
define('MASK_DRAWPOST_HIDDEN', 0x10); // post-header hidden view
define('MASK_DRAWPOST_NO_BODY', 0x20); // no post-body shown
define('MASK_DRAWPOST_NO_NUM', 0x40); // no post-numbering

 /*!
  * \class DisplayForum
  *
  * \brief Class to help with display of forum-pages.
  */
class DisplayForum
{
   /*! \brief Current logged in user */
   private $user_id;
   /*! \brief true, if in moderating-mode */
   public $is_moderator;
   /*! \brief current forum-id (maybe 0) */
   private $forum_id;
   /*! \brief current thread-id (maybe 0) */
   private $thread_id;

   public $cols = 1;
   public $links = 0;
   public $headline = array();
   private $link_array_left = array();
   private $link_array_right = array();
   private $found_rows = -1; // not shown
   public $count_new_posts = 0; // for get_new_string() and echo_link() to support "first new"
   private $new_count = 0; // global new-counter for displaying
   public $back_post_id = 0;
   public $cur_depth = -1;
   public $show_score = false; // used for forum-search
   /*! \brief rx-terms (optionally array) that are to be highlighted in text. */
   private $rx_term = '';
   private $ConfigBoard = null;
   private $forum_opts = null;
   public $flat_view = 0; // 0 = tree-view, -1 = flat-old-first (order Posts.Time ASC), 1 = flat-new-first (order Posts.Time DESC)

   // consts
   public $max_rows = MAXROWS_PER_PAGE_DEFAULT;
   public $offset = 0;
   private $navi_img = null;

   /*! \brief Constructs display handler for forum-pages. */
   public function __construct( $user_id, $is_moderator, $forum_id=0, $thread_id=0 )
   {
      $this->user_id = $user_id;
      $this->is_moderator = $is_moderator;
      $this->forum_id = $forum_id;
      $this->thread_id = $thread_id;
   }

   /*! \brief Setting rx-term (can be array or string). */
   public function set_rx_term( $rx_term='' )
   {
      // highlight terms (skipping XML-elements like tags & entities)
      if ( is_array($rx_term) && count($rx_term) > 0 )
         $this->rx_term = implode('|', $rx_term);
      elseif ( !is_string($rx_term) )
         $this->rx_term = '';
      else
         $this->rx_term = $rx_term;
   }

   public function setConfigBoard( $cfg_board )
   {
      $this->ConfigBoard = $cfg_board;
   }

   public function set_forum_options( $forum_opts )
   {
      $this->forum_opts = $forum_opts;
   }

   public function set_threadpost_view( $forum_flags )
   {
      if ( ($forum_flags & FORUMFLAGS_POSTVIEW_FLAT) == FORUMFLAG_POSTVIEW_FLAT_NEW_FIRST )
         $this->flat_view = 1;
      elseif ( ($forum_flags & FORUMFLAGS_POSTVIEW_FLAT) == FORUMFLAG_POSTVIEW_FLAT_OLD_FIRST )
         $this->flat_view = -1;
      else
         $this->flat_view = 0;
   }

   public function show_found_rows( $rows )
   {
      if ( $rows >=0 )
         $this->found_rows = $rows;
   }

   public function print_moderation_note( $width )
   {
      if ( $this->is_moderator)
         echo "<table width='$width'><tr><td align=right><font color=red>"
            . T_("Moderating") . "</font></td></tr></table>\n";
   }

   // param table_id: begining by an uppercase letter because used as sub-ID name (CSS);
   //                 values: 'Index', 'List', 'Read', 'Search', 'Revision', 'Pending'
   // param ReqParam: optional object RequestParameters containing URL-parts to be included for paging
   // note: sets cur_depth=-1
   public function forum_start_table( $table_id, $ReqParam = null )
   {
      echo name_anchor('ftop'), "<table id='forum$table_id' class=Forum>\n";
      $this->make_link_array( $ReqParam );

      if ( $this->links & LINK_MASKS )
         $this->echo_links(NEWMODE_TOP);

      $this->print_headline();

      $this->cur_depth = -1;
   }

   public function print_headline( $headline=NULL )
   {
      if ( is_null($headline) )
         $headline = $this->headline;

      echo "<tr class=Caption>";
      $first = true;
      foreach ( $headline as $name => $attbs )
      {
         if ( $first && $this->found_rows >= 0 )
         {
            $fmt_entries = ($this->found_rows == 1 ) ? T_('%s entry') : T_('%s entries');
            $name .= SMALL_SPACING . span('NaviInfo', sprintf( $fmt_entries, $this->found_rows ), '(%s)');
            $first = false;
         }
         echo "<td $attbs>$name</td>";
      }
      echo "</tr>\n";
   }//print_headline

   public function forum_end_table( $bottom_bar=true )
   {
      if ( $bottom_bar && $this->links & LINK_MASKS )
         $this->echo_links(NEWMODE_BOTTOM);
      echo "</table>\n", name_anchor('fbottom');
   }

   // return arr( headline1, headtitle2 )
   public function build_threadpostview_headlines( $base_url, $show_overview )
   {
      // build view-links "(tree | new/old first)"
      $arr = array();
      if ( $this->flat_view == 0 ) // tree-view
      {
         $arr[] = array( $base_url .URI_AMP.'view=fo', T_('Old First#thread_view') );
         $arr[] = array( $base_url .URI_AMP.'view=fn', T_('New First#thread_view') );
      }
      else
      {
         $arr[] = array( $base_url .URI_AMP.'view=t', T_('Tree View#thread_view') );
         if ( $this->flat_view > 0 ) // flat new-first
            $arr[] = array( $base_url .URI_AMP.'view=fo', T_('Old First#thread_view') );
         else //if ( $this->flat_view < 0 ) // flat old-first
            $arr[] = array( $base_url .URI_AMP.'view=fn', T_('New First#thread_view') );
      }

      $head_fmt = '%s%s<span class="HeaderToggle">( <a href="%s">%s</a> ) ( <a href="%s">%s</a> | <a href="%s">%s</a> )</span>';
      if ( $show_overview )
      {

         $headtitle1 = sprintf( $head_fmt,
            T_('Reading thread overview'),
            SMALL_SPACING,
            $base_url .URI_AMP.'toggleflag='.FORUMFLAG_POSTVIEW_OVERVIEW,
            T_('Hide overview#threadoverview'),
            $arr[0][0], $arr[0][1],
            $arr[1][0], $arr[1][1] );
         $headline1 = array(
            $headtitle1 => "colspan={$this->cols}"
         );

         $headtitle2 = T_('Reading thread posts');
      }
      else
      {
         $headline1 = null;

         $headtitle2 = sprintf( $head_fmt,
            T_('Reading thread posts'),
            SMALL_SPACING,
            $base_url .URI_AMP.'toggleflag='.FORUMFLAG_POSTVIEW_OVERVIEW,
            T_('Show overview#threadoverview'),
            $arr[0][0], $arr[0][1],
            $arr[1][0], $arr[1][1] );
      }

      return array( $headline1, $headtitle2 );
   }//build_threadpostview_headlines

   // param ReqParam: optional object RequestParameters containing URL-parts to be included for paging
   public function make_link_array( $ReqParam = null )
   {
      global $NOW;
      $links = $this->links;
      if ( !( $links & LINK_MASKS ) )
         return;
      $fid = $this->forum_id;
      $tid = $this->thread_id;

      if ( $links & LINK_FORUMS )
         $this->link_array_left[T_('Forums')] = "index.php";

      if ( $links & LINK_THREADS )
         $this->link_array_left[T_('Threads')] = "list.php?forum=$fid";

      if ( $links & LINK_BACK_TO_THREAD )
      {
         $this->link_array_left[T_('Back to thread')] = "read.php?forum=$fid"
               .URI_AMP."thread=$tid"
               .( ( $this->back_post_id ) ? '#'.$this->back_post_id : '' );
      }

      if ( $links & LINK_NEW_TOPIC )
         $this->link_array_left[T_('New Topic')] = "read.php?forum=$fid";

      if ( $links & LINK_REFRESH )
      {
         if ( $links & LINKPAGE_READ )
         {
            $url = make_url( 'read.php', array( 'forum' => $fid, 'thread' => $tid ));
            if ( isset($_REQUEST['raw']) )
               $url .= URI_AMP . 'raw='.$_REQUEST['raw'];
         }
         elseif ( $links & LINKPAGE_LIST )
            $url = make_url( 'list.php', array( 'forum' => $fid, 'maxrows' => $this->max_rows ));
         elseif ( $links & LINKPAGE_SEARCH )
            $url = '';
         else
            $url = "index.php";
         if ( $url )
            $this->link_array_left[T_('Refresh')] = $url;
      }

      if ( $links & LINK_SEARCH )
         $this->link_array_left[T_('Search')] = "search.php";

      if ( ($links & LINKPAGE_LIST) && ($links & LINK_MARK_READ) )
      {
         $this->link_array_left[T_('Mark All Read')] = make_url( 'list.php',
               array( 'forum' => $fid, 'maxrows' => $this->max_rows, 'markread' => $NOW ));
      }

      if ( $links & LINK_TOGGLE_MODERATOR )
      {
         // preserve all page-args on moderator switch
         $get = array_merge( $_GET, $_POST);
         unset($get['modact']);
         unset($get['modpid']);
         $get['moderator'] = ($this->is_moderator) ? 'n' : 'y';
         if ( $links & LINKPAGE_READ )
            $url = make_url( 'read.php', $get );
         elseif ( $links & LINKPAGE_LIST )
            $url = make_url( 'list.php', $get );
         elseif ( $links & LINKPAGE_SEARCH )
            $url = make_url( 'search.php', $get );
         else
            $url = make_url( 'index.php', $get );
         $this->link_array_right[T_("Toggle forum moderator")] = $url;
      }

      $navi = array( 'maxrows' => $this->max_rows );
      if ( !is_null($ReqParam) && ($links & (LINKPAGE_SEARCH|LINK_PREV_PAGE|LINK_NEXT_PAGE)) )
         $navi = array_merge( $navi, $ReqParam->get_entries() );

      if ( $links & LINK_PREV_PAGE )
      {
         $navi['offset'] = $this->offset - $this->max_rows;
         if ( $links & LINKPAGE_SEARCH )
            $href = 'search.php?';
         else
            $href = "list.php?forum=$fid";
         $this->link_array_right[T_("Prev Page")] =
            array( make_url( $href, $navi ), '', array( 'accesskey' => ACCKEY_ACT_PREV ) );
      }
      if ( $links & LINK_NEXT_PAGE )
      {
         $navi['offset'] = $this->offset + $this->max_rows;
         if ( $links & LINKPAGE_SEARCH )
            $href = 'search.php?';
         else
            $href = "list.php?forum=$fid";
         $this->link_array_right[T_("Next Page")] =
            array( make_url( $href, $navi ), '', array( 'accesskey' => ACCKEY_ACT_NEXT ) );
      }
   }//make_link_array

   public function echo_links( $new_mode )
   {
      if ( ($new_mode & (NEWMODE_TOP|NEWMODE_BOTTOM)) == NEWMODE_TOP )
         $id = 'T';
      elseif ( ($new_mode & (NEWMODE_TOP|NEWMODE_BOTTOM)) == NEWMODE_BOTTOM )
         $id = 'B';
      else
         error('invalid_args', "DisplayForum.echo_links.check.new_mode($new_mode)");

      $lcols = $this->cols; //1; $cols/2; $cols-1;
      $tmp = ( $lcols > 1 ? ' colspan='.$lcols : '' );
      echo "<tr class=Links$id><td$tmp><div class=TreeLinks>";

      $first = true;
      foreach ( $this->link_array_left as $name => $link )
      {
         if ( !$first )
            echo "&nbsp;|&nbsp;";
         else
            $first = false;
         if ( is_array($link) )
            echo anchor( $link[0], $name, $link[1], $link[2]);
         else
            echo anchor( $link, $name);
      }
      echo $this->get_new_string( $new_mode );

      $lcols = $this->cols - $lcols;
      $tmp = ( $lcols > 1 ? ' colspan='.$lcols : '' );
      if ( $lcols > 0 )
         echo "</div></td><td$tmp><div class=PageLinks>";
      else
         echo "</div><div class=PageLinks>";

      $first = true;
      foreach ( $this->link_array_right as $name => $link )
      {
         if ( !$first )
            echo "&nbsp;|&nbsp;";
         else
            $first = false;
         if ( is_array($link) )
            echo anchor( $link[0], $name, $link[1], $link[2]);
         else
            echo anchor( $link, $name);
      }

      echo "</div></td></tr>\n";
   }//echo_links

   /*!
    * \brief Increase global new counter, builds and returns current new-string.
    * param $mode bitmask of NEWMODE_TOP|BOTTOM ('new' for top/bottom-bar);
    *       NEWMODE_OVERVIEW, NEWMODE_NO_LINK
    */
   public function get_new_string( $mode=0 )
   {
      static $fmt_new = '<span class="NewFlag"><a name="%s%d"%s>%s</a></span>'; // anchor_prefix, new-idx, link-ref, new-text

      $new = '';
      $anchor_prefix = 'new';
      if ( $mode & (NEWMODE_TOP|NEWMODE_BOTTOM) ) // top/bottom-bar refer to 1st-NEW
      {
         $link = ($mode & NEWMODE_NO_LINK) ? '' : ' href="#new1"';
         if ( ($mode & NEWMODE_TOP) && $this->count_new_posts > 0 )
            $new = sprintf( $fmt_new, $anchor_prefix, 0, $link, T_('first new') );
         elseif ( ($mode & NEWMODE_BOTTOM) && $this->new_count > 0 )
            $new = sprintf( $fmt_new, $anchor_prefix, $this->new_count + 1, $link, T_('first new') );
      }
      else
      {
         if ( $mode & NEWMODE_OVERVIEW )
         {
            $anchor_prefix = 'treenew';
            $addnew = 0;
         }
         else
            $addnew = 1;

         $this->new_count++;
         $link = ($mode & NEWMODE_NO_LINK)
            ? '' : sprintf(' href="#new%d"', $this->new_count + $addnew );
         $new = sprintf( $fmt_new, $anchor_prefix, $this->new_count, $link, T_('new#forum') );
      }
      return $new;
   }//get_new_string

   // \param $drawmode one of DRAWPOST_NORMAL | PREVIEW | EDIT | REPLY
   // \note checking for thread-read-only flag must be done at caller-side
   public function forum_message_box( $drawmode, $post_id, $GoDiagrams=null, $ErrorMsg='', $Subject='', $Text='', $Flags=0 )
   {
      global $player_row;
      if ( ($player_row['AdminOptions'] & ADMOPT_FORUM_NO_POST) ) // user not allowed to post
         return;

      if ( !Forum::allow_posting($player_row, $this->forum_opts) )
         return;

      // reply-prefix
      if ( ($drawmode == DRAWPOST_NORMAL || $drawmode == DRAWPOST_REPLY)
            && strlen($Subject) > 0 && strcasecmp(substr($Subject,0,3), "re:") != 0 )
         $Subject = "RE: " . $Subject;

      $msg_form = 'messageform';
      $form = new Form( $msg_form, "read.php#preview", FORM_POST );

      global $player_row;
      if ( @$player_row['ID'] <= GUESTS_ID_MAX )
      {
         $form->add_row( array(
               'DESCRIPTION', span('EmphasizeWarn', T_('NOTE#guest')),
               'TEXT', span('EmphasizeWarn',
                        T_("Forum posts by the guest-user can be approved or rejected by the server admins.<br>\n" .
                           "If you want a private (non-public) answer, add your email and ask for private contact.")), ));
      }
      if ( $ErrorMsg )
      {
         $form->add_row( array(
               'DESCRIPTION', span('ErrorMsg bold', T_('Error#forum')),
               'TEXT', span('ErrorMsg', $ErrorMsg), ));
      }

      $form->add_row( array(
            'DESCRIPTION', T_('Subject'),
            'TEXTINPUT', 'Subject', 50, 80, $Subject,
            'HIDDEN', ($drawmode == DRAWPOST_EDIT ? 'edit' : 'parent'), $post_id,
            'HIDDEN', 'thread', $this->thread_id,
            'HIDDEN', 'forum', $this->forum_id ));
      $form->add_row( array(
            'TAB', 'TEXTAREA', 'Text', 70, 25, $Text ));

      if ( $post_id == 0 && ForumPost::allow_post_read_only() ) // only for new thread
      {
         $form->add_row( array(
            'TAB', 'CHECKBOX', 'ReadOnly', 1, T_('Read-only thread (only admin executives may reply)'),
               ($Flags & FPOST_FLAG_READ_ONLY), ));
      }

      $arr_dump_diagrams = array();
/*
      if ( ALLOW_GO_DIAGRAMS && is_javascript_enabled() && !is_null($GoDiagrams) )
      {
         $diagrams_str = GoDiagram::draw_editors($GoDiagrams);
         if ( !empty($diagrams_str) )
         {
            $form->add_row( array( 'OWNHTML', "<td colspan=2>$diagrams_str</td>" ));
            $arr_dump_diagrams = array( 'onClick' => "dump_all_data('$msg_form');" );
         }
      }
*/

      $form->add_row( array(
            'SUBMITBUTTONX', 'post', ' ' . T_('Post') . ' ',
                  array( 'accesskey' => ACCKEY_ACT_EXECUTE ) + $arr_dump_diagrams,
            'SUBMITBUTTONX', 'preview', ' ' . T_('Preview') . ' ',
                  array( 'accesskey' => ACCKEY_ACT_PREVIEW ) + $arr_dump_diagrams ));

      $form->echo_string(1);
   }//forum_message_box


   // The table structure of the list controlled by depth:
   // level 1: the header, body and footer TABLE of the list
   // level 2: the body of the list: one row per post managing its indent
   // level 3: the post cell TABLE
   public function change_depth( $new_depth )
   {
      if ( $new_depth < 1 && $this->cur_depth < 1 )
         return;

      if ( $this->cur_depth >= 1 ) //this means that a cell table is already opened
         echo "</table></td></tr>\n";

      if ( $new_depth < 1 ) //this means close it
      {
         echo "</table></td></tr>\n";
         $this->cur_depth = -1;
         return;
      }

      if ( $this->cur_depth < 1 ) //this means opened it
         echo "<tr><td colspan={$this->cols}><table width=\"100%\" border=0 cellspacing=0 cellpadding=0>";

      // then build the indenting row
      $this->cur_depth = $new_depth;
      echo "<tr>";
      $indent = "<td class=Indent>&nbsp;</td>";
      $i = min( $this->cur_depth, FORUM_MAX_INDENT);
      $c = FORUM_MAX_INDENT+1 - $i;
      switch ( (int)$i )
      {
         case 1:
            break;

         case 2:
            echo "$indent";
            break;

         case 3:
            echo "<td class=Indent2></td>$indent";
            break;

         default:
            echo "<td class=Indent2 colspan=".($i-2)."></td>$indent";
            break;
      }

      // finally, open the cell table
      echo "<td colspan=$c><table width=\"100%\" border=0 cellspacing=0 cellpadding=3>";
   }//change_depth

   /*! \brief Inits and returns navigational images for draw_post if not initialized yet. */
   private function init_navi_images()
   {
      if ( !is_array($this->navi_img) )
      {
         global $base_path;
         $this->navi_img = array(
            'top'          => image( $base_path.'images/f_top.png',
                                     T_('Top'), null ),
            'prev_parent'  => image( $base_path.'images/f_prevparent.png',
                                     T_('Previous parent'), null ),
            'prev_answer'  => image( $base_path.'images/f_prevanswer.png',
                                     T_('Previous answer'), null ),
            'next_answer'  => image( $base_path.'images/f_nextanswer.png',
                                     T_('Next answer'), null ),
            'next_parent'  => image( $base_path.'images/f_nextparent.png',
                                     T_('Next parent'), null ),
            'bottom'       => image( $base_path.'images/f_bottom.png',
                                     T_('Bottom'), null ),
            'first_answer' => image( $base_path.'images/f_firstanswer.png',
                                     T_('First answer'), null ),
         );
      }
      return $this->navi_img;
   }//init_navi_images


   /*!
    * \brief Draws various views of post controlled by $drawmode.
    * \param $drawmode DRAWPOST_..., MASK_DRAWPOST_...
    * \param $post: ForumPost-object to draw
    */
   public function draw_post( $drawmode, $post, $is_my_post, $GoDiagrams=null )
   {
      global $NOW, $player_row;

      // post-vars needed:
      //    id, forum_id, thread_id, parent_id, subject, text, author(id,name,handle,rating),
      //    pending_approval, last_edited, last_read
      // post-vars only needed for forum-search: forum_name, score; this->show_score

      $pid = $post->id;
      $thread_url = $post->build_url_post(''); //post_url ended by #$pid
      $term_url = ( $this->rx_term != '' ) ? URI_AMP."xterm=".urlencode($this->rx_term) : '';
      $user_may_post = !($player_row['AdminOptions'] & ADMOPT_FORUM_NO_POST); // use allowed to post?

      $cols = 2; //one for the subject header, one for the possible approved/hidden state

      // highlight terms in Subject/Text
      $sbj = make_html_safe( $post->subject, SUBJECT_HTML, $this->rx_term );
      $txt = make_html_safe( $post->text, true, $this->rx_term );
//      if ( ALLOW_GO_DIAGRAMS && is_javascript_enabled() && !is_null($GoDiagrams) )
//         $txt = GoDiagram::replace_goban_tags_with_boards($txt, $GoDiagrams);
      $txt = MarkupHandlerGoban::replace_igoban_tags( $txt );
      if ( strlen($txt) == 0 ) $txt = '&nbsp;';

      $sbj_readonly = ( $post->is_thread_post() ) ? $post->format_flags() : '';

      // CSS-class for post header
      $drawmode_type = ($drawmode & MASK_DRAWPOST_MODES);
      if ( $drawmode == DRAWPOST_NORMAL ) // most frequent case, no extras
         $class = 'Normal';
      elseif ( $drawmode_type == DRAWPOST_SEARCH )
         $class = 'SearchResult';
      elseif ( $drawmode & MASK_DRAWPOST_HIDDEN )
         $class = 'Hidden';
      elseif ( $drawmode_type == DRAWPOST_PREVIEW )
         $class = 'Preview';
      elseif ( $drawmode_type == DRAWPOST_EDIT )
         $class = 'Edit';
      elseif ( $drawmode_type == DRAWPOST_REPLY )
         $class = 'Reply';
      else //if drawmode_type 0 or DRAWPOST_NORMAL
      {
         $drawmode_type = DRAWPOST_NORMAL;
         $class = 'Normal';
      }
      $hdrclass = 'PostHead'.$class;

      // post header
      if ( $drawmode_type == DRAWPOST_PREVIEW )
      {
         $hdrrows = 2;
         $hdrcols = $cols;

         echo "\n<tr class=\"$hdrclass Subject\"><td class=Subject colspan=$hdrcols>"
            ,"<a name=\"preview\" class=\"PostSubject\">$sbj</a> $sbj_readonly</td></tr> "
            ,"\n<tr class=\"$hdrclass Author\"><td class=Author colspan=$hdrcols>"
            ,T_('by'),' ' ,user_reference( REF_LINK, 1, '', $player_row)
            , ', ', echo_rating($player_row['Rating2'], /*show%*/false, $player_row['ID'], /*engl*/false, /*short*/true)
            , ' ', SMALL_SPACING, date(DATE_FMT, $NOW)
            ,"</td></tr>";
      }
      else
      {
         if ( $drawmode & MASK_DRAWPOST_HIDDEN )
         {
            $hdrcols = $cols-1; //because of the rowspan=$hdrrows in the "hidden"-column
            $newstr = ''; // no NEW for hidden posts
         }
         else
         {
            $hdrcols = $cols;
            $newstr = ($post->is_read) ? '' : $this->get_new_string();
         }
         $subject_modstr = ( $drawmode & MASK_DRAWPOST_NO_BODY )
            ? '[* '.T_('subject moderated').' *]' : $sbj;

         if ( $drawmode_type == DRAWPOST_SEARCH )
         {
            $hdrrows = 3;

            // [header-row] search-found
            echo "\n<tr class=\"$hdrclass FoundForum\"><td class=FoundForum colspan=$hdrcols>";
            echo '<span class=FoundForum>' ,T_('found in forum')
               ,' <a href="list.php?forum=', $post->forum_id, '">', $post->forum_name, "</a></span>\n";
            if ( $this->show_score )
               echo ' <span class=FoundScore>' ,T_('with')
                  ,' <span>' ,T_('Score') ,' ' ,$post->score ,"</span></span>\n";
         }
         else
         {
            $hdrrows = 2;

            // [header-row] subject
            echo "\n<tr class=\"$hdrclass Subject\"><td class=Subject colspan=$hdrcols>";

            //from revision_history or because, when edited, the link will be obsolete
            if ( $drawmode_type == DRAWPOST_EDIT || $post->thread_no_link )
               echo "<a name=\"$pid\" class=\"PostSubject\">$subject_modstr</a> $sbj_readonly";
            else
               echo "<a name=\"$pid\" class=\"PostSubject\" href=\"", $thread_url, $term_url,
                  "#$pid\">$subject_modstr</a>", $sbj_readonly, $newstr;
         }

         // first [header-row] with different content (adding hidden-state)
         if ( $hdrcols != $cols )
         {
            echo "</td>\n <td rowspan=$hdrrows class=PostStatus><span class=\"Hidden\">";
            echo ( $post->is_pending_approval() ? T_('Awaiting<br>approval') : T_('Hidden') );
            if ( $drawmode & MASK_DRAWPOST_NO_BODY )
               echo sprintf( ' (%s)', T_('Content moderated'));
            echo '</span>';
         }
         echo '</td></tr>';

         if ( $drawmode_type == DRAWPOST_SEARCH )
         {
            // [header-row] subject
            echo "\n<tr class=\"$hdrclass Subject\"><td class=Subject colspan=$hdrcols>";
            echo '<a class=PostSubject href="', $thread_url, $term_url, "#$pid\">$subject_modstr</a>";
            echo "</td></tr>";
         }

         // [header-row] post-info: "author, created (edited)  (No. X)"
         echo "\n<tr class=\"$hdrclass Author\"><td class=Author colspan=$hdrcols>";

         $author_rating_str = ( $post->author->hasRating(false) )
            ? ', ' . echo_rating($post->author->Rating, /*show%*/false, $post->author->ID, /*engl*/false, /*short*/true)
            : '';
         echo T_('by'), ' ',
              echo_image_admin( $post->author->AdminLevel ),
              ' ', $post->author->user_reference(), $author_rating_str, SMALL_SPACING,
              date(DATE_FMT, $post->created);

         if ( !($drawmode & MASK_DRAWPOST_NO_BODY) )
            echo $this->get_post_edited_string( $post );

         if ( $drawmode_type != DRAWPOST_SEARCH && !($drawmode & MASK_DRAWPOST_NO_NUM) )
            echo SMALL_SPACING, sprintf( '(%s %s)', T_('No.#num'), $post->creation_order );

         echo "</td></tr>";
      }

      // post body
      if ( !($drawmode & MASK_DRAWPOST_NO_BODY) )
         echo "\n<tr class=PostBody><td colspan=$cols>$txt</td></tr>";


      // post bottom line (footer)
      if ( $drawmode_type == DRAWPOST_NORMAL || $drawmode_type == DRAWPOST_REPLY )
      {
         $flat = ( $this->flat_view != 0 );
         echo "\n<tr class=PostButtons><td colspan=$cols>";

         $imgarr = $this->init_navi_images();
         $prev_parent = ( is_null($post->prev_parent_post) )
            ? ''
            : anchor( '#'.$post->prev_parent_post->id, $imgarr['prev_parent'] ) . '&nbsp;';
         $next_parent = ( is_null($post->next_parent_post) )
            ? ''
            : anchor( '#'.$post->next_parent_post->id, $imgarr['next_parent'] ) . '&nbsp;';
         $prev_answer = ( is_null($post->prev_post) || $flat )
            ? ''
            : anchor( '#'.$post->prev_post->id, $imgarr['prev_answer'] ) . '&nbsp;';
         $next_answer = ( is_null($post->next_post) || $flat )
            ? ''
            : anchor( '#'.$post->next_post->id, $imgarr['next_answer'] ) . '&nbsp;';
         $first_answer = ( is_null($post->first_child_post) )
            ? ''
            : anchor( '#'.$post->first_child_post->id, $imgarr['first_answer'] ) . '&nbsp;';

         // BEGIN Navi (top/prev-parent/prev-answer)
         echo anchor( '#ftop', $imgarr['top'] ),
            '&nbsp;',
            $prev_parent,
            $prev_answer,
            '&nbsp;';

         if ( $user_may_post && Forum::allow_posting($player_row, $this->forum_opts) )
         {
            if ( !is_null($post->thread_post) && $post->thread_post->allow_post_reply() && $drawmode_type != DRAWPOST_REPLY )
            {
               // reply link
               echo '<a href="', $thread_url,URI_AMP,"reply=$pid#$pid\">[ ", T_('reply#forum'), " ]</a>&nbsp;&nbsp;";
               if ( ALLOW_QUOTING )
                  echo '<a href="', $thread_url,URI_AMP,"quote=1",URI_AMP,"reply=$pid#$pid\">[ ",
                     T_('quote#forum'), " ]</a>&nbsp;&nbsp;";
            }

            // edit link
            if ( $is_my_post )
               echo '<a class=Highlight href="', $thread_url,URI_AMP,"edit=$pid#$pid\">[ ",
                  T_('edit'), " ]</a>&nbsp;&nbsp;";
         }

         // END Navi (next-answer/next-parent/bottom)
         echo $next_answer,
            $next_parent,
            anchor( '#fbottom', $imgarr['bottom'] ),
            '&nbsp;',
            $first_answer,
            '&nbsp;';

         // for moderator: hide/show/approve/reject-link
         if ( $this->is_moderator )
         {
            $modurl_fmt = '<a class=Highlight href="'.$thread_url
               . URI_AMP."modpid=$pid".URI_AMP."modact=%s#$pid\">[ %s ]</a>";
            if ( $post->is_pending_approval() )
            {
               echo sprintf( $modurl_fmt, 'approve',  T_('Approve') ),
                  MED_SPACING,
                  sprintf( $modurl_fmt, 'reject',  T_('Reject') );
            }
            else
            {
               if ( $post->is_approved() )
                  echo sprintf( $modurl_fmt, 'hide',  T_('hide#forum') );
               else
                  echo sprintf( $modurl_fmt, 'show',  T_('show#forum') );
            }
         }
         echo "</td></tr>\n";
      }//post-footer
   }//draw_post

   /*! \brief Draw tree-overview for this thread. */
   public function draw_overview( $fthread )
   {
      global $base_path, $player_row;
      $this->new_count = 0;
      $this->change_depth( 1 );

      echo "\n<tr class=TreePostNormal><td><table class=ForumTreeOverview>",
         "\n<tr class=\"TreePostNormal Header\">",
         sprintf( '<th>%s</th><th>%s</th><th>%s'.SMALL_SPACING.'(%s)</th></tr>',
            T_('Subject'), T_('Author'), T_('Last changed'), T_('No.#num') );

      // draw for post: subject, author, date
      $c=2;
      foreach ( $fthread->posts as $pid => $post )
      {
         $hidden = !$post->is_approved();
         $is_my_post = $post->is_author($this->user_id);
         $show_hidden_post = ( $is_my_post || $this->is_moderator );
         if ( $hidden && !$post->is_thread_post() && !$show_hidden_post )
            continue;

         $subj_part = cut_str( $post->subject, 40 );
         $sbj = make_html_safe( $subj_part, SUBJECT_HTML, $this->rx_term );
         $newstr = ( !$hidden && !$post->is_read ) ? $this->get_new_string(NEWMODE_OVERVIEW) : '';
         $modstr = ( $hidden && ( $post->is_thread_post() || $this->is_moderator || $is_my_post ) )
            ? MED_SPACING . span('Moderated', ForumPost::get_approved_text( $post->approved ), '(%s)')
            : '';

         $c = 3 - $c;
         $mypostclass = ($is_my_post) ? ' class=MyPost' : '';
         $mypost_fmt = ($is_my_post) ? '<span class="MyPost">%s</span>' : '%s';
         $depth = ( $this->flat_view ) ? 1 : $post->depth;
         echo "\n<tr class=\"TreePostNormal Row{$c}". ($is_my_post ? ' MyPost' : '') ."\">",
            "<td$mypostclass>",
            str_repeat( '&nbsp;', 3*($depth - 1) ),
            sprintf( $mypost_fmt, anchor( '#'.$post->id, $sbj, '', 'class=PostSubject' ) ),
            $newstr,
            $modstr,
            "</td><td>",
            span('PostUser', $post->author->user_reference()),
            "</td><td>",
            sprintf( '<span class=PostDate>%s'.SMALL_SPACING.'(%s)</span>',
               date( DATE_FMT, max($post->created, $post->last_edited) ), $post->creation_order ),
            '</td></tr>';
      }

      echo "\n</table></td></tr>\n";

      $this->new_count = 0;
      $this->change_depth( -1 );
   }//draw_overview

   public function get_post_edited_string( $post )
   {
      if ( $post->last_edited > 0 )
      {
         $result = SMALL_SPACING . sprintf( '(<a href="%s">%s</a> %s)',
               $post->build_url_post( '', 'revision_history='.$post->id ),
               T_('edited'),
               date( DATE_FMT, $post->last_edited ) );
      }
      else
         $result = '';
      return $result;
   }

} // end of 'DisplayForum'




 /*!
  * \class Forum
  *
  * \brief Class to handle forum
  */
class Forum
{
   /*! \brief Forums.ID : id */
   public $id;
   /*! \brief Forums.Name : str */
   public $name;
   /*! \brief Forums.Description : str */
   public $description;
   /*! \brief Forums.LastPost : id (=Posts.ID) */
   public $last_post_id;
   /*! \brief Forums.Updated (change-date for forum-thread-read) */
   private $updated;
   /*! \brief Forums.ThreadsInForum : int */
   public $count_threads;
   /*! \brief Forums.PostsInForum : int */
   public $count_posts;
   /*! \brief Forums.SortOrder : int */
   private $sort_order;
   /*! \brief Forums.Options : int, bit-values see FORUMOPT_MODERATED, etc. */
   public $options;

   // non-db vars

   /*! \brief partly filled ForumPost-object for last_post_id [default=null] */
   public $last_post = null;
   /*! \brief array of ForumPost-objects [default=null] */
   public $threads = null;
   /*! \brief true, if there are more threads to page-navigate. */
   private $navi_more_threads;
   /*! \brief true if forum has new posts. */
   public $has_new_posts = false;


   /*! \brief Constructs Forum with specified args. */
   private function __construct( $id=0, $name='', $description='', $last_post_id=0, $updated=0,
         $count_threads=0, $count_posts=0, $sort_order=0, $options=0 )
   {
      $this->id = $id;
      $this->name = $name;
      $this->description = $description;
      $this->last_post_id = $last_post_id;
      $this->updated = $updated;
      $this->count_threads = $count_threads;
      $this->count_posts = $count_posts;
      $this->sort_order = $sort_order;
      $this->options = $options;
   }

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   public function to_string()
   {
      return "Forum(id={$this->id}): "
         . "name=[{$this->name}]"
         . ", description=[{$this->description}]"
         . ", last_post_id=[{$this->last_post_id}]"
         . ", updated=[{$this->updated}]"
         . ", #threads=[{$this->count_threads}]"
         . ", #posts=[{$this->count_posts}]"
         . ", sort_order=[{$this->sort_order}]"
         . ", options=[{$this->options}]"
         . ', last_post={' . ( is_null($this->last_post) ? '' : $this->last_post->to_string() ) . '}'
         . ", has_new_posts=[{$this->has_new_posts}]";
   }

   /*!
    * \brief Returns Forums.Options in text-form.
    * \param $forum_opts ForumOptions-object for specific user
    */
   public function build_options_text( $forum_opts, $formatted=true )
   {
      $opt_prefix = ' &nbsp;&nbsp;[';
      $str = '';

      if ( $this->options & FORUMOPT_MODERATED )
         $str .= $opt_prefix . T_('Moderated') . ']';
      if ( $this->options & FORUMOPT_READ_ONLY )
         $str .= $opt_prefix . T_('Read-Only') . ']';
      if ( $forum_opts->is_executive_admin() && ($this->options & FORUMOPTS_GROUPS_HIDDEN) )
         $str .= $opt_prefix . T_('Hidden#forum') . ']';

      if ( $formatted && $str )
         $str = SMALL_SPACING . span('ForumOpts', $str);
      return $str;
   }

   /*!
    * \brief Loads threads for current forum into this object (this->threads = ForumPost[]).
    * \return count of loaded rows
    */
   public function load_threads( $user_id, $is_moderator, $show_rows, $offset=0 )
   {
      if ( !is_numeric($user_id) )
         error('invalid_user', "Forum.load_threads($user_id)");
      if ( !is_numeric($show_rows) || !is_numeric($offset) )
         error('invalid_args', "Forum.load_threads($show_rows,$offset)");

      $forum_id = $this->id;
      if ( !is_numeric($forum_id) )
         error('unknown_forum', "Forum.load_threads($forum_id)");

      $min_date = ForumRead::get_min_date();
      $qsql = ForumPost::build_query_sql();
      $qsql->add_part( SQLP_FIELDS,
         'LPAuthor.ID AS LPAuthor_ID',
            'LPAuthor.Name AS LPAuthor_Name',
            'LPAuthor.Handle AS LPAuthor_Handle',
         'IF(ISNULL(FR.User_ID),0,UNIX_TIMESTAMP(FR.Time)) AS FR_X_Lastread' );
      $qsql->add_part( SQLP_FROM,
         'LEFT JOIN Posts AS LP ON LP.ID=P.LastPost',  // LastPost
         'LEFT JOIN Players AS LPAuthor ON LPAuthor.ID=LP.User_ID', // LastPost-Author
         "LEFT JOIN Forumreads AS FR ON FR.User_ID='$user_id' AND FR.Forum_ID=P.Forum_ID "
            . 'AND FR.Thread_ID=P.Thread_ID' );
      $qsql->add_part( SQLP_WHERE,
         "P.Forum_ID=$forum_id",
         'P.Parent_ID=0', 'P.Thread_ID=P.ID' ); //=thread (better query with thread=id for Forumreads)
      if ( !$is_moderator )
         $qsql->add_part( SQLP_WHERE, 'P.PostsInThread>0' );
      $qsql->add_part( SQLP_ORDER, 'P.LastChanged DESC' );
      $qsql->add_part( SQLP_LIMIT, sprintf( '%d,%d', $offset, $show_rows + 1) );

      $query = $qsql->get_select();
      $result = db_query( "Forum.load_threads($user_id,$is_moderator,$show_rows,$offset)", $query );
      $rows = mysql_num_rows($result);

      $this->navi_more_threads = false;
      $thlist = array();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $thread = ForumPost::new_from_row( $row ); // Post
         $thread->last_post = new ForumPost( $thread->last_post_id, $this->id, $thread->thread_id,
               User::newForumUser( $row['LPAuthor_ID'], $row['LPAuthor_Name'], $row['LPAuthor_Handle'] ));
         $thread->has_new_posts =
            ( $thread->last_changed >= $min_date ) && ( $thread->last_changed > $row['FR_X_Lastread'] );

         $thlist[] = $thread;
      }
      mysql_free_result($result);

      if ( $rows > $show_rows )
      {
         array_pop( $thlist ); // remove last entry
         $this->navi_more_threads = true;
         $rows--;
      }

      $this->threads = $thlist;
      return $rows;
   }//load_threads

   /*! \brief Returns true, if there are new posts in loaded thread-list. */
   public function has_new_posts_in_threads()
   {
      if ( !is_null($this->threads) )
      {
         foreach ( $this->threads as $thread ) // $thread = ForumPost-obj
         {
            if ( $thread->has_new_posts )
               return true;
         }
      }

      return false;
   }

   /*! \brief Use after call of load_threads() to check, if there are more threads to load. */
   public function has_more_threads()
   {
      return $this->navi_more_threads;
   }

   /*!
    * \brief Fix forum consistency for fields (LastPost, PostsInForum, ThreadsInForum).
    * \param $debug if true only echoes sql-statements
    * \note see section 'Calculated database fields' in 'specs/forums.txt'
    * \return number of updates
    */
   public function fix_forum( $debug, $debug_format="%s\n" )
   {
      $upd_arr = array();
      $fid = $this->id;

      // read fix for Forums.LastPost
      $row = mysql_single_fetch( "Forum.fix_forum.read.LastPost($fid)",
            "SELECT ID AS X_LastPost FROM Posts " .
            "WHERE Forum_ID='$fid' AND Thread_ID>0 AND Approved='Y' AND PosIndex>'' " .
            "ORDER BY Time DESC LIMIT 1" );
      if ( $row && $row['X_LastPost'] != $this->last_post_id )
         $upd_arr[] = 'LastPost=' . $row['X_LastPost'];
      elseif ( !$row && $this->last_post_id != 0 )
         $upd_arr[] = 'LastPost=0';

      // read fix for Forums.PostsInForum
      $row = mysql_single_fetch( "Forum.fix_forum.read.PostsInForum($fid)",
            "SELECT COUNT(*) AS X_Count FROM Posts " .
            "WHERE Forum_ID='$fid' AND Thread_ID>0 AND Approved='Y' AND PosIndex>''" );
      if ( $row && $row['X_Count'] != $this->count_posts )
         $upd_arr[] = 'PostsInForum=' . $row['X_Count'];

      // read fix for Forums.ThreadsInForum
      // note: delivers correct value only if Posts.PostsInThread is correct, so fix Posts first
      $row = mysql_single_fetch( "Forum.fix_forum.read.ThreadsInForum($fid)",
            "SELECT COUNT(*) AS X_Count FROM Posts ".
            "WHERE Forum_ID='$fid' AND Parent_ID=0 AND PostsInThread>0" );
      if ( $row && $row['X_Count'] != $this->count_threads )
         $upd_arr[] = 'ThreadsInForum=' . $row['X_Count'];

      // fix Forums
      if ( count($upd_arr) > 0 )
      {
         $query = "UPDATE Forums SET " . implode(', ', $upd_arr) . " WHERE ID='$fid' LIMIT 1";
         echo sprintf( $debug_format, $query );
         if ( !$debug )
            db_query( "Forum.fix_forum.update($fid)", $query );

         self::delete_cache_forum( "Forum.fix_forum.update($fid)", $fid );
      }

      return (count($upd_arr) > 0) ? 1 : 0;
   }//fix_forum


   // ---------- Static Class functions ----------------------------

   public static function is_admin()
   {
      global $player_row;
      return ( @$player_row['admin_level'] & (ADMIN_FORUM|ADMIN_DEVELOPER) );
   }

   /*! \brief Returns true if "writing posts" is allowed for read-only forum for given user. */
   public static function allow_posting( $user_row, $forum_opts )
   {
      if ( is_null($forum_opts) )
         $forum_opts = FORUMOPT_READ_ONLY; // assuming read-only to be safe
      return ( $forum_opts & FORUMOPT_READ_ONLY )
         ? ( (int)@$user_row['admin_level'] & (ADMIN_FORUM|ADMIN_DEVELOPER) )
         : true;
   }

   /*! \brief Returns db-fields to be used for query of Forum-object. */
   public static function build_query_sql()
   {
      // Forums: ID,Name,Description,LastPost,ThreadsInForum,PostsInForum,SortOrder,Options
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'Forums.*',
         'UNIX_TIMESTAMP(Forums.Updated) AS X_Updated' );
      $qsql->add_part( SQLP_FROM, 'Forums' );
      return $qsql;
   }

   /*! \brief Returns Forum-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $forum = new Forum(
            @$row['ID'],
            @$row['Name'],
            @$row['Description'],
            @$row['LastPost'],
            @$row['X_Updated'],
            @$row['ThreadsInForum'],
            @$row['PostsInForum'],
            @$row['SortOrder'],
            @$row['Options']
         );
      return $forum;
   }

   /*!
    * \brief Returns non-null Forum-object for specified forum-id.
    * Throws errors if forum cannot be found.
    */
   public static function load_forum( $id )
   {
      if ( !is_numeric($id) || $id <= 0 )
         error('unknown_forum', "Forum:load_forum($id)");

      $qsql = self::build_query_sql();
      $qsql->add_part( SQLP_WHERE, "ID='$id'" );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $query = $qsql->get_select();
      $row = mysql_single_fetch( "Forum:load_forum2($id)", $query );
      if ( !$row )
         error('unknown_forum', "Forum:load_forum3($id)");

      return self::new_from_row( $row );
   }

   // cached version of self::load_forum()
   public static function load_cache_forum( $id )
   {
      $dbgmsg = "Forum:load_cache_forum($id)";
      $key = "Forum.$id";

      $forum = DgsCache::fetch( $dbgmsg, CACHE_GRP_FORUM, $key );
      if ( is_null($forum) )
      {
         $forum = self::load_forum( $id );
         DgsCache::store( $dbgmsg, CACHE_GRP_FORUM, $key, $forum, SECS_PER_DAY );
      }

      return $forum;
   }

   public static function delete_cache_forum( $dbgmsg, $fid )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_FORUM, "Forum.$fid" );
   }

   /*!
    * \brief Returns array of Forum-objects for specified user-id (in ForumOptions);
    *        returns null if no feature found.
    * \param $forum_opts ForumOptions-object for given user-id
    * \note also sets Forum-field: has_new_posts
    * \note stores result for user in Forumreads-table
    */
   public static function load_forum_list( $forum_opts )
   {
      if ( !($forum_opts instanceof ForumOptions) )
         error('invalid_args', "Forum:load_forum_list.check.forum_opts($forum_opts)");
      $user_id = $forum_opts->uid;

      $qsql = self::build_query_sql();
      $qsql->add_part( SQLP_FIELDS,
         'LP.Thread_ID AS LP_Thread',
         'UNIX_TIMESTAMP(LP.Time) AS LP_Time',
         'LP.User_ID AS LP_User_ID',
         'PLP.Name AS LP_Name',
         'PLP.Handle AS LP_Handle',
         'IF(ISNULL(FR.User_ID),0,UNIX_TIMESTAMP(FR.Time)) AS FR_X_Lastread',
         'COALESCE(FR.HasNew,0) AS FR_HasNew' );
      $qsql->add_part( SQLP_FROM,
         'LEFT JOIN Posts AS LP ON Forums.LastPost=LP.ID',
         'LEFT JOIN Players AS PLP ON PLP.ID=LP.User_ID',
         "LEFT JOIN Forumreads AS FR ON FR.User_ID='$user_id' AND FR.Forum_ID=Forums.ID "
            . 'AND FR.Thread_ID=0' );
      $qsql->add_part( SQLP_ORDER, 'SortOrder' );

      $query = $qsql->get_select();
      $result = db_query( "Forum:load_forum_list($user_id)", $query );

      $fread = new ForumRead( $user_id );
      $flist = array();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $forum = self::new_from_row( $row );
         if ( !$forum_opts->is_visible_forum( $forum->options ) )
            continue;

         $fid = $forum->id;
         $post = new ForumPost( $forum->last_post_id, $fid, $row['LP_Thread'],
               User::newForumUser( $row['LP_User_ID'], $row['LP_Name'], $row['LP_Handle'] ));
         $post->created = $row['LP_Time'];
         $forum->last_post = $post;

         // update forum-read for NEW-handling
         if ( $forum->count_posts > 0 )
         {
            if ( $row['FR_X_Lastread'] <= 0 || $forum->updated > $row['FR_X_Lastread'] )
            {
               $forum->has_new_posts = ForumRead::has_new_posts_in_forums( $user_id, $fid );
               $fread->replace_row_forumread( "Forum:load_forum_list.forum_read.upd",
                  $fid, 0, $forum->updated, $forum->has_new_posts );
            }
            else
               $forum->has_new_posts = $row['FR_HasNew'];
         }

         $flist[] = $forum;
      }
      mysql_free_result($result);

      return $flist;
   }//load_forum_list

   /*!
    * \brief Returns array of partial Forum-objects (only those visible to a player)
    *        with [ id => name ] entries.
    * \param forum_opts is object ForumOptions($player_row), load all forum names if omitted
    */
   public static function load_cache_forum_names( $forum_opts )
   {
      $dbgmsg = "Forum:load_cache_forum_names";
      $key = "ForumNames";

      $arr_forum_names = DgsCache::fetch( $dbgmsg, CACHE_GRP_FORUM_NAMES, $key );
      if ( is_null($arr_forum_names) )
      {
         // build forum-array for filter: ( Name => Forum_ID )
         $db_result = db_query( 'Forum:load_cache_forum_names',
               'SELECT ID, Name, Options FROM Forums ORDER BY SortOrder' );

         $arr_forum_names = array();
         while ( $row = mysql_fetch_array($db_result) )
            $arr_forum_names[$row['ID']] = array( $row['Name'], $row['Options'] );
         mysql_free_result($db_result);

         DgsCache::store( $dbgmsg, CACHE_GRP_FORUM_NAMES, $key, $arr_forum_names, SECS_PER_DAY );
      }

      $forum_names = array();
      foreach ( $arr_forum_names as $fid => $arr )
      {
         list( $f_name, $f_opts ) = $arr;

         // can user view forum?
         if ( $forum_opts && !$forum_opts->is_visible_forum($f_opts) )
            continue;

         $forum_names[$fid] = $f_name;
      }

      return $forum_names;
   }//load_cache_forum_names

   public static function delete_cache_forum_names( $dbgmsg )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_FORUM_NAMES, "ForumNames" );
   }

   /*! \brief Returns array of Forum-objects with raw Forums-fields (for forum-fixes). */
   public static function load_fix_forum_list()
   {
      $qsql = self::build_query_sql();
      $qsql->add_part( SQLP_ORDER, 'ID' );

      $result = db_query( "Forum:load_fix_forum_list", $qsql->get_select() );
      $flist = array();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $forum = self::new_from_row( $row );
         $flist[] = $forum;
      }
      mysql_free_result($result);
      return $flist;
   }

} // end of 'Forum'



 /*!
  * \class ForumThread
  *
  * \brief Class to handle thread with list of thread-posts
  */
class ForumThread
{
   /*! \brief ForumRead-object to be used to mark posts as read. */
   private $forum_read;
   /*! \brief array of posts in this thread: [ post->id => ForumPost ]. */
   public $posts = array();

   /*! \brief Thread starter post [default=null]; null, if none found. */
   public $thread_post = null;
   /*! \brief Timestamp of last created post; 0=no post. */
   public $last_created = 0;

   public function __construct( $forum_read=null )
   {
      $this->forum_read = $forum_read;
   }

   /*! \brief Returns post with given post-id from this ForumThread object; or NULL if not found. */
   public function get_post( $pid )
   {
      return (isset($this->posts[$pid])) ? $this->posts[$pid] : NULL;
   }

   /*! \brief Returns thread-hits or 0 if thread has no posts. */
   public function get_thread_hits()
   {
      return (is_null($this->thread_post)) ? 0 : $this->thread_post->count_hits;
   }


   /*!
    * \brief Loads and adds posts (to posts-arr): query fields and FROM set,
    *        needs WHERE and ORDER in qsql2-arg QuerySQL; Needs fresh object-instance.
    * \return number of new posts
    * \note sets ForumPost.is_read
    * \note sets this.last_created
    * \note Needs attribute forum_read set in this object!
    */
   public function load_posts( $qsql2=null )
   {
      global $NOW;
      $qsql = ForumPost::build_query_sql();
      $qsql->merge( $qsql2 );

      $query = $qsql->get_select();
      $result = db_query( "ForumThread.load_posts", $query );

      $this->thread_post = null;
      $this->last_created = 0;
      $new_posts = 0;
      while ( $row = mysql_fetch_array( $result ) )
      {
         $post = ForumPost::new_from_row( $row );
         if ( $post->parent_id == 0 )
            $this->thread_post = $post;
         $post->set_post_is_read( $this->forum_read );
         if ( !$post->is_read )
            ++$new_posts;
         if ( $post->created > $this->last_created )
            $this->last_created = $post->created;

         $this->posts[$post->id] = $post;
      }
      mysql_free_result($result);

      foreach ( $this->posts as $pid => $post )
         $post->thread_post = $this->thread_post;

      return $new_posts;
   }//load_posts

   /*!
    * \brief Loads and adds posts (to posts-arr), current active post stored
    *        in thread_post; Needs fresh object-instance.
    */
   public function load_revision_history( $post_id )
   {
      global $NOW, $player_row;

      // select current active post
      $qsql = ForumPost::build_query_sql();
      $qsql->add_part( SQLP_WHERE, "P.ID='$post_id'" );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "ForumThread.load_revision_history.find_post($post_id)", $qsql->get_select() )
         or error('unknown_post', "ForumThread.load_revision_history.find_post2($post_id)");

      $post = ForumPost::new_from_row($row);
      $this->thread_post = $post;

      // check if allowed to view
      if ( !$post->is_approved() )
      {
         $my_id = $player_row['ID'];
         if ( !$post->is_author($my_id) && !Forum::is_admin() )
            error('forbidden_post', "ForumThread.load_revision_history.check_viewer($my_id,$post_id)");
      }

      // select all inactive history posts
      $qsql = ForumPost::build_query_sql();
      $qsql->add_part( SQLP_FIELDS,
         'GREATEST(P.Time,P.Lastedited) AS X_SortTime' ); // only sorting, so need no UNIX-time
      $qsql->add_part( SQLP_WHERE,
         "P.Parent_ID='$post_id'",
         "PosIndex=''" ); // '' == inactivated (edited)
      $qsql->add_part( SQLP_ORDER, 'X_SortTime DESC' );
      $result = db_query( "ForumThread.load_revision_history.find_edits($post_id)", $qsql->get_select() );

      while ( $row = mysql_fetch_array( $result ) )
      {
         $post = ForumPost::new_from_row($row);
         $post->thread_no_link = true; // display-opt
         $post->thread_post = $this->thread_post;
         $this->posts[$post->id] = $post;
      }
      mysql_free_result($result);
   }//load_revision_history

   /*! \brief Returns true, if one of loaded posts contains a go-diagram. */
   public function contains_goban()
   {
      foreach ( $this->posts as $post_id => $post )
      {
         if ( MarkupHandlerGoban::contains_goban($post->text) )
            return true;
      }
      return false;
   }

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   public function to_string()
   {
      $cnt = 0;
      $size = count($this->posts);
      $result = "ForumThread:\n";
      foreach ( $this->posts as $post_id => $post )
         $result .= sprintf( "[%d/%d]. pid=[%s]: {%s}\n", ++$cnt, $size, $post_id, $post->to_string() );
      return $result;
   }

   /*!
    * \brief Builds data-structure for navigation within post-list.
    * \param $set_in_posts if true, set navigation-links in ForumPosts in this object
    *
    * navtree[post_id] = map with keys: value=post-id or 0 (=no according node)
    *   prevP, nextP - prev/next-parent post
    *   prevA, nextA - prev/next-answer post for "parent"-thread
    *   child        - first-answer post
    *
    * \note order of post_id's in navtree is same as in posts of this ForumThread,
    *       but is expecting tree-sort by PosIndex
    */
   public function create_navigation_tree( $set_in_posts=true )
   {
      // find out flat-order
      $arr_order = array();
      foreach ( $this->posts as $post_id => $post )
         $arr_order[$post_id] = $post->created;
      asort($arr_order, SORT_NUMERIC);
      $idx = 0;
      foreach ( array_keys($arr_order) as $post_id )
         $arr_order[$post_id] = ++$idx;

      // build tree
      $navtree = array();
      $last_parent_posts = array(); // [ parent_id => last_post_id in parent-thread ]
      $parent_children = array(); // [ parent_id => [ post_id1, post_id2, ... ] ]
      foreach ( $this->posts as $post_id => $post )
      {
         $parent_id = $post->parent_id;
         $navmap = array();
         $navmap['prevP'] = $parent_id;
         $navmap['nextP'] = 0;
         $navmap['prevA'] = 0;
         $navmap['nextA'] = 0;
         $navmap['child'] = 0;

         $last_post_id = @$last_parent_posts[$parent_id];
         if ( $last_post_id )
         {
            $navmap['prevA'] = $last_post_id;
            $navtree[$last_post_id]['nextA'] = $post_id;
         }

         if ( $parent_id )
         {
            $last_parent_posts[$parent_id] = $post_id;
            $parent_children[$parent_id][] = $post_id;
         }
         $navtree[$post_id] = $navmap;

         $post->creation_order = $arr_order[$post_id]; // set flat-order (sort by creation-date)
      }

      foreach ( $parent_children as $parent_id => $children )
      {
         if ( isset($navtree[$parent_id]) )
         {
            $navtree[$parent_id]['child'] = $children[0]; // children non-empty
            foreach ( $children as $post_id )
               $navtree[$post_id]['nextP'] = $navtree[$parent_id]['nextA'];
         }
      }

      if ( $set_in_posts )
      {
         foreach ( $navtree as $post_id => $navmap )
         {
            $this->posts[$post_id]->set_navigation(
               ( $navmap['prevP'] ) ? $this->get_post($navmap['prevP']) : NULL,
               ( $navmap['nextP'] ) ? $this->get_post($navmap['nextP']) : NULL,
               ( $navmap['prevA'] ) ? $this->get_post($navmap['prevA']) : NULL,
               ( $navmap['nextA'] ) ? $this->get_post($navmap['nextA']) : NULL,
               ( $navmap['child'] ) ? $this->get_post($navmap['child']) : NULL
            );
         }
      }

      $this->navtree = $navtree;
   }//create_navigation_tree

} // end of 'ForumThread'



 /*!
  * \class ForumPost
  *
  * \brief Class to handle a thread-post.
  */
class ForumPost
{
   // IDs

   /*! \brief Posts.ID */
   public $id;
   /*! \brief Posts.Forum_ID */
   public $forum_id;
   /*! \brief Posts.Thread_ID */
   public $thread_id;

   // Thread Meta

   /*! \brief Posts.PostsInThread */
   public $count_posts;
   /*! \brief Posts.Hits */
   public $count_hits;
   /*! \brief Posts.LastPost */
   public $last_post_id;

   // Post Meta & Content

   /*! \brief non-null User-object ( .ID = Posts.User_ID ) */
   public $author;
   /*! \brief Posts.Subject */
   public $subject;
   /*! \brief Posts.Text */
   public $text;
   /*! \brief Posts.Flags */
   public $flags;

   /*! \brief Posts.Parent_ID */
   public $parent_id;
   /*! \brief Posts.AnswerNr */
   private $answer_num;
   /*! \brief Posts.Depth */
   public $depth;
   /*! \brief Posts.PosIndex : string */
   private $posindex;

   /*! \brief Posts.Approved : string (DB=Y|N|P); use is_approved/is_pending_approval-funcs. */
   public $approved;

   /*! \brief Posts.Time */
   public $created;
   /*! \brief Posts.Lastchanged, SQL X_Lastchanged; date of last-post in thread */
   public $last_changed;
   /*! \brief Posts.Lastedited */
   public $last_edited;

   /*! \brief Posts.crc32 */
   private $crc32;
   /*! \brief Posts.old_ID */
   private $old_id;

   // non-db vars

   /*! \brief ref to thread ForumPost (set by ForumThread.load_posts). */
   public $thread_post = null;
   /*! \brief ref to last created visible ForumPost (set by Forum.load_threads). */
   public $last_post = null;

   /*! \brief true, if for thread no link should be drawn (used in draw_post-func) [default=false] */
   public $thread_no_link = false;
   /*! \brief [int] order in thread-view (1..n); 0 = unset. */
   public $creation_order = 0;

   /*! \brief true if thread has new posts. */
   public $has_new_posts = false;
   /*! \brief true if post marked as read. */
   public $is_read = true;

   /*! \brief for forum-search: forum-name */
   public $forum_name;
   /*! \brief for forum-search: score */
   public $score = 0;

   // tree-navigation (set by ForumThread::create_navigation_tree-func), NULL=not-set

   public $prev_parent_post;
   public $next_parent_post;
   public $prev_post;
   public $next_post;
   public $first_child_post;


   /*! \brief Constructs ForumPost-object with specified arguments: dates are in UNIX-time. */
   public function __construct( $id=0, $forum_id=0, $thread_id=0, $author=null, $last_post_id=0,
         $count_posts=0, $count_hits=0, $subject='', $text='', $flags=0, $parent_id=0, $answer_num=0,
         $depth=0, $posindex='', $approved='Y',
         $created=0, $last_changed=0, $last_edited=0, $crc32=0, $old_id=0 )
   {
      $this->id = (int) $id;
      $this->forum_id = (int) $forum_id;
      $this->thread_id = (int) $thread_id;
      $this->count_posts = (int) $count_posts;
      $this->count_hits = (int) $count_hits;
      $this->last_post_id = (int) $last_post_id;
      $this->author = ( is_null($author) ? new User() : $author );
      $this->subject = $subject;
      $this->text = $text;
      $this->flags = (int) $flags;
      $this->parent_id = (int) $parent_id;
      $this->answer_num = (int) $answer_num;
      $this->depth = (int) $depth;
      $this->posindex = $posindex;
      $this->approved = $approved;
      $this->created = (int) $created;
      $this->last_changed = (int) $last_changed;
      $this->last_edited = (int) $last_edited;
      $this->crc32 = (int) $crc32;
      $this->old_id = (int) $old_id;
   }

   public function copy_post()
   {
      global $player_row;
      return new ForumPost( 0, $this->forum_id, $this->thread_id, User::new_from_row($player_row), 0, 0, 0,
         $this->subject, $this->text, 0, $this->parent_id );
   }

   /*! \brief Returns true, if post is thread-post. */
   public function is_thread_post()
   {
      return ( $this->id == $this->thread_id );
   }

   /*! \brief Returns true, if post is approved (Approved=Y). */
   public function is_approved()
   {
      return ( $this->approved === 'Y' );
   }

   /*! \brief Returns true, if post is pending-approval (Approved=P). */
   public function is_pending_approval()
   {
      return ( $this->approved === 'P' );
   }

   /*! \brief Returns true, if authors post matches given user-id. */
   public function is_author( $uid )
   {
      return ( $this->author->ID == $uid );
   }

   public function allow_post_reply()
   {
      return ( !($this->flags & FPOST_FLAG_READ_ONLY) || self::allow_post_read_only() );
   }

   public function format_flags( $flags=null )
   {
      if ( is_null($flags) )
         $flags = $this->flags;

      $out = array();
      if ( $flags & FPOST_FLAG_READ_ONLY )
         $out[] = span('ForumOpts', '[' . T_('Read-Only') . ']' );

      return (count($out)) ? MED_SPACING . implode(' ', $out) : '';
   }

   /*! \brief Sets tree-navigation vars for this post (NULL=not-set). */
   public function set_navigation( $prev_parent_post, $next_parent_post, $prev_post, $next_post, $first_child_post )
   {
      $this->prev_parent_post = $prev_parent_post;
      $this->next_parent_post = $next_parent_post;
      $this->prev_post = $prev_post;
      $this->next_post = $next_post;
      $this->first_child_post = $first_child_post;
   }

   /*!
    * \brief Builds URL for forum-thread-post (without subdir-prefix) with specified anchor.
    * \param $anchor anchorname to link to; if null use current post-id
    */
   public function build_url_post( $anchor=null, $url_suffix='' )
   {
      if ( is_null($anchor) )
         $anchor = '#' . ((int)$this->id);
      elseif ( (string)$anchor != '' )
         $anchor = '#' . ((string)$anchor);
      // else: anchor=''

      if ( $url_suffix != '' && $url_suffix[0] != URI_AMP )
         $url_suffix = URI_AMP . $url_suffix;

      $url = sprintf( 'read.php?forum=%d'.URI_AMP.'thread=%d%s%s',
         $this->forum_id, $this->thread_id, $url_suffix, $anchor );
      return $url;
   }//build_url_post

   /*! \brief Builds link to this post for specified date using given anchor-attribs. */
   public function build_link_postdate( $date, $attbs='' )
   {
     if ( empty($date) )
         return NO_VALUE;

     $datestr = date( DATE_FMT, $date );
     return anchor( $this->build_url_post(), $datestr, '', $attbs );
   }

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   public function to_string()
   {
      return "ForumPost(id={$this->id}): "
         . "forum_id=[{$this->forum_id}], "
         . "thread_id=[{$this->thread_id}], "
         . "#posts=[{$this->count_posts}], "
         . "#hits=[{$this->count_hits}], "
         . "last_post_id=[{$this->last_post_id}], "
         . "subject=[{$this->subject}], "
         . 'text..=[' . cut_str($this->text, 30, false, '..], ')
         . "flags=[{$this->flags}], "
         . 'author={' . ( is_null($this->author) ? 'null' : $this->author->to_string() ) . '}, '
         . "parent_id=[{$this->parent_id}], "
         . "answer#=[{$this->answer_num}], "
         . "depth=[{$this->depth}], "
         . "posidx=[{$this->posindex}], "
         . "approved=[{$this->approved}], "
         . "created=[{$this->created}], "
         . "last_changed=[{$this->last_changed}], "
         . "last_edited=[{$this->last_edited}], "
         . "crc32=[{$this->crc32}], "
         . "old_id=[{$this->old_id}], "
         . "thread_no_link=[{$this->thread_no_link}], "
         . "creation_order=[{$this->creation_order}], "
         . "has_new_posts=[{$this->has_new_posts}], "
         . "is_read=[{$this->is_read}], "
         . "score=[{$this->score}]";
   }//to_string

   /*!
    * \brief Returns true, if user has read specified ForumPost,
    *        i.e. own post, newer than FORUM_WEEKS_NEW_END or has newer thread-read-date
    * \param $fread ForumRead-object pre-loaded for post
    */
   public function is_post_read( $fread )
   {
      // own posts are always read
      if ( $this->is_author($fread->uid) )
         return true;

      $chkdate = $this->created; // check against post creation-date

      // mark read, if date passed global read-date
      if ( $fread->min_date >= $chkdate )
         return true;

      // check if mark as read, if date passed thread read-date
      if ( $fread->has_newer_read_date($chkdate, $fread->fid, $fread->tid) )
         return true;

      return false; // unread = new
   }

   /*! \brief Sets this->is_read using is_post_read(fread). */
   public function set_post_is_read( $fread )
   {
      $this->is_read = $this->is_post_read( $fread );
   }


   // ---------- Static Class functions ----------------------------

   /*! \brief Builds basic QuerySQL to load post(s). */
   public static function build_query_sql()
   {
      // Posts: ID,Forum_ID,Time,Lastchanged,Lastedited,Subject,Text,User_ID,Parent_ID,Thread_ID,Flags,
      //        AnswerNr,Depth,crc32,PosIndex,old_ID,Approved,PostsInThread,LastPost
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'P.*',
         'UNIX_TIMESTAMP(P.Time) AS X_Time',
         'UNIX_TIMESTAMP(P.Lastchanged) AS X_Lastchanged',
         'UNIX_TIMESTAMP(P.Lastedited) AS X_Lastedited',
         'IF(P.Time>P.Lastedited,P.Time,P.Lastedited) as X_MaxEdit', // for order-by modication-date
         'PAuthor.Name AS Author_Name',
         'PAuthor.Handle AS Author_Handle',
         'PAuthor.Rating2 AS Author_Rating',
         'PAuthor.Adminlevel+0 AS Author_AdminLevel' );
      $qsql->add_part( SQLP_FROM,
         'Posts AS P',
         'INNER JOIN Players AS PAuthor ON PAuthor.ID=P.User_ID' ); // Post-Author
      return $qsql;
   }

   /*! \brief Returns ForumPost-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $post = new ForumPost(
            @$row['ID'],
            @$row['Forum_ID'],
            @$row['Thread_ID'],
            // Author_* not part of Posts-table, but are read if set in row
            User::newForumUser( @$row['User_ID'], @$row['Author_Name'], @$row['Author_Handle'],
               @$row['Author_AdminLevel'], @$row['Author_Rating'] ),
            @$row['LastPost'],
            @$row['PostsInThread'],
            @$row['Hits'],
            @$row['Subject'],
            @$row['Text'],
            @$row['Flags'],
            @$row['Parent_ID'],
            @$row['AnswerNr'],
            @$row['Depth'],
            @$row['PosIndex'],
            @$row['Approved'],
            @$row['X_Time'],
            @$row['X_Lastchanged'],
            @$row['X_Lastedited'],
            @$row['crc32'],
            @$row['old_ID']
         );
      return $post;
   }

   /*! \brief Returns M(=pending approval), H=hidden, S=shown for given approved value P|N|Y. */
   public static function get_approved_text( $approved )
   {
      if ( $approved == 'P' )
         return 'M';
      elseif ( $approved == 'N' )
         return 'H';
      else //if ( $approved == 'Y' )
         return 'S';
   }

   public static function allow_post_read_only()
   {
      global $player_row;
      return ( @$player_row['admin_level'] & ADMINGROUP_EXECUTIVE );
   }

} // end of 'ForumPost'
?>
