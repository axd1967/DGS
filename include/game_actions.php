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
 *
 * Example:
 *    $gah = new GameActionHelper( $my_id, $gid, false );
 *    $gah->set_game_action( GAMEACT_DO_MOVE );
 *    $game_row = $gah->load_game( 'my_loc' );
 *    list( $to_move ) = $gah->init_globals( 'my_loc' );
 *    $board = $gah->load_game_board( 'my_loc' );
 *    $gah->init_query( 'my_loc' );
 *
 *    $gah->prepare_game_...(...);
 *
 *    $gah->update_game( 'my_loc' );
 */
class GameActionHelper
{
   private $my_id;
   private $gid;
   private $is_quick;
   private $action;

   private $game_clause = '';
   private $game_query = '';
   private $move_query = '';
   private $message_query = '';
   private $mp_query = '';
   private $time_query = '';

   private $game_row = null;
   private $board = null;

   private $message_raw = '';
   private $message = '';
   private $score = null;
   private $hours = 0;
   private $to_move = 0;
   private $next_to_move = 0;
   private $next_to_move_ID = 0;
   private $game_finished = false;

   private static $MOVE_INSERT_QUERY = "INSERT INTO Moves ( gid, MoveNr, Stone, PosX, PosY, Hours ) VALUES ";


   public function __construct( $my_id, $gid, $is_quick )
   {
      $this->my_id = $my_id;
      $this->gid = $gid;
      $this->is_quick = $is_quick;
   }

   public function set_game_action( $action )
   {
      $this->action = $action;
   }

   public function set_game_finished( $game_finished )
   {
      $this->game_finished = $game_finished;
   }

   public function load_game( $dbgmsg )
   {
      $this->game_row = GameHelper::load_game_row( "$dbgmsg.load_game", $this->gid );
      return $this->game_row;
   }

   public function init_globals( $dbgmsg )
   {
      $dbgmsg .= ".GAH.init_globals({$this->gid})";
      if( is_null($this->game_row) && is_null($this->action) )
         error('assert', "$dbgmsg.miss_init1");

      $ToMove_ID = $this->game_row['ToMove_ID'];
      $Black_ID = $this->game_row['Black_ID'];
      $White_ID = $this->game_row['White_ID'];

      if( $ToMove_ID == 0 )
         error('game_finished', "$dbgmsg.bad_ToMove_ID.gamend");
      if( $Black_ID == $ToMove_ID )
         $this->to_move = BLACK;
      elseif( $White_ID == $ToMove_ID )
         $this->to_move = WHITE;
      else
         error('database_corrupted', "$dbgmsg.check.to_move($ToMove_ID,$Black_ID,$White_ID)");

      // correct to_move on resign-if-not-your-turn
      if( $this->action == GAMEACT_RESIGN && $this->my_id != $ToMove_ID )
         $this->to_move = WHITE + BLACK - $this->to_move;

      $this->next_to_move = WHITE + BLACK - $this->to_move;
      $this->next_to_move_ID = ( $this->next_to_move == BLACK ? $Black_ID : $White_ID );

      return array( $this->to_move );
   }//init_globals


   public function load_game_board( $dbgmsg )
   {
      $dbgmsg .= ".GAH.load_game_board({$this->gid})";
      if( is_null($this->game_row) && is_null($this->action) )
         error('assert', "$dbgmsg.miss_init2");
      $Status = $this->game_row['Status'];

      $no_marked_dead = ( $this->action == GAMEACT_DO_MOVE || $Status == GAME_STATUS_PLAY || $Status == GAME_STATUS_PASS );
      $board_opts = ( $no_marked_dead ? 0 : BOARDOPT_MARK_DEAD );

      $board = new Board();
      if( !$board->load_from_db( $this->game_row, 0, $board_opts) )
         error('internal_error', "$dbgmsg.load_from_db");
      $this->board = $board;

      return $board;
   }//load_game_board


