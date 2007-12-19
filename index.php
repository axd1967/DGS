<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

$TranslateGroups[] = "Start";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );

{
   connect2mysql();


   if( @$_GET['logout'] )
   {
      set_login_cookie("","", true);
      jump_to("index.php");
   }

   $logged_in = who_is_logged( $player_row);

   $sysmsg= get_request_arg('sysmsg'); unset($_REQUEST['sysmsg']);
   start_page(T_("Home"), true, $logged_in, $player_row );


   echo '<IMG  width=666 height=172  border=0 alt="'.$FRIENDLY_LONG_NAME.'" SRC="images/dragon_logo.jpg">';
   echo "\n<BR>&nbsp;";


if( $HOSTNAME == "dragongoserver.sourceforge.net" ) { //for devel server
  echo "<p></p><font color=green>\n" .
     T_("Welcome to the development version of the Dragon Go Server!") . 
     '<br>&nbsp;<br>' . T_("If you want to play on the real server, please visits <a href=\"http://www.dragongoserver.net\">http://www.dragongoserver.net</a> instead.") . 
     '<br>&nbsp;<br><b>' . T_("Note: Since this server is running on the CVS code, bugs and even data losses could happen at any time, so don't feel too attached to your games ;-)") . '</b>' .
     '<br>&nbsp;<br>' . T_("Have a look to the FAQ for more infos.") . 
     "</font><HR>\n";
}else{ //for devel server
  echo "<p></p><font color=green>\n" .
     sprintf( T_('Welcome to the %s!'), $FRIENDLY_LONG_NAME) .
     '<br>&nbsp;<br>' . T_("Please, feel free to register and play some games.") .
     "</font><HR>\n";
} //for devel server


   sysmsg($sysmsg);


  echo '<p></p>' . T_('Please login.') . '<font color="red"> ' .
    sprintf( T_("To look around, use %s."), "'guest' / '$GUESTPASS'" ) . " </font>\n";

  $login_form = new Form( 'loginform', 'login.php', FORM_POST );

  $login_form->add_row( array( 'DESCRIPTION', T_('Userid'),
                               'TEXTINPUT', 'userid',16,16,'',
                               ) );
  $login_form->add_row( array( 'DESCRIPTION', T_('Password'),
                               'PASSWORD', 'passwd',16,16,
                               //'TD',
                               'CELL', 99, 'align="left"',
                               'SUBMITBUTTON', 'login', T_('Log in'),
                               //'HIDDEN', 'url', 'status.php',
                               ) );

  $login_form->echo_string(1);

  $menu_array[T_("Register new account")] = 'register.php';
  $menu_array[T_("Forgot password?")] = 'forgot.php';

  end_page(@$menu_array);
}
?>
