<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once 'include/conditional_moves.php';
require_once 'include/db/move_sequence.php';
require_once 'include/move.php';
require_once 'include/sgf_parser.php';
require_once 'include/std_classes.php';
require_once 'include/std_functions.php';
require_once 'include/game_functions.php';
require_once 'tournaments/include/tournament_globals.php';


/*!
 * \class GameActionHelper
 *
 * \brief Helper class to handle actions on game and processing conditional-moves:
 *
 *    - set handicap stones
 *    - play normal move
 *    - pass move
 *    - scoring steps
 *
 *    - resign game
 *    - delete game
 *
 * Example:
 *    $gah = new GameActionHelper( $my_id, $gid, false );
 *    $gah->set_game_action( GAMEACT_DO_MOVE );
 *    $game_row = $gah->load_game( 'my_loc' );
 *    list( $to_move ) = $gah->init_globals( 'my_loc' );
 *    $gah->load_game_conditional_moves( 'my_loc' );
 *    $board = $gah->load_game_board( 'my_loc' );
 *    $gah->init_query( 'my_loc' );
 *
 *    $gah->prepare_game_...(...);
 *
 *    $gah->process_game_action( 'my_loc' );
 */
class GameActionHelper
{
   private $my_id;
   private $gid;
   private $is_quick;
   private $is_cond_move = false; // false = play original move (with potential cond-moves); true = play cond-move
   private $action;

   private $game_updquery;
   private $game_clause = '';
   private $game_row = null;
   private $board = null;
   private $score = null;
   private $hours = 0;
   private $to_move = 0;
   private $next_to_move = 0;
   private $next_to_move_ID = 0;
   private $game_finished = false;
   private $notify_opponent = true;

   private $mmsg_updquery; // move-message
   private $message_raw = '';
   private $message = '';

   private static $MOVE_INSERT_QUERY = "INSERT INTO Moves ( gid, MoveNr, Stone, PosX, PosY, Hours ) VALUES ";
   private $move_query = ''; // (gid,MoveNr,Stone,PosX,PosY,Hours)

   private $cond_moves_activate_id = 0; // MoveSequence.ID to activate
   private $cond_moves_mseq = null; // MoveSequence-entry if there are cond-moves of opponent
   private $last_move = ''; // last-move for PASS|MOVE in format: "<COLOR><SGF_COORD>", e.g. 'B' (=B-pass), 'Wee'
   private $upd_game_row = array(); // filled with game_row-values (key=>val) to overwrite on game-update

   private static $MAP_COLORS = array( BLACK => 'B', WHITE => 'W' );


   /*!
    * Constructs GameActionHelper-object.
    * \param $is_quick set to true for usage by quick-do-suite and quick-play.
    */
   public function __construct( $my_id, $gid, $is_quick, $is_cond_move=false )
   {
      $this->my_id = $my_id;
      $this->gid = $gid;
      $this->is_quick = $is_quick;
      $this->is_cond_move = (bool)$is_cond_move;

      $this->game_updquery = new UpdateQuery('Games');
      $this->mmsg_updquery = new UpdateQuery('MoveMessages');
   }

   public function set_game_action( $action )
   {
      $this->action = $action;
   }

   public function set_game_finished( $game_finished )
   {
      $this->game_finished = $game_finished;
   }

   private function has_conditional_moves()
   {
      return ( $this->cond_moves_mseq instanceof MoveSequence );
   }


   /*! \brief Loads Games-table for this->gid, returning copy from game_row. */
   public function load_game( $dbgmsg )
   {
      $this->game_row = GameHelper::load_game_row( "$dbgmsg.load_game", $this->gid );
      return $this->game_row;
   }

   public function init_globals( $dbgmsg )
   {
      $dbgmsg .= ".GAH.init_globals({$this->gid})";
      if ( is_null($this->game_row) || !$this->action )
         error('assert', "$dbgmsg.miss_init0");

      $ToMove_ID = $this->game_row['ToMove_ID'];
      $Black_ID = $this->game_row['Black_ID'];
      $White_ID = $this->game_row['White_ID'];

      if ( $ToMove_ID == 0 )
         error('game_finished', "$dbgmsg.bad_ToMove_ID.gamend");
      if ( $Black_ID == $ToMove_ID )
         $this->to_move = BLACK;
      elseif ( $White_ID == $ToMove_ID )
         $this->to_move = WHITE;
      else
         error('database_corrupted', "$dbgmsg.check.to_move($ToMove_ID,$Black_ID,$White_ID)");

      // correct to_move on resign-if-not-your-turn
      if ( $this->action == GAMEACT_RESIGN && $this->my_id != $ToMove_ID )
         $this->to_move = WHITE + BLACK - $this->to_move;

      $this->next_to_move = WHITE + BLACK - $this->to_move;
      $this->next_to_move_ID = ( $this->next_to_move == BLACK ? $Black_ID : $White_ID );

      return array( $this->to_move );
   }//init_globals


