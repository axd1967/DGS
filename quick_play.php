<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once( "include/board.php" );
require_once( "include/move.php" );
//require_once( "include/rating.php" );

$TheErrors->set_mode(ERROR_MODE_PRINT);


if( $is_down )
{
   warning($is_down_message);
}
else
{
   disable_cache();

   $gid = (int)@$_REQUEST['gid'] ;
   if( $gid <= 0 )
      error('unknown_game');


   connect2mysql();

   // logged in?

   $uhandle= safe_getcookie('handle');
   $result = @db_query( "quick_play.find_player($uhandle)",
      'SELECT ID, Timezone, AdminOptions, UNIX_TIMESTAMP(Sessionexpire) AS Expire, Sessioncode ' .
      "FROM Players WHERE Handle='".mysql_addslashes($uhandle)."' LIMIT 1" );
   if( @mysql_num_rows($result) != 1 )
      error('not_logged_in', "quick_play.find_player2($uhandle)");

   $player_row = mysql_fetch_assoc($result);

   if( (@$player_row['AdminOptions'] & ADMOPT_DENY_LOGIN) )
      error('login_denied');

   if( $player_row['Sessioncode'] !== safe_getcookie('sessioncode') || $player_row['Expire'] < $NOW )
      error('not_logged_in','qp2');

   //TODO: fever vault check ??? -> migrate to quick_do-suite
   //setTZ( $player_row['Timezone']);

   $my_id = $player_row['ID'];

   $game_row = mysql_single_fetch( 'quick_play.find_game',
         "SELECT Games.*, " .
            "Games.Flags+0 AS GameFlags, " . //used by check_move
            "black.ClockUsed AS Blackclock, " .
            "white.ClockUsed AS Whiteclock, " .
            "black.OnVacation AS Blackonvacation, " .
            "white.OnVacation AS Whiteonvacation " .
         "FROM (Games, Players AS black, Players AS white) " .
         "WHERE Games.ID=$gid AND Black_ID=black.ID AND White_ID=white.ID"
      );

   if( !$game_row )
      error('unknown_game', "quick_play.find_game($gid)");

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

   $action = 'domove'; //$action = always 'domove'

   $next_to_move = WHITE+BLACK-$to_move;
   $next_to_move_ID = ( $next_to_move == BLACK ? $Black_ID : $White_ID );

   // update clock
   list( $hours, $upd_clock ) = GameHelper::update_clock( $game_row, $to_move, $next_to_move );
   $time_query = $upd_clock->get_query(false, true);

   $TheBoard = new Board( );
   if( !$TheBoard->load_from_db($game_row) )
      error('internal_error', "quick_play.load_from_db($gid)");

   //$too_few_moves = ($Moves < DELETE_LIMIT+$Handicap) ;

   $mp_query = '';
   $is_mpgame = ($GameType != GAMETYPE_GO);
   if( $is_mpgame && $action == 'domove' )
   {
      list( $group_color, $group_order, $gpmove_color )
         = MultiPlayerGame::calc_game_player_for_move( $GamePlayers, $Moves, $Handicap, 2 );
      $mp_gp = GamePlayer::load_game_player( $gid, $group_color, $group_order );
      $mp_uid = $mp_gp->uid;
      $mp_query = (( $ToMove_ID == $Black_ID ) ? 'Black_ID' : 'White_ID' ) . "=$mp_uid, ";
   }

   $message_raw = trim(@$_REQUEST['message']);
   if( preg_match( "/^<c>\s*<\\/c>$/si", $message_raw ) ) // remove empty comment-only tags
      $message_raw = '';
   $message = mysql_addslashes($message_raw);

   if( $message && preg_match( "#</?h(idden)?>#is", $message) )
      $GameFlags |= GAMEFLAGS_HIDDEN_MSG;


/* **********************
*** HOT_SECTION ***
>>> See also confirm.php, quick_play.php and clock_tick.php
Various dirty things (like duplicated moves) could appear
in case of multiple calls with the same move number. This could
happen in case of multi-players account with simultaneous logins
or if one player hit twice the validation button during a net lag
and/or if the opponent had already played between the two calls.

Because the LOCK query is not implemented with MySQL < 4.0,
we use the Moves field of the Games table to check those
possible multiple queries.
This is why:
- the arguments are checked against the current state of the Games table
- the current Games table give the current Moves value
- the Games table is always modified while checking its Moves field (see $game_clause)
- the Games table modification must always modify the Moves field (see $game_query)
- this modification is always done in first place and checked before continuation
*********************** */
   $game_clause = " WHERE ID=$gid AND Status".IS_RUNNING_GAME." AND Moves=$Moves LIMIT 1";
   $Moves++;


   //case 'domove':
   {
      if( $Status != GAME_STATUS_PLAY )
         error('invalid_action', "quick_play.err5($gid)");

      $coord = number2sgf_coords( $query_X, $query_Y, $Size);

      {//to fix the old way Ko detect. Could be removed when no more old way games.
         if( !@$Last_Move ) $Last_Move= number2sgf_coords($Last_X, $Last_Y, $Size);
      }
      check_move( $TheBoard, $coord, $to_move);
      //ajusted globals by check_move(): $Black_Prisoners, $White_Prisoners, $prisoners, $nr_prisoners, $colnr, $rownr;
      //here, $prisoners list the captured stones of play (or suicided stones if, a day, $suicide_allowed==true)

      $move_query = "INSERT INTO Moves (gid, MoveNr, Stone, PosX, PosY, Hours) VALUES ";

      $prisoner_string = '';
      foreach($prisoners as $tmp)
      {
         list($x,$y) = $tmp;
         $move_query .= "($gid, $Moves, ".NONE.", $x, $y, 0), ";
         $prisoner_string .= number2sgf_coords($x, $y, $Size);
      }

      if( strlen($prisoner_string) != $nr_prisoners*2 )
         error('move_problem', "quick_play.domove.prisoner($gid)");

      $move_query .= "($gid, $Moves, $to_move, $colnr, $rownr, $hours) ";

      if( $message )
         $message_query = "INSERT INTO MoveMessages SET gid=$gid, MoveNr=$Moves, Text='$message'";

      $game_query = "UPDATE Games SET Moves=$Moves, " . //See *** HOT_SECTION ***
          "Last_X=$colnr, " . //used with mail notifications
          "Last_Y=$rownr, " .
          "Last_Move='" . number2sgf_coords($colnr, $rownr, $Size) . "', " . //used to detect Ko
          "Status='".GAME_STATUS_PLAY."', ";

      if( $nr_prisoners > 0 )
      {
         if( $to_move == BLACK )
            $game_query .= "Black_Prisoners=$Black_Prisoners, ";
         else
            $game_query .= "White_Prisoners=$White_Prisoners, ";
      }

      if( $nr_prisoners == 1 )
         $GameFlags |= GAMEFLAGS_KO;
      else
         $GameFlags &= ~GAMEFLAGS_KO;

      $game_query .= "ToMove_ID=$next_to_move_ID, " .
          "Flags=$GameFlags, " .
          "Snapshot='" . GameSnapshot::make_game_snapshot($Size, $TheBoard) . "', " .
          $mp_query . $time_query . "Lastchanged=FROM_UNIXTIME($NOW)" ;
   }//domove


   ta_begin();
   {//HOT-section to play move in game
      //See *** HOT_SECTION *** above
      $result = db_query( "quick_play.update_game($gid)", $game_query . $game_clause );
      if( mysql_affected_rows() != 1 )
         error('mysql_update_game', "quick_play.update_game2($gid)");

      $result = db_query( "quick_play.update_moves($gid,$action)", $move_query );
      if( mysql_affected_rows() < 1 && $action != 'delete' )
         error('mysql_insert_move', "quick_play.update_moves2($gid,$action)");

      if( $message_query )
      {
         $result = db_query( "quick_play.update_movemessage1($gid)", $message_query );
         if( mysql_affected_rows() < 1 && $action != 'delete' )
            error('mysql_insert_move', "quick_play.update_movemessage2($gid,$action)");
      }

      // Notify opponent about move
      notify( "quick_play.notify_opponent($gid,$next_to_move_ID)", $next_to_move_ID );

      // Increase moves and activity
      db_query( "quick_play.update_player($gid,$my_id)",
         "UPDATE Players SET Moves=Moves+1"
            .",Activity=LEAST($ActivityMax,$ActivityForMove+Activity)"
            .",LastMove=FROM_UNIXTIME($NOW)"
         ." WHERE ID=$my_id LIMIT 1" );
   }
   ta_end();


// No Jump somewhere

   echo "\nOk";
}
?>
