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
require_once( 'tournaments/include/tournament_director.php' );

 /*!
  * \file tournament.php
  *
  * \brief Functions for handling tournaments: tables Tournament
  */


define('TOURNEY_DATEFMT',    'YYYY-MM-DD hh:mm' ); // user-input for parsing
define('DATEFMT_TOURNAMENT', 'Y-m-d H:i'); // for output


 /*!
  * \class Tournament
  *
  * \brief Class to manage Tournament-table
  */

define('TOURNEY_SCOPE_DRAGON',  'DRAGON');
define('TOURNEY_SCOPE_PUBLIC',  'PUBLIC');
define('TOURNEY_SCOPE_PRIVATE', 'PRIVATE');
define('CHECK_TOURNEY_SCOPE', 'DRAGON|PUBLIC|PRIVATE');

define('TOURNEY_TYPE_ROUND_ROBIN', 'ROUNDROBIN');
define('CHECK_TOURNEY_TYPE', 'ROUNDROBIN');

define('TOURNEY_STATUS_ADMIN',    'ADM');
define('TOURNEY_STATUS_NEW',      'NEW');
define('TOURNEY_STATUS_REGISTER', 'REG');
define('TOURNEY_STATUS_PAIR',     'PAIR');
define('TOURNEY_STATUS_PLAY',     'PLAY');
define('TOURNEY_STATUS_CLOSED',   'CLOSED');
define('CHECK_TOURNEY_STATUS', 'ADM|NEW|REG|PAIR|PLAY|CLOSED');

// lazy-init in Tournament::get..Text()-funcs
$ARR_GLOBALS_TOURNAMENT = array();

class Tournament
{
   var $ID;
   var $Scope;
   var $Type;
   var $Title;
   var $Description;
   var $Owner_ID;
   var $Owner_Handle;
   var $Status;
   var $Created;
   var $Lastchanged;
   var $StartTime;
   var $EndTime;

   /*! \brief Constructs ConfigBoard-object with specified arguments. */
   function Tournament( $id=0, $scope=TOURNEY_SCOPE_PUBLIC, $type=TOURNEY_TYPE_ROUND_ROBIN,
                        $title='', $description='', $owner_id=0, $owner_handle='',
                        $status=TOURNEY_STATUS_NEW, $created=0, $lastchanged=0,
                        $starttime=0, $endtime=0 )
   {
      $this->ID = (int)$id;
      $this->setScope( $scope );
      $this->setType( $type );
      $this->Title = $title;
      $this->Description = $description;
      $this->Owner_ID = (int)$owner_id;
      $this->Owner_Handle = $owner_handle;
      $this->setStatus( $status );
      $this->Created = (int)$created;
      $this->Lastchanged = (int)$lastchanged;
      $this->StartTime = (int)$starttime;
      $this->EndTime = (int)$endtime;
   }

   function to_string()
   {
      return print_r( $this, true );
   }

   function setScope( $scope )
   {
      if( !preg_match( "/^(".CHECK_TOURNEY_SCOPE.")$/", $scope ) )
         error('invalid_args', "Tournament.setScope($scope)");
      $this->Scope = $scope;
   }

   function setType( $type )
   {
      if( !preg_match( "/^(".CHECK_TOURNEY_TYPE.")$/", $type ) )
         error('invalid_args', "Tournament.setType($type)");
      $this->Type = $type;
   }

   function setStatus( $status )
   {
      if( !preg_match( "/^(".CHECK_TOURNEY_STATUS.")$/", $status ) )
         error('invalid_args', "Tournament.setStatus($status)");
      $this->Status = $status;
   }

