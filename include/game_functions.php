<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

define('MAX_ADD_DAYS', 7); // max. amount of days that can be added to game by user


/*!
 * \brief returns true, if user (uid) is allowed to add additional
 *        time for his/her opponent in game specified by $game_row.
 */
function allow_add_time_opponent( $game_row, $uid )
{
   //TODO on what conditions is this allowed ?

   // must be a running-game
   if ( $game_row['Status'] == 'FINISHED' or $game_row['Status'] == 'INVITATION' )
      return false;

   // must be one of my games
   if ( $game_row['White_ID'] != $uid and $game_row['Black_ID'] != $uid )
      return false;

   // must not be a tournament-game
   if ( $game_row['Tournament_ID'] != 0 )
      return false;

   // more potential conditions: TODO
   // - maintime of opponent <= 3 days and no byo-yomi
   // - maintime of opponent == 0 days and in byo-yomi
   /* TODO
   if ( $game_row['Black_ID'] == $uid )
      $opp_maintime = $game_row['White_Maintime'];
   else
      $opp_maintime = $game_row['Black_Maintime'];
   */

   return true;
}

/*!
 * \brief returns 0, if time (add_hours) has been successfully added
 *        for opponent of player (uid) in specified game (gid).
 *        Otherwise error-string is returned.
 * \param $gid game-id to add time for
 * \param $uid user giving time to his opponent
 * \param $add_hours amount of hours to add to maintime of opponent,
 *        allowed range is 1 .. 24*MAX_ADD_DAYS
 */
function add_time_opponent( $gid, $uid, $add_hours )
{
   if ( !is_numeric($add_hours) or $add_hours <= 0 or $add_hours > 24*MAX_ADD_DAYS )
      return sprintf( 'Invalid value for add_hours [%s]', $add_hours);

   $query = "SELECT Games.* from Games WHERE ID=$gid";
   $game_row = mysql_single_fetch( 'message.find_game', $query);
   if ( !$game_row )
      return "Can\'t find game [$gid] to add time";

   if ( !allow_add_time_opponent( $game_row, $uid ) )
      return 'Conditions are not met to allow to add time';

   // get opponents column to update
   if ( $game_row['Black_ID'] == $uid )
      $oppcol = 'White_Maintime';
   else
      $oppcol = 'Black_Maintime';

   // add time to opponent
   $upd_query =
      "UPDATE Games SET $oppcol=$oppcol+$add_hours " .
      "WHERE ID=$gid AND Status" . IS_RUNNING_GAME . " LIMIT 1";
   $result = mysql_query( $upd_query );
   if ( !$result or mysql_affected_rows() != 1 )
      return "Add time failed to write in database";

   return 0; // success (no-error)
}

?>
