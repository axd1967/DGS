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
require_once 'include/quick/quick_user.php';
require_once 'include/contacts.php';

 /*!
  * \file quick_contact.php
  *
  * \brief QuickHandler for user-object.
  * \see specs/quick_suite.txt (3e)
  */

// see specs/quick_suite.txt (3e)
define('CONTACT_COMMANDS', 'list');


 /*!
  * \class QuickHandlerContact
  *
  * \brief Quick-handler class for handling game-object.
  */
class QuickHandlerContact extends QuickHandler
{
   private $contacts = null;


   // ---------- Interface ----------------------------------------

   public static function canHandle( $obj, $cmd ) // static
   {
      return ( $obj == QOBJ_CONTACT ) && QuickHandler::matchRegex(CONTACT_COMMANDS, $cmd);
   }

   public function parseURL()
   {
      parent::checkArgsUnknown();
   }

   public function prepare()
   {
      global $player_row;
      $uid = (int)@$player_row['ID'];

      // see specs/quick_suite.txt (3e)
      $dbgmsg = "QuickHandlerContact.prepare($uid)";
      $this->checkCommand( $dbgmsg, CONTACT_COMMANDS );
      $cmd = $this->quick_object->cmd;

      // prepare command: list

      if ( $cmd == QCMD_LIST )
      {
         $qsql = Contact::build_querysql_contact( $uid );
         $qsql->add_part( SQLP_ORDER, "P.Name ASC" );
         $this->add_query_limits( $qsql, /*calc-rows*/true );
         $this->contacts = Contact::load_quick_contacts( $uid, $qsql );
         $this->read_found_rows();
      }
   }//prepare

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   public function process()
   {
      $cmd = $this->quick_object->cmd;
      if ( $cmd == QCMD_LIST )
         $this->process_cmd_list();
   }//process

   private function process_cmd_list()
   {
      $out = array();
      if ( is_array($this->contacts) )
      {
         foreach ( $this->contacts as $contact )
            $out[] = $this->build_obj_contact($contact);
      }

      $this->add_list( QOBJ_CONTACT, $out, 'contact_user.name+' );
   }//process_cmd_list

   private function build_obj_contact( $contact )
   {
      return array(
         'contact_user' => $this->build_obj_contact_user($contact),
         'system_flags' => Contact::format_system_flags( $contact->sysflags, ',', /*quick*/true ),
         'user_flags'   => Contact::format_user_flags( $contact->userflags, ',', /*quick*/true ),
         'created_at'   => QuickHandler::formatDate($contact->created),
         'updated_at'   => QuickHandler::formatDate($contact->lastchanged),
         'notes'        => $contact->note,
      );
   }//build_obj_contact

   private function build_obj_contact_user( $contact )
   {
      $with_fields = ( $this->is_with_option(QWITH_USER_ID) ) ? 'country,rating,lastacc' : '';
      $urow = $contact->contact_user_row;
      $out = $this->build_obj_user( $contact->cid, $urow, '', $with_fields, /*always*/true );
      if ( $with_fields )
      {
         $out['last_move'] = QuickHandler::formatDate($urow['X_LastMove']);
         $out['type'] = QuickHandlerUser::convertUserType($urow['Type']);
      }
      return $out;
   }//build_obj_contact_user

} // end of 'QuickHandlerContact'

?>
