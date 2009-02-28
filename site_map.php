<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

$TranslateGroups[] = "Docs";

require_once( "include/std_functions.php" );
$ThePage = new Page('SiteMap');


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
         item(T_('Show my running games'), "show_games.php?uid=$id", true);
         item(T_('Show my finished games'), "show_games.php?uid=$id".URI_AMP."finished=1", true);
         item(T_('Show games I\'m observing'), "show_games.php?uid=$id".URI_AMP."observe=1", true);

         item(T_('Show message'), "message.php?mode=ShowMessage", false);
         item(T_('Show game (follow id)'), "game.php", false, true);
         { $item_level++;
            item(T_('Add time for opponent'), "game.php", false);
            item(T_('Download SGF of game'), "sgf.php", false);
            item(T_('Show observers'), "users.php", false);
            item(T_('Show game info'), "gameinfo.php", false, true);
         } $item_level--;
      } $item_level--;

      item(T_('Waiting room'), "waiting_room.php", true);

      item(T_('My user info'), "userinfo.php?uid=$id", true);
      { $item_level++;
         item(T_('Show my rating graph'), "ratinggraph.php?uid=$id", true);
         item(T_('Show my running games'), "show_games.php?uid=$id", true);
         item(T_('Show my finished games'), "show_games.php?uid=$id".URI_AMP."finished=1", true);
         item(T_('Show my rated games'), "show_games.php?uid=$id".URI_AMP."finished=1".URI_AMP."rated=1", true);
         item(T_('Show my won games'), "show_games.php?uid=$id".URI_AMP."finished=1".URI_AMP."rated=1".URI_AMP."won=1", true);
         item(T_('Show my lost games'), "show_games.php?uid=$id".URI_AMP."finished=1".URI_AMP."rated=1".URI_AMP."won=2", true);
         item(T_('Edit profile'), "edit_profile.php", true);
         item(T_('Edit biographical info'), "edit_bio.php", true);
         item(T_('Change password'), "edit_password.php", true);
         item(T_('Edit vacation'), "edit_vacation.php", true);
         item(T_('Show my opponents'), "opponents.php", true, true);
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

      item(T_('Users'), "users.php", true);
      { $item_level++;
         item(T_('Other user info'), "userinfo.php", false);
         { $item_level++;
            item(T_('Show rating graph'), "ratinggraph.php", false);
            item(T_('Show running games'), "show_games.php", false);
            item(T_('Show finished games'), "show_games.php?finished=1", false);
            item(T_('Show opponents'), "opponents.php", false);
            item(T_('Invite user'), "message.php?mode=Invite", false);
            item(T_('Send message to user'), "message.php?mode=NewMessage", false);
            item(T_('Add/edit contact'), "edit_contact.php", false, true);
         } $item_level--;
         item(T_('Show my opponents'), "opponents.php", true, true);
         { $item_level++;
            item(T_('Show game statistics for two players'), "opponents.php", false, true);
         } $item_level--;
      } $item_level--;

      item(T_('Contacts'), "list_contacts.php", true);
      { $item_level++;
         item(T_('Send a message to contact'), "message.php?mode=NewMessage", false);
         item(T_('Invite contact'), "message.php?mode=Invite", false);
         item(T_('Add new contact'), "edit_contact.php", true, true);
      } $item_level--;

      item(T_('Games'), "show_games.php?uid=all".URI_AMP."finished=1", true);
      { $item_level++;
         item(T_('Show users running games'), "show_games.php", false);
         item(T_('Show users finished games'), "show_games.php?finished=1", false);
         item(T_('Show my running games'), "show_games.php?uid=$id", true);
         item(T_('Show my finished games'), "show_games.php?uid=$id".URI_AMP."finished=1", true);
         item(T_('Show all running games'), "show_games.php?uid=all", true);
         item(T_('Show all finished games'), "show_games.php?uid=all".URI_AMP."finished=1", true);
         item(T_('Show games I\'m observing'), "show_games.php?observe=$id", true);
         item(T_('Show all observed games'), "show_games.php?observe=all", true);
         item(T_('Show userinfo'), "userinfo.php?uid=$id", false);
         item(T_('Invite user'), "message.php?mode=Invite", false, true);
      } $item_level--;

      item(T_('Forum'), "forum/index.php", true);
      { $item_level++;
         item(T_('Thread list'), "forum/list.php", false);
         { $item_level++;
            item(T_('Read forum'), "forum/read.php", false); //?forum=fid&thread=tid
            item(T_('New topic'), "forum/read.php", false, true); //?forum=fid without threadid
         } $item_level--;
         item(T_('Search forums'), "forum/search.php", true, true);
      } $item_level--;

      if( @$player_row['Translator'] )
         item(T_('Translate'), "translate.php", true);

      item(T_('Statistics'), "statistics.php", true);

      item(T_('Documentation'), "docs.php", true, true);
      { $item_level++;
         item(T_('Introduction'), "introduction.php", true);
         item(T_('Terms of Service - Rules of Conduct and Privacy Policy'), "policy.php", true);
         if( ENABLE_DONATIONS )
            item(T_('Donation'), 'donation.php', true);
         item(T_('News, Release notes'), "news.php", true);
         item(T_('Site map'), "site_map.php", true);
         item(T_('FAQ'), "faq.php", true);
         //item(T_//('Goodies'), "goodies/index.php", true); // not a DGS-feature
         item(T_('Links'), "links.php", true);
         item(T_('People'), "people.php", true);
         item(T_('Todo list'), "todo.php", true);
         item(T_('License'), "licence.php", true, true);
      } $item_level--;
   } $item_level--;

   echo "</table>\n";
   echo T_('The black links require an argument to work, so they are not usable here.');

   end_page();
}
?>
