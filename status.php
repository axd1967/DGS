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
require( "include/rating.php" );
require( "include/table_columns.php" );
require( "include/form_functions.php" );


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


   start_page(T_('Status'), true, $logged_in, $player_row, $style );

   echo "<center>";

   if( $msg )
      echo "<p><b><font color=\"green\">$msg</font></b><hr>";

   $column_set = $player_row["GamesColumns"];
   $page = "status.php?";

   add_or_del($add, $del, "GamesColumns");

   echo '
    <table border=3>
       <tr><td><b>' . T_("Name") . '</b></td>
           <td>' . make_html_safe($player_row["Name"]) . '</td></tr>
       <tr><td><b>' . T_("Userid") . '</b></td>
           <td>' . make_html_safe($player_row["Handle"]) . '</td></tr>
       <tr><td><b>' . T_("Open for matches?") . '</b></td>
           <td>' . make_html_safe($player_row["Open"], true) . '</td></tr>
       <tr><td><b>' . T_("Rating") . '</b></td>
           <td>' . echo_rating($player_row["Rating"]) .  '</td></tr>
       <tr><td><b>' . T_('Rank info') . '</b></td>
           <td>' . make_html_safe($player_row["Rank"], true) . '</td></tr>
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
      echo "<HR><font color=$h3_color><B>" . T_('New messages') . ":</B></font><p>\n";

      echo start_end_column_table(true);
      echo tablehead(1, T_('Flags'), NULL, true, true);
      echo tablehead(1, T_('From'), NULL, false, true);
      echo tablehead(1, T_('Subject'), NULL, false, true);
      echo tablehead(1, T_('Date'), NULL, true, true);
      echo "</tr>\n";

      $row_color=2;
      while( $row = mysql_fetch_array( $result ) )
      {
         $row_color=3-$row_color;
         $bgcolor = ${"table_row_color$row_color"};

         echo "<tr bgcolor=$bgcolor>";

         if( !(strpos($row["Flags"],'NEW') === false) )
         {
            echo '<td bgcolor="#00F464">' . T_('New') . "</td>\n";
         }
         else if( !(strpos($row["Flags"],'REPLY REQUIRED') === false) )
         {
            echo '<td bgcolor="#FFA27A">' . T_('Reply!') . "</td>\n";
         }
         else
         {
            error("message_status_corrupt");
         }

         echo "<td><A href=\"message.php?mode=ShowMessage&amp;mid=" . $row["ID"] . "\">" .
            make_html_safe($row["sender"]) . "</A></td>\n" .
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
       "opponent.Name, opponent.Handle, opponent.Rating, opponent.ID AS pid " .
       "FROM Games,Players AS opponent " .
       "WHERE ToMove_ID=$uid AND Status!='INVITED' AND Status!='FINISHED' " .
       "AND (opponent.ID=Black_ID OR opponent.ID=White_ID) AND opponent.ID!=$uid " .
       "ORDER BY LastChanged, Games.ID";

   $result = mysql_query( $query ) or die(mysql_error());

   echo "<hr><font color=$h3_color><b>" .
      T_("Your turn to move in the following games:") . "</b></font><p>\n";

   if( mysql_num_rows($result) == 0 )
   {
      echo T_("No games found");
   }
   else
   {
      echo start_end_column_table(true) .
         tablehead(1, T_('ID'), NULL, NULL, true) .
         tablehead(2, T_('sgf')) .
         tablehead(3, T_('Opponent')) .
         tablehead(4, T_('Nick')) .
         tablehead(16, T_('Rating')) .
         tablehead(5, T_('Color')) .
         tablehead(6, T_('Size')) .
         tablehead(7, T_('Handicap')) .
         tablehead(8, T_('Komi')) .
         tablehead(9, T_('Moves')) .
         tablehead(13, T_('Last Move')) .
         "</tr>\n";

      $row_color=2;
      while( $row = mysql_fetch_array( $result ) )
      {
         $Rating=NULL;
         extract($row);
         $color = ( $Color == BLACK ? 'b' : 'w' );


         $row_color=3-$row_color;




         echo "<tr bgcolor=" . ${"table_row_color$row_color"} . ">\n";

         echo "<td class=button width=92 align=center><A class=button href=\"game.php?gid=$ID\">&nbsp;&nbsp;&nbsp;$ID&nbsp;&nbsp;&nbsp;</A></td>\n";
         if( (1 << 1) & $column_set )
            echo "<td><A href=\"sgf.php?gid=$ID\"><font color=$gid_color>sgf</font></A></td>\n";
         if( (1 << 2) & $column_set )
            echo "<td><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
               make_html_safe($Name) . "</font></a></td>\n";
         if( (1 << 3) & $column_set )
            echo "<td><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
               make_html_safe($Handle) . "</font></a></td>\n";
         if( (1 << 15) & $column_set )
            echo "<td>" . echo_rating($Rating) . "&nbsp;</td>\n";
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



   $menu_array = array( T_('Show/edit userinfo') => "userinfo.php?uid=$uid",
                        T_('Show running games') => "show_games.php?uid=$uid",
                        T_('Show finished games') => "show_games.php?uid=$uid&amp;finished=1",
                        T_('Show observed games') => "show_games.php?observe=t" );

   end_page( $menu_array );
}
?>
