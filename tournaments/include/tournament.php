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

require_once 'tournaments/include/tournament_globals.php';
require_once( 'include/db_classes.php' );
require_once( 'include/std_functions.php' ); // for ADMIN_TOURNAMENT
require_once( 'tournaments/include/tournament_utils.php' );
require_once( 'tournaments/include/tournament_director.php' );

 /*!
  * \file tournament.php
  *
  * \brief Functions for handling tournaments: tables Tournament
  */


 /*!
  * \class Tournament
  *
  * \brief Class to manage Tournament-table
  */

// lazy-init in Tournament::get..Text()-funcs
global $ARR_GLOBALS_TOURNAMENT; //PHP5
$ARR_GLOBALS_TOURNAMENT = array();

global $ENTITY_TOURNAMENT; //PHP5
$ENTITY_TOURNAMENT = new Entity( 'Tournament',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_INT,  'ID', 'Owner_ID', 'WizardType', 'Rounds', 'CurrentRound',
      FTYPE_TEXT, 'Title', 'Description',
      FTYPE_DATE, 'Created', 'Lastchanged', 'StartTime', 'EndTime',
      FTYPE_ENUM, 'Scope', 'Type', 'Status'
   );

class Tournament
{
   var $ID;
   var $Scope;
   var $Type;
   var $WizardType;
   var $Title;
   var $Description;
   var $Owner_ID;
   var $Owner_Handle; // non-table
   var $Status;
   var $Created;
   var $Lastchanged;
   var $StartTime;
   var $EndTime;
   var $Rounds;
   var $CurrentRound;

   // non-DB vars

   var $TP_Counts;

