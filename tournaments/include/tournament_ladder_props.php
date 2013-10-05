<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/globals.php';
require_once 'include/db_classes.php';
require_once 'include/dgs_cache.php';
require_once 'include/std_classes.php';
require_once 'include/utilities.php';
require_once 'include/time_functions.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_globals.php';

 /*!
  * \file tournament_ladder_props.php
  *
  * \brief Functions for ladder-specific tournament properties: tables TournamentLadderProps
  */

// game-end type
define('TGE_NORMAL', 1);
define('TGE_JIGO', 2);
define('TGE_TIMEOUT_WIN', 3);
define('TGE_TIMEOUT_LOSS', 4);

 /*!
  * \class TournamentLadderProps
  *
  * \brief Class to manage TournamentLadderProps-table for ladder-specific properties
  */

global $ENTITY_TOURNAMENT_LADDER_PROPS; //PHP5
$ENTITY_TOURNAMENT_LADDER_PROPS = new Entity( 'TournamentLadderProps',
      FTYPE_PKEY, 'tid',
      FTYPE_CHBY,
      FTYPE_INT,  'tid', 'ChallengeRangeAbsolute', 'ChallengeRangeRelative', 'ChallengeRangeRating',
                  'ChallengeRematchWait',
                  'MaxDefenses', 'MaxDefenses1', 'MaxDefenses2', 'MaxDefensesStart1', 'MaxDefensesStart2',
                  'MaxChallenges', 'UserAbsenceDays', 'RankPeriodLength', 'CrownKingHours',
      FTYPE_DATE, 'Lastchanged', 'CrownKingStart',
      FTYPE_ENUM, 'DetermineChallenger', 'GameEndNormal', 'GameEndJigo', 'GameEndTimeoutWin', 'GameEndTimeoutLoss',
                  'UserJoinOrder'
   );

class TournamentLadderProps
{
   private static $ARR_TLPROPS_TEXTS = array(); // lazy-init in Tournament::get..Text()-funcs: [key][id] => text

   public $tid;
   public $Lastchanged;
   public $ChangedBy;
   public $ChallengeRangeAbsolute;
   public $ChallengeRangeRelative;
   public $ChallengeRangeRating;
   public $ChallengeRematchWaitHours;
   public $MaxDefenses;
   public $MaxDefenses1;
   public $MaxDefenses2;
   public $MaxDefensesStart1;
   public $MaxDefensesStart2;
   public $MaxChallenges;
   public $DetermineChallenger;
   public $GameEndNormal;
   public $GameEndJigo;
   public $GameEndTimeoutWin;
   public $GameEndTimeoutLoss;
   public $UserJoinOrder;
   public $UserAbsenceDays;
   public $RankPeriodLength;
   public $CrownKingHours;
   public $CrownKingStart;

   /*! \brief Constructs TournamentLadderProps-object with specified arguments. */
   public function __construct( $tid=0, $lastchanged=0, $changed_by='',
         $challenge_range_abs=0, $challenge_range_rel=0, $challenge_range_rating=TLADDER_CHRNG_RATING_UNUSED,
         $challenge_rematch_wait_hours=0,
         $max_defenses=0, $max_defenses1=0, $max_defenses2=0, $max_defenses_start1=0, $max_defenses_start2=0,
         $max_challenges=0, $determine_challenger=TLP_DETERMINE_CHALL_GEND,
         $game_end_normal=TGEND_CHALLENGER_ABOVE, $game_end_jigo=TGEND_CHALLENGER_BELOW,
         $game_end_timeout_win=TGEND_DEFENDER_BELOW, $game_end_timeout_loss=TGEND_CHALLENGER_LAST,
         $user_join_order=TLP_JOINORDER_REGTIME,
         $user_absence_days=0, $rank_period_len=1, $crown_king_hours=0, $crown_king_start=0 )
   {
      $this->tid = (int)$tid;
      $this->Lastchanged = (int)$lastchanged;
      $this->ChangedBy = $changed_by;
      $this->ChallengeRangeAbsolute = (int)$challenge_range_abs;
      $this->ChallengeRangeRelative = (int)$challenge_range_rel;
      $this->ChallengeRangeRating = (int)$challenge_range_rating;
      $this->ChallengeRematchWaitHours = (int)$challenge_rematch_wait_hours;
      $this->MaxDefenses = (int)$max_defenses;
      $this->MaxDefenses1 = (int)$max_defenses1;
      $this->MaxDefenses2 = (int)$max_defenses2;
      $this->MaxDefensesStart1 = (int)$max_defenses_start1;
      $this->MaxDefensesStart2 = (int)$max_defenses_start2;
      $this->MaxChallenges = (int)$max_challenges;
      $this->setDetermineChallenger($determine_challenger);
      $this->setGameEndNormal($game_end_normal);
      $this->setGameEndJigo($game_end_jigo);
      $this->setGameEndTimeoutWin($game_end_timeout_win);
      $this->setGameEndTimeoutLoss($game_end_timeout_loss);
      $this->setUserJoinOrder($user_join_order);
      $this->UserAbsenceDays = (int)$user_absence_days;
      $this->RankPeriodLength = limit( (int)$rank_period_len, 1, 255, 1 );
      $this->CrownKingHours = (int)$crown_king_hours;
      $this->CrownKingStart = (int)$crown_king_start;
   }//__construct

