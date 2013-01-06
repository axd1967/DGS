<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/globals.php';
require_once 'include/db_classes.php';
require_once 'include/std_classes.php';

 /*!
  * \file survey_vote.php
  *
  * \brief Functions for managing survey-votes: tables SurveyVote
  * \see specs/db/table-Voting.txt
  */



 /*!
  * \class SurveyVote
  *
  * \brief Class to manage SurveyVote-table
  */

global $ENTITY_SURVEY_VOTE; //PHP5
$ENTITY_SURVEY_VOTE = new Entity( 'SurveyVote',
      FTYPE_PKEY, 'soid', 'uid',
      FTYPE_INT,  'soid', 'uid', 'Points'
   );

class SurveyVote
{
   var $soid; // SurveyOption.ID
   var $uid;
   var $Points;

   /*! \brief Constructs SurveyVote-object with specified arguments. */
   function SurveyVote( $soid=0, $uid=0, $points=0 )
   {
      $this->soid = (int)$soid;
      $this->uid = (int)$uid;
      $this->Points = (int)$points;
   }

   function to_string()
   {
      return print_r($this, true);
   }

   /*! \brief Inserts or updates SurveyVote in database. */
   function persist()
   {
      $entityData = $this->fillEntityData();
      $query = $entityData->build_sql_insert_values(true)
         . $entityData->build_sql_insert_values()
         . " ON DUPLICATE KEY UPDATE Points=VALUES(Points)";
      return db_query( "SurveyVote.persist.on_dupl_key({$this->soid},{$this->uid},{$this->Points})", $query );
   }

   function insert()
   {
      $entityData = $this->fillEntityData();
      return $entityData->insert( "SurveyVote.insert(%s)" );
   }

   function update()
   {
      $entityData = $this->fillEntityData();
      return $entityData->update( "SurveyVote.update(%s)" );
   }

   function fillEntityData( $data=null )
   {
      if( is_null($data) )
         $data = $GLOBALS['ENTITY_SURVEY_VOTE']->newEntityData();
      $data->set_value( 'soid', $this->soid );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'Points', $this->Points );
      return $data;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of SurveyVote-objects for given survey-option-id, uid. */
   function build_query_sql( $soid=0, $uid=0 )
   {
      $qsql = $GLOBALS['ENTITY_SURVEY_VOTE']->newQuerySQL('SV');
      if( $soid > 0 )
         $qsql->add_part( SQLP_WHERE, "SV.soid=$soid" );
      if( $uid > 0 )
         $qsql->add_part( SQLP_WHERE, "SV.uid=$uid" );
      return $qsql;
   }

   /*! \brief Returns SurveyVote-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $s_vote = new SurveyVote(
            // from SurveyVote
            @$row['soid'],
            @$row['uid'],
            @$row['Points']
         );
      return $s_vote;
   }

   /*!
    * \brief Loads and returns SurveyVote-object for given survey-option-id, user-id limited to 1 result-entry.
    * \param $soid SurveyOption.ID
    * \param $uid Players.ID (voting user)
    * \return NULL if nothing found; SurveyVote-object otherwise
    */
   function load_survey_vote( $soid, $uid )
   {
      $qsql = SurveyVote::build_query_sql( $soid, $uid );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "SurveyVote::load_survey_vote.find_surveyvote($soid,$uid)", $qsql->get_select() );
      return ($row) ? SurveyVote::new_from_row($row) : NULL;
   }

   /*! \brief Returns enhanced (passed) ListIterator with SurveyVote-objects. */
   function load_survey_votes( $iterator, $soid, $uid=0 )
   {
      $qsql = SurveyVote::build_query_sql( $soid, $uid );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "SurveyVote.load_survey_votes($soid,$uid)", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $s_vote = SurveyVote::new_from_row( $row );
         $iterator->addItem( $s_vote, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

   /*!
    * \brief Adds/updates SurveyVote-table-entries.
    * \param $sid only for error-log
    * \param $arr_upd [ soid => Points, ... ]
    */
   function persist_survey_votes( $sid, $uid, $arr_upd )
   {
      if( !is_array($arr_upd) || count($arr_upd) == 0 )
         return false;

      $data = $GLOBALS['ENTITY_SURVEY_VOTE']->newEntityData();
      $data->set_value( 'uid', (int)$uid );

      $arr_inserts = array();
      foreach( $arr_upd as $soid => $points )
      {
         $data->set_value( 'soid', (int)$soid );
         $data->set_value( 'Points', (int)$points );
         $arr_inserts[] = $data->build_sql_insert_values(false, /*with-PK*/true);
      }

      $query = $data->build_sql_insert_values(true, /*with-PK*/true)
         . implode(',', $arr_inserts)
         . ' ON DUPLICATE KEY UPDATE Points=VALUES(Points)';

      return db_query( "SurveyOption::persist_survey_votes.on_dupl_key($sid,$uid)", $query );
   }//persist_survey_votes

} // end of 'SurveyVote'
?>
