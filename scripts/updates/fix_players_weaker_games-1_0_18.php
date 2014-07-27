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

/*!
 * \file fix_players_weaker_games-1_0_18.php
 *
 * \brief Script to set Players.WeakerGames introduced with release 1.0.18.
 */


$GLOBALS['ThePage'] = new Page('Script', PAGEFLAG_IMPLICIT_FLUSH );


{
   disable_cache();
   connect2mysql();
   set_time_limit(0); // don't want script-break during "transaction" with multi-db-queries or for large-datasets

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scripts.fix_players_weaker_games-1_0_18');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.fix_players_weaker_games-1_0_18');
   if ( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'scripts.fix_players_weaker_games-1_0_18');

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();


   start_html( 'fix_players_weaker_games', false );

//echo ">>>> One shot fix. Do not run it again."; end_html(); exit;

   $do_it = @$_REQUEST['do_it'];
   if ( $do_it )
   {
      function dbg_query($s) {
         if ( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
         if ( DBG_QUERY>1 ) error_log("dbg_query(DO_IT): $s");
      }
      echo "<p>*** Fixes weaker-games-counter ***"
         ."<br>".anchor(make_url($page, $page_args), 'Just show it')
         ."</p>";
   }
   else
   {
      function dbg_query($s) {
         echo "<BR>$s; ";
         if ( DBG_QUERY>1 ) error_log("dbg_query(SIMUL): $s");
      }
      $tmp = array_merge($page_args,array('do_it' => 1));
      echo "<p>(just show needed queries)"
         ."<br>".anchor(make_url($page, $page_args), 'Show it again')
         ."<br>".anchor(make_url($page, $tmp), '[Validate it]')
         ."</p>";
   }


   ta_begin();
   {//HOT-section to merge game-invitation
      $cnt_all = fix_players_weaker_games( $do_it );
   }
   ta_end();

   echo "<br><br>Found $cnt_all Players-entries in total to fix ...\n";
   if ( $do_it )
      echo "\nFix by setting Players.GamesWeaker-counter for all finished games is done.\n";

   end_html();
}//main


function fix_players_weaker_games( $do_it )
{
   $cnt_games = 0;
   $hu_cnt = array(); // uid => weaker-games-count

   // find finished games (non-MPGs)
   $result = db_query("fix_players_weaker_games.load_games_finished()",
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
}//fix_players_weaker_games


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

?>
