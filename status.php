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

$TranslateGroups[] = "Common";

require_once( "include/std_functions.php" );
require_once( "include/rating.php" );
require_once( "include/table_columns.php" );

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

   $gtable = new Table( "status.php",
                        "GamesColumns" );
   $gtable->add_or_del_column();

   echo '
    <table border=3>
       <tr><td><b>' . T_("Name") . '</b></td>
           <td>' . make_html_safe($player_row["Name"]) . '</td></tr>
       <tr><td><b>' . T_("Userid") . '</b></td>
           <td>' . make_html_safe($player_row["Handle"]) . '</td></tr>
       <tr><td><b>' . T_("Open for matches?") . '</b></td>
           <td>' . make_html_safe($player_row["Open"], true) . '</td></tr>
       <tr><td><b>' . T_("Rating") . '</b></td>
           <td>' . echo_rating($player_row["Rating2"],true,$player_row['ID']) .  '</td></tr>
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

      $mtable = new Table( 'status.php', '', '', true );

      $mtable->add_tablehead( 1, T_('Flags'), NULL, true, true );
      $mtable->add_tablehead( 2, T_('From'), NULL, false, true );
      $mtable->add_tablehead( 3, T_('Subject'), NULL, false, true );
      $mtable->add_tablehead( 4, T_('Date'), NULL, true, true );

      while( $row = mysql_fetch_array( $result ) )
      {
         $mrow_strings = array();
         if( !(strpos($row["Flags"],'NEW') === false) )
         {
            $mrow_strings[1] = '<td bgcolor="#00F464">' . T_('New') . "</td>";
         }
         else if( !(strpos($row["Flags"],'REPLY REQUIRED') === false) )
         {
            $mrow_strings[1] = '<td bgcolor="#FFA27A">' . T_('Reply!') . "</td>";
         }
         else
         {
            error("message_status_corrupt");
         }

         $mrow_strings[2] = "<td><A href=\"message.php?mode=ShowMessage&amp;mid=" .
            $row["ID"] . "\">" . make_html_safe($row["sender"]) . "</A></td>";
         $mrow_strings[3] = "<td>" . make_html_safe($row["Subject"]) . "</td>";
         $mrow_strings[4] = "<td>" . date($date_fmt2, $row["date"]) . "</td></tr>";

         $mtable->add_row( $mrow_strings );
      }

      $mtable->echo_table();
      echo "<p>\n";
   }



   // show games;

   $uid = $player_row["ID"];

   $query = "SELECT Black_ID,White_ID,Games.ID,Size,Handicap,Komi,Games.Moves," .
       "UNIX_TIMESTAMP(Lastchanged) AS Time, " .
       "(White_ID=$uid)+1 AS Color, " .
       "opponent.Name, opponent.Handle, opponent.Rating2 AS Rating, opponent.ID AS pid " .
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
      $gtable->add_tablehead(1, T_('ID'), NULL, NULL, true);
      $gtable->add_tablehead(2, T_('sgf'));
      $gtable->add_tablehead(3, T_('Opponent'));
      $gtable->add_tablehead(4, T_('Nick'));
      $gtable->add_tablehead(16, T_('Rating'));
      $gtable->add_tablehead(5, T_('Color'));
      $gtable->add_tablehead(6, T_('Size'));
      $gtable->add_tablehead(7, T_('Handicap'));
      $gtable->add_tablehead(8, T_('Komi'));
      $gtable->add_tablehead(9, T_('Moves'));
      $gtable->add_tablehead(13, T_('Last Move'));

      while( $row = mysql_fetch_array( $result ) )
      {
         $Rating=NULL;
         extract($row);
         $color = ( $Color == BLACK ? 'b' : 'w' );

         $grow_strings = array();
         $grow_strings[1] = "<td class=button width=92 align=center>" .
            "<A class=button href=\"game.php?gid=$ID\">" .
            "&nbsp;&nbsp;&nbsp;$ID&nbsp;&nbsp;&nbsp;</A></td>";
         $grow_strings[2] = "<td><A href=\"sgf.php?gid=$ID\">" .
            "<font color=$gid_color>sgf</font></A></td>";
         $grow_strings[3] = "<td><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
            make_html_safe($Name) . "</font></a></td>";
         $grow_strings[4] = "<td><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
            make_html_safe($Handle) . "</font></a></td>";
         $grow_strings[16] = "<td>" . echo_rating($Rating,true,$pid) . "&nbsp;</td>";
         $grow_strings[5] = "<td align=center><img src=\"17/$color.gif\" alt=$color></td>";
         $grow_strings[6] = "<td>$Size</td>";
         $grow_strings[7] = "<td>$Handicap</td>";
         $grow_strings[8] = "<td>$Komi</td>";
         $grow_strings[9] = "<td>$Moves</td>";
         $grow_strings[13] = '<td>' . date($date_fmt, $Time) . "</td>";

         $gtable->add_row( $grow_strings );
      }

      $gtable->echo_table();
   }
   echo "</center>";

   $menu_array = array( T_('Show/edit userinfo') => "userinfo.php?uid=$uid",
                        T_('Show running games') => "show_games.php?uid=$uid",
                        T_('Show finished games') => "show_games.php?uid=$uid&amp;finished=1",
                        T_('Show observed games') => "show_games.php?observe=t" );

   end_page( $menu_array );
}
?>
