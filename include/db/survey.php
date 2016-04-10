<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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

define('SURVEY_FLAG_USERLIST', 0x01);

define('SURVEY_POINTS_MAX', 25);
define('MAX_SURVEY_OPTIONS', 26); // labels A-Z
define('SQL_NO_POINTS', 256); // >range of tinyint


 /*!
  * \class Survey
  *
  * \brief Class to manage Survey-table
  */

global $ENTITY_SURVEY; //PHP5
$ENTITY_SURVEY = new Entity( 'Survey',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_INT,  'ID', 'uid', 'Flags', 'MinPoints', 'MaxPoints', 'UserCount',
      FTYPE_ENUM, 'Type', 'Status',
      FTYPE_TEXT, 'Title', 'Header',
      FTYPE_DATE, 'Created', 'Lastchanged'
   );

class Survey
{
   public $ID;
   public $uid;
   public $Type;
   public $Status;
   public $Flags;
   public $MinPoints;
   public $MaxPoints;
   public $UserCount;
   public $Title;
   public $Header;
   public $Created;
   public $Lastchanged;

   // non-DB fields

   public $User; // User-object
   public $SurveyOptions = array(); // SurveyOption-objects
   public $UserVoted = null; // Boolean: null=unset, true = there are votes in DB, false = not voted yet

   public $UserList = array(); // [ uid, ...]
   public $UserListHandles = array(); // [ Handle, =1234, ...] (with '='-prefix for numeric handles)
   public $UserListUserRefs = array(); // [ uid => [ ID/Handle/Name/C_RejectMsg => val ], ... ]

   /*! \brief Constructs Survey-object with specified arguments. */
   public function __construct( $id=0, $uid=0, $user=null, $type=SURVEY_TYPE_POINTS, $status=SURVEY_STATUS_NEW,
         $flags=0, $min_points=0, $max_points=0, $user_count=0, $title='', $header='', $created=0, $lastchanged=0 )
   {
      $this->ID = (int)$id;
      $this->uid = (int)$uid;
      $this->setType( $type );
      $this->setStatus( $status );
      $this->Flags = (int)$flags;
      $this->MinPoints = (int)$min_points;
      $this->MaxPoints = (int)$max_points;
      $this->UserCount = (int)$user_count;
      $this->Title = trim($title);
      $this->Header = trim($header);
      $this->Created = (int)$created;
      $this->Lastchanged = (int)$lastchanged;
      // non-DB fields
      $this->User = ($user instanceof User) ? $user : new User( $this->uid );
   }//__construct

   public function to_string()
   {
      return print_r($this, true);
   }

   public function setType( $type )
   {
      if ( !preg_match( "/^(".CHECK_SURVEY_TYPE.")$/", $type ) )
         error('invalid_args', "Survey.setType($type)");
      $this->Type = $type;
   }

   public function setStatus( $status )
   {
      if ( !preg_match( "/^(".CHECK_SURVEY_STATUS.")$/", $status ) )
         error('invalid_args', "Survey.setStatus($status)");
      $this->Status = $status;
   }

   public function setFlag( $flagmask, $value )
   {
      if ( $flagmask > 0 )
      {
         if ( $value )
            $this->Flags |= $flagmask;
         else
            $this->Flags &= ~$flagmask;
      }
   }//setFlag

   /*! \brief Inserts or updates Survey-entry in database. */
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
      $this->Created = $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      $result = $entityData->insert( "Survey.insert(%s)" );
      if ( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   public function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->update( "Survey.update(%s)" );
   }

