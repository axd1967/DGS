<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

$TranslateGroups[] = "Forum"; //local use

chdir("../");
require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );
//require_once( "include/GoDiagram.php" );
chdir("forum/");


//$new_end =  4*7*24*3600;  // four weeks //moved to quick_common.php

$new_level1 = 2*7*24*3600;  // two weeks
$new_count = 0;

// must follow the "ORDER BY PosIndex" order: 
$order_str = "*+-/0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";

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


define('ALLOW_QUOTING', 0);
define('FORUM_MAXIMUM_DEPTH', 15);


// param ReqParam: optional object RequestParameters containing URL-parts to be included for paging
function make_link_array($links, $ReqParam = null)
{
   global $link_array_left, $link_array_right, $forum, $thread, $offset,
      $RowsPerPage, $SearchPostsPerPage, $player_row;

   $link_array_left = $link_array_right = array();

   if( !( $links & LINK_MASKS ) )
      return;

   if( $links & LINK_FORUMS )
      $link_array_left[T_("Forums")] = "index.php";

   if( $links & LINK_THREADS )
      $link_array_left[T_("Threads")] = "list.php?forum=$forum";

   if( $links & LINK_BACK_TO_THREAD )
   {
      global $back_post_id;
      $link_array_left[T_("Back to thread")] = "read.php?forum=$forum"
            .URI_AMP."thread=$thread"
            .( isset($back_post_id) ?"#$back_post_id" :'');
   }


   if( $links & LINK_NEW_TOPIC )
      $link_array_left[T_("New Topic")] = "read.php?forum=$forum";
   if( $links & LINK_SEARCH )
      $link_array_left[T_("Search")] = "search.php";

   if( $links & LINK_MARK_READ )
      $link_array_left["Mark All Read"] = "";

   $navi_url = '';
   if ( !is_null($ReqParam) and ($links & LINKPAGE_SEARCH) and ($links & (LINK_PREV_PAGE|LINK_NEXT_PAGE)) )
      $navi_url = $ReqParam->get_url_parts();

   if( $links & LINK_TOGGLE_MODERATOR )
   {
      $get = array_merge( $_GET, $_POST);
      $get['moderator'] = ( safe_getcookie('forummoderator' . $player_row['ID']) == 'y'? 'n' : 'y' );
      $link_array_right[T_("Toggle forum moderator")] =
         ($links & LINKPAGE_READ
            ? make_url( "read.php", $get, false )
            : ($links & LINKPAGE_LIST
               ? make_url( "list.php", $get, false )
               : ($links & LINKPAGE_SEARCH
                  ? make_url( "search.php", $get, false )
                  : make_url( "index.php", $get, false )
         )));
   }

   if( $links & LINK_PREV_PAGE )
   {
      if( $links & LINKPAGE_SEARCH )
         $href = "search.php?{$navi_url}"
                     . URI_AMP."offset=".($offset-$SearchPostsPerPage);
      else
         $href = "list.php?forum=$forum"
                     . URI_AMP."offset=".($offset-$RowsPerPage);
      $link_array_right[T_("Prev Page")] = array(
         $href, '', array('accesskey' => '<') );
   }
   if( $links & LINK_NEXT_PAGE )
   {
      if( $links & LINKPAGE_SEARCH )
         $href = "search.php?{$navi_url}"
                     . URI_AMP."offset=".($offset+$SearchPostsPerPage);
      else
         $href = "list.php?forum=$forum"
                     . URI_AMP."offset=".($offset+$RowsPerPage);
      $link_array_right[T_("Next Page")] = array(
         $href, '', array('accesskey' => '>') );
   }
}

function print_moderation_note($is_moderator, $width)
{
   if( $is_moderator)
      echo "<table width='$width'><tr><td align=right><font color=red>" . T_("Moderating") . "</font></td></tr></table>\n";
}

// param ReqParam: optional object RequestParameters containing URL-parts to be included for paging
function forum_start_table( $table_id, &$headline, &$links, $cols, $ReqParam = null)
{
/* $table_id could be: (begining by an uppercase letter because used as sub-ID name)
   'Index', 'List', 'Read', 'Search', 'Revision', 'Pending'
*/

   echo "<table id='forum$table_id' class=Forum>\n";

   make_link_array( $links, $ReqParam );

   if( $links & LINK_MASKS )
      echo_links('T',$cols);

   echo "<tr class=Caption>";
   while( list($name, $attbs) = each($headline) )
   {
      echo "<td $attbs>$name</td>";
   }
   echo "</tr>\n";
}

function forum_end_table($links,$cols)
{
   if( $links & LINK_MASKS )
      echo_links('B',$cols);
   echo "</table>\n";
}

