<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Common";

chdir('../');
require_once( "include/std_functions.php" );
require_once( 'include/gui_functions.php' );
require_once( "include/table_columns.php" );
require_once( "include/filter.php" );
require_once( "include/classlib_profile.php" );
require_once( "features/lib_votes.php" );


{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_FEATURE_VOTE )
      error('feature_disabled', 'feature_vote(list_votes)');

   $my_id = (int)$player_row['ID'];
   $user_vote_reason = Feature::allow_vote_check();
   $user_can_vote = is_null($user_vote_reason);

   $page = 'list_votes.php?';

   // init search profile
   $search_profile = new SearchProfile( $my_id, PROFTYPE_FILTER_VOTES );
   $vfilter = new SearchFilter( '', $search_profile );
   //$search_profile->register_regex_save_args( '' ); // named-filters FC_FNAME
   $vtable = new Table( 'votes', $page );
   $vtable->set_profile_handler( $search_profile );
   $search_profile->handle_action();

   // table filters
   $vfilter->add_filter( 1, 'Numeric',   'FL.ID', true, array( FC_SIZE => 8 ));
   $vfilter->add_filter( 3, 'Selection', # filter on status
         Feature::build_filter_selection_status('FL.Status'),
         true, array( FC_DEFAULT => 2 )); // def=0..
   $vfilter->add_filter( 4, 'Text',      'FL.Subject', true,
         array( FC_SIZE => 30, FC_SUBSTRING => 1, FC_START_WILD => STARTWILD_OPTMINCHARS ) );
   $vfilter->add_filter(10, 'Numeric',   'sumPoints', true, array( FC_ADD_HAVING => 1 ));
   $vfilter->add_filter(11, 'Numeric',   'countVotes', true, array( FC_ADD_HAVING => 1 ));
   $vfilter->init(); // parse current value from _GET

   // init table
   $vtable->register_filter( $vfilter );
   $vtable->add_or_del_column();

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   // NOTE: col-IDs in sync with list_features.php
   $vtable->add_tablehead( 1, T_('Vote ID#header'),     'Button', TABLE_NO_HIDE, 'FL.ID+');
   $vtable->add_tablehead( 3, T_('Status#header'),      'Enum', 0, 'FL.Status+');
   $vtable->add_tablehead( 4, T_('Subject#header'),     '', 0, 'FL.Subject+');
   $vtable->add_tablehead(10, T_('Points#header'),      'Number', 0, 'sumPoints-');
   $vtable->add_tablehead(11, T_('#Votes#header'),      'Number', 0, 'countVotes-');
   $vtable->add_tablehead(12, T_('#Y#header'),          'Number', 0, 'countYes-');
   $vtable->add_tablehead(13, T_('#N#header'),          'Number', 0, 'countNo-');

   $vtable->set_default_sort(10); //on sumPoints
   $order = $vtable->current_order_string();
   $limit = $vtable->current_limit_string();

   // build SQL-query
   $qsql = FeatureVote::build_query_featurevote_list( $vtable );
   $query = $qsql->get_select() . "$order $limit";

   $result = mysql_query( $query )
      or error('mysql_query_failed', 'votelist.find_data');

   $show_rows = $vtable->compute_show_rows(mysql_num_rows($result));

   $title = T_('Feature vote result list');
   start_page( $title, true, $logged_in, $player_row,
               button_style($player_row['Button']) );
   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query);

   echo "<h3 class=Header>$title</h3>\n";


   while( ($row = mysql_fetch_assoc( $result )) && $show_rows-- > 0 )
   {
      $feature = Feature::new_from_row($row);
      $ID = $feature->id;
      $allow_vote = ( $user_can_vote && $feature->allow_vote() );

      $frow_strings = array();
      if( $vtable->Is_Column_Displayed[ 1] )
      {
         $url = "{$base_path}features/vote_feature.php?fid=$ID";
         $frow_strings[1] = button_TD_anchor( $url, $ID,
               ( $allow_vote ? T_('Vote') : T_('View vote') ));
      }
      if( $vtable->Is_Column_Displayed[3] )
         $frow_strings[3] = $feature->status;
      if( $vtable->Is_Column_Displayed[4] )
         $frow_strings[4] = make_html_safe( wordwrap($feature->subject, 60), true);

      // FeatureVote-fields
      if( $vtable->Is_Column_Displayed[10] )
         $frow_strings[10] = $row['sumPoints'];
      if( $vtable->Is_Column_Displayed[11] )
         $frow_strings[11] = $row['countVotes'];
      if( $vtable->Is_Column_Displayed[12] )
         $frow_strings[12] = $row['countYes'];
      if( $vtable->Is_Column_Displayed[13] )
         $frow_strings[13] = $row['countNo'];

      $vtable->add_row( $frow_strings );
   }
   mysql_free_result($result);
   $vtable->echo_table();

   // end of table

   $notes = Feature::build_feature_notes( $user_vote_reason );
   echo_notes( 'featurenotesTable', T_('Feature notes'), $notes );


   $menu_array = array();
   $menu_array[T_('Vote on features')] = "features/list_features.php";
   if( Feature::is_admin() )
      $menu_array[T_('Add new feature')] =
         array( 'url' => "features/edit_feature.php", 'class' => 'AdminLink' );

   end_page(@$menu_array);
}
?>
