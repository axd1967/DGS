<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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

chdir('../..');
require_once 'include/std_classes.php';
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/filter.php';
require_once 'include/filterlib_country.php';
require_once 'include/form_functions.php';
require_once 'include/table_columns.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_pool_classes.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentPoolEdit');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_pools');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $page = "edit_pools.php";

/* Actual REQUEST calls used:
     tid=&round=                       : edit tournament pools
     t_unassigned&tid=&round=          : show unassigned users (+ selected pools)
     t_showpools&tid=&round=           : show selected pools only
     t_detach&tid=&round=&mark_$uid..  : detach marked users from assigned pools
     t_assign&tid=&round=&newpool=&mark_$uid..  : assign marked users to new-pool
     t_assign&tid=&round=&uap_$uid..   : assign (unassigned) users to individual new-pools
     t_reassign&tid=&round=&rap_$uid.. : re-assign (assigned) users to individual new-pools
     t_check&tid=&round=               : check pools-integrity and show summary
*/

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;
   $round = (int) @$_REQUEST['round'];
   if( $round < 0 ) $round = 0;
   $show_unassigned = ( @$_REQUEST['t_unassigned'] || @$_REQUEST['t_assign'] );
   $show_pools = ( @$_REQUEST['t_showpools'] || $show_unassigned
      || @$_REQUEST['t_detach'] || @$_REQUEST['t_reassign'] );

   $tourney = Tournament::load_tournament( $tid ); // existing tournament ?
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.edit_pools.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   if( !$ttype->need_rounds )
      error('tournament_edit_rounds_not_allowed', "Tournament.edit_pools.need_rounds($tid)");

   // create/edit allowed?
   if( !$tourney->allow_edit_tournaments($my_id) )
      error('tournament_edit_not_allowed', "Tournament.edit_pools.edit_tournament($tid,$my_id)");

   // load existing T-round
   if( $round < 1 )
      $round = $tourney->CurrentRound;
   $tround = TournamentRound::load_tournament_round( $tid, $round );
   if( is_null($tround) )
      error('bad_tournament', "Tournament.edit_pools.find_tournament_round($tid,$round,$my_id)");

   $tprops = TournamentProperties::load_tournament_properties( $tid );
   if( is_null($tprops) )
      error('bad_tournament', "Tournament.edit_pools.find_tournament_props($tid,$my_id)");
   $needs_trating = ( $tprops->RatingUseMode != TPROP_RUMODE_CURR_FIX );
   $load_opts_tpool = TPOOL_LOADOPT_USER | TPOOL_LOADOPT_REGTIME | ( $needs_trating ? TPOOL_LOADOPT_TRATING : 0 );

   // init
   $errors = $tstatus->check_edit_status( TournamentPool::get_edit_tournament_status() );
   if( !TournamentUtils::isAdmin() && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();
   if( $tround->PoolSize == 0 || $tround->Pools == 0 )
      $errors[] = T_('Pool parameters must be defined first before you can edit pools assigning users!');

   // check select-pools
   $pool_range_str = TournamentUtils::build_range_text( 1, $tround->Pools );
   $arr_selpool = array();
   foreach( range(1,3) as $idx )
   {
      $pool = trim( get_request_arg('selpool'.$idx) );
      if( is_numeric($pool) && $pool >= 1 && $pool <= $tround->Pools )
         $arr_selpool[] = $pool;
      elseif( (string)$pool != '' )
         $errors[] = sprintf( T_('Pool selection #%s with value [%s] is invalid, must be in range %s'),
                              $idx, $pool, $pool_range_str );
   }

   // perform edit-actions: detach, assign-pool
   if( @$_REQUEST['t_detach'] )
   {
      $arr_marked_uid = get_marked_users();
      if( count($arr_marked_uid) )
         TournamentPool::assign_pool( $tround, 0, $arr_marked_uid );
   }
   if( @$_REQUEST['t_assign'] || @$_REQUEST['t_reassign'] )
   {
      // bulk-updates for users with invidually assigned new pool
      if( $show_pools && !$show_unassigned )
         $arr_assign_uid = get_assigned_user_pools( $errors, 'rap_' ); // re-assign pool-users
      else
         $arr_assign_uid = get_assigned_user_pools( $errors, 'uap_' ); // assign detached users
      if( count($arr_assign_uid) )
      {
         foreach( $arr_assign_uid as $pool => $arr_uids )
            TournamentPool::assign_pool( $tround, $pool, $arr_uids );
      }
   }
   if( @$_REQUEST['t_assign'] )
   {
      // bulk-update for marked users to one new pool
      $newpool = trim(get_request_arg('newpool'));
      if( (string)$newpool != '' )
      {
         if( !is_numeric($newpool) || $newpool < 1 || $newpool > $tround->Pools )
            $errors[] = sprintf( T_('Pool [%s] for assigning is invalid, must be in range %s'), $newpool, $pool_range_str );
         else
         {
            $arr_marked_uid = get_marked_users();
            if( count($arr_marked_uid) )
               TournamentPool::assign_pool( $tround, $newpool, $arr_marked_uid );
         }
      }
   }

   $arr_pool_summary = null;
   if( @$_REQUEST['t_check'] )
   {
      $errors = array_merge( $errors, TournamentPool::check_pools($tround, $arr_pool_summary) );
   }


   // External-Form
   $tform = new Form( 'tournament', $page, FORM_GET );
   $tform->set_config( FEC_EXTERNAL_FORM, true );
   $uafilter = new SearchFilter();
   $uatable = new Table( 'poolUnassigned', $page, null, 'ua', TABLE_ROW_NUM|TABLE_ROWS_NAVI );
   if( $uafilter->was_filter_submit_action() || $uatable->was_table_submit_action() )
      $show_unassigned = $show_pools = true;


   // load selected pool-data
   $poolTables = null;
   if( $show_pools && count($arr_selpool) && count($errors) == 0 )
   {
      $tpool_iterator = new ListIterator( 'Tournament.pool_view.load_pools' );
      $tpool_iterator->addQuerySQLMerge(
         new QuerySQL( SQLP_WHERE, 'TPOOL.Pool IN (' . implode(',', $arr_selpool) . ')' ));
      $tpool_iterator = TournamentPool::load_tournament_pools(
         $tpool_iterator, $tid, $round, 0, $load_opts_tpool );

      $poolTables = new PoolTables( $tround->Pools );
      $poolTables->fill_pools( $tpool_iterator );
   }


   // load unassigned pool-users
   if( $show_unassigned )
   {
      $uatable->set_extend_table_form_function( 'pools_unassigned_extend_table_form' ); //defined below
      $page_vars = make_pool_unassigned_table( $tid, $uatable, $uafilter );
      load_and_fill_pool_unassigned( $tid, $round, $uatable );
   }


   // --------------- Tournament-Pools EDIT form --------------------

   $tform->set_layout( FLAYOUT_GLOBAL, '1,2' );
   if( $show_unassigned )
   {
      $uatable->set_externalform( $tform );
      $tform->attach_table( $page_vars ); // for page-vars as hiddens in form
   }

   $tform->set_area(1);
   $tform->add_hidden( 'tid', $tid );
   $tform->add_hidden( 'round', $round );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament Round#tround'),
         'TEXT',        $tourney->formatRound(), ));
   TournamentUtils::show_tournament_flags( $tform, $tourney );
   $tform->add_row( array(
         'DESCRIPTION', T_('Round Status#tround'),
         'TEXT',        TournamentRound::getStatusText($tround->Status), ));

   if( count($errors) )
   {
      $tform->add_row( array( 'HR' ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString(T_('There are some errors'), $errors) ));
      $tform->add_empty_row();
   }

   $tform->add_row( array( 'HR' ));

   $tform->set_area(2);
   $tform->add_row( array(
         'DESCRIPTION',  T_('Selection'),
         'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 't_unassigned', T_('Show Unassigned'),
         'TEXT', MED_SPACING,
         'TEXTINPUT',   'selpool1', 4, 4, get_request_arg('selpool1'),
         'TEXTINPUT',   'selpool2', 4, 4, get_request_arg('selpool2'),
         'TEXTINPUT',   'selpool3', 4, 4, get_request_arg('selpool3'),
         'SUBMITBUTTON', 't_showpools', T_('Show Pools'),
         'TEXT', $pool_range_str, ));
   $tform->add_row( array(
         'DESCRIPTION',  T_('Check'),
         'SUBMITBUTTON', 't_check', T_('Check Pools'),
         'TEXT', MED_SPACING . '(' . T_('Check pool integrity and show summary') . ')', ));

   if( $show_pools || $show_unassigned )
   {
      $tform->add_empty_row();
      $tform->add_row( array(
            'DESCRIPTION', T_('Edit Actions'),
            'TEXT',        T_('Please note, that selected marks are not saved!'), ));
   }
   if( $show_pools && !$show_unassigned && count($arr_selpool) )
   {
      $tform->add_row( array(
            'TAB', 'CELL', 1, '', // align submit-buttons
            'SUBMITBUTTON', 't_detach', T_('Detach from Pool'),
            'TEXT', MED_SPACING . '(' . T_('To detach mark in selected pools') . ')', ));
      $tform->add_row( array(
            'TAB', 'CELL', 1, '', // align submit-buttons
            'SUBMITBUTTON', 't_reassign', T_('Reassign Pool'),
            'TEXT', MED_SPACING . '(' . T_('To re-assign enter individual new pool') . ')', ));
   }

   // --------------- Start Page ------------------------------------

   $title = T_('Tournament Pools Editor');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   echo $tform->print_start_default(), $tform->get_form_string();

   if( !is_null($arr_pool_summary) )
      echo_pool_summary( $tround, $arr_pool_summary );

   if( $show_pools && !is_null($poolTables) )
   {
      $pv_opts = PVOPT_NO_COLCFG | PVOPT_NO_RESULT | PVOPT_NO_EMPTY;
      if( $show_pools && !$show_unassigned )
         $pv_opts |= PVOPT_EDIT_COL;
      $poolViewer = new PoolViewer( $tid, $page, $poolTables, $pv_opts );
      $poolViewer->setEditCallback( 'pools_edit_col_actions' );
      $poolViewer->init_table();
      foreach( $arr_selpool as $pool )
         $poolViewer->make_pool_table( $pool, PVOPT_EMPTY_SEL );

      section('poolSelected', T_('Selected Pools'));
      $poolViewer->echo_table();
   }

   if( $show_unassigned )
   {
      section('poolUnassigned', T_('Users without pool assignment'));
      $uatable->echo_table();
   }

   echo $tform->print_end();


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('View Pools')] = "tournaments/roundrobin/view_pools.php?tid=$tid";
   $menu_array[T_('Define pools')] =
      array( 'url' => "tournaments/roundrobin/define_pools.php?tid=$tid", 'class' => 'TAdmin' );
   $menu_array[T_('Create pools')] =
      array( 'url' => "tournaments/roundrobin/create_pools.php?tid=$tid", 'class' => 'TAdmin' );
   $menu_array[T_('Edit pools')] =
      array( 'url' => "tournaments/roundrobin/edit_pools.php?tid=$tid", 'class' => 'TAdmin' );
   $menu_array[T_('Manage tournament')] =
      array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page( @$menu_array, 6 );
}


