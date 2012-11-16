<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once( 'include/globals.php' );
require_once( 'include/classlib_bitset.php' );
require_once( 'include/dgs_cache.php' );

 /* Author: Jens-Uwe Gaspar */

/*!
 * \file classlib_userconfig.php
 *
 * \brief Functions for user config: tables ConfigBoard, ConfigPages
 */



 /*!
  * \class ConfigBoard
  *
  * \brief Class to manage ConfigBoard-table
  *
  * Examples:
  *    $cfg->insert_default( $user_id );
  *
  *    $cfg = ConfigBoard::load_config_board( $user_id );
  *    $cfg->set_stone_size(25);
  *    $cfg->update_config_...();
  */

// for getter/setter of ConfigBoard
define('CFGBOARD_NOTES_SMALL', 'Small');
define('CFGBOARD_NOTES_LARGE', 'Large');

// Board-Flags
define('BOARDFLAG_MARK_LAST_CAPTURE', 0x01);

// Boardcoords
define('COORD_LEFT',    0x001); // short coordinates left from board
define('COORD_UP',      0x002);
define('COORD_RIGHT',   0x004);
define('COORD_DOWN',    0x008);
define('SMOOTH_EDGE',   0x010); // short board with smooth edges (for textured woodcolor <10 only)
define('COORD_OVER',    0x020); // show A1-like coordinates over board-point
define('COORD_SGFOVER', 0x040); // show AA-like (SGF-like) coordinates over board-point
define('NUMBER_OVER',   0x080); // show number of move over board-point (used for tiny stone-size)
define('COORD_RELATIVE_MOVENUM', 0x100); // relative move-numbering (previous move to current is n)
define('COORD_REVERSE_MOVENUM',  0x200); // reverse move-numbering (previous move to current is 1)
define('COORD_MASK', (COORD_UP|COORD_RIGHT|COORD_DOWN|COORD_LEFT));

class ConfigBoard
{
   var $user_id;
   var $stone_size;
   var $wood_color;
   var $board_flags;
   var $board_coords;
   var $move_numbers;
   var $move_modulo;
   var $notes_height; // arr[SMALL|LARGE] =
   var $notes_width; // arr[SMALL|LARGE] =
   var $notes_mode; // arr[SMALL|LARGE] =
   var $notes_cutoff;

   /*! \brief Constructs ConfigBoard-object with specified arguments. */
   function ConfigBoard( $user_id, $stone_size=25, $wood_color=1, $board_flags=0, $boardcoords=31,
                         $move_numbers=0, $move_modulo=0,
                         $notes_small_height=25, $notes_small_width=30, $notes_small_mode='RIGHT',
                         $notes_large_height=25, $notes_large_width=30, $notes_large_mode='RIGHT',
                         $notes_cutoff=13 )
   {
      ConfigPages::_check_user_id( $user_id, 'ConfigBoard', /*allow-0*/true );

      $this->user_id = (int)$user_id;
      $this->notes_height = array();
      $this->notes_width = array();
      $this->notes_mode = array();

      $this->set_stone_size( (int)$stone_size );
      $this->set_wood_color( (int)$wood_color );
      $this->set_board_flags( (int)$board_flags );
      $this->set_board_coords( (int)$boardcoords );
      $this->set_move_numbers( (int)$move_numbers );
      $this->set_move_modulo( (int)$move_modulo );
      $this->set_notes_height( CFGBOARD_NOTES_SMALL, (int)$notes_small_height );
      $this->set_notes_height( CFGBOARD_NOTES_LARGE, (int)$notes_large_height );
      $this->set_notes_width( CFGBOARD_NOTES_SMALL, (int)$notes_small_width );
      $this->set_notes_width( CFGBOARD_NOTES_LARGE, (int)$notes_large_width );
      $this->set_notes_mode( CFGBOARD_NOTES_SMALL, $notes_small_mode );
      $this->set_notes_mode( CFGBOARD_NOTES_LARGE, $notes_large_mode );
      $this->set_notes_cutoff( (int)$notes_cutoff );
   }

   function get_user_id()
   {
      return $this->user_id;
   }

   function get_stone_size()
   {
      return $this->stone_size;
   }