   /*! \brief Loads conditional-moves for game to process. */
   public function load_game_conditional_moves( $dbgmsg )
   {
      $dbgmsg .= ".GAH.load_game_cm({$this->gid})";
      if ( is_null($this->game_row) || !$this->action )
         error('assert', "$dbgmsg.miss_init1");
      $Status = $this->game_row['Status'];
      $GameType = $this->game_row['GameType'];

      // check for conditional moves: only on move|pass (not handicap-setup, scoring, FK-negotiation), nor for MPG
      $this->cond_moves_mseq = null;
      $this->last_move = '';
      if ( ALLOW_CONDITIONAL_MOVES
            && ($this->action == GAMEACT_DO_MOVE || $this->action == GAMEACT_PASS ) && $GameType == GAMETYPE_GO
            && ($Status == GAME_STATUS_PLAY || $Status == GAMEACT_PASS) )
      {
         // load and parse active cond-moves from opponent
         $move_seq = MoveSequence::load_last_move_sequence( $this->gid, $this->next_to_move_ID, MSEQ_STATUS_ACTIVE );
         if ( !is_null($move_seq) )
         {
            $sgf_parser = new SgfParser( SGFP_OPT_SKIP_ROOT_NODE );
            if ( $sgf_parser->parse_sgf($move_seq->Sequence) )
            {
               $move_seq->parsed_game_tree =
                  ConditionalMoves::fill_conditional_moves_attributes( $sgf_parser->games[0], $move_seq->StartMoveNr );
               $this->cond_moves_mseq = $move_seq;
            }
         }
      }
   }//load_game_conditional_moves


   public function load_game_board( $dbgmsg )
   {
      $dbgmsg .= ".GAH.load_game_board({$this->gid})";
      if ( is_null($this->game_row) && is_null($this->action) )
         error('assert', "$dbgmsg.miss_init2");
      $Status = $this->game_row['Status'];

      $no_marked_dead = ( $this->action == GAMEACT_DO_MOVE || $Status == GAME_STATUS_PLAY || $Status == GAME_STATUS_PASS );
      $board_opts = ( $no_marked_dead ? 0 : BOARDOPT_MARK_DEAD );

      $board = new Board();
      if ( !$board->load_from_db( $this->game_row, 0, $board_opts) )
         error('internal_error', "$dbgmsg.load_from_db");
      $this->board = $board;

      return $board;
   }//load_game_board


   /*! \brief Inits query-parts and SQL-clauses for updating game-data. */
   public function init_query( $dbgmsg )
   {
      if ( is_null($this->game_row) || is_null($this->action) || $this->to_move == 0 )
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
      - the Games table modification must always modify the Moves field (see $game_updquery)
      - this modification is always done in first place and checked before continuation
      *********************** */
      $this->game_clause = " WHERE ID={$this->gid} AND Status".IS_RUNNING_GAME." AND Moves=$Moves LIMIT 1"; // $Moves changes

      // update clock
      list( $this->hours, $upd_clock ) =
         GameHelper::update_clock( $dbgmsg, $this->game_row, $this->to_move, $this->next_to_move );
      if ( $this->action != GAMEACT_DELETE )
      {
         $this->game_updquery->merge( $upd_clock ); // update clock
         $this->game_updquery->upd_time('Lastchanged');

         // init mp-query
         if ( $this->action != GAMEACT_RESIGN )
         {
            $is_mpgame = ( $this->game_row['GameType'] != GAMETYPE_GO );
            if ( $is_mpgame && ($this->action == GAMEACT_DO_MOVE || $this->action == GAMEACT_PASS
                  || $this->action == GAMEACT_SET_HANDICAP || $this->action == GAMEACT_SCORE) )
            {
               list( $group_color, $group_order, $gpmove_color ) =
                  MultiPlayerGame::calc_game_player_for_move(
                     $this->game_row['GamePlayers'], $Moves, $this->game_row['Handicap'], 2 );
               $mp_gp = GamePlayer::load_game_player( $this->gid, $group_color, $group_order );

               $dbfield = ( $this->game_row['ToMove_ID'] == $this->game_row['Black_ID'] ) ? 'Black_ID' : 'White_ID';
               $this->game_updquery->upd_num( $dbfield, $mp_gp->uid );
            }
         }
      }
   }//init_query


   /*!
    * \brief Strips away empty comments from message, replace move-tag and
    *    set game-flags if there are embedded hidden messages.
    */
   public function set_game_move_message( $message_raw )
   {
      if ( is_null($this->game_row) )
         error('assert', "$dbgmsg.GAH.init_query({$this->gid}).miss_init4");

      $this->message_raw = trim($message_raw);
      if ( preg_match( "/^<c>\s*<\\/c>$/si", $this->message_raw ) ) // remove empty comment-only tags
         $this->message_raw = '';

      $this->message = replace_move_tag( $this->message_raw, $this->gid );

      if ( $this->message && preg_match( "#</?h(idden)?>#is", $this->message) )
         $this->game_row['Flags'] |= GAMEFLAGS_HIDDEN_MSG;
   }//prepare_game_move_message


   public function increase_moves()
   {
      $this->game_row['Moves']++;
   }

   public function get_moves()
   {
      return $this->game_row['Moves'];
   }


   public function prepare_conditional_moves_activation( $cm_activate_id )
   {
      if ( ALLOW_CONDITIONAL_MOVES && $cm_activate_id >= 0 )
         $this->cond_moves_activate_id = (int)$cm_activate_id;
   }

