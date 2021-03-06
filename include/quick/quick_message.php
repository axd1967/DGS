<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/quick/quick_folder.php';
require_once 'include/db/game_invitation.php';
require_once 'include/std_functions.php';
require_once 'include/message_functions.php';
require_once 'include/classlib_user.php';

 /*!
  * \file quick_message.php
  *
  * \brief QuickHandler for message-object.
  * \see specs/quick_suite.txt (3c)
  */

// see specs/quick_suite.txt (3c)
// mid=<MESSAGE_ID>
define('MESSAGEOPT_MID', 'mid');
define('MESSAGEOPT_OTHER_UID', 'ouid');
define('MESSAGEOPT_OTHER_HANDLE', 'ouser');
define('MESSAGEOPT_FOLDER', 'folder');
define('MESSAGEOPT_SUBJECT', 'subj');
define('MESSAGEOPT_MESSAGE', 'msg');
define('QMESSAGE_OPTIONS', 'mid|ouid|ouser|folder|subj|msg');

define('MESSAGECMD_SEND_MSG', 'send_msg');
define('MESSAGECMD_MOVE_MESSAGE', 'move_msg');
define('MESSAGECMD_DELETE_MESSAGE', 'delete_msg');
define('MESSAGECMD_ACCEPT_INVITATION', 'accept_inv');
define('MESSAGECMD_DECLINE_INVITATION', 'decline_inv');
define('MESSAGE_COMMANDS', 'info|move_msg|delete_msg|send_msg|accept_inv|decline_inv');


 /*!
  * \class QuickHandlerMessage
  *
  * \brief Quick-handler class for handling message-object.
  */
class QuickHandlerMessage extends QuickHandler
{
   private $mid = 0;
   private $other_uid = 0; // for bulk-msg
   private $folder_id = 0;

   private $msg_row = null;
   private $user_rows = null;
   private $folder = null; //arr
   private $folders = null; //arr; 'public' needed for var-scope
   private $arr_msg_id = null;


   // ---------- Interface ----------------------------------------

   public static function canHandle( $obj, $cmd ) // static
   {
      return ( $obj == QOBJ_MESSAGE ) && QuickHandler::matchRegex(MESSAGE_COMMANDS, $cmd);
   }

   public function parseURL()
   {
      parent::checkArgsUnknown(QMESSAGE_OPTIONS);
      $this->mid = get_request_arg(MESSAGEOPT_MID);
      $this->other_uid = (int)get_request_arg(MESSAGEOPT_OTHER_UID);
      $this->other_handle = trim(get_request_arg(MESSAGEOPT_OTHER_HANDLE));
      $this->folder_id = (int)get_request_arg(MESSAGEOPT_FOLDER);
   }

