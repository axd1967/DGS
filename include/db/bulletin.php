<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Bulletin";

require_once 'include/globals.php';
require_once 'include/db_classes.php';
require_once 'include/dgs_cache.php';
require_once 'include/std_classes.php';
require_once 'include/classlib_user.php';

 /*!
  * \file bulletin.php
  *
  * \brief Functions for managing bulletins: tables Bulletin
  * \see specs/db/table-Bulletins.txt
  */

define('BULLETIN_CAT_MAINT',           'MAINT');
define('BULLETIN_CAT_ADMIN_MSG',       'ADM_MSG');
define('BULLETIN_CAT_TOURNAMENT',      'TOURNEY');
define('BULLETIN_CAT_TOURNAMENT_NEWS', 'TNEWS');
define('BULLETIN_CAT_FEATURE',         'FEATURE');
define('BULLETIN_CAT_PRIVATE_MSG',     'PRIV_MSG');
define('BULLETIN_CAT_SPAM',            'AD');
define('CHECK_BULLETIN_CATEGORY', 'MAINT|ADM_MSG|TOURNEY|TNEWS|FEATURE|PRIV_MSG|AD');

define('BULLETIN_STATUS_NEW',       'NEW');
define('BULLETIN_STATUS_PENDING',   'PENDING');
define('BULLETIN_STATUS_REJECTED',  'REJECTED');
define('BULLETIN_STATUS_SHOW',      'SHOW');
define('BULLETIN_STATUS_ARCHIVE',   'ARCHIVE');
define('BULLETIN_STATUS_DELETE',    'DELETE');
define('CHECK_BULLETIN_STATUS', 'NEW|PENDING|REJECTED|SHOW|ARCHIVE|DELETE');

define('BULLETIN_TRG_UNSET',    'UNSET'); // needs assignment, not defined in DB (only application-default)
define('BULLETIN_TRG_ALL',      'ALL');
define('BULLETIN_TRG_TD',       'TD'); // tourney-director
define('BULLETIN_TRG_TP',       'TP'); // tourney-participant
define('BULLETIN_TRG_USERLIST', 'UL');
define('BULLETIN_TRG_MPG',      'MPG'); // multi-player-game
define('CHECK_BULLETIN_TARGET_TYPE', 'UNSET|ALL|TD|TP|UL|MPG');

// also adjust GuiBulletin::getFlagsText()
define('BULLETIN_FLAG_ADMIN_CREATED', 0x01); // bulletin created by admin
define('BULLETIN_FLAG_USER_EDIT',     0x02); // bulletin can be edited by user

// stored in bitmask Players.SkipBulletin
define('BULLETIN_SKIPCAT_TOURNAMENT',  0x01);
define('BULLETIN_SKIPCAT_PRIVATE_MSG', 0x02);
define('BULLETIN_SKIPCAT_SPAM',        0x04);
define('BULLETIN_SKIPCAT_FEATURE',     0x08);


 /*!
  * \class Bulletin
  *
  * \brief Class to manage Bulletin-table
  */

global $ENTITY_BULLETIN; //PHP5
$ENTITY_BULLETIN = new Entity( 'Bulletin',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_OPTLOCK,
      FTYPE_INT,  'ID', 'uid', 'tid', 'gid', 'CountReads', 'Flags',
      FTYPE_ENUM, 'Category', 'Status', 'TargetType',
      FTYPE_TEXT, 'AdminNote', 'Subject', 'Text',
      FTYPE_DATE, 'PublishTime', 'ExpireTime', 'Lastchanged'
   );

class Bulletin
{
   var $ID;
   var $uid;
   var $LockVersion;
   var $Category;
   var $Status;
   var $TargetType;
   var $Flags;
   var $PublishTime;
   var $ExpireTime;
   var $tid;
   var $gid;
   var $CountReads;
   var $AdminNote;
   var $Subject;
   var $Text;
   var $Lastchanged;

   // non-DB fields

   var $User; // User-object
   var $UserList; // [ uid, ...] for TargetType=UL
   var $UserListHandles; // [ Handle, =1234, ...] for TargetType=UL (with '='-prefix for numeric handles)
   var $UserListUserRefs; // [ uid => [ ID/Handle/Name/C_RejectMsg => val ], ... ]
   var $Tournament; // Tournament-object for $tid | null

   var $ReadState; // 0 = unread, 1 = marked as read, false = unset

   /*! \brief Constructs Bulletin-object with specified arguments. */
   function Bulletin( $id=0, $uid=0, $user=null, $category=BULLETIN_CAT_ADMIN_MSG,
            $status=BULLETIN_STATUS_NEW, $target_type=BULLETIN_TRG_UNSET, $flags=0,
            $publish_time=0, $expire_time=0, $tid=0, $gid=0, $count_reads=0, $admin_note='',
            $subject='', $text='', $lastchanged=0 )
   {
      $this->ID = (int)$id;
      $this->uid = (int)$uid;
      $this->LockVersion = null;
      $this->setCategory( $category );
      $this->setStatus( $status );
      $this->setTargetType( $target_type );
      $this->Flags = (int)$flags;
      $this->PublishTime = (int)$publish_time;
      $this->ExpireTime = (int)$expire_time;
      $this->tid = (int)$tid;
      $this->gid = (int)$gid;
      $this->CountReads = (int)$count_reads;
      $this->AdminNote = $admin_note;
      $this->Subject = $subject;
      $this->Text = $text;
      $this->Lastchanged = (int)$lastchanged;
      // non-DB fields
      $this->User = (is_a($user, 'User')) ? $user : new User( $this->uid );
      $this->UserList = array();
      $this->UserListHandles = array();
      $this->UserListUserRefs = array();
      $this->Tournament = null;
      $this->ReadState = false;
   }

