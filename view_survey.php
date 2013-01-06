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
      error('login_if_not_logged_in', 'view_survey');
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

   $allow_vote = SurveyControl::allow_survey_vote( $survey, $errors );

   // handle save-vote
   if( @$_REQUEST['save'] && $allow_vote && count($errors) == 0 )
   {
      $check_errors = prepare_save_votes( $survey ); // exports global vars
      if( count($check_errors) > 0 )
         $errors = array_merge( $errors, $check_errors );
      else
      {
         handle_save_votes( $sid, $my_id ); // import global vars
         jump_to("view_survey.php?sid=$sid".URI_AMP."sysmsg=".urlencode(T_('Vote saved!')));
      }
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

   echo "<br>\n", SurveyControl::build_view_survey($survey, $allow_vote, $page);


   $menu_array = array();
   $menu_array[T_('Surveys')] = "list_surveys.php";
   $menu_array[T_('View survey')] = "view_survey.php?sid=$sid";
   if( SurveyControl::allow_survey_edit($survey) )
      $menu_array[T_('Edit survey')] = array( 'url' => "admin_survey.php?sid=$sid", 'class' => 'AdminLink' );

   end_page(@$menu_array);
}//main


// sets globals: $arr_votes_upd, $arr_sopts_upd, $is_newvote
// note: also "save" selected values for viewing into $survey
// returns errors
function prepare_save_votes( &$survey )
{
   global $arr_votes_upd, $arr_sopts_upd, $is_newvote;

   $arr_votes_upd = array(); // SOPT.ID => new-vote-points
   $arr_sopts_upd = array(); // SOPT.ID => diff-score
   $sum_points = $cnt_selections = 0;
   $is_newvote = false;

   foreach( $survey->SurveyOptions as $so )
   {
      $points = get_new_points( $survey->ID, $survey->Type, $so );
      $sum_points += $points;
      if( $points )
         $cnt_selections++;

      if( is_null($so->UserVotePoints) ) // new vote
      {
         $arr_votes_upd[$so->ID] = $points;
         $arr_sopts_upd[$so->ID] = $points;
         $so->UserVotePoints = $points;
         $so->Score += $points;
         $is_newvote = true;
      }
      else
      {
         if( $so->UserVotePoints != $points ) // update existing vote
         {
            $arr_votes_upd[$so->ID] = $points;
            $arr_sopts_upd[$so->ID] = $points - $so->UserVotePoints;
            $so->UserVotePoints = $points;
            $so->Score += $arr_sopts_upd[$so->ID];
         }
      }
   }

   if( $is_newvote )
      $survey->UserCount++;

   $errors = array();
   if( $survey->Type == SURVEY_TYPE_SUM )
   {
      if( $sum_points < $survey->MinPoints )
         $errors[] = sprintf( T_('You must spend at least %s point(s) in total, but you only spent %s point(s).'),
            $survey->MinPoints, $sum_points );
      if( $sum_points > $survey->MaxPoints )
         $errors[] = sprintf( T_('You must not spend more than %s point(s) in total, but you spent %s point(s).'),
            $survey->MaxPoints, $sum_points );
   }
   elseif( $survey->Type == SURVEY_TYPE_MULTI )
   {
      if( $survey->MinPoints > 0 && $cnt_selections < $survey->MinPoints )
         $errors[] = sprintf( T_('You must at least select %s checkbox(es), but you only selected %s.'),
            $survey->MinPoints, $cnt_selections );
      if( $survey->MaxPoints > 0 && $cnt_selections > $survey->MaxPoints )
         $errors[] = sprintf( T_('You must not select more than %s checkbox(es), but you selected %s.'),
            $survey->MaxPoints, $cnt_selections );
   }

   return $errors;
}//prepare_save_votes

// parse new points from URL-args
function get_new_points( $sid, $survey_type, $so )
{
   if( $survey_type == SURVEY_TYPE_SINGLE )
   {
      $points = ( (int)@$_REQUEST['so'] == $so->ID ) ? $so->MinPoints : 0; // value from radio-button
      return $points;
   }

   $is_points_type = ( $survey_type == SURVEY_TYPE_POINTS || $survey_type == SURVEY_TYPE_SUM );
   $key = 'so'.$so->ID;
   $is_val_set = isset($_REQUEST[$key]);
   if( /*need-key-val*/Survey::is_point_type($survey_type) && !$is_val_set )
      error('miss_args', "view_survey.get_new_points($sid,$key,$survey_type,$need_key_val)");

   if( $is_val_set )
   {
      $arg_points = @$_REQUEST[$key];
      if( !is_numeric($arg_points) )
         error('invalid_args', "view_survey.get_new_points.check_points($sid,$key,$arg_points)");
   }
   else
      $arg_points = null;

   if( $is_points_type )
      $points = (int)$arg_points; // value from selectbox
   elseif( $survey_type == SURVEY_TYPE_MULTI )
      $points = ($arg_points) ? $so->MinPoints : 0; // value from checkbox
   else
      error('invalid_args', "view_survey.get_new_points.check_type($sid,$key,$survey_type,$arg_points)");

   return $points;
}//get_new_points

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
