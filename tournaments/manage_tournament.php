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
require_once( 'include/std_functions.php' );
require_once( 'include/gui_functions.php' );
require_once( 'include/form_functions.php' );
require_once( 'tournaments/include/tournament_utils.php' );
require_once( 'tournaments/include/tournament.php' );

$GLOBALS['ThePage'] = new Page('TournamentManage');

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
   if( $tourney->Lastchanged )
      $tform->add_row( array(
            'DESCRIPTION', T_('Last changed date'),
            'TEXT',        date(DATEFMT_TOURNAMENT, $tourney->Lastchanged) ));

   $tform->add_row( array(
         'DESCRIPTION', T_('Status'),
         'TEXT',        $tourney->getStatusText($tourney->Status), ));
//TODO TODO add current-round

   $tform->add_hidden( 'tid', $tid );


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();

   echo '<table><tr><td>', "<hr>\n",
      '<ul class="TAdminLinks">',
         '<li>', make_menu_link( T_('Edit tournament'), array( 'url' => "tournaments/edit_tournament.php?tid=$tid", 'class' => 'TAdmin' )),
                 subList( array( T_('Change status, scope, type, title, description, rounds') )),
                 "</li>\n",
         '<li>', ( $allow_new_del_TD
                     ? make_menu_link( T_('Add tournament director'), array( 'url' => "tournaments/edit_director.php?tid=$tid", 'class' => 'TAdmin' ))
                     : T_('Add tournament director') ),
                 subList( array( T_('Adding new tournament director (allowed only for Tournament Owner)') )),
                 "</li>\n",
         '<li>', make_menu_link( T_('Show tournament directors'), "tournaments/list_directors.php?tid=$tid" ),
                 "</li>\n",
         '<li>', make_menu_link( T_('Edit properties'), array( 'url' => "tournaments/edit_properties.php?tid=$tid", 'class' => 'TAdmin' )),
//TODO TODO add "(need creation)" if needed to create/save
                 subList( array( T_('Change properties for registration ...'),
                                 T_('tournament-related: min./max. participants, rating use mode'),
                                 T_('user-related: user rating range, min. (rated) finished games') )),
                 "</li>\n",
         '<li>', make_menu_link( T_('Edit rules'), array( 'url' => "tournaments/edit_rules.php?tid=$tid", 'class' => 'TAdmin' )),
//TODO TODO add "(need creation)" if needed to create/save
                 subList( array( T_('Change game-settings: board size, handicap-settings, time-settings, rated, notes') )),
                 "</li>\n",
         '<li>', make_menu_link( T_('Edit participants'), array( 'url' => "tournaments/edit_participant.php?tid=$tid", 'class' => 'TAdmin' )),
                 subList( array( T_('Manage registration of users: invite user, approve or reject application, remove registration'),
                                 T_('Change status, start round, read message from user and answer with admin message') )),
                 "</li>\n",
         '<li>', make_menu_link( T_('Show tournament participants'), "tournaments/list_participants.php?tid=$tid" ),
                 "</li>\n",
         '<li>', make_menu_link( T_('Edit round'), array( 'url' => "tournaments/edit_round.php?tid=$tid", 'class' => 'TAdmin' )),
//TODO TODO add "(need creation)" if needed to create/save
                 subList( array( T_('Setup tournament round for pooling and pairing') )),
                 "</li>\n",
      '</ul>',
      '</tr></td></table>',
      "\n";

   $menu_array = array();
   if( $tid )
      $menu_array[T_('View this tournament')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Manage this tournament')] =
      array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}

function subList( $arr, $class='SubList' )
{
   if( count($arr) == 0 )
      return '';
   $class_str = ($class != '') ? " class=\"$class\"" : '';
   return "<ul{$class_str}><li>" . implode("</li>\n<li>", $arr) . "</li></ul>\n";
}
?>
