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

require_once 'include/globals.php';
require_once 'include/db_classes.php';
require_once 'include/std_classes.php';
require_once 'include/classlib_user.php';

 /*!
  * \file survey.php
  *
  * \brief Functions for managing surveys: tables Survey
  * \see specs/db/table-Voting.txt
  */

define('SURVEY_TYPE_POINTS', 'POINTS');
define('SURVEY_TYPE_SUM',    'SUM');
define('SURVEY_TYPE_SINGLE', 'SINGLE');
define('SURVEY_TYPE_MULTI',  'MULTI');
define('CHECK_SURVEY_TYPE', 'POINTS|SUM|SINGLE|MULTI');

define('SURVEY_STATUS_NEW',    'NEW');
define('SURVEY_STATUS_ACTIVE', 'ACTIVE');
define('SURVEY_STATUS_CLOSED', 'CLOSED');
define('SURVEY_STATUS_DELETE', 'DELETE');
define('CHECK_SURVEY_STATUS', 'NEW|ACTIVE|CLOSED|DELETE');

define('SURVEY_POINTS_MAX', 25);


 /*!
  * \class Survey
  *
  * \brief Class to manage Survey-table
  */

global $ENTITY_SURVEY; //PHP5
$ENTITY_SURVEY = new Entity( 'Survey',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_INT,  'ID', 'uid', 'Flags', 'MinPoints', 'MaxPoints',
      FTYPE_ENUM, 'SurveyType', 'Status',
      FTYPE_TEXT, 'Title',
      FTYPE_DATE, 'Created', 'Lastchanged'
   );

class Survey
{
   var $ID;
   var $uid;
   var $SurveyType;
   var $Status;
   var $Flags;
   var $MinPoints;
   var $MaxPoints;
   var $Title;
   var $Created;
   var $Lastchanged;

   // non-DB fields

   var $User; // User-object
   var $SurveyOptions; // SurveyOption-objects

   /*! \brief Constructs Survey-object with specified arguments. */
   function Survey( $id=0, $uid=0, $user=null, $type=SURVEY_TYPE_POINTS, $status=SURVEY_STATUS_NEW,
                    $flags=0, $min_points=0, $max_points=0, $title='', $created=0, $lastchanged=0 )
   {
      $this->ID = (int)$id;
      $this->uid = (int)$uid;
      $this->setSurveyType( $type );
      $this->setStatus( $status );
      $this->Flags = (int)$flags;
      $this->MinPoints = (int)$min_points;
      $this->MaxPoints = (int)$max_points;
      $this->Title = $title;
      $this->Created = (int)$created;
      $this->Lastchanged = (int)$lastchanged;
      // non-DB fields
      $this->User = (is_a($user, 'User')) ? $user : new User( $this->uid );
      $this->SurveyOptions = array();
   }

   function to_string()
   {
      return print_r($this, true);
   }

   function setSurveyType( $type )
   {
      if( !preg_match( "/^(".CHECK_SURVEY_TYPE.")$/", $type ) )
         error('invalid_args', "Survey.setSurveyType($type)");
      $this->SurveyType = $type;
   }

   function setStatus( $status )
   {
      if( !preg_match( "/^(".CHECK_SURVEY_STATUS.")$/", $status ) )
         error('invalid_args', "Survey.setStatus($status)");
      $this->Status = $status;
   }

   /*! \brief Inserts or updates Survey-entry in database. */
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
      $this->Created = $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      $result = $entityData->insert( "Survey.insert(%s)" );
      if( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->update( "Survey.update(%s)" );
   }

   function fillEntityData( $data=null )
   {
      if( is_null($data) )
         $data = $GLOBALS['ENTITY_SURVEY']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'SurveyType', $this->SurveyType );
      $data->set_value( 'Status', $this->Status );
      $data->set_value( 'Flags', $this->Flags );
      $data->set_value( 'MinPoints', $this->MinPoints );
      $data->set_value( 'MaxPoints', $this->MaxPoints );
      $data->set_value( 'Created', $this->Created );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'Title', $this->Title );
      return $data;
   }

   function need_option_minpoints()
   {
      return ( $this->SurveyType == SURVEY_TYPE_SINGLE || $this->SurveyType == SURVEY_TYPE_MULTI );
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of Survey-objects for given survey-id. */
   function build_query_sql( $sid=0, $with_player=true )
   {
      $qsql = $GLOBALS['ENTITY_SURVEY']->newQuerySQL('S');
      if( $with_player )
      {
         $qsql->add_part( SQLP_FIELDS,
            'S.uid AS SP_ID',
            'SP.Name AS SP_Name',
            'SP.Handle AS SP_Handle' );
         $qsql->add_part( SQLP_FROM,
            'INNER JOIN Players AS SP ON SP.ID=S.uid' );
      }
      if( $sid > 0 )
         $qsql->add_part( SQLP_WHERE, "S.ID=$sid" );
      return $qsql;
   }

   /*! \brief Returns Survey-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $survey = new Survey(
            // from Survey
            @$row['ID'],
            @$row['uid'],
            User::new_from_row( $row, 'SP_' ), // from Players SP
            @$row['SurveyType'],
            @$row['Status'],
            @$row['Flags'],
            @$row['MinPoints'],
            @$row['MaxPoints'],
            @$row['Title'],
            @$row['X_Created'],
            @$row['X_Lastchanged']
         );
      return $survey;
   }

   /*!
    * \brief Loads and returns Survey-object for given survey-id limited to 1 result-entry.
    * \param $sid Survey.ID
    * \return NULL if nothing found; Survey-object otherwise
    */
   function load_survey( $sid, $with_player=true )
   {
      $qsql = Survey::build_query_sql( $sid, $with_player );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "Survey::load_survey.find_survey($sid)", $qsql->get_select() );
      return ($row) ? Survey::new_from_row($row) : NULL;
   }

   /*! \brief Loads and returns Survey-object for given survey-QuerySQL; NULL if nothing found. */
   function load_survey_by_query( $qsql, $with_row=false )
   {
      $qsql->add_part( SQLP_LIMIT, '1' );
      $row = mysql_single_fetch( "Bulletin::load_survey_by_query()", $qsql->get_select() );
      if( $with_row )
         return ($row) ? array( Survey::new_from_row($row), $row ) : array( NULL, NULL );
      else
         return ($row) ? Survey::new_from_row($row) : NULL;
   }

   /*! \brief Returns enhanced (passed) ListIterator with Survey-objects. */
   function load_surveys( $iterator )
   {
      $qsql = Survey::build_query_sql();
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "Survey.load_surveys", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $survey = Survey::new_from_row( $row );
         $iterator->addItem( $survey, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

} // end of 'Survey'
?>
