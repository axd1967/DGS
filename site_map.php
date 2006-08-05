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

$TranslateGroups[] = "Docs";

require_once( "include/std_functions.php" );


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
      "<td colspan=" . (4-$level) . ">&nbsp;";

   if( $working )
      echo "<a href=\"$link\"><font color=\"#0C41C9\">$text</font></a>";
   else
      echo "<font color=\"black\">$text</font>";

   echo "</td></tr>\n";

   ${"f$level"} = !$last;
}

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   start_page(T_('Site map'), true, $logged_in, $player_row );

   if( !$logged_in )
      error("not_logged_in");

   $f0=$f1=$f2=$f3=true;
   $id = $player_row["ID"];

   echo "<table width=\"80%\" align=\"center\"><tr><td>\n";
   echo "<center><h3><font color=$h3_color>" . T_('Site map') . "</font></h3></center>";
   echo "<table cellspacing=0 cellpadding=0 border=0><tr><td colspan=3>" .
      "<a href=\"index.php\"><font color=0C41C9>" . T_('Welcome page') .
      "</font></a></td></tr>\n";

   {
      item(T_('Status'), "status.php", true, 0);
      {
         item(T_('My user info'), "userinfo.php?uid=$id", true, 1);
         {
            item(T_('Edit profile'), "edit_profile.php", true, 2);
            item(T_('Edit bio'), "edit_bio.php", true, 2);
            item(T_('Change password'), "edit_password.php", true, 2);
            item(T_('Edit vacation'), "edit_vacation.php", true, 2, true);
         }
         item(T_('Show running games'), "show_games.php?uid=$id", true, 1);
         item(T_('Show finished games'), "show_games.php?uid=$id".URI_AMP."finished=1", true, 1);
         item(T_('Show observed games'), "show_games.php?uid=$id".URI_AMP."observe=1", true, 1);

         item(T_('Show message'), "message.php?mode=ShowMessage", false, 1);
         item(T_('Game'), "game.php", false, 1, false);
         item(T_('SGF file of game'), "sgf.php", false, 1, true);
      }

      item(T_('Messages'), "message.php", true, 0);
      {
         item(T_('Send a message'), "message.php?mode=NewMessage", true, 1);
         item(T_('Invite'), "message.php?mode=Invite", true, 1);
         item(T_('Show message'), "message.php?mode=ShowMessage", false, 1);
         item(T_('Message list'), "list_messages.php", true, 1, true);
         {
            item(T_('Edit message folders'), "edit_folders.php", true, 2, true);
         }
      }

      item(T_('Waiting room'), "waiting_room.php", true, 0);

      item(T_('Users'), "users.php", true, 0);
      {
         item(T_('User info'), "userinfo.php", false, 1, true);
      }

      item(T_('Forum'), "forum/index.php", true, 0);
      {
         item(T_('Thread list'), "forum/list.php", false, 1, true);
         {
            item(T_('Read forum'), "forum/read.php", false, 2);
            item(T_('New topic'), "forum/post.php", false, 2, true);
         }
      }

      item(T_('Games'), "show_games.php?uid=all".URI_AMP."finished=1", true, 0);

      item(T_('Translate'), "translate.php", true, 0);

      item(T_('Documentation'), "docs.php", true, 0, true);
      {
         item(T_('Introduction'), "introduction.php", true, 1);
         item(T_('Site map'), "site_map.php", true, 1);
         item(T_('FAQ'), "faq.php", true, 1);
         item(T_('Links'), "links.php", true, 1);
         item(T_('Todo list'), "todo.php", true, 1);
         item(T_('Licence'), "licence.php", true, 1, true);
      }
   }

}

echo "</table>\n<p>";
echo T_('The black links require an argument to work, so they are not usable.') . "\n";
echo "</td></tr></table>\n";

end_page();
?>
