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
require_once 'include/std_functions.php';
require_once 'include/utilities.php';
require_once 'include/time_functions.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_utils.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_games.php';

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
                  'ChallengesIn', 'ChallengesOut',
      FTYPE_DATE, 'Created', 'RankChanged'
   );

class TournamentLadder
{
   var $tid;
   var $rid;
   var $uid;
   var $Created;
   var $RankChanged;
   var $Rank;
   var $BestRank;
   var $StartRank;
   var $PeriodRank;
   var $HistoryRank;
   var $ChallengesIn;
   var $ChallengesOut;

   // non-DB fields

   /*! \brief true if challenge allowed on this user, also if max. outgoing challenges reached. */
   var $AllowChallenge;
   /*! \brief true, if max. number of incoming challenges reached for ladder-user. */
   var $MaxChallengedIn;
   /*! \brief true, if max. number of outgoing challenges reached for ladder-user. */
   var $MaxChallengedOut;
   /*! \brief array of TournamentGames-object of incoming-challenges: arr( TG.ID => TG ); TG with TG.RankRef added. */
   var $RunningTourneyGames;
   /*! \brief how many hours to wait till rematch allowed with same user; -1=rematch allowed, 0=TG still on WAIT-status but due. */
   var $RematchWait;

   /*! \brief Constructs TournamentLadder-object with specified arguments. */
   function TournamentLadder( $tid=0, $rid=0, $uid=0, $created=0, $rank_changed=0, $rank=0, $best_rank=0,
         $start_rank=0, $period_rank=0, $history_rank=0, $challenges_in=0, $challenges_out=0 )
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
      // non-DB fields
      $this->AllowChallenge = false;
      $this->MaxChallengedIn = false;
      $this->MaxChallengedOut = false;
      $this->RunningTourneyGames = array();
      $this->RematchWait = -1;
   }

   /*! \brief Adds TournamentGames-object to list of incoming challenge (running) games. */
   function add_running_game( $tgame )
   {
      if( !is_a($tgame, 'TournamentGames') )
         error('invalid_args', "TournamentLadder.add_running_game({$this->tid},{$this->rid})");
      $this->RunningTourneyGames[$tgame->ID] = $tgame;
   }

   /*! \brief Returns list of running TournamentGames-objects ordered by TG.ID, that is creation-order (first=first-created). */
   function get_running_games()
   {
      ksort( $this->RunningTourneyGames, SORT_NUMERIC );
      return $this->RunningTourneyGames;
   }

   /*! \brief Returns non-null array with "[#Rank]" linked to game-id for running tourney-games. */
   function build_linked_running_games()
   {
      $arr = array();
      if( count($this->RunningTourneyGames) )
      {
         global $base_path;

         foreach( $this->get_running_games() as $tgid => $tgame )
         {
            $rank = $tgame->Challenger_tladder->Rank;
            $arr[] = sprintf( '[%s]', anchor( $base_path."game.php?gid={$tgame->gid}", "#$rank" ));
         }
      }
      return $arr;
   }

   function to_string()
   {
      return print_r($this, true);
   }

   function build_rank_kept( $timefmt=null, $zero_val='' )
   {
      if( $this->RankChanged <= 0 )
         return $zero_val;

      if( is_null($timefmt) )
         $timefmt = TIMEFMT_SHORT|TIMEFMT_ZERO;
      return TimeFormat::echo_time(
         round(($GLOBALS['NOW'] - $this->RankChanged)/SECS_PER_HOUR), $timefmt, '0' );
   }

   function insert()
   {
      $this->Created = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->insert( "TournamentLadder.insert(%s)" );
   }

   function update()
   {
      $entityData = $this->fillEntityData();
      return $entityData->update( "TournamentLadder.update(%s)" );
   }

   function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "TournamentLadder.delete(%s)" );
   }

   function fillEntityData( &$data=null )
   {
      if( is_null($data) )
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
      return $data;
   }

   /*! \brief Updates this TournamentLadder-instance with new rank, BestRank, RankChanged and persist into DB. */
   function update_rank( $new_rank, $upd_rank=true )
   {
      $this->Rank = (int)$new_rank;
      if( $this->Rank < $this->BestRank )
         $this->BestRank = $new_rank;
      if( $upd_rank )
         $this->RankChanged = $GLOBALS['NOW'];
      return $this->update();
   }

   /*! \brief Increases or decreases TournamentLadder.ChallengesIn by given amount. */
   function update_incoming_challenges( $diff )
   {
      if( !is_numeric($diff) || $diff == 0 )
         error('invalid_args', "TournamentLadder.update_incoming_challenges.check.diff({$this->rid},$diff)");

      $table = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->table;
      $op = ($diff > 0) ? "+$diff" : '-'.abs($diff);
      $result = db_query( "TournamentLadder.update_incoming_challenges.update({$this->rid},$diff)",
         "UPDATE $table SET ChallengesIn=ChallengesIn $op "
            . "WHERE tid={$this->tid} AND rid={$this->rid} LIMIT 1" );
      if( $result )
         $this->ChallengesIn += $diff;
      return $result;
   }

   /*! \brief Increases or decreases TournamentLadder.ChallengesOut by given amount. */
   function update_outgoing_challenges( $diff )
   {
      if( !is_numeric($diff) || $diff == 0 )
         error('invalid_args', "TournamentLadder.update_outgoing_challenges.check.diff({$this->rid},$diff)");

      $table = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->table;
      $op = ($diff > 0) ? "+$diff" : '-'.abs($diff);
      $result = db_query( "TournamentLadder.update_outgoing_challenges.update({$this->rid},$diff)",
         "UPDATE $table SET ChallengesOut=ChallengesOut $op "
            . "WHERE tid={$this->tid} AND rid={$this->rid} LIMIT 1" );
      if( $result )
         $this->ChallengesOut += $diff;
      return $result;
   }

   /*!
    * \brief Removes user from ladder with given tournament tid and remove TP if $remove_all=true.
    * \note ignoring running games for tournament
    */
   function remove_user_from_ladder( $remove_all, $upd_rank=false )
   {
      ta_begin();
      {//HOT-section to remove user from ladder and eventually from TournamentParticipant-table
         $table = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->table;
         $table2 = $GLOBALS['ENTITY_TOURNAMENT_PARTICIPANT']->table; // needed for nested-lock for process-game-end
         db_lock( "TournamentLadder.remove_user_from_ladder({$this->tid},{$this->rid})",
            "$table WRITE, $table AS TL READ, $table2 WRITE" );
         {//LOCK TournamentLadder
            $this->delete();
            $is_deleted = ( TournamentLadder::load_rank($this->tid, $this->rid) == 0 );
            if( $is_deleted )
               TournamentLadder::move_up_ladder_part($this->tid, $upd_rank, $this->Rank, 0);
         }

         if( $remove_all && $is_deleted )
            TournamentParticipant::delete_tournament_participant($this->tid, $this->rid);

         db_unlock();
      }
      ta_end();

      return $is_deleted;
   }//remove_user_from_ladder

   /*! \brief Changes rank of current user to new-rank (moving up or down). */
   function change_user_rank( $new_rank )
   {
      if( $this->Rank == $new_rank ) // no rank-change
         return true;

      $table = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->table;
      db_lock( "TournamentLadder.change_user_rank({$this->tid},$new_rank)",
         "$table WRITE, $table AS TL READ" );
      {//LOCK TournamentLadder
         $tl2 = TournamentLadder::load_tournament_ladder_by_rank($this->tid, $new_rank);
         if( is_null($tl2) )
            error('bad_tournament', "TournamentLadder.change_user_rank.load2({$this->tid},$new_rank)");

         $success = false;
         if( abs($this->Rank - $tl2->Rank ) == 1 ) // switch direct neighbours
            $success = $this->switch_user_rank( $tl2, false );
         else
         {
            ta_begin();
            {//HOT-section to update ladder
               if( $this->Rank > $new_rank ) // user-move-up
                  TournamentLadder::move_down_ladder_part( $this->tid, false, $new_rank, $this->Rank - 1 );
               else // user-move-down
                  TournamentLadder::move_up_ladder_part( $this->tid, false, $this->Rank + 1, $new_rank );

               $success = $this->update_rank( $new_rank, false );
            }
            ta_end();
         }
      }
      db_unlock();

      return $success;
   }

   /*! \brief Switch ranks of two users (this and given TournamentLadder-object). */
   function switch_user_rank( $tlsw, $upd_rank )
   {
      if( $this->tid != $tlsw->tid )
         error('invalid_args', "TournamentLadder.switch_user_rank.diff_tid({$this->tid},{$tlsw->tid})");
      if( $this->rid == $tlsw->rid )
         error('invalid_args', "TournamentLadder.switch_user_rank.same_user({$this->tid},{$this->rid})");
      if( $this->Rank == $tlsw->Rank )
         error('invalid_args', "TournamentLadder.switch_user_rank.same_rank({$this->tid},{$this->Rank})");

      swap( $this->Rank, $tlsw->Rank );
      $this->BestRank = TournamentUtils::calc_best_rank($this->BestRank, $this->Rank);
      $tlsw->BestRank = TournamentUtils::calc_best_rank($tlsw->BestRank, $tlsw->Rank);
      if( $upd_rank )
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
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of TournamentLadder-objects for given tournament-id. */
   function build_query_sql( $tid=0 )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->newQuerySQL('TL');
      if( $tid > 0 )
         $qsql->add_part( SQLP_WHERE, "TL.tid='$tid'" );
      return $qsql;
   }

   /*! \brief Returns TournamentLadder-object created from specified (db-)row. */
   function new_from_row( $row )
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
            @$row['ChallengesOut']
         );
      return $tl;
   }

   /*! \brief Returns max Rank of TournamentLadder for given tournament-id; 0 if no entries found. */
   function load_max_rank( $tid )
   {
      $qsql = TournamentLadder::build_query_sql( $tid );
      $qsql->clear_parts( SQLP_FIELDS );
      $qsql->add_part( SQLP_FIELDS, 'IFNULL(MAX(TL.Rank),0) AS X_Rank' );
      $row = mysql_single_fetch( "TournamentLadder::load_max_rank($tid)", $qsql->get_select() );
      return ( $row ) ? (int)@$row['X_Rank'] : 0;
   }

   /*! \brief Count TournamentLadder-entries for given tournament-id; 0 if no entries found. */
   function count_tournament_ladder( $tid )
   {
      $qsql = TournamentLadder::build_query_sql( $tid );
      $qsql->clear_parts( SQLP_FIELDS );
      $qsql->add_part( SQLP_FIELDS, 'COUNT(*) AS X_Count' );
      $row = mysql_single_fetch( "TournamentLadder::count_tournament_ladder($tid)", $qsql->get_select() );
      return ( $row ) ? (int)@$row['X_Count'] : 0;
   }

   /*! \brief Returns current Rank of given for given RID or else UID or else current player; 0 if user not on ladder. */
   function load_rank( $tid, $rid=0, $uid=0 )
   {
      $qsql = TournamentLadder::build_query_sql( $tid );
      $qsql->clear_parts( SQLP_FIELDS );
      $qsql->add_part( SQLP_FIELDS, 'TL.Rank' );
      if( $rid > 0 )
         $qsql->add_part( SQLP_WHERE, "TL.rid=$rid" );
      elseif( $uid > 0 )
         $qsql->add_part( SQLP_WHERE, "TL.uid=$uid" );
      else
         $qsql->add_part( SQLP_WHERE, 'TL.uid=' . (int)@$GLOBALS['player_row']['ID'] );
      $qsql->add_part( SQLP_LIMIT, '1');

      $row = mysql_single_fetch( "TournamentLadder::load_rank($tid,$rid,$uid)", $qsql->get_select() );
      return ( $row ) ? (int)@$row['Rank'] : 0;
   }

   /*!
    * \brief Loads and returns TournamentLadder-object for given tournament-ID and user-id;
    * \return NULL if nothing found; TournamentLadder otherwise
    */
   function load_tournament_ladder_by_user( $tid, $uid, $rid=0 )
   {
      if( $tid <=0 || ($uid <= GUESTS_ID_MAX && $rid <= 0) )
         return NULL;

      $qsql = TournamentLadder::build_query_sql( $tid );
      if( $uid > GUESTS_ID_MAX )
         $qsql->add_part( SQLP_WHERE, "TL.uid=$uid" );
      else
         $qsql->add_part( SQLP_WHERE, "TL.rid=$rid" );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "TournamentLadder::load_tournament_ladder_by_user($tid,$uid,$rid)",
         $qsql->get_select() );
      return ($row) ? TournamentLadder::new_from_row($row) : NULL;
   }

   /*! \brief Loads and returns TournamentLadder-object for given tournament-ID and rank; NULL if nothing found. */
   function load_tournament_ladder_by_rank( $tid, $rank )
   {
      if( $tid <=0 || $rank <= 0 )
         return NULL;

      $qsql = TournamentLadder::build_query_sql( $tid );
      $qsql->add_part( SQLP_WHERE, "TL.Rank=$rank" );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "TournamentLadder::load_tournament_ladder_by_rank($tid,$rank)",
         $qsql->get_select() );
      return ($row) ? TournamentLadder::new_from_row($row) : NULL;
   }

   /*! \brief Returns enhanced (passed) ListIterator with TournamentLadder-objects for given tournament-id. */
   function load_tournament_ladder( $iterator, $tid=-1 )
   {
      $qsql = ( $tid >= 0 ) ? TournamentLadder::build_query_sql( $tid ) : new QuerySQL();
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentLadder::load_tournament_ladder", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $tladder = TournamentLadder::new_from_row( $row );
         $iterator->addItem( $tladder, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

   /*! \brief Adds user given by User-object for tournament tid at bottom of ladder. */
   function add_user_to_ladder( $tid, $uid )
   {
      if( $uid <= GUESTS_ID_MAX )
         error('invalid_user', "TournamentLadder::add_user_to_ladder.check_user($tid)");

      $tp = TournamentParticipant::load_tournament_participant( $tid, $uid, 0 );
      if( is_null($tp) )
         error('invalid_args', "TournamentLadder::add_user_to_ladder.load_tp($tid,$uid)");

      // check pre-conditions
      if( TournamentLadder::load_rank($tid, 0, $uid) > 0 )
         return true; // already joined ladder
      if( $tp->Status != TP_STATUS_REGISTER )
         error('tournament_participant_invalid_status',
               "TournamentLadder::add_user_to_ladder.check_tp_status($tid,$uid,{$tp->Status})");

      return TournamentLadder::add_participant_to_ladder( $tid, $tp->ID, $uid );
   }

   /*! \brief Adds TP configured by rid,uid for tournament tid at bottom of ladder. */
   function add_participant_to_ladder( $tid, $rid, $uid )
   {
      static $query_next_rank = "IFNULL(MAX(Rank),0)+1"; // must result in 1 result-row
      $NOW = $GLOBALS['NOW'];
      $table = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->table;

      // defaults: RankChanged=0, ChallengesIn=0
      $query = "INSERT INTO $table (tid,rid,uid,Created,Rank,BestRank,StartRank,PeriodRank,HistoryRank) "
             . "SELECT $tid, $rid, $uid, FROM_UNIXTIME($NOW), "
                  . "$query_next_rank AS Rank, "
                  . "$query_next_rank AS BestRank, "
                  . "$query_next_rank AS StartRank, "
                  . "$query_next_rank AS PeriodRank, "
                  . "$query_next_rank AS HistoryRank "
             . "FROM $table WHERE tid=$tid";
      return db_query( "TournamentLadder::add_participant_to_ladder.insert(tid[$tid],rid[$rid],uid[$uid])", $query );
   }

   /*!
    * \brief Moves all tourney-users one rank up for incl. rank-range; min/max=0 separately or both for all.
    * \param $upd_rank true if RankChanged should be set too
    */
   function move_up_ladder_part( $tid, $upd_rank, $min_rank, $max_rank )
   {
      if( !is_numeric($tid) || !is_numeric($min_rank) || !is_numeric($max_rank) )
         error('invalid_args', "TournamentLadder::move_up_ladder_part.check_tid($tid,$min_rank,$max_rank)");

      global $NOW;
      $query = "UPDATE TournamentLadder SET Rank=Rank-1, "
            . "BestRank=LEAST(BestRank,Rank) " // NOTE: 'Rank' is the updated field-value, so actually Rank-1
            . ( $upd_rank ? ", RankChanged=FROM_UNIXTIME($NOW) " : '' )
            . "WHERE tid=$tid "
            . TournamentUtils::build_num_range_sql_clause('Rank', $min_rank, $max_rank, 'AND');
      return db_query( "TournamentLadder::move_up_ladder_part.update($tid,$upd_rank,$min_rank,$max_rank)", $query );
   }

   /*!
    * \brief Moves all tourney-users one rank down for incl. rank-range; min/max=0 separately or both for all.
    * \param $upd_rank true if RankChanged should be set too
    */
   function move_down_ladder_part( $tid, $upd_rank, $min_rank, $max_rank )
   {
      if( !is_numeric($tid) || !is_numeric($min_rank) || !is_numeric($max_rank) )
         error('invalid_args', "TournamentLadder::move_down_ladder_part.check_tid($tid,$min_rank,$max_rank)");

      global $NOW;
      $query = "UPDATE TournamentLadder SET Rank=Rank+1, "
            . "BestRank=LEAST(BestRank,Rank) " // NOTE: 'Rank' is the updated field-value, so actually Rank+1
            . ( $upd_rank ? ", RankChanged=FROM_UNIXTIME($NOW) " : '' )
            . "WHERE tid=$tid "
            . TournamentUtils::build_num_range_sql_clause('Rank', $min_rank, $max_rank, 'AND');
      return db_query( "TournamentLadder::move_down_ladder_part.update($tid,$upd_rank,$min_rank,$max_rank)", $query );
   }

   /*! \brief Delete complete ladder for given tournament-id. */
   function delete_ladder( $tid )
   {
      $table = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->table;
      $query = "DELETE FROM $table WHERE tid=$tid";
      return db_query( "TournamentLadder::delete_ladder(tid[$tid])", $query );
   }

   /*!
    * \brief Seeds ladder with all registered TPs handling already joined users.
    * \param $reorder true = reorder already joined users according to $seed_order;
    *        false = append new users below existing users
    */
   function seed_ladder( $tourney, $tprops, $seed_order, $reorder=false )
   {
      if( !is_a($tourney, 'Tournament') && $tourney->ID <= 0 )
         error('unknown_tournament', "TournamentLadder::seed_ladder.check_tid($seed_order)");
      $tid = $tourney->ID;

      list( $def, $arr_seed_order ) = $tprops->build_seed_order();
      if( !isset($arr_seed_order[$seed_order]) )
         error('invalid_args', "TournamentLadder::seed_ladder.check_seed_order($tid,$seed_order)");

      // load already joined ladder-users
      $tl_iterator = new ListIterator( "TournamentLadder::seed_ladder.load_ladder($tid)" );
      $tl_iterator->addIndex( 'uid' );
      $tl_iterator = TournamentLadder::load_tournament_ladder( $tl_iterator, $tid );

      // find all registered TPs (optimized)
      $arr_TPs = TournamentParticipant::load_registered_users_in_seedorder( $tid, $seed_order );

      // add all TPs to ladder
      $NOW = $GLOBALS['NOW'];
      $data = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->newEntityData();
      $arr_inserts = array();

      $table = $data->entity->table;
      db_lock( "TournamentLadder::seed_ladder($tid,$seed_order)",
         "$table WRITE, $table AS TL READ" );
      {//LOCK TournamentLadder
         $rank = ($reorder) ? 1 : TournamentLadder::load_max_rank($tid) + 1;
         foreach( $arr_TPs as $row )
         {
            $uid = $row['uid'];
            $tladder = $tl_iterator->getIndexValue( 'uid', $uid, 0 );
            if( is_null($tladder) ) // user not joined ladder yet
            {
               $tladder = new TournamentLadder( $tid, $row['rid'], $uid, $NOW, 0,
                  $rank, $rank, $rank, $rank, $rank );
            }
            else // user already joined ladder
            {
               if( !$reorder )
                  continue; // no reorder -> skip already joined user (no rank-inc)
               if( $tladder->Rank == $rank )
               {
                  ++$rank;
                  continue; // no update (same rank), but rank-inc
               }
               $tladder->Rank = $rank;
               $tladder->BestRank = TournamentUtils::calc_best_rank( $rank, $tladder->BestRank );
               $tladder->RankChanged = $NOW;
            }

            $tladder->fillEntityData( $data );
            $arr_inserts[] = $data->build_sql_insert_values();
            ++$rank;
         }
         unset($arr_TPs);
         unset($tl_iterator);

         // insert all registered TPs to ladder
         $cnt = count($arr_inserts);
         $seed_query = $data->build_sql_insert_values(true) . implode(',', $arr_inserts)
            . " ON DUPLICATE KEY UPDATE Rank=VALUES(Rank), BestRank=VALUES(BestRank), "
            . " RankChanged=VALUES(RankChanged)";
         $result = db_query( "TournamentLadder::seed_ladder.insert($tid,$seed_order,$reorder,#$cnt)",
            $seed_query );
      }
      db_unlock();

      return $result;
   }//seed_ladder

   /*!
    * \brief Checks if all participants (arr_TPS[rid=>uid]) are registered in ladder;
    *        auto-removing bad registrations.
    */
   function check_participant_registrations( $tid, $arr_TPs )
   {
      $table = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->table;
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS, 'rid', 'uid' );
      $qsql->add_part( SQLP_FROM,   $table );
      $qsql->add_part( SQLP_WHERE,  "tid=$tid" );
      $result = db_query( "TournamentLadder::check_participant_registrations.load_ladder($tid)",
         $qsql->get_select() );

      $arr_miss = array() + $arr_TPs; // registered TP, but not in ladder
      $arr_bad  = array(); // user in ladder, but not a registered TP
      while( $row = mysql_fetch_array($result) )
      {
         $rid = $row['rid'];
         $uid = $row['uid'];
         if( isset($arr_TPs[$rid]) )
            unset($arr_miss[$rid]);
         else
            $arr_bad[$rid] = $uid;
      }
      mysql_free_result($result);

      global $base_path;
      $errors = array();
      if( count($arr_miss) )
      {
         $arr = array();
         foreach( $arr_miss as $rid => $uid )
            $arr[] = anchor( $base_path."tournaments/ladder/admin.php?tid=$tid".URI_AMP."uid=$uid", $uid );
         $errors[] = T_('Found registered tournament participants not added to ladder')
            . "<br>\n" . sprintf( T_('users [%s]#T_ladder'), implode(', ', $arr) );
      }
      if( count($arr_bad) )
      {
         $arr = array();
         foreach( $arr_bad as $rid => $uid )
            $arr[] = anchor( $base_path."tournaments/edit_participant.php?tid=$tid".URI_AMP."uid=$uid", $uid );
         $errors[] = T_('Auto-removing of found users added to ladder without registration')
            . "<br>\n" . sprintf( T_('users [%s]#T_ladder'), implode(', ', $arr) );

         // removing bad ladder-registrations
         foreach( $arr_bad as $rid => $uid )
         {
            $tladder = new TournamentLadder( $tid, $rid );
            $tladder->delete();
         }
      }
      return $errors;
   }//check_participant_registrations

   function new_tournament_game( $tid, $tladder_ch, $tladder_df )
   {
      $tg = new TournamentGames( 0, $tid );
      $tg->Challenger_uid = $tladder_ch->uid;
      $tg->Challenger_rid = $tladder_ch->rid;
      $tg->Defender_uid   = $tladder_df->uid;
      $tg->Defender_rid   = $tladder_df->rid;
      return $tg;
   }

   /*! \brief Processes tournament-game-end for given tournament-game $tgame and game-end-action. */
   function process_game_end( $tid, $tgame, $game_end_action )
   {
      if( $game_end_action == TGEND_NO_CHANGE )
         return true;

      $ch_rid = $tgame->Challenger_rid;
      $df_rid = $tgame->Defender_rid;

      $table = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->table;
      db_lock( "TournamentLadder.process_game_end($tid,$ch_rid,$df_rid,$game_end_action)",
         "$table WRITE, $table AS TL READ" );
      {//LOCK TournamentLadder
         ta_begin();
         {//HOT-section to update ladder (besides reading)
            $success = TournamentLadder::_process_game_end( $tid, $ch_rid, $df_rid, $game_end_action );
         }
         ta_end();
      }
      db_unlock();

      return $success;
   }//process_game_end

   /*!
    * \brief (INTERNAL) processing tournament-game-end, called from process_game_end()-func with already locked TL-table.
    * \internal
    * \note Special change-user-rank, handling missing challenger/defender cases, also updating BestRank + RankChanged-fields
    */
   function _process_game_end( $tid, $ch_rid, $df_rid, $game_end_action )
   {
      $tladder_df = null;
      if( $game_end_action != TGEND_CHALLENGER_LAST && $game_end_action != TGEND_CHALLENGER_DELETE )
      {
         $tladder_df = TournamentLadder::load_tournament_ladder_by_user($tid, 0, $df_rid);
         if( is_null($tladder_df) ) // defender not longer on ladder -> nothing to do
            return true;
      }

      $tladder_ch = null;
      if( $game_end_action != TGEND_DEFENDER_LAST && $game_end_action != TGEND_DEFENDER_DELETE )
      {
         $tladder_ch = TournamentLadder::load_tournament_ladder_by_user($tid, 0, $ch_rid);
         if( is_null($tladder_ch) ) // challenger not longer on ladder -> nothing to do
            return true;
      }

      // process game-end
      $success = true;
      switch( (string)$game_end_action )
      {
         case TGEND_CHALLENGER_ABOVE:
         {
            $logmsg = "CH.Rank={$tladder_ch->Rank}";
            $ch_new_rank = $tladder_df->Rank;
            if( $tladder_ch->Rank > $ch_new_rank ) // challenger below new pos
            {
               TournamentLadder::move_down_ladder_part( $tid, true, $ch_new_rank, $tladder_ch->Rank - 1 );
               $success = $tladder_ch->update_rank( $ch_new_rank );
               $logmsg .= ">$ch_new_rank";
            }
            break;
         }

         case TGEND_CHALLENGER_BELOW:
         {
            $logmsg = "CH.Rank={$tladder_ch->Rank}";
            $ch_new_rank = $tladder_df->Rank + 1;
            if( $tladder_ch->Rank > $ch_new_rank ) // challenger below new pos
            {
               if( abs($tladder_ch->Rank - $tladder_df->Rank) > 1 ) // direct neighbours?
                  TournamentLadder::move_down_ladder_part( $tid, true, $ch_new_rank, $tladder_ch->Rank - 1 );
               $success = $tladder_ch->update_rank( $ch_new_rank );
               $logmsg .= ">$ch_new_rank";
            }
            break;
         }

         case TGEND_CHALLENGER_LAST:
         {
            $logmsg = "CH.Rank={$tladder_ch->Rank}";
            $ch_new_rank = TournamentLadder::load_max_rank( $tid ); // get last ladder-rank
            if( $tladder_ch->Rank < $ch_new_rank ) // challenger above new (last) ladder-pos
            {
               TournamentLadder::move_up_ladder_part( $tid, true, $tladder_ch->Rank + 1, $ch_new_rank );
               $success = $tladder_ch->update_rank( $ch_new_rank );
               $logmsg .= ">LAST$ch_new_rank";
            }
            break;
         }

         case TGEND_CHALLENGER_DELETE:
         {
            $success = $tladder_ch->remove_user_from_ladder( true, true/*upd-rank*/ );
            $logmsg = "CH.Rank={$tladder_ch->Rank}>DEL";

            if( $success )
               TournamentLadder::notify_removed_user( "TournamentLadder::_process_game_end($game_end_action)",
                  $tid, $tladder_ch->uid, null );
            break;
         }

         case TGEND_SWITCH:
         {
            $logmsg = "CH.Rank={$tladder_ch->Rank}";
            $ch_new_rank = $tladder_df->Rank;
            if( $tladder_ch->Rank > $ch_new_rank ) // challenger below new pos
            {
               $success = $tladder_ch->switch_user_rank( $tladder_df, true );
               $logmsg .= "><DF.Rank={$tladder_ch->Rank}";
            }
            break;
         }

         case TGEND_DEFENDER_BELOW:
         {
            $logmsg = "DF.Rank={$tladder_df->Rank}";
            $df_new_rank = $tladder_ch->Rank;
            if( $tladder_df->Rank < $df_new_rank ) // defender above new pos
            {
               if( abs($tladder_ch->Rank - $tladder_df->Rank) > 1 ) // direct neighbours?
                  TournamentLadder::move_up_ladder_part( $tid, true, $tladder_df->Rank + 1, $df_new_rank );
               $success = $tladder_df->update_rank( $df_new_rank );
               $logmsg .= ">$df_new_rank";
            }
            break;
         }

         case TGEND_DEFENDER_LAST:
         {
            $logmsg = "DF.Rank={$tladder_df->Rank}";
            $df_new_rank = TournamentLadder::load_max_rank( $tid ); // get last ladder-rank
            if( $tladder_df->Rank < $df_new_rank ) // defender above new (last) ladder-pos
            {
               TournamentLadder::move_up_ladder_part( $tid, true, $tladder_df->Rank + 1, $df_new_rank );
               $success = $tladder_df->update_rank( $df_new_rank );
               $logmsg .= ">LAST$df_new_rank";
            }
            break;
         }

         case TGEND_DEFENDER_DELETE:
         {
            $success = $tladder_df->remove_user_from_ladder( true, true/*upd-rank*/ );
            $logmsg = "DF.Rank={$tladder_df->Rank}>DEL";

            if( $success ) // notify removed user
               TournamentLadder::notify_removed_user( "TournamentLadder::_process_game_end($game_end_action)",
                  $tid, $tladder_df->uid, null );
            break;
         }
      }//switch(game_end_action)

      if( DBG_QUERY )
         error_log("TournamentLadder.process_game_end($tid,$ch_rid,$df_rid,$game_end_action): $logmsg");

      return $success;
   }//_process_game_end

   /*!
    * \brief Sends notify to removed-user.
    * \param $main_body null=game-end-handling, otherwise body-header-text given as arg
    */
   function notify_removed_user( $dbgmsg, $tid, $uid, $main_body=null )
   {
      if( is_null($main_body) )
         $body = sprintf( T_('The system has removed you from %s due to the ladder configuration defined for tournament game-ends.#tourney'),
                          "<tourney $tid>" );
      else
         $body = $main_body;

      return send_message( "$dbgmsg.notify($tid,$uid)",
         trim( $body . "\n" . TournamentLadder::get_notes_user_removed() ),
         sprintf( T_('Removal from tournament #%s'), $tid ),
         $uid, '', true,
         0/*sys-msg*/, 'NORMAL', 0 );
   }

   /*!
    * \brief Processes long absence of user not being online by removing user from ladder.
    * \note expecting to run into HOT-section
    */
   function process_user_absence( $tid, $uid, $user_abs_days )
   {
      // reload TL because Rank could have been changed in the meantime !!
      $tladder = TournamentLadder::load_tournament_ladder_by_user( $tid, $uid );
      if( is_null($tladder) )
         return true;

      // remove user from ladder
      $success = $tladder->remove_user_from_ladder( true, true/*upd-rank*/ );
      $logmsg = "U.Rank={$tladder->Rank}>DEL";

      if( DBG_QUERY )
         error_log("TournamentLadder::process_user_absence($tid,$uid): $logmsg");

      // notify removed user
      if( $success )
      {
         TournamentLadder::notify_removed_user( "TournamentLadder::process_user_absence", $tid, $uid,
            sprintf( T_('The system has removed you from %s due to inactivity for more than %s days.#tourney'),
                     "<tourney $tid>", $user_abs_days ) );
      }

      return $success;
   }//process_user_absence

   /*! \brief Copies PeriodRank over to HistoryRank-field and Rank to PeriodRank-field. */
   function process_rank_period( $tid )
   {
      if( !is_numeric($tid) || $tid <= 0 )
         error('invalid_args', "TournamentLadder::process_rank_period.check.tid($tid)");

      return db_query( "TournamentLadder::process_rank_period($tid)",
         "UPDATE TournamentLadder SET HistoryRank=PeriodRank, PeriodRank=Rank WHERE tid=$tid" );
   }

   /*! \brief Returns true if edit-ladder is allowed concerning tourney-locks. */
   function allow_edit_ladder( $tourney, &$return_errors )
   {
      $errors = array();

      // check admin-lock
      $is_admin = TournamentUtils::isAdmin();
      $is_admin_lock = $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN);
      if( !$is_admin && $is_admin_lock )
         $errors[] = $tourney->buildAdminLockText();

      // check other locks
      // NOTE: being T-Admin + Admin-lock overwrites other locks
      if( !( $is_admin && $is_admin_lock ) )
      {
         if( !$tourney->isFlagSet(TOURNEY_FLAG_LOCK_TDWORK) )
            $errors[] = Tournament::getLockText(TOURNEY_FLAG_LOCK_TDWORK);
         if( $tourney->isFlagSet(TOURNEY_FLAG_LOCK_CRON) )
            $errors[] = Tournament::getLockText(TOURNEY_FLAG_LOCK_CRON);
      }

      $return_errors = array_merge( $return_errors, $errors );
      return ( count($errors) == 0 );
   }

   /*! \brief Returns relative rank-diff-info for given ranks and format. */
   function build_rank_diff( $rank, $prev_rank, $fmt='%s. (%s)' )
   {
      // also see 'js/common.js buildRankDiff()'
      if( $rank == $prev_rank )
         $rank_diff = '=';
      elseif( $rank < $prev_rank )
         $rank_diff = '+' . ($prev_rank - $rank);
      else //$rank > $prev_rank
         $rank_diff = '-' . ($rank - $prev_rank);
      return sprintf( $fmt, $prev_rank, $rank_diff );
   }

   function get_notes_user_removed()
   {
      return T_('Your running tournament games will be continued as normal games without effecting the tournament.');
   }

   function get_rank_info_format()
   {
      return
         sprintf( "%s: %%s.<br>%s: %%s.<br>%s: %%s<br>%s: %%s",
            basic_safe(T_('Current Rank')),
            basic_safe(T_('Best Rank')),
            basic_safe(T_('Start of Period (Change)')),
            basic_safe(T_('Previous Period (Change)')) );
   }

   function get_edit_tournament_status()
   {
      static $statuslist = array( TOURNEY_STATUS_PAIR, TOURNEY_STATUS_PLAY );
      return $statuslist;
   }

   function get_view_ladder_status( $isTD=false )
   {
      static $statuslist_TD   = array( TOURNEY_STATUS_PAIR, TOURNEY_STATUS_PLAY, TOURNEY_STATUS_CLOSED );
      static $statuslist_user = array( TOURNEY_STATUS_PLAY, TOURNEY_STATUS_CLOSED );
      return ($isTD) ? $statuslist_TD : $statuslist_user;
   }

} // end of 'TournamentLadder'
?>
