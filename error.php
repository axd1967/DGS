<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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

require( "include/std_functions.php" );


{
   $player_row = 0;

   start_page("Error", true, false, $player_row );

   switch( $err )
   {
      case("early pass"):
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

      case("guest_may_not_recieve_messages"):
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
         echo T_("This type action is either unknown or can't be use in this state of the game.");
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
         echo T_("The komi is out of range, please choose a move reasonable value.");
      }
      break;

      case("move_problem"):
      {
         echo T_("An error occurred for this. Usually it works if you try again, otherwise please contact the support.");
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
                   "<li> You haven't got an <a href=\"%1\$s/register.php\">account</a>, or haven't <a href=\"%1\$s/index.php\">logged in</a> yet.\n" .
                   "<li> Your cookies have expired. This happens once a week.\n" .
                   "<li> You haven't enabled cookies in your browser.\n" .
                   "</ul>\n"),
                $HOSTBASE );
      }
      break;

      case("not_your_turn"):
      {
         echo T_("Sorry, it's not your turn.");
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

      case("reciever_not_found"):
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


      case("Unknown forum"):
      {
         echo T_("Sorry, I couldn't find that forum you wanted to show.");
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
         echo T_("Couldn't extrapolate value in function interpolate()");
      }
      break;

      case("wrong_number_of_handicap_stone"):
      {
         echo T_("Wrong, number of handicap stones");
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

      default:
      {
         echo T_("Unknown problem. This shouldn't happen. Please send the url of this page to the support, so that this doesn't happen again.");
      }
      break;
   }

   if( $mysqlerror )
      echo("<p>Mysql error: " . $mysqlerror );

   end_page();
}
?>
