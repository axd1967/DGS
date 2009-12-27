<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
// NOTE: once released, id MUST NOT be changed (db-key)
define('PROFTYPE_FILTER_USERS', 1);
define('PROFTYPE_FILTER_WAITINGROOM', 2);
define('PROFTYPE_FILTER_MSG_SEARCH', 3);
define('PROFTYPE_FILTER_CONTACTS', 4);
define('PROFTYPE_FILTER_FORUM_SEARCH', 5); // unused
define('PROFTYPE_FILTER_GAMES_STATUS', 6); // unused (not configurable)
define('PROFTYPE_FILTER_GAMES_OBSERVED', 7);
define('PROFTYPE_FILTER_GAMES_RUNNING_ALL', 8);
define('PROFTYPE_FILTER_GAMES_RUNNING_MY', 9);
define('PROFTYPE_FILTER_GAMES_RUNNING_OTHER', 10);
define('PROFTYPE_FILTER_GAMES_FINISHED_ALL', 11);
define('PROFTYPE_FILTER_GAMES_FINISHED_MY', 12);
define('PROFTYPE_FILTER_GAMES_FINISHED_OTHER', 13);
define('PROFTYPE_FILTER_OBSERVERS', 14);
define('PROFTYPE_FILTER_OPPONENTS_MY', 15);
define('PROFTYPE_FILTER_OPPONENTS_OTHER', 16);
define('PROFTYPE_FILTER_FEATURES', 17);
define('PROFTYPE_FILTER_VOTES', 18);
define('PROFTYPE_FILTER_GAMES_OBSERVED_ALL', 19);
define('PROFTYPE_FILTER_TOURNAMENTS', 20);
define('PROFTYPE_FILTER_TOURNAMENT_DIRECTORS', 21);
define('PROFTYPE_FILTER_TOURNAMENT_PARTICIPANTS', 22);
// adjust if adding one
define('MAX_PROFTYPE', 22);

