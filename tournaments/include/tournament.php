<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/dgs_cache.php';
require_once 'include/std_functions.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_utils.php';

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

global $ENTITY_TOURNAMENT; //PHP5
$ENTITY_TOURNAMENT = new Entity( 'Tournament',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_CHBY,
      FTYPE_INT,  'ID', 'Owner_ID', 'WizardType', 'Flags', 'Rounds', 'CurrentRound', 'RegisteredTP',
      FTYPE_TEXT, 'Title', 'Description', 'LockNote',
      FTYPE_DATE, 'Created', 'Lastchanged', 'StartTime', 'EndTime',
      FTYPE_ENUM, 'Scope', 'Type', 'Status'
   );

class Tournament
{
   private static $ARR_TOURNEY_TEXTS = array(); // lazy-init in Tournament::get..Text()-funcs: [key][id] => text

   public $ID;
   public $Scope;
   public $Type;
   public $WizardType;
   public $Title;
   public $Description;
   public $Owner_ID;
   public $Status;
   public $Flags;
   public $Created;
   public $Lastchanged;
   public $ChangedBy;
   public $StartTime;
   public $EndTime;
   public $Rounds;
   public $CurrentRound;
   public $RegisteredTP;
   public $LockNote;

   // non-DB vars

   public $Owner_Handle = '';

   /*! \brief Constructs ConfigBoard-object with specified arguments. */
   public function __construct( $id=0, $scope=TOURNEY_SCOPE_PUBLIC, $type=TOURNEY_TYPE_LADDER,
         $wizard_type=TOURNEY_WIZTYPE_PUBLIC_LADDER, $title='', $description='',
         $owner_id=0, $status=TOURNEY_STATUS_NEW, $flags=0,
         $created=0, $lastchanged=0, $changed_by='', $starttime=0, $endtime=0,
         $rounds=1, $current_round=1, $registeredTP=0, $lock_note='' )
   {
      $this->ID = (int)$id;
      $this->setScope( $scope );
      $this->setType( $type );
      $this->setWizardType( $wizard_type );
      $this->Title = $title;
      $this->Description = $description;
      $this->Owner_ID = (int)$owner_id;
      $this->setStatus( $status );
      $this->Flags = (int)$flags;
      $this->Created = (int)$created;
      $this->Lastchanged = (int)$lastchanged;
      $this->ChangedBy = $changed_by;
      $this->StartTime = (int)$starttime;
      $this->EndTime = (int)$endtime;
      $this->Rounds = (int)$rounds;
      $this->CurrentRound = (int)$current_round;
      $this->RegisteredTP = (int)$registeredTP;
      $this->LockNote = $lock_note;
   }//__construct

   public function to_string()
   {
      return print_r( $this, true );
   }

   public function setScope( $scope )
   {
      if ( !preg_match( "/^(".CHECK_TOURNEY_SCOPE.")$/", $scope ) )
         error('invalid_args', "Tournament.setScope($scope)");
      $this->Scope = $scope;
   }

   public function setType( $type )
   {
      if ( !preg_match( "/^(".CHECK_TOURNEY_TYPE.")$/", $type ) )
         error('invalid_args', "Tournament.setType($type)");
      $this->Type = $type;
   }

   public function setWizardType( $wizard_type )
   {
      if ( !is_numeric($wizard_type) || $wizard_type < 1 || $wizard_type > MAX_TOURNEY_WIZARD_TYPE )
         error('invalid_args', "Tournament.setWizardType($wizard_type)");
      $this->WizardType = $wizard_type;
      $this->setType( TournamentUtils::getWizardTournamentType($wizard_type) );
   }

   public function setStatus( $status, $check_only=false )
   {
      if ( !preg_match( "/^(".CHECK_TOURNEY_STATUS.")$/", $status ) )
         error('invalid_args', "Tournament.setStatus($status)");
      if ( !$check_only )
         $this->Status = $status;
   }

   public function isFlagSet( $flag )
   {
      return ($this->Flags & $flag);
   }

