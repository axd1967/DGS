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

require_once 'include/classlib_user.php';
require_once 'include/game_functions.php';
require_once 'include/std_functions.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_ladder_helper.php';
require_once 'tournaments/include/tournament_log_helper.php';
require_once 'tournaments/include/tournament_result.php';
require_once 'tournaments/include/tournament_round_helper.php';
require_once 'tournaments/include/tournament_rules.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

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

   /*!
    * \brief Processes end of tournament-game.
    *
    * \note caller needs to take care of clearing caches.
    */
   public static function process_tournament_game_end( $tourney, $tgame, $check_only )
   {
      $tid = $tourney->ID;
      if ( $tourney->Type != TOURNEY_TYPE_LADDER && $tourney->Type != TOURNEY_TYPE_ROUND_ROBIN )
         error('invalid_args', "TournamentHelper.process_tournament_game_end($tid,{$tourney->Type},{$tgame->ID})");

      // check if processing needed
      if ( $tourney->Status != TOURNEY_STATUS_PLAY ) // process only PLAY-status
         return false;
      if ( $check_only )
         return true;

      if ( $tourney->Type == TOURNEY_TYPE_LADDER )
         $result = TournamentLadderHelper::process_tournament_ladder_game_end( $tourney, $tgame );
      elseif ( $tourney->Type == TOURNEY_TYPE_ROUND_ROBIN )
         $result = TournamentRoundHelper::process_tournament_round_robin_game_end( $tourney, $tgame );
      else
         $result = false;

      return $result;
   }//process_tournament_game_end

   /*!
    * \brief Returns true if given user can edit tournament,
    *        or if user can admin tournament-game (if tournament-director-flag given).
    * \return false if not allowed; otherwise !false with one of TLOG_TYPE_ADMIN/OWNER/DIRECTOR
    */
   public static function allow_edit_tournaments( $tourney, $uid, $td_flag=0 )
   {
      if ( $uid <= GUESTS_ID_MAX ) // forbidden for guests
         return false;

      // logged-in admin is allowed anything
      if ( TournamentUtils::isAdmin() )
         return TLOG_TYPE_ADMIN;

      if ( is_null($tourney) )
         error('invalid_args', "TournamentHelper:allow_edit_tournaments.check.tney_null($uid,$td_flag)");

      // edit/admin-game allowed for T-owner or TD
      if ( $tourney->Owner_ID == $uid )
         return TLOG_TYPE_OWNER;

      // admin-game allowed for TD with respective right (td_flag)
      if ( TournamentCache::is_cache_tournament_director('TournamentHelper:allow_edit_tournaments', $tourney->ID, $uid, $td_flag) )
         return TLOG_TYPE_DIRECTOR;

      return false;
   }//allow_edit_tournaments

   /*!
    * \brief Wrapper to TournamentRules.create_tournament_games() creating game(s) between two users.
    * \return array of Games.ID (e.g. for DOUBLE-tourney)
    */
   public static function create_games_from_tournament_rules( $tid, $tourney_type, $user_ch, $user_df )
   {
      $trules = TournamentCache::load_cache_tournament_rules( 'TournamentHelper:create_game_from_tournament_rules', $tid );
      $trules->TourneyType = $tourney_type;

      $tprops = TournamentCache::load_cache_tournament_properties( 'TournamentHelper:create_game_from_tournament_rules', $tid );

      // set challenger & defender rating according to rating-use-mode
      $ch_uid = $user_ch->ID;
      $ch_rating = self::get_tournament_rating( $tid, $user_ch, $tprops->RatingUseMode );
      $user_ch->urow['Rating2'] = $ch_rating;

      $df_uid = $user_df->ID;
      $df_rating = self::get_tournament_rating( $tid, $user_df, $tprops->RatingUseMode );
      $user_df->urow['Rating2'] = $df_rating;

      $gids = $trules->create_tournament_games( $user_ch, $user_df );
      return $gids;
   }//create_game_from_tournament_rules

   /*!
    * \brief Returns rating for user from Players/TournamentParticipant-table according to rating-use-mode.
    * \param $user User-object
    * \param $RatingUseMode TournamentProperties.RatingUseMode
    * \param $strict_tp_rating if true, return null if rating is taken from $user-var
    */
   public static function get_tournament_rating( $tid, $user, $RatingUseMode, $strict_tp_rating=false )
   {
      if ( $RatingUseMode == TPROP_RUMODE_CURR_FIX )
         $rating = ($strict_tp_rating) ? null : $user->Rating;
      else //if ( $RatingUseMode == TPROP_RUMODE_COPY_CUSTOM || $RatingUseMode == TPROP_RUMODE_COPY_FIX )
      {
         $tp = TournamentCache::load_cache_tournament_participant( 'TournamentHelper:get_tournament_rating', $tid, $user->ID );
         if ( is_null($tp) )
            error('tournament_participant_unknown', "TournamentHelper:get_tournament_rating($tid,{$user->ID},$RatingUseMode)");
         $rating = $tp->Rating;
      }

      return $rating;
   }//get_tournament_rating

   /*
    * \brief Builds restrictions to check for suitable tournaments to join.
    * \return array of restrictions with category-prefix: [ cat:reason, ... ]
    *         cat : E (error), W (warning), I (invite-only)
    *         E-reasons : STAT (tournament-status), MXG (max-games-check), R (rating),
    *         W-reasons : 30k-9d (rating-range), MXP (max participants), REND (register-end-time),
    *                     FG (min. finished games), RG (min. rated games),
    *         I-reasons : PRIV (private tournament)
    *
    * \note E = ERR  -> T cannot be joined, T not suitable (no-rating, max-game-check, T-status)
    *       W = WARN -> T cannot be joined, only by invite by TD
    *       I = INV  -> T cannot be joined, TP must be invited (no restrictions, if no WARNing)
    *       '' = OK  -> T can be joined, no restrictions
    */
   public static function build_tournament_join_restrictions( $tourney, $maxGamesCheck, $row )
   {
      global $NOW, $player_row;
      $out = array(); // restrictions

      // registration only on allowed tournament-status
      $tstatus = new TournamentStatus( $tourney );
      $ttype = TournamentFactory::getTournament($tourney->WizardType);
      $errors = $tstatus->check_edit_status( $ttype->allow_register_tourney_status, false );
      if ( count($errors) > 0 )
         $out[] = 'E:STAT';

      // registration only if not too much started games
      if ( !$maxGamesCheck->allow_tournament_registration() )
         $out[] = 'E:MXG';

      // registration only with rating
      $user = User::new_from_row( $player_row );
      $user_has_rating = $user->hasRating();
      $rating_use_mode = $row['RatingUseMode'];
      if ( !( $user_has_rating || $rating_use_mode == TPROP_RUMODE_COPY_CUSTOM ) )
         $out[] = 'E:R';

      // registration only with rating in correct range
      if ( $row['UserRated'] == 'Y' )
      {
         if ( !$user_has_rating )
            $out[] = 'E:R';
         elseif ( !$user->matchRating( $row['UserMinRating'], $row['UserMaxRating'] ) )
            $out[] = sprintf('W:%s-%s', echo_rating($row['UserMinRating'],0,0,1,1), echo_rating($row['UserMaxRating'],0,0,1,1) );
      }

      // registration only up to max-participants
      $max_tp = (int)$row['MaxParticipants'];
      if ( $max_tp > 0 && $tourney->RegisteredTP >= $max_tp )
         $out[] = 'W:MXP';

      // registration only till tournament register-end-time
      $reg_endtime = (int)$row['X_RegisterEndTime'];
      if ( $reg_endtime > 0 && $NOW >= $reg_endtime )
         $out[] = 'W:REND';

      // registration only with min. finished games
      $min_fin_games = (int)$row['UserMinGamesFinished'];
      if ( $min_fin_games > 0 && $player_row['Finished'] < $min_fin_games )
         $out[] = 'W:FG';

      // registration only with min. rated games
      $min_rated_games = (int)$row['UserMinGamesRated'];
      if ( $min_rated_games > 0 && $player_row['RatedGames'] < $min_rated_games )
         $out[] = 'W:RG';

      // registration only per invitation
      if ( $tourney->Scope == TOURNEY_SCOPE_PRIVATE )
         $out[] = 'I:PRIV';

      return array_unique($out);
   }//build_tournament_join_restrictions

   /*! \brief Returns array of tournament-status on which it is allowed to view tournament-specific T-data (=ladder/pools). */
   public static function get_view_data_status( $isTD=false )
   {
      static $statuslist_TD   = array( TOURNEY_STATUS_PAIR, TOURNEY_STATUS_PLAY, TOURNEY_STATUS_CLOSED );
      static $statuslist_user = array( TOURNEY_STATUS_PLAY, TOURNEY_STATUS_CLOSED );
      return ($isTD) ? $statuslist_TD : $statuslist_user;
   }

   /*! \brief Finds out games-factor (factor of games per challenge) from various sources for given tournament. */
   public static function determine_games_factor( $tid, $trule=null )
   {
      // load T-rules (need Handicaptype for games-count)
      if ( !($trule instanceof TournamentRules) )
         $trule = TournamentCache::load_cache_tournament_rules( 'TournamentHelper:determine_games_factor', $tid );

      return ( $trule->Handicaptype == TRULE_HANDITYPE_DOUBLE ) ? 2 : 1;
   }//determine_games_factor

   /*! \brief Perform tournament-type-specific checks on tournament-result return errors-array (or empty on success). */
   public static function check_tournament_result( $tourney, $tresult )
   {
      global $NOW;
      $errors = array();

      if ( $tresult->uid <= GUESTS_ID_MAX )
         $errors[] = T_('Missing uid for tournament result.');
      if ( $tresult->rid <= 0 )
         $errors[] = T_('Missing rid (=TP.ID) for tournament result.');

      if ( $tresult->Round < 1 || $tresult->Round > $tourney->Rounds )
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('Tournament Round'),
            build_range_text(1, $tourney->Rounds) );

      if ( $tresult->Type < 1 || $tresult->Type > CHECK_MAX_TRESULTYPE )
         $errors[] = sprintf( T_('Invalid tournament-result-type [%s].'), $tresult->Type );
      else
      {
         if ( ( $tourney->Type == TOURNEY_TYPE_LADDER
                  && $tresult->Type != TRESULTTYPE_TL_KING_OF_THE_HILL && $tresult->Type != TRESULTTYPE_TL_SEQWINS )
            || ( $tourney->Type == TOURNEY_TYPE_ROUND_ROBIN
                  && $tresult->Type != TRESULTTYPE_TRR_POOL_WINNER ) )
         {
            $errors[] = sprintf( T_('Result type [%s] can not be selected for this tournament-type [%s].'),
               TournamentResult::getTypeText($tresult->Type), Tournament::getTypeText($tourney->Type) );
         }

         if ( $tresult->Type != TRESULTTYPE_TL_SEQWINS && $tresult->Result != 0 )
            $errors[] = sprintf( T_('Result value can only be provided for tournament-result-types [%s].'),
               build_text_list( 'TournamentResult::getTypeText', array( TRESULTTYPE_TL_SEQWINS ), ', ' ) );
      }

      if ( $tresult->StartTime > $NOW || $tresult->EndTime > $NOW )
         $errors[] = T_('Start time and End time can not be in the future.#tresult');

      if ( $tresult->Rank <= 0 )
         $errors[] = T_('Missing positive value for tournament result rank.');

      return $errors;
   }//check_tournament_result

   /*! \brief Returns all rating-use-modes allowed for tournament; maybe restricted by given tournament-limits. */
   public static function get_restricted_RatingUseModeTexts( $t_limits, $short=true )
   {
      $arr = TournamentProperties::getRatingUseModeText( null, $short );
      if ( $t_limits->getMinLimit(TLIMITS_TPR_RATING_USE_MODE) & TLIM_TPR_RUM_NO_COPY_CUSTOM )
         unset($arr[TPROP_RUMODE_COPY_CUSTOM]);
      return $arr;
   }

} // end of 'TournamentHelper'

?>
