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

require_once( "include/std_functions.php" );
require_once( 'include/std_classes.php' );
require_once( "include/form_functions.php" );
//require_once( "include/GoDiagram.php" );


define('NEW_LEVEL1', 2*7*24*3600);  // two weeks (also see SECS_NEW_END)

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
define("LINKPAGE_STATUS", 1 << 13);

define("LINK_MASKS", ~(LINKPAGE_READ | LINKPAGE_LIST | LINKPAGE_INDEX
          | LINKPAGE_SEARCH | LINKPAGE_STATUS) );



/*!
 * \brief Returns UNIX-time of last-read date for specified user and thread;
 *        NULL, if user hadn't read thread so far.
 */
function load_thread_last_read( $user_id, $thread_id )
{
   $arr = mysql_single_col(
      "Forum.load_last_read_thread($user_id,$thread_id)",
      'SELECT UNIX_TIMESTAMP(Time) AS Lastread FROM Forumreads '
         . "WHERE User_ID='$user_id' AND Thread_ID='$thread_id' LIMIT 1" );
   return ( is_array($arr) && count($arr) > 0 ) ? $arr[0] : NULL;
}


//GLOBAL
//TODO (message -> rename to approve_post)
function approve_message($id, $thread, $forum, $approve=true,
                         $approve_reject_pending_approval=false)
{
   if( $approve_reject_pending_approval )
   {
      $row = mysql_single_fetch( 'approve_message.find_post',
               "SELECT Approved FROM Posts " .
               "WHERE ID=$id AND Thread_ID=$thread LIMIT 1" )
         or error('unknown_post','approve_message.find_post');

      $Approved = ($row['Approved'] == 'Y');

      if( $Approved === $approve )
      {
         mysql_query("UPDATE Posts SET PendingApproval='N' " .
                     "WHERE ID=$id AND Thread_ID=$thread " .
                     "AND PendingApproval='Y' LIMIT 1")
            or error('mysql_query_failed','approve_message.pend_appr');
         return;
      }
   }

   $result = mysql_query("UPDATE Posts SET Approved='" . ( $approve ? 'Y' : 'N' ) . "', " .
                         "PendingApproval='N' " .
                         "WHERE ID=$id AND Thread_ID=$thread " .
                         "AND Approved='" . ( $approve ? 'N' : 'Y' ) . "' LIMIT 1")
      or error('mysql_query_failed','approve_message.set_approved');

   if( mysql_affected_rows() == 1 )
   {
      mysql_query("UPDATE Posts SET PostsInThread=GREATEST(0,PostsInThread" . ($approve ? '+1' : '-1') .
                  ") WHERE ID=$thread LIMIT 1")
         or error('mysql_query_failed','approve_message.set_postsinthread');

      mysql_query("UPDATE Forums SET PostsInForum=GREATEST(0,PostsInForum" . ($approve ? '+1' : '-1') .
                  ") WHERE ID=$forum LIMIT 1")
         or error('mysql_query_failed','approve_message.set_postsinforum');


      recalculate_lastpost($thread, $forum);
   }
}


//GLOBAL
//TODO
function recalculate_lastpost($Thread_ID, $Forum_ID)
{
   $result = mysql_query("SELECT ID, UNIX_TIMESTAMP(Time) AS Timestamp FROM Posts " .
                         "WHERE Thread_ID='$Thread_ID' AND Approved='Y' " .
                         "AND PosIndex>'' " . // '' == inactivated (edited)
                         "ORDER BY Time Desc LIMIT 1")
      or error('mysql_query_failed','recalculate_lastpost.find');

   if( @mysql_num_rows($result) == 1 )
   {
      $row = mysql_fetch_row($result);
      mysql_query("UPDATE Posts SET LastPost=" . $row[0] . ", " .
                  "LastChanged=FROM_UNIXTIME(" . $row[1] . ") " .
                  "WHERE ID=$Thread_ID LIMIT 1")
         or error('mysql_query_failed','recalculate_lastpost.update');
   }


   $result = mysql_query("SELECT Last.ID " .
                         "FROM Posts as Thread, Posts as Last " .
                         "WHERE Thread.LastPost=Last.ID AND " .
                         "Thread.Forum_ID=" . $Forum_ID . " AND Thread.Parent_ID=0 " .
                         "ORDER BY Last.Time DESC LIMIT 1")
      or error('mysql_query_failed','recalculate_lastpost.lastid');

   if( @mysql_num_rows($result) == 1 )
   {
      $row = mysql_fetch_row($result);
      mysql_query("UPDATE Forums SET LastPost=" . $row[0] . " WHERE ID=$Forum_ID LIMIT 1")
         or error('mysql_query_failed','recalculate_lastpost.lastpost');
   }
}


