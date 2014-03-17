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

require_once 'include/db_classes.php';
require_once 'include/std_classes.php';
require_once 'include/std_functions.php';
require_once 'tournaments/include/tournament_globals.php';

 /*!
  * \file tournament_games.php
  *
  * \brief Functions for keeping track of tournament-games: tables TournamentGames
  */


 /*!
  * \class TournamentGames
  *
  * \brief Class to manage tournament-games
  */

global $ENTITY_TOURNAMENT_GAMES; //PHP5
$ENTITY_TOURNAMENT_GAMES = new Entity( 'TournamentGames',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_INT,  'ID', 'tid', 'Round_ID', 'Pool', 'gid', 'TicksDue', 'Flags',
                  'Challenger_uid', 'Challenger_rid', 'Defender_uid', 'Defender_rid',
      FTYPE_FLOAT, 'Score',
      FTYPE_DATE, 'Lastchanged', 'StartTime', 'EndTime',
      FTYPE_ENUM, 'Status'
   );

class TournamentGames
{
   private static $ARR_TGAME_TEXTS = array(); // lazy-init in TournamentGames::get..Text()-funcs: [key][id] => text

   public $ID;
   public $tid;
   public $Round_ID;
   public $Pool;
   public $gid;
   public $Status;
   public $TicksDue;
   public $Flags;
   public $Lastchanged;
   public $Challenger_uid;
   public $Challenger_rid;
   public $Defender_uid;
   public $Defender_rid;
   public $StartTime;
   public $EndTime;
   public $Score;

   // non-DB fields

   public $Defender_tladder = null;
   public $Challenger_tladder = null;

   /*! \brief Constructs TournamentGames-object with specified arguments. */
   public function __construct( $id=0, $tid=0, $round_id=0, $pool=0, $gid=0, $status=TG_STATUS_INIT,
         $ticks_due=0, $flags=0, $lastchanged=0, $challenger_uid=0, $challenger_rid=0,
         $defender_uid=0, $defender_rid=0, $start_time=0, $end_time=0, $score=0.0 )
   {
      $this->ID = (int)$id;
      $this->tid = (int)$tid;
      $this->Round_ID = (int)$round_id;
      $this->Pool = (int)$pool;
      $this->gid = (int)$gid;
      $this->setStatus( $status );
      $this->TicksDue = (int)$ticks_due;
      $this->Flags = (int)$flags;
      $this->Lastchanged = (int)$lastchanged;
      $this->Challenger_uid = (int)$challenger_uid;
      $this->Challenger_rid = (int)$challenger_rid;
      $this->Defender_uid = (int)$defender_uid;
      $this->Defender_rid = (int)$defender_rid;
      $this->StartTime = (int)$start_time;
      $this->EndTime = (int)$end_time;
      $this->Score = (float)$score;
   }

   public function to_string()
   {
      return print_r($this, true);
   }

   public function setStatus( $status )
   {
      if ( !preg_match( "/^(".CHECK_TG_STATUS.")$/", $status ) )
         error('invalid_args', "TournamentGames.setStatus($status)");
      $this->Status = $status;
   }

   /*!
    * \brief Returns true if tournament-game is on status with set score.
    * \param $check_detached true = check that game is not detached (and has no score), false = check only TG.Status
    */
   public function isScoreStatus( $check_detached )
   {
      static $arr_status_with_score = array( TG_STATUS_SCORE, TG_STATUS_WAIT, TG_STATUS_DONE );

      $result = in_array( $this->Status, $arr_status_with_score );
      if ( $check_detached && ($this->Flags & TG_FLAG_GAME_DETACHED) )
         $result = false;
      return $result;
   }

   /*! \brief Returns score for given user; null if no score set. */
   public function getScoreForUser( $uid )
   {
      if ( $uid <= 0 )
         error('invalid_args', "TournamentGames.getScoreForUser($uid)");

      if ( $uid > 0 && $this->isScoreStatus(/*chk-detach*/true) )
         return (( $this->Challenger_uid == $uid ) ? 1 : -1 ) * $this->Score;
      else
         return null;
   }

