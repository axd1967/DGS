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

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );

{
  connect2mysql();


  if( @$_GET['logout'] )
    {
      set_cookies("","", true);
      jump_to("index.php");
    }

  $logged_in = who_is_logged( $player_row);

  start_page(T_("Home"), true, $logged_in, $player_row );


  echo "<center>\n";
  echo '<IMG  width=666 height=172  border=0 alt="Dragon Go Server" SRC="images/dragon_logo.jpg">';
  echo "\n<BR>&nbsp;";


if( $HOSTNAME == "dragongoserver.sourceforge.net" ) { //for devel server
  echo "<p><font color=green>\n" .
     T_("Welcome to the development version of the dragon go server!") . 
     '<br>&nbsp;<br>' . T_("If you want to play on the real server, please visits <a href=\"http://www.dragongoserver.net\">http://www.dragongoserver.net</a> instead.") . 
     '<br>&nbsp;<br><b>' . T_("Note: Since this server is running on the CVS code, bugs and even data losses could happen at any time, so don't feel too attached to your games ;-)") . '</b>' .
     '<br>&nbsp;<br>' . T_("Have a look to the FAQ for more infos.") . 
     "</font><HR>\n";
}else{ //for devel server
  echo "<p><font color=green>\n" .
     T_("Welcome to the dragon go server!") .
     '<br>&nbsp;<br>' . T_("Please, feel free to register and play some games.") .
     "</font><HR>\n";
} //for devel server


   sysmsg(get_request_arg('msg'));


  echo '<p>' . T_('Please login.') . '<font color="red"> ' .
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
                               //'HIDDEN', 'url', 'status.php',
                               ) );

  $login_form->echo_string(1);
  echo "</center>\n";

  $menu_array = array( T_("Register new account") => 'register.php' );

  end_page(@$menu_array);
}
?>