function echo_links($id,$cols)
{
   global $link_array_left, $link_array_right;

   $lcols = $cols; //1; $cols/2; $cols-1;
   $tmp = ( $lcols > 1 ? ' colspan='.$lcols : '' );
   echo "<tr class=Links$id><td$tmp><div class=TreeLinks>";
   $first=true;
   reset($link_array_left);
   foreach( $link_array_left as $name => $link )
   {
      if(!$first) echo "&nbsp;|&nbsp;";
      $first=false;
      if( is_array($link) )
         echo anchor( $link[0], $name, $link[1], $link[2]);
      else
         echo anchor( $link, $name);
   }
   echo get_new_string('bottom', 0);

   $lcols = $cols-$lcols;
   $tmp = ( $lcols > 1 ? ' colspan='.$lcols : '' );
   if( $lcols > 0 )
      echo "</div></td><td$tmp><div class=PageLinks>";
   else
      echo "</div><div class=PageLinks>";
   $first=true;
   reset($link_array_right);
   foreach( $link_array_right as $name => $link )
   {
      if(!$first) echo "&nbsp;|&nbsp;";
      $first=false;
      if( is_array($link) )
         echo anchor( $link[0], $name, $link[1], $link[2]);
      else
         echo anchor( $link, $name);
   }

   echo "</div></td></tr>\n";

}

function get_new_string($Lastchangedstamp, $Lastread)
{
   global $NOW, $new_level1, $new_end, $new_count;

   $new = '';
   if( $Lastchangedstamp == 'bottom' )
   {
      if( $new_count>0 )
      {
         $class = 'NewFlag';
         $new = T_('first new');
         $new = "<a name=\"new". ($new_count+1)
                           . "\" href=\"#new1\">$new</a>";
         $new = "<span class=\"$class\">$new</span>";
      }
   }
   else
   {
      if( (empty($Lastread) or $Lastchangedstamp > $Lastread)
            && $Lastchangedstamp + $new_end > $NOW )
      {
         $new_count++;
         if( $Lastchangedstamp + $new_level1 > $NOW )
            $class = 'NewFlag'; //recent 'new'
         else
            $class = 'OlderNewFlag'; //older 'new'
         $new = T_('new');
         $new = "<a name=\"new$new_count\" href=\"#new"
                           . ($new_count+1) . "\">$new</a>";
         $new = "<span class=\"$class\">$new</span>";
      }
   }
   return $new;
}


