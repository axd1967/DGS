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

require( "include/std_functions.php" );
require( "include/message_functions.php" );
require( "include/rating.php" );

{
   disable_cache();
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");
   //not used: init_standard_folders();

   if( $player_row["Handle"] == "guest" )
      error("not_allowed_for_guest");

   $komi = 0;
   if($handicap_type == 'nigiri' ) { $komi = $komi_n; }
   else if($handicap_type == 'double' ) { $komi = $komi_d; }

   if( ( $handicap_type == 'conv' or $handicap_type == 'proper' ) and
       !$player_row["RatingStatus"] )
   {
      error( "no_initial_rating" );
   }

   interpret_time_limit_forms();

   if( $rated != 'Y' or !$player_row["RatingStatus"] )
      $rated = 'N';

   if( $weekendclock != 'Y' )
      $weekendclock = 'N';

   if( $must_be_rated != 'Y' )
   {
      $must_be_rated = 'N';
      //to keep a good column sorting
      $rating1 = $rating2 = read_rating('99 kyu', 'dragonrating');
   }
   else
   {
      $rating1 = read_rating($rating1, 'dragonrating');
      $rating2 = read_rating($rating2, 'dragonrating');

      if( $rating2 < $rating1 )
      {
         $tmp = $rating1; $rating1 = $rating2; $rating2 = $tmp;
      }

      $rating2 += 50;
      $rating1 -= 50;
   }

   $query = "INSERT INTO Waitingroom SET " .
      "uid=" . $player_row['ID'] . ', ' .
      "nrGames=$nrGames, " .
      "Time=FROM_UNIXTIME($NOW), " .
      "Size=$size, " .
      "Komi=ROUND(2*($komi))/2, " .
      "Maintime=$hours, " .
      "Byotype='$byoyomitype', " .
      "Byotime=$byohours, " .
      "Byoperiods=$byoperiods, " .
      "Handicaptype='$handicap_type', " .
      "WeekendClock='$weekendclock', " .
      "Rated='$rated', " .
      "MustBeRated='$must_be_rated', " .
      "Ratingmin=$rating1, " .
      "Ratingmax=$rating2, " .
      "Comment=\"$comment\"";

   mysql_query( $query )
      or error("mysql_query_failed");

   $msg = urlencode(T_('Game added!'));

   jump_to("waiting_room.php?msg=$msg");
}
?>