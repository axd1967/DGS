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

chdir('..');
require_once 'include/form_functions.php';
require_once 'tournaments/include/tournament_factory.php';


{
   $GLOBALS['ThePage'] = new Page('TournamentWizard');
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.wizard');

   $my_id = $player_row['ID'];
   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   // create/edit allowed?
   if( !Tournament::allow_create($my_id) )
      error('tournament_edit_not_allowed', "tournament_wizard.create($my_id)");
   $is_admin = TournamentUtils::isAdmin();

/* Actual REQUEST calls used:
     (no args)     : add new tournament
     t_save        : create tournament into database
*/

   // check + parse args
   $type = (int)get_request_arg('type');
   $tourney = ( $type ) ? TournamentFactory::getTournament($type) : null;

   // save tournament-object with values from edit-form
   if( @$_REQUEST['t_save'] && !is_null($tourney) )
   {
      $tid = $tourney->createTournament();
      jump_to("tournaments/manage_tournament.php?tid=$tid".URI_AMP
            . "sysmsg=". urlencode(T_('Tournament created!')) );
   }

   $page = "wizard.php";
   $title = T_('Tournament Wizard');


   // ---------- Tournament Wizard form ----------------------------

   $tform = new Form( 'wizard', $page, FORM_POST );

   $tform->add_row( array( 'HEADER', T_('Choose type of tournament') ));
   if( $is_admin )
      $tform->add_row( array(
         'RADIOBUTTONS', 'type', array(
               TOURNEY_WIZTYPE_DGS_LADDER => T_('DGS Ladder (only for Admin)') ), $type ));

   $tform->add_empty_row();
   $tform->add_row( array(
         'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 't_save', T_('Create Tournament'), ));


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();


   $menu_array = array();
   $menu_array[T_('Create new tournament')] = 'tournaments/wizard.php';

   end_page(@$menu_array);
}
?>
