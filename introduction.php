<?php
/*
Dragon Go Server
Copyright (C) 2001 Erik Ouchterlony

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

start_page("Introduction", true, $logged_in, $player_row );

?>

<table align=center width=85%><tr><td>
<center><h3><font color=<?php echo $h3_color; ?>>Introduction to dragon</font></h3></center>

Welcome to Dragon Go Server, a <a href="licence.php">free</a> server for playing <a
href="links.php">go</a>, where the games tends to 'drag on'.

<p> You can look at it as kind of play-by-email, where a web-interface is used to make the
board look prettier. To start playing you should first get yourself an <a
href="register.php">account</a>, if you haven't got one already. Thereafter you could <a
href="edit_profile.php">edit your profile</a> and <a href="edit_bio.php">enter some
biographical info</a>, especially the fields 'Open for matches?', 'Rating' and 'Rank info'
are useful for finding opponents. Next you can study the <a href="users.php">user list</a>
and use the <a href="phorum/index.php">forums</a> to find suitable opponents to <a
href="invite.php">invite</a> for a game.

<p> More information can be found in the <a href="phorum/list.php?f=3">FAQ forum</a> where you
are also encouraged to submit your own questions.

<p> Once again welcome, and enjoy your visit here!




</td></tr></table>

<?php
end_page();
?>
