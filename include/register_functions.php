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
require_once( 'include/classlib_userconfig.php' );
require_once( 'include/classlib_userquota.php' );
require_once( 'forum/forum_functions.php' );
require_once( "include/error_codes.php" );



define('USE_REGEXP_REGISTRATION', 1); // loose account name reject

 /*!
  * \class UserRegistration
  *
  * \brief Class to manage error-code and some attributes of additional logging with error-texts
  */
class UserRegistration
{
   var $die_on_error;
   var $errors;

   var $uhandle;
   var $policy;

   var $name;
   var $password;
   var $password2;
   var $email;
   var $comment;

   function UserRegistration( $die_on_error=true )
   {
      $this->die_on_error = $die_on_error;
      $this->errors = array();

      // for both normal and blocked registration
      $this->uhandle = get_request_arg('userid');
      $this->policy = get_request_arg('policy');
      $this->email = trim(get_request_arg('email'));

      // only for normal-registration
      $this->name = trim(get_request_arg('name'));
      $this->password = get_request_arg('passwd');
      $this->password2 = get_request_arg('passwd2');

      // only for blocked-registration
      $this->comment = trim(get_request_arg('comment'));
   }

   // returns 0=no-error, array with error-texts otherwise or error thrown (depends on die-mode)
   function check_registration_normal()
   {
      $this->errors = array();
      $this->check_userid();
      if( !$this->die_on_error )
         $this->check_existing_user();
      $this->check_name();
      $this->check_password();
      $this->check_policy();
      if( $this->die_on_error )
         $this->check_existing_user();
      $this->check_email();
      return (count($this->errors)) ? $this->errors : 0;
   }

   // returns 0=no-error, array with error-texts otherwise or error thrown (depends on die-mode)
   function check_registration_blocked()
   {
      $this->errors = array();
      $this->check_userid();
      if( !$this->die_on_error )
         $this->check_existing_user();
      $this->check_email();
      $this->check_policy();
      if( $this->die_on_error )
         $this->check_existing_user();
      return (count($this->errors)) ? $this->errors : 0;
   }


   function check_userid()
   {
      if( strlen( $this->uhandle ) < 3 )
         $this->_error('userid_too_short');
      if( illegal_chars( $this->uhandle ) )
         $this->_error('userid_illegal_chars');
   }

   function check_name()
   {
      if( strlen( $this->name ) < 1 )
         $this->_error('name_not_given');
   }

   function check_password()
   {
      if( strlen($this->password) < 6 )
         $this->_error('password_too_short');
      if( illegal_chars( $this->password, true ) )
         $this->_error('password_illegal_chars');

      if( $this->password != $this->password2 )
         $this->_error('password_mismatch');
   }

   function check_policy()
   {
      if( !$this->policy )
        $this->_error('registration_policy_not_checked');
   }

   // returns 0=no-error, array with error-texts otherwise or error thrown (depends on die-mode)
   function check_email()
   {
      if( (string)$this->email != '' )
      {
         $errorcode = verify_email( "Registration.check_email({$this->uhandle})",
            $this->email, $this->die_on_error );
         if( $errorcode !== true )
            $this->_error($errorcode);
      }
   }


   // internal (throw error or add to error-list)
   function _error( $error_code )
   {
      if( $this->die_on_error )
         error($error_code);
      else
         $this->errors[] = ErrorCode::get_error_text($error_code);
   }