function get_marked_users()
{
   $arr = array();
   foreach( $_REQUEST as $key => $val )
   {
      if( strpos($key, 'mark_') === 0 )
      {
         $uid = (int)substr($key, 5);
         if( !is_numeric($uid) )
            error('assert', "Tournament.edit_pools.get_marked_users($key)");
         $arr[] = $uid;
      }
   }
   return $arr;
}

// return: array( pool => array( uids, ... ), ... ); empty-array on error
function get_assigned_user_pools( &$errors, $prefix )
{
   global $tround, $pool_range_str;

   $arr = array();
   $len = strlen($prefix);
   foreach( $_REQUEST as $key => $pool )
   {
      if( strpos($key, $prefix) !== 0 ) continue;
      $uid = (int)substr($key, $len);
      if( !is_numeric($uid) )
         error('assert', "Tournament.edit_pools.get_assigned_user_pools($key,$prefix)");

      $pool = trim($pool);
      if( (string)$pool == '' ) continue;
      if( !is_numeric($pool) || $pool < 1 || $pool > $tround->Pools )
      {
         $err_fmt = ( $prefix == 'uap_' )
            ? T_('Pool [%s] to assign for unassigned user is invalid, must be in range %s')
            : T_('Pool [%s] to re-assign for pool-user is invalid, must be in range %s');
         $errors[] = sprintf( $err_fmt, $pool, $pool_range_str );
      }
      else
         $arr[$pool][] = $uid;
   }
   return $arr;
}