define('SEP_PROFVAL', '&'); // separator of fields (text stored in DB)

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
      // allowed for guests, but no DB-writing ops
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
            return NULL;
         else
         {
            // NOTE: array-value must not be NULL(!)
            split_url( '?'.$this->text, $prefix, $arr_out, SEP_PROFVAL );
            return $arr_out;
         }
      }
   }

   /*!
    * \brief Inserts or updates current profile-data in database (may replace existing profile);
    *        Set id to 0 if you want an insert.
    */
   function update_profile( $check_user=false )
   {
      global $NOW;

      if( $this->user_id <= GUESTS_ID_MAX )
         error('not_allowed_for_guest', "profile.update_profile({$this->user_id})");

      if( $check_user )
      {
         $row = mysql_single_fetch( "profile.update_profile.find_user({$this->user_id})",
            "SELECT ID FROM Players WHERE ID={$this->user_id} LIMIT 1" );
         if( !$row )
            error('unknown_user', "profile.update_profile.find_user2({$this->user_id})");
      }

      $update_query = 'REPLACE INTO Profiles SET'
         . ' ID=' . (int)$this->id
         . ', User_ID=' . (int)$this->user_id
         . ', Type=' . (int)$this->type
         . ', SortOrder=' . (int)$this->sortorder
         . ", Active='" . ($this->active ? 'Y' : 'N') . "'" // enum
         . ", Name='" . mysql_addslashes($this->name) . "'"
         . ', Lastchanged=FROM_UNIXTIME(' . (int)$this->lastchanged .')'
         . ", Text='" . mysql_addslashes($this->text) . "'" // blob
         ;
      db_query( "profile.update_profile({$this->id},{$this->user_id},{$this->type})",
         $update_query );
   }

   /*! \brief Deletes current profile from database. */
   function delete_profile()
   {
      if( $this->user_id <= GUESTS_ID_MAX )
         error('not_allowed_for_guest', "profile.update_profile({$this->user_id})");

      if( $this->id > 0 )
         db_query( "profile.delete_profile({$this->id})",
            "DELETE FROM Profiles WHERE ID='{$this->id}' LIMIT 1" );
   }

   /*! \brief Deletes all profiles for specific user-id and type from database. */
   function delete_all_profiles( $user_id, $type )
   {
      $user_id = (int)$user_id;
      $type = (int)$type;
      if( $this->user_id <= GUESTS_ID_MAX || $user_id <= GUESTS_ID_MAX )
         error('not_allowed_for_guest', "profile.update_profile($user_id/{$this->user_id},$type)");
      if( $type < 0 || $type > MAX_PROFTYPE )
         error('invalid_args', "profile.delete_all_profiles($user_id,$type)");

      db_query( "profile.delete_all_profiles($user_id,$type)",
         "DELETE FROM Profiles WHERE User_ID='$user_id' AND Type='$type'" );
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
         @$row['ID'], @$row['User_ID'], @$row['Type'],
         @$row['SortOrder'], (@$row['Active'] == 'Y'), @$row['Name'],
         @$row['X_LastchangedU'], @$row['Text'] );
      return $profile;
   }

   /*!
    * \brief Returns single Profile-object for specified profile-id (must be owned by given user-id).
    * \return null if no profile found.
    */
   function load_profile( $prof_id, $user_id )
   {
      if( !is_numeric($prof_id) || !is_numeric($user_id) )
         error('invalid_args', "profile.load_profile($prof_id,$user_id)");

      $fields = implode(',', Profile::get_query_fields());
      $row = mysql_single_fetch("profile.load_profile2($prof_id,$user_id)",
            "SELECT $fields FROM Profiles WHERE ID='$prof_id' AND User_ID='$user_id' LIMIT 1");
      if( !$row )
         return NULL;

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
      $result = db_query( "profile.load_profile2($user_id,$type)",
            "SELECT $fields FROM Profiles WHERE User_ID='$user_id' AND Type='$type' " .
            "ORDER BY SortOrder,ID LIMIT 1" );

      $arr_out = array();
      while( ($row = mysql_fetch_assoc( $result )) )
      {
         $arr_out[] = Profile::new_from_row($row);
      }
      mysql_free_result($result);

      return $arr_out;
   }

} // end of 'Profile'




// form-profile actions for selection + submit (selectbox values)
// NOTE: positive values are reserved for profile-IDs
define('SPROF_CURR_VALUES',   0); // use current-values for search
define('SPROF_CLEAR_VALUES', -1); // clear values and use for search
define('SPROF_RESET_VALUES', -2); // reset to default-values and search on them
define('SPROF_SAVE_DEFAULT', -3); // save as default
define('SPROF_LOAD_PROFILE', -4); // load and use for search
define('SPROF_SAVE_PROFILE', -5); // save as inactive profile
define('SPROF_DEL_PROFILE',  -6); // delete saved profile (return to defaults)
define('SPROF_LOAD_DEFAULT', -7); // (internal): load-default-profile (=active profile)

// suffix for profile-selection-action for table-filter-controls
define('SPFORM_PROFILE_ACTION', 'profact_sp');

 /*!
  * \class SearchProfile
  *
  * \brief Class to handle single search-profile (using Profile-DAO).
  */
class SearchProfile
{
   /*! \brief user ID */
   var $user_id;
   /*! \brief Profile-type defined by (PROFTYPE_) */
   var $profile_type;
   /*! \brief Prefix to be used to read profile-action from _REQUEST. */
   var $prefix;

   /*! \brief single Profile-object read from DB or newly created (with id=0). */
   var $profile;
   /*! \brief Array with regex-parts identifying arg-names to save profile for. */
   var $saveargs;
   /*! \brief Set with registered arg-names [ argname => 1 ]. */
   var $argnames;
   /*! \brief Map with values for args [ argname => argvalue ]. */
   var $args;

   /*! \brief true, if an arg-reset should be done (reset=set-to-defaults). */
   var $need_reset;
   /*! \brief true, if args should be cleared (clear=make-empty). */
   var $need_clear;

