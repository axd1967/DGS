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

$TranslateGroups[] = "Game";

require_once 'include/globals.php';
require_once 'include/db/games.php';
require_once 'include/time_functions.php';
require_once 'include/classlib_game.php';
require_once 'include/classlib_profile.php';
require_once 'include/classlib_user.php';
require_once 'include/utilities.php';
require_once 'include/std_functions.php';
require_once 'include/time_functions.php';
require_once 'include/game_texts.php';
require_once 'include/error_codes.php';
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

define('SAMEOPP_TOTAL', -100); // base-value for total-game Waitingroom.SameOpponent-check

// handicap-types, see Waitingroom.Handicaptype in specs/db/table-Waitingroom.txt
define('HTYPE_CONV',    'conv'); // conventional handicap
define('HTYPE_PROPER',  'proper'); // proper handicap
define('HTYPE_NIGIRI',  'nigiri'); // manual, color nigiri
define('HTYPE_DOUBLE',  'double'); // manual, color double (=black and white)
define('HTYPE_BLACK',   'black'); // manual, color black
define('HTYPE_WHITE',   'white'); // manual, color white
define('HTYPE_AUCTION_SECRET', 'auko_sec'); // fair-komi: secret auction komi
//define('HTYPE_AUCTION_OPEN', 'auko_opn'); // fair-komi: open auction komi
//define('HTYPE_YOU_KOMI_I_COLOR', 'div_ykic'); // fair-komi: You cut (choose komi), I choose (color)
//define('HTYPE_I_KOMI_YOU_COLOR', 'div_ikyc'); // fair-komi: I cut (choose komi), You choose (color)
define('HTYPEMP_MANUAL', 'manual'); // manual, only used for multi-player-game in waiting-room
define('CHECK_HTYPES_FAIRKOMI', 'auko_sec|auko_opn|div_ykic|div_ikyc');

// handicap-types for invitations, stored in Games.ToMove_ID, must be <0
define('INVITE_HANDI_CONV',   -1);
define('INVITE_HANDI_PROPER', -2);
define('INVITE_HANDI_NIGIRI', -3);
define('INVITE_HANDI_DOUBLE', -4);
define('INVITE_HANDI_FIXCOL', -5);
define('INVITE_HANDI_AUCTION_SECRET', -6);

// handicap-type categories
define('CAT_HTYPE_CONV', HTYPE_CONV); // conventional handicap-type
define('CAT_HTYPE_PROPER', HTYPE_PROPER); // proper handicap-type
define('CAT_HTYPE_MANUAL', 'manual'); // manual game setting
define('CAT_HTYPE_FAIR_KOMI', 'fairkomi'); // fair-komi setting

// lazy-init in Tournament::get..Text()-funcs
global $ARR_GLOBALS_GAME; //PHP5
$ARR_GLOBALS_GAME = array();

define('GS_SEP', ':'); // separator for game-setup
define('GS_SEP_INVITATION', ' '); // separator for invitational game-setup

