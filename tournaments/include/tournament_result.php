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
require_once 'include/dgs_cache.php';
require_once 'include/std_classes.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_utils.php';

 /*!
  * \file tournament_result.php
  *
  * \brief Functions for handling tournament results: tables TournamentResult
  */


 /*!
  * \class TournamentResult
  *
  * \brief Class to manage TournamentResult-table with pairing-related tournament-settings
  */


global $ENTITY_TOURNAMENT_RESULT; //PHP5
$ENTITY_TOURNAMENT_RESULT = new Entity( 'TournamentResult',
   FTYPE_PKEY,  'ID',
   FTYPE_AUTO,  'ID',
   FTYPE_INT,   'ID', 'tid', 'uid', 'rid', 'Type', 'Round', 'Result', 'Rank',
   FTYPE_TEXT,  'Comment', 'Note',
   FTYPE_FLOAT, 'Rating',
   FTYPE_DATE,  'StartTime', 'EndTime'
   );

class TournamentResult
{
   public $ID;
   public $tid;
   public $uid;
   public $rid;
   public $Rating;
   public $Type;
   public $Round;
   public $StartTime;
   public $EndTime;
   public $Result;
   public $Rank;
   public $Comment;
   public $Note;

   /*! \brief Constructs TournamentResult-object with specified arguments. */
   public function __construct( $id=0, $tid=0, $uid=0, $rid=0, $rating=NO_RATING, $type=0, $round=1,
         $start_time=0, $end_time=0, $result=0, $rank=0, $comment='', $note='' )
   {
      $this->ID = (int)$id;
      $this->tid = (int)$tid;
      $this->uid = (int)$uid;
      $this->rid = (int)$rid;
      $this->Rating = (float)$rating;
      $this->Type = (int)$type;
      $this->Round = (int)$round;
      $this->StartTime = (int)$start_time;
      $this->EndTime = (int)$end_time;
      $this->Result = (int)$result;
      $this->Rank = (int)$rank;
      $this->Comment = $comment;
      $this->Note = $note;
   }

   public function to_string()
   {
      return print_r( $this, true );
   }

   public function build_log_string()
   {
      return sprintf("TournamentResult: id=[%s], uid/rid=[%s/%s], Rating=[%s], Type=[%s], Round=[%s], " .
                     "Start/EndTime=[%s/%s], Result=[%s], Rank=[%s], Comment=[%s], Note=[%s]",
         $this->ID, $this->uid, $this->rid, $this->Rating, $this->Type, $this->Round,
         ($this->StartTime > 0 ? date(DATE_FMT, $this->StartTime) : ''),
         ($this->EndTime > 0 ? date(DATE_FMT, $this->EndTime) : ''),
         $this->Result, $this->Rank, $this->Comment, $this->Note );
   }

