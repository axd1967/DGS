<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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
chdir("forum");


$order_str = "+-/0123456789AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz";

$new_level1 = 2*7*24*3600;  // two week
$new_end =  4*7*24*3600;  // four weeks

define("LINK_FORUMS",1);
define("LINK_THREADS",2);
define("LINK_NEW_TOPIC",4);
define("LINK_EXPAND_VIEW",8);
define("LINK_SEARCH",16);
define("LINK_MARK_READ",32);
define("LINK_PREV_PAGE",1024);
define("LINK_NEXT_PAGE",2048);
define("LINK_TOGGLE_EDITOR",4096);
define("LINK_TOGGLE_EDITOR_LIST",8192);


function make_link_array($links)
{
   global $link_array_left, $link_array_right, $forum, $offset, $RowsPerPage;

   $link_array_left = $link_array_right = array();

   if( $links & LINK_FORUMS )
      $link_array_left["Forums"] = "index.php";

   if( $links & LINK_THREADS )
      $link_array_left["Threads"] = "list.php?forum=$forum";
   if( $links & LINK_NEW_TOPIC )
      $link_array_left["New Topic"] = "read.php?forum=$forum";
   if( $links & LINK_SEARCH )
      $link_array_left["Search"] = "search.php";
   if( $links & LINK_EXPAND_VIEW )
      $link_array_left["Expand View"] = "";
   if( $links & LINK_MARK_READ )
      $link_array_left["Mark All Read"] = "";

   if( $links & LINK_PREV_PAGE )
      $link_array_right["Prev Page"] = "list.php?forum=$forum&offset=".($offset-$RowsPerPage);
   if( $links & LINK_NEXT_PAGE )
      $link_array_right["Next Page"] = "list.php?forum=$forum&offset=".($offset+$RowsPerPage);

   if( ($links & LINK_TOGGLE_EDITOR) or ($links & LINK_TOGGLE_EDITOR_LIST) )
   {
      $get = $_GET;
      $get['editor'] = ( $_COOKIE['forumeditor'] == 'y'? 'n' : 'y' );
      $link_array_right["Toggle forum editor"] =
         ($links & LINK_TOGGLE_EDITOR ?
          make_url( "read.php", false, $get ) :
          make_url( "list.php", false, $get ) );
   }
}

function start_table(&$headline, &$links, $width, $cols)
{
   echo "<center>
<table bgcolor=e0e8ed border=0 cellspacing=0 cellpadding=3 $width>\n";

   make_link_array( $links );

   if( $links > 0 )
      echo_links($cols);

   echo "<tr bgcolor=000080>";
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

   echo "<tr><td bgcolor=d0d0d0 colspan=" . ($cols-1) . " align=left>&nbsp";
   $first=true;
   reset($link_array_left);
   while( list($name, $link) = each($link_array_left) )
   {
      if(!$first) echo "&nbsp;|&nbsp;";
      echo "<a href=\"$link\"><font color=000000>$name</font></a>";
      $first=false;
   }
   echo "&nbsp;</td>\n<td bgcolor=d0d0d0 align=right>&nbsp\n";

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

function message_box( $post_type, $id, $Subject='', $Text='')
{
   global $forum, $thread;

   if( $post_type != 'edit' and strlen($Subject) > 0 and
       strcasecmp(substr($Subject,0,3), "re:") != 0 )
      $Subject = "RE: " . $Subject;

   echo "<ul>\n";

   $form = new Form( 'messageform', "read.php#preview", FORM_POST );

   $form->add_row( array( 'DESCRIPTION', T_('Subject'),
                          'TEXTINPUT', 'Subject', 50, 80, $Subject,
                          'HIDDEN', ($post_type == 'edit' ? 'edit' : 'parent'), $id,
                          'HIDDEN', 'thread', $thread,
                          'HIDDEN', 'forum', $forum ));
   $form->add_row( array( 'SPACE', 'TEXTAREA', 'Text', 70, 25, $Text ) );
   $form->add_row( array( 'SPACE', 'SUBMITBUTTON', 'post', ' ' . T_('Post') . ' ',
                          'SUBMITBUTTON', 'preview', ' ' . T_('Preview') . ' ') );
   $form->echo_string();
   echo "</ul>\n";
}

function forum_name($forum, &$moderated)
{
   if( !($forum > 0) )
      error("unknown_forum");

   $result = mysql_query("SELECT Name AS Forumname, Unmoderated FROM Forums WHERE ID=$forum");

   if( mysql_num_rows($result) != 1 )
      error("unknown_forum");

   $row = mysql_fetch_array($result);

   $moderated = ($row['Unmoderated'] == 'N');
   return $row["Forumname"];
}

function toggle_editor_cookie()
{
   global $NOW, $SUB_PATH;

   if( ($_GET['editor'] === 'y' and $_COOKIE['forumeditor'] !== 'y') or
       ($_GET['editor'] === 'n' and $_COOKIE['forumeditor'] === 'y'))
      {
         if( $_COOKIE['forumeditor'] == 'y' )
            setcookie ("forumeditor", '', $NOW-3600, "$SUB_PATH" );
         else
            setcookie ("forumeditor", 'y', $NOW+3600, "$SUB_PATH" );
         $_COOKIE['forumeditor'] = $_GET['editor'];
      }
}

function approve_message($id, $thread, $approve=true)
{
   mysql_query("UPDATE Posts SET Approved='" . ( $approve ? 'Y' : 'N' ) . "' " .
               "WHERE ID=$id AND Thread_ID=$thread LIMIT 1");
}

?>