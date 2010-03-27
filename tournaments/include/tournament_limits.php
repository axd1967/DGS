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
   var $limit_config; // [ limit-id => array( disable_allowed, min, max ) ]

   function TournamentLimits()
   {
      $this->limit_config = array();
   }

   function setLimits( $limit_id, $disable_allowed, $min, $max )
   {
      $this->limit_config[$limit_id] = array( $disable_allowed, $min, $max );
   }

   function getLimits( $limit_id )
   {
      if( TournamentUtils::isAdmin() ) // T-Admin can set any out-of-limit value
         return false;
      else
         return @$this->limit_config[$limit_id];
   }

   function getMinLimit( $limit_id )
   {
      return (int)@$this->limit_config[$limit_id][1];
   }

   function getMaxLimit( $limit_id )
   {
      return (int)@$this->limit_config[$limit_id][2];
   }

   function getLimitRangeText( $limit_id )
   {
      return TournamentUtils::build_range_text( $this->getMinLimit($limit_id), $this->getMaxLimit($limit_id) );
   }

   function getLimitRangeTextAdmin( $limit_id )
   {
      if( TournamentUtils::isAdmin() )
      {
         $limits = $this->limit_config[$limit_id];
         return span('TWarning', sprintf( ' %s: %s',
            T_('Limits'),
            TournamentUtils::build_range_text(
               ($limits[0] ? '0; ' : '' ) . /*min*/$limits[1], /*max*/$limits[2],
               '[%s..%s [..%s]]',
               TournamentLimits::getStaticMaxLimit($limit_id)) ));
      }
      else
         return '';
   }

   function _checkValue_MinMaxDisable( $limit_id, $value, $curr_value, $errtext_disable, $errtext_value )
   {
      $errors = array();
      if( is_numeric($value) && ($value != $curr_value) && ($limits = $this->getLimits($limit_id)) )
      {
         $limit_maxval = TournamentLimits::getStaticMaxLimit($limit_id);
         list( $disable_allowed, $min_value, $max_value ) = $limits;

         if( $value == 0 && !$disable_allowed )
            $errors[] = $errtext_disable;
         elseif(  ( !is_null($min_value) && $value < $min_value )
               || ( !is_null($max_value) && $max_value < $limit_maxval && $value > $max_value ) )
         {
            $errors[] = sprintf( $errtext_value, TournamentUtils::build_range_text($min_value, $max_value) );
         }
      }
      return $errors;
   }


   function check_MaxParticipants( $value, $curr_value )
   {
      return $this->_checkValue_MinMaxDisable(
         TLIMITS_MAX_TP, $value, $curr_value,
         T_('Disabling feature of maximum participants with 0-value not allowed.'),
         T_('Expecting number for maximum participants in range %s.') );
   }


   function checkLadder_MaxDefenses( $value, $curr_value, $group_id=null )
   {
      $errors = array();
      if( is_numeric($value) && ($value != $curr_value) && ($limits = $this->getLimits(TLIMITS_TL_MAX_DF)) )
      {
         $group_label = (is_null($group_id)) ? '' : " $group_id";
         list( $disable_allowed, $min_value, $max_value ) = $limits;
         if( $value == 0 && is_null($group_id) /*&& !$disable_allowed*/ ) // always forbidden for main-group
            $errors[] = T_('Disabling feature of maximum defenses with 0-value not allowed.');
         elseif(  ( !is_null($min_value) && $value < $min_value )
               || ( !is_null($max_value) && $max_value < TLADDER_MAX_DEFENSES && $value > $max_value ) )
         {
            $errors[] = sprintf( T_('Max. defenses%s must be in range %s, but was [%s].'),
               $group_label, TournamentUtils::build_range_text($min_value, $max_value), $value );
         }
      }
      return $errors;
   }

   function checkLadder_MaxChallenges( $value, $curr_value )
   {
      return $this->_checkValue_MinMaxDisable(
         TLIMITS_TL_MAX_CH, $value, $curr_value,
         T_('Disabling feature of max. outgoing challenges with 0-value not allowed.'),
         T_('Max. outgoing challenges must be in range %s.') );
   }


   function check_MaxRounds( $value, $curr_value )
   {
      return $this->_checkValue_MinMaxDisable(
         TLIMITS_TRD_MAX_ROUNDS, $value, $curr_value,
         T_('Disabling feature of maximum tournament rounds with 0-value not allowed.'),
         T_('Expecting number for maximum tournament rounds in range %s.') );
   }

   function checkRounds_MinPoolSize( $value, $curr_value )
   {
      return $this->_checkValue_MinMaxDisable(
         TLIMITS_TRD_MIN_POOLSIZE, $value, $curr_value,
         T_('Disabling feature of minimum pool size with 0-value not allowed.'),
         T_('Expecting number for min. pool size in range %s.') );
   }

   function checkRounds_MaxPoolSize( $value, $curr_value )
   {
      return $this->_checkValue_MinMaxDisable(
         TLIMITS_TRD_MAX_POOLSIZE, $value, $curr_value,
         T_('Disabling feature of maximum pool size with 0-value not allowed.'),
         T_('Expecting number for max. pool size in range %s.') );
   }

   function checkRounds_MaxPoolCount( $value, $curr_value )
   {
      return $this->_checkValue_MinMaxDisable(
         TLIMITS_TRD_MAX_POOLCOUNT, $value, $curr_value,
         T_('Disabling feature of maximum pool count with 0-value not allowed.'),
         T_('Expecting number for max. pool count in range %s.') );
   }


   // ------------ static functions ----------------------------

   function getStaticMaxLimit( $limit_id )
   {
      static $arr = array(
         TLIMITS_MAX_TP             => TP_MAX_COUNT,
         TLIMITS_TL_MAX_DF          => TLADDER_MAX_DEFENSES,
         TLIMITS_TL_MAX_CH          => TLADDER_MAX_CHALLENGES,
         TLIMITS_TRD_MAX_ROUNDS     => TROUND_MAX_COUNT,
         TLIMITS_TRD_MIN_POOLSIZE   => TROUND_MAX_POOLSIZE,
         TLIMITS_TRD_MAX_POOLSIZE   => TROUND_MAX_POOLSIZE,
         TLIMITS_TRD_MAX_POOLCOUNT  => TROUND_MAX_POOLCOUNT,
      );
      return $arr[$limit_id];
   }

} // end of 'TournamentLimits'

?>
