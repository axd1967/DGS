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


{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $my_id = $player_row["ID"];

   if( $del ) 
   {
      // delete messages

      if( $del == 'all' )
      {
         $result = mysql_query("UPDATE Messages SET Flags=CONCAT_WS(',',Flags,'DELETED') " .
                               "WHERE Flags NOT ( Flags LIKE '%NEW%' OR " .
                               "Flags LIKE '%REPLY REQUIRED%' )");
      }
      else
      {
         $result = mysql_query("UPDATE Messages SET Flags=CONCAT_WS(',',Flags,'DELETED') " .
                               "WHERE ID=$del AND NOT ( Flags LIKE '%NEW%' OR " .
                               "Flags LIKE '%REPLY REQUIRED%' )");
      }
   }


   $result = mysql_query("SELECT UNIX_TIMESTAMP(Messages.Time) AS date, " .
                         "Messages.ID AS mid, Messages.Subject, Messages.Flags, " . 
                         "Players.Name AS sender " .
                         "FROM Messages, Players " .
                         "WHERE To_ID=$my_id AND From_ID=Players.ID " .
                         "AND NOT (Messages.Flags LIKE '%DELETED%') " .
                         "ORDER BY Time DESC") or die ( mysql_error());


   start_page("Messages", true, $logged_in, $player_row );


   echo "<table border=3 align=center>\n";
   echo "<tr><th></th><th>From</th><th>Subject</th><th>Date</th><th>Del</th></tr>\n";



   while( $row = mysql_fetch_array( $result ) )
   {
      echo "<tr>";


      if( !(strpos($row["Flags"],'NEW') === false) )
      {
         echo "<td bgcolor=\"00F464\">New</td>\n";        
      }
      else if( !(strpos($row["Flags"],'REPLIED') === false) )
      {
         echo "<td bgcolor=\"FFEE00\">Replied</td>\n";        
      }
      else if( !(strpos($row["Flags"],'REPLY REQUIRED') === false) )
      {
         echo "<td bgcolor=\"FFA27A\">Reply!</td>\n";
      }
      else
      {
         echo "<td></td>\n";
      }

      echo "<td><A href=\"show_message.php?mid=" . $row["mid"] . "\">" .
         $row["sender"] . "</A></td>\n" . 
         "<td>" . make_html_safe($row["Subject"]) . "</td>\n" .
         "<td>" . date($date_fmt, $row["date"]) . "</td>\n";

      if( strpos($row["Flags"],'NEW') === false and 
          ( strpos($row["Flags"],'REPLY REQUIRED') === false or
            !(strpos($row["Flags"],'REPLIED') === false) ) )
         echo "<td align=center><a href=\"messages.php?del=" . $row["mid"] . "\">" .
            "<img width=15 height=16 border=0 src=\"images/trashcan.gif\"></A></td>\n";

      echo "</tr>\n";
        
   }

   echo "</table>
    <p>
    <table width=\"100%\" border=0 cellspacing=0 cellpadding=4>
      <tr align=\"center\">
        <td><B><A href=\"new_message.php\">Send message</A></B></td>
        <td><B><A href=\"messages.php?del=all\"> Delete all read messages</A></B></td>
      </tr>
    </table>
";

   end_page(false);
}
?>