   /*!
    * \brief Sets handicap-stones for game using either $orig_stonestring OR $quick_moves depending on $this->is_quick.
    * \param $orig_stonestring set if used by non-quick-suite (e.g. confirm-page)
    * \param $quick_moves must be set for quick-suite; ignored for non-quick-suite
    */
   public function prepare_game_action_set_handicap( $dbgmsg, $orig_stonestring, $quick_moves )
   {
      $Moves = $this->get_moves();
      $Handicap = $this->game_row['Handicap'];
      $Status = $this->game_row['Status'];
      $White_ID = $this->game_row['White_ID'];
      $Flags = $this->game_row['Flags'];
      $Size = $this->game_row['Size'];
      $dbgmsg .= ".GAH.prepare_game_action_set_handicap({$this->gid},{$this->action},$Status,$Moves,$Handicap)";

      if ( $this->is_quick )
         $this->board->add_handicap_stones( $quick_moves ); // check coords
      else
      {
         if ( $Status != GAME_STATUS_PLAY || !( $Handicap > 1 && $Moves == 1 ) )
            error('invalid_action', "$dbgmsg.check_status");

         $stonestring = check_handicap( $this->board, $orig_stonestring );
         if ( strlen($stonestring) != 2 * $Handicap )
            error('wrong_number_of_handicap_stone', "$dbgmsg.check.handicap($stonestring)");

         $quick_moves = array();
         for ( $i=1; $i <= $Handicap; $i++ )
         {
            list( $x, $y ) = sgf2number_coords( substr($stonestring, $i*2-2, 2), $Size );
            if ( !isset($x) || !isset($y) )
               error('illegal_position', "$dbgmsg.check_pos(#$i,$x,$y)");
            else
               $quick_moves[] = array( $x, $y );
         }
      }

      for ( $i=1; $i <= $Handicap; $i++ )
      {
         list( $x, $y ) = $quick_moves[$i-1];
         $this->move_query .= // (gid,MoveNr,Stone,PosX,PosY,Hours)
            "({$this->gid}, $i, ".BLACK.", $x, $y, " . ($i == $Handicap ? "{$this->hours})" : "0), " );
      }

      $this->game_updquery->upd_num('Moves', $Handicap);
      $this->game_updquery->upd_num('Last_X', $x);
      $this->game_updquery->upd_num('Last_Y', $y);
      $this->game_updquery->upd_txt('Last_Move', number2sgf_coords($x, $y, $Size) );
      $this->game_updquery->upd_num('Flags', $Flags);
      $this->game_updquery->upd_txt('Snapshot', GameSnapshot::make_game_snapshot($Size, $this->board) );
      $this->game_updquery->upd_num('ToMove_ID', $White_ID);
   }//prepare_game_action_set_handicap


   /*!
    * \brief Executes normal "move" (placing of a stone) on the board.
    * \param $move_coord arr(x,y) OR sgf-coord of move
    * \param $chk_stonestring if given check against calculated prisonerstring
    */
   public function prepare_game_action_do_move( $dbgmsg, $move_coord, $chk_stonestring='' )
   {
      $Last_Move = $this->game_row['Last_Move'];
      $Last_X = $this->game_row['Last_X'];
      $Last_Y = $this->game_row['Last_Y'];
      $Size = $this->game_row['Size'];
      $Black_Prisoners = $this->game_row['Black_Prisoners'];
      $White_Prisoners = $this->game_row['White_Prisoners'];
      $Moves = $this->get_moves();
      $Flags = $this->game_row['Flags'];
      $dbgmsg .= ".GAH.prepare_game_action_do_move({$this->gid},{$this->action})";

      {//to fix the old way Ko detect. Could be removed when no more old way games.
         if ( !@$Last_Move ) $Last_Move = number2sgf_coords($Last_X, $Last_Y, $Size);
      }
      $gchkmove = new GameCheckMove( $this->board );
      $gchkmove->check_move( $move_coord, $this->to_move, $Last_Move, $Flags );
      $gchkmove->update_prisoners( $Black_Prisoners, $White_Prisoners );

      $prisoner_string = '';
      foreach ( $gchkmove->prisoners as $coord )
      {
         list( $x, $y ) = $coord;
         $this->move_query .= // (gid,MoveNr,Stone,PosX,PosY,Hours)
            "({$this->gid}, $Moves, ".NONE.", $x, $y, 0), "; // gid,MoveNr,Stone,PosX,PosY,Hours
         $prisoner_string .= number2sgf_coords($x, $y, $Size);
      }

      if ( ( strlen($prisoner_string) != $gchkmove->nr_prisoners*2 )
            || ( $chk_stonestring && $prisoner_string != $chk_stonestring) )
         error('move_problem', "$dbgmsg.prisoner({$gchkmove->nr_prisoners},$prisoner_string,$chk_stonestring)");

      $this->move_query .= // (gid,MoveNr,Stone,PosX,PosY,Hours)
         "({$this->gid}, $Moves, {$this->to_move}, {$gchkmove->colnr}, {$gchkmove->rownr}, {$this->hours}) ";

      if ( $gchkmove->nr_prisoners == 1 )
         $Flags |= GAMEFLAGS_KO;
      else
         $Flags &= ~GAMEFLAGS_KO;

      $upd_Last_Move = number2sgf_coords($gchkmove->colnr, $gchkmove->rownr, $Size);
      $upd_Status = GAME_STATUS_PLAY;

      $this->game_updquery->upd_num('Moves', $Moves);
      $this->game_updquery->upd_num('Last_X', $gchkmove->colnr); //used with mail notifications
      $this->game_updquery->upd_num('Last_Y', $gchkmove->rownr);
      $this->game_updquery->upd_txt('Last_Move', $upd_Last_Move); //used to detect Ko
      $this->game_updquery->upd_txt('Status', $upd_Status);
      $this->game_updquery->upd_num('Flags', $Flags);
      $this->game_updquery->upd_txt('Snapshot', GameSnapshot::make_game_snapshot($Size, $this->board) );
      $this->game_updquery->upd_num('ToMove_ID', $this->next_to_move_ID);
      if ( $gchkmove->nr_prisoners > 0 )
      {
         if ( $this->to_move == BLACK )
            $this->game_updquery->upd_num('Black_Prisoners', $Black_Prisoners);
         else
            $this->game_updquery->upd_num('White_Prisoners', $White_Prisoners);
      }

      // needed updates for potential conditional-moves
      $sgf_coord = ( is_array($move_coord) ) ? number2sgf_coords($move_coord[0], $move_coord[1], $Size) : $move_coord;
      $this->last_move = ( ($this->to_move == BLACK) ? 'B' : 'W' ) . $sgf_coord;
      $this->upd_game_row['Flags'] = $Flags;
      $this->upd_game_row['Last_Move'] = $upd_Last_Move;
      $this->upd_game_row['Status'] = $upd_Status;
   }//prepare_game_action_do_move


