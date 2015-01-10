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

require_once 'include/db_classes.php';
require_once 'include/db/bulletin.php';
require_once 'include/std_classes.php';
require_once 'include/std_functions.php';
require_once 'include/utilities.php';
require_once 'include/time_functions.php';
require_once 'include/classlib_user.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_ladder_props.php';
require_once 'tournaments/include/tournament_log_helper.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_utils.php';

 /*!
  * \file tournament_ladder.php
  *
  * \brief Functions to keep track of ladder-type tournaments: tables TournamentLadder
  */


 /*!
  * \class TournamentLadder
  *
  * \brief Class to manage TournamentLadder-table to keep track of ladder
  */

global $ENTITY_TOURNAMENT_LADDER; //PHP5
$ENTITY_TOURNAMENT_LADDER = new Entity( 'TournamentLadder',
      FTYPE_PKEY, 'tid', 'rid',
      FTYPE_INT,  'tid', 'rid', 'uid', 'Rank', 'BestRank', 'StartRank', 'PeriodRank', 'HistoryRank',
                  'ChallengesIn', 'ChallengesOut', 'SeqWins', 'SeqWinsBest',
      FTYPE_DATE, 'Created', 'RankChanged'
   );

class TournamentLadder
{
   public $tid;
   public $rid;
   public $uid;
   public $Created;
   public $RankChanged;
   public $Rank;
   public $BestRank;
   public $StartRank;
   public $PeriodRank;
   public $HistoryRank;
   public $ChallengesIn;
   public $ChallengesOut;
   public $SeqWins;
   public $SeqWinsBest;

   // non-DB fields

   /*! \brief true if challenge allowed on this user, also if max. outgoing challenges reached. */
   public $AllowChallenge = false;
   /*! \brief true, if max. number of incoming challenges reached for ladder-user. */
   public $MaxChallengedIn = false;
   /*! \brief true, if max. number of outgoing challenges reached for ladder-user. */
   public $MaxChallengedOut = false;
   /*! \brief array of TournamentGames-object of incoming-challenges: arr( TG.ID => TG ); TG with TG.RankRef added. */
   public $IncomingTourneyGames = array();
   /*! \brief array of TournamentGames-object of outgoing-challenges: arr( TG.ID => TG ); TG with TG.RankRef added. */
   public $OutgoingTourneyGames = array();
   /*! \brief how many hours to wait till rematch allowed with same user; -1=rematch allowed, 0=TG still on WAIT-status but due. */
   public $RematchWait = -1;
   /*! \brief theoretical ladder-position if ladder were ordered by rating (user or tournament rating dependent on T-props); 0=unknown. */
   public $RatingPos = 0;
   /*! \brief theoretical ladder-position if ladder were ordered by Players.Rating2; 0=unknown. */
   public $UserRatingPos = 0;

   /*! \brief Constructs TournamentLadder-object with specified arguments. */
   public function __construct( $tid=0, $rid=0, $uid=0, $created=0, $rank_changed=0, $rank=0, $best_rank=0,
         $start_rank=0, $period_rank=0, $history_rank=0, $challenges_in=0, $challenges_out=0, $seq_wins=0, $seq_wins_best=0 )
   {
      $this->tid = (int)$tid;
      $this->rid = (int)$rid;
      $this->uid = (int)$uid;
      $this->Created = (int)$created;
      $this->RankChanged = (int)$rank_changed;
      $this->Rank = (int)$rank;
      $this->BestRank = (int)$best_rank;
      $this->StartRank = (int)$start_rank;
      $this->PeriodRank = (int)$period_rank;
      $this->HistoryRank = (int)$history_rank;
      $this->ChallengesIn = (int)$challenges_in;
      $this->ChallengesOut = (int)$challenges_out;
      $this->SeqWins = (int)$seq_wins;
      $this->SeqWinsBest = (int)$seq_wins_best;
   }

   /*! \brief Adds TournamentGames-object to list of incoming challenge (running) games. */
   public function add_incoming_game( $tgame )
   {
      if ( !($tgame instanceof TournamentGames) )
         error('invalid_args', "TournamentLadder.add_incoming_game({$this->tid},{$this->rid})");
      $this->IncomingTourneyGames[$tgame->ID] = $tgame;
   }

   /*! \brief Returns list of running incoming TournamentGames-objects ordered by TG.ID, that is creation-order (first=first-created). */
   private function get_incoming_games()
   {
      ksort( $this->IncomingTourneyGames, SORT_NUMERIC );
      return $this->IncomingTourneyGames;
   }

   /*! \brief Adds TournamentGames-object to list of outgoing challenge (running) games. */
   public function add_outgoing_game( $tgame )
   {
      if ( !($tgame instanceof TournamentGames) )
         error('invalid_args', "TournamentLadder.add_outgoing_game({$this->tid},{$this->rid})");
      $this->OutgoingTourneyGames[$tgame->ID] = $tgame;
   }

   /*! \brief Returns list of running outgoing TournamentGames-objects ordered by TG.ID, that is creation-order (first=first-created). */
   private function get_outgoing_games()
   {
      ksort( $this->OutgoingTourneyGames, SORT_NUMERIC );
      return $this->OutgoingTourneyGames;
   }

   /*!
    * \brief Returns non-null array with "[#Rank]" linked to game-id for challenge-incoming running tourney-games.
    * \param $my_id if >0, mark my challenges
    * \see fill_ladder_running_games()
    */
   public function build_linked_incoming_games( $my_id=0 )
   {
      $arr = array();
      if ( count($this->IncomingTourneyGames) )
      {
         global $base_path;

         foreach ( $this->get_incoming_games() as $tgid => $tgame )
         {
            // [#R] = known challenger, [#] = unknown challenger (user removed); add class for detached (user could re-join)
            $is_detached = ( $tgame->Flags & TG_FLAG_GAME_DETACHED );
            $gtext = ( is_null($tgame->Challenger_tladder) ) ? '#' : '#' . $tgame->Challenger_tladder->Rank;
            $ginfo = '[' . anchor( $base_path."game.php?gid={$tgame->gid}", $gtext ) . ']';
            if ( $is_detached )
               $ginfo = span('TGDetached', $ginfo, '%s', T_('annulled#tourney') );
            elseif ( $my_id > 0 && $tgame->Challenger_uid == $my_id )
               $ginfo = span('TourneyOpp', $ginfo);
            $arr[] = $ginfo;
         }
      }
      return $arr;
   }//build_linked_incoming_games

   /*!
    * \brief Returns non-null array with "[#Rank]" linked to game-id for challenge-outgoing running tourney-games.
    * \param $my_id if >0, mark my challenges
    * \see fill_ladder_running_games()
    */
   public function build_linked_outgoing_games( $my_id=0 )
   {
      $arr = array();
      if ( count($this->OutgoingTourneyGames) )
      {
         global $base_path;

         foreach ( $this->get_outgoing_games() as $tgid => $tgame )
         {
            // [#R] = known challenger, [#] = unknown challenger (user removed); add class for detached (user could re-join)
            $is_detached = ( $tgame->Flags & TG_FLAG_GAME_DETACHED );
            $gtext = ( is_null($tgame->Defender_tladder) ) ? '#' : '#' . $tgame->Defender_tladder->Rank;
            $ginfo = '[' . anchor( $base_path."game.php?gid={$tgame->gid}", $gtext ) . ']';
            if ( $is_detached )
               $ginfo = span('TGDetached', $ginfo, '%s', T_('annulled#tourney') );
            elseif ( $my_id > 0 && $tgame->Defender_uid == $my_id )
               $ginfo = span('TourneyOpp', $ginfo);
            $arr[] = $ginfo;
         }
      }
      return $arr;
   }//build_linked_outgoing_games

   public function to_string()
   {
      return print_r($this, true);
   }

   public function build_result_info()
   {
      return
         echo_image_info( "tournaments/ladder/view.php?tid={$this->tid}".URI_AMP."#rank{$this->Rank}",
            T_('Tournament Ladder') )
         . MINI_SPACING
         . span('bold', T_('Tournament Ladder'), '%s: ')
         . sprintf( T_("SeqWins [%s], SeqWinsBest [%s],\n"
                  . "Rank [%s], BestRank [%s], StartRank [%s], PeriodRank [%s], HistoryRank [%s],\n"
                  . "Created [%s], RankChanged [%s]#tourney"),
               $this->SeqWins, $this->SeqWinsBest,
               $this->Rank, $this->BestRank, $this->StartRank, $this->PeriodRank, $this->HistoryRank,
               ($this->Created > 0 ? date(DATE_FMT, $this->Created) : ''),
               ($this->RankChanged > 0 ? date(DATE_FMT, $this->RankChanged) : '') );
   }

