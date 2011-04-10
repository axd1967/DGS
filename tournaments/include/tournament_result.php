<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Jens-Uwe Gaspar

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
   FTYPE_INT,   'ID', 'tid', 'uid', 'rid', 'Type', 'Round', 'Rank', 'RankKept',
   FTYPE_FLOAT, 'Rating',
   FTYPE_DATE,  'StartTime', 'EndTime'
   );

class TournamentResult
{
   var $ID;
   var $tid;
   var $uid;
   var $rid;
   var $Rating;
   var $Type;
   var $StartTime;
   var $EndTime;
   var $Round;
   var $Rank;
   var $RankKept;

   /*! \brief Constructs TournamentResult-object with specified arguments. */
   function TournamentResult( $id=0, $tid=0, $uid=0, $rid=0, $rating=NO_RATING, $type=0,
         $start_time=0, $end_time=0, $round=1, $rank=0, $rank_kept=0 )
   {
      $this->ID = (int)$id;
      $this->tid = (int)$tid;
      $this->uid = (int)$uid;
      $this->rid = (int)$rid;
      $this->Rating = (float)$rating;
      $this->Type = (int)$type;
      $this->StartTime = (int)$start_time;
      $this->EndTime = (int)$end_time;
      $this->Round = (int)$round;
      $this->Rank = (int)$rank;
      $this->RankKept = (int)$rank_kept;
   }

   function to_string()
   {
      return print_r( $this, true );
   }

   /*! \brief Inserts or updates tournament-result in database. */
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
      $entityData = $this->fillEntityData(true);
      $result = $entityData->insert( "TournamentResult::insert(%s)" );
      if( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   function update()
   {
      $entityData = $this->fillEntityData();
      return $entityData->update( "TournamentResult::update(%s)" );
   }

   function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "TournamentResult::delete(%s)" );
   }

   function fillEntityData()
   {
      // checked fields: Status
      $data = $GLOBALS['ENTITY_TOURNAMENT_RESULT']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'rid', $this->rid );
      $data->set_value( 'Rating', $this->Rating );
      $data->set_value( 'Type', $this->Type );
      $data->set_value( 'StartTime', $this->StartTime );
      $data->set_value( 'EndTime', $this->EndTime );
      $data->set_value( 'Round', $this->Round );
      $data->set_value( 'Rank', $this->Rank );
      $data->set_value( 'RankKept', $this->RankKept );
      return $data;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of TournamentResult-objects for given tournament-id. */
   function build_query_sql( $tid=0 )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_RESULT']->newQuerySQL('TRS');
      if( $tid > 0 )
         $qsql->add_part( SQLP_WHERE, "TRS.tid='$tid'" );
      return $qsql;
   }

   /*! \brief Returns TournamentResult-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $tres = new TournamentResult(
            // from TournamentResult
            @$row['ID'],
            @$row['tid'],
            @$row['uid'],
            @$row['rid'],
            @$row['Rating'],
            @$row['Type'],
            @$row['X_StartTime'],
            @$row['X_EndTime'],
            @$row['Round'],
            @$row['Rank'],
            @$row['RankKept']
         );
      return $tres;
   }

   /*! \brief Returns enhanced (passed) ListIterator with TournamentResult-objects for given tournament-id. */
   function load_tournament_results( $iterator, $tid=-1 )
   {
      $qsql = ( $tid >= 0 ) ? TournamentResult::build_query_sql($tid) : new QuerySQL();
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentResult::load_tournament_results", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $tresult = TournamentResult::new_from_row( $row );
         $iterator->addItem( $tresult, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

   function show_tournament_result( $t_status )
   {
      static $statuslist = array( TOURNEY_STATUS_ADMIN, TOURNEY_STATUS_PLAY, TOURNEY_STATUS_CLOSED );
      return in_array( $t_status, $statuslist );
   }

} // end of 'TournamentResult'
?>