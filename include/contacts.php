<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Users";


 /*!
  * \class Contact
  *
  * \brief Class to handle contact-list for user with system and user categories,
  *        like "deny game", "friend" etc.
  */

// TODO: discuss system-flags with Erik(!), then implement actions defined for CSYSFLAGS !!
// system-flags (bitmask for database): 16bit
define('CSYSFLAG_WAITINGROOM'    ,0x0001); // hide my games in waiting-room from contact
define('CSYSFLAG_REJECT_MESSAGE' ,0x0002); // don't accept message from contact
define('CSYSFLAG_REJECT_INVITE'  ,0x0004); // don't accept invitation from contact

// user-flags (bitmask for database): 32bit
define('CUSERFLAG_BUDDY',   0x00000001); // contact is good friend of mine
define('CUSERFLAG_FRIEND',  0x00000002); // friend-like relation with contact
define('CUSERFLAG_STUDENT', 0x00000004); // contact is my student
define('CUSERFLAG_TEACHER', 0x00000008); // contact is my teacher
define('CUSERFLAG_FAN',     0x00000010); // í'm a fan of contact
define('CUSERFLAG_ADMIN',   0x00000020); // contact is member of admin-crew
define('CUSERFLAG_TROLL',   0x00000040); // contact is a troll
define('CUSERFLAG_MISC',    0x00000080); // miscellaneous relationship contact (allow special search on notes)


class Contact
{
   /*! \brief That's me. */
   var $uid;
   /*! \brief That's my contact. */
   var $cid;
   /*! \brief System flags (categories), that prevents or allows certain action between me (uid) and contact (cid). */
   var $sysflags;
   /*! \brief User flags to categorize contacts (no system influence). */
   var $userflags;
   /*! \brief Date when contact have been created (unix-time). */
   var $created;
   /*! \brief Lastchange-date for contact (unix-time). */
   var $lastchanged;
   /*! \brief User-customized note about contact (max 255 chars, linefeeds allowed). */
   var $note;

   /*! Constructs Contact-object with specified arguments: created and lastchanged are in UNIX-time.
    *  $cid may be 0 to add a new contact
    */
   function Contact( $uid, $cid, $sysflags, $userflags, $created, $lastchanged, $note )
   {
      if ( !is_numeric($uid) or !is_numeric($cid)
            or $uid <= 0 or $cid < 0
            or $uid == $cid )
         error('invalid_user', "contacts.Contact($uid,$cid)");
      $this->uid = (int) $uid;
      $this->cid = (int) $cid;
      $this->sysflags = (int) $sysflags;
      $this->userflags = (int) $userflags;
      $this->created = (int) $created;
      $this->lastchanged = (int) $lastchanged;
      $this->set_note( $note );
   }

   //TODO: error-prone, CHANGE: must be called from within class (not in client)
   /*!
    * \brief Load globals used by the class
    *        must be called only one time
    *        delayed because translations need to be already loaded
    *        (and save some time if not used)
    */
   function load_globals()
   {
      //TODO
      global $ARR_CONTACT_USERFLAGS;
      if( isset($ARR_CONTACT_USERFLAGS) )
         return;

      // userflag => ( form_elem_name, translation )
      $ARR_CONTACT_USERFLAGS = array(
         CUSERFLAG_BUDDY   => array( 'ufl_buddy',   T_('Buddy') ),
         CUSERFLAG_FRIEND  => array( 'ufl_friend',  T_('Friend') ),
         CUSERFLAG_STUDENT => array( 'ufl_student', T_('Student') ),
         CUSERFLAG_TEACHER => array( 'ufl_teacher', T_('Teacher') ),
         CUSERFLAG_FAN     => array( 'ufl_fan',     T_('Fan') ),
         CUSERFLAG_TROLL   => array( 'ufl_troll',   T_('Troll') ),
         CUSERFLAG_ADMIN   => array( 'ufl_admin',   T_('Admin-crew') ),
         CUSERFLAG_MISC    => array( 'ufl_misc',    T_('Miscellaneous') ),
         );

      // sysflag => ( form_elem_name, translation )
      global $ARR_CONTACT_SYSFLAGS;
      $ARR_CONTACT_SYSFLAGS = array(
         CSYSFLAG_WAITINGROOM    => array( 'sfl_waitingroom',    T_('Protect waitingroom games') ),
         CSYSFLAG_REJECT_MESSAGE => array( 'sfl_reject_message', T_('Reject messages') ),
         CSYSFLAG_REJECT_INVITE  => array( 'sfl_reject_invite',  T_('Reject invitations') ),
         );
   }

