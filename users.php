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


require( "include/std_functions.php" );
include( "include/rating.php" );
include( "include/table_columns.php" );
include( "include/timezones.php" );

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $column_set = explode(',', $player_row["UsersColumns"]);
   $page = "users.php?";

   if( $del or $add )
   {
      if( $add )
         array_push($column_set,$add);
      if( $del and is_integer($s=array_search($del, $column_set, true)) )
         array_splice($column_set, $s, 1);

      $query = "UPDATE Players " . 
          "SET UsersColumns='" . implode(',', $column_set) . "' " .
          "WHERE ID=" . $player_row["ID"];
      
      mysql_query($query);

   }

   if(!$sort1)
      $sort1 = 'ID';

   $order = $sort1 . ( $desc1 ? ' DESC' : '' );
   if( $sort2 )
      $order .= ",$sort2" . ( $desc2 ? ' DESC' : '' );

   $query = "SELECT *, Rank AS Rankinfo, " .
       "(Activity>$ActiveLevel1)+(Activity>$ActiveLevel2) AS ActivityLevel, " .
       "Running+Finished AS Games, " .
       "100*Won/Finished AS Percent " .
       "FROM Players ORDER BY $order";

   $result = mysql_query( $query );


   start_page("Users", true, $logged_in, $player_row );



   echo start_end_column_table(true) .
      tablehead('ID', 'ID') .
      tablehead('Name', 'Name') .
      tablehead('Nick', 'Handle') .
      tablehead('Rank Info') .
      tablehead('Rating', 'Rating', true) .
      tablehead('Open for matches?') . 
      tablehead('Games', 'Games', true) .
      tablehead('Running', 'Running', true) .
      tablehead('Finished', 'Finished', true) .
      tablehead('Won', 'Won', true) .
      tablehead('Lost', 'Lost', true) .
      tablehead('Percent', 'Percent', true) .
      tablehead('Activity', 'ActivityLevel', true) .
      "</tr>\n";

   while( $row = mysql_fetch_array( $result ) )
   {
      $ID = $row['ID'];
      $percent = ( $row["Finished"] == 0 ? '' : round($row["Percent"]). '%' );
      $a = $row['ActivityLevel'];
      $activity = ( $a == 0 ? '' : 
                    ( $a == 1 ? '<img align=middle src=images/star2.gif>' : 
                      '<img align=middle src=images/star.gif>' .
                      '<img align=middle src=images/star.gif>' ) );

      echo "<tr>\n" .
         tableelement('ID', "<A href=\"userinfo.php?uid=$ID\">$ID</A>") .
         tableelement('Name', "<A href=\"userinfo.php?uid=$ID\">" . $row['Name'] . "</A>") .
         tableelement('Nick', "<A href=\"userinfo.php?uid=$ID\">" . $row['Handle'] . "</A>") .
         tableelement('Rank Info', $row['Rankinfo']) .
         tableelement('Rating', echo_rating($row['Rating'])) .
         tableelement('Open for matches?', $row['Open']) .
         tableelement('Games', $row["Games"]) .
         tableelement('Running', $row["Running"]) .
         tableelement('Finished', $row["Finished"]) .
         tableelement('Won', $row["Won"]) .
         tableelement('Lost', $row["Lost"]) .
         tableelement('Percent', $percent) .
         tableelement('Activity', $activity) .
         "</tr>\n";
   }

   echo start_end_column_table(false);

   end_page();
}
?>
