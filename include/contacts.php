<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/globals.php';
require_once 'include/std_classes.php';


// Contacts.UserFlags (bitmask for database): 32bit
define('CUSERFLAG_BUDDY',   0x00000001); // contact is good friend of mine
define('CUSERFLAG_FRIEND',  0x00000002); // friend-like relation with contact
define('CUSERFLAG_STUDENT', 0x00000004); // contact is my student
define('CUSERFLAG_TEACHER', 0x00000008); // contact is my teacher
define('CUSERFLAG_FAN',     0x00000010); // I'm a fan of contact
define('CUSERFLAG_ADMIN',   0x00000020); // contact is member of admin-crew
define('CUSERFLAG_TROLL',   0x00000040); // contact is a troll
define('CUSERFLAG_MISC',    0x00000080); // miscellaneous relationship contact (allow special search on notes)

/*!
 * \class Contact
 *
 * \brief Class to handle contact-list for user with system and user categories,
 *        like "deny game", "friend" etc.
 */
class Contact
{
   private static $ARR_CONTACT_TEXTS = array(); // lazy-init in Contact::get..Flags()-funcs: [key][id] => text

   /*! \brief That's me. */
   public $uid;
   /*! \brief That's my contact. */
   public $cid;
   /*! \brief System flags (categories), that prevents or allows certain action between me (uid) and contact (cid). */
   public $sysflags;
   /*! \brief User flags to categorize contacts (no system influence). */
   public $userflags;
   /*! \brief Date when contact have been created (unix-time). */
   public $created;
   /*! \brief Lastchange-date for contact (unix-time). */
   public $lastchanged;
   /*! \brief User-customized note about contact (max 255 chars, linefeeds allowed). */
   public $note;

   public $contact_user_row = null;


   /*!
    * \brief Constructs Contact-object with specified arguments: created and lastchanged are in UNIX-time.
    *        $cid may be 0 to add a new contact
    */
   public function __construct( $uid, $cid, $sysflags, $userflags, $created, $lastchanged, $note )
   {
      if ( !is_numeric($uid) || !is_numeric($cid) || $uid <= 0 || $cid < 0 || $uid == $cid )
         error('invalid_user', "contacts.Contact($uid,$cid)");
      $this->uid = (int) $uid;
      $this->cid = (int) $cid;
      $this->sysflags = (int) $sysflags;
      $this->userflags = (int) $userflags;
      $this->created = (int) $created;
      $this->lastchanged = (int) $lastchanged;
      $this->set_note( $note );
   }//__construct

   /*!
    * \brief Returns Contact-object for specified user $uid with fields
    *        uid, created=$NOW set and all others in default-state.
    */
   public function new_contact( $uid, $cid = 0 )
   {
      global $NOW;

      // uid=set, cid=0, sysflags=userflags=0, created=NOW, lastchanged, note=''
      $contact = new Contact( $uid, $cid,  0, 0,  $NOW, 0, '' );
      return $contact;
   }

   /*!
    * \brief Sets note after doing some replacements
    *        (remove double-LFs, remove starting/trailing whitespaces).
    */
   public function set_note( $note )
   {
      if ( is_null($note) )
         $this->note = '';
      else
         $this->note = preg_replace( "/(\r\n|\n|\r)+/s", "\n", trim($note) );
   }

   /*! \brief Returns true, if specified system flag is set. */
   public function is_sysflag_set( $flag )
   {
      return (bool) ( $this->sysflags & $flag);
   }

   /*! \brief Returns true, if specified user flag is set. */
   public function is_userflag_set( $flag )
   {
      return (bool) ( $this->userflags & $flag);
   }

   /*!
    * \brief Parses system-flags from _REQUEST-array into current object:
    *        expecting vars like 'sfl_..' as declared in getContactSystemFlags().
    */
   public function parse_system_flags()
   {
      $this->sysflags = 0;
      foreach ( self::getContactSystemFlags() as $sysflag => $arr )
      {
         if ( @$_REQUEST[$arr[0]] )
            $this->sysflags |= $sysflag;
      }
   }//parse_system_flags

   /*!
    * \brief Parses user-flags from _REQUEST-array into current object:
    *        expecting vars like 'ufl_..' as declared in getContactUserFlags().
    */
   public function parse_user_flags()
   {
      $this->userflags = 0;
      foreach ( self::getContactUserFlags() as $userflag => $arr )
      {
         if ( @$_REQUEST[$arr[0]] )
            $this->userflags |= $userflag;
      }
   }//parse_user_flags