   /*!
    * \brief Returns Contact-object for specified user $uid with fields
    *        uid, created=$NOW set and all others in default-state.
    */
   function new_contact( $uid, $cid = 0 )
   {
      global $NOW;

      // uid=set, cid=0, sysflags=userflags=0, created=NOW, lastchanged, note=''
      $contact = new Contact( $uid, $cid,  0, 0,  $NOW, 0, '' );
      return $contact;
   }

   /*!
    * \brief Returns Contact-object for specified user $uid and contact $cid;
    *        returns null if no contact listed for user.
    */
   function load_contact( $uid, $cid )
   {
      if ( !is_numeric($uid) or !is_numeric($cid) )
         error('invalid_user', "contact.load_contact($uid,$cid)");

      $row = mysql_single_fetch("contact.load_contact2($uid,$cid)",
            "SELECT * FROM Contacts WHERE uid='$uid' AND cid='$cid' LIMIT 1");
      if( !$row )
         return null;

      $contact = new Contact(
            $row['uid'], $row['cid'],
            $row['SystemFlags'], $row['UserFlags'],
            $row['Created'], $row['Lastchanged'],
            $row['Notes'] );

      return $contact;
   }

   /*!
    * \brief Sets note after doing some replacements
    *        (remove double-LFs, remove starting/trailing whitespaces).
    */
   function set_note( $note )
   {
      if ( is_null($note) )
         $this->note = '';
      else
         $this->note = preg_replace( "/(\r\n|\n|\r)+/s", "\n", trim($note) );
   }

   /*! \brief Returns true, if specified system flag is set. */
   function is_sysflag_set( $flag )
   {
      return (bool) ( $this->sysflags & $flag);
   }

   /*! \brief Returns true, if specified user flag is set. */
   function is_userflag_set( $flag )
   {
      return (bool) ( $this->userflags & $flag);
   }

   /*!
    * \brief Parses system-flags from _REQUEST-array into current object:
    *        expecting vars like 'sfl_..' as declared in $ARR_CONTACT_SYSFLAGS.
    */
   function parse_system_flags()
   {
      global $ARR_CONTACT_SYSFLAGS;

      $this->sysflags = 0;
      foreach( $ARR_CONTACT_SYSFLAGS as $sysflag => $arr )
         if ( @$_REQUEST[$arr[0]] )
            $this->sysflags |= $sysflag;
   }

   /*!
    * \brief Parses user-flags from _REQUEST-array into current object:
    *        expecting vars like 'ufl_..' as declared in $ARR_CONTACT_USERFLAGS.
    */
   function parse_user_flags()
   {
      global $ARR_CONTACT_USERFLAGS;

      $this->userflags = 0;
      foreach( $ARR_CONTACT_USERFLAGS as $userflag => $arr )
         if ( @$_REQUEST[$arr[0]] )
            $this->userflags |= $userflag;
   }

