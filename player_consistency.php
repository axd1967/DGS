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

require_once( "include/std_functions.php" );

{
   connect2mysql();

   disable_cache();

   $result = mysql_query("SELECT Players.ID,count(*) AS Run,Running FROM Games,Players " .
                         "WHERE Status!='INVITED' AND Status!='FINISHED' " .
                         "AND (Players.ID=White_ID OR Players.ID=Black_ID) " .
                         "GROUP BY Players.ID HAVING Run!=Running")
      or die("A: " . mysql_error());


   while( $row = mysql_fetch_array($result) )
   {
      extract($row);
      echo "<p>ID: $ID  Running: $Running  Should be: $Run   ---   fixed.";

      mysql_query("UPDATE Players SET Running=$Run WHERE ID=$ID LIMIT 1")
         or die("B: " . mysql_error());
   }

   echo "Running Done.<br>";



   $result = mysql_query("SELECT Players.ID,count(*) AS Fin,Finished FROM Games,Players " .
                         "WHERE Status='FINISHED' " .
                         "AND (Players.ID=White_ID OR Players.ID=Black_ID) " .
                         "GROUP BY Players.ID HAVING Fin!=Finished")
      or die("C: " . mysql_error());

   while( $row = mysql_fetch_array($result) )
   {
      extract($row);
      echo "<p>ID: $ID  Finished: $Finished  Should be: $Fin   ---   fixed.";

      mysql_query("UPDATE Players SET Finished=$Fin WHERE ID=$ID LIMIT 1")
         or die("D: " . mysql_error());
   }

   echo "Finished Done.<br>";



   $result = mysql_query("SELECT Players.ID,count(*) AS W, Won FROM Games,Players " .
                         "WHERE Status='FINISHED' " .
                         "AND ((Black_ID=Players.ID AND Score<0) " .
                         "OR (White_ID=Players.ID AND Score>0)) " .
                         "GROUP BY Players.ID HAVING W!=Won")
      or die("E: " . mysql_error());

   while( $row = mysql_fetch_array($result) )
   {
      extract($row);
      echo "<p>ID: $ID  Won: $Won  Should be: $W   ---   fixed.";

      mysql_query("UPDATE Players SET Won=$W WHERE ID=$ID LIMIT 1")
         or die("F: " . mysql_error());
   }

   echo "Won Done.<br>";




   $result = mysql_query("SELECT Players.ID,count(*) AS L, Lost FROM Games,Players " .
                         "WHERE Status='FINISHED' " .
                         "AND ((Black_ID=Players.ID AND Score>0) " .
                         "OR (White_ID=Players.ID AND Score<0)) " .
                         "GROUP BY Players.ID HAVING L!=Lost")
      or die("G: " . mysql_error());

   while( $row = mysql_fetch_array($result) )
   {
      extract($row);
      echo "<p>ID: $ID  lost: $Lost  Should be: $L   ---   fixed.";

      mysql_query("UPDATE Players SET Lost=$L WHERE ID=$ID LIMIT 1")
         or die("H: " . mysql_error());
   }

   echo "Lost Done.<br>";


}
?>