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


if( basename(getcwd()) == 'forum' )
   chdir("../");
require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );
require_once( "include/GoDiagram.php" );
chdir("forum");


$order_str = "+-/0123456789AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz";

$new_level1 = 2*7*24*3600;  // two week
$new_end =  4*7*24*3600;  // four weeks

define("LINK_FORUMS", 1 << 0);
define("LINK_THREADS", 1 << 1);
define("LINK_BACK_TO_THREAD", 1 << 2);
define("LINK_NEW_TOPIC", 1 << 3);
define("LINK_SEARCH", 1 << 4);
define("LINK_MARK_READ", 1 << 5);
define("LINK_PREV_PAGE", 1 << 6);
define("LINK_NEXT_PAGE", 1 << 7);
define("LINK_TOGGLE_EDITOR", 1 << 8);
define("LINK_TOGGLE_EDITOR_LIST", 1 << 9);


function make_link_array($links)
{
   global $link_array_left, $link_array_right, $forum, $thread, $offset, $RowsPerPage;

   $link_array_left = $link_array_right = array();

   if( $links & LINK_FORUMS )
      $link_array_left[T_("Forums")] = "index.php";

   if( $links & LINK_THREADS )
      $link_array_left[T_("Threads")] = "list.php?forum=$forum";

   if( $links & LINK_BACK_TO_THREAD )
      $link_array_left[T_("Back to thread")] = "read.php?forum=$forum&thread=$thread";


   if( $links & LINK_NEW_TOPIC )
      $link_array_left[T_("New Topic")] = "read.php?forum=$forum";
   if( $links & LINK_SEARCH )
      $link_array_left[T_("Search")] = "search.php";

   if( $links & LINK_MARK_READ )
      $link_array_left["Mark All Read"] = "";

   if( ($links & LINK_TOGGLE_EDITOR) or ($links & LINK_TOGGLE_EDITOR_LIST) )
   {
      $get = $_GET;
      $get['editor'] = ( $_COOKIE['forumeditor'] == 'y'? 'n' : 'y' );
      $link_array_right[T_("Toggle forum editor")] =
         ($links & LINK_TOGGLE_EDITOR ?
          make_url( "read.php", false, $get ) :
          make_url( "list.php", false, $get ) );
   }

   if( $links & LINK_PREV_PAGE )
      $link_array_right[T_("Prev Page")] = "list.php?forum=$forum&offset=".($offset-$RowsPerPage);
   if( $links & LINK_NEXT_PAGE )
      $link_array_right[T_("Next Page")] = "list.php?forum=$forum&offset=".($offset+$RowsPerPage);
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

   $color = '#ff0000';

   if( (empty($Lastread) or $Lastchangedstamp > $Lastread)
       and $Lastchangedstamp + $new_end > $NOW )
   {
      if( $Lastchangedstamp + $new_level1 < $NOW )
      {
         $color = "ff7777";
      }
      $new = '<font color="' . $color . '" size="-1">&nbsp;&nbsp;' . T_('new') .'</font>';
   }
   else
      $new = '';

   return $new;
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

   if( isset($GoDiagrams) )
      $str = draw_editors($GoDiagrams);

   if( !empty($str) )
   {
      $form->add_row( array( 'OWNHTML', '<td colspan=2>' . $str . '</td>'));
      $form->add_row( array( 'OWNHTML', '<td colspan=2 align="center">' . 
                             '<input type="submit" name="post" onClick="dump_all_data(\'messageform\');" value=" ' . T_('Post') . " \">\n" .
                             '<input type="submit" name="preview" onClick="dump_all_data(\'messageform\');" value=" ' . T_('Preview') . " \">\n" .
                             "</td>\n" ));
   }
   else
      $form->add_row( array( 'TAB', 'SUBMITBUTTON', 'post', ' ' . T_('Post') . ' ',
                          'SUBMITBUTTON', 'preview', ' ' . T_('Preview') . ' ') );

   $form->echo_string();

   echo "</ul>\n";
}

function forum_name($forum, &$moderated)
{
   if( !($forum > 0) )
      error("unknown_forum");

   $result = mysql_query("SELECT Name AS Forumname, Moderated FROM Forums WHERE ID=$forum");

   if( mysql_num_rows($result) != 1 )
      error("unknown_forum");

   $row = mysql_fetch_array($result);

   $moderated = ($row['Moderated'] == 'Y');
   return $row["Forumname"];
}

function toggle_editor_cookie()
{
   global $NOW, $SUB_PATH;

   $editor = @$_GET['editor'];
   $cookie = @$_COOKIE['forumeditor'];
   if( $editor === 'y' or $editor === 'n' )
   {
      if( $editor === 'n' )
         $editor = '';
      if( $editor !== $cookie )
      {
         $cookie = $editor;
         setcookie ("forumeditor", $cookie, $NOW+ ( $editor ? 3600 : -3600 ), "$SUB_PATH" );
      }
   }
   $_COOKIE['forumeditor'] = $cookie;
}

function approve_message($id, $thread, $approve=true)
{
   $result = mysql_query("UPDATE Posts SET Approved='" . ( $approve ? 'Y' : 'N' ) . "' " .
                         "WHERE ID=$id AND Thread_ID=$thread LIMIT 1");

   if( mysql_affected_rows() == 1 )
      recalculate_lastchanged($id, ($approve ? 1 : -1));
}


function recalculate_lastchanged($Post_ID, $replies_diff=0)
{
   $result = mysql_query("SELECT Depth,Parent_ID FROM Posts WHERE ID='$Post_ID'");

   if( mysql_num_rows($result) != 1 )
      return;

   extract(mysql_fetch_array($result));

   $d = $Depth;
   $id = $Post_ID;

   while( $d > 0 )
   {
      $result = mysql_query("SELECT Depth,Parent_ID FROM Posts WHERE ID='$id'");
      if( mysql_num_rows($result) != 1 )
         error("internal_error", "recalculate_lastchanged: parent_missing $id $Post_ID" );

      extract(mysql_fetch_array($result));

      if( $Depth != $d )
         error("internal_error", "recalculate_lastchanged: depth_error $id $Post_ID" );

      $result = mysql_query("SELECT Lastchanged FROM Posts " .
                            "WHERE Parent_ID='$id' AND Approved='Y' " .
                            "ORDER BY Lastchanged DESC LIMIT 1");

      if( mysql_num_rows($result) != 1 )
         mysql_query("UPDATE Posts SET Lastchanged=GREATEST(Time,Lastedited), " .
                     "Replies=Replies+($replies_diff) " .
                     "WHERE ID='$id' LIMIT 1");
      else
      {
         extract(mysql_fetch_array($result));
         mysql_query("UPDATE Posts " .
                     "SET Lastchanged='$Lastchanged', Replies=Replies+($replies_diff) " .
                     "WHERE ID='$id' LIMIT 1");
      }

      $d--;
      $id = $Parent_ID;
   }
}

?>