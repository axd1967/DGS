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
require_once 'include/form_functions.php';
require_once 'include/classlib_user.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentRoundEditor');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_rounds');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $page = "edit_rounds.php";

/* Actual REQUEST calls used
     tid=&[round=]                  : edit-rounds for tournament
     tre_view&tid=&round            : view selected round
     tre_add&tid=                   : add new round (needs confirmation)
     tre_add_confirm&tid=           : add new round (confirmed)
     tre_del&tid=&round=            : delete selected round (needs confirmation)
     tre_del_confirm&tid=&round=    : delete selected round (confirmed)
     tre_edit&tid=&round=           : edit selected round-data (forward to separate edit-single-round page)
     //TODO tre_set&tid=&round=            : sets selected round as the current round
     //TODO tre_stat&tid=&round=           : changes round status (forward to separate edit-round-status page)
     tre_cancel&tid=&round=         : cancel previous action
*/

   $tid = (int) @$_REQUEST['tid'];
   $round = (int) @$_REQUEST['round'];
   if( $tid < 0 ) $tid = 0;
   //TODO $is_delete = (bool) @$_REQUEST['tre_delete'];

   if( @$_REQUEST['tre_cancel'] ) // cancel action
      jump_to("tournaments/roundrobin/edit_rounds.php?tid=$tid".URI_AMP."round=$round");
   elseif( @$_REQUEST['tre_edit'] ) // edit-data
      jump_to("tournaments/roundrobin/edit_round_props.php?tid=$tid".URI_AMP."round=$round");

   $tourney = Tournament::load_tournament($tid);
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.edit_rounds.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   if( $round < 1 )
      $round = $tourney->CurrentRound;

   // edit allowed?
   if( !$tourney->allow_edit_tournaments($my_id) )
      error('tournament_edit_not_allowed', "Tournament.edit_rounds.edit($tid,$my_id)");

   $tround = null;
   if( $round > 0 )
      $tround = TournamentRound::load_tournament_round( $tid, $round );

   $errors = $tstatus->check_edit_status( TournamentRound::get_edit_tournament_status() );
   //TODO $allow_admin = TournamentLadder::allow_edit_ladder($tourney, $errors); // check-locks
   //TODO $tdwork_locked = $tourney->isFlagSet(TOURNEY_FLAG_LOCK_TDWORK); //TODO
   //TODO $authorise_seed = ( $tourney->Status == TOURNEY_STATUS_PAIR ); //TODO


   // init
   /*TODO
   $user = null;
   $tladder_user = null;
   $authorise_edit_user = $authorise_add_user = false;
   $count_tp_reg = $count_tl_user = 0;
   if( !$is_delete && $uid > 0 )
   {
      $user = User::load_user($uid);
      if( is_null($user) )
         $errors[] = T_('Unknown user-id') . " [".$uid."]";
      else
      {
         $tladder_user = TournamentLadder::load_tournament_ladder_by_user($tid, $uid);

         $tp = TournamentParticipant::load_tournament_participant( $tid, $uid, 0 );
         if( is_null($tp) )
            $errors[] = sprintf( T_('Missing tournament user registration for user [%s].'), $user->Handle );
         else
         {
            $authorise_edit_user = true;
            $authorise_add_user = ( $tp->Status == TP_STATUS_REGISTER );

            if( @$_REQUEST['tre_adduser'] && !$authorise_add_user )
               $errors[] = T_('Adding unregistered user to ladder is not allowed.');

            if( is_null($tladder_user) && ($user->AdminOptions & ADMOPT_DENY_TOURNEY_REGISTER) )
               $errors[] = T_('Tournament registration of this user has been denied by admins.');
         }
      }
   }
   elseif( !$is_delete && $uid <= 0 )
   {
      $tp_arr = TournamentParticipant::count_tournament_participants( $tid, TP_STATUS_REGISTER );
      $count_tp_reg = (int)@$tp_arr[TP_STATUS_REGISTER];
      $count_tl_user = TournamentLadder::count_tournament_ladder( $tid );
   }
   */


   // ---------- Process actions ------------------------------------------------

   if( count($errors) == 0 )
   {
      if( @$_REQUEST['tre_add_confirm'] ) // add new T-round
      {
         //TODO check adding: check T-status edit-allowed ??, check for max-nr of T-rounds (<=255)
         $new_tround = TournamentHelper::add_new_tournament_round( $tourney );
         //TODO handle errors from previous call
         if( !is_null($new_tround) )
         {
            $round = $new_tround->Round;
            $sys_msg = urlencode( T_('Tournament round added!#tround') );
            jump_to("tournaments/roundrobin/edit_rounds.php?tid=$tid".URI_AMP."round=$round".URI_AMP."sysmsg=$sys_msg");
         }
      }

      if( @$_REQUEST['tre_del_confirm'] ) // remove T-round
      {
         //TODO check deleting: check T-status edit-allowed ??, check T-round-status == INIT, check for min. 1 T-round, check currentRound=del-selected=round
         $success = TournamentHelper::delete_tournament_round( $tourney, $round );
         //TODO handle errors from previous call
         if( $success )
         {
            $sys_msg = urlencode( sprintf( T_('Tournament round #%s removed!#tround'), $round ) );
            jump_to("tournaments/roundrobin/edit_rounds.php?tid=$tid".URI_AMP."sysmsg=$sys_msg");
         }
      }
   }

   /* TODO
   if( $allow_admin && count($errors) == 0 )
   {
      if( $is_delete && $authorise_seed && @$_REQUEST['confirm'] ) // confirm delete ladder
      {
         TournamentLadder::delete_ladder($tid);
         $sys_msg = urlencode( T_('Ladder deleted!#tourney') );
         jump_to("tournaments/ladder/admin.php?tid=$tid".URI_AMP."sysmsg=$sys_msg");
      }

      if( @$_REQUEST['tre_seed'] && $authorise_seed )
      {
         $seed_order = (int)get_request_arg('seed_order');
         $seed_reorder = (bool)get_request_arg('seed_reorder');
         TournamentLadder::seed_ladder( $tourney, $tprops, $seed_order, $seed_reorder );
         $sys_msg = urlencode( T_('Ladder seeded!#tourney') );
         jump_to("tournaments/ladder/admin.php?tid=$tid".URI_AMP."sysmsg=$sys_msg");
      }

      if( @$_REQUEST['tre_adduser'] && $authorise_add_user && !is_null($user) && is_null($tladder_user) )
      {
         TournamentLadder::add_user_to_ladder( $tid, $user->ID );
         $sys_msg = urlencode( sprintf( T_('User [%s] added to ladder!#tourney'), $user->Handle) );
         jump_to("tournaments/ladder/admin.php?tid=$tid".URI_AMP."uid=$uid".URI_AMP."sysmsg=$sys_msg");
      }

      $remove_all = (bool)@$_REQUEST['tre_deluserall'];
      if( (@$_REQUEST['tre_deluser'] || $remove_all) && $authorise_edit_user && !is_null($user) && !is_null($tladder_user) )
      {
         if( $tladder_user->remove_user_from_ladder( $remove_all ) )
         {
            if( $remove_all )
            {
               TournamentLadder::notify_removed_user( "Tournament.edit_rounds.notify($tid,$uid,$my_id)", $tid, $uid,
                  sprintf( T_('You have been removed from %s by tournament director (or admin).#tourney'),
                           "<tourney $tid>", "<user $uid>" ));
            }

            $txtfmt = ($remove_all)
               ? T_('User [%s] completely removed from this ladder-tournament!#tourney')
               : T_('User [%s] removed from ladder!#tourney');
            $sys_msg = urlencode( sprintf( $txtfmt, $user->Handle) . ' ' . T_('User has been notified!') );
            jump_to("tournaments/ladder/admin.php?tid=$tid".URI_AMP."uid=$uid".URI_AMP."sysmsg=$sys_msg");
         }
      }
   }
   */

   // ---------- Tournament Form -----------------------------------

   $tform = new Form( 'tournament', $page, FORM_GET );
   $tform->add_hidden( 'tid', $tid );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   TournamentUtils::show_tournament_flags( $tform, $tourney );
   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament Status#tourney'),
         'TEXT', $tourney->getStatusText($tourney->Status) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Rounds#tourney'),
         'TEXT', $tourney->formatRound(), ));

   if( count($errors) )
   {
      $tform->add_row( array( 'HR' ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString( T_('There are some errors'), $errors ) ));
      $tform->add_empty_row();
   }
   /* TODO
   elseif( $tourney->isFlagSet(TOURNEY_FLAG_LOCK_TDWORK) )
   {
      $tform->add_row( array( 'HR' ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Warning'),
            'TEXT', $tourney->buildMaintenanceLockText(TOURNEY_FLAG_LOCK_TDWORK) ));
   }
   */

   // GUI: Edit rounds -----------------

   $arr_rounds = array_value_to_key_and_value( range(1, $tourney->Rounds) );

   $tform->add_row( array( 'HR' ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Select Round'),
         'SELECTBOX',   'round', 1, $arr_rounds, $round, false,
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tre_view', T_('View Round'), ));

   //TODO add check on max-T-Rounds (=255) -> hide add-round
   $tform->add_row( array(
         'TAB', 'CELL', 1, '',
         'SUBMITBUTTON', 'tre_add', T_('Add Round'), ));
   if( @$_REQUEST['tre_add'] )
      echo_confirm( $tform, T_('Please confirm adding of new tournament round'), 'tre_add', T_('Confirm add') );

   $tform->add_row( array(
         'TAB', 'CELL', 1, '',
         'SUBMITBUTTON', 'tre_del', T_('Remove Round'), ));
   if( @$_REQUEST['tre_del'] )
      echo_confirm( $tform, T_('Please confirm deletion of selected tournament round'), 'tre_del', T_('Confirm deletion') );

   $tform->add_row( array(
         'TAB', 'CELL', 1, '',
         'SUBMITBUTTON', 'tre_edit', T_('Edit Round Properties'), ));
   //TODO $tform->add_row( array(
         //'TAB', 'CELL', 1, '',
         //'SUBMITBUTTON', 'tre_set', T_('Set Current Round'), ));
   //TODO $tform->add_row( array(
         //'TAB', 'CELL', 1, '',
         //'SUBMITBUTTON', 'tre_stat', T_('Change Round Status'), ));

   /* TODO
   if( $authorise_seed )
   {
      $tform->add_row( array( 'HR' ));
      $tform->add_row( array( 'HEADER', T_('Prepare Ladder') ));
      if( $count_tp_reg + $count_tl_user > 0 )
      {
         $tform->add_row( array(
               'CELL', 2, '',
               'TEXT', sprintf( T_('There are %s registered participants from which %s already joined the ladder.'),
                              $count_tp_reg, $count_tl_user ), ));
         $tform->add_empty_row();
      }

      if( $is_delete )
      {
         $tform->add_hidden( 'confirm', 1 );
         $tform->add_row( array(
               'CELL', 2, '',
               'TEXT', T_('Please confirm deletion of ladder') . ':', ));
         $tform->add_row( array(
               'CELL', 2, '',
               'SUBMITBUTTON', 'tre_delete', T_('Confirm delete'),
               'TEXT', SMALL_SPACING,
               'SUBMITBUTTON', 'tre_cancel', T_('Cancel') ));
      }

      $tform->add_empty_row();
   }
   */

   // GUI: show round info -------------

   if( !is_null($tround) )
   {
      $tform->add_row( array( 'HR' ));
      $tform->add_row( array( 'HEADER', T_('Tournament Round Info') ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Tournament Round#tround'),
            'TEXT', $tround->Round, ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Last changed'),
            'TEXT',        TournamentUtils::buildLastchangedBy($tround->Lastchanged, $tround->ChangedBy) ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Round Status#tround'),
            'TEXT', TournamentRound::getStatusText($tround->Status), ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Pool Size#tround'),
            'TEXT', sprintf( T_('min/max-range %s'), TournamentUtils::build_range_text($tround->MinPoolSize, $tround->MaxPoolSize) ), ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Pool Count#tround'),
            'TEXT', $tround->PoolCount, ));
   }

   // ADMIN: Add/Remove user -----------

   /* TODO
   $tform->add_row( array( 'HR' ));
   $tform->add_row( array( 'HEADER', T_('Admin Ladder participants') ));
   if( $uid > 0 )
   {
      $tform->add_hidden( 'uid', $uid );
      $tform->add_row( array(
            'CELL', 2, '',
            'TEXT', sprintf( '%s: %s%s',
                             T_('User to edit#tourney'),
                             SMALL_SPACING . $user->user_reference() . SMALL_SPACING.SMALL_SPACING,
                             anchor( $base_path."tournaments/edit_participant.php?tid=$tid".URI_AMP."uid=$uid",
                                     T_('Edit participant'), '', 'class="TAdmin"') ), ));
      $tform->add_empty_row();
   }
   if( !$is_delete && !is_null($user) && $authorise_edit_user ) // valid user
   {
      if( is_null($tladder_user) )
      {
         if( $authorise_add_user )
            add_form_edit_user( $tform, $user,
               'tre_adduser', T_('Add user [%s] to ladder'),
               T_('Add registered tournament participant to ladder (at bottom).') );
         else
            $tform->add_row( array(
                  'CELL', 2, '',
                  'TEXT', T_('NOTE: Adding user only allowed for registered tournament participants.'), ));
      }
      else
      {
         add_form_edit_user( $tform, $user,
            'tre_deluser', T_('Remove user [%s] from ladder'),
            T_('Remove tournament participant from ladder and eventually remove user registration too.'),
            /*notify* /false,
            T_('User will only be removed from ladder. Tournament user registration is kept.') );

         $tform->add_empty_row();
         add_form_edit_user( $tform, $user,
            'tre_deluserall', T_('Remove user [%s] completely'),
            T_('User will be removed from ladder and tournament user registration will be removed too.'),
            /*notify* /true );
      }
   }
   $tform->add_empty_row();
   */


   $title = T_('Tournament Rounds Editor');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Manage tournament')] =
      array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}

function echo_confirm( &$tform, $message, $confirm_action, $confirm_text )
{
   $tform->add_empty_row();
   $tform->add_row( array(
         'CELL', 2, '',
         'TEXT', "$message:", ));
   $tform->add_row( array(
         'CELL', 2, '',
         'SUBMITBUTTON', $confirm_action.'_confirm', $confirm_text,
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tre_view', T_('Cancel') ));
   $tform->add_empty_row();
}//echo_confirm
?>
