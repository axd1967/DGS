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

/*!
 * \file
 *
 * \brief Shows TOC (table of contents) of important scripts in current folder.
 */

chdir('../');
require_once 'include/std_functions.php';


{
   $GLOBALS['ThePage'] = new Page('Scripts');

   connect2mysql();
   $logged_in = who_is_logged( $player_row, LOGIN_DEFAULT_OPTS_ADM_OPS );
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scripts.index');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.index');
   if ( !(@$player_row['admin_level'] & (ADMIN_SUPERADMIN|ADMIN_DATABASE|ADMIN_DEVELOPER)) )
      error('adminlevel_too_low', 'scripts.index');

   $arr_scripts = array(
      'scripts/', array(
         "Translations" => 0,
            'update_translation_pages.php'   => "1. scan pages for translate-groups",
            'generate_translation_texts.php' => "2. scan pages for translation-texts",
            'translation_consistency.php'    => "3. Check consistency of Translation-data",
            'make_all_translationfiles.php'  => "4. export translations-dir",

         "Cache admin" => 0,
            'dgs_cache_admin.php'      => "DGS Cache Administration (clear caches, cleanup, view file-cache)",
            'apc-live.php'             => "APC Manager",
            'apc_cache_info.php'       => "Show APC cache info and content",
            'apc_clear_cache.php'      => "Clear APC cache",
            'clear_datastore.php'      => "Show & Clear Data-Store cache-dir",

         "Server admin" => 0,
            'sql_game_export.php'      => "Export single game as SQL (for local tests)",
            'check_block_ip.php'       => "Check syntax for IP-blocking of user",
            'data_export.php'          => "Export DDL, translation-data",
            'data_report.php'          => "Show data from database",
            'mailtest.php'             => "Sends test mails",
            'recalculate_ratings2.php' => "Recalculate ratings (use with utter care)",
            'check_players_email.php'  => "Check players for invalid email",

         "Consistency" => 0,
            'player_consistency.php'      => "Check consistency of Players-data",
            'forum_consistency.php'       => "Check consistency of Forums-data",
            'game_consistency.php'        => "Check consistency of Games-data",
            'game_stats_consistency.php'  => "Check consistency of GamesStats-data",
            'message_consistency.php'     => "Check consistency of Message-data",
            'tournament_consistency.php'  => "Check consistency of Tournament-data",
            'voting_consistency.php'      => "Check consistency of Feature- & Survey-data",
            'fix_game_snapshot.php'       => "Fix Games.Snapshot",
            'fix_games_timeleft.php'      => "Fix Games.TimeOutDate for running games (after long maintenance)",
            'fix_ladder_seq_wins.php'     => "Fix TournamentLadder.SeqWins/SeqWinsBest for ladder-tournament",

         "Info" => 0,
            'phpinfo.php'              => "Shows PHP-info",
            'phpinfo.php?module=1&config=1&env=1&var=1' => "Shows PHP-info with ENV and variables (sensitive)",
            'server-info.php'          => "Shows Server-info (CPU, Memory)",
            'browser_stats.php'        => "Build browser statistics on all players",
      ),

      'scripts/updates/', array(
         "Migration scripts for release 1.0.15, use <b>ONLY</b> if you know how they work !!<br>\n(see 1.0.15/database_changes.mysql)" => 0,
            '1.0.15/fix_message_thread.php'  => "Set message-threads/level",
            '1.0.15/fix_game_comments.php'   => "Set game hidden-comments flags",
         "Migration scripts for release 1.0.16, use <b>ONLY</b> if you know how they work !!<br>\n(see 1.0.16/database_changes.mysql)" => 0,
            '1.0.16/fix_new_game_expert_view.php' => "Replace deprecated new-game expert-view",
            '1.0.16/fix_default_max_handi.php'    => "Set default max-handicap",
            '1.0.16/fix_game_invitations.php'     => "Migrate old 1.0.15-style game-invitations",
         "Migration scripts for release 1.0.18, use <b>ONLY</b> if you know how they work !!<br>\n(see 1.0.18/database_changes.mysql)" => 0,
            '1.0.18/fix_hero_ratio.php'      => "Sets Players.WeakerGames for hero awards and enrich game-setup",
         "Migration scripts for release 1.0.19, use <b>ONLY</b> if you know how they work !!<br>\n" => 0,
            '1.19/seed_game_stats.php'       => "Drops and fill GameStats table (need server down)!",
      ),
   ); //arr_scripts

   start_page('DragonGoServer Admin Scripts', true, $logged_in, $player_row );

   $title = "Admin Scripts";
   section( $title, $title );

   echo_scripts( $arr_scripts );

   end_page();
}

function echo_scripts( $arr_dirs )
{
   global $base_path;

   $chk_scripts = array();
   echo '<table id="Scripts"><tr><th>Category</th> <th>Script</th> <th>Description</th></tr>', "\n";
   while ( count($arr_dirs) > 1 )
   {
      $dir = $base_path . array_shift($arr_dirs);
      $arr_scripts = array_shift($arr_dirs);
      foreach ( $arr_scripts as $key => $val )
      {
         // show only script-name, but keep unique
         $scriptname = preg_replace( '/\?.*$/', '', $key );
         if ( isset($chk_scripts[$scriptname]) )
         {
            $chk_scripts[$scriptname]++;
            $scriptname .= ' #' . $chk_scripts[$scriptname];
         }
         else
            $chk_scripts[$scriptname] = 1;

         if ( is_numeric($val) && $val == 0 ) // section-title
            printf( '<tr class="Title"><td colspan="3"><hr>%s</td>', $key );
         else // script with description
            printf( '<tr><td></td> <td>%s</td> <td>%s</td>',
               anchor($dir.$key, $scriptname, '', 'target="_blank"'), $val );
         echo "</tr>\n";
      }
   }
   echo "</table>\n";
}//echo_scripts

?>
