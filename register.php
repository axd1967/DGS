<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/std_functions.php';
require_once 'include/form_functions.php';
require_once 'include/register_functions.php';

{
   connect2mysql();

   error_on_blocked_ip( 'ip_blocked_register' );

   // logout if still logged-in
   $logged_in = who_is_logged( $player_row);
   if ( $logged_in )
   {
      set_login_cookie("","", true);
      $logged_in = who_is_logged( $player_row);
   }

   $reg = new UserRegistration( /*die_on_error*/false );
   $errors = 0;
   if ( @$_REQUEST['register'] ) // register user
   {
      ta_begin();
      {//HOT-section to register user
         $errors = $reg->check_registration_normal();
         if ( !$errors )
         {
            $reg->register_user();
            if ( SEND_ACTIVATION_MAIL )
               jump_to("verify_email.php?user=".urlencode($reg->uhandle));
            else
               jump_to("introduction.php?sysmsg=".urlencode(T_('Account registered!')));
         }
      }
      ta_end();
   }


   start_page(T_("Register"), true, $logged_in, $player_row );
   echo '<h3 class="Header">', T_('Register new account'), "</h3>\n";

   $reg_form = new Form( 'loginform', 'register.php', FORM_POST );
   $reg_form->set_layout( FLAYOUT_GLOBAL, '1,2,3' );

   $reg_form->set_area(1);
   $reg_form->add_row( array( 'HEADER', T_('Please enter data') ) );

   if ( is_array($errors) )
   {
      $error_str = format_array( $errors, "\n<li><span class=\"ErrorMsg\">%s</span></li>" );

      $reg_form->set_area(2);
      $reg_form->add_row( array( 'TAB', 'TEXT', span('ErrorMsg', T_('The following errors have been detected'), '%s:') ));
      $reg_form->add_row( array( 'TAB', 'TEXT', "<ul>$error_str</ul>" ));
   }

   $reg_form->set_area(3);
   $reg_form->add_row( array( 'DESCRIPTION', T_('Userid'),
                              'TEXTINPUT', $reg->build_key('userid'), 16, 16, $reg->uhandle,
                              'TEXT', sptext(T_('Account to log in'), 1), ));
   $reg_form->add_row( array( 'DESCRIPTION', T_('Full name'),
                              'TEXTINPUT', $reg->build_key('name'), 16, 40, $reg->name ));
   $reg_form->add_row( array( 'DESCRIPTION', T_('Password'),
                              'PASSWORD', $reg->build_key('passwd'), 16, 16, $reg->password ));
   $reg_form->add_row( array( 'DESCRIPTION', T_('Confirm password'),
                              'PASSWORD', $reg->build_key('passwd2'), 16, 16, $reg->password2 ));

   list( $text_email_use, $text_email_priv ) = UserRegistration::build_common_verify_texts();
   $text_activate_mail = ( SEND_ACTIVATION_MAIL )
      ? span('RegisterMsg', T_('To activate your account and confirm the registration, a validation code will be sent to your email.')) . "<br>\n"
      : '';
   $reg_form->add_empty_row();
   $reg_form->add_row( array(
      'DESCRIPTION', T_('Email'),
      'TEXTINPUT', $reg->build_key('email'), 50, 80, $reg->email,
      'TEXT', "<br>\n"
            . $text_activate_mail
            . $text_email_use
            . "<br>\n"
            . $text_email_priv ));

   $reg_form->add_empty_row();
   $reg_form->add_row( array(
      'TAB',
      'CHECKBOX', $reg->build_key('policy'), '1', '', $reg->policy,
      'TEXT', sprintf( T_('I have read and accepted the DGS <a href="%s" target="dgsTOS">Rules of Conduct</a>.'),
                       HOSTBASE."policy.php" ) ));

   $reg_form->add_row( array( 'SUBMITBUTTON', 'register', T_('Register') ));
   $reg_form->echo_string(1);

   echo "<br>\n",
      T_("Note for beginners: read the FAQ especially for your initial rank setting in your profile page.");


   $menu_array[T_("Show login page")] = 'index.php?logout=t';

   end_page(@$menu_array);
}
?>
