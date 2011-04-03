<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Game";

require_once 'include/globals.php';
require_once 'include/time_functions.php';
require_once 'include/classlib_game.php';
require_once 'include/utilities.php';
require_once 'include/std_functions.php';
require_once 'include/game_texts.php';
require_once 'tournaments/include/tournament_games.php';


define('MAX_ADD_DAYS', 14); // max. amount of days that can be added to game by user

define('MAX_GAME_PLAYERS', 16);

// GamePlayers.Flags
define('GPFLAG_MASTER',      0x0001); // game-master
define('GPFLAG_JOINED',      0x0002); // joined game, user set
define('GPFLAG_RESERVED',    0x0004); // 1 = WR or INV started, waiting for join; 0 = user joined if WR or INV set
define('GPFLAG_WAITINGROOM', 0x0008); // join user via waiting-room
define('GPFLAG_INVITATION',  0x0010); // join user via invitation
define('GPFLAGS_SLOT_TAKEN',  (GPFLAG_JOINED|GPFLAG_RESERVED) );
define('GPFLAGS_RESERVED_WAITINGROOM', (GPFLAG_RESERVED|GPFLAG_WAITINGROOM) );
define('GPFLAGS_RESERVED_INVITATION', (GPFLAG_RESERVED|GPFLAG_INVITATION) );

// GamePlayers.GroupColor
define('GPCOL_B',  'B');
define('GPCOL_W',  'W');
define('GPCOL_G1', 'G1');
define('GPCOL_G2', 'G2');
define('GPCOL_BW', 'BW');
define('GPCOL_DEFAULT', GPCOL_BW);

// multi-player-game message-type
define('MPGMSG_STARTGAME', 1);
define('MPGMSG_RESIGN', 2);
define('MPGMSG_INVITE', 3);

// for GameNotify
define('ACTBY_PLAYER', 0);
define('ACTBY_ADMIN',  1);
define('ACTBY_CRON',   2);
global $MAP_ACTBY_SUBJECT; //PHP5;
$MAP_ACTBY_SUBJECT = array(
   ACTBY_PLAYER => 'player',
   ACTBY_ADMIN  => 'ADMIN',
   ACTBY_CRON   => 'CRON',
);

// enum Waitingroom.JigoMode
define('JIGOMODE_KEEP_KOMI',  'KEEP_KOMI');
define('JIGOMODE_ALLOW_JIGO', 'ALLOW_JIGO');
define('JIGOMODE_NO_JIGO',    'NO_JIGO');
define('CHECK_JIGOMODE', 'KEEP_KOMI|ALLOW_JIGO|NO_JIGO');

// see Waitingroom.Handicaptype in specs/db/table-Waitingroom.txt
define('HTYPE_CONV',    'conv'); // conventional handicap
define('HTYPE_PROPER',  'proper'); // proper handicap
define('HTYPE_NIGIRI',  'nigiri'); // manual, color nigiri
define('HTYPE_DOUBLE',  'double'); // manual, color double (=black and white)
define('HTYPE_BLACK',   'black'); // manual, color black
define('HTYPE_WHITE',   'white'); // manual, color white
define('HTYPEMP_MANUAL', 'manual'); // manual, only used for multi-player-game in waiting-room

define('CAT_HTYPE_CONV', HTYPE_CONV); // conventional handicap-type
define('CAT_HTYPE_PROPER', HTYPE_PROPER); // proper handicap-type
define('CAT_HTYPE_MANUAL', 'manual'); // manual game setting
//define('CAT_HTYPE_FAIRKOMI', 'fairkomi'); // fair komi game

// lazy-init in Tournament::get..Text()-funcs
global $ARR_GLOBALS_GAME; //PHP5
$ARR_GLOBALS_GAME = array();



/**
 * \brief Class to handle adding of time for a game.
 * \note class with splitted add_time_opponent()-method to support unit-tests.
 */
class GameAddTime
{
   /*! \brief User GIVING time to his opponent. */
   var $uid;
   /*! \brief user-id of tournament-director, which is adding-time (TD needs admin-right TD_FLAG_GAME_ADD_TIME). */
   var $td_uid;
   var $game_row;
   var $game_query;
   var $reset_byoyomi;

   function GameAddTime( &$game_row, $uid, $td_uid=0 )
   {
      $this->uid = (int)$uid;
      $this->td_uid = ( (int)$td_uid > GUESTS_ID_MAX ) ? (int)$td_uid : 0;
      $this->game_row = $game_row;
      $this->game_query = '';
   }

   /*!
    * \brief Adds time to given player updating game-row without data-persisting.
    * \internal
    * \param $add_hours amount of hours to add to maintime of opponent,
    *        allowed range is 0 hour .. MAX_ADD_DAYS
    * \param $reset_byo if true, also byo-yomi will be fully resetted (for JAP/CAN)
    * \return number of hours added (may be 0), if time has been successfully added
    *        for opponent of player (uid) in specified game (gid).
    *        -1|-2 if only byo-yomi has been resetted (-1=full-reset, -2=part-reset).
    *        Otherwise error-string is returned.
    *
    *        NOTE: reset_byoyomi is resetted if needed and stored in $this->reset_byoyomi.
    */
   function add_time( $add_hours, $reset_byo=false )
   {
      $this->reset_byoyomi = $reset_byo;
      if( !is_numeric($add_hours) || $add_hours < 0
            || $add_hours > time_convert_to_hours( MAX_ADD_DAYS, 'days'))
         return sprintf( 'Invalid value for add_hours [%s]', $add_hours);

      // reset_byo: 0=no-reset, 1=full-byo-yomi-reset, 2=reset-byo-time-only
      $reset_byo = ( $reset_byo ) ? 1 : 0;
      if( !$reset_byo && $add_hours == 0 )
         return 0; // nothing to do (0 hours added, no error)

      if( is_numeric($this->game_row) )
      {
         $gid = $this->game_row;
         $this->game_row = mysql_single_fetch( 'add_time_opponent',
            "SELECT Games.* from Games WHERE ID=$gid");
         if( !$this->game_row )
            error('unknown_game',"add_time_opponent($gid)");
      }
      else
         $gid = $this->game_row['ID'];

      if( !GameAddTime::allow_add_time_opponent( $this->game_row, $this->uid, $this->td_uid ) )
         return sprintf( T_('Conditions are not met to allow to add time by user [%s] for game [%s]'),
                         ($this->tdir ? T_('Tournament director') : $this->uid), $gid );

      // get opponents columns to update
      $oppcolor = ( $this->game_row['Black_ID'] == $this->uid ) ? 'White' : 'Black';

      if( !isset($this->game_row["{$oppcolor}_Maintime"])
            || !isset($this->game_row["{$oppcolor}_Byotime"])
            || !isset($this->game_row["{$oppcolor}_Byoperiods"])
            || !isset($this->game_row['Byotype'])
            || !isset($this->game_row['Byotime'])
            || !isset($this->game_row['Byoperiods']) )
         error('internal_error',"add_time_opponent.incomplete_game_row($gid)");

      // special byo-yomi resetting
      $byotype = $this->game_row['Byotype'];
      if( !$reset_byo )
      {
         // reset current byo-yomi period for JAP|CAN if in byo-yomi
         if( $byotype == BYOTYPE_CANADIAN )
            $reset_byo = 1; // CAN-time difficult to handle without reset (see specs/time.txt)
         if( $byotype == BYOTYPE_JAPANESE && $this->game_row["{$oppcolor}_Byoperiods"] >= 0 )
            $reset_byo = 2; // reset current byo-period for JAP-time
      }
      if( $reset_byo )
      {
         if( $byotype == BYOTYPE_FISCHER )
            $reset_byo = 0;
         elseif( $this->game_row["{$oppcolor}_Byoperiods"] < 0 )
            $reset_byo = 0;
         elseif( $this->game_row['Byotime'] <= 0 || $this->game_row['Byoperiods'] < 0 )
            $reset_byo = 0; // no byoyomi-reset if absolute-time
      }
      $this->reset_byoyomi = $reset_byo;

      if( !$reset_byo && $add_hours <= 0 )
         return 0; // nothing to do (0 hours added, no error)

      // add maintime and eventually reset byo-time for opponent
      $this->game_query = '';
      if( $add_hours > 0 )
      {
         $this->game_query .= ",{$oppcolor}_Maintime={$oppcolor}_Maintime+$add_hours";
         $this->game_row["{$oppcolor}_Maintime"] += $add_hours;
      }

      if( $reset_byo == 2 ) // special reset for JAP-time
      {
         $this->game_query .= ",{$oppcolor}_Byotime=" . $this->game_row['Byotime'];
         $this->game_row["{$oppcolor}_Byotime"] = $this->game_row['Byotime'];
      }
      elseif( $reset_byo == 1 ) // full byo-yomi reset
      {
         if( $this->game_row["{$oppcolor}_Maintime"] <= 0  )
         {
            $new_byotime = $this->game_row['Byotime'];
            $new_byoper  = $this->game_row['Byoperiods'];
         }
         else
         {
            $new_byotime = 0;
            $new_byoper  = -1;
         }
         $this->game_query .= ",{$oppcolor}_Byotime=$new_byotime";
         $this->game_query .= ",{$oppcolor}_Byoperiods=$new_byoper";
         $this->game_row["{$oppcolor}_Byotime"] = $new_byotime;
         $this->game_row["{$oppcolor}_Byoperiods"] = $new_byoper;
      }

      if( !$this->game_query )
         return 0; //nothing to do (shouldn't happen here)

      return ( $add_hours > 0 ) ? $add_hours : -$reset_byo;
   }//add_time


