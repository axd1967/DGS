<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Jens-Uwe Gaspar

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
PublishTime by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

$TranslateGroups[] = "Bulletin";

require_once 'include/std_functions.php';
require_once 'include/std_classes.php';
require_once 'include/table_columns.php';
require_once 'include/filter.php';
require_once 'include/classlib_profile.php';
require_once 'include/db/bulletin.php';

$GLOBALS['ThePage'] = new Page('BulletinList');


{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];

   $is_admin = (@$player_row['admin_level'] & ADMIN_DEVELOPER);
   //TODO for testing
   //$is_admin = false;

   $page = "list_bulletins.php?";

   // config for filters
   $status_filter_array = array( T_('All') => '' );
   foreach( Bulletin::getStatusText() as $status => $text )
   {
      if( $is_admin || $status == BULLETIN_STATUS_SHOW || $status == BULLETIN_STATUS_ARCHIVE )
         $status_filter_array[$text] = "B.Status='$status'";
   }
   $category_filter_array = array( T_('All') => '' );
   foreach( Bulletin::getCategoryText() as $category => $text )
      $category_filter_array[$text] = "B.Category='$category'";
   $targettype_filter_array = array( T_('All') => '' );
   foreach( Bulletin::getTargetTypeText() as $ttype => $text )
      $targettype_filter_array[$text] = "B.TargetType='$ttype'";

   $with_text = get_request_arg('text', 0);

   // init search profile
   $search_profile = new SearchProfile( $my_id, PROFTYPE_FILTER_BULLETINS );
   $bfilter = new SearchFilter( '', $search_profile );
   $btable = new Table( 'bulletins', $page, null, '', TABLE_ROWS_NAVI );
   $btable->set_profile_handler( $search_profile );
   $search_profile->handle_action();

   // table filters
   $bfilter->add_filter( 2, 'Text', 'BP.Handle', true);
   $bfilter->add_filter( 3, 'Selection', $status_filter_array, true);
   $bfilter->add_filter( 4, 'Selection', $category_filter_array, true);
   $bfilter->add_filter( 5, 'RelativeDate', 'B.Lastchanged', true,
         array( FC_TIME_UNITS => FRDTU_ALL_ABS, FC_SIZE => 6 ) );
   $bfilter->add_filter( 8, 'Selection', $targettype_filter_array, true);
   $bfilter->init();

   // init table
   $btable->register_filter( $bfilter );
   $btable->add_or_del_column();

   // page vars
   $page_vars = new RequestParameters();
   $page_vars->add_entry( 'text', ($with_text ? 1 : 0) );
   $btable->add_external_parameters( $page_vars, true ); // add as hiddens

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   if( $is_admin )
      $btable->add_tablehead( 1, T_('Actions#bulletin'), 'Image', TABLE_NO_HIDE, '');
   $btable->add_tablehead( 2, T_('Author#bulletin'), 'User', 0, 'Handle+');
   $btable->add_tablehead( 4, T_('Category#bulletin'), 'Enum', TABLE_NO_HIDE, 'Category+');
   $btable->add_tablehead( 3, T_('Status#bulletin'), 'Enum', TABLE_NO_HIDE, 'Status+');
   $btable->add_tablehead( 8, T_('Target#bulletin'), 'Enum', TABLE_NO_HIDE, 'TargetType+');
   $btable->add_tablehead( 5, T_('PublishTime#bulletin'), 'Date', 0, 'PublishTime-');
   $btable->add_tablehead( 6, T_('Subject#bulletin'), null, TABLE_NO_SORT);
   $btable->add_tablehead( 7, T_('Updated#bulletin'), 'Date', 0, 'Lastchanged-');
   $cnt_tablecols = $btable->get_column_count() - ($is_admin ? 1 : 0);

   $btable->set_default_sort( 5 ); //on PublishTime

   $iterator = new ListIterator( 'Bulletin.list',
         $btable->get_query(),
         $btable->current_order_string(),
         $btable->current_limit_string() );
   $iterator->addQuerySQLMerge( Bulletin::build_view_query_sql( $is_admin ) );
   $iterator = Bulletin::load_bulletins( $iterator );

   $show_rows = $btable->compute_show_rows( $iterator->ResultRows );
   $btable->set_found_rows( mysql_found_rows('Bulletin.list.found_rows') );


   $title = T_('Bulletin Archive');
   start_page($title, true, $logged_in, $player_row );

   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe( $iterator->Query );
   section('Bulletin', $title );

   $menu = array();
   $baseURLMenu = $page .
      $btable->current_rows_string(1) .
      $btable->current_sort_string(1) .
      $btable->current_filter_string(1) .
      $btable->current_from_string(1);
   if( $with_text )
      $menu[T_('Hide texts')] = $baseURLMenu.'text=0';
   else
      $menu[T_('Show texts')] = $baseURLMenu.'text=1';
   make_menu( $menu, false);


   while( ($show_rows-- > 0) && list(,$arr_item) = $iterator->getListIterator() )
   {
      list( $bulletin, $orow ) = $arr_item;
      $uid = $bulletin->uid;
      $row_str = array();

      if( $is_admin && @$btable->Is_Column_Displayed[1] )
      {
         $links = anchor( $base_path."admin_bulletin.php?bid={$bulletin->ID}",
               image( $base_path.'images/edit.gif', 'E'),
               T_('Admin Bulletin'), 'class=ButIcon');
         $row_str[1] = $links;
      }

      if( @$btable->Is_Column_Displayed[ 2] )
         $row_str[ 2] = user_reference( REF_LINK, 1, '', $uid, $bulletin->User->Handle, '');
      if( @$btable->Is_Column_Displayed[ 3] )
         $row_str[ 3] = Bulletin::getStatusText( $bulletin->Status );
      if( @$btable->Is_Column_Displayed[ 4] )
         $row_str[ 4] = Bulletin::getCategoryText( $bulletin->Category );
      if( @$btable->Is_Column_Displayed[ 5] )
         $row_str[ 5] = ($bulletin->PublishTime > 0) ? date(DATE_FMT2, $bulletin->PublishTime) : '';
      if( @$btable->Is_Column_Displayed[ 6] )
         $row_str[ 6] = make_html_safe(wordwrap($bulletin->Subject, 60), true);
      if( @$btable->Is_Column_Displayed[ 7] )
         $row_str[ 7] = ($bulletin->Lastchanged > 0) ? date(DATE_FMT2, $bulletin->Lastchanged) : '';
      if( @$btable->Is_Column_Displayed[ 8] )
         $row_str[ 8] = Bulletin::getTargetTypeText( $bulletin->TargetType );

      if( $with_text )
      {
         $row_str['extra_row_class'] = 'BulletinList';
         $row_str['extra_row'] =
            ( $is_admin ? '<td colspan="1"></td>' : '' ) .
            "<td colspan=\"$cnt_tablecols\">" .
                  Bulletin::build_view_bulletin($bulletin) . '</div></td>';
      }

      $btable->add_row( $row_str );
   }

   // print table
   $btable->echo_table();


   $menu_array = array();
   $menu_array[T_('Bulletins')] = "list_bulletins.php";
   if( $is_admin )
   {
      $menu_array[T_('New admin bulletin')] =
         array( 'url' => "admin_bulletin.php", 'class' => 'AdminLink' );
   }

   end_page(@$menu_array);
}

?>
