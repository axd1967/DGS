<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

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

$TranslateGroups[] = "Game";

require_once( "include/std_functions.php" );
require_once( "include/message_functions.php" );
require_once( "include/rating.php" );
require_once( "include/make_game.php" );

{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged($player_row);

   if( !$logged_in )
      error("not_logged_in");
   //not used: init_standard_folders();

   if( $player_row["Handle"] == "guest" )
      error("not_allowed_for_guest");

   $id = @$_REQUEST['id'];
   if( !is_numeric($id) or $id<0 )
      $id=0;

   $result = mysql_query("SELECT Waitingroom.* FROM Waitingroom WHERE ID=$id");

   if( @mysql_num_rows($result) != 1)
      error("waitingroom_game_not_found");

   $game_info_row = mysql_fetch_array($result);
   $uid = $game_info_row['uid'];

   $result = mysql_query("SELECT ID,Name,Handle," .
                         "Rating2,RatingStatus,ClockUsed,OnVacation " .
                         "FROM Players WHERE ID=$uid");

   if( @mysql_num_rows($result) != 1)
      error("waitingroom_game_not_found");

   $opponent_row = mysql_fetch_array($result);



   if( @$_REQUEST['delete'] == 't' )
   {
      if( $player_row['ID'] !== $uid )
         error('waitingroom_delete_not_own');

      mysql_query("DELETE FROM Waitingroom WHERE ID=$id LIMIT 1");

      $msg = urlencode(T_('Game deleted!'));

      jump_to("waiting_room.php?sysmsg=$msg");
   }

//else... joining game

   if( $player_row['ID'] == $uid )
      error("waitingroom_own_game");

   if( $game_info_row['MustBeRated'] == 'Y' and
       !($player_row['Rating2'] >= $game_info_row['Ratingmin']
         and $player_row['Rating2'] <= $game_info_row['Ratingmax']) )
      error("waitingroom_not_in_rating_range");

   $size = $game_info_row['Size'];

   $my_rating = $player_row["Rating2"];
   $iamrated = ( $player_row['RatingStatus'] && is_numeric($my_rating) && $my_rating >= MIN_RATING );
   $opprating = $opponent_row["Rating2"];
   $opprated = ( $opponent_row['RatingStatus'] && is_numeric($opprating) && $opprating >= MIN_RATING );


   switch( $game_info_row['Handicaptype'] )
   {
      case 'conv':
      {
         if( !$iamrated or !$opprated )
            error('no_initial_rating');
         list($game_info_row['Handicap'],$game_info_row['Komi'],$i_am_black) =
            suggest_conventional( $my_rating, $opprating, $size);
      }
      break;

      case 'proper':
      {
         if( !$iamrated or !$opprated )
            error('no_initial_rating');
         list($game_info_row['Handicap'],$game_info_row['Komi'],$i_am_black) =
            suggest_proper( $my_rating, $opprating, $size);
      }
      break;

      case 'double':
      {
         create_game($player_row, $opponent_row, $game_info_row);
         $i_am_black = false;
      }
      break;

      default: //always available even if waiting room or unrated
         $game_info_row['Handicaptype'] = 'nigiri'; 
      case 'nigiri':
      {
         $game_info_row['Handicap'] = 0;
         mt_srand ((double) microtime() * 1000000);
         $i_am_black = mt_rand(0,1);
      }
      break;

      case 'manual':
      {
         $i_am_black = false;
      }
      break;
   }

   if( $i_am_black )
      $gid = create_game($player_row, $opponent_row, $game_info_row);
   else
      $gid = create_game($opponent_row, $player_row, $game_info_row);

   mysql_query( "UPDATE Players SET Running=Running+" .
                ( $game_info_row['Handicaptype'] == 'double' ? 2 : 1 ) .
                ( $game_info_row['Rated'] == 'Y' ? ", RatingStatus='RATED'" : '' ) .
                " WHERE ID=$uid OR ID=" . $player_row['ID'] . " LIMIT 2" );

// Reduce number of games left in the waiting room

   if( $game_info_row['nrGames'] <= 1 )
   {
      mysql_query("DELETE FROM Waitingroom where ID=$id LIMIT 1");
   }
   else
   {
      mysql_query("UPDATE Waitingroom SET nrGames=nrGames-1 WHERE ID=$id LIMIT 1");
   }


   // Send message to notify opponent

// This was often truncated by the database field:
//   $subject = addslashes('<A href=\"userinfo.php?uid=' . $player_row['ID'] . '\">' . $player_row['Name'] . ' (' . $player_row['Handle'] .")</A> has joined your waiting room game");
   $subject = 'Your waiting room game has been joined.';
   $reply = trim(get_request_arg('reply'));
   if ($reply)
   {
      $reply = addslashes(user_reference( REF_LINK, 1, '', $player_row). " wrote:\n" . $reply) ;
   }
   else
   {
      $reply = addslashes(user_reference( REF_LINK, 1, '', $player_row). " has joined your waiting room game.") ;
   }

   $query = "INSERT INTO Messages SET Time=FROM_UNIXTIME($NOW), " .
      "Game_ID=$gid, " .
      "Subject='$subject', " .
      "Text='$reply'";

   mysql_query( $query );

      if( mysql_affected_rows() != 1)
         error("mysql_insert_message");

   $mid = mysql_insert_id();

   mysql_query("INSERT INTO MessageCorrespondents (uid,mid,Sender,Folder_nr) VALUES " .
               "($uid, $mid, 'N', ".FOLDER_NEW.")");


   $msg = urlencode(T_('Game joined!'));

   jump_to("status.php?sysmsg=$msg");
}
?>