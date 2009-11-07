<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once( "include/std_functions.php" );


{
   $ThePage = new Page('Scripts');

   connect2mysql();
   $logged_in = who_is_logged($player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & (ADMIN_SUPERADMIN|ADMIN_DATABASE|ADMIN_DEVELOPER)) )
      error('adminlevel_too_low', 'scripts-index');

   $arr_scripts = array(
      'scripts/', array(
         "Translations" => 0,
            'update_translation_pages.php'   => "1. scan pages for translate-groups",
            'generate_translation_texts.php' => "2. scan pages for translation-texts",
            'make_all_translationfiles.php'  => "3. export translations-dir",

         "Server admin" => 0,
            'apc_clear_cache.php'      => "Clear APC cache",
            'sql_game_export.php'      => "Export single game as SQL (for local tests)",
            'check_block_ip.php'       => "Check syntax for IP-blocking of user",
            'data_export.php'          => "Export DDL, translation-data",
            'data_report.php'          => "Show data from database",
            'mailtest.php'             => "Sends test mails",
            'recalculate_ratings2.php' => "Recalculate ratings (use with utter care)",

         "Consistency" => 0,
            'player_consistency.php'   => "Check consistency of Players-data",
            'forum_consistency.php'    => "Check consistency of Forums-data",
            'game_consistency.php'     => "Check consistency of Games-data",
            'message_consistency.php'  => "Check consistency of Message-data",
            'translation_consistency.php' => "Check consistency of Translation-data",
            'fix_games_timeleft.php'   => "Fix Games.TimeOutDate for running games (after long maintenance)",

         "Info" => 0,
            'phpinfo.php'              => "Shows PHP-info",
            'phpinfo.php?module=1&config=1&env=1&var=1' => "Shows PHP-info with ENV and variables (sensitive)",
            'apc_cache_info.php'       => "Show info for APC cache",
            'browser_stats.php'        => "Build browser statistics on all players",
      ),

      'scripts/updates/', array(
         "Migration scripts for release 1.0.15, use <b>ONLY</b> if you know how they work !!<br>\n(see database_changes_1_0_14_to_1_0_15.mysql)" => 0,
            'fix_message_thread-1_0_15.php'  => "Set message-threads/level",
            'fix_game_comments-1_0_15.php'   => "Set game hidden-comments flags",
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
   while( count($arr_dirs) > 1 )
   {
      $dir = $base_path . array_shift($arr_dirs);
      $arr_scripts = array_shift($arr_dirs);
      foreach( $arr_scripts as $key => $val )
      {
         // show only script-name, but keep unique
         $scriptname = preg_replace( '/\?.*$/', '', $key );
         if( isset($chk_scripts[$scriptname]) )
         {
            $chk_scripts[$scriptname]++;
            $scriptname .= ' #' . $chk_scripts[$scriptname];
         }
         else
            $chk_scripts[$scriptname] = 1;

         if( is_numeric($val) && $val == 0 ) // section-title
            printf( '<tr class="Title"><td colspan="3"><hr>%s</td>', $key );
         else // script with description
            printf( '<tr><td></td> <td>%s</td> <td>%s</td>', anchor($dir.$key, $scriptname), $val );
         echo "</tr>\n";
      }
   }
   echo "</table>\n";
}

?>
