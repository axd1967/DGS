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
     tid=&round=                 : edit tournament pools
     t_unassigned&tid=&round=    : show unassigned users (+ selected pools)
     t_showpools&tid=&round=     : show selected pools only
*/

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;
   $round = (int) @$_REQUEST['round'];
   if( $round < 0 ) $round = 0;
   $show_unassigned = ( @$_REQUEST['t_unassigned'] );
   $show_pools = ( @$_REQUEST['t_showpools'] || $show_unassigned );

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

   $uafilter = new SearchFilter();
   $uatable = new Table( 'poolUnassigned', $page, null, 'ua', TABLE_ROW_NUM|TABLE_ROWS_NAVI );
   if( $uafilter->was_filter_submit_action() || $uatable->was_table_submit_action() )
      $show_unassigned = $show_pools = true;


   // load selected pool-data
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
      $page_vars = make_pool_unassigned_table( $tid, $uatable, $uafilter );
      load_and_fill_pool_unassigned( $tid, $round, $uatable );
   }


   // --------------- Tournament-Pools EDIT form --------------------

   // External-Form
   $tform = new Form( 'tournament', $page, FORM_GET );
   $tform->set_layout( FLAYOUT_GLOBAL, '1,2' );
   $tform->set_config( FEC_EXTERNAL_FORM, true );
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
         'SUBMITBUTTON', 't_unassigned', T_('Show Unassigned'),
         'TEXT', MED_SPACING,
         'TEXTINPUT',   'selpool1', 4, 4, get_request_arg('selpool1'),
         'TEXTINPUT',   'selpool2', 4, 4, get_request_arg('selpool2'),
         'TEXTINPUT',   'selpool3', 4, 4, get_request_arg('selpool3'),
         'SUBMITBUTTON', 't_showpools', T_('Show Pools'),
         'TEXT', $pool_range_str, ));


   // --------------- Start Page ------------------------------------

   $title = T_('Tournament Pools Editor');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   echo $tform->print_start_default(),
      $tform->get_form_string(),
      "<p></p>\n";

   if( $show_pools && !is_null($poolTables) )
   {
      $poolViewer = new PoolViewer( $tid, $page, $poolTables, PVOPT_NO_COLCFG|PVOPT_NO_RESULT|PVOPT_NO_EMPTY );
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
   $menu_array[T_('Create pools')] =
      array( 'url' => "tournaments/roundrobin/create_pools.php?tid=$tid", 'class' => 'TAdmin' );
   $menu_array[T_('Edit pools')] =
      array( 'url' => "tournaments/roundrobin/edit_pools.php?tid=$tid", 'class' => 'TAdmin' );
   $menu_array[T_('Manage tournament')] =
      array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
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
   global $needs_trating, $load_opts_tpool;

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
      $uid = $user->ID;

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

      $uatable->add_row( $row_arr );
   }
}//load_and_fill_pool_unassigned

?>
