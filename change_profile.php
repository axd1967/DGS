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

$TranslateGroups[] = "Users";

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

   if( $emailnotify == 0 or empty($email) )
      $sendemail = '';

   if( $emailnotify >= 1 )
      $sendemail = 'ON';

   if( $emailnotify >= 2 )
      $sendemail .= ',MOVE,MESSAGE';

   if( $emailnotify == 3 )
      $sendemail .= ',BOARD';

   $boardcoords = ( $coordsleft ? LEFT : 0 ) + ( $coordsup ? UP : 0 ) +
      ( $coordsright ? RIGHT : 0 ) + ( $coordsdown ? DOWN : 0 ) +
      ( $smoothedge ? SMOOTH_EDGE : 0 );

   $menudirection = ( $menudir == 'HORIZONTAL' ? 'HORIZONTAL' : 'VERTICAL' );

   $query = "UPDATE Players SET " .
       "Name='" . trim($name) . "', " .
       "Email='" . trim($email) . "', " .
       "Rank='" . trim($rank) . "', " .
       "Open='" . trim($open) . "', " .
       "Stonesize=$stonesize, " .
       "Boardcoords=$boardcoords, " .
       "MenuDirection='$menudirection', " .
       "Woodcolor=$woodcolor, " .
       "Button=$button, " .
       "SendEmail='$sendemail', ";

   list($lang,$enc) = explode('.', $language);

   if( $language === 'C' or ( $language !== $player_row['Lang'] and
                              array_key_exists($lang, $known_languages) and
                              array_key_exists($enc, $known_languages[$lang])) )
     {
       $query .= "Lang='$language', ";
     }

   if( $nightstart != $player_row["Nightstart"] ||
       $timezone != $player_row["Timezone"] )
   {
      putenv("TZ=$timezone" );

      $query .= "ClockChanged='Y', ";

      // ClockUsed is uppdated only once a day to prevent ternal night...
      // $query .= "ClockUsed=" . get_clock_used($nightstart) . ", ";
   }


   $newrating = convert_to_rating($rating, $ratingtype);

   if( $player_row["RatingStatus"] != 'RATED' and is_numeric($newrating) and
       ( $ratingtype != 'dragonrating' or !is_numeric($player_row["Rating2"])
         or  abs($newrating - $player_row["Rating2"]) > 0.005 ) )
   {
      $query .= "Rating=$newrating, " .
         "InitialRating=$newrating, " .
         "Rating2=$newrating, " .
         "RatingMax=$newrating+200+GREATEST(1600-($newrating),0)*2/15, " .
         "RatingMin=$newrating-200-GREATEST(1600-($newrating),0)*2/15, " .
         "RatingStatus='INIT', ";

   }

   $query .= "Timezone='$timezone', " .
       "Nightstart=$nightstart" .
       " WHERE ID=" . $player_row['ID'];

   mysql_query( $query )
      or error("mysql_query_failed");

   include_all_translate_groups($player_row);
   $msg = urlencode(T_('Profile updated!'));

   jump_to("userinfo.php?uid=" . $player_row["ID"] . "&msg=$msg");
}
?>