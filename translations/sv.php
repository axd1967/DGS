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

add_to_known_languages( "sv", "Svenska", 1018728671 );

class sv_Language extends Language
{
  function sv_Language()
    {
      $this->translated_strings = array(
'Edit password' =>
'Ändra lösenord',

'Old password' =>
'Gammalt lösenord',

'New password' =>
'Nytt lösenord',

'Confirm password' =>
'Bekräfta lösenordet',

'Change password' =>
'Byt lösenord',

'Edit profile' =>
'Ändra din profil',

'Personal settings' =>
'Personliga inställningar',

'Userid' =>
'Användaridentitet',

'Full name' =>
'Hela namnet',

'Email' =>
'Epost',

'Open for matches' =>
'',

'Rank info' =>
'',

'Rating' =>
'',

'Off' =>
'',

'Notify only' =>
'',

'Moves and messages' =>
'',

'Full board and messages' =>
'',

'Email notifications' =>
'',

'Language' =>
'Språk',

'Timezone' =>
'Tidszon',

'Nighttime' =>
'',

'Board graphics' =>
'Brädgrafik',

'Stone size' =>
'',

'Wood color' =>
'',

'Coordinate sides' =>
'',

'Game id button' =>
'',

'Change profile' =>
'',

'Sorry, you may not pass before all handicap stones are placed.' =>
'',

'Sorry, the game has already finished.' =>
'',

'Sorry, the game hasn\'t started yet.' =>
'',

'Error, guest may not recieve messages' =>
'',

'Move outside board?' =>
'',

'This type action is either unknown or can\'t be use in this state of the game.' =>
'',

'Sorry, you can\'t invite yourself.' =>
'',

'Sorry, can\'t find the game you are invited to. Already declined?' =>
'',

'Sorry, you may not retake a stone which has just captured a stone, since it would repeat a previous board position. Look for \'ko\' in the rules.' =>
'',

'The komi is out of range, please choose a move reasonable value.' =>
'',

'An error occurred for this. Usually it works if you try again, otherwise please contact the support.' =>
'',

'Connection to database failed. Please wait a few minutes and test again.' =>
'',

'Delete game failed. This is problably not a problem.' =>
'',

'Sorry, the additon of the message to the database seems to have failed.' =>
'',

'Sorry, the additon of the game to the database seems to have failed.' =>
'',

'The insertion of the move into the database seems to have failed. This may or may not be a problem, please return to the game to see if the move has been registered.' =>
'',

'The insertion of your data into the database seems to have failed. If you can\'t log in, please try once more and, if this fails, contact the support.' =>
'',

'Database query failed. Please wait a few minutes and try again. ' =>
'',

'Couldn\'t select the database. Please wait a few minutes and try again. ' =>
'',

'Sorry, couldn\'t start the game. Please wait a few minutes and try again.' =>
'',

'Sorry, couldn\'t update player data. Please wait a few minutes and try again.' =>
'',

'Sorry, you have to supply a name.' =>
'',

'A new password has already been sent to this user, please use that password instead of sending another one.' =>
'',

'Nothing to be done?' =>
'',

'Sorry, I need a game number to know what game to show.' =>
'',

'Sorry, you and your opponent need to set an initial rating, otherwise I can\'t find a suitable handicap' =>
'',

'Sorry, I need to known for which user to show the data.' =>
'',

'Sorry, this is not allowed for guests, please first register a personal account' =>
'',

'Sorry, you may only place stones on empty points.' =>
'',

'Sorry, you have to be logged in to do that.
<p>
The reasons for this problem could be any of the following:
<ul>
<li> You haven\'t got an <a href="%1$s/register.php">account</a>, or haven\'t <a href="%1$s/index.php">logged in</a> yet.
<li> Your cookies have expired. This happens once a week.
<li> You haven\'t enabled cookies in your browser.
</ul>
' =>
'Tyvärr, men du måste vara inloggad för att göra detta.
<p>
Anledningen till detta problem kan vara ett av följande:
<ul>
<li> Du har inget <a href="%1$s/register.php">konto</a>, eller så har du inte <a href="%1$s/index.php">loggat in</a> än.
<li> Dina kakor har blivit ogiltiga. Detta händer en gång i veckan.
<li> Du har inte slagit på kakor i din webbläsare.
</ul>
',

'Sorry, it\'s not your turn.' =>
'',

'The confirmed password didn\'t match the password, please go back and retry.' =>
'',

'Sorry, the password must be at least six letters.' =>
'',

'Sorry, couldn\'t find the reciever of your message. Make sure to use the userid, not the full name.' =>
'',

'Sorry, I\'ve problem with the rating, did you forget to specify \'kyu\' or \'dan\'?' =>
'',

'Sorry, I\'ve problem with the rating, you shouldn\'t use \'kyu\' or \'dan\' for this ratingtype' =>
'',

'Sorry, this stone would have killed itself, but suicide is not allowed under this ruleset.' =>
'',

'Sorry, I can\'t find that game.' =>
'',

'Sorry, I couldn\'t find that forum you wanted to show.' =>
'',

'Sorry, I couldn\'t find the message you wanted to show.' =>
'',

'Sorry, I couldn\'t find this user.' =>
'',

'Sorry, this userid is already used, please try to find a unique userid.' =>
'',

'Sorry, userid must be at least 3 letters long.' =>
'',

'Couldn\'t extrapolate value in function interpolate()' =>
'',

'Wrong, number of handicap stones' =>
'',

'Sorry, you didn\'t write your current password correctly.' =>
'',

'Unknown rank type' =>
'',

'Sorry, I don\'t know anyone with that userid.' =>
'',

'Sorry, the initial rating must be between 30 kyu and 6 dan.' =>
'',

'Sorry, you wrote a non-numeric value on a numeric field.' =>
'',

'Sorry, only translators are allowed to translate.' =>
'',

'Sorry, I couldn\'t find the language you want to translate. Please contact the support.' =>
'',

'Sorry, I was unable to make a backup of the old translation, aborting. Please contact the support.' =>
'',

'Sorry, I was unable to open a file for writing. Please contact the support.' =>
'',

'Unknown problem. This shouldn\'t happen. Please send the url of this page to the support, so that this doesn\'t happen again.' =>
'',

'Resigning' =>
'',

'Passing' =>
'',

'Deleting game' =>
'',

'Please mark dead stones and click \'done\' when finished.' =>
'',

'Score' =>
'Poäng',

'Game' =>
'',

'Pass' =>
'',

'Delete game' =>
'',

'Done' =>
'',

'Resume playing' =>
'',

'Download sgf' =>
'',

'Resign' =>
'',

'Skip to next game' =>
'',

'Home' =>
'Hemma',

'Please login.' =>
'Logga in, tack.',

'To look around, use %s.' =>
'Använd %s för att se dig omkring.',

'Password' =>
'Lösenord',

'Log in' =>
'Logga in',

'Forgot password?' =>
'Glömt lösenordet?',

'Register new account' =>
'Registrera ett nytt konto',

'Send a message' =>
'Skicka ett meddelande',

'Show recieved messages' =>
'Visa mottagna meddelanden',

'Hide deleted' =>
'Göm borttagna',

'Show all' =>
'Visa alla',

'Show sent messages' =>
'Visa skickade meddelanden',

'Delete all' =>
'Ta bort alla',

'Register' =>
'Registrera',

'Please enter data' =>
'Var god fyll i data',

'Name' =>
'Namn',

'Your turn to move in the following games:' =>
'',

'No games found' =>
'Inga spel finns',

'Show/edit userinfo' =>
'',

'Show running games' =>
'',

'Show finished games' =>
'',

'Only active users' =>
'',

'Show all users' =>
'',

'Submit and go to next game' =>
'',

'Submit and go to status' =>
'',

'Go back' =>
'',

'Logged in as' =>
'Inloggad som',

'Not logged in' =>
'Ej inloggad',

'Status' =>
'Status',

'Messages' =>
'Meddelanden',

'Invite' =>
'Bjud in',

'Users' =>
'Användare',

'Forums' =>
'Forum',

'Translate' =>
'Översätt',

'Docs' =>
'Dokumentation',

'Page created in %0.5f' =>
'Sidan skapades på %0.5f',

'Logout' =>
'Logga ut' );
    }
};

?>
