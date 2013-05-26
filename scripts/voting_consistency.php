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


// Checks and fixes errors in Feature & Survey fields in the database.
// see 'specs/db/table-Voting.txt' for necessary forum-updates !!

chdir('..');
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/table_columns.php';
require_once 'include/db/survey.php';

define('SEPLINE', "\n<p><hr>\n");


{
   $beginall = getmicrotime();
   disable_cache();
   connect2mysql();
   set_time_limit(0); // don't want script-break during "transaction" with multi-db-queries or for large-datasets

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scripts.voting_consistency');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.voting_consistency');
   if ( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'scripts.voting_consistency');

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();
   //$page_args['limit'] = $lim;

   start_html( 'voting_consistency', 0, '',
      "  table.Table { border:0; background: #c0c0c0; }\n" .
      "  tr.Row1 { background: #ffffff; }\n" .
      "  tr.Row2 { background: #dddddd; }\n" .
      "  tr.hil { background: #ffb010; }" );

   if ( $do_it = @$_REQUEST['do_it'] )
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


//----------------- Fix Survey.UserCount

   $begin = getmicrotime();
   echo SEPLINE;
   echo "Fix Survey.UserCount ...<br>\n";

   $result = db_query( 'voting_consistency.check_survey_usercount',
      "SELECT S.ID AS sid, S.UserCount, COUNT(DISTINCT SV.uid) AS X_Count " .
      "FROM Survey AS S " .
         "INNER JOIN SurveyOption AS SOPT ON SOPT.sid=S.ID " .
         "LEFT JOIN SurveyVote AS SV ON SV.soid=SOPT.ID " .
      "WHERE S.Status IN ('".SURVEY_STATUS_NEW."','".SURVEY_STATUS_ACTIVE."','".SURVEY_STATUS_CLOSED."') " .
      "GROUP BY S.ID HAVING S.UserCount <> X_Count" );
   $upd_arr = array();
   while ( $row = mysql_fetch_array( $result ) )
   {
      $cnt_err++;
      $sid = $row['sid'];
      $count = $row['X_Count'];
      echo sprintf( "Survey #%s: wrong UserCount found: [%s] -> [%s]<br>\n",
         $sid, $row['UserCount'], $count );
      $upd_arr[] = "UPDATE Survey SET UserCount=$count WHERE ID=$sid LIMIT 1";
   }
   mysql_free_result($result);
   $cnt_err += count($upd_arr);

   do_updates( 'voting_consistency.fix_survey.UserCount', $upd_arr, $do_it );

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin))
      , " - Survey.UserCount check Done.";

//----------------- Fix SurveyOption.Score

   $begin = getmicrotime();
   echo SEPLINE;
   echo "Fix SurveyOption.Score ...<br>\n";

   $result = db_query( 'voting_consistency.check_surveyoption_score',
      "SELECT SOPT.ID AS sopt_id, SOPT.sid, SOPT.Score, SUM(IFNULL(SV.Points,0)) AS X_Score " .
      "FROM Survey AS S " .
         "INNER JOIN SurveyOption AS SOPT ON SOPT.sid=S.ID " .
         "LEFT JOIN SurveyVote AS SV ON SV.soid=SOPT.ID " .
      "WHERE S.Status IN ('".SURVEY_STATUS_NEW."','".SURVEY_STATUS_ACTIVE."','".SURVEY_STATUS_CLOSED."') " .
      "GROUP BY SOPT.ID HAVING SOPT.Score <> X_Score" );
   $upd_arr = array();
   while ( $row = mysql_fetch_array( $result ) )
   {
      $cnt_err++;
      $sopt_id = $row['sopt_id'];
      $sid = $row['sid'];
      $score = $row['X_Score'];
      echo sprintf( "Survey #%s, SurveyOption #%s: wrong Score found: [%s] -> [%s]<br>\n",
         $sid, $sopt_id, $row['Score'], $score );
      $upd_arr[] = "UPDATE SurveyOption SET Score=$score WHERE ID=$sopt_id LIMIT 1";
   }
   mysql_free_result($result);
   $cnt_err += count($upd_arr);

   do_updates( 'voting_consistency.fix_surveyoption.Score', $upd_arr, $do_it );

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin))
      , " - SurveyOption.Score check Done.";

//-----------------

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
         db_query( $dbgmsg, $query );
   }
   echo '</pre>';
}

?>