   public function prepare()
   {
      $my_id = $this->my_id;

      // see specs/quick_suite.txt (3c)
      $dbgmsg = "QuickHandlerMessage.prepare($my_id,{$this->mid})";
      $this->checkCommand( $dbgmsg, MESSAGE_COMMANDS );
      $cmd = $this->quick_object->cmd;

      // check mid
      if ( !is_numeric($this->other_uid) || $this->other_uid < 0 )
         error('invalid_args', "$dbgmsg.bad_uid.oid({$this->other_uid})");
      if ( !is_numeric($this->folder_id) || $this->folder_id < FOLDER_ALL_RECEIVED )
         error('invalid_args', "$dbgmsg.bad_folder({$this->folder_id})");
      $mid = $this->mid;

      // prepare command: info

      if ( $cmd == QCMD_INFO )
      {
         if ( $mid == 0 )
            error('invalid_args', "$dbgmsg.bad_message.miss_mid");

         /* see also the note about MessageCorrespondents.mid==0 in message_list_query() */
         $this->msg_row = DgsMessage::load_message( $dbgmsg, $mid, $my_id, $this->other_uid, /*full*/true );

         if ( $this->is_with_option(QWITH_USER_ID) )
            $this->user_rows = User::load_quick_userinfo( array(
               $my_id, (int)$this->msg_row['other_id'] ));

         if ( $this->folder_id > 0 ) // check folder to move message into after "reading"
         {
            $this->init_folders( $this->my_id );
            if ( !isset($this->folders[$this->folder_id]) )
               error('invalid_args', "$dbgmsg.bad_folder({$this->folder_id})");
            if ( $this->msg_row['Replied'] == 'M' && $this->folder_id != FOLDER_REPLY )
               $this->folder_id = FOLDER_REPLY; // correct to reply-folder if msg needs reply
         }
         $this->folder = $this->load_folder( $my_id, $this->msg_row['Folder_nr'] );
      }
      elseif ( $cmd == MESSAGECMD_MOVE_MESSAGE || $cmd == MESSAGECMD_DELETE_MESSAGE )
      {
         if ( !preg_match("/^(\\d+)(,\\d+)*$/", $this->mid) )
            error('invalid_args', "$dbgmsg.bad_message_id");

         if ( $cmd == MESSAGECMD_MOVE_MESSAGE )
         {
            $this->init_folders( $this->my_id );
            if ( $this->folder_id == 0 )
               error('invalid_args', "$dbgmsg.miss_folder");
            if ( $this->folder_id > FOLDER_ALL_RECEIVED && !isset($this->folders[$this->folder_id]) )
               error('folder_not_found', "$dbgmsg.bad_folder({$this->folder_id})");
         }

         $this->arr_msg_id = explode(',', $this->mid);
         foreach ( $this->arr_msg_id as $msg_id )
         {
            $msg_row = DgsMessage::load_message( $dbgmsg, $msg_id, $my_id, 0, /*full*/false ); // msg exists and is mine
            if ( $msg_row['Replied'] == 'M' )
               error('invalid_args', "$dbgmsg.need_reply($msg_id)");
            if ( $msg_row['Folder_nr'] == FOLDER_DESTROYED )
               error('invalid_args', "$dbgmsg.already_destroyed($msg_id)");
         }
      }
      elseif ( $cmd == MESSAGECMD_SEND_MSG || $cmd == MESSAGECMD_ACCEPT_INVITATION || $cmd == MESSAGECMD_DECLINE_INVITATION )
      {
         if ( $cmd == MESSAGECMD_SEND_MSG )
         {
            // mid is optional (or mid=0 for new msg), if present then must be numeric>0 for reply
            if ( $this->mid && ( !is_numeric($this->mid) || $this->mid < 0 ) )
               error('invalid_args', "$dbgmsg.bad_message_id");
         }
         else // accept|decline-invitation
         {
            if ( !is_numeric($this->mid) || $this->mid < 0 )
               error('invalid_args', "$dbgmsg.bad_message_id");
         }

         if ( $this->mid > 0 ) // reply
         {
            if ( $this->other_uid != 0 || (string)$this->other_handle != '' )
               error('reply_invalid', "$dbgmsg.implicit_recipient({$this->other_uid},{$this->other_handle})");
         }
         else // new message
         {
            if ( $cmd == MESSAGECMD_ACCEPT_INVITATION || $cmd == MESSAGECMD_DECLINE_INVITATION )
               error('invalid_args', "$dbgmsg.miss_mid");
            if ( $this->other_uid > 0 && (string)$this->other_handle == '' )
               $this->other_handle = $this->load_user_handle( $dbgmsg, $this->other_uid );
            if ( $this->other_uid == 0 && (string)$this->other_handle == '' )
               error('receiver_not_found', $dbgmsg);
         }

         $this->init_folders( $this->my_id );
         if ( $this->folder_id > FOLDER_ALL_RECEIVED && !isset($this->folders[$this->folder_id]) )
            error('folder_not_found', "$dbgmsg.bad_folder({$this->folder_id})");
         if ( $this->folder_id == FOLDER_NEW || $this->folder_id == FOLDER_SENT )
            error('folder_forbidden', "$dbgmsg.forbidden_target_folder({$this->folder_id})");

         if ( $this->mid > 0 ) // check reply
         {
            // check that user is indeed a receiver of reply-msg -> otherwise unknown-msg
            $msg_row = DgsMessage::load_message( $dbgmsg, $mid, $my_id, 0, /*full*/false );
            $this->msg_row = $msg_row;

            if ( $msg_row['Sender'] == 'M' ) // reply to message from myself forbidden
               error('reply_invalid', "$dbgmsg.reply_to_myself_forbidden");
            if ( $msg_row['Sender'] != 'N' || !($msg_row['other_id'] > 0) ) // can not reply
               error('reply_invalid', "$dbgmsg.message_can_not_be_replied");

            if ( $cmd == MESSAGECMD_SEND_MSG )
            {
               if ( $msg_row['Type'] != MSGTYPE_NORMAL ) // only reply to NORMAL-messages
                  error('reply_invalid', "$dbgmsg.only_normal({$msg_row['Type']})");
            }
            elseif ( $cmd == MESSAGECMD_ACCEPT_INVITATION || $cmd == MESSAGECMD_DECLINE_INVITATION )
            {
               if ( $msg_row['Type'] != MSGTYPE_INVITATION )
                  error('reply_invalid', "$dbgmsg.no_invitation({$msg_row['Type']})");
            }

            $this->other_uid = $msg_row['other_id'];
            $this->other_handle = $this->load_user_handle( $dbgmsg, $this->other_uid );
         }
      }
   }//prepare

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   public function process()
   {
      $cmd = $this->quick_object->cmd;
      if ( $cmd == QCMD_INFO )
         $this->process_cmd_info();
      elseif ( $cmd == MESSAGECMD_MOVE_MESSAGE )
         $this->process_cmd_move_msg();
      elseif ( $cmd == MESSAGECMD_DELETE_MESSAGE )
         $this->process_cmd_delete_msg();
      elseif ( $cmd == MESSAGECMD_SEND_MSG || $cmd == MESSAGECMD_ACCEPT_INVITATION || $cmd == MESSAGECMD_DECLINE_INVITATION )
         $this->process_cmd_send_msg();
   }//process

