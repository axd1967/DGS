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


// Checks and fixes errors for tournaments in the database.

chdir('..');
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/table_columns.php';
require_once 'include/form_functions.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_ladder.php';
require_once 'tournaments/include/tournament_participant.php';

define('SEPLINE', "\n<p><hr>\n");


{
   $beginall = getmicrotime();
   disable_cache();
   connect2mysql();
   set_time_limit(0); // don't want script-break during "transaction" with multi-db-queries

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scripts.tournament_consistency');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.tournament_consistency');
   if ( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'scripts.tournament_consistency');

   $tid = (int)@$_REQUEST['tid'];
   $do_it = @$_REQUEST['do_it'];

   start_html( 'tournament_consistency', 0, '',
      "  table.Table { border:0; background: #c0c0c0; }\n" .
      "  tr.Row1 { background: #ffffff; }\n" .
      "  tr.Row2 { background: #dddddd; }\n" .
      "  tr.hil { background: #ffb010; }" );

   $page = "tournament_consistency.php";
   $form = new Form( 'tourneyconsistency', $page, FORM_GET );

   $form->add_row( array(
         'DESCRIPTION', 'tid',
         'TEXTINPUT',   'tid', 12, 12, $tid,
         'TEXT',        '0 (=all) | tournament-id', ));
   $form->add_row( array(
         'TAB',
         'CHECKBOX', 'fix_last_move', 1, 'Enable tournament last-move fix', @$_REQUEST['fix_last_move'], ));

   $form->add_empty_row();
   $form->add_row( array(
         'SUBMITBUTTON', 'check_it', 'Check Only',
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'do_it', 'Check and Fix it!', ));

   echo "<p><h3 class=center>Tournament Consistency:</h3>\n";
   $form->echo_string();

//-----------------

   echo "On ", date(DATE_FMT5, $NOW);

   $cnt_err = 0;

   if ( @$_REQUEST['check_it'] || $do_it )
   {
      if ( $do_it )
         echo "<p>*** Fixes errors ***</p>";

      $cnt_err += fix_tournament_RegisteredTP( $tid, $do_it );
      $cnt_err += fix_tournament_participant_game_count( $tid, $do_it );
      $cnt_err += fix_tournament_ladder_challenge_count( $tid, $do_it );
      if ( @$_REQUEST['fix_last_move'] )
         $cnt_err += fix_tournament_participant_last_moved( $tid, $do_it );

      $cnt_err += check_tournament_ladder_unique_rank( $tid );
      $cnt_err += check_tournament_ladder_miss_tp_entry( $tid );
   }

   echo SEPLINE;
   echo sprintf( "<font color=red><b>Found %s errors (inconsistencies).</b></font><br>\n", $cnt_err );

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
      echo $query, "\n";
      if ( $do_it )
         db_query( "$dbgmsg.upd", $query );
   }
   echo '</pre>';
}//do_updates

function tid_clause( $field_tid, $tid, $with_op=true, $with_where=false )
{
   $where = ($with_where) ? 'WHERE' : '';
   $op = ($with_op) ? 'AND' : '';
   return ( $tid > 0 ) ? " $where $op $field_tid=$tid " : '';
}


function fix_tournament_RegisteredTP( $arg_tid, $do_it )
{
   $dbgmsg = "tournament_consistency.fix_tournament_RegisteredTP($arg_tid)";
   $begin = getmicrotime();
   $cnt_err = 0;
   echo SEPLINE;
   echo "Fix Tournament.RegisteredTP ...<br>\n";

   // note: join slightly faster than using subquery: Posts where User_ID not in (select ID from Players)
   $result = db_query( "$dbgmsg.count",
      "SELECT SQL_SMALL_RESULT TP.tid, T.RegisteredTP, COUNT(*) AS X_Count " .
      "FROM TournamentParticipant AS TP INNER JOIN Tournament AS T ON T.ID=TP.tid " .
      "WHERE TP.Status='".TP_STATUS_REGISTER."' " . tid_clause('TP.tid', $arg_tid) .
      "GROUP BY TP.tid HAVING T.RegisteredTP <> X_Count" );
   $upd_arr = array();
   $upd_cache = array(); // tid => 1
   while ( $row = mysql_fetch_array($result) )
   {
      $tid = $row['tid'];
      $count = $row['X_Count'];
      echo sprintf( "Tournament #%s: wrong RegisteredTP found: [%s] -> [%s]<br>\n",
         $tid, $row['RegisteredTP'], $count );
      $upd_arr[] = "UPDATE Tournament SET RegisteredTP=$count WHERE ID=$tid LIMIT 1";
      $upd_cache[$tid] = 1;
   }
   mysql_free_result($result);
   $cnt_err += count($upd_arr);

   do_updates( $dbgmsg, $upd_arr, $do_it );

   // clear caches
   if ( $do_it )
   {
      foreach( $upd_cache as $tid => $tmp )
         TournamentCache::delete_cache_tournament( "$dbgmsg($tid)", $tid );
   }

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin))
      , " - Tournament.RegisteredTP check Done.";

   return $cnt_err;
}//fix_tournament_RegisteredTP


