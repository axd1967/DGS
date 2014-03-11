<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/classlib_user.php';
require_once 'include/db_classes.php';
require_once 'include/dgs_cache.php';
require_once 'tournaments/include/tournament_globals.php';

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
      FTYPE_INT,  'tid', 'uid', 'Flags',
      FTYPE_TEXT, 'Comment'
   );

class TournamentDirector
{
   public $tid;
   public $uid;
   public $Flags;
   public $Comment;
   public $User; // User-object

   /*! \brief Constructs TournamentDirector-object with specified arguments. */
   public function __construct( $tid=0, $uid=0, $flags=0, $comment='', $user=NULL )
   {
      $this->tid = (int)$tid;
      $this->uid = (int)$uid;
      $this->Flags = (int)$flags;
      $this->Comment = $comment;
      $this->User = ($user instanceof User) ? $user : new User( $this->uid );
   }

   public function formatFlags( $flags_val=null )
   {
      if ( is_null($flags_val) )
         $flags_val = $this->Flags;

      $arr = array();
      $arr_flags = self::getFlagsText();
      foreach ( $arr_flags as $flag => $flagtext )
      {
         if ( $flags_val & $flag )
            $arr[] = $flagtext;
      }
      return implode(', ', $arr);
   }//formatFlags

   /*! \brief Inserts or updates TournamentDirector in database. */
   public function persist()
   {
      if ( !is_null(self::load_tournament_director($this->tid, $this->uid, /*with_user*/false)) )
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   public function insert()
   {
      $entityData = $this->fillEntityData();
      $result = $entityData->insert( "TournamentDirector.insert(%s,{$this->uid})" );
      self::delete_cache_tournament_director( 'TournamentDirector.insert', $this->tid );
      return $result;
   }

   public function update()
   {
      $entityData = $this->fillEntityData();
      $result = $entityData->update( "TournamentDirector.update(%s,{$this->uid})" );
      self::delete_cache_tournament_director( 'TournamentDirector.update', $this->tid );
      return $result;
   }

   public function delete()
   {
      $entityData = $this->fillEntityData();
      $result = $entityData->delete( "TournamentDirector.delete(%s,{$this->uid})" );
      self::delete_cache_tournament_director( 'TournamentDirector.delete', $this->tid );
      return $result;
   }

   public function fillEntityData()
   {
      $data = $GLOBALS['ENTITY_TOURNAMENT_DIRECTOR']->newEntityData();
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'Flags', $this->Flags );
      $data->set_value( 'Comment', $this->Comment );
      return $data;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of TournamentDirector-object. */
   public static function build_query_sql( $tid, $with_user=true )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_DIRECTOR']->newQuerySQL('TD');
      if ( $with_user )
      {
         $qsql->add_part( SQLP_FIELDS,
            'TDPL.ID AS TDPL_ID', 'TDPL.Name AS TDPL_Name',
            'TDPL.Handle AS TDPL_Handle', 'TDPL.Rating2 AS TDPL_Rating2',
            'UNIX_TIMESTAMP(TDPL.Lastaccess) AS TDPL_X_Lastaccess' );
         $qsql->add_part( SQLP_FROM,
            'INNER JOIN Players AS TDPL ON TDPL.ID=TD.uid' );
      }
      $qsql->add_part( SQLP_WHERE,
         "TD.tid='$tid'" );
      return $qsql;
   }

   /*! \brief Returns TournamentDirector-object created from specified (db-)row. */
   public static function new_from_row( $row, $with_user=true )
   {
      $director = new TournamentDirector(
            // from TournamentDirector
            @$row['tid'],
            @$row['uid'],
            @$row['Flags'],
            @$row['Comment'],
            // from Players
            ( $with_user ? User::new_from_row($row, 'TDPL_') : null )
         );
      return $director;
   }

   /*! \brief Loads and returns TournamentDirector-object for given tournament-ID and user-id; NULL if nothing found. */
   public static function load_tournament_director( $tid, $uid, $with_user=true )
   {
      $result = NULL;
      if ( $tid > 0 )
      {
         $qsql = self::build_query_sql( $tid, $with_user );
         $qsql->add_part( SQLP_WHERE, "TD.uid='$uid'" );
         $qsql->add_part( SQLP_LIMIT, '1' );

         $row = mysql_single_fetch( "TournamentDirector.load_tournament_director($tid,$uid)",
            $qsql->get_select() );
         if ( $row )
            $result = self::new_from_row( $row, $with_user );
      }
      return $result;
   }//load_tournament_director

   /*! \brief Returns true, if there is at least one TD. for given tournament. */
   public static function has_tournament_director( $tid )
   {
      if ( $tid > 0 )
      {
         $qsql = $GLOBALS['ENTITY_TOURNAMENT_DIRECTOR']->newQuerySQL('TD');
         $qsql->add_part( SQLP_WHERE, "TD.tid=$tid" );
         $qsql->add_part( SQLP_LIMIT, '1' );

         $row = mysql_single_fetch( "TournamentDirector.has_tournament_director($tid)",
            $qsql->get_select() );
         if ( $row )
            return true;
      }
      return false;
   }//has_tournament_director

