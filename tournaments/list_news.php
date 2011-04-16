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

$TranslateGroups[] = "Tournament";

chdir('..');
require_once( 'include/std_functions.php' );
require_once( 'include/std_classes.php' );
require_once( 'include/table_columns.php' );
require_once( 'include/filter.php' );
require_once( 'include/classlib_profile.php' );
require_once( 'tournaments/include/tournament.php' );
require_once( 'tournaments/include/tournament_news.php' );
require_once( 'tournaments/include/tournament_participant.php' );
require_once( 'tournaments/include/tournament_utils.php' );

$GLOBALS['ThePage'] = new Page('TournamentNewsList');


{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.list_news');
   $my_id = $player_row['ID'];

   $tid = (int) @$_REQUEST['tid'];
   $tourney = Tournament::load_tournament( $tid );
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.list_news.find_tournament($tid)");

   $is_admin = TournamentUtils::isAdmin();
   $allow_edit_tourney = $tourney->allow_edit_tournaments( $my_id );
   $is_participant = ($allow_edit_tourney)
      ? true
      : TournamentParticipant::isTournamentParticipant( $tid, $my_id );

   $page = "list_news.php?";

   // config for filters
   $status_filter_array = array( T_('All') => '' );
   foreach( TournamentNews::getStatusText() as $status => $text )
      $status_filter_array[$text] = "TN.Status='$status'";

   $with_text = get_request_arg('text', 0);

   // init search profile
   $search_profile = new SearchProfile( $my_id, PROFTYPE_FILTER_TOURNAMENT_NEWS );
   $tnfilter = new SearchFilter( '', $search_profile );
   $tntable = new Table( 'tournamentNews', $page, null, '', TABLE_ROWS_NAVI );
   $tntable->set_profile_handler( $search_profile );
   $search_profile->handle_action();

   // table filters
   if( $allow_edit_tourney )
   {
      $tnfilter->add_filter( 2, 'Text', 'TNP.Handle', true);
      $tnfilter->add_filter( 3, 'Selection', $status_filter_array, true);
   }
   $tnfilter->add_filter( 5, 'RelativeDate', 'TN.Lastchanged', true,
         array( FC_TIME_UNITS => FRDTU_ALL_ABS, FC_SIZE => 6 ) );

   $tnfilter->init();

   // init table
   $tntable->register_filter( $tnfilter );
   $tntable->add_or_del_column();

   // page vars
   $page_vars = new RequestParameters();
   $page_vars->add_entry( 'tid', $tid );
   $page_vars->add_entry( 'text', ($with_text ? 1 : 0) );
   $tntable->add_external_parameters( $page_vars, true ); // add as hiddens

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   if( $allow_edit_tourney )
      $tntable->add_tablehead( 1, T_('Actions#tnews'), 'Image', TABLE_NO_HIDE, '');
   $tntable->add_tablehead( 2, T_('Author#tnews'), 'User', 0, 'Handle+');
   if( $allow_edit_tourney )
   {
      $tntable->add_tablehead( 3, T_('Status#tnews'), 'Enum', TABLE_NO_HIDE, 'Status+');
      $tntable->add_tablehead( 4, T_('Flags#tnews'), 'Enum', 0, 'Flags+');
   }
   $tntable->add_tablehead( 5, T_('Published#tnews'), 'Date', 0, 'Published-');
   $tntable->add_tablehead( 6, T_('Subject#tnews'), null, TABLE_NO_SORT);
   $tntable->add_tablehead( 7, T_('Updated#tnews'), 'Date', 0, 'Lastchanged-');
   $cnt_tablecols = $tntable->get_column_count();

   $tntable->set_default_sort( 5 ); //on Published

   $iterator = new ListIterator( 'TournamentNews',
         $tntable->get_query(),
         $tntable->current_order_string(),
         $tntable->current_limit_string() );
   if( !$allow_edit_tourney ) // hide some news for non-TDs / non-TPs
   {
      $qsql = new QuerySQL( SQLP_WHERE,
         "TN.Status IN ('".TNEWS_STATUS_SHOW."','".TNEWS_STATUS_ARCHIVE."')",
         "(TN.Flags & ".TNEWS_FLAG_HIDDEN.") = 0" );
      if( !$is_participant )
         $qsql->add_part( SQLP_WHERE, "(TN.Flags & ".TNEWS_FLAG_PRIVATE.") = 0" );
      $iterator->addQuerySQLMerge( $qsql );
   }
   $iterator = TournamentNews::load_tournament_news( $iterator, $tid );

   $show_rows = $tntable->compute_show_rows( $iterator->ResultRows );
   $tntable->set_found_rows( mysql_found_rows('Tournament.list_news.found_rows') );


   $pagetitle = sprintf( T_('Tournament News #%d'), $tid );
   $title = sprintf( T_('Tournament News Archive of [%s]'), $tourney->Title );
   start_page($pagetitle, true, $logged_in, $player_row );

   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe( $iterator->Query );
   echo "<h3 class=Header>". $title . "</h3>\n";

   $menu = array();
   $baseURLMenu = "tournaments/{$page}tid=$tid".URI_AMP .
      $tntable->current_rows_string(1) .
      $tntable->current_sort_string(1) .
      $tntable->current_filter_string(1) .
      $tntable->current_from_string(1);
   if( $with_text )
      $menu[T_('Hide news texts')] = $baseURLMenu.'text=0';
   else
      $menu[T_('Show news texts')] = $baseURLMenu.'text=1';
   make_menu( $menu, false);


   while( ($show_rows-- > 0) && list(,$arr_item) = $iterator->getListIterator() )
   {
      list( $tnews, $orow ) = $arr_item;
      $uid = $tnews->uid;
      $row_str = array();

      if( $allow_edit_tourney && $tntable->Is_Column_Displayed[1] )
      {
         $links = anchor( $base_path."tournaments/edit_news.php?tid=$tid".URI_AMP."tnid={$tnews->ID}",
               image( $base_path.'images/edit.gif', 'E'),
               T_('Edit tournament news#tnews'), 'class=ButIcon');
         if( $is_admin )
         {
            $links .= MED_SPACING;
            $links .= anchor( $base_path."tournaments/edit_news.php?tid=$tid".URI_AMP."tnid={$tnews->ID}".URI_AMP."tn_del=1",
               image( $base_path.'images/trashcan.gif', 'D'),
               T_('Delete tournament news#tnews'), 'class=ButIcon');
         }
         $row_str[1] = $links;
      }

      if( @$tntable->Is_Column_Displayed[ 2] )
         $row_str[ 2] = user_reference( REF_LINK, 1, '', $uid, $tnews->User->Handle, '');
      if( @$tntable->Is_Column_Displayed[ 3] )
         $row_str[ 3] = TournamentNews::getStatusText( $tnews->Status );
      if( @$tntable->Is_Column_Displayed[ 4] )
         $row_str[ 4] = TournamentNews::getFlagsText( $tnews->Flags );
      if( @$tntable->Is_Column_Displayed[ 5] )
         $row_str[ 5] = ($tnews->Published > 0) ? date(DATE_FMT2, $tnews->Published) : '';
      if( @$tntable->Is_Column_Displayed[ 6] )
         $row_str[ 6] = make_html_safe(wordwrap($tnews->Subject, 60), true);
      if( @$tntable->Is_Column_Displayed[ 7] )
         $row_str[ 7] = ($tnews->Lastchanged > 0) ? date(DATE_FMT2, $tnews->Lastchanged) : '';

      if( $with_text )
      {
         $row_str['extra_row_class'] = 'TournamentNewsList';
         $row_str['extra_row'] =
            '<td colspan="1"></td>' .
            '<td colspan="' . ($cnt_tablecols-1) . '">' .
                  TournamentNews::build_tournament_news($tnews) . '</div></td>';
      }

      $tntable->add_row( $row_str );
   }

   // print table
   $tntable->echo_table();


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   if( $allow_edit_tourney ) # for TD
   {
      $menu_array[T_('Add news#tnews')] =
         array( 'url' => "tournaments/edit_news.php?tid=$tid", 'class' => 'TAdmin' );
      $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );
   }

   end_page(@$menu_array);
}

?>
