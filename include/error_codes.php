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

require_once 'include/globals.php';

$TranslateGroups[] = "Error";



 /*!
  * \class ErrorCode
  *
  * \brief Class to manage error-code and some attributes of additional logging with error-texts
  */
class ErrorCode
{
   private static $ARR_ERRORS = array(); // lazy-init in ErrorCode-funcs: [key][errorcode] => error-text

   // ------------ static functions ----------------------------

   public static function echo_error_text( $error_code, $error_log_id )
   {
      if ( $error_log_id && self::is_shown_error_log_id( $error_code ) )
         echo span('ErrorMsg', " [ERRLOG $error_log_id] ($error_code): ");
      echo self::get_error_text( $error_code );
   }

   public static function get_error_text( $error_code )
   {
      self::init();
      if ( isset(self::$ARR_ERRORS['TEXT'][$error_code]) )
         return self::$ARR_ERRORS['TEXT'][$error_code];
      else
         return " ($error_code) " . @self::$ARR_ERRORS['TEXT']['internal_error']; // default-error
   }

   // \internal
   private static function is_shown_error_log_id( $error_code )
   {
      self::init();
      if ( isset(self::$ARR_ERRORS['LOG_ID'][$error_code]) )
         return self::$ARR_ERRORS['LOG_ID'][$error_code];
      else
         return !isset(self::$ARR_ERRORS['TEXT'][$error_code]); // show error-log-id for unknown error-code
   }

   /*! \brief Returns true, if error-code contains sensitive-data that is not meant for public eyes. */
   public static function is_sensitive( $error_code )
   {
      self::init();
      if ( isset(self::$ARR_ERRORS['SENSITIVE'][$error_code]) )
         return self::$ARR_ERRORS['SENSITIVE'][$error_code];
      else
         return false; // default = non-sensitive data shown
   }

