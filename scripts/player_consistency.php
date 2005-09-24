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


// Checks and fixes errors in Running, Finished, Won and Lost fields in the database.

chdir( '../' );
require_once( "include/std_functions.php" );

{
   disable_cache();

   connect2mysql();

  $logged_in = who_is_logged( $player_row);

  if( !$logged_in )
    error("not_logged_in");

  $player_level = (int)$player_row['admin_level'];
  if( !($player_level & ADMIN_DATABASE) )
    error("adminlevel_too_low");


   start_html( 'player_consistency', 0);

   if( $do_it=@$_REQUEST['do_it'] )
   {
      function dbg_query($s) { 
        if( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
        echo " --- fixed. ";
      }
      echo "<p>*** Fixes errors:<br>";
   }
   else
   {
      function dbg_query($s) { echo " --- query:<BR>$s; ";}
      echo "<p>(just show queries needed):<br>";
   }


   if( ($uid=@$_REQUEST['uid']) > 0 )
      $where = " AND Players.ID=$uid";
   else
      $where = "" ;

   if( ($lim=@$_REQUEST['limit']) > 0 )
      $limit = " LIMIT $lim";
   else
      $limit = "";

   $is_rated = " AND Games.Rated!='N'" ;
   //$is_rated.= " AND !(Games.Moves < ".DELETE_LIMIT."+Games.Handicap)";


/********* CAUTION:
  This test does not detect an inconsistency:
   because of count(Games.ID) and LEFT JOIN Games ON,
   a Run=0 case return nothing even if Running!=0.
  The fommowing tests have a similar defect.
**********/

   $query = "SELECT Players.ID, count(Games.ID) AS Run, Running FROM Players " .
            "LEFT JOIN Games ON Status!='INVITED' AND Status!='FINISHED' " .
            "AND (Players.ID=White_ID OR Players.ID=Black_ID) " .
            "GROUP BY Players.ID HAVING Run!=Running$where$limit";
   $result = mysql_query( $query)
      or die("Run.A: " . mysql_error());

   while( $row = mysql_fetch_assoc($result) )
   {
      extract($row);
      echo "<br>ID: $ID  Running: $Running  Should be: $Run";

      dbg_query("UPDATE Players SET Running=$Run WHERE ID=$ID LIMIT 1");
   }

   echo "<br>Running Done.";


   $query = "SELECT Players.ID, count(Games.ID) AS Fin, Finished FROM Players " .
            "LEFT JOIN Games ON Status='FINISHED' " .
            "AND (Players.ID=White_ID OR Players.ID=Black_ID) " .
            "GROUP BY Players.ID HAVING Fin!=Finished$where$limit";
   $result = mysql_query( $query)
      or die("Fin.A: " . mysql_error());

   while( $row = mysql_fetch_assoc($result) )
   {
      extract($row);
      echo "<br>ID: $ID  Finished: $Finished  Should be: $Fin";

      dbg_query("UPDATE Players SET Finished=$Fin WHERE ID=$ID LIMIT 1");
   }

   echo "<br>Finished Done.";


   $query = "SELECT Players.ID, count(Games.ID) AS Rat, RatedGames FROM Players " .
            "LEFT JOIN Games ON Status='FINISHED'$is_rated " .
            "AND (Players.ID=White_ID OR Players.ID=Black_ID) " .
            "GROUP BY Players.ID HAVING Rat!=RatedGames$where$limit";
   $result = mysql_query( $query)
      or die("Rat.A: " . mysql_error());

   while( $row = mysql_fetch_assoc($result) )
   {
      extract($row);
      echo "<br>ID: $ID  Rated: $RatedGames  Should be: $Rat";

      dbg_query("UPDATE Players SET RatedGames=$Rat WHERE ID=$ID LIMIT 1");
   }

   echo "<br>Rated Done.";


   $query = "SELECT Players.ID, count(Games.ID) AS Win, Won FROM Players " .
            "LEFT JOIN Games ON Status='FINISHED'$is_rated " .
            "AND ((Black_ID=Players.ID AND Score<0) " .
              "OR (White_ID=Players.ID AND Score>0)) " .
            "GROUP BY Players.ID HAVING Win!=Won$where$limit";
   $result = mysql_query( $query)
      or die("Won.A: " . mysql_error());

   while( $row = mysql_fetch_assoc($result) )
   {
      extract($row);
      echo "<br>ID: $ID  Won: $Won  Should be: $Win";

      dbg_query("UPDATE Players SET Won=$Win WHERE ID=$ID LIMIT 1");
   }

   echo "<br>Won Done.";


   $query = "SELECT Players.ID, count(Games.ID) AS Los, Lost FROM Players " .
            "LEFT JOIN Games ON Status='FINISHED'$is_rated " .
            "AND ((Black_ID=Players.ID AND Score>0) " .
              "OR (White_ID=Players.ID AND Score<0)) " .
            "GROUP BY Players.ID HAVING Los!=Lost$where$limit";
   $result = mysql_query( $query)
      or die("Los.A: " . mysql_error());

   while( $row = mysql_fetch_assoc($result) )
   {
      extract($row);
      echo "<br>ID: $ID  Lost: $Lost  Should be: $Los";

      dbg_query("UPDATE Players SET Lost=$Los WHERE ID=$ID LIMIT 1");
   }

   echo "<br>Lost Done.";


   //RatedGames = Won + Lost + Jigo consistency
   $query = "SELECT Players.ID, count(Games.ID) AS Jigo, Won, Lost, RatedGames FROM Players " .
            "LEFT JOIN Games ON Status='FINISHED'$is_rated " .
            "AND (Players.ID=White_ID OR Players.ID=Black_ID) " .
            "AND Score=0 " .
            "GROUP BY Players.ID HAVING RatedGames!=(Won+Lost+Jigo)$where$limit";
   $result = mysql_query( $query)
      or die("Cnt.A: " . mysql_error());

   $err = 0;
   while( $row = mysql_fetch_assoc($result) )
   {
      extract($row);
      echo "<br>ID: $ID  Counts: (Rat=$RatedGames) != (Won=$Won) + (Los=$Lost) + (Jig=$Jigo)";
      $err++;
   }
   if( $err )
      echo "<br>--- $err error(s). MAYBE fixed with: scripts/recalculate_ratings2.php";

   echo "<br>Counts Done.";


   //RatedGames && Ratinglog consistency
   $query = "SELECT Players.ID, count(Ratinglog.ID) AS Log, RatedGames FROM Players " .
            "LEFT JOIN Ratinglog ON uid=Players.ID " .
            "GROUP BY Players.ID HAVING Log!=RatedGames$where$limit";
   $result = mysql_query( $query)
      or die("Log.A: " . mysql_error());

   $err = 0;
   while( $row = mysql_fetch_assoc($result) )
   {
      extract($row);
      echo "<br>ID: $ID  Ratinglog: $Log  Should be: $RatedGames";
      $err++;
   }
   if( $err )
      echo "<br>--- $err error(s). MAYBE fixed with: scripts/recalculate_ratings2.php";

   echo "<br>RatinLog Done.";


   //Various checks
   $query = "SELECT Players.ID, ClockUsed, " .
                         "RatingStatus, Rating2, RatingMin, RatingMax " .
                         "FROM Players " .
                         "WHERE (" .
                           "(RatingStatus='RATED' AND (Rating2>=RatingMax OR Rating2<=RatingMin) ) " .
                           "OR NOT((ClockUsed>=0 AND ClockUsed<24) OR (ClockUsed>=100 AND ClockUsed<124))" .
            ")$where$limit";
   $result = mysql_query( $query)
      or die("Mis.A: " . mysql_error());

   $err = 0;
   while( $row = mysql_fetch_assoc($result) )
   {
      extract($row);
      echo "<br>ID: $ID  Misc: ClockUsed=$ClockUsed, $RatingMin &lt; $Rating2 &lt; $RatingMax.";
      $err++;
   }
   if( $err )
      echo "<br>--- $err error(s). Must be fixed by hand.";

   echo "<br>Misc Done.";


   end_html();
}
?>