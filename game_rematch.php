<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/db/games.php';
require_once 'include/game_functions.php';
require_once 'include/time_functions.php';
require_once 'include/classlib_user.php';


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];

   $mode = (int) get_request_arg('mode');
   if( $mode != REMATCH_INVITE && $mode != REMATCH_NEWGAME )
      error('invalid_args', "game_rematch.check.mode($mode)");

   $gid = (int) get_request_arg('gid', 0);
   if( $gid < 1 )
      error('unknown_game', "game_rematch.check.game($mode,$gid)");

   // load game
   $game = Games::load_game( $gid );
   if( is_null($game) )
      error('unknown_game', "game_rematch.find_game($mode,$gid)");


   // build URL-values

   $url = array( 'rematch' => 1 ); // field => value
   if( $game->GameType != GAMETYPE_GO ) // MPG
   {
      if( @$player_row['RatingStatus'] == RATING_NONE )
         error('invalid_args', "game_rematch.check.mpg.rating($mode,$gid,$my_id)");

      $mode = REMATCH_NEWGAME;
      $gs_builder = new GameSetupBuilder( 0, /*gs*/null, $game, /*MPG*/true );
      $gs_builder->fill_new_game_from_game( $url );
   }
   elseif( $game->tid > 0 || $game->Status != GAME_STATUS_INVITED ) // tourney or normal-game
   {
      $game_setup = GameSetup::new_from_game_setup( $game->GameSetup, /*inv*/false, /*null-empty*/true );
      if( is_null($game_setup) ) // no game-setup available -> use only game-info
      {
         $gs_builder = new GameSetupBuilder( $my_id, /*gs*/null, $game );
         if( $mode == REMATCH_NEWGAME )
            $gs_builder->fill_new_game_from_game( $url, /*MPG*/false );
         else //REMATCH_INVITE
            $gs_builder->fill_invite_from_game( $url );
      }
      else // use game-setup
      {
         $gs_builder = new GameSetupBuilder( $my_id, $game_setup, $game );
         if( $mode == REMATCH_NEWGAME )
            $gs_builder->fill_new_game_from_game_setup( $url );
         else //REMATCH_INVITE
            $gs_builder->fill_invite_from_game_setup( $url );
      }
   }
   else
      error('invalid_args', "game_rematch.check.state($mode,$gid)");


   // jump to other page

   if( $mode == REMATCH_INVITE )
      $page = 'message.php?mode=Invite'.URI_AMP;
   else //if( $mode == REMATCH_NEWGAME )
      $page = 'new_game.php?';

   $out = array();
   foreach( $url as $field => $value )
      $out[] = $field . '=' . urlencode($value);

   jump_to($page . implode(URI_AMP, $out));
}//main

?>
