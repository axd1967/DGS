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
require( "include/form_functions.php" );

{
  connect2mysql();

  if( $logout )
    {
      set_cookies("","", true);
      jump_to("index.php");
    }

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  start_page("Home", true, $logged_in, $player_row );
}

?>
<center>
<IMG  width=666 height=172  border=0 alt='Dragon Go Server' SRC="images/dragon_logo.jpg">
<BR>
<BR>
<B><font size="+0">Please login.</font></B><font color="red"> To look around, use 'guest' / 'guest'.</font>
<?php

echo form_start( 'loginform', 'login.php', 'POST' );
echo form_insert_row( 'DESCRIPTION', 'Userid',
                      'TEXTINPUT', 'userid',16,16,'' );
echo form_insert_row( 'DESCRIPTION', 'Password',
                      'PASSWORD', 'passwd',16,16,
                      'SUBMITBUTTON', 'login', 'Log in',
                      'TEXT', '<A href="forgot.php"><font size="-2">Forgot password?</font></A>',
                      'HIDDEN', 'url', 'status.php' );
echo form_end();
?>
    <HR>
    <a href="register.php"><B>Register new account</B></a>
    <HR>
</center>

<?php
end_page();
?>
