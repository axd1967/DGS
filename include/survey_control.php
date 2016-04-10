<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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
class SurveyControl
{
   private static $ARR_SURVEY_TEXTS = array(); // lazy-init in SurveyControl::get..Text()-funcs: [key][id] => text

   // ------------ static functions ----------------------------

   /*! \brief Returns survey-type-text or all type-texts (if arg=null). */
   public static function getTypeText( $type=null )
   {
      // lazy-init of texts
      $key = 'TYPE';
      if ( !isset(self::$ARR_SURVEY_TEXTS[$key]) )
      {
         $arr = array();
         $arr[SURVEY_TYPE_POINTS]   = T_('Points#S_type');
         $arr[SURVEY_TYPE_SUM]      = T_('Sum#S_type');
         $arr[SURVEY_TYPE_SINGLE]   = T_('Single#S_type');
         $arr[SURVEY_TYPE_MULTI]    = T_('Multi#S_type');
         self::$ARR_SURVEY_TEXTS[$key] = $arr;
      }

      if ( is_null($type) )
         return self::$ARR_SURVEY_TEXTS[$key];

      if ( !isset(self::$ARR_SURVEY_TEXTS[$key][$type]) )
         error('invalid_args', "SurveyControl:getTypeText($type,$key)");
      return self::$ARR_SURVEY_TEXTS[$key][$type];
   }//getTypeText

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   public static function getStatusText( $status=null )
   {
      // lazy-init of texts
      $key = 'STATUS';
      if ( !isset(self::$ARR_SURVEY_TEXTS[$key]) )
      {
         $arr = array();
         $arr[SURVEY_STATUS_NEW]    = T_('New#S_status');
         $arr[SURVEY_STATUS_ACTIVE] = T_('Active#S_status');
         $arr[SURVEY_STATUS_CLOSED] = T_('Closed#S_status');
         $arr[SURVEY_STATUS_DELETE] = T_('Delete#S_status');
         self::$ARR_SURVEY_TEXTS[$key] = $arr;
      }

      if ( is_null($status) )
         return self::$ARR_SURVEY_TEXTS[$key];

      if ( !isset(self::$ARR_SURVEY_TEXTS[$key][$status]) )
         error('invalid_args', "SurveyControl:getStatusText($status,$key)");
      return self::$ARR_SURVEY_TEXTS[$key][$status];
   }//getStatusText

   /*! \brief Returns Flags-text or all Flags-texts (if arg=null). */
   public static function getFlagsText( $flag=null )
   {
      // lazy-init of texts
      $key = 'FLAGS';
      if ( !isset(self::$ARR_SURVEY_TEXTS[$key]) )
      {
         $arr = array();
         $arr[SURVEY_FLAG_USERLIST] = T_('User-List#S_flag');
         self::$ARR_SURVEY_TEXTS[$key] = $arr;
      }

      if ( is_null($flag) )
         return self::$ARR_SURVEY_TEXTS[$key];
      if ( !isset(self::$ARR_SURVEY_TEXTS[$key][$flag]) )
         error('invalid_args', "SurveyControl:getFlagsText($flag,$short)");
      return self::$ARR_SURVEY_TEXTS[$key][$flag];
   }//getFlagsText

   /*! \brief Returns text-representation of survey-flags. */
   public static function formatFlags( $flags, $zero_val='', $intersect_flags=0, $class=null )
   {
      $check_flags = ( $intersect_flags > 0 ) ? $flags & $intersect_flags : $flags;

      $arr = array();
      $arr_flags = self::getFlagsText();
      foreach ( $arr_flags as $flag => $flagtext )
      {
         if ( $check_flags & $flag )
            $arr[] = ($class) ? span($class, $flagtext) : $flagtext;
      }
      return (count($arr)) ? implode(', ', $arr) : $zero_val;
   }//formatFlags

   /*! \brief Returns true if current players is survey-admin. */
   public static function is_survey_admin()
   {
      global $player_row;
      return (@$player_row['admin_level'] & (ADMIN_SURVEY|ADMIN_DEVELOPER));
   }