   /*! \brief Executes PASS-"move" on the board. */
   public function prepare_game_action_pass( $dbgmsg )
   {
      $Moves = $this->get_moves();
      $Handicap = $this->game_row['Handicap'];
      $Status = $this->game_row['Status'];
      //$Last_Move = $this->game_row['Last_Move'];
      $Flags = $this->game_row['Flags'];
      $dbgmsg .= ".GAH.prepare_game_action_pass({$this->gid},{$this->action},$Status,$Moves,$Handicap)";

      if ( $Moves < $Handicap )
         error('early_pass', "$dbgmsg.check.moves");

      if ( $Status == GAME_STATUS_PLAY )
         $next_status = GAME_STATUS_PASS;
      else if ( $Status == GAME_STATUS_PASS )
         $next_status = GAME_STATUS_SCORE;
      else
         error('invalid_action', "$dbgmsg.check_status");

      $this->move_query .= // (gid,MoveNr,Stone,PosX,PosY,Hours)
         "({$this->gid}, $Moves, {$this->to_move}, ".POSX_PASS.", 0, {$this->hours})";

      $this->game_updquery->upd_num('Moves', $Moves);
      $this->game_updquery->upd_num('Last_X', POSX_PASS);
      $this->game_updquery->upd_txt('Status', $next_status);
      //$this->game_updquery->upd_txt('Last_Move', $Last_Move); //Not a move, re-use last one
      $this->game_updquery->upd_num('Flags', $Flags); //Don't reset KO-Flag else PASS,PASS,RESUME could break a Ko
      $this->game_updquery->upd_num('ToMove_ID', $this->next_to_move_ID);

      // needed updates for potential conditional-moves
      $this->last_move = ($this->to_move == BLACK) ? 'B' : 'W'; // ''=pass-move
      $this->upd_game_row['Status'] = $next_status;
   }//prepare_game_action_pass


   /*! \brief Resigns game by logged-in player. */
   public function prepare_game_action_resign( $dbgmsg )
   {
      $Moves = $this->get_moves();
      $Flags = $this->game_row['Flags'];
      $dbgmsg .= ".GAH.prepare_game_action_resign({$this->gid},{$this->action})";

      $this->score = ( $this->to_move == BLACK ) ? SCORE_RESIGN : -SCORE_RESIGN;

      $this->move_query .= // (gid,MoveNr,Stone,PosX,PosY,Hours)
         "({$this->gid}, $Moves, {$this->to_move}, ".POSX_RESIGN.", 0, {$this->hours})";

      $this->game_updquery->upd_num('Moves', $Moves);
      $this->game_updquery->upd_num('Last_X', POSX_RESIGN);
      $this->game_updquery->upd_txt('Status', GAME_STATUS_FINISHED);
      $this->game_updquery->upd_num('Flags', $Flags);
      $this->game_updquery->upd_num('ToMove_ID', 0);
      $this->game_updquery->upd_num('Score', $this->score);

      $this->game_finished = true;
   }//prepare_game_action_resign


