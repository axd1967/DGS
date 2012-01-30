<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

   $arg_viewmode = @$_REQUEST['view'];

   // load template for profile
   $prof_tmpl_id = (int)@$_REQUEST['tmpl'];
   if( $prof_tmpl_id > 0 )
   {
      $profile = Profile::load_profile( $prof_tmpl_id, $my_id ); // loads only if user-id correct
      if( is_null($profile) )
         error('invalid_profile', "new_game.check.profile($prof_tmpl_id)");
error_log("#5: ".$profile->to_string()); //TODO-prof

      // check profile-type vs. msg-mode
      if( $profile->Type != PROFTYPE_TMPL_NEWGAME )
         error('invalid_profile', "new_game.check.profile.type($prof_tmpl_id,{$profile->Type})");

      $profile_template = ProfileTemplate::decode( $profile->Type, $profile->get_text(/*raw*/true) );
error_log("#6: ".$profile_template->to_string()); //TODO-prof
      $profile_template->fill( $_REQUEST );
      $need_redraw = true;

      // allow template-conversion for other views
      $tmpl_suffix = URI_AMP . "tmpl=$prof_tmpl_id";
   }
   else
   {
      $tmpl_suffix = '';
      $need_redraw = @$_REQUEST['rematch'];
   }

   $viewmode = (int) get_request_arg('view', GSETVIEW_SIMPLE);
   if( is_numeric($arg_viewmode) && $viewmode != (int)$arg_viewmode ) // view-URL-arg has prio over template
      $viewmode = (int)$arg_viewmode;
   if( $viewmode < 0 || $viewmode > MAX_GSETVIEW )
      $viewmode = GSETVIEW_SIMPLE;
   if( $viewmode != GSETVIEW_SIMPLE && @$player_row['RatingStatus'] == RATING_NONE )
      error('multi_player_need_initial_rating',
            "new_game.check.viewmode_rating($my_id,$viewmode,{$player_row['RatingStatus']})");

   // handle shape-game (passing-on for new-games)
   $shape_id = (int)get_request_arg('shape');
   $shape_snapshot = get_request_arg('snapshot');
   $shape_url_suffix = ( $shape_id > 0 && $shape_snapshot )
      ? URI_AMP."shape=$shape_id".URI_AMP."snapshot=".urlencode($shape_snapshot)
      : '';

   $my_rating = @$player_row['Rating2'];
   $iamrated = ( $player_row['RatingStatus'] != RATING_NONE
      && is_numeric($my_rating) && $my_rating >= MIN_RATING );

   $page = "new_game.php?";
   if( $viewmode == GSETVIEW_MPGAME )
      $title = T_('Add new game#mpg');
   else
      $title = T_('Add new game to waiting room');
   start_page($title, true, $logged_in, $player_row );
   echo "<h3 class=Header>", sprintf( "%s (%s)", $title, get_gamesettings_viewmode($viewmode) ), "</h3>\n";

   $maxGamesCheck = new MaxGamesCheck();
   if( $maxGamesCheck->allow_game_start() )
   {
      echo $maxGamesCheck->get_warn_text();
      add_new_game_form( 'addgame', $viewmode, $iamrated, $need_redraw ); //==> ID='addgameForm'
   }
   else
      echo $maxGamesCheck->get_error_text();


   $menu_array = array();
   $menu_array[T_('New game')] = 'new_game.php?view='.GSETVIEW_SIMPLE . $shape_url_suffix . $tmpl_suffix;
   $menu_array[T_('Shapes#shape')] = 'list_shapes.php';
   ProfileTemplate::add_menu_link( $menu_array );

   $menu_array[T_('New expert game')] = 'new_game.php?view='.GSETVIEW_EXPERT . $shape_url_suffix . $tmpl_suffix;
   $menu_array[T_('New fair-komi game')] = 'new_game.php?view='.GSETVIEW_FAIRKOMI . $shape_url_suffix . $tmpl_suffix;
   if( @$player_row['RatingStatus'] != RATING_NONE )
      $menu_array[T_('New multi-player-game')] = 'new_game.php?view='.GSETVIEW_MPGAME . $shape_url_suffix . $tmpl_suffix;

   end_page(@$menu_array);
}//main


function add_new_game_form( $form_id, $viewmode, $iamrated, $need_redraw )
{
   $addgame_form = new Form( $form_id, 'add_to_waitingroom.php', FORM_POST );

   if( $need_redraw )
      game_settings_form($addgame_form, GSET_WAITINGROOM, $viewmode, $iamrated, 'redraw', $_REQUEST );
   else
      game_settings_form($addgame_form, GSET_WAITINGROOM, $viewmode, $iamrated);

   $addgame_form->add_row( array( 'SPACE' ) );
   $addgame_form->add_row( array( 'TAB', 'CELL', 1, '', // align submit buttons
         'SUBMITBUTTON', 'add_game', T_('Add Game'),
         'TEXT', span('BigSpace'),
         'SUBMITBUTTON', 'save_template', T_('Save Template'), ));

   $addgame_form->echo_string(1);
} //add_new_game_form

?>