/*
 $postClass could be: (no '_' because used as sub-class name => CSS compliance)
   'Normal', 'Hidden', 'Reply', 'Preview', 'Edit', 'SearchResult'
 $rx_term: rx-terms (optionally array) that are to be highlighted in text
*/
function draw_post($postClass, $my_post, $Subject='', $Text='',
                   $GoDiagrams=null, $rx_term='')
{

   global $ID, $User_ID, $HOSTBASE, $forum, $Name, $Handle, $Lasteditedstamp, $Lastedited,
      $thread, $Timestamp, $date_fmt, $Lastread, $is_moderator, $NOW, $player_row,
      $ForumName, $Score, $Forum_ID, $Thread_ID, $show_score, $PendingApproval;

   $post_reference = '';
   $cols = 2;

   // highlight terms in Subject/Text (skipping XML-elements like tags & entities)
   if( is_array($rx_term) && count($rx_term) > 0 )
      $rx_term = implode('|', $rx_term);
   else if( !is_string($rx_term) )
      $rx_term = '';

   $sbj = make_html_safe( $Subject, SUBJECT_HTML, $rx_term);
   $txt = make_html_safe( $Text, true, $rx_term);
//   $txt = replace_goban_tags_with_boards($txt, $GoDiagrams);

   if( strlen($txt) == 0 ) $txt = '&nbsp;';

   // Subject header + post body
   if( $postClass == 'Preview' )
   {
      // one line Subject header
      echo "<tr class=PostHead$postClass>\n <td colspan=$cols" .
         "\"><a class=PostSubject name='preview'>$sbj</a><br> " .
         T_('by')." " . user_reference( REF_LINK, 1, '', $player_row) .
         ' &nbsp;&nbsp;&nbsp;' . date($date_fmt, $NOW) . "</td></tr>\n";

      // post body
      echo "<tr class=PostBody>\n <td colspan=$cols>$txt</td></tr>";
   }
   else
   {
      // first line of Subject header
      if( $postClass == 'SearchResult' )
      {
         $hdrcols = $cols;

         echo "<tr class=PostHead$postClass>\n <td colspan=$hdrcols>";
         echo '<a class=PostSubject href="read.php?forum=' . $Forum_ID .URI_AMP
            . "thread=$Thread_ID"
            . ( $rx_term == '' ? '' : URI_AMP."xterm=".urlencode($rx_term) )
            . "#$ID\">$sbj</a>";

         echo ' <font size="+1" color="#FFFFFF">' . T_('found in forum')
            . '</font> <a href="list.php?forum=' .
            $Forum_ID . '" class=black>' . $ForumName . "</a>\n";
         if( $show_score )
            echo ' <font color="#FFFFFF">' . T_('with') . '</font> ' . T_('Score')
               . ' <font color="#000000">' . $Score  . "</font>\n";

         echo "</td></tr>\n";
      }
      else
      {
         $new = get_new_string($Timestamp, $Lastread);
         if( $postClass == 'Hidden' )
            $hdrcols = $cols-1; //because of the rowspan=2 in the second column
         else
            $hdrcols = $cols;

         echo "<tr class=PostHead$postClass>\n <td colspan=$hdrcols>";
         echo "<a class=PostSubject name=\"$ID\">$sbj</a>$new";

         if( $hdrcols != $cols )
         {
            echo "</td>\n <td rowspan=2 align=right>";
            echo '<b><font color="#990000">' .
               ( $PendingApproval == 'Y' ? T_('Awaiting<br>approval') : T_('Hidden') ) .
               '</font></b>';
         }
         echo "</td></tr>\n";
      }

      // second line of Subject header
      echo "<tr class=PostHead$postClass>\n <td colspan=$hdrcols>";

      $post_reference = date($date_fmt, $Timestamp);
      echo T_('by') . " " . user_reference( REF_LINK, 1, '', $User_ID, $Name, $Handle) .
         " &nbsp;&nbsp;&nbsp;$post_reference";      

      if( $Lastedited > 0 )
      {
         $post_reference = date($date_fmt, $Lasteditedstamp);
         echo "&nbsp;&nbsp;&nbsp;(<a href=\"read.php?forum=$forum".URI_AMP."thread=$thread".URI_AMP."revision_history=$ID\">"
            . T_('edited') . "</a> $post_reference)";
      }

      echo "</td></tr>\n";

      $post_reference = "<user $User_ID> ($post_reference):";

      // post body
      echo "<tr class=PostBody>\n <td colspan=$cols>$txt</td></tr>";
   }

   // bottom line (footer)
   if( $postClass == 'Normal' or $postClass == 'Hidden' )
   {
      $hidden = $postClass == 'Hidden';
      echo "<tr class=PostButtons>\n <td colspan=$cols>";

      if( $postClass == 'Normal' and !$is_moderator ) // reply link
      {
         echo "<a href=\"read.php?forum=$forum".URI_AMP."thread=$thread"
            .URI_AMP."reply=$ID#$ID\">[ " .
            T_('reply') . " ]</a>&nbsp;&nbsp;";
         if( ALLOW_QUOTING )
         echo "<a href=\"read.php?forum=$forum".URI_AMP."thread=$thread"
            .URI_AMP."reply=$ID".URI_AMP."quote=1#$ID\">[ " .
            T_('quote') . " ]</a>&nbsp;&nbsp;";
      }
      if( $my_post and !$is_moderator ) // edit link
      {
         echo "<a class=Highlight href=\"read.php?forum=$forum".URI_AMP
            ."thread=$thread".URI_AMP."edit=$ID#$ID\">"
            ."[ " . T_('edit') . " ]</a>&nbsp;&nbsp;";
      }

      if( $is_moderator ) // hide/show link
      {
         if( $PendingApproval !== 'Y' )
            echo "<a class=Highlight href=\"read.php?forum=$forum".URI_AMP
               ."thread=$thread".URI_AMP . ($hidden ?'show' :'hide') . "=$ID#$ID\">"
               ."[ " . ($hidden ?T_('show') :T_('hide')) . " ]</a>";
         else
            echo "<a class=Highlight href=\"read.php?forum=$forum".URI_AMP
               ."thread=$thread".URI_AMP."approve=$ID#$ID\">"
               ."[ " . T_('Approve') . " ]</a>&nbsp;&nbsp;"
               ."<a class=Highlight href=\"read.php?forum=$forum".URI_AMP
               ."thread=$thread".URI_AMP."reject=$ID#$ID\">"
               ."[ " . T_('Reject') . " ]</a>";
      }
      echo "</td></tr>\n";
      
      //vertical space
      echo "<tr><td colspan=$cols height=2></td></tr>\n";
   }
   
   return $post_reference;
}


