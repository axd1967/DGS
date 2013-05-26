<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentList');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.list_tournaments');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.list_tournaments');
   $my_id = $player_row['ID'];
   $cfg_tblcols = ConfigTableColumns::load_config( $my_id, CFGCOLS_TOURNAMENTS );
   $is_admin = TournamentUtils::isAdmin();

   $page = "list_tournaments.php?";

   $show_notes = ( @$_REQUEST['notes'] ) ? 1 : 0;


   // config for filters
   $scope_filter_array = array( T_('All') => '' );
   foreach ( Tournament::getScopeText() as $scope => $text )
      $scope_filter_array[$text] = "T.Scope='$scope'";

   $type_filter_array = array( T_('All') => '' );
   foreach ( Tournament::getTypeText() as $type => $text )
      $type_filter_array[$text] = "T.Type='$type'";

   $status_filter_array = array( T_('All') => '' );
   $idx = 1;
   foreach ( Tournament::getStatusText() as $status => $text )
   {
      $status_filter_array[$text] = "T.Status='$status'";
      if ( $status == TOURNEY_STATUS_ADMIN )
         $idx_status_admin = $idx;
      elseif ( $status == TOURNEY_STATUS_DELETE )
         $idx_status_delete = $idx;
      $idx++;
   }


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

   if ( $show_notes )
   {
      $rp = new RequestParameters( array( 'notes' => $show_notes ) );
      $ttable->add_external_parameters( $rp, true );
   }

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $ttable->add_tablehead( 1, T_('ID#header'), 'Button', TABLE_NO_HIDE, 'T.ID-');
   $ttable->add_tablehead( 2, T_('Scope#T_header'), 'Enum', 0, 'T.Scope+');
   $ttable->add_tablehead( 3, T_('Type#T_header'), 'Enum', 0, 'T.Type+');
   $ttable->add_tablehead( 4, T_('Status#header'), 'Enum', 0, 'T.Status+');
   $ttable->add_tablehead( 5, T_('Title#header'), '', TABLE_NO_HIDE, 'Title+');
   $ttable->add_tablehead(11, T_('Registration Status#T_header'), 'Enum', ($has_uid ? TABLE_NO_HIDE : 0), 'TP_Status+');
   $ttable->add_tablehead(13, T_('Size#header'), 'Number', 0, 'TRULE.Size-');
   $ttable->add_tablehead(14, T_('Rated#header'), 'YesNo', TABLE_NO_SORT);
   $ttable->add_tablehead(15, T_('Ruleset#header'), 'Enum', TABLE_NO_SORT);
   if ( $is_admin )
      $ttable->add_tablehead(12, T_('Flags#header'), '', 0, 'T.Flags-');
   $ttable->add_tablehead(16, T_('Time limit#header'), 'Enum', TABLE_NO_SORT);
   $ttable->add_tablehead(18, T_('Restrictions#header'), '', TABLE_NO_SORT);
   $ttable->add_tablehead(10, T_('Round#header'), 'NumberC', 0, 'CurrentRound+');
   $ttable->add_tablehead(17, T_('Tournament-Size#header'), 'Number', TABLE_NO_SORT);
   $ttable->add_tablehead( 7, T_('Last changed#header'), 'Date', 0, 'T.Lastchanged-');
   $ttable->add_tablehead( 8, T_('Start time#header'), 'Date', 0, 'StartTime+');
   $ttable->add_tablehead( 9, T_('End time#header'), 'Date', 0, 'EndTime+');
   // 6 is freed

   $ttable->set_default_sort( 2, 1 ); //on ID

   // build SQL-query (for tournament-table)
   $query_tsfilter = $tsfilter->get_query(GETFILTER_ALL); // clause-parts for static filter
   $tqsql = $ttable->get_query(); // clause-parts for filter
   $tqsql->merge( $query_tsfilter );
   $tqsql->merge( new QuerySQL(
      SQLP_FIELDS,
         'TRULE.Size',
         "TRULE.Rated AS X_Rated",
         'TRULE.Ruleset',
         'TRULE.ShapeID', 'TRULE.ShapeSnapshot',
         'TRULE.Maintime', 'TRULE.Byotype', 'TRULE.Byotime', 'TRULE.Byoperiods',
      SQLP_FROM,
         'INNER JOIN TournamentRules AS TRULE ON TRULE.tid=T.ID' ));
   if ( $ttable->is_column_displayed(18) )
   {
      $tqsql->merge( new QuerySQL(
         SQLP_FIELDS,
            'TPR.MaxParticipants', 'TPR.RatingUseMode',
            'UNIX_TIMESTAMP(TPR.RegisterEndTime) AS X_RegisterEndTime',
            'TPR.UserRated', 'TPR.UserMinRating', 'TPR.UserMaxRating',
            'TPR.UserMinGamesFinished', 'TPR.UserMinGamesRated',
         SQLP_FROM,
            'INNER JOIN TournamentProperties AS TPR ON TPR.tid=T.ID' ));
   }
   if ( $ttable->is_column_displayed(17) && !$ttable->is_column_displayed(18) )
   {
      $tqsql->merge( new QuerySQL(
         SQLP_FIELDS, 'TPR.MaxParticipants',
         SQLP_FROM,   'INNER JOIN TournamentProperties AS TPR ON TPR.tid=T.ID' ));
   }
   if ( !$has_uid )
   {
      $tqsql->add_part( SQLP_FIELDS, 'TP.Status AS TP_Status' );
      $tqsql->add_part( SQLP_FROM, "LEFT JOIN TournamentParticipant AS TP ON TP.tid=T.ID AND TP.uid=$my_id" );
   }


   $iterator = new ListIterator( 'Tournaments',
         $tqsql,
         $ttable->current_order_string('ID-'),
         $ttable->current_limit_string() );
   if ( !TournamentUtils::isAdmin() ) // filter away ADM/DEL-status for non-admins
   {
      $f_status = (int)$tfilter->get_filter_value(4);
      $arr_f_stat = array(); // show ADM|DEL if selected in filter
      if ( $f_status != $idx_status_admin )
         $arr_f_stat[] = TOURNEY_STATUS_ADMIN;
      if ( $f_status != $idx_status_delete )
         $arr_f_stat[] = TOURNEY_STATUS_DELETE;
      if ( count($arr_f_stat) )
         $iterator->addQuerySQLMerge(
            new QuerySQL( SQLP_WHERE, "NOT T.Status IN ('" . implode("','", $arr_f_stat) . "')" ));
   }
   $iterator = Tournament::load_tournaments( $iterator );

   $show_rows = $ttable->compute_show_rows( $iterator->getResultRows() );
   $ttable->set_found_rows( mysql_found_rows('Tournament.list_tournaments.found_rows') );

   $maxGamesCheck = new MaxGamesCheck();


   if ( $has_uid )
      $title = T_('My tournaments as participant');
   elseif ( $has_tdir )
      $title = T_('My tournaments as tournament director');
   else
      $title = T_('Tournaments');
   start_page($title, true, $logged_in, $player_row,
               button_style($player_row['Button']) );

   echo "<h3 class=Header>". $title . "</h3>\n";

   while ( ($show_rows-- > 0) && list(,$arr_item) = $iterator->getListIterator() )
   {
      list( $tourney, $orow ) = $arr_item;
      $ID = $tourney->ID;
      $row_str = array();

      if ( $ttable->Is_Column_Displayed[ 1] )
      {
         $tlink = ( $has_tdir )
            ? $base_path."tournaments/manage_tournament.php?tid=$ID"
            : $base_path."tournaments/view_tournament.php?tid=$ID";
         $row_str[ 1] = button_TD_anchor( $tlink, $ID );
      }
      if ( $ttable->Is_Column_Displayed[ 2] )
         $row_str[ 2] = Tournament::getScopeText( $tourney->Scope );
      if ( $ttable->Is_Column_Displayed[ 3] )
         $row_str[ 3] = Tournament::getTypeText( $tourney->Type );
      if ( $ttable->Is_Column_Displayed[ 4] )
         $row_str[ 4] = Tournament::getStatusText( $tourney->Status );
      if ( $ttable->Is_Column_Displayed[ 5] )
      {
         $str = make_html_safe( $tourney->Title, false, $rx_term );
         if ( $orow['ShapeID'] > 0 )
            $str .= MED_SPACING . echo_image_shapeinfo($orow['ShapeID'], $orow['Size'], $orow['ShapeSnapshot']);
         $row_str[ 5] = $str;
      }
      if ( $ttable->Is_Column_Displayed[ 7] )
         $row_str[ 7] = ($tourney->Lastchanged > 0) ? date(DATE_FMT2, $tourney->Lastchanged) : '';
      if ( $ttable->Is_Column_Displayed[ 8] )
         $row_str[ 8] = ($tourney->StartTime > 0) ? date(DATE_FMT2, $tourney->StartTime) : '';
      if ( $ttable->Is_Column_Displayed[ 9] )
         $row_str[ 9] = ($tourney->EndTime > 0) ? date(DATE_FMT2, $tourney->EndTime) : '';
      if ( $ttable->Is_Column_Displayed[10] )
         $row_str[10] = $tourney->formatRound(true);
      if ( $ttable->Is_Column_Displayed[11] )
      {
         $row_str[11] =
            anchor( $base_path."tournaments/register.php?tid=$ID",
               image( $base_path.'images/info.gif',
                  sprintf( T_('Registration for tournament %s'), $ID ), null, 'class=InTextImage'))
            . ' '
            . ( $orow['TP_Status']
                  ? TournamentParticipant::getStatusText($orow['TP_Status'])
                  : NO_VALUE );
      }
      if ( $is_admin && $ttable->Is_Column_Displayed[12] )
         $row_str[12] = $tourney->formatFlags('', 0, true, 'TWarning');
      if ( $ttable->Is_Column_Displayed[13] )
         $row_str[13] = $orow['Size']; // TRULE.Size
      if ( $ttable->Is_Column_Displayed[14] )
         $row_str[14] = ($orow['X_Rated'] == 'N') ? T_('No') : T_('Yes');
      if ( $ttable->Is_Column_Displayed[15] )
         $row_str[15] = getRulesetText( $orow['Ruleset'] );
      if ( $ttable->Is_Column_Displayed[16] )
         $row_str[16] = TimeFormat::echo_time_limit( $orow['Maintime'], $orow['Byotype'],
            $orow['Byotime'], $orow['Byoperiods'], TIMEFMT_SHORT|TIMEFMT_ADDTYPE );
      if ( $ttable->Is_Column_Displayed[17] )
         $row_str[17] = sprintf( '%s / %s', $tourney->RegisteredTP,
            ( $orow['MaxParticipants'] > 0 ) ? $orow['MaxParticipants'] : NO_VALUE );
      if ( $ttable->Is_Column_Displayed[18] )
      {
         list( $restrictions, $class, $title ) = build_restrictions( $tourney, $orow );
         $row_str[18] = ( $class )
            ? array( 'text' => $restrictions, 'attbs' => array( 'class' => $class, 'title' => $title ) )
            : $restrictions;
      }

      $ttable->add_row( $row_str );
   }

   // print static-filter & table
   $ttable->echo_table();


   $notes = array();
   if ( $ttable->is_column_displayed(11) ) // reg-status
   {
      $reg_notes = array( sprintf('<b>%s</b> (%s):', T_('Registration Status#T_header'), T_('Registration Status') ) );
      $arr = array_merge( array( '' => NO_VALUE ), TournamentParticipant::getStatusText() );
      foreach ( $arr as $tpstat => $text )
         $reg_notes[] = $text . ' = ' . TournamentParticipant::getStatusText($tpstat, false, true);
      $notes[] = $reg_notes;
   }
   if ( $ttable->is_column_displayed(18) && !$has_tdir ) // restrictions
   {
      $notes[] = array( sprintf('<b>%s</b> (%s):', T_('Tournament Registration Restrictions'), T_('background colors') ),
            span('TJoinErr',  T_('Error')   . ' = ' . T_('Tournament can not be joined.')),
            span('TJoinWarn', T_('Warning') . ' = ' . T_('Joining tournament only by invitation, but tournament director may deny it because of restrictions.')),
            span('TJoinInv',  T_('Invite')  . ' = ' . T_('Invite-only tournament without restrictions.')),
            T_('Tournament can be joined without restrictions.'),
         );
      $notes[] = array( sprintf('<b>%s</b> (%s):', T_('Tournament Registration Restrictions'), T_('values') ),
            T_('The darkest background color takes priority for all restrictions.#tourney'),
            span('TJoinErr',  'STAT') . ' = ' . T_('Bad tournament status for joining.'),
            span('TJoinErr',  'MXG')  . ' = ' . T_('Max. number of your started games exceed tournament-limits.'),
            span('TJoinErr',  'R')    . ' = ' . T_('Tournament requires a user rating.'),
            span('TJoinWarn', 'RRNG') . ' = ' . T_('User rating does not match tournaments rating range.'),
            span('TJoinWarn', 'MXP')  . ' = ' . T_('Max. number of tournament participants have been reached.'),
            span('TJoinWarn', 'REND') . ' = ' . T_('Tournaments register end time has been reached.'),
            span('TJoinWarn', 'FG')   . ' = ' . T_('You have too few finished games (rated or unrated).'),
            span('TJoinWarn', 'RG')   . ' = ' . T_('You have too few rated finished games.'),
            span('TJoinInv',  'PRIV') . ' = ' . T_('Private tournaments are invite-only.'),
         );
   }
   $notes[] = sprintf( T_("Column <b>%s</b> shows the current registered X and allowed total Y tournament participants: X / Y."),
                       T_('Tournament-Size#header') );

   $baseURL = $page
      . $ttable->current_rows_string(1)
      . $ttable->current_sort_string(1)
      . $ttable->current_filter_string(1)
      . $ttable->current_from_string(1);
   if ( $show_notes )
   {
      echo_notes( 'tournamentnotes', T_('Tournament notes'), $notes );
      echo anchor( $baseURL.'notes=0', T_('Hide tournament notes') ), "\n";
   }
   else
      echo "<br><br>\n", anchor( $baseURL.'notes=1', T_('Show tournament notes') ), "\n";


   $menu_array = array();
   $menu_array[T_('All tournaments')] = 'tournaments/list_tournaments.php';
   $menu_array[T_('My tournaments')] = "tournaments/list_tournaments.php?uid=$my_id";
   $menu_array[T_('Directoring tournaments')] = "tournaments/list_tournaments.php?tdir=$my_id";
   $create_tourney = TournamentUtils::check_create_tournament();
   if ( $create_tourney )
   {
      $menu_array[T_('Create new tournament')] = ($create_tourney == 1)
         ? array( 'url' => 'tournaments/wizard.php', 'class' => 'AdminLink' )
         : 'tournaments/wizard.php';
   }

   end_page(@$menu_array);
}


function build_restrictions( $tourney, $row )
{
   global $maxGamesCheck;
   $arr = TournamentHelper::build_tournament_join_restrictions( $tourney, $maxGamesCheck, $row );

   $out = array();
   $types = array(); // find gravest type: E > W > I > ok
   foreach ( $arr as $item )
   {
      $types[$item[0]] = 1;
      $out[] = substr($item, 2);
   }

   if ( @$types['E'] )
   {
      $class = 'TJoinErr';
      $title = T_('Error');
   }
   elseif ( @$types['W'] )
   {
      $class = 'TJoinWarn';
      $title = T_('Warning');
   }
   elseif ( @$types['W'] )
   {
      $class = 'TJoinInv';
      $title = T_('Invite');
   }
   else
      $class = $title = '';

   return array( implode(', ', $out), $class, $title );
}

?>
