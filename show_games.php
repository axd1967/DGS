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
include( "include/table_columns.php" );
include( "include/timezones.php" );

{
   if( !$uid )
      error("no_uid");


   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $column_set = explode(',', $player_row["GamesColumns"]);
   $finished_string = ( $finished ? 'finished=1&' : '' );
   $page = "show_games.php?uid=$uid&$finished_string";

   if( $del or $add )
   {
      if( $add )
         array_push($column_set,$add);
      if( $del and is_integer($s=array_search($del, $column_set, true)) )
         array_splice($column_set, $s, 1);

      $query = "UPDATE Players " . 
          "SET GamesColumns='" . implode(',', $column_set) . "' " .
          "WHERE ID=" . $player_row["ID"];
      
      mysql_query($query);

   }

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



   echo "<center><h4>" . ( $finished ? "Finished" : "Running" ) . " Games for <A href=\"userinfo.php?uid=$uid\">" . $user_row["Name"] . " (" . $user_row["Handle"] . ")</A></H4></center>\n";



   echo start_end_column_table(true) .
      tablehead('ID', 'ID', true) .
      tablehead('sgf', "show_games.php?uid=$uid&$finished_string") .
      tablehead('Opponent', 'Name') .
      tablehead('Color', 'Color') .
      tablehead('Size', 'Size', true) .
      tablehead('Handicap', 'Handicap') .
      tablehead('Komi', 'Komi') .
      tablehead('Moves', 'Moves', true);

   if( $finished )
   {
      echo tablehead('Score') .
         tablehead('Win?', 'Win', true) .
         tablehead('End date', 'Lastchanged', true);
   }
   else
   {
      echo tablehead('Last Move', 'Lastchanged', true);
   }

   echo "</tr>\n";

   $i=0;
   while( $row = mysql_fetch_array( $result ) )
   {
      extract($row);
      $color = ( $Color == BLACK ? 'b' : 'w' ); 

      echo "<tr>\n" .
         tableelement('ID', "<A href=\"game.php?gid=$ID\"><font color=$gid_color><b>" .
                      "$ID</b></font></A>") .
         tableelement('sgf', "<A href=\"sgf.php?gid=$ID\"><font color=$gid_color>" .
                      "sgf</font></A>") .
         tableelement('Opponent', "<A href=\"userinfo.php?uid=$pid\">$Name</a>") .
         tableelement('Color', "<img align=middle src=\"17/$color.gif\" alt=$color>") .
         tableelement('Size', $Size) .
         tableelement('Handicap', $Handicap) .
         tableelement('Komi', $Komi) .
         tableelement('Moves', $Moves );

      if( $finished )
      {
         $src = '"images/' . 
             ( $Win == 1 ? 'yes.gif" alt=yes' : 
               ( $Win == -1 ? 'no.gif" alt=no' : 
                 'dash.gif" alt=jigo' ) );

         echo tableelement('Score', score2text($Score, false)) .
            tableelement('Win?', "<img align=middle src=$src>") .
            tableelement('End date', date($date_fmt, $Time));
      }
      else
      {
         echo tableelement('Last Move', date($date_fmt, $Time));
      }

      echo "</tr>\n";

      if(++$i >= $show_rows) 
         break;
   }

   echo start_end_column_table(false);



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
