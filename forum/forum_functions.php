<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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


chdir("../");
require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );
//require_once( "include/GoDiagram.php" );
chdir("forum");


//$new_end =  4*7*24*3600;  // four weeks //moved to quick_common.php

$new_level1 = 2*7*24*3600;  // two weeks

$order_str = "*+-/0123456789AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz";

define("LINK_FORUMS", 1 << 0);
define("LINK_THREADS", 1 << 1);
define("LINK_BACK_TO_THREAD", 1 << 2);
define("LINK_NEW_TOPIC", 1 << 3);
define("LINK_SEARCH", 1 << 4);
define("LINK_MARK_READ", 1 << 5);
define("LINK_PREV_PAGE", 1 << 6);
define("LINK_NEXT_PAGE", 1 << 7);
define("LINK_TOGGLE_MODERATOR", 1 << 8);
define("LINK_TOGGLE_MODERATOR_LIST", 1 << 9);


function make_link_array($links)
{
   global $link_array_left, $link_array_right, $forum, $thread, $offset, $RowsPerPage;

   $link_array_left = $link_array_right = array();

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

   if( ($links & LINK_TOGGLE_MODERATOR) or ($links & LINK_TOGGLE_MODERATOR_LIST) )
   {
      $get = $_GET;
      $get['moderator'] = ( @$_COOKIE[COOKIE_PREFIX.'forummoderator'] == 'y'? 'n' : 'y' );
      $link_array_right[T_("Toggle forum moderator")] =
         ($links & LINK_TOGGLE_MODERATOR ?
          make_url( "read.php", $get, false ) :
          make_url( "list.php", $get, false ) );
   }

   if( $links & LINK_PREV_PAGE )
      $link_array_right[T_("Prev Page")] = "list.php?forum=$forum".URI_AMP."offset=".($offset-$RowsPerPage);
   if( $links & LINK_NEXT_PAGE )
      $link_array_right[T_("Next Page")] = "list.php?forum=$forum".URI_AMP."offset=".($offset+$RowsPerPage);
}

function start_table(&$headline, &$links, $width, $cols)
{
   echo "<center>
<table bgcolor=e0e8ed border=0 cellspacing=0 cellpadding=3 $width>\n";

   make_link_array( $links );

   if( $links > 0 )
      echo_links($cols);

   echo "<tr>";
   while( list($name, $extra) = each($headline) )
   {
      echo "<td bgcolor=000080 $extra><font color=white>&nbsp;$name</font></td>";
   }
   echo "</tr>\n";

}

function end_table($links,$cols)
{
   if( $links > 0 )
      echo_links($cols);
   echo "</table></center>\n";
}

function echo_links($cols)
{
   global $link_array_left, $link_array_right;

   echo "<tr><td bgcolor=d0d0d0 colspan=" . ($cols/2) . " align=left>&nbsp;";
   $first=true;
   reset($link_array_left);
   while( list($name, $link) = each($link_array_left) )
   {
      if(!$first) echo "&nbsp;|&nbsp;";
      echo "<a href=\"$link\"><font color=000000>$name</font></a>";
      $first=false;
   }
   echo "&nbsp;</td>\n<td bgcolor=d0d0d0 align=right colspan=" . ($cols-$cols/2) . ">&nbsp;";

   $first=true;
   reset($link_array_right);
   while( list($name, $link) = each($link_array_right) )
   {
      if(!$first) echo "&nbsp;|&nbsp;";
      echo "<a href=\"$link\"><font color=000000>$name</font></a>";
      $first=false;
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
      $new = '<font color="' . $color . '" size="-1">&nbsp;&nbsp;' . T_('new') .'</font>';
   }
   else
      $new = '';

   return $new;
}


