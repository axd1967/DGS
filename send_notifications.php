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
require( "include/board.php" );

{
   connect2mysql();

   $result = mysql_query( "SELECT ID as uid, Email, SendEmail, Lastaccess FROM Players " .
                          "WHERE SendEmail LIKE '%ON%' AND Notify='NOW'" );


   while( $row = mysql_fetch_array( $result ) )
   {
      extract($row);

      $msg = "A message or game move is waiting for you at $HOSTBASE/status.php\n";

      // Find games

      if( !(strpos($SendEmail, 'MOVE') === false) )
      {
         $query = "SELECT Games.*, " .
             "black.Name AS Blackname, " .
             "black.Handle AS Blackhandle, " .
             "white.Name AS Whitename, " .
             "white.Handle AS Whitehandle " .
             "FROM Games, Players AS black, Players AS white " .
             "WHERE ToMove_ID=$uid AND Black_ID=black.ID AND White_ID=white.ID" .
             " AND UNIX_TIMESTAMP(Lastchanged) > UNIX_TIMESTAMP('$Lastaccess')";

         $res2 = mysql_query( $query ) or die(mysql_error() . $query);

         if( mysql_num_rows($res2) > 0 )
         {
            $msg .= str_pad('', 47, '-') . "\n  Games:\n";

            while( $game_row = mysql_fetch_array( $res2 ) )
            {
               extract($game_row);

               $mess = NULL;
               make_array( $ID, $array, $mess, $Moves, NULL, $moves_result, $marked_dead );

               $msg .= str_pad('', 47, '-') . "\n";
               $msg .= "Game ID: $ID  ($HOSTBASE/game.php?gid=$ID)\n";
               $msg .= "Black: $Blackname ($Blackhandle)\n";
               $msg .= "White: $Whitename ($Whitehandle)\n";
               $msg .= "Move $Moves: " . number2board_coords($Last_X, $Last_Y, $Size) . "\n";

               if( !(strpos($SendEmail, 'BOARD') === false) )
                  $msg .= draw_ascii_board($Size, $array, $ID, $Last_X, $Last_Y, 15,
                                           make_html_safe($mess, 'game'));
            }
         }
      }


      // Find new messages

      if( !(strpos($SendEmail, 'MESSAGE') === false) )
      {
         $query = "SELECT UNIX_TIMESTAMP(Time) AS date, " .
             "Messages.*, Players.Name AS FromName, Players.Handle AS FromHandle " .
             "FROM Messages, Players " .
             "WHERE To_ID=$uid " .
             "AND Messages.Flags LIKE '%NEW%' " .
             "AND From_ID=Players.ID " .
             "AND UNIX_TIMESTAMP(Time) > UNIX_TIMESTAMP('$Lastaccess') " .
             "ORDER BY Time DESC";

         $res3 = mysql_query( $query ) or die(mysql_error() . $query);
         if( mysql_num_rows($res3) > 0 )
         {
            $msg .= str_pad('', 47, '-') . "\n  New messages:\n";
            while( $msg_row = mysql_fetch_array( $res3 ) )
            {
               extract($msg_row);

               $msg .= str_pad('', 47, '-') . "\n" .
                   "Date: " . date($date_fmt, $date) . "\n" .
                   "From: $FromName($FromHandle)\n" .
                   "Subject: " . make_html_safe($Subject) .
                   "  ($HOSTBASE/show_message.php?mid=$ID)\n\n" .
                   wordwrap(make_html_safe($Text),47) . "\n";
            }
         }
      }

      $msg .= str_pad('', 47, '-');

      mail( $Email, 'Dragon Go Server notification', $msg, "From: $EMAIL_FROM" );
   }


   mysql_query( "UPDATE Players SET Notify='DONE' " .
                "WHERE SendEmail LIKE '%ON%' AND Notify='NOW' " );

   mysql_query( "UPDATE Players SET Notify='NOW' " .
                "WHERE SendEmail LIKE '%ON%' AND Notify='NEXT' " );



// Update activities

   $factor =  exp( - M_LN2 * 30 / $ActivityHalvingTime );
   mysql_query( "UPDATE Players SET Activity=Activity * $factor" );
}
?>