   public function build_log_string( $fmt=0 )
   {
      if ( $fmt == 1 )
         return sprintf("rid=[%s], uid=[%s], Rank=[%s]", $this->rid, $this->uid, $this->Rank );
      else
         return sprintf("TournamentLadder: rid=[%s], uid=[%s], Created=[%s], RankChanged=[%s], Rank=[%s], BestRank=[%s], " .
                        "StartRank=[%s], PeriodRank=[%s], HistoryRank=[%s], SeqWins=[%s], SeqWinsBest=[%s]",
            $this->rid, $this->uid,
            ($this->Created > 0 ? date(DATE_FMT, $this->Created) : ''),
            ($this->RankChanged > 0 ? date(DATE_FMT, $this->RankChanged) : ''),
            $this->Rank, $this->BestRank, $this->StartRank, $this->PeriodRank, $this->HistoryRank,
            $this->SeqWins, $this->SeqWinsBest );
   }

   public function build_rank_kept( $timefmt=null, $zero_val='' )
   {
      if ( $this->RankChanged <= 0 )
         return $zero_val;

      if ( is_null($timefmt) )
         $timefmt = TIMEFMT_SHORT|TIMEFMT_ZERO;
      return TimeFormat::_echo_time(
         (int)(($GLOBALS['NOW'] - $this->RankChanged)/SECS_PER_HOUR), 24, $timefmt, '0' );
   }

   public function insert()
   {
      $this->Created = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->insert( "TournamentLadder.insert(%s)" );
   }

   public function update()
   {
      $entityData = $this->fillEntityData();
      return $entityData->update( "TournamentLadder.update(%s)" );
   }

