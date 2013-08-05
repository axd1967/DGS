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

require_once 'include/std_functions.php';
require_once 'include/classlib_user.php';
require_once 'include/classlib_userconfig.php';
require_once 'include/classlib_userquota.php';
require_once 'include/db/verification.php';
require_once 'forum/forum_functions.php';
require_once 'include/error_codes.php';



define('USE_REGEXP_REGISTRATION', 1); // loose account name reject

 /*!
  * \class UserRegistration
  *
  * \brief Class to manage error-code and some attributes of additional logging with error-texts
  */
class UserRegistration
{
   private $die_on_error;
   private $errors = array();
   private $key_suffix; // for register-bot prevention

   public $uhandle;
   public $policy;

   public $name;
   public $password;
   public $password2;
   public $email;
   public $comment;

   public function __construct( $die_on_error=true )
   {
      $this->die_on_error = $die_on_error;
      $this->key_suffix = $this->build_key_suffix();
   }//__construct

   /*! \brief Parses arguments from URL with dynamic form-keys. */
   public function parse_args()
   {
      // check for correct key-suffix (day-switch for example)
      // NOTE: allow 2 hours for registering, see also build_key_suffix()-func
      $cnt = 2;
      while ( $cnt-- && !isset($_REQUEST[$this->build_key('userid')]) )
         $this->key_suffix = $this->build_key_suffix( -1 );
      if ( $cnt <= 0 )
      {
         $this->errors[] = ErrorCode::get_error_text('miss_args');
         return;
      }

      // for both normal and blocked registration
      $this->uhandle = get_request_arg($this->build_key('userid'));
      $this->policy = get_request_arg($this->build_key('policy'));
      $this->email = trim(get_request_arg($this->build_key('email')));

      // only for normal-registration
      $this->name = trim(get_request_arg($this->build_key('name')));
      $this->password = get_request_arg($this->build_key('passwd'));
      $this->password2 = get_request_arg($this->build_key('passwd2'));

      // only for blocked-registration
      $this->comment = trim(get_request_arg($this->build_key('comment')));
   }

   private function build_key_suffix( $add=0 )
   {
      // NOTE: use different form-keys to make it more difficult for register-bots to register
      // - use dynamic suffix dependent on current hour
      return substr( md5( floor($GLOBALS['NOW'] / SECS_PER_HOUR) + $add ), 4, 5 );
   }

   public function build_key( $key )
   {
      return $key . '_' . $this->key_suffix;
   }

   // returns 0=no-error, array with error-texts otherwise or error thrown (depends on die-mode)
   public function check_registration_normal()
   {
      $this->errors = array();
      $this->parse_args();

      $this->check_userid();
      if ( !$this->die_on_error )
         $this->check_existing_user();
      $this->check_name();
      $this->check_password();
      $this->check_policy();
      if ( $this->die_on_error )
         $this->check_existing_user();
      $this->check_email();
      return (count($this->errors)) ? $this->errors : 0;
   }//check_registration_normal

   // returns 0=no-error, array with error-texts otherwise or error thrown (depends on die-mode)
   public function check_registration_blocked()
   {
      $this->errors = array();
      $this->parse_args();

      $this->check_userid();
      if ( !$this->die_on_error )
         $this->check_existing_user();
      $this->check_email();
      $this->check_policy();
      if ( $this->die_on_error )
         $this->check_existing_user();
      return (count($this->errors)) ? $this->errors : 0;
   }//check_registration_blocked


   private function check_userid()
   {
      if ( strlen( $this->uhandle ) < 3 )
         $this->_error('userid_too_short', "UserReg.check_userid({$this->uhandle})");
      if ( strlen( $this->uhandle ) > 16 )
         $this->_error('userid_too_long', "UserReg.check_userid({$this->uhandle})");
      if ( illegal_chars( $this->uhandle ) )
         $this->_error('userid_illegal_chars', "UserReg.check_userid({$this->uhandle})");
   }

   private function check_name()
   {
      if ( strlen( $this->name ) < 1 )
         $this->_error('name_not_given', "UserReg.check_name({$this->name})");
   }

   private function check_password()
   {
      if ( strlen($this->password) < 6 )
         $this->_error('password_too_short', 'UserReg.check_password');
      if ( illegal_chars( $this->password, true ) )
         $this->_error('password_illegal_chars', 'UserReg.check_password');

      if ( $this->password != $this->password2 )
         $this->_error('password_mismatch', 'UserReg.check_password');
   }//check_password

