<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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


// Checks and fixes inconsistencies GameStats db-table.

chdir('../../..');
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/game_functions.php';
require_once 'include/form_functions.php';

define('SEPLINE', "\n<p><hr>\n");

define('COL_RUN', 0);
define('COL_FIN', 1);


{
   $beginall = getmicrotime();
   disable_cache();
   connect2mysql();
   set_time_limit(0); // don't want script-break during "transaction" with multi-db-queries

   // may need to be adjusted for large game-queries
   @ini_set('memory_limit', '320M');

   $logged_in = who_is_logged( $player_row, LOGIN_DEFAULT_OPTS_ADM_OPS );
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scripts.seed_game_stats');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.seed_game_stats');
   if ( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'scripts.seed_game_stats');

   $bsize = (int)@$_REQUEST['bsize'];
   $do_it = @$_REQUEST['do_it'];

   if ( $bsize <= 0 )
      $bsize = 50000;

   start_html( 'seed_game_stats', 0, '',
      "  table.Table { border:0; background: #c0c0c0; }\n" .
      "  tr.Row1 { background: #ffffff; }\n" .
      "  tr.Row2 { background: #dddddd; }\n" .
      "  tr.hil { background: #ffb010; }" );

   $page = "seed_game_stats.php";
   $form = new Form( 'seedgstats', $page, FORM_GET );

   $form->add_row( array(
         'DESCRIPTION', 'Block-size',
         'TEXTINPUT',   'bsize', 12, 12, $bsize,
         'TEXT',        '0 (=default 50.000)', ));

   $form->add_empty_row();
   $form->add_row( array(
         'SUBMITBUTTON', 'check_it', 'Check Only',
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'do_it', 'Check and Fix it!', ));

   echo "<p><h3 class=center>GameStats Seeding:</h3>\n";
   echo "<center><b>IMPORTANT: Server must be down (no game must be started or finished)!</b></center><br>\n";
   $form->echo_string();

//-----------------

   echo "On ", date(DATE_FMT5, $NOW);

   $cnt_ent = 0;

   if ( @$_REQUEST['check_it'] || $do_it )
   {
      if ( $do_it )
         echo "<p>*** Seeding GameStats (deleting all existing entries) ***</p>";

      $cnt_ent += fill_game_stats( $bsize, $do_it );
   }

   echo SEPLINE;
   echo sprintf( "<font color=red><b>Updated %s entries.</b></font><br>\n", $cnt_ent );

   echo "\n<br>Needed (all): " . sprintf("%1.3fs", (getmicrotime() - $beginall));
   echo "\n<br>Done!!!\n";
   end_html();
}//main



function do_updates( $dbgmsg, $upd_arr, $do_it )
{
   if ( count($upd_arr) == 0 )
      return;

   echo '<pre>';
   foreach ( $upd_arr as $query )
   {
      echo wordwrap($query, 120, "\n    "), "\n";
      if ( $do_it )
         db_query( "$dbgmsg.upd", $query );
   }
   echo '</pre>';
}//do_updates


