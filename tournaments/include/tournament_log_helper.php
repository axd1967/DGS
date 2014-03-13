<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_director.php';
require_once 'tournaments/include/tournament_log.php';
require_once 'tournaments/include/tournament_ladder_props.php';
require_once 'tournaments/include/tournament_news.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_rules.php';
require_once 'tournaments/include/tournament_utils.php';

 /*!
  * \file tournament_log_helper.php
  *
  * \brief Functions for inserting tournament log-entries.
  */


 /*!
  * \class TournamentLogHelper
  *
  * \brief Class to create tournament log-entries.
  */
class TournamentLogHelper
{
   private static $DIFF_FMT = '%s [%s] -> [%s]';

   // ------------ static functions ----------------------------
   //new Tournamentlog( $id=0, $tid=0, $uid=0, $date=0, $type='', $object='T', $action='', $actuid=0, $message='' )

   public static function log_create_tournament( $tid, $wiz_type, $title )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, 0, 'T_Data', TLOG_ACT_CREATE, 0, $title );
      $tlog->insert();
   }

   public static function log_change_tournament_status( $tid, $tlog_type, $msg )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'T_Status', TLOG_ACT_CHANGE, 0, $msg );
      $tlog->insert();
   }

   public static function log_change_tournament_round_status( $tid, $tlog_type, $msg )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TRND_Status', TLOG_ACT_CHANGE, 0, $msg );
      $tlog->insert();
   }

   public static function log_tournament_lock( $tid, $tlog_type, $msg )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'T_Lock', TLOG_ACT_CHANGE, 0, $msg );
      $tlog->insert();
   }

   public static function log_tournament_game_end( $tid, $tlog_type, $gid, $edits, $old_score, $new_score, $old_flags, $new_flags, $old_status, $new_status )
   {
      $msg = array();
      if ( $old_score !== $new_score )
         $msg[] = sprintf('TG-Score [%s] -> [%s]',
            (is_null($old_score) ? NO_VALUE : $old_score), (is_null($new_score) ? NO_VALUE : $new_score) );
      if ( $old_flags != $new_flags )
         $msg[] = sprintf('TG-Flags [%s] -> [%s]', $old_flags, $new_flags );
      if ( $old_status != $new_status )
         $msg[] = sprintf('TG-Status [%s] -> [%s]', $old_status, $new_status );

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TG_Data', TLOG_ACT_CHANGE, 0,
         sprintf("Change of [%s] for TG#%s with GID#%s:\n%s", implode(', ', $edits), $gid, $gid, implode('; ', $msg) ));
      $tlog->insert();
   }//log_tournament_game_end

   public static function log_tournament_game_add_time( $tid, $tlog_type, $msg )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TG_Data', TLOG_ACT_ADDTIME, 0, $msg );
      $tlog->insert();
   }


   public static function log_create_tournament_director( $tid, $tlog_type, $director )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TD_User', TLOG_ACT_ADD, $director->uid,
         sprintf( "TD-Flags [%s]; TD-Comment [%s]", $director->formatFlags(), $director->Comment ) );
      $tlog->insert();
   }

   public static function log_change_tournament_director( $tid, $tlog_type, $edits, $director, $old_flags_val, $old_comment )
   {
      $old_flags = $director->formatFlags($old_flags_val);
      $new_flags = $director->formatFlags();

      $msg = array();
      if ( $old_flags != $new_flags )
         $msg[] = sprintf(self::$DIFF_FMT, 'TD-Flags', $old_flags, $new_flags );
      if ( $old_comment != $director->Comment )
         $msg[] = sprintf(self::$DIFF_FMT, 'TD-Comment', $old_comment, $director->Comment );

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TD_Props', TLOG_ACT_CHANGE, $director->uid,
         sprintf( "Change of [%s]: %s", implode(', ', $edits), implode('; ', $msg) ));
      $tlog->insert();
   }//log_change_tournament_director

   public static function log_remove_tournament_director( $tid, $tlog_type, $tdir_uid )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TD_User', TLOG_ACT_REMOVE, $tdir_uid );
      $tlog->insert();
   }


   // \internal
   private static function build_diff_tournament_participant( $old_tp, $new_tp )
   {
      $msg = array( "ID=[{$new_tp->ID}]" );
      if ( $old_tp->Status != $new_tp->Status )
         $msg[] = sprintf(self::$DIFF_FMT, 'Status', $old_tp->Status, $new_tp->Status );
      if ( $old_tp->Flags != $new_tp->Flags )
      {
         $old_flags = TournamentParticipant::getFlagsText( $old_tp->Flags );
         $new_flags = TournamentParticipant::getFlagsText( $new_tp->Flags );
         $msg[] = sprintf(self::$DIFF_FMT, 'Flags', $old_flags, $new_flags );
      }
      if ( $old_tp->Rating != $new_tp->Rating )
         $msg[] = sprintf(self::$DIFF_FMT, 'Rating', $old_tp->Rating, $new_tp->Rating );
      if ( $old_tp->StartRound != $new_tp->StartRound )
         $msg[] = sprintf(self::$DIFF_FMT, 'StartRound', $old_tp->StartRound, $new_tp->StartRound );
      if ( $old_tp->NextRound != $new_tp->NextRound )
         $msg[] = sprintf(self::$DIFF_FMT, 'NextRound', $old_tp->NextRound, $new_tp->NextRound );
      if ( $old_tp->Comment != $new_tp->Comment )
         $msg[] = sprintf(self::$DIFF_FMT, 'Comment', $old_tp->Comment, $new_tp->Comment );
      if ( $old_tp->Notes != $new_tp->Notes )
         $msg[] = sprintf(self::$DIFF_FMT, 'Notes', $old_tp->Notes, $new_tp->Notes );
      if ( $old_tp->UserMessage != $new_tp->UserMessage )
         $msg[] = sprintf(self::$DIFF_FMT, 'UserMsg', $old_tp->UserMessage, $new_tp->UserMessage );
      if ( $old_tp->AdminMessage != $new_tp->AdminMessage )
         $msg[] = sprintf(self::$DIFF_FMT, 'AdmMsg', $old_tp->AdminMessage, $new_tp->AdminMessage );

      return implode('; ', $msg);
   }//build_diff_tournament_participant

   public static function log_tp_registration_by_director( $tlog_action, $tid, $tlog_type, $tp_uid, $msg )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TP_Reg', $tlog_action, $tp_uid,
         'TournamentParticipant: ' . $msg );
      $tlog->insert();
   }

   public static function log_change_tp_registration_by_director( $tid, $tlog_type, $tp_uid, $sub_action, $edits, $old_tp, $new_tp )
   {
      $msg = sprintf( 'Change(%s) of [%s]: %s', $sub_action, implode(', ', $edits),
         self::build_diff_tournament_participant($old_tp, $new_tp) );

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TP_Reg', TLOG_ACT_CHANGE, $tp_uid, $msg );
      $tlog->insert();
   }

   public static function log_tp_registration_by_user( $tlog_action, $tid, $msg )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, TLOG_TYPE_USER, 'TP_Reg', $tlog_action, 0,
         'TournamentParticipant: ' . $msg );
      $tlog->insert();
   }

   public static function log_change_tp_registration_by_user( $tid, $sub_action, $edits, $old_tp, $new_tp )
   {
      $msg = sprintf( 'Change(%s) of [%s]: %s', $sub_action, implode(', ', $edits),
         self::build_diff_tournament_participant($old_tp, $new_tp) );

      $tlog = new Tournamentlog( 0, $tid, 0, 0, TLOG_TYPE_USER, 'TP_Reg', TLOG_ACT_CHANGE, 0, $msg );
      $tlog->insert();
   }


   public static function log_change_tournament( $tid, $tlog_type, $edits, $old_t, $new_t )
   {
      $msg = array();
      if ( $old_t->Scope != $new_t->Scope )
         $msg[] = sprintf(self::$DIFF_FMT, 'Scope', $old_t->Scope, $new_t->Scope );
      if ( $old_t->Status != $new_t->Status )
         $msg[] = sprintf(self::$DIFF_FMT, 'Status', $old_t->Status, $new_t->Status );
      if ( $old_t->Flags != $new_t->Flags )
      {
         $old_flags = $old_t->formatFlags('', 0, /*short*/true, null, /*html*/false );
         $new_flags = $new_t->formatFlags('', 0, /*short*/true, null, /*html*/false );
         $msg[] = sprintf(self::$DIFF_FMT, 'Flags', $old_flags, $new_flags );
      }
      if ( $old_t->StartTime != $new_t->StartTime )
         $msg[] = sprintf(self::$DIFF_FMT, 'StartTime',
            ( $old_t->StartTime > 0 ? date(DATE_FMT, $old_t->StartTime) : ''),
            ( $new_t->StartTime > 0 ? date(DATE_FMT, $new_t->StartTime) : '') );
      if ( $old_t->Owner_ID != $new_t->Owner_ID )
         $msg[] = sprintf(self::$DIFF_FMT, 'Owner', $old_t->Owner_ID, $new_t->Owner_ID );
      if ( $old_t->Title != $new_t->Title )
         $msg[] = sprintf(self::$DIFF_FMT, 'Title', $old_t->Title, $new_t->Title );
      if ( $old_t->Description != $new_t->Description )
         $msg[] = sprintf(self::$DIFF_FMT, 'Descr', $old_t->Description, $new_t->Description );

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'T_Data', TLOG_ACT_CHANGE, 0,
         sprintf( "Change of [%s]: %s", implode(', ', $edits), implode('; ', $msg) ));
      $tlog->insert();
   }//log_change_tournament

   public static function log_change_tournament_news( $tlog_action, $tid, $tlog_type, $edits, $old_tn, $new_tn )
   {
      $msg = array( "ID=[{$new_tn->ID}]" );
      if ( $old_tn->Status != $new_tn->Status )
         $msg[] = sprintf(self::$DIFF_FMT, 'Status', $old_tn->Status, $new_tn->Status );
      if ( $old_tn->Flags != $new_tn->Flags )
      {
         $old_flags = TournamentNews::getFlagsText( $old_tn->Flags );
         $new_flags = TournamentNews::getFlagsText( $new_tn->Flags );
         $msg[] = sprintf(self::$DIFF_FMT, 'Flags', $old_flags, $new_flags );
      }
      if ( $old_tn->Published != $new_tn->Published )
         $msg[] = sprintf(self::$DIFF_FMT, 'Published',
            ( $old_tn->Published > 0 ? date(DATE_FMT, $old_tn->Published) : ''),
            ( $new_tn->Published > 0 ? date(DATE_FMT, $new_tn->Published) : '') );
      if ( $old_tn->Subject != $new_tn->Subject )
         $msg[] = sprintf(self::$DIFF_FMT, 'Subject', $old_tn->Subject, $new_tn->Subject );
      if ( $old_tn->Text != $new_tn->Text )
         $msg[] = sprintf(self::$DIFF_FMT, 'Text', $old_tn->Text, $new_tn->Text );

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TN_News', $tlog_action, 0,
         sprintf( "Change of [%s]: %s", implode(', ', $edits), implode('; ', $msg) ));
      $tlog->insert();
   }//log_change_tournament_news

   public static function log_change_tournament_props( $tid, $tlog_type, $edits, $old_tpr, $new_tpr )
   {
      $msg = array();
      if ( $old_tpr->Notes != $new_tpr->Notes )
         $msg[] = sprintf(self::$DIFF_FMT, 'Notes', $old_tpr->Notes, $new_tpr->Notes );
      if ( $old_tpr->MinParticipants != $new_tpr->MinParticipants )
         $msg[] = sprintf(self::$DIFF_FMT, 'MinParticipants', $old_tpr->MinParticipants, $new_tpr->MinParticipants );
      if ( $old_tpr->MaxParticipants != $new_tpr->MaxParticipants )
         $msg[] = sprintf(self::$DIFF_FMT, 'MaxParticipants', $old_tpr->MaxParticipants, $new_tpr->MaxParticipants );
      if ( $old_tpr->RatingUseMode != $new_tpr->RatingUseMode )
         $msg[] = sprintf(self::$DIFF_FMT, 'RatingUseMode', $old_tpr->RatingUseMode, $new_tpr->RatingUseMode );
      if ( $old_tpr->RegisterEndTime != $new_tpr->RegisterEndTime )
         $msg[] = sprintf(self::$DIFF_FMT, 'RegEndTime',
            ( $old_tpr->RegisterEndTime > 0 ? date(DATE_FMT, $old_tpr->RegisterEndTime) : ''),
            ( $new_tpr->RegisterEndTime > 0 ? date(DATE_FMT, $new_tpr->RegisterEndTime) : '') );
      if ( $old_tpr->UserMinRating != $new_tpr->UserMinRating )
         $msg[] = sprintf(self::$DIFF_FMT, 'UserMinRating', $old_tpr->UserMinRating, $new_tpr->UserMinRating );
      if ( $old_tpr->UserMaxRating != $new_tpr->UserMaxRating )
         $msg[] = sprintf(self::$DIFF_FMT, 'UserMaxRating', $old_tpr->UserMaxRating, $new_tpr->UserMaxRating );
      if ( $old_tpr->UserRated != $new_tpr->UserRated )
         $msg[] = sprintf(self::$DIFF_FMT, 'UserRated', $old_tpr->UserRated, $new_tpr->UserRated );
      if ( $old_tpr->UserMinGamesFinished != $new_tpr->UserMinGamesFinished )
         $msg[] = sprintf(self::$DIFF_FMT, 'UserMinGamesFinished', $old_tpr->UserMinGamesFinished, $new_tpr->UserMinGamesFinished );
      if ( $old_tpr->UserMinGamesRated != $new_tpr->UserMinGamesRated )
         $msg[] = sprintf(self::$DIFF_FMT, 'UserMinGamesRated', $old_tpr->UserMinGamesRated, $new_tpr->UserMinGamesRated );

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TPR_Data', TLOG_ACT_CHANGE, 0,
         sprintf( "Change of [%s]: %s", implode(', ', $edits), implode('; ', $msg) ));
      $tlog->insert();
   }//log_change_tournament_props

   public static function log_change_tournament_rules( $tid, $tlog_type, $edits, $old_trule, $new_trule )
   {
      $msg = array( "ID=[{$new_trule->ID}]" );
      if ( $old_trule->Flags != $new_trule->Flags )
      {
         $old_flags = TournamentRules::getFlagsText( $old_trule->Flags );
         $new_flags = TournamentRules::getFlagsText( $new_trule->Flags );
         $msg[] = sprintf(self::$DIFF_FMT, 'Flags', $old_flags, $new_flags );
      }
      if ( $old_trule->Notes != $new_trule->Notes )
         $msg[] = sprintf(self::$DIFF_FMT, 'Notes', $old_trule->Notes, $new_trule->Notes );
      if ( $old_trule->Ruleset != $new_trule->Ruleset )
         $msg[] = sprintf(self::$DIFF_FMT, 'Ruleset', $old_trule->Ruleset, $new_trule->Ruleset );
      if ( $old_trule->Size != $new_trule->Size )
         $msg[] = sprintf(self::$DIFF_FMT, 'Size', $old_trule->Size, $new_trule->Size );
      if ( $old_trule->Handicaptype != $new_trule->Handicaptype )
         $msg[] = sprintf(self::$DIFF_FMT, 'Handicaptype', $old_trule->Handicaptype, $new_trule->Handicaptype );
      if ( $old_trule->Handicap != $new_trule->Handicap )
         $msg[] = sprintf(self::$DIFF_FMT, 'Handicap', $old_trule->Handicap, $new_trule->Handicap );
      if ( $old_trule->Komi != $new_trule->Komi )
         $msg[] = sprintf(self::$DIFF_FMT, 'Komi', $old_trule->Komi, $new_trule->Komi );
      if ( $old_trule->AdjKomi != $new_trule->AdjKomi )
         $msg[] = sprintf(self::$DIFF_FMT, 'AdjKomi', $old_trule->AdjKomi, $new_trule->AdjKomi );
      if ( $old_trule->JigoMode != $new_trule->JigoMode )
         $msg[] = sprintf(self::$DIFF_FMT, 'JigoMode', $old_trule->JigoMode, $new_trule->JigoMode );
      if ( $old_trule->AdjHandicap != $new_trule->AdjHandicap )
         $msg[] = sprintf(self::$DIFF_FMT, 'AdjHandicap', $old_trule->AdjHandicap, $new_trule->AdjHandicap );
      if ( $old_trule->MinHandicap != $new_trule->MinHandicap )
         $msg[] = sprintf(self::$DIFF_FMT, 'MinHandicap', $old_trule->MinHandicap, $new_trule->MinHandicap );
      if ( $old_trule->MaxHandicap != $new_trule->MaxHandicap )
         $msg[] = sprintf(self::$DIFF_FMT, 'MaxHandicap', $old_trule->MaxHandicap, $new_trule->MaxHandicap );
      if ( $old_trule->StdHandicap != $new_trule->StdHandicap )
         $msg[] = sprintf(self::$DIFF_FMT, 'StdHandicap', $old_trule->StdHandicap, $new_trule->StdHandicap );
      if ( $old_trule->Maintime != $new_trule->Maintime )
         $msg[] = sprintf(self::$DIFF_FMT, 'Maintime', $old_trule->Maintime, $new_trule->Maintime );
      if ( $old_trule->Byotype != $new_trule->Byotype )
         $msg[] = sprintf(self::$DIFF_FMT, 'Byotype', $old_trule->Byotype, $new_trule->Byotype );
      if ( $old_trule->Byotime != $new_trule->Byotime )
         $msg[] = sprintf(self::$DIFF_FMT, 'Byotime', $old_trule->Byotime, $new_trule->Byotime );
      if ( $old_trule->Byoperiods != $new_trule->Byoperiods )
         $msg[] = sprintf(self::$DIFF_FMT, 'Byoperiods', $old_trule->Byoperiods, $new_trule->Byoperiods );
      if ( $old_trule->WeekendClock != $new_trule->WeekendClock )
         $msg[] = sprintf(self::$DIFF_FMT, 'WeekendClock', $old_trule->WeekendClock, $new_trule->WeekendClock );
      if ( $old_trule->Rated != $new_trule->Rated )
         $msg[] = sprintf(self::$DIFF_FMT, 'Rated', $old_trule->Rated, $new_trule->Rated );
      if ( $old_trule->ShapeID != $new_trule->ShapeID )
         $msg[] = sprintf(self::$DIFF_FMT, 'ShapeID', $old_trule->ShapeID, $new_trule->ShapeID );

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TRULE_Data', TLOG_ACT_CHANGE, 0,
         sprintf( "Change of [%s]: %s", implode(', ', $edits), implode('; ', $msg) ));
      $tlog->insert();
   }//log_change_tournament_rules

   public static function log_change_tournament_ladder_props( $tid, $tlog_type, $edits, $old_tlp, $new_tlp )
   {
      $msg = array();
      if ( $old_tlp->ChallengeRangeAbsolute != $new_tlp->ChallengeRangeAbsolute )
         $msg[] = sprintf(self::$DIFF_FMT, 'ChallengeRangeAbsolute', $old_tlp->ChallengeRangeAbsolute, $new_tlp->ChallengeRangeAbsolute );
      if ( $old_tlp->ChallengeRangeRelative != $new_tlp->ChallengeRangeRelative )
         $msg[] = sprintf(self::$DIFF_FMT, 'ChallengeRangeRelative', $old_tlp->ChallengeRangeRelative, $new_tlp->ChallengeRangeRelative );
      if ( $old_tlp->ChallengeRangeRating != $new_tlp->ChallengeRangeRating )
         $msg[] = sprintf(self::$DIFF_FMT, 'ChallengeRangeRating', $old_tlp->ChallengeRangeRating, $new_tlp->ChallengeRangeRating );
      if ( $old_tlp->ChallengeRematchWaitHours != $new_tlp->ChallengeRematchWaitHours )
         $msg[] = sprintf(self::$DIFF_FMT, 'ChallengeRematchWaitHours', $old_tlp->ChallengeRematchWaitHours, $new_tlp->ChallengeRematchWaitHours );
      if ( $old_tlp->MaxDefenses != $new_tlp->MaxDefenses )
         $msg[] = sprintf(self::$DIFF_FMT, 'MaxDefenses', $old_tlp->MaxDefenses, $new_tlp->MaxDefenses );
      if ( $old_tlp->MaxDefenses1 != $new_tlp->MaxDefenses1 )
         $msg[] = sprintf(self::$DIFF_FMT, 'MaxDefenses1', $old_tlp->MaxDefenses1, $new_tlp->MaxDefenses1 );
      if ( $old_tlp->MaxDefenses2 != $new_tlp->MaxDefenses2 )
         $msg[] = sprintf(self::$DIFF_FMT, 'MaxDefenses2', $old_tlp->MaxDefenses2, $new_tlp->MaxDefenses2 );
      if ( $old_tlp->MaxDefensesStart1 != $new_tlp->MaxDefensesStart1 )
         $msg[] = sprintf(self::$DIFF_FMT, 'MaxDefensesStart1', $old_tlp->MaxDefensesStart1, $new_tlp->MaxDefensesStart1 );
      if ( $old_tlp->MaxDefensesStart2 != $new_tlp->MaxDefensesStart2 )
         $msg[] = sprintf(self::$DIFF_FMT, 'MaxDefensesStart2', $old_tlp->MaxDefensesStart2, $new_tlp->MaxDefensesStart2 );
      if ( $old_tlp->MaxChallenges != $new_tlp->MaxChallenges )
         $msg[] = sprintf(self::$DIFF_FMT, 'MaxChallenges', $old_tlp->MaxChallenges, $new_tlp->MaxChallenges );
      if ( $old_tlp->DetermineChallenger != $new_tlp->DetermineChallenger )
         $msg[] = sprintf(self::$DIFF_FMT, 'DetermineChallenger', $old_tlp->DetermineChallenger, $new_tlp->DetermineChallenger );
      if ( $old_tlp->GameEndNormal != $new_tlp->GameEndNormal )
         $msg[] = sprintf(self::$DIFF_FMT, 'GameEndNormal', $old_tlp->GameEndNormal, $new_tlp->GameEndNormal );
      if ( $old_tlp->GameEndJigo != $new_tlp->GameEndJigo )
         $msg[] = sprintf(self::$DIFF_FMT, 'GameEndJigo', $old_tlp->GameEndJigo, $new_tlp->GameEndJigo );
      if ( $old_tlp->GameEndTimeoutWin != $new_tlp->GameEndTimeoutWin )
         $msg[] = sprintf(self::$DIFF_FMT, 'GameEndTimeoutWin', $old_tlp->GameEndTimeoutWin, $new_tlp->GameEndTimeoutWin );
      if ( $old_tlp->GameEndTimeoutLoss != $new_tlp->GameEndTimeoutLoss )
         $msg[] = sprintf(self::$DIFF_FMT, 'GameEndTimeoutLoss', $old_tlp->GameEndTimeoutLoss, $new_tlp->GameEndTimeoutLoss );
      if ( $old_tlp->UserJoinOrder != $new_tlp->UserJoinOrder )
         $msg[] = sprintf(self::$DIFF_FMT, 'UserJoinOrder', $old_tlp->UserJoinOrder, $new_tlp->UserJoinOrder );
      if ( $old_tlp->UserAbsenceDays != $new_tlp->UserAbsenceDays )
         $msg[] = sprintf(self::$DIFF_FMT, 'UserAbsenceDays', $old_tlp->UserAbsenceDays, $new_tlp->UserAbsenceDays );
      if ( $old_tlp->RankPeriodLength != $new_tlp->RankPeriodLength )
         $msg[] = sprintf(self::$DIFF_FMT, 'RankPeriodLength', $old_tlp->RankPeriodLength, $new_tlp->RankPeriodLength );
      if ( $old_tlp->CrownKingHours != $new_tlp->CrownKingHours )
         $msg[] = sprintf(self::$DIFF_FMT, 'CrownKingHours', $old_tlp->CrownKingHours, $new_tlp->CrownKingHours );
      if ( $old_tlp->CrownKingStart != $new_tlp->CrownKingStart )
         $msg[] = sprintf(self::$DIFF_FMT, 'CrownKingStart',
            ( $old_tlp->CrownKingStart > 0 ? date(DATE_FMT, $old_tlp->CrownKingStart) : ''),
            ( $new_tlp->CrownKingStart > 0 ? date(DATE_FMT, $new_tlp->CrownKingStart) : '') );

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TLP_Data', TLOG_ACT_CHANGE, 0,
         sprintf( "Change of [%s]: %s", implode(', ', $edits), implode('; ', $msg) ));
      $tlog->insert();
   }//log_change_tournament_ladder_props


   public static function log_tournament_ladder_challenge_user( $tid, $msg )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, TLOG_TYPE_USER, 'TL_Game', TLOG_ACT_START, 0, $msg );
      $tlog->insert();
   }

   public static function log_tournament_ladder_game_end( $tid, $msg )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, TLOG_TYPE_CRON, 'TL_Data', TLOG_ACT_CHANGE, 0, $msg );
      $tlog->insert();
   }


   public static function log_delete_tournament_ladder( $tid, $tlog_type )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TL_Data', TLOG_ACT_REMOVE, 0,
         'Prepare-Ladder: Delete all ladder-entries' );
      $tlog->insert();
   }

   public static function log_seed_tournament_ladder( $tid, $tlog_type, $seed_order, $seed_reorder, $cnt )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TL_Data', TLOG_ACT_SEED, 0,
         "Seed ladder: seed-order=[$seed_order], seed-reorder=[$seed_reorder] -> added $cnt entries" );
      $tlog->insert();
   }

   public static function log_delete_user_from_tournament_ladder( $tid, $tlog_type, $tladder, $arr_detached_gid, $msg )
   {
      $msg .= "; Removed user: " . $tladder->build_log_string();
      if ( count($arr_detached_gid) > 0 )
         $msg .= '; detached games: ' . implode(', ', $arr_detached_gid);
      else
         $msg .= '; no detached games';

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TL_User', TLOG_ACT_REMOVE, $tladder->uid, $msg );
      $tlog->insert();
   }

   public static function log_crown_king_tournament_ladder( $tid, $tlog_type, $king_uid )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TL_User', TLOG_ACT_SET, $king_uid, 'Crown king of the hill' );
      $tlog->insert();
   }

   public static function log_change_rank_tournament_ladder( $tid, $tlog_type, $act_uid, $old_rank, $new_rank )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TL_User', TLOG_ACT_SET, $act_uid,
         sprintf('Set new ladder-rank: %s -> %s', $old_rank, $new_rank ));
      $tlog->insert();
   }

   public static function log_add_user_tournament_ladder( $tid, $tlog_type, $join_order, $act_uid, $rank )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TL_User', TLOG_ACT_ADD, $act_uid,
         sprintf('Added user to ladder: JoinOrder=[%s], Rank=[%s]', $join_order, $rank ));
      $tlog->insert();
   }


   public static function log_add_tournament_round( $tid, $tlog_type, $set_curr_round, $tround, $tourney, $success )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TRND_Data', TLOG_ACT_ADD, 0,
         sprintf('Added round(%s) -> [%s]: Round=[%s] CurrRound=%s',
            ($set_curr_round ? 'Set_CR' : ''),
            ($success ? 'OK' : 'FAILED'),
            (is_null($tround) ? '-' : $tround->ID . '/#' . $tround->Round ),
            $tourney->CurrentRound ));
      $tlog->insert();
   }

   public static function log_delete_tournament_round( $tid, $tlog_type, $tround, $success )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TRND_Data', TLOG_ACT_REMOVE, 0,
         sprintf('Removed round %s/#%s -> [%s]', $tround->ID, $tround->Round, ($success ? 'OK' : 'FAILED') ));
      $tlog->insert();
   }

   public static function log_set_tournament_round( $tid, $tlog_type, $tround, $new_round, $success )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'T_Round', TLOG_ACT_SET, 0,
         sprintf('CurrentRound #%s -> #%s: [%s]', $tround->Round, $new_round, ($success ? 'OK' : 'FAILED') ));
      $tlog->insert();
   }

   public static function log_start_next_tournament_round( $tid, $tlog_type, $tourney, $curr_round, $success )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TRND_Data', TLOG_ACT_START, 0,
         sprintf('Started next round -> [%s]: old-Round=%s T-status=[%s] T-CurrRound=[%s]',
            (int)$success, $curr_round, $tourney->Status, $tourney->CurrentRound ));
      $tlog->insert();
   }


   public static function log_change_tournament_round_props( $tid, $tlog_type, $edits, $old_trnd, $new_trnd )
   {
      $msg = array();
      if ( $old_trnd->Status != $new_trnd->Status )
         $msg[] = sprintf(self::$DIFF_FMT, 'Status', $old_trnd->Status, $new_trnd->Status );
      if ( $old_trnd->MinPoolSize != $new_trnd->MinPoolSize )
         $msg[] = sprintf(self::$DIFF_FMT, 'MinPoolSize', $old_trnd->MinPoolSize, $new_trnd->MinPoolSize );
      if ( $old_trnd->MaxPoolSize != $new_trnd->MaxPoolSize )
         $msg[] = sprintf(self::$DIFF_FMT, 'MaxPoolSize', $old_trnd->MaxPoolSize, $new_trnd->MaxPoolSize );
      if ( $old_trnd->MaxPoolCount != $new_trnd->MaxPoolCount )
         $msg[] = sprintf(self::$DIFF_FMT, 'MaxPoolCount', $old_trnd->MaxPoolCount, $new_trnd->MaxPoolCount );
      if ( $old_trnd->PoolWinnerRanks != $new_trnd->PoolWinnerRanks )
         $msg[] = sprintf(self::$DIFF_FMT, 'PoolWinnerRanks', $old_trnd->PoolWinnerRanks, $new_trnd->PoolWinnerRanks );

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TRND_Data', TLOG_ACT_CHANGE, 0,
         sprintf( "Change of [%s] for Round-Properties %s/#%s: %s",
            implode(', ', $edits), $old_trnd->ID, $old_trnd->Round, implode('; ', $msg) ));
      $tlog->insert();
   }//log_change_tournament_round_props


   public static function log_assign_tournament_pool( $tid, $tlog_type, $tround, $old_pools, $uids, $new_pool )
   {
      $old_state = array();
      foreach ( $old_pools as $old_pool => $old_uids )
         $old_state[] = "pool $old_pool (" . implode(', ', $old_uids) . ')';

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TRND_Pool', TLOG_ACT_SET, 0,
         sprintf('Assign pool in round %s/#%s for users: [%s] -> new pool %s%s',
            $tround->ID, $tround->Round, implode('; ', $old_state), $new_pool, ($new_pool==0 ? ' (DETACH)' : '') ));
      $tlog->insert();
   }


   public static function log_define_tournament_pools( $tid, $tlog_type, $edits, $old_trnd, $new_trnd )
   {
      $msg = array();
      if ( $old_trnd->PoolSize != $new_trnd->PoolSize )
         $msg[] = sprintf(self::$DIFF_FMT, 'PoolSize', $old_trnd->PoolSize, $new_trnd->PoolSize );
      if ( $old_trnd->Pools != $new_trnd->Pools )
         $msg[] = sprintf(self::$DIFF_FMT, 'Pools', $old_trnd->Pools, $new_trnd->Pools );

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TRND_Data', TLOG_ACT_CHANGE, 0,
         sprintf( "Change of [%s] for Round-Pools %s/#%s: %s",
            implode(', ', $edits), $old_trnd->ID, $old_trnd->Round, implode('; ', $msg) ));
      $tlog->insert();
   }


   public static function log_seed_pools( $tid, $tlog_type, $round, $params, $count_users, $count_pools, $success )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TRND_Pool', TLOG_ACT_SEED, 0,
         sprintf('Seed pools (%s) for round #%s: %s users, %s pools -> [%s]',
            $params, $round, $count_users, $count_pools, ($success ? 'OK' : 'FAILED') ));
      $tlog->insert();
   }

   public static function log_seed_pools_add_missing_users( $tid, $tlog_type, $round, $count_users, $success )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TRND_Pool', TLOG_ACT_SEED, 0,
         sprintf('Seed pools for round #%s to pool 0 (add missing %s users) -> [%s]',
            $round, $count_users, ($success ? 'OK' : 'FAILED') ));
      $tlog->insert();
   }

   public static function log_delete_pools( $tid, $tlog_type, $round, $count_pools, $success )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TRND_Pool', TLOG_ACT_REMOVE, 0,
         sprintf('Delete all %s pools for round #%s -> [%s]',
            $count_pools, $round, ($success ? 'OK' : 'FAILED') ));
      $tlog->insert();
   }


   public static function log_execute_tournament_pool_rank_action( $tid, $tlog_type, $round, $action, $uid,
         $rank_from, $rank_to, $pool, $success )
   {
      if ( $action == RKACT_SET_POOL_WIN )
         $act_text = 'SetPoolWinner';
      elseif ( $action == RKACT_CLEAR_POOL_WIN )
         $act_text = 'ClearPoolWinner';
      elseif ( $action == RKACT_CLEAR_RANKS )
         $act_text = 'ClearRank';
      else //if ( $action == RKACT_REMOVE_RANKS )
         $act_text = 'RemoveRank';

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TPOOL_Rank', TLOG_ACT_SET, 0,
         sprintf('Exec rank-action [%s:%s] on round #%s for user [%s]: rank %s..%s, pool %s -> [%s]',
            $action, $act_text, $round, $uid, $rank_from, $rank_to,
            ((string)$pool != '' ? $pool : 'ALL'), ($success ? 'OK' : 'FAILED') ));
      $tlog->insert();
   }

   public static function log_set_tournament_pool_ranks( $tid, $tlog_type, $ref_title, $tpool_ids, $rank, $fix_rank, $success )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TPOOL_Rank', TLOG_ACT_SET, 0,
         sprintf('Set ranks[%s] (fix_rank=%s) for pool-IDs [%s]: rank=%s -> [%s]',
            $ref_title, ( $fix_rank ? 1 : 0 ),
            ( is_array($tpool_ids) ? implode(', ', $tpool_ids) : $tpool_ids ),
            $rank, ($success ? 'OK' : 'FAILED') ));
      $tlog->insert();
   }

   public static function log_fill_tournament_pool_winners( $tid, $tlog_type, $tround, $count )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TPOOL_Rank', TLOG_ACT_SET, 0,
         sprintf('Fill pool-winners for round %s/#%s, PW-ranks=[%s]: %s users',
            $tround->ID, $tround->Round, (int)$tround->PoolWinnerRanks, $count ));
      $tlog->insert();
   }


   public static function log_tournament_round_robin_game_end( $tid, $msg )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, TLOG_TYPE_CRON, 'TG_Data', TLOG_ACT_CHANGE, 0, $msg );
      $tlog->insert();
   }

   public static function log_start_tournament_games( $tid, $tlog_type, $tround, $pool, $cnt_expected, $cnt_existing, $cnt_total )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TG_Data', TLOG_ACT_START, 0,
         sprintf('Start tournament-games for Round[%s/#%s] Pool[%s]: %s expected, %s existing, %s created, %s total games',
            $tround->ID, $tround->Round, $pool,
            $cnt_expected, $cnt_existing, $cnt_total - $cnt_existing, $cnt_total ));
      $tlog->insert();
   }


   public static function log_change_tournament_points( $tid, $tlog_type, $edits, $old_tp, $new_tp )
   {
      $msg = array();
      if ( $old_tp->PointsType != $new_tp->PointsType )
         $msg[] = sprintf(self::$DIFF_FMT, 'PointsType', $old_tp->PointsType, $new_tp->PointsType );
      if ( $old_tp->Flags != $new_tp->Flags )
         $msg[] = sprintf(self::$DIFF_FMT, 'Flags', $old_tp->Flags, $new_tp->Flags );
      if ( $old_tp->PointsWon != $new_tp->PointsWon )
         $msg[] = sprintf(self::$DIFF_FMT, 'PointsWon', $old_tp->PointsWon, $new_tp->PointsWon );
      if ( $old_tp->PointsLost != $new_tp->PointsLost )
         $msg[] = sprintf(self::$DIFF_FMT, 'PointsLost', $old_tp->PointsLost, $new_tp->PointsLost );
      if ( $old_tp->PointsDraw != $new_tp->PointsDraw )
         $msg[] = sprintf(self::$DIFF_FMT, 'PointsDraw', $old_tp->PointsDraw, $new_tp->PointsDraw );
      if ( $old_tp->PointsForfeit != $new_tp->PointsForfeit )
         $msg[] = sprintf(self::$DIFF_FMT, 'PointsForfeit', $old_tp->PointsForfeit, $new_tp->PointsForfeit );
      if ( $old_tp->PointsNoResult != $new_tp->PointsNoResult )
         $msg[] = sprintf(self::$DIFF_FMT, 'PointsNoResult', $old_tp->PointsNoResult, $new_tp->PointsNoResult );
      if ( $old_tp->ScoreBlock != $new_tp->ScoreBlock )
         $msg[] = sprintf(self::$DIFF_FMT, 'ScoreBlock', $old_tp->ScoreBlock, $new_tp->ScoreBlock );
      if ( $old_tp->MaxPoints != $new_tp->MaxPoints )
         $msg[] = sprintf(self::$DIFF_FMT, 'MaxPoints', $old_tp->MaxPoints, $new_tp->MaxPoints );
      if ( $old_tp->PointsResignation != $new_tp->PointsResignation )
         $msg[] = sprintf(self::$DIFF_FMT, 'PointsResignation', $old_tp->PointsResignation, $new_tp->PointsResignation );
      if ( $old_tp->PointsTimeout != $new_tp->PointsTimeout )
         $msg[] = sprintf(self::$DIFF_FMT, 'PointsTimeout', $old_tp->PointsTimeout, $new_tp->PointsTimeout );

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TPOINT_Data', TLOG_ACT_CHANGE, 0,
         sprintf( "Change of [%s]: %s", implode(', ', $edits), implode('; ', $msg) ));
      $tlog->insert();
   }//log_change_tournament_points

} // end of 'TournamentLogHelper'
?>
