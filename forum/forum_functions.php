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


function make_link_array( $links)
{
   global $link_array_left, $link_array_right, $forum;

   $link_array_left = $link_array_right = array();

   if( $links & LINK_FORUMS )
      $link_array_left["Forums"] = "index.php";

   if( $links & LINK_THREADS )
      $link_array_left["Threads"] = "list.php?forum=$forum";
   if( $links & LINK_NEW_TOPIC )
      $link_array_left["New Topic"] = "new_topic.php?forum=$forum";
   if( $links & LINK_SEARCH )
      $link_array_left["Search"] = "search.php";
   if( $links & LINK_EXPAND_VIEW )
      $link_array_left["Expand View"] = "";
   if( $links & LINK_MARK_READ )
      $link_array_left["Mark All Read"] = "";

   if( $links & LINK_PREV_PAGE )
      $link_array_right["Prev Page"] = "test$prev_page";
   if( $links & LINK_NEXT_PAGE )
      $link_array_right["Next Page"] = "$next_page";


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
   echo "</table>\n";
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

function message_box($forum, $parent=-1, $Subject=NULL)
{
   if( $Subject and strcasecmp(substr($Subject,0,3), "re:") != 0 )
      $Subject = "RE: " . $Subject;


?>
<table align=center cellpadding=0>
<FORM name="messageform" action="post.php" method="POST">
    <INPUT type=hidden name="parent" value="<?php echo $parent;?>">
    <INPUT type=hidden name="forum" value="<?php echo $forum;?>">

          <TR nowrap>
            <TD align=left colspan=2>&nbsp;&nbsp;Subject:&nbsp;&nbsp;
            <input type="text" name="Subject" size="50" maxlength="80" <?php if( $Subject ) echo "value=\"$Subject\"";?>></TD>
          </TR>
          <TR nowrap>
            <TD align=center colspan=2>
              &nbsp;<textarea name="Text" cols="60" rows="16" wrap="virtual"></textarea>&nbsp;</TD>
          </TR>

          <TR>
            <TD align=right colspan=2><input type=submit name="send" value=" Post ">&nbsp;</TD>
          </TR>

</FORM>
</table>
<?php
}

function forum_name($forum)
{
   if( !($forum > 0) )
      error("Unknown forum");

   $result = mysql_query("SELECT Name AS Forumname FROM Forums WHERE ID=$forum");

   if( mysql_num_rows($result) != 1 )
      error("Unknown forum");

   $row = mysql_fetch_array($result);

   return $row["Forumname"];
}

?>