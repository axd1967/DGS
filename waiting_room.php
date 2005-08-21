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
require_once( "include/rating.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );
require_once( "include/message_functions.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");
   //not used: init_standard_folders();

   //short descriptions for table
   $handi_array = array( 'conv' => T_('Conventional'),
                         'proper' => T_('Proper'),
                         'nigiri' => T_('Even game'),
                         'double' => T_('Double game') );

   $my_id = $player_row["ID"];
   $my_rating = $player_row["Rating2"];

   $page = "waiting_room.php" . ( @$_GET['info'] > 0 ? '?info=' . $_GET['info'] . URI_AMP : '?' );

   start_page(T_("Waiting room"), true, $logged_in, $player_row, button_style() );

   echo "<center>";

   if(!@$_GET['sort1'])
      $_GET['sort1'] = 'ID';

   if(!@$_GET['sort2'] && $_GET['sort1'] != 'ID')
      $_GET['sort2'] = 'ID';

   $wrtable = new Table( $page, "WaitingroomColumns" );
   $wrtable->add_or_del_column();

   $order = $wrtable->current_order_string();
   $orderstring = $wrtable->current_sort_string();

   $result = mysql_query("SELECT Waitingroom.*,Name,Handle," .
                         "Rating2 AS Rating,RatingStatus,Players.ID AS pid " .
                         "FROM Waitingroom,Players " .
                         "WHERE Players.ID=Waitingroom.uid ORDER BY $order");

   echo "<h3><font color=$h3_color><B>". T_("Players waiting") . ":</B></font></h3><p>\n";

   if( @mysql_num_rows($result) > 0 )
   {
      $wrtable->add_tablehead(0, T_('Info'), NULL, false, true, 92);
      $wrtable->add_tablehead(1, T_('Name'), 'Name', false);
      $wrtable->add_tablehead(2, T_('Userid'), 'Handle', false);
      $wrtable->add_tablehead(3, T_('Rating'), 'Rating', true);
      $wrtable->add_tablehead(4, T_('Comment'));
      $wrtable->add_tablehead(5, T_('Handicap'), 'Handicaptype', false);
      $wrtable->add_tablehead(6, T_('Komi'), 'Komi', true);
      $wrtable->add_tablehead(7, T_('Size'), 'Size', true);
      $wrtable->add_tablehead(8, T_('Rating range'), "Ratingmin".URI_ORDER_CHAR."Ratingmax", true);
      $wrtable->add_tablehead(9, T_('Time limit'));
      $wrtable->add_tablehead(10, T_('#Games'), 'nrGames', true);
      $wrtable->add_tablehead(11, T_('Rated'), 'Rated', true);
      $wrtable->add_tablehead(12, T_('Weekend Clock'), 'WeekendClock', true);
      if( ENA_STDHANDICAP )
         $wrtable->add_tablehead(13, T_('Standard placement'), 'StdHandicap', true);

      while( $row = mysql_fetch_array( $result ) )
      {
         $Rating = NULL;
         extract($row);

         if( @$_GET['info'] === $ID )
            $info_row = $row;

         $calculated = ( $Handicaptype == 'conv' or $Handicaptype == 'proper' );
         $needrating = ( $calculated && !is_numeric($my_rating) );
         //$Komi+= 0;

         $Comment = make_html_safe($Comment, 'cell');
         if( empty($Comment) ) $Comment = '&nbsp;';

         $wrow_strings = array();
         if( $wrtable->Is_Column_Displayed[0] )
            $wrow_strings[0] = str_TD_class_button($player_row["Browser"]) .
               "<A class=button href=\"waiting_room.php?info=$ID".URI_AMP."$orderstring#info\">" .
               "&nbsp;&nbsp;&nbsp;" . T_('Info') . "&nbsp;&nbsp;&nbsp;</A></td>";
         if( $wrtable->Is_Column_Displayed[1] )
            $wrow_strings[1] = "<td nowrap><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
               make_html_safe($Name) . "</font></a></td>";
         if( $wrtable->Is_Column_Displayed[2] )
            $wrow_strings[2] = "<td nowrap><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
               $Handle . "</font></a></td>";
         if( $wrtable->Is_Column_Displayed[3] )
            $wrow_strings[3] = "<td nowrap>" . echo_rating($Rating,true,$pid) . "&nbsp;</td>";
         if( $wrtable->Is_Column_Displayed[4] )
            $wrow_strings[4] = "<td nowrap>" . $Comment . "</td>";
         if( $wrtable->Is_Column_Displayed[5] )
         {
            $wrow_strings[5] = '<td nowrap' .
               ( $needrating ? $wrtable->warning_cell_attb(  T_('No initial rating') ) : '' )
               . '>' . $handi_array[$Handicaptype] . "</td>";
         }
         if( $wrtable->Is_Column_Displayed[6] )
            $wrow_strings[6] = '<td>' . ($calculated ? '-' : $Komi) . '</td>'; 
         if( $wrtable->Is_Column_Displayed[7] )
            $wrow_strings[7] = "<td>$Size</td>";
         if( $wrtable->Is_Column_Displayed[8] )
         {
            list( $Ratinglimit, $good_rating)= echo_rating_limit($MustBeRated, $Ratingmin, $Ratingmax, $my_rating);
            $wrow_strings[8] = '<td nowrap' .
               ( $good_rating ? '' : $wrtable->warning_cell_attb(  T_('Out of range') ) )
               . '>' . $Ratinglimit . "</td>";
         }
         if( $wrtable->Is_Column_Displayed[9] )
            $wrow_strings[9] = '<td nowrap>' .
               echo_time_limit($Maintime, $Byotype, $Byotime, $Byoperiods, 0, 1) .
               "</td>";
         if( $wrtable->Is_Column_Displayed[10] )
            $wrow_strings[10] = "<td>$nrGames</td>";
         if( $wrtable->Is_Column_Displayed[11] )
            $wrow_strings[11] = "<td>".( $Rated == 'Y' ? T_('Yes') : T_('No') )."</td>";
         if( $wrtable->Is_Column_Displayed[12] )
            $wrow_strings[12] = "<td>".( $WeekendClock == 'Y' ? T_('Yes') : T_('No') )."</td>";
         if( ENA_STDHANDICAP )
            if( $wrtable->Is_Column_Displayed[13] )
               $wrow_strings[13] = "<td>".( $StdHandicap == 'Y' ? T_('Yes') : T_('No') )."</td>";

         $wrtable->add_row( $wrow_strings );
      }

      $wrtable->echo_table();
   }
   else
      echo '<p>&nbsp;<p>' . T_('Seems to be empty at the moment.');

   if( @$_GET['info'] > 0 and is_array($info_row) )
   {
      show_game_info($info_row, $info_row['pid'] == $player_row['ID'], $my_rating);
   }
   else
      add_new_game_form();

   echo "</center>";

   if( @$_GET['info'] > 0 and is_array($info_row) )
      $menu_array[T_('Add new game')] = "waiting_room.php" .
         ($orderstring ? "?$orderstring" : '' ) . "#add" ;

   end_page(@$menu_array);
}


