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

require_once 'include/error_codes.php';
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'include/survey_control.php';
require_once 'include/classlib_user.php';
require_once 'include/gui_user_functions.php';

$GLOBALS['ThePage'] = new Page('SurveyAdmin');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'admin_survey');
   if ( !ALLOW_SURVEY_VOTE )
      error('feature_disabled', 'admin_survey');
   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'admin_survey');

   $is_admin = SurveyControl::is_survey_admin();
   if ( !$is_admin )
      error('adminlevel_too_low', 'admin_survey');

/* Actual REQUEST calls used:
     ''                       : add new admin survey
     sid=                     : edit existing survey
     preview&sid=             : preview for survey-save
     save&sid=                : save new/updated survey
*/

   $sid = (int) get_request_arg('sid');
   if ( $sid < 0 ) $sid = 0;

   // init
   $survey = ( $sid > 0 ) ? Survey::load_survey($sid) : null;
   if ( is_null($survey) )
      $survey = SurveyControl::new_survey();
   elseif ( !SurveyControl::allow_survey_edit($survey) )
      error('survey_edit_not_allowed', "admin_survey.check.edit($sid)");
   else
   {
      SurveyControl::load_survey_options($survey);
      $survey->loadUserList();
   }

   $s_old_status = $survey->Status;
   $s_old_type = $survey->Type;
   $s_old_sopts = $survey->SurveyOptions;

   $arr_types = SurveyControl::getTypeText();
   $arr_status = SurveyControl::getStatusText();

   // check + parse edit-form
   list( $vars, $edits, $input_errors ) = parse_edit_form( $survey );
   $errors = $input_errors;

   // save survey-object with values from edit-form
   if ( @$_REQUEST['save'] && !@$_REQUEST['preview'] && count($errors) == 0 )
   {
      if ( count($edits) == 0 )
         $errors[] = T_('Sorry, there\'s nothing to save.');
      else
      {
         ta_begin();
         {//HOT-section to update survey, survey-options
            $survey->persist();
            $sid = $survey->ID;

            SurveyControl::update_merged_survey_options(
               $sid, $survey->SurveyOptions, @$vars['_del_sopts'], /*all-fields*/false );

            Survey::persist_survey_userlist( $sid, $survey->UserList );
         }
         ta_end();

         jump_to("admin_survey.php?sid=$sid".URI_AMP."sysmsg=". urlencode(T_('Survey saved!')) );
      }
   }

   // default for <opt>-tag
   if ( empty($vars['survey_opts']) )
      $vars['survey_opts'] = '<opt tag [min_points] "title">description</tt>';

   $page = "admin_survey.php";
   $title = T_('Admin Survey');


   // ---------- Survey EDIT form --------------------------------

   $sform = new Form( 'surveyEdit', $page, FORM_POST );
   $sform->add_hidden( 'sid', $sid );

   $sform->add_row( array(
         'DESCRIPTION', T_('Survey ID'),
         'TEXT',        ($sid ? anchor( $base_path."admin_survey.php?sid=$sid", $sid, T_('Reload Survey') ) . MED_SPACING .
                                anchor( $base_path."view_survey.php?sid=$sid",
                                        image( $base_path.'images/info.gif', T_('View survey'), null, 'class="InTextImage"'))
                              : T_('NEW survey#survey')), ));
   $sform->add_row( array(
         'DESCRIPTION', T_('Survey Author'),
         'TEXT',        $survey->User->user_reference(), ));
   if ( $survey->Created > 0 )
      $sform->add_row( array(
            'DESCRIPTION', T_('Created'),
            'TEXT',        formatDate($survey->Created), ));
   if ( $survey->Lastchanged > 0 )
      $sform->add_row( array(
            'DESCRIPTION', T_('Last changed'),
            'TEXT',        formatDate($survey->Lastchanged), ));
   if ( $sid )
      $sform->add_row( array(
            'DESCRIPTION', T_('Vote User Count#survey'),
            'TEXT',        $survey->hasUserVotes() ? span('FormWarning', $survey->UserCount) : $survey->UserCount, ));

   $sform->add_row( array( 'HR' ));

   if ( count($errors) )
   {
      $sform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $sform->add_empty_row();
   }

   $sform->add_row( array(
         'DESCRIPTION', T_('Current Type#survey'),
         'TEXT',        SurveyControl::getTypeText($s_old_type) ));
   $sform->add_row( array(
         'TAB',
         'SELECTBOX',    'type', 1, $arr_types, $vars['type'], false, ));

   $sform->add_row( array(
         'DESCRIPTION', T_('Current Status'),
         'TEXT',        SurveyControl::getStatusText($s_old_status) ));
   $sform->add_row( array(
         'TAB',
         'SELECTBOX',    'status', 1, $arr_status, $vars['status'], false, ));
   $sform->add_row( array(
         'DESCRIPTION', (Survey::is_point_type($survey->Type)) ? T_('Points#survey') : T_('Selections#survey'),
         'TEXT',        T_('Min') . MINI_SPACING,
         'TEXTINPUT',   'min_points', 3, 5, $vars['min_points'],
         'TEXT',        SMALL_SPACING . T_('Max') . MINI_SPACING,
         'TEXTINPUT',   'max_points', 3, 5, $vars['max_points'], ));
   $sform->add_row( array(
         'DESCRIPTION', T_('Title'),
         'TEXTINPUT',   'title', 80, 255, $vars['title'] ));
   $sform->add_row( array(
         'DESCRIPTION', T_('Survey Options'),
         'TEXTAREA',    'survey_opts', 80, min(12, (int)(2.5*count($survey->SurveyOptions)) ), $vars['survey_opts'], ));
   $sform->add_row( array(
         'DESCRIPTION', T_('User List'),
         'TEXT',        T_('to restrict users to vote on survey, user-id (text or numeric)#survey_userlist'), ));
   $sform->add_row( array(
         'TAB',
         'TEXTINPUT', 'user_list', 80, -1, $vars['user_list'], ));

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

   if ( @$_REQUEST['preview'] || $survey->Title != '' || count($survey->SurveyOptions) > 0 )
   {
      $sform->add_empty_row();
      $sform->add_row( array(
            'DESCRIPTION', T_('Preview'),
            'OWNHTML', '<td class="Preview">' . SurveyControl::build_view_survey($survey) . '</td>', ));
      if ( (string)$vars['user_list'] != '' && is_array($survey->UserListUserRefs) )
      {
         $arr = array();
         foreach ( $survey->UserListUserRefs as $uid => $urow )
            $arr[] = user_reference( REF_LINK, 1, '', $urow ) . "<br>\n";

         $sform->add_row( array(
               'DESCRIPTION', T_('Vote Users#survey'),
               'TEXT', implode('', $arr) ));
      }
   }


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $sform->echo_string();


   $menu_array = array();
   $menu_array[T_('Surveys')] = "list_surveys.php";
   if ( $survey->ID && Survey::is_status_viewable($s_old_status) )
      $menu_array[T_('View survey')] = "view_survey.php?sid=$sid";
   $menu_array[T_('New survey')] = array( 'url' => "admin_survey.php", 'class' => 'AdminLink' );

   end_page(@$menu_array);
}//main


