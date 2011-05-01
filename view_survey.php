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
   if( $sid < 0 )
      error('invalid_args', "view_survey.check_args($sid)");

   $page = "view_survey.php";

   // init
   $qsql = Survey::build_query_sql( $sid );
   list( $survey, $orow ) = Survey::load_survey_by_query( $qsql, /*withrow*/true );
   if( is_null($survey) )
      error('unknown_survey', "view_survey.find_survey($sid)");


   $title = sprintf( T_('Survey View #%d'), $sid );
   start_page($title, true, $logged_in, $player_row );
   echo "<h3 class=Header>". $title . "</h3>\n";

   echo "<br>\n", SurveyControl::build_view_survey($survey);


   $menu_array = array();
   $menu_array[T_('Surveys')] = "list_surveys.php";
   if( SurveyControl::allow_survey_edit($survey) )
      $menu_array[T_('Edit survey')] = array( 'url' => "admin_survey.php?sid=$sid", 'class' => 'AdminLink' );

   end_page(@$menu_array);
}

?>