   // ------------ static functions ----------------------------

   /*!
    * \brief returns array( days => arr_days, byo_reset => bool ) for add-time-form
    * \param $game_row expect fields: Byotype, Byotime, (Black|White)_Byoperiods
    * \param $color_to_move BLACK | WHITE, used to check if byo-yomi-reset needed for current to-move
    */
   function make_add_time_info( $game_row, $color_to_move )
   {
      $pfx = ($color_to_move == BLACK) ? 'Black' : 'White';

      $allow_reset = false;
      if( $game_row['Byotype'] == BYOTYPE_CANADIAN )
         $allow_reset = true;
      elseif( $game_row['Byotype'] == BYOTYPE_JAPANESE && $game_row["{$pfx}_Byoperiods"] >= 0 )
         $allow_reset = true;
      if( $game_row['Byotime'] <= 0 ) // absolute-time
         $allow_reset = false;

      $arr_days = array();
      $startidx = ($allow_reset) ? 0 : 1;
      for( $i=$startidx; $i <= MAX_ADD_DAYS; $i++)
         $arr_days[$i] = $i . ' ' . (($i>1) ? T_('days') : T_('day'));

      return array( 'days' => $arr_days, 'byo_reset' => $allow_reset );
   }

   /*!
    * \brief returns true, if user (uid) (or TD) is allowed to add additional
    *        time for uid's opponent in game specified by $game_row.
    * \param $game_row expect fields: Status, (Black|White)_(ID|Maintime), tid
    * \param $is_tdir true, if tournament-director with TD_FLAG_GAME_ADD_TIME-right
    */
   function allow_add_time_opponent( $game_row, $uid, $is_tdir=false )
   {
      // must be a running-game
      if( $game_row['Status'] == GAME_STATUS_FINISHED || $game_row['Status'] == GAME_STATUS_INVITED )
         return false;

      // must be one of my games (to give time to my opponent)
      if( $game_row['White_ID'] != $uid && $game_row['Black_ID'] != $uid )
         return false;

      // must not be a tournament-game except TD-allowed-to-add-time
      if( !$is_tdir && $game_row['tid'] != 0 )
         return false;

      // get opponents columns
      if( $game_row['Black_ID'] == $uid )
         $oppcolor = 'White';
      else
         $oppcolor = 'Black';
      // don't exceed 365 days maintime
      if( $game_row["{$oppcolor}_Maintime"]
            + time_convert_to_hours(MAX_ADD_DAYS,'days') > time_convert_to_hours(360,'days') )
         return false;

      return true;
   }

   /*!
    * \brief Adds time to given player updating game-row and save into database.
    * \param $game_row pre-loaded game_row to add time for
    *        or numeric game-id to load the game from database.
    *
    *        Need the following fields set in game_row, and also the fields needed
    *        for NextGameOrder::make_timeout_date():
    *           ID, tid, Status, Maintime, Byotype, Byotime, Byoperiods, ToMove_ID,
    *           LastTicks, Moves, (Black/White)_ID/_Maintime/_Byotime/_Byoperiods,
    *           X_(White|Black)Clock (from Players.Clock)
    *
    * \param $uid user giving time to his opponent
    * \param $add_hours amount of hours to add to maintime of opponent,
    *        allowed range is 0 hour .. MAX_ADD_DAYS
    * \param $reset_byo if true, also byo-yomi will be resetted (for JAP/CAN)
    * \param $by_td_uid user-id of tournament-director, which adds time
    * \return number of hours added (may be 0), if time has been successfully added
    *        for opponent of player (uid) in specified game (gid).
    *        -1|-2 if only byo-yomi has been resetted (-1=full-reset, -2=part-reset).
    *        Otherwise error-string is returned.
    */
   function add_time_opponent( &$game_row, $uid, $add_hours, $reset_byo=false, $by_td_uid=0 )
   {
      $game_addtime = new GameAddTime( $game_row, $uid, $by_td_uid );
      $add_hours = $game_addtime->add_time( $add_hours, $reset_byo );
      $reset_byo = $game_addtime->reset_byoyomi;

      if( !is_numeric($add_hours) || ($add_hours == 0 && !$reset_byo) ) // error or nothing to do
         return $add_hours;

      // handle Games.TimeOutDate
      if( $game_row['ToMove_ID'] != $uid )
      {
         $stoneToMove = ( $game_row['ToMove_ID'] == $game_row['Black_ID'] ) ? BLACK : WHITE;
         $timeout_date = NextGameOrder::make_timeout_date(
               $game_row, $stoneToMove, $game_row['LastTicks'] );

         $game_addtime->game_query .= ",TimeOutDate=$timeout_date";
         $game_row['TimeOutDate'] = $timeout_date;
      }


      //TODO HOT-SECTION to avoid double-clicks
      $gid = $game_row['ID'];
      $game_query = "UPDATE Games SET ".substr($game_addtime->game_query,1)
                  . " WHERE ID=$gid AND Status" . IS_RUNNING_GAME . " LIMIT 1";

      // insert entry in Moves-table
      $Moves = $game_row['Moves'];
      $Stone = ( $game_row['Black_ID'] == $uid ) ? BLACK : WHITE;
      $save_hours = ($add_hours < 0) ? 0 : $add_hours;
      $pos_y = ($reset_byo ? 1 : 0) | ($game_addtime->td_uid ? 2 : 0);
      $move_query = "INSERT INTO Moves (gid, MoveNr, Stone, PosX, PosY, Hours) VALUES "
         . "($gid, $Moves, $Stone, ".POSX_ADDTIME.", $pos_y, $save_hours)";

      // see also confirm.php
      db_query( "GameAddTime::add_time_opponent.update($gid)", $game_query );
      if( mysql_affected_rows() != 1 ) //0 if it had done nothing
         error('mysql_update_game',"GameAddTime::add_time_opponent.update($gid)");

      db_query( "GameAddTime::add_time_opponent.insert_move($gid)", $move_query );
      if( mysql_affected_rows() != 1 )
         error('mysql_insert_move',"GameAddTime::add_time_opponent.insert_move($gid)");

      return $add_hours; // success (no-error)
   } //add_time_opponent

} //end 'GameAddTime'




/**
 * \brief Class to handle multi-player-game.
 */
class MultiPlayerGame
{
   // ------------ static functions ----------------------------

