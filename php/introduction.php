<?php
/*
Dragon Go Server
Copyright (C) 2001  Jim Heiney and Erik Ouchterlony

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

start_page("Links", true, $logged_in, $player_row );

?>

<p align="left">&nbsp;</p>
<h3><font color="#800000">Introduction to dragon</font></h3>

Sorry, nothing here yet.

<p align="left">&nbsp;</p>

<?php
end_page();
?>
