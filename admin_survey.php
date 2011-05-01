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

require_once 'include/error_codes.php';
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'include/survey_control.php';
require_once 'include/classlib_user.php';

$GLOBALS['ThePage'] = new Page('SurveyAdmin');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];
   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $is_admin = SurveyControl::is_survey_admin();
   if( !$is_admin )
      error('adminlevel_too_low', "admin_survey");

/* Actual REQUEST calls used:
     ''                       : add new admin survey
     sid=                     : edit existing survey
     preview&sid=             : preview for survey-save
     save&sid=                : save new/updated survey
*/

   $sid = (int) get_request_arg('sid');
   if( $sid < 0 ) $sid = 0;

   // init
   $survey = ( $sid > 0 ) ? Survey::load_survey($sid) : null;
   if( is_null($survey) )
      $survey = SurveyControl::new_survey();

   $s_old_status = $survey->Status;
   $s_old_type = $survey->SurveyType;

   $arr_types = SurveyControl::getSurveyTypeText();
   $arr_status = SurveyControl::getStatusText();

   // check + parse edit-form
   list( $vars, $edits, $input_errors ) = parse_edit_form( $survey );
   $errors = $input_errors;

   // save survey-object with values from edit-form
   if( @$_REQUEST['save'] && !@$_REQUEST['preview'] && count($errors) == 0 )
   {
      if( count($edits) == 0 )
         $errors[] = T_('Sorry, there\'s nothing to save.');
      else
      {
         $survey->persist();
         $sid = $survey->ID;

         jump_to("admin_survey.php?sid=$sid".URI_AMP."sysmsg=". urlencode(T_('Survey saved!')) );
      }
   }

   $page = "admin_survey.php";
   $title = T_('Admin Survey');


   // ---------- Survey EDIT form --------------------------------

   $sform = new Form( 'surveyEdit', $page, FORM_POST );
   $sform->add_hidden( 'sid', $sid );

   $sform->add_row( array(
         'DESCRIPTION', T_('Survey ID'),
         'TEXT',        ($sid ? anchor( $base_path."admin_survey.php?sid=$sid", $sid )
                              : T_('NEW survey#survey')), ));
   $sform->add_row( array(
         'DESCRIPTION', T_('Survey Author'),
         'TEXT',        $survey->User->user_reference(), ));
   if( $survey->Created > 0 )
      $sform->add_row( array(
            'DESCRIPTION', T_('Created'),
            'TEXT',        formatDate($survey->Created), ));
   if( $survey->Lastchanged > 0 )
      $sform->add_row( array(
            'DESCRIPTION', T_('Last changed'),
            'TEXT',        formatDate($survey->Lastchanged), ));

   $sform->add_row( array( 'HR' ));

   if( count($errors) )
   {
      $sform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $sform->add_empty_row();
   }

   $sform->add_row( array(
         'DESCRIPTION', T_('Current Type#survey'),
         'TEXT',        SurveyControl::getSurveyTypeText($s_old_type) ));
   $sform->add_row( array(
         'TAB',
         'SELECTBOX',    'type', 1, $arr_types, $vars['type'], false, ));

   $sform->add_row( array(
         'DESCRIPTION', T_('Current Status#survey'),
         'TEXT',        SurveyControl::getStatusText($s_old_status) ));
   $sform->add_row( array(
         'TAB',
         'SELECTBOX',    'status', 1, $arr_status, $vars['status'], false, ));
   $sform->add_row( array(
         'DESCRIPTION', T_('Points#survey'),
         'TEXT',        T_('Min#survey') . MINI_SPACING,
         'TEXTINPUT',   'min_points', 3, 5, $vars['min_points'],
         'TEXT',        SMALL_SPACING . T_('Max#survey') . MINI_SPACING,
         'TEXTINPUT',   'max_points', 3, 5, $vars['max_points'], ));
   $sform->add_row( array(
         'DESCRIPTION', T_('Title'),
         'TEXTINPUT',   'title', 80, 255, $vars['title'] ));

   $sform->add_empty_row();

   $sform->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT',        span('TWarning', implode(', ', $edits), '[%s]'), ));

   $sform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'save', T_('Save Survey'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'preview', T_('Preview'),
      ));

   if( @$_REQUEST['preview'] || $survey->Title != '' )
   {
      $sform->add_empty_row();
      $sform->add_row( array(
            'DESCRIPTION', T_('Preview'),
            'OWNHTML', '<td class="Preview">' . SurveyControl::build_view_survey($survey) . '</td>', ));
   }


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $sform->echo_string();


   $menu_array = array();
   $menu_array[T_('Surveys')] = "list_surveys.php";
   $menu_array[T_('New survey')] = array( 'url' => $page, 'class' => 'AdminLink' );

   end_page(@$menu_array);
}//main


// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$survey )
{
   $edits = array();
   $errors = array();
   $is_posted = ( @$_REQUEST['save'] || @$_REQUEST['preview'] );

   // read from props or set defaults
   $vars = array(
      'type'            => $survey->SurveyType,
      'status'          => $survey->Status,
      'min_points'      => $survey->MinPoints,
      'max_points'      => $survey->MaxPoints,
      'title'           => $survey->Title,
   );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // parse URL-vars
   if( $is_posted )
   {
      $survey->setSurveyType($vars['type']);
      $survey->setStatus($vars['status']);

      if( $survey->SurveyType == SURVEY_TYPE_POINTS )
      {
         $new_value = $vars['min_points'];
         if( isNumber($new_value) && abs($new_value) <= SURVEY_POINTS_MAX )
            $survey->MinPoints = (int)$new_value;
         else
            $errors[] = sprintf( T_('Expecting number for min-points in range %s.'),
                                 build_range_text(-SURVEY_POINTS_MAX, SURVEY_POINTS_MAX) );

         $new_value = $vars['max_points'];
         if( isNumber($new_value) && abs($new_value) <= SURVEY_POINTS_MAX )
            $survey->MaxPoints = (int)$new_value;
         else
            $errors[] = sprintf( T_('Expecting number for max-points in range %s.'),
                                 build_range_text(-SURVEY_POINTS_MAX, SURVEY_POINTS_MAX) );

         if( $survey->MinPoints > $survey->MaxPoints )
            $errors[] = T_('Min-points must be smaller than max-points.');
      }

      $new_value = trim($vars['title']);
      if( strlen($new_value) < 6 )
         $errors[] = T_('Survey title missing or too short');
      else
         $survey->Title = $new_value;


      // determine edits
      if( $old_vals['type'] != $survey->SurveyType ) $edits[] = T_('Type#edits');
      if( $old_vals['status'] != $survey->Status ) $edits[] = T_('Status#edits');
      if( $old_vals['min_points'] != $survey->MinPoints ) $edits[] = T_('Min-Points#edits');
      if( $old_vals['max_points'] != $survey->MaxPoints ) $edits[] = T_('Max-Points#edits');
      if( $old_vals['title'] != $survey->Title ) $edits[] = T_('Title#edits');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form

function isNumber( $value, $allow_negative=true, $allow_empty=false )
{
   if( $allow_empty && (string)$value == '' )
      return true;
   $rx_sign = ($allow_negative) ? '\\-?' : '';
   return preg_match( "/^{$rx_sign}\d+$/", $value );
}

function build_range_text( $min, $max, $fmt='[%s..%s]', $generic_max=null )
{
   return sprintf( $fmt, $min, $max, $generic_max );
}

?>
