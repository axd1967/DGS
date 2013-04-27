<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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

define('SEPLINE', "\n<p><hr>\n");


{
   $beginall = getmicrotime();
   disable_cache();
   connect2mysql();
   set_time_limit(0); // don't want script-break during "transaction" with multi-db-queries

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('login_if_not_logged_in', 'scripts.tournament_consistency');
   if( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.tournament_consistency');
   if( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
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

   if( @$_REQUEST['check_it'] || $do_it )
   {
      if( $do_it )
         echo "<p>*** Fixes errors ***</p>";

      $cnt_err += fix_tournament_RegisteredTP( $tid, $do_it );
   }

   echo SEPLINE;
   echo sprintf( "<font color=red><b>Found %s errors (inconsistencies).</b></font><br>\n", $cnt_err );

   echo "\n<br>Needed (all): " . sprintf("%1.3fs", (getmicrotime() - $beginall));
   echo "\n<br>Done!!!\n";
   end_html();
}//main



function do_updates( $dbgmsg, $upd_arr, $do_it )
{
   if( count($upd_arr) == 0 )
      return;

   echo '<pre>';
   foreach( $upd_arr as $query )
   {
      echo $query, "\n";
      if( $do_it )
         db_query( $dbgmsg, $query );
   }
   echo '</pre>';
}//do_updates

function tid_clause( $field_tid, $tid, $with_op=true )
{
   $op = ($with_op) ? 'AND' : '';
   return ( $tid > 0 ) ? " $op $field_tid=$tid " : '';
}


function fix_tournament_RegisteredTP( $tid, $do_it )
{
   $begin = getmicrotime();
   $cnt_err = 0;
   echo SEPLINE;
   echo "Fix Tournament.RegisteredTP ...<br>\n";

   // note: join slightly faster than using subquery: Posts where User_ID not in (select ID from Players)
   $result = db_query( "tournament_consistency.fix_tournament_RegisteredTP($tid)",
      "SELECT TP.tid, T.RegisteredTP, COUNT(*) AS X_Count " .
      "FROM TournamentParticipant AS TP INNER JOIN Tournament AS T ON T.ID=TP.tid " .
      "WHERE TP.Status='".TP_STATUS_REGISTER."' " . tid_clause('TP.tid', $tid) .
      "GROUP BY TP.tid HAVING T.RegisteredTP <> X_Count" );
   $upd_arr = array();
   while( $row = mysql_fetch_array($result) )
   {
      $tid = $row['tid'];
      $count = $row['X_Count'];
      echo sprintf( "Tournament #%s: wrong RegisteredTP found: [%s] -> [%s]<br>\n",
         $tid, $row['RegisteredTP'], $count );
      $upd_arr[] = "UPDATE Tournament SET RegisteredTP=$count WHERE ID=$tid LIMIT 1";
   }
   mysql_free_result($result);
   $cnt_err += count($upd_arr);

   do_updates( 'tournament_consistency.fix_tournament.RegisteredTP', $upd_arr, $do_it );

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin))
      , " - Tournament.RegisteredTP check Done.";

   return $cnt_err;
}//fix_tournament_RegisteredTP

?>
