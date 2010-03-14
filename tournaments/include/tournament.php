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

require_once 'include/db_classes.php';
require_once 'include/std_functions.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_utils.php';
require_once 'tournaments/include/tournament_director.php';
require_once 'tournaments/include/tournament_cache.php';

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
      FTYPE_CHBY,
      FTYPE_INT,  'ID', 'Owner_ID', 'WizardType', 'Flags', 'Rounds', 'CurrentRound',
      FTYPE_TEXT, 'Title', 'Description', 'LockNote',
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
   var $Flags;
   var $Created;
   var $Lastchanged;
   var $ChangedBy;
   var $StartTime;
   var $EndTime;
   var $Rounds;
   var $CurrentRound;
   var $LockNote;

   // non-DB vars

   var $TP_Counts;

   /*! \brief Constructs ConfigBoard-object with specified arguments. */
   function Tournament( $id=0, $scope=TOURNEY_SCOPE_PUBLIC, $type=TOURNEY_TYPE_LADDER,
                        $wizard_type=TOURNEY_WIZTYPE_PUBLIC_LADDER, $title='', $description='',
                        $owner_id=0, $owner_handle='', $status=TOURNEY_STATUS_NEW, $flags=0,
                        $created=0, $lastchanged=0, $changed_by='', $starttime=0, $endtime=0,
                        $rounds=1, $current_round=1, $lock_note='' )
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
      $this->Flags = (int)$flags;
      $this->Created = (int)$created;
      $this->Lastchanged = (int)$lastchanged;
      $this->ChangedBy = $changed_by;
      $this->StartTime = (int)$starttime;
      $this->EndTime = (int)$endtime;
      $this->Rounds = (int)$rounds;
      $this->CurrentRound = (int)$current_round;
      $this->LockNote = $lock_note;
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

   function isFlagSet( $flag )
   {
      return ($this->Flags & $flag);
   }

   function formatFlags( $zero_val='', $intersect_flags=0, $short=false, $class=null )
   {
      if( is_null($class) )
         $class = 'TLockWarn';

      $check_flags = $this->Flags;
      if( $intersect_flags > 0 )
         $check_flags &= $intersect_flags;

      $arr = array();
      $arr_flags = Tournament::getFlagsText(null, ($short ? 'SHORT' : true));
      foreach( $arr_flags as $flag => $flagtext )
      {
         if( $check_flags & $flag )
            $arr[] = ( $class || ($flag & (TOURNEY_FLAG_LOCK_ADMIN|TOURNEY_FLAG_LOCK_TDWORK)) ) // emphasize
               ? span($class, $flagtext)
               : $flagtext;
      }
      return (count($arr)) ? implode(', ', $arr) : $zero_val;
   }

   function buildMaintenanceLockText( $intersect_flags=0, $suffix='.' )
   {
      $check_flags = ($intersect_flags > 0)
         ? $intersect_flags
         : TOURNEY_FLAG_LOCK_ADMIN | TOURNEY_FLAG_LOCK_TDWORK;
      $str = sprintf( Tournament::getFlagsText(TOURNEY_FLAG_LOCK_ADMIN, 'LOCK') . $suffix,
                      $this->formatFlags('', $check_flags) );
      return span('TLockWarn', $str);
   }

   function buildAdminLockText()
   {
      return $this->buildMaintenanceLockText( TOURNEY_FLAG_LOCK_ADMIN, ': ' . T_('Edit prohibited.') );
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

   function fillEntityData( $withCreated=false )
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
      $data->set_value( 'Flags', $this->Flags );
      if( $withCreated )
         $data->set_value( 'Created', $this->Created );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'ChangedBy', $this->ChangedBy );
      $data->set_value( 'StartTime', $this->StartTime );
      if( $this->EndTime > 0 )
         $data->set_value( 'EndTime', $this->EndTime );
      $data->set_value( 'Rounds', $this->Rounds );
      $data->set_value( 'CurrentRound', $this->CurrentRound );
      $data->set_value( 'LockNote', $this->LockNote );
      return $data;
   }

   /*!
    * \brief Updates given tournament-flag.
    * \param $set_flag true = set flag, false = clear flag
    */
   function update_flags( $flag, $set_flag )
   {
      if( $flag > 0 )
      {
         if( $set_flag )
            $this->Flags |= $flag;
         else
            $this->Flags &= ~$flag;

         $data = $GLOBALS['ENTITY_TOURNAMENT']->newEntityData();
         $data->set_value( 'ID', $this->ID );
         $data->set_value( 'Flags', $this->Flags );
         $query = $data->build_sql_update( 1, false, false );
         return db_query( "Tournament.update_flags({$this->ID},$flag,$set_flag)", $query );
      }
      else
         return false;
   }

   function getRoleText( $uid )
   {
      global $TOURNAMENT_CACHE;

      $arr = array();
      if( $this->Owner_ID == $uid )
         $arr[] = T_('Owner#T_role');
      $td = $TOURNAMENT_CACHE->is_tournament_director('Tournament.getRoleText', $this->ID, $uid, 0xffff);
      if( !is_null($td) )
         $arr[] = sprintf( T_('Tournament Director [%s]#T_role'), $td->formatFlags() );
      if( TournamentUtils::isAdmin() )
         $arr[] = T_('Tournament Admin#T_role');
      return (count($arr)) ? implode(', ', $arr) : NO_VALUE;
   }

   /*!
    * \brief Returns true if given user can edit tournament,
    *        or if can admin tournament-game if tournament-director-flag given.
    */
   function allow_edit_tournaments( $uid, $td_flag=0 )
   {
      if( $uid <= GUESTS_ID_MAX ) // forbidden for guests
         return false;

      // logged-in admin is allowed anything
      if( TournamentUtils::isAdmin() )
         return true;

      // edit/admin-game allowed for T-owner or TD
      if( $this->Owner_ID == $uid )
         return true;

      // admin-game allowed for TD with respective right (td_flag)
      global $TOURNAMENT_CACHE;
      if( $TOURNAMENT_CACHE->is_tournament_director('Tournament.allow_edit_tournaments', $this->ID, $uid, $td_flag) )
         return true;

      return false;
   }

   /*! \brief Returns true if given user can edit tournament directors. */
   function allow_edit_directors( $uid )
   {
      if( $uid <= GUESTS_ID_MAX ) // forbidden for guests
         return false;

      // only admin or owner can create/delete TDs, or edit all TDs
      return TournamentUtils::isAdmin() || ( $this->Owner_ID == $uid );
   }

   /*! \brief Returns info about tournament with linked ID, scope, type and title; version=1..3. */
   function build_info( $version=1 )
   {
      if( $version == 1 ) // ID-link (scope type) [title]
         return anchor( "view_tournament.php?tid=".$this->ID, $this->ID )
            . SMALL_SPACING
            . sprintf( '(%s %s)',
                       Tournament::getScopeText($this->Scope),
                       Tournament::getTypeText($this->Type) )
            . SMALL_SPACING . '[' . make_html_safe( $this->Title, true ) . ']';

      if( $version == 2 ) // (scope type) Tournament #ID - title
         return sprintf( '(%s %s) %s #%s - %s',
                         Tournament::getScopeText($this->Scope),
                         Tournament::getTypeText($this->Type),
                         T_('Tournament'),
                         $this->ID,
                         make_html_safe( $this->Title, true) );

      //if( $version == 3 )
      return sprintf( '(%s %s) %s #%s - %s: %s', // (scope type) Tournament #ID - status
                      Tournament::getScopeText($this->Scope),
                      Tournament::getTypeText($this->Type),
                      T_('Tournament'),
                      $this->ID,
                      T_('Status#tourney'),
                      Tournament::getStatusText($this->Status) );
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

   /*!
    * \brief Checks tournament-locks and returns non-null
    *        list of errors and warnings, that disallow registration.
    * \param $check_type TCHKTYPE_TD|USER_NEW|USER_EDIT
    */
   function checkRegistrationLocks( $check_type, $with_admin_check=true )
   {
      $regerr = T_('Registration/Edit prohibited at the moment');
      $errors = array();
      if( $check_type != TCHKTYPE_TD )
         $warnings =& $errors;
      else
         $warnings = array();

      // check admin-lock
      if( $with_admin_check && $this->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      {
         $errmsg = $this->buildAdminLockText();
         if( ($check_type == TCHKTYPE_TD) && TournamentUtils::isAdmin() )
            $warnings[] = $errmsg;
         else
            $errors[] = $errmsg;
      }

      // check other locks
      $chk_flags = TOURNEY_FLAG_LOCK_REGISTER | TOURNEY_FLAG_LOCK_TDWORK;
      if( $this->isFlagSet($chk_flags) )
      {
         $errmsg = span('TLockWarn', sprintf( '%s (%s).', $regerr, $this->formatFlags('', $chk_flags) ));
         if( $check_type == TCHKTYPE_TD )
            $warnings[] = $errmsg;
         else
            $errors[] = $errmsg;
      }
      if( $this->isFlagSet(TOURNEY_FLAG_LOCK_CLOSE) )
         $errors[] = Tournament::getLockText(TOURNEY_FLAG_LOCK_CLOSE);

      return ($check_type == TCHKTYPE_USER_NEW)
         ? array( $errors, array() )
         : array( $errors, $warnings );
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
            @$row['Flags'],
            @$row['X_Created'],
            @$row['X_Lastchanged'],
            @$row['ChangedBy'],
            @$row['X_StartTime'],
            @$row['X_EndTime'],
            @$row['Rounds'],
            @$row['CurrentRound'],
            @$row['LockNote']
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
         $arr[TOURNEY_WIZTYPE_PUBLIC_LADDER] = T_('Public Ladder');
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

   /*! \brief Returns Flags-text or all Flags-texts (if arg=null). */
   function getFlagsText( $flag=null, $short=true )
   {
      global $ARR_GLOBALS_TOURNAMENT;

      // lazy-init of texts
      $key = 'FLAGS';
      if( !isset($ARR_GLOBALS_TOURNAMENT[$key]) )
      {
         $arr = array();
         $arr[TOURNEY_FLAG_LOCK_ADMIN]    = T_('Admin-Lock#T_flag');
         $arr[TOURNEY_FLAG_LOCK_REGISTER] = T_('Register-Lock#T_flag');
         $arr[TOURNEY_FLAG_LOCK_TDWORK]   = T_('Director-Work-Lock#T_flag');
         $arr[TOURNEY_FLAG_LOCK_CRON]     = T_('Cron-Lock#T_flag');
         $arr[TOURNEY_FLAG_LOCK_CLOSE]    = T_('Close-Lock#T_flag');
         $ARR_GLOBALS_TOURNAMENT[$key] = $arr;

         $arr = array();
         $arr[TOURNEY_FLAG_LOCK_ADMIN]    = T_('Prohibits all writing operations on tournament for non-admins.');
         $arr[TOURNEY_FLAG_LOCK_REGISTER] = T_('Prohibits users to join tournament (by application, registration, ACK-invite).');
         $arr[TOURNEY_FLAG_LOCK_TDWORK]   = T_('Provides exclusive write-access to tournament directors on certain tournament-data.');
         $arr[TOURNEY_FLAG_LOCK_CRON]     = T_('Provides exclusive write-access to tounament-cron on certain tournament-data.');
         $arr[TOURNEY_FLAG_LOCK_CLOSE]    = T_('Prohibits users to join tournament or start challenges (preparation to finish tournament).');
         $ARR_GLOBALS_TOURNAMENT[$key.'_LONG'] = $arr;

         $arr = array();
         $arr[TOURNEY_FLAG_LOCK_ADMIN]    = T_('Tournament is in Maintenance-mode (%s)#T_lock');
         $arr[TOURNEY_FLAG_LOCK_REGISTER] = T_('%s is set prohibiting users to register or join tournament.#T_lock');
         $arr[TOURNEY_FLAG_LOCK_TDWORK]   = T_('Edit of ladder needs %s.#T_lock');
         $arr[TOURNEY_FLAG_LOCK_CRON]     = T_('Edit of ladder is prohibited by set %s. Please wait a moment.#T_lock');
         $arr[TOURNEY_FLAG_LOCK_CLOSE]    = T_('%s is set prohibiting users to join tournament or start challenges.#T_lock');
         $ARR_GLOBALS_TOURNAMENT[$key.'_LOCK'] = $arr;

         $arr = array();
         $arr[TOURNEY_FLAG_LOCK_ADMIN]    = 'ADMIN';
         $arr[TOURNEY_FLAG_LOCK_REGISTER] = 'REG';
         $arr[TOURNEY_FLAG_LOCK_TDWORK]   = 'TDWORK';
         $arr[TOURNEY_FLAG_LOCK_CRON]     = 'CRON';
         $arr[TOURNEY_FLAG_LOCK_CLOSE]    = 'CLOSE';
         $ARR_GLOBALS_TOURNAMENT[$key.'_SHORT'] = $arr;
      }

      if( $short === false )
         $key .= '_LONG';
      elseif( $short !== true )
         $key .= '_' . $short;
      if( is_null($flag) )
         return $ARR_GLOBALS_TOURNAMENT[$key];
      if( !isset($ARR_GLOBALS_TOURNAMENT[$key][$flag]) )
         error('invalid_args', "Tournament::getFlagsText($flag,$short)");
      return $ARR_GLOBALS_TOURNAMENT[$key][$flag];
   }//getFlagsText

   function getLockText( $flag )
   {
      return span('TLockWarn', sprintf( Tournament::getFlagsText($flag, 'LOCK'), Tournament::getFlagsText($flag)) );
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

   function get_edit_lock_tournament_status()
   {
      static $statuslist = array( TOURNEY_STATUS_REGISTER, TOURNEY_STATUS_PLAY );
      return $statuslist;
   }

} // end of 'Tournament'

?>