   /*!
    * \brief Returns GAMETYPE for game-players or number of required players (if $count=true);
    *        null (0 if $count set) on wrong format or bad number of players.
    */
   function determine_game_type( $game_players, $count=false )
   {
      if( trim($game_players) == '' || $game_players == '1:1' || $game_players == '2' )
         return ($count) ? 2 : GAMETYPE_GO;
      elseif( is_numeric($game_players) )
      {
         if( $game_players > 2 && $game_players <= MAX_GAME_PLAYERS && ($game_players & 1) ) // odd
            return ($count) ? $game_players : GAMETYPE_ZEN_GO;
      }
      elseif( preg_match("/^\d+:\d+$/", $game_players) )
      {
         $arr = explode(':', $game_players);
         $cnt_players = $arr[0] + $arr[1];
         if( $cnt_players > 2 && $cnt_players <= MAX_GAME_PLAYERS )
            return ($count) ? $cnt_players : GAMETYPE_TEAM_GO;
      }
      return ($count) ? 0 : null;
   }

   /*! \brief Returns number of required players. */
   function determine_player_count( $game_players )
   {
      return MultiPlayerGame::determine_game_type( $game_players, true );
   }

   /*! \brief Returns max number of required players in all groups. */
   function determine_groups_player_count( $game_players, $max=true )
   {
      $arr = explode(':', $game_players);
      return ($max) ? max($arr) : array( min($arr), max($arr) );
   }

   function is_single_player( $game_players, $is_black )
   {
      $arr = explode(':', $game_players);
      if( count($arr) == 2 ) // TeamGo
         return ( $is_black ) ? ( $arr[0] == 1 ) : ( $arr[1] == 1 );
      else
         return false; // ZenGo
   }

   function build_game_type_filter_array( $prefix='' )
   {
      return array(
         T_('All') => '',
         GameTexts::get_game_type(GAMETYPE_GO)      => $prefix."GameType='".GAMETYPE_GO."'",
         GameTexts::get_game_type(GAMETYPE_TEAM_GO) => $prefix."GameType='".GAMETYPE_TEAM_GO."'",
         GameTexts::get_game_type(GAMETYPE_ZEN_GO)  => $prefix."GameType='".GAMETYPE_ZEN_GO."'",
         T_('Rengo#gametype') => $prefix."GameType='".GAMETYPE_TEAM_GO."' AND {$prefix}GamePlayers='2:2'",
         T_('Non-Std#gametype') => $prefix."GameType IN ('".GAMETYPE_TEAM_GO."','".GAMETYPE_ZEN_GO."')",
      );
   }

   /*!
    * \brief "Deletes" (by updating) specified number of game-players for given game-id and flag-typed placeholder.
    * \param $gp_flag GPFLAG_WAITINGROOM | GPFLAG_INVITATION
    */
   function revoke_offer_game_players( $gid, $del_count, $gp_flag )
   {
      if( is_numeric($gid) && $gid > 0 && $del_count > 0 && $gp_flag > 0 )
      {
         // remove join-reservation for waiting-room or invitation
         $gpf_check = GPFLAG_RESERVED | $gp_flag;
         db_query( "MultiPlayerGame::revoke_offer_game_players.update_gp($gid,$del_count,$gp_flag)",
            "UPDATE GamePlayers SET Flags=Flags & ~".(GPFLAG_RESERVED|GPFLAG_WAITINGROOM|GPFLAG_INVITATION)
               . " WHERE gid=$gid AND uid=0 AND (Flags & $gpf_check) = $gpf_check LIMIT $del_count" );
      }
   }

   /*! \brief Returns number of game-players for given game-id. */
   function count_game_players( $gid )
   {
      $row = mysql_single_fetch( "MultiPlayerGame::count_game_players($gid)",
            "SELECT COUNT(*) AS X_Count FROM GamePlayers WHERE gid=$gid" );
      return ($row) ? (int)@$row['X_Count'] : 0;
   }

   /*! \brief Returns true if uid is game-player for given game-id. */
   function is_game_player( $gid, $uid )
   {
      $row = mysql_single_fetch( "MultiPlayerGame::is_game_player($gid,$uid)",
            "SELECT ID FROM GamePlayers WHERE gid=".((int)$gid)." AND uid=".((int)$uid)." LIMIT 1" );
      return ($row) ? 1 : 0;
   }

   /*!
    * \brief Returns diff to required number of players that joined MP-game.
    * \return 0 (=required number reached), <0 (missing players), >0 (too much players)
    */
   function check_count_game_players( $gid, $expected_player_count )
   {
      $player_count = MultiPlayerGame::count_game_players( $gid );
      return ( $player_count - $expected_player_count );
   }

   /*! \brief Inserts game-players entries for given game-id and game-master $uid, and increase Players.GamesMPG. */
   function init_multi_player_game( $dbgmsg, $gid, $uid, $gp_count )
   {
      if( $gp_count <= 2 )
         error('invalid_args', "$dbgmsg.init_multi_player_game.check.gp_count($gid,$uid,$gp_count)");

      $query = "INSERT GamePlayers (gid,uid,Flags) VALUES ";
      $query .= "($gid,$uid,".(GPFLAG_MASTER|GPFLAG_JOINED).")";
      for( $i=2; $i <= $gp_count; $i++ )
         $query .= ", ($gid,0,0)";

      db_query( "$dbgmsg.init_multi_player_game.insert_gp($gid,$uid,$gp_count)",
         $query, 'mysql_start_game' );

      MultiPlayerGame::change_joined_players( $dbgmsg, $gid, 1 );

      // update Players for all starting players: GamesMPG++
      db_query( "$dbgmsg.init_multi_player_game.upd_players.inc_mpg($gid,$uid)",
         "UPDATE Players SET GamesMPG=GamesMPG+1 WHERE ID=$uid LIMIT 1" );
   }

   /*! \brief Joins waiting-room MP-game for given user and check for race-conditions. */
   function join_waitingroom_game( $dbgmsg, $gid, $uid )
   {
      // check race-condition if other joined first
      db_query( "$dbgmsg.join_waitingroom_game.update_game_players($gid,$uid)",
         "UPDATE GamePlayers SET uid=$uid, " .
            "Flags=(Flags & ~".GPFLAG_RESERVED.") | ".GPFLAG_JOINED." " .
         "WHERE gid=$gid AND uid=0 AND (Flags & ".(GPFLAG_RESERVED|GPFLAG_WAITINGROOM).") LIMIT 1",
         'waitingroom_join_error' );
      if( mysql_affected_rows() != 1)
         error('waitingroom_join_too_late', "$dbgmsg.join_waitingroom_game($gid,$uid)");

      MultiPlayerGame::change_joined_players( $dbgmsg, $gid, 1 );
   }//join_waitingroom_game

   /*! \brief Changes number of joined players of MP-game (store in Games.Moves). */
   function change_joined_players( $dbgmsg, $gid, $diff )
   {
      $diff = (int)$diff;
      db_query( "$dbgmsg.change_joined_players.upd_games($gid,$diff)",
         "UPDATE Games SET Moves=Moves+($diff) WHERE ID=$gid AND Status='".GAME_STATUS_SETUP."' LIMIT 1" );
   }//change_joined_players

   /*!
    * \brief Returns arr( GroupColor, GroupOrder, MoveColor ) to identify current game-player to move.
    * \param $game_players Games.GamePlayers = game-players-info
    * \param $game_moves Games.Moves = move-counter starting at 0
    * \param $handicap Games.Handicap = number of handicap-stones.
    * \param $add_moves how many moves in the future (setting-handicap = 1 move); can be <0
    */
   function calc_game_player_for_move( $game_players, $game_moves, $handicap, $add_moves=0 )
   {
      if( $handicap > 0 )
         $moves = ( $game_moves < $handicap ) ? 0 : $game_moves - $handicap + 1;
      else
         $moves = $game_moves;
      $moves += $add_moves;
      if( $moves < 0 )
         $moves = 0;
      $movecol = ($moves & 1) ? GPCOL_W : GPCOL_B;

      $arr = explode(':', $game_players);
      if( count($arr) == 2 ) // Team-Go
      {
         if( $moves & 1 ) // odd = WHITE
            return array( GPCOL_W, ( ( ($moves - 1) >> 1 ) % (int)$arr[1] ) + 1, $movecol );
         else // even = BLACK
            return array( GPCOL_B, ( ( $moves >> 1 ) % (int)$arr[0] ) + 1, $movecol );
      }
      else // Zen-Go
         return array( GPCOL_BW, ($moves % (int)$game_players) + 1, $movecol );
   }//calc_game_player_for_move

