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
include( "include/rating.php" );

{
   connect2mysql();

   $all = ($uid == 'all');

   if( !($uid > 0) and !$observe and !$all )
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

   if( $observe )
      $page = 'show_games.php?observe=t&';
   else
      $page = "show_games.php?uid=$uid&" . ( $finished ? 'finished=1&' : '' );

   add_or_del($add, $del, "GamesColumns");

   if( !$observe and !$all )
   {
      $result = mysql_query( "SELECT Name, Handle FROM Players WHERE ID=$uid" );

      if( mysql_num_rows($result) != 1 )
         error("unknown_user");

      $user_row = mysql_fetch_array($result);
   }

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

   if( $observe )
   {
      $query = "SELECT Games.*, UNIX_TIMESTAMP(Lastchanged) AS Time, " .
         "black.Name AS blackName, black.Handle AS blackHandle, " .
         "black.Rating AS blackRating, black.ID AS blackID, " .
         "white.Name AS whiteName, white.Handle AS whiteHandle, " .
         "white.Rating AS whiteRating, white.ID AS whiteID " .
         "FROM Observers, Games, Players AS white, Players AS black " .
         "WHERE Observers.uid=" . $player_row["ID"] . " AND Games.ID=gid " .
         "AND white.ID=White_ID AND black.ID=Black_ID " .
         "ORDER BY $order LIMIT $from_row,$MaxRowsPerPage";
   }
   else if( $all )
   {
      $query = "SELECT Games.*, UNIX_TIMESTAMP(Lastchanged) AS Time, " .
         "black.Name AS blackName, black.Handle AS blackHandle, " .
         "black.Rating AS blackRating, black.ID AS blackID, " .
         "white.Name AS whiteName, white.Handle AS whiteHandle, " .
         "white.Rating AS whiteRating, white.ID AS whiteID " .
         "FROM Games, Players AS white, Players AS black " .
         "WHERE " .
         ( $finished ? "Status='FINISHED' " : "Status!='INVITED' AND Status!='FINISHED' " ) .
         "AND white.ID=White_ID AND black.ID=Black_ID " .
         "ORDER BY $order LIMIT $from_row,$MaxRowsPerPage";
   }
   else
   {
      $query = "SELECT Games.*, UNIX_TIMESTAMP(Lastchanged) AS Time, " .
         "Name, Handle, Players.ID as pid, " .
         "Rating, UNIX_TIMESTAMP(Lastaccess) AS Lastaccess, " .
         "(White_ID=$uid)+1 AS Color ";

      if( $finished )
         $query .= ", (Black_ID=$uid AND Score<0)*2 + (White_ID=$uid AND Score>0)*2 + " .
            "(Score=0) - 1 AS Win ";

      $query .= "FROM Games,Players WHERE " .
         ( $finished ? "Status='FINISHED' " : "Status!='INVITED' AND Status!='FINISHED' " ) .
         "AND (( Black_ID=$uid AND White_ID=Players.ID ) " .
         "OR ( White_ID=$uid AND Black_ID=Players.ID )) " .
         "ORDER BY $order LIMIT $from_row,$MaxRowsPerPage";
   }

   $result = mysql_query( $query );


   if( $observe or $all)
   {
      $title1 = $title2 = ( $observe ? T_('Observed games') :
                            ( $finished ? T_('Finished games') : T_('Running games') ) );
   }
   else
   {
      $games_for = ( $finished ? T_('Finished games for %s') : T_('Running games for %s') );
      $title = sprintf( $games_for, make_html_safe($user_row["Name"]) );
      $title2 = sprintf(  $games_for, "<A href=\"userinfo.php?uid=$uid\">" .
                          make_html_safe($user_row["Name"]) . " (" .
                          make_html_safe($user_row["Handle"]) . ")</A>");
   }

   start_page( $title1, true, $logged_in, $player_row, $style );

   $show_rows = $nr_rows = mysql_num_rows($result);

   if( $nr_rows == $MaxRowsPerPage )
      $show_rows = $RowsPerPage;

   echo "<center><h4><font color=$h3_color>$title2</font></H4></center>\n";


   echo start_end_column_table(true) .
      tablehead(NULL, T_('ID'), 'ID', true, true) .
      tablehead(2, T_('sgf'));

   if( $observe or $all )
   {
       echo tablehead(17, T_('Black name'), 'blackName') .
          tablehead(18, T_('Black userid'), 'blackHandle') .
          tablehead(19, T_('Black rating'), 'blackRating', true) .
          tablehead(20, T_('White name'), 'whiteName') .
          tablehead(21, T_('White userid'), 'whiteHandle') .
          tablehead(22, T_('White rating'), 'whiteRating', true);
   }
   else
   {
      echo tablehead(3, T_('Opponent'), 'Name') .
         tablehead(4, T_('Nick'), 'Handle') .
         tablehead(16, T_('Rating'), 'Rating', true) .
         tablehead(5, T_('Color'), 'Color');
   }

   echo tablehead(6, T_('Size'), 'Size', true) .
      tablehead(7, T_('Handicap'), 'Handicap') .
      tablehead(8, T_('Komi'), 'Komi') .
      tablehead(9, T_('Moves'), 'Moves', true);

   if( $finished )
   {
      echo tablehead(10, T_('Score'));
      if( !$all )
         echo tablehead(11, T_('Win?'), 'Win', true);
      echo tablehead(14, T_('Rated'), 'Rated', true) .
         tablehead(12, T_('End date'), 'Lastchanged', true);
   }
   else
   {
      echo tablehead(14, T_('Rated'), 'Rated', true) .
         tablehead(13, T_('Last Move'), 'Lastchanged', true);
      if( !$observe and !$all)
        echo tablehead(15, T_('Opponents Last Access'), 'Lastaccess', true);
   }

   echo "</tr>\n";

   $i=0;
   $row_color=2;
   while( $row = mysql_fetch_array( $result ) )
   {
      $Rating = $blackRating = $whiteRating = NULL;
      extract($row);
      $color = ( $Color == BLACK ? 'b' : 'w' );

      $row_color=3-$row_color;
      echo "<tr bgcolor=" . ${"table_row_color$row_color"} . ">\n";


      echo "<td class=button width=92 align=center><A class=button href=\"game.php?gid=$ID\">&nbsp;&nbsp;&nbsp;$ID&nbsp;&nbsp;&nbsp;</A></td>\n";
      if( (1 << 1) & $column_set )
         echo "<td><A href=\"sgf.php?gid=$ID\"><font color=$gid_color>" . T_('sgf') . "</font></A></td>\n";


      if( $observe or $all )
      {
         if( (1 << 16) & $column_set )
            echo "<td><A href=\"userinfo.php?uid=$blackID\"><font color=black>" .
               make_html_safe($blackName) . "</font></a></td>\n";
         if( (1 << 17) & $column_set )
            echo "<td><A href=\"userinfo.php?uid=$blackID\"><font color=black>" .
               make_html_safe($blackHandle) . "</font></a></td>\n";
         if( (1 << 18) & $column_set )
            echo "<td>" . echo_rating($blackRating) . "&nbsp;</td>\n";
         if( (1 << 19) & $column_set )
            echo "<td><A href=\"userinfo.php?uid=$whiteID\"><font color=black>" .
               make_html_safe($whiteName) . "</font></a></td>\n";
         if( (1 << 20) & $column_set )
            echo "<td><A href=\"userinfo.php?uid=$whiteID\"><font color=black>" .
               make_html_safe($whiteHandle) . "</font></a></td>\n";
         if( (1 << 21) & $column_set )
            echo "<td>" . echo_rating($whiteRating) . "&nbsp;</td>\n";
      }
      else
      {
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
      }

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
         if( (1 << 9) & $column_set )
            echo '<td>' . score2text($Score, false) . "</td>\n";
         if( !$all and (1 << 10) & $column_set )
         {
            $src = '"images/' .
               ( $Win == 1 ? 'yes.gif" alt=' . T_('yes') :
                 ( $Win == -1 ? 'no.gif" alt=' . T_('no') :
                   'dash.gif" alt=' . T_('jigo') ));

            echo "<td align=center><img src=$src></td>";
         }
         if( (1 << 13) & $column_set )
            echo "<td>" . ($Rated == 'N' ? T_('No') : T_('Yes') ) . "</td>\n";
         if( (1 << 11) & $column_set )
            echo '<td>' . date($date_fmt, $Time) . "</td>\n";
      }
      else
      {
         if( (1 << 13) & $column_set )
            echo "<td>" . ($Rated == 'N' ? T_('No') : T_('Yes') ) . "</td>\n";
         if( (1 << 12) & $column_set )
            echo '<td>' . date($date_fmt2, $Time) . "</td>\n";

         if( !$observe and !$all and (1 << 14) & $column_set )
            echo '<td align=center>' . date($date_fmt2, $Lastaccess) . "</td>\n";
      }

      echo "</tr>\n";

      if(++$i >= $show_rows)
         break;
   }

   echo start_end_column_table(false);



   $menu_array = array();

   if( $observe )
      $uid = $player_row["ID"];

   if( !$all )
   {
      $menu_array[T_('User info')] = "userinfo.php?uid=$uid";

      if( $uid != $player_row["ID"] and !$observe )
         $menu_array[T_('Invite this user')] = "message.php?mode=Invite&uid=$uid";
   }

   if( $finished or $observe )
      $menu_array[T_('Show running games')] = "show_games.php?uid=$uid";
   if( !$finished )
      $menu_array[T_('Show finished games')] = "show_games.php?uid=$uid&finished=1";
   if( !$observe )
      $menu_array[T_('Show observed games')] = "show_games.php?observe=t";

   end_page($menu_array);
}
?>
