<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once( "include/register_functions.php" );

{
   connect2mysql();

   error_on_blocked_ip( 'ip_blocked_register' );

   $logged_in = who_is_logged( $player_row);

   $reg = new UserRegistration( /*die_on_error*/false );
   $errors = 0;
   if( @$_REQUEST['register'] ) // register user
   {
      $errors = $reg->check_registration_normal();
      if( !$errors )
      {
         $reg->register_user();
         jump_to("status.php");
      }
   }


   start_page(T_("Register"), true, $logged_in, $player_row );

   $reg_form = new Form( 'loginform', 'register.php', FORM_POST );
   $reg_form->set_layout( FLAYOUT_GLOBAL, '1,2,3' );

   $reg_form->set_area(1);
   $reg_form->add_row( array( 'HEADER', T_('Please enter data') ) );

   if( is_array($errors) )
   {
      $error_str = format_array( $errors, "\n<li>%s</li>" );

      $reg_form->set_area(2);
      $reg_form->add_row( array( 'TAB', 'TEXT',
         sprintf( '<span class="ErrorMsg"><b>%s:</b></span>',
                  T_('The following errors have been detected') )));
      $reg_form->add_row( array( 'TAB', 'TEXT', "<ul>$error_str</ul>" ));
   }

   $reg_form->set_area(3);
   $reg_form->add_row( array( 'DESCRIPTION', T_('Userid'),
                              'TEXTINPUT', 'userid', 16, 16, $reg->uhandle ) );
   $reg_form->add_row( array( 'DESCRIPTION', T_('Full name'),
                              'TEXTINPUT', 'name', 16, 80, $reg->name ) );
   $reg_form->add_row( array( 'DESCRIPTION', T_('Password'),
                              'PASSWORD', 'passwd', 16, 16, $reg->password ) );
   $reg_form->add_row( array( 'DESCRIPTION', T_('Confirm password'),
                              'PASSWORD', 'passwd2', 16, 16, $reg->password2 ) );

   $reg_form->add_row( array( 'TAB',
                              'CHECKBOX', 'policy', '1', '', $reg->policy,
                              'TEXT', sprintf( T_('I have read and accepted the DGS <a href="%s" target="dgsTOS">Rules of Conduct</a>.'),
                                               HOSTBASE."policy.php" ) ) );

   $reg_form->add_row( array( 'SUBMITBUTTON', 'register', T_('Register') ) );
   $reg_form->echo_string(1);

   echo "<br>\n",
      T_("Note for beginners: read the FAQ especially for your initial rank setting in your profile page.");

   end_page();
}
?>
