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

chdir('..');
require_once 'include/form_functions.php';
require_once 'tournaments/include/tournament_factory.php';


{
   $GLOBALS['ThePage'] = new Page('TournamentWizard');
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.wizard');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.wizard');

   // create allowed?
   $my_id = $player_row['ID'];
   $is_guest = ( $my_id <= GUESTS_ID_MAX );
   $is_admin = TournamentUtils::isAdmin();
   $create_tourney = TournamentUtils::check_create_tournament('Tournament.wizard');

/* Actual REQUEST calls used:
     (no args)     : add new tournament
     t_save        : create tournament into database
*/

   // check + parse args
   $type = (int)get_request_arg('type');
   $ttype = ( $type ) ? TournamentFactory::getTournament($type) : null;

   // save tournament-object with values from edit-form
   if( @$_REQUEST['t_save'] && !is_null($ttype) )
   {
      if( $is_guest ) // view ok, edit forbidden
         error('not_allowed_for_guest', 'Tournament.wizard');

      // check if user is allowed to create tournament
      if( $ttype->need_admin_create_tourney && !$is_admin )
         error('tournament_create_not_allowed', "Tournament.wizard.create.non_admin($my_id,$type)");

      $tid = $ttype->createTournament();
      jump_to("tournaments/manage_tournament.php?tid=$tid".URI_AMP
            . "sysmsg=". urlencode(T_('Tournament created!')) );
   }

   $page = "wizard.php";
   $title = T_('Tournament Wizard');


   // ---------- Tournament Wizard form ----------------------------

   $tform = new Form( 'wizard', $page, FORM_POST );

   $tform->add_row( array( 'HEADER', T_('Choose type of tournament') ));
   $last_wiz_flags = 0;
   foreach( TournamentFactory::getTournamentTypes() as $wiztype => $wiz_flags )
   {
      $new_ttype = TournamentFactory::getTournament($wiztype);
      if( $last_wiz_flags > 0 && ( ($last_wiz_flags & TWIZT_MASK) != ($wiz_flags & TWIZT_MASK) ) )
         $tform->add_empty_row();
      $tform->add_row( build_tourney_type_radio($new_ttype, $type, $is_admin) );
      $last_wiz_flags = $wiz_flags;
   }

   $tform->add_empty_row();
   $tform->add_row( array(
         'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 't_save', T_('Create Tournament'), ));


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();


   $menu_array = array();
   $menu_array[T_('Create new tournament')] = ($create_tourney == 1)
      ? array( 'url' => 'tournaments/wizard.php', 'class' => 'AdminLink' )
      : 'tournaments/wizard.php';

   end_page(@$menu_array);
}


function build_tourney_type_radio( $ttype, $type, $is_admin )
{
   $radio_defs = array( $ttype->wizard_type => $ttype->title );
   $disabled = ( $ttype->need_admin_create_tourney && !$is_admin ) ? 'disabled=1' : '';
   return array( 'RADIOBUTTONSX', 'type', $radio_defs, $type, $disabled );
}//build_tourney_type_radio

?>
