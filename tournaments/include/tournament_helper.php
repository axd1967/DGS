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
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_rules.php';

 /*!
  * \file tournament_helper.php
  *
  * \brief General functions to support tournament management with db-access.
  */


 /*!
  * \class TournamentHelper
  *
  * \brief Helper-class with mostly static functions to support Tournament management
  *        with db-access combining forces of different tournament-classes.
  */

class TournamentHelper
{

   // ------------ static functions ----------------------------

   /*! \brief Wrapper to TournamentRules.create_game(). */
   function create_game_from_tournament_rules( $tid, $user_ch, $user_df )
   {
      $trules = TournamentRules::load_tournament_rule( $tid );
      if( is_null($trules) )
         error('bad_tournament', "TournamentHelper::create_game_from_tournament_rules.find_trules($tid)");

      $tprops = TournamentProperties::load_tournament_properties( $tid );
      if( is_null($tprops) )
         error('bad_tournament', "TournamentHelper::create_game_from_tournament_rules.find_tprops($tid)");

      return $trules->create_game( $tprops->RatingUseMode, $user_ch, $user_df );
   }

   /*!
    * \brief Returns rating for user from Players/TournamentParticipant-table according to rating-use-mode.
    * \param $user User-object
    * \param $RatingUseMode TournamentProperties.RatingUseMode
    */
   function get_tournament_rating( $tid, $user, $RatingUseMode )
   {
      if( $RatingUseMode == TPROP_RUMODE_CURR_FIX )
         $rating = $user->Rating;
      else //if( $RatingUseMode == TPROP_RUMODE_COPY_CUSTOM || $RatingUseMode == TPROP_RUMODE_COPY_FIX )
      {
         $tp = TournamentParticipant::load_tournament_participant( $tid, $user->ID, 0, false, false );
         if( is_null($tp) )
            error('tournament_participant_unknown',
                  "TournamentHelper::get_tournament_rating($tid,{$user->ID},$RatingUseMode)");
         $rating = $tp->Rating;
      }

      return $rating;
   }

} // end of 'TournamentHelper'

?>
