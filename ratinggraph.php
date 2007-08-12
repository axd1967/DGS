<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   get_request_user( $uid, $uhandle, true);
   if( $uhandle )
      $where = "Handle='".mysql_addslashes($uhandle)."'";
   elseif( $uid > 0 )
      $where = "ID=$uid";
   else
      error("no_uid");

   $result = mysql_query("SELECT ID,Name,Handle FROM Players WHERE $where")
      or error('mysql_query_failed', 'ratinggraph.find_player');

   if( @mysql_num_rows($result) != 1 )
      error("unknown_user");

   $user_row = mysql_fetch_array($result);

   $uid = $user_row['ID'];

   $CURRENTMONTH = $NOW + GRAPH_RATING_MIN_INTERVAL;
   $CURRENTYEAR = date('Y', $CURRENTMONTH);
   $CURRENTMONTH = date('n', $CURRENTMONTH);

   $startyear = ( @$_REQUEST['startyear'] > 0 ? $_REQUEST['startyear'] : BEGINYEAR );
   $startmonth = ( @$_REQUEST['startmonth'] > 0 ? $_REQUEST['startmonth'] : BEGINMONTH );
   $endyear = ( @$_REQUEST['endyear'] > 0 ? $_REQUEST['endyear'] : $CURRENTYEAR );
   $endmonth = ( @$_REQUEST['endmonth'] > 0 ? $_REQUEST['endmonth'] : $CURRENTMONTH );

   if( $startyear < BEGINYEAR or ( $startyear == BEGINYEAR and $startmonth < BEGINMONTH ))
   {
      $startmonth = BEGINMONTH;
      $startyear = BEGINYEAR;
   }
   else if( $startyear > $CURRENTYEAR or
            ( $startyear == $CURRENTYEAR and $startmonth > $CURRENTMONTH ))
   {
      $startmonth = $CURRENTMONTH;
      $startyear = $CURRENTYEAR;
   }

   if( $endyear > $CURRENTYEAR or ( $endyear == $CURRENTYEAR and $endmonth > $CURRENTMONTH ))
   {
      $endmonth = $CURRENTMONTH;
      $endyear = $CURRENTYEAR;
   }
   else if( $endyear < BEGINYEAR or ( $endyear == BEGINYEAR and $endmonth < BEGINMONTH ))
   {
      $endmonth = BEGINMONTH;
      $endyear = BEGINYEAR;
   }

   if( $endyear < $startyear or ( $endyear == $startyear and $endmonth < $startmonth ))
   {
      swap($startmonth, $endmonth);
      swap($startyear, $endyear);
   }


   start_page(T_('Rating graph for') . ' ' . make_html_safe($user_row['Name'])
            , true, $logged_in, $player_row );

   echo '<center>';

   echo "<h3 class=Header>" . T_('Rating graph for') . ' ' .
            user_reference( REF_LINK, 1, '', $user_row) . "</h3>\n" ;

   $result = mysql_query("SELECT Rating FROM Ratinglog WHERE uid=$uid LIMIT 2")
      or error('mysql_query_failed', 'ratinggraph.find_rating_data');

   if( @mysql_num_rows($result) < 1 )
      echo T_("Sorry, too few rated games to draw a graph") . "\n";
   else
   {
      $show_time = @$_REQUEST['show_time'] == 'y';
      $winpie = (bool)@$_REQUEST['winpie'];
      $bynumber = (bool)@$_REQUEST['bynumber'];
      echo '<img src="ratingpng.php?uid='.$uid .
         ($show_time ? URI_AMP.'show_time=y' : '') .
         ($winpie ? URI_AMP.'winpie=1' : '') .
         ($bynumber ? URI_AMP.'bynumber=1' : '') .
         URI_AMP."date=$NOW" . //force cache refresh
         URI_AMP."startyear=$startyear".URI_AMP."startmonth=$startmonth" .
         URI_AMP."endmonth=$endmonth".URI_AMP."endyear=$endyear\"" .
         " alt=\"" . T_('Rating graph') . "\">\n";
      echo "<p></p>\n";

      $form = new Form( 'date_form', 'ratinggraph.php?uid='.$uid, FORM_POST );

      $months = array( 1 => T_('Jan'), 2 => T_('Feb'), 3 => T_('Mar'), 4 => T_('Apr'),
                      5 => T_('May'), 6 => T_('Jun'), 7 => T_('Jul'), 8 => T_('Aug'),
                      9 => T_('Sep'), 10=> T_('Oct'), 11=> T_('Nov'), 12=> T_('Dec') );

      for( $y = BEGINYEAR; $y <= $CURRENTYEAR; $y++ )
        $years[$y] = $y;

      $row = array( 'DESCRIPTION', T_('From#2'),
                   'SELECTBOX', 'startmonth', 1, $months, $startmonth, false,
                   'SELECTBOX', 'startyear', 1, $years, $startyear, false,
                   'OWNHTML', '&nbsp;&nbsp;',
                   'DESCRIPTION', T_('To#2'),
                   'SELECTBOX', 'endmonth', 1, $months, $endmonth, false,
                   'SELECTBOX', 'endyear', 1, $years, $endyear, false,
                   'OWNHTML', '&nbsp;&nbsp;',
                   'HIDDEN', 'uid', $uid,
                   'HIDDEN', 'winpie', $winpie,
                   'HIDDEN', 'show_time', $show_time,
                   'SUBMITBUTTON', 'submit', T_('Change interval') );
      if( GRAPH_RATING_BY_NUM_ENA )
      {
        array_push($row,
                  'OWNHTML', '&nbsp;&nbsp;',
                  'CHECKBOX', 'bynumber', '1',
                  T_('Games'), $bynumber);
      }
      $form->add_row( $row);

      $form->echo_string(1);
   }

   echo '</center>';

   $menu_array =
      array( T_('Show finished games') => "show_games.php?uid=$uid".URI_AMP."finished=1" );

   end_page(@$menu_array);

}

?>
