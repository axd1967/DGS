<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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


$TranslateGroups[] = "Error";

require_once( "include/std_functions.php" );


{
   connect2mysql(true);

   $BlockReason = '';
   $userid = '???';
   if( $dbcnx )
   {
      $tmp= $TheErrors->set_mode(ERROR_MODE_COLLECT);
      //may call error() again:
      $logged_in = who_is_logged( $player_row);
      $TheErrors->set_mode($tmp);
      if( !is_null($player_row) )
      {
         $userid = @$player_row['Handle'];
         $BlockReason = @$player_row['BlockReason'];
      }
   }
   else
   {
      $logged_in = false;
      $player_row = NULL;
   }

   $err = get_request_arg('err');

/*
Within the .htaccess file of the server root:
(where /DragonGoServer/ is SUB_PATH)
# 401: Authorization required (must be local URL)
ErrorDocument 401 /DragonGoServer/error.php?err=page_not_found&redir=htaccess
# 403: Access denied
ErrorDocument 403 /DragonGoServer/error.php?err=page_not_found&redir=htaccess
# 404: Not Found
ErrorDocument 404 /DragonGoServer/error.php?err=page_not_found&redir=htaccess
# 500: Server Error.
*/

   $redir = get_request_arg('redir');
   if( $dbcnx && $redir /* && @$_SERVER['REDIRECT_STATUS'] != 401 */ )
   { //need to be recorded
      //temporary hide an unsolved DGS problem, waiting better:
      if(!( @$_SERVER['REDIRECT_STATUS'] == 404
            && in_array(
               substr( @$_SERVER['REDIRECT_URL'], strlen(SUB_PATH)),
               array('phorum/post.php','favicon.ico'))
         ))
      {
         if( isset($player_row) && isset($player_row['Handle']) )
            $handle = $player_row['Handle'];
         else
            $handle = safe_getcookie('handle');
         $debugmsg= $redir  # htaccess
            .' 1='.@$_SERVER['REDIRECT_STATUS']  # 404
            .' 2='.@$_SERVER['REDIRECT_URL']  # /phorum/post.php (from domain root)
            .' 3='.@$_SERVER['REDIRECT_ERROR_NOTES']  # /
            .' 4='.@$_SERVER['HTTP_REFERER']
            //.' 5='.getcwd()  # the root folder
            //.' 6='.@$_SERVER['REQUEST_URI']  #
            ;
         list( $err, $uri)= err_log( $handle, $err, $debugmsg);
         db_close();
         //must restart from the root else $base_path is not properly set
         jump_to($uri,0);
      }
   }
   db_close();

   start_page("Error", true, $logged_in, $player_row );
   echo '&nbsp;<br>';

   //TODO: also output label-string with error-msg !? at least for admins ...
   switch( (string)$err )
   {
      case("early_pass"):
      {
         echo T_("Sorry, you may not pass before all handicap stones are placed.");
      }
      break;

      case("game_finished"):
      {
         echo T_("Sorry, the game has already finished.");
      }
      break;

      case("game_not_started"):
      {
         echo T_("Sorry, the game hasn't started yet.");
      }
      break;

      case("guest_may_not_receive_messages"):
      {
         echo T_("Impossible, guest may not receive messages");
      }
      break;

      case("illegal_position"):
      {
         echo T_("This move leads to an illegal board position.");
      }
      break;

      case("invalid_action"):
      {
         echo T_("This type of action is either unknown or can't be used in this state of the game.");
      }
      break;

      case("invite_self"):
      {
         echo T_("Sorry, you can't invite yourself.");
      }
      break;

      case("invited_to_unknown_game"):
      {
         echo T_("Sorry, can't find the game you are invited to. Already declined?");
      }
      break;

      case("game_already_accepted"):
      {
         echo T_("Sorry, can't find the game you are invited to. Already accepted?");
      }
      break;

      case("game_delete_invitation"):
      {
         echo T_("Delete game failed. This is problably not a problem.");
      }
      break;

      case("ko"):
      {
         echo T_("Sorry, you may not retake a stone which has just captured a stone, " .
                 "since it would repeat a previous board position. Look for 'ko' in the rules.");
      }
      break;

      case("komi_range"):
      {
         echo T_("The komi is out of range, please choose a more reasonable value.");
      }
      break;

      case('time_limit_too_small'):
      {
         echo T_("The time limit is too small, please choose at least one hour.");
      }
      break;

      case("move_problem"):
      {
         echo T_("An error occurred for this move. Usually it works if you try again, otherwise please contact the support.");
      }
      break;

      case("mysql_connect_failed"):
      {
         echo T_("Connection to database failed. Please wait a few minutes and test again.");
      }
      break;

      case("mysql_insert_message"):
      {
         echo T_("Sorry, the additon of the message to the database seems to have failed.");
      }
      break;

      case("mysql_insert_game"):
      {
         echo T_("Sorry, the additon of the game to the database seems to have failed.");
      }
      break;

      case("mysql_insert_move"):
      case("mysql_update_game"):
      {
         echo T_("The insertion of the move into the database seems to have failed. " .
            "This may or may not be a problem, please return to the game to see " .
            "if the move has been registered.");
      }
      break;

      case("mysql_insert_player"):
      {
         echo T_("The insertion of your data into the database seems to have failed. " .
                 "If you can't log in, please try once more and, if this fails, contact the support.");
      }
      break;

      case("mysql_query_failed"):
      {
         echo T_("Database query failed. Please wait a few minutes and try again. ");
      }
      break;

      case("mysql_select_db_failed"):
      {
         echo T_("Couldn't select the database. Please wait a few minutes and try again. ");
      }
      break;

      case("mysql_start_game"):
      {
         echo T_("Sorry, couldn't start the game. Please wait a few minutes and try again.");
      }
      break;

      case("mysql_update_player"):
      {
         echo T_("Sorry, couldn't update player data. Please wait a few minutes and try again.");
      }
      break;

      case("name_not_given"):
      {
         echo T_("Sorry, you have to supply a name.");
      }
      break;

      case("newpassword_already_sent"):
      {
         echo T_("A new password has already been sent to this user, please use that password instead of sending another one.");
      }
      break;

      case("no_action"):
      {
         echo T_("Nothing to be done?");
      }
      break;

      case('no_email'):
      {
         echo T_("Sorry, no email has been given, so I can't send you the password. Please log in as guest and use the support forum to get help.");
      }
      break;

      case('bad_mail_address'):
      {
         //an email address validity function should never be treated as definitive
         echo T_("Sorry, the email given does not seem to be a valid address. Please, verify your spelling or try another one.");
      }
      break;

      case("no_game_nr"):
      {
         echo T_("Sorry, I need a game number to know what game to show.");
      }
      break;

      case("no_initial_rating"):
      {
         echo T_("Sorry, you and your opponent need to set an initial rating, otherwise I can't find a suitable handicap");
      }
      break;

      case("no_uid"):
      {
         echo T_("Sorry, I need to known for which user to show the data.");
      }
      break;

      case("not_allowed_for_guest"):
      {
         echo T_("Sorry, this is not allowed for guests, please first register a personal account");
      }
      break;

      case("not_empty"):
      {
         echo T_("Sorry, you may only place stones on empty points.");
      }
      break;

      case('ip_blocked_guest_login'):
      {
        echo T_('Sorry, you are not allowed to login as guest to this server. The IP address you are using has been blocked by the admins.'),
            "<br><br>\n",
            sprintf( T_('If you think the IP block is not intended for you, please register your account with our <a href="%s">alternative registration page</a>.'),
               "{$HOSTBASE}register.php" );
      }
      break;

      case('ip_blocked_register'):
      {
        echo T_('Sorry, you are not allowed to register a new account. The IP address you are using has been blocked by the admins.'),
            "<br><br>\n",
            '<form action="do_registration_blocked.php" method="post">',
            T_('To register a new account despite the IP-block, a user account can be created by an admin.'),
            "<br>\n",
            T_('Please enter a user-id for the DragonGoServer and your email to send you the login details.'),
            "<br><br>\n",
            T_('An IP-block is only used to keep misbehaving users away.'),
            "<br>\n",
            T_('So please add why you want to bypass the IP-block in the Comments-field.'),
            "<br>\n",
            T_('There you can also add questions you might have.'),
            "<br>\n",
            T_('All fields must be provided in order to fulfill the request.'),
            "<br><br>\n",
            '<table>',
              '<tr>',
                '<td>', T_('Userid'), ':</td>',
                '<td><input type="text" name="userid" value="" size="16"></td>',
              '</tr>', "\n",
              '<tr>',
                '<td>', T_('Email'), ':</td>',
                '<td><input type="text" name="email" value="" size="50"></td>',
              '</tr>', "\n",
              '<tr>',
                '<td>&nbsp;</td>',
                '<td><input type="checkbox" name="policy" value="1">&nbsp;'
                     . sprintf( T_('I have read and accepted the DGS <a href="%s" target="dgsTOS">Rules of Conduct</a>.'), "{$HOSTBASE}policy.php" ) . '</td>',
              '</tr>', "\n",
              '<tr><td colspan="2">&nbsp;</td></tr>', "\n",
              '<tr>',
                '<td>', T_('Comments'), ':</td>',
                '<td><textarea name="comment" cols="60" rows="10"></textarea></td>',
              '</tr>', "\n",
            '</table>', "\n",
            sprintf( '<input type="submit" name="send_request" value="%s">', T_('Send registration request') ),
            '</form>',
            "\n";
      }
      break;

      case("not_logged_in"):
      {
        printf( T_("Sorry, you have to be logged in to do that.\n" .
                   "<p></p>\n" .
                   "The reasons for this problem could be any of the following:\n" .
                   "<ul>\n" .
                   "<li> You haven't got an <a href=\"%1\$sregister.php\">account</a>, " .
                   "or haven't <a href=\"%1\$sindex.php\">logged in</a> yet.\n" .
                   "<li> Your cookies have expired. This happens once a month.\n" .
                   "<li> You haven't enabled cookies in your browser.\n" .
                   "</ul>"),
                HOSTBASE );
      }
      break;

      case('login_denied'):
      {
         if( (string)$userid != '' )
            admin_log( 0, $userid, 'login_denied');

         if( (string)$BlockReason != '' )
         {
            echo T_('Sorry, you are not allowed to login to this server. Your account has been blocked by admins out of the following reason:'),
               "<br><br>\n",
               '<table><tr><td>',
                  make_html_safe($BlockReason, 'msg'),
               "</td></tr></table><br>\n";
         }
         else
            echo T_('Sorry, you are not allowed to login to this server. Your account has been blocked by admins.');
      }
      break;

      case('edit_bio_denied'):
      {
         if( (string)$userid != '' )
            admin_log( 0, $userid, 'edit_bio_denied');

         echo T_('Sorry, you are not allowed to edit your bio. This feature has been blocked by admins.');
      }
      break;

      case('cookies_disabled'):
      {
         echo T_("Sorry, you haven't enabled cookies in your browser.");
      }
      break;

      case("not_your_turn"):
      {
         echo T_("Sorry, it's not your turn.");
      }
      break;

      case("already_played"):
      {
         echo T_("Sorry, this turn has already been played.");
      }
      break;

      case("page_not_found"):
      {
         echo T_('Page not found. Please contact the server administrators and inform them of the time the error occurred, and anything you might have done that may have caused the error.');
         //echo '<br>('.@$_SERVER['REDIRECT_STATUS'].': '.@$_SERVER['REDIRECT_URL'].' / '.getcwd().')';
      }
      break;

      case("password_illegal_chars"):
      {
         echo T_("The password contained illegal characters, only the characters a-z, A-Z, 0-9 and -_+.,:;?!%* are allowed.");
      }
      break;

      case("userid_illegal_chars"):
      {
         echo T_("The userid contained illegal characters, only the characters a-z, A-Z, 0-9 and -_+ are allowed, and the first one must be a-z, A-Z.");
      }
      break;

      case("password_mismatch"):
      {
         echo T_("The confirmed password didn't match the password, please go back and retry.");
      }
      break;

      case("password_too_short"):
      {
         echo T_("Sorry, the password must be at least six characters long.");
      }
      break;

      case("receiver_not_found"):
      {
         echo T_("Sorry, couldn't find the receiver of your message. Make sure to use " .
                 "the userid, not the full name.");
      }
      break;

      case("rank_not_rating"):
      {
         echo T_("Sorry, I've problem with the rating, did you forget to specify 'kyu' or 'dan'?");
      }
      break;

      case("rating_not_rank"):
      {
         echo T_("Sorry, I've problem with the rating, you shouldn't use 'kyu' or 'dan' for this ratingtype");
      }
      break;

      case("registration_policy_not_checked"):
      {
         echo T_("Please read the Rules of Conduct page and check the box if you accept it.");
      }
      break;

      case("suicide"):
      {
         echo T_("Sorry, this stone would have killed itself, but suicide is not allowed under this ruleset.");
      }
      break;

      case("unknown_game"):
      {
         echo T_("Sorry, I can't find that game.");
      }
      break;


      case("unknown_forum"):
      {
         echo T_("Sorry, I couldn't find that forum you wanted to show.");
      }
      break;

      case('forbidden_forum'):
      {
         echo T_("Sorry, you are not allowed to view or post in this forum.");
      }
      break;


      case("unknown_post"):
      {
         echo T_("Sorry, I couldn't find the post you wanted to show.");
      }
      break;

      case("unknown_parent_post"):
      {
         echo T_("Hmm, this message seems to be a reply to a non-existing post.");
      }
      break;


      case("unknown_message"):
      {
         echo T_("Sorry, I couldn't find the message you wanted to show.");
      }
      break;


      case("unknown_user"):
      {
         echo T_("Sorry, I couldn't find this user.");
      }
      break;

      case('user_mismatch'):
      {
         echo T_("Sorry, the logged user seems to have changed during the operation.");
      }
      break;

      case("userid_in_use"):
      {
         echo T_("Sorry, this userid is already used, please try to find a unique userid.");
      }
      break;

      case("userid_too_short"):
      {
         echo T_("Sorry, userid must be at least 3 characters long.");
      }
      break;

      case("invalid_user"): // Players.ID
      {
         echo T_("Sorry, invalid player id used.");
      }
      break;

      case("invalid_opponent"): // Players.ID
      {
         echo T_("Sorry, invalid player id used as opponent.");
      }
      break;

      case("value_out_of_range"):
      {
         echo T_("Couldn't extrapolate value in function interpolate");
      }
      break;

      case("waitingroom_delete_not_own"):
      {
         echo T_("Sorry, you may only delete your own game.");
      }
      break;

      case("waitingroom_game_not_found"):
      {
         echo T_("Sorry, couldn't find this waiting room game. Probably someone has already joined it.");
      }
      break;

      case("waitingroom_own_game"):
      {
         echo T_("Sorry, you can't join your own game.");
      }
      break;

      case("waitingroom_not_in_rating_range"):
      {
         echo T_("Sorry, you are not in the specified rating range.");
      }
      break;

      case("wrong_number_of_handicap_stone"):
      {
         echo T_("Wrong number of handicap stones");
      }
      break;

      case("wrong_password"):
      {
         echo T_("Sorry, you didn't write your current password correctly.");
      }
      break;

      case("wrong_rank_type"):
      {
         echo T_("Unknown rank type");
      }
      break;

      case("wrong_userid"):
      {
         echo T_("Sorry, I don't know anyone with that userid.");
      }
      break;

      case("rating_out_of_range"):
      {
         echo T_("Sorry, the initial rating must be between 30 kyu and 6 dan.");
      }
      break;

      case("value_not_numeric"):
      {
        echo T_("Sorry, you wrote a non-numeric value on a numeric field.");
      }
      break;

      case("not_translator"):
      {
         echo T_("Sorry, only translators are allowed to translate.") . '<p></p>' .
            T_("If you want to help translating dragon, please post a message to the 'translation' forum.");
      }
      break;

      case("not_correct_transl_language"):
      {
        echo T_("Sorry, you are not allowed to translate the specified language.");
      }
      break;

      case("translation_bad_language_or_group"):
      {
        echo T_("Sorry, I couldn't find the language or group you want to translate. Please contact the support.");
      }
      break;

      case("couldnt_update_translation"):
      {
        echo T_("Sorry, something went wrong when trying to insert the new translations into the database.");
      }
      break;

      case("couldnt_make_backup"):
      {
        echo T_("Sorry, I was unable to make a backup of the old translation, aborting. Please contact the support.");
      }
      break;

      case("couldnt_open_transl_file"):
      {
        echo T_("Sorry, I was unable to open a file for writing. Please contact the support.");
      }
      break;

/*
      case('admin_already_translated'):
      {
         echo T_//("Sorry, this entry is already translated, so I cannot make it untranslatable.");
      }
      break;
*/

      case("adminlevel_too_low"):
      {
        echo T_("Sorry, this page is solely for users with administrative tasks.");
      }
      break;

      case("admin_no_longer_admin_admin"):
      {
         echo T_("Hmm, you seem to try to revoke your abillity to edit the admin staff.");
      }
      break;

      case("new_admin_already_admin"):
      {
         echo T_("This user is already an admin.");
      }
      break;

      case("admin_no_such_entry"):
      {
        echo T_("Sorry, couldn't find that entry.");
      }
      break;

      case("no_specified_user"):
      {
        echo T_("Sorry, you must specify a user.");
      }
      break;

      case("unknown_organizer"):
      {
        echo T_("Sorry, one or more users in the organizer list couldn't be found.");
      }
      break;

      case("mysql_insert_tournament"):
      {
        echo T_("Sorry, the tournament creation in the mysql database failed.");
      }
      break;

      case("strange_tournament_id"):
      {
        echo T_("Sorry, a correct tournament id is required.");
      }
      break;

      case("no_such_tournament"):
      {
        echo T_("Sorry, I couldn't find the given tournament.");
      }
      break;

      case("remove_form_tournament_failed"):
      {
        echo T_("Sorry, something went wrong when removing you from the tournament.");
      }
      break;

      case("add_form_tournament_failed"):
      {
        echo T_("Sorry, something went wrong when adding you to the tournament.");
      }
      break;

      case("could_not_start_tournament"):
      {
        echo T_("Sorry, something went wrong when trying start a tournament.");
      }
      break;

      case("folder_not_found"):
      {
         echo T_("Sorry, couldn't find the specified message folder.");
      }
      break;

      case("not_a_player"):
      {
         echo T_("Sorry, you're not a player in this game.");
      }
      break;

      case("invalid_filter"):
      {
         echo T_("Sorry, there's a configuration problem with a search-filter.");
      }
      break;

      case("invalid_args"):
      {
         echo T_("Sorry, invalid arguments used.");
      }
      break;

      //case('assert'):
      //case('internal_error'):
      default:
      {
         echo T_("Unknown problem. This shouldn't happen. Please send the url of this page to the support, so that this doesn't happen again.")." ($err)";
      }
      break;
   }

   $mysqlerror = get_request_arg('mysqlerror');
   if( $mysqlerror )
   {
      $mysqlerror = str_replace(
         array( MYSQLHOST, DB_NAME, MYSQLUSER, MYSQLPASSWORD),
         array( '[*h*]', '[*d*]', '[*u*]', '[*p*]'),
         $mysqlerror);
      echo "<p>MySQL error: ".basic_safe($mysqlerror)."</p>";
   }

   echo '<p></p>';
   end_page();
}
?>