   public function formatFlags( $zero_val='', $intersect_flags=0, $short=false, $class=null, $html=true, $flags_val=null )
   {
      if ( is_null($class) )
         $class = 'TLockWarn';

      $check_flags = ( is_null($flags_val) ) ? $this->Flags : $flags_val;
      if ( $intersect_flags > 0 )
         $check_flags &= $intersect_flags;

      $arr = array();
      $arr_flags = self::getFlagsText(null, ($short ? 'SHORT' : true));
      foreach ( $arr_flags as $flag => $flagtext )
      {
         if ( $check_flags & $flag )
            $arr[] = ( $html && ( $class || ($flag & (TOURNEY_FLAG_LOCK_ADMIN|TOURNEY_FLAG_LOCK_TDWORK)) ) ) // emphasize
               ? span($class, $flagtext)
               : $flagtext;
      }
      return (count($arr)) ? implode(', ', $arr) : $zero_val;
   }//formatFlags

   public function buildMaintenanceLockText( $intersect_flags=0, $suffix='.' )
   {
      $check_flags = ($intersect_flags > 0)
         ? $intersect_flags
         : TOURNEY_FLAG_LOCK_ADMIN | TOURNEY_FLAG_LOCK_TDWORK;
      $str = sprintf( self::getFlagsText(TOURNEY_FLAG_LOCK_ADMIN, 'LOCK') . $suffix,
                      $this->formatFlags('', $check_flags) );
      return span('TLockWarn', $str);
   }

   public function buildAdminLockText()
   {
      return $this->buildMaintenanceLockText( TOURNEY_FLAG_LOCK_ADMIN, ': ' . T_('Edit prohibited.') );
   }

   // current-round / rounds|*
   public function formatRound( $short=false )
   {
      $rounds_str = ($this->Rounds > 0) ? $this->Rounds : '*';
      if ( $this->Type == TOURNEY_TYPE_ROUND_ROBIN )
      {
         if ( $short )
            return $this->CurrentRound . ' / ' . $rounds_str;
         else
            return sprintf( T_('%s of %s rounds#tourney'), $this->CurrentRound, $rounds_str );
      }
      else //if ( $this->Type == TOURNEY_TYPE_LADDER )
         return ( $short ) ? 1 : T_('1 round#tourney');
   }//formatRound

   public function getRoundLimitText()
   {
      if ( $this->Rounds > 0 )
         return sprintf( T_('(max. %s rounds)#tourney'), $this->Rounds );
      else
         return T_('(unlimited rounds)#tourney');
   }

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
      $this->Created = $this->Lastchanged = $this->StartTime = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData(true);
      $result = $entityData->insert( "Tournament.insert(%s)" );
      if ( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   public function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData(false /*-Created*/);
      $result = $entityData->update( "Tournament.update(%s)" );
      self::delete_cache_tournament( 'Tournament.update', $this->ID );
      return $result;
   }

   public function delete()
   {
      $entityData = $this->fillEntityData(false);
      $result = $entityData->delete( "Tournament.delete(%s)" );
      self::delete_cache_tournament( 'Tournament.update', $this->ID );
      return $result;
   }

