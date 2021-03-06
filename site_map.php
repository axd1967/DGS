<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/std_functions.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('SiteMap');


function echo_item($text, $link, $show_link, $working=true, $last=false)
{
   global $item_nbcols, $item_level;
   static $f = array();

   $size = 25; //see CSS
   $level = min( $item_level, $item_nbcols-1);

   echo "<tr>";
   for ( $i=1; $i<$level; $i++ )
   {
      if ( @$f[$i] )
         echo "<td class=Indent>&nbsp;&nbsp;&nbsp;</td>";
      else
         echo "<td class=Indent><img alt=\"&nbsp;|&nbsp;\" src=\"$size/du.gif\"></td>";
   }
   if ( $level > 0 )
   {
      $f[$level] = $last;
      if ( $last )
         echo "<td class=Indent><img alt=\"&nbsp;`-\" src=\"$size/dl.gif\"></td>";
      else
         echo "<td class=Indent><img alt=\"&nbsp;|-\" src=\"$size/el.gif\"></td>";
   }

   $level = $item_nbcols-$level;
   if ( $level > 1 )
      echo "<td colspan=$level>";
   else
      echo "<td>";
   if ( $show_link && $working && $link )
      echo "<a href=\"$link\">$text</a>";
   else
      echo span('Inactive', $text);
   echo "</td></tr>\n";
} //echo_item

// need login to show link
function item($text, $link='', $working=true, $last=false)
{
   global $logged_in;
   return echo_item( $text, $link, $logged_in, $working, $last);
}

// don't need login, always show link
function itemL($text, $link='', $working=true, $last=false)
{
   return echo_item( $text, $link, true, $working, $last);
}



