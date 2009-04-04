<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once( 'include/std_functions.php' );
require_once( 'include/gui_functions.php' );
require_once( 'include/form_functions.php' );
require_once( 'tournaments/include/tournament_utils.php' );
require_once( 'tournaments/include/tournament.php' );

$ThePage = new Page('TournamentManage');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.manage_tournament');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;

   $tourney = null;
   if( $tid )
      $tourney = Tournament::load_tournament( $tid ); // existing tournament ?
   if( is_null($tourney) )
      error('unknown_tournament', "manage_tournament.find_tournament($tid)");

   // create/edit allowed?
   $is_admin = TournamentUtils::isAdmin();
   if( !$tourney->allow_edit_tournaments($my_id) )
      error('tournament_edit_not_allowed', "manage_tournament.edit_tournament($tid,$my_id)");
   $allow_edit_tourney = $tourney->allow_edit_tournaments( $my_id );
   $allow_new_del_TD = $tourney->allow_edit_directors($my_id, true);

   // init
   $page = "manage_tournament.php";
   $title = T_('Tournament Management');


   // ---------- Tournament Info -----------------------------------

   $tform = new Form( 'tournament', $page, FORM_POST );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        anchor( "view_tournament.php?tid=$tid", $tid ),
         'TEXT',        SMALL_SPACING . '[' . make_html_safe( $tourney->Title, true ) . ']', ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Owner'),
         'TEXT',        ( ($tourney->Owner_ID) ? user_reference( REF_LINK, 1, '', $tourney->Owner_ID ) : NO_VALUE ) ));
   if( $tourney->Created )
      $tform->add_row( array(
            'DESCRIPTION', T_('Creation date'),
            'TEXT',        date(DATEFMT_TOURNAMENT, $tourney->Created) ));
   if( $tourney->Lastchanged )
      $tform->add_row( array(
            'DESCRIPTION', T_('Last changed date'),
            'TEXT',        date(DATEFMT_TOURNAMENT, $tourney->Lastchanged) ));

   $tform->add_row( array(
         'DESCRIPTION', T_('Status'),
         'TEXT',        $tourney->getStatusText($tourney->Status), ));

   $tform->add_hidden( 'tid', $tid );


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();

   $menu_array = array();
   if( $tid )
      $menu_array[T_('View this tournament')] = "tournaments/view_tournament.php?tid=$tid";
   if( $allow_new_del_TD )
      $menu_array[T_('Add tournament director')] =
         array( 'url' => "tournaments/edit_director.php?tid=$tid", 'class' => 'TAdmin' );
   else
      $menu_array[T_('Tournament directors')] = "tournaments/list_directors.php?tid=$tid";
   if( $allow_edit_tourney ) # for TD
   {
      $menu_array[T_('Manage this tournament')] = // for page-refresh
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );
      $menu_array[T_('Edit tournament')] =
         array( 'url' => "tournaments/edit_tournament.php?tid=$tid", 'class' => 'TAdmin' );
      $menu_array[T_('Edit properties')] =
         array( 'url' => "tournaments/edit_properties.php?tid=$tid", 'class' => 'TAdmin' );
      $menu_array[T_('Edit rules')] =
         array( 'url' => "tournaments/edit_rules.php?tid=$tid", 'class' => 'TAdmin' );
      $menu_array[T_('Edit participants')] =
         array( 'url' => "tournaments/edit_participant.php?tid=$tid", 'class' => 'TAdmin' );
   }

   end_page(@$menu_array);
}
?>
