<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Jens-Uwe Gaspar

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

chdir('..');
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

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scripts.game_stats_consistency');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.game_stats_consistency');
   if ( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'scripts.game_stats_consistency');

   $uid = (int)@$_REQUEST['uid'];
   $do_it = @$_REQUEST['do_it'];

   start_html( 'game_stats_consistency', 0, '',
      "  table.Table { border:0; background: #c0c0c0; }\n" .
      "  tr.Row1 { background: #ffffff; }\n" .
      "  tr.Row2 { background: #dddddd; }\n" .
      "  tr.hil { background: #ffb010; }" );

   $page = "game_stats_consistency.php";
   $form = new Form( 'gstatsconsistency', $page, FORM_GET );

   $form->add_row( array(
         'DESCRIPTION', 'uid',
         'TEXTINPUT',   'uid', 12, 12, ($uid <= 0 ? '' : $uid),
         'TEXT',        'user-id (>0)', ));

   $form->add_empty_row();
   $form->add_row( array(
         'SUBMITBUTTON', 'check_it', 'Check Only',
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'do_it', 'Check and Fix it!', ));

   echo "<p><h3 class=center>GameStats Consistency:</h3>\n";
   $form->echo_string();

//-----------------

   echo "On ", date(DATE_FMT5, $NOW);

   $cnt_ent = 0;

   if ( @$_REQUEST['check_it'] || $do_it )
   {
      if ( $do_it )
         echo "<p>*** Fixes entries ***</p>";

      if ( $uid > 0 )
         $cnt_ent += fix_game_stats_user( $uid, $do_it );
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

function fix_game_stats_user( $uid, $do_it )
{
   $dbgmsg = "game_stats_consistency.fix_game_stats_user($uid)";
   $begin = getmicrotime();
   echo SEPLINE;
   echo "Fix GameStatus.Running/Finished for user [$uid] ...<br>\n";

   $data = array();
   count_games_for_user( $dbgmsg, $uid, false, $data );
   count_games_for_user( $dbgmsg, $uid, true,  $data );

   $v_arr = array();
   foreach( $data as $oid => $arr )
   {
      $g_run = $arr[COL_RUN];
      $g_fin = $arr[COL_FIN];
      if ( $uid < $oid )
         $v_arr[] = "($uid,$oid,$g_run,$g_fin)";
      else
         $v_arr[] = "($oid,$uid,$g_run,$g_fin)";
   }

   $upd_arr = array();
   $upd_arr[] = "INSERT INTO GameStats (uid,oid,Running,Finished) VALUES " .
         implode(', ', $v_arr) .
         " ON DUPLICATE KEY UPDATE Running=VALUES(Running), Finished=VALUES(Finished)";

   do_updates( $dbgmsg, $upd_arr, $do_it );

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin))
      , " - GameStats.Running/Finished Done.";

   return count($v_arr);
}//fix_game_stats_user

// fills in data-map( oid => [ Run-count, Fin-count ] ) for given user $uid and game-status (bool)$finished
function count_games_for_user( $dbgmsg, $uid, $finished, &$data ) {
   $msg = ($finished) ? 'fin' : 'run';

   $qpart_status = ($finished)
      ? "Status='".GAME_STATUS_FINISHED."'"
      : "Status".IS_STARTED_GAME;

   $result = db_query( "$dbgmsg.count_W_$msg",
      "SELECT White_ID AS oid, COUNT(*) AS X_Count " .
      "FROM Games WHERE Black_ID=$uid AND GameType='".GAMETYPE_GO."' AND $qpart_status " .
      "GROUP BY White_ID" );
   while ( $row = mysql_fetch_array($result) )
   {
      $oid = $row['oid'];
      $count = $row['X_Count'];

      if ( !isset($data[$oid]) )
         $data[$oid] = array( 0, 0 );
      if ( $finished )
         $data[$oid][COL_FIN] += $count;
      else
         $data[$oid][COL_RUN] += $count;
   }
   mysql_free_result($result);

   $result = db_query( "$dbgmsg.count_B_$msg",
      "SELECT Black_ID AS oid, COUNT(*) AS X_Count " .
      "FROM Games WHERE White_ID=$uid AND GameType='".GAMETYPE_GO."' AND $qpart_status " .
      "GROUP BY Black_ID" );
   while ( $row = mysql_fetch_array($result) )
   {
      $oid = $row['oid'];
      $count = $row['X_Count'];

      if ( !isset($data[$oid]) )
         $data[$oid] = array( 0, 0 );
      if ( $finished )
         $data[$oid][COL_FIN] += $count;
      else
         $data[$oid][COL_RUN] += $count;
   }
   mysql_free_result($result);
}//count_games_for_user

?>