   /*!
    * \brief Updates current Contact-data into database (may replace existing contact
    *        and set lastchanged=NOW).
    */
   public function update_contact()
   {
      if ( !is_numeric($this->uid) || !is_numeric($this->cid)
            || $this->uid <= 0 || $this->cid <= 0
            || $this->uid == $this->cid )
         error('invalid_user', "contact.update_contact({$this->uid},{$this->cid})");

      global $NOW;
      if ( $this->created == 0 )
         $this->created = $NOW;
      $this->lastchanged = $NOW;

      $result = db_query( "contact.find_user({$this->uid},{$this->cid})",
         "SELECT ID FROM Players WHERE ID IN ('{$this->uid}','{$this->cid}') LIMIT 2" );
      if ( !$result || mysql_num_rows($result) != 2 )
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
      $result = db_query( "contact.update_contact2({$this->uid},{$this->cid})", $update_query );

      self::delete_cache_contact_fp_hide( "contact.update_contact", $this->uid );
   }//update_contact

   /*! \brief Deletes current Contact from database. */
   public function delete_contact()
   {
      if ( !is_numeric($this->uid) || !is_numeric($this->cid)
            || $this->uid <= 0 || $this->cid <= 0
            || $this->uid == $this->cid )
         error('invalid_user', "contact.delete_contact({$this->uid},{$this->cid})");

      $delete_query = "DELETE FROM Contacts "
         . "WHERE uid='{$this->uid}' AND cid='{$this->cid}' LIMIT 1";
      $result = db_query( 'contacts.delete_contact', $delete_query );

      self::delete_cache_contact_fp_hide( "contact.delete_contact", $this->uid );
   }//delete_contact

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   public function to_string()
   {
      return "Contact(u={$this->uid},c={$this->cid}): "
         . "sysflags=[".self::format_system_flags($this->sysflags)."], "
         . "userflags=[".self::format_user_flags($this->userflags)."], "
         . "created=[{$this->created}], lastchanged=[{$this->lastchanged}], note=[{$this->note}]";
   }


   // ---------- Static Class functions ----------------------------