   /*! \brief Returns arr( game-score, game-score-text ) for given user for this TournamentGames-instance. */
   public function getGameScore( $uid, $verbose, $keep_english=false )
   {
      $game_score = $this->getScoreForUser( $uid );
      $game_flags = ( $this->Flags & TG_FLAG_GAME_NO_RESULT ) ? GAMEFLAGS_NO_RESULT : 0;
      $score_text = score2text( $game_score, $game_flags, $verbose, $keep_english );
      return array( $game_score, $score_text );
   }

   /*! \brief Returns array( uid, uid ) from Challenger_uid and Defender_uid smallest first. */
   public function get_ordered_uids()
   {
      if ( $this->Challenger_uid < $this->Defender_uid )
         return array( $this->Challenger_uid, $this->Defender_uid );
      else
         return array( $this->Defender_uid, $this->Challenger_uid );
   }

   public function formatFlags( $flags_val=null )
   {
      if ( is_null($flags_val) )
         $flags_val = $this->Flags;
      $arr = array();
      $arr_flags = self::getFlagsText();
      foreach ( $arr_flags as $flag => $flagtext )
      {
         if ( $flags_val & $flag )
            $arr[] = $flagtext;
      }
      return implode(', ', $arr);
   }//formatFlags

   /*! \brief Inserts or updates tournament-ladder-props in database. */
   public function persist()
   {
      if ( $this->ID > 0 )
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   public function insert()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      $result = $entityData->insert( "TournamentGames.insert(%s)" );
      if ( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   public function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->update( "TournamentGames.update(%s)" );
   }

   public function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "TournamentGames.delete(%s)" );
   }

   public function fillEntityData( $data=null )
   {
      if ( is_null($data) )
         $data = $GLOBALS['ENTITY_TOURNAMENT_GAMES']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'Round_ID', $this->Round_ID );
      $data->set_value( 'Pool', $this->Pool );
      $data->set_value( 'gid', $this->gid );
      $data->set_value( 'Status', $this->Status );
      $data->set_value( 'TicksDue', $this->TicksDue );
      $data->set_value( 'Flags', $this->Flags );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'Challenger_uid', $this->Challenger_uid );
      $data->set_value( 'Challenger_rid', $this->Challenger_rid );
      $data->set_value( 'Defender_uid', $this->Defender_uid );
      $data->set_value( 'Defender_rid', $this->Defender_rid );
      $data->set_value( 'StartTime', $this->StartTime );
      $data->set_value( 'EndTime', $this->EndTime );
      $data->set_value( 'Score', $this->Score );
      return $data;
   }

   /*!
    * \brief Signals end of tournament-game updating TournamentGames to SCORE-status and
    *        setting TG.Score and TG.Flags for given tournament-ID and game-id.
    */
   public function update_score( $dbgmsg, $old_status=null )
   {
      $this->Lastchanged = $this->EndTime = $GLOBALS['NOW'];

      $data = $GLOBALS['ENTITY_TOURNAMENT_GAMES']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'Status', $this->Status );
      $data->set_value( 'Flags', $this->Flags );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'EndTime', $this->EndTime );
      $data->set_value( 'Score', $this->Score );

      $arr_query = $data->build_sql_update( 1, true );
      if ( !is_null($old_status) )
         $arr_query[1] .= " AND Status='" . mysql_addslashes($old_status) . "'"; // WHERE-clause

      $result = db_query( "$dbgmsg.TournamentGames.update_score({$this->ID},{$this->tid},{$this->gid},{$this->Score})",
         implode(' ', $arr_query) );
      return $result;
   }//update_score


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of TournamentGames-objects for given tournament-id. */
   public static function build_query_sql( $tid=0 )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_GAMES']->newQuerySQL('TG');
      if ( $tid > 0 )
         $qsql->add_part( SQLP_WHERE, "TG.tid='$tid'" );
      return $qsql;
   }

   /*! \brief Returns TournamentGames-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $tg = new TournamentGames(
            // from TournamentGames
            @$row['ID'],
            @$row['tid'],
            @$row['Round_ID'],
            @$row['Pool'],
            @$row['gid'],
            @$row['Status'],
            @$row['TicksDue'],
            @$row['Flags'],
            @$row['X_Lastchanged'],
            @$row['Challenger_uid'],
            @$row['Challenger_rid'],
            @$row['Defender_uid'],
            @$row['Defender_rid'],
            @$row['X_StartTime'],
            @$row['X_EndTime'],
            @$row['Score']
         );
      return $tg;
   }

   /*!
    * \brief Loads and returns TournamentGames-object for given games-ID.
    * \return NULL if nothing found; TournamentGames otherwise
    */
   public static function load_tournament_game_by_gid( $gid )
   {
      if ( !is_numeric($gid) || $gid <= 0 )
         error('invalid_args', "TournamentGames:load_tournament_game_by_gid.check_gid($gid)");

      $qsql = self::build_query_sql();
      $qsql->add_part( SQLP_WHERE,
            "TG.gid=$gid" );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "TournamentGames:load_tournament_game_by_gid.find_tgame($gid)",
         $qsql->get_select() );
      return ($row) ? self::new_from_row($row) : NULL;
   }//load_tournament_game_by_gid

   /*!
    * \brief Loads and returns TournamentGames-object for given tournament-ID, challenger- and defender-uid
    *     or reversed roles.
    * \return NULL if nothing found; TournamentGames otherwise
    */
   public static function load_tournament_game_by_pair_uid( $tid, $ch_uid, $df_uid )
   {
      $tgame = self::load_tournament_game_by_challenger_uid( $tid, $ch_uid, $df_uid );
      if ( is_null($tgame) )
         $tgame = self::load_tournament_game_by_challenger_uid( $tid, $df_uid, $ch_uid );
      return $tgame;
   }//load_tournament_game_by_pair_uid

   /*!
    * \brief Loads and returns TournamentGames-object for given tournament-ID, challenger- and defender-uid.
    * \return NULL if nothing found; TournamentGames otherwise
    */
   private static function load_tournament_game_by_challenger_uid( $tid, $ch_uid, $df_uid )
   {
      $dbgmsg = "TournamentGames:load_tournament_game_by_challenger_uid($tid,$ch_uid,$df_uid)";
      if ( !is_numeric($tid) || $tid <= 0 )
         error('invalid_args', "$dbgmsg.check_tid");
      if ( !is_numeric($ch_uid) || $ch_uid <= GUESTS_ID_MAX )
         error('invalid_args', "$dbgmsg.check_ch_uid");
      if ( !is_numeric($df_uid) || $df_uid <= GUESTS_ID_MAX )
         error('invalid_args', "$dbgmsg.check_df_uid");

      $qsql = self::build_query_sql( $tid );
      $qsql->add_part( SQLP_WHERE,
            'TG.Challenger_uid=' . (int)$ch_uid,
            'TG.Defender_uid=' . (int)$df_uid );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "$dbgmsg.find_tgame", $qsql->get_select() );
      return ($row) ? self::new_from_row($row) : NULL;
   }//load_tournament_game_by_challenger_uid

   /*!
    * \brief Returns ListIterator with TournamentGames-objects for given tournament-id and TournamentGames-status.
    * \param $round_id optional TournamentRound.ID
    * \param $pool 0 = load all pools, otherwise load specific pool or pools if array of pools
    * \param $status null=no restriction on status, single-status or array with status to restrict on
    */
   public static function load_tournament_games( $iterator, $tid, $round_id=0, $pool=0, $status=null )
   {
      $qsql = self::build_query_sql( $tid );
      if ( is_array($status) )
         $qsql->add_part( SQLP_WHERE, build_query_in_clause('TG.Status', $status, true) );
      elseif ( !is_null($status) )
         $qsql->add_part( SQLP_WHERE, "TG.Status='" . mysql_addslashes($status) . "'" );
      if ( $round_id > 0 )
         $qsql->add_part( SQLP_WHERE, "TG.Round_ID=$round_id" );
      if ( is_array($pool) )
         $qsql->add_part( SQLP_WHERE, build_query_in_clause( 'TG.Pool', $pool, /*is-str*/false ) );
      elseif ( $pool > 0 )
         $qsql->add_part( SQLP_WHERE, "TG.Pool='$pool'" );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentGames:load_tournament_games($tid,$round_id,$pool)", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $tgame = self::new_from_row( $row );
         $iterator->addItem( $tgame, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_tournament_games

   /*!
    * \brief Counts Tournament-games with given status-array or single status (empty array for all stati).
    * \param $pool_group if true, return array( pool => array( status => count, ...), ...);
    *        otherwise return scalar count
    */
   public static function count_tournament_games( $tid, $round_id=0, $arr_status=null, $pool_group=false )
   {
      static $tg_undone_status = array( TG_STATUS_INIT, TG_STATUS_PLAY, TG_STATUS_SCORE, TG_STATUS_WAIT );
      $tid = (int)$tid;
      $round_id = (int)$round_id;
      if ( is_null($arr_status) )
         $arr_status = $tg_undone_status;
      elseif ( !is_array($arr_status) )
         $arr_status = array( $arr_status );

      $qsql = new QuerySQL(
         SQLP_FIELDS, 'COUNT(*) AS X_Count',
         SQLP_FROM, 'TournamentGames AS TG',
         SQLP_WHERE, "TG.tid=$tid", build_query_in_clause('TG.Status', $arr_status, /*is_str*/true) );
      if ( $round_id > 0 )
         $qsql->add_part( SQLP_WHERE, "TG.Round_ID=$round_id" );

      if ( $pool_group ) // group by Pool, Status
      {
         $qsql->add_part( SQLP_FIELDS, 'Pool', 'Status' );
         $qsql->add_part( SQLP_GROUP, 'Pool', 'Status' );
         $result = db_query( "TournamentGames:count_tournament_games.group($tid,$round_id)", $qsql->get_select() );

         $arr = array();
         while ( $row = mysql_fetch_array( $result ) )
            $arr[$row['Pool']][$row['Status']] = $row['X_Count'];
         mysql_free_result($result);

         return $arr;
      }
      else
      {
         $row = mysql_single_fetch( "TournamentGames:count_tournament_games($tid,$round_id)", $qsql->get_select() );
         return ( $row ) ? (int)$row['X_Count'] : 0;
      }
   }//count_tournament_games

   /*! \brief Counts Games for consistency-check with Tournament-games. */
   public static function count_games_started( $tid, $round_id )
   {
      if ( !is_numeric($tid) || $tid <= 0 )
         error('invalid_args', "TournamentGames:count_games_started.check.tid($tid,$round_id)");
      if ( !is_numeric($round_id) || $round_id <= 0 )
         error('invalid_args', "TournamentGames:count_games_started.check.round_id($tid,$round_id)");

      $qsql = new QuerySQL(
         SQLP_FIELDS, 'COUNT(*) AS X_Count',
         SQLP_FROM,
            'Games AS G',
            // NOTE: LEFT-JOIN would be better, but Games-table has no knowledge of T-round, so we have to equi-join with TGs
            "INNER JOIN TournamentGames AS TG ON TG.gid=G.ID AND TG.tid=$tid AND TG.Round_ID=$round_id",
         SQLP_WHERE, "G.tid=$tid" );

      $row = mysql_single_fetch( "TournamentGames:count_games_started($tid,$round_id)", $qsql->get_select() );
      return ( $row ) ? (int)$row['X_Count'] : 0;
   }//count_games_started

   /*!
    * \brief Finds running, undetached tournament-games.
    * \return array( TG.ID-arr, gid-array, opp-uid-arr ) : TG.ID and gid to set DETACHED-flag, opponent-arr to notify
    */
   public static function find_undetached_running_games( $tid, $uid )
   {
      $qsql = new QuerySQL(
         SQLP_FIELDS,
            'ID', 'gid', 'Status', 'Challenger_uid', 'Defender_uid',
         SQLP_FROM,
            'TournamentGames',
         SQLP_WHERE,
            "tid=$tid",
            "Status IN ('".TG_STATUS_PLAY."','".TG_STATUS_SCORE."')",
            '(Flags & '.TG_FLAG_GAME_DETACHED.')=0',
         SQLP_UNION_WHERE,
            "Challenger_uid=$uid",
            "Defender_uid=$uid" );
      $result = db_query( "TournamentGames:find_undetached_running_games($tid,$uid)", $qsql->get_select() );

      $out_tg_id = array();
      $out_gid = array();
      $out_opp = array();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $out_tg_id[] = $row['ID'];
         $out_gid[] = $row['gid'];
         if ( $row['Status'] == TG_STATUS_PLAY )
            $out_opp[] = ( $row['Challenger_uid'] == $uid ) ? $row['Defender_uid'] : $row['Challenger_uid'];
      }
      mysql_free_result($result);

      return array( $out_tg_id, $out_gid, array_unique($out_opp) );
   }//find_undetached_running_games

   /*! \brief Returns number of running, undetached tournament-games for given tournament and user. */
   public static function count_user_running_games( $tid, $uid )
   {
      $qsql = new QuerySQL(
         SQLP_FIELDS,
            'COUNT(*) AS X_Count',
         SQLP_FROM,
            'TournamentGames',
         SQLP_WHERE,
            "tid=$tid",
            "Status IN ('".TG_STATUS_PLAY."','".TG_STATUS_SCORE."')",
         SQLP_UNION_WHERE,
            "Challenger_uid=$uid",
            "Defender_uid=$uid" );
      $result = db_query( "TournamentGames:find_undetached_running_games($tid,$uid)", $qsql->get_select() );

      $cnt = 0;
      while ( $row = mysql_fetch_array( $result ) )
         $cnt += (int)$row['X_Count'];
      mysql_free_result($result);

      return $cnt;
   }//count_undetached_running_games

   /*! \brief "End" finished rematch-waiting tournament-games for given tournament and user by setting Status=DONE. */
   public static function end_rematch_waiting_finished_games( $tid, $uid )
   {
      $dbgmsg = "TournamentGames:end_rematch_waiting_finished_games($tid,$uid)";
      db_query( "$dbgmsg.upd_challenger",
         "UPDATE TournamentGames SET Status='".TG_STATUS_DONE."' " .
         "WHERE tid=$tid AND Status='".TG_STATUS_WAIT."' AND Challenger_uid=$uid" );
      db_query( "$dbgmsg.upd_defender",
         "UPDATE TournamentGames SET Status='".TG_STATUS_DONE."' " .
         "WHERE tid=$tid AND Status='".TG_STATUS_WAIT."' AND Defender_uid=$uid" );
   }//end_rematch_waiting_finished_games

   /*!
    * \brief Signals end of tournament-game updating TournamentGames to SCORE-status and
    *        setting TG.Score and TG.Flags for given tournament-ID and game-id.
    */
   public static function update_tournament_game_end( $dbgmsg, $tid, $gid, $black_uid, $score, $game_flags )
   {
      if ( !is_numeric($tid) || $tid <= 0 )
         error('invalid_args', "TournamentGames:update_tournament_game_end.check.tid($tid,$gid)");
      if ( !is_numeric($gid) || $gid <= 0 )
         error('invalid_args', "TournamentGames:update_tournament_game_end.check.gid($tid,$gid)");
      if ( !is_numeric($black_uid) || $black_uid <= GUESTS_ID_MAX )
         error('invalid_args', "TournamentGames:update_tournament_game_end.check.black_uid($tid,$gid,$black_uid)");
      if ( is_null($score) )
         return 0;

      $tg_flags = 0;
      if ( $game_flags & GAMEFLAGS_NO_RESULT )
         $tg_flags = TG_FLAG_GAME_NO_RESULT;

      global $NOW;
      $result = db_query( $dbgmsg."($gid,$tid,$black_uid,$score)",
         "UPDATE TournamentGames SET "
            . "Status='".TG_STATUS_SCORE."', "
            . "Score=IF(Challenger_uid=$black_uid,$score,-$score), "
            . ( $tg_flags > 0 ? "Flags=Flags | $tg_flags, " : '' )
            . "EndTime=FROM_UNIXTIME($NOW), "
            . "Lastchanged=FROM_UNIXTIME($NOW) "
         . " WHERE tid=$tid AND gid=$gid AND Status='".TG_STATUS_PLAY."' LIMIT 1" );
      return $result;
   }//update_tournament_game_end

   /*! \brief Updates due tournament-games finishing with DONE-status. */
   public static function update_tournament_game_wait( $dbgmsg, $wait_ticks )
   {
      global $NOW;

      $dbgmsg .= ".TG:update_tournament_game_wait($wait_ticks)";
      if ( !is_numeric($wait_ticks) )
         error('invalid_args', "$dbgmsg.check.ticks");

      $query_part = " WHERE Status='".TG_STATUS_WAIT."' AND TicksDue<=$wait_ticks";

      // find tournament-id to clear cache for
      $arr_tids = array();
      $result = db_query( "$dbgmsg.find_tids", "SELECT tid FROM TournamentGames $query_part" );
      while ( $row = mysql_fetch_array($result) )
         $arr_tids[] = $row['tid'];
      mysql_free_result($result);

      ta_begin();
      {//HOT-section to finish waiting tournament-games
         $result = db_query( "$dbgmsg.upd_tgame",
            "UPDATE TournamentGames SET Status='".TG_STATUS_DONE."', Lastchanged=FROM_UNIXTIME($NOW) $query_part" );
         if ( $result )
         {
            foreach ( $arr_tids as $tid )
               self::delete_cache_tournament_games( $dbgmsg, $tid );
         }
      }
      ta_end();

      return $result;
   }//update_tournament_game_wait

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   public static function getStatusText( $status=null )
   {
      // lazy-init of texts
      $key = 'STATUS';
      if ( !isset(self::$ARR_TGAME_TEXTS[$key]) )
      {
         $arr = array();
         $arr[TG_STATUS_INIT] = T_('Init#TG_status');
         $arr[TG_STATUS_PLAY] = T_('Play#TG_status');
         $arr[TG_STATUS_SCORE] = T_('Score#TG_status');
         $arr[TG_STATUS_WAIT] = T_('Wait#TG_status');
         $arr[TG_STATUS_DONE] = T_('Done#TG_status');
         self::$ARR_TGAME_TEXTS[$key] = $arr;
      }

      if ( is_null($status) )
         return self::$ARR_TGAME_TEXTS[$key];
      if ( !isset(self::$ARR_TGAME_TEXTS[$key][$status]) )
         error('invalid_args', "TournamentGames:getStatusText($status)");
      return self::$ARR_TGAME_TEXTS[$key][$status];
   }//getStatusText

   /*! \brief Returns Flags-text or all Flags-texts (if arg=null). */
   public static function getFlagsText( $flag=null )
   {
      // lazy-init of texts
      $key = 'FLAGS';
      if ( !isset(self::$ARR_TGAME_TEXTS[$key]) )
      {
         $arr = array();
         $arr[TG_FLAG_GAME_END_TD] = T_('Game end by TD#TG_flag');
         $arr[TG_FLAG_GAME_DETACHED] = T_('Game annulled#tourney');
         $arr[TG_FLAG_CH_DF_SWITCHED] = T_('Challenger/Defender switched#TG_flag');
         $arr[TG_FLAG_GAME_NO_RESULT] = T_('Game No-Result#TG_flag');
         self::$ARR_TGAME_TEXTS[$key] = $arr;
      }

      if ( is_null($flag) )
         return self::$ARR_TGAME_TEXTS[$key];
      if ( !isset(self::$ARR_TGAME_TEXTS[$key][$flag]) )
         error('invalid_args', "TournamentGames:getFlagsText($flag)");
      return self::$ARR_TGAME_TEXTS[$key][$flag];
   }//getFlagsText

   /*! \brief Returns array for Selection-filter on TournamentGames.Status. */
   public static function buildStatusFilterArray( $prefix='' )
   {
      $arr = array(
            T_('All') => '',
            T_('Active#TG_stat_filter') => $prefix."Status IN ('".TG_STATUS_PLAY."','".TG_STATUS_SCORE."','".TG_STATUS_WAIT."')",
         );
      foreach ( self::getStatusText() as $tg_status => $tg_text )
         $arr[$tg_text] = $prefix."Status='$tg_status'";
      $arr[T_('None#TG_stat_filter')] = $prefix.'Status IS NULL';
      return $arr;
   }

   public static function get_admin_tournament_status()
   {
      static $statuslist = array( TOURNEY_STATUS_PAIR, TOURNEY_STATUS_PLAY, TOURNEY_STATUS_CLOSED );
      return $statuslist;
   }

   public static function delete_cache_tournament_games( $dbgmsg, $tid )
   {
      DgsCache::delete_group( $dbgmsg, CACHE_GRP_TGAMES, "TGames.$tid" );
   }

   /*! \brief Returns true if given tournament-game-status, for that change of tournament-game-score is allowed. */
   public static function is_score_change_allowed( $tg_status )
   {
      return ( $tg_status == TG_STATUS_INIT || $tg_status == TG_STATUS_PLAY );
   }

} // end of 'TournamentGames'
?>