   private function process_cmd_info()
   {
      self::fill_message_info( $this, $this->quick_object->result, $this->msg_row, $this->user_rows, $this->folders );

      // move message into target folder (if given)
      if ( $this->folder_id > 0 )
      {
         $my_id = $this->my_id;
         $mid = (int)$this->msg_row['ID'];

         ta_begin();
         {//HOT-section to move message into target folder
            $updated = DgsMessage::update_message_folder( $mid, $my_id, /*Sender*/null, $this->folder_id, /*die*/false );
            if ( $updated && ($this->msg_row['Folder_nr'] == FOLDER_NEW || $this->folder_id == FOLDER_NEW) )
               update_count_message_new( 'QuickHandlerMessage.process_cmd_info.upd_msg_folder', $my_id, COUNTNEW_RECALC );
         }
         ta_end();
      }
   }//process_cmd_info

   private function process_cmd_move_msg()
   {
      $moved_count = change_folders( $this->my_id, $this->folders, $this->arr_msg_id, $this->folder_id,
         /*curr-fold*/false, /*need-reply*/false, /*quick*/true );
      $this->addResultKey( 'success_count', $moved_count );
      $this->addResultKey( 'failure_count', count($this->arr_msg_id) - $moved_count );
   }

   private function process_cmd_delete_msg()
   {
      $moved_count = change_folders( $this->my_id, /*folders*/null, $this->arr_msg_id, FOLDER_DESTROYED,
         /*curr-fold*/false, /*need-reply*/false, /*quick*/true );
      $this->addResultKey( 'success_count', $moved_count );
      $this->addResultKey( 'failure_count', count($this->arr_msg_id) - $moved_count );
   }

