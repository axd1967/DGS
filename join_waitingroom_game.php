<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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

require( "include/std_functions.php" );
require( "include/message_functions.php" );
require( "include/rating.php" );

{
   disable_cache();
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   if( $player_row["Handle"] == "guest" )
      error("not_allowed_for_guest");

   if( !is_numeric($id) )
      $id=0;

   $result = mysql_query("SELECT Waitingroom.*,Name,Handle,Rating,ClockUsed " .
                         "FROM Waitingroom,Players " .
                         "WHERE Players.ID=Waitingroom.uid AND Waitingroom.ID=$id");

   if( mysql_num_rows($result) != 1)
      error("waitingroom_game_not_found");


   extract(mysql_fetch_array($result));

   if( $delete == 't' and $player_row['ID'] == $uid )
   {
      mysql_query("DELETE FROM Waitingroom WHERE ID=$id LIMIT 1");

      $msg = urlencode(T_('Game deleted!'));

      jump_to("waiting_room.php?msg=$msg");
   }


   if( $player_row['ID'] == $uid )
      error("waitingroom_own_game");



// set me to black and swap later if necessary

   $clock_used_white = $ClockUsed;
   $clock_used_black = $player_row["ClockUsed"];
   $rating_white = $Rating;
   $rating_black = $player_row["Rating"];

   if( $MustBeRated == 'Y' and
       !($player_row["Rating"]>=$Ratingmin and $player_row["Rating"]<=$Ratingmax) )
      error("waitingroom_not_in_rating_range");

   if( $weekendclock != 'Y' )
   {
      $clock_used_white += 100;
      $clock_used_black += 100;
   }

   $ticks_black = get_clock_ticks($clock_used_black);
   $ticks_white = get_clock_ticks($clock_used_white);

   mt_srand ((double) microtime() * 1000000);
   if( $Handicaptype == 'nigiri' ) // nigiri
      $swap = mt_rand(0,1);


   $query =  "INSERT INTO Games SET " .
      "Status='PLAY', ";


   if( $Handicaptype == 'proper' )
   {
      list($handicap,$komi,$swap) = suggest_proper($rating_black, $rating_white, $Size);

      $query .= "Handicap=$handicap, Komi=$komi, ";
   }

   if( $Handicaptype == 'conv' )
   {
      list($handicap,$komi,$swap) = suggest_conventional($rating_black, $rating_white, $Size);

      $query .= "Handicap=$handicap, Komi=$komi, ";
   }

   if( $Handicaptype == 'double' )
      $query .= "Handicap=0, Komi=$Komi, ";

   if( $swap )
      $query .= "Black_ID=$uid, " .
         "White_ID=" . $player_row["ID"] . ", " .
         "ToMove_ID=$uid, " .
         "ClockUsed=$clock_used_white, " .
         "LastTicks=$ticks_white, ";
   else
      $query .= "White_ID=$uid, " .
         "Black_ID=" . $player_row["ID"] . ", " .
         "ToMove_ID=" . $player_row["ID"] . ", " .
         "ClockUsed=$clock_used_black, " .
         "LastTicks=$ticks_black, ";

   $query .= "Size=$Size, " .
      "Maintime=$Maintime, " .
      "Byotype='$Byotype', " .
      "Byotime=$Byotime, " .
      "Byoperiods=$Byoperiods, " .
      "Black_Maintime=$Maintime, " .
      "White_Maintime=$Maintime," .
      "WeekendClock='$WeekendClock', " .
      "Rated='$Rated', " .
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
         "ToMove_ID=$uid, " .
         "Status='PLAY', " .
         "ClockUsed=$clock_used_white, " .
         "LastTicks=$ticks_white, " .
         "Size=$Size, " .
         "Handicap=0, " .
         "Komi=$Komi, " .
         "Maintime=$Maintime, " .
         "Byotype='$Byotype', " .
         "Byotime=$Byotime, " .
         "Byoperiods=$Byoperiods, " .
         "Black_Maintime=$Maintime, " .
         "White_Maintime=$Maintime," .
         "WeekendClock='$WeekendClock', " .
         "Rated='$Rated', " .
         "Starttime=FROM_UNIXTIME($NOW), " .
         "Lastchanged=FROM_UNIXTIME($NOW)";

      mysql_query( $query )
         or error("mysql_query_failed");

   }

   mysql_query( "UPDATE Players SET Running=Running+" .
                ( $Handicaptype == 'double' ? 2 : 1 ) .
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

   $subject = "I have joined your waiting room game";

   $query = "INSERT INTO Messages SET " .
      "From_ID=" . $player_row["ID"] . ", " .
      "To_ID=$uid, " .
      "Time=FROM_UNIXTIME($NOW), " .
      "Game_ID=$gid, " .
      "Subject=\"$subject\", " .
      "Text=''";

   mysql_query( $query );



   $msg = urlencode(T_('Game joined!'));

   jump_to("status.php?msg=$msg");
}
?>