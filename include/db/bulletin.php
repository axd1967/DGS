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
//TODO define('BULLETIN_CAT_TOURNAMENT_NEWS', 'TNEWS');
define('BULLETIN_CAT_PRIVATE_MSG',     'PRIV_MSG');
define('BULLETIN_CAT_SPAM',            'AD');
define('CHECK_BULLETIN_CATEGORY', 'MAINT|ADM_MSG|TOURNEY|PRIV_MSG|AD');
//TODO define('CHECK_BULLETIN_CATEGORY', 'MAINT|ADM_MSG|TOURNEY|TNEWS|PRIV_MSG|AD');

define('BULLETIN_STATUS_NEW',     'NEW');
//TODO define('BULLETIN_STATUS_PENDING', 'PENDING');
//TODO define('BULLETIN_STATUS_HIDDEN',  'HIDDEN');
define('BULLETIN_STATUS_SHOW',    'SHOW');
define('BULLETIN_STATUS_ARCHIVE', 'ARCHIVE');
define('BULLETIN_STATUS_DELETE',  'DELETE');
define('CHECK_BULLETIN_STATUS', 'NEW|SHOW|ARCHIVE|DELETE');
//TODO define('CHECK_BULLETIN_STATUS', 'NEW|PENDING|HIDDEN|SHOW|ARCHIVE|DELETE');

define('BULLETIN_TRG_UNSET', 'UNSET'); // needs assignment
define('BULLETIN_TRG_ALL', 'ALL');
//TODO define('BULLETIN_TRG_TD',  'TD'); // tourney-director
//TODO define('BULLETIN_TRG_TP',  'TP'); // tourney-participant
//TODO define('BULLETIN_TRG_UL',  'UL'); // user-list
define('CHECK_BULLETIN_TARGET_TYPE', 'UNSET|ALL');
//TODO define('CHECK_BULLETIN_TARGET_TYPE', 'UNSET|ALL|TD|TP|UL');


 /*!
  * \class Bulletin
  *
  * \brief Class to manage Bulletin-table
  */

// lazy-init in Bulletin::get..Text()-funcs
global $ARR_GLOBALS_BULLETIN; //PHP5
$ARR_GLOBALS_BULLETIN = array();