   /*! \brief send_msg | accept_inv | decline_inv */
   private function process_cmd_send_msg()
   {
      $action = 'send_msg';
      $subject = trim(@$_REQUEST[MESSAGEOPT_SUBJECT]); // mandatory only for send_msg
      $gid = 0;

      $cmd = $this->quick_object->cmd;
      if ( $cmd == MESSAGECMD_ACCEPT_INVITATION )
      {
         $action = 'accept_inv';
         $subject = 1; // non-empty
         $gid = $this->msg_row['Game_ID'];
      }
      elseif ( $cmd == MESSAGECMD_DECLINE_INVITATION )
      {
         $action = 'decline_inv';
         $subject = 1; // non-empty
         $gid = $this->msg_row['Game_ID'];
      }

      $msg_control = new MessageControl( $this->folders, /*allow-bulk*/false );
      $input = array(
            'action'       => $action,
            'senderid'     => $this->my_id,
            'folder'       => $this->folder_id,
            'reply'        => (int)$this->mid,
            'mpgid'        => 0,
            'subject'      => $subject,
            'message'      => trim(@$_REQUEST[MESSAGEOPT_MESSAGE]),
            'gid'          => $gid,
            'disputegid'   => 0,
         );
      $result = $msg_control->handle_send_message( $this->other_handle, MSGTYPE_NORMAL, $input );
      if ( is_array($result) && count($result) )
      {
         $this->setError('invalid_action');
         $error_texts = $result;
      }
      else
         $error_texts = array();

      $this->addResultKey('error_texts', $error_texts);
   }//process_cmd_send_msg

   private function init_folders( $uid )
   {
      init_standard_folders();
      $this->folders = get_folders( $uid );
   }

   private function load_folder( $uid, $folder_id )
   {
      if ( !$this->is_with_option(QWITH_FOLDER) )
         return null;

      if ( $folder_id == FOLDER_DESTROYED ) // invisible-folder
         $arr = array( T_('Destroyed#folder'), 'FF88EE00', '000000' );
      elseif ( $folder_id == FOLDER_ALL_RECEIVED ) // pseudo folder
         $arr = array( T_('All Received'), '00000000', '000000' );
      else
      {
         init_standard_folders();
         $arr = get_folders( $uid, true, $folder_id );
         if ( is_array($arr) && isset($arr[$folder_id]) )
            $arr = $arr[$folder_id];
         if ( !is_array($arr) )
            $arr = array( T_('Unknown#folder'), '00000000', '000000' );
      }
      return $arr;
   }//load_folder


