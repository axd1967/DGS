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

/*!
 * \file forum_functions.php
 *
 * \brief Functions for forum management.
 */

$TranslateGroups[] = "Forum"; //local use

require_once( "include/std_functions.php" );
require_once( 'include/gui_functions.php' );
require_once( 'include/std_classes.php' );
require_once( "include/form_functions.php" );
require_once( 'include/classlib_user.php' );
require_once( 'include/classlib_goban.php' );
//if( ALLOW_GO_DIAGRAMS ) require_once( "include/GoDiagram.php" );


define('NEW_LEVEL1', 4*7 *24*3600);  // four weeks (also see SECS_NEW_END)

//must follow the "ORDER BY PosIndex" order and have at least 64 chars:
$order_str = "*+-/0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
define('FORUM_MAX_DEPTH', 40); //half the length of the Posts.PosIndex field
define('FORUM_MAX_INDENT', 15); //at the display time


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

// Forums.Options
define('FORUMOPT_MODERATED',     0x0001);
define('FORUMOPT_GROUP_ADMIN',   0x0002); // forum is of admin group
define('FORUMOPT_GROUP_DEV',     0x0004); // forum is of develop/advisor group
// mask for normally hidden forums
define('FORUMOPTS_GROUPS_HIDDEN', FORUMOPT_GROUP_ADMIN|FORUMOPT_GROUP_DEV);


 /*!
  * \class ForumOptions
  * \brief Class to handle Forums.Options and user allowed to view hidden forums.
  */
class ForumOptions
{
   /*! \brief true if admin-option ADMOPT_FGROUP_ADMIN for user set [bool] */
   var $view_admin;
   /*! \brief true if admin-option ADMOPT_FGROUP_DEV for user set [bool] */
   var $view_dev;

   /*! \brief Constructs ForumOptions from specified player_row. */
   function ForumOptions( $player_row )
   {
      $this->view_admin = (@$player_row['AdminOptions'] & ADMOPT_FGROUP_ADMIN);
      $this->view_dev = (@$player_row['AdminOptions'] & ADMOPT_FGROUP_DEV);
   }

   /*! \brief returns true if forum with given Options can be seen by user. */
   function is_visible_forum( $fopts )
   {
      $show = true;

      // hidden forums has precedence (overwriting other forum-visibility)
      if( $fopts & FORUMOPTS_GROUPS_HIDDEN )
      {// is hidden forum
         if( ($fopts & FORUMOPT_GROUP_DEV) && $this->view_dev )
            $show = true;
         elseif( ($fopts & FORUMOPT_GROUP_ADMIN) && $this->view_admin )
            $show = true;
         else
            $show = false;
      }

      return $show;
   }

} // end of 'ForumOptions'


/* \brief Adds entry into Forumlog */
function add_forum_log( $tid, $pid, $action )
{
   global $player_row, $NOW;
   $uid = @$player_row['ID'];
   if( $uid <= 0 )
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

   if( @mysql_num_rows($result) != 1 )
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
         'SELECT Posts.ID, Forum_ID, Thread_ID, Subject, UNIX_TIMESTAMP(Time) as X_Time, '
            . 'User_ID, PAuthor.Name AS Author_Name, PAuthor.Handle AS Author_Handle, '
            . 'Forums.Name AS X_Forumname '
         . 'FROM Posts '
            . 'INNER JOIN Players AS PAuthor ON PAuthor.ID=Posts.User_ID '
            . 'INNER JOIN Forums ON Forums.ID=Posts.Forum_ID '
         . "WHERE Approved='P' ORDER BY Time" );

   $cnt = 0;
   if( mysql_num_rows($result) > 0 )
   {
      $disp_forum = new DisplayForum( 0, false );
      $disp_forum->cols = $cols = 4;
      $disp_forum->headline = array(
         T_('Posts pending approval') => "colspan=".($cols-1),
         anchor( 'forum/admin_show_forumlog.php', T_('Show forum log') ) => '',
      );
      $disp_forum->links = LINKPAGE_STATUS;
      $disp_forum->forum_start_table('Pending');

      while( $row = mysql_fetch_array( $result ) )
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
}


// mode-bitmask for get_new_string
define('NEWMODE_BOTTOM',   0x1);
define('NEWMODE_OVERVIEW', 0x2);
define('NEWMODE_NEWCOUNT', 0x4);
define('NEWMODE_NO_LINK',  0x8);

// draw-modes for draw_post()
define('MASK_DRAWPOST_MODES', 0x0f); // lower 4 bits reserved for draw-modes
define('DRAWPOST_NORMAL',  1); // post normal view
define('DRAWPOST_PREVIEW', 2); // post preview
define('DRAWPOST_EDIT',    3); // post edit
define('DRAWPOST_REPLY',   4); // post reply
define('DRAWPOST_SEARCH',  5); // post search-result view
define('MASK_DRAWPOST_HIDDEN', 0x10); // post-header hidden view
define('MASK_DRAWPOST_NO_BODY', 0x20); // no post-body shown

 /*!
  * \class DisplayForum
  *
  * \brief Class to help with display of forum-pages.
  */
class DisplayForum
{
   /*! \brief Current logged in user */
   var $user_id;
   /*! \brief true, if in moderating-mode */
   var $is_moderator;
   /*! \brief current forum-id (maybe 0) */
   var $forum_id;
   /*! \brief current thread-id (maybe 0) */
   var $thread_id;

   var $cols;
   var $links;
   var $headline;
   var $link_array_left;
   var $link_array_right;
   var $found_rows;
   var $new_count;
   var $back_post_id;
   var $cur_depth;
   var $show_score; // used for forum-search
   /*! \brief rx-terms (optionally array) that are to be highlighted in text. */
   var $rx_term;
   var $ConfigBoard;

   // consts
   var $max_rows;
   var $offset;
   var $fmt_new;
   var $navi_img;

   /*! \brief Constructs display handler for forum-pages. */
   function DisplayForum( $user_id, $is_moderator, $forum_id=0, $thread_id=0 )
   {
      $this->user_id = $user_id;
      $this->is_moderator = $is_moderator;
      $this->forum_id = $forum_id;
      $this->thread_id = $thread_id;

      $this->cols = 1;
      $this->links = 0;
      $this->headline = array();
      $this->link_array_left = array();
      $this->link_array_right = array();
      $this->found_rows = -1; // not shown
      $this->new_count = 0;
      $this->back_post_id = 0;
      $this->cur_depth = -1;
      $this->show_score = false;
      $this->rx_term = '';
      $this->ConfigBoard = null;

      $this->max_rows = MAXROWS_PER_PAGE_DEFAULT;
      $this->offset = 0;
      $this->fmt_new = '<span class="%s"><a name="%s%d"%s>%s</a></span>';
      $this->navi_img = null;
   }

   /*! \brief Setting rx-term (can be array or string). */
   function set_rx_term( $rx_term='' )
   {
      // highlight terms (skipping XML-elements like tags & entities)
      if( is_array($rx_term) && count($rx_term) > 0 )
         $this->rx_term = implode('|', $rx_term);
      elseif( !is_string($rx_term) )
         $this->rx_term = '';
      else
         $this->rx_term = $rx_term;
   }

   function setConfigBoard( $cfg_board )
   {
      $this->ConfigBoard = $cfg_board;
   }

   function show_found_rows( $rows )
   {
      if( $rows >=0 )
         $this->found_rows = $rows;
   }

   function print_moderation_note( $width )
   {
      if( $this->is_moderator)
         echo "<table width='$width'><tr><td align=right><font color=red>"
            . T_("Moderating") . "</font></td></tr></table>\n";
   }

   // param table_id: begining by an uppercase letter because used as sub-ID name (CSS);
   //                 values: 'Index', 'List', 'Read', 'Search', 'Revision', 'Pending'
   // param ReqParam: optional object RequestParameters containing URL-parts to be included for paging
   // note: sets cur_depth=-1
   function forum_start_table( $table_id, $ReqParam = null)
   {
      echo "<a name=\"ftop\">\n",
           "<table id='forum$table_id' class=Forum>\n";
      $this->make_link_array( $ReqParam );

      if( $this->links & LINK_MASKS )
         $this->echo_links('T');

      $this->print_headline();

      $this->cur_depth = -1;
   }

   function print_headline( $headline=NULL )
   {
      if( is_null($headline) )
         $headline = $this->headline;

      echo "<tr class=Caption>";
      $first = true;
      foreach( $headline as $name => $attbs )
      {
         if( $first && $this->found_rows >= 0 )
         {
            $fmt_entries = ($this->found_rows == 1 ) ? T_('%s entry') : T_('%s entries');
            $name .= SMALL_SPACING .
               '<span class="NaviInfo">(' . sprintf( $fmt_entries, $this->found_rows ) . ')</span>';
            $first = false;
         }
         echo "<td $attbs>$name</td>";
      }
      echo "</tr>\n";
   }

   function forum_end_table( $bottom_bar=true )
   {
      if( $bottom_bar && $this->links & LINK_MASKS )
         $this->echo_links('B');
      echo "</table>\n<a name=\"fbottom\">\n";
   }

