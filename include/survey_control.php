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
require_once 'include/classlib_user.php';
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';


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
   function is_survey_admin()
   {
      global $player_row;
      //TODO return false; //for testing
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

   function build_view_survey( $survey, $rx_term='' )
   {
      $title = make_html_safe($survey->Title, true, $rx_term);
      $title = preg_replace( "/[\r\n]+/", '<br>', $title ); //reduce multiple LF to one <br>
      $updated_time = sprintf( '[%s]', date(DATE_FMT2, $survey->Lastchanged) );

      return
         "<div class=\"Survey\">\n" .
            "<div class=\"Title\">$title</div>" .
            "<div class=\"Time\">$updated_time</div>" .
            "<div class=\"Options\">opts</div>" .
         "</div>\n";
   }//build_view_survey

} // end of 'SurveyControl'
?>
