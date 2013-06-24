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

chdir('../..');
require_once 'include/std_functions.php';
require_once 'include/form_functions.php';
require_once 'include/classlib_user.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_round_helper.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentRoundEditor');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
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
     tre_cancel&tid=&round=         : cancel previous action
     tre_final&tid=&round=          : finalize round
     tre_fillwin&tid=&round=        : fill tournament winners
*/

   $tid = (int) @$_REQUEST['tid'];
   $round = (int) @$_REQUEST['round'];
   if ( $tid < 0 ) $tid = 0;

   if ( @$_REQUEST['tre_cancel'] ) // cancel action
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

   // edit allowed?
   if ( !TournamentHelper::allow_edit_tournaments($tourney, $my_id) )
      error('tournament_edit_not_allowed', "Tournament.edit_rounds.edit($tid,$my_id)");

   $t_limits = $ttype->getTournamentLimits();
   $max_rounds = $t_limits->getMaxLimit(TLIMITS_TRD_MAX_ROUNDS);
   if ( $round < 1 || $round > $max_rounds )
      $round = $tourney->CurrentRound;
   $tround = ( $round > 0 )
      ? TournamentCache::load_cache_tournament_round( 'Tournament.edit_rounds', $tid, $round, /*check*/false )
      : null;

   // init
   $errors = $tstatus->check_edit_status(
      array( TOURNEY_STATUS_NEW, TOURNEY_STATUS_REGISTER, TOURNEY_STATUS_PAIR, TOURNEY_STATUS_PLAY ) );
   if ( !TournamentUtils::isAdmin() && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();
   $authorise_set_tround = !TournamentRound::authorise_set_tround( $tourney->Status );


   // ---------- Process actions ------------------------------------------------

   $has_action_error = false;
   if ( count($errors) == 0 )
   {
      $action_errors = array();

      // only checks
      if ( @$_REQUEST['tre_add'] )
         TournamentRoundHelper::add_new_tournament_round( $tourney, $action_errors, true );
      elseif ( @$_REQUEST['tre_del'] )
         TournamentRoundHelper::remove_tournament_round( $tourney, $tround, $action_errors, true );
      elseif ( @$_REQUEST['tre_set'] )
         TournamentRoundHelper::set_tournament_round( $tourney, $round, $action_errors, true );
      elseif ( @$_REQUEST['tre_final'] )
         TournamentRoundHelper::finalize_tournament_round( $tourney, $round, $action_errors, true ); //TODO TODO finalize T-round (copy TPOOL->TP.NextRnd)
      elseif ( @$_REQUEST['tre_fillwin'] )
         TournamentRoundHelper::fill_tournament_round_winners( $tourney, $round, $action_errors, true ); //TODO TODO fill T-winners (add T-Result from TP.NextRnd > CurrRnd)
      // do confirmed actions
      elseif ( @$_REQUEST['tre_add_confirm'] ) // add new T-round
      {
         $new_tround = TournamentRoundHelper::add_new_tournament_round( $tourney, $action_errors, false );
         if ( !is_null($new_tround) )
         {
            $round = $new_tround->Round;
            $sys_msg = urlencode( T_('Tournament Round added!') );
            jump_to("tournaments/roundrobin/edit_rounds.php?tid=$tid".URI_AMP."round=$round".URI_AMP."sysmsg=$sys_msg");
         }
      }
      elseif ( @$_REQUEST['tre_del_confirm'] && !is_null($tround) ) // remove T-round
      {
         $success = TournamentRoundHelper::remove_tournament_round( $tourney, $tround, $action_errors, false );
         if ( $success )
         {
            $sys_msg = urlencode( sprintf( T_('Tournament Round #%s removed!'), $round ) );
            jump_to("tournaments/roundrobin/edit_rounds.php?tid=$tid".URI_AMP."sysmsg=$sys_msg");
         }
      }
      elseif ( @$_REQUEST['tre_set_confirm'] ) // set current T-round
      {
         $success = TournamentRoundHelper::set_tournament_round( $tourney, $round, $action_errors, false );
         if ( $success )
         {
            $sys_msg = urlencode( sprintf( T_('Tournament Round #%s switched!'), $round ) );
            jump_to("tournaments/roundrobin/edit_rounds.php?tid=$tid".URI_AMP."round=$round".URI_AMP."sysmsg=$sys_msg");
         }
      }
      elseif ( @$_REQUEST['tre_final_confirm'] ) // finalize current T-round
      {
         $success = TournamentRoundHelper::finalize_tournament_round( $tourney, $round, $action_errors, false ); //TODO TODO finalize T-round (copy TPOOL->TP.NextRnd)
         if ( $success )
         {
            $sys_msg = urlencode( sprintf( T_('Tournament Round #%s finalized!'), $round ) );
            jump_to("tournaments/roundrobin/edit_rounds.php?tid=$tid".URI_AMP."round=$round".URI_AMP."sysmsg=$sys_msg");
         }
      }
      elseif ( @$_REQUEST['tre_fillwin_confirm'] ) // fill T-winners
      {
         $success = TournamentRoundHelper::fill_tournament_round_winners( $tourney, $round, $action_errors, false ); //TODO TODO fill T-winners (add T-Result from TP.NextRnd > CurrRnd)
         if ( $success )
         {
            $sys_msg = T_('Tournament winners set!');
            jump_to("tournaments/roundrobin/edit_rounds.php?tid=$tid".URI_AMP."round=$round".URI_AMP."sysmsg=$sys_msg");
         }
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

   if ( $tourney->Rounds < $max_rounds ) // valid to add T-round
   {
      $tform->add_row( array(
            'TAB',
            'SUBMITBUTTON', 'tre_add', T_('Add Round#tourney'), ));
      if ( @$_REQUEST['tre_add'] && !$has_action_error )
         echo_confirm( $tform, T_('Please confirm adding of a new tournament round'),
            'tre_add', T_('Confirm add') );
   }

   if ( $tourney->Rounds > 1 ) // valid to remove T-round
   {
      $tform->add_row( array(
            'TAB',
            'SUBMITBUTTON', 'tre_del', T_('Remove Round#tourney'), ));
      if ( @$_REQUEST['tre_del'] && !$has_action_error )
         echo_confirm( $tform, sprintf( T_('Please confirm deletion of selected tournament round #%s'), $round ),
            'tre_del', T_('Confirm deletion') );
   }

   $tform->add_row( array(
         'TAB',
         'SUBMITBUTTON', 'tre_edit', T_('Edit Round Properties#tourney'), ));

   $tform->add_row( array(
         'TAB',
         'SUBMITBUTTON', 'tre_stat', T_('Change Round Status#tourney'), ));

   $tform->add_empty_row();
   $tform->add_row( array(
         'TAB',
         'SUBMITBUTTON', 'tre_final', T_('Finalize Round#tourney'),
         'TEXT', sptext(T_('Prepare next round / Prepare tournament winners#tourney'), 1), ));
   //TODO TODO add confirm-text w/ extra-info for finalize-round
   if ( @$_REQUEST['tre_final'] && !$has_action_error )
      ;

   if ( $tourney->CurrentRound == $tourney->Rounds )
   {
      $tform->add_row( array(
            'TAB',
            'SUBMITBUTTON', 'tre_fillwin', T_('Fill Tournament Winners'),
            'TEXT', sptext(T_('Set tournament winners'), 1), ));
      //TODO TODO add confirm-text w/ extra-info for filling-T-winners
      if ( @$_REQUEST['tre_fillwin'] && !$has_action_error )
         ;
   }

   if ( $authorise_set_tround && ($tourney->Rounds > 1) ) // valid to set current T-round
   {
      $tform->add_row( array(
            'TAB',
            'SUBMITBUTTON', 'tre_set', T_('Set Current Round#tourney'),
            'TEXT', sptext(T_('Switch to selected round#tourney'), 1), ));
      if ( @$_REQUEST['tre_set'] && !$has_action_error )
         echo_confirm( $tform,
            sprintf( T_('Please confirm setting the current tournament round from #%s to #%s'),
                     $tourney->CurrentRound, $round ),
            'tre_set', T_('Confirm setting#tround') );
   }


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


function echo_confirm( &$tform, $message, $confirm_action, $confirm_text )
{
   $tform->add_empty_row();
   $tform->add_row( array(
         'TAB',
         'TEXT', span('TWarning', $message.':'), ));
   $tform->add_row( array(
         'TAB',
         'SUBMITBUTTON', $confirm_action.'_confirm', $confirm_text,
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tre_view', T_('Cancel') ));
   $tform->add_empty_row();
}//echo_confirm
?>
