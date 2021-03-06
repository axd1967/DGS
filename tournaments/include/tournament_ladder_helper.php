<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/std_classes.php';
require_once 'include/std_functions.php';
require_once 'include/time_functions.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_director.php';
require_once 'tournaments/include/tournament_extension.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_ladder.php';
require_once 'tournaments/include/tournament_ladder_props.php';
require_once 'tournaments/include/tournament_log_helper.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_result.php';
require_once 'tournaments/include/tournament_result_control.php';
require_once 'tournaments/include/tournament_utils.php';

 /*!
  * \file tournament_ladder_helper.php
  *
  * \brief General functions to support tournament management for ladders with db-access.
  */


 /*!
  * \class TournamentLadderHelper
  *
  * \brief Helper-class for ladder-like tournaments with mostly static functions
  *        to support Tournament management with db-access combining forces of different tournament-classes.
  */
class TournamentLadderHelper
{

   /*!
    * \brief Processes end of tournament-game for ladder-tournament.
    *
    * \note Do NOT use directly. Use TournamentHelper.process_tournament_game_end() instead.
    */
   public static function process_tournament_ladder_game_end( $tourney, $tgame )
   {
      $tid = $tourney->ID;
      $tl_props = TournamentCache::load_cache_tournament_ladder_props( 'TLH:process_tournament_ladder_game_end',
         $tid, /*check*/false );
      if ( is_null($tl_props) )
         return false;

      // determine challenger (at game-end)
      // NOTE: if determined at game-start -> TGame-challenger-role is correct
      $tl_rank_ch = TournamentLadder::load_rank( $tid, $tgame->Challenger_rid );
      $tl_rank_df = TournamentLadder::load_rank( $tid, $tgame->Defender_rid );
      if ( $tl_props->DetermineChallenger == TLP_DETERMINE_CHALL_GEND )
      {
         if ( $tl_rank_ch > 0 && $tl_rank_df > 0 && $tl_rank_ch < $tl_rank_df ) // role of challenger and defender reversed
            $tgame->Flags |= TG_FLAG_CH_DF_SWITCHED;
      }
      else
         $challrole_uid = $tgame->Challenger_uid;

      // process game-end
      $game_end_action = $tl_props->calc_game_end_action( $tgame->Score, $tgame->Flags );

      ta_begin();
      {//HOT-section to process tournament-game-end
         $success = TournamentLadder::process_game_end( $tid, $tgame, $game_end_action );
         if ( $success )
         {
            // decrease TL.ChallengesIn for defender
            $tladder_df = new TournamentLadder( $tid, $tgame->Defender_rid, $tgame->Defender_uid );
            $tladder_df->update_incoming_challenges( -1 );

            // decrease TL.ChallengesOut for challenger
            $tladder_ch = new TournamentLadder( $tid, $tgame->Challenger_rid, $tgame->Challenger_uid );
            $tladder_ch->update_outgoing_challenges( -1 );

            // tournament-game done, or start rematch-wait-period if not detached-game and not no-result-game-end
            if ( $tl_props->ChallengeRematchWaitHours > 0 && !($tgame->Flags & (TG_FLAG_GAME_DETACHED|TG_FLAG_GAME_NO_RESULT)) )
            {
               $tgame->setStatus(TG_STATUS_WAIT);
               $tgame->TicksDue = $tl_props->calc_ticks_due_rematch_wait();
            }
            else
               $tgame->setStatus(TG_STATUS_DONE);
            $tgame->update();

            // update TP.Finished/Won/Lost for challenger and defender (except for annulled(=detached) games)
            if ( !( $tgame->Flags & TG_FLAG_GAME_DETACHED ) )
            {
               TournamentParticipant::update_game_end_stats( $tid, $tgame->Challenger_rid, $tgame->Challenger_uid, $tgame->Score );
               TournamentParticipant::update_game_end_stats( $tid, $tgame->Defender_rid, $tgame->Defender_uid, -$tgame->Score );
            }
         }

         // track consecutive-wins for both players
         self::process_game_end_seq_wins( $tgame->ID, $tl_props->SeqWinsThreshold );

         // process timeout-loss handling and potential withdrawal if reached penalty-points-limit
         $logmsg_timeout = self::process_tournament_ladder_timeout( $tgame, $tl_props->PenaltyTimeout, $tl_props->PenaltyLimit );

         TournamentLogHelper::log_tournament_ladder_game_end( $tid,
            sprintf('Game End(game %s): Users role:rid/uid:Rank %s:%s/%s:%d vs %s:%s/%s:%s; T-Game(%s): Status=[%s], Flags=[%s], Score=[%s] => Action %s',
               $tgame->gid,
               ( $tgame->Flags & TG_FLAG_CH_DF_SWITCHED ? 'Defender' : 'Challenger' ),
               $tgame->Challenger_rid, $tgame->Challenger_uid, $tl_rank_ch,
               ( $tgame->Flags & TG_FLAG_CH_DF_SWITCHED ? 'Challenger' : 'Defender' ),
               $tgame->Defender_rid, $tgame->Defender_uid, $tl_rank_df,
               $tgame->ID, $tgame->Status, $tgame->formatFlags(), $tgame->Score, $game_end_action ) .
            $logmsg_timeout );
      }
      ta_end();

      return $success;
   }//process_tournament_ladder_game_end

