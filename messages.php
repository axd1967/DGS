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
         $result = mysql_query("UPDATE Messages ". 
                               "SET Flags=CONCAT_WS(',',Flags,'DELETED'), Time=Time " .
                               "WHERE To_ID=$my_id AND " .
                               "NOT ( Flags LIKE '%NEW%' OR Flags LIKE '%REPLY REQUIRED%' )");
      }
      else
      {
         $query = "UPDATE Messages " . 
             "SET Flags=" . 
             ( $del > 0 ? "CONCAT_WS(',',Flags,'DELETED')" : "REPLACE(Flags,'DELETED','')" ) .
             ", Time=Time " .
             "WHERE To_ID=$my_id AND ID=" . abs($del) . " AND " .
             "NOT ( Flags LIKE '%NEW%' OR Flags LIKE '%REPLY REQUIRED%' )";
         
         mysql_query($query);

      }
   }


   $query = "SELECT UNIX_TIMESTAMP(Messages.Time) AS date, " .
       "Messages.ID AS mid, Messages.Subject, Messages.Flags, " . 
       "Players.Name AS sender " .
       "FROM Messages, Players ";

   if( $sent==1 )
      $query .= "WHERE From_ID=$my_id AND To_ID=Players.ID ";
   else
   {
      $query .= "WHERE To_ID=$my_id AND From_ID=Players.ID ";
      
      if( !($all==1) )
         $query .= "AND NOT (Messages.Flags LIKE '%DELETED%') ";
      else
         $all_str = "&all=1";
   }


   if(!($limit > 0 )) 
      $limit = 0;

   $query .= "ORDER BY Time DESC LIMIT $limit,$MessagesPerPage";

   $result = mysql_query( $query ) 
       or die ( mysql_error("mysql_query_failed", true));


   start_page("Messages", true, $logged_in, $player_row );


   echo "<table border=3 align=center>\n";
   echo "<tr>" . ($sent==1 ? "<th>To" : "<th width=40></th><th>From") . 
      "</th><th>Subject</th><th>Date</th>";
   
   if( !($sent==1) ) 
      echo "<th>Del</th></tr>\n";



   while( $row = mysql_fetch_array( $result ) )
   {
      echo "<tr";

      if( !($sent==1) and !(strpos($row["Flags"],'DELETED') === false) )
      {
         $mid = -$row["mid"];
         echo " bgcolor=ffc0b0>";
      }
      else
      {
         $mid = $row["mid"];
         echo ">";
      }
      
      if( !($sent==1) )
      {
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
            echo "<td>&nbsp;</td>\n";
         }
      }

      echo "<td><A href=\"show_message.php?mid=" . $row["mid"] . "\">" .
         $row["sender"] . "</A></td>\n" . 
         "<td>" . make_html_safe($row["Subject"]) . "&nbsp;</td>\n" .
         "<td>" . date($date_fmt, $row["date"]) . "</td>\n";

      if( !($sent==1) and strpos($row["Flags"],'NEW') === false and 
          ( strpos($row["Flags"],'REPLY REQUIRED') === false or
            !(strpos($row["Flags"],'REPLIED') === false) ) )
      {
         echo "<td align=center><a href=\"messages.php?del=" . $mid . $all_str .
            "\"> <img width=15 height=16 border=0 src=\"images/trashcan.gif\"></A></td>\n";
      }
      echo "</tr>\n";
        
   }

   echo "</table>
    <p>
    <table width=\"100%\" border=0 cellspacing=0 cellpadding=4>
      <tr align=\"center\">
        <td><B><A href=\"new_message.php\">Send message</A></B></td>\n";

   if( $sent==1 )
      echo "        <td><B><A href=\"messages.php\">Show recieved messages</A></B></td>\n";
   else
   {
      if( $all==1 )
         echo "        <td><B><A href=\"messages.php\">Don't show deleted</A></B></td>\n";
      else
         echo "        <td><B><A href=\"messages.php?all=1\">Show all</A></B></td>\n";

      echo "        <td><B><A href=\"messages.php?sent=1\">Show sent messages</A></B></td>\n";
   }

   echo "        <td><B><A href=\"messages.php?del=all$all_str\">Delete all</A></B></td>
      </tr>
    </table>
";

   end_page(false);
}
?>
