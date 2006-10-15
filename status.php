<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

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
require_once( "include/form_functions.php" ); //for test
require_once( "include/table_columns.php" );
require_once( "include/message_functions.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");
   init_standard_folders();

   $my_id = $player_row["ID"];

   start_page(T_('Status'), true, $logged_in, $player_row, button_style($player_row['Button']) );

   echo "<center>";


   echo "<h3><font color=$h3_color>" . T_('Status') . '</font></h3>';

   echo '
    <table id="user_status" class=infos border=1>
       <tr><td><b>' . T_("Name") . '</b></td>
           <td>' . make_html_safe($player_row["Name"]) . '</td></tr>
       <tr><td><b>' . T_("Userid") . '</b></td>
           <td>' . $player_row["Handle"] . '</td></tr>
       <tr><td><b>' . T_("Open for matches?") . '</b></td>
           <td>' . make_html_safe($player_row["Open"],INFO_HTML) . '</td></tr>
       <tr><td><b>' . T_("Rating") . '</b></td>
           <td>' . echo_rating($player_row["Rating2"],true,$player_row['ID']) .  '</td></tr>
       <tr><td><b>' . T_('Rank info') . '</b></td>
           <td>' . make_html_safe($player_row["Rank"],INFO_HTML) . '</td></tr>
       <tr><td><b><a href="edit_vacation.php"><font color=black>' .
      T_('Vacation days left') . '</font></a></b></td>
           <td>' . echo_day(floor($player_row["VacationDays"]))
           . '</td></tr>';

   if( $player_row['OnVacation'] > 0 )
   {
      echo '<tr><td><b><a href="edit_vacation.php"><font color=red>' . T_('On vacation') .
         '</font></a></b></td><td>' . 
         echo_day(floor($player_row['OnVacation'])) . ' ' .T_('left') . '</td></tr>';
   }
   echo '
    </table>
    <p></p>';


   // show messages

   $mtable = new Table( 'status.php', '', 'm_' );

   $order = 'date';

   $folderstring = $player_row['StatusFolders'] .
      (empty($player_row['StatusFolders']) ? '' : ',') . FOLDER_NEW . ',' . FOLDER_REPLY;

   $result = message_list_query($my_id, $folderstring, $order, 'LIMIT 20');
   if( @mysql_num_rows($result) > 0 )
   {
      $my_folders = get_folders($my_id);

      echo "<HR><h3><font color=$h3_color>" . T_('New messages') . ":</font></h3>\n";

      message_list_table( $mtable, $result, 20
             , FOLDER_NONE /*FOLDER_ALL_RECEIVED*/, $my_folders
             , /*no_sort=*/true, true ) ;
//no_sort must stay true because of the two tables in the same page

      $mtable->echo_table();
      unset($mtable);
      echo "<p></p>\n";
   }



   // show games

   $uid = $my_id;

   $gtable = new Table( "status.php", "GamesColumns" );
   $gtable->add_or_del_column();
