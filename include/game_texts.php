<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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
class GameTexts
{
   // ------------ static functions ----------------------------

   public static function get_game_type( $game_type=null )
   {
      static $ARR_GAME_TYPES = null;
      if ( is_null($ARR_GAME_TYPES) )
      {
         $ARR_GAME_TYPES = array(
            GAMETYPE_GO       => T_('Go#gametype'),
            GAMETYPE_TEAM_GO  => T_('Team-Go#gametype'),
            GAMETYPE_ZEN_GO   => T_('Zen-Go#gametype'),
         );
      }
      return is_null($game_type) ? $ARR_GAME_TYPES : @$ARR_GAME_TYPES[$game_type];
   }//get_game_type

   public static function get_manual_handicap_types( $htype=null )
   {
      static $ARR_GAME_MAN_HTYPES = null;
      if ( is_null($ARR_GAME_MAN_HTYPES) )
      {
         $ARR_GAME_MAN_HTYPES = array(
            HTYPE_NIGIRI      => T_('Nigiri#htman'),
            HTYPE_ALTERNATE   => T_('Alternate#htman'), // only for tournaments
            HTYPE_DOUBLE      => T_('Double#htman'),
            HTYPE_BLACK       => T_('Black#htman'),
            HTYPE_WHITE       => T_('White#htman'),
         );
      }
      return is_null($htype) ? $ARR_GAME_MAN_HTYPES : @$ARR_GAME_MAN_HTYPES[$htype];
   }//get_manual_handicap_types

   public static function format_game_type( $game_type, $game_players, $quick=false )
   {
      if ( $game_type == GAMETYPE_GO )
         return ($quick) ? $game_type : self::get_game_type($game_type);
      else
         return ($quick)
            ? "$game_type($game_players)" // see quick-suite
            : sprintf( '%s (%s)', self::get_game_type($game_type), $game_players );
   }//format_game_type

   public static function build_fairkomi_gametype( $game_status, $rss=false )
   {
      if ( $game_status != GAME_STATUS_KOMI )
         return '';
      elseif ( $rss )
         return ' (' . T_('Fair Komi Negotiation#fairkomi') . ')';
      else
         return MINI_SPACING . span('GameTypeFairKomi', '(' . T_('Fair Komi#fairkomi') . ')');
   }

   public static function get_fair_komi_types( $handicap_type=null, $with_note=false, $my_handle=null, $opp_handle=null )
   {
      $arr = array(
            HTYPE_AUCTION_OPEN   => T_('Open Auction Komi#fairkomi'),
            HTYPE_AUCTION_SECRET => T_('Secret Auction Komi#fairkomi'),
            HTYPE_YOU_KOMI_I_COLOR => T_('You choose Komi, I choose Color#fairkomi'),
            HTYPE_I_KOMI_YOU_COLOR => T_('I choose Komi, You choose Color#fairkomi'),
         );
      $arr2 = array(
            HTYPE_YOU_KOMI_I_COLOR => T_('Divide & Choose#fairkomi'),
            HTYPE_I_KOMI_YOU_COLOR => T_('Divide & Choose#fairkomi'),
         );

      if ( is_null($handicap_type) )
         return $arr;
      elseif ( !isset($arr[$handicap_type]) )
         return '';
      elseif ( is_null($my_handle) || $handicap_type == HTYPE_AUCTION_OPEN || $handicap_type == HTYPE_AUCTION_SECRET )
      {
         if ( $with_note )
         {
            if ( $handicap_type == HTYPE_AUCTION_OPEN )
               $note = T_('Color determined by higher OPEN bid on komi#fk_color');
            elseif ( $handicap_type == HTYPE_AUCTION_SECRET )
               $note = T_('Color determined by higher SECRET bid on komi#fk_color');
         }
         else
            $note = '';

         $result = ( isset($arr2[$handicap_type]) ) ? $arr2[$handicap_type] : $arr[$handicap_type];
         return $result . ($note != '' ? " ($note)" : '');
      }
      elseif ( $handicap_type == HTYPE_YOU_KOMI_I_COLOR )
      {
         if ( is_null($opp_handle) )
            return sprintf( T_('You choose Komi, I (%s) choose Color#fairkomi'), $my_handle );
         else
            return sprintf( T_('You (%s) choose Komi, I (%s) choose Color#fairkomi'), $opp_handle, $my_handle );
      }
      elseif ( $handicap_type == HTYPE_I_KOMI_YOU_COLOR )
      {
         if ( is_null($opp_handle) )
            return sprintf( T_('I (%s) choose Komi, You choose Color#fairkomi'), $my_handle );
         else
            return sprintf( T_('I (%s) choose Komi, You (%s) choose Color#fairkomi'), $my_handle, $opp_handle );
      }
      else
         error('invalid_args', "GameTexts:get_fair_komi_types($handicap_type,$my_handle,$opp_handle)");
   }//get_fair_komi_types

   public static function get_jigo_modes( $jigo_mode=null )
   {
      $arr = array(
         JIGOMODE_KEEP_KOMI  => T_('No Jigo restriction#jigo_mode'),
         JIGOMODE_ALLOW_JIGO => T_('Enforce Jigo#jigo_mode'),
         JIGOMODE_NO_JIGO    => T_('Forbid Jigo#jigo_mode'),
      );
      if ( is_null($jigo_mode) )
         return $arr;
      else
         return (isset($arr[$jigo_mode])) ? $arr[$jigo_mode] : '';
   }//get_jigo_modes

} //end 'GameTexts'

?>
