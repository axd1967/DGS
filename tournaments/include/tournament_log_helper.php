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

require_once 'tournaments/include/tournament_director.php';
require_once 'tournaments/include/tournament_log.php';
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
   }

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
         $msg[] = sprintf('TD-Flags [%s] -> [%s]', $old_flags, $new_flags );
      if( $old_comment != $director->Comment )
         $msg[] = sprintf('TD-Comment [%s] -> [%s]', $old_comment, $director->Comment );

      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TD_Props', TLOG_ACT_CHANGE, $director->uid,
         sprintf( "Change of [%s]: %s", implode(', ', $edits), implode('; ', $msg) ));
      $tlog->insert();
   }

   function log_remove_tournament_director( $tid, $tlog_type, $tdir_uid )
   {
      $tlog = new Tournamentlog( 0, $tid, 0, 0, $tlog_type, 'TD_User', TLOG_ACT_REMOVE, $tdir_uid );
      $tlog->insert();
   }

} // end of 'TournamentLogHelper'
?>