   /*! \brief Sets valid stone size [5..50]; if invalid set to default 25. */
   function set_stone_size( $stone_size=25 )
   {
      if( is_numeric($stone_size) && $stone_size >= 5 && $stone_size <= 50 )
         $this->stone_size = (int)$stone_size;
      else
         $this->stone_size = 25;
   }

   function get_wood_color()
   {
      return $this->wood_color;
   }

   /*! \brief Sets valid wood-color [1..5,11..15]; if invalid set to default 1. */
   function set_wood_color( $wood_color=1 )
   {
      if( ( $wood_color >= 1 && $wood_color <= 5 ) || ( $wood_color >= 11 && $wood_color <= 15 ) )
         $this->wood_color = (int)$wood_color;
      else
         $this->wood_color = 1;
   }

   function get_board_flags()
   {
      return $this->board_flags;
   }

   function set_board_flags( $board_flags )
   {
      $this->board_flags = (int)$board_flags;
   }

   function get_board_coords()
   {
      return $this->board_coords;
   }

   function set_board_coords( $board_coords=-1 )
   {
      if( is_numeric($board_coords) )
         $this->board_coords = (int)$board_coords;
      else
         $this->board_coords = COORD_MASK | SMOOTH_EDGE;
   }

   function get_move_numbers()
   {
      return $this->move_numbers;
   }

   function set_move_numbers( $move_numbers )
   {
      $this->move_numbers = (int)$move_numbers;
   }

   function get_move_modulo()
   {
      return $this->move_modulo;
   }

   function set_move_modulo( $move_modulo )
   {
      $this->move_modulo = (int)$move_modulo;
   }

   // size=CFGBOARD_NOTES_LARGE|CFGBOARD_NOTES_SMALL
   function get_notes_height( $size )
   {
      return @$this->notes_height[$size];
   }

   // size=CFGBOARD_NOTES_LARGE|CFGBOARD_NOTES_SMALL
   function set_notes_height( $size, $notes_height )
   {
      $this->notes_height[$size] = (int)$notes_height;
   }

   // size=CFGBOARD_NOTES_LARGE|CFGBOARD_NOTES_SMALL
   function get_notes_width( $size )
   {
      return @$this->notes_width[$size];
   }

   // size=CFGBOARD_NOTES_LARGE|CFGBOARD_NOTES_SMALL
   function set_notes_width( $size, $notes_width )
   {
      $this->notes_width[$size] = (int)$notes_width;
   }

   // size=CFGBOARD_NOTES_LARGE|CFGBOARD_NOTES_SMALL
   function get_notes_mode( $size )
   {
      return @$this->notes_mode[$size];
   }

   // size=CFGBOARD_NOTES_LARGE|CFGBOARD_NOTES_SMALL
   function set_notes_mode( $size, $notes_mode )
   {
      $this->notes_mode[$size] = $notes_mode;
   }

   function get_notes_cutoff()
   {
      return $this->notes_cutoff;
   }

   // return config-type for notes: small or large
   function get_cfgsize_notes( $size )
   {
      return ( $size >= $this->notes_cutoff )
         ? CFGBOARD_NOTES_LARGE
         : CFGBOARD_NOTES_SMALL;
   }

   // size=CFGBOARD_NOTES_LARGE|CFGBOARD_NOTES_SMALL
   function set_notes_cutoff( $notes_cutoff )
   {
      $this->notes_cutoff = (int)$notes_cutoff;
   }

   /*! \brief Updates current ConfigBoard-data into database. */
   function update_all()
   {
      ConfigPages::_check_user_id( $this->user_id, 'ConfigBoard::update_all', /*allow0*/false );

      $update_query = 'UPDATE ConfigBoard SET'
         . '  Stonesize=' . $this->stone_size
         . ', Woodcolor=' . $this->wood_color
         . ', BoardFlags=' . $this->board_flags
         . ', Boardcoords=' . $this->board_coords
         . ', MoveNumbers=' . $this->move_numbers
         . ', MoveModulo=' . $this->move_modulo
         . ', NotesSmallHeight=' . $this->get_notes_height(CFGBOARD_NOTES_SMALL)
         . ', NotesSmallWidth=' . $this->get_notes_width(CFGBOARD_NOTES_SMALL)
         . ", NotesSmallMode='" . mysql_addslashes($this->get_notes_mode(CFGBOARD_NOTES_SMALL)) . "'"
         . ', NotesLargeHeight=' . $this->get_notes_height(CFGBOARD_NOTES_LARGE)
         . ', NotesLargeWidth=' . $this->get_notes_width(CFGBOARD_NOTES_LARGE)
         . ", NotesLargeMode='" . mysql_addslashes($this->get_notes_mode(CFGBOARD_NOTES_LARGE)) . "'"
         . ', NotesCutoff=' . $this->notes_cutoff
         . " WHERE User_ID='{$this->user_id}' LIMIT 1";
         ;
      db_query( "ConfigBoard::update_all.update({$this->user_id})", $update_query );
      ConfigBoard::delete_cache_config_board( $this->user_id );
   }