   /*! \brief Constructs SearchProfile for user and given profile-type. */
   function SearchProfile( $user_id, $proftype, $prefix='' )
   {
      $this->user_id = $user_id;
      $this->profile_type = $proftype;
      $this->prefix = $prefix;

      $this->saveargs = array();
      $this->argnames = array();
      $this->profile = NULL;
      $this->args = NULL;
      $this->need_reset = false;
      $this->need_clear = false;

      $this->load_profile();
   }

   /*! \brief Sets profile-var, if profile found; false otherwise and profile-var is NULL. */
   function load_profile()
   {
      $this->profile = NULL;

      // load first profile (should only be one)
      $arr_profiles = Profile::load_profiles( $this->user_id, $this->profile_type );
      if( is_array($arr_profiles) && count($arr_profiles) > 0 )
         $this->profile = $arr_profiles[0];
   }

   /*!
    * \brief Registers argument-names as regex-parts to identify args to save.
    * \param $regex regex-part to identify arg-names needed to save profile
    */
   function register_regex_save_args( $regex )
   {
      $this->saveargs[] = $regex;
   }

   /*!
    * \brief Returns regex built from regex added by register_save_args-func.
    * \internal
    */
   function _build_regex_save_args()
   {
      $regex = (count($this->saveargs) > 0) ? implode('|', $this->saveargs) : "[a-z]\w*";
      return "/^($regex)$/i";
   }

   /*!
    * \brief Registers argument-names, that are provided as profile.
    * \param $arg_names array with arg-names or string
    */
   function register_argnames( $arg_names )
   {
      if( is_string($arg_names) )
      {
         $this->argnames[$arg_names] = 1;
      }
      elseif( is_array($arg_names) )
      {
         foreach( $arg_names as $name )
            $this->argnames[$name] = 1;
      }
   }

   /*! \brief Returns true, if user has a saved (and optionally active) profile. */
   function has_profile( $chk_active=false )
   {
      if( !is_null($this->profile) && ($this->profile->id > 0) )
         $has_profile = ($chk_active) ? $this->profile->active : true;
      else
         $has_profile = false;
      return $has_profile;
   }

   /*!
    * \brief Returns value from saved-profile for given arg-name;
    *        NULL, if arg has not been saved.
    */
   function get_arg( $name )
   {
      if( $this->need_reset )
         $result = NULL;
      elseif( $this->need_clear )
         $result = '';
      elseif( is_array($this->args) )
      {
         if( isset($this->args[$name]) )
            $result = $this->args[$name];
         elseif( isset($this->argnames[$name]) )
            $result = ''; // overwrite (clear)
         else
            $result = NULL; // need default-value
      }
      else
         $result = NULL; // need default-value
      return $result;
   }

   /*! \brief Builds map with data to save for profile. */
   function build_save_data( $arr_in )
   {
      $arrdata = array();
      $regex = $this->_build_regex_save_args();
      foreach( $arr_in as $key => $val )
      {
         if( preg_match( $regex, $key ) )
            $arrdata[$key] = $val;
      }
      return $arrdata;
   }

   /*!
    * \brief Handles profile-action read from according form-entry:
    *        use current-values in _REQUEST-var,
    *        clear or reset values (needing additional external action),
    *        load, save or delete profile (keeping current form-values).
    * NOTE: argnames not complete!!
    */
   function handle_action()
   {
      // load default-profile (if no action set)
      $prof_fname = $this->prefix . SPFORM_PROFILE_ACTION;
      $prof_action = (int)get_request_arg( $prof_fname, SPROF_LOAD_DEFAULT );

      $this->args = NULL;
      switch( (int)$prof_action )
      {
         case SPROF_CURR_VALUES:    // no-action, take values from _REQUEST
            break;

         case SPROF_RESET_VALUES:
            $this->need_reset = true;
            break;

         case SPROF_CLEAR_VALUES:
            $this->need_clear = true;
            break;

         case SPROF_SAVE_DEFAULT:   // save profile (active=default or inactive)
         case SPROF_SAVE_PROFILE:
            $this->save_profile( ($prof_action == SPROF_SAVE_DEFAULT) );
            break;

         case SPROF_DEL_PROFILE:    // delete profile(s) for user
            if( !is_null($this->profile) )
               $this->profile->delete_all_profiles( $this->user_id, $this->profile_type );
            $this->load_profile();
            break;

         case SPROF_LOAD_PROFILE:   // load profile
         case SPROF_LOAD_DEFAULT:   // load (active) default profile
         default:
            $chk_active = ($prof_action != SPROF_LOAD_PROFILE);
            if( $this->has_profile($chk_active) )
               $this->args = $this->profile->get_text();
            break;
      }
      //error_log("SearchProfile.handle_action($prof_action): ".$this->to_string());
   }