   // \internal
   private static function init()
   {
      global $base_path;

      // lazy-init of texts
      if ( !isset(self::$ARR_ERRORS['TEXT']) )
      {
         // error-codes for which error-details contain sensitive data, that should be hidden for non-admin users.
         //    has entry = debug-msg (label) with details not shown
         //    value 0 = special handling required, see handle_error(); only codes with sensitive data needs to be listed here
         //    value 1 = just hide error-details
         $arr_secret = array();

         // NOTE: currently undefined error-codes (without error-text which is possible):
         //   assert, couldnt_open_file, database_corrupted, mysql_insert_post, mysql_update_message,
         //   mysql_update_game, not_implemented

         // error-codes for which Errorlog.ID is shown (reference for support); keep in alphabetic-order
         $arr_logid = array();
         $arr_logid['mysql_insert_post'] = 1;

         // IMPORTANT NOTE:
         //   when adding new error-codes also check DgsErrors::need_db_errorlog()-func in 'include/error_functions.php' !!
         $arr = array();

         $arr_logid['internal_error'] = 1;
         $arr['internal_error'] = // default-error-text
            T_("Unknown problem. This shouldn't happen. Please report this to the support describing the context of your actions.");

         $arr_logid['user_init_error'] = 1;
         $arr['user_init_error'] =
            T_("User initialization error occured because of inconsistent user data. Please report this problem to the support.");

         $arr_logid['wrong_players'] = 1;
         $arr['wrong_players'] =
            T_("The player-IDs are wrong for this operation. Please send this problem to the support.");

         $arr['server_down'] =
            T_("Sorry, the Dragon Go Server is down for maintenance.");

         $arr['bad_host'] =
            T_("Bad hostname used for request.");

         $arr['fever_vault'] =
            T_("The activity of your account grew too high. Temporary access restriction is in place.");

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

         $arr_logid['invalid_action'] = 1;
         $arr['invalid_action'] =
            T_("This type of action is either unknown or can't be used in this state of the game.");

         $arr['invalid_game_status'] =
            T_("Sorry, the game is in the wrong state to perform this operation.");

         $arr['invite_self'] =
            T_("Sorry, you can't invite yourself.");

         $arr['bulkmessage_self'] =
            T_('Message to myself is forbidden for bulk message.');

         $arr['bulkmessage_forbidden'] =
            T_('Sorry, you are not allowed to send a bulk message.');

         $arr['invited_to_unknown_game'] =
            T_("Sorry, can't find the game you are invited to. Already declined?");

         $arr['game_already_accepted'] =
            T_("Sorry, can't find the game you are invited to. Already accepted?");

         $arr_logid['game_delete_invitation'] = 1;
         $arr['game_delete_invitation'] =
            T_("Delete game failed. This is problably not a problem.");

         $arr_logid['invite_bad_gamesetup'] = 1;
         $arr['invite_bad_gamesetup'] =
            T_("Sorry, missing game-setup for invitation. Please contact support.");

         $arr_logid['max_games'] = 1;
         $arr['max_games'] =
            T_("Sorry, your limit on started games has exceeded.");

         $arr['max_games_opp'] =
            T_("Sorry, the limit of your opponent on started games has exceeded.");

         $arr['max_games_tourney_reg'] =
            T_("Sorry, your limit on started games for tournament registration has exceeded.");

         $arr['max_games_user_tourney_reg'] =
            T_("The users limit on started games for tournament registration has exceeded.");

         $arr['ko'] =
            T_("Sorry, you may not retake a stone which has just captured a stone, " .
               "since it would repeat a previous board position. Look for 'ko' in the rules.");

         $arr_logid['unknown_ruleset'] = 1;
         $arr['unknown_ruleset'] =
            T_("Sorry, an unknown ruleset has been used.");

         $arr['komi_range'] =
            T_("The komi is out of range, please choose a more reasonable value.");

         $arr['komi_bad_fraction'] =
            T_("Fractional part of komi can only be .0 or .5.");

         $arr['handicap_range'] =
            T_("The handicap is out of range, please choose a more reasonable value.");

         $arr['time_limit_too_small'] =
            T_("The time limit is too small, please choose at least one hour.");

         $arr_logid['move_problem'] = 1;
         $arr['move_problem'] =
            T_("An error occurred for this move. Usually it works if you try again, otherwise please contact the support.");

         $arr['mysql_connect_failed'] =
            T_("Connection to database failed. Please wait a few minutes and test again.");

         $arr_logid['mysql_insert_message'] = 1;
         $arr['mysql_insert_message'] =
            T_("Sorry, the additon of the message to the database seems to have failed.");

         $arr_logid['mysql_insert_game'] = 1;
         $arr['mysql_insert_game'] =
            T_("Sorry, the additon of the game to the database seems to have failed.");

         $arr_logid['mysql_insert_move'] = $arr_logid['mysql_update_game'] = 1;
         $arr['mysql_insert_move'] = $arr['mysql_update_game'] =
            T_("The insertion of the move into the database seems to have failed. " .
               "This may or may not be a problem, please return to the game to see " .
               "if the move has been registered.");

         $arr_logid['mysql_insert_player'] = 1;
         $arr['mysql_insert_player'] =
            T_("The insertion of your data into the database seems to have failed. " .
               "If you can't log in, please try once more and, if this fails, contact the support.");

         $arr_secret['mysql_query_failed'] = 0; // contains DB-query, but handle in error.php (for admin)
         $arr_logid['mysql_query_failed'] = 1;
         $arr['mysql_query_failed'] =
            T_("Database query failed. Please wait a few minutes and try again.");

         $arr['mysql_select_db_failed'] =
            T_("Couldn't select the database. Please wait a few minutes and try again.");

         $arr_logid['mysql_start_game'] = 1;
         $arr['mysql_start_game'] =
            T_("Sorry, couldn't start the game. Please wait a few minutes and try again.");

         $arr['optlock_clash'] =
            T_("Sorry, someone changed the data you were about to save (optimistic-locking clash). Please reload and re-enter your changes for an update.");

         $arr['name_not_given'] =
            T_("Sorry, you have to supply a name.");

         $arr['newpassword_already_sent'] =
            T_("A new password has already been sent to this user. Please use that password instead of sending another one.");

         $arr['no_email:support'] =
            T_("Please log in as guest and use the support forum to get help (provide your user-id and your email).");
         $arr['no_email'] =
            T_("Sorry, no email has been given, so I can't send you the password.") . ' ' .
            $arr['no_email:support'];

         $arr_secret['bad_mail_address'] = 1; // contains email
         $arr['bad_mail_address'] = //an email address validity function should never be treated as definitive
            T_("Sorry, the email given does not seem to be a valid address. Please, verify your spelling or try another one.");

         $arr_secret['mail_failure'] = 1; // contains email
         $arr_logid['mail_failure'] = 1;
         $arr['mail_failure'] =
            T_("Sorry, an error occured during sending of the email.");

         $arr['no_initial_rating'] =
            T_("Sorry, you and your opponent need to set an initial rating, otherwise I can't find a suitable handicap");

         $arr['multi_player_need_initial_rating'] =
            T_("Sorry, you need an initial rating to start a multi-player-game.");

         $arr['multi_player_no_users'] =
            T_('Sorry, I can\'t find any users for the multi-player-game.');

         $arr['multi_player_master_mismatch'] =
            T_("Sorry, for this operation you need to be the game-master of the multi-player-game.");

         $arr['multi_player_unknown_user'] =
            T_('Sorry, the user is not registered or invited for the multi-player-game.');

         $arr['multi_player_msg_miss_game'] =
            T_('Missing game-id for multi-player-game message.');

         $arr['multi_player_msg_no_mpg'] =
            T_('Multi-player-game message can only be created for a multi-player-game.');

         $arr_logid['multi_player_invite_unknown_user'] = 1;
         $arr['multi_player_invite_unknown_user'] =
            T_('This shouldn\'t happen. Found unknown invited user for multi-player-game message. Please contact an admin.');

         $arr['guest_no_invite'] =
            T_('Sorry, the guest-user can not be invited.');

         $arr['no_uid'] =
            T_("Sorry, I need to know for which user to show the data.");

         $arr['not_allowed_for_guest'] =
            T_("Sorry, this is not allowed for guests, please first register a personal account");

         $arr_logid['ip_blocked_guest_login'] = 1;
         $arr['ip_blocked_guest_login'] =
            T_('Sorry, you are not allowed to login as guest to this server. The IP address you are using has been blocked by the admins.') .
               "<br><br>\n" .
               sprintf( T_('If you think the IP block is not intended for you, please register your account with the <a href="%s">alternative registration page</a>.'),
                  HOSTBASE."register.php" );

         $arr_logid['ip_blocked_register'] = 1;
         $arr['ip_blocked_register'] =
            T_('Sorry, you are not allowed to register a new account. The IP address you are using has been blocked by the admins.');

         $arr['not_logged_in'] =
            T_("Sorry, you have to be logged in to do that.")
            . "\n<br><br>\n"
            . "<table id=\"ErrorNote\"><tr><td>\n" // format-left
            . T_('The reasons for this problem could be any of the following:')
            . "\n<ul>"
            . "\n<li>" . sprintf( T_("You haven't got an <a href=\"%1\$sregister.php\">account</a>, or haven't <a href=\"%1\$sindex.php\">logged in</a> yet."), HOSTBASE )
            . "\n<li>" . T_('Your cookies have expired. This happens once a month.')
            . "\n<li>" . T_('You haven\'t enabled cookies in your browser.')
            . "\n</ul>\n"
            . "</td></tr></table>\n";
         $arr['login_if_not_logged_in'] = $arr['not_logged_in'];

         $arr['login_denied'] = ''; // splitted in 2 mapped texts in error.php
         $arr['login_denied:blocked_with_reason'] =
            T_('Sorry, you are not allowed to login to this server. Your account has been blocked by admins out of the following reason:');
         $arr['login_denied:blocked'] =
            T_('Sorry, you are not allowed to login to this server. Your account has been blocked by admins.');

         $arr['edit_bio_denied'] =
            T_('Sorry, you are not allowed to edit your bio. This feature has been blocked by admins.');

         $arr['cookies_disabled'] =
            T_("Sorry, you haven't enabled cookies in your browser.");

         $arr_logid['feature_disabled'] = 1;
         $arr['feature_disabled'] =
            T_("Sorry, this feature has been disabled on this server.");

         $arr_logid['upload_miss_temp_folder'] = 1;
         $arr['upload_miss_temp_folder'] = // Introduced in PHP 4.3.10 and PHP 5.0.3
            T_("Sorry, missing a temporary folder to upload files. Please contact the administrators.");

         $arr['upload_failed'] =
            T_("Sorry, the file upload failed.");

         $arr['not_game_player'] =
            T_("Sorry, you are not a player in this game.");

         $arr['not_your_turn'] =
            T_("Sorry, it's not your turn.");

         $arr['already_played'] =
            T_("Sorry, this turn has already been played.");

         $arr_logid['page_not_found'] = 1;
         $arr['page_not_found'] = // see error.php
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

         $arr['reply_invalid'] =
            T_("Sorry, replying to that message is not possible.");

         $arr['rank_not_rating'] =
            T_("Sorry, I've problem with the rating, did you forget to specify 'kyu' or 'dan'?");

         $arr['rating_not_rank'] =
            T_("Sorry, I've problem with the rating, you shouldn't use 'kyu' or 'dan' for this ratingtype");

         $arr['invalid_rating'] =
            T_("The specified rating is invalid.");

         $arr['registration_policy_not_checked'] =
            T_("Please read the Rules of Conduct page and check the box if you accept it.");

         $arr['suicide'] =
            T_("Sorry, this stone would have killed itself, but suicide is not allowed under this ruleset.");

         $arr['invalid_coord'] =
            T_("The specified move-coordinates are invalid.");

         $arr['unknown_game'] =
            T_("Sorry, I can't find that game.");

         $arr['wrong_dispute_game'] =
            T_("Sorry, this game is not matching the initial game players.");

         $arr['bad_shape_id'] =
            T_("Sorry, the shape-id is invalid.");

         $arr['unknown_shape'] =
            T_("Sorry, I can't find that shape.");

         $arr['unknown_forum'] =
            T_("Sorry, I couldn't find that forum you wanted to show.");

         $arr['forbidden_forum'] =
            T_("Sorry, you are not allowed to view or post in this forum.");

         $arr['read_only_forum'] =
            T_("Sorry, you are not allowed to post in this forum.");

         $arr['read_only_thread'] =
            T_("Sorry, you are not allowed to post in this thread.");

         $arr['forbidden_post'] =
            T_("Sorry, you are not allowed to view this post.");

         $arr['unknown_post'] =
            T_("Sorry, I couldn't find the post you wanted to show.");

         $arr_logid['unknown_parent_post'] = 1;
         $arr['unknown_parent_post'] =
            T_("Hmm, this message seems to be a reply to a non-existing post.");

         $arr_logid['unknown_message'] = 1;
         $arr['unknown_message'] =
            T_("Sorry, I couldn't find the message you wanted to show.");

         $arr['unknown_user'] =
            T_("Sorry, I couldn't find this user.");

         $arr_logid['user_mismatch'] = 1;
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

         $arr['waitingroom_join_error'] =
            T_("Sorry, you couldn't join this waiting room game because of an internal error. Please contact an admin.");

         $arr['waitingroom_join_too_late'] =
            T_("Sorry, you are too late to join this waiting room game.");

         $arr['wrong_number_of_handicap_stone'] =
            T_("Wrong number of handicap stones");

         $arr['wrong_password'] =
            T_("Sorry, you didn't write your current password correctly.")
            // the following text has been added at 20-Sep-2011 to give more info how to get a new password.
            // (happened a lot, that people didn't have or give an email, so there was no way to contact them).
            . "<br><br>\n"
            . "<table id=\"ErrorNote\"><tr><td>\n" // format-left
            . T_('If you have forgotten your password we can email a new one.')
            . "<br>\n"
            . T_('The new password will be randomly generated, but you can of course change it later from the edit profile page.')
            . "<br>\n"
            . T_('Until you change it, both new and old passwords will be operational.')
            . "<br><br>\n"
            . sprintf( T_('In case you HAVE an email set in your account, you can generate a new one sent to your email with: %s'),
                       sprintf('<a href="%s">%s</a>', $base_path.'forgot.php', T_('Forgot password?')) )
            . "<br><br><br>\n"
            . T_('In case you have no email set in your account:')
            . "<br>\n"
            . T_("Please log in as guest-user and use the Support-forum to get help (provide your login-user-id and your email).")
            . "<br>\n"
            . T_('for more details on that see FAQ:')
            . sprintf(' <a href="%s">%s</a>', $base_path.'faq.php?read=t&cat=12#Entry234', T_('I forgot my password. What can I do?')
            . "\n</td></tr></table>\n"
            );

         $arr['wrong_rank_type'] =
            T_("Unknown rank type");

         $arr['wrong_userid'] =
            T_("Sorry, I don't know anyone with that userid.");

         $arr['rating_out_of_range'] =
            T_("Sorry, the initial rating must be between 30 kyu and 6 dan.");

         $arr['value_not_numeric'] = // NOTE: unused, but could be useful, so not deleted
            T_("Sorry, you wrote a non-numeric value on a numeric field.");

         $arr['not_translator'] =
            T_("Sorry, only translators are allowed to translate.") . '<p></p>' .
            T_("If you want to help translating dragon, please post a message to the 'translation' forum.");

         $arr['not_correct_transl_language'] =
            T_("Sorry, you are not allowed to translate the specified language.");

         $arr['translation_bad_language_or_group'] =
            T_("Sorry, I couldn't find the language or group you want to translate. Please contact the support.");

         $arr_logid['couldnt_update_translation'] = 1;
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

         $arr_logid['bad_tournament'] = 1;
         $arr['bad_tournament'] =
            T_("Sorry, there's something wrong with this tournament. Please contact a tournament admin.");

         $arr['tournament_create_denied'] =
            T_('Sorry, you are not allowed to create a new tournament. This feature has been blocked by admins.');

         $arr['tournament_create_only_by_admin'] =
            T_('Sorry, only a tournament admin can create a new tournament.');

         $arr['tournament_register_denied'] =
            T_('Sorry, you are not allowed to register for a tournament. This feature has been blocked by admins.');

         $arr['tournament_create_not_allowed'] =
            T_("Sorry, you are not allowed to create this type of tournament.");

         $arr['tournament_edit_not_allowed'] =
            T_("Sorry, you are not allowed to edit this tournament.");

         $arr['tournament_edit_rounds_not_allowed'] =
            T_("Sorry, you are not allowed to edit rounds for this tournament.");

         $arr['tournament_create_error'] =
            T_("Sorry, some errors occured on tournament creation.");

         $arr['unknown_tournament_news'] =
            T_("Sorry, I couldn't find the given tournament news or you are not allowed to view this tournament news entry.");

         $arr_logid['bad_tournament_news'] = 1;
         $arr['bad_tournament_news'] =
            T_("Sorry, there's something wrong with the tournament-news. Please contact a tournament admin.");

         $arr['tournament_director_edit_not_allowed'] =
            T_("Sorry, you are not allowed to add, edit or delete a tournament director for this tournament.");

         $arr['tournament_director_new_del_not_allowed'] =
            T_("Sorry, you are not allowed to add or delete a tournament director for this tournament.");

         $arr['tournament_director_min1'] =
            T_("Sorry, there must be at least one tournament director.");

         $arr['tournament_register_not_allowed'] = //FIXME unused?
            T_("Sorry, you are not allowed to register for this tournament.");

         $arr['tournament_register_edit_not_allowed'] =
            T_("Sorry, you are not allowed to edit this tournament registration.");

         $arr['tournament_participant_invalid_status'] =
            T_("Sorry, action on tournament participant is not allowed for registration status.");

         $arr['tournament_participant_unknown'] =
            T_("Sorry, user is unknown for current tournament.");

         $arr['tournament_wrong_status'] =
            T_('Sorry, tournament is on wrong status to allow this action for tournament.');

         $arr_logid['folder_not_found'] = 1;
         $arr['folder_not_found'] =
            T_("Sorry, couldn't find the specified message folder.");

         $arr['folder_forbidden'] =
            T_("Sorry, this folder can not be used for this operation.");

         $arr_logid['invalid_filter'] = 1;
         $arr['invalid_filter'] =
            T_("Sorry, there's a configuration problem with a search-filter.");

         $arr_logid['invalid_args'] = 1;
         $arr['invalid_args'] =
            T_("Sorry, invalid arguments used.");

         $arr['miss_args'] =
            T_("Sorry, an argument is missing.");

         $arr_logid['invalid_command'] = 1;
         $arr['invalid_command'] =
            T_("Sorry, invalid quick-suite command used.");

         $arr_logid['invalid_method'] = 1;
         $arr['invalid_method'] =
            T_("Sorry, there's a problem with a class-method.");

         $arr['invalid_snapshot_char'] =
            T_("Found an invalid character in game-snapshot.");

         $arr['invalid_snapshot'] =
            T_("Shape snapshot has bad format.");

         $arr['mismatch_snapshot'] =
            T_("Mismatching shape or snapshot found.");

         $arr['unknown_entry'] =
            T_("Sorry, I can't find that entry.");

         $arr['miss_snapshot_size'] =
            T_("Missing size for shape-game snapshot.");

         $arr['entity_init_error'] =
            T_("Sorry, Entity-class initialization is wrong.");

         $arr_logid['constraint_votes_delete_feature'] = 1;
         $arr['constraint_votes_delete_feature'] =
            T_("Sorry, feature can't be deleted because of existing votes for feature.");

         $arr['feature_edit_not_allowed'] =
            T_("Sorry, you are not allowed to add, edit or delete features.");

         $arr['feature_edit_bad_status'] =
            T_("Sorry, you are not allowed to edit feature on that status.");

         $arr['bulletin_edit_not_allowed'] =
            T_("Sorry, you are not allowed to edit this bulletin.");

         $arr['unknown_bulletin'] =
            T_("Sorry, I can't find that bulletin.");

         $arr['no_view_bulletin'] =
            T_("Sorry, you are not allowed to view that bulletin.");

         $arr['unknown_survey'] =
            T_("Sorry, I can't find that survey.");

         $arr['survey_edit_not_allowed'] =
            T_("Sorry, you are not allowed to edit this survey on current status.");

         $arr_logid['miss_user_quota'] = 1;
         $arr['miss_user_quota'] =
            T_("Sorry, something is wrong with your user data. Please contact an administrator to fix this.");

         $arr_logid['invalid_profile'] = 1;
         $arr['invalid_profile'] =
            T_("Sorry, this profile is not existing or is not suitable for this operation.");

         self::$ARR_ERRORS['TEXT'] = $arr;
         self::$ARR_ERRORS['LOG_ID'] = $arr_logid;
         self::$ARR_ERRORS['SENSITIVE'] = $arr_secret;
      }
   }//init

} // end of 'ErrorCode'

?>
