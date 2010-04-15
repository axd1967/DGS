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
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'include/table_columns.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_round_status.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentPoolCreate');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.create_pools');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $page = "create_pools.php";

/* Actual REQUEST calls used:
     tid=                                   : create/manage tournament pools
     t_seed&tid=&seed_order=&slice_mode=    : seed pools
     t_delete&tid=                          : delete all pools (needs confirm)
     t_del_confirm&tid=                     : delete all pools (confirmed)
     t_add_miss&tid=                        : add missing registered users to default pool #0
     t_cancel&tid=                          : cancel pool-deletion
*/

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;

   if( @$_REQUEST['t_cancel'] ) // cancel delete
      jump_to("tournaments/roundrobin/create_pools.php?tid=$tid");

   $tourney = Tournament::load_tournament( $tid ); // existing tournament ?
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.create_pools.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   if( !$ttype->need_rounds )
      error('tournament_edit_rounds_not_allowed', "Tournament.create_pools.need_rounds($tid)");

   // create/edit allowed?
   if( !$tourney->allow_edit_tournaments($my_id) )
      error('tournament_edit_not_allowed', "Tournament.create_pools.edit_tournament($tid,$my_id)");

   // load existing T-round
   $round = $tourney->CurrentRound;
   $tround = TournamentRound::load_tournament_round( $tid, $round );
   if( is_null($tround) )
      error('bad_tournament', "Tournament.create_pools.find_tournament_round($tid,$round,$my_id)");
   $trstatus = new TournamentRoundStatus( $tourney, $tround );

   $tprops = TournamentProperties::load_tournament_properties($tid);
   if( is_null($tprops) )
      error('bad_tournament', "Tournament.create_pools.find_tprops($tid,$round,$my_id)");

   list( $count_poolrows, $count_pools ) = TournamentPool::count_tournament_pool( $tid, $round );


   // init
   $errors = $tstatus->check_edit_status( TournamentPool::get_edit_tournament_status() );
   $errors = array_merge( $errors, $trstatus->check_edit_status( TROUND_STATUS_POOL ) );
   if( !TournamentUtils::isAdmin() && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();
   if( $tround->PoolSize == 0 || $tround->Pools == 0 )
      $errors[] = T_('Pool parameters must be defined first before you can manage pools!');
   if( @$_REQUEST['t_del_confirm'] )
   {
      if( $count_poolrows == 0 )
         $errors[] = T_('There are no pools existing for deletion');
   }

   $tp_counts = TournamentParticipant::count_tournament_participants( $tid, TP_STATUS_REGISTER );
   $reg_count = (int)@$tp_counts[TPCOUNT_STATUS_ALL];

   // ---------- Process actions ------------------------------------------------

   if( count($errors) == 0 )
   {
      if( @$_REQUEST['t_seed'] && $count_poolrows == 0 )
      {
         $seed_order = (int)get_request_arg('seed_order');
         $slice_mode = (int)get_request_arg('slice_mode');
         if( TournamentPool::seed_pools( $tid, $tprops, $tround, $seed_order, $slice_mode ) )
         {
            $sys_msg = urlencode( T_('Tournament Pools seeded!#tourney') );
            jump_to("tournaments/roundrobin/create_pools.php?tid=$tid".URI_AMP."sysmsg=$sys_msg");
         }
      }
      elseif( @$_REQUEST['t_del_confirm'] && $count_poolrows > 0 )
      {
         if( TournamentPool::delete_pools($tid, $round) )
         {
            $sys_msg = urlencode( T_('Tournament Pools removed!#tourney') );
            jump_to("tournaments/roundrobin/create_pools.php?tid=$tid".URI_AMP."sysmsg=$sys_msg");
         }
      }
      elseif( @$_REQUEST['t_add_miss'] && $reg_count > $count_poolrows )
      {
         if( TournamentPool::add_missing_registered_users( $tid, $tround ) )
         {
            $sys_msg = urlencode( T_('Tournament Default Pool filled with missing users!#tourney') );
            jump_to("tournaments/roundrobin/create_pools.php?tid=$tid".URI_AMP."sysmsg=$sys_msg");
         }
      }
   }

   // --------------- Tournament-Pools EDIT form --------------------

   $tform = new Form( 'tournament', $page, FORM_GET );
   $tform->add_hidden( 'tid', $tid );

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

   $has_errors = count($errors);
   if( $has_errors )
   {
      $tform->add_row( array( 'HR' ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString(T_('There are some errors'), $errors) ));
      $tform->add_empty_row();
   }

   // Seed Pools -----------------------------

   list( $seed_order_def, $arr_seed_order ) = $tprops->build_seed_order();
   $seed_order_val = get_request_arg('seed_order', $seed_order_def);
   list( $slice_mode_def, $arr_slice_mode ) = TournamentPool::build_slice_mode();
   $slice_mode_val = get_request_arg('slice_mode', $slice_mode_def);

   $tform->add_row( array( 'HR' ));
   $tform->add_row( array( 'HEADER', T_('Prepare Pools') ));

   $disable_submit = ($has_errors) ? 'disabled=1' : '';
   if( $count_poolrows > 0 )
   {
      $tform->add_row( array(
            'CELL', 2, '',
            'TEXT', sprintf( T_('There are %s stored pools with %s users for current tournament round #%s') . ':',
                           $count_pools, $count_poolrows, $round ), ));
      $tform->add_row( array(
            'CELL', 2, '',
            'TEXT', T_('Delete all existing pools') . MED_SPACING,
            'SUBMITBUTTONX', 't_delete', T_('Delete Pools'), $disable_submit ));

      if( @$_REQUEST['t_delete'] && !$has_errors )
      {
         $tform->add_row( array(
               'CELL', 2, '',
               'TEXT', span('TWarning', T_('Please confirm deletion of all pools')) . ':', ));
         $tform->add_row( array(
               'CELL', 2, '',
               'SUBMITBUTTON', 't_del_confirm', T_('Confirm deletion'),
               'TEXT', SMALL_SPACING,
               'SUBMITBUTTON', 't_cancel', T_('Cancel') ));
      }
      $tform->add_empty_row();

      $tform->add_row( array(
            'CELL', 2, '',
            'TEXT', span('TWarning', T_('Seeding pools is only allowed with empty pools!')), ));
   }
   else
   {
      $tform->add_row( array(
            'CELL', 2, '',
            'TEXT', sprintf( T_('Create and seed pools with all %s registered users for tournament round #%s'),
                           $reg_count, $round) . ':', ));

      $tform->add_row( array(
            'CELL', 2, '',
            'TEXT',         T_('Order by') . MED_SPACING,
            'SELECTBOX',    'seed_order', 1, $arr_seed_order, $seed_order_val, false,
            'TEXT',         SMALL_SPACING . T_('Slice by') . MED_SPACING,
            'SELECTBOX',    'slice_mode', 1, $arr_slice_mode, $slice_mode_val, false,
            'SUBMITBUTTONX', 't_seed', T_('Seed Pools'), $disable_submit ));
      $tform->add_row( array(
            'CELL', 2, '',
            'TEXT', T_('This may take a while, so please be patient!'), ));
   }

   if( $reg_count > $count_poolrows )
   {
      $tform->add_empty_row();
      $tform->add_row( array(
            'CELL', 2, '',
            'TEXT', sprintf( T_('There are %s registered users from which %s users are not appearing in the pools.'),
                             $reg_count, $reg_count - $count_poolrows ), ));
      $tform->add_row( array(
            'CELL', 2, '',
            'TEXT', T_('Add missing registered users to default pool (with unassigned users).') . "<br>\n",
            'SUBMITBUTTONX', 't_add_miss', T_('Assign missing users to Default Pool'), $disable_submit ));
   }


   $title = T_('Tournament Pools Manager');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Define pools')] =
      array( 'url' => "tournaments/roundrobin/define_pools.php?tid=$tid", 'class' => 'TAdmin' );
   $menu_array[T_('Create pools')] =
      array( 'url' => "tournaments/roundrobin/create_pools.php?tid=$tid", 'class' => 'TAdmin' );
   $menu_array[T_('Edit pools')] =
      array( 'url' => "tournaments/roundrobin/edit_pools.php?tid=$tid", 'class' => 'TAdmin' );
   $menu_array[T_('Manage tournament')] =
      array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}
?>
