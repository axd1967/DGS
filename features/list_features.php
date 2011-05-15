<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Feature";

chdir('../');
require_once( "include/std_functions.php" );
require_once( 'include/gui_functions.php' );
require_once( "include/table_columns.php" );
require_once( "include/filter.php" );
require_once( "include/classlib_profile.php" );
require_once( 'include/classlib_userconfig.php' );
require_once( 'include/classlib_userquota.php' );
require_once( "features/lib_votes.php" );


{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_FEATURE_VOTE )
      error('feature_disabled', 'feature_vote(list_features)');

   $my_id = (int)@$player_row['ID'];
   $user_quota = UserQuota::load_user_quota($my_id);
   if( is_null($user_quota) )
      error('miss_user_quota', "list_features.user_quota.check($my_id)");
   $cfg_tblcols = ConfigTableColumns::load_config( $my_id, CFGCOLS_FEATURE_LIST );

   $user_vote_reason = Feature::allow_vote_check();
   $user_can_vote = is_null($user_vote_reason);

   $page = 'list_features.php?';

   // init search profile
   $search_profile = new SearchProfile( $my_id, PROFTYPE_FILTER_FEATURES );
   $ffilter = new SearchFilter( '', $search_profile );
   $search_profile->register_regex_save_args( 'my_vote' ); // named-filters FC_FNAME
   $ftable = new Table( 'features', $page, $cfg_tblcols, '', TABLE_ROWS_NAVI );
   $ftable->set_profile_handler( $search_profile );
   $search_profile->handle_action();

   // table filters
   $ffilter->add_filter( 1, 'Numeric', 'F.ID', true, array( FC_SIZE => 8 ));
   $ffilter->add_filter( 3, 'Selection', Feature::build_filter_selection_status('F.Status'), true,
         array( FC_DEFAULT => 2 )); // def=0..
   $filter_subject =&
      $ffilter->add_filter( 4, 'Text', 'F.Subject', true,
         array( FC_SIZE => 30, FC_SUBSTRING => 1, FC_START_WILD => 2 ) );
   $ffilter->add_filter( 6, 'RelativeDate', 'F.Lastchanged', true,
         array( FC_TIME_UNITS => FRDTU_ALL_ABS, FC_SIZE => 8 ));
   $ffilter->add_filter( 8, 'Selection',     # filter on user-voted-state
         array( T_('All#filterfeat')      => '',
                T_('Unvoted#filterfeat')  => "ISNULL(FV.fid)", // FC_DEFAULT
                T_('Voted#filterfeat')    => "FV.fid>0",
                T_('<0#filterfeat')       => "FV.Points<0",
                T_('=0#filterfeat')       => "FV.Points=0",
                T_('>0#filterfeat')       => "FV.Points>0",
         ),
         true, array( FC_FNAME => 'my_vote', FC_STATIC => 1, FC_DEFAULT => 1 )); // def=0..
   $ffilter->add_filter(10, 'Selection', Feature::build_filter_selection_size('F.Size'), true );
   $ffilter->init(); // parse current value from _GET
   $rx_term = implode('|', $filter_subject->get_rx_terms() );

   // init table
   $ftable->register_filter( $ffilter );
   $ftable->add_or_del_column();

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   // NOTE: col-IDs in sync with list_votes.php
   $ftable->add_tablehead( 1, T_('Vote ID#header'),     'Button', TABLE_NO_HIDE, 'F.ID+'); // static
   $ftable->add_tablehead( 2, '',                       'Image', TABLE_NO_HIDE, ''); // edit (static)
   $ftable->add_tablehead( 3, T_('Status#header'),      'Enum', 0, 'F.Status+');
   $ftable->add_tablehead(10, T_('Size#featheader'),    'Enum', 0, 'F.Size+');
   $ftable->add_tablehead( 4, T_('Subject#header'),     '', 0, 'F.Subject+');
   $ftable->add_tablehead( 8, T_('My Vote#featheader'), 'NumberC', TABLE_NO_HIDE, 'FV.Points-');
   $ftable->add_tablehead( 9, T_('Lastvoted#header'),   'Date', 0, 'FV.Lastchanged+');
   $ftable->add_tablehead( 5, T_('Created#header'),     'Date', 0, 'F.Created+');
   $ftable->add_tablehead( 6, T_('Lastchanged#header'), 'Date', 0, 'F.Lastchanged+');

   $ftable->set_default_sort( 1); //on Feature.ID
   $order = $ftable->current_order_string();
   $limit = $ftable->current_limit_string();

   // build SQL-query
   $qsql = Feature::build_query_feature_list( $ftable, $my_id );
   $query = $qsql->get_select() . "$order $limit";

   $result = db_query( 'featurelist.find_data', $query );

   $show_rows = $ftable->compute_show_rows(mysql_num_rows($result));
   $ftable->set_found_rows( mysql_found_rows('featurelist.found_rows') );

   $title = T_('Features to vote on');
   start_page( $title, true, $logged_in, $player_row,
               button_style($player_row['Button']) );
   if( $DEBUG_SQL ) echo "QUERY: ", make_html_safe($query), "<br>\n";
   if( $DEBUG_SQL ) echo "TERMS: ", $rx_term, "<br>\n";

   echo "<h3 class=Header>$title</h3>\n",
      FeatureVote::getFeaturePointsText( $user_quota->feature_points ),
      "<br><br>\n";

   while( ($row = mysql_fetch_assoc( $result )) && $show_rows-- > 0 )
   {
      $feature = Feature::new_from_row($row);
      $fvote   = FeatureVote::new_from_row($row);
      $ID = $feature->id;
      $allow_vote = ( $user_can_vote && $feature->allow_vote() );

      $frow_strings = array();
      if( $ftable->Is_Column_Displayed[ 1] )
      {
         $url = "{$base_path}features/vote_feature.php?fid=$ID";
         $frow_strings[1] = button_TD_anchor( $url, $ID,
               ( $allow_vote ? T_('Vote') : T_('View vote') ));
      }
      if( $feature->allow_edit() && $ftable->Is_Column_Displayed[2] ) // Edit-Action
      {
         $frow_strings[2] = anchor( "edit_feature.php?fid=$ID",
               image( "{$base_path}images/edit.gif", 'E'),
               T_('Edit feature'));
      }
      if( $ftable->Is_Column_Displayed[3] )
         $frow_strings[3] = $feature->status;
      if( $ftable->Is_Column_Displayed[4] )
         $frow_strings[4] = make_html_safe( wordwrap($feature->subject,FEAT_SUBJECT_WRAPLEN), true, $rx_term);
      if( $ftable->Is_Column_Displayed[5] )
         $frow_strings[5] = ($feature->created > 0 ? date(DATEFMT_FEATLIST, $feature->created) : '' );
      if( $ftable->Is_Column_Displayed[6] )
         $frow_strings[6] = ($feature->lastchanged > 0 ? date(DATE_FMT2, $feature->lastchanged) : '' );
      if( !is_null($fvote) )
      {
         if( $ftable->Is_Column_Displayed[8] )
            $frow_strings[8] = FeatureVote::formatPoints( $fvote->points );
         if( $ftable->Is_Column_Displayed[9] )
            $frow_strings[9] = ($fvote->lastchanged > 0 ? date(DATE_FMT2, $fvote->lastchanged) : '' );
      }
      if( $ftable->Is_Column_Displayed[10] )
         $frow_strings[10] = $feature->size;

      $ftable->add_row( $frow_strings );
   }
   mysql_free_result($result);
   $ftable->echo_table();

   // end of table

   $notes = Feature::build_feature_notes( $user_vote_reason );
   echo_notes( 'featurenotesTable', T_('Feature notes'), $notes );


   $menu_array = array();
   $menu_array[T_('Vote on features')] = "features/list_features.php";
   $menu_array[T_('My feature votes')] = "features/list_features.php?my_vote=0";
   $menu_array[T_('Feature Vote Results')] = "features/list_votes.php";
   if( Feature::is_admin() )
      $menu_array[T_('Add new feature')] =
         array( 'url' => "features/edit_feature.php", 'class' => 'AdminLink' );

   end_page(@$menu_array);
}
?>
