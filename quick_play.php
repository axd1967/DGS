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

require_once( "include/std_functions.php" );
require_once( "include/game_functions.php" );
require_once( "include/game_actions.php" );
require_once( "include/board.php" );
require_once( "include/move.php" );
//require_once( "include/rating.php" );

$TheErrors->set_mode(ERROR_MODE_PRINT);


if( $is_down )
{
   warning('server_down', str_replace("\n", "  ", $is_down_message));
}
else
{
   disable_cache();

   $gid = (int)@$_REQUEST['gid'] ;
   if( $gid <= 0 )
      error('unknown_game', "quick_play($gid)");

   connect2mysql();

   // login, set timezone, quota-check, login-denied-check
   $logged_in = who_is_logged( $player_row, LOGIN_UPD_ACTIVITY | LOGIN_QUICK_SUITE | LOGIN_QUICK_PLAY );
   if( !$logged_in )
      error('not_logged_in', 'quick_play.check.login');
   if( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'quick_play');

   $uhandle = $player_row['Handle'];
   $my_id = $player_row['ID'];

   $game_row = GameHelper::load_game_row( 'quick_play.find_game', $gid );
   $Last_X = $Last_Y = -1;
   extract($game_row);

   if( $Status == GAME_STATUS_FINISHED )
      error('game_finished', "quick_play.check_status.finished($gid)");
   elseif( !isRunningGame($Status) )
      error('game_not_started', "quick_play.check_status.bad($gid,$Status)");

   if( $ToMove_ID == 0 )
      error('game_finished', "quick_play.bad_ToMove_ID.gamend($gid)");
   if( $Black_ID == $ToMove_ID )
      $to_move = BLACK;
   else if( $White_ID == $ToMove_ID )
      $to_move = WHITE;
   else
      error('database_corrupted', "quick_play.check_tomove($gid,$ToMove_ID,$Black_ID,$White_ID)");

   if( $Moves < $Handicap )
      error('invalid_action', "quick_play.check.set_handicap.not_supported($gid,$my_id,$Moves,$Handicap)");

   if( $my_id != $ToMove_ID )
      error('not_your_turn', "quick_play.check_tomove2($gid,$ToMove_ID)");

   if( $Status != GAME_STATUS_PLAY //exclude SCORE,PASS steps and KOMI,SETUP,INVITED,FINISHED
         || !number2sgf_coords( $Last_X, $Last_Y, $Size) //exclude first move and previous moves like pass,resume...
         || ($Handicap>1 && $Moves<=$Handicap) ) //exclude first white move after handicap stones
   {
      error('invalid_action', "quick_play.check_status.play($gid,$Status)");
   }



   //See *** HOT_SECTION *** below
   if( isset($_REQUEST['sgf_move']) )
      list( $query_X, $query_Y) = sgf2number_coords($_REQUEST['sgf_move'], $Size);
   elseif( isset($_REQUEST['board_move']) )
      list( $query_X, $query_Y) = board2number_coords($_REQUEST['board_move'], $Size);
   else
      list( $query_X, $query_Y) = array( NULL, NULL);

   if( is_null($query_X) || is_null($query_Y) )
      error('illegal_position', "quick_play.err1($gid)");

   if( isset($_REQUEST['sgf_prev']) )
      list( $prev_X, $prev_Y) = sgf2number_coords($_REQUEST['sgf_prev'], $Size);
   elseif( isset($_REQUEST['board_prev']) )
      list( $prev_X, $prev_Y) = board2number_coords($_REQUEST['board_prev'], $Size);
   else
      list( $prev_X, $prev_Y) = array( NULL, NULL);

   if( is_null($prev_X) || is_null($prev_Y) )
      error('illegal_position', "quick_play.err2($gid)");

   if( $prev_X != $Last_X || $prev_Y != $Last_Y )
      error('already_played', "quick_play.err3($gid)");

   $move_color = strtoupper( @$_REQUEST['color']);
   if( $move_color != ($to_move==WHITE ? 'W' : 'B') )
      error('not_your_turn', "quick_play.err4($gid)");

   $action = GAMEACT_DO_MOVE; //$action = always 'domove'

   $next_to_move = WHITE+BLACK-$to_move;
   $next_to_move_ID = ( $next_to_move == BLACK ? $Black_ID : $White_ID );


   $TheBoard = new Board( );
   if( !$TheBoard->load_from_db($game_row) )
      error('internal_error', "quick_play.load_from_db($gid)");

   //$too_few_moves = ($Moves < DELETE_LIMIT+$Handicap) ;


   // ***** HOT_SECTION *****
   // >>> See also: confirm.php, quick_play.php, include/quick/quick_game.php, clock_tick.php (for timeout)
   $gah = new GameActionHelper( $my_id, $gid, $action, /*quick*/true );
   $gah->init_query( 'quick_play', $Moves, $game_row, $to_move, $next_to_move );
   $gah->init_mp_query( $GameType, $GamePlayers, $Moves, $Handicap, $ToMove_ID, $Black_ID );
   $gah->set_game_move_message( @$_REQUEST['message'], $GameFlags );

   $Moves++;

   //case GAMEACT_DO_MOVE:
   {
      if( $Status != GAME_STATUS_PLAY )
         error('invalid_action', "quick_play.err5($gid)");

      $coord = number2sgf_coords( $query_X, $query_Y, $Size);
      $gah->prepare_game_action_do_move( 'quick_play', $TheBoard, $coord );
   }//domove

   $gah->prepare_game_action_generic();
   $gah->update_game( 'quick_play' );

// No Jump somewhere

   echo "\nOk";
}
?>
