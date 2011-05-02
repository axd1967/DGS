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

 /* Author: Jens-Uwe Gaspar */

$TranslateGroups[] = "Survey";

require_once 'include/db/survey.php';
require_once 'include/db/survey_option.php';
require_once 'include/db/survey_vote.php';
require_once 'include/std_classes.php';
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';


 /*!
  * \class SurveyControl
  *
  * \brief Controller-Class to handle survey-stuff.
  */

// lazy-init in SurveyControl::get..Text()-funcs
global $ARR_GLOBALS_SURVEY; //PHP5
$ARR_GLOBALS_SURVEY = array();

class SurveyControl
{
   // ------------ static functions ----------------------------

   /*! \brief Returns survey-type-text or all type-texts (if arg=null). */
   function getSurveyTypeText( $type=null )
   {
      global $ARR_GLOBALS_SURVEY;

      // lazy-init of texts
      $key = 'TYPE';
      if( !isset($ARR_GLOBALS_SURVEY[$key]) )
      {
         $arr = array();
         $arr[SURVEY_TYPE_POINTS]   = T_('Points#S_type');
         //TODO $arr[SURVEY_TYPE_SUM]      = T_('Sum#S_type');
         //TODO $arr[SURVEY_TYPE_SINGLE]   = T_('Single#S_type');
         //TODO $arr[SURVEY_TYPE_MULTI]    = T_('Multi#S_type');
         $ARR_GLOBALS_SURVEY[$key] = $arr;
      }

      if( is_null($type) )
         return $ARR_GLOBALS_SURVEY[$key];

      if( !isset($ARR_GLOBALS_SURVEY[$key][$type]) )
         error('invalid_args', "SurveyControl::getSurveyTypeText($type,$key)");
      return $ARR_GLOBALS_SURVEY[$key][$type];
   }

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   function getStatusText( $status=null )
   {
      global $ARR_GLOBALS_SURVEY;

      // lazy-init of texts
      $key = 'STATUS';
      if( !isset($ARR_GLOBALS_SURVEY[$key]) )
      {
         $arr = array();
         $arr[SURVEY_STATUS_NEW]    = T_('New#S_status');
         $arr[SURVEY_STATUS_ACTIVE] = T_('Active#S_status');
         $arr[SURVEY_STATUS_CLOSED] = T_('Closed#S_status');
         $arr[SURVEY_STATUS_DELETE] = T_('Delete#S_status');
         $ARR_GLOBALS_SURVEY[$key] = $arr;
      }

      if( is_null($status) )
         return $ARR_GLOBALS_SURVEY[$key];

      if( !isset($ARR_GLOBALS_SURVEY[$key][$status]) )
         error('invalid_args', "SurveyControl::getStatusText($status,$key)");
      return $ARR_GLOBALS_SURVEY[$key][$status];
   }

   /*! \brief Returns true if current players is survey-admin. */
   function is_survey_admin( $check_super_admin=false )
   {
      global $player_row;
      //TODO return false; //for testing
      if( $check_super_admin )
         return (@$player_row['admin_level'] & ADMIN_DEVELOPER);
      else
         return (@$player_row['admin_level'] & (ADMIN_SURVEY|ADMIN_DEVELOPER));
   }

   /*! \brief Returns new Survey-object for user and args. */
   function new_survey()
   {
      global $player_row;

      $uid = (int)@$player_row['ID'];
      if( !is_numeric($uid) || $uid <= GUESTS_ID_MAX )
         error('invalid_args', "SurveyControl::new_survey.check.uid($uid)");
      $user = new User( $uid, @$player_row['Name'], @$player_row['Handle'] );

      $survey = new Survey( 0, $uid, $user );
      return $survey;
   }//new_survey

   /*! \brief Returns true if this Survey can be edited by admin. */
   function allow_survey_edit( $survey )
   {
      if( SurveyControl::is_survey_admin() )
         return true;

      return ( $survey->Status == SURVEY_STATUS_NEW );
   }//allow_survey_edit

   function load_survey_options( &$survey )
   {
      $sid = $survey->ID;
      $iterator = new ListIterator( "SurveyControl::load_survey_options($sid)" );
      $iterator = SurveyOption::load_survey_options( $iterator, $sid );
      $survey->SurveyOptions = $iterator->getItems();
   }

   /*! \brief Builds markup-text for admin-survey from array of SurveyOption-objects. */
   function buildSurveyOptionsText( $survey )
   {
      $out = array();
      $need_points = $survey->need_option_minpoints();
      foreach( $survey->SurveyOptions as $so )
      {
         $points = ($need_points) ? ' ' . $so->MinPoints : '';
         $out[] = trim( sprintf("<opt %s%s \"%s\"> %s", $so->Tag, $points, trim($so->Title), trim($so->Text) ) );
      }
      return trim(implode("\r\n\r\n", $out));
   }//buildSurveyOptionsText