global $MAP_GAME_SETUP; //PHP5;
$MAP_GAME_SETUP = array(
   // map Handicap-types -> encoded GameSetup-handi-type
   HTYPE_CONV     => 'T1',
   HTYPE_PROPER   => 'T2',
   HTYPE_NIGIRI   => 'T3',
   HTYPE_DOUBLE   => 'T4',
   HTYPE_BLACK    => 'T5',
   HTYPE_WHITE    => 'T6',
   HTYPE_AUCTION_SECRET    => 'T7',
   //HTYPE_AUCTION_OPEN      => 'T8',
   //HTYPE_YOU_KOMI_I_COLOR  => 'T9',
   //HTYPE_I_KOMI_YOU_COLOR  => 'T10',

   // map GameSetup-handi-type -> Handicap-types
   'T1'  => HTYPE_CONV,
   'T2'  => HTYPE_PROPER,
   'T3'  => HTYPE_NIGIRI,
   'T4'  => HTYPE_DOUBLE,
   'T5'  => HTYPE_BLACK,
   'T6'  => HTYPE_WHITE,
   'T7'  => HTYPE_AUCTION_SECRET,
   //'T8'  => HTYPE_AUCTION_OPEN,
   //'T9'  => HTYPE_YOU_KOMI_I_COLOR,
   //'T10' => HTYPE_I_KOMI_YOU_COLOR,

   // map JigoMode -> encoded GameSetup-jigo-mode
   JIGOMODE_KEEP_KOMI   => 'J0',
   JIGOMODE_ALLOW_JIGO  => 'J1',
   JIGOMODE_NO_JIGO     => 'J2',

   // map GameSetup-jigo-mode -> JigoMode
   'J0' => JIGOMODE_KEEP_KOMI,
   'J1' => JIGOMODE_ALLOW_JIGO,
   'J2' => JIGOMODE_NO_JIGO,

   // map Ruleset -> encoded GameSetup-ruleset
   RULESET_JAPANESE => 'r1',
   RULESET_CHINESE  => 'r2',

   'r1' => RULESET_JAPANESE,
   'r2' => RULESET_CHINESE,

   // map Byotype -> encoded GameSetup-byotype
   BYOTYPE_JAPANESE => 'tJ',
   BYOTYPE_CANADIAN => 'tC',
   BYOTYPE_FISCHER => 'tF',

   'tJ' => BYOTYPE_JAPANESE,
   'tC' => BYOTYPE_CANADIAN,
   'tF' => BYOTYPE_FISCHER,
);//MAP_GAME_SETUP


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
            "SELECT G.* from Games AS G WHERE G.ID=$gid");
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
   }//make_add_time_info

   /*!
    * \brief returns true, if user (uid) (or TD) is allowed to add additional
    *        time for uid's opponent in game specified by $game_row.
    * \param $game_row expect fields: Status, (Black|White)_(ID|Maintime), tid
    * \param $is_tdir true, if tournament-director with TD_FLAG_GAME_ADD_TIME-right
    */
   function allow_add_time_opponent( $game_row, $uid, $is_tdir=false )
   {
      // must be a running-game (not allowed for fair-komi)
      if( !isRunningGame($game_row['Status']) )
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
   }//allow_add_time_opponent

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
    *
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
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


      // NOTE: HOT-SECTION to avoid double-clicks must be done by caller
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
    * \return true if slots removed; false if nothing updated
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
         return true;
      }
      else
         return false;
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

   /*!
    * \brief Inserts game-players entries for given game-id and game-master $uid.
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
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

      MultiPlayerGame::change_player_mpg_count( "$dbgmsg.init_multi_player_game", $gid, $uid, 1 );
      MultiPlayerGame::change_joined_players( $dbgmsg, $gid, 1 );
   }

   /*!
    * \brief Joins waiting-room MP-game for given user and check for race-conditions.
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section if used with other db-writes!!
    */
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

      MultiPlayerGame::change_player_mpg_count( "$dbgmsg.join_waitingroom_game", $gid, $uid, 1 );
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

   function change_player_mpg_count( $dbgmsg, $gid, $uid, $diff )
   {
      db_query( "$dbgmsg.change_player_mpg_count($gid,$uid,$diff)",
         "UPDATE Players SET GamesMPG=GamesMPG+($diff) WHERE ID=$uid LIMIT 1" );
   }//change_player_mpg_count

   /*! \brief Starts MP-game for all participating (joined) players. */
   function update_players_start_mpgame( $gid )
   {
      // update Players for all game-players: Running++, GamesMPG--
      db_query( "MultiPlayerGame::update_players_start_mpgame($gid)",
         "UPDATE Players AS P INNER JOIN GamePlayers AS GP ON GP.uid=P.ID "
            . "SET P.Running=P.Running+1, P.GamesMPG=P.GamesMPG-1 WHERE GP.gid=$gid" );
   }//update_players_start_mpgame

   /*!
    * \brief Updates player on end of MP-game for given game-id.
    * \param $game_in_setup_mode true, if Players.GamesMPG must be synchronized because game in setup-mode
    */
   function update_players_end_mpgame( $gid, $game_in_setup_mode )
   {
      // update Players for all game-players: GamesMPG-- if game in SETUP-mode (for joined or reserved-invitation)
      $qpart_del = ($game_in_setup_mode)
         ? ", P.GamesMPG=P.GamesMPG - IF( ( (GP.Flags & ".GPFLAG_JOINED.") OR " .
               "((GP.Flags & ".GPFLAGS_RESERVED_INVITATION.")=".GPFLAGS_RESERVED_INVITATION.") ), 1, 0) "
         : '';

      // update Players for all game-players: Running--, Finished++;
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
    *       'shape_id' = shape-id
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
            $shape_id    = @$mpg_arr['shape_id'];
            return array(
               // subject
               sprintf( T_('Invitation to multi-player-game from [%s]#mpg'), $from_handle ),
               // body
               sprintf( T_('Game-master %s invites you to a %s multi-player-game.#mpg'), "<user =$from_handle>", $game_type ) . "\n" .
               ( $shape_id > 0 ? sprintf( T_('This game is a shape-game (Shape #%s)#mpg'), $shape_id ) . "\n" : '' ) . "\n" .
               sprintf( T_('You can accept or reject the invitation on setup-page of game: %s#mpg'), "<game_ $mpg_gid>" ) . "\n\n" .
               T_('To reject the invitation, please inform the game-master by replying to this message.#mpg') . "\n" .
               T_('You may also want to discuss what team, color or playing order you prefer in the game (see FAQ for more details).#mpg') . "\n\n"
            );

         case MPGMSG_STARTGAME:
         default:
            return array(
               T_('Everybody ready to start the game?#mpg'),
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

   /*! \brief Returns true, if GamePlayer-entry exists for given game-id and user-id (and flags). */
   function exists_game_player( $gid, $uid, $flags=0 )
   {
      $row = mysql_single_fetch( "GamePlayer::exists_game_player($gid,$uid,$flags)",
            "SELECT Flags FROM GamePlayers WHERE gid=$gid AND uid=$uid LIMIT 1" );
      return ( $row && $flags > 0 )
         ? ($row['Flags'] & $flags)
         : (bool) $row;
   }//exists_game_player

   /*!
    * \brief Returns array of Players.Handle for given game-id (and group-color).
    * \param $arr_users if non-null array given, clear it, and save as arr_users["$group_color:$group_order"]
    *        = ( GroupColor/GroupOrder/uid/Handle/Name/Rating2/Sessioncode/Sessionexpire => values, ... )
    * \return [ Players.ID => Players.Handle, ... ]
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
      $out = array(); # uid => Handle
      $arr_users = array(); // clear
      while( $row = mysql_fetch_array( $result ) )
      {
         if( $skip_myself && $player_row['Handle'] == $row['Handle'] )
            continue;
         $out[$row['uid']] = $row['Handle'];
         $arr_users["{$row['GroupColor']}:{$row['GroupOrder']}"] = $row;
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

   /*!
    * \brief Deletes reserved invitation for MP-game.
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
   function delete_reserved_invitation( $gid, $uid )
   {
      db_query( "GamePlayer::delete_reserved_invitation.gp_upd($gid,$uid)",
         "UPDATE GamePlayers SET uid=0, GroupColor='".GPCOL_DEFAULT."', GroupOrder=0, Flags=0 " .
         "WHERE gid=$gid AND uid=$uid AND " .
            "(Flags & ".GPFLAGS_RESERVED_INVITATION.") = ".GPFLAGS_RESERVED_INVITATION." " .
         "LIMIT 1" );

      MultiPlayerGame::change_player_mpg_count( "$dbgmsg.delete_reserved_invitation", $gid, $uid, -1 );
   }//delete_reserved_invitation

   /*!
    * \brief Deletes joined game-player of MP-game.
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
   function delete_joined_player( $gid, $uid )
   {
      db_query( "GamePlayer::delete_joined_player.gp_upd($gid,$uid)",
         "UPDATE GamePlayers SET uid=0, GroupColor='".GPCOL_DEFAULT."', GroupOrder=0, Flags=0 " .
         "WHERE gid=$gid AND uid=$uid AND (Flags & ".GPFLAG_JOINED.") > 0 " .
         "LIMIT 1" );

      MultiPlayerGame::change_player_mpg_count( "$dbgmsg.delete_joined_player", $gid, $uid, -1 );
   }//delete_joined_player

} // end 'GamePlayer'




/**
 * \brief Class to handle general game-actions.
 */
class GameHelper
{

   /*!
    * \brief Deletes non-tourney game and all related tables for given game-id, if not finished yet.
    * \param $upd_players if true, also decrease Players.Running
    * \param $grow game-row can be provided to save additional query
    * \return true on success, false on invalid args or invalid game to delete (finished, tourney)
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
      elseif( @$grow['Status'] == GAME_STATUS_FINISHED ) // must not be finished game, allow for setup/fair-komi
      {
         error('invalid_args', "GameHelper::delete_running_game.check.status($gid,{$grow['Status']})");
         return false; // if passing through on error()
      }

      ta_begin();
      {//HOT-section to delete game table-entries for running game
         GameHelper::remove_double_game_reference( $gid, (int)@$grow['DoubleGame_ID'] );

         // delete potentially assigned waiting-room-entry (max. 1)
         if( $grow['GameType'] != GAMETYPE_GO )
            db_query( "GameHelper::delete_running_game.del_wroom($gid)",
               "DELETE FROM Waitingroom WHERE gid=$gid LIMIT 1" );

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

         GameHelper::_delete_base_game_tables( $gid );
      }
      ta_end();

      return true;
   }//delete_running_game

   /*!
    * \brief Deletes finished non-tourney unrated game and all related tables for given game-id.
    * \note Players-table will be updated (decrease Players.Finished/etc)
    * \return true on success, false on invalid args or invalid game to delete (not finished, tourney, rated game)
    */
   function delete_finished_unrated_game( $gid )
   {
      if( !is_numeric($gid) && $gid <= 0 )
         return false;

      $grow = mysql_single_fetch( "GameHelper::delete_finished_unrated_game.check.gid($gid)",
         "SELECT Status, tid, DoubleGame_ID, GameType, Black_ID, White_ID, Rated " .
         "FROM Games WHERE ID=$gid LIMIT 1");
      if( is_null($grow) )
         return false;
      elseif( @$grow['Status'] != GAME_STATUS_FINISHED ) // must be finished game
      {
         error('invalid_args', "GameHelper::delete_finished_unrated_game.check.status($gid,{$grow['Status']})");
         return false; // if passing through on error()
      }
      elseif( (int)@$grow['tid'] > 0 )
         return false;
      elseif( @$grow['Rated'] != 'N' )
         return false;

      ta_begin();
      {//HOT-section to delete game table-entries for finished game
         GameHelper::remove_double_game_reference( $gid, (int)@$grow['DoubleGame_ID'] );

         if( $grow['GameType'] == GAMETYPE_GO )
         {
            $Black_ID = (int)@$grow['Black_ID'];
            $White_ID = (int)@$grow['White_ID'];
            db_query( "GameHelper::delete_finished_unrated_game.upd_players($gid,$Black_ID,$White_ID)",
               "UPDATE Players SET Finished=Finished-1 WHERE ID IN ($Black_ID,$White_ID) LIMIT 2" );
         }
         else
         {
            db_query( "GameHelper::delete_finished_unrated_game.upd_mgplayers($gid)",
               "UPDATE Players AS P INNER JOIN GamePlayers AS GP ON GP.uid=P.ID "
                  . "SET P.Finished=P.Finished-1 WHERE GP.gid=$gid" );
         }

         GameHelper::_delete_base_game_tables( $gid );
      }
      ta_end();

      return true;
   }//delete_finished_unrated_game

   /*! \brief Marks reference in other double-game to indicate referring game has vanished. */
   function remove_double_game_reference( $gid, $double_gid )
   {
      $double_gid = (int)$double_gid;
      if( $double_gid > 0 )
         db_query( "GameHelper::remove_double_game_reference.doublegame($gid)",
            "UPDATE Games SET DoubleGame_ID=-ABS(DoubleGame_ID) WHERE ID=$double_gid LIMIT 1" );
   }//remove_double_game_reference

   /*!
    * \brief Deletes basic game-related table-entries for given game-id.
    * \internal
    */
   function _delete_base_game_tables( $gid )
   {
      NextGameOrder::delete_game_priorities($gid);
      db_query( "GameHelper::_delete_base_game_tables.observers($gid)",
         "DELETE FROM Observers WHERE ID=$gid" );
      db_query( "GameHelper::_delete_base_game_tables.notes($gid)",
         "DELETE FROM GamesNotes WHERE gid=$gid" );
      db_query( "GameHelper::_delete_base_game_tables.movemsg($gid)",
         "DELETE FROM MoveMessages WHERE gid=$gid" );
      db_query( "GameHelper::_delete_base_game_tables.moves($gid)",
         "DELETE FROM Moves WHERE gid=$gid" );
      db_query( "GameHelper::_delete_base_game_tables.gameplayers($gid)",
         "DELETE FROM GamePlayers WHERE gid=$gid" );
      db_query( "GameHelper::_delete_base_game_tables.games($gid)",
         "DELETE FROM Games WHERE ID=$gid LIMIT 1" );
   }//_delete_base_game_tables

   /*!
    * \brief Deletes game-invitation game (on INVITED-status).
    * \return true for success; false on failure.
    */
   function delete_invitation_game( $dbgmsg, $gid, $uid1, $uid2 )
   {
      db_query( "GameHelper::delete_invitation_game.delgame.$dbgmsg($gid,$uid1,$uid2)",
         "DELETE FROM Games WHERE ID=$gid AND Status='".GAME_STATUS_INVITED."' " .
            "AND ( Black_ID=$uid1 OR White_ID=$uid1 ) " .
            "AND ( Black_ID=$uid2 OR White_ID=$uid2 ) " .
            "LIMIT 1" );
      if( mysql_affected_rows() != 1)
      {
         error('game_delete_invitation', "GameHelper::delete_invitation_game.delres.$dbgmsg($gid,$uid1,$uid2)");
         return false;
      }
      else
         return true;
   }//delete_invitation_game

   function update_players_start_game( $dbgmsg, $uid1, $uid2, $game_count, $rated_game )
   {
      $dbgmsg = "GameHelper::update_players_start_game($uid1,$uid2,$game_count,$rated_game).$dbgmsg";
      if( !is_numeric($uid1) || !is_numeric($uid2) )
         error('invalid_args', "$dbgmsg.check.uids");

      $upd_players = new UpdateQuery('Players');
      $upd_players->upd_raw('Running', "Running + " . (int)$game_count );
      if( $rated_game )
         $upd_players->upd_txt('RatingStatus', RATING_RATED);
      db_query( "$dbgmsg.update",
         "UPDATE Players SET " . $upd_players->get_query() . " WHERE ID IN ($uid1,$uid2) LIMIT 2" );
   }//update_players_start_game

   /*!
    * \brief Updates game-stats in Players-table for simple or multi-player game.
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
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

   /*! \brief Returns number of started games (used for same-opponent-check). */
   function count_started_games( $uid, $opp )
   {
      $row = mysql_single_fetch( "GameHelper::count_started_games($uid,$opp)",
            "SELECT ((SELECT COUNT(*) FROM Games AS G1 WHERE G1.Status".IS_STARTED_GAME." AND G1.GameType='".GAMETYPE_GO."' AND G1.Black_ID=$uid AND G1.White_ID=$opp) + " .
                   " (SELECT COUNT(*) FROM Games AS G2 WHERE G2.Status".IS_STARTED_GAME." AND G2.GameType='".GAMETYPE_GO."' AND G2.Black_ID=$opp AND G2.White_ID=$uid)) " .
                   "AS X_Count" );
      return ($row) ? (int)$row['X_Count'] : 0;
   }

   /*!
    * \brief Updates clock-values for game.
    * \param $grow game-row with mandatory fields:
    *        Maintime, Byotime, ClockUsed, LastTicks, WeekendClock,
    *        Black_Byoperiods, Black_Byotime, Black_Maintime, Blackonvacation, X_BlackClock,
    *        White_Byoperiods, White_Byotime, White_Maintime, Whiteonvacation, X_WhiteClock
    * \return array( hours, UpdateQuery )
    */
   function update_clock( $grow, $to_move, $next_to_move, $do_ticks=true )
   {
      $upd_query = new UpdateQuery('Games');
      $hours = 0;
      if( $grow['Maintime'] > 0 || $grow['Byotime'] > 0)
      {
         if( $do_ticks )
         {
            // LastTicks may handle -(time spend) at the moment of the start of vacations
            // time since start of move in the reference of the ClockUsed by the game
            $hours = ticks_to_hours(get_clock_ticks($grow['ClockUsed']) - $grow['LastTicks']);

            if( $to_move == BLACK )
            {
               time_remaining( $hours,
                  $grow['Black_Maintime'], $grow['Black_Byotime'], $grow['Black_Byoperiods'],
                  $grow['Maintime'], $grow['Byotype'], $grow['Byotime'], $grow['Byoperiods'], true);
               $upd_query->upd_num('Black_Maintime', $grow['Black_Maintime']);
               $upd_query->upd_num('Black_Byotime', $grow['Black_Byotime']);
               $upd_query->upd_num('Black_Byoperiods', $grow['Black_Byoperiods']);
            }
            else
            {
               time_remaining( $hours,
                  $grow['White_Maintime'], $grow['White_Byotime'], $grow['White_Byoperiods'],
                  $grow['Maintime'], $grow['Byotype'], $grow['Byotime'], $grow['Byoperiods'], true);
               $upd_query->upd_num('White_Maintime', $grow['White_Maintime']);
               $upd_query->upd_num('White_Byotime', $grow['White_Byotime']);
               $upd_query->upd_num('White_Byoperiods', $grow['White_Byoperiods']);
            }
         }//do_ticks

         $next_onvacation = ( $next_to_move == BLACK ) ? $grow['Blackonvacation'] : $grow['Whiteonvacation'];
         if( $next_onvacation > 0 ) // next-player on vacation
            $next_clockused = VACATION_CLOCK; //and LastTicks=0, see below
         else
         {
            $next_clockused = ( $next_to_move == BLACK ? $grow['X_BlackClock'] : $grow['X_WhiteClock'] );
            if( $grow['WeekendClock'] != 'Y' )
               $next_clockused += WEEKEND_CLOCK_OFFSET;
         }

         $next_lastticks = get_clock_ticks($next_clockused);
         $timeout_date = NextGameOrder::make_timeout_date( $grow, $next_to_move, $next_lastticks );

         $upd_query->upd_num('LastTicks', $next_lastticks);
         $upd_query->upd_num('ClockUsed', $next_clockused);
         $upd_query->upd_num('TimeOutDate', $timeout_date);
      }
      return array( $hours, $upd_query );
   }//update_clock

} // end 'GameHelper'




/**
 * \brief Class to handle fair-komi.
 */
class FairKomiNegotiation
{
   var $gid;
   var $game_setup; // GameSetup-obj
   var $game_status; // GAME_STATUS_...
   var $black_id;
   var $white_id;
   var $tomove_id;

   function FairKomiNegotiation( $game_setup, $game_row )
   {
      $this->game_setup = $game_setup;
      $this->read_from_game_row( $game_row );
   }

   /*! \brief Reads fields from row: ID, Status, Black_ID, White_ID, ToMove_ID. */
   function read_from_game_row( $grow )
   {
      $this->gid = (int)$grow['ID'];
      $this->game_status = $grow['Status'];
      $this->black_id = (int)$grow['Black_ID'];
      $this->white_id = (int)$grow['White_ID'];
      $this->tomove_id = (int)$grow['ToMove_ID'];
   }

   function has_both_komibids()
   {
      return !is_null($this->game_setup->Komi) && !is_null($this->game_setup->OppKomi);
   }

   function get_komibid( $uid )
   {
      return ( $uid == $this->game_setup->uid )
         ? $this->game_setup->Komi
         : $this->game_setup->OppKomi;
   }

   function set_komibid( $uid, $komibid )
   {
      if( $uid != $this->black_id && $uid != $this->white_id )
         error('invalid_args', "FKN.set_komibid({$this->gid},$uid)");
      if( $uid == $this->game_setup->uid )
         $this->game_setup->Komi = $komibid;
      else
         $this->game_setup->OppKomi = $komibid;
   }

   function get_uid_highest_bid()
   {
      if( !$this->has_both_komibids() )
         return 0;
      if( $this->game_setup->Komi > $this->game_setup->OppKomi )
         return $this->game_setup->uid;
      elseif( $this->game_setup->Komi < $this->game_setup->OppKomi )
         return ( $this->game_setup->uid == $this->black_id ) ? $this->white_id : $this->black_id;
      else // nigiri
      {
         mt_srand ((double) microtime() * 1000000);
         return ( mt_rand(0,1) ) ? $this->black_id : $this->white_id;
      }
   }//get_uid_highest_bid

   /*! \brief Show text for komi-bid of player $player_id to viewer $viewer_uid. */
   function get_view_komibid( $viewer_uid, $player_id, $form=null, $komibid_input=null )
   {
      global $base_path;
      if( $player_id != $this->black_id && $player_id != $this->white_id )
         error('invalid_args', "FKN.get_view_komibid.check.player({$this->gid},$viewer_uid,$player_id)");

      $htype = $this->game_setup->Handicaptype;
      $is_negotiation = ( $this->game_status == GAME_STATUS_KOMI );
      $komibid = $this->get_komibid( $player_id );
      $secret_text = T_('Top Secret#fairkomi');

      if( !$is_negotiation || $viewer_uid != $player_id || $player_id != $this->tomove_id || is_null($form) ) // view-only
      {
         $hide_komibid = ( $htype == HTYPE_AUCTION_SECRET );

         if( is_null($komibid) )
            $result = sprintf( '(%s)', T_('No bid yet#fairkomi') );
         elseif( $is_negotiation && $hide_komibid && $viewer_uid != $player_id )
            $result = $secret_text;
         else
            $result = $komibid;

         if( $is_negotiation && $this->tomove_id == $player_id )
            $result .= MED_SPACING . image( $base_path.'images/prev.gif', T_('Player to move'), null, 'class=InTextImage' );
      }
      else // viewer is player and to-move -> show input-box
      {
         $curr_bid = ( is_null($komibid) )
            ? sprintf( '(%s)', T_('No bid yet#fairkomi') )
            : sprintf( '%s (%s)', $komibid, T_('Current bid#fairkomi') );

         if( is_null($komibid_input) )
            $komibid_input = $komibid;
         $result = $form->print_insert_text_input('komibid', 8, 8, $komibid_input) . SMALL_SPACING . $curr_bid;
      }

      return $result;
   }//get_view_komibid

   function echo_fairkomi_table( $form, $user, $show_bid, $my_id )
   {
      $htype = $this->game_setup->Handicaptype;

      $form_actions = ($my_id == $this->tomove_id) ? $this->build_form_actions( $form ) : '';

      echo "<table id=\"fairkomiTable\" class=\"Infos\">",
         "<tr><td class=Caption colspan=2>",
            span('Caption', GameTexts::get_fair_komi_types($htype)), "<br>\n",
            '(', GameTexts::get_jigo_modes(true, $this->game_setup->JigoMode), ')',
         "</td></tr>\n",
         "<tr class=Header><td>", T_('Player'), "</td><td>", T_('Komi Bid#fairkomi'), "</td></tr>\n",
         "<tr><td>", $user[0], "</td><td>", $show_bid[0], "</td></tr>\n",
         "<tr><td>", $user[1], "</td><td>", $show_bid[1], "</td></tr>\n",
         "</table>\n",
         span('Center', $form_actions), "<br><br>\n";
   }//echo_fairkomi_table

   function build_form_actions( $form )
   {
      $htype = $this->game_setup->Handicaptype;
      if( $htype == HTYPE_AUCTION_SECRET )
         $out = $form->print_insert_submit_button('komi_save', T_('Save Komi Bid') );
      else
         $out = '';
      return $out;
   }//build_form_actions

   function build_notes()
   {
      $htype = $this->game_setup->Handicaptype;
      $notes = array();

      if( $htype == HTYPE_AUCTION_SECRET )
      {
         $notes[] = T_('Players secretly give their respective komi-bid (once per player).#fairkomi');
         $notes[] = T_('Komi-bids are hidden from the other player.#fairkomi');
         $notes[] = array(
            T_('After the 2nd komi-bid, the game will be started:#fairkomi'),
            T_('The player with the highest bid takes Black and is giving that number of komi to White.#fairkomi'),
            T_('If the komi-bids are equal, player color is determined by Nigiri.#fairkomi'), );
      }

      return $notes;
   }//build_notes


   /*!
    * \brief Saves and process komi-bid dependent on handi-type.
    * \return 0=saved-komi, 1=saved+started-game; otherwise text-error-code
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
   function save_komi( $game_row, $komibid )
   {
      $to_move = ( $this->tomove_id == $this->black_id ) ? BLACK : WHITE;
      $next_to_move = BLACK + WHITE - $to_move;
      $next_tomove_id = ( $this->tomove_id == $this->black_id ) ? $this->white_id : $this->black_id;
      $my_id = $this->tomove_id;

      list( $hours, $upd_game ) = GameHelper::update_clock( $game_row, $to_move, $next_to_move );
      $upd_game->upd_num('ToMove_ID', $next_tomove_id );

      // eventually determine komi/colors + start-game
      $start_game_new_black = 0;
      if( $this->game_setup->Handicaptype == HTYPE_AUCTION_SECRET )
      {
         // save komi-bid for player to-move
         $this->set_komibid( $this->tomove_id, $komibid );
         $upd_game->upd_txt('GameSetup', $this->game_setup->encode());

         if( $this->has_both_komibids() )
            $start_game_new_black = $this->get_uid_highest_bid();
      }

      // first update clock-stuff, next to-move-id
      $upd_game->upd_time('Lastchanged');
      db_query( "FKN.save_komi.update({$this->gid},{$this->tomove_id},$komibid)",
         "UPDATE Games SET " . $upd_game->get_query()
         . " WHERE ID={$this->gid} AND Status='".GAME_STATUS_KOMI."' AND ToMove_ID={$this->tomove_id} LIMIT 1" );

      global $ActivityMax, $ActivityForMove;
      $upd_player = new UpdateQuery('Players');
      $upd_player->upd_raw('Activity', "LEAST($ActivityMax,$ActivityForMove+Activity)");
      $upd_player->upd_time('LastMove');
      db_query( "FKN.save_komi.upd_activity({$this->gid},$my_id)",
         "UPDATE Players SET " . $upd_player->get_query() . " WHERE ID=$my_id LIMIT 1" );

      if( $start_game_new_black > 0 )
      {
         $this->start_fairkomi_game( $start_game_new_black );
         $result = 1; // komi-bid saved + started game
      }
      else
         $result = 0; // komi-bid saved

      return $result;
   }//save_komi

   /*!
    * \brief Starts fairkomi-game by updating existing game on KOMI-status.
    * \see also create_game()-func in "include/make_game.php"
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
   function start_fairkomi_game( $new_black_id )
   {
      // re-read Games in order to switch values if B/W-players changed
      $gid = $this->gid;
      $dbgmsg = "FKN.start_fairkomi_game($gid,$new_black_id)";
      $grow = mysql_single_fetch( "$dbgmsg.find_game",
            "SELECT G.*, " .
            "black.ClockUsed AS X_BlackClock, " .
            "white.ClockUsed AS X_WhiteClock, " .
            "black.OnVacation AS Blackonvacation, " .
            "white.OnVacation AS Whiteonvacation " .
            "FROM Games AS G " .
                "INNER JOIN Players AS black ON black.ID=G.Black_ID " .
                "INNER JOIN Players AS white ON white.ID=G.White_ID " .
            "WHERE G.ID=$gid LIMIT 1" )
         or error('unknown_game', "$dbgmsg.find_game2");
      $this->read_from_game_row( $grow );

      // checks
      if( $this->game_status != GAME_STATUS_KOMI )
         error('internal_error', "$dbgmsg.check.status({$this->game_status})");
      if( $new_black_id != $this->black_id && $new_black_id != $this->white_id )
         error('wrong_players', "$dbgmsg.check.players");
      if( $this->black_id != $this->game_setup->uid )
         error('wrong_players', "$dbgmsg.check.gs_uid({$this->game_setup->uid})");

      $new_white_id = ( $new_black_id == $this->black_id ) ? $this->white_id : $this->black_id;

      // handle shape-game (need to determine color to start playing)
      $black_first = true; // fair-komi has NO handicap
      $shape_id = (int)$grow['ShapeID'];
      if( $shape_id > 0 )
      {
         $shape_snapshot = $grow['ShapeSnapshot'];
         $arr_shape = GameSnapshot::parse_check_extended_snapshot($shape_snapshot);
         if( !is_array($arr_shape) ) // overwrite with defaults
            error('invalid_snapshot', "$dbgmsg.check.shape($shape_id,$shape_snapshot)");
         if( ! (bool)@$arr_shape['PlayColorB'] ) // W-first
            $black_first = false;
      }
      $next_tomove_id = ($black_first) ? $new_black_id : $new_white_id;

      // update game

      $upd_game = new UpdateQuery('Games');
      $upd_game->upd_num('ToMove_ID', $next_tomove_id);
      $upd_game->upd_txt('Status', GAME_STATUS_PLAY);
      $upd_game->upd_num('Komi', $this->get_komibid($new_black_id) );

      if( $new_black_id != $this->black_id ) // switch attributes of B/W-player
      {
         $upd_game->upd_num('Black_ID', $new_black_id);
         $upd_game->upd_num('White_ID', $new_white_id);
         $upd_game->upd_num('Black_Start_Rating', $grow['White_Start_Rating']);
         $upd_game->upd_num('White_Start_Rating', $grow['Black_Start_Rating']);

         $upd_game->upd_num('Black_Maintime', $grow['White_Maintime']);
         $upd_game->upd_num('White_Maintime', $grow['Black_Maintime']);
         $upd_game->upd_num('Black_Byotime', $grow['White_Byotime']);
         $upd_game->upd_num('White_Byotime', $grow['Black_Byotime']);
         $upd_game->upd_num('Black_Byoperiods', $grow['White_Byoperiods']);
         $upd_game->upd_num('White_Byoperiods', $grow['Black_Byoperiods']);

         $to_move = ( $new_black_id == $next_tomove_id ) ? BLACK : WHITE;
         $next_to_move = BLACK + WHITE - $to_move;
         list( $hours, $upd_clock ) = GameHelper::update_clock( $grow, $to_move, $next_to_move, /*ticks*/false );
         $upd_game->merge( $upd_clock );
      }

      db_query( "$dbgmsg.update",
         "UPDATE Games SET " . $upd_game->get_query() . " WHERE ID=$gid AND Status='{$this->game_status}' LIMIT 1" );
   }//start_fairkomi_game


   // ------------ static functions ----------------------------

   /*! \brief Returns error-list checking komi-bid for fair-komi; empty on success. */
   function check_komibid( $game_setup, $komibid )
   {
      $jigomode = $game_setup->JigoMode;
      $komibid = trim($komibid);

      $errors = array();
      if( strlen($komibid) == 0 )
         $errors[] = T_('Missing komi-bid#fairkomi');
      elseif( !is_numeric($komibid) )
         $errors[] = T_('Komi-bid must be a numeric value#fairkomi');
      else
      {
         if( floor(2 * $komibid) != 2 * $komibid ) // check for x.0|x.5
            $errors[] = ErrorCode::get_error_text('komi_bad_fraction');

         $is_fractional = floor(2 * abs($komibid)) & 1;
         if( $jigomode == JIGOMODE_ALLOW_JIGO && $is_fractional )
            $errors[] = T_('Jigo is enforced, so komi-bid must not be fractional#fairkomi');
         elseif( $jigomode == JIGOMODE_NO_JIGO && !$is_fractional )
            $errors[] = T_('Jigo is forbidden, so komi-bid must be fractional#fairkomi');

         //TODO must be increasing for open-auction
      }

      return $errors;
   }//check_komibid

} // end 'FairKomiNegotiation'




