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

//chdir( '../' ); //if moved in /scripts
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

   //$alt_where = " AND !(Games.Moves < ".DELETE_LIMIT."+Games.Handicap)";
   $alt_where = " AND Games.Rated!='N'" ;



   $result = mysql_query("SELECT Players.ID,count(*) AS Run,Running FROM Games,Players " .
                         "WHERE Status!='INVITED' AND Status!='FINISHED'$where " .
                         "AND (Players.ID=White_ID OR Players.ID=Black_ID) " .
                         "GROUP BY Players.ID HAVING Run!=Running")
      or die("Run.A: " . mysql_error());


   while( $row = mysql_fetch_array($result) )
   {
      extract($row);
      echo "<br>ID: $ID  Running: $Running  Should be: $Run";

      dbg_query("UPDATE Players SET Running=$Run WHERE ID=$ID LIMIT 1");
   }

   echo "<br>Running Done.";



   $result = mysql_query("SELECT Players.ID,count(*) AS Fin,Finished FROM Games,Players " .
                         "WHERE Status='FINISHED'$where$alt_where " .
                         "AND (Players.ID=White_ID OR Players.ID=Black_ID) " .
                         "GROUP BY Players.ID HAVING Fin!=Finished")
      or die("Fin.A: " . mysql_error());

   while( $row = mysql_fetch_array($result) )
   {
      extract($row);
      echo "<br>ID: $ID  Finished: $Finished  Should be: $Fin";

      dbg_query("UPDATE Players SET Finished=$Fin WHERE ID=$ID LIMIT 1");
   }

   echo "<br>Finished Done.";



   $result = mysql_query("SELECT Players.ID,count(*) AS W, Won FROM Games,Players " .
                         "WHERE Status='FINISHED'$where$alt_where " .
                         "AND ((Black_ID=Players.ID AND Score<0) " .
                         "OR (White_ID=Players.ID AND Score>0)) " .
                         "GROUP BY Players.ID HAVING W!=Won")
      or die("Won.A: " . mysql_error());

   while( $row = mysql_fetch_array($result) )
   {
      extract($row);
      echo "<br>ID: $ID  Won: $Won  Should be: $W";

      dbg_query("UPDATE Players SET Won=$W WHERE ID=$ID LIMIT 1");
   }

   echo "<br>Won Done.";



   $result = mysql_query("SELECT Players.ID,count(*) AS L, Lost FROM Games,Players " .
                         "WHERE Status='FINISHED'$where$alt_where " .
                         "AND ((Black_ID=Players.ID AND Score>0) " .
                         "OR (White_ID=Players.ID AND Score<0)) " .
                         "GROUP BY Players.ID HAVING L!=Lost")
      or die("Los.A: " . mysql_error());

   while( $row = mysql_fetch_array($result) )
   {
      extract($row);
      echo "<br>ID: $ID  Lost: $Lost  Should be: $L";

      dbg_query("UPDATE Players SET Lost=$L WHERE ID=$ID LIMIT 1");
   }

   echo "<br>Lost Done.";

}
?>