<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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


// Checks and fixes errors in Forum fields in the database.
// see section 'Calculated database fields' in 'specs/forums.txt' for necessary forum-updates !!

chdir('..');
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/table_columns.php';
require_once 'tournaments/include/tournament.php';

define('SEPLINE', "\n<p><hr>\n");


{
   $beginall = getmicrotime();
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'tournament_consistency');

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();
   //$page_args['limit'] = $lim;

   start_html( 'tournament_consistency', 0, '',
      "  table.Table { border:0; background: #c0c0c0; }\n" .
      "  tr.Row1 { background: #ffffff; }\n" .
      "  tr.Row2 { background: #dddddd; }\n" .
      "  tr.hil { background: #ffb010; }" );

   if( $do_it = @$_REQUEST['do_it'] )
   {
      echo "<p>*** Fixes errors ***"
         ."<br>".anchor(make_url($page, $page_args), 'Just show it')
         ."</p>";
   }
   else
   {
      $tmp = array_merge($page_args,array('do_it' => 1));
      $tmp2 = array_merge($page_args,array('do_it' => 1, 'withnew' => 1 ));
      echo "<p>(just show needed queries)"
         ."<br>".anchor(make_url($page, $page_args), 'Show it again')
         ."<br>".anchor(make_url($page, $tmp), '[Validate it]')
         ."</p>";
   }
   $debug = !$do_it;
   $cnt_err = 0;

   echo "On ", date(DATE_FMT5, $NOW);


//----------------- Fix Tournament.RegisteredTP

   $begin = getmicrotime();
   echo SEPLINE;
   echo "Fix Tournament.RegisteredTP ...<br>\n";

   // note: join slightly faster than using subquery: Posts where User_ID not in (select ID from Players)
   $result = db_query( 'tournament_consistency.check_authors',
      "SELECT TP.tid, T.RegisteredTP, COUNT(*) AS X_Count FROM TournamentParticipant AS TP INNER JOIN Tournament AS T ON T.ID=TP.tid " .
      "WHERE TP.Status='".TP_STATUS_REGISTER."' GROUP BY TP.tid HAVING T.RegisteredTP <> X_Count" );
   $upd_arr = array();
   while( $row = mysql_fetch_array( $result ) )
   {
      $cnt_err++;
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

//-----------------

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
}

?>