   /*! \brief Constructs ConfigBoard-object with specified arguments. */
   function Tournament( $id=0, $scope=TOURNEY_SCOPE_PUBLIC, $type=TOURNEY_TYPE_LADDER,
                        $wizard_type=TOURNEY_WIZTYPE_DGS_LADDER, $title='', $description='',
                        $owner_id=0, $owner_handle='', $status=TOURNEY_STATUS_NEW,
                        $created=0, $lastchanged=0, $starttime=0, $endtime=0,
                        $rounds=1, $current_round=1 )
   {
      $this->ID = (int)$id;
      $this->setScope( $scope );
      $this->setType( $type );
      $this->setWizardType( $wizard_type );
      $this->Title = $title;
      $this->Description = $description;
      $this->Owner_ID = (int)$owner_id;
      $this->Owner_Handle = $owner_handle;
      $this->setStatus( $status );
      $this->Created = (int)$created;
      $this->Lastchanged = (int)$lastchanged;
      $this->StartTime = (int)$starttime;
      $this->EndTime = (int)$endtime;
      $this->Rounds = (int)$rounds;
      $this->CurrentRound = (int)$current_round;
      // non-DB
      $this->TP_Counts = NULL;
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

   function setWizardType( $wizard_type )
   {
      if( !is_numeric($wizard_type) || $wizard_type < 1 || $wizard_type > MAX_TOURNEY_WIZARD_TYPE )
         error('invalid_args', "Tournament.setWizardType($wizard_type)");
      $this->WizardType = $wizard_type;
      $this->setType( TournamentUtils::getWizardTournamentType($wizard_type) );
   }

   function setStatus( $status, $check_only=false )
   {
      if( !preg_match( "/^(".CHECK_TOURNEY_STATUS.")$/", $status ) )
         error('invalid_args', "Tournament.setStatus($status)");
      if( !$check_only )
         $this->Status = $status;
   }

   // current-round / rounds|*
   function formatRound( $short=false )
   {
      $rounds_str = ($this->Rounds > 0) ? $this->Rounds : '*';
      if( $short )
         return $this->CurrentRound . ' / ' . $rounds_str;
      else
         return sprintf( T_('%s of %s rounds'), $this->CurrentRound, $rounds_str );
   }

   function getRoundLimitText()
   {
      if( $this->Rounds > 0 )
         return sprintf( T_('(max. %s rounds)'), $this->Rounds );
      else
         return T_('(unlimited rounds)#Trounds');
   }

   function setTP_Counts( $arr )
   {
      $this->TP_Counts = array_merge( array(), $arr );
   }

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
      $this->Created = $this->Lastchanged = $this->StartTime = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData(true);
      $result = $entityData->insert( "Tournament::insert(%s)" );
      if( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData(false /*-Created*/);
      return $entityData->update( "Tournament::update(%s)" );
   }

   function delete()
   {
      $entityData = $this->fillEntityData(false);
      return $entityData->delete( "Tournament::delete(%s)" );
   }

   function fillEntityData( $withCreated )
   {
      // checked fields: Scope/Type/Status
      $data = $GLOBALS['ENTITY_TOURNAMENT']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'Scope', $this->Scope );
      $data->set_value( 'Type', $this->Type );
      $data->set_value( 'WizardType', $this->WizardType );
      $data->set_value( 'Title', $this->Title );
      $data->set_value( 'Description', $this->Description );
      $data->set_value( 'Owner_ID', $this->Owner_ID );
      $data->set_value( 'Status', $this->Status );
      if( $withCreated )
         $data->set_value( 'Created', $this->Created );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'StartTime', $this->StartTime );
      if( $this->EndTime > 0 )
         $data->set_value( 'EndTime', $this->EndTime );
      $data->set_value( 'Rounds', $this->Rounds );
      $data->set_value( 'CurrentRound', $this->CurrentRound );
      return $data;
   }

   /*! \brief Returns true if given user can edit tournament. */
   function allow_edit_tournaments( $uid )
   {
      if( $uid <= GUESTS_ID_MAX ) // forbidden for guests
         return false;

      // logged-in admin is allowed anything
      if( TournamentUtils::isAdmin() )
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
         return TournamentUtils::isAdmin() || ( $this->Owner_ID == $uid );
      }
      else
      {// same checks to edit-only of TDs as for Ts
         return $this->allow_edit_tournaments( $uid );
      }
   }

   /*!
    * \brief Returns empty error-array if given user can register to tournament;
    * \note Tournament-properties are separately checked.
    */
   function allow_register( $uid, $ignore_status=false )
   {
      global $player_row;
      $errors = array();
      if( $uid <= GUESTS_ID_MAX ) // forbidden for guests
         return $errors + array( T_('Guest-users are not allowed to register in tournaments.') );

      return $errors;
   }

   /*! \brief Returns info about tournament with linked ID, scope, type and title. */
   function build_info()
   {
      return anchor( "view_tournament.php?tid=".$this->ID, $this->ID )
         . sprintf( '%s(%s %s)',
                    SMALL_SPACING,
                    Tournament::getScopeText($this->Scope),
                    Tournament::getTypeText($this->Type) )
         . SMALL_SPACING . '[' . make_html_safe( $this->Title, true ) . ']';
   }

   function build_role_info()
   {
      if( TournamentUtils::isAdmin() )
         return T_('You are a tournament admin.');

      global $player_row;
      if( $player_row['ID'] == $this->Owner_ID )
         return T_('You are the owner of this tournament.');

      // "normal" user
      return T_('You are a director of this tournament.');
   }

   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of Tournament-object. */
   function build_query_sql()
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT']->newQuerySQL('T');
      $qsql->add_part( SQLP_FIELDS,
         'Owner.Handle AS X_OwnerHandle' );
      $qsql->add_part( SQLP_FROM,
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
            @$row['WizardType'],
            @$row['Title'],
            @$row['Description'],
            @$row['Owner_ID'],
            @$row['X_OwnerHandle'],
            @$row['Status'],
            @$row['X_Created'],
            @$row['X_Lastchanged'],
            @$row['X_StartTime'],
            @$row['X_EndTime'],
            @$row['Rounds'],
            @$row['CurrentRound']
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
         $iterator->addItem( $tourney, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }


   /*! \brief Returns scope-text or all scope-texts (if arg=null). */
   function getScopeText( $scope=null )
   {
      global $ARR_GLOBALS_TOURNAMENT;

      // lazy-init of texts
      $key = 'SCOPE';
      if( !isset($ARR_GLOBALS_TOURNAMENT[$key]) )
      {
         $arr = array();
         $arr[TOURNEY_SCOPE_DRAGON]  = T_('Dragon#T_scope');
         $arr[TOURNEY_SCOPE_PUBLIC]  = T_('Public#T_scope');
         //TODO $arr[TOURNEY_SCOPE_PRIVATE] = T_('Private#T_scope');
         $ARR_GLOBALS_TOURNAMENT[$key] = $arr;
      }

      if( is_null($scope) )
         return $ARR_GLOBALS_TOURNAMENT[$key];
      if( !isset($ARR_GLOBALS_TOURNAMENT[$key][$scope]) )
         error('invalid_args', "Tournament.getScopeText($scope)");
      return $ARR_GLOBALS_TOURNAMENT[$key][$scope];
   }

   /*! \brief Returns type-text or all type-texts (if arg=null). */
   function getTypeText( $type=null )
   {
      global $ARR_GLOBALS_TOURNAMENT;

      // lazy-init of texts
      $key = 'TYPE';
      if( !isset($ARR_GLOBALS_TOURNAMENT[$key]) )
      {
         $arr = array();
         $arr[TOURNEY_TYPE_LADDER] = T_('Ladder#T_type');
         $arr[TOURNEY_TYPE_ROUND_ROBIN] = T_('Round Robin#T_type');
         $ARR_GLOBALS_TOURNAMENT[$key] = $arr;
      }

      if( is_null($type) )
         return $ARR_GLOBALS_TOURNAMENT[$key];
      if( !isset($ARR_GLOBALS_TOURNAMENT[$key][$type]) )
         error('invalid_args', "Tournament.getTypeText($type)");
      return $ARR_GLOBALS_TOURNAMENT[$key][$type];
   }

   /*! \brief Returns wizard-type-text or all type-texts (if arg=null). */
   function getWizardTypeText( $wiztype=null )
   {
      global $ARR_GLOBALS_TOURNAMENT;

      // lazy-init of texts
      $key = 'WIZARDTYPE';
      if( !isset($ARR_GLOBALS_TOURNAMENT[$key]) )
      {
         $arr = array();
         $arr[TOURNEY_WIZTYPE_DGS_LADDER] = T_('DGS Ladder');
         $ARR_GLOBALS_TOURNAMENT[$key] = $arr;
      }

      if( is_null($wiztype) )
         return $ARR_GLOBALS_TOURNAMENT[$key];
      if( !isset($ARR_GLOBALS_TOURNAMENT[$key][$wiztype]) )
         error('invalid_args', "Tournament.getWizardTypeText($wiztype)");
      return $ARR_GLOBALS_TOURNAMENT[$key][$wiztype];
   }

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   function getStatusText( $status=null )
   {
      global $ARR_GLOBALS_TOURNAMENT;

      // lazy-init of texts
      $key = 'STATUS';
      if( !isset($ARR_GLOBALS_TOURNAMENT[$key]) )
      {
         $arr = array();
         $arr[TOURNEY_STATUS_ADMIN]    = T_('Admin#T_status');
         $arr[TOURNEY_STATUS_NEW]      = T_('New#T_status');
         $arr[TOURNEY_STATUS_REGISTER] = T_('Register#T_status');
         $arr[TOURNEY_STATUS_PAIR]     = T_('Pair#T_status');
         $arr[TOURNEY_STATUS_PLAY]     = T_('Play#T_status');
         $arr[TOURNEY_STATUS_CLOSED]   = T_('Finished#T_status');
         $arr[TOURNEY_STATUS_DELETE]   = T_('Delete#T_status');
         $ARR_GLOBALS_TOURNAMENT[$key] = $arr;
      }

      if( is_null($status) )
      {
         return array() + $ARR_GLOBALS_TOURNAMENT[$key]; // cloned
         //$arrout = array() + $ARR_GLOBALS_TOURNAMENT[$key];
         //if( !TournamentUtils::isAdmin() )
            //unset($arrout[TOURNEY_STATUS_ADMIN]);
         //return $arrout;
      }
      if( !isset($ARR_GLOBALS_TOURNAMENT[$key][$status]) )
         error('invalid_args', "Tournament.getStatusText($status)");
      return $ARR_GLOBALS_TOURNAMENT[$key][$status];
   }

   /*! \brief Returns true if given user can create a new tournament. */
   function allow_create( $uid )
   {
      if( $uid <= GUESTS_ID_MAX )
         return false;

      // anyone can create Ts except guests
      return true;
   }

   function get_edit_tournament_status()
   {
      static $statuslist = array( TOURNEY_STATUS_NEW );
      return $statuslist;
   }

} // end of 'Tournament'

?>