function echo_rating_limit($MustBeRated, $Ratingmin, $Ratingmax, $my_rating=false)
{
   if( $MustBeRated != 'Y' )
      return array('-', true);

   // +/-50 reverse the inflation from add_to_waitingroom.php
   $r1 = echo_rating($Ratingmin+50,false);
   $r2 = echo_rating($Ratingmax-50,false);
   if( $r1 == $r2 )
      $Ratinglimit = sprintf( T_('%s only'), $r1);
   else
      $Ratinglimit = $r1 . ' - ' . $r2;
   if( is_numeric($my_rating) )
      $good = ( $my_rating>=$Ratingmin && $my_rating<=$Ratingmax );
   else
      $good = false;
   return array($Ratinglimit, $good);
}

function add_new_game_form()
{
   echo '<a name="add"></a>' . "\n";
   $addgame_form = new Form( 'addgame', 'add_to_waitingroom.php', FORM_POST );
   $addgame_form->add_row( array( 'HEADER', T_('Add new game') ) );

   $vals = array();

   for($i=1; $i<=10; $i++)
      $vals["$i"] = "$i";

   $addgame_form->add_row( array( 'DESCRIPTION', T_('Number of games to add'),
                                  'SELECTBOX', 'nrGames', 1, $vals, '1', false ) );

   game_settings_form($addgame_form,NULL,NULL,true);

   $rating_array = array();

   $s = ' ' . T_('dan');
   for($i=9; $i>0; $i--)
      $rating_array["$i dan"] = $i . $s;

   $s = ' ' . T_('kyu');
   for($i=1; $i<=30; $i++)
      $rating_array["$i kyu"] = $i . $s;


   $addgame_form->add_row( array( 'DESCRIPTION', T_('Require rated opponent'),
                                  'CHECKBOX', 'must_be_rated', 'Y', "", false,
                                  'TEXT', '&nbsp;&nbsp;&nbsp;' . T_('If yes, rating between'),
                                  'SELECTBOX', 'rating1', 1, $rating_array, '30 kyu', false,
                                  'TEXT', T_('and'),
                                  'SELECTBOX', 'rating2', 1, $rating_array, '9 dan', false ) );


   $addgame_form->add_row( array( 'SPACE' ) );
   $addgame_form->add_row( array( 'DESCRIPTION', T_('Comment'),
                                  'TEXTINPUT', 'comment', 40, 40, "" ) );
   $addgame_form->add_row( array( 'SPACE' ) );


   $addgame_form->add_row( array( 'SUBMITBUTTON', 'add_game', T_('Add Game') ) );

   $addgame_form->echo_string(1);
}

