<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Game";

require_once( 'include/std_functions.php' );
require_once( 'include/gui_functions.php' );
require_once( 'include/std_classes.php' );
require_once( 'include/rating.php' );
require_once( 'include/table_columns.php' );
require_once( 'include/form_functions.php' );
require_once( 'include/message_functions.php' );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];

   $my_rating = @$player_row['Rating2'];
   $iamrated = ( $player_row['RatingStatus'] && is_numeric($my_rating) && $my_rating >= MIN_RATING );

   $page = "new_game.php?";
   $title = T_('Add new game');
   start_page($title, true, $logged_in, $player_row );
   echo "<h3 class=Header>". $title . "</h3>\n";

   add_new_game_form( 'addgame', $iamrated); //==> ID='addgameForm'


   $menu_array = array();
   $menu_array[T_('Waiting room')] = 'waiting_room.php';
   $menu_array[T_('Invite')] = 'message.php?mode=Invite';

   end_page(@$menu_array);
}


function add_new_game_form( $form_id, $iamrated)
{
   $addgame_form = new Form( $form_id, 'add_to_waitingroom.php', FORM_POST );

   //$addgame_form->add_row( array( 'HEADER', T_('Add new game') ) );

   $vals = array();
   for( $i=1; $i <= NEWGAME_MAX_GAMES; $i++ )
      $vals[$i] = $i;
   $addgame_form->add_row( array( 'DESCRIPTION', T_('Number of games to add'),
                                  'SELECTBOX', 'nrGames', 1, $vals, '1', false ) );

   game_settings_form($addgame_form, GSET_WAITINGROOM, $iamrated);

   $rating_array = getRatingArray();
   $addgame_form->add_row( array( 'DESCRIPTION', T_('Require rated opponent'),
                                  'CHECKBOX', 'must_be_rated', 'Y', "", false,
                                  'TEXT', sptext(T_('If yes, rating between'),1),
                                  'SELECTBOX', 'rating1', 1, $rating_array, '30 kyu', false,
                                  'TEXT', sptext(T_('and')),
                                  'SELECTBOX', 'rating2', 1, $rating_array, '9 dan', false ) );
   $addgame_form->add_row( array( 'DESCRIPTION', T_('Require min. rated finished games'),
                                  'TEXTINPUT', 'min_rated_games', 5, 5, '',
                                  'TEXT', MINI_SPACING . T_('(optional)'), ));
   $same_opp_array = build_accept_same_opponent_array(array( 0,  -1, -2, -3,  3, 7, 14 ));
   $addgame_form->add_row( array( 'DESCRIPTION', T_('Accept same opponent'),
                                  'SELECTBOX', 'same_opp', 1, $same_opp_array, '0', false, ));


   $addgame_form->add_row( array( 'SPACE' ) );
   $addgame_form->add_row( array( 'DESCRIPTION', T_('Comment'),
                                  'TEXTINPUT', 'comment', 40, 40, "" ) );

   $addgame_form->add_row( array( 'SPACE' ) );
   $addgame_form->add_row( array( 'SUBMITBUTTON', 'add_game', T_('Add Game') ) );

   $addgame_form->echo_string(1);
}

?>