   function check_existing_user()
   {
      if( (string)$this->uhandle == '' )
         return;

      if( !USE_REGEXP_REGISTRATION )
      {
         //if foO exist, reject foo but accept fo0 (with a zero instead of uppercase o)
         $result = db_query( "Registration.check_existing_user.find_player({$this->uhandle})",
            "SELECT Handle FROM Players WHERE Handle='".mysql_addslashes($this->uhandle)."'" );
      }
      else
      {
         //reject the O0, l1I and S5 confusing matchings (used by account usurpers)
         //for instance, with the Arial font, a I and a l can't be distinguished
         $regx = preg_quote($this->uhandle); //quotemeta()
         $regx = eregi_replace( '[0o]', '[0o]', $regx);
         $regx = eregi_replace( '[1li]', '[1li]', $regx);
         $regx = eregi_replace( '[5s]', '[5s]', $regx);
         $regx = mysql_addslashes($regx);
         $regx = '^'.$regx.'$';

         $result = db_query( "Registration.check_existing_user.find_player_regexp({$this->uhandle},[$regx]})",
            "SELECT Handle FROM Players WHERE Handle REGEXP '$regx'" );
      }

      if( @mysql_num_rows($result) > 0 )
         $this->_error('userid_in_use');
   } //check_existing_user


   // do the registration to the database
   function register_user()
   {
      global $NOW;

      $code = make_session_code();

      $result = db_query( "Registration.register_user.insert_player({$this->uhandle})",
         "INSERT INTO Players SET " .
            "Handle='".mysql_addslashes($this->uhandle)."', " .
            "Name='".mysql_addslashes($this->name)."', " .
            "Password=".PASSWORD_ENCRYPT."('".mysql_addslashes($this->password)."'), " .
            ($this->email ? "Email='".mysql_addslashes($this->email)."', " : '' ) .
            "Registerdate=FROM_UNIXTIME($NOW), " .
            "Sessioncode='$code', " .
            "Sessionexpire=FROM_UNIXTIME(".($NOW+SESSION_DURATION).")" );

      $new_id = mysql_insert_id();

      if( mysql_affected_rows() != 1 )
         error('mysql_insert_player', "Registration.register_user.insert_player2({$this->uhandle})");

      ConfigPages::insert_default( $new_id );
      ConfigBoard::insert_default( $new_id );
      UserQuota::insert_default( $new_id );

      set_login_cookie( $this->uhandle, $code );
   } //register_user


   // send request to Support-forum (moderated)
   function register_blocked_user( $forum_id )
   {
      global $NOW;

      $ip = (string)@$_SERVER['REMOTE_ADDR'];

      $Subject = "Admin-Request to register account to bypass IP-block";
      $Text = T_("This is an automated support-request to register a new account on behalf of an IP-blocked user with the following credentials:")
         . "\n"
         . "Userid: {$this->uhandle}\n"
         . "Email: {$this->email}\n"
         . "Blocked-IP: $ip\n\n"
         . "User-Comment:\n" . $this->comment;

      $query = "INSERT INTO Posts SET " .
               "Forum_ID=$forum_id, " .
               "Thread_ID=0, " .
               "Time=FROM_UNIXTIME($NOW), " .
               "LastChanged=FROM_UNIXTIME($NOW), " .
               "Subject=\"$Subject\", " .
               "Text=\"$Text\", " .
               "User_ID=1, " . // user moderated guest-user
               "Parent_ID=0, " .
               "AnswerNr=1, " .
               "Depth=1, " .
               "Approved='P', " .
               "crc32=" . crc32($Text) . ", " .
               "PosIndex='**'";

      $result = db_query( "Registration.register_blocked_user.insert_new_post({$this->uhandle})", $query );

      if( mysql_affected_rows() != 1)
         error('mysql_insert_post', "Registration.register_blocked_user.insert_new_post2({$this->uhandle})");

      $new_id = mysql_insert_id();
      db_query( "Registration.register_blocked_user.new_thread($new_id)",
         "UPDATE Posts SET Thread_ID=ID, LastPost=ID WHERE ID=$new_id LIMIT 1" );

      if( mysql_affected_rows() != 1)
         error('mysql_insert_post', "Registration.register_blocked_user.new_thread2($new_id)");

      add_forum_log( $new_id, $new_id, FORUMLOGACT_NEW_PEND_POST . ':new_thread' );
   } //register_blocked_user

} // end of 'UserRegistration'

?>