   /*! \brief Fills current values into $player_row. */
   function fill_player_row()
   {
      global $player_row;
      $player_row['Stonesize'] = $this->stone_size;
      $player_row['Woodcolor'] = $this->wood_color;
      $player_row['BoardFlags'] = $this->board_flags;
      $player_row['Boardcoords'] = $this->board_coords;
      $player_row['MoveNumbers'] = $this->move_numbers;
      $player_row['MoveModulo'] = $this->move_modulo;
      $player_row['NotesSmallHeight'] = $this->notes_height[CFGBOARD_NOTES_SMALL];
      $player_row['NotesSmallWidth'] = $this->notes_width[CFGBOARD_NOTES_SMALL];
      $player_row['NotesSmallMode'] = $this->notes_mode[CFGBOARD_NOTES_SMALL];
      $player_row['NotesLargeHeight'] = $this->notes_height[CFGBOARD_NOTES_LARGE];
      $player_row['NotesLargeWidth'] = $this->notes_width[CFGBOARD_NOTES_LARGE];
      $player_row['NotesLargeMode'] = $this->notes_mode[CFGBOARD_NOTES_LARGE];
      $player_row['NotesCutoff'] = $this->notes_cutoff;
   }

   // ------------ static functions ----------------------------

   /*! \brief (static) Loads ConfigBoard-data for given user. */
   function load_config_board( $uid, $force_load=false, $upd_player_row=true )
   {
      ConfigPages::_check_user_id( $uid, 'ConfigBoard::load_config_board');

      // read values from cookies if logged in
      global $player_row;
      $src_row = ( isset($player_row['Handle']) && isset($player_row['Stonesize']) ) ? $player_row : NULL;
      $need_load = is_null($src_row);

      if( $force_load || $need_load )
      {
         $dbgmsg = "ConfigBoard::load_config_board($uid)";
         $key = "ConfigBoard.$uid";
         $src_row = DgsCache::fetch( $dbgmsg, CACHE_GRP_CFG_BOARD, $key );
         if( is_null($src_row) )
         {
            $src_row = mysql_single_fetch($dbgmsg.'find', "SELECT * FROM ConfigBoard WHERE User_ID='$uid' LIMIT 1");
            if( !is_null($src_row) )
               DgsCache::store( $dbgmsg, CACHE_GRP_CFG_BOARD, $key, $src_row, SECS_PER_DAY );
         }
         if( !$src_row )
            return null;
      }

      $config = new ConfigBoard(
            $uid,
            @$src_row['Stonesize'],
            @$src_row['Woodcolor'],
            @$src_row['BoardFlags'],
            @$src_row['Boardcoords'],
            @$src_row['MoveNumbers'],
            @$src_row['MoveModulo'],
            @$src_row['NotesSmallHeight'],
            @$src_row['NotesSmallWidth'],
            @$src_row['NotesSmallMode'],
            @$src_row['NotesLargeHeight'],
            @$src_row['NotesLargeWidth'],
            @$src_row['NotesLargeMode'],
            @$src_row['NotesCutoff']
            );
      if( $upd_player_row && $need_load )
         $config->fill_player_row();
      return $config;
   }//load_config_board

   function delete_cache_config_board( $uid )
   {
      DgsCache::delete( "ConfigBoard::delete_cache_config_board($uid)", CACHE_GRP_CFG_BOARD, "ConfigBoard.$uid" );
   }

