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

start_page("Status", true, $logged_in, $player_row );

    echo "
    <table border=3>
       <tr><td>Name:</td> <td>" . $player_row["Name"] . "</td></tr>
       <tr><td>Userid:</td> <td>" . $player_row["Handle"] . "</td></tr>
       <tr><td>Rank:</td> <td>" . $player_row["Rank"] . "</td></tr>
    </table>
    <p>";


$result = mysql_query("SELECT DATE_FORMAT(Messages$my_id.Time, \"%H:%i  %Y-%m-%d\") AS date, " . 
                      "Messages$my_id.*, Players.Name AS sender " . 
                      "FROM Messages$my_id, Players " .
                      "WHERE (Info='NEW' OR Info='REPLY REQUIRED') AND From_ID=Players.ID");


if( mysql_num_rows($result) > 0 )
{
    echo "<HR><B>New messages:</B><p>\n";
    echo "<table border=3>\n";
    echo "<tr><th></th><th>From</th><th>Subject</th><th>Date</th></tr>\n";



    while( $row = mysql_fetch_array( $result ) )
        {
            echo "<tr>";
            
            switch( $row["Info"] )
                {
                case 'NEW':
                    echo "<td bgcolor=\"00F464\">New</td>\n";
                    break;
                case 'REPLY REQUIRED':
                    echo "<td bgcolor=\"FFA27A\">Reply!</td>\n";
                    break;
                default:
                    echo "<td bgcolor=\"FF0000\">Error?</td>\n";
                    break;                    
                }
            
            echo "<td><A href=\"show_message.php?mid=" . $row["ID"] . "\">" .
                $row["sender"] . "</A></td>\n" . 
                "<td>" . $row["Subject"] . "</td>\n" .
                "<td>" . $row["date"] . "</td></tr>\n";
        }

    echo "</table><p>\n";
}



// show games;

$uid = $player_row["ID"];

$result = mysql_query("SELECT Games.*, Players.Name FROM Games,Players " .
                      "WHERE ToMove_ID=$uid AND Status!='INVITED' AND Status!='FINISHED' " .
                      "AND Players.ID!=$uid " .
                      "AND (Black_ID=Players.ID OR White_ID=Players.ID)" );

echo "<HR><B>Your turn to move in the following games:</B><p>\n";
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
        <td><B><A href=\"edit_profile.php\">Edit profile</A></B></td>
        <td><B><A href=\"userinfo.php?uid=$uid\">Show my userinfo</A></B></td>
        <td><B><A href=\"show_games.php?uid=$uid\">Show running games</A></B></td>
        <td><B><A href=\"show_games.php?uid=$uid&finished=1\">Show finished games</A></B></td>
      </tr>
    </table>
";


end_page(false);

?>
