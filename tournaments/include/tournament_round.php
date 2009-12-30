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

require_once( 'include/std_classes.php' );

 /*!
  * \file tournament_round.php
  *
  * \brief Functions for handling tournament rounds (for pairing): tables TournamentRound
  */


 /*!
  * \class TournamentRound
  *
  * \brief Class to manage TournamentRound-table with pairing-related tournament-settings
  */

define('TROUND_STATUS_INIT',     'INIT');
define('TROUND_STATUS_POOLINIT', 'POOLINIT');
define('TROUND_STATUS_PAIRINIT', 'PAIRINIT');
define('TROUND_STATUS_GAMEINIT', 'GAMEINIT');
define('TROUND_STATUS_DONE',     'DONE');
define('CHECK_TROUND_STATUS', 'INIT|POOLINIT|PAIRINIT|GAMEINIT|DONE');


// lazy-init in TournamentRound::get..Text()-funcs
$ARR_GLOBALS_TOURNAMENT_ROUND = array();

class TournamentRound
{
   var $tid;
   var $Round;
   var $RulesID;
   var $Lastchanged;
   var $Status;
   var $MinPoolSize;
   var $MaxPoolSize;
   var $PoolCount;

   /*! \brief Constructs TournamentRound-object with specified arguments. */
   function TournamentRound( $tid=0, $round=0, $rules_id=0, $lastchanged=0,
         $status=TROUND_STATUS_INIT, $min_pool_size=0, $max_pool_size=0,
         $pool_count=0 )
   {
      $this->tid = (int)$tid;
      $this->Round = (int)$round;
      $this->RulesID = (int)$rules_id;
      $this->Lastchanged = (int)$lastchanged;
      $this->setStatus( $status );
      $this->MinPoolSize = (int)$min_pool_size;
      $this->MaxPoolSize = (int)$max_pool_size;
      $this->PoolCount = (int)$pool_count;
   }

   function setStatus( $status )
   {
      if( !preg_match( "/^(".CHECK_TROUND_STATUS.")$/", $status ) )
         error('invalid_args', "TournamentRound.setStatus($status)");
      $this->Status = $status;
   }

   function to_string()
   {
      return "tid=[{$this->tid}]"
            . ", Round=[{$this->Round}]"
            . ", RulesID=[{$this->RulesID}]"
            . ", Lastchanged=[{$this->Lastchanged}]"
            . ", Status=[{$this->Status}]"
            . ", MinPoolSize=[{$this->MinPoolSize}]"
            . ", MaxPoolSize=[{$this->MaxPoolSize}]"
            . ", PoolCount=[{$this->PoolCount}]"
         ;
   }

   /*! \brief Inserts or updates tournament-properties in database. */
   function persist()
   {
      $success = $this->insert();
      return $success;
   }

   /*! \brief Builds query-part for persistance (insert or update). */
   function build_persist_query_part()
   {
      // Status is checked
      return  " tid='{$this->tid}'"
            . ",Round='{$this->Round}'"
            . ",RulesID='{$this->RulesID}'"
            . ",Lastchanged=FROM_UNIXTIME({$this->Lastchanged})"
            . ",Status='" . mysql_addslashes($this->Status) . "'"
            . ",MinPoolSize='{$this->MinPoolSize}'"
            . ",MaxPoolSize='{$this->MaxPoolSize}'"
            . ",PoolCount='{$this->PoolCount}'"
         ;
   }

   /*!
    * \brief Inserts or replaces existing TournamentRound-entry.
    * \note sets Lastchanged=NOW
    */
   function insert()
   {
      global $NOW;
      $this->Lastchanged = $NOW;

      $result = db_query( "TournamentRound::insert({$this->tid},{$this->Round})",
            "REPLACE INTO TournamentRound SET "
            . $this->build_persist_query_part()
         );
      return $result;
   }

   /*!
    * \brief Updates TournamentRound-entry.
    * \note sets Lastchanged=NOW
    */
   function update()
   {
      global $NOW;
      $this->Lastchanged = $NOW;

      $result = db_query( "TournamentRound::update({$this->tid})",
            "UPDATE TournamentRound SET "
            . $this->build_persist_query_part()
            . " WHERE tid='{$this->tid}' AND Round='{$this->Round}' LIMIT 1"
         );
      return $result;
   }


   // ------------ static functions ----------------------------

   /*! \brief Deletes TournamentRound-entry for given id. */
   function delete_tournament_round( $tid, $round )
   {
      $result = db_query( "TournamentRound::delete_tournament_round($tid,$round)",
         "DELETE FROM TournamentRound WHERE tid='$tid' AND Round='{$this->Round}' LIMIT 1" );
      return $result;
   }