function fix_tournament_participant_game_count( $arg_tid, $do_it )
{
   $dbgmsg = 'tournament_consistency.fix_tournament_participant_game_count';
   $begin = getmicrotime();
   $cnt_err = 0;
   echo SEPLINE;
   echo "Fix TournamentParticipant.Finished/Won/Lost ...<br>\n";

   // find finished/won/lost tournament-games for challenger
   $result = db_query( "$dbgmsg.challenger($arg_tid)",
      "SELECT TP.tid, TP.ID AS rid, TP.uid, TP.Finished, TP.Won, TP.Lost, SUM(IF(ISNULL(TG.ID),0,1)) AS X_Finished, " .
         "SUM(IF(TG.Score<0,1,0)) AS X_ChallengerWon, SUM(IF(TG.Score>0,1,0)) AS X_ChallengerLost " .
      "FROM TournamentParticipant AS TP " .
         "LEFT JOIN TournamentGames AS TG ON TG.Challenger_rid=TP.ID AND TG.Status IN ('".TG_STATUS_WAIT."','".TG_STATUS_DONE."') " .
            "AND (TG.Flags & ".TG_FLAG_GAME_DETACHED.")=0 " .
      tid_clause('TP.tid', $arg_tid, /*with-OP*/false, /*with-WHERE*/true) .
      "GROUP BY TP.tid, TP.ID " .
      "ORDER BY TP.tid, TP.ID" );
   $chk = array(); // arr( tid => arr( rid => arr( Finished/Won/Lost/X_Finished/X_ChallengerWon/X_ChallengerLost => count )))
   while ( $row = mysql_fetch_array($result) )
   {
      extract($row);
      if ( !isset($chk[$tid]) )
         $chk[$tid] = array();
      if ( !isset($chk[$tid][$rid]) )
         $chk[$tid][$rid] = $row;
   }
   mysql_free_result($result);

   // find finished/won/lost tournament-games for defender
   $result = db_query( "$dbgmsg.defender($arg_tid)",
      "SELECT TP.tid, TP.ID AS rid, TP.uid, TP.Finished, TP.Won, TP.Lost, SUM(IF(ISNULL(TG.ID),0,1)) AS X_Finished, " .
         "SUM(IF(TG.Score>0,1,0)) AS X_DefenderWon, SUM(IF(TG.Score<0,1,0)) AS X_DefenderLost " .
      "FROM TournamentParticipant AS TP " .
         "LEFT JOIN TournamentGames AS TG ON TG.Defender_rid=TP.ID AND TG.Status IN ('".TG_STATUS_WAIT."','".TG_STATUS_DONE."') " .
            "AND (TG.Flags & ".TG_FLAG_GAME_DETACHED.")=0 " .
      tid_clause('TP.tid', $arg_tid, /*with-OP*/false, /*with-WHERE*/true) .
      "GROUP BY TP.tid, TP.ID " .
      "ORDER BY TP.tid, TP.ID" );
   while ( $row = mysql_fetch_array($result) )
   {
      extract($row);
      if ( !isset($chk[$tid]) )
         $chk[$tid] = array();
      if ( !isset($chk[$tid][$rid]) )
         $chk[$tid][$rid] = $row;
      else
      {
         $chk[$tid][$rid]['X_Finished'] += $X_Finished;
         $chk[$tid][$rid]['X_DefenderWon'] = $X_DefenderWon;
         $chk[$tid][$rid]['X_DefenderLost'] = $X_DefenderLost;
      }
   }
   mysql_free_result($result);

   // find discrepancies to fix on TP.Finished/Won/Lost
   // NOTE: matching on TP.ID = rid (which can change over time by re-joining ladder)
   $upd_arr = array();
   $upd_cache = array(); // tid => [ uid, ... ]
   foreach ( $chk as $tid => $arr_rid )
   {
      if ( !isset($upd_cache[$tid]) )
         $upd_cache[$tid] = array();
      foreach ( $arr_rid as $rid => $arr )
      {
         $upd = array();
         $diff = array();
         $cnt_won  = (int)@$arr['X_ChallengerWon']  + (int)@$arr['X_DefenderWon'];
         $cnt_lost = (int)@$arr['X_ChallengerLost'] + (int)@$arr['X_DefenderLost'];
         $cnt_fin  = (int)@$arr['X_Finished'];
         if ( $arr['Finished'] != $cnt_fin )
         {
            $upd[] = "Finished=$cnt_fin";
            $diff[] = sprintf('Finished %s -> %s', $arr['Finished'], $cnt_fin );
         }
         if ( $arr['Won'] != $cnt_won )
         {
            $upd[] = "Won=$cnt_won";
            $diff[] = sprintf('Won %s -> %s', $arr['Won'], $cnt_won );
         }
         if ( $arr['Lost'] != $cnt_lost )
         {
            $upd[] = "Lost=$cnt_lost";
            $diff[] = sprintf('Lost %s -> %s', $arr['Lost'], $cnt_lost );
         }
         if ( count($upd) )
         {
            $upd_arr[] = "UPDATE TournamentParticipant SET ".implode(', ', $upd)." WHERE ID=$rid LIMIT 1";
            $upd_cache[$tid][] = $arr['uid'];
            echo sprintf( "Tournament #%s: found wrong counts for TP [%s]: %s<br>\n",
               $tid, $rid, implode(', ', $diff) );
         }
      }
   }

   $cnt_err += count($upd_arr);

   do_updates( $dbgmsg, $upd_arr, $do_it );

   // clear caches
   if ( $do_it )
   {
      foreach( $upd_cache as $tid => $arr_uid )
      {
         foreach( $arr_uid as $uid )
            TournamentParticipant::delete_cache_tournament_participant( "$dbgmsg($tid)", $tid, $uid );
      }
   }

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin))
      , " - TournamentParticipant.Finished/Won/Lost check Done.";

   return $cnt_err;
}//fix_tournament_participant_game_count


