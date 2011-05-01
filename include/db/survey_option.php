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

 /*!
  * \file survey_option.php
  *
  * \brief Functions for managing survey-options: tables SurveyOption
  * \see specs/db/table-Voting.txt
  */


 /*!
  * \class SurveyOption
  *
  * \brief Class to manage SurveyOption-table
  */

global $ENTITY_SURVEY_OPTION; //PHP5
$ENTITY_SURVEY_OPTION = new Entity( 'SurveyOption',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_INT,  'ID', 'sid', 'Tag', 'SortOrder', 'MinPoints', 'UserCount', 'Score',
      FTYPE_TEXT, 'Title', 'Text'
   );

class SurveyOption
{
   var $ID;
   var $SurveyID;
   var $Tag;
   var $SortOrder;
   var $MinPoints;
   var $UserCount;
   var $Score;
   var $Title;
   var $Text;

   /*! \brief Constructs SurveyOption-object with specified arguments. */
   function SurveyOption( $id=0, $sid=0, $tag=0, $sort_order=0, $min_points=0, $user_count=0, $score=0,
                          $title='', $text='' )
   {
      $this->ID = (int)$id;
      $this->SurveyID = (int)$sid;
      $this->Tag = (int)$tag;
      $this->SortOrder = (int)$sort_order;
      $this->MinPoints = (int)$min_points;
      $this->UserCount = (int)$user_count;
      $this->Score = (int)$score;
      $this->Title = $subject;
      $this->Text = $text;
   }

   function to_string()
   {
      return print_r($this, true);
   }

   /*! \brief Inserts or updates SurveyOption-entry in database. */
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
      $entityData = $this->fillEntityData();
      $result = $entityData->insert( "SurveyOption.insert(%s)" );
      if( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   function update()
   {
      $entityData = $this->fillEntityData();
      return $entityData->update( "SurveyOption.update(%s)" );
   }

   function fillEntityData( $data=null )
   {
      if( is_null($data) )
         $data = $GLOBALS['ENTITY_SURVEY_OPTION']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'sid', $this->sid );
      $data->set_value( 'Tag', $this->Tag );
      $data->set_value( 'SortOrder', $this->SortOrder );
      $data->set_value( 'MinPoints', $this->MinPoints );
      $data->set_value( 'UserCount', $this->UserCount );
      $data->set_value( 'Score', $this->Score );
      $data->set_value( 'Title', $this->Title );
      $data->set_value( 'Text', $this->Text );
      return $data;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of SurveyOption-objects for given survey-option-id. */
   function build_query_sql( $sopt_id=0 )
   {
      $qsql = $GLOBALS['ENTITY_SURVEY_OPTION']->newQuerySQL('SOPT');
      if( $sopt_id > 0 )
         $qsql->add_part( SQLP_WHERE, "SOPT.ID=$sopt_id" );
      return $qsql;
   }

   /*! \brief Returns SurveyOption-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $s_opt = new SurveyOption(
            // from SurveyOption
            @$row['ID'],
            @$row['sid'],
            @$row['Tag'],
            @$row['SortOrder'],
            @$row['MinPoints'],
            @$row['UserCount'],
            @$row['Score'],
            @$row['Title'],
            @$row['Text']
         );
      return $s_opt;
   }

   /*!
    * \brief Loads and returns SurveyOption-object for given survey_option-id limited to 1 result-entry.
    * \param $sopt_id SurveyOption.ID
    * \return NULL if nothing found; SurveyOption-object otherwise
    */
   function load_survey_option( $sopt_id )
   {
      $qsql = SurveyOption::build_query_sql( $sopt_id );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "SurveyOption::load_survey_option.find_surveyoption($sopt_id)", $qsql->get_select() );
      return ($row) ? SurveyOption::new_from_row($row) : NULL;
   }

   /*! \brief Returns enhanced (passed) ListIterator with SurveyOption-objects. */
   function load_survey_options( $iterator )
   {
      $qsql = SurveyOption::build_query_sql();
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "SurveyOption.load_survey_options", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $s_opt = SurveyOption::new_from_row( $row );
         $iterator->addItem( $s_opt, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

} // end of 'SurveyOption'
?>
