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

$TranslateGroups[] = "Common";

require_once( "include/std_functions.php" );
require_once( "include/rating.php" );
require_once( "include/table_columns.php" );
require_once( "include/message_functions.php" );

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");
   init_standard_folders();

   $my_id = $player_row["ID"];

   start_page(T_('Status'), true, $logged_in, $player_row, button_style() );

   echo "<center>";

   if( $msg )
      echo "<p><b><font color=\"green\">$msg</font></b><hr>";

   echo "<h3><font color=$h3_color>" . T_('Status') . '</font></h3>';

   $gtable = new Table( "status.php", "GamesColumns" );
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
       <tr><td><b><a href="edit_vacation.php"><font color=black>' .
      T_('Vacation days left') . '</font></a></b></td>
           <td>' . sprintf("%d",$player_row["VacationDays"]) . '</td></tr>';

   if( $player_row['OnVacation'] > 0 )
   {
      $days = round($player_row['OnVacation']);
      echo '<tr><td><b><a href="edit_vacation.php"><font color=red>' . T_('On vacation') .
         '</font></a></b></td>
           <td>' . "$days " . ($days <= 1 ? T_('day') : T_('days')) .
         ' ' .T_('left') . '</td></tr>';
   }
   echo '
    </table>
    <p>';


   $folderstring = $player_row['StatusFolders'] .
      (empty($player_row['StatusFolders']) ? '' : ',') . FOLDER_NEW . ',' . FOLDER_REPLY;

   $result = message_list_query($my_id, $folderstring, 'date', 'LIMIT 20');
   if( mysql_num_rows($result) > 0 )
   {
      $mtable = new Table( 'status.php', '', '', true );
      $my_folders = get_folders($my_id);

      echo "<HR><h3><font color=$h3_color>" . T_('New messages') . ":</font></h3><p>\n";

      message_list_table( $mtable, $result, 20
             , FOLDER_NONE /*FOLDER_ALL_RECEIVED*/, $my_folders
             , true, true ) ;

      $mtable->echo_table();
      unset($mtable);
      echo "<p>\n";
   }



   // show games;

   $uid = $player_row["ID"];

   $query = "SELECT Black_ID,White_ID,Games.ID,Size,Handicap,Komi,Games.Moves," .
       "UNIX_TIMESTAMP(Lastchanged) AS Time, " .
       "IF(White_ID=$uid," . WHITE . "," . BLACK . ") AS Color, " .
       "opponent.Name, opponent.Handle, opponent.Rating2 AS Rating, opponent.ID AS pid " .
       "FROM Games,Players AS opponent " .
       "WHERE ToMove_ID=$uid AND Status!='INVITED' AND Status!='FINISHED' " .
       "AND (opponent.ID=Black_ID OR opponent.ID=White_ID) AND opponent.ID!=$uid " .
       "ORDER BY LastChanged, Games.ID";

   $result = mysql_query( $query ) or die(mysql_error());

   echo "<hr><h3><font color=$h3_color>" .
      T_("Your turn to move in the following games:") . "</font></h3><p>\n";

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
         if( $gtable->Is_Column_Displayed[1] )
            $grow_strings[1] = str_TD_class_button($player_row["Browser"]) .
               "<A class=button href=\"game.php?gid=$ID\">" .
               "&nbsp;&nbsp;&nbsp;$ID&nbsp;&nbsp;&nbsp;</A></td>";
         if( $gtable->Is_Column_Displayed[2] )
            $grow_strings[2] = "<td><A href=\"sgf.php?gid=$ID\">" .
               "<font color=$sgf_color>" . T_('sgf') . "</font></A></td>";
         if( $gtable->Is_Column_Displayed[3] )
            $grow_strings[3] = "<td><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
               make_html_safe($Name) . "</font></a></td>";
         if( $gtable->Is_Column_Displayed[4] )
            $grow_strings[4] = "<td><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
               make_html_safe($Handle) . "</font></a></td>";
         if( $gtable->Is_Column_Displayed[16] )
            $grow_strings[16] = "<td>" . echo_rating($Rating,true,$pid) . "&nbsp;</td>";
         if( $gtable->Is_Column_Displayed[5] )
            $grow_strings[5] = "<td align=center><img src=\"17/$color.gif\" alt=$color></td>";
         if( $gtable->Is_Column_Displayed[6] )
            $grow_strings[6] = "<td>$Size</td>";
         if( $gtable->Is_Column_Displayed[7] )
            $grow_strings[7] = "<td>$Handicap</td>";
         if( $gtable->Is_Column_Displayed[8] )
            $grow_strings[8] = "<td>$Komi</td>";
         if( $gtable->Is_Column_Displayed[9] )
            $grow_strings[9] = "<td>$Moves</td>";
         if( $gtable->Is_Column_Displayed[13] )
            $grow_strings[13] = '<td>' . date($date_fmt, $Time) . "</td>";

         $gtable->add_row( $grow_strings );
      }

      $gtable->echo_table();
   }
   echo "</center>";

   $menu_array = array( T_('Show/edit userinfo') => "userinfo.php?uid=$uid",
                        T_('Show running games') => "show_games.php?uid=$uid",
                        T_('Show finished games') => "show_games.php?uid=$uid&finished=1",
                        T_('Show observed games') => "show_games.php?observe=t" );

   end_page( $menu_array );
}
?>
