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

$translation_template_copyright =
'<?php

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
';

$translation_template_top = $translation_template_copyright .
'
/* Automatically generated at %4$s */

add_to_known_languages( "%1$s", "%2$s", %3$s );

class %1$s_Language extends Language
{
  function %1$s_Language()
    {
      $this->translated_strings = array(
';

$translation_template_bottom =
' );
    }
};

?>
';

$translation_groups =
array( 'Admin', 'Common', 'Documentation', 'Edit bio',
       'Edit password', 'Edit profile', 'Error',
       'Game', 'Index', 'Links', 'Messages',
       'Misc', 'Register', 'Status', 'Users' );

$translation_info =
array( /* From std_functions.php */
      "Edit password" =>
      array( 'Groups' => array( 'Edit password' ) ),

      "Old password" =>
      array( 'Groups' => array( 'Edit password' ) ),

      "New password" =>
      array( 'Groups' => array( 'Edit password' ) ),

      "Confirm password" =>
      array( 'Groups' => array( 'Common', 'Edit password', 'Register' ) ),

      "Change password" =>
      array( 'Groups' => array( 'Edit password' ) ),

      "Edit profile" =>
      array( 'Groups' => array( 'Edit profile' ) ),

      "Personal settings" =>
      array( 'Groups' => array( 'Edit profile' ) ),

      "Userid" =>
      array( 'Groups' => array( 'Common', 'Edit profile', 'Register', 'Status' ) ),

      "Full name" =>
      array( 'Groups' => array( 'Common', 'Edit profile', 'Register' ) ),

      "Email" =>
      array( 'Groups' => array( 'Common', 'Edit profile' ) ),

      "Open for matches" =>
      array( 'Groups' => array( 'Edit profile', 'Status' ) ),

      "Rank info" =>
      array( 'Groups' => array( 'Edit profile', 'Status' ) ),

      "Rating" =>
      array( 'Groups' => array( 'Common', 'Edit profile', 'Status' ) ),

      "Off" =>
      array( 'Groups' => array( 'Common', 'Edit profile' ) ),

      "Notify only" =>
      array( 'Groups' => array( 'Edit profile' ) ),

      "Moves and messages" =>
      array( 'Groups' => array( 'Edit profile' ) ),

      "Full board and messages" =>
      array( 'Groups' => array( 'Edit profile' ) ),

      "Email notifications" =>
      array( 'Groups' => array( 'Edit profile' ) ),

      "Language" =>
      array( 'Groups' => array( 'Common', 'Edit profile' ) ),

      "Timezone" =>
      array( 'Groups' => array( 'Common', 'Edit profile' ) ),

      "Nighttime" =>
      array( 'Groups' => array( 'Edit profile' ) ),

      "Board graphics" =>
      array( 'Groups' => array( 'Edit profile' ) ),

      "Stone size" =>
      array( 'Groups' => array( 'Edit profile' ) ),

      "Wood color" =>
      array( 'Groups' => array( 'Edit profile' ) ),

      "Coordinate sides" =>
      array( 'Groups' => array( 'Edit profile' ) ),

      "Game id button" =>
      array( 'Groups' => array( 'Edit profile' ) ),

      "Change profile" =>
      array( 'Groups' => array( 'Edit profile' ) ),

      "Sorry, you may not pass before all handicap stones are placed." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, the game has already finished." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, the game hasn't started yet." =>
      array( 'Groups' => array( 'Error' ) ),

      "Error, guest may not recieve messages" =>
      array( 'Groups' => array( 'Error' ) ),

      "Move outside board?" =>
      array( 'Groups' => array( 'Error' ) ),

      "This type action is either unknown or can't be use in this state of the game." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, you can't invite yourself." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, can't find the game you are invited to. Already declined?" =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, you may not retake a stone which has just captured a stone, " .
      "since it would repeat a previous board position. Look for 'ko' in the rules." =>
      array( 'Groups' => array( 'Error' ) ),

      "The komi is out of range, please choose a move reasonable value." =>
      array( 'Groups' => array( 'Error' ) ),

      "An error occurred for this. Usually it works if you try again, otherwise please contact the support." =>
      array( 'Groups' => array( 'Error' ) ),

      "Connection to database failed. Please wait a few minutes and test again." =>
      array( 'Groups' => array( 'Error' ) ),

      "Delete game failed. This is problably not a problem." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, the additon of the message to the database seems to have failed." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, the additon of the game to the database seems to have failed." =>
      array( 'Groups' => array( 'Error' ) ),

      "The insertion of the move into the database seems to have failed. " .
      "This may or may not be a problem, please return to the game to see " .
      "if the move has been registered." =>
      array( 'Groups' => array( 'Error' ) ),

      "The insertion of your data into the database seems to have failed. " .
      "If you can't log in, please try once more and, if this fails, contact the support." =>
      array( 'Groups' => array( 'Error' ) ),

      "Database query failed. Please wait a few minutes and try again. " =>
      array( 'Groups' => array( 'Error' ) ),

      "Couldn't select the database. Please wait a few minutes and try again. " =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, couldn't start the game. Please wait a few minutes and try again." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, couldn't update player data. Please wait a few minutes and try again." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, you have to supply a name." =>
      array( 'Groups' => array( 'Error' ) ),

      "A new password has already been sent to this user, please use that password instead of sending another one." =>
      array( 'Groups' => array( 'Error' ) ),

      "Nothing to be done?" =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, I need a game number to know what game to show." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, you and your opponent need to set an initial rating, otherwise I can't find a suitable handicap" =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, I need to known for which user to show the data." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, this is not allowed for guests, please first register a personal account" =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, you may only place stones on empty points." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, you have to be logged in to do that.\n" .
      "<p>\n" .
      "The reasons for this problem could be any of the following:\n" .
      "<ul>\n" .
      "<li> You haven't got an <a href=\"%1\$s/register.php\">account</a>, or haven't <a href=\"%1\$s/index.php\">logged in</a> yet.\n" .
      "<li> Your cookies have expired. This happens once a week.\n" .
      "<li> You haven't enabled cookies in your browser.\n" .
      "</ul>" =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, it's not your turn." =>
      array( 'Groups' => array( 'Error' ) ),

      "The confirmed password didn't match the password, please go back and retry." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, the password must be at least six letters." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, couldn't find the reciever of your message. Make sure to use " .
      "the userid, not the full name." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, I've problem with the rating, did you forget to specify 'kyu' or 'dan'?" =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, I've problem with the rating, you shouldn't use 'kyu' or 'dan' " .
      "for this ratingtype" =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, this stone would have killed itself, but suicide is not allowed under this ruleset." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, I can't find that game." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, I couldn't find that forum you wanted to show." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, I couldn't find the message you wanted to show." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, I couldn't find this user." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, this userid is already used, please try to find a unique userid." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, userid must be at least 3 letters long." =>
      array( 'Groups' => array( 'Error' ) ),

      "Couldn't extrapolate value in function interpolate()" =>
      array( 'Groups' => array( 'Error' ) ),

      "Wrong, number of handicap stones" =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, you didn't write your current password correctly." =>
      array( 'Groups' => array( 'Error' ) ),

      "Unknown rank type" =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, I don't know anyone with that userid." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, the initial rating must be between 30 kyu and 6 dan." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, you wrote a non-numeric value on a numeric field." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, only translators are allowed to translate." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, I couldn't find the language you want to translate. Please contact the support." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, I was unable to make a backup of the old translation, aborting. Please contact the support." =>
      array( 'Groups' => array( 'Error' ) ),

      "Sorry, I was unable to open a file for writing. Please contact the support." =>
      array( 'Groups' => array( 'Error' ) ),

      "Unknown problem. This shouldn't happen. Please send the url of this page to the support, so that this doesn't happen again." =>
      array( 'Groups' => array( 'Error' ) ),

      "Resigning" =>
      array( 'Groups' => array( 'Game' ) ),

      "Passing" =>
      array( 'Groups' => array( 'Game' ) ),

      "Deleting game" =>
      array( 'Groups' => array( 'Game' ) ),

      "Please mark dead stones and click 'done' when finished." =>
      array( 'Groups' => array( 'Game' ) ),

      "Score" =>
      array( 'Groups' => array( 'Common', 'Game' ) ),

      "Game" =>
      array( 'Groups' => array( 'Common', 'Game' ) ),

      "Pass" =>
      array( 'Groups' => array( 'Game' ) ),

      "Delete game" =>
      array( 'Groups' => array( 'Game' ) ),

      "Done" =>
      array( 'Groups' => array( 'Game' ) ),

      "Resume playing" =>
      array( 'Groups' => array( 'Game' ) ),

      "Delete game" =>
      array( 'Groups' => array( 'Game' ) ),

      "Download sgf" =>
      array( 'Groups' => array( 'Game' ) ),

      "Resign" =>
      array( 'Groups' => array( 'Game' ) ),

      "Skip to next game" =>
      array( 'Groups' => array( 'Game' ) ),

      "Home" =>
      array( 'Groups' => array( 'Index' ) ),

      "Please login." =>
      array( 'Groups' => array( 'Index' ) ),

      "To look around, use %s." =>
      array( 'Groups' => array( 'Index' ) ),

      "Password" =>
      array( 'Groups' => array( 'Common', 'Index', 'Register' ) ),

      "Log in" =>
      array( 'Groups' => array( 'Index' ) ),

      "Forgot password?" =>
      array( 'Groups' => array( 'Index' ) ),

      "Register new account" =>
      array( 'Groups' => array( 'Index' ) ),

      "Send a message" =>
      array( 'Groups' => array( 'Messages' ) ),

      "Show recieved messages" =>
      array( 'Groups' => array( 'Messages' ) ),

      "Hide deleted" =>
      array( 'Groups' => array( 'Messages' ) ),

      "Show all" =>
      array( 'Groups' => array( 'Messages' ) ),

      "Show sent messages" =>
      array( 'Groups' => array( 'Messages' ) ),

      "Delete all" =>
      array( 'Groups' => array( 'Messages' ) ),

      "Register" =>
      array( 'Groups' => array( 'Register' ) ),

      "Please enter data" =>
      array( 'Groups' => array( 'Register' ) ),

      "Name" =>
      array( 'Groups' => array( 'Status' ) ),

      "Your turn to move in the following games:" =>
      array( 'Groups' => array( 'Status' ) ),

      "No games found" =>
      array( 'Groups' => array( 'Status' ) ),

      "Show/edit userinfo" =>
      array( 'Groups' => array( 'Status' ) ),

      "Show running games" =>
      array( 'Groups' => array( 'Status' ) ),

      "Show finished games" =>
      array( 'Groups' => array( 'Status' ) ),

      "Only active users" =>
      array( 'Groups' => array( 'Users' ) ),

      "Show all users" =>
      array( 'Groups' => array( 'Users' ) ),

      "Submit and go to next game" =>
      array( 'Groups' => array( 'Game' ) ),

      "Submit and go to status" =>
      array( 'Groups' => array( 'Game' ) ),

      "Go back" =>
      array( 'Groups' => array( 'Game' ) ),

      "Logged in as" =>
      array( 'Groups' => array( 'Common' ) ),

      "Not logged in" =>
      array( 'Groups' => array( 'Common' ) ),

      "Status" =>
      array( 'Groups' => array( 'Common' ) ),

      "Messages" =>
      array( 'Groups' => array( 'Common' ) ),

      "Invite" =>
      array( 'Groups' => array( 'Common' ) ),

      "Users" =>
      array( 'Groups' => array( 'Common' ) ),

      "Forums" =>
      array( 'Groups' => array( 'Common' ) ),

      "Translate" =>
      array( 'Groups' => array( 'Common' ) ),

      "Docs" =>
      array( 'Groups' => array( 'Common' ) ),

      "Page created in %0.5f" =>
      array( 'Groups' => array( 'Common' ) ),

      "Logout" =>
      array( 'Groups' => array( 'Common' ) ),

      'Admin' =>
      array( 'Groups' => array( 'Common', 'Admin' ) ), /* include/std_functions.php */

      'Add language for translation' =>
      array( 'Groups' => array( 'Admin' ) ), /* admin.php */

      'Set translator privileges for user' =>
      array( 'Groups' => array( 'Admin' ) ), /* admin.php */

      'Bio updated!' =>
      array( 'Groups' => array( 'Edit bio' ) ), /* change_bio.php */

      'Password changed!' =>
      array( 'Groups' => array( 'Edit password' ) ), /* change_password.php */

      'Profile updated!' =>
      array( 'Groups' => array( 'Edit profile' ) ), /* change_profile.php */

      'Documentation' =>
      array( 'Groups' => array( 'Documentation' ) ), /* docs.php */

      'Introduction to Dragon' =>
      array( 'Groups' => array( 'Documentaion' ) ), /* docs.php */

      'Other:' =>
      array( 'Groups' => array( 'Edit bio' ) ), /* edit_bio.php */

      'Edit biopgraphical info' =>
      array( 'Groups' => array( 'Edit bio' ) ), /* edit_bio.php */

      'Change bio' =>
      array( 'Groups' => array( 'Edit bio' ) ), /* edit_bio.php */

      'Smooth board edge' =>
      array( 'Groups' => array( 'Edit profile' ) ), /* edit_profile.php */

      'Couldn\'t extrapolate value in function interpolate' =>
      array( 'Groups' => array( 'Error' ) ), /* error.php */

      'Sorry, you are not allowed to translate the specified language.' =>
      array( 'Groups' => array( 'Error' ) ), /* error.php */

      'Sorry, something went wrong when trying to insert the new translations into the database.' =>
      array( 'Groups' => array( 'Error' ) ), /* error.php */

      'Sorry, this page is solely for users with administrative tasks.' =>
      array( 'Groups' => array( 'Error' ) ), /* error.php */

      'Sorry, there was a missing or incorrect field when adding a language.' =>
      array( 'Groups' => array( 'Error' ) ), /* error.php */

      'Sorry, the language you tried to add already exists.' =>
      array( 'Groups' => array( 'Error' ) ), /* error.php */

      'Sorry, you must specify a user.' =>
      array( 'Groups' => array( 'Error' ) ), /* error.php */

      'Sorry, you must specify a language.' =>
      array( 'Groups' => array( 'Error' ) ), /* error.php */

      'If you have forgot your password we can email a new one. The new password will be randomly generated, but you can of course change it later from the edit profile page.' =>
      array( 'Groups' => array( 'Misc' ) ), /* forgot.php */

      'Installation instructions' =>
      array( 'Groups' => array( 'Misc' ) ), /* install.php */

      'Introduction' =>
      array( 'Groups' => array( 'Misc' ) ), /* introduction.php */

      'General Public Licence' =>
      array( 'Groups' => array( 'Misc' ) ), /* licence.php */

      'Links' =>
      array( 'Groups' => array( 'Links' ) ), /* links.php */

      'General Info' =>
      array( 'Groups' => array( 'Links' ) ), /* links.php */

      'Jan van der Steens Pages' =>
      array( 'Groups' => array( 'Links' ) ), /* links.php */

      'Rules' =>
      array( 'Groups' => array( 'Links' ) ), /* links.php */

      'An Interactive Introduction' =>
      array( 'Groups' => array( 'Links' ) ), /* links.php */

      'Strategy and terms' =>
      array( 'Groups' => array( 'Links' ) ), /* links.php */

      'An Introduction to Shape' =>
      array( 'Groups' => array( 'Links' ) ), /* links.php */

      'History' =>
      array( 'Groups' => array( 'Links' ) ), /* links.php */

      'A Brief History' =>
      array( 'Groups' => array( 'Links' ) ), /* links.php */

      'Go books, equipment and software' =>
      array( 'Groups' => array( 'Links' ) ), /* links.php */

      'Kiseido' =>
      array( 'Groups' => array( 'Links' ) ), /* links.php */

      'Other go servers' =>
      array( 'Groups' => array( 'Links' ) ), /* links.php */

      'It\'s your turn' =>
      array( 'Groups' => array( 'Links' ) ), /* links.php */

      'Message list' =>
      array( 'Groups' => array( 'Messages' ) ), /* list_messages.php */

      'From' =>
      array( 'Groups' => array( 'Messages' ) ), /* list_messages.php */

      'Subject' =>
      array( 'Groups' => array( 'Messages' ) ), /* list_messages.php */

      'Del' =>
      array( 'Groups' => array( 'Messages' ) ), /* list_messages.php */

      'New' =>
      array( 'Groups' => array( 'Status' ) ), /* status.php */

      'Message sent!' =>
      array( 'Groups' => array( 'Messages' ) ), /* send_message.php */

      'User info' =>
      array( 'Groups' => array( 'Game' ) ), /* show_games.php */

      'Invite this user' =>
      array( 'Groups' => array( 'Game' ) ), /* show_games.php */

      'Site map' =>
      array( 'Groups' => array( 'Misc' ) ), /* site_map.php */

      'Forum' =>
      array( 'Groups' => array( 'Common' ) ), /* site_map.php */

      'Flags' =>
      array( 'Groups' => array( 'Status' ) ), /* status.php */

      'Place your handicap stones, please!' =>
      array( 'Groups' => array( 'Game' ) ), /* include/move.php */

      'Message' =>
      array( 'Groups' => array( 'Game', 'Messages' ) ), /* include/move.php */

      'White' =>
      array( 'Groups' => array( 'Common', 'Game' ) ), /* include/move.php */

      'Main Time' =>
      array( 'Groups' => array( 'Game' ) ), /* include/move.php */

      'Byoyomi' =>
      array( 'Groups' => array( 'Game' ) ), /* include/move.php */

      'Time limit' =>
      array( 'Groups' => array( 'Game' ) ), /* include/move.php */

      'without byoyomi' =>
      array( 'Groups' => array( 'Game' ) ), /* include/move.php */

      'Rated' =>
      array( 'Groups' => array( 'Game' ) ), /* include/move.php */

      'Moves' =>
      array( 'Groups' => array( 'Game' ) ), /* include/move.php */

      'dan' =>
      array( 'Groups' => array( 'Common' ) ), /* include/rating.php */

      'Page created in' =>
      array( 'Groups' => array( 'Common' ) ), /* include/std_functions.php */

      'day' =>
      array( 'Groups' => array( 'Common' ) ), /* include/std_functions.php */

      'and' =>
      array( 'Groups' => array( 'Common' ) ), /* include/std_functions.php */

      'hour' =>
      array( 'Groups' => array( 'Common' ) ), /* include/std_functions.php */

      'This %sgame%s invitation has already been accepted.' =>
      array( 'Groups' => array( 'Messages' ) ), /* message.php */

      'Reply:' =>
      array( 'Groups' => array( 'Messages' ) ), /* message.php */

      'New message:' =>
      array( 'Groups' => array( 'Messages' ) ), /* message.php */

      'white' =>
      array( 'Groups' => array( 'Messages' ) ), /* message.php */

      'Disputing settings:' =>
      array( 'Groups' => array( 'Messages' ) ), /* message.php */

      'Send Reply' =>
      array( 'Groups' => array( 'Messages' ) ), /* message.php */

      'Invitation message:' =>
      array( 'Groups' => array( 'Messages' ) ), /* message.php */

      'Send Invitation' =>
      array( 'Groups' => array( 'Messages' ) ), /* message.php */

      'Finished games for %s' =>
      array( 'Groups' => array( 'Game' ) ), /* show_games.php */

      'ID' =>
      array( 'Groups' => array( 'Common' ) ), /* users.php */

      'sgf' =>
      array( 'Groups' => array( 'Common', 'Game' ) ), /* show_games.php */

      'yes' =>
      array( 'Groups' => array( 'Common' ) ), /* show_games.php */

      'User Info' =>
      array( 'Groups' => array( 'Users' ) ), /* userinfo.php */

      'Biographical info' =>
      array( 'Groups' => array( 'Users' ) ), /* userinfo.php */

      'Conventional handicap (komi 0.5 if not even)' =>
      array( 'Groups' => array( 'Messages' ) ), /* include/message_functions.php */

      'Proper handicap' =>
      array( 'Groups' => array( 'Messages' ) ), /* include/message_functions.php */

      'Manual setting' =>
      array( 'Groups' => array( 'Messages' ) ), /* include/message_functions.php */

      'Handicap' =>
      array( 'Groups' => array( 'Messages' ) ), /* include/message_functions.php */

      'Even game with nigiri' =>
      array( 'Groups' => array( 'Messages' ) ), /* include/message_functions.php */

      'Double game' =>
      array( 'Groups' => array( 'Messages' ) ), /* include/message_functions.php */

      'Main time' =>
      array( 'Groups' => array( 'Messages', 'Game' ) ), /* include/message_functions.php */

      'Japanese byo-yomi' =>
      array( 'Groups' => array( 'Messages', 'Game' ) ), /* include/message_functions.php */

      'Canadian byo-yomi' =>
      array( 'Groups' => array( 'Messages', 'Game' ) ), /* include/message_functions.php */

      'Fischer time' =>
      array( 'Groups' => array( 'Messages', 'Game' ) ), /* include/message_functions.php */

      'Clock runs on weekends' =>
      array( 'Groups' => array( 'Messages', 'Game' ) ), /* include/message_functions.php */

      'Date' =>
      array( 'Groups' => array( 'Common', 'Messages' ) ), /* include/message_functions.php */

      'Game ID' =>
      array( 'Groups' => array( 'Messages', 'Game' ) ), /* include/message_functions.php */

      'Size' =>
      array( 'Groups' => array( 'Messages', 'Game' ) ), /* include/message_functions.php */

      'Komi' =>
      array( 'Groups' => array( 'Messages', 'Game' ) ), /* include/message_functions.php */

      'Colors' =>
      array( 'Groups' => array( 'Messages', 'Game' ) ), /* include/message_functions.php */

      'periods' =>
      array( 'Groups' => array( 'Game' ) )  /* include/std_functions.php */

      );


?>