   private function check_policy()
   {
      if ( !$this->policy )
        $this->_error('registration_policy_not_checked', 'UserReg.check_policy');
   }

   // returns 0=no-error, array with error-texts otherwise or error thrown (depends on die-mode)
   private function check_email()
   {
      if ( (string)$this->email != '' )
      {
         $errorcode = verify_invalid_email( "UserReg.check_email({$this->uhandle})", $this->email, $this->die_on_error );
         if ( $errorcode )
            $this->_error($errorcode, "UserReg.check_email2({$this->uhandle})");
      }
   }//check_email


   // internal (throw error or add to error-list)
   private function _error( $error_code, $dbg_msg=null )
   {
      if ( $this->die_on_error )
         error($error_code, $dbg_msg);
      else
         $this->errors[] = ErrorCode::get_error_text($error_code);
   }


   private function check_existing_user()
   {
      if ( (string)$this->uhandle == '' )
         return;

      if ( !USE_REGEXP_REGISTRATION )
      {
         //if foO exist, reject foo but accept fo0 (with a zero instead of uppercase o)
         $result = db_query( "UserReg.check_existing_user.find_player({$this->uhandle})",
            "SELECT Handle FROM Players WHERE Handle='".mysql_addslashes($this->uhandle)."'" );
      }
      else
      {
         //reject the O0, l1I and S5 confusing matchings (used by account usurpers)
         //for instance, with the Arial font, a I and a l can't be distinguished
         $regx = preg_replace(
            array( '/[0o]/i', '/[1li]/i', '/[5s]/i' ), // match
            array( '[0o]',    '[1li]',    '[5s]' ),    // replacements (regex for REGEXP-SQL-match)
            preg_quote($this->uhandle) ); //quotemeta()
         $regx = '^' . mysql_addslashes($regx) . '$';

         $result = db_query( "UserReg.check_existing_user.find_player_regexp({$this->uhandle},[$regx]})",
            "SELECT Handle FROM Players WHERE Handle REGEXP '$regx'" );
      }

      if ( @mysql_num_rows($result) > 0 )
         $this->_error('userid_in_use', "UserReg.check_existing_user({$this->uhandle})");
   }//check_existing_user


   // do the registration to the database
   public function register_user( $set_cookie=true )
   {
      global $NOW, $base_path;

      $code = make_session_code();
      $ip = (string)@$_SERVER['REMOTE_ADDR'];
      $browser = substr(@$_SERVER['HTTP_USER_AGENT'], 0, 150);

      ta_begin();
      {//HOT-section for registering new player
         $upd = new UpdateQuery('Players');
         $upd->upd_txt('Handle', $this->uhandle );
         $upd->upd_txt('Name', $this->name );
         $upd->upd_raw('Password', sprintf( "%s('%s')", PASSWORD_ENCRYPT, mysql_addslashes($this->password) ));
         if ( $this->email )
            $upd->upd_txt('Email', $this->email );
         $upd->upd_time('Registerdate', $NOW);
         $upd->upd_txt('Sessioncode', $code);
         $upd->upd_time('Sessionexpire', $NOW + SESSION_DURATION );
         if ( $ip )
            $upd->upd_txt('IP', $ip );
         if ( $browser )
            $upd->upd_txt('Browser', $browser );
         $upd->upd_num('VaultCnt', VAULT_CNT ); // initial quota
         $upd->upd_time('VaultTime', $NOW + VAULT_DELAY );
         $upd->upd_num('UserFlags', USERFLAG_ACTIVATE_REGISTRATION|USERFLAG_VERIFY_EMAIL );
         $result = db_query( "UserReg.register_user.insert_player({$this->uhandle})",
            "INSERT INTO Players SET " . $upd->get_query() );

         $new_id = mysql_insert_id();

         if ( mysql_affected_rows() != 1 )
            error('mysql_insert_player', "UserReg.register_user.insert_player2({$this->uhandle})");

         ConfigPages::insert_default( $new_id );
         ConfigBoard::insert_default( $new_id );
         UserQuota::insert_default( $new_id );

         // send activation-mail with verification-code for email
         $vfy_code = Verification::build_code( $new_id, $this->email );
         $vfy = new Verification( 0, $new_id, 0, $NOW, $this->email, $vfy_code );
         if ( $vfy->insert() )
         {
            $vfy_id = $vfy->ID;
            $text = sprintf( T_('You are now registered with an account at Dragon Go Server, %s!'), $this->uhandle ) . "\n\n"
               . T_('Your account\'s user-id is:') . " {$this->uhandle}\n\n"
               . T_('Before you can use your account, you first need to activate it.') . "\n"
               . T_('You may be redirected to login first with your chosen password.') . "\n\n"
               . T_('To activate your account, please follow this link:#reg') . "\n\n"
               . "    ".HOSTBASE."verify_email.php?uid=$new_id".URI_AMP_IN."vid={$vfy_id}".URI_AMP_IN."code=".urlencode($vfy_code)."\n\n"
               . sprintf( T_('This verification code can only be used once and expires after %s days.'),
                          VFY_MAX_DAYS_CODE_VALID ) . "\n\n"
               . T_('Please do not reply to this automated message!') . "\n"
               . T_('In case of problems, please login as guest-user and ask for help in the support-forum:') . "\n\n"
               . "    ".HOSTBASE."forum/read.php?forum=".FORUM_ID_SUPPORT . "\n\n"
               . T_('Provide your user-id and describe the problem with the registration process.') . "\n\n"
               ;
            send_email( "UserReg.register_user.send_activation($new_id,$vfy_id)", $this->email, EMAILFMT_SKIP_WORDWRAP,
               $text, T_('Welcome to the Dragon Go Server') );
         }
      }
      ta_end();

      if ( $set_cookie )
         set_login_cookie( $this->uhandle, $code );
   }//register_user


