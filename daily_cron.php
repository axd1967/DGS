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
   connect2mysql();


   $delete_msgs = false;
   $messege_timelimit = 92;
   $invite_timelimit = 92;

// Delete old messages

   if( $delete_msgs )
   {

      $result = mysql_query( "SELECT ID FROM Players" );

      while( $row = mysql_fetch_array( $result ) )
      {
         $id = $row["ID"];

         // delete read messages

         mysql_query("DELETE FROM Messages$id WHERE " .
                     "( Info='None' OR Info='REPLIED' ) AND " .
                     "TO_DAYS(Now())-TO_DAYS(Time) > $messege_timelimit" );

         //delete old invitations

         $result2 = mysql_query( "SELECT Game_ID FROM Messages$id WHERE " .
                                 "Type='INVITATION' AND " .
                                 "TO_DAYS(Now())-TO_DAYS(Time) > $invite_timelimit" );

         if( mysql_num_rows($result2) > 0 )
         {
            while( $row2 = mysql_fetch_array( $result2 ) )
            {

               mysql_query( "DELETE FROM Games WHERE ID=" . $row["Game_ID"] . 
                            " AND Status='INVITED'" .
                            " AND ( Black_ID=$id OR White_ID=$id ) " );
            }

            mysql_query( "DELETE FROM Messages$id WHERE " .
                         "Type='INVITATION' AND " .
                         "TO_DAYS(Now())-TO_DAYS(Time) > $invite_timelimit" );
      
         }

      }  

   }





// Update rating


   $query = "SELECT Games.ID as gid ". 
       "FROM Games, Players as white, Players as black " .
       "WHERE Status='FINISHED' AND Rated='Y' " .
       "AND white.ID=White_ID AND black.ID=Black_ID ".
       "AND ( white.RatingStatus='READY' OR white.RatingStatus='RATED' ) " .
       "AND ( black.RatingStatus='READY' OR black.RatingStatus='RATED' ) " .
       "ORDER BY Lastchanged, gid";


   $result = mysql_query( $query );
   echo mysql_num_rows($result);
   while( $row = mysql_fetch_array( $result ) )
   {
      update_rating($row["gid"]);    
   }

   $result = mysql_query( "UPDATE Players SET RatingStatus='READY', Lastaccess=Lastaccess " .
                          "WHERE RatingStatus='INIT' " );
  
} 
?>