   // param ReqParam: optional object RequestParameters containing URL-parts to be included for paging
   function make_link_array( $ReqParam = null )
   {
      global $NOW;
      $links = $this->links;
      $fid = $this->forum_id;
      if( !( $links & LINK_MASKS ) )
         return;

      if( $links & LINK_FORUMS )
         $this->link_array_left[T_('Forums')] = "index.php";

      if( $links & LINK_THREADS )
         $this->link_array_left[T_('Threads')] = "list.php?forum=$fid";

      if( $links & LINK_BACK_TO_THREAD )
      {
         $this->link_array_left[T_('Back to thread')] = "read.php?forum=$fid"
               .URI_AMP."thread={$this->thread_id}"
               .( ( $this->back_post_id ) ? '#'.$this->back_post_id : '' );
      }

      if( $links & LINK_NEW_TOPIC )
         $this->link_array_left[T_('New Topic')] = "read.php?forum=$fid";

      if( $links & LINK_REFRESH )
      {
         $get = array_merge( $_GET, $_POST);
         if( $links & LINKPAGE_READ )
            $url = make_url( 'read.php',
               array( 'forum' => $get['forum'], 'thread' => $get['thread'] ));
         elseif( $links & LINKPAGE_LIST )
            $url = make_url( 'list.php',
               array( 'forum' => $get['forum'], 'maxrows' => @$get['maxrows'] ));
         elseif( $links & LINKPAGE_SEARCH )
            $url = '';
         else
            $url = make_url( 'index.php', '' );
         if( $url )
            $this->link_array_left[T_('Refresh')] = $url;
      }

      if( $links & LINK_SEARCH )
         $this->link_array_left[T_('Search')] = "search.php";

      if( $links & LINK_MARK_READ )
         $this->link_array_left[T_('Mark All Read')] = "read.php?forum=$fid"
            .URI_AMP."thread={$this->thread_id}"
            .URI_AMP.'markread=pall.'.$NOW; // pall=all thread-posts

      if( $links & LINK_TOGGLE_MODERATOR )
      {
         // preserve all page-args on moderator switch
         $get = array_merge( $_GET, $_POST);
         unset($get['modact']);
         unset($get['modpid']);
         $get['moderator'] = ($this->is_moderator) ? 'n' : 'y';
         if( $links & LINKPAGE_READ )
            $url = make_url( 'read.php', $get );
         elseif( $links & LINKPAGE_LIST )
            $url = make_url( 'list.php', $get );
         elseif( $links & LINKPAGE_SEARCH )
            $url = make_url( 'search.php', $get );
         else
            $url = make_url( 'index.php', $get );
         $this->link_array_right[T_("Toggle forum moderator")] = $url;
      }

      $navi = array( 'maxrows' => $this->max_rows );
      if( !is_null($ReqParam) && ($links & (LINKPAGE_SEARCH|LINK_PREV_PAGE|LINK_NEXT_PAGE)) )
         $navi = array_merge( $navi, $ReqParam->get_entries() );

      if( $links & LINK_PREV_PAGE )
      {
         $navi['offset'] = $this->offset - $this->max_rows;
         if( $links & LINKPAGE_SEARCH )
            $href = 'search.php?';
         else
            $href = 'list.php?forum=' . $fid;
         $this->link_array_right[T_("Prev Page")] =
            array( make_url( $href, $navi ), '', array( 'accesskey' => ACCKEY_ACT_PREV ) );
      }
      if( $links & LINK_NEXT_PAGE )
      {
         $navi['offset'] = $this->offset + $this->max_rows;
         if( $links & LINKPAGE_SEARCH )
            $href = 'search.php?';
         else
            $href = 'list.php?forum=' . $fid;
         $this->link_array_right[T_("Next Page")] =
            array( make_url( $href, $navi ), '', array( 'accesskey' => ACCKEY_ACT_NEXT ) );
      }
   }

   function echo_links( $id )
   {
      $lcols = $this->cols; //1; $cols/2; $cols-1;
      $tmp = ( $lcols > 1 ? ' colspan='.$lcols : '' );
      echo "<tr class=Links$id><td$tmp><div class=TreeLinks>";

      $first = true;
      foreach( $this->link_array_left as $name => $link )
      {
         if( !$first )
            echo "&nbsp;|&nbsp;";
         else
            $first = false;
         if( is_array($link) )
            echo anchor( $link[0], $name, $link[1], $link[2]);
         else
            echo anchor( $link, $name);
      }
      echo $this->get_new_string( NEWMODE_BOTTOM );

      $lcols = $this->cols - $lcols;
      $tmp = ( $lcols > 1 ? ' colspan='.$lcols : '' );
      if( $lcols > 0 )
         echo "</div></td><td$tmp><div class=PageLinks>";
      else
         echo "</div><div class=PageLinks>";

      $first = true;
      foreach( $this->link_array_right as $name => $link )
      {
         if( !$first )
            echo "&nbsp;|&nbsp;";
         else
            $first = false;
         if( is_array($link) )
            echo anchor( $link[0], $name, $link[1], $link[2]);
         else
            echo anchor( $link, $name);
      }

      echo "</div></td></tr>\n";
   }

   /*!
    * \brief Increase global new counter, builds and returns current new-string.
    * param $mode bitmask of NEWMODE_BOTTOM ('new' for bottom-bar);
    *       NEWMODE_OVERVIEW, NEWMODE_NEWCOUNT (append NEW-count), NEWMODE_NO_LINK
    * param $cnt_new number of new entries to show, add '(count)' if NEWMODE_NEWCOUNT set
    * param $newdate used to decide which CSS-class to use for display new-string
    *       (NewFlag=default, OlderNewFlag)
    */
   function get_new_string( $mode, $cnt_new=0, $newdate=0 )
   {
      $new = '';
      $anchor_prefix = 'new';
      if( $mode & NEWMODE_BOTTOM ) // bottom-bar refers to 1st-NEW
      {
         $link = ($mode & NEWMODE_NO_LINK) ? '' : ' href="#new1"';
         if( $this->new_count > 0 )
            $new = sprintf( $this->fmt_new, 'NewFlag', $anchor_prefix,
               $this->new_count + 1, $link, T_('first new') );
      }
      elseif( $cnt_new > 0 )
      {
         global $NOW;
         $newclass = ( $newdate == 0 || $newdate + NEW_LEVEL1 > $NOW )
            ? 'NewFlag'       // recent 'new'
            : 'OlderNewFlag'; // older 'new'

         if( $mode & NEWMODE_OVERVIEW )
         {
            $anchor_prefix = 'treenew';
            $addnew = 0;
         }
         else
            $addnew = 1;

         $this->new_count++;
         $link = ($mode & NEWMODE_NO_LINK)
            ? '' : sprintf(' href="#new%d"', $this->new_count + $addnew );
         $new = sprintf( $this->fmt_new,
            $newclass, $anchor_prefix, $this->new_count, $link,
            T_('new') . ( ($mode & NEWMODE_NEWCOUNT) ? " ($cnt_new)" : '' ) );
      }
      return $new;
   }//get_new_string

