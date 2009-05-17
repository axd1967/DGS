<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once( 'include/std_functions.php' ); // for ADMIN_TOURNAMENT

 /*!
  * \file tournament_utils.php
  *
  * \brief General functions to support tournament management
  */


define('TOURNEY_DATEFMT',    'YYYY-MM-DD hh:mm' ); // user-input for parsing
define('DATEFMT_TOURNAMENT', 'Y-m-d H:i'); // for output


 /*!
  * \class TournamentUtils
  *
  * \brief Helper-class with mostly static functions to support Tournament management
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

   function formatDate( $date, $defval='', $datefmt=DATEFMT_TOURNAMENT )
   {
      return ($date) ? date($datefmt, $date) : $defval;
   }

   /*!
    * \brief Parses given date-string (expect format TOURNEY_DATEFMT)
    *        into UNIX-timestamp; or return error-string.
    *        Returns 0 if no date-string given.
    */
   function parseDate( $msg, $date_str )
   {
      $result = 0;
      $date_str = trim($date_str);
      if( $date_str != '' )
      {
         if( preg_match( "/^(\d{4})-?(\d+)-?(\d+)(?:\s+(\d+)(?::(\d+)))$/", $date_str, $matches ) )
         {// (Y)=1, (M)=2, (D)=3, (h)=4, (m)=5
            list(, $year, $month, $day, $hour, $min ) = $matches;
            $result = mktime( 0+$hour, 0+$min, 0, 0+$month, 0+$day, 0+$year );
         }
         else
            $result = sprintf( T_('Dateformat of [%s] is wrong, expected [%s] for [%s]'),
               $date_str, TOURNEY_DATEFMT, $msg );
      }
      return $result;
   }

   /*!
    * \brief Returns normalized rating within boundaries of
    *        -OUT_OF_RATING < MIN_RATING <= $rating < OUT_OF_RATING.
    */
   function normalizeRating( $rating )
   {
      if( is_null($rating) || !is_numeric($rating)
            || $rating <= -OUT_OF_RATING || $rating >= OUT_OF_RATING )
         return -OUT_OF_RATING;
      else
         return limit( (double)$rating, MIN_RATING, OUT_OF_RATING-1, -OUT_OF_RATING );
   }

   function isNumberOrEmpty( $value )
   {
      return ((string)$value == '') || preg_match( "/^\d+$/", $value );
   }

} // end of 'TournamentUtils'

?>
