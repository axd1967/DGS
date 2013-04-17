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
   public $mp_query = '';
   public $time_query = '';

   private static $MOVE_INSERT_QUERY = "INSERT INTO Moves ( gid, MoveNr, Stone, PosX, PosY, Hours ) VALUES ";


   public function __construct( $my_id, $gid, $action, $is_quick )
   {
      $this->my_id = $my_id;
      $this->gid = $gid;
      $this->action = $action;
      $this->is_quick = $is_quick;
   }

   public function init_query( $moves, $mp_query, $time_query )
   {
      /* **********************
      >>> See also confirm.php, quick_play.php, include/quick/quick_game.php and clock_tick.php

      Various dirty things (like duplicated moves) could appear
      in case of multiple calls with the same move number. This could
      happen in case of multi-players account with simultaneous logins
      or if one player hit twice the validation button during a net lag
      and/or if the opponent had already played between the two calls.

      Because the LOCK query is not implemented with MySQL < 4.0 (and despite that),
      we use the Moves field of the Games table to check those
      possible multiple queries using "optimistic locking".

      This is why:
      - the arguments are checked against the current state of the Games table
      - the current Games table give the current Moves value
      - the Games table is always modified while checking its Moves field (see $game_clause)
      - the Games table modification must always modify the Moves field (see $game_query)
      - this modification is always done in first place and checked before continuation
      *********************** */
      $this->game_clause = " WHERE ID={$this->gid} AND Status".IS_RUNNING_GAME." AND Moves=$moves LIMIT 1";

      $this->mp_query = $mp_query;
      $this->time_query = $time_query;
   }//init_query


   // \param $board Board-object
   // \param $orig_stonestring must be for non-quick-suite
   // \param $quick_moves must be set for quick-suite; ignored for non-quick-suite
   public function prepare_game_action_set_handicap( $dbgmsg, $board, $orig_stonestring, $quick_moves )
   {
      //TODO TODO TODO
      global $Moves, $Handicap, $Status, $hours, $White_ID, $GameFlags;

      $dbgmsg .= ".GAH.prepare_game_action_set_handicap({$this->gid},{$this->action},$Status,$Moves,$Handicap)";
      $board_size = $board->size;

      $this->move_query = self::$MOVE_INSERT_QUERY; // gid,MoveNr,Stone,PosX,PosY,Hours

      if( $this->is_quick )
         $board->add_handicap_stones( $quick_moves ); // check coords
      else
      {
         if( $Status != GAME_STATUS_PLAY || !( $Handicap > 1 && $Moves == 1 ) )
            error('invalid_action', "$dbgmsg.check_status");

         $stonestring = check_handicap( $board, $orig_stonestring );
         if( strlen( $stonestring ) != 2 * $Handicap )
            error('wrong_number_of_handicap_stone', "$dbgmsg.check.handicap($stonestring)");

         $quick_moves = array();
         for( $i=1; $i <= $Handicap; $i++ )
         {
            list( $x, $y ) = sgf2number_coords( substr($stonestring, $i*2-2, 2), $board_size );
            if( !isset($x) || !isset($y) )
               error('illegal_position', "$dbgmsg.check_pos(#$i,$x,$y)");
            else
               $quick_moves[] = array( $x, $y );
         }
      }

      for( $i=1; $i <= $Handicap; $i++ )
      {
         list( $x, $y ) = $quick_moves[$i-1];
         $this->move_query .= "({$this->gid}, $i, ".BLACK.", $x, $y, " . ($i == $Handicap ? "$hours)" : "0), " );
      }


      $this->game_query = "UPDATE Games SET Moves=$Handicap, " . //See *** HOT_SECTION ***
          "Last_X=$x, " .
          "Last_Y=$y, " .
          "Last_Move='" . number2sgf_coords($x, $y, $board_size) . "', " .
          "Flags=$GameFlags, " .
          "Snapshot='" . GameSnapshot::make_game_snapshot($board_size, $board) . "', " .
          "ToMove_ID=$White_ID, ";
   }//prepare_game_action_set_handicap


   // \param $move_coord arr(x,y) or sgf-coord of move
   // \param $chk_stonestring if given check against calculated prisonerstring
   public function prepare_game_action_do_move( $dbgmsg, $board, $move_coord, $chk_stonestring='' )
   {
      //TODO TODO TODO
      global $Last_Move, $Last_X, $Last_Y, $Size, $to_move, $GameFlags, $Black_Prisoners, $White_Prisoners, $Moves, $hours, $next_to_move_ID;

      $dbgmsg .= ".GAH.prepare_game_action_do_move({$this->gid},{$this->action})";

      $next_status = GAME_STATUS_PLAY;

      {//to fix the old way Ko detect. Could be removed when no more old way games.
         if( !@$Last_Move ) $Last_Move = number2sgf_coords($Last_X, $Last_Y, $Size);
      }
      $gchkmove = new GameCheckMove( $board );
      $gchkmove->check_move( $move_coord, $to_move, $Last_Move, $GameFlags );
      $gchkmove->update_prisoners( $Black_Prisoners, $White_Prisoners );

      $this->move_query = self::$MOVE_INSERT_QUERY; // gid,MoveNr,Stone,PosX,PosY,Hours

      $prisoner_string = '';
      foreach( $gchkmove->prisoners as $coord )
      {
         list( $x, $y ) = $coord;
         $this->move_query .= "({$this->gid}, $Moves, ".NONE.", $x, $y, 0), ";
         $prisoner_string .= number2sgf_coords($x, $y, $Size);
      }

      if( ( strlen($prisoner_string) != $gchkmove->nr_prisoners*2 )
            || ( $chk_stonestring && $prisoner_string != $chk_stonestring) )
         error('move_problem', "$dbgmsg.prisoner({$gchkmove->nr_prisoners},$prisoner_string,$chk_stonestring)");

      $this->move_query .= "({$this->gid}, $Moves, $to_move, {$gchkmove->colnr}, {$gchkmove->rownr}, $hours) ";

      $this->game_query = "UPDATE Games SET Moves=$Moves, " . //See *** HOT_SECTION ***
          "Last_X={$gchkmove->colnr}, " . //used with mail notifications
          "Last_Y={$gchkmove->rownr}, " .
          "Last_Move='" . number2sgf_coords($gchkmove->colnr, $gchkmove->rownr, $Size) . "', " . //used to detect Ko
          "Status='$next_status', ";

      if( $gchkmove->nr_prisoners > 0 )
      {
         if( $to_move == BLACK )
            $this->game_query .= "Black_Prisoners=$Black_Prisoners, ";
         else
            $this->game_query .= "White_Prisoners=$White_Prisoners, ";
      }

      if( $gchkmove->nr_prisoners == 1 )
         $GameFlags |= GAMEFLAGS_KO;
      else
         $GameFlags &= ~GAMEFLAGS_KO;

      $this->game_query .= "ToMove_ID=$next_to_move_ID, " .
         "Flags=$GameFlags, " .
         "Snapshot='" . GameSnapshot::make_game_snapshot($Size, $board) . "', ";
   }//prepare_game_action_do_move


   public function prepare_game_action_pass( $dbgmsg )
   {
      //TODO TODO TODO
      global $Moves, $Handicap, $Status, $to_move, $hours, $next_to_move_ID, $Last_Move, $GameFlags;

      $dbgmsg .= ".GAH.prepare_game_action_pass({$this->gid},{$this->action},$Status,$Moves,$Handicap)";

      if( $Moves < $Handicap )
         error('early_pass', "$dbgmsg.check.moves");

      if( $Status == GAME_STATUS_PLAY )
         $next_status = GAME_STATUS_PASS;
      else if( $Status == GAME_STATUS_PASS )
         $next_status = GAME_STATUS_SCORE;
      else
         error('invalid_action', "$dbgmsg.check_status");

      $this->move_query = self::$MOVE_INSERT_QUERY; // gid,MoveNr,Stone,PosX,PosY,Hours
      $this->move_query .= "({$this->gid}, $Moves, $to_move, ".POSX_PASS.", 0, $hours)";

      $this->game_query = "UPDATE Games SET Moves=$Moves, " . //See *** HOT_SECTION ***
          "Last_X=".POSX_PASS.", " .
          "Status='$next_status', " .
          "ToMove_ID=$next_to_move_ID, " .
          //"Last_Move='$Last_Move', " . //Not a move, re-use last one
          "Flags=$GameFlags, "; //Don't reset KO-Flag else PASS,PASS,RESUME could break a Ko
   }//prepare_game_action_pass

   public function prepare_game_action_generic( $message )
   {
      //TODO TODO TODO
      global $Handicap, $Moves, $NOW;

      if( $this->action != GAMEACT_DELETE )
      {
         if( $this->action != GAMEACT_RESIGN )
            $this->game_query .= $this->mp_query;
         $this->game_query .= $this->time_query . "Lastchanged=FROM_UNIXTIME($NOW)" ;

         if( $message )
         {
            $move_nr = ( $this->action == GAMEACT_SET_HANDICAP ) ? $Handicap : $Moves;
            $this->message_query = "INSERT INTO MoveMessages SET gid={$this->gid}, MoveNr=$move_nr, Text=\"$message\"";
         }
      }
   }//prepare_game_action_generic

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
            if( mysql_affected_rows() < 1 && $this->action != GAMEACT_DELETE )
               error('mysql_insert_move', "$dbgmsg.upd_moves2");

            clear_cache_quick_status( array( $ToMove_ID, $next_to_move_ID ), QST_CACHE_GAMES );
            GameHelper::delete_cache_status_games( "$dbgmsg.upd_moves3", $ToMove_ID, $next_to_move_ID );
            Board::delete_cache_game_moves( "$dbgmsg.upd_moves4", $this->gid );
         }

         if( $this->message_query )
         {
            $result = db_query( "$dbgmsg.upd_msg1", $this->message_query );
            if( mysql_affected_rows() < 1 && $this->action != GAMEACT_DELETE )
               error('mysql_insert_move', "$dbgmsg.upd_msg2");

            Board::delete_cache_game_move_messages( "$dbgmsg.upd_msg3", $this->gid );
         }

         if( $game_finished )
         {
            $game_finalizer = new GameFinalizer( ACTBY_PLAYER, $this->my_id, $this->gid, $tid, $Status,
               $GameType, $GamePlayers, $GameFlags, $Black_ID, $White_ID, $Moves, ($game_row['Rated'] != 'N') );

            $do_delete = ( $this->action == GAMEACT_DELETE );

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
