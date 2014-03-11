<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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


$TranslateGroups[] = "Users";

require_once 'include/std_functions.php';
require_once 'include/dgs_cache.php';


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
define('PROFTYPE_FILTER_TOURNAMENT_NEWS', 23);
define('PROFTYPE_FILTER_BULLETINS', 24);
define('PROFTYPE_FILTER_SURVEYS', 25);
define('PROFTYPE_FILTER_SHAPES', 26);
define('PROFTYPE_TMPL_SENDMSG', 27);
define('PROFTYPE_TMPL_INVITE', 28);
define('PROFTYPE_TMPL_NEWGAME', 29);
// adjust if adding one
define('MAX_PROFTYPE', 29);

define('SEP_PROFVAL', '&'); // separator of fields (text stored in DB)

 /*!
  * \class Profile
  *
  * \brief DAO and DB-load/save to manage search-profiles and form-profiles.
  */
class Profile
{
   /*! \brief ID (PK from db). */
   public $id;
   /*! \brief user-id for profile. */
   public $uid;
   /*! \brief type (must be one of PROFTYPE_). */
   public $Type;
   /*! \brief sort-order (1..n). */
   public $SortOrder;
   /*! \brief bool active (Y|N-enum in DB). */
   public $Active;
   /*! \brief user-chosen name */
   public $Name;
   /*! \brief Date when profile has been lastchanged (unix-time). */
   public $Lastchanged;
   /*! \brief profile-content/url/address. */
   public $Text;

   /*!
    * \brief Constructs Profile-object with specified arguments: lastchanged are in UNIX-time.
    *        $id may be 0 to add a new profile
    * \param $type must be non-0
    */
   public function __construct( $id=0, $uid=0, $type=0, $sortorder=1, $active=false, $name='', $lastchanged=0, $text='' )
   {
      // allowed for guests, but no DB-writing ops
      if ( !is_numeric($uid) || $uid < 0 )
         error('invalid_user', "Profile.construct($id,$uid,$type)");

      $this->set_type( $type );
      $this->id = (int) $id;
      $this->uid = (int) $uid;
      $this->SortOrder = (int) $sortorder;
      $this->Active = (bool) $active;
      $this->Name = $name;
      $this->Lastchanged = (int) $lastchanged;
      $this->Text = $text;
   }//__construct

   /*! \brief Sets valid profile-type (exception on invalid type). */
   public function set_type( $type )
   {
      if ( !is_numeric($type) || $type < 1 || $type > MAX_PROFTYPE )
         error('invalid_args', "Profile.set_type($type)");
      $this->Type = (int) $type;
   }

   /*!
    * \brief Sets profile-content built from given array (URL-like) or raw-text (converted to string).
    * \param $arr null, array with key-values, or raw-text
    */
   public function set_text( $arr )
   {
      if ( is_null($arr) )
         $this->Text = '0'; // representation of NULL
      elseif ( is_array($arr) )
         $this->Text = build_url( $arr, false, SEP_PROFVAL );
      else
         $this->Text = (string)$arr; // raw-format
   }//set_text

   /*!
    * \brief Returns parsed profile-content as array with splitted values, or as raw-text ($raw=true).
    */
   public function get_text( $raw=false )
   {
      if ( $raw )
         return $this->Text;
      else
      {
         if ( is_null($this->Text) || (string)$this->Text == '0' )
            return NULL;
         else
         {
            // NOTE: array-value must not be NULL(!)
            split_url( '?'.$this->Text, $prefix, $arr_out, SEP_PROFVAL );
            return $arr_out;
         }
      }
   }//get_text