   /*! \brief Process timeout-loss handling and potential withdrawal if reached penalty-points-limit. */
   private static function process_tournament_ladder_timeout( $tgame, $penalty_timeout, $penalty_limit )
   {
      list( $withdraw_tp, $logmsg_timeout ) =
         TournamentHelper::handle_timeout_loss_for_participant( $tgame, $penalty_timeout, $penalty_limit );
      if ( !is_null($withdraw_tp) )
      {
         $withdraw_tl = TournamentLadder::load_tournament_ladder_by_user( $tgame->tid, 0, $withdraw_tp->ID );
         if ( !is_null($withdraw_tl) )
         {
            if ( $withdraw_tl->start_withdrawal_from_ladder( 'TLH:process_tladder_timeout', TLOG_TYPE_CRON, 'Timeout' ) )
               $logmsg_timeout .= " => withdraw started (PenaltyPoints=[{$withdraw_tp->PenaltyPoints}] > PenaltyLimit=[$penalty_limit])";
         }
      }

      return $logmsg_timeout;
   }//process_tournament_ladder_timeout

   /*!
    * \brief Tracks (increases or resets) consecutive-wins (TournamentLadder.SeqWins/SeqWinsBest)
    *       for given tournament-game for challenger and defender, and adds/updates tournament-result
    *       for best-seq-wins.
    * \note must be called AFTER game-end processing, because game-end processing is setting TG.Flags for annulled games.
    *       It could be though, that challenger or defender could have been removed by game-end processing (loser removed
    *       from ladder), but if lost consecutive-win-count is not increased.
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
   private static function process_game_end_seq_wins( $tgame_id, $seq_wins_threshold )
   {
      // reload T-game due to potentially changed flags after game-end processing
      $tgame = TournamentGames::load_tournament_game_by_id( $tgame_id );
      if ( is_null($tgame) )
         return;

      // track wins for challenger
      $tladder_ch = new TournamentLadder( $tgame->tid, $tgame->Challenger_rid, $tgame->Challenger_uid );
      $tladder_ch->update_seq_wins( $tgame->Score, $tgame->Flags );

      // track wins for defender
      $tladder_df = new TournamentLadder( $tgame->tid, $tgame->Defender_rid, $tgame->Defender_uid );
      $tladder_df->update_seq_wins( -$tgame->Score, $tgame->Flags );

      TournamentResultControl::create_tournament_result_best_seq_wins( 0, $tgame, $seq_wins_threshold );
   }//process_game_end_seq_wins


   /*!
    * \brief Updates TournamentLadder.Period/History-Rank when rank-update is due, set next update-date.
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
   public static function process_ladder_rank_period( $t_ext )
   {
      $tid = $t_ext->tid;
      $tl_props = TournamentCache::load_cache_tournament_ladder_props( 'TLH:process_ladder_rank_period',
         $tid, /*check*/false );
      if ( is_null($tl_props) )
         return false;

      // set next check date at month-start (min-period = 1 month)
      $t_ext->DateValue = TournamentUtils::get_month_start_time( $GLOBALS['NOW'], $tl_props->RankPeriodLength );
      $success = $t_ext->update();

      if ( $success )
         $success = TournamentLadder::process_rank_period( $tid );

      return $success;
   }//process_ladder_rank_period

   public static function load_ladder_absent_users( $iterator=null )
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
            // no check on P.LastQuickAccess needed as P.Lastaccess contains both (see specs)
            'P.Lastaccess < NOW() - INTERVAL (TLP.UserAbsenceDays + CEIL(P.OnVacation)) DAY',
         SQLP_ORDER,
            'TL.tid ASC'
         );

      if ( is_null($iterator) )
         $iterator = new ListIterator( 'TLH:load_ladder_absent_users' );
      $iterator->addQuerySQLMerge( $qsql );
      return TournamentLadder::load_tournament_ladder( $iterator );
   }//load_ladder_absent_users

   public static function load_ladder_rank_period_update( $iterator = null )
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

      if ( is_null($iterator) )
         $iterator = new ListIterator( 'TLH:load_ladder_rank_period_update' );
      $iterator->addQuerySQLMerge( $qsql );
      return TournamentExtension::load_tournament_extensions( $iterator );
   }//load_ladder_rank_period_update


   /*!
    * \brief Crowns King with information given in $row.
    * \param $row map with fields: tid, uid, rid, Rank, X_RankChanged, Rating2, owner_uid
    */
   public static function process_tournament_ladder_crown_king( $row, $tlog_type, $by_tdir_uid=0 )
   {
      global $NOW;

      $tid = (int)$row['tid'];
      if ( !is_numeric($tid) || $tid <= 0 )
         error('invalid_args', "TLH:process_tournament_ladder_crown_king.check.tid($tid)");
      if ( !is_numeric($by_tdir_uid) || $by_tdir_uid < 0 )
         error('invalid_args', "TLH:process_tournament_ladder_crown_king.check.tdir_uid($tid,$by_tdir_uid)");

      $tresult = new TournamentResult( 0, $tid, $row['uid'], $row['rid'], $row['Rating2'],
         TRESULTTYPE_TL_KING_OF_THE_HILL, /*round*/1, /*start*/$row['X_RankChanged'], /*end*/$NOW,
         0, $row['Rank'], '', ($by_tdir_uid <= 0 ? 'set by CRON' : "set by uid $by_tdir_uid") );

      $nfy_uids = TournamentDirector::load_tournament_directors_uid( $tid );
      $nfy_uids[] = $row['owner_uid'];

      // build message-text for TD/owner notify
      $msg_text = '';
      if ( $by_tdir_uid > 0 )
         $ufmt = ( ($by_tdir_uid == $row['owner_uid']) ? T_('Owner#tourney') : T_('Tournament director') )
            . " <user $by_tdir_uid>";
      else
         $ufmt = 'CRON';
      $msg_text .=
         sprintf( T_('Tournament result changed by %s.'), $ufmt ) .
         "\n\n" .
         sprintf( T_("For %s user [ %s ] has kept the rank #%s for [%s], so the user is crowned as \"King of the Hill\"."),
                  "<tourney $tid>",
                  "<user {$row['uid']}>",
                  $row['Rank'],
                  TimeFormat::echo_time_diff( $NOW, $row['X_RankChanged'], 24, TIMEFMT_ZERO, '' ) ) .
         "\n\n" .
         T_('This notification has been sent to the tournament-owner and to all tournament-directors.');

      ta_begin();
      {//HOT-section to insert crowned-king for ladder-tournament as tournament-result
         $tresult->persist(); // add T-result

         // notify TDs + owner
         send_message( "TLH:process_tournament_ladder_crown_king.check.tid($tid)",
            $msg_text, sprintf( T_('King of the Hill crowned for tournament #%s'), $tid ),
            $nfy_uids, '', /*notify*/true,
            /*sys-msg*/0, MSGTYPE_NORMAL );

         TournamentLogHelper::log_crown_king_tournament_ladder( $tid, $tlog_type, $tresult->uid );
      }
      ta_end();
   }//process_tournament_ladder_crown_king


   /*! \brief Finds initiated withdrawals and remove users after all tournament-games finished and processed. */
   public static function process_initiated_withdrawals( $dbgmsg )
   {
      // find tournament-participants on active ladders with initiated withdrawals
      $qsql = new QuerySQL(
         SQLP_FIELDS,
            'TL.tid',
            'TL.rid',
            'P.Handle',
         SQLP_FROM,
            'Tournament AS T',
            'INNER JOIN TournamentLadder AS TL ON TL.tid=T.ID',
            'INNER JOIN Players AS P ON P.ID=TL.uid',
         SQLP_WHERE,
            "T.Status='".TOURNEY_STATUS_PLAY."'",
            "TL.Flags>0 AND (TL.Flags & ".TL_FLAG_HOLD_WITHDRAW.")"
         );
      $result = db_query( "$dbgmsg.TLH:process_initiated_withdrawals", $qsql->get_select() );

      $arr_proc = array();
      while ( $row = mysql_fetch_array($result) )
         $arr_proc[] = $row;
      mysql_free_result($result);

      ta_begin();
      {//HOT-section to check and remove users with initiated withdrawals
         foreach ( $arr_proc as $arr )
         {
            $tid = (int)$arr['tid'];
            $rid = (int)$arr['rid'];

            $count_run_tg = TournamentGames::count_user_running_games( $tid, $rid );
            if ( $count_run_tg == 0 )
            {
               $tladder = TournamentLadder::load_tournament_ladder_by_user( $tid, 0, $rid );
               if ( !is_null($tladder) )
               {
                  $tladder->remove_user_from_ladder( "$dbgmsg.proc_init_wd", TLOG_TYPE_CRON, 'Ladder-Withdraw',
                     /*upd-rank*/false, $tladder->uid, $arr['Handle'], /*withdraw*/true, /*nfy-user*/true,
                     T_('User has been withdrawn from the ladder tournament.') );
               }
            }
         }
      }
      ta_end();
   }//process_initiated_withdrawals

} // end of 'TournamentLadderHelper'

?>