   /*! \brief Saves profile: save values from _REQUEST for registered arg-names. */
   function save_profile( $save_default )
   {
      // build profile-data to save
      $arr_savedata = $this->build_save_data( $_REQUEST );

      if( $this->has_profile() )
         $profile = $this->profile;
      else
         $profile = Profile::new_profile( $this->user_id, $this->profile_type );
      $profile->active = (bool)$save_default;
      $profile->set_text( $arr_savedata );
      $profile->update_profile();
      $this->profile = $profile;

      $this->set_sysmessage( (bool)$save_default
         ? T_('Profile saved as default!')
         : T_('Profile saved!') );
   }

   /*! \brief Sets sys-message. */
   function set_sysmessage( $msg )
   {
      //FIXME: get_magic_quotes_gpc-func is deprecated and will be removed, -> really ?
      //       check quick_common.php arg_stripslashes-func
      $_REQUEST['sysmsg'] = ( get_magic_quotes_gpc() ) ? addslashes($msg) : $msg;
   }

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   function to_string()
   {
      if( is_null($this->args) )
         $args_str = ', NULL';
      else
      {
         $args_str = '';
         foreach( $this->args as $k => $v )
            $args_str .= ", $k=[$v]";
      }

      return "SearchProfile(id={$this->user_id},type={$this->profile_type}): "
         . "prefix=[{$this->prefix}], "
         . "saveargs=[".implode(',', $this->saveargs)."], "
         . "argnames=[".implode(',', array_keys($this->argnames))."], "
         . "need_reset=[{$this->need_reset}], "
         . "need_clear=[{$this->need_clear}], "
         . (is_null($this->profile) ? 'profile=[NULL]' : $this->profile->to_string() ) . ', '
         . 'args=['.substr($args_str,2).']';
   }

   /*! \brief Returns RequestParameters with form-element-names (to be included into page-links). */
   function get_request_params()
   {
      $rp = new RequestParameters();
      $rp->add_entry( $this->prefix . SPFORM_PROFILE_ACTION, SPROF_CURR_VALUES );
      return $rp;
   }

   /*!
    * \brief Returns (at least one) form-elements to manage search-profile
    *        to be used with Table or External-Form (submit not included).
    * \param $form instance of Form-class
    */
   function get_form_elements( $form )
   {
      $arr_actions = array(
         SPROF_CURR_VALUES   => T_('Current values#filter'),
         SPROF_CLEAR_VALUES  => T_('Clear values#filter'),
         SPROF_RESET_VALUES  => T_('Reset values#filter'),
         SPROF_SAVE_DEFAULT  => T_('Save as default#filter'),
         SPROF_LOAD_PROFILE  => T_('Load profile#filter'),
         SPROF_SAVE_PROFILE  => T_('Save profile#filter'),
         SPROF_DEL_PROFILE   => T_('Delete profile#filter'),
      );
      if( !$this->has_profile() ) // has no saved profile
      {
         unset($arr_actions[SPROF_LOAD_PROFILE]);
         unset($arr_actions[SPROF_DEL_PROFILE]);
      }

      // selectbox with profile-actions
      $elems = $form->print_insert_select_box(
         $this->prefix . SPFORM_PROFILE_ACTION, 1, $arr_actions,
         SPROF_CURR_VALUES ); // curr-values is "normal"-action

      return $elems;
   }

} // end of 'SearchProfile'

?>
