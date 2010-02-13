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
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_ladder.php';

$GLOBALS['ThePage'] = new Page('TournamentLadderAdmin');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.ladder.admin');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $page = "admin.php";

/* Actual REQUEST calls used
     tid=                        : admin T-ladder
     ta_seed&tid=                : seed T-ladder with registered TPs
     ta_adduser&tid=&uid=        : add user (Players.ID) to T-ladder
     ta_deluser&tid=&uid=        : remove user (Players.ID) from T-ladder (no confirm)
     ta_deluserall&tid=&uid=     : remove user (Players.ID) along WITH user-registration (no confirm)
     ta_delete&tid=              : delete T-ladder (to seed again, need confirm)
     ta_delete&confirm=1&tid=    : delete T-ladder (confirmed)
*/

   $tid = (int) @$_REQUEST['tid'];
   $uid = (int) @$_REQUEST['uid'];
   if( $tid < 0 ) $tid = 0;
   $is_delete = (bool) @$_REQUEST['ta_delete'];

   if( @$_REQUEST['ta_cancel'] ) // cancel delete
      jump_to("tournaments/ladder/admin.php?tid=$tid");

   $tourney = Tournament::load_tournament($tid);
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.ladder_admin.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);

   // edit allowed?
   $is_admin = TournamentUtils::isAdmin();
   if( !$tourney->allow_edit_tournaments($my_id) )
      error('tournament_edit_not_allowed', "Tournament.ladder_admin.edit($tid,$my_id)");

   $tprops = TournamentProperties::load_tournament_properties($tid);
   if( is_null($tprops) )
      error('bad_tournament', "Tournament.ladder_admin.miss_properties($tid,$my_id)");

   $errors = $tstatus->check_edit_status( TournamentLadder::get_edit_tournament_status() );
   $authorise_seed = ( $tourney->Status == TOURNEY_STATUS_PAIR );

   // init
   $user = null;
   $tladder_user = null;
   $authorise_edit_user = $authorise_add_user = false;
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

            if( @$_REQUEST['ta_adduser'] && !$authorise_add_user )
               $errors[] = T_('Adding unregistered user to ladder is not allowed.');

            if( is_null($tladder_user) && ($user->AdminOptions & ADMOPT_DENY_TOURNEY_REGISTER) )
               $errors[] = T_('Tournament registration of this user has been denied by admins.');
         }
      }
   }

   // ---------- Process actions ------------------------------------------------

   if( count($errors) == 0 )
   {
      if( $is_delete && $authorise_seed && @$_REQUEST['confirm'] ) // confirm delete ladder
      {
         TournamentLadder::delete_ladder($tid);
         $sys_msg = urlencode( T_('Ladder deleted!#tourney') );
         jump_to("tournaments/ladder/admin.php?tid=$tid".URI_AMP."sysmsg=$sys_msg");
      }

      if( @$_REQUEST['ta_seed'] && $authorise_seed )
      {
         $seed_order = get_request_arg('seed_order');
         TournamentLadder::seed_ladder( $tourney, $tprops, $seed_order );
         $sys_msg = urlencode( T_('Ladder seeded!#tourney') );
         jump_to("tournaments/ladder/admin.php?tid=$tid".URI_AMP."sysmsg=$sys_msg");
      }

      if( @$_REQUEST['ta_adduser'] && $authorise_add_user && !is_null($user) && is_null($tladder_user) )
      {
         TournamentLadder::add_user_to_ladder( $tid, $user->ID );
         $sys_msg = urlencode( sprintf( T_('User [%s] added to ladder!#tourney'), $user->Handle) );
         jump_to("tournaments/ladder/admin.php?tid=$tid".URI_AMP."uid=$uid".URI_AMP."sysmsg=$sys_msg");
      }

      $remove_all = (bool)@$_REQUEST['ta_deluserall'];
      if( (@$_REQUEST['ta_deluser'] || $remove_all) && $authorise_edit_user && !is_null($user) && !is_null($tladder_user) )
      {
         if( $tladder_user->remove_user_from_ladder( $remove_all ) )
         {
            //TODO send user-notification about removal !?
            $txtfmt = ($remove_all)
               ? T_('User [%s] completely removed from this ladder-tournament!#tourney')
               : T_('User [%s] removed from ladder!#tourney');
            $sys_msg = urlencode( sprintf( $txtfmt, $user->Handle) );
            jump_to("tournaments/ladder/admin.php?tid=$tid".URI_AMP."uid=$uid".URI_AMP."sysmsg=$sys_msg");
         }
      }
   }

   // ---------- Tournament Form -----------------------------------

   $tform = new Form( 'tournament', $page, FORM_GET );
   $tform->add_hidden( 'tid', $tid );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Status'),
         'TEXT', $tourney->getStatusText($tourney->Status) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Rating Use Mode'),
         'TEXT',  TournamentProperties::getRatingUseModeText($tprops->RatingUseMode) ));

   if( count($errors) )
   {
      $tform->add_row( array( 'HR' ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString( T_('There are some errors'), $errors ) ));
      $tform->add_empty_row();
   }

   // ADMIN: Seed Ladder ---------------

   if( $authorise_seed )
   {
      $tform->add_row( array( 'HR' ));
      $tform->add_row( array( 'HEADER', T_('Prepare Ladder') ));

      if( !$is_delete )
      {
         list( $seed_order_def, $arr_seed_order ) = $tprops->build_ladder_seed_order();
         $seed_order_val = get_request_arg('seed_order', $seed_order_def);
         $tform->add_row( array(
               'CELL', 2, '',
               'TEXT', T_('Seed ladder with all registered tournament participants') . ':', ));
         $tform->add_row( array(
               'CELL', 2, '',
               'TEXT',         T_('Order by') . MED_SPACING,
               'SELECTBOX',    'seed_order', 1, $arr_seed_order, $seed_order_val, false,
               'SUBMITBUTTON', 'ta_seed', T_('Seed Ladder'),
               'TEXT',         SMALL_SPACING,
               'SUBMITBUTTON', 'ta_delete', T_('Delete Ladder'), ));
      }
      else
      {
         $tform->add_hidden( 'confirm', 1 );
         $tform->add_row( array(
               'CELL', 2, '',
               'TEXT', T_('Please confirm deletion of ladder') . ':', ));
         $tform->add_row( array(
               'CELL', 2, '',
               'SUBMITBUTTON', 'ta_delete', T_('Confirm delete'),
               'TEXT', SMALL_SPACING,
               'SUBMITBUTTON', 'ta_cancel', T_('Cancel') ));
      }

      $tform->add_empty_row();
   }

   // ADMIN: Add/Remove user -----------

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
               'ta_adduser', T_('Add user [%s] to ladder'),
               T_('Add registered tournament participant to ladder (at bottom)') );
         else
            $tform->add_row( array(
                  'CELL', 2, '',
                  'TEXT', T_('NOTE: Adding user only allowed for registered tournament participants.'), ));
      }
      else
      {
         add_form_edit_user( $tform, $user,
            'ta_deluser', T_('Remove user [%s] from ladder'),
            T_('Remove tournament participant from ladder and eventually remove user registration too'),
            T_('User will only be removed from ladder. Tournament user registration is kept.') );

         $tform->add_empty_row();
         add_form_edit_user( $tform, $user,
            'ta_deluserall', T_('Remove user [%s] completely'),
            T_('User will be removed from ladder and tournament user registration will be removed too.') );
      }
   }
   $tform->add_empty_row();


   $title = T_('Tournament Ladder Admin');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Tournament participants')] = "tournaments/list_participants.php?tid=$tid";
   $menu_array[T_('View Ladder')] = "tournaments/ladder/view.php?tid=$tid";
   $menu_array[T_('Edit Ladder')] =
      array( 'url' => "tournaments/ladder/view.php?tid=$tid".URI_AMP."admin=1", 'class' => 'TAdmin' );
   $menu_array[T_('Manage tournament')] =
      array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}


function add_form_edit_user( &$form, $user, $action, $act_fmt, $title, $extra='' )
{
   $form->add_row( array(
         'CELL', 2, '',
         'TEXT', $title.':', ));
   if( $extra )
      $form->add_row( array(
            'CELL', 2, '',
            'TEXT', $extra, ));
   $form->add_row( array(
         'CELL', 2, '',
         'SUBMITBUTTON', $action, sprintf( $act_fmt, $user->Handle ), ));
}//add_form_edit_user
?>