function fix_tournament_ladder_challenge_count( $arg_tid, $do_it )
{
   $dbgmsg = "tournament_consistency.fix_tournament_ladder_challenge_count($arg_tid)";
   $begin = getmicrotime();
   $cnt_err = 0;
   echo SEPLINE;
   echo "Fix TournamentLadder.ChallengesIn/-Out ...<br>\n";

   // fix TournamentLadder.ChallengesIn
   $upd_arr = array();
   $upd_cache = array(); # tid => 1
   $result = db_query( "$dbgmsg.challenges_in($arg_tid)",
      "SELECT TL.tid, TL.rid, TL.ChallengesIn, SUM(IF(ISNULL(TG.ID),0,1)) AS X_Count " .
      "FROM TournamentLadder AS TL " .
         // NOTE: only TG.uid has index, but join on TG.rid is the important one (as user could have withdrawn from ladder),
         //       so join on TG.uid for index-use and join TG.rid for real-join
         // NOTE: left-join needed to also correct wrong 0-count
         "LEFT JOIN TournamentGames AS TG ON TG.Defender_uid=TL.uid AND TG.Defender_rid=TL.rid " .
            "AND TG.Status IN ('".TG_STATUS_PLAY."','".TG_STATUS_SCORE."') " .
      ( $arg_tid > 0 ? 'WHERE ' . tid_clause('TL.tid', $arg_tid, 0) : '' ) .
      "GROUP BY TL.tid, TL.rid " .
      "HAVING ChallengesIn <> X_Count " .
      "ORDER BY TL.tid, TL.rid" );
   while ( $row = mysql_fetch_array($result) )
   {
      extract($row);
      $upd_arr[] = "UPDATE TournamentLadder SET ChallengesIn=$X_Count WHERE tid=$tid AND rid=$rid LIMIT 1";
      $upd_cache[$tid] = 1;
      echo sprintf( "Tournament #%s: found wrong TL.ChallengesIn for rid [%s]: %s -> %s<br>\n",
         $tid, $rid, $ChallengesIn, $X_Count );
   }
   mysql_free_result($result);


   // fix TournamentLadder.ChallengesOut
   $result = db_query( "$dbgmsg.challenges_out($arg_tid)",
      "SELECT TL.tid, TL.rid, TL.ChallengesOut, SUM(IF(ISNULL(TG.ID),0,1)) AS X_Count " .
      "FROM TournamentLadder AS TL " .
         "LEFT JOIN TournamentGames AS TG ON TG.Challenger_uid=TL.uid AND TG.Challenger_rid=TL.rid " .
            "AND TG.Status IN ('".TG_STATUS_PLAY."','".TG_STATUS_SCORE."') " .
      ( $arg_tid > 0 ? 'WHERE ' . tid_clause('TL.tid', $arg_tid, 0) : '' ) .
      "GROUP BY TL.tid, TL.rid " .
      "HAVING ChallengesOut <> X_Count " .
      "ORDER BY TL.tid, TL.rid" );
   while ( $row = mysql_fetch_array($result) )
   {
      extract($row);
      $upd_arr[] = "UPDATE TournamentLadder SET ChallengesOut=$X_Count WHERE tid=$tid AND rid=$rid LIMIT 1";
      $upd_cache[$tid] = 1;
      echo sprintf( "Tournament #%s: found wrong TL.ChallengesOut for rid [%s]: %s -> %s<br>\n",
         $tid, $rid, $ChallengesOut, $X_Count );
   }
   mysql_free_result($result);

   $cnt_err += count($upd_arr);

   do_updates( $dbgmsg, $upd_arr, $do_it );

   // clear caches
   if ( $do_it )
   {
      foreach( $upd_cache as $tid => $tmp )
         TournamentLadder::delete_cache_tournament_ladder( $dbgmsg, $tid );
   }

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin))
      , " - TournamentLadder.ChallengesIn/-Out check Done.";

   return $cnt_err;
}//fix_tournament_ladder_challenge_count