   public function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "TournamentLadder.delete(%s)" );
   }

   public function fillEntityData( $data=null )
   {
      if ( is_null($data) )
         $data = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->newEntityData();
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'rid', $this->rid );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'Created', $this->Created );
      $data->set_value( 'RankChanged', $this->RankChanged );
      $data->set_value( 'Rank', $this->Rank );
      $data->set_value( 'BestRank', $this->BestRank );
      $data->set_value( 'StartRank', $this->StartRank );
      $data->set_value( 'PeriodRank', $this->PeriodRank );
      $data->set_value( 'HistoryRank', $this->HistoryRank );
      $data->set_value( 'ChallengesIn', $this->ChallengesIn );
      $data->set_value( 'ChallengesOut', $this->ChallengesOut );
      $data->set_value( 'SeqWins', $this->SeqWins );
      $data->set_value( 'SeqWinsBest', $this->SeqWinsBest );
      return $data;
   }

   /*! \brief Updates this TournamentLadder-instance with new rank, BestRank, RankChanged and persist into DB. */
   public function update_rank( $new_rank, $upd_rank=true )
   {
      $this->Rank = (int)$new_rank;
      if ( $this->Rank < $this->BestRank )
         $this->BestRank = $new_rank;
      if ( $upd_rank )
         $this->RankChanged = $GLOBALS['NOW'];
      return $this->update();
   }

   /*! \brief Increases or decreases TournamentLadder.ChallengesIn by given amount. */
   public function update_incoming_challenges( $diff )
   {
      if ( !is_numeric($diff) || $diff == 0 )
         error('invalid_args', "TournamentLadder.update_incoming_challenges.check.diff({$this->rid},$diff)");

      $result = db_query( "TournamentLadder.update_incoming_challenges.update({$this->rid},$diff)",
         "UPDATE TournamentLadder SET ChallengesIn=ChallengesIn+($diff) " // maybe diff<0
            . "WHERE tid={$this->tid} AND rid={$this->rid} LIMIT 1" );
      if ( $result )
         $this->ChallengesIn += $diff;
      return $result;
   }

   /*! \brief Increases or decreases TournamentLadder.ChallengesOut by given amount. */
   public function update_outgoing_challenges( $diff )
   {
      if ( !is_numeric($diff) || $diff == 0 )
         error('invalid_args', "TournamentLadder.update_outgoing_challenges.check.diff({$this->rid},$diff)");

      $result = db_query( "TournamentLadder.update_outgoing_challenges.update({$this->rid},$diff)",
         "UPDATE TournamentLadder SET ChallengesOut=ChallengesOut+($diff) " // maybe diff<0
            . "WHERE tid={$this->tid} AND rid={$this->rid} LIMIT 1" );
      if ( $result )
         $this->ChallengesOut += $diff;
      return $result;
   }

   /*!
    * \brief Increases or resets consecutive-wins stored in TournamentLadder.SeqWins and keep track of SeqWinsBest
    *       for current ladder user dependent on score and game-flags.
    * \param $game_score game-score relative to current ladder-user, i.e. score<0 =win
    * \param $tgame_flags respective TournamentGames.Flags to decide on annulled-game and no-result-game (no action)
    * \param $db_update false = only update this TournamentLadder-object
    */
   public function update_seq_wins( $game_score, $tgame_flags, $db_update=true )
   {
      if ( $tgame_flags & (TG_FLAG_GAME_DETACHED|TG_FLAG_GAME_NO_RESULT) )
         return false;

      if ( $game_score <= 0 ) // game won or jigo
      {
         if ( $db_update )
         {
            $result = db_query( "TournamentLadder.update_seq_wins.inc({$this->rid},$game_score,$tgame_flags)",
               "UPDATE TournamentLadder SET SeqWins=SeqWins+1, SeqWinsBest=GREATEST(SeqWinsBest,SeqWins) " .
               "WHERE tid={$this->tid} AND rid={$this->rid} LIMIT 1" );
         }
         else
            $result = true;
         if ( $result )
         {
            $this->SeqWins++;
            $this->SeqWinsBest = max( $this->SeqWinsBest, $this->SeqWins );
         }
      }
      else // game lost
      {
         if ( $db_update )
         {
            $result = db_query( "TournamentLadder.update_seq_wins.reset({$this->rid},$game_score,$tgame_flags)",
               "UPDATE TournamentLadder SET SeqWins=0 " .
               "WHERE tid={$this->tid} AND rid={$this->rid} LIMIT 1" );
         }
         else
            $result = true;
         if ( $result )
            $this->SeqWins = 0;
      }
      return $result;
   }//update_seq_wins

   /*!
    * \brief Removes user from ladder with given tournament tid, remove TP and notifies opponents of running games.
    * \param $tlog_type TLOG_TYPE_...
    * \param $upd_rank false (normal), true = update TL.RankChanged-field
    * \param $nfy_user true = notify user, false = user not informed about own user-removal
    * \return is-deleted
    *
    * \note notification to removed-user is done outside of this function
    * \note running games for tournament are "detached" (made unrated + detached-flag set)
    * \note IMPORTANT NOTE: expecting to run in HOT-section
    */
   public function remove_user_from_ladder( $dbgmsg, $tlog_type, $tlog_msg, $upd_rank, $rm_uid, $rm_uhandle, $nfy_user, $reason=null )
   {
      $xdbgmsg = "$dbgmsg.TL.remove_user_from_ladder({$this->tid},{$this->rid},{$this->uid},$rm_uid,$nfy_user)";

      //HOT-section to remove user from ladder and eventually from TournamentParticipant-table
      db_lock( "$xdbgmsg.upd_tladder",
         "TournamentLadder WRITE, TournamentLadder AS TL READ, " .
         "TournamentParticipant WRITE, " .
         "TournamentParticipant AS TP READ, Players AS TPP READ, " . // nested-lock for process-game-end
         "Tournament WRITE" ); // needed for nested-lock for delete-TP
      {//LOCK TournamentLadder
         $this->delete();
         $is_deleted = ( self::load_rank($this->tid, $this->rid) == 0 );
         if ( $is_deleted )
         {
            self::move_up_ladder_part($this->tid, $upd_rank, $this->Rank, 0);
            TournamentParticipant::delete_tournament_participant($this->tid, $this->rid);

            self::delete_cache_tournament_ladder( $xdbgmsg, $this->tid );
         }
      }
      db_unlock();

      if ( $is_deleted )
      {
         // end finished rematch-waiting games (no need for rematch-wait on removed user)
         TournamentGames::end_rematch_waiting_finished_games( $this->tid, $this->uid );

         // identify running TGs in role as challenger + defender
         list( $arr_tg_id, $arr_gid, $arr_opp ) = TournamentGames::find_undetached_running_games( $this->tid, $this->uid );
         if ( count($arr_tg_id) ) // set TournamentGames: set detached-flag, init SCORE-process to fix in/out-challenges
         {
            db_query( "$xdbgmsg.upd_tg",
               "UPDATE TournamentGames SET " .
                  "Flags=Flags | ".TG_FLAG_GAME_DETACHED.", " .
                  "Status=IF(Status='".TG_STATUS_PLAY."','".TG_STATUS_SCORE."',Status) " .
               "WHERE ID IN (" . implode(',', $arr_tg_id) . ") AND " .
                  "Status IN ('".TG_STATUS_PLAY."','".TG_STATUS_SCORE."')" ); // avoid race-condition
         }
         if ( count($arr_gid) ) // set Games: make unrated, set detached-flag
         {
            Games::detach_games( $xdbgmsg, $arr_gid );
            foreach ( $arr_gid as $tgid )
               GameHelper::delete_cache_game_row( "$xdbgmsg.upd_games.detach.del_cach($tgid)", $tgid );
         }

         // notify opponents about user-removal (if there are running games)
         self::notify_user_removal( "$xdbgmsg.opp_nfy", $this->tid, $rm_uid, $rm_uhandle, $reason, $arr_opp );

         if ( $nfy_user ) // notify removed user
            self::notify_user_removal( "$xdbgmsg.user_nfy", $this->tid, $rm_uid, $rm_uhandle, $reason );

         // reset Players.CountBulletinNew (as visibility of T-typed bulletins can change)
         if ( $this->uid > 0 )
            Bulletin::update_count_bulletin_new( "$xdbgmsg.upd_cntbullnew", $this->uid );

         TournamentLogHelper::log_delete_user_from_tournament_ladder( $this->tid, $tlog_type, $this, $arr_gid, $tlog_msg );
      }

      return $is_deleted;
   }//remove_user_from_ladder

   /*! \brief Changes rank of current user to new-rank (moving up or down). */
   public function change_user_rank( $new_rank, $tlog_type )
   {
      if ( $this->Rank == $new_rank ) // no rank-change
         return true;
      $dbgmsg = "TournamentLadder.change_user_rank({$this->tid},$new_rank)";

      //HOT-section to change user-rank
      db_lock( $dbgmsg, "TournamentLadder WRITE, TournamentLadder AS TL READ, Tournamentlog WRITE" );
      {//LOCK TournamentLadder
         $tl2 = self::load_tournament_ladder_by_rank($this->tid, $new_rank);
         if ( is_null($tl2) )
            error('bad_tournament', "$dbgmsg.load2");

         $success = false;
         $old_rank = $this->Rank;
         if ( abs($this->Rank - $tl2->Rank ) == 1 ) // switch direct neighbours
            $success = $this->switch_user_rank( $tl2, false );
         else
         {
            //HOT-section to update ladder
            if ( $this->Rank > $new_rank ) // user-move-up
               self::move_down_ladder_part( $this->tid, false, $new_rank, $this->Rank - 1 );
            else // user-move-down
               self::move_up_ladder_part( $this->tid, false, $this->Rank + 1, $new_rank );

            $success = $this->update_rank( $new_rank, false );
         }

         if ( $success )
         {
            self::delete_cache_tournament_ladder( $dbgmsg, $this->tid );
            TournamentLogHelper::log_change_rank_tournament_ladder( $this->tid, $tlog_type, $this->uid, $old_rank, $new_rank );
         }
      }
      db_unlock();

      return $success;
   }//change_user_rank

   /*! \brief Switch ranks of two users (this and given TournamentLadder-object). */
   public function switch_user_rank( $tlsw, $upd_rank )
   {
      if ( $this->tid != $tlsw->tid )
         error('invalid_args', "TournamentLadder.switch_user_rank.diff_tid({$this->tid},{$tlsw->tid})");
      if ( $this->rid == $tlsw->rid )
         error('invalid_args', "TournamentLadder.switch_user_rank.same_user({$this->tid},{$this->rid})");
      if ( $this->Rank == $tlsw->Rank )
         error('invalid_args', "TournamentLadder.switch_user_rank.same_rank({$this->tid},{$this->Rank})");

      swap( $this->Rank, $tlsw->Rank );
      $this->BestRank = TournamentUtils::calc_best_rank($this->BestRank, $this->Rank);
      $tlsw->BestRank = TournamentUtils::calc_best_rank($tlsw->BestRank, $tlsw->Rank);
      if ( $upd_rank )
         $this->RankChanged = $tlsw->RankChanged = $GLOBALS['NOW'];

      $this_data = $this->fillEntityData();
      $tlsw_data = $tlsw->fillEntityData();

      $query = $this_data->build_sql_insert_values(true)
         . $this_data->build_sql_insert_values() . ', '
         . $tlsw_data->build_sql_insert_values()
         . " ON DUPLICATE KEY UPDATE Rank=VALUES(Rank), BestRank=VALUES(BestRank), "
            . "RankChanged=VALUES(RankChanged)";

      return db_query( "TournamentLadder.switch_user_rank.save" .
                       "({$this->tid},{$this->rid}:{$tlsw->rid},{$this->Rank}:{$tlsw->Rank})", $query );
   }//switch_user_rank


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of TournamentLadder-objects for given tournament-id. */
   public static function build_query_sql( $tid=0 )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->newQuerySQL('TL');
      if ( $tid > 0 )
         $qsql->add_part( SQLP_WHERE, "TL.tid='$tid'" );
      return $qsql;
   }

   /*! \brief Returns TournamentLadder-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $tl = new TournamentLadder(
            // from TournamentLadder
            @$row['tid'],
            @$row['rid'],
            @$row['uid'],
            @$row['X_Created'],
            @$row['X_RankChanged'],
            @$row['Rank'],
            @$row['BestRank'],
            @$row['StartRank'],
            @$row['PeriodRank'],
            @$row['HistoryRank'],
            @$row['ChallengesIn'],
            @$row['ChallengesOut'],
            @$row['SeqWins'],
            @$row['SeqWinsBest']
         );
      return $tl;
   }

   /*! \brief Returns max Rank of TournamentLadder for given tournament-id; 0 if no entries found. */
   public static function load_max_rank( $tid )
   {
      $qsql = self::build_query_sql( $tid );
      $qsql->clear_parts( SQLP_FIELDS );
      $qsql->add_part( SQLP_FIELDS, 'IFNULL(MAX(TL.Rank),0) AS X_Rank' );
      $row = mysql_single_fetch( "TournamentLadder:load_max_rank($tid)", $qsql->get_select() );
      return ( $row ) ? (int)@$row['X_Rank'] : 0;
   }

   /*! \brief Count TournamentLadder-entries for given tournament-id; 0 if no entries found. */
   public static function count_tournament_ladder( $tid )
   {
      $qsql = self::build_query_sql( $tid );
      $qsql->clear_parts( SQLP_FIELDS );
      $qsql->add_part( SQLP_FIELDS, 'COUNT(*) AS X_Count' );
      $row = mysql_single_fetch( "TournamentLadder:count_tournament_ladder($tid)", $qsql->get_select() );
      return ( $row ) ? (int)@$row['X_Count'] : 0;
   }

   /*! \brief Returns current Rank for given RID or else UID or else current player; 0 if user not on ladder. */
   public static function load_rank( $tid, $rid=0, $uid=0 )
   {
      $qsql = self::build_query_sql( $tid );
      $qsql->clear_parts( SQLP_FIELDS );
      $qsql->add_part( SQLP_FIELDS, 'TL.Rank' );
      if ( $rid > 0 )
         $qsql->add_part( SQLP_WHERE, "TL.rid=$rid" );
      elseif ( $uid > 0 )
         $qsql->add_part( SQLP_WHERE, "TL.uid=$uid" );
      else
         $qsql->add_part( SQLP_WHERE, 'TL.uid=' . (int)@$GLOBALS['player_row']['ID'] );
      $qsql->add_part( SQLP_LIMIT, '1');

      $row = mysql_single_fetch( "TournamentLadder:load_rank($tid,$rid,$uid)", $qsql->get_select() );
      return ( $row ) ? (int)@$row['Rank'] : 0;
   }//load_rank

   /*!
    * \brief Loads and returns TournamentLadder-object for given tournament-ID and user-id;
    * \return NULL if nothing found; TournamentLadder otherwise
    */
   public static function load_tournament_ladder_by_user( $tid, $uid, $rid=0 )
   {
      if ( $tid <=0 || ($uid <= GUESTS_ID_MAX && $rid <= 0) )
         return NULL;

      $qsql = self::build_query_sql( $tid );
      if ( $uid > GUESTS_ID_MAX )
         $qsql->add_part( SQLP_WHERE, "TL.uid=$uid" );
      else
         $qsql->add_part( SQLP_WHERE, "TL.rid=$rid" );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "TournamentLadder:load_tournament_ladder_by_user($tid,$uid,$rid)",
         $qsql->get_select() );
      return ($row) ? self::new_from_row($row) : NULL;
   }//load_tournament_ladder_by_user

   /*!
    * \brief Loads and returns TournamentLadder-objects for given uid-array.
    * \return empty array if nothing found; array( uid => TournamentLadder, ...) otherwise; not all keys might be set.
    */
   public static function load_tournament_ladder_by_uids( $tid, $arr_uids )
   {
      $out = array();
      if ( $tid <= 0 || !is_array($arr_uids) || count($arr_uids) < 1 )
         return $out;

      $qsql = self::build_query_sql( $tid );
      $qsql->add_part( SQLP_WHERE, "TL.uid IN (" . implode(',', $arr_uids) . ")" );
      $result = db_query( "TournamentLadder:load_tournament_ladder_by_uids($tid)", $qsql->get_select() );

      while ( $row = mysql_fetch_array($result) )
      {
         $tladder = self::new_from_row( $row );
         $out[$tladder->uid] = $tladder;
      }
      mysql_free_result($result);

      return $out;
   }//load_tournament_ladder_by_uids

   /*! \brief Loads and returns TournamentLadder-object for given tournament-ID and rank; NULL if nothing found. */
   public static function load_tournament_ladder_by_rank( $tid, $rank )
   {
      if ( $tid <=0 || $rank <= 0 )
         return NULL;

      $qsql = self::build_query_sql( $tid );
      $qsql->add_part( SQLP_WHERE, "TL.Rank=$rank" );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "TournamentLadder:load_tournament_ladder_by_rank($tid,$rank)",
         $qsql->get_select() );
      return ($row) ? self::new_from_row($row) : NULL;
   }//load_tournament_ladder_by_rank

   /*!
    * \brief Finds ladder-position (TL.Rank) after which to insert user with given user-rating for given tournament-ID.
    * \param $use_tourney_rating true = compare given $user_rating with ladder users TournamentParticipant.Rating2;
    *        false = compare given $user_rating with ladder users Players.Rating2
    * \return ladder-position after which to insert user (if ladder sorted by rating);
    *       0 = takes top position, because ladder contains only users with weaker user-rating.
    */
   public static function find_tournament_ladder_pos_for_user_rating( $tid, $user_rating, $use_tourney_rating )
   {
      $qsql = self::build_query_sql( $tid );
      $qsql->clear_parts( SQLP_FIELDS );
      $qsql->add_part( SQLP_FIELDS, 'COUNT(*) AS X_Count' );

      if ( $use_tourney_rating )
      {
         $qsql->add_part( SQLP_FROM, "INNER JOIN TournamentParticipant AS TP ON TP.tid=TL.tid AND TP.ID=TL.rid" );
         $qsql->add_part( SQLP_WHERE, "TP.Rating >= $user_rating" );
      }
      else
      {
         $qsql->add_part( SQLP_FROM, "INNER JOIN Players AS P ON P.ID=TL.uid" );
         $qsql->add_part( SQLP_WHERE, "P.Rating2 >= $user_rating" );
      }

      $row = mysql_single_fetch( "TournamentLadder:find_tournament_ladder_pos_for_user_rating($tid,$user_rating,$use_tourney_rating)",
         $qsql->get_select() );
      return ( $row ) ? (int)@$row['X_Count'] : 0;
   }//find_tournament_ladder_pos_for_user_rating

   /*! \brief Returns enhanced (passed) ListIterator with TournamentLadder-objects for given tournament-id. */
   public static function load_tournament_ladder( $iterator, $tid=-1 )
   {
      $qsql = ( $tid >= 0 ) ? self::build_query_sql( $tid ) : new QuerySQL();
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentLadder:load_tournament_ladder", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $tladder = self::new_from_row( $row );
         $iterator->addItem( $tladder, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_tournament_ladder

   /*!
    * \brief Adds user given by User-object for tournament tid at bottom of ladder.
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
   public static function add_user_to_ladder( $tid, $uid, $tlog_type, $tl_props=null, $tprops=null )
   {
      if ( $uid <= GUESTS_ID_MAX )
         error('invalid_user', "TournamentLadder:add_user_to_ladder.check_user($tid)");

      $tp = TournamentCache::load_cache_tournament_participant( 'TournamentLadder:add_user_to_ladder', $tid, $uid );
      if ( is_null($tp) )
         error('internal_error', "TournamentLadder:add_user_to_ladder.load_tp($tid,$uid)");

      // check pre-conditions
      if ( self::load_rank($tid, 0, $uid) > 0 )
         return true; // already joined ladder
      if ( $tp->Status != TP_STATUS_REGISTER )
         error('tournament_participant_invalid_status',
               "TournamentLadder:add_user_to_ladder.check_tp_status($tid,$uid,{$tp->Status})");

      // init
      if ( is_null($tl_props) )
         $tl_props = TournamentCache::load_cache_tournament_ladder_props( 'TournamentLadder:add_user_to_ladder', $tid );

      // add user to ladder
      if ( $tl_props->UserJoinOrder == TLP_JOINORDER_REGTIME )
         $success = self::add_participant_to_ladder_bottom( $tp );
      elseif ( $tl_props->UserJoinOrder == TLP_JOINORDER_RATING )
      {
         if ( is_null($tprops) )
            $tprops = TournamentCache::load_cache_tournament_properties( 'TournamentLadder:add_user_to_ladder', $tid );
         $success = self::add_participant_to_ladder_by_rating( $tp, $tprops->need_rating_copy() );
      }
      elseif ( $tl_props->UserJoinOrder == TLP_JOINORDER_RANDOM )
         $success = self::add_participant_to_ladder_by_random( $tp );
      else
         error('invalid_args', "TournamentLadder:add_user_to_ladder.check_tlp_joinorder($tid,{$tl_props->UserJoinOrder})");

      if ( $success )
      {
         $success = self::fix_tournament_games_for_rejoin( $tid, $tp->ID, $uid );

         self::delete_cache_tournament_ladder( "TournamentLadder:add_user_to_ladder($tid,$uid)", $tid );

         $user_rank = self::load_rank($tid, 0, $uid);
         TournamentLogHelper::log_add_user_tournament_ladder( $tid, $tlog_type, $tl_props->UserJoinOrder, $uid, $user_rank );
      }

      return $success;
   }//add_user_to_ladder

   /*!
    * \brief Adds TP configured by rid,uid for tournament tid at bottom of ladder.
    * \return success
    * \internal
    */
   private static function add_participant_to_ladder_bottom( $tp )
   {
      global $NOW;
      static $query_next_rank = "IFNULL(MAX(TL.Rank),0)+1"; // must result in 1 result-row
      $tid = $tp->tid;
      $rid = $tp->ID;
      $uid = $tp->uid;

      // defaults: RankChanged=0, ChallengesIn=0, ChallengesOut=0; PeriodRank=0, HistoryRank=0
      $query = "INSERT INTO TournamentLadder (tid,rid,uid,Created,Rank,BestRank,StartRank) "
             . "SELECT $tid, $rid, $uid, FROM_UNIXTIME($NOW), "
                  . "$query_next_rank AS Rank, "
                  . "$query_next_rank AS BestRank, "
                  . "$query_next_rank AS StartRank "
             . "FROM TournamentLadder AS TL WHERE TL.tid=$tid";
      return db_query( "TournamentLadder:add_participant_to_ladder_bottom.insert(tid[$tid],rid[$rid],uid[$uid])", $query );
   }//add_participant_to_ladder_bottom

   /*!
    * \brief Adds TP configured by rid,uid for tournament tid at ladder-position below user with same rating.
    * \return success
    * \internal
    */
   private static function add_participant_to_ladder_by_rating( $tp, $use_tourney_rating )
   {
      global $player_row;
      $tid = $tp->tid;
      $rid = $tp->ID;
      $uid = $tp->uid;

      // determine user-rating to use (to find correct ladder-position)
      if ( $use_tourney_rating )
         $user_rating = $tp->Rating;
      elseif ( $player_row['ID'] == $uid )
         $user_rating = $player_row['Rating2'];
      else
      {
         $arr = User::load_quick_userinfo( array( $uid ) );
         if ( count($arr) == 0 )
            error('invalid_args', "TournamentLadder:add_participant_to_ladder_by_rating.check.bad_uid($tid,$rid,$uid)");
         $user_rating = $arr[$uid]['Rating2'];
      }

      $extra_lock = ( $use_tourney_rating ) //see find_tournament_ladder_pos_for_user_rating()
         ? ', TournamentParticipant AS TP READ'
         : ', Players AS P READ';
      db_lock( "TournamentLadder:add_participant_to_ladder_by_rating($tid,$rid,$uid)",
         "TournamentLadder WRITE, TournamentLadder AS TL READ $extra_lock" );
      {//LOCK TournamentLadder (to avoid race-conditions)
         $cnt_tp = self::count_tournament_ladder( $tid );

         // insert below user with same or stronger rating
         $new_rank = self::find_tournament_ladder_pos_for_user_rating( $tid, $user_rating, $use_tourney_rating );
         $new_rank++;
         if ( $cnt_tp > 1 && $new_rank == 1 ) // don't insert at top position without a game played
            $new_rank++;

         $result = self::_add_participant_to_ladder_with_new_rank( $tp, $cnt_tp, $new_rank );
      }
      db_unlock();

      return $result;
   }//add_participant_to_ladder_by_rating

   /*!
    * \brief Adds TP configured by rid,uid for tournament tid at random position in ladder.
    * \return success
    * \internal
    */
   private static function add_participant_to_ladder_by_random( $tp )
   {
      $tid = $tp->tid;
      $rid = $tp->ID;
      $uid = $tp->uid;

      db_lock( "TournamentLadder:add_participant_to_ladder_by_random($tid,$rid,$uid)",
         "TournamentLadder WRITE, TournamentLadder AS TL READ" );
      {//LOCK TournamentLadder (to avoid race-conditions)
         $cnt_tp = self::count_tournament_ladder( $tid );
         $new_rank = mt_rand( 1, $cnt_tp + 1 );

         $result = self::_add_participant_to_ladder_with_new_rank( $tp, $cnt_tp, $new_rank );
      }
      db_unlock();

      return $result;
   }//add_participant_to_ladder_by_random

   /*!
    * Adds tournament-participant to ladder at given new rank.
    * \note IMPORTANT: needs DB-lock on writing tables
    * \internal
    */
   private static function _add_participant_to_ladder_with_new_rank( $tp, $cnt_tp, $new_rank )
   {
      if ( $cnt_tp == 0 || $new_rank > $cnt_tp )
      {
         // add at bottom for empty ladder or last rank
         $result = self::add_participant_to_ladder_bottom( $tp );
      }
      else
      {
         // insert at new ladder-pos
         self::move_down_ladder_part( $tp->tid, false, $new_rank, 0 );

         $tladder = new TournamentLadder( $tp->tid, $tp->ID, $tp->uid, 0, 0, $new_rank, $new_rank, $new_rank );
         $result = $tladder->insert();
      }

      return $result;
   }//_add_participant_to_ladder_with_new_rank

   /*!
    * \brief Fixes TournamentLadder.ChallengesIn/Out for potentially rejoining user.
    * \return success
    * \internal
    *
    * \note When a user had been removed from the same tournament and rejoins now, there could
    *       still be running games from the moment of the removal (which are detached
    *       from the tournament). They are set on TG.Status=SCORE to remove the challenges.
    *       Howver, the processing is delayed (because running in a cron), so it can happen,
    *       that those games are still there. Therefore they still count as incoming and
    *       outgoing challenges for the challenger and defender in order to ensure correct
    *       in/out-limits on the ladder-users (the next run of the tourney-cron should fix this).
    * \see remove_user_from_ladder()
    */
   private static function fix_tournament_games_for_rejoin( $tid, $rid, $uid )
   {
      $dbgmsg = "TournamentLadder:fix_tournament_games_for_rejoin($tid,$rid,$uid)";
      $query_part = "SELECT COUNT(*) AS X_Count FROM TournamentGames WHERE tid=$tid AND " .
         "Status IN ('".TG_STATUS_PLAY."','".TG_STATUS_SCORE."') AND ";

      $row = mysql_single_fetch( "$dbgmsg.ch", $query_part . "Challenger_uid=$uid" );
      $challenges_out = (int)@$row['X_Count'];

      $row = mysql_single_fetch( "$dbgmsg.df", $query_part . "Defender_uid=$uid" );
      $challenges_in = (int)@$row['X_Count'];

      $qset = array();
      if ( $challenges_in > 0 )
         $qset[] = "ChallengesIn=$challenges_in";
      if ( $challenges_out > 0 )
         $qset[] = "ChallengesOut=$challenges_out";

      if ( count($qset) > 0 )
      {
         $success = db_query( "$dbgmsg.upd_tl",
            "UPDATE TournamentLadder SET " . implode(', ', $qset) . " WHERE tid=$tid AND rid=$rid LIMIT 1" );
      }
      else
         $success = true;

      return $success;
   }//fix_tournament_games_for_rejoin

   /*!
    * \brief Moves all tourney-users one rank up for incl. rank-range; min/max=0 separately or both for all.
    * \param $upd_rank true if RankChanged should be set too
    */
   public static function move_up_ladder_part( $tid, $upd_rank, $min_rank, $max_rank )
   {
      if ( !is_numeric($tid) || !is_numeric($min_rank) || !is_numeric($max_rank) )
         error('invalid_args', "TournamentLadder:move_up_ladder_part.check_tid($tid,$min_rank,$max_rank)");

      global $NOW;
      $query = "UPDATE TournamentLadder SET Rank=Rank-1, "
            . "BestRank=LEAST(BestRank,Rank) " // NOTE: 'Rank' is the updated field-value, so actually Rank-1
            . ( $upd_rank ? ", RankChanged=FROM_UNIXTIME($NOW) " : '' )
            . "WHERE tid=$tid "
            . TournamentUtils::build_num_range_sql_clause('Rank', $min_rank, $max_rank, 'AND');
      return db_query( "TournamentLadder:move_up_ladder_part.update($tid,$upd_rank,$min_rank,$max_rank)", $query );
   }//move_up_ladder_part

   /*!
    * \brief Moves all tourney-users one rank down for incl. rank-range; min/max=0 separately or both for all.
    * \param $upd_rank true if RankChanged should be set too
    */
   public static function move_down_ladder_part( $tid, $upd_rank, $min_rank, $max_rank )
   {
      if ( !is_numeric($tid) || !is_numeric($min_rank) || !is_numeric($max_rank) )
         error('invalid_args', "TournamentLadder:move_down_ladder_part.check_tid($tid,$min_rank,$max_rank)");

      global $NOW;
      $query = "UPDATE TournamentLadder SET Rank=Rank+1, "
            . "BestRank=LEAST(BestRank,Rank) " // NOTE: 'Rank' is the updated field-value, so actually Rank+1
            . ( $upd_rank ? ", RankChanged=FROM_UNIXTIME($NOW) " : '' )
            . "WHERE tid=$tid "
            . TournamentUtils::build_num_range_sql_clause('Rank', $min_rank, $max_rank, 'AND');
      return db_query( "TournamentLadder:move_down_ladder_part.update($tid,$upd_rank,$min_rank,$max_rank)", $query );
   }//move_down_ladder_part

   /*! \brief Delete complete ladder for given tournament-id. */
   public static function delete_ladder( $tid )
   {
      $query = "DELETE FROM TournamentLadder WHERE tid=$tid";
      $result = db_query( "TournamentLadder:delete_ladder($tid)", $query );
      self::delete_cache_tournament_ladder( "TournamentLadder:delete_ladder($tid)", $tid );
      return $result;
   }

   /*!
    * \brief Seeds ladder with all registered TPs handling already joined users.
    * \param $reorder true = reorder already joined users according to $seed_order;
    *        false = append new users below existing users
    * \return count of updated/inserted entries
    */
   public static function seed_ladder( $tourney, $tprops, $seed_order, $reorder=false )
   {
      if ( !($tourney instanceof Tournament) && $tourney->ID <= 0 )
         error('unknown_tournament', "TournamentLadder:seed_ladder.check_tid($seed_order)");
      $tid = $tourney->ID;
      $dbgmsg = "TournamentLadder:seed_ladder($tid,$seed_order)";

      list( $def, $arr_seed_order ) = $tprops->build_seed_order();
      if ( !isset($arr_seed_order[$seed_order]) )
         error('invalid_args', $dbgmsg.'.check_seed_order');

      // load already joined ladder-users
      $tl_iterator = new ListIterator( $dbgmsg.'.load_ladder' );
      $tl_iterator->addIndex( 'uid' );
      $tl_iterator = self::load_tournament_ladder( $tl_iterator, $tid );

      // find all registered TPs (optimized)
      $arr_TPs = TournamentParticipant::load_registered_users_in_seedorder( $tid, /*round*/1, $seed_order );

      // add all TPs to ladder
      $NOW = $GLOBALS['NOW'];
      $entity_tladder = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->newEntityData();
      $arr_inserts = array();

      db_lock( $dbgmsg, "TournamentLadder WRITE, TournamentLadder AS TL READ" );
      {//LOCK TournamentLadder
         $rank = ($reorder) ? 1 : self::load_max_rank($tid) + 1;
         foreach ( $arr_TPs as $row )
         {
            $uid = $row['uid'];
            $tladder = $tl_iterator->getIndexValue( 'uid', $uid, 0 );
            if ( is_null($tladder) ) // user not joined ladder yet
            {
               $tladder = new TournamentLadder( $tid, $row['rid'], $uid, $NOW, 0, $rank, $rank, $rank );
            }
            else // user already joined ladder
            {
               if ( !$reorder )
                  continue; // no reorder -> skip already joined user (no rank-inc)
               if ( $tladder->Rank == $rank )
               {
                  ++$rank;
                  continue; // no update (same rank), but rank-inc
               }
               $tladder->Rank = $rank;
               $tladder->BestRank = TournamentUtils::calc_best_rank( $rank, $tladder->BestRank );
               $tladder->RankChanged = $NOW;
            }

            $data_tladder = $tladder->fillEntityData( $entity_tladder );
            $arr_inserts[] = $data_tladder->build_sql_insert_values();
            ++$rank;
         }
         unset($arr_TPs);
         unset($tl_iterator);

         // insert all registered TPs to ladder
         $cnt = count($arr_inserts);
         if ( $cnt > 0 )
         {
            $seed_query = $entity_tladder->build_sql_insert_values(true) . implode(',', $arr_inserts)
               . " ON DUPLICATE KEY UPDATE Rank=VALUES(Rank), BestRank=VALUES(BestRank), "
               . " RankChanged=VALUES(RankChanged)";
            $result = db_query( $dbgmsg.".insert($reorder,#$cnt)", $seed_query );
         }

         self::delete_cache_tournament_ladder( $dbgmsg, $tid );
      }
      db_unlock();

      return $cnt;
   }//seed_ladder

   /*!
    * \brief Checks if all participants (arr_TPS[rid=>uid]) are registered in ladder;
    *        auto-removing bad registrations.
    */
   public static function check_participant_registrations( $tid, $arr_TPs )
   {
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS, 'rid', 'uid' );
      $qsql->add_part( SQLP_FROM,   'TournamentLadder' );
      $qsql->add_part( SQLP_WHERE,  "tid=$tid" );
      $result = db_query( "TournamentLadder:check_participant_registrations.load_ladder($tid)",
         $qsql->get_select() );

      $arr_miss = array() + $arr_TPs; // registered TP, but not in ladder
      $arr_bad  = array(); // user in ladder, but not a registered TP
      while ( $row = mysql_fetch_array($result) )
      {
         $rid = $row['rid'];
         $uid = $row['uid'];
         if ( isset($arr_TPs[$rid]) )
            unset($arr_miss[$rid]);
         else
            $arr_bad[$rid] = $uid;
      }
      mysql_free_result($result);

      global $base_path;
      $errors = array();
      if ( count($arr_miss) )
      {
         $arr = array();
         foreach ( $arr_miss as $rid => $uid )
            $arr[] = anchor( $base_path."tournaments/ladder/admin.php?tid=$tid".URI_AMP."uid=$uid", $uid );
         $errors[] = T_('Found registered tournament participants not added to ladder')
            . "<br>\n" . sprintf( T_('users [%s]#T_ladder'), implode(', ', $arr) );
      }
      if ( count($arr_bad) )
      {
         $arr = array();
         foreach ( $arr_bad as $rid => $uid )
            $arr[] = anchor( $base_path."tournaments/edit_participant.php?tid=$tid".URI_AMP."uid=$uid", $uid );
         $errors[] = T_('Found users added to ladder without registration (contact tournament-admin to fix inconsistency)')
            . "<br>\n" . sprintf( T_('users [%s]#T_ladder'), implode(', ', $arr) );
      }
      return $errors;
   }//check_participant_registrations

   public static function new_tournament_game( $tid, $tladder_ch, $tladder_df )
   {
      $tg = new TournamentGames( 0, $tid );
      $tg->Challenger_uid = $tladder_ch->uid;
      $tg->Challenger_rid = $tladder_ch->rid;
      $tg->Defender_uid   = $tladder_df->uid;
      $tg->Defender_rid   = $tladder_df->rid;
      return $tg;
   }

   /*!
    * \brief Processes tournament-game-end for given tournament-game $tgame and game-end-action.
    * \note Only called from CRON! (for tournament-logging)
    */
   public static function process_game_end( $tid, $tgame, $game_end_action )
   {
      if ( $game_end_action == TGEND_NO_CHANGE )
         return true;

      $ch_rid = $tgame->Challenger_rid;
      $df_rid = $tgame->Defender_rid;
      if ( $tgame->Flags & TG_FLAG_CH_DF_SWITCHED )
         swap($ch_rid, $df_rid); // reverse role of challenger and defender

      //HOT-section to update ladder (besides reading)
      db_lock( "TournamentLadder:process_game_end($tid,$ch_rid,$df_rid,{$tgame->Flags},$game_end_action)",
         "TournamentLadder WRITE, TournamentLadder AS TL READ" );
      {//LOCK TournamentLadder
         $success = self::_process_game_end( $tid, $ch_rid, $df_rid, $game_end_action );
      }
      db_unlock();

      return $success;
   }//process_game_end

   /*!
    * \brief (INTERNAL) processing tournament-game-end, called from process_game_end()-func with already locked TL-table.
    * \internal
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    * \note $ch_rid and $df_rid must already reflect switched role of challenger/defender determined
    *       by TournamentLadderProps.DetermineChallenger
    * \note Special change-user-rank, handling missing challenger/defender cases, also updating BestRank + RankChanged-fields
    * \note Only called from CRON! (for tournament-logging)
    */
   private static function _process_game_end( $tid, $ch_rid, $df_rid, $game_end_action )
   {
      $tladder_df = null;
      if ( $game_end_action != TGEND_CHALLENGER_LAST && $game_end_action != TGEND_CHALLENGER_DELETE )
      {
         $tladder_df = self::load_tournament_ladder_by_user($tid, 0, $df_rid);
         if ( is_null($tladder_df) ) // defender not longer on ladder -> nothing to do
            return true;
      }

      $tladder_ch = null;
      if ( $game_end_action != TGEND_DEFENDER_LAST && $game_end_action != TGEND_DEFENDER_DELETE )
      {
         $tladder_ch = self::load_tournament_ladder_by_user($tid, 0, $ch_rid);
         if ( is_null($tladder_ch) ) // challenger not longer on ladder -> nothing to do
            return true;
      }

      // process game-end
      $success = true;
      $dbgmsg = "TournamentLadder.process_game_end($tid,$ch_rid,$df_rid,$game_end_action)";
      switch ( (string)$game_end_action )
      {
         case TGEND_CHALLENGER_ABOVE: // move challenger right above defender
         {
            $logmsg = "CH.Rank={$tladder_ch->Rank}";
            $ch_new_rank = $tladder_df->Rank;
            if ( $tladder_ch->Rank > $ch_new_rank ) // challenger below new pos
            {
               self::move_down_ladder_part( $tid, true, $ch_new_rank, $tladder_ch->Rank - 1 );
               $success = $tladder_ch->update_rank( $ch_new_rank );
               $logmsg .= ">$ch_new_rank";
            }
            break;
         }

         case TGEND_CHALLENGER_BELOW: // move challenger right below defender
         {
            $logmsg = "CH.Rank={$tladder_ch->Rank}";
            $ch_new_rank = $tladder_df->Rank + 1;
            if ( $tladder_ch->Rank > $ch_new_rank ) // challenger below new pos
            {
               if ( abs($tladder_ch->Rank - $tladder_df->Rank) > 1 ) // no direct neighbours?
               {
                  self::move_down_ladder_part( $tid, true, $ch_new_rank, $tladder_ch->Rank - 1 );
                  $success = $tladder_ch->update_rank( $ch_new_rank );
               }
               $logmsg .= ">$ch_new_rank";
            }
            break;
         }

         case TGEND_CHALLENGER_LAST: // move challenger to last ladder-position
         {
            $logmsg = "CH.Rank={$tladder_ch->Rank}";
            $ch_new_rank = self::load_max_rank( $tid ); // get last ladder-rank
            if ( $tladder_ch->Rank < $ch_new_rank ) // challenger above new (last) ladder-pos
            {
               self::move_up_ladder_part( $tid, true, $tladder_ch->Rank + 1, $ch_new_rank );
               $success = $tladder_ch->update_rank( $ch_new_rank );
               $logmsg .= ">LAST$ch_new_rank";
            }
            break;
         }

         case TGEND_CHALLENGER_DELETE: // remove challenger from ladder
         {
            $success = $tladder_ch->remove_user_from_ladder( $dbgmsg, TLOG_TYPE_CRON, "Game-End[$game_end_action]",
               /*upd-rank*/true, $tladder_ch->uid, '', /*nfy-user*/true );
            $logmsg = "CH.Rank={$tladder_ch->Rank}>DEL";
            break;
         }

         case TGEND_SWITCH: // switch challenger with defender
         {
            $logmsg = "CH.Rank={$tladder_ch->Rank}";
            $ch_new_rank = $tladder_df->Rank;
            if ( $tladder_ch->Rank > $ch_new_rank ) // challenger below new pos
            {
               $success = $tladder_ch->switch_user_rank( $tladder_df, true );
               $logmsg .= "><DF.Rank={$tladder_ch->Rank}";
            }
            break;
         }

         case TGEND_DEFENDER_BELOW: // move defender right below challenger
         {
            $logmsg = "DF.Rank={$tladder_df->Rank}";
            $df_new_rank = $tladder_ch->Rank;
            if ( $tladder_df->Rank < $df_new_rank ) // defender above new pos
            {
               self::move_up_ladder_part( $tid, true, $tladder_df->Rank + 1, $df_new_rank );
               $success = $tladder_df->update_rank( $df_new_rank );
               $logmsg .= ">$df_new_rank";
            }
            break;
         }

         case TGEND_DEFENDER_LAST: // move defender to last ladder-position
         {
            $logmsg = "DF.Rank={$tladder_df->Rank}";
            $df_new_rank = self::load_max_rank( $tid ); // get last ladder-rank
            if ( $tladder_df->Rank < $df_new_rank ) // defender above new (last) ladder-pos
            {
               self::move_up_ladder_part( $tid, true, $tladder_df->Rank + 1, $df_new_rank );
               $success = $tladder_df->update_rank( $df_new_rank );
               $logmsg .= ">LAST$df_new_rank";
            }
            break;
         }

         case TGEND_DEFENDER_DELETE: // remove defender from ladder
         {
            $success = $tladder_df->remove_user_from_ladder( $dbgmsg, TLOG_TYPE_CRON, "Game-End[$game_end_action]",
               /*upd-rank*/true, $tladder_df->uid, '', /*nfy-user*/true );
            $logmsg = "DF.Rank={$tladder_df->Rank}>DEL";
            break;
         }
      }//switch (game_end_action)

      if ( DBG_QUERY )
         error_log("$dbgmsg: $logmsg");

      return $success;
   }//_process_game_end

   /*!
    * \brief Sends notify to removed-user or to list of opponents about user-removal and detached tournament-games.
    * \param $rm_uid removed-user-id
    * \param $rm_uhandle should be set, but is loaded if empty
    * \param $reason null (default on processing game-end); or text instead
    * \param $arr_uid null = send msg to removed-user; otherwise send msg to opponents-uids from $arr_uid (if not empty)
    */
   public static function notify_user_removal( $dbgmsg, $tid, $rm_uid, $rm_uhandle, $reason=null, $arr_uid=null )
   {
      $is_user = !is_array($arr_uid);
      if ( !$is_user && count($arr_uid) == 0 )
         return;

      if ( !$is_user && !$rm_uhandle ) // load removed-user handle if not set
      {
         $uarr = User::load_quick_userinfo( array( $rm_uid ) );
         $rm_uhandle = @$uarr[$rm_uid]['Handle'];
         if ( !$rm_uhandle )
            $rm_uhandle = "<user $rm_uid>";
      }

      if ( !$reason ) // default reason for processing game-end
         $reason = T_('The system has removed the user from the tournament due to the ladder-configurations defined for tournament-game endings.');

      $subject = ( $is_user )
          ? sprintf( T_('Removal from tournament #%s'), $tid )
          : sprintf( T_('Removal of user [%s] from tournament #%s'), $rm_uhandle, $tid );

      $body = array();
      $body[] = ( $is_user )
         ? T_('You have been removed from the tournament') . ": <tourney $tid>"
         : sprintf( T_('User %s has been removed from the tournament'), "<user $rm_uid>" ) . ": <tourney $tid>";
      $body[] = T_('Reason#tourney') . ': ' . $reason . "\n";
      $body[] = TournamentUtils::get_tournament_ladder_notes_user_removed() . "\n";
      $body[] = ( $is_user )
         ? anchor( "show_games.php?tid=$tid",
                   sprintf( T_('My running games for tournament #%s'), $tid ))
         : anchor( "show_games.php?tid=$tid".URI_AMP."opp_hdl=".urlencode($rm_uhandle),
                   sprintf( T_('My running games with user [%s] for tournament #%s'), $rm_uhandle, $tid ));

      send_message( "$dbgmsg.sendmsg($tid,$rm_uid)",
         implode("\n", $body), $subject,
         ( $is_user ? $rm_uid : $arr_uid ), '', /*notify*/true,
         0/*sys-msg*/, MSGTYPE_NORMAL );
   }//notify_user_removal

   /*!
    * \brief Processes long absence of user not being online by removing user from ladder.
    * \note IMPORTANT NOTE: expecting to run in HOT-section
    * \note only called by CRON! (for tournament-logging)
    */
   public static function process_user_absence( $tid, $uid, $user_abs_days )
   {
      // reload TL because Rank could have been changed in the meantime !!
      $tladder = self::load_tournament_ladder_by_user( $tid, $uid );
      if ( is_null($tladder) )
         return true;

      // remove user from ladder
      $reason = sprintf( T_('The system has removed the user from the tournament due to inactivity for more than %s days as defined in the ladder-configurations.'),
                         $user_abs_days );
      $success = $tladder->remove_user_from_ladder( "TournamentLadder:process_user_absence($tid,$uid,$user_abs_days)",
         TLOG_TYPE_CRON, 'User-Absence', /*upd-rank*/true, $uid, '', /*nfy-user*/true, $reason );
      $logmsg = "U.Rank={$tladder->Rank}>DEL";

      if ( DBG_QUERY )
         error_log("TournamentLadder:process_user_absence($tid,$uid,$user_abs_days): $logmsg");

      return $success;
   }//process_user_absence

   /*! \brief Copies PeriodRank over to HistoryRank-field and Rank to PeriodRank-field. */
   public static function process_rank_period( $tid )
   {
      if ( !is_numeric($tid) || $tid <= 0 )
         error('invalid_args', "TournamentLadder:process_rank_period.check.tid($tid)");

      return db_query( "TournamentLadder:process_rank_period($tid)",
         "UPDATE TournamentLadder SET HistoryRank=PeriodRank, PeriodRank=Rank WHERE tid=$tid" );
   }

   public static function process_crown_king_reset_rank( $tid, $rid )
   {
      global $NOW;
      if ( !is_numeric($tid) || $tid <= 0 )
         error('invalid_args', "TournamentLadder:process_crown_king.check.tid($tid,$rid)");
      if ( !is_numeric($rid) || $rid <= 0 )
         error('invalid_args', "TournamentLadder:process_crown_king.check.rid($tid,$rid)");

      return db_query( "TournamentLadder:process_crown_king($tid,$rid)",
         "UPDATE TournamentLadder SET RankChanged=FROM_UNIXTIME($NOW) WHERE tid=$tid AND rid=$rid LIMIT 1" );
   }

   /*! \brief Returns true if edit-ladder is allowed concerning tourney-locks. */
   public static function allow_edit_ladder( $tourney, &$return_errors )
   {
      $errors = array();

      // check admin-lock
      $is_admin = TournamentUtils::isAdmin();
      $is_admin_lock = $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN);
      if ( !$is_admin && $is_admin_lock )
         $errors[] = $tourney->buildAdminLockText();

      // check other locks
      // NOTE: being T-Admin + Admin-lock overwrites other locks
      if ( !( $is_admin && $is_admin_lock ) )
      {
         if ( !$tourney->isFlagSet(TOURNEY_FLAG_LOCK_TDWORK) )
            $errors[] = Tournament::getLockText(TOURNEY_FLAG_LOCK_TDWORK);
         if ( $tourney->isFlagSet(TOURNEY_FLAG_LOCK_CRON) )
            $errors[] = Tournament::getLockText(TOURNEY_FLAG_LOCK_CRON);
      }

      $return_errors = array_merge( $return_errors, $errors );
      return ( count($errors) == 0 );
   }//allow_edit_ladder

   /*! \brief Returns rank-diff-info for given prev-rank (relative to rank) and format, NO_VALUE if prev-rank is 0. */
   public static function build_rank_diff( $rank, $prev_rank, $fmt='%s. (%s)' )
   {
      // also see 'js/common.js buildRankDiff()'
      if ( $prev_rank == 0 ) // nothing to compare
         return NO_VALUE;

      if ( $rank == $prev_rank )
         $rank_diff = '=';
      elseif ( $rank < $prev_rank )
         $rank_diff = '+' . ($prev_rank - $rank);
      else //$rank > $prev_rank
         $rank_diff = '-' . ($rank - $prev_rank);
      return sprintf( $fmt, $prev_rank, $rank_diff );
   }//build_rank_diff

   public static function get_rank_info_format()
   {
      return
         sprintf( "%s: %%s.<br>%s: %%s.<br>%s: %%s<br>%s: %%s",
            basic_safe(T_('Current Rank#T_ladder')),
            basic_safe(T_('Best Rank#T_ladder')),
            basic_safe(T_('Start of Period (Change)#T_ladder')),
            basic_safe(T_('Previous Period (Change)#T_ladder')) );
   }

   public static function get_edit_tournament_status()
   {
      static $statuslist = array( TOURNEY_STATUS_PAIR, TOURNEY_STATUS_PLAY );
      return $statuslist;
   }

   /*! \brief Loads and caches TournamentLadder-entries for given tournament-id. */
   public static function load_cache_tournament_ladder( $dbgmsg, $tid, $with_tp_rating, $need_tp_finished,
         $with_tp_lastmove, $limit=0, $with_index=false )
   {
      $with_tp = ( $with_tp_rating || $need_tp_finished || $with_tp_lastmove ) ? 1 : 0;

      $dbgmsg .= ".TL:load_cache_tournament_ladder($tid,$with_tp,$limit)";
      $group_id = "TLadder.$tid";
      $allkey = "TLadder.$tid.$with_tp.L0";
      $key = "TLadder.$tid.$with_tp.L$limit";

      $tl_iterator = new ListIterator( $dbgmsg, null, 'ORDER BY Rank ASC', ($limit > 0 ? "LIMIT $limit" : '') );

      $arr_tladder = DgsCache::fetch( $dbgmsg, CACHE_GRP_TLADDER, $key ); // cached with limit?
      if ( $limit > 0 && is_null($arr_tladder) )
         $arr_tladder = DgsCache::fetch( $dbgmsg, CACHE_GRP_TLADDER, $allkey ); // perhaps cached without limit?
      if ( is_null($arr_tladder) )
      {
         $tl_iterator->addQuerySQLMerge( new QuerySQL(
               SQLP_FIELDS, 'TLP.ID AS TLP_ID', 'TLP.Name AS TLP_Name', 'TLP.Handle AS TLP_Handle',
                            'TLP.OnVacation AS TLP_OnVacation',
                            'TLP.Country AS TLP_Country', 'TLP.Rating2 AS TLP_Rating2',
                            'UNIX_TIMESTAMP(TLP.Lastaccess) AS TLP_X_Lastaccess',
               SQLP_FROM,   'INNER JOIN Players AS TLP ON TLP.ID=TL.uid'
            ));
         if ( $with_tp )
            $tl_iterator->addQuerySQLMerge( new QuerySQL(
                  SQLP_FIELDS, 'TP.Rating AS TP_Rating', 'TP.Finished AS TP_Finished',
                               'UNIX_TIMESTAMP(TP.Lastmoved) AS TP_X_Lastmoved',
                  SQLP_FROM,   'INNER JOIN TournamentParticipant AS TP ON TP.tid=TL.tid AND TP.ID=TL.rid'
               ));
         if ( $with_index )
            $tl_iterator->addIndex( 'uid', 'Rank' );
         $tl_iterator = self::load_tournament_ladder( $tl_iterator, $tid );

         DgsCache::store( $dbgmsg, CACHE_GRP_TLADDER, $key, $tl_iterator->getItemRows(), SECS_PER_HOUR, $group_id );
      }
      else // transform cache-stored row-arr into ListIterator of TournamentLadder
      {
         if ( $with_index )
            $tl_iterator->addIndex( 'uid', 'Rank' );
         $show_rows = $limit;
         foreach ( $arr_tladder as $row )
         {
            if ( $limit > 0 && $show_rows-- <= 0 ) // need only $limit rows
               break;
            $tladder = self::new_from_row( $row );
            $tl_iterator->addItem( $tladder, $row );
         }
      }

      return $tl_iterator;
   }//load_cache_tournament_ladder

   /*! \brief Returns TournamentLadder.Rank for given user. */
   public static function determine_ladder_rank( $iterator, $uid )
   {
      $result_rank = 0;
      while ( list(,$arr_item) = $iterator->getListIterator() )
      {
         $tladder = $arr_item[0];
         if ( $tladder->uid == $uid )
         {
            $result_rank = $tladder->Rank;
            break;
         }
      }
      $iterator->resetListIterator();

      return $result_rank;
   }//determine_ladder_rank

   public static function delete_cache_tournament_ladder( $dbgmsg, $tid )
   {
      DgsCache::delete_group( $dbgmsg, CACHE_GRP_TLADDER, "TLadder.$tid" );
   }

   /*!
    * \brief Returns theoretical position for given rating in ladder ordered by user rating.
    * \return ladder-position; or 0 if given rating greater than all ladder-users-ratings
    *
    * \note sync with TournamentLadderProps.determine_ladder_rating_pos()
    */
   public static function find_ladder_rating_pos( $dbgmsg, $tid, $tl_props, $rating )
   {
      if ( $tl_props->ChallengeRangeRating == TLADDER_CHRNG_RATING_UNUSED
            || (string)$rating == '' || (int)$rating <= NO_RATING )
         return 0;

      // count of ladder-users with rating >= given-rating
      $row = mysql_single_fetch( $dbgmsg.".TL:find_ladder_rating_pos($tid,$rating)",
         "SELECT COUNT(*) AS X_Count FROM TournamentLadder AS TL INNER JOIN Players AS TLP ON TLP.ID=TL.uid " .
         "WHERE TL.tid=$tid AND TLP.Rating2 >= $rating" );
      return ($row) ? (int)$row['X_Count'] : 0;
   }//find_ladder_rating_pos

   /*! \brief Determines theoretical ladder position if ordered by user-rating, set in TournamentLadder->UserRatingPos. */
   public static function compute_user_rating_pos_tournament_ladder( $tl_iterator )
   {
      $arr = array(); // use other array for sorting to preserve order of given iterator
      while ( list(,$arr_item) = $tl_iterator->getListIterator() )
      {
         list( $tladder, $orow ) = $arr_item;
         $arr[$tladder->uid] = $orow['TLP_Rating2'];
      }
      $tl_iterator->resetListIterator();

      arsort($arr);
      $pos = 0;
      foreach( $arr as $uid => $rating )
         $arr[$uid] = ++$pos;

      while ( list(,$arr_item) = $tl_iterator->getListIterator() )
      {
         $tladder = $arr_item[0];
         $tladder->UserRatingPos = $arr[$tladder->uid];
      }
      $tl_iterator->resetListIterator();
   }//compute_user_rating_pos_tournament_ladder

} // end of 'TournamentLadder'
?>
