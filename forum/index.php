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


include("forum_functions.php");

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

start_page("Forum list", true, $logged_in, $player_row );

$result = mysql_query("SELECT Forums.ID,Description,Name, " .
                      "UNIX_TIMESTAMP(MAX(Lastchanged)) AS Timestamp,Count(*) AS Count " .
                      "FROM Posts,Forums " .
                      "GROUP BY Posts.Forum_ID");

$cols = 3;
$headline   = array("Forums" => "colspan=$cols");


start_table($headline, $links, "width=98%", $cols);


while( $row = mysql_fetch_array( $result ) )
{
   extract($row);

   echo '<tr><td width="60%"><b>&nbsp;<a href="list.php?forum=' . $ID . '">' . $Name .
      '</a></b></td>' .
      '<td nowrap>Posts: <b>' . $Count .  '&nbsp;&nbsp;&nbsp;</b></td>' .
      '<td nowrap>Last Post: <b>' . date($date_fmt, $Timestamp) . '</b></td></tr>

<tr bgcolor=white><td colspan=3><dl><dt><dd>&nbsp;' . $Description . '</dl></td></tr>';   
}

end_table($links, $cols);

end_page();
?>