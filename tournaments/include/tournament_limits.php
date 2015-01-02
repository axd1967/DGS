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

 /* Author: Jens-Uwe Gaspar */

$TranslateGroups[] = "Tournament";

require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_utils.php';


 /*!
  * \file tournament_limits.php
  *
  * \brief Container and helper class with additional restrictions imposed on tournaments
  */


// limit-IDs
define('TLIMITS_MAX_TP', 'max_tp'); // TournamentProperties.MaxParticipants
define('TLIMITS_TL_MAX_DF', 'tl_max_df'); // TournamentLadderProps.MaxDefenses
define('TLIMITS_TL_MAX_CH', 'tl_max_ch'); // TournamentLadderProps.MaxChallenges
define('TLIMITS_TRD_MAX_ROUNDS', 'trd_max_rounds'); // TournamentRound.Round / Tournament.Rounds
define('TLIMITS_TRD_MIN_POOLSIZE', 'trd_min_poolsize'); // TournamentRound.MinPoolSize
define('TLIMITS_TRD_MAX_POOLSIZE', 'trd_max_poolsize'); // TournamentRound.MaxPoolSize
define('TLIMITS_TRD_MAX_POOLCOUNT', 'trd_max_poolcnt'); // TournamentRound.MaxPoolCount
define('TLIMITS_TRD_TP_MAX_GAMES', 'trd_tp_max_games'); // max. T-games per T-participant per round & pool; 0=unlimited
define('TLIMITS_TRULE_GAME_UNRATED', 'trule_game_unrated'); // TournamentRules.Rated; TLimit.min=max; 0=no-restriction, 1=rated-forbidden

// TournamentProperties.RatingUseMode; TLimit.min=max; values: 0=no-restriction, TLIM_TPR_RUM_NO_COPY_CUSTOM
define('TLIMITS_TPR_RATING_USE_MODE', 'trp_rat_use_mode');
define('TLIM_TPR_RUM_NO_COPY_CUSTOM', 0x01); // forbid-COPY-CUSTOM rating-use-mode



 /*!
  * \class TournamentLimits
  *
  * \brief Container and helper to check for specific restrictions.
  *        This class should not contain generic checks for the default
  *        or absolute min/max-limits on (table-)fields, but should be used
  *        for specific limitations for example for non-Dragon-scoped tourneys.
  */
class TournamentLimits
{
   private $limit_config = array(); // [ limit-id => array( disable_allowed, min, max ) ]

   public function setLimits( $limit_id, $disable_allowed, $min, $max=null )
   {
      $this->limit_config[$limit_id] = array( $disable_allowed, $min, ( is_null($max) ? $min : $max ) );
   }

   public function getLimits( $limit_id )
   {
      if ( TournamentUtils::isAdmin() ) // T-Admin can set any out-of-limit value
         return false;
      else
         return @$this->limit_config[$limit_id];
   }

   public function getMinLimit( $limit_id )
   {
      return (int)@$this->limit_config[$limit_id][1];
   }

   public function getMaxLimit( $limit_id )
   {
      return (int)@$this->limit_config[$limit_id][2];
   }

   /*! \brief Returns text '[min..max]' for given limit-id. */
   public function getLimitRangeText( $limit_id )
   {
      return build_range_text( $this->getMinLimit($limit_id), $this->getMaxLimit($limit_id) );
   }

   /*! \brief Returns text '[0; min..max [..admin-max]]' for given limit-id (the '0' appears if disabling-feature is allowed). */
   public function getLimitRangeTextAdmin( $limit_id )
   {
      if ( TournamentUtils::isAdmin() )
      {
         $limits = $this->limit_config[$limit_id];
         return span('TWarning', sprintf( ' %s: %s',
            T_('Limits'),
            build_range_text(
               ($limits[0] ? '0; ' : '' ) . /*min*/$limits[1], /*max*/$limits[2],
               '[%s..%s [..%s]]',
               self::getStaticMaxLimit($limit_id)) ));
      }
      else
         return '';
   }//getLimitRangeTextAdmin

