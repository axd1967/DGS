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
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");


   $order_list = array('ID', 'ID DESC', 
                       'Rating', 'Rating DESC', 
                       'Name', 'Name DESC', 
                       'Handle', 'Handle DESC');

   if( !in_array($order, $order_list) )
      $order = 'ID';


   $result = mysql_query("SELECT *, Rank as Rankinfo FROM Players order by $order");


   start_page("Users", true, $logged_in, $player_row );


   echo "<table border=3 align=center>\n";
   echo "<tr>" .
      "<th><A href=\"users.php?order=Name" . ($order=='Name'? '+DESC' : '') . "\">" . _("Name") . "</A></th>" .
      "<th><A href=\"users.php?order=Handle" . ($order=='Handle'? '+DESC' : '') . 
      "\">" . _("Userid") . "</A></th>" .
      "<th>Rank info</th>" .
      "<th width=14>&nbsp;&nbsp;&nbsp;&nbsp;<A href=\"users.php?order=Rating" . 
      ($order=='Rating DESC'? '' : '+DESC') . "\">" . _("Rating") . "</A>&nbsp;&nbsp;&nbsp;&nbsp;</th>" .
      "<th>" . _("Open for matches?") . "</th></tr>\n";



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