function make_pool_unassigned_table( $tid, &$uatable, &$uafilter )
{
   global $needs_trating, $show_unassigned;

   // table filters
   $uafilter->add_filter( 1, 'Text', 'TPU.Name', true, array( FC_SIZE => 12 ));
   $uafilter->add_filter( 2, 'Text', 'TPU.Handle', true, array( FC_SIZE => 12 ));
   $uafilter->add_filter( 3, 'Rating', 'TPU.Rating2', true);
   if( $needs_trating )
      $uafilter->add_filter( 4, 'Rating', 'TP_Rating', true);
   $uafilter->add_filter( 5, 'Country', 'TPU.Country', false, array( FC_HIDE => 1 ));
   $uafilter->add_filter( 7, 'RelativeDate', 'TPU.Lastaccess', true);

   $uafilter->init(); // parse current value from _GET

   // init table
   $uatable->register_filter( $uafilter );
   $uatable->add_or_del_column();

   // attach external URL-parameters from and page-vars for table-links
   $page_vars = new RequestParameters( array( 'tid' => $tid ));
   $form_vars = new RequestParameters();
   if( $show_unassigned )
      $form_vars->add_entry( 't_unassigned', 1 );
   foreach( range(1,3) as $key )
   {
      $val = get_request_arg('selpool'.$key);
      if( $val != '' )
         $form_vars->add_entry('selpool'.$key, $val);
   }
   $uatable->add_external_parameters( $form_vars, false ); // add to table-links only
   $uatable->add_external_parameters( $page_vars, true ); // add as hiddens

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $uatable->add_tablehead( 8, T_('Actions#pool_header'), 'Image', TABLE_NO_HIDE );
   $uatable->add_tablehead( 1, T_('Name#header'), 'User', 0, 'Name+' );
   $uatable->add_tablehead( 2, T_('Userid#header'), 'User', TABLE_NO_HIDE, 'Handle+' );
   $uatable->add_tablehead( 3, T_('User Rating#pool_header'), 'Rating', 0, 'TPU.Rating2-' );
   if( $needs_trating )
      $uatable->add_tablehead( 4, T_('Tournament Rating#pool_header'), 'Rating', 0, 'TP_Rating-' );
   $uatable->add_tablehead( 5, T_('Country#pool_header'), 'Image', 0, 'Country+' );
   $uatable->add_tablehead( 6, T_('Register Time#pool_header'), 'Date', 0, 'TP_X_RegisterTime+' );
   $uatable->add_tablehead( 7, T_('Last access#pool_header'), 'Date', 0, 'Lastaccess-');

   $uatable->set_default_sort( 4, 2); //on T-Rating

   return $page_vars;
}//make_pool_unassigned_table

