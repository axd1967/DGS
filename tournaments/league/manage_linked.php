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
require_once 'include/std_functions.php';
require_once 'include/form_functions.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_gui_helper.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_league_helper.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentManageLinked');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.league.manage_linked');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.league.manage_linked');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'Tournament.league.manage_linked');

   $page = "manage_linked.php";

/* Actual REQUEST calls used
     tid=                           : view linked tournaments
     tml_spawn&tid=                 : spawn next cycle (needs confirmation)
     tml_spawn_confirm&tid=         : spawn next cycle (confirmed)
     cancel&tid=                    : cancel previous action
*/

   $tid = (int) @$_REQUEST['tid'];
   if ( $tid < 0 ) $tid = 0;

   if ( @$_REQUEST['cancel'] ) // cancel action
      jump_to("tournaments/league/manage_linked.php?tid=$tid");

   $tourney = TournamentCache::load_cache_tournament( 'Tournament.manage_linked.find_tournament', $tid );
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   if ( $tourney->Type != TOURNEY_TYPE_LEAGUE )
      error('tournament_edit_not_allowed', "Tournament.manage_linked.check.ttype_only_league($tid)");

   // edit allowed?
   $allow_edit_tourney = TournamentHelper::allow_edit_tournaments($tourney, $my_id);
   if ( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.manage_linked.edit($tid,$my_id)");

   // init
   $tstat_errors = $tstatus->check_edit_status( array( TOURNEY_STATUS_PLAY ) );
   $errors = array_merge( array(), $tstat_errors ); // clone
   if ( !TournamentUtils::isAdmin() && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();


   // ---------- Process actions ------------------------------------------------

   $has_action_error = false;
   if ( count($errors) == 0 )
   {
      $action_errors = array();

      // only checks
      if ( @$_REQUEST['tml_spawn'] )
         TournamentLeagueHelper::spawn_next_cycle( $allow_edit_tourney, $tourney, $action_errors, true );
      // do confirmed actions
      elseif ( @$_REQUEST['tml_spawn_confirm'] ) // spawn next cycle-T
      {
         $next_tid = TournamentLeagueHelper::spawn_next_cycle( $allow_edit_tourney, $tourney, $action_errors, false );
         if ( $next_tid > 0 )
         {
            $sys_msg = urlencode( T_('Next tournament cycle created!') );
            jump_to("tournaments/league/manage_linked.php?tid=$tid".URI_AMP."sysmsg=$sys_msg");
         }
      }

      if ( count($action_errors) )
      {
         $errors = array_merge( $errors, $action_errors );
         $has_action_error = true;
      }
   }

   // ---------- Tournament Form -----------------------------------

   $tform = new Form( 'tmanagelinked', $page, FORM_GET );
   $tform->add_hidden( 'tid', $tid );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   TournamentUtils::show_tournament_flags( $tform, $tourney );
   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament Status'),
         'TEXT', $tourney->getStatusText($tourney->Status) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament Links'),
         'TEXT', TournamentGuiHelper::build_tournament_links($tourney, 'tournaments/manage_tournament.php'), ));

   if ( count($errors) )
   {
      $tform->add_row( array( 'HR' ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString( T_('There are some errors'), $errors ) ));
      $tform->add_empty_row();
   }


   // GUI: Main actions ----------------

   $allow_spawn = ( count($tstat_errors) == 0 && $tourney->Next_tid == 0 );

   $tform->add_row( array( 'HR' ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Actions#tourney'),
         'SUBMITBUTTONX', 'tml_spawn', T_('Spawn Next Cycle#tourney'), ($allow_spawn ? '' : 'disabled=1'),
         'TEXT', sptext( T_('Spawn new tournament for next cycle#tourney'), 1), ));
   if ( @$_REQUEST['tml_spawn'] && $allow_spawn && !$has_action_error )
      TournamentGuiHelper::build_form_confirm( $tform,
         T_('Please confirm spawning next cycle#tourney'), 'tml_spawn', T_('Confirm') );


   // GUI: start page ------------------

   $title = T_('Manage Linked Tournaments');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Manage tournament')] =
      array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}//main
?>