   // \param $drawmode one of DRAWPOST_NORMAL | PREVIEW | EDIT | REPLY
   function forum_message_box( $drawmode, $post_id, $GoDiagrams=null, $ErrorMsg='', $Subject='', $Text='' )
   {
      global $player_row;
      if( $player_row['MayPostOnForum'] == 'N' ) // user not allowed to post
         return;

      // reply-prefix
      if( ($drawmode == DRAWPOST_NORMAL || $drawmode == DRAWPOST_REPLY)
            && strlen($Subject) > 0 && strcasecmp(substr($Subject,0,3), "re:") != 0 )
         $Subject = "RE: " . $Subject;

      $msg_form = 'messageform';
      $form = new Form( $msg_form, "read.php#preview", FORM_POST );

      global $player_row;
      if( @$player_row['ID'] <= GUESTS_ID_MAX )
      {
         $form->add_row( array(
               'DESCRIPTION', sprintf('<span class="ErrorMsg"><b>%s</b></span>', T_('NOTE#guest')),
               'TEXT', '<span class="ErrorMsg">'
                        . T_("Forum posts by the guest-user can be approved or rejected by the server admins.<br>\n"
                           . "If you want a private (non-public) answer, add your email and ask for private contact.")
                        . '</span>' ));
      }
      if( $ErrorMsg )
      {
         $form->add_row( array(
               'DESCRIPTION', sprintf('<span class="ErrorMsg"><b>%s</b></span>', T_('Error#forum')),
               'TEXT', "<span class=\"ErrorMsg\">$ErrorMsg</span>" ));
      }

      $form->add_row( array(
            'DESCRIPTION', T_('Subject'),
            'TEXTINPUT', 'Subject', 50, 80, $Subject,
            'HIDDEN', ($drawmode == DRAWPOST_EDIT ? 'edit' : 'parent'), $post_id,
            'HIDDEN', 'thread', $this->thread_id,
            'HIDDEN', 'forum', $this->forum_id ));
      $form->add_row( array(
            'TAB', 'TEXTAREA', 'Text', 70, 25, $Text ));

      $arr_dump_diagrams = array();
/*
      if( ALLOW_GO_DIAGRAMS && is_javascript_enabled() && !is_null($GoDiagrams) )
      {
         $diagrams_str = draw_editors($GoDiagrams);
         if( !empty($diagrams_str) )
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
   function change_depth( $new_depth )
   {
      if( $new_depth < 1 && $this->cur_depth < 1 )
         return;

      if( $this->cur_depth >= 1 ) //this means that a cell table is already opened
         echo "</table></td></tr>";

      if( $new_depth < 1 ) //this means close it
      {
         echo "</table></td></tr>";
         $this->cur_depth = -1;
         return;
      }

      if( $this->cur_depth < 1 ) //this means opened it
         echo "<tr><td colspan={$this->cols}><table width=\"100%\" border=0 cellspacing=0 cellpadding=0>";

      // then build the indenting row
      $this->cur_depth = $new_depth;
      echo "<tr>";
      $indent = "<td class=Indent>&nbsp;</td>";
      $i = min( $this->cur_depth, FORUM_MAX_INDENT);
      $c = FORUM_MAX_INDENT+1 - $i;
      switch( (int)$i )
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
   }

   /*! \brief Inits and returns navigational images for draw_post if not initialized yet. */
   function init_navi_images()
   {
      if( !is_array($this->navi_img) )
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
   }


   /*!
    * \brief Draws various views of post controlled by $drawmode.
    * \param $drawmode DRAWPOST_..., MASK_DRAWPOST_...
    * \param $post: ForumPost-object to draw
    */
   function draw_post( $drawmode, $post, $is_my_post, $GoDiagrams=null )
   {
      global $NOW, $player_row;

      // post-vars needed:
      //    id, forum_id, thread_id, parent_id, subject, text, author(id,name,handle),
      //    pending_approval, last_updated, last_edited, last_read
      // post-vars only needed for forum-search: forum_name, score; this->show_score

      $pid = $post->id;
      $thread_url = $post->build_url_post(''); //post_url ended by #$pid
      $term_url = ( $this->rx_term != '' ) ? URI_AMP."xterm=".urlencode($this->rx_term) : '';
      $user_may_post = ( $player_row['MayPostOnForum'] != 'N' ); // use allowed to post?

      $post_reference = '';
      $cols = 2; //one for the subject header, one for the possible approved/hidden state

      // highlight terms in Subject/Text
      $sbj = make_html_safe( $post->subject, SUBJECT_HTML, $this->rx_term );
      $txt = make_html_safe( $post->text, true, $this->rx_term );
//      if( ALLOW_GO_DIAGRAMS && is_javascript_enabled() && !is_null($GoDiagrams) )
//         $txt = replace_goban_tags_with_boards($txt, $GoDiagrams);
      $txt = MarkupHandlerGoban::replace_igoban_tags( $txt );

      if( strlen($txt) == 0 ) $txt = '&nbsp;';

      // CSS-class for post header
      $drawmode_type = ($drawmode & MASK_DRAWPOST_MODES);
      if( $drawmode == DRAWPOST_NORMAL ) // most frequent case, no extras
         $class = 'Normal';
      elseif( $drawmode_type == DRAWPOST_SEARCH )
         $class = 'SearchResult';
      elseif( $drawmode & MASK_DRAWPOST_HIDDEN )
         $class = 'Hidden';
      elseif( $drawmode_type == DRAWPOST_PREVIEW )
         $class = 'Preview';
      elseif( $drawmode_type == DRAWPOST_EDIT )
         $class = 'Edit';
      elseif( $drawmode_type == DRAWPOST_REPLY )
         $class = 'Reply';
      else //if drawmode_type 0 or DRAWPOST_NORMAL
      {
         $drawmode_type = DRAWPOST_NORMAL;
         $class = 'Normal';
      }
      $hdrclass = 'PostHead'.$class;

      // post header
      if( $drawmode_type == DRAWPOST_PREVIEW )
      {
         $hdrrows = 2;
         $hdrcols = $cols;

         echo "\n<tr class=\"$hdrclass Subject\"><td class=Subject colspan=$hdrcols>"
            ,"<a class=PostSubject name='preview'>$sbj</a></td></tr> "
            ,"\n<tr class=\"$hdrclass Author\"><td class=Author colspan=$hdrcols>"
            ,T_('by'),' ' ,user_reference( REF_LINK, 1, '', $player_row)
            ,' &nbsp;&nbsp;&nbsp;' ,date(DATE_FMT, $NOW)
            ,"</td></tr>";
      }
      else
      {
         if( $drawmode & MASK_DRAWPOST_HIDDEN )
         {
            $hdrcols = $cols-1; //because of the rowspan=$hdrrows in the "hidden"-column
            $newstr = '';
         }
         else
         {
            $hdrcols = $cols;
            $newstr = $this->get_new_string( 0, $post->count_new, $post->created );
         }
         $subject_modstr = ( $drawmode & MASK_DRAWPOST_NO_BODY )
            ? '[* '.T_('subject moderated').' *]' : $sbj;

         if( $drawmode_type == DRAWPOST_SEARCH )
         {
            $hdrrows = 3;

            // [header-row] search-found
            echo "\n<tr class=\"$hdrclass FoundForum\"><td class=FoundForum colspan=$hdrcols>";
            echo '<span class=FoundForum>' ,T_('found in forum')
               ,' <a href="list.php?forum='
               ,$post->forum_id ,'">' ,$post->forum_name ,"</a></span>\n";
            if( $this->show_score )
               echo ' <span class=FoundScore>' ,T_('with')
                  ,' <span>' ,T_('Score') ,' ' ,$post->score ,"</span></span>\n";
         }
         else
         {
            $hdrrows = 2;

            // [header-row] subject
            echo "\n<tr class=\"$hdrclass Subject\"><td class=Subject colspan=$hdrcols>";

            //from revision_history or because, when edited, the link will be obsolete
            if( $drawmode_type == DRAWPOST_EDIT || $post->thread_no_link )
               echo "<a class=PostSubject name=\"$pid\">$subject_modstr</a>";
            else
               echo '<a class=PostSubject href="', $thread_url, $term_url,
                  "#$pid\" name=\"$pid\">$subject_modstr</a>", $newstr;
         }

         // first [header-row] with different content (adding hidden-state)
         if( $hdrcols != $cols )
         {
            echo "</td>\n <td rowspan=$hdrrows class=PostStatus><span class=\"Hidden\">";
            echo ( $post->is_pending_approval() ? T_('Awaiting<br>approval') : T_('Hidden') );
            if( $drawmode & MASK_DRAWPOST_NO_BODY )
               echo sprintf( ' (%s)', T_('Content moderated'));
            echo '</span>';
         }
         echo '</td></tr>';

         if( $drawmode_type == DRAWPOST_SEARCH )
         {
            // [header-row] subject
            echo "\n<tr class=\"$hdrclass Subject\"><td class=Subject colspan=$hdrcols>";
            echo '<a class=PostSubject href="', $thread_url, $term_url, "#$pid\">$subject_modstr</a>";
            echo "</td></tr>";
         }

         // [header-row] post-info
         echo "\n<tr class=\"$hdrclass Author\"><td class=Author colspan=$hdrcols>";

         $post_reference = date(DATE_FMT, $post->created);
         echo T_('by') ,' ' , $post->author->user_reference(),
              echo_image_admin( $post->author->AdminLevel ),
              ' ', SMALL_SPACING, $post_reference;

         if( !($drawmode & MASK_DRAWPOST_NO_BODY) )
            echo $this->get_post_edited_string( $post );
         if( $post->last_edited > 0 )
            $post_reference = date(DATE_FMT, $post->last_edited);

         echo "</td></tr>\n";

         $post_reference = "<user {$post->author->id}> ($post_reference):";
      }

      // post body
      if( !($drawmode & MASK_DRAWPOST_NO_BODY) )
         echo "\n<tr class=PostBody><td colspan=$cols>$txt</td></tr>";


      // post bottom line (footer)
      if( $drawmode_type == DRAWPOST_NORMAL )
      {
         echo "\n<tr class=PostButtons><td colspan=$cols>";

         $imgarr = $this->init_navi_images();
         $prev_parent = ( is_null($post->prev_parent_post) )
            ? ''
            : anchor( '#'.$post->prev_parent_post->id, $imgarr['prev_parent'] ) . '&nbsp;';
         $next_parent = ( is_null($post->next_parent_post) )
            ? ''
            : anchor( '#'.$post->next_parent_post->id, $imgarr['next_parent'] ) . '&nbsp;';
         $prev_answer = ( is_null($post->prev_post) )
            ? ''
            : anchor( '#'.$post->prev_post->id, $imgarr['prev_answer'] ) . '&nbsp;';
         $next_answer = ( is_null($post->next_post) )
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

         if( $user_may_post )
         {
            // reply link
            echo '<a href="', $thread_url,URI_AMP,"reply=$pid#$pid\">[ ", T_('reply'), " ]</a>&nbsp;&nbsp;";
            if( ALLOW_QUOTING )
               echo '<a href="', $thread_url,URI_AMP,"quote=1",URI_AMP,"reply=$pid#$pid\">[ ",
                  T_('quote'), " ]</a>&nbsp;&nbsp;";

            // edit link
            if( $is_my_post )
               echo '<a class=Highlight href="', $thread_url,URI_AMP,"edit=$pid#$pid\">[ ",
                  T_('edit'), " ]</a>&nbsp;&nbsp;";
         }

         // mark read link
         if( $post->count_new > 0 )
         {
            $readmark = ( $post->read_mark != '' ) ? $post->read_mark : "p$pid.$NOW";
            echo '<a href="', $thread_url, URI_AMP, "markread=$readmark#$pid\">", // mark post
               "[ ", T_('mark read'), " ]</a>&nbsp;&nbsp;";
         }

         // END Navi (next-answer/next-parent/bottom)
         echo $next_answer,
            $next_parent,
            anchor( '#fbottom', $imgarr['bottom'] ),
            '&nbsp;',
            $first_answer,
            '&nbsp;';

         // for moderator: hide/show/approve/reject-link
         if( $this->is_moderator )
         {
            $modurl_fmt = '<a class=Highlight href="'.$thread_url
               . URI_AMP."modpid=$pid".URI_AMP."modact=%s#$pid\">[ %s ]</a>";
            if( $post->is_pending_approval() )
            {
               echo sprintf( $modurl_fmt, 'approve',  T_('Approve') ),
                  "&nbsp;&nbsp;",
                  sprintf( $modurl_fmt, 'reject',  T_('Reject') );
            }
            else
            {
               if( $post->is_approved() )
                  echo sprintf( $modurl_fmt, 'hide',  T_('hide') );
               else
                  echo sprintf( $modurl_fmt, 'show',  T_('show') );
            }
         }
         echo "</td></tr>\n";
      }

      return $post_reference;
   } //draw_post

   /*! \brief Draw tree-overview for this thread. */
   function draw_overview( $fthread )
   {
      global $base_path, $player_row;
      $this->new_count = 0;
      $this->change_depth( 1 );

      echo "\n<tr class=TreePostNormal><td><table class=ForumTreeOverview>",
         "\n<tr class=\"TreePostNormal Header\">",
         sprintf( '<th>%s</th><th>%s</th><th>%s</th></tr>',
            T_('Subject'), T_('Author'), T_('Last changed') );

      // draw for post: subject, author, date
      $c=2;
      foreach( $fthread->posts as $pid => $post )
      {
         $hidden = !$post->is_approved();
         $is_my_post = ( $post->author->id == $this->user_id );
         $show_hidden_post = ( $is_my_post || $this->is_moderator );
         if( $hidden && !$post->is_thread_post() && !$show_hidden_post )
            continue;

         $subj_part = substr( $post->subject, 0, 40 )
            . ( (strlen($post->subject) > 40) ? ' ...' : '' );
         $sbj = make_html_safe( $subj_part, SUBJECT_HTML, $this->rx_term );
         $newstr = (!$hidden)
            ? $this->get_new_string( NEWMODE_OVERVIEW, $post->count_new, $post->created ) : '';
         $modstr = ( $hidden && ( $post->is_thread_post() || $this->is_moderator || $is_my_post ) )
            ? MED_SPACING . sprintf( '<span class="Moderated">(%s)</span>',
                  ForumPost::get_approved_text( $post->approved ) ) : '';

         $c = 3 - $c;
         $mypostclass = ($is_my_post) ? ' class=MyPost' : '';
         $mypost_fmt = ($is_my_post) ? '<span class="MyPost">%s</span>' : '%s';
         echo "\n<tr class=\"TreePostNormal Row{$c}". ($is_my_post ? ' MyPost' : '') ."\">",
            "<td$mypostclass>",
            str_repeat( '&nbsp;', 3*($post->depth - 1) ),
            sprintf( $mypost_fmt, anchor( '#'.$post->id, $sbj, '', 'class=PostSubject' ) ),
            $newstr,
            $modstr,
            "</td><td>",
            sprintf( '<span class=PostUser>%s</span>', $post->author->user_reference() ),
            "</td><td>",
            sprintf( '<span class=PostDate>%s</span>',
               date( DATE_FMT, max($post->created, $post->last_edited) ) ),
            '</td></tr>';
      }

      echo "\n</table></td></tr>\n";

      $this->new_count = 0;
      $this->change_depth( -1 );
   } //draw_overview

   function get_post_edited_string( $post )
   {
      if( $post->last_edited > 0 )
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
   var $id;
   /*! \brief Forums.Name : str */
   var $name;
   /*! \brief Forums.Description : str */
   var $description;
   /*! \brief Forums.LastPost : id (=Posts.ID) */
   var $last_post_id;
   /*! \brief Forums.ThreadsInForum : int */
   var $count_threads;
   /*! \brief Forums.PostsInForum : int */
   var $count_posts;
   /*! \brief Forums.SortOrder : int */
   var $sort_order;
   /*! \brief Forums.Options : int, bit-values see FORUMOPT_MODERATED, etc. */
   var $options;

   /*! \brief partly filled ForumPost-object for last_post_id [default=null] */
   var $last_post;
   /*! \brief array of ForumThread-objects [default=null] */
   var $threads;
   /*! \brief true, if there are more threads to page-navigate. */
   var $navi_more_threads;

   // non-db vars

   /*! \brief Count of new entries for this forum; -1=update needed. */
   var $count_new;


   /*! \brief Constructs Forum with specified args. */
   function Forum( $id=0, $name='', $description='', $last_post_id=0,
         $count_threads=0, $count_posts=0, $sort_order=0, $options=0 )
   {
      $this->id = $id;
      $this->name = $name;
      $this->description = $description;
      $this->last_post_id = $last_post_id;
      $this->count_threads = $count_threads;
      $this->count_posts = $count_posts;
      $this->sort_order = $sort_order;
      $this->options = $options;
      // non-db
      $this->last_post = null;
      $this->threads = null;
      $this->count_new = -1; // unknown count
   }

   /*! \brief Returns true, if forum is moderated. */
   function is_moderated()
   {
      return ( $this->options & FORUMOPT_MODERATED );
   }

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   function to_string()
   {
      return "Forum(id={$this->id}): "
         . "name=[{$this->name}], "
         . "description=[{$this->description}], "
         . "last_post_id=[{$this->last_post_id}], "
         . "#threads=[{$this->count_threads}], "
         . "#posts=[{$this->count_posts}], "
         . "sort_order=[{$this->sort_order}], "
         . "options=[{$this->options}], "
         . 'last_post={' . ( is_null($this->last_post) ? '' : $this->last_post->to_string() ) . '}';
   }

   /*!
    * \brief Loads threads for current forum into this object (threads-var).
    * \return count of loaded rows
    */
   function load_threads( $user_id, $is_moderator, $show_rows, $offset=0 )
   {
      if( !is_numeric($user_id) )
         error('invalid_user', "Forum.load_threads($user_id)");
      if( !is_numeric($show_rows) || !is_numeric($offset) )
         error('invalid_args', "Forum.load_threads(show_rows=$show_rows,offset=$offset)");

      $forum_id = $this->id;
      if( !is_numeric($forum_id) )
         error('unknown_forum', "Forum.load_threads(forum_id={$forum_id})");

      $mindate = ForumRead::get_min_date();
      $qsql = ForumPost::build_query_sql();
      $qsql->add_part( SQLP_FIELDS,
         'LPAuthor.ID AS LPAuthor_ID',
            'LPAuthor.Name AS LPAuthor_Name',
            'LPAuthor.Handle AS LPAuthor_Handle',
         'FR.NewCount AS FR_NewCount',
         "IF(ISNULL(FR.User_ID),(P.Updated > FROM_UNIXTIME($mindate)),"
            . "(FR.NewCount<0 OR P.Updated > FR.Time)) AS FR_NeedUpdate" );
      $qsql->add_part( SQLP_FROM,
         'LEFT JOIN Posts AS LP ON LP.ID=P.LastPost',  // LastPost
         'LEFT JOIN Players AS LPAuthor ON LPAuthor.ID=LP.User_ID', // LastPost-Author
         "LEFT JOIN ForumRead AS FR ON FR.User_ID='$user_id' AND FR.Forum_ID=P.Forum_ID "
            . 'AND FR.Thread_ID=P.Thread_ID AND FR.Post_ID='.THPID_NEWCOUNT );
      $qsql->add_part( SQLP_WHERE,
         "P.Forum_ID=$forum_id",
         'P.Parent_ID=0', 'P.Thread_ID=P.ID' ); //=thread (better query with thread=id for ForumRead)
      if( !$is_moderator )
         $qsql->add_part( SQLP_WHERE, 'P.PostsInThread>0' );
      $qsql->add_part( SQLP_ORDER, 'P.LastChanged DESC' );
      $qsql->add_part( SQLP_LIMIT, sprintf( '%d,%d', $offset, $show_rows + 1) );

      $query = $qsql->get_select();
      $result = db_query( "Forum.load_threads($user_id,$is_moderator,$show_rows,$offset)", $query );
      $rows = mysql_num_rows($result);

      $this->navi_more_threads = false;
      $thlist = array();
      while( $row = mysql_fetch_array( $result ) )
      {
         $thread = ForumPost::new_from_row( $row ); // Post
         $thread->last_post =
            new ForumPost( $thread->last_post_id, $this->id, $thread->thread_id,
               //TODO use User-class
               new ForumUser( $row['LPAuthor_ID'], $row['LPAuthor_Name'], $row['LPAuthor_Handle'] ) );
         $thread->count_new = ($row['FR_NeedUpdate']) ? -1 : @$row['FR_NewCount'] + 0;

         $thlist[] = $thread;
      }
      mysql_free_result($result);

      if( $rows > $show_rows )
      {
         array_pop( $thlist ); // remove last entry
         $this->navi_more_threads = true;
         $rows--;
      }

      $this->threads = $thlist;
      return $rows;
   }

   /*! \brief Use after call of load_threads() to check, if there are more threads to load. */
   function has_more_threads()
   {
      return $this->navi_more_threads;
   }

   /*!
    * \brief Fix forum consistency for fields (LastPost, PostsInForum, ThreadsInForum).
    * \param $debug if true only echoes sql-statements
    * \note see section 'Calculated database fields' in 'specs/forums.txt'
    * \return number of updates
    */
   function fix_forum( $debug, $debug_format="%s\n" )
   {
      $upd_arr = array();
      $fid = $this->id;

      // read fix for Forums.LastPost
      $row = mysql_single_fetch( "Forum.fix_forum.read.LastPost($fid)",
            "SELECT ID AS X_LastPost FROM Posts " .
            "WHERE Forum_ID='$fid' AND Thread_ID>0 AND Approved='Y' AND PosIndex>'' " .
            "ORDER BY Time DESC LIMIT 1" );
      if( $row && $row['X_LastPost'] != $this->last_post_id )
         $upd_arr[] = 'LastPost=' . $row['X_LastPost'];
      elseif ( !$row && $this->last_post_id != 0 )
         $upd_arr[] = 'LastPost=0';

      // read fix for Forums.PostsInForum
      $row = mysql_single_fetch( "Forum.fix_forum.read.PostsInForum($fid)",
            "SELECT COUNT(*) AS X_Count FROM Posts " .
            "WHERE Forum_ID='$fid' AND Thread_ID>0 AND Approved='Y' AND PosIndex>''" );
      if( $row && $row['X_Count'] != $this->count_posts )
         $upd_arr[] = 'PostsInForum=' . $row['X_Count'];

      // read fix for Forums.ThreadsInForum
      // note: delivers correct value only if Posts.PostsInThread is correct, so fix Posts first
      $row = mysql_single_fetch( "Forum.fix_forum.read.ThreadsInForum($fid)",
            "SELECT COUNT(*) AS X_Count FROM Posts ".
            "WHERE Forum_ID='$fid' AND Parent_ID=0 AND PostsInThread>0" );
      if( $row && $row['X_Count'] != $this->count_threads )
         $upd_arr[] = 'ThreadsInForum=' . $row['X_Count'];

      // fix Forums
      if( count($upd_arr) > 0 )
      {
         $query = "UPDATE Forums SET " . implode(', ', $upd_arr) . " WHERE ID='$fid' LIMIT 1";
         echo sprintf( $debug_format, $query );
         if( !$debug )
            db_query( "Forum.fix_forum.update($fid)", $query );
      }

      return (count($upd_arr) > 0) ? 1 : 0;
   }


   // ---------- Static Class functions ----------------------------

   /*! \brief Returns db-fields to be used for query of Forum-object. */
   function build_query_sql()
   {
      // Forums: ID,Name,Description,LastPost,ThreadsInForum,PostsInForum,SortOrder,Options
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS, 'Forums.*' );
      $qsql->add_part( SQLP_FROM, 'Forums' );
      return $qsql;
   }

   /*! \brief Returns Forum-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $forum = new Forum(
            @$row['ID'],
            @$row['Name'],
            @$row['Description'],
            @$row['LastPost'],
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
   function load_forum( $id )
   {
      if( !is_numeric($id) || $id <= 0 )
         error('unknown_forum', "Forum.load_forum($id)");

      $qsql = Forum::build_query_sql();
      $qsql->add_part( SQLP_WHERE, "ID='$id'" );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $query = $qsql->get_select();
      $row = mysql_single_fetch( "Forum.load_forum2($id)", $query );
      if( !$row )
         error('unknown_forum', "Forum.load_forum3($id)");

      return Forum::new_from_row( $row );
   }

   /*!
    * \brief Returns array of Forum-objects for specified user-id;
    *        returns null if no feature found.
    */
   function load_forum_list( $user_id )
   {
      if( !is_numeric($user_id) )
         error('invalid_user', "Forum.load_forum_list.check.user_id($user_id)");

      $mindate = ForumRead::get_min_date();
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'Forums.*',
         'LP.Thread_ID AS LP_Thread',
         'UNIX_TIMESTAMP(LP.Time) AS LP_Time',
         'LP.User_ID AS LP_User_ID',
         'P.Name AS LP_Name',
         'P.Handle AS LP_Handle',
         'FR.NewCount AS FR_NewCount',
         "IF(ISNULL(FR.User_ID),(Forums.Updated > FROM_UNIXTIME($mindate)),"
            . "(FR.NewCount<0 OR Forums.Updated > FR.Time)) AS FR_NeedUpdate" );
      $qsql->add_part( SQLP_FROM,
         'Forums',
         'LEFT JOIN Posts AS LP ON Forums.LastPost=LP.ID',
         'LEFT JOIN Players AS P ON P.ID=LP.User_ID',
         "LEFT JOIN ForumRead AS FR ON FR.User_ID='$user_id' "
            . 'AND FR.Forum_ID=Forums.ID AND FR.Thread_ID=0 AND FR.Post_ID='.THPID_NEWCOUNT );
      $qsql->add_part( SQLP_ORDER, 'SortOrder' );

      $query = $qsql->get_select();
      $result = db_query( "Forum.load_forum_list($user_id)", $query );

      $flist = array();
      while( $row = mysql_fetch_array( $result ) )
      {
         $forum = Forum::new_from_row( $row );
         $post =
            new ForumPost( $forum->last_post_id, $forum->id, $row['LP_Thread'],
               new ForumUser( $row['LP_User_ID'], $row['LP_Name'], $row['LP_Handle'] ) );
         $post->created = $row['LP_Time'];
         $forum->last_post = $post;
         $forum->count_new = ($row['FR_NeedUpdate']) ? -1 : @$row['FR_NewCount'] + 0;

         $flist[] = $forum;
      }
      mysql_free_result($result);

      return $flist;
   }

