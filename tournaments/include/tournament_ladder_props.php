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

require_once 'include/db_classes.php';
require_once 'include/std_classes.php';
require_once 'include/utilities.php';
require_once 'tournaments/include/tournament_globals.php';

 /*!
  * \file tournament_ladder_props.php
  *
  * \brief Functions for ladder-specific tournament properties: tables TournamentLadderProps
  */

define('TLADDER_MAX_DEFENSES', 20);

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
      FTYPE_INT,  'tid', 'ChallengeRangeAbsolute', 'MaxDefenses', 'MaxDefenses1', 'MaxDefenses2',
                  'MaxDefensesStart1', 'MaxDefensesStart2',
      FTYPE_DATE, 'Lastchanged',
      FTYPE_ENUM, 'GameEndNormal', 'GameEndJigo', 'GameEndTimeoutWin', 'GameEndTimeoutLoss'
   );

class TournamentLadderProps
{
   var $tid;
   var $Lastchanged;
   var $ChangedBy;
   var $ChallengeRangeAbsolute;
   var $MaxDefenses;
   var $MaxDefenses1;
   var $MaxDefenses2;
   var $MaxDefensesStart1;
   var $MaxDefensesStart2;
   var $GameEndNormal;
   var $GameEndJigo;
   var $GameEndTimeoutWin;
   var $GameEndTimeoutLoss;

   /*! \brief Constructs TournamentLadderProps-object with specified arguments. */
   function TournamentLadderProps( $tid=0, $lastchanged=0, $changed_by='',
         $challenge_range_abs=0,
         $max_defenses=0, $max_defenses1=0, $max_defenses2=0, $max_defenses_start1=0, $max_defenses_start2=0,
         $game_end_normal=TGEND_CHALLENGER_ABOVE, $game_end_jigo=TGEND_CHALLENGER_BELOW,
         $game_end_timeout_win=TGEND_DEFENDER_BELOW, $game_end_timeout_loss=TGEND_CHALLENGER_LAST )
   {
      $this->tid = (int)$tid;
      $this->Lastchanged = (int)$lastchanged;
      $this->ChangedBy = $changed_by;
      $this->ChallengeRangeAbsolute = (int)$challenge_range_abs;
      $this->MaxDefenses = (int)$max_defenses;
      $this->MaxDefenses1 = (int)$max_defenses1;
      $this->MaxDefenses2 = (int)$max_defenses2;
      $this->MaxDefensesStart1 = (int)$max_defenses_start1;
      $this->MaxDefensesStart2 = (int)$max_defenses_start2;
      $this->setGameEndNormal($game_end_normal);
      $this->setGameEndJigo($game_end_jigo);
      $this->setGameEndTimeoutWin($game_end_timeout_win);
      $this->setGameEndTimeoutLoss($game_end_timeout_loss);
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
      $data->set_value( 'MaxDefenses', $this->MaxDefenses );
      $data->set_value( 'MaxDefenses1', $this->MaxDefenses1 );
      $data->set_value( 'MaxDefenses2', $this->MaxDefenses2 );
      $data->set_value( 'MaxDefensesStart1', $this->MaxDefensesStart1 );
      $data->set_value( 'MaxDefensesStart2', $this->MaxDefensesStart2 );
      $data->set_value( 'GameEndNormal', $this->GameEndNormal );
      $data->set_value( 'GameEndJigo', $this->GameEndJigo );
      $data->set_value( 'GameEndTimeoutWin', $this->GameEndTimeoutWin );
      $data->set_value( 'GameEndTimeoutLoss', $this->GameEndTimeoutLoss );
      return $data;
   }

   /*! \brief Checks if all ladder-properties are valid; return error-list, empty if ok. */
   function check_properties()
   {
      $errors = array();

      if( $this->ChallengeRangeAbsolute == 0 )
         $errors[] = T_('There must be at least one USED challenge range configuration.');

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

      return $errors;
   }//check_properties