   /*!
    * \brief Internal helper-func to check min/max-value and if disabling feature is allowed.
    * \internal
    * \param $limit_id what limit to check, choose one of TLIMITS_..
    * \param $value new value to check for validity;
    * \param $curr_value current-value of field to check;
    * \param $errtext_disable error-text (without arguments) that is used if disabling feature by
    *        using 0-value is not allowed.
    * \param $errtext_value error-text (with expected one sprintf-argument to note min/max-range)
    *        is used when new value exceeds the allowed limitations.
    * \return empty array if no errors found; otherwise errors-list
    *
    * \note IMPORTANT NOTE on $value:
    *       tournament-admin can use any value, so it's important,
    *       that the checks in this class are only ADDITIONAL checks.
    *       There must be value checks for the absolute limits somewhere else!!
    *
    * \note IMPORTANT NOTE on $curr_value:
    *       tournament-admin can overwrite limitations for some fields,
    *       so the current-value of a field may be higher than is allowed for a non-tourney-admin.
    *       In this case, the value can NOT be changed by non-admins, otherwise the original
    *       limitations set by tournament-type-specifics are applied on the new values!!
    */
   private function _checkValue_MinMaxDisable( $limit_id, $value, $curr_value, $errtext_disable, $errtext_value )
   {
      $errors = array();
      if ( is_numeric($value) && ($value != $curr_value) && ($limits = $this->getLimits($limit_id)) )
      {
         $limit_maxval = self::getStaticMaxLimit($limit_id);
         list( $disable_allowed, $min_value, $max_value ) = $limits;

         if ( $value == 0 && !$disable_allowed )
            $errors[] = $errtext_disable;
         elseif (  ( !is_null($min_value) && $value < $min_value )
               || ( !is_null($max_value) && $max_value < $limit_maxval && $value > $max_value ) )
         {
            $errors[] = sprintf( $errtext_value, build_range_text($min_value, $max_value) );
         }
      }
      return $errors;
   }//_checkValue_MinMaxDisable


   // ---------- General field checks --------------------

   /*! \brief Returns errors-array for check on min/max/disabling of tournament-max-participants value. */
   public function check_MaxParticipants( $value, $curr_value )
   {
      return $this->_checkValue_MinMaxDisable(
         TLIMITS_MAX_TP, $value, $curr_value,
         sprintf( T_('Disabling feature of %s with 0-value not allowed.#tourney'), T_('maximum participants#tourney') ),
         sprintf( T_('Expecting number for %s in range %%s.'), T_('maximum participants#tourney') ) );
   }

   // ---------- Fields checks for Ladder-tournaments --------------------

   /*! \brief Returns errors-array for check on min/max of the different grouped tournament-ladder-max-defenses value. */
   public function checkLadder_MaxDefenses( $value, $curr_value, $group_id=null )
   {
      $errors = array();
      if ( is_numeric($value) && ($value != $curr_value) && ($limits = $this->getLimits(TLIMITS_TL_MAX_DF)) )
      {
         $group_label = (is_null($group_id)) ? '' : " $group_id";
         list( $disable_allowed, $min_value, $max_value ) = $limits;
         if ( $value == 0 && is_null($group_id) /*&& !$disable_allowed*/ ) // always forbidden for main-group
            $errors[] = sprintf( T_('Disabling feature of %s with 0-value not allowed.#tourney'), T_('Max. Defenses#T_ladder') );
         elseif (  ( !is_null($min_value) && $value < $min_value )
               || ( !is_null($max_value) && $max_value < TLADDER_MAX_DEFENSES && $value > $max_value ) )
         {
            $errors[] = sprintf( T_('Max. defenses%s must be in range %s, but was [%s].#T_ladder'),
               $group_label, build_range_text($min_value, $max_value), $value );
         }
      }
      return $errors;
   }//checkLadder_MaxDefenses