   public function to_string()
   {
      return print_r($this, true);
   }

   public function setDetermineChallenger( $determine_challenger )
   {
      if ( !preg_match( "/^(".CHECK_TLP_DETERMINE_CHALL.")$/", $determine_challenger ) )
         error('invalid_args', "TournamentLadderProps.setDetermineChallenger($determine_challenger)");
      $this->DetermineChallenger = $determine_challenger;
   }

   public function setGameEndNormal( $game_end )
   {
      if ( !preg_match( "/^(".CHECK_TGEND_NORMAL.")$/", $game_end ) )
         error('invalid_args', "TournamentLadderProps.setGameEndNormal($game_end)");
      $this->GameEndNormal = $game_end;
   }

   public function setGameEndJigo( $game_end )
   {
      if ( !preg_match( "/^(".CHECK_TGEND_JIGO.")$/", $game_end ) )
         error('invalid_args', "TournamentLadderProps.setGameEndJigo($game_end)");
      $this->GameEndJigo = $game_end;
   }

   public function setGameEndTimeoutWin( $game_end )
   {
      if ( !preg_match( "/^(".CHECK_TGEND_TIMEOUT_WIN.")$/", $game_end ) )
         error('invalid_args', "TournamentLadderProps.setGameEndTimeoutWin($game_end)");
      $this->GameEndTimeoutWin = $game_end;
   }

   public function setGameEndTimeoutLoss( $game_end )
   {
      if ( !preg_match( "/^(".CHECK_TGEND_TIMEOUT_LOSS.")$/", $game_end ) )
         error('invalid_args', "TournamentLadderProps.setGameEndTimeoutLoss($game_end)");
      $this->GameEndTimeoutLoss = $game_end;
   }

   public function setUserJoinOrder( $user_join_order )
   {
      if ( !preg_match( "/^(".CHECK_TLP_JOINORDER.")$/", $user_join_order ) )
         error('invalid_args', "TournamentLadderProps.setUserJoinOrder($user_join_order)");
      $this->UserJoinOrder = $user_join_order;
   }

   /*! \brief Inserts or updates tournament-ladder-props in database. */
   public function persist()
   {
      if ( self::isTournamentLadderProps($tid) )
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   public function insert()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->insert( "TournamentLadderProps.insert(%s)" );
   }

