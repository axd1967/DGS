<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/std_functions.php';
require_once 'include/std_classes.php';
require_once 'include/table_columns.php';
require_once 'include/game_functions.php';
require_once 'include/time_functions.php';
require_once 'include/filter.php';
require_once 'include/classlib_profile.php';
require_once 'include/classlib_userconfig.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentList');


{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in', 'Tournament.list_tournaments');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.list_tournaments');
   $my_id = $player_row['ID'];
   $cfg_tblcols = ConfigTableColumns::load_config( $my_id, CFGCOLS_TOURNAMENTS );
   $is_admin = TournamentUtils::isAdmin();

   $page = "list_tournaments.php?";

   // config for filters
   $scope_filter_array = array( T_('All') => '' );
   foreach( Tournament::getScopeText() as $scope => $text )
      $scope_filter_array[$text] = "T.Scope='$scope'";

   $type_filter_array = array( T_('All') => '' );
   foreach( Tournament::getTypeText() as $type => $text )
      $type_filter_array[$text] = "T.Type='$type'";

   $status_filter_array = array( T_('All') => '' );
   $idx = 1;
   foreach( Tournament::getStatusText() as $status => $text )
   {
      $status_filter_array[$text] = "T.Status='$status'";
      if( $status == TOURNEY_STATUS_ADMIN ) $idx_status_admin = $idx;
      $idx++;
   }

   $owner_filter_array = array(
         T_('All') => '',
         T_('Mine#T_owner') => "T.Owner_ID=$my_id",
      );


   // init search profile
   $search_profile = new SearchProfile( $my_id, PROFTYPE_FILTER_TOURNAMENTS );
   $tsfilter = new SearchFilter( 's' );
   $tfilter = new SearchFilter( '', $search_profile );
   //$search_profile->register_regex_save_args( '' ); // named-filters FC_FNAME
   $ttable = new Table( 'tournament', $page, $cfg_tblcols, '', TABLE_ROWS_NAVI );
   $ttable->set_profile_handler( $search_profile );
   $search_profile->handle_action();

   // static filters
   $tsfilter->add_filter( 1, 'Text', 'TP.uid', true, array(
            FC_FNAME => 'uid',
            FC_QUERYSQL => new QuerySQL(
               SQLP_FIELDS, 'TP.Status AS TP_Status',
               SQLP_FROM,  'INNER JOIN TournamentParticipant AS TP ON TP.tid=T.ID' ) ));
   $tsfilter->add_filter( 2, 'Text', 'TD.uid', true, array(
            FC_FNAME => 'tdir',
            FC_QUERYSQL => new QuerySQL(
               SQLP_FROM,  'INNER JOIN TournamentDirector AS TD ON TD.tid=T.ID' ) ));
   $tsfilter->init();
   $has_uid = (bool)( $tsfilter->get_filter_value(1) );
   $has_tdir = (bool)( $tsfilter->get_filter_value(2) );

   // table filters
   $tfilter->add_filter( 1, 'Numeric', 'T.ID', true);
   $tfilter->add_filter( 2, 'Selection', $scope_filter_array, true );
   $tfilter->add_filter( 3, 'Selection', $type_filter_array, true );
   $tfilter->add_filter( 4, 'Selection', $status_filter_array, true );
   $filter_title =&
      $tfilter->add_filter( 5, 'Text', 'T.Title #OP #VAL', true,
         array( FC_SIZE => 16, FC_SUBSTRING => 1, FC_START_WILD => 3, FC_SQL_TEMPLATE => 1 ));
   $tfilter->add_filter( 6, 'Selection', $owner_filter_array, true );
   $tfilter->add_filter( 8, 'RelativeDate', 'T.StartTime', true,
         array( FC_TIME_UNITS => FRDTU_YMWD|FRDTU_ABS ));
   $tfilter->add_filter(13, 'Numeric', 'TRULE.Size', true,
         array( FC_SIZE => 3 ));
   $tfilter->init();
   $rx_term = implode('|', $filter_title->get_rx_terms() );

   // init table
   $ttable->register_filter( $tfilter );
   $ttable->add_or_del_column();

   // attach external URL-parameters from static filter
   $ttable->add_external_parameters( $tsfilter->get_req_params(), true );

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $ttable->add_tablehead( 1, T_('ID#headert'), 'Button', TABLE_NO_HIDE, 'T.ID-');
   $ttable->add_tablehead( 2, T_('Scope#headert'), 'Enum', 0, 'T.Scope+');
   $ttable->add_tablehead( 3, T_('Type#headert'), 'Enum', 0, 'T.Type+');
   $ttable->add_tablehead( 4, T_('Status#headert'), 'Enum', 0, 'T.Status+');
   $ttable->add_tablehead( 5, T_('Title#headert'), '', TABLE_NO_HIDE, 'Title+');
   if( $has_uid )
      $ttable->add_tablehead(11, T_('Registration Status#headert'), 'Enum', TABLE_NO_HIDE, 'TP_Status+');
   $ttable->add_tablehead(13, T_('Size#headert'), 'Number', 0, 'TRULE.Size-');
   $ttable->add_tablehead(14, T_('Rated#headert'), 'YesNo', TABLE_NO_SORT);
   $ttable->add_tablehead(15, T_('Ruleset#headert'), 'Enum', TABLE_NO_SORT);
   if( $is_admin )
      $ttable->add_tablehead(12, T_('Flags#headert'), '', 0, 'T.Flags-');
   $ttable->add_tablehead(16, T_('Time limit#header'), 'Enum', TABLE_NO_SORT);
   $ttable->add_tablehead(10, T_('Round#headert'), 'NumberC', 0, 'CurrentRound+');
   $ttable->add_tablehead(17, T_('Tournament-Size#headert'), 'Number', TABLE_NO_SORT);
   $ttable->add_tablehead( 6, T_('Owner#headert'), 'User', 0, 'X_OwnerHandle+');
   $ttable->add_tablehead( 7, T_('Last changed#headert'), 'Date', 0, 'T.Lastchanged-');
   $ttable->add_tablehead( 8, T_('Start time#headert'), 'Date', 0, 'StartTime+');
   $ttable->add_tablehead( 9, T_('End time#headert'), 'Date', 0, 'EndTime+');

   $ttable->set_default_sort( 2, 1 ); //on ID

   // build SQL-query (for tournament-table)
   $query_tsfilter = $tsfilter->get_query(GETFILTER_ALL); // clause-parts for static filter
   $tqsql = $ttable->get_query(); // clause-parts for filter
   $tqsql->merge( $query_tsfilter );
   $tqsql->merge( new QuerySQL(
      SQLP_FIELDS,
         'TRULE.Size',
         "IF(TRULE.Rated='N','N','Y') AS X_Rated",
         'TRULE.Ruleset',
         'TRULE.ShapeID', 'TRULE.ShapeSnapshot',
         'TRULE.Maintime', 'TRULE.Byotype', 'TRULE.Byotime', 'TRULE.Byoperiods',
      SQLP_FROM,
         'INNER JOIN TournamentRules AS TRULE ON TRULE.tid=T.ID' ));
   if( $ttable->is_column_displayed(17) )
   {
      $tqsql->merge( new QuerySQL(
         SQLP_FIELDS, 'TPR.MaxParticipants',
         SQLP_FROM, 'INNER JOIN TournamentProperties AS TPR ON TPR.tid=T.ID' ));
   }

   $iterator = new ListIterator( 'Tournaments',
         $tqsql,
         $ttable->current_order_string('ID-'),
         $ttable->current_limit_string() );
   if( !TournamentUtils::isAdmin() && $idx_status_admin != (int)$tfilter->get_filter_value(4) )
      $iterator->addQuerySQLMerge(
         new QuerySQL( SQLP_WHERE, "T.Status<>'".TOURNEY_STATUS_ADMIN."'" ));
   $iterator = Tournament::load_tournaments( $iterator );

   $show_rows = $ttable->compute_show_rows( $iterator->ResultRows );
   $ttable->set_found_rows( mysql_found_rows('Tournament.list_tournaments.found_rows') );


   if( $has_uid )
      $title = T_('My tournaments as participant');
   elseif( $has_tdir )
      $title = T_('My tournaments as tournament director');
   else
      $title = T_('Tournaments');
   start_page($title, true, $logged_in, $player_row,
               button_style($player_row['Button']) );

   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe( $iterator->Query );
   echo "<h3 class=Header>". $title . "</h3>\n";

   while( ($show_rows-- > 0) && list(,$arr_item) = $iterator->getListIterator() )
   {
      list( $tourney, $orow ) = $arr_item;
      $ID = $tourney->ID;
      $row_str = array();

      if( $ttable->Is_Column_Displayed[ 1] )
      {
         $tlink = ( $has_tdir )
            ? $base_path."tournaments/manage_tournament.php?tid=$ID"
            : $base_path."tournaments/view_tournament.php?tid=$ID";
         $row_str[ 1] = button_TD_anchor( $tlink, $ID );
      }
      if( $ttable->Is_Column_Displayed[ 2] )
         $row_str[ 2] = Tournament::getScopeText( $tourney->Scope );
      if( $ttable->Is_Column_Displayed[ 3] )
         $row_str[ 3] = Tournament::getTypeText( $tourney->Type );
      if( $ttable->Is_Column_Displayed[ 4] )
         $row_str[ 4] = Tournament::getStatusText( $tourney->Status );
      if( $ttable->Is_Column_Displayed[ 5] )
      {
         $str = make_html_safe( $tourney->Title, false, $rx_term );
         if( $orow['ShapeID'] > 0 )
            $str .= MED_SPACING . echo_image_shapeinfo($orow['ShapeID'], $orow['Size'], $orow['ShapeSnapshot']);
         $row_str[ 5] = $str;
      }
      if( $ttable->Is_Column_Displayed[ 6] )
         $row_str[ 6] = user_reference( REF_LINK, 1, '', $tourney->Owner_ID, $tourney->Owner_Handle, '' );
      if( $ttable->Is_Column_Displayed[ 7] )
         $row_str[ 7] = ($tourney->Lastchanged > 0) ? date(DATE_FMT2, $tourney->Lastchanged) : '';
      if( $ttable->Is_Column_Displayed[ 8] )
         $row_str[ 8] = ($tourney->StartTime > 0) ? date(DATE_FMT2, $tourney->StartTime) : '';
      if( $ttable->Is_Column_Displayed[ 9] )
         $row_str[ 9] = ($tourney->EndTime > 0) ? date(DATE_FMT2, $tourney->EndTime) : '';
      if( $ttable->Is_Column_Displayed[10] )
         $row_str[10] = $tourney->formatRound(true);
      if( $has_uid && $ttable->Is_Column_Displayed[11] )
      {
         $row_str[11] =
            anchor( $base_path."tournaments/register.php?tid=$ID",
               image( $base_path.'images/info.gif',
                  sprintf( T_('Registration for tournament %s'), $ID ),
                  null, 'class=InTextImage'))
            . '&nbsp;' . TournamentParticipant::getStatusText($orow['TP_Status']);
      }
      if( $is_admin && $ttable->Is_Column_Displayed[12] )
         $row_str[12] = $tourney->formatFlags('', 0, true, 'TWarning');
      if( $ttable->Is_Column_Displayed[13] )
         $row_str[13] = $orow['Size']; // TRULE.Size
      if( $ttable->Is_Column_Displayed[14] )
         $row_str[14] = ($orow['X_Rated'] == 'N') ? T_('No') : T_('Yes');
      if( $ttable->Is_Column_Displayed[15] )
         $row_str[15] = getRulesetText( $orow['Ruleset'] );
      if( $ttable->Is_Column_Displayed[16] )
         $row_str[16] = TimeFormat::echo_time_limit( $orow['Maintime'], $orow['Byotype'],
            $orow['Byotime'], $orow['Byoperiods'], TIMEFMT_SHORT|TIMEFMT_ADDTYPE );
      if( $ttable->Is_Column_Displayed[17] )
         $row_str[17] = sprintf( '%s / %s', $tourney->RegisteredTP,
            ( $orow['MaxParticipants'] > 0 ) ? $orow['MaxParticipants'] : NO_VALUE );

      $ttable->add_row( $row_str );
   }

   // print static-filter & table
   $ttable->echo_table();


   $menu_array = array();
   $menu_array[T_('All tournaments')] = 'tournaments/list_tournaments.php';
   $menu_array[T_('My tournaments')] = "tournaments/list_tournaments.php?uid=$my_id";
   $menu_array[T_('Directoring tournaments')] = "tournaments/list_tournaments.php?tdir=$my_id";
   $create_tourney = TournamentUtils::check_create_tournament();
   if( $create_tourney )
   {
      $menu_array[T_('Create new tournament')] = ($create_tourney == 1)
         ? array( 'url' => 'tournaments/wizard.php', 'class' => 'AdminLink' )
         : 'tournaments/wizard.php';
   }

   end_page(@$menu_array);
}
?>