   /*! \brief Returns db-fields to be used for query of TournamentRound-objects for given tournament-id. */
   function build_query_sql( $tid )
   {
      // TournamentRound: tid,Round,RulesID,Lastchanged,Status,MinPoolSize,MaxPoolSize
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'TRD.*',
         'UNIX_TIMESTAMP(TRD.Lastchanged) AS X_Lastchanged' );
      $qsql->add_part( SQLP_FROM,
         'TournamentRound AS TRD' );
      $qsql->add_part( SQLP_WHERE, "TRD.tid='$tid'" );
      return $qsql;
   }

   /*! \brief Returns TournamentRound-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $trd = new TournamentRound(
            // from TournamentRound
            @$row['tid'],
            @$row['Round'],
            @$row['RulesID'],
            @$row['X_Lastchanged'],
            @$row['Status'],
            @$row['MinPoolSize'],
            @$row['MaxPoolSize'],
            @$row['PoolCount']
         );
      return $trd;
   }

   /*!
    * \brief Loads and returns TournamentRound-object for given tournament-ID.
    */
   function load_tournament_round( $tid, $round )
   {
      $result = NULL;
      if( $tid > 0 )
      {
         $qsql = TournamentRound::build_query_sql( $tid );
         $qsql->add_part( SQLP_LIMIT, '1' );

         if( $round > 0 ) // get specific round
            $qsql->add_part( SQLP_WHERE, "TRD.Round='{$round}'" );
         else // get latest round
            $qsql->add_part( SQLP_ORDER, "TRD.Round DESC'" );

         $row = mysql_single_fetch( "TournamentRound.load_tournament_round($tid,$round)",
            $qsql->get_select() );
         if( $row )
            $result = TournamentRound::new_from_row( $row );
      }
      return $result;
   }

   /*! \brief Returns enhanced (passed) ListIterator with TournamentRound-objects for given tournament-id. */
   function load_tournament_rounds( $iterator, $tid )
   {
      $qsql = TournamentRound::build_query_sql( $tid );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentRound.load_tournament_rounds", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $tourney = TournamentRound::new_from_row( $row );
         $iterator->addItem( $tourney, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   function getStatusText( $status=null )
   {
      global $ARR_GLOBALS_TOURNAMENT_ROUND;

      // lazy-init of texts
      $key = 'STATUS';
      if( !isset($ARR_GLOBALS_TOURNAMENT_ROUND[$key]) )
      {
         $arr = array();
         $arr[TROUND_STATUS_INIT]     = T_('Init#TRD_status');
         $arr[TROUND_STATUS_POOLINIT] = T_('Pool-Init#TRD_status');
         $arr[TROUND_STATUS_PAIRINIT] = T_('Pair-Init#TRD_status');
         $arr[TROUND_STATUS_GAMEINIT] = T_('Game-Init#TRD_status');
         $arr[TROUND_STATUS_DONE]     = T_('Done#TRD_status');
         $ARR_GLOBALS_TOURNAMENT_ROUND[$key] = $arr;
      }

      if( is_null($status) )
         return $ARR_GLOBALS_TOURNAMENT_ROUND[$key];
      if( !isset($ARR_GLOBALS_TOURNAMENT_ROUND[$key][$status]) )
         error('invalid_args', "Tournament.getStatusText($status)");
      return $ARR_GLOBALS_TOURNAMENT_ROUND[$key][$status];
   }

   /*! \brief Returns array with notes about tournament-round. */
   function build_notes( $intro=true )
   {
      $notes = array();
      //$notes[] = null; // empty line

      $notes[] = sprintf(
            T_('Tournament round status:<ul>'
               . '<li>%1$s = start new tournament round'."\n" // init
               . '<li>%2$s = new pools for tournament round created'."\n" // pool-init
               . '<li>%3$s = participants assigned to pools'."\n" // pair-init
               . '<li>%4$s = tournament games prepared, ready to start games'."\n" // game-init
               . '<li>%5$s = pairing for tournament round finished'."\n" // done
               . '</ul>'),
            TournamentRound::getStatusText(TROUND_STATUS_INIT),
            TournamentRound::getStatusText(TROUND_STATUS_POOLINIT),
            TournamentRound::getStatusText(TROUND_STATUS_PAIRINIT),
            TournamentRound::getStatusText(TROUND_STATUS_GAMEINIT),
            TournamentRound::getStatusText(TROUND_STATUS_DONE)
         );
      return $notes;
   }

} // end of 'TournamentRound'
?>