   public function init_query( $dbgmsg )
   {
      if( is_null($this->game_row) || is_null($this->action) || $this->to_move == 0 )
         error('assert', "$dbgmsg.GAH.init_query({$this->gid}).miss_init3");

      $Moves = $this->get_moves();
      $dbgmsg .= ".GAH.init_query({$this->gid},{$this->action},$Moves,{$this->to_move})";

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
      $this->game_clause = " WHERE ID={$this->gid} AND Status".IS_RUNNING_GAME." AND Moves=$Moves LIMIT 1";

      // update clock
      list( $this->hours, $upd_clock ) =
         GameHelper::update_clock( $dbgmsg, $this->game_row, $this->to_move, $this->next_to_move );
      $this->time_query = $upd_clock->get_query(false, true);

      // init mp-query
      $is_mpgame = ( $this->game_row['GameType'] != GAMETYPE_GO );
      if( $is_mpgame && ($this->action == GAMEACT_DO_MOVE || $this->action == GAMEACT_PASS
            || $this->action == GAMEACT_SET_HANDICAP || $this->action == GAMEACT_SCORE) )
      {
         list( $group_color, $group_order, $gpmove_color )
            = MultiPlayerGame::calc_game_player_for_move(
               $this->game_row['GamePlayers'], $Moves, $this->game_row['Handicap'], 2 );
         $mp_gp = GamePlayer::load_game_player( $this->gid, $group_color, $group_order );
         $this->mp_query = (( $this->game_row['ToMove_ID'] == $this->game_row['Black_ID'] ) ? 'Black_ID' : 'White_ID' ) .
            "={$mp_gp->uid}, ";
      }
      else
         $this->mp_query = '';
   }//init_query


   public function set_game_move_message( $message_raw )
   {
      if( is_null($this->game_row) )
         error('assert', "$dbgmsg.GAH.init_query({$this->gid}).miss_init4");

      $this->message_raw = trim($message_raw);
      if( preg_match( "/^<c>\s*<\\/c>$/si", $this->message_raw ) ) // remove empty comment-only tags
         $this->message_raw = '';
      $this->message = mysql_addslashes($this->message_raw);

      if( $this->message && preg_match( "#</?h(idden)?>#is", $this->message) )
         $this->game_row['GameFlags'] |= GAMEFLAGS_HIDDEN_MSG;
   }//prepare_game_move_message


   public function increase_moves()
   {
      $this->game_row['Moves']++;
   }

   public function get_moves()
   {
      return $this->game_row['Moves'];
   }


   // \param $orig_stonestring must be for non-quick-suite
   // \param $quick_moves must be set for quick-suite; ignored for non-quick-suite
   public function prepare_game_action_set_handicap( $dbgmsg, $orig_stonestring, $quick_moves )
   {
      global $Moves, $Handicap, $Status, $White_ID;
      $GameFlags = $this->game_row['GameFlags'];
      if( $this->is_quick && $this->game_row ) extract($this->game_row);

      $dbgmsg .= ".GAH.prepare_game_action_set_handicap({$this->gid},{$this->action},$Status,$Moves,$Handicap)";
      $board_size = $this->board->size;

      $this->move_query = self::$MOVE_INSERT_QUERY; // gid,MoveNr,Stone,PosX,PosY,Hours

      if( $this->is_quick )
         $this->board->add_handicap_stones( $quick_moves ); // check coords
      else
      {
         if( $Status != GAME_STATUS_PLAY || !( $Handicap > 1 && $Moves == 1 ) )
            error('invalid_action', "$dbgmsg.check_status");

         $stonestring = check_handicap( $this->board, $orig_stonestring );
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
         $this->move_query .= "({$this->gid}, $i, ".BLACK.", $x, $y, " . ($i == $Handicap ? "{$this->hours})" : "0), " );
      }


      $this->game_query = "UPDATE Games SET Moves=$Handicap, " . //See *** HOT_SECTION ***
          "Last_X=$x, " .
          "Last_Y=$y, " .
          "Last_Move='" . number2sgf_coords($x, $y, $board_size) . "', " .
          "Flags=$GameFlags, " .
          "Snapshot='" . GameSnapshot::make_game_snapshot($board_size, $this->board) . "', " .
          "ToMove_ID=$White_ID, ";
   }//prepare_game_action_set_handicap


