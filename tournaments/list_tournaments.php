<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once( 'include/classlib_userconfig.php' );
require_once( 'tournaments/include/tournament.php' );

$ThePage = new Page('TournamentList');

{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.list_tournaments');
   $my_id = $player_row['ID'];
   $cfg_tblcols = ConfigTableColumns::load_config( $my_id, CFGCOLS_TOURNAMENTS );

   $page = "list_tournaments.php?";

   // config for filters
   $scope_filter_array = array( T_('All') => '' );
   foreach( Tournament::getScopeText() as $scope => $text )
      $scope_filter_array[$text] = "T.Scope='$scope'";

   $type_filter_array = array( T_('All') => '' );
   foreach( Tournament::getTypeText() as $type => $text )
      $type_filter_array[$text] = "T.Type='$type'";

   $status_filter_array = array( T_('All') => '' );
   foreach( Tournament::getStatusText() as $status => $text )
      $status_filter_array[$text] = "T.Status='$status'";

   $owner_filter_array = array(
         T_('All') => '',
         T_('Mine#T_owner') => "T.Owner_ID=$my_id",
      );

   if( Tournament::isAdmin() )
      $where_scope = "1=1"; // admin can see all scopes (incl. private)
   else
      $where_scope = sprintf( "T.Scope IN ('%s','%s')", TOURNEY_SCOPE_DRAGON, TOURNEY_SCOPE_PUBLIC );


   // init search profile
   $search_profile = new SearchProfile( $my_id, PROFTYPE_FILTER_TOURNAMENT_LIST );
   $tsfilter = new SearchFilter( 's', $search_profile );
   $tfilter = new SearchFilter( '', $search_profile );
   $ttable = new Table( 'tournament', $page, $cfg_tblcols );
   $ttable->set_profile_handler( $search_profile );
   $search_profile->handle_action();

   // static filters
   $tsfilter->add_filter( 1, 'Text', 'TPP.Handle', true, array(
            FC_QUERYSQL => new QuerySQL(
               SQLP_FROM,
                  'INNER JOIN TournamentParticipant AS TP ON TP.tid=T.ID',
                  'INNER JOIN Players AS TPP ON TPP.ID=TP.uid',
               SQLP_WHERE,
                  $where_scope ),
            FC_SIZE => 12 ));
   $tsfilter->add_filter( 2, 'Text', 'TDP.Handle', true, array(
            FC_QUERYSQL => new QuerySQL(
               SQLP_FROM,
                  'INNER JOIN TournamentDirector AS TD ON TD.tid=T.ID',
                  'INNER JOIN Players AS TDP ON TDP.ID=TD.uid',
               SQLP_WHERE,
                  $where_scope ),
            FC_SIZE => 12 ));
   $tsfilter->init();

   // table filters
   $tfilter->add_filter( 1, 'Numeric', 'T.ID', true);
   $tfilter->add_filter( 2, 'Selection', $scope_filter_array, true );
   $tfilter->add_filter( 3, 'Selection', $type_filter_array, true );
   $tfilter->add_filter( 4, 'Selection', $status_filter_array, true );
   $tfilter->add_filter( 6, 'Selection', $owner_filter_array, true );
   $tfilter->add_filter( 8, 'RelativeDate', 'T.StartTime', true,
         array( FC_TIME_UNITS => FRDTU_YMWD|FRDTU_ABS ));
   $tfilter->init();

   // init table
   $ttable->register_filter( $tfilter );
   $ttable->add_or_del_column();

   // External-Form
   $tform = new Form( $ttable->Prefix, $page, FORM_GET, false);
   $tform->set_layout( FLAYOUT_GLOBAL, '1' );
   $tform->set_config( FEC_EXTERNAL_FORM, true );
   $ttable->set_externalform( $tform ); // also attach offset, sort, manage-filter as hidden (table) to ext-form
   $tform->attach_table( $tsfilter ); // attach manage-filter as hiddens (static) to ext-form

   // attach external URL-parameters from static filter
   $ttable->add_external_parameters( $tsfilter->get_req_params(), false );

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $ttable->add_tablehead( 1, T_('ID#headert'), 'Button', TABLE_NO_HIDE, 'ID-');
   $ttable->add_tablehead( 2, T_('Scope#headert'), 'Enum', 0, 'Scope+');
   $ttable->add_tablehead( 3, T_('Type#headert'), 'Enum', 0, 'Type+');
   $ttable->add_tablehead( 4, T_('Status#headert'), 'Enum', 0, 'Status+');
   $ttable->add_tablehead( 5, T_('Title#headert'), '', TABLE_NO_HIDE, 'Title+');
   $ttable->add_tablehead( 6, T_('Owner#headert'), 'User', 0, 'X_OwnerHandle+');
   $ttable->add_tablehead( 7, T_('Last changed#headert'), 'Date', 0, 'Lastchanged-');
   $ttable->add_tablehead( 8, T_('Start time#headert'), 'Date', 0, 'StartTime+');
   $ttable->add_tablehead( 9, T_('End time#headert'), 'Date', 0, 'EndTime+');

   $ttable->set_default_sort( 1 ); //on ID

   // build SQL-query (for tournament-table)
   $query_tsfilter = $tsfilter->get_query(GETFILTER_ALL); // clause-parts for static filter
   $tqsql = $ttable->get_query(); // clause-parts for filter
   $tqsql->merge( $query_tsfilter );

   $iterator = new ListIterator( 'Tournaments',
         $tqsql,
         $ttable->current_order_string('ID-'),
         $ttable->current_limit_string() );
   if( !Tournament::isAdmin() )
      $iterator->addQuerySQLMerge(
         new QuerySQL( SQLP_WHERE, "T.Status<>'".TOURNEY_STATUS_ADMIN."'" ));
   $iterator = Tournament::load_tournaments( $iterator );


   $title = T_('Tournaments');
   start_page($title, true, $logged_in, $player_row,
               button_style($player_row['Button']) );

   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe( $iterator->Query );
   echo "<h3 class=Header>". $title . "</h3>\n";

   // form for static filters
   $tform->set_area( 1 );
   $tform->set_layout( FLAYOUT_AREACONF, 1,
      array( 'title' => T_('Search tournaments by user roles'), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Participating user'),
         'FILTER',      $tsfilter, 1,
         'FILTERERROR', $tsfilter, 1, '<br>'.$FERR1, $FERR2, true ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament director'),
         'FILTER',      $tsfilter, 2,
         'FILTERERROR', $tsfilter, 1, '<br>'.$FERR1, $FERR2, true ));

   $show_rows = $ttable->compute_show_rows( $iterator->ResultRows );
   while( ($show_rows-- > 0) && list(,$tourney) = $iterator->getListIterator() )
   {
      $ID = $tourney->ID;
      $row_str = array();

      if( $ttable->Is_Column_Displayed[ 1] )
         $row_str[ 1] = button_TD_anchor( "view_tournament.php?tid=$ID", $ID );
      if( $ttable->Is_Column_Displayed[ 2] )
         $row_str[ 2] = Tournament::getScopeText( $tourney->Scope );
      if( $ttable->Is_Column_Displayed[ 3] )
         $row_str[ 3] = Tournament::getTypeText( $tourney->Type );
      if( $ttable->Is_Column_Displayed[ 4] )
         $row_str[ 4] = Tournament::getStatusText( $tourney->Status );
      if( $ttable->Is_Column_Displayed[ 5] )
         $row_str[ 5] = make_html_safe( $tourney->Title );
      if( $ttable->Is_Column_Displayed[ 6] )
         $row_str[ 6] = user_reference( REF_LINK, 1, '', $tourney->Owner_ID, $tourney->Owner_Handle, '' );
      if( $ttable->Is_Column_Displayed[ 7] )
         $row_str[ 7] = ($tourney->Lastchanged > 0) ? date(DATE_FMT2, $tourney->Lastchanged) : '';
      if( $ttable->Is_Column_Displayed[ 8] )
         $row_str[ 8] = ($tourney->StartTime > 0) ? date(DATE_FMT2, $tourney->StartTime) : '';
      if( $ttable->Is_Column_Displayed[ 9] )
         $row_str[ 9] = ($tourney->EndTime > 0) ? date(DATE_FMT2, $tourney->EndTime) : '';

      $ttable->add_row( $row_str );
   }

   // print static-filter & table
   echo "\n",
      $tform->print_start_default(),
      $ttable->make_table(),
      "<br>\n",
      $tform->get_form_string(), // static form
      $tform->print_end();


   $menu_array = array();
   $menu_array[T_('Show all tournaments')] = 'tournaments/list_tournaments.php';
   if( Tournament::allow_create($my_id) )
      $menu_array[T_('Add new tournament')] = 'tournaments/edit_tournament.php';

   end_page(@$menu_array);
}

?>
