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
require_once( "include/rating.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );
require_once( "include/message_functions.php" );

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $handi_array = array( 'conv' => T_('Conventional'),
                         'proper' => T_('Proper'),
                         'nigiri' => T_('Even game with nigiri'),
                         'double' => T_('Double game') );

   $my_id = $player_row["ID"];

   $button_nr = $player_row["Button"];

   if ( !is_numeric($button_nr) or $button_nr < 0 or $button_nr > $button_max  )
      $button_nr = 0;

   $style = 'a.button { color : ' . $buttoncolors[$button_nr] .
      ';  font : bold 100% sans-serif;  text-decoration : none;  width : 90px; }
td.button { background-image : url(images/' . $buttonfiles[$button_nr] . ');' .
      'background-repeat : no-repeat;  background-position : center; }';


   start_page(T_("Waiting room"), true, $logged_in, $player_row, $style );

   echo "<center>";

   if( $_GET['msg'] )
      echo "<p><b><font color=\"green\">" . $_GET['msg'] . "</font></b><hr>";

   $page = "waiting_room.php" . ( $_GET['info'] > 0 ? "?info=" . $_GET['info'] . "&" : '?' );

   $wrtable = new Table( $page, "WaitingroomColumns" );
   $wrtable->add_or_del_column();

   if(!$_GET['sort1'])
      $_GET['sort1'] = 'ID';

   $order = $_GET['sort1'] . ( $_GET['desc1'] ? ' DESC' : '' );
   if( $_GET['sort2'] )
      $order .= "," . $_GET['sort2'] . ( $_GET['desc2'] ? ' DESC' : '' );

   $orderstring = $wrtable->make_sort_string( $_GET['sort1'],
                                              $_GET['desc1'],
                                              $_GET['sort2'],
                                              $_GET['desc2'] );

   $result = mysql_query("SELECT Waitingroom.*,Name,Handle," .
                         "Rating2 AS Rating,Players.ID AS pid " .
                         "FROM Waitingroom,Players " .
                         "WHERE Players.ID=Waitingroom.uid ORDER BY $order");

   echo "<h3><font color=$h3_color><B>". T_("Players waiting") . ":</B></font></h3><p>\n";

   if( mysql_num_rows($result) > 0 )
   {
      $wrtable->add_tablehead(0, T_('Info'), NULL, NULL, true, 92);
      $wrtable->add_tablehead(1, T_('Name'), 'Name', false);
      $wrtable->add_tablehead(2, T_('Nick'), 'Handle', false);
      $wrtable->add_tablehead(3, T_('Rating'), 'Rating', true);
      $wrtable->add_tablehead(4, T_('Comment'), NULL, true);
      $wrtable->add_tablehead(5, T_('Handicap'), 'Handicaptype', false);
      $wrtable->add_tablehead(6, T_('Komi'), 'Komi', true);
      $wrtable->add_tablehead(7, T_('Size'), 'Size', true);
      $wrtable->add_tablehead(8, T_('Rating range'), NULL, true);
      $wrtable->add_tablehead(9, T_('Time limit'), NULL, true);
      $wrtable->add_tablehead(10, T_('#Games'), 'nrGames', true);
      $wrtable->add_tablehead(11, T_('Rated'), 'Rated', true);
      $wrtable->add_tablehead(12, T_('Weekend Clock'), 'WeekendClock', true);

      while( $row = mysql_fetch_array( $result ) )
      {
         $Rating = NULL;
         extract($row);

         if( $_GET['info'] === $ID )
            $info_row = $row;

         if( $Handicaptype == 'conv' or $Handicaptype == 'proper' )
            $Komi = '-';

         $wrow_strings = array();
         if( $wrtable->Is_Column_Displayed[0] )
            $wrow_strings[0] = "<td class=button width=92 align=center>" .
               "<A class=button href=\"waiting_room.php?info=$ID&$orderstring#info\">" .
               "&nbsp;&nbsp;&nbsp;" . T_('Info') . "&nbsp;&nbsp;&nbsp;</A></td>";
         if( $wrtable->Is_Column_Displayed[1] )
            $wrow_strings[1] = "<td nowrap><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
               make_html_safe($Name) . "</font></a></td>";
         if( $wrtable->Is_Column_Displayed[2] )
            $wrow_strings[2] = "<td nowrap><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
               make_html_safe($Handle) . "</font></a></td>";
         if( $wrtable->Is_Column_Displayed[3] )
            $wrow_strings[3] = "<td nowrap>" . echo_rating($Rating,true,$pid) . "&nbsp;</td>";
         if( empty($Comment) )
         {
            $Comment = '&nbsp;';
         }
         if( $wrtable->Is_Column_Displayed[4] )
            $wrow_strings[4] = "<td nowrap>" . make_html_safe($Comment, true) . "</td>";
         if( $wrtable->Is_Column_Displayed[5] )
            $wrow_strings[5] = "<td nowrap>" . $handi_array[$Handicaptype] . "</td>";
         if( $wrtable->Is_Column_Displayed[6] )
            $wrow_strings[6] = "<td>$Komi</td>";
         if( $wrtable->Is_Column_Displayed[7] )
            $wrow_strings[7] = "<td>$Size</td>";
         if( $wrtable->Is_Column_Displayed[8] )
            $wrow_strings[8] = '<td nowrap>' .
               echo_rating_limit($MustBeRated, $Ratingmin, $Ratingmax) .
               "</td>";
         if( $wrtable->Is_Column_Displayed[9] )
            $wrow_strings[9] = '<td nowrap>' .
               echo_time_limit($Maintime, $Byotype, $Byotime, $Byoperiods) .
               "</td>";
         if( $wrtable->Is_Column_Displayed[10] )
            $wrow_strings[10] = "<td>$nrGames</td>";
         if( $wrtable->Is_Column_Displayed[11] )
            $wrow_strings[11] = "<td>$Rated</td>";
         if( $wrtable->Is_Column_Displayed[12] )
            $wrow_strings[12] = "<td>$WeekendClock</td>";

         $wrtable->add_row( $wrow_strings );
      }

      $wrtable->echo_table();
   }
   else
      echo '<p>&nbsp;<p>' . T_('Seems to be empty at the moment.');

   if( $_GET['info'] > 0 and is_array($info_row) )
   {
      show_game_info($info_row, $info_row['pid'] == $player_row['ID']);
   }
   else
      add_new_game_form($info_row['pid'] == $player_row['ID']);

   echo "</center>";

   if( $_GET['info'] > 0 and is_array($info_row) )
      $menu_array[T_('Add new game')] = "waiting_room.php" .
         ($orderstring ? "?$orderstring" : '' ) . "#add" ;

   end_page($menu_array);
}


function echo_rating_limit($MustBeRated, $Ratingmin, $Ratingmax)
{
   if( $MustBeRated == 'N' )
      $Ratinglimit = '-';
   else
   {
      $r1 = echo_rating($Ratingmin+50,false);
      $r2 = echo_rating($Ratingmax-50,false);
      if( $r1 == $r2 )
         $Ratinglimit = sprintf( T_('%s only'), $r1);
      else
         $Ratinglimit = $r1 . ' - ' . $r2;
   }

   return $Ratinglimit;
}

function add_new_game_form()
{
   echo '<a name="add">' . "\n";
   $addgame_form = new Form( 'addgame', 'add_to_waitingroom.php', FORM_POST );
   $addgame_form->add_row( array( 'HEADER', T_('Add new game') ) );

   $addgame_form->add_row( array( 'DESCRIPTION', T_('Comment'),
                                  'TEXTINPUT', 'comment', 40, 40, "" ) );

   $vals = array();

   for($i=1; $i<=10; $i++)
      $vals["$i"] = "$i";

   $addgame_form->add_row( array( 'DESCRIPTION', T_('Number of games to add'),
                                  'SELECTBOX', 'nrGames', 1, $vals, '1', false ) );

   game_settings_form($addgame_form,NULL,NULL,true);

   $rating_array = array();

   for($i=9; $i>0; $i--)
      $rating_array["$i dan"] = $i . ' ' . T_('dan');

   for($i=1; $i<=40; $i++)
      $rating_array["$i kyu"] = $i . ' ' . T_('kyu');


   $addgame_form->add_row( array( 'DESCRIPTION', T_('Require rated opponent'),
                                  'CHECKBOX', 'must_be_rated', 'Y', "", false,
                                  'TEXT', '&nbsp;&nbsp;&nbsp;' . T_('If yes, rating between'),
                                  'SELECTBOX', 'rating1', 1, $rating_array, '40 kyu', false,
                                  'TEXT', T_('and'),
                                  'SELECTBOX', 'rating2', 1, $rating_array, '9 dan', false ) );


   $addgame_form->add_row( array( 'SUBMITBUTTON', 'add_game', T_('Add Game') ) );

   $addgame_form->echo_string();
}

function show_game_info($game_row, $mygame=false)
{
   global $handi_array;

   extract($game_row);

   echo '<p><a name="info">' . "\n";
   echo '<table align=center border=2 cellpadding=3 cellspacing=3>' . "\n";

   echo '<tr><td><b>' . T_('Player') . '<b></td><td>' .
      "<A href=\"userinfo.php?uid=$pid\"><font color=black>$Name ($Handle)</a></td></tr>\n";

   echo '<tr><td><b>' . T_('Rating') . '<b></td><td>' .
      echo_rating($Rating,true,$pid) . "</td></tr>\n";
   echo '<tr><td><b>' . T_('Size') . '<b></td><td>' . $Size . "</td></tr>\n";
   echo '<tr><td><b>' . T_('Komi') . '<b></td><td>' .
      ( ($Handicaptype == 'conv' or $Handicaptype == 'proper') ? '-' : $Komi ) .
      "</td></tr>\n";
   echo '<tr><td><b>' . T_('Handicap') . '<b></td><td>' . $handi_array[$Handicaptype] .
      "</td></tr>\n";
   echo '<tr><td><b>' . T_('Rating range') . '<b></td><td>' .
      echo_rating_limit($MustBeRated, $Ratingmin, $Ratingmax) . "</td></tr>\n";
   echo '<tr><td><b>' . T_('Time limit') . '<b></td><td>' .
      echo_time_limit($Maintime, $Byotype, $Byotime, $Byoperiods) . "</td></tr>\n";
   echo '<tr><td><b>' . T_('Number of games') . '<b></td><td>' . $nrGames . "</td></tr>\n";
   echo '<tr><td><b>' . T_('Rated') . '<b></td><td>' .
      ( $Rated == 'Y' ? T_('Yes') : T_('No') ) . "</td></tr>\n";
   echo '<tr><td><b>' . T_('Clock runs on weekends') . '<b></td><td>' .
      ( $WeekendClock == 'Y' ? T_('Yes') : T_('No') ) . "</td></tr>\n";

   echo '<tr><td><b>' . T_('Comment') . '<b></td><td>' . $Comment . "</td></tr>\n";

   echo "</tr></td></table>\n";

   if( $mygame )
   {
      $delete_form = new Form( 'delete', 'join_waitingroom_game.php', FORM_POST );
      $delete_form->add_row( array( 'SUBMITBUTTON', 'delete', T_('Delete'),
                                    'HIDDEN', 'id', $ID,
                                    'HIDDEN', 'delete', 't') );
      $delete_form->echo_string();
   }
   else
   {
      $join_form = new Form( 'join', 'join_waitingroom_game.php', FORM_POST );
      $join_form->add_row( array( 'DESCRIPTION', T_('Reply'),
                                  'HIDDEN', 'id', $ID,
                                  'TEXTAREA', 'reply', 40, 4, "",
                                  'SUBMITBUTTON', 'join', T_('Join') ) );
      $join_form->echo_string();
   }


}
?>
