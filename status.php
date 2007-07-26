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
require_once( 'include/table_infos.php' );
require_once( "include/table_columns.php" );
require_once( "include/message_functions.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $my_id = $player_row["ID"];

   //check if the player's clock need an adjustment from/to summertime
   if( $player_row['ClockChanged'] != 'Y' &&
      $player_row['ClockUsed'] !== get_clock_used($player_row['Nightstart']) )
   {
      // ClockUsed is updated once a day...
      mysql_query("UPDATE Players SET ClockChanged='Y' WHERE ID=$my_id LIMIT 1");
         //or error('mysql_query_failed','status.summertime');
   }


   $gtable = new Table( 'game', "status.php", "GamesColumns" );
   //can't be sorted until jump_to_next_game() adjusted to follow the sort
   $gtable->set_sort( 'Games.Lastchanged', 0, 'Games.ID', 0);
   $gtable->use_show_rows(false);
   $gtable->add_or_del_column();

   start_page(T_('Status'), true, $logged_in, $player_row,
               $gtable->button_style($player_row['Button']) );

   echo "<h3 class=Header>" . T_('Status') . "</h3>\n";



{ // show user infos
   $itable= new Table_info('user');

      $itable->add_row( array(
            'sname' => T_('Name'),
            'info' => $player_row["Name"],
            ) );
      $itable->add_row( array(
            'sname' => T_('Userid'),
            'sinfo' => $player_row["Handle"],
            ) );
      $itable->add_row( array(
            'sname' => T_('Open for matches?'),
            'info' => @$player_row["Open"],
            ) );
      $itable->add_row( array(
            'sname' => T_('Rating'),
            'sinfo' => echo_rating(@$player_row["Rating2"],true,$player_row['ID']),
            ) );
      $itable->add_row( array(
            'sname' => T_('Rank info'),
            'info' => @$player_row["Rank"],
            ) );
      $itable->add_row( array(
            'sname' => anchor( "edit_vacation.php", T_('Vacation days left')),
            'sinfo' => echo_day(floor($player_row["VacationDays"])),
            ) );

      if( $player_row['OnVacation'] > 0 )
      {
         $itable->add_row( array(
               'nattb' => 'class=OnVacation',
               'sname' => anchor( "edit_vacation.php", T_('On vacation')),
               'sinfo' => echo_day(floor($player_row['OnVacation'])).' '.T_('left#2'),
               ) );
      }

   $itable->echo_table();
   unset($itable);
} // show user infos


{ // show messages

   $mtable = new Table( 'message', 'status.php', '', 'MSG' );
   //sort must stay fixed because of the fixed LIMIT and no prev/next feature
   $mtable->set_sort( 'date', 0);
   //$mtable->add_or_del_column();

   $order = $mtable->current_order_string();
   $folderstring = $player_row['StatusFolders'] .
      (empty($player_row['StatusFolders']) ? '' : ',') . FOLDER_NEW . ',' . FOLDER_REPLY;

   list( $result ) = message_list_query($my_id, $folderstring, $order, 'LIMIT 20');
   if( @mysql_num_rows($result) > 0 )
   {
      init_standard_folders();
      $my_folders = get_folders($my_id);

      echo "<br><hr id=secMessage><h3 class=Header>" . T_('New messages') . ":</h3>\n";

      message_list_table( $mtable, $result, 20
             , FOLDER_NONE /*FOLDER_ALL_RECEIVED*/, $my_folders
             , /*no_sort=*/true, true ) ;
   //no_sort must stay true because of the fixed LIMIT and no prev/next feature

      $mtable->echo_table();
   }
   unset($mtable);
} // show messages


{ // show games
   $uid = $my_id;

   //can't be sorted until jump_to_next_game() adjusted to follow the sort
   $order = $gtable->current_order_string();

   $query = "SELECT Games.*, UNIX_TIMESTAMP(Games.Lastchanged) AS Time, " .
      "IF(Rated='N','N','Y') as Rated, " .
      "opponent.Name, opponent.Handle, opponent.Rating2 AS Rating, opponent.ID AS pid, " .
         //extra bits of Color are for sorting purposes
      "IF(ToMove_ID=$uid,0,0x10)+IF(White_ID=$uid,2,0)+IF(White_ID=ToMove_ID,1,IF(Black_ID=ToMove_ID,0,0x20)) AS Color, " .
      "Clock.Ticks " . //always my clock because always my turn (status page)
      "FROM (Games,Players AS opponent) " .
      "LEFT JOIN Clock ON Clock.ID=Games.ClockUsed " .
      "WHERE ToMove_ID=$uid AND Status" . IS_RUNNING_GAME .
      "AND opponent.ID=(Black_ID+White_ID-$uid) " .
      "ORDER BY $order,Games.ID";

   $result = mysql_query( $query )
      or error('mysql_query_failed', 'status.find_games');

   echo "<br><hr id=sect_game><h3 class=Header>" .
         T_("Your turn to move in the following games:") . "</h3>\n";

   if( @mysql_num_rows($result) == 0 )
   {
      echo T_("No games found");
   }
   else
   {
      $gtable->add_tablehead( 0,
         T_('ID'), NULL, false, true, array( 'class' => 'Button') );

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
            $grow_strings[0] = $gtable->button_TD_anchor( "game.php?gid=$ID", $ID);
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

            //if( !(($Color+1) & 2) ) //is it my turn? (always set in status page)
            $hours = ticks_to_hours($Ticks - $LastTicks);

            time_remaining($hours, $my_Maintime, $my_Byotime, $my_Byoperiods,
                           $Maintime, $Byotype, $Byotime, $Byoperiods, false);

            $grow_strings[10] .=
               echo_time_remaining( $my_Maintime, $Byotype, $my_Byotime,
                           $my_Byoperiods, $Byotime, false, true, true);
            $grow_strings[10] .= "</td>";
         }

         $gtable->add_row( $grow_strings );
      }

      $gtable->echo_table();
   }
   unset($gtable);
} // show games


{ // show pending posts
   if( (@$player_row['admin_level'] & ADMIN_FORUM) )
   {
      echo "<hr id=sect_pending><br>";
      chdir('forum');
      require_once('forum_functions.php');
      display_posts_pending_approval();
      chdir('..');
   }
} // show pending posts



   $menu_array = array( T_('Show/edit userinfo') => "userinfo.php?uid=$my_id",
                        T_('Show running games') => "show_games.php?uid=$my_id",
                        T_('Show finished games') => "show_games.php?uid=$my_id".URI_AMP."finished=1",
                        T_('Show observed games') => "show_games.php?observe=1" );

   end_page(@$menu_array);

}
?>
