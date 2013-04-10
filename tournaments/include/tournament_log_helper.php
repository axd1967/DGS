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

require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_director.php';
require_once 'tournaments/include/tournament_log.php';
require_once 'tournaments/include/tournament_news.php';
require_once 'tournaments/include/tournament_participant.php';
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

   function log_create_tournament( $tid, $wiz_type, $title )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, 0, 'T_Data', TLOG_ACT_CREATE, 0, $title );
      $tlog->insert();
   }

   function log_change_tournament_status( $tid, $tlog_type, $msg )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'T_Status', TLOG_ACT_CHANGE, 0, $msg );
      $tlog->insert();
   }

   function log_change_tournament_round_status( $tid, $tlog_type, $msg )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TRND_Status', TLOG_ACT_CHANGE, 0, $msg );
      $tlog->insert();
   }

   function log_tournament_lock( $tid, $tlog_type, $msg )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'T_Lock', TLOG_ACT_CHANGE, 0, $msg );
      $tlog->insert();
   }

   function log_tournament_game_end( $tid, $tlog_type, $gid, $edits, $old_score, $new_score, $old_flags, $new_flags, $old_status, $new_status )
   {
      $msg = array();
      if( $old_score !== $new_score )
         $msg[] = sprintf('TG-Score [%s] -> [%s]',
            (is_null($old_score) ? NO_VALUE : $old_score), (is_null($new_score) ? NO_VALUE : $new_score) );
      if( $old_flags != $new_flags )
         $msg[] = sprintf('TG-Flags [%s] -> [%s]', $old_flags, $new_flags );
      if( $old_status != $new_status )
         $msg[] = sprintf('TG-Status [%s] -> [%s]', $old_status, $new_status );

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TG_Data', TLOG_ACT_CHANGE, 0,
         sprintf("Change of [%s] for TG#%s with GID#%s:\n%s", implode(', ', $edits), $gid, $gid, implode('; ', $msg) ));
      $tlog->insert();
   }//log_tournament_game_end

   function log_tournament_game_add_time( $tid, $tlog_type, $msg )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TG_Data', TLOG_ACT_ADDTIME, 0, $msg );
      $tlog->insert();
   }


   function log_create_tournament_director( $tid, $tlog_type, $director )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TD_User', TLOG_ACT_CREATE, $director->uid,
         sprintf( "TD-Flags [%s]; TD-Comment [%s]", $director->formatFlags(), $director->Comment ) );
      $tlog->insert();
   }

   function log_change_tournament_director( $tid, $tlog_type, $edits, $director, $old_flags_val, $old_comment )
   {
      $old_flags = $director->formatFlags($old_flags_val);
      $new_flags = $director->formatFlags();

      $msg = array();
      if( $old_flags != $new_flags )
         $msg[] = sprintf(self::$DIFF_FMT, 'TD-Flags', $old_flags, $new_flags );
      if( $old_comment != $director->Comment )
         $msg[] = sprintf(self::$DIFF_FMT, 'TD-Comment', $old_comment, $director->Comment );

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TD_Props', TLOG_ACT_CHANGE, $director->uid,
         sprintf( "Change of [%s]: %s", implode(', ', $edits), implode('; ', $msg) ));
      $tlog->insert();
   }//log_change_tournament_director

   function log_remove_tournament_director( $tid, $tlog_type, $tdir_uid )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TD_User', TLOG_ACT_REMOVE, $tdir_uid );
      $tlog->insert();
   }


   function build_diff_tournament_participant( $old_tp, $new_tp )
   {
      $msg = array( "ID=[{$new_tp->ID}]" );
      if( $old_tp->Status != $new_tp->Status )
         $msg[] = sprintf(self::$DIFF_FMT, 'Status', $old_tp->Status, $new_tp->Status );
      if( $old_tp->Flags != $new_tp->Flags )
      {
         $old_flags = TournamentParticipant::getFlagsText( $old_tp->Flags );
         $new_flags = TournamentParticipant::getFlagsText( $new_tp->Flags );
         $msg[] = sprintf(self::$DIFF_FMT, 'Flags', $old_flags, $new_flags );
      }
      if( $old_tp->Rating != $new_tp->Rating )
         $msg[] = sprintf(self::$DIFF_FMT, 'Rating', $old_tp->Rating, $new_tp->Rating );
      if( $old_tp->StartRound != $new_tp->StartRound )
         $msg[] = sprintf(self::$DIFF_FMT, 'StartRound', $old_tp->StartRound, $new_tp->StartRound );
      if( $old_tp->NextRound != $new_tp->NextRound )
         $msg[] = sprintf(self::$DIFF_FMT, 'NextRound', $old_tp->NextRound, $new_tp->NextRound );
      if( $old_tp->Comment != $new_tp->Comment )
         $msg[] = sprintf(self::$DIFF_FMT, 'Comment', $old_tp->Comment, $new_tp->Comment );
      if( $old_tp->Notes != $new_tp->Notes )
         $msg[] = sprintf(self::$DIFF_FMT, 'Notes', $old_tp->Notes, $new_tp->Notes );
      if( $old_tp->UserMessage != $new_tp->UserMessage )
         $msg[] = sprintf(self::$DIFF_FMT, 'UserMsg', $old_tp->UserMessage, $new_tp->UserMessage );
      if( $old_tp->AdminMessage != $new_tp->AdminMessage )
         $msg[] = sprintf(self::$DIFF_FMT, 'AdmMsg', $old_tp->AdminMessage, $new_tp->AdminMessage );

      return implode('; ', $msg);
   }//build_diff_tournament_participant

   function log_tp_registration_by_director( $tlog_action, $tid, $tlog_type, $tp_uid, $msg )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TP_Reg', $tlog_action, $tp_uid, $msg );
      $tlog->insert();
   }

   function log_change_tp_registration_by_director( $tid, $tlog_type, $tp_uid, $sub_action, $edits, $old_tp, $new_tp )
   {
      $msg = sprintf( 'Change(%s) of [%s]: %s', $sub_action, implode(', ', $edits),
         TournamentLogHelper::build_diff_tournament_participant($old_tp, $new_tp) );

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TP_Reg', TLOG_ACT_CHANGE, $tp_uid, $msg );
      $tlog->insert();
   }

   function log_tp_registration_by_user( $tlog_action, $tid, $msg )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, TLOG_TYPE_USER, 'TP_Reg', $tlog_action, 0, $msg );
      $tlog->insert();
   }

   function log_change_tp_registration_by_user( $tid, $sub_action, $edits, $old_tp, $new_tp )
   {
      $msg = sprintf( 'Change(%s) of [%s]: %s', $sub_action, implode(', ', $edits),
         TournamentLogHelper::build_diff_tournament_participant($old_tp, $new_tp) );

      $tlog = new Tournamentlog( 0, $tid, 0, 0, TLOG_TYPE_USER, 'TP_Reg', TLOG_ACT_CHANGE, 0, $msg );
      $tlog->insert();
   }


   function log_change_tournament( $tid, $tlog_type, $edits, $old_t, $new_t )
   {
      $msg = array();
      if( $old_t->Scope != $new_t->Scope )
         $msg[] = sprintf(self::$DIFF_FMT, 'Scope', $old_t->Scope, $new_t->Scope );
      if( $old_t->Status != $new_t->Status )
         $msg[] = sprintf(self::$DIFF_FMT, 'Status', $old_t->Status, $new_t->Status );
      if( $old_t->Flags != $new_t->Flags )
      {
         $old_flags = $old_t->formatFlags('', 0, /*short*/true, null, /*html*/false );
         $new_flags = $new_t->formatFlags('', 0, /*short*/true, null, /*html*/false );
         $msg[] = sprintf(self::$DIFF_FMT, 'Flags', $old_flags, $new_flags );
      }
      if( $old_t->StartTime != $new_t->StartTime )
         $msg[] = sprintf(self::$DIFF_FMT, 'StartTime',
            date(DATE_FMT, $old_t->StartTime), date(DATE_FMT, $new_t->StartTime) );
      if( $old_t->Owner_ID != $new_t->Owner_ID )
         $msg[] = sprintf(self::$DIFF_FMT, 'Owner', $old_t->Owner_ID, $new_t->Owner_ID );
      if( $old_t->Title != $new_t->Title )
         $msg[] = sprintf(self::$DIFF_FMT, 'Title', $old_t->Title, $new_t->Title );
      if( $old_t->Description != $new_t->Description )
         $msg[] = sprintf(self::$DIFF_FMT, 'Descr', $old_t->Description, $new_t->Description );

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'T_Data', TLOG_ACT_CHANGE, 0,
         sprintf( "Change of [%s]: %s", implode(', ', $edits), implode('; ', $msg) ));
      $tlog->insert();
   }//log_change_tournament


   function log_change_tournament_news( $tlog_action, $tid, $tlog_type, $edits, $old_tn, $new_tn )
   {
      $msg = array( "ID=[{$new_tn->ID}]" );
      if( $old_tn->Status != $new_tn->Status )
         $msg[] = sprintf(self::$DIFF_FMT, 'Status', $old_tn->Status, $new_tn->Status );
      if( $old_tn->Flags != $new_tn->Flags )
      {
         $old_flags = TournamentNews::getFlagsText( $old_tn->Flags );
         $new_flags = TournamentNews::getFlagsText( $new_tn->Flags );
         $msg[] = sprintf(self::$DIFF_FMT, 'Flags', $old_flags, $new_flags );
      }
      if( $old_tn->Published != $new_tn->Published )
         $msg[] = sprintf(self::$DIFF_FMT, 'Published',
            date(DATE_FMT, $old_tn->Published), date(DATE_FMT, $new_tn->Published) );
      if( $old_tn->Subject != $new_tn->Subject )
         $msg[] = sprintf(self::$DIFF_FMT, 'Subject', $old_tn->Subject, $new_tn->Subject );
      if( $old_tn->Text != $new_tn->Text )
         $msg[] = sprintf(self::$DIFF_FMT, 'Text', $old_tn->Text, $new_tn->Text );

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TN_News', $tlog_action, 0,
         sprintf( "Change of [%s]: %s", implode(', ', $edits), implode('; ', $msg) ));
      $tlog->insert();
   }//log_change_tournament_news

} // end of 'TournamentLogHelper'
?>
