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

   echo '
    <table border=3>
       <tr><td><b>' . _("Name") . '</b></td> <td>' . $player_row["Name"] . '</td></tr>
       <tr><td><b>' . _("Userid") . '</b></td> <td>' . $player_row["Handle"] . '</td></tr>
       <tr><td><b>' . _("Open for matches?") . '</b></td> <td>' . $player_row["Open"] . '</td></tr>
       <tr><td><b>' . _("Rating") . '</b></td> <td>';  echo_rating($player_row["Rating"]); 
   echo '</td></tr>
       <tr><td><b>Rank info</b></td> <td>' . $player_row["Rank"] . '</td></tr>
    </table>
    <p>';


   $result = mysql_query("SELECT UNIX_TIMESTAMP(Messages.Time) AS date, " . 
                         "Messages.*, Players.Name AS sender " . 
                         "FROM Messages, Players " .
                         "WHERE To_ID=$my_id " .
                         "AND (Messages.Flags LIKE '%NEW%' OR Messages.Flags LIKE '%REPLY REQUIRED%') " .
                         "AND From_ID=Players.ID " .
                         "ORDER BY Time DESC") or error( "mysql_query_failed", true );


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

   $query = "SELECT Black_ID,White_ID,Games.ID,Size,Handicap,Komi,Games.Moves," . 
       "UNIX_TIMESTAMP(Lastchanged) AS Time, " .
       "black.Name AS bName, white.Name AS wName " .
       "FROM Games " .
       "LEFT JOIN Players AS black ON black.ID=Black_ID " . 
       "LEFT JOIN Players AS white ON white.ID=White_ID " . 
       "WHERE ToMove_ID=$uid AND Status!='INVITED' AND Status!='FINISHED' " .
       "ORDER BY LastChanged, Games.ID";

   $result = mysql_query( $query ) or die(mysql_error());

   echo "<HR><B>" . _("Your turn to move in the following games:") . "</B><p>\n";
   echo "<table border=3>\n";
   echo "<tr><th>gid</th><th>" . _("Opponent") . "</th><th>" . _("Color") . "</th><th>" . _("Size") . "</th><th>" . _("Handicap") . "</th><th>" . _("Komi") . "</th><th>" ._("Moves") . "</th><th>" . _("Last moved") . "</th></tr>\n";


   while( $row = mysql_fetch_array( $result ) )
   {
      extract($row);

      $color = ( $uid == $Black_ID ? 'b' : 'w' );
      $opp_ID = ( $uid == $Black_ID ? $White_ID : $Black_ID );

      echo "<tr><td><a href=\"game.php?gid=$ID\"><font color=$gid_color><b>$ID</b></font></a></td>" .
         "<td><a href=\"userinfo.php?uid=$opp_ID\">" . 
         ( $uid == $Black_ID ? $wName : $bName ) . "</a></td>\n" . 

         "<td align=center><img src=\"17/$color.gif\" alt=$color></td>" .
         "<td>" . $Size . "</td>\n" .
         "<td>" . $Handicap . "</td>\n" .
         "<td>" . $Komi . "</td>\n" .
         "<td>" . $Moves . "</td>\n" .
         "<td>" . date($date_fmt, $Time) . "</td>\n" .
         "</tr>\n";
   }

   echo "</table>\n";


   echo "
    <p>
    <table width=\"100%\" border=0 cellspacing=0 cellpadding=4>
      <tr align=\"center\">
        <td><B><A href=\"userinfo.php?uid=$uid\">" . _("Show/edit userinfo") . "</A></B></td>
        <td><B><A href=\"show_games.php?uid=$uid\">" . _("Show running games") . "</A></B></td>
        <td><B><A href=\"show_games.php?uid=$uid&finished=1\">" . _("Show finished games") . "</A></B></td>
      </tr>
    </table>
";

   echo "</center>";
   end_page(false);
}
?>
