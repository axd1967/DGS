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
include( "connect2mysql.php" );

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

if( !$logged_in )
{
    header("Location: error.php?err=not_logged_in");
    exit;
}


$result = mysql_query("SELECT Messages.*, " .
                      "DATE_FORMAT(Messages.Time, \"%H:%i  %Y-%m-%d\") AS date, " .
                      "Players.Name AS sender, " .
                      "Players.Handle, Players.ID AS pid, " .
                      "Messages.Info " .
                      "FROM Messages,Players " .
                      "WHERE Messages.ID=$mid AND To_ID=" . $player_row["ID"] . 
                      " AND From_ID=Players.ID");

if( mysql_num_rows($result) != 1 )
{
    header("Location: error.php?err=unknown_message");
    exit;
}

$row = mysql_fetch_array($result);

$type = $row["Type"];
$info = $row["Info"];


if( $type == 'INVITATION' and $info != 'REPLIED' )
{
    $result = mysql_query( "SELECT * FROM Games WHERE ID=" . $row["Game_ID"] . 
                           " AND Status='INVITED'" );

    if( mysql_num_rows($result) != 1 )
        {
            header("Location: error.php?err=invited_to_unknown_game");
            exit;
        } 
    
    $game_row = mysql_fetch_array($result);

    if( $game_row["Black_ID"] == $player_row["ID"] )
        $col = "Black";
    else if( $game_row["White_ID"] == $player_row["ID"] )
        $col = "White";
    else
        {
            header("Location: error.php?err=invited_to_unknown_game");
            exit;
        } 
}

if( $info == 'NEW' )
{
    if( $type == 'INVITATION' )
        $new_info = 'REPLY REQUIRED';
    else
        $new_info = 'NONE';

    mysql_query( "UPDATE Messages SET Info='$new_info' WHERE ID=$mid" );

    if( mysql_affected_rows() != 1)
        {
            header("Location: error.php?err=mysql_message_info");
            exit;
        }

}

start_page("Show Message", true, $logged_in, $player_row );

echo "
    <table>
        <tr><td>Date:</td><td>" . $row["date"] . "</td></tr>
        <tr><td>From:</td><td><A href=\"userinfo.php?uid=" . $row["pid"] ."\">" . 
                              $row["sender"] . "</A></td></tr>\n";

switch( $type )
{
case 'NORMAL':
echo "<tr><td>Subject:</td><td>" . $row["Subject"] . "</td></tr>\n";
break;

case 'INVITATION':
echo "<tr><td>Subject:</td><td>Game invitation</td></tr>\n";
break;

case 'ACCEPT':
echo "<tr><td>Subject:</td><td>Invitation declined</td></tr>\n";
break;

case 'DECLINE':
echo "<tr><td>Subject:</td><td>Invitation accepted</td></tr>\n";
break;
}

echo "<tr><td>Message:</td><td>" . $row["Text"] . "</td></tr>\n</table>\n";


if( $type == 'INVITATION' and $info != 'REPLIED' )
{
?>
    <table align=center border=2 cellpadding=3 cellspacing=3>
      <tr><td>Size: </td><td><?php echo( $game_row["Size"] );?></td></tr>
      <tr><td>Color: </td><td><?php echo( $col );?></td></tr>
      <tr><td>Komi: </td><td><?php echo( $game_row["Komi"] );?></td></tr>
      <tr><td>Handicap: </td><td><?php echo( $game_row["Handicap"] );?></td></tr>
    </table>
<?php
}

if( $type != 'INVITATION' or $info != 'REPLIED' )
{
?>


<HR>
<FORM name="loginform" action="send_message.php" method="POST">
  
  
  <center><B><font size=+1>Reply:</font></B></center>
    <TABLE align="center">
      
      <input type="hidden" name="to" value="<?php echo $row["Handle"]; ?>">
      <input type="hidden" name="reply" value="<?php echo $mid; ?>">

<?php if( $type != "INVITATION" ) { ?>
      
      <TR>
        <TD align=right>Subject:</TD>
        <TD align=left> <input type="text" name="subject" size="50" maxlength="80"></TD>
      </TR>      

<?php } ?>
      
      <TR>
        <TD align=right>Message:</TD>
        <TD align=left>  
          <textarea name="message" cols="50" rows="8" wrap="virtual"></textarea></TD>
      </TR>
           
      <TR>
        
<?php

if( $type == "INVITATION" and $info != 'REPLIED' )
{
    echo "<input type=hidden name=\"gid\" value=\"" . $row["Game_ID"] . "\">\n";
    echo "<TD></TD><TD><input type=submit name=\"type\" value=\"Accept\">\n";
    echo "<input type=submit name=\"type\" value=\"Decline\"></TD>\n";
}
else
{
    echo "<TD></TD><TD><input type=submit name=\"send\" value=\"Reply\"></TD>\n";    
}

echo "</TR></table></FORM>\n";
   
}

end_page();
?>