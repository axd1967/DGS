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
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_ladder.php';
require_once 'tournaments/include/tournament_ladder_props.php';

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
   var $tcache;

   function TournamentHelper()
   {
      $this->tcache = new TournamentCache();
   }

   function process_tournament_game_end( $tourney, $tgame, $check_only )
   {
      $tid = $tourney->ID;
      if( $tourney->Type == TOURNEY_TYPE_LADDER )
         return $this->process_tournament_ladder_game_end( $tourney, $tgame, $check_only );
      elseif( $tourney->Type == TOURNEY_TYPE_ROUND_ROBIN )
         error('invalid_method', "TournamentHelper.process_tournament_game_end($tid,{$tourney->Type},{$tgame->ID})");
      else
         error('invalid_args', "TournamentHelper.process_tournament_game_end($tid,{$tourney->Type},{$tgame->ID})");
   }



   function process_tournament_ladder_game_end( $tourney, $tgame, $check_only )
   {
      // check if processing needed
      if( $tourney->Status != TOURNEY_STATUS_PLAY ) // process only PLAY-status
         return false;
      if( $check_only )
         return true;

      $tid = $tourney->ID;
      $tl_props = $this->tcache->load_tournament_ladder_props( 'process_tournament_ladder_game_end', $tid);
      if( is_null($tl_props) )
         return false;

      // process game-end
      $game_end_action = $tl_props->calc_game_end_action( $tgame->Score );

      ta_begin();
      {//HOT-section to process tournament-game-end
         $success = TournamentLadder::process_game_end( $tid, $tgame, $game_end_action );
         if( $success )
         {
            // decrease TG.ChallengesIn for defender
            $tladder_df = new TournamentLadder( $tid, $tgame->Defender_rid, $tgame->Defender_uid ); // don't load
            $tladder_df->update_incoming_challenges( -1 );

            // decrease TG.ChallengesOut for challenger
            $tladder_ch = new TournamentLadder( $tid, $tgame->Challenger_rid, $tgame->Challenger_uid ); // don't load
            $tladder_ch->update_outgoing_challenges( -1 );

            // tournament-game done
            if( $tl_props->ChallengeRematchWaitHours > 0 )
            {
               $tgame->setStatus(TG_STATUS_WAIT);
               $tgame->TicksDue = $tl_props->calc_ticks_due_rematch_wait( $this->tcache );
            }
            else
               $tgame->setStatus(TG_STATUS_DONE);
            $tgame->update();
         }
      }
      ta_end();

      return $success;
   }


   // ------------ static functions ----------------------------

   /*! \brief Wrapper to TournamentRules.create_game(). */
   function create_game_from_tournament_rules( $tid, $tourney_type, $user_ch, $user_df )
   {
      $trules = TournamentRules::load_tournament_rule( $tid );
      if( is_null($trules) )
         error('bad_tournament', "TournamentHelper::create_game_from_tournament_rules.find_trules($tid)");
      $trules->TourneyType = $tourney_type;

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
