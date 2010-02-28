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
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_ladder.php';

$GLOBALS['ThePage'] = new Page('TournamentLadderRetreat');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.ladder.retreat');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $page = "retreat.php";

/* Actual REQUEST calls used
     tid=                : info about retreat
     tid=&confirm=1      : retreat from ladder confirmed
     tid=&tu_cancel      : cancel retreat
*/

   $tid = (int)@$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;

   if( @$_REQUEST['tu_cancel'] ) // cancel delete
      jump_to("tournaments/ladder/view.php?tid=$tid");

   $tourney = Tournament::load_tournament($tid);
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.ladder_retreat.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );

   $errors = $tstatus->check_edit_status( TournamentLadder::get_view_ladder_status(false) );
   if( $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN|TOURNEY_FLAG_LOCK_TDWORK) )
      $errors[] = $tourney->buildMaintenanceLockText();

   $tladder = TournamentLadder::load_tournament_ladder_by_user($tid, $my_id);
   if( is_null($tladder) )
      $errors[] = T_('Retreat from this ladder is not possible, because you didn\'t join it.');


   // ---------- Process actions ------------------------------------------------

   if( @$_REQUEST['confirm'] && !is_null($tladder) && count($errors) == 0 ) // confirm retreat
   {
      $tladder->remove_user_from_ladder(true);
      $sys_msg = urlencode( T_('Retreated from Ladder!#tourney') );
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
   if( $has_errors )
   {
      $tform->add_row( array( 'HR' ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString( T_('There are some errors'), $errors ) ));
      $tform->add_empty_row();
   }

   // EDIT: Submit-Buttons -------------

   $tform->add_hidden( 'confirm', 1 );
   $tform->add_row( array( 'HEADER', T_('Ladder user info') ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Started'),
         'TEXT',        date(DATEFMT_TOURNAMENT, $tladder->Created) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Rank changed'),
         'TEXT',        date(DATEFMT_TOURNAMENT, $tladder->RankChanged ) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Rank'),
         'TEXT',        $tladder->Rank ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Best Rank'),
         'TEXT',        $tladder->BestRank ));
   $tform->add_empty_row();

   $tform->add_row( array(
         'TAB',
         'TEXT', T_('Please confirm if you want to retreat from ladder!') . "<br>\n"
               . T_('(also see notes below)'), ));
   $tform->add_row( array(
         'TAB', 'CELL', 2, '',
         'SUBMITBUTTONX', 'tu_retreat', T_('Confirm Retreat'), ($has_errors ? 'disabled=1' : ''),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tu_cancel', T_('Cancel') ));


   $title = T_('Retreat from Ladder');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();

   echo_notes( 'retreatnotesTable', T_('Retreat notes'), build_retreat_notes() );


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Tournament participants')] = "tournaments/list_participants.php?tid=$tid";
   $menu_array[T_('View Ladder')] = "tournaments/ladder/view.php?tid=$tid";

   end_page(@$menu_array);
}


/*! \brief Returns array with notes about retreating from ladder. */
function build_retreat_notes()
{
   $notes = array();

   $notes[] = T_('Retreating from this ladder will also remove your tournament user registration.');
   $notes[] = T_('Your running tournament games will be continued as normal games without effecting the tournament.');
   $notes[] = T_('If you rejoin the ladder, your ladder rank will be restarted according to the tournaments properties.');
   $notes[] = null; // empty line

   return $notes;
}//build_retreat_notes
?>
