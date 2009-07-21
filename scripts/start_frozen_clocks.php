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
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low');

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();

   start_html('start_frozen_clocks', 0);

echo ">>>> Should not be used now. Do not run it before a check."; end_html(); exit;
   if( $do_it=@$_REQUEST['do_it'] )
   {
      function dbg_query($s) {
        if( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
        echo " --- fixed. ";
      }
      echo "<p>*** Fixes errors ***"
         ."<br>".anchor(make_url($page, $page_args), 'Just show it')
         ."</p>";
   }
   else
   {
      function dbg_query($s) { echo " --- query:<BR>$s; ";}
      $tmp = array_merge($page_args,array('do_it' => 1));
      echo "<p>(just show needed queries)"
         ."<br>".anchor(make_url($page, $page_args), 'Show it again')
         ."<br>".anchor(make_url($page, $tmp), '[Validate it]')
         ."</p>";
   }



   $query = "SELECT Games.ID AS gid, ClockUsed, LastTicks, Clock.Ticks " .
      "FROM (Games, Clock) " .
      "WHERE Clock.ID=Games.ClockUsed " .
      "AND Games.Status" . IS_RUNNING_GAME .
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
   mysql_free_result($result);

   echo "<hr>Done!!!\n";
   end_html();
}

?>
