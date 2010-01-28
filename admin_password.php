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

// translations removed for this page: $TranslateGroups[] = "Admin";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_PASSWORD) )
      error('adminlevel_too_low');

   $user = get_request_arg('pswduser');
   $user_email = null;
   if( @$_REQUEST['show_email'] && (string)$user != '' )
   {
      $query = "SELECT Email FROM Players WHERE Handle='".mysql_addslashes($user)."' LIMIT 1";
      $row = mysql_single_fetch( "admin_password.find_user($user)", $query );
      $row_email = ( $row ) ? trim($row['Email']) : '';
      if( (string)$row_email != '' )
         $user_email = $row_email;
   }


   start_page(/*T_*/('Admin').' - './*T_*/('Send password'), true, $logged_in, $player_row );

   $passwd_form = new Form( 'adminnewpasswdform', "send_new_password.php", FORM_POST );

   $passwd_form->add_row( array( 'HEADER', /*T_*/('New password') ) );

   $passwd_form->add_row( array( 'DESCRIPTION', /*T_*/('Userid'),
                                 'TEXTINPUT', 'pswduser', 16, 16, $user, ));
   $passwd_form->add_row( array( 'DESCRIPTION', /*T_*/('Email'),
                                 'TEXTINPUT', 'email', 32, 80, '',
                                 'TEXT', /*T_*/('to replace the user\'s one'), ));
   $passwd_form->add_row( array( 'TAB', 'CELL', 1, 'align=left',
                                 'CHECKBOX', 'overnew', 1,
                                     /*T_*/('overwrite the current new password process'), 0, ));
   $passwd_form->add_row( array( 'TAB', 'CELL', 1, 'align=left',
                                 'SUBMITBUTTON', 'action', /*T_*/('Send password'),
                               ) );
   $passwd_form->echo_string(1);

   echo "<p></p>\n";


   $email_form = new Form( 'adminemailform', "admin_password.php", FORM_GET );

   $email_form->add_row( array( 'HEADER', /*T_*/('User information') ) );

   $email_form->add_row( array(
         'CELL', 2, '',
         'TEXT', /*T_*/('Please keep in mind, that the email is protected by DGS\' Privacy Policy!'), ));
   $email_form->add_row( array(
         'CELL', 2, '',
         'TEXT', /*T_*/('See also') . ':' . MED_SPACING
               . anchor( $base_path."policy.php", /*T_*/('DGS Privacy Policy')), ));
   $email_form->add_empty_row();

   $email_form->add_row( array(
         'DESCRIPTION', /*T_*/('Userid'),
         'TEXTINPUT', 'pswduser', 16, 16, $user,
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'show_email', /*T_*/('Show email'), ));
   if( @$_REQUEST['show_email'] || !is_null($user_email) )
   {
      $email_form->add_row( array(
            'DESCRIPTION', /*T_*/('Email'),
            'TEXT', ( is_null($user_email) ? NO_VALUE : $user_email ), ));
   }
   $email_form->echo_string();


   $menu_array = array();
   $menu_array[T_('New password')] = "admin_password.php";

   end_page(@$menu_array);
}
?>
