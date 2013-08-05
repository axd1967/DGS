<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/classlib_user.php';
require_once 'include/register_functions.php';

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row, LOGIN_SKIP_UPDATE|LOGIN_SKIP_VFY_CHK ); // skip-upd to avoid db-activity on brute-force-attack
   if ( !$logged_in )
      error('login_if_not_logged_in', 'verify_email');

   // execute verification
   $user = load_user_info();
   if ( !is_null($user) )
   {
      $vid = (int)get_request_arg('vid');
      $code = get_request_arg('code');

      $vfy_result = UserRegistration::process_verification( $user, $vid, $code );
      if ( is_numeric($vfy_result) && $vfy_result > 0 )
      {
         $infos = array();
         $load_intro = false;
         if ( $vfy_result & USERFLAG_ACTIVATE_REGISTRATION )
         {
            $infos[] = T_('Account activated!#reg');
            $load_intro = true;
         }
         if ( $vfy_result & USERFLAG_VERIFY_EMAIL )
            $infos[] = T_('Email verified!#reg');

         $msg = urlencode( implode(' + ', $infos) );
         if ( $load_intro )
            jump_to("introduction.php?sysmsg=$msg"); // after activation
         else
            jump_to("edit_email.php?sysmsg=$msg"); // after simple mail-verification
      }
   }
   else
      $vfy_result = T_('Missing user-id to identify user to process verification.');


   $title = sprintf( T_('Verify email for user [%s]'), $player_row['Handle']);
   start_page(T_('Verify email'), true, $logged_in, $player_row );
   echo '<h3 class="Header">', $title, "</h3>\n";

   if ( !is_numeric($vfy_result) )
      echo str_repeat("<br>\n", 2), span('ErrMsgCode larger', $vfy_result);

   list( $tmp1, $tmp2, $subnotes_problems_mail_change ) = UserRegistration::build_common_verify_texts();
   $notes = array();
   $notes[] = sprintf( T_("A message with a validation-code has been sent to the email-address you provided.\n"
            . "By opening the link from that message in the browser, your email-address is verified as valid\n"
            . "and the respective action (%s) will be executed."),
         implode(', ', array( T_('Account activation#reg'), T_('Email change#reg') )) );
   $notes[] = null;
   $notes[] = array( T_('In case of problems with your account activation:'),
      make_html_safe( sprintf( T_('Please <home %s>login as guest-user</home> and ask for help in the <home %s>support-forum</home>.'),
         'index.php?logout=t', 'forum/read.php?forum='.FORUM_ID_SUPPORT ), 'line'),
      T_('Provide your user-id and describe the problem with the registration process.') );
   $notes[] = $subnotes_problems_mail_change;
   echo str_repeat("<br>\n", 3);
   echo_notes( 'verify_email', T_('Troubleshooting'), $notes );

   end_page();
}//main


function load_user_info()
{
   $arg_uid = (int)get_request_arg('uid');
   $user = null;
   if ( $arg_uid > GUESTS_ID_MAX )
      $user = User::load_user( $arg_uid );
   else
   {
      $arg_user = trim( get_request_arg('user') );
      if ( (string)$arg_user != '' )
         $user = User::load_user_by_handle( $arg_user );
   }
   return $user;
}//load_user_info

?>