   function update_players_start_mpgame( $gid )
   {
      // update Players for all game-players: Running++
      db_query( "MultiPlayerGame::update_players_start_mpgame($gid)",
         "UPDATE Players AS P INNER JOIN GamePlayers AS GP ON GP.uid=P.ID "
            . "SET P.Running=P.Running+1, P.GamesMPG=P.GamesMPG-1 WHERE GP.gid=$gid" );
   }//update_players_start_mpgame

   /*!
    * \brief Updates player on end of MP-game for given game-id.
    * \param $game_in_setup_mode true, if Players.GamesMPG should be decreased (e.g. on game-deletion)
    */
   function update_players_end_mpgame( $gid, $game_in_setup_mode )
   {
      // update Players for all game-players: Running--, Finished++; GamesMPG-- if $delete
      $qpart_del = ($game_in_setup_mode) ? ", P.GamesMPG=P.GamesMPG-1 " : '';
      db_query( "MultiPlayerGame::update_players_end_mpgame($gid,$game_in_setup_mode)",
         "UPDATE Players AS P INNER JOIN GamePlayers AS GP ON GP.uid=P.ID "
            . "SET P.Running=P.Running-1, P.Finished=P.Finished+1 $qpart_del WHERE GP.gid=$gid" );
   }//update_players_end_mpgame

   /*!
    * \brief Returns default-templates for messages for MP-games with subject and body-text.
    * \param $mpg_arr array with additional keys:
    *    for mpg_type=MPGMSG_RESIGN:
    *       'move' = move for mpg_type=MPGMSG_RESIGN
    *    for mpg_type=MPGMSG_INVITE:
    *       'from_handle' = user-id (=handle) of game-master
    *       'game_type' = "$GameType ($GamePlayers)"
    */
   function get_message_defaults( $mpg_type, $mpg_gid, $mpg_arr )
   {
      switch( (int)$mpg_type )
      {
         case MPGMSG_RESIGN:
            $move = (int)$mpg_arr['move'];
            return array(
               T_('May I resign?#mpg'),
               "<game_ $mpg_gid,$move>\n"
            );

         case MPGMSG_INVITE:
            $from_handle = @$mpg_arr['from_handle'];
            $game_type   = @$mpg_arr['game_type'];
            return array(
               // subject
               sprintf( T_('Invitation to multi-player-game from [%s]#mpg'), $from_handle ),
               // body
               sprintf( T_('Game-master %s invites you to a %s multi-player-game.#mpg'), "<user =$from_handle>", $game_type ) . "\n\n" .
               sprintf( T_('You can accept or reject the invitation on setup-page of game: %s#mpg'), "<game_ $mpg_gid>" ) . "\n\n" .
               T_('To reject the invitation, please inform the game-master by replying to this message.#mpg') . "\n" .
               T_('You may also want to discuss what team, color or playing order you prefer in the game (see FAQ for more details).#mpg') . "\n\n"
            );

         case MPGMSG_STARTGAME:
         default:
            return array(
               T_('Everybody ready to start game?#mpg'),
               "<game_ $mpg_gid>\n"
            );
      }
   }//get_message_defaults

   /*!
    * \brief Returns array( b|wRating => group-rating ) for set game-end-rating.
    * \param $gamedata array of GamePlayer-objects with loaded User-object (with RatingStatus + Rating2)
    * \param $rating_update if true, return in format for rating-update: $arr_rating['b/wRating'] = rating
    * \return $arr_ratings[$group_color] = average-rating, or $arr_rating['b/wRating'] if $rating_udpate set
    */
   function calc_average_group_ratings( $gamedata, $rating_update=false )
   {
      $calc_ratings = array();
      if( is_array($gamedata) )
      {
         foreach( $gamedata as $gp )
         {
            if( !is_null($gp->user) && $gp->user->hasRating() )
               $calc_ratings[$gp->GroupColor][] = $gp->user->Rating;
         }
      }
      elseif( !is_numeric($gamedata) || $gamedata <= 0 )
         error('invalid_args', "MultiPlayerGame::calc_average_group_ratings.check.gid($gamedata)");
      else
      {
         $result = db_query( "MultiPlayerGame::calc_average_group_ratings.find($gamedata)",
            "SELECT GP.GroupColor, P.Rating2, P.RatingStatus " .
            "FROM GamePlayers AS GP INNER JOIN Players AS P ON P.ID=GP.uid " .
            "WHERE gid=$gamedata" );

         while( $row = mysql_fetch_assoc($result) )
         {
            if( $row['RatingStatus'] == RATING_INIT || $row['RatingStatus'] == RATING_RATED ) // user has rating
            {
               if( is_valid_rating($row['Rating2']) )
                  $calc_ratings[$row['GroupColor']][] = $row['Rating2'];
            }
         }
         mysql_free_result($result);
      }

      // calc average rating for groups B/W | BW
      $arr_ratings = array();
      foreach( $calc_ratings as $gr_col => $arr )
      {
         $cnt = count($arr);
         if( $cnt )
            $arr_ratings[$gr_col] = array_sum($arr) / $cnt;
      }

      if( $rating_update ) // convert result into format needed for rating-update
      {
         $upd_ratings = array();
         if( isset($arr_ratings[GPCOL_BW]) )
            $upd_ratings['bRating'] = $upd_ratings['wRating'] = $arr_ratings[GPCOL_BW];
         else
         {
            $upd_ratings['bRating'] = $arr_ratings[GPCOL_B];
            $upd_ratings['wRating'] = $arr_ratings[GPCOL_W];
         }
         return $upd_ratings;
      }
      else
         return $arr_ratings;
   }//calc_average_group_ratings

} //end 'MultiPlayerGame'


/**
 * \brief Class to model GamePlayers-entity.
 */
class GamePlayer
{
   var $id;
   var $gid;
   var $GroupColor;
   var $GroupOrder;
   var $Flags;
   var $uid;

   var $user;

   function GamePlayer( $id, $gid, $group_color='BW', $group_order=0, $flags=0, $uid=0 )
   {
      $this->id = (int)$id;
      $this->gid = (int)$gid;
      $this->setGroupColor( $group_color );
      $this->GroupOrder = (int)$group_order;
      $this->Flags = (int)$flags;
      $this->uid = (int)$uid;
   }

   function setGroupColor( $group_color )
   {
      if( !preg_match("/^(BW|B|W|G[12])$/", $group_color) )
         error('invalid_args', "GamePlayer.setGroupColor($group_color)");
      $this->GroupColor = $group_color;
   }

   function getGroupColorOrder()
   {
      return GamePlayer::get_group_color_order( $this->GroupColor );
   }


   // ------------ static functions ----------------------------

   function build_game_player( $id, $gid, $group_color, $group_order, $flags, $uid, $user )
   {
      $gp = new GamePlayer( $id, $gid, $group_color, $group_order, $flags, $uid );
      $gp->user = $user;
      return $gp;
   }

   /*! \brief Returns GamePlayer-object for given game-id, group-color and group-order. */
   function load_game_player( $gid, $group_color, $group_order )
   {
      $sql_gr_col = mysql_addslashes($group_color);
      $sql_gr_order = (int)$group_order;
      $row = mysql_single_fetch( "GamePlayer::load_game_player($gid,$group_color,$group_order)",
            "SELECT * FROM GamePlayers " .
            "WHERE gid=$gid AND GroupColor='$sql_gr_col' AND GroupOrder=$sql_gr_order LIMIT 1" );
      if( !$row )
         error('internal_error', "GamePlayer::load_game_player($gid,$group_color,$group_order)");
      return new GamePlayer( $row['ID'], $row['gid'], $row['GroupColor'], $row['GroupOrder'], $row['Flags'], $row['uid'] );
   }//load_game_player

   /*! \brief Returns GamePlayer-object for given game-id and user-id. */
   function load_game_player_by_uid( $gid, $uid )
   {
      $row = mysql_single_fetch( "GamePlayer::load_game_player_by_uid($gid,$uid)",
            "SELECT * FROM GamePlayers WHERE gid=$gid AND uid=$uid LIMIT 1" );
      return ( $row )
         ? new GamePlayer( $row['ID'], $row['gid'], $row['GroupColor'], $row['GroupOrder'], $row['Flags'], $row['uid'] )
         : null;
   }//load_game_player_by_uid

