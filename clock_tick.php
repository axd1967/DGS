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

if( !$is_down )
{
   connect2mysql();
    
    
   $hour = gmdate('G', $NOW);
   $day_of_week = gmdate('w', $NOW); 

   // Check that ticks are not too frequent

   $result = mysql_query( "SELECT MIN($NOW-UNIX_TIMESTAMP(Lastchanged)) AS timediff FROM Clock WHERE ID=0 OR ID=12" );
    

   $row = mysql_fetch_array( $result );

   if( $row['timediff'] < 3600/$tick_frequency-10 )
      exit;


   // Now increase clocks that are not sleeping

   $query = "UPDATE Clock SET Ticks=Ticks+1, Lastchanged=FROM_UNIXTIME($NOW) " .
       "WHERE ((ID>$hour OR ID<". ($hour-8) . ') AND ID< '. ($hour+16) . ')';
    
   if( $day_of_week > 0 and $day_of_week < 6 )
      $query .= ' OR (ID>=100 AND (ID>' . ($hour+100) . ' OR ID<'. ($hour+92) . 
         ') AND ID< '. ($hour+116) . ')';
   
   
   mysql_query( $query );




   // Check if any game has timed out
    
   $result = mysql_query('SELECT Games.*, Games.ID as gid, Clock.Ticks as ticks, ' .
                         'black.Name as blackname, white.Name as whitename ' . 
                         'FROM Games, Clock ,Players as white, Players as black ' .
                         'WHERE Status!="INVITED" AND Status!="FINISHED" ' .
                         'AND ( Maintime>0 OR Byotime>0 ) ' .
                         'AND Games.ClockUsed=Clock.ID ' . 
                         'AND white.ID=White_ID AND black.ID=Black_ID' ); 

   while($row = mysql_fetch_array($result))
   {
      extract($row);
      $ticks = $ticks - $LastTicks;
      $hours = ( $ticks > 0 ? (int)(($ticks-1) / $tick_frequency) : 0 );
        
      if( $ToMove_ID == $Black_ID )
      {
         time_remaining( $hours, $Black_Maintime, $Black_Byotime, 
         $Black_Byoperiods, $Maintime,
         $Byotype, $Byotime, $Byoperiods, false);
            
         $time_is_up = ( $Black_Maintime == 0 and $Black_Byotime == 0 );
      }
      else if( $ToMove_ID == $White_ID )
      {
         time_remaining( $hours, $White_Maintime, $White_Byotime, 
         $White_Byoperiods, $Maintime,
         $Byotype, $Byotime, $Byoperiods, false);
            
         $time_is_up = ( $White_Maintime == 0 and $White_Byotime == 0 );
      }



      if( $time_is_up )
      {

         $score = ( $ToMove_ID == $Black_ID ? 2000 : -2000 );

         $query = "UPDATE Games SET " .
             "Status='FINISHED', " .
             "Lastchanged=FROM_UNIXTIME($NOW), " .
             "ToMove_ID=0, " .
             "Score=$score, " .
             "Flags=0" .
             " WHERE ID=$gid";

         mysql_query( $query );

         if( mysql_affected_rows() != 1)
            error("Couldn't update game.");

         // Send messages to the players
         $Text = "The result in the game <A href=\"game.php?gid=$gid\">" . 
             "$whitename (W)  vs. $blackname (B) </A>" . 
             "was: <p><center>" . score2text($score,true) . "</center></BR>";
          
         mysql_query( "INSERT INTO Messages SET " .
                      "From_ID=" . $White_ID . ", " .
                      "To_ID=" . $Black_ID . ", " .
                      "Time=FROM_UNIXTIME($NOW), " .
                      "Game_ID=$gid, Subject='Game result', Text='$Text'" );

         mysql_query( "INSERT INTO Messages SET " .
                      "From_ID=" . $Black_ID . ", " .
                      "To_ID=" . $White_ID . ", " .
                      "Time=FROM_UNIXTIME($NOW), " .
                      "Game_ID=$gid, Subject='Game result', Text='$Text'" );
          
         // Notify players
         mysql_query( "UPDATE Players SET Notify='NEXT' " .
                      "WHERE (ID='$Black_ID' OR ID='$White_ID') " .
                      "AND SendEmail LIKE '%ON%' AND Notify='NONE'" ) ;

      }
   }

}


?>
