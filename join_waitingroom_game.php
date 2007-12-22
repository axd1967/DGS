<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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
      error("not_logged_in");

   if( $player_row["Handle"] == "guest" )
      error("not_allowed_for_guest");

   $my_id = $player_row['ID'];

   $wr_id = @$_REQUEST['id'];
   if( !is_numeric($wr_id) or $wr_id <= 0 )
      error('waitingroom_game_not_found', 'join_waitingroom_game.bad_id');

   $tmp= CSYSFLAG_WAITINGROOM;
   $query= "SELECT W.*"
         . ",IF(ISNULL(C.uid),0,C.SystemFlags & $tmp) AS C_denied"
         . " FROM Waitingroom AS W"
         . " LEFT JOIN Contacts AS C ON C.uid=W.uid AND C.cid=$my_id"
         . " WHERE W.ID=$wr_id AND W.nrGames>0"
         . " HAVING C_denied=0"
         ;
   $game_row = mysql_single_fetch('join_waitingroom_game.find_game', $query);
   if( !$game_row )
      error('waitingroom_game_not_found', 'join_waitingroom_game.find_game');

   $opponent_ID = $game_row['uid'];

   if( @$_REQUEST['delete'] == 't' )
   {
      if( $my_id != $opponent_ID )
         error('waitingroom_delete_not_own');

      mysql_query("DELETE FROM Waitingroom WHERE ID=$wr_id LIMIT 1")
         or error('mysql_query_failed', 'join_waitingroom_game.delete');

      $msg = urlencode(T_('Game deleted!'));

      jump_to("waiting_room.php?sysmsg=$msg");
   }

   //else... joining game

   $opponent_row = mysql_single_fetch('join_waitingroom_game.find_players',
         "SELECT ID,Name,Handle," .
         "Rating2,RatingStatus,ClockUsed,OnVacation " .
         "FROM Players WHERE ID=$opponent_ID");

   if( !$opponent_row )
      error('waitingroom_game_not_found', 'join_waitingroom_game.find_players');

   if( $my_id == $opponent_ID )
      error('waitingroom_own_game');

   if( $game_row['MustBeRated'] == 'Y' and
       !($player_row['Rating2'] >= $game_row['Ratingmin']
         and $player_row['Rating2'] <= $game_row['Ratingmax']) )
      error('waitingroom_not_in_rating_range');

   $size = $game_row['Size'];

   $my_rating = $player_row["Rating2"];
   $iamrated = ( $player_row['RatingStatus'] && is_numeric($my_rating) && $my_rating >= MIN_RATING );
   $opprating = $opponent_row["Rating2"];
   $opprated = ( $opponent_row['RatingStatus'] && is_numeric($opprating) && $opprating >= MIN_RATING );

   $double = false;
   switch( $game_row['Handicaptype'] )
   {
      case 'conv':
      {
         if( !$iamrated or !$opprated )
            error('no_initial_rating');
         list($game_row['Handicap'],$game_row['Komi'],$i_am_black) =
            suggest_conventional( $my_rating, $opprating, $size);
      }
      break;

      case 'proper':
      {
         if( !$iamrated or !$opprated )
            error('no_initial_rating');
         list($game_row['Handicap'],$game_row['Komi'],$i_am_black) =
            suggest_proper( $my_rating, $opprating, $size);
      }
      break;

      case 'double':
      {
         $double = true;
         $i_am_black = true;
      }
      break;

      case 'manual':
      /* to be adjusted if 'manual' is allowed in the waitingroom
      {
         $i_am_black = false;
      }
      break;
      */
      default: //always available even if waiting room or unrated
         $game_row['Handicaptype'] = 'nigiri'; 
      case 'nigiri':
      {
         $game_row['Handicap'] = 0;
         mt_srand ((double) microtime() * 1000000);
         $i_am_black = mt_rand(0,1);
      }
      break;
   }
   
   //TODO: HOT_SECTION ???
   $gids = array();
   if( $i_am_black or $double )
      $gids[] = create_game($player_row, $opponent_row, $game_row);
   else
      $gids[] = create_game($opponent_row, $player_row, $game_row);
   $gid = $gids[0];
   //keep this after the regular one ($gid => consistency with send_message)
   if( $double )
      $gids[] = create_game($opponent_row, $player_row, $game_row);

   //TODO: provide a link between the two paired "double" games
   $cnt = count($gids);
   mysql_query( "UPDATE Players SET Running=Running+$cnt" .
                ( $game_row['Rated'] == 'Y' ? ", RatingStatus='RATED'" : '' ) .
                " WHERE (ID=$my_id OR ID=$opponent_ID) LIMIT 2" )
      or error('mysql_query_failed', 'join_waitingroom_game.update_players');


   // Reduce number of games left in the waiting room

   if( $game_row['nrGames'] > 1 )
   {
      mysql_query("UPDATE Waitingroom SET nrGames=nrGames-1"
                  ." WHERE ID=$wr_id AND nrGames>0 LIMIT 1")
         or error('mysql_query_failed', 'join_waitingroom_game.reduce');
   }
   else
   {
      mysql_query("DELETE FROM Waitingroom WHERE ID=$wr_id LIMIT 1")
         or error('mysql_query_failed', 'join_waitingroom_game.reduce_delete');
   }


   // Send message to notify opponent

// This was often truncated by the database field:
//   $subject = mysql_addslashes('<A href=\"userinfo.php?uid=' . $player_row['ID'] . '\">' . $player_row['Name'] . ' (' . $player_row['Handle'] .")</A> has joined your waiting room game");
   $subject = 'Your waiting room game has been joined.';
   $reply = trim(get_request_arg('reply'));
   if ($reply)
   {
      $reply = user_reference( REF_LINK, 1, '', $player_row). " wrote:\n" . $reply;
   }
   else
   {
      $reply = user_reference( REF_LINK, 1, '', $player_row). " has joined your waiting room game.";
   }
   if( !empty($game_row['Comment']) )
      $reply = 'Comment: '.$game_row['Comment']."\n".$reply;

   send_message( 'join_waitingroom_game', $reply, $subject
      , $opponent_ID, '', true
      , 0, 'NORMAL', $gid);


   $msg = urlencode(T_('Game joined!'));

   jump_to("status.php?sysmsg=$msg");
}
?>