   public function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      $result = $entityData->update( "TournamentLadderProps.update(%s)" );
      self::delete_cache_tournament_ladder_props( 'TournamentLadderProps.update', $this->tid );
      return $result;
   }

   public function delete()
   {
      $entityData = $this->fillEntityData();
      $result = $entityData->delete( "TournamentLadderProps.delete(%s)" );
      self::delete_cache_tournament_ladder_props( 'TournamentLadderProps.delete', $this->tid );
      return $result;
   }

   public function fillEntityData( $data=null )
   {
      if ( is_null($data) )
         $data = $GLOBALS['ENTITY_TOURNAMENT_LADDER_PROPS']->newEntityData();
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'ChangedBy', $this->ChangedBy );
      $data->set_value( 'ChallengeRangeAbsolute', $this->ChallengeRangeAbsolute );
      $data->set_value( 'ChallengeRangeRelative', $this->ChallengeRangeRelative );
      $data->set_value( 'ChallengeRangeRating', $this->ChallengeRangeRating );
      $data->set_value( 'ChallengeRematchWait', $this->ChallengeRematchWaitHours );
      $data->set_value( 'MaxDefenses', $this->MaxDefenses );
      $data->set_value( 'MaxDefenses1', $this->MaxDefenses1 );
      $data->set_value( 'MaxDefenses2', $this->MaxDefenses2 );
      $data->set_value( 'MaxDefensesStart1', $this->MaxDefensesStart1 );
      $data->set_value( 'MaxDefensesStart2', $this->MaxDefensesStart2 );
      $data->set_value( 'MaxChallenges', $this->MaxChallenges );
      $data->set_value( 'DetermineChallenger', $this->DetermineChallenger );
      $data->set_value( 'GameEndNormal', $this->GameEndNormal );
      $data->set_value( 'GameEndJigo', $this->GameEndJigo );
      $data->set_value( 'GameEndTimeoutWin', $this->GameEndTimeoutWin );
      $data->set_value( 'GameEndTimeoutLoss', $this->GameEndTimeoutLoss );
      $data->set_value( 'UserJoinOrder', $this->UserJoinOrder );
      $data->set_value( 'UserAbsenceDays', $this->UserAbsenceDays );
      $data->set_value( 'RankPeriodLength', $this->RankPeriodLength );
      $data->set_value( 'CrownKingHours', $this->CrownKingHours );
      $data->set_value( 'CrownKingStart', $this->CrownKingStart );
      return $data;
   }

   /*! \brief Checks if all ladder-properties are valid; return error-list, empty if ok. */
   public function check_properties()
   {
      $errors = array();

      if ( $this->ChallengeRangeAbsolute == 0 && $this->ChallengeRangeRelative == 0
            && $this->ChallengeRangeRating == TLADDER_CHRNG_RATING_UNUSED )
         $errors[] = T_('There must be at least one USED challenge range configuration.#T_ladder');
      if ( $this->ChallengeRangeRelative < 0 || $this->ChallengeRangeRelative > 100 )
         $errors[] = sprintf( T_('Challenge Range Relative must be in percentage range of %s.#T_ladder'),
            build_range_text(0, 100) );
      if ( $this->ChallengeRangeRating != TLADDER_CHRNG_RATING_UNUSED
            && abs($this->ChallengeRangeRating) > TLADDER_MAX_CHRNG_RATING )
         $errors[] = sprintf( T_('Challenge Range Rating must be in range of %s.#T_ladder'),
            build_range_text( -TLADDER_MAX_CHRNG_RATING, TLADDER_MAX_CHRNG_RATING ) );

      if ( $this->MaxDefenses < 1 || $this->MaxDefenses > TLADDER_MAX_DEFENSES )
         $errors[] = sprintf( T_('Max. defenses for remaining ranks must be in range %s.#T_ladder'),
            build_range_text(1, TLADDER_MAX_DEFENSES) );
      if ( $this->MaxDefenses1 < 0 || $this->MaxDefenses1 > TLADDER_MAX_DEFENSES
            || $this->MaxDefenses2 < 0 || $this->MaxDefenses2 > TLADDER_MAX_DEFENSES )
         $errors[] = sprintf( T_('Max. defenses for groups must be in range %s (0 if not used).#T_ladder'),
            build_range_text(0, TLADDER_MAX_DEFENSES) );
      if ( $this->MaxDefensesStart1 < 0 || $this->MaxDefensesStart2 < 0 )
         $errors[] = T_('Max. defenses start-rank for groups must be 0 (if not used) or >0 otherwise.#T_ladder');
      if ( ($this->MaxDefenses1 > 0 && $this->MaxDefensesStart1 == 0) || ($this->MaxDefenses1 == 0 && $this->MaxDefensesStart1 > 0) )
         $errors[] = sprintf( T_('Max. defenses and start-rank for group #%s must both be 0 (if not used) or both be given.#T_ladder'), 1 );
      if ( ($this->MaxDefenses2 > 0 && $this->MaxDefensesStart2 == 0) || ($this->MaxDefenses2 == 0 && $this->MaxDefensesStart2 > 0) )
         $errors[] = sprintf( T_('Max. defenses and start-rank for group #%s must both be 0 (if not used) or both be given.#T_ladder'), 2 );
      if ( $this->MaxDefenses1 > 0 && ($this->MaxDefenses >= $this->MaxDefenses1) )
         $errors[] = sprintf( T_('Max. defenses for remaining ranks must be < max. defenses for group #%s.#T_ladder'), 1 );
      if ( $this->MaxDefenses2 > 0 && ($this->MaxDefenses >= $this->MaxDefenses2) )
         $errors[] = sprintf( T_('Max. defenses for remaining ranks must be < max. defenses for group #%s.#T_ladder'), 2 );
      if ( $this->MaxDefenses1 > 0 && $this->MaxDefenses2 > 0 && ($this->MaxDefenses2 >= $this->MaxDefenses1) )
         $errors[] = T_('Max. defenses for group #1 must be > max. defenses for group #2.#T_ladder');
      if ( $this->MaxDefensesStart1 > 0 && $this->MaxDefensesStart2 > 0 && ($this->MaxDefensesStart2 <= $this->MaxDefensesStart1) )
         $errors[] = T_('Max. defenses start-rank for group #1 must be < start-rank for group #2.#T_ladder');
      if ( $this->MaxDefenses1 == 0 && $this->MaxDefensesStart1 == 0 && $this->MaxDefenses2 > 0 )
         $errors[] = T_('If one group is unused, it must be group #2.#T_ladder');

      if ( $this->MaxChallenges < 0 || $this->MaxChallenges > TLADDER_MAX_CHALLENGES )
         $errors[] = sprintf( T_('Max. outgoing challenges must be in range %s.#T_ladder'),
            build_range_text(0, TLADDER_MAX_CHALLENGES) );

      if ( $this->UserAbsenceDays < 0 || $this->UserAbsenceDays > 255 )
         $errors[] = sprintf( T_('User absence must be in range %s days.#T_ladder'),
            build_range_text(0, 255) );

      if ( $this->RankPeriodLength < 1 || $this->RankPeriodLength > 255 )
         $errors[] = sprintf( T_('Rank-period length must be in range %s months.#T_ladder'),
            build_range_text(1, 255) );

      if ( $this->CrownKingHours < 0 || $this->RankPeriodLength > 50000 )
         $errors[] = sprintf( T_('Crowning of king time must be in range %s hours.#T_ladder'),
            build_range_text(0, 50000) );
      if ( ($this->CrownKingHours > 0 && $this->CrownKingStart == 0) || ($this->CrownKingHours ==0 && $this->CrownKingStart > 0) )
         $errors[] = T_('For auto-crowning of king you need both settings (hours and check start date).#T_ladder');

      return $errors;
   }//check_properties

   /*! \brief Returns array( header, notes-array ) with this properties in textual form. */
   public function build_notes_props()
   {
      $arr_props = array();

      // challenge-range
      $arr = array( T_('You can challenge the highest from the following ladder positions') );
      if ( $this->ChallengeRangeAbsolute < 0 )
         $arr[] = T_('anyone above your own ladder position');
      elseif ( $this->ChallengeRangeAbsolute > 0 )
         $arr[] = sprintf( T_('%s positions above your own#T_ladder'), $this->ChallengeRangeAbsolute );
      if ( $this->ChallengeRangeRelative > 0 )
         $arr[] = sprintf( T_('%s of the positions above your own position#T_ladder'), $this->ChallengeRangeRelative.'%' );
      if ( $this->ChallengeRangeRating != TLADDER_CHRNG_RATING_UNUSED )
      {
         if ( $this->ChallengeRangeRating < 0 )
            $arr[] = sprintf( T_('%s positions above your position in the ladder ordered by user rating'),
                              abs($this->ChallengeRangeRating) );
         elseif ( $this->ChallengeRangeRating > 0 )
            $arr[] = sprintf( T_('%s positions below your position in the ladder ordered by user rating'),
                              $this->ChallengeRangeRating );
         else //==0
            $arr[] = T_('your position in the ladder ordered by user rating');
      }
      $arr_props[] = $arr;

      // incoming challenges
      $arr = array( T_('The number of incoming challenges for any defender is restricted to#T_ladder') );
      $groups = 3;
      if ( $this->MaxDefenses1 > 0 && $this->MaxDefensesStart1 > 0 )
         $arr[] = sprintf( T_('%s challenges for ranks #%s..%s#T_ladder'),
                           $this->MaxDefenses1, 1, $this->MaxDefensesStart1 );
      else
         --$groups;
      if ( $this->MaxDefenses2 > 0 && $this->MaxDefensesStart2 > 0 )
         $arr[] = sprintf( T_('%s challenges for ranks #%s..%s#T_ladder'),
                           $this->MaxDefenses2, $this->MaxDefensesStart1 + 1, $this->MaxDefensesStart2 );
      else
         --$groups;
      if ( $this->MaxDefenses > 0 )
      {
         if ( $groups > 1 )
            $arr[] = sprintf( T_('%s challenges for remaining ranks#T_ladder'), $this->MaxDefenses );
         else
            $arr[] = sprintf( T_('%s challenges for all ranks#T_ladder'), $this->MaxDefenses );
      }
      $arr_props[] = $arr;

      // outgoing challenges
      if ( $this->MaxChallenges > 0 )
         $arr_props[] = sprintf( T_('The number of outgoing challenges is restricted to %s games.#T_ladder'),
                                 $this->MaxChallenges );
      else
         $arr_props[] = T_('The number of outgoing challenges is not restricted.#T_ladder');

      // challenge-rematch
      if ( $this->ChallengeRematchWaitHours > 0 )
         $arr_props[] = sprintf( T_('Before a rematch with the same user you will have to wait %s.#T_ladder'),
                                 self::echo_rematch_wait($this->ChallengeRematchWaitHours) );

      $arr_props[] = T_('You may only have one running game per opponent.#T_ladder');

      // determine challenger
      if ( $this->DetermineChallenger == TLP_DETERMINE_CHALL_GEND )
         $arr_props[] = T_('The challenger is the player with the lower ladder-position at game-end.');
      else
         $arr_props[] = T_('The challenger is the player with the lower ladder-position at game-start.');

      // game-end handling
      $arr = array( T_('On game-end the following action is performed:#T_ladder') );
      $arr[] = sprintf( '%s: %s', T_('if challenger wins by score or resignation#T_ladder'),
                        self::getGameEndText($this->GameEndNormal) );
      $arr[] = sprintf( '%s: %s', T_('if challenger wins by timeout#T_ladder'),
                        self::getGameEndText($this->GameEndTimeoutWin) );
      $arr[] = sprintf( '%s: %s', T_('if challenger loses by timeout#T_ladder'),
                        self::getGameEndText($this->GameEndTimeoutLoss) );
      $arr[] = sprintf( '%s: %s', T_('on Jigo'), self::getGameEndText($this->GameEndJigo) );
      $arr_props[] = $arr;

      // user-join-order
      $arr_props[] = self::getUserJoinOrderText($this->UserJoinOrder, /*short*/false);

      // user absence handling
      if ( $this->UserAbsenceDays > 0 )
         $arr_props[] = sprintf( T_("The user will be removed from the ladder, if player hasn't accessed DGS\n"
            . 'within the last %d days (excluding vacation).'), $this->UserAbsenceDays );

      $arr_props[] = T_('On user removal or retreat from the ladder') . ":\n"
         . wordwrap( TournamentUtils::get_tournament_ladder_notes_user_removed(), 80 );

      // rank-change period
      $arr_props[] = T_('Length of one rank archiving period#T_ladder') . ': ' .
         ( ($this->RankPeriodLength == 1)
               ? T_('1 month')
               : sprintf( T_('%s months'), $this->RankPeriodLength ) );

      // crowning king
      if ( $this->CrownKingHours > 0 )
         $arr_props[] = sprintf( T_('You will be crowned as "King of the Hill" after keeping the top rank for %s.#T_ladder'),
            TimeFormat::_echo_time( $this->CrownKingHours, 24, TIMEFMT_SHORT|TIMEFMT_ZERO, 0 ) ) . "\n" .
            sprintf( T_('The check for this starts at [%s].#T_ladder'), date(DATE_FMT, $this->CrownKingStart) );

      return array( T_('The ladder is configured with the following properties') . ':', $arr_props );
   }//build_notes_props

   /*!
    * \brief Enhances ladder with additional info/data (Challenge-range): TL.AllowChange, TL.MaxChallengedIn.
    * \param $iterator ListIterator on ordered TournamentLadder with iterator-Index on Rank & uid
    * \return (modified) TournamentLadder of given user or null if not in ladder.
    */
   public function fill_ladder_challenge_range( &$iterator, $uid )
   {
      list( $tl_user, $tl_user_orow ) = $iterator->getIndexValue( 'uid', $uid );
      if ( is_null($tl_user) )
         return null;

      // check max-challenges
      if ( $this->MaxChallenges > 0 && $tl_user->ChallengesOut >= $this->MaxChallenges )
         $tl_user->MaxChallengedOut = true;

      // highest challenge-rank
      $user_rank = $tl_user->Rank;
      $tl_user_rating_pos = $tl_user->RatingPos =
         $this->determine_ladder_rating_pos( $iterator, @$tl_user_orow['TLP_Rating2'] );
      $high_rank = $this->calc_highest_challenge_rank( $user_rank, $tl_user_rating_pos );
      for ( $pos=$user_rank; $pos >= $high_rank; $pos-- )
      {
         $tladder = $iterator->getIndexValue( 'Rank', $pos, 0 );
         if ( is_null($tladder) )
            continue;

         if ( $tladder->Rank >= $high_rank && $tladder->Rank < $user_rank ) // chk again (race-cond)
         {
            if ( $tladder->ChallengesIn < $this->calc_max_defenses($tladder->Rank) ) // check max-defenses
               $tladder->AllowChallenge = true;
            else
               $tladder->MaxChallengedIn = true;
         }
      }

      return $tl_user;
   }//fill_ladder_challenge_range

   /*!
    * \brief Enhances ladder with additional info/data for defenders (incoming challenge-games):
    * \param $iterator ListIterator on ordered TournamentLadder with iterator-Index on uid
    * \param $tgame_iterator ListIterator on TournamentGames
    * \return array( TG_STATUS_... => count, ... ); not all stati-keys filled
    *
    * \note Must run after fill_ladder_challenge_range()-func
    * \note TournamentLadder.RematchWait is only set for games $my_id challenged!
    */
   public function fill_ladder_running_games( &$iterator, $tgame_iterator, $my_id )
   {
      $arr_counts = array();
      while ( list(,$arr_item) = $tgame_iterator->getListIterator() )
      {
         list( $tgame, $orow ) = $arr_item;

         // identify TLadder of defender and challenger
         $df_tladder = $iterator->getIndexValue( 'uid', $tgame->Defender_uid, 0 );
         if ( !is_null($df_tladder) )
         {
            $tgame->Defender_tladder = $df_tladder;
            if ( $tgame->Challenger_uid == $my_id )
               $df_tladder->AllowChallenge = false;

            $ch_tladder = $iterator->getIndexValue( 'uid', $tgame->Challenger_uid, 0 );
            if ( !is_null($ch_tladder) )
            {
               $tgame->Challenger_tladder = $ch_tladder;
               if ( $tgame->Defender_uid == $my_id )
                  $ch_tladder->AllowChallenge = false;

               if ( $tgame->Status == TG_STATUS_WAIT )
               {
                  if ( $tgame->Challenger_uid == $my_id )
                     $df_tladder->RematchWait = $this->calc_rematch_wait_remaining_hours( $tgame );
                  elseif ( $tgame->Defender_uid == $my_id )
                     $ch_tladder->RematchWait = $this->calc_rematch_wait_remaining_hours( $tgame );
               }
               else
                  $df_tladder->add_incoming_game( $tgame );
            }
            else // no challenger from detached T-game
               $df_tladder->add_incoming_game( $tgame );
         }

         // count TG-status
         if ( !isset($arr_counts[$tgame->Status]) )
            $arr_counts[$tgame->Status] = 1;
         else
            $arr_counts[$tgame->Status]++;
      }

      return $arr_counts;
   }//fill_ladder_running_games

   /*!
    * \brief Returns theoretical position for given rating in ladder ordered by user rating.
    * \param $iterator ListIterator on ordered TournamentLadder, expecting orow-field TLP_Rating2 with user-rating
    * \return ladder-position; or 0 if given rating greater than all ladder-users-ratings
    *
    * \note sync with TournamentLadder::find_ladder_rating_pos()
    * \note purpose of this method is to avoid db-query using pre-loaded iterator on TournamentLadder instead
    */
   public function determine_ladder_rating_pos( $iterator, $rating )
   {
      if ( $this->ChallengeRangeRating == TLADDER_CHRNG_RATING_UNUSED || (string)$rating == '' || (int)$rating <= NO_RATING )
         return 0;

      $cnt_higher = 0; // count of ladder-users with rating >= given-rating
      while ( list(,$arr_item) = $iterator->getListIterator() )
      {
         $orow = $arr_item[1];
         if ( isset($orow['TLP_Rating2']) )
         {
            $tl_rating = $orow['TLP_Rating2'];
            if ( $tl_rating > NO_RATING && $rating <= $tl_rating )
               ++$cnt_higher;
         }
      }
      $iterator->resetListIterator();

      return $cnt_higher;
   }//determine_ladder_rating_pos

   /*!
    * \brief Returns highest allowed challenge rank.
    * \param $ch_rank challenger-rank
    * \note wording: 1 is "higher" than 2 in ladder.
    */
   public function calc_highest_challenge_rank( $ch_rank, $rating_pos=0 )
   {
      if ( $this->ChallengeRangeAbsolute < 0 )
         $abs_high_rank = 1;
      elseif ( $this->ChallengeRangeAbsolute > 0 )
         $abs_high_rank = $ch_rank - $this->ChallengeRangeAbsolute;
      else
         $abs_high_rank = $ch_rank;

      if ( $this->ChallengeRangeRelative > 0 )
      {
         $rel_rank = $ch_rank - round( $ch_rank * $this->ChallengeRangeRelative / 100 );
         $rel_high_rank = ( $rel_rank > 0 ) ? $rel_rank : 1;
      }
      else
         $rel_high_rank = $ch_rank;

      if ( $this->ChallengeRangeRating != TLADDER_CHRNG_RATING_UNUSED && $rating_pos > 0 )
         $rating_high_rank = $rating_pos + $this->ChallengeRangeRating;
      else
         $rating_high_rank = $ch_rank;

      return min( $abs_high_rank, $rel_high_rank, $rating_high_rank );
   }//calc_highest_challenge_rank

   /*! \brief Returns non-0 number of max. defenses for given ladder-rank. */
   public function calc_max_defenses( $rank )
   {
      if ( $this->MaxDefenses1 > 0 && $rank <= $this->MaxDefensesStart1 )
         $max_defenses = $this->MaxDefenses1;
      elseif ( $this->MaxDefenses2 > 0 && $rank <= $this->MaxDefensesStart2 )
         $max_defenses = $this->MaxDefenses2;
      else
         $max_defenses = $this->MaxDefenses;
      return $max_defenses;
   }

   /*!
    * \brief Verifies challenge to defender; return errors-list or empty if ok.
    * \param $tladder_ch TournamentLadder from challenger
    * \param $tladder_df TournamentLadder from defender
    */
   public function verify_challenge( $tladder_ch, $tladder_df, $rating_pos )
   {
      $errors = array();

      if ( $tladder_ch->rid == $tladder_df->rid )
         error('invalid_args', "TournamentLadderProps.verify_challenge.check_rid_same({$this->tid},{$tladder_ch->rid})");

      $tgame = TournamentGames::load_tournament_game_by_pair_uid( $tladder_ch->tid, $tladder_ch->uid, $tladder_df->uid );
      if ( !is_null($tgame) )
      {
         if ( $tgame->Status == TG_STATUS_WAIT )
         {
            // should normally not be reached by link from ladder-view, so don't calc remaing-hours
            $errors[] = sprintf( T_('Before a rematch with the same user you will have to wait %s.#T_ladder'),
                                 self::echo_rematch_wait($this->ChallengeRematchWaitHours) );
         }
         elseif ( $tgame->Status != TG_STATUS_DONE )
            $errors[] = T_('You may only have one running game per opponent.');
      }

      // check rank-range
      $high_rank = $this->calc_highest_challenge_rank( $tladder_ch->Rank, $rating_pos );
      if ( $tladder_ch->Rank < $tladder_df->Rank )
         $errors[] = T_('You may only challenge users above your own position.#T_ladder');
      if ( $tladder_df->Rank < $high_rank )
         $errors[] = sprintf( T_('Defender rank #%s is out of your rank challenge range [#%s..#%s].#T_ladder'),
                              $tladder_df->Rank, $high_rank, $tladder_ch->Rank - 1 );

      // check defenders max. defenses
      if ( $tladder_df->ChallengesIn >= $this->calc_max_defenses($tladder_df->Rank) )
         $errors[] = sprintf( T_('Defender already has max. %s incoming challenges.#T_ladder'), $tladder_df->ChallengesIn );

      // check challengers max. outgoing challenges
      if ( $tladder_ch->ChallengesOut >= $this->MaxChallenges )
         $errors[] = sprintf( T_('Challenger already has max. %s outgoing challenges.#T_ladder'), $tladder_ch->ChallengesOut );

      return $errors;
   }//verify_challenge

   /*!
    * \brief Checks tournament-game-score and returns corresponding ladder-action for it.
    * \param $score tournament-game score, see also 'specs/db/table-Tournaments.txt'
    * \param $tgame_flags TournamentGames.Flags, see also 'specs/db/table-Tournaments.txt'
    * \return TGEND_NO_CHANGE if no action needed; otherwise TGEND_..-action
    * \internal
    */
   public function calc_game_end_action( $score, $tgame_flags )
   {
      $action = TGEND_NO_CHANGE;

      // Score only has meaning if game is not detached
      if ( !($tgame_flags & TG_FLAG_GAME_DETACHED) )
      {
         if ( $tgame_flags & TG_FLAG_CH_DF_SWITCHED )
            $score = -$score; // role of challenger and defender is reversed

         if ( $score == -SCORE_TIME ) // game timeout (challenger won)
            $action = $this->GameEndTimeoutWin;
         elseif ( $score == SCORE_TIME ) // game timeout (challenger lost)
            $action = $this->GameEndTimeoutLoss;
         elseif ( $score != 0 ) // game score|resignation
         {
            if ( $score < 0 )
               $action = $this->GameEndNormal;
         }
         else // ==0 = jigo
            $action = $this->GameEndJigo;
      }

      return $action;
   }//calc_game_end_action

   /*! \brief Returns TicksDue for rematch-wait, anchored on half-hourly-clock. */
   public function calc_ticks_due_rematch_wait()
   {
      $clock_ticks = get_clock_ticks( "TournamentLadderProps.calc_ticks_due_rematch_wait({$this->tid})",
         CLOCK_TOURNEY_GAME_WAIT );

      $ticks_due = $clock_ticks + TICK_FREQUENCY * $this->ChallengeRematchWaitHours; // 5min-ticks to wait
      return $ticks_due;
   }

   /*! \brief Returns remaining hours to wait for rematch. */
   public function calc_rematch_wait_remaining_hours( $tgame )
   {
      $wait_ticks = get_clock_ticks( "TournamentLadderProps.calc_rematch_wait_remaining_hours({$this->tid},{$tgame->ID})",
         CLOCK_TOURNEY_GAME_WAIT);

      $remaining_hours = floor(($tgame->TicksDue - $wait_ticks + 1) / TICK_FREQUENCY);
      if ( $remaining_hours < 0 )
         $remaining_hours = 0;
      return $remaining_hours;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of TournamentLadderProps-objects for given tournament-id. */
   public static function build_query_sql( $tid )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_LADDER_PROPS']->newQuerySQL('TLP');
      $qsql->add_part( SQLP_WHERE, "TLP.tid='$tid'" );
      return $qsql;
   }

   /*! \brief Returns TournamentLadderProps-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $tlp = new TournamentLadderProps(
            // from TournamentLadderProps
            @$row['tid'],
            @$row['X_Lastchanged'],
            @$row['ChangedBy'],
            @$row['ChallengeRangeAbsolute'],
            @$row['ChallengeRangeRelative'],
            @$row['ChallengeRangeRating'],
            @$row['ChallengeRematchWait'],
            @$row['MaxDefenses'],
            @$row['MaxDefenses1'],
            @$row['MaxDefenses2'],
            @$row['MaxDefensesStart1'],
            @$row['MaxDefensesStart2'],
            @$row['MaxChallenges'],
            @$row['DetermineChallenger'],
            @$row['GameEndNormal'],
            @$row['GameEndJigo'],
            @$row['GameEndTimeoutWin'],
            @$row['GameEndTimeoutLoss'],
            @$row['UserJoinOrder'],
            @$row['UserAbsenceDays'],
            @$row['RankPeriodLength'],
            @$row['CrownKingHours'],
            @$row['X_CrownKingStart']
         );
      return $tlp;
   }//new_from_row

   /*! \brief Checks, if tournament ladder-props existing for given tournament. */
   public static function isTournamentLadderProps( $tid )
   {
      return (bool)mysql_single_fetch( "TournamentLadderProps:isTournamentLadderProps($tid)",
         "SELECT 1 FROM TournamentLadderProps WHERE tid='$tid' LIMIT 1" );
   }

   /*!
    * \brief Loads and returns TournamentLadderProps-object for given tournament-ID.
    * \return NULL if nothing found; TournamentLadderProps otherwise
    */
   public static function load_tournament_ladder_props( $tid )
   {
      if ( $tid <=0 )
         return NULL;

      $qsql = self::build_query_sql( $tid );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "TournamentLadderProps:load_tournament_ladder_props($tid)",
         $qsql->get_select() );
      return ($row) ? self::new_from_row($row) : NULL;
   }//load_tournament_ladder_props

   /*! \brief Returns game-end-text, or all game-end-texts for given game-end-type TGE_.. (if game_end=null). */
   public static function getGameEndText( $game_end=null, $game_end_type=TGE_NORMAL )
   {
      static $arr_tgame_end = array(
         // NOTE: all enum-values:
         //       TGEND_NO_CHANGE, TGEND_CHALLENGER_ABOVE, TGEND_CHALLENGER_BELOW, TGEND_SWITCH,
         //       TGEND_DEFENDER_BELOW, TGEND_DEFENDER_LAST, TGEND_DEFENDER_DELETE,
         //       TGEND_CHALLENGER_LAST, TGEND_CHALLENGER_DELETE
         TGE_NORMAL  => array( TGEND_CHALLENGER_ABOVE, TGEND_CHALLENGER_BELOW, TGEND_SWITCH,
                               TGEND_DEFENDER_BELOW, TGEND_DEFENDER_LAST ),
         TGE_JIGO    => array( TGEND_NO_CHANGE, TGEND_CHALLENGER_ABOVE, TGEND_CHALLENGER_BELOW ),
         TGE_TIMEOUT_WIN  => array( TGEND_NO_CHANGE, TGEND_CHALLENGER_ABOVE, TGEND_CHALLENGER_BELOW,
                                    TGEND_SWITCH, TGEND_DEFENDER_BELOW, TGEND_DEFENDER_LAST,
                                    TGEND_DEFENDER_DELETE ),
         TGE_TIMEOUT_LOSS => array( TGEND_NO_CHANGE, TGEND_CHALLENGER_LAST, TGEND_CHALLENGER_DELETE ),
      );

      // lazy-init of texts
      $key = 'TGAMEEND';
      if ( !isset(self::$ARR_TLPROPS_TEXTS[$key]) )
      {
         $arr = array();
         $arr[TGEND_NO_CHANGE]         = T_('No change#TG_end');
         $arr[TGEND_CHALLENGER_ABOVE]  = T_('Move challenger above defender#TG_end');
         $arr[TGEND_CHALLENGER_BELOW]  = T_('Move challenger below defender#TG_end');
         $arr[TGEND_CHALLENGER_LAST]   = T_('Move challenger to ladder-bottom#TG_end');
         $arr[TGEND_CHALLENGER_DELETE] = T_('Remove challenger from ladder#TG_end');
         $arr[TGEND_SWITCH]            = T_('Switch challenger and defender#TG_end');
         $arr[TGEND_DEFENDER_BELOW]    = T_('Move defender below challenger#TG_end');
         $arr[TGEND_DEFENDER_LAST]     = T_('Move defender to ladder-bottom#TG_end');
         $arr[TGEND_DEFENDER_DELETE]   = T_('Remove defender from ladder#TG_end');
         self::$ARR_TLPROPS_TEXTS[$key] = $arr;
      }

      if ( is_null($game_end) )
      {
         $arr = self::$ARR_TLPROPS_TEXTS[$key]; // clone
         if ( !isset($arr_tgame_end[$game_end_type]) )
            error('invalid_args', "TournamentLadderProps:getGameEndText.check_type($game_end,$game_end_type)");
         $arr_intersect = $arr_tgame_end[$game_end_type];
         if ( is_array($arr_intersect) )
            $arr = array_intersect_key_values( $arr, $arr_intersect );
         return $arr;
      }
      if ( !isset(self::$ARR_TLPROPS_TEXTS[$key][$game_end]) )
         error('invalid_args', "TournamentLadderProps:getGameEndText($game_end)");
      return self::$ARR_TLPROPS_TEXTS[$key][$game_end];
   }//getGameEndText

   public static function getUserJoinOrderText( $join_order=null, $short=true )
   {
      // lazy-init of texts
      $key = 'UJOINORDER';
      if ( !isset(self::$ARR_TLPROPS_TEXTS[$key]) )
      {
         $arr = array();
         $arr[TLP_JOINORDER_REGTIME] = T_('Tournament Registration Time');
         $arr[TLP_JOINORDER_RATING]  = T_('Current User Rating#T_ladder');
         $arr[TLP_JOINORDER_RANDOM]  = T_('Random#T_ladder');
         self::$ARR_TLPROPS_TEXTS[$key] = $arr;

         $arr = array();
         $arr[TLP_JOINORDER_REGTIME] = T_('Add new user in position ordered by tournament registration time (bottom of ladder).#T_ladder');
         $arr[TLP_JOINORDER_RATING]  = T_('Add new user below user with same rating in the ladder ordered by user rating.#T_ladder');
         $arr[TLP_JOINORDER_RANDOM]  = T_('Add new user at random position in the ladder.#T_ladder');
         self::$ARR_TLPROPS_TEXTS[$key.'_LONG'] = $arr;
      }

      if ( !$short )
         $key .= '_LONG';
      if ( is_null($join_order) )
         return array() + self::$ARR_TLPROPS_TEXTS[$key]; // cloned
      if ( !isset(self::$ARR_TLPROPS_TEXTS[$key][$join_order]) )
         error('invalid_args', "TournamentLadderProps:getUserJoinOrderText($join_order,$short)");
      return self::$ARR_TLPROPS_TEXTS[$key][$join_order];
   }//getUserJoinOrderText

   public static function echo_rematch_wait( $hours, $short=false )
   {
      return TimeFormat::_echo_time( $hours, 24, TIMEFMT_ZERO | ($short ? TIMEFMT_SHORT : 0), 0 );
   }

   public static function formatChallengeRangeRating( $ch_range_rating )
   {
      return ($ch_range_rating == TLADDER_CHRNG_RATING_UNUSED) ? '' : $ch_range_rating;
   }

   public static function get_edit_tournament_status()
   {
      static $statuslist = array( TOURNEY_STATUS_NEW );
      return $statuslist;
   }

   public static function delete_cache_tournament_ladder_props( $dbgmsg, $tid )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_TLPROPS, "TLadderProps.$tid" );
   }

} // end of 'TournamentLadderProps'
?>