   /*!
    * \brief Returns array of partial Forum-objects (only those visible to a player)
    *        with name => id entries.
    * param forum_opts is object ForumOptions($player_row), load all forum names if omitted
    */
   function load_forum_names( $forum_opts )
   {
      // build forum-array for filter: ( Name => Forum_ID )
      $result = db_query( 'Forum.load_forum_names',
            'SELECT ID, Name, Options FROM Forums ORDER BY SortOrder' );

      $fnames = array();
      while( $row = mysql_fetch_array( $result ) )
      {
         // can user view forum?
         if( $forum_opts && !$forum_opts->is_visible_forum( $row['Options'] ) )
            continue;

         $fnames[$row['Name']] = $row['ID'];
      }
      mysql_free_result($result);
      return $fnames;
   }

   /*! \brief Returns array of Forum-objects with raw Forums-fields (for forum-fixes). */
   function load_fix_forum_list()
   {
      $qsql = Forum::build_query_sql();
      $qsql->add_part( SQLP_ORDER, 'ID' );

      $result = db_query( "Forum.load_fix_forum_list", $qsql->get_select() );
      $flist = array();
      while( $row = mysql_fetch_array( $result ) )
      {
         $forum = Forum::new_from_row( $row );
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
   /*! \brief array of posts in this thread: [ post->id => ForumPost ]. */
   var $posts;
   /*! \brief Thread starter post [default=null]; null, if none found. */
   var $thread_post;

   /*! \brief ForumRead-object to be used to mark posts as read. */
   var $forum_read;
   /*! \brief Number of unread posts; -1=forum-read needs update. */
   var $count_new;

   function ForumThread( $forum_read=null )
   {
      $this->posts = array();
      $this->thread_post = null;
      $this->forum_read = $forum_read;
      $this->count_new = 0;
   }

   /*! \brief Returns post with given post-id from this ForumThread object; or NULL if not found. */
   function get_post( $pid )
   {
      return (isset($this->posts[$pid])) ? $this->posts[$pid] : NULL;
   }


   /*!
    * \brief Loads and adds posts (to posts-arr): query fields and FROM set,
    *        needs WHERE and ORDER in qsql2-arg QuerySQL; Needs fresh object-instance.
    * NOTE: Needs var forum_read set in this object!
    */
   function load_posts( $qsql2=null )
   {
      global $NOW;
      $qsql = ForumPost::build_query_sql();
      $qsql->merge( $qsql2 );

      $query = $qsql->get_select();
      $result = db_query( "ForumThread.load_posts", $query );

      $this->thread_post = null;
      while( $row = mysql_fetch_array( $result ) )
      {
         $post = ForumPost::new_from_row( $row );
         if( $post->parent_id == 0 )
            $this->thread_post = $post;

         $post->count_new = ( $this->forum_read->is_read_post($post) ) ? 0 : 1;
         if( $post->count_new > 0 )
         {
            $this->count_new++;
            $post->read_mark = "p{$post->id}.$NOW";
         }

         $this->posts[$post->id] = $post;
      }
      mysql_free_result($result);
   }

   /*!
    * \brief Loads and adds posts (to posts-arr), current active post stored
    *        in thread_post; Needs fresh object-instance.
    */
   function load_revision_history( $post_id )
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
      if( !$post->is_approved() )
      {
         $my_id = $player_row['ID'];
         if( ( $post->author->id != $my_id ) && !($player_row['admin_level'] & ADMIN_FORUM) )
            error('forbidden_post', "ForumThread.load_revision_history.check_viewer($post_id,$my_id)");
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

      while( $row = mysql_fetch_array( $result ) )
      {
         $post = ForumPost::new_from_row($row);
         $post->thread_no_link = true; // display-opt
         $this->posts[$post->id] = $post;
      }
      mysql_free_result($result);
   }

   /*! \brief Returns thread starter post or null, if none there; call load_posts-func before use. */
   function thread_post()
   {
      return $this->thread_post;
   }

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   function to_string()
   {
      $cnt = 0;
      $size = count($this->posts);
      $result = "ForumThread:\n";
      foreach( $this->posts as $post_id => $post )
         $result .= sprintf( "[%d/%d]. pid=[%s]: {%s}\n", ++$cnt, $size, $post_id, $post->to_string() );
      return $result;
   }

   /*!
    * \brief Builds data-structure for navigation within post-list.
    * param $set_in_posts if true, set navigation-links in ForumPosts in this object
    *
    * navtree[post_id] = map with keys: value=post-id or 0 (=no according node)
    *   prevP, nextP - prev/next-parent post
    *   prevA, nextA - prev/next-answer post for "parent"-thread
    *   child        - first-answer post
    * NOTE: order of post_id's in navtree is same as in posts of this ForumThread,
    *       but is expecting tree-sort by PosIndex
    */
   function create_navigation_tree( $set_in_posts=true )
   {
      $navtree = array();
      $last_parent_posts = array(); // [ parent_id => last_post_id in parent-thread ]
      $parent_children = array(); // [ parent_id => [ post_id1, post_id2, ... ] ]
      foreach( $this->posts as $post_id => $post )
      {
         $parent_id = $post->parent_id;
         $navmap = array();
         $navmap['prevP'] = $parent_id;
         $navmap['nextP'] = 0;
         $navmap['prevA'] = 0;
         $navmap['nextA'] = 0;
         $navmap['child'] = 0;

         $last_post_id = @$last_parent_posts[$parent_id];
         if( $last_post_id )
         {
            $navmap['prevA'] = $last_post_id;
            $navtree[$last_post_id]['nextA'] = $post_id;
         }

         if( $parent_id )
         {
            $last_parent_posts[$parent_id] = $post_id;
            $parent_children[$parent_id][] = $post_id;
         }
         $navtree[$post_id] = $navmap;
      }

      foreach( $parent_children as $parent_id => $children )
      {
         if( isset($navtree[$parent_id]) )
         {
            $navtree[$parent_id]['child'] = $children[0]; // children non-empty
            foreach( $children as $post_id )
               $navtree[$post_id]['nextP'] = $navtree[$parent_id]['nextA'];
         }
      }

      if( $set_in_posts )
      {
         foreach( $navtree as $post_id => $navmap )
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
   }

   // ---------- Static Class functions ----------------------------

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
   var $id;
   /*! \brief Posts.Forum_ID */
   var $forum_id;
   /*! \brief Posts.Thread_ID */
   var $thread_id;

   // Thread Meta

   /*! \brief Posts.PostsInThread */
   var $count_posts;
   /*! \brief Posts.Hits */
   var $count_hits;
   /*! \brief Posts.LastPost */
   var $last_post_id;

   // Post Meta & Content

   /*! \brief non-null ForumUser-object ( .id = Posts.User_ID ) */
   var $author;
   /*! \brief Posts.Subject */
   var $subject;
   /*! \brief Posts.Text */
   var $text;

   /*! \brief Posts.Parent_ID */
   var $parent_id;
   /*! \brief Posts.AnswerNr */
   var $answer_num;
   /*! \brief Posts.Depth */
   var $depth;
   /*! \brief Posts.PosIndex : string */
   var $posindex;

   /*! \brief Posts.Approved : string (DB=Y|N|P); use is_approved/is_pending_approval-funcs. */
   var $approved;

   /*! \brief Posts.Time */
   var $created;
   /*! \brief Posts.Lastchanged, SQL X_Lastchanged; date of last-post in thread */
   var $last_changed;
   /*! \brief Posts.Lastedited */
   var $last_edited;
   /*! \brief Posts.Updated (change-date for thread-forum-read) */
   var $updated;

   /*! \brief Posts.crc32 */
   var $crc32;
   /*! \brief Posts.old_ID */
   var $old_id;

   // non-db vars

   /*! \brief max of (created,last_edited) = GREATEST(Posts.Time,Posts.Lastedited); date of last-change of current post */
   var $last_updated;
   /*! \brief true, if for thread no link should be drawn (used in draw_post-func) [default=false] */
   var $thread_no_link;

   /*! \brief Count of new entries for a thread */
   var $count_new;
   /*! \brief command-arg to be used to mark post as read */
   var $read_mark;

   /*! \brief for forum-search: forum-name */
   var $forum_name;
   /*! \brief for forum-search: score */
   var $score;

   // tree-navigation (set by ForumThread::create_navigation_tree-func), NULL=not-set

   var $prev_parent_post;
   var $next_parent_post;
   var $prev_post;
   var $next_post;
   var $first_child_post;


   /*! \brief Constructs ForumPost-object with specified arguments: dates are in UNIX-time. */
   function ForumPost( $id=0, $forum_id=0, $thread_id=0, $author=null, $last_post_id=0,
         $count_posts=0, $count_hits=0, $subject='', $text='', $parent_id=0, $answer_num=0,
         $depth=0, $posindex='', $approved='Y',
         $created=0, $last_changed=0, $last_edited=0, $updated=0, $crc32=0, $old_id=0 )
   {
      $this->id = (int) $id;
      $this->forum_id = (int) $forum_id;
      $this->thread_id = (int) $thread_id;
      $this->count_posts = (int) $count_posts;
      $this->count_hits = (int) $count_hits;
      $this->last_post_id = (int) $last_post_id;
      $this->author = ( is_null($author) ? new ForumUser() : $author );
      $this->subject = $subject;
      $this->text = $text;
      $this->parent_id = (int) $parent_id;
      $this->answer_num = (int) $answer_num;
      $this->depth = (int) $depth;
      $this->posindex = $posindex;
      $this->approved = $approved;
      $this->created = (int) $created;
      $this->last_changed = (int) $last_changed;
      $this->last_edited = (int) $last_edited;
      $this->updated = (int) $updated;
      $this->crc32 = (int) $crc32;
      $this->old_id = (int) $old_id;
      // non-db
      $this->last_updated = max( $this->created, $this->last_edited );
      $this->thread_no_link = false;
      $this->count_new = -1; // unknown count
      $this->read_mark = '';
      $this->score = 0;
   }


   /*! \brief Returns true, if post is thread-post. */
   function is_thread_post()
   {
      return ( $this->id == $this->thread_id );
   }

   /*! \brief Returns true, if post is approved (Approved=Y). */
   function is_approved()
   {
      return ( $this->approved === 'Y' );
   }

   /*! \brief Returns true, if post is pending-approval (Approved=P). */
   function is_pending_approval()
   {
      return ( $this->approved === 'P' );
   }

   /*! \brief Sets tree-navigation vars for this post (NULL=not-set). */
   function set_navigation( $prev_parent_post, $next_parent_post, $prev_post, $next_post, $first_child_post )
   {
      $this->prev_parent_post = $prev_parent_post;
      $this->next_parent_post = $next_parent_post;
      $this->prev_post = $prev_post;
      $this->next_post = $next_post;
      $this->first_child_post = $first_child_post;
   }

   /*!
    * \brief Builds URL for forum-thread-post (without subdir-prefix) with specified anchor.
    * param anchor anchorname to link to; if null use current post-id
    */
   function build_url_post( $anchor=null, $url_suffix='' )
   {
      if( is_null($anchor) )
         $anchor = '#' . ((int)$this->id);
      elseif( (string)$anchor != '' )
         $anchor = '#' . ((string)$anchor);
      // else: anchor=''

      if( $url_suffix != '' && $url_suffix[0] != URI_AMP )
         $url_suffix = URI_AMP . $url_suffix;

      $url = sprintf( 'read.php?forum=%d'.URI_AMP.'thread=%d%s%s',
         $this->forum_id, $this->thread_id, $url_suffix, $anchor );
      return $url;
   }

   /*! \brief Builds link to this post for specified date using given anchor-attribs. */
   function build_link_postdate( $date, $attbs='' )
   {
     if( empty($date) )
         return NO_VALUE;

     $datestr = date( DATE_FMT, $date );
     return anchor( $this->build_url_post(), $datestr, '', $attbs );
   }

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   function to_string()
   {
      return "ForumPost(id={$this->id}): "
         . "forum_id=[{$this->forum_id}], "
         . "thread_id=[{$this->thread_id}], "
         . "#posts=[{$this->count_posts}], "
         . "#hits=[{$this->count_hits}], "
         . "last_post_id=[{$this->last_post_id}], "
         . "subject=[{$this->subject}], "
         . 'text..=[' . substr($this->text,0,30) . '..], '
         . 'author={' . ( is_null($this->author) ? 'null' : $this->author->to_string() ) . '}, '
         . "parent_id=[{$this->parent_id}], "
         . "answer#=[{$this->answer_num}], "
         . "depth=[{$this->depth}], "
         . "posidx=[{$this->posindex}], "
         . "approved=[{$this->approved}], "
         . "created=[{$this->created}], "
         . "last_changed=[{$this->last_changed}], "
         . "last_edited=[{$this->last_edited}], "
         . "updated=[{$this->updated}], "
         . "crc32=[{$this->crc32}], "
         . "old_id=[{$this->old_id}], "
         . "last_updated=[{$this->last_updated}], "
         . "forum_name=[{$this->forum_name}], "
         . "score=[{$this->score}]";
   }


   // ---------- Static Class functions ----------------------------

   /*! \brief Builds basic QuerySQL to load post(s). */
   function build_query_sql()
   {
      // Posts: ID,Forum_ID,Time,Lastchanged,Lastedited,Updated,Subject,Text,User_ID,Parent_ID,Thread_ID,
      //        AnswerNr,Depth,crc32,PosIndex,old_ID,Approved,PostsInThread,LastPost
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'P.*',
         'UNIX_TIMESTAMP(P.Time) AS X_Time',
         'UNIX_TIMESTAMP(P.Lastchanged) AS X_Lastchanged',
         'UNIX_TIMESTAMP(P.Lastedited) AS X_Lastedited',
         'UNIX_TIMESTAMP(P.Updated) AS X_Updated',
         'PAuthor.Name AS Author_Name', 'PAuthor.Handle AS Author_Handle',
         'PAuthor.Adminlevel+0 AS Author_AdminLevel' );
      $qsql->add_part( SQLP_FROM,
         'Posts AS P',
         'INNER JOIN Players AS PAuthor ON PAuthor.ID=P.User_ID' ); // Post-Author
      return $qsql;
   }

   /*! \brief Returns ForumPost-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $post = new ForumPost(
            @$row['ID'],
            @$row['Forum_ID'],
            @$row['Thread_ID'],
            // Author_* not part of Posts-table, but are read if set in row
            new ForumUser( @$row['User_ID'], @$row['Author_Name'], @$row['Author_Handle'], @$row['Author_AdminLevel'] ),
            @$row['LastPost'],
            @$row['PostsInThread'],
            @$row['Hits'],
            @$row['Subject'],
            @$row['Text'],
            @$row['Parent_ID'],
            @$row['AnswerNr'],
            @$row['Depth'],
            @$row['PosIndex'],
            @$row['Approved'],
            @$row['X_Time'],
            @$row['X_Lastchanged'],
            @$row['X_Lastedited'],
            @$row['X_Updated'],
            @$row['crc32'],
            @$row['old_ID']
         );
      return $post;
   }

   /*! \brief Returns M(=pending approval), H=hidden, S=shown for given approved value P|N|Y. */
   function get_approved_text( $approved )
   {
      if( $approved == 'P' )
         return 'M';
      elseif( $approved == 'N' )
         return 'H';
      else //if( $approved == 'Y' )
         return 'S';
   }

} // end of 'ForumPost'


 /*!
  * \brief Intermediate convenience class to represent user with User_ID, Name, Handle.
  * At the moment used as container to hold data to be able to create user-reference.
  * TODO: needs to be refactored into Players-class.
  */
class ForumUser
{
   var $id;
   var $name;
   var $handle;
   var $AdminLevel;

   /*! \brief Constructs a ForumUser with specified args. */
   function ForumUser( $id=0, $name='', $handle='', $admin_level=0 )
   {
      $this->id = (int) $id;
      $this->name = (string)$name;
      $this->handle = (string)$handle;
      $this->AdminLevel = (int)$admin_level;
   }

   /*! \brief Returns true, if user set (id != 0). */
   function is_set()
   {
      return ( is_numeric($this->id) && $this->id > 0 );
   }

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   function to_string()
   {
      return "ForumUser(id={$this->id}): "
         . "name=[{$this->name}], "
         . "handle=[{$this->handle}]"
         . "AdminLevel=[{$this->AdminLevel}]";
   }

   /*! \brief Returns user_reference for user in this object. */
   function user_reference()
   {
      $name = ( (string)$this->name != '' ) ? $this->name : UNKNOWN_VALUE;
      $handle = ( (string)$this->handle != '' ) ? $this->handle : UNKNOWN_VALUE;
      return user_reference( REF_LINK, 1, '', $this->id, $name, $handle );
   }

} // end of 'ForumUser'




// internal array-struct for ForumRead-entities
define('FR_TIME',  0);
define('FR_COUNT', 1);

// thread post-id for thread-new-count / thread-posts read-date
define('THPID_NEWCOUNT', 0);
define('THPID_READDATE', -1);

 /*!
  * \class ForumRead
  *
  * \brief Class to help with handling forum-reads and cope with 'new'-flag.
  * NOTE: Dates are stored in UNIX-time
  * NOTE: expected row-fields: Forum_ID, Thread_ID, Post_ID, FR_Count, FR_X_Time
  * NOTE: need combined index on IDs for correct working of update-funcs
  */
class ForumRead
{
   var $uid;
   var $fid;
   var $tid;
   var $reads; // key=fid,tid,pid => [ unix-time, count ]
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

   /*! \brief Sets read-date and count for specified forum, thread and post. */
   function set_read( $forum_id, $thread_id, $post_id, $date, $count=1 )
   {
      $this->reads["$forum_id,$thread_id,$post_id"] =
         array( FR_TIME => $date, FR_COUNT => $count );
   }

   /*!
    * \brief Returns true, if specified check-date is older than read-date
    *        stored for specified forum, thread and post.
    *        So is a read (and no "new") post.
    */
   function has_newer_read_date( $chkdate, $forum_id, $thread_id, $post_id )
   {
      $arr = @$this->reads["$forum_id,$thread_id,$post_id"];
      $result = ( is_array($arr) && $arr[FR_TIME] >= $chkdate );
      return $result;
   }

   /*! \brief Builds basic QuerySQL to load forum-reads. */
   function build_query_sql()
   {
      // ForumRead: User_ID, Forum_ID, Thread_ID, Post_ID, NewCount, Time
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS, 'FR.*', 'UNIX_TIMESTAMP(FR.Time) AS X_Time' );
      $qsql->add_part( SQLP_FROM, 'ForumRead AS FR' );
      $qsql->add_part( SQLP_WHERE, "FR.User_ID='{$this->uid}'" );
      return $qsql;
   }

   /*!
    * \brief Loads forum-reads for thread-post view into this ForumRead-object.
    */
   function load_reads_post()
   {
      $qsql = $this->build_query_sql();
      $qsql->add_part( SQLP_WHERE,
         'Forum_ID='.$this->fid,
         'Thread_ID='.$this->tid );
      $query = $qsql->get_select();
      $result = db_query( "ForumRead.load_reads_post({$this->uid},{$this->fid},{$this->tid})", $query );

      while( $row = mysql_fetch_array( $result ) )
      {
         $this->set_read( $row['Forum_ID'], $row['Thread_ID'], $row['Post_ID'],
            $row['X_Time'], $row['NewCount']);
      }
      mysql_free_result($result);
   }

   /*! \brief Returns true, if user has read specified ForumPost. */
   function is_read_post( $post )
   {
      // own posts are always read
      if( $post->author->id == $this->uid )
         return true;

      $chkdate = $post->created; // check against post creation-date
      // read, if date passed global read-date
      if( $this->min_date >= $chkdate )
         return true;

      // read, if date passed thread read-date
      if( $this->has_newer_read_date( $chkdate, $this->fid, $this->tid, THPID_READDATE ) )
         return true;

      // read, if date passed post read-date
      if( $this->has_newer_read_date( $chkdate, $this->fid, $this->tid, $post->id ) )
         return true;

      return false; // unread = new
   }

   /*! \brief Replaces ForumRead-db-entry with time and newcount for specified fid/tid/pid-key. */
   function replace_row_forumread( $dbgmsg, $fid, $tid, $pid, $time, $newcount )
   {
      // IMPORTANT NOTE:
      // - 'INSERT .. ON DUPLICATE UPDATE' need at least 5.0.38 for replication-bugfix,
      //   can also be used for multi-row inserts.
      // - REPLACE would be a combined DELETE + INSERT with full index-updating on both steps.
      db_query( $dbgmsg."({$this->uid},$fid,$tid,$pid,$newcount)",
         "INSERT INTO ForumRead (User_ID,Forum_ID,Thread_ID,Post_ID,NewCount,Time) "
            . "VALUES ({$this->uid},$fid,$tid,$pid,$newcount,FROM_UNIXTIME($time)) "
            . "ON DUPLICATE KEY UPDATE NewCount=VALUES(NewCount), Time=VALUES(Time)" );
   }

   /*!
    * \brief Marks forum/thread/posts as read and returns true on success.
    * param mark Syntax:
    *   p123.time - mark post id=123 with NOW/time
    *   pall.time - mark all thread-posts with NOW/time
    */
   function mark_read( $mark )
   {
      $out = array();
      if( !preg_match( "/^(p)(all|\d+)\.(\d+)$/", $mark, $out ) )
         error( 'invalid_args', "ForumRead.mark_read($mark)" );
      $type = $out[1];
      $id = $out[2];
      $time = $out[3];

      $result = false;
      if( $type === 'p' )
      {
         if( $id === 'all' )
            $result = $this->mark_thread_read( $this->tid, $time );
         else
            $result = $this->mark_post_read( $id, $time );
      }

      return $result;
   }

   /*!
    * \brief Marks post as read with specified time.
    * \see specs/forums.txt (use-case U10)
    */
   function mark_post_read( $post_id, $time )
   {
      $this->replace_row_forumread( "ForumRead.mark_post_read",
         $this->fid, $this->tid, $post_id, $time, -1 );
      $this->trigger_recalc_thread( $this->tid, $time );
      $this->trigger_recalc_forum( $this->fid, $time );
   }

   /*!
    * \brief Marks all posts in thread as read with specified time.
    * \see specs/forums.txt (use-case U11)
    */
   function mark_thread_read( $thread_id, $time )
   {
      $this->replace_row_forumread( "ForumRead.mark_thread_read",
         $this->fid, $thread_id, THPID_READDATE, $time, -1 );
      $this->trigger_recalc_thread( $thread_id, $time );
      $this->trigger_recalc_forum( $this->fid, $time );
   }

   /*! \brief Triggers recalc of thread new-count. */
   function trigger_recalc_thread( $thread_id, $time )
   {
      db_query( "ForumRead.trigger_recalc_thread({$this->uid},{$this->fid},$thread_id)",
         "UPDATE Posts SET Updated=GREATEST(Updated,FROM_UNIXTIME($time)) "
            . "WHERE ID='$thread_id' AND Parent_ID=0 LIMIT 1" );
   }

   /*! \brief Triggers recalc of forum new-count. */
   function trigger_recalc_forum( $forum_id, $time )
   {
      db_query( "ForumRead.trigger_recalc_forum({$this->uid},{$this->fid})",
         "UPDATE Forums SET Updated=GREATEST(Updated,FROM_UNIXTIME($time)) "
            . "WHERE ID='$forum_id' LIMIT 1" );
   }


   /*! \brief Recalculates new-count of forums. */
   function recalc_forum_reads( &$forums )
   {
      // recalc forum reads
      global $NOW;
      $queryfmt = 'SELECT SUM(NewCount) AS X_NewCount '
         . 'FROM ForumRead '
         . "WHERE User_ID={$this->uid} AND Forum_ID=%s " // fill in $fid
            . 'AND Thread_ID>0 AND Post_ID=0 AND NewCount>0';

      // NOTE: foreach does NOT work with array-reference (need PHP5)
      $cnt_forums = count($forums);
      for( $i=0; $i < $cnt_forums; $i++)
      {
         // Calculate fields for single thread if update needed
         $forum =& $forums[$i];
         if( $forum->count_new >= 0 )
            continue;
         $fid = $forum->id;

         // recalc forum threads
         $empty = array();
         $this->recalc_thread_reads( $empty, $fid );

         $dbgmsgfmt = "Forum.recalc_forum_reads.recalc_forum%s({$this->uid},$fid)";
         $row =
            mysql_single_fetch( sprintf($dbgmsgfmt,'1'), sprintf( $queryfmt, $fid ) )
               or error('mysql_query_failed', sprintf($dbgmsgfmt,'2') );

         // update
         $forum->count_new = @$row['X_NewCount'] + 0;
         $this->replace_row_forumread( 'Forum.recalc_forum_reads.update_forumread',
            $fid, 0, THPID_NEWCOUNT, $NOW, $forum->count_new );
      }
   }

   /*! \brief Recalculates new-count of threads. */
   function recalc_thread_reads( &$threads, $forum_id=0 )
   {
      // get threads, that needs to be updated (all or for specific forum)
      if( !is_array($threads) || count($threads) == 0 )
      {
         $qsql = new QuerySQL();
         $qsql->add_part( SQLP_FIELDS,
            'P.ID', 'P.Forum_ID',
            "IF(ISNULL(FR.User_ID),(P.Updated > FROM_UNIXTIME({$this->min_date})),"
               . "(FR.NewCount<0 OR P.Updated > FR.Time)) AS FR_NeedUpdate" );
         $qsql->add_part( SQLP_FROM,
            'Posts AS P',
            "LEFT JOIN ForumRead AS FR ON FR.User_ID='{$this->uid}' AND FR.Forum_ID=P.Forum_ID "
               . 'AND FR.Thread_ID=P.ID AND FR.Post_ID='.THPID_NEWCOUNT );
         $qsql->add_part( SQLP_WHERE,
            'P.Parent_ID=0', 'P.Thread_ID=P.ID', //=thread (better query with thread=id for ForumRead)
            'P.PostsInThread>0',
            "P.Lastchanged > FROM_UNIXTIME({$this->min_date})" );
         if( $forum_id > 0 )
            $qsql->add_part( SQLP_WHERE, 'P.Forum_ID='.$forum_id );
         $qsql->add_part( SQLP_HAVING,
            'FR_NeedUpdate>0' );

         $query = $qsql->get_select();
         $result = db_query( "ForumRead.recalc_thread_reads.read_threads({$this->uid},$forum_id)", $query );

         $threads = array();
         while( $row = mysql_fetch_array( $result ) )
         {
            $threads[] = new ForumPost( $row['ID'], $row['Forum_ID'], $row['ID'] );
         }
         mysql_free_result($result);
      }

      // recalc thread reads
      global $NOW;
      $queryfmt = 'SELECT SUM(ISNULL(FRT.Thread_ID) & ISNULL(FRP.Post_ID)) AS X_NewCount '
         . 'FROM Posts AS P '
         . 'LEFT JOIN ForumRead AS FRT ON '  // thread read-date
            . "FRT.User_ID={$this->uid} AND FRT.Forum_ID=P.Forum_ID "
            . "AND FRT.Thread_ID=P.Thread_ID AND FRT.Post_ID=-1 AND FRT.Time >= P.Time "
         . 'LEFT JOIN ForumRead AS FRP ON '  // post read
            . "FRP.User_ID={$this->uid} AND FRP.Forum_ID=P.Forum_ID "
            . "AND FRP.Thread_ID=P.Thread_ID AND FRP.Post_ID=P.ID AND FRP.Time >= P.Time "
         . "WHERE P.Forum_ID=%s AND P.Thread_ID=%s " // fill in $fid, $tid
            . "AND P.Approved='Y' "
            . "AND P.Time > FROM_UNIXTIME({$this->min_date}) "
            . "AND P.User_ID<>{$this->uid}";

      // NOTE: foreach does NOT work with array-reference (need PHP5)
      $cnt_threads = count($threads);
      for( $i=0; $i < $cnt_threads; $i++)
      {
         // Calculate fields for single thread if update needed
         $thread =& $threads[$i];
         if( $thread->count_new >= 0 )
            continue;
         $fid = $thread->forum_id;
         $tid = $thread->thread_id;

         $dbgmsgfmt = "Forum.recalc_thread_reads.recalc_thread%s({$this->uid},$fid,$tid)";
         $row =
            mysql_single_fetch( sprintf($dbgmsgfmt,'1'), sprintf( $queryfmt, $fid, $tid ) )
               or error('mysql_query_failed', sprintf($dbgmsgfmt,'2') );

         // update
         $thread->count_new = @$row['X_NewCount'] + 0;
         $this->replace_row_forumread( 'Forum.recalc_thread_reads.update_forumread',
            $fid, $tid, THPID_NEWCOUNT, $NOW, $thread->count_new );
      }
   }//recalc_thread_reads

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   function to_string()
   {
      $reads = '';
      foreach( $this->reads as $k => $arr )
         $reads .= "\n{ [$k]=[".$arr[FR_COUNT].','.$arr[FR_TIME]."] },";
      return "ForumRead(uid={$this->uid},fid={$this->fid},tid={$this->tid}): "
         . "min_date=[{$this->min_date}], "
         . $reads;
   }

   // ---------- Static Class functions ----------------------------

   function get_min_date()
   {
      global $NOW;
      return $NOW - FORUM_SECS_NEW_END;
   }

} // end of 'ForumRead'

?>
