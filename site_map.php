<?php
/*
Dragon Go Server
Copyright (C) 2001  Jim Heiney and Erik Ouchterlony

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

require( "include/std_functions.php" );


function item($text,$link,$working, $level,$last=false)
{
   global $f0, $f1, $f2, $f3;

   $size=25;

   echo "<tr>";

   for($i=0; $i<$level; $i++)
   {
      echo "<td width=50 align=right>" .
         ( ${"f$i"} ? "<img alt=\"&nbsp;|&nbsp;\" src=\"$size/du.gif\">" : "" ) . "</td>";
   }

   echo "<td width=50 align=right><img alt=\"" . ( $last ? "&nbsp;`-" : "&nbsp;|-" ) .
      "\" src=\"$size/" . ( $last ? "dl" : "el" ) . ".gif\"></td>" .
      "<td colspan=" . (4-$level) . ">&nbsp;<a " . ( $working ? "" : "class=dead " ) .
      " href=\"$link\">" .
      "<font color=" . ($working ? "0C41C9" : "black"  ) . ">$text</font></a></td></tr>\n";

   ${"f$level"} = !$last;

}

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   start_page("Site map", true, $logged_in, $player_row );

   $f0=$f1=$f2=$f3=true;


   echo "<table width=80% align=center><tr><td>\n";
   echo "<center><h3><font color=\"#800000\">Site map</font></h3></center>";
   echo "<table cellspacing=0 cellpadding=0 border=0><tr><td colspan=3>" .
      "<a href=\"index.php\"><font color=0C41C9>Welcome page</font></a></td></tr>\n";

   {
      item("Status", "status.php", true, 0);
      {
         item("My user info", "userinfo.php?uid=" . $player_row["ID"], true, 1);
         {
            item("Edit profil", "edit_profile.php", true, 2);
            item("Edit bio", "edit_bio.php", true, 2);
            item("Change password", "edit_password.php", true, 2, true);
         }
         item("Show message", "show_message.php", false, 1);
         item("Game", "game.php", false, 1, true);
      }

      item("Messages", "messages.php", true, 0);
      {
         item("Send a message", "new_message.php", true, 1);
         item("Show message", "show_message.php", false, 1);
         item("Show all messages", "messages.php?all=1", true, 1);
         item("Show sent messages", "messages.php?sent=1", true, 1, true);
      }

      item("Invite", "invite.php", true, 0);

      item("Users", "users.php", true, 0);
      {
         item("User info", "userinfo.php", false, 1, true);
         {
            item("Show running games", "show_games.php", false, 2);
            item("Show finished games", "show_games.php?finished=1", false, 2);
            {
               item("Game", "game.php", false, 3);
               item("SGF file of game", "sgf.php", false, 3, true);
            }
            item("Send a message", "new_message.php", true, 2, true);
         }
      }

      item("Forum", "phorum/index.php", true, 0);
      {
         item("Thread list", "phorum/list.php", false, 1, true);
         {
            item("Read forum", "phorum/read.php", false, 2);
            item("New topic", "phorum/post.php", false, 2, true);
         }
      }

      item("Documentation", "docs.php", true, 0, true);
      {
         item("Introduction", "introduction.php", true, 1);
         item("Site map", "site_map.php", true, 1);
         item("FAQ", "phorum/list.php?f=3", true, 1);
         item("Links", "links.php", true, 1);
         item("Todo list", "todo.php", true, 1);
         item("Licence", "licence.php", true, 1, true);
      }
   }

}

echo "</table>\n";
echo "<p>The black links require an argument to work, so they are not usable\n";
echo "</td></tr></table>\n";

end_page();
?>