function draw_post($post_type, $my_post, $Subject='', $Text='', $GoDiagrams=null )
{
   global $ID, $User_ID, $HOSTBASE, $forum, $Name, $Handle, $Lasteditedstamp, $Lastedited,
      $thread, $Timestamp, $date_fmt, $Lastread, $is_moderator, $NOW, $player_row;

   $post_colors = array( 'normal' => 'cccccc',
                         'hidden' => 'eecccc',
                         'reply' => 'cccccc',
                         'preview' => 'cceecc',
                         'edit' => 'eeeecc' );

   $sbj = make_html_safe( $Subject );
   $txt = make_html_safe( $Text, true);
//   $txt = replace_goban_tags_with_boards($txt, $GoDiagrams);

   if( strlen($txt) == 0 ) $txt = '&nbsp;';

   $color = "ff0000";
   $new = get_new_string($Timestamp, $Lastread);


   if( $post_type == 'preview' )
      echo '<tr><td bgcolor="#' . $post_colors[ $post_type ] .
         "\"><a name=\"preview\"><font size=\"+1\"><b>$sbj</b></font></a><br> " . 
         T_('by')." " . user_reference( 1, 1, "black", $player_row) .
         ' &nbsp;&nbsp;&nbsp;' . date($date_fmt, $NOW) . "</td></tr>\n" .
         '<tr><td bgcolor=white>' . $txt . "</td></tr>\n";
   else
   {
      echo '<tr><td bgcolor="#' . $post_colors[ $post_type ] .
         "\"><a name=\"$ID\"><font size=\"+1\"><b>$sbj</b></font>$new</a><br> " .
         T_('by')." " . user_reference( 1, 1, "black", $User_ID, $Name, $Handle) .
         ' &nbsp;&nbsp;&nbsp;' . date($date_fmt, $Timestamp);
      if( $Lastedited > 0 )
         echo "&nbsp;&nbsp;&nbsp;(<a href=\"read.php?forum=$forum".URI_AMP."thread=$thread".URI_AMP."revision_history=$ID\">" . T_('edited') .
            "</a> " . date($date_fmt, $Lasteditedstamp) . ")";
      echo "</td></tr>\n" .
         '<tr><td bgcolor=white>' . $txt . "</td></tr>\n";
   }

   if( $post_type == 'normal' or $post_type == 'hidden' )
   {
      $hidden = $post_type == 'hidden';
      echo "<tr><td bgcolor=white align=left>";
      if(  $post_type == 'normal' and !$is_moderator ) // reply link
         echo "<a href=\"read.php?forum=$forum".URI_AMP."thread=$thread".URI_AMP."reply=$ID#$ID\">[ " .
            T_('reply') . " ]</a>&nbsp;&nbsp;";
      if( $my_post ) // edit link
         echo "<a href=\"read.php?forum=$forum".URI_AMP."thread=$thread".URI_AMP."edit=$ID#$ID\">" .
            "<font color=\"#ee6666\">[ " . T_('edit') . " ]</font></a>&nbsp;&nbsp;";
      if( $is_moderator ) // hide/show link
         echo "<a href=\"read.php?forum=$forum".URI_AMP."thread=$thread".URI_AMP .
            ( $hidden ? 'show' : 'hide' ) . "=$ID#$ID\"><font color=\"#ee6666\">[ " .
            ( $hidden ? T_('show') : T_('hide') ) . " ]</font></a>";

      echo "</td></tr>\n";
   }
}


function message_box( $post_type, $id, $GoDiagrams=null, $Subject='', $Text='')
{
   global $forum, $thread;

   if( $post_type != 'edit' and $post_type != 'preview' and strlen($Subject) > 0 and
       strcasecmp(substr($Subject,0,3), "re:") != 0 )
      $Subject = "RE: " . $Subject;

   echo "<ul>\n";

   $form = new Form( 'messageform', "read.php#preview", FORM_POST );

   $form->add_row( array( 'DESCRIPTION', T_('Subject'),
                          'TEXTINPUT', 'Subject', 50, 80, $Subject,
                          'HIDDEN', ($post_type == 'edit' ? 'edit' : 'parent'), $id,
                          'HIDDEN', 'thread', $thread,
                          'HIDDEN', 'forum', $forum ));
   $form->add_row( array( 'TAB', 'TEXTAREA', 'Text', 70, 25, $Text ) );

//    if( isset($GoDiagrams) )
//       $str = draw_editors($GoDiagrams);

   if( !empty($str) )
   {
      $form->add_row( array( 'OWNHTML', '<td colspan=2>' . $str . '</td>'));
      $form->add_row( array( 'OWNHTML', '<td colspan=2 align="center">' . 
                             '<input type="submit" name="post" onClick="dump_all_data(\'messageform\');" value=" ' . T_('Post') . " \">\n" .
                             '<input type="submit" name="preview" onClick="dump_all_data(\'messageform\');" value=" ' . T_('Preview') . " \">\n" .
                             "</td>\n" ));
   }
   else
      $form->add_row( array( 'TAB', 'SUBMITBUTTON', 'post" accesskey="x', ' ' . T_('Post') . ' ',
                          'SUBMITBUTTON', 'preview" accesskey="w', ' ' . T_('Preview') . ' ') );

   $form->echo_string(1);

   echo "</ul>\n";
}

function forum_name($forum, &$moderated)
{
   if( !($forum > 0) )
      error("unknown_forum");

   $result = mysql_query("SELECT Name AS Forumname, Moderated FROM Forums WHERE ID=$forum")
      or die(mysql_error());

   if( @mysql_num_rows($result) != 1 )
      error("unknown_forum");

   $row = mysql_fetch_array($result);

   $moderated = ($row['Moderated'] == 'Y');
   return $row["Forumname"];
}

function set_moderator_cookie()
{
   $moderator = @$_GET['moderator'];
   $cookie = @$_COOKIE[COOKIE_PREFIX.'forummoderator'];
   if( $moderator === 'n' && $cookie !== '' )
   {
      $cookie = '';
      safe_setcookie( 'forummoderator');
   }
   else if( $moderator === 'y' && $cookie !== 'y' )
   {
      $cookie = 'y';
      safe_setcookie( 'forummoderator', $cookie, 3600);
   }
   $_COOKIE[COOKIE_PREFIX.'forummoderator'] = $cookie;
   return $cookie === 'y';
}

