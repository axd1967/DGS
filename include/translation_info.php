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

class %1$s_Language extends Language
{
  function %1$s_Language()
    {
      $this->full_name = \'%2$s\';
      $this->last_updated = %3$d;

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
       'Game', 'Index', 'Introduction', 'Links', 'Messages',
       'Misc', 'Register', 'Site map', 'Status', 'Users' );

$translation_info =
array(
      /* Found in these files: include/message_functions.php */
      '%s per %s stones' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: include/message_functions.php */
      '%s per move and %s extra periods' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: links.php */
      'A Brief History' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: message.php */
      'Accept' =>
      array( 'Groups' => array( 'Common', 'Messages' ) ),

      /* Found in these files: links.php */
      'A collaboration web site. Read and contribute!' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: users.php */
      'Activity' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: include/table_columns.php */
      'Add Column' =>
      array( 'Groups' => array( 'Game', 'Messages', 'Status' ) ),

      /* Found in these files: admin.php */
      'Added language %s with code %s and characterencoding %s.' =>
      array( 'Groups' => array( 'Admin' ) ),

      /* Found in these files: admin.php */
      'Added user %s as translator for language %s.' =>
      array( 'Groups' => array( 'Admin' ) ),

      /* Found in these files: admin.php */
      'Add language' =>
      array( 'Groups' => array( 'Admin' ) ),

      /* Found in these files: admin.php */
      'Add language for translation' =>
      array( 'Groups' => array( 'Admin' ) ),

      /* Found in these files: admin.php */
      'Add language for translator' =>
      array( 'Groups' => array( 'Admin' ) ),

      /* Found in these files: admin.php, include/std_functions.php */
      'Admin' =>
      array( 'Groups' => array( 'Admin', 'Common' ) ),

      /* Found in these files: links.php */
      'A large collection of go book reviews' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'A large server for realtime play' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'Also turn based. Has several other games.' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'A manga about go. Recommended!' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'A more complete list of servers' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: include/std_functions.php */
      'and' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: error.php */
      'An error occurred for this. Usually it works if you try again, otherwise please contact the support.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'A new password has already been sent to this user, please use that password instead of sending another one.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: links.php */
      'An excellent, but unfortunately no longer updated site.' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'An Interactive Introduction' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'An Introduction to Shape' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'Annotated Go Bibliographies' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'An open sourced go server' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'Another go site with lots of useful info.' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: userinfo.php */
      'Biographical info' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: change_bio.php */
      'Bio updated!' =>
      array( 'Groups' => array( 'Edit bio' ) ),

      /* Found in these files: message.php */
      'black' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: include/message_functions.php, include/move.php */
      'Black' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: edit_profile.php */
      'Board graphics' =>
      array( 'Groups' => array( 'Edit profile' ) ),

      /* Found in these files: include/message_functions.php */
      'Board size' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: docs.php */
      'Browse Dragon source code' =>
      array( 'Groups' => array( 'Documentation' ) ),

      /* Found in these files: include/message_functions.php, include/move.php */
      'Byoyomi' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: include/message_functions.php */
      'Canadian' =>
      array( 'Groups' => array( 'Common', 'Messages' ) ),

      /* Found in these files: include/message_functions.php */
      'Canadian byo-yomi' =>
      array( 'Groups' => array( 'Game', 'Messages' ) ),

      /* Found in these files: include/std_functions.php */
      'Canadian byoyomi' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: edit_bio.php */
      'Change bio' =>
      array( 'Groups' => array( 'Edit bio' ) ),

      /* Found in these files: admin.php */
      'Changed translator privileges info for user %s.' =>
      array( 'Groups' => array( 'Admin' ) ),

      /* Found in these files: edit_password.php, site_map.php, userinfo.php */
      'Change password' =>
      array( 'Groups' => array( 'Edit password', 'Site map' ) ),

      /* Found in these files: edit_profile.php */
      'Change profile' =>
      array( 'Groups' => array( 'Edit profile' ) ),

      /* Found in these files: admin.php */
      'Character encoding (i.e. \'iso-8859-1\')' =>
      array( 'Groups' => array( 'Admin' ) ),

      /* Found in these files: edit_bio.php */
      'City' =>
      array( 'Groups' => array( 'Edit bio' ) ),

      /* Found in these files: include/message_functions.php */
      'Clock runs on weekends' =>
      array( 'Groups' => array( 'Game', 'Messages' ) ),

      /* Found in these files: edit_bio.php */
      'Club' =>
      array( 'Groups' => array( 'Edit bio' ) ),

      /* Found in these files: show_games.php, status.php */
      'Color' =>
      array( 'Groups' => array( 'Common', 'Game', 'Messages' ) ),

      /* Found in these files: include/message_functions.php */
      'Colors' =>
      array( 'Groups' => array( 'Common', 'Game', 'Messages' ) ),

      /* Found in these files: links.php */
      'Common Japanese Go Terms' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: edit_password.php, register.php */
      'Confirm password' =>
      array( 'Groups' => array( 'Common', 'Edit password', 'Register' ) ),

      /* Found in these files: error.php */
      'Connection to database failed. Please wait a few minutes and test again.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: include/message_functions.php */
      'Conventional handicap (komi 0.5 if not even)' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: edit_profile.php */
      'Coordinate sides' =>
      array( 'Groups' => array( 'Edit profile' ) ),

      /* Found in these files: error.php */
      'Couldn\'t extrapolate value in function interpolate' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Couldn\'t select the database. Please wait a few minutes and try again. ' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: edit_bio.php */
      'Country' =>
      array( 'Groups' => array( 'Edit bio' ) ),

      /* Found in these files: docs.php */
      'daily snapshot of the cvs' =>
      array( 'Groups' => array( 'Documentation' ) ),

      /* Found in these files: include/rating.php */
      'dan' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: error.php */
      'Database query failed. Please wait a few minutes and try again. ' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: include/message_functions.php, list_messages.php, status.php */
      'Date' =>
      array( 'Groups' => array( 'Common', 'Messages' ) ),

      /* Found in these files: include/std_functions.php */
      'day' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: include/message_functions.php, include/std_functions.php */
      'days' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: message.php */
      'Decline' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: list_messages.php */
      'Del' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: list_messages.php */
      'Delete all' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: game.php */
      'Delete game' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: error.php */
      'Delete game failed. This is problably not a problem.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: game.php */
      'Deleting game' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: message.php */
      'Disputing settings' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: docs.php, include/std_functions.php */
      'Docs' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: docs.php, site_map.php */
      'Documentation' =>
      array( 'Groups' => array( 'Documentation', 'Site map' ) ),

      /* Found in these files: game.php */
      'Done' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: include/message_functions.php */
      'Double game' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: edit_profile.php */
      'Down' =>
      array( 'Groups' => array( 'Edit profile' ) ),

      /* Found in these files: docs.php */
      'Download dragon sources' =>
      array( 'Groups' => array( 'Documentation' ) ),

      /* Found in these files: game.php */
      'Download sgf' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: docs.php */
      'Dragon project page at sourceforge' =>
      array( 'Groups' => array( 'Documentation' ) ),

      /* Found in these files: site_map.php, userinfo.php */
      'Edit bio' =>
      array( 'Groups' => array( 'Edit bio', 'Site map', 'Status' ) ),

      /* Found in these files: edit_bio.php */
      'Edit biopgraphical info' =>
      array( 'Groups' => array( 'Edit bio' ) ),

      /* Found in these files: edit_password.php */
      'Edit password' =>
      array( 'Groups' => array( 'Edit password' ) ),

      /* Found in these files: edit_profile.php, site_map.php, userinfo.php */
      'Edit profile' =>
      array( 'Groups' => array( 'Edit profile', 'Site map' ) ),

      /* Found in these files: edit_bio.php, edit_profile.php */
      'Email' =>
      array( 'Groups' => array( 'Common', 'Edit profile' ) ),

      /* Found in these files: edit_profile.php */
      'Email notifications' =>
      array( 'Groups' => array( 'Edit profile' ) ),

      /* Found in these files: show_games.php */
      'End date' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: error.php */
      'Error, guest may not recieve messages' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: links.php */
      'European shop' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: include/message_functions.php */
      'Even game with nigiri' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: include/message_functions.php */
      'extra periods.' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: include/message_functions.php */
      'extra per move' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: include/message_functions.php */
      'extra per move.' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: site_map.php */
      'FAQ' =>
      array( 'Groups' => array( 'Site map' ) ),

      /* Found in these files: users.php */
      'Finished' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: show_games.php */
      'Finished games for %s' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: include/message_functions.php */
      'Fischer time' =>
      array( 'Groups' => array( 'Game', 'Messages' ) ),

      /* Found in these files: list_messages.php, status.php */
      'Flags' =>
      array( 'Groups' => array( 'Status' ) ),

      /* Found in these files: include/message_functions.php */
      'for' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: forgot.php, index.php */
      'Forgot password?' =>
      array( 'Groups' => array( 'Index' ) ),

      /* Found in these files: site_map.php */
      'Forum' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: include/std_functions.php */
      'Forums' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: links.php */
      'For you people with short attention spans.' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: docs.php */
      'Frequently Asked Questions' =>
      array( 'Groups' => array( 'Documentation' ) ),

      /* Found in these files: links.php */
      'Frequently asked questions about go for the rec.games.go newsgroup' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: include/message_functions.php, list_messages.php, status.php */
      'From' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: edit_profile.php */
      'Full board and messages' =>
      array( 'Groups' => array( 'Edit profile' ) ),

      /* Found in these files: edit_profile.php, register.php */
      'Full name' =>
      array( 'Groups' => array( 'Common', 'Edit profile', 'Register' ) ),

      /* Found in these files: game.php, site_map.php */
      'Game' =>
      array( 'Groups' => array( 'Common', 'Game' ) ),

      /* Found in these files: include/message_functions.php */
      'Game ID' =>
      array( 'Groups' => array( 'Game', 'Messages' ) ),

      /* Found in these files: edit_profile.php */
      'Game id button' =>
      array( 'Groups' => array( 'Edit profile' ) ),

      /* Found in these files: edit_bio.php */
      'Game preferences' =>
      array( 'Groups' => array( 'Edit bio' ) ),

      /* Found in these files: users.php */
      'Games' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: links.php */
      'General Info' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: licence.php */
      'General Public Licence' =>
      array( 'Groups' => array( 'Misc' ) ),

      /* Found in these files: include/move.php */
      'Go back' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: links.php */
      'Go books, equipment and software' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'Go FAQ' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'Go News' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'Go Problems' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'Go Teaching Ladder' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: include/message_functions.php, include/move.php, show_games.php, status.php */
      'Handicap' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: links.php */
      'Het Paard' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: list_messages.php */
      'Hide deleted' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: links.php */
      'Hikaru no Go' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'History' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: edit_bio.php */
      'Hobbies' =>
      array( 'Groups' => array( 'Edit bio' ) ),

      /* Found in these files: index.php */
      'Home' =>
      array( 'Groups' => array( 'Index' ) ),

      /* Found in these files: edit_bio.php */
      'Homepage' =>
      array( 'Groups' => array( 'Edit bio' ) ),

      /* Found in these files: include/std_functions.php */
      'hour' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: include/message_functions.php, include/std_functions.php */
      'hours' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: links.php */
      'How to Teach Go' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: edit_bio.php */
      'ICQ-number' =>
      array( 'Groups' => array( 'Edit bio' ) ),

      /* Found in these files: show_games.php, status.php, users.php */
      'ID' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: forgot.php */
      'If you have forgot your password we can email a new one. The new password will be randomly generated, but you can of course change it later from the edit profile page.' =>
      array( 'Groups' => array( 'Misc' ) ),

      /* Found in these files: docs.php */
      'if you want your own dragon' =>
      array( 'Groups' => array( 'Documentation' ) ),

      /* Found in these files: links.php */
      'IGS' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'In case you\'re an aspiring know-it-all.' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: docs.php, install.php */
      'Installation instructions' =>
      array( 'Groups' => array( 'Misc' ) ),

      /* Found in these files: introduction.php, links.php, site_map.php */
      'Introduction' =>
      array( 'Groups' => array( 'Misc' ) ),

      /* Found in these files: introduction.php */
      'Introduction to dragon' =>
      array( 'Groups' => array( 'Introduction' ) ),

      /* Found in these files: docs.php */
      'Introduction to Dragon' =>
      array( 'Groups' => array( 'Documentation' ) ),

      /* Found in these files: message.php */
      'Invitation message' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: include/std_functions.php, site_map.php */
      'Invite' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: show_games.php, userinfo.php */
      'Invite this user' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: links.php */
      'It\'s your turn' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'Jan van der Steens Pages' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: include/message_functions.php */
      'Japanese' =>
      array( 'Groups' => array( 'Common', 'Messages' ) ),

      /* Found in these files: include/message_functions.php */
      'Japanese byo-yomi' =>
      array( 'Groups' => array( 'Game', 'Messages' ) ),

      /* Found in these files: include/std_functions.php */
      'Japanese byoyomi' =>
      array( 'Groups' => array( 'Game', 'Messages' ) ),

      /* Found in these files: show_games.php */
      'jigo' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: links.php */
      'Kiseido' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'Kiseido Go Server' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: include/message_functions.php, include/move.php, show_games.php, status.php */
      'Komi' =>
      array( 'Groups' => array( 'Game', 'Messages' ) ),

      /* Found in these files: include/rating.php */
      'kyu' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: edit_profile.php */
      'Language' =>
      array( 'Groups' => array( 'Common', 'Edit profile' ) ),

      /* Found in these files: admin.php */
      'Language name (i.e. English)' =>
      array( 'Groups' => array( 'Admin' ) ),

      /* Found in these files: users.php */
      'Last Access' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: userinfo.php */
      'Last access' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: show_games.php, status.php */
      'Last Move' =>
      array( 'Groups' => array( 'Status', 'Users' ) ),

      /* Found in these files: users.php */
      'Last Moved' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: edit_profile.php */
      'Left' =>
      array( 'Groups' => array( 'Edit profile' ) ),

      /* Found in these files: docs.php, site_map.php */
      'Licence' =>
      array( 'Groups' => array( 'Documentation', 'Site map' ) ),

      /* Found in these files: docs.php, links.php, site_map.php */
      'Links' =>
      array( 'Groups' => array( 'Documentation', 'Links', 'Site map' ) ),

      /* Found in these files: include/std_functions.php */
      'Logged in as' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: index.php */
      'Log in' =>
      array( 'Groups' => array( 'Index' ) ),

      /* Found in these files: include/std_functions.php */
      'Logout' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: users.php */
      'Lost' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: links.php */
      'Lots of info on go' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: include/message_functions.php */
      'Main time' =>
      array( 'Groups' => array( 'Game', 'Messages' ) ),

      /* Found in these files: include/move.php */
      'Main Time' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: include/message_functions.php */
      'Manual setting' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: links.php */
      'Meet other turn-based go players' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: include/message_functions.php, include/move.php, message.php */
      'Message' =>
      array( 'Groups' => array( 'Game', 'Messages' ) ),

      /* Found in these files: list_messages.php */
      'Message list' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: include/std_functions.php, site_map.php */
      'Messages' =>
      array( 'Groups' => array( 'Common', 'Messages' ) ),

      /* Found in these files: send_message.php */
      'Message sent!' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: links.php */
      'Mind Sport Zine' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: include/message_functions.php */
      'months' =>
      array( 'Groups' => array( 'Common', 'Messages' ) ),

      /* Found in these files: introduction.php */
      'More information can be found in the <a href="phorum/list.php?f=3">FAQ forum</a> where you are also encouraged to submit your own questions.' =>
      array( 'Groups' => array( 'Introduction' ) ),

      /* Found in these files: links.php */
      'More Japanese Go Terms' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: error.php */
      'Move outside board?' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: include/move.php, show_games.php, status.php */
      'Moves' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: edit_profile.php */
      'Moves and messages' =>
      array( 'Groups' => array( 'Edit profile' ) ),

      /* Found in these files: include/message_functions.php */
      'My color' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: site_map.php */
      'My user info' =>
      array( 'Groups' => array( 'Site map' ) ),

      /* Found in these files: include/move.php, status.php, userinfo.php, users.php */
      'Name' =>
      array( 'Groups' => array( 'Status' ) ),

      /* Found in these files: list_messages.php, status.php */
      'New' =>
      array( 'Groups' => array( 'Status' ) ),

      /* Found in these files: message.php */
      'New message' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: edit_password.php */
      'New password' =>
      array( 'Groups' => array( 'Edit password' ) ),

      /* Found in these files: links.php */
      'News and games from the professional scene' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: site_map.php */
      'New topic' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: include/table_columns.php */
      'next page' =>
      array( 'Groups' => array( 'Game', 'Messages' ) ),

      /* Found in these files: show_games.php, status.php, users.php */
      'Nick' =>
      array( 'Groups' => array( 'Status', 'Users' ) ),

      /* Found in these files: edit_profile.php */
      'Nighttime' =>
      array( 'Groups' => array( 'Edit profile' ) ),

      /* Found in these files: include/message_functions.php */
      'Nigiri' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: links.php */
      'NNGS' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: include/message_functions.php, include/move.php */
      'No' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: show_games.php */
      'no' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: status.php */
      'No games found' =>
      array( 'Groups' => array( 'Status' ) ),

      /* Found in these files: error.php */
      'Nothing to be done?' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: edit_profile.php */
      'Notify only' =>
      array( 'Groups' => array( 'Edit profile' ) ),

      /* Found in these files: include/std_functions.php */
      'Not logged in' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: edit_bio.php */
      'Occupation' =>
      array( 'Groups' => array( 'Edit bio' ) ),

      /* Found in these files: edit_profile.php */
      'Off' =>
      array( 'Groups' => array( 'Common', 'Edit profile' ) ),

      /* Found in these files: edit_password.php */
      'Old password' =>
      array( 'Groups' => array( 'Edit password' ) ),

      /* Found in these files: introduction.php */
      'Once again welcome, and enjoy your visit here!' =>
      array( 'Groups' => array( 'Introduction' ) ),

      /* Found in these files: users.php */
      'Only active users' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: edit_profile.php, userinfo.php */
      'Open for matches' =>
      array( 'Groups' => array( 'Edit profile', 'Status' ) ),

      /* Found in these files: status.php, users.php */
      'Open for matches?' =>
      array( 'Groups' => array( 'Status', 'Users' ) ),

      /* Found in these files: show_games.php, status.php */
      'Opponent' =>
      array( 'Groups' => array( 'Status', 'Users' ) ),

      /* Found in these files: edit_bio.php */
      'Other:' =>
      array( 'Groups' => array( 'Edit bio' ) ),

      /* Found in these files: links.php */
      'Other go servers' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: include/std_functions.php */
      'Page created in' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: game.php */
      'Pass' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: game.php */
      'Passing' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: index.php, register.php */
      'Password' =>
      array( 'Groups' => array( 'Common', 'Index', 'Register' ) ),

      /* Found in these files: change_password.php */
      'Password changed!' =>
      array( 'Groups' => array( 'Edit password' ) ),

      /* Found in these files: users.php */
      'Percent' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: include/std_functions.php */
      'periods' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: edit_profile.php */
      'Personal settings' =>
      array( 'Groups' => array( 'Edit profile' ) ),

      /* Found in these files: include/move.php */
      'Place your handicap stones, please!' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: docs.php */
      'plans for future improvements' =>
      array( 'Groups' => array( 'Documentation' ) ),

      /* Found in these files: register.php */
      'Please enter data' =>
      array( 'Groups' => array( 'Register' ) ),

      /* Found in these files: index.php */
      'Please login.' =>
      array( 'Groups' => array( 'Index' ) ),

      /* Found in these files: game.php */
      'Please mark dead stones and click \'done\' when finished.' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: include/table_columns.php */
      'prev page' =>
      array( 'Groups' => array( 'Game', 'Messages' ) ),

      /* Found in these files: include/move.php */
      'Prisoners' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: change_profile.php */
      'Profile updated!' =>
      array( 'Groups' => array( 'Edit profile' ) ),

      /* Found in these files: include/message_functions.php */
      'Proper handicap' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: links.php */
      'Rafael\'s Go Page' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: users.php */
      'Rank Info' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: edit_profile.php, include/move.php, status.php, userinfo.php */
      'Rank info' =>
      array( 'Groups' => array( 'Edit profile', 'Status' ) ),

      /* Found in these files: include/message_functions.php, include/move.php */
      'Rated' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: edit_profile.php, include/move.php, status.php, userinfo.php, users.php */
      'Rating' =>
      array( 'Groups' => array( 'Common', 'Edit profile', 'Status' ) ),

      /* Found in these files: site_map.php */
      'Read forum' =>
      array( 'Groups' => array( 'Site map' ) ),

      /* Found in these files: register.php */
      'Register' =>
      array( 'Groups' => array( 'Register' ) ),

      /* Found in these files: index.php */
      'Register new account' =>
      array( 'Groups' => array( 'Index' ) ),

      /* Found in these files: userinfo.php */
      'Registration date' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: include/message_functions.php, list_messages.php */
      'Replied' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: message.php */
      'Reply' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: list_messages.php, status.php */
      'Reply!' =>
      array( 'Groups' => array( 'Messages', 'Status' ) ),

      /* Found in these files: game.php */
      'Resign' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: game.php */
      'Resigning' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: game.php */
      'Resume playing' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: edit_profile.php */
      'Right' =>
      array( 'Groups' => array( 'Edit profile' ) ),

      /* Found in these files: links.php */
      'Rules' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: users.php */
      'Running' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: show_games.php */
      'Running games for %s' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: links.php */
      'Samarkand' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: game.php, show_games.php */
      'Score' =>
      array( 'Groups' => array( 'Common', 'Game' ) ),

      /* Found in these files: links.php */
      'Scot\'s Go Page' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: admin.php */
      'Select language to make user translator for that language.' =>
      array( 'Groups' => array( 'Admin' ) ),

      /* Found in these files: admin.php */
      'Select the languages the user should be allowed to translate' =>
      array( 'Groups' => array( 'Admin' ) ),

      /* Found in these files: list_messages.php, site_map.php */
      'Send a message' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: message.php */
      'Send Invitation' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: message.php */
      'Send Message' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: userinfo.php */
      'Send message to user' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: message.php */
      'Send Reply' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: links.php */
      'Sensei\'s Library' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'Server list' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'Server with java interface' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: admin.php */
      'Set translator privileges for user' =>
      array( 'Groups' => array( 'Admin' ) ),

      /* Found in these files: admin.php */
      'Set user privileges' =>
      array( 'Groups' => array( 'Admin' ) ),

      /* Found in these files: show_games.php, status.php */
      'sgf' =>
      array( 'Groups' => array( 'Common', 'Game' ) ),

      /* Found in these files: site_map.php */
      'SGF file of game' =>
      array( 'Groups' => array( 'Site map' ) ),

      /* Found in these files: status.php */
      'Show/edit userinfo' =>
      array( 'Groups' => array( 'Status' ) ),

      /* Found in these files: list_messages.php */
      'Show all' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: site_map.php */
      'Show all messages' =>
      array( 'Groups' => array( 'Site map' ) ),

      /* Found in these files: users.php */
      'Show all users' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: show_games.php, site_map.php, status.php, userinfo.php */
      'Show finished games' =>
      array( 'Groups' => array( 'Status' ) ),

      /* Found in these files: site_map.php */
      'Show message' =>
      array( 'Groups' => array( 'Site map' ) ),

      /* Found in these files: list_messages.php */
      'Show recieved messages' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: show_games.php, site_map.php, status.php, userinfo.php */
      'Show running games' =>
      array( 'Groups' => array( 'Status' ) ),

      /* Found in these files: list_messages.php, site_map.php */
      'Show sent messages' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: docs.php, site_map.php */
      'Site map' =>
      array( 'Groups' => array( 'Misc' ) ),

      /* Found in these files: include/message_functions.php, show_games.php, status.php */
      'Size' =>
      array( 'Groups' => array( 'Game', 'Messages' ) ),

      /* Found in these files: game.php */
      'Skip to next game' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: edit_profile.php */
      'Smooth board edge' =>
      array( 'Groups' => array( 'Edit profile' ) ),

      /* Found in these files: error.php */
      'Sorry, can\'t find the game you are invited to. Already declined?' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, couldn\'t find the reciever of your message. Make sure to use the userid, not the full name.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, couldn\'t start the game. Please wait a few minutes and try again.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, couldn\'t update player data. Please wait a few minutes and try again.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, I\'ve problem with the rating, did you forget to specify \'kyu\' or \'dan\'?' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, I\'ve problem with the rating, you shouldn\'t use \'kyu\' or \'dan\' for this ratingtype' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, I can\'t find that game.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, I couldn\'t find that forum you wanted to show.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, I couldn\'t find the language you want to translate. Please contact the support.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, I couldn\'t find the message you wanted to show.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, I couldn\'t find this user.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, I don\'t know anyone with that userid.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, I need a game number to know what game to show.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, I need to known for which user to show the data.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, it\'s not your turn.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, I was unable to make a backup of the old translation, aborting. Please contact the support.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, I was unable to open a file for writing. Please contact the support.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, only translators are allowed to translate.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, something went wrong when trying to insert the new translations into the database.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, the additon of the game to the database seems to have failed.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, the additon of the message to the database seems to have failed.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, the game has already finished.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, the game hasn\'t started yet.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, the initial rating must be between 30 kyu and 6 dan.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, the language you tried to add already exists.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, the password must be at least six letters.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, there was a missing or incorrect field when adding a language.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, this is not allowed for guests, please first register a personal account' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, this page is solely for users with administrative tasks.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, this stone would have killed itself, but suicide is not allowed under this ruleset.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, this userid is already used, please try to find a unique userid.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, userid must be at least 3 letters long.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, you and your opponent need to set an initial rating, otherwise I can\'t find a suitable handicap' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, you are not allowed to translate the specified language.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, you can\'t invite yourself.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, you didn\'t write your current password correctly.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, you have to be logged in to do that.
<p>
The reasons for this problem could be any of the following:
<ul>
<li> You haven\'t got an <a href="%1$s/register.php">account</a>, or haven\'t <a href="%1$s/index.php">logged in</a> yet.
<li> Your cookies have expired. This happens once a week.
<li> You haven\'t enabled cookies in your browser.
</ul>' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, you have to supply a name.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, you may not pass before all handicap stones are placed.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, you may not retake a stone which has just captured a stone, since it would repeat a previous board position. Look for \'ko\' in the rules.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, you may only place stones on empty points.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, you must specify a language.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, you must specify a user.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Sorry, you wrote a non-numeric value on a numeric field.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: edit_bio.php */
      'State' =>
      array( 'Groups' => array( 'Edit bio' ) ),

      /* Found in these files: include/std_functions.php, site_map.php, status.php */
      'Status' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: include/message_functions.php, include/std_functions.php */
      'stones' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: edit_profile.php */
      'Stone size' =>
      array( 'Groups' => array( 'Edit profile' ) ),

      /* Found in these files: links.php */
      'Strategy and terms' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: include/message_functions.php, list_messages.php, message.php, status.php */
      'Subject' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: include/move.php */
      'Submit and go to next game' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: include/move.php */
      'Submit and go to status' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: links.php */
      'Submit your games for comments to see where you might have played better.' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: error.php */
      'The confirmed password didn\'t match the password, please go back and retry.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: links.php */
      'The Extended History' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: error.php */
      'The insertion of the move into the database seems to have failed. This may or may not be a problem, please return to the game to see if the move has been registered.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'The insertion of your data into the database seems to have failed. If you can\'t log in, please try once more and, if this fails, contact the support.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'The komi is out of range, please choose a move reasonable value.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: message.php */
      'This %sgame%s invitation has already been accepted.' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: message.php */
      'This invitation has been declined or the game deleted' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: links.php */
      'This is all you need to get started. Very basic stuff' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'This is a very nice site to learn with.' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'This is more in-depth.' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: error.php */
      'This type action is either unknown or can\'t be use in this state of the game.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: site_map.php */
      'Thread list' =>
      array( 'Groups' => array( 'Site map' ) ),

      /* Found in these files: include/move.php */
      'Time limit' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: edit_profile.php */
      'Timezone' =>
      array( 'Groups' => array( 'Common', 'Edit profile' ) ),

      /* Found in these files: include/message_functions.php, list_messages.php */
      'To' =>
      array( 'Groups' => array( 'Messages', 'Status' ) ),

      /* Found in these files: message.php */
      'To (userid)' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: docs.php */
      'To do list' =>
      array( 'Groups' => array( 'Documentation' ) ),

      /* Found in these files: site_map.php */
      'Todo list' =>
      array( 'Groups' => array( 'Site map' ) ),

      /* Found in these files: index.php */
      'To look around, use %s.' =>
      array( 'Groups' => array( 'Index' ) ),

      /* Found in these files: include/std_functions.php, site_map.php, translate.php */
      'Translate' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: links.php */
      'Translated and explained.' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: links.php */
      'Turn-based go guild' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: admin.php */
      'Two-letter language code' =>
      array( 'Groups' => array( 'Admin' ) ),

      /* Found in these files: error.php */
      'Unknown problem. This shouldn\'t happen. Please send the url of this page to the support, so that this doesn\'t happen again.' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: error.php */
      'Unknown rank type' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: edit_profile.php */
      'Up' =>
      array( 'Groups' => array( 'Edit profile' ) ),

      /* Found in these files: admin.php */
      'User %s is already translator for language %s.' =>
      array( 'Groups' => array( 'Admin' ) ),

      /* Found in these files: edit_profile.php, forgot.php, index.php, register.php, status.php, userinfo.php */
      'Userid' =>
      array( 'Groups' => array( 'Common', 'Edit profile', 'Register', 'Status' ) ),

      /* Found in these files: show_games.php, site_map.php */
      'User info' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: userinfo.php */
      'User Info' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: include/std_functions.php, site_map.php, users.php */
      'Users' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: admin.php */
      'User to set privileges for (use the userid)' =>
      array( 'Groups' => array( 'Admin' ) ),

      /* Found in these files: links.php */
      'Very well written introduction by the British Go Association.' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: site_map.php */
      'Welcome page' =>
      array( 'Groups' => array( 'Site map' ) ),

      /* Found in these files: introduction.php */
      'Welcome to Dragon Go Server, a <a href="licence.php">free</a>server for playing <a href="links.php">go</a>, where the games tends to \'drag on\'.' =>
      array( 'Groups' => array( 'Introduction' ) ),

      /* Found in these files: message.php */
      'white' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: include/message_functions.php, include/move.php */
      'White' =>
      array( 'Groups' => array( 'Common', 'Game' ) ),

      /* Found in these files: show_games.php */
      'Win?' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: include/message_functions.php */
      'with' =>
      array( 'Groups' => array( 'Messages' ) ),

      /* Found in these files: docs.php */
      'with answers' =>
      array( 'Groups' => array( 'Documentation' ) ),

      /* Found in these files: include/std_functions.php */
      'without byoyomi' =>
      array( 'Groups' => array( 'Game' ) ),

      /* Found in these files: users.php */
      'Won' =>
      array( 'Groups' => array( 'Users' ) ),

      /* Found in these files: edit_profile.php */
      'Wood color' =>
      array( 'Groups' => array( 'Edit profile' ) ),

      /* Found in these files: links.php */
      'Working through these can help out your game.' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: error.php */
      'Wrong, number of handicap stones' =>
      array( 'Groups' => array( 'Error' ) ),

      /* Found in these files: include/message_functions.php, include/move.php */
      'Yes' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: show_games.php */
      'yes' =>
      array( 'Groups' => array( 'Common' ) ),

      /* Found in these files: introduction.php */
      'You can look at it as kind of play-by-email, where a web-interface is used to make the board look prettier. To start playing you should first get yourself an <a href="register.php">account</a>, if you haven\'t got one already. Thereafter you could <a href="edit_profile.php">edit your profile</a> and <a href="edit_bio.php">enter some biographical info</a>, especially the fields \'Open for matches?\', \'Rating\' and \'Rank info\' are useful for finding opponents. Next you can study the <a href="users.php">user list</a> and use the <a href="phorum/index.php">forums</a> to find suitable opponents to <a href="invite.php">invite</a> for a game.' =>
      array( 'Groups' => array( 'Introduction' ) ),

      /* Found in these files: links.php */
      'You have to know what other players are talking about.' =>
      array( 'Groups' => array( 'Links' ) ),

      /* Found in these files: status.php */
      'Your turn to move in the following games:' =>
      array( 'Groups' => array( 'Status' ) ),

      /* Found in these files: links.php */
      'Yutopian' =>
      array( 'Groups' => array( 'Links' ) )

      );


?>