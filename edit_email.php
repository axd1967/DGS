<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/register_functions.php';
require_once 'include/table_columns.php';


{
   disable_cache();
   connect2mysql();

   $logged_in = who_is_logged($player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'edit_email');
   $my_id = $player_row['ID'];

/* Actual REQUEST calls used:
     save                  : save profile
     vfy_delete&vid=       : remove verification
     vfy_resend&vid=       : re-send verification
*/


   // ----- actions (check & save) -----------------------

   // read defaults for vars, and parse & check posted values
   list( $vars, $errors ) = parse_edit_form( $cfg_board );

   $vid = (int)@$_REQUEST['vid'];
   if ( @$_REQUEST['vfy_delete'] && $vid > 0 )
      handle_delete_verification( $vid, $errors );
   elseif ( @$_REQUEST['vfy_resend'] && $vid > 0 )
      handle_resend_mail( $vid, $errors );
   elseif ( @$_REQUEST['save'] && count($errors) == 0 )
      handle_save_mail( $vars );


   // ----- init form-data -------------------------------

   $notify_msg = array(
         0 => T_('Off'),
         1 => T_('Notify only'),
         2 => T_('Moves and messages'),
         3 => T_('Full board and messages'),
      );


   // ----- Email Edit Form ------------------------------

   $page = 'edit_email.php';
   $form = new Form( 'emailform', $page, FORM_GET );

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
   if ( $vars['email'] )
   {
      if ( verify_invalid_email( "edit_email.check.email", $vars['email'], /*err-die*/false ) )
         $text = span('ErrMsgCode', T_('Email is invalid!'));
      elseif ( $player_row['UserFlags'] & USERFLAG_EMAIL_VERIFIED )
         $text = ( count($errors) ) ? '' : span('WarnMsg', T_('Email is verified!'));
      else
         $text = span('ErrMsgCode', T_('Email is unverified!'));
   }
   else
      $text = span('ErrMsgCode', T_('Email is missing!'));
   array_push( $row, 'TEXT', sptext($text,1) );
   $form->add_row($row);

   $form->add_empty_row();
   $form->add_row( array(
         'SUBMITBUTTONX', 'save', T_('Save settings'), array( 'accesskey' => ACCKEY_ACT_EXECUTE ), ));
   $form->add_empty_row();


   // ----- Check for open verifications -----------------

   $vtable = new Table( 'verifications', $page, null, '', TABLE_NO_SORT );
   $vtable->use_show_rows( false );

   $vtable->add_tablehead( 5, T_('Actions#header'), 'Image', TABLE_NO_HIDE, '');
   $vtable->add_tablehead( 1, T_('Type#header'), 'Enum', 0, 'VType+');
   $vtable->add_tablehead( 2, T_('Email#header'), '', 0, 'Email+');
   $vtable->add_tablehead( 3, T_('Created#header'), 'Date', 0, 'Created+');
   $vtable->add_tablehead( 4, T_('Attempts#header'), 'Number', 0, 'Counter+');
   $vtable->set_default_sort( 3 ); //on Created

   $iterator = new ListIterator( 'edit_email.verification', null, $vtable->current_order_string('Created+') );
   if ( $my_id > GUESTS_ID_MAX )
   {
      $iterator = Verification::load_verifications( $iterator, $my_id );
      $cnt_verifications = $vtable->compute_show_rows( $iterator->getResultRows() );
      $vtable->set_found_rows( $cnt_verifications );
   }
   else
      $cnt_verifications = 0;

   while ( list(,$arr_item) = $iterator->getListIterator() )
   {
      list( $vfy, $orow ) = $arr_item;

      $row_str = array();
      if ( $vtable->Is_Column_Displayed[ 1] )
         $row_str[ 1] = Verification::get_type_text( $vfy->VType );
      if ( $vtable->Is_Column_Displayed[ 2] )
         $row_str[ 2] = $vfy->Email;
      if ( $vtable->Is_Column_Displayed[ 3] )
         $row_str[ 3] = date(DATE_FMT2, $vfy->Created);
      if ( $vtable->Is_Column_Displayed[ 4] )
         $row_str[ 4] = $vfy->Counter;
      if ( $vtable->Is_Column_Displayed[ 5] )
      {
         $row_str[ 5] =
            anchor( "edit_email.php?vid={$vfy->ID}".URI_AMP."vfy_resend=1",
               image( 'images/send.gif', 'M', '', 'class="Action"' ), T_('Resend verification#email')) .
            MED_SPACING .
            anchor( "edit_email.php?vid={$vfy->ID}".URI_AMP."vfy_delete=1",
               image( 'images/trashcan.gif', 'X', '', 'class="Action"' ), T_('Remove verification#email'));
      }

      $vtable->add_row( $row_str );
   }


   // ----- Main -----------------------------------------

   $title = T_('Change email & notifications');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $form->echo_string(1);

   if ( $cnt_verifications > 0 )
   {
      section( 'Verifications', T_('Open verifications#email') );
      $vtable->echo_table();
   }


   list( $text_email_use, $text_email_priv, $subnotes_problems_mail_change ) = UserRegistration::build_common_verify_texts();
   $notes = array();
   $notes[] = $text_email_use;
   if ( !$player_row['Email'] )
      $notes[] = T_('To receive notifications or a new password (if forgotten) an email must be set.');
   $notes[] = $text_email_priv;
   $notes[] = null;
   $notes[] = array( T_('Steps to change your email:'),
      T_('Enter a new and valid email and click \'Save settings\''),
      T_('A message will be sent to your new email-address with a validation-code.'),
      make_html_safe(
         T_("Opening the link from that message in the browser will verify your new email and\n" .
            "the email in your account will be replaced with the new one."), 'line' ),
      );
   $notes[] = null;
   $notes[] = $subnotes_problems_mail_change;
   echo str_repeat("<br>\n", 3);
   echo_notes( 'edit_email', T_('Change notes'), $notes );


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
      {
         $email_error = false;
         $errors[] = T_('Email is missing!');
      }
      $vars['email'] = $email;

      // reset error-flag if email valid
      if ( !$email_error && ($player_row['UserFlags'] & USERFLAG_NFY_BUT_NO_OR_INVALID_EMAIL) )
         $vars['clear_user_flags'] |= USERFLAG_NFY_BUT_NO_OR_INVALID_EMAIL;

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
   global $player_row, $NOW;

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'edit_email.handle_save_mail');
   $my_handle = $player_row['Handle'];

   $old_email = $player_row['Email'];
   $new_email = $nval['email'];
   $set_userflags = 0;
   $clear_userflags = $nval['clear_user_flags'];
   if ( $new_email )
   {
      // added missing email or changed email, or verify unverified unchanged email
      $diff_mail = strcasecmp($old_email, $new_email);
      if ( !$old_email || $diff_mail || ( !$diff_mail && !($player_row['UserFlags'] & USERFLAG_EMAIL_VERIFIED) ) )
         $set_userflags |= USERFLAG_VERIFY_EMAIL;
   }
   $infos = array();

   $upd = new UpdateQuery('Players');
   $upd->upd_txt('SendEmail', $nval['send_email'] ); // always update even if the same
   if ( $nval['send_email'] != $player_row['SendEmail'] )
      $infos[] = T_('Notifications updated!');
   if ( $set_userflags || $clear_userflags )
      $upd->upd_raw('UserFlags', "(UserFlags | $set_userflags) & ~$clear_userflags" );

   $reload = false;
   ta_begin();
   {//HOT-section to update players profile & initiate email-verification-process
      if ( $new_email && ($set_userflags & USERFLAG_VERIFY_EMAIL) )
      {
         // send email-change-mail with verification-code
         $vfy_code = Verification::build_code( $my_id, $new_email );
         $vfy = new Verification( 0, $my_id, 0, $NOW, VFY_TYPE_EMAIL_CHANGE, $new_email, $vfy_code );
         if ( $vfy->insert() )
         {
            list( $subject, $text ) = UserRegistration::build_email_verification(
               $my_id, $my_handle, $vfy->ID, $vfy->VType, $vfy_code, $new_email );
            send_email( "edit_email.send_email_change($my_id,{$vfy->ID})",
               $new_email, EMAILFMT_SKIP_WORDWRAP, $text, $subject );
            if ( $diff_mail )
               $infos[] = T_('Verification mail for email-change sent!');
            else // mail didn't change, so just verify
               $infos[] = T_('Verification mail for existing email sent!');
            $reload = true;
         }
      }

      db_query( "edit_email.handle_save_mail($my_id)",
         "UPDATE Players SET " . $upd->get_query() . " WHERE ID=$my_id LIMIT 1" );
   }
   ta_end();

   $msg = urlencode( implode(' + ', $infos) );
   if ( $reload )
      jump_to("edit_email.php?sysmsg=$msg");
   else
      jump_to("userinfo.php?uid=$my_id".URI_AMP."sysmsg=$msg");
}//handle_save_mail

