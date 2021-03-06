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
require_once 'include/classlib_user.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_gui_helper.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_round_helper.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentRoundEditor');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row, LOGIN_DEFAULT_OPTS_TDIR_OPS );
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.roundrobin.edit_rounds');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.roundrobin.edit_rounds');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'Tournament.roundrobin.edit_rounds');

   $page = "edit_rounds.php";

/* Actual REQUEST calls used
     tid=&[round=]                  : edit-rounds for tournament
     tre_view&tid=&round            : view selected round
     tre_add&tid=                   : add new round (needs confirmation)
     tre_add_confirm&tid=           : add new round (confirmed)
     tre_del&tid=&round=            : delete selected round (needs confirmation)
     tre_del_confirm&tid=&round=    : delete selected round (confirmed)
     tre_edit&tid=&round=           : edit selected round-data (forward to separate edit-single-round page)
     tre_set&tid=&round=            : sets selected round as the current round (need confirmation)
     tre_set_confirm&tid=&round=    : sets selected round as the current round (confirmed)
     tre_stat&tid=&round=           : changes round status (forward to separate edit-round-status page)
     tre_next&tid=                  : start next round (needs confirmation)
     tre_next_confirm&tid=          : start next round (confirmed)
     cancel&tid=&round=             : cancel previous action
*/

   $tid = (int) @$_REQUEST['tid'];
   $round = (int) @$_REQUEST['round'];
   if ( $tid < 0 ) $tid = 0;

   if ( @$_REQUEST['cancel'] ) // cancel action
      jump_to("tournaments/roundrobin/edit_rounds.php?tid=$tid".URI_AMP."round=$round");
   elseif ( @$_REQUEST['tre_edit'] ) // edit-data
      jump_to("tournaments/roundrobin/edit_round_props.php?tid=$tid".URI_AMP."round=$round");
   elseif ( @$_REQUEST['tre_stat'] ) // edit-status
      jump_to("tournaments/roundrobin/edit_round_status.php?tid=$tid".URI_AMP."round=$round");

   $tourney = TournamentCache::load_cache_tournament( 'Tournament.edit_rounds.find_tournament', $tid );
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   if ( !$ttype->need_rounds )
      error('tournament_edit_rounds_not_allowed', "Tournament.edit_rounds.need_rounds($tid)");
   if ( $tourney->Type != TOURNEY_TYPE_ROUND_ROBIN )
      error('tournament_edit_rounds_not_allowed', "Tournament.edit_rounds.check.ttype_only_rr($tid)");

   // edit allowed?
   $allow_edit_tourney = TournamentHelper::allow_edit_tournaments($tourney, $my_id);
   if ( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.edit_rounds.edit($tid,$my_id)");

   $max_rounds = max( $ttype->getMaxRounds(), $tourney->Rounds ); // allow higher rounds than limit, if T-adm started them
   if ( $round < 1 || $round > $max_rounds )
      $round = $tourney->CurrentRound;
   $tround = ( $round > 0 )
      ? TournamentCache::load_cache_tournament_round( 'Tournament.edit_rounds', $tid, $round, /*check*/false )
      : null;

   // init
   $is_admin = TournamentUtils::isAdmin();
   $errors = $tstatus->check_edit_status(
      array( TOURNEY_STATUS_NEW, TOURNEY_STATUS_REGISTER, TOURNEY_STATUS_PAIR, TOURNEY_STATUS_PLAY ) );
   if ( !$is_admin && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();
   $authorise_set_tround = !TournamentRound::authorise_set_tround( $tourney->Status );


   // ---------- Process actions ------------------------------------------------

   $has_action_error = false;
   if ( count($errors) == 0 )
   {
      $action_errors = array();

      // only checks
      if ( @$_REQUEST['tre_add'] )
         TournamentRoundHelper::add_new_tournament_round( $allow_edit_tourney, $tourney, $tround, $action_errors, true );
      elseif ( @$_REQUEST['tre_del'] )
         TournamentRoundHelper::remove_tournament_round( $allow_edit_tourney, $tourney, $tround, $action_errors, true );
      elseif ( @$_REQUEST['tre_set'] )
         TournamentRoundHelper::set_tournament_round( $allow_edit_tourney, $tourney, $round, $action_errors, true );
      elseif ( @$_REQUEST['tre_next'] )
         TournamentRoundHelper::start_next_tournament_round( $allow_edit_tourney, $tourney, $action_errors, true );
      // do confirmed actions
      elseif ( @$_REQUEST['tre_add_confirm'] && $is_admin ) // add new T-round
      {
         $new_tround = TournamentRoundHelper::add_new_tournament_round( $allow_edit_tourney, $tourney, $tround, $action_errors, false );
         if ( !is_null($new_tround) )
         {
            $round = $new_tround->Round;
            $sys_msg = urlencode( T_('Tournament Round added!') );
            jump_to("tournaments/roundrobin/edit_rounds.php?tid=$tid".URI_AMP."round=$round".URI_AMP."sysmsg=$sys_msg");
         }
      }
      elseif ( @$_REQUEST['tre_del_confirm'] && !is_null($tround) && $is_admin ) // remove T-round
      {
         $success = TournamentRoundHelper::remove_tournament_round( $allow_edit_tourney, $tourney, $tround, $action_errors, false );
         if ( $success )
         {
            $sys_msg = urlencode( sprintf( T_('Tournament Round #%s removed!'), $round ) );
            jump_to("tournaments/roundrobin/edit_rounds.php?tid=$tid".URI_AMP."sysmsg=$sys_msg");
         }
      }
      elseif ( @$_REQUEST['tre_set_confirm'] && $is_admin ) // set current T-round
      {
         $success = TournamentRoundHelper::set_tournament_round( $allow_edit_tourney, $tourney, $round, $action_errors, false );
         if ( $success )
         {
            $sys_msg = urlencode( sprintf( T_('Tournament Round #%s switched!'), $round ) );
            jump_to("tournaments/roundrobin/edit_rounds.php?tid=$tid".URI_AMP."round=$round".URI_AMP."sysmsg=$sys_msg");
         }
      }
      elseif ( @$_REQUEST['tre_next_confirm'] ) // start next T-round
      {
         $success = TournamentRoundHelper::start_next_tournament_round( $allow_edit_tourney, $tourney, $action_errors, false );
         if ( ($success & 7) == 7 )
         {
            $sys_msg = urlencode( sprintf( T_('Next Tournament Round #%s started!'), $round + 1 ) );
            jump_to("tournaments/roundrobin/edit_rounds.php?tid=$tid".URI_AMP."sysmsg=$sys_msg");
         }
         else
            $action_errors[] = sprintf( T_('Starting next tournament round has failed with error-value [%s].'), $success );
      }

      if ( count($action_errors) )
      {
         $errors = array_merge( $errors, $action_errors );
         $has_action_error = true;
      }
   }

   // ---------- Tournament Form -----------------------------------

   $tform = new Form( 'troundeditor', $page, FORM_GET );
   $tform->add_hidden( 'tid', $tid );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   TournamentUtils::show_tournament_flags( $tform, $tourney );
   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament Status'),
         'TEXT', $tourney->getStatusText($tourney->Status) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Rounds#tourney'),
         'TEXT', $tourney->formatRound(), ));

   if ( count($errors) )
   {
      $tform->add_row( array( 'HR' ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString( T_('There are some errors'), $errors ) ));
      $tform->add_empty_row();
   }


   // GUI: Edit rounds -----------------

   $arr_rounds = array_value_to_key_and_value( range(1, $tourney->Rounds) );

   $tform->add_row( array( 'HR' ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Select Round#tourney'),
         'SELECTBOX',   'round', 1, $arr_rounds, $round, false, ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Actions#tourney'),
         'SUBMITBUTTON', 'tre_view', T_('View Round#tourney'), ));

   $tform->add_row( array(
         'TAB',
         'SUBMITBUTTON', 'tre_edit', T_('Edit Round Properties#tourney'), ));

   $tform->add_row( array(
         'TAB',
         'SUBMITBUTTON', 'tre_stat', T_('Change Round Status#tourney'), ));

   if ( $is_admin || $tourney->Rounds < $max_rounds ) // valid to start next T-round
   {
      $tform->add_empty_row();
      $tform->add_row( array(
            'TAB',
            'SUBMITBUTTON', 'tre_next', T_('Start Next Round#tourney'),
            'TEXT', sptext( T_('Prepare next round, Switch tournament-status, Add & set new round#tourney'), 1), ));
      if ( @$_REQUEST['tre_next'] && !$has_action_error )
         TournamentGuiHelper::build_form_confirm( $tform,
            T_('Please confirm starting next tournament round'), 'tre_next', T_('Confirm') );
   }

   if ( $is_admin )
   {
      $tform->add_empty_row();
      if ( $tourney->Rounds < $max_rounds ) // valid to add T-round
      {
         $tform->add_row( array(
               'TAB',
               'SUBMITBUTTON', 'tre_add', T_('Add Round#tourney'), ));
         if ( @$_REQUEST['tre_add'] && !$has_action_error )
            TournamentGuiHelper::build_form_confirm( $tform,
               T_('Please confirm adding of a new tournament round'), 'tre_add', T_('Confirm add') );
      }

      if ( $tourney->Rounds > 1 ) // valid to remove T-round
      {
         $tform->add_row( array(
               'TAB',
               'SUBMITBUTTON', 'tre_del', T_('Remove Round#tourney'), ));
         if ( @$_REQUEST['tre_del'] && !$has_action_error )
            TournamentGuiHelper::build_form_confirm( $tform,
               sprintf( T_('Please confirm deletion of selected tournament round #%s'), $round ),
               'tre_del', T_('Confirm deletion') );
      }

      if ( $authorise_set_tround && ($tourney->Rounds > 1) ) // valid to set current T-round
      {
         $tform->add_row( array(
               'TAB',
               'SUBMITBUTTON', 'tre_set', T_('Set Current Round#tourney'),
               'TEXT', sptext(T_('Switch to selected round#tourney'), 1), ));
         if ( @$_REQUEST['tre_set'] && !$has_action_error )
         {
            TournamentGuiHelper::build_form_confirm( $tform,
               sprintf( T_('Please confirm setting the current tournament round from #%s to #%s'),
                        $tourney->CurrentRound, $round ),
               'tre_set', T_('Confirm setting#tround') );
         }
      }
   }//T-admin


   // GUI: show round info -------------

   if ( !is_null($tround) )
   {
      $tform->add_row( array( 'HR' ));
      $tform->add_row( array( 'HEADER', T_('Tournament Round Info') ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Tournament Round'),
            'TEXT', $tround->Round, ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Last changed'),
            'TEXT',        TournamentUtils::buildLastchangedBy($tround->Lastchanged, $tround->ChangedBy) ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Round Status#tourney'),
            'TEXT', TournamentRound::getStatusText($tround->Status), ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Min. Pool Size'),
            'TEXT', $tround->MinPoolSize, ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Max. Pool Size'),
            'TEXT', $tround->MaxPoolSize, ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Max. Pool Count'),
            'TEXT', ( $tround->MaxPoolCount > 0 ? $tround->MaxPoolCount : NO_VALUE ), ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Pool Winner Ranks'),
            'TEXT', $tround->PoolWinnerRanks, ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Pool Names Format'),
            'TEXT', $tround->PoolNamesFormat, ));
   }


   $title = T_('Tournament Rounds Editor');
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
