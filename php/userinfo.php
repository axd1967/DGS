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

require( "include/std_functions.php" );
require( "include/rating.php" );

if( !$uid )
{
    header("Location: error.php?err=no_uid");
    exit;
}


connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

if( !$logged_in )
{
    header("Location: error.php?err=not_logged_in");
    exit;
}



$my_info = ( $player_row["ID"] == $uid );

$result = mysql_query("SELECT * FROM Players WHERE ID=$uid");

if( mysql_affected_rows() != 1 )
{
    header("Location: error.php?err=unknown_user");
    exit;
}

$row = mysql_fetch_array( $result );

start_page("User Info", true, $logged_in, $player_row );




echo "<center>
    <table border=3>
       <tr><td>Name:</td> <td>" . $row["Name"] . "</td></tr>
       <tr><td>Userid:</td> <td>" . $row["Handle"] . "</td></tr>
       <tr><td>Open for matches:</td> <td>" . $row["Open"] . "</td></tr>";

if( $row["RatingStatus"] ) echo "
       <tr><td>Rating:</td> <td>";  echo_rating($row["Rating"]); echo "</td></tr>";

    echo "
       <tr><td>Rank info:</td> <td>" . $row["Rank"] . "</td></tr>
    </table>
";

$result = mysql_query("SELECT * FROM Bio where uid=$uid");

if( mysql_num_rows($result) > 0 )
{
  echo "    <p>
    <H4>Biographical info</H4>
    <table border=3>
";
}

while( $row = mysql_fetch_array( $result ) )
{
  echo '     <tr><td>' . $row["Category"] . '</td><td>' . $row["Text"] . "</td></tr>\n";
}

if(  mysql_num_rows($result) > 0 )
   echo "    </table>\n";

echo "    <p>
    <table width=\"100%\" border=0 cellspacing=0 cellpadding=4>
      <tr align=\"center\">
        <td><B><A href=\"show_games.php?uid=$uid\">Show running games</A></B></td>
        <td><B><A href=\"invite.php?uid=$uid\">Invite this user</A></B></td>
        <td><B><A href=\"new_message.php?uid=$uid\">Send message to user</A></B></td>
        <td><B><A href=\"show_games.php?uid=$uid&finished=1\">Show finished games</A></B></td>
      </tr>
    </table>
</center>
";


end_page(false);

?>