   /*!
    * \brief Inserts or updates current profile-data in database (may replace existing profile);
    *        Set id to 0 if you want an insert.
    */
   public function save_profile( $check_user=false )
   {
      $dbgmsg = "Profile.save_profile({$this->id},{$this->uid},{$this->Type})";
      if ( $this->uid <= GUESTS_ID_MAX )
         error('not_allowed_for_guest', $dbgmsg.'.check.uid');

      if ( $check_user )
      {
         $row = mysql_single_fetch( $dbgmsg.'.find_user',
            "SELECT ID FROM Players WHERE ID={$this->uid} LIMIT 1" );
         if ( !$row )
            error('unknown_user', $dbgmsg.'.find_user2');
      }

      $this->Lastchanged = $GLOBALS['NOW'];

      $upd = new UpdateQuery('Profiles');
      $upd->upd_num('ID', (int)$this->id );
      $upd->upd_num('User_ID', (int)$this->uid );
      $upd->upd_num('Type', (int)$this->Type );
      $upd->upd_num('SortOrder', (int)$this->SortOrder );
      $upd->upd_bool('Active', $this->Active );
      $upd->upd_txt('Name', $this->Name );
      $upd->upd_time('Lastchanged', (int)$this->Lastchanged );
      $upd->upd_txt('Text', $this->Text ); // blob

      ta_begin();
      {//HOT-section to save game profile
         db_query( $dbgmsg.'.replace', "REPLACE INTO Profiles SET " . $upd->get_query() );
         self::delete_profile_cache( $dbgmsg, $this->uid, $this->Type );
      }
      ta_end();
   }//save_profile

   /*! \brief Deletes current profile from database. */
   public function delete_profile()
   {
      $dbgmsg = "Profile.delete_profile({$this->id},{$this->uid},{$this->Type})";
      if ( $this->uid <= GUESTS_ID_MAX )
         error('not_allowed_for_guest', $dbgmsg.'check.uid');

      if ( $this->id > 0 )
      {
         ta_begin();
         {//HOT-section to delete game profile
            db_query( $dbgmsg.'.del', "DELETE FROM Profiles WHERE ID='{$this->id}' LIMIT 1" );
            self::delete_profile_cache( $dbgmsg, $this->uid, $this->Type );
         }
         ta_end();
      }
   }//delete_profile_cache

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   public function to_string()
   {
      return "Profile(id={$this->id}): "
         . "user_id=[{$this->uid}], "
         . "type=[{$this->Type}], "
         . "sortorder=[{$this->SortOrder}], "
         . "active=[{$this->Active}], "
         . "name=[{$this->Name}], "
         . "lastchanged=[{$this->Lastchanged}], "
         . "text=[{$this->Text}]";
   }//to_string


   // ---------- Static Class functions ----------------------------

   /*! \brief Returns db-fields to be used for query of Profile-object. */
   private static function get_query_fields()
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
   public static function new_profile( $uid, $type, $prof_id=0 )
   {
      // id=set, uid=?, Type=?, SortOrder=1, Active=false, Name='', Lastchanged=NOW, Text=''
      $profile = new Profile( $prof_id, $uid, $type );
      $profile->Lastchanged = $GLOBALS['NOW'];
      return $profile;
   }

   /*! \brief Returns Profile-object lastchanged from specified (db-)row with fields defined by func fields_profile. */
   private static function new_from_row( $row )
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
   public static function load_profile_by_id( $prof_id, $user_id )
   {
      if ( !is_numeric($prof_id) || !is_numeric($user_id) )
         error('invalid_args', "Profile:load_profile_by_id($prof_id,$user_id)");

      $fields = implode(',', self::get_query_fields());
      $row = mysql_single_fetch("Profile:load_profile_by_id.find($prof_id,$user_id)",
            "SELECT $fields FROM Profiles WHERE ID='$prof_id' AND User_ID='$user_id' LIMIT 1");
      if ( !$row )
         return NULL;

      return self::new_from_row( $row );
   }//load_profile_by_id

