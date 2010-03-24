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

require_once 'include/connect2mysql.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_extension.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_ladder.php';
require_once 'tournaments/include/tournament_ladder_props.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_round.php';
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
            $tladder_df = new TournamentLadder( $tid, $tgame->Defender_rid, $tgame->Defender_uid );
            $tladder_df->update_incoming_challenges( -1 );

            // decrease TG.ChallengesOut for challenger
            $tladder_ch = new TournamentLadder( $tid, $tgame->Challenger_rid, $tgame->Challenger_uid );
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

            // update TP.Finished/Won/Lost for challenger and defender
            TournamentParticipant::update_game_end_stats( $tid, $tgame->Challenger_rid, $tgame->Score );
            TournamentParticipant::update_game_end_stats( $tid, $tgame->Defender_rid, -$tgame->Score );
         }
      }
      ta_end();

      return $success;
   }//process_tournament_ladder_game_end

   /*! \brief Updates TournamentLadder.Period/History-Rank when rank-update is due, set next update-date. */
   function process_rank_period( $t_ext )
   {
      $tid = $t_ext->tid;
      $tl_props = $this->tcache->load_tournament_ladder_props( 'process_rank_period', $tid);
      if( is_null($tl_props) )
         return false;

      // set next check date at month-start (min-period = 1 month)
      $t_ext->DateValue = TournamentUtils::get_month_start_time( $GLOBALS['NOW'], $tl_props->RankPeriodLength );
      $success = $t_ext->update();

      if( $success )
         $success = TournamentLadder::process_rank_period( $tid );

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

   function load_ladder_absent_users( $iterator=null )
   {
      $qsql = new QuerySQL(
         SQLP_FIELDS,
            'TL.tid', 'TL.rid', 'TL.uid',
            'TLP.UserAbsenceDays AS TLP_UserAbsenceDays',
         SQLP_FROM,
            'Tournament AS T',
            'INNER JOIN TournamentLadderProps AS TLP ON TLP.tid=T.ID',
            'INNER JOIN TournamentLadder AS TL ON TL.tid=T.ID',
            'INNER JOIN Players AS P ON P.ID=TL.uid',
         SQLP_WHERE,
            'TLP.UserAbsenceDays > 0',
            "T.Status='".TOURNEY_STATUS_PLAY."'",
            'P.Lastaccess < NOW() - INTERVAL (TLP.UserAbsenceDays + IF(P.OnVacation>0,P.UseVacation,0)) DAY',
         SQLP_ORDER,
            'TL.tid ASC'
         );

      if( is_null($iterator) )
         $iterator = new ListIterator( 'TournamentHelper::load_ladder_absent_users' );
      $iterator->addQuerySQLMerge( $qsql );
      return TournamentLadder::load_tournament_ladder( $iterator );
   }

   function load_ladder_rank_period_update( $iterator = null )
   {
      global $NOW;

      $qsql = new QuerySQL(
         SQLP_FROM,
            'INNER JOIN Tournament AS T ON T.ID=TE.tid',
         SQLP_WHERE,
            "T.Status='".TOURNEY_STATUS_PLAY."'",
            "TE.Property=".TE_PROP_TLADDER_RANK_PERIOD_UPDATE,
            "TE.DateValue <= FROM_UNIXTIME($NOW)",
         SQLP_ORDER,
            'TE.tid ASC'
         );

      if( is_null($iterator) )
         $iterator = new ListIterator( 'TournamentHelper::load_ladder_rank_period_update' );
      $iterator->addQuerySQLMerge( $qsql );
      return TournamentExtension::load_tournament_extensions( $iterator );
   }

   /*! \brief Adds new tournament-round and updates Tournament.Rounds, returning new TournamentRound-object. */
   function add_new_tournament_round( $tourney )
   {
      ta_begin();
      {//HOT-section to add T-round and updating T-data
         $tround = TournamentRound::add_tournament_round( $tourney->ID );
         if( !is_null($tround) )
            $success = $tourney->update_rounds( 1 );
      }
      ta_end();
      return $tround;
   }

   /*! \brief Deletes tournament-round and updates Tournament.Rounds. */
   function delete_tournament_round( $tourney, $round )
   {
      ta_begin();
      {//HOT-section to remove existing T-round and updating T-data
         $success = TournamentRound::delete_tournament_round( $tourney->ID, $round );
         if( $success )
            $success = $tourney->update_rounds( -1 );
      }
      ta_end();
      return $success;
   }

} // end of 'TournamentHelper'

?>
