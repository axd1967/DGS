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


include("forum_functions.php");

function draw_post($reply_link=true)
{
   global $Subject, $Text, $ID, $User_ID, $HOSTBASE, $forum, $Name, $thread, $Timestamp,
      $date_fmt, $Lastread;

   $txt = make_html_safe($Text, true);
   if( strlen($txt) == 0 ) $txt = '&nbsp';

   $color = "ff0000";
   $new = get_new_string($Timestamp, $Lastread);

   echo '<tr><td bgcolor=cccccc>
<a name="' . $ID . '"><font size="+1"><b>' . make_html_safe($Subject) . '</b></font>' . $new . '</a><br>
by <a href="' . $HOSTBASE . '/userinfo.php?uid=' . $User_ID . '">' . make_html_safe($Name) . '</a>
on ' . date($date_fmt, $Timestamp) . '</td></tr>
<tr><td bgcolor=white>' . $txt . '</td></tr>
';
   if( $reply_link )
      echo "<tr><td bgcolor=white align=left><a href =\"read.php?forum=$forum&thread=$thread&reply=$ID#$ID\">[ reply ]</a></td></tr>\n";
}




//  input: $forum, $thread, $reply
{
   connect2mysql();


   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( ($reply > 0) and !$logged_in )
      error("not_logged_in");

   $Forumname = forum_name($forum);

   start_page("Reading forum $Forumname", true, $logged_in, $player_row );

   $cols=2;
   $headline   = array("Reading thread" => "colspan=$cols");
   $links = LINK_FORUMS | LINK_THREADS | LINK_EXPAND_VIEW;

   start_table($headline, $links, 'width="99%"', $cols);

   $result = mysql_query("SELECT UNIX_TIMESTAMP(Time) AS Lastread FROM Forumreads " .
                         "WHERE User_ID=" . $player_row["ID"] . " AND Thread_ID=$thread");

   if( mysql_num_rows($result) == 1 )
      extract( mysql_fetch_array( $result ) );

   $result = mysql_query("SELECT Posts.*, " .
                         "UNIX_TIMESTAMP(Posts.Lastchanged) AS Lastchangedstamp, " .
                         "UNIX_TIMESTAMP(Posts.Time) AS Timestamp, " .
                         "Players.Name " .
                         "FROM Posts LEFT JOIN Players ON Posts.User_ID=Players.ID " .
                         "WHERE Forum_ID=$forum AND Thread_ID=$thread " .
                         "ORDER BY PosIndex");

   echo "<tr><td colspan=$cols><table width=\"100%\" cellpadding=2 cellspacing=0 border=0>\n";
   $cur_depth=1;
   while( $row = mysql_fetch_array( $result ) )
   {
      $Name = '?';
      extract($row);

      if( !$Lastchangedthread )
         $Lastchangedthread = $Lastchangedstamp;

      while( $cur_depth < $Depth )
      {
         echo "<tr><td><ul><table width=\"100%\" cellpadding=2 cellspacing=0 border=0>\n";
         $cur_depth++;
      }

      while( $cur_depth > $Depth )
      {
         echo "</table></ul></td></tr>\n";
         $cur_depth--;
      }

      draw_post($reply != $ID);

      if( $reply == $ID )
      {
         echo "<tr><td>\n";
         message_box($forum, $ID, $Subject);
         echo "</td></tr>\n";
      }
   }

   while( $cur_depth > 1 )
   {
      echo "</td></tr></table></ul>\n";
      $cur_depth--;
   }

   echo "</table></td></tr>\n";

   end_table($links, $cols);


// Update Forumreads to remove the 'new' flag

   if( $Lastchangedthread + $new_end > $NOW )
   {
      mysql_query( "REPLACE INTO Forumreads SET " .
                   "User_ID=" . $player_row["ID"] . ", " .
                   "Thread_ID=$thread, " .
                   "Time=FROM_UNIXTIME($NOW)" );
   }

   end_page();
}
?>
