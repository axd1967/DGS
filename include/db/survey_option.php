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

   // non-DB fields

   var $UserVotePoints; // SurveyVote.Points of specific user; null=unset

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
      $this->Title = $title;
      $this->Text = $text;
      // non-DB fields
      $this->UserVotePoints = null;
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
      $data->set_value( 'sid', $this->SurveyID );
      $data->set_value( 'Tag', $this->Tag );
      $data->set_value( 'SortOrder', $this->SortOrder );
      $data->set_value( 'MinPoints', $this->MinPoints );
      $data->set_value( 'UserCount', $this->UserCount );
      $data->set_value( 'Score', $this->Score );
      $data->set_value( 'Title', $this->Title );
      $data->set_value( 'Text', $this->Text );
      return $data;
   }

   /*! \brief Copies non-key-like fields from given SurveyOption-object to this object. */
   function copyValues( $sopt )
   {
      // NOTE: no cloning of fields: ID, sid, Tag
      $this->SortOrder = (int)$sopt->SortOrder;
      $this->MinPoints = (int)$sopt->MinPoints;
      $this->UserCount = (int)$sopt->UserCount;
      $this->Score = (int)$sopt->Score;
      $this->Title = $sopt->Title;
      $this->Text = $sopt->Text;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of SurveyOption-objects for given survey-id and survey-option-id. */
   function build_query_sql( $sid, $sopt_id=0 )
   {
      $qsql = $GLOBALS['ENTITY_SURVEY_OPTION']->newQuerySQL('SOPT');
      if( $sid > 0 )
         $qsql->add_part( SQLP_WHERE, "SOPT.sid=$sid" );
      if( $sopt_id > 0 )
         $qsql->add_part( SQLP_WHERE, "SOPT.ID=$sopt_id" );
      else
         $qsql->add_part( SQLP_ORDER, 'SOPT.SortOrder ASC' );
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

   /*! \brief Clone all fields from given SurveyOption-object to this object. */
   function cloneSurveyOption( $sopt )
   {
      return new SurveyOption( $sopt->ID, $sopt->SurveyID, $sopt->Tag, $sopt->SortOrder, $sopt->MinPoints,
         $sopt->UserCount, $sopt->Score, $sopt->Title, $sopt->Text );
   }

   /*!
    * \brief Loads and returns SurveyOption-object for given survey_option-id limited to 1 result-entry.
    * \param $sopt_id SurveyOption.ID
    * \return NULL if nothing found; SurveyOption-object otherwise
    */
   function load_survey_option( $sopt_id )
   {
      $qsql = SurveyOption::build_query_sql( 0, $sopt_id );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "SurveyOption::load_survey_option.find_surveyoption($sopt_id)", $qsql->get_select() );
      return ($row) ? SurveyOption::new_from_row($row) : NULL;
   }

   /*! \brief Returns enhanced (passed) ListIterator with SurveyOption-objects. */
   function load_survey_options( $iterator, $sid )
   {
      $qsql = SurveyOption::build_query_sql( $sid, 0 );
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

   function persist_survey_options( $sid, $arr_sopts, $all_fields=true )
   {
      if( !is_array($arr_sopts) || count($arr_sopts) == 0 )
         return false;

      $skip_fields = ($all_fields) ? null : array( 'UserCount', 'Score' );

      $entity_sopt = $GLOBALS['ENTITY_SURVEY_OPTION']->newEntityData();
      $arr_inserts = array();
      foreach( $arr_sopts as $so )
      {
         $data_sopt = $so->fillEntityData( $entity_sopt );
         $arr_inserts[] = $data_sopt->build_sql_insert_values(false, /*with-PK*/true, $skip_fields);
      }

      $query = $entity_sopt->build_sql_insert_values(true, /*with-PK*/true, $skip_fields)
         . implode(',', $arr_inserts)
         . ' ON DUPLICATE KEY UPDATE '
         . 'sid=VALUES(sid), Tag=VALUES(Tag), SortOrder=VALUES(SortOrder), MinPoints=VALUES(MinPoints), '
         . 'UserCount=VALUES(UserCount), Score=VALUES(Score), Title=VALUES(Title), Text=VALUES(Text)';

      return db_query( "SurveyOption::persist_survey_options.on_dupl_key($sid)", $query );
   }//persist_survey_options

   /*! \brief Updates SurveyOption-entries with $arr_upd = [ ID => [ diff_usercount, diff_score], ... ]. */
   function update_aggregates_survey_options( $sid, $arr_upd )
   {
      if( !is_array($arr_upd) || count($arr_upd) == 0 )
         return false;

      $sid = (int)$sid;

      $table_sopt = $GLOBALS['ENTITY_SURVEY_OPTION']->table;
      $arr_inserts = array();
      foreach( $arr_upd as $id => $arr )
      {
         list( $diff_usercount, $diff_score ) = $arr;
         if( $diff_usercount || $diff_score )
            $arr_inserts[] = "($id,$diff_usercount,$diff_score)";
      }

      $query = "INSERT INTO $table_sopt (ID,UserCount,Score) VALUES " . implode(', ', $arr_inserts)
         . " ON DUPLICATE KEY UPDATE UserCount=UserCount+(VALUES(UserCount)), Score=Score+(VALUES(Score))";

      return db_query( "SurveyOption::update_aggregates_survey_options.on_dupl_key($sid,$diff_usercount)", $query );
   }//update_aggregates_survey_options

   function delete_survey_options( $sid, $arr_sopts_id )
   {
      if( !is_array($arr_sopts_id) || count($arr_sopts_id) == 0 )
         return false;

      // check ids to delete
      $ids = array();
      foreach( $arr_sopts_id as $sopt_id )
      {
         if( !is_numeric($sopt_id) || $sopt_id <= 0 )
            error('invalid_args', "SurveyOption::delete_survey_options.check.id($sid,$sopt_id)");
         $ids[] = (int)$sopt_id;
      }
      $cnt_ids = count($ids);
      $ids = implode(',', $ids);
      $sid = (int)$sid;

      $result = db_query( "SurveyOption::delete_survey_options.del($sid;$ids)",
         "DELETE FROM SurveyOption WHERE ID IN ($ids) AND sid=$sid LIMIT $cnt_ids" );
      return $result;
   }//delete_survey_options

} // end of 'SurveyOption'
?>
