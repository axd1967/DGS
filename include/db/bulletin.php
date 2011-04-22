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

$TranslateGroups[] = "Bulletin";

require_once 'include/globals.php';
require_once 'include/db_classes.php';
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
define('BULLETIN_CAT_PRIVATE_MSG',     'PRIV_MSG');
define('BULLETIN_CAT_SPAM',            'AD');
define('CHECK_BULLETIN_CATEGORY', 'MAINT|ADM_MSG|TOURNEY|TNEWS|PRIV_MSG|AD');

define('BULLETIN_STATUS_NEW',     'NEW');
//TODO define('BULLETIN_STATUS_PENDING', 'PENDING');
//TODO define('BULLETIN_STATUS_HIDDEN',  'HIDDEN');
define('BULLETIN_STATUS_SHOW',    'SHOW');
define('BULLETIN_STATUS_ARCHIVE', 'ARCHIVE');
define('BULLETIN_STATUS_DELETE',  'DELETE');
define('CHECK_BULLETIN_STATUS', 'NEW|SHOW|ARCHIVE|DELETE');
//TODO define('CHECK_BULLETIN_STATUS', 'NEW|PENDING|HIDDEN|SHOW|ARCHIVE|DELETE');

define('BULLETIN_TRG_UNSET',    'UNSET'); // needs assignment, not defined in DB (only application-default)
define('BULLETIN_TRG_ALL',      'ALL');
define('BULLETIN_TRG_TD',       'TD'); // tourney-director
define('BULLETIN_TRG_TP',       'TP'); // tourney-participant
define('BULLETIN_TRG_USERLIST', 'UL');
define('CHECK_BULLETIN_TARGET_TYPE', 'UNSET|ALL|TD|TP|UL');


 /*!
  * \class Bulletin
  *
  * \brief Class to manage Bulletin-table
  */

global $ENTITY_BULLETIN; //PHP5
$ENTITY_BULLETIN = new Entity( 'Bulletin',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_INT,  'ID', 'uid', 'tid', 'CountReads',
      FTYPE_ENUM, 'Category', 'Status', 'TargetType',
      FTYPE_TEXT, 'AdminNote', 'Subject', 'Text',
      FTYPE_DATE, 'PublishTime', 'ExpireTime', 'Lastchanged'
   );

class Bulletin
{
   var $ID;
   var $uid;
   var $Category;
   var $Status;
   var $TargetType;
   var $PublishTime;
   var $ExpireTime;
   var $tid;
   var $CountReads;
   var $AdminNote;
   var $Subject;
   var $Text;
   var $Lastchanged;

   // non-DB fields

   var $User; // User-object
   var $UserList; // [ uid, ...] for TargetType=UL
   var $UserListHandles; // [ Handle, =1234, ...] for TargetType=UL (with '='-prefix for numeric handles)
   var $UserListUserRefs; // [ [ ID/Handle/Name => val ], ... ]
   var $Tournament; // Tournament-object for $tid | null

