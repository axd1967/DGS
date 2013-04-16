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
require_once 'include/std_classes.php';
require_once 'include/db/bulletin.php';


 /*!
  * \file quick_bulletin.php
  *
  * \brief QuickHandler for bulletin-object.
  * \see specs/quick_suite.txt (3g)
  */

// see specs/quick_suite.txt (3g)
define('BULLETINCMD_MARK_READ', 'mark_read');
define('BULLETIN_COMMANDS', 'list|mark_read');


 /*!
  * \class QuickHandlerBulletin
  *
  * \brief Quick-handler class for handling bulletin-object.
  */
class QuickHandlerBulletin extends QuickHandler
{
   private $bulletin_iterator = null;
   private $mark_bulletins = null;


   // ---------- Interface ----------------------------------------

   public static function canHandle( $obj, $cmd ) // static
   {
      return ( $obj == QOBJ_BULLETIN ) && QuickHandler::matchRegex(BULLETIN_COMMANDS, $cmd);
   }

   public function parseURL()
   {
      parent::checkArgsUnknown('bid');
      $this->mark_bulletins = array_unique( explode(',', get_request_arg('bid')) );
   }

   public function prepare()
   {
      global $player_row;
      $uid = (int)@$player_row['ID'];

      // see specs/quick_suite.txt (3g)
      $dbgmsg = "QuickHandlerBulletin.prepare($uid)";
      $this->checkCommand( $dbgmsg, BULLETIN_COMMANDS );
      $cmd = $this->quick_object->cmd;

      // prepare command: list

      if( $cmd == QCMD_LIST )
      {
         $qsql = Bulletin::build_view_query_sql( /*adm*/false, /*cnt*/false, /*type*/'', /*chk*/false );
         $qsql->add_part( SQLP_WHERE,
            'BR.bid IS NULL', // unread-bulletins
            "B.Status='".BULLETIN_STATUS_SHOW."'" );
         $qsql->add_part( SQLP_ORDER, 'PublishTime DESC' );
         $this->add_query_limits( $qsql, /*calc-rows*/true );
         $iterator = new ListIterator( $dbgmsg.'.list', $qsql );
         $this->bulletin_iterator = Bulletin::load_bulletins( $iterator );
         $this->read_found_rows();
      }
      elseif( $cmd == BULLETINCMD_MARK_READ )
      {
         if( !is_array($this->mark_bulletins) || count($this->mark_bulletins) == 0 )
            error('invalid_args', "$dbgmsg.check.miss_bid");
         foreach( $this->mark_bulletins as $bid )
         {
            if( !is_numeric($bid) || $bid <= 0 )
               error('invalid_args', "$dbgmsg.check.invalid_bid($bid)");
         }
      }

      // check for invalid-action
   }//prepare

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   public function process()
   {
      $cmd = $this->quick_object->cmd;
      if( $cmd == QCMD_LIST )
         $this->process_cmd_list();
      elseif( $cmd == BULLETINCMD_MARK_READ )
         $this->process_cmd_mark_read();
   }

   private function process_cmd_list()
   {
      $out = array();

      while( list(,$arr_item) = $this->bulletin_iterator->getListIterator() )
      {
         list( $bulletin, $orow ) = $arr_item;
         $out[] = array(
               'id' => $bulletin->ID,
               'target_type' => strtoupper($bulletin->TargetType),
               'status' => strtoupper($bulletin->Status),
               'category' => strtoupper($bulletin->Category),
               'flags' => self::convertBulletinFlags($bulletin->Flags),
               'time_published' => QuickHandler::formatDate(@$bulletin->PublishTime),
               'time_expires' => QuickHandler::formatDate(@$bulletin->ExpireTime),
               'time_updated' => QuickHandler::formatDate(@$bulletin->Lastchanged),
               'author' => $this->build_obj_user($bulletin->uid, $orow, 'BP_'),
               'tournament_id' => $bulletin->tid,
               'game_id' => $bulletin->gid,
               'hits' => (int)$bulletin->CountReads,
               'subject' => $bulletin->Subject,
               'text' => $bulletin->Text,
               'read' => ( @$orow['BR_Read'] ? 1 : 0),
            );
      }

      $this->add_list( QOBJ_BULLETIN, $out, 'time_published-' );
   }//process_cmd_list

   private function process_cmd_mark_read()
   {
      if( is_array($this->mark_bulletins) )
      {
         foreach( $this->mark_bulletins as $bid )
            Bulletin::mark_bulletin_as_read( $bid );
      }
   }//process_cmd_mark_read


   // ------------ static functions ----------------------------

   private static function convertBulletinFlags( $flags )
   {
      $out = array();
      if( $flags & BULLETIN_FLAG_ADMIN_CREATED )
         $out[] = 'ADM_CREATED';
      if( $flags & BULLETIN_FLAG_USER_EDIT )
         $out[] = 'USER_EDIT';
      return implode(',', $out);
   }

} // end of 'QuickHandlerBulletin'

?>