   /*! \brief Returns true if LockVersion for optimistic-locking does not match latest version. */
   function is_optimistic_lock_clash()
   {
      if( $this->ID == 0 || is_null($this->LockVersion) || mysql_affected_rows() >= 1 ) //multi-update not supported
         return false;
      $row = mysql_single_fetch( "Bulletin.is_optimistic_lock_clash({$this->ID},{$this->LockVersion})",
         "SELECT ".FIELD_LOCKVERSION." FROM Bulletin WHERE ID={$this->ID} LIMIT 1" );
      return ( !$row || (int)$row[FIELD_LOCKVERSION] != $this->LockVersion );
   }

   function readLockVersion()
   {
      $lock_version = @$_REQUEST[FORMFIELD_LOCKVERSION];
      if( (string)$lock_version != '' )
         $this->LockVersion = (int)$lock_version;
   }

   function to_string()
   {
      return print_r($this, true);
   }

   function setCategory( $category )
   {
      if( !preg_match( "/^(".CHECK_BULLETIN_CATEGORY.")$/", $category ) )
         error('invalid_args', "Bulletin.setCategory($category)");
      $this->Category = $category;
   }

   function setStatus( $status )
   {
      if( !preg_match( "/^(".CHECK_BULLETIN_STATUS.")$/", $status ) )
         error('invalid_args', "Bulletin.setStatus($status)");
      $this->Status = $status;
   }

   function setTargetType( $target_type )
   {
      if( !preg_match( "/^(".CHECK_BULLETIN_TARGET_TYPE.")$/", $target_type ) )
         error('invalid_args', "Bulletin.setTargetType($target_type)");
      $this->TargetType = $target_type;
   }

   function is_user_bulletin()
   {
      return ($this->Flags & BULLETIN_FLAG_USER_EDIT);
   }

   /*! \brief Inserts or updates Bulletin-entry in database. */
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
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      $result = $entityData->insert( "Bulletin.insert(%s)" );
      if( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->update( "Bulletin.update(%s)" );
   }

