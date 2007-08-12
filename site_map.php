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

$TranslateGroups[] = "Docs";

require_once( "include/std_functions.php" );


function item($text, $link='', $working=true, $last=false)
{
   global $item_nbcols, $item_level;
   static $f = array();

   $size = 25; //see CSS
   $level = min( $item_level, $item_nbcols-1);

   echo "<tr>";
   for( $i=1; $i<$level; $i++ )
   {
      if( @$f[$i] )
         echo "<td class=Indent>&nbsp;&nbsp;&nbsp;</td>";
      else
         echo "<td class=Indent><img alt=\"&nbsp;|&nbsp;\" src=\"$size/du.gif\"></td>";
   }
   if( $level > 0 )
   {
      $f[$level] = $last;
      if( $last )
         echo "<td class=Indent><img alt=\"&nbsp;`-\" src=\"$size/dl.gif\"></td>";
      else
         echo "<td class=Indent><img alt=\"&nbsp;|-\" src=\"$size/el.gif\"></td>";
   }

   $level = $item_nbcols-$level;
   if( $level > 1 )
      echo "<td colspan=$level>";
   else
      echo "<td>";
   if( $working && $link )
      echo "<a href=\"$link\">$text</a>";
   else
      echo "<span class=Inactive>$text</span>";
   echo "</td></tr>\n";
}


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   $ThePage['class']= 'SiteMap'; //temporary solution to CSS problem
   start_page(T_('Site map'), true, $logged_in, $player_row );

   if( !$logged_in )
      error("not_logged_in");

   $id = $player_row["ID"];

   section( 'SiteMap', T_('Site map'));

   $item_nbcols = 5;
   $item_level = 0;
   echo "<table class=SiteMap>";

   item(T_('Welcome page'), "index.php", true);
   { $item_level++;
      item(T_('Status'), "status.php", true);
      { $item_level++;
         item(T_('My user info'), "userinfo.php?uid=$id", true);
         { $item_level++;
            item(T_('Edit profile'), "edit_profile.php", true);
            item(T_('Edit biographical info'), "edit_bio.php", true);
            item(T_('Change password'), "edit_password.php", true);
            item(T_('Edit vacation'), "edit_vacation.php", true, true);
         } $item_level--;
         item(T_('Show running games'), "show_games.php?uid=$id", true);
         item(T_('Show finished games'), "show_games.php?uid=$id".URI_AMP."finished=1", true);
         item(T_('Show observed games'), "show_games.php?uid=$id".URI_AMP."observe=1", true);

         item(T_('Show message'), "message.php?mode=ShowMessage", false);
         item(T_('Game'), "game.php", false);
         item(T_('SGF of game'), "sgf.php", false, true);
      } $item_level--;

      item(T_('Messages'), "list_messages.php", false);
      { $item_level++;
         item(T_('Send a message'), "message.php?mode=NewMessage", true);
         item(T_('Invite'), "message.php?mode=Invite", true);
         item(T_('Show message'), "message.php?mode=ShowMessage", false);
         item(T_('Message list'), "list_messages.php", true, true);
         { $item_level++;
            item(T_('Search messages'), "search_messages.php", true);
            item(T_('Edit folders'), "edit_folders.php", true, true);
         } $item_level--;
      } $item_level--;

      item(T_('Waiting room'), "waiting_room.php", true);

      item(T_('Users'), "users.php", true);
      { $item_level++;
         item(T_('User info'), "userinfo.php", false);
         { $item_level++;
            item(T_('Show opponents'), "opponents.php", false, true);
         } $item_level--;
         item(T_('Show my opponents'), "opponents.php", true, true);
      } $item_level--;

      item(T_('Forum'), "forum/index.php", true);
      { $item_level++;
         item(T_('Thread list'), "forum/list.php", false);
         { $item_level++;
            item(T_('Read forum'), "forum/read.php", false);
            item(T_('New topic'), "forum/post.php", false, true);
         } $item_level--;

         item(T_('Search forums'), "forum/search.php", true, true);
      } $item_level--;

      item(T_('Games'), "show_games.php?uid=all".URI_AMP."finished=1", true);
      { $item_level++;
         item(T_('Show all running games'), "show_games.php?uid=all", true);
         item(T_('Show all finished games'), "show_games.php?uid=all".URI_AMP."finished=1", true);
         item(T_('Show my running games'), "show_games.php?uid=$id", true);
         item(T_('Show my finished games'), "show_games.php?uid=$id".URI_AMP."finished=1", true);
         item(T_('Show my observed games'), "show_games.php?observe=1", true, true);
      } $item_level--;

      item(T_('Translate'), "translate.php", true);

      item(T_('Documentation'), "docs.php", true, true);
      { $item_level++;
         item(T_('Introduction'), "introduction.php", true);
         item(T_('Site map'), "site_map.php", true);
         item(T_('FAQ'), "faq.php", true);
         item(T_('Links'), "links.php", true);
         item(T_('Todo list'), "todo.php", true);
         item(T_('Licence'), "licence.php", true, true);
      } $item_level--;
   } $item_level--;

   echo "</table>\n";
   echo T_('The black links require an argument to work, so they are not usable.');

   end_page();
}
?>
