<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/error_functions.php';



 /*!
  * \class Ruleset
  *
  * \brief Helper-class to define rulesets for DGS and their characteristics.
  *
  * \note RULESET_JAPANESE : (Japanese 1989 rules); simple ko, default komi 6.5, territory-scoring,
  *       seki-points do NOT count, bent-four-in-corner is dead, no pass-stones, no white-moves-last
  *
  * \note RULESET_CHINESE : simple ko, default komi 7.5, area-scoring, seki-points do count, full handicap compensation,
  *       bent-four-in-corner must be played out, no pass-stones, no white-moves-last
  */
class Ruleset
{

   public static function get_default_ruleset()
   {
      $arr = explode('|', ALLOWED_RULESETS);
      return ( count($arr) ) ? $arr[0] : RULESET_JAPANESE;
   }

   public static function getRulesetScoring( $ruleset )
   {
      static $arr = array(
         RULESET_JAPANESE => GSMODE_TERRITORY_SCORING,
         RULESET_CHINESE  => GSMODE_AREA_SCORING,
      );
      return $arr[$ruleset];
   }

   /*!
    * \brief Returns by how much points handicap is changed on scoring.
    * \return  0 = full handicap-compensation,
    *         -1 = full handicap - 1,
    *         null = NO compensation at all
    *
    * \see GameScore.calculate_score() for usage
    *
    * \note use full handicap-compensation as DGS-choice for chinese ruleset (with area scoring).
    *       If later AGA will be introduced, this needs to be differed in the ruleset.
    *       see http://www.dragongoserver.net/forum/read.php?forum=4&thread=32466#36522
    */
   public static function getRulesetHandicapCompensation( $ruleset )
   {
      static $arr = array(
         RULESET_JAPANESE => 0,
         RULESET_CHINESE  => 0,
      );
      return $arr[$ruleset];
   }

   public static function getRulesetDefaultKomi( $ruleset )
   {
      // NOTE: express komi relative to STONE_VALUE (see also suggest_proper() and suggest_conventional())
      switch ( (string)$ruleset )
      {
         case RULESET_JAPANESE:
            return STONE_VALUE / 2.0; // 6.5
         case RULESET_CHINESE:
            return STONE_VALUE / 2.0 + 1; // 7.5
         default:
            error('invalid_args', "Ruleset:getRulesetDefaultKomi.bad_ruleset($ruleset)");
      }
   }//getRulesetDefaultKomi


   public static function getRulesetText( $ruleset=null )
   {
      static $ARR_RULESET = null; // ruleset => text

      // lazy-init of texts
      if ( is_null($ARR_RULESET) )
      {
         $arr = array();
         if ( preg_match( "/^(".ALLOWED_RULESETS.")$/", RULESET_JAPANESE) )
            $arr[RULESET_JAPANESE] = T_('Japanese#ruleset');
         if ( preg_match( "/^(".ALLOWED_RULESETS.")$/", RULESET_CHINESE) )
            $arr[RULESET_CHINESE] = T_('Chinese#ruleset');
         if ( count($arr) == 0 )
            error('internal_error', "Ruleset:getRulesetText.bad_config.must_not_be_empty(ALLOWED_RULESETS)");
         $ARR_RULESET = $arr;
      }

      if ( is_null($ruleset) )
         return $ARR_RULESET;
      if ( !isset($ARR_RULESET[$ruleset]) )
         error('invalid_args', "Ruleset:getRulesetText($ruleset)");
      return $ARR_RULESET[$ruleset];
   }//getRulesetText

   public static function build_ruleset_filter_array( $prefix='' )
   {
      $arr = array( T_('All') => '' );
      $arr_rulesets = self::getRulesetText();
      foreach ( $arr_rulesets as $ruleset => $tmp )
         $arr[self::getRulesetText($ruleset)] = "{$prefix}Ruleset='$ruleset'";
      return $arr;
   }

   public static function build_ruleset_default_komi_javascript_map()
   {
      $arr = array();
      $arr_rulesets = self::getRulesetText();
      foreach ( $arr_rulesets as $ruleset => $tmp )
         $arr[] = sprintf( "'%s': %s", $ruleset, self::getRulesetDefaultKomi($ruleset));
      return '{ ' . implode(', ', $arr) . ' }';
   }

} //end 'Ruleset'

?>