   // fills $out-array and return it, filled with message-data from row
   // NOTE: $user_rows, $folders must be initialized; or else be null
   public static function fill_message_info( $quickh, &$out, $row, $user_rows, $folders )
   {
      global $player_row;

      $my_id = $player_row['ID'];
      $other_uid = (int)$row['other_id'];
      switch ( $row['Sender'] ) // also see get_message_directions()
      {
         case 'M': $uid_from = $my_id; $uid_to = $my_id; break; // myself
         case 'S': $uid_from = 0;      $uid_to = $my_id; break; // system
         case 'Y': $uid_from = $my_id; $uid_to = $other_uid; break;
         case 'N': $uid_from = $other_uid; $uid_to = $my_id; break;
         default:  $uid_from = $uid_to = 0; break;
      }
      $gid = (int)$row['Game_ID'];
      $mid = (int)$row['ID'];
      $folder_id = (int)$row['Folder_nr'];

      $out['id'] = $mid;
      $out['user_from'] = $quickh->build_obj_user($uid_from, @$user_rows[$uid_from], '', 'country,rating');
      $out['user_to']   = $quickh->build_obj_user($uid_to, @$user_rows[$uid_to], '', 'country,rating');
      $out['type'] = strtoupper($row['Type']);
      $out['flags'] = self::convertMessageFlags($row['Flags']);
      $out['folder'] = QuickHandlerFolder::build_obj_folder(
            $folder_id, @$folders[$folder_id], $quickh->is_with_option(QWITH_FOLDER) );
      $out['created_at'] = QuickHandler::formatDate(@$row['X_Time']);
      $out['thread'] = (int)$row['Thread'];
      $out['level'] = (int)$row['Level'];
      $out['message_prev'] = (int)$row['ReplyTo'];
      $out['message_hasnext'] = ($row['X_Flow'] & FLOW_ANSWERED) ? 1 : 0;
      $out['can_reply'] = ($row['Sender'] == 'N' && $other_uid>0) ? 1 : 0;
      $out['need_reply'] = self::convertMessageReplyStatus($row['Replied']);
      $out['game_id'] = $gid;
      $out['subject'] = $row['Subject'];
      $out['text'] = $row['Text'];

      if ( $row['Type'] == MSGTYPE_INVITATION )
      {
         $game_status = @$row['Status'];
         if ( is_null($game_status) )
            $game_status = '';
         $out['game_status'] = $game_status;

         if ( $game_status == GAME_STATUS_INVITED )
         {
            $game_inv = GameInvitation::load_game_invitation( $gid, $row['ToMove_ID'] );
            if ( is_null($game_inv) )
               error('invite_bad_gamesetup', "QuickHandlerMessage.fill_message_info.miss_game_inv($gid,{$row['ToMove_ID']})");

            $gopp_row = array();
            GameHelper::count_games_with_opponent( $my_id, $other_uid, $gopp_row );

            $time_limit = TimeFormat::echo_time_limit(
                  $row['Maintime'], $row['Byotype'], $row['Byotime'], $row['Byoperiods'],
                  TIMEFMT_QUICK|TIMEFMT_ENGL|TIMEFMT_SHORT|TIMEFMT_ADDTYPE);

            // NOTE: players have a rating if an invitation exists
            $inv_gs = GameSetup::new_from_game_invitation( $row, $game_inv );
            $gs_calc = new GameSettingsCalculator( $inv_gs, $player_row['Rating2'], $row['other_rating'] );
            $gs_calc->calculate_settings( $my_id );

            $out['game_settings'] = array(
                  'game_type' => GAMETYPE_GO,
                  'game_players' => '1:1',
                  'handicap_type' => $game_inv->Handicaptype,
                  'shape_id' => (int)$row['ShapeID'],
                  'shape_snapshot' => $row['ShapeSnapshot'],

                  'rated' => ( ($row['Rated'] == 'N') ? 0 : 1 ),
                  'ruleset' => strtoupper($row['Ruleset']),
                  'size' => (int)$row['Size'],
                  'komi' => (float)$row['Komi'],
                  'handicap' =>  (int)$row['Handicap'],
                  'handicap_mode' => ( ($row['StdHandicap'] == 'Y') ? 'STD' : 'FREE' ),

                  'adjust_handicap' => (int)$game_inv->AdjHandicap,
                  'min_handicap' => (int)$game_inv->MinHandicap,
                  'max_handicap' => (int)$game_inv->MaxHandicap,
                  'adjust_komi' => (float)$game_inv->AdjKomi,
                  'jigo_mode' => $game_inv->JigoMode,

                  'time_weekend_clock' => ( ($row['WeekendClock'] == 'Y') ? 1 : 0 ),
                  'time_mode' => strtoupper($row['Byotype']),
                  'time_limit' => $time_limit,
                  'time_main' => $row['Maintime'],
                  'time_byo' => $row['Byotime'],
                  'time_periods' => $row['Byoperiods'],

                  'own_started_games' => (int)$player_row['Running'],
                  'opp_started_games' => (int)$gopp_row['Running'],
                  'opp_finished_games' => (int)$gopp_row['Finished'],
                  'calc_type' => $gs_calc->calc_type,
                  'calc_color' => $gs_calc->calc_color,
                  'calc_handicap' => $gs_calc->calc_handicap,
                  'calc_komi' => $gs_calc->calc_komi,
               );
         }
      }//invitation-info

      return $out;
   }//fill_message_info

   private static function convertMessageFlags( $flags )
   {
      $out = array();
      if ( $flags & MSGFLAG_BULK )
         $out[] = 'BULK';
      return implode(',', $out);
   }

   private static function convertMessageReplyStatus( $replied )
   {
      if ( $replied == 'M' )
         return 2; // need reply
      elseif ( $replied == 'Y' )
         return 1; // has replied
      else
         return 0; // no reply needed
   }

   private static function load_user_handle( $dbgmsg, $uid )
   {
      $arr = User::load_quick_userinfo( array( $uid ) );
      if ( count($arr) == 0 || !isset($arr[$uid]) )
         error('receiver_not_found', "$dbgmsg.miss_recipient($uid)");
      return $arr[$uid]['Handle'];
   }

} // end of 'QuickHandlerMessage'

?>
