<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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


   start_html( 'game_consistency', 0);

      echo "<p>--- Report only:<br>";

   if( ($gid=@$_REQUEST['gid']) > 0 )
      $where = " AND ID>=$gid";
   else
      $where = "";

   if( ($lim=@$_REQUEST['limite']) > 0 )
      $limit = " LIMIT $lim";
   else
      $limit = "";

   //$since may be: "2 DAY", "12 MONTH", ...
   if( ($since=@$_REQUEST['since']) )
      $where.= " AND DATE_ADD(Lastchanged,INTERVAL $since) > FROM_UNIXTIME($NOW)";


   $query = "SELECT ID"
      . " FROM Games WHERE Status!='INVITED'$where ORDER BY ID$limit";

   echo "\n<p>query: $query;\n";
   $result = mysql_query($query);

   $n= (int)@mysql_num_rows($result);
   echo "\n<br>=&gt; result: $n rows<p>\n";

   if( $n > 0 )
   while( $row = mysql_fetch_assoc( $result ) )
   {
      //echo ' ' . $row["ID"];
      check_consistency($row["ID"]);
   }

   end_html();
}
?>