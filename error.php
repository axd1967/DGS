<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

$TranslateGroups[] = "Error";

require_once( "include/std_functions.php" );


{
   connect2mysql(true);

   $logged_in = who_is_logged( $player_row);

   start_page("Error", true, $logged_in, $player_row );

   $err = @$_GET['err'];
   switch( $err )
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
         echo T_("Error, guest may not recieve messages");
      }
      break;

      case("illegal_position"):
      {
         echo T_("Move outside board?");
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

      case("mysql_delete_game_invitation"):
      {
         echo T_("Delete game failed. This is problably not a problem.");
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

      case("not_logged_in"):
      {
        printf( T_("Sorry, you have to be logged in to do that.\n" .
                   "<p>\n" .
                   "The reasons for this problem could be any of the following:\n" .
                   "<ul>\n" .
                   "<li> You haven't got an <a href=\"%1\$s/register.php\">account</a>, " .
                   "or haven't <a href=\"%1\$s/index.php\">logged in</a> yet.\n" .
                   "<li> Your cookies have expired. This happens once a month.\n" .
                   "<li> You haven't enabled cookies in your browser.\n" .
                   "</ul>"),
                $HOSTBASE );
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
         //echo '<br>('.@$_SERVER['REDIRECT_STATUS'].': '.@$_SERVER['REDIRECT_URL'].')';
      }
      break;

      case("password_illegal_chars"):
      {
         echo T_("The password contained illegal characters, only the characters a-z, A-Z, 0-9 and -_+.,:;?!%* are allowed.");
      }
      break;

      case("userid_illegal_chars"):
      {
         echo T_("The userid contained illegal characters, only the characters a-z, A-Z, 0-9 and -_+ are allowed.");
      }
      break;

      case("password_mismatch"):
      {
         echo T_("The confirmed password didn't match the password, please go back and retry.");
      }
      break;

      case("password_too_short"):
      {
         echo T_("Sorry, the password must be at least six letters.");
      }
      break;

      case("receiver_not_found"):
      {
         echo T_("Sorry, couldn't find the reciever of your message. Make sure to use " .
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
         echo T_("Sorry, I've problem with the rating, you shouldn't use 'kyu' or 'dan' " .
                 "for this ratingtype");
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

      case("userid_in_use"):
      {
         echo T_("Sorry, this userid is already used, please try to find a unique userid.");
      }
      break;

      case("userid_too_short"):
      {
         echo T_("Sorry, userid must be at least 3 letters long.");
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
         echo T_("Sorry, only translators are allowed to translate.") . '<p>' .
            T_("If you want to help translating dragon, please post a message to the 'translation' forum.");
      }
      break;

      case("not_correct_transl_language"):
      {
        echo T_("Sorry, you are not allowed to translate the specified language.");
      }
      break;

      case("no_such_translation_language"):
      {
        echo T_("Sorry, I couldn't find the language you want to translate. Please contact the support.");
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

      case('admin_already_translated'):
      {
         echo T_("Sorry, this entry is already translated, so I cannot make untranslatable.");
      }
      break;

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

      case("translator_admin_add_lang_missing_field"):
      {
        echo T_("Sorry, there was a missing or incorrect field when adding a language.");
      }
      break;

      case("translator_admin_add_lang_exists"):
      {
        echo T_("Sorry, the language you tried to add already exists.");
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

      case("no_lang_selected"):
      {
        echo T_("Sorry, you must specify a language.");
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

      default:
      {
         echo T_("Unknown problem. This shouldn't happen. Please send the url of this page to the support, so that this doesn't happen again.")." ($err)";
      }
      break;
   }

   $mysqlerror = get_request_arg('mysqlerror'); //@$_GET['mysqlerror'];
   if( $mysqlerror )
      echo("<p>Mysql error: " . $mysqlerror );

   end_page();
}
?>
