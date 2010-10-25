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
define('CONTACTCMD_LIST', 'list');
define('CONTACT_COMMANDS', 'list');


 /*!
  * \class QuickHandlerContact
  *
  * \brief Quick-handler class for handling game-object.
  */
class QuickHandlerContact extends QuickHandler
{
   var $contacts;

   function QuickHandlerContact( $quick_object )
   {
      parent::QuickHandler( $quick_object );
      $this->contacts = null;
   }


   // ---------- Interface ----------------------------------------

   function canHandle( $obj, $cmd ) // static
   {
      return ( $obj == QOBJ_CONTACT ) && QuickHandler::matchCommand(CONTACT_COMMANDS, $cmd);
   }

   function parseURL()
   {
   }

   function prepare()
   {
      global $player_row;
      $uid = (int)@$player_row['ID'];

      // see specs/quick_suite.txt (3e)
      $dbgmsg = "QuickHandlerContact.prepare($uid)";
      $this->checkCommand( $dbgmsg, CONTACT_COMMANDS );
      $cmd = $this->quick_object->cmd;

      // prepare command: list

      if( $cmd == CONTACTCMD_LIST )
      {
         $qsql = Contact::build_querysql_contact( $uid );
         $qsql->add_part( SQLP_ORDER, "P.Name ASC" );
         $this->contacts = Contact::load_quick_contacts( $uid, $qsql );
      }

      // check for invalid-action

   }//prepare

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   function process()
   {
      $cmd = $this->quick_object->cmd;
      if( $cmd == CONTACTCMD_LIST )
         $this->process_cmd_list();
   }//process

   function process_cmd_list()
   {
      $out = array();
      if( is_array($this->contacts) )
      {
         foreach( $this->contacts as $contact )
            $out[] = $this->build_obj_contact($contact);
      }

      $this->add_list( QOBJ_CONTACT, $out, 'contact_user.name+' );
   }//process_cmd_list

   function build_obj_contact( $contact )
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

   function build_obj_contact_user( $contact )
   {
      $urow = $contact->contact_user_row;
      $out = $this->build_obj_user( $contact->cid, array( $contact->cid => $urow, /*always*/true ));
      $out['type'] = QuickHandlerUser::convertUserType($urow['Type']);
      $out['country'] = $urow['Country'];
      $out['picture'] = $urow['UserPicture'];
      $out['last_access'] = QuickHandler::formatDate($urow['X_Lastaccess']);
      $out['last_move'] = QuickHandler::formatDate($urow['X_LastMove']);
      $out['rating_status'] = strtoupper($urow['RatingStatus']);
      $out['rating'] = echo_rating($urow['Rating2'], 1, 0, true, 1);
      $out['rating_elo'] = echo_rating_elo($urow['Rating2']);
      return $out;
   }//build_obj_contact_user

} // end of 'QuickHandlerContact'

?>
