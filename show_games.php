<?php
/*
Dragon Go Server
Copyright (C) 2001  Erik Ouchterlony

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
include( "include/table_columns.php" );

{
   if( !$uid )
      error("no_uid");


   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   $result = mysql_query( "SELECT Name, Handle FROM Players WHERE ID=$uid" );

   if( mysql_num_rows($result) != 1 )
      error("unknown_user");


   $user_row = mysql_fetch_array($result);

   if(!$sort1)
   {
      $sort1 = 'Lastchanged';
      $desc1 = 1;
      $sort2 = 'ID';
      $desc2 = 1;
   }

   $order = $sort1 . ( $desc1 ? ' DESC' : '' );
   if( $sort2 )
      $order .= ",$sort2" . ( $desc2 ? ' DESC' : '' );

   if( !is_numeric($from_row) or $from_row < 0 )
      $from_row = 0;

   $query = "SELECT Games.*, UNIX_TIMESTAMP(Lastchanged) AS Time, " . 
       "Players.Name, Players.Handle, Players.ID as pid, " .
       "(Black_ID=$uid)+1 AS Color ";

   if( $finished )
      $query .= ", (Black_ID=$uid AND Score<0)*2 + (White_ID=$uid AND Score>0)*2-1 AS Win ";

   $query .= "FROM Games,Players WHERE " .
       ( $finished ? "Status='FINISHED' " : "Status!='INVITED' AND Status!='FINISHED' " ) .  
       "AND (( Black_ID=$uid AND White_ID=Players.ID ) " .
       "OR ( White_ID=$uid AND Black_ID=Players.ID )) " .
       "ORDER BY $order LIMIT $from_row,$MaxRowsPerPage";

   $result = mysql_query( $query );
   
   start_page( ($finished ? "Finished" : "Running" ) . " games for " . $user_row["Name"], 
               true, $logged_in, $player_row );


   $show_rows = $nr_rows = mysql_num_rows($result);

   if( $nr_rows == $MaxRowsPerPage )
      $show_rows = $RowsPerPage;

   $finished_string = ( $finished ? 'finished=1&' : '' );

   echo "<center><h4>" . ( $finished ? "Finished" : "Running" ) . " Games for <A href=\"userinfo.php?uid=$uid\">" . $user_row["Name"] . " (" . $user_row["Handle"] . ")</A></H4></center>\n";
   echo "<table border=0 cellspacing=0 cellpadding=0 align=center>\n";
   echo "<tr><td align=left>";
   if( $from_row > 0 )
      next_prev("show_games.php?uid=$uid&$finished_string&", $from_row-$RowsPerPage, false);

   echo "</td>\n<td align=right>";

   if( $show_rows < $nr_rows )
      next_prev("show_games.php?uid=$uid&$finished_string&", $from_row+$RowsPerPage, true);

   echo "</td>\n</tr>\n<tr><td colspan=2><table border=3>\n<tr>\n" .
      tablehead('gid', 'ID', "show_games.php?uid=$uid&$finished_string", true) .
      "<th>sgf</th>\n" .
      tablehead('Opponent', 'Name', "show_games.php?uid=$uid&$finished_string") .
      tablehead('Color', 'Color', "show_games.php?uid=$uid&$finished_string") .
      tablehead('Size', 'Size', "show_games.php?uid=$uid&$finished_string", true) .
      tablehead('Handicap', 'Handicap', "show_games.php?uid=$uid&$finished_string") .
      tablehead('Komi', 'Komi', "show_games.php?uid=$uid&$finished_string");

   if( $finished )
   {
      echo "<th>Score</th>\n" .
         tablehead('win?', 'Win', "show_games.php?uid=$uid&$finished_string", true) .
         tablehead('End date', 'Lastchanged', "show_games.php?uid=$uid&$finished_string", true);
   }
   else
   {
      echo tablehead('Moves', 'Moves', "show_games.php?uid=$uid&$finished_string", true) .
         tablehead('Last move', 'Lastchanged', "show_games.php?uid=$uid&$finished_string", true);
   }

   $i=0;
   while( $row = mysql_fetch_array( $result ) )
   {
      extract($row);

      if( $Color == BLACK )
         $color = "b"; 
      else
         $color = "w"; 

      echo "<tr><td><A href=\"game.php?gid=$ID\"><font color=$gid_color><b>$ID</b></font></td>
<td><A href=\"sgf.php?gid=$ID\"><font color=$gid_color>sgf</font></td>
<td><A href=\"userinfo.php?uid=$pid\">$Name</a></td>
<td align=center><img src=\"17/$color.gif\" alt=$color></td>
<td>$Size</td>
<td>$Handicap</td>
<td>$Komi</td>
<td>" . ($finished ? score2text($Score, false) : $Moves ) . "</td>
";

      if( $finished )
      {
         $src = '"images/' . 
             ( $Win == 1 ? 'yes.gif" alt=yes' : 
             ( $Win == -1 ? 'no.gif" alt=no' : 
             'dash.gif" alt=jigo' ) ); 
         echo "<td align=center><img src=$src></td>\n";
      }

      echo "<td>" . date($date_fmt, $Time) . "</td>\n";

      echo "</tr>\n";

      if(++$i >= $show_rows) 
         break;
   }

   echo "</table>\n";

   echo "</table>\n";



   echo "
    <p>
    <table width=\"100%\" border=0 cellspacing=0 cellpadding=4>
      <tr align=\"center\">
        <td><B><A href=\"userinfo.php?uid=$uid\">User info</A></B></td>\n";
   if( $uid != $player_row["ID"] ) 
      echo "        <td><B><A href=\"invite.php?uid=$uid\">Invite this user</A></B></td>\n";

   if( $finished )
      echo "        <td><B><A href=\"show_games.php?uid=$uid\">Show running games</A></B></td>";
   else
      echo "        <td><B><A href=\"show_games.php?uid=$uid&finished=1\">Show finished games</A></B></td>";

   echo "
      </tr>
    </table>
";

   end_page(false);
}
?>
