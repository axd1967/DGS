<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once( "include/error_codes.php" );

$GLOBALS['ThePage'] = new Page('Start', ROBOTS_NO_FOLLOW, DGS_DESCRIPTION,
   'forums, discussions, multi-player-go, zen go, rengo, team-go, pair-go, shape-go' );

{
   connect2mysql();

   if( @$_GET['logout'] )
      set_login_cookie("","", true);

   $logged_in = who_is_logged( $player_row);

   $sysmsg = get_request_arg('sysmsg');
   unset($_REQUEST['sysmsg']);

   $error_code = @$_REQUEST['err'];
   $errorlog_id = (int)@$_REQUEST['eid'];
   $page = @$_REQUEST['page']; // for redirect after login

   // check for maintenance-user to allow form-login, use URL:
   // index.php?userid=... without exposing password in URL
   if( $logged_in && isset($player_row['Handle']) )
      $user_handle = $player_row['Handle'];
   else
      $user_handle = @$_REQUEST['userid'];
   if( check_maintenance( $user_handle ) )
   {
      if( !is_array($player_row) )
         $player_row = array();
      $player_row['Handle'] = $user_handle;
   }

   T_('Home'); // keep translations
   start_page(T_('Dragon Go Server'), true, $logged_in, $player_row );


   echo '<IMG  width=666 height=172  border=0 alt="'.FRIENDLY_LONG_NAME.'" SRC="images/dragon_logo.jpg">';
   echo "\n<BR>&nbsp;";


   if( $error_code )
   {
      echo "<p></p>", ErrorCode::echo_error_text( $error_code, $errorlog_id ), "\n<hr>\n";
   }
   else
   {
      if( HOSTNAME == "dragongoserver.sourceforge.net" )
      {
         // for devel server
         echo "<p></p><font color=green>\n" .
            T_("Welcome to the development version of the Dragon Go Server!") .
            '<br>&nbsp;<br>' . T_("If you want to play on the real server, please visits <a href=\"http://www.dragongoserver.net\">http://www.dragongoserver.net</a> instead.") .
            '<br>&nbsp;<br><b>' . T_("Note: Since this server is running on the CVS code, bugs and even data losses could happen at any time, so don't feel too attached to your games ;-)") . '</b>' .
            '<br>&nbsp;<br>' . T_("Have a look to the FAQ for more infos.") .
            "</font><HR>\n";
      }
      else
      {
         // for live server
         echo "<p></p><font color=green>\n" .
            sprintf( T_('Welcome to the %s!'), FRIENDLY_LONG_NAME) .
            '<br>&nbsp;<br>' . T_("Please, feel free to register and play some games.") .
            "</font><HR>\n";
      }
   }

   sysmsg($sysmsg);


   echo '<p></p>' . T_('Please login.') . '<font color="red"> ' .
      sprintf( T_("To look around, use %s."), "'guest' / '$GUESTPASS'" ) . " </font>\n";

   $login_form = new Form( 'loginform', 'login.php', FORM_POST );
   $login_form->add_hidden('page', $page);

   $login_form->add_row( array(
         'DESCRIPTION', T_('Userid'),
         'TEXTINPUT', 'userid',16,16,'',
      ));
   $login_form->add_row( array(
         'DESCRIPTION', T_('Password'),
         'PASSWORD', 'passwd',16,16, '',
         'TEXT', MED_SPACING,
         'SUBMITBUTTON', 'login', T_('Log in'),
      ));

  $login_form->echo_string(1);

  $menu_array[T_("Register new account")] = 'register.php';
  $menu_array[T_("Forgot password?")] = 'forgot.php';

  end_page(@$menu_array);
}
?>