   /*! \brief Returns errors-array for check on min/max/disabling of tournament-ladder-max-challenges value. */
   public function checkLadder_MaxChallenges( $value, $curr_value )
   {
      return $this->_checkValue_MinMaxDisable(
         TLIMITS_TL_MAX_CH, $value, $curr_value,
         sprintf( T_('Disabling feature of %s with 0-value not allowed.#tourney'), T_('Max. outgoing Challenges#T_ladder') ),
         sprintf( T_('Expecting number for %s in range %%s.'), T_('Max. outgoing Challenges#T_ladder') ) );
   }

   // ---------- Fields checks for Round-Robin-tournaments --------------------

   /*! \brief Returns errors-array for check on min/max/disabling of tournament-round-robin-max-rounds value. */
   public function check_MaxRounds( $value, $curr_value )
   {
      return $this->_checkValue_MinMaxDisable(
         TLIMITS_TRD_MAX_ROUNDS, $value, $curr_value,
         sprintf( T_('Disabling feature of %s with 0-value not allowed.#tourney'), T_('maximum tournament rounds') ),
         sprintf( T_('Expecting number for %s in range %%s.'), T_('maximum tournament rounds') ) );
   }

   /*! \brief Returns errors-array for check on min/max/disabling of tournament-round-robin-min-pool-size value. */
   public function checkRounds_MinPoolSize( $value, $curr_value )
   {
      return $this->_checkValue_MinMaxDisable(
         TLIMITS_TRD_MIN_POOLSIZE, $value, $curr_value,
         sprintf( T_('Disabling feature of %s with 0-value not allowed.#tourney'), T_('Min. Pool Size') ),
         sprintf( T_('Expecting number for %s in range %%s.'), T_('Min. Pool Size') ) );
   }

   /*! \brief Returns errors-array for check on min/max/disabling of tournament-round-robin-max-pool-size value. */
   public function checkRounds_MaxPoolSize( $value, $curr_value )
   {
      return $this->_checkValue_MinMaxDisable(
         TLIMITS_TRD_MAX_POOLSIZE, $value, $curr_value,
         sprintf( T_('Disabling feature of %s with 0-value not allowed.#tourney'), T_('Max. Pool Size') ),
         sprintf( T_('Expecting number for %s in range %%s.'), T_('Max. Pool Size') ) );
   }

   /*! \brief Returns errors-array for check on min/max/disabling of tournament-round-robin-max-pool-count value. */
   public function checkRounds_MaxPoolCount( $value, $curr_value )
   {
      return $this->_checkValue_MinMaxDisable(
         TLIMITS_TRD_MAX_POOLCOUNT, $value, $curr_value,
         sprintf( T_('Disabling feature of %s with 0-value not allowed.#tourney'), T_('Max. Pool Count') ),
         sprintf( T_('Expecting number for %s in range %%s.'), T_('Max. Pool Count') ) );
   }


   // ------------ static functions ----------------------------

   /*! \brief Provide the absolute upper limits for known limit-id. */
   private static function getStaticMaxLimit( $limit_id )
   {
      static $arr = array(
         TLIMITS_MAX_TP             => TP_MAX_COUNT,
         TLIMITS_TL_MAX_DF          => TLADDER_MAX_DEFENSES,
         TLIMITS_TL_MAX_CH          => TLADDER_MAX_CHALLENGES,
         TLIMITS_TRD_MAX_ROUNDS     => TROUND_MAX_COUNT,
         TLIMITS_TRD_MIN_POOLSIZE   => TROUND_MAX_POOLSIZE,
         TLIMITS_TRD_MAX_POOLSIZE   => TROUND_MAX_POOLSIZE,
         TLIMITS_TRD_MAX_POOLCOUNT  => TROUND_MAX_POOLCOUNT,
         TLIMITS_TRD_TP_MAX_GAMES   => 0,
         TLIMITS_TRULE_GAME_UNRATED => 0,
         TLIMITS_TPR_RATING_USE_MODE => 0,
      );
      return $arr[$limit_id];
   }

} // end of 'TournamentLimits'

?>
