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
include( "include/form_functions.php" );

$table_columns = array('ID','sgf','Opponent','Nick','Color','Size','Handicap','Komi',
                       'Moves','Score','Win?','End date','Last Move');


{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");


   $my_id = $player_row["ID"];

   $button_nr = $player_row["Button"];

   if ( !is_numeric($button_nr) or $button_nr < 0 or $button_nr > $button_max  )
      $button_nr = 0;

   $style = 'a.button { color : ' . $buttoncolors[$button_nr] .
      ';  font : bold 100% sans-serif;  text-decoration : none;  width : 90px; }
td.button { background-image : url(images/' . $buttonfiles[$button_nr] . ');' .
      'background-repeat : no-repeat;  background-position : center; }';


   start_page("Status", true, $logged_in, $player_row, $style );

   echo "<center>";

   if( $msg )
      echo "<p><b><font color=green>$msg</font></b><hr>";

   $column_set = $player_row["GamesColumns"];
   $page = "status.php?";

   add_or_del($add, $del, "GamesColumns");

   echo '
    <table border=3>
       <tr><td><b>' . T_("Name") . '</b></td> <td>' . $player_row["Name"] . '</td></tr>
       <tr><td><b>' . T_("Userid") . '</b></td> <td>' . $player_row["Handle"] . '</td></tr>
       <tr><td><b>' . T_("Open for matches?") . '</b></td> <td>' . $player_row["Open"] . '</td></tr>
       <tr><td><b>' . T_("Rating") . '</b></td> <td>' . echo_rating($player_row["Rating"]);
   echo '</td></tr>
       <tr><td><b>' . T_('Rank info') . '</b></td> <td>' . $player_row["Rank"] . '</td></tr>
    </table>
    <p>';


   $result = mysql_query("SELECT UNIX_TIMESTAMP(Messages.Time) AS date, " .
                         "Messages.*, Players.Name AS sender " .
                         "FROM Messages, Players " .
                         "WHERE To_ID=$my_id " .
                         "AND (Messages.Flags LIKE '%NEW%' OR Messages.Flags LIKE '%REPLY REQUIRED%') " .
                         "AND From_ID=Players.ID " .
                         "ORDER BY Time DESC") or error( "mysql_query_failed", true );


   if( mysql_num_rows($result) > 0 )
   {
      echo "<HR><B>New messages:</B><p>\n";
      echo "<table border=3>\n";
      echo "<tr><th></th><th>From</th><th>Subject</th><th>Date</th></tr>\n";



      while( $row = mysql_fetch_array( $result ) )
      {
         echo "<tr>";

         if( !(strpos($row["Flags"],'NEW') === false) )
         {
            echo "<td bgcolor=\"00F464\">New</td>\n";
         }
         else if( !(strpos($row["Flags"],'REPLY REQUIRED') === false) )
         {
            echo "<td bgcolor=\"FFA27A\">Reply!</td>\n";
         }
         else
         {
            error("message_status_corrupt");
         }

         echo "<td><A href=\"message.php?mode=ShowMessage&mid=" . $row["ID"] . "\">" .
            $row["sender"] . "</A></td>\n" .
            "<td>" . make_html_safe($row["Subject"]) . "</td>\n" .
            "<td>" . date($date_fmt2, $row["date"]) . "</td></tr>\n";
      }

      echo "</table><p>\n";
   }



   // show games;

   $uid = $player_row["ID"];

   $query = "SELECT Black_ID,White_ID,Games.ID,Size,Handicap,Komi,Games.Moves," .
       "UNIX_TIMESTAMP(Lastchanged) AS Time, " .
       "(White_ID=$uid)+1 AS Color, " .
       "opponent.Name, opponent.Handle, opponent.ID AS pid " .
       "FROM Games,Players AS opponent " .
       "WHERE ToMove_ID=$uid AND Status!='INVITED' AND Status!='FINISHED' " .
       "AND (opponent.ID=Black_ID OR opponent.ID=White_ID) AND opponent.ID!=$uid " .
       "ORDER BY LastChanged, Games.ID";

   $result = mysql_query( $query ) or die(mysql_error());

   echo "<hr><b>" . T_("Your turn to move in the following games:") . "</b><p>\n";

   if( mysql_num_rows($result) == 0 )
   {
      echo T_("No games found");
   }
   else
   {
      echo start_end_column_table(true) .
         tablehead(1, 'ID', NULL, NULL, true) .
         tablehead(2, 'sgf') .
         tablehead(3, 'Opponent') .
         tablehead(4, 'Nick') .
         tablehead(5, 'Color') .
         tablehead(6, 'Size') .
         tablehead(7, 'Handicap') .
         tablehead(8, 'Komi') .
         tablehead(9, 'Moves') .
         tablehead(13, 'Last Move') .
         "</tr>\n";

      $row_color=2;
      while( $row = mysql_fetch_array( $result ) )
      {
         extract($row);
         $color = ( $Color == BLACK ? 'b' : 'w' );


         $row_color=3-$row_color;




         echo "<tr bgcolor=" . ${"table_row_color$row_color"} . ">\n";

         if( (1 << 0) & $column_set )
            echo "<td class=button width=92 align=center><A class=button href=\"game.php?gid=$ID\">&nbsp;&nbsp;&nbsp;$ID&nbsp;&nbsp;&nbsp;</A></td>\n";
         if( (1 << 1) & $column_set )
            echo "<td><A href=\"sgf.php?gid=$ID\"><font color=$gid_color>sgf</font></A></td>\n";
         if( (1 << 2) & $column_set )
            echo "<td><A href=\"userinfo.php?uid=$pid\"><font color=black>$Name</font></a></td>\n";
         if( (1 << 3) & $column_set )
            echo "<td><A href=\"userinfo.php?uid=$pid\"><font color=black>$Handle</font></a></td>\n";
         if( (1 << 4) & $column_set )
            echo "<td align=center><img src=\"17/$color.gif\" alt=$color></td>\n";
         if( (1 << 5) & $column_set )
            echo "<td>$Size</td>\n";
         if( (1 << 6) & $column_set )
            echo "<td>$Handicap</td>\n";
         if( (1 << 7) & $column_set )
            echo "<td>$Komi</td>\n";
         if( (1 << 8) & $column_set )
            echo "<td>$Moves</td>\n";
         if( (1 << 12) & $column_set )
            echo '<td>' . date($date_fmt, $Time) . "</td>\n";

         echo "</tr>\n";
      }
      echo start_end_column_table(false);
   }
   echo "</center>";


   echo "
    <p>
    <table width=\"100%\" border=0 cellspacing=0 cellpadding=4>
      <tr align=\"center\">
        <td><B><A href=\"userinfo.php?uid=$uid\">" . T_("Show/edit userinfo") . "</A></B></td>
        <td><B><A href=\"show_games.php?uid=$uid\">" . T_("Show running games") . "</A></B></td>
        <td><B><A href=\"show_games.php?uid=$uid&finished=1\">" . T_("Show finished games") . "</A></B></td>
      </tr>
    </table>
";

   end_page(false);
}
?>