function show_game_header($str)
{
  global $h3_color;

//Rdvl:from 'My user info' style:
//   echo "<h3><font color=$h3_color>" . $title . '</font></h3>';

   return   '<tr><td colspan=99 align="center">' . 
            "&nbsp;<B><font color=$h3_color>" . 
            $str . ":</font></B>&nbsp;</td></tr>\n";
}

function show_game_row( $info, $cell, $hilight=false, $warning='')
{
   $info = eregi_replace('<BR>',' ',$info); //allow 2 lines long headers
   return '<tr><td><b>' . $info . '</b></td><td' .
         ( $hilight ? blend_warning_cell_attb( $warning ) : '' )
       . '>' . $cell . "</td></tr>\n";
}

function show_game_info($game_row, $mygame=false, $my_rating=false)
{
   global $bg_color;
   //long descriptions for box
   $handi_array = array( 'conv' => T_('Conventional handicap (komi 0.5 if not even)'),
                         'proper' => T_('Proper handicap'),
                         'nigiri' => T_('Even game with nigiri'),
                         'double' => T_('Double game') );

   extract($game_row);
   $calculated = ( $Handicaptype == 'conv' or $Handicaptype == 'proper' );
   $needrating = ( $calculated && !is_numeric($my_rating) );
   //$Komi+= 0;

   echo '<p><a name="info"></a>' . "\n";
   echo '<table align=center border=2 cellpadding=3 cellspacing=3>' . "\n";

   echo show_game_header(T_('Info'));

   echo show_game_row( T_('Player'), user_reference( REF_LINK, 1, "black", $pid, $Name, $Handle));

   echo show_game_row( T_('Rating'), echo_rating($Rating,true,$pid));
   echo show_game_row( T_('Size'), $Size);
   echo show_game_row( T_('Handicap'), $handi_array[$Handicaptype]
         , $needrating, T_('No initial rating'));
   echo show_game_row( T_('Komi'), $calculated ? '-' : $Komi);

   list( $Ratinglimit, $good_rating)= echo_rating_limit($MustBeRated, $Ratingmin, $Ratingmax, $my_rating);
   echo show_game_row( T_('Rating range'), $Ratinglimit
         , !$good_rating, T_('Out of range'));
   echo show_game_row( T_('Time limit'), echo_time_limit($Maintime, $Byotype, $Byotime, $Byoperiods));
   echo show_game_row( T_('Number of games'), $nrGames);
   echo show_game_row( T_('Rated'), $Rated == 'Y' ? T_('Yes') : T_('No'));
   echo show_game_row( T_('Clock runs on weekends'), $WeekendClock == 'Y' ? T_('Yes') : T_('No'));
   if( ENA_STDHANDICAP )
      echo show_game_row( T_('Standard placement'), $StdHandicap == 'Y' ? T_('Yes') : T_('No'));

   $Comment = make_html_safe($Comment, true);
   //if( empty($Comment) ) $Comment = '&nbsp;';
   echo show_game_row( T_('Comment'), $Comment);

   if( !$mygame && $good_rating && !$needrating)
   {
   /* Not useful here, because RatingStatus couldn't be empty to accept the match:
      $infoRated = (( $Rated === 'Y' and
                  !empty($RatingStatus) and
                  !empty($player_row['RatingStatus']) ) ? 'Y' : 'N' );
   */

      if( $Handicaptype == 'proper' )
         list($infoHandicap,$infoKomi,$swap) = suggest_proper($Rating, $my_rating, $Size);
      else if( $Handicaptype == 'conv' )
         list($infoHandicap,$infoKomi,$swap) = suggest_conventional($Rating, $my_rating, $Size);
      else
      {
         $infoHandicap = 0; $infoKomi = $Komi; $swap = 0;
      }

      $colortxt = '<img align="top" src="17/';
      if( $Handicaptype == 'double' )
         $colortxt = $colortxt . 'w.gif" alt="' . T_('White') . '">&nbsp;+&nbsp;' .
                     $colortxt . 'b.gif" alt="' . T_('Black') . '">' ;
      else if( $Handicaptype == 'nigiri' 
            or $Handicaptype == 'conv' && $infoHandicap == 0 && $infoKomi == 6.5 )
         $colortxt = $colortxt . 'y.gif" alt="' . T_('Nigiri') . '">' ;
      else if( $swap )
         $colortxt = $colortxt . 'w.gif" alt="' . T_('White') . '">' ;
      else
         $colortxt = $colortxt . 'b.gif" alt="' . T_('Black') . '">' ;

      //echo "<tr height=20><td colspan=2 height=20></td></tr>\n";
      echo show_game_header(T_('Probable settings'));

      echo show_game_row( T_('Color'), $colortxt);
      echo show_game_row( T_('Handicap'), $infoHandicap);
      echo show_game_row( T_('Komi'), sprintf("%.1f",$infoKomi));
   }

   echo "</table>\n";


   if( $mygame )
   {
      $delete_form = new Form( 'delete', 'join_waitingroom_game.php', FORM_POST );
      $delete_form->add_row( array( 'SUBMITBUTTON', 'deletebut', T_('Delete'),
                                    'HIDDEN', 'id', $ID,
                                    'HIDDEN', 'delete', 't') );
      $delete_form->echo_string(1);
   }
   else if( $good_rating && !$needrating )
   {
      $join_form = new Form( 'join', 'join_waitingroom_game.php', FORM_POST );
      $join_form->add_row( array( 'DESCRIPTION', T_('Reply'),
                                  'HIDDEN', 'id', $ID,
                                  'TEXTAREA', 'reply', 40, 4, "",
                                  'SUBMITBUTTON', 'join', T_('Join') ) );
      $join_form->echo_string(1);
   }

}
?>
