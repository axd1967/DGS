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


start_page("Send Message", true, $logged_in, $player_row );

?>

<FORM name="loginform" action="send_message.php" method="POST">
      
      
      <center><B><font size=+1>New Message:</font></B></center>
      <HR>
      <TABLE align="center">
        
          <TR>
            <TD align=right>To (userid):</TD>
            <TD align=left> <input type="text" name="to" size="50" maxlength="80"></TD>
          </TR>


          <TR>
            <TD align=right>Subject:</TD>
            <TD align=left> <input type="text" name="subject" size="50" maxlength="80"></TD>
          </TR>


          <TR>
            <TD align=right>Message:</TD>
            <TD align=left>  
              <textarea name="message" cols="50" rows="8" wrap="virtual"></textarea></TD>
          </TR>

          <TR>
            <TD></TD>
            <TD><input type=submit name="send" value="Send message"></TD>
          </TR>

      </TABLE>
</FORM>

<?php
end_page();
?>