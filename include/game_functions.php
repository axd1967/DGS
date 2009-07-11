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

//$TranslateGroups[] = "Game";

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

// handicap-types category
$ARR_GLOBAL_HTYPES = array(
      HTYPE_CONV     => CAT_HTYPE_CONV,
      HTYPE_PROPER   => CAT_HTYPE_PROPER,
      HTYPE_NIGIRI   => CAT_HTYPE_MANUAL,
      HTYPE_DOUBLE   => CAT_HTYPE_MANUAL,
      HTYPE_BLACK    => CAT_HTYPE_MANUAL,
      HTYPE_WHITE    => CAT_HTYPE_MANUAL,
      //HTYPE_AUKO     => CAT_HTYPE_FAIRKOMI,
   );

/*!
 * \brief returns true, if user (uid) is allowed to add additional
 *        time for his/her opponent in game specified by $game_row.
 */
function allow_add_time_opponent( $game_row, $uid )
{
   // must be a running-game
   if( $game_row['Status'] == 'FINISHED' || $game_row['Status'] == 'INVITED' )
      return false;

   // must be one of my games
   if( $game_row['White_ID'] != $uid && $game_row['Black_ID'] != $uid )
      return false;

   // must not be a tournament-game
   //TODO if( $game_row['Tournament_ID'] != 0 ) return false;

   // get opponents columns
   if( $game_row['Black_ID'] == $uid )
      $oppcolor = 'White';
   else
      $oppcolor = 'Black';
   // don't exceed 365 days maintime
   if( $game_row["{$oppcolor}_Maintime"]
         + time_convert_to_hours(MAX_ADD_DAYS,'days') > time_convert_to_hours(360,'days') )
      return false;

   // TODO: might be denied, if declared as forbidden in waiting-room (by option)
   return true;
}

/*!
 * \brief returns number of hours added (may be 0), if time has been successfully added
 *        for opponent of player (uid) in specified game (gid).
 *        Otherwise error-string is returned.
 * \param $game_row pre-loaded game_row to add time for
 *        or numeric game-id to load the game from database
 * \param $uid user giving time to his opponent
 * \param $add_hours amount of hours to add to maintime of opponent,
 *        allowed range is 0 hour .. MAX_ADD_DAYS
 * \param $reset_byo if true, also byo-yomi will be resetted (for JAP/CAN)
 */
function add_time_opponent( &$game_row, $uid, $add_hours, $reset_byo=false )
{
   if( !is_numeric($add_hours) || $add_hours < 0
         || $add_hours > time_convert_to_hours( MAX_ADD_DAYS, 'days'))
      return sprintf( 'Invalid value for add_hours [%s]', $add_hours);

   if( !$reset_byo && $add_hours == 0 )
      return 0; // nothing to do (0 hours added, no error)

   if( is_numeric($game_row) )
   {
      $gid = $game_row;
      $game_row = mysql_single_fetch( 'add_time_opponent',
                  "SELECT Games.* from Games WHERE ID=$gid");
      if( !$game_row )
         error('unknown_game',"add_time_opponent($gid)");
   }
   else
      $gid = $game_row['ID'];

   if( !allow_add_time_opponent( $game_row, $uid ) )
      return "Conditions are not met to allow to add time for game [$gid]";

   // get opponents columns to update
   if( $game_row['Black_ID'] == $uid )
   {
      $oppcolor = 'White';
      $Stone = BLACK;
   }
   else
   {
      $oppcolor = 'Black';
      $Stone = WHITE;
   }

   if( !isset($game_row["{$oppcolor}_Maintime"])
      || !isset($game_row["{$oppcolor}_Byoperiods"])
      )
      error('internal_error',"add_time_opponent.incomplete_game_row($gid)");

   if( $reset_byo && $game_row['Byotype'] == 'FIS' )
      $reset_byo = 0;
   if( $reset_byo && $game_row["{$oppcolor}_Byoperiods"] == -1 )
      $reset_byo = 0;
   if( $reset_byo && ($game_row['Byotime'] <= 0 || $game_row['Byoperiods'] <= 0) )
      $reset_byo = 0; // no byoyomi-reset if no byoyomi (no time or no periods)

/*
   // min. 1h to be able to reset byo-period with -1 (for next period)
   if( $reset_byo && $add_hours <= 0 && $game_row["{$oppcolor}_Maintime"] <= 0 )
      $add_hours = 1;
*/

   // add maintime and eventually reset byo-time for opponent
   $game_query = '';
   if( $add_hours > 0 )
   {
      $game_query.= ",{$oppcolor}_Maintime={$oppcolor}_Maintime+$add_hours";
      $game_row["{$oppcolor}_Maintime"]+= $add_hours;
   }
   if( $reset_byo )
   {
      $game_query.= ",{$oppcolor}_Byoperiods=-1";
      $game_row["{$oppcolor}_Byoperiods"] = -1;
   }
   if( !$game_query )
      return 0; //nothing to do

   //TODO: HOT_SECTION to avoid multiple-clicks
   $game_query = "UPDATE Games SET ".substr($game_query,1)
               . " WHERE ID=$gid AND Status" . IS_RUNNING_GAME . " LIMIT 1";

   // insert entry in Moves-table
   $Moves = $game_row['Moves'];
   $move_query = "INSERT INTO Moves (gid, MoveNr, Stone, PosX, PosY, Hours) VALUES "
      . "($gid, $Moves, $Stone, ".POSX_ADDTIME.", ".($reset_byo ? 1 : 0).", $add_hours)";

   //see also confirm.php
   mysql_query( $game_query )
         or error('mysql_query_failed',"add_time_opponent.update($gid)");
   if( mysql_affected_rows() != 1 ) //0 if it had done nothing
      error('mysql_update_game',"add_time_opponent.update($gid)");

   mysql_query( $move_query )
      or error('mysql_query_failed',"add_time_opponent.insert_move($gid)");
   if( mysql_affected_rows() != 1 )
      error('mysql_insert_move',"add_time_opponent.insert_move($gid)");

   return $add_hours; // success (no-error)
}

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

   if( (string)$komi == '-0' )
      $komi = 0;
   return (float)$komi;
}

// returns adjusted handicap within limits, also checking for valid limits
function adjust_handicap( $handicap, $adj_handicap, $min_handicap, $max_handicap )
{
   // assure valid limits
   $min_handicap = min( MAX_HANDICAP, max( 0, $min_handicap ));
   $max_handicap = ( $max_handicap < 0 ) ? MAX_HANDICAP : min( MAX_HANDICAP, $max_handicap );

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
   global $ARR_GLOBAL_HTYPES;
   return @$ARR_GLOBAL_HTYPES[$handitype];
}

function build_image_double_game( $with_sep=false, $class='' )
{
   global $base_path;
   return image( $base_path.'17/w.gif', T_('Double game (White)'), null, $class)
          . ( $with_sep ? '&nbsp;+&nbsp;' : '' )
          . image( $base_path.'17/b.gif', T_('Double game (Black)'), null, $class);
}

?>
