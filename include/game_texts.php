<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/globals.php';
require_once 'include/translation_functions.php';


/**
 * \brief Static class with some global game-texts.
 */
class GameText
{
   // ------------ static functions ----------------------------

   function get_game_type( $game_type=null )
   {
      static $ARR_GAME_TYPES = null;
      if( is_null($ARR_GAME_TYPES) )
      {
         $ARR_GAME_TYPES = array(
            GAMETYPE_GO       => T_('Go#gametype'),
            GAMETYPE_TEAM_GO  => T_('Team-Go#gametype'),
            GAMETYPE_ZEN_GO   => T_('Zen-Go#gametype'),
         );
      }
      return is_null($game_type) ? $ARR_GAME_TYPES : @$ARR_GAME_TYPES[$game_type];
   }//get_game_type

   function format_game_type( $game_type, $game_players, $quick=false )
   {
      if( $game_type == GAMETYPE_GO )
         return ($quick) ? $game_type : GameTexts::get_game_type($game_type);
      else
         return ($quick)
            ? "$game_type($game_players)" // see quick-suite
            : sprintf( '%s (%s)', GameTexts::get_game_type($game_type), $game_players );
   }//format_game_type

} //end 'GameText'

?>
