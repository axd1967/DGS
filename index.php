<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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

$TranslateGroups[] = "Start";

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

  start_page(T_("Home"), true, $logged_in, $player_row );


  echo "<center>\n";
  echo '<IMG  width=666 height=172  border=0 alt="Dragon Go Server" SRC="images/dragon_logo.jpg">';
  echo "\n<BR>\n<BR>\n";
  echo "<font color=green>\n" .
     T_("Welcome to the dragon go server!") . '<p>' . T_("Please, feel free to register and play some games.") .
     "</font><HR>\n";

  echo '<B><font size="+0">' . T_('Please login.') .
    '</font></B><font color="red"> ' .
    sprintf( T_("To look around, use %s."), "'guest' / 'guest'" ) . " </font>\n";

  $login_form = new Form( 'loginform', 'login.php', FORM_POST );

  $login_form->add_row( array( 'DESCRIPTION', T_('Userid'),
                               'TEXTINPUT', 'userid',16,16,'' ) );
  $login_form->add_row( array( 'DESCRIPTION', T_('Password'),
                               'PASSWORD', 'passwd',16,16,
                               'TD',
                               'SUBMITBUTTON', 'login', T_('Log in'),
                               'TEXT',
                               '<A href="forgot.php"><font size="-2">' .
                               T_('Forgot password?') . '</font></A>',
                               'HIDDEN', 'url', 'status.php' ) );

  $login_form->echo_string();
  echo "</center>\n";

  $menu_array = array( T_("Register new account") => 'register.php' );

  end_page($menu_array);
}
?>
