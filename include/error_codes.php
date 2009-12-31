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

require_once( 'include/globals.php' );

$TranslateGroups[] = "Error";



 /*!
  * \class ErrorCode
  *
  * \brief Class to manage error-code and some attributes of additional logging with error-texts
  */

// lazy-init in ErrorCode-funcs
$ARR_GLOBALS_ERRORS = array();

class ErrorCode
{
   // ------------ static functions ----------------------------

   function echo_error_text( $error_code, $error_log_id )
   {
      if( $error_log_id && ErrorCode::is_shown_error_log_id( $error_code ) )
         echo " [ERRLOG{$error_log_id}]: ";
      echo ErrorCode::get_error_text( $error_code );
   }

   function get_error_text( $error_code )
   {
      global $ARR_GLOBALS_ERRORS;

      ErrorCode::init();
      if( isset($ARR_GLOBALS_ERRORS['TEXT'][$error_code]) )
         return $ARR_GLOBALS_ERRORS['TEXT'][$error_code];
      else
         return @$ARR_GLOBALS_ERRORS['TEXT']['internal_error'] . " ($error_code)"; // default-error
   }

   function is_shown_error_log_id( $error_code )
   {
      global $ARR_GLOBALS_ERRORS;

      ErrorCode::init();
      if( isset($ARR_GLOBALS_ERRORS['LOG_ID'][$error_code]) )
         return $ARR_GLOBALS_ERRORS['LOG_ID'][$error_code];
      else
         return true; // show error-log-id for unknown error-code
   }

   function is_sensitive( $error_code )
   {
      global $ARR_GLOBALS_ERRORS;

      ErrorCode::init();
      if( isset($ARR_GLOBALS_ERRORS['SENSITIVE'][$error_code]) )
         return $ARR_GLOBALS_ERRORS['SENSITIVE'][$error_code];
      else
         return false; // default = non-sensitive data shown
   }