   // \param $move_coord arr(x,y) or sgf-coord of move
   // \param $chk_stonestring if given check against calculated prisonerstring
   public function prepare_game_action_do_move( $dbgmsg, $move_coord, $chk_stonestring='' )
   {
      global $Last_Move, $Last_X, $Last_Y, $Size, $Black_Prisoners, $White_Prisoners, $Moves;
      $GameFlags = $this->game_row['GameFlags'];
      if( $this->is_quick && $this->game_row ) extract($this->game_row);

      $dbgmsg .= ".GAH.prepare_game_action_do_move({$this->gid},{$this->action})";

      $next_status = GAME_STATUS_PLAY;

      {//to fix the old way Ko detect. Could be removed when no more old way games.
         if( !@$Last_Move ) $Last_Move = number2sgf_coords($Last_X, $Last_Y, $Size);
      }
      $gchkmove = new GameCheckMove( $this->board );
      $gchkmove->check_move( $move_coord, $this->to_move, $Last_Move, $GameFlags );
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

      $this->move_query .= "({$this->gid}, $Moves, {$this->to_move}, {$gchkmove->colnr}, {$gchkmove->rownr}, {$this->hours}) ";

      $this->game_query = "UPDATE Games SET Moves=$Moves, " . //See *** HOT_SECTION ***
          "Last_X={$gchkmove->colnr}, " . //used with mail notifications
          "Last_Y={$gchkmove->rownr}, " .
          "Last_Move='" . number2sgf_coords($gchkmove->colnr, $gchkmove->rownr, $Size) . "', " . //used to detect Ko
          "Status='$next_status', ";

      if( $gchkmove->nr_prisoners > 0 )
      {
         if( $this->to_move == BLACK )
            $this->game_query .= "Black_Prisoners=$Black_Prisoners, ";
         else
            $this->game_query .= "White_Prisoners=$White_Prisoners, ";
      }

      if( $gchkmove->nr_prisoners == 1 )
         $GameFlags |= GAMEFLAGS_KO;
      else
         $GameFlags &= ~GAMEFLAGS_KO;

      $this->game_query .= "ToMove_ID={$this->next_to_move_ID}, " .
         "Flags=$GameFlags, " .
         "Snapshot='" . GameSnapshot::make_game_snapshot($Size, $this->board) . "', ";
   }//prepare_game_action_do_move


   public function prepare_game_action_pass( $dbgmsg )
   {
      global $Moves, $Handicap, $Status, $Last_Move;
      $GameFlags = $this->game_row['GameFlags'];
      if( $this->is_quick && $this->game_row ) extract($this->game_row);

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
      $this->move_query .= "({$this->gid}, $Moves, {$this->to_move}, ".POSX_PASS.", 0, {$this->hours})";

      $this->game_query = "UPDATE Games SET Moves=$Moves, " . //See *** HOT_SECTION ***
          "Last_X=".POSX_PASS.", " .
          "Status='$next_status', " .
          "ToMove_ID={$this->next_to_move_ID}, " .
          //"Last_Move='$Last_Move', " . //Not a move, re-use last one
          "Flags=$GameFlags, "; //Don't reset KO-Flag else PASS,PASS,RESUME could break a Ko
   }//prepare_game_action_pass


   public function prepare_game_action_resign( $dbgmsg )
   {
      global $Moves;
      $GameFlags = $this->game_row['GameFlags'];
      if( $this->is_quick && $this->game_row ) extract($this->game_row);

      $dbgmsg .= ".GAH.prepare_game_action_resign({$this->gid},{$this->action})";

      $next_status = GAME_STATUS_FINISHED;
      $this->score = ( $this->to_move == BLACK ) ? SCORE_RESIGN : -SCORE_RESIGN;

      $this->move_query = self::$MOVE_INSERT_QUERY; // gid,MoveNr,Stone,PosX,PosY,Hours
      $this->move_query .= "({$this->gid}, $Moves, {$this->to_move}, ".POSX_RESIGN.", 0, {$this->hours})";

      $this->game_query = "UPDATE Games SET Moves=$Moves, " . //See *** HOT_SECTION ***
          "Last_X=".POSX_RESIGN.", " .
          "Status='$next_status', " .
          "ToMove_ID=0, " .
          "Score={$this->score}, " .
          "Flags=$GameFlags, ";

      $this->game_finished = true;
   }//prepare_game_action_resign


