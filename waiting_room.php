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
include( "include/rating.php" );
include( "include/table_columns.php" );
include( "include/form_functions.php" );
include( "include/message_functions.php" );

$table_columns = array('ID','sgf','Opponent','Nick','Color','Size','Handicap','Komi',
                       'Moves','Score','Win?','End date','Last Move');



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


   start_page(T_("Waiting room"), true, $logged_in, $player_row, $style );

   echo "<center>";

   if( $msg )
      echo "<p><b><font color=\"green\">$msg</font></b><hr>";

   $page = "waiting_room.php?";

   if(!$sort1)
      $sort1 = 'ID';

   $order = $sort1 . ( $desc1 ? ' DESC' : '' );
   if( $sort2 )
      $order .= ",$sort2" . ( $desc2 ? ' DESC' : '' );

   $result = mysql_query("SELECT Waitingroom.*,Name,Handle,Rating,Players.ID as pid " .
                         "FROM Waitingroom,Players " .
                         "WHERE Players.ID=Waitingroom.uid ORDER BY $order");

   echo "<h3><font color=$h3_color><B>". T_("Players waiting") . ":</B></font></h3><p>\n";

   if( mysql_num_rows($result) > 0 )
   {

      $column_set = 1;
      echo start_end_column_table(true) .
         tablehead(1, T_('Join'), NULL, true, true, 92) .
         tablehead(1, T_('Name'), 'Name', false, true) .
         tablehead(1, T_('Nick'), 'Handle', false, true) .
         tablehead(1, T_('Rating'), 'Rating', false, true) .
         tablehead(1, T_('Comment'), NULL, true, true) .
         tablehead(1, T_('Handicap'), 'Handicaptype', false, true) .
         tablehead(1, T_('Komi'), 'Komi', true, true) .
         tablehead(1, T_('Size'), 'Size', true, true) .
         tablehead(1, T_('Rating range'), NULL, true, true) .
         tablehead(1, T_('Time limit'), NULL, true, true) .
         tablehead(1, T_('#Games'), 'nrGames', true, true) .
         tablehead(1, T_('Rated'), 'Rated', true, true) .
            tablehead(1, T_('Weekend Clock'), 'WeekendClock', true, true);

      echo "</tr>\n";

      $handi_array = array( 'conv' => T_('Conventional'),
                            'proper' => T_('Proper'),
                            'nigiri' => T_('Even game with nigiri'),
                            'double' => T_('Double game') );
      $row_color=2;
      while( $row = mysql_fetch_array( $result ) )
      {
         $Rating = NULL;
         extract($row);

         $row_color=3-$row_color;
         echo "<tr bgcolor=" . ${"table_row_color$row_color"} . ">\n";

         if( $Handicaptype == 'conv' or $Handicaptype == 'proper' )
            $Komi = '-';

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

         if( $pid == $player_row['ID'] )
            echo "<td class=button width=92 align=center><A class=button href=\"join_waitingroom_game.php?id=$ID&delete=t\">&nbsp;&nbsp;&nbsp;" . T_('Delete') . "&nbsp;&nbsp;&nbsp;</A></td>\n";
         else
            echo "<td class=button width=92 align=center><A class=button href=\"join_waitingroom_game.php?id=$ID\">&nbsp;&nbsp;&nbsp;" . T_('Join') . "&nbsp;&nbsp;&nbsp;</A></td>\n";
         echo "<td nowrap><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
            make_html_safe($Name) . "</font></a></td>\n";
         echo "<td nowrap><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
            make_html_safe($Handle) . "</font></a></td>\n";
         echo "<td nowrap>" . echo_rating($Rating) . "&nbsp;</td>\n";
         if( empty($Comment) ) $Comment = '&nbsp;';
         echo "<td nowrap>" . make_html_safe($Comment, true) . "</td>\n";
         echo "<td nowrap>" . $handi_array[$Handicaptype] . "</td>\n";
         echo "<td>$Komi</td>\n";
         echo "<td>$Size</td>\n";
         echo "<td nowrap>$Ratinglimit</td>\n";
         echo "<td nowrap>" . echo_time_limit($Maintime, $Byotype, $Byotime, $Byoperiods) . "</td>\n";
         echo "<td>$nrGames</td>\n";
         echo "<td>$Rated</td>\n";
         echo "<td>$WeekendClock</td>\n";

      }

      echo start_end_column_table(false);
   }
   else
      echo '<p>&nbsp;<p>' . T_('Seems to be empty at the moment.');


   // Add new game form

   echo "<p>&nbsp;<p><hr>";

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

   echo "</center>";

   end_page();
}
?>