/**
 * \brief Class to handle game-deletion and game-finishing.
 */
class GameFinalizer
{
   var $action_by; // ACTBY_...
   var $my_id; // for game-notify: <0=cron(timeout), 0=admin
   var $gid;
   var $tid;
   var $Status;
   var $GameType;
   var $GamePlayers;
   var $GameFlags;
   var $Black_id;
   var $White_id;
   var $Moves;
   var $skip_game_query;

   function GameFinalizer( $action_by, $my_id, $gid, $tid, $game_status, $game_type, $game_players,
         $game_flags, $black_id, $white_id, $moves )
   {
      $this->action_by = $action_by;
      $this->my_id = (int)$my_id;
      $this->gid = (int)$gid;
      $this->tid = (int)$tid;
      $this->Status = $game_status;
      $this->GameType = $game_type;
      $this->GamePlayers = $game_players;
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
    * \param $do_delete true=delete-running-game, false=end-running-game
    * \param $game_updquery null=build default query, SQL-Games-update-query
    * \param $game_score score to end game with
    * \param $message message added in game-notify to players
    *
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
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
               "WHERE ID=$gid AND Status".IS_STARTED_GAME." AND Moves={$this->Moves} LIMIT 1";
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
      $game_notify = new GameNotify( $gid, $this->my_id, $this->Status, $this->GameType, $this->GamePlayers,
         $this->GameFlags, $this->Black_ID, $this->White_ID, $game_score, $message );

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
      send_message( "$dbgmsg.msg", $Text, $Subject,
         $game_notify->get_recipients(), '',
         /*notify*/true, /*system-msg*/0, MSGTYPE_RESULT, $gid );
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
   var $game_status;
   var $game_type;
   var $game_players;
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
   function GameNotify( $gid, $uid, $game_status, $game_type, $game_players, $game_flags,
         $black_id, $white_id, $score, $message )
   {
      $this->gid = (int)$gid;
      $this->uid = (int)$uid;
      $this->game_status = $game_status;
      $this->game_type = $game_type;
      $this->game_players = $game_players;
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

   function build_game_ref_array()
   {
      return array(
         'Blackname' => $this->black_name, 'Whitename' => $this->white_name,
         'GameType' => $this->game_type, 'GamePlayers' => $this->game_players,
         'Status' => $this->game_status,
         );
   }

   /*! \brief Returns subject and text for message to players if game got deleted. */
   function get_text_game_deleted( $action_by=ACTBY_PLAYER )
   {
      global $player_row, $MAP_ACTBY_SUBJECT;

      $subject = 'Game deleted';
      if( $action_by == ACTBY_ADMIN || $action_by == ACTBY_CRON )
         $subject .= sprintf(' (by %s)', $MAP_ACTBY_SUBJECT[$action_by]);
      elseif( $action_by != ACTBY_PLAYER )
         $action_by = ACTBY_PLAYER;

      if( $this->game_status == GAME_STATUS_FINISHED )
         $gstatus = 'finished ';
      elseif( $this->game_status == GAME_STATUS_SETUP )
         $gstatus = 'setup-';
      elseif( $this->game_status == GAME_STATUS_KOMI )
         $gstatus = 'started ';
      else
         $gstatus = 'running ';

      $text = "The ".$gstatus."game:<center>"
            // game will be deleted => can't use <game>
            . game_reference( REF_LINK, 1, '', $this->gid, 0, $this->build_game_ref_array() )
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
            . game_reference( REF_LINK, 1, '', $this->gid, 0, $this->build_game_ref_array() )
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



/**
 * \brief Class to handle checks on max-games for users.
 */
class MaxGamesCheck
{
   var $count_games; // number of started/running-games

   function MaxGamesCheck( $urow=null )
   {
      if( is_numeric($urow) )
         $this->count_games = (int)$urow;
      else
      {
         global $player_row;
         if( !is_array($urow) )
            $urow = $player_row;
         $this->count_games = (int)@$urow['Running'] + (int)@$urow['GamesMPG'];
      }
   }

   /*! \brief Returns true, if new game is allowed to start regarding max-games-limit. */
   function allow_game_start()
   {
      return ( MAX_GAMESRUN <= 0 || $this->count_games < MAX_GAMESRUN );
   }

   /*! \brief Returns true, if tourney-registration is allowed (with expected/future new games) regarding max-games-limit. */
   function allow_tournament_registration()
   {
      return ( MAX_GAMESRUN <= 0 || $this->count_games < MaxGamesCheck::_MAX_GAMESRUN_TREG() );
   }

   /*! \brief Returns amount of allowed games to start from given number. */
   function get_allowed_games( $num )
   {
      if( MAX_GAMESRUN <= 0 ) // unlimited
         return $num;
      else
      {
         $cnt = MAX_GAMESRUN - $this->count_games;
         return ($cnt < 0) ? 0 : min($num, $cnt);
      }
   }

   function get_error_text( $with_span=true )
   {
      $msg = sprintf( T_('Sorry, you are not allowed to start more than %s games!'), MAX_GAMESRUN );
      return ($with_span) ? span('ErrMsgMaxGames', $msg) : $msg;
   }

   /*! \brief Returns true, if warning-threshold reached. */
   function need_warning()
   {
      if( MAX_GAMESRUN <= 0 ) // unlimited
         return false;

      $warn_threshold = round(MAX_GAMESRUN * 75/100); // warning-threshold 75% of MAX_GAMESRUN
      if( ALLOW_TOURNAMENTS && MAX_GAMESRUN_TREG > 0 )
         $warn_threshold = min( $warn_threshold, round(MAX_GAMESRUN_TREG * 75/100) ); // 75% for tourney-reg

      return ( $this->count_games >= max(1, $warn_threshold) );
   }

   function get_warn_text()
   {
      if( !$this->need_warning() )
         return '';

      $msg = sprintf( T_('You already started %s of max. %s games.'), $this->count_games, MAX_GAMESRUN );
      if( ALLOW_TOURNAMENTS )
         $msg .= ' ' . sprintf( T_('Tournament registration only allowed for <%s games.'),
            MaxGamesCheck::_MAX_GAMESRUN_TREG() );

      $class = ($this->count_games >= MAX_GAMESRUN) ? 'ErrMsg' : 'WarnMsg';
      return span($class, $msg) . "<br><br>\n";
   }

   function is_limited()
   {
      return (MAX_GAMESRUN > 0);
   }

   // internal
   function _MAX_GAMESRUN_TREG()
   {
      return (MAX_GAMESRUN_TREG > 0) ? MAX_GAMESRUN_TREG : MAX_GAMESRUN;
   }

} // end 'MaxGamesCheck'




/**
 * \brief Class to handle game-setup, stored in Games.GameSetup.
 * \note To support: 1. handle fair-komi negotiation, 2. rematch with same settings, 3. better invitation-handling
 */
class GameSetup
{
   // fields stored in encoded form in Games.GameSetup
   // (data required for rematch and komi-negotiation, though not neccessarily for playing game except Handicap/Komi)
   var $uid;
   var $Handicaptype; // HTYPE_...
   var $Handicap;
   var $AdjustHandicap;
   var $MinHandicap;
   var $MaxHandicap;
   var $Komi; // null=no-komi set yet (used for fair-komi)
   var $AdjustKomi;
   var $JigoMode; // JIGOMODE_...
   var $MustBeRated; // bool
   var $RatingMin;
   var $RatingMax;
   var $MinRatedGames;
   var $SameOpponent;
   var $Message;

   // fields for fair-komi negotiation
   var $OppKomi; // komi-bid of opponent: null=no-komi set yet (used for fair-komi)

   // additional games-fields solely needed to PLAY game (and are therefore already stored in Games-table)
   // - fields that are set once
   var $tid;
   var $ShapeID;
   var $ShapeSnapshot;
   var $GameType; // GAMETYPE_...
   var $GamePlayers;
   // - fields that are changeable
   var $Ruleset; // RULESET_...
   var $Size;
   var $Rated; // bool
   var $StdHandicap; // bool
   var $Maintime;
   var $Byotype; // BYOTYPE_...
   var $Byotime;
   var $Byoperiods;
   var $WeekendClock; // bool

   // additional fields, only used for save-template of new-game views
   var $NumGames;
   var $ViewMode;

   function GameSetup( $uid )
   {
      $this->set_defaults();
      $this->uid = $uid;
   }

   function set_defaults()
   {
      // defaults
      $this->uid = 0;
      $this->Handicaptype = HTYPE_NIGIRI;
      $this->Handicap = 0;
      $this->AdjustHandicap = 0;
      $this->MinHandicap = 0;
      $this->MaxHandicap = MAX_HANDICAP;
      $this->Komi = 0.0;
      $this->AdjustKomi = 0.0;
      $this->JigoMode = JIGOMODE_KEEP_KOMI;
      $this->MustBeRated = true;
      $this->RatingMin = MIN_RATING;
      $this->RatingMax = RATING_9DAN;
      $this->MinRatedGames = 0;
      $this->SameOpponent = 0;
      $this->Message = '';

      // other fields
      $this->OppKomi = null;
      $this->tid = 0;
      $this->ShapeID = 0;
      $this->ShapeSnapshot = '';
      $this->GameType = GAMETYPE_GO;
      $this->GamePlayers = '';

      $this->Ruleset = RULESET_JAPANESE;
      $this->Size = 19;
      $this->Rated = true;
      $this->StdHandicap = true;
      $this->Maintime = time_convert_to_hours( 14, 'days' );
      $this->Byotype = BYOTYPE_FISCHER;
      $this->Byotime = time_convert_to_hours( 1, 'days' );
      $this->Byoperiods = 0;
      $this->WeekendClock = true;

      $this->NumGames = 1;
      $this->ViewMode = NULL;
   }//set_defaults

   function init_opponent_handicaptype( $opp_id )
   {
      $this->uid = $opp_id;
      $this->Handicaptype = GameSetup::swap_htype_black_white( $this->Handicaptype );
      $this->Message = '';
      $this->OppKomi = null;
   }

   function is_fairkomi()
   {
      $cat_htype = get_category_handicaptype($this->Handicaptype);
      return ( $cat_htype == CAT_HTYPE_FAIR_KOMI );
   }

   /*!
    * \brief Encodes GameSetup-object into Games.GameSetup encoding/"compressed" game-setup.
    * \param $is_template true to behave like invitation-encoding + additional new-game-template-encoding
    */
   function encode( $invitation=false, $is_template=false )
   {
      global $MAP_GAME_SETUP;

      if( !isset($MAP_GAME_SETUP[$this->Handicaptype]) )
         error('invalid_args', "GameSetup.encode.htype({$this->uid},{$this->Handicaptype})");
      if( !isset($MAP_GAME_SETUP[$this->JigoMode]) )
         error('invalid_args', "GameSetup.encode.jigomode({$this->uid},{$this->JigoMode})");
      if( $invitation )
      {
         if( !isset($MAP_GAME_SETUP[$this->Ruleset]) )
            error('invalid_args', "GameSetup.encode.ruleset({$this->uid},{$this->Ruleset})");
         if( !isset($MAP_GAME_SETUP[$this->Byotype]) )
            error('invalid_args', "GameSetup.encode.byotype({$this->uid},{$this->Byotype})");
      }

      $out = array();
      $out[] = $MAP_GAME_SETUP[$this->Handicaptype]; // type: T10
      $out[] = 'U'.(int)$this->uid; // user: U123

      // handicap-stuff: H21:-21:10:21
      $out[] = 'H'.(int)$this->Handicap;
      if( $invitation && !$is_template )
         array_push($out, 0, 0, 0 );
      else
         array_push($out,
            (int)$this->AdjustHandicap,
            (int)$this->MinHandicap,
            (int)$this->MaxHandicap );

      // komi-stuff: K-199.5:-199.5:J2:FK[7]
      $out[] = (is_null($this->Komi)) ? 'K' : sprintf('K%.1f', (float)$this->Komi);
      $out[] = ( $invitation && !$is_template ) ? 0 : sprintf('%.1f', (float)$this->AdjustKomi);
      $out[] = $MAP_GAME_SETUP[$this->JigoMode]; // J<x>
      if( $invitation && !$is_template )
         $out[] = 'FK';
      else
         $out[] = (is_null($this->OppKomi)) ? 'FK' : sprintf('FK%.1f', (float)$this->OppKomi);

      // restriction-stuff: R1:-900:2900:999:-103
      if( $invitation && !$is_template )
         array_push($out, 'R0', 0, 0, 0, 0 );
      else
         array_push($out,
            'R' . ( $this->MustBeRated ? 1 : 0 ),
            (int)$this->RatingMin,
            (int)$this->RatingMax,
            (int)$this->MinRatedGames,
            (int)$this->SameOpponent );

      if( $invitation || $is_template )
      {
         $out[] = 'C'; // message (empty)

         $out[] = 'I'.(int)$this->Size; // I=invitation-data
         $out[] = ( $this->Rated ? 1 : 0 );
         $out[] = $MAP_GAME_SETUP[$this->Ruleset]; // ruleset: r1
         $out[] = ( $this->StdHandicap ? 1 : 0 );

         // time-stuff: tJ:60:15:5:1
         $out[] = $MAP_GAME_SETUP[$this->Byotype]; // byoType: tF
         $out[] = (int)$this->Maintime;
         $out[] = (int)$this->Byotime;
         $out[] = (int)$this->Byoperiods;
         $out[] = ( $this->WeekendClock ? 1 : 0 );
      }
      else
         $out[] = 'C'.$this->Message; // message: Cslow game

      return implode(GS_SEP, $out);
   }//encode

   /*! \brief Encodes this GameSetup into GameSetup used for profile-template for invitation and new-game. */
   function encode_profile_template( $prof_type )
   {
      if( $prof_type == PROFTYPE_TMPL_INVITE )
         return $this->encode( /*inv*/true, /*tmpl*/false );
      elseif( $prof_type == PROFTYPE_TMPL_NEWGAME )
         return $this->encode( /*inv*/true, /*tmpl*/true );
      else
         error('invalid_args', "GameSetup.encode_profile_template.check($prof_type)");
   }//encode_profile_template

   /*!
    * \brief Parses remaining game-fields (that are not required for setup a game) into this GameSetup.
    * \param $grow parsed fields:
    *        ShapeID, ShapeSnapshot, GameType, GamePlayers, tid, Ruleset, Size, Rated,
    *        StdHandicap, Maintime, Byotype, Byotime, Byoperiods, WeekendClock
    */
   function read_waitingroom_fields( $grow )
   {
      if( isset($grow['tid']) )
         $this->tid = (int)$grow['tid'];

      if( isset($grow['ShapeID']) )
         $this->ShapeID = (int)$grow['ShapeID'];
      if( isset($grow['ShapeSnapshot']) )
         $this->ShapeSnapshot = $grow['ShapeSnapshot'];
      if( isset($grow['GameType']) )
         $this->GameType = $grow['GameType'];
      if( isset($grow['GamePlayers']) )
         $this->GamePlayers = $grow['GamePlayers'];
      if( isset($grow['Ruleset']) )
         $this->Ruleset = $grow['Ruleset'];
      if( isset($grow['Size']) )
         $this->Size = (int)@$grow['Size'];
      if( isset($grow['Rated']) )
         $this->Rated = ( $grow['Rated'] == 'Y' );
      if( isset($grow['StdHandicap']) )
         $this->StdHandicap = ( $grow['StdHandicap'] == 'Y' );

      if( isset($grow['Maintime']) )
         $this->Maintime = (int)$grow['Maintime'];
      if( isset($grow['Byotype']) )
         $this->Byotype = $grow['Byotype'];
      if( isset($grow['Byotime']) )
         $this->Byotime = (int)$grow['Byotime'];
      if( isset($grow['Byoperiods']) )
         $this->Byoperiods = (int)$grow['Byoperiods'];
      if( isset($grow['WeekendClock']) )
         $this->WeekendClock = ( $grow['WeekendClock'] == 'Y' );
   }//read_waitingroom_fields

   function to_string( $invitation=false )
   {
      $result = "GameSetup: U={$this->uid} T={$this->Handicaptype} " .
         "H={$this->Handicap}/{$this->AdjustHandicap}/{$this->MinHandicap}-{$this->MaxHandicap} " .
         "K={$this->Komi}/{$this->AdjustKomi} J={$this->JigoMode} FK={$this->OppKomi} " .
         "MBR=" . yesno($this->MustBeRated) . " Rating={$this->RatingMin}..{$this->RatingMax} " .
         "MRG={$this->MinRatedGames} SO={$this->SameOpponent} M=[{$this->Message}]";
      if( $invitation )
         $result .= "; S={$this->Size} Rules={$this->Ruleset} Rated=" . yesno($this->Rated) . " StdH=" . yesno($this->StdHandicap) .
            " Time={$this->Byotype}:{$this->Maintime}/{$this->Byotime}/{$this->Byoperiods}:" . yesno($this->WeekendClock) .
            " tid={$this->tid} shape={$this->ShapeID}/[{$this->ShapeSnapshot}] " .
            "gtype={$this->GameType}/{$this->GamePlayers}";
      $result .= "; #G={$this->NumGames} view={$this->ViewMode}";
      return $result;
   }//to_string

   function format_handicap_type( $handicaptype=null )
   {
      if( is_null($handicaptype) )
         $handicaptype = $this->Handicaptype;

      $cat_htype = get_category_handicaptype($handicaptype);
      switch( (string)$cat_htype )
      {
         case CAT_HTYPE_CONV:
            return T_('Conventional handicap');

         case CAT_HTYPE_PROPER:
            return T_('Proper handicap');

         case CAT_HTYPE_FAIR_KOMI:
            return sprintf( T_('Fair Komi of Type [%s], Jigo-Check [%s]'),
                            GameTexts::get_fair_komi_types($handicaptype),
                            GameTexts::get_jigo_modes(/*fk*/true, $this->JigoMode) );

         case CAT_HTYPE_MANUAL:
         default:
            return sprintf( T_('Manual setting with My Color [%s], Handicap %s, Komi %s'),
                            GameTexts::get_manual_handicap_types($handicaptype), $this->Handicap, $this->Komi );
      }
   }//format_handicap_type

   function format_time()
   {
      return TimeFormat::echo_time_limit( $this->Maintime, $this->Byotype, $this->Byotime, $this->Byoperiods,
         TIMEFMT_SHORT|TIMEFMT_ADDTYPE);
   }//format_time

   function format_std_handicap()
   {
      return ($this->StdHandicap) ? T_('Standard-Handicap#inv_diff') : T_('Free-Handicap#inv_diff');
   }//format_std_handicap


   // ------------ static functions ----------------------------

   /*! \brief Decodes (=parse) Games.GameSetup value with game-setup into GameSetup-object. */
   function new_from_game_setup( $game_setup, $invitation=false, $null_on_empty=false )
   {
      global $MAP_GAME_SETUP;
      static $RX_KOMI = "-?\\d+(\\.[05])?";
      static $RX_KOMI2 = "(-?\\d+(\\.[05])?)?"; // can be empty

      $gs = new GameSetup( 0 );
      $game_setup = trim($game_setup);
      if( (string)$game_setup == '' )
         return ($null_on_empty) ? null : $gs;

      // Standard:
      //   "T<htype>:U<uid>:H<handicap>:<adjH>:<minH>:<maxH>:K<komi>:<adjK>:J<jigomode>:R<mustBeRated>:<ratingMin>:<ratingMax>:<minRG>:<sameOpp>:C<msg>"
      // Invitation (standard-part shows example):
      //   "T1:U2:H0:0:0:0:K6.5:0:J2:R0:0:0:0:0:C:I<size>:<rated>:r<ruleset>:<stdH>:t<byoType>:<mainT>:<byoTime>:<byoPeriods>:<wkclock>"
      // NOTE: when adding new game-settings -> adjust regex also matching "old" syntaxes
      $rx_inv = ($invitation) ? ":I\\d+:[01]:r\\d+:[01]:t[JCF]:\\d+:\\d+:\\d+:[01]\$" : '';
      $rx_gs = "/^T\\d+:U\\d+:H\\d+:-?\\d+:\\d+:\\d+:K$RX_KOMI2:$RX_KOMI:J[012]:FK$RX_KOMI2:R[01]:-?\\d+:-?\\d+:\\d+:-?\\d+:C$rx_inv/";
      if( !preg_match($rx_gs, $game_setup) )
         error('invalid_args', "GameSetup::new_from_game_setup.check_gs($invitation,$game_setup)");
      $arr = explode(GS_SEP, $game_setup);
      //error_log("new_from_game_setup($invitation,$game_setup): [". implode('] [', $arr)."]"); //TEST

      $gs->Handicaptype = $MAP_GAME_SETUP[ array_shift($arr) ];
      $gs->uid = (int)substr( array_shift($arr), 1);

      $gs->Handicap = (int)substr( array_shift($arr), 1);
      $gs->AdjustHandicap = (int)array_shift($arr);
      $gs->MinHandicap = (int)array_shift($arr);
      $gs->MaxHandicap = (int)array_shift($arr);

      $komi = substr( array_shift($arr), 1);
      $gs->Komi = ( strlen($komi) > 0 ) ? (float)$komi : null;
      $gs->AdjustKomi = (float)array_shift($arr);
      $gs->JigoMode = $MAP_GAME_SETUP[ array_shift($arr) ];
      $komi = substr( array_shift($arr), 2);
      $gs->OppKomi = ( strlen($komi) > 0 ) ? (float)$komi : null;

      $gs->MustBeRated = ( (int)substr( array_shift($arr), 1) != 0 );
      $gs->RatingMin = (int)array_shift($arr);
      $gs->RatingMax = (int)array_shift($arr);
      $gs->MinRatedGames = (int)array_shift($arr);
      $gs->SameOpponent = (int)array_shift($arr);

      if( $invitation )
      {
         $gs->Message = substr( array_shift($arr), 1);

         $gs->Size = (int)substr( array_shift($arr), 1);
         $gs->Rated = ( (int)array_shift($arr) != 0 );
         $gs->Ruleset = $MAP_GAME_SETUP[ array_shift($arr) ];
         $gs->StdHandicap = ( (int)array_shift($arr) != 0 );

         $gs->Byotype = $MAP_GAME_SETUP[ array_shift($arr) ];
         $gs->Maintime = (int)array_shift($arr);
         $gs->Byotime = (int)array_shift($arr);
         $gs->Byoperiods = (int)array_shift($arr);
         $gs->WeekendClock = ( (int)array_shift($arr) != 0 );
      }
      else
         $gs->Message = substr( implode(GS_SEP, $arr), 1 );

      //error_log("new_from_game_setup.2: ".$gs->to_string(true)); //TEST
      return $gs;
   }//new_from_game_setup

   /*! \brief Encodes invitation GameSetup-objects into Games.GameSetup for invitations and disputes. */
   function build_invitation_game_setup( $game_setup1, $game_setup2 )
   {
      $out = array();
      if( !is_null($game_setup1) )
         $out[] = $game_setup1->encode( /*inv*/true );
      if( !is_null($game_setup2) )
         $out[] = $game_setup2->encode( /*inv*/true );
      return implode(GS_SEP_INVITATION, $out );
   }//build_invitation_game_setup

   /*!
    * \brief Decodes Games.GameSetup of invitation-type with additional data to make diffs on disputes.
    * \param $gid only for dbg-info, can be Games.ID
    * \return non-null array [ GameSetup-obj for pivot-uid, GameSetup-obj for opponent ],
    *         elemements may be null (if non-matching game-setup found)
    */
   function parse_invitation_game_setup( $pivot_uid, $game_setup, $gid=0 )
   {
      $arr_input = explode(GS_SEP_INVITATION, trim($game_setup)); // note: arr( Str ) if gs==empty
      $cnt_input = count($arr_input);
      if( $cnt_input > 2 )
         error('invalid_args', "GameSetup::parse_invitation_game_setup.check.gs($pivot_uid,$cnt_input,$game_setup)");

      $result = array();
      foreach( $arr_input as $gs_part )
      {
         if( (string)$gs_part != '' )
            $result[] = GameSetup::new_from_game_setup( $gs_part, /*inv*/true );
      }

      $cnt_gs = count($result);
      if( $cnt_gs == 0 )
         array_push( $result, null, null );
      elseif( $cnt_gs == 1 )
      {
         if( $result[0]->uid == $pivot_uid )
            $result[] = null;
         else
            array_unshift( $result, null );
      }
      elseif( $cnt_gs == 2 )
      {
         if( $result[0]->uid != $pivot_uid && $result[1]->uid != $pivot_uid )
            error('internal_error', "GameSetup::parse_inv_gs.check.uid($pivot_uid,$gid)");
         if( $result[1]->uid == $pivot_uid && $result[0]->uid != $pivot_uid )
            swap( $result[0], $result[1] );
      }

      return $result;
   }//parse_invitation_game_setup

   function parse_jigo_mode_from_game_setup( $cat_htype, $pivot_uid, $game_setup, $gid )
   {
      $jigo_mode = JIGOMODE_KEEP_KOMI; //default

      if( $cat_htype == CAT_HTYPE_FAIR_KOMI )
      {
         list( $my_gs, $opp_gs ) = GameSetup::parse_invitation_game_setup( $pivot_uid, $game_setup, $gid );
         if( !is_null($my_gs) )
            $jigo_mode = $my_gs->JigoMode;
      }

      return $jigo_mode;
   }//parse_jigo_mode_from_game_setup

   /*!
    * \brief Creates GameSetup parsing fields from games-row (for waiting-room).
    * \param $grow array with keys: uid, Handicaptype, Handicap, Adj/Min/MaxHandicap, Komi, AdjKomi, JigoMode,
    *        MustBeRated, RatingMin/Max, MinRatedGames, SameOpponent, Message|Comment
    */
   function new_from_game_row( $grow )
   {
      $gs = new GameSetup( (int)@$grow['uid'] );
      $gs->Handicaptype = $grow['Handicaptype'];
      $gs->Handicap = (int)$grow['Handicap'];
      $gs->AdjustHandicap = (int)$grow['AdjHandicap'];
      $gs->MinHandicap = (int)$grow['MinHandicap'];
      $gs->MaxHandicap = (int)$grow['MaxHandicap'];
      $gs->Komi = (float)$grow['Komi'];
      $gs->AdjustKomi = (float)$grow['AdjKomi'];
      $gs->JigoMode = $grow['JigoMode'];
      $gs->MustBeRated = ( $grow['MustBeRated'] == 'Y' );
      $gs->RatingMin = (int)$grow['RatingMin'];
      $gs->RatingMax = (int)$grow['RatingMax'];
      $gs->MinRatedGames = (int)$grow['MinRatedGames'];
      $gs->SameOpponent = (int)$grow['SameOpponent'];
      if( isset($grow['Message']) )
         $gs->Message = $grow['Message'];
      elseif( isset($grow['Comment']) )
         $gs->Message = $grow['Comment'];
      return $gs;
   }//new_from_game_row

   /*!
    * \briefs Builds diffs between two GameSetup-objects regarding invitation-game-settings.
    * \return array with diff-arrays: [ setting-title, old-setting, new-setting [, long-diff=0|1]  ]
    */
   function build_invitation_diffs( $gs_old, $gs_new )
   {
      $out = array();

      if( $gs_old->Ruleset !== $gs_new->Ruleset )
         $out[] = array( T_('Ruleset'), getRulesetText($gs_old->Ruleset), getRulesetText($gs_new->Ruleset) );
      if( $gs_old->Size !== $gs_new->Size )
         $out[] = array( T_('Board size'), $gs_old->Size, $gs_new->Size );

      // handicap-type
      $htype_new = GameSetup::swap_htype_black_white($gs_new->Handicaptype);
      $htype_old_text = $gs_old->format_handicap_type();
      $htype_new_text = $gs_new->format_handicap_type($htype_new);
      if( $htype_old_text !== $htype_new_text )
         $out[] = array( T_('Handicap-Type#inv_diff'), $htype_old_text, $htype_new_text, 1 );

      if( $gs_old->StdHandicap !== $gs_new->StdHandicap )
         $out[] = array( T_('Handicap stones placement#inv_diff'), $gs_old->format_std_handicap(), $gs_new->format_std_handicap() );

      // time
      $old_time = $gs_old->format_time();
      $new_time = $gs_new->format_time();
      if( $old_time !== $new_time )
         $out[] = array( T_('Time#inv_diff'), $old_time, $new_time );

      if( $gs_old->WeekendClock !== $gs_new->WeekendClock )
         $out[] = array( T_('Clock runs on weekends'), yesno($gs_old->WeekendClock), yesno($gs_new->WeekendClock) );
      if( $gs_old->Rated !== $gs_new->Rated )
         $out[] = array( T_('Rated game'), yesno($gs_old->Rated), yesno($gs_new->Rated) );

      return $out;
   }//build_invitation_diffs

   function create_opponent_game_setup( $game_setup, $opp_id )
   {
      $opp_gs = clone $game_setup;
      $opp_gs->init_opponent_handicaptype( $opp_id );
      return $opp_gs;
   }//create_opponent_game_setup

   function swap_htype_black_white( $handicaptype )
   {
      if( $handicaptype == HTYPE_BLACK )
         return HTYPE_WHITE;
      elseif( $handicaptype == HTYPE_WHITE )
         return HTYPE_BLACK;
      else
         return $handicaptype;
   }//swap_htype_black_white

} //end 'GameSetup'



define('GSC_VIEW_INVITE', -1); // invite/dispute

/**
 * \brief Class to check values of input-fields for invitation/dispute and new-game.
 */
class GameSetupChecker
{
   var $view; //GSETVIEW_... for new-game, GSC_VIEW_INVITE for invite/dispute
   var $errors;

   function GameSetupChecker( $view )
   {
      $this->view = (int)$view;
      $this->errors = array();
   }

   function has_errors()
   {
      return count($this->errors);
   }

   function get_errors()
   {
      return $this->errors;
   }

   function add_error( $error )
   {
      $this->errors[] = $error;
   }

   function add_default_values_info()
   {
      $this->errors[] = T_('Invalid values have been replaced with default-values!');
   }

   function check_komi()
   {
      // komi-check only for: invite, simple/expert new-game
      if( $this->view != GSC_VIEW_INVITE && $this->view != GSETVIEW_SIMPLE && $this->view != GSETVIEW_EXPERT )
         return;

      $komi = trim(@$_REQUEST['komi_m']);
      if( (string)$komi == '' || !is_numeric($komi) )
         $this->errors[] = sprintf( T_('Invalid value for komi [%s].'), $komi );

      $komi = (float)$komi;

      if( abs($komi) > MAX_KOMI_RANGE )
         $this->errors[] = ErrorCode::get_error_text('komi_range');
      if( floor(2 * $komi) != 2 * $komi ) // check for x.0|x.5
         $this->errors[] = ErrorCode::get_error_text('komi_bad_fraction');
   }//check_komi

   function check_time()
   {
      static $check_fields = array( 'timevalue', // maintime
            'byotimevalue_jap', 'byoperiods_jap', // JAP
            'byotimevalue_can', 'byoperiods_can', // CAN
            'byotimevalue_fis', // FIS
         );
      static $check_fields_simple = array( 'timevalue', // maintime
            'byotimevalue_fis', // FIS
         );

      $arr_check_fields = ( $this->view == GSETVIEW_SIMPLE ) ? $check_fields_simple : $check_fields;
      $ferrors = array();
      foreach( $arr_check_fields as $field )
      {
         $val = trim(@$_REQUEST[$field]);
         if( (string)$val == '' || !is_numeric($val) || (int)$val != $val || $val < 0 )
            $ferrors[] = $val;
      }
      if( count($ferrors) )
         $this->errors[] = sprintf(T_('Invalid time values [%s], must be integer and >= 0.'), implode('][', $ferrors));

      $byoyomitype = @$_REQUEST['byoyomitype'];
      $timevalue = (int)@$_REQUEST['timevalue'];
      $timeunit = @$_REQUEST['timeunit'];

      $byotimevalue_jap = (int)@$_REQUEST['byotimevalue_jap'];
      $timeunit_jap = @$_REQUEST['timeunit_jap'];
      $byoperiods_jap = (int)@$_REQUEST['byoperiods_jap'];

      $byotimevalue_can = (int)@$_REQUEST['byotimevalue_can'];
      $timeunit_can = @$_REQUEST['timeunit_can'];
      $byoperiods_can = (int)@$_REQUEST['byoperiods_can'];

      $byotimevalue_fis = (int)@$_REQUEST['byotimevalue_fis'];
      $timeunit_fis = @$_REQUEST['timeunit_fis'];

      list($hours, $byohours, $byoperiods) =
         interpret_time_limit_forms($byoyomitype, $timevalue, $timeunit,
                                    $byotimevalue_jap, $timeunit_jap, $byoperiods_jap,
                                    $byotimevalue_can, $timeunit_can, $byoperiods_can,
                                    $byotimevalue_fis, $timeunit_fis);

      if( $hours < 1 && ($byohours < 1 || $byoyomitype == BYOTYPE_FISCHER) )
         $this->errors[] = ErrorCode::get_error_text('time_limit_too_small');
   }//check_time

   function check_adjust_komi()
   {
      // adj-komi-check only for: expert new-game
      if( $this->view != GSETVIEW_EXPERT )
         return;

      $adj_komi = @$_REQUEST['adj_komi'];
      if( (string)$adj_komi == '' || !is_numeric($adj_komi) )
         $this->errors[] = sprintf( T_('Invalid value for komi-adjustment [%s].'), $adj_komi );

      if( abs($adj_komi) > MAX_KOMI_RANGE )
         $this->errors[] = T_('Adjust komi#errchk') . ': ' . ErrorCode::get_error_text('komi_range');
      if( floor(2 * $adj_komi) != 2 * $adj_komi ) // check for x.0|x.5
         $this->errors[] = T_('Adjust komi#errchk') . ': ' . ErrorCode::get_error_text('komi_bad_fraction');
   }//check_adjust_komi

   function check_min_rated_games()
   {
      // min-rated-games only for: expert/fair-komi new-game
      if( $this->view != GSETVIEW_EXPERT && $this->view != GSETVIEW_FAIRKOMI )
         return;

      $min_rgames = @$_REQUEST['min_rated_games'];
      if( (string)$min_rgames == '' || !is_numeric($min_rgames) || (int)$min_rgames != $min_rgames || $min_rgames < 0 )
         $this->errors[] = sprintf( T_('Invalid value for min. rated games [%s].'), $min_rated_games );

      $min_rgames = (int)$min_rgames;
      if( $min_rgames > 999 )
         $this->errors[] = sprintf( T_('Value for min. rated games is out of range.'), $min_rgames );
   }//check_min_rated_games

   function check_game_players()
   {
      // game-players only for: MPG new-game
      if( $this->view != GSETVIEW_MPGAME )
         return;

      $game_players = @$_REQUEST['game_players'];
      $game_type = MultiPlayerGame::determine_game_type($game_players);
      if( is_null($game_type) )
         $this->errors[] = sprintf( T_('Invalid value for game-players [%s].'), $game_players );
   }//check_game_players


   // ------------ static functions ----------------------------

   function check_fields( $view )
   {
      $gsc = new GameSetupChecker( $view );
      $gsc->check_komi();
      $gsc->check_time();
      $gsc->check_adjust_komi();
      $gsc->check_min_rated_games();
      $gsc->check_game_players();
      return $gsc;
   }//check_fields

} //end 'GameSetupChecker'



/**
 * \brief Class to pre-seed form-fields for invites and game-setup for rematch and templates.
 */
class GameSetupBuilder
{
   var $my_id;
   var $game_setup;
   var $game;
   var $is_mpg;
   var $is_template;

   /*!
    * \brief Constructs GameSetupBuilder.
    * \param $game Games-object, null, or GameSetup-object (as it has the same field-interface) for fields:
    *           GamePlayers, ShapeID, ShapeSnapshot, Ruleset, Size, StdHandicap, Handicap, Komi,
    *           Maintime, Byotype, Byotime, Byoperiods, WeekendClock;
    *        The following fields do not have common semantics, therefore are treated differently using $is_template:
    *           DoubleGame_ID, Black_ID, White_ID, Rated
    */
   function GameSetupBuilder( $my_id=0, $game_setup=null, $game=null, $is_mpg=false, $is_template=false )
   {
      $this->my_id = $my_id;
      $this->game_setup = $game_setup;
      $this->game = $game;
      $this->is_mpg = $is_mpg;
      $this->is_template = $is_template;
   }

   function fill_invite_from_game( &$url )
   {
      $this->build_url_invite_to( $url );
      $this->build_url_game_basics( $url );
      $this->build_url_cat_htype_manual( $url, CAT_HTYPE_MANUAL, null );
      $this->build_url_handi_komi_rated( $url );
   }//fill_invite_from_game

   function fill_invite_from_game_setup( &$url )
   {
      $cat_htype = get_category_handicaptype( $this->game_setup->Handicaptype );

      $this->build_url_invite_to( $url );
      $this->build_url_game_basics( $url );
      $this->build_url_cat_htype_manual( $url, $cat_htype, $this->game_setup->Handicaptype );
      $this->build_url_handi_komi_rated( $url );

      $url['fk_htype'] = ($cat_htype === CAT_HTYPE_FAIR_KOMI) ? $this->game_setup->Handicaptype : HTYPE_AUCTION_SECRET;
      $url['jigo_mode'] = $this->game_setup->JigoMode;

      if( !$this->is_template )
         $url['message'] = $this->game_setup->Message;
   }//fill_invite_from_game_setup


   function fill_new_game_from_game( &$url )
   {
      $url['view'] = ( $this->is_mpg ) ? GSETVIEW_MPGAME : GSETVIEW_EXPERT;

      $this->build_url_game_basics( $url );
      $url['game_players'] = $this->game->GamePlayers;

      if( !$this->is_mpg )
      {
         $this->build_url_cat_htype_manual( $url, CAT_HTYPE_MANUAL, null );
         $this->build_url_handi_komi_rated( $url );
      }
   }//fill_new_game_from_game

   function fill_new_game_from_game_setup( &$url )
   {
      $cat_htype = get_category_handicaptype( $this->game_setup->Handicaptype );

      if( $this->is_template )
      {
         $url['nrGames'] = $this->game_setup->NumGames;
         $url['view'] = $this->game_setup->ViewMode;
      }
      else
         $url['view'] = ($cat_htype === CAT_HTYPE_FAIR_KOMI) ? GSETVIEW_FAIRKOMI : GSETVIEW_EXPERT;

      $this->build_url_game_basics( $url );
      $url['game_players'] = $this->game->GamePlayers;

      $this->build_url_cat_htype_manual( $url, $cat_htype, $this->game_setup->Handicaptype );
      $this->build_url_handi_komi_rated( $url );

      $url['fk_htype'] = ($cat_htype === CAT_HTYPE_FAIR_KOMI) ? $this->game_setup->Handicaptype : HTYPE_AUCTION_SECRET;
      $url['adj_komi'] = $this->game_setup->AdjustKomi;
      $url['jigo_mode'] = $this->game_setup->JigoMode;
      $url['adj_handicap'] = $this->game_setup->AdjustHandicap;
      $url['min_handicap'] = $this->game_setup->MinHandicap;
      $url['max_handicap'] = $this->game_setup->MaxHandicap;

      $url['mb_rated'] = bool_YN( $this->game_setup->MustBeRated );
      if( $this->game_setup->RatingMin < OUT_OF_RATING )
         $url['rat1'] = $this->game_setup->RatingMin;
      if( $this->game_setup->RatingMax < OUT_OF_RATING )
         $url['rat2'] = $this->game_setup->RatingMax;
      $url['min_rg'] = $this->game_setup->MinRatedGames;
      $url['same_opp'] = $this->game_setup->SameOpponent;
      $url['comment'] = $this->game_setup->Message;
   }//fill_new_game_from_game_setup


   function build_url_game_basics( &$url )
   {
      if( $this->game->ShapeID > 0 )
      {
         $url['shape'] = $this->game->ShapeID;
         $url['snapshot'] = $this->game->ShapeSnapshot;
      }

      $url['ruleset'] = $this->game->Ruleset;
      $url['size'] = $this->game->Size;
      $url['stdhandicap'] = bool_YN( $this->game->StdHandicap );

      $this->build_url_time( $url );
   }//build_url_game_basics

   function build_url_time( &$url )
   {
      $MaintimeUnit = 'hours';
      $Maintime = $this->game->Maintime;
      time_convert_to_longer_unit($Maintime, $MaintimeUnit);
      $url['timeunit'] = $MaintimeUnit;
      $url['timevalue'] = $Maintime;

      $url['byoyomitype'] = $this->game->Byotype;
      $ByotimeUnit = 'hours';
      $Byotime = $this->game->Byotime;
      time_convert_to_longer_unit($Byotime, $ByotimeUnit);
      $url['byotimevalue_jap'] = $url['byotimevalue_can'] = $url['byotimevalue_fis'] = $Byotime;
      $url['timeunit_jap'] = $url['timeunit_can'] = $url['timeunit_fis'] = $ByotimeUnit;

      if( $this->game->Byoperiods > 0 )
         $url['byoperiods_jap'] = $url['byoperiods_can'] = $this->game->Byoperiods;

      $url['weekendclock'] = bool_YN( $this->game->WeekendClock );
   }//build_url_time

   function build_url_cat_htype_manual( &$url, $cat_htype, $gs_htype )
   {
      $url['cat_htype'] = $cat_htype;
      if( !is_null($gs_htype) && $cat_htype === CAT_HTYPE_MANUAL )
         $url['color_m'] = $gs_htype;
      elseif( !$this->is_template && $this->game->DoubleGame_ID != 0 )
         $url['color_m'] = HTYPE_DOUBLE;
      elseif( !$this->is_template && $this->my_id == $this->game->Black_ID )
         $url['color_m'] = HTYPE_BLACK;
      elseif( !$this->is_template && $this->my_id == $this->game->White_ID )
         $url['color_m'] = HTYPE_WHITE;
      else
         $url['color_m'] = HTYPE_NIGIRI; // default
   }//build_url_cat_htype_manual

   function build_url_handi_komi_rated( &$url )
   {
      if( is_null($this->game_setup) )
      {
         $url['handicap_m'] = $this->game->Handicap;
         $url['komi_m'] = $this->game->Komi;
      }
      else
      {
         $url['handicap_m'] = $this->game_setup->Handicap;
         $url['komi_m'] = $this->game_setup->Komi;
      }

      if( $this->is_template )
         $url['rated'] = bool_YN( $this->game->Rated );
      else
         $url['rated'] = ( $this->game->Rated == 'N' ) ? 'N' : 'Y';
   }//build_url_handi_komi_rated

   function build_url_invite_to( &$url )
   {
      if( $this->is_template ) // skip for template
         return;

      if( $this->my_id == $this->game->Black_ID )
         $opp_id = $this->game->White_ID;
      elseif( $this->my_id == $this->game->White_ID )
         $opp_id = $this->game->Black_ID;
      else
         $opp_id = 0;

      $opp_to = '';
      if( $opp_id > 0 )
      {
         $users = User::load_quick_userinfo( array( $opp_id ) );
         if( isset($users[$opp_id]) )
            $opp_to = $users[$opp_id]['Handle'];
      }
      $url['to'] = $opp_to;
   }//build_url_invite_to

} //end 'GameSetupBuilder'



define('MAX_PROFILE_TEMPLATES', 30);
define('MAX_PROFILE_TEMPLATES_DATA', 10000); // max byte-len for template

/**
 * \brief Class to handle templates for send-message and game-setup (for invitation and new-game)
 *        stored in Profiles-table.
 */
class ProfileTemplate
{
   var $TemplateType; //PROFTYPE_TMPL_...

   var $GameSetup;
   var $Subject;
   var $Text;

   /*!
    * \brief Constructs template with template-type.
    * \param $template_type one of PROFTYPE_TMPL_...
    */
   function ProfileTemplate( $template_type )
   {
      if( !ProfileTemplate::is_valid_type($template_type) )
         error('invalid_args', "ProfileTemplate.new($template_type)");
      $this->TemplateType = (int)$template_type;
   }

   /*! \brief Encodes template into blob-value stored in Profiles-table. */
   function encode()
   {
      if( $this->TemplateType == PROFTYPE_TMPL_SENDMSG )
         $result = "{$this->Subject}\n{$this->Text}";
      elseif( $this->TemplateType == PROFTYPE_TMPL_INVITE )
      {
         $extra = sprintf('SH%s:%s', $this->GameSetup->ShapeID, $this->GameSetup->ShapeSnapshot );
         $result = $this->GameSetup->encode_profile_template( $this->TemplateType )
            . "\n$extra\n{$this->Subject}\n{$this->Text}";
      }
      elseif( $this->TemplateType == PROFTYPE_TMPL_NEWGAME )
      {
         $extra = sprintf('V%s G%s GP%s SH%s:%s',
            $this->GameSetup->ViewMode, $this->GameSetup->NumGames, $this->GameSetup->GamePlayers,
            $this->GameSetup->ShapeID, $this->GameSetup->ShapeSnapshot );
         $result = $this->GameSetup->encode_profile_template( $this->TemplateType )
            . "\n$extra\n{$this->Subject}\n{$this->Text}";
      }
      else
         error('invalid_args', "ProfileTemplate.encode.check.type({$this->TemplateType})");

      return trim($result);
   }//encode

   function build_profile()
   {
      global $player_row;

      $profile = ProfileTemplate::new_default_profile( $player_row['ID'], $this->TemplateType );
      $profile->set_text( $this->encode() );
      return $profile;
   }

   function fill( &$url, $use_type=null )
   {
      if( is_null($use_type) )
         $use_type = $this->TemplateType;

      if( $use_type == PROFTYPE_TMPL_SENDMSG )
         $this->fill_message( $url );
      elseif( $use_type == PROFTYPE_TMPL_INVITE )
      {
         $gs_builder = new GameSetupBuilder( 0, $this->GameSetup, /*game*/$this->GameSetup, /*mpg*/false, /*tmpl*/true );
         $gs_builder->fill_invite_from_game_setup( $url );
         $this->fill_message( $url );
      }
      elseif( $use_type == PROFTYPE_TMPL_NEWGAME )
      {
         $gs_builder = new GameSetupBuilder( 0, $this->GameSetup, /*game*/$this->GameSetup, /*mpg*/false, /*tmpl*/true );
         $gs_builder->fill_new_game_from_game_setup( $url );
         $url['comment'] = $this->Subject;
      }
      else
         error('invalid_args', "ProfileTemplate.fill({$this->TemplateType},$use_type)");
   }//fill

   /*! \brief Fills new-game form-values with invite-template-type data. */
   function fill_new_game_with_invite( &$url, $use_type )
   {
      if( $use_type == PROFTYPE_TMPL_NEWGAME && $this->TemplateType == PROFTYPE_TMPL_INVITE )
      {
         list( $line, $tmp ) = ProfileTemplate::eat_line( $this->Text ); // take 1st line
         $url['comment'] = ( strlen($line) > 40 ) ? substr($line,0,40) : $line;
      }
   }

   function fill_invite_with_new_game( &$url, $use_type )
   {
      if( $use_type == PROFTYPE_TMPL_INVITE && $this->TemplateType == PROFTYPE_TMPL_NEWGAME )
      {
         $url['message'] = $this->Subject;
      }
   }

   function fill_message( &$url )
   {
      $url['subject'] = $this->Subject;
      $url['message'] = $this->Text;
   }

   /*!
    * \brief Returns true if current decoded new-game-template can be used as template for invite.
    * \see is_valid_template_raw_check()
    */
   function is_valid_new_game_template_for_invite()
   {
      if( $this->TemplateType == PROFTYPE_TMPL_NEWGAME && !is_null($this->GameSetup) )
      {
         if( $this->GameSetup->ViewMode == GSETVIEW_MPGAME )
            return false;
      }
      return true;
   }

   function to_string()
   {
      $gs_str = (is_null($this->GameSetup)) ? '-' : $this->GameSetup->to_string(/*inv*/true);
      return "ProfileTemplate({$this->TemplateType}): GameSetup=[$gs_str] Subject=[{$this->Subject}] Text=[$this->Text]";
   }


   // ------------ static functions ----------------------------

   function new_template_send_message( $subject, $text )
   {
      $tmpl = new ProfileTemplate( PROFTYPE_TMPL_SENDMSG );
      $tmpl->Subject = trim($subject);
      $tmpl->Text = trim($text);
      return $tmpl;
   }

   function new_template_game_setup_invite( $subject, $text )
   {
      $tmpl = new ProfileTemplate( PROFTYPE_TMPL_INVITE );
      $tmpl->Subject = trim($subject);
      $tmpl->Text = trim($text);
      return $tmpl;
   }

   function new_template_game_setup_newgame( $comment )
   {
      $tmpl = new ProfileTemplate( PROFTYPE_TMPL_NEWGAME );
      $tmpl->Subject = trim($comment);
      $tmpl->Text = '';
      return $tmpl;
   }

   /*!
    * \brief Returns false, if value from Profile.Text of given template-type can be used for $use_type.
    * \see #is_valid_new_game_template_for_invite()
    */
   function is_valid_template_raw_check( $template_type, $use_type, $value )
   {
      if( $template_type == PROFTYPE_TMPL_NEWGAME && $use_type == PROFTYPE_TMPL_INVITE )
      {
         // MPG not supported for invite
         if( preg_match("/^[^\\n]+\\nV".GSETVIEW_MPGAME."\\b/", $value) )
            return false;
      }
      return true;
   }//is_valid_template_raw_check

   /*! \brief Parses profile-raw-value into game-setup and other fields dependent on template-type. */
   function decode( $template_type, $value )
   {
      $tmpl = new ProfileTemplate( $template_type );

      if( $template_type == PROFTYPE_TMPL_SENDMSG )
      {
         list( $tmpl->Subject, $tmpl->Text ) = ProfileTemplate::eat_line( $value );
      }
      elseif( $template_type == PROFTYPE_TMPL_INVITE || $template_type == PROFTYPE_TMPL_NEWGAME )
      {
         list( $gs_line, $rem1 ) = ProfileTemplate::eat_line( $value );
         list( $extra_line, $rem2 ) = ProfileTemplate::eat_line( $rem1 );
         list( $tmpl->Subject, $tmpl->Text ) = ProfileTemplate::eat_line( $rem2 );

         $tmpl->GameSetup = GameSetup::new_from_game_setup( $gs_line, /*inv*/true, /*0OnEmpty*/false );

         // parse extra-format for types -> see 'specs/db/table-Profiles.txt'
         $pline = $extra_line;
         while( (string)$pline != '' )
         {
            if( preg_match("/^SH\\d+:.*$/", $pline) ) // new-game + invite
            {
               list( $shape_id, $shape_snapshot ) = ProfileTemplate::eat_line( $pline, ':' );
               $shape_id = (int)substr($shape_id, 2);
               if( $shape_id <= 0 )
               {
                  $shape_id = 0;
                  $shape_snapshot = '';
               }
               $tmpl->GameSetup->ShapeID = $shape_id;
               $tmpl->GameSetup->ShapeSnapshot = $shape_snapshot;

               $pline = '';
               break; // shape must be last group
            }

            $input = $pline; // note: must use copied value for list(..) =
            list( $val, $pline ) = ProfileTemplate::eat_line( $input, ' ' ); // eat next group

            if( preg_match("/^V\\d+$/", $val) ) // new-game: viewmode
               $tmpl->GameSetup->ViewMode = (int)substr($val, 1);
            elseif( preg_match("/^G\\d+$/", $val) ) // new-game: num-games
               $tmpl->GameSetup->NumGames = (int)substr($val, 1);
            elseif( preg_match("/^GP(\\d+|\\d+:\\d+)?$/", $val) ) // new-game: game-players
               $tmpl->GameSetup->GamePlayers = trim( substr($val, 2) );
            else
               error('invalid_args', "ProfileTemplate.decode.parse.extra($template_type,[$val],[$pline],[$extra_line])");
         }//while
      }//invite/new-game

      return $tmpl;
   }//decode

   /*! \brief Splits line at next LF and return it as arr(1st-part, remaining-part). */
   function eat_line( $str, $sep="\n" )
   {
      $pos = strpos($str, $sep);
      if( $pos === false )
         return array( $str, '' );
      else
         return array( trim( substr($str, 0, $pos) ), trim( substr($str, $pos + 1) ) );
   }

   function add_menu_link( &$menu, $handle='' )
   {
      $handle = trim($handle);
      if( (string)$handle != '' )
      {
         $text = sprintf( T_('Templates with user-id [%s]'), $handle);
         $menu[$text] = "templates.php?to=".urlencode($handle);
      }
      else
         $menu[T_('Templates')] = "templates.php";
   }//add_menu_link

   function get_template_type_text( $type )
   {
      if( $type == PROFTYPE_TMPL_SENDMSG )
         return T_('Message#tmpl');
      elseif( $type == PROFTYPE_TMPL_INVITE )
         return T_('Invite#tmpl');
      elseif( $type == PROFTYPE_TMPL_NEWGAME )
         return T_('New Game#tmpl');
      else
         error('invalid_args', "ProfileTemplate.get_template_type_text($type)");
   }//get_template_type_text

   function new_default_profile( $uid, $type )
   {
      if( !ProfileTemplate::is_valid_type($type) )
         error('invalid_args', "ProfileTemplate.new_default_profile.check.type($type)");

      return new Profile( 0, $uid, $type, 1, true );
   }

   function is_valid_type( $type )
   {
      return ( $type == PROFTYPE_TMPL_SENDMSG
            || $type == PROFTYPE_TMPL_INVITE
            || $type == PROFTYPE_TMPL_NEWGAME );
   }

   function known_template_types()
   {
      static $TYPES = array( PROFTYPE_TMPL_SENDMSG, PROFTYPE_TMPL_INVITE, PROFTYPE_TMPL_NEWGAME );
      return $TYPES;
   }

} // end 'ProfileTemplate'



/**
 * \brief Class to handle invite-rematch and new-game from existing game/game-setup.
 */
class GameRematch
{

   // ------------ static functions ----------------------------

   function add_rematch_links( &$arr_menu, $gid, $game_status, $game_type, $tid )
   {
      global $base_path;

      $allow_newgame = $allow_invite = false;
      if( $game_type != GAMETYPE_GO ) // MPG
         $allow_newgame = true;
      elseif( $tid > 0 ) // tournament
         $allow_invite = $allow_newgame = true;
      elseif( $game_status != GAME_STATUS_INVITED ) // normal-game
         $allow_invite = $allow_newgame = true;

      if( $allow_invite )
         $arr_menu[T_('Rematch#rematch')] = $base_path."game_rematch.php?mode=" . REMATCH_INVITE .URI_AMP."gid=$gid";
      if( $allow_newgame )
         $arr_menu[T_('Copy new game#rematch')] = $base_path."game_rematch.php?mode=" . REMATCH_NEWGAME .URI_AMP."gid=$gid";
   }//add_rematch_links

} //end 'GameRematch'



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
   // handicap-type => handicaptype-category
   static $ARR_HTYPES = array(
         HTYPE_CONV     => CAT_HTYPE_CONV,
         HTYPE_PROPER   => CAT_HTYPE_PROPER,
         HTYPE_NIGIRI   => CAT_HTYPE_MANUAL,
         HTYPE_DOUBLE   => CAT_HTYPE_MANUAL,
         HTYPE_BLACK    => CAT_HTYPE_MANUAL,
         HTYPE_WHITE    => CAT_HTYPE_MANUAL,
         HTYPE_AUCTION_SECRET => CAT_HTYPE_FAIR_KOMI,
      );
   return @$ARR_HTYPES[$handitype];
}

function get_invite_handicaptype( $handitype )
{
   // handicap-type => invite-handicap-type
   static $ARR_INVITE_HTYPES = array(
         HTYPE_CONV     => INVITE_HANDI_CONV,
         HTYPE_PROPER   => INVITE_HANDI_PROPER,
         HTYPE_NIGIRI   => INVITE_HANDI_NIGIRI,
         HTYPE_DOUBLE   => INVITE_HANDI_DOUBLE,
         HTYPE_BLACK    => INVITE_HANDI_FIXCOL,
         HTYPE_WHITE    => INVITE_HANDI_FIXCOL,
         HTYPE_AUCTION_SECRET => INVITE_HANDI_AUCTION_SECRET,
      );
   return @$ARR_INVITE_HTYPES[$handitype];
}

function get_handicaptype_for_invite( $inv_handitype, $is_black_col=null )
{
   // invite-handicap-type -> handicap-type
   static $ARR_HTYPES = array(
         INVITE_HANDI_CONV    => HTYPE_CONV,
         INVITE_HANDI_PROPER  => HTYPE_PROPER,
         INVITE_HANDI_NIGIRI  => HTYPE_NIGIRI,
         INVITE_HANDI_DOUBLE  => HTYPE_DOUBLE,
         //INVITE_HANDI_FIXCOL  => '', // calculated mapping (see below)
         INVITE_HANDI_AUCTION_SECRET => HTYPE_AUCTION_SECRET,
      );

   if( $inv_handitype != INVITE_HANDI_FIXCOL )
      return @$ARR_HTYPES[$inv_handitype];
   elseif( is_null($is_black_col) )
      error('invalid_args', "get_handicaptype_for_invite.check.is_black_col_null($inv_handitype)");
   else
      return ($is_black_col) ? HTYPE_BLACK : HTYPE_WHITE;
}//get_handicaptype_for_invite

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
         GSETVIEW_FAIRKOMI => T_('fair-komi settings#gsview'),
         GSETVIEW_MPGAME => T_('multi-player settings#gsview'),
      );
   }
   return @$ARR[$viewmode];
}