function load_and_fill_pool_unassigned( $tid, $round, &$uatable )
{
   global $needs_trating, $load_opts_tpool, $tform;

   // build SQL-query (mainly for T-Pool-table)
   $tpool0_iterator = new ListIterator( 'Tournament.edit_pools.load_pools_unassigned',
         $uatable->get_query(), // clause-parts for filter
         $uatable->current_order_string(),
         $uatable->current_limit_string() );
   $tpool0_iterator->addQuerySQLMerge( new QuerySQL( SQLP_WHERE, 'TPOOL.Pool=0' ));
   $tpool0_iterator = TournamentPool::load_tournament_pools(
      $tpool0_iterator, $tid, $round, 0, $load_opts_tpool );

   $show_rows = $uatable->compute_show_rows( $tpool0_iterator->ResultRows );
   $uatable->set_found_rows( mysql_found_rows('Tournament.edit_pools.pools_unassigned.found_rows') );

   while( ($show_rows-- > 0) && list(,$arr_item) = $tpool0_iterator->getListIterator() )
   {
      list( $tpool, $orow ) = $arr_item;
      $user = $tpool->User;
      $uid = $tpool->uid;

      $row_arr = array();
      if( $uatable->Is_Column_Displayed[1] )
         $row_arr[1] = user_reference( REF_LINK, 1, '', $uid, $user->Name, '');
      if( $uatable->Is_Column_Displayed[2] )
         $row_arr[2] = user_reference( REF_LINK, 1, '', $uid, $user->Handle, '');
      if( $uatable->Is_Column_Displayed[3] )
         $row_arr[3] = echo_rating( $user->Rating, true, $uid );
      if( $needs_trating && $uatable->Is_Column_Displayed[4] )
         $row_arr[4] = echo_rating( @$user->urow['TP_Rating'], true, $uid );
      if( $uatable->Is_Column_Displayed[5] )
         $row_arr[5] = getCountryFlagImage( $user->Country );
      if( $uatable->Is_Column_Displayed[6] )
         $row_arr[6] = (@$user->urow['TP_X_RegisterTime'] > 0) ? date(DATE_FMT2, $user->urow['TP_X_RegisterTime']) : '';
      if( $uatable->Is_Column_Displayed[7] )
         $row_arr[7] = ($user->Lastaccess > 0) ? date(DATE_FMT2, $user->Lastaccess) : '';
      $row_arr[8] = $tform->print_insert_text_input( "uap_$uid", 4, 4, '' ) . ' '
         . $tform->print_insert_checkbox( "mark_$uid", '1', '', false, false )
         . MED_SPACING;

      $uatable->add_row( $row_arr );
   }
}//load_and_fill_pool_unassigned

