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

$table_columns = array('ID','Name','Nick','Rank Info','Rating','Open for matches?','Games',
                       'Running','Finished','Won','Lost','Percent','Activity');

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $column_set = $player_row["UsersColumns"];
   $page = "users.php?";

   add_or_del($add, $del, "UsersColumns");

   if(!$sort1)
      $sort1 = 'ID';

   $order = $sort1 . ( $desc1 ? ' DESC' : '' );
   if( $sort2 )
      $order .= ",$sort2" . ( $desc2 ? ' DESC' : '' );

   $query = "SELECT *, Rank AS Rankinfo, " .
       "(Activity>$ActiveLevel1)+(Activity>$ActiveLevel2) AS ActivityLevel, " .
       "Running+Finished AS Games, " .
       "100*Won/Finished AS Percent, " .
       "IFNULL(UNIX_TIMESTAMP(Lastaccess),0) AS lastaccess, " .
       "IFNULL(UNIX_TIMESTAMP(LastMove),0) AS Lastmove " .
       "FROM Players ORDER BY $order";

   $result = mysql_query( $query );


   start_page("Users", true, $logged_in, $player_row );



   echo start_end_column_table(true) .
      tablehead(1, 'ID', 'ID') .
      tablehead(2, 'Name', 'Name') .
      tablehead(3, 'Nick', 'Handle') .
      tablehead(4, 'Rank Info') .
      tablehead(5, 'Rating', 'Rating', true) .
      tablehead(6, 'Open for matches?') . 
      tablehead(7, 'Games', 'Games', true) .
      tablehead(8, 'Running', 'Running', true) .
      tablehead(9, 'Finished', 'Finished', true) .
      tablehead(10, 'Won', 'Won', true) .
      tablehead(11, 'Lost', 'Lost', true) .
      tablehead(12, 'Percent', 'Percent', true) .
      tablehead(13, 'Activity', 'ActivityLevel', true) .
      tablehead(14, 'Last Access', 'Lastaccess', true) .
      tablehead(15, 'Last Moved', 'Lastmove', true) .
      "</tr>\n";

   $row_color=2;
   while( $row = mysql_fetch_array( $result ) )
   {
      $ID = $row['ID'];
      $percent = ( $row["Finished"] == 0 ? '' : round($row["Percent"]). '%' );
      $a = $row['ActivityLevel'];
      $activity = ( $a == 0 ? '' : 
                    ( $a == 1 ? '<img align=middle alt="*" src=images/star2.gif>' : 
                      '<img align=middle alt="*" src=images/star.gif>' .
                      '<img align=middle alt="*" src=images/star.gif>' ) );

      $lastaccess = ($row["lastaccess"] > 0 ? date($date_fmt2, $row["lastaccess"]) : NULL );
      $lastmove = ($row["Lastmove"] > 0 ? date($date_fmt2, $row["Lastmove"]) : NULL );

      $row_color=3-$row_color;
      echo "<tr bgcolor=" . ${"table_row_color$row_color"} . ">\n" .
         tableelement(1, 'ID', "<A href=\"userinfo.php?uid=$ID\">$ID</A>") .
         tableelement(2, 'Name', "<A href=\"userinfo.php?uid=$ID\">" . $row['Name'] . "</A>") .
         tableelement(3, 'Nick', "<A href=\"userinfo.php?uid=$ID\">" . $row['Handle'] . "</A>") .
         tableelement(4, 'Rank Info', $row['Rankinfo']) .
         tableelement(5, 'Rating', echo_rating($row['Rating'])) .
         tableelement(6, 'Open for matches?', $row['Open']) .
         tableelement(7, 'Games', $row["Games"]) .
         tableelement(8, 'Running', $row["Running"]) .
         tableelement(9, 'Finished', $row["Finished"]) .
         tableelement(10, 'Won', $row["Won"]) .
         tableelement(11, 'Lost', $row["Lost"]) .
         tableelement(12, 'Percent', $percent) .
         tableelement(13, 'Activity', $activity) .
         tableelement(14, 'Last Access', $lastaccess) .
         tableelement(15, 'Last Moved', $lastmove) .
         "</tr>\n";
   }

   echo start_end_column_table(false);

   end_page();
}
?>
