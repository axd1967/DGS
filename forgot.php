<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

start_page(T_('Forgot password?'), true, $logged_in, $player_row );

echo '
<p>

<CENTER>
<TABLE cellpadding=10 width=80% >
<TR><TD align="left">
<p>
' . T_('If you have forgot your password we can email a new one. The new password will be randomly generated, but you can of course change it later from the edit profile page.') . '
</TD></TR>
<FORM name="forgot" action="send_new_password.php" method="POST">

<TR><TD align=center>' . T_('Userid') .
  ': <input type="text" name="userid" size="16" maxlength="16">
<input type=submit name="action" value="Send password"></TR>
<TR><TD align="right"><input type=submit name="action" value="Go back"></TD></TR>
</FORM>
</TABLE>
</CENTER>
';

end_page();
?>