   /*! \brief Returns Players.ID for given game-id, group-color and group-order. */
   function load_uid_for_move( $gid, $group_color, $group_order )
   {
      $sql_gr_col = mysql_addslashes($group_color);
      $sql_gr_order = (int)$group_order;
      $row = mysql_single_fetch( "GamePlayer::load_uid_for_move($gid,$group_color,$group_order)",
            "SELECT uid FROM GamePlayers " .
            "WHERE gid=$gid AND GroupColor='$sql_gr_col' AND GroupOrder=$sql_gr_order LIMIT 1" );
      if( !$row )
         error('internal_error', "GamePlayer::load_uid_for_move($gid,$group_color,$group_order)");
      return (int)@$row['uid'];
   }//load_uid_for_move

   /*! \brief Returns true, if GamePlayer-entry exists for given game-id and user-id. */
   function exists_game_player( $gid, $uid )
   {
      $row = mysql_single_fetch( "GamePlayer::load_game_player_by_uid($gid,$uid)",
            "SELECT ID FROM GamePlayers WHERE gid=$gid AND uid=$uid LIMIT 1" );
      return (bool) $row;
   }//exists_game_player

   /*!
    * \brief Returns list of Players.Handle for given game-id (and group-color).
    * \param $arr_users if non-null array given, save as arr_users["$group_color:$group_order"]
    *        = ( GroupColor/GroupOrder/uid/Handle/Name/Rating2/Sessioncode/Sessionexpire => values, ... )
    */
   function load_users_for_mpgame( $gid, $group_color='', $skip_myself=false, &$arr_users )
   {
      global $player_row;

      if( !is_numeric($gid) || $gid <= 0 )
         error('invalid_args', "GamePlayers::load_users_for_mpgame.check.gid($gid,$group_color)");
      if( (string)$group_color != '' && !preg_match("/^(BW|B|W|G[12])$/", $group_color) )
         error('invalid_args', "GamePlayers::load_users_for_mpgame.check.grcol($gid,$group_color)");

      $qpart_grcol = ($group_color) ? " AND GP.GroupColor='$group_color'" : '';
      $result = db_query( "GamePlayer::load_users_for_mpgame.find($gid,$group_color)",
            "SELECT GP.GroupColor, GP.GroupOrder, GP.uid, " .
               "P.Handle, P.Name, P.Rating2, P.Sessioncode, " .
               "UNIX_TIMESTAMP(P.Sessionexpire) AS X_Sessionexpire " .
            "FROM GamePlayers AS GP INNER JOIN Players AS P ON P.ID=GP.uid " .
            "WHERE GP.gid=$gid $qpart_grcol" );
      $out = array();
      while( $row = mysql_fetch_array( $result ) )
      {
         if( !($skip_myself && $player_row['Handle'] == $row['Handle']) )
         {
            $out[] = $row['Handle'];
            if( is_array($arr_users) )
               $arr_users["{$row['GroupColor']}:{$row['GroupOrder']}"] = $row;
         }
      }
      mysql_free_result($result);
      return $out;
   }//load_users_for_mpgame

   /*! \brief Returns user-map ( uid/Handle/etc => vals ) as created in arr_users by load_users_for_mpgame()-method. */
   function get_user_info( &$arr_users, $group_color, $group_order )
   {
      return @$arr_users["{$group_color}:{$group_order}"];
   }//get_user_info

   function build_image_group_color( $group_color )
   {
      static $arr_col_images = null;
      if( is_null($arr_col_images) )
      {
         global $base_path;

         $arr_col_images = array(
            'BW'  => image( $base_path.'17/y.gif', T_('Black/White or unset#gpcol'), null ),
            'B'   => image( $base_path.'17/b.gif', T_('Black#gpcol'), null ),
            'W'   => image( $base_path.'17/w.gif', T_('White#gpcol'), null ),
            'G1'  => image( $base_path.'17/bw.gif', T_('Group #1#gpcol'), null ),
            'G2'  => image( $base_path.'17/wb.gif', T_('Group #2#gpcol'), null ),
         );
      }
      return @$arr_col_images[$group_color];
   }//build_image_group_color

   function get_group_color_text( $group_color=null )
   {
      static $arr_group_cols = null;
      if( is_null($arr_group_cols) )
      {
         $arr_group_cols = array(
            'BW'  => T_('BW#gpcol'),
            'B'   => T_('Black#gpcol'),
            'W'   => T_('White#gpcol'),
            'G1'  => T_('Group #1#gpcol'),
            'G2'  => T_('Group #2#gpcol'),
         );
      }
      return (is_null($group_color)) ? $arr_group_cols : @$arr_group_cols[$group_color];
   }//get_group_color_text

   function get_group_color_order( $group_color )
   {
      static $arr_grcol_order = null;
      if( is_null($arr_grcol_order) )
      {
         $arr_grcol_order = array(
            'B'   => 0,
            'W'   => 1,
            'G1'  => 2,
            'G2'  => 3,
            'BW'  => 4,
         );
      }
      return $arr_grcol_order[$group_color];
   }//get_group_color_order

   /*! \brief Clears flags of game-players-table for given game. */
   function reset_flags( $gid, $flags )
   {
      db_query( "GamePlayer::reset_flags.update($gid,$flags)",
         "UPDATE GamePlayers SET Flags=Flags & ~$flags " .
         "WHERE gid=$gid AND (Flags & $flags) > 0" );
   }//reset_flags

   function delete_reserved_invitation( $gid, $uid )
   {
      db_query( "GamePlayer::delete_reserved_invitation.gp_upd($gid,$uid)",
         "UPDATE GamePlayers SET uid=0, GroupColor='".GPCOL_DEFAULT."', GroupOrder=0, Flags=0 " .
         "WHERE gid=$gid AND uid=$uid AND " .
            "(Flags & ".GPFLAGS_RESERVED_INVITATION.") = ".GPFLAGS_RESERVED_INVITATION." " .
         "LIMIT 1" );
   }//delete_reserved_invitation

   function delete_joined_player( $gid, $uid )
   {
      db_query( "GamePlayer::delete_joined_player.gp_upd($gid,$uid)",
         "UPDATE GamePlayers SET uid=0, GroupColor='".GPCOL_DEFAULT."', GroupOrder=0, Flags=0 " .
         "WHERE gid=$gid AND uid=$uid AND (Flags & ".GPFLAG_JOINED.") > 0 " .
         "LIMIT 1" );
   }//delete_joined_player

} // end 'GamePlayer'




/**
 * \brief Class to handle general game-actions.
 */
class GameHelper
{

   /*!
    * \brief Deletes non-tourney game and all related tables for given game-id.
    * \param $upd_players if true, also decrease Players.Running
    * \param $grow game-row can be provided to save additional query
    * \return true on success, false on invalid args or tourney-game.
    */
   function delete_running_game( $gid, $upd_players=true, $grow=null )
   {
      if( !is_numeric($gid) && $gid <= 0 )
         return false;

      if( is_null($grow) )
         $grow = mysql_single_fetch( "GameHelper::delete_running_game.check.gid($gid)",
            "SELECT Status, tid, DoubleGame_ID, GameType, Black_ID, White_ID from Games WHERE ID=$gid LIMIT 1");
      if( !$grow || (int)@$grow['tid'] > 0 )
         return false;

      ta_begin();
      {//HOT-section to delete game table-entries
         // mark reference in other double-game to indicate referring game has vanished
         $dbl_gid = (int)@$grow['DoubleGame_ID'];
         if( $dbl_gid > 0 )
            db_query( "GameHelper::delete_running_game.doublegame($gid)",
               "UPDATE Games SET DoubleGame_ID=-ABS(DoubleGame_ID) WHERE ID=$dbl_gid LIMIT 1" );

         if( $upd_players )
         {
            if( $grow['GameType'] == GAMETYPE_GO )
            {
               $Black_ID = (int)@$grow['Black_ID'];
               $White_ID = (int)@$grow['White_ID'];
               db_query( "GameHelper::delete_running_game.upd_players($gid,$Black_ID,$White_ID)",
                  "UPDATE Players SET Running=Running-1 WHERE ID IN ($Black_ID,$White_ID) LIMIT 2" );
            }
            else
               MultiPlayerGame::update_players_end_mpgame( $gid, ($grow['Status'] == GAME_STATUS_SETUP) );
         }

         NextGameOrder::delete_game_priorities($gid);
         db_query( "GameHelper::delete_running_game.observers($gid)",
            "DELETE FROM Observers WHERE ID=$gid" );
         db_query( "GameHelper::delete_running_game.notes($gid)",
            "DELETE FROM GamesNotes WHERE gid=$gid" );
         db_query( "GameHelper::delete_running_game.movemsg($gid)",
            "DELETE FROM MoveMessages WHERE gid=$gid" );
         db_query( "GameHelper::delete_running_game.moves($gid)",
            "DELETE FROM Moves WHERE gid=$gid" );
         db_query( "GameHelper::delete_running_game.gameplayers($gid)",
            "DELETE FROM GamePlayers WHERE gid=$gid" );
         db_query( "GameHelper::delete_running_game.games($gid)",
            "DELETE FROM Games WHERE ID=$gid LIMIT 1" );
      }
      ta_end();

      return true;
   }//delete_running_game