   /*!
    * \brief Executes 1st and 2nd scoring-steps for the board depend on current game-state.
    * \param $toggle_uniq see quick-game-handler GAMEOPTVAL_TOGGLE_UNIQUE
    * \param $agree ignored for non-quick-suite; boolean value
    * \param $arr_coords ignored for non-quick-suite; use false | array of coord-moves [(x,y), ...]
    */
   public function prepare_game_action_score( $dbgmsg, $stonestring, $toggle_uniq=false, $agree=true, $arr_coords=false )
   {
      $Moves = $this->get_moves();
      $Handicap = $this->game_row['Handicap'];
      $Komi = $this->game_row['Komi'];
      $Black_Prisoners = $this->game_row['Black_Prisoners'];
      $White_Prisoners = $this->game_row['White_Prisoners'];
      $Ruleset = $this->game_row['Ruleset'];
      $Status = $this->game_row['Status'];
      $Size = $this->game_row['Size'];
      $Flags = $this->game_row['Flags'];
      $dbgmsg .= ".GAH.prepare_game_action_score({$this->gid},{$this->action})";

      if ( !$this->is_quick )
         $arr_coords = false;

      $gchkscore = new GameCheckScore( $this->board, $stonestring, $Handicap, $Komi, $Black_Prisoners, $White_Prisoners );
      if ( $toggle_uniq )
         $gchkscore->set_toggle_unique();
      $game_score = $gchkscore->check_remove( $Ruleset, $arr_coords );
      $gchkscore->update_stonestring( $stonestring );
      $this->score = $game_score->calculate_score();

      $l = strlen($stonestring);
      if ( $Status == GAME_STATUS_SCORE2 && $l < 2 )
      {
         if ( $this->is_quick && !$agree )
            error('invalid_action', "$dbgmsg.expect_agree($agree)");
         $next_status = GAME_STATUS_FINISHED;
         $this->game_finished = true;
      }
      else
      {
         if ( $this->is_quick && $l < 2 && !$agree )
            error('invalid_action', "$dbgmsg.no_dispute_but_miss_agree($agree)");
         elseif ( $this->is_quick && $l >=2 && $agree )
            error('invalid_action', "$dbgmsg.dispute_expect_disagree($agree)");
         $next_status = GAME_STATUS_SCORE2;
      }

      $mark_stone = ( $this->to_move == BLACK ) ? MARKED_BY_BLACK : MARKED_BY_WHITE;
      for ( $i=0; $i < $l; $i += 2 )
      {
         list($x,$y) = sgf2number_coords(substr($stonestring, $i, 2), $Size);
         $this->move_query .= // (gid,MoveNr,Stone,PosX,PosY,Hours)
            "({$this->gid}, $Moves, $mark_stone, $x, $y, 0), ";
      }
      $this->move_query .= "({$this->gid}, $Moves, {$this->to_move}, ".POSX_SCORE.", 0, {$this->hours}) ";

      $this->game_updquery->upd_num('Moves', $Moves);
      $this->game_updquery->upd_num('Last_X', POSX_SCORE);
      $this->game_updquery->upd_txt('Status', $next_status);
      //$this->game_updquery->upd_txt('Last_Move', $Last_Move); //Not a move, re-use last one
      $this->game_updquery->upd_num('Flags', $Flags);
      $this->game_updquery->upd_txt('Snapshot', GameSnapshot::make_game_snapshot($Size, $this->board) );
      $this->game_updquery->upd_num('Score', $this->score);
      $this->game_updquery->upd_num('ToMove_ID',
         ( $next_status != GAME_STATUS_FINISHED ) ? $this->next_to_move_ID : 0 );
   }//prepare_game_action_score


   public function prepare_game_action_generic()
   {
      $Moves = $this->get_moves();
      $Handicap = $this->game_row['Handicap'];
      $Komi = $this->game_row['Komi'];

      if ( $this->action != GAMEACT_DELETE && (string)$this->message != '' )
      {
         $this->mmsg_updquery->upd_num('gid', $this->gid);
         $this->mmsg_updquery->upd_num('MoveNr',
            ( $this->action == GAMEACT_SET_HANDICAP ) ? $Handicap : $Moves );
         $this->mmsg_updquery->upd_txt('Text', $this->message);
      }
   }//prepare_game_action_generic


   /*! \brief Updates game-action and processes conditional-moves. */
   public function process_game_action( $dbgmsg )
   {
      ta_begin();
      {//HOT-section to process game-action
         $this->activate_conditional_moves( $dbgmsg );

         $this->update_game( $dbgmsg );

         $last_gah = $this->process_conditional_moves( $dbgmsg );

         $gah = ( is_null($last_gah) ) ? $this : $last_gah;
         $gah->process_post_action( $dbgmsg ); // move-notify opponent of last cond-move or originally submitted move
      }
      ta_end();
   }//process_game_action

   private function activate_conditional_moves( $dbgmsg )
   {
      if ( $this->cond_moves_activate_id <= 0 )
         return false;

      return MoveSequence::activate_move_sequence( $dbgmsg, $this->cond_moves_activate_id, $this->gid, $this->my_id );
   }//activate_conditional_moves

