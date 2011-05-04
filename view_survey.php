<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Survey";

require_once 'include/std_functions.php';
require_once 'include/std_classes.php';
require_once 'include/table_columns.php';
require_once 'include/survey_control.php';

$GLOBALS['ThePage'] = new Page('SurveyView');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_SURVEY_VOTE )
      error('feature_disabled', 'view_survey');
   $my_id = $player_row['ID'];

   $sid = (int) @$_REQUEST['sid'];
   if( $sid <= 0 )
      error('invalid_args', "view_survey.check_args($sid)");
   $show_result = (bool) @$_REQUEST['result'];

   $page = "view_survey.php";

   // init
   $qsql = Survey::build_query_sql( $sid );
   $qsql->merge( SurveyControl::build_view_query_sql(false) );
   $survey = Survey::load_survey_by_query( $qsql, /*withrow*/false );
   if( is_null($survey) )
      error('unknown_survey', "view_survey.find_survey($sid)");

   $sql_sort = ($show_result) ? new QuerySQL( SQLP_ORDER, "SOPT.Score DESC" ) : null;
   SurveyControl::load_survey_options($survey, $my_id, $sql_sort); // with my-votes

   // checks
   $errors = array();
   if( @$_REQUEST['save'] && $survey->Status != SURVEY_STATUS_ACTIVE )
      $errors[] = sprintf( T_('Voting on survey only possible on %s status.'),
         SurveyControl::getStatusText(SURVEY_STATUS_ACTIVE) );

   // handle save-vote
   if( @$_REQUEST['save'] && count($errors) == 0 )
   {
      prepare_save_votes( $survey );
      handle_save_votes( $sid, $my_id );
      jump_to("view_survey.php?sid=$sid");
   }//save


   $title = sprintf( T_('Survey View #%d'), $sid );
   start_page($title, true, $logged_in, $player_row );
   echo "<h3 class=Header>". $title . "</h3>\n";

   if( count($errors) )
   {
      $form = new Form( 'surveyView', $page, FORM_GET );
      $form->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $form->add_empty_row();
      $form->echo_string();
   }

   echo "<br>\n", SurveyControl::build_view_survey($survey, $page);


   $menu_array = array();
   $menu_array[T_('Surveys')] = "list_surveys.php";
   $menu_array[T_('View survey')] = "view_survey.php?sid=$sid";
   if( SurveyControl::allow_survey_edit($survey) )
      $menu_array[T_('Edit survey')] = array( 'url' => "admin_survey.php?sid=$sid", 'class' => 'AdminLink' );

   end_page(@$menu_array);
}//main


// sets globals: $arr_votes_upd, $arr_sopts_upd, $is_newvote
function prepare_save_votes( $survey )
{
   global $arr_votes_upd, $arr_sopts_upd, $is_newvote;

   //TODO handle different Survey-types, following is POINTS-type

   $arr_votes_upd = array(); // SOPT.ID => new-vote-points
   $arr_sopts_upd = array(); // SOPT.ID => diff-score
   $is_newvote = false;

   foreach( $survey->SurveyOptions as $so )
   {
      $key = 'so'.$so->ID;
      if( !isset($_REQUEST[$key]) )
         continue;
      $points = @$_REQUEST[$key];
      if( !is_numeric($points) )
         error('invalid_args', "view_survey.prepare_save_votes.check_points($sid,$key,$points)");

      if( is_null($so->UserVotePoints) ) // new vote
      {
         $arr_votes_upd[$so->ID] = $points;
         $arr_sopts_upd[$so->ID] = $points;
         $is_newvote = true;
      }
      else
      {
         if( $so->UserVotePoints != $points ) // update existing vote
         {
            $arr_votes_upd[$so->ID] = $points;
            $arr_sopts_upd[$so->ID] = $points - $so->UserVotePoints;
         }
      }
   }
}//prepare_save_votes

function handle_save_votes( $sid, $uid )
{
   global $arr_votes_upd, $arr_sopts_upd, $is_newvote;

   ta_begin();
   {//HOT-section to update survey-votes
      SurveyVote::persist_survey_votes( $sid, $uid, $arr_votes_upd );
      SurveyOption::update_aggregates_survey_options( $sid, $arr_sopts_upd );

      if( $is_newvote )
         Survey::update_user_count( $sid, 1 );
   }
   ta_end();
}//handle_save_votes

?>