{
   connect2mysql();

   $logged_in = who_is_logged( $player_row, LOGIN_DEFAULT_OPTS|LOGIN_SKIP_VFY_CHK );
   $id = (int)@$player_row['ID'];
   $uhandle = @$player_row['Handle'];

   start_page(T_('Site map'), true, $logged_in, $player_row );

   section( 'SiteMap', T_('Site map'));

   $note = T_('Note') . ': ';
   if ( $logged_in )
      $note .= T_('The black links require an argument to work, so they are not usable here.');
   else
   {
      $note .= T_('The black links require to be logged in, so they are not usable here.') .
         "<br>\n" . T_('Note') . ': ' . T_('More links will be available if you\'re logged in.');
   }
   echo span('Note', $note), "<br><br>\n";

   $item_nbcols = 6;
   $item_level = 0;
   echo "<table class=SiteMap>";

   item(T_('Welcome page'), "index.php", true);
   { $item_level++;
      itemL(T_('Register new account'), "register.php", true);
      { $item_level++;
         item(T_('Verify email'), "verify_email.php?uid=$id", true, true);
      } $item_level--;
      itemL(T_('Forgot password?'), "forgot.php", true);
      itemL(T_('Introduction'), "introduction.php", true);
      item(T_('Status'), "status.php", true);
      { $item_level++;
         item(T_('My user info'), "userinfo.php?uid=$id", true);
         item(T_('My running games'), "show_games.php?uid=$id", true);
         item(T_('My finished games'), "show_games.php?uid=$id".URI_AMP."finished=1", true);
         item(T_('Games I\'m observing'), "show_games.php?observe=$id", true);
         if ( ALLOW_TOURNAMENTS )
            item(T_('My tournaments'), "tournaments/list_tournaments.php?uid=$id", true);
         item(T_('My running multi-player-games'), "show_games.php?uid=$id".URI_AMP."mp=1", true);
         item(T_('My finished multi-player-games'), "show_games.php?uid=$id".URI_AMP."finished=1".URI_AMP."mp=1", true);
         item(T_('Show message'), "message.php?mode=ShowMessage", false);
         item(T_('Show game (follow id)'), "game.php", false, true);
         { $item_level++;
            item(T_('Add time for opponent'), "game.php", false);
            item(T_('Download SGF of game'), "sgf.php", false);
            item(T_('Show observers'), "users.php", false);
            item(T_('Show game info'), "gameinfo.php", false);
            { $item_level++;
               item(T_('Rematch'), "game_rematch.php?mode=".REMATCH_INVITE, false);
               item(T_('Copy as new game#rematch'), "game_rematch.php".REMATCH_NEWGAME, false);
               item(T_('Show rating changes'), "rating_changes.php?b=".urlencode($uhandle), true, true);
            } $item_level--;
            item(T_('Show game-players'), "game_players.php", false);
            item(T_('Attach SGF'), "manage_sgf.php", false, true);
         } $item_level--;
      } $item_level--;

      item(T_('Waiting room'), "waiting_room.php", true);
      { $item_level++;
         item(T_('New Game'), "new_game.php", true);
         item(T_('All waiting games'), "waiting_room.php?good=0", true);
         item(T_('Suitable waiting games'), "waiting_room.php?good=1", true);
         item(T_('My waiting games'), "waiting_room.php?good=2", true, true);
      } $item_level--;

      if ( ALLOW_TOURNAMENTS )
      {
      item(T_('Tournaments'), "tournaments/list_tournaments.php", true);
      { $item_level++;
         item(T_('All tournaments'), "tournaments/list_tournaments.php", true);
         item(T_('My tournaments'), "tournaments/list_tournaments.php?uid=$id", true);
         item(T_('Managing tournaments'), "tournaments/list_tournaments.php?tdir=$id", true);
         if ( TournamentUtils::check_create_tournament() )
            item(T_('Create new tournament'), "tournaments/wizard.php", true);
         item(T_('View tournament'), "tournaments/view_tournament.php", false, true);
         { $item_level++;
            item(T_('Tournament directors'), "tournaments/list_directors.php", false);
            { $item_level++;
               item(T_('Send a message'), "message.php?mode=NewMessage", false);
               item(T_('Add tournament director'), "tournaments/edit_directors.php", false);
               item(T_('Edit tournament director'), "tournaments/edit_directors.php", false);
               item(T_('Remove Tournament Director'), "tournaments/edit_directors.php?td_delete=1", false, true);
            } $item_level--;
            item(T_('Manage this tournament'), "tournaments/manage_tournament.php", false);
            { $item_level++;
               item(T_('Change Status'), "tournaments/edit_status.php", false);
               item(T_('Edit tournament'), "tournaments/edit_tournament.php", false);
               item(T_('Add news#tnews'), "tournaments/edit_news.php", false);
               item(T_('Show tournament news'), "tournaments/list_news.php", false);
               item(T_('Add tournament director'), "tournaments/edit_directors.php", false);
               item(T_('Show tournament directors'), "tournaments/list_directors.php", false);
               item(T_('Edit registration properties#tourney'), "tournaments/edit_properties.php", false);
               item(T_('Edit rules#tourney'), "tournaments/edit_rules.php", false);
               item(T_('Edit participants'), "tournaments/edit_participant.php", false);
               { $item_level++;
                  item(T_('Show users'), "users.php", false);
                  item(T_('Show my opponents'), "opponents.php", false);
                  item(T_('Show my contacts'), "list_contacts.php", false, true);
               } $item_level--;
               item(T_('Show tournament participants'), "tournaments/list_participants.php", false);
               item(T_('Edit results#tourney'), "tournaments/edit_results.php", false, true);
            } $item_level--;
            item(T_('Tournament participants'), "tournaments/list_participants.php", false);
            item(T_('Registration#tourney'), "tournaments/register.php", false);
            item(T_('Edit participants'), "tournaments/edit_participant.php", false);
            item(T_('Show all tournament results'), "tournaments/list_results.php", false, true);
         } $item_level--;
      } $item_level--;
      }

      item(T_('My user info'), "userinfo.php?uid=$id", true);
      { $item_level++;
         item(T_('My rating graph'), "ratinggraph.php?uid=$id", true);
         item(T_('My running games'), "show_games.php?uid=$id", true);
         item(T_('My finished games'), "show_games.php?uid=$id".URI_AMP."finished=1", true);
         item(T_('My rated games'), "show_games.php?uid=$id".URI_AMP."finished=1".URI_AMP."rated=1".REQF_URL.'rated', true);
         item(T_('My won games'), "show_games.php?uid=$id".URI_AMP."finished=1".URI_AMP."rated=1".URI_AMP."won=1".REQF_URL.'rated,won', true);
         item(T_('My lost games'), "show_games.php?uid=$id".URI_AMP."finished=1".URI_AMP."rated=1".URI_AMP."won=2".REQF_URL.'rated,won', true);
         item(T_('My running multi-player-games'), "show_games.php?uid=$id".URI_AMP."mp=1", true);
         item(T_('My finished multi-player-games'), "show_games.php?uid=$id".URI_AMP."finished=1".URI_AMP."mp=1", true);
         item(T_('Edit profile'), "edit_profile.php", true);
         item(T_('Change rating & rank'), "edit_rating.php", true);
         item(T_('Change email & notifications'), "edit_email.php", true);
         item(T_('Change password'), "edit_password.php", true);
         item(T_('Edit biographical info'), "edit_bio.php", true);
         item(T_('Edit biographical order'), "edit_bio.php?editorder=1", true);
         item(T_('Edit user picture'), "edit_picture.php", true);
         item(T_('Edit vacation'), "edit_vacation.php", true);
         item(T_('Show my opponents'), "opponents.php", true);
         item(T_('Show rating changes'), "rating_changes.php?b=".urlencode($uhandle), true, true);
      } $item_level--;

      item(T_('Messages'), "list_messages.php", true);
      { $item_level++;
         item(T_('Send a message'), "message.php?mode=NewMessage", true);
         item(T_('Invite'), "message.php?mode=Invite", true);
         item(T_('Show message'), "message.php?mode=ShowMessage", false);
         item(T_('Message list'), "list_messages.php", true);
         { $item_level++;
            item(T_('Show message thread'), "message_thread.php", false);
            item(T_('Search messages'), "search_messages.php", true);
            item(T_('Edit folders'), "edit_folders.php", true, true);
         } $item_level--;
         item(T_('Templates'), "templates.php", true, true);
      } $item_level--;

      item(T_('New Game'), "new_game.php", true);
      { $item_level++;
         item(T_('New Game'), "new_game.php", true);
         item(T_('New fair-komi game'), "new_game.php?view=".GSETVIEW_FAIRKOMI, true);
         item(T_('New multi-player-game'), "new_game.php?view=".GSETVIEW_MPGAME, true);
         item(T_('Shapes'), "list_shapes.php", true);
         item(T_('Templates'), "templates.php", true, true);
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
         item(T_('Show my rating position'), "users.php?showpos=1", true);
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
         item(T_('Users running games'), "show_games.php", false);
         item(T_('Users finished games'), "show_games.php?finished=1", false);
         item(T_('My running games'), "show_games.php?uid=$id", true);
         item(T_('My finished games'), "show_games.php?uid=$id".URI_AMP."finished=1", true);
         item(T_('My running multi-player-games'), "show_games.php?uid=$id".URI_AMP."mp=1", true);
         item(T_('My finished multi-player-games'), "show_games.php?uid=$id".URI_AMP."finished=1".URI_AMP."mp=1", true);
         item(T_('All running games'), "show_games.php?uid=all", true);
         item(T_('All finished games'), "show_games.php?uid=all".URI_AMP."finished=1", true);
         item(T_('All finished games with attached SGFs (reviews)'), "show_games.php?uid=all".URI_AMP."finished=1".URI_AMP."sgfs=1", true);
         item(T_('Games I\'m observing'), "show_games.php?observe=$id", true);
         item(T_('All observed games'), "show_games.php?observe=all", true);
         item(T_('Show user info'), "userinfo.php?uid=$id", false);
         item(T_('Invite user'), "message.php?mode=Invite", false, true);
      } $item_level--;

      item(T_('Shapes'), "list_shapes.php", true);
      { $item_level++;
         item(T_('All shapes'), "list_shapes.php?pub=0", true);
         item(T_('My shapes'), "list_shapes.php?user=".urlencode($uhandle).URI_AMP."pub=0", ((string)$uhandle != '') );
         item(T_('New Shape (Goban Editor)'), "goban_editor.php", true);
         item(T_('View Shape'), "view_shape.php", false);
         { $item_level++;
            item(T_('Invite'), "message.php?mode=Invite", false);
            item(T_('New Shape-Game'), "new_game.php", false, true);
         } $item_level--;
         item(T_('Edit Shape'), "edit_shape.php", false, true);
      } $item_level--;

      itemL(T_('Introduction'), "introduction.php", true);

      itemL(T_('Help / FAQ'), "faq.php", true);

      itemL(T_('Site map'), "site_map.php", true);

      itemL(T_('Documentation'), "docs.php", true);
      { $item_level++;
         itemL(T_('Introduction'), "introduction.php", true);
         itemL(T_('Terms of Service - Rules of Conduct and Privacy Policy'), "policy.php", true);
         if ( ENABLE_DONATIONS )
            item(T_('Donation'), 'donation.php', true);
         itemL(T_('News, Release notes'), "news.php", true);
         itemL(T_('Site map'), "site_map.php", true);
         itemL(T_('Help / FAQ'), "faq.php", true);
         //item(T_//('Goodies'), "goodies/index.php", true); // not a DGS-feature
         itemL(T_('Links'), "links.php", true);
         itemL(T_('People'), "people.php", true);
         itemL(T_('Statistics'), "statistics.php", true);
         if ( strpos(HOSTBASE,'dragongoserver.net') !== false )
            itemL(T_('Web-Statistics'), HOSTBASE."stat/", true);
         itemL(T_('Translation-Statistics'), "translation_stats.php", true);
         itemL(T_('DGS Rank Converter'), "rank_converter.php", true);
         itemL(T_('DGS Wish list'), "http://senseis.xmp.net/?DGSWishlist", true);
         itemL(T_('Installation instructions'), "install.php", true);
         itemL(T_('Download dragon sources'), "snapshot.php", true);
         itemL(T_('License'), "licence.php", true, true);
      } $item_level--;

      item(T_('Forum'), "forum/index.php", true);
      { $item_level++;
         item(T_('Thread list'), "forum/list.php", false);
         { $item_level++;
            item(T_('Read forum'), "forum/read.php", false); //?forum=fid&thread=tid
            item(T_('New Topic'), "forum/read.php", false, true); //?forum=fid without threadid
         } $item_level--;
         item(T_('Search forums'), "forum/search.php", true);
         item(T_('Goban Editor'), "goban_editor.php", true, !ALLOW_SURVEY_VOTE);
         if ( ALLOW_SURVEY_VOTE )
            item(T_('Surveys'), "list_surveys.php", true, true);
      } $item_level--;

      item(T_('Bulletins'), "list_bulletins.php?read=2".URI_AMP."no_adm=1", true);
      { $item_level++;
         item(T_('Unread Bulletins (show with text)'), "list_bulletins.php?text=1".URI_AMP."view=1".URI_AMP."no_adm=1", true);
         item(T_('My Bulletins'), "list_bulletins.php?text=0".URI_AMP."read=2".URI_AMP."mine=1".URI_AMP."no_adm=1", true, true);
      } $item_level--;

      if ( ALLOW_FEATURE_VOTE )
      {
         item(T_('Feature Vote Result'), "features/list_votes.php", true);
         { $item_level++;
            item(T_('Vote on features'), "features/list_features.php?status=2".URI_AMP.'my_vote=1', true);
            item(T_('My feature votes'), "features/list_features.php?status=3".URI_AMP.'my_vote=2', true);
            item(T_('All features'),     "features/list_features.php?status=3".URI_AMP.'my_vote=0', true, true);
         } $item_level--;
      }

      if ( ALLOW_GAME_EDITOR )
         item(T_('Game Editor'), "game_editor.php", true);
      item(T_('Goban Editor'), "goban_editor.php", true);

      if ( @$player_row['admin_level'] )
         item(T_('Admin'), "admin.php", true);
      if ( @$player_row['Translator'] )
         item(T_('Translate'), "translate.php", true);

      itemL(T_('Logout'), "index.php?logout=t", true, true);

   } $item_level--;

   echo "</table>\n";

   end_page();
}
?>