// return [ $header_text, $arr_survey_opts, $errors ]
function check_survey_options( $survey, $sopt_text )
{
   $arr_so = array();
   $errors = array();

   $sopt_text = preg_replace("%<\\s*/\\s*opt\\s*>%i", '', $sopt_text); // remove </opt>-end-tag
   $sopt_text = str_replace("\r", '', $sopt_text); // remove CR

   // preconditional checks before parsing can start
   if ( !preg_match("/<opt\\b/", $sopt_text) )
      $errors[] = T_('Missing &lt;opt>-tag in survey-options-text.');
   if ( count($errors) )
      return array( $arr_so, $errors );

   $sid = $survey->ID;
   $need_points = $survey->need_option_minpoints();
   $last_so = null;
   $prev_text = '';
   $rem_text = trim($sopt_text);
   $sort_order = 0;
   $arr_tags = array(); # tag => 1

   // eat text before first <opt> as header-text
   if ( preg_match("/^(.*?)(<opt.*)$/is", $rem_text, $matches) )
   {
      $header_text = trim($matches[1]);
      $rem_text = $matches[2];
   }
   else
      $header_text = '';

   // matches: $1=prev-opt-text, $2=tag, [ $3=points ], $4=title, $5=remaining
   $regex = "%^(.*?)<opt\\s+(\\S+)\\s+(?:(\S+)\s+)?\"([^\"]*?)\"\\s*>(.*)$%s";

   while ( preg_match($regex, $rem_text, $matches) )
   {
      list( , $prev_text, $tag, $points, $title, $rem_text ) = $matches;
      unset($matches);
      $rem_text = trim($rem_text);
      $title = trim($title);
      $sort_order++;

      if ( preg_match("/<opt/i", $prev_text) )
         $errors[] = sprintf( T_('Bad syntax around %s. &lt;opt> found: &lt;opt> incomplete'), $sort_order );

      if ( !is_null($last_so) && (string)$prev_text != '' )
         $last_so->Text = trim($prev_text);

      // checks
      $sopt = $last_so = new SurveyOption( 0, $sid, 0, $sort_order ); // $sid=0 for NEW

      if ( isNumber($tag, false) && $tag >= 1 && $tag <= 255 )
      {
         if ( !isset($arr_tags[$tag]) )
            $sopt->Tag = (int)$tag;
         else
            $errors[] = sprintf( T_('Tag-label [%s] in %s. &lt;opt> already used.'), $tag, $sort_order );
         $arr_tags[$tag] = 1;
      }
      else
         $errors[] = sprintf( T_('Expecting number for tag-label in %s. &lt;opt> in range %s, but was [%s].'),
            $sort_order, build_range_text(1,255), $tag );

      if ( (string)$points == '' )
         $sopt->MinPoints = ($need_points) ? 1 : 0; // default
      elseif ( isNumber($points) && abs($points) <= SURVEY_POINTS_MAX )
         $sopt->MinPoints = (int)$points;
      else
         $errors[] = sprintf( T_('Expecting number for min-points in %s. &lt;opt> to be in range %s or empty for default, but was [%s].'),
            $sort_order, build_range_text(-SURVEY_POINTS_MAX, SURVEY_POINTS_MAX), $points );

      if ( Survey::is_point_type($survey->Type) && $sopt->MinPoints != 0 )
         $errors[] = sprintf( T_('Only value 0 is allowed for min-points in %s. &lt;opt>.'), $sort_order );
      elseif ( $survey->Type == SURVEY_TYPE_MULTI && $sopt->MinPoints == 0 )
         $errors[] = sprintf( T_('Value 0 is not allowed for min-points in %s. &lt;opt>.'), $sort_order );
      elseif ( $survey->Type == SURVEY_TYPE_SINGLE && $sopt->MinPoints != 1 )
         $errors[] = sprintf( T_('Only value 1 is allowed for min-points in %s. &lt;opt>.'), $sort_order );

      if ( strlen($title) > 0 )
         $sopt->Title = $title;
      else
         $errors[] = sprintf( T_('Survey-option-title in %s. &lt;opt> is missing.'), $sort_order );

      $arr_so[] = $sopt;
   }//while

   if ( preg_match("/<opt/i", $rem_text) )
      $errors[] = T_('Incomplete &lt;opt> found in remaining text');
   if ( !is_null($last_so) && (string)$rem_text != '' )
      $last_so->Text = trim($rem_text);

   $arr_tag_keys = array_keys($arr_tags);
   if ( count($arr_tag_keys) > 0 ) // needed for min/max()
   {
      if ( min($arr_tag_keys) != 1 || max($arr_tag_keys) != count($arr_so) )
         $errors[] = sprintf( T_('Expecting tag-labels to be in range %s.'), build_range_text(1, count($arr_so)) );
   }

   $cnt_so = count($arr_so);
   if ( $cnt_so < 1 || $cnt_so > MAX_SURVEY_OPTIONS )
      $errors[] = sprintf( T_('Expecting %s survey-options, but there are [%s].'),
         build_range_text(1, MAX_SURVEY_OPTIONS), $cnt_so );

   return array( $header_text, $arr_so, $errors );
}//check_survey_options


