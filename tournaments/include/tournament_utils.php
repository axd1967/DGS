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

require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/utilities.php';
require_once 'tournaments/include/tournament_globals.php';

 /*!
  * \file tournament_utils.php
  *
  * \brief General utility functions to support tournament management without db-access.
  */


define('TOURNEY_DATEFMT',    'YYYY-MM-DD hh:mm' ); // user-input for parsing
define('DATEFMT_TOURNAMENT', 'Y-m-d H:i'); // for output


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

   function isNumberOrEmpty( $value, $allow_negative=false )
   {
      $rx_sign = ($allow_negative) ? '\\-?' : '';
      return ((string)$value == '') || preg_match( "/^{$rx_sign}\d+$/", $value );
   }

   function getWizardTournamentType( $wizard_type )
   {
      static $arr_map = array(
         TOURNEY_WIZTYPE_DGS_LADDER => TOURNEY_TYPE_LADDER,
      );
      if( !isset($arr_map[$wizard_type]) )
         error('invalid_args', "TournamentUtils.getWizardTournamentType($wizard_type)");
      return $arr_map[$wizard_type];
   }

   function buildErrorListString( $errmsg, $errors, $colspan=0, $safe=true )
   {
      if( count($errors) == 0 )
         return '';

      if( $colspan <= 0 )
         return span('ErrorMsg', ( $errmsg ? "$errmsg:" : '') . "<br>\n* " . implode("<br>\n* ", $errors));
      else
      {
         $out = "\n<ul>";
         foreach( $errors as $err )
            $out .= "<li>" . span('TWarning', ($safe ? make_html_safe($err, 'line') : $err)) . "\n";
         $out .= "</ul>\n";
         $out = span('ErrorMsg', ( $errmsg ? "$errmsg:<br>\n" : '' )) . $out;
         return "<td colspan=\"$colspan\">$out</td>";
      }
   }

   function buildLastchangedBy( $lastchanged, $changed_by )
   {
      return date(DATEFMT_TOURNAMENT, $lastchanged) . MED_SPACING
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

} // end of 'TournamentUtils'

?>
