<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

//$TranslateGroups[] = "Game";

define('MAX_ADD_DAYS', 14); // max. amount of days that can be added to game by user


/*!
 * \brief returns true, if user (uid) is allowed to add additional
 *        time for his/her opponent in game specified by $game_row.
 */
function allow_add_time_opponent( $game_row, $uid )
{
   // must be a running-game
   if( $game_row['Status'] == 'FINISHED' or $game_row['Status'] == 'INVITED' )
      return false;

   // must be one of my games
   if( $game_row['White_ID'] != $uid and $game_row['Black_ID'] != $uid )
      return false;

   // must not be a tournament-game
   if( $game_row['Tournament_ID'] != 0 )
      return false;

   // get opponents columns
   if( $game_row['Black_ID'] == $uid )
      $oppcolor = 'White';
   else
      $oppcolor = 'Black';
   // don't exceed 365 days maintime
   if( $game_row["{$oppcolor}_Maintime"]
         + time_convert_to_hours(MAX_ADD_DAYS,'days') > time_convert_to_hours(365,'days') )
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
   if( !is_numeric($add_hours) or $add_hours < 0
         or $add_hours > time_convert_to_hours( MAX_ADD_DAYS, 'days'))
      return sprintf( 'Invalid value for add_hours [%s]', $add_hours);

   if ( !$reset_byo and $add_hours == 0 )
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
      or !isset($game_row["{$oppcolor}_Byoperiods"])
      )
      error('internal_error',"add_time_opponent.incomplete_game_row($gid)");

   if( $reset_byo && $game_row['Byotype'] == 'FIS' )
      $reset_byo = 0;
   if( $reset_byo && $game_row["{$oppcolor}_Byoperiods"] == -1 )
      $reset_byo = 0;
   if( $reset_byo && ($game_row['Byotime'] <= 0 or $game_row['Byoperiods'] <= 0) )
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

?>
