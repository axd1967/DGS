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

require_once 'include/globals.php';
require_once 'include/db_classes.php';
require_once 'include/std_classes.php';
require_once 'include/utilities.php';
require_once 'include/time_functions.php';
require_once 'tournaments/include/tournament_globals.php';

 /*!
  * \file tournament_ladder_props.php
  *
  * \brief Functions for ladder-specific tournament properties: tables TournamentLadderProps
  */

define('TLADDER_MAX_DEFENSES', 20);
define('TLADDER_MAX_CHALLENGES', 200);
define('TLADDER_MAX_WAIT_REMATCH', 3*30*24); // 3 months
define('TLADDER_MAX_CHRNG_RATING', 32767);
define('TLADDER_CHRNG_RATING_UNUSED', -TLADDER_MAX_CHRNG_RATING-1);

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

// lazy-init in Tournament::get..Text()-funcs
global $ARR_GLOBALS_TOURNAMENT_LADDER_PROPS; //PHP5
$ARR_GLOBALS_TOURNAMENT_LADDER_PROPS = array();

global $ENTITY_TOURNAMENT_LADDER_PROPS; //PHP5
$ENTITY_TOURNAMENT_LADDER_PROPS = new Entity( 'TournamentLadderProps',
      FTYPE_PKEY, 'tid',
      FTYPE_CHBY,
      FTYPE_INT,  'tid', 'ChallengeRangeAbsolute', 'ChallengeRangeRelative', 'ChallengeRangeRating',
                  'ChallengeRematchWait',
                  'MaxDefenses', 'MaxDefenses1', 'MaxDefenses2', 'MaxDefensesStart1', 'MaxDefensesStart2',
                  'MaxChallenges', 'UserAbsenceDays',
      FTYPE_DATE, 'Lastchanged',
      FTYPE_ENUM, 'GameEndNormal', 'GameEndJigo', 'GameEndTimeoutWin', 'GameEndTimeoutLoss'
   );

class TournamentLadderProps
{
   var $tid;
   var $Lastchanged;
   var $ChangedBy;
   var $ChallengeRangeAbsolute;
   var $ChallengeRangeRelative;
   var $ChallengeRangeRating;
   var $ChallengeRematchWaitHours;
   var $MaxDefenses;
   var $MaxDefenses1;
   var $MaxDefenses2;
   var $MaxDefensesStart1;
   var $MaxDefensesStart2;
   var $MaxChallenges;
   var $GameEndNormal;
   var $GameEndJigo;
   var $GameEndTimeoutWin;
   var $GameEndTimeoutLoss;
   var $UserAbsenceDays;

