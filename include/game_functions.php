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

//$TranslateGroups[] = "Game";

require_once( 'include/globals.php' );
require_once( 'include/time_functions.php' );
require_once( 'include/classlib_game.php' );
require_once( 'include/utilities.php' );


define('MAX_ADD_DAYS', 14); // max. amount of days that can be added to game by user

// enum Waitingroom.JigoMode
define('JIGOMODE_KEEP_KOMI',  'KEEP_KOMI');
define('JIGOMODE_ALLOW_JIGO', 'ALLOW_JIGO');
define('JIGOMODE_NO_JIGO',    'NO_JIGO');

// see Waitingroom.Handicaptype in specs/db/table-Waitingroom.txt
define('HTYPE_CONV',    'conv'); // conventional handicap
define('HTYPE_PROPER',  'proper'); // proper handicap
define('HTYPE_NIGIRI',  'nigiri'); // manual, color nigiri
define('HTYPE_DOUBLE',  'double'); // manual, color double (=black and white)
define('HTYPE_BLACK',   'black'); // manual, color black
define('HTYPE_WHITE',   'white'); // manual, color white

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

   return $handicap;
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

?>
