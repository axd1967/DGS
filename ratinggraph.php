<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
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

   $result = db_query( "ratinggraph.find_player($uid,$uhandle)",
      "SELECT ID,Name,Handle FROM Players WHERE $where LIMIT 1" );

   if( @mysql_num_rows($result) != 1 )
      error("unknown_user");

   $user_row = mysql_fetch_array($result);

   $uid = $user_row['ID'];

   $CURRENTMONTH = $NOW + GRAPH_RATING_MIN_INTERVAL;
   $CURRENTYEAR = date('Y', $CURRENTMONTH);
   $CURRENTMONTH = date('n', $CURRENTMONTH);

   $startyear = (int)get_request_arg( 'startyear', BEGINYEAR );
   $startmonth = (int)get_request_arg( 'startmonth', BEGINMONTH );
   $endyear = (int)get_request_arg( 'endyear', $CURRENTYEAR );
   $endmonth = (int)get_request_arg( 'endmonth', $CURRENTMONTH );

   if( $startyear < BEGINYEAR
      || ( $startyear == BEGINYEAR && $startmonth < BEGINMONTH ) )
   {
      $startmonth = BEGINMONTH;
      $startyear = BEGINYEAR;
   }
   else if( $startyear > $CURRENTYEAR
      || ( $startyear == $CURRENTYEAR && $startmonth > $CURRENTMONTH ) )
   {
      $startmonth = $CURRENTMONTH;
      $startyear = $CURRENTYEAR;
   }

   if( $endyear > $CURRENTYEAR
      || ( $endyear == $CURRENTYEAR && $endmonth > $CURRENTMONTH ) )
   {
      $endmonth = $CURRENTMONTH;
      $endyear = $CURRENTYEAR;
   }
   else if( $endyear < BEGINYEAR
      || ( $endyear == BEGINYEAR && $endmonth < BEGINMONTH ) )
   {
      $endmonth = BEGINMONTH;
      $endyear = BEGINYEAR;
   }

   if( $endyear < $startyear
      || ( $endyear == $startyear && $endmonth < $startmonth ) )
   {
      swap($startmonth, $endmonth);
      swap($startyear, $endyear);
   }


   start_page(T_('Rating graph for') . ' ' . make_html_safe($user_row['Name'])
            , true, $logged_in, $player_row );

   echo "<h3 class=Header>" . T_('Rating graph for') . ' ' .
            user_reference( REF_LINK, 1, '', $user_row) . "</h3>\n" ;

   $result = db_query( "ratinggraph.find_rating_data($uid)",
         "SELECT Rating FROM Ratinglog WHERE uid=$uid LIMIT 2" );
   if( @mysql_num_rows($result) < 1 )
      echo T_("Sorry, too few rated games to draw a graph") ,"\n";
   else
   {
      $show_time = (int)(bool)@$_REQUEST['show_time'];
      $dyna= floor($NOW/(3600/TICK_FREQUENCY));
      $winpie = (bool)@$_REQUEST['winpie'];
      $bynumber = (bool)@$_REQUEST['bynumber'];

      echo "\n<img src=\"ratingpng.php?uid=$uid"
         ,($show_time ? URI_AMP.'show_time=1' : '')
         ,($winpie ? URI_AMP.'winpie=1' : '')
         ,(GRAPH_RATING_BY_NUM_ENABLED && $bynumber ? URI_AMP.'bynumber=1' : '')
         ,URI_AMP,"dyna=$dyna" //force caches refresh
         ,URI_AMP,"startyear=$startyear"
         ,URI_AMP,"startmonth=$startmonth"
         ,URI_AMP,"endyear=$endyear"
         ,URI_AMP,"endmonth=$endmonth"
         ,"\" alt=\"" ,T_('Rating graph') ,"\">";
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
      if( GRAPH_RATING_BY_NUM_ENABLED )
      {
        array_push($row,
                  'OWNHTML', '&nbsp;&nbsp;',
                  'CHECKBOX', 'bynumber', '1',
                  T_('Games'), $bynumber);
      }
      $form->add_row( $row);

      $form->echo_string(1);
   }

   $menu_array =
      array( T_('Show finished games') => "show_games.php?uid=$uid".URI_AMP."finished=1" );

   end_page(@$menu_array);

}

?>
