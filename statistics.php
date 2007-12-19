<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

$TranslateGroups[] = "Docs";

require_once( "include/std_functions.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");


   start_page("Statistics", true, $logged_in, $player_row );

   $q1 = "SELECT Status,SUM(Moves) as moves, COUNT(*) as count FROM Games GROUP BY Status";
   $q2 = "SELECT SUM(Moves) as moves, COUNT(*) as count FROM Games";
   $q3 = "SELECT SUM(Hits) as hits, Count(*) as count, sum(Activity) as activity FROM Players";

   $result = mysql_query( $q1 );

   echo '<table border=1>
<tr><th>Status</th><th>Moves</th><th>Games</th></tr>
';

   while( $row = mysql_fetch_array( $result ) )
   {
      echo '<tr><td>' . $row["Status"] . '</td><td align="right">' . $row["moves"] . '</td><td align="right">' . $row["count"] . '</td></tr>
';
   }
   mysql_free_result($result);

   $row = mysql_single_fetch( 'statistics.q2', $q2 );
   if( $row )
   {
      echo '<tr><td>Total</td><td align="right">' . $row["moves"] 
         . '</td><td align="right">' . $row["count"] . "</td></tr>\n";
   }
   echo "</table>\n";

   $row = mysql_single_fetch( 'statistics.q3', $q3 );
   if( $row )
   {
      echo '<p></p>' . $row["hits"] . ' hits by ' . $row["count"] . ' players';
      echo '<p></p>Activity: ' . round($row['activity']) . "\n";
   }

   //echo '<p></p>Loadavg: ' . `cat /proc/loadavg`; //only under Linux like systems and with safe_mode=off
   $tmp = '/proc/loadavg';
   if( @is_readable( $tmp ) )
   {
      $tmp = trim(implode('', file($tmp)));
      echo '<p></p>Loadavg: ' . $tmp;
   }

   $forcecache = floor($NOW/86400); //to force the caches (daily)
   
   $title = T_('Statistics graph');
   echo "<h3 class=Header>$title</h3>\n";
   echo "<img src=\"statisticspng.php?date=$forcecache" .
        (@$_REQUEST['show_time'] == 'y' ? URI_AMP.'show_time=y' : '') .
        "\" alt=\"$title\">\n";

   $title = T_('Rating histogram');
   echo "<h3 class=Header>$title</h3>\n";
   echo "<img src=\"statratingspng.php?date=$forcecache" .
        (@$_REQUEST['show_time'] == 'y' ? URI_AMP.'show_time=y' : '') .
        "\" alt=\"$title\">\n";

   end_page();
}
?>