   /*! \brief Return cloned SurveyOption in Survey->SurveyOptions array matching on same Tag-value; null otherwise. */
   function findMatchingSurveyOption( $survey, $tag )
   {
      foreach( $survey->SurveyOptions as $so )
      {
         if( $so->Tag == $tag )
            return SurveyOption::cloneSurveyOption($so);
      }
      return null;
   }

   /*! \brief Adds/updated/deletes SurveyOption-table-entries. */
   function update_merged_survey_options( $sid, $sopts_save, $sopts_del )
   {
      $sid = (int)$sid;

      $arr_del = array();
      foreach( $sopts_del as $so )
      {
         if( $so->ID == 0 )
            error('invalid_args', "SurveyControl::update_merged_survey_options.check_del($sid)");
         $arr_del[] = $so->ID;
      }
      SurveyOption::delete_survey_options( $sid, $arr_del );

      SurveyOption::persist_survey_options( $sid, $sopts_save ); // add new, update existing
   }//update_merged_survey_options

   /*! \brief Returns QuerySQL with restrictions to view surveys to what current user is allowed to view. */
   function build_view_query_sql()
   {
      $qsql = new QuerySQL();
      if( !SurveyControl::is_survey_admin() )
         $qsql->add_part( SQLP_WHERE, "S.Status IN ('".SURVEY_STATUS_ACTIVE."','".SURVEY_STATUS_CLOSED."')" );
      return $qsql;
   }//build_view_query_sql

   function build_points_array( $min, $max )
   {
      if( $min > $max )
         swap($min, $max);

      $arr = array();
      if( $min > 0 || $max < 0 )
         $arr[0] = '&nbsp;0';
      for( $val = $max; $val >= $min; $val-- )
         $arr[$val] = ($val <= 0) ? $val : "+$val";
      if( isset($arr[0]) )
         $arr[0] = '&nbsp;0';

      return $arr;
   }//build_points_array

   function build_view_survey( $survey, $page='', $rx_term='' )
   {
      $sform = null;
      if( $page && $survey->ID > 0 && $survey->Status == SURVEY_STATUS_ACTIVE )
      {
         $sform = new Form( 'surveyVote', $page, FORM_GET );
         $sform->add_hidden( 'sid', $survey->ID );
      }

      $survey_title = make_html_safe($survey->Title, true, $rx_term);
      $survey_title = preg_replace( "/[\r\n]+/", '<br>', $survey_title ); //reduce multiple LF to one <br>
      $extra_text = sprintf( '(%s) [%s]', span('Status', SurveyControl::getStatusText($survey->Status)),
         date(DATE_FMT2, $survey->Lastchanged) );

      $arr_points = $def_points = 0;
      if( $survey->SurveyType == SURVEY_TYPE_POINTS )
         $arr_points = SurveyControl::build_points_array( $survey->MinPoints, $survey->MaxPoints );

      $vote = '';
      $s_opts = array();
      foreach( $survey->SurveyOptions as $so )
      {
         $fname = 'so' . $so->ID;
         if( $sform && $arr_points )
         {
            $sel_points = $def_points; //TODO use loaded user-config, else default
            $vote = $sform->print_insert_select_box( $fname, 1, $arr_points, $sel_points, false ) . MED_SPACING;
         }
         $title = span('Title', make_html_safe($so->Title, true) );
         $text  = ($so->Text) ? sprintf( '<div class="Text">%s</div>', make_html_safe($so->Text, true) ) : '';
         if( $survey->SurveyType == SURVEY_TYPE_POINTS )
            $s_opts[] = '   <li>' . $vote . trim($title . $text) . '</li>';
      }
      $opts_text = sprintf( "\n  <ol type=\"A\">\n%s\n  </ol>\n", implode("\n", $s_opts) );

      if( $sform )
      {
         $action_text = $sform->print_insert_submit_button( 'save', T_('Save vote') );
         $action_text = " <div class=\"Actions\">$action_text</div>\n";
      }
      else
         $action_text = '';

      $div_survey = "\n<div class=\"Survey\">\n" .
            " <div class=\"Title\">$survey_title</div>\n" .
            " <div class=\"Extra\">$extra_text</div>\n" .
            " <div class=\"Options\">$opts_text</div>\n" .
            $action_text .
         "</div>\n";

      if( $sform )
      {
         return $sform->print_start_default()
            . $div_survey
            . $sform->get_form_string() // static form
            . $sform->print_end();
      }
      else
         return $div_survey;
   }//build_view_survey

} // end of 'SurveyControl'
?>
