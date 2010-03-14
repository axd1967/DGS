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

// lazy-init in TournamentGames::get..Text()-funcs
global $ARR_GLOBALS_TOURNAMENT_GAMES; //PHP5
$ARR_GLOBALS_TOURNAMENT_GAMES = array();

global $ENTITY_TOURNAMENT_GAMES; //PHP5
$ENTITY_TOURNAMENT_GAMES = new Entity( 'TournamentGames',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_CHBY,
      FTYPE_INT,  'ID', 'tid', 'gid', 'TicksDue', 'Flags', 'Challenger_uid', 'Challenger_rid',
                  'Defender_uid', 'Defender_rid',
      FTYPE_FLOAT, 'Score',
      FTYPE_DATE, 'Lastchanged', 'StartTime', 'EndTime',
      FTYPE_ENUM, 'Status'
   );

class TournamentGames
{
   var $ID;
   var $tid;
   var $gid;
   var $Status;
   var $TicksDue;
   var $Flags;
   var $Lastchanged;
   var $ChangedBy;
   var $Challenger_uid;
   var $Challenger_rid;
   var $Defender_uid;
   var $Defender_rid;
   var $StartTime;
   var $EndTime;
   var $Score;

   // non-DB fields

   var $Defender_tladder;
   var $Challenger_tladder;

   /*! \brief Constructs TournamentGames-object with specified arguments. */
   function TournamentGames( $id=0, $tid=0, $gid=0, $status=TG_STATUS_INIT, $ticks_due=0, $flags=0,
         $lastchanged=0, $changed_by='',
         $challenger_uid=0, $challenger_rid=0, $defender_uid=0, $defender_rid=0,
         $start_time=0, $end_time=0, $score=0.0 )
   {
      $this->ID = (int)$id;
      $this->tid = (int)$tid;
      $this->gid = (int)$gid;
      $this->setStatus( $status );
      $this->TicksDue = (int)$ticks_due;
      $this->Flags = (int)$flags;
      $this->Lastchanged = (int)$lastchanged;
      $this->ChangedBy = $changed_by;
      $this->Challenger_uid = (int)$challenger_uid;
      $this->Challenger_rid = (int)$challenger_rid;
      $this->Defender_uid = (int)$defender_uid;
      $this->Defender_rid = (int)$defender_rid;
      $this->StartTime = (int)$start_time;
      $this->EndTime = (int)$end_time;
      $this->Score = (float)$score;
      // non-DB fields
      $this->Defender_tladder = null;
      $this->Challenger_tladder = null;
   }

   function to_string()
   {
      return print_r($this, true);
   }

   function setStatus( $status )
   {
      if( !preg_match( "/^(".CHECK_TG_STATUS.")$/", $status ) )
         error('invalid_args', "TournamentGames.setStatus($status)");
      $this->Status = $status;
   }

   /*! \brief Returns true if tournament-game is on status with set score. */
   function isScoreStatus()
   {
      static $arr_status_with_score = array( TG_STATUS_SCORE, TG_STATUS_WAIT, TG_STATUS_DONE );
      return in_array( $this->Status, $arr_status_with_score );
   }

   function getScoreForUser( $uid )
   {
      if( $uid <= 0 )
         error('invalid_args', "TournamentGames.getScoreForUser($uid)");

      if( $uid > 0 && $this->isScoreStatus() )
         return (( $this->Challenger_uid == $uid ) ? 1 : -1 ) * $this->Score;
      else
         return -OUT_OF_RATING;
   }

