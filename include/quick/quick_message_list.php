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

require_once 'include/quick/quick_handler.php';
require_once 'include/quick/quick_message.php';
require_once 'include/std_functions.php';
require_once 'include/message_functions.php';


 /*!
  * \file quick_message_list.php
  *
  * \brief QuickHandler for returning list of message-objects.
  * \see specs/quick_suite.txt (3c)
  */

// see specs/quick_suite.txt (3c)
define('MSGLIST_COMMANDS', 'list');

define('MSGLIST_FILTER_FOLDERS', 'folders');
define('MSGLIST_FILTER_ANSWERS', 'answers');
define('MSGLIST_FILTERS', 'folders|answers');


 /*!
  * \class QuickHandlerMessageList
  *
  * \brief Quick-handler class for handling message-object.
  */
class QuickHandlerMessageList extends QuickHandler
{
   var $filter_folders; // [ folder-id, ... ] or empty-arr for all-folders
   var $filter_answers_mid;

   var $user_rows;
   var $folders;
   var $msg_result_rows;

   public function QuickHandlerMessageList( $quick_object )
   {
      parent::QuickHandler( $quick_object );

      $this->filter_folders = array();
      $this->filter_answers_mid = 0;

      $this->user_rows = array( $this->my_id => $GLOBALS['player_row'] );
      $this->folders = null;
      $this->msg_result_rows = null;
   }


   // ---------- Interface ----------------------------------------

   public function canHandle( $obj, $cmd ) // static
   {
      return ( $obj == QOBJ_MESSAGE ) && QuickHandler::matchRegex(MSGLIST_COMMANDS, $cmd);
   }

   public function parseURL()
   {
      parent::checkArgsUnknown();
      parent::parseFilters(MSGLIST_FILTERS);

      // filter-defaults
      if( !isset($this->filters[MSGLIST_FILTER_FOLDERS]) )
         $this->filters[MSGLIST_FILTER_FOLDERS] = ''; // default all-received
      if( !isset($this->filters[MSGLIST_FILTER_ANSWERS]) )
         $this->filters[MSGLIST_FILTER_ANSWERS] = ''; // no default (filter-disabled)
   }

   public function prepare()
   {
      global $player_row;
      $my_id = $player_row['ID'];

      // see specs/quick_suite.txt (3c)
      $dbgmsg = "QuickHandlerMessageList.prepare()";
      $this->checkCommand( $dbgmsg, MSGLIST_COMMANDS );
      $cmd = $this->quick_object->cmd;

      // init
      init_standard_folders();
      $this->folders = get_folders( $my_id, false );

      // check filters
      $this->prepare_filter_answers();
      $this->prepare_filter_folders();
      $this->check_allow_limit_all();

      $dbgmsg = "QuickHandlerMessageList.prepare()";

      // prepare command: list

      $qsql = DgsMessage::build_message_base_query( $my_id, /*full*/true, /*single*/false );
      $folder_str = implode(',', $this->filter_folders);
      if( $this->filter_answers_mid > 0 )
         $qsql->add_part( SQLP_WHERE, 'M.ReplyTo=' . $this->filter_answers_mid );
      if( count($this->filter_folders) )
         $qsql->add_part( SQLP_WHERE, "me.Folder_nr IN ($folder_str)" );
      if( $this->list_limit > 0 )
         $qsql->add_part( SQLP_LIMIT, sprintf('%s,%s', $this->list_offset, $this->list_limit + 1 )); //+1 to check for has-next
      $qsql->add_part( SQLP_ORDER, 'me.mid DESC' ); // creation-date (oldest first), equiv. msg-id
      $this->list_order = 'id-';

      // load messages
      $result = db_query( "$dbgmsg.find_msgs($folder_str)", $qsql->get_select() );
      $arr_msg = array();
      while( $row = mysql_fetch_assoc($result) )
      {
         $arr_msg[] = $row;
         $this->collect_msg_users( $row );
      }
      mysql_free_result($result);
      $this->msg_result_rows = $arr_msg;
   }//prepare