// callback-func for edit-column in selected pools
function pools_edit_col_actions( &$poolviewer, $uid )
{
   global $tform;
   return $tform->print_insert_text_input( "rap_$uid", 4, 4, '' ) . ' '
      . $tform->print_insert_checkbox( "mark_$uid", '1', '', false, false )
      . MED_SPACING;
}

// callback-func for unassigned-pool-users adding form-elements below table
function pools_unassigned_extend_table_form( &$table, &$form )
{
   return SMALL_SPACING
      . $form->print_insert_text_input( 'newpool', 4, 4, get_request_arg('newpool') )
      . $form->print_insert_submit_button( 't_assign', T_('Assign to Pool') );
}

function echo_pool_summary( $tround, $arr_pool_sum )
{
   global $page, $base_path;
   $tid = $tround->tid;

   $pstable = new Table( 'TPoolSummary', $page, null, 'ps',
      TABLE_NO_SORT|TABLE_NO_HIDE|TABLE_NO_PAGE|TABLE_NO_SIZE );

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $pstable->add_tablehead( 1, T_('Pool#poolsum_header'), 'Number' );
   $pstable->add_tablehead( 2, T_('Size#poolsum_header'), 'Number' );
   $pstable->add_tablehead( 3, T_('Games#poolsum_header'), 'Number' );
   $pstable->add_tablehead( 4, T_('Pool Errors#poolsum_header'), 'Note' );

   ksort($arr_pool_sum);
   foreach( $arr_pool_sum as $pool => $arr )
   {
      list( $pool_usercount, $errors, $pool_games ) = $arr;
      $cnt_errors = count($errors);
      $row_arr = array(
         1 => $pool,
         2 => $pool_usercount,
         3 => $pool_games,
         4 => ( $cnt_errors ? implode(', ', $errors ) : T_('OK#poolsum') ),
      );
      if( $cnt_errors )
         $row_arr['extra_class'] = 'Violation';
      $pstable->add_row( $row_arr );
   }

   section('poolSummary', T_('Pool Summary'));
   echo
      sprintf( T_('You have chosen the following pool parameters on the %s page:'),
         anchor($base_path."tournaments/roundrobin/define_pools.php?tid=$tid",
         T_('Define Pools')) ),
      "<br>\n",
      sprintf( T_('Pool Count [%s], Pool-Size range %s, Best chosen Pool-Size [%s]'),
         $tround->Pools, TournamentUtils::build_range_text($tround->MinPoolSize, $tround->MaxPoolSize),
         $tround->PoolSize ),
      "<p></p>\n",
      $pstable->make_table();
}//echo_pool_summary

?>
