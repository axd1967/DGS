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

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

if( !$logged_in )
{
   header("Location: error.php?err=not_logged_in");
   exit;
}

if( $player_row["Handle"] == "guest" )
{
    header("Location: error.php?err=not_allowed_for_guest");
    exit;    
}

if( $action == "Change profile" )
{

    if( strlen( $name ) < 1 )
        {
            header("Location: error.php?err=name_not_given");
            exit;
        }

    $query = "UPDATE Players SET " .
         "Name='$name', " .
         "Email='$email', " .
         "Rank='$rank', " .
         "Stonesize=$stonesize, " .
         "Boardfontsize='$boardfontsize', " .
         "Sessionexpire=" . $player_row["Sessionexpire"] .
         " WHERE ID=" . $player_row["ID"]; 
    
    mysql_query( $query );

    if( mysql_affected_rows() != 1 )
    {
        header("Location: error.php?err=mysql_update_player");
        exit;
    }

    start_page("Profile updated", true, $logged_in, $player_row );

    echo "Profile updated!\n";

    end_page();
    exit();
}
else if( $action == "Change password" )
{
   if( $passwd != $passwd2 )
       {
           header("Location: error.php?err=password_missmatch");
           exit;
       }
   else if( strlen($passwd) < 6 )
       {
           header("Location: error.php?err=password_too_short");
           exit;
       } 
   
   $query = "UPDATE Players SET " .
        "Password=PASSWORD('$passwd'), " .
         "Sessionexpire=" .$player_row["Sessionexpire"] .
        "WHERE ID=" . $player_row["ID"];    

   mysql_query( $query );
   
   if( mysql_affected_rows() != 1 )
       {
           header("Location: error.php?err=mysql_update_player");
           exit;
       }
   
   start_page("Password changed", true, $logged_in, $player_row );
   
   echo "Password changed!\n";
   
   end_page();
   exit();   
}
else
{
    header("Location: error.php?err=no_action");
    exit;
}