   public function fillEntityData( $data=null )
   {
      if ( is_null($data) )
         $data = $GLOBALS['ENTITY_SURVEY']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'Type', $this->Type );
      $data->set_value( 'Status', $this->Status );
      $data->set_value( 'Flags', $this->Flags );
      $data->set_value( 'MinPoints', $this->MinPoints );
      $data->set_value( 'MaxPoints', $this->MaxPoints );
      $data->set_value( 'UserCount', $this->UserCount );
      $data->set_value( 'Created', $this->Created );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'Title', $this->Title );
      $data->set_value( 'Header', $this->Header );
      return $data;
   }

   public function hasUserVotes()
   {
      return ( $this->UserCount > 0 );
   }

   public function need_option_minpoints()
   {
      return ( $this->Type == SURVEY_TYPE_SINGLE || $this->Type == SURVEY_TYPE_MULTI );
   }

   public function loadUserList()
   {
      $this->UserList = array();
      $this->UserListHandles = array();
      $this->UserListUserRefs = array();

      if ( $this->ID > 0 )
      {
         $result = db_query( "Survey.loadUserList({$this->ID})",
            "SELECT SU.uid as ID, P.Handle, P.Name " .
            "FROM SurveyUser AS SU INNER JOIN Players AS P ON P.ID=SU.uid " .
            "WHERE SU.sid={$this->ID} ORDER BY SU.uid" );
         while ( $row = mysql_fetch_array( $result ) )
         {
            $this->UserList[] = $row['ID'];
            $this->UserListHandles[] = ( is_numeric($row['Handle']) ? '=' : '' ) . $row['Handle'];
            $this->UserListUserRefs[$row['ID']] = $row;
         }
         mysql_free_result($result);
      }
   }//loadUserList


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of Survey-objects for given survey-id. */
   public static function build_query_sql( $sid=0, $with_player=true )
   {
      $qsql = $GLOBALS['ENTITY_SURVEY']->newQuerySQL('S');
      if ( $with_player )
      {
         $qsql->add_part( SQLP_FIELDS,
            'S.uid AS SP_ID',
            'SP.Name AS SP_Name',
            'SP.Handle AS SP_Handle' );
         $qsql->add_part( SQLP_FROM,
            'INNER JOIN Players AS SP ON SP.ID=S.uid' );
      }
      if ( $sid > 0 )
         $qsql->add_part( SQLP_WHERE, "S.ID=$sid" );
      return $qsql;
   }//build_query_sql

   /*! \brief Returns Survey-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $survey = new Survey(
            // from Survey
            @$row['ID'],
            @$row['uid'],
            User::new_from_row( $row, 'SP_' ), // from Players SP
            @$row['Type'],
            @$row['Status'],
            @$row['Flags'],
            @$row['MinPoints'],
            @$row['MaxPoints'],
            @$row['UserCount'],
            @$row['Title'],
            @$row['Header'],
            @$row['X_Created'],
            @$row['X_Lastchanged']
         );
      return $survey;
   }//new_from_row

   /*!
    * \brief Loads and returns Survey-object for given survey-id limited to 1 result-entry.
    * \param $sid Survey.ID
    * \return NULL if nothing found; Survey-object otherwise
    */
   public static function load_survey( $sid, $with_player=true )
   {
      $qsql = self::build_query_sql( $sid, $with_player );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "Survey:load_survey.find_survey($sid)", $qsql->get_select() );
      return ($row) ? self::new_from_row($row) : NULL;
   }//load_survey

   /*! \brief Loads and returns Survey-object for given survey-QuerySQL; NULL if nothing found. */
   public static function load_survey_by_query( $qsql, $with_row=false )
   {
      $qsql->add_part( SQLP_LIMIT, '1' );
      $row = mysql_single_fetch( "Survey:load_survey_by_query()", $qsql->get_select() );
      if ( $with_row )
         return ($row) ? array( self::new_from_row($row), $row ) : array( NULL, NULL );
      else
         return ($row) ? self::new_from_row($row) : NULL;
   }//load_survey_by_query

   /*! \brief Returns enhanced (passed) ListIterator with Survey-objects. */
   public static function load_surveys( $iterator )
   {
      $qsql = self::build_query_sql();
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "Survey:load_surveys", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $survey = self::new_from_row( $row );
         $iterator->addItem( $survey, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_surveys

   public static function is_status_viewable( $status )
   {
      return ( $status == SURVEY_STATUS_ACTIVE || $status == SURVEY_STATUS_CLOSED );
   }

   public static function is_point_type( $type )
   {
      return ( $type == SURVEY_TYPE_POINTS || $type == SURVEY_TYPE_SUM );
   }

   public static function update_user_count( $sid, $diff )
   {
      if ( !is_numeric($diff) )
         error('invalid_args', "Survey.updateUserCount.check.diff($sid,$diff)");

      return db_query( "Survey.updateUserCount.upd($sid,$diff)",
         "UPDATE Survey SET UserCount=UserCount+($diff) WHERE ID=$sid LIMIT 1" );
   }//update_user_count

   /*!
    * \brief Persists user-list for survey in SurveyUser-table.
    *
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
   public static function persist_survey_userlist( $sid, $uids )
   {
      if ( !is_numeric($sid) || $sid <= 0 )
         error('invalid_args', "Survey:persist_survey_userlist.check.sid($sid)");
      if ( !is_array($uids) )
         error('invalid_args', "Survey:persist_survey_userlist.check.uids($sid)");

      $cnt_uids = count($uids);
      if ( $cnt_uids > 0 )
      {
         $uids = array_unique($uids);
         foreach ( $uids as $uid )
         {
            if ( !is_numeric($uid) || $uid <= GUESTS_ID_MAX )
               error('invalid_args', "Survey:persist_survey_userlist.check.uids.bad_uid($sid,$uid)");
         }
         $uids_sql = implode(',', $uids);
      }

      db_query( "Survey:persist_survey_userlist.del($sid,$cnt_uids)",
         "DELETE FROM SurveyUser WHERE sid=$sid" . ( $cnt_uids > 0 ? " AND uid NOT IN ($uids_sql)" : '' ) );

      if ( $cnt_uids > 0 )
      {
         db_query( "Survey:persist_survey_userlist.add($sid,$cnt_uids)",
            "INSERT IGNORE SurveyUser (sid,uid) " .
            "SELECT $sid, ID FROM Players WHERE ID IN ($uids_sql) LIMIT $cnt_uids" );
      }
   }//persist_survey_userlist

   /*! \brief Returns true if user is on Survey-userlist. */
   public static function exists_survey_user( $sid, $uid )
   {
      if ( !is_numeric($sid) || $sid <= 0 )
         error('invalid_args', "Survey:exists_survey_user.check.sid($sid,$uid)");
      if ( !is_numeric($uid) || $uid <= GUESTS_ID_MAX )
         error('invalid_args', "Survey:exists_survey_user.check.uid($sid,$uid)");

      $row = mysql_single_fetch( "Survey:exists_survey_user($sid,$uid)",
         "SELECT 1 FROM SurveyUser WHERE sid=$sid AND uid=$uid LIMIT 1" );
      return (bool) $row;
   }//exists_survey_user

} // end of 'Survey'
?>