   // send request to Support-forum (moderated)
   public function register_blocked_user( $forum_id )
   {
      global $NOW;
      if ( !is_numeric($forum_id) || $forum_id <= 0 )
         error('invalid_args', "UserReg.register_blocked_user($forum_id)");

      $ip = (string)@$_SERVER['REMOTE_ADDR'];

      $Subject = "Admin-Request to register account to bypass IP-block";
      $Text = T_("This is an automated support-request to register a new account on behalf of an IP-blocked user with the following credentials:")
         . "\n"
         . "Userid: {$this->uhandle}\n"
         . "Email: {$this->email}\n"
         . "Blocked-IP: $ip\n\n"
         . "User-Comment:\n" . $this->comment;

      $upd = new UpdateQuery('Posts');
      $upd->upd_num('Forum_ID', $forum_id );
      $upd->upd_num('Thread_ID', 0 );
      $upd->upd_time('Time', $NOW );
      $upd->upd_txt('Subject', $Subject );
      $upd->upd_txt('Text', $Text );
      $upd->upd_num('User_ID', 1 ); // user moderated guest-user
      $upd->upd_num('Parent_ID', 0 );
      $upd->upd_num('AnswerNr', 1 );
      $upd->upd_num('Depth', 1 );
      $upd->upd_txt('Approved', 'P' );
      $upd->upd_num('crc32', crc32($Text) );
      $upd->upd_txt('PosIndex', '**' );

      ta_begin();
      {//HOT-section to register blocked user
         $result = db_query( "UserReg.register_blocked_user.insert_new_post({$this->uhandle})",
            "INSERT INTO Posts SET " . $upd->get_query() );
         if ( mysql_affected_rows() != 1)
            error('mysql_insert_post', "UserReg.register_blocked_user.insert_new_post2({$this->uhandle})");

         $new_id = mysql_insert_id();
         db_query( "UserReg.register_blocked_user.new_thread($new_id)",
            "UPDATE Posts SET Thread_ID=ID, LastPost=ID WHERE ID=$new_id LIMIT 1" );
         if ( mysql_affected_rows() != 1)
            error('mysql_insert_post', "UserReg.register_blocked_user.new_thread2($new_id)");

         add_forum_log( $new_id, $new_id, FORUMLOGACT_NEW_PEND_POST . ':new_thread' );
      }
      ta_end();
   }//register_blocked_user


