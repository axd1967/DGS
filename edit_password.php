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
require( "include/timezones.php" );
require( "include/rating.php" );

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

if( !$logged_in )
   error("not_logged_in");


start_page("Edit password", true, $logged_in, $player_row );

?>
<CENTER>
  <FORM name="passwordform" action="change_password.php" method="POST">
      <table>
      <TR>
        <TD align=right>New Password:</TD>
        <TD align=left><input type="password" name="passwd" size="16" maxlength="16"></TD>
      </TR>
      
      <TR>
        <TD align=right>Confirm Password:</TD>
        <TD align=left><input type="password" name="passwd2" size="16" maxlength="16"></TD>
      </TR>
    </TABLE>

<input type=submit name="action" value="Change password">

      

  </FORM>
</CENTER>  


<?php

end_page(false);

?>