   /*! \brief (static) Inserts default ConfigBoard. */
   function insert_default( $user_id )
   {
      ConfigPages::_check_user_id( $user_id, 'ConfigBoard::insert_default');
      db_query( "ConfigBoard::insert_default.insert({$user_id})",
         "INSERT INTO ConfigBoard SET User_ID='{$user_id}'" );
   }

} // end of 'ConfigBoard'




 /*!
  * \class ConfigPages
  *
  * \brief Class to manage ConfigPages-table
  *
  * Examples:
  *    $cfg->insert_default( $user_id );
  *
  *    $cfg = ConfigPages::load_config_pages( $user_id );
  *    $cfg->update_config();
  */

// behavior-flags for status-page
define('STATUSFLAG_SHOW_FOLDER_NEW',   0x01);
define('STATUSFLAG_SHOW_FOLDER_REPLY', 0x02);
define('DEFAULT_STATUSFLAG', (STATUSFLAG_SHOW_FOLDER_NEW|STATUSFLAG_SHOW_FOLDER_REPLY)); // also in table ConfigPages

// sync with legal-check in toggle_forum_flags-func
define('FORUMFLAG_FORUM_SHOWAUTHOR',  0x01); // last-post author
define('FORUMFLAG_THREAD_SHOWAUTHOR', 0x02);
//define('FORUMFLAG_...', 0x04); //re-use for db
define('FORUMFLAG_POSTVIEW_OVERVIEW', 0x08); // show overview
define('FORUMFLAG_POSTVIEW_FLAT_NEW_FIRST', 0x10); // both flat-flags not set => TREE-view
define('FORUMFLAG_POSTVIEW_FLAT_OLD_FIRST', 0x20);
define('FORUMFLAGS_POSTVIEW_FLAT', (FORUMFLAG_POSTVIEW_FLAT_NEW_FIRST|FORUMFLAG_POSTVIEW_FLAT_OLD_FIRST));

// Table column-sets (db-fieldname-prefix for ConfigPages-table for BitSet-handling)
define('CFGCOLS_STATUS_GAMES',            'ColumnsStatusGames');
define('CFGCOLS_STATUS_TOURNAMENTS',      'ColumnsStatusTournaments');
define('CFGCOLS_WAITINGROOM',             'ColumnsWaitingroom');
define('CFGCOLS_USERS',                   'ColumnsUsers');
define('CFGCOLS_OPPONENTS',               'ColumnsOpponents');
define('CFGCOLS_CONTACTS',                'ColumnsContacts');
define('CFGCOLS_GAMES_RUNNING_ALL',       'ColumnsGamesRunningAll');
define('CFGCOLS_GAMES_RUNNING_USER',      'ColumnsGamesRunningUser');
define('CFGCOLS_GAMES_FINISHED_ALL',      'ColumnsGamesFinishedAll');
define('CFGCOLS_GAMES_FINISHED_USER',     'ColumnsGamesFinishedUser');
define('CFGCOLS_GAMES_OBSERVED',          'ColumnsGamesObserved');
define('CFGCOLS_GAMES_OBSERVED_ALL',      'ColumnsGamesObservedAll');
define('CFGCOLS_BULLETIN_LIST',           'ColumnsBulletinList');
define('CFGCOLS_FEATURE_LIST',            'ColumnsFeatureList');
define('CFGCOLS_TOURNAMENTS',             'ColumnsTournaments');
define('CFGCOLS_TOURNAMENT_PARTICIPANTS', 'ColumnsTournamentParticipants');
define('CFGCOLS_TD_TOURNAMENT_PARTICIPANTS', 'ColumnsTDTournamentParticipants');
define('CFGCOLS_TOURNAMENT_RESULTS',      'ColumnsTournamentResults');
define('CFGCOLS_TOURNAMENT_LADDER_VIEW',  'ColumnsTournamentLadderView');
define('CFGCOLS_TOURNAMENT_POOL_VIEW',    'ColumnsTournamentPoolView');

class ConfigPages
{
   var $user_id;
   var $status_flags;
   var $status_folders;
   var $forum_flags;
   var $table_columns;