function fill_game_stats( $bsize, $do_it )
{
   $dbgmsg = "seed_game_stats.fill_game_stats($bsize)";
   $begin = getmicrotime();
   echo SEPLINE;
   echo "Fix GameStatus.Running/Finished for all user and games (no MP-games) ...<br>\n";

   $cnt_run_all = count_games_for_status( $dbgmsg, false );
   $cnt_fin_all = count_games_for_status( $dbgmsg, true );
   echo sprintf("There are %s games to scan (%s running & %s finished games) ...<br>\n",
      $cnt_run_all + $cnt_fin_all, $cnt_run_all, $cnt_fin_all );

   // some servers have huge gaps in the Game-IDs
   $row = mysql_single_fetch( "$dbgmsg.max_gid", "SELECT MAX(ID) AS X_gid FROM Games" );
   if ( !$row )
   {
      echo "<font color=red><b>ERROR: can't read max Games.ID!</b></font><br>\n";
      return 0;
   }
   $max_gid = (int)$row['X_gid'];

   // count up all Running & Finished games for all player-pairs
   $data = array();
   for ( $idx = 0; $idx <= $max_gid; $idx += $bsize )
   {
      echo "Scanning game-IDs $idx ...<br>\n";
      count_games_stats( $dbgmsg, $idx, $bsize, false, $data );
      count_games_stats( $dbgmsg, $idx, $bsize, true, $data );
   }

   // build entries

   $upd_arr = array();
   $upd_arr[] = "DELETE FROM GameStats";

   $cnt_run = $cnt_fin = $cnt_ent = 0;
   $v_arr = array();
   $i = 0;
   foreach ( $data as $uid => $arr_oid )
   {
      foreach ( $arr_oid as $oid => $arr )
      {
         $cnt_run += $arr[COL_RUN];
         $cnt_fin += $arr[COL_FIN];
         $v_arr[] = sprintf("(%s,%s,%s,%s)", $uid, $oid, $arr[COL_RUN], $arr[COL_FIN] );
         if ( ++$cnt_ent % $bsize == 0 )
         {
            $upd_arr[] = "INSERT INTO GameStats (uid,oid,Running,Finished) VALUES " . implode(', ', $v_arr);
            $v_arr = array();
         }
      }
   }
   if ( count($v_arr) )
      $upd_arr[] = "INSERT INTO GameStats (uid,oid,Running,Finished) VALUES " . implode(', ', $v_arr);
   unset($data);
   unset($v_arr);
   $cnt_all = $cnt_run + $cnt_fin;
   echo sprintf("<br>\nFound %s games (%s running & %s finished) for %s entries ...<br>\n\n",
      $cnt_all, $cnt_run, $cnt_fin, $cnt_ent );

   // update DB

   echo "Deleting all GameStats entries & inserting all GameStats entries ...<br>\n";
   do_updates( $dbgmsg, $upd_arr, $do_it );

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin))
      , " - GameStats seed Done.";

   return $cnt_ent;
}//fill_game_stats


// fills $data-map ( uid => ( oid => [ cnt-run, cnt-fin ]))
// returns new entries
function count_games_stats( $dbgmsg, $start_idx, $bsize, $finished, &$data )
{
   $msg = ($finished) ? 'fin' : 'run';
   $end_idx = $start_idx + $bsize - 1;

   $qpart_status = build_query_part_status( $finished );

   $result = db_query( "$dbgmsg.count_games_$msg($start_idx,$bsize)",
      "SELECT Black_ID, White_ID, COUNT(*) AS X_Count " .
      "FROM Games WHERE (ID BETWEEN $start_idx AND $end_idx) AND GameType='".GAMETYPE_GO."' AND $qpart_status " .
      "GROUP BY Black_ID, White_ID" );
   while ( $row = mysql_fetch_array($result) )
   {
      $uid = $row['Black_ID'];
      $oid = $row['White_ID'];
      $count = $row['X_Count'];

      if ( $uid > $oid )
         swap( $uid, $oid);

      if ( !isset($data[$uid]) )
         $data[$uid] = array();
      if ( !isset($data[$uid][$oid]) )
         $data[$uid][$oid] = array( 0, 0 );

      if ( $finished )
         $data[$uid][$oid][COL_FIN] += $count;
      else
         $data[$uid][$oid][COL_RUN] += $count;
   }
   mysql_free_result($result);
}//count_games_stats

function count_games_for_status( $dbgmsg, $finished )
{
   $qpart_status = build_query_part_status( $finished );

   $row = mysql_single_fetch( "$dbgmsg.count_games_for_status($finished)",
      "SELECT COUNT(*) AS X_Count " .
      "FROM Games WHERE GameType='".GAMETYPE_GO."' AND $qpart_status " );
   return ($row) ? (int)$row['X_Count'] : 0;
}//count_games_for_status

function build_query_part_status( $finished )
{
   return ($finished)
      ? "Status='".GAME_STATUS_FINISHED."'"
      : "Status".IS_STARTED_GAME;
}//build_query_part_status

?>