function forum_message_box( $postClass, $id, $GoDiagrams=null, $Subject='', $Text='')
{
   global $forum, $thread;

   if( $postClass != 'Edit' and $postClass != 'Preview' and strlen($Subject) > 0 and
       strcasecmp(substr($Subject,0,3), "re:") != 0 )
      $Subject = "RE: " . $Subject;

   $form = new Form( 'messageform', "read.php#preview", FORM_POST );

   $form->add_row( array( 'DESCRIPTION', T_('Subject'),
                          'TEXTINPUT', 'Subject', 50, 80, $Subject,
                          'HIDDEN', ($postClass == 'Edit' ? 'edit' : 'parent'), $id,
                          'HIDDEN', 'thread', $thread,
                          'HIDDEN', 'forum', $forum ));
   $form->add_row( array( 'TAB', 'TEXTAREA', 'Text', 70, 25, $Text ) );

/*
    if( isset($GoDiagrams) )
       $str = draw_editors($GoDiagrams);

   if( !empty($str) )
   {
      $form->add_row( array( 'OWNHTML', '<td colspan=2>' . $str . '</td>'));
      $form->add_row( array( 'OWNHTML', '<td colspan=2 align="center">' .
//review accesskey: 
                             '<input type="submit" name="post" accesskey="x" onClick="dump_all_data(\'messageform\');" value=" ' . T_('Post') . " \">\n" .
                             '<input type="submit" name="preview" accesskey="w" onClick="dump_all_data(\'messageform\');" value=" ' . T_('Preview') . " \">\n" .
                             "</td>\n" ));
   }
   else
*/
      $form->add_row( array(
                  'SUBMITBUTTONX', 'post', ' ' . T_('Post') . ' ',
                     array('accesskey' => 'x'),
                  'SUBMITBUTTONX', 'preview', ' ' . T_('Preview') . ' ',
                     array('accesskey' => 'w'),
                  ) );

   $form->echo_string(1);
}

function forum_name($forum, &$moderated)
{
   if( !($forum > 0) )
      error("unknown_forum");

   $result = mysql_query("SELECT Name AS Forumname, Moderated FROM Forums WHERE ID=$forum")
      or error('mysql_query_failed','forum_name');

   if( @mysql_num_rows($result) != 1 )
      error("unknown_forum");

   $row = mysql_fetch_array($result);

   $moderated = ($row['Moderated'] == 'Y');
   return $row["Forumname"];
}

function set_moderator_cookie($id)
{
   $moderator = @$_GET['moderator'];
   $cookie = safe_getcookie("forummoderator$id");
   if( $moderator === 'n' && $cookie !== '' )
   {
      $cookie = '';
      safe_setcookie( "forummoderator$id");
   }
   else if( $moderator === 'y' && $cookie !== 'y' )
   {
      $cookie = 'y';
      safe_setcookie( "forummoderator$id", $cookie, 3600);
   }
   return $cookie === 'y';
}

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
      mysql_query("UPDATE Posts SET PostsInThread=PostsInThread" . ($approve ? '+1' : '-1') .
                  " WHERE ID=$thread LIMIT 1")
         or error('mysql_query_failed','approve_message.set_postsinthread');

      mysql_query("UPDATE Forums SET PostsInForum=PostsInForum" . ($approve ? '+1' : '-1') .
                  " WHERE ID=$forum LIMIT 1")
         or error('mysql_query_failed','approve_message.set_postsinforum');


      recalculate_lastpost($thread, $forum);
   }
}




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

function display_posts_pending_approval()
{
   global $date_fmt;

   $result = mysql_query("SELECT UNIX_TIMESTAMP(Time) as Time,Subject,Forum_ID,Thread_ID, " .
                         "Posts.ID as Post_ID,Forums.Name AS Forumname," .
                         "User_ID,Players.Name as User_Name,Handle " .
                         "FROM (Posts,Players,Forums) " .
                         "WHERE PendingApproval='Y' AND Players.ID=User_ID AND Forums.ID=Forum_ID " .
                         "ORDER BY Time DESC")
      or error('mysql_query_failed','display_posts_pending_approval.find');

   if( mysql_num_rows($result) == 0 )
      return;

   $cols = 4;
   $headline  = array(T_("Posts pending approval") => "colspan=$cols");
   $links = LINKPAGE_STATUS;
   forum_start_table('Pending', $headline, $links, $cols);

   $odd = true;
   while( $row = mysql_fetch_array( $result ) )
   {
      $color = ( $odd ? "" : " bgcolor=white" );

      $Subject = make_html_safe( $row['Subject'], SUBJECT_HTML);
      echo "<tr$color><td>" . ($cols>3?$row['Forumname'] . "</td><td>" : '') .
         "<a href=\"forum/read.php?forum=" . $row['Forum_ID'] .
         URI_AMP . "thread=" . $row['Thread_ID'] . URI_AMP .
         "moderator=y#" . $row['Post_ID'] . "\">$Subject</a></td><td>" .
         user_reference( REF_LINK, 1, '', $row['User_ID'],
               $row['User_Name'], $row['Handle']) .
         "</td><td nowrap align=right>" .date($date_fmt, $row['Time']) .
         "</td></tr>\n";
      $odd = !$odd;

   }
   forum_end_table($links, $cols);
}


?>
