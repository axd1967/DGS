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
require_once( "include/form_functions.php" );

{
  connect2mysql();

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

  if( !($uid > 0) )
  {
     if( eregi("[?&]uid=([0-9]+)", $HTTP_REFERER, $result) )
        $uid = $result[1];
  }

  if( !($uid > 0) )
     error("no_uid");

  $CURRENTYEAR = date('Y', $NOW);
  $CURRENTMONTH = date('n', $NOW);



  $result = mysql_query("SELECT Name FROM Players where ID=$uid");

  if( mysql_num_rows($result) != 1 )
     error("unknown user");

  $row = mysql_fetch_array($result);

  start_page(T_('Rating graph for') . ' ' . $row['Name'], true, $logged_in, $player_row );

  echo '<center>';


  echo "<h3><font color=$h3_color>" .
     T_('Rating graph for') . " <A href=\"userinfo.php?uid=$uid\">" . $row['Name'] .
     "</A></font></h3><p>\n";

  $result = mysql_query("SELECT Rating FROM Ratinglog WHERE uid=$uid LIMIT 2");

  $startyear = ( $_GET['startyear'] > 0 ? $_GET['startyear'] : $BEGINYEAR );
  $startmonth = ( $_GET['startmonth'] > 0 ? $_GET['startmonth'] : $BEGINMONTH );
  $endyear = ( $_GET['endyear'] > 0 ? $_GET['endyear'] : $CURRENTYEAR );
  $endmonth = ( $_GET['endmonth'] > 0 ? $_GET['endmonth'] : $CURRENTMONTH );

  if( $startyear < $BEGINYEAR or ( $startyear == $BEGINYEAR and $startmonth < $BEGINMONTH ))
  {
     $startmonth = $BEGINMONTH;
     $startyear = $BEGINYEAR;
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
  else if( $endyear < $BEGINYEAR or ( $endyear == $BEGINYEAR and $endmonth < $BEGINMONTH ))
  {
     $endmonth = $BEGINMONTH;
     $endyear = $BEGINYEAR;
  }

  if( $endyear < $startyear or ( $endyear == $startyear and $endmonth < $startmonth ))
  {
     swap($startmonth, $endmonth);
     swap($startyear, $endyear);
  }

  if( mysql_num_rows($result) < 2 )
     echo T_("Sorry, too few rated games to draw a graph") . "\n";
  else
     echo '<img src="ratingpng.php?uid=' . $uid .
        ($_GET['show_time'] == 'y' ? '&show_time=y' : '') . "&startyear=$startyear&startmonth=$startmonth&endmonth=$endmonth&endyear=$endyear\">\n";

  echo "<p>\n";

  $form = new Form( 'date_form', 'ratinggraph.php', FORM_GET );

  $months = array( 1 => T_('Jan'), 2 => T_('Feb'), 3 => T_('Mar'), 4 => T_('Apr'),
                   5 => T_('May'), 6 => T_('Jun'), 7 => T_('Jul'), 8 => T_('Aug'),
                   9 => T_('Sep'), 10=> T_('Oct'), 11=> T_('Nov'), 12=> T_('Dec') );

  for( $y = $BEGINYEAR; $y <= $CURRENTYEAR; $y++ )
     $years[$y] = $y;

  $form->add_row( array( 'HIDDEN', 'uid', $uid,
                         'DESCRIPTION', T_('From#2'),
                         'SELECTBOX', 'startmonth', 1, $months, $startmonth, false,
                         'SELECTBOX', 'startyear', 1, $years, $startyear, false,
                         'OWNHTML', '&nbsp;&nbsp;',
                         'DESCRIPTION', T_('To#2'),
                         'SELECTBOX', 'endmonth', 1, $months, $endmonth, false,
                         'SELECTBOX', 'endyear', 1, $years, $endyear, false,
                         'OWNHTML', '&nbsp;&nbsp;',
                         'SUBMITBUTTON', 'submit', T_('Change interval') ) );

  $form->echo_string();


  echo '</center>';

  end_page();
}

?>
