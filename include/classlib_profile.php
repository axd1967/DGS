<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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


$TranslateGroups[] = "Common";

require_once( "include/std_functions.php" );


// profile.Type
define('PROFTYPE_FILTER_USERS', 1);
define('PROFTYPE_FILTER_WAITINGROOM', 2);
define('PROFTYPE_FILTER_MSG_SEARCH', 3);
define('PROFTYPE_FILTER_CONTACTS', 4);
define('PROFTYPE_FILTER_FEATURES', 5);
define('PROFTYPE_FILTER_VOTES', 6);
define('PROFTYPE_FILTER_FORUM_SEARCH', 7);
define('PROFTYPE_FILTER_GAMES_STATUS', 8);
define('PROFTYPE_FILTER_GAMES_OBSERVED', 9);
define('PROFTYPE_FILTER_GAMES_RUNNING_MY', 10);
define('PROFTYPE_FILTER_GAMES_RUNNING_ALL', 11);
define('PROFTYPE_FILTER_GAMES_FINISHED_MY', 12);
define('PROFTYPE_FILTER_GAMES_FINISHED_ALL', 13);
define('MAX_PROFTYPE', 13);

define('SEP_PROFVAL', '&'); // separator of fields (text stored in DB)

// form-profile actions for selection + submit (selectbox values)
define('FPROF_CURRENT_VALUES', 0);
define('FPROF_CLEAR_VALUES', 1);
define('FPROF_SAVE_DEFAULT', 2);
define('FPROF_LOAD_PROFILE', 3);
define('FPROF_SAVE_PROFILE', 4);
define('FPROF_DEL_PROFILE',  5);

define('FFORM_PROF_ACTION', '');


 /*!
  * \class Profile
  *
  * \brief DAO and DB-load/save to manage search-profiles and form-profiles.
  */
class Profile
{
   /*! \brief ID (PK from db). */
   var $id;
   /*! \brief user-id for profile. */
   var $user_id;
   /*! \brief type (must be one of PROFTYPE_). */
   var $type;
   /*! \brief sort-order (1..n). */
   var $sortorder;
   /*! \brief bool active (Y|N-enum in DB). */
   var $active;
   /*! \brief user-chosen name */
   var $name;
   /*! \brief Date when profile has been lastchanged (unix-time). */
   var $lastchanged;
   /*! \brief profile-content/url/address. */
   var $text;

   /*!
    * \brief Constructs Profile-object with specified arguments: lastchanged are in UNIX-time.
    *        $id may be 0 to add a new profile
    * \param $type must be non-0
    */
   function Profile( $id=0, $user_id=0, $type=0, $sortorder=1, $active=false, $name='', $lastchanged=0, $text='' )
   {
      if( !is_numeric($user_id) || $user_id < 0 )
         error('invalid_user', "profile.Profile($id,$user_id,$type)");
      $this->set_type( $type );
      $this->id = (int) $id;
      $this->user_id = (int) $user_id;
      $this->sortorder = (int) $sortorder;
      $this->active = (bool) $active;
      $this->name = $name;
      $this->lastchanged = (int) $lastchanged;
      $this->text = $text;
   }

   /*! \brief Sets valid profile-type (exception on invalid type). */
   function set_type( $type )
   {
      if( !is_numeric($type) || $type < 1 || $type > MAX_PROFTYPE )
         error('invalid_arg', "profile.set_type($type)");
      $this->type = (int) $type;
   }

   /*!
    * \brief Sets profile-content built from given array (URL-like) or raw-text (converted to string).
    * \param $arr null, array with key-values, or raw-text
    */
   function set_text( $arr )
   {
      if( is_null($arr) )
         $this->text = '0'; // representation of NULL
      elseif( is_array($arr) )
         $this->text = build_url( $arr, false, SEP_PROFVAL );
      else
         $this->text = (string)$arr; // raw-format
   }

   /*!
    * \brief Returns parsed profile-content as array with splitted values, or as raw-text ($raw=true).
    */
   function get_text( $raw=false )
   {
      if( $raw )
         return $this->text;
      else
      {
         if( is_null($this->text) || (string)$this->text == '0' )
            return null;
         else
         {
            split_url( '?'.$this->text, $prefix, $arr_out, SEP_PROFVAL );
            return $arr_out;
         }
      }
   }