   public static function build_querysql_contact( $uid )
   {
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'P.Type', 'P.Name', 'P.Handle', 'P.Country', 'P.UserPicture',
         'P.RatingStatus', 'P.Rating2', 'P.OnVacation',
         'UNIX_TIMESTAMP(P.Lastaccess) AS X_Lastaccess',
         'UNIX_TIMESTAMP(P.LastMove) AS X_LastMove',
         'C.uid', 'C.cid', 'C.SystemFlags', 'C.UserFlags AS ContactsUserFlags', 'C.Notes',
         'C.Created', 'C.Lastchanged',
         'IFNULL(UNIX_TIMESTAMP(C.Created),0) AS CX_Created',
         'IFNULL(UNIX_TIMESTAMP(C.Lastchanged),0) AS CX_Lastchanged' );
      $qsql->add_part( SQLP_FROM,
         'Contacts AS C',
         'INNER JOIN Players AS P ON C.cid = P.ID' );
      $qsql->add_part( SQLP_WHERE,
         "C.uid=$uid AND C.cid>".GUESTS_ID_MAX ); //exclude guest
      return $qsql;
   }//build_querysql_contact

   /*!
    * \brief Returns Contact-object for specified user $uid and contact $cid;
    *        returns null if no contact listed for user.
    */
   public static function load_contact( $uid, $cid )
   {
      if ( !is_numeric($uid) || !is_numeric($cid) )
         error('invalid_user', "contact.load_contact($uid,$cid)");

      $row = mysql_single_fetch("contact.load_contact2($uid,$cid)",
            "SELECT uid,cid,SystemFlags,UserFlags,Notes, " .
               "UNIX_TIMESTAMP(Created) AS X_Created, " .
               "UNIX_TIMESTAMP(Lastchanged) AS X_Lastchanged " .
            "FROM Contacts WHERE uid='$uid' AND cid='$cid' LIMIT 1");
      if ( !$row )
         return null;

      $contact = new Contact(
            $row['uid'], $row['cid'],
            $row['SystemFlags'], $row['UserFlags'],
            $row['X_Created'], $row['X_Lastchanged'],
            $row['Notes'] );

      return $contact;
   }//load_contact

   /*!
    * \brief Returns array of Contact-objects for quick-suite.
    * \see Contact::build_querysql_contact()
    */
   public static function load_quick_contacts( $uid, $qsql )
   {
      static $arr_userfields = array( 'Type', 'Name', 'Handle', 'Country', 'X_Lastaccess', 'X_LastMove', 'Rating2' );

      $query = $qsql->get_select();
      $result = db_query( "contact.load_quick_contacts($uid)", $query );
      $out = array();
      while ( $row = mysql_fetch_assoc( $result ) )
      {
         $contact = new Contact(
               $row['uid'], $row['cid'],
               $row['SystemFlags'], $row['ContactsUserFlags'],
               $row['CX_Created'], $row['CX_Lastchanged'],
               $row['Notes'] );

         $contact->contact_user_row = array();
         foreach ( $arr_userfields as $key )
            $contact->contact_user_row[$key] = $row[$key];

         $out[] = $contact;
      }
      mysql_free_result($result);

      return $out;
   }//load_quick_contacts

   /*! \brief Loads and return non-empty list of uid, that have a certain contact-system-flag set. */
   public static function load_contact_uids_by_systemflag( $uid, $sysflags )
   {
      $out = array();
      if ( !is_numeric($uid) )
         error('invalid_args', "contact.load_contact_uids_by_systemflag.check.uid($uid)");
      $sysflags = (int)$sysflags;
      if ( $uid <= GUESTS_ID_MAX || $sysflags <= 0 )
         return $out;

      $result = db_query( "contact.load_contact_uids_by_systemflag($uid,$sysflags)",
         "SELECT cid FROM Contacts WHERE uid=$uid AND (SystemFlags & $sysflags)>0" );
      while ( $row = mysql_fetch_assoc( $result ) )
         $out[] = $row['cid'];
      mysql_free_result($result);

      return $out;
   }//load_contact_uids_by_systemflag

   /*!
    * \brief Static function returning
    *   1: $cid is a defined contact of $uid.
    *   0: $cid is not yet a contact of $uid, but he may become.
    *  -1: $cid and $uid can't have contacts.
    */
   public static function has_contact( $uid, $cid )
   {
      if ( $uid == $cid || $cid <= GUESTS_ID_MAX || $uid <= GUESTS_ID_MAX ) //exclude guest
         return -1;
      $result = db_query( "Contact:has_contact($uid,$cid)",
         "SELECT cid FROM Contacts WHERE uid='$uid' AND cid='$cid' LIMIT 1");
      if ( !$result )
         return 0;
      $res = (int)( @mysql_num_rows($result) > 0 );
      mysql_free_result($result);
      return $res;
   }//has_contact

   /*!
    * \brief Returns separated list of translated texts for specified system-flags;
    *        separator specified as $sep.
    */
   public static function format_system_flags( $flagmask, $sep=', ', $quick=false )
   {
      return self::format_flags( self::getContactSystemFlags($quick), $flagmask, $sep );
   }

   /*!
    * \brief Returns separated list of translated texts for specified user-flags;
    *        separator specified as $sep.
    */
   public static function format_user_flags( $flagmask, $sep=', ', $quick=false )
   {
      return self::format_flags( self::getContactUserFlags($quick), $flagmask, $sep );
   }

   /*!
    * \brief Returns $sep-separated list of translated texts for specified
    *        flags-array and flag-bitmask.
    * \internal
    */
   private static function format_flags( $flags_array, $flagmask, $sep )
   {
      $out = array();
      foreach ( $flags_array as $flag => $arr )
         if ( $flagmask & $flag )
            $out[]= $arr[1];
      return implode($sep, $out);
   }

   /*! \brief Returns globals (lazy init) for contact-user-flags. */
   public static function getContactUserFlags( $quick=false )
   {
      // lazy-init of texts
      $key = 'USERFLAGS' . ($quick ? '_QUICK' : '');
      if ( !isset(self::$ARR_CONTACT_TEXTS[$key]) )
      {
         $arr = array();
         // userflag => ( form_elem_name, translation )
         if ( $quick )
         {
            $arr[CUSERFLAG_BUDDY]   = array( 0, 'BUDDY' );
            $arr[CUSERFLAG_FRIEND]  = array( 0, 'FRIEND' );
            $arr[CUSERFLAG_STUDENT] = array( 0, 'STUDENT' );
            $arr[CUSERFLAG_TEACHER] = array( 0, 'TEACHER' );
            $arr[CUSERFLAG_FAN]     = array( 0, 'FAN' );
            $arr[CUSERFLAG_TROLL]   = array( 0, 'TROLL' );
            $arr[CUSERFLAG_ADMIN]   = array( 0, 'ADMIN' );
            $arr[CUSERFLAG_MISC]    = array( 0, 'MISC' );
         }
         else
         {
            $arr[CUSERFLAG_BUDDY]   = array( 'ufl_buddy',   T_('Buddy') );
            $arr[CUSERFLAG_FRIEND]  = array( 'ufl_friend',  T_('Friend') );
            $arr[CUSERFLAG_STUDENT] = array( 'ufl_student', T_('Student') );
            $arr[CUSERFLAG_TEACHER] = array( 'ufl_teacher', T_('Teacher') );
            $arr[CUSERFLAG_FAN]     = array( 'ufl_fan',     T_('Fan') );
            $arr[CUSERFLAG_TROLL]   = array( 'ufl_troll',   T_('Troll') );
            $arr[CUSERFLAG_ADMIN]   = array( 'ufl_admin',   T_('Site Crew') );
            $arr[CUSERFLAG_MISC]    = array( 'ufl_misc',    T_('Miscellaneous') );
         }
         self::$ARR_CONTACT_TEXTS[$key] = $arr;
      }

      return self::$ARR_CONTACT_TEXTS[$key];
   }//getContactUserFlags

   /*! \brief Returns globals (lazy init) for contact-system-flags. */
   public static function getContactSystemFlags( $quick=false )
   {
      // lazy-init of texts
      $key = 'SYSTEMFLAGS' . ($quick ? '_QUICK' : '');
      if ( !isset(self::$ARR_CONTACT_TEXTS[$key]) )
      {
         $arr = array();
         // sysflag => ( form_elem_name, translation )
         if ( $quick )
         {
            $arr[CSYSFLAG_WR_HIDE_GAMES]  = array( 0, 'WR_HIDE_GAMES' );
            $arr[CSYSFLAG_WAITINGROOM]    = array( 0, 'WR_PROTECT_GAMES' );
            $arr[CSYSFLAG_REJECT_MESSAGE] = array( 0, 'REJECT_MESSAGE' );
            $arr[CSYSFLAG_REJECT_INVITE]  = array( 0, 'REJECT_INVITE' );
            $arr[CSYSFLAG_F_HIDE_POSTS]   = array( 0, 'F_HIDE_POSTS' );
         }
         else
         {
            $arr[CSYSFLAG_WR_HIDE_GAMES]  = array( 'sfl_wr_hide',    T_('Hide waitingroom games') );
            $arr[CSYSFLAG_WAITINGROOM]    = array( 'sfl_wr_protect', T_('Protect waitingroom games') );
            $arr[CSYSFLAG_REJECT_MESSAGE] = array( 'sfl_reject_msg', T_('Reject messages') );
            $arr[CSYSFLAG_REJECT_INVITE]  = array( 'sfl_reject_inv', T_('Reject invitations') );
            $arr[CSYSFLAG_F_HIDE_POSTS]   = array( 'sfl_fpost_hide', T_('Hide forum posts') );
         }
         self::$ARR_CONTACT_TEXTS[$key] = $arr;
      }

      return self::$ARR_CONTACT_TEXTS[$key];
   }//getContactSystemFlags

   /*!
    * \brief Loads and caches output of Contact::load_contact_uids_by_systemflag().
    * \note IMPORTANT NOTE: can only be used for not more than one specific sysflag!
    */
   public static function load_cache_contact_uids_by_systemflag( $dbgmsg, $uid, $sysflag )
   {
      $uid = (int)$uid;
      $dbgmsg .= ".contact.load_cache_cids_sysflag($uid,$sysflag)";
      $key = "Cont_FP_Hide.$uid";

      $arr_uids = DgsCache::fetch( $dbgmsg, CACHE_GRP_CONT_FP_HIDE, $key );
      if ( is_null($arr_uids) )
      {
         $arr_uids = self::load_contact_uids_by_systemflag( $uid, $sysflag );
         DgsCache::store( $dbgmsg, CACHE_GRP_CONT_FP_HIDE, $key, $arr_uids, 3*SECS_PER_DAY );
      }

      return $arr_uids;
   }//load_cache_contact_uids_by_systemflag

   public static function delete_cache_contact_fp_hide( $dbgmsg, $uid )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_CONT_FP_HIDE, "Cont_FP_Hide.$uid" );
   }

} // end of 'Contact'

?>
