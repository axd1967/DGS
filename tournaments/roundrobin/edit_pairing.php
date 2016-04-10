<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/error_codes.php';
require_once 'include/filter.php';
require_once 'include/filterlib_country.php';
require_once 'include/form_functions.php';
require_once 'include/table_columns.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_extension.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_pool_classes.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_round_helper.php';
require_once 'tournaments/include/tournament_round_status.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentPairEdit', PAGEFLAG_IMPLICIT_FLUSH ); // flushing for progress-output


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.roundrobin.edit_pairing');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.roundrobin.edit_pairing');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'Tournament.roundrobin.edit_pairing');

   $page = "edit_pairing.php";

/* Actual REQUEST calls used:
     tid=                        : edit tournament pairing
     t_pair&tid=                 : start all pool T-games for current round
*/

   $tid = (int) @$_REQUEST['tid'];
   if ( $tid < 0 ) $tid = 0;
   $do_pair = (bool)@$_REQUEST['t_pair'];

   $tourney = TournamentCache::load_cache_tournament( 'Tournament.edit_pairing.find_tournament', $tid );
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   if ( !$ttype->need_rounds )
      error('tournament_edit_rounds_not_allowed', "Tournament.edit_pairing.need_rounds($tid)");

   // create/edit allowed?
   $allow_edit_tourney = TournamentHelper::allow_edit_tournaments($tourney, $my_id);
   if ( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.edit_pairing.edit_tournament($tid,$my_id)");

   // load existing T-round
   $round = $tourney->CurrentRound;
   $tround = TournamentCache::load_cache_tournament_round( 'Tournament.edit_pairing', $tid, $round );
   $trstatus = new TournamentRoundStatus( $tourney, $tround );

   // init
   $errors = $tstatus->check_edit_status( TournamentPool::get_edit_tournament_status() );
   $errors = array_merge( $errors, $trstatus->check_edit_round_status( TROUND_STATUS_PAIR ) );
   if ( !TournamentUtils::isAdmin() && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();

   // parse URL-args
   $input_errors = array();
   $create_pools = -1;
   if ( @$_REQUEST['pall'] )
      $create_pools = 0; // create all pools
   else
   {
      $create_pools = array();
      for ( $pool=1; $pool <= $tround->Pools; $pool++ )
         if ( @$_REQUEST["p$pool"] )
            $create_pools[] = $pool;
   }
   if ( $do_pair && ($create_pools < 0 || count($create_pools) == 0) )
      $errors[] = T_('No pool selected to start tournament games.') . "<br>\n";

   // check for pairing-"lock" (stored in T-extension)
   $lock_errors = array();
   $disable_create = '';
   $t_ext = TournamentExtension::load_tournament_extension( $tid, TE_PROP_TROUND_START_TGAMES );
   $lock_errtext = str_replace("\n", "<br>\n",
      T_("If a (repeated) error occured during creation of the tournament-games or it was stopped manually,\n" .
         "the tournament could be broken and must be fixed by a tournament-admin.") );
   if ( !is_null($t_ext) )
   {
      $lock_expire_time = $t_ext->Lastchanged + TRR_START_TGAMES_LOCK_HOURS * SECS_PER_HOUR;
      $lock_expired = ( $NOW >= $lock_expire_time );
      if ( !$lock_expire_time ) // not expired yet?
         $disable_create = 'disabled=1';

      $lock_errors[] = T_('A special lock is set when starting games to prevent problems with multiple start operations.')
         . "<br><br>\n"
         . sprintf( T_('Creating tournament games is in work already (started at [%s] by %s).'),
               span('WarnMsg', date(DATE_FMT, $t_ext->DateValue)), span('WarnMsg', $t_ext->ChangedBy) )
         . "<br>\n"
         . sprintf( T_('The lock expires after some time, so please retry first at [%s] before you ask for assistance.#tourney'),
               span('WarnMsg', date(DATE_FMT, $lock_expire_time)) )
         . "<br><br>\n" . $lock_errtext;
   }
   else
      $lock_expired = false;

   if ( $do_pair )
      $arr_pool_summary = $pool_errors = null;
   else
      list( $pool_errors, $arr_pool_summary ) = TournamentPool::check_pools( $tround, /*only-sum*/true );


   // --------------- Tournament-Pairing EDIT form --------------------

   $tform = new Form( 'tournamentPairing', $page, FORM_GET, false );
   $tform->set_config( FEC_EXTERNAL_FORM, true );
   $tform->add_hidden( 'tid', $tid );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament Round'),
         'TEXT',        $tourney->formatRound(), ));
   TournamentUtils::show_tournament_flags( $tform, $tourney );
   $tform->add_row( array(
         'DESCRIPTION', T_('Round Status#tourney'),
         'TEXT',        TournamentRound::getStatusText($tround->Status), ));

   $has_errors = count($errors);
   if ( $has_errors || count($lock_errors) )
   {
      if ( count($lock_errors) )
         $errors = array_merge( $errors, $lock_errors );

      $tform->add_row( array( 'HR' ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $tform->add_empty_row();
   }

   $tform->add_row( array( 'HR' ));

   // --------------- Start Page ------------------------------------

   $title = T_('Tournament Pairing Editor');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   echo $tform->print_start_default(), $tform->get_form_string();

   if ( !is_null($arr_pool_summary) )
   {
      $pool_sum = new PoolSummary( $page, $arr_pool_summary, $tform );
      $pstable = $pool_sum->make_table_pool_summary();
      list( $count_pools, $count_users, $count_games, $count_started_games ) = $pool_sum->get_counts();

      section('poolSummary', T_('Pool Summary'));
      echo sprintf( T_('Tournament Round #%s has %s pools with %s users: %s of %s expected games have been started.'),
                    $round, $count_pools, $count_users,
                    MED_SPACING . span('EmphasizeWarn', $count_started_games), span('EmphasizeWarn', $count_games) ),
         "<br><br>\n",
         T_('Select pools for starting tournament-games (use last checkbox for all pools and final check):'),
         "<br><br>\n";
      $pstable->echo_table();

      if ( count(@$pool_errors) )
         echo "<br>\n", span('bold', buildErrorListString(T_('There are some errors'), $pool_errors) ), "<br>\n";
   }

   if ( !$do_pair )
   {
      echo "<br>\n",
         $tform->print_insert_submit_buttonx( 't_pair', T_('Create Tournament Games'), $disable_create ),
         MED_SPACING, T_('Start games for selected pools in current round#tourney');
   }

   echo $tform->print_end();

   $notes = array(
         T_('Creating all games may take a while. Please be patient!'),
         T_('Once started, the creation process must not be stopped. Progress of the creation will be shown.'),
         T_('The games for all pairings will start immediately and can neither be changed nor removed.'),
         T_('As final step you must start the games (once) with the "All"-selection to let the system switch the round-status.'),
      );
   echo_notes( 'tournamentPairingNotes', T_('Pairing notes#tourney'), $notes );


   // --------------- Start Games ---------------

   // keep at page-bottom as starting T-games flushes out progress-lines
   if ( $do_pair && !$has_errors && (count($lock_errors) == 0 || $lock_expired ) )
   {
      echo span('bold', T_('Starting tournament games ...')), "<br><br>\n";
      ta_begin();
      {//HOT-section to start all T-games for T-round
         $result = TournamentRoundHelper::start_tournament_round_games( $allow_edit_tourney,
            $tourney, $tround, $create_pools );
      }
      ta_end();

      echo "<br>\n";
      if ( is_string($result) )
      {
         $has_errors = true;
         echo span('CritError', T_('A critical error has occured#tourney'), '%s:') . "<br>\n" . $result,
            "<br><br>\n",
            span('ErrorMsg', $lock_errtext);
      }
      elseif ( is_array($result) )
      {
         list( $count_games, $expected_games, $switched_status ) = $result;
         if ( $count_games == $expected_games )
         {
            echo span('TInfo bold', sprintf( T_('All %s games for selected pools have been started.'), $count_games )),
               "<br>\n";

            if ( $switched_status )
               echo span('TInfo bold', sprintf( T_('Tournament-Round Status has been changed to [%s].'),
                  TournamentRound::getStatusText(TROUND_STATUS_PLAY) ));
         }
         else
         {
            echo span('ErrorMsg bold', T_('There are some errors'), '%s:'),
               "<br>\n",
               span('ErrorMsg',
                  sprintf( T_('Expected %s games for selected pools, but only %s games has been started.#tourney'),
                           $expected_games, $count_games )
                  . "<br>\n"
                  . T_('Try again to start the remaining games or contact a tournament-admin for support.') );
         }
      }
   }//do-pair


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('View Pools')] = "tournaments/roundrobin/view_pools.php?tid=$tid";
   $menu_array[T_('Edit game pairing')] =
      array( 'url' => "tournaments/roundrobin/edit_pairing.php?tid=$tid", 'class' => 'TAdmin' );
   $menu_array[T_('Manage tournament')] =
      array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}
?>
