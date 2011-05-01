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
require_once 'include/filter.php';
require_once 'include/classlib_profile.php';
require_once 'include/survey_control.php';

$GLOBALS['ThePage'] = new Page('SurveyList');


{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_SURVEY_VOTE )
      error('feature_disabled', 'list_surveys');
   $my_id = $player_row['ID'];

   $is_admin = SurveyControl::is_survey_admin();

   $page = "list_surveys.php";

/* Actual REQUEST calls used:
     ''                       : show surveys
*/

   // config for filters
   $status_filter_array = array( T_('All') => '' );
   foreach( SurveyControl::getStatusText() as $status => $text )
   {
      if( $is_admin || $status == SURVEY_STATUS_ACTIVE || $status == SURVEY_STATUS_CLOSED )
         $status_filter_array[$text] = "S.Status='$status'";
   }

   $type_filter_array = array( T_('All') => '' );
   foreach( SurveyControl::getSurveyTypeText() as $type => $text )
      $type_filter_array[$text] = "S.SurveyType='$type'";

   // init search profile
   $search_profile = new SearchProfile( $my_id, PROFTYPE_FILTER_SURVEYS );
   $sfilter = new SearchFilter( '', $search_profile );
   $table = new Table( 'surveys', $page, null, '', TABLE_ROWS_NAVI );
   $table->set_profile_handler( $search_profile );
   $search_profile->handle_action();

   // table filters
   $sfilter->add_filter( 3, 'Selection', $type_filter_array, true);
   $sfilter->add_filter( 4, 'Selection', $status_filter_array, true );
   $sfilter->add_filter( 5, 'Text', 'SP.Handle', true,
         array( FC_FNAME => 'handle' ) );
   $sfilter->add_filter( 6, 'Text', "S.Title #OP #VAL", true,
         array( FC_SIZE => 15, FC_SUBSTRING => 1, FC_START_WILD => 3, FC_SQL_TEMPLATE => 1 ));
   $sfilter->add_filter( 7, 'RelativeDate', 'S.Created', true,
         array( FC_TIME_UNITS => FRDTU_ALL_ABS, FC_SIZE => 6 ) );
   $sfilter->add_filter( 8, 'RelativeDate', 'S.Lastchanged', true,
         array( FC_TIME_UNITS => FRDTU_ALL_ABS, FC_SIZE => 6 ) );
   $sfilter->init();

   $filter_text =& $sfilter->get_filter(6);
   $rx_term = implode('|', $filter_text->get_rx_terms() );

   // init table
   $table->register_filter( $sfilter );
   $table->add_or_del_column();

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $table->add_tablehead( 1, T_('ID#header'), 'Button', TABLE_NO_HIDE, 'S.ID-');
   $table->add_tablehead( 3, T_('Type#survey'), 'Enum', TABLE_NO_HIDE, 'S.Type+');
   $table->add_tablehead( 4, T_('Status#survey'), 'Enum', TABLE_NO_HIDE, 'S.Status+');
   if( $is_admin )
      $table->add_tablehead( 2, new TableHead( T_('Edit Survey#survey'), 'images/edit.gif'), 'ImagesLeft', TABLE_NO_HIDE);
   $table->add_tablehead( 5, T_('Author#survey'), 'User', 0, 'SP.Handle+');
   $table->add_tablehead( 6, T_('Title#survey'), null, TABLE_NO_SORT|TABLE_NO_HIDE );
   $table->add_tablehead( 7, T_('Created#survey'), 'Date', 0, 'S.Created-');
   $table->add_tablehead( 8, T_('Updated#survey'), 'Date', 0, 'S.Lastchanged-');

   $table->set_default_sort( 7, 1 ); //on Created, ID

   $iterator = new ListIterator( 'Survey.list',
         $table->get_query(),
         $table->current_order_string(),
         $table->current_limit_string() );
   $iterator = Survey::load_surveys( $iterator );

   $show_rows = $table->compute_show_rows( $iterator->ResultRows );
   $table->set_found_rows( mysql_found_rows('Survey.list.found_rows') );


   $title = T_('Surveys');
   start_page($title, true, $logged_in, $player_row, button_style($player_row['Button']) );

   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe( $iterator->Query );
   section('Survey', $title );

   while( ($show_rows-- > 0) && list(,$arr_item) = $iterator->getListIterator() )
   {
      list( $survey, $orow ) = $arr_item;
      $uid = $survey->uid;
      $row_str = array();

      if( @$table->Is_Column_Displayed[ 1] )
         $row_str[ 1] = button_TD_anchor( "view_survey.php?sid={$survey->ID}", $survey->ID );
      if( @$table->Is_Column_Displayed[ 2] )
      {
         $links = array();
         if( $is_admin )
         {
            $admin_link = span('AdminLink',
               anchor( $base_path."admin_survey.php?sid={$survey->ID}",
                  image( $base_path.'images/edit.gif', 'E'), T_('Admin Survey'), 'class=ButIcon') );
            $links[] = $admin_link;
         }
         $row_str[ 2] = implode(MINI_SPACING, $links);
      }
      if( @$table->Is_Column_Displayed[ 3] )
         $row_str[ 3] = SurveyControl::getSurveyTypeText( $survey->SurveyType );
      if( @$table->Is_Column_Displayed[ 4] )
         $row_str[ 4] = SurveyControl::getStatusText( $survey->Status );
      if( @$table->Is_Column_Displayed[ 5] )
         $row_str[ 5] = user_reference( REF_LINK, 1, '', $uid, $survey->User->Handle, '');
      if( @$table->Is_Column_Displayed[ 6] )
      {
         $title = make_html_safe( wordwrap($survey->Title, 60), true, $rx_term );
         $row_str[ 6] = preg_replace( "/[\r\n]+/", '<br>', $title ); //reduce multiple LF to one <br>
      }
      if( @$table->Is_Column_Displayed[ 7] )
         $row_str[ 7] = formatDate($survey->Created);
      if( @$table->Is_Column_Displayed[ 8] )
         $row_str[ 8] = formatDate($survey->Lastchanged);

      $table->add_row( $row_str );
   }

   // print table
   $table->echo_table();


   $menu_array = array();
   $menu_array[T_('Surveys')] = "list_surveys.php";
   if( $is_admin )
   {
      $menu_array[T_('New survey')] =
         array( 'url' => "admin_survey.php", 'class' => 'AdminLink' );
   }

   end_page(@$menu_array);
}

?>
