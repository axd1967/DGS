<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once( 'include/game_functions.php' );
require_once( 'include/utilities.php' );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];

   $viewmode = (int) get_request_arg('view', GSETVIEW_SIMPLE);
   if( $viewmode < 0 || $viewmode > MAX_GSETVIEW )
      $viewmode = GSETVIEW_SIMPLE;
   if( $viewmode != GSETVIEW_SIMPLE && @$player_row['RatingStatus'] == RATING_NONE )
      error('multi_player_need_initial_rating',
            "new_game.check.viewmode_rating($my_id,$viewmode,{$player_row['RatingStatus']})");

   $my_rating = @$player_row['Rating2'];
   $iamrated = ( $player_row['RatingStatus'] != RATING_NONE
      && is_numeric($my_rating) && $my_rating >= MIN_RATING );

   $page = "new_game.php?";
   $title = T_('Add new game to waiting room');
   start_page($title, true, $logged_in, $player_row );
   echo "<h3 class=Header>", sprintf( "%s (%s)", $title, get_gamesettings_viewmode($viewmode) ), "</h3>\n";

   add_new_game_form( 'addgame', $viewmode, $iamrated); //==> ID='addgameForm'


   $menu_array = array();
   $menu_array[T_('New game')] = 'new_game.php';
   $menu_array[T_('New expert game')] = 'new_game.php?view='.GSETVIEW_EXPERT;
   if( @$player_row['RatingStatus'] != RATING_NONE )
      $menu_array[T_('New multi-player-game')] = 'new_game.php?view='.GSETVIEW_MPGAME;
   $menu_array[T_('Waiting room')] = 'waiting_room.php';
   $menu_array[T_('Invite')] = 'message.php?mode=Invite';

   end_page(@$menu_array);
}


function add_new_game_form( $form_id, $viewmode, $iamrated)
{
   $addgame_form = new Form( $form_id, 'add_to_waitingroom.php', FORM_POST );

   game_settings_form($addgame_form, GSET_WAITINGROOM, $viewmode, $iamrated);

   $addgame_form->add_row( array( 'SPACE' ) );
   $addgame_form->add_row( array( 'SUBMITBUTTON', 'add_game', T_('Add Game') ) );

   $addgame_form->echo_string(1);
} //add_new_game_form

?>
