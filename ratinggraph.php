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

$TranslateGroups[] = "Game";

require( "include/std_functions.php" );

{
  connect2mysql();

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

  if( !($uid > 0) )
  {
     if( eregi("uid=([0-9]+)", $HTTP_REFERER, $result) )
        $uid = $result[1];
  }

  if( !($uid > 0) )
     error("no_uid");

  $result = mysql_query("SELECT Name FROM Players where ID=$uid");

  if( mysql_num_rows($result) != 1 )
     error("unknown user");

  $row = mysql_fetch_array($result);

  $title =  T_('Rating graph for') . ' ' . "<A href=\"userinfo.php?uid=$uid\">" .
     $row['Name'] . "</A>";

  start_page($title, true, $logged_in, $player_row );

  echo '<center>';
  echo "<h3><font color=$h3_color>$title</font></h3><p>\n";

  $result = mysql_query("SELECT Rating FROM Ratinglog WHERE uid=$uid LIMIT 2");

  if( mysql_num_rows($result) < 2 )
     echo T_("Sorry, too few rated games to draw a graph") . "\n";
  else
     echo '<img src="ratingpng.php?uid=' . $uid .
        ($_GET['show_time'] == 'y' ? '&show_time=y' : '') . "\">\n";

  echo '</center>';

  end_page();
}

?>
