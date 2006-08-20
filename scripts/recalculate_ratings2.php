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

// Update rating

chdir( '../' );
require_once( "include/std_functions.php" );
require_once( "include/rating.php" );

{
   disable_cache();

   connect2mysql();

  $logged_in = who_is_logged( $player_row);

  if( !$logged_in )
    error("not_logged_in");

  $player_level = (int)$player_row['admin_level'];
  if( !($player_level & ADMIN_DATABASE) )
    error("adminlevel_too_low");

   if( ($lim=@$_REQUEST['limit']) > '' )
      $limit = " LIMIT $lim";
   else
      $limit = "";


   start_html( 'recalculate_ratings2', 0);

   if( $do_it=@$_REQUEST['do_it'] )
   {
      function dbg_query($s) { 
        if( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
        echo " --- fixed. ";
      }
      echo "<p></p>*** Fixes errors:<br>";
   }
   else
   {
      function dbg_query($s) { echo " --- query:<BR>$s; ";}
      echo "<p></p>(just show queries needed):<br>";
   }


   if( !($lim > 0) )
   {
      echo "<br>Reset Players' ratings";
      dbg_query( "UPDATE Players SET " .
                 "Rating2=InitialRating, " .
                 "RatingMax=InitialRating+200+GREATEST(1600-InitialRating,0)*2/15, " .
                 "RatingMin=InitialRating-200-GREATEST(1600-InitialRating,0)*2/15" );

      echo "<br>Reset Ratinglog";
      dbg_query( "DELETE FROM Ratinglog" );
   }

   $query = "SELECT Games.ID as gid ".
       "FROM Games, Players as white, Players as black " .
       "WHERE Status='FINISHED' AND Rated!='N' " . //redo Rated='Done' and do missed Rated='Y' 
       "AND white.ID=White_ID AND black.ID=Black_ID ".
       "AND ( NOT ISNULL(white.RatingStatus) ) " .
       "AND ( NOT ISNULL(black.RatingStatus) ) " .
       "ORDER BY Lastchanged, gid $limit";

   $result = mysql_query( $query )
           or die("<BR>" . mysql_error() );

   echo "<p></p>Game:";
   $count=0; $tot=0;
   while( $row = mysql_fetch_assoc( $result ) )
   {
      echo ' ' . $row["gid"];
      if( $do_it )
      {
         $rated_status = update_rating2($row["gid"], false/*=check_done*/); //0=rated game
         if( $rated_status == 0 )
            $count++;
         else
            echo '--';
      }
      $tot++;
   }
   echo "\n<p></p>Finished!<br>$count/$tot rated games.\n";


   end_html();
}
?>
