<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once( "include/std_functions.php" );
require_once( "include/message_functions.php" );
require_once( "include/rating.php" );
require_once( "include/make_game.php" );
require_once( "include/contacts.php" );

{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   $my_id = (int)@$player_row['ID'];
   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $wr_id = @$_REQUEST['id'];
   if( !is_numeric($wr_id) || $wr_id <= 0 )
      error('waitingroom_game_not_found', "join_waitingroom_game.bad_id($wr_id)");

   $my_rated_games = (int)$player_row['RatedGames'];
   $sql_goodmingames = "IF(W.MinRatedGames>0,($my_rated_games >= W.MinRatedGames),1)";
   $query= "SELECT W.*"
         . ',IF(ISNULL(C.uid),0,C.SystemFlags & '.CSYSFLAG_WAITINGROOM.') AS C_denied'
         . ',IF(ISNULL(WRJ.opp_id),0,1) AS X_wrj_exists'
         . ',WRJ.JoinedCount'
         . ',IF(W.SameOpponent=0 OR ISNULL(WRJ.wroom_id), 1, '
            . 'IF(W.SameOpponent<0, '
               . '(WRJ.JoinedCount < -W.SameOpponent), '
               . "(WRJ.ExpireDate <= FROM_UNIXTIME($NOW)) )) AS goodsameopp"
         . ",$sql_goodmingames AS goodmingames"
         . " FROM Waitingroom AS W"
         . " LEFT JOIN Contacts AS C ON C.uid=W.uid AND C.cid=$my_id"
         . " LEFT JOIN WaitingroomJoined AS WRJ ON WRJ.opp_id=$my_id AND WRJ.wroom_id=W.ID"
         . " WHERE W.ID=$wr_id AND W.nrGames>0"
         . " HAVING C_denied=0"
         ;
   $game_row = mysql_single_fetch( "join_waitingroom_game.find_game($wr_id,$my_id)", $query);
   if( !$game_row )
      error('waitingroom_game_not_found', "join_waitingroom_game.find_game2($wr_id,$my_id)");

   $opponent_ID = $game_row['uid'];

   if( @$_REQUEST['delete'] == 't' )
   {
      if( $my_id != $opponent_ID )
         error('waitingroom_delete_not_own', "join_waitingroom_game.check.user($opponent_ID)");

      db_query( "join_waitingroom_game.delete($wr_id)",
         "DELETE FROM Waitingroom WHERE ID=$wr_id LIMIT 1" );

      $gid = @$game_row['gid'];
      if( $gid )
         MultiPlayerGame::revoke_offer_game_players( $gid, $game_row['nrGames'], GPFLAG_WAITINGROOM );

      $msg = urlencode(T_('Game deleted!'));
      if( $gid )
         jump_to("game_players.php?gid=$gid".URI_AMP."sysmsg=$msg");
      else
         jump_to("waiting_room.php?sysmsg=$msg");
   }


   //else... joining game

   $opponent_row = mysql_single_fetch('join_waitingroom_game.find_players',
         "SELECT ID,Name,Handle," .
         "Rating2,RatingStatus,ClockUsed,OnVacation " .
         "FROM Players WHERE ID=$opponent_ID LIMIT 1");

   if( !$opponent_row )
      error('waitingroom_game_not_found', "join_waitingroom_game.find_players.opp($opponent_ID)");

   if( $my_id == $opponent_ID )
      error('waitingroom_own_game');

   if( $game_row['MustBeRated'] == 'Y' &&
       !($player_row['Rating2'] >= $game_row['Ratingmin']
         && $player_row['Rating2'] <= $game_row['Ratingmax']) )
      error('waitingroom_not_in_rating_range');

   if( !$game_row['goodmingames'] )
      error('waitingroom_not_enough_rated_fin_games',
         "join_waitingroom_game.min_rated_fin_games({$game_row['MinRatedGames']})");

   if( !$game_row['goodsameopp'] )
      error('waitingroom_not_same_opponent',
         "join_waitingroom_game.same_opponent({$game_row['SameOpponent']})");

   $size = limit( $game_row['Size'], MIN_BOARD_SIZE, MAX_BOARD_SIZE, 19 );

   $my_rating = $player_row["Rating2"];
   $iamrated = ( $player_row['RatingStatus'] != RATING_NONE
         && is_numeric($my_rating) && $my_rating >= MIN_RATING );
   $opprating = $opponent_row["Rating2"];
   $opprated = ( $opponent_row['RatingStatus'] != RATING_NONE
         && is_numeric($opprating) && $opprating >= MIN_RATING );

   $double = false;
   switch( (string)$game_row['Handicaptype'] )
   {
      case HTYPE_CONV:
         if( !$iamrated || !$opprated )
            error('no_initial_rating');
         list($game_row['Handicap'],$game_row['Komi'],$i_am_black ) =
            suggest_conventional( $my_rating, $opprating, $size);
         break;

      case HTYPE_PROPER:
         if( !$iamrated || !$opprated )
            error('no_initial_rating');
         list($game_row['Handicap'],$game_row['Komi'],$i_am_black ) =
            suggest_proper( $my_rating, $opprating, $size);
         break;

      case HTYPE_DOUBLE:
         $double = true;
         $i_am_black = true;
         break;

      case HTYPE_BLACK:
         $i_am_black = false; // game-offerer wants BLACK, so challenger gets WHITE
         break;

      case HTYPE_WHITE:
         $i_am_black = true; // game-offerer wants WHITE, so challenger gets BLACK
         break;

      default: //always available even if waiting room or unrated
         $game_row['Handicaptype'] = HTYPE_NIGIRI;
         $game_row['Handicap'] = 0;
      case HTYPE_NIGIRI:
         mt_srand((double) microtime() * 1000000);
         $i_am_black = mt_rand(0,1);
         break;
   }

   //TODO: HOT_SECTION ???
   $gids = array();
   $is_std_go = ( $game_row['GameType'] == GAMETYPE_GO );
   if( $is_std_go )
   {
      if( $i_am_black || $double )
         $gids[] = create_game($player_row, $opponent_row, $game_row);
      else
         $gids[] = create_game($opponent_row, $player_row, $game_row);
      $gid = $gids[0];
      //keep this after the regular one ($gid => consistency with send_message)
      if( $double )
      {
         // provide a link between the two paired "double" games
         $game_row['double_gid'] = $gid;
         $double_gid2 = create_game($opponent_row, $player_row, $game_row);
         $gids[] = $double_gid2;

         db_query( "join_waitingroom_game.update_double2($gid)",
            "UPDATE Games SET DoubleGame_ID=$double_gid2 WHERE ID=$gid LIMIT 1" );
      }
   }
   else // join multi-player-game
   {
      $gid = $game_row['gid']; // use existing game for Team-/Zen-Go
      if( $gid <= 0 )
         error('internal_error', "join_waitingroom_game.join_game.check.gid($wr_id,$gid,$my_id)");

      MultiPlayerGame::join_waitingroom_game( "join_waitingroom_game.join_game($wr_id)", $gid, $my_id );
      $gids[] = $gid;
   }

   $cnt = count($gids);
   db_query( 'join_waitingroom_game.update_players',
      "UPDATE Players SET Running=Running+$cnt" .
                ( $game_row['Rated'] == 'Y' ? ", RatingStatus='".RATING_RATED."'" : '' ) .
                " WHERE ID IN ($my_id,$opponent_ID) LIMIT 2" );


   // Reduce number of games left in the waiting room

   if( $game_row['nrGames'] > 1 )
   {
      db_query( 'join_waitingroom_game.reduce',
         "UPDATE Waitingroom SET nrGames=nrGames-1 WHERE ID=$wr_id AND nrGames>0 LIMIT 1" );
   }
   else
   {
      db_query( 'join_waitingroom_game.reduce_delete',
         "DELETE FROM Waitingroom WHERE ID=$wr_id LIMIT 1" );
   }


   // Update WaitingroomJoined
   // NOTE: restriction on count and time are mutual exclusive

   $same_opp = $game_row['SameOpponent'];
   $query_so = '';
   if( $same_opp < 0 ) // restriction on count
   {
      if( $game_row['X_wrj_exists'] )
         $query_so = 'UPDATE WaitingroomJoined SET JoinedCount=JoinedCount+1 '
            . "WHERE opp_id=$my_id AND wroom_id=$wr_id LIMIT 1";
      else
         $query_so = 'INSERT INTO WaitingroomJoined '
            . "SET opp_id=$my_id, wroom_id=$wr_id, JoinedCount=1";
   }
   elseif( $same_opp > 0 ) // restriction on time
   {
      $expire_date = $NOW + $same_opp * SECS_PER_DAY;
      $query_wrjexp = "WaitingroomJoined SET ExpireDate=FROM_UNIXTIME($expire_date)";
      if( $game_row['X_wrj_exists'] ) // faster than REPLACE-INTO
         $query_so = 'UPDATE ' . $query_wrjexp . "WHERE opp_id=$my_id AND wroom_id=$wr_id LIMIT 1";
      else
         $query_so = 'INSERT INTO ' . $query_wrjexp . ", opp_id=$my_id, wroom_id=$wr_id";
   }
   if( $query_so )
      db_query( "join_waitingroom_game.wroom_joined.save(u$my_id,wr$wr_id)", $query_so );



   // Send message to notify opponent

   $subject = 'Your waiting room game has been joined.'; // maxlen=80
   $message = ( empty($game_row['Comment']) ) ? '' : "Comment: {$game_row['Comment']}\n\n";
   $message .= sprintf( "%s has joined your waiting room game.\n",
      user_reference( REF_LINK, 1, '', $player_row) );
   $message .= sprintf( "\nGames of type [%s]:\n",
      MultiPlayerGame::get_game_type($game_row['GameType']) );
   foreach( $gids as $gid )
      $message .= "* <game $gid>\n";

   send_message( 'join_waitingroom_game', $message, $subject
      , $opponent_ID, '', true
      , 0, 'NORMAL', $gid);


   $msg = urlencode(T_('Game joined!'));

   jump_to("status.php?sysmsg=$msg");
}
?>
