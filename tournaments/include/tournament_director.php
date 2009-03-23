<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Jens-Uwe Gaspar

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
         $success = TournamentDirector::update_tournament_director( $this->tid, $this->uid, $this->Comment );
      else
         $success = TournamentDirector::insert_tournament_director( $this->tid, $this->uid, $this->Comment );
      return $success;
   }

   // ------------ static functions ----------------------------

   /*! \brief Checks, if user is a tournament director of given tournament. */
   function isTournamentDirector( $tid, $uid )
   {
      $row = mysql_single_fetch( "TournamentDirector.isTournamentDirector($tid,$uid)",
         "SELECT 1 FROM TournamentDirector WHERE tid='$tid' AND uid='$uid' LIMIT 1" );
      return (bool)$row;
   }

   /*! \brief Inserts TournamentDirector-entry with given tournament-/user-id and comment. */
   function insert_tournament_director( $tid, $uid, $comment='' )
   {
      $result = db_query( "TournamentDirector::insert_tournament_director($tid,$uid,$comment)",
         "INSERT INTO TournamentDirector SET "
            . " tid='$tid'"
            . ",uid='$uid'"
            . ",Comment='".mysql_addslashes($comment)."'"
         );
      return $result;
   }

   /*! \brief Updates TournamentDirector-entry with given tournament-/user-id and comment. */
   function update_tournament_director( $tid, $uid, $comment='' )
   {
      $result = db_query( "TournamentDirector::update_tournament_director($tid,$uid,$comment)",
         "UPDATE TournamentDirector SET "
            . " Comment='".mysql_addslashes($comment)."'"
            . " WHERE tid='$tid' AND uid='$uid' LIMIT 1"
         );
      return $result;
   }

   /*! \brief Deletes TournamentDirector-entry for given tournament- and user-id. */
   function delete_tournament_director( $tid, $uid )
   {
      $result = db_query( "TournamentDirector::delete_tournament_director($tid,$uid)",
         "DELETE FROM TournamentDirector WHERE tid='$tid' AND uid='$uid' LIMIT 1" );
      return $result;
   }

   /*! \brief Returns db-fields to be used for query of TournamentDirector-object. */
   function build_query_sql( $tid )
   {
      // TournamentDirector: tid,uid,Comment
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'TD.*',
         'TDPL.ID AS TDPL_ID', 'TDPL.Name AS TDPL_Name',
         'TDPL.Handle AS TDPL_Handle', 'TDPL.Rating2 AS TDPL_Rating2',
         'UNIX_TIMESTAMP(TDPL.Lastaccess) AS TDPL_X_Lastaccess' );
      $qsql->add_part( SQLP_FROM,
         'TournamentDirector AS TD',
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

} // end of 'TournamentDirector'

?>