   /*! \brief Inserts or updates tournment in database. */
   function persist()
   {
      if( $this->ID > 0 )
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   /*! \brief Builds query-part for persistance (insert or update). */
   function build_persist_query_part( $withCreated )
   {
      // Scope/Type/Status are checked
      return  " Scope='{$this->Scope}'"
            . ",Type='{$this->Type}'"
            . ",Title='" . mysql_addslashes($this->Title) . "'"
            . ",Description='" . mysql_addslashes($this->Description) . "'"
            . ",Owner_ID='{$this->Owner_ID}'"
            . ",Status='{$this->Status}'"
            . ( $withCreated ? ",Created=FROM_UNIXTIME({$this->Created})" : '' )
            . ",Lastchanged=FROM_UNIXTIME({$this->Lastchanged})"
            . ",StartTime=FROM_UNIXTIME({$this->StartTime})"
            . ( $this->EndTime > 0 ? ",EndTime=FROM_UNIXTIME({$this->EndTime})" : '' )
         ;
   }

   /*!
    * \brief Inserts Tournament-entry.
    * \note sets Created=NOW, Lastchanged=NOW, StartTime=NOW
    * \note sets ID to inserted Tournament.ID
    */
   function insert()
   {
      global $NOW;
      $this->Created = $NOW;
      $this->Lastchanged = $NOW;
      $this->StartTime = $NOW;

      $result = db_query( "Tournament::insert({$this->ID})",
            "INSERT INTO Tournament SET "
            . $this->build_persist_query_part(true)
         );
      if( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   /*!
    * \brief Updates Tournament-entry.
    * \note sets Lastchanged=NOW
    */
   function update()
   {
      global $NOW;
      $this->Lastchanged = $NOW;

      $result = db_query( "Tournament::update({$this->ID})",
            "UPDATE Tournament SET "
            . $this->build_persist_query_part(false /*-Created*/)
            . " WHERE ID='{$this->ID}' LIMIT 1"
         );
      return $result;
   }

   /*! \brief Returns true if given user can edit tournament. */
   function allow_edit_tournaments( $uid )
   {
      if( $uid <= GUESTS_ID_MAX ) // forbidden for guests
         return false;

      // logged-in admin is allowed anything
      if( Tournament::isAdmin() )
         return true;

      // edit allowed for T-owner or TD
      if( $this->Owner_ID == $uid || TournamentDirector::isTournamentDirector($this->ID, $uid) )
         return true;

      return false;
   }

   /*!
    * \brief Returns true if given user can edit tournament directors.
    * \param $withNewDelete true to check, if user is also allowed to create and delete directors.
    */
   function allow_edit_directors( $uid, $withNewDelete=false )
   {
      if( $uid <= GUESTS_ID_MAX ) // forbidden for guests
         return false;

      if( $withNewDelete )
      {// only admin or owner can create/delete TDs
         return Tournament::isAdmin() || ( $this->Owner_ID == $uid );
      }
      else
      {// same checks to edit-only of TDs as for Ts
         return $this->allow_edit_tournaments( $uid );
      }
   }

   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of Tournament-object. */
   function build_query_sql()
   {
      // Tournament: ID,Scope,Type,Title,Description,Owner_ID,Status,Created,Lastchanged,StartTime,EndTime
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'T.*',
         'UNIX_TIMESTAMP(T.Created) AS X_Created',
         'UNIX_TIMESTAMP(T.Lastchanged) AS X_Lastchanged',
         'UNIX_TIMESTAMP(T.StartTime) AS X_StartTime',
         'UNIX_TIMESTAMP(T.EndTime) AS X_EndTime',
         'Owner.Handle AS X_OwnerHandle' );
      $qsql->add_part( SQLP_FROM,
         'Tournament AS T',
         'INNER JOIN Players AS Owner ON Owner.ID=T.Owner_ID' );
      return $qsql;
   }

   /*! \brief Returns Tournament-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $tournament = new Tournament(
            @$row['ID'],
            @$row['Scope'],
            @$row['Type'],
            @$row['Title'],
            @$row['Description'],
            @$row['Owner_ID'],
            @$row['X_OwnerHandle'],
            @$row['Status'],
            @$row['X_Created'],
            @$row['X_Lastchanged'],
            @$row['X_StartTime'],
            @$row['X_EndTime']
         );
      return $tournament;
   }

   /*! \brief Loads and returns Tournament-object for given tournament-ID; NULL if nothing found. */
   function load_tournament( $tid )
   {
      $result = NULL;
      if( $tid > 0 )
      {
         $qsql = Tournament::build_query_sql();
         $qsql->add_part( SQLP_WHERE, "T.ID='$tid'" );
         $qsql->add_part( SQLP_LIMIT, '1' );

         $row = mysql_single_fetch( "Tournament.load_tournament($tid)", $qsql->get_select() );
         if( $row )
            $result = Tournament::new_from_row( $row );
      }
      return $result;
   }

   /*! \brief Returns enhanced (passed) ListIterator with Tournament-objects. */
   function load_tournaments( $iterator )
   {
      $qsql = Tournament::build_query_sql();
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "Tournament.load_tournaments", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $tourney = Tournament::new_from_row( $row );
         $iterator->addItem( $tourney );
      }
      mysql_free_result($result);

      return $iterator;
   }

   /*! \brief Returns scope-text or all scope-texts (if arg=null). */
   function getScopeText( $scope=null )
   {
      // lazy-init of texts
      if( !isset($ARR_GLOBALS_TOURNAMENT['SCOPE']) )
      {
         $arr = array();
         $arr[TOURNEY_SCOPE_DRAGON]  = T_('Dragon#T_scope');
         $arr[TOURNEY_SCOPE_PUBLIC]  = T_('Public#T_scope');
         $arr[TOURNEY_SCOPE_PRIVATE] = T_('Private#T_scope');
         $ARR_GLOBALS_TOURNAMENT['SCOPE'] = $arr;
      }

      if( is_null($scope) )
         return $ARR_GLOBALS_TOURNAMENT['SCOPE'];
      if( !isset($ARR_GLOBALS_TOURNAMENT['SCOPE'][$scope]) )
         error('invalid_args', "Tournament.getScopeText($scope)");
      return $ARR_GLOBALS_TOURNAMENT['SCOPE'][$scope];
   }

   /*! \brief Returns type-text or all type-texts (if arg=null). */
   function getTypeText( $type=null )
   {
      // lazy-init of texts
      if( !isset($ARR_GLOBALS_TOURNAMENT['TYPE']) )
      {
         $arr = array();
         $arr[TOURNEY_TYPE_ROUND_ROBIN] = T_('Round Robin#T_type');
         $ARR_GLOBALS_TOURNAMENT['TYPE'] = $arr;
      }

      if( is_null($type) )
         return $ARR_GLOBALS_TOURNAMENT['TYPE'];
      if( !isset($ARR_GLOBALS_TOURNAMENT['TYPE'][$type]) )
         error('invalid_args', "Tournament.getTypeText($type)");
      return $ARR_GLOBALS_TOURNAMENT['TYPE'][$type];
   }

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   function getStatusText( $status=null )
   {
      // lazy-init of texts
      if( !isset($ARR_GLOBALS_TOURNAMENT['STATUS']) )
      {
         global $player_row;
         $arr = array();
         if( Tournament::isAdmin() )
            $arr[TOURNEY_STATUS_ADMIN] = T_('Admin#T_status');
         $arr[TOURNEY_STATUS_NEW]      = T_('New#T_status');
         $arr[TOURNEY_STATUS_REGISTER] = T_('Register#T_status');
         $arr[TOURNEY_STATUS_PAIR]     = T_('Pair#T_status');
         $arr[TOURNEY_STATUS_PLAY]     = T_('Play#T_status');
         $arr[TOURNEY_STATUS_CLOSED]   = T_('Closed#T_status');
         $ARR_GLOBALS_TOURNAMENT['STATUS'] = $arr;
      }

      if( is_null($status) )
         return $ARR_GLOBALS_TOURNAMENT['STATUS'];
      if( !isset($ARR_GLOBALS_TOURNAMENT['STATUS'][$status]) )
         error('invalid_args', "Tournament.getStatusText($status)");
      return $ARR_GLOBALS_TOURNAMENT['STATUS'][$status];
   }

   /*! \brief Returns true for tournament-admin. */
   function isAdmin()
   {
      global $player_row;
      //TODO: add own ADMIN_TOURNAMENT (tournament-admin)
      return ( @$player_row['admin_level'] & ADMIN_DEVELOPER );
   }

   /*! \brief Returns true if given user can create a new tournament. */
   function allow_create( $uid )
   {
      return ( $uid > GUESTS_ID_MAX ); // anyone can create Ts except guests
   }

   /*!
    * \brief Parses given date-string (expect format TOURNEY_DATEFMT)
    *        into UNIX-timestamp; or return error-string.
    *        Returns 0 if no date-string given.
    */
   function parseDate( $msg, $date_str )
   {
      $result = 0;
      $date_str = trim($date_str);
      if( $date_str != '' )
      {
         if( preg_match( "/^(\d{4})-?(\d+)-?(\d+)(?:\s+(\d+)(?::(\d+)))$/", $date_str, $matches ) )
         {// (Y)=1, (M)=2, (D)=3, (h)=4, (m)=5
            list(, $year, $month, $day, $hour, $min ) = $matches;
            $result = mktime( 0+$hour, 0+$min, 0, 0+$month, 0+$day, 0+$year );
         }
         else
            $result = sprintf( T_('Dateformat of [%s] is wrong, expected [%s] for [%s]'),
               $date_str, TOURNEY_DATEFMT, $msg );
      }
      return $result;
   }

} // end of 'Tournament'

?>
