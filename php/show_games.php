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

header ("Cache-Control: no-cache, must-revalidate, max_age=0"); 

include( "std_functions.php" );

if( !$uid )
{
    header("Location: error.php?err=no_uid");
    exit;
}

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

$result = mysql_query("SELECT Games.*, Players.Name FROM Games,Players " .
                      " WHERE  Status!='INVITED' AND Status!='FINISHED' AND " . 
                      "(( Black_ID=$uid AND White_ID=Players.ID ) OR " .
                      "( White_ID=$uid AND Black_ID=Players.ID ))");



start_page("Running games", true, $logged_in, $player_row );


echo "<table border=3>\n";
echo "<tr><th>Opponent</th><th>Color</th><th>Size</th><th>Handicap</th><th>moves</th></tr>\n";


while( $row = mysql_fetch_array( $result ) )
{
    if( $uid == $row["Black_ID"] )
        $col = "Black";
    else
        $col = "White";

    echo "<tr><td><A href=\"game.php?gid=" . $row["ID"] . "\">" . $row["Name"] . "</td>\n" .
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
        <td><B><A href=\"userinfo.php?uid=$uid\">User info</A></B></td>
        <td><B><A href=\"invite.php?uid=$uid\">Invite this user</A></B></td>
        <td><B><A href=\"show_games.php?uid=$uid&finished=1\">Show finished games</A></B></td>
      </tr>
    </table>
";

end_page(false);

?>