   /*!
    * \brief Executes SQL-statements prepared by this->prepare_game_action_...()-functions for the game.
    * \note updating Games-table, insert moves/prisoners and move-message;
    *       may delete or finish game, update players-stats and moves-stats
    *
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
   private function update_game( $dbgmsg )
   {
      global $ActivityMax, $ActivityForMove, $NOW;
      $Moves = $this->get_moves();
      $tid = $this->game_row['tid'];
      $Status = $this->game_row['Status'];
      $GameType = $this->game_row['GameType'];
      $GamePlayers = $this->game_row['GamePlayers'];
      $Black_ID = $this->game_row['Black_ID'];
      $White_ID = $this->game_row['White_ID'];
      $Rated = $this->game_row['Rated'];
      $ToMove_ID = $this->game_row['ToMove_ID'];
      $Flags = $this->game_row['Flags'];
      $dbgmsg .= ".GAH.update_game({$this->gid},{$this->action})";

      //See *** HOT_SECTION *** above in previous preparation-calls
      if ( $this->game_updquery->has_updates() )
      {
         $result = db_query( "$dbgmsg.upd_game1",
            "UPDATE Games SET " . $this->game_updquery->get_query() . $this->game_clause );
         if ( mysql_affected_rows() != 1 )
            error('mysql_update_game', "$dbgmsg.upd_game2");

         foreach ( $this->upd_game_row as $key => $value ) // update game-values for conditional-moves processing
            $this->game_row[$key] = $value;

         GameHelper::delete_cache_game_row( "$dbgmsg.upd_game3", $this->gid );
      }

      if ( $this->move_query )
      {
         $result = db_query( "$dbgmsg.upd_moves1", self::$MOVE_INSERT_QUERY . $this->move_query );
         if ( mysql_affected_rows() < 1 && $this->action != GAMEACT_DELETE )
            error('mysql_insert_move', "$dbgmsg.upd_moves2");

         clear_cache_quick_status( array( $ToMove_ID, $this->next_to_move_ID ), QST_CACHE_GAMES );
         GameHelper::delete_cache_status_games( "$dbgmsg.upd_moves3", $ToMove_ID, $this->next_to_move_ID );
         Board::delete_cache_game_moves( "$dbgmsg.upd_moves4", $this->gid );
      }

      if ( $this->mmsg_updquery->has_updates() )
      {
         $result = db_query( "$dbgmsg.upd_msg1",
            "INSERT INTO MoveMessages SET " . $this->mmsg_updquery->get_query() );
         if ( mysql_affected_rows() < 1 && $this->action != GAMEACT_DELETE )
            error('mysql_insert_move', "$dbgmsg.upd_msg2");

         Board::delete_cache_game_move_messages( "$dbgmsg.upd_msg3", $this->gid );
      }

      if ( $this->game_finished )
      {
         $game_finalizer = new GameFinalizer( ACTBY_PLAYER, $this->my_id, $this->gid, $tid, $Status,
            $GameType, $GamePlayers, $Flags, $Black_ID, $White_ID, $Moves, ($Rated != 'N'),
            $this->game_row['Black_Start_Rating'], $this->game_row['White_Start_Rating'] );

         $do_delete = ( $this->action == GAMEACT_DELETE );
         $this->notify_opponent = !$do_delete;

         $game_finalizer->skip_game_query();
         $game_finalizer->finish_game( "confirm", $do_delete, null, $this->score, $this->message_raw );
      }
      else
         $this->notify_opponent = true;


      // Update TournamentParticipant-fields
      if ( ALLOW_TOURNAMENTS && $tid > 0 )
      {
         // set last-moved, reset timeout-loss-flag on own move-action, decrease PenaltyPoints
         db_query( "$dbgmsg.upd_tp.lastmoved($tid,{$this->my_id})",
            "UPDATE TournamentParticipant SET Lastmoved=FROM_UNIXTIME($NOW), Flags=Flags & ~" .TP_FLAG_TIMEOUT_LOSS .
               ", PenaltyPoints=PenaltyPoints-1 " .
            "WHERE tid=$tid AND Status='".TP_STATUS_REGISTER."' AND uid={$this->my_id} LIMIT 1" );
      }

      // Increase moves and activity
      $upd = new UpdateQuery('Players');
      $upd->upd_raw('Moves', 'Moves+1' ); // NOTE: count also delete + set-handicap as one move
      if ( $this->is_quick && !$this->is_cond_move )
      {
         $upd->upd_raw('Lastaccess', "GREATEST(Lastaccess,FROM_UNIXTIME($NOW))" ); // update too on quick-suite access
         $upd->upd_time('LastQuickAccess', $NOW );
      }
      if ( !$this->is_cond_move )
         $upd->upd_raw('Activity', "LEAST($ActivityMax,$ActivityForMove+Activity)" );
      $upd->upd_time('LastMove', $NOW );
      db_query( "$dbgmsg.activity({$this->my_id})",
         "UPDATE Players SET " . $upd->get_query() . " WHERE ID={$this->my_id} LIMIT 1" );

      if ( !$this->is_cond_move )
         increaseMoveStats( $this->my_id );
   }//update_game

   /*! \brief Notifies opponent about move for last submitted move without following cond-move. */
   private function process_post_action( $dbgmsg )
   {
      $dbgmsg .= ".GAH.process_post_action({$this->gid},{$this->action})";

      // Notify opponent about move
      if ( $this->notify_opponent )
         notify( "$dbgmsg.notify_opponent({$this->next_to_move_ID})", $this->next_to_move_ID );
   }//process_post_action


   /*!
    * \brief Processes conditional-moves.
    * \return GameActionHelper-instance that needs post-processing (notifying opponent, etc);
    *       null = no post-processing for cond-moves needed (but for original submitted move)
    *
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    * \note implementation should not use recursion!
    */
   private function process_conditional_moves( $dbgmsg )
   {
      $dbgmsg .= ".GAH.process_cm";

      /*
      How are conditional-moves processed?
      - a move LM (=last-move) is submitted by a player

      - after the move (PASS or board-move) is executed and saved after the last call of update_game()
        this method 'process_conditional_moves()' is called starting a loop:

         - stop if there is no last-move (only set by a previously PASS or MOVE execution)
           or if there are no active conditional-moves of the opponent

         - find the last-move (context) in the parsed conditional-moves and get the next-move;
           (because there are no db-transactions, saving of previous cond-moves could have failed;
            so this "search" can also pick up from previous failed updates of the conditional-moves)

         - if there is a next-move, play it using a new GameActionHelper-instance, which in turn
           loads conditional-moves of the "new" opponent

         - to avoid recursion, after update_game() called in play_conditional_move() on the new instance,
           process_conditional_moves() is not called again, but the GameActionHelper-instance is saved
           and used in the next loop-run

         - after the move is played, the changed state of the conditional-moves are saved

         - as last step the opponent needs to be notified about the last submitted (original or conditional) move,
           though this notification should be done by the caller-function of this function here

      - this process reloads all data (Games-table, all moves, cond-moves), because it's very error-prone
        to correctly change the inner state of all objects (e.g. Board, Games-row) to be able to correctly
        process consecutive move-execution (e.g. when both player have conditional-moves in place).

        The other reason for this approach (SAVE(!) + reload after EACH move) is, because DGS has no real
        db-transactions over multiple tables due to the MyISAM-engine used for the db-tables.  If it would
        be done in one "save"-section (with different tables written), the inconsistencies in case
        of a db-failure would be much more difficult to sort out and fix.
      */

      // GameActionHelper to process on
      $curr_gah = (ALLOW_CONDITIONAL_MOVES) ? $this : null;
      $last_gah = null;

      while ( $curr_gah && $curr_gah->last_move && $curr_gah->has_conditional_moves() )
      {
         $next_sgf_move = $curr_gah->prepare_conditional_moves( $dbgmsg ); // SGF-coord | '' (=PASS)

         // play cond-move if one found
         if ( !is_null($next_sgf_move) )
         {
            $next_gah = $curr_gah->play_conditional_move( $dbgmsg, $next_sgf_move );
            $last_gah = $next_gah;
         }
         else
            $next_gah = null;

         $curr_gah->cond_moves_mseq->update(); // save updated CM (after move played)
         $curr_gah = $next_gah;
      }

      return $last_gah;
   }//process_conditional_moves

