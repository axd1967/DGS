<?php
/*
Dragon Go Server
Copyright (C) 2001  Erik Ouchterlony

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

header ("Cache-Control: no-cache, must-revalidate, max_age=0"); 

include( "std_functions.php" );
include( "connect2mysql.php" );

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

start_page("Messages", true, $logged_in, $player_row );

switch( $err )
{
 case("mysql_connect_failed"):
     {
         echo "Connection to database failed. Please wait a few minutes and test again.";
     }
     break;

 case("mysql_select_db_failed"):
     {
         echo "Couldn't select the database. Please wait a few minutes and test again. ";
     }
     break;

 case("wrong_userid"):
     {
         echo "Sorry, I don't know anyone with that userid.";
     }
     break;

 case("wrong_password"):
     {
         echo "Sorry, invalid password.";
     }
     break;

 case("not_logged_in"):
     {
         echo "Sorry, you have to be logged in to do that.";
     }
     break;

 case("no_game_nr"):
     {
         echo "Sorry, I need a game number to know what game to show.";
     }
     break;

 case("unknown_game"):
     {
         echo "Sorry, I can't find that game.";
     }
     break;

 case("not_your_turn"):
     {
         echo "Sorry, it's not your turn.";
     }
     break;

 case("not_empty"):
     {
         echo "Sorry, you may only place stones on empty points.";
     }
     break;

 case("mysql_insert_move"):
 case("mysql_update_game"):
     {
         echo "The insertion of the move into the database seems to have failed. " .
             "This may or may not be a problem, please return to the game to see " .
             "if the move has been registered.";
     }
     break;

 case("suicide"):
     {
         echo "Sorry, this stone would have killed itself, but suicide is not allowed under this ruleset.";
     }
     break;

 case("ko"):
     {
         echo "Sorry, you may not retake a stone which has just captured a stone, " . 
             "since it would repeat a previous board position. Look for 'ko' in the rules.";
     }
     break;

 case("reciever_not_found"):
     {
         echo "Sorry, couldn't find the reciever of your message. Make sure to use " .
             "the userid, not the full name.";
     }
     break;

 case("mysql_insert_message"):
     {
         echo "Sorry, the additon of the message to the database seems to have failed.";
     }
     break;
     
 case("unknown_message"):
     {
         echo "Sorry, I couldn't find the message you wanted to show.";
     }
     break;
     
 case("password_missmatch"):
     {
         echo "The confirmed password didn't match the password, please go back and retry.";
     }
     break;

 case("password_too_short"):
     {
         echo "Sorry, the password must be at least six letters.";
     }
     break;

 case("userid_in_use"):
     {
         echo "Sorry, this userid is already used, please try to find a unique userid.";
     }
     break;

 case("mysql_insert_player"):
     {
         echo "The insertion of your data into the database seems to have failed. " .
             "If you can't log in, please try once more and, if this fails, contact the support.";
     }
     break;


 default:
     {
         echo "Unknown problem. This shouldn't happen. Please send the url of this page to the support, so that this doesn't happen again.";
     }
     break;
}

end_page();

?>