// return arr( $must_be_rated, $rating1, $rating2 ) ready for db-insert
// note: multi-player-game requires rated game-players (RatingStatus != NONE),
//       see also append_form_add_waiting_room_game()-func
function parse_waiting_room_rating_range( $is_multi_player_game=false, $arg_rating1=null, $arg_rating2=null )
{
   if( !$is_multi_player_game && get_request_arg('must_be_rated') != 'Y' )
   {
      $MustBeRated = 'N';
      //to keep a good column sorting:
      $rating1 = $rating2 = OUT_OF_RATING;
   }
   else
   {
      $MustBeRated = 'Y';
      $rating1 = ( is_null($arg_rating1) ) ? get_request_arg('rating1') : $arg_rating1;
      $rating2 = ( is_null($arg_rating2) ) ? get_request_arg('rating2') : $arg_rating2;
      $rating1 = read_rating( $rating1 );
      $rating2 = read_rating( $rating2 );

      if( $rating1 == NO_RATING || $rating2 == NO_RATING )
         error('rank_not_rating', "parse_waiting_room_rating_range.check($rating1,$rating2)");

      if( $rating2 < $rating1 )
         swap( $rating1, $rating2 );

      $rating1 -= 50;
      $rating2 += 50;
   }

   return array( $MustBeRated, $rating1, $rating2 );
}//parse_waiting_room_rating_range