   /*!
    * \brief Updates current Contact-data into database (may replace existing contact
    *        and set lastchanged=NOW).
    */
   function update_contact()
   {
      if ( !is_numeric($this->uid) or !is_numeric($this->cid)
            or $this->uid <= 0 or $this->cid <= 0
            or $this->uid == $this->cid )
         error('invalid_user', "contact.update_contact({$this->uid},{$this->cid})");

      global $NOW;
      $this->lastchanged = $NOW;

      $result = mysql_query("SELECT ID FROM Players WHERE ID IN ('{$this->uid}','{$this->cid}') LIMIT 2")
         or error('mysql_query_failed', "contact.find_user({$this->uid},{$this->cid})");
      if( !$result or mysql_num_rows($result) != 2 )
         error('unknown_user', "contact.find_user2({$this->uid},{$this->cid})");
      mysql_free_result($result);

      $update_query = 'REPLACE INTO Contacts SET'
         . ' uid=' . (int)$this->uid
         . ', cid=' . (int)$this->cid
         . ', SystemFlags=' . (int)$this->sysflags
         . ', UserFlags=' . (int)$this->userflags
         . ', Created=FROM_UNIXTIME(' . $this->created .')'
         . ', Lastchanged=FROM_UNIXTIME(' . $this->lastchanged .')'
         . ", Notes='" . mysql_addslashes($this->note) . "'"
         ;
      $result = mysql_query( $update_query )
         or error('mysql_query_failed', "contact.update_contact2({$this->uid},{$this->cid})");
   }

   /*! \brief Deletes current Contact from database. */
   function delete_contact()
   {
      if ( !is_numeric($this->uid) or !is_numeric($this->cid)
            or $this->uid <= 0 or $this->cid <= 0
            or $this->uid == $this->cid )
         error('invalid_user', "contact.delete_contact({$this->uid},{$this->cid})");

      $delete_query = "DELETE FROM Contacts "
         . "WHERE uid='{$this->uid}' AND cid='{$this->cid}' LIMIT 1";
      $result = mysql_query( $delete_query )
         or error('mysql_query_failed','contacts.delete_contact');
   }

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   function to_string()
   {
      return "Contact(u={$this->uid},c={$this->cid}): "
         . "sysflags=[".Contact::format_system_flags($this->sysflags)."], "
         . "userflags=[".Contact::format_user_flags($this->userflags)."], "
         . "created=[{$this->created}], lastchanged=[{$this->lastchanged}], note=[{$this->note}]";
   }


   // ---------- Static Class functions ----------------------------

   /*! \brief Static function returning
    *   1: $cid is a defined contact of $uid.
    *   0: $cid is not yet a contact of $uid, but he may become.
    *  -1: $cid and $uid can't have contacts.
    */
   function has_contact( $uid, $cid )
   {
      if ( $uid == $cid || $cid <= GUESTS_ID_MAX || $uid <= GUESTS_ID_MAX ) //exclude guest
         return -1;
      $result = db_query( 'contact.has_contact',
         "SELECT cid FROM Contacts WHERE uid='$uid' AND cid='$cid' LIMIT 1");
      if( !$result )
         return 0;
      $res = (int)( @mysql_num_rows($result) > 0 );
      mysql_free_result($result);
      return $res;
   }

   /*!
    * \brief Returns separated list of translated texts for specified system-flags;
    *        separator specified as $sep.
    */
   function format_system_flags( $flagmask, $sep=', ' )
   {
      global $ARR_CONTACT_SYSFLAGS;
      return Contact::format_flags( $ARR_CONTACT_SYSFLAGS, $flagmask, $sep );
   }

   /*!
    * \brief Returns separated list of translated texts for specified user-flags;
    *        separator specified as $sep.
    */
   function format_user_flags( $flagmask, $sep=', ' )
   {
      global $ARR_CONTACT_USERFLAGS;
      return Contact::format_flags( $ARR_CONTACT_USERFLAGS, $flagmask, $sep );
   }

   /*!
    * \brief Returns $sep-separated list of translated texts for specified
    *        flags-array and flag-bitmask.
    * \internal
    */
   function format_flags( $flags_array, $flagmask, $sep )
   {
      $out = array();
      foreach( $flags_array as $flag => $arr )
         if ( $flagmask & $flag )
            $out[]= $arr[1];
      return implode($sep, $out);
   }

} // end of 'Contact'

?>