// NOTE: manual fix required
function check_tournament_ladder_unique_rank( $arg_tid )
{
   $begin = getmicrotime();
   $cnt_err = 0;
   echo SEPLINE;
   echo "Check TournamentLadder.Rank (unique) ...<br>\n";
   echo "<font color=\"red\">\n";

   // find non-unique TournamentLadder.Rank
   $result = db_query( "tournament_consistency.check_tournament_ladder_unique_rank($arg_tid)",
      "SELECT TL.tid, TL.Rank, COUNT(*) AS X_Count " .
      "FROM TournamentLadder AS TL " .
      ( $arg_tid > 0 ? 'WHERE ' . tid_clause('TL.tid', $arg_tid, 0) : '' ) .
      "GROUP BY TL.tid, TL.Rank " .
      "HAVING X_Count > 1 " .
      "ORDER BY TL.tid" );
   while ( $row = mysql_fetch_array($result) )
   {
      extract($row);
      echo sprintf( "Tournament #%s: found non-unique TL.Rank [%s] with count of %s -> needs manual fixing!<br>\n",
         $tid, $Rank, $X_Count );
      $cnt_err++;
   }
   mysql_free_result($result);


   echo "</font>\n";
   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin))
      , " - TournamentLadder.Rank (unique) check Done.";

   return $cnt_err;
}//check_tournament_ladder_unique_rank