   /*!
    * \brief Processes email-verification (optionally coupled with account activation after registration).
    * \param $user User-object of user to verify code for
    * \param $vid Verification.ID to verify code against
    * \param $code input-code to verify against
    * \return error-string on failure to process verification successfully; or integer-bitmask-value on success
    *       with set flags (USERFLAG_VERIFY_EMAIL, USERFLAG_ACTIVATE_REGISTRATION) depending on executed action
    *
    * \note User need to be logged-in!
    */
   public static function process_verification( $user, $vid, $code )
   {
      global $player_row, $NOW;

      // paranoid checking to avoid abuse ....

      if ( @$player_row['ID'] == 0 )
         error('login_if_not_logged_in', "UserReg.process_verification.check_login");
      if ( !($user instanceof User) )
         error('invalid_args:nolog', "UserReg.process_verification.check.user($vid)");
      $uid = $user->ID;
      if ( !is_numeric($uid) )
         error('invalid_args:nolog', "UserReg.process_verification.check.uid($uid,$vid)");

      if ( !is_numeric($vid) || $vid <= 0 )
         return sprintf( T_('Missing valid id to process verification for user [%s].'), $user->Handle );
      if ( $uid != $player_row['ID'] )
         return sprintf( T_('Logged-in as wrong user [%s] to process verification with id [%s] for user [%s].'),
            @$player_row['Handle'], $vid, $user->Handle );

      $user_flags = (int)$user->urow['UserFlags'];
      if ( $user_flags == 0 )
         return sprintf( T_('There is nothing to verify for user [%s].'), $user->Handle );
      if ( strlen($code) == 0 )
         return sprintf( T_('Missing code to process verification with id [%s].'), $vid );

      $verification = Verification::load_verification( $vid );
      if ( is_null($verification) )
         return sprintf( T_('No verification entry found for verification-id [%s].'), $vid );
      if ( $verification->uid != $uid )
         return sprintf( T_('Found wrong user [%s] to process verification with id [%s].'), $user->Handle, $vid );
      if ( $verification->Verified != 0 )
         return sprintf( T_('Verification with id [%s] has been processed already and is no longer valid.'), $vid );

      // expire verification by invalidating for too old codes
      if ( $verification->Created < $NOW - VFY_MAX_DAYS_CODE_VALID*SECS_PER_HOUR )
      {
         if ( $user_flags & USERFLAG_ACTIVATE_REGISTRATION )
            $helptext = T_('Please contact support to help with your registration.');
         elseif ( $user_flags & USERFLAG_VERIFY_EMAIL )
            $helptext = T_('Please repeat changing your email getting a new verification-code, or else contact support.');

         $verification->Verified = $NOW;
         $verification->update();
         return sprintf( T_('Verification-code expired after %s days.'), VFY_MAX_DAYS_CODE_VALID ) . "<br>\n$helptext";
      }

      // safety-checks on verification-db-data (shouldn't happen)
      $errorcode = verify_invalid_email( "UserReg:process_verification.verify_email($uid,$vid)",
         $verification->Email, /*die-on-err*/false );
      if ( $errorcode )
         error($errorcode, "UserReg:process_verification.check_email($uid,$vid)");
      if ( strlen(trim($verification->Code)) < VFY_MIN_CODELEN )
         error('internal_error', "UserReg.process_verification.bad_code.verify_code($uid,$vid)");

      // invalidate verification if tried too often (to prevent brute-force attack)
      if ( $verification->increase_verification_counter(25) )
         error('verification_invalidated', "UserReg.process_verification.check.count($uid,$vid)");

      if ( strcmp($code, $verification->Code) != 0 )
         return T_('Verification code is invalid!');


      // verify-email and/or activate registration
      ta_begin();
      {//HOT-section for handling verification
         $verification->Verified = $NOW;
         $verification->update();

         $set_userflags = $clear_userflags = 0;
         if ( $user_flags & USERFLAG_ACTIVATE_REGISTRATION ) // activate account by clearing flag, if email verified
            $clear_userflags |= USERFLAG_ACTIVATE_REGISTRATION;
         if ( $user_flags & USERFLAG_VERIFY_EMAIL ) // replace email and clear flag, if email verified
            $clear_userflags |= USERFLAG_VERIFY_EMAIL;
         if ( $user_flags & (USERFLAG_ACTIVATE_REGISTRATION|USERFLAG_VERIFY_EMAIL) )
            $set_userflags |= USERFLAG_EMAIL_VERIFIED;
         if ( $clear_userflags > 0 )
         {
            // NOTE: this update could go wrong leaving verification invalidated -> must be handled by admin
            $upd = new UpdateQuery('Players');
            $upd->upd_txt('Email', $verification->Email );
            $upd->upd_raw('UserFlags', "(UserFlags | $set_userflags) & ~$clear_userflags" );
            db_query("UserReg:process_verification.upd_email_uflags($uid,$vid,$user_flags->$clear_userflags)",
               "UPDATE Players SET " . $upd->get_query() . " WHERE ID=$uid LIMIT 1" );
         }
      }
      ta_end();

      return $clear_userflags;
   }//process_verification

} // end of 'UserRegistration'

?>
