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

require_once( "include/std_functions.php" );

disable_cache();

{
   connect2mysql();

   if( !$gid )
      error("no_game_nr");

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $result = mysql_query( "SELECT Games.Black_ID, Games.White_ID ".
                          "FROM Games " .
                          "WHERE Games.ID=$gid" );

   if (mysql_num_rows($result) != 1 )
      error("unknown_game");

   extract(mysql_fetch_array($result));
   
   if ($player_row["ID"] != $Black_ID and $player_row["ID"] != $White_ID)
      error("not_a_player");


   if ($player_row["ID"] == $Black_ID)
      {
      mysql_query( "UPDATE Games SET Black_notes='$notes' WHERE Games.ID=$gid");
      }
   else 
      {
      mysql_query( "UPDATE Games SET White_notes='$notes' WHERE Games.ID=$gid");
      }

   //go back to where we came from
   jump_to($refer_url);

}