function handle_delete_verification( $vid, &$errors )
{
   global $player_row;

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', "edit_email.handle_delete_verification($vid)");

   $result = UserRegistration::remove_verification( 'edit_email', $vid );
   if ( $result == 0 )
      $errors[] = T_('Verification to remove couldn\'t be found!');
   elseif ( $result == -1 )
      $errors[] = T_('You can only remove your own verifications!');
   elseif ( $result == 1 )
      jump_to("edit_email.php?sysmsg=".urlencode(T_('Verification removed!#email')));
}//handle_delete_verification

function handle_resend_mail( $vid, &$errors )
{
   global $player_row;

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', "edit_email.handle_resend_mail($vid)");

   $vfy = Verification::load_verification( $vid );
   if ( is_null($vfy) )
      $errors[] = T_('Verification to re-send couldn\'t be found!');
   elseif ( $vfy->uid != $my_id )
      $errors[] = T_('You can only re-send your own verifications!');
   else
   {
      // send email-change-mail with verification-code
      list( $subject, $text ) = UserRegistration::build_email_verification(
         $my_id, $player_row['Handle'], $vfy->ID, $vfy->VType, $vfy->Code, $vfy->Email );
      $success = send_email( "edit_email.handle_resend_mail.resend_mail($my_id,{$vfy->ID})",
         $vfy->Email, EMAILFMT_SKIP_WORDWRAP, $text, $subject );

      if ( $success )
      {
         jump_to("edit_email.php?sysmsg=" . urlencode(
            sprintf( T_('Verification mail for email-change [%s] have been re-sent!'), $vfy->Email) ));
      }
   }
}//handle_resend_mail

?>