   /*! \brief Returns new Survey-object for user and args. */
   public static function new_survey()
   {
      global $player_row;

      $uid = (int)@$player_row['ID'];
      if ( !is_numeric($uid) || $uid <= GUESTS_ID_MAX )
         error('invalid_args', "SurveyControl:new_survey.check.uid($uid)");
      $user = new User( $uid, @$player_row['Name'], @$player_row['Handle'] );

      $survey = new Survey( 0, $uid, $user );
      return $survey;
   }//new_survey

   /*! \brief Returns true if this Survey can be edited by admin. */
   public static function allow_survey_edit( $survey )
   {
      if ( self::is_survey_admin() )
         return true;

      return ( $survey->Status == SURVEY_STATUS_NEW );
   }//allow_survey_edit

   /*! \brief Returns true if current user is eligible to vote on survey; add errors with reason why not allowed. */
   public static function allow_survey_vote( $survey, &$errors )
   {
      global $player_row, $NOW;

      if ( $survey->Status != SURVEY_STATUS_ACTIVE )
         return false;

      if ( @$player_row['AdminOptions'] & ADMOPT_DENY_SURVEY_VOTE )
      {
         $errors[] = T_('Survey-Voting has been denied.');
         return false;
      }

      // vote only allowed by users on user-list
      if ( $survey->Flags & SURVEY_FLAG_USERLIST )
      {
         if ( Survey::exists_survey_user($survey->ID, $player_row['ID']) )
            return true;

         $errors[] = T_('You are not eligible to vote on this survey because of user-list-restriction.');
         return false;
      }

      if ( self::is_survey_admin() )
         return true;

      // vote only allowed by users actively playing: 2 finished-games, played in last 30 days
      if ( @$player_row['Finished'] < 2 || @$player_row['X_LastMove'] < $NOW - 30 * SECS_PER_DAY )
      {
         $errors[] = T_('To be eligible to vote on this survey you need to actively play in games.');
         return false;
      }

      return true;
   }//allow_survey_vote

   /*!
    * \brief Loads & fills Survey->SurveyOptions; with UserVotePoints/UserVoted from a users SurveyVote-entries
    *        if $uid given.
    */
   public static function load_survey_options( &$survey, $uid=0, $order_result=false )
   {
      $sid = $survey->ID;
      $iterator = new ListIterator( "SurveyControl:load_survey_options($sid,$uid)",
         ( $order_result ? new QuerySQL( SQLP_ORDER, 'SOPT.Score DESC' ) : null ) );
      if ( $uid > 0 ) // with user-vote
      {
         $iterator->addQuerySQLMerge( new QuerySQL(
            SQLP_FIELDS, "IFNULL(SV.Points,".SQL_NO_POINTS.") AS SV_Points",
            SQLP_FROM,   "LEFT JOIN SurveyVote AS SV ON SV.soid=SOPT.ID AND SV.uid=$uid" ) );
      }

      $iterator = SurveyOption::load_survey_options( $iterator, $sid, /*sort*/ !$order_result );

      if ( $uid > 0 ) // with user-vote
      {
         $arr = array();
         $survey->UserVoted = false;
         while ( list(,$arr_item) = $iterator->getListIterator() )
         {
            list( $sopt, $orow ) = $arr_item;
            if ( @$orow['SV_Points'] == SQL_NO_POINTS )
               $sopt->UserVotePoints = null;
            else
            {
               $sopt->UserVotePoints = (int)@$orow['SV_Points'];
               $survey->UserVoted = true;
            }
            $arr[] = $sopt;
         }
         $survey->SurveyOptions = $arr;
      }
      else
      {
         $survey->SurveyOptions = $iterator->getItems();
         $survey->UserVoted = null;
      }
   }//load_survey_options

   /*! \brief Builds markup-text for admin-survey from array of SurveyOption-objects. */
   public static function buildSurveyOptionsText( $survey )
   {
      $out = array();
      if ( (string)$survey->Header != '' )
         $out[] = $survey->Header;

      $need_points = $survey->need_option_minpoints();
      foreach ( $survey->SurveyOptions as $so )
      {
         $points = ($need_points) ? ' ' . $so->MinPoints : '';
         $out[] = trim( sprintf("<opt %s%s \"%s\"> %s", $so->Tag, $points, trim($so->Title), trim($so->Text) ) );
      }
      return trim(implode("\r\n\r\n", $out));
   }//buildSurveyOptionsText

