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

header ("Cache-Control: no-cache, must-revalidate, max_age=0"); 

require( "include/std_functions.php" );

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

start_page("FAQ", true, $logged_in, $player_row );


$result = mysql_query("SELECT * FROM FAQ");

echo "<H4>Questions:</H4>\n";
while( $row = mysql_fetch_array( $result ) )
{
    echo '<P><A href="#q' . $row["ID"] . '">' . $row["Question"] . "</A>\n";
}

mysql_data_seek($result, 0);

echo "<HR><H4>Answers:</H4>\n";
while( $row = mysql_fetch_array( $result ) )
{
    echo '<HR><A name="q' . $row["ID"] . '">' . $row["Question"] .
        "</A><UL><LI>\n<p>" . $row["Answer"] . "</UL>\n";
}

end_page();
?>
    