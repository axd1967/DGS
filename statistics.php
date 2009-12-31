<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Docs";

require_once( "include/std_functions.php" );
require_once( "include/gui_functions.php" );
require_once( "include/countries.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   $page = "statistics.php";

   // stats: 0=default, 1=user-countries
   $stats = (int)get_request_arg('stats', 0);

   $statsmenu = array();
   $statsmenu[T_('Standard view')] = $page;
   $statsmenu[T_('User countries')] = "$page?stats=1";

   if( $stats == 1 )
      show_stats_user_countries();
   else // default: stats=0 or illegal
   {
      $title = T_('Statistics');
      stats_start_page( $title );

      make_menu( $statsmenu );
      echo "<hr>\n";

      show_stats_default();
   }

   if( $stats > 0 )
   {
      echo "<hr>\n";
      make_menu( $statsmenu );
   }

   end_page();
}

function stats_start_page( $title )
{
   global $logged_in, $player_row;

   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=\"Header\">$title</h3>\n";
}


function show_stats_default()
{
   global $ActivityForHit, $player_row, $NOW;

   $q1 = "SELECT Status,SUM(Moves) AS moves, COUNT(*) AS count FROM Games GROUP BY Status";
   $q2 = "SELECT SUM(Moves) AS moves, COUNT(*) AS count FROM Games";
   $q3 = "SELECT SUM(Hits) AS hits, COUNT(*) AS count, SUM(Activity)/$ActivityForHit AS activity FROM Players";

   $result = db_query( 'statistics.games.moves', $q1 );

   echo "<table border=1>\n"
      , "<tr><th>Status</th><th>Moves</th><th>Games</th></tr>\n";

   while( $row = mysql_fetch_array( $result ) )
   {
      echo '<tr>'
         , "<td>{$row['Status']}</td>"
         , "<td align=\"right\">{$row['moves']}</td>"
         , "<td align=\"right\">{$row['count']}</td>"
         , "</tr>\n";
   }
   mysql_free_result($result);

   $row = mysql_single_fetch( 'statistics.q2', $q2 );
   if( $row )
   {
      echo '<tr><td>Total</td><td align="right">', $row["moves"]
         , '</td><td align="right">', $row["count"], "</td></tr>\n";
   }
   echo "</table>\n";

   $row = mysql_single_fetch( 'statistics.q3', $q3 );
   if( $row )
   {
      echo '<p>', $row["hits"], ' hits by ', $row["count"], ' players</p>';
      echo '<p>Activity: ', round($row['activity']), "</p>\n";
   }

   //echo '<p></p>Loadavg: ' . `cat /proc/loadavg`; //only under Linux like systems and with safe_mode=off
   if( (@$player_row['admin_level'] & ADMIN_DEVELOPER) /* && @$_REQUEST['debug'] */ )
   {
      //FIXME: Only working for Linux ?
      $tmp = '/proc/loadavg';
      if( ($tmp=trim(@read_from_file($tmp))) )
      {
         echo '<p><span class=DebugInfo>Loadavg: ', $tmp, '</span></p>';
      }
   }

   $args= array();
   $args['show_time']= (int)(bool)@$_REQUEST['show_time'];
   $args['activity']= (int)(bool)@$_REQUEST['activity'];
   $args['dyna']= floor($NOW/CACHE_EXPIRE_GRAPH); //force caches refresh
   $args= make_url('?', $args);

   $title = T_('Statistics graph');
   echo "<h3 class=Header>$title</h3>\n";
   echo "<img src=\"statisticspng.php$args\" alt=\"$title\">\n";

   $title = T_('Rating histogram');
   echo "<h3 class=Header>$title</h3>\n";
   echo "<img src=\"statratingspng.php$args\" alt=\"$title\">\n";
}//show_stats_default

function show_stats_user_countries()
{
   global $NOW, $page;

   $weeks = (int)get_request_arg('w',0);
   $last_access = $NOW - $weeks * 7 * SECS_PER_DAY;

   $page_user = "$page?stats=1";
   $title = T_('Statistics - User countries');
   stats_start_page( $title );

   $userstatsmenu = array();
   $userstatsmenu[T_('All users')] = $page_user;
   $userstatsmenu[T_('Users online within 8 weeks')] = $page_user.URI_AMP."w=8";
   make_menu( $userstatsmenu, false );


   $result = db_query( 'statistics.users.countries',
      "SELECT Country, COUNT(*) AS X_Count " .
      "FROM Players " .
      ( $weeks > 0 ? "WHERE Lastaccess>=FROM_UNIXTIME($last_access) " : '' ) .
      "GROUP BY Country " .
      "ORDER BY X_Count DESC" );

   echo "<table id=\"Statistics\" class=\"Table\">\n"
      , sprintf( "<tr><th>%s</th><th>%s</th><th>%s</th></tr>\n", T_('Count'), T_('Flag#country'), T_('Country') );

   $arr_countries = getCountryText();
   $total = 0;
   while( $row = mysql_fetch_array( $result ) )
   {
      $ccode = $row['Country'];
      $total += $row['X_Count'];
      unset($arr_countries[$ccode]);

      $ccimg = getCountryFlagImage($ccode);
      if( $ccode == '' )
         $ccimg = NO_VALUE;
      elseif( $ccimg == '' )
         $ccimg = "[$ccode]";

      $cctxt = getCountryText($ccode);
      if( $ccode == '' )
         $cctxt = T_('Unset');
      elseif( $cctxt == '' )
         $cctxt = NO_VALUE;

      echo sprintf( "<tr><td class=\"Number\">%s</td><td class=\"Image\">%s</td><td>%s</td></tr>\n",
                    $row['X_Count'], $ccimg, $cctxt );
   }
   mysql_free_result($result);
   echo sprintf( "<tr><td class=\"Number\"><b>%s</b></td><td class=\"Image\">%s</td><td><b>%s</b></td></tr>\n",
                 $total, '', T_('Total#stats') );
   echo "</table>\n";

   echo "<br>\n", T_('Remaining countries'), ":\n<p>\n";
   asort($arr_countries);
   foreach( $arr_countries as $ccode => $cctxt )
   {
      echo getCountryFlagImage($ccode), SMALL_SPACING, "\n";
   }
}

?>
