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
include( "include/form_functions.php" );

{
   connect2mysql();

   if( !$uid )
      error("no_uid");

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $button_nr = $player_row["Button"];

   if ( !is_numeric($button_nr) or $button_nr < 0 or $button_nr > $button_max  )
      $button_nr = 0;

   $style = 'a.button { color : ' . $buttoncolors[$button_nr] .
      ';  font : bold 100% sans-serif;  text-decoration : none;  width : 90px; }
td.button { background-image : url(images/' . $buttonfiles[$button_nr] . ');' .
      'background-repeat : no-repeat;  background-position : center; }';


   $column_set = $player_row["GamesColumns"];
   $finished_string = ( $finished ? 'finished=1&' : '' );
   $page = "show_games.php?uid=$uid&$finished_string";

   add_or_del($add, $del, "GamesColumns");

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
       "(White_ID=$uid)+1 AS Color ";

   if( $finished )
      $query .= ", (Black_ID=$uid AND Score<0)*2 + (White_ID=$uid AND Score>0)*2-1 AS Win ";

   $query .= "FROM Games,Players WHERE " .
       ( $finished ? "Status='FINISHED' " : "Status!='INVITED' AND Status!='FINISHED' " ) .
       "AND (( Black_ID=$uid AND White_ID=Players.ID ) " .
       "OR ( White_ID=$uid AND Black_ID=Players.ID )) " .
       "ORDER BY $order LIMIT $from_row,$MaxRowsPerPage";

   $result = mysql_query( $query );


   $games_for = ( $finished ? T_('Finished games for %s') : T_('Running games for %s') );

   start_page( sprintf( $games_for, $user_row["Name"] ),
               true, $logged_in, $player_row, $style );


   $show_rows = $nr_rows = mysql_num_rows($result);

   if( $nr_rows == $MaxRowsPerPage )
      $show_rows = $RowsPerPage;



   echo "<center><h4>";

   printf(  $games_for, "<A href=\"userinfo.php?uid=$uid\">" . $user_row["Name"] .
            " (" . $user_row["Handle"] . ")</A>");

   echo "</H4></center>\n";


   echo start_end_column_table(true) .
      tablehead(1, T_('ID'), 'ID', true, true) .
      tablehead(2, T_('sgf')) .
      tablehead(3, T_('Opponent'), 'Name') .
      tablehead(4, T_('Nick'), 'Handle') .
      tablehead(5, T_('Color'), 'Color') .
      tablehead(6, T_('Size'), 'Size', true) .
      tablehead(7, T_('Handicap'), 'Handicap') .
      tablehead(8, T_('Komi'), 'Komi') .
      tablehead(9, T_('Moves'), 'Moves', true);

   if( $finished )
   {
      echo tablehead(10, T_('Score')) .
         tablehead(11, T_('Win?'), 'Win', true) .
         tablehead(12, T_('End date'), 'Lastchanged', true);
   }
   else
   {
      echo tablehead(13, T_('Last Move'), 'Lastchanged', true);
   }

   echo "</tr>\n";

   $i=0;
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
         echo "<td><A href=\"sgf.php?gid=$ID\"><font color=$gid_color>" . T_('sgf') . "</font></A></td>\n";
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

      if( $finished )
      {
         $src = '"images/' .
             ( $Win == 1 ? 'yes.gif" alt=' . T_('yes') :
               ( $Win == -1 ? 'no.gif" alt=' . T_('no') :
                 'dash.gif" alt=' . T_('jigo' ) );

      if( (1 << 9) & $column_set )
         echo '<td>' . score2text($Score, false) . "</td>\n";
      if( (1 << 10) & $column_set )
         echo "<td align=center><img src=$src></td>";
      if( (1 << 11) & $column_set )
         echo '<td>' . date($date_fmt, $Time) . "</td>\n";
      }
      else
      {
         if( (1 << 12) & $column_set )
            echo '<td>' . date($date_fmt, $Time) . "</td>\n";
      }

      echo "</tr>\n";

      if(++$i >= $show_rows)
         break;
   }

   echo start_end_column_table(false);



   $menu_array = array( T_('User info') => "userinfo.php?uid=$uid" );

   if( $uid != $player_row["ID"] )
      $menu_array[T_('Invite this user')] = "invite.php?uid=$uid";

   if( $finished )
      $menu_array[T_('Show running games')] = "show_games.php?uid=$uid";
   else
      $menu_array[T_('Show finished games')] = "show_games.php?uid=$uid&finished=1";

   end_page($menu_array);
}
?>
