<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_ladder.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentLadderWithdraw');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.ladder.withdraw');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.ladder.withdraw');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'Tournament.ladder.withdraw');

   $page = "withdraw.php";

/* Actual REQUEST calls used
     tid=                           : info about withdraw
     tid=&tu_withdraw&&confirm=1    : withdraw from ladder confirmed
     tid=&tu_cancel                 : cancel withdraw
*/

   $tid = (int)@$_REQUEST['tid'];
   if ( $tid < 0 ) $tid = 0;

   if ( @$_REQUEST['tu_cancel'] ) // cancel delete
      jump_to("tournaments/ladder/view.php?tid=$tid");

   $tourney = TournamentCache::load_cache_tournament( 'Tournament.ladder.withdraw.find_tournament', $tid );
   $tstatus = new TournamentStatus( $tourney );

   $errors = $tstatus->check_edit_status( array( TOURNEY_STATUS_PLAY ) );
   if ( $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN|TOURNEY_FLAG_LOCK_TDWORK) )
      $errors[] = $tourney->buildMaintenanceLockText();

   $tladder = TournamentLadder::load_tournament_ladder_by_user($tid, $my_id);
   if ( is_null($tladder) )
      $errors[] = T_('Withdrawing from this ladder is not possible, because you didn\'t join it.');

   $count_run_games = ( is_null($tladder) ) ? -1 : TournamentGames::count_user_running_games( $tid, $my_id );


   // ---------- Process actions ------------------------------------------------

   if ( @$_REQUEST['confirm'] && !is_null($tladder) && count($errors) == 0 ) // confirm withdrawal
   {
      ta_begin();
      {//HOT-section to remove user
         $tladder->remove_user_from_ladder( 'Tournament.ladder.withdraw',
            TLOG_TYPE_USER, 'Ladder-Withdraw', /*upd-rank*/false, $my_id, $player_row['Handle'], /*nfy-user*/false,
            T_('User withdrew from the ladder tournament.') );
      }
      ta_end();

      $sys_msg = urlencode( T_('Withdrawn from ladder!') );
      jump_to("tournaments/view_tournament.php?tid=$tid".URI_AMP."sysmsg=$sys_msg");
   }


   // ---------- Tournament Form -----------------------------------

   $tform = new Form( 'tournament', $page, FORM_GET );
   $tform->add_hidden( 'tid', $tid );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   $tform->add_row( array(
         'DESCRIPTION', T_('User'),
         'TEXT',        user_reference( REF_LINK, 1, '', $player_row ) ));

   $has_errors = ( count($errors) > 0 );
   if ( $has_errors )
   {
      $tform->add_row( array( 'HR' ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString( T_('There are some errors'), $errors ) ));
      $tform->add_empty_row();
   }

   // EDIT: Submit-Buttons -------------

   $tform->add_hidden( 'confirm', 1 );
   $tform->add_row( array( 'HEADER', T_('Ladder user info') ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Started'),
         'TEXT',        date(DATE_FMT, $tladder->Created) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Rank Changed#T_ladder'),
         'TEXT',        date(DATE_FMT, $tladder->RankChanged ) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Rank#T_ladder'),
         'TEXT',        $tladder->Rank ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Best Rank#T_ladder'),
         'TEXT',        $tladder->BestRank ));
   if ( $count_run_games >= 0 )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Running tournament games'),
            'TEXT',        span('bold', $count_run_games) ));
   }
   $tform->add_empty_row();

   $tform->add_row( array(
         'TAB',
         'TEXT', T_('Please confirm if you want to withdraw from ladder!') . "<br>\n"
               . T_('(also see notes below)'), ));
   $tform->add_row( array(
         'TAB', 'CELL', 2, '',
         'SUBMITBUTTONX', 'tu_withdraw', T_('Confirm Withdrawal#T_ladder'), ($has_errors ? 'disabled=1' : ''),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tu_cancel', T_('Cancel') ));


   $title = T_('Withdraw from Ladder');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();

   echo_notes( 'withdrawalNotesTable', T_('Withdrawal notes#T_ladder'), build_withdrawal_notes() );


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Tournament participants')] = "tournaments/list_participants.php?tid=$tid";
   $menu_array[T_('View Ladder')] = "tournaments/ladder/view.php?tid=$tid";
   $menu_array[T_('My running games')] = "show_games.php?tid=$tid".URI_AMP."uid=$my_id";

   end_page(@$menu_array);
}//main


/*! \brief Returns array with notes about withdrawing from ladder. */
function build_withdrawal_notes()
{
   $notes = array();

   $notes[] = wordwrap( TournamentUtils::get_tournament_ladder_notes_user_removed(), 100 ) . "\n" .
      T_('The opponents in your running tournament games will be notified about the withdrawal.#T_ladder');
   $notes[] = T_('Withdrawing from this ladder will remove your tournament user registration along with the rank history.');
   $notes[] = T_('If you rejoin the ladder, your get a new ladder rank according to the tournaments properties.');

   return $notes;
}//build_withdrawal_notes
?>
