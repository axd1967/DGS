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

start_page("Register", true, $logged_in, $player_row );

?>

<CENTER>
  <FORM name="loginform" action="do_registration.php" method="POST">

    <B><font size=+1>Please enter data:</font></B>
    
    <TABLE>

      
      <TR>
        <TD align=right>Userid:</TD>
        <TD align=left> <input type="text" name="userid" size="16" maxlength="16"></TD>
      </TR>

      <TR>
        <TD align=right>Full name:</TD>
        <TD align=left> <input type="text" name="name" size="16" maxlength="80"></TD>
      </TR>
      
      <TR>
        <TD align=right>Password:</TD>
        <TD align=left><input type="password" name="passwd" size="16" maxlength="16"></TD>
      </TR>
      
      <TR>
        <TD align=right>Confirm Password:</TD>
        <TD align=left><input type="password" name="passwd2" size="16" maxlength="16"></TD>
        <TD><input type=submit name="register" value="Register"></TD>
      </TR>
      
    </TABLE>
  </FORM>
</CENTER>  

<?php
end_page();
?>