   /*!
    * \brief Returns list of Profile-objects for specified user-id and type (in SortOrder).
    * \param $types single type or array of types
    * \param $load_templates true to order by profile-template related stuff
    * \return array( Profile, ...); otherwise empty-array if no profile found
    */
   public static function load_profiles( $user_id, $types, $load_templates=false )
   {
      if ( !is_numeric($user_id) )
         error('invalid_args', "Profile:load_profiles.check.user($user_id)");
      if ( !is_array($types) )
      {
         if ( !is_numeric($types) )
            error('invalid_args', "Profile:load_profiles.check.type($user_id,$types)");
         else
            $types = array( $types );
      }
      $cnt_types = count($types);
      if ( is_array($types) && $cnt_types == 0 )
         error('invalid_args', "Profile:load_profiles.check.types($user_id,$types)");

      $use_cache = ( $cnt_types == 1 && !$load_templates );
      $sql_types = implode(',', $types);
      $dbgmsg = "Profile:load_profiles($user_id,$load_templates,T[$sql_types])";
      $key = "Profile.$user_id.$sql_types";

      $arr_profiles = ( $use_cache ) ? DgsCache::fetch( $dbgmsg, CACHE_GRP_PROFILE, $key ) : null;
      if ( is_null($arr_profiles) )
      {
         $fields = implode(',', self::get_query_fields());
         $db_result = db_query( $dbgmsg,
               "SELECT $fields FROM Profiles " .
               "WHERE User_ID=$user_id AND Type IN ($sql_types) " .
               "ORDER BY " . ( $load_templates ? "Type,Name,ID" : "SortOrder,ID LIMIT 1" ) );

         $arr_profiles = array();
         while ( ($row = mysql_fetch_assoc($db_result)) )
            $arr_profiles[] = self::new_from_row($row);
         mysql_free_result($db_result);

         if ( $use_cache )
            DgsCache::store( $dbgmsg, CACHE_GRP_PROFILE, $key, $arr_profiles, SECS_PER_DAY );
      }

      return $arr_profiles;
   }//load_profiles