   /*! \brief Returns array( header, notes-array ) with this properties in textual form. */
   function build_notes_props()
   {
      $arr_props = array();

      // challenge-range
      $arr = array( T_('You can challenge the highest position matching the following conditions') );
      if( $this->ChallengeRangeAbsolute < 0 )
         $arr[] = T_('anyone above your own ladder position');
      elseif( $this->ChallengeRangeAbsolute > 0 )
         $arr[] = sprintf( T_('%s positions above your own'), $this->ChallengeRangeAbsolute );
      $arr_props[] = $arr;

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

      // general conditions
      $arr_props[] = T_('You may only have one running game per opponent.');
      $arr_props[] = T_("On user removal or retreat from the ladder, the running tournament games\n"
         . "will be continued as normal games without effecting the tournament.");

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

      return array( T_('The ladder is configured with the following properties') . ':', $arr_props );
   }//build_notes_props

   /*!
    * \brief Enhances ladder with additional info/data (Challenge-range).
    * \param $iterator ListIterator on ordered TournamentLadder with iterator-Index on Rank & uid
    * \return TournamentLadder of given user or null if not in ladder.
    */
   function fill_ladder_challenge_range( &$iterator, $uid )
   {
      $tl_user = $iterator->getIndexValue( 'uid', $uid, 0 );
      if( is_null($tl_user) )
         return null;

      // highest challenge-rank
      $user_rank = $tl_user->Rank;
      $high_rank = $this->calc_highest_challenge_rank( $tl_user->Rank );
      for( $pos=$user_rank; $pos >= $high_rank; $pos-- )
      {
         $tladder = $iterator->getIndexValue( 'Rank', $pos, 0 );
         if( is_null($tladder) )
            continue;

         if( $tladder->Rank >= $high_rank && $tladder->Rank < $user_rank ) // chk again (race-cond)
         {
            // check max-defenses
            if( $tladder->ChallengesIn < $this->calc_max_defenses($tladder->Rank) )
               $tladder->AllowChallenge = true;
            else
               $tladder->MaxChallenged = true;
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
   function fill_ladder_running_games( &$iterator, $tgame_iterator, $my_id )
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
               $df_tladder->add_running_game( $tgame );
            }
         }
      }
   }

   /*!
    * \brief Returns highest allowed challenge rank.
    * \param $ch_rank challenger-rank
    * \note wording: 1 is "higher" than 2 in ladder.
    */
   function calc_highest_challenge_rank( $ch_rank )
   {
      $high_rank = $ch_rank;

      if( $this->ChallengeRangeAbsolute < 0 )
         $high_rank = 1;
      elseif( $this->ChallengeRangeAbsolute > 0 )
         $high_rank = $ch_rank - $this->ChallengeRangeAbsolute;

      return max( 1, $high_rank );
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
   function verify_challenge( $tladder_ch, $tladder_df )
   {
      $errors = array();

      $tgame = TournamentGames::load_tournament_game_by_uid( $tladder_ch->tid,
         $tladder_ch->uid, $tladder_df->uid );
      if( !is_null($tgame) && $tgame->Status != TG_STATUS_DONE )
         $errors[] = T_('You may only have one running game per opponent.');

      // check rank-range
      $high_rank = $this->calc_highest_challenge_rank( $tladder_ch->Rank );
      if( $tladder_ch->Rank < $tladder_df->Rank )
         $errors[] = T_('You may only challenge users above your own position.');
      if( $tladder_df->Rank < $high_rank )
         $errors[] = sprintf( T_('Defender rank #%s is out of your rank challenge range [#%s..#%s].'),
                              $tladder_df->Rank, $high_rank, $tladder_ch->Rank - 1 );

      // check defenders max. defenses
      if( $tladder_df->ChallengesIn >= $this->calc_max_defenses($tladder_df->Rank) )
         $errors[] = sprintf( T_('Defender already has max. %s incoming challenges.'), $tladder_df->ChallengesIn );

      return $errors;
   }

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
            @$row['MaxDefenses'],
            @$row['MaxDefenses1'],
            @$row['MaxDefenses2'],
            @$row['MaxDefensesStart1'],
            @$row['MaxDefensesStart2'],
            @$row['GameEndNormal'],
            @$row['GameEndJigo'],
            @$row['GameEndTimeoutWin'],
            @$row['GameEndTimeoutLoss']
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
         TGE_TIMEOUT_WIN  => 1, //=all
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
   }

   function get_edit_tournament_status()
   {
      static $statuslist = array( TOURNEY_STATUS_NEW );
      return $statuslist;
   }

} // end of 'TournamentLadderProps'
?>
