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

$TranslateGroups[] = "Game";

require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );
require_once( "include/rating.php" );

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $observe = isset($_GET['observe']);
   $finished = isset($_GET['finished']);
   $uid = @$_GET['uid'];
   $all = ($uid == 'all');

   if( !$observe and !$all )
   {
      get_request_user( $uid, $uhandle, true);
      if( $uhandle )
         $where = "Handle='$uhandle'";
      elseif( $uid > 0 )
         $where = "ID=$uid";
      else
         error("no_uid");

      $result = mysql_query( "SELECT ID, Name, Handle FROM Players WHERE $where" );

      if( mysql_num_rows($result) != 1 )
         error("unknown_user");

      $user_row = mysql_fetch_array($result);
      $uid = $user_row['ID'];
   }

   if( $observe )
      $page = 'show_games.php?observe=t&';
   else
      $page = "show_games.php?uid=$uid&" . ( $finished ? 'finished=1&' : '' );

   if(!@$_GET['sort1'])
   {
      $_GET['sort1'] = 'Lastchanged';
      $_GET['desc1'] = 1;
      $_GET['sort2'] = 'ID';
      $_GET['desc2'] = 1;
   }

   if(!@$_GET['sort2'])
   {
      $_GET['sort2'] = 'Lastchanged';
      $_GET['desc2'] = 1;
   }

   $gtable = new Table( $page, "GamesColumns" );
   $gtable->add_or_del_column();

   $order = $gtable->current_order_string();
   $limit = $gtable->current_limit_string();

   if( $observe )
   {
      $query = "SELECT Games.*, UNIX_TIMESTAMP(Lastchanged) AS Time, " .
         "black.Name AS blackName, black.Handle AS blackHandle, " .
         "black.Rating2 AS blackRating, black.ID AS blackID, " .
         "white.Name AS whiteName, white.Handle AS whiteHandle, " .
         "white.Rating2 AS whiteRating, white.ID AS whiteID " .
         "FROM Observers, Games, Players AS white, Players AS black " .
         "WHERE Observers.uid=" . $player_row["ID"] . " AND Games.ID=gid " .
         "AND white.ID=White_ID AND black.ID=Black_ID " .
         "ORDER BY $order $limit";
   }
   else if( $all )
   {
      $query = "SELECT Games.*, UNIX_TIMESTAMP(Lastchanged) AS Time, " .
         "black.Name AS blackName, black.Handle AS blackHandle, black.ID AS blackID, " .
         "white.Name AS whiteName, white.Handle AS whiteHandle, white.ID AS whiteID, " .
         "black.Rating2 AS blackRating, white.Rating2 AS whiteRating, " .
         "Games.Black_Start_Rating AS blackStartRating, Games.White_Start_Rating AS whiteStartRating " .
         ( $finished
           ? ", Black_End_Rating AS blackEndRating, White_End_Rating AS whiteEndRating, " .
           "blog.RatingDiff AS blackDiff, wlog.RatingDiff AS whiteDiff " : '' ) .
         "FROM Games, Players AS white, Players AS black " .
         ( $finished ?
           "LEFT JOIN Ratinglog AS blog ON blog.gid=Games.ID AND blog.uid=Black_ID ".
           "LEFT JOIN Ratinglog AS wlog ON wlog.gid=Games.ID AND wlog.uid=White_ID " : '' ) .
         "WHERE " . ( $finished
                      ? "Status='FINISHED' "
                      : "Status!='INVITED' AND Status!='FINISHED' " ) .
         "AND white.ID=White_ID AND black.ID=Black_ID " .
         "ORDER BY $order $limit";
   }
   else
   {
      $query = "SELECT Games.*, UNIX_TIMESTAMP(Lastchanged) AS Time, " .
         "Name, Handle, Players.ID as pid, " .
         "Rating2 AS Rating, " .
         "IF(Black_ID=$uid" .
         ', Games.White_Start_Rating, Games.Black_Start_Rating) AS startRating, ' .
         ( $finished ?
           "IF(Black_ID=$uid" .
           ', Games.White_End_Rating, Games.Black_End_Rating) AS endRating, ' .
           'log.RatingDiff AS ratingDiff, ' : '' ) .
         "UNIX_TIMESTAMP(Lastaccess) AS Lastaccess, " .
         "IF(White_ID=$uid," . WHITE . "," . BLACK . ") AS Color ";

      if( $finished )
      {
         $query .= ", (Black_ID=$uid AND Score<0)*2 + " .
            "(White_ID=$uid AND Score>0)*2 + " .
            "(Score=0) - 1 AS Win ";
      }

      $query .= "FROM Games,Players " .
         ( $finished ?
           "LEFT JOIN Ratinglog AS log ON gid=Games.ID AND uid=$uid " : '' ) .
         "WHERE " . ( $finished ? "Status='FINISHED' "
                      : "Status!='INVITED' AND Status!='FINISHED' " ) .
         "AND (( Black_ID=$uid AND White_ID=Players.ID ) " .
           "OR ( White_ID=$uid AND Black_ID=Players.ID )) " .
         "ORDER BY $order $limit";
   }

   $result = mysql_query( $query ) or die(mysql_error());

   $show_rows = $gtable->compute_show_rows(mysql_num_rows($result));

   if( $observe or $all)
   {
      $title1 = $title2 = ( $observe ? T_('Observed games') :
                            ( $finished ? T_('Finished games') : T_('Running games') ) );
   }
   else
   {
      $games_for = ( $finished ? T_('Finished games for %s') : T_('Running games for %s') );
      $title1 = sprintf(  $games_for, make_html_safe($user_row["Name"]) );
      $title2 = sprintf(  $games_for, user_reference( 1, true, '', $user_row) );
   }

   start_page( $title1, true, $logged_in, $player_row, button_style() );

   echo "<center><h3><font color=$h3_color>$title2</font></H3></center>\n";


   $gtable->add_tablehead( 1, T_('ID'), 'ID', true, true );
   $gtable->add_tablehead( 2, T_('sgf') );

   if( $observe or $all )
   {
      $gtable->add_tablehead(17, T_('Black name'), 'blackName');
      $gtable->add_tablehead(18, T_('Black userid'), 'blackHandle');
      $gtable->add_tablehead(26, T_('Black start rating'), 'blackStartRating', true);
      if( $finished )
         $gtable->add_tablehead(27, T_('Black end rating'), 'blackEndRating', true);
      $gtable->add_tablehead(19, T_('Black rating'), 'blackRating', true);
      if( $finished )
         $gtable->add_tablehead(28, T_('Black rating diff'), 'blackDiff', true);
      $gtable->add_tablehead(20, T_('White name'), 'whiteName');
      $gtable->add_tablehead(21, T_('White userid'), 'whiteHandle');
      $gtable->add_tablehead(29, T_('White start rating'), 'whiteStartRating', true);
      if( $finished )
         $gtable->add_tablehead(30, T_('White end rating'), 'whiteEndRating', true);
      $gtable->add_tablehead(22, T_('White rating'), 'whiteRating', true);
      if( $finished )
         $gtable->add_tablehead(31, T_('White rating diff'), 'whiteDiff', true);
   }
   else
   {
      $gtable->add_tablehead(3, T_('Opponent'), 'Name');
      $gtable->add_tablehead(4, T_('Nick'), 'Handle');
      $gtable->add_tablehead(23, T_('Start rating'), 'startRating', true);
      if( $finished )
         $gtable->add_tablehead(24, T_('End rating'), 'endRating', true);
      $gtable->add_tablehead(16, T_('Rating'), 'Rating', true);
      if( $finished )
         $gtable->add_tablehead(25, T_('Rating diff'), 'ratingDiff', true);
      $gtable->add_tablehead(5, T_('Color'), 'Color');
   }

   $gtable->add_tablehead(6, T_('Size'), 'Size', true);
   $gtable->add_tablehead(7, T_('Handicap'), 'Handicap');
   $gtable->add_tablehead(8, T_('Komi'), 'Komi');
   $gtable->add_tablehead(9, T_('Moves'), 'Moves', true);

   if( $finished )
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
      if( !$observe and !$all)
      {
         $gtable->add_tablehead(15, T_('Opponents Last Access'), 'Lastaccess', true);
      }
   }

   while( ($row = mysql_fetch_array( $result )) && $show_rows-- > 0 )
   {
      $Rating = $blackRating = $whiteRating = NULL;
      $startRating = $blackStartRating = $whiteStartRating = NULL;
      $endRating = $blackEndRating = $whiteEndRating = NULL;
      $blackDiff = $whiteDiff = $ratingDiff = NULL;
      extract($row);
      if( !isset($Color) )
         $color = 'y';
      else
         $color = ( $Color == BLACK ? 'b' : 'w' );

      $grow_strings = array();
      if( $gtable->Is_Column_Displayed[1] )
         $grow_strings[1] = str_TD_class_button($player_row["Browser"]) .
            "<A class=button href=\"game.php?gid=$ID\">" .
            "&nbsp;&nbsp;&nbsp;$ID&nbsp;&nbsp;&nbsp;</A></td>";
      if( $gtable->Is_Column_Displayed[2] )
         $grow_strings[2] = "<td><A href=\"sgf.php?gid=$ID\">" .
            "<font color=$sgf_color>" . T_('sgf') . "</font></A></td>";

      if( $observe or $all )
      {
         if( $gtable->Is_Column_Displayed[17] )
            $grow_strings[17] = "<td><A href=\"userinfo.php?uid=$blackID\"><font color=black>" .
               make_html_safe($blackName) . "</font></a></td>";
         if( $gtable->Is_Column_Displayed[18] )
            $grow_strings[18] = "<td><A href=\"userinfo.php?uid=$blackID\"><font color=black>" .
               $blackHandle . "</font></a></td>";
         if( $gtable->Is_Column_Displayed[26] )
            $grow_strings[26] = "<td>" . echo_rating($blackStartRating,true,$blackID) . "&nbsp;</td>";
         if( $finished and $gtable->Is_Column_Displayed[27] )
            $grow_strings[27] = "<td>" . echo_rating($blackEndRating,true,$blackID) . "&nbsp;</td>";
         if( $gtable->Is_Column_Displayed[19] )
            $grow_strings[19] = "<td>" . echo_rating($blackRating,true,$blackID) . "&nbsp;</td>";
         if( $finished and $gtable->Is_Column_Displayed[28] )
            $grow_strings[28] = "<td>" .
               (isset($blackDiff) ? ($blackDiff > 0 ? '+' : '') .
                sprintf("%0.2f",$blackDiff*0.01) : '&nbsp;' ) . "</td>";
         if( $gtable->Is_Column_Displayed[20] )
            $grow_strings[20] = "<td><A href=\"userinfo.php?uid=$whiteID\"><font color=black>" .
               make_html_safe($whiteName) . "</font></a></td>";
         if( $gtable->Is_Column_Displayed[21] )
            $grow_strings[21] = "<td><A href=\"userinfo.php?uid=$whiteID\"><font color=black>" .
               $whiteHandle . "</font></a></td>";
         if( $gtable->Is_Column_Displayed[29] )
            $grow_strings[29] = "<td>" . echo_rating($whiteStartRating,true,$whiteID) . "&nbsp;</td>";
         if( $finished and $gtable->Is_Column_Displayed[30] )
            $grow_strings[30] = "<td>" . echo_rating($whiteEndRating,true,$whiteID) . "&nbsp;</td>";
         if( $gtable->Is_Column_Displayed[22] )
            $grow_strings[22] = "<td>" . echo_rating($whiteRating,true,$whiteID) . "&nbsp;</td>";
         if( $finished and $gtable->Is_Column_Displayed[31] )
            $grow_strings[31] = "<td>" .
               (isset($whiteDiff) ? ($whiteDiff > 0 ? '+' : '') .
                sprintf("%0.2f",$whiteDiff*0.01) : '&nbsp;' ) . "</td>";
      }
      else
      {
         if( $gtable->Is_Column_Displayed[3] )
            $grow_strings[3] = "<td><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
               make_html_safe($Name) . "</font></a></td>";
         if( $gtable->Is_Column_Displayed[4] )
            $grow_strings[4] = "<td><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
               $Handle . "</font></a></td>";
         if( $gtable->Is_Column_Displayed[23] )
            $grow_strings[23] = "<td>" . echo_rating($startRating,true,$pid) . "&nbsp;</td>";
         if( $finished and $gtable->Is_Column_Displayed[24] )
            $grow_strings[24] = "<td>" . echo_rating($endRating,true,$pid) . "&nbsp;</td>";
         if( $gtable->Is_Column_Displayed[16] )
            $grow_strings[16] = "<td>" . echo_rating($Rating,true,$pid) . "&nbsp;</td>";
         if( $finished and $gtable->Is_Column_Displayed[25] )
            $grow_strings[25] = "<td>" .
               (isset($ratingDiff) ? ($ratingDiff > 0 ? '+' : '') .
                sprintf("%0.2f",$ratingDiff*0.01) : '&nbsp;' ) . "</td>";
         if( $gtable->Is_Column_Displayed[5] )
            $grow_strings[5] = "<td align=center><img src=\"17/$color.gif\" alt=$color></td>";
      }

      if( $gtable->Is_Column_Displayed[6] )
         $grow_strings[6] = "<td>$Size</td>";
      if( $gtable->Is_Column_Displayed[7] )
         $grow_strings[7] = "<td>$Handicap</td>";
      if( $gtable->Is_Column_Displayed[8] )
         $grow_strings[8] = "<td>$Komi</td>";
      if( $gtable->Is_Column_Displayed[9] )
         $grow_strings[9] = "<td>$Moves</td>";

      if( $finished )
      {
         if( $gtable->Is_Column_Displayed[10] )
            $grow_strings[10] = '<td>' . score2text($Score, false) . "</td>";
         if( !$all )
         {
            $src = '"images/' .
               ( $Win == 1 ? 'yes.gif" alt=' . T_('Yes') :
                 ( $Win == -1 ? 'no.gif" alt=' . T_('No') :
                   'dash.gif" alt=' . T_('jigo') ));

            if( $gtable->Is_Column_Displayed[11] )
               $grow_strings[11] = "<td align=center><img src=$src></td>";
         }
         if( $gtable->Is_Column_Displayed[14] )
            $grow_strings[14] = "<td>" . ($Rated == 'N' ? T_('No') : T_('Yes') ) . "</td>";
         if( $gtable->Is_Column_Displayed[12] )
            $grow_strings[12] = '<td>' . date($date_fmt2, $Time) . "</td>";
      }
      else
      {
         if( $gtable->Is_Column_Displayed[14] )
            $grow_strings[14] = "<td>" . ($Rated == 'N' ? T_('No') : T_('Yes') ) . "</td>";
         if( $gtable->Is_Column_Displayed[13] )
            $grow_strings[13] = '<td>' . date($date_fmt2, $Time) . "</td>";

         if( !$observe and !$all and $gtable->Is_Column_Displayed[15] )
            $grow_strings[15] = '<td align=center>' . date($date_fmt2, $Lastaccess) . "</td>";
      }

      $gtable->add_row( $grow_strings );
   }

   $gtable->echo_table();

   $menu_array = array();

   if( $observe )
   {
      $uid = $player_row["ID"];
   }

   if( !$all )
   {
      $menu_array[T_('User info')] = "userinfo.php?uid=$uid";

      if( $uid != $player_row["ID"] and !$observe )
         $menu_array[T_('Invite this user')] = "message.php?mode=Invite&uid=$uid";
   }

   if( $finished or $observe )
   {
      $menu_array[T_('Show running games')] = "show_games.php?uid=$uid";
   }
   if( !$finished )
   {
      $menu_array[T_('Show finished games')] = "show_games.php?uid=$uid&finished=1";
   }
   if( !$observe )
   {
      $menu_array[T_('Show observed games')] = "show_games.php?observe=t";
   }

   end_page(@$menu_array);
}
?>