   /*! \brief Constructs Bulletin-object with specified arguments. */
   function Bulletin( $id=0, $uid=0, $user=null, $category=BULLETIN_CAT_ADMIN_MSG,
            $status=BULLETIN_STATUS_NEW, $target_type=BULLETIN_TRG_UNSET, $publish_time=0,
            $expire_time=0, $tid=0, $count_reads=0, $admin_note='', $subject='', $text='',
            $lastchanged=0 )
   {
      $this->ID = (int)$id;
      $this->uid = (int)$uid;
      $this->setCategory( $category );
      $this->setStatus( $status );
      $this->setTargetType( $target_type );
      $this->PublishTime = (int)$publish_time;
      $this->ExpireTime = (int)$expire_time;
      $this->tid = (int)$tid;
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
      $data->set_value( 'Category', $this->Category );
      $data->set_value( 'Status', $this->Status );
      $data->set_value( 'TargetType', $this->TargetType );
      $data->set_value( 'PublishTime', $this->PublishTime );
      $data->set_value( 'ExpireTime', $this->ExpireTime );
      $data->set_value( 'tid', $this->tid );
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
            "SELECT BT.uid as ID, P.Handle, P.Name FROM BulletinTarget AS BT " .
               "INNER JOIN Players AS P ON P.ID=BT.uid " .
            "WHERE BT.bid={$this->ID} ORDER BY BT.uid" );
         while( $row = mysql_fetch_array( $result ) )
         {
            $this->UserList[] = $row['ID'];
            $this->UserListHandles[] = ( (is_numeric($row['Handle'])) ? '=' : '' ) . $row['Handle'];
            $this->UserListUserRefs[] = $row;
         }
         mysql_free_result($result);
      }
   }//loadUserList


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

   /*!
    * \brief Returns QuerySQL with restrictions to view bulletins to what user is allowed to view.
    * \param $is_admin true, if user is admin; false = normal user
    * \param $uid current user-id Players.ID
    * \param $count_new true to count new-bulletins (for main-menu); false for list-bulletins
    * \param $show_target_type BULLETIN_TRG_... = query restricted to specific target-type or '' for all target-types.
    *        'B_View'-field indicating if bulletin is shown or not, values: 0 = don't show, 1 = show entry
    */
   function build_view_query_sql( $is_admin, $uid, $count_new, $show_target_type='' )
   {
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
      else // show all target-types
      {
         $view_sql =
            "CASE B.TargetType " .
               "WHEN '".BULLETIN_TRG_ALL."' THEN 1 " .
               "WHEN '".BULLETIN_TRG_TP."' THEN IF(BTP.tid IS NULL,0,1) " .
               "WHEN '".BULLETIN_TRG_TD."' THEN IF(BTD.tid IS NULL,IF(BTN.ID IS NULL,0,1),1) " .
               "WHEN '".BULLETIN_TRG_USERLIST."' THEN IF(BT.bid IS NULL,0,1) " .
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
               "ON BT.bid=B.ID AND BT.uid=$uid AND B.TargetType='".BULLETIN_TRG_USERLIST."'" );
      }

      if( !$count_new && !$is_admin )
         $qsql->add_part( SQLP_HAVING, "B_View > 0" );

      return $qsql;
   }//build_view_query_sql

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
            @$row['X_PublishTime'],
            @$row['X_ExpireTime'],
            @$row['tid'],
            @$row['CountReads'],
            @$row['AdminNote'],
            @$row['Subject'],
            @$row['Text'],
            @$row['X_Lastchanged']
         );
      return $bull;
   }

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

   /*! \brief Returns new Bulletin-object for user. */
   function new_bulletin( $is_admin )
   {
      global $player_row;
      $uid = (int)@$player_row['ID'];
      if( !is_numeric($uid) || $uid <= GUESTS_ID_MAX || !$is_admin )
         error('invalid_args', "Bulletin.new_bulletin($uid,$is_admin)");

      // for admin
      $user = new User( $uid, @$player_row['Name'], @$player_row['Handle'] );
      $bulletin = new Bulletin( 0, $uid, $user );
      $bulletin->setCategory( BULLETIN_CAT_ADMIN_MSG );
      $bulletin->PublishTime = $GLOBALS['NOW'];
      $bulletin->ExpireTime = $bulletin->PublishTime + 30 * SECS_PER_DAY; // default +30d

      return $bulletin;
   }//new_bulletin

   function mark_bulletin_as_read( $bid )
   {
      global $player_row;
      if( !is_numeric($bid) || $bid <= 0 )
         error('invalid_args', "Bulletin:mark_bulletin_as_read.check.bid($bid)");
      $uid = (int)$player_row['ID'];

      ta_begin();
      {//HOT-section to mark bulletin as read
         db_query( "Bulletin::mark_bulletin_as_read($bid,$uid)",
            "INSERT IGNORE BulletinRead (bid,uid) " .
            "SELECT ID, $uid FROM Bulletin WHERE ID=$bid AND Status='".BULLETIN_STATUS_SHOW."' LIMIT 1" );

         if( mysql_affected_rows() > 0 )
         {
            $bulletin = Bulletin::load_bulletin( $bid, /*with_player*/false );
            if( !is_null($bulletin) )
            {
               Bulletin::update_count_players( "Bulletin::mark_bulletin_as_read($bid,$uid)",
                  BULLETIN_STATUS_SHOW, $bulletin->TargetType, $uid );
               Bulletin::update_count_bulletin_new( "Bulletin::mark_bulletin_as_read($bid,$uid)", COUNTNEW_RECALC );

               db_query( "Bulletin::mark_bulletin_as_read.inc_read($bid)",
                  "UPDATE Bulletin SET CountReads=CountReads+1 WHERE ID=$bid LIMIT 1" );
            }
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

   /*! \brief Change status of expired bulletins. */
   function process_expired_bulletins()
   {
      global $NOW;
      foreach( array( BULLETIN_TRG_ALL ) as $target_type )
      {
         db_query( "Bulletin::process_expired_bulletins.upd_bulletin($target_type)",
            "UPDATE Bulletin SET " .
               "Status='".BULLETIN_STATUS_ARCHIVE."', " .
               "AdminNote=TRIM(CONCAT('EXPIRED ',AdminNote)) " .
            "WHERE Status='".BULLETIN_STATUS_SHOW."' AND TargetType='$target_type' AND " .
               "ExpireTime > 0 AND ExpireTime < FROM_UNIXTIME($NOW)" );

         if( mysql_affected_rows() > 0 )
            Bulletin::update_count_players( "Bulletin::process_expired_bulletins",
               BULLETIN_STATUS_SHOW, $target_type );
      }
   }//process_expire_bulletins

   /*!
    * \brief Updates or resets Players.CountBulletinNew for current user.
    * \param $diff null|omit to reset to -1 (=recalc later); COUNTNEW_RECALC to recalc now;
    *        otherwise increase or decrease counter
    */
   function update_count_bulletin_new( $dbgmsg, $diff=null )
   {
      global $player_row;

      $uid = (int)@$player_row['ID'];
      $dbgmsg .= "update_count_bulletin_new($uid,$diff)";
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
         $count_new = count_bulletin_new( $uid );
         $player_row['CountBulletinNew'] = $count_new;
         db_query( "$dbgmsg.recalc",
            "UPDATE Players SET CountBulletinNew=$count_new WHERE ID='$uid' LIMIT 1" );
      }
   }//update_count_bulletin_new

   /*!
    * \brief Updates Players.CountBulletinNew according for given Bulletin-data (only updated on SHOW-status).
    * \param $status BULLETIN_STATUS_...
    * \param $target_type BULLETIN_TRG_...
    * \param $uid restrict update to given user-id; can be uid-array; 0 otherwise
    * \return true, if update required; false otherwise
    *
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    *
    * \note to avoid updating ALL players, restrict update to players with last-access
    *       up to session-expire, and login.php resets counter too on session-expire
    * \see also count_bulletin_new() in 'include/std_functions.php'
    */
   function update_count_players( $dbgmsg, $status, $target_type, $uid=0 )
   {
      global $NOW;

      if( $status != BULLETIN_STATUS_SHOW )
         return false;

      $dbgmsg .= "Bulletin::update_count_players($status,$target_type)";
      if( is_numeric($uid) && $uid > 0 )
         $qpart_uid = " AND ID=$uid LIMIT 1";
      elseif( is_array($uid) && count($uid) > 0 )
         $qpart_uid = " AND ID IN (".implode(',', $uid).") LIMIT " . count($uid);
      else
         $qpart_uid = '';

      if( $target_type != BULLETIN_TRG_UNSET && preg_match( "/^(".CHECK_BULLETIN_TARGET_TYPE.")$/", $target_type ) )
      {
         $upd_time = $NOW - SESSION_DURATION;
         db_query( "$dbgmsg.upd_all",
            "UPDATE Players SET CountBulletinNew=-1 WHERE Lastaccess >= FROM_UNIXTIME($upd_time) $qpart_uid" );
      }
      else
         error('invalid_args', "$dbgmsg.check.target_type($target_type)");

      return true;
   }//update_count_players

   /*! \brief Deletes all existing BulletinRead-entries for given bulletin-id. */
   function reset_bulletin_read( $bid )
   {
      db_query( "Bulletin::reset_bulletin_read($bid)",
         "DELETE FROM BulletinRead WHERE bid=$bid" );
   }//reset_bulletin_read

} // end of 'Bulletin'
?>
