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

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);
    
   if( !$logged_in )
      error("not_logged_in");


   $my_id = $player_row["ID"];

   start_page("Status", true, $logged_in, $player_row );

   echo "<center>";

   if( $msg )
      echo "<p><b><font color=green>$msg</font></b><hr>";

   echo "
    <table border=3>
       <tr><td>Name:</td> <td>" . $player_row["Name"] . "</td></tr>
       <tr><td>Userid:</td> <td>" . $player_row["Handle"] . "</td></tr>
       <tr><td>Open for matches:</td> <td>" . $player_row["Open"] . "</td></tr>
       <tr><td>Rating:</td> <td>";  echo_rating($player_row["Rating"]); 
   echo "</td></tr>
       <tr><td>Rank info:</td> <td>" . $player_row["Rank"] . "</td></tr>
    </table>
    <p>";


   $result = mysql_query("SELECT UNIX_TIMESTAMP(Messages.Time) AS date, " . 
                         "Messages.*, Players.Name AS sender " . 
                         "FROM Messages, Players " .
                         "WHERE To_ID=$my_id " .
                         "AND (Messages.Flags LIKE '%NEW%' OR Messages.Flags LIKE '%REPLY REQUIRED%') " .
                         "AND From_ID=Players.ID " .
                         "ORDER BY Time DESC") or error( "", true );


   if( mysql_num_rows($result) > 0 )
   {
      echo "<HR><B>New messages:</B><p>\n";
      echo "<table border=3>\n";
      echo "<tr><th></th><th>From</th><th>Subject</th><th>Date</th></tr>\n";



      while( $row = mysql_fetch_array( $result ) )
      {
         echo "<tr>";
         
         if( !(strpos($row["Flags"],'NEW') === false) )
         {
            echo "<td bgcolor=\"00F464\">New</td>\n";        
         }
         else if( !(strpos($row["Flags"],'REPLY REQUIRED') === false) )
         {
            echo "<td bgcolor=\"FFA27A\">Reply!</td>\n";
         }
         else
         {
            error("message_status_corrupt");
         }
            
         echo "<td><A href=\"show_message.php?mid=" . $row["ID"] . "\">" .
            $row["sender"] . "</A></td>\n" . 
            "<td>" . make_html_safe($row["Subject"]) . "</td>\n" .
            "<td>" . date($date_fmt, $row["date"]) . "</td></tr>\n";
      }

      echo "</table><p>\n";
   }



   // show games;

   $uid = $player_row["ID"];

   $result = mysql_query("SELECT Games.*, Players.Name FROM Games,Players " .
                         "WHERE ToMove_ID=$uid AND Status!='INVITED' AND Status!='FINISHED' " .
                         "AND Players.ID!=$uid " .
                         "AND (Black_ID=Players.ID OR White_ID=Players.ID) " .
                         "ORDER BY Lastchanged,Games.ID");

   echo "<HR><B>Your turn to move in the following games:</B><p>\n";
   echo "<table border=3>\n";
   echo "<tr><th>Opponent</th><th>Color</th><th>Size</th><th>Handicap</th><th>moves</th></tr>\n";


   while( $row = mysql_fetch_array( $result ) )
   {
      if( $uid == $row["Black_ID"] )
         $col = "Black";
      else
         $col = "White";

      echo "<tr><td><A href=\"game.php?gid=" . $row["ID"] . "\">" . 
         $row["Name"] . "</td>\n" .
         "<td>$col</td>" .
         "<td>" . $row["Size"] . "</td>\n" .
         "<td>" . $row["Handicap"] . "</td>\n" .
         "<td>" . $row["Moves"] . "</td>\n" .
         "</tr>\n";
   }

   echo "</table>\n";


   echo "
    <p>
    <table width=\"100%\" border=0 cellspacing=0 cellpadding=4>
      <tr align=\"center\">
        <td><B><A href=\"userinfo.php?uid=$uid\">Show/edit userinfo</A></B></td>
        <td><B><A href=\"show_games.php?uid=$uid\">Show running games</A></B></td>
        <td><B><A href=\"show_games.php?uid=$uid&finished=1\">Show finished games</A></B></td>
      </tr>
    </table>
";

   echo "</center>";
   end_page(false);
}
?>
