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

require( "include/std_functions.php" );
require( "include/rating.php" );

{
   disable_cache();

   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   if( $player_row["Handle"] == "guest" )
      error("not_allowed_for_guest");

   if( $passwd != $passwd2 )
   {
      error("password_missmatch");
   }
   else if( strlen($passwd) < 6 )
   {
      error("password_too_short");
   } 

   $query = "UPDATE Players SET " .
       "Password=PASSWORD('$passwd') " .
       "WHERE ID=" . $player_row['ID'];    

   mysql_query( $query );

   $msg = urlencode("Password changed!");

   header("Location: userinfo.php?uid=" . $player_row["ID"] . "&msg=$msg");
   exit;
}
?>