// NOTE: manual fix may include:
// - if existing TournamentGames- or Tournamentlog-entries => fix by inserting correct TournamentParticipant-entry with corresponding TP.ID
// - alternatively deleting TournamentLadder-entry by tournament-director may be needed; not manual,
//   because ranks of other ladder-user needs adjustment -> see func remove_user_from_ladder() what to do
function check_tournament_ladder_miss_tp_entry( $arg_tid )
{
   $begin = getmicrotime();
   $cnt_err = 0;
   echo SEPLINE;
   echo "Check for missing TournamentParticipant for ladder ...<br>\n";
   echo "<font color=\"red\">\n";

   // find non-unique TournamentLadder.Rank
   $result = db_query( "tournament_consistency.check_tournament_ladder_miss_tp_entry($arg_tid)",
      "SELECT TL.tid, TL.rid, TL.uid " .
      "FROM TournamentLadder AS TL " .
         "LEFT JOIN TournamentParticipant AS TP ON TP.ID=TL.rid " .
      "WHERE TP.ID IS NULL " . tid_clause('TL.tid', $arg_tid) .
      "ORDER BY TL.tid" );
   while ( $row = mysql_fetch_array($result) )
   {
      extract($row);
      echo sprintf( "Tournament #%s: found ladder-entry with rid [%s] and uid [%s] -> needs manual fixing!<br>\n",
         $tid, $rid, $uid );
      $cnt_err++;
   }
   mysql_free_result($result);

   echo "</font>\n";
   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin))
      , " - missing TournamentParticipant-check for ladder Done.";

   return $cnt_err;
}//check_tournament_ladder_miss_tp_entry


