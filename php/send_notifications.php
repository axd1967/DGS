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

connect2mysql();

$result = mysql_query( "SELECT Email FROM Players " .
                       "WHERE Flags LIKE '%WANT_EMAIL%' AND Notify='NOW'" );





while( $row = mysql_fetch_array( $result ) )
{
    mail( $row['Email'], 
         'Dragon Go Server notification', 
         'A message or game move is waiting for you at ' . 
          $HOSTBASE . '/status.php',
          'From: ' . $EMAIL_FROM );
}


$result = mysql_query( "UPDATE Players SET Notify='DONE' " .
                       "WHERE Flags LIKE '%WANT_EMAIL%' AND Notify='NOW' " );

$result = mysql_query( "UPDATE Players SET Notify='NOW' " .
                       "WHERE Flags LIKE '%WANT_EMAIL%' AND Notify='NEXT' " );
                       