   /*! \brief Return cloned SurveyOption in Survey->SurveyOptions array matching on same Tag-value; null otherwise. */
   public static function findMatchingSurveyOption( $survey, $tag )
   {
      foreach ( $survey->SurveyOptions as $so )
      {
         if ( $so->Tag == $tag )
            return SurveyOption::cloneSurveyOption($so);
      }
      return null;
   }

   /*!
    * \brief Adds/updates/deletes SurveyOption-table-entries.
    * \param $sopts_save [ SurveyOption, ... ]; sets sid in given SurveyOptions for NEW entries
    * \param $sopts_del [ SurveyOption.ID, ... ]
    * \param $all_fields true = update all fields, false = skip Score-field for update (use defaults for insert)
    */
   public static function update_merged_survey_options( $sid, &$sopts_save, $sopts_del, $all_fields )
   {
      $sid = (int)$sid;

      $arr_del = array();
      foreach ( $sopts_del as $so )
      {
         if ( $so->ID == 0 )
            error('invalid_args', "SurveyControl:update_merged_survey_options.check_del($sid)");
         $arr_del[] = $so->ID;
      }
      SurveyOption::delete_survey_options( $sid, $arr_del );

      SurveyOption::persist_survey_options( $sid, $sopts_save, $all_fields ); // add new, update existing
   }//update_merged_survey_options

   /*! \brief Returns QuerySQL with restrictions to view surveys to what current user is allowed to view. */
   public static function build_view_query_sql( $is_admin )
   {
      $qsql = new QuerySQL();
      if ( !$is_admin )
         $qsql->add_part( SQLP_WHERE, "S.Status IN ('".SURVEY_STATUS_ACTIVE."','".SURVEY_STATUS_CLOSED."')" );
      return $qsql;
   }//build_view_query_sql

   public static function build_points_array( $min, $max, $with_plus=true, $dir_asc=false )
   {
      if ( $min > $max )
         swap($min, $max);

      $arr = array();
      $plus = ($with_plus) ? '+' : MINI_SPACING;
      if ( $dir_asc ) // direction ascending
      {
         for ( $val = $min; $val <= $max; $val++ )
            $arr[$val] = ($val <= 0) ? $val : $plus.$val;
      }
      else // direction descending
      {
         for ( $val = $max; $val >= $min; $val-- )
            $arr[$val] = ($val <= 0) ? $val : $plus.$val;
      }
      if ( isset($arr[0]) )
         $arr[0] = MINI_SPACING . 0;

      return $arr;
   }//build_points_array

