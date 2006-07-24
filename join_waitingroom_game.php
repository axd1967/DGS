<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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

{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");
   //not used: init_standard_folders();

   if( $player_row["Handle"] == "guest" )
      error("not_allowed_for_guest");

   $id = @$_REQUEST['id'];
   if( !is_numeric($id) or $id<0 )
      $id=0;

   $result = mysql_query("SELECT Waitingroom.*,Name,Handle," .
                         "Rating2 AS Rating,RatingStatus,ClockUsed,OnVacation " .
                         "FROM Waitingroom,Players " .
                         "WHERE Players.ID=Waitingroom.uid AND Waitingroom.ID=$id");

   if( @mysql_num_rows($result) != 1)
      error("waitingroom_game_not_found");


   extract(mysql_fetch_array($result));

   if( ($Handicaptype == 'proper' or $Handicaptype == 'conv')
       and !$player_row["RatingStatus"] )
      error("no_initial_rating");

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



// set me to black and swap later if necessary

   $clock_used_white = ( $OnVacation > 0 ? VACATION_CLOCK : $ClockUsed );
   $clock_used_black = ( $player_row['OnVacation'] > 0 ? VACATION_CLOCK : $player_row["ClockUsed"] );

   $opponent_rating = $Rating;
   $my_rating = $player_row["Rating2"];

   if( $MustBeRated == 'Y' and
       !($my_rating>=$Ratingmin and $my_rating<=$Ratingmax) )
      error("waitingroom_not_in_rating_range");

   if( $WeekendClock != 'Y' )
   {
      $clock_used_white += WEEKEND_CLOCK_OFFSET;
      $clock_used_black += WEEKEND_CLOCK_OFFSET;
   }

   $ticks_black = get_clock_ticks($clock_used_black);
   $ticks_white = get_clock_ticks($clock_used_white);

   $swap = 0;
   mt_srand ((double) microtime() * 1000000);
   if( $Handicaptype == 'nigiri' ) // nigiri
      $swap = mt_rand(0,1);


   $query =  "INSERT INTO Games SET " .
      "Status='PLAY', ";

   $Handicap = 0;

   $Rated = (( $Rated === 'Y' and
               !empty($RatingStatus) and
               !empty($player_row['RatingStatus']) ) ? 'Y' : 'N' );

   if( $Handicaptype == 'proper' )
      list($Handicap,$Komi,$swap) = suggest_proper($opponent_rating, $my_rating, $Size);


   if( $Handicaptype == 'conv' )
      list($Handicap,$Komi,$swap) = suggest_conventional($opponent_rating, $my_rating, $Size);

   if( $swap )
      $query .= "Black_ID=$uid, " .
         "White_ID=" . $player_row["ID"] . ", " .
         (is_numeric($my_rating) ? "White_Start_Rating=$my_rating, " : '' ) .
         (is_numeric($opponent_rating) ? "Black_Start_Rating=$opponent_rating, " : '' ) .
         "ToMove_ID=$uid, " .
         "ClockUsed=$clock_used_white, " .
         "LastTicks=$ticks_white, ";
   else
      $query .= "White_ID=$uid, " .
         "Black_ID=" . $player_row["ID"] . ", " .
         (is_numeric($my_rating) ? "Black_Start_Rating=$my_rating, " : '' ) .
         (is_numeric($opponent_rating) ? "White_Start_Rating=$opponent_rating, " : '' ) .
         "ToMove_ID=" . $player_row["ID"] . ", " .
         "ClockUsed=$clock_used_black, " .
         "LastTicks=$ticks_black, ";

   $query .= "Size=$Size, " .
      "Handicap=$Handicap, " .
      "Komi=$Komi, " .
      "Maintime=$Maintime, " .
      "Byotype='$Byotype', " .
      "Byotime=$Byotime, " .
      "Byoperiods=$Byoperiods, " .
      "Black_Maintime=$Maintime, " .
      "White_Maintime=$Maintime," .
      "Rated='$Rated', " .
      "StdHandicap='$StdHandicap', " .
      "WeekendClock='$WeekendClock', " .
      "Starttime=FROM_UNIXTIME($NOW), " .
      "Lastchanged=FROM_UNIXTIME($NOW)";

   $result = mysql_query( $query );

   if( mysql_affected_rows() != 1)
      error("mysql_start_game");

   $gid = mysql_insert_id();

   if( $Handicaptype == 'double' ) // extra game for double
   {
      $query = "INSERT INTO Games SET " .
         "Black_ID=$uid, " .
         "White_ID=" . $player_row["ID"] . ", " .
         (is_numeric($my_rating) ? "White_Start_Rating=$my_rating, " : '' ) .
         (is_numeric($opponent_rating) ? "Black_Start_Rating=$opponent_rating, " : '' ) .
         "ToMove_ID=$uid, " .
         "Status='PLAY', " .
         "ClockUsed=$clock_used_white, " .
         "LastTicks=$ticks_white, " .
         "Size=$Size, " .
         "Handicap=$Handicap, " .
         "Komi=$Komi, " .
         "Maintime=$Maintime, " .
         "Byotype='$Byotype', " .
         "Byotime=$Byotime, " .
         "Byoperiods=$Byoperiods, " .
         "Black_Maintime=$Maintime, " .
         "White_Maintime=$Maintime," .
         "Rated='$Rated', " .
         "StdHandicap='$StdHandicap', " .
         "WeekendClock='$WeekendClock', " .
         "Starttime=FROM_UNIXTIME($NOW), " .
         "Lastchanged=FROM_UNIXTIME($NOW)";

      mysql_query( $query )
         or error("mysql_query_failed");

   }

   mysql_query( "UPDATE Players SET Running=Running+" .
                ( $Handicaptype == 'double' ? 2 : 1 ) .
                ( $Rated == 'Y' ? ", RatingStatus='RATED'" : '' ) .
                " WHERE ID=$uid OR ID=" . $player_row['ID'] . " LIMIT 2" );

// Reduce number of games left in the waiting room

   if( $nrGames <= 1 )
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