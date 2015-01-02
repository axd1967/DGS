<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/std_functions.php';
require_once 'include/game_functions.php';
require_once 'include/game_actions.php';
require_once 'include/board.php';
require_once 'include/move.php';
//require_once 'include/rating.php';

$TheErrors->set_mode(ERROR_MODE_PRINT);


if ( $is_down )
{
   warning('server_down', str_replace("\n", "  ", $is_down_message));
}
else
{
   disable_cache();

   $gid = (int)@$_REQUEST['gid'] ;
   if ( $gid <= 0 )
      error('unknown_game', "quick_play($gid)");

   connect2mysql();

   // login, set timezone, quota-check, login-denied-check
   $logged_in = who_is_logged( $player_row, LOGIN_UPD_ACTIVITY | LOGIN_QUICK_SUITE | LOGIN_QUICK_PLAY );
   if ( !$logged_in )
      error('not_logged_in', 'quick_play.check.login');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'quick_play');

   $uhandle = $player_row['Handle'];
   $my_id = $player_row['ID'];


   $gah = new GameActionHelper( $my_id, $gid, /*quick*/true );
   $gah->set_game_action( GAMEACT_DO_MOVE ); // always 'domove'
   $game_row = $gah->load_game( 'quick_play' );
   extract($game_row);
   list( $to_move ) = $gah->init_globals( 'quick_play' );
   $gah->load_game_conditional_moves( 'quick_play' );

   // affirm, that game is running
   if ( $Status == GAME_STATUS_INVITED || $Status == GAME_STATUS_SETUP )
      error('game_not_started', "quick_play.check.status($gid,$Status)");
   elseif ( $Status == GAME_STATUS_FINISHED )
      error('game_finished', "quick_play.check_status.finished($gid)");
   elseif ( !isRunningGame($Status) )
      error('invalid_game_status', "quick_play.check_status.bad($gid,$Status)");

   if ( $Moves < $Handicap )
      error('invalid_action', "quick_play.check.set_handicap.not_supported($gid,$my_id,$Moves,$Handicap)");

   if ( $my_id != $ToMove_ID )
      error('not_your_turn', "quick_play.check_tomove2($gid,$ToMove_ID)");

   if ( $Status != GAME_STATUS_PLAY //exclude SCORE,PASS steps and KOMI,SETUP,INVITED,FINISHED
         || !number2sgf_coords( $Last_X, $Last_Y, $Size) //exclude first move and previous moves like pass,resume...
         || ($Handicap>1 && $Moves<=$Handicap) ) //exclude first white move after handicap stones
   {
      error('invalid_action', "quick_play.check_status.play($gid,$Status)");
   }


   if ( isset($_REQUEST['sgf_move']) )
      list( $query_X, $query_Y) = sgf2number_coords($_REQUEST['sgf_move'], $Size);
   elseif ( isset($_REQUEST['board_move']) )
      list( $query_X, $query_Y) = board2number_coords($_REQUEST['board_move'], $Size);
   else
      list( $query_X, $query_Y) = array( NULL, NULL);

   if ( is_null($query_X) || is_null($query_Y) )
      error('illegal_position', "quick_play.err1($gid)");

   if ( isset($_REQUEST['sgf_prev']) )
      list( $prev_X, $prev_Y) = sgf2number_coords($_REQUEST['sgf_prev'], $Size);
   elseif ( isset($_REQUEST['board_prev']) )
      list( $prev_X, $prev_Y) = board2number_coords($_REQUEST['board_prev'], $Size);
   else
      list( $prev_X, $prev_Y) = array( NULL, NULL);

   if ( is_null($prev_X) || is_null($prev_Y) )
      error('illegal_position', "quick_play.err2($gid)");

   if ( $prev_X != $Last_X || $prev_Y != $Last_Y )
      error('already_played', "quick_play.err3($gid)");

   $move_color = strtoupper( @$_REQUEST['color']);
   if ( $move_color != ($to_move==WHITE ? 'W' : 'B') )
      error('not_your_turn', "quick_play.err4($gid)");


   // ***** HOT_SECTION *****
   // >>> See also: confirm.php, quick_play.php, include/quick/quick_game.php, clock_tick.php (for timeout)
   $gah->load_game_board( 'quick_play' );
   $gah->init_query( 'quick_play' );
   $gah->set_game_move_message( @$_REQUEST['message'] );
   $gah->increase_moves();

   //case GAMEACT_DO_MOVE:
   $coord = number2sgf_coords( $query_X, $query_Y, $Size);
   $gah->prepare_game_action_do_move( 'quick_play', $coord );

   $gah->prepare_game_action_generic();
   $gah->process_game_action( 'quick_play' );

// No Jump somewhere

   echo "\nOk";
}
?>