   public static function build_view_survey( $survey, $allow_vote=false, $page='', $rx_term='' )
   {
      $sform = null;
      if ( $allow_vote && $page && $survey->ID > 0 && $survey->Status == SURVEY_STATUS_ACTIVE )
      {
         $sform = new Form( 'surveyVote', $page, FORM_GET );
         $sform->add_hidden( 'sid', $survey->ID );
      }
      $show_uservote = $allow_vote && Survey::is_status_viewable($survey->Status);
      $show_result = self::is_survey_admin() || ( $survey->Status == SURVEY_STATUS_CLOSED )
         || ( $survey->Status == SURVEY_STATUS_ACTIVE && $survey->UserVoted );

      $survey_title = make_html_safe($survey->Title, true, $rx_term);
      $survey_title = preg_replace( "/[\r\n]+/", '<br>', $survey_title ); //reduce multiple LF to one <br>

      $extra_text = sprintf( '(%s)%s [%s]',
         span('Status', self::getStatusText($survey->Status)),
         ( $show_result ? span('Result', $survey->UserCount, ' #%s', T_('Vote User Count#survey')) : '' ),
         date(DATE_FMT2, $survey->Lastchanged) );

      if ( $survey->Type == SURVEY_TYPE_SUM )
      {
         $optheader_text = sprintf( T_('You have to spend %s points in total for voting on all options.'),
            ( $survey->MinPoints == $survey->MaxPoints )
               ? $survey->MaxPoints
               : build_range_text($survey->MinPoints, $survey->MaxPoints, '%s-%s') );
      }
      elseif ( $survey->Type == SURVEY_TYPE_MULTI && ( $survey->MinPoints > 0 || $survey->MaxPoints > 0 ) )
      {
         $optheader_text = sprintf( T_('You can select %s checkbox(es) for your vote.'),
               ( ($survey->MinPoints == $survey->MaxPoints)
                  ? $survey->MaxPoints
                  : "{$survey->MinPoints}-{$survey->MaxPoints}" ) );
      }
      else
         $optheader_text = '';

      if ( (string)$survey->Header != '' )
         $optheader_text = trim($survey->Header . "\n\n" . $optheader_text);
      if ( (string)$optheader_text != '' )
         $optheader_text = make_html_safe($optheader_text, true);

      if ( $survey->Type == SURVEY_TYPE_POINTS )
         $arr_points = self::build_points_array( $survey->MinPoints, $survey->MaxPoints );
      elseif ( $survey->Type == SURVEY_TYPE_SUM )
         $arr_points = self::build_points_array( 0, $survey->MaxPoints, /*with-plus*/false, /*dir-asc*/true );
      else
         $arr_points = 0;

      $vote = $user_vote = $result = '';
      $s_opts = array();
      foreach ( $survey->SurveyOptions as $so )
      {
         $fname = 'so' . $so->ID;
         $label = $so->buildLabel();

         if ( $sform )
         {
            $sel_points = (int)$so->UserVotePoints; // cast null|int -> int
            if ( Survey::is_point_type($survey->Type) )
               $vote = $sform->print_insert_select_box( $fname, 1, $arr_points, $sel_points, false );
            elseif ( $survey->Type == SURVEY_TYPE_MULTI )
               $vote = $sform->print_insert_checkbox( $fname, 1, '', $sel_points, '' );
            elseif ( $survey->Type == SURVEY_TYPE_SINGLE )
               $vote = $sform->print_insert_radio_buttonsx( 'so', array( $so->ID => '' ), ($sel_points ? $so->ID : 0) );
            else
               $vote = '???'; // shouldn't happen
         }
         if ( $show_uservote )
            $user_vote = span('UserVote', ( !is_null($so->UserVotePoints) ? formatNumber($so->UserVotePoints) : '-' ),
               '[%s]', T_('My vote#survey') );
         if ( $show_result )
         {
            $result = ( $survey->hasUserVotes() )
               ? span('Result', formatNumber($so->Score), '%s', T_('All votes#survey') )
               : '';
         }
         $title = span('Title', make_html_safe($so->Title, true) );
         $text  = ($so->Text) ? sprintf( '<div class="Text">%s</div>', make_html_safe($so->Text, true) ) : '';

         $s_opts[] = "   <tr><td class=\"Result\">$result</td> <td class=\"UserVote\">$user_vote</td> " .
            "<td class=\"FormElem\">$vote</td> <td class=\"Label\">$label</td> " .
            "<td class=\"Data\">{$title}{$text}</td></tr>";
      }
      $opts_text = sprintf( "\n  <table>\n%s\n  </table>\n", implode("\n", $s_opts) );

      if ( $sform )
         $action_text = $sform->print_insert_submit_button( 'save', T_('Save vote') );
      else
         $action_text = '';
      // CSS needs something below floats
      $notes = sprintf( make_html_safe( T_('Notes: %s, %s#survey'), true),
                        span('Result',   make_html_safe( T_('All votes#survey'), true), '%s'),
                        span('UserVote', make_html_safe( T_('Your vote#survey'), true), '[%s]') );
      #$action_text .= span('Notes', make_html_safe($notes, true) );
      $action_text .= span('Notes', $notes);

      $div_survey = "\n<div class=\"Survey\">\n" .
            " <div class=\"Title\">$survey_title</div>\n" .
            " <div class=\"Extra\">$extra_text</div>\n" .
            ($optheader_text ? " <div class=\"OptionHeader\">$optheader_text</div>\n" : '' ).
            " <div class=\"Options\">$opts_text</div>\n" .
            " <div class=\"Actions\">$action_text</div>\n" .
         "</div>\n";

      if ( $sform )
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