function fix_tournament_participant_last_moved( $arg_tid, $do_it )
{
   $begin = getmicrotime();
   $cnt_err = 0;
   echo SEPLINE;
   echo "Fix TournamentParticipant.Lastmoved ...<br>\n";

   $tp_lm0_qpart =
      "FROM TournamentParticipant AS TP " .
         "INNER JOIN Tournament AS T ON T.ID=TP.tid " .
      "WHERE TP.Status='".TP_STATUS_REGISTER."' AND Lastmoved=0 " .
         "AND T.Status <> '".TOURNEY_STATUS_REGISTER."'" .
         tid_clause('T.ID', $arg_tid, /*with-OP*/true, /*with-WHERE*/false);

   // find existing TP without Lastmoved
   $map_tp = array(); // tid => { uid => timestamp }
   $cnt_tp = 0;
   $result = db_query( "tournament_consistency.fix_tournament_participant_last_moved.find_tp($arg_tid)",
      "SELECT tid, uid, UNIX_TIMESTAMP(TP.Lastmoved) AS X_Lastmoved " . $tp_lm0_qpart );
   while ( $row = mysql_fetch_array($result) )
   {
      extract($row);
      $cnt_tp++;
      if ( !isset($map_tp[$tid]) )
         $map_tp[$tid] = array();
      $map_tp[$tid][$uid] = $X_Lastmoved;
   }
   mysql_free_result($result);

   echo "\n<br>Found $cnt_tp tournament-participants on REGISTER-status without last-moved-date ...<br>\n";

   // collect last-moved data of finished and started games
   $cnt_games = 0;
   $map_lm = array(); // tid => { uid => timestamp }
   $result = db_query( "tournament_consistency.fix_tournament_participant_last_moved.find_games($arg_tid)",
      "SELECT DISTINCT G.ID AS gid, G.tid, G.Status, G.Moves, G.Handicap, G.StdHandicap, " .
         "G.Black_ID, G.White_ID, G.ToMove_ID, UNIX_TIMESTAMP(G.Lastchanged) AS X_Lastchanged, " .
         "M1.Stone AS Stone1, M1.Hours AS Hours1, M2.Stone AS Stone2 " .
      "FROM Games AS G " .
         "LEFT JOIN Moves AS M1 ON M1.gid=G.ID AND M1.MoveNr=G.Moves AND M1.Stone IN (".BLACK.",".WHITE.") " .
         "LEFT JOIN Moves AS M2 ON M2.gid=G.ID AND M2.MoveNr=G.Moves-1 AND M2.Stone IN (".BLACK.",".WHITE.") " .
      "WHERE G.GameType='".GAMETYPE_GO."' AND G.tid > 0 " .
         tid_clause('G.tid', $arg_tid, /*with-OP*/true, /*with-WHERE*/false) .
      "ORDER BY G.Lastchanged ASC" );
   while ( $row = mysql_fetch_array($result) )
   {
      extract($row);
      $cnt_games++;

      if ( $Status == GAME_STATUS_FINISHED )
      {
         if ( @$Stone1 )
         {
            if ( $Stone1 == BLACK )
            {
               collect_tp_last_move( $map_lm, $tid, $Black_ID, $X_Lastchanged );
               if ( @$Stone2 == WHITE ) // approximate
                  collect_tp_last_move( $map_lm, $tid, $White_ID, $X_Lastchanged - (int)@$Hours1 * SECS_PER_HOUR);
            }
            elseif ( $Stone1 == WHITE )
            {
               collect_tp_last_move( $map_lm, $tid, $White_ID, $X_Lastchanged );
               if ( @$Stone2 == BLACK ) // approximate
                  collect_tp_last_move( $map_lm, $tid, $Black_ID, $X_Lastchanged - (int)@$Hours1 * SECS_PER_HOUR);
            }
         }
      }
      elseif ( isStartedGame($Status) )
      {
         if ( ( $Moves > $Handicap ) || ( $Handicap > 0 && $StdHandicap == 'N' && $Moves > 0 ) )
         {
            $uid = ( $ToMove_ID == $Black_ID ) ? $White_ID : $Black_ID;
            collect_tp_last_move( $map_lm, $tid, $uid, $X_Lastchanged );
            if ( $Moves > $Handicap + 1 ) // approximate
               collect_tp_last_move( $map_lm, $tid, $ToMove_ID, $X_Lastchanged - (int)@$Hours1 * SECS_PER_HOUR);
         }
      }
   }
   mysql_free_result($result);

   echo "\n<br>Checked $cnt_games games ...<br><br>\n";

   // fix TP.Lastmoved
   $upd_arr = array();
   $upd_cache = array(); # tid => [ uid, ... ]
   foreach ( $map_lm as $tid => $data )
   {
      if ( !isset($upd_cache[$tid]) )
         $upd_cache[$tid] = array();
      foreach ( $data as $uid => $last_moved )
      {
         // only update TPs with Lastmoved=0
         if ( isset($map_tp[$tid][$uid]) && $last_moved > @$map_tp[$tid][$uid] )
         {
            $upd_arr[] = "UPDATE TournamentParticipant SET Lastmoved=GREATEST(Lastmoved,FROM_UNIXTIME($last_moved)) " .
               "WHERE tid=$tid AND Status='".TP_STATUS_REGISTER."' AND uid=$uid LIMIT 1";
            $upd_cache[$tid][] = $uid;
            echo sprintf( "TournamentParticipant: found last-move-date [%s] for tid [%s] uid [%s]<br>\n",
               date(DATE_FMT, $last_moved), $tid, $uid );
         }
      }
   }

   $cnt_err += count($upd_arr);

   do_updates( 'tournament_consistency.fix_tournament_participant_last_moved', $upd_arr, $do_it );

   // clear caches
   if ( $do_it )
   {
      foreach( $upd_cache as $tid => $arr_uid )
      {
         foreach( $arr_uid as $uid )
            TournamentParticipant::delete_cache_tournament_participant( "$dbgmsg($tid)", $tid, $uid );
      }
   }

   $row = mysql_single_fetch( "tournament_consistency.fix_tournament_participant_last_moved.count_tp_no_lastmove($arg_tid)",
      "SELECT COUNT(*) AS X_Count " . $tp_lm0_qpart );
   $cnt_tp_lm0 = ($row) ? (int)$row['X_Count'] : 0;

   echo "\nNOTE: $cnt_tp_lm0 participants without last-moved ...<br>\n";

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin))
      , " - TournamentParticipant.Lastmoved check Done.";

   return $cnt_err;
}//fix_tournament_participant_last_moved

function collect_tp_last_move( &$map, $tid, $uid, $game_lm )
{
   if ( $uid > 0 && $game_lm > 0 )
   {
      if ( !isset($map[$tid]) )
         $map[$tid] = array();
      $last_moved = ( isset($map[$tid][$uid]) ) ? $map[$tid][$uid] : 0;
      if ( $game_lm > $last_moved )
         $map[$tid][$uid] = $game_lm;
   }
}//collect_tp_last_move

?>