   /*! \brief Finds and returns next-move in conditional-moves; or else null. */
   private function prepare_conditional_moves( $dbgmsg )
   {
      // find last-move in cond-moves by "replaying" to get next-move
      $game_move_nr = $this->cond_moves_mseq->StartMoveNr;
      $last_move_nr = $this->get_moves();
      if ( $game_move_nr > $last_move_nr ) // shouldn't happen (as 1st CM-move is at least context-move to start from)
      {
         $this->update_cm_move_sequence( MSEQ_STATUS_ILLEGAL, null, MSEQ_ERR_MISS_CONTEXT_MOVE );
         return null;
      }

      // traverse game-tree of cond-moves searching last game-move
      $board_moves = $this->build_board_moves( $game_move_nr );
      $next_game_tree = $this->cond_moves_mseq->parsed_game_tree;
      $last_move_node = $prev_move_node = null;
      $cm_deviated = false;
      while ( $next_game_tree )
      {
         $game_tree = $next_game_tree;
         $next_game_tree = null;

         reset($game_tree->nodes);
         while ( $arr_each = each($game_tree->nodes) )
         {
            list( $id, $node ) = $arr_each; // $node is SgfNode
            $game_move = @$board_moves[$game_move_nr];

            if ( $game_move_nr == $node->move_nr && $game_move == $node->sgf_move )
            {
               if ( $game_move_nr == $last_move_nr ) // found last-move
               {
                  $last_move_node = $node;
                  break 2;
               }
            }
            else // move from game and CM not matching
            {
               $last_move_node = $prev_move_node;
               $cm_deviated = true;
               break 2;
            }

            $prev_move_node = $node;
            $game_move_nr++;
         }//nodes-end

         // find correct variation from list with matching 1st CM-move
         foreach ( $game_tree->vars as $sub_tree )
         {
            $sub_node = $sub_tree->get_first_node();
            if ( !is_null($sub_node) ) // safety-check (shouldn't happen as empty var is forbidden)
            {
               if ( $game_move_nr == $sub_node->move_nr && $game_move == $sub_node->sgf_move )
               {
                  $next_game_tree = $sub_tree; // found variation with matching move from CM-node
                  break;
               }
            }
         }

         if ( is_null($next_game_tree) ) // no vars with matching move
         {
            $last_move_node = $prev_move_node;
            $cm_deviated = true;
            break;
         }//else: found game-tree with matching move, so continue last-move-search in found game-tree
      }//end-next-game-tree


      $next_move_node = null;
      if ( is_null($last_move_node) ) // something wrong in CM
      {
         $this->update_cm_move_sequence( MSEQ_STATUS_ILLEGAL, null, MSEQ_ERR_NO_LAST_MOVE );
      }
      else if ( $cm_deviated ) // last-move found, but then deviation in CM detected
      {
         $this->update_cm_move_sequence( MSEQ_STATUS_DEVIATED, $last_move_node );
      }
      else // last-move found, so now get next move
      {
         $arr_each = each($game_tree->nodes); // get next node from variation, or false
         $has_vars = $game_tree->has_vars();
         if ( $arr_each || $has_var )
         {
            $next_move_node = ( $arr_each ) ? $arr_each[1] : 0;
            if ( $has_var || $next_move_node->move_nr != $last_move_nr + 1 ) // something wrong in CM
            {
               $this->update_cm_move_sequence( MSEQ_STATUS_ILLEGAL, null,
                  ( ( $has_var && !$arr_each ) ? MSEQ_ERR_NEXT_MOVE_VARNODE : MSEQ_ERR_NEXT_MOVE_BAD_MOVE_NR ) );
               $next_move_node = null;
            }
         }
         else // variation has no more nodes (=no next move), so CM is done
         {
            $this->update_cm_move_sequence( MSEQ_STATUS_DONE, $last_move_node );
         }
      }

      $next_move = $this->validate_next_conditional_move( $next_move_node );
      if ( !is_null($next_move) && !is_null($next_move_node) ) // next-move is valid
      {
         if ( $this->mmsg_updquery->has_updates() ) // stop if opponent submitted move-comment
         {
            // don't submit CM-move to let opponent see move-comment
            $next_move = $next_move_node = null;
            $this->update_cm_move_sequence( MSEQ_STATUS_OPP_MSG, $next_move_node );
         }
         else
         {
            if ( each($game_tree->nodes) === false && !$game_tree->has_vars() ) // variation end reached
               $this->update_cm_move_sequence( MSEQ_STATUS_DONE, $next_move_node );
            else // keep previous ACTIVE-status
               $this->update_cm_move_sequence( null, $next_move_node );
         }
      }

      return $next_move; // next-move: null (=no next-move), '' (=pass), or sgf-coord
   }//prepare_conditional_moves


