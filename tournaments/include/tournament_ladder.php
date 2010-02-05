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
      FTYPE_INT,  'tid', 'rid', 'uid', 'Rank', 'BestRank', 'ChallengesIn',
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
   var $ChallengesIn;

   // non-DB fields

   /*! \brief true if challenge allowed on this user. */
   var $AllowChallenge;
   /*! \brief true, if max. number of incoming challenges reached for current tank. */
   var $MaxChallenged;
   /*! \brief array of TournamentGames-object of incoming-challenges: arr( TG.ID => TG ); TG with TG.RankRef added. */
   var $RunningTourneyGames;

   /*! \brief Constructs TournamentLadder-object with specified arguments. */
   function TournamentLadder( $tid=0, $rid=0, $uid=0, $created=0, $rank_changed=0, $rank=0, $bestrank=0,
         $challenges_in=0 )
   {
      $this->tid = (int)$tid;
      $this->rid = (int)$rid;
      $this->uid = (int)$uid;
      $this->Created = (int)$created;
      $this->RankChanged = (int)$rank_changed;
      $this->Rank = $rank;
      $this->BestRank = $bestrank;
      $this->ChallengesIn = $challenges_in;
      // non-DB fields
      $this->AllowChallenge = false;
      $this->MaxChallenged = false;
      $this->RunningTourneyGames = array();
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
      $data->set_value( 'ChallengesIn', $this->ChallengesIn );
      return $data;
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

   /*!
    * \brief Removes user given by User-object from ladder with given tournament tid and
    *        remove TP if $remove_all=true.
    * \return error-list (empty on success)
    */
   function remove_user_from_ladder( $remove_all )
   {
      //TODO do consistency-checks (no running games for tournament)
      //if( ... ) return array( T_('error...') );

      ta_begin();
      {//HOT-section to remove user from ladder and eventually from TournamentParticipant-table
         $table = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->table;
         db_lock( "TournamentLadder.remove_user_from_ladder({$this->tid},{$this->rid})",
            "$table WRITE, $table AS TL READ" );
         {//LOCK TournamentLadder
            $this->delete();
            $is_deleted = ( TournamentLadder::load_rank($this->tid, $this->rid) == 0 );
            if( $is_deleted )
               TournamentLadder::move_up_ladder_part($this->tid, $this->Rank);
         }
         db_unlock();

         if( $remove_all && $is_deleted )
            TournamentParticipant::delete_tournament_participant($this->tid, $this->rid);
      }
      ta_end();

      return array();
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
            $success = $this->switch_user_rank( $tl2 );
         else
         {
            ta_begin();
            {//HOT-section to update ladder
               if( $this->Rank > $new_rank ) // user-move-up
                  TournamentLadder::move_down_ladder_part( $this->tid, $new_rank, $this->Rank - 1 );
               else // user-move-down
                  TournamentLadder::move_up_ladder_part( $this->tid, $this->Rank + 1, $new_rank );

               $this->Rank = $new_rank;
               $success = $this->update();
            }
            ta_end();
         }
      }
      db_unlock();

      return $success;
   }

   /*! \brief Switch ranks of two users (this and given TournamentLadder-object). */
   function switch_user_rank( $tlsw, $trigger=false )
   {
      if( $this->tid != $tlsw->tid )
         error('invalid_args', "TournamentLadder.switch_user_rank.diff_tid({$this->tid},{$tlsw->tid})");
      if( $this->rid == $tlsw->rid )
         error('invalid_args', "TournamentLadder.switch_user_rank.same_user({$this->tid},{$this->rid})");
      if( $this->Rank == $tlsw->Rank )
         error('invalid_args', "TournamentLadder.switch_user_rank.same_rank({$this->tid},{$this->Rank})");

      swap( $this->Rank, $tlsw->Rank );
      if( $trigger )
      {
         $this->BestRank = TournamentUtils::calc_best_rank($this->BestRank, $this->Rank);
         $tlsw->BestRank = TournamentUtils::calc_best_rank($tlsw->BestRank, $tlsw->Rank);
         $this->RankChanged = $tlsw->RankChanged = $GLOBALS['NOW'];
      }
      else
      {
         if( $this->BestRank > 0 )
         {
            $this->BestRank = TournamentUtils::calc_best_rank($this->BestRank, $this->Rank);
            $this->RankChanged = $GLOBALS['NOW'];
         }
         if( $tlsw->BestRank > 0 )
         {
            $tlsw->BestRank = TournamentUtils::calc_best_rank($tlsw->BestRank, $tlsw->Rank);
            $tlsw->RankChanged = $GLOBALS['NOW'];
         }
      }

      $this_data = $this->fillEntityData();
      $tlsw_data = $tlsw->fillEntityData();

      $query = $this_data->build_sql_insert_values(true)
         . $this_data->build_sql_insert_values() . ', '
         . $tlsw_data->build_sql_insert_values()
         . " ON DUPLICATE KEY UPDATE Rank=VALUES(Rank)";

      return db_query( "TournamentLadder.switch_user_rank.save" .
                       "({$this->tid},{$this->rid}:{$tlsw->rid},{$this->Rank}:{$tlsw->Rank})", $query );
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of TournamentLadder-objects for given tournament-id. */
   function build_query_sql( $tid )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->newQuerySQL('TL');
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
            @$row['ChallengesIn']
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
   function load_tournament_ladder( $iterator, $tid )
   {
      $qsql = TournamentLadder::build_query_sql( $tid );
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
      if( $tp->Status != TP_STATUS_REGISTER )
         error('tournament_participant_invalid_status',
               "TournamentLadder::add_user_to_ladder.check_tp_status($tid,$uid,{$tp->Status})");

      return TournamentLadder::add_participant_to_ladder( $tid, $tp->ID, $uid );
   }

   /*! \brief Adds TP configured by rid,uid for tournament tid at bottom of ladder. */
   function add_participant_to_ladder( $tid, $rid, $uid )
   {
      $NOW = $GLOBALS['NOW'];
      $table = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->table;

      // defaults: RankChanged=0, BestRank=0, ChallengesIn=0
      $query = "INSERT INTO $table (tid,rid,uid,Created,Rank) "
             . "SELECT $tid, $rid, $uid, FROM_UNIXTIME($NOW), IFNULL(MAX(Rank),0)+1 AS Rank "
               . "FROM $table WHERE tid=$tid";
      return db_query( "TournamentLadder::add_participant_to_ladder.insert(tid[$tid],rid[$rid],uid[$uid])", $query );
   }

   /*! \brief Moves all tourney-users one rank up for incl. rank-range; min/max=0 separately or both for all. */
   function move_up_ladder_part( $tid, $min_rank=0, $max_rank=0 )
   {
      if( !is_numeric($tid) || !is_numeric($min_rank) || !is_numeric($max_rank) )
         error('invalid_args', "TournamentLadder::move_up_ladder_part.check_tid($tid,$min_rank,$max_rank)");

      global $NOW;
      $query = "UPDATE TournamentLadder SET Rank=Rank-1, BestRank=IF(BestRank=0,0,LEAST(BestRank,Rank-1)), "
            . "RankChanged=IF(BestRank=0,RankChanged,FROM_UNIXTIME($NOW)) WHERE tid=$tid "
            . TournamentUtils::build_num_range_sql_clause('Rank', $min_rank, $max_rank, 'AND');
      return db_query( "TournamentLadder::move_up_ladder_part.update($tid,$min_rank,$max_rank)", $query );
   }

   /*! \brief Moves all tourney-users one rank down for incl. rank-range; min/max=0 separately or both for all. */
   function move_down_ladder_part( $tid, $min_rank=0, $max_rank=0 )
   {
      if( !is_numeric($tid) || !is_numeric($min_rank) || !is_numeric($max_rank) )
         error('invalid_args', "TournamentLadder::move_down_ladder_part.check_tid($tid,$min_rank,$max_rank)");

      global $NOW;
      $query = "UPDATE TournamentLadder SET Rank=Rank+1, BestRank=IF(BestRank=0,0,LEAST(BestRank,Rank+1)), "
            . "RankChanged=IF(BestRank=0,RankChanged,FROM_UNIXTIME($NOW)) WHERE tid=$tid "
            . TournamentUtils::build_num_range_sql_clause('Rank', $min_rank, $max_rank, 'AND');
      return db_query( "TournamentLadder::move_down_ladder_part.update($tid,$min_rank,$max_rank)", $query );
   }

   /*! \brief Delete complete ladder for given tournament-id. */
   function delete_ladder( $tid )
   {
      $table = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->table;
      $query = "DELETE FROM $table WHERE tid=$tid";
      return db_query( "TournamentLadder::delete_ladder(tid[$tid])", $query );
   }

   /*! \brief Seeds ladder with all registered TPs. */
   function seed_ladder( $tourney, $tprops, $seed_order )
   {
      if( !is_a($tourney, 'Tournament') && $tourney->ID <= 0 )
         error('unknown_tournament', "TournamentLadder::seed_ladder.check_tid($seed_order)");
      $tid = $tourney->ID;

      list( $def, $arr_seed_order ) = $tprops->build_ladder_seed_order();
      if( !isset($arr_seed_order[$seed_order]) )
         error('invalid_args', "TournamentLadder::seed_ladder.check_seed_order($tid,$seed_order)");

      // find all registered TPs (optimized)
      $table = $GLOBALS['ENTITY_TOURNAMENT_PARTICIPANT']->table;
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS, 'TP.ID AS rid', 'TP.uid' );
      $qsql->add_part( SQLP_FROM,   "$table AS TP" );
      $qsql->add_part( SQLP_WHERE,  "TP.tid=$tid", "TP.Status='".TP_STATUS_REGISTER."'" );
      if( $seed_order == LADDER_SEEDORDER_CURRENT_RATING )
      {
         $qsql->add_part( SQLP_FROM,  'INNER JOIN Players AS TPP ON TPP.ID=TP.uid' );
         $qsql->add_part( SQLP_ORDER, 'TPP.Rating2 DESC' );
      }
      elseif( $seed_order == LADDER_SEEDORDER_REGISTER_TIME )
         $qsql->add_part( SQLP_ORDER, 'TP.Created ASC' );
      elseif( $seed_order == LADDER_SEEDORDER_TOURNEY_RATING )
         $qsql->add_part( SQLP_ORDER, 'TP.Rating DESC' );

      // load all registered TPs (optimized = no TournamentParticipant-objects)
      $result = db_query( "TournamentLadder::seed_ladder.load_tournament_participants($tid,$seed_order)",
         $qsql->get_select() );
      $arr_TPs = array();
      while( $row = mysql_fetch_array($result) )
         $arr_TPs[] = $row;
      mysql_free_result($result);

      if( $seed_order == LADDER_SEEDORDER_RANDOM )
         shuffle( $arr_TPs );

      // add all TPs to ladder
      $NOW = $GLOBALS['NOW'];
      $data = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->newEntityData();
      $arr_inserts = array();

      $table = $data->entity->table;
      db_lock( "TournamentLadder::seed_ladder($tid,$seed_order)",
         "$table WRITE, $table AS TL READ" );
      {//LOCK TournamentLadder
         $rank = TournamentLadder::load_max_rank($tid);
         foreach( $arr_TPs as $row )
         {
            ++$rank;
            $tladder = new TournamentLadder( $tid, $row['rid'], $row['uid'], $NOW, 0, $rank, 0 );
            $tladder->fillEntityData( $data );
            $arr_inserts[] = $data->build_sql_insert_values();
         }
         unset($arr_TPs);

         // insert all registered TPs to ladder
         $cnt = count($arr_inserts);
         $seed_query = $data->build_sql_insert_values(true) . implode(', ', $arr_inserts);
         $result = db_query( "TournamentLadder::seed_ladder.insert_all(tid[$tid],$seed_order,#{$cnt})", $seed_query );
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
