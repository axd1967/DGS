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

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");


   start_page("Statistics", true, $logged_in, $player_row );

   $q1 = "SELECT Status,SUM(Moves) as moves, COUNT(*) as count FROM Games GROUP BY Status";
   $q2 = "SELECT SUM(Moves) as moves, COUNT(*) as count FROM Games";
   $q3 = "SELECT SUM(Hits) as hits, Count(*) as count, sum(Activity) as activity FROM Players";

   $result = mysql_query( $q1 );

   echo '<table border=1>
<tr><th>Status</th><th>Moves</th><th>Games</th><tr>
';

   while( $row = mysql_fetch_array( $result ) )
   {
      echo '<tr><td>' . $row["Status"] . '</td><td>' . $row["moves"] . '</td><td>' . $row["count"] . '</td></tr>
';
   }

   $result = mysql_query( $q2 );
   $row = mysql_fetch_array( $result );

   echo '<tr><td>Total</td><td>' . $row["moves"] . '</td><td>' . $row["count"] . '</td></tr>
</table>
';


   $result = mysql_query( $q3 );
   $row = mysql_fetch_array( $result );

   echo '<p>' . $row["hits"] . ' hits by ' . $row["count"] . ' players';
   echo '<p>Activity: ' . round($row['activity']);

   end_page();
}
?>