   /*! \brief Inserts or updates tournament-ladder-props in database. */
   function persist()
   {
      if( $this->ID > 0 )
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   function insert()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      $result = $entityData->insert( "TournamentGames.insert(%s)" );
      if( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->update( "TournamentGames.update(%s)" );
   }

   function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "TournamentGames.delete(%s)" );
   }

   function fillEntityData( &$data=null )
   {
      if( is_null($data) )
         $data = $GLOBALS['ENTITY_TOURNAMENT_GAMES']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'gid', $this->gid );
      $data->set_value( 'Status', $this->Status );
      $data->set_value( 'TicksDue', $this->TicksDue );
      $data->set_value( 'Flags', $this->Flags );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'ChangedBy', $this->ChangedBy );
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
    *        setting TG.Score for given tournament-ID and game-id.
    */
   function update_score( $dbgmsg, $old_status=null )
   {
      $this->Lastchanged = $this->EndTime = $GLOBALS['NOW'];

      $data = $GLOBALS['ENTITY_TOURNAMENT_GAMES']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'Status', $this->Status );
      $data->set_value( 'Flags', $this->Flags );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'ChangedBy', $this->ChangedBy );
      $data->set_value( 'EndTime', $this->EndTime );
      $data->set_value( 'Score', $this->Score );

      $arr_query = $data->build_sql_update( 1, true );
      if( !is_null($old_status) )
         $arr_query[1] .= " AND Status='" . mysql_addslashes($old_status) . "'"; // WHERE-clause

      $result = db_query( "$dbgmsg.TournamentGames.update_score({$this->ID},{$this->tid},{$this->gid},{$this->Score})",
         implode(' ', $arr_query) );
      return $result;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of TournamentGames-objects for given tournament-id. */
   function build_query_sql( $tid=0 )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_GAMES']->newQuerySQL('TG');
      if( $tid > 0 )
         $qsql->add_part( SQLP_WHERE, "TG.tid='$tid'" );
      return $qsql;
   }

   /*! \brief Returns TournamentGames-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $tg = new TournamentGames(
            // from TournamentGames
            @$row['ID'],
            @$row['tid'],
            @$row['gid'],
            @$row['Status'],
            @$row['TicksDue'],
            @$row['Flags'],
            @$row['X_Lastchanged'],
            @$row['ChangedBy'],
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
   function load_tournament_game_by_gid( $gid )
   {
      if( !is_numeric($gid) || $gid <= 0 )
         error('invalid_args', "TournamentGames.load_tournament_game_by_gid.check_gid($gid)");

      $qsql = TournamentGames::build_query_sql();
      $qsql->add_part( SQLP_WHERE,
            "TG.gid=$gid" );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "TournamentGames::load_tournament_game_by_gid.find_tgame($gid)",
         $qsql->get_select() );
      return ($row) ? TournamentGames::new_from_row($row) : NULL;
   }

   /*!
    * \brief Loads and returns TournamentGames-object for given tournament-ID, challenger- and defender-uid.
    * \return NULL if nothing found; TournamentGames otherwise
    */
   function load_tournament_game_by_uid( $tid, $ch_uid, $df_uid )
   {
      if( !is_numeric($tid) || $tid <= 0 )
         error('invalid_args', "TournamentGames.load_tournament_game_by_uid.check_tid($tid,$ch_uid,$df_uid)");
      if( !is_numeric($ch_uid) || $ch_uid <= GUESTS_ID_MAX )
         error('invalid_args', "TournamentGames.load_tournament_game_by_uid.check_ch_uid($tid,$ch_uid,$df_uid)");
      if( !is_numeric($df_uid) || $df_uid <= GUESTS_ID_MAX )
         error('invalid_args', "TournamentGames.load_tournament_game_by_uid.check_df_uid($tid,$ch_uid,$df_uid)");

      $qsql = TournamentGames::build_query_sql( $tid );
      $qsql->add_part( SQLP_WHERE,
            'TG.Challenger_uid=' . (int)$ch_uid,
            'TG.Defender_uid=' . (int)$df_uid );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "TournamentGames::load_tournament_game_by_uid.find_tgame($tid,$ch_uid,$df_uid)",
         $qsql->get_select() );
      return ($row) ? TournamentGames::new_from_row($row) : NULL;
   }

   /*!
    * \brief Returns ListIterator with TournamentGames-objects for given tournament-id and TournamentGames-status.
    * \param $status null=no restriction on status, single-status or array with status to restrict on
    */
   function load_tournament_games( $iterator, $tid, $status=null )
   {
      $qsql = TournamentGames::build_query_sql( $tid );
      if( is_array($status) )
         $qsql->add_part( SQLP_WHERE, build_query_in_clause('TG.Status', $status, true) );
      elseif( !is_null($status) )
         $qsql->add_part( SQLP_WHERE, "TG.Status='" . mysql_addslashes($status) . "'" );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentGames::load_tournament_games", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $tgame = TournamentGames::new_from_row( $row );
         $iterator->addItem( $tgame, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

   /*! \brief Counts Tournament-games with given status-array or single status. */
   function count_tournament_games( $arr_status=null )
   {
      static $tg_undone_status = array( TG_STATUS_INIT, TG_STATUS_PLAY, TG_STATUS_SCORE, TG_STATUS_WAIT );
      if( is_nulL($arr_status) )
         $arr_status = $tg_undone_status;
      elseif( !is_array($arr_status) )
         $arr_status = array( $arr_status );

      $qsql = new QuerySQL(
         SQLP_FIELDS, 'COUNT(*) AS X_Count',
         SQLP_FROM, 'TournamentGames AS TG',
         SQLP_WHERE, build_query_in_clause('TG.Status', $arr_status, /*is_str*/true) );

      $row = mysql_single_fetch( 'TournamentGames::count_tournament_games()', $qsql->get_select() );
      return ( $row ) ? (int)$row['X_Count'] : 0;
   }

   /*!
    * \brief Signals end of tournament-game updating TournamentGames to SCORE-status and
    *        setting TG.Score for given tournament-ID and game-id.
    */
   function update_tournament_game_end( $dbgmsg, $changed_by, $tid, $gid, $black_uid, $score )
   {
      if( !is_numeric($tid) || $tid <= 0 )
         error('invalid_args', "TournamentGames.update_tournament_game_end.check.tid($tid,$gid)");
      if( !is_numeric($gid) || $gid <= 0 )
         error('invalid_args', "TournamentGames.update_tournament_game_end.check.gid($tid,$gid)");
      if( !is_numeric($black_uid) || $black_uid <= GUESTS_ID_MAX )
         error('invalid_args', "TournamentGames.update_tournament_game_end.check.black_uid($tid,$gid,$black_uid)");

      global $NOW;
      $table = $GLOBALS['ENTITY_TOURNAMENT_GAMES']->table;
      $result = db_query( $dbgmsg."($gid,$tid,$black_uid,$score)",
         "UPDATE $table SET "
            . "Status='".TG_STATUS_SCORE."', "
            . "Score=IF(Challenger_uid=$black_uid,$score,-$score), "
            . "EndTime=FROM_UNIXTIME($NOW), "
            . "Lastchanged=FROM_UNIXTIME($NOW), "
            . EntityData::build_update_part_changed_by($changed_by)
         . " WHERE tid=$tid AND gid=$gid AND Status='".TG_STATUS_PLAY."' LIMIT 1" );
      return $result;
   }

   /*! \brief Updates due tournament-games finishing with DONE-status. */
   function update_tournament_game_wait( $dbgmsg, $changed_by, $wait_ticks )
   {
      if( !is_numeric($wait_ticks) )
         error('invalid_args', "TournamentGames.update_tournament_game_wait.check.ticks($wait_ticks)");

      global $NOW;
      $table = $GLOBALS['ENTITY_TOURNAMENT_GAMES']->table;
      $result = db_query( "$dbgmsg.update_tournament_game_wait($wait_ticks)",
         "UPDATE $table SET "
            . "Status='".TG_STATUS_DONE."', "
            . "Lastchanged=FROM_UNIXTIME($NOW), "
            . EntityData::build_update_part_changed_by($changed_by)
         . " WHERE Status='".TG_STATUS_WAIT."' AND TicksDue<=$wait_ticks" );
      return $result;
   }

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   function getStatusText( $status=null )
   {
      global $ARR_GLOBALS_TOURNAMENT_GAMES;

      // lazy-init of texts
      $key = 'STATUS';
      if( !isset($ARR_GLOBALS_TOURNAMENT_GAMES[$key]) )
      {
         $arr = array();
         $arr[TG_STATUS_INIT] = T_('Init#TG_status');
         $arr[TG_STATUS_PLAY] = T_('Play#TG_status');
         $arr[TG_STATUS_SCORE] = T_('Score#TG_status');
         $arr[TG_STATUS_WAIT] = T_('Wait#TG_status');
         $arr[TG_STATUS_DONE] = T_('Done#TG_status');
         $ARR_GLOBALS_TOURNAMENT_GAMES[$key] = $arr;
      }

      if( is_null($status) )
         return $ARR_GLOBALS_TOURNAMENT_GAMES[$key];
      if( !isset($ARR_GLOBALS_TOURNAMENT_GAMES[$key][$status]) )
         error('invalid_args', "TournamentGames.getStatusText($status)");
      return $ARR_GLOBALS_TOURNAMENT_GAMES[$key][$status];
   }

   function get_admin_tournament_status()
   {
      static $statuslist = array( TOURNEY_STATUS_PAIR, TOURNEY_STATUS_PLAY, TOURNEY_STATUS_CLOSED );
      return $statuslist;
   }

} // end of 'TournamentGames'
?>
