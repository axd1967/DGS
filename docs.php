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

require( "include/std_functions.php" );

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

start_page("Links", true, $logged_in, $player_row );

?>

<table align=center><tr><td>

<center><h3><font color="#800000">Documentaion</font></h3></center>


<p><a href="introduction.php">Introduction to Dragon</a>

<p><a href="site_map.php">Site map</a>

<p><a href="phorum/list.php?f=3">Frequently Asked Questions</a> -- with answers

<p><a href="links.php">Links</a>

<p><a href="todo.php">To do list</a> --- plans for future improvements

<p><a href="install.php">Installation instructions</a> --- if you want your own dragon

<p><a href="snapshot">Download dragon sources</a> --- daily shapshot of the cvs

<p><a href="http://sourceforge.net/projects/dragongoserver">Dragon project page at sourceforge</a>

<p><a href="licence.php">Licence</a> --- GPL

<br>&nbsp;
</td></tr></table>

<?php
end_page();
?>