// return [ arr_merged_survey_opts, arr_del_survey_opts, errors ]
function merge_survey_options( $survey, $arr_survey_opts )
{
   $errors = array();
   $has_votes = $survey->hasUserVotes();

   $arr_tags = array(); # tag => 1
   $arr_merged_so = array();
   $upd_forbidden = false;
   foreach ( $arr_survey_opts as $so ) // check updates
   {
      $s_so = SurveyControl::findMatchingSurveyOption($survey, $so->Tag);
      if ( is_null($s_so) )
         $arr_merged_so[] = $so;
      else
      {
         if ( $has_votes && $s_so->copyValues($so, /*check*/true) ) // SOPT changed?
            $upd_forbidden = true;
         $s_so->copyValues( $so );
         $arr_merged_so[] = $s_so;
      }
      $arr_tags[$so->Tag] = 1;
   }
   if ( $upd_forbidden )
      $errors[] = T_('Update of survey-options not possible: there are user-votes.');

   $arr_del_so = array();
   foreach ( $survey->SurveyOptions as $so ) // check deletes
   {
      if ( isset($arr_tags[$so->Tag]) )
         continue;
      if ( $has_votes )
         $errors[] = sprintf( T_('Delete of survey-option with tag [%s] not possible: it already has user-votes.'), $so->Tag );
      else
         $arr_del_so[] = $so;
   }

   return array( $arr_merged_so, $arr_del_so, $errors );
}//merge_survey_options


// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$survey )
{
   $edits = array();
   $errors = array();
   $is_posted = ( @$_REQUEST['save'] || @$_REQUEST['preview'] );

   // read from props or set defaults
   $vars = array(
      'type'            => $survey->Type,
      'status'          => $survey->Status,
      'flags'           => $survey->Flags,
      'min_points'      => $survey->MinPoints,
      'max_points'      => $survey->MaxPoints,
      'title'           => $survey->Title,
      'survey_opts'     => SurveyControl::buildSurveyOptionsText($survey),
      'user_list'       => implode(' ', $survey->UserListHandles ),
      '_del_sopts'      => array(),
   );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach ( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // parse URL-vars
   if ( $is_posted )
   {
      $survey->setType($vars['type']);
      $survey->setStatus($vars['status']);

      if ( $survey->Type == SURVEY_TYPE_POINTS || $survey->Type == SURVEY_TYPE_SUM )
      {
         $new_value = $vars['min_points'];
         $min_value = ( $survey->Type == SURVEY_TYPE_POINTS ) ? -SURVEY_POINTS_MAX : 0;
         if ( isNumber($new_value) && $new_value >= $min_value && $new_value <= SURVEY_POINTS_MAX )
            $survey->MinPoints = (int)$new_value;
         else
            $errors[] = sprintf( T_('Expecting number for min-points in range %s.'),
                                 build_range_text($min_value, SURVEY_POINTS_MAX) );

         $new_value = $vars['max_points'];
         $min_value = ( $survey->Type == SURVEY_TYPE_POINTS ) ? -SURVEY_POINTS_MAX : 1;
         if ( isNumber($new_value) && $new_value >= $min_value && $new_value <= SURVEY_POINTS_MAX )
            $survey->MaxPoints = (int)$new_value;
         else
            $errors[] = sprintf( T_('Expecting number for max-points in range %s.'),
                                 build_range_text($min_value, SURVEY_POINTS_MAX) );

         if ( $survey->MinPoints > $survey->MaxPoints )
            $errors[] = T_('Min-points must be smaller than max-points.');

         if ( $survey->Type == SURVEY_TYPE_POINTS )
         {
            if ( $survey->MinPoints == $survey->MaxPoints )
               $errors[] = sprintf( T_('Use %s-type instead if min-points equals max-points.'),
                  SurveyControl::getTypeText(SURVEY_TYPE_MULTI) );
            if ( $survey->MaxPoints - $survey->MinPoints == 1 )
               $errors[] = sprintf( T_('Use %s-type instead if difference between min- & max-points is only 1.'),
                  SurveyControl::getTypeText(SURVEY_TYPE_SINGLE) .'|'. SurveyControl::getTypeText(SURVEY_TYPE_MULTI) );
         }
         if ( $survey->Type == SURVEY_TYPE_SUM && $survey->MaxPoints == 1 )
            $errors[] = sprintf( T_('Use %s-type instead if max-points is only 1.'),
               SurveyControl::getTypeText(SURVEY_TYPE_SINGLE) );
      }
      elseif ( $survey->Type == SURVEY_TYPE_MULTI )
      {
         $new_value = $vars['min_points'];
         if ( isNumber($new_value) && $new_value >= 0 && $new_value <= MAX_SURVEY_OPTIONS )
            $survey->MinPoints = (int)$new_value;
         else
            $errors[] = sprintf( T_('Expecting number for min-selections in range %s.'),
                                 build_range_text(0, MAX_SURVEY_OPTIONS) );

         $new_value = $vars['max_points'];
         if ( isNumber($new_value) && $new_value >= 0 && $new_value <= MAX_SURVEY_OPTIONS )
            $survey->MaxPoints = (int)$new_value;
         else
            $errors[] = sprintf( T_('Expecting number for max-selections in range %s.'),
                                 build_range_text(0, MAX_SURVEY_OPTIONS) );

         if ( $survey->MaxPoints > 0 )
         {
            if ( $survey->MinPoints > $survey->MaxPoints )
               $errors[] = T_('Min-selections must be smaller than max-selections.');
            if ( $survey->MinPoints == $survey->MaxPoints )
               $errors[] = T_('Min-selections must not be equal to max-selections: it makes no sense to select all options');
         }

         if ( $survey->MaxPoints == 1 )
            $errors[] = sprintf( T_('Use %s-type instead if max-selections is only 1.'),
               SurveyControl::getTypeText(SURVEY_TYPE_SINGLE) );
      }
      elseif ( $survey->Type == SURVEY_TYPE_SINGLE )
      {
         $new_value = $vars['min_points'];
         if ( !isNumber($new_value) || $new_value )
            $errors[] = sprintf( T_('Expecting value 0 for min-points, but was [%s].'), $new_value );

         $new_value = $vars['max_points'];
         if ( !isNumber($new_value) || $new_value )
            $errors[] = sprintf( T_('Expecting value 0 for max-points, but was [%s].'), $new_value );
      }

      $new_value = trim($vars['title']);
      if ( strlen($new_value) < 6 )
         $errors[] = T_('Survey title missing or too short');
      else
         $survey->Title = $new_value;

      $new_value = trim($vars['survey_opts']);
      if ( (string)$new_value != '' )
      {
         list( $header_text, $arr_survey_opts, $check_errors ) = check_survey_options( $survey, $new_value );
         if ( count($check_errors) == 0 )
            list( $arr_survey_opts, $arr_del_sopts, $check_errors ) = merge_survey_options( $survey, $arr_survey_opts );
         else
            $arr_del_sopts = array();

         if ( count($check_errors) > 0 )
            $errors = array_merge( $errors, $check_errors );
         else
         {
            $survey->Header = $header_text;
            $survey->SurveyOptions = $arr_survey_opts;
            $vars['survey_opts'] = SurveyControl::buildSurveyOptionsText($survey);
            $vars['_del_sopts'] = $arr_del_sopts;
         }
      }

      if ( $survey->Type == SURVEY_TYPE_MULTI )
      {
         $cnt_sopts = count($survey->SurveyOptions);
         if ( $survey->MaxPoints > $cnt_sopts )
            $errors[] = sprintf( T_('Value for max-selections [%s] can not exceed number of survey-options [%s].'),
               $survey->MaxPoints, $cnt_sopts );
      }

      $new_value = trim($vars['user_list']);
      list( $arr_handles, $arr_uids, $arr_urefs, $arr_rejected, $check_errors ) = check_user_list( $new_value, 0 );
      if ( count($check_errors) > 0 )
         $errors = array_merge( $errors, $check_errors );
      else
      {
         $survey->UserList = $arr_uids;
         $survey->UserListHandles = $arr_handles;
         $survey->UserListUserRefs = $arr_urefs;
         $vars['user_list'] = implode(' ', $arr_handles); // re-format

         $survey->setFlag( SURVEY_FLAG_USERLIST, count($survey->UserList) );
      }


      // determine edits
      $has_upd_status = ( $old_vals['status'] != $survey->Status );
      if ( $old_vals['type'] != $survey->Type ) $edits[] = T_('Type#survey');
      if ( $has_upd_status ) $edits[] = T_('Status');
      if ( $old_vals['flags'] != $survey->Flags ) $edits[] = T_('Flags');
      if ( $old_vals['min_points'] != $survey->MinPoints ) $edits[] = T_('Min-Points');
      if ( $old_vals['max_points'] != $survey->MaxPoints ) $edits[] = T_('Max-Points');
      if ( $old_vals['title'] != $survey->Title ) $edits[] = T_('Title');
      if ( $old_vals['survey_opts'] != $vars['survey_opts'] ) $edits[] = T_('Survey-Options');
      if ( $old_vals['user_list'] != $vars['user_list'] ) $edits[] = T_('User List');

      if ( $survey->hasUserVotes() && count($edits) > 0 && !( count($edits) == 1 && $has_upd_status ) )
         $errors[] = T_('Update of survey not allowed, because there are user-votes.');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form

?>