   /*! \brief Constructs TournamentLadderProps-object with specified arguments. */
   function TournamentLadderProps( $tid=0, $lastchanged=0, $changed_by='',
         $challenge_range_abs=0, $challenge_range_rel=0, $challenge_range_rating=TLADDER_CHRNG_RATING_UNUSED,
         $challenge_rematch_wait_hours=0,
         $max_defenses=0, $max_defenses1=0, $max_defenses2=0, $max_defenses_start1=0, $max_defenses_start2=0,
         $max_challenges=0,
         $game_end_normal=TGEND_CHALLENGER_ABOVE, $game_end_jigo=TGEND_CHALLENGER_BELOW,
         $game_end_timeout_win=TGEND_DEFENDER_BELOW, $game_end_timeout_loss=TGEND_CHALLENGER_LAST,
         $user_absence_days=0 )
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
      $this->setGameEndNormal($game_end_normal);
      $this->setGameEndJigo($game_end_jigo);
      $this->setGameEndTimeoutWin($game_end_timeout_win);
      $this->setGameEndTimeoutLoss($game_end_timeout_loss);
      $this->UserAbsenceDays = (int)$user_absence_days;
   }

   function to_string()
   {
      return print_r($this, true);
   }

   function setGameEndNormal( $game_end )
   {
      if( !preg_match( "/^(".CHECK_TGEND_NORMAL.")$/", $game_end ) )
         error('invalid_args', "TournamentLadderProps.setGameEndNormal($game_end)");
      $this->GameEndNormal = $game_end;
   }

   function setGameEndJigo( $game_end )
   {
      if( !preg_match( "/^(".CHECK_TGEND_JIGO.")$/", $game_end ) )
         error('invalid_args', "TournamentLadderProps.setGameEndJigo($game_end)");
      $this->GameEndJigo = $game_end;
   }

   function setGameEndTimeoutWin( $game_end )
   {
      if( !preg_match( "/^(".CHECK_TGEND_TIMEOUT_WIN.")$/", $game_end ) )
         error('invalid_args', "TournamentLadderProps.setGameEndTimeoutWin($game_end)");
      $this->GameEndTimeoutWin = $game_end;
   }

   function setGameEndTimeoutLoss( $game_end )
   {
      if( !preg_match( "/^(".CHECK_TGEND_TIMEOUT_LOSS.")$/", $game_end ) )
         error('invalid_args', "TournamentLadderProps.setGameEndTimeoutLoss($game_end)");
      $this->GameEndTimeoutLoss = $game_end;
   }

   /*! \brief Inserts or updates tournament-ladder-props in database. */
   function persist()
   {
      if( TournamentLadderProps::isTournamentLadderProps($tid) )
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   function insert()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->insert( "TournamentLadderProps.insert(%s)" );
   }

   function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->update( "TournamentLadderProps.update(%s)" );
   }

   function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "TournamentLadderProps.delete(%s)" );
   }

   function fillEntityData( &$data=null )
   {
      if( is_null($data) )
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
      $data->set_value( 'GameEndNormal', $this->GameEndNormal );
      $data->set_value( 'GameEndJigo', $this->GameEndJigo );
      $data->set_value( 'GameEndTimeoutWin', $this->GameEndTimeoutWin );
      $data->set_value( 'GameEndTimeoutLoss', $this->GameEndTimeoutLoss );
      $data->set_value( 'UserAbsenceDays', $this->UserAbsenceDays );
      return $data;
   }

   /*! \brief Checks if all ladder-properties are valid; return error-list, empty if ok. */
   function check_properties()
   {
      $errors = array();

      if( $this->ChallengeRangeAbsolute == 0 && $this->ChallengeRangeRelative == 0
            && $this->ChallengeRangeRating == TLADDER_CHRNG_RATING_UNUSED )
         $errors[] = T_('There must be at least one USED challenge range configuration.');
      if( $this->ChallengeRangeRelative < 0 || $this->ChallengeRangeRelative > 100 )
         $errors[] = T_('Challenge Range Relative must be in percentage range of [0..100].');
      if( $this->ChallengeRangeRating != TLADDER_CHRNG_RATING_UNUSED
            && abs($this->ChallengeRangeRating) > TLADDER_MAX_CHRNG_RATING )
         $errors[] = sprintf( T_('Challenge Range Rating must be in range of [%s..%s].'),
                              -TLADDER_MAX_CHRNG_RATING, TLADDER_MAX_CHRNG_RATING );

      if( $this->MaxDefenses <= 0 || $this->MaxDefenses > TLADDER_MAX_DEFENSES )
         $errors[] = sprintf( T_('Max. defenses for remaining ranks must be in range [1..%s].'), TLADDER_MAX_DEFENSES );
      if( $this->MaxDefenses1 < 0 || $this->MaxDefenses1 > TLADDER_MAX_DEFENSES
            || $this->MaxDefenses2 < 0 || $this->MaxDefenses2 > TLADDER_MAX_DEFENSES )
         $errors[] = sprintf( T_('Max. defenses for groups must be in range [0..%s] (0 if not used).'), TLADDER_MAX_DEFENSES );
      if( $this->MaxDefensesStart1 < 0 || $this->MaxDefensesStart2 < 0 )
         $errors[] = T_('Max. defenses start-rank for groups must be 0 (if not used) or >0 otherwise.');
      if( ($this->MaxDefenses1 > 0 && $this->MaxDefensesStart1 == 0) || ($this->MaxDefenses1 == 0 && $this->MaxDefensesStart1 > 0) )
         $errors[] = sprintf( T_('Max. defenses and start-rank for group #%s must both be 0 (if not used) or both be given.'), 1 );
      if( ($this->MaxDefenses2 > 0 && $this->MaxDefensesStart2 == 0) || ($this->MaxDefenses2 == 0 && $this->MaxDefensesStart2 > 0) )
         $errors[] = sprintf( T_('Max. defenses and start-rank for group #%s must both be 0 (if not used) or both be given.'), 2 );
      if( $this->MaxDefenses1 > 0 && ($this->MaxDefenses >= $this->MaxDefenses1) )
         $errors[] = sprintf( T_('Max. defenses for remaining ranks must be < max. defenses for group #%s.'), 1 );
      if( $this->MaxDefenses2 > 0 && ($this->MaxDefenses >= $this->MaxDefenses2) )
         $errors[] = sprintf( T_('Max. defenses for remaining ranks must be < max. defenses for group #%s.'), 2 );
      if( $this->MaxDefenses1 > 0 && $this->MaxDefenses2 > 0 && ($this->MaxDefenses2 >= $this->MaxDefenses1) )
         $errors[] = T_('Max. defenses for group #1 must be > max. defenses for group #2.');
      if( $this->MaxDefensesStart1 > 0 && $this->MaxDefensesStart2 > 0 && ($this->MaxDefensesStart2 <= $this->MaxDefensesStart1) )
         $errors[] = T_('Max. defenses start-rank for group #1 must be < start-rank for group #2.');
      if( $this->MaxDefenses1 == 0 && $this->MaxDefensesStart1 == 0 && $this->MaxDefenses2 > 0 )
         $errors[] = T_('If one group is unused, it must be group #2.');

      if( $this->MaxChallenges < 0 || $this->MaxChallenges > TLADDER_MAX_CHALLENGES )
         $errors[] = sprintf( T_('Max. outgoing challenges must be in range [0..%s].'), TLADDER_MAX_CHALLENGES );

      if( $this->UserAbsenceDays < 0 || $this->UserAbsenceDays > 255 )
         $errors[] = sprintf( T_('User absence must be in range [0..%s] days.'), 255 );

      return $errors;
   }//check_properties

   /*! \brief Returns array( header, notes-array ) with this properties in textual form. */
   function build_notes_props()
   {
      $arr_props = array();

      // challenge-range
      $arr = array( T_('You can challenge the highest ladder position matching the following conditions') );
      if( $this->ChallengeRangeAbsolute < 0 )
         $arr[] = T_('anyone above your own ladder position');
      elseif( $this->ChallengeRangeAbsolute > 0 )
         $arr[] = sprintf( T_('%s positions above your own'), $this->ChallengeRangeAbsolute );
      if( $this->ChallengeRangeRelative > 0 )
         $arr[] = sprintf( T_('%s of ladder users above your own position'), $this->ChallengeRangeRelative.'%' );
      if( $this->ChallengeRangeRating != TLADDER_CHRNG_RATING_UNUSED )
      {
         if( $this->ChallengeRangeRating < 0 )
            $arr[] = sprintf( T_('%s positions above your theoretical position ordered by user rating'),
                              abs($this->ChallengeRangeRating) );
         elseif( $this->ChallengeRangeRating > 0 )
            $arr[] = sprintf( T_('%s positions below your theoretical position ordered by user rating'),
                              $this->ChallengeRangeRating );
         else //==0
            $arr[] = T_('your theoretical position ordered by user rating');
      }
      $arr_props[] = $arr;

      // incoming challenges
      $arr = array( T_('The number of incoming challenges for any defender is restricted to') );
      $groups = 3;
      if( $this->MaxDefenses1 > 0 && $this->MaxDefensesStart1 > 0 )
         $arr[] = sprintf( T_('%s challenges for ranks #1..%s'),
                           $this->MaxDefenses1, $this->MaxDefensesStart1 );
      else
         --$groups;
      if( $this->MaxDefenses2 > 0 && $this->MaxDefensesStart2 > 0 )
         $arr[] = sprintf( T_('%s challenges for ranks #%s..%s'),
                           $this->MaxDefenses2, $this->MaxDefensesStart1 + 1, $this->MaxDefensesStart2 );
      else
         --$groups;
      if( $this->MaxDefenses > 0 )
      {
         if( $groups > 1 )
            $arr[] = sprintf( T_('%s challenges for remaining ranks'), $this->MaxDefenses );
         else
            $arr[] = sprintf( T_('%s challenges for all ranks'), $this->MaxDefenses );
      }
      $arr_props[] = $arr;

      // outgoing challenges
      if( $this->MaxChallenges > 0 )
         $arr_props[] = sprintf( T_('The number of outgoing challenges is restricted to %s games.'),
                                 $this->MaxChallenges );

      // challenge-rematch
      if( $this->ChallengeRematchWaitHours > 0 )
         $arr_props[] = sprintf( T_('Before a rematch with the same user you will have to wait %s.'),
                                 TournamentLadderProps::echo_rematch_wait($this->ChallengeRematchWaitHours) );

      // general conditions
      $arr_props[] = T_('You may only have one running game per opponent.');
      $arr_props[] = T_("On user removal or retreat from the ladder, the running tournament games\n"
         . "will be continued as normal games without further effect to the tournament.");

      // game-end handling
      $arr = array( T_('On game-end the following action is performed') );
      $arr[] = sprintf( '%s: %s', T_('if challengers wins by score or resignation'),
                        TournamentLadderProps::getGameEndText($this->GameEndNormal) );
      $arr[] = sprintf( '%s: %s', T_('if challenger wins by timeout'),
                        TournamentLadderProps::getGameEndText($this->GameEndTimeoutWin) );
      $arr[] = sprintf( '%s: %s', T_('if challenger loses by timeout'),
                        TournamentLadderProps::getGameEndText($this->GameEndTimeoutLoss) );
      $arr[] = sprintf( '%s: %s', T_('on Jigo'), TournamentLadderProps::getGameEndText($this->GameEndJigo) );
      $arr_props[] = $arr;

      // user absence handling
      if( $this->UserAbsenceDays > 0 )
         $arr_props[] = sprintf( T_("If player haven't accessed DGS within the last %d days (excluding vacation)\n"
            . 'the user will be removed from ladder.'), $this->UserAbsenceDays );

      return array( T_('The ladder is configured with the following properties') . ':', $arr_props );
   }//build_notes_props

   /*!
    * \brief Enhances ladder with additional info/data (Challenge-range).
    * \param $iterator ListIterator on ordered TournamentLadder with iterator-Index on Rank & uid
    * \return TournamentLadder of given user or null if not in ladder.
    */
   function fill_ladder_challenge_range( &$iterator, $uid )
   {
      list( $tl_user, $tl_user_orow ) = $iterator->getIndexValue( 'uid', $uid );
      if( is_null($tl_user) )
         return null;

      // check max-challenges
      if( $this->MaxChallenges > 0 && $tl_user->ChallengesOut >= $this->MaxChallenges )
         $tl_user->MaxChallengedOut = true;

      // highest challenge-rank
      $user_rank = $tl_user->Rank;
      $user_rating_tl_pos = $this->find_ladder_rating_pos( $iterator, @$tl_user_orow['TLP_Rating2'] );
      $high_rank = $this->calc_highest_challenge_rank( $user_rank, $user_rating_tl_pos );
      for( $pos=$user_rank; $pos >= $high_rank; $pos-- )
      {
         $tladder = $iterator->getIndexValue( 'Rank', $pos, 0 );
         if( is_null($tladder) )
            continue;

         if( $tladder->Rank >= $high_rank && $tladder->Rank < $user_rank ) // chk again (race-cond)
         {
            if( $tladder->ChallengesIn < $this->calc_max_defenses($tladder->Rank) ) // check max-defenses
               $tladder->AllowChallenge = true;
            else
               $tladder->MaxChallengedIn = true;
         }
      }

      return $tl_user;
   }

   /*!
    * \brief Enhances ladder with additional info/data (incoming challenge-games).
    * \note Must run after fill_ladder_challenge_range()-func
    * \param $iterator ListIterator on ordered TournamentLadder with iterator-Index on uid
    * \param $tgame_iterator ListIterator on TournamentGames
    */
   function fill_ladder_running_games( &$tcache, &$iterator, $tgame_iterator, $my_id )
   {
      while( list(,$arr_item) = $tgame_iterator->getListIterator() )
      {
         list( $tgame, $orow ) = $arr_item;

         // identify TLadder of defender and challenger
         $df_tladder = $iterator->getIndexValue( 'uid', $tgame->Defender_uid, 0 );
         if( !is_null($df_tladder) )
         {
            if( $tgame->Challenger_uid == $my_id )
               $df_tladder->AllowChallenge = false;

            $ch_tladder = $iterator->getIndexValue( 'uid', $tgame->Challenger_uid, 0 );
            if( !is_null($ch_tladder) )
            {
               $tgame->Defender_tladder = $df_tladder;
               $tgame->Challenger_tladder = $ch_tladder;
               if( $tgame->Status == TG_STATUS_WAIT )
                  $df_tladder->RematchWait = $this->calc_rematch_wait_remaining_hours( $tcache, $tgame );
               else
                  $df_tladder->add_running_game( $tgame );
            }
         }
      }
   }

   /*!
    * \brief Returns theoretical position for given rating in ladder ordered by user rating.
    * \param $iterator ListIterator on ordered TournamentLadder, expecting orow-field TLP_Rating2 with user-rating
    * \return ladder-position; or 0 if given rating greater than all ladder-users-ratings
    */
   function find_ladder_rating_pos( $iterator, $rating )
   {
      if( $this->ChallengeRangeRating == TLADDER_CHRNG_RATING_UNUSED
            || (string)$rating == '' || (int)$rating <= -OUT_OF_RATING )
         return 0;

      $cnt_higher = 0; // count of ladder-users with rating >= given-rating
      while( list(,$arr_item) = $iterator->getListIterator() )
      {
         $orow = $arr_item[1];
         if( isset($orow['TLP_Rating2']) )
         {
            $tl_rating = $orow['TLP_Rating2'];
            if( $tl_rating > -OUT_OF_RATING && $rating <= $tl_rating )
               ++$cnt_higher;
         }
      }
      $iterator->resetListIterator();

      return $cnt_higher;
   }

   /*!
    * \brief Returns highest allowed challenge rank.
    * \param $ch_rank challenger-rank
    * \note wording: 1 is "higher" than 2 in ladder.
    */
   function calc_highest_challenge_rank( $ch_rank, $rating_pos=0 )
   {
      if( $this->ChallengeRangeAbsolute < 0 )
         $abs_high_rank = 1;
      elseif( $this->ChallengeRangeAbsolute > 0 )
         $abs_high_rank = $ch_rank - $this->ChallengeRangeAbsolute;
      else
         $abs_high_rank = $ch_rank;

      if( $this->ChallengeRangeRelative > 0 )
      {
         $rel_rank = $ch_rank - round( $ch_rank * $this->ChallengeRangeRelative / 100 );
         $rel_high_rank = ( $rel_rank > 0 ) ? $rel_rank : 1;
      }
      else
         $rel_high_rank = $ch_rank;

      if( $this->ChallengeRangeRating != TLADDER_CHRNG_RATING_UNUSED && $rating_pos > 0 )
         $rating_high_rank = $rating_pos + $this->ChallengeRangeRating;
      else
         $rating_high_rank = $ch_rank;

      return min( $abs_high_rank, $rel_high_rank, $rating_high_rank );
   }

   /*! \brief Returns non-0 number of max. defenses for given ladder-rank. */
   function calc_max_defenses( $rank )
   {
      if( $this->MaxDefenses1 > 0 && $rank <= $this->MaxDefensesStart1 )
         $max_defenses = $this->MaxDefenses1;
      elseif( $this->MaxDefenses2 > 0 && $rank <= $this->MaxDefensesStart2 )
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
   function verify_challenge( $tladder_ch, $tladder_df, $rating_pos )
   {
      $errors = array();

      if( $tladder_ch->rid == $tladder_df->rid )
         error('invalid_args', "TournamentLadderProps.verify_challenge.check_rid_same({$this->tid},{$tladder_ch->rid})");

      $tgame = TournamentGames::load_tournament_game_by_uid( $tladder_ch->tid,
         $tladder_ch->uid, $tladder_df->uid );
      if( !is_null($tgame) )
      {
         if( $tgame->Status == TG_STATUS_WAIT )
         {
            // should normally not be reached by link from ladder-view, so don't calc remaing-hours
            $errors[] = sprintf( T_('Before a rematch with the same user you will have to wait %s.'),
                                 TournamentLadderProps::echo_rematch_wait($this->ChallengeRematchWaitHours) );
         }
         elseif( $tgame->Status != TG_STATUS_DONE )
            $errors[] = T_('You may only have one running game per opponent.');
      }

      // check rank-range
      $high_rank = $this->calc_highest_challenge_rank( $tladder_ch->Rank, $rating_pos );
      if( $tladder_ch->Rank < $tladder_df->Rank )
         $errors[] = T_('You may only challenge users above your own position.');
      if( $tladder_df->Rank < $high_rank )
         $errors[] = sprintf( T_('Defender rank #%s is out of your rank challenge range [#%s..#%s].'),
                              $tladder_df->Rank, $high_rank, $tladder_ch->Rank - 1 );

      // check defenders max. defenses
      if( $tladder_df->ChallengesIn >= $this->calc_max_defenses($tladder_df->Rank) )
         $errors[] = sprintf( T_('Defender already has max. %s incoming challenges.'), $tladder_df->ChallengesIn );

      // check challengers max. outgoing challenges
      if( $tladder_ch->ChallengesOut >= $this->MaxChallenges )
         $errors[] = sprintf( T_('Challenger already has max. %s outgoing challenges.'), $tladder_ch->ChallengesOut );

      return $errors;
   }//verify_challenge

   /*!
    * \brief Checks tournament-game-score and returns corresponding ladder-action for it.
    * \param $score tournament-game score, see also 'specs/db/table-Tournaments.txt'
    * \return TGEND_NO_CHANGE if no action needed; otherwise TGEND_..-action
    */
   function calc_game_end_action( $score )
   {
      $action = TGEND_NO_CHANGE;

      if( $score == -SCORE_TIME ) // game timeout (challenger won)
         $action = $this->GameEndTimeoutWin;
      elseif( $score == SCORE_TIME ) // game timeout (challenger lost)
         $action = $this->GameEndTimeoutLoss;
      elseif( $score != 0 ) // game score|resignation
      {
         if( $score < 0 )
            $action = $this->GameEndNormal;
      }
      else // ==0 = jigo
         $action = $this->GameEndJigo;

      return $action;
   }

   /*! \brief Returns TicksDue for rematch-wait, anchored on half-hourly-clock. */
   function calc_ticks_due_rematch_wait( &$tcache )
   {
      $clock_ticks = $tcache->load_clock_ticks(
         "TournamentLadderProps.calc_ticks_due_rematch_wait({$this->tid})", CLOCK_TOURNEY_GAME_WAIT );
      $ticks_due = $clock_ticks + 2 * $this->ChallengeRematchWaitHours; // half-hour-ticks to wait
      return $ticks_due;
   }

   /*! \brief Returns remaining hours to wait for rematch. */
   function calc_rematch_wait_remaining_hours( &$tcache, $tgame )
   {
      $wait_ticks = $tcache->load_clock_ticks(
         "TournamentLadderProps.calc_rematch_wait_remaining_hours({$this->tid},{$tgame->ID})",
         CLOCK_TOURNEY_GAME_WAIT);

      $remaining_hours = floor(($tgame->TicksDue - $wait_ticks + 1) / 2);
      if( $remaining_hours < 0 )
         $remaining_hours = 0;
      return $remaining_hours;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of TournamentLadderProps-objects for given tournament-id. */
   function build_query_sql( $tid )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_LADDER_PROPS']->newQuerySQL('TLP');
      $qsql->add_part( SQLP_WHERE, "TLP.tid='$tid'" );
      return $qsql;
   }

   /*! \brief Returns TournamentLadderProps-object created from specified (db-)row. */
   function new_from_row( $row )
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
            @$row['GameEndNormal'],
            @$row['GameEndJigo'],
            @$row['GameEndTimeoutWin'],
            @$row['GameEndTimeoutLoss'],
            @$row['UserAbsenceDays']
         );
      return $tlp;
   }

   /*! \brief Checks, if tournament ladder-props existing for given tournament. */
   function isTournamentLadderProps( $tid )
   {
      return (bool)mysql_single_fetch( "TournamentLadderProps::isTournamentLadderProps($tid)",
         "SELECT 1 FROM TournamentLadderProps WHERE tid='$tid' LIMIT 1" );
   }

   /*!
    * \brief Loads and returns TournamentLadderProps-object for given tournament-ID.
    * \return NULL if nothing found; TournamentLadderProps otherwise
    */
   function load_tournament_ladder_props( $tid )
   {
      if( $tid <=0 )
         return NULL;

      $qsql = TournamentLadderProps::build_query_sql( $tid );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "TournamentLadderProps::load_tournament_ladder_props($tid)",
         $qsql->get_select() );
      return ($row) ? TournamentLadderProps::new_from_row($row) : NULL;
   }

   /*! \brief Returns game-end-text, or all game-end-texts for given game-end-type TGE_.. (if game_end=null). */
   function getGameEndText( $game_end=null, $game_end_type=TGE_NORMAL )
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
      global $ARR_GLOBALS_TOURNAMENT_LADDER_PROPS;

      // lazy-init of texts
      $key = 'TGAMEEND';
      if( !isset($ARR_GLOBALS_TOURNAMENT_LADDER_PROPS[$key]) )
      {
         $arr = array();
         $arr[TGEND_NO_CHANGE]         = T_('No change#T_gameend');
         $arr[TGEND_CHALLENGER_ABOVE]  = T_('Move challenger above defender#T_gameend');
         $arr[TGEND_CHALLENGER_BELOW]  = T_('Move challenger below defender#T_gameend');
         $arr[TGEND_CHALLENGER_LAST]   = T_('Move challenger to ladder-bottom#T_gameend');
         $arr[TGEND_CHALLENGER_DELETE] = T_('Remove challenger from ladder#T_gameend');
         $arr[TGEND_SWITCH]            = T_('Switch challenger and defender#T_gameend');
         $arr[TGEND_DEFENDER_BELOW]    = T_('Move defender below challenger#T_gameend');
         $arr[TGEND_DEFENDER_LAST]     = T_('Move defender to ladder-bottom#T_gameend');
         $arr[TGEND_DEFENDER_DELETE]   = T_('Remove defender from ladder#T_gameend');
         $ARR_GLOBALS_TOURNAMENT_LADDER_PROPS[$key] = $arr;
      }

      if( is_null($game_end) )
      {
         $arr = $ARR_GLOBALS_TOURNAMENT_LADDER_PROPS[$key]; // clone
         if( !isset($arr_tgame_end[$game_end_type]) )
            error('invalid_args', "TournamentLadderProps.getGameEndText.check_type($game_end,$game_end_type)");
         $arr_intersect = $arr_tgame_end[$game_end_type];
         if( is_array($arr_intersect) )
            $arr = array_intersect_key_values( $arr, $arr_intersect );
         return $arr;
      }
      if( !isset($ARR_GLOBALS_TOURNAMENT_LADDER_PROPS[$key][$game_end]) )
         error('invalid_args', "TournamentLadderProps.getGameEndText($game_end)");
      return $ARR_GLOBALS_TOURNAMENT_LADDER_PROPS[$key][$game_end];
   }//getGameEndText

   function echo_rematch_wait( $hours, $short=false )
   {
      return TimeFormat::_echo_time( $hours, 24, TIMEFMT_ZERO | ($short ? TIMEFMT_SHORT : 0), 0 );
   }

   function formatChallengeRangeRating( $ch_range_rating )
   {
      return ($ch_range_rating == TLADDER_CHRNG_RATING_UNUSED) ? '' : $ch_range_rating;
   }

   function get_edit_tournament_status()
   {
      static $statuslist = array( TOURNEY_STATUS_NEW );
      return $statuslist;
   }

} // end of 'TournamentLadderProps'
?>