//can't be sorted until jump_to_next_game() adjusted to follow the sort
   $order = "Games.LastChanged";

   $query = "SELECT Games.*, UNIX_TIMESTAMP(Games.Lastchanged) AS Time, " .
      "IF(Rated='N','N','Y') as Rated, " .
      "opponent.Name, opponent.Handle, opponent.Rating2 AS Rating, opponent.ID AS pid, " .
         //extra bits of Color are for sorting purposes
      "IF(ToMove_ID=$uid,0,0x10)+IF(White_ID=$uid,2,0)+IF(White_ID=ToMove_ID,1,IF(Black_ID=ToMove_ID,0,0x20)) AS Color, " .
      "Clock.Ticks " .
      "FROM (Games,Players AS opponent) " .
      "LEFT JOIN Clock ON Clock.ID=Games.ClockUsed " .
      "WHERE ToMove_ID=$uid AND Status!='INVITED' AND Status!='FINISHED' " .
      "AND opponent.ID=(Black_ID+White_ID-$uid) " .
      "ORDER BY $order,Games.ID";

   $result = mysql_query( $query )
      or error('mysql_query_failed', 'status.find_games');

   echo "<hr><h3><font color=$h3_color>" .
      T_("Your turn to move in the following games:") . "</font></h3>\n";

   if( @mysql_num_rows($result) == 0 )
   {
      echo T_("No games found");
   }
   else
   {
      $gtable->add_tablehead( 0, T_('ID'), NULL, false, true, $button_width);
      $gtable->add_tablehead( 2, T_('sgf'));
      $gtable->add_tablehead( 3, T_('Opponent'));
      $gtable->add_tablehead( 4, T_('Userid'));
      $gtable->add_tablehead(16, T_('Rating'));
      $gtable->add_tablehead( 5, T_('Color'));
      $gtable->add_tablehead( 6, T_('Size'));
      $gtable->add_tablehead( 7, T_('Handicap'));
      $gtable->add_tablehead( 8, T_('Komi'));
      $gtable->add_tablehead( 9, T_('Moves'));
      $gtable->add_tablehead(14, T_('Rated'));
      $gtable->add_tablehead(13, T_('Last Move'));
      $gtable->add_tablehead(10, T_('Time remaining'));

      while( $row = mysql_fetch_assoc( $result ) )
      {
         $Rating=NULL;
         $Ticks=0;
         extract($row);

         $grow_strings = array();
         //if( $gtable->Is_Column_Displayed[0] )
            $grow_strings[0] = str_TD_class_button( "game.php?gid=$ID", $ID);
         if( $gtable->Is_Column_Displayed[2] )
            $grow_strings[2] = "<td><A href=\"sgf.php?gid=$ID\">" .
               "<font color=$sgf_color>" . T_('sgf') . "</font></A></td>";
         if( $gtable->Is_Column_Displayed[3] )
            $grow_strings[3] = "<td><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
               make_html_safe($Name) . "</font></a></td>";
         if( $gtable->Is_Column_Displayed[4] )
            $grow_strings[4] = "<td><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
               $Handle . "</font></a></td>";
         if( $gtable->Is_Column_Displayed[16] )
            $grow_strings[16] = "<td>" . echo_rating($Rating,true,$pid) . "&nbsp;</td>";
         if( $gtable->Is_Column_Displayed[5] )
         {
            if( $Color & 2 ) //my color
               $colors = 'w';
            else
               $colors = 'b';
      /*
            if( !($Color & 0x20) )
            {
               if( $Color & 1 ) //to move color
                  $colors.= '_w';
               else
                  $colors.= '_b';
            }
      */
            $grow_strings[5] = "<td align=center><img src=\"17/$colors.gif\" alt=\"$colors\"></td>";
         }
         if( $gtable->Is_Column_Displayed[6] )
            $grow_strings[6] = "<td>$Size</td>";
         if( $gtable->Is_Column_Displayed[7] )
            $grow_strings[7] = "<td>$Handicap</td>";
         if( $gtable->Is_Column_Displayed[8] )
            $grow_strings[8] = "<td>$Komi</td>";
         if( $gtable->Is_Column_Displayed[9] )
            $grow_strings[9] = "<td>$Moves</td>";
         if( $gtable->Is_Column_Displayed[14] )
            $grow_strings[14] = "<td>" . ($Rated == 'N' ? T_('No') : T_('Yes') ) . "</td>";
         if( $gtable->Is_Column_Displayed[13] )
            $grow_strings[13] = '<td>' . date($date_fmt, $Time) . "</td>";
         if( $gtable->Is_Column_Displayed[10] )
         {
            $grow_strings[10] = '<td align=center>';
            $my_Maintime = ( ($Color & 2) ? $White_Maintime : $Black_Maintime );
            $my_Byotime = ( ($Color & 2)  ? $White_Byotime : $Black_Byotime );
            $my_Byoperiods = ( ($Color & 2) ? $White_Byoperiods : $Black_Byoperiods );

            $hours = ticks_to_hours($Ticks - $LastTicks);

            time_remaining($hours, $my_Maintime, $my_Byotime, $my_Byoperiods,
                           $Maintime, $Byotype, $Byotime, $Byoperiods, false);

            $grow_strings[10] .=
               echo_time_remaining( $my_Maintime, $Byotype, $my_Byotime,
                                   $my_Byoperiods, false, true, true);
            $grow_strings[10] .= "</td>";
         }

         $gtable->add_row( $grow_strings );
      }

      $gtable->echo_table();
   }

   if( $player_row['Adminlevel'] & ADMIN_FORUM )
   {
      echo "<hr><br>";
      chdir('forum');
      require_once('forum_functions.php');
      display_posts_pending_approval();
      chdir('..');
   }

   echo "</center>";



   $menu_array = array( T_('Show/edit userinfo') => "userinfo.php?uid=$my_id",
                        T_('Show running games') => "show_games.php?uid=$my_id",
                        T_('Show finished games') => "show_games.php?uid=$my_id".URI_AMP."finished=1",
                        T_('Show observed games') => "show_games.php?observe=1" );

   end_page(@$menu_array);

}
?>
