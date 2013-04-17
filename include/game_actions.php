<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once 'include/board.php';
require_once 'include/std_classes.php';
require_once 'include/std_functions.php';
require_once 'include/game_functions.php';


define('GAHACT_DELETE', 'delete');

/*!
 * \class GameActionHelper
 *
 * \brief Helper class to handle actions on game:
 *
 *    - set handicap stones
 *    - play normal move
 *    - pass move
 *
 *    - resign game
 *    - delete game
 */
class GameActionHelper
{
   public $my_id;
   public $gid;
   public $action;
   public $is_quick;

   public $game_clause = '';
   public $game_query = '';
   public $move_query = '';
   public $message_query = '';

   public function __construct( $my_id, $gid, $action, $is_quick )
   {
      $this->my_id = $my_id;
      $this->gid = $gid;
      $this->action = $action;
      $this->is_quick = $is_quick;
   }

   public function update_game( $dbgmsg, $game_finished )
   {
      //TODO TODO TODO
      global $tid, $Status, $GameType, $GamePlayers, $GameFlags, $Black_ID, $White_ID, $Moves, $game_row,
             $score, $message_raw, $ActivityMax, $ActivityForMove, $NOW, $ToMove_ID, $next_to_move_ID;

      $dbgmsg .= ".GAH.update_game({$this->gid},{$this->action})";

      ta_begin();
      {//HOT-section to update game-action
         //See *** HOT_SECTION *** above
         if( $this->game_query )
         {
            $result = db_query( "$dbgmsg.upd_game1", $this->game_query . $this->game_clause );
            if( mysql_affected_rows() != 1 )
               error('mysql_update_game', "$dbgmsg.upd_game2");

            GameHelper::delete_cache_game_row( "$dbgmsg.upd_game3", $this->gid );
         }

         if( $this->move_query )
         {
            $result = db_query( "$dbgmsg.upd_moves1", $this->move_query );
            if( mysql_affected_rows() < 1 && $this->action != GAHACT_DELETE )
               error('mysql_insert_move', "$dbgmsg.upd_moves2");

            clear_cache_quick_status( array( $ToMove_ID, $next_to_move_ID ), QST_CACHE_GAMES );
            GameHelper::delete_cache_status_games( "$dbgmsg.upd_moves3", $ToMove_ID, $next_to_move_ID );
            Board::delete_cache_game_moves( "$dbgmsg.upd_moves4", $this->gid );
         }

         if( $this->message_query )
         {
            $result = db_query( "$dbgmsg.upd_msg1", $this->message_query );
            if( mysql_affected_rows() < 1 && $this->action != GAHACT_DELETE )
               error('mysql_insert_move', "$dbgmsg.upd_msg2");

            Board::delete_cache_game_move_messages( "$dbgmsg.upd_msg3", $this->gid );
         }

         if( $game_finished )
         {
            $game_finalizer = new GameFinalizer( ACTBY_PLAYER, $this->my_id, $this->gid, $tid, $Status,
               $GameType, $GamePlayers, $GameFlags, $Black_ID, $White_ID, $Moves, ($game_row['Rated'] != 'N') );

            $do_delete = ( $this->action == GAHACT_DELETE );

            $game_finalizer->skip_game_query();
            $game_finalizer->finish_game( "confirm", $do_delete, null, $score, $message_raw );
         }
         else
            $do_delete = false;

         // Notify opponent about move
         if( !$do_delete )
            notify( "$dbgmsg.notify_opponent($next_to_move_ID)", $next_to_move_ID );

         // Increase moves and activity
         db_query( "$dbgmsg.activity({$this->my_id})",
               "UPDATE Players SET Moves=Moves+1" // NOTE: count also delete + set-handicap as one move
               . ( $this->is_quick ? ",LastQuickAccess=FROM_UNIXTIME($NOW)" : '' )
               .",Activity=LEAST($ActivityMax,$ActivityForMove+Activity)"
               .",LastMove=FROM_UNIXTIME($NOW)"
               ." WHERE ID={$this->my_id} LIMIT 1" );

         increaseMoveStats( $this->my_id );
      }
      ta_end();
   }//update_game_action

} //end 'GameActionHelper'

?>
