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

$TranslateGroups[] = "Users";

require_once 'include/std_functions.php';
require_once 'include/error_codes.php';
require_once 'include/form_functions.php';


{
   disable_cache();
   connect2mysql();

   $logged_in = who_is_logged($player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'edit_email');
   $my_id = $player_row['ID'];

/* Actual REQUEST calls used:
     save                  : save profile
*/


   // ----- actions (check & save) -----------------------

   // read defaults for vars, and parse & check posted values
   list( $vars, $errors ) = parse_edit_form( $cfg_board );

   if ( @$_REQUEST['save'] && count($errors) == 0 )
      handle_save_mail( $vars );


   // ----- init form-data -------------------------------

   $notify_msg = array(
         0 => T_('Off'),
         1 => T_('Notify only'),
         2 => T_('Moves and messages'),
         3 => T_('Full board and messages'),
      );


   // ----- Email Edit Form ------------------------------

   $form = new Form( 'emailform', 'edit_email.php', FORM_GET );

   if ( count($errors) )
   {
      $form->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $form->add_empty_row();
   }

   $form->add_row( array(
         'DESCRIPTION', T_('Email notifications'),
         'SELECTBOX', 'emailnotify', 1, $notify_msg, (int)$vars['gui:email_notify'], false ) );
   $row = array(
         'DESCRIPTION', T_('Email'),
         'TEXTINPUT', 'email', 32, 80, $vars['email'] );
   if ( !$vars['email'] )
      array_push( $row,
            'TEXT', span('FormWarning', T_('Must be filled to receive a new password or a notification')) );
   $form->add_row($row);

   $form->add_empty_row();
   $form->add_row( array(
         'SUBMITBUTTONX', 'save', T_('Save settings'), array( 'accesskey' => ACCKEY_ACT_EXECUTE ), ));
   $form->add_empty_row();

   // ----- Main -----------------------------------------

   $title = T_('Change email & notifications');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $form->echo_string(1);


   $menu_array = array();
   $menu_array[T_('Edit profile')] = 'edit_profile.php';
   $menu_array[T_('Change email & notifications')] = 'edit_email.php';
   $menu_array[T_('Change password')] = 'edit_password.php';

   end_page(@$menu_array);
}//main



// return ( vars, error-list ) with $vars containing default or changed profile-values
function parse_edit_form( &$cfg_board )
{
   global $player_row;

   // set defaults
   $vars = array(
      'gui:email_notify'   => 0,
      'send_email'         => $player_row['SendEmail'], // db-value
      'email'              => $player_row['Email'],
      'clear_user_flags'   => 0,
   );

   // parse mail-notification for GUI
   $send_email = $player_row['SendEmail'];
   if ( strpos($send_email, 'BOARD') !== false )
      $notify_msg_idx = 3;
   elseif ( strpos($send_email, 'MOVE') !== false )
      $notify_msg_idx = 2;
   elseif ( strpos($send_email, 'ON') !== false )
      $notify_msg_idx = 1;
   else
      $notify_msg_idx = 0;
   $vars['gui:email_notify'] = $notify_msg_idx;


   // parse URL-vars from form-submit
   $errors = array();
   if ( @$_REQUEST['save'] )
   {
      $email = trim(get_request_arg('email'));
      if ( $email )
      {
         $email_error = verify_invalid_email(false, $email, /*err-die*/false );
         if ( $email_error )
            $errors[] = ErrorCode::get_error_text($email_error);
      }
      else
         $email_error = false;
      $vars['email'] = $email;

      // reset error-flag if email valid
      if ( !$email_error && ($player_row['UserFlags'] & USERFLAG_NFY_BUT_NO_OR_INVALID_EMAIL) )
         $vars['clear_user_flags'] = USERFLAG_NFY_BUT_NO_OR_INVALID_EMAIL;

      $emailnotify = (int)@$_REQUEST['emailnotify'];
      if ( $emailnotify < 0 )
         $emailnotify = 0;
      elseif ( $emailnotify > 3 )
         $emailnotify = 3;
      if ( $emailnotify >= 1 )
      {
         $sendemail = 'ON';
         if ( $emailnotify >= 2 ) // BOARD also includes moves+message
         {
            $sendemail .= ',MOVE,MESSAGE';
            if ( $emailnotify >= 3 )
               $sendemail .= ',BOARD';
         }

         if ( empty($email) )
            $errors[] = T_('Missing email-address for enabled email notifications.#profile');
      }
      else
         $sendemail = '';
      $vars['gui:email_notify'] = $emailnotify;
      $vars['send_email'] = $sendemail;
   }//is_save

   return array( $vars, $errors );
}//parse_edit_form


// save profile-data $nval into database and jump to follow-up-page
function handle_save_mail( $nval )
{
   global $player_row;

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'edit_email.handle_save_mail');

   $upd = new UpdateQuery('Players');
   $upd->upd_txt('SendEmail', $nval['send_email'] );
   $upd->upd_txt('Email', $nval['email'] );
   if ( $nval['clear_user_flags'] )
      $upd->upd_raw('UserFlags', sprintf( "UserFlags=UserFlags & ~%s", (int)$nval['clear_user_flags'] ) );

   db_query( "edit_email.handle_save_mail($my_id)",
      "UPDATE Players SET " . $upd->get_query() . " WHERE ID=$my_id LIMIT 1" );

   $msg = urlencode(T_('Email & notifications updated!'));
   jump_to("userinfo.php?uid=$my_id".URI_AMP."sysmsg=$msg");
}//handle_save_mail

?>