global $ENTITY_BULLETIN; //PHP5
$ENTITY_BULLETIN = new Entity( 'Bulletin',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_INT,  'ID', 'uid', 'tid',
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
   var $AdminNote;
   var $Subject;
   var $Text;
   var $Lastchanged;

   // non-DB fields

   var $User; // User-object

   /*! \brief Constructs Bulletin-object with specified arguments. */
   function Bulletin( $id=0, $uid=0, $user=null, $category=BULLETIN_CAT_ADMIN_MSG,
            $status=BULLETIN_STATUS_NEW, $target_type=BULLETIN_TRG_UNSET, $publish_time=0,
            $expire_time=0, $tid=0, $admin_note='', $subject='', $text='', $lastchanged=0 )
   {
      $this->ID = (int)$id;
      $this->uid = (int)$uid;
      $this->setCategory( $category );
      $this->setStatus( $status );
      $this->setTargetType( $target_type );
      $this->PublishTime = (int)$publish_time;
      $this->ExpireTime = (int)$expire_time;
      $this->tid = (int)$tid;
      $this->AdminNote = $admin_note;
      $this->Subject = $subject;
      $this->Text = $text;
      $this->Lastchanged = (int)$lastchanged;
      // non-DB fields
      $this->User = (is_a($user, 'User')) ? $user : new User( $this->uid );
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
      $data->set_value( 'AdminNote', $this->AdminNote );
      $data->set_value( 'Subject', $this->Subject );
      $data->set_value( 'Text', $this->Text );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      return $data;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of Bulletin-objects for given game-id. */
   function build_query_sql( $bid=0 )
   {
      $qsql = $GLOBALS['ENTITY_BULLETIN']->newQuerySQL('B');
      $qsql->add_part( SQLP_FIELDS,
         'B.uid AS BP_ID',
         'BP.Name AS BP_Name',
         'BP.Handle AS BP_Handle' );
      $qsql->add_part( SQLP_FROM,
         'INNER JOIN Players AS BP ON BP.ID=B.uid' );
      if( $bid > 0 )
         $qsql->add_part( SQLP_WHERE, "B.ID=$bid" );
      return $qsql;
   }

   /*!
    * \brief Returns QuerySQL with restrictions to view bulletins to what user is allowed to view.
    * \param $is_admin true, if user is admin; false = normal user
    */
   function build_view_query_sql( $is_admin )
   {
      $qsql = new QuerySQL();
      if( !$is_admin ) // hide some bulletins
      {
         $qsql->add_part( SQLP_WHERE,
            "B.Status IN ('".BULLETIN_STATUS_SHOW."','".BULLETIN_STATUS_ARCHIVE."')" );
      }
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
            @$row['X_PublishTime'],
            @$row['X_ExpireTime'],
            @$row['tid'],
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
   function load_bulletin( $bid )
   {
      $qsql = Bulletin::build_query_sql( $bid );
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

   /*! \brief Returns category-text or all category-texts (if arg=null). */
   function getCategoryText( $category=null )
   {
      global $ARR_GLOBALS_BULLETIN;

      // lazy-init of texts
      $key = 'CAT';
      if( !isset($ARR_GLOBALS_BULLETIN[$key]) )
      {
         $arr = array();
         $arr[BULLETIN_CAT_MAINT]            = T_('Maintenance#B_cat');
         $arr[BULLETIN_CAT_ADMIN_MSG]        = T_('Admin Announcement#B_cat');
         $arr[BULLETIN_CAT_TOURNAMENT]       = T_('Tournament Announcement#B_cat');
         //TODO $arr[BULLETIN_CAT_TOURNAMENT_NEWS]  = T_('Tournament News#B_cat');
         $arr[BULLETIN_CAT_PRIVATE_MSG]      = T_('Private Announcement#B_cat');
         $arr[BULLETIN_CAT_SPAM]             = T_('Advertisement#B_cat');
         $ARR_GLOBALS_BULLETIN[$key] = $arr;
      }

      if( is_null($category) )
         return $ARR_GLOBALS_BULLETIN[$key];

      if( !isset($ARR_GLOBALS_BULLETIN[$key][$category]) )
         error('invalid_args', "Bulletin.getCategoryText($category,$key)");
      return $ARR_GLOBALS_BULLETIN[$key][$category];
   }

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   function getStatusText( $status=null )
   {
      global $ARR_GLOBALS_BULLETIN;

      // lazy-init of texts
      $key = 'STATUS';
      if( !isset($ARR_GLOBALS_BULLETIN[$key]) )
      {
         $arr = array();
         $arr[BULLETIN_STATUS_NEW]     = T_('New#B_status');
         //TODO $arr[BULLETIN_STATUS_PENDING] = T_('Pending#B_status');
         //TODO $arr[BULLETIN_STATUS_HIDDEN]  = T_('Hidden#B_status');
         $arr[BULLETIN_STATUS_SHOW]    = T_('Show#B_status');
         $arr[BULLETIN_STATUS_ARCHIVE] = T_('Archive#B_status');
         $arr[BULLETIN_STATUS_DELETE]  = T_('Delete#B_status');
         $ARR_GLOBALS_BULLETIN[$key] = $arr;
      }

      if( is_null($status) )
         return $ARR_GLOBALS_BULLETIN[$key];

      if( !isset($ARR_GLOBALS_BULLETIN[$key][$status]) )
         error('invalid_args', "Bulletin.getStatusText($status,$key)");
      return $ARR_GLOBALS_BULLETIN[$key][$status];
   }

   /*! \brief Returns target-type-text or all target-type-texts (if arg=null). */
   function getTargetTypeText( $trg_type=null )
   {
      global $ARR_GLOBALS_BULLETIN;

      // lazy-init of texts
      $key = 'TRGTYPE';
      if( !isset($ARR_GLOBALS_BULLETIN[$key]) )
      {
         $arr = array();
         $arr[BULLETIN_TRG_UNSET] = T_('Unset#B_trg');
         $arr[BULLETIN_TRG_ALL]  = T_('All#B_trg');
         //TODO $arr[BULLETIN_TRG_TD]   = T_('T-Dir#B_trg');
         //TODO $arr[BULLETIN_TRG_TP]   = T_('T-Part#B_trg');
         //TODO $arr[BULLETIN_TRG_UL]   = T_('UserList#B_trg');
         $ARR_GLOBALS_BULLETIN[$key] = $arr;
      }

      if( is_null($trg_type) )
         return $ARR_GLOBALS_BULLETIN[$key];

      if( !isset($ARR_GLOBALS_BULLETIN[$key][$trg_type]) )
         error('invalid_args', "Bulletin.getTargetTypeText($trg_type,$key)");
      return $ARR_GLOBALS_BULLETIN[$key][$trg_type];
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
      $bulletin->PublishTime = $GLOBALS['NOW'];
      $bulletin->setCategory( BULLETIN_CAT_ADMIN_MSG );

      return $bulletin;
   }//new_bulletin

   /*! \brief Prints formatted Bulletin with CSS-style with author, publish-time, text. */
   function build_view_bulletin( $bulletin )
   {
      $category = Bulletin::getCategoryText($bulletin->Category);
      $title = make_html_safe($bulletin->Subject, true);
      $text = make_html_safe($bulletin->Text, true);
      $publish_text = sprintf( T_('[%s] by %s#bulletin'),
         date(DATE_FMT2, $bulletin->PublishTime), $bulletin->User->user_reference() );
      return
         "<div class=\"Bulletin\">\n" .
            "<div class=\"Category\">$category:</div>" .
            "<div class=\"PublishTime\">$publish_text</div>" .
            "<div class=\"Title\">$title</div>" .
            "<div class=\"Text\">$text</div>" .
         "</div>\n";
   }

} // end of 'Bulletin'
?>
