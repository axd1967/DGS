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
   if( !$uid )
      error("no_uid");


   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   $result = mysql_query( "SELECT Name, Handle FROM Players WHERE ID=$uid" );

   if( mysql_num_rows($result) != 1 )
      error("unknown_user");


   $user_row = mysql_fetch_array($result);

   if( $finished )
   {
      $result = mysql_query("SELECT Games.*, Players.Name, Players.Handle, Players.ID as uid " .
                            "FROM Games,Players " .
                            " WHERE Status='FINISHED' AND " . 
                            "(( Black_ID=$uid AND White_ID=Players.ID ) OR " .
                            "( White_ID=$uid AND Black_ID=Players.ID )) " .
                            "ORDER BY Lastchanged DESC,ID DESC");
      start_page("Finished games for " . $user_row["Name"], true, $logged_in, $player_row );
   }
   else
   {
      $result = mysql_query("SELECT Games.*, Players.Name, Players.Handle, Players.ID as uid " .
                            "FROM Games,Players " .
                            " WHERE  Status!='INVITED' AND Status!='FINISHED' AND " . 
                            "(( Black_ID=$uid AND White_ID=Players.ID ) OR " .
                            "( White_ID=$uid AND Black_ID=Players.ID )) " . 
                            "ORDER BY Lastchanged DESC");
      start_page("Running games for " . $user_row["Name"], true, $logged_in, $player_row );
   }

   echo "<center><h4>" . ( $finished ? "Finished" : "Running" ) . " Games for <A href=\"userinfo.php?uid=$uid\">" . $user_row["Name"] . " (" . $user_row["Handle"] . ")</A></H4></center>\n";
   echo "<table border=3 align=center>\n";
   echo "<tr><th>Opponent</th><th>Color</th><th>Size</th><th>Handicap</th><th>Komi</th>" .
      "<th>" .( $finished ? "Score" : "Moves" ) . "</th><th>sgf</th><th>html</th>";
   if( $finished ) echo "<th>win?</th>";
   
   echo "\n";


   while( $row = mysql_fetch_array( $result ) )
   {
      if( $uid == $row["Black_ID"] )
         $color = "b"; 
      else
         $color = "w"; 

      echo "<tr><td><A href=\"userinfo.php?uid=" . $row["uid"] . "\">" . $row["Name"] . "</td>" .
      "<td align=center><img src=\"17/$color.gif\"></td>" .
      "<td>" . $row["Size"] . "</td>" .
         "<td>" . $row["Handicap"] . "</td>" .
         "<td>" . $row["Komi"] . "</td>" .
         "<td>" . ($finished ? score2text($row["Score"], false) : $row["Moves"] ) . "</td>" .
      "<td><A href=\"sgf.php?gid=" . $row["ID"] . "\">sgf</td>" .
      "<td><A href=\"game.php?gid=" . $row["ID"] . "\">html</td>";
      if( $finished )
      {
         if( $color == "w" xor $row["Score"] > 0.0 )
            $image = 'no.gif';
         else
            $image = 'yes.gif';

         if( abs($row["Score"]) < 0.1 )
            $image = 'dash.gif';

      echo"<td align=center><img src=\"images/$image\"></td>\n";
      }
      echo "</tr>\n";
   }

   echo "</table>\n";



   echo "
    <p>
    <table width=\"100%\" border=0 cellspacing=0 cellpadding=4>
      <tr align=\"center\">
        <td><B><A href=\"userinfo.php?uid=$uid\">User info</A></B></td>\n";
   if( $uid != $player_row["ID"] ) 
      echo "        <td><B><A href=\"invite.php?uid=$uid\">Invite this user</A></B></td>\n";

   if( $finished )
      echo "        <td><B><A href=\"show_games.php?uid=$uid\">Show running games</A></B></td>";
   else
      echo "        <td><B><A href=\"show_games.php?uid=$uid&finished=1\">Show finished games</A></B></td>";

   echo "
      </tr>
    </table>
";

   end_page(false);
}
?>