   /*! \brief Deletes INVITED-game; return true for success; false on failure. */
   function delete_invitation_game( $dbgmsg, $gid, $uid1, $uid2 )
   {
      db_query( "GameHelper::delete_invitation_game.delgame.$dbgmsg($gid)",
         "DELETE FROM Games WHERE ID=$gid AND Status='INVITED' " .
            "AND ( Black_ID=$uid1 OR White_ID=$uid1 ) " .
            "AND ( Black_ID=$uid2 OR White_ID=$uid2 ) " .
            "LIMIT 1" );
      if( mysql_affected_rows() != 1)
      {
         error('game_delete_invitation', "GameHelper::delete_invitation_game.delres.$dbgmsg($gid)");
         return false;
      }
      else
         return true;
   }//delete_invitation_game

   /*! \brief Updates game-stats in Players-table for simple or multi-player game. */
   function update_players_end_game( $dbgmsg, $gid, $game_type, $rated_status, $score, $black_id, $white_id )
   {
      if( !is_numeric($gid) && $gid <= 0 )
         return;

      if( $game_type == GAMETYPE_GO )
      {
         db_query( $dbgmsg."GameHelper::update_players_end_game.W($gid,$white_id)",
            "UPDATE Players SET Running=Running-1, Finished=Finished+1"
            .($rated_status ? '' : ", RatedGames=RatedGames+1"
               .($score > 0 ? ", Won=Won+1" : ($score < 0 ? ", Lost=Lost+1 " : ""))
             ). " WHERE ID=$white_id LIMIT 1" );

         db_query( $dbgmsg."GameHelper::update_players_end_game.B($gid,$black_id)",
            "UPDATE Players SET Running=Running-1, Finished=Finished+1"
            .($rated_status ? '' : ", RatedGames=RatedGames+1"
               .($score < 0 ? ", Won=Won+1" : ($score > 0 ? ", Lost=Lost+1 " : ""))
             ). " WHERE ID=$black_id LIMIT 1" );
      }
      else // multi-player-go
         MultiPlayerGame::update_players_end_mpgame( $gid, /*setup*/false );
   }//update_players_end_game

} // end 'GameHelper'





/**
 * \brief Class to handle game-deletion and game-finishing.
 */
class GameFinalizer
{
   var $action_by; // ACTBY_...
   var $my_id; // for game-notify: <0=cron(timeout), 0=admin
   var $gid;
   var $tid;
   var $GameType;
   var $GameFlags;
   var $Black_id;
   var $White_id;
   var $Moves;
   var $skip_game_query;

   function GameFinalizer( $action_by, $my_id, $gid, $tid, $game_type, $game_flags, $black_id, $white_id, $moves )
   {
      $this->action_by = $action_by;
      $this->my_id = (int)$my_id;
      $this->gid = (int)$gid;
      $this->tid = (int)$tid;
      $this->GameType = $game_type;
      $this->GameFlags = (int)$game_flags;
      $this->Black_ID = (int)$black_id;
      $this->White_ID = (int)$white_id;
      $this->Moves = (int)$moves;
      $this->skip_game_query = false;
   }

   function skip_game_query()
   {
      $this->skip_game_query = true;
   }

   /**
    * \brief Finishes or deletes game.
    * \param $do_delete true=delete game, false=end-game
    * \param $game_updquery null=build default query, SQL-Games-update-query
    * \param $game_score score to end game with
    * \param $message message added in game-notify to players
    */
   function finish_game( $dbgmsg, $do_delete, $game_updquery, $game_score, $message='' )
   {
      global $NOW;
      $gid = $this->gid;
      $dbgmsg = "GameFinalizer::finish_game($gid).$dbgmsg";

      // update Games-entry
      if( !$this->skip_game_query && !$do_delete )
      {
         if( $this->action_by == ACTBY_ADMIN )
            $this->GameFlags |= GAMEFLAGS_ADMIN_RESULT;
         if( is_null($game_updquery) )
         {
            $game_updquery = "UPDATE Games SET Status='".GAME_STATUS_FINISHED."', " .
               "Last_X=". GameFinalizer::convert_score_to_posx($game_score) .", " .
               "ToMove_ID=0, " .
               "Flags={$this->GameFlags}, " .
               "Score=$game_score, " .
               "Lastchanged=FROM_UNIXTIME($NOW) " .
               "WHERE ID=$gid AND Status".IS_RUNNING_GAME." AND Moves={$this->Moves} LIMIT 1";
         }

         $result = db_query( "$dbgmsg.upd_game", $game_updquery );
         if( mysql_affected_rows() != 1 )
            error('mysql_update_game', "$dbgmsg.upd_game2");
      }

      // signal game-end for tournament
      if( $this->tid > 0 )
         TournamentGames::update_tournament_game_end( "$dbgmsg.tourney_game_end",
            $this->tid, $gid, $this->Black_ID, $game_score );

      // send message to my opponent / all-players / observers about the result
      $game_notify = new GameNotify( $gid, $this->my_id, $this->GameType, $this->GameFlags,
         $this->Black_ID, $this->White_ID, $game_score, $message );

      if( $do_delete )
      {
         GameHelper::delete_running_game( $gid );
         list( $Subject, $Text ) = $game_notify->get_text_game_deleted( $this->action_by );
      }
      else
      {
         if( $this->GameType != GAMETYPE_GO ) // MP-game
         {
            $arr_ratings = MultiPlayerGame::calc_average_group_ratings( $gid, /*rating-upd*/true );
            $rated_status = update_rating2($gid, true, false, $arr_ratings);
         }
         else
            $rated_status = update_rating2($gid);
         GameHelper::update_players_end_game( $dbgmsg,
            $gid, $this->GameType, $rated_status, $game_score, $this->Black_ID, $this->White_ID );

         list( $Subject, $Text, $observerText ) = $game_notify->get_text_game_result( $this->action_by );

         // GamesPriority-entries are kept for running games only, delete for finished games too
         NextGameOrder::delete_game_priorities( $gid );

         delete_all_observers($gid, ($rated_status != RATEDSTATUS_DELETABLE), $observerText);
      }

      // Send a message to the opponent
      send_message( "$dbgmsg.msg", $Text, $Subject
         , /*to*/$game_notify->get_recipients(), ''
         , /*notify*/false //the move itself is always notified, see below
         , /*system-msg*/0
         , 'RESULT', $gid );
   }//finish_game


   // ------------ static functions ----------------------------

   function convert_score_to_posx( $score )
   {
      if( abs($score) == SCORE_RESIGN )
         return POSX_RESIGN;
      elseif( abs($score) == SCORE_TIME )
         return POSX_TIME;
      else
         return POSX_SCORE;
   }//convert_score_to_posx

} // end 'GameFinalizer





/**
 * \brief Class to build game-related notifications.
 */
class GameNotify
{
   var $gid;
   var $uid; // can be 0 for admin (<> players)
   var $game_type;
   var $game_flags;
   var $black_id;
   var $white_id;
   var $score;
   var $message;

