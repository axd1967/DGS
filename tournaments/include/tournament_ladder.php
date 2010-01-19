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

require_once( 'include/db_classes.php' );

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
      FTYPE_INT,  'tid', 'rid', 'uid', 'Rank', 'BestRank',
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

   /*! \brief Constructs TournamentLadder-object with specified arguments. */
   function TournamentLadder( $tid=0, $rid=0, $uid=0, $created=0, $rank_changed=0, $rank=0, $bestrank=0 )
   {
      $this->tid = (int)$tid;
      $this->rid = (int)$rid;
      $this->uid = (int)$uid;
      $this->Created = (int)$created;
      $this->RankChanged = (int)$rank_changed;
      $this->Rank = $rank;
      $this->BestRank = $bestrank;
   }

   function to_string()
   {
      return print_r($this, true);
   }

   /*! \brief Inserts or updates tournament-ladder in database. */
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
      $this->Created = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->insert( "TournamentLadder::insert(%s,{$this->tid},{$this->rid})" );
   }

   function update()
   {
      $entityData = $this->fillEntityData();
      return $entityData->update( "TournamentLadder::update(%s,{$this->tid},{$this->rid})" );
   }

   function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "TournamentLadder::delete(%s,{$this->tid},{$this->rid})" );
   }

   function fillEntityData()
   {
      $data = $GLOBALS['ENTITY_TOURNAMENT_LADDER']->newEntityData();
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'rid', $this->rid );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'Created', $this->Created );
      $data->set_value( 'RankChanged', $this->RankChanged );
      $data->set_value( 'Rank', $this->Rank );
      $data->set_value( 'BestRank', $this->BestRank );
      return $data;
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
      $tp = new TournamentLadder(
            // from TournamentLadder
            @$row['tid'],
            @$row['rid'],
            @$row['uid'],
            @$row['X_Created'],
            @$row['X_RankChanged'],
            @$row['Rank'],
            @$row['BestRank']
         );
      return $tp;
   }

   /*! \brief Returns enhanced (passed) ListIterator with TournamentLadder-objects for given tournament-id. */
   function load_tournament_ladder( $iterator, $tid )
   {
      $qsql = TournamentLadder::build_query_sql( $tid );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentLadder.load_tournament_ladder", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $tourney = TournamentLadder::new_from_row( $row );
         $iterator->addItem( $tourney, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

   function get_edit_tournament_status()
   {
      static $statuslist = array( TOURNEY_STATUS_PAIR );
      return $statuslist;
   }

} // end of 'TournamentLadder'
?>
