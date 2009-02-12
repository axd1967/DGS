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

   $my_id = (int)@$player_row['ID'];
   //TODO: check that guests can view features & votes
   //if( $my_id <= GUESTS_ID_MAX )
      //error('not_allowed_for_guest');

   $is_super_admin = Feature::is_super_admin();
   $user_vote_reason = Feature::allow_vote_check(); //TODO
   $user_can_vote = is_null($user_vote_reason);

   $page = 'list_features.php?';

   // init search profile
   $search_profile = new SearchProfile( $my_id, PROFTYPE_FILTER_FEATURES );
   $ffilter = new SearchFilter( '', $search_profile );
   //$search_profile->register_regex_save_args( '' ); // named-filters FC_FNAME
   $ftable = new Table( 'features', $page );
   $ftable->set_profile_handler( $search_profile );
   $search_profile->handle_action();

   // table filters
   $ffilter->add_filter( 1, 'Numeric', 'FL.ID', true, array( FC_SIZE => 8 ));
   $ffilter->add_filter( 2, 'Selection',     # filter on status
         Feature::build_filter_selection_status('FL.Status'),
         true, array( FC_DEFAULT => 1 )); // def=0..
   $filter_subject =&
      $ffilter->add_filter( 3, 'Text', 'FL.Subject', true,
         array( FC_SIZE => 30, FC_SUBSTRING => 1, FC_START_WILD => STARTWILD_OPTMINCHARS ) );
   $ffilter->add_filter( 6, 'RelativeDate', 'FL.Lastchanged', true,
         array( FC_TIME_UNITS => FRDTU_ALL|FRDTU_ABS ));
   $ffilter->add_filter( 7, 'Selection',     # filter on user-voted-state
         array( T_('All#filterfeat')      => '',
                T_('Unvoted#filterfeat')  => "ISNULL(FV.fid)", // FC_DEFAULT
                T_('Voted#filterfeat')    => "FV.fid>0",
         ),
         true, array( FC_DEFAULT => 1 )); // def=0..
   $ffilter->init(); // parse current value from _GET
   $rx_term = implode('|', $filter_subject->get_rx_terms() );

   // init table
   $ftable->register_filter( $ffilter );
   $ftable->add_or_del_column();

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   //TODO: re-org col-IDs
   $ftable->add_tablehead( 1, T_('Vote ID#header'),     'Button', TABLE_NO_HIDE, 'FL.ID+'); // static
   $ftable->add_tablehead(33, T_(''),                   'Image', TABLE_NO_HIDE, '');
   $ftable->add_tablehead( 2, T_('Status#header'),      'Enum', 0, 'FL.Status+');
   $ftable->add_tablehead( 3, T_('Subject#header'),     '', 0, 'FL.Subject+');
   $ftable->add_tablehead( 7, T_('My Vote#header'),     'Number', 0, 'FV.Points-');
   $ftable->add_tablehead( 8, T_('Lastvoted#header'),   'Date', 0, 'FV.Lastchanged+');
   if( $is_super_admin )
      $ftable->add_tablehead( 4, T_('Editor#header'),   'User', 0, 'FL.Editor_ID+');
   $ftable->add_tablehead( 5, T_('Created#header'),     'Date', 0, 'FL.Created+');
   $ftable->add_tablehead( 6, T_('Lastchanged#header'), 'Date', 0, 'FL.Lastchanged+');

   $ftable->set_default_sort( 1); //on FeatureList.ID
   $order = $ftable->current_order_string();
   $limit = $ftable->current_limit_string();

   // build SQL-query
   $qsql = Feature::build_query_feature_list( $ftable, $my_id );
   $query = $qsql->get_select() . "$order $limit";

   $result = mysql_query( $query )
      or error('mysql_query_failed', 'featurelist.find_data');

   $show_rows = $ftable->compute_show_rows(mysql_num_rows($result));

   $title = T_('Feature list');
   start_page( $title, true, $logged_in, $player_row,
               button_style($player_row['Button']) );
   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query);

   echo "<h3 class=Header>$title</h3>\n";

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
      if( $feature->allow_edit() && $ftable->Is_Column_Displayed[33] ) // Edit-Action
      {
         $frow_strings[33] = anchor( "edit_feature.php?fid=$ID",
               image( "{$base_path}images/edit.gif", 'E'),
               T_('Edit feature'), 'class=ButIcon');
      }
      if( $ftable->Is_Column_Displayed[2] )
         $frow_strings[2] = $feature->status;
      if( $ftable->Is_Column_Displayed[3] )
         $frow_strings[3] = make_html_safe( $feature->subject, false, $rx_term);
      if( $is_super_admin && $ftable->Is_Column_Displayed[4] )
         $frow_strings[4] = user_reference( REF_LINK, 1, '', $feature->editor );
      if( $ftable->Is_Column_Displayed[5] )
         $frow_strings[5] = ($feature->created > 0 ? date(DATEFMT_FEATLIST, $feature->created) : '' );
      if( $ftable->Is_Column_Displayed[6] )
         $frow_strings[6] = ($feature->lastchanged > 0 ? date(DATE_FMT2, $feature->lastchanged) : '' );
      if( !is_null($fvote) )
      {
         if( $ftable->Is_Column_Displayed[7] )
            $frow_strings[7] = $fvote->points;
         if( $ftable->Is_Column_Displayed[8] )
            $frow_strings[8] = ($fvote->lastchanged > 0 ? date(DATE_FMT2, $fvote->lastchanged) : '' );
      }

      $ftable->add_row( $frow_strings );
   }
   mysql_free_result($result);
   $ftable->echo_table();

   // end of table

   $menu_array = array();
   $menu_array[T_('Show votes')]    = "features/list_votes.php";
   if( Feature::is_admin() )
      $menu_array[T_('Add new feature')] = "features/edit_feature.php";

   end_page(@$menu_array);
}
?>
