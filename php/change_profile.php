<?php
/*
Dragon Go Server
Copyright (C) 2001  Erik Ouchterlony

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
require( "include/rating.php" );

{
   disable_cache();
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   if( $player_row["Handle"] == "guest" )
      error("not_allowed_for_guest");


   if( strlen( $name ) < 1 )
      error("name_not_given");

   if( $wantemail and $email )
      $flags = 1;
   else
      $flags = 0;

   $query = "UPDATE Players SET " .
       "Name='$name', " .
       "Email='$email', " .
       "Rank='$rank', " .
       "Open='$open', " .
       "Stonesize=$stonesize, " .
       "Boardfontsize='$boardfontsize', " .
       "Flags=$flags, ";

   if( $nightstart != $player_row["Nightstart"] || 
   $timezone != $player_row["Timezone"] )
   {            
      putenv("TZ=$timezone" );

      $query .= "ClockChanged=NOW(), ";
      // TODO: Should be changed after 15h
      $query .= "ClockUsed=" . get_clock_used($nightstart) . ", ";
   }

    
   $newrating = convert_to_rating($rating, $ratingtype);

   if( $player_row["RatingStatus"] != 'RATED' and $newrating and
   ( $ratingtype != 'dragonrating' or abs($newrating - $player_row["Rating"]) > 0.005 ) )
   {
      // TODO: check if reasonable
      $query .= "Rating=$newrating, " .
          "RatingStatus='INIT', ";
        
   }

   $query .= "Timezone='$timezone', " .
       "Nightstart=$nightstart" .
       " WHERE ID=" . $player_row['ID']; 
    
   mysql_query( $query );

   $msg = urlencode("Profile updated!");

   header("Location: userinfo.php?uid=" . $player_row["ID"] . "&msg=$msg");
   exit;
}
?>