   /*! \brief Returns count of tournament-directors (TDs) for given tournament; 0 otherwise. */
   public static function count_tournament_directors( $tid )
   {
      if ( $tid <= 0 )
         error('invalid_args', "TournamentDirector:count_tournament_directors.check.tid($tid)");

      $row = mysql_single_fetch( "TournamentDirector:count_tournament_directors($tid)",
         "SELECT COUNT(*) AS X_Count FROM TournamentDirector WHERE tid=$tid" );
      return ($row) ? (int)@$row['X_Count'] : 0;
   }

   /*! \brief Returns enhanced (passed) ListIterator with TournamentDirector-objects. */
   public static function load_tournament_directors( $iterator, $tid )
   {
      $qsql = self::build_query_sql( $tid );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentDirector:load_tournament_directors($tid)", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $director = self::new_from_row( $row );
         $iterator->addItem( $director, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_tournament_directors

   /*! \brief Returns non-null arr( uid -> TournamentDirector.Flags, ... ) for given tournament-id. */
   public static function load_tournament_directors_flags( $tid )
   {
      $db_result = db_query( "TournamentDirector.load_tournament_directors_flags($tid)",
         "SELECT uid, Flags FROM TournamentDirector WHERE tid=$tid" );

      $result = array();
      while ( $row = mysql_fetch_array($db_result) )
         $result[$row['uid']] = $row['Flags'];
      mysql_free_result($db_result);

      return $result;
   }//load_tournament_directors_flags

   /*! \brief Returns list of uid of tournament-directors for given tournament. */
   public static function load_tournament_directors_uid( $tid )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_DIRECTOR']->newQuerySQL('TD');
      $qsql->clear_parts(SQLP_FIELDS);
      $qsql->add_part( SQLP_FIELDS, 'TD.uid' );
      $qsql->add_part( SQLP_WHERE, "TD.tid='$tid'" );
      $result = db_query( "TournamentDirector.load_tournament_directors_uid($tid)", $qsql->get_select() );

      $out = array();
      while ( $row = mysql_fetch_array($result) )
         $out[] = $row['uid'];
      mysql_free_result($result);

      return $out;
   }//load_tournament_directors_uid

   /*!
    * \brief Identify user from given user-info (uid or handle).
    * \return row-array with ID/Name/Handle/Rating/X_Lastaccess; null if nothing found
    */
   public static function load_user_row( $uid, $uhandle )
   {
      $player_query = 'SELECT ID, Name, Handle, Rating2, '
            . 'UNIX_TIMESTAMP(Lastaccess) AS X_Lastaccess FROM Players WHERE ';

      if ( $uid && is_numeric($uid) )
      {
         // load user by ID
         $row = mysql_single_fetch( "TournamentDirector.load_user_row.find_user.id($uid)",
            $player_query . "ID=$uid LIMIT 1" );
         if ( $row )
            return $row;
      }

      if ( $uhandle != '' )
      {
         // load user by userid
         $row = mysql_single_fetch( "edit_director.find_user.handle($uid,$uhandle)",
            $player_query . "Handle='" . mysql_addslashes($uhandle) . "' LIMIT 1");
         if ( $row )
            return $row;
      }

      return null;
   }//load_user_row

   /*!
    * \brief Assert with error (if $die set), that tourney has at least one TD.
    * \param $count_TDs give number of TDs (T-directors) or load count from DB if null
    * \return true if check is ok, false on error
    */
   public static function assert_min_directors( $tid, $t_status, $die=true, $count_TDs=null )
   {
      static $allowed_status = array( TOURNEY_STATUS_ADMIN, TOURNEY_STATUS_NEW, TOURNEY_STATUS_DELETE );
      $has_error = false;
      if ( !in_array($t_status, $allowed_status) )
      {
         $cntTD = ( is_null($count_TDs) || !is_numeric($count_TDs) )
            ? self::count_tournament_directors($tid)
            : $count_TDs;
         if ( $cntTD <= 1 )
            $has_error = true;
      }

      if ( $die && $has_error )
         error('tournament_director_min1', "TournamentDirector:assert_min_directors($tid,$t_status)");
      return !$has_error;
   }//assert_min_directors

   /*! \brief Returns Flags-text or all Flags-texts (if arg=null). */
   public static function getFlagsText( $flag=null )
   {
      static $ARR_TDIR_FLAGS = null; // flag => text

      // lazy-init of texts
      if ( is_null($ARR_TDIR_FLAGS) )
      {
         $arr = array();
         $arr[TD_FLAG_GAME_END] = T_('Game End#TD_flag');
         $arr[TD_FLAG_GAME_ADD_TIME] = T_('Add Time#TD_flag');
         $ARR_TDIR_FLAGS = $arr;
      }

      if ( is_null($flag) )
         return $ARR_TDIR_FLAGS;
      if ( !isset($ARR_TDIR_FLAGS[$flag]) )
         error('invalid_args', "TournamentDirector:getFlagsText($flag)");
      return $ARR_TDIR_FLAGS[$flag];
   }//getFlagsText

   public static function get_edit_tournament_status()
   {
      static $statuslist = array(
         TOURNEY_STATUS_NEW, TOURNEY_STATUS_REGISTER, TOURNEY_STATUS_PAIR,
         TOURNEY_STATUS_PLAY, TOURNEY_STATUS_CLOSED
      );
      return $statuslist;
   }

   public static function delete_cache_tournament_director( $dbgmsg, $tid )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_TDIRECTOR, "TDirector.$tid" );
   }

} // end of 'TournamentDirector'

?>