   /*! \brief Updates current profile-data into database (may replace existing profile). */
   function update_profile( $check_user=false )
   {
      global $NOW;

      if( $check_user )
      {
         $row = mysql_single_fetch( "profile.find_user({$this->user_id})",
            "SELECT ID FROM Players WHERE ID={$this->user_id} LIMIT 1" );
         if( !$row )
            error('unknown_user', "profile.find_user2({$this->user_id})");
      }

      $update_query = 'REPLACE INTO Profile SET'
         . ' ID=' . (int)$this->id
         . ', User_ID=' . (int)$this->user_id
         . ', Type=' . (int)$this->type
         . ', SortOrder=' . (int)$this->sortorder
         . ", active='" . ($this->active ? 'Y' : 'N') . "'" // enum
         . ", Name='" . mysql_addslashes($this->name) . "'"
         . ', lastchanged=FROM_UNIXTIME(' . (int)$this->lastchanged .')'
         . ", Text='" . mysql_addslashes($this->text) . "'" // blob
         ;
      db_query( "profile.update_profile({$this->id},{$this->user_id},{$this->type})",
         $update_query );
   }

   /*! \brief Deletes current profile from database. */
   function delete_profile()
   {
      db_query( "profile.delete_profile({$this->id})",
         "DELETE FROM Profile WHERE ID='{$this->id}' LIMIT 1" );
   }

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   function to_string()
   {
      return "Profile(id={$this->id}): "
         . "user_id=[{$this->user_id}], "
         . "type=[{$this->type}], "
         . "sortorder=[{$this->sortorder}], "
         . "active=[{$this->active}], "
         . "name=[{$this->name}], "
         . "lastchanged=[{$this->lastchanged}], "
         . "text=[{$this->text}]";
   }


   // ---------- Static Class functions ----------------------------

   /*! \brief Returns db-fields to be used for query of Profile-object. */
   function get_query_fields()
   {
      return array(
         'ID', 'User_ID', 'Type', 'SortOrder', 'Active', 'Name', 'Lastchanged', 'Text',
         'IFNULL(UNIX_TIMESTAMP(Lastchanged),0) AS X_LastchangedU',
      );
   }

   /*!
    * \brief Returns Profile-object for specified user and type set
    *        lastchanged=$NOW set and all others in default-state.
    */
   function new_profile( $user_id, $type, $prof_id=0 )
   {
      global $NOW;

      // id=set, user_id=?, type=?, sortoder=1, active=false, name='', lastchanged=NOW, text=''
      $profile = new Profile( $prof_id, $user_id, $type );
      $profile->lastchanged = $NOW;
      return $profile;
   }

   /*! \brief Returns Profile-object lastchanged from specified (db-)row with fields defined by func fields_profile. */
   function new_from_row( $row )
   {
      $profile = new Profile(
         $row['ID'], $row['User_ID'], $row['Type'],
         $row['SortOrder'], (@$row['Active'] == 'Y'), $row['Name'],
         $row['X_LastchangedU'], $row['Text'] );
      return $profile;
   }

   /*!
    * \brief Returns single Profile-object for specified profile-id (must be owned by given user-id).
    * \return null if no profile found.
    */
   function load_profile( $user_id, $prof_id )
   {
      if( !is_numeric($prof_id) )
         error('invalid_args', "profile.load_profile($prof_id)");

      $fields = implode(',', Profile::get_query_fields());
      $row = mysql_single_fetch("profile.load_profile2($user_id,$type)",
            "SELECT $fields FROM Profile WHERE ID='$prof_id' AND User_ID='$user_id' LIMIT 1");
      if( !$row )
         return null;

      return Profile::new_from_row( $row );
   }

   /*!
    * \brief Returns list of Profile-objects for specified user-id and type (in SortOrder).
    * \return empty-array if no profile found.
    */
   function load_profiles( $user_id, $type )
   {
      if( !is_numeric($user_id) || !is_numeric($type) )
         error('invalid_args', "profile.load_profiles($user_id,$type)");

      $fields = implode(',', Profile::get_query_fields());
      $result = mysql_query(
            "SELECT $fields FROM Profile WHERE User_ID='$user_id' AND Type='$type' " .
            "ORDER BY SortOrder,ID LIMIT 1" )
         or error('mysql_query_failed', "profile.load_profile2($user_id,$type)");

      $arr_out = array();
      while( ($row = mysql_fetch_assoc( $result )) && $show_rows-- > 0 )
      {
         $arr_out[] = Profile::new_from_row($row);
      }
      mysql_free_result($result);

      return $arr_out;
   }

} // end of 'Profile'

?>