   public function fillEntityData( $withCreated=false )
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
      if ( $withCreated )
         $data->set_value( 'Created', $this->Created );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'ChangedBy', $this->ChangedBy );
      $data->set_value( 'StartTime', $this->StartTime );
      if ( $this->EndTime > 0 )
         $data->set_value( 'EndTime', $this->EndTime );
      $data->set_value( 'Rounds', $this->Rounds );
      $data->set_value( 'CurrentRound', $this->CurrentRound );
      $data->set_value( 'RegisteredTP', $this->RegisteredTP );
      $data->set_value( 'LockNote', $this->LockNote );
      return $data;
   }

   /*!
    * \brief Updates given tournament-flag.
    * \param $set_flag true = set flag, false = clear flag
    */
   public function update_flags( $flag, $set_flag )
   {
      if ( $flag > 0 )
      {
         if ( $set_flag )
            $this->Flags |= $flag;
         else
            $this->Flags &= ~$flag;

         $data = $GLOBALS['ENTITY_TOURNAMENT']->newEntityData();
         $data->set_value( 'ID', $this->ID );
         $data->set_value( 'Flags', $this->Flags );
         $query = $data->build_sql_update( 1, false, false );
         $result = db_query( "Tournament.update_flags({$this->ID},$flag,$set_flag)", $query );
         self::delete_cache_tournament( 'Tournament.update_flags', $this->ID );
         return $result;
      }
      else
         return false;
   }//update_flags

   /*! \brief Updates Tournament.Rounds/CurrentRound. */
   public function update_rounds( $change_rounds, $curr_round=0 )
   {
      if ( !is_numeric($change_rounds) )
         error('invalid_args', "Tournament.update_rounds.check.change_rounds({$this->ID},$change_rounds)");
      if ( !is_numeric($curr_round) )
         error('invalid_args', "Tournament.update_rounds.check.curr_rounds({$this->ID},$curr_round)");

      $data = $GLOBALS['ENTITY_TOURNAMENT']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      if ( $change_rounds )
         $data->set_query_value( 'Rounds', "Rounds+($change_rounds)" );
      if ( $curr_round > 0 )
         $data->set_value( 'CurrentRound', $curr_round );
      $data->set_value( 'Lastchanged', $GLOBALS['NOW'] );
      $data->set_value( 'ChangedBy', $this->ChangedBy );
      $result = $data->update( "Tournament.update_rounds(%s,$change_rounds,$curr_round)" );
      self::delete_cache_tournament( 'Tournament.update_rounds', $this->ID );
      return $result;
   }//update_rounds

   /*! \brief Returns TLOG_TYPE_... if given user can edit tournament directors; false otherwise. */
   public function allow_edit_directors( $uid )
   {
      if ( $uid <= GUESTS_ID_MAX ) // forbidden for guests
         return false;

      // only admin or owner can create/delete TDs, or edit all TDs
      if ( TournamentUtils::isAdmin() )
         return TLOG_TYPE_ADMIN;

      if ( $this->Owner_ID == $uid )
         return TLOG_TYPE_OWNER;

      return false;
   }//allow_edit_directors

   /*! \brief Returns info about tournament with linked ID, scope, type and title; version=1..3. */
   public function build_info( $version=1, $extra='' )
   {
      global $base_path;

      if ( $version == 1 ) // ID-link (scope type) [title]
         return anchor( $base_path."tournaments/view_tournament.php?tid=".$this->ID, $this->ID )
            . SMALL_SPACING
            . sprintf( '(%s %s)',
                       self::getScopeText($this->Scope),
                       self::getTypeText($this->Type) )
            . SMALL_SPACING . '[' . make_html_safe( $this->Title, true ) . ']';

      if ( $version == 2 ) // (scope type) Tournament #ID - title
         return sprintf( '(%s %s) %s #%s - %s',
                         self::getScopeText($this->Scope),
                         self::getTypeText($this->Type),
                         T_('Tournament'),
                         $this->ID,
                         make_html_safe( $this->Title, true) );

      if ( $version == 3 ) // (scope type) title [- extra]
         return sprintf( '(%s %s) %s' . ($extra ? ' - %s' : ''),
                         self::getScopeText($this->Scope),
                         self::getTypeText($this->Type),
                         make_html_safe( $this->Title, true),
                         $extra );

      if ( $version == 4 )
         return sprintf( '(%s %s) %s #%s - %s: %s', // (scope type) Tournament #ID - status
                         self::getScopeText($this->Scope),
                         self::getTypeText($this->Type),
                         T_('Tournament'),
                         $this->ID,
                         T_('Status#tourney'),
                         self::getStatusText($this->Status) );

      //if ( $version == 5 ) // extra=max-title-len
      return sprintf( '%s %s - %s', // linked: (img) Tournament - Title
                      echo_image_tournament_info($this->ID),
                      anchor( $base_path."tournaments/view_tournament.php?tid=".$this->ID, T_('Tournament') ),
                      make_html_safe( (is_numeric($extra) ? cut_str($this->Title, $extra) : $this->Title), true) );
   }//build_info

   public function build_role_info()
   {
      if ( TournamentUtils::isAdmin() )
         return T_('You are a tournament admin.');

      global $player_row;
      if ( $player_row['ID'] == $this->Owner_ID )
         return T_('You are the owner of this tournament.');

      // "normal" user
      return T_('You are a director of this tournament.');
   }//build_role_info

   /*!
    * \brief Checks tournament-locks and returns non-null list of errors and warnings, that do not allow registration.
    * \param $check_type TCHKTYPE_TD|USER_NEW|USER_EDIT
    */
   public function checkRegistrationLocks( $check_type, $with_admin_check=true )
   {
      $regerr = T_('Registration/Edit prohibited at the moment#tourney');
      $errors = array();
      if ( $check_type != TCHKTYPE_TD )
         $warnings =& $errors;
      else
         $warnings = array();

      // check admin-lock
      if ( $with_admin_check && $this->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      {
         $errmsg = $this->buildAdminLockText();
         if ( ($check_type == TCHKTYPE_TD) && TournamentUtils::isAdmin() )
            $warnings[] = $errmsg;
         else
            $errors[] = $errmsg;
      }

      // check other locks
      $chk_flags = TOURNEY_FLAG_LOCK_REGISTER | TOURNEY_FLAG_LOCK_TDWORK;
      if ( $this->isFlagSet($chk_flags) )
      {
         $errmsg = span('TLockWarn', sprintf( '%s (%s).', $regerr, $this->formatFlags('', $chk_flags) ));
         if ( $check_type == TCHKTYPE_TD )
            $warnings[] = $errmsg;
         else
            $errors[] = $errmsg;
      }
      if ( $this->isFlagSet(TOURNEY_FLAG_LOCK_CLOSE) )
         $errors[] = self::getLockText(TOURNEY_FLAG_LOCK_CLOSE);

      return ($check_type == TCHKTYPE_USER_NEW)
         ? array( $errors, array() )
         : array( $errors, $warnings );
   }//checkRegistrationLocks


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of Tournament-object. */
   public static function build_query_sql()
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT']->newQuerySQL('T');
      return $qsql;
   }

   /*! \brief Returns Tournament-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $tournament = new Tournament(
            @$row['ID'],
            @$row['Scope'],
            @$row['Type'],
            @$row['WizardType'],
            @$row['Title'],
            @$row['Description'],
            @$row['Owner_ID'],
            @$row['Status'],
            @$row['Flags'],
            @$row['X_Created'],
            @$row['X_Lastchanged'],
            @$row['ChangedBy'],
            @$row['X_StartTime'],
            @$row['X_EndTime'],
            @$row['Rounds'],
            @$row['CurrentRound'],
            @$row['RegisteredTP'],
            @$row['LockNote']
         );
      return $tournament;
   }

   /*! \brief Loads and returns Tournament-object for given tournament-ID; NULL if nothing found. */
   public static function load_tournament( $tid )
   {
      $result = NULL;
      if ( $tid > 0 )
      {
         $qsql = self::build_query_sql();
         $qsql->add_part( SQLP_WHERE, "T.ID='$tid'" );
         $qsql->add_part( SQLP_LIMIT, '1' );

         $row = mysql_single_fetch( "Tournament.load_tournament($tid)", $qsql->get_select() );
         if ( $row )
            $result = self::new_from_row( $row );
      }
      return $result;
   }//load_tournament

   /*! \brief Returns enhanced (passed) ListIterator with Tournament-objects. */
   public static function load_tournaments( $iterator )
   {
      $qsql = self::build_query_sql();
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "Tournament.load_tournaments", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $tourney = self::new_from_row( $row );
         $iterator->addItem( $tourney, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_tournaments

   /*!
    * \brief Increases/decreases Tournament.RegisteredTP for given tournament.
    *
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
   public static function update_tournament_registeredTP( $tid, $diff )
   {
      if ( !is_numeric($tid) || !is_numeric($diff) )
         error('invalid_args', "Tournament:update_tournament_registeredTP($tid,$diff)");
      if ( $diff )
      {
         db_query( "Tournament:update_tournament_registeredTP($tid,$diff)",
            "UPDATE Tournament SET RegisteredTP=RegisteredTP+($diff) WHERE ID=$tid LIMIT 1" );
         self::delete_cache_tournament( 'Tournament.update_tournament_registeredTP', $tid );
      }
   }

   /*! \brief Returns scope-text or all scope-texts (if arg=null). */
   public static function getScopeText( $scope=null )
   {
      // lazy-init of texts
      $key = 'SCOPE';
      if ( !isset(self::$ARR_TOURNEY_TEXTS[$key]) )
      {
         $arr = array();
         $arr[TOURNEY_SCOPE_DRAGON]  = T_('Dragon#T_scope');
         $arr[TOURNEY_SCOPE_PUBLIC]  = T_('Public#T_scope');
         $arr[TOURNEY_SCOPE_PRIVATE] = T_('Private#T_scope');
         self::$ARR_TOURNEY_TEXTS[$key] = $arr;
      }

      if ( is_null($scope) )
         return self::$ARR_TOURNEY_TEXTS[$key];
      if ( !isset(self::$ARR_TOURNEY_TEXTS[$key][$scope]) )
         error('invalid_args', "Tournament:getScopeText($scope)");
      return self::$ARR_TOURNEY_TEXTS[$key][$scope];
   }//getScopeText

   /*! \brief Returns type-text or all type-texts (if arg=null). */
   public static function getTypeText( $type=null )
   {
      // lazy-init of texts
      $key = 'TYPE';
      if ( !isset(self::$ARR_TOURNEY_TEXTS[$key]) )
      {
         $arr = array();
         $arr[TOURNEY_TYPE_LADDER] = T_('Ladder#T_type');
         $arr[TOURNEY_TYPE_ROUND_ROBIN] = T_('Round Robin#T_type');
         self::$ARR_TOURNEY_TEXTS[$key] = $arr;
      }

      if ( is_null($type) )
         return self::$ARR_TOURNEY_TEXTS[$key];
      if ( !isset(self::$ARR_TOURNEY_TEXTS[$key][$type]) )
         error('invalid_args', "Tournament:getTypeText($type)");
      return self::$ARR_TOURNEY_TEXTS[$key][$type];
   }//getTypeText

   /*! \brief Returns wizard-type-text or all type-texts (if arg=null). */
   public static function getWizardTypeText( $wiztype=null )
   {
      // lazy-init of texts
      $key = 'WIZARDTYPE';
      if ( !isset(self::$ARR_TOURNEY_TEXTS[$key]) )
      {
         $arr = array();
         $arr[TOURNEY_WIZTYPE_DGS_LADDER] = T_('DGS Ladder');
         $arr[TOURNEY_WIZTYPE_PUBLIC_LADDER] = T_('Public Ladder');
         $arr[TOURNEY_WIZTYPE_PRIVATE_LADDER] = T_('Private Ladder');
         $arr[TOURNEY_WIZTYPE_DGS_ROUNDROBIN] = T_('DGS Round-Robin');
         self::$ARR_TOURNEY_TEXTS[$key] = $arr;
      }

      if ( is_null($wiztype) )
         return self::$ARR_TOURNEY_TEXTS[$key];
      if ( !isset(self::$ARR_TOURNEY_TEXTS[$key][$wiztype]) )
         error('invalid_args', "Tournament:getWizardTypeText($wiztype)");
      return self::$ARR_TOURNEY_TEXTS[$key][$wiztype];
   }//getWizardTypeText

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   public static function getStatusText( $status=null )
   {
      // lazy-init of texts
      $key = 'STATUS';
      if ( !isset(self::$ARR_TOURNEY_TEXTS[$key]) )
      {
         $arr = array();
         $arr[TOURNEY_STATUS_ADMIN]    = T_('Admin#T_status');
         $arr[TOURNEY_STATUS_NEW]      = T_('New#T_status');
         $arr[TOURNEY_STATUS_REGISTER] = T_('Register#T_status');
         $arr[TOURNEY_STATUS_PAIR]     = T_('Pair#T_status');
         $arr[TOURNEY_STATUS_PLAY]     = T_('Play#T_status');
         $arr[TOURNEY_STATUS_CLOSED]   = T_('Finished#T_status');
         $arr[TOURNEY_STATUS_DELETE]   = T_('Delete#T_status');
         self::$ARR_TOURNEY_TEXTS[$key] = $arr;
      }

      if ( is_null($status) )
         return array() + self::$ARR_TOURNEY_TEXTS[$key]; // cloned
      if ( !isset(self::$ARR_TOURNEY_TEXTS[$key][$status]) )
         error('invalid_args', "Tournament:getStatusText($status)");
      return self::$ARR_TOURNEY_TEXTS[$key][$status];
   }//getStatusText

   /*! \brief Returns Flags-text or all Flags-texts (if arg=null). */
   public static function getFlagsText( $flag=null, $short=true )
   {
      // lazy-init of texts
      $key = 'FLAGS';
      if ( !isset(self::$ARR_TOURNEY_TEXTS[$key]) )
      {
         $arr = array();
         $arr[TOURNEY_FLAG_LOCK_ADMIN]    = T_('Admin-Lock#T_flag');
         $arr[TOURNEY_FLAG_LOCK_REGISTER] = T_('Register-Lock#T_flag');
         $arr[TOURNEY_FLAG_LOCK_TDWORK]   = T_('Director-Work-Lock#T_flag');
         $arr[TOURNEY_FLAG_LOCK_CRON]     = T_('Cron-Lock#T_flag');
         $arr[TOURNEY_FLAG_LOCK_CLOSE]    = T_('Close-Lock#T_flag');
         self::$ARR_TOURNEY_TEXTS[$key] = $arr;

         $arr = array();
         $arr[TOURNEY_FLAG_LOCK_ADMIN]    = T_('Prohibits all writing operations on tournament for non-admins.#T_locktxt');
         $arr[TOURNEY_FLAG_LOCK_REGISTER] = T_('Prohibits users to join tournament (by application, registration, ACK-invite).#T_locktxt');
         $arr[TOURNEY_FLAG_LOCK_TDWORK]   = T_('Provides exclusive write-access to tournament directors on certain tournament-data.#T_locktxt');
         $arr[TOURNEY_FLAG_LOCK_CRON]     = T_('Provides exclusive write-access to tounament-cron on certain tournament-data.#T_locktxt');
         $arr[TOURNEY_FLAG_LOCK_CLOSE]    = T_('Prohibits users to join tournament or start challenges (preparation to finish tournament).#T_locktxt');
         self::$ARR_TOURNEY_TEXTS[$key.'_LONG'] = $arr;

         $arr = array();
         $arr[TOURNEY_FLAG_LOCK_ADMIN]    = T_('Tournament is in Maintenance-mode (%s)#T_lock');
         $arr[TOURNEY_FLAG_LOCK_REGISTER] = T_('%s is set prohibiting users to register or join tournament.#T_lock');
         $arr[TOURNEY_FLAG_LOCK_TDWORK]   = T_('Edit of ladder needs %s.#T_lock');
         $arr[TOURNEY_FLAG_LOCK_CRON]     = T_('Edit of ladder is prohibited by set %s. Please wait a moment.#T_lock');
         $arr[TOURNEY_FLAG_LOCK_CLOSE]    = T_('%s is set prohibiting users to join tournament or start challenges.#T_lock');
         self::$ARR_TOURNEY_TEXTS[$key.'_LOCK'] = $arr;

         $arr = array();
         $arr[TOURNEY_FLAG_LOCK_ADMIN]    = 'ADMIN';
         $arr[TOURNEY_FLAG_LOCK_REGISTER] = 'REG';
         $arr[TOURNEY_FLAG_LOCK_TDWORK]   = 'TDWORK';
         $arr[TOURNEY_FLAG_LOCK_CRON]     = 'CRON';
         $arr[TOURNEY_FLAG_LOCK_CLOSE]    = 'CLOSE';
         self::$ARR_TOURNEY_TEXTS[$key.'_SHORT'] = $arr;
      }

      if ( $short === false )
         $key .= '_LONG';
      elseif ( $short !== true )
         $key .= '_' . $short;
      if ( is_null($flag) )
         return self::$ARR_TOURNEY_TEXTS[$key];
      if ( !isset(self::$ARR_TOURNEY_TEXTS[$key][$flag]) )
         error('invalid_args', "Tournament:getFlagsText($flag,$short)");
      return self::$ARR_TOURNEY_TEXTS[$key][$flag];
   }//getFlagsText

   public static function getLockText( $flag )
   {
      return span('TLockWarn', sprintf( self::getFlagsText($flag, 'LOCK'), self::getFlagsText($flag)) );
   }

   public static function get_edit_tournament_status()
   {
      static $statuslist = array( TOURNEY_STATUS_NEW );
      return $statuslist;
   }

   public static function get_edit_lock_tournament_status()
   {
      static $statuslist = array( TOURNEY_STATUS_PAIR, TOURNEY_STATUS_REGISTER, TOURNEY_STATUS_PLAY );
      return $statuslist;
   }

   public static function delete_cache_tournament( $dbgmsg, $tid )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_TOURNAMENT, "Tournament.$tid" );
   }

} // end of 'Tournament'

?>