   /*! \brief Constructs ConfigPages-object with specified arguments. */
   function ConfigPages( $user_id, $status_flags=DEFAULT_STATUSFLAG, $status_folders='',
                         $forum_flags=FORUMFLAG_POSTVIEW_OVERVIEW )
   {
      ConfigPages::_check_user_id( $user_id, 'ConfigPages');

      $this->user_id = (int)$user_id;
      $this->status_flags = $status_flags;
      $this->status_folders = $status_folders;
      $this->forum_flags = $forum_flags;
      $this->table_columns = null;
   }

   function get_user_id()
   {
      return $this->user_id;
   }

   function get_status_flags()
   {
      return $this->status_flags;
   }

   /*!
    * \brief Returns visibility-state for folders (including new- and reply-folder).
    * \param $folder_nr FOLDER_NEW or FOLDER_REPLY, or other folder-nr (if all=true)
    * \param $all true=also checks other folders
    * \return 1 if given folder should be shown or 0 if to hide; -1 if $all and folder != FOLDER_NEW|REPLY
    */
   function get_status_folder_visibility( $folder_nr, $all=false )
   {
      if( $folder_nr == FOLDER_NEW )
         return ( $this->status_flags & STATUSFLAG_SHOW_FOLDER_NEW ) ? 1 : 0;
      elseif( $folder_nr == FOLDER_REPLY )
         return ( $this->status_flags & STATUSFLAG_SHOW_FOLDER_REPLY ) ? 1 : 0;
      else
      {
         if( $all && (string)$folder_nr != '' )
            return (strpos(",{$this->status_folders},", ",$folder_nr,") === false) ? 0 : 1;
         else
            return -1;
      }
   }

   /*!
    * \brief Sets bit of StatusFlags for given folder.
    * \param $folder_nr FOLDER_NEW|REPLY
    * \param $value [bool]
    */
   function set_status_flags_folderbit( $folder_nr, $value )
   {
      if( $folder_nr == FOLDER_NEW )
         $bitmask = STATUSFLAG_SHOW_FOLDER_NEW;
      elseif( $folder_nr == FOLDER_REPLY )
         $bitmask = STATUSFLAG_SHOW_FOLDER_REPLY;
      else
         return;

      if( $value )
         $this->status_flags |= $bitmask;
      else
         $this->status_flags &= ~$bitmask;
   }

   function set_status_flags( $status_flags )
   {
      $this->status_flags = $status_flags;
   }

   function get_status_folders()
   {
      return $this->status_folders;
   }

   function set_status_folders( $status_folders )
   {
      $this->status_folders = $status_folders;
   }

   /*! \brief Returns query-restriction on Folder_nr for user. */
   function get_status_folders_querypart()
   {
      $folders = array();
      if( $this->status_flags & STATUSFLAG_SHOW_FOLDER_NEW )
         $folders[] = FOLDER_NEW;
      if( $this->status_flags & STATUSFLAG_SHOW_FOLDER_REPLY )
         $folders[] = FOLDER_REPLY;

      if( (string)$this->status_folders != '' )
         $folders[] = $this->status_folders; // ','-separated folder-list

      return implode(',', $folders);
   }

   function get_forum_flags()
   {
      return $this->forum_flags;
   }

   function set_forum_flags( $forum_flags )
   {
      $this->forum_flags = $forum_flags;
   }

   /*! \brief Returns null if no table-columnset loaded. */
   function &get_table_columns()
   {
      return $this->table_columns;
   }


   /*! \brief Updates ConfigPages-data into database for field StatusFlags and StatusFolders only. */
   function update_status_folders()
   {
      ConfigPages::_check_user_id( $this->user_id, 'ConfigPages::update_status_folders');
      $arr_write = array(
            'StatusFlags'   => $this->status_flags,
            'StatusFolders' => $this->status_folders,
         );
      ConfigPages::_update_query(
         "ConfigPages::update_status_folders.update({$this->user_id})",
         $this->user_id, $arr_write );
   }

   // ------------ static functions ----------------------------

   /*! \internal (static) */
   function _check_user_id( $user_id, $loc, $allow0=false )
   {
      if( !is_numeric($user_id) || $user_id < 0 || ( !$allow0 && $user_id == 0 ) )
         error('invalid_user', "$loc.check.user_id($user_id)");
   }