// add form-elements required for new-game for waiting-room
// INPUT: must_be_rated, rating1, rating2, min_rated_games, comment
function append_form_add_waiting_room_game( &$mform, $viewmode, $read_args=false )
{
   // note: multi-player-game requires rated game-players (RatingStatus != NONE)
   $rating_array = getRatingArray();

   $rating_min = '30 kyu';
   $rating_max = '9 dan';
   if( $read_args ) // read init-vals from URL (for rematch / profile-template)
   {
      $must_be_rated = ( @$_REQUEST['mb_rated'] == 'Y' );
      $url_rat_min = (isset($_REQUEST['rat1'])) ? (int) $_REQUEST['rat1'] : OUT_OF_RATING;
      $url_rat_max = (isset($_REQUEST['rat2'])) ? (int) $_REQUEST['rat2'] : OUT_OF_RATING;
      if( $url_rat_min < OUT_OF_RATING )
         $rating_min = echo_rating( $url_rat_min, /*%*/false, /*gfx-uid*/0, /*engl*/true, /*short*/false );
      if( $url_rat_max < OUT_OF_RATING )
         $rating_max = echo_rating( $url_rat_max, /*%*/false, /*gfx-uid*/0, /*engl*/true, /*short*/false );
      $min_rated_games = (int) @$_REQUEST['min_rg'];
      $same_opponent = (int) @$_REQUEST['same_opp'];
      $comment = trim(@$_REQUEST['comment']);
   }
   else
   {
      $must_be_rated = false;
      $min_rated_games = $comment = '';
      $same_opponent = 0;
   }
   if( $viewmode == GSETVIEW_MPGAME )
      $must_be_rated = $disable_mbrated = true;
   else
      $disable_mbrated = false;

   $mform->add_row( array( 'DESCRIPTION', T_('Require rated opponent'),
                           'CHECKBOXX', 'must_be_rated', 'Y', "", $must_be_rated, ($disable_mbrated ? 'disabled=1' : ''),
                           'TEXT', sptext(T_('If yes, rating between'),1),
                           'SELECTBOX', 'rating1', 1, $rating_array, $rating_min, false,
                           'TEXT', sptext(T_('and')),
                           'SELECTBOX', 'rating2', 1, $rating_array, $rating_max, false ) );

   if( $viewmode != GSETVIEW_SIMPLE )
      $mform->add_row( array( 'DESCRIPTION', T_('Min. rated finished games'),
                              'TEXTINPUT', 'min_rated_games', 5, 5, $min_rated_games,
                              'TEXT', MINI_SPACING . T_('(optional)'), ));

   if( $viewmode == GSETVIEW_EXPERT || $viewmode == GSETVIEW_FAIRKOMI )
   {
      $same_opp_array = build_accept_same_opponent_array(array( 0,  -101, -102, -103,  -1, -2, -3,  3, 7, 14 ));
      $mform->add_row( array( 'DESCRIPTION', T_('Accept same opponent'),
                              'SELECTBOX', 'same_opp', 1, $same_opp_array, $same_opponent, false, ));
   }

   $mform->add_row( array( 'SPACE' ) );
   $mform->add_row( array( 'DESCRIPTION', T_('Comment'),
                           'TEXTINPUT', 'comment', 40, 40, $comment ) );
}//append_form_add_waiting_room_game