   private function prepare_filter_answers()
   {
      if( (string)$this->filters[MSGLIST_FILTER_ANSWERS] != '' )
      {
         $arg_mid = $this->filters[MSGLIST_FILTER_ANSWERS];
         if( !is_numeric($arg_mid) || $arg_mid <= 0 )
            error('invalid_args', "QuickHandlerMessageList.prepare.check.filter_answers($arg_mid)");

         $this->filter_answers_mid = (int)$arg_mid;
         $this->filters[MSGLIST_FILTER_FOLDERS] = FOLDER_ALL_RECEIVED; // enforce folders-filter
         $this->list_limit = $this->list_offset = 0; // unlimited
      }
   }//prepare_filter_answers

   // check and parse folders-filter into this->filter_folders
   private function prepare_filter_folders()
   {
      static $ARR_FOLDER_ALIASES = array( // alias => folder-id
            'ALL'    => FOLDER_ALL_RECEIVED,
            'MAIN'   => FOLDER_MAIN,
            'NEW'    => FOLDER_NEW,
            'REPLY'  => FOLDER_REPLY,
            'TRASH'  => FOLDER_DELETED,
            'SENT'   => FOLDER_SENT,
         );

      if( (string)$this->filters[MSGLIST_FILTER_FOLDERS] == '' ) // default
      {
         $this->filter_folders = build_folders_all_received( $this->folders );
         return;
      }
      else
         $this->filter_folders = array();

      $filter_arr = explode(',', trim($this->filters[MSGLIST_FILTER_FOLDERS]));
      if( count($filter_arr) == 0 )
         error('invalid_args', "QuickHandlerMessageList.prepare_filter_folders.miss_folders");

      $result = array();
      foreach( $filter_arr as $folder )
      {
         if( isset($this->folders[$folder]) )
            $result[] = (int)$folder;
         elseif( isset($ARR_FOLDER_ALIASES[$folder]) )
            $result[] = $ARR_FOLDER_ALIASES[$folder];
         else
            error('folder_not_found', "QuickHandlerMessageList.prepare_filter_folders.bad.folder($folder)");
      }

      $result = array_unique( $result );
      if( count($result) == 1 )
      {
         if( $result[0] == FOLDER_ALL_RECEIVED )
            $result = build_folders_all_received( $this->folders );
      }
      $this->filter_folders = $result;
   }//prepare_filter_folders

   private function check_allow_limit_all()
   {
      // allow returning ALL entries if folders are only NEW and/or REPLY
      if( @$_REQUEST[QOPT_LIMIT] === 'all' )
      {
         $allow_limit_all = $forbid_limit_all = false;
         foreach( $this->filter_folders as $folder_id )
         {
            if( $folder_id == FOLDER_NEW || $folder_id == FOLDER_REPLY )
               $allow_limit_all = true;
            else
               $forbid_limit_all = true;
         }
         if( $allow_limit_all && !$forbid_limit_all )
             $this->list_limit = $this->list_offset = 0;
      }
   }//check_allow_limit_all

   private function collect_msg_users( $msg_row )
   {
      $uid = (int)$msg_row['other_id'];
      if( $uid > 0 && !isset($this->user_rows[$uid]) )
      {
         $this->user_rows[$uid] = array(
               'ID' => $uid,
               'Handle'  => $msg_row['other_handle'],
               'Name'    => $msg_row['other_name'],
               'Country' => $msg_row['other_country'],
               'Rating2' => (float)$msg_row['other_rating'],
            );
      }
   }//collect_msg_users

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   public function process()
   {
      $out = array();

      if( is_array($this->msg_result_rows) )
      {
         foreach( $this->msg_result_rows as $msg_row )
         {
            $arr = array();
            $out[] = QuickHandlerMessage::fill_message_info( $this, $arr, $msg_row );
         }
      }

      $this->add_list( QOBJ_MESSAGE, $out );
   }//process

} // end of 'QuickHandlerMessageList'

?>