   /*!
    * \brief Returns true if given folder-nr is system-folder FOLDER_NEW|REPLY
    *        for which hidden-state is managed in StatusFlags-field.
    */
   function is_system_status_folder( $folder_nr )
   {
      return ( $folder_nr == FOLDER_NEW || $folder_nr == FOLDER_REPLY );
   }

   /*! \brief (static) Loads ConfigPages-data for given user (without column-sets). */
   function load_config_pages( $uid, $col_name='' )
   {
      $dbgmsg = "ConfigPages::load_config_pages($uid)";
      ConfigPages::_check_user_id( $uid, $dbgmsg );
      $cfg_size = ($col_name) ? ConfigTableColumns::get_config_size($col_name, $dbgmsg) : 0;

      // need special handling to load only specific field or caching all fields
      if( DgsCache::is_persistent(CACHE_GRP_CFG_PAGES) )
         $row = ConfigPages::load_cache_config_pages( $uid );
      else
      {
         $fieldset = 'User_ID,StatusFlags,StatusFolders,ForumFlags';
         if( $col_name )
            $fieldset .= ',' . ConfigTableColumns::build_fieldset( $col_name );
         $row = mysql_single_fetch("ConfigPages::load_config_pages.find($uid,$col_name)",
               "SELECT $fieldset FROM ConfigPages WHERE User_ID='$uid' LIMIT 1");
      }
      if( !$row )
         return null;

      $config = new ConfigPages(
            $row['User_ID'],
            $row['StatusFlags'],
            $row['StatusFolders'],
            $row['ForumFlags']
            );

      if( $col_name )
      {
         $bitset = ConfigTableColumns::read_columns_bitset( $row, $col_name, $cfg_size );
         $config->table_columns = new ConfigTableColumns( $uid, $col_name, $bitset );
      }

      return $config;
   }//load_config_pages

   function load_cache_config_pages( $uid )
   {
      $dbgmsg = "ConfigPages::load_cache_config_pages($uid)";
      $key = "ConfigPages.$uid";
      $row = DgsCache::fetch( $dbgmsg, CACHE_GRP_CFG_PAGES, $key );
      if( is_null($row) )
      {
         $row = mysql_single_fetch($dbgmsg.'.find', "SELECT * FROM ConfigPages WHERE User_ID='$uid' LIMIT 1" );
         if( !is_null($row) )
            DgsCache::store( $dbgmsg, CACHE_GRP_CFG_PAGES, $key, $row, SECS_PER_DAY );
      }
      return $row;
   }//load_cache_config_pages

   function delete_cache_config_pages( $uid )
   {
      DgsCache::delete( "ConfigPages::delete_cache_config_pages($uid)", CACHE_GRP_CFG_PAGES, "ConfigPages.$uid" );
   }

   /*!
    * \brief (static) Updates ContigPages-table for given user_id
    * \param $field_set_query array( fieldname => value ),
    *        or (correctly escaped) sql-SET-part ( 'f=v, f2=v2' )
    * \internal
    */
   function _update_query( $errmsg, $user_id, $field_set_query )
   {
      if( $field_set_query )
      {
         if( is_array($field_set_query) )
         {
            $parts = array();
            foreach( $field_set_query as $fieldname => $value )
               $parts[] = sprintf( "%s='%s'", $fieldname, mysql_addslashes($value) );
            $sqlpart = implode(', ', $parts);
         }
         else
            $sqlpart = $field_set_query;
         if( $sqlpart )
         {
            db_query( $errmsg,
               "UPDATE ConfigPages SET $sqlpart WHERE User_ID='$user_id' LIMIT 1" );
            ConfigPages::delete_cache_config_pages( $user_id );
         }
      }
   }//_update_query

   /*! \brief (static) Inserts default ConfigPages. */
   function insert_default( $user_id )
   {
      ConfigPages::_check_user_id( $user_id, 'ConfigPages::insert_default');
      db_query( "ConfigPages::insert_default.insert($user_id)",
         "INSERT INTO ConfigPages SET User_ID='$user_id'" );
   }

