<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/form_functions.php';
require_once 'include/register_functions.php';


{
   connect2mysql();
   $player_row = array( 'ID' => 0 );

   if ( !is_blocked_ip() )
      error('not_logged_in', 'do_registration_blocked'); // block spammer, call only if IP-blocked

   $errorlog_id = (int)get_request_arg('errlog_id');

   $reg = new UserRegistration( /*die_on_error*/false );
   $errors = 0;
   if ( @$_REQUEST['register'] ) // register blocked user
   {
      $errors = $reg->check_registration_blocked();
      if ( !$errors )
      {
         $reg->register_blocked_user( FORUM_ID_SUPPORT );
         jump_to('index.php?sysmsg=' .
            T_('Request to register new account has been sent to an admin. '
               . 'This can take some time. If everything is OK, '
               . 'the account-details with password to login are sent to your email.') );
      }
   }


   $title = T_('Register new account for IP blocked users');
   start_page( $title, true, false, $player_row );
   echo "<h3 class=\"Header\">$title</h3>\n";

   ErrorCode::echo_error_text('ip_blocked_register', $errorlog_id);
   echo "<br><br>\n",
         T_('To register a new account despite the IP-block, a user account can be created by an admin.'),
         "<br>\n",
         T_('Please enter a user-id for the DragonGoServer and your email to send you the login details.'),
         "<br>\n",
         T_('An IP-block is only used to keep misbehaving users away.'),
         "<br>\n",
         T_('So please add why you want to bypass the IP-block in the Comments-field.'),
         "<br>\n",
         T_('There you can also add questions you might have.'),
         "<br>\n",
         T_('All fields must be provided in order to fulfill the request.'),
         "<br>\n";


   $reg_form = new Form( 'loginform', 'do_registration_blocked.php', FORM_POST );
   $reg_form->set_layout( FLAYOUT_GLOBAL, '1,2,3' );

   $reg_form->set_area(1);
   $reg_form->add_row( array( 'HEADER', T_('Please enter data') ) );

   if ( is_array($errors) )
   {
      $error_str = format_array( $errors, "\n<li>%s</li>" );

      $reg_form->set_area(2);
      $reg_form->add_row( array( 'TAB', 'TEXT', span('ErrorMsg', T_('The following errors have been detected'), '%s:') ));
      $reg_form->add_row( array( 'TAB', 'TEXT', "<ul>$error_str</ul>" ));
   }

   $reg_form->set_area(3);
   $reg_form->add_row( array( 'DESCRIPTION', T_('Userid'),
                              'TEXTINPUT', $reg->build_key('userid'), 16, 16, $reg->uhandle ) );
   $reg_form->add_row( array( 'DESCRIPTION', T_('Email'),
                              'TEXTINPUT', $reg->build_key('email'), 50, 80, $reg->email ) );

   $reg_form->add_row( array(
      'TAB',
      'CHECKBOX', $reg->build_key('policy'), '1', '', $reg->policy,
      'TEXT', sprintf( T_('I have read and accepted the DGS <a href="%s" target="dgsTOS">Rules of Conduct</a>.'),
                       HOSTBASE."policy.php" ) ));

   $reg_form->add_empty_row();
   $reg_form->add_row( array( 'DESCRIPTION', T_('Comment'),
                              'TEXTAREA', $reg->build_key('comment'), 60, 10, $reg->comment ));

   $reg_form->add_row( array( 'SUBMITBUTTON', 'register', T_('Send registration request') ) );
   $reg_form->add_row( array( 'HIDDEN', 'errlog_id', $errorlog_id ));
   $reg_form->echo_string(1);

   echo "<br>\n",
      T_("Note for beginners: read the FAQ especially for your initial rank setting in your profile page.");

   end_page();
}
?>