   function init()
   {
      global $ARR_GLOBALS_ERRORS;

      // lazy-init of texts
      if( !isset($ARR_GLOBALS_ERRORS['TEXT']) )
      {
         $arr = array();
         $arr_logid = array();
         $arr_secret = array();

         $arr_logid['internal_error'] = 1;
         $arr_logid['constraint_votes_delete_feature'] = 1;
         $arr_logid['couldnt_update_translation'] = 1;
         $arr_logid['feature_disabled'] = 1;
         $arr_logid['folder_not_found'] = 1;
         $arr_logid['game_delete_invitation'] = 1;
         $arr_logid['invalid_action'] = 1;
         $arr_logid['invalid_args'] = 1;
         $arr_logid['invalid_filter'] = 1;
         $arr_logid['ip_blocked_guest_login'] = 1;
         $arr_logid['ip_blocked_register'] = 1;
         $arr_logid['mail_failure'] = 1;
         $arr_logid['move_problem'] = 1;
         $arr_logid['mysql_connect_failed'] = 1;
         $arr_logid['mysql_insert_game'] = 1;
         $arr_logid['mysql_insert_message'] = 1;
         $arr_logid['mysql_insert_move'] = 1;
         $arr_logid['mysql_insert_player'] = 1;
         $arr_logid['mysql_query_failed'] = 1;
         $arr_logid['mysql_select_db_failed'] = 1;
         $arr_logid['mysql_start_game'] = 1;
         $arr_logid['mysql_update_game'] = 1;
         $arr_logid['page_not_found'] = 1;
         $arr_logid['unknown_message'] = 1;
         $arr_logid['unknown_parent_post'] = 1;
         $arr_logid['user_mismatch'] = 1;
         $arr_logid['value_out_of_range'] = 1;

         $arr_secret['mysql_query_failed'] = 0; // contains DB-query, but handle in error.php (for admin)
         $arr_secret['bad_mail_address'] = 1; // contains email
         $arr_secret['mail_failure'] = 1; // contains email


         $arr['internal_error'] = // default-error-text
            T_("Unknown problem. This shouldn't happen. Please send the url of this page to the support, so that this doesn't happen again.");

         $arr['early_pass'] =
            T_("Sorry, you may not pass before all handicap stones are placed.");

         $arr['game_finished'] =
            T_("Sorry, the game has already finished.");

         $arr['game_not_started'] =
            T_("Sorry, the game hasn't started yet.");

         $arr['guest_may_not_receive_messages'] =
            T_("Impossible, guest may not receive messages");

         $arr['illegal_position'] =
            T_("This move leads to an illegal board position.");

         $arr['invalid_action'] =
            T_("This type of action is either unknown or can't be used in this state of the game.");

         $arr['invite_self'] =
            T_("Sorry, you can't invite yourself.");

         $arr['invited_to_unknown_game'] =
            T_("Sorry, can't find the game you are invited to. Already declined?");

         $arr['game_already_accepted'] =
            T_("Sorry, can't find the game you are invited to. Already accepted?");

         $arr['game_delete_invitation'] =
            T_("Delete game failed. This is problably not a problem.");

         $arr['ko'] =
            T_("Sorry, you may not retake a stone which has just captured a stone, " .
               "since it would repeat a previous board position. Look for 'ko' in the rules.");

         $arr['komi_range'] =
            T_("The komi is out of range, please choose a more reasonable value.");

         $arr['time_limit_too_small'] =
            T_("The time limit is too small, please choose at least one hour.");

         $arr['move_problem'] =
            T_("An error occurred for this move. Usually it works if you try again, otherwise please contact the support.");

         $arr['mysql_connect_failed'] =
            T_("Connection to database failed. Please wait a few minutes and test again.");

         $arr['mysql_insert_message'] =
            T_("Sorry, the additon of the message to the database seems to have failed.");

         $arr['mysql_insert_game'] =
            T_("Sorry, the additon of the game to the database seems to have failed.");

         $arr['mysql_insert_move'] = $arr['mysql_update_game'] =
            T_("The insertion of the move into the database seems to have failed. " .
               "This may or may not be a problem, please return to the game to see " .
               "if the move has been registered.");

         $arr['mysql_insert_player'] =
            T_("The insertion of your data into the database seems to have failed. " .
               "If you can't log in, please try once more and, if this fails, contact the support.");

         $arr['mysql_query_failed'] =
            T_("Database query failed. Please wait a few minutes and try again. ");

         $arr['mysql_select_db_failed'] =
            T_("Couldn't select the database. Please wait a few minutes and try again. ");

         $arr['mysql_start_game'] =
            T_("Sorry, couldn't start the game. Please wait a few minutes and try again.");

         $arr['name_not_given'] =
            T_("Sorry, you have to supply a name.");

         $arr['newpassword_already_sent'] =
            T_("A new password has already been sent to this user, please use that password instead of sending another one.");

         $arr['no_email'] =
            T_("Sorry, no email has been given, so I can't send you the password. " .
               "Please log in as guest and use the support forum to get help (provide your user-id and your email).");

         $arr['bad_mail_address'] = //an email address validity function should never be treated as definitive
            T_("Sorry, the email given does not seem to be a valid address. Please, verify your spelling or try another one.");

         $arr['mail_failure'] =
            T_("Sorry, an error occured during sending of the email.");

         $arr['no_initial_rating'] =
            T_("Sorry, you and your opponent need to set an initial rating, otherwise I can't find a suitable handicap");

         $arr['no_uid'] =
            T_("Sorry, I need to know for which user to show the data.");

         $arr['not_allowed_for_guest'] =
            T_("Sorry, this is not allowed for guests, please first register a personal account");

         $arr['not_empty'] =
            T_("Sorry, you may only place stones on empty points.");

         $arr['ip_blocked_guest_login'] =
            T_('Sorry, you are not allowed to login as guest to this server. The IP address you are using has been blocked by the admins.') .
               "<br><br>\n" .
               sprintf( T_('If you think the IP block is not intended for you, please register your account with the <a href="%s">alternative registration page</a>.'),
                  HOSTBASE."register.php" );

         $arr['ip_blocked_register'] =
            T_('Sorry, you are not allowed to register a new account. The IP address you are using has been blocked by the admins.');

         $arr['not_logged_in'] = sprintf(
            T_("Sorry, you have to be logged in to do that.\n" .
               "<p></p>\n" .
               "The reasons for this problem could be any of the following:\n" .
               "<ul>\n" .
               "<li> You haven't got an <a href=\"%1\$sregister.php\">account</a>, or haven't <a href=\"%1\$sindex.php\">logged in</a> yet.\n" .
               "<li> Your cookies have expired. This happens once a month.\n" .
               "<li> You haven't enabled cookies in your browser.\n" .
               "</ul>"),
            HOSTBASE );

         $arr['login_denied'] = ''; // splitted in 2 mapped texts in error.php
         $arr['login_denied:blocked_with_reason'] =
            T_('Sorry, you are not allowed to login to this server. Your account has been blocked by admins out of the following reason:');
         $arr['login_denied:blocked'] =
            T_('Sorry, you are not allowed to login to this server. Your account has been blocked by admins.');

         $arr['edit_bio_denied'] =
            T_('Sorry, you are not allowed to edit your bio. This feature has been blocked by admins.');

         $arr['cookies_disabled'] =
            T_("Sorry, you haven't enabled cookies in your browser.");

         $arr['feature_disabled'] =
            T_("Sorry, this feature has been disabled on this server.");

         $arr['upload_miss_temp_folder'] = // Introduced in PHP 4.3.10 and PHP 5.0.3
            T_("Sorry, missing a temporary folder to upload files. Please contact the administrators.");

         $arr['upload_failed'] =
            T_("Sorry, the file upload failed.");

         $arr['not_your_turn'] =
            T_("Sorry, it's not your turn.");

         $arr['already_played'] =
            T_("Sorry, this turn has already been played.");

         $arr['page_not_found'] =
            T_('Page not found. Please contact the server administrators and inform them of the time the ' .
               'error occurred, and anything you might have done that may have caused the error.');
            //echo '<br>('.@$_SERVER['REDIRECT_STATUS'].': '.@$_SERVER['REDIRECT_URL'].' / '.getcwd().')';

         $arr['password_illegal_chars'] =
            T_("The password contained illegal characters, only the characters a-z, A-Z, 0-9 and -_+.,:;?!%* are allowed.");

         $arr['userid_illegal_chars'] =
            T_("The userid contained illegal characters, only the characters a-z, A-Z, 0-9 and -_+ are allowed, and the first one must be a-z, A-Z.");

         $arr['password_mismatch'] =
            T_("The confirmed password didn't match the password, please go back and retry.");

         $arr['password_too_short'] =
            T_("Sorry, the password must be at least six characters long.");

         $arr['receiver_not_found'] =
            T_("Sorry, couldn't find the receiver of your message. Make sure to use the userid, not the full name.");

         $arr['rank_not_rating'] =
            T_("Sorry, I've problem with the rating, did you forget to specify 'kyu' or 'dan'?");

         $arr['rating_not_rank'] =
            T_("Sorry, I've problem with the rating, you shouldn't use 'kyu' or 'dan' for this ratingtype");

         $arr['registration_policy_not_checked'] =
            T_("Please read the Rules of Conduct page and check the box if you accept it.");

         $arr['suicide'] =
            T_("Sorry, this stone would have killed itself, but suicide is not allowed under this ruleset.");

         $arr['unknown_game'] =
            T_("Sorry, I can't find that game.");

         $arr['unknown_forum'] =
            T_("Sorry, I couldn't find that forum you wanted to show.");

         $arr['forbidden_forum'] =
            T_("Sorry, you are not allowed to view or post in this forum.");

         $arr['read_only_forum'] =
            T_("Sorry, you are not allowed to post in this forum.");

         $arr['forbidden_post'] =
            T_("Sorry, you are not allowed to view this post.");

         $arr['unknown_post'] =
            T_("Sorry, I couldn't find the post you wanted to show.");

         $arr['unknown_parent_post'] =
            T_("Hmm, this message seems to be a reply to a non-existing post.");

         $arr['unknown_message'] =
            T_("Sorry, I couldn't find the message you wanted to show.");

         $arr['unknown_user'] =
            T_("Sorry, I couldn't find this user.");

         $arr['user_mismatch'] =
            T_("Sorry, the logged user seems to have changed during the operation.");

         $arr['userid_in_use'] =
            T_("Sorry, this userid is already used, please try to find a unique userid.");

         $arr['userid_too_short'] =
            T_("Sorry, userid must be at least 3 characters long.");

         $arr['invalid_user'] = // Players.ID
            T_("Sorry, invalid player id used.");

         $arr['invalid_opponent'] = // Players.ID
            T_("Sorry, invalid player id used as opponent.");

         $arr['value_out_of_range'] =
            T_("Couldn't extrapolate value in function interpolate");

         $arr['waitingroom_delete_not_own'] =
            T_("Sorry, you may only delete your own game.");

         $arr['waitingroom_game_not_found'] =
            T_("Sorry, couldn't find this waiting room game. Probably someone has already joined it.");

         $arr['waitingroom_own_game'] =
            T_("Sorry, you can't join your own game.");

         $arr['waitingroom_not_in_rating_range'] =
            T_("Sorry, you are not in the specified rating range.");

         $arr['waitingroom_not_enough_rated_fin_games'] =
            T_("Sorry, you don't have enough rated finished games to join this game offer.");

         $arr['waitingroom_not_same_opponent'] =
            T_("Sorry, on this game offer there are counter- or time-based restrictions for challenges from the same opponent.");

         $arr['wrong_number_of_handicap_stone'] =
            T_("Wrong number of handicap stones");

         $arr['wrong_password'] =
            T_("Sorry, you didn't write your current password correctly.");

         $arr['wrong_rank_type'] =
            T_("Unknown rank type");

         $arr['wrong_userid'] =
            T_("Sorry, I don't know anyone with that userid.");

         $arr['rating_out_of_range'] =
            T_("Sorry, the initial rating must be between 30 kyu and 6 dan.");

         $arr['value_not_numeric'] = //TODO unused, but could be useful, so not deleted
            T_("Sorry, you wrote a non-numeric value on a numeric field.");

         $arr['not_translator'] =
            T_("Sorry, only translators are allowed to translate.") . '<p></p>' .
            T_("If you want to help translating dragon, please post a message to the 'translation' forum.");

         $arr['not_correct_transl_language'] =
            T_("Sorry, you are not allowed to translate the specified language.");

         $arr['translation_bad_language_or_group'] =
            T_("Sorry, I couldn't find the language or group you want to translate. Please contact the support.");

         $arr['couldnt_update_translation'] =
            T_("Sorry, something went wrong when trying to insert the new translations into the database.");

         $arr['adminlevel_too_low'] =
            T_("Sorry, this page is solely for users with administrative tasks.");

         $arr['new_admin_already_admin'] =
            T_("This user is already an admin.");

         $arr['admin_no_such_entry'] =
            T_("Sorry, couldn't find that entry.");

         $arr['unknown_tournament'] =
            T_("Sorry, I couldn't find the given tournament.");

         $arr['tournament_edit_not_allowed'] =
            T_("Sorry, you are not allowed to add or edit this tournament.");

         $arr['tournament_director_edit_not_allowed'] =
            T_("Sorry, you are not allowed to add, edit or delete a tournament director for this tournament.");

         $arr['tournament_director_new_del_not_allowed'] =
            T_("Sorry, you are not allowed to add or delete a tournament director for this tournament.");

         $arr['tournament_register_not_allowed'] =
            T_("Sorry, you are not allowed to register for this tournament.");

         $arr['tournament_miss_rules'] =
            T_("Sorry, missing configured rules-set for this tournament.");

         $arr['folder_not_found'] =
            T_("Sorry, couldn't find the specified message folder.");

         $arr['invalid_filter'] =
            T_("Sorry, there's a configuration problem with a search-filter.");

         $arr['invalid_args'] =
            T_("Sorry, invalid arguments used.");

         $arr['constraint_votes_delete_feature'] =
            T_("Sorry, feature can't be deleted because of existing votes for feature.");

         $arr['feature_edit_not_allowed'] =
            T_("Sorry, you are not allowed to add, edit or delete features.");

         $arr['feature_edit_bad_status'] =
            T_("Sorry, you are not allowed to edit feature on that status.");

         $arr['miss_user_quota'] =
            T_("Sorry, something is wrong with your user data. Please contact an administrator to fix this.");

         $ARR_GLOBALS_ERRORS['TEXT'] = $arr;
         $ARR_GLOBALS_ERRORS['LOG_ID'] = $arr_logid;
         $ARR_GLOBALS_ERRORS['SENSITIVE'] = $arr_secret;
      }
   } //init

} // end of 'ErrorCode'

?>
