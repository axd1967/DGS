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
require_once( 'include/classlib_user.php' );

 /*!
  * \file tournament_director.php
  *
  * \brief Functions for handling tournament directors: tables TournamentDirector
  */


 /*!
  * \class TournamentDirector
  *
  * \brief Class to manage TournamentDirector-table
  */

global $ENTITY_TOURNAMENT_DIRECTOR; //PHP5
$ENTITY_TOURNAMENT_DIRECTOR = new Entity( 'TournamentDirector',
      FTYPE_PKEY, 'tid', 'uid',
      FTYPE_INT,  'tid', 'uid',
      FTYPE_TEXT, 'Comment'
   );

class TournamentDirector
{
   var $tid;
   var $uid;
   var $Comment;
   var $User; // User-object

   /*! \brief Constructs TournamentDirector-object with specified arguments. */
   function TournamentDirector( $tid=0, $uid=0, $comment='', $user=NULL )
   {
      $this->tid = (int)$tid;
      $this->uid = (int)$uid;
      $this->Comment = $comment;
      $this->User = (is_a($user, 'User')) ? $user : new User( $this->uid );
   }

   /*! \brief Inserts or updates TournmentDirector in database. */
   function persist()
   {
      if( TournamentDirector::isTournamentDirector($this->tid, $this->uid) )
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   function insert()
   {
      $entityData = $this->fillEntityData();
      return $entityData->insert( "TournamentDirector::insert(%s,{$this->uid})" );
   }

   function update()
   {
      $entityData = $this->fillEntityData();
      return $entityData->update( "TournamentDirector::update(%s,{$this->uid})" );
   }

   function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "TournamentDirector::delete(%s,{$this->uid})" );
   }

   function fillEntityData()
   {
      $data = $GLOBALS['ENTITY_TOURNAMENT_DIRECTOR']->newEntityData();
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'Comment', $this->Comment );
      return $data;
   }

   // ------------ static functions ----------------------------

   /*! \brief Checks, if user is a tournament director of given tournament. */
   function isTournamentDirector( $tid, $uid )
   {
      return (bool)mysql_single_fetch( "TournamentDirector.isTournamentDirector($tid,$uid)",
         "SELECT 1 FROM TournamentDirector WHERE tid='$tid' AND uid='$uid' LIMIT 1" );
   }

   /*! \brief Returns db-fields to be used for query of TournamentDirector-object. */
   function build_query_sql( $tid )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_DIRECTOR']->newQuerySQL('TD');
      $qsql->add_part( SQLP_FIELDS,
         'TDPL.ID AS TDPL_ID', 'TDPL.Name AS TDPL_Name',
         'TDPL.Handle AS TDPL_Handle', 'TDPL.Rating2 AS TDPL_Rating2',
         'UNIX_TIMESTAMP(TDPL.Lastaccess) AS TDPL_X_Lastaccess' );
      $qsql->add_part( SQLP_FROM,
         'INNER JOIN Players AS TDPL ON TDPL.ID=TD.uid' );
      $qsql->add_part( SQLP_WHERE,
         "TD.tid='$tid'" );
      return $qsql;
   }

   /*! \brief Returns TournamentDirector-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $director = new TournamentDirector(
            // from TournamentDirector
            @$row['tid'],
            @$row['uid'],
            @$row['Comment'],
            // from Players
            User::new_from_row( $row, 'TDPL_' )
         );
      return $director;
   }

   /*!
    * \brief Loads and returns TournamentDirector-object for given
    *        tournament-ID and user-id; NULL if nothing found.
    */
   function load_tournament_director( $tid, $uid )
   {
      $result = NULL;
      if( $tid > 0 )
      {
         $qsql = TournamentDirector::build_query_sql( $tid );
         $qsql->add_part( SQLP_WHERE, "TD.uid='$uid'" );
         $qsql->add_part( SQLP_LIMIT, '1' );

         $row = mysql_single_fetch( "TournamentDirector.load_tournament_director($tid,$uid)",
            $qsql->get_select() );
         if( $row )
            $result = TournamentDirector::new_from_row( $row );
      }
      return $result;
   }

   /*! \brief Returns true, if there is at least one TD. for given tournament. */
   function has_tournament_director( $tid )
   {
      if( $tid > 0 )
      {
         $qsql = $GLOBALS['ENTITY_TOURNAMENT_DIRECTOR']->newQuerySQL('TD');
         $qsql->add_part( SQLP_WHERE, "TD.tid=$tid" );
         $qsql->add_part( SQLP_LIMIT, '1' );

         $row = mysql_single_fetch( "TournamentDirector.has_tournament_director($tid)",
            $qsql->get_select() );
         if( $row )
            return true;
      }
      return false;
   }

   /*! \brief Returns count of tournament-directors (TDs) for given tournament; 0 otherwise. */
   function count_tournament_directors( $tid )
   {
      if( $tid <= 0 )
         error('invalid_args', "TournamentDirector::count_tournament_directors($tid)");

      $table = $GLOBALS['ENTITY_TOURNAMENT_DIRECTOR']->table;
      $row = mysql_single_fetch( "TournamentDirector::count_tournament_directors($tid)",
         "SELECT COUNT(*) AS X_Count FROM $table WHERE tid=$tid" );
      return ($row) ? (int)@$row['X_Count'] : 0;
   }

   /*! \brief Returns enhanced (passed) ListIterator with TournamentDirector-objects. */
   function load_tournament_directors( $iterator, $tid )
   {
      $qsql = TournamentDirector::build_query_sql( $tid );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentDirector.load_tournament_directors($tid)", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $director = TournamentDirector::new_from_row( $row );
         $iterator->addItem( $director, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

   /*!
    * \brief Identify user from given user-info (uid or handle).
    * \return row-array with ID/Name/Handle/Rating/X_Lastaccess; null if nothing found
    */
   function load_user_row( $uid, $uhandle )
   {
      $player_query = 'SELECT ID, Name, Handle, Rating2, '
            . 'UNIX_TIMESTAMP(Lastaccess) AS X_Lastaccess FROM Players WHERE ';

      if( $uid && is_numeric($uid) )
      {
         // load user by ID
         $row = mysql_single_fetch( "TournamentDirector.load_user_row.find_user.id($uid)",
            $player_query . "ID=$uid LIMIT 1" );
         if( $row )
            return $row;
      }

      if( $uhandle != '' )
      {
         // load user by userid
         $row = mysql_single_fetch( "edit_director.find_user.handle($uid,$uhandle)",
            $player_query . "Handle='" . mysql_addslashes($uhandle) . "' LIMIT 1");
         if( $row )
            return $row;
      }

      return null;
   }

   /*!
    * \brief Assert with error (if $die set), that tourney has at least one TD.
    * \param $count_TDs give number of TDs (T-directors) or load count from DB if null
    * \return true if check is ok, false on error
    */
   function assert_min_directors( $tid, $t_status, $die=true, $count_TDs=null )
   {
      static $allowed_status = array( TOURNEY_STATUS_ADMIN, TOURNEY_STATUS_NEW, TOURNEY_STATUS_DELETE );
      $has_error = false;
      if( !in_array($t_status, $allowed_status) )
      {
         $cntTD = ( is_null($count_TDs) || !is_numeric($count_TDs) )
            ? TournamentDirector::count_tournament_directors($tid)
            : $count_TDs;
         if( $cntTD <= 1 )
            $has_error = true;
      }

      if( $die && $has_error )
         error('tournament_director_min1', "TournamentDirector::assert_min_directors($tid,$t_status)");
      return !$has_error;
   }

   function get_edit_tournament_status()
   {
      static $statuslist = array(
         TOURNEY_STATUS_NEW, TOURNEY_STATUS_REGISTER, TOURNEY_STATUS_PAIR,
         TOURNEY_STATUS_PLAY, TOURNEY_STATUS_CLOSED
      );
      return $statuslist;
   }

} // end of 'TournamentDirector'

?>
