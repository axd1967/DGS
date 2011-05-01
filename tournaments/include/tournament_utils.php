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

 /* Author: Jens-Uwe Gaspar */

$TranslateGroups[] = "Tournament";

require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/utilities.php';
require_once 'tournaments/include/tournament_globals.php';

 /*!
  * \file tournament_utils.php
  *
  * \brief General utility functions to support tournament management without db-access.
  */


 /*!
  * \class TournamentUtils
  *
  * \brief Helper-class with mostly static functions to support Tournament management without db-access.
  */

class TournamentUtils
{

   // ------------ static functions ----------------------------

   /*! \brief Returns true for tournament-admin. */
   function isAdmin()
   {
      global $player_row;
      return ( @$player_row['admin_level'] & ADMIN_TOURNAMENT );
   }

   /*!
    * \brief Checks if current user can create a new tournament.
    * \param $label if set, error with given label-prefix is thrown if create not allowed
    * \return >0 if user can create tournament (1=only-admin-is-allowed, 2=user-is-allowed-too),
    *         0 if user can not create tournament
    */
   function check_create_tournament( $label='' )
   {
      global $player_row;

      if( @$player_row['ID'] <= GUESTS_ID_MAX )
      {
         if( $label )
            error('not_allowed_for_guest', "$label.create.guest");
         return 0;
      }

      if( @$player_row['AdminOptions'] & ADMOPT_DENY_TOURNEY_CREATE )
      {
         if( $label )
            error('tournament_create_denied', "$label.create.denied");
         return 0;
      }

      if( !TournamentUtils::isAdmin() && !ALLOW_TOURNAMENTS_CREATE_BY_USER )
      {
         if( $label )
            error('tournament_create_only_by_admin', "$label.create.admin_only");
         return 0;
      }

      return (ALLOW_TOURNAMENTS_CREATE_BY_USER) ? 2 : 1;
   }//check_create_tournament

   /*!
    * \brief Returns normalized rating within boundaries of
    *        -OUT_OF_RATING < MIN_RATING <= $rating < OUT_OF_RATING.
    */
   function normalizeRating( $rating )
   {
      if( is_null($rating) || !is_numeric($rating)
            || $rating <= -OUT_OF_RATING || $rating >= OUT_OF_RATING )
         return NO_RATING;
      else
         return limit( (double)$rating, MIN_RATING, OUT_OF_RATING-1, NO_RATING );
   }

   function isNumberOrEmpty( $value, $allow_negative=false )
   {
      return isNumber( $value, $allow_negative, /*allow-empty*/true );
   }

   function getWizardTournamentType( $wizard_type )
   {
      static $arr_map = array(
         TOURNEY_WIZTYPE_DGS_LADDER     => TOURNEY_TYPE_LADDER,
         TOURNEY_WIZTYPE_PUBLIC_LADDER  => TOURNEY_TYPE_LADDER,
         TOURNEY_WIZTYPE_PRIVATE_LADDER => TOURNEY_TYPE_LADDER,
         TOURNEY_WIZTYPE_DGS_ROUNDROBIN => TOURNEY_TYPE_ROUND_ROBIN,
      );
      if( !isset($arr_map[$wizard_type]) )
         error('invalid_args', "TournamentUtils.getWizardTournamentType($wizard_type)");
      return $arr_map[$wizard_type];
   }

   function buildLastchangedBy( $lastchanged, $changed_by )
   {
      return date(DATE_FMT, $lastchanged) . MED_SPACING
           . sprintf( T_('( changed by %s )#tourney'), ( $changed_by ? trim($changed_by) : NO_VALUE ) );
   }

   function build_num_range_sql_clause( $field, $min, $max, $prefix_op='' )
   {
      if( $min > 0 && $max > 0 )
      {
         if( $min > $max )
            swap( $min, $max );
         return "$prefix_op $field BETWEEN $min AND $max";
      }
      elseif( $min > 0 )
         return "$prefix_op $field >= $min";
      elseif( $max > 0 )
         return "$prefix_op $field <= $max";
      return '';
   }

   // best_rank=0 (init-value)
   function calc_best_rank( $best_rank, $rank )
   {
      return ($best_rank <= 0) ? $rank : min($best_rank, $rank);
   }

   /*! \brief Show all tournament-flags for admin in given Form-object. */
   function show_tournament_flags( &$tform, $tourney )
   {
      if( TournamentUtils::isAdmin() && $tourney->Flags > 0 )
      {
         $tform->add_row( array(
               'DESCRIPTION', T_('Tournament Flags#tourney'),
               'TEXT',        $tourney->formatFlags(NO_VALUE) ));
      }
   }

   /*!
    * \brief Calculates unix-timestamp for start of month.
    * \param $month_add 0=current month, -1=previous-month
    */
   function get_month_start_time( $gm_time, $month_add=0 )
   {
       $arr = localtime( $gm_time, true);
       return gmmktime( /*hour*/ 1, 0, 0, /*month*/ $arr['tm_mon'] + 1 + $month_add,
                        /*day*/ 1, /*year*/ $arr['tm_year'] + 1900, $arr['tm_isdst'] );
   }

   function calc_pool_count( $user_count, $pool_size )
   {
      if( $pool_size == 0 )
         return 0;
      else
         return floor( ( $user_count + $pool_size - 1 ) / $pool_size );
   }

   /*! Returns number of games that need to be played for a pool of given size: n*(n-1)/2 x games_per_round. */
   function calc_pool_games( $pool_size, $games_per_challenge=1 )
   {
      return $games_per_challenge * floor( $pool_size * ( $pool_size - 1 ) / 2 );
   }

} // end of 'TournamentUtils'

?>