//GLOBAL
//TODO
function recalculate_postsinforum($Forum_ID)
{
   $result = mysql_query("SELECT COUNT(*), Thread_ID FROM Posts " .
                         "WHERE Forum_ID=$Forum_ID AND Approved='Y' GROUP BY Thread_ID")
      or error('mysql_query_failed','recalculate_postsinforum.find');

   $sum = 0;
   while( $row = mysql_fetch_row( $result ) )
   {
      $sum += $row[0];

      mysql_query("UPDATE Posts SET PostsInThread=" . $row[0] . " WHERE ID=" .$row[1])
      or error('mysql_query_failed','recalculate_postsinforum.postsintrhead');

      recalculate_lastpost($row[1], $Forum_ID);
   }

   mysql_query("UPDATE Forums SET PostsInForum=$sum WHERE ID=$Forum_ID")
      or error('mysql_query_failed','recalculate_postsinforum.postsinofrum');
}

//GLOBAL
//TODO
function display_posts_pending_approval()
{
   $result = mysql_query("SELECT UNIX_TIMESTAMP(Time) as Time,Subject,Forum_ID,Thread_ID, " .
                         "Posts.ID as Post_ID,Forums.Name AS Forumname," .
                         "User_ID,Players.Name as User_Name,Handle " .
                         "FROM (Posts,Players,Forums) " .
                         "WHERE PendingApproval='Y' AND Players.ID=User_ID AND Forums.ID=Forum_ID " .
                         "ORDER BY Time DESC")
      or error('mysql_query_failed','display_posts_pending_approval.find');

   if( mysql_num_rows($result) == 0 )
      return;

   $disp_forum = new DisplayForum( 0, false );
   $disp_forum->cols = $cols = 4;
   $disp_forum->headline = array( T_('Posts pending approval') => "colspan=$cols" );
   $disp_forum->links = LINKPAGE_STATUS;
   $disp_forum->forum_start_table('Pending');

   $odd = true;
   while( $row = mysql_fetch_array( $result ) )
   {
      $color = ( $odd ? "" : " bgcolor=white" );
      $odd = !$odd;

      $Subject = make_html_safe( $row['Subject'], SUBJECT_HTML);
      $post_href = 'forum/read.php?forum='.$row['Forum_ID'].URI_AMP.'thread='.$row['Thread_ID'].URI_AMP.'moderator=y#'.$row['Post_ID'];

      echo "<tr$color>"
         . '<td>' . ( $cols > 3 ? $row['Forumname'] . '</td><td>' : '' )
         . "<a href=\"$post_href\">$Subject</a></td><td>"
         . user_reference( REF_LINK, 1, '', $row['User_ID'], $row['User_Name'], $row['Handle'] )
         . '</td><td nowrap align=right>' . date(DATE_FMT, $row['Time']) . "</td></tr>\n";
   }
   mysql_free_result($result);
   $disp_forum->forum_end_table();
}





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
   var $new_count;
   var $back_post_id;
   var $cur_depth;
   var $show_score; // used for forum-search
   /*! \brief rx-terms (optionally array) that are to be highlighted in text. */
   var $rx_term;

   // consts
   var $page_rows;
   var $offset;
   var $fmt_new;

   /*! \brief Constructs display handler for forum-pages. */
   function DisplayForum( $user_id, $is_moderator, $forum_id=0, $thread_id=0 )
   {
      global $RowsPerPage;
      $this->user_id = $user_id;
      $this->is_moderator = $is_moderator;
      $this->forum_id = $forum_id;
      $this->thread_id = $thread_id;

      $this->cols = 1;
      $this->links = 0;
      $this->headline = array();
      $this->link_array_left = array();
      $this->link_array_right = array();
      $this->new_count = 0;
      $this->back_post_id = 0;
      $this->cur_depth = -1; // also set in forum_start_table-func
      $this->show_score = false;
      $this->rx_term = '';

      $this->page_rows = $RowsPerPage;
      $this->offset = 0;
      $this->fmt_new = '<span class="%s"><a name="%s%d" href="#new%d">%s</a></span>';
   }

   /*! \brief Setting rx-term (can be array or string). */
   function set_rx_term( $rx_term='' )
   {
      // highlight terms (skipping XML-elements like tags & entities)
      if( is_array($rx_term) && count($rx_term) > 0 )
         $this->rx_term = implode('|', $rx_term);
      else if( !is_string($rx_term) )
         $this->rx_term = '';
      else
         $this->rx_term = $rx_term;
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
   function forum_start_table( $table_id, $max_rows=MAXROWS_PER_PAGE_DEFAULT, $ReqParam = null)
   {
      echo "<a name=\"ftop\">\n",
           "<table id='forum$table_id' class=Forum>\n";
      $this->make_link_array( $max_rows, $ReqParam );

      if( $this->links & LINK_MASKS )
         $this->echo_links('T');

      $this->print_headline();

      $this->cur_depth = -1;
   }

   function print_headline( $headline=NULL )
   {
      if ( is_null($headline) )
         $headline = $this->headline;

      echo "<tr class=Caption>";
      foreach( $headline as $name => $attbs )
         echo "<td $attbs>$name</td>";
      echo "</tr>\n";
   }

   function forum_end_table()
   {
      if( $this->links & LINK_MASKS )
         $this->echo_links('B');
      echo "</table>\n<a name=\"fbottom\">\n";
   }

   // param ReqParam: optional object RequestParameters containing URL-parts to be included for paging
   function make_link_array( $max_rows, $ReqParam = null )
   {
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
      if( $links & LINK_SEARCH )
         $this->link_array_left[T_('Search')] = "search.php";

      if( $links & LINK_MARK_READ )
         $this->link_array_left[T_('Mark All Read')] = ''; //TODO

      $navi_url = '';
      if ( !is_null($ReqParam) && ($links & (LINKPAGE_SEARCH|LINK_PREV_PAGE|LINK_NEXT_PAGE)) )
         $navi_url = $ReqParam->get_url_parts();

      if( $links & LINK_TOGGLE_MODERATOR )
      {
         $get = array_merge( $_GET, $_POST);
         $get['moderator'] = ( empty($this->is_moderator) ? 'y' : 'n' );
         if( $links & LINKPAGE_READ )
            $url = make_url( "read.php", $get, false );
         else if ( $links & LINKPAGE_LIST )
            $url = make_url( "list.php", $get, false );
         else if ( $links & LINKPAGE_SEARCH )
            $url = make_url( "search.php", $get, false );
         else
            $url = make_url( "index.php", $get, false );
         $this->link_array_right[T_("Toggle forum moderator")] = $url;
      }

      if( $links & LINK_PREV_PAGE )
      {
         if( $links & LINKPAGE_SEARCH )
            $href = "search.php?{$navi_url}".URI_AMP."offset=".($this->offset - $max_rows);
         else
            $href = "list.php?forum=$fid".URI_AMP."offset=".($this->offset - $this->page_rows);
         $this->link_array_right[T_("Prev Page")] =
            array( $href, '', array( 'accesskey' => ACCKEY_ACT_PREV ) );
      }
      if( $links & LINK_NEXT_PAGE )
      {
         if( $links & LINKPAGE_SEARCH )
            $href = "search.php?{$navi_url}".URI_AMP."offset=".($this->offset + $max_rows);
         else
            $href = "list.php?forum=$fid".URI_AMP."offset=".($this->offset + $this->page_rows);
         $this->link_array_right[T_("Next Page")] =
            array( $href, '', array( 'accesskey' => ACCKEY_ACT_NEXT ) );
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
      echo $this->get_new_string('bottom', 0);

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

   // param: Lastchangedstamp : date or 'bottom'
   // param: Lastread : date or empty
   function get_new_string( $Lastchangedstamp, $Lastread, $anchor_prefix='new' )
   {
      $new = '';
      if( $Lastchangedstamp === 'bottom' )
      {
         if( $this->new_count > 0 )
            $new = sprintf( $this->fmt_new, 'NewFlag', $anchor_prefix,
               $this->new_count + 1, 1, T_('first new') );
      }
      else
      {
         global $NOW;
         if( (empty($Lastread) || $Lastchangedstamp > $Lastread)
               && $Lastchangedstamp + SECS_NEW_END > $NOW )
         {
            $this->new_count++;
            if( $Lastchangedstamp + NEW_LEVEL1 > $NOW )
               $class = 'NewFlag'; //recent 'new'
            else
               $class = 'OlderNewFlag'; //older 'new'
            $new = sprintf( $this->fmt_new, $class, $anchor_prefix,
               $this->new_count, $this->new_count + 1, T_('new') );
         }
      }
      return $new;
   }

   function forum_message_box( $postClass, $post_id, $GoDiagrams=null, $Subject='', $Text='')
   {
      // reply-prefix
      if( $postClass != 'Edit' && $postClass != 'Preview'
         && strlen($Subject) > 0 && strcasecmp(substr($Subject,0,3), "re:") != 0 )
            $Subject = "RE: " . $Subject;

      $form = new Form( 'messageform', "read.php#preview", FORM_POST );

      $form->add_row( array(
            'DESCRIPTION', T_('Subject'),
            'TEXTINPUT', 'Subject', 50, 80, $Subject,
            'HIDDEN', ($postClass == 'Edit' ? 'edit' : 'parent'), $post_id,
            'HIDDEN', 'thread', $this->thread_id,
            'HIDDEN', 'forum', $this->forum_id ));
      $form->add_row( array(
            'TAB', 'TEXTAREA', 'Text', 70, 25, $Text ));

      /*
      if( isset($GoDiagrams) )
         $str = draw_editors($GoDiagrams);

      if( !empty($str) )
      {
         $form->add_row( array( 'OWNHTML', '<td colspan=2>' . $str . '</td>'));
         $form->add_row( array( 'OWNHTML', '<td colspan=2 align="center">' .
               //review accesskey:
               '<input type="submit" name="post" accesskey="'.ACCKEY_ACT_EXECUTE.'" onClick="dump_all_data(\'messageform\');" value=" ' . T_('Post') . " \">\n" .
               '<input type="submit" name="preview" accesskey="'.ACCKEY_ACT_PREVIEW.'" onClick="dump_all_data(\'messageform\');" value=" ' . T_('Preview') . " \">\n" .
               "</td>\n" ));
      }
      else
      */
      {
         $form->add_row( array(
               'SUBMITBUTTONX', 'post',    ' ' . T_('Post') . ' ',    array( 'accesskey' => ACCKEY_ACT_EXECUTE ),
               'SUBMITBUTTONX', 'preview', ' ' . T_('Preview') . ' ', array( 'accesskey' => ACCKEY_ACT_PREVIEW ) ));
      }

      $form->echo_string(1);
   }


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
         case 1: break;

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


   //TODO: refactor (don't control logic with style-var), also see forum/read.php & forum-search (moderator-stuff)
   // param postClass: no '_' because used as sub-class name => CSS compliance,
   //                  values: 'Normal', 'Hidden', 'Reply', 'Preview', 'Edit', 'SearchResult'
   // param post: ForumPost-object to draw
   function draw_post($postClass, $post, $is_my_post, $GoDiagrams=null )
   {
      global $NOW, $player_row;

      // post-vars needed:
      //    id, forum_id, thread_id, parent_id, subject, text, author(id,name,handle),
      //    pending_approval, last_updated, last_edited, last_read
      // post-vars only needed for forum-search: forum_name, score; this->show_score

      $pid = $post->id;
      $thread_url = $post->build_url_post(''); //post_url ended by #$pid
      $term_url = ( $this->rx_term != '' ) ? URI_AMP."xterm=".urlencode($this->rx_term) : '';

      $post_reference = '';
      $cols = 2; //one for the subject header, one for the possible approved/hidden state

      // highlight terms in Subject/Text
      $sbj = make_html_safe( $post->subject, SUBJECT_HTML, $this->rx_term );
      $txt = make_html_safe( $post->text, true, $this->rx_term );
//      $txt = replace_goban_tags_with_boards($txt, $GoDiagrams);

      if( strlen($txt) == 0 ) $txt = '&nbsp;';

      // post header
      $hdrclass = 'PostHead'.$postClass;
      if( $postClass == 'Preview' )
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
         if( $postClass == 'SearchResult' )
         {
            $hdrrows = 3;
            $hdrcols = $cols;

            echo "\n<tr class=\"$hdrclass FoundForum\"><td class=FoundForum colspan=$hdrcols>";
            echo '<span class=FoundForum>' ,T_('found in forum')
               ,' <a href="list.php?forum='
               ,$post->forum_id ,'">' ,$post->forum_name ,"</a></span>\n";
            if( $this->show_score )
               echo ' <span class=FoundScore>' ,T_('with')
                  ,' <span>' ,T_('Score') ,' ' ,$post->score ,"</span></span>\n";
            echo '</td></tr>';
            echo "\n<tr class=\"$hdrclass Subject\"><td class=Subject colspan=$hdrcols>";
            echo '<a class=PostSubject href="', $thread_url, $term_url, "#$pid\">$sbj</a>";
            echo "</td></tr>";
         }
         else
         {
            $hdrrows = 2;
            if( $postClass == 'Hidden' )
               $hdrcols = $cols-1; //because of the rowspan=$hdrrows in the second column
            else
               $hdrcols = $cols;
            $new = $this->get_new_string($post->created, $post->last_read);

            echo "\n<tr class=\"$hdrclass Subject\"><td class=Subject colspan=$hdrcols>";

            //from revision_history or because, when edited, the link will be obsolete
            if( $postClass == 'Edit' || $post->thread_no_link )
               echo "<a class=PostSubject name=\"$pid\">$sbj</a>";
            else
               echo '<a class=PostSubject href="', $thread_url, $term_url
                  ,"#$pid\" name=\"$pid\">$sbj</a>$new";

            if( $hdrcols != $cols )
            {
               echo "</td>\n <td rowspan=$hdrrows class=PostStatus>";
               echo ( $post->pending_approval ? T_('Awaiting<br>approval') : T_('Hidden') );
            }
            echo "</td></tr>";
         }

         echo "\n<tr class=\"$hdrclass Author\"><td class=Author colspan=$hdrcols>";

         $post_reference = date(DATE_FMT, $post->created);
         echo T_('by') ,' ' , $post->author->user_reference()
            ," &nbsp;&nbsp;&nbsp;" ,$post_reference;

         echo $this->get_post_edited_string( $post );
         if( $post->last_edited > 0 )
            $post_reference = date(DATE_FMT, $post->last_edited);

         echo "</td></tr>\n";

         $post_reference = "<user {$post->author->id}> ($post_reference):";
      }

      // post body
      echo "\n<tr class=PostBody><td colspan=$cols>$txt</td></tr>";

      // bottom line (footer)
      if( $postClass == 'Normal' || $postClass == 'Hidden' )
      {
         $hidden = $postClass == 'Hidden';
         echo "\n<tr class=PostButtons><td colspan=$cols>";

         global $base_path;
         $img_top         = image( $base_path.'images/start.gif',    T_('Top'), T_('Top') );
         $img_prev_parent = image( $base_path.'images/backward.gif', T_('Previous parent'), T_('Previous parent') );
         $img_prev_answer = image( $base_path.'images/prev.gif',     T_('Previous answer'), T_('Previous answer') );
         $img_next_answer = image( $base_path.'images/next.gif',     T_('Next answer'), T_('Next answer') );
         $img_next_parent = image( $base_path.'images/forward.gif',  T_('Next parent'), T_('Next parent') );
         $img_bottom      = image( $base_path.'images/end.gif',      T_('Bottom'), T_('Bottom') );

         //TODO: very strange: insert_width() does not work, resulting in line-breaks :(
         $prev_parent = ( is_null($post->prev_parent_post) )
            ? '&nbsp;&nbsp;' //insert_width(18)
            : anchor( $post->prev_parent_post->build_url_post( null, $term_url ), $img_prev_parent );
         $next_parent = ( is_null($post->next_parent_post) )
            ? '&nbsp;&nbsp;' //insert_width(18)
            : anchor( $post->next_parent_post->build_url_post( null, $term_url ), $img_next_parent );
         $prev_answer = ( is_null($post->prev_post) )
            ? '&nbsp;&nbsp;' //insert_width(13)
            : anchor( $post->prev_post->build_url_post( null, $term_url ), $img_prev_answer );
         $next_answer = ( is_null($post->next_post) )
            ? '&nbsp;&nbsp;' //insert_width(13)
            : anchor( $post->next_post->build_url_post( null, $term_url ), $img_next_answer );

         // BEGIN Navi (top/prev-parent/prev-answer)
         echo anchor( "$thread_url$term_url#ftop", $img_top )
            . '&nbsp;'
            . $prev_parent
            . '&nbsp;'
            . $prev_answer
            . '&nbsp;&nbsp;';

         if( $postClass == 'Normal' && !$this->is_moderator ) // reply link
         {
            echo '<a href="'.$thread_url
               .URI_AMP."reply=$pid#$pid\">[ " .
               T_('reply') . " ]</a>&nbsp;&nbsp;";
            if( ALLOW_QUOTING )
            echo '<a href="'.$thread_url
               .URI_AMP."quote=1"
               .URI_AMP."reply=$pid#$pid\">[ " .
               T_('quote') . " ]</a>&nbsp;&nbsp;";
         }
         if( $is_my_post && !$this->is_moderator ) // edit link
         {
            echo '<a class=Highlight href="'.$thread_url
               .URI_AMP."edit=$pid#$pid\">"
               ."[ " . T_('edit') . " ]</a>&nbsp;&nbsp;";
         }

         // END Navi (next-answer/next-parent/bottom)
         echo $next_answer
            . '&nbsp;'
            . $next_parent
            . '&nbsp;'
            . anchor( "$thread_url$term_url#fbottom", $img_bottom )
            . '&nbsp;&nbsp;';

         if( $this->is_moderator ) // hide/show link
         {
            if( !$post->pending_approval )
               echo '<a class=Highlight href="'.$thread_url
                  .URI_AMP . ($hidden ?'show' :'hide') . "=$pid#$pid\">"
                  ."[ " . ($hidden ?T_('show') :T_('hide')) . " ]</a>";
            else
               echo '<a class=Highlight href="'.$thread_url
                  .URI_AMP."approve=$pid#$pid\">"
                  ."[ " . T_('Approve') . " ]</a>&nbsp;&nbsp;"
                  .'<a class=Highlight href="'.$thread_url
                  .URI_AMP."reject=$pid#$pid\">"
                  ."[ " . T_('Reject') . " ]</a>";
         }
         echo "</td></tr>\n";
      }

      return $post_reference;
   } //draw_post

   /*! \brief Draw tree-overview for this thread. */
   function draw_overview( $fthread, $last_read )
   {
      global $base_path;
      $this->new_count = 0;
      $this->change_depth( 1 );

      // draw for post: subject, author, date
      foreach( $fthread->posts as $pid => $post )
      {
         $sbj = make_html_safe( $post->subject, SUBJECT_HTML, $this->rx_term );
         $hdrcols = 1; //TODO handle moderator-state

         echo "\n<tr class=\"TreePostNormal\"><td class=TreePostRow colspan=$hdrcols>",
            str_repeat( '&nbsp;', 3*($post->depth - 1) ),
            anchor( $post->build_url_post(), $sbj, '', 'class=PostSubject' ),
            sprintf( ' <span class=PostUser>%s %s</span>',
               T_('by'), $post->author->user_reference() ),
            sprintf( '<span class=PostDate>, %s%s</span>',
               date( DATE_FMT, $post->created ),
               $this->get_post_edited_string( $post ) ),
            $this->get_new_string( $post->created, $last_read, 'treenew' ),
            "</td></tr>";
      }

      $this->new_count = 0;
      $this->change_depth( -1 );
   } //draw_overview

   function get_post_edited_string( $post )
   {
      if( $post->last_edited > 0 )
         $result = sprintf( '&nbsp;&nbsp;&nbsp;(<a href="%s">%s</a> %s)',
            $post->build_url_post( '', URI_AMP.'revision_history='.$post->id ),
            T_('edited'),
            date( DATE_FMT, $post->last_edited ) );
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
   /*! \brief Forum.ID : id */
   var $id;
   /*! \brief Forum.Name : str */
   var $name;
   /*! \brief Forum.Description : str */
   var $description;
   /*! \brief Forum.LastPost : id (=Posts.ID) */
   var $last_post_id;
   /*! \brief Forum.ThreadsInForum : int */
   var $count_threads;
   /*! \brief Forum.PostsInForum : int */
   var $count_posts;
   /*! \brief Forum.SortOrder : int */
   var $sort_order;
   /*! \brief Forum.Moderated : char (Y|N) */ //TODO: should be enum in db
   var $moderated;

   /*! \brief partly filled ForumPost-object for last_post_id [default=null] */
   var $last_post;
   /*! \brief array of ForumThread-objects [default=null] */
   var $threads;
   /*! \brief true, if there are more threads to page-navigate. */
   var $navi_more_threads;


   /*! \brief Constructs Forum with specified args. */
   function Forum( $id=0, $name='', $description='', $last_post_id=0,
         $count_threads=0, $count_posts=0, $sort_order=0, $moderated='N' )
   {
      $this->id = $id;
      $this->name = $name;
      $this->description = $description;
      $this->last_post_id = $last_post_id;
      $this->count_threads = $count_threads;
      $this->count_posts = $count_posts;
      $this->sort_order = $sort_order;
      $this->moderated = $moderated;
      // non-db
      $this->last_post = null;
      $this->threads = null;
   }

   /*! \brief Returns true, if forum is moderated. */
   function is_moderated()
   {
      return ( $this->moderated === 'Y' );
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
         . "moderated=[{$this->moderated}], "
         . 'last_post={' . ( is_null($this->last_post) ? '' : $this->last_post->to_string() ) . '}';
   }

   /*!
    * \brief Loads threads for current forum into this object.
    * \return count of loaded rows
    */
   function load_threads( $user_id, $is_moderator, $show_rows, $offset=0 )
   {
      if ( !is_numeric($user_id) )
         error('invalid_user', "Forum.load_threads($user_id)");
      if ( !is_numeric($show_rows) || !is_numeric($offset) )
         error('invalid_args', "Forum.load_threads(show_rows=$show_rows,offset=$offset)");

      if ( !is_numeric($this->id) )
         error('unknown_forum', "Forum.load_threads(forum_id={$this->id})");

      $qsql = ForumPost::build_query_sql();
      $qsql->add_part( SQLP_FIELDS,
         'LPAuthor.ID AS LPAuthor_ID',
            'LPAuthor.Name AS LPAuthor_Name',
            'LPAuthor.Handle AS LPAuthor_Handle',
         'UNIX_TIMESTAMP(FR.Time) AS Lastread' );
      $qsql->add_part( SQLP_FROM,
         'LEFT JOIN Posts AS LP ON LP.ID=P.LastPost',  // LastPost
            'INNER JOIN Players AS LPAuthor ON LPAuthor.ID=LP.User_ID', // LastPost-Author
         'LEFT JOIN Forumreads FR ON FR.User_ID=' . $user_id . ' AND FR.Thread_ID=P.Thread_ID' );
      $qsql->add_part( SQLP_WHERE,
         "P.Forum_ID='" . (int)$this->id. "'",
         'P.Parent_ID=0' );
      if ( !$is_moderator )
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
         $thread->forum_id = $this->id;
         $thread->last_post =
            new ForumPost( $thread->last_post_id, $this->id, $thread->thread_id,
               new ForumUser( $row['LPAuthor_ID'], $row['LPAuthor_Name'], $row['LPAuthor_Handle'] ) );
         $thread->last_read = $row['Lastread'];

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
   }

   /*! \brief Use after call of load_threads() to check, if there are more threads to load. */
   function has_more_threads()
   {
      return $this->navi_more_threads;
   }

   // ---------- Static Class functions ----------------------------

   /*! \brief Returns db-fields to be used for query of Forum-object. */
   function build_query_sql()
   {
      // Forums: ID,Name,Description,LastPost,ThreadsInForum,PostsInForum,SortOrder,Moderated
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
            @$row['Moderated']
         );
      return $forum;
   }

   /*!
    * \brief Returns non-null Forum-object for specified forum-id.
    * Throws errors if forum cannot be found.
    */
   function load_forum( $id )
   {
      if ( !is_numeric($id) || $id <= 0 )
         error('unknown_forum', "load_forum($id)");

      $qsql = Forum::build_query_sql();
      $qsql->add_part( SQLP_WHERE, "ID='$id'" );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $query = $qsql->get_select();
      $row = mysql_single_fetch( "forum.load_forum2($id)", $query );
      if( !$row )
         error('unknown_forum', "load_forum3($id)");

      return Forum::new_from_row( $row );
   }

   /*!
    * \brief Returns array of Forum-objects for specified user-id;
    *        returns null if no feature found.
    */
   function load_forum_list( $user_id )
   {
      if ( !is_numeric($user_id) )
         error('invalid_user', "Forum.build_query_forum_list($user_id)");

      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'Forums.*',
         'LP.Thread_ID AS LP_Thread',
         'UNIX_TIMESTAMP(LP.Time) AS LP_Time',
         'LP.User_ID AS LP_User_ID',
         'P.Name AS LP_Name',
         'P.Handle AS LP_Handle' );
      $qsql->add_part( SQLP_FROM,
         'Forums',
         'LEFT JOIN Posts AS LP ON Forums.LastPost=LP.ID',
         'LEFT JOIN Players AS P ON P.ID=LP.User_ID' );
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

         $flist[] = $forum;
      }
      mysql_free_result($result);

      return $flist;
   }

   /*! \brief Returns array of partial Forum-objects with id and name set. */
   function load_forum_names()
   {
      // build forum-array for filter: ( Name => Forum_ID )
      $fnames = mysql_single_col( 'Forum.load_forum_names()',
         'SELECT ID, Name FROM Forums ORDER BY SortOrder', true );
      return $fnames;
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

   function ForumThread()
   {
      $this->posts = array();
      $this->thread_post = null;
   }


   /*!
    * \brief Loads and adds posts (to posts-arr): query fields and FROM set,
    *        needs WHERE and ORDER in qsql2-arg QuerySQL; Needs fresh object-instance.
    */
   function load_posts( $qsql2=null )
   {
      $qsql = ForumPost::build_query_sql();
      $qsql->merge( $qsql2 );

      $query = $qsql->get_select();
      $result = db_query( "ForumThread.load_posts()", $query );

      $this->thread_post = null;
      while( $row = mysql_fetch_array( $result ) )
      {
         $post = ForumPost::new_from_row( $row );
         if ( $post->parent_id == 0 )
            $this->thread_post = $post;
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
      global $NOW;

      // select current active post
      $qsql = ForumPost::build_query_sql();
      $qsql->add_part( SQLP_WHERE, "P.ID='$post_id'" );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "ForumThread.load_revision_history.find_post($post_id)", $qsql->get_select() )
         or error('unknown_post', "ForumThread.load_revision_history.find_post2($post_id)");

      $post = ForumPost::new_from_row($row);
      $post->last_read = $NOW;
      $this->thread_post = $post;

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
         $post->last_read = $NOW;
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
    * NOTE: order of post_id's in navtree is same as in posts of this ForumThread
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
      }

      foreach( $parent_children as $parent_id => $children )
      {
         foreach( $children as $post_id )
         {
            $navtree[$post_id]['nextP'] =
               ( isset($navtree[$parent_id]) )
                  ? $navtree[$parent_id]['nextA']
                  : 0;
         }
      }

      if ( $set_in_posts )
      {
         foreach( $navtree as $post_id => $navmap )
         {
            $this->posts[$post_id]->set_navigation(
               ( $navmap['prevP'] ) ? $this->posts[$navmap['prevP']] : NULL,
               ( $navmap['nextP'] ) ? $this->posts[$navmap['nextP']] : NULL,
               ( $navmap['prevA'] ) ? $this->posts[$navmap['prevA']] : NULL,
               ( $navmap['nextA'] ) ? $this->posts[$navmap['nextA']] : NULL
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

   /*! \brief Posts.Approved : bool (DB=Y|N) */
   var $approved;
   /*! \brief Posts.PendingApproval : bool (DB=Y|N) */
   var $pending_approval;

   /*! \brief Posts.Time */
   var $created;
   /*! \brief Posts.Lastchanged, SQL X_Lastchanged; date of last-post in thread */
   var $last_changed;
   /*! \brief Posts.Lastedited */
   var $last_edited;

   /*! \brief Posts.crc32 */
   var $crc32;
   /*! \brief Posts.old_ID */
   var $old_id;

   // non-db vars

   /*! \brief max of (created,last_edited) = GREATEST(Posts.Time,Posts.Lastedited); date of last-change of current post */
   var $last_updated;
   /*! \brief last-read of user of this post [default=0] */
   var $last_read;
   /*! \brief true, if for thread no link should be drawn (used in draw_post-func) [default=false] */
   var $thread_no_link;

   /*! \brief for forum-search: forum-name */
   var $forum_name;
   /*! \brief for forum-search: score */
   var $score;

   // tree-navigation (set by ForumThread::create_navigation_tree-func), NULL=not-set

   var $prev_parent_post;
   var $next_parent_post;
   var $prev_post;
   var $next_post;


   /*! \brief Constructs ForumPost-object with specified arguments: dates are in UNIX-time. */
   function ForumPost( $id=0, $forum_id=0, $thread_id=0, $author=null, $last_post_id=0,
         $count_posts=0, $count_hits=0, $subject='', $text='', $parent_id=0, $answer_num=0,
         $depth=0, $posindex='', $approved='Y', $pending_approval='N',
         $created=0, $last_changed=0, $last_edited=0, $crc32=0, $old_id=0 )
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
      $this->set_approved( $approved );
      $this->set_pending_approval( $pending_approval );
      $this->created = (int) $created;
      $this->last_changed = (int) $last_changed;
      $this->last_edited = (int) $last_edited;
      $this->crc32 = (int) $crc32;
      $this->old_id = (int) $old_id;
      // non-db
      $this->last_updated = max( $this->created, $this->last_edited );
      $this->last_read = 0;
      $this->thread_no_link = false;
      $this->score = 0;
   }


   /*! \brief Correctly sets approved-field to boolean-value (input=enum(Y|N) from db). */
   function set_approved( $val )
   {
      $this->approved = ( is_string($val) ? ( (string)$val === 'Y' ) : (bool)$val );
   }

   /*! \brief Correctly sets pending_approval-field to boolean-value (input=enum(Y|N) from db). */
   function set_pending_approval( $val )
   {
      $this->pending_approval = ( is_string($val) ? ( (string)$val === 'Y' ) : (bool)$val );
   }

   /*! \brief Sets tree-navigation vars for this post (NULL=not-set). */
   function set_navigation( $prev_parent_post, $next_parent_post, $prev_post, $next_post )
   {
      $this->prev_parent_post = $prev_parent_post;
      $this->next_parent_post = $next_parent_post;
      $this->prev_post = $prev_post;
      $this->next_post = $next_post;
   }

   /*!
    * \brief Builds URL for forum-thread-post (without subdir-prefix) with specified anchor.
    * param anchor anchorname to link to; if null use current post-id
    */
   function build_url_post( $anchor=null, $url_suffix='' )
   {
      if ( is_null($anchor) )
         $anchor = '#' . ((int)$this->id);
      else if ( (string)$anchor != '' )
         $anchor = '#' . ((string)$anchor);
      // else: anchor=''

      $url = sprintf( 'read.php?forum=%d'.URI_AMP.'thread=%d%s%s',
         $this->forum_id, $this->thread_id, $url_suffix, $anchor );
      return $url;
   }

   /*! \brief Builds link to this post for specified date using given anchor-attribs. */
   function build_link_postdate( $date, $attbs='' )
   {
     if ( empty($date) )
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
         . "pendapprov=[{$this->pending_approval}], "
         . "created=[{$this->created}], "
         . "last_changed=[{$this->last_changed}], "
         . "last_edited=[{$this->last_edited}], "
         . "crc32=[{$this->crc32}], "
         . "old_id=[{$this->old_id}], "
         . "last_updated=[{$this->last_updated}], "
         . "last_read=[{$this->last_read}], "
         . "forum_name=[{$this->forum_name}], "
         . "score=[{$this->score}]";
   }


   // ---------- Static Class functions ----------------------------

   /*! \brief Builds basic QuerySQL to load post(s). */
   function build_query_sql()
   {
      // Posts: ID,Forum_ID,Time,Lastchanged,Lastedited,Subject,Text,User_ID,Parent_ID,Thread_ID,
      //        AnswerNr,Depth,crc32,PosIndex,old_ID,Approved,PostsInThread,LastPost,PendingApproval
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'P.*',
         'UNIX_TIMESTAMP(P.Time) AS X_Time',
         'UNIX_TIMESTAMP(P.Lastchanged) AS X_Lastchanged',
         'UNIX_TIMESTAMP(P.Lastedited) AS X_Lastedited',
         'PAuthor.Name AS Author_Name', 'PAuthor.Handle AS Author_Handle' );
      $qsql->add_part( SQLP_FROM,
         'Posts AS P',
         'LEFT JOIN Players AS PAuthor ON PAuthor.ID=P.User_ID' ); // Post-Author
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
            new ForumUser( @$row['User_ID'], @$row['Author_Name'], @$row['Author_Handle'] ),
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
            @$row['PendingApproval'],
            @$row['X_Time'],
            @$row['X_Lastchanged'],
            @$row['X_Lastedited'],
            @$row['crc32'],
            @$row['old_ID']
         );
      return $post;
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

   /*! \brief Constructs a ForumUser with specified args. */
   function ForumUser( $id=0, $name='', $handle='' )
   {
      $this->id = (int) $id;
      $this->name = (string)$name;
      $this->handle = (string)$handle;
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
         . "handle=[{$this->handle}]";
   }

   /*! \brief Returns user_reference for user in this object. */
   function user_reference()
   {
      $name = ( (string)$this->name != '' ) ? $this->name : UNKNOWN_VALUE;
      $handle = ( (string)$this->handle != '' ) ? $this->handle : UNKNOWN_VALUE;
      return user_reference( REF_LINK, 1, '', $this->id, $name, $handle );
   }

} // end of 'ForumUser'

?>
