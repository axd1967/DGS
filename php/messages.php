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

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

if( !$logged_in )
{
    header("Location: error.php?err=not_logged_in");
    exit;
}

$my_id = $player_row["ID"];

$result = mysql_query("SELECT DATE_FORMAT(Messages$my_id.Time, \"%H:%i  %Y-%m-%d\") AS date, " .
                      "Messages$my_id.ID as mid, Messages$my_id.Subject, Messages$my_id.Info, " . 
                      "Players.Name AS sender " .
                      "FROM Messages$my_id,Players " .
                      "WHERE From_ID=Players.ID");


start_page("Messages", true, $logged_in, $player_row );


echo "<table border=3>\n";
echo "<tr><th></th><th>From</th><th>Subject</th><th>Date</th></tr>\n";



while( $row = mysql_fetch_array( $result ) )
{
    echo "<tr>";

    switch( $row["Info"] )
        {
        case 'NONE':
            echo "<td></td>\n";
            break;
        case 'NEW':
            echo "<td bgcolor=\"00F464\">New</td>\n";
            break;
        case 'REPLIED':
            echo "<td bgcolor=\"FFEE00\">Replied</td>\n";
            break;
        case 'REPLY REQUIRED':
            echo "<td bgcolor=\"FFA27A\">Reply!</td>\n";
            break;
        }

    echo "<td><A href=\"show_message.php?mid=" . $row["mid"] . "\">" .
        $row["sender"] . "</A></td>\n" . 
        "<td>" . $row["Subject"] . "</td>\n" .
        "<td>" . $row["date"] . "</td></tr>\n";
}

echo "</table>
    <p>
    <table width=\"100%\" border=0 cellspacing=0 cellpadding=4>
      <tr align=\"center\">
        <td><B><A href=\"new_message.php\">Send message</A></B></td>
        <td><B><A href=\"delete_messages.php\"> Delete all messages</A></B></td>
      </tr>
    </table>
";

end_page(false);

?>
