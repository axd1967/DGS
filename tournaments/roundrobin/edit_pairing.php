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
require_once 'tournaments/include/tournament_extension.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_pool_classes.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_round_status.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentPairEdit');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_pairing');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $page = "edit_pairing.php";

/* Actual REQUEST calls used:
     tid=                        : edit tournament pairing
     t_pair&tid=                 : start all pool T-games for current round
*/

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;
   $do_pair = (bool)@$_REQUEST['t_pair'];

   $tourney = Tournament::load_tournament( $tid ); // existing tournament ?
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.edit_pairing.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   if( !$ttype->need_rounds )
      error('tournament_edit_rounds_not_allowed', "Tournament.edit_pairing.need_rounds($tid)");

   // create/edit allowed?
   if( !$tourney->allow_edit_tournaments($my_id) )
      error('tournament_edit_not_allowed', "Tournament.edit_pairing.edit_tournament($tid,$my_id)");

   // load existing T-round
   $round = $tourney->CurrentRound;
   $tround = TournamentRound::load_tournament_round( $tid, $round );
   if( is_null($tround) )
      error('bad_tournament', "Tournament.edit_pairing.find_tournament_round($tid,$round,$my_id)");
   $trstatus = new TournamentRoundStatus( $tourney, $tround );

   // init
   $errors = $tstatus->check_edit_status( TournamentPool::get_edit_tournament_status() );
   $errors = array_merge( $errors, $trstatus->check_edit_status( TROUND_STATUS_PAIR ) );
   if( !TournamentUtils::isAdmin() && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();

   // check for pairing-"lock" (stored in T-extension)
   $t_ext = TournamentExtension::load_tournament_extension( $tid, TE_PROP_TROUND_START_TGAMES );
   if( !is_null($t_ext) )
      $errors[] = sprintf( T_('Creating tournament games is in work already (started at [%s] by %s ).'),
         date(DATEFMT_TOURNAMENT, $t_ext->DateValue), $t_ext->ChangedBy );

   $arr_pool_summary = null;
   if( count($errors) == 0 && !$do_pair )
      list( $errors, $arr_pool_summary ) = TournamentPool::check_pools( $tround, /*only-sum*/true );


   // --------------- Tournament-Pairing EDIT form --------------------

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

   $disable_create = '';
   if( count($errors) )
   {
      $disable_create = 'disabled=1';

      $tform->add_row( array( 'HR' ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString(T_('There are some errors'), $errors) ));
      $tform->add_empty_row();
   }

   $tform->add_row( array( 'HR' ));

   if( !$do_pair )
   {
      $tform->add_row( array(
            'CELL', 2, '', // align submit-buttons
            'SUBMITBUTTONX', 't_pair', T_('Create tournament games'), $disable_create,
            'TEXT', MED_SPACING . '(' . T_('Start games for all pools in current round') . ')', ));
   }

   // --------------- Start Page ------------------------------------

   $title = T_('Tournament Pairing Editor');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();

   if( !is_null($arr_pool_summary) )
   {
      $pool_sum = new PoolSummary( $page, $arr_pool_summary );
      $pstable = $pool_sum->make_table_pool_summary();

      $count_pools = count($arr_pool_summary);
      $count_users = 0;
      $count_games = 0;
      foreach( $arr_pool_summary as $pool => $arr )
      {
         $count_users += $arr[0];
         $count_games += $arr[2];
      }

      section('poolSummary', T_('Pool Summary'));
      echo sprintf( T_('Tournament Round #%s has %s pools with %s users going to play in %s games.'),
                    $round, $count_pools, $count_users, $count_games ), "<p></p>\n";
      $pstable->echo_table();
   }

   if( $do_pair )
   {
      $thelper = new TournamentHelper();
      ta_begin();
      {//HOT-section to start all T-games for T-round
         $count_created_games = $thelper->start_tournament_round_games( $tourney, $tround );
      }
      ta_end();
   }


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('View Pools')] = "tournaments/roundrobin/view_pools.php?tid=$tid";
   $menu_array[T_('Manage tournament')] =
      array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}
?>