   // \param $agree ignored for non-quick-suite; boolean value
   // \param $arr_coords ignored for non-quick-suite; use false | array of coord-moves [(x,y), ...]
   public function prepare_game_action_score( $dbgmsg, $stonestring, $toggle_uniq=false, $agree=true, $arr_coords=false )
   {
      global $Handicap, $Komi, $Black_Prisoners, $White_Prisoners, $Ruleset, $Status, $Moves;
      $GameFlags = $this->game_row['GameFlags'];
      if( $this->is_quick && $this->game_row ) extract($this->game_row);

      $dbgmsg .= ".GAH.prepare_game_action_score({$this->gid},{$this->action})";

      $board_size = $this->board->size;
      if( !$this->is_quick )
         $arr_coords = false;

      $gchkscore = new GameCheckScore( $this->board, $stonestring, $Handicap, $Komi, $Black_Prisoners, $White_Prisoners );
      if( $toggle_uniq )
         $gchkscore->set_toggle_unique();
      $game_score = $gchkscore->check_remove( getRulesetScoring($Ruleset), $arr_coords );
      $gchkscore->update_stonestring( $stonestring );
      $this->score = $game_score->calculate_score();

      $l = strlen( $stonestring );
      if( $Status == GAME_STATUS_SCORE2 && $l < 2 )
      {
         if( $this->is_quick && !$agree )
            error('invalid_action', "$dbgmsg.expect_agree($agree)");
         $next_status = GAME_STATUS_FINISHED;
         $this->game_finished = true;
      }
      else
      {
         if( $this->is_quick && $agree )
            error('invalid_action', "$dbgmsg.expect_disagree($agree)");
         $next_status = GAME_STATUS_SCORE2;
      }

      $this->move_query = self::$MOVE_INSERT_QUERY; // gid,MoveNr,Stone,PosX,PosY,Hours
      $mark_stone = ( $this->to_move == BLACK ) ? MARKED_BY_BLACK : MARKED_BY_WHITE;
      for( $i=0; $i < $l; $i += 2 )
      {
         list($x,$y) = sgf2number_coords(substr($stonestring, $i, 2), $board_size);
         $this->move_query .= "({$this->gid}, $Moves, $mark_stone, $x, $y, 0), ";
      }
      $this->move_query .= "({$this->gid}, $Moves, {$this->to_move}, ".POSX_SCORE.", 0, {$this->hours}) ";

      $this->game_query = "UPDATE Games SET Moves=$Moves, " . //See *** HOT_SECTION ***
          "Last_X=".POSX_SCORE.", " .
          "Status='$next_status', " .
          "Score={$this->score}, " .
          //"Last_Move='$Last_Move', " . //Not a move, re-use last one
          "Flags=$GameFlags, " . //Don't reset KO-Flag else SCORE,RESUME could break a Ko
          "Snapshot='" . GameSnapshot::make_game_snapshot($board_size, $this->board) . "', ";

      if( $next_status != GAME_STATUS_FINISHED )
         $this->game_query .= "ToMove_ID={$this->next_to_move_ID}, ";
      else
         $this->game_query .= "ToMove_ID=0, ";
   }//prepare_game_action_score


   public function prepare_game_action_generic()
   {
      global $Handicap, $Moves, $NOW;
      if( $this->is_quick && $this->game_row ) extract($this->game_row);

      if( $this->action != GAMEACT_DELETE )
      {
         if( $this->action != GAMEACT_RESIGN )
            $this->game_query .= $this->mp_query;
         $this->game_query .= $this->time_query . "Lastchanged=FROM_UNIXTIME($NOW)" ;

         if( $this->message )
         {
            $move_nr = ( $this->action == GAMEACT_SET_HANDICAP ) ? $Handicap : $Moves;
            $this->message_query =
               "INSERT INTO MoveMessages SET gid={$this->gid}, MoveNr=$move_nr, Text=\"{$this->message}\"";
         }
      }
   }//prepare_game_action_generic


   public function update_game( $dbgmsg )
   {
      global $tid, $Status, $GameType, $GamePlayers, $Black_ID, $White_ID, $Moves, $Rated,
             $ActivityMax, $ActivityForMove, $NOW, $ToMove_ID;
      $GameFlags = $this->game_row['GameFlags'];
      if( $this->is_quick && $this->game_row ) extract($this->game_row);

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

            clear_cache_quick_status( array( $ToMove_ID, $this->next_to_move_ID ), QST_CACHE_GAMES );
            GameHelper::delete_cache_status_games( "$dbgmsg.upd_moves3", $ToMove_ID, $this->next_to_move_ID );
            Board::delete_cache_game_moves( "$dbgmsg.upd_moves4", $this->gid );
         }

         if( $this->message_query )
         {
            $result = db_query( "$dbgmsg.upd_msg1", $this->message_query );
            if( mysql_affected_rows() < 1 && $this->action != GAMEACT_DELETE )
               error('mysql_insert_move', "$dbgmsg.upd_msg2");

            Board::delete_cache_game_move_messages( "$dbgmsg.upd_msg3", $this->gid );
         }

         if( $this->game_finished )
         {
            $game_finalizer = new GameFinalizer( ACTBY_PLAYER, $this->my_id, $this->gid, $tid, $Status,
               $GameType, $GamePlayers, $GameFlags, $Black_ID, $White_ID, $Moves, ($Rated != 'N') );

            $do_delete = ( $this->action == GAMEACT_DELETE );

            $game_finalizer->skip_game_query();
            $game_finalizer->finish_game( "confirm", $do_delete, null, $this->score, $this->message_raw );
         }
         else
            $do_delete = false;

         // Notify opponent about move
         if( !$do_delete )
            notify( "$dbgmsg.notify_opponent({$this->next_to_move_ID})", $this->next_to_move_ID );

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
   }//update_game

} //end 'GameActionHelper'

?>
