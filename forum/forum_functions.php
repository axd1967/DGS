<?php
/*
Dragon Go Server
Copyright (C) 2001  Erik Ouchterlony

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
require( "include/std_functions.php" );
chdir("forum");


$order_str = "+-/0123456789AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz";

function start_table(&$headline, &$link_array, $width)
{
   echo "<center>
<table bgcolor=e0e8ed border=0 cellspacing=0 cellpadding=3 $width>\n";

   echo_links($headline, $link_array);

   echo "<tr bgcolor=000080>";
   while( list($name, $extra) = each($headline) )
   {
      echo "<td bgcolor=000080 $extra><font color=white>&nbsp;$name</font></td>";
   }
   echo "</tr>\n";

}

function end_table(&$headline, &$link_array)
{
   echo_links($headline, $link_array);
   echo "</table>\n";
}

function echo_links(&$headline, &$link_array)
{
   $cols = max(count($headline),2);

   reset($link_array);
   echo "<tr><td bgcolor=d0d0d0 colspan=$cols align=left>";
   while( list($name, $link) = each($link_array) )
   {
      echo "&nbsp;<a href=\"$link\"><font color=000000>$name</font></a>&nbsp;";
   }
   echo "</td></tr>\n";

}

function message_box($forum, $parent=-1)
{
?>
<FORM name="messageform" action="post.php" method="POST">
    <INPUT type=hidden name="parent" value="<?php echo $parent;?>">
    <INPUT type=hidden name="forum" value="<?php echo $forum;?>">
      
          <TR nowrap>
            <TD align=left>&nbsp;Subject:</TD>
            <TD align=left> <input type="text" name="Subject" size="50" maxlength="80"></TD>
          </TR>
          <TR nowrap>
            <TD align=center colspan=2>  
              &nbsp;<textarea name="Text" cols="60" rows="16" wrap="virtual"></textarea>&nbsp;</TD>
          </TR>

          <TR>
            <TD align=right colspan=2><input type=submit name="send" value=" Post ">&nbsp;</TD>
          </TR>

</FORM>
<?php
}

?>