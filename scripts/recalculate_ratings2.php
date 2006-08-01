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

// Update rating

require( "include/std_functions.php" );
require( "include/rating.php" );

{
   connect2mysql();


   mysql_query( "UPDATE Players SET " .
                "Rating2=InitialRating, " .
                "RatingMax=InitialRating+200+GREATEST(1600-InitialRating,0)*2/15, " .
                "RatingMin=InitialRating-200-GREATEST(1600-InitialRating,0)*2/15" )
      or die(mysql_error());

   mysql_query( "DELETE FROM Ratinglog" );

   $query = "SELECT Games.ID as gid ".
       "FROM Games, Players as white, Players as black " .
       "WHERE Status='FINISHED' AND Rated='Done' " .
       "AND white.ID=White_ID AND black.ID=Black_ID ".
       "AND ( white.RatingStatus='READY' OR white.RatingStatus='RATED' ) " .
       "AND ( black.RatingStatus='READY' OR black.RatingStatus='RATED' ) " .
       "ORDER BY Lastchanged, gid";


   $result = mysql_query( $query );

   while( $row = mysql_fetch_array( $result ) )
   {
      echo "<p>Game " . $row["gid"] . ":<br>";
      update_rating2($row["gid"]);
   }
}
?>