   // returns 1 if toggle was needed; 0 otherwise
   function toggle_forum_flags( $uid, $flag )
   {
      if( is_numeric($flag) && $flag > 0 )
      {
         db_query( "ConfigPages::toggle_forum_flags.toggle_flag($uid,$flag)",
            "UPDATE ConfigPages SET ForumFlags=ForumFlags ^ $flag WHERE User_ID='$uid' LIMIT 1" );
         ConfigPages::delete_cache_config_pages( $uid );
         return 1;
      }
      return 0;
   }

   function set_clear_forum_flags( $uid, $clear_flags, $set_flags )
   {
      if( is_numeric($clear_flags) && is_numeric($set_flags) && $clear_flags >= 0 && $set_flags >= 0
            && $clear_flags + $set_flags > 0 )
      {
         db_query( "ConfigPages::set_clear_forum_flags.upd($uid,$clear_flags,$set_flags)",
            "UPDATE ConfigPages SET ForumFlags=(ForumFlags & ~$clear_flags) | $set_flags WHERE User_ID='$uid' LIMIT 1" );
         ConfigPages::delete_cache_config_pages( $uid );
      }
   }

} // end of 'ConfigPages'




 /*!
  * \class ConfigTableColumns
  *
  * \brief Class to manage table-column-set
  *
  * Examples:
  *    $cfg = ConfigTableColumns::load_config( $user_id, CFGCOLS_... );
  *    $cfg->update_config();
  */
class ConfigTableColumns
{
   var $user_id;
   var $col_name; // ''=unset, don't save
   var $bitset;
   /*! \brief Maximum number of bits, that can be stored for column; BITSET_MAXSIZE if no column given. */
   var $maxsize;

   /*!
    * \brief Constructs ConfigTableColumns-object with specified arguments.
    * \param $col_name field of ConfigPages-table; or '' for static (not stored) table-columns
    */
   function ConfigTableColumns( $user_id, $col_name, $bitset )
   {
      ConfigPages::_check_user_id( $user_id, 'ConfigTableColumns');

      $this->user_id = (int)$user_id;
      $this->set_bitset( $bitset );
      if( $col_name )
      {
         $cfg_size = ConfigTableColumns::get_config_size($col_name, 'constructor');
         $this->col_name = $col_name;
         $this->maxsize = $cfg_size * BITSET_EXPORT_INTBITS;
      }
      else
      {
         $this->col_name = ''; // 0/false -> ''
         $this->maxsize = BITSET_MAXSIZE;
      }
   }

   function get_user_id()
   {
      return $this->user_id;
   }

   function get_col_name()
   {
      return $this->col_name;
   }

   function has_col_name()
   {
      return !empty($this->col_name);
   }

   /*! \brief Returns maximum number of storeable bits for current column-set. */
   function get_maxsize()
   {
      return $this->maxsize;
   }

   function &get_bitset()
   {
      return $this->bitset;
   }

   function set_bitset( $bitset )
   {
      if( !is_a($bitset, 'BitSet') )
         error('invalid_args', 'ConfigTableColumns.set_bitset.check');
      $this->bitset = $bitset;
   }

   /*! \brief Updates ConfigPages-data into database for column-set of this object only; error if no col-name set. */
   function update_config()
   {
      if( empty($this->col_name) )
         error('invalid_args', "ConfigTableColumns.update_config.check.col_name({$this->col_name})");
      ConfigPages::_check_user_id( $this->user_id, 'ConfigTableColumns::update_config');

      $arr_write_columns = ConfigTableColumns::write_columns_bitset(
            $this->col_name, $this->bitset );
      if( count($arr_write_columns) )
      {
         ConfigPages::_update_query(
            "ConfigTableColumns::update_config.update({$this->user_id},{$this->col_name})",
            $this->user_id, $arr_write_columns );
      }
   }

   // ------------ static functions ----------------------------

   /*! \brief (static) Loads ConfigPages-data for given user (with all column-sets if given otherwise). */
   function load_config( $uid, $col_name )
   {
      ConfigPages::_check_user_id( $uid, 'ConfigTableColumns::load_config');
      $cfg_size = ConfigTableColumns::get_config_size($col_name, "load_config($uid)");

      // need special handling to load only specific field or caching all fields
      if( DgsCache::is_persistent(CACHE_GRP_CFG_PAGES) )
         $row = ConfigPages::load_cache_config_pages( $uid );
      else
      {
         $fieldset = 'User_ID';
         $fieldset .= ',' . ConfigTableColumns::build_fieldset( $col_name );
         $row = mysql_single_fetch("ConfigTableColumns::load_config.find($uid,$col_name)",
               "SELECT $fieldset FROM ConfigPages WHERE User_ID='$uid' LIMIT 1");
      }
      if( !$row )
         return null;

      // read + parse column-set into BitSet and build config
      $bitset = ConfigTableColumns::read_columns_bitset( $row, $col_name, $cfg_size );
      $config = new ConfigTableColumns( $uid, $col_name, $bitset );
      return $config;
   }