   /*! \brief Inserts or updates tournament-result in database. */
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
      $entityData = $this->fillEntityData(true);
      $result = $entityData->insert( "TournamentResult.insert(%s)" );
      if ( $result )
         $this->ID = mysql_insert_id();
      self::delete_cache_tournament_result( 'TournamentResult.insert', $this->tid );
      return $result;
   }

   public function update()
   {
      $entityData = $this->fillEntityData();
      $result = $entityData->update( "TournamentResult.update(%s)" );
      self::delete_cache_tournament_result( 'TournamentResult.update', $this->tid );
      return $result;
   }

   public function delete()
   {
      $entityData = $this->fillEntityData();
      $result = $entityData->delete( "TournamentResult.delete(%s)" );
      self::delete_cache_tournament_result( 'TournamentResult.delete', $this->tid );
      return $result;
   }

   public function fillEntityData()
   {
      // checked fields: Status
      $data = $GLOBALS['ENTITY_TOURNAMENT_RESULT']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'rid', $this->rid );
      $data->set_value( 'Rating', $this->Rating );
      $data->set_value( 'Type', $this->Type );
      $data->set_value( 'Round', $this->Round );
      $data->set_value( 'StartTime', $this->StartTime );
      $data->set_value( 'EndTime', $this->EndTime );
      $data->set_value( 'Result', $this->Result );
      $data->set_value( 'Rank', $this->Rank );
      $data->set_value( 'Comment', $this->Comment );
      $data->set_value( 'Note', $this->Note );
      return $data;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of TournamentResult-objects for given tournament-id. */
   public static function build_query_sql( $tid=0, $with_player_info=false )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_RESULT']->newQuerySQL('TRS');
      if ( $tid > 0 )
         $qsql->add_part( SQLP_WHERE, "TRS.tid='$tid'" );
      if ( $with_player_info )
      {
         $qsql->add_part( SQLP_FIELDS,
               'TRP.Name AS TRP_Name', 'TRP.Handle AS TRP_Handle',
               'TRP.Country AS TRP_Country', 'TRP.Rating2 AS TRP_Rating2' );
         $qsql->add_part( SQLP_FROM,
               'INNER JOIN Players AS TRP ON TRP.ID=TRS.uid' );
      }
      return $qsql;
   }//build_query_sql

   /*! \brief Returns TournamentResult-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $tres = new TournamentResult(
            // from TournamentResult
            @$row['ID'],
            @$row['tid'],
            @$row['uid'],
            @$row['rid'],
            @$row['Rating'],
            @$row['Type'],
            @$row['Round'],
            @$row['X_StartTime'],
            @$row['X_EndTime'],
            @$row['Result'],
            @$row['Rank'],
            @$row['Comment'],
            @$row['Note']
         );
      return $tres;
   }

   /*!
    * \brief Returns single TournamentResult-objects for given tournament-result-id and check against optionally
    *       given tournament-id.
    * \param $tid dies with error if $tid given and it doesn't match with tournament-id found in tournament-result
    * \return TournamentResult-object; null if nothing found
    */
   public static function load_tournament_result( $dbgmsg, $tresult_id, $tid=0 )
   {
      $tresult_id = (int)$tresult_id;
      $tid = (int)$tid;

      $qsql = self::build_query_sql( 0, true );
      $qsql->add_part( SQLP_WHERE, "TRS.ID=$tresult_id" );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "$dbgmsg.TournamentResult.load_tournament_result($tresult_id,$tid)",
         $qsql->get_select() );
      if ( !$row )
         return null;

      $tresult = TournamentResult::new_from_row($row);
      if ( $tid > 0 && $tresult->tid != $tid )
         error('', "$dbgmsg.TournamentResult.load_tournament_result.check.tid($tresult_id,$tid)");
      return $tresult;
   }//load_tournament_result

   /*! \brief Returns enhanced (passed) ListIterator with TournamentResult-objects for given tournament-id. */
   public static function load_tournament_results( $iterator, $tid=-1, $with_player_info=false )
   {
      $qsql = ( $tid >= 0 ) ? self::build_query_sql($tid, $with_player_info) : new QuerySQL();
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentResult:load_tournament_results", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $tresult = self::new_from_row( $row );
         $iterator->addItem( $tresult, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_tournament_results

   /*! \brief Returns type-text or all type-texts (if arg=null). */
   public static function getTypeText( $type=null )
   {
      static $ARR_TRESULT_TEXTS = null;

      // lazy-init of texts
      if ( is_null($ARR_TRESULT_TEXTS) )
      {
         $arr = array();
         $arr[TRESULTTYPE_TL_KING_OF_THE_HILL] = T_('King of the Hill#TRES_type');
         $arr[TRESULTTYPE_TL_SEQWINS] = T_('Sequently Wins');
         $arr[TRESULTTYPE_TRR_POOL_WINNER] = T_('Pool Winner#TRES_type');
         $ARR_TRESULT_TEXTS = $arr;
      }

      if ( is_null($type) )
         return $ARR_TRESULT_TEXTS;
      if ( !isset($ARR_TRESULT_TEXTS[$type]) )
         error('invalid_args', "TournamentResult:getTypeText($type)");
      return $ARR_TRESULT_TEXTS[$type];
   }//getTypeText

   public static function show_tournament_result( $t_status )
   {
      static $statuslist = array( TOURNEY_STATUS_ADMIN, TOURNEY_STATUS_PLAY, TOURNEY_STATUS_CLOSED );
      return in_array( $t_status, $statuslist );
   }

   public static function delete_cache_tournament_result( $dbgmsg, $tid )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_TRESULT, "TResult.$tid" );
   }

} // end of 'TournamentResult'
?>
