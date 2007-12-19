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

// Update rating

chdir( '../' );
require_once( "include/std_functions.php" );
require_once( "include/rating.php" );

{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low');

   if( ($lim=@$_REQUEST['limit']) > '' )
      $limit = " LIMIT $lim";
   else
      $limit = "";

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();
   if( $lim > '' )
      $page_args['limit'] = $lim;

   start_html( 'recalculate_ratings2', 0);

//echo ">>>> One shot fix. Do not run it again."; end_html(); exit;
   if( $do_it=@$_REQUEST['do_it'] )
   {
      function dbg_query($s) {
        if( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
        echo " --- fixed. ";
      }
      echo "<p>*** Fixes errors ***"
         ."<br>".anchor(make_url($page, $page_args), 'Just show it')
         ."</p>";
   }
   else
   {
      function dbg_query($s) { echo " --- query:<BR>$s; ";}
      $tmp = array_merge($page_args,array('do_it' => 1));
      echo "<p>(just show needed queries)"
         ."<br>".anchor(make_url($page, $page_args), 'Show it again')
         ."<br>".anchor(make_url($page, $tmp), '[Validate it]')
         ."</p>";
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
       "AND white.ID=White_ID AND white.RatingStatus!='' " .
       "AND black.ID=Black_ID AND black.RatingStatus!='' " .
       "ORDER BY Lastchanged,gid $limit";

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
   mysql_free_result($result);
   echo "\n<p></p>Finished!<br>$count/$tot rated games.\n";


   echo "<hr>Done!!!\n";
   end_html();
}
?>
