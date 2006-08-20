<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

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

// Checks and show errors in the Games database.

chdir( '../' );
require_once( "include/std_functions.php" );
require_once( "include/board.php" );
require_once( "include/move.php" );

{
   disable_cache();

   connect2mysql();

  $logged_in = who_is_logged( $player_row);

  if( !$logged_in )
    error("not_logged_in");

  $player_level = (int)$player_row['admin_level'];
  if( !($player_level & ADMIN_DATABASE) )
    error("adminlevel_too_low");

   start_html('start_frozen_clocks', 0);

   if( $do_it=@$_REQUEST['do_it'] )
   {
      function dbg_query($s) {
        if( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
        echo " --- fixed. ";
      }
      echo "<p></p>*** Fixes errors:<br>";
   }
   else
   {
      function dbg_query($s) { echo " --- query:<BR>$s; ";}
      echo "<p></p>(just show queries needed):<br>";
   }




   $query = "SELECT Games.ID AS gid, ClockUsed, LastTicks, Clock.Ticks " .
      "FROM Games, Clock " .
      "WHERE Clock.ID=Games.ClockUsed " .
      "AND Games.Status!='FINISHED' AND Games.Status!='INVITED' " .
      "HAVING Clock.Ticks < LastTicks";

   $result = mysql_query($query);

   $n= (int)@mysql_num_rows($result);
   echo "\n<br>=&gt; result: $n rows<p></p>\n";

   if( $n > 0 )
   while( $row = mysql_fetch_assoc( $result ) )
   {
      echo '<hr>Game_ID: ' . $row["gid"] . ": &nbsp; " .
         "ClockUsed: " . $row['ClockUsed'] .
         ", LastTicks: " . $row['LastTicks'] .
         ", Clock.Ticks: " . $row['Ticks'] .
         "<br>\n";
      dbg_query("UPDATE Games " .
                "SET LastTicks=" . $row['Ticks'] . " " .
                "WHERE ID=" . $row['gid'] . " LIMIT 1");
   }

   echo "<hr>Done!!!\n";

   end_html();
}

?>