   var $players; // [ uid => [ ID/Name/Handle => ...], ... ]
   var $black_name;
   var $white_name;
   var $players_text;

   /*! \brief Constructs GameNotify also loading player-info from db. */
   function GameNotify( $gid, $uid, $game_type, $game_flags, $black_id, $white_id, $score, $message )
   {
      $this->gid = (int)$gid;
      $this->uid = (int)$uid;
      $this->game_type = $game_type;
      $this->game_flags = (int)$game_flags;
      $this->black_id = (int)$black_id;
      $this->white_id = (int)$white_id;
      $this->score = $score;
      $this->message = $message;

      $this->_load_players();
      $this->black_name = @$this->players[$black_id]['Name'];
      $this->white_name = @$this->players[$white_id]['Name'];
      $this->players_text = $this->_build_text_players();
   }

   /*! \brief Loads players (for simple-game B|W, for multi-player-game all players). */
   function _load_players()
   {
      $this->players = array();

      if( $this->game_type == GAMETYPE_GO )
      {
         $result = db_query( "GameNotify.find_players({$this->gid})",
            "SELECT ID, Handle, Name FROM Players WHERE ID IN ({$this->black_id},{$this->white_id}) LIMIT 2" );
      }
      else // mp-game
      {
         $result = db_query( "GameNotify.find_mpg_players({$this->gid})",
            "SELECT P.ID, P.Handle, P.Name " .
            "FROM GamePlayers AS GP INNER JOIN Players AS P ON P.ID=GP.uid " .
            "WHERE GP.gid={$this->gid}" );
      }

      while( $row = mysql_fetch_array( $result ) )
         $this->players[$row['ID']] = $row;
      mysql_free_result($result);
   }//_load_players

   function _build_text_players()
   {
      // NOTE: server messages does not allow a reply, so add an *in message* reference to players
      $arr = array();
      foreach( $this->players as $user_row )
         $arr[] = send_reference( REF_LINK, 1, '', $user_row );
      return "<p>Send a message to:<center>" . implode('<br>', $arr) . "</center>";
   }//_build_text_players

   /*! \brief Returns subject and text for message to players if game got deleted. */
   function get_text_game_deleted( $action_by=ACTBY_PLAYER )
   {
      global $player_row, $MAP_ACTBY_SUBJECT;

      $subject = 'Game deleted';
      if( $action_by == ACTBY_ADMIN || $action_by == ACTBY_CRON )
         $subject .= sprintf(' (by %s)', $MAP_ACTBY_SUBJECT[$action_by]);
      elseif( $action_by != ACTBY_PLAYER )
         $action_by = ACTBY_PLAYER;

      $text = "The game:<center>"
            . game_reference( REF_LINK, 1, '', $this->gid, 0, $this->white_name, $this->black_name) // game is deleted => no link
            . "</center>has been deleted by {$MAP_ACTBY_SUBJECT[$action_by]}:<center>"
            . send_reference( REF_LINK, 1, '', $player_row )
            . "</center>"
            . $this->players_text;

      if( $this->message )
         $text .= "<p>The {$MAP_ACTBY_SUBJECT[$action_by]} wrote:<p></p>" . $this->message;

      return array( $subject, $text );
   }//get_text_game_deleted

   /*! \brief Returns subject and text (and observer-text) for message to players/observers with normal game-result. */
   function get_text_game_result( $action_by=ACTBY_PLAYER )
   {
      global $MAP_ACTBY_SUBJECT;
      if( is_null($this->score) )
         error('invalid_args', "GameNotify.get_text_game_result.check.score({$this->gid})");

      $subject = 'Game result';
      if( $action_by == ACTBY_ADMIN )
         $subject .= sprintf(' (by %s)', $MAP_ACTBY_SUBJECT[$action_by]);
      elseif( $action_by != ACTBY_PLAYER && $action_by != ACTBY_CRON )
         $action_by = ACTBY_PLAYER;

      $text = "The result in the game:<center>"
            . game_reference( REF_LINK, 1, '', $this->gid, 0, $this->white_name, $this->black_name)
            . "</center>was:<center>"
            . score2text($this->score, true, true)
            . "</center>";

      $info_text = ( $this->game_flags & GAMEFLAGS_HIDDEN_MSG )
         ? "<p><b>Info:</b> The game has hidden comments!"
         : '';

      $player_text = $this->players_text;
      if( $this->message )
         $player_text .= "<p>The {$MAP_ACTBY_SUBJECT[$action_by]} wrote:<p></p>" . $this->message;

      return array( $subject, $text.$info_text.$player_text, $text.$player_text );
   }//get_text_game_result

   /*!
    * \brief Returns list of Players.IDs to which message should be sent (to all for time-out,
    *        otherwise only for others than current-user for resign/result/delete).
    */
   function get_recipients()
   {
      $arr = array_keys( $this->players );
      if( $this->uid > 0 && abs($this->score) != SCORE_TIME )
         unset($arr[$this->uid]);
      return $arr;
   }//get_recipients

} // end 'GameNotify'




// returns adjusted komi within limits, also checking for valid limits
function adjust_komi( $komi, $adj_komi, $jigo_mode )
{
   // adjust
   if( $adj_komi )
      $komi += $adj_komi;

   // assure valid limits up to the limits
   if( $komi < -MAX_KOMI_RANGE )
      $komi = -MAX_KOMI_RANGE;
   elseif( $komi > MAX_KOMI_RANGE )
      $komi = MAX_KOMI_RANGE;

   if( $jigo_mode == JIGOMODE_ALLOW_JIGO && floor($komi) != $komi )
      $komi = (($komi < 0) ? -1 : 1) * floor(abs($komi));
   elseif( $jigo_mode == JIGOMODE_NO_JIGO && floor($komi) == $komi )
      $komi += ($komi < 0) ? -0.5 : 0.5;

   // assure valid limits after applying jigo-mode
   $komi = (float) round( 2 * $komi ) / 2;
   if( $komi < -MAX_KOMI_RANGE )
      $komi += 1.0;
   elseif( $komi > MAX_KOMI_RANGE )
      $komi -= 1.0;

   if( (string)$komi == '-0' ) // strange effect
      $komi = 0;
   return (float)$komi;
}

// returns adjusted handicap within limits, also checking for valid limits
function adjust_handicap( $handicap, $adj_handicap, $min_handicap=0, $max_handicap=MAX_HANDICAP )
{
   // assure valid limits
   $min_handicap = min( MAX_HANDICAP, max( 0, $min_handicap ));
   $max_handicap = ( $max_handicap < 0 ) ? MAX_HANDICAP : min( MAX_HANDICAP, $max_handicap );
   if( $min_handicap > $max_handicap )
      swap( $min_handicap, $max_handicap );

   // adjust
   if( $adj_handicap )
      $handicap += $adj_handicap;

   if( $handicap < $min_handicap )
      $handicap = $min_handicap;
   elseif( $handicap > $max_handicap )
      $handicap = $max_handicap;

   return (int)$handicap;
}

/*! \brief Determines who is to move (BLACK|WHITE), expects game-row with fields ID,Black_ID,White_ID,ToMove_ID. */
function get_to_move( $grow, $errmsg )
{
   $to_move_id = $grow['ToMove_ID'];
   if( $grow['Black_ID'] == $to_move_id )
      $to_move = BLACK;
   elseif( $grow['White_ID'] == $to_move_id )
      $to_move = WHITE;
   elseif( $to_move_id )
      error('database_corrupted', "$errmsg({$grow['ID']})");
   else
      $to_move = -1; // can happen on finished game
   return $to_move;
}

/*!
 * \brief Returns handicap-type category for handicap-type (Waitingroom.)Handicaptype.
 * \return CAT_HTYPE_... if valid handicap-type given; false otherwise
 */
