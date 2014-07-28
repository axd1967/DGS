<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Jens-Uwe Gaspar

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

chdir( '../../' );
require_once 'include/std_functions.php';
require_once 'include/game_functions.php';
require_once 'include/deprecated_functions.php';

/*!
 * \file fix_hero_ratio-1_0_18.php
 *
 * \brief Script to set Players.WeakerGames introduced with release 1.0.18, and to enrich game-setups with hero-ratio.
 */


$GLOBALS['ThePage'] = new Page('Script', PAGEFLAG_IMPLICIT_FLUSH );


{
   disable_cache();
   connect2mysql();
   set_time_limit(0); // don't want script-break during "transaction" with multi-db-queries or for large-datasets

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scripts.fix_hero_ratio-1_0_18');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.fix_hero_ratio-1_0_18');
   if ( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'scripts.fix_hero_ratio-1_0_18');

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();


   start_html( 'fix_hero_ratio', false );

//echo ">>>> One shot fix. Do not run it again."; end_html(); exit;

   $do_it = @$_REQUEST['do_it'];
   $action = (int)@$_REQUEST['action'];
   if ( $do_it )
   {
      function dbg_query($s) {
         if ( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
         if ( DBG_QUERY>1 ) error_log("dbg_query(DO_IT): $s");
      }
   }
   else
   {
      function dbg_query($s) {
         echo "<BR>$s; ";
         if ( DBG_QUERY>1 ) error_log("dbg_query(SIMUL): $s");
      }
   }

   $page_args1 = array_merge( $page_args, array( 'action' => 1 ) );
   $tmp1 = array_merge($page_args1, array('do_it' => 1));
   $page_args2 = array_merge( $page_args, array( 'action' => 2 ) );
   $tmp2 = array_merge($page_args2, array('do_it' => 1));
   echo "<p>(just show needed queries)"
      ."<br>".anchor(make_url($page, $page_args1), 'Check/Fix Players.GamesWeaker')
      ."<br>".anchor(make_url($page, $tmp1), '[Validate Fix]')
      ."<br>"
      ."<br>".anchor(make_url($page, $page_args2), 'Enrich GameSetup with hero-ratio')
      ."<br>".anchor(make_url($page, $tmp2), '[Validate Enrich-Fix]')
      ."</p>";


   ta_begin();
   {//HOT-section for fix-action
      if ( $action == 1 )
      {
         $cnt_all = fix_hero_ratio( $do_it );
         echo "<br><br>Found $cnt_all Players-entries in total to fix ...\n";
      }
      elseif ( $action == 2 )
      {
         $cnt_all = fix_enrich_game_setup_hero_ratio( $do_it );
         echo "<br><br>Found $cnt_all entries in total to fix ...\n";
      }
   }
   ta_end();

   if ( $do_it )
      echo "\nFix done.\n";

   end_html();
}//main


function fix_hero_ratio( $do_it )
{
   $cnt_games = 0;
   $hu_cnt = array(); // uid => weaker-games-count

   // find finished games (non-MPGs)
   $result = db_query("scripts.fix_hero_ratio-1_0_18.load_games_finished()",
      "SELECT G.Black_ID, G.White_ID, G.Black_Start_Rating, G.White_Start_Rating " .
      "FROM Games AS G " .
      "WHERE G.Status='".GAME_STATUS_FINISHED."' AND G.GameType='".GAMETYPE_GO."'" );
   while ( $grow = mysql_fetch_assoc($result) )
   {
      extract($grow);

      $hero_uid = GameHelper::determine_finished_game_hero_uid( $Black_ID, $White_ID, $Black_Start_Rating, $White_Start_Rating );
      if ( $hero_uid > 0 )
      {
         if ( !isset($hu_cnt[$hero_uid]) )
            $hu_cnt[$hero_uid] = 1;
         else
            $hu_cnt[$hero_uid]++;
      }

      if ( (++$cnt_games % 10000) == 0 )
         echo "<br><br>... $cnt_games scanned ...\n";
   }
   mysql_free_result($result);

   $upd_pl = array(); // cnt => array( uid, ... ) to update with cnt
   foreach ( $hu_cnt as $uid => $cnt )
   {
      if ( !isset($upd_pl[$cnt]) )
         $upd_pl[$cnt] = array( $uid );
      else
         $upd_pl[$cnt][] = $uid;
   }

   // reset Players.GamesWeaker
   dbg_query( "UPDATE Players SET GamesWeaker=0" );

   $cnt_fix = 0;
   foreach ( $upd_pl as $cnt => $arr_uid )
   {
      $cnt_fix += count($arr_uid);
      update_players_games_weaker( $do_it, $cnt, $arr_uid );
   }

   return $cnt_fix;
}//fix_hero_ratio

/*!
 * \brief Migrates single game-invitation from old 1.0.15 or former style to 1.0.16 style using GameInvitation-table.
 * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
 */
function update_players_games_weaker( $do_it, $cnt, $arr_uid )
{
   $cnt = (int)$cnt;

   echo sprintf("# Update Players.GamesWeaker = %s for #%s entries:\n", $cnt, count($arr_uid) );

   $block_size = 200;
   while ( count($arr_uid) > 0 )
   {
      if ( count($arr_uid) > $block_size )
         $proc_arr = array_splice( $arr_uid, 0, $block_size );
      else
      {
         $proc_arr = $arr_uid;
         $arr_uid = array();
      }

      // update Players-table
      dbg_query( "UPDATE Players SET GamesWeaker=$cnt WHERE ID IN (" . join(',', $proc_arr) . ") LIMIT $block_size" );
   }

   echo "<br><br>\n";
}//update_players_games_weaker



function fix_enrich_game_setup_hero_ratio( $do_it )
{
   $cnt_fix = 0;

   // fix Games.GameSetup
   $result = db_query("scripts.fix_hero_ratio-1_0_18.load_games_gamesetup",
      "SELECT ID, GameSetup FROM Games " .
      "WHERE GameSetup>'' AND NOT GameSetup LIKE '%:H\%%'" );
   while ( $row = mysql_fetch_assoc( $result ) )
   {
      $gid = $row['ID'];
      $gs = $row['GameSetup'];
      $enriched_gs = DeprecatedGameSetup::enrich_game_setup_hero_ratio( $gs );
      if ( !is_null($enriched_gs) )
      {
         $cnt_fix++;
         dbg_query( "UPDATE Games SET GameSetup='".mysql_addslashes($enriched_gs)."' WHERE ID=$gid LIMIT 1" );
      }
   }
   mysql_free_result($result);

   // fix Profiles game-setup
   $result = db_query("fix_default_max_handi.load_profiles_gamesetup",
      "SELECT ID, Text FROM Profiles " .
      "WHERE Type IN (".PROFTYPE_TMPL_INVITE.",".PROFTYPE_TMPL_NEWGAME.") AND NOT Text LIKE '%:H\%%'" );
   while ( $row = mysql_fetch_assoc( $result ) )
   {
      $id = $row['ID'];
      $gs = $row['Text'];
      $enriched_gs = DeprecatedGameSetup::enrich_game_setup_hero_ratio( $gs );
      if ( !is_null($enriched_gs) )
      {
         $cnt_fix++;
         dbg_query( "UPDATE Profiles SET Text='".mysql_addslashes($enriched_gs)."' WHERE ID=$id LIMIT 1" );
      }
   }
   mysql_free_result($result);

   return $cnt_fix;
}//fix_enrich_game_setup_hero_ratio

?>
