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

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

if( !$logged_in )
{
    header("Location: error.php?err=not_logged_in");
    exit;
}

$my_id = $player_row["ID"];


$result = mysql_query("SELECT Messages$my_id.*, " .
                      "UNIX_TIMESTAMP(Messages$my_id.Time) AS date, " .
                      "Players.Name AS sender, " .
                      "Players.Handle, Players.ID AS pid, " .
                      "Messages$my_id.Info " .
                      "FROM Messages$my_id,Players " .
                      "WHERE Messages$my_id.ID=$mid" . 
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

    mysql_query( "UPDATE Messages$my_id SET Info='$new_info' WHERE ID=$mid" );

    if( mysql_affected_rows() != 1)
        {
            header("Location: error.php?err=mysql_message_info");
            exit;
        }

}

start_page("Show Message", true, $logged_in, $player_row );

echo "<center>
    <table>
        <tr><td>Date:</td><td>" . date($date_fmt, $row["date"]) . "</td></tr>
        <tr><td>From:</td><td><A href=\"userinfo.php?uid=" . $row["pid"] ."\">" . 
                              $row["sender"] . "</A></td></tr>\n";

switch( $type )
{
 case 'NORMAL':
     $Subject = $row["Subject"];
     break;
     
 case 'INVITATION':
     $Subject="Game invitation";
     break;
     
 case 'ACCEPT':
     $Subject="Invitation declined";
     break;

 case 'DECLINE':
     $Subject="Invitation accepted";
     break;
}

echo "<tr><td>Subject:</td><td>$Subject</td></tr>\n" .
     "<tr><td valign=\"top\">Message:</td><td align=\"center\">\n" . 
     "<table border=2 align=center><tr>" . 
     "<td width=\"" . ($player_row["Stonesize"]*19) . "\" align=left>" .$row["Text"] .
     "</td></tr></table><BR>\n";


echo "</td></tr>\n</table></center>\n";

if( strcasecmp(substr($Subject,0,3), "re:") != 0 )
     $Subject = "RE: " . $Subject;

if( $type == 'INVITATION' and $info != 'REPLIED' )
{
?>
    <table align=center border=2 cellpadding=3 cellspacing=3>
      <tr><td>Size: </td><td><?php echo( $game_row["Size"] );?></td></tr>
      <tr><td>Color: </td><td><?php echo( $col );?></td></tr>
      <tr><td>Komi: </td><td><?php echo( $game_row["Komi"] );?></td></tr>
      <tr><td>Handicap: </td><td><?php echo( $game_row["Handicap"] );?></td></tr>
      <tr><td>Main time: </td><td><?php echo( $game_row["Maintime"] . " hours" );?></td></tr>
<?php 
                                                                              
if( $game_row["Byotype"] == 'JAP' )
    {
        echo '        <tr><td>Byo-yomi: </td><td> Japanese: ' . $game_row["Byotime"]  .
            ' hours per move and ' .$game_row["Byoperiods"] . ' extra periods </td></tr>' . "\n";
    }
 else
     {
        echo '        <tr><td>Byo-yomi: </td><td> Canadian: ' . $game_row["Byotime"]  .
            ' hours per ' .$game_row["Byoperiods"] . ' stones </td></tr>' . "\n";

     }

    echo '<tr><td>Rated: </td><td>' . ( $game_row["Rated"] == 'Y' ? 'Yes' : 'No' );

 echo "    </table>\n";
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
        <TD align=left> <input type="text" name="subject" size="50" maxlength="80" value="<?php echo $Subject ?>"></TD>
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