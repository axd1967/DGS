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

$TranslateGroups[] = "Game";

require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );
require_once( "include/rating.php" );

{
   connect2mysql();

   $all = ($_GET['uid'] == 'all');

   if( !($_GET['uid'] > 0) and !$_GET['observe'] and !$all )
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
   if( $_GET['observe'] )
      $page = 'show_games.php?observe=t&';
   else
      $page = "show_games.php?uid=" . $_GET['uid'] . '&' . ( $_GET['finished'] ? 'finished=1&' : '' );

   $gtable = new Table( $page, "GamesColumns" );
   $gtable->add_or_del_column();

   if( !$_GET['observe'] and !$all )
   {
      $result = mysql_query( "SELECT Name, Handle FROM Players WHERE ID=" . $_GET['uid'] );

      if( mysql_num_rows($result) != 1 )
         error("unknown_user");

      $user_row = mysql_fetch_array($result);
   }

   if(!$_GET['sort1'])
   {
      $_GET['sort1'] = 'Lastchanged';
      $_GET['desc1'] = 1;
      $_GET['sort2'] = 'ID';
      $_GET['desc2'] = 1;
   }

   $order = $_GET['sort1'] . ( $_GET['desc1'] ? ' DESC' : '' );
   if( $_GET['sort2'] )
      $order .= "," . $_GET['sort2'] . ( $_GET['desc2'] ? ' DESC' : '' );

   if( !is_numeric($_GET['from_row']) or $_GET['from_row'] < 0 )
      $_GET['from_row'] = 0;

   if( $_GET['observe'] )
   {
      $query = "SELECT Games.*, UNIX_TIMESTAMP(Lastchanged) AS Time, " .
         "black.Name AS blackName, black.Handle AS blackHandle, " .
         "black.Rating2 AS blackRating, black.ID AS blackID, " .
         "white.Name AS whiteName, white.Handle AS whiteHandle, " .
         "white.Rating2 AS whiteRating, white.ID AS whiteID " .
         "FROM Observers, Games, Players AS white, Players AS black " .
         "WHERE Observers.uid=" . $player_row["ID"] . " AND Games.ID=gid " .
         "AND white.ID=White_ID AND black.ID=Black_ID " .
         "ORDER BY $order LIMIT " . $_GET['from_row'] . ",$MaxRowsPerPage";
   }
   else if( $all )
   {
      $query = "SELECT Games.*, UNIX_TIMESTAMP(Lastchanged) AS Time, " .
         "black.Name AS blackName, black.Handle AS blackHandle, " .
         "black.Rating2 AS blackRating, black.ID AS blackID, " .
         "white.Name AS whiteName, white.Handle AS whiteHandle, " .
         "white.Rating2 AS whiteRating, white.ID AS whiteID " .
         "FROM Games, Players AS white, Players AS black " .
         "WHERE " .
         ( $_GET['finished'] ? "Status='FINISHED' " : "Status!='INVITED' AND Status!='FINISHED' " ) .
         "AND white.ID=White_ID AND black.ID=Black_ID " .
         "ORDER BY $order LIMIT " . $_GET['from_row'] . ",$MaxRowsPerPage";
   }
   else
   {
      $query = "SELECT Games.*, UNIX_TIMESTAMP(Lastchanged) AS Time, " .
         "Name, Handle, Players.ID as pid, " .
         "Rating2 AS Rating, UNIX_TIMESTAMP(Lastaccess) AS Lastaccess, " .
         "(White_ID=" . $_GET['uid'] . ")+1 AS Color ";

      if( $_GET['finished'] )
      {
         $query .= ", (Black_ID=" . $_GET['uid'] . " AND Score<0)*2 + " .
            "(White_ID=" . $_GET['uid'] . " AND Score>0)*2 + " .
            "(Score=0) - 1 AS Win ";
      }

      $query .= "FROM Games,Players WHERE " .
         ( $_GET['finished'] ? "Status='FINISHED' " : "Status!='INVITED' AND Status!='FINISHED' " ) .
         "AND (( Black_ID=" . $_GET['uid'] . " AND White_ID=Players.ID ) " .
         "OR ( White_ID=" . $_GET['uid'] . " AND Black_ID=Players.ID )) " .
         "ORDER BY $order LIMIT " . $_GET['from_row'] . ",$MaxRowsPerPage";
   }

   $result = mysql_query( $query );

   if( $_GET['observe'] or $all)
   {
      $title1 = $title2 = ( $_GET['observe'] ? T_('Observed games') :
                            ( $_GET['finished'] ? T_('Finished games') : T_('Running games') ) );
   }
   else
   {
      $games_for = ( $_GET['finished'] ? T_('Finished games for %s') : T_('Running games for %s') );
      $title = sprintf( $games_for, make_html_safe($user_row["Name"]) );
      $title2 = sprintf(  $games_for, "<A href=\"userinfo.php?uid=" . $_GET['uid'] . "\">" .
                          make_html_safe($user_row["Name"]) . " (" .
                          make_html_safe($user_row["Handle"]) . ")</A>");
   }

   start_page( $title1, true, $logged_in, $player_row, $style );

   $show_rows = mysql_num_rows($result);
   if( $show_rows == $MaxRowsPerPage )
   {
      $show_rows = $RowsPerPage;
      $gtable->Last_Page = false;
   }

   echo "<center><h4><font color=$h3_color>$title2</font></H4></center>\n";


   $gtable->add_tablehead( 1, T_('ID'), 'ID', true, true );
   $gtable->add_tablehead( 2, T_('sgf') );

   if( $_GET['observe'] or $all )
   {
      $gtable->add_tablehead(17, T_('Black name'), 'blackName');
      $gtable->add_tablehead(18, T_('Black userid'), 'blackHandle');
      $gtable->add_tablehead(19, T_('Black rating'), 'blackRating', true);
      $gtable->add_tablehead(20, T_('White name'), 'whiteName');
      $gtable->add_tablehead(21, T_('White userid'), 'whiteHandle');
      $gtable->add_tablehead(22, T_('White rating'), 'whiteRating', true);
   }
   else
   {
      $gtable->add_tablehead(3, T_('Opponent'), 'Name');
      $gtable->add_tablehead(4, T_('Nick'), 'Handle');
      $gtable->add_tablehead(16, T_('Rating'), 'Rating', true);
      $gtable->add_tablehead(5, T_('Color'), 'Color');
   }

   $gtable->add_tablehead(6, T_('Size'), 'Size', true);
   $gtable->add_tablehead(7, T_('Handicap'), 'Handicap');
   $gtable->add_tablehead(8, T_('Komi'), 'Komi');
   $gtable->add_tablehead(9, T_('Moves'), 'Moves', true);

   if( $_GET['finished'] )
   {
      $gtable->add_tablehead(10, T_('Score'));
      if( !$all )
      {
         $gtable->add_tablehead(11, T_('Win?'), 'Win', true);
      }
      $gtable->add_tablehead(14, T_('Rated'), 'Rated', true);
      $gtable->add_tablehead(12, T_('End date'), 'Lastchanged', true);
   }
   else
   {
      $gtable->add_tablehead(14, T_('Rated'), 'Rated', true);
      $gtable->add_tablehead(13, T_('Last Move'), 'Lastchanged', true);
      if( !$_GET['observe'] and !$all)
      {
         $gtable->add_tablehead(15, T_('Opponents Last Access'), 'Lastaccess', true);
      }
   }

   $i=0;
   while( $row = mysql_fetch_array( $result ) )
   {
      $Rating = $blackRating = $whiteRating = NULL;
      extract($row);
      $color = ( $Color == BLACK ? 'b' : 'w' );

      $grow_strings = array();
      $grow_strings[1] = "<td class=button width=92 align=center>" .
         "<A class=button href=\"game.php?gid=$ID\">" .
         "&nbsp;&nbsp;&nbsp;$ID&nbsp;&nbsp;&nbsp;</A></td>";
      $grow_strings[2] = "<td><A href=\"sgf.php?gid=$ID\">" .
         "<font color=$gid_color>" . T_('sgf') . "</font></A></td>";

      if( $_GET['observe'] or $all )
      {
         $grow_strings[17] = "<td><A href=\"userinfo.php?uid=$blackID\"><font color=black>" .
            make_html_safe($blackName) . "</font></a></td>";
         $grow_strings[18] = "<td><A href=\"userinfo.php?uid=$blackID\"><font color=black>" .
            make_html_safe($blackHandle) . "</font></a></td>";
         $grow_strings[19] = "<td>" . echo_rating($blackRating,true,$blackID) . "&nbsp;</td>";
         $grow_strings[20] = "<td><A href=\"userinfo.php?uid=$whiteID\"><font color=black>" .
            make_html_safe($whiteName) . "</font></a></td>";
         $grow_strings[21] = "<td><A href=\"userinfo.php?uid=$whiteID\"><font color=black>" .
            make_html_safe($whiteHandle) . "</font></a></td>";
         $grow_strings[22] = "<td>" . echo_rating($whiteRating,true,$whiteID) . "&nbsp;</td>";
      }
      else
      {
         $grow_strings[3] = "<td><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
            make_html_safe($Name) . "</font></a></td>";
         $grow_strings[4] = "<td><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
            make_html_safe($Handle) . "</font></a></td>";
         $grow_strings[16] = "<td>" . echo_rating($Rating,true,$pid) . "&nbsp;</td>";
         $grow_strings[5] = "<td align=center><img src=\"17/$color.gif\" alt=$color></td>";
      }

      $grow_strings[6] = "<td>$Size</td>";
      $grow_strings[7] = "<td>$Handicap</td>";
      $grow_strings[8] = "<td>$Komi</td>";
      $grow_strings[9] = "<td>$Moves</td>";

      if( $_GET['finished'] )
      {
         $grow_strings[10] = '<td>' . score2text($Score, false) . "</td>";
         if( !$all )
         {
            $src = '"images/' .
               ( $Win == 1 ? 'yes.gif" alt=' . T_('Yes') :
                 ( $Win == -1 ? 'no.gif" alt=' . T_('No') :
                   'dash.gif" alt=' . T_('jigo') ));

            $grow_strings[11] = "<td align=center><img src=$src></td>";
         }
         $grow_strings[14] = "<td>" . ($Rated == 'N' ? T_('No') : T_('Yes') ) . "</td>";
         $grow_strings[12] = '<td>' . date($date_fmt, $Time) . "</td>";
      }
      else
      {
         $grow_strings[14] = "<td>" . ($Rated == 'N' ? T_('No') : T_('Yes') ) . "</td>";
         $grow_strings[13] = '<td>' . date($date_fmt2, $Time) . "</td>";

         if( !$_GET['observe'] and !$all )
         {
            $grow_strings[15] = '<td align=center>' . date($date_fmt2, $Lastaccess) . "</td>";
         }
      }

      $gtable->add_row( $grow_strings );

      if(++$i >= $show_rows)
         break;
   }

   $gtable->echo_table();

   $menu_array = array();

   if( $_GET['observe'] )
   {
      $_GET['uid'] = $player_row["ID"];
   }

   if( !$all )
   {
      $menu_array[T_('User info')] = "userinfo.php?uid=" . $_GET['uid'];

      if( $_GET['uid'] != $player_row["ID"] and !$_GET['observe'] )
         $menu_array[T_('Invite this user')] = "message.php?mode=Invite&uid=" . $_GET['uid'];
   }

   if( $_GET['finished'] or $_GET['observe'] )
   {
      $menu_array[T_('Show running games')] = "show_games.php?uid=" . $_GET['uid'];
   }
   if( !$_GET['finished'] )
   {
      $menu_array[T_('Show finished games')] = "show_games.php?uid=" .
         $_GET['uid'] . "&finished=1";
   }
   if( !$_GET['observe'] )
   {
      $menu_array[T_('Show observed games')] = "show_games.php?observe=t";
   }

   end_page($menu_array);
}
?>