   public static function delete_profile_cache( $dbgmsg, $uid, $type )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_PROFILE, "Profile.$uid.$type" );
   }

   /*! \brief Deletes all profiles for specific user-id and type from database. */
   public static function delete_all_profiles( $uid, $type )
   {
      $dbgmsg = "Profile:delete_all_profiles($uid,$type)";
      if ( !is_numeric($uid) || $uid <= GUESTS_ID_MAX )
         error('not_allowed_for_guest', $dbgmsg.'.check.uid');
      if ( !is_numeric($type) || $type < 0 || $type > MAX_PROFTYPE )
         error('invalid_args', $dbgmsg.'.check.type');

      ta_begin();
      {//HOT-section to delete game profile
         db_query( $dbgmsg.'.del', "DELETE FROM Profiles WHERE User_ID=$uid AND Type=$type" );
         self::delete_profile_cache( $dbgmsg, $uid, $type );
      }
      ta_end();
   }//delete_all_profiles

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
   private $user_id;
   /*! \brief Profile-type defined by (PROFTYPE_) */
   private $profile_type;
   /*! \brief Prefix to be used to read profile-action from _REQUEST. */
   private $prefix;
   /*! \brief if true, no default-profile is allowed to be used (normally false).  */
   private $forbid_default;

   /*! \brief single Profile-object read from DB or newly created (with id=0). */
   private $profile = null;
   /*! \brief Array with regex-parts identifying arg-names to save profile for. */
   private $saveargs = array();
   /*! \brief Set with registered arg-names [ argname => 1 ]. */
   private $argnames = array();
   /*! \brief Map with values for args [ argname => argvalue ]. */
   private $args = null;

   /*! \brief true, if an arg-reset should be done (reset=set-to-defaults). */
   private $need_reset = false;
   /*! \brief true, if args should be cleared (clear=make-empty). */
   private $need_clear = false;
   /*! \brief true, if URL-args have precedence over profile-values (used for SPROF_CURR_VALUES). */
   private $skip_profile_values = false;
   /*! \brief true, if URL-args have precedence over profile-values (used for loading profile). */
   private $use_url_args = false;

   /*! \brief Constructs SearchProfile for user and given profile-type. */
   public function __construct( $user_id, $proftype, $prefix='' )
   {
      $this->user_id = $user_id;
      $this->profile_type = $proftype;
      $this->prefix = $prefix;
      $this->forbid_default = get_request_arg(SP_ARG_NO_DEF, false);

      $this->load_profile();
   }

   public function set_forbid_default()
   {
      $this->forbid_default = true;
   }

   public function need_reset()
   {
      return $this->need_reset;
   }

   public function need_clear()
   {
      return $this->need_clear;
   }

   /*! \brief Sets profile-var, if profile found; false otherwise and profile-var is NULL. */
   public function load_profile()
   {
      $this->profile = NULL;

      // load first profile (should only be one)
      $arr_profiles = Profile::load_profiles( $this->user_id, $this->profile_type );
      if ( is_array($arr_profiles) && count($arr_profiles) > 0 )
         $this->profile = $arr_profiles[0];
   }//load_profile

   /*!
    * \brief Registers argument-names as regex-parts to identify args to save.
    * \param $regex regex-part to identify arg-names needed to save profile
    */
   public function register_regex_save_args( $regex )
   {
      $this->saveargs[] = $regex;
   }

   /*!
    * \brief Returns regex built from regex added by register_save_args-func.
    * \internal
    */
   private function _build_regex_save_args()
   {
      $regex = (count($this->saveargs) > 0) ? implode('|', $this->saveargs) : "[a-z]\w*";
      return "/^($regex)$/i";
   }

   /*!
    * \brief Registers argument-names, that are provided as profile.
    * \param $arg_names array with arg-names or string
    */
   public function register_argnames( $arg_names )
   {
      if ( is_string($arg_names) )
      {
         $this->argnames[$arg_names] = 1;
      }
      elseif ( is_array($arg_names) )
      {
         foreach ( $arg_names as $name )
            $this->argnames[$name] = 1;
      }
   }//register_argnames

   /*! \brief Returns true, if user has a saved (and optionally active) profile. */
   public function has_profile( $chk_active=false )
   {
      if ( !is_null($this->profile) && ($this->profile->id > 0) )
         $has_profile = ($chk_active) ? $this->profile->Active : true;
      else
         $has_profile = false;
      return $has_profile;
   }//has_profile

   /*!
    * \brief Returns value from saved-profile for given arg-name;
    *        NULL, if arg has not been saved (using default-value).
    */
   public function get_arg( $name )
   {
      if ( $this->need_reset )
         $result = NULL;
      elseif ( $this->need_clear )
         $result = '';
      elseif ( $this->skip_profile_values )
      {
         if ( isset($_REQUEST[$name]) )
            $result = get_request_arg($name);
         else
            $result = NULL;
      }
      elseif ( is_array($this->args) )
      {
         if ( $this->use_url_args && isset($_REQUEST[$name]) ) // URL-args have precedence over profile-data
            $result = get_request_arg($name);
         elseif ( isset($this->args[$name]) ) // profile-data
            $result = $this->args[$name];
         elseif ( isset($this->argnames[$name]) )
            $result = ''; // overwrite (clear)
         else
            $result = NULL;
      }
      else
         $result = NULL;
      #error_log("Profile.get_arg($name)=[".(is_null($result)?'NULL':$result)."]");
      return $result;
   }//get_arg

   /*! \brief Builds map with data to save for profile. */
   private function build_save_data( $arr_in )
   {
      $arrdata = array();
      $regex = $this->_build_regex_save_args();
      foreach ( $arr_in as $key => $val )
      {
         if ( preg_match( $regex, $key ) )
            $arrdata[$key] = $val;
      }
      return $arrdata;
   }//build_save_data

   /*!
    * \brief Handles profile-action read from according form-entry:
    *        use current-values in _REQUEST-var,
    *        clear or reset values (needing additional external action),
    *        load, save or delete profile (keeping current form-values).
    * \param $prof_action null = use user-action from form
    * \note NOTE: argnames not complete!!
    */
   public function handle_action( $prof_action=null )
   {
      if ( is_null($prof_action) )
      {
         // load default-profile (if no action set and allowed)
         $default_action = ( $this->forbid_default ) ? SPROF_CURR_VALUES : SPROF_LOAD_DEFAULT;

         $prof_fname = $this->prefix . SPFORM_PROFILE_ACTION;
         $prof_action = (int)get_request_arg( $prof_fname, $default_action );
      }

      $this->args = NULL;
      switch ( (int)$prof_action )
      {
         case SPROF_CURR_VALUES:    // no-action, take values from _REQUEST
            $this->skip_profile_values = true;
            break;

         case SPROF_RESET_VALUES:
            $this->need_reset = true;
            break;

         case SPROF_CLEAR_VALUES:
            $this->need_clear = true;
            break;

         case SPROF_SAVE_DEFAULT:   // save profile (active=default or inactive)
            if ( $this->forbid_default )
               error('invalid_args', "SearchProfile.handle_action.save_default.forbidden({$this->profile_type})");
         case SPROF_SAVE_PROFILE:
            $this->save_profile( ($prof_action == SPROF_SAVE_DEFAULT) );
            break;

         case SPROF_DEL_PROFILE:    // delete profile(s) for user
            if ( !is_null($this->profile) )
               Profile::delete_all_profiles( $this->user_id, $this->profile_type );
            $this->load_profile();
            break;

         case SPROF_LOAD_PROFILE:   // load profile
         case SPROF_LOAD_DEFAULT:   // load (active) default profile
         default:
            if ( $prof_action == SPROF_LOAD_DEFAULT )
               $this->use_url_args = true;

            $chk_active = ($prof_action != SPROF_LOAD_PROFILE);
            if ( $this->has_profile($chk_active) )
               $this->args = $this->profile->get_text();
            break;
      }
      #error_log("SearchProfile.handle_action($prof_action): ".$this->to_string());
   }//handle_action

   /*! \brief Saves profile: save values from _REQUEST for registered arg-names. */
   public function save_profile( $save_default )
   {
      // build profile-data to save
      $arr_savedata = $this->build_save_data( $_REQUEST );

      if ( $this->has_profile() )
         $profile = $this->profile;
      else
         $profile = Profile::new_profile( $this->user_id, $this->profile_type );
      $profile->Active = (bool)$save_default;
      $profile->set_text( $arr_savedata );
      $profile->save_profile();
      $this->profile = $profile;

      $this->set_sysmessage( (bool)$save_default
         ? T_('Profile saved as default!')
         : T_('Profile saved!') );
   }//save_profile

   /*! \brief Sets sys-message. */
   public function set_sysmessage( $msg )
   {
      $_REQUEST['sysmsg'] = ( get_magic_quotes_gpc() ) ? addslashes($msg) : $msg;
   }

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   public function to_string()
   {
      if ( is_null($this->args) )
         $args_str = ', NULL';
      else
      {
         $args_str = '';
         foreach ( $this->args as $k => $v )
            $args_str .= ", $k=[$v]";
      }

      return "SearchProfile(id={$this->user_id},type={$this->profile_type}): "
         . "prefix=[{$this->prefix}], "
         . "saveargs=[".implode(',', $this->saveargs)."], "
         . "argnames=[".implode(',', array_keys($this->argnames))."], "
         . "need_reset=[{$this->need_reset}], "
         . "need_clear=[{$this->need_clear}], "
         . "skip_prof_vals=[{$this->skip_profile_values}], "
         . "use_url_args=[{$this->use_url_args}], "
         . (is_null($this->profile) ? 'profile=[NULL]' : $this->profile->to_string() ) . ', '
         . 'args=['.substr($args_str,2).']';
   }//to_string

   /*! \brief Returns RequestParameters with form-element-names (to be included into page-links). */
   public function get_request_params()
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
   public function get_form_elements( $form )
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
      if ( $this->forbid_default )
         unset($arr_actions[SPROF_SAVE_DEFAULT]);
      if ( !$this->has_profile() ) // has no saved profile
      {
         unset($arr_actions[SPROF_LOAD_PROFILE]);
         unset($arr_actions[SPROF_DEL_PROFILE]);
      }

      // selectbox with profile-actions
      $elems = $form->print_insert_select_box(
         $this->prefix . SPFORM_PROFILE_ACTION, 1, $arr_actions,
         SPROF_CURR_VALUES ); // curr-values is "normal"-action

      return $elems;
   }//get_form_elements

} // end of 'SearchProfile'

?>
