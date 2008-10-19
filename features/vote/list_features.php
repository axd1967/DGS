<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

chdir("../../");
require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );
require_once( "include/filter.php" );
require_once( "features/vote/lib_votes.php" );


{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   $my_id = (int)@$player_row['ID'];
   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $is_admin = Feature::is_admin();

   $page = 'list_features.php?';

   // table filters
   $ffilter = new SearchFilter();
   $ffilter->add_filter( 1, 'Numeric', 'FL.ID', true );
   $ffilter->add_filter( 2, 'Selection',     # filter on status
            array( T_('All#filterfeat') => '',
                   T_('New#filterfeat')  => "FL.Status='".FEATSTAT_NEW."'",
                   T_('Unvoted#filterfeat') => "FL.Status IN ('".FEATSTAT_ACK."','".FEATSTAT_WORK."') AND ISNULL(FV.fid)",
                   T_('Voted#filterfeat') => "FL.Status IN ('".FEATSTAT_ACK."','".FEATSTAT_WORK."') AND FV.fid>0",
                   T_('Vote#filterfeat') => "FL.Status IN ('".FEATSTAT_ACK."','".FEATSTAT_WORK."')",
                   T_('Work#filterfeat') => "FL.Status='".FEATSTAT_WORK."'",
                   T_('Done#filterfeat') => "FL.Status='".FEATSTAT_DONE."'",
                   T_('NACK#filterfeat') => "FL.Status='".FEATSTAT_NACK."'" ),
            true, array( FC_DEFAULT => 4 ));
   $filter_subject =&
      $ffilter->add_filter( 3, 'Text', 'FL.Subject', true,
         array( FC_SIZE => 30, FC_SUBSTRING => 1, FC_START_WILD => STARTWILD_OPTMINCHARS ) );
   if( $is_admin )
      $ffilter->add_filter( 4, 'Numeric', 'FL.Editor_ID', true, array( FC_SIZE => 8 ) );
   $ffilter->init(); // parse current value from _GET
   $rx_term = implode('|', $filter_subject->get_rx_terms() );

   $ftable = new Table( 'features', $page );
   $ftable->register_filter( $ffilter );
   $ftable->add_or_del_column();

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $ftable->add_tablehead(33, T_('Actions#header'),     'Image', TABLE_NO_HIDE, ''); // static
   $ftable->add_tablehead( 1, T_('ID#header'),          'ID', 0, 'FL.ID+');
   $ftable->add_tablehead( 2, T_('Status#header'),      'Enum', 0, 'FL.Status+');
   $ftable->add_tablehead( 3, T_('Subject#header'),     '', 0, 'FL.Subject+');
   if( $is_admin )
      $ftable->add_tablehead( 4, T_('Editor#header'),   'User', 0, 'FL.Editor_ID+');
   $ftable->add_tablehead( 5, T_('Created#header'),     'Date', 0, 'FL.Created+');
   $ftable->add_tablehead( 6, T_('Lastchanged#header'), 'Date', 0, 'FL.Lastchanged+');
   $ftable->add_tablehead( 7, T_('My Vote#header'),     'Number', 0, 'FV.Points-');
   $ftable->add_tablehead( 8, T_('Lastvoted#header'),   'Date', 0, 'FV.Lastchanged+');

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
   start_page( $title, true, $logged_in, $player_row );
   if( $DEBUG_SQL ) echo "WHERE: " . make_html_safe($query_ffilter->get_select()) ."<br>";
   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query);

   echo "<h3 class=Header>$title</h3>\n";

   while( ($row = mysql_fetch_assoc( $result )) && $show_rows-- > 0 )
   {
      $feature = Feature::new_from_row($row);
      $fvote   = FeatureVote::new_from_row($row);
      $ID = $feature->id;

      $frow_strings = array();
      if( $ftable->Is_Column_Displayed[33] )
      {
         $links = '';
         $linkspc = "<img src='{$base_path}images/dot.gif' width=17 height=17 alt=''>";
         $allow_edit = $feature->allow_edit( $my_id );

         // vote
         if( $feature->allow_vote( $my_id ) )
         {
            $links .= anchor( "vote_feature.php?fid=$ID",
                  image( "{$base_path}17/wx.gif", 'V'),
                  T_('Vote feature'), 'class=ButIcon');
         }
         else
            $links .= $linkspc;

         // edit
         $links .= '&nbsp;';
         if( $allow_edit )
         {
            $links .= anchor( "edit_feature.php?fid=$ID",
                  image( "{$base_path}images/edit.gif", 'E'),
                  T_('Edit feature'), 'class=ButIcon');
         }
         else
            $links .= $linkspc;

         // delete
         $links .= '&nbsp;';
         if( $allow_edit )
         {
            $links .= anchor( "edit_feature.php?fid=$ID".URI_AMP.'feature_delete=1',
                  image( "{$base_path}images/trashcan.gif", 'X'),
                  T_('Remove feature'), 'class=ButIcon');
         }
         else
            $links .= $linkspc;

         $frow_strings[33] = $links;
      }
      if( $ftable->Is_Column_Displayed[1] )
      {
         $url = "{$base_path}features/vote/vote_feature.php?fid=$ID".URI_AMP.'view=1';
         $frow_strings[1] = "<A HREF=\"$url\">$ID</A>";
      }
      if( $ftable->Is_Column_Displayed[2] )
         $frow_strings[2] = $feature->status;
      if( $ftable->Is_Column_Displayed[3] )
         $frow_strings[3] = make_html_safe( $feature->subject, false, $rx_term);
      if( $is_admin && $ftable->Is_Column_Displayed[4] )
         $frow_strings[4] = user_reference( REF_LINK, 1, '', $feature->editor );
      if( $ftable->Is_Column_Displayed[5] )
         $frow_strings[5] = ($feature->created > 0 ? date(DATEFMT_FEATLIST, $feature->created) : '' );
      if( $ftable->Is_Column_Displayed[6] )
         $frow_strings[6] = ($feature->lastchanged > 0 ? date(DATEFMT_FEATLIST, $feature->lastchanged) : '' );

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
   if( Feature::allow_user_edit( $my_id ) )
      $menu_array[ T_('Add new feature') ] = "features/vote/edit_feature.php";
   $menu_array[ T_('Show votes') ] = "features/vote/list_votes.php";

   end_page(@$menu_array);
}
?>
