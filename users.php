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
include( "include/rating.php" );
include( "include/table_columns.php" );

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   if(!$sort1)
      $sort1 = 'ID';

   $order = $sort1 . ( $desc1 ? ' DESC' : '' );
   if( $sort2 )
      $order .= ",$sort2" . ( $desc2 ? ' DESC' : '' );

   $result = mysql_query("SELECT *, Rank as Rankinfo FROM Players order by $order");


   start_page("Users", true, $logged_in, $player_row );


   echo "<table border=3 align=center>\n";
   echo "<tr>\n" .
      tablehead('Name','Name','users.php?') .
      tablehead('UserId','Handle','users.php?') .
      "<th>Rank info</th>" .
      tablehead('Rating','Rating','users.php?', true) .
      "<th>" . _("Open for matches?") . "</th>\n</tr>\n";

   while( $row = mysql_fetch_array( $result ) )
   {
      echo '<tr><td><A href="userinfo.php?uid=' . $row["ID"] . '">' . $row["Name"] . '</A></td>' .
         '<td><A href="userinfo.php?uid=' . $row["ID"] . '">' . $row["Handle"] . '</A></td>' .
         '<td>' . $row["Rankinfo"] . '</td><td>';     
      echo_rating( $row["Rating"] ); 
      echo '</td><td>' . $row["Open"] . '</td></tr>
';
   }

   echo "</table>\n";

   end_page();
}
?>