   // return fieldset as SQL-fields-string
   function build_fieldset( $col_name )
   {
      $cfg_size = ConfigTableColumns::get_config_size($col_name, 'build_fieldset');
      $fieldset = $col_name;
      for( $idx=2; $idx <= $cfg_size; $idx++ )
         $fieldset .= ",$col_name$idx";
      return $fieldset;
   }

   /*!
    * \brief (static) Reads and parses int-array from DB-fields into BitSet
    * \param count number of ints stored for field
    * \internal
    */
   function read_columns_bitset( $row, $col_name, $count )
   {
      $arr_parse = array();
      for( $idx = 1; $idx <= $count; $idx++)
      {
         $fieldname = $col_name . ( $idx == 1 ? '' : $idx);
         $arr_parse[] = (int)@$row[$fieldname];
      }

      $bitset = BitSet::read_from_int_array( $arr_parse );
      //error_log($bitset->get_hex_format());
      return $bitset;
   }

   /*!
    * \brief (static) Creates array ready for saving bitset: arr( db_field => int-value, ...)
    * \internal
    */
   function write_columns_bitset( $col_name, $bitset )
   {
      $arr_write = $bitset->get_int_array();
      $cfg_size = ConfigTableColumns::get_config_size($col_name, 'write_columns_bitset');
      $result = array();
      for( $idx = 1; $idx <= $cfg_size; $idx++)
      {
         $fieldname = $col_name . ( $idx == 1 ? '' : $idx);
         $result[$fieldname] = (int)@$arr_write[$idx-1];
      }
      return $result;
   }

   /*!
    * \brief Returns number of integers in DB for given column-name in ConfigPages-table.
    * \param $col_name column-name in ConfigPages-table CFGCOLS_...; Throws error for undefined column
    */
   function get_config_size( $col_name, $dbgmsg='' )
   {
      static $cfg_size = array(
         // col_name => number of integers in DB (needed for writing)
         CFGCOLS_STATUS_GAMES             => 1,
         CFGCOLS_STATUS_TOURNAMENTS       => 1,
         CFGCOLS_WAITINGROOM              => 1,
         CFGCOLS_USERS                    => 1,
         CFGCOLS_OPPONENTS                => 1,
         CFGCOLS_CONTACTS                 => 1,
         CFGCOLS_GAMES_RUNNING_ALL        => 2, // >30 bit
         CFGCOLS_GAMES_RUNNING_USER       => 2, // >30 bit
         CFGCOLS_GAMES_FINISHED_ALL       => 2, // >30 bit
         CFGCOLS_GAMES_FINISHED_USER      => 2, // >30 bit
         CFGCOLS_GAMES_OBSERVED           => 2, // >30 bit
         CFGCOLS_GAMES_OBSERVED_ALL       => 2, // >30 bit
         CFGCOLS_BULLETIN_LIST            => 1,
         CFGCOLS_FEATURE_LIST             => 1,
         CFGCOLS_TOURNAMENTS              => 1,
         CFGCOLS_TOURNAMENT_PARTICIPANTS  => 1,
         CFGCOLS_TD_TOURNAMENT_PARTICIPANTS  => 1,
         CFGCOLS_TOURNAMENT_RESULTS       => 1,
         CFGCOLS_TOURNAMENT_LADDER_VIEW   => 1,
         CFGCOLS_TOURNAMENT_POOL_VIEW     => 1,
      );

      if( !isset($cfg_size[$col_name]) )
         error('invalid_args', "ConfigTableColumns[$dbgmsg]::get_config_size.check.col($col_name)");

      return $cfg_size[$col_name];
   }//get_config_size

} // end of 'ConfigTableColumns'

?>