   function fillEntityData( $data=null )
   {
      if( is_null($data) )
         $data = $GLOBALS['ENTITY_BULLETIN']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'uid', $this->uid );
      if( !is_null($this->LockVersion) )
         $data->set_value( FIELD_LOCKVERSION, $this->LockVersion );
      $data->set_value( 'Category', $this->Category );
      $data->set_value( 'Status', $this->Status );
      $data->set_value( 'TargetType', $this->TargetType );
      $data->set_value( 'Flags', $this->Flags );
      $data->set_value( 'PublishTime', $this->PublishTime );
      $data->set_value( 'ExpireTime', $this->ExpireTime );
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'gid', $this->gid );
      $data->set_value( 'CountReads', $this->CountReads );
      $data->set_value( 'AdminNote', $this->AdminNote );
      $data->set_value( 'Subject', $this->Subject );
      $data->set_value( 'Text', $this->Text );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      return $data;
   }

   function loadUserList()
   {
      $this->UserList = array();
      $this->UserListHandles = array();
      $this->UserListUserRefs = array();

      if( $this->ID > 0 )
      {
         $result = db_query( "Bulletin.loadUserList({$this->ID})",
            "SELECT BT.uid as ID, P.Handle, P.Name, IFNULL(C.uid,0) AS C_RejectMsg " .
            "FROM BulletinTarget AS BT " .
               "INNER JOIN Players AS P ON P.ID=BT.uid " .
               "LEFT JOIN Contacts AS C ON C.uid=BT.uid AND C.cid={$this->uid} AND (C.SystemFlags & ".CSYSFLAG_REJECT_MESSAGE.") " .
            "WHERE BT.bid={$this->ID} ORDER BY BT.uid" );
         while( $row = mysql_fetch_array( $result ) )
         {
            $this->UserList[] = $row['ID'];
            $this->UserListHandles[] = ( is_numeric($row['Handle']) ? '=' : '' ) . $row['Handle'];
            $this->UserListUserRefs[$row['ID']] = $row;
         }
         mysql_free_result($result);
      }
   }//loadUserList

   /*! \brief Wrapper to update_bulletin_count_players()-func for this bulletin. */
   function update_count_players( $dbgmsg, $uid=0 )
   {
      return Bulletin::update_bulletin_count_players( $dbgmsg, $this->Status, $this->TargetType,
         $uid, $this->ID, $this->tid, $this->gid );
   }

   /*!
    * \brief Returns true if this Bulletin can be edited by user.
    * \param $errmsg if set and edit is not allowed, throw error(), otherwise return just false instead
    */
   function allow_bulletin_user_edit( $uid, $errmsg='' )
   {
      if( $errmsg )
         $errmsg .= "({$this->ID},{$this->Status},{$this->Flags},$uid)";

      if( $this->uid != $uid ) // not author
         return ($errmsg) ? error('bulletin_edit_not_allowed', "$errmsg.author") : false;
      if( $this->Status == BULLETIN_STATUS_ARCHIVE || $this->Status == BULLETIN_STATUS_DELETE )
         return ($errmsg) ? error('bulletin_edit_not_allowed', "$errmsg.status") : false;

      // check despite easier check on user-edit-flag
      if( $this->TargetType == BULLETIN_TRG_MPG && $this->Category == BULLETIN_CAT_PRIVATE_MSG && $this->gid > 0 )
         return true;
      if( ($this->TargetType == BULLETIN_TRG_TP || $this->TargetType == BULLETIN_TRG_TD )
            && $this->Category == BULLETIN_CAT_TOURNAMENT_NEWS && $this->tid > 0 )
         return true;

      if( $this->Flags & BULLETIN_FLAG_USER_EDIT )
         return true;

      return ($errmsg) ? error('bulletin_edit_not_allowed', "$errmsg.last_check") : false;
   }//allow_bulletin_user_edit

   function allow_bulletin_user_view()
   {
      return ( $this->Status == BULLETIN_STATUS_SHOW || $this->Status == BULLETIN_STATUS_ARCHIVE );
   }

   /*! \brief Returns true if this Bulletin.Category should be skipped according to Players.SkipBulletin-flags. */
   function skipCategory()
   {
      global $player_row;

      $skip_bullcat = (int)$player_row['SkipBulletin'];
      if( $skip_bullcat > 0 )
      {
         if( $this->Category == BULLETIN_CAT_TOURNAMENT )
            return ( $skip_bullcat & BULLETIN_SKIPCAT_TOURNAMENT );
         if( $this->Category == BULLETIN_CAT_FEATURE )
            return ( $skip_bullcat & BULLETIN_SKIPCAT_FEATURE );
         if( $this->Category == BULLETIN_CAT_PRIVATE_MSG )
            return ( $skip_bullcat & BULLETIN_SKIPCAT_PRIVATE_MSG );
         if( $this->Category == BULLETIN_CAT_SPAM )
            return ( $skip_bullcat & BULLETIN_SKIPCAT_SPAM );
      }
      return false;
   }//skipCategory


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of Bulletin-objects for given game-id. */
   function build_query_sql( $bid=0, $with_player=true )
   {
      $qsql = $GLOBALS['ENTITY_BULLETIN']->newQuerySQL('B');
      if( $with_player )
      {
         $qsql->add_part( SQLP_FIELDS,
            'B.uid AS BP_ID',
            'BP.Name AS BP_Name',
            'BP.Handle AS BP_Handle' );
         $qsql->add_part( SQLP_FROM,
            'INNER JOIN Players AS BP ON BP.ID=B.uid' );
      }
      if( $bid > 0 )
         $qsql->add_part( SQLP_WHERE, "B.ID=$bid" );
      return $qsql;
   }

   /*! \brief Returns Bulletin-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $bull = new Bulletin(
            // from Bulletin
            @$row['ID'],
            @$row['uid'],
            User::new_from_row( $row, 'BP_' ), // from Players BP
            @$row['Category'],
            @$row['Status'],
            @$row['TargetType'],
            @$row['Flags'],
            @$row['X_PublishTime'],
            @$row['X_ExpireTime'],
            @$row['tid'],
            @$row['gid'],
            @$row['CountReads'],
            @$row['AdminNote'],
            @$row['Subject'],
            @$row['Text'],
            @$row['X_Lastchanged']
         );
      $bull->LockVersion = (int)@$row[FIELD_LOCKVERSION];
      if( isset($row['BR_Read']) )
         $bull->ReadState = (int)@$row['BR_Read'];
      return $bull;
   }

   /*!
    * \brief Returns QuerySQL with restrictions to view bulletins to what current user is allowed to view,
    *        skipping bulletins according to user-prefs 'SkipBulletin' except for $is_admin.
    * \param $is_admin true, if user is admin; false = normal user
    * \param $count_new true to count new-bulletins (for main-menu); false for list-bulletins
    * \param $show_target_type BULLETIN_TRG_... = query restricted to specific target-type or '' for all target-types.
    *        'B_View'-field indicating if bulletin is shown or not, values: 0 = don't show, 1 = show entry
    * \param $check if true, don't restrict on viewable (B_View > 0)
    */
   function build_view_query_sql( $is_admin, $count_new, $show_target_type='', $check=false )
   {
      global $player_row;
      $uid = (int)@$player_row['ID'];
      if( $uid <= 0 )
         error('invalid_args', "Bulletin::build_view_query_sql.check.uid($is_admin,$count_new,$show_target_type)");

      $qsql = new QuerySQL();
      if( $count_new )
         $show_target_type = '';

      if( $count_new )
      {
         $qsql->add_part( SQLP_FROM, "Bulletin AS B" );
         $qsql->add_part( SQLP_WHERE,
            "B.Status='".BULLETIN_STATUS_SHOW."'",
            "BR.bid IS NULL" ); // count only unread
      }
      else
      {
         if( !$is_admin ) // hide some bulletins
            $qsql->add_part( SQLP_WHERE, "B.Status IN ('".BULLETIN_STATUS_SHOW."','".BULLETIN_STATUS_ARCHIVE."')" );
      }

      // ignore all bulletins user configured to skip
      $skip_bullcat = (int)$player_row['SkipBulletin'];
      if( !$is_admin && $skip_bullcat > 0 )
      {
         $find_categories = array( BULLETIN_CAT_MAINT, BULLETIN_CAT_ADMIN_MSG, BULLETIN_CAT_TOURNAMENT_NEWS );
         if( !($skip_bullcat & BULLETIN_SKIPCAT_TOURNAMENT) )
            $find_categories[] = BULLETIN_CAT_TOURNAMENT;
         if( !($skip_bullcat & BULLETIN_SKIPCAT_FEATURE ) )
            $find_categories[] = BULLETIN_CAT_FEATURE;
         if( !($skip_bullcat & BULLETIN_SKIPCAT_PRIVATE_MSG) )
            $find_categories[] = BULLETIN_CAT_PRIVATE_MSG;
         if( !($skip_bullcat & BULLETIN_SKIPCAT_SPAM) )
            $find_categories[] = BULLETIN_CAT_SPAM;
         $qsql->add_part( SQLP_WHERE, "B.Category IN ('".implode("','", $find_categories)."')" );
      }

      // BR_Read = 1 = mark-as-read, 0 = unread (=BR.bid IS NULL)
      $qsql->add_part( SQLP_FROM, "LEFT JOIN BulletinRead AS BR ON BR.bid=B.ID AND BR.uid=$uid" );
      if( !$count_new )
         $qsql->add_part( SQLP_FIELDS, 'IFNULL(BR.bid,0) AS BR_Read' );

      // handle target-types (UNSET not possible)
      if( $show_target_type == BULLETIN_TRG_TP ) // restricted to TargetType=TP (tournament-participant)
      {
         $qsql->add_part( SQLP_FIELDS, "1 AS B_View" );
         $qsql->add_part( SQLP_FROM,   "INNER JOIN TournamentParticipant AS BTP ON BTP.tid=B.tid AND BTP.uid=$uid" );
         $qsql->add_part( SQLP_WHERE,
            "B.TargetType='$show_target_type'",
            "B.tid > 0" );
      }
      elseif( $show_target_type == BULLETIN_TRG_TD ) // restricted to TargetType=DL (tournament-owner & director)
      {
         $qsql->add_part( SQLP_FIELDS, "1 AS B_View" );
         $qsql->add_part( SQLP_FROM,
            "LEFT JOIN TournamentDirector AS BTD ON BTD.tid=B.tid AND BTD.uid=$uid",
            "LEFT JOIN Tournament AS BTN ON BTN.ID=B.tid AND BTN.Owner_ID=$uid" );
         $qsql->add_part( SQLP_WHERE,
            "B.TargetType='$show_target_type'",
            "B.tid > 0",
            "(BTD.tid IS NOT NULL OR BTN.ID IS NOT NULL)" );
      }
      elseif( $show_target_type == BULLETIN_TRG_USERLIST ) // restricted to TargetType=UL (userlist)
      {
         $qsql->add_part( SQLP_FIELDS, "1 AS B_View" );
         $qsql->add_part( SQLP_FROM,   "INNER JOIN BulletinTarget AS BT ON BT.bid=B.ID AND BT.uid=$uid" );
         $qsql->add_part( SQLP_WHERE,  "B.TargetType='$show_target_type'" );
      }
      elseif( $show_target_type == BULLETIN_TRG_ALL ) // restricted to TargetType=ALL (all-users)
      {
         $qsql->add_part( SQLP_FIELDS, "1 AS B_View" );
      }
      elseif( $show_target_type == BULLETIN_TRG_MPG ) // restricted to TargetType=MPG (multi-player-game)
      {
         $qsql->add_part( SQLP_FIELDS, "1 AS B_View" );
         $qsql->add_part( SQLP_FROM,
            "INNER JOIN Games AS G ON G.ID=B.gid",
            "INNER JOIN GamePlayers AS GP ON GP.gid=G.ID AND GP.uid=$uid" );
         $qsql->add_part( SQLP_WHERE,
            "B.TargetType='$show_target_type'",
            "B.gid > 0",
            "G.GameType IN ('".GAMETYPE_TEAM_GO."','".GAMETYPE_ZEN_GO."')" );
      }
      else // show all target-types
      {
         $view_sql =
            "CASE B.TargetType " .
               "WHEN '".BULLETIN_TRG_ALL."' THEN 1 " .
               "WHEN '".BULLETIN_TRG_TP."' THEN IF(BTP.tid IS NULL,0,1) " .
               "WHEN '".BULLETIN_TRG_TD."' THEN IF(BTD.tid IS NULL,IF(BTN.ID IS NULL,0,1),1) " .
               "WHEN '".BULLETIN_TRG_USERLIST."' THEN IF(BT.bid IS NULL,0,1) " .
               "WHEN '".BULLETIN_TRG_MPG."' THEN IF(GP.gid IS NULL,0,1) " .
               "ELSE 0 END";
         if( $count_new )
            $qsql->add_part( SQLP_FIELDS, "SUM($view_sql) AS X_Count" );
         else
            $qsql->add_part( SQLP_FIELDS, "($view_sql) AS B_View" );

         $qsql->add_part( SQLP_FROM,
            // target-type=TP
            "LEFT JOIN TournamentParticipant AS BTP " .
               "ON BTP.tid=B.tid AND BTP.uid=$uid AND B.TargetType='".BULLETIN_TRG_TP."' AND B.tid > 0",
            // target-type=TD
            "LEFT JOIN TournamentDirector AS BTD " .
               "ON BTD.tid=B.tid AND BTD.uid=$uid AND B.TargetType='".BULLETIN_TRG_TD."' AND B.tid > 0",
            "LEFT JOIN Tournament AS BTN " .
               "ON BTN.ID=B.tid AND BTN.Owner_ID=$uid AND B.TargetType='".BULLETIN_TRG_TD."' AND B.tid > 0",
            // target-type=UL
            "LEFT JOIN BulletinTarget AS BT " .
               "ON BT.bid=B.ID AND BT.uid=$uid AND B.TargetType='".BULLETIN_TRG_USERLIST."'",
            // target-type=MPG
            "LEFT JOIN Games AS G " .
               "ON G.ID=B.gid AND B.TargetType='".BULLETIN_TRG_MPG."' AND B.gid > 0 " .
                  "AND G.GameType IN ('".GAMETYPE_TEAM_GO."','".GAMETYPE_ZEN_GO."')",
            "LEFT JOIN GamePlayers AS GP " .
               "ON GP.gid=G.ID AND GP.uid=$uid AND B.gid > 0" );
      }

      if( !$count_new && !$is_admin && !$check )
         $qsql->add_part( SQLP_HAVING, "B_View > 0" );

      return $qsql;
   }//build_view_query_sql

   /*!
    * \brief Loads and returns Bulletin-object for given bulletin-id limited to 1 result-entry.
    * \param $bid Bulletin.ID
    * \return NULL if nothing found; Bulletin-object otherwise
    */
   function load_bulletin( $bid, $with_player=true )
   {
      $qsql = Bulletin::build_query_sql( $bid, $with_player );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "Bulletin::load_bulletin.find_Bulletin($bid)", $qsql->get_select() );
      return ($row) ? Bulletin::new_from_row($row) : NULL;
   }

   /*! \brief Loads and returns Bulletin-object for given bulletin-QuerySQL; NULL if nothing found. */
   function load_bulletin_by_query( $qsql, $with_row=false )
   {
      $qsql->add_part( SQLP_LIMIT, '1' );
      $row = mysql_single_fetch( "Bulletin::load_bulletin_by_query()", $qsql->get_select() );
      if( $with_row )
         return ($row) ? array( Bulletin::new_from_row($row), $row ) : array( NULL, NULL );
      else
         return ($row) ? Bulletin::new_from_row($row) : NULL;
   }

   /*! \brief Returns enhanced (passed) ListIterator with Bulletin-objects. */
   function load_bulletins( $iterator )
   {
      $qsql = Bulletin::build_query_sql();
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "Bulletin.load_bulletins", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $rca = Bulletin::new_from_row( $row );
         $iterator->addItem( $rca, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

   /*!
    * \brief Returns new Bulletin-object for user and args.
    * \param $gid if set, creates MPG-target-type bulletin
    * \param $tid if set, creates TP-target-type bulletin
    * \param $new_uid if set, creates new bulletin for user
    */
   function new_bulletin( $is_admin, $gid=0, $tid=0, $new_uid=0 )
   {
      global $player_row;

      $uid = (int)@$player_row['ID'];
      if( !is_numeric($uid) || $uid <= GUESTS_ID_MAX )
         error('invalid_args', "Bulletin.new_bulletin.check.uid($uid,$is_admin,$gid,$tid,$new_uid)");

      if( !is_numeric($gid) || $gid < 0 )
         error('invalid_args', "Bulletin.new_bulletin.check.gid($uid,$is_admin,$gid)");
      if( !is_numeric($tid) || $tid < 0 )
         error('invalid_args', "Bulletin.new_bulletin.check.tid($uid,$is_admin,$tid)");
      if( !is_numeric($new_uid) || ($new_uid != 0 && $new_uid <= GUESTS_ID_MAX) )
         error('invalid_args', "Bulletin.new_bulletin.check.new_uid_guest($uid,$is_admin,$new_uid)");
      if( !$is_admin && $new_uid > 0 )
         error('invalid_args', "Bulletin.new_bulletin.check.new_uid0($uid,$is_admin,$new_uid)");

      if( $new_uid == $uid || $new_uid == 0 )
         $user = new User( $uid, @$player_row['Name'], @$player_row['Handle'] );
      else
      {
         $user = User::load_user( $new_uid );
         if( is_null($user) )
            error('unknown_user', "Bulletin.new_bulletin.check.find_user($uid,$new_uid)");
         $uid = $new_uid;
      }

      $bulletin = new Bulletin( 0, $uid, $user );
      $bulletin->PublishTime = $GLOBALS['NOW'];
      $bulletin->ExpireTime = $bulletin->PublishTime + 30 * SECS_PER_DAY; // default +30d

      if( $is_admin )
      {
         $bulletin->setCategory( BULLETIN_CAT_ADMIN_MSG );
         $bulletin->Flags = BULLETIN_FLAG_ADMIN_CREATED;
      }
      else
      {
         $bulletin->setCategory( BULLETIN_CAT_PRIVATE_MSG );
         $bulletin->Flags = BULLETIN_FLAG_USER_EDIT;
      }

      if( $gid > 0 ) // MPG-bulletin
      {
         $bulletin->setStatus( BULLETIN_STATUS_SHOW ); // no admin-ACK needed
         $bulletin->setTargetType( BULLETIN_TRG_MPG );
         $bulletin->gid = $gid;
      }
      elseif( $tid > 0 ) // TP-bulletin
      {
         $bulletin->setCategory( BULLETIN_CAT_TOURNAMENT_NEWS );
         $bulletin->setStatus( BULLETIN_STATUS_SHOW ); // no admin-ACK needed
         $bulletin->setTargetType( BULLETIN_TRG_TP );
         $bulletin->tid = $tid;
      }
      elseif( $new_uid > 0 ) // prepare bulletin for user
      {
         $bulletin->setCategory( BULLETIN_CAT_PRIVATE_MSG );
         $bulletin->setTargetType( BULLETIN_TRG_ALL );
         $bulletin->Flags |= BULLETIN_FLAG_USER_EDIT;
         $bulletin->Subject = "ENTER SUBJECT";
         $bulletin->Text = "ENTER TEXT";
      }

      return $bulletin;
   }//new_bulletin

   function mark_bulletin_as_read( $bid )
   {
      global $player_row;
      if( !is_numeric($bid) || $bid <= 0 )
         error('invalid_args', "Bulletin::mark_bulletin_as_read.check.bid($bid)");
      $uid = (int)$player_row['ID'];

      ta_begin();
      {//HOT-section to mark bulletin as read
         db_query( "Bulletin::mark_bulletin_as_read($bid,$uid)",
            "INSERT IGNORE BulletinRead (bid,uid) " .
            "SELECT ID, $uid FROM Bulletin WHERE ID=$bid AND Status IN ('".BULLETIN_STATUS_SHOW."','".BULLETIN_STATUS_ARCHIVE."') LIMIT 1" );

         if( mysql_affected_rows() > 0 ) // increase read-counter
         {
            $bulletin = Bulletin::load_bulletin( $bid, /*with_player*/false );
            if( !is_null($bulletin) )
            {
               $bulletin->update_count_players( 'Bulletin::mark_bulletin_as_read', $uid );
               Bulletin::update_count_bulletin_new( "Bulletin::mark_bulletin_as_read($bid,$uid)", COUNTNEW_RECALC );

               db_query( "Bulletin::mark_bulletin_as_read.inc_read($bid)",
                  "UPDATE Bulletin SET CountReads=CountReads+1 WHERE ID=$bid LIMIT 1" );
            }

            clear_cache_quick_status( $uid, QST_CACHE_BULLETIN );
            Bulletin::delete_cache_bulletins( "Bulletin::mark_bulletin_as_read($bid)", $uid );
         }
      }
      ta_end();
   }//mark_bulletin_as_read

   /*!
    * \brief Persists user-list for bulletin in BulletinTarget-table.
    *
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
   function persist_bulletin_userlist( $bid, $uids )
   {
      if( !is_numeric($bid) || $bid <= 0 )
         error('invalid_args', "Bulletin::persist_bulletin_userlist.check.bid($bid)");
      if( !is_array($uids) || count($uids) == 0 )
         error('invalid_args', "Bulletin::persist_bulletin_userlist.check.uids($bid)");

      $uids = array_unique($uids);
      foreach( $uids as $uid )
      {
         if( !is_numeric($uid) || $uid <= GUESTS_ID_MAX )
            error('invalid_args', "Bulletin::persist_bulletin_userlist.check.uids.bad_uid($bid,$uid)");
      }
      $uids_sql = implode(',', $uids);

      db_query( "Bulletin::persist_bulletin_userlist.del($bid)",
         "DELETE FROM BulletinTarget WHERE bid=$bid AND uid NOT IN ($uids_sql)" );

      $cnt_uids = count($uids);
      db_query( "Bulletin::persist_bulletin_userlist.add($bid,$cnt_uids)",
         "INSERT IGNORE BulletinTarget (bid,uid) " .
         "SELECT $bid, ID FROM Players WHERE ID IN ($uids_sql) LIMIT $cnt_uids" );
   }//persist_bulletin_userlist

   /*! \brief Change status of expired bulletins of all target-types. */
   function process_expired_bulletins()
   {
      global $NOW;

      db_query( "Bulletin::process_expired_bulletins.upd_bulletin()",
         "UPDATE Bulletin SET " .
            "Status='".BULLETIN_STATUS_ARCHIVE."', " .
            "AdminNote=TRIM(CONCAT('EXPIRED ',AdminNote)) " .
         "WHERE Status='".BULLETIN_STATUS_SHOW."' AND " .
            "ExpireTime > 0 AND ExpireTime < FROM_UNIXTIME($NOW)" );

      if( mysql_affected_rows() > 0 )
         Bulletin::update_bulletin_count_players( 'Bulletin::process_expired_bulletins',
            BULLETIN_STATUS_SHOW, BULLETIN_TRG_ALL );
   }//process_expire_bulletins

   /*!
    * \brief Updates or resets Players.CountBulletinNew for current user.
    * \param $uid Players.ID to update; 0 = current logged-in player
    * \param $diff null|omit to reset to -1 (=recalc later); COUNTNEW_RECALC to recalc now;
    *        otherwise increase or decrease counter
    */
   function update_count_bulletin_new( $dbgmsg, $uid=0, $diff=null )
   {
      global $player_row;

      if( $uid <= 0 )
         $uid = (int)@$player_row['ID'];
      $dbgmsg .= "Bulletin::update_count_bulletin_new($uid,$diff)";
      if( $uid <= 0 )
         error( 'invalid_args', "$dbgmsg.check.uid" );

      if( is_null($diff) )
      {
         db_query( "$dbgmsg.reset",
            "UPDATE Players SET CountBulletinNew=-1 WHERE ID='$uid' LIMIT 1" );
      }
      elseif( is_numeric($diff) && $diff != 0 )
      {
         db_query( "$dbgmsg.upd",
            "UPDATE Players SET CountBulletinNew=CountBulletinNew+($diff) " .
            "WHERE CountBulletinNew>=0 AND ID='$uid' LIMIT 1" );
      }
      elseif( (string)$diff == COUNTNEW_RECALC )
      {
         $count_new = Bulletin::count_bulletin_new( -1 );
         $player_row['CountBulletinNew'] = $count_new;
         db_query( "$dbgmsg.recalc",
            "UPDATE Players SET CountBulletinNew=$count_new WHERE ID='$uid' LIMIT 1" );
      }

      Bulletin::delete_cache_bulletins( $dbgmsg, $uid );
   }//update_count_bulletin_new

   /*!
    * \brief Counts new bulletins for current user if current count < 0 (=needs-update).
    * \param $curr_count force counting if <0 or omitted
    * \return new bulletin count (>=0) for given user-id; or -1 on error
    */
   function count_bulletin_new( $curr_count=-1 )
   {
      if( $curr_count >= 0 )
         return $curr_count;

      $qsql = Bulletin::build_view_query_sql( /*adm*/false, /*count*/true );
      $row = mysql_single_fetch( 'Bulletin::count_bulletin_new', $qsql->get_select() );
      return ($row) ? (int)@$row['X_Count'] : -1;
   }

   /*!
    * \brief Updates Players.CountBulletinNew according for given Bulletin-data (only updated on SHOW-status).
    * \param $status BULLETIN_STATUS_...
    * \param $target_type BULLETIN_TRG_...
    * \param $uid restrict update to given user-id; can be uid-array; 0 otherwise for all users
    * \return true, if update required; false otherwise
    *
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    *
    * \note to avoid updating ALL players, restrict update to players with last-access
    *       up to session-expire, and login.php resets counter too on session-expire
    * \see also count_bulletin_new() in 'include/std_functions.php'
    */
   function update_bulletin_count_players( $dbgmsg, $status, $target_type, $uid=0, $bid=0, $tid=0, $gid=0 )
   {
      global $NOW;
      $dbgmsg .= "Bulletin::update_bulletin_count_players($status,$target_type,$uid)";

      Bulletin::update_cache_bulletins_global( $dbgmsg );

      if( $status != BULLETIN_STATUS_SHOW )
         return false;

      // NOTE: no sql-'LIMIT' allowed with multi-table-UPDATE
      if( is_numeric($uid) && $uid > 0 )
         $qpart_uid = " AND P.ID=$uid";
      elseif( is_array($uid) && count($uid) > 0 )
         $qpart_uid = " AND P.ID IN (".implode(',', $uid).")";
      else
         $qpart_uid = '';

      if( $target_type == BULLETIN_TRG_TD && $tid > 0 )
      {
         db_query( "$dbgmsg.upd_td($tid)",
            "UPDATE Players AS P INNER JOIN TournamentDirector AS TD ON TD.uid=P.ID " .
            "SET P.CountBulletinNew=-1 WHERE TD.tid=$tid $qpart_uid" );
         db_query( "$dbgmsg.upd_towner($tid)",
            "UPDATE Players AS P INNER JOIN Tournament AS T ON T.Owner_ID=P.ID " .
            "SET P.CountBulletinNew=-1 WHERE T.ID=$tid $qpart_uid" );
      }
      elseif( $target_type == BULLETIN_TRG_TP && $tid > 0 )
      {
         db_query( "$dbgmsg.upd_tp($tid)",
            "UPDATE Players AS P INNER JOIN TournamentParticipant AS TP ON TP.uid=P.ID " .
            "SET P.CountBulletinNew=-1 WHERE TP.tid=$tid $qpart_uid" );
      }
      elseif( $target_type == BULLETIN_TRG_USERLIST && $bid > 0 )
      {
         db_query( "$dbgmsg.upd_userlist($bid)",
            "UPDATE Players AS P INNER JOIN BulletinTarget AS BT ON BT.uid=P.ID " .
            "SET P.CountBulletinNew=-1 WHERE BT.bid=$bid $qpart_uid" );
      }
      elseif( $target_type == BULLETIN_TRG_MPG && $gid > 0 )
      {
         db_query( "$dbgmsg.upd_mpg($gid)",
            "UPDATE Players AS P INNER JOIN GamePlayers AS GP ON GP.uid=P.ID " .
            "SET P.CountBulletinNew=-1 WHERE GP.gid=$gid $qpart_uid" );
      }
      elseif( $target_type == BULLETIN_TRG_UNSET ) // should not occur
         error('invalid_args', "$dbgmsg.check.target_type($target_type)");
      else //if( $target_type == BULLETIN_TRG_ALL || $tid/bid/gid <=0 for respective target-type
      {
         db_query( "$dbgmsg.upd_all",
            "UPDATE Players AS P SET P.CountBulletinNew=-1 " .
            "WHERE P.Lastaccess >= FROM_UNIXTIME(" . ($NOW - SESSION_DURATION) . ") $qpart_uid" );
      }

      return true;
   }//update_bulletin_count_players

   /*! \brief Deletes all existing BulletinRead-entries for given bulletin-id. */
   function reset_bulletin_read( $bid )
   {
      db_query( "Bulletin::reset_bulletin_read($bid)",
         "DELETE FROM BulletinRead WHERE bid=$bid" );
   }//reset_bulletin_read

   /*! \brief Returns row-arr or null if no game found. */
   function load_multi_player_game( $gid )
   {
      $game_row = mysql_single_fetch( "Bulletin::load_multi_player_game($gid)",
         "SELECT ID, GameType, Status FROM Games where ID=$gid LIMIT 1" );
      return ($game_row) ? $game_row : null;
   }

   /*! \brief Returns true if current players is bulletin-admin. */
   function is_bulletin_admin()
   {
      global $player_row;
      return (@$player_row['admin_level'] & ADMIN_DEVELOPER);
   }

   function load_cache_bulletins( $dbgmsg, $uid )
   {
      $dbgmsg = $dbgmsg.".Bulletin::load_cache_bulletins($uid)";
      $gkey = "Bulletins.global";
      $key = "Bulletins.$uid";

      // time of last new or changed bulletin
      $lastchange_bulletin = DgsCache::fetch( $dbgmsg, CACHE_GRP_BULLETINS, $gkey );
      if( !is_numeric($lastchange_bulletin) )
         $lastchange_bulletin = 0;

      $arr_rows = DgsCache::fetch( $dbgmsg, CACHE_GRP_BULLETINS, $key );
      if( is_array($arr_rows) )
      {
         // check if cache-entry creation-date is older than that of global bulletin-change-time
         if( count($arr_rows) > 0 )
         {
            $stored_creation_time = array_shift($arr_rows); // remove time for final result
            if( $lastchange_bulletin > 0 && $stored_creation_time < $lastchange_bulletin )
               $arr_rows = null; // outdated -> reload
         }
         else
            $arr_rows = null; // invalid store-data -> reload
      }

      $result = array();
      if( is_null($arr_rows) )
      {
         $iterator = new ListIterator( $dbgmsg.'.list_bulletin.unread',
            new QuerySQL( SQLP_WHERE,
                  "BR.bid IS NULL", // only unread
                  "B.Status='".BULLETIN_STATUS_SHOW."'" ),
            'ORDER BY B.PublishTime DESC' );
         $iterator->addQuerySQLMerge( Bulletin::build_view_query_sql( /*adm*/false, /*count*/false ) );
         $iterator = Bulletin::load_bulletins( $iterator );

         $cache_result = array( $GLOBALS['NOW'] ); // bulletin-cache-entry creation-time
         while( list(,$arr_item) = $iterator->getListIterator() )
         {
            $result[] = $arr_item[0]; // Bulletin-obj
            $cache_result[] = $arr_item[1]; // only cache row-data
         }

         // store in cache
         DgsCache::store( $dbgmsg, CACHE_GRP_BULLETINS, $key, $cache_result, SECS_PER_DAY );
      }
      else // transform cache-stored row-arr into Bulletin-arr
      {
         foreach( $arr_rows as $row )
            $result[] = Bulletin::new_from_row( $row );
      }

      return $result;
   }//load_cache_bulletins

   function delete_cache_bulletins( $dbgmsg, $uid )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_BULLETINS, "Bulletins.$uid" );
   }

   function update_cache_bulletins_global( $dbgmsg )
   {
      DgsCache::store( $dbgmsg, CACHE_GRP_BULLETINS, "Bulletins.global", $GLOBALS['NOW'], 7*SECS_PER_DAY );
   }

} // end of 'Bulletin'
?>