function get_category_handicaptype( $handitype )
{
   // handicap-types category
   static $ARR_GLOBAL_HTYPES = array(
         HTYPE_CONV     => CAT_HTYPE_CONV,
         HTYPE_PROPER   => CAT_HTYPE_PROPER,
         HTYPE_NIGIRI   => CAT_HTYPE_MANUAL,
         HTYPE_DOUBLE   => CAT_HTYPE_MANUAL,
         HTYPE_BLACK    => CAT_HTYPE_MANUAL,
         HTYPE_WHITE    => CAT_HTYPE_MANUAL,
         //HTYPE_AUKO     => CAT_HTYPE_FAIRKOMI,
      );
   return @$ARR_GLOBAL_HTYPES[$handitype];
}

function build_image_double_game( $with_sep=false, $class='' )
{
   global $base_path;
   return image( $base_path.'17/w.gif', T_('Double game (White)'), null, $class)
          . ( $with_sep ? '&nbsp;+&nbsp;' : '' )
          . image( $base_path.'17/b.gif', T_('Double game (Black)'), null, $class);
}

function getRulesetText( $ruleset=null )
{
   global $ARR_GLOBALS_GAME;

   // lazy-init of texts
   $key = 'RULESET';
   if( !isset($ARR_GLOBALS_GAME[$key]) )
   {
      $arr = array();
      $arr[RULESET_JAPANESE] = T_('Japanese#ruleset');
      $arr[RULESET_CHINESE]  = T_('Chinese#ruleset');
      $ARR_GLOBALS_GAME[$key] = $arr;
   }

   if( is_null($ruleset) )
      return $ARR_GLOBALS_GAME[$key];
   if( !isset($ARR_GLOBALS_GAME[$key][$ruleset]) )
      error('invalid_args', "getRulesetText($ruleset)");
   return $ARR_GLOBALS_GAME[$key][$ruleset];
}

function build_ruleset_filter_array( $prefix='' )
{
   return array(
      T_('All')                        => '',
      getRulesetText(RULESET_JAPANESE) => $prefix."Ruleset='".RULESET_JAPANESE."'",
      getRulesetText(RULESET_CHINESE)  => $prefix."Ruleset='".RULESET_CHINESE."'",
   );
}

function getRulesetScoring( $ruleset )
{
   static $arr = array(
      RULESET_JAPANESE => GSMODE_TERRITORY_SCORING,
      RULESET_CHINESE  => GSMODE_AREA_SCORING,
   );
   return $arr[$ruleset];
}

function get_gamesettings_viewmode( $viewmode )
{
   static $ARR = null;
   if( is_null($ARR) )
   {
      $ARR = array(
         GSETVIEW_SIMPLE => T_('simple settings#gsview'),
         GSETVIEW_EXPERT => T_('expert settings#gsview'),
         GSETVIEW_MPGAME => T_('multi-player settings#gsview'),
      );
   }
   return @$ARR[$viewmode];
}

// return arr( $must_be_rated, $rating1, $rating2 ) ready for db-insert
// note: multi-player-game requires rated game-players (RatingStatus != NONE),
//       see also append_form_add_waiting_room_game()-func
function parse_waiting_room_rating_range( $is_multi_player_game=false )
{
   if( !$is_multi_player_game && (string)get_request_arg('must_be_rated') != 'Y' )
   {
      $MustBeRated = 'N';
      //to keep a good column sorting:
      $rating1 = $rating2 = OUT_OF_RATING;
   }
   else
   {
      $MustBeRated = 'Y';
      $rating1 = read_rating( (string)get_request_arg('rating1') );
      $rating2 = read_rating( (string)get_request_arg('rating2') );

      if( $rating1 == NO_RATING || $rating2 == NO_RATING )
         error('rank_not_rating', "parse_waiting_room_rating_range.check($rating1,$rating2)");

      if( $rating2 < $rating1 )
         swap( $rating1, $rating2 );

      $rating2 += 50;
      $rating1 -= 50;
   }

   return array( $MustBeRated, $rating1, $rating2 );
}//parse_waiting_room_rating_range

// add form-elements required for new-game for waiting-room
// INPUT: must_be_rated, rating1, rating2, min_rated_games, comment
function append_form_add_waiting_room_game( &$mform, $viewmode )
{
   // note: multi-player-game requires rated game-players (RatingStatus != NONE)
   $req_rated = ($viewmode == GSETVIEW_MPGAME );
   $rating_array = getRatingArray();
   $mform->add_row( array( 'DESCRIPTION', T_('Require rated opponent'),
                           'CHECKBOXX', 'must_be_rated', 'Y', "", $req_rated, ($req_rated ? 'disabled=1' : ''),
                           'TEXT', sptext(T_('If yes, rating between'),1),
                           'SELECTBOX', 'rating1', 1, $rating_array, '30 kyu', false,
                           'TEXT', sptext(T_('and')),
                           'SELECTBOX', 'rating2', 1, $rating_array, '9 dan', false ) );

   if( $viewmode != GSETVIEW_SIMPLE )
      $mform->add_row( array( 'DESCRIPTION', T_('Min. rated finished games'),
                              'TEXTINPUT', 'min_rated_games', 5, 5, '',
                              'TEXT', MINI_SPACING . T_('(optional)'), ));

   if( $viewmode == GSETVIEW_EXPERT )
   {
      $same_opp_array = build_accept_same_opponent_array(array( 0,  -1, -2, -3,  3, 7, 14 ));
      $mform->add_row( array( 'DESCRIPTION', T_('Accept same opponent'),
                              'SELECTBOX', 'same_opp', 1, $same_opp_array, '0', false, ));
   }

   $mform->add_row( array( 'SPACE' ) );
   $mform->add_row( array( 'DESCRIPTION', T_('Comment'),
                           'TEXTINPUT', 'comment', 40, 40, "" ) );
}//append_form_add_waiting_room_game

// WaitingRoom.SameOpponent: 0=always, <0=n times, >0=after n days
function echo_accept_same_opponent( $same_opp, $game_row=null )
{
   if( $same_opp == 0 )
      return T_('always#same_opp');

   if( $same_opp < 0 )
   {
      if ($same_opp == -1)
         $out = T_('1 time#same_opp');
      else //if ($same_opp < 0)
         $out = sprintf( T_('%s times#same_opp'), -$same_opp );
      if( is_array($game_row) && (int)@$game_row['JoinedCount'] > 0 )
      {
         $join_fmt = ($game_row['JoinedCount'] > 1)
            ? T_('joined %s games#same_opp') : T_('joined %s game#same_opp');
         $out .= ' (' . sprintf( $join_fmt, $game_row['JoinedCount'] ) . ')';
      }
   }
   else
   {
      global $NOW;
      if ($same_opp == 1)
         $out = T_('after 1 day#same_opp');
      else //if ($same_opp > 0)
         $out = sprintf( T_('after %s days#same_opp'), $same_opp );
      if( is_array($game_row) && isset($game_row['X_ExpireDate'])
            && ($game_row['X_ExpireDate'] > $NOW) )
      {
         $out .= ' (' . sprintf( T_('wait till %s#same_opp'),
            date(DATE_FMT6, $game_row['X_ExpireDate']) ) . ')';
      }
   }
   return $out;
}//echo_accept_same_opponent

function build_accept_same_opponent_array( $arr )
{
   $out = array();
   foreach( $arr as $same_opp )
      $out[$same_opp] = echo_accept_same_opponent($same_opp);
   return $out;
}

function build_arr_handicap_stones()
{
   $handi_stones = array( 0 => 0 );
   for( $bs = 2; $bs <= MAX_HANDICAP; $bs++ )
      $handi_stones[$bs] = $bs;
   return $handi_stones;
}

function build_suggestion_shortinfo( $suggest_result, $mpgame=false )
{
   list( $handi, $komi, $iamblack ) = $suggest_result;
   $info = sprintf(
      ($mpgame)
         ? T_('... your Color is probably %1$s with Handicap %2$s, Komi %3$.1f')
         : T_('... your Color would be %1$s with Handicap %2$s, Komi %3$.1f'),
      get_colortext_probable( $iamblack ), $handi, $komi );
   return $info;
}

function get_colortext_probable( $iamblack )
{
   global $base_path;
   $color_class = 'class="InTextStone"';
   return ( $iamblack )
      ? image( $base_path.'17/b.gif', T_('Black'), null, $color_class)
      : image( $base_path.'17/w.gif', T_('White'), null, $color_class);
}

?>
