<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

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

$TranslateGroups[] = "Forum";

chdir("../");
require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );
//require_once( "include/GoDiagram.php" );
chdir("forum");


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


define("FORUM_MAXIMUM_DEPTH", 15);
define("FORUM_INDENTATION_PIXELS", 15);

function make_link_array($links)
{
   global $link_array_left, $link_array_right, $forum, $thread, $offset,
      $RowsPerPage, $SearchPostsPerPage, $search_terms, $player_row;

   $link_array_left = $link_array_right = array();

   if( !( $links & LINK_MASKS ) )
      return;

   if( $links & LINK_FORUMS )
      $link_array_left[T_("Forums")] = "index.php";

   if( $links & LINK_THREADS )
      $link_array_left[T_("Threads")] = "list.php?forum=$forum";

   if( $links & LINK_BACK_TO_THREAD )
      $link_array_left[T_("Back to thread")] = "read.php?forum=$forum".URI_AMP."thread=$thread";


   if( $links & LINK_NEW_TOPIC )
      $link_array_left[T_("New Topic")] = "read.php?forum=$forum";
   if( $links & LINK_SEARCH )
      $link_array_left[T_("Search")] = "search.php";

   if( $links & LINK_MARK_READ )
      $link_array_left["Mark All Read"] = "";

   if( $links & LINK_TOGGLE_MODERATOR )
   {
      $get = $_GET;
      $get['moderator'] = ( safe_getcookie('forummoderator' . $player_row['ID']) == 'y'? 'n' : 'y' );
      $link_array_right[T_("Toggle forum moderator")] =
         ($links & LINKPAGE_READ ?
            make_url( "read.php", $get, false ) :
            ($links & LINKPAGE_LIST ?
               make_url( "list.php", $get, false ) :
               make_url( "index.php", $get, false ) ) );
   }

   if( $links & LINK_PREV_PAGE )
   {
      if( $links & LINKPAGE_SEARCH )
         $href = "search.php?search_terms=$search_terms"
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
         $href = "search.php?search_terms=$search_terms"
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

function start_table(&$headline, &$links, $width, $cols)
{
   echo "<center>
<table bgcolor=\"#e0e8ed\" border=0 cellspacing=0 cellpadding=3 $width>\n";

   make_link_array( $links );

   if( $links & LINK_MASKS )
      echo_links($cols);

   echo "<tr>";
   while( list($name, $extra) = each($headline) )
   {
      echo "<td bgcolor=\"#000080\" $extra><font color=white>&nbsp;$name</font></td>";
   }
   echo "</tr>\n";
}

function end_table($links,$cols)
{
   if( $links & LINK_MASKS )
      echo_links($cols);
   echo "</table></center>\n";
}

function echo_links($cols)
{
   global $link_array_left, $link_array_right;

   $rcols = $cols-1; //1; $cols/2; $cols-1;
   echo '<tr class="forum_header"><td colspan=' . ($cols-$rcols) . " align=left>&nbsp;";
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

   echo "&nbsp;</td>\n<td colspan=" . ($rcols) . " align=right>&nbsp;";
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

   echo "&nbsp;</td></tr>\n";

}

function get_new_string($Lastchangedstamp, $Lastread)
{
   global $NOW, $new_level1, $new_end;

   if( (empty($Lastread) or $Lastchangedstamp > $Lastread)
       and $Lastchangedstamp + $new_end > $NOW )
   {
      if( $Lastchangedstamp + $new_level1 > $NOW )
         $color = '#ff0000'; //recent 'new'
      else
         $color = '#ff7777'; //older 'new'
      $new = '<font color="' . $color . '" size="-1">' . T_('new') .'</font>';

      global $new_count;
      $new_count++;
      $new = "&nbsp;&nbsp;<a name=\"new$new_count\" href=\"#new"
                        . ($new_count+1) . "\">$new</a>";
   }
   else
      $new = '';

   return $new;
}


function draw_post($post_type, $my_post, $Subject='', $Text='', $GoDiagrams=null)
{
/* $post_type could be:
   'normal', 'search_result', 'hidden', 'reply', 'preview', 'edit'
*/

   global $ID, $User_ID, $HOSTBASE, $forum, $Name, $Handle, $Lasteditedstamp, $Lastedited,
      $thread, $Timestamp, $date_fmt, $Lastread, $is_moderator, $NOW, $player_row,
      $ForumName, $Score, $Forum_ID, $Thread_ID, $bool, $PendingApproval;

   $post_reference = '';
   $cols = 2;

   $sbj = make_html_safe( $Subject );
   $txt = make_html_safe( $Text, true);
//   $txt = replace_goban_tags_with_boards($txt, $GoDiagrams);

   if( strlen($txt) == 0 ) $txt = '&nbsp;';

   $color = "ff0000"; //useful?? 
   $new = get_new_string($Timestamp, $Lastread);


   // Subject header + post body
   if( $post_type == 'preview' )
   {
      // one line Subject header
      echo "<tr class=\"post_head $post_type\">\n <td colspan=$cols" .
         "\"><a class=\"post_subject\" name=\"preview\">$sbj</a><br> " . 
         T_('by')." " . user_reference( 1, 1, "black", $player_row) .
         ' &nbsp;&nbsp;&nbsp;' . date($date_fmt, $NOW) . "</td></tr>\n";

      // post body
      echo "<tr class=\"post_body\">\n <td colspan=$cols>$txt</td></tr>";
   }
   else
   {
      // first line of Subject header
      if( $post_type == 'search_result' )
      {
         $hdrcols = $cols;

         echo "<tr class=\"post_head $post_type\">\n <td colspan=$hdrcols>";
         echo '<a class=\"post_subject\" href="read.php?forum=' . $Forum_ID .URI_AMP
            . "thread=$Thread_ID#$ID\">$sbj</a>";

         echo ' <font size="+1" color="#FFFFFF">' . T_('found in forum')
            . '</font> <a href="list.php?forum=' .
            $Forum_ID . '" class=black>' . $ForumName . "</a>\n";
         if( !$bool )
            echo ' <font color="#FFFFFF">' . T_('with') . '</font> ' . T_('Score')
               . ' <font color="#000000">' . $Score  . "</font>\n";

         echo "</td></tr>\n";
      }
      else
      {
         if( $post_type == 'hidden' )
            $hdrcols = $cols-1; //because of the rowspan=2 in the second column
         else
            $hdrcols = $cols;

         echo "<tr class=\"post_head $post_type\">\n <td colspan=$hdrcols>";
         echo "<a class=\"post_subject\" name=\"$ID\">$sbj</a>$new";

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
      echo "<tr class=\"post_head $post_type\">\n <td colspan=$hdrcols>";

      $post_reference = date($date_fmt, $Timestamp);
      echo T_('by') . " " . user_reference( 1, 1, "black", $User_ID, $Name, $Handle) .
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
      echo "<tr class=\"post_body\">\n <td colspan=$cols>$txt</td></tr>";
   }

   // bottom line (footer)
   if( $post_type == 'normal' or $post_type == 'hidden' )
   {
      $hidden = $post_type == 'hidden';
      echo "<tr class=\"post_buttons\">\n <td colspan=$cols align=left>";
      if(  $post_type == 'normal' and !$is_moderator ) // reply link
      {
         echo "<a href=\"read.php?forum=$forum".URI_AMP."thread=$thread"
            .URI_AMP."reply=$ID#$ID\">[ " .
            T_('reply') . " ]</a>&nbsp;&nbsp;";
//          echo "<a href=\"read.php?forum=$forum".URI_AMP."thread=$thread"
//             .URI_AMP."reply=$ID".URI_AMP."quote=1#$ID\">[ " .
//             T_('quote') . " ]</a>&nbsp;&nbsp;";
      }
      if( $my_post and !$is_moderator ) // edit link
         echo "<a href=\"read.php?forum=$forum".URI_AMP."thread=$thread".URI_AMP."edit=$ID#$ID\">" .
            "<font color=\"#ee6666\">[ " . T_('edit') . " ]</font></a>&nbsp;&nbsp;";
      if( $is_moderator ) // hide/show link
      {
         if( $PendingApproval !== 'Y' )
            echo "<a href=\"read.php?forum=$forum".URI_AMP."thread=$thread".URI_AMP .
               ( $hidden ? 'show' : 'hide' ) . "=$ID#$ID\"><font color=\"#ee6666\">[ " .
               ( $hidden ? T_('show') : T_('hide') ) . " ]</font></a>";
         else
            echo "<a href=\"read.php?forum=$forum".URI_AMP."thread=$thread".URI_AMP .
               "approve=$ID#$ID\"><font color=\"#ee6666\">[ " .
               T_('Approve')  . " ]</font></a>&nbsp;&nbsp;" .
               "<a href=\"read.php?forum=$forum".URI_AMP."thread=$thread".URI_AMP .
               "reject=$ID#$ID\"><font color=\"#ee6666\">[ " .
               T_('Reject')  . " ]</font></a>";
      }
      echo "</td></tr>\n";
      
      //vertical space
      echo "<tr><td colspan=$cols height=2></td></tr>\n";
   }
   
   return $post_reference;
}


function message_box( $post_type, $id, $GoDiagrams=null, $Subject='', $Text='')
{
   global $forum, $thread;

   if( $post_type != 'edit' and $post_type != 'preview' and strlen($Subject) > 0 and
       strcasecmp(substr($Subject,0,3), "re:") != 0 )
      $Subject = "RE: " . $Subject;

   $form = new Form( 'messageform', "read.php#preview", FORM_POST );

   $form->add_row( array( 'DESCRIPTION', T_('Subject'),
                          'TEXTINPUT', 'Subject', 50, 80, $Subject,
                          'HIDDEN', ($post_type == 'edit' ? 'edit' : 'parent'), $id,
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
                  'TAB',
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
      or error('mysql_query_failed','forum_functions.forum_name');

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
      $row = mysql_single_fetch( 'forum_functions.approve_message.find_post',
               "SELECT Approved FROM Posts " .
               "WHERE ID=$id AND Thread_ID=$thread LIMIT 1" )
         or error('unknown_post','forum_functions.approve_message.find_post');

      $Approved = ($row['Approved'] == 'Y');

      if( $Approved === $approve )
      {
         mysql_query("UPDATE Posts SET PendingApproval='N' " .
                     "WHERE ID=$id AND Thread_ID=$thread " .
                     "AND PendingApproval='Y' LIMIT 1")
            or error('mysql_query_failed','forum_functions.approve_message.pend_appr');
         return;
      }
   }

   $result = mysql_query("UPDATE Posts SET Approved='" . ( $approve ? 'Y' : 'N' ) . "', " .
                         "PendingApproval='N' " .
                         "WHERE ID=$id AND Thread_ID=$thread " .
                         "AND Approved='" . ( $approve ? 'N' : 'Y' ) . "' LIMIT 1")
      or error('mysql_query_failed','forum_functions.approve_message.set_approved');

   if( mysql_affected_rows() == 1 )
   {
      mysql_query("UPDATE Posts SET PostsInThread=PostsInThread" . ($approve ? '+1' : '-1') .
                  " WHERE ID=$thread LIMIT 1")
         or error('mysql_query_failed','forum_functions.approve_message.set_postsinthread');

      mysql_query("UPDATE Forums SET PostsInForum=PostsInForum" . ($approve ? '+1' : '-1') .
                  " WHERE ID=$forum LIMIT 1")
         or error('mysql_query_failed','forum_functions.approve_message.set_postsinforum');


      recalculate_lastpost($thread, $forum);
   }
}




function recalculate_lastpost($Thread_ID, $Forum_ID)
{
   $result = mysql_query("SELECT ID, UNIX_TIMESTAMP(Time) AS Timestamp FROM Posts " .
                         "WHERE Thread_ID='$Thread_ID' AND Approved='Y' " .
                         "AND PosIndex IS NOT NULL " .
                         "ORDER BY Time Desc LIMIT 1")
      or error('mysql_query_failed','forum_functions.recalculate_lastpost.find');

   if( @mysql_num_rows($result) == 1 )
   {
      $row = mysql_fetch_row($result);
      mysql_query("UPDATE Posts SET LastPost=" . $row[0] . ", " .
                  "LastChanged=FROM_UNIXTIME(" . $row[1] . ") " .
                  "WHERE ID=$Thread_ID LIMIT 1")
         or error('mysql_query_failed','forum_functions.recalculate_lastpost.update');
   }


   $result = mysql_query("SELECT Last.ID " .
                         "FROM Posts as Thread, Posts as Last " .
                         "WHERE Thread.LastPost=Last.ID AND " .
                         "Thread.Forum_ID=" . $Forum_ID . " AND Thread.Parent_ID=0 " .
                         "ORDER BY Last.Time DESC LIMIT 1")
      or error('mysql_query_failed','forum_functions.recalculate_lastpost.lastid');

   if( @mysql_num_rows($result) == 1 )
   {
      $row = mysql_fetch_row($result);
      mysql_query("UPDATE Forums SET LastPost=" . $row[0] . " WHERE ID=$Forum_ID LIMIT 1")
         or error('mysql_query_failed','forum_functions.recalculate_lastpost.lastpost');
   }

}


function recalculate_postsinforum($Forum_ID)
{
   $result = mysql_query("SELECT COUNT(*), Thread_ID FROM Posts " .
                         "WHERE Forum_ID=$Forum_ID AND Approved='Y' GROUP BY Thread_ID")
      or error('mysql_query_failed','forum_functions.recalculate_postsinforum.find');

   $sum = 0;
   while( $row = mysql_fetch_row( $result ) )
   {
      $sum += $row[0];

      mysql_query("UPDATE Posts SET PostsInThread=" . $row[0] . " WHERE ID=" .$row[1])
      or error('mysql_query_failed','forum_functions.recalculate_postsinforum.postsintrhead');

      recalculate_lastpost($row[1], $Forum_ID);
   }

   mysql_query("UPDATE Forums SET PostsInForum=$sum WHERE ID=$Forum_ID")
      or error('mysql_query_failed','forum_functions.recalculate_postsinforum.postsinofrum');
}

function display_posts_pending_approval()
{
   global $date_fmt;

   $result = mysql_query("SELECT UNIX_TIMESTAMP(Time) as Time,Subject,Forum_ID,Thread_ID, " .
                         "Posts.ID as Post_ID,User_ID,Name,Handle " .
                         "FROM Posts,Players " .
                         "WHERE PendingApproval='Y' AND Players.ID=User_ID " .
                         "ORDER BY Time DESC")
      or error('mysql_query_failed','forum_functions.display_posts_pending_approval.find');

   if( mysql_num_rows($result) == 0 )
      return;

   $cols = 3;
   $headline  = array(T_("Posts pending approval") => "colspan=$cols");
   $links = LINKPAGE_STATUS;
   start_table($headline, $links, "width=90%", $cols);

   $odd = true;
   while( $row = mysql_fetch_array( $result ) )
   {
      $color = ( $odd ? "" : " bgcolor=white" );

      $Subject = make_html_safe( $row['Subject']);
      echo "<tr$color><td><a href=\"forum/read.php?forum=" . $row['Forum_ID'] . URI_AMP .
         "thread=" . $row['Thread_ID'] . URI_AMP . "moderator=y#" . $row['Post_ID'] . "\">$Subject</a></td><td>" .
         user_reference( REF_LINK, 1, NULL, $row['User_ID'], $row['Name'], $row['Handle']) .
         "</td><td nowrap align=right>" .date($date_fmt, $row['Time']) . "</td></tr>\n";
      $odd = !$odd;

   }
   end_table($links, $cols);
}


?>