function echo_started_games( $game_count )
{
   if( $game_count <= 0 )
      return '';

   $fmt = ($game_count > 1)
      ? T_('%s games started#same_opp')
      : T_('%s game started#same_opp');
   return sprintf( $fmt, $game_count );
}

// WaitingRoom.SameOpponent: 0=always, <-101=(-n-100) total times, <0=n times (same game-offer), >0=after n days
// \param $game_row expecting JoinedCount, X_TotalCount
function echo_accept_same_opponent( $same_opp, $game_row=null )
{
   if( $same_opp == 0 )
      return T_('always#same_opp');

   if( $same_opp < SAMEOPP_TOTAL )
   {
      if ($same_opp == SAMEOPP_TOTAL-1 )
         $out = T_('1 total time#same_opp');
      else
         $out = sprintf( T_('%s total times#same_opp'), -$same_opp + SAMEOPP_TOTAL );
      if( is_array($game_row) && (int)@$game_row['X_TotalCount'] > 0 )
         $out .= ' (' . echo_started_games($game_row['X_TotalCount']) . ')';
   }
   elseif( $same_opp < 0 )
   {
      if ($same_opp == -1)
         $out = T_('1 time (same offer)#same_opp');
      else //if ($same_opp < 0)
         $out = sprintf( T_('%s times (same offer)#same_opp'), -$same_opp );
      if( is_array($game_row) && (int)@$game_row['JoinedCount'] > 0 )
      {
         $join_fmt = ($game_row['JoinedCount'] > 1)
            ? T_('joined %s games#same_opp')
            : T_('joined %s game#same_opp');
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
      if( is_array($game_row) && isset($game_row['X_ExpireDate']) && ($game_row['X_ExpireDate'] > $NOW) )
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