function approve_message($id, $thread, $forum, $approve=true)
{
   $result = mysql_query("UPDATE Posts SET Approved='" . ( $approve ? 'Y' : 'N' ) . "', " .
                         "PendingApproval='N' " .
                         "WHERE ID=$id AND Thread_ID=$thread " .
                         "AND Approved='" . ( $approve ? 'N' : 'Y' ) . "' LIMIT 1")
      or die(mysql_error());

   if( mysql_affected_rows() == 1 )
   {
      mysql_query("UPDATE Posts SET PostsInThread=PostsInThread" . ($approve ? '+1' : '-1') .
                  " WHERE ID=$thread LIMIT 1") or die(mysql_error());

      mysql_query("UPDATE Forums SET PostsInForum=PostsInForum" . ($approve ? '+1' : '-1') .
                  " WHERE ID=$forum LIMIT 1") or die(mysql_error());


      recalculate_lastpost($thread, $forum);
   }
}




function recalculate_lastpost($Thread_ID, $Forum_ID)
{
   $result = mysql_query("SELECT ID, UNIX_TIMESTAMP(Time) AS Timestamp FROM Posts " .
                         "WHERE Thread_ID='$Thread_ID' AND Approved='Y' " .
                         "AND PosIndex IS NOT NULL " .
                         "ORDER BY Time Desc LIMIT 1")
      or die(mysql_error());

   if( @mysql_num_rows($result) == 1 )
   {
      $row = mysql_fetch_row($result);
      mysql_query("UPDATE Posts SET LastPost=" . $row[0] . ", " .
                  "LastChanged=FROM_UNIXTIME(" . $row[1] . ") " .
                  "WHERE ID=$Thread_ID LIMIT 1")
         or die(mysql_error());
   }


   $result = mysql_query("SELECT Last.ID " .
                         "FROM Posts as Thread, Posts as Last " .
                         "WHERE Thread.LastPost=Last.ID AND " .
                         "Thread.Forum_ID=" . $Forum_ID . " AND Thread.Parent_ID=0 " .
                         "ORDER BY Last.Time DESC LIMIT 1")
      or die(mysql_error());

   if( @mysql_num_rows($result) == 1 )
   {
      $row = mysql_fetch_row($result);
      mysql_query("UPDATE Forums SET LastPost=" . $row[0] . " WHERE ID=$Forum_ID LIMIT 1")
         or die(mysql_error());
   }

}


function recalculate_postsinforum($Forum_ID)
{
   $result = mysql_query("SELECT COUNT(*), Thread_ID FROM Posts " .
                         "WHERE Forum_ID=$Forum_ID AND Approved='Y' GROUP BY Thread_ID")
      or die(mysql_error());

   $sum = 0;
   while( $row = mysql_fetch_row( $result ) )
   {
      $sum += $row[0];

      mysql_query("UPDATE Posts SET PostsInThread=" . $row[0] . " WHERE ID=" .$row[1])
         or die(mysql_error());

      recalculate_lastpost($row[1], $Forum_ID);
   }

   mysql_query("UPDATE Forums SET PostsInForum=$sum WHERE ID=$Forum_ID")
      or die(mysql_error());
}

function display_posts_pending_approval()
{
   global $date_fmt;

   $result = mysql_query("SELECT UNIX_TIMESTAMP(Time) as Time,Subject,Forum_ID,Thread_ID, " .
                         "Posts.ID as Post_ID,User_ID,Name,Handle " .
                         "FROM Posts,Players " .
                         "WHERE PendingApproval='Y' AND Players.ID=User_ID " .
                         "ORDER BY Time DESC")
      or die(mysql_error());

   if( mysql_num_rows($result) == 0 )
      return;

   $cols = 3;
   $headline  = array(T_("Posts pending approval") => "colspan=$cols");
   $links = 0;
   start_table($headline, $links, "width=90%", $cols);

   $odd = true;
   while( $row = mysql_fetch_array( $result ) )
   {
      $color = ( $odd ? "" : " bgcolor=white" );

      $Subject = make_html_safe( $row['Subject'], true);
      echo "<tr$color><td><a href=\"forum/read.php?forum=" . $row['Forum_ID'] . URI_AMP .
         "thread=" . $row['Thread_ID'] . URI_AMP . "moderator=y#" . $row['Post_ID'] . "\">$Subject</a></td><td>" .
         user_reference( REF_LINK, 1, NULL, $row['User_ID'], $row['Name'], $row['Handle']) .
         "</td><td nowrap align=right>" .date($date_fmt, $row['Time']) . "</td></tr>\n";
      $odd = !$odd;

   }
   end_table($links, $cols);
}


?>