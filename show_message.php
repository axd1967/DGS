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


   $result = mysql_query("SELECT Messages.*, " .
                         "UNIX_TIMESTAMP(Messages.Time) AS date, " .
                         "Players.Name AS sender, " .
                         "Players.Handle, Players.ID AS pid " .
                         "FROM Messages,Players " .
                         "WHERE Messages.ID=$mid " .
                         "AND ((Messages.To_ID=$my_id AND From_ID=Players.ID)" .
                         "OR (Messages.From_ID=$my_id AND To_ID=Players.ID))");

   if( mysql_num_rows($result) != 1 )
      error("unknown_message");

   $row = mysql_fetch_array($result);

   extract($row);

   $to_me = ( $To_ID == $my_id );

   $RepliedQ = !(strpos($Flags,'REPLIED') === false);

   if( $Type == 'INVITATION' and !$RepliedQ )
   {
      $result = mysql_query( "SELECT * FROM Games WHERE ID=$Game_ID " . 
                             " AND Status='INVITED'" );

      if( mysql_num_rows($result) != 1 )
         error("invited_to_unknown_game");
    
      $game_row = mysql_fetch_array($result);

      if( $game_row["Black_ID"] == $player_row["ID"] )
         $col = "Black";
      else if( $game_row["White_ID"] == $player_row["ID"] )
         $col = "White";
      else
      {
         error("invited_to_unknown_game");
      } 
   }

   $pos = strpos($Flags,'NEW');
   if( $to_me and !($pos === false) )
   {
      $Flags = substr_replace($Flags, '', $pos, 3);

      mysql_query( "UPDATE Messages SET Flags='$Flags', Time=Time " .
                   "WHERE ID=$mid AND To_ID=$my_id" );

      if( mysql_affected_rows() != 1)
         error("mysql_message_info", true);

   }

   start_page("Show Message", true, $logged_in, $player_row );

   echo "<center>
    <table>
        <tr><td>Date:</td><td>" . date($date_fmt, $row["date"]) . "</td></tr>
        <tr><td>" . ($to_me ? "From" : "To" ) . 
      ":</td><td><A href=\"userinfo.php?uid=" . $row["pid"] ."\">" . 
      $row["sender"] . "</A></td></tr>\n";

   switch( $Type )
   {
      case 'NORMAL':
         $Subject = make_html_safe($row["Subject"]);
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
      "<td width=\"" . ($player_row["Stonesize"]*19) . "\" align=left>" . 
      make_html_safe($row["Text"],true) .
      "</td></tr></table><BR>\n";


   echo "</td></tr>\n</table></center>\n";

   if( strcasecmp(substr($Subject,0,3), "re:") != 0 )
      $Subject = "RE: " . $Subject;

   if( $Type == 'INVITATION' and !$RepliedQ )
   {
?>
    <table align=center border=2 cellpadding=3 cellspacing=3>
      <tr><td>Size: </td><td><?php echo( $game_row["Size"] );?></td></tr>
      <tr><td>Color: </td><td><?php echo( $col );?></td></tr>
      <tr><td>Komi: </td><td><?php echo( $game_row["Komi"] );?></td></tr>
      <tr><td>Handicap: </td><td><?php echo( $game_row["Handicap"] );?></td></tr>
      <tr><td>Main time: </td><td><?php echo_time( $game_row["Maintime"] );?></td></tr>
<?php 
                                                                              
       if( $game_row["Byotype"] == 'JAP' )
       {
          echo '        <tr><td>Byo-yomi: </td><td> Japanese: ';
          echo_time($game_row["Byotime"]);
          echo ' per move and ' . $game_row["Byoperiods"] . ' extra periods </td></tr>' . "\n";
       }
       else if ( $game_row["Byotype"] == 'CAN' )
       {
          echo '        <tr><td>Byo-yomi: </td><td> Canadian: '; 
          echo_time($game_row["Byotime"]);
          echo ' per ' .$game_row["Byoperiods"] . ' stones </td></tr>' . "\n";
        
       }
       else if ( $game_row["Byotype"] == 'FIS' )
       {
          echo '        <tr><td>Fischer time: </td><td> ';
          echo_time($game_row["Byotime"]);
          echo ' extra per move </td></tr>' . "\n";     
       }

    echo '<tr><td>Rated: </td><td>' . ( $game_row["Rated"] == 'Y' ? 'Yes' : 'No' ) . '</td></tr>
</table>
';
   }

   if( $to_me and ( $Type != 'INVITATION' or !$RepliedQ ))
   {
?>


<HR>
<FORM name="loginform" action="send_message.php" method="POST">
  
  
  <center><B><font size=+1>Reply:</font></B></center>
    <TABLE align="center">
      
      <input type="hidden" name="to" value="<?php echo $row["Handle"]; ?>">
      <input type="hidden" name="reply" value="<?php echo $mid; ?>">

<?php 
       if( $Type != "INVITATION" ) 
       { 
?>      
      <TR>
        <TD align=right>Subject:</TD>
        <TD align=left> <input type="text" name="subject" size="50" maxlength="80" value="<?php echo $Subject ?>"></TD>
      </TR>      

<?php 
       } 
?>      
      <TR>
        <TD align=right>Message:</TD>
        <TD align=left>  
          <textarea name="message" cols="50" rows="8" wrap="virtual"></textarea></TD>
      </TR>
           
      <TR>
        
<?php
       if( $Type == "INVITATION" and !$RepliedQ )
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
}
?>