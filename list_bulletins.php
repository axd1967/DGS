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

$TranslateGroups[] = "Bulletin";

require_once 'include/std_functions.php';
require_once 'include/std_classes.php';
require_once 'include/table_columns.php';
require_once 'include/filter.php';
require_once 'include/db/bulletin.php';
require_once 'include/gui_bulletin.php';
require_once 'include/classlib_profile.php';
require_once 'include/classlib_userconfig.php';

$GLOBALS['ThePage'] = new Page('BulletinList');


{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];
   $cfg_tblcols = ConfigTableColumns::load_config( $my_id, CFGCOLS_BULLETIN_LIST );

   $was_admin = $is_admin = (@$player_row['admin_level'] & ADMIN_DEVELOPER);
   $no_adm = (@$_REQUEST['no_adm']) ? 1 : 0;
   if( $no_adm )
      $is_admin = false;

   $page = "list_bulletins.php?";

/* Actual REQUEST calls used:
     ''                       : show bulletins (without text)
     text=1|0                 : show bulletins with/without text
     read=0|1|2               : show bulletins (0=unread, 1=read, 2=all)
     view=0|1|2               : show bulletins for admin (0=all, 1=mine, 2=others)
     no_adm=0|1               : show bulletins (0=admin-mode, 1=user-mode for admin)
     mr=bid                   : mark bulletin with Bulletin.ID=bid as read
*/

   // mark bulletin as read
   $markread = (int)get_request_arg('mr');
   if( $markread > 0 )
      Bulletin::mark_bulletin_as_read( $markread );

   // config for filters
   $status_filter_array = array( T_('All') => '' );
   if( $is_admin )
      $status_filter_array[T_('Viewable#B_status')] =
         "B.Status IN ('".BULLETIN_STATUS_SHOW."','".BULLETIN_STATUS_ARCHIVE."')";
   $idx_status_default = 0;
   $cnt = count($status_filter_array);
   foreach( GuiBulletin::getStatusText() as $status => $text )
   {
      if( $is_admin || $status == BULLETIN_STATUS_SHOW || $status == BULLETIN_STATUS_ARCHIVE )
      {
         if( $status == BULLETIN_STATUS_SHOW )
            $idx_status_default = $cnt;
         $cnt++;
         $status_filter_array[$text] = "B.Status='$status'";
      }
   }

   $category_filter_array = array( T_('All') => '' );
   foreach( GuiBulletin::getCategoryText() as $category => $text )
      $category_filter_array[$text] = "B.Category='$category'";

   $targettype_filter_array = array( T_('All Types#B_trg') => '' );
   $idxmap_ttypes = array( '' => '', 0 => '' ); // first-entry = all
   $cnt = 1;
   foreach( GuiBulletin::getTargetTypeText() as $ttype => $text )
   {
      if( $ttype == BULLETIN_TRG_UNSET )
         continue;
      $idxmap_ttypes[$cnt++] = $ttype;
      $targettype_filter_array[$text] = "B.TargetType='$ttype'";
   }

   $read_filter_array = array(
         T_('Unread#bulletinread') => 'BR.bid IS NULL',
         T_('Read#bulletinread')   => 'BR.bid > 0',
         T_('All') => '',
      );
   $adminview_filter_array = array(
         T_('All') => '',
         T_('Mine#bulletin')  => 'B_View > 0', //idx=1
         T_('Other#bulletin') => 'B_View = 0',
      );

   $with_text = (get_request_arg('text', 0)) ? 1 : 0;

   // init search profile
   $search_profile = new SearchProfile( $my_id, PROFTYPE_FILTER_BULLETINS );
   $bfilter = new SearchFilter( '', $search_profile );
   $search_profile->register_regex_save_args( 'read|view' ); // named-filters FC_FNAME
   $btable = new Table( 'bulletins', $page, $cfg_tblcols, '', TABLE_ROWS_NAVI );
   $btable->set_profile_handler( $search_profile );
   $search_profile->handle_action();

   // table filters
   $bfilter->add_filter( 2, 'Text', 'BP.Handle', true);
   $bfilter->add_filter( 3, 'Selection', $status_filter_array, true,
         array( FC_DEFAULT => $idx_status_default ) );
   $bfilter->add_filter( 4, 'Selection', $category_filter_array, true);
   $bfilter->add_filter( 5, 'RelativeDate', 'B.Lastchanged', true,
         array( FC_TIME_UNITS => FRDTU_ALL_ABS, FC_SIZE => 6 ) );
   $bfilter->add_filter( 6, 'Text', "CONCAT(B.Subject,' ',B.Text) #OP #VAL", true,
         array( FC_SIZE => 15, FC_SUBSTRING => 1, FC_START_WILD => 3, FC_SQL_TEMPLATE => 1 ));
   $bfilter->add_filter( 8, 'Selection', $targettype_filter_array, true );
   $bfilter->add_filter(10, 'Selection', $read_filter_array, true,
         array( FC_FNAME => 'read', FC_STATIC => 1, FC_DEFAULT => 0 ) );
   if( $is_admin )
      $bfilter->add_filter(12, 'Selection', $adminview_filter_array, true,
            array( FC_FNAME => 'view', FC_STATIC => 1, FC_ADD_HAVING => 1 ) );
   $bfilter->init();
   $filter_text =& $bfilter->get_filter(6);
   $filter_target_type =& $bfilter->get_filter(8);
   $rx_term = implode('|', $filter_text->get_rx_terms() );

   // init table
   $btable->register_filter( $bfilter );
   $btable->add_or_del_column();

   // page vars
   $page_vars = new RequestParameters();
   $page_vars->add_entry( 'text', $with_text );
   $page_vars->add_entry( 'no_adm', $no_adm );
   $btable->add_external_parameters( $page_vars, true ); // add as hiddens

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   if( $is_admin )
      $btable->add_tablehead( 1, T_('Actions#bulletin'), 'Image', TABLE_NO_HIDE|TABLE_NO_SORT);
   $btable->add_tablehead( 8, T_('Target#bulletin'), 'Enum', TABLE_NO_HIDE, 'TargetType+');
   $btable->add_tablehead( 3, T_('Status#bulletin'), 'Enum', TABLE_NO_HIDE, 'Status+');
   $btable->add_tablehead( 4, T_('Category#bulletin'), 'Enum', TABLE_NO_HIDE, 'Category+');
   $btable->add_tablehead( 2, T_('Author#bulletin'), 'User', 0, 'Handle+');
   $btable->add_tablehead( 5, T_('PublishTime#bulletin'), 'Date', TABLE_NO_HIDE, 'PublishTime-');
   $btable->add_tablehead(10, T_('Read#bulletin'), 'Image', TABLE_NO_HIDE, 'BR_Read+' );
   if( $is_admin )
      $btable->add_tablehead(12, T_('View#bulletin'), 'Image', TABLE_NO_HIDE, 'B_View+' );
   $btable->add_tablehead(13, new TableHead( T_('Information#bulletin'), 'images/info.gif'), 'ImagesLeft', 0);
   $btable->add_tablehead( 6, T_('Subject#bulletin'), null, TABLE_NO_SORT);
   $btable->add_tablehead(11, T_('Hits#bulletin'), 'Number', 0, 'CountReads-' );
   $btable->add_tablehead( 9, T_('Expires#bulletin'), 'Date', 0, 'ExpireTime+');
   $btable->add_tablehead( 7, T_('Updated#bulletin'), 'Date', 0, 'Lastchanged-');
   $cnt_tablecols = $btable->get_column_count() - ($is_admin ? 1 : 0);

   $btable->set_default_sort( 5 ); //on PublishTime

   $iterator = new ListIterator( 'Bulletin.list',
         $btable->get_query(),
         $btable->current_order_string(),
         $btable->current_limit_string() );
   $iterator->addQuerySQLMerge(
      Bulletin::build_view_query_sql( $is_admin, /*count*/false, $idxmap_ttypes[ $filter_target_type->get_value() ] ));
   $iterator = Bulletin::load_bulletins( $iterator );

   $show_rows = $btable->compute_show_rows( $iterator->ResultRows );
   $btable->set_found_rows( mysql_found_rows('Bulletin.list.found_rows') );


   $title = T_('Bulletin Board');
   start_page($title, true, $logged_in, $player_row );

   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe( $iterator->Query );
   if( $is_admin )
      $title .= sprintf( ' - %s', span('AdminTitle', T_('Admin View#bulletin')) );
   elseif( $was_admin )
      $title .= sprintf( ' - %s', span('AdminTitle', T_('Admin User-View#bulletin')) );
   section('Bulletin', $title );

   $menu = array();
   $baseURLMenu = $page . "no_adm=$no_adm" . URI_AMP .
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
         $row_str[ 3] = GuiBulletin::getStatusText( $bulletin->Status );
      if( @$btable->Is_Column_Displayed[ 4] )
         $row_str[ 4] = GuiBulletin::getCategoryText( $bulletin->Category );
      if( @$btable->Is_Column_Displayed[ 5] )
         $row_str[ 5] = formatDate($bulletin->PublishTime);
      if( @$btable->Is_Column_Displayed[ 6] )
      {
         $subject = make_html_safe( wordwrap($bulletin->Subject, 60), true, $rx_term );
         $row_str[ 6] = preg_replace( "/[\r\n]+/", '<br>', $subject ); //reduce multiple LF to one <br>
      }
      if( @$btable->Is_Column_Displayed[ 7] )
         $row_str[ 7] = formatDate($bulletin->Lastchanged);
      if( @$btable->Is_Column_Displayed[ 8] )
         $row_str[ 8] = GuiBulletin::getTargetTypeText( $bulletin->TargetType );
      if( @$btable->Is_Column_Displayed[ 9] )
         $row_str[ 9] = formatDate($bulletin->ExpireTime, NO_VALUE);
      if( @$btable->Is_Column_Displayed[10] )
      {
         $row_str[10] = (@$orow['BR_Read'])
            ? image( $base_path.'images/yes.gif', T_('Bulletin marked as read'), null, 'class="InTextImage"' )
            : image( $base_path.'images/no.gif', T_('Bulletin unread'), null, 'class="InTextImage"' );
      }
      if( @$btable->Is_Column_Displayed[11] )
         $row_str[11] = $bulletin->CountReads;
      if( $is_admin && @$btable->Is_Column_Displayed[12] )
      {
         $row_str[12] = (@$orow['B_View'])
            ? image( $base_path.'images/yes.gif', T_('Viewable as user#bulletin'), null, 'class="InTextImage"' )
            : image( $base_path.'images/no.gif', T_('Viewable by admin, but not by you as user#bulletin'), null, 'class="InTextImage"' );
      }
      if( @$btable->Is_Column_Displayed[13] )
      {
         if( $bulletin->tid > 0 )
            $info = echo_image_tournament_info($bulletin->tid);
         elseif( $bulletin->gid > 0 )
            $info = echo_image_game_players($bulletin->gid);
         else
            $info = '';
         $row_str[13] = $info;
      }

      if( $with_text )
      {
         $mark_as_read_url = ( !@$orow['BR_Read'] && $bulletin->Status == BULLETIN_STATUS_SHOW )
            ? $baseURLMenu.'text='.$with_text
            : '';
         $row_str['extra_row_class'] = 'BulletinList';
         $row_str['extra_row'] =
            ( $is_admin ? '<td colspan="1"></td>' : '' ) .
            "<td colspan=\"$cnt_tablecols\">" .
                  GuiBulletin::build_view_bulletin($bulletin, $mark_as_read_url) . '</div></td>';
      }

      $btable->add_row( $row_str );
   }

   // print table
   $btable->echo_table();


   $menu_array = array();
   $menu_array[T_('Unread Bulletins')] = "list_bulletins.php?text=1".URI_AMP."view=1".URI_AMP."no_adm=1";
   if( $was_admin )
   {
      $menu_array[T_('All Bulletins')] =
         array( 'url' => "list_bulletins.php?read=2", 'class' => 'AdminLink' );
      $menu_array[T_('New admin bulletin')] =
         array( 'url' => "admin_bulletin.php", 'class' => 'AdminLink' );
   }

   end_page(@$menu_array);
}

?>