   /*!
    * \brief Builds moves-array for processing conditional-moves in format "<B|W><SGF_COORD|''>" for all game-moves
    *       including last submitted move.
    */
   private function build_board_moves( $start_move_nr )
   {
      $size = $this->game_row['Size'];
      $last_move_nr = $this->get_moves();

      $board_moves = array();
      foreach ( $this->board->moves as $move_nr => $arr_move )
      {
         if ( !is_numeric($move_nr) || $move_nr < $start_move_nr )
            continue;

         list( $stone, $x, $y ) = $arr_move;
         if ( ($color = @self::$MAP_COLORS[$stone]) )
         {
            if ( $x == POSX_PASS )
               $board_moves[$move_nr] = $color;
            elseif ( $x >= 0 )
               $board_moves[$move_nr] = $color . number2sgf_coords( $x, $y, $size );
         }
      }
      $board_moves[$last_move_nr] = $this->last_move;

      return $board_moves;
   }//build_board_moves

   /*!
    * \brief Checks if next-move from conditional-moves is legal (expected move-color, legal move on board).
    * \note Needs this->game_row['Status/Flags/Last_Move'] updated from previous move|PASS-action,
    *       see prepare_game_action_pass() and prepare_game_action_do_move()
    */
   private function validate_next_conditional_move( $next_move_node )
   {
      if ( is_null($next_move_node) || !($next_move_node instanceof SgfNode) )
         return null;

      $color_next_move = $next_move_node->sgf_move[0];
      $coord_next_move = substr( $next_move_node->sgf_move, 1 );

      // check move-color
      $cm_error = 0;
      if ( $color_next_move != self::$MAP_COLORS[$this->next_to_move] ) // wrong color
         $cm_error = MSEQ_ERR_NEXT_MOVE_COLOR_MISMATCH;
      else
      {
         // check move-validity
         $Status = $this->game_row['Status'];
         if ( $coord_next_move == '' ) // PASS
         {
            // NOTE: similar check as in prepare_game_action_pass()
            if ( $Status != GAME_STATUS_PLAY && $Status != GAME_STATUS_PASS )
               $cm_error = MSEQ_ERR_ILLEGAL_STATE_PASS_MOVE;
         }
         else
         {
            // NOTE: similar check as in prepare_game_action_do_move()
            $Last_Move = $this->game_row['Last_Move'];
            $Flags = (int)$this->game_row['Flags'];

            $gchkmove = new GameCheckMove( $this->board ); // board->array already contains last-move
            $err = $gchkmove->check_move( $coord_next_move, $this->next_to_move, $Last_Move, $Flags, /*die*/false );
            $cm_error = MoveSequence::get_check_move_error_code( $err );
         }
      }

      if ( $cm_error )
      {
         // don't submit illegal CM-next-move
         $next_move = null;
         $this->update_cm_move_sequence( MSEQ_STATUS_ILLEGAL, $next_move_node, $cm_error );
      }
      else
         $next_move = $coord_next_move;

      return $next_move;
   }//validate_next_conditional_move

   private function update_cm_move_sequence( $mseq_status, $node=null, $error_code=0 )
   {
      if ( !is_null($mseq_status) )
         $this->cond_moves_mseq->setStatus( $mseq_status );
      if ( $node instanceof SgfNode )
         $this->cond_moves_mseq->set_last_move_info( $node->move_nr, $node->pos, $node->sgf_move );
      if ( $error_code >= 0 )
         $this->cond_moves_mseq->ErrorCode = (int)$error_code;
   }


   /*!
    * \brief Executes next-move from conditional-move-sequence without (recursive) call of processing consecutive
    *       conditional-moves, which is handled at callers site.
    * \param $next_move_coord '' (=PASS), or else sgf-coord
    * \return new GameActionHelper-instance, that processed next-move execution of cond-move
    */
   private function play_conditional_move( $dbgmsg, $next_move_coord )
   {
      $is_pass_move = ( $next_move_coord == '' );

      $gah = new GameActionHelper( $this->next_to_move_ID, $this->gid, $this->is_quick, /*is-CM*/true );
      $gah->set_game_action( ( $is_pass_move ? GAMEACT_PASS : GAMEACT_DO_MOVE ) );
      $gah->load_game( $dbgmsg );
      $gah->init_globals( $dbgmsg );
      $gah->load_game_conditional_moves( $dbgmsg );

      $gah->load_game_board( $dbgmsg );
      $gah->init_query( $dbgmsg );
      $gah->increase_moves();

      if ( $is_pass_move )
         $gah->prepare_game_action_pass( $dbgmsg );
      else
         $gah->prepare_game_action_do_move( $dbgmsg, $next_move_coord );

      $gah->prepare_game_action_generic();

      // NOTE: cond-moves are processed in outer loop to prevent recursion, so don't use process_game_action() here
      $gah->update_game( $dbgmsg );

      return $gah;
   }//play_conditional_move


   // ------------ static functions ----------------------------

   /*! \brief Returns arr( score, GameScore-object ), modified $board and $stonestring accordingly to calculate game-score. */
   public static function calculate_game_score( &$board, &$stonestring, $ruleset, $coord=false )
   {
      global $Handicap, $Komi, $Black_Prisoners, $White_Prisoners;

      $gchkscore = new GameCheckScore( $board, $stonestring, $Handicap, $Komi, $Black_Prisoners, $White_Prisoners );
      $game_score = $gchkscore->check_remove( $ruleset, $coord );
      $gchkscore->update_stonestring( $stonestring );
      $score = $game_score->calculate_score();

      return array( $score, $game_score );
   }//calculate_game_score

} //end 'GameActionHelper'

?>
